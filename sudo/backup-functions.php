<?php
// ══════════════════════════════════════════════════════════════════
//  backup-functions.php
//  Shared database-backup logic used by BOTH cron/backup_database.php
//  (the daily scheduled run) and sudo/settings.php (the superadmin's
//  "Backup Now" button + backup list/download/delete). Kept in one
//  place so the two call sites can't drift.
//
//  Pure PHP + mysqli, no shell-out to `mysqldump` — see the long
//  comment in cron/backup_database.php for why.
// ══════════════════════════════════════════════════════════════════

if (!function_exists('db_backup_dir')) {
    function db_backup_dir()
    {
        $dir = __DIR__ . '/../backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $htaccessPath = $dir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "# Full database dumps — never web-accessible.\nRequire all denied\n");
        }

        return $dir;
    }
}

if (!function_exists('db_backup_retention_days')) {
    function db_backup_retention_days()
    {
        return max(1, (int) env('DB_BACKUP_RETENTION_DAYS', 7));
    }
}

if (!function_exists('is_valid_backup_filename')) {
    // Whitelists the exact naming scheme run_database_backup() produces.
    // Both download-backup.php and settings.php's delete handler run
    // user-suppliable filenames through this before touching the
    // filesystem, so it doubles as the path-traversal guard.
    function is_valid_backup_filename($filename)
    {
        return is_string($filename) && preg_match('/^itasker_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/', $filename) === 1;
    }
}

if (!function_exists('format_backup_size')) {
    function format_backup_size($bytes)
    {
        $bytes = (float) $bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}

if (!function_exists('list_database_backups')) {
    function list_database_backups()
    {
        $dir = db_backup_dir();
        $backups = [];
        foreach (glob($dir . '/itasker_*.sql.gz') ?: [] as $path) {
            $filename = basename($path);
            if (!is_valid_backup_filename($filename)) {
                continue;
            }
            $backups[] = [
                'filename' => $filename,
                'size'     => filesize($path),
                'mtime'    => filemtime($path),
            ];
        }
        usort($backups, function ($a, $b) { return $b['mtime'] <=> $a['mtime']; });
        return $backups;
    }
}

if (!function_exists('run_database_backup')) {
    /**
     * Dumps every table (structure + data) to a new gzip-compressed
     * .sql.gz file. Data is read out in CHUNK_SIZE-row pages via
     * mysqli so a large table never has to be held in memory at once.
     * Returns ['success' => bool, 'file'|'error' => ..., 'size', 'tables', 'rows', 'elapsed'].
     */
    function run_database_backup($con)
    {
        $chunkSize = 500;
        $startTime = microtime(true);
        $dir = db_backup_dir();

        if (!is_writable($dir)) {
            return ['success' => false, 'error' => 'Backup directory is not writable: ' . $dir];
        }

        $filename = 'itasker_' . date('Y-m-d_His') . '.sql.gz';
        $filepath = $dir . '/' . $filename;

        $gz = gzopen($filepath, 'wb9');
        if (!$gz) {
            return ['success' => false, 'error' => 'Could not open backup file for writing.'];
        }

        try {
            $dbName = defined('DB_NAME') ? DB_NAME : env('DB_NAME');
            gzwrite($gz, "-- iTasker database backup\n");
            gzwrite($gz, "-- Database: $dbName\n");
            gzwrite($gz, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
            gzwrite($gz, "SET NAMES utf8mb4;\n");
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $tablesResult = mysqli_query($con, 'SHOW TABLES');
            if (!$tablesResult) {
                throw new RuntimeException('SHOW TABLES failed: ' . mysqli_error($con));
            }

            $tableCount = 0;
            $totalRows = 0;
            $skippedTables = [];

            while ($tableRow = mysqli_fetch_row($tablesResult)) {
                $table = $tableRow[0];

                $createResult = mysqli_query($con, "SHOW CREATE TABLE `$table`");
                if (!$createResult) {
                    $skippedTables[] = $table;
                    continue;
                }
                $createRow = mysqli_fetch_row($createResult);

                gzwrite($gz, "-- --------------------------------------------------\n");
                gzwrite($gz, "-- Table: `$table`\n");
                gzwrite($gz, "-- --------------------------------------------------\n");
                gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n");
                gzwrite($gz, $createRow[1] . ";\n\n");

                $countResult = mysqli_query($con, "SELECT COUNT(*) AS c FROM `$table`");
                $rowCount = $countResult ? (int) mysqli_fetch_assoc($countResult)['c'] : 0;

                if ($rowCount > 0) {
                    $columns = null;
                    for ($offset = 0; $offset < $rowCount; $offset += $chunkSize) {
                        $chunkResult = mysqli_query($con, "SELECT * FROM `$table` LIMIT $offset, $chunkSize");
                        if (!$chunkResult) {
                            continue;
                        }

                        if ($columns === null) {
                            $columns = array_map(function ($f) { return "`{$f->name}`"; }, mysqli_fetch_fields($chunkResult));
                        }
                        $columnList = implode(',', $columns);

                        // Flush on row count OR accumulated byte size, so a
                        // handful of big TEXT columns (task descriptions,
                        // chat messages) can't produce a single INSERT that
                        // blows past MySQL's max_allowed_packet on restore.
                        $valueTuples = [];
                        $batchBytes = 0;
                        while ($row = mysqli_fetch_row($chunkResult)) {
                            $escaped = array_map(function ($v) use ($con) {
                                return $v === null ? 'NULL' : "'" . mysqli_real_escape_string($con, $v) . "'";
                            }, $row);
                            $tuple = '(' . implode(',', $escaped) . ')';
                            $valueTuples[] = $tuple;
                            $batchBytes += strlen($tuple);
                            $totalRows++;

                            if (count($valueTuples) >= 100 || $batchBytes >= 1048576) {
                                gzwrite($gz, "INSERT INTO `$table` ($columnList) VALUES\n" . implode(",\n", $valueTuples) . ";\n");
                                $valueTuples = [];
                                $batchBytes = 0;
                            }
                        }

                        if ($valueTuples) {
                            gzwrite($gz, "INSERT INTO `$table` ($columnList) VALUES\n" . implode(",\n", $valueTuples) . ";\n");
                        }
                    }
                }

                gzwrite($gz, "\n");
                $tableCount++;
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
            gzclose($gz);
        } catch (Throwable $e) {
            gzclose($gz);
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($tableCount === 0) {
            unlink($filepath);
            return ['success' => false, 'error' => 'No tables were backed up.'];
        }

        return [
            'success' => true,
            'file'    => $filename,
            'size'    => filesize($filepath),
            'tables'  => $tableCount,
            'rows'    => $totalRows,
            'skipped' => $skippedTables,
            'elapsed' => round(microtime(true) - $startTime, 1),
        ];
    }
}

if (!function_exists('prune_old_backups')) {
    // Deletes backups older than db_backup_retention_days(). Returns the
    // list of filenames actually deleted (empty array if none/none due).
    function prune_old_backups()
    {
        $dir = db_backup_dir();
        $cutoff = time() - db_backup_retention_days() * 86400;
        $deleted = [];

        foreach (glob($dir . '/itasker_*.sql.gz') ?: [] as $path) {
            if (filemtime($path) < $cutoff && unlink($path)) {
                $deleted[] = basename($path);
            }
        }

        return $deleted;
    }
}

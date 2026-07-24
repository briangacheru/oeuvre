<?php
// ══════════════════════════════════════════════════════════════════
//  backup_database.php
//  Dumps the entire database (schema + data, every table) to a single
//  gzip-compressed .sql.gz file under cron/../backups/, then deletes
//  backups older than DB_BACKUP_RETENTION_DAYS (default 14).
//
//  Pure PHP + mysqli — deliberately does NOT shell out to `mysqldump`.
//  Shared/cPanel hosting (which is what this app runs on — see the
//  crontab paths in the other cron/*.php files) very often has
//  shell_exec/exec/proc_open disabled via disable_functions for
//  security, which would make a mysqldump-based script silently fail.
//  Reading rows through mysqli in chunks needs no shell access at all.
//
//  Restore with:
//    gunzip -c backups/itasker_2026-07-24_030000.sql.gz | mysql -u USER -p DBNAME
//
//  Crontab example (once a day at 03:00 Nairobi time, before the
//  03:30 session-cleanup job):
//    0 3 * * * /usr/local/bin/ea-php82 /home/monkbria/web.monkbrian.com/cron/backup_database.php >> /home/monkbria/web.monkbrian.com/cron/backup_database.log 2>&1
// ══════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../sudo/dbcon.php';

date_default_timezone_set('Africa/Nairobi');

const CHUNK_SIZE = 500;

function logLine($msg)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

function backupEscape($con, $value)
{
    if ($value === null) {
        return 'NULL';
    }
    return "'" . $con->real_escape_string($value) . "'";
}

$startTime = microtime(true);

// ── 1. Prepare the backup directory (created + locked down if missing) ──
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0750, true)) {
        logLine('FATAL: could not create backup directory ' . $backupDir);
        exit(1);
    }
}

$htaccessPath = $backupDir . '/.htaccess';
if (!file_exists($htaccessPath)) {
    file_put_contents($htaccessPath, "# Full database dumps — never web-accessible.\nRequire all denied\n");
}

// ── 2. Open the gzip-compressed output file ──────────────────────────
$filename = 'itasker_' . date('Y-m-d_His') . '.sql.gz';
$filepath = $backupDir . '/' . $filename;

$gz = gzopen($filepath, 'wb9');
if (!$gz) {
    logLine("FATAL: could not open $filepath for writing.");
    exit(1);
}

gzwrite($gz, "-- iTasker database backup\n");
gzwrite($gz, "-- Database: " . DB_NAME . "\n");
gzwrite($gz, "-- Generated: " . date('Y-m-d H:i:s') . " Africa/Nairobi\n\n");
gzwrite($gz, "SET NAMES utf8mb4;\n");
gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n\n");

// ── 3. Dump every table: structure, then data in chunks ──────────────
$tablesResult = mysqli_query($con, 'SHOW TABLES');
if (!$tablesResult) {
    logLine('FATAL: SHOW TABLES failed: ' . mysqli_error($con));
    gzclose($gz);
    unlink($filepath);
    exit(1);
}

$tableCount = 0;
$totalRows = 0;
$skippedTables = [];

while ($tableRow = mysqli_fetch_row($tablesResult)) {
    $table = $tableRow[0];

    $createResult = mysqli_query($con, "SHOW CREATE TABLE `$table`");
    if (!$createResult) {
        logLine("WARNING: skipping `$table` — SHOW CREATE TABLE failed: " . mysqli_error($con));
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
        for ($offset = 0; $offset < $rowCount; $offset += CHUNK_SIZE) {
            $chunkResult = mysqli_query($con, "SELECT * FROM `$table` LIMIT $offset, " . CHUNK_SIZE);
            if (!$chunkResult) {
                logLine("WARNING: `$table` chunk at offset $offset failed: " . mysqli_error($con));
                continue;
            }

            if ($columns === null) {
                $columns = array_map(function ($f) { return "`{$f->name}`"; }, mysqli_fetch_fields($chunkResult));
            }
            $columnList = implode(',', $columns);

            // Flush as soon as either the row count or the accumulated byte
            // size gets large, so a handful of big TEXT columns (task
            // descriptions, chat messages) can't produce a single INSERT
            // that blows past MySQL's max_allowed_packet on restore.
            $valueTuples = [];
            $batchBytes = 0;
            while ($row = mysqli_fetch_row($chunkResult)) {
                $escaped = array_map(function ($v) use ($con) { return backupEscape($con, $v); }, $row);
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

$sizeMb = round(filesize($filepath) / 1048576, 2);
$elapsed = round(microtime(true) - $startTime, 1);

logLine("Backup complete: $filename ({$sizeMb}MB, $tableCount tables, $totalRows rows, {$elapsed}s).");
if ($skippedTables) {
    logLine('Skipped tables (SHOW CREATE TABLE failed): ' . implode(', ', $skippedTables));
}

// ── 4. Retention: delete backups older than N days ────────────────────
$retentionDays = (int) env('DB_BACKUP_RETENTION_DAYS', 14);
$cutoff = time() - $retentionDays * 86400;
$deleted = 0;

foreach (glob($backupDir . '/itasker_*.sql.gz') as $oldFile) {
    if (filemtime($oldFile) < $cutoff) {
        if (unlink($oldFile)) {
            $deleted++;
        } else {
            logLine('WARNING: could not delete old backup ' . basename($oldFile));
        }
    }
}

logLine("Retention: deleted $deleted backup(s) older than $retentionDays day(s).");

if (empty($tableCount)) {
    exit(1);
}

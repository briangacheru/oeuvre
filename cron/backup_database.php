<?php
// ══════════════════════════════════════════════════════════════════
//  backup_database.php
//  Dumps the entire database (schema + data, every table) to a single
//  gzip-compressed .sql.gz file under cron/../backups/, then deletes
//  backups older than DB_BACKUP_RETENTION_DAYS (default 7).
//
//  The actual dump/prune logic lives in sudo/backup-functions.php,
//  shared with the "Backup Now" button on sudo/settings.php so the
//  two call sites can't drift.
//
//  Restore with:
//    gunzip -c backups/itasker_2026-07-24_030000.sql.gz | mysql -u USER -p DBNAME
//
//  Crontab example (once a day at 03:00 Nairobi time):
//    0 3 * * * /usr/local/bin/ea-php82 /home/monkbria/web.monkbrian.com/cron/backup_database.php >> /home/monkbria/web.monkbrian.com/cron/backup_database.log 2>&1
// ══════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../sudo/dbcon.php';
require_once __DIR__ . '/../sudo/backup-functions.php';
require_once __DIR__ . '/../shared-functions.php';

date_default_timezone_set('Africa/Nairobi');

function logLine($msg)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

$result = run_database_backup($con);

if ($result['success']) {
    $sizeMb = round($result['size'] / 1048576, 2);
    logLine("Backup complete: {$result['file']} ({$sizeMb}MB, {$result['tables']} tables, {$result['rows']} rows, {$result['elapsed']}s).");
    if (!empty($result['skipped'])) {
        logLine('Skipped tables (SHOW CREATE TABLE failed): ' . implode(', ', $result['skipped']));
    }
    log_activity($con, 'system', 'cron', 'db_backup_created', "{$result['file']} (" . format_backup_size($result['size']) . ")");
} else {
    logLine('FATAL: ' . $result['error']);
    log_activity($con, 'system', 'cron', 'db_backup_failed', $result['error']);
}

$deleted = prune_old_backups();
$retentionDays = db_backup_retention_days();
logLine('Retention: deleted ' . count($deleted) . " backup(s) older than $retentionDays day(s).");
if ($deleted) {
    log_activity($con, 'system', 'cron', 'db_backup_pruned', count($deleted) . " old backup(s) deleted: " . implode(', ', $deleted));
}

if (!$result['success']) {
    exit(1);
}

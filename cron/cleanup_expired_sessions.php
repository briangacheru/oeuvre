<?php
// ══════════════════════════════════════════════════════════════════
//  cleanup_expired_sessions.php
//  Housekeeping for rows that exist purely as time-bounded state and have
//  no other cleanup path once they go stale:
//    - tblwriter_known_devices / tbladmin_known_devices: deletes rows whose
//      expires_at has passed. is_known_device_token() already ignores
//      expired rows itself, so this is purely reclaiming space, not a
//      correctness fix.
//    - tblwriter_sessions: deletes rows whose last_activity is more than
//      14 days old. Unlike tblsessions (admin), which gets pruned by
//      explicit logout, "log out all other devices", and the
//      MAX_LOGGED_IN_DEVICES eviction, writer sessions currently only get
//      removed by an explicit logout - an inactive row otherwise sits
//      there forever.
//
//  All three columns are written with PHP's date() under
//  date_default_timezone_set('Africa/Nairobi') (see remember_device() /
//  record_writer_session() in shared-functions.php / session_tracker.php),
//  NOT MySQL's NOW() (this server's UTC clock) - so every cutoff here is
//  computed the same way, not with SQL NOW()/DATE_SUB(), to stay on the
//  same clock the columns were actually written with.
//
//  Crontab example (once a day at 03:30 Nairobi time):
//    30 3 * * * /usr/local/bin/ea-php82 /home/monkbria/web.monkbrian.com/cron/cleanup_expired_sessions.php >> /home/monkbria/web.monkbrian.com/cron/cleanup_expired_sessions.log 2>&1
// ══════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../sudo/dbcon.php';

date_default_timezone_set('Africa/Nairobi');

function logLine($msg)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

function deleteExpiredDevices($con, $table)
{
    $now = date('Y-m-d H:i:s');
    $stmt = $con->prepare("DELETE FROM `$table` WHERE expires_at < ?");
    if (!$stmt) {
        logLine("Skipped $table (prepare failed - has db-migrations/2026_07_20_add_known_devices.sql been run?): " . $con->error);
        return 0;
    }
    $stmt->bind_param('s', $now);
    $stmt->execute();
    $count = $stmt->affected_rows;
    $stmt->close();
    return $count;
}

$deletedWriterDevices = deleteExpiredDevices($con, 'tblwriter_known_devices');
logLine("Deleted $deletedWriterDevices expired tblwriter_known_devices row(s).");

$deletedAdminDevices = deleteExpiredDevices($con, 'tbladmin_known_devices');
logLine("Deleted $deletedAdminDevices expired tbladmin_known_devices row(s).");

$sessionCutoff = date('Y-m-d H:i:s', time() - 14 * 86400);
$stmt = $con->prepare("DELETE FROM tblwriter_sessions WHERE last_activity < ?");
if (!$stmt) {
    logLine("Skipped tblwriter_sessions (prepare failed): " . $con->error);
} else {
    $stmt->bind_param('s', $sessionCutoff);
    $stmt->execute();
    logLine("Deleted {$stmt->affected_rows} stale tblwriter_sessions row(s) (last_activity older than 14 days, cutoff $sessionCutoff).");
    $stmt->close();
}

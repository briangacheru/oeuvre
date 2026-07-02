<?php
require_once 'check-login.php';        // session + $con + $dbh + auth context
require_once 'session_tracker.php';

if (!isset($_SESSION['odmsaid'])) { header('Location: login'); exit; }
$email = $_SESSION['odmsaid'];

// CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    header('Location: profile'); exit;
}

// Clearing the account-wide remember token prevents a logged-out device
// from auto-logging itself back in via its rememberme cookie.
function clear_remember_token($con, $email) {
    $stmt = $con->prepare("UPDATE tbladmin SET remember_token = NULL WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

// Immediately destroy ANOTHER device's server-side session.
// Works with PHP's default "files" session handler. The deleted session file
// means that device's next request has no session -> it gets sent to login.
function destroy_session_file($targetSid) {
    // session IDs are alphanumeric (plus , and -); reject anything else to block path traversal
    if (!preg_match('/^[A-Za-z0-9,\-]+$/', (string)$targetSid)) return;
    $path = session_save_path();
    if ($path === '') $path = sys_get_temp_dir();
    if (strpos($path, ';') !== false) {           // handles "N;/path" and "N;MODE;/path"
        $parts = explode(';', $path);
        $path = end($parts);
    }
    $file = rtrim($path, "/\\") . DIRECTORY_SEPARATOR . 'sess_' . $targetSid;
    if (is_file($file)) @unlink($file);
}

// Log out ALL other devices
if (isset($_POST['logout_all_others'])) {
    $sel = $dbh->prepare("SELECT session_id FROM tblsessions WHERE admin_email = :email AND session_id <> :sid");
    $sel->execute([':email'=>$email, ':sid'=>session_id()]);
    foreach ($sel->fetchAll(PDO::FETCH_COLUMN) as $otherSid) {
        destroy_session_file($otherSid);
    }
    $dbh->prepare("DELETE FROM tblsessions WHERE admin_email = :email AND session_id <> :sid")
        ->execute([':email'=>$email, ':sid'=>session_id()]);
    clear_remember_token($con, $email);
    header('Location: profile'); exit;
}

// Log out a SINGLE device
if (isset($_POST['session_db_id'])) {
    $id = (int)$_POST['session_db_id'];
    $q = $dbh->prepare("SELECT session_id FROM tblsessions WHERE id = :id AND admin_email = :email");
    $q->execute([':id'=>$id, ':email'=>$email]);
    $r = $q->fetch(PDO::FETCH_OBJ);
    if ($r) {
        $targetSid = $r->session_id;
        $dbh->prepare("DELETE FROM tblsessions WHERE id = :id AND admin_email = :email")
            ->execute([':id'=>$id, ':email'=>$email]);
        clear_remember_token($con, $email);
        if ($targetSid === session_id()) {
            // you removed the device you're on -> end this session now
            session_unset(); session_destroy();
            header('Location: login'); exit;
        } else {
            // removing another device -> kill its session immediately
            destroy_session_file($targetSid);
        }
    }
    header('Location: profile'); exit;
}

header('Location: profile'); exit;
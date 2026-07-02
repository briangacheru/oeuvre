<?php
function get_device_info($ua) {
    $ua = $ua ?? '';
    $os = 'Unknown OS';
    if      (preg_match('/windows nt 10/i', $ua))    $os = 'Windows 10/11';
    elseif  (preg_match('/windows/i', $ua))          $os = 'Windows';
    elseif  (preg_match('/android/i', $ua))          $os = 'Android';
    elseif  (preg_match('/iphone|ipad|ipod/i', $ua)) $os = 'iOS';
    elseif  (preg_match('/mac os x/i', $ua))         $os = 'macOS';
    elseif  (preg_match('/linux/i', $ua))            $os = 'Linux';

    $browser = 'Unknown Browser';
    if      (preg_match('/edg/i', $ua))       $browser = 'Edge';
    elseif  (preg_match('/opr|opera/i', $ua)) $browser = 'Opera';
    elseif  (preg_match('/chrome/i', $ua))    $browser = 'Chrome'; // before Safari
    elseif  (preg_match('/firefox/i', $ua))   $browser = 'Firefox';
    elseif  (preg_match('/safari/i', $ua))    $browser = 'Safari';

    $type = preg_match('/mobile/i', $ua) ? 'Mobile' : 'Desktop';
    return "$browser on $os ($type)";
}

// Resolve an IP to "City, Country". Returns null for private/reserved/local IPs
// or if the lookup fails. Called only at login, so the 2s timeout is acceptable.
function lookup_ip_location($ip) {
    if (empty($ip) ||
        !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return null; // localhost, LAN (192.168.x / 10.x), or invalid -> not geolocatable
    }
    $url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,country,city";
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') return null;
    $parts = array_filter([$data['city'] ?? '', $data['country'] ?? '']);
    return $parts ? implode(', ', $parts) : null;
}

/* ===========================================================================
 * ADMIN sessions (PDO / $dbh) — used in the admin app.
 * ======================================================================== */

// Record/refresh the row for the CURRENT session. Call at every real auth event.
function record_login_session($dbh, $email) {
    $sid   = session_id();
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $label = get_device_info($ua);
    $loc   = lookup_ip_location($ip);
    $now   = date('Y-m-d H:i:s'); // PHP local time (Africa/Nairobi) — NOT MySQL NOW(), which is UTC here
    $sql = "INSERT INTO tblsessions
              (admin_email, session_id, ip_address, location, user_agent, device_label, login_time, last_activity)
            VALUES (:email, :sid, :ip, :loc, :ua, :label, :now, :now2)
            ON DUPLICATE KEY UPDATE
              admin_email = VALUES(admin_email), ip_address = VALUES(ip_address),
              location = VALUES(location), user_agent = VALUES(user_agent),
              device_label = VALUES(device_label), last_activity = VALUES(last_activity)";
    $dbh->prepare($sql)->execute(
        [':email'=>$email, ':sid'=>$sid, ':ip'=>$ip, ':loc'=>$loc,
            ':ua'=>$ua, ':label'=>$label, ':now'=>$now, ':now2'=>$now]
    );
}

// Returns false if this session's row is gone (revoked from another device).
function touch_session($dbh, $email) {
    $sid = session_id();
    $q = $dbh->prepare("SELECT id FROM tblsessions WHERE session_id = :sid AND admin_email = :email");
    $q->execute([':sid'=>$sid, ':email'=>$email]);
    if ($q->rowCount() === 0) return false;
    $now = date('Y-m-d H:i:s');
    $dbh->prepare("UPDATE tblsessions SET last_activity = :now WHERE session_id = :sid")
        ->execute([':now'=>$now, ':sid'=>$sid]);
    return true;
}

/* ===========================================================================
 * WRITER sessions (mysqli / $con) — used in the SEPARATE writer app, which
 * has no $dbh. Keyed by writer_email, mirroring sessionWriter.
 * Place a copy of this file in the writer app's directory.
 * ======================================================================== */

// Call in the writer login right after: $_SESSION['sessionWriter'] = $email;
function record_writer_session($con, $email) {
    $sid   = session_id();
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $label = get_device_info($ua);
    $loc   = lookup_ip_location($ip);
    $now   = date('Y-m-d H:i:s'); // PHP local time (Africa/Nairobi)
    $sql = "INSERT INTO tblwriter_sessions
              (writer_email, session_id, ip_address, location, user_agent, device_label, login_time, last_activity)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              writer_email = VALUES(writer_email), ip_address = VALUES(ip_address),
              location = VALUES(location), user_agent = VALUES(user_agent),
              device_label = VALUES(device_label), last_activity = VALUES(last_activity)";
    $stmt = $con->prepare($sql);
    if (!$stmt) return;
    $stmt->bind_param('ssssssss', $email, $sid, $ip, $loc, $ua, $label, $now, $now);
    $stmt->execute();
    $stmt->close();
}

// Keeps last_activity fresh on writer page loads so the device count stays
// accurate. Returns false if the row was revoked (e.g. logged out remotely).
function touch_writer_session($con, $email) {
    $sid = session_id();
    $chk = $con->prepare("SELECT id FROM tblwriter_sessions WHERE session_id = ? AND writer_email = ?");
    if (!$chk) return false;
    $chk->bind_param('ss', $sid, $email);
    $chk->execute();
    $chk->store_result();
    $exists = $chk->num_rows > 0;
    $chk->close();
    if (!$exists) return false;
    $now = date('Y-m-d H:i:s');
    $upd = $con->prepare("UPDATE tblwriter_sessions SET last_activity = ? WHERE session_id = ?");
    $upd->bind_param('ss', $now, $sid);
    $upd->execute();
    $upd->close();
    return true;
}
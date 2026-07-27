<?php
/**
 * "Sign in with Google" (OAuth 2.0 authorization-code flow), shared by the
 * writer (root) and administrator (sudo) interfaces. Deliberately just two
 * HTTPS calls (token exchange, userinfo) via cURL rather than pulling in
 * Google's PHP SDK - the app already keeps its third-party footprint small
 * (PHPMailer, TCPDF, the AWS SDK for Spaces), and this needs none of what
 * that SDK adds beyond it.
 */

require_once __DIR__ . '/env.php';

if (!function_exists('google_oauth_configured')) {
    function google_oauth_configured() {
        return (bool) (env('GOOGLE_CLIENT_ID') && env('GOOGLE_CLIENT_SECRET'));
    }
}

if (!function_exists('google_oauth_authorize_url')) {
    // $redirectUri must exactly match one registered in Google Cloud Console
    // for this client (see .env.example). $state is an opaque, unguessable
    // value the caller generates and stashes in the session to check on the
    // way back in google_oauth_fetch_profile()'s caller - standard OAuth
    // CSRF protection, since this endpoint has no form/CSRF token of its own.
    function google_oauth_authorize_url($redirectUri, $state) {
        $params = [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
}

if (!function_exists('google_oauth_http_post')) {
    function google_oauth_http_post($url, array $fields) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("google_oauth_http_post: request to $url failed (HTTP $httpCode) $error");
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('google_oauth_http_get')) {
    function google_oauth_http_get($url, $bearerToken) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bearerToken],
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("google_oauth_http_get: request to $url failed (HTTP $httpCode) $error");
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('google_oauth_fetch_profile')) {
    // Exchanges the authorization $code for a token, then fetches the
    // profile. Returns ['sub' => ..., 'email' => ..., 'email_verified' =>
    // ..., 'name' => ...] on success, or null on any failure - a bad/expired
    // code, a network error, or a malformed response. $redirectUri must be
    // byte-identical to the one used to build the authorize URL; Google
    // rejects the token exchange otherwise.
    function google_oauth_fetch_profile($code, $redirectUri) {
        $token = google_oauth_http_post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$token || empty($token['access_token'])) {
            return null;
        }

        $profile = google_oauth_http_get('https://www.googleapis.com/oauth2/v3/userinfo', $token['access_token']);
        if (!$profile || empty($profile['sub']) || empty($profile['email'])) {
            return null;
        }

        return $profile;
    }
}

if (!function_exists('google_oauth_derive_username')) {
    // Turns a Google display name into a username-safe string. The name
    // comes from the Google account's own profile, which its owner can set
    // to anything (including SQL metacharacters) - both interfaces'
    // username_exists()/email_exists() build their WHERE clause by direct
    // string concatenation rather than a prepared statement, so this must
    // never pass an unsanitized name into either of those. Stripping down to
    // a safe charset here neutralizes that regardless of how the value is
    // used downstream.
    function google_oauth_derive_username($name, $email) {
        $base = trim((string) $name) !== '' ? $name : explode('@', $email)[0];
        $safe = preg_replace('/[^a-zA-Z0-9_.]/', '', str_replace(' ', '_', $base));
        return $safe !== '' ? $safe : 'user' . substr(hash('crc32b', $email), 0, 6);
    }
}

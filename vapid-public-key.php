<?php
// Serves the VAPID public key to the browser for pushManager.subscribe().
// Safe to expose - it's the public half of the key pair; only the private
// key (server-side only, see shared-functions.php's send_push_notification())
// can actually sign push messages.
require_once __DIR__ . '/env.php';
header('Content-Type: application/json');
echo json_encode(['publicKey' => env('VAPID_PUBLIC_KEY', '')]);

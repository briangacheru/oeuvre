<?php
require_once __DIR__ . '/env.php';

// Digital Ocean Spaces Configuration
$config = [
    'region' => env('SPACES_REGION', 'sfo3'),
    'endpoint' => env('SPACES_ENDPOINT', 'https://sfo3.digitaloceanspaces.com'),
    'bucket' => env('SPACES_BUCKET'),
    'access_key' => env('SPACES_KEY'),
    'secret_key' => env('SPACES_SECRET'),
];
if (env('SPACES_CDN_ENDPOINT')) {
    $config['cdn_endpoint'] = env('SPACES_CDN_ENDPOINT');
}
return $config;

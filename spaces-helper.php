<?php
require_once __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class SpacesHelper {
    private $client;
    private $config;

    public function __construct() {
        $this->config = require 'spaces-config.php';

        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $this->config['region'],
            'endpoint' => $this->config['endpoint'],
            'credentials' => [
                'key' => $this->config['access_key'],
                'secret' => $this->config['secret_key'],
            ],
            'use_path_style_endpoint' => false,
        ]);
    }

    public function uploadFile($filePath, $fileName = null, $folder = '') {
        if ($fileName === null) {
            $fileName = basename($filePath);
        }

        try {
            // Use filename as-is (assuming it already has unique ID from upload_update.php)
            $uniqueFileName = $fileName;

            // Add folder prefix if specified
            $objectKey = empty($folder) ? $uniqueFileName : trim($folder, '/') . '/' . $uniqueFileName;

            // Upload the file
            $result = $this->client->putObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $objectKey,
                'SourceFile' => $filePath,
                'ACL' => 'public-read', // Make file publicly accessible
                // Without this, Spaces stores every object as application/octet-stream,
                // so browsers can't tell a PDF/image from generic binary data and just
                // download it instead of rendering it inline (native PDF viewer, <img>, etc).
                'ContentType' => $this->detectContentType($filePath, $uniqueFileName),
            ]);

            // Return the URL to the uploaded file
            return [
                'success' => true,
                'url' => $this->getFileUrl($objectKey),
                'key' => $objectKey
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function deleteFile($objectKey) {
        try {
            $result = $this->client->deleteObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $objectKey,
            ]);

            return [
                'success' => true
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getFileUrl($objectKey) {
        // If CDN is enabled, use the CDN URL
        if (isset($this->config['cdn_endpoint'])) {
            return $this->config['cdn_endpoint'] . '/' . $objectKey;
        }

        // Otherwise, use the regular Spaces URL - DO NOT URL encode the object key
        return 'https://' . $this->config['bucket'] . '.' . $this->config['region'] . '.digitaloceanspaces.com/' . $objectKey;
    }

    // Prefers sniffing the actual bytes (works even if the extension is wrong
    // or missing) and falls back to an extension lookup for types
    // mime_content_type() commonly gets wrong (docx/xlsx/pptx etc. report as
    // generic zip archives since that's literally their container format).
    private function detectContentType($filePath, $fileName) {
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($filePath);
            if ($detected && $detected !== 'application/octet-stream' && $detected !== 'application/zip') {
                return $detected;
            }
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $map = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
            'svg'  => 'image/svg+xml',
            'txt'  => 'text/plain',
            'csv'  => 'text/csv',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt'  => 'application/vnd.oasis.opendocument.text',
            'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp'  => 'application/vnd.oasis.opendocument.presentation',
            'zip'  => 'application/zip',
            'mp4'  => 'video/mp4',
            'mp3'  => 'audio/mpeg',
        ];

        return $map[$extension] ?? 'application/octet-stream';
    }
}
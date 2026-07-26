<?php
require_once __DIR__ . '/../env.php';
ob_start();

include "check-login.php";
requireCapability($currentAdminRole, 'operate_finance', 'json');
csrf_verify_or_json_die();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/lib/pdf-rc4.php';
require_once __DIR__ . '/lib/statement-import.php';

function sendJsonResponse($data)
{
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

const MAX_UPLOAD_BYTES = 15 * 1024 * 1024;
const MAX_FILES = 6;
const ALLOWED_SOURCE_TYPES = ['equity_csv', 'equity_pdf', 'mpesa_pdf'];

if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
    sendJsonResponse(['success' => false, 'error' => 'No files were uploaded.']);
}

$fileCount = count($_FILES['files']['name']);
if ($fileCount > MAX_FILES) {
    sendJsonResponse(['success' => false, 'error' => 'Too many files in one batch (max ' . MAX_FILES . ').']);
}

$sourceTypes = $_POST['source_type'] ?? [];
$passwords = $_POST['password'] ?? [];

$allRows = [];
$fileErrors = [];
$warnings = [];

for ($i = 0; $i < $fileCount; $i++) {
    $name = $_FILES['files']['name'][$i];
    $error = $_FILES['files']['error'][$i];
    $tmpPath = $_FILES['files']['tmp_name'][$i];
    $size = $_FILES['files']['size'][$i];
    $sourceType = $sourceTypes[$i] ?? '';
    $password = trim((string) ($passwords[$i] ?? ''));

    if ($error !== UPLOAD_ERR_OK) {
        $uploadErrorMessages = [
            UPLOAD_ERR_INI_SIZE => 'exceeds the server\'s upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE => 'exceeds the form\'s max file size',
            UPLOAD_ERR_PARTIAL => 'was only partially uploaded — please try again',
            UPLOAD_ERR_NO_TMP_DIR => 'server error: missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'server error: failed to write file to disk',
        ];
        $fileErrors[] = "$name: " . ($uploadErrorMessages[$error] ?? "upload failed (error code $error)");
        continue;
    }
    if ($size > MAX_UPLOAD_BYTES) {
        $fileErrors[] = "$name: file is too large (max 15MB).";
        continue;
    }
    if (!in_array($sourceType, ALLOWED_SOURCE_TYPES, true)) {
        $fileErrors[] = "$name: unrecognized source type.";
        continue;
    }

    try {
        if ($sourceType === 'equity_csv') {
            $content = file_get_contents($tmpPath);
            $rows = StatementImport::parseEquityCsv($content, $name);
        } elseif ($sourceType === 'equity_pdf') {
            $text = StatementImport::extractPdfText($tmpPath, $password);
            $rows = StatementImport::parseEquityPdf($text, $name);
        } else { // mpesa_pdf
            $text = StatementImport::extractPdfText($tmpPath, $password);
            $rows = StatementImport::parseMpesaPdf($text, $name);
        }

        if (empty($rows)) {
            $fileErrors[] = "$name: no transactions could be found in this file.";
            continue;
        }

        foreach ($rows as $row) {
            if (!empty($row['warning'])) {
                $warnings[] = "$name: " . $row['warning'];
            }
            $allRows[] = $row;
        }
    } catch (PdfWrongPasswordException $e) {
        $fileErrors[] = "$name: incorrect password.";
    } catch (PdfUnsupportedEncryptionException $e) {
        $fileErrors[] = "$name: " . $e->getMessage();
    } catch (StatementParseException $e) {
        $fileErrors[] = "$name: " . $e->getMessage();
    } catch (\Throwable $e) {
        error_log('parse-statement.php: ' . $e->getMessage());
        $fileErrors[] = "$name: could not be parsed.";
    }
}

StatementImport::detectInternalTransfers($allRows);

$outRows = array_map(function ($row) {
    return [
        'category' => $row['category'],
        'subcategory' => $row['subcategory'],
        'description' => $row['description'],
        'amount' => $row['amount'],
        'cost' => $row['cost'],
        'tag' => $row['tag'],
        'date' => $row['date'],
        'is_internal_transfer' => (bool) $row['is_internal_transfer'],
        'source' => $row['source'],
    ];
}, $allRows);

sendJsonResponse([
    'success' => true,
    'rows' => $outRows,
    'warnings' => $warnings,
    'file_errors' => $fileErrors,
]);

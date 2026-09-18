<?php
// Pre-submission checker: given a just-uploaded .docx file, extracts its
// word count and estimates a page count, then compares that against the
// task's required page count so a writer can catch a short/over-long
// submission before hitting Submit. Called from submission.php and
// resubmission.php right after Dropzone's 'success' event.
//
// Only .docx (OOXML) is supported - it's a plain zip archive so PHP's
// bundled ZipArchive class can read word/document.xml with no extra
// dependency. Legacy .doc (binary OLE format) and PDFs are not parsed;
// the endpoint reports that plainly rather than guessing.
require_once __DIR__ . '/shared-functions.php';
ob_start();
include 'check-login.php';
csrf_verify_or_json_die();
ob_clean();
header('Content-Type: application/json');

if (empty($_SESSION['sessionWriter'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$aid = $_SESSION['sessionWriter'];

function statsJson($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    statsJson(false, 'Invalid request method');
}

$taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$fileUrl = trim($_POST['file_url'] ?? '');
$fileName = trim($_POST['file_name'] ?? '');

if ($taskId <= 0 || $fileUrl === '') {
    statsJson(false, 'Missing file.');
}

if (!preg_match('/\.docx$/i', $fileName)) {
    statsJson(true, 'Page-count check only supports .docx files.', ['supported' => false]);
}

if (!class_exists('ZipArchive')) {
    statsJson(true, 'Page-count check unavailable on this server (php-zip not installed).', ['supported' => false]);
}

// Confirm this task belongs to the requesting writer and grab the target
// page count for the comparison. $aid is the writer's session EMAIL, and
// tbltasks.writer holds their username, not their email - email is the
// column that actually matches $aid (see check_new_tasks.php).
$stmt = mysqli_prepare($con, "SELECT pages FROM tbltasks WHERE id = ? AND email = ?");
mysqli_stmt_bind_param($stmt, 'is', $taskId, $aid);
mysqli_stmt_execute($stmt);
$task = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$task) {
    statsJson(false, 'Task not found.');
}

$targetPages = (float) $task['pages'];

$tempFile = tempnam(sys_get_temp_dir(), 'docx_check_');
if (!download_remote_file($fileUrl, $tempFile)) {
    @unlink($tempFile);
    statsJson(false, 'Could not read the uploaded file for checking.');
}

$zip = new ZipArchive();
if ($zip->open($tempFile) !== true) {
    @unlink($tempFile);
    statsJson(false, 'The uploaded file is not a valid .docx document.');
}

$xml = $zip->getFromName('word/document.xml');
$zip->close();
@unlink($tempFile);

if ($xml === false) {
    statsJson(false, 'Could not read document contents.');
}

// Strip XML tags, decode entities, collapse whitespace, then count words -
// a standard-enough proxy since the app has no access to the writer's
// actual page layout/margins/font.
$text = strip_tags(str_replace('</w:p>', ' ', $xml));
$text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
$text = trim(preg_replace('/\s+/', ' ', $text));
$wordCount = $text === '' ? 0 : count(preg_split('/\s+/', $text));

// 300 words/page - the estimate this app standardizes on for the
// pre-submission checker (double-spaced, Times New Roman 12pt runs closer
// to 275, but 300 is the round number this project has chosen to use).
$wordsPerPage = 300;
$estimatedPages = $wordCount > 0 ? round($wordCount / $wordsPerPage, 1) : 0;

$diff = $targetPages > 0 ? round((($estimatedPages - $targetPages) / $targetPages) * 100) : 0;
if ($targetPages <= 0) {
    $verdict = 'unknown';
} elseif (abs($diff) <= 10) {
    $verdict = 'on_target';
} elseif ($diff < -10) {
    $verdict = 'short';
} else {
    $verdict = 'long';
}

statsJson(true, 'Checked.', [
    'supported' => true,
    'word_count' => $wordCount,
    'estimated_pages' => $estimatedPages,
    'target_pages' => $targetPages,
    'diff_percent' => $diff,
    'verdict' => $verdict,
]);

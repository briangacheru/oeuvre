<?php
session_start();
include('dbcon.php');
header('Content-Type: application/json; charset=utf-8');

// Check if user is authenticated
if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Security token mismatch']);
    exit();
}

// Validate required fields
if (!isset($_POST['receiver_id'], $_POST['receiver_type'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

// Sanitize and validate input - FIXED: Replaced deprecated FILTER_SANITIZE_STRING
$message = trim($_POST['message'] ?? '');
$receiverId = filter_var($_POST['receiver_id'], FILTER_VALIDATE_INT);
$receiverType = trim(htmlspecialchars($_POST['receiver_type'], ENT_QUOTES, 'UTF-8')); // Fixed deprecated filter

// Validate inputs
if ($receiverId === false || $receiverId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid receiver ID']);
    exit();
}

if (!in_array($receiverType, ['admin', 'writer'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid receiver type']);
    exit();
}

// Check if message or file is provided
$hasFiles = isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0]);
if (empty($message) && !$hasFiles) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Message or file required']);
    exit();
}

// Message length validation
if (strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Message too long (max 1000 characters)']);
    exit();
}

$senderEmail = $_SESSION['odmsaid'];
$fileUrl = null;

try {
    // Enhanced file upload handling
    if ($hasFiles) {
        $uploadResult = handleFileUpload($_FILES['file']);
        if (!$uploadResult['success']) {
            echo json_encode(['status' => 'error', 'message' => $uploadResult['message']]);
            exit();
        }
        $fileUrl = $uploadResult['filename'];
    }

    // Get sender information - Using mysqli_real_escape_string for compatibility
    $escapedEmail = mysqli_real_escape_string($con, $senderEmail);
    $senderQuery = mysqli_query($con, "
        SELECT id, 'admin' as type FROM tbladmin WHERE email = '$escapedEmail'
        UNION 
        SELECT id, 'writer' as type FROM tblwriters WHERE email = '$escapedEmail'
    ");

    if (!$senderQuery) {
        throw new Exception('Database query failed: ' . mysqli_error($con));
    }

    $sender = mysqli_fetch_assoc($senderQuery);

    if (!$sender) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Sender not found']);
        exit();
    }

    $senderId = (int)$sender['id'];
    $senderType = $sender['type'];

    // Verify receiver exists - Fixed to avoid SQL injection
    $receiverQuery = mysqli_query($con, "
        SELECT id FROM tbladmin WHERE id = $receiverId AND '$receiverType' = 'admin'
        UNION
        SELECT id FROM tblwriters WHERE id = $receiverId AND '$receiverType' = 'writer'
    ");

    if (!$receiverQuery) {
        throw new Exception('Receiver verification failed: ' . mysqli_error($con));
    }

    $receiverExists = mysqli_num_rows($receiverQuery) > 0;

    if (!$receiverExists) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid receiver']);
        exit();
    }

    // Escape message content for database insertion
    $escapedMessage = mysqli_real_escape_string($con, $message);
    $escapedFileUrl = $fileUrl ? mysqli_real_escape_string($con, $fileUrl) : null;

    // Insert message
    $insertQuery = "
        INSERT INTO chat_messages (sender_id, sender_type, receiver_id, receiver_type, message, file_url, timestamp, is_read) 
        VALUES ($senderId, '$senderType', $receiverId, '$receiverType', '$escapedMessage', " .
        ($escapedFileUrl ? "'$escapedFileUrl'" : 'NULL') . ", NOW(), 0)
    ";

    $result = mysqli_query($con, $insertQuery);

    if (!$result) {
        throw new Exception('Message insert failed: ' . mysqli_error($con));
    }

    $messageId = mysqli_insert_id($con);

    echo json_encode([
        'status' => 'success',
        'message_id' => $messageId,
        'file_url' => $fileUrl
    ]);

} catch (Exception $e) {
    error_log('Send message error: ' . $e->getMessage());

    // Clean up uploaded file if database insert failed
    if ($fileUrl && file_exists('../taskfiles/' . $fileUrl)) {
        unlink('../taskfiles/' . $fileUrl);
    }

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
}

/**
 * Enhanced file upload function with comprehensive security checks
 */
function handleFileUpload($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . getUploadErrorMessage($file['error'])];
    }

    // File size validation (10MB limit)
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'message' => 'File size exceeds 10MB limit'];
    }

    // Get real MIME type using finfo (more reliable than $_FILES['type'])
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        // Fallback for older PHP versions
        $mimeType = mime_content_type($file['tmp_name']);
    }

    // Define allowed file types
    $allowedTypes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp']
    ];

    // Validate MIME type
    if (!array_key_exists($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed. Only images are permitted.'];
    }

    // Validate file extension
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedTypes[$mimeType])) {
        return ['success' => false, 'message' => 'File extension does not match file type'];
    }

    // Additional validation for images
    if (strpos($mimeType, 'image/') === 0) {
        // Verify it's a valid image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }

        // Check for embedded PHP code or scripts (security check)
        $imageContent = file_get_contents($file['tmp_name'], false, null, 0, 1024); // Check first 1KB
        $suspiciousPatterns = ['<?php', '<?', '<script', 'javascript:', 'vbscript:'];

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($imageContent, $pattern) !== false) {
                return ['success' => false, 'message' => 'File contains suspicious content'];
            }
        }
    }

    // Generate secure filename
    $filename = generateSecureFilename($fileExtension);

    // Set upload directory
    $uploadDir = '../taskfiles/';

    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        return ['success' => false, 'message' => 'Upload directory is not writable'];
    }

    $destPath = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        // Set proper file permissions
        chmod($destPath, 0644);
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Generate secure filename
 */
function generateSecureFilename($extension) {
    return bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
}

/**
 * Get human-readable upload error message
 */
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File is too large';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}
?>
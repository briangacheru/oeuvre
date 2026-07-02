<?php
ob_start();
include 'check-login.php';
ob_clean();
header('Content-Type: application/json');

// Ensure no caching
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Get and validate input
$taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$parentId = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;

if ($taskId <= 0) {
    sendJsonResponse(false, 'Invalid task ID');
}

// Check if files are uploaded (support both single 'file' and multiple 'attachments[]')
$hasFile = isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;
$hasMultipleFiles = isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0]);

// Validate that either comment or file is provided
if (empty($comment) && !$hasFile && !$hasMultipleFiles) {
    sendJsonResponse(false, 'Comment or file attachment required');
}

// Check if database connection exists
if (!isset($con) || !$con) {
    sendJsonResponse(false, 'Database connection not available');
}

// Check session and determine user type
$userEmail = null;
$userType = null;
$userName = null;
$userId = null;

if (isset($_SESSION['sessionWriter'])) {
    $userEmail = $_SESSION['sessionWriter'];
    $userType = 'writer';

    // Get writer name and ID from database
    $writerQuery = 'SELECT id, writer FROM tblwriters WHERE username = ? OR email = ? LIMIT 1';
    if ($writerStmt = mysqli_prepare($con, $writerQuery)) {
        mysqli_stmt_bind_param($writerStmt, 'ss', $userEmail, $userEmail);
        mysqli_stmt_execute($writerStmt);
        mysqli_stmt_bind_result($writerStmt, $writerId, $writerName);
        if (mysqli_stmt_fetch($writerStmt)) {
            $userName = $writerName ? $writerName : $userEmail;
            $userId = $writerId;
        }
        mysqli_stmt_close($writerStmt);
    }

    // Fallback: try to get from tbltasks if not found in tblwriters
    if (!$userId) {
        $taskWriterQuery = 'SELECT writer FROM tbltasks WHERE email = ? LIMIT 1';
        if ($taskWriterStmt = mysqli_prepare($con, $taskWriterQuery)) {
            mysqli_stmt_bind_param($taskWriterStmt, 's', $userEmail);
            mysqli_stmt_execute($taskWriterStmt);
            mysqli_stmt_bind_result($taskWriterStmt, $writerName);
            if (mysqli_stmt_fetch($taskWriterStmt)) {
                $userName = $writerName ? $writerName : $userEmail;
                $userId = 0;
            }
            mysqli_stmt_close($taskWriterStmt);
        }
    }

    if (empty($userName)) {
        $userName = $userEmail;
    }
    if (!$userId) {
        $userId = 0;
    }

} elseif (isset($_SESSION['odmsaid'])) {
    $userEmail = $_SESSION['odmsaid'];
    $userType = 'admin';

    // Get ACTUAL admin username from database
    $adminQuery = 'SELECT id, username, AdminName, CONCAT(FirstName, " ", LastName) as fullname FROM tbladmin WHERE email = ? LIMIT 1';
    if ($adminStmt = mysqli_prepare($con, $adminQuery)) {
        mysqli_stmt_bind_param($adminStmt, 's', $userEmail);
        mysqli_stmt_execute($adminStmt);
        $result = mysqli_stmt_get_result($adminStmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $userId = $row['id'];
            $userName = $row['username'] ?: $row['AdminName'] ?: $row['fullname'] ?: 'Admin';
        }
        mysqli_stmt_close($adminStmt);
    }

    if (!$userName) {
        $userName = $userEmail ?: 'Admin';
    }
    if (!$userId) {
        $userId = 1;
    }
} else {
    sendJsonResponse(false, 'User session not found. Please login again.');
}

// Verify user has access to this task
if ($userType == 'writer') {
    $accessQuery = 'SELECT id FROM tbltasks WHERE id = ? AND email = ?';
    if ($accessStmt = mysqli_prepare($con, $accessQuery)) {
        mysqli_stmt_bind_param($accessStmt, 'is', $taskId, $userEmail);
        mysqli_stmt_execute($accessStmt);
        $accessResult = mysqli_stmt_get_result($accessStmt);

        if (mysqli_num_rows($accessResult) == 0) {
            mysqli_stmt_close($accessStmt);
            sendJsonResponse(false, 'Access denied - Task not assigned to you');
        }
        mysqli_stmt_close($accessStmt);
    } else {
        sendJsonResponse(false, 'Database error: ' . mysqli_error($con));
    }
} elseif ($userType == 'admin') {
    $accessQuery = 'SELECT id FROM tbltasks WHERE id = ?';
    if ($accessStmt = mysqli_prepare($con, $accessQuery)) {
        mysqli_stmt_bind_param($accessStmt, 'i', $taskId);
        mysqli_stmt_execute($accessStmt);
        $accessResult = mysqli_stmt_get_result($accessStmt);

        if (mysqli_num_rows($accessResult) == 0) {
            mysqli_stmt_close($accessStmt);
            sendJsonResponse(false, 'Task not found');
        }
        mysqli_stmt_close($accessStmt);
    } else {
        sendJsonResponse(false, 'Database error: ' . mysqli_error($con));
    }
} else {
    sendJsonResponse(false, 'Invalid user type: ' . $userType);
}

// Validate parent comment if provided
if ($parentId !== null) {
    $parentQuery = 'SELECT id FROM tbl_task_comments WHERE id = ? AND task_id = ?';
    if ($parentStmt = mysqli_prepare($con, $parentQuery)) {
        mysqli_stmt_bind_param($parentStmt, 'ii', $parentId, $taskId);
        mysqli_stmt_execute($parentStmt);
        $parentResult = mysqli_stmt_get_result($parentStmt);

        if (mysqli_num_rows($parentResult) == 0) {
            mysqli_stmt_close($parentStmt);
            sendJsonResponse(false, 'Parent comment not found');
        }
        mysqli_stmt_close($parentStmt);
    } else {
        sendJsonResponse(false, 'Database error: ' . mysqli_error($con));
    }
}

// Handle file uploads (support both single and multiple)
$uploadedFiles = [];
$primaryFileUrl = null;

// Handle single file upload (backward compatibility)
if ($hasFile) {
    $uploadResult = handleFileUpload($_FILES['file']);
    if (!$uploadResult['success']) {
        sendJsonResponse(false, $uploadResult['message']);
    }
    $primaryFileUrl = $uploadResult['filename'];
    $uploadedFiles[] = [
        'filename' => $uploadResult['filename'],
        'original_name' => $_FILES['file']['name'],
        'size' => $_FILES['file']['size'],
        'type' => $uploadResult['mime_type'] ?? 'unknown'
    ];
}

// Handle multiple files upload
if ($hasMultipleFiles) {
    $fileCount = count($_FILES['attachments']['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        // Skip if no file at this index
        if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $file = [
            'name' => $_FILES['attachments']['name'][$i],
            'type' => $_FILES['attachments']['type'][$i],
            'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
            'error' => $_FILES['attachments']['error'][$i],
            'size' => $_FILES['attachments']['size'][$i]
        ];

        $uploadResult = handleFileUpload($file);
        if (!$uploadResult['success']) {
            // Clean up previously uploaded files
            foreach ($uploadedFiles as $uploadedFile) {
                if (file_exists('../taskfiles/' . $uploadedFile['filename'])) {
                    unlink('../taskfiles/' . $uploadedFile['filename']);
                }
            }
            sendJsonResponse(false, $uploadResult['message']);
        }

        // Set first file as primary
        if ($primaryFileUrl === null) {
            $primaryFileUrl = $uploadResult['filename'];
        }

        $uploadedFiles[] = [
            'filename' => $uploadResult['filename'],
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $uploadResult['mime_type'] ?? 'unknown'
        ];
    }
}

// Sanitize input
$comment = trim($comment);

// Check if table structure supports file_url column
$checkColumnQuery = "SHOW COLUMNS FROM tbl_task_comments LIKE 'file_url'";
$columnResult = mysqli_query($con, $checkColumnQuery);
$hasFileUrlColumn = (mysqli_num_rows($columnResult) > 0);

// Check if the table has AUTO_INCREMENT on id column
$hasAutoIncrement = false;
$checkAutoIncQuery = "SHOW COLUMNS FROM tbl_task_comments WHERE Field = 'id'";
$autoIncResult = mysqli_query($con, $checkAutoIncQuery);

if ($autoIncResult && $row = mysqli_fetch_assoc($autoIncResult)) {
    $hasAutoIncrement = strpos(strtolower($row['Extra']), 'auto_increment') !== false;
}

// Insert comment with file_url
if ($hasAutoIncrement) {
    // Table has auto-increment
    if ($hasFileUrlColumn) {
        $insertQuery = 'INSERT INTO tbl_task_comments (task_id, user_id, user_type, username, comment, file_url, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())';
        if ($insertStmt = mysqli_prepare($con, $insertQuery)) {
            mysqli_stmt_bind_param($insertStmt, 'iissssi', $taskId, $userId, $userType, $userName, $comment, $primaryFileUrl, $parentId);

            if (mysqli_stmt_execute($insertStmt)) {
                $commentId = mysqli_insert_id($con);
                mysqli_stmt_close($insertStmt);
            } else {
                $error = mysqli_stmt_error($insertStmt);
                mysqli_stmt_close($insertStmt);
                // Clean up uploaded files if insert failed
                foreach ($uploadedFiles as $uploadedFile) {
                    if (file_exists('../taskfiles/' . $uploadedFile['filename'])) {
                        unlink('../taskfiles/' . $uploadedFile['filename']);
                    }
                }
                sendJsonResponse(false, 'Failed to add comment: ' . $error);
            }
        } else {
            sendJsonResponse(false, 'Database error: ' . mysqli_error($con));
        }
    } else {
        // No file_url column
        $insertQuery = 'INSERT INTO tbl_task_comments (task_id, user_id, user_type, username, comment, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())';
        if ($insertStmt = mysqli_prepare($con, $insertQuery)) {
            mysqli_stmt_bind_param($insertStmt, 'iisssi', $taskId, $userId, $userType, $userName, $comment, $parentId);

            if (mysqli_stmt_execute($insertStmt)) {
                $commentId = mysqli_insert_id($con);
                mysqli_stmt_close($insertStmt);
            } else {
                $error = mysqli_stmt_error($insertStmt);
                mysqli_stmt_close($insertStmt);
                sendJsonResponse(false, 'Failed to add comment: ' . $error);
            }
        } else {
            sendJsonResponse(false, 'Database error: ' . mysqli_error($con));
        }
    }
} else {
    // Table does NOT have auto-increment - get next ID manually
    $getMaxIdQuery = 'SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM tbl_task_comments';
    $maxIdResult = mysqli_query($con, $getMaxIdQuery);
    $nextId = 1;

    if ($maxIdResult && $row = mysqli_fetch_assoc($maxIdResult)) {
        $nextId = $row['next_id'];
    }

    if ($hasFileUrlColumn) {
        $insertQuery = 'INSERT INTO tbl_task_comments (id, task_id, user_id, user_type, username, comment, file_url, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())';
        if ($insertStmt = mysqli_prepare($con, $insertQuery)) {
            mysqli_stmt_bind_param($insertStmt, 'iiissssi', $nextId, $taskId, $userId, $userType, $userName, $comment, $primaryFileUrl, $parentId);

            if (mysqli_stmt_execute($insertStmt)) {
                $commentId = $nextId;
                mysqli_stmt_close($insertStmt);
            } else {
                $error = mysqli_stmt_error($insertStmt);
                mysqli_stmt_close($insertStmt);
                // Clean up uploaded files if insert failed
                foreach ($uploadedFiles as $uploadedFile) {
                    if (file_exists('../taskfiles/' . $uploadedFile['filename'])) {
                        unlink('../taskfiles/' . $uploadedFile['filename']);
                    }
                }
                sendJsonResponse(false, 'Failed to add comment: ' . $error);
            }
        } else {
            sendJsonResponse(false, 'Database error: ' . mysqli_error($con));
        }
    } else {
        // No file_url column
        $insertQuery = 'INSERT INTO tbl_task_comments (id, task_id, user_id, user_type, username, comment, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())';
        if ($insertStmt = mysqli_prepare($con, $insertQuery)) {
            mysqli_stmt_bind_param($insertStmt, 'iiisssi', $nextId, $taskId, $userId, $userType, $userName, $comment, $parentId);

            if (mysqli_stmt_execute($insertStmt)) {
                $commentId = $nextId;
                mysqli_stmt_close($insertStmt);
            } else {
                $error = mysqli_stmt_error($insertStmt);
                mysqli_stmt_close($insertStmt);
                sendJsonResponse(false, 'Failed to add comment: ' . $error);
            }
        } else {
            sendJsonResponse(false, 'Database error: ' . mysqli_error($con));
        }
    }
}

// If multiple files, store additional files in message_attachments table
if (count($uploadedFiles) > 1) {
    // Check if message_attachments table exists
    $tableCheck = mysqli_query($con, "SHOW TABLES LIKE 'tbl_comment_attachments'");

    if (mysqli_num_rows($tableCheck) > 0) {
        // Insert additional attachments (skip first one as it's in file_url)
        foreach ($uploadedFiles as $index => $fileInfo) {
            if ($index === 0) continue; // Skip first file

            $attachQuery = "INSERT INTO tbl_comment_attachments (comment_id, file_name, file_path, file_size, file_type, uploaded_at)
                           VALUES (?, ?, ?, ?, ?, NOW())";

            if ($attachStmt = mysqli_prepare($con, $attachQuery)) {
                $filePath = '../taskfiles/' . $fileInfo['filename'];
                mysqli_stmt_bind_param($attachStmt, 'issis', $commentId, $fileInfo['original_name'], $filePath, $fileInfo['size'], $fileInfo['type']);
                mysqli_stmt_execute($attachStmt);
                mysqli_stmt_close($attachStmt);
            }
        }
    }
}

// Get the inserted comment details for response
if ($hasFileUrlColumn) {
    $selectQuery = 'SELECT id, task_id, user_id, user_type, username, comment, file_url, parent_id, created_at FROM tbl_task_comments WHERE id = ?';
} else {
    $selectQuery = 'SELECT id, task_id, user_id, user_type, username, comment, parent_id, created_at FROM tbl_task_comments WHERE id = ?';
}

if ($selectStmt = mysqli_prepare($con, $selectQuery)) {
    mysqli_stmt_bind_param($selectStmt, 'i', $commentId);
    mysqli_stmt_execute($selectStmt);
    $result = mysqli_stmt_get_result($selectStmt);
    $commentData = mysqli_fetch_assoc($result);
    mysqli_stmt_close($selectStmt);

    if ($commentData) {
        $responseData = [
            'comment' => [
                'id' => $commentData['id'],
                'task_id' => $commentData['task_id'],
                'user_id' => $commentData['user_id'],
                'user_type' => $commentData['user_type'],
                'username' => $commentData['username'],
                'comment' => $commentData['comment'],
                'parent_id' => $commentData['parent_id'],
                'created_at' => $commentData['created_at'],
                'formatted_date' => date('M j, Y g:i A', strtotime($commentData['created_at'])),
                'attachments_count' => count($uploadedFiles)
            ]
        ];

        // Add file_url to response if column exists
        if ($hasFileUrlColumn && isset($commentData['file_url'])) {
            $responseData['comment']['file_url'] = $commentData['file_url'];
        }

        // Add all uploaded files info
        if (!empty($uploadedFiles)) {
            $responseData['comment']['files'] = $uploadedFiles;
        }

        sendJsonResponse(true, 'Comment added successfully', $responseData);
    } else {
        sendJsonResponse(true, 'Comment added successfully');
    }
} else {
    sendJsonResponse(true, 'Comment added successfully');
}

/**
 * Enhanced file upload function with comprehensive security checks
 * Now supports both images AND documents
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
    } elseif (function_exists('mime_content_type')) {
        // Fallback if finfo is not available
        $mimeType = mime_content_type($file['tmp_name']);
    } else {
        // Final fallback: use the browser-supplied MIME type
        $mimeType = $file['type'];
    }

    // Define allowed file types - EXPANDED to include documents
    $allowedTypes = [
        // Images
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        // Documents
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'text/plain' => ['txt'],
    ];

    // Validate MIME type
    if (!array_key_exists($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed. Allowed: Images, PDF, DOC, DOCX, XLS, XLSX, TXT'];
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

    // Additional security check for all files
    $fileContent = file_get_contents($file['tmp_name'], false, null, 0, 2048);
    $dangerousPatterns = ['<?php', '<?=', 'eval(', 'base64_decode', 'exec(', 'system(', 'shell_exec'];

    foreach ($dangerousPatterns as $pattern) {
        if (stripos($fileContent, $pattern) !== false) {
            return ['success' => false, 'message' => 'File contains potentially dangerous content'];
        }
    }

    // Generate secure filename
    $filename = generateSecureFilename($file['name'], $fileExtension);

    // Set upload directory
    $uploadDir = '../taskfiles/';

    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
        chmod($uploadDir, 0777);
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        @chmod($uploadDir, 0777);
        if (!is_writable($uploadDir)) {
            return ['success' => false, 'message' => 'Upload directory is not writable'];
        }
    }

    $destPath = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        // Set proper file permissions
        chmod($destPath, 0644);
        return [
            'success' => true,
            'filename' => $filename,
            'mime_type' => $mimeType
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Generate secure filename
 */
function generateSecureFilename($originalFilename, $extension) {
    // Remove the extension from original filename
    $nameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);

    // Only remove null bytes (minimal security)
    $nameWithoutExt = str_replace("\0", '', $nameWithoutExt);

    // Check if file already exists
    $uploadDir = '../taskfiles/';
    $finalName = $nameWithoutExt . '.' . $extension;
    $counter = 1;

    // If file exists, add counter (like Windows does)
    // document.pdf -> document (1).pdf -> document (2).pdf
    while (file_exists($uploadDir . $finalName)) {
        $finalName = $nameWithoutExt . ' (' . $counter . ').' . $extension;
        $counter++;
    }

    return $finalName;
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

ob_end_flush();
?>
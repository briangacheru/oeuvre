<?php
include('check-login.php');
csrf_verify_or_json_die();

if (isset($_POST['receiver_id'], $_POST['receiver_type']) && (!empty($_POST['message']) || (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE))) {
    $senderEmail = $_SESSION['sessionWriter'] ?? null;
    $message = isset($_POST['message']) ? urldecode(trim($_POST['message'])) : ''; // Decode the message content
    $receiverId = intval($_POST['receiver_id']);
    $receiverType = trim($_POST['receiver_type']);
    $fileUrl = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $validation = validateChatAttachment($_FILES['file']);
        if (!$validation['success']) {
            echo json_encode(['status' => 'error', 'message' => $validation['message']]);
            exit;
        }

        $fileTmpPath = $_FILES['file']['tmp_name'];
        // Random filename rather than the original - the allow-list now
        // covers many more types than just images, so name collisions
        // between different senders' files are far more likely.
        $fileName = bin2hex(random_bytes(16)) . '_' . time() . '.' . $validation['extension'];
        $uploadDir = 'taskfiles/'; // Ensure this directory exists and is writable
        $destPath = $uploadDir . $fileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            chmod($destPath, 0644);
            $fileUrl = $fileName; // Only store the file name
        } else {
            error_log('File upload failed.');
            echo json_encode(['status' => 'error', 'message' => 'File upload failed.']);
            exit;
        }
    }

    error_log('File URL: ' . $fileUrl); // Debugging: log the file URL

    $senderQuery = mysqli_query($con, "
        SELECT id, 'admin' as type FROM tbladmin WHERE email = '$senderEmail'
        UNION 
        SELECT id, 'writer' as type FROM tblwriters WHERE email = '$senderEmail'
    ");
    $sender = mysqli_fetch_assoc($senderQuery);

    if ($sender) {
        $senderId = $sender['id'];
        $senderType = $sender['type'];

        $relatedTaskId = filter_var($_POST['related_task_id'] ?? null, FILTER_VALIDATE_INT);
        if ($relatedTaskId === false || $relatedTaskId <= 0) {
            $relatedTaskId = null;
        }

        $insertQuery = mysqli_prepare($con, "
            INSERT INTO chat_messages (sender_id, sender_type, receiver_id, receiver_type, message, file_url, related_task_id, timestamp, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)
        ");
        mysqli_stmt_bind_param($insertQuery, 'isisssi', $senderId, $senderType, $receiverId, $receiverType, $message, $fileUrl, $relatedTaskId);

        // Error logging can be done by manually creating a string of the query and parameters
        $logMessage = sprintf(
            "INSERT INTO chat_messages (sender_id, sender_type, receiver_id, receiver_type, message, file_url, timestamp, is_read) VALUES (%d, '%s', %d, '%s', '%s', '%s', NOW(), 0)",
            $senderId,
            $senderType,
            $receiverId,
            $receiverType,
            mysqli_real_escape_string($con, $message),
            $fileUrl
        );
        error_log('Insert Query: ' . $logMessage); // Debugging: log the query

        if (mysqli_stmt_execute($insertQuery)) {
            echo json_encode(['status' => 'success', 'message_id' => mysqli_insert_id($con), 'file_url' => $fileUrl]);
        } else {
            error_log('Database insert failed: ' . mysqli_error($con));
            echo json_encode(['status' => 'error', 'message' => 'Database insert failed.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid sender.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Required fields are missing.']);
}
?>

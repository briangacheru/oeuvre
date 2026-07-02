<?php
include('check-login.php');

if (isset($_POST['receiver_id'], $_POST['receiver_type']) && (!empty($_POST['message']) || (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE))) {
    $senderEmail = $_SESSION['sessionWriter'] ?? null;
    $message = isset($_POST['message']) ? urldecode(trim($_POST['message'])) : ''; // Decode the message content
    $receiverId = intval($_POST['receiver_id']);
    $receiverType = trim($_POST['receiver_type']);
    $fileUrl = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['file']['tmp_name'];
        $fileName = basename($_FILES['file']['name']);
        $uploadDir = 'taskfiles/'; // Ensure this directory exists and is writable
        $destPath = $uploadDir . $fileName;

        // Check if the file is an image
        $fileType = mime_content_type($fileTmpPath);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];

        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $fileUrl = $fileName; // Only store the file name
            } else {
                error_log('File upload failed.');
                echo json_encode(['status' => 'error', 'message' => 'File upload failed.']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Only jpeg, png, jpg, and gif files are allowed.']);
            exit;
        }
    } else if (isset($_FILES['file'])) {
        error_log('File upload error: ' . $_FILES['file']['error']);
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

        $insertQuery = mysqli_prepare($con, "
            INSERT INTO chat_messages (sender_id, sender_type, receiver_id, receiver_type, message, file_url, timestamp, is_read) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)
        ");
        mysqli_stmt_bind_param($insertQuery, 'isisss', $senderId, $senderType, $receiverId, $receiverType, $message, $fileUrl);

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
            echo json_encode(['status' => 'success']);
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

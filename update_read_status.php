<?php
include "check-login.php"; // Include your database connection file

if (isset($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);
    $currentUserEmail = $_SESSION['sessionWriter']; // Assuming sessionWriter stores the current user's email

    // Fetch the current user ID based on the email
    $currentUserIdQuery = mysqli_prepare($con, "
        SELECT id FROM tbladmin WHERE email = ?
        UNION
        SELECT id FROM tblwriters WHERE email = ?
    ");
    mysqli_stmt_bind_param($currentUserIdQuery, 'ss', $currentUserEmail, $currentUserEmail);
    mysqli_stmt_execute($currentUserIdQuery);
    $result = mysqli_stmt_get_result($currentUserIdQuery);
    $currentUser = mysqli_fetch_assoc($result);

    if ($currentUser) {
        $currentUserId = $currentUser['id'];

        // Update is_read status for messages where the current user is the receiver
        $updateQuery = mysqli_prepare($con, "
            UPDATE chat_messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
        ");
        mysqli_stmt_bind_param($updateQuery, 'ii', $currentUserId, $userId);

        if (mysqli_stmt_execute($updateQuery)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update read status.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required.']);
}
?>

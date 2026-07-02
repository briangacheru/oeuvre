<?php
include "check-login.php"; // Include your database connection file

if (isset($_GET['user_id']) && isset($_GET['user_type'])) {
    $userId = intval($_GET['user_id']);
    $userType = $_GET['user_type'];
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

        // Fetch messages between the current user and the selected user
        $messagesQuery = mysqli_prepare($con, "
            SELECT sender_id, receiver_id, message, timestamp, file_url, is_read 
            FROM chat_messages 
            WHERE (sender_id = ? AND receiver_id = ?)
               OR (receiver_id = ? AND sender_id = ?)
            ORDER BY timestamp ASC
        ");
        mysqli_stmt_bind_param($messagesQuery, 'iiii', $userId, $currentUserId, $userId, $currentUserId);
        mysqli_stmt_execute($messagesQuery);
        $result = mysqli_stmt_get_result($messagesQuery);

        $messages = [];
        while ($message = mysqli_fetch_assoc($result)) {
            $messages[] = $message;
        }

        echo json_encode($messages);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>

<?php
include "check-login.php"; // Include your database connection file

if (isset($_POST['message_ids'])) {
    $messageIds = $_POST['message_ids'];

    $ids = implode(',', array_map('intval', $messageIds));
    $updateQuery = "UPDATE chat_messages SET is_read = 1 WHERE id IN ($ids)";

    if (mysqli_query($con, $updateQuery)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No message IDs provided']);
}
?>

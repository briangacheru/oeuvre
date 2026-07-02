<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'] ?? 'medium';
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (empty($title)) {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Title is required!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    try {
        $con->begin_transaction();

        // Update main task
        $stmt = $con->prepare("UPDATE tbltodos SET title = ?, description = ?, priority = ?, category_id = ?, due_date = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssisi", $title, $description, $priority, $category_id, $due_date, $id);
        $stmt->execute();

        // Handle subtask deletions
        if (!empty($_POST['delete_subtasks'])) {
            $deleteStmt = $con->prepare("DELETE FROM subtasks WHERE id = ? AND todo_id = ?");
            foreach ($_POST['delete_subtasks'] as $subtaskId) {
                $deleteStmt->bind_param("ii", $subtaskId, $id);
                $deleteStmt->execute();
            }
        }

        // Handle existing subtasks updates
        if (!empty($_POST['existing_subtasks'])) {
            $updateSubtaskStmt = $con->prepare("UPDATE subtasks SET title = ?, completed = ? WHERE id = ? AND todo_id = ?");
            foreach ($_POST['existing_subtasks'] as $subtaskId => $subtaskTitle) {
                $completed = isset($_POST['subtask_completed'][$subtaskId]) ? 1 : 0;
                $updateSubtaskStmt->bind_param("siii", $subtaskTitle, $completed, $subtaskId, $id);
                $updateSubtaskStmt->execute();
            }
        }

        // Handle new subtasks
        if (!empty($_POST['new_subtasks'])) {
            $newSubtaskStmt = $con->prepare("INSERT INTO subtasks (todo_id, title, created_at) VALUES (?, ?, NOW())");
            foreach ($_POST['new_subtasks'] as $subtaskTitle) {
                $subtaskTitle = trim($subtaskTitle);
                if (!empty($subtaskTitle)) {
                    $newSubtaskStmt->bind_param("is", $id, $subtaskTitle);
                    $newSubtaskStmt->execute();
                }
            }
        }

        // Handle attachment deletions
        if (!empty($_POST['delete_attachments'])) {
            $getAttachmentStmt = $con->prepare("SELECT file_path FROM task_attachments WHERE id = ? AND todo_id = ?");
            $deleteAttachmentStmt = $con->prepare("DELETE FROM task_attachments WHERE id = ? AND todo_id = ?");

            foreach ($_POST['delete_attachments'] as $attachmentId) {
                // Get file path to delete physical file
                $getAttachmentStmt->bind_param("ii", $attachmentId, $id);
                $getAttachmentStmt->execute();
                $result = $getAttachmentStmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    if (file_exists($row['file_path'])) {
                        unlink($row['file_path']);
                    }
                }

                // Delete from database
                $deleteAttachmentStmt->bind_param("ii", $attachmentId, $id);
                $deleteAttachmentStmt->execute();
            }
        }

        // Handle new file attachments
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadDir = 'uploads/tasks/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $attachmentStmt = $con->prepare("INSERT INTO task_attachments (todo_id, filename, file_path, file_size, created_at) VALUES (?, ?, ?, ?, NOW())");

            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = $_FILES['attachments']['name'][$i];
                    $tempPath = $_FILES['attachments']['tmp_name'][$i];
                    $fileSize = $_FILES['attachments']['size'][$i];

                    // Generate unique filename
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                    $uniqueFilename = $id . '_' . time() . '_' . uniqid() . '.' . $extension;
                    $filePath = $uploadDir . $uniqueFilename;

                    if (move_uploaded_file($tempPath, $filePath)) {
                        $attachmentStmt->bind_param("issi", $id, $filename, $filePath, $fileSize);
                        $attachmentStmt->execute();
                    }
                }
            }
        }

        $con->commit();
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center"><p class="mb-0 flex-1">To-do task updated successfully!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';

    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Error updating to-do task: ' . $e->getMessage() . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>

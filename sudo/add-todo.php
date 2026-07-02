<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'] ?? 'medium';
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (empty($title)) {
        $_SESSION['alert'] = '<div class="alert alert-danger">Title is required!</div>';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    try {
        $con->begin_transaction();

        // Insert main task
        $stmt = $con->prepare("INSERT INTO tbltodos (title, description, priority, category_id, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssis", $title, $description, $priority, $category_id, $due_date);
        $stmt->execute();
        $taskId = $con->insert_id;

        // Handle subtasks
        if (!empty($_POST['subtasks'])) {
            $subtaskStmt = $con->prepare("INSERT INTO subtasks (todo_id, title, created_at) VALUES (?, ?, NOW())");
            foreach ($_POST['subtasks'] as $subtaskTitle) {
                $subtaskTitle = trim($subtaskTitle);
                if (!empty($subtaskTitle)) {
                    $subtaskStmt->bind_param("is", $taskId, $subtaskTitle);
                    $subtaskStmt->execute();
                }
            }
        }

        // Handle file attachments
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
                    $uniqueFilename = $taskId . '_' . time() . '_' . uniqid() . '.' . $extension;
                    $filePath = $uploadDir . $uniqueFilename;

                    if (move_uploaded_file($tempPath, $filePath)) {
                        $attachmentStmt->bind_param("issi", $taskId, $filename, $filePath, $fileSize);
                        $attachmentStmt->execute();
                    }
                }
            }
        }

        $con->commit();
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center"><p class="mb-0 flex-1">To-do task added successfully!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';

    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['alert'] = '<div class="alert alert-danger">Error adding task: ' . $e->getMessage() . '</div>';
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Error adding to-do task: ' . $e->getMessage() . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>

<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    try {
        if ($action === 'add') {
            $name = trim($_POST['name']);
            $color = $_POST['color'];

            if (empty($name)) {
                $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Category name is required!</p>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            } else {
                $stmt = $con->prepare("INSERT INTO categories (name, color, created_at) VALUES (?, ?, NOW())");
                $stmt->bind_param("ss", $name, $color);
                $stmt->execute();
                $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center"><p class="mb-0 flex-1">Category added successfully!</p>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            }

        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);

            // Check if category is being used
            $checkStmt = $con->prepare("SELECT COUNT(*) as count FROM tbltodos WHERE category_id = ?");
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result()->fetch_assoc();

            if ($result['count'] > 0) {
                $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center"><p class="mb-0 flex-1">Cannot delete category that is being used by tasks!</p>
                    <button class="btn-close-falcon" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            } else {
                $stmt = $con->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center"><p class="mb-0 flex-1">Category deleted successfully!</p>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            }
        }

    } catch (Exception $e) {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Error: ' . $e->getMessage() . '</p>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>
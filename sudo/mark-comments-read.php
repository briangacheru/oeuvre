<?php
include('check-login.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['odmsaid'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$aid = $_SESSION['odmsaid'];
$sql = 'SELECT * FROM tbladmin WHERE email=:aid';
$query = $dbh->prepare($sql);
$query->bindParam(':aid', $aid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);

if ($query->rowCount() == 0 || $results[0]->AdminName != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'single':
            case 'selected':
            case 'page':
                $commentIds = json_decode($_POST['comment_ids'] ?? '[]', true);
                if (empty($commentIds)) {
                    echo json_encode(['success' => false, 'message' => 'No comment IDs provided']);
                    exit();
                }

                $placeholders = str_repeat('?,', count($commentIds) - 1) . '?';
                $stmt = $dbh->prepare("
                    UPDATE tbl_task_comments 
                    SET is_read = 1 
                    WHERE id IN ($placeholders) 
                    AND user_type = 'writer' 
                    AND is_read = 0
                ");
                $stmt->execute($commentIds);
                $count = $stmt->rowCount();

                echo json_encode(['success' => true, 'count' => $count]);
                break;

            case 'all_filtered':
                $filter = $_POST['filter'] ?? 'all';
                $task_filter = (int)($_POST['task_filter'] ?? 0);
                $writer_filter = $_POST['writer_filter'] ?? '';

                // Build WHERE clause based on filters
                $whereClause = "WHERE tc.user_type = 'writer' AND tc.is_read = 0";
                $params = [];

                if ($task_filter > 0) {
                    $whereClause .= ' AND tc.task_id = ?';
                    $params[] = $task_filter;
                }

                if (!empty($writer_filter)) {
                    $whereClause .= ' AND t.email = ?';
                    $params[] = $writer_filter;
                }

                $stmt = $dbh->prepare("
                    UPDATE tbl_task_comments tc 
                    JOIN tbltasks t ON tc.task_id = t.id 
                    SET tc.is_read = 1 
                    $whereClause
                ");
                $stmt->execute($params);
                $count = $stmt->rowCount();

                echo json_encode(['success' => true, 'count' => $count]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
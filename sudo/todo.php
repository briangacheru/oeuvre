<?php
include "db.php";
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'update_status':
                if (!isset($_POST['id']) || !isset($_POST['status'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                    exit;
                }

                $taskId = intval($_POST['id']);
                $status = $_POST['status'];

                // Validate status
                $validStatuses = ['pending', 'in_progress', 'completed'];
                if (!in_array($status, $validStatuses)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid status']);
                    exit;
                }

                $stmt = $con->prepare("UPDATE tbltodos SET status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?");
                $completed_at = $status === 'completed' ? date('Y-m-d H:i:s') : null;
                $stmt->bind_param("ssi", $status, $completed_at, $taskId);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database update failed']);
                }
                exit;

            case 'bulk_action':
                if (!isset($_POST['ids']) || !isset($_POST['bulk_type'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                    exit;
                }

                $ids = $_POST['ids'];
                $action_type = $_POST['bulk_type'];

                if (empty($ids) || !is_array($ids)) {
                    echo json_encode(['success' => false, 'message' => 'No tasks selected']);
                    exit;
                }

                // Sanitize IDs
                $ids = array_map('intval', $ids);
                $ids = array_filter($ids, function($id) { return $id > 0; });

                if (empty($ids)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid task IDs']);
                    exit;
                }

                if ($action_type === 'delete') {
                    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                    $stmt = $con->prepare("DELETE FROM tbltodos WHERE id IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                } elseif ($action_type === 'complete') {
                    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                    $stmt = $con->prepare("UPDATE tbltodos SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid bulk action type']);
                    exit;
                }

                if ($stmt->execute()) {
                    $affected = $stmt->affected_rows;
                    echo json_encode(['success' => true, 'message' => "Successfully processed $affected task(s)"]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database operation failed']);
                }
                exit;

            case 'toggle_subtask':
                // Flip a single subtask's completed flag
                if (!isset($_POST['subtask_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing subtask id']);
                    exit;
                }
                $subId = intval($_POST['subtask_id']);
                $completedIn = isset($_POST['completed']) ? (intval($_POST['completed']) ? 1 : 0) : null;

                $sel = $con->prepare("SELECT completed, todo_id FROM subtasks WHERE id = ?");
                $sel->bind_param("i", $subId);
                $sel->execute();
                $row = $sel->get_result()->fetch_assoc();
                if (!$row) {
                    echo json_encode(['success' => false, 'message' => 'Subtask not found']);
                    exit;
                }
                $newVal = $completedIn === null ? ($row['completed'] ? 0 : 1) : $completedIn;
                $todoId = (int)$row['todo_id'];

                $upd = $con->prepare("UPDATE subtasks SET completed = ? WHERE id = ?");
                $upd->bind_param("ii", $newVal, $subId);
                if (!$upd->execute()) {
                    echo json_encode(['success' => false, 'message' => 'Database update failed']);
                    exit;
                }

                $prog = $con->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(completed), 0) AS done FROM subtasks WHERE todo_id = ?");
                $prog->bind_param("i", $todoId);
                $prog->execute();
                $p = $prog->get_result()->fetch_assoc();

                echo json_encode([
                    'success' => true,
                    'completed' => (int)$newVal,
                    'todo_id' => $todoId,
                    'total' => (int)$p['total'],
                    'done' => (int)$p['done'],
                ]);
                exit;

            case 'reschedule_task':
                // Drag-to-reschedule: update due_date to a date string or NULL
                if (!isset($_POST['id']) || !isset($_POST['new_date'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                    exit;
                }
                $id = intval($_POST['id']);
                $newDate = $_POST['new_date']; // 'YYYY-MM-DD' or '' to clear
                if ($newDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
                    exit;
                }
                $dateVal = $newDate === '' ? null : $newDate;
                $stmt = $con->prepare("UPDATE tbltodos SET due_date = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $dateVal, $id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'new_date' => $dateVal]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database update failed']);
                }
                exit;

            case 'quick_add_task':
                // Lightweight inline create (no attachments, no subtasks)
                $title = trim($_POST['title'] ?? '');
                if ($title === '') {
                    echo json_encode(['success' => false, 'message' => 'Title is required']);
                    exit;
                }
                $description = trim($_POST['description'] ?? '');
                $priority = in_array($_POST['priority'] ?? '', ['low','medium','high']) ? $_POST['priority'] : 'medium';
                $due_date = $_POST['due_date'] ?? '';
                if ($due_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) $due_date = '';
                $due_date_val = $due_date === '' ? null : $due_date;
                $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;

                $stmt = $con->prepare("INSERT INTO tbltodos (title, description, priority, status, category_id, due_date, created_at, updated_at) VALUES (?, ?, ?, 'pending', ?, ?, NOW(), NOW())");
                $stmt->bind_param("sssis", $title, $description, $priority, $category_id, $due_date_val);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'message' => 'Task added']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database insert failed']);
                }
                exit;

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle export - Updated version
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $exportType = $_GET['type'] ?? 'all';
    $includeSubtasks = isset($_GET['subtasks']) && $_GET['subtasks'] === '1';
    $includeAttachments = isset($_GET['attachments']) && $_GET['attachments'] === '1';

    if ($format === 'csv') {
        $filename = 'todos_' . date('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Prepare CSV headers
        $headers = ['ID', 'Title', 'Description', 'Priority', 'Status', 'Category', 'Due Date', 'Created At', 'Completed At'];

        if ($includeSubtasks) {
            $headers[] = 'Subtasks Count';
            $headers[] = 'Completed Subtasks';
            $headers[] = 'Subtasks Progress %';
        }

        if ($includeAttachments) {
            $headers[] = 'Attachments Count';
        }

        fputcsv($output, $headers);

        // Build query based on export type
        if ($exportType === 'filtered') {
            // Use the same filtering logic as the main query
            $where_conditions = [];
            $params = [];
            $param_types = "";

            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
                $search_term = "%" . $_GET['search'] . "%";
                $params[] = $search_term;
                $params[] = $search_term;
                $param_types .= "ss";
            }

            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $where_conditions[] = "status = ?";
                $params[] = $_GET['status'];
                $param_types .= "s";
            }

            if (isset($_GET['priority']) && !empty($_GET['priority'])) {
                $where_conditions[] = "priority = ?";
                $params[] = $_GET['priority'];
                $param_types .= "s";
            }

            if (isset($_GET['category']) && !empty($_GET['category'])) {
                $where_conditions[] = "category_id = ?";
                $params[] = $_GET['category'];
                $param_types .= "i";
            }

            if (isset($_GET['due_filter'])) {
                switch ($_GET['due_filter']) {
                    case 'overdue':
                        $where_conditions[] = "due_date < CURDATE() AND status != 'completed'";
                        break;
                    case 'today':
                        $where_conditions[] = "due_date = CURDATE()";
                        break;
                    case 'week':
                        $where_conditions[] = "due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                        break;
                }
            }

            // Add default filter if no status is specified
            if (!isset($_GET['status']) || empty($_GET['status'])) {
                $where_conditions[] = "status IN ('pending', 'in_progress')";
            }

            $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        } else {
            $where_clause = "";
            $params = [];
            $param_types = "";
        }

        // Build the export query
        $sql = "
            SELECT t.*, c.name as category_name";

        if ($includeSubtasks) {
            $sql .= ",
                (SELECT COUNT(*) FROM subtasks WHERE todo_id = t.id) as subtask_count,
                (SELECT COUNT(*) FROM subtasks WHERE todo_id = t.id AND completed = 1) as completed_subtasks";
        }

        if ($includeAttachments) {
            $sql .= ",
                (SELECT COUNT(*) FROM task_attachments WHERE todo_id = t.id) as attachment_count";
        }

        $sql .= "
            FROM tbltodos t 
            LEFT JOIN categories c ON t.category_id = c.id 
            $where_clause
            ORDER BY t.created_at DESC
        ";

        $stmt = $con->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $csvRow = [
                $row['id'],
                $row['title'],
                $row['description'],
                $row['priority'],
                $row['status'],
                $row['category_name'] ?? 'No Category',
                $row['due_date'],
                $row['created_at'],
                $row['completed_at']
            ];

            if ($includeSubtasks) {
                $subtaskCount = $row['subtask_count'] ?? 0;
                $completedSubtasks = $row['completed_subtasks'] ?? 0;
                $progress = $subtaskCount > 0 ? round(($completedSubtasks / $subtaskCount) * 100, 1) : 0;

                $csvRow[] = $subtaskCount;
                $csvRow[] = $completedSubtasks;
                $csvRow[] = $progress . '%';
            }

            if ($includeAttachments) {
                $csvRow[] = $row['attachment_count'] ?? 0;
            }

            fputcsv($output, $csvRow);
        }

        fclose($output);
        exit;
    }
}
?>
<?php include "head.php"; ?>
<title>iTasker | Todo List</title>
<?php include "navi.php"; ?>

<?php
// Fetch categories
$categories_stmt = $con->prepare("SELECT * FROM categories ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build query with filters (no pagination — group by date in PHP, cap completed)
$where_conditions = [];
$params = [];
$param_types = "";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
    $search_term = "%" . $_GET['search'] . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "ss";
}

if (isset($_GET['priority']) && !empty($_GET['priority'])) {
    $where_conditions[] = "priority = ?";
    $params[] = $_GET['priority'];
    $param_types .= "s";
}

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $where_conditions[] = "category_id = ?";
    $params[] = $_GET['category'];
    $param_types .= "i";
}

if (isset($_GET['due_filter'])) {
    switch ($_GET['due_filter']) {
        case 'overdue':
            $where_conditions[] = "due_date < CURDATE() AND status != 'completed'";
            break;
        case 'today':
            $where_conditions[] = "due_date = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            break;
    }
}

// Status filter (clicking a stat pill) — narrows the visible groups
$status_filter = (isset($_GET['status']) && in_array($_GET['status'], ['pending','in_progress','completed'])) ? $_GET['status'] : '';
if ($status_filter !== '') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Cap on completed tasks (most recent N)
$completed_limit = isset($_GET['completed_limit']) ? max(10, min(500, intval($_GET['completed_limit']))) : 30;

// Fetch tasks. We fetch ALL non-completed (matching filters) and the most recent N completed.
$sql = "
    SELECT t.*, c.name AS category_name, c.color AS category_color,
           (SELECT COUNT(*) FROM subtasks WHERE todo_id = t.id) AS subtask_count,
           (SELECT COUNT(*) FROM subtasks WHERE todo_id = t.id AND completed = 1) AS completed_subtasks
    FROM tbltodos t
    LEFT JOIN categories c ON t.category_id = c.id
    $where_clause
    ORDER BY
        CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END,
        CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
        t.due_date ASC,
        CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
        t.created_at DESC
";
$stmt = $con->prepare($sql);
if (!empty($params)) $stmt->bind_param($param_types, ...$params);
$stmt->execute();
$all_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total completed count BEFORE capping (so we can show "+ N more")
$completed_total = 0;
foreach ($all_rows as $r) if ($r['status'] === 'completed') $completed_total++;

// Group by date bucket
$today_str    = date('Y-m-d');
$tomorrow_str = date('Y-m-d', strtotime('+1 day'));
$week_end_str = date('Y-m-d', strtotime('+7 days'));

$groups = [
    'overdue'   => ['label' => 'Overdue',     'icon' => 'fa-exclamation-triangle', 'color' => 'danger',    'items' => []],
    'today'     => ['label' => 'Today',       'icon' => 'fa-calendar-day',         'color' => 'info',      'items' => []],
    'tomorrow'  => ['label' => 'Tomorrow',    'icon' => 'fa-sun',                  'color' => 'primary',   'items' => []],
    'week'      => ['label' => 'This Week',   'icon' => 'fa-calendar-week',        'color' => 'primary',   'items' => []],
    'later'     => ['label' => 'Later',       'icon' => 'fa-calendar',             'color' => 'secondary', 'items' => []],
    'no_date'   => ['label' => 'No Due Date', 'icon' => 'fa-infinity',             'color' => 'secondary', 'items' => []],
    'completed' => ['label' => 'Completed',   'icon' => 'fa-check-circle',         'color' => 'success',   'items' => []],
];

$completed_shown = 0;
foreach ($all_rows as $r) {
    if ($r['status'] === 'completed') {
        if ($completed_shown < $completed_limit) {
            $groups['completed']['items'][] = $r;
            $completed_shown++;
        }
        continue;
    }
    $d = $r['due_date'];
    if (!$d) {
        $bucket = 'no_date';
    } elseif ($d < $today_str) {
        $bucket = 'overdue';
    } elseif ($d === $today_str) {
        $bucket = 'today';
    } elseif ($d === $tomorrow_str) {
        $bucket = 'tomorrow';
    } elseif ($d <= $week_end_str) {
        $bucket = 'week';
    } else {
        $bucket = 'later';
    }
    $groups[$bucket]['items'][] = $r;
}

// Get statistics (without default filter to show all stats)
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN due_date = CURDATE() THEN 1 ELSE 0 END) as due_today
    FROM tbltodos
";
$stats_result = $con->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Helper function to build filter URLs
function buildFilterUrl($filter_type, $filter_value = null) {
    $current_params = $_GET;

    // Remove pagination when changing filters
    unset($current_params['page']);
    unset($current_params['view_all']);

    // Clear existing filters and set new ones
    unset($current_params['status']);
    unset($current_params['due_filter']);

    switch ($filter_type) {
        case 'all':
            $current_params['view_all'] = '1';
            break;
        case 'status':
            $current_params['status'] = $filter_value;
            break;
        case 'overdue':
            $current_params['due_filter'] = 'overdue';
            break;
        case 'due_today':
            $current_params['due_filter'] = 'today';
            break;
    }

    return '?' . http_build_query($current_params);
}

if (isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']);
}
?>


<!-- ============================================================
     Clean Grouped-List Redesign
     ============================================================ -->

<!-- Header -->
<div class="card shadow-none border mb-3">
    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
    <div class="card-header z-1">
        <div class="row flex-between-center gx-0">
            <div class="col-lg-auto d-flex align-items-center flex-wrap gap-2">
                <h4 class="mb-0 text-primary fw-bold">To Do <span class="text-info fw-medium">List</span></h4>
            </div>
            <div class="col-lg-auto pt-3 pt-lg-0">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal">
                        <i class="fas fa-tags me-1"></i>Categories
                    </button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="fas fa-plus me-1"></i>Full Form
                    </button>
                    <h6 class="mb-0 badge rounded-pill badge-subtle-info ms-2"><?php echo date("jS F Y"); ?> | <span id="timeDisplay"></span></h6>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compact Stat Pills -->
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a href="<?php echo buildFilterUrl('due_today'); ?>" class="todo-stat-pill <?php echo ($_GET['due_filter'] ?? '') === 'today' ? 'active border-secondary' : ''; ?>">
        <i class="fas fa-calendar-day me-1 text-secondary"></i>Due Today
        <span class="badge bg-secondary bg-opacity-25 text-secondary ms-1"><?php echo $stats['due_today']; ?></span>
    </a>
    <a href="<?php echo buildFilterUrl('overdue'); ?>" class="todo-stat-pill <?php echo ($_GET['due_filter'] ?? '') === 'overdue' ? 'active border-danger text-danger' : ''; ?>">
        <i class="fas fa-exclamation-triangle me-1 text-danger"></i>Overdue
        <span class="badge bg-danger bg-opacity-25 text-danger ms-1"><?php echo $stats['overdue']; ?></span>
    </a>
    <a href="<?php echo buildFilterUrl('status', 'pending'); ?>" class="todo-stat-pill <?php echo $status_filter === 'pending' ? 'active border-info text-info' : ''; ?>">
        <i class="fas fa-hourglass-start me-1 text-info"></i>Pending
        <span class="badge bg-info bg-opacity-25 text-info ms-1"><?php echo $stats['pending']; ?></span>
    </a>
    <a href="<?php echo buildFilterUrl('status', 'in_progress'); ?>" class="todo-stat-pill <?php echo $status_filter === 'in_progress' ? 'active border-warning text-warning' : ''; ?>">
        <i class="fas fa-clock me-1 text-warning"></i>In Progress
        <span class="badge bg-warning bg-opacity-25 text-warning ms-1"><?php echo $stats['in_progress']; ?></span>
    </a>
    <a href="<?php echo buildFilterUrl('status', 'completed'); ?>" class="todo-stat-pill <?php echo $status_filter === 'completed' ? 'active border-success text-success' : ''; ?>">
        <i class="fas fa-check-circle me-1 text-success"></i>Completed
        <span class="badge bg-success bg-opacity-25 text-success ms-1"><?php echo $stats['completed']; ?></span>
    </a>
    <a href="<?php echo parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>" class="todo-stat-pill">
        <i class="fas fa-tasks me-1 text-primary"></i>All
        <span class="badge bg-primary bg-opacity-25 text-primary ms-1"><?php echo $stats['total']; ?></span>
    </a>
</div>

<!-- Inline Quick Add + Search/Filter Bar -->
<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body py-2 px-3">
        <!-- Quick Add Row -->
        <form id="quickAddTaskForm" class="d-flex gap-2 align-items-center flex-wrap" autocomplete="off">
            <i class="fas fa-plus-circle text-primary"></i>
            <input type="text" name="title" id="quickAddTitle" class="form-control form-control-sm border-0 shadow-none flex-grow-1"
                   placeholder="Quick add a task..." required style="min-width:180px;background:transparent;">
            <div class="d-flex gap-2 align-items-center quick-add-meta" style="display:none !important;">
                <select name="priority" id="quickAddPriority" class="form-select form-select-sm" style="width:auto;">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                </select>
                <input type="date" name="due_date" id="quickAddDue" class="form-control form-control-sm" style="width:auto;">
                <select name="category_id" id="quickAddCategory" class="form-select form-select-sm" style="width:auto;">
                    <option value="">No category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Add</button>
            </div>
        </form>
    </div>

    <!-- Search/Filter Row -->
    <div class="card-body py-2 px-3 border-top">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <div class="position-relative flex-grow-1" style="min-width:200px;max-width:380px;">
                <i class="fas fa-search position-absolute top-50 translate-middle-y ms-3 text-muted" style="font-size:0.85rem;"></i>
                <input type="text" id="listSearch" class="form-control form-control-sm ps-5"
                       placeholder="Search tasks..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <select id="listPriorityFilter" class="form-select form-select-sm" style="width:auto;">
                <option value="">All priorities</option>
                <option value="high"   <?php echo ($_GET['priority'] ?? '') === 'high'   ? 'selected' : ''; ?>>High</option>
                <option value="medium" <?php echo ($_GET['priority'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="low"    <?php echo ($_GET['priority'] ?? '') === 'low'    ? 'selected' : ''; ?>>Low</option>
            </select>
            <select id="listCategoryFilter" class="form-select form-select-sm" style="width:auto;">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo ($_GET['category'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select id="listStatusFilter" class="form-select form-select-sm" style="width:auto;">
                <option value="">All statuses</option>
                <option value="pending"     <?php echo $status_filter === 'pending'     ? 'selected' : ''; ?>>Pending</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed"   <?php echo $status_filter === 'completed'   ? 'selected' : ''; ?>>Completed</option>
            </select>
            <?php if (!empty(array_intersect_key($_GET, array_flip(['search','priority','category','due_filter','status'])))): ?>
                <a href="<?php echo parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>" class="btn btn-sm btn-link text-decoration-none text-muted">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bulk Actions Bar (hidden until selections) -->
<div class="bulk-actions mb-3" style="display:none;">
    <div class="card border-warning border-2">
        <div class="card-body py-2">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span><strong id="selectedCount">0</strong> selected</span>
                <button class="btn btn-success btn-sm" onclick="bulkAction('complete')">
                    <i class="fas fa-check me-1"></i>Mark Complete
                </button>
                <button class="btn btn-danger btn-sm" onclick="bulkAction('delete')">
                    <i class="fas fa-trash me-1"></i>Delete
                </button>
                <button class="btn btn-outline-secondary btn-sm ms-auto" onclick="clearSelection()">
                    Clear
                </button>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Render a single task row (clean list style with inline expand target).
 */
function renderTaskRow($todo) {
    $isOverdue  = $todo['due_date'] && $todo['due_date'] < date('Y-m-d') && $todo['status'] !== 'completed';
    $isDueToday = $todo['due_date'] === date('Y-m-d');
    $isCompleted = $todo['status'] === 'completed';
    $priorityColor = $todo['priority'] === 'high' ? 'danger' : ($todo['priority'] === 'medium' ? 'warning' : 'success');
    $subtotal = (int)$todo['subtask_count'];
    $sdone    = (int)$todo['completed_subtasks'];
    $sprogress = $subtotal > 0 ? round(($sdone / $subtotal) * 100) : 0;
    ?>
    <div class="task-row <?= $isCompleted ? 'is-completed' : '' ?> <?= $isOverdue ? 'is-overdue' : '' ?>"
         data-id="<?= $todo['id'] ?>"
         data-title="<?= htmlspecialchars(strtolower($todo['title'])) ?>"
         data-description="<?= htmlspecialchars(strtolower($todo['description'] ?? '')) ?>"
         data-priority="<?= htmlspecialchars($todo['priority']) ?>"
         data-category="<?= (int)($todo['category_id'] ?? 0) ?>"
         data-status="<?= htmlspecialchars($todo['status']) ?>"
         data-due-date="<?= htmlspecialchars($todo['due_date'] ?? '') ?>"
         draggable="true">

        <div class="task-row-main">
            <!-- Drag handle (visible on hover) -->
            <div class="task-drag-handle" title="Drag to reschedule">
                <i class="fas fa-grip-vertical"></i>
            </div>

            <!-- Bulk checkbox -->
            <input type="checkbox" class="form-check-input bulk-select flex-shrink-0"
                   value="<?= $todo['id'] ?>" onclick="event.stopPropagation();">

            <!-- Complete-toggle "circle" -->
            <button type="button" class="task-complete-btn js-quick-status flex-shrink-0"
                    data-id="<?= $todo['id'] ?>"
                    data-status="<?= $isCompleted ? 'pending' : 'completed' ?>"
                    title="<?= $isCompleted ? 'Reopen' : 'Mark complete' ?>"
                    onclick="event.stopPropagation();">
                <i class="fas <?= $isCompleted ? 'fa-check-circle text-success' : 'fa-circle text-muted' ?>"></i>
            </button>

            <!-- Priority indicator -->
            <span class="task-priority-dot bg-<?= $priorityColor ?>" title="<?= ucfirst($todo['priority']) ?> priority"></span>

            <!-- Title + meta (main content) -->
            <div class="task-content flex-grow-1 min-w-0">
                <div class="task-title <?= $isCompleted ? 'text-decoration-line-through text-muted' : '' ?>">
                    <?= htmlspecialchars($todo['title']) ?>
                </div>
                <div class="task-meta">
                    <?php if (!empty($todo['due_date'])): ?>
                        <span class="<?= $isOverdue ? 'text-danger fw-semibold' : ($isDueToday ? 'text-warning fw-semibold' : 'text-muted') ?>">
                            <i class="fas <?= $isOverdue ? 'fa-exclamation-triangle' : 'fa-calendar' ?> me-1"></i><?= date('M j', strtotime($todo['due_date'])) ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($todo['category_name'])): ?>
                        <span class="task-category-chip" style="background-color: <?= htmlspecialchars($todo['category_color'] ?: '#6c757d') ?>;">
                            <?= htmlspecialchars($todo['category_name']) ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($subtotal > 0): ?>
                        <span class="text-muted small" title="Subtasks">
                            <i class="fas fa-tasks me-1"></i><?= $sdone ?>/<?= $subtotal ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($todo['description'])): ?>
                        <span class="text-muted small task-has-desc" title="Has description">
                            <i class="fas fa-align-left"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status pill (always visible) -->
            <span class="task-status-pill flex-shrink-0 status-<?= $todo['status'] ?>" title="Status">
                <?= ucwords(str_replace('_', ' ', $todo['status'])) ?>
            </span>

            <!-- Per-row kebab menu -->
            <div class="dropdown flex-shrink-0">
                <button class="btn btn-sm btn-link text-muted p-0 px-1" data-bs-toggle="dropdown" onclick="event.stopPropagation();">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <button class="dropdown-item edit-btn"
                                data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                data-id="<?= $todo['id'] ?>"
                                data-title="<?= htmlspecialchars($todo['title']) ?>"
                                data-description="<?= htmlspecialchars($todo['description'] ?? '') ?>"
                                data-priority="<?= htmlspecialchars($todo['priority']) ?>"
                                data-category="<?= (int)($todo['category_id'] ?? 0) ?>"
                                data-due-date="<?= htmlspecialchars($todo['due_date'] ?? '') ?>">
                            <i class="fas fa-edit me-2 text-primary"></i>Edit
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item js-quick-status" data-id="<?= $todo['id'] ?>" data-status="in_progress">
                            <i class="fas fa-clock me-2 text-warning"></i>Mark in progress
                        </button>
                    </li>
                    <?php if (!$isCompleted): ?>
                        <li><button class="dropdown-item js-quick-status" data-id="<?= $todo['id'] ?>" data-status="completed">
                                <i class="fas fa-check me-2 text-success"></i>Mark complete
                            </button></li>
                    <?php else: ?>
                        <li><button class="dropdown-item js-quick-status" data-id="<?= $todo['id'] ?>" data-status="pending">
                                <i class="fas fa-undo me-2 text-info"></i>Reopen
                            </button></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><button class="dropdown-item text-danger delete-btn" data-id="<?= $todo['id'] ?>">
                            <i class="fas fa-trash me-2"></i>Delete
                        </button></li>
                </ul>
            </div>
        </div>

        <!-- Inline expansion (loaded lazily on click) -->
        <div class="task-row-expand" style="display:none;"></div>
    </div>
<?php }
?>

<!-- Task List -->
<div class="task-list-container" id="taskList">
    <?php
    $any_visible = false;
    foreach ($groups as $bucket_key => $group):
        if (empty($group['items'])) continue;
        $any_visible = true;
        ?>
        <div class="task-group" data-bucket="<?= $bucket_key ?>">
            <div class="task-group-header" data-droptarget="<?= $bucket_key ?>">
                <i class="fas <?= $group['icon'] ?> text-<?= $group['color'] ?> me-2"></i>
                <strong class="text-<?= $group['color'] ?>"><?= $group['label'] ?></strong>
                <span class="badge bg-<?= $group['color'] ?> bg-opacity-15 text-<?= $group['color'] ?> ms-2 task-group-count"><?= count($group['items']) ?></span>
                <button type="button" class="btn btn-sm btn-link text-muted ms-auto py-0 px-1 js-collapse-group"
                        data-bucket="<?= $bucket_key ?>" title="Collapse / expand">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="task-group-body">
                <?php foreach ($group['items'] as $todo) renderTaskRow($todo); ?>
                <?php if ($bucket_key === 'completed' && $completed_total > count($group['items'])): ?>
                    <div class="text-center py-2 border-top">
                        <a href="?<?= http_build_query(array_merge($_GET, ['completed_limit' => $completed_limit + 30])) ?>" class="btn btn-sm btn-link text-decoration-none">
                            Show <?= $completed_total - count($group['items']) ?> more completed
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!$any_visible): ?>
        <div class="text-center py-5">
            <i class="fas fa-clipboard-list fa-3x text-muted opacity-25 mb-3"></i>
            <h5 class="text-muted fw-light">No tasks found</h5>
            <p class="text-muted small mb-3">
                <?php if (!empty(array_filter([$_GET['search'] ?? '', $_GET['priority'] ?? '', $_GET['category'] ?? '', $_GET['due_filter'] ?? '', $status_filter]))): ?>
                    Try clearing your filters or
                <?php else: ?>
                    Use the quick-add bar above or
                <?php endif; ?>
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-bs-toggle="modal" data-bs-target="#addTaskModal">open the full form</button>.
            </p>
        </div>
    <?php endif; ?>
</div>
<!-- /Task List -->

<?php
// Kept for backward compat with anything that may still reference it
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>


<!-- Rest of the modals and forms remain the same -->
<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="add-todo" method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-control" id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-control" id="category" name="category_id">
                                <option value="">No Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="attachments" class="form-label">Attachments</label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                    <small class="text-muted">You can select multiple files</small>
                </div>

                <!-- Subtasks Section -->
                <div class="mb-3">
                    <label class="form-label">Subtasks</label>
                    <div id="subtasksContainer">
                        <!-- Subtasks will be added here -->
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSubtaskField()">
                        <i class="fas fa-plus"></i> Add Subtask
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="edit-todo" method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTaskModalLabel">Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editTaskId" name="id">

                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="editTitle" name="title" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="editPriority" class="form-label">Priority</label>
                            <select class="form-control" id="editPriority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="editDescription" class="form-label">Description</label>
                    <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="editCategory" class="form-label">Category</label>
                            <select class="form-control" id="editCategory" name="category_id">
                                <option value="">No Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="editDueDate" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="editDueDate" name="due_date">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="editAttachments" class="form-label">Add More Attachments</label>
                    <input type="file" class="form-control" id="editAttachments" name="attachments[]" multiple>
                </div>

                <!-- Existing Attachments -->
                <div id="existingAttachments" class="mb-3">
                    <!-- Will be populated via JavaScript -->
                </div>

                <!-- Subtasks Section -->
                <div class="mb-3">
                    <label class="form-label">Subtasks</label>
                    <div id="editSubtasksContainer">
                        <!-- Subtasks will be loaded here -->
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addEditSubtaskField()">
                        <i class="fas fa-plus"></i> Add Subtask
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><span><i class="fas fa-save"></i></span> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- View Task Modal -->
<div class="modal fade" id="viewTaskModal" tabindex="-1" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border">
            <div class="modal-header bg-body-primary ps-card pe-5 border-bottom-0">
                <h5 class="modal-title" id="viewTaskTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-card pb-card pt-1 fs-10">

                <!-- Description Section -->
                <div class="d-flex mt-3">
                    <span class="fa-stack ms-n1 me-3">
                        <i class="fas fa-circle fa-stack-2x text-200"></i>
                        <i class="fas fa-align-left fa-stack-1x text-primary"></i>
                    </span>
                    <div class="flex-1">
                        <h6>Description</h6>
                        <p class="mb-0" id="viewTaskDescription">
                            <!-- Description content will be loaded here -->
                        </p>
                    </div>
                </div>

                <!-- Priority & Status Section -->
                <div class="d-flex mt-3">
                    <span class="fa-stack ms-n1 me-3">
                        <i class="fas fa-circle fa-stack-2x text-200"></i>
                        <i class="fas fa-flag fa-stack-1x text-primary"></i>
                    </span>
                    <div class="flex-1">
                        <h6>Priority & Status</h6>
                        <p class="mb-1">
                            <span class="badge me-2" id="viewTaskPriority"></span>
                            <span class="badge" id="viewTaskStatus"></span>
                        </p>
                    </div>
                </div>

                <!-- Due Date Section -->
                <div class="d-flex mt-3">
                    <span class="fa-stack ms-n1 me-3">
                        <i class="fas fa-circle fa-stack-2x text-200"></i>
                        <i class="fas fa-calendar-check fa-stack-1x text-primary"></i>
                    </span>
                    <div class="flex-1">
                        <h6>Due Date</h6>
                        <p class="mb-1" id="viewTaskDueDate">
                            <!-- Due date will be loaded here -->
                        </p>
                    </div>
                </div>

                <!-- Category Section -->
                <div class="d-flex mt-3">
                    <span class="fa-stack ms-n1 me-3">
                        <i class="fas fa-circle fa-stack-2x text-200"></i>
                        <i class="fas fa-tag fa-stack-1x text-primary"></i>
                    </span>
                    <div class="flex-1">
                        <h6>Category</h6>
                        <p class="mb-0" id="viewTaskCategory">
                            <!-- Category will be loaded here -->
                        </p>
                    </div>
                </div>

                <!-- Subtasks Section -->
                <div class="d-flex mt-3">
                    <span class="fa-stack ms-n1 me-3">
                        <i class="fas fa-circle fa-stack-2x text-200"></i>
                        <i class="fas fa-tasks fa-stack-1x text-primary"></i>
                    </span>
                    <div class="flex-1">
                        <h6>Subtasks</h6>
                        <div id="viewTaskSubtasks">
                            <!-- Subtasks will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Attachments Section -->
                <div class="d-flex mt-3">
                    <span class="fa-stack ms-n1 me-3">
                        <i class="fas fa-circle fa-stack-2x text-200"></i>
                        <i class="fas fa-paperclip fa-stack-1x text-primary"></i>
                    </span>
                    <div class="flex-1">
                        <div class="d-flex justify-content-between mb-2">
                            <h6 class="mb-0 fs-9">Attachments</h6>
                        </div>
                        <div id="viewTaskAttachments">
                            <!-- Attachments will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Timeline Section -->
                <div class="d-flex mt-3">
                    <span class="fa-stack ms-n1 me-3">
                        <i class="fas fa-circle fa-stack-2x text-200"></i>
                        <i class="fas fa-clock fa-stack-1x text-primary"></i>
                    </span>
                    <div class="flex-1">
                        <h6>Timeline</h6>
                        <p class="mb-1">
                            <small class="text-muted">Created: <span id="viewTaskCreated"></span></small><br>
                            <small class="text-muted">Completed: <span id="viewTaskCompleted"></span></small>
                        </p>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editTaskFromView()">
                    <i class="fas fa-edit me-2"></i>Edit Task
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Category Management Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalLabel">Manage Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Add New Category Form -->
                <form action="manage-categories" method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="name" placeholder="Category Name" required>
                        </div>
                        <div class="col-md-4">
                            <input type="color" class="form-control" name="color" value="#007bff">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </form>

                <!-- Existing Categories -->
                <div class="list-group">
                    <?php foreach($categories as $category): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <span class="badge me-2" style="background-color: <?php echo $category['color']; ?>; width: 20px; height: 20px;"></span>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </div>
                            <form action="manage-categories" method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this category?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Confirmation Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="fas fa-download me-2"></i>Export Tasks
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6>Export Details:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>Total Tasks: <span id="exportTotalTasks"><?php echo $stats['total']; ?></span></li>
                        <li><i class="fas fa-check text-success me-2"></i>Completed: <span id="exportCompletedTasks"><?php echo $stats['completed']; ?></span></li>
                        <li><i class="fas fa-clock text-warning me-2"></i>Pending: <span id="exportPendingTasks"><?php echo $stats['pending']; ?></span></li>
                        <li><i class="fas fa-play text-info me-2"></i>In Progress: <span id="exportInProgressTasks"><?php echo $stats['in_progress']; ?></span></li>
                    </ul>
                </div>

                <div class="mb-3">
                    <h6>Export Options:</h6>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="exportType" id="exportAll" value="all" checked>
                        <label class="form-check-label" for="exportAll">
                            <strong>All Tasks</strong> - Export all tasks regardless of filters
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="exportType" id="exportFiltered" value="filtered">
                        <label class="form-check-label" for="exportFiltered">
                            <strong>Current View</strong> - Export only tasks currently visible with applied filters
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <h6>Include Additional Data:</h6>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="includeSubtasks" checked>
                        <label class="form-check-label" for="includeSubtasks">
                            Include subtasks information
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="includeAttachments" checked>
                        <label class="form-check-label" for="includeAttachments">
                            Include attachments information
                        </label>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <small>The CSV file will be downloaded automatically and will include task details, categories, due dates, and timestamps.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmExport()">
                    <i class="fas fa-download me-2"></i>Export CSV
                </button>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<script>
    // Global variables
    let subtaskCounter = 0;
    let editSubtaskCounter = 0;

    // Time display
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        document.getElementById('timeDisplay').textContent = timeString;
    }
    setInterval(updateTime, 1000);
    updateTime();

    // Tasks per page change function
    function changeTasksPerPage(value) {
        const url = new URL(window.location);
        url.searchParams.set('per_page', value);
        url.searchParams.delete('page'); // Reset to first page
        window.location.href = url.toString();
    }

    // Auto-save functionality
    let autoSaveTimer;
    function autoSave() {
        const title = document.getElementById('title')?.value || '';
        const description = document.getElementById('description')?.value || '';

        if (title || description) {
            localStorage.setItem('draft_title', title);
            localStorage.setItem('draft_description', description);
        }
    }

    // Load draft on page load
    document.addEventListener('DOMContentLoaded', function() {
        const draftTitle = localStorage.getItem('draft_title');
        const draftDescription = localStorage.getItem('draft_description');

        if (draftTitle && document.getElementById('title')) {
            document.getElementById('title').value = draftTitle;
        }
        if (draftDescription && document.getElementById('description')) {
            document.getElementById('description').value = draftDescription;
        }

        // Clear drafts when form is submitted
        document.querySelector('#addTaskModal form')?.addEventListener('submit', function() {
            localStorage.removeItem('draft_title');
            localStorage.removeItem('draft_description');
        });
    });

    // Enhanced hover effects for better UX
    document.addEventListener('DOMContentLoaded', function() {
        // Show/hide action buttons on hover for desktop
        if (window.innerWidth > 768) {
            document.querySelectorAll('.todo-list-item').forEach(item => {
                const hoverActions = item.querySelector('.hover-actions');

                item.addEventListener('mouseenter', function() {
                    hoverActions.style.opacity = '1';
                });

                item.addEventListener('mouseleave', function() {
                    hoverActions.style.opacity = '0.3';
                });

                // Keep visible when focused
                hoverActions.addEventListener('focusin', function() {
                    this.style.opacity = '1';
                });
            });
        }
    });

    // Auto-save on input
    document.getElementById('title')?.addEventListener('input', function() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(autoSave, 1000);
    });

    document.getElementById('description')?.addEventListener('input', function() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(autoSave, 1000);
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+N to add new task
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            document.querySelector('[data-bs-target="#addTaskModal"]').click();
        }

        // Escape to close modals
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                bootstrap.Modal.getInstance(openModal).hide();
            }
        }
    });

    // Subtask management
    function addSubtaskField() {
        subtaskCounter++;
        const container = document.getElementById('subtasksContainer');
        const subtaskDiv = document.createElement('div');
        subtaskDiv.className = 'input-group mb-2';
        subtaskDiv.innerHTML = `
        <input type="text" class="form-control" name="subtasks[]" placeholder="Subtask title">
        <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
        container.appendChild(subtaskDiv);
    }

    function addEditSubtaskField() {
        editSubtaskCounter++;
        const container = document.getElementById('editSubtasksContainer');
        const subtaskDiv = document.createElement('div');
        subtaskDiv.className = 'input-group mb-2';
        subtaskDiv.innerHTML = `
        <input type="text" class="form-control" name="new_subtasks[]" placeholder="New subtask title">
        <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
        container.appendChild(subtaskDiv);
    }

    // Status update
    function updateStatus(taskId, status) {
        // Create FormData for proper POST request
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('id', taskId);
        formData.append('status', status);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text(); // Get as text first to debug
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // Show success message briefly
                        showNotification('Status updated successfully!', 'success');
                        // Optional: Update UI without full reload
                        updateTaskRowStatus(taskId, status);
                    } else {
                        showNotification('Failed to update status: ' + (data.message || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response text:', text);
                    showNotification('Server response error. Check console for details.', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showNotification('Network error: ' + error.message, 'error');
            });
    }

    // Bulk selection — old #selectAll checkbox removed in redesign; guard for null
    document.getElementById('selectAll')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.bulk-select');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActions();
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('bulk-select')) {
            updateBulkActions();
        }
    });

    function updateBulkActions() {
        const selected = document.querySelectorAll('.bulk-select:checked');
        const bulkActions = document.querySelector('.bulk-actions');
        const selectedCount = document.getElementById('selectedCount');

        if (selectedCount) selectedCount.textContent = selected.length;
        if (bulkActions) bulkActions.style.display = selected.length > 0 ? 'block' : 'none';

        // Update select all checkbox (if it exists)
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            const allCheckboxes = document.querySelectorAll('.bulk-select');
            selectAll.checked = selected.length === allCheckboxes.length && allCheckboxes.length > 0;
            selectAll.indeterminate = selected.length > 0 && selected.length < allCheckboxes.length;
        }
    }

    // Fix for bulk action - update the fetch body
    function bulkAction(action) {
        const selected = Array.from(document.querySelectorAll('.bulk-select:checked')).map(cb => cb.value);

        if (selected.length === 0) {
            showNotification('Please select tasks first', 'warning');
            return;
        }

        const actionText = action === 'delete' ? 'delete' : 'mark as complete';
        if (!confirm(`Are you sure you want to ${actionText} ${selected.length} task(s)?`)) {
            return;
        }

        // Create FormData for proper POST request
        const formData = new FormData();
        formData.append('action', 'bulk_action');
        formData.append('bulk_type', action);

        // Add each ID as a separate form field
        selected.forEach(id => {
            formData.append('ids[]', id);
        });

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showNotification(`Successfully ${actionText}d ${selected.length} task(s)!`, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Failed to perform bulk action: ' + (data.message || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response text:', text);
                    showNotification('Server response error. Check console for details.', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showNotification('Network error: ' + error.message, 'error');
            });
    }

    function clearSelection() {
        document.querySelectorAll('.bulk-select').forEach(cb => cb.checked = false);
        const selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;
        updateBulkActions();
    }

    // Load task details for view modal
    function loadTaskDetails(taskId) {
        fetch(`get-task-details.php?id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const task = data.task;

                    // Set title
                    document.getElementById('viewTaskTitle').textContent = task.title;

                    // Set description
                    const descriptionElement = document.getElementById('viewTaskDescription');
                    descriptionElement.style.whiteSpace = 'pre-line';
                    descriptionElement.textContent = task.description || 'No description provided';

                    // Set priority and status
                    const priorityBadge = document.getElementById('viewTaskPriority');
                    priorityBadge.textContent = task.priority.toUpperCase();
                    priorityBadge.className = `rounded-pill badge-subtle-${getPriorityColor(task.priority)}`;

                    const statusBadge = document.getElementById('viewTaskStatus');
                    statusBadge.textContent = task.status.replace('_', ' ').toUpperCase();
                    statusBadge.className = `rounded-pill badge-subtle-${getStatusColor(task.status)}`;

                    // Set due date
                    const dueDateElement = document.getElementById('viewTaskDueDate');
                    if (task.due_date) {
                        const dueDate = new Date(task.due_date);
                        const today = new Date();
                        const isOverdue = dueDate < today && task.status !== 'completed';
                        const isDueToday = dueDate.toDateString() === today.toDateString();

                        let dateClass = '';
                        let dateIcon = '';
                        if (isOverdue) {
                            dateClass = 'text-danger';
                            dateIcon = '<i class="fas fa-exclamation-triangle me-1"></i>';
                        } else if (isDueToday) {
                            dateClass = 'text-warning';
                            dateIcon = '<i class="fas fa-clock me-1"></i>';
                        } else {
                            dateClass = 'text-muted';
                            dateIcon = '<i class="fas fa-calendar me-1"></i>';
                        }

                        dueDateElement.innerHTML = `<span class="${dateClass}">${dateIcon}${dueDate.toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        })}</span>`;
                    } else {
                        dueDateElement.innerHTML = '<span class="text-muted">No due date set</span>';
                    }

                    // Set category
                    const categoryElement = document.getElementById('viewTaskCategory');
                    if (task.category_name) {
                        categoryElement.innerHTML = `<span class="badge" style="background-color: ${task.category_color || '#6c757d'}">${task.category_name}</span>`;
                    } else {
                        categoryElement.innerHTML = '<span class="text-muted">No category assigned</span>';
                    }

                    // Load subtasks
                    const subtasksContainer = document.getElementById('viewTaskSubtasks');
                    if (data.subtasks && data.subtasks.length > 0) {
                        const completedCount = data.subtasks.filter(s => s.completed).length;
                        const totalCount = data.subtasks.length;
                        const progressPercent = Math.round((completedCount / totalCount) * 100);

                        let subtasksHTML = `
                    <div class="mb-2">
                        <small class="text-muted">${completedCount}/${totalCount} completed (${progressPercent}%)</small>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar" style="width: ${progressPercent}%"></div>
                        </div>
                    </div>
                `;

                        subtasksHTML += data.subtasks.map(subtask =>
                            `<div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" ${subtask.completed ? 'checked' : ''} disabled>
                        <label class="form-check-label ${subtask.completed ? 'text-decoration-line-through text-muted' : ''}">
                            ${subtask.title}
                        </label>
                    </div>`
                        ).join('');

                        subtasksContainer.innerHTML = subtasksHTML;
                    } else {
                        subtasksContainer.innerHTML = '<p class="text-muted mb-0">No subtasks added</p>';
                    }


                    // Load attachments
                    const attachmentsContainer = document.getElementById('viewTaskAttachments');
                    if (data.attachments && data.attachments.length > 0) {
                        attachmentsContainer.innerHTML = data.attachments.map(attachment =>
                            `<div class="d-flex align-items-center mb-1">
                        <a href="${attachment.file_path}"  class="text-decoration-none me-3" data-gallery="attachment-bg">
                            <div class="bg-attachment">
                                <div class="bg-holder rounded" style="background-image:url(${attachment.file_path});"></div><!--/.bg-holder-->
                            </div>
                        </a>
                        <div class="flex-1 fs-11">
                            <h6 class="mb-1"> <a class="text-decoration-none" href="${attachment.file_path}" data-gallery="attachment-title">${attachment.filename}</a></h6>
                            <p class="mb-0">(${formatFileSize(attachment.file_size || 0)})</p>
                        </div>
                    </div>`
                        ).join('');
                    } else {
                        attachmentsContainer.innerHTML = '<p class="text-muted mb-0">No attachments</p>';
                    }

                    // Set timeline
                    document.getElementById('viewTaskCreated').textContent = formatDateTime(task.created_at);
                    document.getElementById('viewTaskCompleted').textContent = task.completed_at ? formatDateTime(task.completed_at) : 'Not completed yet';

                    // Store task ID for potential editing
                    document.getElementById('viewTaskModal').setAttribute('data-task-id', taskId);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load task details');
            });
    }

    // Helper function to format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Helper function to format date and time
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Function to edit task from view modal
    function editTaskFromView() {
        const taskId = document.getElementById('viewTaskModal').getAttribute('data-task-id');
        const taskRow = document.querySelector(`[data-id="${taskId}"]`);

        if (taskRow) {
            // Close view modal
            bootstrap.Modal.getInstance(document.getElementById('viewTaskModal')).hide();

            // Trigger edit modal
            setTimeout(() => {
                const editBtn = taskRow.querySelector('.edit-btn');
                if (editBtn) {
                    editBtn.click();
                }
            }, 300);
        }
    }

    function getStatusColor(status) {
        switch(status) {
            case 'completed': return 'success';
            case 'in_progress': return 'warning';
            case 'pending': return 'secondary';
            default: return 'secondary';
        }
    }

    function getPriorityColor(priority) {
        switch(priority) {
            case 'high': return 'danger';
            case 'medium': return 'warning';
            case 'low': return 'success';
            default: return 'secondary';
        }
    }

    // Edit task modal
    const editTaskModal = document.getElementById('editTaskModal');
    editTaskModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const title = button.getAttribute('data-title');
        const description = button.getAttribute('data-description');
        const priority = button.getAttribute('data-priority');
        const category = button.getAttribute('data-category');
        const dueDate = button.getAttribute('data-due-date');

        document.getElementById('editTaskId').value = id;
        document.getElementById('editTitle').value = title;
        document.getElementById('editDescription').value = description;
        document.getElementById('editPriority').value = priority;
        document.getElementById('editCategory').value = category || '';
        document.getElementById('editDueDate').value = dueDate || '';

        // Load existing subtasks and attachments
        loadEditTaskDetails(id);
    });

    function loadEditTaskDetails(taskId) {
        fetch(`get-task-details.php?id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Load existing subtasks
                    const subtasksContainer = document.getElementById('editSubtasksContainer');
                    subtasksContainer.innerHTML = '';

                    if (data.subtasks && data.subtasks.length > 0) {
                        data.subtasks.forEach(subtask => {
                            const subtaskDiv = document.createElement('div');
                            subtaskDiv.className = 'input-group mb-2';
                            subtaskDiv.innerHTML = `
                            <input type="text" class="form-control" name="existing_subtasks[${subtask.id}]" value="${subtask.title}">
                            <div class="input-group-text">
                                <input type="checkbox" name="subtask_completed[${subtask.id}]" ${subtask.completed ? 'checked' : ''}>
                            </div>
                            <button type="button" class="btn btn-outline-danger" onclick="markSubtaskForDeletion(${subtask.id}, this)">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                            subtasksContainer.appendChild(subtaskDiv);
                        });
                    }

                    // Load existing attachments
                    const attachmentsContainer = document.getElementById('existingAttachments');
                    attachmentsContainer.innerHTML = '';

                    if (data.attachments && data.attachments.length > 0) {
                        const attachmentsDiv = document.createElement('div');
                        attachmentsDiv.innerHTML = '<label class="form-label">Existing Attachments</label>';

                        data.attachments.forEach(attachment => {
                            const attachmentDiv = document.createElement('div');
                            attachmentDiv.className = 'd-flex justify-content-between align-items-center border p-2 mb-1';
                            attachmentDiv.innerHTML = `
                            <div>
                                <i class="fas fa-paperclip"></i> ${attachment.filename}
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="markAttachmentForDeletion(${attachment.id}, this)">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                            attachmentsDiv.appendChild(attachmentDiv);
                        });

                        attachmentsContainer.appendChild(attachmentsDiv);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function markSubtaskForDeletion(subtaskId, button) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'delete_subtasks[]';
        hiddenInput.value = subtaskId;
        document.getElementById('editTaskModal').querySelector('form').appendChild(hiddenInput);
        button.parentElement.remove();
    }

    function markAttachmentForDeletion(attachmentId, button) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'delete_attachments[]';
        hiddenInput.value = attachmentId;
        document.getElementById('editTaskModal').querySelector('form').appendChild(hiddenInput);
        button.parentElement.remove();
    }

    // Delete task
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            const taskId = this.getAttribute('data-id');
            if (confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
                window.location.href = `delete-todo?id=${taskId}`;
            }
        });
    });

    // Real-time search (if not using form submission)
    function setupRealTimeSearch() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            let searchTimer;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const searchTerm = this.value.toLowerCase();
                    document.querySelectorAll('.todo-list-item').forEach(item => {
                        const title = item.querySelector('.text-700').textContent.toLowerCase();
                        const description = item.querySelector('.text-600').textContent.toLowerCase();
                        const matches = title.includes(searchTerm) || description.includes(searchTerm);
                        item.style.display = matches ? 'flex' : 'none';
                    });
                }, 300);
            });
        }
    }

    // Notification system
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.custom-notification');
        existingNotifications.forEach(notification => notification.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show custom-notification`;
        notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;

        notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Export functionality
    function confirmExport() {
        const exportType = document.querySelector('input[name="exportType"]:checked').value;
        const includeSubtasks = document.getElementById('includeSubtasks').checked;
        const includeAttachments = document.getElementById('includeAttachments').checked;

        // Show loading state
        const exportBtn = document.querySelector('#exportModal .btn-primary');
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Preparing Export...';
        exportBtn.disabled = true;

        // Build export URL with parameters
        let exportUrl = '?export=csv';
        exportUrl += `&type=${exportType}`;
        exportUrl += `&subtasks=${includeSubtasks ? '1' : '0'}`;
        exportUrl += `&attachments=${includeAttachments ? '1' : '0'}`;

        // If exporting filtered results, include current filter parameters
        if (exportType === 'filtered') {
            const urlParams = new URLSearchParams(window.location.search);
            const filterParams = ['search', 'status', 'priority', 'category', 'due_filter', 'view_all'];

            filterParams.forEach(param => {
                if (urlParams.has(param) && urlParams.get(param)) {
                    exportUrl += `&${param}=${encodeURIComponent(urlParams.get(param))}`;
                }
            });
        }

        // Create a temporary link and trigger download
        const link = document.createElement('a');
        link.href = exportUrl;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Show success notification
        setTimeout(() => {
            showNotification('Export started! Your CSV file should download shortly.', 'success');

            // Reset button state
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;

            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
        }, 1000);
    }

    // Update export statistics when modal is shown
    document.getElementById('exportModal').addEventListener('show.bs.modal', function() {
        // You can update these dynamically if needed
        // For now, they're populated from PHP
    });

    // Optional: Update task row status without full page reload
    function updateTaskRowStatus(taskId, status) {
        const taskRow = document.querySelector(`[data-id="${taskId}"]`);
        if (taskRow) {
            // Update status classes
            taskRow.classList.remove('status-completed');
            if (status === 'completed') {
                taskRow.classList.add('status-completed');
            }

            // Update the dropdown to reflect the change
            const dropdown = taskRow.querySelector('.status-dropdown');
            if (dropdown) {
                dropdown.value = status;
            }
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        updateBulkActions();
        if (typeof initTaskList === 'function') initTaskList();
    });

    /* ============================================================
       TASK LIST INTERACTIVITY
       - Inline expand on row click
       - Drag-to-reschedule (drop on group header)
       - Subtask toggle
       - Live search + filter
       - Inline quick-add
       - Quick status changes
       - Group collapse
       ============================================================ */
    function initTaskList() {
        const list = document.getElementById('taskList');
        if (!list) return;

        // ---------- Inline expand on row click ----------
        list.addEventListener('click', e => {
            if (e.target.closest('input, button, a, select, .dropdown, .task-row-expand')) return;
            const row = e.target.closest('.task-row');
            if (!row) return;
            toggleRowExpand(row);
        });

        function toggleRowExpand(row) {
            const id = row.dataset.id;
            const expand = row.querySelector('.task-row-expand');
            if (!expand) return;

            if (row.classList.contains('expanded')) {
                expand.style.display = 'none';
                row.classList.remove('expanded');
                return;
            }
            if (!expand.dataset.loaded) {
                expand.innerHTML = '<div class="text-center py-3 text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</div>';
                fetch(`get-task-details.php?id=${id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            expand.innerHTML = '<div class="text-danger small px-3 py-2">Failed to load details</div>';
                            return;
                        }
                        expand.innerHTML = renderExpansion(data);
                        expand.dataset.loaded = '1';
                    })
                    .catch(() => { expand.innerHTML = '<div class="text-danger small px-3 py-2">Network error</div>'; });
            }
            expand.style.display = 'block';
            row.classList.add('expanded');
        }

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, m => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[m]));
        }

        function renderExpansion(data) {
            const t = data.task;
            const subtasks = data.subtasks || [];
            const attachments = data.attachments || [];
            const done = subtasks.filter(s => +s.completed).length;
            const total = subtasks.length;
            const pct = total ? Math.round((done / total) * 100) : 0;

            const desc = t.description
                ? `<div class="expand-section"><div class="expand-label">Description</div><div class="small" style="white-space:pre-line;">${esc(t.description)}</div></div>`
                : '';

            let subHtml = '';
            if (total > 0) {
                subHtml = `
                    <div class="expand-section">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="expand-label mb-0">Subtasks</div>
                            <small class="text-muted js-subtask-summary">${done}/${total} · ${pct}%</small>
                        </div>
                        <div class="progress mb-2" style="height:3px;"><div class="progress-bar js-subtask-bar" style="width:${pct}%"></div></div>
                        ${subtasks.map(s => `
                            <div class="form-check mb-1 d-flex align-items-center">
                                <input class="form-check-input me-2 js-subtask-toggle" type="checkbox" ${+s.completed ? 'checked' : ''} data-subtask-id="${s.id}" id="sub_${s.id}">
                                <label class="form-check-label small flex-grow-1 ${+s.completed ? 'text-decoration-line-through text-muted' : ''}" for="sub_${s.id}">${esc(s.title)}</label>
                            </div>`).join('')}
                    </div>`;
            }

            let attHtml = '';
            if (attachments.length) {
                attHtml = `
                    <div class="expand-section">
                        <div class="expand-label">Attachments</div>
                        ${attachments.map(a => `
                            <div class="small mb-1">
                                <i class="fas fa-paperclip me-1 text-muted"></i>
                                <a href="${esc(a.file_path)}" target="_blank" class="text-decoration-none">${esc(a.filename)}</a>
                            </div>`).join('')}
                    </div>`;
            }

            const created = t.created_at ? new Date(t.created_at.replace(' ', 'T')).toLocaleString() : '—';
            const completed = t.completed_at ? new Date(t.completed_at.replace(' ', 'T')).toLocaleString() : '—';

            return `
                ${desc}
                ${subHtml}
                ${attHtml}
                <div class="expand-section">
                    <div class="expand-label">Timeline</div>
                    <div class="small text-muted">Created: ${esc(created)}</div>
                    <div class="small text-muted">Completed: ${esc(completed)}</div>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditFromRow(${t.id})">
                        <i class="fas fa-edit me-1"></i>Edit
                    </button>
                </div>
            `;
        }

        window.openEditFromRow = function(id) {
            const btn = document.querySelector(`.task-row[data-id="${id}"] .edit-btn`);
            if (btn) btn.click();
        };

        // ---------- Subtask toggle ----------
        document.addEventListener('change', e => {
            const cb = e.target.closest('.js-subtask-toggle');
            if (!cb) return;
            const subId = cb.dataset.subtaskId;
            const completed = cb.checked ? 1 : 0;
            const fd = new FormData();
            fd.append('action', 'toggle_subtask');
            fd.append('subtask_id', subId);
            fd.append('completed', completed);

            const label = cb.closest('.form-check').querySelector('label');
            if (label) {
                label.classList.toggle('text-decoration-line-through', !!completed);
                label.classList.toggle('text-muted', !!completed);
            }

            fetch(window.location.href, { method:'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        cb.checked = !cb.checked;
                        if (label) {
                            label.classList.toggle('text-decoration-line-through', !!cb.checked);
                            label.classList.toggle('text-muted', !!cb.checked);
                        }
                        showNotification('Failed to update subtask', 'error');
                        return;
                    }
                    // Update parent row's subtask counter + expansion summary/bar
                    const row = cb.closest('.task-row');
                    if (row && data.total != null) {
                        const counter = row.querySelector('.task-meta span[title="Subtasks"]');
                        if (counter) counter.innerHTML = `<i class="fas fa-tasks me-1"></i>${data.done}/${data.total}`;
                        const pct = data.total ? Math.round((data.done / data.total) * 100) : 0;
                        const summary = row.querySelector('.js-subtask-summary');
                        if (summary) summary.textContent = `${data.done}/${data.total} · ${pct}%`;
                        const bar = row.querySelector('.js-subtask-bar');
                        if (bar) bar.style.width = pct + '%';
                    }
                })
                .catch(() => {
                    cb.checked = !cb.checked;
                    showNotification('Network error', 'error');
                });
        });

        // ---------- Quick status (data-id + data-status) ----------
        document.addEventListener('click', e => {
            const trigger = e.target.closest('.js-quick-status');
            if (!trigger) return;
            e.preventDefault();
            e.stopPropagation();
            const id = trigger.dataset.id;
            const status = trigger.dataset.status;

            const fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('id', id);
            fd.append('status', status);
            fetch(window.location.href, { method:'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showNotification('Status updated', 'success');
                        setTimeout(() => location.reload(), 350);
                    } else {
                        showNotification(d.message || 'Failed', 'error');
                    }
                });
        });

        // ---------- Drag-to-reschedule (drop on group header) ----------
        let dragId = null;
        list.querySelectorAll('.task-row').forEach(bindRowDrag);
        list.querySelectorAll('.task-group-header').forEach(bindHeaderDrop);

        function bindRowDrag(row) {
            row.addEventListener('dragstart', e => {
                if (e.target.closest('input, button, a, select, .dropdown, .task-row-expand')) {
                    e.preventDefault(); return;
                }
                dragId = row.dataset.id;
                row.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', dragId);
            });
            row.addEventListener('dragend', () => {
                row.classList.remove('dragging');
                document.querySelectorAll('.task-group-header.drop-active').forEach(el => el.classList.remove('drop-active'));
                dragId = null;
            });
        }

        function bucketToDate(bucket) {
            const today = new Date(); today.setHours(0,0,0,0);
            const pad = n => String(n).padStart(2, '0');
            const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
            switch (bucket) {
                case 'overdue':  { const d = new Date(today); d.setDate(d.getDate()-1); return fmt(d); }
                case 'today':    return fmt(today);
                case 'tomorrow': { const d = new Date(today); d.setDate(d.getDate()+1); return fmt(d); }
                case 'week':     { const d = new Date(today); d.setDate(d.getDate()+3); return fmt(d); }
                case 'later':    { const d = new Date(today); d.setDate(d.getDate()+14); return fmt(d); }
                case 'no_date':  return ''; // clear due date
                case 'completed': return null; // not reschedulable
                default: return null;
            }
        }

        function bindHeaderDrop(header) {
            header.addEventListener('dragover', e => {
                if (header.dataset.droptarget === 'completed') return; // not a reschedule target
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                header.classList.add('drop-active');
            });
            header.addEventListener('dragleave', e => {
                if (!header.contains(e.relatedTarget)) header.classList.remove('drop-active');
            });
            header.addEventListener('drop', e => {
                e.preventDefault();
                header.classList.remove('drop-active');
                const id = e.dataTransfer.getData('text/plain') || dragId;
                const bucket = header.dataset.droptarget;
                const newDate = bucketToDate(bucket);
                if (!id || newDate === null) return;

                const fd = new FormData();
                fd.append('action', 'reschedule_task');
                fd.append('id', id);
                fd.append('new_date', newDate);
                fetch(window.location.href, { method:'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            showNotification('Rescheduled', 'success');
                            setTimeout(() => location.reload(), 350);
                        } else {
                            showNotification(d.message || 'Failed to reschedule', 'error');
                        }
                    });
            });
        }

        // ---------- Group collapse / expand ----------
        document.addEventListener('click', e => {
            const btn = e.target.closest('.js-collapse-group');
            if (!btn) return;
            e.stopPropagation();
            const group = btn.closest('.task-group');
            if (!group) return;
            const body = group.querySelector('.task-group-body');
            const icon = btn.querySelector('i');
            const collapsed = group.classList.toggle('collapsed');
            if (body) body.style.display = collapsed ? 'none' : '';
            if (icon) { icon.classList.toggle('fa-chevron-down', !collapsed); icon.classList.toggle('fa-chevron-right', collapsed); }
        });

        // ---------- Inline quick-add ----------
        const quickForm = document.getElementById('quickAddTaskForm');
        const quickTitle = document.getElementById('quickAddTitle');
        const quickMeta = quickForm?.querySelector('.quick-add-meta');

        if (quickTitle && quickMeta) {
            quickTitle.addEventListener('focus', () => {
                quickMeta.style.display = 'flex';
            });
            document.addEventListener('click', e => {
                if (!quickForm.contains(e.target) && quickTitle.value.trim() === '') {
                    quickMeta.style.display = 'none';
                }
            });
        }
        if (quickForm) {
            quickForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const title = quickTitle.value.trim();
                if (!title) return;
                const fd = new FormData();
                fd.append('action', 'quick_add_task');
                fd.append('title', title);
                fd.append('priority', document.getElementById('quickAddPriority').value || 'medium');
                fd.append('due_date', document.getElementById('quickAddDue').value || '');
                fd.append('category_id', document.getElementById('quickAddCategory').value || '');

                const btn = quickForm.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; btn.textContent = 'Adding...'; }

                fetch(window.location.href, { method:'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            showNotification('Task added', 'success');
                            setTimeout(() => location.reload(), 350);
                        } else {
                            showNotification(d.message || 'Failed', 'error');
                            if (btn) { btn.disabled = false; btn.textContent = 'Add'; }
                        }
                    })
                    .catch(() => {
                        showNotification('Network error', 'error');
                        if (btn) { btn.disabled = false; btn.textContent = 'Add'; }
                    });
            });
        }

        // ---------- Live search + filter ----------
        const searchInput = document.getElementById('listSearch');
        const priorityFlt = document.getElementById('listPriorityFilter');
        const categoryFlt = document.getElementById('listCategoryFilter');
        const statusFlt   = document.getElementById('listStatusFilter');

        function applyClientFilters() {
            const q = (searchInput?.value || '').trim().toLowerCase();
            const pri = priorityFlt?.value || '';
            const cat = categoryFlt?.value || '';

            document.querySelectorAll('.task-row').forEach(row => {
                const title = row.dataset.title || '';
                const desc  = row.dataset.description || '';
                const okSearch = !q || title.includes(q) || desc.includes(q);
                const okPri    = !pri || row.dataset.priority === pri;
                const okCat    = !cat || row.dataset.category === cat;
                row.style.display = (okSearch && okPri && okCat) ? '' : 'none';
            });
            // Hide empty groups, update counts
            document.querySelectorAll('.task-group').forEach(group => {
                const visible = group.querySelectorAll('.task-row:not([style*="display: none"])').length;
                group.style.display = visible > 0 ? '' : 'none';
                const cnt = group.querySelector('.task-group-count');
                if (cnt) cnt.textContent = visible;
            });
        }

        if (searchInput) {
            let t;
            searchInput.addEventListener('input', () => { clearTimeout(t); t = setTimeout(applyClientFilters, 150); });
        }
        if (priorityFlt) priorityFlt.addEventListener('change', applyClientFilters);
        if (categoryFlt) categoryFlt.addEventListener('change', applyClientFilters);
        // Status filter: reload (status is in SQL because we group differently for completed)
        if (statusFlt) {
            statusFlt.addEventListener('change', function() {
                const params = new URLSearchParams(window.location.search);
                if (this.value) params.set('status', this.value); else params.delete('status');
                window.location.search = params.toString();
            });
        }
    }
</script>

<style>
    /* ============================================================
       Clean List Styles
       ============================================================ */

    /* Stat pills */
    .todo-stat-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 0.9rem;
        border-radius: 999px;
        border: 1px solid var(--bs-border-color);
        background: var(--bs-body-bg);
        color: var(--bs-body-color);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.15s ease;
    }
    .todo-stat-pill:hover {
        background: var(--bs-body-tertiary-bg);
        transform: translateY(-1px);
        text-decoration: none;
    }
    .todo-stat-pill.active { border-width: 2px; font-weight: 600; }
    .todo-stat-pill .badge { font-size: 0.7rem; padding: 0.15em 0.55em; font-weight: 600; }

    /* Task list container */
    .task-list-container {
        background: var(--bs-body-bg);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        border: 1px solid var(--bs-border-color);
    }

    /* Group header */
    .task-group { }
    .task-group-header {
        position: sticky;
        top: 0;
        z-index: 4;
        background: var(--bs-body-tertiary-bg);
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        border-bottom: 1px solid var(--bs-border-color);
        transition: background-color 0.15s ease;
    }
    .task-group-header.drop-active {
        background: var(--bs-primary-bg-subtle);
        outline: 2px dashed var(--bs-primary);
        outline-offset: -4px;
    }
    .task-group.collapsed .task-group-header { border-bottom-color: transparent; }
    .task-group-body { background: var(--bs-body-bg); }

    /* Task row */
    .task-row {
        display: flex;
        flex-direction: column;
        padding: 0;
        border-bottom: 1px solid var(--bs-border-color);
        transition: background-color 0.12s ease;
        position: relative;
        user-select: none;
    }
    .task-row:last-child { border-bottom: 0; }
    .task-row:hover { background: var(--bs-body-tertiary-bg); }
    .task-row.is-completed { opacity: 0.7; }
    .task-row.dragging { opacity: 0.45; }
    .task-row.expanded { background: var(--bs-body-tertiary-bg); }

    .task-row-main {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.7rem 1rem;
        cursor: pointer;
    }

    /* Drag handle: hidden until row hover */
    .task-drag-handle {
        opacity: 0;
        color: var(--bs-secondary-color);
        cursor: grab;
        font-size: 0.85rem;
        padding: 0 0.15rem;
        flex-shrink: 0;
        transition: opacity 0.15s ease;
    }
    .task-row:hover .task-drag-handle { opacity: 0.5; }
    .task-drag-handle:hover { opacity: 1 !important; }
    .task-row.dragging .task-drag-handle { cursor: grabbing; }

    /* Complete button (circle → checkmark) */
    .task-complete-btn {
        background: none;
        border: none;
        padding: 0;
        line-height: 1;
        flex-shrink: 0;
        font-size: 1.15rem;
        cursor: pointer;
    }
    .task-complete-btn:hover i.fa-circle { color: var(--bs-success) !important; }

    /* Priority dot */
    .task-priority-dot {
        display: inline-block;
        width: 6px;
        height: 26px;
        border-radius: 3px;
        flex-shrink: 0;
    }

    /* Title + meta */
    .task-content { min-width: 0; }
    .task-title {
        font-size: 0.95rem;
        font-weight: 500;
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .task-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.6rem;
        font-size: 0.78rem;
        margin-top: 0.15rem;
    }
    .task-category-chip {
        color: #fff;
        font-size: 0.7rem;
        padding: 0.1em 0.55em;
        border-radius: 999px;
        font-weight: 500;
    }

    /* Status pill */
    .task-status-pill {
        font-size: 0.7rem;
        padding: 0.2em 0.7em;
        border-radius: 999px;
        font-weight: 500;
        text-transform: capitalize;
        background: var(--bs-secondary-bg);
        color: var(--bs-secondary-color);
    }
    .task-status-pill.status-pending     { background: rgba(13,202,240,0.15);  color: #0dcaf0; }
    .task-status-pill.status-in_progress { background: rgba(255,193,7,0.18);   color: #d39e00; }
    .task-status-pill.status-completed   { background: rgba(25,135,84,0.15);   color: #198754; }

    /* Inline expand */
    .task-row-expand {
        padding: 0.85rem 1.25rem 1rem 3.5rem;
        background: var(--bs-body-tertiary-bg);
        border-top: 1px solid var(--bs-border-color);
        font-size: 0.85rem;
    }
    .expand-section { margin-bottom: 0.85rem; }
    .expand-section:last-child { margin-bottom: 0; }
    .expand-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--bs-secondary-color);
        font-weight: 600;
        margin-bottom: 0.4rem;
    }

    .min-w-0 { min-width: 0; }

    /* Mobile */
    @media (max-width: 575.98px) {
        .task-meta { gap: 0.4rem; font-size: 0.72rem; }
        .task-status-pill { display: none; }  /* save row space */
        .task-row-main { padding: 0.65rem 0.75rem; gap: 0.45rem; }
        .task-row-expand { padding-left: 1rem; }
    }
</style>
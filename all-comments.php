<?php include "head.php";?>
    <title>All Task Comments| iTasker</title>
<?php include "navi.php";?>

<?php
$aid = $_SESSION['sessionWriter'];

// Get writer details
$sql = 'SELECT * FROM tblwriters WHERE email=:aid';
$query = $dbh->prepare($sql);
$query->bindParam(':aid', $aid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);

if ($query->rowCount() == 0 || $results[0]->is_verified != 1) {
    header('location:logout.php');
    exit();
}

// Pagination settings
$limit = 10; // Comments per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter settings
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$task_filter = isset($_GET['task']) ? (int)$_GET['task'] : 0;

// Build WHERE clause based on filters
$whereClause = "WHERE t.email = '$aid'";
if ($filter === 'unread') {
    $whereClause .= " AND tc.is_read = 0 AND tc.user_type = 'admin'";
} elseif ($filter === 'admin') {
    $whereClause .= " AND tc.user_type = 'admin'";
} elseif ($filter === 'my') {
    $whereClause .= " AND tc.user_type = 'writer'";
}

if ($task_filter > 0) {
    $whereClause .= " AND tc.task_id = $task_filter";
}

// Get total count for pagination
$countQuery = mysqli_query($con, "
    SELECT COUNT(*) as total 
    FROM tbl_task_comments tc 
    JOIN tbltasks t ON tc.task_id = t.id 
    $whereClause
");
$totalResult = mysqli_fetch_assoc($countQuery);
$totalComments = $totalResult['total'];
$totalPages = ceil($totalComments / $limit);

// Get comments with pagination
$commentsQuery = mysqli_query($con, "
    SELECT tc.*, t.topic, t.id as task_id, t.status as task_status
    FROM tbl_task_comments tc 
    JOIN tbltasks t ON tc.task_id = t.id 
    $whereClause
    ORDER BY tc.created_at DESC 
    LIMIT $limit OFFSET $offset
");

$comments = [];
while ($comment = mysqli_fetch_assoc($commentsQuery)) {
    // Calculate time ago
    $commentTime = new DateTime($comment['created_at']);
    $now = new DateTime();
    $interval = $now->diff($commentTime);

    if ($interval->y > 0) {
        $timeAgo = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    } elseif ($interval->m > 0) {
        $timeAgo = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    } elseif ($interval->d > 0) {
        $timeAgo = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
    } elseif ($interval->h > 0) {
        $timeAgo = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    } elseif ($interval->i > 0) {
        $timeAgo = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    } else {
        $timeAgo = 'Just now';
    }

    $comment['time_ago'] = $timeAgo;
    $comments[] = $comment;
}

// Get tasks for filter dropdown
$tasksQuery = mysqli_query($con, "SELECT id, topic FROM tbltasks WHERE email = '$aid' AND is_deleted = 0 ORDER BY create_date DESC");
$tasks = [];
while ($task = mysqli_fetch_assoc($tasksQuery)) {
    $tasks[] = $task;
}

?>

    <div class='content'>
        <div class="container-fluid px-0">
            <div class="row">
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-header bg-body-tertiary">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="mb-0">
                                        <i class="fas fa-comments me-2 text-primary"></i>
                                        All Comments
                                    </h5>
                                    <p class="mb-0 text-muted fs-10">
                                        Total: <?php echo $totalComments; ?> comments
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="card-body border-bottom">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fs-10">Filter by Type</label>
                                    <select name="filter" class="form-select form-select-sm">
                                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All
                                            Comments
                                        </option>
                                        <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>
                                            Unread Only
                                        </option>
                                        <option value="admin" <?php echo $filter === 'admin' ? 'selected' : ''; ?>>Admin
                                            Comments
                                        </option>
                                        <option value="my" <?php echo $filter === 'my' ? 'selected' : ''; ?>>My
                                            Comments
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs-10">Filter by Task</label>
                                    <select name="task" class="form-select form-select-sm">
                                        <option value="0">All Tasks</option>
                                        <?php foreach ($tasks as $task): ?>
                                            <option value="<?php echo $task['id']; ?>" <?php echo $task_filter == $task['id'] ? 'selected' : ''; ?>>
                                                Task <?php echo $task['id']; ?>
                                                : <?php echo htmlspecialchars(substr($task['topic'], 0, 40)) . (strlen($task['topic']) > 40 ? '...' : ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fs-10">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-sm d-block">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-10">&nbsp;</label>
                                    <a href="all-comments.php" class="btn btn-outline-secondary btn-sm d-block">
                                        <i class="fas fa-times me-1"></i>Clear Filters
                                    </a>
                                </div>
                            </form>
                        </div>

                        <div class="card-body">
                            <?php if (empty($comments)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No comments found</h5>
                                    <p class="text-muted">There are no comments matching your current filters.</p>
                                </div>
                            <?php else: ?>
                                <div class='row'>
                                    <?php foreach ($comments as $comment): ?>
                                        <div class='col-sm-2 col-md-2 mb-3'>
                                            <div class='position-relative h-sm-100'>
                                                <div class='avatar avatar-2xl w-100 h-100 object-fit-cover rounded-5 absolute-sm-centered d-flex align-items-center justify-content-center <?php echo $comment['user_type'] === 'admin' ? 'bg-body-secondary' : 'bg-body-tertiary'; ?>'>
                                                    <span class='fs-10'>
                                                        <?php echo strtoupper(substr($comment['username'], 0)); ?>
                                                    </span>
                                                </div>
                                                <?php if ($comment['is_read'] == 0 && $comment['user_type'] === 'admin'): ?>
                                                    <div class='badge rounded-pill bg-danger position-absolute top-0 end-0 me-2 mt-2 fs-11 z-2'>
                                                        UNREAD
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class='col-sm-10 col-md-10'>
                                            <div class='row'>
                                                <div class='col-lg-8'>
                                                    <h5 class='mt-3 mt-sm-0'>
                                                        <?php $encodedTaskId = base64_encode($comment['task_id']); ?>
                                                        <a class='text-1100 fs-10 fs-lg-10' href="view-task?task_id=<?php echo htmlspecialchars($encodedTaskId); ?>">
                                                            <i class='fas fa-tasks me-1'></i> Task <?php echo $comment['task_id']; ?>
                                                            <span><?php echo htmlspecialchars(substr($comment['topic'], 0, 50)) . (strlen($comment['topic']) > 50 ? '...' : ''); ?></span>
                                                        </a>
                                                    </h5>
                                                    <p class='fs-10 mb-2 mb-md-3'>
                                                        <span class='text-primary-emphasis'>
                                                            <?php
                                                            $unescaped_comment = stripcslashes($comment['comment']);
                                                            echo nl2br(htmlspecialchars($unescaped_comment));
                                                            ?>
                                                        </span>
                                                    </p>
                                                </div>
                                                <div class='col-lg-4 d-flex justify-content-between flex-column'>
                                                    <span class='fs-10 mb-0'><?php echo $comment['time_ago']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <hr class='text-200'/>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                    <nav aria-label="Comments pagination">
                                        <ul class="pagination justify-content-center">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link"
                                                       href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>&task=<?php echo $task_filter; ?>">
                                                        <i class="fas fa-chevron-left"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link"
                                                       href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&task=<?php echo $task_filter; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link"
                                                       href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>&task=<?php echo $task_filter; ?>">
                                                        <i class="fas fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>

                                    <div class="text-center text-muted fs-10">
                                        Showing <?php echo($offset + 1); ?>
                                        to <?php echo min($offset + $limit, $totalComments); ?>
                                        of <?php echo $totalComments; ?> comments
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
include "footer.php";
?>
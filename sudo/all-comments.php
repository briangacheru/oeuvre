<?php include 'head.php'; ?>
    <title>All Task Comments| iTasker</title>
<?php include 'navi.php'; ?>

<?php
$aid = $_SESSION['odmsaid'];

// Get admin details and verify permissions
$sql = 'SELECT * FROM tbladmin WHERE email=:aid';
$query = $dbh->prepare($sql);
$query->bindParam(':aid', $aid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);

if ($query->rowCount() == 0 || $results[0]->AdminName != 'Admin') {
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
$writer_filter = isset($_GET['writer']) ? $_GET['writer'] : '';

// Build WHERE clause based on filters
$whereClause = 'WHERE 1=1';
if ($filter === 'unread') {
    $whereClause .= " AND tc.is_read = 0 AND tc.user_type = 'writer'";
} elseif ($filter === 'writer') {
    $whereClause .= " AND tc.user_type = 'writer'";
} elseif ($filter === 'admin') {
    $whereClause .= " AND tc.user_type = 'admin'";
}

if ($task_filter > 0) {
    $whereClause .= " AND tc.task_id = $task_filter";
}

if (!empty($writer_filter)) {
    $whereClause .= " AND t.email = '" . mysqli_real_escape_string($con, $writer_filter) . "'";
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
    SELECT tc.*, t.topic, t.id as task_id, t.status as task_status, t.email as writer_email, t.writer as writer_name
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
$tasksQuery = mysqli_query($con, 'SELECT id, topic FROM tbltasks WHERE is_deleted = 0 ORDER BY create_date DESC LIMIT 100');
$tasks = [];
while ($task = mysqli_fetch_assoc($tasksQuery)) {
    $tasks[] = $task;
}

// Get writers for filter dropdown
$writersQuery = mysqli_query($con, 'SELECT DISTINCT email, writer FROM tbltasks WHERE is_deleted = 0 ORDER BY writer ASC');
$writers = [];
while ($writer = mysqli_fetch_assoc($writersQuery)) {
    $writers[] = $writer;
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
                                        All Task Comments
                                    </h5>
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Filters with Mark as Read Options -->
                        <div class='card-body border-bottom'>
                            <div class='row align-items-end'>
                                <div class='col-md-8'>
                                    <form method='GET' class='row g-3'>
                                        <div class='col-md-3'>
                                            <label class='form-label fs-10'>Filter by Type</label>
                                            <select name='filter' class='form-select form-select-sm'>
                                                <option value='all' <?php echo $filter === 'all' ? 'selected' : ''; ?>>
                                                    All Comments
                                                </option>
                                                <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>
                                                    Unread Only
                                                </option>
                                                <option value="writer" <?php echo $filter === 'writer' ? 'selected' : ''; ?>>
                                                    Writer Comments
                                                </option>
                                                <option value="admin" <?php echo $filter === 'admin' ? 'selected' : ''; ?>>
                                                    Admin Comments
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fs-10">Filter by Writer</label>
                                            <select name="writer" class="form-select form-select-sm">
                                                <option value="">All Writers</option>
                                                <?php foreach ($writers as $writer): ?>
                                                    <option value="<?php echo htmlspecialchars($writer['email']); ?>" <?php echo $writer_filter == $writer['email'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($writer['writer']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fs-10">Filter by Task</label>
                                            <select name="task" class="form-select form-select-sm">
                                                <option value="0">All Tasks</option>
                                                <?php foreach ($tasks as $task): ?>
                                                    <option value="<?php echo $task['id']; ?>" <?php echo $task_filter == $task['id'] ? 'selected' : ''; ?>>
                                                        Task <?php echo $task['id']; ?>
                                                        : <?php echo htmlspecialchars(substr($task['topic'], 0, 30)) . (strlen($task['topic']) > 30 ? '...' : ''); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label fs-10">&nbsp;</label>
                                            <button type="submit" class="btn btn-primary btn-sm d-block" data-bs-toggle='tooltip' data-bs-placement='top' title='Filter'>
                                                <i class="fas fa-filter me-1"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <?php
                                        // Count unread comments for current filter
                                        $unreadCountQuery = mysqli_query($con, "
                    SELECT COUNT(*) as unread_count 
                    FROM tbl_task_comments tc 
                    JOIN tbltasks t ON tc.task_id = t.id 
                    WHERE tc.user_type = 'writer' AND tc.is_read = 0
                    " . ($task_filter > 0 ? " AND tc.task_id = $task_filter" : '') . '
                    ' . (!empty($writer_filter) ? " AND t.email = '" . mysqli_real_escape_string($con, $writer_filter) . "'" : '')
                                        );
                                        $unreadCountResult = mysqli_fetch_assoc($unreadCountQuery);
                                        $unreadCount = $unreadCountResult['unread_count'];
                                        ?>

                                        <?php if ($unreadCount > 0): ?>
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle='tooltip' data-bs-placement='top' title='Mark selected as read'
                                                    onclick="markSelectedAsRead()">
                                                <i class="fas fa-check me-1"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle='tooltip' data-bs-placement='top' title='Mark Page as Read (<?php echo min($unreadCount, $limit); ?>)'
                                                    onclick="markAllVisibleAsRead()">
                                                <i class="fas fa-eye me-1"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle='tooltip' data-bs-placement='top' title='Mark All as Read (<?php echo $unreadCount; ?>)'
                                                    onclick="markAllFilteredAsRead()">
                                                <i class="fas fa-check-double me-1"></i>
                                            </button>
                                        <?php endif; ?>

                                        <a href="all-comments.php" class="btn btn-outline-secondary btn-sm" data-bs-toggle='tooltip' data-bs-placement='top' title='Clear Filters'>
                                            <i class="fas fa-times me-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <?php if (empty($comments)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No comments found</h5>
                                    <p class="text-muted">There are no comments matching your current filters.</p>
                                </div>
                            <?php else: ?>
                                <?php
                                // Count unread writer comments on current page
                                $unreadOnPage = 0;
                                foreach ($comments as $comment) {
                                    if ($comment['is_read'] == 0 && $comment['user_type'] === 'writer') {
                                        $unreadOnPage++;
                                    }
                                }
                                ?>

                                <?php if ($unreadOnPage > 0): ?>
                                    <div class='d-flex justify-content-between align-items-center mb-3'>
                                        <div class='form-check'>
                                            <input class='form-check-input' type='checkbox' id='selectAll'
                                                   onchange='toggleSelectAll()'>
                                            <label class='form-check-label fw-bold' for='selectAll'>
                                                Select All Unread Comments (<?php echo $unreadOnPage; ?>)
                                            </label>
                                        </div>
                                        <div class='text-muted fs-10'>
                                            <span id='selectedCount'>0</span> comments selected
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <div class='row'>
                                    <?php foreach ($comments as $comment): ?>
                                        <div class='col-sm-2 col-md-2 mb-3'>
                                            <div class='position-relative h-sm-100'>
                                                <!-- Add checkbox for unread writer comments -->
                                                <?php if ($comment['is_read'] == 0 && $comment['user_type'] === 'writer'): ?>
                                                    <div class="me-2">
                                                        <input class="form-check-input comment-checkbox"
                                                               type="checkbox"
                                                               value="<?php echo $comment['id']; ?>"
                                                               onchange="updateSelectedCount()">
                                                    </div>
                                                <?php endif; ?>
                                                <div class='avatar avatar-2xl w-50 h-50 object-fit-cover rounded-5 absolute-sm-centered d-flex align-items-center justify-content-center <?php echo $comment['user_type'] === 'writer' ? 'bg-body-secondary' : 'bg-body-tertiary'; ?>'>
                                                    <span class='fs-10'>
                                                        <?php echo strtoupper(substr($comment['username'], 0)); ?>
                                                    </span>
                                                </div>
                                                <?php if ($comment['is_read'] == 0 && $comment['user_type'] === 'writer'): ?>
                                                    <span class='badge rounded-pill bg-danger '> Unread</span>
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
                                                        <span class="badge task-status-badge <?php echo $comment['task_status'] === 'Completed' ? 'badge-subtle-success' :
                                                        ($comment['task_status'] === 'In Progress' ? 'badge-subtle-warning' : ($comment['task_status'] === 'Submitted' ? 'badge-subtle-info' : 'badge-subtle-secondary'));?>">
                                                        <?php echo $comment['task_status']; ?>
                                                        </span>
                                                    </p>
                                                </div>
                                                <div class='col-lg-4 d-flex justify-content-between flex-column'>
                                                    <span class='fs-10 mb-0'><?php echo $comment['time_ago']; ?></span>
                                                    <div class='mt-2'>
                                                        <?php if ($comment['is_read'] == 0 && $comment['user_type'] === 'writer'): ?>
                                                            <button type='button'
                                                                    class='btn btn-sm btn-primary d-lg-block mt-lg-2 mb-2'
                                                                    data-bs-toggle='tooltip' data-bs-placement='top'
                                                                    title='Mark as Read'
                                                                    onclick="markSingleAsRead(<?php echo $comment['id']; ?>)">
                                                                <span class='fas fa-eye fs-11'> </span> <span
                                                                        class='ms-2 d-none d-md-inline-block'>Mark as Read</span>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
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
                                                       href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>&task=<?php echo $task_filter; ?>&writer=<?php echo urlencode($writer_filter); ?>">
                                                        <i class="fas fa-chevron-left"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link"
                                                       href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&task=<?php echo $task_filter; ?>&writer=<?php echo urlencode($writer_filter); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link"
                                                       href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>&task=<?php echo $task_filter; ?>&writer=<?php echo urlencode($writer_filter); ?>">
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

    <script>
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const commentCheckboxes = document.querySelectorAll('.comment-checkbox');

            commentCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });

            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selectedCheckboxes = document.querySelectorAll('.comment-checkbox:checked');
            const count = selectedCheckboxes.length;
            document.getElementById('selectedCount').textContent = count;

            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.comment-checkbox');
            const selectAllCheckbox = document.getElementById('selectAll');

            if (count === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (count === allCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
        }

        function markSelectedAsRead() {
            const selectedCheckboxes = document.querySelectorAll('.comment-checkbox:checked');
            const commentIds = Array.from(selectedCheckboxes).map(cb => cb.value);

            if (commentIds.length === 0) {
                alert('Please select comments to mark as read.');
                return;
            }

            if (confirm(`Mark ${commentIds.length} selected comments as read?`)) {
                markCommentsAsRead(commentIds, 'selected');
            }
        }

        function markAllVisibleAsRead() {
            const allCheckboxes = document.querySelectorAll('.comment-checkbox');
            const commentIds = Array.from(allCheckboxes).map(cb => cb.value);

            if (commentIds.length === 0) {
                alert('No unread comments on this page.');
                return;
            }

            if (confirm(`Mark all ${commentIds.length} comments on this page as read?`)) {
                markCommentsAsRead(commentIds, 'page');
            }
        }

        function markAllFilteredAsRead() {
            if (confirm('Mark ALL unread comments matching current filters as read? This action cannot be undone.')) {
                markCommentsAsRead([], 'all_filtered');
            }
        }

        function markSingleAsRead(commentId) {
            markCommentsAsRead([commentId], 'single');
        }

        function markCommentsAsRead(commentIds, action) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('comment_ids', JSON.stringify(commentIds));

            // Add current filter parameters for 'all_filtered' action
            if (action === 'all_filtered') {
                formData.append('filter', '<?php echo $filter; ?>');
                formData.append('task_filter', '<?php echo $task_filter; ?>');
                formData.append('writer_filter', '<?php echo $writer_filter; ?>');
            }

            fetch('mark-comments-read', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (action === 'single') {
                            // Just reload the page for single comment
                            location.reload();
                        } else {
                            // Show success message and reload
                            alert(`Successfully marked ${data.count} comments as read.`);
                            location.reload();
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Failed to mark comments as read.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while marking comments as read.');
                });
        }

        // Initialize selected count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
        });
    </script>

<?php
include 'footer.php';
?>
<?php
include_once('head.php');
requireCapability($currentAdminRole, 'operate_tasks');
csrf_verify_or_redirect();

// Handle level updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_level') {
        $levelNumber = intval($_POST['level_number']);
        $levelName = trim($_POST['level_name']);
        $levelDescription = trim($_POST['level_description']);

        // Format icon class
        $iconClassInput = trim($_POST['icon_class']);
        if (!empty($iconClassInput)) {
            $iconClass = $iconClassInput;
            if (strpos($iconClass, 'fas ') === 0) {
                $iconClass = substr($iconClass, 4);
            }
            if (strpos($iconClass, 'fa-') !== 0) {
                $iconClass = 'fa-' . $iconClass;
            }
            $iconClass = 'fas ' . $iconClass;
        } else {
            $iconClass = 'fas fa-star';
        }

        $iconColor = trim($_POST['icon_color']);
        $minTasks = intval($_POST['min_completed_tasks']);
        $maxTasks = $_POST['max_completed_tasks'] ? intval($_POST['max_completed_tasks']) : null;

        // Check if level number already exists
        $checkQuery = "SELECT id FROM tbl_writer_levels WHERE level_number = ?";
        $checkStmt = $con->prepare($checkQuery);
        $checkStmt->bind_param("i", $levelNumber);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $errorMessage = "Level number {$levelNumber} already exists!";
        } else {
            $insertQuery = "INSERT INTO tbl_writer_levels 
                           (level_number, level_name, level_description, icon_class, icon_color, min_completed_tasks, max_completed_tasks) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $con->prepare($insertQuery);
            $stmt->bind_param("issssii", $levelNumber, $levelName, $levelDescription, $iconClass, $iconColor, $minTasks, $maxTasks);

            if ($stmt->execute()) {
                $successMessage = "New level added successfully!";
            } else {
                $errorMessage = "Failed to add level: " . $stmt->error;
            }
            $stmt->close();
        }
        $checkStmt->close();
    }

    if ($_POST['action'] == 'update_level') {
        $levelId = intval($_POST['level_id']);
        $levelName = trim($_POST['level_name']);
        $levelDescription = trim($_POST['level_description']);

        // Fix: Properly format icon class
        $iconClassInput = trim($_POST['icon_class']);
        if (!empty($iconClassInput)) {
            // Remove any existing prefixes
            $iconClass = $iconClassInput;
            if (strpos($iconClass, 'fas ') === 0) {
                $iconClass = substr($iconClass, 4);
            }
            if (strpos($iconClass, 'fa-') !== 0) {
                $iconClass = 'fa-' . $iconClass;
            }
            // Add the full class with prefix
            $iconClass = 'fas ' . $iconClass;
        } else {
            $iconClass = 'fas fa-star'; // Default fallback
        }

        $iconColor = trim($_POST['icon_color']);
        $minTasks = intval($_POST['min_completed_tasks']);
        $maxTasks = $_POST['max_completed_tasks'] ? intval($_POST['max_completed_tasks']) : null;

        $updateQuery = "UPDATE tbl_writer_levels SET 
                        level_name = ?, level_description = ?, icon_class = ?, 
                        icon_color = ?, min_completed_tasks = ?, max_completed_tasks = ?
                        WHERE id = ?";
        $stmt = $con->prepare($updateQuery);
        $stmt->bind_param("ssssiis", $levelName, $levelDescription, $iconClass, $iconColor, $minTasks, $maxTasks, $levelId);

        if ($stmt->execute()) {
            $successMessage = "Level updated successfully!";
        } else {
            $errorMessage = "Failed to update level: " . $stmt->error;
        }
        $stmt->close();
    }

    if ($_POST['action'] == 'delete_level') {
        $levelId = intval($_POST['level_id']);

        $levelStmt = $con->prepare("SELECT level_number, level_name FROM tbl_writer_levels WHERE id = ?");
        $levelStmt->bind_param("i", $levelId);
        $levelStmt->execute();
        $levelToDelete = $levelStmt->get_result()->fetch_assoc();
        $levelStmt->close();

        if (!$levelToDelete) {
            $errorMessage = "Level not found.";
        } else {
            // Block deletion while any active writer is currently sitting at
            // this level - deleting it out from under them would leave their
            // profile/level badge referencing a level that no longer exists.
            $countStmt = $con->prepare("SELECT COUNT(*) as active_count
                                         FROM tbl_writer_performance wp
                                         JOIN tblwriters w ON w.id = wp.writer_id
                                         WHERE wp.current_level = ? AND w.is_active = 1");
            $countStmt->bind_param("i", $levelToDelete['level_number']);
            $countStmt->execute();
            $activeCount = $countStmt->get_result()->fetch_assoc()['active_count'];
            $countStmt->close();

            if ($activeCount > 0) {
                $errorMessage = "Can't delete \"{$levelToDelete['level_name']}\" - $activeCount active writer" . ($activeCount == 1 ? ' is' : 's are') . " currently at this level.";
            } else {
                $deleteStmt = $con->prepare("DELETE FROM tbl_writer_levels WHERE id = ?");
                $deleteStmt->bind_param("i", $levelId);
                if ($deleteStmt->execute()) {
                    $successMessage = "Level \"{$levelToDelete['level_name']}\" deleted successfully!";
                } else {
                    $errorMessage = "Failed to delete level: " . $deleteStmt->error;
                }
                $deleteStmt->close();
            }
        }
    }
}

// Get all levels
$levelsQuery = "SELECT * FROM tbl_writer_levels ORDER BY level_number ASC";
$levelsResult = mysqli_query($con, $levelsQuery);
?>

    <title>iTasker | Manage Writer Levels</title>
<?php include "navi.php"; ?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Manage <span class="text-info fw-medium">Writer Levels</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLevelModal">
                        <i class="fas fa-plus me-1"></i>Add New Level
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $successMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $errorMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Writer Level Configuration</h5>
            <p class="mb-0 text-muted">Configure writer levels based on completed tasks. Each level should have unique task ranges.</p>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-primary">
                    <tr>
                        <th>Level</th>
                        <th>Name</th>
                        <th>Icon</th>
                        <th>Task Range</th>
                        <th>Description</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($level = mysqli_fetch_assoc($levelsResult)): ?>
                        <tr class="level-row" role="button" tabindex="0"
                            data-level='<?php echo htmlspecialchars(json_encode($level)); ?>'
                            title="Click to edit this level">
                            <td>
                                <span class="badge bg-primary fs-6"><?php echo $level['level_number']; ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($level['level_name']); ?></strong>
                            </td>
                            <td>
                                <i class="<?php echo htmlspecialchars($level['icon_class']); ?> fa-2x" style="color: <?php echo htmlspecialchars($level['icon_color']); ?>;"></i>
                            </td>
                            <td>
                                <?php echo $level['min_completed_tasks']; ?> -
                                <?php echo $level['max_completed_tasks'] ? $level['max_completed_tasks'] : '∞'; ?> tasks
                            </td>
                            <td class="text-muted">
                                <?php echo htmlspecialchars($level['level_description']); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Current Writers by Level -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Writers by Level</h5>
        </div>
        <div class="card-body">
            <?php
            // Only active writers count toward each level's total - a deactivated
            // writer's last-known level is still in tbl_writer_performance, but
            // they're not really "at" that level anymore for staffing purposes.
            $writersByLevelQuery = "SELECT
            wl.level_number, wl.level_name, wl.icon_class, wl.icon_color,
            wl.min_completed_tasks, wl.max_completed_tasks,
            COUNT(w.id) as writer_count,
            AVG(CASE WHEN w.id IS NOT NULL THEN wp.completion_rate END) as avg_completion_rate,
            AVG(CASE WHEN w.id IS NOT NULL THEN wp.on_time_rate END) as avg_on_time_rate
            FROM tbl_writer_levels wl
            LEFT JOIN tbl_writer_performance wp ON wl.level_number = wp.current_level
            LEFT JOIN tblwriters w ON w.id = wp.writer_id AND w.is_active = 1
            GROUP BY wl.level_number
            ORDER BY wl.level_number";
            $writersByLevel = mysqli_query($con, $writersByLevelQuery);
            ?>

            <div class="row g-3">
                <?php while ($levelStats = mysqli_fetch_assoc($writersByLevel)): ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="card border-0 bg-body-tertiary h-100 level-stat-card<?php echo $levelStats['writer_count'] > 0 ? '' : ' opacity-75'; ?>"
                             role="button" tabindex="0"
                             data-level-number="<?php echo (int) $levelStats['level_number']; ?>"
                             data-level-name="<?php echo htmlspecialchars($levelStats['level_name'], ENT_QUOTES); ?>"
                             data-level-range="<?php echo $levelStats['min_completed_tasks']; ?> - <?php echo $levelStats['max_completed_tasks'] ? $levelStats['max_completed_tasks'] : '&infin;'; ?> tasks"
                             title="<?php echo $levelStats['writer_count'] > 0 ? 'Click to see writers at this level' : 'No active writers at this level'; ?>">
                            <div class="card-body text-center">
                                <i class="<?php echo htmlspecialchars($levelStats['icon_class']); ?> fa-3x mb-3" style="color: <?php echo htmlspecialchars($levelStats['icon_color']); ?>;"></i>
                                <h6 class="mb-2"><?php echo htmlspecialchars($levelStats['level_name']); ?></h6>
                                <h4 class="text-primary mb-1"><?php echo $levelStats['writer_count']; ?></h4>
                                <small class="text-muted">Active Writers</small>
                                <?php if ($levelStats['writer_count'] > 0): ?>
                                    <div class="mt-2">
                                        <small class="text-success d-block">Avg Completion: <?php echo round($levelStats['avg_completion_rate'], 1); ?>%</small>
                                        <small class="text-info d-block">Avg On-Time: <?php echo round($levelStats['avg_on_time_rate'], 1); ?>%</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Add New Level Modal -->
    <div class="modal fade" id="addLevelModal" tabindex="-1" aria-labelledby="addLevelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLevelModalLabel">Add New Writer Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
<?= csrf_field() ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_level">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_level_number" class="form-label">Level Number</label>
                                    <input type="number" class="form-control" name="level_number" id="add_level_number" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_level_name" class="form-label">Level Name</label>
                                    <input type="text" class="form-control" name="level_name" id="add_level_name" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_icon_class" class="form-label">Icon Class</label>
                                    <div class="input-group">
                                        <span class="input-group-text">fa-</span>
                                        <input type="text" class="form-control" name="icon_class" id="add_icon_class"
                                               placeholder="star" required>
                                    </div>
                                    <small class="text-muted">
                                        Enter icon name without 'fa-' prefix (e.g., 'star', 'crown', 'gem')<br>
                                        <a href="https://fontawesome.com/icons" target="_blank" class="text-primary">
                                            <i class="fas fa-external-link-alt me-1"></i>Browse Font Awesome icons
                                        </a>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_icon_color" class="form-label">Icon Color</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" name="icon_color" id="add_icon_color" value="#ffc107" required>
                                        <input type="text" class="form-control" id="add_icon_color_text" placeholder="#ffc107" value="#ffc107">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_min_tasks" class="form-label">Minimum Completed Tasks</label>
                                    <input type="number" class="form-control" name="min_completed_tasks" id="add_min_tasks" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_max_tasks" class="form-label">Maximum Completed Tasks</label>
                                    <input type="number" class="form-control" name="max_completed_tasks" id="add_max_tasks" min="0">
                                    <small class="text-muted">Leave empty for unlimited</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="add_level_description" class="form-label">Description</label>
                            <textarea class="form-control" name="level_description" id="add_level_description" rows="3"></textarea>
                        </div>

                        <!-- Preview -->
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6>Preview</h6>
                                <span id="add_preview_icon_wrap" class="d-inline-block mb-2"><i id="add_preview_icon" class="fas fa-star fa-3x" style="color: #ffc107;"></i></span>
                                <h5 id="add_preview_name">Level Name</h5>
                                <p id="add_preview_description" class="text-muted mb-0">Description</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Level Modal -->
    <div class="modal fade" id="editLevelModal" tabindex="-1" aria-labelledby="editLevelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLevelModalLabel">Edit Writer Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
<?= csrf_field() ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_level">
                        <input type="hidden" name="level_id" id="edit_level_id">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_level_name" class="form-label">Level Name</label>
                                    <input type="text" class="form-control" name="level_name" id="edit_level_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_icon_class" class="form-label">Icon Class</label>
                                    <div class="input-group">
                                        <span class="input-group-text">fa-</span>
                                        <input type="text" class="form-control" name="icon_class" id="edit_icon_class"
                                               placeholder="star" required>
                                    </div>
                                    <small class="text-muted">
                                        Enter icon name without 'fa-' prefix (e.g., 'star', 'crown', 'gem')<br>
                                        <a href="https://fontawesome.com/icons" target="_blank" class="text-primary">
                                            <i class="fas fa-external-link-alt me-1"></i>Browse Font Awesome icons
                                        </a>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_min_tasks" class="form-label">Minimum Completed Tasks</label>
                                    <input type="number" class="form-control" name="min_completed_tasks" id="edit_min_tasks" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_max_tasks" class="form-label">Maximum Completed Tasks</label>
                                    <input type="number" class="form-control" name="max_completed_tasks" id="edit_max_tasks" min="0">
                                    <small class="text-muted">Leave empty for unlimited</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_icon_color" class="form-label">Icon Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" name="icon_color" id="edit_icon_color" required>
                                <input type="text" class="form-control" id="edit_icon_color_text" placeholder="#ffc107">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_level_description" class="form-label">Description</label>
                            <textarea class="form-control" name="level_description" id="edit_level_description" rows="3"></textarea>
                        </div>

                        <!-- Preview -->
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6>Preview</h6>
                                <span id="preview_icon_wrap" class="d-inline-block mb-2"><i id="preview_icon" class="fas fa-star fa-3x" style="color: #ffc107;"></i></span>
                                <h5 id="preview_name">Level Name</h5>
                                <p id="preview_description" class="text-muted mb-0">Description</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger delete-level-btn me-auto" id="edit_modal_delete_btn">
                            <i class="fas fa-trash me-1"></i>Delete Level
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Level Confirmation Modal -->
    <div class="modal fade" id="deleteLevelModal" tabindex="-1" aria-labelledby="deleteLevelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteLevelModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Delete Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
<?= csrf_field() ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_level">
                        <input type="hidden" name="level_id" id="delete_level_id">
                        <p>Are you sure you want to delete <strong id="delete_level_name"></strong>? This can't be undone.</p>
                        <p class="text-muted mb-0 fs-10">Levels with active writers currently assigned to them can't be deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Writers at Level Modal -->
    <div class="modal fade" id="levelWritersModal" tabindex="-1" aria-labelledby="levelWritersModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="levelWritersModalLabel">Writers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="levelWritersLoading" class="text-center text-muted py-3">
                        <i class="fas fa-spinner fa-spin me-1"></i>Loading writers...
                    </div>
                    <ul id="levelWritersList" class="list-group list-group-flush d-none"></ul>
                    <p id="levelWritersEmpty" class="text-muted text-center mb-0 d-none">No active writers at this level.</p>
                    <p id="levelWritersError" class="text-danger text-center mb-0 d-none">Couldn't load writers. Please try again.</p>
                </div>
            </div>
        </div>
    </div>

    <style>
        .level-stat-card { cursor: pointer; transition: box-shadow 0.16s ease, transform 0.16s ease; }
        .level-stat-card:hover { box-shadow: 0 0.5rem 1.25rem rgba(0,0,0,0.08); transform: translateY(-2px); }
        .level-row { cursor: pointer; }
    </style>

    <script>
        // Use event delegation for dynamically loaded content
        document.addEventListener('DOMContentLoaded', function() {
            // Click a level row to open it for editing
            document.addEventListener('click', function(e) {
                const levelRow = e.target.closest('.level-row');
                if (levelRow) {
                    const levelData = JSON.parse(levelRow.getAttribute('data-level'));
                    editLevel(levelData);
                }

                const statCard = e.target.closest('.level-stat-card');
                if (statCard) {
                    showLevelWriters(statCard.getAttribute('data-level-number'), statCard.getAttribute('data-level-name'), statCard.getAttribute('data-level-range'));
                }

                const deleteBtn = e.target.closest('.delete-level-btn');
                if (deleteBtn) {
                    // Triggered from the Edit modal's own footer - step out of
                    // it before the confirmation modal takes over, so they
                    // don't stack.
                    const editModalEl = document.getElementById('editLevelModal');
                    const openEditModal = bootstrap.Modal.getInstance(editModalEl);
                    if (openEditModal) {
                        openEditModal.hide();
                    }

                    document.getElementById('delete_level_id').value = deleteBtn.getAttribute('data-level-id');
                    document.getElementById('delete_level_name').textContent = deleteBtn.getAttribute('data-level-name');
                    new bootstrap.Modal(document.getElementById('deleteLevelModal')).show();
                }
            });

            // Same trigger via keyboard for the (non-button) clickable rows/cards
            document.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;

                const levelRow = e.target.closest('.level-row');
                if (levelRow) {
                    e.preventDefault();
                    editLevel(JSON.parse(levelRow.getAttribute('data-level')));
                    return;
                }

                const statCard = e.target.closest('.level-stat-card');
                if (statCard) {
                    e.preventDefault();
                    showLevelWriters(statCard.getAttribute('data-level-number'), statCard.getAttribute('data-level-name'), statCard.getAttribute('data-level-range'));
                }
            });

            // Add event listeners for edit modal preview
            const editPreviewInputs = ['edit_level_name', 'edit_icon_class', 'edit_icon_color', 'edit_level_description'];
            editPreviewInputs.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('input', updateEditPreview);
                }
            });

            // Add event listeners for add modal preview
            const addPreviewInputs = ['add_level_name', 'add_icon_class', 'add_icon_color', 'add_level_description'];
            addPreviewInputs.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('input', updateAddPreview);
                }
            });

            // Sync color pickers for edit modal
            const editColorPicker = document.getElementById('edit_icon_color');
            const editColorText = document.getElementById('edit_icon_color_text');

            if (editColorPicker && editColorText) {
                editColorPicker.addEventListener('input', function() {
                    editColorText.value = this.value;
                    updateEditPreview();
                });

                editColorText.addEventListener('input', function() {
                    if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                        editColorPicker.value = this.value;
                        updateEditPreview();
                    }
                });
            }

            // Sync color pickers for add modal
            const addColorPicker = document.getElementById('add_icon_color');
            const addColorText = document.getElementById('add_icon_color_text');

            if (addColorPicker && addColorText) {
                addColorPicker.addEventListener('input', function() {
                    addColorText.value = this.value;
                    updateAddPreview();
                });

                addColorText.addEventListener('input', function() {
                    if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                        addColorPicker.value = this.value;
                        updateAddPreview();
                    }
                });
            }
        });

        function showLevelWriters(levelNumber, levelName, levelRange) {
            const modalEl = document.getElementById('levelWritersModal');
            const modal = new bootstrap.Modal(modalEl);
            const loading = document.getElementById('levelWritersLoading');
            const list = document.getElementById('levelWritersList');
            const empty = document.getElementById('levelWritersEmpty');
            const error = document.getElementById('levelWritersError');

            document.getElementById('levelWritersModalLabel').textContent = levelName + ' (' + levelRange + ') - Active Writers';
            loading.classList.remove('d-none');
            list.classList.add('d-none');
            empty.classList.add('d-none');
            error.classList.add('d-none');
            list.innerHTML = '';

            modal.show();

            fetch('level-writers-api?level_number=' + encodeURIComponent(levelNumber))
                .then(response => {
                    if (!response.ok) throw new Error('Request failed');
                    return response.json();
                })
                .then(data => {
                    loading.classList.add('d-none');
                    const writers = data.writers || [];
                    if (writers.length === 0) {
                        empty.classList.remove('d-none');
                        return;
                    }
                    writers.forEach(writer => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex align-items-center';

                        const icon = document.createElement('i');
                        icon.className = 'fas fa-user-circle text-info me-2';
                        li.appendChild(icon);

                        if (writer.encoded_id) {
                            const link = document.createElement('a');
                            link.href = 'writer?writerID=' + encodeURIComponent(writer.encoded_id);
                            link.className = 'text-decoration-none stretched-link';
                            link.textContent = writer.username || writer.email;
                            li.appendChild(link);
                        } else {
                            const nameSpan = document.createElement('span');
                            nameSpan.textContent = writer.username || writer.email;
                            li.appendChild(nameSpan);
                        }

                        list.appendChild(li);
                    });
                    list.classList.remove('d-none');
                })
                .catch(() => {
                    loading.classList.add('d-none');
                    error.classList.remove('d-none');
                });
        }

        function editLevel(level) {
            try {
                // Populate form fields
                document.getElementById('edit_level_id').value = level.id;
                document.getElementById('edit_level_name').value = level.level_name || '';

                // The footer's Delete button reuses the same delete-level-btn
                // handler as everywhere else - just needs this level's id/name
                // on it, refreshed every time a different row is opened.
                const editModalDeleteBtn = document.getElementById('edit_modal_delete_btn');
                editModalDeleteBtn.setAttribute('data-level-id', level.id);
                editModalDeleteBtn.setAttribute('data-level-name', level.level_name || '');

                // Handle icon class properly - get the actual icon name from database
                let iconClass = level.icon_class || '';

                // Extract just the icon name (remove fas and fa- prefixes)
                iconClass = iconClass.replace(/^fas\s+/, '').replace(/^fa-/, '');

                document.getElementById('edit_icon_class').value = iconClass;
                document.getElementById('edit_min_tasks').value = level.min_completed_tasks || 0;
                document.getElementById('edit_max_tasks').value = level.max_completed_tasks || '';
                document.getElementById('edit_icon_color').value = level.icon_color || '#ffc107';
                document.getElementById('edit_icon_color_text').value = level.icon_color || '#ffc107';
                document.getElementById('edit_level_description').value = level.level_description || '';

                // Update preview immediately
                setTimeout(() => {
                    updateEditPreview();
                }, 100);

                // Show modal
                const editModal = new bootstrap.Modal(document.getElementById('editLevelModal'));
                editModal.show();

            } catch (error) {
                console.error('Error in editLevel function:', error);
                showToast('Error opening edit modal. Please try again.', 'danger');
            }
        }

        function updateEditPreview() {
            try {
                const name = document.getElementById('edit_level_name').value || 'Level Name';
                let iconClass = document.getElementById('edit_icon_class').value || 'star';
                const iconColor = document.getElementById('edit_icon_color').value || '#ffc107';
                const description = document.getElementById('edit_level_description').value || 'Description';

                // Clean and format icon class properly
                iconClass = iconClass.trim().replace(/^(fas\s+)?(fa-)?/, '');

                // Ensure it starts with fa-
                if (iconClass && !iconClass.startsWith('fa-')) {
                    iconClass = 'fa-' + iconClass;
                }

                // Update preview elements. FontAwesome's JS kit converts the
                // <i> into an inline <svg> on page load, so just changing
                // .className afterward has no visual effect (the shape is
                // already baked into the SVG). Re-inserting a fresh <i> node
                // instead lets FontAwesome's mutation observer pick it up
                // and re-convert it to the right icon.
                const previewIconWrap = document.getElementById('preview_icon_wrap');
                const previewName = document.getElementById('preview_name');
                const previewDescription = document.getElementById('preview_description');

                if (previewIconWrap) {
                    previewIconWrap.innerHTML = '';
                    const freshIcon = document.createElement('i');
                    freshIcon.id = 'preview_icon';
                    freshIcon.className = `fas ${iconClass} fa-3x`;
                    freshIcon.style.color = iconColor;
                    previewIconWrap.appendChild(freshIcon);
                }

                if (previewName) {
                    previewName.textContent = name;
                }

                if (previewDescription) {
                    previewDescription.textContent = description;
                }

            } catch (error) {
                console.error('Error in updateEditPreview function:', error);
            }
        }

        function updateAddPreview() {
            try {
                const name = document.getElementById('add_level_name').value || 'Level Name';
                let iconClass = document.getElementById('add_icon_class').value || 'star';
                const iconColor = document.getElementById('add_icon_color').value || '#ffc107';
                const description = document.getElementById('add_level_description').value || 'Description';

                // Clean and format icon class properly
                iconClass = iconClass.trim().replace(/^(fas\s+)?(fa-)?/, '');

                // Ensure it starts with fa-
                if (iconClass && !iconClass.startsWith('fa-')) {
                    iconClass = 'fa-' + iconClass;
                }

                // Update preview elements - see the comment in updateEditPreview()
                // for why this recreates the <i> instead of mutating its class.
                const previewIconWrap = document.getElementById('add_preview_icon_wrap');
                const previewName = document.getElementById('add_preview_name');
                const previewDescription = document.getElementById('add_preview_description');

                if (previewIconWrap) {
                    previewIconWrap.innerHTML = '';
                    const freshIcon = document.createElement('i');
                    freshIcon.id = 'add_preview_icon';
                    freshIcon.className = `fas ${iconClass} fa-3x`;
                    freshIcon.style.color = iconColor;
                    previewIconWrap.appendChild(freshIcon);
                }

                if (previewName) {
                    previewName.textContent = name;
                }

                if (previewDescription) {
                    previewDescription.textContent = description;
                }

            } catch (error) {
                console.error('Error in updateAddPreview function:', error);
            }
        }

        // Initialize tooltips if Bootstrap is available
        if (typeof bootstrap !== 'undefined') {
            document.addEventListener('DOMContentLoaded', function() {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        }
    </script>

<?php include "footer.php"; ?>
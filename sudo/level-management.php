<?php
include_once('head.php');

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
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($level = mysqli_fetch_assoc($levelsResult)): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary fs-6"><?php echo $level['level_number']; ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($level['level_name']); ?></strong>
                            </td>
                            <td>
                                <i class="<?php echo $level['icon_class']; ?> fa-2x" style="color: <?php echo $level['icon_color']; ?>;"></i>
                            </td>
                            <td>
                                <?php echo $level['min_completed_tasks']; ?> -
                                <?php echo $level['max_completed_tasks'] ? $level['max_completed_tasks'] : '∞'; ?> tasks
                            </td>
                            <td class="text-muted">
                                <?php echo htmlspecialchars($level['level_description']); ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-level-btn"
                                        data-level='<?php echo htmlspecialchars(json_encode($level)); ?>'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
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
            $writersByLevelQuery = "SELECT 
            wl.level_number, wl.level_name, wl.icon_class, wl.icon_color,
            COUNT(wp.writer_id) as writer_count,
            AVG(wp.completion_rate) as avg_completion_rate,
            AVG(wp.on_time_rate) as avg_on_time_rate
            FROM tbl_writer_levels wl
            LEFT JOIN tbl_writer_performance wp ON wl.level_number = wp.current_level
            GROUP BY wl.level_number
            ORDER BY wl.level_number";
            $writersByLevel = mysqli_query($con, $writersByLevelQuery);
            ?>

            <div class="row g-3">
                <?php while ($levelStats = mysqli_fetch_assoc($writersByLevel)): ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="card border-0 bg-body-tertiary h-100">
                            <div class="card-body text-center">
                                <i class="<?php echo $levelStats['icon_class']; ?> fa-3x mb-3" style="color: <?php echo $levelStats['icon_color']; ?>;"></i>
                                <h6 class="mb-2"><?php echo $levelStats['level_name']; ?></h6>
                                <h4 class="text-primary mb-1"><?php echo $levelStats['writer_count']; ?></h4>
                                <small class="text-muted">Writers</small>
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
                                <i id="add_preview_icon" class="fas fa-star fa-3x mb-2" style="color: #ffc107;"></i>
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
                                <i id="preview_icon" class="fas fa-star fa-3x mb-2" style="color: #ffc107;"></i>
                                <h5 id="preview_name">Level Name</h5>
                                <p id="preview_description" class="text-muted mb-0">Description</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Use event delegation for dynamically loaded content
        document.addEventListener('DOMContentLoaded', function() {
            // Handle edit button clicks using event delegation
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('edit-level-btn') || e.target.closest('.edit-level-btn')) {
                    e.preventDefault();

                    const button = e.target.classList.contains('edit-level-btn') ? e.target : e.target.closest('.edit-level-btn');
                    const levelData = JSON.parse(button.getAttribute('data-level'));

                    editLevel(levelData);
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

        function editLevel(level) {
            try {
                // Populate form fields
                document.getElementById('edit_level_id').value = level.id;
                document.getElementById('edit_level_name').value = level.level_name || '';

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
                alert('Error opening edit modal. Please try again.');
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

                // Update preview elements
                const previewIcon = document.getElementById('preview_icon');
                const previewName = document.getElementById('preview_name');
                const previewDescription = document.getElementById('preview_description');

                if (previewIcon) {
                    previewIcon.className = `fas ${iconClass} fa-3x mb-2`;
                    previewIcon.style.color = iconColor;
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

                // Update preview elements
                const previewIcon = document.getElementById('add_preview_icon');
                const previewName = document.getElementById('add_preview_name');
                const previewDescription = document.getElementById('add_preview_description');

                if (previewIcon) {
                    previewIcon.className = `fas ${iconClass} fa-3x mb-2`;
                    previewIcon.style.color = iconColor;
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
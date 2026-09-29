<?php include "head.php";?>
    <title>iTasker | Create New Task</title>
<?php include "navi.php";?><div id="alert-container"></div>

    <script>
        // Apply the saved "Improved view" preference before the page renders, to avoid a flash of the classic layout.
        try { if (localStorage.getItem('ctModernView') === '1') document.documentElement.classList.add('ct-modern'); } catch (e) {}
    </script>
    <style>
        /* ===== Improved view (html.ct-modern) for sudo/create-task.php - a pure re-layout/restyle
           of the existing form: every id, name and submit handler is untouched, so all existing
           functionality (validation, Dropzone uploads, writer email autofill, custom CPP, the
           fetch-based submit flow) keeps working unmodified. Classic view is the default. ===== */
        .ct-only { display: none !important; }
        html.ct-modern .ct-only { display: inline-flex !important; }
        html.ct-modern .ct-sidebar.ct-only { display: flex !important; }
        .ct-icon-classic { display: none; }
        html.ct-modern .ct-icon-classic { display: inline-flex; }
        html.ct-modern .ct-icon-improved { display: none; }
        .ct-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; }

        /* Section quick-nav pills, shown only in the improved view */
        .ct-quicknav { display: none; }
        html.ct-modern .ct-quicknav { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px; border-radius: 999px; background: var(--falcon-body-bg-tertiary, rgba(127,127,127,.08)); margin-bottom: 20px; }
        .ct-quicknav a { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; color: var(--falcon-secondary-color); text-decoration: none; transition: background .15s, color .15s; scroll-margin-top: 90px; }
        .ct-quicknav a:hover { background: rgba(var(--falcon-primary-rgb), .1); color: var(--falcon-primary); }
        .ct-quicknav a.active { background: var(--falcon-primary); color: #fff; }

        html.ct-modern #taskForm > .pb-4,
        html.ct-modern #taskForm > .pt-3.pb-4,
        html.ct-modern #taskForm > .pt-3.mb-4 { scroll-margin-top: 90px; }

        /* Two-column grid: form sections on the left, a live sticky summary on the right */
        @media (min-width: 1200px) {
            html.ct-modern #taskForm { display: grid; grid-template-columns: minmax(0, 1fr) 340px; column-gap: 28px; align-items: start; }
            html.ct-modern #taskForm > .ct-basic { grid-column: 1; grid-row: 1; }
            html.ct-modern #taskForm > .ct-desc { grid-column: 1; grid-row: 2; }
            html.ct-modern #taskForm > .ct-files { grid-column: 1; grid-row: 3; }
            html.ct-modern #taskForm > .ct-actions-bar { grid-column: 1 / -1; grid-row: 4; }
            html.ct-modern #taskForm > .ct-sidebar { grid-column: 2; grid-row: 1 / span 3; position: sticky; top: 84px; }
        }
        html.ct-modern #taskForm > .pb-4,
        html.ct-modern #taskForm > .pt-3.pb-4,
        html.ct-modern #taskForm > .pt-3.mb-4 { border: 1px solid var(--falcon-border-color); border-radius: 16px; padding: 20px !important; margin-bottom: 20px; }
        html.ct-modern #taskForm > .pt-3.mb-4 { margin-bottom: 0 !important; }
        @media (min-width: 1200px) { html.ct-modern #taskForm > .ct-files { margin-bottom: 0; } }

        html.ct-modern .ct-sidebar-card { border: 1px solid var(--falcon-border-color); border-radius: 16px; padding: 18px; background: var(--falcon-body-bg-tertiary, rgba(127,127,127,.04)); }
        html.ct-modern .ct-sidebar-card + .ct-sidebar-card { margin-top: 16px; }
        html.ct-modern .ct-sidebar-title { font-size: 11px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: var(--falcon-secondary-color); margin-bottom: 12px; }
        .ct-summary-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; padding: 6px 0; font-size: 13px; }
        .ct-summary-row + .ct-summary-row { border-top: 1px dashed var(--falcon-border-color); }
        .ct-summary-row .ct-summary-label { color: var(--falcon-secondary-color); font-weight: 600; }
        .ct-summary-row .ct-summary-value { text-align: right; font-weight: 700; color: var(--falcon-emphasis-color); max-width: 60%; overflow-wrap: anywhere; }
        .ct-summary-total { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--falcon-border-color); }
        .ct-summary-total .ct-summary-total-value { font-size: 20px; font-weight: 800; color: var(--falcon-success); }
        .ct-writer-preview { display: flex; align-items: center; gap: 10px; }
        .ct-writer-avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; background: rgba(var(--falcon-primary-rgb), .15); color: var(--falcon-primary); flex-shrink: 0; }
        .ct-checklist { list-style: none; margin: 0; padding: 0; font-size: 13px; }
        .ct-checklist li { display: flex; align-items: center; gap: 8px; padding: 5px 0; color: var(--falcon-secondary-color); }
        .ct-checklist li.done { color: var(--falcon-emphasis-color); }
        .ct-checklist li i { width: 16px; color: var(--falcon-300, #d8e2ef); }
        .ct-checklist li.done i { color: var(--falcon-success); }

        /* The "Select writer" Choices.js dropdown was rendering behind the Task Description
           row: TinyMCE's own editor container is a positioned element (like the dropdown
           itself) that comes later in the DOM, so with no explicit z-index it painted on top
           of - and see-through over - the writer list. Force it above with an opaque background. */
        .choices .choices__list--dropdown {
            z-index: 1060 !important;
            background-color: var(--falcon-card-bg, #fff) !important;
        }
    </style>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Create <span class="text-info fw-medium">New Task</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0 ct-actions">
                    <button type="button" id="ctViewToggle" class="btn btn-sm btn-primary" aria-pressed="false" title="Switch between the classic and improved layout">
                        <span class="ct-icon-improved"><i class="fas fa-magic" aria-hidden="true"></i></span><span class="ct-icon-classic"><i class="fas fa-list-alt" aria-hidden="true"></i></span>
                        <span class="ms-1" id="ctViewToggleText">Improved view</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header border-bottom border-dashed">
            <h5 class="mb-0" data-anchor="data-anchor"><span class="fas fa-clipboard-list text-primary me-2"></span>Task Details</h5>
        </div>
        <div class="card-body pt-3">
            <div class="ct-quicknav">
                <a href="#ctSectionBasic"><i class="fas fa-info-circle"></i> Basic Information</a>
                <a href="#ctSectionDesc"><i class="fas fa-align-left"></i> Description</a>
                <a href="#ctSectionFiles"><i class="fas fa-paperclip"></i> Files</a>
            </div>
            <form class="needs-validation" novalidate="novalidate" id="taskForm" method="post" action="submit-task" enctype="multipart/form-data">
<?= csrf_field() ?>
                <div class="pb-4 border-bottom border-dashed ct-basic" id="ctSectionBasic">
                    <h6 class="text-uppercase text-body-tertiary fs-11 fw-bold mb-3"><span class="fas fa-info-circle text-primary me-1"></span> Basic Information</h6>
                    <div class="row gx-3">
                        <div class="col-12 mb-3">
                            <label class="form-label" for="manufacturer-name">Topic:</label>
                            <input class="form-control" name="topic" type="text" required="required" />
                            <div class="invalid-feedback">This field is required</div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="import-status">Subject: </label>
                            <input class="form-control" name="subject" type="text" required="required" />
                            <div class="invalid-feedback">This field is required</div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="origin-country">Account: </label>
                            <input class="form-control" name="account" type="text" required="required" />
                            <div class="invalid-feedback">This field is required</div>
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label" for="product-summary">Pages: </label>
                            <input class="form-control"  type="number" name="pages" id="pages" min="0" step="0.5" required="required"/>
                            <div class="invalid-feedback">This field is required</div>
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label" for="cpp">CPP: </label>
                            <select class="form-select" id="cpp" name="cpp" required="required" onchange="toggleCustomCpp(this)">
                                <option selected disabled></option>
                                <option value="375">375</option>
                                <option value="250">250</option>
                                <option value="300">300</option>
                                <option value="190">190</option>
                                <option value="350">350</option>
                                <option value="200">200</option>
                                <option value="400">400</option>
                                <option value="450">450</option>
                                <option value="500">500</option>
                                <option value="750">750</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <input type="number" class="form-control mt-2" id="cpp_custom" placeholder="Enter custom CPP value" min="1" style="display:none;">
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label d-block">Confirmed: </label>
                            <div class="btn-group" role="group" aria-label="Confirmed">
                                <input type="radio" class="btn-check" name="is_confirmed" id="isConfirmedYes" value="0" checked>
                                <label class="btn btn-outline-success" for="isConfirmedYes">Yes</label>

                                <input type="radio" class="btn-check" name="is_confirmed" id="isConfirmedNo" value="1">
                                <label class="btn btn-outline-secondary" for="isConfirmedNo">No</label>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="basic-form-due">Deadline:</label>
                            <input class="form-control" name="due_date" required="required" id="due_date" type="datetime-local" min="<?php echo date('Y-m-d\T00:00'); ?>" />
                            <div class="invalid-feedback">This field is required</div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="publish">Publish: </label>
                            <select class="form-select" name="publish" id="publish">
                                <option selected value="1">Yes (Send Email & Set Active)</option>
                                <option value="0">No (Save as Draft, No Email)</option>
                            </select>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="cpp">Select writer: </label>
                            <select class="form-select js-choice" name="writer" id="writerSelect" required="required" data-options='{"removeItemButton":true,"placeholder":true}' >
                                <option selected disabled value="">Select Writer</option>
                                <?php
                                // Availability status (available/busy/away) is appended to the
                                // label so it shows without any changes to the Choices.js init -
                                // see [[oeuvre-storage-provider-toggle]]-style "don't touch shared
                                // JS" caution. Falls back gracefully if the migration hasn't run
                                // yet (SELECT * would 500; this only selects the one new column).
                                $hasAvailabilityColumn = true;
                                try {
                                    $writerQuery = "SELECT id, username, email, availability_status FROM tblwriters WHERE is_deleted = 0 AND is_verified=1 ORDER BY id ASC";
                                    $query = mysqli_query($con, $writerQuery);
                                } catch (\mysqli_sql_exception $e) {
                                    $hasAvailabilityColumn = false;
                                    $query = mysqli_query($con, "SELECT id, username, email FROM tblwriters WHERE is_deleted = 0 AND is_verified=1 ORDER BY id ASC");
                                }
                                $availabilityDots = ['available' => '🟢', 'busy' => '🟡', 'away' => '⚪'];
                                while ($row = mysqli_fetch_assoc($query)) {
                                    $dot = $hasAvailabilityColumn ? ($availabilityDots[$row['availability_status'] ?? 'available'] ?? '🟢') . ' ' : '';
                                    $statusLabel = $hasAvailabilityColumn ? ' (' . ucfirst($row['availability_status'] ?? 'available') . ')' : '';
                                    echo "<option value='" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "|" . htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') . "'>" . $dot . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . $statusLabel . "</option>";
                                }
                                ?>
                            </select>
                            <div id="writerError" class="invalid-feedback">Please select a writer.</div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="product-summary">Writer email: </label>
                            <input class="form-control" type="email" name="email" value="" id="email" required="required"  readonly/>
                            <div class="invalid-feedback">This field is required</div>
                        </div>
                    </div>
                </div>

                <div class="pt-3 pb-4 border-bottom border-dashed ct-desc" id="ctSectionDesc">
                    <h6 class="text-uppercase text-body-tertiary fs-11 fw-bold mb-3"><span class="fas fa-align-left text-primary me-1"></span> Task Description</h6>
                    <label class="form-label visually-hidden" for="description">Task description:</label>
                    <textarea name="description" id="description"></textarea>
                    <div class="invalid-feedback">This field is required</div>
                    <?php include 'task-description-editor.php'; ?>
                </div>

                <div class="pt-3 mb-4 ct-files" id="ctSectionFiles">
                    <h6 class="text-uppercase text-body-tertiary fs-11 fw-bold mb-3"><span class="fas fa-paperclip text-primary me-1"></span> Task Files</h6>
                    <div id="dropArea" class="dropzone border rounded-3"></div>
                    <div class="form-text mt-2">
                        Accepted: Word, Excel, PowerPoint, PDF, ZIP, and photos (JPG, PNG, GIF, WEBP, HEIC, BMP, TIFF) — max 50MB per file.
                    </div>
                    <input type="hidden" name="uploadedFiles" id="uploadedFiles" value="">
                </div>

                <!-- Improved view only: a live, read-only summary of what's about to be created,
                     plus a running checklist. Purely decorative - it just mirrors the real form
                     fields below via JS and never participates in submission itself. -->
                <div class="ct-sidebar ct-only" style="flex-direction: column;">
                    <div class="ct-sidebar-card">
                        <div class="ct-sidebar-title"><i class="fas fa-eye me-1"></i>Task Summary</div>
                        <div class="ct-summary-row">
                            <span class="ct-summary-label">Topic</span>
                            <span class="ct-summary-value" id="ctSummaryTopic">—</span>
                        </div>
                        <div class="ct-summary-row">
                            <span class="ct-summary-label">Subject</span>
                            <span class="ct-summary-value" id="ctSummarySubject">—</span>
                        </div>
                        <div class="ct-summary-row">
                            <span class="ct-summary-label">Account</span>
                            <span class="ct-summary-value" id="ctSummaryAccount">—</span>
                        </div>
                        <div class="ct-summary-row">
                            <span class="ct-summary-label">Due date</span>
                            <span class="ct-summary-value" id="ctSummaryDue">—</span>
                        </div>
                        <div class="ct-summary-row">
                            <span class="ct-summary-label">Publish</span>
                            <span class="ct-summary-value" id="ctSummaryPublish">—</span>
                        </div>
                        <div class="ct-summary-row">
                            <span class="ct-summary-label">Files attached</span>
                            <span class="ct-summary-value" id="ctSummaryFiles">0</span>
                        </div>
                        <div class="ct-summary-total">
                            <span class="ct-summary-label">Total cost</span>
                            <span class="ct-summary-total-value">Ksh <span id="ctSummaryTotal">0.00</span></span>
                        </div>
                    </div>
                    <div class="ct-sidebar-card">
                        <div class="ct-sidebar-title"><i class="fas fa-user-edit me-1"></i>Writer</div>
                        <div class="ct-writer-preview">
                            <div class="ct-writer-avatar" id="ctWriterAvatar">?</div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold" id="ctWriterName">No writer selected</div>
                                <div class="text-muted fs-11" id="ctWriterEmail"></div>
                            </div>
                        </div>
                    </div>
                    <div class="ct-sidebar-card">
                        <div class="ct-sidebar-title"><i class="fas fa-list-check me-1"></i>Checklist</div>
                        <ul class="ct-checklist">
                            <li id="ctCheckTopic"><i class="fas fa-circle-check"></i> Topic &amp; subject</li>
                            <li id="ctCheckWriter"><i class="fas fa-circle-check"></i> Writer assigned</li>
                            <li id="ctCheckDue"><i class="fas fa-circle-check"></i> Deadline set</li>
                            <li id="ctCheckDesc"><i class="fas fa-circle-check"></i> Description written</li>
                            <li id="ctCheckFiles"><i class="fas fa-circle-check"></i> Files attached (optional)</li>
                        </ul>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top ct-actions-bar">
                    <h5 class="mb-2 mb-md-0">You're almost done!</h5>
                    <div>
                        <button class="btn btn-link text-secondary p-0 me-3 fw-medium" type="button" id="discardButton" role="button">Discard</button>
                        <button type="submit" id="createTaskButton" class="btn btn-primary" name="createTask" role="button">
                            <span id="buttonText">Create Task</span>
                            <span id="loadingSpinner" class="d-none">
                                Creating Task...
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('writerSelect').addEventListener('change', function() {
            var selectedOption = this.value.split('|'); // Split the value by the delimiter to get [name, email]
            if (selectedOption.length === 2) { // Make sure both name and email are present
                var email = selectedOption[1]; // Get the email part
                document.getElementById('email').value = email; // Update the email input field
            } else {
                document.getElementById('email').value = ''; // Clear the email input if not a valid selection
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            const discardButton = document.getElementById('discardButton');
            const form = document.getElementById('taskForm');

            discardButton.addEventListener('click', function() {
                form.reset();
                // Optionally, scroll to the top if you want to reset the view as well
                window.scrollTo(0, 0);
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('taskForm'); // Ensure you have the correct form ID
            const writerSelect = document.getElementById('writerSelect');
            const writerError = document.getElementById('writerError');

            // Validate the writerSelect on form submit
            form.addEventListener('submit', function(e) {
                if (writerSelect.value === "") {
                    e.preventDefault(); // Prevent form submission
                    writerError.style.display = 'block'; // Show the error message
                } else {
                    writerError.style.display = 'none'; // Hide the error message if a writer is selected
                }
            });

            // Optionally: Hide the error message when a valid option is selected
            writerSelect.addEventListener('change', function() {
                if (writerSelect.value === "") {
                    writerError.style.display = 'block';
                } else {
                    writerError.style.display = 'none';
                }
            });
        });

        function toggleCustomCpp(select) {
            const customInput = document.getElementById('cpp_custom');
            if (select.value === 'custom') {
                customInput.style.display = 'block';
                customInput.required = true;
                customInput.focus();
            } else {
                customInput.style.display = 'none';
                customInput.required = false;
                customInput.value = '';
            }
        }

        // Before form submit, swap "custom" select value with the typed number
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('taskForm').addEventListener('submit', function() {
                const cppSelect = document.getElementById('cpp');
                const customInput = document.getElementById('cpp_custom');
                if (cppSelect.value === 'custom' && customInput.value) {
                    // A <select>'s value can only be set to something matching one
                    // of its <option>s - assigning an arbitrary number directly
                    // (as below, previously) is silently ignored by the browser.
                    // Inject a matching option instead so the typed value actually
                    // gets submitted.
                    let customOption = cppSelect.querySelector('option[data-custom-cpp]');
                    if (!customOption) {
                        customOption = document.createElement('option');
                        customOption.setAttribute('data-custom-cpp', 'true');
                        cppSelect.appendChild(customOption);
                    }
                    customOption.value = customInput.value;
                    customOption.selected = true;
                }
            }, true); // capture phase so it runs before other submit listeners
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('taskForm');
            const createTaskButton = document.getElementById('createTaskButton');
            const buttonText = document.getElementById('buttonText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            let uploadedFilePaths = []; // To store paths of successfully uploaded files

            // Fireworks function
            function triggerFireworks() {
                // Create multiple bursts of fireworks
                const duration = 3000; // 3 seconds
                const animationEnd = Date.now() + duration;
                const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 0 };

                function randomInRange(min, max) {
                    return Math.random() * (max - min) + min;
                }

                const interval = setInterval(function() {
                    const timeLeft = animationEnd - Date.now();

                    if (timeLeft <= 0) {
                        return clearInterval(interval);
                    }

                    const particleCount = 50 * (timeLeft / duration);

                    // Create fireworks from different positions
                    confetti(Object.assign({}, defaults, {
                        particleCount,
                        origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 }
                    }));
                    confetti(Object.assign({}, defaults, {
                        particleCount,
                        origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 }
                    }));
                }, 250);

                // Additional burst in the center
                setTimeout(() => {
                    confetti({
                        particleCount: 100,
                        spread: 70,
                        origin: { y: 0.6 }
                    });
                }, 500);
            }

            // Keep the visible file name short; the last 4 characters (usually
            // the extension) always stay visible. Full name is on the title tooltip.
            function truncateFileName(name, maxLength = 24) {
                if (name.length <= maxLength) return name;
                const keepEnd = 4;
                const end = name.slice(-keepEnd);
                const start = name.slice(0, Math.max(maxLength - keepEnd - 3, 1));
                return `${start}...${end}`;
            }

            function updateUploadedFilesInput() {
                document.getElementById('uploadedFiles').value = JSON.stringify(uploadedFilePaths); // Update hidden input value
            }

            async function deleteFileFromServer(filePath) {
                const formData = new FormData();
                formData.append('filePath', filePath);
                formData.append('action', 'deleteFile');
                formData.append('csrf_token', csrfToken);

                try {
                    const response = await fetch('delete_file', {
                        method: 'POST',
                        body: formData,
                    });

                    const data = await response.json();
                    if (data.status !== 'success') {
                        console.error('Failed to delete file: ' + data.message);
                    } else {
                        console.log('File deleted successfully');
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            }

            const csrfToken = form.querySelector('input[name="csrf_token"]').value;

            const taskDropzone = new Dropzone('#dropArea', {
                url: 'upload',
                paramName: 'file',
                maxFilesize: 50, // MB - matches the chat-attachment policy in shared-functions.php
                acceptedFiles: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.jpg,.jpeg,.png,.gif,.webp,.heic,.heif,.avif,.bmp,.tiff,.tif',
                addRemoveLinks: true,
                dictDefaultMessage: 'Drag and drop your files here or click to select files',
                dictRemoveFile: 'Remove',
                dictFileTooBig: 'File is too big ({{filesize}}MiB). Max file size: {{maxFilesize}}MiB.',
                dictInvalidFileType: "You can't upload files of this type. Accepted: Word, Excel, PowerPoint, PDF, ZIP, and photos.",
                init: function () {
                    this.on('sending', function (file, xhr, formData) {
                        formData.append('action', 'upload');
                        formData.append('csrf_token', csrfToken);
                    });

                    this.on('addedfile', function (file) {
                        const nameEl = file.previewElement.querySelector('[data-dz-name]');
                        if (nameEl) {
                            nameEl.textContent = truncateFileName(file.name);
                            nameEl.title = file.name;
                        }
                        if (window.ctRefreshSummary) window.ctRefreshSummary();
                    });

                    this.on('success', function (file, response) {
                        let data = response;
                        if (typeof data === 'string') {
                            try { data = JSON.parse(data); } catch (e) { data = null; }
                        }
                        if (data && data.status === 'success') {
                            uploadedFilePaths.push({
                                fileName: file.name,
                                filePath: data.filePath,
                                fileUrl: data.fileUrl,
                                fileSize: data.fileSize
                            });
                            updateUploadedFilesInput();
                        } else {
                            this.emit('error', file, (data && data.message) || 'Upload failed.');
                        }
                    });

                    this.on('removedfile', function (file) {
                        const index = uploadedFilePaths.findIndex(f => f.fileName === file.name);
                        if (index > -1) {
                            deleteFileFromServer(uploadedFilePaths[index].filePath);
                            uploadedFilePaths.splice(index, 1);
                            updateUploadedFilesInput();
                            if (window.ctRefreshSummary) window.ctRefreshSummary();
                        }
                    });
                }
            });

            form.addEventListener('submit', async function(e) {
                e.preventDefault(); // Prevent the default form submission
                // Example validation check
                if (!form.checkValidity()) {
                    // Display an error message or highlight the invalid fields
                    displayBootstrapAlert('Please fill in all required fields.', 'danger');
                    return; // Stop the function if validation fails
                }

                // Show loading spinner and disable button
                createTaskButton.disabled = true;
                buttonText.classList.add('d-none');
                loadingSpinner.classList.remove('d-none');

                handleSubmit();
            });

            async function handleSubmit() {
                const formData = new FormData(form);
                formData.append('action', 'submitForm');

                try {
                    const response = await fetch('submit-task', {
                        method: 'POST',
                        body: formData,
                    });

                    // Get the raw text response
                    const responseText = await response.text();
                    console.log("Raw server response:", responseText);

                    // Extract the JSON part from the response
                    // This regex looks for a JSON object at the end of the string
                    const jsonMatch = responseText.match(/(\{.*\})$/s);

                    if (jsonMatch && jsonMatch[1]) {
                        try {
                            const data = JSON.parse(jsonMatch[1]);

                            if (data.status === 'success') {
                                // TRIGGER FIREWORKS ON SUCCESS!
                                triggerFireworks();

                                // Show success message with fireworks
                                displayBootstrapAlert(`🎉 ${data.message} 🎉`, 'success');

                                // Delay the redirect to let users enjoy the fireworks
                                setTimeout(() => {
                                    window.location.href = `view-task?task_id=${data.task_id}`;
                                }, 5000);

                            } else if (data.status === 'error') {
                                displayBootstrapAlert(`Failed to submit the form: ${data.message}`, 'danger');
                                resetButton();
                            }
                        } catch (parseError) {
                            console.error("JSON parse error:", parseError);
                            displayBootstrapAlert(`Error parsing server response. See console for details.`, 'danger');
                            resetButton();
                        }
                    } else {
                        console.error("Could not find valid JSON in response");
                        displayBootstrapAlert(`Server returned an invalid response. See console for details.`, 'danger');
                        resetButton();
                    }
                } catch (error) {
                    console.error("Error during form submission:", error);
                    displayBootstrapAlert(`An error occurred while submitting the form: ${error.message}`, 'danger');
                    resetButton();
                }
            }

            function resetButton() {
                createTaskButton.disabled = false;
                buttonText.classList.remove('d-none');
                loadingSpinner.classList.add('d-none');
            }

            function displayBootstrapAlert(message, type) {
                const alertContainer = document.getElementById('alert-container');
                const alertHTML = `
            <div class="alert alert-${type} border-0 d-flex align-items-center" role="alert">
                <p class="mb-0 flex-1">${message}</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
                alertContainer.innerHTML = alertHTML;
                // Scroll the alert container into view
                alertContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

            }
        });
    </script>
    <script>
        // Improved-view toggle, section quick-nav scroll-spy, and the live sidebar summary.
        // Purely additive/read-only - none of it touches the real form fields' values or IDs.
        document.addEventListener('DOMContentLoaded', function () {
            var root = document.documentElement;
            var toggleBtn = document.getElementById('ctViewToggle');
            var toggleText = document.getElementById('ctViewToggleText');

            function syncToggle() {
                var on = root.classList.contains('ct-modern');
                if (toggleBtn) {
                    toggleBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    toggleBtn.classList.toggle('btn-primary', !on);
                    toggleBtn.classList.toggle('btn-outline-primary', on);
                }
                if (toggleText) toggleText.textContent = on ? 'Classic view' : 'Improved view';
            }
            syncToggle();
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    var on = root.classList.toggle('ct-modern');
                    try { localStorage.setItem('ctModernView', on ? '1' : '0'); } catch (e) {}
                    syncToggle();
                });
            }

            // Quick-nav: smooth scroll + highlight the section currently in view.
            var navLinks = Array.prototype.slice.call(document.querySelectorAll('.ct-quicknav a'));
            var sections = navLinks.map(function (a) { return document.querySelector(a.getAttribute('href')); });
            navLinks.forEach(function (a) {
                a.addEventListener('click', function (e) {
                    var target = document.querySelector(a.getAttribute('href'));
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
            if (sections.length && 'IntersectionObserver' in window) {
                var spy = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        var idx = sections.indexOf(entry.target);
                        if (idx === -1) return;
                        if (entry.isIntersecting) {
                            navLinks.forEach(function (a) { a.classList.remove('active'); });
                            navLinks[idx].classList.add('active');
                        }
                    });
                }, { rootMargin: '-100px 0px -70% 0px' });
                sections.forEach(function (s) { if (s) spy.observe(s); });
            }

            // Live sidebar summary - mirrors the real fields, changes nothing about them.
            var topicInput = document.querySelector('#taskForm [name="topic"]');
            var subjectInput = document.querySelector('#taskForm [name="subject"]');
            var accountInput = document.querySelector('#taskForm [name="account"]');
            var pagesInput = document.getElementById('pages');
            var cppSelect = document.getElementById('cpp');
            var cppCustom = document.getElementById('cpp_custom');
            var dueInput = document.getElementById('due_date');
            var publishSelect = document.getElementById('publish');
            var writerSelect = document.getElementById('writerSelect');
            var descTextarea = document.getElementById('description');

            var elTopic = document.getElementById('ctSummaryTopic');
            var elSubject = document.getElementById('ctSummarySubject');
            var elAccount = document.getElementById('ctSummaryAccount');
            var elDue = document.getElementById('ctSummaryDue');
            var elPublish = document.getElementById('ctSummaryPublish');
            var elFiles = document.getElementById('ctSummaryFiles');
            var elTotal = document.getElementById('ctSummaryTotal');
            var writerAvatar = document.getElementById('ctWriterAvatar');
            var writerName = document.getElementById('ctWriterName');
            var writerEmailEl = document.getElementById('ctWriterEmail');

            function currentCpp() {
                if (!cppSelect) return 0;
                if (cppSelect.value === 'custom') return parseFloat(cppCustom && cppCustom.value) || 0;
                return parseFloat(cppSelect.value) || 0;
            }

            function setCheck(id, ok) {
                var li = document.getElementById(id);
                if (li) li.classList.toggle('done', !!ok);
            }

            // The description field is a TinyMCE editor (task-description-editor.php) that only
            // writes back into this hidden <textarea> on form submit (editor.save()), so reading
            // descTextarea.value here would always see stale/empty content while typing. Read the
            // live editor content instead, falling back to the textarea before TinyMCE has loaded.
            function getDescriptionText() {
                if (window.tinymce) {
                    var editor = tinymce.get('description');
                    if (editor) return editor.getContent({ format: 'text' }).trim();
                }
                return descTextarea ? descTextarea.value.replace(/<[^>]*>/g, '').trim() : '';
            }

            function refreshSummary() {
                if (elTopic) elTopic.textContent = (topicInput && topicInput.value.trim()) || '—';
                if (elSubject) elSubject.textContent = (subjectInput && subjectInput.value.trim()) || '—';
                if (elAccount) elAccount.textContent = (accountInput && accountInput.value.trim()) || '—';

                if (elDue) {
                    if (dueInput && dueInput.value) {
                        var d = new Date(dueInput.value);
                        elDue.textContent = isNaN(d) ? dueInput.value : d.toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });
                    } else {
                        elDue.textContent = '—';
                    }
                }

                if (elPublish && publishSelect) {
                    elPublish.textContent = publishSelect.value === '1' ? 'Yes, notify writer' : 'No, save as draft';
                }

                var pages = parseFloat(pagesInput && pagesInput.value) || 0;
                var cpp = currentCpp();
                if (elTotal) elTotal.textContent = (pages * cpp).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                if (elFiles) elFiles.textContent = document.querySelectorAll('#dropArea .dz-preview').length;

                setCheck('ctCheckTopic', topicInput && topicInput.value.trim() && subjectInput && subjectInput.value.trim());
                setCheck('ctCheckWriter', writerSelect && writerSelect.value);
                setCheck('ctCheckDue', dueInput && dueInput.value);
                setCheck('ctCheckDesc', !!getDescriptionText());
                setCheck('ctCheckFiles', document.querySelectorAll('#dropArea .dz-preview').length > 0);
            }

            function refreshWriter() {
                if (!writerSelect || !writerAvatar || !writerName || !writerEmailEl) return;
                var opt = writerSelect.options[writerSelect.selectedIndex];
                var parts = (writerSelect.value || '').split('|');
                if (parts.length === 2 && parts[0]) {
                    var uname = parts[0];
                    writerName.textContent = uname;
                    writerEmailEl.textContent = parts[1] || '';
                    writerAvatar.textContent = uname.slice(0, 2).toUpperCase();
                } else {
                    writerName.textContent = 'No writer selected';
                    writerEmailEl.textContent = '';
                    writerAvatar.textContent = '?';
                }
            }

            ['input', 'change'].forEach(function (evt) {
                [topicInput, subjectInput, accountInput, pagesInput, cppSelect, cppCustom, dueInput, publishSelect].forEach(function (el) {
                    if (el) el.addEventListener(evt, refreshSummary);
                });
            });
            if (writerSelect) writerSelect.addEventListener('change', function () { refreshWriter(); refreshSummary(); });

            // TinyMCE loads asynchronously (task-description-editor.php calls tinymce.init
            // separately) - wait for the editor instance to exist, then hook its own keystroke
            // events for an instant update instead of relying only on the fallback poll below.
            (function bindTinyMceLive(attemptsLeft) {
                if (window.tinymce && tinymce.get('description')) {
                    tinymce.get('description').on('input keyup change SetContent', refreshSummary);
                    return;
                }
                if (attemptsLeft > 0) setTimeout(function () { bindTinyMceLive(attemptsLeft - 1); }, 300);
            })(40); // ~12s worst case for a slow-loading editor

            // Exposed so the Dropzone instance (initialized in the script block above, which
            // already has its own addedfile/removedfile/success handlers) can trigger an
            // instant refresh instead of waiting on the fallback poll below.
            window.ctRefreshSummary = refreshSummary;

            setInterval(refreshSummary, 1000);

            refreshWriter();
            refreshSummary();
        });
    </script>
<?php
include "footer.php";
?>
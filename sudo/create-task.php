<?php include "head.php";?>
    <title>iTasker | Create New Task</title>
<?php include "navi.php";?><div id="alert-container"></div>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Create <span class="text-info fw-medium">New Task</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class="col-auto">
                        </div>
                        <div class="col-md-auto position-relative">
                            <h6 class="mb-1 text-primary"></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header border-bottom border-dashed">
            <h5 class="mb-0" data-anchor="data-anchor"><span class="fas fa-clipboard-list text-primary me-2"></span>Task Details</h5>
        </div>
        <div class="card-body pt-3">
            <form class="needs-validation" novalidate="novalidate" id="taskForm" method="post" action="submit-task" enctype="multipart/form-data">
<?= csrf_field() ?>
                <div class="pb-4 border-bottom border-dashed">
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
                            <label class="form-label" for="cpp">Confirmed: </label>
                            <select class="form-select" name="is_confirmed">
                                <option selected value="0">Yes</option>
                                <option value="1">No</option>
                            </select>
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
                                // Assuming $con is your database connection
                                $query = mysqli_query($con, "SELECT id, username, email FROM tblwriters WHERE is_deleted = 0 AND is_verified=1 ORDER BY id ASC");
                                while ($row = mysqli_fetch_assoc($query)) {
                                    echo "<option value='" . $row['username'] . "|" . $row['email'] . "'>" . $row['username'] . "</option>";
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

                <div class="pt-3 pb-4 border-bottom border-dashed">
                    <h6 class="text-uppercase text-body-tertiary fs-11 fw-bold mb-3"><span class="fas fa-align-left text-primary me-1"></span> Task Description</h6>
                    <label class="form-label visually-hidden" for="description">Task description:</label>
                    <textarea name="description" id="description"></textarea>
                    <div class="invalid-feedback">This field is required</div>
                    <?php include 'task-description-editor.php'; ?>
                </div>

                <div class="pt-3 mb-4">
                    <h6 class="text-uppercase text-body-tertiary fs-11 fw-bold mb-3"><span class="fas fa-paperclip text-primary me-1"></span> Task Files</h6>
                    <div id="dropArea" class="dropzone border rounded-3"></div>
                    <input type="hidden" name="uploadedFiles" id="uploadedFiles" value="">
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top">
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
                maxFilesize: 1024, // MB, effectively unlimited like the previous accept="*/*"
                addRemoveLinks: true,
                dictDefaultMessage: 'Drag and drop your files here or click to select files',
                dictRemoveFile: 'Remove',
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
<?php
include "footer.php";
?>
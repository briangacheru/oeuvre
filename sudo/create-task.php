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
        <div class="card-header">
            <div class="row flex-between-end">
                <div class="col-auto align-self-center">
                    <h5 class="mb-0" data-anchor="data-anchor">Task Details</h5>
                </div>
            </div>
        </div>
        <div class="card-body bg-body-tertiary">
            <div class="tab-content">
                <div class="tab-pane preview-tab-pane active" >
                    <form class="needs-validation" novalidate="novalidate" id="taskForm" method="post" action="submit-task" enctype="multipart/form-data">
                        <div class="card mb-3">
                            <div class="card-header bg-body-tertiary">
                                <h6 class="mb-0">Basic information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row gx-2">
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
                        </div>
                        <div class="card mb-3">
                            <div class="card-header bg-body-tertiary">
                                <h6 class="mb-0">Details</h6>
                            </div>
                            <div class="card-body">
                                <div class="row gx-2">
                                    <div class="col-12 mb-3">
                                        <label class="form-label" for="task-description">Task description:</label>
                                        <div  id="description"></div>
                                        <input type="hidden" name="description" id="description-input">
                                        <script>
                                            const quill = new Quill('#description', {
                                                theme: 'snow',
                                                modules: {
                                                    toolbar: {
                                                        container: [
                                                            ['bold', 'italic', 'underline', 'strike'],
                                                            ['blockquote', 'code-block'],
                                                            [{ 'header': 1 }, { 'header': 2 }],
                                                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                                            [{ 'script': 'sub'}, { 'script': 'super' }],
                                                            [{ 'indent': '-1'}, { 'indent': '+1' }],
                                                            [{ 'direction': 'rtl' }],
                                                            [{ 'size': ['small', false, 'large', 'huge'] }],
                                                            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                                                            [{ 'color': [] }, { 'background': [] }],
                                                            [{ 'font': [] }],
                                                            [{ 'align': [] }],
                                                            ['clean'],
                                                            ['link', 'image', 'video']
                                                        ],
                                                        handlers: {
                                                            link: function(value) {
                                                                if (value) {
                                                                    let href = prompt('Enter URL');
                                                                    if (href) {
                                                                        // Remove all quotes, whitespace, and accidental slashes
                                                                        let cleanUrl = href.trim()
                                                                            .replace(/^["']+|["']+$/g, '') // Remove leading/trailing quotes
                                                                            .replace(/^\/+|\/+$/g, '');    // Remove leading/trailing slashes
                                                                        this.quill.format('link', cleanUrl);
                                                                    }
                                                                } else {
                                                                    this.quill.format('link', false);
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            });

                                            document.getElementById('taskForm').addEventListener('submit', function(e) {
                                                // Clean the content before saving
                                                let content = quill.root.innerHTML;
                                                content = content.replace(/href="([^"]+)"/g, (match, url) => {
                                                    let cleanUrl = url.trim();
                                                    try { cleanUrl = decodeURIComponent(cleanUrl); } catch(e) {}
                                                    cleanUrl = cleanUrl
                                                        .replace(/^["']+|["']+$/g, '') // Remove quotes
                                                        .replace(/^\/+|\/+$/g, '')    // Remove slashes
                                                        .replace(/\\+|"+/g, '');      // Remove backslashes and extra quotes
                                                    return `href="${cleanUrl}"`;
                                                });
                                                document.getElementById('description-input').value = content;
                                            });
                                        </script>
                                        <div class="invalid-feedback">This field is required</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-header bg-body-tertiary">
                                <h6 class="mb-0">Add task file(s)</h6>
                            </div>
                            <div class="card-body">
                                <div id="dropArea" class="drop-area">
                                    <p>Drag and drop your files here or click to select files</p>
                                    <input name="taskfiles" id="fileInput" type="file" multiple="multiple" accept="*/*" style="display: none;"/>
                                    <button class="btn btn-outline-info me-1 mb-1" type="button" onclick="document.getElementById('fileInput').click()">Select Files</button>
                                </div>
                                <div id="fileList" class="file-list btn-outline-info">
                                    <ul id="fileNamesList"></ul>
                                </div>
                                <input type="hidden" name="uploadedFiles" id="uploadedFiles" value="">
                            </div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-body">
                                <div class="row justify-content-between align-items-center">
                                    <div class="col-md">
                                        <h5 class="mb-2 mb-md-0">You're almost done!</h5>
                                    </div>
                                    <div class="col-auto">
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
                            </div>
                        </div>
                    </form>
                </div>
            </div>
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
                    cppSelect.value = customInput.value;
                }
            }, true); // capture phase so it runs before other submit listeners
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropArea = document.getElementById('dropArea');
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

            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            // Highlight drop area when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, unhighlight, false);
            });

            // Handle dropped files
            dropArea.addEventListener('drop', handleDrop, false);

            // Add change event listener to file input for direct file selection
            document.getElementById('fileInput').addEventListener('change', function(e) {
                handleFiles(e.target.files);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight(e) {
                dropArea.classList.add('highlight');
            }

            function unhighlight(e) {
                dropArea.classList.remove('highlight');
            }

            function handleDrop(e) {
                var dt = e.dataTransfer;
                var files = dt.files;

                handleFiles(files);
            }

            function handleFiles(files) {
                files = [...files]; // Convert files to an array
                files.forEach(file => uploadFile(file));
            }

            async function uploadFile(file) {
                const url = 'upload'; // This now points to our new upload.php
                const formData = new FormData();
                formData.append('file', file);
                formData.append('action', 'upload');

                const li = document.createElement('li');
                li.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB) - Uploading: 0%`;
                li.style.color = '#FFA500';

                const progressBar = document.createElement('progress');
                progressBar.value = 0;
                progressBar.max = 100;
                li.appendChild(progressBar);

                const removeBtn = document.createElement('button');
                removeBtn.textContent = 'Remove';
                removeBtn.classList.add('btn', 'btn-outline-warning', 'btn-sm', 'ms-2');
                removeBtn.onclick = function () {
                    li.parentNode.removeChild(li);
                    const index = uploadedFilePaths.findIndex(f => f.fileName === file.name);
                    if (index > -1) {
                        const filePath = uploadedFilePaths[index].filePath;
                        deleteFileFromServer(filePath);
                        uploadedFilePaths.splice(index, 1);
                        updateUploadedFilesInput();
                    }
                };

                li.appendChild(removeBtn);
                document.getElementById('fileNamesList').appendChild(li);

                try {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', url, true);

                    xhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            progressBar.value = percentComplete;
                            li.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB) - Uploading: ${percentComplete.toFixed(2)}%`;
                            li.appendChild(progressBar);
                            li.appendChild(removeBtn);
                        }
                    });

                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            const response = JSON.parse(xhr.responseText);
                            if (response.status === 'success') {
                                const filePath = response.filePath;
                                const fileUrl = response.fileUrl;
                                const fileSize = response.fileSize;
                                li.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB) - Upload complete!`;
                                li.style.color = 'green';
                                li.appendChild(removeBtn);
                                uploadedFilePaths.push({ fileName: file.name, filePath: filePath, fileUrl: fileUrl, fileSize: fileSize });
                                updateUploadedFilesInput();
                            } else {
                                li.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB) - Upload failed: ${response.message}`;
                                li.style.color = 'red';
                            }
                        } else {
                            li.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB) - Upload error.`;
                            li.style.color = 'red';
                        }
                    };

                    xhr.send(formData);
                } catch (error) {
                    console.error('Error:', error);
                    li.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB) - Upload error.`;
                    li.style.color = 'red';
                }
            }

            async function deleteFileFromServer(filePath) {
                const url = 'delete_file'; // URL to the PHP file handling deletions
                const formData = new FormData();
                formData.append('filePath', filePath);
                formData.append('action', 'deleteFile');

                try {
                    const response = await fetch(url, {
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

            function updateUploadedFilesInput() {
                document.getElementById('uploadedFiles').value = JSON.stringify(uploadedFilePaths); // Update hidden input value
            }

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
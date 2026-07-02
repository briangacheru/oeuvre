<?php include "head.php";
require_once 'spaces-helper.php';

if (isset($_GET['task_id'])) {
    $encodedId = $_GET['task_id'];
    $taskId = base64_decode($encodedId);
} else {
    $_SESSION['alert'] ='<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
                                        <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                                        <p class="mb-0 flex-1">Invalid task ID!</p>
                                        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
}


// Define variables for task data
$taskTopic = $taskSubject = $taskAccount = $taskStatus = $taskConfirmation = $taskIsPaid = $taskDescription = $taskWriter = $taskDueDate  = $taskCPP = $taskDuplicate = $taskPages = $taskPublish = '';

// Retrieve the task data from the database
$sql2 = "SELECT * FROM tbltasks WHERE id='$taskId'";
$result = mysqli_query($con, $sql2);

if ($row = mysqli_fetch_array($result)) {
    $id = base64_encode($row["id"]);
    $taskTopic = $row["topic"];
    $taskSubject = $row["subject"];
    $taskAccount = $row["account"];
    $taskStatus = $row["status"];
    $taskConfirmation = $row["is_confirmed"];
    $taskIsPaid = $row["is_paid"];
    $taskDescription = $row["description"];
    $taskWriter = $row["writer"];
    $taskDueDate = $row["due_date"];
    $taskCPP = $row["cpp"];
    $taskPages = $row["pages"];
    $taskCreatedOn = $row["create_date"];
    $taskDuplicate = $row["is_duplicate"];
    $taskOriginalId = $row["original_task_id"] ?? null;
    $admin_acknowledged = $row["admin_acknowledged"];
    $acknowledged = $row["acknowledged"];
    $taskPublish = $row["publish"];
}
?>
    <title>iTasker | Edit Task #<?php  echo $taskId;?></title>
<?php include "navi.php";?>
    <div id="alert-container"></div>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Edit <span class="text-info fw-medium">Task #<?php  echo $taskId;?></span></h4>
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

<?php
// Show duplicate task notice with link to original
    if (isset($taskDuplicate) && $taskDuplicate == 1 && !empty($taskOriginalId)) {
    $originalTaskQuery = "SELECT topic FROM tbltasks WHERE id = ?";
    $originalStmt = mysqli_prepare($con, $originalTaskQuery);
    mysqli_stmt_bind_param($originalStmt, 'i', $taskOriginalId);
    mysqli_stmt_execute($originalStmt);
    $originalTaskResult = mysqli_stmt_get_result($originalStmt);

    if ($originalTaskData = mysqli_fetch_assoc($originalTaskResult)) {
        $encodedOriginalId = base64_encode($taskOriginalId);
        $originalTaskTopic = htmlspecialchars($originalTaskData['topic']);
        ?>
        <div class="bg-info-subtle border-start border-info border-3 rounded-3 py-2 ps-3 pe-2 mb-3">
            <div class="d-flex align-items-center">
                <span class="fas fa-copy text-info me-2"></span>
                <div>
                    <strong class="text-info">Duplicate Task</strong>
                    <p class="mb-0 small">
                        This is a duplicate of task:
                        <a href="view-task?task_id=<?php echo $encodedOriginalId; ?>" class="fw-semibold text-decoration-none">
                            #<?php echo $taskOriginalId; ?> - <?php echo $originalTaskTopic; ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
        }
    mysqli_stmt_close($originalStmt);
    }
?>

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
                    <form class="needs-validation" novalidate="novalidate" id="taskForm" method="post" action="update-task" enctype="multipart/form-data">
                        <div class="card mb-3">
                            <div class="card-header bg-body-tertiary">
                                <h6 class="mb-0">Basic information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row gx-2">
                                    <div class="col-12 mb-3">
                                        <label class="form-label" for="manufacturar-name">Topic:</label>
                                        <input type="hidden" name="taskId" value="<?php echo htmlspecialchars($taskId); ?>">
                                        <input class="form-control" name="topic" type="text" value="<?php  echo $taskTopic;?>" required="required" />
                                        <div class="invalid-feedback">This field is required</div>
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label" for="import-status">Subject: </label>
                                        <input class="form-control" name="subject" type="text" value="<?php  echo $taskSubject;?>" required="required" />
                                        <div class="invalid-feedback">This field is required</div>
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label" for="origin-country">Account: </label>
                                        <input class="form-control" name="account" type="text" value="<?php  echo $taskAccount;?>" required="required" />
                                        <div class="invalid-feedback">This field is required</div>
                                    </div>
                                    <div class="col-sm-4 mb-3">
                                        <label class="form-label" for="product-summary">Pages: </label>
                                        <input class="form-control"  type="number" name="pages" id="pages" min="0" step="0.5" value="<?php  echo $taskPages;?>" required="required"/>
                                        <div class="invalid-feedback">This field is required</div>
                                    </div>
                                    <div class="col-sm-4 mb-3">
                                        <label class="form-label" for="cpp">CPP: </label>
                                        <select class="form-select" id="cpp" name="cpp" required="required">
                                            <option value="375" <?php echo ($taskCPP == '375') ? 'selected' : ''; ?>>375</option>
                                            <option value="250" <?php echo ($taskCPP == '250') ? 'selected' : ''; ?>>250</option>
                                            <option value="300" <?php echo ($taskCPP == '300') ? 'selected' : ''; ?>>300</option>
                                            <option value="190" <?php echo ($taskCPP == '190') ? 'selected' : ''; ?>>190</option>
                                            <option value="350" <?php echo ($taskCPP == '350') ? 'selected' : ''; ?>>350</option>
                                            <option value="200" <?php echo ($taskCPP == '200') ? 'selected' : ''; ?>>200</option>
                                            <option value="400" <?php echo ($taskCPP == '400') ? 'selected' : ''; ?>>400</option>
                                            <option value="450" <?php echo ($taskCPP == '450') ? 'selected' : ''; ?>>450</option>
                                            <option value="500" <?php echo ($taskCPP == '500') ? 'selected' : ''; ?>>500</option>
                                            <option value="700" <?php echo ($taskCPP == '700') ? 'selected' : ''; ?>>700</option>
                                            <option value="750" <?php echo ($taskCPP == '750') ? 'selected' : ''; ?>>750</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 mb-3">
                                        <label class="form-label" for="publish">Publish: </label>
                                        <select class="form-select" id="publish" name="publish" required="required">
                                            <option value="1" <?php echo ($taskPublish == '1') ? 'selected' : ''; ?>>Yes (Published)</option>
                                            <option value="0" <?php echo ($taskPublish == '0') ? 'selected' : ''; ?>>No (Draft)</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label" for="dateTimepickerVal">Due Date & Time:</label>
                                        <input class="form-control" name="due_date" type="datetime-local" required="required"  value="<?php echo htmlspecialchars($taskDueDate, ENT_QUOTES); ?>" id="due_date" />
                                        <div class="invalid-feedback">This field is required</div>
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label" for="cpp">Confirmed: </label>
                                        <select class="form-select" id="is_confirmed" name="is_confirmed" required="required">
                                            <option value="0" <?php echo ($taskConfirmation == '0') ? 'selected' : ''; ?>>Yes</option>
                                            <option value="1" <?php echo ($taskConfirmation == '1') ? 'selected' : ''; ?>>No</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-12 mb-3">
                                        <label class="form-label" for="writerSearch">Select writer: </label>
                                        <div class="writer-dropdown-wrap" style="position:relative;">
                                            <input
                                                    type="text"
                                                    id="writerSearch"
                                                    class="form-control"
                                                    placeholder="Search writer..."
                                                    autocomplete="off"
                                                    value="<?php echo htmlspecialchars($taskWriter ?? ''); ?>"
                                            />
                                            <ul id="writerDropdown" class="list-unstyled mb-0" style="display:none;position:absolute;z-index:9999;width:100%;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #ced4da;border-radius:0 0 6px 6px;box-shadow:0 4px 12px rgba(0,0,0,.1);">
                                                <?php
                                                $query = mysqli_query($con, "SELECT id, username, email FROM tblwriters WHERE is_deleted = 0 AND is_verified=1 ORDER BY username ASC");
                                                while ($writer = mysqli_fetch_assoc($query)) {
                                                    $u = htmlspecialchars($writer['username']);
                                                    $e = htmlspecialchars($writer['email']);
                                                    echo "<li data-value=\"{$u}\" data-email=\"{$e}\" style=\"padding:8px 12px;cursor:pointer;\">{$u} <span style='color:#888;font-size:.85em;'>({$e})</span></li>";
                                                }
                                                ?>
                                            </ul>
                                        </div>
                                        <input type="hidden" name="writer" id="writerValue" value="<?php echo htmlspecialchars($taskWriter ?? ''); ?>" />
                                        <input type="hidden" name="email"  id="email"       value="<?php
                                        if (!empty($taskWriter)) {
                                            $writerEmailQuery = mysqli_query($con, "SELECT email FROM tblwriters WHERE username='".mysqli_real_escape_string($con, $taskWriter)."' LIMIT 1");
                                            if ($writerEmailRow = mysqli_fetch_assoc($writerEmailQuery)) {
                                                echo htmlspecialchars($writerEmailRow['email'] ?? '');
                                            }
                                        }
                                        ?>" />
                                        <div id="writerError" style="display:none;color:#dc3545;font-size:.875em;margin-top:.25rem;">Please select a writer.</div>
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label" for="product-summary">Status:</label>
                                        <select class="form-select" id="status" name="status" required="required">
                                            <option value="Draft" <?php echo ($taskStatus == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                                            <option value="In Progress" <?php echo ($taskStatus == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="In Revision" <?php echo ($taskStatus == 'In Revision') ? 'selected' : ''; ?>>In Revision</option>
                                            <option value="Submitted" <?php echo ($taskStatus == 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                                        </select>
                                        <div class="invalid-feedback">This field is required</div>
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label" for="cpp">Send email</label>
                                        <select class="form-select" id="sendEmail" name="sendEmail" required="required">
                                            <option value="1">Yes</option>
                                            <option value="0">No</option>
                                        </select>
                                    </div>
                                    <div class='col-sm-6 mb-3'>
                                        <label class='form-label' for='cpp'>Admin Acknowledged: </label>
                                        <select class='form-select' id='admin_acknowledged' name='admin_acknowledged' required='required'>
                                            <option value='1' <?php echo ($admin_acknowledged == '1') ? 'selected' : ''; ?>>Yes</option>
                                            <option value="0" <?php echo ($admin_acknowledged == '0') ? 'selected' : ''; ?>>No</option>
                                        </select>
                                    </div>
                                    <div class='col-sm-6 mb-3'>
                                        <label class='form-label' for='cpp'>Writer Acknowledged: </label>
                                        <select class='form-select' id='acknowledged' name='acknowledged' required='required'>
                                            <option value='1' <?php echo ($acknowledged == '1') ? 'selected' : ''; ?>>Yes</option>
                                            <option value="0" <?php echo ($acknowledged == '0') ? 'selected' : ''; ?>>No</option>
                                        </select>
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
                                        <div id="description"><?php echo ($taskDescription); ?></div>
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

                        <!-- Task Files card section -->
                        <div class='col mb-3'>
                            <div class='row g-3'>
                                <div class='col-xxl-12'>
                                    <div class='card h-100 h-xxl-auto mt-xxl-3'>
                                        <div class='card-header d-flex flex-between-center bg-body-tertiary py-2'>
                                            <h6 class='mb-0'>Task Files</h6>
                                        </div>
                                        <div class='card-body position-relative'>
                                            <div class='bg-holder bg-card d-none d-md-block'
                                                 style='background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);'></div>
                                            <?php
                                            // Get task files from new table
                                            $taskFilesQuery = "SELECT * FROM tbl_task_files WHERE task_id = ? AND file_type = 'task' AND is_deleted = 0 ORDER BY upload_time ASC";
                                            $stmt = mysqli_prepare($con, $taskFilesQuery);
                                            mysqli_stmt_bind_param($stmt, 'i', $taskId);
                                            mysqli_stmt_execute($stmt);
                                            $taskFilesResult = mysqli_stmt_get_result($stmt);

                                            if (mysqli_num_rows($taskFilesResult) > 0) {
                                                while ($fileRow = mysqli_fetch_assoc($taskFilesResult)) {
                                                    $fileName = $fileRow['original_file_name'];
                                                    $filePath = $fileRow['file_path'];
                                                    $fileUrl = $fileRow['file_url'];
                                                    $fileSize = $fileRow['file_size'];
                                                    $uploadTime = $fileRow['upload_time'];
                                                    $fileId = $fileRow['id'];
                                                    $formattedSize = formatFileSize($fileSize);
                                                    $formattedDate = date('d M Y, g:i A', strtotime($uploadTime));

                                                    // Determine thumbnail based on file extension
                                                    $thumbnailPath = '../assets/img/icons/docs.png';
                                                    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                                    switch (strtolower($fileExtension)) {
                                                        case 'pdf':
                                                            $thumbnailPath = '../assets/img/icons/pdf.png';
                                                            break;
                                                        case 'doc':
                                                        case 'docx':
                                                        case 'rtf':
                                                            $thumbnailPath = '../assets/img/icons/word.png';
                                                            break;
                                                        case 'xls':
                                                        case 'xlsx':
                                                        case 'csv':
                                                            $thumbnailPath = '../assets/img/icons/excel.png';
                                                            break;
                                                        case 'ppt':
                                                        case 'pptx':
                                                            $thumbnailPath = '../assets/img/icons/powerpoint.png';
                                                            break;
                                                        case 'mp4':
                                                        case 'avi':
                                                        case 'mov':
                                                        case 'mkv':
                                                        case 'wmv':
                                                        case 'flv':
                                                        case 'mpeg':
                                                        case 'mpg':
                                                        case '3gp':
                                                        case 'webm':
                                                        case 'm4v':
                                                            $thumbnailPath = '../assets/img/icons/mp4.png';
                                                            break;
                                                        case 'jpg':
                                                        case 'jpeg':
                                                        case 'png':
                                                        case 'gif':
                                                            $thumbnailPath = '../assets/img/icons/image.png';
                                                            break;
                                                        case 'zip':
                                                        case 'rar':
                                                            $thumbnailPath = '../assets/img/icons/zip.png';
                                                            break;
                                                        default:
                                                            $thumbnailPath = '../assets/img/icons/docs.png';
                                                            break;
                                                    }
                                                    ?>
                                                    <div class="d-flex mb-3 hover-actions-trigger align-items-center">
                                                        <div class="file-thumbnail">
                                                            <img class="border h-100 w-100 object-fit-cover rounded-2"
                                                                 src="<?php echo $thumbnailPath; ?>" alt=""/>
                                                        </div>
                                                        <div class="ms-3 flex-shrink-1 flex-grow-1">
                                                            <h6 class="mb-1">
                                                                <a class="stretched-link text-900 fw-semi-bold"
                                                                   href="<?php echo $fileUrl; ?>"><?php echo htmlspecialchars($fileName); ?></a>
                                                            </h6>
                                                            <div class="fs-10">
                                                                <span class="fw-medium text-600"><?php echo $formattedSize; ?></span>
                                                                <span class="fw-medium text-600 ms-2"><?php echo $formattedDate; ?></span>
                                                            </div>
                                                            <input type="hidden" name="existingFileIds[]"
                                                                   value="<?php echo $fileId; ?>">
                                                            <input type="hidden" id="removedFileIds" name="removedFileIds"
                                                                   value="">
                                                            <div class="hover-actions end-0 top-50 translate-middle-y">
                                                                <a class="btn btn-tertiary border-300 btn-sm me-1 text-600"
                                                                   data-bs-toggle="tooltip" data-bs-placement="top"
                                                                   title="Download" href="<?php echo $fileUrl; ?>"
                                                                   download="<?php echo htmlspecialchars($fileName); ?>">
                                                                    <img src="../assets/img/icons/cloud-download.svg" alt=""
                                                                         width="15"/>
                                                                </a>
                                                                <button class="btn btn-tertiary border-300 btn-sm me-1 text-600 shadow-none delete-btn"
                                                                        type="button" data-file-id="<?php echo $fileId; ?>"
                                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                                        title="Remove">
                                                                    <img src="../assets/img/icons/delete.svg" alt=""
                                                                         width="15"/>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <hr class="text-200"/>
                                                    <?php
                                                }
                                                mysqli_stmt_close($stmt);
                                            } else {
                                                echo '<div>No task files attached.</div>';
                                            }
                                            ?>
                                        </div>
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
                                    <input name="taskfiles[]" id="fileInput" type="file" multiple="multiple" accept="*/*" style="display: none;"/>
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
                                        <button class="btn btn-primary" name="save" type="submit" role="button" id="updateTaskButton">
                                            <span id="buttonText">Update Task</span>
                                            <span id="loadingText" class="d-none">
                                            Updating Task...
                                            <span id="loadingSpinner" class="spinner-border spinner-border-sm me-2" role="status"></span>
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
        document.addEventListener('DOMContentLoaded', function() {
            const form          = document.getElementById('taskForm');
            const discardButton = document.getElementById('discardButton');
            const writerSearch  = document.getElementById('writerSearch');
            const writerValue   = document.getElementById('writerValue');
            const emailInput    = document.getElementById('email');
            const dropdown      = document.getElementById('writerDropdown');
            const writerError   = document.getElementById('writerError');
            const allItems      = Array.from(dropdown.querySelectorAll('li'));

            // Highlight hover
            allItems.forEach(function (li) {
                li.addEventListener('mouseenter', () => li.style.background = '#f0f4ff');
                li.addEventListener('mouseleave', () => li.style.background = '');
            });

            // Filter list as user types
            writerSearch.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                let anyVisible = false;
                allItems.forEach(function (li) {
                    const match = li.textContent.toLowerCase().includes(q);
                    li.style.display = match ? '' : 'none';
                    if (match) anyVisible = true;
                });
                dropdown.style.display = anyVisible ? 'block' : 'none';
                if (this.value === '') {
                    writerValue.value = '';
                    emailInput.value  = '';
                }
            });

            // Show full list on focus
            writerSearch.addEventListener('focus', function () {
                allItems.forEach(li => li.style.display = '');
                dropdown.style.display = 'block';
            });

            // Select a writer — mousedown so it fires before blur
            dropdown.addEventListener('mousedown', function (e) {
                const li = e.target.closest('li');
                if (!li) return;
                e.preventDefault();
                writerSearch.value  = li.dataset.value;
                writerValue.value   = li.dataset.value;
                emailInput.value    = li.dataset.email;
                dropdown.style.display = 'none';
                writerError.style.display = 'none';
            });

            // Close dropdown when clicking outside; revert search text if no match chosen
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.writer-dropdown-wrap')) {
                    dropdown.style.display = 'none';
                    if (writerValue.value && writerSearch.value !== writerValue.value) {
                        writerSearch.value = writerValue.value;
                    }
                }
            });

            // Block submit if no writer chosen
            form.addEventListener('submit', function (e) {
                if (!writerValue.value) {
                    e.preventDefault();
                    writerError.style.display = 'block';
                    writerSearch.focus();
                } else {
                    writerError.style.display = 'none';
                }
            });

            // Discard resets the form
            discardButton.addEventListener('click', function() {
                form.reset();
                window.scrollTo(0, 0);
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropArea = document.getElementById('dropArea');
            const form = document.getElementById('taskForm');
            const updateTaskButton = document.getElementById('updateTaskButton');
            const buttonText = document.getElementById('buttonText');
            const loadingText = document.getElementById('loadingText');
            const fileContainer = document.querySelector('.card-body');
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

            // Handle existing file removal
            fileContainer.addEventListener('click', function(e) {
                if (e.target.closest('.delete-btn')) {
                    e.preventDefault();
                    const fileId = e.target.closest('.delete-btn').getAttribute('data-file-id');
                    removeFileReference(fileId, e.target.closest('.d-flex'));
                }
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
                const url = 'upload_update'; // Point to the new Digital Ocean upload handler
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
                                li.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB) - Upload complete!`;
                                li.style.color = 'green';
                                li.appendChild(removeBtn);
                                uploadedFilePaths.push({
                                    fileName: file.name,
                                    filePath: filePath,
                                    fileUrl: fileUrl,
                                    fileSize: response.fileSize || file.size
                                });
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

            async function removeFileReference(fileId, elementToRemove) {
                if (confirm('Are you sure you want to remove this file from the task?')) {
                    try {
                        // Show loading state on delete button
                        const deleteBtn = elementToRemove.querySelector('.delete-btn');
                        const originalContent = deleteBtn.innerHTML;
                        deleteBtn.disabled = true;
                        deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

                        // Make AJAX call to delete file immediately
                        const formData = new FormData();
                        formData.append('action', 'delete_file');
                        formData.append('file_id', fileId);

                        const response = await fetch('update-task', {
                            method: 'POST',
                            body: formData
                        });

                        const responseText = await response.text();
                        console.log('Delete response:', responseText);

                        // Try to parse JSON from response
                        const jsonMatch = responseText.match(/(\{.*\})$/s);
                        if (jsonMatch && jsonMatch[1]) {
                            const data = JSON.parse(jsonMatch[1]);

                            if (data.status === 'success') {
                                // Remove the element from DOM
                                elementToRemove.remove();
                                displayBootstrapAlert('File removed successfully!', 'success');
                            } else {
                                displayBootstrapAlert(`Failed to remove file: ${data.message}`, 'danger');
                                // Reset button state
                                deleteBtn.disabled = false;
                                deleteBtn.innerHTML = originalContent;
                            }
                        } else {
                            throw new Error('Invalid server response');
                        }
                    } catch (error) {
                        console.error('Error removing file:', error);
                        displayBootstrapAlert('Error removing file. Please try again.', 'danger');
                        // Reset button state
                        const deleteBtn = elementToRemove.querySelector('.delete-btn');
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = '<img src="../assets/img/icons/delete.svg" alt="" width="15" />';
                    }
                }
            }

            function updateUploadedFilesInput() {
                document.getElementById('uploadedFiles').value = JSON.stringify(uploadedFilePaths);
            }

            form.addEventListener('submit', async function(e) {
                e.preventDefault(); // Prevent the default form submission

                // Show loading state
                updateTaskButton.disabled = true;
                buttonText.classList.add('d-none');
                loadingText.classList.remove('d-none');

                // Example validation check
                if (!form.checkValidity()) {
                    displayBootstrapAlert('Please fill in all required fields.', 'danger');
                    resetButton();
                    return;
                }

                document.getElementById('uploadedFiles').value = JSON.stringify(uploadedFilePaths);
                handleSubmit();
            });

            async function handleSubmit() {
                const formData = new FormData(form);
                formData.append('action', 'submitForm');

                try {
                    const response = await fetch('update-task', {
                        method: 'POST',
                        body: formData,
                    });

                    // Get the raw text response
                    const responseText = await response.text();
                    console.log("Raw server response:", responseText);

                    // Extract the JSON part from the response
                    const jsonMatch = responseText.match(/(\{.*\})$/s);

                    if (jsonMatch && jsonMatch[1]) {
                        try {
                            const data = JSON.parse(jsonMatch[1]);

                            if (data.status === 'success') {
                                // TRIGGER FIREWORKS ON SUCCESS!
                                triggerFireworks();

                                let fullMessage = 'Task updated successfully!';

                                // Check if there's email status information in the response
                                if (data.emailStatus) {
                                    if (data.emailStatus.includes('successfully')) {
                                        fullMessage += ' Email sent successfully.';
                                    } else {
                                        fullMessage += ' ' + data.emailStatus;
                                    }
                                }

                                // Show success message with fireworks
                                displayBootstrapAlert(fullMessage, 'success');

                                // Delay the redirect to let users enjoy the fireworks
                                setTimeout(() => {
                                    window.location.href = `view-task?task_id=${data.task_id}`;
                                }, 5500);
                            } else if (data.status === 'error') {
                                displayBootstrapAlert(`Failed to update the task: ${data.message}`, 'danger');
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
                    displayBootstrapAlert(`An error occurred while updating the task: ${error.message}`, 'danger');
                    resetButton();
                }
            }

            function resetButton() {
                updateTaskButton.disabled = false;
                buttonText.classList.remove('d-none');
                loadingText.classList.add('d-none');
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
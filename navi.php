
<!-- ===============================================-->
<!--    Favicons-->
<!-- ===============================================-->
<link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon-16x16.png">
<link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
<link rel="manifest" href="assets/img/favicons/manifest.json">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.0/font/bootstrap-icons.css" />
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons" />
<meta name="msapplication-TileImage" content="assets/img/favicons/mstile-150x150.png">
<meta name="theme-color" content="#ffffff">
<script src="vendors/jquery/jquery.min.js"></script>
<script src="assets/js/config.js"></script>
<script src="vendors/simplebar/simplebar.min.js"></script>



<!-- ===============================================-->
<!--    Stylesheets-->
<!-- ===============================================-->
<link href="vendors/glightbox/glightbox.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
<link href="vendors/simplebar/simplebar.min.css" rel="stylesheet">
<link href="assets/css/theme-rtl.css" rel="stylesheet" id="style-rtl">
<link href="assets/css/theme.css" rel="stylesheet" id="style-default">
<link href="assets/css/user-rtl.css" rel="stylesheet" id="user-style-rtl">
<link href="assets/css/user.css" rel="stylesheet" id="user-style-default">
<script src="vendors/datatables.net-bs5/dataTables.bootstrap5.min.css"></script>
<script src="vendors/select2/select2.min.css"></script>
<script src="vendors/select2-bootstrap-5-theme/select2-bootstrap-5-theme.min.css"></script>
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<link href="vendors/flatpickr/flatpickr.min.css" rel="stylesheet" />
<link href="vendors/dropzone/dropzone.css" rel="stylesheet" />
<style>
    .drop-area {
        border: 2px dashed #ccc;
        border-radius: 20px;
        width: auto;
        padding: 20px;
        text-align: center;
        cursor: pointer;
    }
    .drop-area:hover {
        border-color: #333;
    }
    .drop-area.highlight {
        background-color: #f0f0f0; /* Light grey background */
        border: 2px dashed #ff9900; /* Dashed border in warning color */
    }

    .highlighted-link:hover {
        color: #0056b3;
    }

    /* Fix for text overflow */
    .task-description-content {
        word-wrap: break-word;
        word-break: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        max-width: 100%;
    }

    /* Specific handling for URLs */
    .highlighted-link {
        word-break: break-all;
        color: #0d6efd;
        text-decoration: underline;
        display: inline-block;
        max-width: 100%;
        line-height: 1.4;
    }

    /* Ensure the container doesn't overflow */
    .card-body {
        overflow-x: hidden;
    }

    /* Mobile-specific adjustments */
    @media (max-width: 576px) {
        .task-description-content {
            font-size: 0.85rem;
        }

        .highlighted-link {
            font-size: 0.8rem;
        }
    }

    .file-list ul {
        list-style-type: none;
        padding: 0;
    }

    .file-list li {
        margin: 10px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .file-list button {
        margin-left: 20px;

    }
    .progress-bar {
        width: 0;
        height: 20px;
        background-color: green;
        text-align: center;
        color: white;
    }
    .custom-toast {
        position: fixed !important;
        top: 20px !important;
        right: 20px !important;
        min-width: 300px !important;
        background: white !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        z-index: 99999 !important;
        animation: slideInRight 0.3s ease-out !important;
        border-left: 4px solid #28a745 !important;
        display: block !important;
        visibility: visible !important;
    }

    .toast-success {
        border-left-color: #28a745 !important;
    }

    .toast-error {
        border-left-color: #dc3545 !important;
    }

    .toast-content {
        display: flex !important;
        align-items: center !important;
        padding: 15px !important;
    }

    .toast-icon {
        font-size: 20px !important;
        margin-right: 10px !important;
    }

    .toast-message {
        flex: 1 !important;
        font-weight: 500 !important;
        color: #333 !important;
    }

    .toast-close {
        background: none !important;
        border: none !important;
        font-size: 18px !important;
        cursor: pointer !important;
        color: #999 !important;
        margin-left: 10px !important;
    }

    .toast-close:hover {
        color: #666 !important;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .table-active {
        border-left: 4px solid #28a745 !important; /* Yellow left border */
    }

    #notificationToggle {
        position: relative;
    }

    #notificationToggle:hover {
        color: #0d6efd !important;
    }

    /* Notification permission indicator */
    .notification-enabled::after {
        content: '';
        position: absolute;
        top: 8px;
        right: 8px;
        width: 8px;
        height: 8px;
        background-color: #28a745;
        border-radius: 50%;
        border: 2px solid white;
    }
</style>

<script>
    var isRTL = JSON.parse(localStorage.getItem('isRTL'));
    if (isRTL) {
        var linkDefault = document.getElementById('style-default');
        var userLinkDefault = document.getElementById('user-style-default');
        linkDefault.setAttribute('disabled', true);
        userLinkDefault.setAttribute('disabled', true);
        document.querySelector('html').setAttribute('dir', 'rtl');
    } else {
        var linkRTL = document.getElementById('style-rtl');
        var userLinkRTL = document.getElementById('user-style-rtl');
        linkRTL.setAttribute('disabled', true);
        userLinkRTL.setAttribute('disabled', true);
    }
</script>
</head>
<body>

<!-- ===============================================-->
<!--    Main Content-->
<!-- ===============================================-->
<main class="main" id="top">
    <div class="container" data-layout="container">
        <script>
            var isFluid = JSON.parse(localStorage.getItem('isFluid'));
            if (isFluid) {
                var container = document.querySelector('[data-layout]');
                container.classList.remove('container');
                container.classList.add('container-fluid');
            }
        </script>
        <nav class="navbar navbar-light navbar-vertical navbar-expand-xl">
            <script>
                var navbarStyle = localStorage.getItem("navbarStyle");
                if (navbarStyle && navbarStyle !== 'transparent') {
                    document.querySelector('.navbar-vertical').classList.add(`navbar-${navbarStyle}`);
                }
            </script>
            <div class="d-flex align-items-center">
                <div class="toggle-icon-wrapper">

                    <button class="btn navbar-toggler-humburger-icon navbar-vertical-toggle" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-toggle="tooltip" data-bs-placement="top" title="Toggle Navigation"><span class="navbar-toggle-icon"><span class="toggle-line"></span></span></button>

                </div><a class="navbar-brand" href="index">
                    <div class="d-flex align-items-center py-3"><img class="me-2" src="assets/img/icons/spot-illustrations/itasker.png" alt="" width="40" /><span class="font-sans-serif text-primary">i<span class="text-info">Tasker</span></span>
                    </div>
                </a>
            </div>

            <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
                <div class="navbar-vertical-content scrollbar">
                    <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
                        <!-- parent pages--><a class="nav-link" href="index" role="button">
                            <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-home "></span></span><span class="nav-link-text ps-1">Dashboard</span>
                            </div>
                        </a>
                        <?php
                        $aid=$_SESSION['sessionWriter'];
                        $sql="SELECT * from  tblwriters where email=:aid";
                        $query = $dbh -> prepare($sql);
                        $query->bindParam(':aid',$aid,PDO::PARAM_STR);
                        $query->execute();
                        $results=$query->fetchAll(PDO::FETCH_OBJ);
                        $cnt=1;
                        if($query->rowCount() > 0)
                        {
                        foreach($results as $row)
                        {
                        if($row->is_verified == 1)
                        {
                        ?>
                        <li class="nav-item">
                            <!-- label-->
                            <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                <div class="col-auto navbar-vertical-label">TASKS
                                </div>
                                <div class="col ps-0">
                                    <hr class="mb-0 navbar-vertical-divider" />
                                </div>
                            </div>
                            <!-- parent pages--><a class="nav-link" href="all-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-database"></span></span><span class="nav-link-text ps-1">All Tasks</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-success"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE email = '$aid' AND status != 'Draft'  ";
                                        $result = mysqli_query($con, $query);
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $count = $row['taskCount'];
                                            // Check if count is greater than 0
                                            if ($count > 0) {
                                                echo $count; // Display the count
                                            } else {
                                                echo "0"; // Display "No Data" if count is 0
                                            }
                                        }
                                        ?></span>
                                </div>
                            </a>

                            <!-- parent pages--><a class="nav-link" href="unconfirmed" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-question-circle"></span></span><span class="nav-link-text ps-1">Unconfirmed</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-primary"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_confirmed = 1 AND email = '$aid'";
                                        $result = mysqli_query($con, $query);
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $count = $row['taskCount'];
                                            // Check if count is greater than 0
                                            if ($count > 0) {
                                                echo $count; // Display the count
                                            } else {
                                                echo "0"; // Display "No Data" if count is 0
                                            }
                                        }
                                        ?></span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="tasks-in-progress" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-spinner"></span></span><span class="nav-link-text ps-1">In Progress</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-warning"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND email = '$aid'";
                                        $result = mysqli_query($con, $query);
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $count = $row['taskCount'];
                                            // Check if count is greater than 0
                                            if ($count > 0) {
                                                echo $count; // Display the count
                                            } else {
                                                echo "0"; // Display "No Data" if count is 0
                                            }
                                        }
                                        ?></span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="tasks-in-revision" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-flag"></span></span><span class="nav-link-text ps-1">In Revision</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-primary"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Revision'  AND email = '$aid'";
                                        $result = mysqli_query($con, $query);
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $count = $row['taskCount'];
                                            // Check if count is greater than 0
                                            if ($count > 0) {
                                                echo $count; // Display the count
                                            } else {
                                                echo "0"; // Display "No Data" if count is 0
                                            }
                                        }
                                        ?></span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="submitted-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-check"></span></span><span class="nav-link-text ps-1">Submitted</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-info"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted' AND email = '$aid'";
                                        $result = mysqli_query($con, $query);
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $count = $row['taskCount'];
                                            // Check if count is greater than 0
                                            if ($count > 0) {
                                                echo $count; // Display the count
                                            } else {
                                                echo "0"; // Display "No Data" if count is 0
                                            }
                                        }
                                        ?></span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="completed-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-check-double"></span></span><span class="nav-link-text ps-1">Completed</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-success"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND email = '$aid'";
                                        $result = mysqli_query($con, $query);
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $count = $row['taskCount'];
                                            // Check if count is greater than 0
                                            if ($count > 0) {
                                                echo $count; // Display the count
                                            } else {
                                                echo "0"; // Display "No Data" if count is 0
                                            }
                                        }
                                        ?></span>
                                </div>
                            </a>

                            <!-- label-->
                            <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                <div class="col-auto navbar-vertical-label">APPS
                                </div>
                                <div class="col ps-0">
                                    <hr class="mb-0 navbar-vertical-divider" />
                                </div>
                            </div>
                            <!-- parent pages--><a class="nav-link" href="calendar" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-calendar"></span></span><span class="nav-link-text ps-1">Calendar</span>
                                </div>
                            </a>
                            <!-- parent pages-->
                            <a class="nav-link" href="chat" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-comments"></span></span><span class="nav-link-text ps-1">Chat</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-info"><?php echo $unreadMessagesCount?></span>
                                </div>
                            </a>

                            <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                <div class="col-auto navbar-vertical-label">PAYMENT
                                </div>
                                <div class="col ps-0">
                                    <hr class="mb-0 navbar-vertical-divider" />
                                </div>
                            </div>
                            <!-- parent pages--><a class="nav-link" href="unpaid-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fab fa-creative-commons-nc"></span></span><span class="nav-link-text ps-1">Unpaid</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-warning"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed' AND email = '$aid'";
                                        $result = mysqli_query($con, $query);
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $count = $row['taskCount'];
                                            // Check if count is greater than 0
                                            if ($count > 0) {
                                                echo $count; // Display the count
                                            } else {
                                                echo "0"; // Display "No Data" if count is 0
                                            }
                                        }
                                        ?></span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="paid-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="far fa-credit-card"></span></span><span class="nav-link-text ps-1">Paid</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-success"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND is_paid = 1 AND email = '$aid'";
                                        $result = mysqli_query($con, $query);
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $count = $row['taskCount'];
                                            // Check if count is greater than 0
                                            if ($count > 0) {
                                                echo $count; // Display the count
                                            } else {
                                                echo "0"; // Display "No Data" if count is 0
                                            }
                                        }
                                        ?></span>
                                </div>
                            </a>

                            <!-- label-->
                            <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                <div class="col-auto navbar-vertical-label">OVERDRAFTS
                                </div>
                                <div class="col ps-0">
                                    <hr class="mb-0 navbar-vertical-divider" />
                                </div>
                            </div>
                            <!-- parent pages--><a class="nav-link" href="overdraft" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-donate"></span></span><span class="nav-link-text ps-1">Overdrafts</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="settled-overdrafts" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-stamp"></span></span><span class="nav-link-text ps-1">Settled</span>
                                </div>
                            </a>
                            <?php
                            $_showInvoiceLink = false;
                            if (!empty($_SESSION['sessionWriter'])) {
                                $_invEmail = $_SESSION['sessionWriter'];
                                $_invStmt  = mysqli_prepare($con,
                                    "SELECT username FROM tblwriters WHERE email = ? AND is_deleted = 0 LIMIT 1"
                                );
                                if ($_invStmt) {
                                    mysqli_stmt_bind_param($_invStmt, 's', $_invEmail);
                                    mysqli_stmt_execute($_invStmt);
                                    $_invResult = mysqli_stmt_get_result($_invStmt);
                                    $_invRow    = mysqli_fetch_assoc($_invResult);
                                    $_invWriter = $_invRow['username'] ?? '';
                                    mysqli_stmt_close($_invStmt);

                                    if (!empty($_invWriter)) {
                                        $_invCheck = mysqli_prepare($con,
                                            "SELECT id FROM tbl_invoice_logs WHERE writer_name = ? LIMIT 1"
                                        );
                                        if ($_invCheck) {
                                            mysqli_stmt_bind_param($_invCheck, 's', $_invWriter);
                                            mysqli_stmt_execute($_invCheck);
                                            mysqli_stmt_store_result($_invCheck);
                                            $_showInvoiceLink = (mysqli_stmt_num_rows($_invCheck) > 0);
                                            mysqli_stmt_close($_invCheck);
                                        }
                                    }
                                }
                            }
                            ?>
                            <?php if ($_showInvoiceLink): ?>
                                <!-- parent pages -->
                                <a class="nav-link" href="invoice-logs" role="button">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-receipt"></span></span>
                                        <span class="nav-link-text ps-1">Invoices</span>
                                    </div>
                                </a>
                            <?php endif; ?>
                            <!-- label-->
                            <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                <div class="col-auto navbar-vertical-label">MANAGEMENT
                                </div>
                                <div class="col ps-0">
                                    <hr class="mb-0 navbar-vertical-divider" />
                                </div>
                            </div>

                            <?php
                            }
                            }
                            } ?>

                            <!-- parent pages--><a class="nav-link" href="profile" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-wrench"></span></span><span class="nav-link-text ps-1">Profile</span>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>



        <div class="content">
            <nav class="navbar navbar-light navbar-glass navbar-top navbar-expand">

                <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation"><span class="navbar-toggle-icon"><span class="toggle-line"></span></span></button>
                <a class="navbar-brand me-1 me-sm-3" href="index">
                    <div class="d-flex align-items-center"><img class="me-2" src="assets/img/icons/spot-illustrations/itasker.png" alt="" width="40" /><span class="font-sans-serif text-primary">i</span><span class="font-sans-serif text-info">Tasker</span>
                    </div>
                </a>
                <ul class="navbar-nav align-items-center d-none d-lg-block">
                    <li class="nav-item">
                        <div class="search-box" data-list='{"valueNames":["title"]}'>
                            <form class="position-relative" data-bs-toggle="search" data-bs-display="static">
                                <input class="form-control search-input fuzzy-search" type="search" placeholder="Search..." aria-label="Search" />
                                <span class="fas fa-search search-box-icon"></span>

                            </form>
                            <div class="btn-close-falcon-container position-absolute end-0 top-50 translate-middle shadow-none" data-bs-dismiss="search">
                                <button class="btn btn-link btn-close-falcon p-0" aria-label="Close"></button>
                            </div>
                        </div>
                    </li>
                </ul>

                <ul class="navbar-nav navbar-nav-icons ms-auto flex-row align-items-center">
                    <li class="nav-item ps-2 pe-0">
                        <div class="dropdown theme-control-dropdown"><a class="nav-link d-flex align-items-center dropdown-toggle fa-icon-wait fs-9 pe-1 py-0" href="#" role="button" id="themeSwitchDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="fas fa-sun fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="light"></span><span class="fas fa-moon fs-7" data-fa-transform="shrink-3" data-theme-dropdown-toggle-icon="dark"></span><span class="fas fa-adjust fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="auto"></span></a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-caret border py-0 mt-3" aria-labelledby="themeSwitchDropdown">
                                <div class="bg-white dark__bg-1000 rounded-2 py-2">
                                    <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="light" data-theme-control="theme"><span class="fas fa-sun"></span>Light<span class="fas fa-check dropdown-check-icon ms-auto text-600"></span></button>
                                    <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="dark" data-theme-control="theme"><span class="fas fa-moon" data-fa-transform=""></span>Dark<span class="fas fa-check dropdown-check-icon ms-auto text-600"></span></button>
                                    <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="auto" data-theme-control="theme"><span class="fas fa-adjust" data-fa-transform=""></span>Auto<span class="fas fa-check dropdown-check-icon ms-auto text-600"></span></button>
                                </div>
                            </div>
                        </div>
                    </li>

                    <li class='nav-item dropdown'>
                        <?php
                        $aid = $_SESSION['sessionWriter'];

                        // Query to count new assigned tasks (not yet acknowledged)
                        $newTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS new_task_count FROM tbltasks WHERE is_deleted = 0 AND (status = 'In Progress' OR is_confirmed = 1) AND email = '$aid' AND acknowledged = 0");
                        $newTasksCountResult = mysqli_fetch_assoc($newTasksCountQuery);
                        $newTasksCount = $newTasksCountResult['new_task_count'];

                        // Query to count late tasks
                        $lateTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS late_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW() AND email = '$aid'");
                        $lateTasksCountResult = mysqli_fetch_assoc($lateTasksCountQuery);
                        $lateTasksCount = $lateTasksCountResult['late_task_count'];

                        // Total task notifications
                        $totalTaskNotifications = $newTasksCount + $lateTasksCount;

                        // Query to fetch new tasks details
                        $newTasksQuery = mysqli_query($con, "SELECT *, 'new' as notification_type FROM tbltasks WHERE is_deleted = 0 AND (status = 'In Progress' OR is_confirmed = 1) AND email = '$aid' AND acknowledged = 0 ORDER BY create_date DESC LIMIT 3");
                        $newTasks = [];
                        while ($task = mysqli_fetch_assoc($newTasksQuery)) {
                            $newTasks[] = $task;
                        }

                        // Query to fetch late tasks details
                        $lateTasksQuery = mysqli_query($con, "SELECT *, 'late' as notification_type FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW() AND email = '$aid' ORDER BY due_date ASC LIMIT 3");
                        $lateTasks = [];
                        while ($task = mysqli_fetch_assoc($lateTasksQuery)) {
                            // Calculate overdue time
                            $dueDate = new DateTime($task['due_date']);
                            $now = new DateTime();
                            $interval = $now->diff($dueDate);
                            $task['time_overdue'] = $interval->format('%a days %h hours');
                            $lateTasks[] = $task;
                        }

                        // Combine and sort all tasks by priority (late tasks first, then new tasks)
                        $allTaskNotifications = array_merge($lateTasks, $newTasks);
                        ?>

                        <a class="nav-link notification-indicator notification-indicator-warning px-0 fa-icon-wait"
                           id="navbarDropdownTasks" role="button" data-bs-toggle="dropdown" aria-haspopup="true"
                           aria-expanded="false">
                            <span class="fas fa-tasks" data-fa-transform="shrink-6" style="font-size: 33px;"></span>
                            <?php if ($totalTaskNotifications > 0): ?>
                                <span class="notification-indicator-number"><?php echo $totalTaskNotifications; ?></span>
                            <?php endif; ?>
                        </a>

                        <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-menu-notification dropdown-caret-bg"
                             aria-labelledby="navbarDropdownTasks">
                            <div class="card card-notification shadow-none">
                                <div class="card-header">
                                    <div class="row justify-content-between align-items-center">
                                        <div class="col-auto">
                                            <h6 class="card-header-title mb-0 text-warning">Task Notifications</h6>
                                        </div>
                                    </div>
                                </div>

                                <div class="scrollbar-overlay" style="max-height:19rem">
                                    <div class="list-group list-group-flush fw-normal fs-10">

                                        <?php if ($lateTasksCount > 0): ?>
                                            <div class="list-group-title border-bottom text-danger">
                                                <span class="fas fa-exclamation-triangle me-2"></span>You
                                                have <?php echo $lateTasksCount; ?> overdue tasks.
                                            </div>
                                            <?php foreach ($lateTasks as $key => $task): ?>
                                                <div class="list-group-item">
                                                    <?php $encodedId = base64_encode($task['id']); ?>
                                                    <a class="notification notification-flush notification-unread"
                                                       href="view-task?task_id=<?php echo htmlspecialchars($encodedId); ?>">
                                                        <div class="notification-avatar">
                                                            <div class="avatar avatar-2xl me-3">
                                                                <span class="material-icons text-danger fs-3">dangerous</span>
                                                            </div>
                                                        </div>
                                                        <div class="notification-body">
                                                            <p class="mb-1 text-danger">
                                                                <strong>Task <?php echo $task['id']; ?>:</strong>
                                                                <?php echo htmlspecialchars(substr($task['topic'], 0, 35)) . (strlen($task['topic']) > 35 ? '...' : ''); ?>
                                                            </p>
                                                            <span class="notification-time text-danger">
                                            <span class="me-2" role="img" aria-label="Time">⏰</span>
                                            Overdue by <?php echo $task['time_overdue']; ?>
                                        </span>
                                                        </div>
                                                    </a>
                                                </div>
                                                <?php if ($key >= 2) break; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if ($newTasksCount > 0): ?>
                                            <div class="list-group-title border-bottom text-success">
                                                <span class="fas fa-plus-circle me-2"></span>You
                                                have <?php echo $newTasksCount; ?> new assigned tasks.
                                            </div>
                                            <?php foreach ($newTasks as $key => $task): ?>
                                                <div class="list-group-item">
                                                    <?php $encodedId = base64_encode($task['id']); ?>
                                                    <a class="notification notification-flush notification-unread"
                                                       href="view-task?task_id=<?php echo htmlspecialchars($encodedId); ?>"
                                                       onclick="markTaskAsRead(<?php echo $task['id']; ?>)">
                                                        <div class="notification-avatar">
                                                            <div class="avatar avatar-2xl me-3">
                                                                <span class="material-icons text-success fs-3">assignment</span>
                                                            </div>
                                                        </div>
                                                        <div class="notification-body">
                                                            <p class="mb-1 text-success">
                                                                <strong>Task <?php echo $task['id']; ?>:</strong>
                                                                <?php echo htmlspecialchars(substr($task['topic'], 0, 35)) . (strlen($task['topic']) > 35 ? '...' : ''); ?>
                                                            </p>
                                                            <?php $dueInfo = timeDueIn($task['due_date'], 31, true); ?>
                                                            <span class='notification-time <?php echo $dueInfo['class']; ?>'
                                                                  title="<?php echo date('M j, Y g:i A', strtotime($task['due_date'])); ?>">
                                                                <span class='me-2' role='img' aria-label='Time'>📅</span>
                                                                <?php echo $dueInfo['text']; ?>
                                                            </span>
                                                        </div>
                                                    </a>
                                                </div>
                                                <?php if ($key >= 2) break; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if ($totalTaskNotifications == 0): ?>
                                            <div class="list-group-item text-center text-muted py-4">
                                                <span class="fas fa-check-circle me-2 text-success"></span>
                                                <p class="mb-0">All tasks are up to date!</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer text-center border-top">
                                    <a class="card-link d-block" href="tasks-in-progress">View all tasks</a>
                                </div>
                            </div>
                        </div>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link notification-indicator notification-indicator-info px-0 fa-icon-wait" id="navbarDropdownNotification" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-hide-on-body-scroll="data-hide-on-body-scroll">
                            <span class="fas fa-comment" data-fa-transform="shrink-6" style="font-size: 33px;"></span>
                            <span class="notification-indicator-number"><?php echo $unreadMessagesCount; ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-menu-notification dropdown-caret-bg" aria-labelledby="navbarDropdownNotification">
                            <div class="card card-notification shadow-none">
                                <div class="card-header">
                                    <div class="row justify-content-between align-items-center">
                                        <div class="col-auto">
                                            <h6 class="card-header-title mb-0 text-primary">Messages</h6>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                $sql = "SELECT * FROM tblwriters WHERE email='$aid'";
                                $query = $dbh->prepare($sql);
                                $query->execute();
                                $results = $query->fetchAll(PDO::FETCH_OBJ);
                                $cnt = 1;

                                if ($query->rowCount() > 0) {
                                foreach ($results as $row) {
                                if ($row->is_verified == 1) {
                                ?>
                                <div class="scrollbar-overlay" style="max-height:19rem">
                                    <div class="list-group list-group-flush fw-normal fs-10">
                                        <div class="list-group-title border-bottom text-info">You have <?php echo $unreadMessagesCount; ?> unread messages.</div>
                                        <?php
                                        // Display each unread message
                                        foreach ($unreadMessages as $key => $message) {
                                            // Fetch sender details from tblwriters
                                            $senderID = $message['sender_id'];
                                            $senderQuery = mysqli_query($con, "SELECT username, Photo FROM tbladmin WHERE id = '$senderID'");
                                            $senderResult = mysqli_fetch_assoc($senderQuery);

                                            $receivedDate = new DateTime($message['timestamp']);
                                            $now = new DateTime();
                                            $interval = $now->diff($receivedDate);
                                            $unreadMessages[$key]['time_received'] = $interval->format('%a days %h hours %i minutes');
                                            if ($key >= 9) break; // Limit to only 10 messages
                                        }
                                        ?>
                                        <?php foreach ($unreadMessages as $key => $message): ?>
                                            <div class="list-group-item">
                                                <?php
                                                $encodedId = base64_encode($message['id']);
                                                $senderName = $senderResult['username'];
                                                $senderPhoto = $senderResult['Photo'];
                                                ?>
                                                <a class="notification notification-flush notification-unread" href="chat?message_id=<?php echo htmlspecialchars($encodedId); ?>">
                                                    <div class="notification-avatar">
                                                        <div class="avatar avatar-2xl me-3">
                                                            <div class="avatar-name rounded-circle"><img src="profileimages/<?php echo $senderPhoto; ?>" alt="<?php echo $senderName; ?>" class="rounded-circle"></div>
                                                        </div>
                                                    </div>
                                                    <div class="notification-body">
                                                        <p class="mb-1"><strong><?php echo $senderName; ?></strong>: <?php echo $message['message']; ?></p>
                                                        <span class="notification-time"><span class="me-2" role="img" aria-label="Emoji">💬</span><?php echo $message['time_received']; ?></span>
                                                    </div>
                                                </a>
                                            </div>
                                            <?php if ($key >= 9) break; // Display only up to 10 messages ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="card-footer text-center border-top"><a class="card-link d-block" href="chat">View all</a></div>
                            </div>
                        </div>
                    </li>
                    <?php
                    }
                    }
                    } ?>

                    <li class='nav-item dropdown'>
                        <?php
                        $aid = $_SESSION['sessionWriter'];

                        // Query to count unread admin comments for this writer's tasks
                        $unreadCommentsCountQuery = mysqli_query($con, "
                        SELECT COUNT(*) AS unread_comments_count 
                        FROM tbl_task_comments tc 
                        JOIN tbltasks t ON tc.task_id = t.id 
                        WHERE t.email = '$aid' 
                        AND tc.user_type = 'admin' 
                        AND tc.is_read = 0
                    ");
                        $unreadCommentsCountResult = mysqli_fetch_assoc($unreadCommentsCountQuery);
                        $unreadCommentsCount = $unreadCommentsCountResult['unread_comments_count'];

                        // Query to fetch unread comments details (limit to recent ones)
                        $unreadCommentsQuery = mysqli_query($con, "
                        SELECT tc.*, t.topic, t.id as task_id
                        FROM tbl_task_comments tc 
                        JOIN tbltasks t ON tc.task_id = t.id 
                        WHERE t.email = '$aid' 
                        AND tc.user_type = 'admin' 
                        AND tc.is_read = 0 
                        ORDER BY tc.created_at DESC 
                        LIMIT 5
                    ");

                        $unreadComments = [];
                        while ($comment = mysqli_fetch_assoc($unreadCommentsQuery)) {
                            $unreadComments[] = $comment;
                        }
                        ?>

                        <a class="nav-link notification-indicator notification-indicator-info px-0 fa-icon-wait"
                           id="navbarDropdownComments" role="button" data-bs-toggle="dropdown" aria-haspopup="true"
                           aria-expanded="false">
                            <span class="fas fa-bell" data-fa-transform="shrink-6" style="font-size: 33px;"></span>
                            <?php if ($unreadCommentsCount > 0): ?>
                                <span class="notification-indicator-number"><?php echo $unreadCommentsCount; ?></span>
                            <?php endif; ?>
                        </a>

                        <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-menu-notification dropdown-caret-bg"
                             aria-labelledby="navbarDropdownComments">
                            <div class="card card-notification shadow-none">
                                <div class="card-header">
                                    <div class="row justify-content-between align-items-center">
                                        <div class="col-auto">
                                            <h6 class="card-header-title mb-0 text-info">New Comments</h6>
                                        </div>
                                    </div>
                                </div>

                                <div class="scrollbar-overlay" style="max-height:19rem">
                                    <div class="list-group list-group-flush fw-normal fs-10">
                                        <?php if ($unreadCommentsCount > 0): ?>
                                            <div class="list-group-title border-bottom text-info">
                                                You have <?php echo $unreadCommentsCount; ?> unread comments.
                                            </div>
                                            <?php foreach ($unreadComments as $key => $comment): ?>
                                                <div class="list-group-item">
                                                    <?php $encodedTaskId = base64_encode($comment['task_id']); ?>
                                                    <a class="notification notification-flush notification-unread"
                                                       href="view-task?task_id=<?php echo htmlspecialchars($encodedTaskId); ?>">
                                                        <div class="notification-avatar">
                                                            <div class="avatar avatar-2xl me-3">
                                                                <span class="material-icons text-info fs-3">chat_bubble</span>
                                                            </div>
                                                        </div>
                                                        <div class="notification-body">
                                                            <p class="mb-1 text-info">
                                                                <strong>Task <?php echo $comment['task_id']; ?>
                                                                    :</strong>
                                                                <?php echo htmlspecialchars(substr($comment['topic'], 0, 30)) . (strlen($comment['topic']) > 30 ? '...' : ''); ?>
                                                            </p>
                                                            <p class="mb-1 fs-11">
                                                                Admin: <?php
                                                                $unescaped_comment = stripcslashes($comment['comment']);
                                                                echo htmlspecialchars(substr($unescaped_comment, 0, 45)) . (strlen($unescaped_comment) > 45 ? '...' : '');
                                                                ?>
                                                            </p>
                                                            <span class='notification-time text-muted'>
                                                                <span class='me-2' role='img' aria-label='Time'>💬</span>
                                                                <?php echo timeAgo($comment['created_at']); ?>
                                                            </span>
                                                        </div>
                                                    </a>
                                                </div>
                                                <?php if ($key >= 5) break; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="list-group-item text-center text-muted py-4">
                                                <span class="fas fa-check-circle me-2 text-info"></span>
                                                <p class="mb-0">No new comments</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer text-center border-top">
                                    <a class="card-link d-block" href="all-comments">View all comments</a>
                                </div>
                            </div>
                        </div>
                    </li>

                    <li class="nav-item dropdown"><a class="nav-link pe-0 ps-2" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <?php
                            $aid=$_SESSION['sessionWriter'];
                            $sql="SELECT * from  tblwriters where email=:aid";
                            $query = $dbh -> prepare($sql);
                            $query->bindParam(':aid',$aid,PDO::PARAM_STR);
                            $query->execute();
                            $results=$query->fetchAll(PDO::FETCH_OBJ);
                            $cnt=1;
                            if($query->rowCount() > 0)
                            {
                            foreach($results as $row)
                            {
                            ?>
                            <div class="avatar avatar-xl status-online">
                                <?php
                                if($row->Photo=="avatar.png")
                                {
                                    ?>
                                    <img class="rounded-circle" src="assets/img/team/avatar.png" alt="" />
                                    <?php
                                } else {
                                    ?>
                                    <img class="rounded-circle" src="profileimages/<?php  echo $row->Photo;?>" alt="">
                                    <?php
                                } ?>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end py-0" aria-labelledby="navbarDropdownUser">
                            <div class="bg-white dark__bg-1000 rounded-2 py-2">
                                <a class="dropdown-item fw-bold text-warning" href="#"><span><?php  echo $row->FirstName;?> <?php  echo $row->LastName;?></span></a>

                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="profile">Profile &amp; account</a>

                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="settings">Settings</a>
                                <a class="dropdown-item" href="logout?logout=1">Logout</a>
                            </div>
                        </div>
                    </li>
                </ul>
            </nav>
            <?php
            }
            } ?>
            <?php
                $query = mysqli_query($con, "SELECT * FROM tblsettings WHERE id = 3");

                if ($query && mysqli_num_rows($query) > 0) {
                    $row = mysqli_fetch_assoc($query);
                    $currentNotification = $row['description'];

                    if (isset($row['regStatus']) && $row['regStatus'] == 1) {
                        ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>Notification!</strong> <?php echo htmlspecialchars($currentNotification, ENT_QUOTES, 'UTF-8'); ?>
                            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="alert alert-danger" role="alert">Error fetching notification settings.</div>';
                }
            ?>

            <!-- Alert for Unconfirmed Tasks -->
            <?php
            $query_unconfirmed = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_confirmed = 1 AND email = '$aid'";
            $result_unconfirmed = mysqli_query($con, $query_unconfirmed);
            if ($result_unconfirmed) {
                $row_unconfirmed = mysqli_fetch_assoc($result_unconfirmed);
                $count_unconfirmed = $row_unconfirmed['taskCount'];
                if ($count_unconfirmed > 0) {
                    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                        You have ' . $count_unconfirmed . ' unconfirmed tasks! 
                        <a href="unconfirmed" class="alert-link">View Tasks</a>
                        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                }
            }
            ?>

            <!-- Alert for Tasks in Revision -->
            <?php
            $query_revision = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Revision' AND email = '$aid'";
            $result_revision = mysqli_query($con, $query_revision);
            if ($result_revision) {
                $row_revision = mysqli_fetch_assoc($result_revision);
                $count_revision = $row_revision['taskCount'];
                if ($count_revision > 0) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        You have ' . $count_revision . ' tasks in revision!
                        <a href="tasks-in-revision" class="alert-link">View Tasks</a>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button> 
                    </div>';
                }
            }
            ?>


            <script>
                // Browser Notifications Handler
                class NotificationManager {
                    constructor() {
                        this.previousCounts = {
                            tasks: 0,
                            messages: 0,
                            comments: 0
                        };
                        this.initialized = false;
                        this.checkInterval = null;

                        this.init();
                    }

                    async init() {
                        // Request notification permission
                        if ('Notification' in window) {
                            if (Notification.permission === 'default') {
                                await Notification.requestPermission();
                            }
                        }

                        await this.updateCounts(false);
                        this.initialized = true;
                        this.startPeriodicCheck();
                    }

                    async requestNotificationPermission() {
                        if ('Notification' in window && Notification.permission === 'default') {
                            const permission = await Notification.requestPermission();
                            if (permission === 'granted') {
                                this.updateNotificationIndicator();
                            } else if (permission === 'denied') {
                                alert('Notifications are blocked. Please enable them in your browser settings for this site.');
                            }
                            return permission;
                        }
                        return Notification.permission;
                    }

                    playNotificationSound() {
                        try {
                            const audio = new Audio('audio/task-notification.mp3');
                            audio.volume = 0.7;
                            audio.play().catch(() => {
                                this.playFallbackSound();
                            });
                        } catch (error) {
                            this.playFallbackSound();
                        }
                    }

                    playFallbackSound() {
                        try {
                            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                            const oscillator = audioContext.createOscillator();
                            const gainNode = audioContext.createGain();

                            oscillator.connect(gainNode);
                            gainNode.connect(audioContext.destination);

                            oscillator.frequency.value = 800;
                            oscillator.type = 'sine';

                            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

                            oscillator.start(audioContext.currentTime);
                            oscillator.stop(audioContext.currentTime + 0.5);
                        } catch (error) {
                            // Silent fallback - continue without sound
                        }
                    }

                    updateNotificationIndicator() {
                        const toggleBtn = document.getElementById('notificationToggle');
                        if (toggleBtn) {
                            const icon = toggleBtn.querySelector('span');
                            if (Notification.permission === 'granted') {
                                toggleBtn.classList.add('notification-enabled');
                                toggleBtn.title = 'Browser Notifications Enabled';
                                if (icon) {
                                    icon.style.color = '#28a745';
                                }
                            } else {
                                toggleBtn.classList.remove('notification-enabled');
                                toggleBtn.title = 'Enable Browser Notifications';
                                if (icon) {
                                    icon.style.color = '';
                                }
                            }
                        }
                    }

                    startPeriodicCheck() {
                        if (this.checkInterval) {
                            clearInterval(this.checkInterval);
                        }
                        this.checkInterval = setInterval(() => {
                            this.updateCounts(true);
                        }, 30000);
                    }

                    stopPeriodicCheck() {
                        if (this.checkInterval) {
                            clearInterval(this.checkInterval);
                        }
                    }

                    async updateCounts(showNotifications = true) {
                        try {
                            const response = await fetch('get-notification-counts', {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                }
                            });

                            if (!response.ok) {
                                throw new Error('Failed to fetch notification counts');
                            }

                            const data = await response.json();

                            if (this.initialized && showNotifications) {
                                this.checkForNewNotifications(data);
                            }

                            this.previousCounts = {
                                tasks: data.tasks,
                                messages: data.messages,
                                comments: data.comments
                            };

                        } catch (error) {
                            console.error('Error updating notification counts:', error);
                        }
                    }

                    checkForNewNotifications(newCounts) {
                        if (newCounts.tasks > this.previousCounts.tasks) {
                            const diff = newCounts.tasks - this.previousCounts.tasks;
                            this.showNotification(
                                `${diff} New Task${diff > 1 ? 's' : ''}`,
                                diff === 1 ? 'You have a new task notification' : `You have ${diff} new task notifications`,
                                'task',
                                'tasks-in-progress'
                            );
                        }

                        if (newCounts.messages > this.previousCounts.messages) {
                            const diff = newCounts.messages - this.previousCounts.messages;
                            this.showNotification(
                                `${diff} New Message${diff > 1 ? 's' : ''}`,
                                diff === 1 ? 'You have a new message' : `You have ${diff} new messages`,
                                'message',
                                'chat'
                            );
                        }

                        if (newCounts.comments > this.previousCounts.comments) {
                            const diff = newCounts.comments - this.previousCounts.comments;
                            this.showNotification(
                                `${diff} New Comment${diff > 1 ? 's' : ''}`,
                                diff === 1 ? 'You have a new task comment from admin' : `You have ${diff} new comments from admin`,
                                'comment',
                                'all-comments'
                            );
                        }
                    }

                    showNotification(title, body, type = 'info', clickUrl = null) {
                        if ('Notification' in window) {
                            if (Notification.permission === 'granted') {
                                try {
                                    this.playNotificationSound();

                                    const options = {
                                        body: body,
                                        icon: this.getIconForType(type),
                                        badge: 'assets/img/icons/spot-illustrations/itasker.png',
                                        tag: `${type}-${Date.now()}`,
                                        renotify: true,
                                        silent: false,
                                        dir: 'ltr',
                                        lang: 'en-US',
                                        vibrate: [200, 100, 200]
                                    };

                                    const notification = new Notification(title, options);

                                    notification.onclick = (event) => {
                                        window.focus();
                                        if (clickUrl) {
                                            window.location.href = clickUrl;
                                        }
                                        notification.close();
                                    };

                                    setTimeout(() => {
                                        if (notification) {
                                            notification.close();
                                        }
                                    }, 60000);

                                } catch (error) {
                                    console.error('Failed to create notification:', error);
                                }
                            } else if (Notification.permission === 'default') {
                                Notification.requestPermission().then(permission => {
                                    if (permission === 'granted') {
                                        this.showNotification(title, body, type, clickUrl);
                                    }
                                });
                            }
                        }
                    }

                    getIconForType(type) {
                        const baseUrl = '../assets/img/icons/spot-illustrations/';
                        switch (type) {
                            case 'task':
                                return baseUrl + 'task-icon.png';
                            case 'message':
                                return baseUrl + 'message-icon.png';
                            case 'comment':
                                return baseUrl + 'comment-icon.png';
                            default:
                                return baseUrl + 'itasker.png';
                        }
                    }

                    handleVisibilityChange() {
                        if (document.hidden) {
                            this.startPeriodicCheck();
                        } else {
                            this.updateCounts(true);
                        }
                    }
                }

                // Initialize the notification manager
                window.notificationManager = null;

                document.addEventListener('DOMContentLoaded', function() {
                    try {
                        window.notificationManager = new NotificationManager();
                    } catch (error) {
                        console.error('Failed to initialize NotificationManager:', error);
                    }

                    document.addEventListener('visibilitychange', () => {
                        if (window.notificationManager) {
                            window.notificationManager.handleVisibilityChange();
                        }
                    });

                    // Add notification toggle button
                    const navbar = document.querySelector('.navbar-nav.ms-auto');
                    if (navbar) {
                        const notificationToggle = document.createElement('li');
                        notificationToggle.className = 'nav-item';
                        notificationToggle.innerHTML = `
                <a class="nav-link px-2" href="#" id="notificationToggle" title="Enable Browser Notifications">
                    <span class="fas fa-desktop" style="font-size: 20px;"></span>
                </a>
            `;
                        navbar.insertBefore(notificationToggle, navbar.firstChild);

                        setTimeout(() => {
                            if (window.notificationManager) {
                                window.notificationManager.updateNotificationIndicator();
                            }
                        }, 500);

                        document.getElementById('notificationToggle').addEventListener('click', (e) => {
                            e.preventDefault();
                            if (window.notificationManager) {
                                if (Notification.permission !== 'granted') {
                                    window.notificationManager.requestNotificationPermission();
                                }
                            }
                        });
                    }
                });

                window.addEventListener('beforeunload', () => {
                    if (window.notificationManager) {
                        window.notificationManager.stopPeriodicCheck();
                    }
                });
            </script>

            <script>
                class NotificationUpdater {
                    constructor(updateInterval = 15000) { // Update every 15 seconds
                        this.updateInterval = updateInterval;
                        this.isRunning = false;
                    }

                    start() {
                        if (this.isRunning) return;
                        this.isRunning = true;
                        this.updateNotifications();
                        this.intervalId = setInterval(() => {
                            this.updateNotifications();
                        }, this.updateInterval);
                    }

                    stop() {
                        if (this.intervalId) {
                            clearInterval(this.intervalId);
                            this.isRunning = false;
                        }
                    }

                    async updateNotifications() {
                        try {
                            const response = await fetch('get-notification-counts', {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                }
                            });

                            if (!response.ok) throw new Error('Network response was not ok');

                            const data = await response.json();

                            // Update task notifications
                            this.updateCounter('navbarDropdownTasks', data.tasks);

                            // Update message notifications
                            this.updateCounter('navbarDropdownNotification', data.messages);

                            // Update comment notifications
                            this.updateCounter('navbarDropdownComments', data.comments);

                        } catch (error) {
                            console.error('Error updating notifications:', error);
                        }
                    }

                    updateCounter(elementId, count) {
                        const notificationLink = document.getElementById(elementId);
                        if (!notificationLink) return;

                        const counterElement = notificationLink.querySelector('.notification-indicator-number');

                        if (count > 0) {
                            if (counterElement) {
                                counterElement.textContent = count;
                            } else {
                                // Create counter if it doesn't exist
                                const newCounter = document.createElement('span');
                                newCounter.className = 'notification-indicator-number';
                                newCounter.textContent = count;
                                notificationLink.appendChild(newCounter);
                            }
                        } else {
                            // Remove counter if count is 0
                            if (counterElement) {
                                counterElement.remove();
                            }
                        }
                    }
                }

                // Initialize the updater when page loads
                document.addEventListener('DOMContentLoaded', function() {
                    const notificationUpdater = new NotificationUpdater(15000); // 15 seconds
                    notificationUpdater.start();

                    // Optional: Update more frequently when user is active
                    let userActive = true;
                    let inactiveTimer;

                    function setUserInactive() {
                        userActive = false;
                        notificationUpdater.stop();
                        // Start with longer interval when inactive (2 minutes)
                        notificationUpdater.updateInterval = 120000;
                        notificationUpdater.start();
                    }

                    function setUserActive() {
                        if (!userActive) {
                            userActive = true;
                            notificationUpdater.stop();
                            // Shorter interval when active (15 seconds)
                            notificationUpdater.updateInterval = 15000;
                            notificationUpdater.start();
                        }
                        clearTimeout(inactiveTimer);
                        inactiveTimer = setTimeout(setUserInactive, 300000); // 5 minutes
                    }

                    // Track user activity
                    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
                        document.addEventListener(event, setUserActive, true);
                    });
                });
            </script>



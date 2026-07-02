

<!-- ===============================================-->
<!--    Favicons-->
<!-- ===============================================-->
<link rel="apple-touch-icon" sizes="180x180" href="../assets/img/favicons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicons/favicon-16x16.png">
<link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicons/favicon.ico">
<link rel="manifest" href="../assets/img/favicons/manifest.json">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.0/font/bootstrap-icons.css" />
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons" />
<meta name="msapplication-TileImage" content="../assets/img/favicons/mstile-150x150.png">
<meta name="theme-color" content="#ffffff">
<script src="../vendors/jquery/jquery.min.js"></script>
<script src="../assets/js/config.js"></script>
<script src="../vendors/simplebar/simplebar.min.js"></script>
<script src="../assets/js/adminsessiontimeout.js"></script>
<script src="../assets/js/admin-task-notification.js"></script>



<!-- ===============================================-->
<!--    Stylesheets-->
<!-- ===============================================-->
<link href="../vendors/glightbox/glightbox.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
<link href="../vendors/simplebar/simplebar.min.css" rel="stylesheet">
<link href="../assets/css/theme-rtl.css" rel="stylesheet" id="style-rtl">
<link href="../assets/css/theme.css" rel="stylesheet" id="style-default">
<link href="../assets/css/user-rtl.css" rel="stylesheet" id="user-style-rtl">
<link href="../assets/css/user.css" rel="stylesheet" id="user-style-default">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
<link href="../vendors/datatables.net-bs5/dataTables.bootstrap5.min.css" rel='stylesheet' />
<link href="../vendors/select2/select2.min.css" rel='stylesheet'/>
<script src="../vendors/tinymce/tinymce.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<link href="../vendors/select2-bootstrap-5-theme/select2-bootstrap-5-theme.min.css" rel='stylesheet' />
<link href="../vendors/flatpickr/flatpickr.min.css" rel="stylesheet" />
<link href="../vendors/dropzone/dropzone.css" rel="stylesheet" />
<link href='../vendors/choices/choices.min.css' rel='stylesheet'/>
<script src='../vendors/choices/choices.min.js'></script>
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

    .grain-overlay {
        background: url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><pattern id='grain' width='10' height='10' patternUnits='userSpaceOnUse'><circle cx='5' cy='5' r='1' fill='white' opacity='0.05'/></pattern></defs><rect width='100' height='100' fill='url(%23grain)'/></svg>");
        opacity: 1;
    }

    @keyframes onlinePulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.2);
            opacity: 0.7;
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* Ensure the hover works on the entire message bubble */
    .comment-bubble {
        transition: all 0.3s ease;
    }

    .comment-bubble:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    }

    /* Tooltip styling */
    .tooltip-inner {
        background-color: rgba(0, 0, 0, 0.9) !important;
        color: white !important;
        padding: 8px 12px !important;
        border-radius: 6px !important;
        font-size: 12px !important;
        max-width: 200px !important;
    }

    .tooltip .tooltip-arrow {
        border-top-color: rgba(0, 0, 0, 0.9) !important;
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

    .blurred-text {
        filter: blur(5px);
        transition: filter 0.3s ease-in-out;
    }
    .visible-text {
        filter: none;
    }
    .blur-financial-data {
        filter: blur(5px);
        transition: filter 0.3s ease-in-out;
    }

    .visible-financial-data {
        filter: none;
    }
    .event-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
        vertical-align: middle;
    }

    .highlighted-link:hover {
        color: #0056b3;
    }

    .badge-subtle-primary .event-dot {
        background-color: #007bff; /* Example color for Income */
    }

    .badge-subtle-danger .event-dot {
        background-color: #dc3545; /* Example color for Expense */
    }

    .badge-subtle-success .event-dot {
        background-color: #28a745; /* Example color for Savings */
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
    .unviewed-task {
        border-left: 4px solid #f1c40f !important; /* Warning yellow left border */
        position: relative;
    }
    .account-card {
        transition: transform 0.2s;
    }
    .account-card:hover {
        transform: translateY(-5px);
    }
    .balance-positive {
        color: #28a745;
    }
    .balance-negative {
        color: #dc3545;
    }
    .chart-container {
        position: relative;
        height: 400px;
    }
    .table-responsive {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .progress-bar-custom {
        background: linear-gradient(45deg, #007bff, #0056b3);
    }
    .loading {
        display: none;
    }
    .loading.show {
        display: block;
    }
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
    }
    .growth-updated {
        animation: growthUpdate 0.3s ease-in-out;
    }
    .summary-cards-row {
        display: flex !important;
        flex-wrap: wrap;
    }

    .summary-cards-row .col-md-3 {
        flex: 0 0 25%;
        max-width: 25%;
        padding: 0 15px;
        margin-bottom: 15px;
    }

    @media (max-width: 768px) {
        .summary-cards-row .col-md-3 {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }

    @media (max-width: 576px) {
        .summary-cards-row .col-md-3 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    /* Preserve line breaks in descriptions */
    .task-description {
        white-space: pre-line;
    }

    #viewTaskDescription {
        white-space: pre-line;
    }

    .summary-card {
        height: 100%;
        min-height: 120px;
    }

    .toast {
        border: none;
        border-radius: 0.5rem;
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
        min-width: 300px;
    }

    .toast-body {
        border-radius: 0.5rem;
        font-size: 0.9rem;
    }

    .urgent-reminder-toast {
        min-width: 400px;
        max-width: 500px;
        border: 2px solid;
        animation: urgentPulse 2s infinite;
    }

    .urgent-reminder-toast .toast-header.bg-danger {
        border-color: #dc3545;
    }

    .urgent-reminder-toast .toast-header.bg-warning {
        border-color: #ffc107;
    }

    .urgent-reminder-toast .toast-header.bg-info {
        border-color: #0dcaf0;
    }

    @keyframes urgentPulse {
        0%, 100% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
        }
        50% {
            box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
        }
    }

    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1055;
        max-height: 80vh;
        overflow-y: auto;
    }

    .urgent-reminder-toast .btn-sm {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
    .reminder-card {
        transition: all 0.3s ease;
    }
    .reminder-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .priority-high { border-left: 4px solid #dc3545; }
    .priority-medium { border-left: 4px solid #ffc107; }
    .priority-low { border-left: 4px solid #28a745; }
    .completed { opacity: 0.6; }
    .sidebar {
        min-height: 100vh;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .main-content {
        background-color: #f8f9fa;
        min-height: 100vh;
    }
    .stat-card {
        transition: all 0.3s ease;
        cursor: pointer;
        border-radius: 12px !important;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    }

    .stat-card .card-body {
        border-radius: 12px;
    }

    .stat-card i {
        transition: transform 0.3s ease;
    }

    .stat-card:hover i {
        transform: scale(1.1);
    }
    .priority-high {
        border-left: 4px solid #dc3545 !important;
    }

    .priority-medium {
        border-left: 4px solid #ffc107 !important;
    }

    .priority-low {
        border-left: 4px solid #28a745 !important;
    }

    .status-completed {
        opacity: 0.7;
    }

    .status-completed .text-700 {
        text-decoration: line-through;
    }

    /* Active state styling */
    .stat-card.border-primary {
        border-width: 2px !important;
    }

    .stat-card.border-info {
        border-width: 2px !important;
    }

    .stat-card.border-danger {
        border-width: 2px !important;
    }

    .stat-card.border-success {
        border-width: 2px !important;
    }

    .stat-card.border-warning {
        border-width: 2px !important;
    }

    .stat-card.border-secondary {
        border-width: 2px !important;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .stat-card .card-body {
            padding: 1rem !important;
        }

        .stat-card i {
            font-size: 1.5rem !important;
        }

        .stat-card h4 {
            font-size: 1.25rem !important;
        }
    }

    .reminder-type-tab {
        border-radius: 8px !important;
        font-weight: 500;
        padding: 10px 16px;
    }

    .reminder-type-tab.active {
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        color: white !important;
    }

    .hover-shadow-lg {
        transition: all 0.3s ease;
    }

    .hover-shadow-lg:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }

    .transition-all {
        transition: all 0.3s ease;
    }

    .card-title {
        line-height: 1.3;
    }

     .fc-event-badge {
         position: absolute;
         top: -5px;
         right: -5px;
         font-size: 0.6rem;
         min-width: 16px;
         height: 16px;
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         font-weight: bold;
         z-index: 10;
         border: 2px solid white;
     }

    .fc-event-badge.today {
        background-color: #0dcaf0;
        color: white;
    }

    .fc-event-badge.upcoming {
        background-color: #0d6efd;
        color: white;
    }

    .fc-event-badge.overdue {
        background-color: #dc3545;
        color: white;
    }

    .fc-event-badge.completed {
        background-color: #198754;
        color: white;
    }

    .fc-event-badge.dismissed {
        background-color: #ffc107;
        color: #000;
    }

    .fc-event-main {
        position: relative;
    }

    .event-content {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 2px 6px !important;
        line-height: 1.2;
    }

    @media (max-width: 768px) {
        .fc-event {
            font-size: 0.65rem !important;
        }

        .event-content {
            padding: 1px 4px !important;
        }
    }

    @media (max-width: 576px) {
        .fc-event {
            font-size: 0.6rem !important;
        }
    }
    /* Make calendar container responsive */
    #appCalendar {
        width: 100%;
        max-width: 100%;
    }

    /* Responsive calendar adjustments */
    .fc {
        width: 100% !important;
    }

    .fc-view-harness {
        width: 100% !important;
    }

    .fc-daygrid-body {
        width: 100% !important;
    }

    .fc-scrollgrid {
        width: 100% !important;
    }

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .fc-header-toolbar {
            flex-direction: column;
            gap: 10px;
        }

        .fc-toolbar-chunk {
            display: flex;
            justify-content: center;
        }

        .fc-button-group .fc-button {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .fc-daygrid-day-number {
            font-size: 0.8rem;
        }
    }

    @media (max-width: 576px) {
        .fc-col-header-cell {
            font-size: 0.7rem;
        }

        .fc-daygrid-day-number {
            font-size: 0.7rem;
        }

        .fc-button-group .fc-button {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
        }
    }

    .typing-indicator {
        display: none;
        font-style: italic;
        color: #6c757d;
        font-size: 0.875rem;
        padding: 0.5rem;
    }

    .message-delivery-status {
        font-size: 0.75rem;
        margin-top: 0.25rem;
    }

    .file-preview {
        max-width: 200px;
        max-height: 200px;
        object-fit: cover;
        border-radius: 8px;
        margin: 0.5rem 0;
    }

    .connection-status {
        position: fixed;
        top: 10px;
        right: 10px;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        z-index: 1050;
    }

    .connection-online {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .connection-offline {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
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

    /* Attachment styles */
    .attachments-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .attachment-image {
        position: relative;
        display: inline-block;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .attachment-image:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .attachment-image img {
        display: block;
        transition: transform 0.3s ease;
    }

    .attachment-image:hover img {
        transform: scale(1.05);
    }

    .attachment-file-badge {
        display: inline-flex;
        align-items: center;
        padding: 10px 15px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        text-decoration: none;
        color: #212529;
        transition: all 0.2s ease;
        font-size: 14px;
    }

    .attachment-file-badge:hover {
        background: #e9ecef;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        color: #212529;
    }

    .attachment-file-badge i {
        font-size: 18px;
    }
    #selectedFilesPreview {
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    #filesPreviewList::-webkit-scrollbar {
        width: 4px;
    }

    #filesPreviewList::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 2px;
    }

    #filesPreviewList::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 2px;
    }

    #filesPreviewList::-webkit-scrollbar-thumb:hover {
        background: #555;
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
                    <div class="d-flex align-items-center py-3"><img class="me-2" src="../assets/img/icons/spot-illustrations/itasker.png" alt="" width="40" /><span class="font-sans-serif text-primary">i<span class="text-info">Tasker</span></span>
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
                        $aid=$_SESSION['odmsaid'];
                        $sql="SELECT * from  tbladmin where email=:aid";
                        $query = $dbh -> prepare($sql);
                        $query->bindParam(':aid',$aid,PDO::PARAM_STR);
                        $query->execute();
                        $results=$query->fetchAll(PDO::FETCH_OBJ);
                        $cnt=1;
                        if($query->rowCount() > 0)
                        {
                        foreach($results as $row)
                        {
                        if($row->AdminName=="Admin"  )
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
                            <!-- parent pages--><a class="nav-link" href="create-task" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-plus-circle"></span></span><span class="nav-link-text ps-1">Create Task</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="all-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-database"></span></span><span class="nav-link-text ps-1">All Tasks</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-success"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks";
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
                            <!-- parent pages--><a class="nav-link" href="draft-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-edit"></span></span><span class="nav-link-text ps-1">Drafts</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-danger"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND (writer = 'Draft' OR status = 'Draft')";
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
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_confirmed = 1";
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
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress'";
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
                                    <span class="badge rounded-pill ms-2 badge-subtle-warning"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Revision'";
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
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted'";
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
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND is_archived = 0";
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
                            <!-- parent pages--><a class="nav-link" href="cancelled-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-trash"></span></span><span class="nav-link-text ps-1">Cancelled</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-danger"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 1";
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
                            <!-- parent pages--><a class="nav-link" href="archived-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fa fa-archive"></span></span><span class="nav-link-text ps-1">Archived</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-secondary"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_archived = 1";
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
                            <!-- parent pages--><a class="nav-link" href="favorited-tasks" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fa fa-heart"></span></span><span class="nav-link-text ps-1">Favorite</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-light"><?php
                                        // Query to count tasks where is_deleted = 0
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_favorite = 1";
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
                            <!-- parent pages--><a class="nav-link" href="calendar" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-calendar"></span></span><span class="nav-link-text ps-1">Calendar</span>
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

                            <!-- parent pages--><a class='nav-link' href='reminders' role='button'>
                                <div class='d-flex align-items-center'><span class='nav-link-icon'><span class='fas fa-stopwatch-20'></span></span>
                                    <span class='nav-link-text ps-1'>Reminders</span>
                                    <span class='badge rounded-pill ms-2 badge-subtle-success'>New</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="chat" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-comments"></span></span><span class="nav-link-text ps-1">Chat</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-primary"><?php echo $unreadMessagesCount ?></span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="todo" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-clipboard-list"></span></span><span class="nav-link-text ps-1">To-Do List</span>
                                </div>
                            </a>
                            <!-- label-->
                            <a class="nav-link dropdown-indicator collapsed" href="#budget" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="budget">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-wallet"></span></span><span class="nav-link-text ps-1">iFinance</span>
                                </div>
                            </a>
                            <ul class="nav collapse" id="budget" style="">
                                <li class="nav-item"><a class="nav-link" href="budget">
                                        <div class="d-flex align-items-center"><span class="nav-link-text ps-1">iBudget</span>
                                        </div>
                                    </a>
                                </li>
                                <li class="nav-item"><a class="nav-link" href="transactions">
                                        <div class="d-flex align-items-center"><span class="nav-link-text ps-1">iTransactions</span>
                                        </div>
                                    </a>
                                </li>
                                <li class="nav-item"><a class="nav-link" href="analytics">
                                        <div class="d-flex align-items-center"><span class="nav-link-text ps-1">iChart</span>
                                        </div>
                                    </a>
                                </li>
                                <li class="nav-item"><a class="nav-link" href="t-calendar">
                                        <div class="d-flex align-items-center"><span class="nav-link-text ps-1">iCalendar</span>
                                        </div>
                                    </a>
                                </li>
                                <li class="nav-item"><a class="nav-link" href="saving-goals">
                                        <div class="d-flex align-items-center"><span class="nav-link-text ps-1">iGoals</span>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                            <!-- parent pages--><a class="nav-link" href="projects" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-folder"></span></span><span class="nav-link-text ps-1">Projects</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-primary">new</span>
                                </div>
                            </a>
                            <!-- label-->
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
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed'";
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
                                        $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND is_paid = 1";
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

                            <!-- parent pages--><a class="nav-link" href="invoice-logs" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-receipt"></span></span><span class="nav-link-text ps-1">Invoices</span>
                                </div>
                            </a>
                            <!-- label-->
                            <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                <div class="col-auto navbar-vertical-label">MANAGEMENT
                                </div>
                                <div class="col ps-0">
                                    <hr class="mb-0 navbar-vertical-divider" />
                                </div>
                            </div>
                            <!-- parent pages--><a class="nav-link" href="usermanagement" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-users"></span></span><span class="nav-link-text ps-1">Writers</span>
                                    <span class="badge rounded-pill ms-2 badge-subtle-secondary">New</span>
                                </div>
                            </a>

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
                    <div class="d-flex align-items-center"><img class="me-2" src="../assets/img/icons/spot-illustrations/itasker.png" alt="" width="40" /><span class="font-sans-serif text-primary">i</span><span class="font-sans-serif text-info">Tasker</span>
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
                        // Query to count new submitted tasks (not yet acknowledged)
                        $newTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS new_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted' AND admin_acknowledged = 0");
                        $newTasksCountResult = mysqli_fetch_assoc($newTasksCountQuery);
                        $newTasksCount = $newTasksCountResult['new_task_count'];

                        // Query to count late tasks
                        $lateTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS late_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW()");
                        $lateTasksCountResult = mysqli_fetch_assoc($lateTasksCountQuery);
                        $lateTasksCount = $lateTasksCountResult['late_task_count'];

                        // Total task notifications
                        $totalTaskNotifications = $newTasksCount + $lateTasksCount;

                        // Query to fetch new submitted tasks details (priority: submitted first)
                        $newTasksQuery = mysqli_query($con, "SELECT *, 'submitted' as notification_type FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted' AND admin_acknowledged = 0 ORDER BY submitted_on DESC LIMIT 3");
                        $newTasks = [];
                        while ($task = mysqli_fetch_assoc($newTasksQuery)) {
                            $newTasks[] = $task;
                        }

                        // Query to fetch late tasks details
                        $lateTasksQuery = mysqli_query($con, "SELECT *, 'late' as notification_type FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW() ORDER BY due_date ASC LIMIT 3");
                        $lateTasks = [];
                        while ($task = mysqli_fetch_assoc($lateTasksQuery)) {
                            // Calculate overdue time
                            $dueDate = new DateTime($task['due_date']);
                            $now = new DateTime();
                            $interval = $now->diff($dueDate);
                            $task['time_overdue'] = $interval->format('%a days %h hours');
                            $lateTasks[] = $task;
                        }

                        // Check admin permissions
                        $aid = $_SESSION['odmsaid'];
                        $sql = 'SELECT * FROM tbladmin WHERE email=:aid';
                        $query = $dbh->prepare($sql);
                        $query->bindParam(':aid', $aid, PDO::PARAM_STR);
                        $query->execute();
                        $results = $query->fetchAll(PDO::FETCH_OBJ);
                        $isAdmin = ($query->rowCount() > 0 && $results[0]->AdminName == 'Admin');
                        ?>

                        <?php if ($isAdmin): ?>
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
                                            <?php if ($newTasksCount > 0): ?>
                                                <div class="col-auto ps-0 ps-sm-3">
                                                    <a class="card-link fw-normal" href="#"
                                                       onclick="markAllSubmittedAsRead()">Mark all as read</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="scrollbar-overlay" style="max-height:19rem">
                                        <div class="list-group list-group-flush fw-normal fs-10">

                                            <?php if ($newTasksCount > 0): ?>
                                                <div class="list-group-title border-bottom text-success">
                                                    <span class="fas fa-check-circle me-2"></span>You
                                                    have <?php echo $newTasksCount; ?> new submitted tasks.
                                                </div>
                                                <?php foreach ($newTasks as $key => $task): ?>
                                                    <div class="list-group-item">
                                                        <?php $encodedId = base64_encode($task['id']); ?>
                                                        <a class="notification notification-flush notification-unread"
                                                           href="view-task?task_id=<?php echo htmlspecialchars($encodedId); ?>"
                                                           onclick="handleTaskClick(event, <?php echo $task['id']; ?>, '<?php echo htmlspecialchars($encodedId); ?>')">
                                                            <div class="notification-avatar">
                                                                <div class="avatar avatar-2xl me-3">
                                                                    <span class="material-icons text-success fs-3">assignment_turned_in</span>
                                                                </div>
                                                            </div>
                                                            <div class="notification-body">
                                                                <p class="mb-1 text-success">
                                                                    <strong>Task <?php echo $task['id']; ?>:</strong>
                                                                    <?php echo htmlspecialchars(substr($task['topic'], 0, 35)) . (strlen($task['topic']) > 35 ? '...' : ''); ?>
                                                                </p>
                                                                <span class='notification-time text-muted'
                                                                      title="<?php echo date('M j, Y g:i A', strtotime($task['submitted_on'])); ?>">
                                                                    <span class='me-2' role='img' aria-label='Time'>📤</span>
                                                                    Submitted: <?php echo timeSubAgo($task['submitted_on']); ?>
                                                                </span>
                                                            </div>
                                                        </a>
                                                    </div>
                                                    <?php if ($key >= 2) break; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

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

                                            <?php if ($totalTaskNotifications == 0): ?>
                                                <div class="list-group-item text-center text-muted py-4">
                                                    <span class="fas fa-check-circle me-2 text-success"></span>
                                                    <p class="mb-0">All tasks are up to date!</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer text-center border-top">
                                        <a class="card-link d-block" href="submitted-tasks">View all tasks</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link notification-indicator notification-indicator-info px-0 fa-icon-wait"
                           id="navbarDropdownNotification"
                           role="button"
                           data-bs-toggle="dropdown"
                           aria-haspopup="true"
                           aria-expanded="false"
                           data-hide-on-body-scroll="data-hide-on-body-scroll">
                            <span class="fas fa-comment" data-fa-transform="shrink-6" style="font-size: 33px;"></span>
                            <span class="notification-indicator-number"><?php echo htmlspecialchars($unreadMessagesCount); ?></span>
                        </a>

                        <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-menu-notification dropdown-caret-bg"
                             aria-labelledby="navbarDropdownNotification">
                            <div class="card card-notification shadow-none">
                                <div class="card-header">
                                    <div class="row justify-content-between align-items-center">
                                        <div class="col-auto">
                                            <h6 class="card-header-title mb-0 text-primary">Messages</h6>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                // Check if user is admin
                                $sql = "SELECT * FROM tbladmin WHERE email = :email";
                                $query = $dbh->prepare($sql);
                                $query->bindParam(':email', $aid);
                                $query->execute();
                                $results = $query->fetchAll(PDO::FETCH_OBJ);

                                if ($query->rowCount() > 0) {
                                    foreach ($results as $row) {
                                        if ($row->AdminName == "Admin") {
                                            ?>
                                            <div class="scrollbar-overlay" style="max-height:19rem">
                                                <div class="list-group list-group-flush fw-normal fs-10">
                                                    <div class="list-group-title border-bottom text-info">
                                                        You have <?php echo htmlspecialchars($unreadMessagesCount); ?> unread messages.
                                                    </div>

                                                    <?php
                                                    // Process unread messages with sender details and time calculation
                                                    $processedMessages = [];
                                                    $messageLimit = 10;
                                                    $messageCount = 0;

                                                    foreach ($unreadMessages as $key => $message) {
                                                        if ($messageCount >= $messageLimit) {
                                                            break;
                                                        }

                                                        // Fetch sender details from tblwriters
                                                        $senderID = (int)$message['sender_id'];
                                                        $senderQuery = "SELECT username, Photo FROM tblwriters WHERE id = ?";
                                                        $stmt = mysqli_prepare($con, $senderQuery);
                                                        mysqli_stmt_bind_param($stmt, "i", $senderID);
                                                        mysqli_stmt_execute($stmt);
                                                        $senderResult = mysqli_stmt_get_result($stmt);
                                                        $sender = mysqli_fetch_assoc($senderResult);

                                                        // Calculate time difference
                                                        $receivedDate = new DateTime($message['timestamp']);
                                                        $now = new DateTime();
                                                        $interval = $now->diff($receivedDate);

                                                        // Format time display
                                                        if ($interval->days > 0) {
                                                            $timeReceived = $interval->format('%a days ago');
                                                        } elseif ($interval->h > 0) {
                                                            $timeReceived = $interval->format('%h hours ago');
                                                        } elseif ($interval->i > 0) {
                                                            $timeReceived = $interval->format('%i minutes ago');
                                                        } else {
                                                            $timeReceived = 'Just now';
                                                        }

                                                        // Store processed message data
                                                        $processedMessages[] = [
                                                            'id' => $message['id'],
                                                            'message' => $message['message'],
                                                            'timestamp' => $message['timestamp'],
                                                            'time_received' => $timeReceived,
                                                            'sender_name' => $sender['username'] ?? 'Unknown User',
                                                            'sender_photo' => $sender['Photo'] ?? 'default-avatar.png'
                                                        ];

                                                        $messageCount++;
                                                        mysqli_stmt_close($stmt);
                                                    }

                                                    // Display processed messages
                                                    foreach ($processedMessages as $message):
                                                        $encodedId = base64_encode($message['id']);
                                                        ?>
                                                        <div class="list-group-item">
                                                            <a class="notification notification-flush notification-unread"
                                                               href="chat?message_id=<?php echo htmlspecialchars($encodedId); ?>">
                                                                <div class="notification-avatar">
                                                                    <div class="avatar avatar-2xl me-3">
                                                                        <div class="avatar-name rounded-circle">
                                                                            <img src="../profileimages/<?php echo htmlspecialchars($message['sender_photo']); ?>"
                                                                                 alt="<?php echo htmlspecialchars($message['sender_name']); ?>"
                                                                                 class="rounded-circle">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="notification-body">
                                                                    <p class="mb-1">
                                                                        <strong><?php echo htmlspecialchars($message['sender_name']); ?></strong>:
                                                                        <?php echo htmlspecialchars($message['message']); ?>
                                                                    </p>
                                                                    <span class="notification-time">
                                                    <span class="me-2" role="img" aria-label="Emoji">💬</span>
                                                    <?php echo htmlspecialchars($message['time_received']); ?>
                                                </span>
                                                                </div>
                                                            </a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="card-footer text-center border-top">
                                                <a class="card-link d-block" href="chat">View all</a>
                                            </div>
                                            <?php
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </li>

                    <li class='nav-item dropdown'>
                        <?php
                        $aid = $_SESSION['odmsaid'];

                        // Query to count unread writer comments for admin
                        $unreadCommentsCountQuery = mysqli_query($con, "
                            SELECT COUNT(*) AS unread_comments_count 
                            FROM tbl_task_comments 
                            WHERE user_type = 'writer' 
                            AND is_read = 0
                        ");
                        $unreadCommentsCountResult = mysqli_fetch_assoc($unreadCommentsCountQuery);
                        $unreadCommentsCount = $unreadCommentsCountResult['unread_comments_count'];

                        // Query to fetch unread comments details (limit to recent ones)
                        $unreadCommentsQuery = mysqli_query($con, "
                            SELECT tc.*, t.topic, t.id as task_id, t.email as writer_email
                            FROM tbl_task_comments tc 
                            JOIN tbltasks t ON tc.task_id = t.id 
                            WHERE tc.user_type = 'writer' 
                            AND tc.is_read = 0 
                            ORDER BY tc.created_at DESC 
                            LIMIT 5
                        ");

                        $unreadComments = [];
                        while ($comment = mysqli_fetch_assoc($unreadCommentsQuery)) {
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
                            $unreadComments[] = $comment;
                        }

                        // Check admin permissions
                        $sql = 'SELECT * FROM tbladmin WHERE email=:aid';
                        $query = $dbh->prepare($sql);
                        $query->bindParam(':aid', $aid, PDO::PARAM_STR);
                        $query->execute();
                        $results = $query->fetchAll(PDO::FETCH_OBJ);
                        $isAdmin = ($query->rowCount() > 0 && $results[0]->AdminName == 'Admin');
                        ?>

                        <?php if ($isAdmin): ?>
                            <a class="nav-link notification-indicator notification-indicator-info px-0 fa-icon-wait"
                               id="navbarDropdownComments" role="button" data-bs-toggle="dropdown" aria-haspopup="true"
                               aria-expanded="false">
                                <span class="fas fa-bell" data-fa-transform="shrink-6"
                                      style="font-size: 33px;"></span>
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
                                                    You have <?php echo $unreadCommentsCount; ?> unread comments from
                                                    writers.
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
                                                                    <strong><?php echo htmlspecialchars($comment['username']); ?>
                                                                        :</strong>
                                                                    <?php
                                                                    $unescaped_comment = stripcslashes($comment['comment']);
                                                                    echo htmlspecialchars(substr($unescaped_comment, 0, 45)) . (strlen($unescaped_comment) > 45 ? '...' : '');
                                                                    ?>
                                                                </p>
                                                                <span class="notification-time text-muted">
                                            <span class="me-2" role="img" aria-label="Time">💬</span>
                                            <?php echo $comment['time_ago']; ?>
                                        </span>
                                                            </div>
                                                        </a>
                                                    </div>
                                                    <?php if ($key >= 3) break; ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="list-group-item text-center text-muted py-4">
                                                    <span class="fas fa-check-circle me-2 text-info"></span>
                                                    <p class="mb-0">No new comments from writers</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer text-center border-top">
                                        <a class="card-link d-block" href="all-comments.php">View all comments</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link pe-0 ps-2"
                           id="navbarDropdownUser"
                           role="button"
                           data-bs-toggle="dropdown"
                           aria-haspopup="true"
                           aria-expanded="false">

                            <?php
                            // Get current admin session
                            $aid = $_SESSION['odmsaid'];

                            // Fetch admin details
                            $sql = "SELECT * FROM tbladmin WHERE email = :aid";
                            $query = $dbh->prepare($sql);
                            $query->bindParam(':aid', $aid, PDO::PARAM_STR);
                            $query->execute();
                            $results = $query->fetchAll(PDO::FETCH_OBJ);

                            if ($query->rowCount() > 0) {
                            foreach ($results as $row) {
                            ?>
                            <div class="avatar avatar-xl status-online">
                                <?php if ($row->Photo == "avatar.png" || empty($row->Photo)): ?>
                                    <img class="rounded-circle"
                                         src="../assets/img/team/avatar.png"
                                         alt="Default Avatar" />
                                <?php else: ?>
                                    <img class="rounded-circle"
                                         src="../profileimages/<?php echo htmlspecialchars($row->Photo); ?>"
                                         alt="<?php echo htmlspecialchars($row->FirstName . ' ' . $row->LastName); ?> Avatar">
                                <?php endif; ?>
                            </div>
                        </a>

                        <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end py-0"
                             aria-labelledby="navbarDropdownUser">
                            <div class="bg-white dark__bg-1000 rounded-2 py-2">
                                <!-- User Name Header -->
                                <a class="dropdown-item fw-bold text-warning" href="#">
                                    <span>
                                        <?php echo htmlspecialchars($row->FirstName . ' ' . $row->LastName); ?>
                                    </span>
                                </a>

                                <div class="dropdown-divider"></div>

                                <!-- Account Management Section -->
                                <a class="dropdown-item" href="profile">
                                    <i class="fas fa-user me-2"></i>Profile &amp; account
                                </a>
                                <a class="dropdown-item" href="settings">
                                    <i class="fas fa-cog me-2"></i>Settings
                                </a>
                                <a class="dropdown-item" href="level-management">
                                    <i class="fas fa-layer-group me-2"></i>Levels
                                </a>
                                <a class="dropdown-item" href="bonus-settings">
                                    <i class="fas fa-gift me-2"></i>Bonus
                                </a>

                                <div class="dropdown-divider"></div>

                                <!-- System Information Section -->
                                <a class="dropdown-item" href="changelog">
                                    <i class="fas fa-code-branch me-2"></i>Version
                                </a>
                                <a class="dropdown-item" href="14">
                                    <i class="fas fa-file-alt me-2"></i>Logs
                                </a>

                                <div class="dropdown-divider"></div>

                                <!-- Logout Section -->
                                <a class="dropdown-item text-danger" href="logout?logout=1">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </div>
                        </div>

                        <?php
                        }
                        }
                        ?>
                    </li>
                </ul>
            </nav>

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
            $query_unconfirmed = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_confirmed = 1";
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
            // Total tasks in revision
            $query_revision = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Revision'";
            $result_revision = mysqli_query($con, $query_revision);
            $count_revision = 0;
            if ($result_revision) {
                $row_revision = mysqli_fetch_assoc($result_revision);
                $count_revision = $row_revision['taskCount'];
            }

            // Tasks in revision not yet viewed (acknowledged = 0)
            $query_unviewed = "SELECT COUNT(*) as unviewedCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Revision' AND acknowledged = 0";
            $result_unviewed = mysqli_query($con, $query_unviewed);
            $count_unviewed = 0;
            if ($result_unviewed) {
                $row_unviewed = mysqli_fetch_assoc($result_unviewed);
                $count_unviewed = $row_unviewed['unviewedCount'];
            }

            if ($count_revision > 0) {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                echo 'You have ' . $count_revision . ' tasks in revision';
                if ($count_unviewed > 0) {
                    echo ' (' . $count_unviewed . ' not yet viewed)';
                }
                echo '! <a href="tasks-in-revision" class="alert-link">View Tasks</a>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button> 
                    </div>';
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
                            const audio = new Audio('../audio/task-notification.mp3');
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
                                'submitted-tasks'
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
                                diff === 1 ? 'You have a new comment from a writer' : `You have ${diff} new comments from writers`,
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
                                        badge: '../assets/img/icons/spot-illustrations/itasker.png',
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
                // Solution 1: AJAX Polling (Recommended for this use case)
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
                            const response = await fetch('notification-update', {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                }
                            });

                            if (!response.ok) throw new Error('Network response was not ok');

                            const data = await response.json();

                            // Update task notifications
                            this.updateCounter('navbarDropdownTasks', data.task_count);

                            // Update message notifications
                            this.updateCounter('navbarDropdownNotification', data.message_count);

                            // Update comment notifications
                            this.updateCounter('navbarDropdownComments', data.comment_count);

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
                    const notificationUpdater = new NotificationUpdater(30000); // 30 seconds
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
                            // Shorter interval when active (30 seconds)
                            notificationUpdater.updateInterval = 30000;
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

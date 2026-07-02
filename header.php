<?php
include('check-login.php');

// Check if 'session' exists before using it
if (isset($_SESSION['sessionWriter'])) {
    $aid = $_SESSION['sessionWriter'];
} else {
    header('Location: login.php');
    exit();
}

// Fetch userID from the database using the email stored in the session
$userQuery = mysqli_query($con, "SELECT id FROM tblwriters WHERE email = '$aid'");
$userResult = mysqli_fetch_assoc($userQuery);
$userID = $userResult['id']; // Get the userID

// Query to fetch unread messages details by userID
$unreadMessagesQuery = mysqli_query($con, "SELECT * FROM chat_messages WHERE is_read = 0 AND receiver_id = '$userID' ORDER BY timestamp ASC");

$unreadMessages = []; // Initialize array to hold unread messages data
while ($message = mysqli_fetch_assoc($unreadMessagesQuery)) {
    $unreadMessages[] = $message; // Add each unread message to the array
}

$unreadMessagesCount = count($unreadMessages); // Count the number of unread messages
?>

<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Writer Verification | iTasker</title>

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

                    <li class="nav-item dropdown">
                        <?php
                        $aid = $_SESSION['sessionWriter'];
                        // Query to count late tasks
                        $lateTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS late_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW() AND email = '$aid'");
                        $lateTasksCountResult = mysqli_fetch_assoc($lateTasksCountQuery);
                        $lateTasksCount = $lateTasksCountResult['late_task_count'];

                        // Query to fetch late tasks details
                        $lateTasksQuery = mysqli_query($con, "SELECT * FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW() AND email = '$aid' ORDER BY due_date ASC");

                        $lateTasks = []; // Initialize array to hold late tasks data
                        while ($task = mysqli_fetch_assoc($lateTasksQuery)) {
                            $lateTasks[] = $task; // Add each late task to the array
                        }
                        $sql = "SELECT * FROM tblwriters WHERE email=:aid";
                        $query = $dbh->prepare($sql);
                        $query->bindParam(':aid', $aid, PDO::PARAM_STR);
                        $query->execute();
                        $results = $query->fetchAll(PDO::FETCH_OBJ);
                        $cnt = 1;

                        if ($query->rowCount() > 0) {
                        foreach ($results as $row) {
                        if ($row->is_verified == 1) {
                        ?>
                        <a class="nav-link notification-indicator notification-indicator-primary px-0 fa-icon-wait" id="navbarDropdownNotification" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-hide-on-body-scroll="data-hide-on-body-scroll"><span class="fas fa-bell" data-fa-transform="shrink-6" style="font-size: 33px;"></span><span class="notification-indicator-number"><?php echo $lateTasksCount; ?></span></a>
                        <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-menu-notification dropdown-caret-bg" aria-labelledby="navbarDropdownNotification">
                            <div class="card card-notification shadow-none">
                                <div class="card-header">
                                    <div class="row justify-content-between align-items-center">
                                        <div class="col-auto">
                                            <h6 class="card-header-title mb-0 text-primary">Notifications</h6>
                                        </div>
                                        <!--                                        <div class="col-auto ps-0 ps-sm-3"><a class="card-link fw-normal" href="#">Mark all as read</a></div>-->
                                    </div>
                                </div>

                                <div class="scrollbar-overlay" style="max-height:19rem">
                                    <div class="list-group list-group-flush fw-normal fs-10">

                                        <div class="list-group-title border-bottom text-info">You have <?php echo $lateTasksCount; ?> late tasks.</div>
                                        <?php
                                        // Calculate time remaining for each late task
                                        foreach ($lateTasks as $key => $task) {
                                            $dueDate = new DateTime($task['due_date']);
                                            $now = new DateTime();
                                            $interval = $now->diff($dueDate);
                                            $lateTasks[$key]['time_remaining'] = $interval->format('%a days %h hours %i minutes');
                                            if ($key >= 2) break; // Limit to only 3 tasks
                                        }
                                        ?>
                                        <?php foreach ($lateTasks as $key => $task): ?>
                                            <div class="list-group-item"><?php
                                                $encodedId = base64_encode($task['id']);
                                                ?>
                                                <a class="notification notification-flush notification-unread" href="view-task?task_id=<?php echo htmlspecialchars($encodedId); ?>">
                                                    <div class="notification-avatar">
                                                        <div class="avatar avatar-2xl me-3">
                                                            <span class="material-icons text-secondary fs-3 text-info">dangerous</span>
                                                        </div>
                                                    </div>
                                                    <div class="notification-body">
                                                        <p class="mb-1 text-primary"><strong>Task <?php echo $task['id']; ?>:</strong> <?php echo $task['topic']; ?></p>
                                                        <span class="notification-time text-danger"><span class="me-2" role="img" aria-label="Time">⏰</span><?php echo $task['time_remaining']; ?></span>
                                                    </div>
                                                </a>
                                            </div>
                                            <?php if ($key >= 2) break; // Display only up to 3 tasks ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="card-footer text-center border-top"><a class="card-link d-block" href="tasks-in-progress">View all</a></div>
                            </div>
                        </div>
                    </li>
                <?php
                }
                }
                } ?>
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

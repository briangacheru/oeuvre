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

                </div><a class="navbar-brand" href="index.php">
                    <div class="d-flex align-items-center py-3"><img class="me-2" src="assets/img/icons/spot-illustrations/falcon.png" alt="" width="40" /><span class="font-sans-serif text-primary">falcon</span>
                    </div>
                </a>
            </div>
            <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
                <div class="navbar-vertical-content scrollbar">
                    <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
                        <!-- parent pages--><a class="nav-link" href="index.php" role="button">
                            <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-home "></span></span><span class="nav-link-text ps-1">Dashboard</span>
                            </div>
                        </a>
                        <li class="nav-item">
                            <!-- label-->
                            <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                <div class="col-auto navbar-vertical-label">TASKS
                                </div>
                                <div class="col ps-0">
                                    <hr class="mb-0 navbar-vertical-divider" />
                                </div>
                            </div>
                            <!-- parent pages--><a class="nav-link" href="all-tasks.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-database"></span></span><span class="nav-link-text ps-1">All Tasks</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="draft-tasks.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-edit"></span></span><span class="nav-link-text ps-1">Drafts</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="unconfirmed.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-question-circle"></span></span><span class="nav-link-text ps-1">Unconfirmed</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="In Progress.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-spinner"></span></span><span class="nav-link-text ps-1">In Progress</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="submitted-tasks.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-check"></span></span><span class="nav-link-text ps-1">Submitted</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="completed-tasks.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-check-double"></span></span><span class="nav-link-text ps-1">Completed</span>
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
                            <!-- parent pages--><a class="nav-link" href="unpaid-tasks.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fab fa-creative-commons-nc"></span></span><span class="nav-link-text ps-1">Unpaid</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="paid-tasks.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="far fa-credit-card"></span></span><span class="nav-link-text ps-1">Paid</span>
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
                            <!-- parent pages--><a class="nav-link" href="overdraft.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-donate"></span></span><span class="nav-link-text ps-1">Overdrafts</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="settled-overdrafts.php" role="button">
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
                            <!-- parent pages--><a class="nav-link" href="usermanagement.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-users"></span></span><span class="nav-link-text ps-1">Users</span>
                                </div>
                            </a>
                            <!-- parent pages--><a class="nav-link" href="profile.php" role="button">
                                <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-wrench"></span></span><span class="nav-link-text ps-1">Profile</span>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
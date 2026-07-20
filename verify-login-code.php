<?php
require_once('check-login.php');
require_once('login-helpers.php');
csrf_verify_or_redirect();

$verifyError = '';
$verifyMessage = '';

// Nothing pending to verify - send them back to log in properly.
if (empty($_SESSION['otp_pending_email'])) {
    header('Location: login.php');
    exit;
}

$pendingEmail = $_SESSION['otp_pending_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $otpCode = generate_login_otp($con, 'tblwriters', $pendingEmail);
        send_login_otp_code_email($pendingEmail, $otpCode);
        $verifyMessage = "
            <div class='alert alert-info alert-dismissible fade show' role='alert'>
                <i class='bi bi-envelope me-1'></i> A new code has been sent to your email.
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
    } else {
        $submittedCode = trim($_POST['code'] ?? '');
        $result = verify_login_otp($con, 'tblwriters', $pendingEmail, $submittedCode);

        if ($result['success']) {
            $remember = !empty($_SESSION['otp_pending_remember']);
            $taskIdParam = $_SESSION['otp_pending_task_id'] ?? null;
            $existingDeviceToken = $_SESSION['otp_pending_device_token'] ?? null;
            unset($_SESSION['otp_pending_email'], $_SESSION['otp_pending_remember'], $_SESSION['otp_pending_task_id'], $_SESSION['otp_pending_device_token']);

            remember_device($con, 'tblwriter_known_devices', 'writer_email', $pendingEmail, 'writer_device_token', $existingDeviceToken);

            $redirectUrl = finalize_writer_login($con, $pendingEmail, $remember, $taskIdParam);
            header('Location: ' . $redirectUrl);
            exit;
        }

        $errorMessages = [
            'expired' => 'That code has expired. Please request a new one.',
            'locked' => 'Too many incorrect attempts. Please request a new code.',
            'invalid' => 'Incorrect code. Please try again.',
            'unavailable' => 'Verification is temporarily unavailable. Please try again shortly.',
        ];
        $verifyError = "
            <div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <i class='bi bi-exclamation-circle me-1'></i> " . ($errorMessages[$result['error']] ?? 'Something went wrong.') . "
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
    }
}

$atPos = strpos($pendingEmail, '@');
$maskedEmail = $atPos === false ? $pendingEmail
    : substr($pendingEmail, 0, 1) . str_repeat('*', max($atPos - 1, 1)) . substr($pendingEmail, $atPos);
?>

<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">


    <!-- ===============================================-->
    <!--    Document Title-->
    <!-- ===============================================-->
    <title>iTasker | Verify Login</title>


    <!-- ===============================================-->
    <!--    Favicons-->
    <!-- ===============================================-->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
    <link rel="manifest" href="assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="assets/img/favicons/mstile-150x150.png">
    <meta name="theme-color" content="#ffffff">
    <script src="assets/js/config.js"></script>
    <script src="vendors/simplebar/simplebar.min.js"></script>


    <!-- ===============================================-->
    <!--    Stylesheets-->
    <!-- ===============================================-->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
    <link href="vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/css/theme-rtl.css" rel="stylesheet" id="style-rtl">
    <link href="assets/css/theme.css" rel="stylesheet" id="style-default">
    <link href="assets/css/user-rtl.css" rel="stylesheet" id="user-style-rtl">
    <link href="assets/css/user.css" rel="stylesheet" id="user-style-default">
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
    <div class="container-fluid">
        <div class="row min-vh-100 flex-center g-0">
            <div class="col-lg-8 col-xxl-5 py-3 position-relative"><img class="bg-auth-circle-shape" src="assets/img/icons/spot-illustrations/bg-shape.png" alt="" width="250"><img class="bg-auth-circle-shape-2" src="assets/img/icons/spot-illustrations/shape-1.png" alt="" width="150">
                <div class="card overflow-hidden z-1">
                    <div class="card-body p-0">
                        <div class="row g-0 h-100">
                            <div class="col-md-5 text-center bg-card-gradient">
                                <div class="position-relative p-4 pt-md-5 pb-md-7" data-bs-theme="light">
                                    <div class="bg-holder bg-auth-card-shape" style="background-image:url(assets/img/icons/spot-illustrations/half-circle.png);">
                                    </div>
                                    <!--/.bg-holder-->

                                    <div class="z-1 position-relative"><a class="link-light mb-4 font-sans-serif fs-5 d-inline-block fw-bolder" href="index">iTasker</a>
                                        <p class="opacity-75 text-white">Welcome back — it's been a while. Let's confirm it's really you.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7 d-flex flex-center">
                                <div class="p-4 p-md-5 flex-grow-1">
                                    <?php
                                    echo $verifyError;
                                    echo $verifyMessage;
                                    ?>
                                    <div class="row flex-between-center">
                                        <div class="col-auto">
                                            <h3>Verify it's you</h3>
                                        </div>
                                    </div>
                                    <p class="mb-4">Enter the 6-digit code we sent to <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>. It expires in 10 minutes.</p>
                                    <form class="needs-validation" novalidate="novalidate" method="post" role="form" action="">
<?= csrf_field() ?>
                                        <div class="form-floating mb-3">
                                            <input class="form-control text-center" style="letter-spacing: 8px; font-size: 1.5rem;" id="floatingCode" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" name="code" required="required" autofocus="autofocus" />
                                            <label for="floatingCode">Verification code</label>
                                        </div>
                                        <div class="mb-3">
                                            <button class="btn btn-primary d-block w-100 mt-3" type="submit" name="submit">Verify and continue</button>
                                        </div>
                                    </form>
                                    <form method="post" role="form" action="">
<?= csrf_field() ?>
                                        <button class="btn btn-link p-0 fs-10" type="submit" name="resend" value="1">Didn't get a code? Resend it</button>
                                    </form>
                                    <a class="fs-10 text-600 d-block mt-3" href="login">Use a different account</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- ===============================================-->
<!--    End of Main Content-->
<!-- ===============================================-->


<!-- ===============================================-->
<!--    JavaScripts-->
<!-- ===============================================-->
<script src="vendors/popper/popper.min.js"></script>
<script src="vendors/bootstrap/bootstrap.min.js"></script>
<script src="vendors/anchorjs/anchor.min.js"></script>
<script src="vendors/is/is.min.js"></script>
<script src="vendors/fontawesome/all.min.js"></script>
<script src="vendors/lodash/lodash.min.js"></script>
<script src="vendors/list.js/list.min.js"></script>
<script src="assets/js/theme.js"></script>
</body>

</html>

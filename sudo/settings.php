<?php include "head.php";?>
    <title>iTasker | Settings</title>
<?php include "navi.php";
require_once "currency_helper.php";

$error_message = ''; // Initialize an empty error message variable

if (isset($_POST['upload_cover_image'])) {
    $adminid = $_SESSION['odmsaid'];

    // Handle file upload
    if (isset($_FILES['cover-image']) && $_FILES['cover-image']['error'] == 0) {
        $target_dir = "../profileimages/";
        $file_name = basename($_FILES["cover-image"]["name"]);
        $target_file = $target_dir . $file_name;

        // Check if file is an actual image
        $check = getimagesize($_FILES["cover-image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["cover-image"]["tmp_name"], $target_file)) {
                // Update the cover image in the database
                $sql = "UPDATE tbladmin SET coverImage=:coverImage WHERE email=:aid";
                $query = $dbh->prepare($sql);
                $query->bindParam(':coverImage', $file_name, PDO::PARAM_STR); // Use only the file name
                $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
                $query->execute();
                $message = 'Cover image has been updated successfully.';
            } else {
                $error_message = 'Failed to upload cover image.';
            }
        } else {
            $error_message = 'File is not an image.';
        }
    } else {
        $error_message = 'No file was uploaded or there was an upload error.';
    }
}

if (isset($_POST['upload_image'])) {
    $adminid = $_SESSION['odmsaid'];

    // Handle file upload
    if (isset($_FILES['profile-image']) && $_FILES['profile-image']['error'] == 0) {
        $target_dir = "../profileimages/";
        $file_name = basename($_FILES["profile-image"]["name"]);
        $target_file = $target_dir . $file_name;

        // Check if file is an actual image
        $check = getimagesize($_FILES["profile-image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["profile-image"]["tmp_name"], $target_file)) {
                // Update the profile image in the database
                $sql = "UPDATE tbladmin SET Photo=:photo WHERE email=:aid";
                $query = $dbh->prepare($sql);
                $query->bindParam(':photo', $file_name, PDO::PARAM_STR); // Use only the file name
                $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
                $query->execute();
                $message = 'Profile image has been updated successfully.';
            } else {
                $error_message = 'Failed to upload profile image.';
            }
        } else {
            $error_message = 'File is not an image.';
        }
    } else {
        $error_message = 'No file was uploaded or there was an upload error.';
    }
}


if (isset($_POST['submit'])) {
    $adminid = $_SESSION['odmsaid'];
    $AName = $_POST['username'];
    $fName = $_POST['firstname'];
    $lName = $_POST['lastname'];
    $mobno = $_POST['mobilenumber'];
    $email = $_POST['email'];

    // Handle file upload
    if (isset($_FILES['profile-image']) && $_FILES['profile-image']['error'] == 0) {
        $target_dir = "../profileimages/";
        $target_file = $target_dir . basename($_FILES["profile-image"]["name"]);
        if (move_uploaded_file($_FILES["profile-image"]["tmp_name"], $target_file)) {
            // Update the profile image in the database
            $sql = "UPDATE tbladmin SET Photo=:photo WHERE email=:aid";
            $query = $dbh->prepare($sql);
            $query->bindParam(':photo', $target_file, PDO::PARAM_STR);
            $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
            $query->execute();
        } else {
            $error_message = 'Failed to upload profile image.';
        }
    }

    if (empty($error_message)) {
        $sql = "UPDATE tbladmin SET username=:username, FirstName=:firstname, LastName=:lastname, MobileNumber=:mobilenumber, email=:email WHERE email=:aid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':username', $AName, PDO::PARAM_STR);
        $query->bindParam(':firstname', $fName, PDO::PARAM_STR);
        $query->bindParam(':lastname', $lName, PDO::PARAM_STR);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->bindParam(':mobilenumber', $mobno, PDO::PARAM_STR);
        $query->bindParam(':aid', $adminid, PDO::PARAM_STR);

        if ($query->execute()) {
            $message = 'Profile has been updated successfully.';
        } else {
            $error_message = 'Failed to update profile.';
        }
    }
}

if (isset($_POST['update_password'])) {
    $adminid = $_SESSION['odmsaid'];
    $oldPassword = $_POST['old_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Verify old password
    $sql = "SELECT Password FROM tbladmin WHERE email=:aid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_OBJ);

    if ($result && password_verify($oldPassword, $result->Password)) {
        if ($newPassword === $confirmPassword) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE tbladmin SET Password=:newpassword WHERE email=:aid";
            $query = $dbh->prepare($sql);
            $query->bindParam(':newpassword', $hashedPassword, PDO::PARAM_STR);
            $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
            if ($query->execute()) {
                $message = 'Password has been updated successfully.';
            } else {
                $error_message = 'Failed to update password.';
            }
        } else {
            $error_message = 'New password and confirm password do not match.';
        }
    } else {
        $error_message = 'Old password is incorrect.';
    }
}


// ── Preferred (display) currency for the Financial Dashboard ────────
if (isset($_POST['update_currency'])) {
    $adminid = $_SESSION['odmsaid'];
    $newCurrency = strtoupper(trim($_POST['preferred_currency'] ?? ''));

    if (!preg_match('/^[A-Z]{3}$/', $newCurrency)) {
        $error_message = 'Please choose a valid currency.';
    } else {
        $sql = "INSERT INTO tbl_user_settings (admin_id, preferred_currency)
                VALUES (:aid, :currency)
                ON DUPLICATE KEY UPDATE preferred_currency = :currency2, updated_at = CURRENT_TIMESTAMP";
        $query = $dbh->prepare($sql);
        $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
        $query->bindParam(':currency', $newCurrency, PDO::PARAM_STR);
        $query->bindParam(':currency2', $newCurrency, PDO::PARAM_STR);

        if ($query->execute()) {
            $message = 'Preferred currency updated to ' . $newCurrency . '. Dashboard charts and totals will use it going forward.';
        } else {
            $error_message = 'Failed to update preferred currency.';
        }
    }
}

// Fetch the admin's current preferred currency (defaults to KES if unset)
$currentPreferredCurrency = 'KES';
$adminIdForCurrency = $_SESSION['odmsaid'] ?? null;
if ($adminIdForCurrency) {
    $curSql = "SELECT preferred_currency FROM tbl_user_settings WHERE admin_id = :aid LIMIT 1";
    $curQuery = $dbh->prepare($curSql);
    $curQuery->bindParam(':aid', $adminIdForCurrency, PDO::PARAM_STR);
    $curQuery->execute();
    $curRow = $curQuery->fetch(PDO::FETCH_ASSOC);
    if ($curRow && !empty($curRow['preferred_currency'])) {
        $currentPreferredCurrency = $curRow['preferred_currency'];
    }
}
$currencyOptions = supportedCurrencies();

// Check if the form is submitted
if (isset($_POST['submitStatus'])) {
    $newStatus = isset($_POST['newStatus']) ? (int) $_POST['newStatus'] : null;

    // Validate new status
    if ($newStatus !== null && ($newStatus === 0 || $newStatus === 1)) {
        // Update the registration status in the database
        $query = "UPDATE tblsettings SET regStatus = ? WHERE id = 1"; // Assuming there's only one record to update
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "i", $newStatus);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Writer Registration status updated successfully.";
        } else {
            $error_message = "Failed to update registration status.";
        }
    } else {
        $error_message = "Invalid status value.";
    }
}

// Fetch the current registration status
$query = "SELECT regStatus FROM tblsettings WHERE id = 1"; // Assuming there's only one record
$result = mysqli_query($con, $query);
$currentStatus = mysqli_fetch_assoc($result)['regStatus'];
$currentStatusText = $currentStatus == 1 ? 'OPEN' : 'CLOSED';
$badgeClass = $currentStatus == 1 ? 'badge-subtle-success' : 'badge-subtle-danger';

// Check if the form is submitted
if (isset($_POST['adminStatus'])) {
    $newStatus = isset($_POST['newStatus']) ? (int) $_POST['newStatus'] : null;

    // Validate new status
    if ($newStatus !== null && ($newStatus === 0 || $newStatus === 1)) {
        // Update the registration status in the database
        $query = "UPDATE tblsettings SET regStatus = ? WHERE id = 2"; // Assuming there's only one record to update
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "i", $newStatus);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Admin Registration status updated successfully.";
        } else {
            $error_message = "Failed to update registration status.";
        }
    } else {
        $error_message = "Invalid status value.";
    }
}

// Fetch the current registration status
$query1 = "SELECT regStatus FROM tblsettings WHERE id = 2"; // Assuming there's only one record
$result1 = mysqli_query($con, $query1);
$currentStatus1 = mysqli_fetch_assoc($result1)['regStatus'];
$currentStatusText1 = $currentStatus1 == 1 ? 'OPEN' : 'CLOSED';
$badgeClass1 = $currentStatus1 == 1 ? 'badge-subtle-success' : 'badge-subtle-danger';

// Handle form submission for updating notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notificationSubmit'])) {
    $notificationText = $_POST['notificationText'];

    $sql = "UPDATE tblsettings SET description = ?, regStatus = 1 WHERE id = 3";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $notificationText);

    if ($stmt->execute()) {
        $message = "Notification updated successfully.";
    } else {
        $error_message = "Error updating notification: " . $stmt->error;
    }

    $stmt->close();
}

// Handle form submission for deleting notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notificationDelete'])) {
    $sql = "UPDATE tblsettings SET description = '', regStatus = 0 WHERE id = 3";
    $stmt = $con->prepare($sql);

    if ($stmt->execute()) {
        $message = "Notification deleted successfully.";
    } else {
        $error_message = "Error deleting notification: " . $stmt->error;
    }

    $stmt->close();
}

// Fetch current notification
$query = mysqli_query($con, "SELECT * FROM tblsettings WHERE id = 3");
$row = mysqli_fetch_assoc($query);
$currentNotification = $row['description'];

?>

    <div class="row">
    <div class="col-12">
        <?php if ($message): ?>
            <div class="alert alert-success border-0 d-flex align-items-center" role="alert">
                <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
                <p class="mb-0 flex-1"><?php echo $message; ?></p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                <p class="mb-0 flex-1"><?php echo $error_message; ?></p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="card mb-3 btn-reveal-trigger">
            <div class="card-header position-relative min-vh-25 mb-8">
                <?php
                $adminid = $_SESSION['odmsaid'];
                $sql = "SELECT * from tbladmin where email =:aid";
                $query = $dbh->prepare($sql);
                $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
                $query->execute();
                $results = $query->fetchAll(PDO::FETCH_OBJ);
                if ($query->rowCount() > 0) {
                foreach ($results as $row) {
                ?>
                <div class="cover-image">
                    <?php if ($row->coverImage == "1.jpg") { ?>
                    <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url(../profileimages/1.jpg);">
                        <?php } else { ?>
                        <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url('../profileimages/<?php echo $row->coverImage; ?>');">
                            <?php } ?>
                        </div>
                        <!--/.bg-holder-->
                        <label class="cover-image-file-input" data-bs-toggle="modal" data-bs-target="#updateCoverModal" style="cursor: pointer;">
                            <span class="fas fa-camera me-2"></span><span>Change cover photo</span>
                        </label>
                    </div>
                    <div class="avatar avatar-5xl avatar-profile shadow-sm img-thumbnail rounded-circle">
                        <div class="h-100 w-100 rounded-circle overflow-hidden position-relative">
                            <?php if ($row->Photo == "avatar.png") { ?>
                                <img src="../assets/img/team/avatar.png" width="200" alt="" data-dz-thumbnail="data-dz-thumbnail">
                            <?php } else { ?>
                                <img src="../profileimages/<?php echo $row->Photo; ?>" width="200" alt="" data-dz-thumbnail="data-dz-thumbnail">
                            <?php } ?>
                            <label class="mb-0 overlay-icon d-flex flex-center" data-bs-toggle="modal" data-bs-target="#updateModal">
                                <span class="bg-holder overlay overlay-0"></span>
                                <span class="z-1 text-white dark__text-white text-center fs-10">
                                <span class="fas fa-camera"></span>
                                <span class="d-block">Update</span>
                            </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cover Image Modal -->
    <div class="modal fade" id="updateCoverModal" tabindex="-1" aria-labelledby="updateCoverModalLabel" aria-hidden="true">
        <div class="modal-dialog mt-6" role="document">
            <div class="modal-content border-0">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1">
                        <h4 class="mb-0 text-white" id="cover-modal-label">Update your Cover Image</h4>
                    </div>
                    <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>>
                </div>
                <div class="modal-body py-4 px-5">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group mb-3">
                            <label for="cover-image">Choose a new cover image</label>
                            <input type="file" class="form-control-file" id="cover-image" name="cover-image" required>
                        </div>
                        <button type="submit" name="upload_cover_image"  class="btn btn-outline-primary w-50">Upload Cover Image</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Avatar Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
        <div class="modal-dialog mt-6" role="document">
            <div class="modal-content border-0">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1">
                        <h4 class="mb-0 text-white" id="avatar-modal-label">Update your Avatar</h4>
                    </div>
                    <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 px-5">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group mb-3">
                            <label for="profile-image">Choose a new profile image</label>
                            <input type="file" class="form-control-file" id="profile-image" name="profile-image" required>
                        </div>
                        <button type="submit" name="upload_image" class="btn btn-outline-primary w-50">Upload Avatar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-0">
        <div class="col-lg-8 pe-lg-2">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0 text-info">Profile Settings</h5>
                </div>
                <div class="card-body bg-body-tertiary">
                    <form class="row g-3" method="post" enctype="multipart/form-data">
                        <div class="col-lg-6 form-floating">
                            <input class="form-control" id="first-name" type="text" name="firstname" value="<?php echo $row->FirstName; ?>" />
                            <label for="first-name">First Name</label>
                        </div>
                        <div class="col-lg-6 form-floating">
                            <input class="form-control" id="last-name" type="text" name="lastname" value="<?php echo $row->LastName; ?>" />
                            <label for="last-name">Last Name</label>
                        </div>
                        <div class="col-lg-6 form-floating">
                            <input class="form-control" id="email1" type="email" name="email" value="<?php echo $row->email; ?>" />
                            <label for="email1">Email</label>
                        </div>
                        <div class="col-lg-6 form-floating">
                            <input class="form-control" id="email2" type="text" name="mobilenumber" value="<?php echo $row->MobileNumber; ?>" />
                            <label for="email2">Phone</label>
                        </div>
                        <div class="col-lg-6 form-floating">
                            <input class="form-control" id="user-name" type="text" name="username" value="<?php echo $row->username; ?>" />
                            <label for="user-name">Username</label>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-outline-primary w-100" type="submit" name="submit">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php
            if($row->superadmin == 1)
            {
                ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0 text-info">Writer Registration Settings</h5>
                    </div>
                    <div class="card-body bg-body-tertiary">
                        <!-- Display Current Registration Status -->
                        <p>Current Writer Registration Status: <span class="badge fs-10 <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($currentStatusText, ENT_QUOTES, 'UTF-8'); ?></span></p>
                        <!-- Form to Update Registration Status -->
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="newStatus" value="<?php echo $currentStatus == 1 ? 0 : 1; ?>">
                            <button class="btn btn-outline-primary w-100" type="submit" name="submitStatus">
                                <?php echo $currentStatus == 1 ? 'Close Registration' : 'Open Registration'; ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0 text-info">Admin Registration Settings</h5>
                    </div>
                    <div class="card-body bg-body-tertiary">
                        <!-- Display Current Registration Status -->
                        <p>Current Admin Registration Status: <span class="badge fs-10 <?php echo $badgeClass1; ?>"><?php echo htmlspecialchars($currentStatusText1, ENT_QUOTES, 'UTF-8'); ?></span></p>
                        <!-- Form to Update Registration Status -->
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="newStatus" value="<?php echo $currentStatus1 == 1 ? 0 : 1; ?>">
                            <button class="btn btn-outline-primary w-100" type="submit" name="adminStatus">
                                <?php echo $currentStatus1 == 1 ? 'Close Registration' : 'Open Registration'; ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0 text-info">Notifications Settings</h5>
                    </div>
                    <div class="card-body bg-body-tertiary">
                        <!-- Form to Update Notification -->
                        <!-- Form to Update Notification -->
                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="notificationText" class="form-label">Notification Text</label>
                                <textarea class="form-control" id="notificationText" name="notificationText" rows="3" required><?php echo htmlspecialchars($currentNotification, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <button class="btn btn-outline-primary me-1 mb-1" type="submit" name="notificationSubmit">Update Notification</button>
                            <button class="btn btn-outline-danger me-1 mb-1 float-end" type="submit" name="notificationDelete" onclick="return confirm('Are you sure you want to delete the notification?');">Delete Notification</button>
                        </form>
                    </div>
                </div>
                <?php
            } else {
                ?>
                <?php
            } ?>
        </div>
        <div class="col-lg-4 ps-lg-2">
            <div class="sticky-sidebar">
                <!-- ════════════════════════════════════════════════
                     FINANCIAL DASHBOARD — PIN LOCK SETTINGS
                     ════════════════════════════════════════════════ -->
                <div class="card mb-3 overflow-hidden" id="pinLockSettingsCard">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 text-info">
                            <i class="fas fa-shield-alt me-2"></i>PIN Lock
                        </h5>
                        <span class="badge" id="pinStatusBadge" style="font-size:.75rem;">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </div>
                    <div class="card-body bg-body-tertiary">

                        <!-- Description -->
                        <p class="text-muted fs-10 mb-3">
                            Protect <strong>Financial Dashboard</strong> with a numeric PIN.
                            When a PIN is set, visitors must enter it before the page content is revealed.
                        </p>

                        <!-- ── Set / Change PIN form ── -->
                        <div id="pinSetSection">
                            <h6 class="mb-2 fw-bold" id="pinFormTitle">Set a New PIN</h6>
                            <!-- Current PIN field — only shown when changing an existing PIN -->
                            <div class="mb-2" id="currentPinField" style="display:none;">
                                <label class="form-label fs-10">Current PIN <small class="text-muted">(required to change)</small></label>
                                <input type="password" inputmode="numeric" pattern="[0-9]*"
                                       class="form-control" id="currentPinInput" maxlength="8"
                                       placeholder="Enter current PIN" autocomplete="current-password">
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs-10">New PIN <small class="text-muted">(4–8 digits)</small></label>
                                <input type="password" inputmode="numeric" pattern="[0-9]*"
                                       class="form-control" id="newPinInput" maxlength="8"
                                       placeholder="e.g. 1234" autocomplete="new-password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fs-10">Confirm New PIN</label>
                                <input type="password" inputmode="numeric" pattern="[0-9]*"
                                       class="form-control" id="confirmPinInput" maxlength="8"
                                       placeholder="Repeat new PIN" autocomplete="new-password">
                            </div>
                            <button class="btn btn-outline-primary w-100" id="savePinBtn" onclick="saveDashboardPin()">
                                <i class="fas fa-save me-1"></i> Save PIN
                            </button>
                        </div>

                        <!-- ── Remove PIN section (shown when PIN exists) ── -->
                        <div id="pinRemoveSection" style="display:none;" class="mt-3 border-top pt-3">
                            <h6 class="text-warning mb-2"><i class="fas fa-unlock-alt me-1"></i>Remove PIN</h6>
                            <p class="text-muted fs-10">Enter your current PIN to disable the lock.</p>
                            <div class="mb-2">
                                <input type="password" inputmode="numeric" pattern="[0-9]*"
                                       class="form-control" id="removePinInput" maxlength="8"
                                       placeholder="Current PIN" autocomplete="current-password">
                            </div>
                            <button class="btn btn-outline-danger w-100" onclick="removeDashboardPin()">
                                <i class="fas fa-trash me-1"></i> Remove PIN
                            </button>
                        </div>

                        <!-- ── Status / feedback message ── -->
                        <div id="pinSettingsMsg" class="mt-3" style="display:none;"></div>

                        <!-- ── Info box ── -->
                        <div class="alert alert-secondary mt-3 mb-0 fs-10">
                            <i class="fas fa-info-circle me-1"></i>
                            The PIN lock blurs the dashboard page and requires entry before any data is visible.
                            Sessions auto-lock after <strong>30 minutes</strong> of inactivity.
                        </div>

                        <!-- ── Forgot PIN ── -->
                        <div class="mt-3 text-center border-top pt-3">
                            <p class="text-muted fs-10 mb-2">Forgot your PIN?</p>
                            <button class="btn btn-sm btn-outline-warning w-100" id="forgotPinBtn" onclick="requestPinReset()">
                                <i class="fas fa-envelope me-1"></i> Send Reset Link to My Email
                            </button>
                            <div id="forgotPinMsg" class="mt-2" style="display:none;"></div>
                        </div>
                    </div>

                </div>
                <!-- ════════════════════════════════════════════════ end PIN -->

                <!-- ════════════════════════════════════════════════
                     FINANCIAL DASHBOARD — PREFERRED CURRENCY
                     ════════════════════════════════════════════════ -->
                <div class="card mb-3 overflow-hidden" id="preferredCurrencyCard">
                    <div class="card-header">
                        <h5 class="mb-0 text-info">
                            <i class="fas fa-coins me-2"></i>Preferred Currency
                        </h5>
                    </div>
                    <div class="card-body bg-body-tertiary">
                        <p class="text-muted fs-10 mb-3">
                            Charts and totals on the <strong>Financial Dashboard</strong> are converted into this
                            currency. Individual accounts still display in whichever currency they were created with.
                        </p>
                        <form method="post">
                            <div class="mb-3">
                                <label for="preferredCurrencySelect" class="form-label">Display currency</label>
                                <select class="form-select" id="preferredCurrencySelect" name="preferred_currency">
                                    <?php foreach ($currencyOptions as $code => $info): ?>
                                        <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo ($code === $currentPreferredCurrency) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($code . ' — ' . $info['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button class="btn btn-outline-primary w-100" type="submit" name="update_currency">
                                <i class="fas fa-save me-1"></i> Save Preferred Currency
                            </button>
                        </form>
                    </div>
                </div>
                <!-- ════════════════════════════════════════════════ end Preferred Currency -->

                <div class="card mb-3 overflow-hidden">
                    <div class="card-header">
                        <h5 class="mb-0 text-info">Change Password</h5>
                    </div>
                    <div class="card-body bg-body-tertiary">
                        <form method="post">
                            <div class="form-floating mb-3">
                                <input class="form-control" id="old-password" type="password" name="old_password" placeholder="Old Password" />
                                <label for="old-password">Old Password</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="new-password" type="password" name="new_password" placeholder="New Password" />
                                <label for="new-password">New Password</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="confirm-password" type="password" name="confirm_password" placeholder="Confirm Password" />
                                <label for="confirm-password">Confirm Password</label>
                            </div>
                            <button class="btn btn-outline-primary d-block w-100" type="submit" name="update_password">Update Password</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 text-info">Danger Zone</h5>
                    </div>
                    <div class="card-body bg-body-tertiary">
                        <h5 class="fs-9">Delete this account</h5>
                        <p class="fs-10">Once you delete an account, there is no going back. Please be certain.</p><a class="btn btn-falcon-danger d-block" href="#!">Deactivate Account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    }
    }
    ?>
    <script>
        window.requestPinReset = async function () {
            const btn = document.getElementById('forgotPinBtn');
            const msg = document.getElementById('forgotPinMsg');

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';

            try {
                const res  = await fetch('pin_reset', {
                    method : 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body   : JSON.stringify({ action: 'request_reset' })
                });
                const data = await res.json();

                msg.style.display = 'block';
                msg.innerHTML = `<div class="alert alert-${data.success ? 'success' : 'danger'} py-2 mb-0 fs-10">${data.message}</div>`;

                if (data.success) {
                    // Disable button for 60 s to prevent spam
                    let countdown = 60;
                    const interval = setInterval(() => {
                        countdown--;
                        btn.innerHTML = `<i class="fas fa-envelope me-1"></i> Resend in ${countdown}s`;
                        if (countdown <= 0) {
                            clearInterval(interval);
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-envelope me-1"></i> Send Reset Link to My Email';
                        }
                    }, 1000);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-envelope me-1"></i> Send Reset Link to My Email';
                }
            } catch (e) {
                msg.style.display = 'block';
                msg.innerHTML = '<div class="alert alert-danger py-2 mb-0 fs-10">Request failed. Please try again.</div>';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-envelope me-1"></i> Send Reset Link to My Email';
            }
        };
    </script>
    <script>
        (function() {
            const API = 'pin_api';

            /* ── Load current PIN status on page load ── */
            async function loadPinStatus() {
                try {
                    const res  = await fetch(API, {
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({action:'check_pin_status'})
                    });
                    const data = await res.json();
                    applyPinStatus(data.pin_set);
                } catch(e) {
                    document.getElementById('pinStatusBadge').textContent = 'Unavailable';
                    document.getElementById('pinStatusBadge').className   = 'badge bg-secondary';
                }
            }

            function applyPinStatus(pinSet) {
                const badge          = document.getElementById('pinStatusBadge');
                const removeDiv      = document.getElementById('pinRemoveSection');
                const titleEl        = document.getElementById('pinFormTitle');
                const currentField   = document.getElementById('currentPinField');
                const currentInput   = document.getElementById('currentPinInput');

                if (pinSet) {
                    badge.innerHTML   = '<i class="fas fa-lock me-1"></i> PIN Active';
                    badge.className   = 'badge bg-success';
                    removeDiv.style.display  = 'block';
                    currentField.style.display = 'block';
                    titleEl.textContent      = 'Change PIN';
                } else {
                    badge.innerHTML   = '<i class="fas fa-lock-open me-1"></i> No PIN';
                    badge.className   = 'badge bg-secondary';
                    removeDiv.style.display  = 'none';
                    currentField.style.display = 'none';
                    if (currentInput) currentInput.value = '';
                    titleEl.textContent      = 'Set a New PIN';
                }
            }

            /* ── Save / Change PIN ── */
            window.saveDashboardPin = async function() {
                const currentInput = document.getElementById('currentPinInput');
                const currentPin   = currentInput ? currentInput.value : '';
                const pin          = document.getElementById('newPinInput').value;
                const confirm      = document.getElementById('confirmPinInput').value;
                const isChanging   = currentInput && currentInput.closest('#currentPinField').style.display !== 'none';

                // If changing an existing PIN, current PIN is required
                if (isChanging && !currentPin) {
                    showMsg('Please enter your current PIN first.', 'danger'); return;
                }
                if (isChanging && !/^\d{4,8}$/.test(currentPin)) {
                    showMsg('Current PIN must be numeric digits.', 'danger'); return;
                }
                if (!/^\d{4,8}$/.test(pin)) {
                    showMsg('New PIN must be 4–8 numeric digits.', 'danger'); return;
                }
                if (pin !== confirm) {
                    showMsg('New PINs do not match.', 'danger'); return;
                }

                const btn = document.getElementById('savePinBtn');
                btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

                try {
                    const payload = {action: 'set_pin', pin, confirm};
                    if (isChanging) payload.current_pin = currentPin;

                    const res  = await fetch(API, {
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();

                    if (data.success) {
                        showMsg('<i class="fas fa-check-circle me-1"></i>' + data.message, 'success');
                        document.getElementById('currentPinInput').value = '';
                        document.getElementById('newPinInput').value     = '';
                        document.getElementById('confirmPinInput').value = '';
                        applyPinStatus(true);
                    } else {
                        showMsg(data.message, 'danger');
                    }
                } catch(e) {
                    showMsg('Request failed. Please try again.', 'danger');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save me-1"></i> Save PIN';
                }
            };

            /* ── Remove PIN ── */
            window.removeDashboardPin = async function() {
                const pin = document.getElementById('removePinInput').value;
                if (!pin) { showMsg('Enter your current PIN first.', 'danger'); return; }

                if (!confirm('Remove the Financial Dashboard PIN lock?')) return;

                try {
                    const res  = await fetch(API, {
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({action:'remove_pin', pin})
                    });
                    const data = await res.json();

                    if (data.success) {
                        showMsg('<i class="fas fa-check-circle me-1"></i>' + data.message, 'success');
                        document.getElementById('removePinInput').value = '';
                        applyPinStatus(false);
                    } else {
                        showMsg(data.message, 'danger');
                    }
                } catch(e) {
                    showMsg('Request failed. Please try again.', 'danger');
                }
            };

            function showMsg(html, type) {
                const el = document.getElementById('pinSettingsMsg');
                el.style.display = 'block';
                el.innerHTML = `<div class="alert alert-${type} py-2 mb-0 fs-10">${html}</div>`;
                setTimeout(() => { el.style.display = 'none'; }, 5000);
            }

            // Boot
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', loadPinStatus);
            } else {
                loadPinStatus();
            }
        })();
    </script>

<?php include "footer.php"; ?>
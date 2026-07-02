<?php
include "head.php";

$message = ''; // Initialize an empty message variable
$error_message = ''; // Initialize an empty error message variable

if (isset($_POST['upload_cover_image'])) {
    $adminid = $_SESSION['sessionWriter'];

    // Handle file upload
    if (isset($_FILES['cover-image']) && $_FILES['cover-image']['error'] == 0) {
        $target_dir = "profileimages/";
        $file_name = basename($_FILES["cover-image"]["name"]);
        $target_file = $target_dir . $file_name;

        // Check if file is an actual image
        $check = getimagesize($_FILES["cover-image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["cover-image"]["tmp_name"], $target_file)) {
                // Update the cover image in the database
                $sql = "UPDATE tblwriters SET coverImage=:coverImage WHERE email=:aid";
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
    $adminid = $_SESSION['sessionWriter'];

    // Handle file upload
    if (isset($_FILES['profile-image']) && $_FILES['profile-image']['error'] == 0) {
        $target_dir = "profileimages/";
        $file_name = basename($_FILES["profile-image"]["name"]);
        $target_file = $target_dir . $file_name;

        // Check if file is an actual image
        $check = getimagesize($_FILES["profile-image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["profile-image"]["tmp_name"], $target_file)) {
                // Update the profile image in the database
                $sql = "UPDATE tblwriters SET Photo=:photo WHERE email=:aid";
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
    $adminid = $_SESSION['sessionWriter'];
    $AName = $_POST['username'];
    $fName = $_POST['firstname'];
    $lName = $_POST['lastname'];
    $mobno = $_POST['mobilenumber'];
    $email = $_POST['email'];

    // Handle file upload
    if (isset($_FILES['profile-image']) && $_FILES['profile-image']['error'] == 0) {
        $target_dir = "profileimages/";
        $target_file = $target_dir . basename($_FILES["profile-image"]["name"]);
        if (move_uploaded_file($_FILES["profile-image"]["tmp_name"], $target_file)) {
            // Update the profile image in the database
            $sql = "UPDATE tblwriters SET Photo=:photo WHERE email=:aid";
            $query = $dbh->prepare($sql);
            $query->bindParam(':photo', $target_file, PDO::PARAM_STR);
            $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
            $query->execute();
        } else {
            $error_message = 'Failed to upload profile image.';
        }
    }

    if (empty($error_message)) {
        $sql = "UPDATE tblwriters SET username=:username, FirstName=:firstname, LastName=:lastname, phone=:mobilenumber, email=:email WHERE email=:aid";
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
    $adminid = $_SESSION['sessionWriter'];
    $oldPassword = $_POST['old_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Password validation
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $newPassword)) {
        $error_message = 'Password must be at least 8 characters long, contain at least one number, one lowercase letter, and one uppercase letter.';
    } else {
        // Verify old password
        $sql = "SELECT Password FROM tblwriters WHERE email=:aid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_OBJ);

        if ($result && password_verify($oldPassword, $result->Password)) {
            if ($newPassword === $confirmPassword) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $sql = "UPDATE tblwriters SET Password=:newpassword WHERE email=:aid";
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
}
?>
<title>Settings | iTasker</title>
<?php include "navi.php";?>

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
                $adminid = $_SESSION['sessionWriter'];
                $sql = "SELECT * from tblwriters where email =:aid";
                $query = $dbh->prepare($sql);
                $query->bindParam(':aid', $adminid, PDO::PARAM_STR);
                $query->execute();
                $results = $query->fetchAll(PDO::FETCH_OBJ);
                if ($query->rowCount() > 0) {
                foreach ($results as $row) {
                ?>
                <div class="cover-image">
                    <?php if ($row->coverImage == "1.jpg") { ?>
                    <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url(profileimages/1.jpg);">
                    <?php } else { ?>
                        <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url('profileimages/<?php echo $row->coverImage; ?>');">
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
                            <img src="assets/img/team/avatar.png" width="200" alt="" data-dz-thumbnail="data-dz-thumbnail">
                        <?php } else { ?>
                            <img src="profileimages/<?php echo $row->Photo; ?>" width="200" alt="" data-dz-thumbnail="data-dz-thumbnail">
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

    <div class="modal fade" id="overdraft-view-modal" tabindex="-1" role="dialog" aria-labelledby="authentication-modal-label" aria-hidden="true">
        <div class="modal-dialog mt-6" role="document">
            <div class="modal-content border-0">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1">
                        <h4 class="mb-0 text-white" id="authentication-modal-label">Edit Overdraft</h4>
                    </div>
                    <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 px-5">
                    <div id="modal-alert" class="alert d-none"></div>
                    <form id="overdraft-form">
                        <input type="hidden" id="overdraft-id" name="id">
                        <div class="mb-3">
                            <label class="form-label" for="modal-auth-name">Writer</label>
                            <input class="form-control" type="text" autocomplete="on" name="writer" id="modal-auth-name" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="modal-auth-amount">Amount</label>
                            <input class="form-control" type="number" autocomplete="on" name="amount" id="modal-auth-amount" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="modal-auth-date">Date</label>
                            <input class="form-control datetimepicker" type="text" autocomplete="on" name="od_date" id="modal-auth-date" placeholder="YYYY-mm.dd H:i" data-options='{"enableTime":true,"dateFormat":"Y-m-d H:i","disableMobile":true,"allowInput":true}' />
                        </div>
                        <div class="mb-3">
                            <button class="btn btn-primary d-block w-100 mt-3" type="submit">Update Overdraft</button>
                        </div>
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
                            <input class="form-control" id="email1" type="email" name="email" value="<?php echo $row->email; ?>" readonly />
                            <label for="email1">Email</label>
                        </div>
                        <div class="col-lg-6 form-floating">
                            <input class="form-control" id="email2" type="text" name="mobilenumber" value="<?php echo $row->phone; ?>" />
                            <label for="email2">Phone</label>
                        </div>
                        <div class="col-lg-6 form-floating">
                            <input class="form-control" id="user-name" type="text" name="username" value="<?php echo $row->username; ?>" readonly />
                            <label for="user-name">Username</label>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-outline-primary w-100" type="submit" name="submit">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4 ps-lg-2">
            <div class="sticky-sidebar">
                <div class="card mb-3 overflow-hidden">
                    <div class="card-header">
                        <h5 class="mb-0 text-info">Change Password</h5>
                    </div>
                    <div class="card-body bg-body-tertiary">
                        <form method="post" onsubmit="return validateForm()">
                            <div class="form-floating mb-3">
                                <input class="form-control" id="old-password" type="password" name="old_password" placeholder="Old Password" required />
                                <label for="old-password">Old Password</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="new-password" type="password" name="new_password" placeholder="New Password" required />
                                <label for="new-password">New Password</label>
                                <div id="new-password-error" class="text-danger mt-2"></div>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="confirm-password" type="password" name="confirm_password" placeholder="Confirm Password" required />
                                <label for="confirm-password">Confirm Password</label>
                                <div id="confirm-password-error" class="text-danger mt-2"></div>
                            </div>
                            <button class="btn btn-outline-primary d-block w-100" type="submit" name="update_password">Update Password</button>
                        </form>
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
        document.getElementById('new-password').addEventListener('input', validatePassword);
        document.getElementById('confirm-password').addEventListener('input', validateConfirmPassword);

        function validatePassword() {
            const password = document.getElementById('new-password').value;
            const errorDiv = document.getElementById('new-password-error');
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

            if (!regex.test(password)) {
                errorDiv.textContent = 'Password must be at least 8 characters long, contain at least one number, one lowercase letter, and one uppercase letter.';
            } else {
                errorDiv.textContent = '';
            }
        }

        function validateConfirmPassword() {
            const password = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const errorDiv = document.getElementById('confirm-password-error');

            if (password !== confirmPassword) {
                errorDiv.textContent = 'Passwords do not match.';
            } else {
                errorDiv.textContent = '';
            }
        }

        function validateForm() {
            validatePassword();
            validateConfirmPassword();

            const newPasswordError = document.getElementById('new-password-error').textContent;
            const confirmPasswordError = document.getElementById('confirm-password-error').textContent;

            return newPasswordError === '' && confirmPasswordError === '';
        }
    </script>
<?php include "footer.php"; ?>

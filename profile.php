<?php
include "head.php";
?>

<?php
$allCompleted = "";
$query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND email ='$aid'";
$result = mysqli_query($con, $query);
if ($result) {
    $rowProfile = mysqli_fetch_assoc($result);
    $count = $rowProfile['taskCount'];
    if ($count > 0) {
        $allCompleted = $count; // Set the count to output variable
    } else {
        $allCompleted = "0"; // Set "0" if count is 0
    }
} else {
    $allCompleted = "No data"; // Set "No Data" if query fails
}
?>

<?php
$allProgress = "";
$query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND email ='$aid'";
$result = mysqli_query($con, $query);
if ($result) {
    $rowProfile = mysqli_fetch_assoc($result);
    $count = $rowProfile['taskCount'];
    if ($count > 0) {
        $allProgress = $count; // Set the count to output variable
    } else {
        $allProgress = "0"; // Set "0" if count is 0
    }
} else {
    $allProgress = "No data"; // Set "No Data" if query fails
}
?>

<?php
$totalPaidFormatted = "No data"; // Default message if the query fails
$totalPaidRaw = 0; // Raw total for JavaScript
$query = mysqli_query($con, "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND email ='$aid'");
if ($query) {
    $rowProfile = mysqli_fetch_array($query);
    if ($rowProfile && $rowProfile['total'] !== null) {
        $totalPaidRaw = $rowProfile['total']; // Keep the raw total
        $totalPaidFormatted = 'Ksh. ' . number_format($rowProfile['total'], 2);
    } else {
        $totalPaidFormatted = 'Ksh. 0.00';
    }
} else {
    $totalPaidFormatted = "Error: " . mysqli_error($con);
}
?>

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
    foreach($results as $rowProfile)
    {
        ?>
        <title>My Profile | iTasker</title>
        <?php include "navi.php";?>
          <div class="card mb-3">
            <div class="card-header position-relative min-vh-25 mb-7">
                <?php if ($rowProfile->coverImage == "1.jpg") { ?>
                <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url(profileimages/1.jpg);">
                <?php } else { ?>
                    <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url('profileimages/<?php echo $rowProfile->coverImage; ?>');">
                <?php } ?>
              </div>
              <!--/.bg-holder-->

              <div class="avatar avatar-5xl avatar-profile">
                  <?php
                  if($rowProfile->Photo=="avatar.png")
                  {
                      ?>
                      <img class="rounded-circle img-thumbnail shadow-sm" src="assets/img/team/avatar.png" width="200" alt="" />
                      <?php
                  } else {
                      ?>
                      <img class="rounded-circle img-thumbnail shadow-sm" src="profileimages/<?php  echo $rowProfile->Photo;?>" width="200" alt="">
                      <?php
                  } ?>
              </div>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-lg-6">
                  <h4 class="mb-1 text-info"> <?php  echo $rowProfile->FirstName;?> <?php  echo $rowProfile->LastName;?>
                      <?php
                      if($rowProfile->is_verified == 1)
                      {
                          ?>
                      <span data-bs-toggle="tooltip" data-bs-placement="right" title="Verified">
                          <small class="fa fa-check-circle text-primary" data-fa-transform="shrink-4 down-2"></small>
                      </span>
                          <?php
                      } else {
                          ?>
                      <span data-bs-toggle="tooltip" data-bs-placement="right" title="Unverified">
                          <small class="fa fa-times-circle text-secondary" data-fa-transform="shrink-4 down-2"></small>
                      </span>
                          <?php
                      } ?>

                  </h4>
                  <h5 class="fs-9 fw-normal text-primary"><?php  echo $rowProfile->email;?> </h5>
                  <p class="text-900"><?php  echo $rowProfile->username;?> | <?php  echo $rowProfile->phone;?></p>
                  <a class="btn btn-outline-primary btn-sm px-3" type="button" href="settings">Edit Profile</a>
                  <div class="border-bottom border-dashed my-4 d-lg-none"></div>
                </div>
                <div class="col ps-2 ps-lg-5"><div class="d-flex align-items-center mb-2" href="#"><span class="fas fa-user-secret fs-8 me-2 text-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Member Since" data-fa-transform="grow-2"></span>
                    <div class="flex-1">
                      <h6 class="mb-0 text-primary"><?php
                          $adminRegDate = $rowProfile->created_at;
                          $formattedDate = date("jS F, Y", strtotime($adminRegDate));
                          echo $formattedDate;
                              ?>
                      </h6>
                    </div>
                  </div><div class="d-flex align-items-center mb-2" href="#"><span class="fas fa-archive fs-8 me-2 text-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Completed Tasks" data-fa-transform="grow-2"></span>
                    <div class="flex-1">
                        <h6 class="mb-0 text-primary"><?php echo $allCompleted ?> Completed tasks</h6>
                    </div>
                  </div><div class="d-flex align-items-center mb-2" href="#"><span class="fas fa-spinner fs-8 me-2 text-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Tasks Pending" data-fa-transform="grow-2"></span>
                    <div class="flex-1">
                        <h6 class="mb-0 text-primary"><?php echo $allProgress ?> Task(s) pending</h6>
                    </div>
                  </div><div class="d-flex align-items-center mb-2" href="#"><span class="fas fa-wallet fs-8 me-2 text-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Total Earned" data-fa-transform="grow-2"></span>
                    <div class="flex-1">
                        <h6 class="mb-0 text-primary"><?php echo $totalPaidFormatted ?></h6>
                    </div>
                  </div></div>
              </div>
            </div>
          </div>
          <div class="row g-0">
            <div class="col-lg-12">
              <div class="card mb-3">
                <div class="card-header bg-body-tertiary">
                  <h5 class="mb-0 text-info">Intro</h5>
                </div>
                <div class="card-body text-justify">
                  <p class="mb-0  text-primary">Dedicated, passionate, and accomplished Full Stack Developer with 9+ years of progressive experience working as an Independent Contractor for Google and developing and growing my educational social network that helps others learn programming, web design, game development, networking.</p>
                  <div class="collapse show" id="profile-intro">
                    <p class="mt-3  text-primary">I’ve acquired a wide depth of knowledge and expertise in using my technical skills in programming, computer science, software development, and mobile app development to developing solutions to help organizations increase productivity, and accelerate business performance. </p>
                    <p class="text-primary">It’s great that we live in an age where we can share so much with technology but I’m but I’m ready for the next phase of my career, with a healthy balance between the virtual world and a workplace where I help others face-to-face.</p>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary p-0 border-top">
                  <button class="btn btn-link d-block w-100 btn-intro-collapse" type="button" data-bs-toggle="collapse" data-bs-target="#profile-intro" aria-expanded="true" aria-controls="profile-intro">Show <span class="less">less<span class="fas fa-chevron-up ms-2 fs-11"></span></span><span class="full">full<span class="fas fa-chevron-down ms-2 fs-11"></span></span></button>
                </div>
              </div>
            </div>
          </div>
        <?php
    }
} ?>
<?php
include "footer.php";
?>
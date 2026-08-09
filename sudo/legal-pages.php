<?php include "head.php";?>
<?php requireCapability($currentAdminRole, 'manage_settings'); ?>
<?php csrf_verify_or_redirect(); ?>
    <title>iTasker | Terms &amp; Privacy</title>
<?php include "navi.php";
require_once __DIR__ . '/../legal-content-defaults.php';

// ── Save / reset handlers ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['legal_action'])) {
    $pageKey = $_POST['page_key'] ?? '';

    if (!in_array($pageKey, ['terms', 'privacy'], true)) {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Unknown page.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } elseif ($_POST['legal_action'] === 'save') {
        $content = $_POST['content'] ?? '';
        $stmt = mysqli_prepare($con,
            "INSERT INTO tbl_legal_pages (page_key, content, updated_by, updated_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE content = VALUES(content), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sss', $pageKey, $content, $aid);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $ok = false;
        }

        $_SESSION['alert'] = $ok
            ? '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
                <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
                <p class="mb-0 flex-1">' . ucfirst($pageKey) . ' page updated.</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>'
            : '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                <p class="mb-0 flex-1">Could not save - has db-migrations/2026_08_09_add_legal_pages.sql been run yet?</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    } elseif ($_POST['legal_action'] === 'reset') {
        $stmt = mysqli_prepare($con, "UPDATE tbl_legal_pages SET content = NULL, updated_by = ?, updated_at = NOW() WHERE page_key = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $aid, $pageKey);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $_SESSION['alert'] = '<div class="alert alert-info border-0 d-flex align-items-center" role="alert">
            <div class="bg-info me-3 icon-item"><span class="fas fa-undo text-white fs-6"></span></div>
            <p class="mb-0 flex-1">' . ucfirst($pageKey) . ' page reset to the built-in default text.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }

    header('Location: legal-pages');
    exit;
}

if (isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']);
}

$legalTerms   = get_legal_page($con, 'terms');
$legalPrivacy = get_legal_page($con, 'privacy');
?>

    <!-- Page header -->
    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Terms <span class="text-info fw-medium">&amp; Privacy</span></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col">
            <div class="card">
                <div class="card-header p-0 border-bottom">
                    <ul class="nav nav-tabs card-header-tabs px-3 pt-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="terms-tab" data-bs-toggle="tab" data-bs-target="#terms-pane" type="button" role="tab">
                                Terms of Service
                                <?php if (!$legalTerms['is_custom']): ?><span class="badge badge-subtle-secondary rounded-pill ms-1">Default</span><?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy-pane" type="button" role="tab">
                                Privacy Policy
                                <?php if (!$legalPrivacy['is_custom']): ?><span class="badge badge-subtle-secondary rounded-pill ms-1">Default</span><?php endif; ?>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="terms-pane" role="tabpanel">
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="page_key" value="terms" />
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <p class="mb-0 text-600 fs-10">
                                        <?php if ($legalTerms['is_custom'] && $legalTerms['updated_at']): ?>
                                            Last edited <?= date('jS M Y, g:i A', strtotime($legalTerms['updated_at'])) ?>
                                        <?php else: ?>
                                            Showing the built-in default text - not yet customized.
                                        <?php endif; ?>
                                    </p>
                                    <a href="../terms" target="_blank" rel="noopener" class="fs-10">Preview live page <span class="fas fa-external-link-alt ms-1"></span></a>
                                </div>
                                <textarea class="tinymce-legal" name="content" id="terms-content"><?= $legalTerms['content'] ?></textarea>
                                <div class="mt-3 d-flex gap-2">
                                    <button class="btn btn-primary" type="submit" name="legal_action" value="save">Save Terms of Service</button>
                                    <?php if ($legalTerms['is_custom']): ?>
                                        <button class="btn btn-outline-secondary" type="submit" name="legal_action" value="reset" formnovalidate onclick="return confirm('Reset the Terms of Service to the built-in default text? Your custom edits will be lost.');">Reset to Default</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="privacy-pane" role="tabpanel">
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="page_key" value="privacy" />
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <p class="mb-0 text-600 fs-10">
                                        <?php if ($legalPrivacy['is_custom'] && $legalPrivacy['updated_at']): ?>
                                            Last edited <?= date('jS M Y, g:i A', strtotime($legalPrivacy['updated_at'])) ?>
                                        <?php else: ?>
                                            Showing the built-in default text - not yet customized.
                                        <?php endif; ?>
                                    </p>
                                    <a href="../privacy" target="_blank" rel="noopener" class="fs-10">Preview live page <span class="fas fa-external-link-alt ms-1"></span></a>
                                </div>
                                <textarea class="tinymce-legal" name="content" id="privacy-content"><?= $legalPrivacy['content'] ?></textarea>
                                <div class="mt-3 d-flex gap-2">
                                    <button class="btn btn-primary" type="submit" name="legal_action" value="save">Save Privacy Policy</button>
                                    <?php if ($legalPrivacy['is_custom']): ?>
                                        <button class="btn btn-outline-secondary" type="submit" name="legal_action" value="reset" formnovalidate onclick="return confirm('Reset the Privacy Policy to the built-in default text? Your custom edits will be lost.');">Reset to Default</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    tinymce.init({
        selector: '.tinymce-legal',
        height: 520,
        menubar: false,
        skin: 'oxide',
        plugins: 'advlist autolink lists link table code fullscreen wordcount',
        toolbar: 'undo redo | blocks | bold italic underline | forecolor | bullist numlist outdent indent | link table | removeformat code fullscreen',
        paste_data_images: false
    });

    // Keep the tab shown after a save reflected in the URL hash so a page
    // reload/redirect doesn't silently drop the admin back on the Terms tab
    // after they were editing Privacy.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var activePane = form.closest('.tab-pane');
                if (activePane) {
                    sessionStorage.setItem('legalPagesActiveTab', activePane.id);
                }
            });
        });
        var lastTab = sessionStorage.getItem('legalPagesActiveTab');
        if (lastTab === 'privacy-pane') {
            var trigger = document.getElementById('privacy-tab');
            if (trigger) new bootstrap.Tab(trigger).show();
        }
    });
</script>

<?php include "footer.php"; ?>

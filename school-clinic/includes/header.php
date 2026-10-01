<?php
/**
 * Legacy backward-compatibility wrapper for header.
 * Provides the HTML head and header layout for old-style pages.
 * Requires that config/db.php has been included (starts session, creates $pdo).
 */
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> &middot; <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
    <div class="shell-header">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <img src="<?= e(url('assets/images/ncst.png')) ?>" alt="NCST logo" class="auth-logo-image">
                    <h4 class="mb-0"><?= e(get_setting($pdo, 'school_name', APP_NAME)) ?></h4>
                    <small><?= e(get_setting($pdo, 'clinic_name', 'School Health Clinic')) ?></small>
                </div>
                <div class="col-md-9 text-end">
                    <?php if (is_logged_in()): ?>
                        <a href="<?= e(url('auth/logout.php')) ?>" class="btn btn-outline-secondary btn-sm">Logout</a>
                        <span class="ms-3"><?= e($_SESSION['full_name'] ?: $_SESSION['username']) ?></span>
                    <?php else: ?>
                        <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-outline-secondary btn-sm">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
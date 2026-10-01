<?php
/**
 * Dashboard layout start.
 * Provides the HTML head, sidebar, topbar, and opens the content area.
 * Requires that config/db.php has been included.
 * Sets $page_title if not already set.
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
    <?php $__css = dirname(__DIR__) . '/assets/css/style.css'; ?>
    <link href="<?= e(url('assets/css/style.css')) ?>?v=<?= file_exists($__css) ? filemtime($__css) : time() ?>" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php require __DIR__ . '/topbar.php'; ?>
        <main class="main-content">
            <div class="content-area">
                <div class="container-fluid">
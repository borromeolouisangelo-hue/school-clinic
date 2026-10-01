<?php
/**
 * Topbar component.
 * Provides the top navigation bar for dashboard pages.
 * Requires that config/db.php has been included and session is started.
 */
$unreadNotifCount = 0;
if (is_logged_in()) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $unreadNotifCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // Silently fail
    }
}
$fullName = is_logged_in() ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') : 'User';
$username = is_logged_in() ? ($_SESSION['username'] ?? 'User') : 'User';
$accountType = is_logged_in() ? ($_SESSION['account_type'] ?? 'user') : 'user';
?>
<header class="topbar">
    <div class="d-flex align-items-center">
        <button class="sidebar-toggle me-3" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="appSidebar">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="page-title mb-0"><?= e($page_title ?? 'Dashboard') ?></h1>
    </div>

    <div class="topbar-actions" style="display: flex; align-items: center;">
        <div class="dropdown">
            <button class="btn dropdown-toggle" type="button" id="notifBellBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                <?php /* Always rendered: main.js captures #notifBadge at load and
                         toggles d-none itself (updateNotifBadge), so the span must
                         exist even when the unread count is 0. */ ?>
                <span class="badge rounded-pill bg-danger<?= $unreadNotifCount > 0 ? '' : ' d-none' ?>" id="notifBadge"><?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notifBellBtn">
                <div class="notification-list" id="notifList">
                    <?php if ($unreadNotifCount === 0): ?>
                        <div class="text-muted py-3 text-center">No notifications</div>
                    <?php else: ?>
                        <!-- Notifications loaded via SSE -->
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dropdown ms-2">
            <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="btn-label"><?= e($fullName) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header"><?= e($fullName) ?></h6></li>
                <li class="px-3 pb-2"><span class="role-badge"><?= e(role_badge_label()) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e(url('user/profile.php')) ?>"><i class="bi bi-person me-2"></i> Profile</a></li>
                <li><a class="dropdown-item" href="<?= e(url('user/settings.php')) ?>"><i class="bi bi-gear me-2"></i> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= e(url('auth/logout.php')) ?>"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</header>
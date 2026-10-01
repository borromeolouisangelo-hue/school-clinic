<?php
/**
 * Notification center — all roles.
 * View, mark as read, and manage notifications.
 */
require_once __DIR__ . '/config/db.php';
require_login();

$page_title = 'Notifications';
$uid = (int)current_user()['id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('notifications.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {
        $count = mark_all_notifications_as_read($pdo, $uid);
        set_flash('success', "Marked $count notification(s) as read.");
        redirect('notifications.php');
    }

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            mark_notification_as_read($pdo, $id, $uid);
        }
        redirect('notifications.php');
    }
}

// Fetch notifications
$filter = $_GET['filter'] ?? 'all';
$where = 'WHERE user_id = ?';
$params = [$uid];
if ($filter === 'unread') {
    $where .= ' AND is_read = 0';
}
$sql = "SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$unreadCount = get_unread_count($pdo, $uid);
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$totalStmt->execute([$uid]);
$totalCount = (int)$totalStmt->fetchColumn();

require __DIR__ . '/includes/layout_dashboard_start.php';
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex gap-2">
                <a href="notifications.php?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
                <a href="notifications.php?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>">Unread (<?= $unreadCount ?>)</a>
            </div>
            <?php if ($unreadCount > 0): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check-all me-1"></i> Mark All Read</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$notifications): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bell-slash display-4 text-muted"></i>
                    <p class="text-muted mt-3">No notifications to display.</p>
                </div>
            </div>
        </div>
    <?php else: foreach ($notifications as $n): ?>
        <div class="col-12">
            <div class="card <?= $n['is_read'] ? '' : 'border-start border-4 border-primary' ?> notification-card" data-id="<?= (int)$n['id'] ?>">
                <div class="card-body py-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="notification-icon <?= e(severity_text_class($n['severity'])) ?> flex-shrink-0">
                            <?php
                            $defaultIcon = match($n['severity']) {
                                'success' => 'bi-check-circle-fill',
                                'warning' => 'bi-exclamation-triangle-fill',
                                'danger'  => 'bi-x-octagon-fill',
                                default   => 'bi-bell-fill',
                            };
                            ?>
                            <i class="bi <?= e($n['icon'] ?? $defaultIcon) ?> fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-1 <?= $n['is_read'] ? 'text-muted' : 'fw-bold' ?>"><?= e($n['title']) ?></h6>
                                <small class="text-muted flex-shrink-0 ms-2"><?= e(fmt_dt($n['created_at'], 'M d, Y g:i A')) ?></small>
                            </div>
                            <p class="mb-1 <?= $n['is_read'] ? 'text-muted' : '' ?>"><?= e($n['message']) ?></p>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <span class="badge <?= e(severity_badge_class($n['severity'])) ?>"><?= e(ucfirst($n['severity'])) ?></span>
                                <?php if ($n['action_url']): ?>
                                    <a href="<?= e(url($n['action_url'])) ?>" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>
                                <?php endif; ?>
                                <?php if (!$n['is_read']): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link p-0 text-muted">Mark as read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_dashboard_end.php'; ?>

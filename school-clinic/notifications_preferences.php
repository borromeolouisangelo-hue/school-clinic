<?php
/**
 * Notification preferences — let users toggle specific notification types.
 */
require_once __DIR__ . '/config/db.php';
require_login();

$page_title = 'Notification Preferences';
$uid = (int)current_user()['id'];
$nm = new NotificationModel($pdo);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('notifications_preferences.php');
    }

    $types = [
        'medicine_dispensed' => 'Medicine dispensed',
        'medicine_declined' => 'Medicine request declined',
        'consultation_new' => 'New consultation',
        'certificate_issued' => 'Certificate issued',
        'equipment_issued' => 'Equipment borrowed',
        'equipment_returned' => 'Equipment returned',
        'equipment_overdue' => 'Equipment overdue warning',
        'sick_leave' => 'Sick leave (staff only)',
        'sick_leave_status' => 'Sick leave review update',
        'user_created' => 'New user added (staff only)',
        'category_created' => 'New category added (staff only)',
    ];

    foreach ($types as $type => $label) {
        $enabled = isset($_POST["pref_$type"]) && $_POST["pref_$type"] === '1';
        $nm->setPreference($uid, $type, $enabled);
    }

    set_flash('success', 'Notification preferences saved.');
    redirect('notifications_preferences.php');
}

// Load current preferences
$prefs = $nm->getPreferences($uid);

// Group types for display
$groups = [
    'Medical Services' => ['medicine_dispensed', 'medicine_declined', 'consultation_new', 'certificate_issued', 'equipment_issued', 'equipment_returned', 'equipment_overdue'],
    'Sick Leave' => ['sick_leave', 'sick_leave_status'],
    'System (Staff)' => ['user_created', 'category_created'],
];

require __DIR__ . '/includes/layout_dashboard_start.php';
?>

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-bell me-1"></i> Notification Preferences</div>
            <div class="card-body">
                <p class="text-muted">Choose which notifications you want to receive. Disabled notifications will not appear in your notification center.</p>

                <form method="post" data-swal-save="notification preferences">
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">

                    <?php foreach ($groups as $groupLabel => $types): ?>
                        <h6 class="mt-4 mb-3 fw-bold text-primary"><?= e($groupLabel) ?></h6>
                        <div class="list-group mb-3">
                            <?php foreach ($types as $type): ?>
                                <label class="list-group-item d-flex align-items-center justify-content-between">
                                    <span><?= e($type) ?></span>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox"
                                               name="pref_<?= e($type) ?>" value="1"
                                               id="pref_<?= e($type) ?>"
                                               <?= ($prefs[$type] ?? true) ? 'checked' : '' ?>>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Preferences</button>
                        <a href="<?= e(url('notifications.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Notifications</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout_dashboard_end.php'; ?>

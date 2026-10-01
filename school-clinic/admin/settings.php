<?php
/**
 * Admin — System settings.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'Account Settings';
$errors = [];

$allowed = [
    'school_name', 'clinic_name', 'clinic_address', 'clinic_contact',
    'current_school_year', 'current_semester',
    'default_dispense_limit', 'default_equipment_loan_limit',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $_POST)) {
            continue;
        }
        $val = trim((string)$_POST[$key]);
        $up = $pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $up->execute([$key, $val]);
    }
    log_action($pdo, (int)current_user()['id'], 'update_settings', 'system_settings', null, 'Updated system settings');
    set_flash('success', 'Settings saved.');
    redirect('admin/settings.php');
}

$settings = [];
foreach ($pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$val = fn($k, $d = '') => e($settings[$k] ?? $d);

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger py-2"><?= e($err) ?></div>
            <?php endforeach; ?>

            <form method="post" data-swal-save="system settings" data-confirm="Save Account Settings?">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><i class="bi bi-building me-1"></i> School & Clinic Info</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">School Name</label>
                                    <input type="text" name="school_name" class="form-control" value="<?= $val('school_name') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Clinic Name</label>
                                    <input type="text" name="clinic_name" class="form-control" value="<?= $val('clinic_name') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Clinic Address</label>
                                    <input type="text" name="clinic_address" class="form-control" value="<?= $val('clinic_address') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Clinic Contact</label>
                                    <input type="text" name="clinic_contact" class="form-control" value="<?= $val('clinic_contact') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><i class="bi bi-sliders me-1"></i> Rules & Academic Period</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Current School Year</label>
                                    <input type="text" name="current_school_year" class="form-control" value="<?= $val('current_school_year') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Current Semester</label>
                                    <select name="current_semester" class="form-select">
                                        <?php foreach (['1st','2nd','summer'] as $sem): ?>
                                            <option value="<?= $sem ?>" <?= ($settings['current_semester'] ?? '1st') === $sem ? 'selected' : '' ?>><?= ucfirst($sem) ?> Semester</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Used as the default period for the semester medical form.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Default Dispense Limit</label>
                                    <input type="number" min="1" name="default_dispense_limit" class="form-control" value="<?= e($settings['default_dispense_limit'] ?? 10) ?>">
                                    <div class="form-text">Default cap used when a product does not have its own stock-based limit.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Default Equipment Loan Limit</label>
                                    <input type="number" min="1" name="default_equipment_loan_limit" class="form-control" value="<?= e($settings['default_equipment_loan_limit'] ?? 5) ?>">
                                    <div class="form-text">Default cap for loaned equipment quantities when no custom rule applies.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save Account Settings</button>
                </div>
            </form>
        </div>
        <!-- /.content-area -->
    </main>
    <!-- /.main-content -->
</div>
<!-- /.app-wrapper -->

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
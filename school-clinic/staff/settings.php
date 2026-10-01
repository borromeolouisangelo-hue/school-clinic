<?php
/**
 * Staff — Account settings and profile.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

$page_title = 'Account Settings';
$errors = [];
$uid = (int)current_user()['id'];

$stmt = $pdo->prepare(
    "SELECT u.*, p.*, ed.employee_no, ed.department, ed.position
     FROM users u
     LEFT JOIN profiles p ON p.user_id = u.id
     LEFT JOIN employee_details ed ON ed.user_id = u.id
     WHERE u.id = ? LIMIT 1"
);
$stmt->execute([$uid]);
$data = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    }

    if (($_POST['form_action'] ?? '') === 'profile') {
        $first = trim($_POST['first_name'] ?? '');
        $mid = trim($_POST['middle_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : null;
        $birthdate = $_POST['birthdate'] ?: null;
        $address = trim($_POST['address'] ?? '');
        $contact = trim($_POST['contact_no'] ?? '');
        $emergencyName = trim($_POST['emergency_name'] ?? '');
        $emergencyContact = trim($_POST['emergency_contact'] ?? '');

        if ($first === '') $errors[] = 'First name is required.';
        if ($last === '') $errors[] = 'Last name is required.';

        if (!$errors) {
            $pdo->prepare('UPDATE profiles SET first_name=?, middle_name=?, last_name=?, gender=?, birthdate=?, address=?, contact_no=?, emergency_name=?, emergency_contact=? WHERE user_id=?')
                ->execute([$first, $mid, $last, $gender, $birthdate, $address, $contact, $emergencyName, $emergencyContact, $uid]);
            $pdo->prepare('UPDATE employee_details SET department=?, position=? WHERE user_id=?')
                ->execute([trim($_POST['department'] ?? ''), trim($_POST['position'] ?? ''), $uid]);
            $_SESSION['full_name'] = trim($first . ' ' . $last);
            log_action($pdo, $uid, 'update_profile', 'profiles', $uid, 'Updated own account settings');
            set_flash('success', 'Account settings updated.');
            redirect('staff/settings.php');
        }
    } elseif (($_POST['form_action'] ?? '') === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$uid]);
        $passwordHash = (string)$stmt->fetchColumn();

        if (!$errors && !password_verify($currentPassword, $passwordHash)) $errors[] = 'Current password is incorrect.';
        if ($newPassword === '' || strlen($newPassword) < 6) $errors[] = 'New password must be at least 6 characters.';
        if ($newPassword !== $confirmPassword) $errors[] = 'New passwords do not match.';

        if (!$errors) {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $uid]);
            log_action($pdo, $uid, 'change_password', 'users', $uid, 'Changed own password');
            set_flash('success', 'Password updated successfully.');
            redirect('staff/settings.php');
        }
    }
}

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Personal Information</div>
            <div class="card-body">
                <form method="post" data-swal-save="account">
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <input type="hidden" name="form_action" value="profile">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?= e($data['first_name'] ?? '') ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?= e($data['middle_name'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?= e($data['last_name'] ?? '') ?>" required></div>
                        <div class="col-md-3"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">--</option><?php foreach (['male','female','other'] as $g): ?><option value="<?= $g ?>" <?= ($data['gender'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label class="form-label">Birthdate</label><input type="date" name="birthdate" class="form-control" value="<?= e($data['birthdate'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Contact No.</label><input type="text" name="contact_no" class="form-control" value="<?= e($data['contact_no'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= e($data['address'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Emergency Contact Person</label><input type="text" name="emergency_name" class="form-control" value="<?= e($data['emergency_name'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Emergency Contact Number</label><input type="text" name="emergency_contact" class="form-control" value="<?= e($data['emergency_contact'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Employee No.</label><input type="text" class="form-control" value="<?= e($data['employee_no'] ?? '') ?>" readonly></div>
                        <div class="col-md-4"><label class="form-label">Department</label><input type="text" name="department" class="form-control" value="<?= e($data['department'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Position</label><input type="text" name="position" class="form-control" value="<?= e($data['position'] ?? '') ?>"></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save me-1"></i> Save Account Details</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-shield-lock me-1"></i> Account Security</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <input type="hidden" name="form_action" value="password">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>

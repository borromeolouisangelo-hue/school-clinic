<?php
/**
 * Self-registration for students and employees.
 */
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect(dashboard_for(current_role()));
}

$errors = [];
$old = [
    'username' => '', 'email' => '', 'account_type' => 'student',
    'first_name' => '', 'middle_name' => '', 'last_name' => '',
    'gender' => '', 'birthdate' => '', 'lot_block' => '', 'street' => '',
    'barangay' => '', 'postal_code' => '', 'city_municipality' => '', 'province' => '', 'contact_no' => '',
    'emergency_name' => '', 'emergency_contact' => '',
    'student_no' => '', 'course' => '', 'year_level' => '', 'section' => '',
    'employee_no' => '', 'department' => '', 'position' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $v) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $type     = ($old['account_type'] === 'employee') ? 'employee' : 'student';

    // Validation
    if ($old['username'] === '')  $errors[] = 'Username is required.';
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($old['first_name'] === '') $errors[] = 'First name is required.';
    if ($old['last_name'] === '')  $errors[] = 'Last name is required.';
    if (strlen($password) < 6)     $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)    $errors[] = 'Passwords do not match.';

    if ($type === 'student' && $old['student_no'] === '') $errors[] = 'Student number is required.';
    if ($type === 'employee' && $old['employee_no'] === '') $errors[] = 'Employee number is required.';

    // Uniqueness
    if (!$errors) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
        $chk->execute([$old['username'], $old['email']]);
        if ($chk->fetchColumn() > 0) {
            $errors[] = 'Username or email is already taken.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, account_type, status)
                                  VALUES (?, ?, ?, "user", ?, "active")');
            $ins->execute([$old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $type]);
            $uid = (int)$pdo->lastInsertId();

            $p = $pdo->prepare('INSERT INTO profiles (user_id, first_name, middle_name, last_name, gender, birthdate, address, contact_no, emergency_name, emergency_contact)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $p->execute([
                $uid, $old['first_name'], $old['middle_name'], $old['last_name'],
                in_array($old['gender'], ['male','female','other'], true) ? $old['gender'] : null,
                $old['birthdate'] ?: null,
                format_address([
                    $old['lot_block'], $old['street'], $old['barangay'], $old['postal_code'],
                    $old['city_municipality'], $old['province'],
                ]),
                $old['contact_no'],
                $old['emergency_name'], $old['emergency_contact'],
            ]);

            if ($type === 'student') {
                $s = $pdo->prepare('INSERT INTO student_details (user_id, student_no, course, year_level, section) VALUES (?, ?, ?, ?, ?)');
                $s->execute([$uid, $old['student_no'], $old['course'], $old['year_level'], $old['section']]);
            } else {
                $emp = $pdo->prepare('INSERT INTO employee_details (user_id, employee_no, department, position) VALUES (?, ?, ?, ?)');
                $emp->execute([$uid, $old['employee_no'], $old['department'], $old['position']]);
            }

            $pdo->commit();
            log_action($pdo, $uid, 'register', 'users', $uid, "New $type registered");
            set_flash('success', 'Account created successfully. You may now log in.');
            redirect('auth/login.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errors[] = 'Registration failed: ' . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register &middot; <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-shell">
    <div class="auth-card auth-card-wide">
        <div class="auth-brand">
            <img src="<?= e(url('assets/images/ncst.png')) ?>" alt="NCST logo" class="auth-logo-image">
            <h3 class="mb-0">Create an Account</h3>
            <p>Students & Employees of the school</p>
        </div>

        <div class="auth-register-shell">
            <?php if ($errors): ?>
                <div class="auth-alerts">
                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger py-2 mb-2"><?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-register-form auth-form">
                <div class="section-title">Account Type</div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <select name="account_type" id="account_type" class="form-select">
                            <option value="student" <?= $old['account_type'] === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="employee" <?= $old['account_type'] === 'employee' ? 'selected' : '' ?>>Employee</option>
                        </select>
                    </div>
                </div>

                <div class="section-title">Login Credentials</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" value="<?= e($old['username']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Confirm *</label>
                        <input type="password" name="password_confirm" class="form-control" required>
                    </div>
                </div>

                <div class="section-title">Personal Information</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?= e($old['first_name']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" value="<?= e($old['middle_name']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" value="<?= e($old['last_name']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">--</option>
                            <?php foreach (['male','female','other'] as $g): ?>
                                <option value="<?= $g ?>" <?= $old['gender'] === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Birthdate</label>
                        <input type="date" name="birthdate" id="birthdate" class="form-control" value="<?= e($old['birthdate']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Age</label>
                        <input type="text" id="ageField" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contact No.</label>
                        <input type="text" name="contact_no" class="form-control" value="<?= e($old['contact_no']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Lot/Block</label>
                        <input type="text" name="lot_block" class="form-control" value="<?= e($old['lot_block']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Street</label>
                        <input type="text" name="street" class="form-control" value="<?= e($old['street']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Barangay</label>
                        <input type="text" name="barangay" class="form-control" value="<?= e($old['barangay']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Postal/ZIP Code</label>
                        <input type="text" name="postal_code" class="form-control" inputmode="numeric" maxlength="10" value="<?= e($old['postal_code']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">City/Municipality</label>
                        <input type="text" name="city_municipality" class="form-control" value="<?= e($old['city_municipality']) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Province</label>
                        <input type="text" name="province" class="form-control" value="<?= e($old['province']) ?>">
                    </div>
                </div>

                <div class="section-title">Emergency Contact</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="emergency_name" class="form-control" value="<?= e($old['emergency_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="emergency_contact" class="form-control" value="<?= e($old['emergency_contact']) ?>">
                    </div>
                </div>

                <div id="studentFields">
                    <div class="section-title">Student Details</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Student No. *</label>
                            <input type="text" name="student_no" class="form-control" value="<?= e($old['student_no']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Course</label>
                            <input type="text" name="course" class="form-control" value="<?= e($old['course']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Year Level</label>
                            <input type="text" name="year_level" class="form-control" value="<?= e($old['year_level']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Section</label>
                            <input type="text" name="section" class="form-control" value="<?= e($old['section']) ?>">
                        </div>
                    </div>
                </div>

                <div id="employeeFields" style="display:none;">
                    <div class="section-title">Employee Details</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Employee No. *</label>
                            <input type="text" name="employee_no" class="form-control" value="<?= e($old['employee_no']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control" value="<?= e($old['department']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control" value="<?= e($old['position']) ?>">
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i> Register</button>
                    <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-outline-secondary">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var sel = document.getElementById('account_type');
    var sF = document.getElementById('studentFields');
    var eF = document.getElementById('employeeFields');
    function toggle() {
        if (sel.value === 'employee') { sF.style.display = 'none'; eF.style.display = ''; }
        else { sF.style.display = ''; eF.style.display = 'none'; }
    }
    sel.addEventListener('change', toggle);
    toggle();

    var birthdate = document.getElementById('birthdate');
    var ageField = document.getElementById('ageField');
    function updateAge() {
        if (!birthdate.value) { ageField.value = ''; return; }
        var birth = new Date(birthdate.value + 'T00:00:00');
        var today = new Date();
        var age = today.getFullYear() - birth.getFullYear();
        var month = today.getMonth() - birth.getMonth();
        if (month < 0 || (month === 0 && today.getDate() < birth.getDate())) age--;
        ageField.value = age >= 0 ? age : '';
    }
    birthdate.addEventListener('change', updateAge);
    updateAge();
})();
</script>
</body>
</html>
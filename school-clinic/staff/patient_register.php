<?php
/**
 * Staff — Register new patient (student/employee).
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

$page_title = 'Register Patient';
$errors = [];

$accountType = $_GET['type'] ?? 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('staff/patients.php');
    }
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $accountType  = $_POST['account_type'] ?? 'student';
    $password     = $_POST['password'] ?? '';
    $first        = trim($_POST['first_name'] ?? '');
    $last         = trim($_POST['last_name'] ?? '');
    $type         = ($accountType === 'employee') ? 'employee' : 'student';
    $address = format_address([
        $_POST['lot_block'] ?? '', $_POST['street'] ?? '', $_POST['barangay'] ?? '',
        $_POST['postal_code'] ?? '', $_POST['city_municipality'] ?? '', $_POST['province'] ?? '',
    ]);

    if ($username === '') $errors[] = 'Username is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($first === '') $errors[] = 'First name is required.';
    if ($last === '') $errors[] = 'Last name is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($type === 'student' && trim($_POST['student_no'] ?? '') === '') $errors[] = 'Student number is required.';
    if ($type === 'employee' && trim($_POST['employee_no'] ?? '') === '') $errors[] = 'Employee number is required.';

    if (!$errors) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
        $chk->execute([$username, $email]);
        if ($chk->fetchColumn() > 0) {
            $errors[] = 'Username or email already exists.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, account_type, status) VALUES (?,?,?,?,?,?)');
            $ins->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), 'user', $type, 'active']);
            $uid = (int)$pdo->lastInsertId();

            $p = $pdo->prepare('INSERT INTO profiles (user_id, first_name, middle_name, last_name, gender, birthdate, address, contact_no, emergency_name, emergency_contact) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $p->execute([
                $uid, $first, trim($_POST['middle_name'] ?? ''), $last,
                in_array($_POST['gender'] ?? '', ['male','female','other'], true) ? $_POST['gender'] : null,
                $_POST['birthdate'] ?: null, $address, trim($_POST['contact_no'] ?? ''),
                trim($_POST['emergency_name'] ?? ''), trim($_POST['emergency_contact'] ?? '')
            ]);

            if ($type === 'student') {
                $s = $pdo->prepare('INSERT INTO student_details (user_id, student_no, course, year_level, section) VALUES (?,?,?,?,?)');
                $s->execute([$uid, trim($_POST['student_no'] ?? ''), trim($_POST['course'] ?? ''), trim($_POST['year_level'] ?? ''), trim($_POST['section'] ?? '')]);
            } else {
                $emp = $pdo->prepare('INSERT INTO employee_details (user_id, employee_no, department, position) VALUES (?,?,?,?)');
                $emp->execute([$uid, trim($_POST['employee_no'] ?? ''), trim($_POST['department'] ?? ''), trim($_POST['position'] ?? '')]);
            }

            $pdo->commit();
            log_action($pdo, (int)current_user()['id'], 'create_patient', 'users', $uid, "Registered patient $username ($type)");
            set_flash('success', "Patient account $username created successfully.");
            redirect('staff/patients.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errors[] = 'Registration failed: ' . $ex->getMessage();
        }
    }
}

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><?= $accountType === 'employee' ? 'Register Employee' : 'Register Student' ?></div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <div class="section-title">Account Type</div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <select name="account_type" id="account_type" class="form-select" onchange="toggleAccountType()">
                                <option value="student" <?= $accountType === 'student' ? 'selected' : '' ?>>Student</option>
                                <option value="employee" <?= $accountType === 'employee' ? 'selected' : '' ?>>Employee</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-title">Login Credentials</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
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
                        <div class="col-md-3"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select"><option value="">--</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Birthdate</label><input type="date" name="birthdate" id="birthdate" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Age</label><input type="text" id="ageField" class="form-control" readonly></div>
                        <div class="col-md-3"><label class="form-label">Contact No.</label><input type="text" name="contact_no" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Lot/Block</label><input type="text" name="lot_block" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Street</label><input type="text" name="street" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Barangay</label><input type="text" name="barangay" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Postal/ZIP Code</label><input type="text" name="postal_code" class="form-control" inputmode="numeric" maxlength="10"></div>
                        <div class="col-md-4"><label class="form-label">City/Municipality</label><input type="text" name="city_municipality" class="form-control"></div>
                        <div class="col-md-5"><label class="form-label">Province</label><input type="text" name="province" class="form-control"></div>
                    </div>

                    <div class="section-title">Emergency Contact</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Contact Person</label><input type="text" name="emergency_name" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Contact Number</label><input type="text" name="emergency_contact" class="form-control"></div>
                    </div>

                    <div id="studentFields">
                        <div class="section-title">Student Details</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><label class="form-label">Student No. *</label><input type="text" name="student_no" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">Course</label><input type="text" name="course" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">Year Level</label><input type="text" name="year_level" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">Section</label><input type="text" name="section" class="form-control"></div>
                        </div>
                    </div>
                    <div id="employeeFields" style="display:none;">
                        <div class="section-title">Employee Details</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><label class="form-label">Employee No. *</label><input type="text" name="employee_no" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Department</label><input type="text" name="department" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Position</label><input type="text" name="position" class="form-control"></div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i> Register</button>
                        <a href="<?= e(url('staff/patients.php')) ?>" class="btn btn-outline-secondary">Back to Patients</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function toggleAccountType() {
    var sel = document.getElementById('account_type');
    var sF = document.getElementById('studentFields');
    var eF = document.getElementById('employeeFields');
    if (sel.value === 'employee') { sF.style.display = 'none'; eF.style.display = ''; }
    else { sF.style.display = ''; eF.style.display = 'none'; }
}
toggleAccountType();
</script>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>

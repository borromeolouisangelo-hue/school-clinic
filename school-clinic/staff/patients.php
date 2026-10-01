?<?php
/**
 * Staff — Patient records list & profile view.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

$page_title = 'Patients';

$viewId = (int)($_GET['id'] ?? 0);

// ---- Single patient detail ------------------------------------------------
$patient = null;
if ($viewId > 0) {
    $stmt = $pdo->prepare(
        "SELECT u.*, p.*, sd.student_no, sd.course, sd.year_level, sd.section,
                ed.employee_no, ed.department, ed.position
         FROM users u
         LEFT JOIN profiles p ON p.user_id = u.id
         LEFT JOIN student_details sd ON sd.user_id = u.id
         LEFT JOIN employee_details ed ON ed.user_id = u.id
         WHERE u.id = ? LIMIT 1"
    );
    $stmt->execute([$viewId]);
    $patient = $stmt->fetch();

    if ($patient) {
        $mr = $pdo->prepare('SELECT * FROM medical_records WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
        $mr->execute([$viewId]);
        $medRec = $mr->fetch();

        $al = $pdo->prepare('SELECT * FROM user_allergies WHERE user_id = ?');
        $al->execute([$viewId]);
        $allergies = $al->fetchAll();

        $cons = $pdo->prepare('SELECT c.*, CONCAT(pf.first_name," ",pf.last_name) AS staff_name
                               FROM consultations c LEFT JOIN profiles pf ON pf.user_id = c.staff_id
                               WHERE c.patient_id = ? ORDER BY c.consultation_date DESC LIMIT 10');
        $cons->execute([$viewId]);
        $consultations = $cons->fetchAll();

        $disp = $pdo->prepare('SELECT d.*, m.name AS medicine_name FROM dispense_logs d
                               JOIN medicines m ON m.id = d.medicine_id
                               WHERE d.user_id = ? ORDER BY d.dispensed_at DESC LIMIT 10');
        $disp->execute([$viewId]);
        $dispenses = $disp->fetchAll();
    }
}

// ---- Patient list ---------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$typeFilter = in_array($_GET['type'] ?? '', ['all','student','employee'], true)
    ? $_GET['type']
    : 'all';
$sql = "SELECT u.id, u.username, u.account_type, u.status,
               CONCAT(p.first_name,' ',p.last_name) AS full_name, p.contact_no,
               sd.student_no, ed.employee_no
        FROM users u
        LEFT JOIN profiles p ON p.user_id = u.id
        LEFT JOIN student_details sd ON sd.user_id = u.id
        LEFT JOIN employee_details ed ON ed.user_id = u.id
        WHERE u.role = 'user'";
$params = [];
if ($typeFilter !== 'all') {
    $sql .= ' AND u.account_type = ?';
    $params[] = $typeFilter;
}
if ($search !== '') {
    $sql .= " AND (
        CONCAT(p.first_name, ' ', p.last_name) LIKE ?
        OR p.first_name LIKE ?
        OR p.last_name LIKE ?
        OR u.username LIKE ?
        OR sd.student_no LIKE ?
        OR ed.employee_no LIKE ?
        OR CONCAT(p.first_name, ' ', p.last_name, ' - ', COALESCE(sd.student_no, ed.employee_no, ''), ' - @', u.username) LIKE ?
    )";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}
$sql .= ' ORDER BY p.first_name, p.last_name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$patientSuggestions = $pdo->query(
    "SELECT CONCAT(p.first_name, ' ', p.last_name) AS full_name,
            u.username, u.account_type, sd.student_no, ed.employee_no
     FROM users u
     LEFT JOIN profiles p ON p.user_id = u.id
     LEFT JOIN student_details sd ON sd.user_id = u.id
     LEFT JOIN employee_details ed ON ed.user_id = u.id
     WHERE u.role = 'user' AND u.status = 'active'
     ORDER BY p.first_name, p.last_name"
)->fetchAll();

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>

<?php if ($patient): ?>
    <div class="mb-3">
        <a href="<?= e(url('staff/patients.php')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to patients</a>
    </div>

    <ul class="nav nav-pills mb-3 no-print patient-profile-tabs" aria-label="Patient profile sections">
        <li class="nav-item"><button type="button" class="nav-link active" data-profile-tab-button="overview">Overview</button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-profile-tab-button="activity">Visits and dispensing</button></li>
    </ul>

    <div class="row g-3 patient-profile-row">
        <div class="col-lg-4">
            <div class="card mb-3" data-profile-tab-panel="overview">
                <div class="card-header">Patient Information</div>
                <div class="card-body">
                    <h5 class="mb-1"><?= e(trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''))) ?></h5>
                    <div class="text-muted small mb-2"><?= ucfirst($patient['account_type']) ?> &middot; @<?= e($patient['username']) ?></div>
                    <dl class="row mb-0 small">
                        <?php if ($patient['account_type'] === 'student'): ?>
                            <dt class="col-5">Student No.</dt><dd class="col-7"><?= e($patient['student_no'] ?: '—') ?></dd>
                            <dt class="col-5">Course</dt><dd class="col-7"><?= e($patient['course'] ?: '—') ?></dd>
                            <dt class="col-5">Year / Section</dt><dd class="col-7"><?= e(trim(($patient['year_level'] ?? '') . ' ' . ($patient['section'] ?? '')) ?: '—') ?></dd>
                        <?php else: ?>
                            <dt class="col-5">Employee No.</dt><dd class="col-7"><?= e($patient['employee_no'] ?: '—') ?></dd>
                            <dt class="col-5">Department</dt><dd class="col-7"><?= e($patient['department'] ?: '—') ?></dd>
                            <dt class="col-5">Position</dt><dd class="col-7"><?= e($patient['position'] ?: '—') ?></dd>
                        <?php endif; ?>
                        <dt class="col-5">Gender</dt><dd class="col-7"><?= e(ucfirst((string)$patient['gender'])) ?></dd>
                        <dt class="col-5">Birthdate</dt><dd class="col-7"><?= e($patient['birthdate'] ? fmt_dt($patient['birthdate'], 'M d, Y') : '—') ?></dd>
                        <dt class="col-5">Contact</dt><dd class="col-7"><?= e($patient['contact_no'] ?: '—') ?></dd>
                        <dt class="col-5">Address</dt><dd class="col-7"><?= e($patient['address'] ?: '—') ?></dd>
                        <dt class="col-5">Emergency</dt><dd class="col-7"><?= e($patient['emergency_name'] ?: '—') ?> <?= e($patient['emergency_contact'] ? '(' . $patient['emergency_contact'] . ')' : '') ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3" data-profile-tab-panel="overview">
                <div class="card-header">Latest Medical Record</div>
                <div class="card-body">
                    <?php if (!$medRec): ?>
                        <p class="text-muted mb-0">No medical record submitted yet.</p>
                    <?php else: ?>
                        <div class="row g-2 small">
                            <div class="col-md-3"><strong>Blood Type:</strong> <?= e($medRec['blood_type'] ?: '—') ?></div>
                            <div class="col-md-3"><strong>Height:</strong> <?= e($medRec['height_cm'] ?: '—') ?> cm</div>
                            <div class="col-md-3"><strong>Weight:</strong> <?= e($medRec['weight_kg'] ?: '—') ?> kg</div>
                            <div class="col-md-3"><strong>Period:</strong> <?= e($medRec['school_year'] . ' ' . $medRec['semester']) ?></div>
                            <div class="col-12 mt-2"><strong>Allergies (free text):</strong> <?= e($medRec['allergies_text'] ?: '—') ?></div>
                            <div class="col-12"><strong>Existing Conditions:</strong> <?= e($medRec['existing_conditions'] ?: '—') ?></div>
                            <div class="col-12"><strong>Current Medications:</strong> <?= e($medRec['current_medications'] ?: '—') ?></div>
                            <div class="col-12"><strong>Immunizations:</strong> <?= e($medRec['immunizations'] ?: '—') ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3" data-profile-tab-panel="overview">
                <div class="card-header"><i class="bi bi-exclamation-triangle text-accent me-1"></i> Recorded Medicine Allergies</div>
                <div class="card-body">
                    <?php if (!$allergies): ?>
                        <p class="text-muted mb-0">No linked allergies recorded.</p>
                    <?php else: ?>
                        <ul class="mb-0">
                            <?php foreach ($allergies as $a): ?>
                                <li>
                                    <strong><?= e($a['allergen_name']) ?></strong>
                                    <span class="badge bg-secondary ms-1"><?= e($a['severity']) ?></span>
                                    <?php if ($a['notes']): ?><span class="text-muted small">— <?= e($a['notes']) ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3 patient-activity-card" data-profile-tab-panel="activity">
                <div class="card-header">Recent Consultations</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr><th>Date</th><th>Complaint</th><th>Diagnosis</th><th>Attending</th></tr></thead>
                        <tbody>
                        <?php if (!$consultations): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No consultations.</td></tr>
                        <?php else: foreach ($consultations as $c): ?>
                            <tr>
                                <td><?= e(fmt_dt($c['consultation_date'], 'M d, Y')) ?></td>
                                <td><?= e(mb_strimwidth((string)$c['complaint'], 0, 30, '…')) ?></td>
                                <td><?= e(mb_strimwidth((string)$c['diagnosis'], 0, 30, '…')) ?></td>
                                <td><?= e($c['staff_name'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card patient-activity-card" data-profile-tab-panel="activity">
                <div class="card-header">Recent Dispensed Medicines</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr><th>Date</th><th>Medicine</th><th class="text-end">Qty</th></tr></thead>
                        <tbody>
                        <?php if (!$dispenses): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No dispensing records.</td></tr>
                        <?php else: foreach ($dispenses as $d): ?>
                            <tr>
                                <td><?= e(fmt_dt($d['dispensed_at'], 'M d, Y')) ?></td>
                                <td><?= e($d['medicine_name']) ?></td>
                                <td class="text-end"><?= (int)$d['quantity'] ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
<div class="row g-3">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end audit-toolbar">
                    <div class="col">
                        <label class="form-label">Search</label>
                        <input type="search" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" list="patientSearchSuggestions" autocomplete="off" placeholder="Name or ID..." aria-label="Search patients">
                        <datalist id="patientSearchSuggestions">
                            <?php foreach ($patientSuggestions as $suggestion): ?>
                                <?php
                                    $suggestionName = trim((string)$suggestion['full_name']);
                                    $suggestionId = $suggestion['student_no'] ?: $suggestion['employee_no'];
                                    $suggestionLabel = trim($suggestionName . ($suggestionId ? ' - ' . $suggestionId : '') . ' - @' . $suggestion['username']);
                                ?>
                                <option value="<?= e($suggestionLabel) ?>" label="<?= e($suggestionLabel) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Filter</label>
                        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="student" <?= $typeFilter === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="employee" <?= $typeFilter === 'employee' ? 'selected' : '' ?>>Employee</option>
                        </select>
                    </div>
                    <div class="col d-flex flex-wrap gap-2 justify-content-start">
                        <button class="btn btn-primary text-nowrap"><i class="bi bi-search me-1"></i> Search</button>
                        <a href="<?= e(url('staff/patients.php')) ?>" class="btn btn-outline-secondary text-nowrap">Reset</a>
                        <a href="<?= e(url('staff/patient_register.php')) ?>" class="btn btn-accent text-nowrap"><i class="bi bi-plus-lg me-1"></i> New Patient</a>
                    </div>
                </form>
            </div>
        </div>

    <div class="card">
        <div class="table-responsive">
            <table id="patientTable" class="table table-hover mb-0 align-middle">
                <thead><tr><th>#</th><th>Name</th><th>ID No.</th><th>Type</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (!$patients): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No patients found.</td></tr>
                <?php else: foreach ($patients as $pt): ?>
                    <tr>
                        <td><?= (int)$pt['id'] ?></td>
                        <td><?= e($pt['full_name'] ?: $pt['username']) ?></td>
                        <td><?= e($pt['student_no'] ?: $pt['employee_no'] ?: '—') ?></td>
                        <td>
                            <span class="badge <?= $pt['account_type'] === 'student' ? 'bg-info text-dark' : 'bg-primary' ?>">
                                <?= ucfirst($pt['account_type']) ?>
                            </span>
                        </td>
                        <td><?= e($pt['contact_no'] ?: '—') ?></td>
                        <td><?= $pt['status'] === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td class="text-end">
                            <a href="<?= e(url('staff/patients.php?id=' . $pt['id'])) ?>" class="btn btn-sm btn-outline-primary" title="View patient"><i class="bi bi-eye"></i></a>
                            <a href="<?= e(url('staff/consultations.php?patient_id=' . $pt['id'])) ?>" class="btn btn-sm btn-accent" title="New consultation"><i class="bi bi-clipboard2-plus"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>

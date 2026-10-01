<?php
/**
 * Staff — Consultations (record + history).
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

$page_title = 'Consultations';
$errors = [];

// ---- Save consultation -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('staff/consultations.php');
    }
    $pid   = (int)($_POST['patient_id'] ?? 0);
    $compl = trim($_POST['complaint'] ?? '');
    $bp    = trim($_POST['blood_pressure'] ?? '');
    $temp  = $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : null;
    $pulse = $_POST['pulse'] !== '' ? (int)$_POST['pulse'] : null;
    $resp  = $_POST['respiration'] !== '' ? (int)$_POST['respiration'] : null;
    $wt    = $_POST['weight_kg'] !== '' ? (float)$_POST['weight_kg'] : null;
    $ht    = $_POST['height_cm'] !== '' ? (float)$_POST['height_cm'] : null;
    $diag  = trim($_POST['diagnosis'] ?? '');
    $treat = trim($_POST['treatment'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($pid <= 0) $errors[] = 'Please select a patient.';
    if ($compl === '') $errors[] = 'Complaint is required.';

    if (!$errors) {
        $pdo->prepare('INSERT INTO consultations
            (patient_id, staff_id, complaint, blood_pressure, temperature, pulse, respiration, weight_kg, height_cm, diagnosis, treatment, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$pid, (int)current_user()['id'], $compl, $bp, $temp, $pulse, $resp, $wt, $ht, $diag, $treat, $notes]);
        $cid = (int)$pdo->lastInsertId();
        log_action($pdo, (int)current_user()['id'], 'create_consultation', 'consultations', $cid, "Consultation for patient #$pid");
        fire_event('consultation.created', ['consultation_id' => $cid]);
        set_flash('success', 'Consultation saved.');
        redirect('staff/consultations.php');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM consultations WHERE id=? LIMIT 1');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
}

if (($_GET['action'] ?? '') === 'delete' && ($cid = (int)($_GET['id'] ?? 0)) > 0) {
    $pdo->prepare('DELETE FROM consultations WHERE id=?')->execute([$cid]);
    log_action($pdo, (int)current_user()['id'], 'delete_consultation', 'consultations', $cid, "Deleted consultation #$cid");
    set_flash('success', 'Consultation deleted.');
    redirect('staff/consultations.php');
}

// ---- Patients for dropdown -------------------------------------------------
$patients = $pdo->query(
    "SELECT u.id, CONCAT(p.first_name,' ',p.last_name) AS full_name, u.account_type
     FROM users u LEFT JOIN profiles p ON p.user_id = u.id
     WHERE u.role='user' ORDER BY p.first_name, p.last_name"
)->fetchAll();

$preselect = (int)($_GET['patient_id'] ?? 0);
$patientId = $edit['patient_id'] ?? $preselect;

// ---- History ---------------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name
        FROM consultations c
        LEFT JOIN profiles p ON p.user_id = c.patient_id";
$params = [];
if ($search !== '') {
    $sql .= " WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR c.complaint LIKE ? OR c.diagnosis LIKE ?";
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$sql .= ' ORDER BY c.consultation_date DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<div class="row g-3">
    <div class="col-12">
        <div class="modal fade" id="consultationModal" tabindex="-1" aria-labelledby="consultationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-md">
                <div class="modal-content">
        <div class="card">
            <div class="card-header" id="consultationModalLabel"><?= $edit ? 'Edit Consultation' : 'New Consultation' ?></div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">-- select patient --</option>
                            <?php foreach ($patients as $pt): ?>
                                <option value="<?= (int)$pt['id'] ?>" <?= $patientId === (int)$pt['id'] ? 'selected' : '' ?>>
                                    <?= e($pt['full_name'] ?: ('User #' . $pt['id'])) ?> (<?= ucfirst($pt['account_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chief Complaint *</label>
                        <textarea name="complaint" class="form-control" rows="2" required><?= e($edit['complaint'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">Blood Pressure</label><input type="text" name="blood_pressure" class="form-control" value="<?= e($edit['blood_pressure'] ?? '') ?>" placeholder="120/80"></div>
                        <div class="col-6"><label class="form-label">Temperature (°C)</label><input type="number" step="0.1" name="temperature" class="form-control" value="<?= e($edit['temperature'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label">Pulse (bpm)</label><input type="number" name="pulse" class="form-control" value="<?= e($edit['pulse'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label">Respiration</label><input type="number" name="respiration" class="form-control" value="<?= e($edit['respiration'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label">Weight (kg)</label><input type="number" step="0.01" name="weight_kg" class="form-control" value="<?= e($edit['weight_kg'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label">Height (cm)</label><input type="number" step="0.01" name="height_cm" class="form-control" value="<?= e($edit['height_cm'] ?? '') ?>"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <textarea name="diagnosis" class="form-control" rows="2"><?= e($edit['diagnosis'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Treatment</label>
                        <textarea name="treatment" class="form-control" rows="2"><?= e($edit['treatment'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= e($edit['notes'] ?? '') ?></textarea>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <button class="btn btn-primary" <?= $edit ? 'data-swal-save="consultation"' : 'data-swal-add="consultation"' ?>><i class="bi bi-save me-1"></i> <?= $edit ? 'Update' : 'Save Consultation' ?></button>
                    <?php if ($edit): ?><a href="<?= e(url('staff/consultations.php')) ?>" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end audit-toolbar mb-2">
                    <div class="col">
                        <label class="form-label">Search</label>
                        <input id="consultationsSearch" type="search" class="form-control form-control-sm" data-live-filter="#consultationsTable tbody tr" data-live-filter-count="consultationsResultCount" placeholder="Search visible consultations..." aria-label="Search visible consultations">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Filter</label>
                        <form method="get" class="d-inline">
                            <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= (($_GET['filter'] ?? 'all') === 'all') ? 'selected' : '' ?>>All</option>
                                <option value="today" <?= (($_GET['filter'] ?? '') === 'today') ? 'selected' : '' ?>>Today</option>
                                <option value="week" <?= (($_GET['filter'] ?? '') === 'week') ? 'selected' : '' ?>>This Week</option>
                                <option value="month" <?= (($_GET['filter'] ?? '') === 'month') ? 'selected' : '' ?>>This Month</option>
                            </select>
                        </form>
                    </div>
                    <div class="col d-flex flex-wrap gap-2 justify-content-start">
                        
                        <button type="button" class="btn btn-sm btn-primary text-nowrap" data-search-trigger="#consultationsSearch"><i class="bi bi-search me-1"></i> Search</button>
                        <a href="<?= e(url('staff/consultations.php')) ?>" class="btn btn-sm btn-outline-secondary text-nowrap">Reset</a>
                        <button type="button" class="btn btn-sm btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#consultationModal"><i class="bi bi-plus-lg me-1"></i> New Consultation</button>
                    </div>
                </div>
                
            </div>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table id="consultationsTable" class="table table-hover mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Patient</th><th>Complaint</th><th>Diagnosis</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$list): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No consultations found.</td></tr>
                    <?php else: foreach ($list as $c): ?>
                        <tr>
                            <td><?= e(fmt_dt($c['consultation_date'], 'M d, Y g:i A')) ?></td>
                            <td><?= e($c['patient_name'] ?: '—') ?></td>
                            <td><?= e(mb_strimwidth((string)$c['complaint'], 0, 40, '…')) ?></td>
                            <td><?= e(mb_strimwidth((string)$c['diagnosis'], 0, 40, '…')) ?></td>
                            <td class="text-end">
                                <a href="<?= e(url('staff/consultations.php?edit=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <a href="<?= e(url('staff/consultations.php?action=delete&id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this consultation?" data-swal-delete="consultation"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php if ($errors || $edit): ?><script>document.addEventListener('DOMContentLoaded', function () { var modal = document.getElementById('consultationModal'); if (modal && window.bootstrap) new bootstrap.Modal(modal).show(); });</script><?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('consultationModal');
    if (!modal) return;
    modal.addEventListener('hidden.bs.modal', function () {
        var form = modal.querySelector('form');
        if (form) form.reset();
    });
});
</script>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
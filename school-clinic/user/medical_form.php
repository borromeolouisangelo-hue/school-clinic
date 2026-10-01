<?php
/**
 * User — Semester medical form + allergy registry.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

$page_title = 'Medical Form';
$uid = (int)current_user()['id'];
$errors = [];

// Load latest medical record
$stmt = $pdo->prepare('SELECT * FROM medical_records WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
$stmt->execute([$uid]);
$rec = $stmt->fetch() ?: [];

// Load allergies
$stmt = $pdo->prepare('SELECT * FROM user_allergies WHERE user_id = ? ORDER BY id');
$stmt->execute([$uid]);
$allergies = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('user/medical_form.php');
    }
    $formAction = $_POST['form_action'] ?? 'record';

    if ($formAction === 'record') {
        $blood = trim($_POST['blood_type'] ?? '');
        $h     = ($_POST['height_cm'] ?? '') !== '' ? (float)$_POST['height_cm'] : null;
        $w     = ($_POST['weight_kg'] ?? '') !== '' ? (float)$_POST['weight_kg'] : null;
        $allergiesText = trim($_POST['allergies_text'] ?? '');
        $conditions = trim($_POST['existing_conditions'] ?? '');
        $meds  = trim($_POST['current_medications'] ?? '');
        $immun = trim($_POST['immunizations'] ?? '');
        $sym   = trim($_POST['current_symptoms'] ?? '');
        $year  = trim($_POST['school_year'] ?? get_setting($pdo, 'current_school_year', date('Y')));
        $sem   = trim($_POST['semester'] ?? get_setting($pdo, 'current_semester', '1st'));

        if ($year === '') $errors[] = 'School year is required.';
        if ($h !== null && ($h <= 0 || $h > 300)) $errors[] = 'Height must be between 1 and 300 cm.';
        if ($w !== null && ($w <= 0 || $w > 700)) $errors[] = 'Weight must be between 1 and 700 kg.';
        if (!in_array($sem, ['1st', '2nd', 'summer'], true)) $errors[] = 'Select a valid semester.';

        // Upsert by (user_id, school_year, semester)
        if ($errors) {
            $rec = array_merge($rec, [
                'blood_type' => $blood, 'height_cm' => $h, 'weight_kg' => $w,
                'allergies_text' => $allergiesText, 'existing_conditions' => $conditions,
                'current_medications' => $meds, 'immunizations' => $immun, 'current_symptoms' => $sym,
            ]);
        }
        if (!$errors) {
            $pdo->prepare(
                'INSERT INTO medical_records (user_id, school_year, semester, blood_type, height_cm, weight_kg, allergies_text, existing_conditions, current_medications, immunizations, current_symptoms)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE blood_type=VALUES(blood_type), height_cm=VALUES(height_cm), weight_kg=VALUES(weight_kg),
                   allergies_text=VALUES(allergies_text), existing_conditions=VALUES(existing_conditions),
                   current_medications=VALUES(current_medications), immunizations=VALUES(immunizations),
                   current_symptoms=VALUES(current_symptoms), submitted_at=NOW()'
            )->execute([$uid, $year, $sem, $blood, $h, $w, $allergiesText, $conditions, $meds, $immun, $sym]);
            $saved = $pdo->prepare('SELECT id FROM medical_records WHERE user_id=? AND school_year=? AND semester=? LIMIT 1');
            $saved->execute([$uid, $year, $sem]);
            $recordId = (int)$saved->fetchColumn();
            log_action($pdo, $uid, 'submit_medical_form', 'medical_records', $recordId, 'Submitted medical form');
            set_flash('success', 'Medical form saved.');
            redirect('user/medical_form.php');
        }
    }

    if ($formAction === 'add_allergy') {
        $name = trim($_POST['allergen_name'] ?? '');
        $sev  = in_array($_POST['severity'] ?? '', ['mild','moderate','severe'], true) ? $_POST['severity'] : 'mild';
        $notes = trim($_POST['notes'] ?? '');
        if ($name === '') {
            $errors[] = 'Allergen name is required.';
        } else {
            $pdo->prepare('INSERT INTO user_allergies (user_id, allergen_name, severity, notes) VALUES (?,?,?,?)')
                ->execute([$uid, $name, $sev, $notes]);
            log_action($pdo, $uid, 'add_allergy', 'user_allergies', (int)$pdo->lastInsertId(), "Added allergy: $name");
            set_flash('success', 'Allergy added.');
            redirect('user/medical_form.php');
        }
    }
}

if (($_GET['action'] ?? '') === 'delete_allergy' && ($aid = (int)($_GET['id'] ?? 0)) > 0) {
    $pdo->prepare('DELETE FROM user_allergies WHERE id=? AND user_id=?')->execute([$aid, $uid]);
    log_action($pdo, $uid, 'delete_allergy', 'user_allergies', $aid, "Deleted allergy #$aid");
    set_flash('success', 'Allergy removed.');
    redirect('user/medical_form.php');
}

$year = $rec['school_year'] ?? get_setting($pdo, 'current_school_year', date('Y'));
$sem  = $rec['semester'] ?? get_setting($pdo, 'current_semester', '1st');

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="alert alert-info py-2">
    <i class="bi bi-info-circle me-1"></i> Complete this form each semester. Allergies recorded here are used to warn clinic staff before dispensing medicine.
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Health Information</div>
            <div class="card-body">
                <form method="post" data-autosave="medical-form">
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <input type="hidden" name="form_action" value="record">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">School Year</label>
                            <input type="text" name="school_year" class="form-control" value="<?= e($year) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <?php foreach (['1st','2nd','summer'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $sem === $s ? 'selected' : '' ?>><?= ucfirst($s) ?> Semester</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Blood Type</label>
                            <input type="text" name="blood_type" class="form-control" value="<?= e($rec['blood_type'] ?? '') ?>">
                        </div>
                        <div class="col-md-3"><label class="form-label">Height (cm)</label><input type="number" step="0.01" name="height_cm" class="form-control" value="<?= e($rec['height_cm'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Weight (kg)</label><input type="number" step="0.01" name="weight_kg" class="form-control" value="<?= e($rec['weight_kg'] ?? '') ?>"></div>
                        <div class="col-12">
                            <label class="form-label">Allergies (free text)</label>
                            <textarea name="allergies_text" class="form-control" rows="2" placeholder="e.g. penicillin, peanuts, dust"><?= e($rec['allergies_text'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Existing Conditions</label>
                            <textarea name="existing_conditions" class="form-control" rows="2"><?= e($rec['existing_conditions'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Current Medications</label>
                            <textarea name="current_medications" class="form-control" rows="2"><?= e($rec['current_medications'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Immunizations</label>
                            <textarea name="immunizations" class="form-control" rows="2"><?= e($rec['immunizations'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Current Symptoms (if any)</label>
                            <textarea name="current_symptoms" class="form-control" rows="2"><?= e($rec['current_symptoms'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Medical Form</button>
                        <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#allergyModal"><i class="bi bi-plus-lg me-1"></i> Add Allergy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-exclamation-triangle text-accent me-1"></i> Allergy Registry</div>
            <div class="card-body">
                <?php if (!$allergies): ?>
                    <p class="text-muted small">No allergies recorded.</p>
                <?php else: ?>
                    <ul class="list-unstyled">
                        <?php foreach ($allergies as $a): ?>
                            <li class="d-flex justify-content-between align-items-start mb-2">
                                <span>
                                    <strong><?= e($a['allergen_name']) ?></strong>
                                    <span class="badge bg-secondary"><?= e($a['severity']) ?></span>
                                    <?php if ($a['notes']): ?><div class="small text-muted"><?= e($a['notes']) ?></div><?php endif; ?>
                                </span>
                                <a href="<?= e(url('user/medical_form.php?action=delete_allergy&id=' . $a['id'])) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Remove this allergy?" data-swal-delete="allergy"><i class="bi bi-trash"></i></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Allergy Modal -->
<div class="modal fade" id="allergyModal" tabindex="-1" aria-labelledby="allergyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="card">
                <div class="card-header" id="allergyModalLabel"><i class="bi bi-plus-lg me-1"></i> Add Allergy</div>
                <div class="card-body">
                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger py-2"><?= e($err) ?></div>
                    <?php endforeach; ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="add_allergy">
                        <div class="mb-3">
                            <label class="form-label">Allergen *</label>
                            <input type="text" name="allergen_name" class="form-control" required placeholder="e.g. Penicillin">
                            <input type="text" name="allergen_name" class="form-control" required placeholder="e.g. Penicillin">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Severity</label>
                            <select name="severity" class="form-select">
                                <option value="mild">Mild</option>
                                <option value="moderate">Moderate</option>
                                <option value="severe">Severe</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Optional notes...">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-accent"><i class="bi bi-plus-lg me-1"></i> Add Allergy</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($errors): ?><script>document.addEventListener('DOMContentLoaded', function() { var modal = document.getElementById('allergyModal'); if (modal && window.bootstrap) new bootstrap.Modal(modal).show(); });</script><?php endif; ?>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
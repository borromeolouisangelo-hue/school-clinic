<?php
/**
 * Staff — Walk-in dispensing (with allergy warning + override).
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

$page_title = 'Requests';
$errors = [];
$conflicts = [];
$showAllergyDecision = false;
$selPatient = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
$selMedicine = (int)($_POST['medicine_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('staff/dispense.php');
    }
    $qty      = (int)($_POST['quantity'] ?? 0);
    $override = trim($_POST['override_reason'] ?? '');
    $notes    = trim($_POST['notes'] ?? '');
    $reason   = $notes !== '' ? $notes : 'Walk-in dispense';
    $outcome  = 'dispensed';
    $allergyDecision = $_POST['allergy_decision'] ?? '';
    $reasonableLimit = get_setting_int($pdo, 'default_dispense_limit', 10);

    if ($selMedicine > 0) {
        $productLimitStmt = $pdo->prepare('SELECT low_stock_threshold FROM medicines WHERE id = ? AND is_active = 1 LIMIT 1');
        $productLimitStmt->execute([$selMedicine]);
        $productLimitRow = $productLimitStmt->fetch();
        if ($productLimitRow) {
            $reasonableLimit = max(1, (int)($productLimitRow['low_stock_threshold'] ?? $reasonableLimit));
        }
    }

    if ($selPatient <= 0) $errors[] = 'Please select a patient.';
    if ($selMedicine <= 0) $errors[] = 'Please select a medicine.';
    if ($qty !== 1) $errors[] = 'Medicine requests are limited to 1 unit per day.';
    if ($qty > $reasonableLimit) $errors[] = 'Quantity exceeds the reasonable limit for this product (max ' . (int)$reasonableLimit . ').';

    $allergyDecision = $_POST['allergy_decision'] ?? '';
    if ($allergyDecision === 'accept' && $override === '') {
        $errors[] = 'An override reason is required when accepting an allergy conflict.';
    }

    if (!$errors) {
        $conflicts = check_allergy_conflict($pdo, $selPatient, $selMedicine);
        if ($outcome === 'dispensed' && $conflicts && $allergyDecision === '') {
            $showAllergyDecision = true;
        } elseif ($allergyDecision === 'reject') {
            // Stored value must be the enum member 'rejected'; the UI labels it "Declined".
            $outcome = 'rejected';
        }
    }

    if (!$errors && recent_medicine_request_conflict($pdo, $selPatient, $selMedicine, $reason)) {
        $errors[] = 'This patient already has the same medicine or purpose recorded within the last 24 hours.';
    }

    if (!$errors && !$showAllergyDecision) {
        $stock = medicine_stock($pdo, $selMedicine);
        if ($outcome === 'dispensed' && $stock < $qty) {
            $errors[] = "Insufficient stock ($stock available).";
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'INSERT INTO medicine_requests (user_id, medicine_id, quantity, reason, status, allergy_flag, override_reason, processed_by, processed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $selPatient, $selMedicine, $qty, $reason, $outcome, $conflicts ? 1 : 0,
                    $override ?: null, (int)current_user()['id'],
                ]);
                $requestId = (int)$pdo->lastInsertId();

                $dispenseId = null;
                if ($outcome === 'dispensed') {
                    $used = deduct_stock_trace($pdo, $selMedicine, $qty, (int)current_user()['id'], $requestId, 'Walk-in dispense: ' . $reason);
                    if ($used === null) {
                        throw new RuntimeException('Stock deduction failed.');
                    }
                    $pdo->prepare('INSERT INTO dispense_logs (request_id, user_id, medicine_id, batch_id, quantity, dispensed_by, remarks) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$requestId, $selPatient, $selMedicine, $used[0]['batch_id'] ?? null, $qty, (int)current_user()['id'], ($conflicts ? '[ALLERGY OVERRIDE: ' . $override . '] ' : '') . $notes]);
                    $dispenseId = (int)$pdo->lastInsertId();
                }
                $pdo->commit();
                $outcomeLabel = $outcome === 'dispensed' ? 'Dispensed' : 'Declined';
                log_action($pdo, (int)current_user()['id'], $outcome === 'dispensed' ? 'dispense_walkin' : 'reject_request', 'medicine_requests', $requestId,
                    $outcomeLabel . " walk-in request #$requestId for patient #$selPatient");
                fire_event($outcome === 'dispensed' ? 'medicine.dispensed' : 'medicine.declined', ['request_id' => $requestId]);
                set_flash('success', $outcomeLabel . ' and added to the dispensing record.');
                redirect('staff/dispense.php');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $ex->getMessage();
            }
        }
    }
    // keep conflicts for display
    if ($selPatient && $selMedicine) {
        $conflicts = check_allergy_conflict($pdo, $selPatient, $selMedicine);
    }
}

$patients = $pdo->query(
    "SELECT u.id, CONCAT(p.first_name,' ',p.last_name) AS full_name,
            u.username, sd.student_no, ed.employee_no
        FROM users u
        LEFT JOIN profiles p ON p.user_id=u.id
        LEFT JOIN student_details sd ON sd.user_id=u.id
        LEFT JOIN employee_details ed ON ed.user_id=u.id
        WHERE u.role='user' ORDER BY p.first_name"
)->fetchAll();

$medicines = $pdo->query('SELECT id, name, unit, generic_name FROM medicines WHERE is_active=1 ORDER BY name')->fetchAll();

$historyStatus = $_GET['history_status'] ?? 'all';
if (!in_array($historyStatus, ['all', 'dispensed', 'rejected'], true)) {
    $historyStatus = 'all';
}

$historyWhere = '1=1';
$historyParams = [];
if ($historyStatus !== 'all') {
    $historyWhere .= ' AND r.status = ?';
    $historyParams[] = $historyStatus;
}

$historySql = "SELECT r.*, m.name AS medicine_name,
                    CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    u.username AS processed_username
               FROM medicine_requests r
               JOIN medicines m ON m.id = r.medicine_id
               LEFT JOIN profiles p ON p.user_id = r.user_id
               LEFT JOIN users u ON u.id = r.processed_by
               WHERE $historyWhere
               ORDER BY r.requested_at DESC LIMIT 200";
$historyStmt = $pdo->prepare($historySql);
$historyStmt->execute($historyParams);
$historyRows = $historyStmt->fetchAll();

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<div class="row g-3">
    <div class="col-12">
        <div class="modal fade" id="dispenseModal" tabindex="-1" aria-labelledby="dispenseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-md"><div class="modal-content">
        <div class="card">
            <div class="card-header" id="dispenseModalLabel">Medicine Dispensing</div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>

                <?php if ($conflicts): ?>
                    <div class="alert-allergy p-3 mb-3">
                        <strong><i class="bi bi-exclamation-triangle"></i> Allergy Warning</strong><br>
                        Selected patient is allergic to:
                        <ul class="mb-0 mt-1">
                            <?php foreach ($conflicts as $cf): ?><li><?= e($cf['allergen_name']) ?> (<?= e($cf['severity']) ?>)</li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label">Patient *</label>
                        <div class="d-flex align-items-center gap-2 mb-2 compact-search-row">
                            <input type="search" class="form-control form-control-sm" data-live-filter="#dispensePatientList option" data-live-filter-count="dispensePatientResultCount" placeholder="Search patient..." aria-label="Search patient">
                            <small id="dispensePatientResultCount" class="text-muted text-nowrap">All records</small>
                        </div>
                        <select id="dispensePatientList" name="user_id" class="form-select form-select-sm" required>
                            <option value="">-- select patient --</option>
                            <?php foreach ($patients as $pt): ?>
                                <?php $patientId = $pt['student_no'] ?: $pt['employee_no']; ?>
                                <option value="<?= (int)$pt['id'] ?>" <?= $selPatient === (int)$pt['id'] ? 'selected' : '' ?>><?= e($pt['full_name'] ?: ('User #' . $pt['id'])) ?><?= $patientId ? ' - ' . e($patientId) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Medicine / First Aid Item *</label>
                        <select name="medicine_id" id="dispenseMedicineSelect" class="form-select" required>
                            <option value="">-- select item --</option>
                            <?php foreach ($medicines as $m): $stock = medicine_stock($pdo, (int)$m['id']); $limit = max(1, (int)($m['low_stock_threshold'] ?? 10)); ?>
                                <option value="<?= (int)$m['id'] ?>" data-stock="<?= (int)$stock ?>" data-limit="<?= (int)$limit ?>" <?= $selMedicine === (int)$m['id'] ? 'selected' : '' ?>>
                                    <?= e($m['name']) ?><?= $m['generic_name'] ? ' (' . e($m['generic_name']) . ')' : '' ?> — <?= e($m['unit']) ?> · stock <?= (int)$stock ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="quantity" id="dispenseQuantity" class="form-control" min="1" value="1" max="1" required>
                        <div id="dispenseQuantityHelp" class="form-text">Use the item unit shown above (box, roll, tube, bottle, piece, etc.).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <?php if ($showAllergyDecision): ?>
                        <div class="alert alert-warning mb-3">
                            <strong>Allergy warning requires a decision.</strong>
                            Choose whether to accept or reject this request. No stock or history record is changed until you choose.
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="dispenseOverrideReason">Override reason *</label>
                            <textarea name="override_reason" id="dispenseOverrideReason" class="form-control" rows="2" required
                                placeholder="e.g. Assessed by clinic physician; risk outweighed benefit"><?= e($override ?? '') ?></textarea>
                            <div class="form-text">Required when accepting an allergy conflict. It is stored with the dispensing record.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="allergy_decision" value="accept" class="btn btn-danger flex-fill"><i class="bi bi-check-circle me-1"></i> Accept and Dispense</button>
                            <button type="submit" name="allergy_decision" value="reject" class="btn btn-outline-secondary flex-fill" formnovalidate><i class="bi bi-x-circle me-1"></i> Reject Request</button>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    <?php else: ?>
                        <div class="d-flex gap-2">
                            <button class="btn btn-accent flex-fill" data-swal-add="medicine request"><i class="bi bi-prescription2 me-1"></i> Dispense Item</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
            </div></div>
        </div>
    </div>

    <div class="col">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end audit-toolbar mb-2">
                    <div class="col">
                        <label class="form-label">Search</label>
                        <input id="dispenseHistorySearch" type="search" class="form-control form-control-sm" data-live-filter="#dispenseHistoryTable tbody tr" data-live-filter-count="dispenseHistoryResultCount" placeholder="Search visible history..." aria-label="Search medicine history">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Filter</label>
                        <form method="get" class="d-inline">
                            <select name="history_status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= $historyStatus === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="dispensed" <?= $historyStatus === 'dispensed' ? 'selected' : '' ?>>Dispensed</option>
                                <option value="rejected" <?= $historyStatus === 'rejected' ? 'selected' : '' ?>>Declined</option>
                            </select>
                        </form>
                    </div>
                    <div class="col d-flex flex-wrap gap-2 justify-content-start">
                        <button type="button" class="btn btn-primary text-nowrap" data-search-trigger="#dispenseHistorySearch"><i class="bi bi-search me-1"></i> Search</button>
                        <button type="button" class="btn btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#dispenseModal"><i class="bi bi-prescription2 me-1"></i> Dispense Item</button>
                    </div>
                </div>
            </div>
        </div>
                
        <div class="card">
                <div class="table-responsive">
                    <table id="dispenseHistoryTable" class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Medicine</th>
                                <th class="text-end">Qty</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$historyRows): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No medicine history in this view.</td></tr>
                        <?php else: foreach ($historyRows as $row): ?>
                            <tr>
                                <td><?= e(fmt_dt($row['requested_at'], 'M d, Y g:i A')) ?></td>
                                <td><?= e($row['patient_name'] ?: '—') ?></td>
                                <td><?= e($row['medicine_name']) ?></td>
                                <td class="text-end"><?= (int)$row['quantity'] ?></td>
                                <td><?= e($row['reason'] ?: '—') ?></td>
                                <td>
                                    <span class="badge <?= $row['status'] === 'dispensed' ? 'bg-success' : 'bg-danger' ?>"><?= $row['status'] === 'rejected' ? 'Declined' : ucfirst($row['status']) ?></span>
                                </td>
                                <td><?= e($row['processed_username'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($errors || $conflicts || $showAllergyDecision): ?><script>document.addEventListener('DOMContentLoaded', function () { var modal = document.getElementById('dispenseModal'); if (modal && window.bootstrap) new bootstrap.Modal(modal).show(); });</script><?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var medicineSelect = document.getElementById('dispenseMedicineSelect');
    var quantityInput = document.getElementById('dispenseQuantity');
    var helpText = document.getElementById('dispenseQuantityHelp');
    var dispenseModal = document.getElementById('dispenseModal');

    function applyMedicineLimit() {
        if (!medicineSelect || !quantityInput) return;
        var selected = medicineSelect.options[medicineSelect.selectedIndex];
        var stock = selected && selected.dataset.stock ? parseInt(selected.dataset.stock, 10) : 0;
        stock = Number.isFinite(stock) ? stock : 0;
        var maxQty = 1;
        quantityInput.min = '1';
        quantityInput.max = String(maxQty);
        quantityInput.setAttribute('min', '1');
        quantityInput.setAttribute('max', String(maxQty));
        quantityInput.value = '1';
        if (parseInt(quantityInput.value, 10) > maxQty) {
            quantityInput.value = String(maxQty);
        }
        if (stock <= 0) {
            helpText.textContent = 'This item is out of stock. Add stock before dispensing.';
            quantityInput.value = '0';
            quantityInput.disabled = true;
        } else {
            helpText.textContent = 'Medicine requests are limited to 1 unit per day. Use the item unit shown above.';
            quantityInput.disabled = false;
            if (parseInt(quantityInput.value, 10) < 1) {
                quantityInput.value = '1';
            }
        }
    }

    if (medicineSelect) {
        medicineSelect.addEventListener('change', applyMedicineLimit);
    }
    if (dispenseModal) {
        dispenseModal.addEventListener('hidden.bs.modal', function () {
            var form = dispenseModal.querySelector('form');
            if (form) form.reset();
            if (medicineSelect) medicineSelect.selectedIndex = 0;
            if (quantityInput) quantityInput.value = '1';
            applyMedicineLimit();
        });
    }
    applyMedicineLimit();
});
</script>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
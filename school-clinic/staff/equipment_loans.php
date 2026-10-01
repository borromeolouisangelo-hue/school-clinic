<?php
/**
 * Staff — Equipment loans (issue + return).
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

create_overdue_loan_notifications($pdo);

$page_title = 'Borrow';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Invalid request. Please try again.');
    redirect('staff/equipment_loans.php');
}

// ---- Issue a loan ----------------------------------------------------------
if (($_POST['do'] ?? '') === 'issue') {
    $uid  = (int)($_POST['user_id'] ?? 0);
    $eid  = (int)($_POST['equipment_id'] ?? 0);
    $qty  = (int)($_POST['quantity'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');

    if ($uid <= 0) $errors[] = 'Select a borrower.';
    if ($eid <= 0) $errors[] = 'Select equipment.';
    if ($qty !== 1) $errors[] = 'Equipment requests are limited to 1 item per request.';
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT * FROM equipment WHERE id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$eid]);
        $eq = $stmt->fetch();
        if (!$eq) {
            $errors[] = 'Selected equipment is unavailable.';
        } elseif ((int)$eq['available_qty'] < $qty) {
            $errors[] = 'Not enough available units.';
        } elseif ($qty > (int)$eq['total_qty']) {
            $errors[] = 'Requested quantity cannot exceed total stock.';
        }
        if (!$errors) {
            $pdo->beginTransaction();
            $updated = $pdo->prepare('UPDATE equipment SET available_qty = available_qty - ? WHERE id=? AND available_qty >= ? AND is_active=1');
            $updated->execute([$qty, $eid, $qty]);
            if ($updated->rowCount() !== 1) {
                $pdo->rollBack();
                $errors[] = 'Not enough available units.';
            } else {
            $pdo->prepare('INSERT INTO equipment_loans (user_id, equipment_id, quantity, borrow_date, borrowed_at, status, notes, issued_by)
                           VALUES (?,?,?,CURDATE(),NOW(), "borrowed", ?, ?)')
                ->execute([$uid, $eid, $qty, $notes, (int)current_user()['id']]);
            $lid = (int)$pdo->lastInsertId();
            $pdo->commit();
            log_action($pdo, (int)current_user()['id'], 'issue_loan', 'equipment_loans', $lid, "Issued equipment #$eid to user #$uid");
            fire_event('equipment.issued', ['loan_id' => $lid]);
            set_flash('success', 'Equipment issued.');
            redirect('staff/equipment_loans.php');
            }
        }
    }
}

// ---- Return a loan ---------------------------------------------------------
if (($_POST['do'] ?? '') === 'return') {
    $lid = (int)($_POST['loan_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM equipment_loans WHERE id=? LIMIT 1');
    $stmt->execute([$lid]);
    $loan = $stmt->fetch();
    if ($loan && $loan['status'] === 'borrowed') {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE equipment SET available_qty = available_qty + ? WHERE id=?')->execute([(int)$loan['quantity'], (int)$loan['equipment_id']]);
        $pdo->prepare("UPDATE equipment_loans SET status='returned', return_date=CURDATE(), received_by=? WHERE id=?")
            ->execute([(int)current_user()['id'], $lid]);
        $pdo->commit();
        log_action($pdo, (int)current_user()['id'], 'return_loan', 'equipment_loans', $lid, "Returned loan #$lid");
        fire_event('equipment.returned', ['loan_id' => $lid]);
        set_flash('success', 'Equipment returned.');
    } else {
        set_flash('danger', 'Loan not found or already returned.');
    }
    redirect('staff/equipment_loans.php');
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
$equipment = $pdo->query('SELECT * FROM equipment WHERE is_active=1 ORDER BY name')->fetchAll();

$statusFilter = $_GET['status'] ?? 'borrowed';
$where = ''; $params = [];
if ($statusFilter === 'overdue') {
    $where = "WHERE l.status = 'borrowed' AND l.borrowed_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
} elseif (in_array($statusFilter, ['borrowed','returned'], true)) {
    $where = 'WHERE l.status = ?';
    $params[] = $statusFilter;
}
$sql = "SELECT l.*, e.name AS equipment_name, CONCAT(p.first_name,' ',p.last_name) AS borrower_name
        FROM equipment_loans l
        JOIN equipment e ON e.id = l.equipment_id
        LEFT JOIN profiles p ON p.user_id = l.user_id
        $where ORDER BY l.borrow_date DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<div class="row g-3">
    <div class="col-12">
        <div class="modal fade" id="issueEquipmentModal" tabindex="-1" aria-labelledby="issueEquipmentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-md"><div class="modal-content">
        <div class="card">
            <div class="card-header" id="issueEquipmentModalLabel">Issue Equipment</div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <input type="hidden" name="do" value="issue">
                    <div class="mb-3">
                        <label class="form-label">Borrower *</label>
                        <input type="search" class="form-control" data-patient-combobox="#loanPatientId" list="loanPatientSuggestions" autocomplete="off" placeholder="Search borrower name or ID..." aria-label="Search borrower" required>
                        <input type="hidden" id="loanPatientId" name="user_id" value="" required>
                        <datalist id="loanPatientSuggestions">
                            <?php foreach ($patients as $pt): ?>
                                <?php $patientId = $pt['student_no'] ?: $pt['employee_no']; ?>
                                <option value="<?= e(($pt['full_name'] ?: ('User #' . $pt['id'])) . ($patientId ? ' - ' . $patientId : '')) ?>" data-user-id="<?= (int)$pt['id'] ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Equipment *</label>
                        <select name="equipment_id" id="loanEquipmentSelect" class="form-select" required>
                            <option value="">-- select --</option>
                            <?php $defaultLoanLimit = get_setting_int($pdo, 'default_equipment_loan_limit', 5); ?>
                            <?php foreach ($equipment as $eq): $limit = min((int)$eq['total_qty'], $defaultLoanLimit); ?>
                                <option value="<?= (int)$eq['id'] ?>" data-available="<?= (int)$eq['available_qty'] ?>" data-limit="<?= (int)$limit ?>"><?= e($eq['name']) ?> (<?= (int)$eq['available_qty'] ?> available)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" id="loanQuantity" class="form-control" min="1" value="1" max="1">
                        <div id="loanQuantityHelp" class="form-text">Maximum available units for the selected equipment will be applied automatically.</div>
                    </div>
                    <div class="alert alert-info small py-2">Equipment must be returned within 24 hours. A warning will be sent to the borrower after the timeout.</div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button class="btn btn-primary w-100" data-swal-add="equipment loan"><i class="bi bi-box-arrow-up me-1"></i> Issue</button>
                </form>
            </div>
        </div>
            </div></div>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-3 ">
            <div class="card-body">
                <div class="row g-2 align-items-end audit-toolbar mb-2">
                    <div class="col">
                        <label class="form-label">Search</label>
                        <input id="loanSearch" type="search" class="form-control form-control-sm" data-live-filter="#loanTable tbody tr" data-live-filter-count="loanResultCount" placeholder="Search visible loans..." aria-label="Search loans">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Filter</label>
                        <form method="get" class="d-inline">
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="borrowed" <?= (($_GET['status'] ?? 'borrowed') === 'borrowed') ? 'selected' : '' ?>>Borrowed</option>
                                <option value="returned" <?= (($_GET['status'] ?? '') === 'returned') ? 'selected' : '' ?>>Returned</option>
                                <option value="overdue" <?= (($_GET['status'] ?? '') === 'overdue') ? 'selected' : '' ?>>Overdue</option>
                            </select>
                        </form>
                    </div>
                    <div class="col d-flex flex-wrap gap-2 justify-content-start">
                        
                        <button type="button" class="btn  btn-primary text-nowrap" data-search-trigger="#loanSearch"><i class="bi bi-search me-1"></i> Search</button>

                        <button type="button" class="btn btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#issueEquipmentModal"><i class="bi bi-box-arrow-up me-1"></i> Issue Equipment</button>
                    </div>
                </div>
                
            </div>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table id="loanTable" class="table table-hover mb-0 align-middle">
                    <thead><tr><th>Borrower</th><th>Equipment</th><th class="text-end">Qty</th><th>Borrowed</th><th>24-Hour Timeout</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$loans): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No loans in this view.</td></tr>
                    <?php else: foreach ($loans as $l): ?>
                        <?php $overdue = $l['status'] === 'borrowed' && strtotime($l['borrowed_at']) <= time() - 86400; ?>
                        <tr>
                            <td><?= e($l['borrower_name'] ?: '—') ?></td>
                            <td><?= e($l['equipment_name']) ?></td>
                            <td class="text-end"><?= (int)$l['quantity'] ?></td>
                            <td><?= e(fmt_dt($l['borrowed_at'], 'M d, Y g:i A')) ?></td>
                            <td><?= e(fmt_dt(date('Y-m-d H:i:s', strtotime($l['borrowed_at']) + 86400), 'M d, Y g:i A')) ?></td>
                            <td>
                                <?php if ($l['status'] === 'returned'): ?>
                                    <span class="badge bg-secondary">Returned</span>
                                <?php elseif ($overdue): ?>
                                    <span class="badge bg-secondary">Overdue</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Borrowed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($l['status'] === 'borrowed'): ?>
                                    <form method="post" class="d-inline" data-confirm="Mark as returned?">
                                        <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                        <input type="hidden" name="do" value="return">
                                        <input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>">
                                        <button class="btn btn-sm btn-accent"><i class="bi bi-box-arrow-in-down"></i> Return</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small"><?= e(fmt_dt($l['return_date'], 'M d, Y')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php if ($errors): ?><script>document.addEventListener('DOMContentLoaded', function () { var modal = document.getElementById('issueEquipmentModal'); if (modal && window.bootstrap) new bootstrap.Modal(modal).show(); });</script><?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var equipmentSelect = document.getElementById('loanEquipmentSelect');
    var quantityInput = document.getElementById('loanQuantity');
    var helpText = document.getElementById('loanQuantityHelp');
    var equipmentModal = document.getElementById('issueEquipmentModal');

    function applyEquipmentLimit() {
        if (!equipmentSelect || !quantityInput) return;
        var selected = equipmentSelect.options[equipmentSelect.selectedIndex];
        var available = selected && selected.dataset.available ? parseInt(selected.dataset.available, 10) : 0;
        available = Number.isFinite(available) ? available : 0;
        var maxQty = 1;
        quantityInput.min = '1';
        quantityInput.max = String(maxQty);
        quantityInput.setAttribute('min', '1');
        quantityInput.setAttribute('max', String(maxQty));
        quantityInput.value = '1';
        if (parseInt(quantityInput.value, 10) > maxQty) {
            quantityInput.value = String(maxQty);
        }
        if (available <= 0) {
            helpText.textContent = 'This equipment is currently unavailable for loan.';
            quantityInput.value = '0';
            quantityInput.disabled = true;
        } else {
            helpText.textContent = 'Equipment requests are limited to 1 item per request.';
            quantityInput.disabled = false;
            if (parseInt(quantityInput.value, 10) < 1) {
                quantityInput.value = '1';
            }
        }
    }

    if (equipmentSelect) {
        equipmentSelect.addEventListener('change', applyEquipmentLimit);
    }
    if (equipmentModal) {
        equipmentModal.addEventListener('hidden.bs.modal', function () {
            var form = equipmentModal.querySelector('form');
            if (form) form.reset();
            if (equipmentSelect) equipmentSelect.selectedIndex = 0;
            if (quantityInput) quantityInput.value = '1';
            applyEquipmentLimit();
        });
    }
    applyEquipmentLimit();
});
</script>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
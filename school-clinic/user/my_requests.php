<?php
/**
 * User — Consolidated My Activity overview.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

$page_title = 'My Activity';
$uid = (int)current_user()['id'];

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT r.*, m.name AS medicine_name, m.generic_name
        FROM medicine_requests r JOIN medicines m ON m.id = r.medicine_id
        WHERE r.user_id = ?";
$params = [$uid];
if (in_array($statusFilter, ['dispensed','rejected'], true)) {
    $sql .= ' AND r.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY r.requested_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$badge = ['dispensed'=>'bg-success','rejected'=>'bg-danger'];

$stmt = $pdo->prepare(
    "SELECT l.*, e.name AS equipment_name
     FROM equipment_loans l JOIN equipment e ON e.id = l.equipment_id
     WHERE l.user_id = ? ORDER BY COALESCE(l.return_date, l.borrow_date) DESC"
);
$stmt->execute([$uid]);
$loans = $stmt->fetchAll();
$loanBadge = ['borrowed'=>'bg-warning text-dark','returned'=>'bg-success'];

$stmt = $pdo->prepare(
    "SELECT c.*, CONCAT(p.first_name,' ',p.last_name) AS staff_name
     FROM consultations c LEFT JOIN profiles p ON p.user_id = c.staff_id
     WHERE c.patient_id = ? ORDER BY c.consultation_date DESC"
);
$stmt->execute([$uid]);
$consultations = $stmt->fetchAll();

// Fetch sick leave history
$stmt = $pdo->prepare('SELECT * FROM sick_leaves WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$uid]);
$sickLeaves = $stmt->fetchAll();

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<div class="alert alert-info">This page combines your medicine requests, equipment loans, consultation history, and sick leave history into one activity overview.</div>
<ul class="nav nav-pills mb-3">
    <?php foreach (['' => 'All Medicine', 'dispensed' => 'Dispensed', 'rejected' => 'Declined'] as $key => $lbl): ?>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === $key ? 'active' : '' ?>" href="<?= e(url('user/my_requests.php' . ($key ? '?status=' . $key : ''))) ?>"><?= $lbl ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card mb-3">
    <div class="card-header">Medicine History</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Date</th><th>Medicine</th><th class="text-end">Qty</th><th>Reason</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php if (!$requests): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No requests found.</td></tr>
            <?php else: foreach ($requests as $r): ?>
                <tr>
                    <td><?= e(fmt_dt($r['requested_at'], 'M d, Y g:i A')) ?></td>
                    <td><?= e($r['medicine_name']) ?><?php if ($r['generic_name']): ?><div class="small text-muted"><?= e($r['generic_name']) ?></div><?php endif; ?></td>
                    <td class="text-end"><?= (int)$r['quantity'] ?></td>
                    <td><?= e(mb_strimwidth((string)$r['reason'], 0, 40, '…')) ?></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'bg-secondary' ?>"><?= $r['status'] === 'rejected' ? 'Declined' : ucfirst($r['status']) ?></span></td>
                    <td><?= e($r['remarks'] ?: '—') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Equipment History</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Equipment</th><th class="text-end">Qty</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$loans): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No equipment history found.</td></tr>
            <?php else: foreach ($loans as $loan): ?>
                <tr>
                    <td><?= e($loan['equipment_name']) ?></td>
                    <td class="text-end"><?= (int)$loan['quantity'] ?></td>
                    <td><?= e($loan['borrow_date'] ? fmt_dt($loan['borrow_date'], 'M d, Y') : '—') ?></td>
                    <td><?= e($loan['due_date'] ? fmt_dt($loan['due_date'], 'M d, Y') : '—') ?></td>
                    <td><?= e($loan['return_date'] ? fmt_dt($loan['return_date'], 'M d, Y') : '—') ?></td>
                    <td><span class="badge <?= $loanBadge[$loan['status']] ?? 'bg-secondary' ?>"><?= ucfirst($loan['status']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Consultation History</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Date</th><th>Complaint</th><th>Diagnosis</th><th>Attending</th></tr></thead>
            <tbody>
            <?php if (!$consultations): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No consultation records found.</td></tr>
            <?php else: foreach ($consultations as $consultation): ?>
                <tr>
                    <td><?= e(fmt_dt($consultation['consultation_date'], 'M d, Y g:i A')) ?></td>
                    <td><?= e(mb_strimwidth((string)$consultation['complaint'], 0, 50, '…')) ?: '—' ?></td>
                    <td><?= e(mb_strimwidth((string)$consultation['diagnosis'], 0, 50, '…')) ?: '—' ?></td>
                    <td><?= e($consultation['staff_name'] ?: '—') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($sickLeaves): ?>
<div class="card">
    <div class="card-header">Sick Leave History</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Date Filed</th>
                    <th>Reason</th>
                    <th>Leave Period</th>
                    <th>Status</th>
                    <th>Reviewed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leave = $sickLeaves as $leave): ?>
                    <tr>
                        <td><?= e(fmt_dt($leave['created_at'], 'M d, Y g:i A')) ?></td>
                        <td><?= e(mb_strimwidth($leave['reason'], 0, 50, '…')) ?></td>
                        <td>
                            <?= e(date('M d, Y', strtotime($leave['start_date']))) ?>
                            <?php if ($leave['end_date']): ?>
                                – <?= e(date('M d, Y', strtotime($leave['end_date']))) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($leave['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php elseif ($leave['status'] === 'approved'): ?>
                                <span class="badge bg-success">Approved</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Declined</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($leave['review_note']): ?>
                                <?= e($leave['review_note']) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
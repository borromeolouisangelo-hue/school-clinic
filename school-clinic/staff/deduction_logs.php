<?php
/**
 * Staff — Deduction logs for medicine requests.
 * Shows what was deducted from stock after each request.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

$page_title = 'Deduction Logs';

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT sm.id AS movement_id, sm.type, sm.quantity AS deducted_qty,
            sm.reason, sm.ref_type, sm.ref_id, sm.created_at AS deducted_at,
            m.id AS medicine_id, m.name AS medicine_name, m.unit,
            b.batch_no, b.lot_no,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            r.status AS request_status, r.reason AS request_reason
     FROM stock_movements sm
     JOIN medicines m ON m.id = sm.medicine_id
     LEFT JOIN medicine_batches b ON b.id = sm.batch_id
     LEFT JOIN medicine_requests r ON r.id = sm.ref_id AND sm.ref_type = 'dispense'
     LEFT JOIN profiles p ON p.user_id = r.user_id
     WHERE sm.type = 'OUT'";

$params = [];
if ($search !== '') {
    $sql .= ' AND (m.name LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR b.batch_no LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
if (in_array($statusFilter, ['dispensed', 'rejected'], true)) {
    $sql .= ' AND r.status = ?';
    $params[] = $statusFilter;
}

$sql .= ' ORDER BY sm.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deductions = $stmt->fetchAll();

// Summary stats
$totalDeductions = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE type = 'OUT'")->fetchColumn();
$todayDeductions = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE type = 'OUT' AND DATE(created_at) = CURDATE()")->fetchColumn();

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div class="metric-icon icon-primary"><i class="bi bi-box-arrow-down"></i></div>
            <div class="metric-value"><?= $totalDeductions ?></div>
            <div class="metric-label">Total Deductions</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div class="metric-icon icon-warning"><i class="bi bi-calendar-day"></i></div>
            <div class="metric-value"><?= $todayDeductions ?></div>
            <div class="metric-label">Today's Deductions</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end audit-toolbar">
                    <div class="col">
                        <label class="form-label">Search</label>
                        <input type="search" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="Medicine, patient, or batch..." aria-label="Search deduction logs">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Filter</label>
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="dispensed" <?= $statusFilter === 'dispensed' ? 'selected' : '' ?>>Dispensed</option>
                            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Declined</option>
                        </select>
                    </div>
                    <div class="col d-flex flex-wrap gap-2 justify-content-start">
                        <button class="btn btn-primary text-nowrap"><i class="bi bi-search me-1"></i> Search</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Medicine</th>
                            <th>Batch</th>
                            <th>Patient</th>
                            <th class="text-end">Qty Deducted</th>
                            <th class="text-end">Pieces</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$deductions): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No deduction records found.</td></tr>
                    <?php else: foreach ($deductions as $d): ?>
                        <?php
                        $deductedQty = (int)$d['deducted_qty'];
                        ?>
                        <tr>
                            <td><?= e(fmt_dt($d['deducted_at'], 'M d, Y g:i A')) ?></td>
                            <td><?= e($d['medicine_name']) ?></td>
                            <td><?= e($d['batch_no'] ?: '—') ?></td>
                            <td><?= e($d['patient_name'] ?: '—') ?></td>
                            <td class="text-end"><?= $deductedQty ?> <?= e($d['unit']) ?></td>
                            <td class="text-end"><?= $deductedQty ?> pcs</td>
                            <td><?= e($d['reason'] ?: '—') ?></td>
                            <td>
                                <?php if ($d['request_status'] === 'dispensed'): ?>
                                    <span class="badge bg-success">Dispensed</span>
                                <?php elseif ($d['request_status'] === 'rejected'): ?>
                                    <span class="badge bg-danger">Declined</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= e(ucfirst($d['request_status'] ?? 'N/A')) ?></span>
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

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
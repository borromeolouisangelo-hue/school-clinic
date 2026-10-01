<?php
/**
 * Admin — Reports overview.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'Reports';

[$period, $from, $to] = report_range($_GET['period'] ?? 'monthly', $_GET['from'] ?? null, $_GET['to'] ?? null);

// Consultations in range
$stmt = $pdo->prepare('SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) BETWEEN ? AND ?');
$stmt->execute([$from, $to]);
$consCount = (int)$stmt->fetchColumn();

// Requested medicine units in range
$stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM medicine_requests WHERE DATE(requested_at) BETWEEN ? AND ?');
$stmt->execute([$from, $to]);
$requestCount = (int)$stmt->fetchColumn();

// Medicine usage (top)
$stmt = $pdo->prepare(
    'SELECT m.name, r.reason, SUM(r.quantity) AS total
     FROM medicine_requests r JOIN medicines m ON m.id = r.medicine_id
     WHERE DATE(r.requested_at) BETWEEN ? AND ?
     GROUP BY r.medicine_id, r.reason ORDER BY total DESC'
);
$stmt->execute([$from, $to]);
$medicineRequests = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT m.name, SUM(r.quantity) AS total
     FROM medicine_requests r JOIN medicines m ON m.id = r.medicine_id
     WHERE DATE(r.requested_at) BETWEEN ? AND ?
     GROUP BY r.medicine_id ORDER BY total DESC'
);
$stmt->execute([$from, $to]);
$medicineChart = $stmt->fetchAll();

// Borrowed equipment in range
$stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM equipment_loans WHERE DATE(borrow_date) BETWEEN ? AND ?');
$stmt->execute([$from, $to]);
$loanCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT e.name, l.notes AS reason, SUM(l.quantity) AS total
     FROM equipment_loans l JOIN equipment e ON e.id = l.equipment_id
     WHERE DATE(l.borrow_date) BETWEEN ? AND ?
     GROUP BY l.equipment_id, l.notes ORDER BY total DESC'
);
$stmt->execute([$from, $to]);
$equipmentLoans = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT e.name, SUM(l.quantity) AS total
     FROM equipment_loans l JOIN equipment e ON e.id = l.equipment_id
     WHERE DATE(l.borrow_date) BETWEEN ? AND ?
     GROUP BY l.equipment_id ORDER BY total DESC'
);
$stmt->execute([$from, $to]);
$equipmentChart = $stmt->fetchAll();

// Certificates issued
$stmt = $pdo->prepare('SELECT COUNT(*) FROM medical_certificates WHERE DATE(issued_at) BETWEEN ? AND ?');
$stmt->execute([$from, $to]);
$certCount = (int)$stmt->fetchColumn();

// Low stock list (non-expired stock only)
$lowStock = $pdo->query(
    "SELECT m.name, m.low_stock_threshold, COALESCE(SUM(b.quantity),0) AS stock
     FROM medicines m LEFT JOIN medicine_batches b ON b.medicine_id = m.id
        AND b.quantity > 0 AND (b.expiry_date IS NULL OR b.expiry_date >= CURDATE())
     WHERE m.is_active = 1 GROUP BY m.id HAVING stock <= m.low_stock_threshold ORDER BY stock ASC"
)->fetchAll();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <div id="reportArea">
                <div class="mb-3">
                    <h5 class="text-primary mb-0"><?= e(get_setting($pdo, 'clinic_name', 'School Health Clinic')) ?></h5>
                    <div class="text-muted small">Report period: <?= e(fmt_dt($from, 'M d, Y')) ?> to <?= e(fmt_dt($to, 'M d, Y')) ?></div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-lg-3"><div class="metric-card"><div class="metric-icon icon-primary"><i class="bi bi-clipboard2-pulse"></i></div><div class="metric-value"><?= $consCount ?></div><div class="metric-label">Consultations</div></div></div>
                    <div class="col-6 col-lg-3"><div class="metric-card"><div class="metric-icon icon-warning"><i class="bi bi-capsule"></i></div><div class="metric-value"><?= $requestCount ?></div><div class="metric-label">Medicine Units Requested</div></div></div>
                    <div class="col-6 col-lg-3"><div class="metric-card"><div class="metric-icon icon-success"><i class="bi bi-tools"></i></div><div class="metric-value"><?= $loanCount ?></div><div class="metric-label">Equipment Loans</div></div></div>
                    <div class="col-6 col-lg-3"><div class="metric-card"><div class="metric-icon icon-primary"><i class="bi bi-file-earmark-medical"></i></div><div class="metric-value"><?= $certCount ?></div><div class="metric-label">Certificates Issued</div></div></div>
                </div>
                <div class="card mb-3 no-print">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Reporting period</label>
                            <select name="period" class="form-select">
                                <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="yearly" <?= $period === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">From</label>
                            <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To</label>
                            <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="button" class="btn btn-accent" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
                            <button type="button" class="btn  btn-outline-primary" data-export-table="#adminReportTable" data-export-name="clinic-admin-report"><i class="bi bi-download me-1"></i> CSV</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-3" id="adminReportTable">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">Requested Medicines and Reasons</div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Medicine</th><th>Reason</th><th class="text-end">Qty</th></tr></thead>
                                <tbody>
                                <?php if (!$medicineRequests): ?><tr><td colspan="3" class="text-center text-muted py-3">No medicine requests in this period.</td></tr>
                                <?php else: foreach ($medicineRequests as $request): ?><tr><td><?= e($request['name']) ?></td><td><?= e($request['reason'] ?: '—') ?></td><td class="text-end"><?= (int)$request['total'] ?></td></tr><?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">Borrowed Equipment and Reasons</div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0"><thead><tr><th>Equipment</th><th>Reason</th><th class="text-end">Qty</th></tr></thead>
                                <tbody>
                                <?php if (!$equipmentLoans): ?><tr><td colspan="3" class="text-center text-muted py-3">No equipment loans in this period.</td></tr>
                                <?php else: foreach ($equipmentLoans as $loan): ?><tr><td><?= e($loan['name']) ?></td><td><?= e($loan['reason'] ?: '—') ?></td><td class="text-end"><?= (int)$loan['total'] ?></td></tr><?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6"><div class="card h-100"><div class="card-header">Medicine Request Distribution</div><div class="card-body"><canvas id="medicineRequestsChart" width="640" height="320"></canvas></div></div></div>
                <div class="col-lg-6"><div class="card h-100"><div class="card-header">Equipment Loan Distribution</div><div class="card-body"><canvas id="equipmentLoansChart" width="640" height="320"></canvas></div></div></div>
            </div>
        </div>

    <script src="<?= e(url('assets/js/charts.js')) ?>"></script>
    <script>drawPieChart('medicineRequestsChart', <?= json_encode(array_column($medicineChart, 'name')) ?>, <?= json_encode(array_map('intval', array_column($medicineChart, 'total'))) ?>); drawPieChart('equipmentLoansChart', <?= json_encode(array_column($equipmentChart, 'name')) ?>, <?= json_encode(array_map('intval', array_column($equipmentChart, 'total'))) ?>);</script>

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
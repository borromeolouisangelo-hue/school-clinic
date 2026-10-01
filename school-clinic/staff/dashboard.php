<?php
/**
 * Staff / frontdesk dashboard.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

$page_title = 'Clinic Dashboard';

// --- Today's stats ---
$todayRequests = (int)$pdo->query("SELECT COUNT(*) FROM medicine_requests WHERE DATE(requested_at) = CURDATE()")->fetchColumn();
$todayCons     = (int)$pdo->query("SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = CURDATE()")->fetchColumn();
$activeLoans   = (int)$pdo->query("SELECT COUNT(*) FROM equipment_loans WHERE status = 'borrowed'")->fetchColumn();
$overdue       = (int)$pdo->query("SELECT COUNT(*) FROM equipment_loans WHERE status = 'borrowed' AND borrowed_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();

// --- Yesterday's stats for trend comparison ---
$yesterdayRequests = (int)$pdo->query("SELECT COUNT(*) FROM medicine_requests WHERE DATE(requested_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
$yesterdayCons     = (int)$pdo->query("SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
$yesterdayLoans    = (int)$pdo->query("SELECT COUNT(*) FROM equipment_loans WHERE status = 'borrowed' AND DATE(borrow_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();

// --- Previous period loans (2 days ago) for trend ---
$prevActiveLoans   = (int)$pdo->query("SELECT COUNT(*) FROM equipment_loans WHERE status = 'borrowed' AND DATE(borrow_date) <= DATE_SUB(CURDATE(), INTERVAL 2 DAY)")->fetchColumn();

function trend_info(int $today, int $yesterday): array
{
    if ($yesterday === 0) {
        return ['pct' => null, 'dir' => 'up'];
    }
    $pct = round((($today - $yesterday) / $yesterday) * 100, 1);
    return ['pct' => abs($pct), 'dir' => $pct >= 0 ? 'up' : 'down'];
}

$reqTrend  = trend_info($todayRequests, $yesterdayRequests);
$consTrend = trend_info($todayCons, $yesterdayCons);
$loanTrend = trend_info($activeLoans, $prevActiveLoans);

// --- Frequently requested / borrowed ---
$requestedItems = $pdo->query(
    "SELECT m.name, SUM(mr.quantity) AS total_quantity
     FROM medicine_requests mr
     JOIN medicines m ON m.id = mr.medicine_id
     GROUP BY mr.medicine_id, m.name
     ORDER BY total_quantity DESC, m.name ASC
     LIMIT 6"
)->fetchAll();

$borrowedItems = $pdo->query(
    "SELECT e.name, SUM(el.quantity) AS total_quantity
     FROM equipment_loans el
     JOIN equipment e ON e.id = el.equipment_id
     GROUP BY el.equipment_id, e.name
     ORDER BY total_quantity DESC, e.name ASC
     LIMIT 6"
)->fetchAll();

// --- 7-day trend data ---
$days = 7;
$trendLabels = [];
$trendValues = [];
$consultationValues = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('D', strtotime($date));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medicine_requests WHERE DATE(requested_at) = ?");
    $stmt->execute([$date]);
    $trendValues[] = (int)$stmt->fetchColumn();

    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = ?");
    $stmt2->execute([$date]);
    $consultationValues[] = (int)$stmt2->fetchColumn();
}

// Period totals
$periodTotal = array_sum($trendValues);
$prevPeriodTotal = 0;
for ($i = $days * 2 - 1; $i >= $days; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medicine_requests WHERE DATE(requested_at) = ?");
    $stmt->execute([$date]);
    $prevPeriodTotal += (int)$stmt->fetchColumn();
}
$periodChange = $prevPeriodTotal > 0 ? round((($periodTotal - $prevPeriodTotal) / $prevPeriodTotal) * 100, 1) : 0;

/**
 * Build a per-day time series of requests and consultations for the last
 * $days days, plus the period total and its change against the previous
 * period of equal length. Two grouped queries total, regardless of range.
 */
function clinic_trend_range(PDO $pdo, int $days): array
{
    $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    $requests = [];
    $stmt = $pdo->prepare('SELECT DATE(requested_at) AS d, COUNT(*) AS c FROM medicine_requests WHERE requested_at >= ? GROUP BY DATE(requested_at)');
    $stmt->execute([$start]);
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $date => $count) {
        $requests[$date] = (int)$count;
    }

    $consultations = [];
    $stmt = $pdo->prepare('SELECT DATE(consultation_date) AS d, COUNT(*) AS c FROM consultations WHERE consultation_date >= ? GROUP BY DATE(consultation_date)');
    $stmt->execute([$start]);
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $date => $count) {
        $consultations[$date] = (int)$count;
    }

    $labels = [];
    $requestSeries = [];
    $consultationSeries = [];
    $total = 0;
    for ($i = $days - 1; $i >= 0; $i--) {
        $date  = date('Y-m-d', strtotime("-$i days"));
        $count = $requests[$date] ?? 0;
        $labels[]            = $days > 7 ? date('d M', strtotime($date)) : date('D', strtotime($date));
        $requestSeries[]     = $count;
        $consultationSeries[] = $consultations[$date] ?? 0;
        $total += $count;
    }

    $previousTotal = 0;
    for ($i = $days * 2 - 1; $i >= $days; $i--) {
        $previousTotal += $requests[date('Y-m-d', strtotime("-$i days"))] ?? 0;
    }

    return [
        'labels'        => $labels,
        'requests'      => $requestSeries,
        'consultations' => $consultationSeries,
        'total'         => $total,
        'change'        => $previousTotal > 0 ? round((($total - $previousTotal) / $previousTotal) * 100, 1) : 0,
    ];
}

$trendRanges = [
    '7'  => clinic_trend_range($pdo, 7),
    '14' => clinic_trend_range($pdo, 14),
    '30' => clinic_trend_range($pdo, 30),
];

$lowStock = $pdo->query(
    "SELECT m.name, m.low_stock_threshold, COALESCE(SUM(b.quantity),0) AS stock
     FROM medicines m LEFT JOIN medicine_batches b ON b.medicine_id = m.id
        AND b.quantity > 0
     WHERE m.is_active = 1 GROUP BY m.id HAVING stock <= m.low_stock_threshold ORDER BY stock ASC LIMIT 6"
)->fetchAll();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <div class="staff-dashboard-shell">
                <div class="staff-dashboard-intro">
                    <h2>Clinic Dashboard</h2>
                </div>

                <!-- KPI Stat Cards -->
                <div class="row g-3 mb-4 staff-kpi-row">
                    <div class="col-6 col-lg-3">
                        <div class="metric-card">
                            <div class="metric-icon icon-primary"><i class="bi bi-activity"></i></div>
                            <div class="metric-value"><?= $todayRequests ?></div>
                            <div class="metric-label">Requests Today</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="metric-card">
                            <div class="metric-icon icon-success"><i class="bi bi-heart-pulse"></i></div>
                            <div class="metric-value"><?= $todayCons ?></div>
                            <div class="metric-label">Consultations Today</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="metric-card">
                            <div class="metric-icon icon-primary"><i class="bi bi-arrow-left-right"></i></div>
                            <div class="metric-value"><?= $activeLoans ?></div>
                            <div class="metric-label">Active Loans</div>
                            <?php if ($loanTrend['pct'] !== null): ?>
                                <span class="trend-badge trend-<?= $loanTrend['dir'] ?>"><i class="bi bi-arrow-<?= $loanTrend['dir'] ?>"></i> <?= $loanTrend['dir'] === 'up' ? '+' : '-' ?><?= $loanTrend['pct'] ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="metric-card">
                            <div class="metric-icon icon-warning"><i class="bi bi-exclamation-triangle"></i></div>
                            <div class="metric-value"><?= $overdue ?></div>
                            <div class="metric-label">Overdue Loans</div>
                            <?php
                            $yesterdayOverdue = (int)$pdo->query("SELECT COUNT(*) FROM equipment_loans WHERE status = 'borrowed' AND borrowed_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND borrowed_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)")->fetchColumn();
                            $overdueTrend = trend_info($overdue, $yesterdayOverdue);
                            ?>
                            <?php if ($overdueTrend['pct'] !== null): ?>
                                <span class="trend-badge trend-<?= $overdueTrend['dir'] ?>"><i class="bi bi-arrow-<?= $overdueTrend['dir'] ?>"></i> <?= $overdueTrend['dir'] === 'up' ? '+' : '-' ?><?= $overdueTrend['pct'] ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Line Chart with Period Sidebar -->
                <div class="row g-2 mb-3 staff-dashboard-charts">
                    <div class="col-12">
                        <div class="card chart-card staff-chart-card staff-line-card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <i class="bi bi-graph-up me-1"></i> Total requests over time
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="chart-legend">
                                        <span class="legend-item"><span class="legend-dot" style="background:#2f7bbd"></span> Requests</span>
                                        <span class="legend-item"><span class="legend-dot" style="background:#4a9f86"></span> Consultations</span>
                                    </div>
                                    <select class="form-select form-select-sm chart-period-select" style="width:auto">
                                        <option value="7" selected>Last 7 days</option>
                                        <option value="14">Last 14 days</option>
                                        <option value="30">Last 30 days</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex gap-3">
                                    <div class="flex-grow-1">
                                        <canvas id="requestTrendChart" class="dashboard-chart" width="1040" height="240"></canvas>
                                    </div>
                                    <div class="period-sidebar">
                                        <div class="period-total-label">Period Total</div>
                                        <div class="period-total-value" id="periodTotalValue"><?= $periodTotal ?></div>
                                        <div class="period-total-sub">requests logged</div>
                                        <div class="period-change <?= $periodChange >= 0 ? 'text-success' : 'text-danger' ?>" id="periodChange">
                                            <i class="bi bi-arrow-<?= $periodChange >= 0 ? 'up' : 'down' ?>"></i> <?= abs($periodChange) ?>%
                                        </div>
                                        <div class="period-change-label">vs previous period</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Charts -->
                <div class="row g-2 mb-3 staff-dashboard-charts">
                    <div class="col-lg-6">
                        <div class="card chart-card staff-chart-card h-100">
                            <div class="card-header"><i class="bi bi-capsule me-1"></i> Frequently Requested Medicines</div>
                            <div class="card-body"><canvas id="requestedItemsChart" class="dashboard-chart" width="520" height="150"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card chart-card staff-chart-card h-100">
                            <div class="card-header"><i class="bi bi-tools me-1"></i> Frequently Borrowed Equipment</div>
                            <div class="card-body"><canvas id="borrowedItemsChart" class="dashboard-chart" width="520" height="150"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><i class="bi bi-exclamation-triangle text-accent me-1"></i> Low Stock Alerts</div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>Medicine</th><th class="text-end">Stock</th><th class="text-end">Threshold</th></tr></thead>
                                    <tbody>
                                    <?php if (!$lowStock): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">All items sufficiently stocked.</td></tr>
                                    <?php else: foreach ($lowStock as $l): ?>
                                        <tr><td><?= e($l['name']) ?></td><td class="text-end"><span class="badge bg-danger"><?= (int)$l['stock'] ?></span></td><td class="text-end"><?= (int)$l['low_stock_threshold'] ?></td></tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <script src="<?= e(url('assets/js/charts.js')) ?>"></script>
    <script>
    (function () {
        var ranges = <?= json_encode($trendRanges, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        var requestedLabels = <?= json_encode(array_column($requestedItems, 'name'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        var requestedTotals = <?= json_encode(array_map('intval', array_column($requestedItems, 'total_quantity'))) ?>;
        var borrowedLabels  = <?= json_encode(array_column($borrowedItems, 'name'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        var borrowedTotals  = <?= json_encode(array_map('intval', array_column($borrowedItems, 'total_quantity'))) ?>;

        function renderTrend(days) {
            var range = ranges[String(days)] || ranges['7'];
            if (!range) return;
            drawLineChart('requestTrendChart', range.labels, range.requests, { secondaryData: range.consultations });

            var totalEl = document.getElementById('periodTotalValue');
            if (totalEl) totalEl.textContent = range.total;

            var changeEl = document.getElementById('periodChange');
            if (changeEl) {
                var up = range.change >= 0;
                changeEl.className = 'period-change ' + (up ? 'text-success' : 'text-danger');
                changeEl.innerHTML = '<i class="bi bi-arrow-' + (up ? 'up' : 'down') + '"></i> ' + Math.abs(range.change) + '%';
            }
        }

        renderTrend(<?= (int)$days ?>);
        drawHorizontalBarChart('requestedItemsChart', requestedLabels, requestedTotals, { barColor: '#1a56a8' });
        drawHorizontalBarChart('borrowedItemsChart', borrowedLabels, borrowedTotals, { barColor: '#7950f2' });

        var select = document.querySelector('.chart-period-select');
        if (select) {
            select.addEventListener('change', function () { renderTrend(parseInt(this.value, 10) || 7); });
        }
    })();
    </script>

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
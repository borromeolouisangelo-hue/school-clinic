<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Admin dashboard.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'Admin Dashboard';

// Stats
$totalUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalStaff   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
$totalMeds    = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1")->fetchColumn();
$todayRequests = (int)$pdo->query("SELECT COUNT(*) FROM medicine_requests WHERE DATE(requested_at) = CURDATE()")->fetchColumn();
$activeLoans  = (int)$pdo->query("SELECT COUNT(*) FROM equipment_loans WHERE status = 'borrowed'")->fetchColumn();

// Low stock medicines (non-expired stock only)
$lowStock = $pdo->query(
    "SELECT m.id, m.name, m.low_stock_threshold,
            COALESCE(SUM(b.quantity),0) AS stock
     FROM medicines m
     LEFT JOIN medicine_batches b ON b.medicine_id = m.id
        AND b.quantity > 0 AND (b.expiry_date IS NULL OR b.expiry_date >= CURDATE())
     WHERE m.is_active = 1
     GROUP BY m.id
     HAVING stock <= m.low_stock_threshold
     ORDER BY stock ASC
     LIMIT 8"
)->fetchAll();

// Expiring soon (within 90 days), non-expired batches only
$expiring = $pdo->query(
    "SELECT b.*, m.name AS medicine_name
     FROM medicine_batches b
     JOIN medicines m ON m.id = b.medicine_id
     WHERE b.quantity > 0 AND b.expiry_date IS NOT NULL
       AND b.expiry_date >= CURDATE()
       AND b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
     ORDER BY b.expiry_date ASC
     LIMIT 8"
)->fetchAll();

// Recent activity
$recent = $pdo->query(
    "SELECT a.*, u.username
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 8"
)->fetchAll();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="metric-card">
                        <div class="metric-icon icon-primary"><i class="bi bi-people"></i></div>
                        <div class="metric-value"><?= $totalUsers ?></div>
                        <div class="metric-label">Students / Employees</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="metric-card">
                        <div class="metric-icon icon-success"><i class="bi bi-person-badge"></i></div>
                        <div class="metric-value"><?= $totalStaff ?></div>
                        <div class="metric-label">Clinic Staff</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="metric-card">
                        <div class="metric-icon icon-warning"><i class="bi bi-capsule"></i></div>
                        <div class="metric-value"><?= $totalMeds ?></div>
                        <div class="metric-label">Active Medicines</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="metric-card">
                        <div class="metric-icon icon-primary"><i class="bi bi-activity"></i></div>
                        <div class="metric-value"><?= $todayRequests ?></div>
                        <div class="metric-label">Requests Today</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-exclamation-triangle text-accent me-1"></i> Low Stock</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Medicine</th><th class="text-end">Stock</th><th class="text-end">Threshold</th></tr></thead>
                                <tbody>
                                <?php if (!$lowStock): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No low-stock items.</td></tr>
                                <?php else: foreach ($lowStock as $r): ?>
                                    <tr>
                                        <td><?= e($r['name']) ?></td>
                                        <td class="text-end"><span class="badge bg-danger"><?= (int)$r['stock'] ?></span></td>
                                        <td class="text-end"><?= (int)$r['low_stock_threshold'] ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><i class="bi bi-calendar-x text-accent me-1"></i> Expiring Within 90 Days</div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-end">Qty</th></tr></thead>
                                <tbody>
                                <?php if (!$expiring): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No batches expiring soon.</td></tr>
                                <?php else: foreach ($expiring as $r): ?>
                                    <tr>
                                        <td><?= e($r['medicine_name']) ?></td>
                                        <td><?= e($r['batch_no'] ?: '—') ?></td>
                                        <td><?= e(fmt_dt($r['expiry_date'], 'M d, Y')) ?></td>
                                        <td class="text-end"><?= (int)$r['quantity'] ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-clock-history me-1"></i> Recent Activity</span>
                            <a href="<?= e(url('admin/audit_logs.php')) ?>" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
                                <tbody>
                                <?php if (!$recent): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No activity yet.</td></tr>
                                <?php else: foreach ($recent as $r): ?>
                                    <tr>
                                        <td><?= e(fmt_dt($r['created_at'])) ?></td>
                                        <td><?= e($r['username'] ?: 'system') ?></td>
                                        <td><span class="chip"><?= e($r['action']) ?></span></td>
                                        <td><?= e($r['details']) ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.content-area -->
    </main>
    <!-- /.main-content -->
</div>
<!-- /.app-wrapper -->

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
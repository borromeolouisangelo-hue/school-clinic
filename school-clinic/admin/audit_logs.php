<?php
/**
 * Admin — Audit logs.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'Audit Logs';

$search = trim($_GET['q'] ?? '');
$actionFilter = trim($_GET['action_filter'] ?? '');

$where = []; $params = [];
if ($search !== '') {
    $where[] = '(u.username LIKE ? OR a.action LIKE ? OR a.details LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
if ($actionFilter !== '') {
    $where[] = 'a.action = ?';
    $params[] = $actionFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$actions = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT a.*, u.username
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        $whereSql
        ORDER BY a.created_at DESC
        LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <div class="card mt-3 mb-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end audit-toolbar">
                        <div class="col">
                            <label class="form-label">Search</label>
                            <input type="search" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="User, action or details" aria-label="Search audit logs">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label">Filter</label>
                                    <select name="action_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">All Actions</option>
                                        <option value="login" <?= (($_GET['action_filter'] ?? '') === 'login') ? 'selected' : '' ?>>Login</option>
                                        <option value="logout" <?= (($_GET['action_filter'] ?? '') === 'logout') ? 'selected' : '' ?>>Logout</option>
                                        <option value="create" <?= (($_GET['action_filter'] ?? '') === 'create') ? 'selected' : '' ?>>Create</option>
                                        <option value="update" <?= (($_GET['action_filter'] ?? '') === 'update') ? 'selected' : '' ?>>Update</option>
                                        <option value="delete" <?= (($_GET['action_filter'] ?? '') === 'delete') ? 'selected' : '' ?>>Delete</option>
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
                            <table id="auditLogsTable" class="table table-hover mb-0 align-middle">
                                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Table</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
                                <tbody>
                                <?php if (!$logs): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No logs found.</td></tr>
                                <?php else: foreach ($logs as $l): ?>
                                    <?php
                                        $actionName = (string)($l['action'] ?? '');
                                        $actionLower = strtolower($actionName);
                                        $actionClass = 'chip';
                                        $actionStyle = '';
                                        if (strpos($actionLower, 'login') !== false) {
                                            $actionClass = 'chip chip-success';
                                            $actionStyle = 'background: rgba(25, 135, 84, 0.12); color: #146c43; border: 1px solid rgba(25, 135, 84, 0.25);';
                                        } elseif (strpos($actionLower, 'logout') !== false) {
                                            $actionClass = 'chip chip-danger';
                                            $actionStyle = 'background: rgba(220, 53, 69, 0.12); color: #b02a37; border: 1px solid rgba(220, 53, 69, 0.25);';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= e(fmt_dt($l['created_at'])) ?></td>
                                        <td><?= e($l['username'] ?: 'system') ?></td>
                                        <td><span class="<?= $actionClass ?>" style="<?= e($actionStyle) ?>"><?= e($l['action']) ?></span></td>
                                        <td><?= e($l['table_name'] ?: '—') ?></td>
                                        <td><?= $l['record_id'] !== null ? (int)$l['record_id'] : '—' ?></td>
                                        <td><?= e($l['details']) ?></td>
                                        <td><?= e($l['ip_address']) ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
        </div>
        <!-- /.content-area -->
    </main>
    <!-- /.main-content -->
</div>
<!-- /.app-wrapper -->

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
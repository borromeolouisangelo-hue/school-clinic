<?php
/**
 * Admin — Database backup.
 * Backs up whitelisted tables to a file OUTSIDE the web root
 * (config/backups) and logs the action.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'Backup';
$errors = [];
$success = false;

// Whitelist of tables that are safe to back up (no secrets beyond hashes).
$allowedTables = [
    'medicine_categories', 'suppliers', 'medicines', 'medicine_batches',
    'stock_movements', 'stock_adjustments', 'medicine_requests',
    'dispense_logs', 'consultations', 'equipment', 'equipment_loans', 'notifications',
    'medical_certificates', 'audit_logs', 'system_settings',
    'users', 'profiles', 'student_details', 'employee_details',
    'medical_records', 'user_allergies'
];

// Backup directory outside the web root (one level above htdocs).
$backupDir = dirname(__DIR__) . '/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['tables'] ?? [];
    $selected = array_intersect($selected, $allowedTables);

    if (empty($selected)) {
        $errors[] = 'Please select at least one table to back up.';
    } elseif (!is_dir($backupDir) || !is_writable($backupDir)) {
        $errors[] = 'Backup directory is not writable. Create /school-clinic/backups manually.';
    } else {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.sql';
        $filepath = $backupDir . '/' . $filename;

        $handle = fopen($filepath, 'w');
        if (!$handle) {
            $errors[] = 'Could not open backup file for writing.';
        } else {
            fwrite($handle, "-- School Clinic backup\n");
            fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");

            foreach ($selected as $table) {
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                fwrite($handle, "-- Table: $table (" . count($rows) . " rows)\n");
                if (empty($rows)) {
                    continue;
                }
                $cols = array_keys($rows[0]);
                $colList = '`' . implode('`,`', $cols) . '`';
                foreach ($rows as $row) {
                    $values = [];
                    foreach (array_values($row) as $v) {
                        $values[] = $v === null ? 'NULL' : $pdo->quote($v);
                    }
                    fwrite($handle, "INSERT INTO `$table` ($colList) VALUES (" . implode(',', $values) . ");\n");
                }
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
            fclose($handle);

            log_action($pdo, (int)current_user()['id'], 'backup', null, null,
                "Backed up tables: " . implode(', ', $selected) . " -> $filename");
            set_flash('success', "Backup created: $filename");
            $success = true;
        }
    }
}

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-cloud-arrow-down me-1"></i> Create Backup</div>
                        <div class="card-body">
                            <?php foreach ($errors as $err): ?>
                                <div class="alert alert-danger py-2"><?= e($err) ?></div>
                            <?php endforeach; ?>
                            <?php if ($success): ?>
                                <div class="alert alert-success py-2">Backup created successfully.</div>
                            <?php endif; ?>
                            <p class="text-muted small mb-3">
                                Backups are written to <code><?= e($backupDir) ?></code>, outside the web root.
                                Only whitelisted tables are exported.
                            </p>
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Select Tables</label>
                                    <div class="row g-2">
                                        <?php foreach ($allowedTables as $t): ?>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="tables[]" value="<?= e($t) ?>" id="tbl_<?= $t ?>" checked>
                                                    <label class="form-check-label" for="tbl_<?= $t ?>"><?= e($t) ?></label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-download me-1"></i> Generate Backup</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-history me-1"></i> Recent Backups</div>
                        <div class="card-body">
                            <?php
                            $files = [];
                            if (is_dir($backupDir)) {
                                $files = glob($backupDir . '/*.sql') ?: [];
                                rsort($files);
                            }
                            ?>
                            <?php if (empty($files)): ?>
                                <div class="text-muted small">No backups yet.</div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach (array_slice($files, 0, 20) as $f): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="text-truncate" style="max-width: 70%"><?= e(basename($f)) ?></span>
                                            <span class="text-muted small"><?= number_format(filesize($f)) ?> bytes</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
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
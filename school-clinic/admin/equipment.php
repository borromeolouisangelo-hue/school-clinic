<?php
/**
 * Admin — Equipment inventory.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'Equipment';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eid     = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $total   = (int)($_POST['total_qty'] ?? 1);
    $cond    = trim($_POST['condition_note'] ?? 'good');
    $active  = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '')  $errors[] = 'Equipment name is required.';
    if ($total < 0)    $errors[] = 'Total quantity cannot be negative.';

    if (!$errors) {
        if ($eid > 0) {
            $cur = $pdo->prepare('SELECT total_qty, available_qty FROM equipment WHERE id=?');
            $cur->execute([$eid]);
            $row = $cur->fetch();
            if (!$row) {
                $errors[] = 'Equipment record not found.';
            }
            $borrowed = $row ? max(0, (int)$row['total_qty'] - (int)$row['available_qty']) : 0;
            $newAvail = max(0, $total - $borrowed);

            if (!$errors) {
                $pdo->prepare('UPDATE equipment SET name=?, description=?, total_qty=?, available_qty=?, condition_note=?, is_active=? WHERE id=?')
                    ->execute([$name, $desc, $total, $newAvail, $cond, $active, $eid]);
                log_action($pdo, (int)current_user()['id'], 'update_equipment', 'equipment', $eid, "Updated equipment $name");
                set_flash('success', 'Equipment updated.');
            }
        } else {
            $pdo->prepare('INSERT INTO equipment (name, description, total_qty, available_qty, condition_note, is_active) VALUES (?,?,?,?,?,?)')
                ->execute([$name, $desc, $total, $total, $cond, $active]);
            log_action($pdo, (int)current_user()['id'], 'create_equipment', 'equipment', (int)$pdo->lastInsertId(), "Created equipment $name");
            set_flash('success', 'Equipment created.');
        }
        redirect('admin/equipment.php');
    }
}

if (($_GET['action'] ?? '') === 'delete' && ($eid = (int)($_GET['id'] ?? 0)) > 0) {
    $pdo->prepare('DELETE FROM equipment WHERE id=?')->execute([$eid]);
    log_action($pdo, (int)current_user()['id'], 'delete_equipment', 'equipment', $eid, "Deleted equipment #$eid");
    set_flash('success', 'Equipment deleted.');
    redirect('admin/equipment.php');
}

$edit = null;
if (($_GET['action'] ?? '') === 'edit' && ($eid = (int)($_GET['id'] ?? 0)) > 0) {
    $stmt = $pdo->prepare('SELECT * FROM equipment WHERE id=? LIMIT 1');
    $stmt->execute([$eid]);
    $edit = $stmt->fetch();
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM equipment';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE name LIKE ? OR description LIKE ? OR condition_note LIKE ?';
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= ' ORDER BY name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipment = $stmt->fetchAll();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <div class="row g-3">
                <div class="col-12">
                    <div class="modal fade" id="equipmentModal" tabindex="-1" aria-labelledby="equipmentModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-md"><div class="modal-content">
                        <div class="card">
                            <div class="card-header" id="equipmentModalLabel"><?= $edit ? 'Edit Equipment' : 'Add Equipment' ?></div>
                            <div class="card-body">
                                <?php foreach ($errors as $err): ?>
                                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                                <?php endforeach; ?>
                                <form method="post">
                                    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Name *</label>
                                        <input type="text" name="name" class="form-control" value="<?= e($edit['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="2"><?= e($edit['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label">Total Qty</label>
                                            <input type="number" name="total_qty" class="form-control" min="0" value="<?= (int)($edit['total_qty'] ?? 1) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Condition</label>
                                            <input type="text" name="condition_note" class="form-control" value="<?= e($edit['condition_note'] ?? 'good') ?>">
                                        </div>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (int)($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                    <button class="btn btn-primary" <?= $edit ? 'data-swal-save="equipment"' : 'data-swal-add="equipment"' ?>><i class="bi bi-save me-1"></i> <?= $edit ? 'Update' : 'Save' ?></button>
                                    <?php if ($edit): ?><a href="<?= e(url('admin/equipment.php')) ?>" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                                </form>
                            </div>
                        </div>
                        </div></div>
                    </div>
                </div>
                <div class="col">
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="get" class="row g-2 align-items-end audit-toolbar">
                                <div class="col">
                                    <label class="form-label">Search</label>
                                    <input type="search" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="Name, description or condition" aria-label="Search equipment">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label">Filter</label>
                                    <select name="condition" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">All Conditions</option>
                                        <option value="good" <?= (($_GET['condition'] ?? '') === 'good') ? 'selected' : '' ?>>Good</option>
                                        <option value="fair" <?= (($_GET['condition'] ?? '') === 'fair') ? 'selected' : '' ?>>Fair</option>
                                        <option value="poor" <?= (($_GET['condition'] ?? '') === 'poor') ? 'selected' : '' ?>>Poor</option>
                                    </select>
                                </div>
                                <div class="col d-flex flex-wrap gap-2 justify-content-start">
                                    <button class="btn btn-primary text-nowrap"><i class="bi bi-search me-1"></i> Search</button>
                                    <a href="<?= e(url('admin/equipment.php')) ?>" class="btn btn-sm btn-outline-secondary text-nowrap">Reset</a>
                                    <button type="button" class="btn btn-sm btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#equipmentModal"><i class="bi bi-plus-lg me-1"></i> Add Equipment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead><tr><th>#</th><th>Name</th><th>Description</th><th class="text-end">Total</th><th class="text-end">Available</th><th>Condition</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php if (!$equipment): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No equipment yet.</td></tr>
                            <?php else: foreach ($equipment as $eq): ?>
                                <tr>
                                    <td><?= (int)$eq['id'] ?></td>
                                    <td><?= e($eq['name']) ?></td>
                                    <td><?= e($eq['description']) ?></td>
                                    <td class="text-end"><?= (int)$eq['total_qty'] ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= (int)$eq['available_qty'] > 0 ? 'bg-success' : 'bg-danger' ?>"><?= (int)$eq['available_qty'] ?></span>
                                    </td>
                                    <td><?= e($eq['condition_note']) ?></td>
                                    <td><?= (int)$eq['is_active'] === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('admin/equipment.php?action=edit&id=' . $eq['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= e(url('admin/equipment.php?action=delete&id=' . $eq['id'])) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this equipment?" data-swal-delete="equipment"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
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
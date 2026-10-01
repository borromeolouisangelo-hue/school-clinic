<?php
/**
 * Admin — Medicines catalog.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'Medicines';
$errors = [];

$categories = $pdo->query(
    'SELECT * FROM medicine_categories ORDER BY name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('admin/medicines.php');
    }
    $mid      = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $generic  = trim($_POST['generic_name'] ?? '');
    $catId    = (int)($_POST['category_id'] ?? 0) ?: null;
    $unit     = trim($_POST['unit'] ?? 'tablet');
    $desc     = trim($_POST['description'] ?? '');
    $thresh   = (int)($_POST['low_stock_threshold'] ?? 10);
    $active   = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') $errors[] = 'Medicine name is required.';
    if ($unit === '') $unit = 'tablet';
    if ($thresh < 0) $errors[] = 'Low stock threshold cannot be negative.';

    if (!$errors) {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM medicines WHERE LOWER(name) = LOWER(?) AND id <> ?');
        $dup->execute([$name, $mid]);
        if ((int)$dup->fetchColumn() > 0) {
            $errors[] = 'A medicine with this name already exists.';
        }
    }

    if (!$errors) {
        if ($mid > 0) {
            $pdo->prepare('UPDATE medicines SET name=?, generic_name=?, category_id=?, unit=?, description=?, low_stock_threshold=?, is_active=? WHERE id=?')
                ->execute([$name, $generic, $catId, $unit, $desc, $thresh, $active, $mid]);
            log_action($pdo, (int)current_user()['id'], 'update_medicine', 'medicines', $mid, "Updated medicine $name");
            set_flash('success', 'Medicine updated.');
        } else {
            $pdo->prepare('INSERT INTO medicines (name, generic_name, category_id, unit, description, low_stock_threshold, is_active) VALUES (?,?,?,?,?,?,?)')
                ->execute([$name, $generic, $catId, $unit, $desc, $thresh, $active]);
            log_action($pdo, (int)current_user()['id'], 'create_medicine', 'medicines', (int)$pdo->lastInsertId(), "Created medicine $name");
            set_flash('success', 'Medicine created.');
        }
        redirect('admin/medicines.php');
    }
}

if (($_GET['action'] ?? '') === 'delete' && ($mid = (int)($_GET['id'] ?? 0)) > 0) {
    $pdo->prepare('DELETE FROM medicines WHERE id=?')->execute([$mid]);
    log_action($pdo, (int)current_user()['id'], 'delete_medicine', 'medicines', $mid, "Deleted medicine #$mid");
    set_flash('success', 'Medicine deleted.');
    redirect('admin/medicines.php');
}

$edit = null;
if (($_GET['action'] ?? '') === 'edit' && ($mid = (int)($_GET['id'] ?? 0)) > 0) {
    $stmt = $pdo->prepare('SELECT * FROM medicines WHERE id=? LIMIT 1');
    $stmt->execute([$mid]);
    $edit = $stmt->fetch();
}

$categories = $pdo->query(
    'SELECT * FROM medicine_categories ORDER BY name'
)->fetchAll();

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT m.*, c.name AS category_name,
            COALESCE((SELECT SUM(b.quantity) FROM medicine_batches b
                      WHERE b.medicine_id = m.id
                        AND b.quantity > 0
                        AND (b.expiry_date IS NULL OR b.expiry_date >= CURDATE())),0) AS stock
     FROM medicines m
     LEFT JOIN medicine_categories c ON c.id = m.category_id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE m.name LIKE ? OR m.generic_name LIKE ?';
    $params = ["%$search%", "%$search%"];
}
$sql .= ' ORDER BY m.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <div class="row g-3">
                <div class="col-12">
                    <div class="modal fade" id="medicineModal" tabindex="-1" aria-labelledby="medicineModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-md"><div class="modal-content">
                        <div class="card">
                            <div class="card-header" id="medicineModalLabel"><?= $edit ? 'Edit Medicine' : 'Add Medicine' ?></div>
                            <div class="card-body">
                                <?php foreach ($errors as $err): ?>
                                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                                <?php endforeach; ?>
                                <form method="post">
                                    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Brand / Name *</label>
                                        <input type="text" name="name" class="form-control" value="<?= e($edit['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Generic Name</label>
                                        <input type="text" name="generic_name" class="form-control" value="<?= e($edit['generic_name'] ?? '') ?>">
                                        <div class="form-text">Used for allergy matching.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select name="category_id" class="form-select">
                                            <option value="">-- none --</option>
                                            <?php foreach ($categories as $c): ?>
                                                <option value="<?= (int)$c['id'] ?>" <?= (int)($edit['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label">Unit</label>
                                            <input type="text" name="unit" class="form-control" value="<?= e($edit['unit'] ?? 'tablet') ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Low Stock Threshold</label>
                                            <input type="number" name="low_stock_threshold" class="form-control" min="0" value="<?= (int)($edit['low_stock_threshold'] ?? 10) ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="2"><?= e($edit['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (int)($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                    <button class="btn btn-primary" <?= $edit ? 'data-swal-save="medicine"' : 'data-swal-add="medicine"' ?>><i class="bi bi-save me-1"></i> <?= $edit ? 'Update' : 'Save' ?></button>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
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
                                    <input type="search" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="Brand or generic name" aria-label="Search medicine catalog">
                                </div>
                                <div class="col d-flex flex-wrap gap-2 justify-content-start">
                                    <button class="btn btn-primary text-nowrap"><i class="bi bi-search me-1"></i> Search</button>
                                    <button type="button" class="btn btn-sm btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#medicineModal"><i class="bi bi-plus-lg me-1"></i> Add Medicine</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table id="medicinesTable" class="table table-hover mb-0 align-middle">
                            <thead><tr><th>#</th><th>Name</th><th>Generic</th><th>Category</th><th>Unit</th><th class="text-end">Stock</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php if (!$medicines): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No medicines found.</td></tr>
                            <?php else: foreach ($medicines as $m): ?>
                                <tr>
                                    <td><?= (int)$m['id'] ?></td>
                                    <td><?= e($m['name']) ?></td>
                                    <td><?= e($m['generic_name']) ?></td>
                                    <td><?= e($m['category_name'] ?: '—') ?></td>
                                    <td><?= e($m['unit']) ?></td>
                                    <td class="text-end">
                                        <?php $st = (int)$m['stock']; ?>
                                        <span class="badge <?= $st <= (int)$m['low_stock_threshold'] ? 'bg-danger' : 'bg-success' ?>"><?= $st ?></span>
                                    </td>
                                    <td><?= (int)$m['is_active'] === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('admin/medicines.php?action=edit&id=' . $m['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= e(url('admin/medicines.php?action=delete&id=' . $m['id'])) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this medicine and all its batches?" data-swal-delete="medicine"><i class="bi bi-trash"></i></a>
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
<?php
/**
 * Admin — Suppliers.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'Suppliers';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid     = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $person  = trim($_POST['contact_person'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') $errors[] = 'Supplier name is required.';

    if (!$errors) {
        if ($sid > 0) {
            $pdo->prepare('UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?')
                ->execute([$name, $person, $phone, $email, $address, $sid]);
            log_action($pdo, (int)current_user()['id'], 'update_supplier', 'suppliers', $sid, "Updated supplier $name");
            set_flash('success', 'Supplier updated.');
        } else {
            $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?,?,?,?,?)')
                ->execute([$name, $person, $phone, $email, $address]);
            log_action($pdo, (int)current_user()['id'], 'create_supplier', 'suppliers', (int)$pdo->lastInsertId(), "Created supplier $name");
            set_flash('success', 'Supplier created.');
        }
        redirect('admin/suppliers.php');
    }
}

if (($_GET['action'] ?? '') === 'delete' && ($sid = (int)($_GET['id'] ?? 0)) > 0) {
    $pdo->prepare('DELETE FROM suppliers WHERE id=?')->execute([$sid]);
    log_action($pdo, (int)current_user()['id'], 'delete_supplier', 'suppliers', $sid, "Deleted supplier #$sid");
    set_flash('success', 'Supplier deleted.');
    redirect('admin/suppliers.php');
}

$edit = null;
if (($_GET['action'] ?? '') === 'edit' && ($sid = (int)($_GET['id'] ?? 0)) > 0) {
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id=? LIMIT 1');
    $stmt->execute([$sid]);
    $edit = $stmt->fetch();
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM suppliers';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ? OR address LIKE ?';
    $params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
}
$sql .= ' ORDER BY name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <div class="row g-3">
                <div class="col-12">
                    <div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-md"><div class="modal-content">
                        <div class="card">
                            <div class="card-header" id="supplierModalLabel"><?= $edit ? 'Edit Supplier' : 'Add Supplier' ?></div>
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
                                        <label class="form-label">Contact Person</label>
                                        <input type="text" name="contact_person" class="form-control" value="<?= e($edit['contact_person'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" name="phone" class="form-control" value="<?= e($edit['phone'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= e($edit['email'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control" rows="2"><?= e($edit['address'] ?? '') ?></textarea>
                                    </div>
                                    <button class="btn btn-primary" <?= $edit ? 'data-swal-save="supplier"' : 'data-swal-add="supplier"' ?>><i class="bi bi-save me-1"></i> <?= $edit ? 'Update' : 'Save' ?></button>
                                    <?php if ($edit): ?><a href="<?= e(url('admin/suppliers.php')) ?>" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                                </form>
                            </div>
                        </div>
                        </div></div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="get" class="row g-2 align-items-end audit-toolbar">
                                <div class="col">
                                    <label class="form-label">Search</label>
                                    <input type="search" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="Name, contact, phone or email" aria-label="Search suppliers">
                                </div>
                                <div class="col d-flex flex-wrap gap-2 justify-content-start">
                                    <button class="btn btn-primary text-nowrap"><i class="bi bi-search me-1"></i> Search</button>
                                    <button type="button" class="btn btn-sm btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#supplierModal"><i class="bi bi-plus-lg me-1"></i> Add Supplier</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php if (!$suppliers): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No suppliers yet.</td></tr>
                            <?php else: foreach ($suppliers as $s): ?>
                                <tr>
                                    <td><?= (int)$s['id'] ?></td>
                                    <td><?= e($s['name']) ?></td>
                                    <td><?= e($s['contact_person']) ?></td>
                                    <td><?= e($s['phone']) ?></td>
                                    <td><?= e($s['email']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('admin/suppliers.php?action=edit&id=' . $s['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= e(url('admin/suppliers.php?action=delete&id=' . $s['id'])) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this supplier?" data-swal-delete="supplier"><i class="bi bi-trash"></i></a>
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
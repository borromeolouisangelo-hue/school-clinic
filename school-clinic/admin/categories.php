<?php
/**
 * Admin — Medicine categories.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'Medicine Categories';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $type = in_array($_POST['type'] ?? 'medicine', ['medicine', 'first_aid'], true) ? $_POST['type'] : 'medicine';
    $cid  = (int)($_POST['id'] ?? 0);

    if ($name === '') $errors[] = 'Category name is required.';
    if (!$errors) {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM medicine_categories WHERE name = ? AND type = ? AND id <> ?');
        $dup->execute([$name, $type, $cid]);
        if ($dup->fetchColumn() > 0) $errors[] = 'Category name already exists in this type.';
    }

    if (!$errors) {
        if ($cid > 0) {
            $pdo->prepare('UPDATE medicine_categories SET name=?, description=?, type=? WHERE id=?')
                ->execute([$name, $desc, $type, $cid]);
            log_action($pdo, (int)current_user()['id'], 'update_category', 'medicine_categories', $cid, "Updated category $name");
            set_flash('success', 'Category updated.');
        } else {
            $pdo->prepare('INSERT INTO medicine_categories (name, description, type) VALUES (?,?,?)')
                ->execute([$name, $desc, $type]);
            $cid = (int)$pdo->lastInsertId();
            log_action($pdo, (int)current_user()['id'], 'create_category', 'medicine_categories', $cid, "Created category $name");
            fire_event('category.created', ['category_id' => $cid]);
            set_flash('success', 'Category created.');
        }
        redirect('admin/categories.php');
    }
}

if (($_GET['action'] ?? '') === 'delete' && ($cid = (int)($_GET['id'] ?? 0)) > 0) {
    $pdo->prepare('DELETE FROM medicine_categories WHERE id=?')->execute([$cid]);
    log_action($pdo, (int)current_user()['id'], 'delete_category', 'medicine_categories', $cid, "Deleted category #$cid");
    set_flash('success', 'Category deleted.');
    redirect('admin/categories.php');
}

$edit = null;
if (($_GET['action'] ?? '') === 'edit' && ($cid = (int)($_GET['id'] ?? 0)) > 0) {
    $stmt = $pdo->prepare('SELECT * FROM medicine_categories WHERE id=? LIMIT 1');
    $stmt->execute([$cid]);
    $edit = $stmt->fetch();
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT c.*, (SELECT COUNT(*) FROM medicines m WHERE m.category_id = c.id) AS med_count
        FROM medicine_categories c';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE c.name LIKE ? OR c.description LIKE ? OR c.type LIKE ?';
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= ' ORDER BY c.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cats = $stmt->fetchAll();

$categoryOptions = $pdo->query(
    'SELECT * FROM medicine_categories ORDER BY name'
)->fetchAll();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <div class="row g-3">
                <div class="col-12">
                    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-md"><div class="modal-content">
                        <div class="card">
                            <div class="card-header" id="categoryModalLabel"><?= $edit ? 'Edit Category' : 'Add Category' ?></div>
                            <div class="card-body">
                                <?php foreach ($errors as $err): ?>
                                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                                <?php endforeach; ?>
                                <form method="post">
                                    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Type</label>
                                        <select name="type" class="form-select">
                                            <option value="medicine" <?= (($edit['type'] ?? 'medicine') === 'medicine') ? 'selected' : '' ?>>Medicine</option>
                                            <option value="first_aid" <?= (($edit['type'] ?? 'medicine') === 'first_aid') ? 'selected' : '' ?>>First Aid / Wound Care</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Name *</label>
                                        <input type="text" name="name" class="form-control" value="<?= e($edit['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="2"><?= e($edit['description'] ?? '') ?></textarea>
                                    </div>
                                    <button class="btn btn-primary" <?= $edit ? 'data-swal-save="category"' : 'data-swal-add="category"' ?>><i class="bi bi-save me-1"></i> <?= $edit ? 'Update' : 'Save' ?></button>
                                    <?php if ($edit): ?><a href="<?= e(url('admin/categories.php')) ?>" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
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
                                    <input type="search" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="Name, type or description" aria-label="Search categories">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label">Filter</label>
                                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">All Types</option>
                                        <option value="medicine" <?= (($_GET['type'] ?? '') === 'medicine') ? 'selected' : '' ?>>Medicine</option>
                                        <option value="first_aid" <?= (($_GET['type'] ?? '') === 'first_aid') ? 'selected' : '' ?>>First Aid / Wound Care</option>
                                    </select>
                                </div>
                                <div class="col d-flex flex-wrap gap-2 justify-content-start">
                                    <button class="btn btn-primary text-nowrap"><i class="bi bi-search me-1"></i> Search</button>
                                    <a href="<?= e(url('admin/categories.php')) ?>" class="btn btn-sm btn-outline-secondary text-nowrap">Reset</a>
                                    <button type="button" class="btn btn-sm btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="bi bi-plus-lg me-1"></i> Add Category</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead><tr><th>#</th><th>Type</th><th>Name</th><th>Description</th><th class="text-end">Items</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php if (!$cats): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No categories yet.</td></tr>
                            <?php else: foreach ($cats as $c): ?>
                                <tr>
                                    <td><?= (int)$c['id'] ?></td>
                                    <td><span class="badge bg-secondary"><?= $c['type'] === 'first_aid' ? 'First Aid / Wound Care' : 'Medicine' ?></span></td>
                                    <td><?= e($c['name']) ?></td>
                                    <td><?= e($c['description']) ?></td>
                                    <td class="text-end"><?= (int)$c['med_count'] ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('admin/categories.php?action=edit&id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= e(url('admin/categories.php?action=delete&id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this category?" data-swal-delete="category"><i class="bi bi-trash"></i></a>
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
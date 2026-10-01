<?php
/**
 * Admin — User account management.
 * List, create (staff/admin/user), edit, activate/deactivate.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$page_title = 'User Accounts';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// ---- Handle POST -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('admin/users.php');
    }
    $postAction = $_POST['form_action'] ?? '';
    $uid        = (int)($_POST['id'] ?? 0);

    if ($postAction === 'save') {
        $username     = trim($_POST['username'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $role         = in_array($_POST['role'] ?? '', ['user','staff','admin'], true) ? $_POST['role'] : 'user';
        $account_type = ($_POST['account_type'] ?? 'student') === 'employee' ? 'employee' : 'student';
        $status       = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $first        = trim($_POST['first_name'] ?? '');
        $last         = trim($_POST['last_name'] ?? '');
        $password     = $_POST['password'] ?? '';

        if ($username === '') $errors[] = 'Username is required.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if ($first === '') $errors[] = 'First name is required.';
        if ($last === '')  $errors[] = 'Last name is required.';
        if ($uid === 0 && strlen($password) < 6) $errors[] = 'Password (min 6 chars) is required for new users.';

        if (!$errors) {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id <> ?');
            $dup->execute([$username, $email, $uid]);
            if ($dup->fetchColumn() > 0) {
                $errors[] = 'Username or email already exists.';
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                if ($uid > 0) {
                    // Update user
                    $sql = 'UPDATE users SET username=?, email=?, role=?, account_type=?, status=?';
                    $params = [$username, $email, $role, $account_type, $status];
                    if ($password !== '') {
                        if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters.');
                        $sql .= ', password_hash=?';
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= ' WHERE id=?';
                    $params[] = $uid;
                    $pdo->prepare($sql)->execute($params);

                    // Profile upsert
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM profiles WHERE user_id=?');
                    $chk->execute([$uid]);
                    if ((int)$chk->fetchColumn() === 0) {
                        $pdo->prepare('INSERT INTO profiles (user_id, first_name, last_name) VALUES (?,?,?)')
                            ->execute([$uid, $first, $last]);
                    } else {
                        $pdo->prepare('UPDATE profiles SET first_name=?, last_name=? WHERE user_id=?')
                            ->execute([$first, $last, $uid]);
                    }

                    // Sync account_type with student/employee details presence.
                    sync_account_type($pdo, $uid, $account_type);

                    log_action($pdo, (int)current_user()['id'], 'update_user', 'users', $uid, "Updated user #$uid");
                    set_flash('success', 'User updated successfully.');
                } else {
                    // Create user
                    $pdo->prepare('INSERT INTO users (username, email, password_hash, role, account_type, status) VALUES (?,?,?,?,?,?)')
                        ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $account_type, $status]);
                    $newId = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO profiles (user_id, first_name, last_name) VALUES (?,?,?)')
                        ->execute([$newId, $first, $last]);

                    // Sync account_type with student/employee details presence.
                    sync_account_type($pdo, $newId, $account_type);

                    log_action($pdo, (int)current_user()['id'], 'create_user', 'users', $newId, "Created user $username ($role)");
                    fire_event('user.created', ['user_id' => $newId]);
                    set_flash('success', 'User created successfully.');
                }
                $pdo->commit();
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Save failed: ' . $ex->getMessage();
            }
        }

        if (!$errors) {
            redirect('admin/users.php');
        }
        // Fall through to form with errors
        $action = ($uid > 0) ? 'edit' : 'new';
        $id = $uid;
    }

    if ($postAction === 'toggle') {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid === (int)current_user()['id']) {
            set_flash('danger', 'You cannot deactivate your own account.');
        } else {
            $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$uid]);
            log_action($pdo, (int)current_user()['id'], 'toggle_user', 'users', $uid, 'Toggled account status');
            set_flash('success', 'Account status updated.');
        }
        redirect('admin/users.php');
    }
}

// ---- Delete ---------------------------------------------------------------
if ($action === 'delete' && $id > 0) {
    if ($id === (int)current_user()['id']) {
        set_flash('danger', 'You cannot delete your own account.');
    } else {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        log_action($pdo, (int)current_user()['id'], 'delete_user', 'users', $id, "Deleted user #$id");
        set_flash('success', 'User deleted.');
    }
    redirect('admin/users.php');
}

// ---- Load form data --------------------------------------------------------
$editUser = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT u.*, p.first_name, p.last_name FROM users u LEFT JOIN profiles p ON p.user_id=u.id WHERE u.id=? LIMIT 1');
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
}

// ---- List data -------------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$where = []; $params = [];
if ($search !== '') {
    $where[] = '(u.username LIKE ? OR u.email LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
if (in_array($roleFilter, ['user','staff','admin'], true)) {
    $where[] = 'u.role = ?';
    $params[] = $roleFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$sql = "SELECT u.*, p.first_name, p.last_name FROM users u LEFT JOIN profiles p ON p.user_id=u.id $whereSql ORDER BY u.role, u.username";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <?php if ($action === 'edit'): ?>
                <div class="mb-3">
                    <a href="<?= e(url('admin/users.php')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to list</a>
                </div>

                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>

                <div class="card">
                    <div class="card-header"><?= $action === 'edit' ? 'Edit User' : 'Create User' ?></div>
                    <div class="card-body">
                        <form method="post" data-confirm="Save this user?">
                            <input type="hidden" name="form_action" value="save">
                            <input type="hidden" name="id" value="<?= (int)($editUser['id'] ?? 0) ?>">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control" value="<?= e($editUser['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" value="<?= e($editUser['last_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" name="username" class="form-control" value="<?= e($editUser['username'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" value="<?= e($editUser['email'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Role *</label>
                                    <select name="role" class="form-select">
                                        <?php foreach (['user' => 'User', 'staff' => 'Clinic Staff', 'admin' => 'Administrator'] as $val => $lbl): ?>
                                            <option value="<?= $val ?>" <?= ($editUser['role'] ?? 'user') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Account Type</label>
                                    <select name="account_type" class="form-select">
                                        <option value="student" <?= ($editUser['account_type'] ?? 'student') === 'student' ? 'selected' : '' ?>>Student</option>
                                        <option value="employee" <?= ($editUser['account_type'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?= ($editUser['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= ($editUser['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Password <?= $action === 'edit' ? '(leave blank to keep)' : '*' ?></label>
                                    <input type="password" name="password" class="form-control" <?= $action === 'new' ? 'required' : '' ?>>
                                </div>
                            </div>
                            <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save</button>
                                <a href="<?= e(url('admin/users.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: ?>

            <div class="row">
                <div class="col-12">
                    <div class="card mb-3 mt-3">
                        <div class="card-body">
                            <form method="get" class="row g-2 align-items-end audit-toolbar">
                            <div class="col">
                                <label class="form-label">Search</label>
                                <input type="search" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="Name, username or email" aria-label="Search users">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">Filter</label>
                                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">All Roles</option>
                                    <option value="user" <?= (($_GET['role'] ?? '') === 'user') ? 'selected' : '' ?>>User</option>
                                    <option value="staff" <?= (($_GET['role'] ?? '') === 'staff') ? 'selected' : '' ?>>Clinic Staff</option>
                                    <option value="admin" <?= (($_GET['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrator</option>
                                </select>
                            </div>
                            <div class="col d-flex flex-wrap gap-2 justify-content-start">
                                <button class="btn btn-primary text-nowrap"><i class="bi bi-search me-1"></i> Search</button>

                                <button type="button" class="btn btn-sm btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#newUserModal"><i class="bi bi-plus-lg me-1"></i> New User</button>
                            </div>
                        </form> 
                    </div>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr><th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Type</th><th>Status</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                            <?php if (!$users): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr>
                            <?php else: foreach ($users as $usr): ?>
                                <tr>
                                    <td><?= (int)$usr['id'] ?></td>
                                    <td><?= e(trim(($usr['first_name'] ?? '') . ' ' . ($usr['last_name'] ?? '')) ?: '—') ?></td>
                                    <td><?= e($usr['username']) ?></td>
                                    <td><?= e($usr['email']) ?></td>
                                    <td><span class="chip"><?= e(role_label($usr['role'])) ?></span></td>
                                    <td><?= ucfirst($usr['account_type']) ?></td>
                                    <td>
                                        <?php if ($usr['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= e(url('admin/users.php?action=edit&id=' . $usr['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="form_action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                                            <button class="btn btn-sm btn-outline-secondary" title="Toggle status"><i class="bi bi-power"></i></button>
                                        </form>
                                        <a href="<?= e(url('admin/users.php?action=delete&id=' . $usr['id'])) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this user? This cannot be undone." data-swal-delete="user"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal fade" id="newUserModal" tabindex="-1" aria-labelledby="newUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title" id="newUserModalLabel"><i class="bi bi-person-plus me-1"></i> Create User</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <form method="post" data-confirm="Save this user?">
                            <input type="hidden" name="form_action" value="save">
                            <input type="hidden" name="id" value="0">
                            <div class="modal-body">
                                <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                                <div class="row g-2">
                                    <div class="col-md-6"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?= e($_POST['first_name'] ?? '') ?>" required></div>
                                    <div class="col-md-6"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?= e($_POST['last_name'] ?? '') ?>" required></div>
                                    <div class="col-md-6"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" required></div>
                                    <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required></div>
                                    <div class="col-md-4"><label class="form-label">Role *</label><select name="role" class="form-select"><option value="user" <?= ($_POST['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>User</option><option value="staff" <?= ($_POST['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Clinic Staff</option><option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option></select></div>
                                    <div class="col-md-4"><label class="form-label">Account Type</label><select name="account_type" class="form-select"><option value="student" <?= ($_POST['account_type'] ?? 'student') === 'student' ? 'selected' : '' ?>>Student</option><option value="employee" <?= ($_POST['account_type'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option></select></div>
                                    <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                                    <div class="col-12"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" minlength="6" required></div>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-swal-save="user"><i class="bi bi-save me-1"></i>Save User</button></div>
                        </form>
                    </div></div>
                </div>
                <?php if ($action === 'new' || $errors): ?><script>document.addEventListener('DOMContentLoaded', function () { var modal = document.getElementById('newUserModal'); if (modal && window.bootstrap) new bootstrap.Modal(modal).show(); });</script><?php endif; ?>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var modal = document.getElementById('newUserModal');
                    if (!modal) return;
                    modal.addEventListener('hidden.bs.modal', function () {
                        var form = modal.querySelector('form');
                        if (form) form.reset();
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <!-- /.content-area -->
    </main>
    <!-- /.main-content -->
</div>
<!-- /.app-wrapper -->

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
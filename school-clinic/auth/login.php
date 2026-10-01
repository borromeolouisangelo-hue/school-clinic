<?php
/**
 * Login page (all roles).
 */
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect(dashboard_for(current_role()));
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($username === '' || $password === '') {
        $errors[] = 'Please enter your username and password.';
    } elseif (is_login_blocked($pdo, $username, $ipAddress)) {
        $errors[] = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
    } else {
        $stmt = $pdo->prepare('SELECT u.*, p.first_name, p.last_name
                               FROM users u
                               LEFT JOIN profiles p ON p.user_id = u.id
                               WHERE u.username = ? OR u.email = ? LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            record_failed_login($pdo, $username, $ipAddress);
            $errors[] = 'Invalid username or password.';
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'Your account is inactive. Please contact the administrator.';
        } else {
            clear_failed_logins($pdo, $user['username']);
            regenerate_session();

            $_SESSION['user_id']      = (int)$user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['account_type'] = $user['account_type'];
            $_SESSION['full_name']    = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

            log_action($pdo, (int)$user['id'], 'login', 'users', (int)$user['id'], 'User logged in');
            set_flash('success', 'Welcome back, ' . ($_SESSION['full_name'] ?: $user['username']) . '!');
            redirect(dashboard_for($user['role']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login &middot; <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="<?= e(url('assets/images/ncst.png')) ?>" alt="NCST logo" class="auth-logo-image">
            <h4 class="mb-0"><?= e(get_setting($pdo, 'school_name', APP_NAME)) ?></h4>
            <small><?= e(get_setting($pdo, 'clinic_name', 'School Health Clinic')) ?></small>
        </div>

        <div class="auth-card-body">
            <?php if ($errors): ?>
                <div class="auth-alerts">
                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger py-2 mb-2"><?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="username">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" id="username" class="form-control"
                               value="<?= e($username) ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>

            <div class="auth-divider">or</div>
            <a href="<?= e(url('auth/register.php')) ?>" class="btn btn-outline-primary w-100">
                <i class="bi bi-person-plus me-1"></i> Create an Account
            </a>

            <div class="auth-meta">
                
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
/**
 * Logout — destroys the session.
 */
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    log_action($pdo, (int)($_SESSION['user_id'] ?? 0), 'logout', 'users', (int)($_SESSION['user_id'] ?? 0), 'User logged out');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

session_start();
set_flash('success', 'You have been logged out.');
redirect('auth/login.php');
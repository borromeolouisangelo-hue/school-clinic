<?php
/**
 * Authentication helper functions for the School Clinic IMS.
 * Legacy compatibility — used by auth/login.php, auth/register.php, auth/logout.php
 */

if (!function_exists('password_verify_legacy')) {
    // PASSWORD_VERIFY is native PHP 5.5+, but we keep a wrapper for compatibility
    function password_verify_legacy($password, $hash) {
        return password_verify($password, $hash);
    }
}

if (!function_exists('log_action')) {
    function log_action($pdo, $user_id, $action, $table, $record_id, $description) {
        try {
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $action, $table, $record_id, $description]);
        } catch (PDOException $e) {
            // Silently fail — audit logging is non-critical
        }
    }
}

if (!function_exists('record_failed_login')) {
    function record_failed_login($pdo, $username, $ip_address) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO failed_logins (username, ip_address, attempt_time) VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, last_attempt = NOW()
            ");
            $stmt->execute([$username, $ip_address]);
        } catch (PDOException $e) {
            // Silently fail
        }
    }
}

if (!function_exists('clear_failed_logins')) {
    function clear_failed_logins($pdo, $username) {
        try {
            $pdo->prepare("DELETE FROM failed_logins WHERE username = ?")->execute([$username]);
        } catch (PDOException $e) {
            // Silently fail
        }
    }
}

if (!function_exists('regenerate_session')) {
    function regenerate_session() {
        if (isset($_SESSION['user_id'])) {
            $old_id = session_id();
            session_regenerate_id(true);
            // Clear old session data but keep user_id
            $_SESSION = ['user_id' => $_SESSION['user_id'], 'username' => $_SESSION['username'], 'role' => $_SESSION['role'], 'account_type' => $_SESSION['account_type'], 'full_name' => $_SESSION['full_name']];
        }
    }
}

// Check if login is blocked due to too many failed attempts
if (!function_exists('is_login_blocked')) {
    function is_login_blocked($pdo, $username, $ip_address) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM failed_logins
                WHERE username = ? AND ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$username, $ip_address]);
            $row = $stmt->fetch();
            return $row['count'] >= 5;
        } catch (PDOException $e) {
            return false;
        }
    }
}
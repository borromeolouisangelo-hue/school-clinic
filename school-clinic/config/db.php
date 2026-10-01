<?php
/**
 * Database connection (PDO) + global bootstrap.
 *
 * Every setting below reads the environment first and falls back to the local
 * XAMPP defaults, so this one file runs unchanged on XAMPP and on Railway.
 * A developer running XAMPP sees zero behavior change.
 */

// ---- Database credentials ----
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'school_clinic');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// ---- App constants ----
define('APP_NAME', getenv('APP_NAME') ?: 'School Clinic IMS');
// Railway serves from the domain root, so it passes BASE_URL='/'; locally it
// stays '/school-clinic'. url() rtrims the trailing slash, so '/' becomes ''.
define('BASE_URL', rtrim(getenv('BASE_URL') !== false ? getenv('BASE_URL') : '/school-clinic', '/'));

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- PDO connection ----
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()) .
        '<br>Make sure MySQL is running in XAMPP and the <code>school_clinic</code> database is imported.');
}

// ---- Load legacy helpers ----
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/EventDispatcher.php';
require_once __DIR__ . '/../includes/NotificationModel.php';
require_once __DIR__ . '/../includes/NotificationRouter.php';

// Register notification event listeners
register_notification_listeners($pdo);

// ---- Autoload legacy classes (compatibility) ----
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/includes/' . str_replace('\\', '/', $class_name) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
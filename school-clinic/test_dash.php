<?php
// Test script to simulate admin/dashboard.php loading
// This replicates the exact include order from dashboard.php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "=== Admin Dashboard Execution Trace ===\n\n";

// Simulate: session already started from login
// (db.php starts session if session_status() === PHP_SESSION_NONE)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Step 1: session_start() status=" . session_status() . "\n";

// Step: require_once __DIR__ . '/../config/db.php'
echo "Step 2: Including config/db.php...\n";
require_once __DIR__ . '/config/db.php';
echo "Step 2: config/db.php loaded. PDO=" . ($pdo ? "yes" : "no") . "\n";
echo "Step 2: BASE_URL=" . BASE_URL . "\n";

// Step: require_once __DIR__ . '/includes/functions.php'
// (db.php already loads this, but let's verify)
echo "Step 3: Including includes/functions.php...\n";
require_once __DIR__ . '/includes/functions.php';
echo "Step 3: functions.php loaded. require_login exists: " . (function_exists('require_login') ? "YES" : "NO") . "\n";

// Step: require_once __DIR__ . '/includes/auth.php'
echo "Step 4: Including includes/auth.php...\n";
require_once __DIR__ . '/includes/auth.php';
echo "Step 4: auth.php loaded. is_login_blocked exists: " . (function_exists('is_login_blocked') ? "YES" : "NO") . "\n";

// Step: require_login(['admin'])
echo "Step 5: require_login(['admin'])...\n";
try {
    require_login(['admin']);
    echo "Step 5: require_login completed OK\n";
} catch (Exception $e) {
    echo "Step 5: require_login FAILED - " . $e->getMessage() . "\n";
}

// Step 6: Check user session state
echo "Step 6: is_logged_in=" . (is_logged_in() ? "true" : "false") . "\n";
echo "Step 6: current_role=" . current_role() . "\n";

// Step 7: Execute dashboard SQL queries
echo "Step 7: Executing dashboard SQL queries...\n";
global $pdo;
try {
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
    echo "Step 7: Users=" . $totalUsers . ", Staff=" . $totalStaff . "\n";
} catch (Exception $e) {
    echo "Step 7: SQL ERROR - " . $e->getMessage() . "\n";
}

// Step 8: Include header.php
echo "Step 8: Including includes/header.php...\n";
require_once __DIR__ . '/includes/header.php';
echo "Step 8: header.php loaded\n";

// Step 9: Output would continue with dashboard HTML
echo "Step 9: Dashboard execution would continue HTML output\n";
echo "=== Execution Trace Complete ===\n";
?>
<?php
include "config/db.php";
require_once "includes/functions.php";
require_once "includes/auth.php";

echo "Testing is_login_blocked function call:\n";
// Test with no session - should return false (no failed attempts)
$result = is_login_blocked($pdo, 'admin', '127.0.0.1');
echo "is_login_blocked(admin, 127.0.0.1): " . ($result ? "true" : "false") . "\n";

echo "\nAll function calls successful!\n";
?>
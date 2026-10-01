<?php
/**
 * Compatibility redirect for the old sick leave URL.
 * The sick leave form is now a modal on the dashboard.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

redirect('user/dashboard.php');

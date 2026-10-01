<?php
/**
 * Entry point / role router for School Clinic IMS.
 * Routes authenticated users to role-specific dashboards,
 * unauthenticated users to the login page.
 */

require_once __DIR__ . '../config/db.php';

if (is_logged_in()) {
    redirect(dashboard_for(current_role()));
}

// Uncomment and set up routing for old-style pages if needed:
// redirect('auth/login.php');
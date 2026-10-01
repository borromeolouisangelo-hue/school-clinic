<?php
/**
 * Entry point / role router for School Clinic IMS.
 * Routes authenticated users to role-specific dashboards,
 * unauthenticated users to the login page.
 */

require_once __DIR__ . '/config/db.php';

if (is_logged_in()) {
    redirect(dashboard_for(current_role()));
}

// Unauthenticated users go to the login page (as documented above).
redirect('auth/login.php');
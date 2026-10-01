<?php
/**
 * Sidebar navigation component.
 * Provides role-specific navigation for the School Clinic IMS.
 * Requires that config/db.php has been included (starts session, creates $pdo).
 */
$role = current_role();
$isEmployee = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee';
?>
<aside class="sidebar" id="appSidebar" aria-label="Main navigation">
    <div class="brand">
        <img src="<?= e(url('assets/images/ncst.png')) ?>" alt="NCST logo" class="brand-image">
        <span class="brand-text"><?= e(APP_NAME) ?></span>
    </div>

    <nav class="sidebar-nav flex-grow-1 overflow-auto">
        <?php if ($role === 'admin'): ?>
            <!-- Admin Navigation -->
            <div class="nav-section">Main</div>
            <a href="<?= e(url('admin/dashboard.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <div class="nav-section">Management</div>
            <a href="<?= e(url('admin/users.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Users
            </a>
            <a href="<?= e(url('admin/medicines.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'medicines.php' ? 'active' : '' ?>">
                <i class="bi bi-capsule"></i> Medicines
            </a>
            <a href="<?= e(url('admin/categories.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i> Categories
            </a>
            <a href="<?= e(url('admin/suppliers.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'suppliers.php' ? 'active' : '' ?>">
                <i class="bi bi-truck"></i> Suppliers
            </a>
            <a href="<?= e(url('admin/equipment.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'equipment.php' ? 'active' : '' ?>">
                <i class="bi bi-tools"></i> Equipment
            </a>

            <div class="nav-section">System</div>
            <a href="<?= e(url('admin/reports.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i> Reports
            </a>
            <a href="<?= e(url('admin/audit_logs.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'audit_logs.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i> Audit Logs
            </a>
            <a href="<?= e(url('admin/backup.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'backup.php' ? 'active' : '' ?>">
                <i class="bi bi-download"></i> Backup
            </a>
            <a href="<?= e(url('admin/settings.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Settings
            </a>

        <?php elseif ($role === 'staff'): ?>
            <!-- Staff Navigation -->
            <div class="nav-section">Main</div>
            <a href="<?= e(url('staff/dashboard.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <div class="nav-section">Clinical</div>
            <a href="<?= e(url('staff/patients.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'patients.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Patients
            </a>
            <a href="<?= e(url('staff/patient_register.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'patient_register.php' ? 'active' : '' ?>">
                <i class="bi bi-person-plus"></i> Register Patient
            </a>
            <a href="<?= e(url('staff/consultations.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'consultations.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-pulse"></i> Consultations
            </a>
            <a href="<?= e(url('staff/certificates.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'certificates.php' ? 'active' : '' ?>">
                <i class="bi bi-file-medical"></i> Certificates
            </a>
            <a href="<?= e(url('staff/sick_leaves.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sick_leaves.php' ? 'active' : '' ?>">
                <i class="bi bi-thermometer"></i> Sick Leaves
            </a>

            <div class="nav-section">Inventory</div>
            <a href="<?= e(url('staff/dispense.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dispense.php' ? 'active' : '' ?>">
                <i class="bi bi-capsule"></i> Dispense
            </a>
            <a href="<?= e(url('staff/inventory.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> Inventory
            </a>
            <a href="<?= e(url('staff/medicine_requests.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'medicine_requests.php' ? 'active' : '' ?>">
                <i class="bi bi-activity"></i> Medicine Requests
            </a>
            <a href="<?= e(url('staff/equipment_loans.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'equipment_loans.php' ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i> Equipment Loans
            </a>
            <a href="<?= e(url('staff/stock_adjust.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'stock_adjust.php' ? 'active' : '' ?>">
                <i class="bi bi-arrow-up-down"></i> Stock Adjust
            </a>

            <div class="nav-section">Records</div>
            <a href="<?= e(url('staff/history_logs.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'history_logs.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> History Logs
            </a>
            <a href="<?= e(url('staff/deduction_logs.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'deduction_logs.php' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> Deduction Logs
            </a>
            <a href="<?= e(url('staff/reports.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i> Reports
            </a>
            <a href="<?= e(url('staff/settings.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Settings
            </a>

        <?php else: ?>
            <!-- User/Student/Employee Navigation -->
            <div class="nav-section">Main</div>
            <a href="<?= e(url('user/dashboard.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <div class="nav-section">Health</div>
            <a href="<?= e(url('user/medical_form.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'medical_form.php' ? 'active' : '' ?>">
                <i class="bi bi-file-medical"></i> Medical Form
            </a>
            <a href="<?= e(url('user/my_consultations.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'my_consultations.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-pulse"></i> My Consultations
            </a>
            <a href="<?= e(url('user/my_certificates.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'my_certificates.php' ? 'active' : '' ?>">
                <i class="bi bi-file-medical"></i> My Certificates
            </a>

            <div class="nav-section">Activity</div>
            <a href="<?= e(url('user/my_requests.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'my_requests.php' ? 'active' : '' ?>">
                <i class="bi bi-activity"></i> My Activity
            </a>
            <a href="<?= e(url('user/my_loans.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'my_loans.php' ? 'active' : '' ?>">
                <i class="bi bi-tools"></i> My Loans
            </a>
            <?php if ($isEmployee): ?>
            <a href="<?= e(url('user/sick_leave.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sick_leave.php' ? 'active' : '' ?>">
                <i class="bi bi-thermometer"></i> Sick Leave
            </a>
            <?php endif; ?>

            <div class="nav-section">Resources</div>
            <a href="<?= e(url('user/profile.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person"></i> Profile
            </a>
            <a href="<?= e(url('user/settings.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Settings
            </a>
            <a href="<?= e(url('notifications.php')) ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell"></i> Notifications
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <small>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></small>
    </div>

    <!-- Sidebar collapse control (» expands a collapsed rail, < collapses it) -->
    <button class="sidebar-brand-control" id="sidebarBrandControl" type="button" aria-label="Expand navigation" title="Expand navigation">
        <i class="bi bi-chevron-double-right bc-icon-expand"></i><i class="bi bi-chevron-double-left bc-icon-collapse"></i>
    </button>
</aside>

<!-- Sidebar backdrop for mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
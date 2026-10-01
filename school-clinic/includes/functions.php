<?php
/**
 * General helper functions for the School Clinic IMS.
 * Legacy compatibility layer — used by includes/ and old admin/staff/user pages.
 */

if (!function_exists('e')) {
    function e($string = '') {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url($path = '') {
        // Use BASE_URL constant from config/db.php, fall back to HTTP_BASE
        $base = defined('BASE_URL') ? BASE_URL : (($_SERVER['HTTP_BASE'] ?? '') . dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = rtrim($base, '/');
        $path = ltrim($path, '/');
        if ($path === '') return $base;
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('format_address')) {
    function format_address($parts) {
        $address = trim(implode(' ', $parts));
        return $address;
    }
}

if (!function_exists('get_setting')) {
    function get_setting($pdo, $key, $default = '') {
        // Try to retrieve from system_settings table
        // Columns: setting_key, setting_value (not key/value)
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }
}

if (!function_exists('redirect')) {
    function redirect($url, $permanent = true) {
        // If URL is already absolute (starts with / or http), use as-is
        // Otherwise, prepend BASE_URL
        if (strpos($url, '/') === 0 || strpos($url, 'http') === 0) {
            header('Location: ' . $url, true, $permanent ? 302 : 301);
        } else {
            header('Location: ' . url($url), true, $permanent ? 302 : 301);
        }
        exit;
    }
}

if (!function_exists('e')) {
    function e($string = '') {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('current_role')) {
    function current_role() {
        return $_SESSION['role'] ?? 'user';
    }
}

// Role label helper — returns human-readable label for a role
if (!function_exists('role_label')) {
    function role_label($role) {
        $labels = [
            'admin' => 'Administrator',
            'staff' => 'Clinic Staff',
            'user' => 'User',
        ];
        return $labels[$role] ?? ucfirst($role);
    }
}

// Short, uppercase badge label for the current user (topbar profile dropdown).
// Resolves student/employee through the existing role + account_type system.
if (!function_exists('role_badge_label')) {
    function role_badge_label() {
        $role = current_role();
        if ($role === 'admin') {
            return 'ADMIN';
        }
        if ($role === 'staff') {
            return 'STAFF';
        }
        $accountType = $_SESSION['account_type'] ?? '';
        if (in_array($accountType, ['student', 'employee'], true)) {
            return strtoupper($accountType);
        }
        return strtoupper($role ?: 'user');
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('dashboard_for')) {
    function dashboard_for($role) {
        $allowed = ['admin', 'staff', 'user'];
        if (!in_array($role, $allowed, true)) {
            $role = 'user';
        }
        return "{$role}/dashboard.php";
    }
}

// Auth required check — redirects to login if not logged in (optionally with role check)
if (!function_exists('require_login')) {
    function require_login($allowed_roles = null) {
        if (!is_logged_in()) {
            redirect('auth/login.php');
        }
        if ($allowed_roles !== null && !in_array(current_role(), $allowed_roles, true)) {
            redirect(dashboard_for(current_role()));
        }
    }
}

// Get current user session data
if (!function_exists('current_user')) {
    function current_user() {
        $userId = is_logged_in() ? (int)$_SESSION['user_id'] : 0;
        // Build minimal user array from session
        $user = [
            'id' => $userId,
            'username' => $_SESSION['username'] ?? '',
            'role' => $_SESSION['role'] ?? 'user',
            'account_type' => $_SESSION['account_type'] ?? 'student',
            'full_name' => $_SESSION['full_name'] ?? '',
        ];
        return $user;
    }
}

// Format datetime — used throughout views for consistent date formatting
if (!function_exists('fmt_dt')) {
    function fmt_dt($datetime, $format = 'Y-m-d H:i:s') {
        if (!$datetime) return '';
        $ts = strtotime($datetime);
        if ($ts === false) return $datetime;
        // Default: human-readable format
        $defaultFormats = [
            'Y-m-d H:i:s' => 'Y-m-d H:i:s',
            'M d, Y g:i A' => 'M d, Y g:i A',
            'F d, Y' => 'F d, Y',
            'M d, Y' => 'M d, Y',
        ];
        $fmt = $defaultFormats[$format] ?? $format;
        return date($fmt, $ts);
    }
}

// Flash message helper — session-based one-time messages
if (!function_exists('set_flash')) {
    function set_flash($key, $value = null) {
        if ($value !== null) {
            $_SESSION['flash'][$key] = $value;
        } else {
            // Set flash with implicit value (for boolean flags)
            $_SESSION['flash'][$key] = true;
        }
    }
}

if (!function_exists('get_flash')) {
    function get_flash($key, $default = '') {
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]); // One-time use only
        return $value !== null ? $value : $default;
    }
}

// Create overdue loan notifications — check for overdue equipment loans and notify users
if (!function_exists('create_overdue_loan_notifications')) {
    function create_overdue_loan_notifications($pdo) {
        try {
            // Find overdue loans: status = 'borrowed' and due_date < today
            $stmt = $pdo->prepare("
                SELECT el.id, el.user_id, el.equipment_id, el.due_date, e.name as equipment_name
                FROM equipment_loans el
                JOIN equipment e ON e.id = el.equipment_id
                WHERE el.status = 'borrowed'
                  AND el.due_date IS NOT NULL
                  AND el.due_date < CURDATE()
            ");
            $stmt->execute();
            $overdueLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($overdueLoans as $loan) {
                // Check if notification already exists for this overdue loan
                $checkStmt = $pdo->prepare("
                    SELECT id FROM notifications 
                    WHERE user_id = ? AND loan_id = ? AND type = 'overdue_loan'
                ");
                $checkStmt->execute([$loan['user_id'], $loan['id']]);
                $existing = $checkStmt->fetch();

                if (!$existing) {
                    $title = 'Overdue Equipment Loan';
                    $message = "Your loan of '{$loan['equipment_name']}' was due on " . date('M d, Y', strtotime($loan['due_date'])) . " and is now overdue. Please return it to the clinic.";
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, loan_id, type, title, message, is_read, created_at)
                        VALUES (?, ?, 'overdue_loan', ?, ?, 0, NOW())
                    ");
                    $stmt->execute([$loan['user_id'], $loan['id'], $title, $message]);
                }
            }
        } catch (PDOException $e) {
            // Silently fail — notification creation is non-critical
            error_log("create_overdue_loan_notifications error: " . $e->getMessage());
        }
    }
}

// Get unread notification count for a user
if (!function_exists('get_unread_count')) {
    function get_unread_count($pdo, $user_id) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

// Get recent notifications for a user
if (!function_exists('get_recent_notifications')) {
    function get_recent_notifications($pdo, $user_id, $limit = 10, $unread_only = false) {
        try {
            $where = $unread_only ? 'WHERE user_id = ? AND is_read = 0' : 'WHERE user_id = ?';
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}

// Mark one notification as read. Owner-scoped: only the owner's row can change.
// Returns true when an unread row was actually updated.
if (!function_exists('mark_notification_as_read')) {
    function mark_notification_as_read($pdo, $notification_id, $user_id) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0'
            );
            $stmt->execute([(int)$notification_id, (int)$user_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Mark every unread notification of a user as read; returns how many were updated
// (used in the "Marked N notification(s) as read." flash message).
if (!function_exists('mark_all_notifications_as_read')) {
    function mark_all_notifications_as_read($pdo, $user_id) {
        try {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
            $stmt->execute([(int)$user_id]);
            return (int)$stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

// Text colour class for the notification icon, by notifications.severity
// (enum: info, success, warning, danger).
if (!function_exists('severity_text_class')) {
    function severity_text_class($severity) {
        switch ((string)$severity) {
            case 'success':
                return 'text-success';
            case 'warning':
                return 'text-warning';
            case 'danger':
                return 'text-danger';
            default:
                return 'text-primary';
        }
    }
}

// Badge class for the notification severity label (Bootstrap 5 utilities).
if (!function_exists('severity_badge_class')) {
    function severity_badge_class($severity) {
        switch ((string)$severity) {
            case 'success':
                return 'bg-success';
            case 'warning':
                return 'bg-warning text-dark';
            case 'danger':
                return 'bg-danger';
            default:
                return 'bg-primary';
        }
    }
}

// Event dispatcher helper — fires an event to registered listeners
if (!function_exists('fire_event')) {
    function fire_event($event, $data = []) {
        if (class_exists('EventDispatcher')) {
            EventDispatcher::dispatch($event, $data);
        }
    }
}

// Sync account type with student/employee details tables
if (!function_exists('sync_account_type')) {
    function sync_account_type($pdo, $user_id, $account_type) {
        if ($account_type === 'student') {
            // Ensure student_details exists
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM student_details WHERE user_id = ?');
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO student_details (user_id, student_no) VALUES (?, "")')
                    ->execute([$user_id]);
            }
            // Remove from employee_details if exists
            $pdo->prepare('DELETE FROM employee_details WHERE user_id = ?')->execute([$user_id]);
        } elseif ($account_type === 'employee') {
            // Ensure employee_details exists
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM employee_details WHERE user_id = ?');
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO employee_details (user_id, employee_no) VALUES (?, "")')
                    ->execute([$user_id]);
            }
            // Remove from student_details if exists
            $pdo->prepare('DELETE FROM student_details WHERE user_id = ?')->execute([$user_id]);
        }
    }
}

// Report date range helper — computes from/to dates based on period
if (!function_exists('report_range')) {
    function report_range($period = 'monthly', $from = null, $to = null) {
        $allowed = ['daily', 'weekly', 'monthly', 'yearly'];
        if (!in_array($period, $allowed, true)) {
            $period = 'monthly';
        }

        if ($from && $to) {
            // Explicit dates provided — validate them
            $from = date('Y-m-d', strtotime($from));
            $to = date('Y-m-d', strtotime($to));
            return [$period, $from, $to];
        }

        $now = new DateTime();
        $today = $now->format('Y-m-d');

        switch ($period) {
            case 'daily':
                $from = $today;
                $to = $today;
                break;
            case 'weekly':
                // Start of week (Monday)
                $from = $now->modify('monday this week')->format('Y-m-d');
                $to = (clone $now)->modify('sunday this week')->format('Y-m-d');
                break;
            case 'monthly':
                $from = $now->modify('first day of this month')->format('Y-m-d');
                $to = $now->modify('last day of this month')->format('Y-m-d');
                break;
            case 'yearly':
                $from = $now->modify('first day of January')->format('Y-m-d');
                $to = $now->modify('last day of December')->format('Y-m-d');
                break;
        }

        return [$period, $from, $to];
    }
}

// CSRF token generation and validation
if (!function_exists('get_csrf_token')) {
    function get_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Get setting as integer with default
if (!function_exists('get_setting_int')) {
    function get_setting_int($pdo, $key, $default = 0) {
        $val = get_setting($pdo, $key, '');
        return $val !== '' ? (int)$val : $default;
    }
}

// Get medicine stock (total available across all batches)
if (!function_exists('medicine_stock')) {
    function medicine_stock($pdo, $medicine_id) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS stock
            FROM medicine_batches
            WHERE medicine_id = ? AND quantity > 0
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ");
        $stmt->execute([$medicine_id]);
        return (int)$stmt->fetchColumn();
    }
}

// Allergy conflicts between a patient and a medicine.
// Returns the matching user_allergies rows (staff/dispense.php prints
// allergen_name + severity from each row); an empty array means "no conflict".
// Matching order: explicit medicine link, explicit category link, then
// case-insensitive text matching against the medicine's name, generic name,
// description and category name (both directions, plus whole-word tokens).
if (!function_exists('check_allergy_conflict')) {
    function check_allergy_conflict($pdo, $user_id, $medicine_id) {
        $user_id = (int)$user_id;
        $medicine_id = (int)$medicine_id;
        if ($user_id <= 0 || $medicine_id <= 0) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT m.id, m.name, m.generic_name, m.description, m.category_id, c.name AS category_name
               FROM medicines m
               LEFT JOIN medicine_categories c ON c.id = m.category_id
              WHERE m.id = ? LIMIT 1'
        );
        $stmt->execute([$medicine_id]);
        $medicine = $stmt->fetch();
        if (!$medicine) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT id, medicine_id, category_id, allergen_name, severity, notes
               FROM user_allergies
              WHERE user_id = ?'
        );
        $stmt->execute([$user_id]);
        $allergies = $stmt->fetchAll();
        if (!$allergies) {
            return [];
        }

        // Everything the medicine is described by, lowercased once.
        $parts = [];
        foreach (['name', 'generic_name', 'description', 'category_name'] as $field) {
            $value = trim((string)($medicine[$field] ?? ''));
            if ($value !== '') {
                $parts[] = mb_strtolower($value);
            }
        }
        $haystack = implode(' ', $parts);

        // Medicine-side keys tested against the allergy text (reverse direction).
        $medicineKeys = [];
        foreach (['name', 'generic_name'] as $field) {
            $value = mb_strtolower(trim((string)($medicine[$field] ?? '')));
            if (mb_strlen($value) >= 4) {
                $medicineKeys[] = $value;
            }
        }

        $conflicts = [];
        foreach ($allergies as $allergy) {
            if ($allergy['medicine_id'] !== null && (int)$allergy['medicine_id'] === $medicine_id) {
                $conflicts[] = $allergy;
                continue;
            }
            if ($allergy['category_id'] !== null
                && $medicine['category_id'] !== null
                && (int)$allergy['category_id'] === (int)$medicine['category_id']) {
                $conflicts[] = $allergy;
                continue;
            }

            $allergen = mb_strtolower(trim((string)($allergy['allergen_name'] ?? '')));
            if ($allergen === '') {
                continue;
            }

            // The allergen text appears in the medicine's details …
            if (mb_strpos($haystack, $allergen) !== false) {
                $conflicts[] = $allergy;
                continue;
            }

            // … or a significant allergen word appears in them ("peanut" in "Peanut Oil") …
            $matched = false;
            $tokens = preg_split('/[^\p{L}\p{N}]+/u', $allergen, -1, PREG_SPLIT_NO_EMPTY);
            foreach ((array)$tokens as $token) {
                if (mb_strlen($token) >= 4 && mb_strpos($haystack, $token) !== false) {
                    $matched = true;
                    break;
                }
            }

            // … or the medicine's own name/generic name appears in the allergy text
            // (allergy "Amoxicillin 500mg" vs medicine "Amoxicillin").
            if (!$matched) {
                foreach ($medicineKeys as $key) {
                    if (mb_strpos($allergen, $key) !== false) {
                        $matched = true;
                        break;
                    }
                }
            }

            if ($matched) {
                $conflicts[] = $allergy;
            }
        }

        return $conflicts;
    }
}

// True when the same patient already has this medicine — or the same purpose —
// recorded within the last 24 hours. Uses the database clock (NOW()) so it
// matches medicine_requests.requested_at, which is written by MySQL.
if (!function_exists('recent_medicine_request_conflict')) {
    function recent_medicine_request_conflict($pdo, $user_id, $medicine_id, $reason) {
        $reason = trim((string)$reason);
        $where = 'user_id = ? AND requested_at >= (NOW() - INTERVAL 24 HOUR)';
        $params = [(int)$user_id];

        if ($reason !== '') {
            $where .= ' AND (medicine_id = ? OR TRIM(reason) = ?)';
            $params[] = (int)$medicine_id;
            $params[] = $reason;
        } else {
            $where .= ' AND medicine_id = ?';
            $params[] = (int)$medicine_id;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM medicine_requests WHERE $where");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}

// Deduct a quantity from a medicine's batches, oldest expiry first (the same
// batches medicine_stock() counts), writing one stock_movements OUT row per
// batch with ref_type 'dispense' / ref_id = request id so staff/deduction_logs.php
// can join the dispensing record.
// Returns the batches used — [['batch_id' => int, 'quantity' => int], …] with the
// primary batch first — or null when stock is insufficient (the caller rolls back).
// Runs inside the caller's transaction; FOR UPDATE locks the batch rows it reads.
if (!function_exists('deduct_stock_trace')) {
    function deduct_stock_trace($pdo, $medicine_id, $qty, $performed_by, $request_id, $reason) {
        $medicine_id = (int)$medicine_id;
        $qty = (int)$qty;
        if ($medicine_id <= 0 || $qty <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, quantity FROM medicine_batches
              WHERE medicine_id = ? AND quantity > 0
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
              ORDER BY (expiry_date IS NULL), expiry_date, id
              FOR UPDATE'
        );
        $stmt->execute([$medicine_id]);
        $batches = $stmt->fetchAll();

        $available = 0;
        foreach ($batches as $batch) {
            $available += (int)$batch['quantity'];
        }
        if ($available < $qty) {
            return null;
        }

        $updateBatch = $pdo->prepare('UPDATE medicine_batches SET quantity = quantity - ? WHERE id = ?');
        $insertMovement = $pdo->prepare(
            'INSERT INTO stock_movements (medicine_id, batch_id, type, quantity, reason, ref_type, ref_id, performed_by)
             VALUES (?, ?, "OUT", ?, ?, "dispense", ?, ?)'
        );

        $used = [];
        $remaining = $qty;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((int)$batch['quantity'], $remaining);
            if ($take <= 0) {
                continue;
            }
            $updateBatch->execute([$take, (int)$batch['id']]);
            $insertMovement->execute([$medicine_id, (int)$batch['id'], $take, $reason, $request_id, $performed_by]);
            $used[] = ['batch_id' => (int)$batch['id'], 'quantity' => $take];
            $remaining -= $take;
        }

        return $remaining === 0 ? $used : null;
    }
}

// Age (in years) from a birthdate; returns null when unknown or invalid.
if (!function_exists('age_from_birthdate')) {
    function age_from_birthdate($birthdate) {
        if ($birthdate === null || $birthdate === '') {
            return null;
        }
        try {
            $born = new DateTime((string)$birthdate);
        } catch (Exception $ex) {
            return null;
        }
        $today = new DateTime('today');
        if ($born > $today) {
            return null;
        }
        return (int)$born->diff($today)->y;
    }
}

// Generate certificate number
if (!function_exists('generate_cert_no')) {
    function generate_cert_no($pdo, $type = 'medical') {
        $prefix = $type === 'medical' ? 'MED' : 'FIT';
        $stmt = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(cert_no, " . (strlen($prefix) + 1) . ") AS UNSIGNED))
            FROM medical_certificates
            WHERE cert_no LIKE ?
        ");
        $stmt->execute([$prefix . '%']);
        $max = (int)$stmt->fetchColumn();
        $next = $max + 1;
        return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
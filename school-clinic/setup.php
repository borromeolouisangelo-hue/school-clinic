<?php
/**
 * Create or repair the development/demo accounts after importing database.sql.
 *
 * DANGER: this script overwrites the demo account passwords and runs schema
 * migrations, so it is restricted to authenticated admins. database.sql already
 * seeds an admin account, so a fresh install can log in first and then run this.
 */
require_once __DIR__ . '/config/db.php';
require_login(['admin']);

$accounts = [
	['admin', 'admin@school.edu', 'admin123', 'admin', 'employee'],
	['staff', 'staff@school.edu', 'staff123', 'staff', 'employee'],
	['student', 'student@school.edu', 'student123', 'user', 'student'],
	['employee', 'employee@school.edu', 'employee123', 'user', 'employee'],
];

foreach ($accounts as [$username, $email, $password, $role, $accountType]) {
	$stmt = $pdo->prepare(
		'UPDATE users SET email = ?, password_hash = ?, role = ?, account_type = ?, status = "active" WHERE username = ?'
	);
	$stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $role, $accountType, $username]);
}

$employeeCheck = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$employeeCheck->execute(['employee']);
if (!$employeeCheck->fetchColumn()) {
	$pdo->beginTransaction();
	try {
		$pdo->prepare('INSERT INTO users (username, email, password_hash, role, account_type, status) VALUES (?, ?, ?, ?, ?, "active")')
			->execute(['employee', 'employee@school.edu', password_hash('employee123', PASSWORD_DEFAULT), 'user', 'employee']);
		$employeeId = (int)$pdo->lastInsertId();
		$pdo->prepare('INSERT INTO profiles (user_id, first_name, middle_name, last_name) VALUES (?, ?, ?, ?)')
			->execute([$employeeId, 'Maria', 'D', 'Santos']);
		$pdo->prepare('INSERT INTO employee_details (user_id, employee_no, department, position) VALUES (?, ?, ?, ?)')
			->execute([$employeeId, 'EMP-0003', 'Registrar', 'Administrative Officer']);
		$pdo->commit();
	} catch (Throwable $ex) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $ex;
	}
}

// Close legacy workflow states before narrowing the status enums.
$pdo->exec("UPDATE medicine_requests
			SET status = 'rejected',
				remarks = COALESCE(NULLIF(remarks, ''), 'Closed during status workflow migration'),
				processed_at = COALESCE(processed_at, NOW())
			WHERE status IN ('pending', 'approved')");
$pdo->exec("ALTER TABLE medicine_requests
			MODIFY status ENUM('dispensed','rejected') NOT NULL DEFAULT 'dispensed'");
$pdo->exec("UPDATE equipment_loans
			SET status = 'borrowed'
			WHERE status = 'overdue'");
$pdo->exec("UPDATE equipment_loans
			SET status = 'returned', return_date = COALESCE(return_date, CURDATE())
			WHERE status IN ('requested', 'rejected')");
$pdo->exec("ALTER TABLE equipment_loans
			MODIFY status ENUM('borrowed','returned') NOT NULL DEFAULT 'borrowed'");

$loanColumn = $pdo->query("SELECT COUNT(*) FROM information_schema.columns
                           WHERE table_schema = DATABASE()
                             AND table_name = 'equipment_loans'
                             AND column_name = 'borrowed_at'")->fetchColumn();
if (!(int)$loanColumn) {
	$pdo->exec("ALTER TABLE equipment_loans ADD borrowed_at DATETIME NULL AFTER borrow_date");
	$pdo->exec("UPDATE equipment_loans SET borrowed_at = COALESCE(created_at, CONCAT(borrow_date, ' 00:00:00')) WHERE borrowed_at IS NULL");
	$pdo->exec("ALTER TABLE equipment_loans MODIFY borrowed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	loan_id INT DEFAULT NULL,
	type VARCHAR(60) NOT NULL,
	title VARCHAR(150) NOT NULL,
	message TEXT NOT NULL,
	is_read TINYINT(1) NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_notification_reference (loan_id, type),
	CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_notification_loan FOREIGN KEY (loan_id) REFERENCES equipment_loans(id) ON DELETE CASCADE
) ENGINE=InnoDB");

?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Setup complete</title></head>
<body>
<h1>Setup complete</h1>
<p>Demo account passwords were updated. Delete or protect setup.php before deploying.</p>
</body>
</html>

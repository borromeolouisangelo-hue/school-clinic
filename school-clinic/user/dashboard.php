<?php
/**
 * Student / Employee dashboard.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

$page_title = 'My Dashboard';
$uid = (int)current_user()['id'];
$isEmployee = current_user()['account_type'] === 'employee';

create_overdue_loan_notifications($pdo);
$unreadNotifCount = get_unread_count($pdo, $uid);
$recentNotifications = get_recent_notifications($pdo, $uid, 5, true);

// Handle sick leave form submission (modal on dashboard)
$sickLeaveErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'sick_leave') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('user/dashboard.php');
    }

    $reason     = trim($_POST['reason'] ?? '');
    $startDate  = $_POST['start_date'] ?? '';
    $endDate    = $_POST['end_date'] ?? '';

    if ($reason === '') $sickLeaveErrors[] = 'Please describe your reason for leave.';
    if ($startDate === '') $sickLeaveErrors[] = 'Start date is required.';
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $sickLeaveErrors[] = 'Invalid start date format.';
    if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $sickLeaveErrors[] = 'Invalid end date format.';
    if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) $sickLeaveErrors[] = 'End date cannot be before start date.';

    if (!$sickLeaveErrors) {
        $stmt = $pdo->prepare(
            'INSERT INTO sick_leaves (user_id, reason, start_date, end_date, status)
             VALUES (?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$uid, $reason, $startDate, $endDate !== '' ? $endDate : null]);
        $leaveId = (int)$pdo->lastInsertId();
        fire_event('sick_leave.created', ['leave_id' => $leaveId]);
        set_flash('success', 'Sick leave reported. The clinic staff has been notified.');
        redirect('user/dashboard.php');
    }
}

// Fetch recent sick leave records for quick display
$stmt = $pdo->prepare('SELECT * FROM sick_leaves WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$uid]);
$recentSickLeaves = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM medicine_requests WHERE user_id = ?");
$stmt->execute([$uid]);
$reqCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_loans WHERE user_id = ?");
$stmt->execute([$uid]);
$loanCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE patient_id = ?");
$stmt->execute([$uid]);
$consultationCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_loans WHERE user_id = ? AND status = 'borrowed'");
$stmt->execute([$uid]);
$activeLoanCount = (int)$stmt->fetchColumn();

// medical record completeness
$stmt = $pdo->prepare('SELECT COUNT(*) FROM medical_records WHERE user_id = ?');
$stmt->execute([$uid]);
$hasMedRec = (int)$stmt->fetchColumn() > 0;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM user_allergies WHERE user_id = ?');
$stmt->execute([$uid]);
$allergyCount = (int)$stmt->fetchColumn();

// Layout: dashboard layout with sidebar
require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
            <?php if (!$hasMedRec): ?>
                <div class="alert alert-warning d-flex align-items-center justify-content-between">
                    <div><i class="bi bi-exclamation-triangle me-1"></i> You have not submitted your medical form yet.</div>
                    <a href="<?= e(url('user/medical_form.php')) ?>" class="btn btn-sm btn-primary">Complete Now</a>
                </div>
            <?php endif; ?>
            <?php if ($unreadNotifCount > 0): ?>
                <div class="alert alert-info d-flex align-items-center justify-content-between">
                    <div><i class="bi bi-bell me-1"></i> You have <?= $unreadNotifCount ?> unread notification<?= $unreadNotifCount > 1 ? 's' : '' ?>.</div>
                    <a href="<?= e(url('notifications.php')) ?>" class="btn btn-sm btn-primary">View All</a>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3"><div class="metric-card"><div class="metric-icon icon-primary"><i class="bi bi-activity"></i></div><div class="metric-value"><?= $reqCount ?></div><div class="metric-label">Total Requests</div></div></div>
                <div class="col-6 col-lg-3"><div class="metric-card"><div class="metric-icon icon-warning"><i class="bi bi-tools"></i></div><div class="metric-value"><?= $loanCount ?></div><div class="metric-label">Total Loans</div></div></div>
                <div class="col-6 col-lg-3"><div class="metric-card"><div class="metric-icon icon-success"><i class="bi bi-clipboard2-pulse"></i></div><div class="metric-value"><?= $consultationCount ?></div><div class="metric-label">Consultations</div></div></div>
                <div class="col-6 col-lg-3"><div class="metric-card"><div class="metric-icon icon-primary"><i class="bi bi-arrow-left-right"></i></div><div class="metric-value"><?= $activeLoanCount ?></div><div class="metric-label">Active Loans</div></div></div>
            </div>

            <div class="row g-3">
                <div class="<?= ($isEmployee && !empty($recentSickLeaves)) ? 'col-lg-6' : 'col-12' ?>">
                    <div class="card h-100">
                        <div class="card-header">My Tasks</div>
                        <div class="card-body d-grid gap-2">
                            <a href="<?= e(url('user/medical_form.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-file-medical me-1"></i> Update Medical Form</a>
                            <a href="<?= e(url('user/my_requests.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-activity me-1"></i> Open My Activity</a>
                            <a href="<?= e(url('user/my_consultations.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-clipboard2-pulse me-1"></i> View My Consultations</a>
                            <?php if ($isEmployee): ?>
                                <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#sickLeaveModal"><i class="bi bi-thermometer me-1"></i> Notify Staff (Sick Leave)</button>
                            <?php endif; ?>
                            <div class="alert alert-info small mb-0">Medicine requests are entered by clinic staff when you visit the clinic in person.</div>
                            <?php if ($allergyCount === 0): ?>
                                <div class="alert-allergy p-2 small mb-0">
                                    <i class="bi bi-info-circle"></i> You have no recorded allergies. Add them in your Medical Form so staff can be alerted before dispensing.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($isEmployee && $recentSickLeaves): ?>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><i class="bi bi-clock-history me-1"></i> Recent Sick Leave</div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($recentSickLeaves as $sl): ?>
                                    <li class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <small class="text-muted"><?= e(fmt_dt($sl['created_at'], 'M d, Y g:i A')) ?></small>
                                            <div><?= e(mb_strimwidth($sl['reason'], 0, 60, '…')) ?></div>
                                            <small class="text-muted">
                                                <?= e(date('M d, Y', strtotime($sl['start_date']))) ?>
                                                <?php if ($sl['end_date']): ?> – <?= e(date('M d, Y', strtotime($sl['end_date']))) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <?php if ($sl['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif ($sl['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Declined</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="<?= e(url('user/my_requests.php')) ?>" class="btn btn-sm btn-outline-primary mt-2">View Full History</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sick Leave Modal -->
            <?php if ($isEmployee): ?>
            <div class="modal fade" id="sickLeaveModal" tabindex="-1" aria-labelledby="sickLeaveModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-md">
                    <div class="modal-content">
                        <div class="card">
                            <div class="card-header" id="sickLeaveModalHeader"><i class="bi bi-thermometer me-1"></i> Notify Staff — Sick Leave</div>
                            <div class="card-body">
                                <?php foreach ($sickLeaveErrors as $err): ?>
                                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                                <?php endforeach; ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                    <input type="hidden" name="form_action" value="sick_leave">
                                    <div class="mb-3">
                                        <label class="form-label">Reason for Leave *</label>
                                        <textarea name="reason" class="form-control" rows="4" required placeholder="Describe your symptoms or reason for sick leave..."><?= e($_POST['reason'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Start Date *</label>
                                        <input type="date" name="start_date" class="form-control" value="<?= e($_POST['start_date'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">End Date <span class="text-muted">(optional)</span></label>
                                        <input type="date" name="end_date" class="form-control" value="<?= e($_POST['end_date'] ?? '') ?>">
                                        <div class="form-text">Leave blank if returning the same day.</div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-accent" data-swal-add="sick leave"><i class="bi bi-send me-1"></i> Notify Staff</button>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($sickLeaveErrors): ?><script>document.addEventListener('DOMContentLoaded', function() { var modal = document.getElementById('sickLeaveModal'); if (modal && window.bootstrap) new bootstrap.Modal(modal).show(); });</script><?php endif; ?>
            <?php endif; ?>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
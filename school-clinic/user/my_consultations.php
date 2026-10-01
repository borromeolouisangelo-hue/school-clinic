<?php
/**
 * User — My consultation history.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

$page_title = 'My Consultations';
$uid = (int)current_user()['id'];

$stmt = $pdo->prepare(
    "SELECT c.*, CONCAT(p.first_name,' ',p.last_name) AS staff_name
     FROM consultations c LEFT JOIN profiles p ON p.user_id = c.staff_id
     WHERE c.patient_id = ? ORDER BY c.consultation_date DESC"
);
$stmt->execute([$uid]);
$list = $stmt->fetchAll();

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<div class="alert alert-info">Consultation records are shown here for quick review. For full request and equipment activity, use <a href="<?= e(url('user/my_requests.php')) ?>" class="alert-link">My Activity</a>.</div>
<?php if (!$list): ?>
    <div class="card"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-clipboard2-pulse fs-1 d-block mb-2"></i>
        You have no consultation records yet.
    </div></div>
<?php else: ?>
    <?php foreach ($list as $c): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-calendar3 me-1"></i> <?= e(fmt_dt($c['consultation_date'], 'M d, Y g:i A')) ?></span>
                <span class="text-muted small">Attending: <?= e($c['staff_name'] ?: '—') ?></span>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Complaint:</strong> <?= e($c['complaint']) ?></p>
                <?php if ($c['diagnosis']): ?><p class="mb-2"><strong>Diagnosis:</strong> <?= e($c['diagnosis']) ?></p><?php endif; ?>
                <?php if ($c['treatment']): ?><p class="mb-2"><strong>Treatment:</strong> <?= e($c['treatment']) ?></p><?php endif; ?>
                <div class="row g-2 small text-muted mt-2">
                    <?php if ($c['blood_pressure']): ?><div class="col-auto">BP: <?= e($c['blood_pressure']) ?></div><?php endif; ?>
                    <?php if ($c['temperature'] !== null): ?><div class="col-auto">Temp: <?= e($c['temperature']) ?>°C</div><?php endif; ?>
                    <?php if ($c['pulse']): ?><div class="col-auto">Pulse: <?= e($c['pulse']) ?> bpm</div><?php endif; ?>
                    <?php if ($c['weight_kg']): ?><div class="col-auto">Weight: <?= e($c['weight_kg']) ?> kg</div><?php endif; ?>
                    <?php if ($c['height_cm']): ?><div class="col-auto">Height: <?= e($c['height_cm']) ?> cm</div><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
<?php
/**
 * User — My medical certificates (view + print).
 */
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

$page_title = 'My Certificates';
$uid = (int)current_user()['id'];

$printId = (int)($_GET['print'] ?? 0);
$cert = null;
if ($printId > 0) {
    $stmt = $pdo->prepare(
        "SELECT c.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, sd.student_no, ed.employee_no
         FROM medical_certificates c
         LEFT JOIN profiles p ON p.user_id = c.user_id
         LEFT JOIN student_details sd ON sd.user_id = c.user_id
         LEFT JOIN employee_details ed ON ed.user_id = c.user_id
         WHERE c.id = ? AND c.user_id = ? LIMIT 1"
    );
    $stmt->execute([$printId, $uid]);
    $cert = $stmt->fetch();
}

$stmt = $pdo->prepare('SELECT * FROM medical_certificates WHERE user_id = ? ORDER BY issued_at DESC');
$stmt->execute([$uid]);
$certs = $stmt->fetchAll();

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>

<?php if ($cert): ?>
    <div class="mb-3 no-print">
        <a href="<?= e(url('user/my_certificates.php')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        <button class="btn btn-sm btn-accent" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
    <div class="card">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h4 class="text-primary mb-0"><?= e(get_setting($pdo, 'school_name', 'School')) ?></h4>
                <div><?= e(get_setting($pdo, 'clinic_name', 'School Health Clinic')) ?></div>
                <div class="text-muted small"><?= e(get_setting($pdo, 'clinic_address', '')) ?> <?= e(get_setting($pdo, 'clinic_contact', '')) ?></div>
                <hr>
                <h5 class="text-uppercase"><?= e(ucfirst($cert['cert_type'])) ?> Certificate</h5>
            </div>
            <p>Date: <strong><?= e(fmt_dt($cert['issued_at'], 'F d, Y')) ?></strong></p>
            <p class="mb-2">To Whom It May Concern:</p>
            <p>This is to certify that <strong><?= e($cert['patient_name']) ?></strong>
                <?php if ($cert['student_no']): ?>(Student No. <?= e($cert['student_no']) ?>)<?php elseif ($cert['employee_no']): ?>(Employee No. <?= e($cert['employee_no']) ?>)<?php endif; ?>
                was seen at the school clinic on <strong><?= e(fmt_dt($cert['issued_at'], 'F d, Y')) ?></strong>.</p>
            <p><strong>Purpose:</strong> <?= e($cert['purpose']) ?></p>
            <?php if ($cert['findings']): ?><p><strong>Findings:</strong> <?= nl2br(e($cert['findings'])) ?></p><?php endif; ?>
            <?php if ($cert['recommendation']): ?><p><strong>Recommendation:</strong> <?= nl2br(e($cert['recommendation'])) ?></p><?php endif; ?>
            <?php if ($cert['rest_days']): ?><p><strong>Recommended rest:</strong> <?= (int)$cert['rest_days'] ?> day(s)</p><?php endif; ?>
            <div class="row mt-5">
                <div class="col-6"></div>
                <div class="col-6 text-center"><div class="border-top pt-1">Attending Clinic Personnel</div></div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>#</th><th>Type</th><th>Purpose</th><th>Issued</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (!$certs): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">You have no certificates yet.</td></tr>
                <?php else: foreach ($certs as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td><span class="chip"><?= ucfirst($c['cert_type']) ?></span></td>
                        <td><?= e(mb_strimwidth((string)$c['purpose'], 0, 40, '…')) ?></td>
                        <td><?= e(fmt_dt($c['issued_at'], 'M d, Y')) ?></td>
                        <td class="text-end">
                            <a href="<?= e(url('user/my_certificates.php?print=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i> Print</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
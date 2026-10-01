<?php
/**
 * Staff — Medical certificates (issue + list + print).
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

$page_title = 'Medical Certificates';
$errors = [];

// ---- Issue certificate -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('staff/certificates.php');
    }
    $pid     = (int)($_POST['patient_id'] ?? 0);
    $type    = trim($_POST['cert_type'] ?? 'medical');
    $purpose = trim($_POST['purpose'] ?? '');
    $find    = trim($_POST['findings'] ?? '');
    $reco    = trim($_POST['recommendation'] ?? '');
    $rest    = ($_POST['rest_days'] ?? '') !== '' ? (int)$_POST['rest_days'] : null;

    if ($pid <= 0) $errors[] = 'Select a patient.';
    if ($purpose === '') $errors[] = 'Purpose is required.';

    if (!$errors) {
        $certNo = generate_cert_no($pdo);
        $pdo->prepare('INSERT INTO medical_certificates (cert_no, user_id, issued_by, cert_type, purpose, findings, recommendation, rest_days)
                       VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$certNo, $pid, (int)current_user()['id'], $type, $purpose, $find, $reco, $rest]);
        $cid = (int)$pdo->lastInsertId();
        log_action($pdo, (int)current_user()['id'], 'issue_certificate', 'medical_certificates', $cid, "Issued certificate $certNo");
        fire_event('certificate.issued', ['certificate_id' => $cid]);
        set_flash('success', 'Certificate issued.');
        redirect('staff/certificates.php?print=' . $cid);
    }
}

if (($_GET['action'] ?? '') === 'delete' && ($cid = (int)($_GET['id'] ?? 0)) > 0) {
    $pdo->prepare('DELETE FROM medical_certificates WHERE id=?')->execute([$cid]);
    log_action($pdo, (int)current_user()['id'], 'delete_certificate', 'medical_certificates', $cid, "Deleted certificate #$cid");
    set_flash('success', 'Certificate deleted.');
    redirect('staff/certificates.php');
}

$patients = $pdo->query(
    "SELECT u.id, CONCAT(p.first_name,' ',p.last_name) AS full_name
     FROM users u LEFT JOIN profiles p ON p.user_id=u.id WHERE u.role='user' ORDER BY p.first_name"
)->fetchAll();

// ---- Printable single certificate -----------------------------------------
$printId = (int)($_GET['print'] ?? 0);
$cert = null;
if ($printId > 0) {
    $stmt = $pdo->prepare(
        "SELECT c.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.birthdate, p.gender, p.address, p.contact_no,
            sd.student_no, ed.employee_no,
            CONCAT(ip.first_name, ' ', ip.last_name) AS issuer_name
         FROM medical_certificates c
         LEFT JOIN profiles p ON p.user_id = c.user_id
         LEFT JOIN student_details sd ON sd.user_id = c.user_id
         LEFT JOIN employee_details ed ON ed.user_id = c.user_id
         LEFT JOIN profiles ip ON ip.user_id = c.issued_by
         WHERE c.id=? LIMIT 1"
    );
    $stmt->execute([$printId]);
    $cert = $stmt->fetch();
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.gender, p.birthdate
        FROM medical_certificates c
        LEFT JOIN profiles p ON p.user_id = c.user_id";
$params = [];
if ($search !== '') {
    $sql .= " WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR c.purpose LIKE ?";
    array_push($params, "%$search%", "%$search%", "%$search%");
}
$sql .= ' ORDER BY c.issued_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$certs = $stmt->fetchAll();

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>

<div class="registry-shell">
<?php if ($cert): ?>
    <div class="mb-3 no-print">
        <a href="<?= e(url('staff/certificates.php')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        <button class="btn btn-sm btn-accent" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
    <main class="certificate-page" id="certificateArea">
        <header class="certificate-header">
            <div class="ncst-logo-wrap" aria-label="NCST logo">
                <img src="<?= e(url('assets/images/ncst.png')) ?>" alt="NCST Logo" class="ncst-logo-image" onerror="this.style.display='none'; this.parentNode.innerHTML='<div class=\'logo-placeholder\'>NCST</div>';" />
            </div>
            <div class="header-text">
                <div class="certificate-school-name"><?= e(get_setting($pdo, 'certificate_school_name', 'NATIONAL COLLEGE OF SCIENCE & TECHNOLOGY')) ?></div>
                <div class="certificate-school-address"><?= e(get_setting($pdo, 'certificate_school_address', 'Amafel Building, Aguinaldo Highway, Dasmariñas, Cavite 4114')) ?></div>
                <div class="certificate-office">HEALTH SERVICES OFFICE</div>
            </div>
            <div aria-hidden="true"></div>
        </header>
        <hr class="header-rule">
        <h1 class="document-title">MEDICAL CERTIFICATE</h1>

        <?php $patientAge = age_from_birthdate($cert['birthdate'] ?? null); ?>
        <section class="certificate-form">
            <p class="certificate-introduction">
                This is to certify that <span class="inline-field patient-name"><?= e($cert['patient_name']) ?></span>
            </p>
            <p class="certificate-introduction">
                <span class="inline-field age"><?= $patientAge !== null ? (int)$patientAge : '' ?></span> y/o, ( <span class="inline-field sex"><?= $cert['gender'] ? e(strtoupper($cert['gender'])) : '' ?></span> ) consulted on <span class="inline-field date"><?= e(fmt_dt($cert['issued_at'], 'F d, Y')) ?></span>
            </p>
            <p class="certificate-introduction certificate-introduction-last">
                Because of <span class="inline-field reason"><?= e($cert['purpose']) ?></span>
            </p>

            <section class="section">
                <h2 class="section-heading">DIAGNOSIS:</h2>
                <div class="editable-box diagnosis-box"><?= $cert['findings'] ? nl2br(e($cert['findings'])) : '' ?></div>
            </section>

            <section class="section">
                <h2 class="section-heading">REMARKS/ RECOMMENDATION:</h2>
                <div class="editable-box remarks-box">
                    <?= nl2br(e($cert['recommendation'] ?: 'Essentially, normal PE findings during the time of consultation. No comorbidities found.')) ?>
                    <?php if ($cert['rest_days']): ?><div>Recommended rest: <?= (int)$cert['rest_days'] ?> day(s).</div><?php endif; ?>
                </div>
            </section>

            <footer class="certificate-footer">
                <div class="issuance-text">
                    This certification is issued upon the request of Mr./ Ms <span class="inline-field requestor-name"><?= e($cert['patient_name']) ?></span>
                    <div class="purpose-note">Medical purpose only.</div>
                </div>

                <div class="signature-block">
                    <div class="signature-space"></div>
                    <p class="physician-name"><?= e(get_setting($pdo, 'certificate_physician_name', 'GERALINE S. GABUTERO, M.D.')) ?></p>
                    <p class="physician-title">School Physician</p>
                </div>
            </footer>
        </section>
    </main>
    <script>
    window.addEventListener('load', function () {
        window.print();
    });
    </script>

<?php else: ?>

    <div class="registry-stats row g-3 mb-4">
        <div class="col-md-4">
            <div class="metric-card">
                <div class="metric-icon icon-primary"><i class="bi bi-file-earmark-medical"></i></div>
                <div class="metric-value"><?= count($certs) ?></div>
                <div class="metric-label">Total Certificates</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric-card">
                <div class="metric-icon icon-success"><i class="bi bi-calendar-check"></i></div>
                <div class="metric-value"><?= count(array_filter($certs, fn($c) => !empty($c['issued_at']) && date('Y-m-d', strtotime($c['issued_at'])) === date('Y-m-d'))) ?></div>
                <div class="metric-label">Issued Today</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric-card metric-card-compact">
                <div class="metric-icon icon-warning"><i class="bi bi-person-badge"></i></div>
                <div class="metric-value metric-value--text">Dr. Gabutero</div>
                <div class="metric-label">Physician on Duty</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="modal fade" id="certificateModal" tabindex="-1" aria-labelledby="certificateModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-md"><div class="modal-content">
            <div class="card">
                <div class="card-header" id="certificateModalLabel">Issue Certificate</div>
                <div class="card-body">
                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger py-2"><?= e($err) ?></div>
                    <?php endforeach; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Patient *</label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">-- select --</option>
                                <?php foreach ($patients as $pt): ?>
                                    <option value="<?= (int)$pt['id'] ?>"><?= e($pt['full_name'] ?: ('User #' . $pt['id'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="cert_type" class="form-select">
                                <option value="medical">Medical Certificate</option>
                                <option value="excuse">Excuse Slip</option>
                                <option value="fit">Fit to Work / Study</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Purpose *</label>
                            <input type="text" name="purpose" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Findings</label>
                            <textarea name="findings" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Recommendation</label>
                            <textarea name="recommendation" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rest Days</label>
                            <input type="number" name="rest_days" class="form-control" min="0">
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-swal-add="certificate"><i class="bi bi-file-earmark-plus me-1"></i> Issue</button></div>
                    </form>
                </div>
            </div>
                </div></div>
            </div>
        </div>

        <div class="col-12">
            <div class="table-panel card mb-3">
                <div class="card-body">
                    <div class="row g-2 align-items-end audit-toolbar mb-2">
                        <div class="col">
                            <label class="form-label">Search</label>
                            <input id="certificatesSearch" type="search" class="form-control form-control-sm" data-live-filter="#certificatesTable tbody tr" data-live-filter-count="certificatesResultCount" placeholder="Search patient name..." aria-label="Search certificates">
                        </div>
                        <div class="col-auto">
                            <label class="form-label">Filter</label>
                            <form method="get" class="d-inline">
                                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="all" <?= (($_GET['type'] ?? 'all') === 'all') ? 'selected' : '' ?>>All Types</option>
                                    <option value="medical" <?= (($_GET['type'] ?? '') === 'medical') ? 'selected' : '' ?>>Medical Certificate</option>
                                    <option value="excuse" <?= (($_GET['type'] ?? '') === 'excuse') ? 'selected' : '' ?>>Excuse Slip</option>
                                    <option value="fit" <?= (($_GET['type'] ?? '') === 'fit') ? 'selected' : '' ?>>Fit to Work / Study</option>
                                </select>
                            </form>
                        </div>
                        <div class="col d-flex flex-wrap gap-2 justify-content-start">
                            
                            <button type="button" class="btn  btn-primary text-nowrap" data-search-trigger="#certificatesSearch"><i class="bi bi-search me-1"></i> Search</button>
                            <a href="<?= e(url('staff/certificates.php')) ?>" class="btn  btn-outline-secondary text-nowrap">Reset</a>
                            <button type="button" class="btn  btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#certificateModal"><i class="bi bi-file-earmark-plus me-1"></i> Create Certificate</button>
                        </div>
                    </div>
                    
                </div>
                <div class="table-responsive">
                    <table id="certificatesTable" class="table table-hover mb-0 align-middle">
                        <thead><tr><th>PATIENT NAME</th><th>SEX / AGE</th><th>CONSULT DATE</th><th>DIAGNOSIS</th><th>REQUESTOR</th><th>ACTIONS</th></tr></thead>
                        <tbody>
                        <?php if (!$certs): ?>
                            <tr><td colspan="6" class="empty-state-cell"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-file-earmark-text"></i></div><div>No medical certificates created yet.</div></div></td></tr>
                        <?php else: foreach ($certs as $c): ?>
                            <tr>
                                <td><?= e($c['patient_name'] ?: '—') ?></td>
                                <td><?= e($c['gender'] ? ucfirst($c['gender']) : '—') ?> / <?= $c['birthdate'] ? e(age_from_birthdate($c['birthdate'])) : '—' ?></td>
                                <td><?= e(fmt_dt($c['issued_at'], 'M d, Y')) ?></td>
                                <td><?= e(mb_strimwidth((string)$c['purpose'], 0, 30, '…')) ?></td>
                                <td><?= e($c['patient_name'] ?: '—') ?></td>
                                <td class="text-end actions-cell">
                                    <a href="<?= e(url('staff/certificates.php?print=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Print certificate"><i class="bi bi-printer"></i></a>
                                    <a href="<?= e(url('staff/certificates.php?action=delete&id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-danger" title="Delete certificate" data-confirm="Delete this certificate?" data-swal-delete="certificate"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php if ($errors): ?><script>document.addEventListener('DOMContentLoaded', function () { var modal = document.getElementById('certificateModal'); if (modal && window.bootstrap) new bootstrap.Modal(modal).show(); });</script><?php endif; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var certificateModal = document.getElementById('certificateModal');
        if (!certificateModal) return;
        certificateModal.addEventListener('hidden.bs.modal', function () {
            var form = certificateModal.querySelector('form');
            if (form) form.reset();
        });
    });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
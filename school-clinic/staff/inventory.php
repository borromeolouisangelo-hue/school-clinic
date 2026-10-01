<?php
/**
 * Staff - Inventory batches and stock receiving.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

$page_title = 'Inventory';
$errors = [];

$medicines = $pdo->query(
    'SELECT m.id, m.name, m.unit, c.name AS category_name, c.type
     FROM medicines m
     LEFT JOIN medicine_categories c ON c.id = m.category_id
     WHERE m.is_active = 1
     ORDER BY c.name, m.name'
)->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('staff/inventory.php');
    }

    $action   = $_POST['action'] ?? 'receive';
    $mid      = (int)($_POST['medicine_id'] ?? 0);
    $batchNo  = trim($_POST['batch_no'] ?? '');
    $lotNo    = trim($_POST['lot_no'] ?? '');
    $supId    = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $qty      = (int)($_POST['quantity'] ?? 0);
    $expiry   = null;
    $received = trim($_POST['date_received'] ?? '') ?: date('Y-m-d');
    $cost     = trim($_POST['unit_cost'] ?? '') !== '' ? (float)$_POST['unit_cost'] : null;

    if ($mid > 0) {
        $itemTypeStmt = $pdo->prepare(
            'SELECT CASE WHEN c.type = "first_aid" THEN "first_aid" ELSE "medicine" END AS item_type
             FROM medicines m
             LEFT JOIN medicine_categories c ON c.id = m.category_id
             WHERE m.id = ? LIMIT 1'
        );
        $itemTypeStmt->execute([$mid]);
        $itemType = $itemTypeStmt->fetchColumn() ?: 'medicine';
        if ($itemType !== 'first_aid') {
            $expiry = trim($_POST['expiry_date'] ?? '') ?: null;
        }
    }

    if ($action === 'receive') {
        if ($mid <= 0) $errors[] = 'Please select a medicine or first aid item.';
        if ($qty < 3) $errors[] = 'Quantity must be at least 3 for stock receiving.';
        if (($expiry !== null) && strtotime($expiry) < strtotime('today')) $errors[] = 'Expiry date cannot be in the past.';
        if (strtotime($received) === false) $errors[] = 'Date received is invalid.';

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                $mergeStmt = $pdo->prepare(
                    'SELECT id, quantity FROM medicine_batches
                     WHERE medicine_id = ?
                       AND COALESCE(batch_no, "") = ?
                       AND COALESCE(lot_no, "") = ?
                       AND COALESCE(expiry_date, "") = ?
                       AND COALESCE(supplier_id, 0) = ?
                     ORDER BY created_at DESC LIMIT 1'
                );
                $mergeStmt->execute([
                    $mid,
                    $batchNo !== '' ? $batchNo : '',
                    $lotNo !== '' ? $lotNo : '',
                    $expiry !== null ? $expiry : '',
                    $supId ?? 0,
                ]);
                $existingBatch = $mergeStmt->fetch();

                if ($existingBatch) {
                    $bid = (int)$existingBatch['id'];
                    $pdo->prepare('UPDATE medicine_batches SET quantity = quantity + ?, date_received = ?, unit_cost = COALESCE(?, unit_cost) WHERE id = ?')
                        ->execute([$qty, $received, $cost, $bid]);
                    $pdo->prepare('INSERT INTO stock_movements (medicine_id, batch_id, type, quantity, reason, ref_type, performed_by)
                                   VALUES (?,?, "IN", ?, "Stock received", "stock_in", ?)')
                        ->execute([$mid, $bid, $qty, (int)current_user()['id']]);
                    log_action($pdo, (int)current_user()['id'], 'stock_in', 'medicine_batches', $bid, "Added $qty units to batch #$bid (medicine #$mid)");
                } else {
                    $pdo->prepare('INSERT INTO medicine_batches (medicine_id, batch_no, lot_no, supplier_id, quantity, expiry_date, date_received, unit_cost)
                                   VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$mid, $batchNo !== '' ? $batchNo : null, $lotNo !== '' ? $lotNo : null, $supId, $qty, $expiry, $received, $cost]);
                    $bid = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO stock_movements (medicine_id, batch_id, type, quantity, reason, ref_type, performed_by)
                                   VALUES (?,?, "IN", ?, "Stock received", "stock_in", ?)')
                        ->execute([$mid, $bid, $qty, (int)current_user()['id']]);
                    log_action($pdo, (int)current_user()['id'], 'stock_in', 'medicine_batches', $bid, "Received $qty units (medicine #$mid)");
                }
                $pdo->commit();
                set_flash('success', 'Stock added successfully.');
                redirect('staff/inventory.php');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Stock could not be added. Please try again.';
            }
        }
    } elseif ($action === 'delete') {
        $bid = (int)($_POST['batch_id'] ?? 0);
        if ($bid > 0) {
            $pdo->prepare('DELETE FROM medicine_batches WHERE id = ?')->execute([$bid]);
            log_action($pdo, (int)current_user()['id'], 'delete_batch', 'medicine_batches', $bid, "Deleted batch #$bid");
            set_flash('success', 'Batch deleted.');
            redirect('staff/inventory.php');
        }
        $errors[] = 'Batch could not be found.';
    }
}

$filterType = in_array($_GET['item_type'] ?? '', ['all', 'medicine', 'first_aid'], true) ? $_GET['item_type'] : 'all';

$inventorySql = 'SELECT m.id, m.name AS medicine_name, m.unit, m.low_stock_threshold,
        c.name AS category_name,
        CASE WHEN c.type = "first_aid" THEN "first_aid" ELSE "medicine" END AS item_type,
        COALESCE(SUM(b.quantity), 0) AS total_qty,
        COUNT(b.id) AS batch_count,
        MAX(b.date_received) AS last_received,
        MIN(CASE WHEN b.expiry_date IS NOT NULL AND b.expiry_date >= CURDATE() THEN b.expiry_date END) AS next_expiry_date,
        MAX(CASE WHEN b.expiry_date IS NOT NULL AND b.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS has_expired
        FROM medicines m
        LEFT JOIN medicine_categories c ON c.id = m.category_id
        LEFT JOIN medicine_batches b ON b.medicine_id = m.id AND b.quantity > 0
        WHERE m.is_active = 1';

$where = [];
$params = [];
if ($filterType !== 'all') {
    $where[] = 'CASE WHEN c.type = "first_aid" THEN "first_aid" ELSE "medicine" END = ?';
    $params[] = $filterType;
}
if ($where) $inventorySql .= ' AND ' . implode(' AND ', $where);
$inventorySql .= ' GROUP BY m.id, m.name, m.unit, m.low_stock_threshold, c.name, c.type
        ORDER BY m.name ASC';
$inventoryStmt = $pdo->prepare($inventorySql);
$inventoryStmt->execute($params);
$inventoryRows = $inventoryStmt->fetchAll();

$filteredInventory = [];
$today = date('Y-m-d');
foreach ($inventoryRows as $row) {
    $rowTotal = (int)($row['total_qty'] ?? 0);
    $threshold = max(1, (int)($row['low_stock_threshold'] ?? 1));
    $nextExpiry = $row['next_expiry_date'] ?? null;
    $hasExpired = (int)($row['has_expired'] ?? 0) === 1;
    $isExpiringSoon = $nextExpiry && strtotime($nextExpiry) >= strtotime($today) && strtotime($nextExpiry) <= strtotime('+30 days');

    $filteredInventory[] = $row;
}

require __DIR__ . '/../includes/layout_dashboard_start.php';
?>
<div class="row g-3">
    <div class="col">
        <div class="card mb-3 mt-3">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end audit-toolbar mb-2">
                    <div class="col">
                        <label class="form-label">Search inventory</label>
                        <input type="search" name="q" class="form-control form-control-sm" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Name or category..." aria-label="Search inventory items">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Filter</label>
                        <select name="stock" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Stock</option>
                            <option value="in" <?= (($_GET['stock'] ?? '') === 'in') ? 'selected' : '' ?>>In Stock</option>
                            <option value="low" <?= (($_GET['stock'] ?? '') === 'low') ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out" <?= (($_GET['stock'] ?? '') === 'out') ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col d-flex flex-wrap gap-2 justify-content-start">
                        
                        <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-search me-1"></i> Search</button>
                        <a href="<?= e(url('staff/inventory.php')) ?>" class="btn btn-sm btn-outline-secondary text-nowrap">Reset</a>
                        <button type="button" class="btn btn-sm btn-accent text-nowrap" data-bs-toggle="modal" data-bs-target="#stockInModal"><i class="bi bi-plus-lg me-1"></i> Add Stock</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card"><div class="table-responsive"><table id="inventoryTable" class="table table-hover mb-0 align-middle"><thead><tr><th>#</th><th>Item</th><th>Type</th><th class="text-end">Total Stock</th><th>Unit</th><th class="text-end">Total Pieces</th><th>Threshold</th><th>Status</th><th>Next Expiry</th><th>Last Received</th></tr></thead>
        <?php if (!$filteredInventory): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No products found.</td></tr>
        <?php else: foreach ($filteredInventory as $index => $row): ?>
            <?php
                $threshold = max(1, (int)($row['low_stock_threshold'] ?? 1));
                $qty = (int)($row['total_qty'] ?? 0);
                $nextExpiry = $row['next_expiry_date'] ?? null;
                $isExpiringSoon = $nextExpiry && strtotime($nextExpiry) >= strtotime($today) && strtotime($nextExpiry) <= strtotime('+30 days');
                $isExpired = (int)($row['has_expired'] ?? 0) === 1;
                if ($qty <= 0) {
                    $stockBadge = 'bg-secondary';
                    $statusLabel = 'Out of stock';
                } elseif ($qty <= floor($threshold * 0.25)) {
                    $stockBadge = 'bg-danger';
                    $statusLabel = 'Critical';
                } elseif ($qty <= floor($threshold * 0.5)) {
                    $stockBadge = 'bg-warning';
                    $statusLabel = 'Low';
                } else {
                    $stockBadge = 'bg-success';
                    $statusLabel = 'Healthy';
                }
            ?>
            <?php
            $totalPieces = $qty; // Default 1 piece per unit since pieces_per_unit column removed
            ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= e($row['medicine_name']) ?><?php if (!empty($row['category_name'])): ?> <small class="text-muted d-block"><?= e($row['category_name']) ?></small><?php endif; ?></td>
                <td><span class="badge bg-secondary"><?= $row['item_type'] === 'first_aid' ? 'First Aid / Wound Care' : 'Medicine' ?></span></td>
                <td class="text-end"><strong><?= $qty ?></strong></td>
                <td><?= e($row['unit'] ?: 'pcs') ?></td>
                <td class="text-end"><?= $totalPieces ?></td>
                <td><?= $threshold ?></td>
                <td><?php if ($isExpired): ?><span class="badge bg-secondary">Expired</span><?php elseif ($isExpiringSoon): ?><span class="badge bg-secondary">Expiring soon</span><?php else: ?><span class="badge <?= $stockBadge ?>"><?= e($statusLabel) ?></span><?php endif; ?></td>
                <td><?= $nextExpiry ? e(fmt_dt($nextExpiry, 'M d, Y')) : '—' ?></td>
                <td><?= !empty($row['last_received']) ? e(fmt_dt($row['last_received'], 'M d, Y')) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody></table></div></div>
    </div>
</div>
<div class="modal fade" id="stockInModal" tabindex="-1" aria-labelledby="stockInModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                <input type="hidden" name="action" value="receive">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockInModalLabel"><i class="bi bi-box-arrow-in-down me-1"></i> Stock In</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body compact-modal-body">
                    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                    <div class="mb-3"><label class="form-label">Medicine / First Aid Item *</label><select id="stockMedicineSelect" name="medicine_id" class="form-select form-select-sm" required><option value="">-- select item --</option><?php foreach ($medicines as $m): ?><option value="<?= (int)$m['id'] ?>" data-item-type="<?= e($m['type'] ?? 'medicine') ?>"><?= e($m['name']) ?><?= $m['category_name'] ? ' (' . e($m['category_name']) . ')' : '' ?></option><?php endforeach; ?></select></div>
                    <div class="row g-2 mb-3"><div class="col-6"><label class="form-label">Batch No.</label><input type="text" name="batch_no" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label">Lot No.</label><input type="text" name="lot_no" class="form-control form-control-sm"></div></div>
                    <div class="mb-3"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select form-select-sm"><option value="">-- none --</option><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="row g-2 mb-3"><div class="col-6"><label class="form-label">Quantity *</label><input type="number" name="quantity" class="form-control form-control-sm" min="3" value="3" required></div><div class="col-6"><label class="form-label">Unit Cost</label><input type="number" step="0.01" name="unit_cost" class="form-control form-control-sm"></div></div>
                    <div class="row g-2" id="expiryRowWrapper"><div class="col-6" id="stockExpiryField"><label class="form-label">Expiry Date</label><input type="date" id="stockExpiryInput" name="expiry_date" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label">Date Received</label><input type="date" name="date_received" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div></div>
                </div>
                <div class="modal-footer compact-modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Stock</button></div>
            </form>
        </div>
    </div>
</div>
<?php if ($errors): ?><script>document.addEventListener('DOMContentLoaded', function () { var modal = document.getElementById('stockInModal'); if (modal && window.bootstrap) new bootstrap.Modal(modal).show(); });</script><?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var stockMedicineSelect = document.getElementById('stockMedicineSelect');
    var expiryField = document.getElementById('stockExpiryField');
    var expiryInput = document.getElementById('stockExpiryInput');
    var stockModal = document.getElementById('stockInModal');

    function toggleExpiryVisibility() {
        if (!stockMedicineSelect || !expiryField || !expiryInput) return;
        var selected = stockMedicineSelect.options[stockMedicineSelect.selectedIndex];
        var itemType = selected && selected.dataset.itemType ? selected.dataset.itemType : 'medicine';
        var isFirstAid = itemType === 'first_aid';
        expiryField.style.display = isFirstAid ? 'none' : 'block';
        if (isFirstAid) {
            expiryInput.value = '';
            expiryInput.removeAttribute('required');
        }
    }

    if (stockMedicineSelect) {
        stockMedicineSelect.addEventListener('change', toggleExpiryVisibility);
    }
    if (stockModal) {
        stockModal.addEventListener('hidden.bs.modal', function () {
            var form = stockModal.querySelector('form');
            if (form) form.reset();
            if (stockMedicineSelect) {
                stockMedicineSelect.selectedIndex = 0;
            }
            toggleExpiryVisibility();
        });
    }
    toggleExpiryVisibility();
});
</script>
<?php require __DIR__ . '/../includes/layout_dashboard_end.php'; ?>
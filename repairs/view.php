<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/repairs/index.php'); exit; }

$db = getDB();
$job = $db->prepare("SELECT r.*, COALESCE(c.name,'Walk-in') as cname, COALESCE(c.phone,'') as cphone, COALESCE(c.email,'') as cemail, COALESCE(u.name,'Unassigned') as tech_name, COALESCE(cb.name,'') as cashier_name FROM repair_jobs r LEFT JOIN customers c ON r.customer_id=c.id LEFT JOIN users u ON r.assigned_to=u.id LEFT JOIN users cb ON r.cashier_id=cb.id WHERE r.id=?");
$job->execute([$id]);
$job = $job->fetch();
if (!$job) { header('Location: ' . BASE_URL . '/repairs/index.php'); exit; }
$jobBarcodeValue = trim((string)($job['barcode'] ?: $job['job_number']));
$jobBarcodeValue = preg_replace('/[\x00-\x1F\x7F]/u', '', $jobBarcodeValue);

$services    = $db->prepare("SELECT * FROM repair_job_services WHERE job_id=?");
$services->execute([$id]);
$services = $services->fetchAll();

$parts = $db->prepare("SELECT rp.*, p.code as pcode, COALESCE(u.name,'') as added_by_name FROM repair_job_parts rp LEFT JOIN products p ON rp.product_id=p.id LEFT JOIN users u ON rp.added_by=u.id WHERE rp.job_id=? ORDER BY rp.added_at DESC");
$parts->execute([$id]);
$parts = $parts->fetchAll();

$technicians = $db->query("SELECT id, name FROM users WHERE role IN ('technician','admin') AND status=1 ORDER BY name")->fetchAll();
$allParts    = $db->query("SELECT p.* FROM products p WHERE p.status=1 AND p.type='part' ORDER BY p.name")->fetchAll();

// Check if invoice exists
$invoice = $db->prepare("SELECT * FROM repair_invoices WHERE job_id=? LIMIT 1");
$invoice->execute([$id]);
$invoice = $invoice->fetch();

// Calculate totals
$serviceTotal = array_sum(array_column($services, 'price'));
$partsTotal   = array_sum(array_column($parts, 'total'));
$grandTotal   = $serviceTotal + $partsTotal;
$balanceDue   = $grandTotal - (float)$job['advance_payment'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_status':
            $newStatus = $_POST['status'] ?? $job['status'];
            $techId    = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $notes     = trim($_POST['internal_notes'] ?? '');
            $estimDel  = $_POST['estimated_delivery'] ?? null;
            $actualDel = ($newStatus === 'delivered') ? date('Y-m-d H:i:s') : null;

            $upd = $db->prepare("UPDATE repair_jobs SET status=?, assigned_to=?, internal_notes=?, estimated_delivery=?" . ($actualDel ? ", actual_delivery=NOW()" : "") . " WHERE id=?");
            $upd->execute([$newStatus, $techId, $notes, $estimDel ?: null, $id]);

            setFlash('success', 'Job status updated successfully.');
            break;

        case 'add_service':
            $svcId   = (int)($_POST['service_id'] ?? 0);
            $svcName = trim($_POST['service_name'] ?? '');
            $price   = abs((float)($_POST['service_price'] ?? 0));
            if ($svcName) {
                $db->prepare("INSERT INTO repair_job_services (job_id, service_id, service_name, price) VALUES (?,?,?,?)")
                   ->execute([$id, $svcId ?: null, $svcName, $price]);
                setFlash('success', 'Service added.');
            }
            break;

        case 'remove_service':
            $svcRowId = (int)($_POST['service_row_id'] ?? 0);
            $db->prepare("DELETE FROM repair_job_services WHERE id=? AND job_id=?")->execute([$svcRowId, $id]);
            setFlash('success', 'Service removed.');
            break;

        case 'add_part':
            requireAuth('admin', 'technician', 'cashier');
            $prodId  = (int)($_POST['product_id'] ?? 0);
            $qty     = max(1, (int)($_POST['qty'] ?? 1));
            $price   = abs((float)($_POST['unit_price'] ?? 0));

            if (!$prodId) { setFlash('error', 'Select a product.'); break; }
            $prod = $db->prepare("SELECT * FROM products WHERE id=?");
            $prod->execute([$prodId]);
            $prod = $prod->fetch();
            if (!$prod) { setFlash('error', 'Product not found.'); break; }
            if ($prod['stock_quantity'] < $qty) { setFlash('warning', "Insufficient stock. Available: {$prod['stock_quantity']}"); break; }

            $total = $price * $qty;
            $db->prepare("INSERT INTO repair_job_parts (job_id, product_id, product_name, product_code, quantity, unit_price, total, added_by) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$id, $prodId, $prod['name'], $prod['code'], $qty, $price, $total, $_SESSION['user_id']]);

            // Deduct stock
            $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id=?")->execute([$qty, $prodId]);
            logStockMovement($prodId, 'repair_use', -$qty, $id, 'repair_job', "Used in {$job['job_number']}");

            setFlash('success', "Part '{$prod['name']}' added and stock deducted.");
            break;

        case 'remove_part':
            $partRowId = (int)($_POST['part_row_id'] ?? 0);
            $partRow = $db->prepare("SELECT * FROM repair_job_parts WHERE id=? AND job_id=?");
            $partRow->execute([$partRowId, $id]);
            $partRow = $partRow->fetch();
            if ($partRow) {
                // Return stock
                $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id=?")->execute([$partRow['quantity'], $partRow['product_id']]);
                logStockMovement($partRow['product_id'], 'return', $partRow['quantity'], $id, 'repair_job', "Part removed from {$job['job_number']}");
                $db->prepare("DELETE FROM repair_job_parts WHERE id=?")->execute([$partRowId]);
                setFlash('success', 'Part removed and stock returned.');
            }
            break;

        case 'record_payment':
            requireAuth('admin', 'cashier');
            $inv = $db->prepare("SELECT * FROM repair_invoices WHERE job_id=? LIMIT 1");
            $inv->execute([$id]);
            $inv = $inv->fetch();
            if ($inv) {
                $newAmt    = abs((float)($_POST['new_payment'] ?? 0));
                $payMethod = $_POST['payment_method'] ?? 'cash';
                if ($newAmt > 0) {
                    $totalPaid   = (float)$inv['paid_amount'] + (float)$inv['advance_payment'] + $newAmt;
                    $newPaidAmt  = (float)$inv['paid_amount'] + $newAmt;
                    $newBalance  = max(0, (float)$inv['total'] - (float)$inv['advance_payment'] - $newPaidAmt);
                    $payStatus   = $totalPaid >= (float)$inv['total'] ? 'paid' : 'partial';
                    $db->prepare("UPDATE repair_invoices SET paid_amount=?, balance_due=?, payment_status=?, payment_method=? WHERE id=?")
                       ->execute([$newPaidAmt, $newBalance, $payStatus, $payMethod, $inv['id']]);
                    setFlash('success', 'Payment of ' . money($newAmt) . ' recorded. Status: ' . ucfirst($payStatus) . '.');
                } else {
                    setFlash('error', 'Enter a valid payment amount.');
                }
            } else {
                setFlash('error', 'No invoice found for this job.');
            }
            break;
    }

    header('Location: ' . BASE_URL . '/repairs/view.php?id=' . $id);
    exit;
}

$pageTitle = 'Repair Job — ' . $job['job_number'];
require_once __DIR__ . '/../includes/header.php';

$showTicket = isset($_GET['print_ticket']);
?>

<?php showFlash(); ?>

<!-- Page Header -->
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
  <div>
    <h4 class="d-flex align-items-center gap-3">
      <?= e($job['job_number']) ?>
      <?= jobStatusBadge($job['status']) ?>
      <?= priorityBadge($job['priority']) ?>
    </h4>
    <p><?= e($job['device_brand']) ?> <?= e($job['device_model']) ?> — <?= e($job['cname']) ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= BASE_URL ?>/repairs/job_ticket.php?id=<?= $id ?>&print=1" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-2"></i>Print Ticket</a>
    <a href="<?= BASE_URL ?>/repairs/job_ticket.php?id=<?= $id ?>&sticker=1&print=1" class="btn btn-outline-info" target="_blank"><i class="fas fa-tag me-2"></i>Print Sticker</a>
    <?php if ($job['status'] === 'completed' && !$invoice): ?>
    <a href="<?= BASE_URL ?>/repairs/invoice.php?id=<?= $id ?>" class="btn btn-success"><i class="fas fa-file-invoice me-2"></i>Generate Invoice</a>
    <?php elseif ($invoice): ?>
    <a href="<?= BASE_URL ?>/repairs/invoice.php?id=<?= $id ?>" class="btn btn-outline-success"><i class="fas fa-file-invoice me-2"></i>View Invoice</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/repairs/index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
  </div>
</div>

<div class="row g-4">
  <!-- LEFT: Job Info -->
  <div class="col-lg-8">

    <!-- Job + Device Info -->
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header"><i class="fas fa-user me-2 text-primary"></i>Customer</div>
          <div class="card-body">
            <div class="fw-bold fs-6 mb-1"><?= e($job['cname']) ?></div>
            <?php if ($job['cphone']): ?><div class="text-muted small"><i class="fas fa-phone me-2"></i><?= e($job['cphone']) ?></div><?php endif; ?>
            <?php if ($job['cemail']): ?><div class="text-muted small"><i class="fas fa-envelope me-2"></i><?= e($job['cemail']) ?></div><?php endif; ?>
            <hr class="my-2" style="border-color:#334155">
            <div class="small text-muted"><i class="fas fa-calendar me-2"></i>Job created: <?= niceDateTime($job['created_at']) ?></div>
            <?php if ($job['estimated_delivery']): ?>
            <div class="small text-muted mt-1"><i class="fas fa-clock me-2"></i>Est. delivery: <?= niceDate($job['estimated_delivery']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header"><i class="fas fa-mobile-alt me-2 text-info"></i>Device</div>
          <div class="card-body">
            <div class="fw-bold fs-6 mb-1"><?= e($job['device_brand']) ?> <?= e($job['device_model']) ?></div>
            <?php if ($job['device_color']): ?><div class="text-muted small"><i class="fas fa-palette me-2"></i><?= e($job['device_color']) ?></div><?php endif; ?>
            <?php if ($job['device_imei']): ?><div class="text-muted small"><i class="fas fa-barcode me-2"></i>IMEI: <?= e($job['device_imei']) ?></div><?php endif; ?>
            <?php if ($job['device_condition']): ?><div class="text-muted small"><i class="fas fa-info-circle me-2"></i><?= e($job['device_condition']) ?></div><?php endif; ?>
            <hr class="my-2" style="border-color:#334155">
            <div class="small"><strong>Complaint:</strong> <?= e($job['customer_complaint'] ?: '—') ?></div>
            <div class="small mt-1"><strong>Issue:</strong> <?= e($job['issue_description']) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Services -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-wrench me-2 text-success"></i>Services</span>
        <?php if (!in_array($job['status'], ['delivered','cancelled'])): ?>
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addServiceModal"><i class="fas fa-plus me-1"></i>Add</button>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if (empty($services)): ?>
        <div class="p-4 text-center text-muted small">No services added yet</div>
        <?php else: ?>
        <table class="table table-hover mb-0">
          <thead><tr><th>Service</th><th class="text-end">Price</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($services as $s): ?>
            <tr>
              <td><?= e($s['service_name']) ?></td>
              <td class="text-end fw-semibold"><?= money((float)$s['price']) ?></td>
              <td class="text-end">
                <?php if (!in_array($job['status'], ['delivered','cancelled'])): ?>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="remove_service">
                  <input type="hidden" name="service_row_id" value="<?= $s['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this service?')"><i class="fas fa-times"></i></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold"><td>Services Total</td><td class="text-end"><?= money($serviceTotal) ?></td><td></td></tr>
          </tfoot>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Parts Used -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-boxes me-2 text-warning"></i>Parts Used <small class="text-muted ms-2">(deducted from inventory)</small></span>
        <?php if (!in_array($job['status'], ['delivered','cancelled'])): ?>
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#addPartModal"><i class="fas fa-plus me-1"></i>Add Part</button>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if (empty($parts)): ?>
        <div class="p-4 text-center text-muted small">No parts added yet</div>
        <?php else: ?>
        <table class="table table-hover mb-0">
          <thead><tr><th>Part</th><th>Code</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th><th>Added By</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($parts as $p): ?>
            <tr>
              <td><?= e($p['product_name']) ?></td>
              <td class="text-muted small"><code><?= e($p['product_code'] ?? $p['pcode'] ?? '—') ?></code></td>
              <td class="text-center"><?= $p['quantity'] ?></td>
              <td class="text-end"><?= money((float)$p['unit_price']) ?></td>
              <td class="text-end fw-semibold"><?= money((float)$p['total']) ?></td>
              <td class="text-muted small"><?= e($p['added_by_name']) ?></td>
              <td>
                <?php if (!in_array($job['status'], ['delivered','cancelled'])): ?>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="remove_part">
                  <input type="hidden" name="part_row_id" value="<?= $p['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this part? Stock will be returned.')"><i class="fas fa-times"></i></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold"><td colspan="4">Parts Total</td><td class="text-end"><?= money($partsTotal) ?></td><td colspan="2"></td></tr>
          </tfoot>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- end col-lg-8 -->

  <!-- RIGHT COLUMN -->
  <div class="col-lg-4">

    <!-- Status Update -->
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-edit me-2"></i>Update Job</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="update_status">
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['pending'=>'Pending','in_progress'=>'In Progress','waiting_parts'=>'Waiting Parts','completed'=>'Completed','delivered'=>'Delivered','cancelled'=>'Cancelled'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= $job['status']===$v ? 'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Assign Technician</label>
            <select name="assigned_to" class="form-select select2">
              <option value="">— Unassigned —</option>
              <?php foreach ($technicians as $t): ?>
              <option value="<?= $t['id'] ?>" <?= $job['assigned_to']==$t['id'] ? 'selected':'' ?>><?= e($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Est. Delivery Date</label>
            <input type="date" name="estimated_delivery" class="form-control" value="<?= e($job['estimated_delivery'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Internal Notes</label>
            <textarea name="internal_notes" class="form-control" rows="2"><?= e($job['internal_notes'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100">Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Cost Summary -->
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-calculator me-2 text-success"></i>Cost Summary</div>
      <div class="card-body">
        <table class="w-100" style="font-size:13px">
          <tr><td class="text-muted py-1">Services</td><td class="text-end fw-semibold"><?= money($serviceTotal) ?></td></tr>
          <tr><td class="text-muted py-1">Parts</td><td class="text-end fw-semibold"><?= money($partsTotal) ?></td></tr>
          <tr style="border-top:1px solid #334155"><td class="py-2 fw-bold">Total</td><td class="text-end fw-bold fs-6"><?= money($grandTotal) ?></td></tr>
          <tr><td class="text-muted py-1">Advance Paid</td><td class="text-end" style="color:#86efac">− <?= money((float)$job['advance_payment']) ?></td></tr>
          <tr style="border-top:1px solid #334155"><td class="py-2 fw-bold">Balance Due</td><td class="text-end fw-bold" style="color:#fbbf24;font-size:15px"><?= money(max(0, $balanceDue)) ?></td></tr>
        </table>
      </div>
    </div>

    <?php if ($invoice): ?>
    <!-- Payment Collection -->
    <?php
      $invBalance  = (float)$invoice['balance_due'];
      $invTotal    = (float)$invoice['total'];
      $invAdvance  = (float)$invoice['advance_payment'];
      $invPaid     = (float)$invoice['paid_amount'];
      $invStatus   = $invoice['payment_status'];
      $statusColor = $invStatus === 'paid' ? '#86efac' : ($invStatus === 'partial' ? '#fbbf24' : '#f87171');
      $statusLabel = $invStatus === 'paid' ? '✓ Paid' : ($invStatus === 'partial' ? '◑ Partial' : '✗ Pending');
    ?>
    <div class="card mb-4" style="border-color:<?= $invBalance > 0 ? '#f59e0b' : '#22c55e' ?>">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-money-bill-wave me-2 text-warning"></i>Payment</span>
        <span style="color:<?= $statusColor ?>;font-size:12px;font-weight:700"><?= $statusLabel ?></span>
      </div>
      <div class="card-body">
        <table class="w-100 mb-3" style="font-size:12px">
          <tr><td class="text-muted py-1">Invoice</td><td class="text-end fw-semibold"><?= e($invoice['invoice_number']) ?></td></tr>
          <tr><td class="text-muted py-1">Total</td><td class="text-end fw-semibold"><?= money($invTotal) ?></td></tr>
          <tr><td class="text-muted py-1">Advance</td><td class="text-end" style="color:#86efac">− <?= money($invAdvance) ?></td></tr>
          <?php if ($invPaid > 0): ?>
          <tr><td class="text-muted py-1">Collected</td><td class="text-end" style="color:#86efac">− <?= money($invPaid) ?></td></tr>
          <?php endif; ?>
          <tr style="border-top:1px solid #334155">
            <td class="py-2 fw-bold">Balance Due</td>
            <td class="text-end fw-bold" style="color:<?= $invBalance > 0 ? '#fbbf24' : '#86efac' ?>;font-size:15px"><?= money($invBalance) ?></td>
          </tr>
        </table>

        <?php if ($invBalance > 0): ?>
        <form method="POST">
          <input type="hidden" name="action" value="record_payment">
          <div class="mb-2">
            <label class="form-label small text-muted">Collect Amount</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">Rs.</span>
              <input type="number" name="new_payment" class="form-control" placeholder="<?= number_format($invBalance, 2) ?>" step="0.01" min="0.01" max="<?= $invBalance ?>" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small text-muted">Method</label>
            <select name="payment_method" class="form-select form-select-sm">
              <option value="cash" <?= $invoice['payment_method']==='cash'?'selected':'' ?>>Cash</option>
              <option value="card" <?= $invoice['payment_method']==='card'?'selected':'' ?>>Card</option>
              <option value="transfer" <?= $invoice['payment_method']==='transfer'?'selected':'' ?>>Bank Transfer</option>
            </select>
          </div>
          <button type="submit" class="btn btn-warning w-100 btn-sm fw-bold">
            <i class="fas fa-check-circle me-2"></i>Record Payment
          </button>
        </form>
        <?php else: ?>
        <div class="text-center py-2" style="color:#86efac">
          <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
          <strong>Fully Paid</strong>
        </div>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/repairs/invoice.php?id=<?= $id ?>" class="btn btn-outline-secondary w-100 btn-sm mt-2">
          <i class="fas fa-file-invoice me-1"></i>View / Print Invoice
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Job Barcode Card -->
    <div class="card">
      <div class="card-header"><i class="fas fa-barcode me-2"></i>Job Barcode</div>
      <div class="card-body text-center">
        <svg id="jobBarcode"></svg>
        <div class="mt-2 small text-muted"><?= e($job['job_number']) ?></div>
        <div class="d-flex gap-2 mt-3">
          <a href="<?= BASE_URL ?>/repairs/job_ticket.php?id=<?= $id ?>&print=1" target="_blank" class="btn btn-sm btn-outline-secondary flex-grow-1"><i class="fas fa-print me-1"></i>Job Ticket</a>
          <a href="<?= BASE_URL ?>/repairs/job_ticket.php?id=<?= $id ?>&sticker=1&print=1" target="_blank" class="btn btn-sm btn-outline-info flex-grow-1"><i class="fas fa-tag me-1"></i>Sticker</a>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title"><i class="fas fa-wrench me-2 text-success"></i>Add Service</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <input type="hidden" name="action" value="add_service">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Service Description <span class="text-danger">*</span></label>
            <input type="text" name="service_name" id="modalServiceName" class="form-control" placeholder="e.g. Screen Replacement, Battery Change" required autofocus>
          </div>
          <div class="mb-0">
            <label class="form-label">Price <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" name="service_price" id="modalServicePrice" class="form-control" placeholder="0.00" step="0.01" min="0" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-success" type="submit"><i class="fas fa-plus me-1"></i>Add Service</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Part Modal -->
<div class="modal fade" id="addPartModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title"><i class="fas fa-boxes me-2 text-warning"></i>Add Part from Stock</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <input type="hidden" name="action" value="add_part">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Select Part <span class="text-danger">*</span></label>
            <select name="product_id" id="partSelect" class="form-select select2" required>
              <option value="">— Search parts inventory —</option>
              <?php foreach ($allParts as $p): ?>
              <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['stock_quantity'] ?>">
                <?= e($p['name']) ?> [<?= e($p['code']) ?>] — Stock: <?= $p['stock_quantity'] ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="partStockInfo" class="alert alert-info small d-none"></div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Quantity</label>
              <input type="number" name="qty" id="partQty" class="form-control" value="1" min="1" required>
            </div>
            <div class="col-6">
              <label class="form-label">Unit Price</label>
              <div class="input-group">
                <span class="input-group-text">Rs.</span>
                <input type="number" name="unit_price" id="partPrice" class="form-control" placeholder="0.00" step="0.01" min="0" required>
              </div>
            </div>
          </div>
          <div class="mt-3 p-2 rounded" style="background:#0f172a">
            <div class="d-flex justify-content-between small">
              <span class="text-muted">Part Total:</span>
              <span class="fw-bold" id="partTotal">Rs. 0.00</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-warning" type="submit"><i class="fas fa-plus me-1"></i>Add Part & Deduct Stock</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraScripts = "
<script>
JsBarcode('#jobBarcode', " . json_encode($jobBarcodeValue) . ", { format:'CODE128', width:2, height:50, displayValue:true, fontSize:11, margin:4, lineColor:'#000', background:'#fff' });

// Part modal
$('#partSelect').on('change', function() {
  const opt   = $(this).find(':selected');
  const price = parseFloat(opt.data('price')) || 0;
  const stock = parseInt(opt.data('stock')) || 0;
  $('#partPrice').val(price.toFixed(2));
  $('#partQty').attr('max', stock);
  if (opt.val()) {
    $('#partStockInfo').removeClass('d-none').text('Available stock: ' + stock + ' units');
  } else {
    $('#partStockInfo').addClass('d-none');
  }
  calcPartTotal();
});

function calcPartTotal() {
  const qty   = parseInt($('#partQty').val()) || 0;
  const price = parseFloat($('#partPrice').val()) || 0;
  $('#partTotal').text('Rs. ' + (qty * price).toFixed(2));
}
$('#partQty, #partPrice').on('input', calcPartTotal);

// Select2 in modals
$(document).ready(function() {
  $('#partSelect').select2({ theme:'bootstrap-5', dropdownParent: $('#addPartModal') });
  $('select.select2:not(#partSelect)').select2({ theme:'bootstrap-5' });
});
" . ($showTicket ? "$(document).ready(function() { setTimeout(() => { window.open('" . BASE_URL . "/repairs/job_ticket.php?id=$id&print=1', '_blank'); }, 800); });" : "") . "
</script>
";
require_once __DIR__ . '/../includes/footer.php';
?>

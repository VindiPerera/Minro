<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin', 'cashier');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/repairs/index.php'); exit; }

$db = getDB();
$job = $db->prepare("SELECT r.*, COALESCE(c.name,'Walk-in') as cname, COALESCE(c.phone,'') as cphone, COALESCE(c.email,'') as cemail FROM repair_jobs r LEFT JOIN customers c ON r.customer_id=c.id WHERE r.id=?");
$job->execute([$id]);
$job = $job->fetch();
if (!$job) { header('Location: ' . BASE_URL . '/repairs/index.php'); exit; }

$services = $db->prepare("SELECT * FROM repair_job_services WHERE job_id=?");
$services->execute([$id]);
$services = $services->fetchAll();

$parts = $db->prepare("SELECT * FROM repair_job_parts WHERE job_id=?");
$parts->execute([$id]);
$parts = $parts->fetchAll();

$serviceTotal = array_sum(array_column($services, 'price'));
$partsTotal   = array_sum(array_column($parts, 'total'));

// Check for existing invoice
$existingInv = $db->prepare("SELECT * FROM repair_invoices WHERE job_id=? LIMIT 1");
$existingInv->execute([$id]);
$existingInv = $existingInv->fetch();

$errors = [];
$invoice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingInv) {
    $discount  = abs((float)($_POST['discount'] ?? 0));
    $paidAmt   = abs((float)($_POST['paid_amount'] ?? 0));
    $payMethod = $_POST['payment_method'] ?? 'cash';
    $notes     = trim($_POST['notes'] ?? '');

    $subtotal = $serviceTotal + $partsTotal;
    $total    = max(0, $subtotal - $discount);
    $advance  = (float)$job['advance_payment'];
    $balance  = max(0, $total - $advance);
    $totalPaid = $advance + $paidAmt;
    $payStatus = $totalPaid >= $total ? 'paid' : ($totalPaid > 0 ? 'partial' : 'pending');

    if ($paidAmt < $balance && $payStatus !== 'partial') {
        // Allow partial payment
    }

    $invNum = generateRepairInvoiceNumber();

    $stmt = $db->prepare("INSERT INTO repair_invoices (invoice_number, job_id, subtotal, discount, total, advance_payment, balance_due, paid_amount, payment_method, payment_status, cashier_id, notes)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$invNum, $id, $subtotal, $discount, $total, $advance, $balance, $paidAmt, $payMethod, $payStatus, $_SESSION['user_id'], $notes]);
    $invId = (int)$db->lastInsertId();

    // Update job status to delivered
    $db->prepare("UPDATE repair_jobs SET status='delivered', actual_delivery=NOW() WHERE id=?")->execute([$id]);

    setFlash('success', "Invoice $invNum generated. Job marked as Delivered!");
    header('Location: ' . BASE_URL . '/repairs/invoice.php?id=' . $id . '&print=1');
    exit;
}

if ($existingInv) $invoice = $existingInv;

$settings    = getSettings();
$companyName = $settings['company_name']    ?? 'Minro Mobile Repair';
$companyAddr = $settings['company_address'] ?? '';
$companyPhone= $settings['company_phone']   ?? '';
$footerMsg   = $settings['receipt_footer']  ?? 'Thank you!';
$warranty    = $settings['repair_warranty_days'] ?? '30';

$isPrint = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Repair Invoice - <?= e($job['job_number']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
  body { background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', sans-serif; }
  .no-print { padding: 16px 20px; background: #1e293b; border-bottom: 1px solid #334155; display: flex; gap: 10px; align-items: center; }

  .invoice-wrap { max-width: 700px; margin: 30px auto; background: white; color: #111; border-radius: 12px; overflow: hidden; }
  .inv-header { background: #1e293b; color: white; padding: 24px 32px; display: flex; justify-content: space-between; align-items: flex-start; }
  .inv-body   { padding: 24px 32px; }
  .inv-footer { background: #f8f9fa; padding: 16px 32px; font-size: 12px; color: #666; text-align: center; }

  .inv-label  { font-size: 10px; text-transform: uppercase; letter-spacing: .8px; color: #94a3b8; }
  .inv-value  { font-size: 13px; font-weight: 600; color: #1e293b; }

  table.inv-table { width: 100%; font-size: 13px; }
  table.inv-table th { background: #f8f9fa; padding: 8px 10px; font-weight: 700; border-bottom: 2px solid #e2e8f0; color: #334155; }
  table.inv-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
  .inv-totals { width: 260px; margin-left: auto; }
  .inv-totals td { padding: 5px 0; font-size: 13px; color: #475569; }
  .inv-totals td:last-child { text-align: right; font-weight: 600; color: #1e293b; }
  .inv-totals .grand-total td { font-size: 18px; font-weight: 800; color: #1e293b; border-top: 2px solid #e2e8f0; padding-top: 8px; }
  .badge-paid { background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
  .badge-partial { background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
  .badge-pending { background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }

  .payment-form-wrap { max-width: 700px; margin: 20px auto; padding: 24px; background: #1e293b; border-radius: 12px; border: 1px solid #334155; }

  @media print {
    @page { size: 80mm auto; margin: 0mm; }

    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    body { background: white !important; color: #000 !important; font-family: 'Courier New', monospace !important; font-size: 11px !important; }

    .no-print, .payment-form-wrap { display: none !important; }

    .invoice-wrap {
      max-width: 80mm !important;
      width: 80mm !important;
      margin: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      overflow: visible !important;
    }

    /* Header: stack vertically instead of side-by-side */
    .inv-header {
      display: block !important;
      text-align: center !important;
      padding: 8px !important;
      background: #000 !important;
      color: #fff !important;
    }
    .inv-header > div { text-align: center !important; }
    .inv-header [style*="font-size:24px"] { font-size: 15px !important; letter-spacing: 1px !important; }
    .inv-header [style*="font-size:22px"] { font-size: 13px !important; }
    .inv-header [style*="font-size:11px"] { font-size: 9px !important; }
    .inv-header [style*="font-size:18px"] { font-size: 11px !important; }

    .inv-body { padding: 6px 8px !important; }
    .inv-footer { padding: 6px 8px !important; font-size: 9px !important; }

    /* Meta grid: stack to single column */
    .inv-body > div[style*="grid"] {
      display: block !important;
      margin-bottom: 8px !important;
    }
    .inv-body > div[style*="grid"] > div {
      text-align: left !important;
      margin-bottom: 4px !important;
    }
    .inv-label { font-size: 8px !important; letter-spacing: 0 !important; }
    .inv-value { font-size: 11px !important; }

    /* Barcode */
    #invBarcode { max-width: 68mm !important; height: 30px !important; }

    /* Tables: compact */
    table.inv-table th, table.inv-table td { padding: 3px 2px !important; font-size: 10px !important; }
    table.inv-table th { background: #eee !important; }

    /* Totals */
    .inv-totals { width: 100% !important; margin: 0 !important; }
    .inv-totals td { font-size: 11px !important; padding: 2px 0 !important; }
    .inv-totals .grand-total td { font-size: 13px !important; }

    /* Badges */
    .badge-paid, .badge-partial, .badge-pending { font-size: 10px !important; padding: 2px 6px !important; border-radius: 3px !important; }

    /* Dashed separator */
    hr { border-top: 1px dashed #000 !important; }
  }
</style>
</head>
<body>
<div class="no-print">
  <a href="<?= BASE_URL ?>/repairs/view.php?id=<?= $id ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Job</a>
  <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="fas fa-print me-1"></i>Print Invoice</button>
</div>

<?php if (!$invoice): ?>
<!-- Payment Form -->
<div class="payment-form-wrap">
  <h5 class="mb-3 text-light"><i class="fas fa-file-invoice me-2 text-success"></i>Generate Repair Invoice</h5>
  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><?= e($e) ?></div>
  <?php endforeach; ?>
  <form method="POST">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label text-muted small">Discount</label>
        <div class="input-group">
          <span class="input-group-text" style="background:#0f172a;border-color:#334155;color:#94a3b8">Rs.</span>
          <input type="number" name="discount" id="invDiscount" class="form-control" value="0" step="0.01" min="0" style="background:#0f172a;border-color:#334155;color:#e2e8f0">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label text-muted small">Amount Collected Now</label>
        <div class="input-group">
          <span class="input-group-text" style="background:#0f172a;border-color:#334155;color:#94a3b8">Rs.</span>
          <input type="number" name="paid_amount" id="invPaid" class="form-control" placeholder="Balance amount" step="0.01" min="0" style="background:#0f172a;border-color:#334155;color:#e2e8f0">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label text-muted small">Payment Method</label>
        <select name="payment_method" class="form-select" style="background:#0f172a;border-color:#334155;color:#e2e8f0">
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="transfer">Bank Transfer</option>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label text-muted small">Notes</label>
        <input type="text" name="notes" class="form-control" placeholder="Invoice notes (optional)" style="background:#0f172a;border-color:#334155;color:#e2e8f0">
      </div>
    </div>
    <div class="mt-3 p-3 rounded" style="background:#0f172a">
      <div class="d-flex justify-content-between mb-1 small text-muted"><span>Services:</span><span id="fSvc"><?= money($serviceTotal) ?></span></div>
      <div class="d-flex justify-content-between mb-1 small text-muted"><span>Parts:</span><span id="fParts"><?= money($partsTotal) ?></span></div>
      <div class="d-flex justify-content-between mb-1 small text-muted"><span>Discount:</span><span id="fDisc">Rs. 0.00</span></div>
      <div class="d-flex justify-content-between mb-1 small fw-bold text-light border-top pt-2" style="border-color:#334155!important"><span>Total:</span><span id="fTotal"><?= money($serviceTotal + $partsTotal) ?></span></div>
      <div class="d-flex justify-content-between mb-1 small text-muted"><span>Advance Paid:</span><span style="color:#86efac">− <?= money((float)$job['advance_payment']) ?></span></div>
      <div class="d-flex justify-content-between small fw-bold" style="color:#fbbf24"><span>Balance Due:</span><span id="fBalance"><?= money(max(0, $serviceTotal + $partsTotal - (float)$job['advance_payment'])) ?></span></div>
    </div>
    <button type="submit" class="btn btn-success w-100 mt-3 py-2 fw-bold">
      <i class="fas fa-check-circle me-2"></i>Generate Invoice & Mark Delivered
    </button>
  </form>
</div>
<?php endif; ?>

<!-- Invoice Document -->
<div class="invoice-wrap" id="invoicePrint">
  <div class="inv-header">
    <div>
      <div style="font-size:24px;font-weight:800;letter-spacing:2px"><?= e($companyName) ?></div>
      <?php if ($companyAddr): ?><div style="font-size:11px;opacity:.7"><?= e($companyAddr) ?></div><?php endif; ?>
      <?php if ($companyPhone): ?><div style="font-size:11px;opacity:.7"><?= e($companyPhone) ?></div><?php endif; ?>
    </div>
    <div style="text-align:right">
      <div style="font-size:11px;opacity:.7">REPAIR INVOICE</div>
      <?php if ($invoice): ?>
      <div style="font-size:22px;font-weight:800;color:#60a5fa"><?= e($invoice['invoice_number']) ?></div>
      <div style="margin-top:4px">
        <?php if ($invoice['payment_status']==='paid'): ?>
        <span class="badge-paid">✓ PAID</span>
        <?php elseif ($invoice['payment_status']==='partial'): ?>
        <span class="badge-partial">◑ PARTIAL</span>
        <?php else: ?>
        <span class="badge-pending">✗ PENDING</span>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div style="font-size:18px;font-weight:800;color:#60a5fa">DRAFT</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="inv-body">
    <!-- Meta -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
      <div>
        <div class="inv-label">Bill To</div>
        <div class="inv-value" style="font-size:15px"><?= e($job['cname']) ?></div>
        <?php if ($job['cphone']): ?><div class="inv-value"><?= e($job['cphone']) ?></div><?php endif; ?>
        <?php if ($job['cemail']): ?><div class="inv-value"><?= e($job['cemail']) ?></div><?php endif; ?>
      </div>
      <div style="text-align:right">
        <div class="inv-label">Job Number</div>
        <div class="inv-value" style="color:#2563eb"><?= e($job['job_number']) ?></div>
        <div class="inv-label mt-2">Device</div>
        <div class="inv-value"><?= e($job['device_brand']) ?> <?= e($job['device_model']) ?></div>
        <?php if ($invoice): ?>
        <div class="inv-label mt-2">Invoice Date</div>
        <div class="inv-value"><?= niceDateTime($invoice['created_at']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Barcode -->
    <div style="text-align:center;padding:10px;background:#f8f9fa;border-radius:8px;margin-bottom:20px">
      <svg id="invBarcode"></svg>
      <div style="font-size:10px;color:#64748b;margin-top:2px">Job: <?= e($job['job_number']) ?> &nbsp;|&nbsp; <?= e($job['device_brand']) ?> <?= e($job['device_model']) ?></div>
    </div>

    <hr style="border:none;border-top:1px dashed #ccc;margin:10px 0">

    <!-- Services -->
    <?php if (!empty($services)): ?>
    <div style="margin-bottom:20px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:8px">Repair Services</div>
      <table class="inv-table">
        <thead><tr><th>Service</th><th style="text-align:right">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($services as $s): ?>
          <tr><td><?= e($s['service_name']) ?></td><td style="text-align:right"><?= money((float)$s['price']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Parts -->
    <?php if (!empty($parts)): ?>
    <hr style="border:none;border-top:1px dashed #ccc;margin:10px 0">
    <div style="margin-bottom:20px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:8px">Replacement Parts</div>
      <table class="inv-table">
        <thead><tr><th>Part</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr></thead>
        <tbody>
          <?php foreach ($parts as $p): ?>
          <tr>
            <td><?= e($p['product_name']) ?></td>
            <td style="text-align:center"><?= $p['quantity'] ?></td>
            <td style="text-align:right"><?= money((float)$p['unit_price']) ?></td>
            <td style="text-align:right"><?= money((float)$p['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <hr style="border:none;border-top:1px dashed #ccc;margin:10px 0">

    <!-- Totals -->
    <?php
    $sub  = $serviceTotal + $partsTotal;
    $disc = $invoice ? (float)$invoice['discount'] : 0;
    $tot  = $invoice ? (float)$invoice['total'] : $sub;
    $adv  = (float)$job['advance_payment'];
    $bal  = $invoice ? (float)$invoice['balance_due'] : max(0, $tot - $adv);
    $paid = $invoice ? (float)$invoice['paid_amount'] : 0;
    ?>
    <table class="inv-totals">
      <tr><td>Services</td><td><?= money($serviceTotal) ?></td></tr>
      <tr><td>Parts</td><td><?= money($partsTotal) ?></td></tr>
      <?php if ($disc > 0): ?>
      <tr style="color:#dc2626"><td>Discount</td><td>− <?= money($disc) ?></td></tr>
      <?php endif; ?>
      <tr class="grand-total"><td>TOTAL</td><td><?= money($tot) ?></td></tr>
      <tr><td style="color:#16a34a">Advance Paid</td><td style="color:#16a34a">− <?= money($adv) ?></td></tr>
      <?php if ($invoice): ?>
      <tr><td>Collected Now</td><td>− <?= money($paid) ?></td></tr>
      <tr style="font-size:15px;font-weight:700;color:<?= $bal > 0 ? '#dc2626' : '#16a34a' ?>"><td>Balance Due</td><td><?= money($bal) ?></td></tr>
      <?php else: ?>
      <tr style="font-size:15px;font-weight:700;color:#d97706"><td>Balance Due</td><td><?= money($bal) ?></td></tr>
      <?php endif; ?>
    </table>

    <?php if ($invoice && $invoice['notes']): ?>
    <div style="margin-top:16px;padding:10px;background:#f8f9fa;border-radius:6px;font-size:12px;color:#475569">
      <strong>Note:</strong> <?= e($invoice['notes']) ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="inv-footer">
    <strong><?= e($companyName) ?></strong> | <?= e($companyPhone) ?><br>
    <small>Warranty: <?= $warranty ?> days from delivery date. This invoice is your warranty document. Keep it safe.</small>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
JsBarcode('#invBarcode', '<?= e($job['barcode'] ?: $job['job_number']) ?>', { format:'CODE128', width:1.4, height:40, displayValue:true, fontSize:10, lineColor:'#334155', background:'#f8f9fa' });

// Form calculations
const svc = <?= $serviceTotal ?>, parts = <?= $partsTotal ?>, adv = <?= (float)$job['advance_payment'] ?>;
function calcInv() {
  const disc = parseFloat(document.getElementById('invDiscount')?.value) || 0;
  const total = Math.max(0, svc + parts - disc);
  const balance = Math.max(0, total - adv);
  document.getElementById('fDisc').textContent = 'Rs. ' + disc.toFixed(2);
  document.getElementById('fTotal').textContent = 'Rs. ' + total.toFixed(2);
  document.getElementById('fBalance').textContent = 'Rs. ' + balance.toFixed(2);
  if (document.getElementById('invPaid') && !document.getElementById('invPaid').dataset.touched) {
    document.getElementById('invPaid').value = balance.toFixed(2);
  }
}
document.getElementById('invDiscount')?.addEventListener('input', calcInv);
document.getElementById('invPaid')?.addEventListener('focus', function() { this.dataset.touched = '1'; });
calcInv();

<?php if ($isPrint): ?>window.onload = function() { setTimeout(() => window.print(), 500); };<?php endif; ?>
</script>
</body>
</html>

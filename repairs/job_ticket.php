<?php
/**
 * Repair Job Ticket + Device Barcode Sticker
 * ?id=JOB_ID        → Full job ticket (print for customer)
 * ?id=JOB_ID&sticker=1 → Barcode sticker (attach to device)
 */
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$id      = (int)($_GET['id'] ?? 0);
$sticker = isset($_GET['sticker']);
$isPrint = isset($_GET['print']);
if (!$id) die('Invalid job ID');

$db = getDB();
$job = $db->prepare("SELECT r.*, COALESCE(c.name,'Walk-in') as cname, COALESCE(c.phone,'') as cphone, COALESCE(u.name,'—') as tech_name FROM repair_jobs r LEFT JOIN customers c ON r.customer_id=c.id LEFT JOIN users u ON r.assigned_to=u.id WHERE r.id=?");
$job->execute([$id]);
$job = $job->fetch();
if (!$job) die('Job not found');
$jobBarcodeValue = trim((string)($job['barcode'] ?: $job['job_number']));
$jobBarcodeValue = preg_replace('/[\x00-\x1F\x7F]/u', '', $jobBarcodeValue);

$services = $db->prepare("SELECT * FROM repair_job_services WHERE job_id=?");
$services->execute([$id]);
$services = $services->fetchAll();

$settings    = getSettings();
$companyName = $settings['company_name']    ?? 'Minro Mobile Repair';
$companyAddr = $settings['company_address'] ?? '';
$companyPhone= $settings['company_phone']   ?? '';
$warranty    = $settings['repair_warranty_days'] ?? '30';

$serviceTotal = array_sum(array_column($services, 'price'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $sticker ? 'Device Sticker' : 'Job Ticket' ?> - <?= e($job['job_number']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
<?php if ($sticker): ?>
@page { size: 50mm 25mm; margin: 0mm; }
html, body { width: 50mm; margin: 0; padding: 0; }
<?php else: ?>
@page { size: 80mm auto; margin: 2.5mm 3mm; }
<?php endif; ?>

* { box-sizing: border-box; }
body { background: #f8fafc; font-family: Arial, sans-serif; color: #000; }
.no-print { background: #1e293b; padding: 12px 20px; display: flex; gap: 10px; align-items: center; }
.print-hint { font-size: 11px; color: #94a3b8; margin-left: 8px; }
.quick-print-fab {
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 9999;
  border-radius: 999px;
  padding: 10px 14px;
  box-shadow: 0 8px 20px rgba(0,0,0,.25);
}

/* ── STICKER (direct thermal label) ───────────────────── */
.sticker-wrap { display: flex; justify-content: center; padding: 30px; }
.sticker { width: 50mm; height: 25mm; border: 1px dashed #888; padding: 1mm 1.5mm; background: #fff; text-align: center; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }
.sticker-company { font-size: 6pt; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 0.3mm; line-height: 1; }
.sticker-job     { font-size: 8.5pt; font-weight: 900; letter-spacing: 1px; line-height: 1; }
.sticker-device  { font-size: 7pt; font-weight: 700; line-height: 1; }
.sticker-cust    { font-size: 6.5pt; line-height: 1; }
.sticker-meta    { font-size: 6pt; line-height: 1; }
.sticker svg     { max-width: 100%; display: block; }

/* ── TICKET (80mm direct thermal) ─────────────────────── */
.ticket { width: 100%; max-width: 72mm; margin: 20px auto; background: #fff; padding: 2mm; font-size: 8.5pt; line-height: 1.4; overflow: hidden; }
.ticket-co-logo  { text-align: center; margin-bottom: 1mm; }
.ticket-co-name  { font-size: 13pt; font-weight: 900; text-align: center; letter-spacing: 1px; }
.ticket-co-sub   { font-size: 7.5pt; text-align: center; margin-bottom: 0.5mm; }
.t-divider       { border: none; border-top: 1px dashed #555; margin: 2mm 0; }
.t-title         { font-size: 9.5pt; font-weight: 700; text-align: center; letter-spacing: 2px; margin: 1.5mm 0; }
.t-job-no        { font-size: 16pt; font-weight: 900; text-align: center; margin: 1mm 0; }
.t-priority      { font-size: 8pt; font-weight: 700; text-align: center; margin-bottom: 1.5mm; }
.t-section-hd    { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #000; padding-bottom: 0.5mm; margin: 2mm 0 1mm; }
.t-row           { display: flex; justify-content: space-between; font-size: 8.5pt; margin: 0.8mm 0; }
.t-row .lbl      { color: #444; flex-shrink: 0; margin-right: 3mm; }
.t-row .val      { font-weight: 600; text-align: right; }
.t-svc-table     { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
.t-svc-table td  { padding: 0.8mm 0; }
.t-svc-table .r  { text-align: right; }
.t-total td      { border-top: 1px solid #000; font-weight: 700; padding-top: 1.5mm; }
.t-barcode-box   { text-align: center; margin: 2mm 0; }
.t-barcode-box svg { display: block; width: 100%; max-width: 64mm; height: auto; margin: 0 auto; }
.t-sig           { display: flex; justify-content: space-between; margin: 5mm 0 2mm; }
.t-sig-item      { flex: 1; text-align: center; }
.t-sig-line      { border-top: 1px solid #000; margin: 0 4mm 1.5mm; }
.t-sig-lbl       { font-size: 7pt; }
.t-footer        { text-align: center; font-size: 7.5pt; border-top: 1px dashed #555; padding-top: 2mm; margin-top: 2mm; line-height: 1.5; }

@media print {
  body { background: white; }
  .no-print { display: none; }
  .quick-print-fab { display: none !important; }
  body.is-sticker .sticker-wrap { padding: 0; display: block; }
  body.is-sticker .ticket-co-logo { display: none !important; }
  body.is-sticker .sticker {
    border: none;
    width: 50mm;
    height: 25mm;
    padding: 1.2mm 1.5mm;
    margin: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  body.is-sticker .sticker-company,
  body.is-sticker .sticker-job,
  body.is-sticker .sticker-device,
  body.is-sticker .sticker-cust,
  body.is-sticker .sticker-meta { line-height: 1 !important; }
  body.is-sticker .sticker svg { max-width: 100% !important; display: block !important; }
  body.is-ticket .ticket { width: 100%; max-width: 72mm; margin: 0 auto; padding: 0; overflow: hidden; }
  body.is-ticket .t-barcode-box svg { max-width: 64mm !important; }
}
</style>
</head>
<body class="<?= $sticker ? 'is-sticker' : 'is-ticket' ?>">

<div class="no-print">
  <a href="<?= BASE_URL ?>/repairs/view.php?id=<?= $id ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
  <?php if (!$sticker): ?>
  <a href="?id=<?= $id ?>&sticker=1<?= $isPrint ? '&print=1' : '' ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-tag me-1"></i>Print Sticker</a>
  <?php else: ?>
  <a href="?id=<?= $id ?><?= $isPrint ? '&print=1' : '' ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file me-1"></i>Print Ticket</a>
  <?php endif; ?>
  <button onclick="triggerPrintNow(true); return false;" class="btn btn-sm btn-primary"><i class="fas fa-print me-1"></i>Print</button>
  <span class="print-hint">If dialog shows Save, choose your printer in Destination.</span>
</div>

<button type="button" class="btn btn-primary quick-print-fab" onclick="triggerPrintNow(true); return false;">
  <i class="fas fa-print me-1"></i>Print Now
</button>

<?php if ($sticker): ?>
<!-- ============================================================
     DEVICE BARCODE STICKER (direct thermal label)
============================================================ -->
<div class="sticker-wrap">
  <div class="sticker">
    <div class="sticker-company"><?= e($companyName) ?></div>
    <div style="text-align: center; margin-top: 1mm; margin-bottom: 0.5mm;">
      <svg class="stickerBarcode" data-value="<?= e($jobBarcodeValue) ?>" style="display: inline-block; max-height: 14mm; max-width: 100%; object-fit: contain;"></svg>
      <div style="font-size: 8pt; font-weight: 900; margin-top: 0.5mm; line-height: 1;"><?= e($job['job_number']) ?> &mdash; <?= money((float)$job['estimated_cost']) ?></div>
    </div>
    <div class="sticker-device"><?= e($job['device_brand']) ?> <?= e($job['device_model']) ?></div>
    <div class="sticker-cust"><?= e($job['cname']) ?></div>
    <div class="sticker-meta"><?= date('d/m/Y', strtotime($job['created_at'])) ?></div>
    <?php if ($job['device_imei']): ?>
    <div class="sticker-meta">IMEI: <?= e($job['device_imei']) ?></div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ============================================================
     FULL JOB TICKET (80mm direct thermal)
============================================================ -->
<div class="ticket" id="ticketPrint">

  <div class="ticket-co-logo"><img src="<?= BASE_URL ?>/assets/logo.png" alt="logo" style="max-height:36px;max-width:120px;object-fit:contain"></div>
  <div class="ticket-co-name"><?= e($companyName) ?></div>
  <?php if ($companyAddr): ?><div class="ticket-co-sub"><?= e($companyAddr) ?></div><?php endif; ?>
  <?php if ($companyPhone): ?><div class="ticket-co-sub"><?= e($companyPhone) ?></div><?php endif; ?>

  <hr class="t-divider">
  <div class="t-title">REPAIR JOB TICKET</div>
  <div class="t-job-no"><?= e($job['job_number']) ?></div>
  <div class="t-priority"><?= strtoupper($job['priority'] ?? 'NORMAL') ?> PRIORITY</div>

  <div class="t-barcode-box">
    <svg id="ticketBarcode"></svg>
    <div style="font-size: 9.5pt; font-weight: 700; margin-top: 1mm;"><?= money((float)$job['estimated_cost']) ?></div>
  </div>

  <hr class="t-divider">
  <div class="t-section-hd">Customer</div>
  <div class="t-row"><span class="lbl">Name</span><span class="val"><?= e($job['cname']) ?></span></div>
  <?php if ($job['cphone']): ?><div class="t-row"><span class="lbl">Phone</span><span class="val"><?= e($job['cphone']) ?></span></div><?php endif; ?>
  <div class="t-row"><span class="lbl">Date In</span><span class="val"><?= date('d/m/Y', strtotime($job['created_at'])) ?></span></div>
  <?php if ($job['estimated_delivery']): ?><div class="t-row"><span class="lbl">Est. Ready</span><span class="val"><?= date('d/m/Y', strtotime($job['estimated_delivery'])) ?></span></div><?php endif; ?>

  <hr class="t-divider">
  <div class="t-section-hd">Device</div>
  <div class="t-row"><span class="lbl">Brand</span><span class="val"><?= e($job['device_brand']) ?></span></div>
  <div class="t-row"><span class="lbl">Model</span><span class="val"><?= e($job['device_model']) ?></span></div>
  <?php if ($job['device_imei']): ?><div class="t-row"><span class="lbl">IMEI</span><span class="val"><?= e($job['device_imei']) ?></span></div><?php endif; ?>
  <?php if ($job['device_color']): ?><div class="t-row"><span class="lbl">Color</span><span class="val"><?= e($job['device_color']) ?></span></div><?php endif; ?>
  <?php if ($job['device_condition']): ?><div class="t-row"><span class="lbl">Condition</span><span class="val"><?= e($job['device_condition']) ?></span></div><?php endif; ?>

  <hr class="t-divider">
  <div class="t-section-hd">Issue / Complaint</div>
  <div style="font-size:8.5pt;margin:1mm 0"><?= e($job['issue_description']) ?></div>
  <?php if ($job['customer_complaint'] && $job['customer_complaint'] !== $job['issue_description']): ?>
  <div style="font-size:8pt;font-style:italic">"<?= e($job['customer_complaint']) ?>"</div>
  <?php endif; ?>

  <?php if (!empty($services)): ?>
  <hr class="t-divider">
  <div class="t-section-hd">Services</div>
  <table class="t-svc-table">
    <?php foreach ($services as $s): ?>
    <tr><td><?= e($s['service_name']) ?></td><td class="r"><?= money((float)$s['price']) ?></td></tr>
    <?php endforeach; ?>
    <tr class="t-total"><td>TOTAL</td><td class="r"><?= money($serviceTotal) ?></td></tr>
  </table>
  <?php endif; ?>

  <hr class="t-divider">
  <div class="t-row"><span class="lbl">Advance Paid</span><span class="val"><?= money((float)$job['advance_payment']) ?></span></div>
  <div class="t-row"><span class="lbl">Balance Due</span><span class="val"><?= money(max(0,(float)$job['estimated_cost']-(float)$job['advance_payment'])) ?></span></div>
  <?php if ($job['assigned_to']): ?><div class="t-row"><span class="lbl">Technician</span><span class="val"><?= e($job['tech_name']) ?></span></div><?php endif; ?>

  <div class="t-sig">
    <div class="t-sig-item"><div class="t-sig-line"></div><div class="t-sig-lbl">Customer Signature</div></div>
    <div class="t-sig-item"><div class="t-sig-line"></div><div class="t-sig-lbl">Staff Signature</div></div>
  </div>

  <div class="t-footer">
    <?= e($companyName) ?><?= $companyPhone ? ' | ' . e($companyPhone) : '' ?><br>
    Warranty: <?= $warranty ?> days from delivery.<br>
    Items not collected within 30 days will be discarded.
  </div>

</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
const shouldAutoPrint = <?= $isPrint ? 'true' : 'false' ?>;
let printTriggered = false;

function triggerPrintNow(fromUserClick = false) {
  if (printTriggered) return;
  printTriggered = true;
  try { window.focus(); } catch (e) {}

  if (fromUserClick) {
    try {
      window.print();
      return;
    } catch (e) {}
  }

  setTimeout(function () {
    try {
      window.print();
    } catch (e) {
      try { document.execCommand('print', false, null); } catch (ignored) {}
    }
  }, 100);
}

window.addEventListener('afterprint', function () {
  printTriggered = false;
});

function renderBarcode() {
  function getAdaptiveBarcodeOptions(value, isSticker) {
    const len = String(value || '').trim().length;
    let width = isSticker ? 1.35 : 1.45;
    if (len > 10) width = isSticker ? 1.25 : 1.35;
    if (len > 14) width = isSticker ? 1.15 : 1.25;
    if (len > 18) width = isSticker ? 1.05 : 1.15;

    return {
      format: 'CODE128',
      width: width,
      height: isSticker ? 14 : 38,
      displayValue: !isSticker,
      fontSize: 8,
      textMargin: 2,
      margin: isSticker ? 1.5 : 2,
      lineColor: '#000',
      background: '#fff'
    };
  }

  <?php if ($sticker): ?>
  var el = document.querySelector('.stickerBarcode');
  if (el) {
    try {
      const value = el.getAttribute('data-value');
      JsBarcode(el, value, getAdaptiveBarcodeOptions(value, true));
    } catch(e) {}
  }
  <?php else: ?>
  try {
    const value = <?= json_encode($jobBarcodeValue) ?>;
    JsBarcode('#ticketBarcode', value, getAdaptiveBarcodeOptions(value, false));
  } catch(e) {}
  <?php endif; ?>
}

window.addEventListener('load', function () {
  renderBarcode();

  var printBtn = document.querySelector('.no-print .btn-primary');
  if (printBtn) {
    printBtn.addEventListener('click', function (event) {
      event.preventDefault();
      triggerPrintNow(true);
    });
  }

  if (shouldAutoPrint) {
    requestAnimationFrame(function () {
      setTimeout(function () { triggerPrintNow(false); }, 180);
    });
    setTimeout(function () { triggerPrintNow(false); }, 1200);
  }
});
</script>
</body>
</html>

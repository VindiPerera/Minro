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
if (!$id) die('Invalid job ID');

$db = getDB();
$job = $db->prepare("SELECT r.*, COALESCE(c.name,'Walk-in') as cname, COALESCE(c.phone,'') as cphone, COALESCE(u.name,'—') as tech_name FROM repair_jobs r LEFT JOIN customers c ON r.customer_id=c.id LEFT JOIN users u ON r.assigned_to=u.id WHERE r.id=?");
$job->execute([$id]);
$job = $job->fetch();
if (!$job) die('Job not found');

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
@page { size: 50mm 25mm; margin: 0; }
<?php else: ?>
@page { size: 80mm auto; margin: 3mm 4mm; }
<?php endif; ?>

* { box-sizing: border-box; }
body { background: #f8fafc; font-family: Arial, sans-serif; color: #000; }
.no-print { background: #1e293b; padding: 12px 20px; display: flex; gap: 10px; align-items: center; }

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
.ticket { width: 72mm; margin: 20px auto; background: #fff; padding: 2mm; font-size: 8.5pt; line-height: 1.4; }
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
.t-sig           { display: flex; justify-content: space-between; margin: 5mm 0 2mm; }
.t-sig-item      { flex: 1; text-align: center; }
.t-sig-line      { border-top: 1px solid #000; margin: 0 4mm 1.5mm; }
.t-sig-lbl       { font-size: 7pt; }
.t-footer        { text-align: center; font-size: 7.5pt; border-top: 1px dashed #555; padding-top: 2mm; margin-top: 2mm; line-height: 1.5; }

@media print {
  body { background: white; }
  .no-print { display: none; }
  <?php if ($sticker): ?>
  .sticker { border: none; width: 50mm; height: 25mm; padding: 1mm 1.5mm; margin: 0; }
  .sticker-wrap { padding: 0; }
  <?php else: ?>
  .ticket { width: 100%; margin: 0; padding: 0; }
  <?php endif; ?>
}
</style>
</head>
<body>

<div class="no-print">
  <a href="<?= BASE_URL ?>/repairs/view.php?id=<?= $id ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
  <?php if (!$sticker): ?>
  <a href="?id=<?= $id ?>&sticker=1" class="btn btn-sm btn-outline-info"><i class="fas fa-tag me-1"></i>Print Sticker</a>
  <?php else: ?>
  <a href="?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file me-1"></i>Print Ticket</a>
  <?php endif; ?>
  <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="fas fa-print me-1"></i>Print</button>
</div>

<?php if ($sticker): ?>
<!-- ============================================================
     DEVICE BARCODE STICKER (direct thermal label)
============================================================ -->
<div class="sticker-wrap">
  <div class="sticker">
    <div class="sticker-company"><?= e($companyName) ?></div>
    <svg class="stickerBarcode" data-value="<?= e($job['barcode'] ?: $job['job_number']) ?>"></svg>
    <div class="sticker-job"><?= e($job['job_number']) ?></div>
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

  <div class="ticket-co-name"><?= e($companyName) ?></div>
  <?php if ($companyAddr): ?><div class="ticket-co-sub"><?= e($companyAddr) ?></div><?php endif; ?>
  <?php if ($companyPhone): ?><div class="ticket-co-sub"><?= e($companyPhone) ?></div><?php endif; ?>

  <hr class="t-divider">
  <div class="t-title">REPAIR JOB TICKET</div>
  <div class="t-job-no"><?= e($job['job_number']) ?></div>
  <div class="t-priority"><?= strtoupper($job['priority'] ?? 'NORMAL') ?> PRIORITY</div>

  <div class="t-barcode-box">
    <svg id="ticketBarcode"></svg>
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
window.onload = function() {
  <?php if ($sticker): ?>
  var el = document.querySelector('.stickerBarcode');
  if (el) {
    try { JsBarcode(el, el.getAttribute('data-value'), { format:'CODE128', width:1.1, height:18, displayValue:false, margin:0, lineColor:'#000', background:'#fff' }); } catch(e) {}
  }
  <?php else: ?>
  try { JsBarcode('#ticketBarcode', '<?= e($job['job_number']) ?>', { format:'CODE128', width:1.5, height:40, displayValue:true, fontSize:9, margin:2, lineColor:'#000', background:'#fff' }); } catch(e) {}
  <?php endif; ?>
};
</script>
</body>
</html>

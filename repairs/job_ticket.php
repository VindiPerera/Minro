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
  body { background: #f1f5f9; font-family: Arial, sans-serif; }
  .no-print { background: #1e293b; padding: 12px 20px; display: flex; gap: 10px; align-items: center; }

  /* ---- JOB TICKET ---- */
  .ticket { width: 200mm; margin: 20px auto; background: white; border: 1px solid #e2e8f0; }
  .ticket-header { background: #1e293b; color: white; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; }
  .ticket-body { padding: 20px 24px; }
  .ticket-section { margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px dashed #e2e8f0; }
  .ticket-section:last-child { border-bottom: none; margin-bottom: 0; }
  .section-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 8px; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .info-item label { font-size: 10px; color: #94a3b8; display: block; }
  .info-item span  { font-size: 13px; font-weight: 600; color: #1e293b; }
  .ticket-footer { background: #f8f9fa; padding: 14px 24px; text-align: center; font-size: 11px; color: #666; border-top: 1px dashed #e2e8f0; }
  .priority-urgent  { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .priority-express { background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .priority-normal  { background: #f1f5f9; color: #64748b; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .barcode-box { text-align: center; padding: 12px; background: white; }
  .services-list { font-size: 12px; }
  .services-list tr td { padding: 3px 0; }

  /* ---- STICKER ---- */
  .sticker-page { display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
  .sticker { width: 60mm; border: 2px dashed #334155; padding: 10px; text-align: center; background: white; margin: 8px; display: inline-block; vertical-align: top; }
  .sticker-brand { font-size: 18px; font-weight: 800; letter-spacing: 1px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 6px; }
  .sticker-job   { font-size: 14px; font-weight: 700; color: #2563eb; }
  .sticker-device{ font-size: 11px; color: #475569; }
  .stickers-wrap { display: flex; flex-wrap: wrap; justify-content: center; padding: 20px; }

  @media print {
    body { background: white; }
    .no-print { display: none; }
    .ticket { width: 100%; margin: 0; border: none; box-shadow: none; }
    .sticker-page { min-height: auto; padding: 0; }
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
     DEVICE BARCODE STICKER (small label to attach to device)
============================================================ -->
<div class="stickers-wrap">
  <?php for ($i = 0; $i < 4; $i++): ?>
  <div class="sticker">
    <div class="sticker-brand"><?= e($companyName) ?></div>
    <svg class="stickerBarcode" data-value="<?= e($job['job_number']) ?>"></svg>
    <div class="sticker-job"><?= e($job['job_number']) ?></div>
    <div class="sticker-device"><?= e($job['device_brand']) ?> <?= e($job['device_model']) ?></div>
    <div style="font-size:10px;color:#94a3b8;margin-top:4px"><?= date('d/m/Y', strtotime($job['created_at'])) ?></div>
    <?php if ($job['device_imei']): ?>
    <div style="font-size:9px;color:#94a3b8">IMEI: <?= e($job['device_imei']) ?></div>
    <?php endif; ?>
  </div>
  <?php endfor; ?>
</div>

<?php else: ?>
<!-- ============================================================
     FULL JOB TICKET (A5/A4 for customer)
============================================================ -->
<div class="ticket" id="ticketPrint">
  <div class="ticket-header">
    <div>
      <div style="font-size:22px;font-weight:800;letter-spacing:2px"><?= e($companyName) ?></div>
      <?php if ($companyAddr): ?><div style="font-size:11px;opacity:.7"><?= e($companyAddr) ?></div><?php endif; ?>
      <?php if ($companyPhone): ?><div style="font-size:11px;opacity:.7"><i class="fas fa-phone me-1"></i><?= e($companyPhone) ?></div><?php endif; ?>
    </div>
    <div style="text-align:right">
      <div style="font-size:11px;opacity:.7">REPAIR JOB TICKET</div>
      <div style="font-size:22px;font-weight:800;color:#60a5fa"><?= e($job['job_number']) ?></div>
      <div><?php
        if ($job['priority'] === 'express') echo '<span class="priority-express">⚡ EXPRESS</span>';
        elseif ($job['priority'] === 'urgent') echo '<span class="priority-urgent">⚠ URGENT</span>';
        else echo '<span class="priority-normal">NORMAL</span>';
      ?></div>
    </div>
  </div>

  <div class="ticket-body">

    <!-- Barcode -->
    <div class="barcode-box mb-3" style="border:1px solid #e2e8f0;border-radius:8px">
      <svg id="ticketBarcode"></svg>
      <div style="font-size:12px;color:#64748b;font-weight:600"><?= e($job['job_number']) ?></div>
    </div>

    <!-- Customer & Date -->
    <div class="ticket-section">
      <div class="info-grid">
        <div class="info-item"><label>Customer Name</label><span><?= e($job['cname']) ?></span></div>
        <div class="info-item"><label>Phone</label><span><?= e($job['cphone'] ?: '—') ?></span></div>
        <div class="info-item"><label>Date Received</label><span><?= date('d M Y', strtotime($job['created_at'])) ?></span></div>
        <div class="info-item"><label>Est. Delivery</label><span><?= $job['estimated_delivery'] ? date('d M Y', strtotime($job['estimated_delivery'])) : 'TBD' ?></span></div>
      </div>
    </div>

    <!-- Device -->
    <div class="ticket-section">
      <div class="section-title">Device Details</div>
      <div class="info-grid">
        <div class="info-item"><label>Brand</label><span><?= e($job['device_brand']) ?></span></div>
        <div class="info-item"><label>Model</label><span><?= e($job['device_model']) ?></span></div>
        <?php if ($job['device_imei']): ?><div class="info-item"><label>IMEI / Serial</label><span><?= e($job['device_imei']) ?></span></div><?php endif; ?>
        <?php if ($job['device_color']): ?><div class="info-item"><label>Color</label><span><?= e($job['device_color']) ?></span></div><?php endif; ?>
        <?php if ($job['device_condition']): ?><div class="info-item" style="grid-column:span 2"><label>Condition</label><span><?= e($job['device_condition']) ?></span></div><?php endif; ?>
      </div>
    </div>

    <!-- Issue -->
    <div class="ticket-section">
      <div class="section-title">Issue / Complaint</div>
      <div style="font-size:13px;color:#1e293b"><?= e($job['issue_description']) ?></div>
      <?php if ($job['customer_complaint'] && $job['customer_complaint'] !== $job['issue_description']): ?>
      <div style="font-size:12px;color:#64748b;margin-top:4px"><em>"<?= e($job['customer_complaint']) ?>"</em></div>
      <?php endif; ?>
    </div>

    <!-- Services -->
    <?php if (!empty($services)): ?>
    <div class="ticket-section">
      <div class="section-title">Services Requested</div>
      <table class="services-list w-100">
        <?php foreach ($services as $s): ?>
        <tr><td><?= e($s['service_name']) ?></td><td style="text-align:right;font-weight:600"><?= money((float)$s['price']) ?></td></tr>
        <?php endforeach; ?>
        <tr style="border-top:1px solid #e2e8f0;font-weight:700"><td>Estimated Total</td><td style="text-align:right"><?= money($serviceTotal) ?></td></tr>
      </table>
    </div>
    <?php endif; ?>

    <!-- Payment Info -->
    <div class="ticket-section">
      <div class="info-grid">
        <div class="info-item"><label>Advance Paid</label><span style="color:#16a34a"><?= money((float)$job['advance_payment']) ?></span></div>
        <div class="info-item"><label>Balance Due</label><span style="color:#dc2626"><?= money(max(0, (float)$job['estimated_cost'] - (float)$job['advance_payment'])) ?></span></div>
        <?php if ($job['assigned_to']): ?>
        <div class="info-item"><label>Assigned Technician</label><span><?= e($job['tech_name']) ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Customer Signature -->
    <div class="ticket-section" style="border-bottom:none">
      <div class="d-flex justify-content-between" style="margin-top:10px">
        <div style="text-align:center;flex:1">
          <div style="border-top:1px solid #334155;margin:0 20px 4px;"></div>
          <div style="font-size:10px;color:#94a3b8">Customer Signature</div>
        </div>
        <div style="text-align:center;flex:1">
          <div style="border-top:1px solid #334155;margin:0 20px 4px;"></div>
          <div style="font-size:10px;color:#94a3b8">Staff Signature</div>
        </div>
      </div>
    </div>

  </div>

  <div class="ticket-footer">
    <strong><?= e($companyName) ?></strong> | <?= e($companyPhone) ?><br>
    <small>Keep this ticket safe. Warranty: <?= $warranty ?> days from delivery. Items not collected within 30 days will be discarded.</small>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
window.onload = function() {
  <?php if ($sticker): ?>
  document.querySelectorAll('.stickerBarcode').forEach(function(el) {
    try { JsBarcode(el, el.getAttribute('data-value'), { format:'CODE128', width:1.5, height:40, displayValue:false, margin:2, lineColor:'#000', background:'#fff' }); } catch(e) {}
  });
  <?php else: ?>
  try { JsBarcode('#ticketBarcode', '<?= e($job['job_number']) ?>', { format:'CODE128', width:2, height:55, displayValue:false, margin:4, lineColor:'#000', background:'#fff' }); } catch(e) {}
  <?php endif; ?>
};
</script>
</body>
</html>

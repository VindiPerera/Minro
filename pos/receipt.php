<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/pos/index.php'); exit; }

$db = getDB();
$sale = $db->prepare("SELECT s.*, COALESCE(c.name,'Walk-in') as cname, COALESCE(c.phone,'') as cphone, COALESCE(u.name,'') as cashier_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id LEFT JOIN users u ON s.cashier_id=u.id WHERE s.id=?");
$sale->execute([$id]);
$sale = $sale->fetch();
if (!$sale) { header('Location: ' . BASE_URL . '/pos/index.php'); exit; }

$items = $db->prepare("SELECT * FROM sale_items WHERE sale_id=?");
$items->execute([$id]);
$items = $items->fetchAll();

$settings = getSettings();
$company  = $settings['company_name']    ?? 'Minro Mobile Repair';
$addr     = $settings['company_address'] ?? '';
$phone    = $settings['company_phone']   ?? '';
$footer   = $settings['receipt_footer']  ?? 'Thank you for your purchase!';

$isPrint  = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt - <?= e($sale['invoice_number']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
  body { background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', Arial, sans-serif; }
  .receipt-wrap { max-width: 380px; margin: 40px auto; background: white; color: #000; overflow: hidden; font-family: Arial, Helvetica, sans-serif; font-size: 14px; padding: 20px; box-sizing: border-box; }
  .receipt-header { text-align: center; margin-bottom: 10px; }
  .receipt-logo { max-height: 80px; margin-bottom: 5px; }
  .company-name { font-size: 26px; font-weight: 900; letter-spacing: 1px; margin-bottom: 2px; }
  .company-contact { font-size: 14px; line-height: 1.4; margin-bottom: 8px; }
  
  .dashed { border-top: 1px dashed #000; margin: 12px 0; }
  .solid { border-top: 2px solid #000; margin: 12px 0; }
  
  .receipt-title { text-align: center; font-size: 18px; font-weight: 700; letter-spacing: 2px; margin: 10px 0; }
  .invoice-number { text-align: center; font-size: 28px; font-weight: 900; margin-bottom: 5px; }
  
  .section-label { font-size: 16px; font-weight: 700; letter-spacing: 1px; margin-bottom: 8px; }
  
  table { width: 100%; border-collapse: collapse; }
  td { padding: 4px 0; vertical-align: top; }
  td.label { color: #555; width: 40%; }
  td.value { font-weight: 700; text-align: right; }
  
  .items-table { margin-bottom: 15px; }
  .items-table th { padding: 4px 0; font-weight: 700; text-align: left; }
  .items-table td.qty { text-align: center; }
  .items-table td.price, .items-table th.price { text-align: right; }
  .items-table td.total, .items-table th.total { text-align: right; font-weight: 700; }
  
  .totals-table td.label { color: #555; }
  .total-row { font-weight: 900; font-size: 16px; }
  
  .signature-area { display: flex; justify-content: space-between; margin-top: 40px; }
  .sig-line { border-top: 1px solid #000; width: 45%; text-align: center; padding-top: 5px; font-size: 12px; }
  
  .receipt-footer { text-align: center; font-size: 12px; margin-top: 15px; line-height: 1.5; }
  
  .no-print-bar { text-align: center; padding: 20px; }
  @media print {
    body { background: white; margin: 0; padding: 0; }
    .no-print-bar { display: none !important; }
    .receipt-wrap { margin: 0 auto; box-shadow: none; max-width: 80mm; padding: 0; }
  }
</style>
</head>
<body>
<div class="no-print-bar">
  <a href="<?= BASE_URL ?>/pos/index.php" class="btn btn-sm btn-secondary me-2"><i class="fas fa-arrow-left me-1"></i>Back to POS</a>
  <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="fas fa-print me-1"></i>Print Receipt</button>
</div>

<div class="receipt-wrap" id="receiptPrint">
  <div class="receipt-header">
    <img src="<?= BASE_URL ?>/assets/logo.png" alt="logo" class="receipt-logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/150x50?text=LOGO';">
    <div class="company-name"><?= e($company) ?></div>
    <div class="company-contact">
      <?php if ($addr): ?><?= e($addr) ?><br><?php endif; ?>
      <?php if ($phone): ?><?= e($phone) ?><br><?php endif; ?>
    </div>
  </div>

  <div class="dashed"></div>
  <div class="receipt-title" style="font-size: 20px; margin-bottom: 5px;">SALES RECEIPT</div>
  <div class="invoice-number" style="font-size: 22px; letter-spacing: 1px;"><?= e($sale['invoice_number']) ?></div>
  <div class="dashed"></div>

  <div style="text-align: center; font-size: 14px; margin-bottom: 10px;">
    Cashier: <strong><?= e($sale['cashier_name']) ?></strong>
  </div>

  <div class="section-label">CUSTOMER</div>
  <table>
    <tr><td class="label">Name</td><td class="value"><?= e($sale['cname']) ?></td></tr>
    <?php if ($sale['cphone']): ?><tr><td class="label">Phone</td><td class="value"><?= e($sale['cphone']) ?></td></tr><?php endif; ?>
    <tr><td class="label">Date</td><td class="value"><?= niceDateTime($sale['sale_date']) ?></td></tr>
  </table>

  <div class="dashed"></div>
  <div class="section-label">PRODUCTS</div>
  <div class="solid" style="border-top: 2px solid #000; margin-bottom: 10px; margin-top: 0;"></div>
  
  <table class="items-table">
    <thead>
      <tr>
        <th style="width: 35%; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Item</th>
        <th style="width: 15%; text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Qty</th>
        <th style="width: 25%; text-align: right; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Price</th>
        <th style="width: 25%; text-align: right; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Total</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
    <tr>
      <td style="font-weight: 700; padding: 4px 0; word-break: break-word;"><?= e($item['product_name']) ?></td>
      <td style="text-align: center; padding: 4px 0;"><?= $item['quantity'] ?></td>
      <td style="text-align: right; padding: 4px 0; white-space: nowrap;"><?= money((float)$item['unit_price']) ?></td>
      <td style="text-align: right; padding: 4px 0; white-space: nowrap;"><?= money((float)$item['total']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="dashed"></div>
  <table class="totals-table">
    <tr><td class="label" style="font-size: 15px;">Subtotal</td><td class="value" style="font-size: 15px;"><?= money((float)$sale['subtotal']) ?></td></tr>
    <?php if ((float)$sale['discount'] > 0): ?>
    <tr><td class="label" style="font-size: 15px;">Discount</td><td class="value" style="font-size: 15px;">− <?= money((float)$sale['discount']) ?></td></tr>
    <?php endif; ?>
    <?php if ((float)$sale['tax'] > 0): ?>
    <tr><td class="label" style="font-size: 15px;">Tax</td><td class="value" style="font-size: 15px;"><?= money((float)$sale['tax']) ?></td></tr>
    <?php endif; ?>
  </table>
  
  <div class="dashed"></div>
  <table class="totals-table" style="margin: 8px 0;">
    <tr class="total-row"><td class="label" style="color: #000; font-size: 18px;">TOTAL</td><td class="value" style="font-size: 18px; font-weight: 900;"><?= money((float)$sale['total']) ?></td></tr>
  </table>
  
  <div class="dashed"></div>
  <table class="totals-table">
    <tr><td class="label" style="font-weight: normal; color: #555;">Paid (<?= ucfirst($sale['payment_method']) ?>)</td><td class="value" style="font-weight: normal;"><?= money((float)$sale['paid_amount']) ?></td></tr>
    <tr><td class="label" style="font-weight: normal; color: #555;">Change</td><td class="value" style="font-weight: normal;"><?= money((float)$sale['change_amount']) ?></td></tr>
  </table>

  <div class="dashed" style="margin-top: 20px;"></div>
  
  <?php if ($sale['notes']): ?>
  <div style="font-size: 14px; text-align: center; margin-bottom: 20px;">
    <strong>Warranty Period:</strong> <?= e($sale['notes']) ?>
  </div>
  <?php endif; ?>

  <div class="receipt-footer">
    <?= e($company) ?> | <?= e($phone) ?><br>
    <?= e($footer) ?><br>
    Items are non-refundable after 7 days.<br><br>
    <strong>Powered by JAAN Network</strong>
  </div>
</div>

<script>
  <?php if ($isPrint): ?>window.onload = function() { window.print(); };<?php endif; ?>
</script>
</body>
</html>

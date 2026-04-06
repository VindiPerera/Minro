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
  body { background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', sans-serif; }
  .receipt-wrap { max-width: 380px; margin: 40px auto; background: white; color: #111; border-radius: 12px; overflow: hidden; font-family: 'Courier New', monospace; font-size: 13px; }
  .receipt-header { background: #1e293b; color: white; padding: 20px; text-align: center; }
  .receipt-body { padding: 20px; }
  .receipt-footer { background: #f8f9fa; padding: 14px 20px; text-align: center; font-size: 11px; color: #666; }
  .dashed { border-top: 1.5px dashed #ccc; margin: 12px 0; }
  .total-row { font-weight: 700; font-size: 15px; }
  table { width: 100%; }
  td { padding: 3px 0; }
  .no-print-bar { text-align: center; padding: 20px; }
  @media print {
    body { background: white; }
    .no-print-bar { display: none; }
    .receipt-wrap { margin: 0; border-radius: 0; box-shadow: none; max-width: 80mm; }
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
    <div style="font-size:22px;font-weight:800;letter-spacing:2px"><?= e($company) ?></div>
    <?php if ($addr): ?><div style="font-size:11px;opacity:.8"><?= e($addr) ?></div><?php endif; ?>
    <?php if ($phone): ?><div style="font-size:11px;opacity:.8"><?= e($phone) ?></div><?php endif; ?>
  </div>

  <div class="receipt-body">
    <div style="text-align:center;margin-bottom:12px">
      <svg data-barcode="<?= e($sale['invoice_number']) ?>"></svg>
    </div>

    <table>
      <tr><td style="color:#666">Invoice:</td><td style="text-align:right;font-weight:700"><?= e($sale['invoice_number']) ?></td></tr>
      <tr><td style="color:#666">Date:</td><td style="text-align:right"><?= niceDateTime($sale['sale_date']) ?></td></tr>
      <tr><td style="color:#666">Customer:</td><td style="text-align:right"><?= e($sale['cname']) ?></td></tr>
      <?php if ($sale['cphone']): ?><tr><td style="color:#666">Phone:</td><td style="text-align:right"><?= e($sale['cphone']) ?></td></tr><?php endif; ?>
      <tr><td style="color:#666">Cashier:</td><td style="text-align:right"><?= e($sale['cashier_name']) ?></td></tr>
    </table>

    <div class="dashed"></div>
    <table>
      <tr style="font-weight:700;border-bottom:1px solid #ddd">
        <td>Item</td><td style="text-align:center">Qty</td><td style="text-align:right">Price</td><td style="text-align:right">Total</td>
      </tr>
      <tr><td colspan="4" style="padding-bottom:4px"></td></tr>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= e($item['product_name']) ?></td>
        <td style="text-align:center"><?= $item['quantity'] ?></td>
        <td style="text-align:right"><?= money((float)$item['unit_price']) ?></td>
        <td style="text-align:right"><?= money((float)$item['total']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <div class="dashed"></div>
    <table>
      <tr><td style="color:#666">Subtotal</td><td style="text-align:right"><?= money((float)$sale['subtotal']) ?></td></tr>
      <?php if ((float)$sale['discount'] > 0): ?>
      <tr><td style="color:#666">Discount</td><td style="text-align:right;color:#dc2626">− <?= money((float)$sale['discount']) ?></td></tr>
      <?php endif; ?>
      <?php if ((float)$sale['tax'] > 0): ?>
      <tr><td style="color:#666">Tax</td><td style="text-align:right"><?= money((float)$sale['tax']) ?></td></tr>
      <?php endif; ?>
      <tr class="total-row"><td>TOTAL</td><td style="text-align:right"><?= money((float)$sale['total']) ?></td></tr>
      <div class="dashed" style="margin:6px 0"></div>
      <tr><td style="color:#666">Paid (<?= ucfirst($sale['payment_method']) ?>)</td><td style="text-align:right"><?= money((float)$sale['paid_amount']) ?></td></tr>
      <tr><td style="color:#666">Change</td><td style="text-align:right"><?= money((float)$sale['change_amount']) ?></td></tr>
    </table>

    <?php if ($sale['notes']): ?>
    <div class="dashed"></div>
    <div style="font-size:11px;color:#666"><strong>Note:</strong> <?= e($sale['notes']) ?></div>
    <?php endif; ?>
  </div>

  <div class="receipt-footer">
    <div style="font-weight:700;margin-bottom:4px"><?= e($footer) ?></div>
    <div>Items are non-refundable after 7 days</div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
  JsBarcode("[data-barcode]", "<?= e($sale['invoice_number']) ?>", { format:'CODE128', width:1.5, height:40, displayValue:true, fontSize:10, lineColor:'#000', background:'#fff' });
  <?php if ($isPrint): ?>window.onload = function() { window.print(); };<?php endif; ?>
</script>
</body>
</html>

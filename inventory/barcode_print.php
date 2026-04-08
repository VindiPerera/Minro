<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$db = getDB();
$id  = (int)($_GET['id'] ?? 0);
$qty = max(1, min(9999, (int)($_GET['qty'] ?? 1)));

if (!$id) { header('Location: ' . BASE_URL . '/inventory/index.php'); exit; }

$stmt = $db->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$product = $stmt->fetch();
$barcodeValue = trim((string)($product['barcode'] ?? ''));
$barcodeValue = preg_replace('/[\x00-\x1F\x7F]/u', '', $barcodeValue);
if (!$product || $barcodeValue === '') {
    setFlash('error', 'Product not found or has no barcode.');
    header('Location: ' . BASE_URL . '/inventory/index.php');
    exit;
}

$settings    = getSettings();
$companyName = $settings['company_name'] ?? 'Minro';
$isPrint     = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Print Barcode — <?= e($product['name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
  body { background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', sans-serif; }

  .no-print-bar {
    padding: 16px 24px;
    background: #1e293b;
    border-bottom: 1px solid #334155;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .no-print-bar h6 { margin: 0; color: #f1f5f9; font-weight: 700; }
  .print-hint { font-size: 11px; color: #94a3b8; }
  .quick-print-fab {
    position: fixed;
    right: 18px;
    bottom: 18px;
    z-index: 9999;
    border-radius: 999px;
    padding: 10px 14px;
    box-shadow: 0 8px 20px rgba(0,0,0,.35);
  }

  .label-grid {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    padding: 30px;
  }

  /* Screen preview — proportional 2:1 */
  .barcode-label {
    background: white;
    color: #111;
    border: 1px dashed #bbb;
    border-radius: 4px;
    width: 189px;
    height: 94px;
    padding: 3px 5px;
    text-align: center;
    font-family: Arial, sans-serif;
    box-shadow: 0 1px 4px rgba(0,0,0,.12);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 1px;
  }
  .barcode-label .label-shop {
    font-size: 7px;
    font-weight: 900;
    color: #000;
    text-transform: uppercase;
    letter-spacing: 1px;
    line-height: 1;
    border-bottom: 1px solid #000;
    padding-bottom: 1px;
    width: 100%;
  }
  .barcode-label .label-name {
    font-size: 8px;
    font-weight: 700;
    color: #111;
    line-height: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    text-transform: uppercase;
  }
  .barcode-label .label-brand-model {
    font-size: 6px;
    font-weight: 600;
    color: #333;
    text-transform: uppercase;
    line-height: 1;
    width: 100%;
  }
  .barcode-label svg { 
    max-width: 100%; 
    max-height: 34px; 
    display: block; 
    margin: 0 auto;
    object-fit: contain; 
  }
  .barcode-label .label-code {
    font-size: 7px;
    font-weight: 900;
    color: #000;
    line-height: 1;
  }
  .barcode-label .label-price {
    font-size: 9px;
    font-weight: 900;
    color: #000;
    line-height: 1;
  }

  @media print {
    /* Thermal label: 50mm x 25mm with 3mm vertical gap */
    @page {
      size: 50mm 28mm;   /* label 25mm + 3mm gap */
      margin: 0;
    }
    html, body {
      width: 50mm;
      margin: 0;
      padding: 0;
      background: white;
      -webkit-print-color-adjust: exact;
    }
    .no-print-bar { display: none !important; }
    .quick-print-fab { display: none !important; }
    .label-grid {
      display: block;
      padding: 0;
      margin: 0;
      width: 50mm;
    }
    .barcode-label {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 0.5mm;
      box-shadow: none;
      border: none;
      border-radius: 0;
      width: 50mm !important;
      height: 25mm !important;
      padding: 1.5mm !important;
      margin: 0 0 3mm 0 !important; /* 3mm vertical gap */
      overflow: hidden;
      box-sizing: border-box;
      page-break-after: always;
      break-after: page;
    }
    .barcode-label .label-shop  { font-size: 5.5pt !important; font-weight: 900 !important; line-height: 1 !important; letter-spacing: 0.5px; text-transform: uppercase; border-bottom: 0.5pt solid #000; padding-bottom: 0.3mm; flex-shrink: 0; width: 100%; text-align: center; }
    .barcode-label .label-name  { font-size: 6.5pt !important; font-weight: 700 !important; line-height: 1 !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-shrink: 0; width: 100%; text-align: center; text-transform: uppercase; }
    .barcode-label .label-brand-model { font-size: 5pt !important; font-weight: 600 !important; line-height: 1 !important; flex-shrink: 0; width: 100%; text-align: center; text-transform: uppercase; }
    .barcode-label .label-code  { font-size: 6pt !important; font-weight: 900 !important; line-height: 1 !important; flex-shrink: 0; }
    .barcode-label .label-price { font-size: 7pt !important; font-weight: 900 !important; line-height: 1 !important; flex-shrink: 0; }
    .barcode-label svg {
      display: block !important;
      margin: 0 auto !important;
      max-width: 100% !important;
      height: 9mm !important;
      flex-shrink: 0;
      object-fit: fill !important;
    }
  }
</style>
</head>
<body>

<div class="no-print-bar">
  <a href="<?= BASE_URL ?>/inventory/index.php" class="btn btn-sm btn-secondary">
    <i class="fas fa-arrow-left me-1"></i>Back
  </a>
  <div>
    <h6><i class="fas fa-barcode me-2 text-warning"></i>Print Barcode Labels — <?= e($product['name']) ?></h6>
    <small class="text-muted">Barcode: <strong class="text-success"><?= e($barcodeValue) ?></strong></small>
    <div class="print-hint mt-1">If dialog shows Save, change Destination to your printer and click Print.</div>
  </div>

  <div class="ms-auto d-flex align-items-center gap-2">
    <label class="text-muted small me-1">Copies:</label>
    <form method="GET" class="d-flex gap-2 align-items-center">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="number" name="qty" value="<?= $qty ?>" min="1" max="9999"
        class="form-control form-control-sm"
        style="width:90px;background:#0f172a;color:#e2e8f0;border-color:#334155"
        title="Enter number of labels">
      <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt"></i></button>
    </form>
    <button onclick="triggerPrintNow(true); return false;" class="btn btn-sm btn-primary">
      <i class="fas fa-print me-1"></i>Print
    </button>
  </div>
</div>

<button type="button" class="btn btn-primary quick-print-fab" onclick="triggerPrintNow(true); return false;">
  <i class="fas fa-print me-1"></i>Print Now
</button>

<div class="label-grid" id="labelGrid">
  <?php for ($i = 0; $i < $qty; $i++): ?>
  <div class="barcode-label">
    <div class="label-shop"><?= e($companyName) ?></div>
    <div class="label-name" title="<?= e($product['name']) ?>"><?= e($product['name']) ?></div>
    <?php if (!empty($product['brand']) || !empty($product['model'])): ?>
    <div class="label-brand-model"><?= $product['brand'] ? e($product['brand']) : '' ?><?= ($product['brand'] && $product['model']) ? ' ' : '' ?><?= $product['model'] ? e($product['model']) : '' ?></div>
    <?php endif; ?>
    <svg class="barcode-svg-<?= $i ?>"></svg>
    <div class="label-code"><?= e($barcodeValue) ?></div>
    <div class="label-price"><?= money((float)$product['selling_price']) ?></div>
  </div>
  <?php endfor; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
const barcodeValue = <?= json_encode($barcodeValue) ?>;
const total = <?= $qty ?>;
const shouldAutoPrint = <?= $isPrint ? 'true' : 'false' ?>;
let printTriggered = false;

function getAdaptiveBarcodeOptions(value) {
  const len = String(value || '').trim().length;
  let width = 1.9;
  if (len > 10) width = 1.7;
  if (len > 14) width = 1.5;
  if (len > 18) width = 1.3;
  if (len > 24) width = 1.15;

  return {
    format: 'CODE128',
    width: width,
    height: 44,
    displayValue: false,
    lineColor: '#000',
    background: '#ffffff',
    margin: 2
  };
}

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

for (let i = 0; i < total; i++) {
  JsBarcode('.barcode-svg-' + i, barcodeValue, getAdaptiveBarcodeOptions(barcodeValue));
}
window.addEventListener('load', function () {
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

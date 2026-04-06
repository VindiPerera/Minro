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
if (!$product || empty($product['barcode'])) {
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
    justify-content: space-between;
  }
  .barcode-label .label-name {
    font-size: 8px;
    font-weight: 700;
    color: #111;
    line-height: 1.1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .barcode-label .label-meta {
    font-size: 7px;
    color: #666;
    line-height: 1;
  }
  .barcode-label svg { max-width: 100%; display: block; }
  .barcode-label .label-price {
    font-size: 10px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1.1;
  }

  @media print {
    /* XP-237B single-column 50x25mm label */
    @page {
      size: 50mm 25mm;
      margin: 0mm;
    }
    html, body {
      width: 50mm;
      margin: 0;
      padding: 0;
      background: white;
    }
    .no-print-bar { display: none !important; }
    .label-grid {
      display: block;
      padding: 0;
      margin: 0;
      width: 50mm;
    }
    .barcode-label {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: none;
      border: none;
      border-radius: 0;
      width: 50mm !important;
      height: 25mm !important;
      padding: 1.2mm 1.5mm !important;
      margin: 0 !important;
      overflow: hidden;
      page-break-after: always;
      break-after: page;
    }
    .barcode-label .label-name  { font-size: 7pt !important;  font-weight: 700 !important; line-height: 1 !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .barcode-label .label-meta  { display: none !important; }
    .barcode-label .label-price { font-size: 8pt !important;  font-weight: 800 !important; line-height: 1 !important; }
    .barcode-label svg {
      display: block !important;
      width: 47mm !important;
      height: 14mm !important;
      overflow: hidden !important;
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
    <small class="text-muted">Barcode: <strong class="text-success"><?= e($product['barcode']) ?></strong></small>
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
    <button onclick="window.print()" class="btn btn-sm btn-primary">
      <i class="fas fa-print me-1"></i>Print
    </button>
  </div>
</div>

<div class="label-grid" id="labelGrid">
  <?php for ($i = 0; $i < $qty; $i++): ?>
  <div class="barcode-label">
    <div class="label-name" title="<?= e($product['name']) ?>"><?= e($product['name']) ?></div>
    <?php if (!empty($product['brand']) || !empty($product['model'])): ?>
    <div class="label-meta"><?= $product['brand'] ? e($product['brand']) : '' ?><?= ($product['brand'] && $product['model']) ? ' &middot; ' : '' ?><?= $product['model'] ? e($product['model']) : '' ?></div>
    <?php endif; ?>
    <svg class="barcode-svg-<?= $i ?>"></svg>
    <div class="label-price"><?= money((float)$product['selling_price']) ?></div>
  </div>
  <?php endfor; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
const barcodeValue = <?= json_encode($product['barcode']) ?>;
const total = <?= $qty ?>;
for (let i = 0; i < total; i++) {
  JsBarcode('.barcode-svg-' + i, barcodeValue, {
    format: 'CODE128',
    width: 2,
    height: 40,
    displayValue: false,
    lineColor: '#000',
    background: '#ffffff',
    margin: 0
  });
}
<?php if ($isPrint): ?>
window.onload = function() { setTimeout(() => window.print(), 400); };
<?php endif; ?>
</script>
</body>
</html>

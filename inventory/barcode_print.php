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
    flex-wrap: wrap;
    gap: 8px;
    padding: 30px;
    justify-content: flex-start;
  }

  .barcode-label {
    background: white;
    color: #111;
    border: 1px dashed #bbb;
    border-radius: 4px;
    padding: 3px 5px;
    width: 189px;
    height: 94px;
    text-align: center;
    font-family: Arial, sans-serif;
    box-shadow: 0 1px 4px rgba(0,0,0,.12);
    overflow: hidden;
  }
  .barcode-label .label-company {
    font-size: 7px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #888;
    margin-bottom: 1px;
  }
  .barcode-label .label-name {
    font-size: 9px;
    font-weight: 700;
    color: #111;
    line-height: 1.2;
    margin-bottom: 1px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
  }
  .barcode-label .label-meta {
    font-size: 7px;
    color: #666;
    margin-bottom: 1px;
  }
  .barcode-label .label-price {
    font-size: 10px;
    font-weight: 800;
    color: #1e293b;
    margin-top: 1px;
  }
  .barcode-label svg { max-width: 100%; }

  @media print {
    @page { size: 50mm 28mm; margin: 0; }
    body { background: white; }
    .no-print-bar { display: none; }
    .label-grid { padding: 0; gap: 0; flex-direction: column; align-items: flex-start; }
    .barcode-label {
      box-shadow: none;
      border: none;
      width: 50mm !important;
      height: 25mm !important;
      padding: 1mm 2mm !important;
      margin-bottom: 3mm !important;
      page-break-after: always;
      overflow: hidden;
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
    <div class="label-company"><?= e($companyName) ?></div>
    <div class="label-name" title="<?= e($product['name']) ?>"><?= e($product['name']) ?></div>
    <?php if (!empty($product['brand']) || !empty($product['model'])): ?>
    <div class="label-meta">
      <?= $product['brand'] ? e($product['brand']) : '' ?><?= ($product['brand'] && $product['model']) ? ' · ' : '' ?><?= $product['model'] ? e($product['model']) : '' ?>
    </div>
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
    width: 1.2,
    height: 28,
    displayValue: true,
    fontSize: 8,
    lineColor: '#1e293b',
    background: '#ffffff',
    margin: 1
  });
}
<?php if ($isPrint): ?>
window.onload = function() { setTimeout(() => window.print(), 400); };
<?php endif; ?>
</script>
</body>
</html>

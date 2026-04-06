<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin', 'cashier');

$pageTitle = 'Manage Product';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$product = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) { header('Location: ' . BASE_URL . '/inventory/index.php'); exit; }
    $pageTitle = 'Edit Product';
} else {
    $pageTitle = 'Add Product';
}

$categories = $db->query("SELECT * FROM categories WHERE status=1 ORDER BY name")->fetchAll();
$errors = [];
$data   = $product ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'code'               => trim($_POST['code'] ?? ''),
        'name'               => trim($_POST['name'] ?? ''),
        'category_id'        => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
        'type'               => $_POST['type'] ?? 'accessory',
        'description'        => trim($_POST['description'] ?? ''),
        'cost_price'         => abs((float)($_POST['cost_price'] ?? 0)),
        'selling_price'      => abs((float)($_POST['selling_price'] ?? 0)),
        'stock_quantity'     => (int)($_POST['stock_quantity'] ?? 0),
        'low_stock_threshold'=> max(0, (int)($_POST['low_stock_threshold'] ?? 5)),
        'unit'               => trim($_POST['unit'] ?? 'pcs'),
        'status'             => isset($_POST['status']) ? 1 : 0,
    ];

    if (empty($data['code'])) $errors[] = 'Product code is required.';
    if (empty($data['name'])) $errors[] = 'Product name is required.';
    if ($data['selling_price'] <= 0) $errors[] = 'Selling price must be greater than 0.';

    // Check code uniqueness
    if ($data['code']) {
        $check = $db->prepare("SELECT id FROM products WHERE code=? AND id!=?");
        $check->execute([$data['code'], $id]);
        if ($check->fetch()) $errors[] = "Product code '{$data['code']}' already exists.";
    }

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE products SET code=?,name=?,category_id=?,type=?,description=?,cost_price=?,selling_price=?,stock_quantity=?,low_stock_threshold=?,unit=?,status=? WHERE id=?");
            $stmt->execute([$data['code'],$data['name'],$data['category_id'],$data['type'],$data['description'],$data['cost_price'],$data['selling_price'],$data['stock_quantity'],$data['low_stock_threshold'],$data['unit'],$data['status'],$id]);
            setFlash('success', "Product '{$data['name']}' updated successfully.");
        } else {
            $stmt = $db->prepare("INSERT INTO products (code,name,category_id,type,description,cost_price,selling_price,stock_quantity,low_stock_threshold,unit,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$data['code'],$data['name'],$data['category_id'],$data['type'],$data['description'],$data['cost_price'],$data['selling_price'],$data['stock_quantity'],$data['low_stock_threshold'],$data['unit'],$data['status']]);
            $newId = $db->lastInsertId();
            // Log initial stock
            if ($data['stock_quantity'] > 0) {
                logStockMovement($newId, 'purchase', $data['stock_quantity'], $newId, 'initial', 'Initial stock entry');
            }
            setFlash('success', "Product '{$data['name']}' added successfully.");
        }
        header('Location: ' . BASE_URL . '/inventory/index.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-<?= $id ? 'edit' : 'plus-circle' ?> me-2 text-primary"></i><?= $pageTitle ?></h4>
    <p><?= $id ? 'Update product information and pricing.' : 'Add a new product to your inventory.' ?></p>
  </div>
  <a href="<?= BASE_URL ?>/inventory/index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
</div>

<form method="POST">
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card mb-4">
      <div class="card-header">Product Information</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Product Code <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="text" name="code" class="form-control barcode-input" placeholder="e.g. PRD-001" value="<?= e($data['code'] ?? '') ?>" required>
              <?php if (!$id): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="autoCode" title="Auto-generate code">
                <i class="fas fa-magic"></i>
              </button>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-8">
            <label class="form-label">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="Full product name" value="<?= e($data['name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select select2">
              <option value="">— No Category —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($data['category_id'] ?? 0)==$c['id'] ? 'selected':'' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Product Type</label>
            <select name="type" class="form-select">
              <option value="accessory" <?= ($data['type']??'')==='accessory'?'selected':'' ?>>Accessory (for retail sale)</option>
              <option value="part" <?= ($data['type']??'')==='part'?'selected':'' ?>>Part (for repairs only)</option>
              <option value="both" <?= ($data['type']??'')==='both'?'selected':'' ?>>Both (accessory & part)</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Optional product description"><?= e($data['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Pricing</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Cost Price</label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" name="cost_price" class="form-control" id="costPrice" step="0.01" min="0" placeholder="0.00" value="<?= e($data['cost_price'] ?? '0') ?>">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Selling Price <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" name="selling_price" class="form-control" id="sellingPrice" step="0.01" min="0" placeholder="0.00" value="<?= e($data['selling_price'] ?? '0') ?>" required>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Margin</label>
            <div class="input-group">
              <input type="text" class="form-control" id="marginDisplay" readonly placeholder="—">
              <span class="input-group-text">%</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-header">Stock Management</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Current Stock</label>
          <div class="input-group">
            <input type="number" name="stock_quantity" class="form-control" value="<?= e($data['stock_quantity'] ?? '0') ?>" min="0">
            <input type="text" name="unit" class="form-control" style="max-width:70px" placeholder="pcs" value="<?= e($data['unit'] ?? 'pcs') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Low Stock Alert Threshold</label>
          <input type="number" name="low_stock_threshold" class="form-control" value="<?= e($data['low_stock_threshold'] ?? '5') ?>" min="0">
          <small class="text-muted">Alert when stock falls below this number</small>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="status" id="statusSwitch" <?= ($data['status'] ?? 1) ? 'checked':'' ?>>
          <label class="form-check-label text-muted" for="statusSwitch">Active (visible in POS)</label>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <div class="text-muted small mb-2">Quick Markup from Cost</div>
        <div class="d-flex gap-2 flex-wrap">
          <?php foreach ([20,30,50,100] as $pct): ?>
          <button type="button" class="btn btn-sm btn-outline-secondary markup-btn" data-pct="<?= $pct ?>"><?= $pct ?>%</button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
      <i class="fas fa-save me-2"></i><?= $id ? 'Update Product' : 'Add Product' ?>
    </button>
    <?php if ($id): ?>
    <a href="<?= BASE_URL ?>/inventory/stock_in.php?product_id=<?= $id ?>" class="btn btn-outline-success w-100 mt-2">
      <i class="fas fa-truck-loading me-2"></i>Add Stock
    </a>
    <?php endif; ?>
  </div>
</div>
</form>

<?php
$nextCode = generateProductCode();
$extraScripts = "
<script>
$('#autoCode').on('click', function() { $('input[name=code]').val('$nextCode'); });

function calcMargin() {
  const cost = parseFloat($('#costPrice').val()) || 0;
  const sell = parseFloat($('#sellingPrice').val()) || 0;
  if (cost > 0 && sell > 0) {
    const margin = ((sell - cost) / cost * 100).toFixed(1);
    $('#marginDisplay').val(margin);
  } else { $('#marginDisplay').val(''); }
}
$('#costPrice, #sellingPrice').on('input', calcMargin);
calcMargin();

$('.markup-btn').on('click', function() {
  const pct = parseInt(\$(this).data('pct'));
  const cost = parseFloat(\$('#costPrice').val()) || 0;
  if (cost > 0) {
    const sell = (cost * (1 + pct/100)).toFixed(2);
    \$('#sellingPrice').val(sell).trigger('input');
  }
});
</script>
";
require_once __DIR__ . '/../includes/footer.php';
?>

<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin', 'cashier');

$pageTitle = 'Stock In — Receive Stock';
$db = getDB();
$preselect = (int)($_GET['product_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId  = (int)($_POST['product_id'] ?? 0);
    $qty        = max(1, (int)($_POST['quantity'] ?? 0));
    $supplier   = trim($_POST['supplier'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');
    $cost       = abs((float)($_POST['unit_cost'] ?? 0));

    if (!$productId) $errors[] = 'Please select a product.';
    if ($qty < 1) $errors[] = 'Quantity must be at least 1.';

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            // Update stock quantity
            $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id=?")->execute([$qty, $productId]);
            // Optionally update cost price if provided
            if ($cost > 0) {
                $db->prepare("UPDATE products SET cost_price=? WHERE id=?")->execute([$cost, $productId]);
            }
            // Log movement
            $full = ($supplier ? "Supplier: $supplier. " : '') . $notes;
            logStockMovement($productId, 'purchase', $qty, 0, 'stock_in', $full ?: 'Stock received');

            $db->commit();
            setFlash('success', "Stock updated. Added $qty unit(s) successfully.");
            header('Location: ' . BASE_URL . '/inventory/stock_in.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Load products (with stock info)
$products = $db->query("SELECT id, code, name, brand, model, stock_quantity, unit, type FROM products WHERE status=1 ORDER BY name")->fetchAll();

// Recent stock-in history
$history = $db->query("
    SELECT sm.*, p.name as product_name, p.code as product_code, u.name as user_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    LEFT JOIN users u ON u.id = sm.created_by
    WHERE sm.movement_type = 'purchase'
    ORDER BY sm.created_at DESC
    LIMIT 50
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-truck-loading me-2 text-success"></i>Receive Stock</h4>
    <p>Record incoming stock from purchases or deliveries.</p>
  </div>
  <a href="<?= BASE_URL ?>/inventory/index.php" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Inventory
  </a>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header fw-semibold"><i class="fas fa-plus-circle me-2 text-success"></i>Record Stock Received</div>
      <div class="card-body">
        <form method="POST" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">Product <span class="text-danger">*</span></label>
            <select name="product_id" id="productSelect" class="form-select select2" required>
              <option value="">— Search product —</option>
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>"
                data-code="<?= e($p['code']) ?>"
                data-unit="<?= e($p['unit']) ?>"
                data-stock="<?= $p['stock_quantity'] ?>"
                <?= $preselect == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['code']) ?> — <?= e($p['name']) ?> (<?= $p['stock_quantity'] ?> <?= e($p['unit']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="stockInfoBox" class="alert alert-secondary py-2 small mb-3 d-none">
            Current stock: <strong id="curStock">—</strong> <span id="curUnit"></span>
          </div>

          <div class="mb-3">
            <label class="form-label">Quantity to Add <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" name="quantity" id="qtyInput" class="form-control" min="1" value="1" required>
              <span class="input-group-text" id="unitLabel">pcs</span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Unit Cost <small class="text-muted">(optional — updates product cost)</small></label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" placeholder="0.00">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Supplier / Source</label>
            <input type="text" name="supplier" class="form-control" placeholder="Supplier name or source">
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Invoice number, batch, etc."></textarea>
          </div>
          <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
            <i class="fas fa-check-circle me-2"></i>Record Stock In
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header fw-semibold"><i class="fas fa-history me-2 text-muted"></i>Recent Stock Received</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 datatable">
            <thead>
              <tr>
                <th>Date</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Notes</th>
                <th>By</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $h): ?>
              <tr>
                <td class="text-nowrap"><small><?= niceDateTime($h['created_at']) ?></small></td>
                <td>
                  <div class="fw-semibold"><?= e($h['product_name']) ?></div>
                  <small class="text-muted"><?= e($h['product_code']) ?></small>
                </td>
                <td><span class="badge bg-success-subtle text-success">+<?= $h['quantity'] ?></span></td>
                <td><small class="text-muted"><?= e($h['notes'] ?? '') ?></small></td>
                <td><small><?= e($h['user_name'] ?? '—') ?></small></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($history)): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No stock receipts yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php $extraScripts = <<<JS
<script>
$('#productSelect').on('change', function() {
  const opt = $(this).find(':selected');
  const stock = opt.data('stock');
  const unit  = opt.data('unit') || 'pcs';
  if ($(this).val()) {
    $('#curStock').text(stock);
    $('#curUnit').text(unit);
    $('#stockInfoBox').removeClass('d-none');
    $('#unitLabel').text(unit);
  } else {
    $('#stockInfoBox').addClass('d-none');
    $('#unitLabel').text('pcs');
  }
});
// Trigger on load if preselected
if ($('#productSelect').val()) $('#productSelect').trigger('change');
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>

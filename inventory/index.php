<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$pageTitle = 'Inventory';
$db = getDB();

$filter   = $_GET['filter']   ?? 'all';
$category = $_GET['category'] ?? 'all';
$type     = $_GET['type']     ?? 'all';
$search   = trim($_GET['search'] ?? '');

$where  = ['p.status=1'];
$params = [];

if ($filter === 'low_stock')  { $where[] = 'p.stock_quantity <= p.low_stock_threshold'; }
if ($filter === 'out_stock')  { $where[] = 'p.stock_quantity <= 0'; }
if ($filter === 'parts')      { $where[] = "p.type IN ('part','both')"; }
if ($filter === 'accessories'){ $where[] = "p.type IN ('accessory','both')"; }
if ($category !== 'all')      { $where[] = 'p.category_id=?'; $params[] = $category; }
if ($type !== 'all')          { $where[] = 'p.type=?'; $params[] = $type; }
if ($search)                  { $where[] = '(p.name LIKE ? OR p.code LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$whereClause = implode(' AND ', $where);
$stmt = $db->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $whereClause ORDER BY p.name");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories WHERE status=1 ORDER BY name")->fetchAll();

// Stats
$totalProducts  = $db->query("SELECT COUNT(*) FROM products WHERE status=1")->fetchColumn();
$lowStockCount  = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= low_stock_threshold AND stock_quantity > 0 AND status=1")->fetchColumn();
$outOfStockCount= $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 0 AND status=1")->fetchColumn();
$inventoryValue = $db->query("SELECT COALESCE(SUM(cost_price * stock_quantity),0) FROM products WHERE status=1")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h4><i class="fas fa-boxes me-2 text-warning"></i>Products & Stock</h4>
    <p>Manage your inventory, accessories, and repair parts.</p>
  </div>
  <a href="<?= BASE_URL ?>/inventory/manage.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Product</a>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="stat-card text-center">
      <div class="stat-value"><?= $totalProducts ?></div>
      <div class="stat-label">Total Products</div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card text-center">
      <div class="stat-value" style="color:#fbbf24"><?= $lowStockCount ?></div>
      <div class="stat-label">Low Stock</div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card text-center">
      <div class="stat-value" style="color:#f87171"><?= $outOfStockCount ?></div>
      <div class="stat-label">Out of Stock</div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card text-center">
      <div class="stat-value" style="color:#86efac;font-size:18px"><?= money((float)$inventoryValue) ?></div>
      <div class="stat-label">Inventory Value</div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body py-3">
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php $filters = ['all'=>'All Products','low_stock'=>'⚠ Low Stock','out_stock'=>'✗ Out of Stock','parts'=>'Parts','accessories'=>'Accessories']; ?>
      <?php foreach ($filters as $f => $l): ?>
      <a href="?filter=<?= $f ?>&category=<?= urlencode($category) ?>&search=<?= urlencode($search) ?>"
         class="btn btn-sm <?= $filter===$f ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <form method="GET" class="row g-2">
      <input type="hidden" name="filter" value="<?= e($filter) ?>">
      <div class="col-sm-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or code..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <select name="category" class="form-select form-select-sm">
          <option value="all">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $category==$c['id'] ? 'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <select name="type" class="form-select form-select-sm">
          <option value="all">All Types</option>
          <option value="accessory" <?= $type==='accessory'?'selected':'' ?>>Accessories</option>
          <option value="part" <?= $type==='part'?'selected':'' ?>>Parts</option>
          <option value="both" <?= $type==='both'?'selected':'' ?>>Both</option>
        </select>
      </div>
      <div class="col-sm-3 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fas fa-filter me-1"></i>Filter</button>
        <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
        <a href="<?= BASE_URL ?>/inventory/stock_in.php" class="btn btn-sm btn-outline-info" title="Stock In"><i class="fas fa-truck-loading"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Products Table -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="productsTable">
        <thead>
          <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Category</th>
            <th>Type</th>
            <th class="text-end">Cost</th>
            <th class="text-end">Sell Price</th>
            <th class="text-center">Stock</th>
            <th class="text-center">Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p):
            $stockStatus = $p['stock_quantity'] <= 0 ? 'out' : ($p['stock_quantity'] <= $p['low_stock_threshold'] ? 'low' : 'ok');
          ?>
          <tr>
            <td><code><?= e($p['code']) ?></code></td>
            <td>
              <div class="fw-semibold small"><?= e($p['name']) ?></div>
              <?php if ($p['description']): ?><div class="text-muted" style="font-size:11px"><?= e(substr($p['description'],0,40)) ?>...</div><?php endif; ?>
            </td>
            <td class="small text-muted"><?= e($p['cat_name'] ?? '—') ?></td>
            <td>
              <span class="badge <?= $p['type']==='accessory'?'bg-info':($p['type']==='part'?'bg-warning text-dark':'bg-secondary') ?>">
                <?= ucfirst($p['type']) ?>
              </span>
            </td>
            <td class="text-end small"><?= money((float)$p['cost_price']) ?></td>
            <td class="text-end fw-semibold"><?= money((float)$p['selling_price']) ?></td>
            <td class="text-center">
              <span class="fw-bold <?= $stockStatus==='out'?'text-danger':($stockStatus==='low'?'text-warning':'text-success') ?>">
                <?= $p['stock_quantity'] ?>
              </span>
              <div style="font-size:10px;color:#64748b">min: <?= $p['low_stock_threshold'] ?></div>
              <?php if ($stockStatus==='low'): ?><span class="badge bg-warning text-dark" style="font-size:9px">Low</span><?php endif; ?>
              <?php if ($stockStatus==='out'):  ?><span class="badge bg-danger" style="font-size:9px">Out</span><?php endif; ?>
            </td>
            <td class="text-center">
              <?php if ($p['status']): ?>
              <span class="badge bg-success">Active</span>
              <?php else: ?>
              <span class="badge bg-secondary">Inactive</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/inventory/manage.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                <a href="<?= BASE_URL ?>/inventory/stock_in.php?product_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success" title="Add Stock"><i class="fas fa-plus"></i></a>
                <?php if (isAdmin()): ?>
                <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this product? This cannot be undone." title="Delete"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($products)): ?>
          <tr><td colspan="9" class="text-center py-5 text-muted">
            <i class="fas fa-box-open fa-3x mb-3 d-block" style="color:#334155"></i>No products found
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isAdmin()) {
    $delId = (int)($_GET['id'] ?? 0);
    if ($delId) {
        $db->prepare("UPDATE products SET status=0 WHERE id=?")->execute([$delId]);
        setFlash('success', 'Product deleted (deactivated).');
        header('Location: ' . BASE_URL . '/inventory/index.php');
        exit;
    }
}

$extraScripts = "<script>
$(document).ready(function() {
  $('#productsTable').DataTable({ pageLength: 25, order: [[2,'asc']], searching: false });
});
</script>";
require_once __DIR__ . '/../includes/footer.php';
?>

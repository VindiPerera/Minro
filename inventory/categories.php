<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin');

$pageTitle = 'Brands & Models';
$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_brand') {
        $bId    = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        if (!$name) $errors[] = 'Brand name is required.';
        if (empty($errors)) {
            if ($bId) {
                $db->prepare("UPDATE brands SET name=?,status=? WHERE id=?")->execute([$name,$status,$bId]);
                setFlash('success', 'Brand updated.');
            } else {
                $db->prepare("INSERT INTO brands (name,status) VALUES (?,?)")->execute([$name,$status]);
                setFlash('success', 'Brand added.');
            }
            header('Location: ' . BASE_URL . '/inventory/categories.php'); exit;
        }
    } elseif ($action === 'delete_brand') {
        $bId = (int)($_POST['id'] ?? 0);
        $cnt = $db->prepare("SELECT COUNT(*) FROM phone_models WHERE brand_id=?");
        $cnt->execute([$bId]);
        if ($cnt->fetchColumn() > 0) {
            setFlash('danger', 'Cannot delete: brand has models assigned. Remove models first.');
        } else {
            $db->prepare("DELETE FROM brands WHERE id=?")->execute([$bId]);
            setFlash('success', 'Brand deleted.');
        }
        header('Location: ' . BASE_URL . '/inventory/categories.php'); exit;

    } elseif ($action === 'save_model') {
        $mId     = (int)($_POST['id'] ?? 0);
        $brandId = (int)($_POST['brand_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $status  = isset($_POST['status']) ? 1 : 0;
        if (!$name)    $errors[] = 'Model name is required.';
        if (!$brandId) $errors[] = 'Brand is required.';
        if (empty($errors)) {
            if ($mId) {
                $db->prepare("UPDATE phone_models SET brand_id=?,name=?,status=? WHERE id=?")->execute([$brandId,$name,$status,$mId]);
                setFlash('success', 'Model updated.');
            } else {
                $db->prepare("INSERT INTO phone_models (brand_id,name,status) VALUES (?,?,?)")->execute([$brandId,$name,$status]);
                setFlash('success', 'Model added.');
            }
            header('Location: ' . BASE_URL . '/inventory/categories.php#models'); exit;
        }
    } elseif ($action === 'delete_model') {
        $mId = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM phone_models WHERE id=?")->execute([$mId]);
        setFlash('success', 'Model deleted.');
        header('Location: ' . BASE_URL . '/inventory/categories.php#models'); exit;
    }
}

$brands    = $db->query("SELECT b.*, COUNT(m.id) as model_count FROM brands b LEFT JOIN phone_models m ON m.brand_id=b.id GROUP BY b.id ORDER BY b.name")->fetchAll();
$models    = $db->query("SELECT m.*, b.name as brand_name FROM phone_models m JOIN brands b ON b.id=m.brand_id ORDER BY b.name, m.name")->fetchAll();
$allBrands = $db->query("SELECT * FROM brands WHERE status=1 ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h4><i class="fas fa-mobile-alt me-2 text-primary"></i>Brands & Models</h4>
    <p>Manage predefined phone brands and models used in products.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modelModal" id="addModelBtn">
      <i class="fas fa-plus me-1"></i>Add Model
    </button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#brandModal" id="addBrandBtn">
      <i class="fas fa-plus me-1"></i>Add Brand
    </button>
  </div>
</div>

<div class="row g-4">
  <!-- Brands -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header fw-semibold"><i class="fas fa-tags me-2 text-primary"></i>Brands</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0 datatable">
          <thead>
            <tr><th>#</th><th>Brand</th><th>Models</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($brands as $i => $b): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td class="fw-semibold"><?= e($b['name']) ?></td>
              <td><span class="badge bg-primary-subtle text-primary"><?= $b['model_count'] ?></span></td>
              <td><?= $b['status'] ? '<span class="badge bg-success-subtle text-success">Active</span>' : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>' ?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary me-1 edit-brand-btn"
                  data-id="<?= $b['id'] ?>" data-name="<?= e($b['name']) ?>" data-status="<?= $b['status'] ?>">
                  <i class="fas fa-edit"></i>
                </button>
                <?php if ($b['model_count'] == 0): ?>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="delete_brand">
                  <input type="hidden" name="id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete brand '<?= e($b['name']) ?>'?">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
                <?php else: ?>
                <button class="btn btn-sm btn-outline-danger" disabled title="Has models"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($brands)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No brands yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Models -->
  <div class="col-lg-7" id="models">
    <div class="card">
      <div class="card-header fw-semibold"><i class="fas fa-list me-2 text-info"></i>Phone Models</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0 datatable">
          <thead>
            <tr><th>#</th><th>Brand</th><th>Model</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($models as $i => $m): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><span class="badge bg-secondary-subtle text-secondary"><?= e($m['brand_name']) ?></span></td>
              <td class="fw-semibold"><?= e($m['name']) ?></td>
              <td><?= $m['status'] ? '<span class="badge bg-success-subtle text-success">Active</span>' : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>' ?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary me-1 edit-model-btn"
                  data-id="<?= $m['id'] ?>" data-brand-id="<?= $m['brand_id'] ?>"
                  data-name="<?= e($m['name']) ?>" data-status="<?= $m['status'] ?>">
                  <i class="fas fa-edit"></i>
                </button>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="delete_model">
                  <input type="hidden" name="id" value="<?= $m['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete model '<?= e($m['name']) ?>'?">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($models)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No models yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Brand Modal -->
<div class="modal fade" id="brandModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <form method="POST">
      <input type="hidden" name="action" value="save_brand">
      <input type="hidden" name="id" id="brandId" value="0">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="brandModalTitle">Add Brand</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Brand Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="brandName" class="form-control" placeholder="e.g. Samsung" required>
          </div>
          <div class="form-check form-switch">
            <input type="checkbox" name="status" id="brandStatus" class="form-check-input" checked>
            <label class="form-check-label text-muted" for="brandStatus">Active</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Brand</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Model Modal -->
<div class="modal fade" id="modelModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST">
      <input type="hidden" name="action" value="save_model">
      <input type="hidden" name="id" id="modelId" value="0">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modelModalTitle">Add Model</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Brand <span class="text-danger">*</span></label>
            <select name="brand_id" id="modelBrandId" class="form-select select2" required>
              <option value="">— Select Brand —</option>
              <?php foreach ($allBrands as $b): ?>
              <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Model Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="modelName" class="form-control" placeholder="e.g. Galaxy S21" required>
          </div>
          <div class="form-check form-switch">
            <input type="checkbox" name="status" id="modelStatus" class="form-check-input" checked>
            <label class="form-check-label text-muted" for="modelStatus">Active</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Model</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = <<<JS
<script>
// Brand modal
$('#addBrandBtn').on('click', function() {
  $('#brandModalTitle').text('Add Brand');
  $('#brandId').val('0');
  $('#brandName').val('');
  $('#brandStatus').prop('checked', true);
});
$('.edit-brand-btn').on('click', function() {
  const d = $(this).data();
  $('#brandModalTitle').text('Edit Brand');
  $('#brandId').val(d.id);
  $('#brandName').val(d.name);
  $('#brandStatus').prop('checked', d.status == 1);
  new bootstrap.Modal(document.getElementById('brandModal')).show();
});

// Model modal
$('#addModelBtn').on('click', function() {
  $('#modelModalTitle').text('Add Model');
  $('#modelId').val('0');
  $('#modelBrandId').val('').trigger('change');
  $('#modelName').val('');
  $('#modelStatus').prop('checked', true);
});
$('.edit-model-btn').on('click', function() {
  const d = $(this).data();
  $('#modelModalTitle').text('Edit Model');
  $('#modelId').val(d.id);
  $('#modelBrandId').val(d.brandId).trigger('change');
  $('#modelName').val(d.name);
  $('#modelStatus').prop('checked', d.status == 1);
  new bootstrap.Modal(document.getElementById('modelModal')).show();
});
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
$db = getDB();
$errors = [];

// Handle POST (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $cId   = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $brand = trim($_POST['brand'] ?? '') ?: null;
        $model = trim($_POST['model'] ?? '') ?: null;
        $desc  = trim($_POST['description'] ?? '');
        $status= isset($_POST['status']) ? 1 : 0;

        if (!$name) $errors[] = 'Category name is required.';
        if (empty($errors)) {
            if ($cId) {
                $db->prepare("UPDATE categories SET name=?,brand=?,model=?,description=?,status=? WHERE id=?")->execute([$name,$brand,$model,$desc,$status,$cId]);
                setFlash('success', 'Category updated.');
            } else {
                $db->prepare("INSERT INTO categories (name,brand,model,description,status) VALUES (?,?,?,?,?)")->execute([$name,$brand,$model,$desc,$status]);
                setFlash('success', 'Category added.');
            }
            header('Location: ' . BASE_URL . '/inventory/categories.php'); exit;
        }
    } elseif ($action === 'delete') {
        $cId = (int)($_POST['id'] ?? 0);
        $used = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
        $used->execute([$cId]);
        if ($used->fetchColumn() > 0) {
            setFlash('danger', 'Cannot delete: category has products assigned.');
        } else {
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([$cId]);
            setFlash('success', 'Category deleted.');
        }
        header('Location: ' . BASE_URL . '/inventory/categories.php'); exit;
    }
}

// Load categories with product count
$categories = $db->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-tags me-2 text-primary"></i>Categories</h4>
    <p>Organise your products and parts into categories.</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" id="addBtn">
    <i class="fas fa-plus me-2"></i>Add Category
  </button>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0 datatable">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>          <th>Brand</th>
          <th>Model</th>
          <th>Products</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $i => $c): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= e($c['name']) ?></td>
          <td><?= $c['brand'] ? e($c['brand']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $c['model'] ? e($c['model']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $c['product_count'] ?></td>
          <td>
            <?php if ($c['status']): ?>
              <span class="badge bg-success-subtle text-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary me-1 edit-btn"
              data-id="<?= $c['id'] ?>"
              data-name="<?= e($c['name']) ?>"
              data-brand="<?= e($c['brand'] ?? '') ?>"
              data-model="<?= e($c['model'] ?? '') ?>"
              data-description="<?= e($c['description'] ?? '') ?>"
              data-status="<?= $c['status'] ?>">
              <i class="fas fa-edit"></i>
            </button>
            <?php if ($c['product_count'] == 0): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete category '<?= e($c['name']) ?>'?">
                <i class="fas fa-trash"></i>
              </button>
            </form>
            <?php else: ?>
            <button class="btn btn-sm btn-outline-danger" disabled title="Has products">
              <i class="fas fa-trash"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="catId" value="0">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="catModalTitle">Add Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Category Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="catName" class="form-control" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Brand <span class="text-muted small">(optional)</span></label>
              <input type="text" name="brand" id="catBrand" class="form-control" placeholder="e.g. Samsung, Apple">
            </div>
            <div class="col-md-6">
              <label class="form-label">Model <span class="text-muted small">(optional)</span></label>
              <input type="text" name="model" id="catModel" class="form-control" placeholder="e.g. Galaxy S21, iPhone 13">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" id="catDesc" class="form-control" rows="2"></textarea>
          </div>
          <div class="form-check form-switch">
            <input type="checkbox" name="status" id="catStatus" class="form-check-input" checked>
            <label class="form-check-label text-muted" for="catStatus">Active</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Category</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = <<<JS
<script>
$('#addBtn').on('click', function() {
  $('#catModalTitle').text('Add Category');
  $('#catId').val('0');
  $('#catName').val('');
  $('#catBrand').val('');
  $('#catModel').val('');
  $('#catDesc').val('');
  $('#catStatus').prop('checked', true);
});

$('.edit-btn').on('click', function() {
  const d = $(this).data();
  $('#catModalTitle').text('Edit Category');
  $('#catId').val(d.id);
  $('#catName').val(d.name);
  $('#catBrand').val(d.brand || '');
  $('#catModel').val(d.model || '');
  $('#catDesc').val(d.description);
  $('#catStatus').prop('checked', d.status == 1);
  new bootstrap.Modal(document.getElementById('catModal')).show();
});
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>

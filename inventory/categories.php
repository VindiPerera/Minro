<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin');

$pageTitle = 'Categories';
$db = getDB();
$errors = [];

// Handle POST (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $cId   = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $type  = $_POST['type'] ?? 'both';
        $desc  = trim($_POST['description'] ?? '');
        $status= isset($_POST['status']) ? 1 : 0;

        if (!$name) $errors[] = 'Category name is required.';
        if (empty($errors)) {
            if ($cId) {
                $db->prepare("UPDATE categories SET name=?,type=?,description=?,status=? WHERE id=?")->execute([$name,$type,$desc,$status,$cId]);
                setFlash('success', 'Category updated.');
            } else {
                $db->prepare("INSERT INTO categories (name,type,description,status) VALUES (?,?,?,?)")->execute([$name,$type,$desc,$status]);
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
          <th>Name</th>
          <th>Type</th>
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
          <td>
            <?php if ($c['type']==='accessory'): ?>
              <span class="badge bg-info-subtle text-info">Accessory</span>
            <?php elseif ($c['type']==='part'): ?>
              <span class="badge bg-warning-subtle text-warning">Part</span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary">Both</span>
            <?php endif; ?>
          </td>
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
              data-type="<?= e($c['type']) ?>"
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
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="type" id="catType" class="form-select">
              <option value="accessory">Accessory (for retail sale)</option>
              <option value="part">Part (for repairs)</option>
              <option value="both">Both</option>
            </select>
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
  $('#catType').val('both');
  $('#catDesc').val('');
  $('#catStatus').prop('checked', true);
});

$('.edit-btn').on('click', function() {
  const d = $(this).data();
  $('#catModalTitle').text('Edit Category');
  $('#catId').val(d.id);
  $('#catName').val(d.name);
  $('#catType').val(d.type);
  $('#catDesc').val(d.description);
  $('#catStatus').prop('checked', d.status == 1);
  new bootstrap.Modal(document.getElementById('catModal')).show();
});
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>

<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin');

$pageTitle = 'Repair Services';
$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $sid    = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $price  = abs((float)($_POST['base_price'] ?? 0));
        $status = isset($_POST['status']) ? 1 : 0;

        if (!$name) $errors[] = 'Service name is required.';

        if (empty($errors)) {
            if ($sid) {
                $db->prepare("UPDATE repair_services SET name=?,description=?,base_price=?,status=? WHERE id=?")->execute([$name,$desc,$price,$status,$sid]);
                setFlash('success', 'Service updated.');
            } else {
                $db->prepare("INSERT INTO repair_services (name,description,base_price,status) VALUES (?,?,?,?)")->execute([$name,$desc,$price,$status]);
                setFlash('success', 'Service added.');
            }
            header('Location: ' . BASE_URL . '/settings/services.php'); exit;
        }
    } elseif ($action === 'delete') {
        $sid = (int)($_POST['id'] ?? 0);
        $inUse = $db->prepare("SELECT COUNT(*) FROM repair_job_services WHERE service_id=?");
        $inUse->execute([$sid]);
        if ($inUse->fetchColumn() > 0) {
            setFlash('danger', 'Cannot delete: service is used in existing repair jobs.');
        } else {
            $db->prepare("DELETE FROM repair_services WHERE id=?")->execute([$sid]);
            setFlash('success', 'Service deleted.');
        }
        header('Location: ' . BASE_URL . '/settings/services.php'); exit;
    }
}

$services = $db->query("
    SELECT rs.*, COUNT(rjs.id) as usage_count
    FROM repair_services rs
    LEFT JOIN repair_job_services rjs ON rjs.service_id = rs.id
    GROUP BY rs.id
    ORDER BY rs.name
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-tools me-2 text-warning"></i>Repair Services</h4>
    <p>Manage the list of repair services offered (e.g., Screen Replacement, Battery Replacement).</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/settings/index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Settings</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#svcModal" id="addSvcBtn">
      <i class="fas fa-plus me-2"></i>Add Service
    </button>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0 datatable">
      <thead><tr><th>#</th><th>Service Name</th><th>Description</th><th>Base Price</th><th>Used In</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($services as $i => $s): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= e($s['name']) ?></td>
          <td class="text-muted small"><?= e($s['description'] ?? '') ?></td>
          <td><?= money($s['base_price']) ?></td>
          <td><span class="badge bg-secondary-subtle text-secondary"><?= $s['usage_count'] ?> jobs</span></td>
          <td>
            <?php if ($s['status']): ?>
              <span class="badge bg-success-subtle text-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary me-1 edit-svc-btn"
              data-id="<?= $s['id'] ?>"
              data-name="<?= e($s['name']) ?>"
              data-description="<?= e($s['description'] ?? '') ?>"
              data-price="<?= $s['base_price'] ?>"
              data-status="<?= $s['status'] ?>">
              <i class="fas fa-edit"></i>
            </button>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"
                data-confirm="Delete '<?= e($s['name']) ?>'?"
                <?= $s['usage_count'] > 0 ? 'disabled title="In use"' : '' ?>>
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($services)): ?><tr><td colspan="7" class="text-center text-muted py-5">No services yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="svcModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="svcId" value="0">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="svcModalTitle">Add Service</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Service Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="svcName" class="form-control" required placeholder="e.g. Screen Replacement">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" id="svcDesc" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Base Price</label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" name="base_price" id="svcPrice" class="form-control" step="0.01" min="0" value="0">
            </div>
          </div>
          <div class="form-check form-switch">
            <input type="checkbox" name="status" id="svcStatus" class="form-check-input" checked>
            <label class="form-check-label text-muted" for="svcStatus">Active</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Service</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = <<<JS
<script>
$('#addSvcBtn').on('click', function() {
  $('#svcModalTitle').text('Add Service');
  $('#svcId').val('0');
  $('#svcName').val('');
  $('#svcDesc').val('');
  $('#svcPrice').val('0');
  $('#svcStatus').prop('checked', true);
});
$('.edit-svc-btn').on('click', function() {
  const d = $(this).data();
  $('#svcModalTitle').text('Edit Service');
  $('#svcId').val(d.id);
  $('#svcName').val(d.name);
  $('#svcDesc').val(d.description);
  $('#svcPrice').val(d.price);
  $('#svcStatus').prop('checked', d.status == 1);
  new bootstrap.Modal(document.getElementById('svcModal')).show();
});
</script>
JS;
require_once __DIR__ . '/../includes/footer.php'; ?>

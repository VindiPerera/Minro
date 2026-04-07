<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isAdmin()) { header('Location: ' . BASE_URL . '/dashboard/index.php'); exit; }

$pageTitle = 'Returns to Supplier';
$db = getDB();

$query = "
  SELECT sr.*, 
         s.name as supplier_name,
         (SELECT SUM(quantity) FROM supplier_return_items WHERE return_id = sr.id) as total_items
  FROM supplier_returns sr
  JOIN suppliers s ON sr.supplier_id = s.id
  ORDER BY sr.created_at DESC
";
$returns = $db->query($query)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h4 class="mb-1"><i class="fas fa-truck-loading me-2 text-danger"></i>Returns to Supplier</h4>
    <p class="text-muted mb-0">Manage and track product returns sent back to your suppliers.</p>
  </div>
  
  <div class="d-flex align-items-center gap-2">
    <?php if (!empty($returns)): ?>
    <div class="input-group" style="width: 250px;">
      <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
      <input type="text" id="customSearch" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search returns...">
    </div>
    <select id="customLength" class="form-select w-auto shadow-none text-muted">
      <option value="10">10 Rows</option>
      <option value="25" selected>25 Rows</option>
      <option value="50">50 Rows</option>
      <option value="100">100 Rows</option>
    </select>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/returns/manage.php" class="btn btn-danger text-nowrap">
      <i class="fas fa-plus me-2"></i>New Return Order
    </a>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <?php if (empty($returns)): ?>
  <div class="card-body text-center py-5">
    <div class="mb-4 text-muted" style="opacity: 0.5;">
        <i class="fas fa-box-open" style="font-size: 4rem;"></i>
    </div>
    <h5 class="fw-bold mb-2">No Returns Found</h5>
    <p class="text-muted mb-4">You haven't initiated any product returns to suppliers yet.</p>
    <a href="<?= BASE_URL ?>/returns/manage.php" class="btn btn-danger px-4 py-2">
      <i class="fas fa-plus me-2"></i>Create First Return Order
    </a>
  </div>
  <?php else: ?>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 datatable">
      <thead>
        <tr>
          <th>Return ID</th>
          <th>Supplier</th>
          <th>Total Items</th>
          <th>Status</th>
          <th>Note</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($returns as $r): ?>
        <tr>
          <td class="fw-semibold text-primary">RET-<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?></td>
          <td class="fw-semibold"><?= e($r['supplier_name']) ?></td>
          <td class="fw-bold text-danger">-<?= e($r['total_items'] ?: 0) ?> Items</td>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
              <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>
            <?php elseif ($r['status'] === 'completed'): ?>
              <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Completed</span>
            <?php else: ?>
              <span class="badge bg-secondary"><i class="fas fa-times-circle me-1"></i>Canceled</span>
            <?php endif; ?>
          </td>
          <td class="text-truncate" style="max-width: 150px;" title="<?= e($r['note']) ?>">
             <?= e($r['note'] ?: '—') ?>
          </td>
          <td class="text-muted small"><?= niceDate($r['created_at']) ?></td>   
          <td>
            <a href="<?= BASE_URL ?>/returns/manage.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit/Add Items"><i class="fas fa-edit"></i></a>
            <a href="<?= BASE_URL ?>/returns/status.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="Update Status"><i class="fas fa-sync-alt"></i></a>
            <a href="<?= BASE_URL ?>/returns/delete.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" data-confirm="Are you sure you want to delete this return order? Stock will be restored!"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php 
$extraScripts = "
<script>
$(document).ready(function() {
  var table = $('.datatable').DataTable({
    pageLength: 25,
    order: [[0, 'desc']],
    searching: true,
    dom: '<\"table-responsive\"t><\"row pt-3 pb-2 px-3 align-items-center\"<\"col-md-5 small text-muted\"i><\"col-md-7 d-flex justify-content-end mb-0\"p>>',
    language: {
      paginate: {
        previous: '<i class=\"fas fa-chevron-left\"></i>',
        next: '<i class=\"fas fa-chevron-right\"></i>'
      }
    }
  });

  $('#customSearch').on('keyup', function() {
    table.search(this.value).draw();
  });

  $('#customLength').on('change', function() {
    table.page.len(this.value).draw();
  });
});
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>
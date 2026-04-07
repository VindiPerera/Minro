<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isAdmin()) { header('Location: ' . BASE_URL . '/dashboard/index.php'); exit; }

$pageTitle = 'Suppliers';
$db = getDB();

$suppliers = $db->query('SELECT * FROM suppliers ORDER BY created_at DESC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">     
  <div>
    <h4 class="mb-1"><i class="fas fa-truck me-2 text-primary"></i>Suppliers</h4>
    <p class="text-muted mb-0">Manage your suppliers and view their details.</p>
  </div>
  
  <div class="d-flex align-items-center gap-2">
    <?php if (!empty($suppliers)): ?>
    <!-- Custom Search -->
    <div class="input-group" style="width: 250px;">
      <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
      <input type="text" id="customSearch" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search suppliers...">
    </div>
    <!-- Custom Length Menu -->
    <select id="customLength" class="form-select w-auto shadow-none text-muted">
      <option value="10">10 Rows</option>
      <option value="25" selected>25 Rows</option>
      <option value="50">50 Rows</option>
      <option value="100">100 Rows</option>
    </select>
    
    <!-- Export CSV -->
    <a href="<?= BASE_URL ?>/suppliers/export.php" class="btn btn-outline-success text-nowrap" title="Export to CSV">
      <i class="fas fa-file-csv me-1"></i> Export
    </a>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/suppliers/manage.php" class="btn btn-primary text-nowrap">       
      <i class="fas fa-plus me-2"></i>Add Supplier
    </a>
  </div>
</div>

<div class="card">
  <?php if (empty($suppliers)): ?>
  <div class="card-body text-center py-5">
    <div class="mb-4 text-muted" style="opacity: 0.5;">
        <i class="fas fa-building" style="font-size: 4rem;"></i>
    </div>
    <h5 class="fw-bold mb-2">No Suppliers Found</h5>
    <p class="text-muted mb-4">You haven't added any suppliers yet. Keep track of them by adding your first one.</p>
    <a href="<?= BASE_URL ?>/suppliers/manage.php" class="btn btn-primary px-4 py-2">
      <i class="fas fa-plus me-2"></i>Add First Supplier
    </a>
  </div>
  <?php else: ?>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 datatable">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Contacts</th>
          <th>Website</th>
          <th>Added Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($suppliers as $i => $s): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= e($s['name']) ?></td>
          <td class="text-muted small"><?= e($s['email'] ?? '—') ?></td>
          <td>
            <?php 
            $contacts = json_decode($s['contacts'] ?? '[]', true) ?: []; 
            echo count($contacts) . ' Contact(s)';
            ?>
          </td>
          <td class="text-muted small">
             <?php if (!empty($s['website'])): ?>
                <a href="<?= e($s['website']) ?>" target="_blank" rel="noopener noreferrer"><?= e($s['website']) ?></a>
             <?php else: ?>
                —
             <?php endif; ?>
          </td>
          <td class="text-muted small"><?= niceDate($s['created_at']) ?></td>   
          <td>
            <a href="<?= BASE_URL ?>/suppliers/view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="View profile"><i class="fas fa-eye"></i></a>
            <a href="<?= BASE_URL ?>/suppliers/manage.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="Edit"><i class="fas fa-edit"></i></a>
            <a href="<?= BASE_URL ?>/suppliers/delete.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" data-confirm="Are you sure you want to delete this supplier?"><i class="fas fa-trash"></i></a>
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
    order: [[0, 'asc']],
    searching: true,
    dom: '<\"table-responsive\"t><\"row pt-3 pb-2 px-3 align-items-center\"<\"col-md-5 small text-muted\"i><\"col-md-7 d-flex justify-content-end mb-0\"p>>',
    language: {
      paginate: {
        previous: '<i class=\"fas fa-chevron-left\"></i>',
        next: '<i class=\"fas fa-chevron-right\"></i>'
      }
    }
  });

  // Link custom search input to datatable search
  $('#customSearch').on('keyup', function() {
    table.search(this.value).draw();
  });

  // Link custom entries length drop-down to datatable sizing
  $('#customLength').on('change', function() {
    table.page.len(this.value).draw();
  });
});
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>
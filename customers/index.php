<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$pageTitle = 'Customers';
$db = getDB();

// Stats
$total  = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$thisMonth = $db->query("SELECT COUNT(*) FROM customers WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

// Customers with last activity
$customers = $db->query("
    SELECT c.*,
        COUNT(DISTINCT s.id)  AS sale_count,
        COUNT(DISTINCT r.id)  AS repair_count,
        MAX(GREATEST(COALESCE(s.created_at,'1970-01-01'), COALESCE(r.created_at,'1970-01-01'))) AS last_activity
    FROM customers c
    LEFT JOIN sales s ON s.customer_id = c.id
    LEFT JOIN repair_jobs r ON r.customer_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-users me-2 text-primary"></i>Customers</h4>
    <p>Manage your customer database and view transaction history.</p>
  </div>
  <a href="<?= BASE_URL ?>/customers/manage.php" class="btn btn-primary">
    <i class="fas fa-plus me-2"></i>Add Customer
  </a>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div class="stat-value"><?= number_format($total) ?></div>
      <div class="stat-label">Total Customers</div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card" style="--accent:#16a34a">
      <div class="stat-icon" style="background:rgba(22,163,74,.15);color:#16a34a"><i class="fas fa-user-plus"></i></div>
      <div class="stat-value"><?= $thisMonth ?></div>
      <div class="stat-label">New This Month</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0 datatable">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Sales</th>
          <th>Repairs</th>
          <th>Last Activity</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $i => $c): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= e($c['name']) ?></td>
          <td><?= e($c['phone'] ?? '—') ?></td>
          <td class="text-muted small"><?= e($c['email'] ?? '—') ?></td>
          <td><span class="badge bg-secondary-subtle text-secondary"><?= $c['sale_count'] ?></span></td>
          <td><span class="badge bg-secondary-subtle text-secondary"><?= $c['repair_count'] ?></span></td>
          <td class="text-muted small"><?= $c['last_activity'] ? niceDate($c['last_activity']) : '—' ?></td>
          <td class="text-muted small"><?= niceDate($c['created_at']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="View profile"><i class="fas fa-eye"></i></a>
            <a href="<?= BASE_URL ?>/customers/manage.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-edit"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($customers)): ?>
        <tr><td colspan="9" class="text-center text-muted py-5">No customers yet. <a href="<?= BASE_URL ?>/customers/manage.php">Add the first one</a>.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

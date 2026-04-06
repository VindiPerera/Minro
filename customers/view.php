<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/customers/index.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT * FROM customers WHERE id=?");
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) { header('Location: ' . BASE_URL . '/customers/index.php'); exit; }

$pageTitle = e($customer['name']);

// Sales history
$sales = $db->prepare("
    SELECT s.*, u.name as cashier_name,
        COUNT(si.id) as item_count
    FROM sales s
    LEFT JOIN users u ON u.id = s.cashier_id
    LEFT JOIN sale_items si ON si.sale_id = s.id
    WHERE s.customer_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$sales->execute([$id]);
$sales = $sales->fetchAll();

// Repair history
$repairs = $db->prepare("
    SELECT r.*, u.name as tech_name
    FROM repair_jobs r
    LEFT JOIN users u ON u.id = r.assigned_to
    WHERE r.customer_id = ?
    ORDER BY r.created_at DESC
");
$repairs->execute([$id]);
$repairs = $repairs->fetchAll();

// Totals
$totalSales   = array_sum(array_column($sales, 'total'));
$totalRepairs = $db->prepare("SELECT SUM(total) FROM repair_invoices ri JOIN repair_jobs rj ON rj.id=ri.job_id WHERE rj.customer_id=?");
$totalRepairs->execute([$id]);
$totalRepairs = $totalRepairs->fetchColumn() ?? 0;

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-user me-2 text-primary"></i><?= e($customer['name']) ?></h4>
    <p>Full transaction history and profile.</p>
  </div>
  <div>
    <a href="<?= BASE_URL ?>/customers/manage.php?id=<?= $id ?>" class="btn btn-outline-secondary me-2">
      <i class="fas fa-edit me-1"></i>Edit
    </a>
    <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Profile Card -->
  <div class="col-lg-3">
    <div class="card mb-4">
      <div class="card-body text-center">
        <div style="width:72px;height:72px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff;margin:0 auto 12px">
          <?= strtoupper(mb_substr($customer['name'],0,1)) ?>
        </div>
        <h5 class="mb-0"><?= e($customer['name']) ?></h5>
        <?php if ($customer['phone']): ?>
        <p class="text-muted mb-1"><i class="fas fa-phone-alt me-1"></i><?= e($customer['phone']) ?></p>
        <?php endif; ?>
        <?php if ($customer['email']): ?>
        <p class="text-muted small mb-1"><?= e($customer['email']) ?></p>
        <?php endif; ?>
        <?php if ($customer['address']): ?>
        <p class="text-muted small"><?= e($customer['address']) ?></p>
        <?php endif; ?>
        <?php if ($customer['notes']): ?>
        <div class="alert alert-secondary text-start small py-2 mt-2"><?= e($customer['notes']) ?></div>
        <?php endif; ?>
        <small class="text-muted d-block mt-2">Customer since <?= niceDate($customer['created_at']) ?></small>
      </div>
    </div>
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Total Sales</span>
          <strong><?= money($totalSales) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Repair Revenue</span>
          <strong><?= money($totalRepairs) ?></strong>
        </div>
        <hr>
        <div class="d-flex justify-content-between">
          <span class="text-muted fw-semibold">Lifetime Value</span>
          <strong class="text-primary"><?= money($totalSales + $totalRepairs) ?></strong>
        </div>
      </div>
    </div>
  </div>

  <!-- History Tabs -->
  <div class="col-lg-9">
    <ul class="nav nav-tabs mb-3" id="histTabs">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#salesTab">
          <i class="fas fa-shopping-cart me-1"></i>Sales <span class="badge bg-secondary ms-1"><?= count($sales) ?></span>
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#repairsTab">
          <i class="fas fa-tools me-1"></i>Repairs <span class="badge bg-secondary ms-1"><?= count($repairs) ?></span>
        </button>
      </li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="salesTab">
        <div class="card">
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
              <thead><tr><th>Invoice</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($sales as $s): ?>
                <tr>
                  <td class="fw-semibold small"><?= e($s['invoice_number']) ?></td>
                  <td class="text-muted small"><?= niceDateTime($s['created_at']) ?></td>
                  <td><?= $s['item_count'] ?></td>
                  <td><?= money($s['total']) ?></td>
                  <td><span class="badge bg-secondary-subtle text-secondary"><?= ucfirst($s['payment_method']) ?></span></td>
                  <td><a href="<?= BASE_URL ?>/pos/receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary"><i class="fas fa-receipt"></i></a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sales)): ?><tr><td colspan="6" class="text-center text-muted py-4">No sales found.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="repairsTab">
        <div class="card">
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
              <thead><tr><th>Job #</th><th>Device</th><th>Status</th><th>Technician</th><th>Date</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($repairs as $r): ?>
                <tr>
                  <td class="fw-semibold small"><?= e($r['job_number']) ?></td>
                  <td class="small"><?= e($r['device_brand']) ?> <?= e($r['device_model']) ?></td>
                  <td><?= jobStatusBadge($r['status']) ?></td>
                  <td class="text-muted small"><?= e($r['tech_name'] ?? '—') ?></td>
                  <td class="text-muted small"><?= niceDate($r['created_at']) ?></td>
                  <td>
                    <a href="<?= BASE_URL ?>/repairs/view.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary me-1"><i class="fas fa-eye"></i></a>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($repairs)): ?><tr><td colspan="6" class="text-center text-muted py-4">No repairs found.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

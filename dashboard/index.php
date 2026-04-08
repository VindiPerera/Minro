<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$pageTitle = 'Dashboard';
$db = getDB();

// Stats
$todaySales = $db->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'")->fetchColumn();
$todayOrders = $db->query("SELECT COUNT(*) FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'")->fetchColumn();
$pendingRepairs = $db->query("SELECT COUNT(*) FROM repair_jobs WHERE status IN ('pending','in_progress','waiting_parts')")->fetchColumn();
$completedRepairs = $db->query("SELECT COUNT(*) FROM repair_jobs WHERE status='completed'")->fetchColumn();
$totalCustomers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$lowStockCount = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= low_stock_threshold AND status=1")->fetchColumn();
$monthSales = $db->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE()) AND status='completed'")->fetchColumn();
$repairRevenue = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM repair_invoices WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();

// Recent Sales
$recentSales = $db->query("SELECT s.*, COALESCE(c.name,'Walk-in') as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id ORDER BY s.created_at DESC LIMIT 8")->fetchAll();

// Recent Repair Jobs
$recentJobs = $db->query("SELECT r.*, COALESCE(c.name,'—') as customer_name, COALESCE(u.name,'Unassigned') as tech_name FROM repair_jobs r LEFT JOIN customers c ON r.customer_id=c.id LEFT JOIN users u ON r.assigned_to=u.id ORDER BY r.created_at DESC LIMIT 6")->fetchAll();

// Low Stock Products
$lowStock = $db->query("SELECT * FROM products WHERE stock_quantity <= low_stock_threshold AND status=1 ORDER BY stock_quantity ASC LIMIT 5")->fetchAll();

// 7-day Sales Chart Data
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $amt  = $db->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(sale_date)=? AND status='completed'");
    $amt->execute([$date]);
    $chartData[] = ['date' => date('D', strtotime($date)), 'amount' => (float)$amt->fetchColumn()];
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4>Dashboard</h4>
    <p>Welcome back, <?= e(currentUser()['name']) ?>! Here's what's happening today.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/pos/index.php" class="btn btn-primary">
      <i class="fas fa-cash-register me-2"></i>Open POS
    </a>
    <a href="<?= BASE_URL ?>/repairs/create.php" class="btn btn-outline-primary">
      <i class="fas fa-plus me-2"></i>New Repair Job
    </a>
  </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="stat-icon" style="background:rgba(37,99,235,.2)">
          <i class="fas fa-dollar-sign" style="color:#60a5fa"></i>
        </div>
        <span class="badge bg-success">Today</span>
      </div>
      <div class="stat-value"><?= money((float)$todaySales) ?></div>
      <div class="stat-label mt-1">Today's Sales</div>
      <div class="stat-change up mt-1"><i class="fas fa-receipt me-1"></i><?= $todayOrders ?> transactions</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="stat-icon" style="background:rgba(124,58,237,.2)">
          <i class="fas fa-chart-line" style="color:#a78bfa"></i>
        </div>
        <span class="badge bg-info">Month</span>
      </div>
      <div class="stat-value"><?= money((float)$monthSales) ?></div>
      <div class="stat-label mt-1">Monthly Sales</div>
      <div class="stat-change up mt-1"><i class="fas fa-tools me-1"></i>Repair: <?= money((float)$repairRevenue) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="stat-icon" style="background:rgba(217,119,6,.2)">
          <i class="fas fa-tools" style="color:#fbbf24"></i>
        </div>
        <span class="badge bg-warning text-dark"><?= $pendingRepairs ?> Active</span>
      </div>
      <div class="stat-value"><?= $pendingRepairs ?></div>
      <div class="stat-label mt-1">Pending Repairs</div>
      <div class="stat-change mt-1" style="color:#86efac"><i class="fas fa-check me-1"></i><?= $completedRepairs ?> completed</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="stat-icon" style="background:rgba(220,38,38,.2)">
          <i class="fas fa-exclamation-triangle" style="color:#f87171"></i>
        </div>
        <?php if ($lowStockCount > 0): ?>
        <span class="badge bg-danger"><?= $lowStockCount ?> Alerts</span>
        <?php endif; ?>
      </div>
      <div class="stat-value"><?= $totalCustomers ?></div>
      <div class="stat-label mt-1">Total Customers</div>
      <?php if ($lowStockCount > 0): ?>
      <div class="stat-change down mt-1"><i class="fas fa-box me-1"></i><?= $lowStockCount ?> low stock items</div>
      <?php else: ?>
      <div class="stat-change up mt-1"><i class="fas fa-check me-1"></i>All stock OK</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-line me-2 text-primary"></i>Sales (Last 7 Days)</span>
        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-sm btn-outline-primary">View Report</a>
      </div>
      <div class="card-body">
        <canvas id="salesChart" height="100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Alert</div>
      <div class="card-body p-0">
        <?php if (empty($lowStock)): ?>
        <div class="p-4 text-center">
          <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
          <p class="text-muted mb-0 small">All products are well stocked!</p>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($lowStock as $p): ?>
          <a href="<?= BASE_URL ?>/inventory/index.php" class="list-group-item list-group-item-action px-4 py-3" style="background:transparent;border-color:var(--border);color:var(--text-primary)">
            <div class="d-flex justify-content-between">
              <div>
                <div class="fw-semibold small"><?= e($p['name']) ?></div>
                <div class="text-muted" style="font-size:11px"><?= e($p['code']) ?></div>
              </div>
              <span class="badge <?= $p['stock_quantity'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= $p['stock_quantity'] ?> left</span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <div class="p-3 border-top" style="border-color:var(--border)!important">
          <a href="<?= BASE_URL ?>/inventory/index.php?filter=low_stock" class="btn btn-sm btn-outline-warning w-100">View All Low Stock</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Recent Transactions & Repair Jobs -->
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-receipt me-2 text-primary"></i>Recent Sales</span>
        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr>
              <th>Invoice</th><th>Customer</th><th>Amount</th><th>Payment</th><th>Time</th>
            </tr></thead>
            <tbody>
              <?php foreach ($recentSales as $s): ?>
              <tr>
                <td><a href="<?= BASE_URL ?>/pos/receipt.php?id=<?= $s['id'] ?>" class="text-info fw-semibold small"><?= e($s['invoice_number']) ?></a></td>
                <td class="small"><?= e($s['customer_name']) ?></td>
                <td class="fw-semibold"><?= money((float)$s['total']) ?></td>
                <td><span class="badge bg-secondary"><?= ucfirst($s['payment_method']) ?></span></td>
                <td class="text-muted small"><?= date('h:i A', strtotime($s['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($recentSales)): ?>
              <tr><td colspan="5" class="text-center py-4 text-muted">No sales today</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-tools me-2 text-warning"></i>Active Repair Jobs</span>
        <a href="<?= BASE_URL ?>/repairs/index.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <?php foreach ($recentJobs as $j): ?>
        <a href="<?= BASE_URL ?>/repairs/view.php?id=<?= $j['id'] ?>" class="d-block text-decoration-none" style="color:inherit">
          <div class="px-4 py-3 border-bottom" style="border-color:#334155!important">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <div class="fw-semibold small text-info"><?= e($j['job_number']) ?></div>
              <?= jobStatusBadge($j['status']) ?>
            </div>
            <div class="small" style="color:var(--text-primary)"><?= e($j['device_brand']) ?> <?= e($j['device_model']) ?></div>
            <div class="d-flex justify-content-between mt-1">
              <span style="font-size:11px;color:#64748b"><?= e($j['customer_name']) ?></span>
              <?= priorityBadge($j['priority']) ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
        <?php if (empty($recentJobs)): ?>
        <div class="p-4 text-center text-muted small">No active repair jobs</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$chartLabels  = json_encode(array_column($chartData, 'date'));
$chartAmounts = json_encode(array_column($chartData, 'amount'));
$extraScripts = "
<script>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: $chartLabels,
    datasets: [{
      label: 'Sales (" . setting('currency_symbol', 'Rs.') . ")',
      data: $chartAmounts,
      borderColor: '#2563eb',
      backgroundColor: 'rgba(37,99,235,.1)',
      tension: 0.4,
      fill: true,
      pointBackgroundColor: '#2563eb',
      pointRadius: 5,
      pointHoverRadius: 8
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ' Rs. ' + ctx.raw.toLocaleString() } }
    },
    scales: {
      x: { grid: { color: (document.body.classList.contains('light-theme') || document.documentElement.classList.contains('light-theme')) ? '#e2e8f0' : '#334155' }, ticks: { color: '#64748b' } },
      y: { grid: { color: (document.body.classList.contains('light-theme') || document.documentElement.classList.contains('light-theme')) ? '#e2e8f0' : '#334155' }, ticks: { color: '#64748b', callback: v => 'Rs. ' + v.toLocaleString() } }
    }
  }
});
</script>";
require_once __DIR__ . '/../includes/footer.php';
?>

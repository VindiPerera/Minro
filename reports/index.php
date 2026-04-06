<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin', 'cashier');

$pageTitle = 'Reports';
$db = getDB();

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');
$tab      = $_GET['tab']  ?? 'sales';

// ============ SALES REPORT ============
$salesRows = [];
$salesTotal = $salesTotalByMethod = [];
if ($tab === 'sales') {
    $salesRows = $db->prepare("
        SELECT s.*, c.name as customer_name, u.name as cashier_name,
               COUNT(si.id) as item_count
        FROM sales s
        LEFT JOIN customers c ON c.id = s.customer_id
        LEFT JOIN users u ON u.id = s.cashier_id
        LEFT JOIN sale_items si ON si.sale_id = s.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY s.id
        ORDER BY s.created_at DESC
    ");
    $salesRows->execute([$dateFrom, $dateTo]);
    $salesRows = $salesRows->fetchAll();

    // By payment method
    $methodQ = $db->prepare("SELECT payment_method, SUM(total) as total, COUNT(*) as cnt FROM sales WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY payment_method");
    $methodQ->execute([$dateFrom, $dateTo]);
    $salesTotalByMethod = $methodQ->fetchAll();
}

// ============ REPAIRS REPORT ============
$repairRows = [];
if ($tab === 'repairs') {
    $repairRows = $db->prepare("
        SELECT r.*, c.name as customer_name, u.name as tech_name,
            ri.total as invoice_total, ri.payment_status
        FROM repair_jobs r
        LEFT JOIN customers c ON c.id = r.customer_id
        LEFT JOIN users u ON u.id = r.assigned_to
        LEFT JOIN repair_invoices ri ON ri.job_id = r.id
        WHERE DATE(r.created_at) BETWEEN ? AND ?
        ORDER BY r.created_at DESC
    ");
    $repairRows->execute([$dateFrom, $dateTo]);
    $repairRows = $repairRows->fetchAll();
}

// ============ STOCK REPORT ============
$stockRows = [];
if ($tab === 'stock') {
    $stockRows = $db->prepare("
        SELECT sm.*, p.name as product_name, p.code as product_code, u.name as user_name
        FROM stock_movements sm
        JOIN products p ON p.id = sm.product_id
        LEFT JOIN users u ON u.id = sm.created_by
        WHERE DATE(sm.created_at) BETWEEN ? AND ?
        ORDER BY sm.created_at DESC
    ");
    $stockRows->execute([$dateFrom, $dateTo]);
    $stockRows = $stockRows->fetchAll();
}

// ============ SERVICE USAGE ============
$serviceRows = [];
if ($tab === 'services') {
    $serviceRows = $db->prepare("
        SELECT p.code, p.name, p.type,
            SUM(CASE WHEN sm.reference_type='sale' THEN sm.quantity ELSE 0 END) as retail_qty,
            SUM(CASE WHEN sm.reference_type='repair_parts' THEN sm.quantity ELSE 0 END) as repair_qty,
            SUM(sm.quantity) as total_qty
        FROM stock_movements sm
        JOIN products p ON p.id = sm.product_id
        WHERE sm.movement_type IN ('sale','repair_use') AND DATE(sm.created_at) BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY total_qty DESC
    ");
    $serviceRows->execute([$dateFrom, $dateTo]);
    $serviceRows = $serviceRows->fetchAll();

    $repairServices = $db->prepare("
        SELECT rs.name as service_name, COUNT(rjs.id) as usage_count, SUM(rjs.price) as revenue
        FROM repair_job_services rjs
        JOIN repair_services rs ON rs.id = rjs.service_id
        JOIN repair_jobs rj ON rj.id = rjs.job_id
        WHERE DATE(rj.created_at) BETWEEN ? AND ?
        GROUP BY rs.id
        ORDER BY usage_count DESC
    ");
    $repairServices->execute([$dateFrom, $dateTo]);
    $repairServices = $repairServices->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-chart-bar me-2 text-primary"></i>Reports</h4>
    <p>Analyse your business performance, stock usage, and service activity.</p>
  </div>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
</div>

<!-- Filter Bar -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <div class="col-auto">
        <label class="form-label mb-1 small text-muted">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1 small text-muted">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
        <a href="?tab=<?= e($tab) ?>" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
      </div>
      <div class="col-auto ms-auto">
        <small class="text-muted">Showing: <?= niceDate($dateFrom) ?> — <?= niceDate($dateTo) ?></small>
      </div>
    </form>
  </div>
</div>

<!-- Report Tabs -->
<ul class="nav nav-tabs mb-4">
  <?php foreach (['sales'=>'Sales','repairs'=>'Repairs','stock'=>'Stock Movements','services'=>'Service Usage'] as $t => $label): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$t?'active':'' ?>" href="?tab=<?= $t ?>&from=<?= $dateFrom ?>&to=<?= $dateTo ?>">
      <?= $label ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'sales'): ?>
<!-- Sales Summary Cards -->
<div class="row g-3 mb-4">
  <?php
  $grandTotal = array_sum(array_column($salesRows,'total'));
  $grandDisc  = array_sum(array_column($salesRows,'discount'));
  ?>
  <div class="col-sm-4">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-receipt"></i></div>
      <div class="stat-value"><?= count($salesRows) ?></div><div class="stat-label">Transactions</div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card" style="--accent:#16a34a"><div class="stat-icon" style="background:rgba(22,163,74,.15);color:#16a34a"><i class="fas fa-coins"></i></div>
      <div class="stat-value"><?= money($grandTotal) ?></div><div class="stat-label">Total Revenue</div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card" style="--accent:#d97706"><div class="stat-icon" style="background:rgba(217,119,6,.15);color:#d97706"><i class="fas fa-tag"></i></div>
      <div class="stat-value"><?= money($grandDisc) ?></div><div class="stat-label">Total Discounts</div></div>
  </div>
</div>

<?php if ($salesTotalByMethod): ?>
<div class="row g-3 mb-4">
  <?php foreach ($salesTotalByMethod as $m): ?>
  <div class="col-sm-4">
    <div class="card"><div class="card-body py-3">
      <div class="d-flex justify-content-between">
        <span><?= ucfirst($m['payment_method']) ?></span>
        <span class="fw-bold"><?= money($m['total']) ?></span>
      </div>
      <small class="text-muted"><?= $m['cnt'] ?> transactions</small>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header fw-semibold">Sales Transactions</div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0 datatable">
      <thead><tr><th>Invoice</th><th>Date/Time</th><th>Customer</th><th>Items</th><th>Discount</th><th>Total</th><th>Payment</th><th>Cashier</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($salesRows as $s): ?>
        <tr>
          <td class="fw-semibold small"><?= e($s['invoice_number']) ?></td>
          <td class="text-muted small text-nowrap"><?= niceDateTime($s['created_at']) ?></td>
          <td class="small"><?= e($s['customer_name'] ?? 'Walk-in') ?></td>
          <td><?= $s['item_count'] ?></td>
          <td><?= $s['discount'] > 0 ? money($s['discount']) : '—' ?></td>
          <td class="fw-semibold"><?= money($s['total']) ?></td>
          <td><span class="badge bg-secondary-subtle text-secondary"><?= ucfirst($s['payment_method']) ?></span></td>
          <td class="small text-muted"><?= e($s['cashier_name'] ?? '—') ?></td>
          <td><a href="<?= BASE_URL ?>/pos/receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary"><i class="fas fa-receipt"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($salesRows)): ?><tr><td colspan="9" class="text-center text-muted py-5">No sales in this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'repairs'): ?>
<?php
$repairRevenue = array_sum(array_column($repairRows,'invoice_total'));
$statusCounts  = array_count_values(array_column($repairRows,'status'));
?>
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-tools"></i></div>
      <div class="stat-value"><?= count($repairRows) ?></div><div class="stat-label">Repair Jobs</div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card" style="--accent:#16a34a"><div class="stat-icon" style="background:rgba(22,163,74,.15);color:#16a34a"><i class="fas fa-coins"></i></div>
      <div class="stat-value"><?= money($repairRevenue) ?></div><div class="stat-label">Repair Revenue</div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card" style="--accent:#2563eb"><div class="stat-icon" style="background:rgba(37,99,235,.15);color:#2563eb"><i class="fas fa-check-circle"></i></div>
      <div class="stat-value"><?= ($statusCounts['delivered']??0) ?></div><div class="stat-label">Delivered</div></div>
  </div>
</div>
<div class="card">
  <div class="card-header fw-semibold">Repair Jobs</div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0 datatable">
      <thead><tr><th>Job #</th><th>Date</th><th>Customer</th><th>Device</th><th>Status</th><th>Technician</th><th>Invoice Total</th><th>Payment</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($repairRows as $r): ?>
        <tr>
          <td class="fw-semibold small"><?= e($r['job_number']) ?></td>
          <td class="text-muted small text-nowrap"><?= niceDate($r['created_at']) ?></td>
          <td class="small"><?= e($r['customer_name'] ?? '—') ?></td>
          <td class="small"><?= e($r['device_brand'].' '.$r['device_model']) ?></td>
          <td><?= jobStatusBadge($r['status']) ?></td>
          <td class="small text-muted"><?= e($r['tech_name'] ?? 'Unassigned') ?></td>
          <td><?= $r['invoice_total'] ? money($r['invoice_total']) : '—' ?></td>
          <td><?= $r['payment_status'] ? paymentStatusBadge($r['payment_status']) : '—' ?></td>
          <td><a href="<?= BASE_URL ?>/repairs/view.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="fas fa-eye"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($repairRows)): ?><tr><td colspan="9" class="text-center text-muted py-5">No repairs in this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'stock'): ?>
<div class="card">
  <div class="card-header fw-semibold">Stock Movements</div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0 datatable">
      <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Reference</th><th>Notes</th><th>By</th></tr></thead>
      <tbody>
        <?php foreach ($stockRows as $m): ?>
        <?php
        $typeColour = match($m['movement_type']) {
            'purchase'   => 'success',
            'sale'       => 'primary',
            'repair_use' => 'warning',
            'return'     => 'info',
            'adjustment' => 'secondary',
            default      => 'secondary',
        };
        $sign = in_array($m['movement_type'],['purchase','return']) ? '+' : '-';
        ?>
        <tr>
          <td class="text-muted small text-nowrap"><?= niceDateTime($m['created_at']) ?></td>
          <td>
            <div class="fw-semibold small"><?= e($m['product_name']) ?></div>
            <small class="text-muted"><?= e($m['product_code']) ?></small>
          </td>
          <td><span class="badge bg-<?= $typeColour ?>-subtle text-<?= $typeColour ?>"><?= ucfirst(str_replace('_',' ',$m['movement_type'])) ?></span></td>
          <td class="fw-semibold <?= $sign==='+' ? 'text-success':'text-danger' ?>"><?= $sign ?><?= $m['quantity'] ?></td>
          <td class="small text-muted"><?= e($m['reference_type'] ?? '—') ?></td>
          <td class="small text-muted"><?= e($m['notes'] ?? '') ?></td>
          <td class="small text-muted"><?= e($m['user_name'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($stockRows)): ?><tr><td colspan="7" class="text-center text-muted py-5">No stock movements in this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'services'): ?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold">Parts Usage (Retail vs Repair)</div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0 datatable">
          <thead><tr><th>Product</th><th>Type</th><th>Retail</th><th>Repair</th><th>Total</th></tr></thead>
          <tbody>
            <?php foreach ($serviceRows as $s): ?>
            <tr>
              <td>
                <div class="small fw-semibold"><?= e($s['name']) ?></div>
                <small class="text-muted"><?= e($s['code']) ?></small>
              </td>
              <td><span class="badge bg-secondary-subtle text-secondary small"><?= e($s['type']) ?></span></td>
              <td><?= $s['retail_qty'] ?: '—' ?></td>
              <td><?= $s['repair_qty'] ?: '—' ?></td>
              <td class="fw-semibold"><?= $s['total_qty'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($serviceRows)): ?><tr><td colspan="5" class="text-center text-muted py-4">No data.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold">Repair Services Usage</div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0 datatable">
          <thead><tr><th>Service</th><th>Jobs</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach (($repairServices ?? []) as $s): ?>
            <tr>
              <td class="small fw-semibold"><?= e($s['service_name']) ?></td>
              <td><?= $s['usage_count'] ?></td>
              <td><?= money($s['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($repairServices)): ?><tr><td colspan="3" class="text-center text-muted py-4">No data.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

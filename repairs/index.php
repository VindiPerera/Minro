<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$pageTitle = 'Repair Jobs';
$db = getDB();

// Filters
$status   = $_GET['status']   ?? 'all';
$priority = $_GET['priority'] ?? 'all';
$search   = trim($_GET['search'] ?? '');
$tech     = $_GET['tech']     ?? 'all';

ensureRepairJobBarcodes();

if ($search !== '') {
  $scanValue = compactBarcodeValue($search);
  if ($scanValue !== '') {
    $scanStmt = $db->prepare("SELECT id FROM repair_jobs
                  WHERE REPLACE(UPPER(TRIM(barcode)), ' ', '')=?
                   OR REPLACE(UPPER(TRIM(job_number)), ' ', '')=?
                  LIMIT 1");
    $scanStmt->execute([$scanValue, $scanValue]);
    $matchedJobId = (int)$scanStmt->fetchColumn();
    if ($matchedJobId > 0) {
      header('Location: ' . BASE_URL . '/repairs/view.php?id=' . $matchedJobId);
      exit;
    }
  }
}

// Build WHERE
$where = ['1=1'];
$params = [];
if ($status !== 'all')   { $where[] = 'r.status=?'; $params[] = $status; }
if ($priority !== 'all') { $where[] = 'r.priority=?'; $params[] = $priority; }
if ($tech !== 'all')     { $where[] = 'r.assigned_to=?'; $params[] = $tech; }
if ($search)             { $where[] = '(r.job_number LIKE ? OR r.barcode LIKE ? OR c.name LIKE ? OR r.device_brand LIKE ? OR r.device_model LIKE ? OR r.device_imei LIKE ?)'; $s = "%$search%"; $params = array_merge($params, [$s,$s,$s,$s,$s,$s]); }

$whereClause = implode(' AND ', $where);
$sql = "SELECT r.*, COALESCE(c.name,'—') as cname, COALESCE(c.phone,'') as cphone, COALESCE(u.name,'Unassigned') as tech_name FROM repair_jobs r LEFT JOIN customers c ON r.customer_id=c.id LEFT JOIN users u ON r.assigned_to=u.id WHERE $whereClause ORDER BY FIELD(r.status,'in_progress','pending','waiting_parts','completed','delivered','cancelled'), FIELD(r.priority,'express','urgent','normal'), r.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$technicians = $db->query("SELECT id, name FROM users WHERE role='technician' AND status=1 ORDER BY name")->fetchAll();

// Status counts
$counts = [];
foreach (['all','pending','in_progress','waiting_parts','completed','delivered','cancelled'] as $s) {
    if ($s === 'all') {
        $counts[$s] = $db->query("SELECT COUNT(*) FROM repair_jobs")->fetchColumn();
    } else {
        $stmt2 = $db->prepare("SELECT COUNT(*) FROM repair_jobs WHERE status=?");
        $stmt2->execute([$s]);
        $counts[$s] = $stmt2->fetchColumn();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h4><i class="fas fa-tools me-2 text-warning"></i>Repair Jobs</h4>
    <p>Manage all repair jobs, assign technicians, and track progress.</p>
  </div>
  <a href="<?= BASE_URL ?>/repairs/create.php" class="btn btn-primary">
    <i class="fas fa-plus me-2"></i>New Repair Job
  </a>
</div>

<!-- Status Filter Tabs -->
<div class="d-flex gap-2 flex-wrap mb-4">
  <?php
  $tabs = ['all'=>'All','pending'=>'Pending','in_progress'=>'In Progress','waiting_parts'=>'Waiting Parts','completed'=>'Completed','delivered'=>'Delivered','cancelled'=>'Cancelled'];
  $tabColors = ['pending'=>'secondary','in_progress'=>'primary','waiting_parts'=>'warning','completed'=>'success','delivered'=>'info','cancelled'=>'danger'];
  foreach ($tabs as $s => $label):
      $active = ($status === $s) ? 'active fw-bold' : '';
      $color  = $tabColors[$s] ?? 'secondary';
      $cnt    = $counts[$s] ?? 0;
  ?>
  <a href="?status=<?= $s ?>&priority=<?= urlencode($priority) ?>&tech=<?= urlencode($tech) ?>&search=<?= urlencode($search) ?>"
     class="btn btn-sm <?= $active ? "btn-$color" : "btn-outline-$color" ?>">
    <?= $label ?> <span class="badge bg-dark ms-1"><?= $cnt ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filters Bar -->
<div class="card mb-4">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end mb-3">
      <div class="col-md-8">
        <label class="form-label small mb-1">Scan Repair Barcode</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="fas fa-barcode"></i></span>
          <input type="text" id="repairScanInput" class="form-control" placeholder="Scan barcode to open repair job instantly..." autocomplete="off">
          <button type="button" id="repairScanBtn" class="btn btn-primary"><i class="fas fa-search me-1"></i>Open</button>
        </div>
        <div id="repairScanStatus" class="small mt-1 text-muted">Ready to scan</div>
      </div>
    </div>

    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <div class="col-sm-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Scan barcode or search job#, customer, device..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-2">
        <select name="priority" class="form-select form-select-sm">
          <option value="all">All Priorities</option>
          <option value="normal" <?= $priority==='normal' ? 'selected':'' ?>>Normal</option>
          <option value="urgent" <?= $priority==='urgent' ? 'selected':'' ?>>Urgent</option>
          <option value="express" <?= $priority==='express' ? 'selected':'' ?>>Express</option>
        </select>
      </div>
      <div class="col-sm-3">
        <select name="tech" class="form-select form-select-sm">
          <option value="all">All Technicians</option>
          <?php foreach ($technicians as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $tech==$t['id'] ? 'selected':'' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fas fa-filter me-1"></i>Filter</button>
        <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
      </div>
    </form>
  </div>
</div>

<!-- Jobs Table -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="jobsTable">
        <thead>
          <tr>
            <th>Job #</th>
            <th>Customer</th>
            <th>Device</th>
            <th>Issue</th>
            <th>Technician</th>
            <th>Priority</th>
            <th>Est. Cost</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobs as $job): ?>
          <tr>
            <td>
              <a href="<?= BASE_URL ?>/repairs/view.php?id=<?= $job['id'] ?>" class="fw-bold text-info text-decoration-none small"><?= e($job['job_number']) ?></a>
            </td>
            <td>
              <div class="small fw-semibold"><?= e($job['cname']) ?></div>
              <?php if ($job['cphone']): ?><div class="text-muted" style="font-size:11px"><?= e($job['cphone']) ?></div><?php endif; ?>
            </td>
            <td class="small"><?= e($job['device_brand']) ?> <?= e($job['device_model']) ?></td>
            <td class="small text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($job['issue_description']) ?>">
              <?= e(substr($job['issue_description'] ?? '', 0, 40)) ?><?= strlen($job['issue_description'] ?? '') > 40 ? '...' : '' ?>
            </td>
            <td class="small"><?= e($job['tech_name']) ?></td>
            <td><?= priorityBadge($job['priority']) ?></td>
            <td class="small fw-semibold"><?= money((float)$job['estimated_cost']) ?></td>
            <td><?= jobStatusBadge($job['status']) ?></td>
            <td class="small text-muted"><?= niceDate($job['created_at']) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/repairs/view.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                <a href="<?= BASE_URL ?>/repairs/job_ticket.php?id=<?= $job['id'] ?>&print=1" class="btn btn-sm btn-outline-secondary" title="Print Ticket" target="_blank"><i class="fas fa-print"></i></a>
                <?php if ($job['status'] === 'completed'): ?>
                <a href="<?= BASE_URL ?>/repairs/invoice.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-success" title="Invoice"><i class="fas fa-file-invoice"></i></a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($jobs)): ?>
          <tr><td colspan="10" class="text-center py-5 text-muted">
            <i class="fas fa-tools fa-3x mb-3 d-block" style="color:#334155"></i>
            No repair jobs found
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraScripts = "
<script>
(function() {
  const scanInput = document.getElementById('repairScanInput');
  const scanBtn = document.getElementById('repairScanBtn');
  const statusEl = document.getElementById('repairScanStatus');
  if (!scanInput || !scanBtn || !statusEl) return;

  let lookupBusy = false;

  function setStatus(text, type) {
    statusEl.textContent = text;
    statusEl.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');
    statusEl.classList.add(type || 'text-muted');
  }

  function lookupRepairByScan() {
    const raw = scanInput.value || '';
    const code = raw.trim();
    if (!code || lookupBusy) return;

    lookupBusy = true;
    setStatus('Looking up repair job...', 'text-warning');

    $.getJSON('" . BASE_URL . "/api/repair_api.php', {
      action: 'get_by_barcode',
      barcode: code
    }).done(function(res) {
      if (res && res.success && res.view_url) {
        setStatus('Match found. Opening job...', 'text-success');
        window.location.href = res.view_url;
        return;
      }
      setStatus('No repair job found for scanned code.', 'text-danger');
    }).fail(function(xhr) {
      const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'No repair job found for scanned code.';
      setStatus(msg, 'text-danger');
    }).always(function() {
      lookupBusy = false;
      scanInput.select();
    });
  }

  scanBtn.addEventListener('click', function() {
    lookupRepairByScan();
  });

  scanInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      lookupRepairByScan();
    }
  });

  scanInput.addEventListener('focus', function() {
    setStatus('Ready to scan', 'text-muted');
  });

  setTimeout(function() { scanInput.focus(); }, 100);
})();
</script>
";
require_once __DIR__ . '/../includes/footer.php';
?>

<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin', 'cashier');

$pageTitle = 'Create Repair Job';
$db = getDB();

$customers   = $db->query("SELECT id, name, phone FROM customers ORDER BY name")->fetchAll();
$technicians = $db->query("SELECT id, name FROM users WHERE role='technician' AND status=1 ORDER BY name")->fetchAll();

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'customer_id'         => !empty($_POST['customer_id'])   ? (int)$_POST['customer_id']   : null,
        'new_customer_name'   => trim($_POST['new_customer_name'] ?? ''),
        'new_customer_phone'  => trim($_POST['new_customer_phone'] ?? ''),
        'new_customer_email'  => trim($_POST['new_customer_email'] ?? ''),
        'device_brand'        => trim($_POST['device_brand'] ?? ''),
        'device_model'        => trim($_POST['device_model'] ?? ''),
        'device_imei'         => trim($_POST['device_imei'] ?? ''),
        'device_color'        => trim($_POST['device_color'] ?? ''),
        'device_condition'    => trim($_POST['device_condition'] ?? ''),
        'issue_description'   => trim($_POST['issue_description'] ?? ''),
        'customer_complaint'  => trim($_POST['customer_complaint'] ?? ''),
        'estimated_cost'      => abs((float)($_POST['estimated_cost'] ?? 0)),
        'advance_payment'     => abs((float)($_POST['advance_payment'] ?? 0)),
        'advance_payment_method' => $_POST['advance_payment_method'] ?? 'cash',
        'status'              => 'pending',
        'priority'            => $_POST['priority'] ?? 'normal',
        'assigned_to'         => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
        'estimated_delivery'  => $_POST['estimated_delivery'] ?? null,
        'internal_notes'      => trim($_POST['internal_notes'] ?? ''),
        'service_names'       => $_POST['service_names'] ?? [],
        'service_prices'      => $_POST['service_prices'] ?? [],
    ];

    // Validate
    if (empty($data['device_brand']))      $errors[] = 'Device brand is required.';
    if (empty($data['device_model']))      $errors[] = 'Device model is required.';
    if (empty($data['issue_description'])) $errors[] = 'Issue description is required.';

    // Handle new customer creation
    $customerId = $data['customer_id'];
    if (!$customerId && $data['new_customer_name']) {
        if (empty($data['new_customer_phone'])) {
            $errors[] = 'Customer phone is required.';
        } else {
            $stmt = $db->prepare("INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)");
            $stmt->execute([$data['new_customer_name'], $data['new_customer_phone'], $data['new_customer_email']]);
            $customerId = (int)$db->lastInsertId();
        }
    } elseif (!$customerId) {
        $errors[] = 'Please select or create a customer.';
    }

    if (empty($errors)) {
        $jobNumber = generateRepairJobNumber();
        $barcode   = $jobNumber;

        $stmt = $db->prepare("INSERT INTO repair_jobs
            (job_number, customer_id, device_brand, device_model, device_imei, device_color, device_condition,
             issue_description, customer_complaint, estimated_cost, advance_payment, advance_payment_method,
             status, priority, assigned_to, cashier_id, barcode, estimated_delivery, internal_notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $jobNumber, $customerId,
            $data['device_brand'], $data['device_model'], $data['device_imei'], $data['device_color'], $data['device_condition'],
            $data['issue_description'], $data['customer_complaint'],
            $data['estimated_cost'], $data['advance_payment'], $data['advance_payment_method'],
            'pending', $data['priority'], $data['assigned_to'], $_SESSION['user_id'],
            $barcode,
            $data['estimated_delivery'] ?: null,
            $data['internal_notes']
        ]);
        $jobId = (int)$db->lastInsertId();

        // Add services
        foreach ($data['service_names'] as $idx => $svcName) {
            $svcName = trim($svcName);
            if (!$svcName) continue;
            $price = abs((float)($data['service_prices'][$idx] ?? 0));
            $db->prepare("INSERT INTO repair_job_services (job_id, service_id, service_name, price) VALUES (?,NULL,?,?)")
               ->execute([$jobId, $svcName, $price]);
        }

        // If assigned, change status to in_progress
        if ($data['assigned_to']) {
            $db->prepare("UPDATE repair_jobs SET status='in_progress' WHERE id=?")->execute([$jobId]);
        }

        setFlash('success', "Repair job {$jobNumber} created successfully!");
        header('Location: ' . BASE_URL . '/repairs/view.php?id=' . $jobId . '&print_ticket=1');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>
<?php foreach ($errors as $e): ?>
<div class="alert alert-danger"><?= e($e) ?></div>
<?php endforeach; ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-plus-circle me-2 text-primary"></i>New Repair Job</h4>
    <p>Create a new repair job and generate a job ticket with barcode.</p>
  </div>
  <a href="<?= BASE_URL ?>/repairs/index.php" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Back
  </a>
</div>

<form method="POST" id="repairForm">
<div class="row g-4">

  <!-- LEFT COLUMN -->
  <div class="col-lg-8">

    <!-- Customer Section -->
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-user me-2 text-primary"></i>Customer Information</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Select Existing Customer</label>
          <div class="input-group">
            <select name="customer_id" id="customerSelect" class="form-select select2">
              <option value="">— Create New Customer —</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= isset($data['customer_id']) && $data['customer_id']==$c['id'] ? 'selected':'' ?>>
                <?= e($c['name']) ?> — <?= e($c['phone']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <span class="input-group-text text-muted small">or</span>
          </div>
        </div>
        <div id="newCustomerSection">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" name="new_customer_name" class="form-control" placeholder="Full name" value="<?= e($data['new_customer_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Phone <span class="text-danger">*</span></label>
              <input type="text" name="new_customer_phone" class="form-control" placeholder="Phone number" value="<?= e($data['new_customer_phone'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Email</label>
              <input type="email" name="new_customer_email" class="form-control" placeholder="Email (optional)" value="<?= e($data['new_customer_email'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Device Section -->
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-mobile-alt me-2 text-info"></i>Device Details</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Brand <span class="text-danger">*</span></label>
            <input type="text" name="device_brand" class="form-control" placeholder="e.g. Samsung, Apple, Xiaomi" list="brandList" value="<?= e($data['device_brand'] ?? '') ?>" required>
            <datalist id="brandList">
              <option>Samsung</option><option>Apple</option><option>Xiaomi</option><option>Huawei</option>
              <option>OnePlus</option><option>Oppo</option><option>Vivo</option><option>Realme</option>
              <option>Nokia</option><option>Sony</option><option>LG</option><option>Motorola</option>
            </datalist>
          </div>
          <div class="col-md-6">
            <label class="form-label">Model <span class="text-danger">*</span></label>
            <input type="text" name="device_model" class="form-control" placeholder="e.g. Galaxy S21, iPhone 13" value="<?= e($data['device_model'] ?? '') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">IMEI / Serial</label>
            <input type="text" name="device_imei" class="form-control" placeholder="IMEI or serial number" value="<?= e($data['device_imei'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Color</label>
            <input type="text" name="device_color" class="form-control" placeholder="e.g. Black, White" value="<?= e($data['device_color'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Physical Condition</label>
            <select name="device_condition" class="form-select">
              <option value="">Select condition</option>
              <option value="Excellent">Excellent</option>
              <option value="Good - Minor scratches">Good - Minor scratches</option>
              <option value="Fair - Visible damage">Fair - Visible damage</option>
              <option value="Poor - Heavy damage">Poor - Heavy damage</option>
              <option value="Cracked screen">Cracked screen</option>
              <option value="Water damaged">Water damaged</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Issue Section -->
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-clipboard me-2 text-warning"></i>Issue & Complaint</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Issue Description <span class="text-danger">*</span></label>
            <textarea name="issue_description" class="form-control" rows="3" placeholder="Describe the technical issue..." required><?= e($data['issue_description'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Customer Complaint (in customer's words)</label>
            <textarea name="customer_complaint" class="form-control" rows="2" placeholder="What the customer described..."><?= e($data['customer_complaint'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Internal Notes (not visible to customer)</label>
            <textarea name="internal_notes" class="form-control" rows="2" placeholder="Internal notes for technicians..."><?= e($data['internal_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Services Section -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-wrench me-2 text-success"></i>Services</span>
        <button type="button" class="btn btn-sm btn-outline-success" id="addServiceRow"><i class="fas fa-plus me-1"></i>Add Service</button>
      </div>
      <div class="card-body">
        <div id="servicesContainer">
          <div class="service-row row g-2 mb-2">
            <div class="col-md-8">
              <input type="text" name="service_names[]" class="form-control" placeholder="Service description (e.g. Screen Replacement)">
            </div>
            <div class="col-md-3">
              <div class="input-group">
                <span class="input-group-text">Rs.</span>
                <input type="number" name="service_prices[]" class="form-control service-price" placeholder="0.00" step="0.01" min="0">
              </div>
            </div>
            <div class="col-md-1">
              <button type="button" class="btn btn-outline-danger btn-sm remove-service w-100"><i class="fas fa-times"></i></button>
            </div>
          </div>
        </div>
        <div class="mt-2 small text-muted"><i class="fas fa-info-circle me-1"></i>Type the service name and enter the agreed price.</div>
      </div>
    </div>

  </div><!-- end col-lg-8 -->

  <!-- RIGHT COLUMN -->
  <div class="col-lg-4">

    <!-- Job Settings -->
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-cogs me-2"></i>Job Settings</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Priority</label>
          <div class="d-flex gap-2">
            <?php foreach (['normal'=>['secondary','Normal'],'urgent'=>['warning','Urgent'],'express'=>['danger','Express']] as $p => [$cls, $lbl]): ?>
            <div class="form-check flex-grow-1 text-center border rounded p-2" style="border-color:#334155!important">
              <input class="form-check-input" type="radio" name="priority" id="prio_<?= $p ?>" value="<?= $p ?>" <?= ($data['priority']??'normal')===$p ? 'checked':'' ?>>
              <label class="form-check-label w-100 small fw-semibold" for="prio_<?= $p ?>" style="cursor:pointer">
                <span class="badge bg-<?= $cls ?> d-block"><?= $lbl ?></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Assign Technician</label>
          <select name="assigned_to" class="form-select select2">
            <option value="">— Assign Later —</option>
            <?php foreach ($technicians as $t): ?>
            <option value="<?= $t['id'] ?>" <?= isset($data['assigned_to']) && $data['assigned_to']==$t['id'] ? 'selected':'' ?>><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Estimated Delivery Date</label>
          <input type="date" name="estimated_delivery" class="form-control" value="<?= e($data['estimated_delivery'] ?? date('Y-m-d', strtotime('+3 days'))) ?>">
        </div>
      </div>
    </div>

    <!-- Pricing -->
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-dollar-sign me-2 text-success"></i>Pricing & Payment</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Estimated Total Cost</label>
          <div class="input-group">
            <span class="input-group-text">Rs.</span>
            <input type="number" name="estimated_cost" id="estimatedCost" class="form-control" placeholder="0.00" step="0.01" min="0" value="<?= e($data['estimated_cost'] ?? '') ?>">
          </div>
          <small class="text-muted">Auto-calculated from services or enter manually</small>
        </div>
        <div class="mb-3">
          <label class="form-label">Advance Payment</label>
          <div class="input-group">
            <span class="input-group-text">Rs.</span>
            <input type="number" name="advance_payment" class="form-control" placeholder="0.00" step="0.01" min="0" value="<?= e($data['advance_payment'] ?? '0') ?>">
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label">Advance Payment Method</label>
          <select name="advance_payment_method" class="form-select">
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="transfer">Bank Transfer</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="card mb-4">
      <div class="card-body">
        <table class="totals-table">
          <tr><td class="text-muted">Services Total</td><td id="serviceTotal">Rs. 0.00</td></tr>
          <tr><td class="text-muted">Advance Paid</td><td id="advancePaid">Rs. 0.00</td></tr>
          <tr class="grand"><td>Balance Due</td><td id="balanceDue">Rs. 0.00</td></tr>
        </table>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold" style="font-size:15px">
      <i class="fas fa-plus-circle me-2"></i>Create Repair Job & Print Ticket
    </button>
  </div>

</div>
</form>

<?php
$extraScripts = '
<script>
// Show/hide new customer section
function toggleNewCustomer() {
  const val = $("#customerSelect").val();
  $("#newCustomerSection").toggle(!val);
}
$("#customerSelect").on("change", toggleNewCustomer);
toggleNewCustomer();

// Service row add/remove
$(document).on("change", "select[name=\'services[]\']", function() {
  const price = $(this).find(":selected").data("price") || 0;
  $(this).closest(".service-row").find(".service-price").val(price).trigger("change");
  calcTotals();
});
$(document).on("input change", ".service-price, input[name=\'advance_payment\']", calcTotals);

function calcTotals() {
  let total = 0;
  $(".service-price").each(function() { total += parseFloat($(this).val()) || 0; });
  const advance = parseFloat($("input[name=\'advance_payment\']").val()) || 0;
  $("#serviceTotal").text("Rs. " + total.toFixed(2));
  $("#advancePaid").text("Rs. " + advance.toFixed(2));
  $("#balanceDue").text("Rs. " + Math.max(0, total - advance).toFixed(2));
  if (!$("#estimatedCost").is(":focus") || !$("#estimatedCost").val()) {
    $("#estimatedCost").val(total > 0 ? total.toFixed(2) : "");
  }
}

$("#addServiceRow").on("click", function() {
  const row = $(".service-row:first").clone();
  row.find("select").val("");
  row.find("input").val("");
  $("#servicesContainer").append(row);
});
$(document).on("click", ".remove-service", function() {
  if ($(".service-row").length > 1) $(this).closest(".service-row").remove();
  calcTotals();
});

$(document).ready(function() {
  $("select.select2").select2({ theme: "bootstrap-5" });
});
</script>
<style>
.totals-table { width:100%; }
.totals-table td { padding: 3px 0; font-size: 13px; color: #64748b; }
.totals-table td:last-child { text-align: right; color: #e2e8f0; }
.totals-table .grand td { font-size: 16px; font-weight: 700; color: #f1f5f9; padding-top: 8px; border-top: 1px solid #334155; }
</style>
';
require_once __DIR__ . '/../includes/footer.php';
?>

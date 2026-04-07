<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin');

$pageTitle = 'Settings';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['company_name','company_address','company_phone','company_email',
               'currency','currency_symbol','tax_rate','receipt_footer',
               'invoice_prefix','repair_prefix'];
    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        $stmt = $db->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
        $stmt->execute([$key, $val, $val]);
    }
    setFlash('success', 'Settings saved.');
    header('Location: ' . BASE_URL . '/settings/index.php'); exit;
}

// Load all settings
$settings = [];
$rows = $db->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll();
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];

function s(string $key, string $default=''): string {
    global $settings;
    return htmlspecialchars($settings[$key] ?? $default, ENT_QUOTES);
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-cog me-2 text-primary"></i>Settings</h4>
    <p>Configure your shop information, currency, and receipt defaults.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/settings/users.php" class="btn btn-outline-secondary"><i class="fas fa-users me-2"></i>Users</a>
    <a href="<?= BASE_URL ?>/settings/services.php" class="btn btn-outline-secondary"><i class="fas fa-tools me-2"></i>Services</a>
  </div>
</div>

<form method="POST">
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card mb-4">
      <div class="card-header fw-semibold">Company / Shop Info</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Shop / Company Name</label>
          <input type="text" name="company_name" class="form-control" value="<?= s('company_name','Minro Mobile') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Address</label>
          <textarea name="company_address" class="form-control" rows="2"><?= s('company_address') ?></textarea>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" name="company_phone" class="form-control" value="<?= s('company_phone') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="company_email" class="form-control" value="<?= s('company_email') ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header fw-semibold">Currency & Tax</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Currency Code</label>
            <input type="text" name="currency" class="form-control" value="<?= s('currency','LKR') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Currency Symbol</label>
            <input type="text" name="currency_symbol" class="form-control" value="<?= s('currency_symbol','Rs.') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Tax Rate (%)</label>
            <input type="number" name="tax_rate" class="form-control" step="0.01" min="0" max="100" value="<?= s('tax_rate','0') ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header fw-semibold">Receipt & Invoice</div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Invoice Number Prefix</label>
            <input type="text" name="invoice_prefix" class="form-control" value="<?= s('invoice_prefix','INV-') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Repair Job Prefix</label>
            <input type="text" name="repair_prefix" class="form-control" value="<?= s('repair_prefix','REP-') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Receipt Footer Message</label>
          <textarea name="receipt_footer" class="form-control" rows="2" placeholder="Thank you for your business!"><?= s('receipt_footer','Thank you! Visit us again.') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-4">
      <div class="card-header fw-semibold">System Info</div>
      <div class="card-body">
        <?php
        $productCount = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $customerCount= $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
        $saleCount    = $db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
        $repairCount  = $db->query("SELECT COUNT(*) FROM repair_jobs")->fetchColumn();
        ?>
        <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Products</span><strong><?= number_format($productCount) ?></strong></div>
        <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Customers</span><strong><?= number_format($customerCount) ?></strong></div>
        <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Sales</span><strong><?= number_format($saleCount) ?></strong></div>
        <div class="d-flex justify-content-between py-2"><span class="text-muted">Repairs</span><strong><?= number_format($repairCount) ?></strong></div>
      </div>
    </div>
    <div class="card mb-4">
      <div class="card-header fw-semibold">Quick Links</div>
      <div class="card-body d-grid gap-2">
        <a href="<?= BASE_URL ?>/settings/users.php" class="btn btn-outline-secondary text-start">
          <i class="fas fa-users me-2 text-primary"></i>Manage Users & Roles
        </a>
        <a href="<?= BASE_URL ?>/settings/services.php" class="btn btn-outline-secondary text-start">
          <i class="fas fa-tools me-2 text-warning"></i>Manage Repair Services
        </a>
        <a href="<?= BASE_URL ?>/inventory/categories.php" class="btn btn-outline-secondary text-start">
          <i class="fas fa-tags me-2 text-info"></i>Product Categories
        </a>
        <a href="<?= BASE_URL ?>/setup/install.php" class="btn btn-outline-danger text-start" target="_blank">
          <i class="fas fa-database me-2"></i>Re-run Installer (danger)
        </a>
      </div>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
      <i class="fas fa-save me-2"></i>Save Settings
    </button>
  </div>
</div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Minro POS - Sidebar Navigation
 */
$currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
function isActive(string $path): string {
    global $currentPath;
    return (strpos($currentPath, $path) !== false) ? 'active' : '';
}
$user = currentUser();
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fas fa-mobile-alt"></i></div>
    <div class="brand-text">
      <div class="brand-name">Minro</div>
      <div class="brand-sub">POS System</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>

    <a href="<?= BASE_URL ?>/dashboard/index.php" class="nav-item <?= isActive('/dashboard') ?>">
      <i class="fas fa-tachometer-alt nav-icon"></i>
      <span>Dashboard</span>
    </a>

    <a href="<?= BASE_URL ?>/pos/index.php" class="nav-item <?= isActive('/pos/index') ?>">
      <i class="fas fa-cash-register nav-icon"></i>
      <span>Point of Sale</span>
      <span class="nav-badge">POS</span>
    </a>

    <a href="<?= BASE_URL ?>/pos/quotation.php" class="nav-item <?= isActive('/pos/quotation') ?>">
      <i class="fas fa-file-invoice nav-icon"></i>
      <span>Quotation</span>
    </a>

    <div class="nav-section-label">Repair</div>

    <a href="<?= BASE_URL ?>/repairs/index.php" class="nav-item <?= isActive('/repairs') ?>">
      <i class="fas fa-tools nav-icon"></i>
      <span>Repair Jobs</span>
      <?php
        try {
            $db = getDB();
            $pending = $db->query("SELECT COUNT(*) FROM repair_jobs WHERE status IN ('pending','in_progress','waiting_parts')")->fetchColumn();
            if ($pending > 0) echo "<span class=\"nav-badge bg-warning text-dark\">$pending</span>";
        } catch (Exception $e) {}
      ?>
    </a>

    <div class="nav-section-label">Inventory</div>

    <a href="<?= BASE_URL ?>/inventory/index.php" class="nav-item <?= isActive('/inventory/index') ?>">
      <i class="fas fa-boxes nav-icon"></i>
      <span>Products & Stock</span>
    </a>

    <a href="<?= BASE_URL ?>/inventory/categories.php" class="nav-item <?= isActive('/inventory/categories') ?>">
      <i class="fas fa-mobile-alt nav-icon"></i>
      <span>Brands &amp; Models</span>
    </a>

    <a href="<?= BASE_URL ?>/inventory/stock_in.php" class="nav-item <?= isActive('/inventory/stock_in') ?>">
      <i class="fas fa-truck-loading nav-icon"></i>
      <span>Stock Receiving</span>
    </a>

    <div class="nav-section-label">CRM</div>

    <a href="<?= BASE_URL ?>/customers/index.php" class="nav-item <?= isActive('/customers') ?>">
      <i class="fas fa-users nav-icon"></i>
      <span>Customers</span>
    </a>

    <div class="nav-section-label">Analytics</div>

    <a href="<?= BASE_URL ?>/reports/index.php" class="nav-item <?= isActive('/reports') ?>">
      <i class="fas fa-chart-bar nav-icon"></i>
      <span>Reports</span>
    </a>

    <?php if (isAdmin()): ?>
    <div class="nav-section-label">Administration</div>

    <a href="<?= BASE_URL ?>/settings/users.php" class="nav-item <?= isActive('/settings/users') ?>">
      <i class="fas fa-user-cog nav-icon"></i>
      <span>Users</span>
    </a>

    <a href="<?= BASE_URL ?>/settings/services.php" class="nav-item <?= isActive('/settings/services') ?>">
      <i class="fas fa-wrench nav-icon"></i>
      <span>Repair Services</span>
    </a>

    <a href="<?= BASE_URL ?>/settings/index.php" class="nav-item <?= isActive('/settings/index') ?>">
      <i class="fas fa-cog nav-icon"></i>
      <span>Settings</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2">
      <div class="user-avatar-sm"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <div>
        <div class="small fw-semibold text-light"><?= e($user['name']) ?></div>
        <div class="small text-muted"><?= ucfirst($user['role']) ?></div>
      </div>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="ms-auto text-muted" title="Logout">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </div>
</aside>

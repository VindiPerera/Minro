<?php
/**
 * Minro POS - HTML Header Include
 * Usage: require_once BASE_PATH . '/includes/header.php';
 * $pageTitle must be set before including
 */
$pageTitle = $pageTitle ?? 'Minro POS';
$settings  = getSettings();
$companyName = $settings['company_name'] ?? 'Minro Mobile Repair';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — <?= e($companyName) ?></title>

<!-- Bootstrap 5.3 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome 6 -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<!-- DataTables -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<!-- Flatpickr -->
<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
<!-- Custom CSS -->
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <!-- Top Navbar -->
  <nav class="topbar d-flex align-items-center justify-content-between px-4">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
      </button>
      <div class="topbar-title"><?= e($pageTitle) ?></div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <div class="topbar-date d-none d-md-block">
        <i class="fas fa-calendar-alt me-1 text-muted"></i>
        <span id="liveDateTime"></span>
      </div>
      <div class="dropdown">
        <button class="btn btn-sm dropdown-toggle user-btn" data-bs-toggle="dropdown">
          <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
          <div class="d-none d-md-block">
            <div class="user-name"><?= e($user['name']) ?></div>
            <div class="user-role"><?= ucfirst($user['role']) ?></div>
          </div>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= BASE_URL ?>/settings/index.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
  <!-- Page Content -->
  <div class="page-content p-4">

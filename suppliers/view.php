<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isAdmin()) { header('Location: ' . BASE_URL . '/dashboard/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT * FROM suppliers WHERE id=?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    setFlash('error', 'Supplier not found.');
    header('Location: ' . BASE_URL . '/suppliers/index.php');
    exit;
}

$pageTitle = 'View Supplier: ' . e($supplier['name']);
$contacts = json_decode($supplier['contacts'], true) ?: [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-truck me-2 text-primary"></i>Supplier Profile</h4>
    <p>View detailed information about this supplier.</p>
  </div>
  <div>
    <a href="<?= BASE_URL ?>/suppliers/manage.php?id=<?= $supplier['id'] ?>" class="btn btn-primary me-2">
      <i class="fas fa-edit me-2"></i>Edit Supplier
    </a>
    <a href="<?= BASE_URL ?>/suppliers/index.php" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-2"></i>Back
    </a>
  </div>
</div>

<div class="row">
  <div class="col-md-4">
    <div class="card bg-dark text-light border-0 shadow-sm text-center p-4">
        <div class="mb-3 text-muted" style="opacity:0.8;">
            <div class="bg-secondary text-white d-flex align-items-center justify-content-center mx-auto" style="width:150px; height:150px; border-radius:50%; font-size:3rem;">
              <i class="fas fa-building"></i>
            </div>
        </div>
        <h4 class="mb-1"><?= e($supplier['name']) ?></h4>
        <p class="text-muted mb-0">Added on <?= niceDate($supplier['created_at']) ?></p>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card bg-dark text-light border-0 shadow-sm">
      <div class="card-body">
        <h5 class="card-title fw-bold mb-4 border-bottom border-secondary pb-2">Business Details</h5>
        
        <ul class="list-group list-group-flush bg-transparent">
            <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center px-0">
                <span class="text-muted"><i class="fas fa-envelope me-2"></i>Email</span>
                <span class="fw-semibold text-end">
                  <?php if ($supplier['email']): ?>
                    <a href="mailto:<?= e($supplier['email']) ?>" class="text-primary text-decoration-none"><?= e($supplier['email']) ?></a>
                  <?php else: ?>
                    <span class="text-muted">Not provided</span>
                  <?php endif; ?>
                </span>
            </li>
            <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center px-0">
                <span class="text-muted"><i class="fas fa-globe me-2"></i>Website</span>
                <span class="fw-semibold text-end">
                  <?php if ($supplier['website']): ?>
                    <a href="<?= e($supplier['website']) ?>" target="_blank" class="text-primary text-decoration-none"><?= e($supplier['website']) ?></a>
                  <?php else: ?>
                    <span class="text-muted">Not provided</span>
                  <?php endif; ?>
                </span>
            </li>
            <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-start px-0">
                <span class="text-muted"><i class="fas fa-map-marker-alt me-2"></i>Address</span>
                <span class="fw-semibold text-end text-break" style="max-width: 60%;"><?= e($supplier['address']) ?: '<span class="text-muted">Not provided</span>' ?></span>
            </li>
        </ul>

        <h5 class="card-title fw-bold mt-5 mb-4 border-bottom border-secondary pb-2">Phone Contacts</h5>
        <?php if (empty($contacts)): ?>
            <p class="text-muted">No contacts listed for this supplier.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush bg-transparent">
                <?php foreach ($contacts as $c): ?>
                    <li class="list-group-item bg-transparent text-light border-secondary">
                        <i class="fas fa-user-circle me-2 text-muted"></i> <strong><?= e($c['name'] ?: 'Unnamed') ?></strong>
                        <span class="ms-3"><i class="fas fa-<?= isset($c['type']) && $c['type'] === 'foreign' ? 'globe' : 'phone-alt' ?> me-2 text-muted"></i> <?= e($c['phone'] ?: 'N/A') ?> <?= isset($c['type']) && $c['type'] === 'foreign' ? '<span class="badge bg-secondary ms-1">Foreign</span>' : '' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
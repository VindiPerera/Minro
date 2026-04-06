<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$errors = [];
$customer = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
    if (!$customer) { header('Location: ' . BASE_URL . '/customers/index.php'); exit; }
    $pageTitle = 'Edit Customer';
} else {
    $pageTitle = 'Add Customer';
}

$data = $customer ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'    => trim($_POST['name'] ?? ''),
        'phone'   => trim($_POST['phone'] ?? ''),
        'email'   => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'notes'   => trim($_POST['notes'] ?? ''),
    ];

    if (!$data['name']) $errors[] = 'Customer name is required.';
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    if (empty($errors)) {
        if ($id) {
            $db->prepare("UPDATE customers SET name=?,phone=?,email=?,address=?,notes=? WHERE id=?")->execute(array_merge(array_values($data), [$id]));
            setFlash('success', 'Customer updated.');
        } else {
            $db->prepare("INSERT INTO customers (name,phone,email,address,notes) VALUES (?,?,?,?,?)")->execute(array_values($data));
            setFlash('success', 'Customer added.');
        }
        header('Location: ' . BASE_URL . '/customers/index.php'); exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-user-<?= $id ? 'edit' : 'plus' ?> me-2 text-primary"></i><?= $pageTitle ?></h4>
    <p><?= $id ? 'Update customer details.' : 'Create a new customer record.' ?></p>
  </div>
  <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Back
  </a>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">Customer Details</div>
      <div class="card-body">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= e($data['name'] ?? '') ?>" required placeholder="Customer's full name">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control" value="<?= e($data['phone'] ?? '') ?>" placeholder="+94 77 000 0000">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($data['email'] ?? '') ?>" placeholder="email@example.com">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="2" placeholder="Street, city"><?= e($data['address'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Any special notes..."><?= e($data['notes'] ?? '') ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-5">
              <i class="fas fa-save me-2"></i>Save
            </button>
            <?php if ($id): ?>
            <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
              <i class="fas fa-eye me-2"></i>View Profile
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

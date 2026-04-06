<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin');

$pageTitle = 'User Management';
$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $uid   = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'cashier';
        $phone = trim($_POST['phone'] ?? '');
        $pass  = trim($_POST['password'] ?? '');
        $status= isset($_POST['status']) ? 1 : 0;

        if (!$name) $errors[] = 'Name is required.';
        if (!$email || !filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (!$uid && !$pass) $errors[] = 'Password is required for new users.';

        // Email uniqueness check
        $check = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $check->execute([$email, $uid]);
        if ($check->fetch()) $errors[] = 'Email already in use.';

        if (empty($errors)) {
            if ($uid) {
                if ($pass) {
                    $db->prepare("UPDATE users SET name=?,email=?,role=?,phone=?,status=?,password=? WHERE id=?")->execute([$name,$email,$role,$phone,$status,password_hash($pass,PASSWORD_DEFAULT),$uid]);
                } else {
                    $db->prepare("UPDATE users SET name=?,email=?,role=?,phone=?,status=? WHERE id=?")->execute([$name,$email,$role,$phone,$status,$uid]);
                }
                setFlash('success', 'User updated.');
            } else {
                $db->prepare("INSERT INTO users (name,email,role,phone,password,status) VALUES (?,?,?,?,?,?)")->execute([$name,$email,$role,$phone,password_hash($pass,PASSWORD_DEFAULT),$status]);
                setFlash('success', 'User created.');
            }
            header('Location: ' . BASE_URL . '/settings/users.php'); exit;
        }
    } elseif ($action === 'toggle') {
        $uid = (int)($_POST['id'] ?? 0);
        $current = $db->prepare("SELECT status FROM users WHERE id=?"); $current->execute([$uid]);
        $cur = $current->fetchColumn();
        $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$cur ? 0 : 1, $uid]);
        header('Location: ' . BASE_URL . '/settings/users.php'); exit;
    }
}

$users = $db->query("SELECT * FROM users ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-users me-2 text-primary"></i>Users</h4>
    <p>Manage staff accounts and access roles.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/settings/index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Settings</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" id="addUserBtn">
      <i class="fas fa-plus me-2"></i>Add User
    </button>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $i => $u): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= e($u['name']) ?></td>
          <td class="text-muted small"><?= e($u['email']) ?></td>
          <td class="text-muted small"><?= e($u['phone'] ?? '—') ?></td>
          <td>
            <span class="badge bg-<?= match($u['role']){'admin'=>'danger','cashier'=>'primary','technician'=>'warning',default=>'secondary'} ?>">
              <?= ucfirst($u['role']) ?>
            </span>
          </td>
          <td>
            <?php if ($u['status']): ?>
              <span class="badge bg-success-subtle text-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary me-1 edit-user-btn"
              data-id="<?= $u['id'] ?>"
              data-name="<?= e($u['name']) ?>"
              data-email="<?= e($u['email']) ?>"
              data-phone="<?= e($u['phone'] ?? '') ?>"
              data-role="<?= e($u['role']) ?>"
              data-status="<?= $u['status'] ?>">
              <i class="fas fa-edit"></i>
            </button>
            <?php if ($u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-<?= $u['status'] ? 'warning':'success' ?>" title="<?= $u['status'] ? 'Deactivate':'Activate' ?>">
                <i class="fas fa-<?= $u['status'] ? 'ban':'check' ?>"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="userId" value="0">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="userModalTitle">Add User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="userName" class="form-control" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-7">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="userEmail" class="form-control" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" id="userPhone" class="form-control">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" id="userRole" class="form-select">
              <option value="cashier">Cashier</option>
              <option value="technician">Technician</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Password <span id="passRequired" class="text-danger">*</span><small id="passHint" class="text-muted d-none"> (leave blank to keep unchanged)</small></label>
            <input type="password" name="password" id="userPass" class="form-control" autocomplete="new-password">
          </div>
          <div class="form-check form-switch">
            <input type="checkbox" name="status" id="userStatus" class="form-check-input" checked>
            <label class="form-check-label text-muted" for="userStatus">Active</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save User</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php $extraScripts = <<<JS
<script>
$('#addUserBtn').on('click', function() {
  $('#userModalTitle').text('Add User');
  $('#userId').val('0');
  $('#userName').val('');
  $('#userEmail').val('');
  $('#userPhone').val('');
  $('#userRole').val('cashier');
  $('#userPass').val('');
  $('#userStatus').prop('checked', true);
  $('#passRequired').show();
  $('#passHint').hide();
});

$('.edit-user-btn').on('click', function() {
  const d = $(this).data();
  $('#userModalTitle').text('Edit User');
  $('#userId').val(d.id);
  $('#userName').val(d.name);
  $('#userEmail').val(d.email);
  $('#userPhone').val(d.phone);
  $('#userRole').val(d.role);
  $('#userPass').val('');
  $('#userStatus').prop('checked', d.status == 1);
  $('#passRequired').hide();
  $('#passHint').show();
  new bootstrap.Modal(document.getElementById('userModal')).show();
});
</script>
JS;
require_once __DIR__ . '/../includes/footer.php'; ?>

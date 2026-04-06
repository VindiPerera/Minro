<?php
require_once __DIR__ . '/../config/database.php';
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if (empty($email) || empty($pass)) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($pass, $user['password'])) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_email'] = $user['email'];
                header('Location: ../dashboard/index.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please run the installer first.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Minro POS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
  :root { --primary: #2563eb; --secondary: #7c3aed; }
  * { box-sizing: border-box; }
  body { background: #0f172a; min-height: 100vh; display: flex; align-items: stretch; margin: 0; font-family: 'Segoe UI', sans-serif; }

  .login-left {
    flex: 1; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    position: relative; overflow: hidden;
  }
  .login-left::before {
    content: ''; position: absolute; width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(37,99,235,.15) 0%, transparent 70%);
    top: -200px; left: -200px; border-radius: 50%;
  }
  .login-left::after {
    content: ''; position: absolute; width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(124,58,237,.1) 0%, transparent 70%);
    bottom: -100px; right: -100px; border-radius: 50%;
  }
  .brand-section { text-align: center; z-index: 1; }
  .brand-logo {
    width: 90px; height: 90px; margin: 0 auto 24px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 24px; display: flex; align-items: center; justify-content: center;
    font-size: 40px; color: white; box-shadow: 0 20px 40px rgba(37,99,235,.3);
  }
  .brand-tagline { font-size: 40px; font-weight: 800; color: #f1f5f9; line-height: 1.1; }
  .brand-tagline span { background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
  .brand-sub { color: #64748b; font-size: 16px; margin-top: 12px; }

  .feature-list { margin-top: 48px; display: flex; flex-direction: column; gap: 16px; text-align: left; }
  .feature-item { display: flex; align-items: center; gap: 12px; color: #94a3b8; }
  .feature-icon { width: 36px; height: 36px; background: rgba(37,99,235,.2); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 14px; }

  .login-right {
    width: 440px; display: flex; align-items: center; justify-content: center;
    padding: 40px; background: #1e293b; border-left: 1px solid #334155;
  }
  .login-box { width: 100%; }
  .login-title { font-size: 26px; font-weight: 700; color: #f1f5f9; margin-bottom: 8px; }
  .login-subtitle { color: #64748b; font-size: 14px; margin-bottom: 32px; }

  .form-label { color: #94a3b8; font-size: 13px; font-weight: 500; }
  .form-control {
    background: #0f172a; border: 1px solid #334155; color: #e2e8f0;
    border-radius: 10px; padding: 12px 16px; font-size: 14px;
  }
  .form-control:focus { background: #0f172a; border-color: var(--primary); color: #e2e8f0; box-shadow: 0 0 0 3px rgba(37,99,235,.2); }
  .form-control::placeholder { color: #475569; }
  .input-group-text { background: #0f172a; border: 1px solid #334155; color: #64748b; border-radius: 0 10px 10px 0 !important; cursor: pointer; }
  .input-group .form-control { border-radius: 10px 0 0 10px !important; }

  .btn-login {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none; color: white; padding: 13px; font-weight: 600; font-size: 15px;
    border-radius: 10px; width: 100%; transition: all .2s;
  }
  .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(37,99,235,.4); }

  .demo-creds { background: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 14px; margin-top: 20px; }
  .demo-creds p { margin: 0; font-size: 12px; color: #64748b; }
  .demo-creds code { background: rgba(37,99,235,.2); color: #93c5fd; border-radius: 4px; padding: 2px 6px; }

  .alert-danger { background: rgba(220,38,38,.15); border: 1px solid rgba(220,38,38,.3); color: #fca5a5; border-radius: 10px; font-size: 13px; }

  @media (max-width: 768px) {
    .login-left { display: none; }
    .login-right { width: 100%; border-left: none; }
  }
</style>
</head>
<body>
<div class="login-left">
  <div class="brand-section">
    <div class="brand-logo"><i class="fas fa-mobile-alt"></i></div>
    <div class="brand-tagline">Minro <span>POS</span></div>
    <div class="brand-sub">Mobile Repair & Retail Management</div>

    <div class="feature-list">
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-cash-register"></i></div>
        <div><strong style="color:#e2e8f0">Point of Sale</strong><br><small>Fast retail billing with barcode scanning</small></div>
      </div>
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-tools"></i></div>
        <div><strong style="color:#e2e8f0">Repair Management</strong><br><small>Job tickets, parts tracking & technician assignment</small></div>
      </div>
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-boxes"></i></div>
        <div><strong style="color:#e2e8f0">Inventory Control</strong><br><small>Real-time stock with low-stock alerts</small></div>
      </div>
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
        <div><strong style="color:#e2e8f0">Analytics & Reports</strong><br><small>Sales, repairs, and inventory insights</small></div>
      </div>
    </div>
  </div>
</div>

<div class="login-right">
  <div class="login-box">
    <div class="login-title">Welcome back 👋</div>
    <div class="login-subtitle">Sign in to your Minro POS account</div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
      <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="admin@minro.lk"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="input-group">
          <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter password" required>
          <span class="input-group-text" onclick="togglePass()"><i class="fas fa-eye" id="eyeIcon"></i></span>
        </div>
      </div>
      <button type="submit" class="btn btn-login">
        <i class="fas fa-sign-in-alt me-2"></i> Sign In
      </button>
    </form>

    <div class="demo-creds mt-4">
      <p class="mb-2 fw-semibold" style="color:#64748b">Demo Credentials</p>
      <p class="mb-1">Admin: <code>admin@minro.lk</code> / <code>admin123</code></p>
      <p class="mb-1">Cashier: <code>cashier@minro.lk</code> / <code>cashier123</code></p>
      <p class="mb-0">Technician: <code>tech@minro.lk</code> / <code>tech123</code></p>
    </div>

    <p class="text-center mt-4" style="color:#475569;font-size:12px">
      Not installed? <a href="../setup/install.php" style="color:#60a5fa">Run Installer →</a>
    </p>
  </div>
</div>

<script>
function togglePass() {
  const inp = document.getElementById('passwordInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') { inp.type = 'text'; ico.classList.replace('fa-eye','fa-eye-slash'); }
  else { inp.type = 'password'; ico.classList.replace('fa-eye-slash','fa-eye'); }
}
</script>
</body>
</html>

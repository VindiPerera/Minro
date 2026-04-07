<?php
/**
 * Minro POS - Database Installation Script
 * Run this once to set up the database
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'minro_pos';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host   = $_POST['host'] ?? 'localhost';
    $user   = $_POST['db_user'] ?? 'root';
    $pass   = $_POST['db_pass'] ?? '';
    $dbname = $_POST['db_name'] ?? 'minro_pos';
    $admin_name  = $_POST['admin_name'] ?? 'Admin';
    $admin_email = $_POST['admin_email'] ?? 'admin@minro.lk';
    $admin_pass  = $_POST['admin_pass'] ?? 'admin123';

    try {
        // Create DB
        $conn = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->exec("USE `$dbname`");

        // -------------------------------------------------------
        // DROP & CREATE ALL TABLES
        // -------------------------------------------------------
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");

        $sql = "
        DROP TABLE IF EXISTS stock_movements;
        DROP TABLE IF EXISTS repair_invoices;
        DROP TABLE IF EXISTS repair_job_parts;
        DROP TABLE IF EXISTS repair_job_services;
        DROP TABLE IF EXISTS repair_jobs;
        DROP TABLE IF EXISTS sale_items;
        DROP TABLE IF EXISTS sales;
          DROP TABLE IF EXISTS supplier_return_items;
          DROP TABLE IF EXISTS supplier_returns;
          DROP TABLE IF EXISTS suppliers;
          DROP TABLE IF EXISTS stock_movements;
          DROP TABLE IF EXISTS products;
          DROP TABLE IF EXISTS phone_models;
          DROP TABLE IF EXISTS brands;
          DROP TABLE IF EXISTS repair_services;
          DROP TABLE IF EXISTS categories;
          DROP TABLE IF EXISTS customers;
          DROP TABLE IF EXISTS users;
          DROP TABLE IF EXISTS app_settings;
        ";
        foreach (explode(';', $sql) as $s) {
            if (trim($s)) $conn->exec(trim($s));
        }

        // USERS
        $conn->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','cashier','technician') DEFAULT 'cashier',
            phone VARCHAR(20),
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // CUSTOMERS
        $conn->exec("CREATE TABLE customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            email VARCHAR(100),
            address TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // BRANDS
        $conn->exec("CREATE TABLE brands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            status TINYINT DEFAULT 1
        ) ENGINE=InnoDB");

        // PHONE MODELS
        $conn->exec("CREATE TABLE phone_models (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            status TINYINT DEFAULT 1,
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // PRODUCTS
        $conn->exec("CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            barcode VARCHAR(100) UNIQUE DEFAULT NULL,
            name VARCHAR(200) NOT NULL,
            brand VARCHAR(100) DEFAULT NULL,
            model VARCHAR(150) DEFAULT NULL,
            quality VARCHAR(50) DEFAULT NULL,
            type ENUM('part','accessory') DEFAULT 'part',
            description TEXT,
            cost_price DECIMAL(12,2) DEFAULT 0.00,
            selling_price DECIMAL(12,2) DEFAULT 0.00,
            stock_quantity INT DEFAULT 0,
            low_stock_threshold INT DEFAULT 5,
            unit VARCHAR(20) DEFAULT 'pcs',
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // REPAIR SERVICES
        $conn->exec("CREATE TABLE repair_services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            description TEXT,
            base_price DECIMAL(12,2) DEFAULT 0.00,
            status TINYINT DEFAULT 1
        ) ENGINE=InnoDB");

        // SALES
        $conn->exec("CREATE TABLE sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(50) UNIQUE NOT NULL,
            customer_id INT,
            sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            subtotal DECIMAL(12,2) DEFAULT 0.00,
            discount DECIMAL(12,2) DEFAULT 0.00,
            tax DECIMAL(12,2) DEFAULT 0.00,
            total DECIMAL(12,2) DEFAULT 0.00,
            paid_amount DECIMAL(12,2) DEFAULT 0.00,
            change_amount DECIMAL(12,2) DEFAULT 0.00,
            payment_method ENUM('cash','card','transfer','mixed') DEFAULT 'cash',
            status ENUM('completed','refunded','pending') DEFAULT 'completed',
            cashier_id INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // SALE ITEMS
        $conn->exec("CREATE TABLE sale_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            product_id INT,
            product_name VARCHAR(200) NOT NULL,
            product_code VARCHAR(50),
            quantity INT NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL,
            discount DECIMAL(12,2) DEFAULT 0.00,
            total DECIMAL(12,2) NOT NULL,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // REPAIR JOBS
        $conn->exec("CREATE TABLE repair_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_number VARCHAR(50) UNIQUE NOT NULL,
            customer_id INT,
            device_brand VARCHAR(100),
            device_model VARCHAR(150),
            device_imei VARCHAR(50),
            device_color VARCHAR(50),
            device_condition TEXT,
            issue_description TEXT,
            customer_complaint TEXT,
            estimated_cost DECIMAL(12,2) DEFAULT 0.00,
            advance_payment DECIMAL(12,2) DEFAULT 0.00,
            advance_payment_method ENUM('cash','card','transfer') DEFAULT 'cash',
            status ENUM('pending','in_progress','waiting_parts','completed','delivered','cancelled') DEFAULT 'pending',
            priority ENUM('normal','urgent','express') DEFAULT 'normal',
            assigned_to INT,
            cashier_id INT,
            barcode VARCHAR(100),
            estimated_delivery DATE,
            actual_delivery DATETIME,
            internal_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // REPAIR JOB SERVICES
        $conn->exec("CREATE TABLE repair_job_services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            service_id INT,
            service_name VARCHAR(150) NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            FOREIGN KEY (job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES repair_services(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // REPAIR JOB PARTS
        $conn->exec("CREATE TABLE repair_job_parts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            product_id INT,
            product_name VARCHAR(200) NOT NULL,
            product_code VARCHAR(50),
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL,
            total DECIMAL(12,2) NOT NULL,
            added_by INT,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // REPAIR INVOICES
        $conn->exec("CREATE TABLE repair_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(50) UNIQUE NOT NULL,
            job_id INT NOT NULL,
            subtotal DECIMAL(12,2) DEFAULT 0.00,
            discount DECIMAL(12,2) DEFAULT 0.00,
            tax DECIMAL(12,2) DEFAULT 0.00,
            total DECIMAL(12,2) DEFAULT 0.00,
            advance_payment DECIMAL(12,2) DEFAULT 0.00,
            balance_due DECIMAL(12,2) DEFAULT 0.00,
            paid_amount DECIMAL(12,2) DEFAULT 0.00,
            payment_method ENUM('cash','card','transfer','mixed') DEFAULT 'cash',
            payment_status ENUM('pending','partial','paid') DEFAULT 'pending',
            cashier_id INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
            FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // STOCK MOVEMENTS
        $conn->exec("CREATE TABLE stock_movements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            movement_type ENUM('purchase','sale','repair_use','adjustment','return') NOT NULL,
            quantity INT NOT NULL,
            reference_id INT,
            reference_type VARCHAR(50),
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
          // SUPPLIERS
          $conn->exec("CREATE TABLE IF NOT EXISTS suppliers (
              id INT AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(150) NOT NULL,
              contact_person VARCHAR(100) DEFAULT NULL,
              phone VARCHAR(50) DEFAULT NULL,
              email VARCHAR(100) DEFAULT NULL,
              address TEXT DEFAULT NULL,
              status TINYINT DEFAULT 1,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
          ) ENGINE=InnoDB");

          // SUPPLIER RETURNS
          $conn->exec("CREATE TABLE IF NOT EXISTS supplier_returns (
              id INT AUTO_INCREMENT PRIMARY KEY,
              return_number VARCHAR(50) UNIQUE NOT NULL,
              supplier_id INT NOT NULL,
              return_date DATETIME DEFAULT CURRENT_TIMESTAMP,
              status ENUM('pending','completed','canceled') DEFAULT 'pending',
              notes TEXT,
              created_by INT,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
              FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
          ) ENGINE=InnoDB");

          // SUPPLIER RETURN ITEMS
          $conn->exec("CREATE TABLE IF NOT EXISTS supplier_return_items (
              id INT AUTO_INCREMENT PRIMARY KEY,
              return_id INT NOT NULL,
              product_id INT NOT NULL,
              quantity INT NOT NULL,
              unit_cost DECIMAL(12,2) DEFAULT 0.00,
              total_cost DECIMAL(12,2) DEFAULT 0.00,
              reason VARCHAR(255) DEFAULT NULL,
              FOREIGN KEY (return_id) REFERENCES supplier_returns(id) ON DELETE CASCADE,
              FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
          ) ENGINE=InnoDB");
        // APP SETTINGS
        $conn->exec("CREATE TABLE app_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");

        // -------------------------------------------------------
        // SEED DEFAULT DATA
        // -------------------------------------------------------

        // Admin user
        $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$admin_name, $admin_email, $hashed]);

        // Default cashier
        $conn->exec("INSERT INTO users (name, email, password, role) VALUES ('Cashier', 'cashier@minro.lk', '" . password_hash('cashier123', PASSWORD_DEFAULT) . "', 'cashier')");
        // Default technician
        $conn->exec("INSERT INTO users (name, email, password, role) VALUES ('Technician', 'tech@minro.lk', '" . password_hash('tech123', PASSWORD_DEFAULT) . "', 'technician')");

        // Default brands
        $conn->exec("INSERT INTO brands (name) VALUES
            ('Samsung'),('Apple'),('Huawei'),('Xiaomi'),('OnePlus'),
            ('Nokia'),('Motorola'),('Oppo'),('Vivo'),('Realme'),
            ('Sony'),('LG'),('Generic')");

        // Default phone models (brand_id matches insert order above)
        $conn->exec("INSERT INTO phone_models (brand_id, name) VALUES
            (1,'Galaxy S21'),(1,'Galaxy S22'),(1,'Galaxy S23'),(1,'Galaxy S24'),
            (1,'Galaxy A54'),(1,'Galaxy A34'),(1,'Galaxy A14'),(1,'Galaxy M34'),
            (1,'Galaxy Note 20'),(1,'Galaxy Note 10'),
            (2,'iPhone 11'),(2,'iPhone 12'),(2,'iPhone 13'),(2,'iPhone 14'),
            (2,'iPhone 15'),(2,'iPhone 16'),(2,'iPhone X'),(2,'iPhone XR'),
            (2,'iPhone XS'),(2,'iPhone SE (2022)'),
            (3,'P30 Pro'),(3,'P40 Pro'),(3,'P50 Pro'),(3,'Mate 20 Pro'),(3,'Mate 30 Pro'),(3,'Nova 9'),
            (4,'Redmi Note 11'),(4,'Redmi Note 12'),(4,'Redmi Note 13'),(4,'Mi 11'),(4,'POCO X5 Pro'),
            (5,'OnePlus 9'),(5,'OnePlus 10 Pro'),(5,'OnePlus Nord'),
            (6,'Nokia G21'),(6,'Nokia G42'),
            (7,'Moto G32'),(7,'Moto G52'),(7,'Moto G72'),
            (8,'Reno 8'),(8,'A78'),(8,'Find X5'),
            (9,'V25'),(9,'V29'),(9,'Y75'),
            (10,'Realme 11 Pro'),(10,'Realme C55'),
            (11,'Xperia 5 IV'),(11,'Xperia 1 IV'),
            (12,'LG V50'),(12,'LG G8')");

        // Default repair services
        $conn->exec("INSERT INTO repair_services (name, description, base_price) VALUES
            ('Screen Replacement', 'Replace broken or damaged screen', 2500.00),
            ('Battery Replacement', 'Replace old or faulty battery', 1500.00),
            ('Charging Port Repair', 'Fix or replace charging port', 1000.00),
            ('Back Cover Replacement', 'Replace cracked or broken back cover', 800.00),
            ('Water Damage Repair', 'Clean and repair water damaged phone', 3000.00),
            ('Software Flashing', 'Flash or update phone software/firmware', 1200.00),
            ('Motherboard Repair', 'Diagnose and repair motherboard issues', 5000.00),
            ('Camera Repair', 'Fix or replace camera module', 2000.00),
            ('Speaker/Mic Repair', 'Fix speaker or microphone issues', 1000.00),
            ('General Diagnosis', 'Diagnose phone issues', 500.00)");

        // Default products
        $conn->exec("INSERT INTO products (code, barcode, name, brand, model, quality, type, cost_price, selling_price, stock_quantity, low_stock_threshold) VALUES
            ('PRD-001', 'BC-PRD-001', 'Samsung S21 AMOLED Display', 'Samsung', 'Galaxy S21', 'Original', 'part', 8000.00, 12000.00, 5, 2),
            ('PRD-002', 'BC-PRD-002', 'iPhone 13 LCD Screen Assembly', 'Apple', 'iPhone 13', 'Original', 'part', 15000.00, 22000.00, 3, 1),
            ('PRD-003', 'BC-PRD-003', 'Samsung Generic Battery 4000mAh', 'Samsung', 'Generic', 'Compatible', 'part', 1200.00, 2000.00, 20, 5),
            ('PRD-004', 'BC-PRD-004', 'iPhone 12 Battery Original', 'Apple', 'iPhone 12', 'Original', 'part', 2500.00, 4000.00, 10, 3),
            ('PRD-005', 'BC-PRD-005', 'Universal USB-C Charging Port', 'Generic', NULL, 'Compatible', 'part', 500.00, 900.00, 15, 5),
            ('PRD-006', 'BC-PRD-006', 'iPhone Lightning Port', 'Apple', NULL, 'OEM', 'part', 800.00, 1400.00, 10, 3),
            ('PRD-007', 'BC-PRD-007', 'Silicone Phone Case - Universal', 'Generic', NULL, NULL, 'accessory', 150.00, 350.00, 50, 10),
            ('PRD-008', 'BC-PRD-008', 'Tempered Glass Screen Protector', 'Generic', NULL, NULL, 'accessory', 80.00, 200.00, 100, 20),
            ('PRD-009', 'BC-PRD-009', 'Type-C Fast Charger 20W', 'Generic', NULL, NULL, 'accessory', 600.00, 1200.00, 30, 8),
            ('PRD-010', 'BC-PRD-010', 'iPhone Lightning Cable 1m', 'Apple', NULL, NULL, 'accessory', 300.00, 700.00, 40, 10),
            ('PRD-011', 'BC-PRD-011', 'Wireless Earbuds TWS', 'Generic', NULL, NULL, 'accessory', 1500.00, 3000.00, 15, 5),
            ('PRD-012', 'BC-PRD-012', '10000mAh Power Bank', 'Generic', NULL, NULL, 'accessory', 1800.00, 3500.00, 12, 4)");

        // App settings
        $conn->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES
            ('company_name', 'Minro Mobile Repair'),
            ('company_address', 'No. 1, Main Street, Colombo'),
            ('company_phone', '+94 77 123 4567'),
            ('company_email', 'info@minro.lk'),
            ('currency', 'LKR'),
            ('currency_symbol', 'Rs.'),
            ('tax_rate', '0'),
            ('receipt_footer', 'Thank you for choosing Minro Mobile!'),
            ('repair_warranty_days', '30'),
            ('invoice_prefix_sale', 'INV'),
            ('invoice_prefix_repair', 'REP')");

        // Write config file
        $configContent = "<?php
define('DB_HOST', '$host');
define('DB_USER', '$user');
define('DB_PASS', '$pass');
define('DB_NAME', '$dbname');
define('INSTALLED', true);
";
        file_put_contents(__DIR__ . '/../config/db_config.php', $configContent);

        $success = true;

    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Minro POS - Installation</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
    body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .install-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 40px; max-width: 560px; width: 100%; }
    .logo { width: 64px; height: 64px; background: linear-gradient(135deg, #2563eb, #7c3aed); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    .form-control, .form-select { background: #0f172a; border-color: #334155; color: #e2e8f0; }
    .form-control:focus, .form-select:focus { background: #0f172a; border-color: #2563eb; color: #e2e8f0; box-shadow: 0 0 0 0.2rem rgba(37,99,235,.25); }
    label { color: #94a3b8; }
    h4, h5 { color: #e2e8f0; }
    p { color: #64748b; }
    .section-header { color: #60a5fa; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 12px; }
</style>
</head>
<body>
<div class="container">
  <div class="install-card mx-auto">
    <div class="logo"><i class="fas fa-mobile-alt text-white fs-4"></i></div>
    <h4 class="text-center mb-1">Minro POS Installation</h4>
    <p class="text-center mb-4 small">Set up your POS & Repair Management System</p>

    <?php if ($success): ?>
    <div class="alert alert-success text-center">
        <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
        <strong>Installation Successful!</strong><br>
        Your Minro POS system is ready.<br>
        <small class="text-muted">Admin: <?= htmlspecialchars($admin_email) ?> | Password: <?= htmlspecialchars($_POST['admin_pass']) ?></small>
    </div>
    <a href="../index.php" class="btn btn-primary w-100 mt-3"><i class="fas fa-rocket me-2"></i>Launch Minro POS</a>
    <?php else: ?>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= $e ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <div class="section-header"><i class="fas fa-database me-1"></i>Database Configuration</div>
        <div class="row g-3 mb-4">
            <div class="col-6">
                <label class="form-label small">DB Host</label>
                <input type="text" name="host" class="form-control" value="localhost" required>
            </div>
            <div class="col-6">
                <label class="form-label small">Database Name</label>
                <input type="text" name="db_name" class="form-control" value="minro_pos" required>
            </div>
            <div class="col-6">
                <label class="form-label small">DB Username</label>
                <input type="text" name="db_user" class="form-control" value="root" required>
            </div>
            <div class="col-6">
                <label class="form-label small">DB Password</label>
                <input type="password" name="db_pass" class="form-control" placeholder="(leave blank if none)">
            </div>
        </div>

        <div class="section-header"><i class="fas fa-user-shield me-1"></i>Admin Account</div>
        <div class="row g-3 mb-4">
            <div class="col-12">
                <label class="form-label small">Admin Name</label>
                <input type="text" name="admin_name" class="form-control" value="Admin" required>
            </div>
            <div class="col-12">
                <label class="form-label small">Admin Email</label>
                <input type="email" name="admin_email" class="form-control" value="admin@minro.lk" required>
            </div>
            <div class="col-12">
                <label class="form-label small">Admin Password</label>
                <input type="password" name="admin_pass" class="form-control" value="admin123" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="fas fa-cogs me-2"></i>Install Minro POS
        </button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
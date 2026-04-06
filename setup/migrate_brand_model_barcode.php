<?php
/**
 * Migration: Brands/Models tables, product type ENUM, remove categories
 * Run once on an existing minro_pos database.
 * After successful run you can delete this file.
 */
require_once __DIR__ . '/../config/db_config.php';

$errors  = [];
$success = [];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ── products: add barcode/brand/model/quality columns if missing ──────────
    $productCols = [
        'barcode VARCHAR(100) UNIQUE DEFAULT NULL AFTER code',
        'brand   VARCHAR(100) DEFAULT NULL AFTER name',
        'model   VARCHAR(150) DEFAULT NULL AFTER brand',
        'quality VARCHAR(50)  DEFAULT NULL AFTER model',
    ];
    foreach ($productCols as $col) {
        $colName = trim(explode(' ', $col)[0]);
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN $col");
            $success[] = "products.$colName added.";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                $success[] = "products.$colName already exists – skipped.";
            } else { $errors[] = "products.$colName: " . $e->getMessage(); }
        }
    }

    // ── products: add type ENUM ───────────────────────────────────────────────
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN type ENUM('part','accessory') DEFAULT 'part' AFTER quality");
        $success[] = "products.type added.";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $success[] = "products.type already exists – skipped.";
        } else { $errors[] = "products.type: " . $e->getMessage(); }
    }

    // ── back-fill barcodes ────────────────────────────────────────────────────
    $rows = $pdo->query("SELECT id, code FROM products WHERE barcode IS NULL OR barcode=''")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE products SET barcode=? WHERE id=?");
    foreach ($rows as $r) {
        $bc = 'BC-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $r['code']));
        $ck = $pdo->prepare("SELECT id FROM products WHERE barcode=? AND id!=?");
        $ck->execute([$bc, $r['id']]);
        if ($ck->fetch()) $bc = 'BC-' . $r['id'] . '-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $r['code']));
        $stmt->execute([$bc, $r['id']]);
    }
    if ($rows) $success[] = count($rows) . " product(s) back-filled with barcodes.";

    // ── create brands table ───────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS brands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        status TINYINT DEFAULT 1
    ) ENGINE=InnoDB");
    $success[] = "brands table ensured.";

    // ── create phone_models table ─────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS phone_models (
        id INT AUTO_INCREMENT PRIMARY KEY,
        brand_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        status TINYINT DEFAULT 1,
        FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $success[] = "phone_models table ensured.";

    // ── seed brands if empty ──────────────────────────────────────────────────
    $brandCount = $pdo->query("SELECT COUNT(*) FROM brands")->fetchColumn();
    if ($brandCount == 0) {
        $pdo->exec("INSERT INTO brands (name) VALUES
            ('Samsung'),('Apple'),('Huawei'),('Xiaomi'),('OnePlus'),
            ('Nokia'),('Motorola'),('Oppo'),('Vivo'),('Realme'),
            ('Sony'),('LG'),('Generic')");
        $pdo->exec("INSERT INTO phone_models (brand_id, name) VALUES
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
        $success[] = "13 brands and models seeded.";
    } else {
        $success[] = "brands already seeded – skipped.";
    }

    // ── drop category_id FK then column from products ─────────────────────────
    // Find and drop foreign key constraint if it exists
    $fkRows = $pdo->query("
        SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products'
        AND CONSTRAINT_TYPE='FOREIGN KEY'
    ")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($fkRows as $fk) {
        try {
            $pdo->exec("ALTER TABLE products DROP FOREIGN KEY `$fk`");
            $success[] = "Dropped FK: $fk";
        } catch (PDOException $e) { /* already gone */ }
    }
    try {
        $pdo->exec("ALTER TABLE products DROP COLUMN category_id");
        $success[] = "products.category_id removed.";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Can't DROP") !== false || strpos($e->getMessage(), "check that column") !== false) {
            $success[] = "products.category_id already removed – skipped.";
        } else { $errors[] = "products.category_id: " . $e->getMessage(); }
    }

    // ── drop categories table ─────────────────────────────────────────────────
    try {
        $pdo->exec("DROP TABLE IF EXISTS categories");
        $success[] = "categories table dropped.";
    } catch (PDOException $e) {
        $errors[] = "categories drop: " . $e->getMessage();
    }

} catch (PDOException $e) {
    $errors[] = "Connection error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Migration – Brands / Models / Categories Removal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#0f172a;color:#e2e8f0;padding:40px}.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;max-width:640px;margin:auto}</style>
</head>
<body>
<div class="card">
  <h4 class="mb-4"><i class="fas fa-database me-2"></i>Migration: Brands / Models / Remove Categories</h4>
  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <strong>Errors:</strong>
    <ul class="mb-0 mt-1">
      <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="alert alert-success">
    <strong>Done:</strong>
    <ul class="mb-0 mt-1">
      <?php foreach ($success as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
  <?php if (empty($errors)): ?>
  <div class="alert alert-info">Migration complete. You can now delete this file.</div>
  <?php endif; ?>
  <a href="../index.php" class="btn btn-primary mt-2">Go to POS</a>
</div>
</body>
</html>

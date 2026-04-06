<?php
/**
 * Minro POS - Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

// -------------------------------------------------------
// Authentication & Session
// -------------------------------------------------------
function requireAuth(string ...$roles): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['user_role'], $roles)) {
        http_response_code(403);
        die('<div style="text-align:center;padding:60px;font-family:sans-serif">
            <h2>Access Denied</h2><p>You do not have permission to view this page.</p>
            <a href="' . BASE_URL . '/dashboard/index.php">Back to Dashboard</a></div>');
    }
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? 0,
        'name' => $_SESSION['user_name'] ?? 'Guest',
        'role' => $_SESSION['user_role'] ?? '',
        'email'=> $_SESSION['user_email'] ?? '',
    ];
}

function isAdmin(): bool    { return ($_SESSION['user_role'] ?? '') === 'admin'; }
function isCashier(): bool  { return in_array($_SESSION['user_role'] ?? '', ['admin','cashier']); }
function isTech(): bool     { return ($_SESSION['user_role'] ?? '') === 'technician'; }

// -------------------------------------------------------
// Number & Currency Formatting
// -------------------------------------------------------
function money(float $amount): string {
    $symbol = setting('currency_symbol', 'Rs.');
    return $symbol . ' ' . number_format($amount, 2);
}

function moneyRaw(float $amount): string {
    return number_format($amount, 2, '.', '');
}

// -------------------------------------------------------
// Invoice / Job Number Generators
// -------------------------------------------------------
function generateInvoiceNumber(): string {
    $prefix = setting('invoice_prefix_sale', 'INV');
    $db = getDB();
    $count = (int)$db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
    return $prefix . '-' . date('Y') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
}

function generateRepairJobNumber(): string {
    $prefix = setting('invoice_prefix_repair', 'REP');
    $db = getDB();
    $count = (int)$db->query("SELECT COUNT(*) FROM repair_jobs")->fetchColumn();
    return $prefix . '-' . date('Y') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
}

function generateRepairInvoiceNumber(): string {
    $db = getDB();
    $count = (int)$db->query("SELECT COUNT(*) FROM repair_invoices")->fetchColumn();
    return 'RINV-' . date('Y') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
}

function generateProductCode(): string {
    $db = getDB();
    $count = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    return 'PRD-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

// -------------------------------------------------------
// Stock Management
// -------------------------------------------------------
function deductStock(int $productId, int $qty, string $type, int $refId, string $refType, string $notes = ''): bool {
    $db = getDB();
    $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?")->execute([$qty, $productId, $qty]);
    if ($db->rowCount() === 0) return false; // Not enough stock — though we allow it
    // Force deduct anyway (allow negative for repair parts)
    $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$qty, $productId]);
    logStockMovement($productId, $type, -$qty, $refId, $refType, $notes);
    return true;
}

function addStock(int $productId, int $qty, string $type, int $refId = 0, string $refType = '', string $notes = ''): void {
    $db = getDB();
    $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$qty, $productId]);
    logStockMovement($productId, $type, $qty, $refId, $refType, $notes);
}

function logStockMovement(int $productId, string $type, int $qty, int $refId, string $refType, string $notes): void {
    $db = getDB();
    $userId = $_SESSION['user_id'] ?? null;
    $db->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, reference_id, reference_type, notes, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?)")
       ->execute([$productId, $type, $qty, $refId, $refType, $notes, $userId]);
}

// -------------------------------------------------------
// Job Status Helpers
// -------------------------------------------------------
function jobStatusBadge(string $status): string {
    $map = [
        'pending'       => ['bg-secondary',  'Pending'],
        'in_progress'   => ['bg-primary',    'In Progress'],
        'waiting_parts' => ['bg-warning text-dark', 'Waiting Parts'],
        'completed'     => ['bg-success',    'Completed'],
        'delivered'     => ['bg-info',       'Delivered'],
        'cancelled'     => ['bg-danger',     'Cancelled'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-secondary', ucfirst($status)];
    return "<span class=\"badge $cls\">$label</span>";
}

function priorityBadge(string $priority): string {
    $map = [
        'normal'  => ['bg-secondary', 'Normal'],
        'urgent'  => ['bg-warning text-dark', 'Urgent'],
        'express' => ['bg-danger', 'Express'],
    ];
    [$cls, $label] = $map[$priority] ?? ['bg-secondary', ucfirst($priority)];
    return "<span class=\"badge $cls\">$label</span>";
}

function paymentStatusBadge(string $status): string {
    $map = [
        'pending' => ['bg-secondary', 'Pending'],
        'partial' => ['bg-warning text-dark', 'Partial'],
        'paid'    => ['bg-success', 'Paid'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-secondary', ucfirst($status)];
    return "<span class=\"badge $cls\">$label</span>";
}

// -------------------------------------------------------
// Date Helpers
// -------------------------------------------------------
function niceDate(string $date): string {
    if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') return '—';
    return date('M d, Y', strtotime($date));
}

function niceDateTime(string $date): string {
    if (!$date || $date === '0000-00-00 00:00:00') return '—';
    return date('M d, Y h:i A', strtotime($date));
}

// -------------------------------------------------------
// Flash Messages
// -------------------------------------------------------
function setFlash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function showFlash(): void {
    $f = getFlash();
    if ($f) {
        $cls = ['success'=>'alert-success','error'=>'alert-danger','warning'=>'alert-warning','info'=>'alert-info'][$f['type']] ?? 'alert-info';
        echo "<div class=\"alert {$cls} alert-dismissible fade show\" role=\"alert\">" . htmlspecialchars($f['msg']) . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// -------------------------------------------------------
// Sanitize / Escape
// -------------------------------------------------------
function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

function safePost(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

// -------------------------------------------------------
// Pagination
// -------------------------------------------------------
function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = (int)ceil($total / $perPage);
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => ($currentPage - 1) * $perPage,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}

<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isAdmin()) { header('Location: ' . BASE_URL . '/dashboard/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$db = getDB();

if ($id) {
    $db->prepare("DELETE FROM suppliers WHERE id=?")->execute([$id]);
    setFlash('success', 'Supplier deleted successfully.');
}

header('Location: ' . BASE_URL . '/suppliers/index.php');
exit;
<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isAdmin()) { header('Location: ' . BASE_URL . '/dashboard/index.php'); exit; }

$db = getDB();
$id = $_GET['id'] ?? 0;

if ($id) {
    $stmt = $db->prepare('SELECT status FROM supplier_returns WHERE id = ?');
    $stmt->execute([$id]);
    $return = $stmt->fetch();

    if ($return) {
        $db->beginTransaction();
        try {
            // Restore stock if it was actively returning (pending/completed)
            if (in_array($return['status'], ['pending', 'completed'])) {
                $stmtItems = $db->prepare('SELECT * FROM supplier_return_items WHERE return_id = ?');
                $stmtItems->execute([$id]);
                $items = $stmtItems->fetchAll();

                $restore = $db->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?');
                foreach ($items as $item) {
                     $restore->execute([$item['quantity'], $item['product_id']]);
                }
            }
            
            // Delete record
            $delete = $db->prepare('DELETE FROM supplier_returns WHERE id = ?');
            $delete->execute([$id]);

            $db->commit();
            setFlash('Supplier return deleted and stock restored.', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('Failed to delete return: ' . $e->getMessage(), 'danger');
        }
    } else {
        setFlash('Return not found.', 'danger');
    }
}

header('Location: ' . BASE_URL . '/returns/index.php');
exit;

<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isAdmin()) { header('Location: ' . BASE_URL . '/dashboard/index.php'); exit; }

$db = getDB();

$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: ' . BASE_URL . '/returns/index.php');
    exit;
}

$stmt = $db->prepare('SELECT sr.* FROM supplier_returns sr WHERE sr.id = ?');
$stmt->execute([$id]);
$return = $stmt->fetch();

if (!$return) {
    setFlash('Return not found.', 'danger');
    header('Location: ' . BASE_URL . '/returns/index.php');
    exit;
}

$stmtItems = $db->prepare('SELECT ri.*, p.name FROM supplier_return_items ri JOIN products p ON ri.product_id = p.id WHERE ri.return_id = ?');
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['pending', 'completed', 'canceled'])) {
        try {
            $db->beginTransaction();

            $update = $db->prepare('UPDATE supplier_returns SET status = ? WHERE id = ?');
            $update->execute([$newStatus, $id]);

            // If canceled, restore stock to products (if it wasn't already canceled)
            if ($newStatus === 'canceled' && $return['status'] !== 'canceled') {
                $restore = $db->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?');
                foreach ($items as $item) {
                     $restore->execute([$item['quantity'], $item['product_id']]);
                }
            }
            // If moved from canceled back to active (pending/completed)
            if ($return['status'] === 'canceled' && $newStatus !== 'canceled') {
                $deduct = $db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?');
                foreach ($items as $item) {
                     $deduct->execute([$item['quantity'], $item['product_id']]);
                }
            }

            $db->commit();
            setFlash('Return status updated successfully.', 'success');
            header('Location: ' . BASE_URL . '/returns/index.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('Failed to update status', 'danger');
        }
    }
}

$pageTitle = 'Update Return Status';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0 mt-5">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-sync-alt me-2 text-info"></i>Update Return Status (RET-<?= str_pad($return['id'], 5, '0', STR_PAD_LEFT) ?>)</h5>
            </div>
            <div class="card-body p-4">
                <form method="post" action="">
                    <h6 class="mb-3 fw-bold border-bottom pb-2">Items in this return order:</h6>
                    <ul class="list-group mb-4">
                        <?php foreach($items as $item): ?>
                         <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= e($item['name']) ?>
                            <span class="badge bg-danger rounded-pill">-<?= e($item['quantity']) ?> Qty</span>
                         </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="form-group mb-4 mt-3">
                        <label class="form-label fw-bold">Change Overall Status To:</label>
                        <select name="status" class="form-select">
                            <option value="pending" <?= $return['status'] == 'pending' ? 'selected' : '' ?>>Pending (Waiting on Supplier)</option>
                            <option value="completed" <?= $return['status'] == 'completed' ? 'selected' : '' ?>>Completed (Accepted)</option>
                            <option value="canceled" <?= $return['status'] == 'canceled' ? 'selected' : '' ?>>Canceled (Stock fully restored)</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between pt-3">
                        <a href="<?= BASE_URL ?>/returns/index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-info px-4">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
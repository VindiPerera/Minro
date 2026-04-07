<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isAdmin()) { header('Location: ' . BASE_URL . '/dashboard/index.php'); exit; }

$pageTitle = 'Return Order Management';
$db = getDB();

$id = $_GET['id'] ?? 0;
$returnOrder = null;
$returnItems = [];

// If editing
if ($id) {
    $stmt = $db->prepare('SELECT sr.*, s.name as supplier_name FROM supplier_returns sr JOIN suppliers s ON sr.supplier_id = s.id WHERE sr.id = ?');
    $stmt->execute([$id]);
    $returnOrder = $stmt->fetch();
    
    if (!$returnOrder) {
        setFlash('Return order not found.', 'danger');
        header('Location: ' . BASE_URL . '/returns/index.php');
        exit;
    }
    
    $stmtItems = $db->prepare('SELECT ri.*, p.name FROM supplier_return_items ri JOIN products p ON ri.product_id = p.id WHERE ri.return_id = ?');
    $stmtItems->execute([$id]);
    $returnItems = $stmtItems->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = $_POST['supplier_id'] ?? '';
    $product_ids = $_POST['product_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $note = $_POST['note'] ?? '';
    $status = $_POST['status'] ?? 'pending';

    $errors = [];
    if (!$id && empty($supplier_id)) $errors[] = 'Please select a supplier.';
    
    if (empty($product_ids) || empty($quantities)) {
        $errors[] = 'At least one product is required.';
    } else {
        foreach ($product_ids as $index => $pid) {
            $qty = $quantities[$index] ?? 0;
            if ($qty <= 0) $errors[] = "Quantity must be at least 1 for all items.";
        }
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            if ($id) {
                // Update existing
                $update = $db->prepare('UPDATE supplier_returns SET status = ?, note = ? WHERE id = ?');
                $update->execute([$status, $note, $id]);
                
                // Restore old stock before deleting old items (only if order was not canceled)
                if ($returnOrder['status'] !== 'canceled') {
                    foreach ($returnItems as $oldItem) {
                        $db->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?')
                           ->execute([$oldItem['quantity'], $oldItem['product_id']]);
                    }
                }
                
                // delete old items
                $db->prepare('DELETE FROM supplier_return_items WHERE return_id = ?')->execute([$id]);
                
                $return_id = $id;
            } else {
                // Create new
                $stmt = $db->prepare('INSERT INTO supplier_returns (supplier_id, status, note) VALUES (?, ?, ?)');
                $stmt->execute([$supplier_id, $status, $note]);
                $return_id = $db->lastInsertId();
            }

            // Insert new items and reduce stock (only if NOT canceled)
            $stmtItem = $db->prepare('INSERT INTO supplier_return_items (return_id, product_id, quantity) VALUES (?, ?, ?)');
            $stmtStock = $db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?');
            
            foreach ($product_ids as $index => $pid) {
                $qty = $quantities[$index];
                $stmtItem->execute([$return_id, $pid, $qty]);
                
                if ($status !== 'canceled') {
                    $stmtStock->execute([$qty, $pid]);
                }
            }

            $db->commit();
            setFlash($id ? 'Return order updated successfully.' : 'Return order created successfully.', 'success');
            header('Location: ' . BASE_URL . '/returns/index.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('Error saving return: ' . $e->getMessage(), 'danger');
        }
    } else {
        foreach ($errors as $e) {
            setFlash($e, 'danger');
        }
    }
}

$suppliers = $db->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll();
$products = $db->query('SELECT id, name, stock_quantity FROM products ORDER BY name ASC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-1"><i class="fas fa-undo-alt me-2 text-danger"></i><?= $id ? 'Edit Return Order' : 'Create Return Order' ?></h4>
    <p class="text-muted mb-0">Record multiple product returns for a supplier.</p>
  </div>
  <a href="<?= BASE_URL ?>/returns/index.php" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Back
  </a>
</div>

<div class="row">
  <div class="col-lg-10 mx-auto">
    <?php showFlash(); ?>
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <form method="post" action="">
          <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Supplier <span class="text-danger">*</span></label>
                <?php if ($id): ?>
                    <input type="text" class="form-control" value="<?= e($returnOrder['supplier_name']) ?>" disabled>
                    <input type="hidden" name="supplier_id" value="<?= $returnOrder['supplier_id'] ?>">
                <?php else: ?>
                    <select name="supplier_id" class="form-select select2" required>
                      <option value="">Select Supplier</option>
                      <?php foreach ($suppliers as $s): ?>
                      <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Initial Status</label>
                <select name="status" class="form-select">
                  <option value="pending" <?= ($returnOrder['status']??'') == 'pending' ? 'selected' : '' ?>>Pending (Waiting on Supplier)</option>
                  <option value="completed" <?= ($returnOrder['status']??'') == 'completed' ? 'selected' : '' ?>>Completed (Accepted)</option>
                  <option value="canceled" <?= ($returnOrder['status']??'') == 'canceled' ? 'selected' : '' ?>>Canceled (Stock Restores)</option>
                </select>
            </div>
          </div>
          
          <hr class="mb-4">
          <h5 class="fw-bold mb-3">Return Items</h5>
          
          <div id="itemsContainer">
            <?php 
            if (!empty($returnItems)) {
                foreach ($returnItems as $index => $item) {
            ?>
                <div class="row mb-3 return-item-row align-items-end">
                    <div class="col-md-8">
                      <label class="form-label fw-semibold">Product</label>
                      <select name="product_ids[]" class="form-select select2 product-select" required>
                        <option value="">Select Product...</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $item['product_id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> (Current Stock: <?= $p['stock_quantity'] ?>)
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold">Quantity</label>
                      <input type="number" name="quantities[]" class="form-control" min="1" value="<?= $item['quantity'] ?>" required>
                    </div>
                    <div class="col-md-1 text-end">
                      <?php if ($index > 0): ?>
                      <button type="button" class="btn btn-outline-danger remove-item"><i class="fas fa-times"></i></button>
                      <?php else: ?>
                      <button type="button" class="btn btn-outline-danger remove-item" style="visibility:hidden;"><i class="fas fa-times"></i></button>
                      <?php endif; ?>
                    </div>
                </div>
            <?php 
                }
            } else {
            ?>
                <div class="row mb-3 return-item-row align-items-end">
                    <div class="col-md-8">
                      <label class="form-label fw-semibold">Product</label>
                      <select name="product_ids[]" class="form-select select2 product-select" required>
                        <option value="">Select Product...</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= e($p['name']) ?> (Current Stock: <?= $p['stock_quantity'] ?>)
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold">Quantity</label>
                      <input type="number" name="quantities[]" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-md-1 text-end">
                      <button type="button" class="btn btn-outline-danger remove-item" style="visibility:hidden;"><i class="fas fa-times"></i></button>    
                    </div>
                </div>
            <?php } ?>
          </div>
          
          <button type="button" id="addItemBtn" class="btn btn-sm btn-outline-primary mb-4">
              <i class="fas fa-plus me-1"></i> Add Another Product
          </button>

          <div class="mb-4">
            <label class="form-label fw-bold">Return Note / Reason</label>
            <textarea name="note" class="form-control" rows="3" placeholder="e.g. Display not working, dead pixels..."><?= e($returnOrder['note'] ?? '') ?></textarea>
          </div>

          <hr class="my-4">
          <div class="text-end">
            <button type="submit" class="btn btn-danger px-4">
              <i class="fas fa-save me-2"></i><?= $id ? 'Update Return & Stock' : 'Create Return & Deduct Stock' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php 
$extraScripts = "
<script>
$(document).ready(function() {
    $('#addItemBtn').click(function() {
        var firstRow = $('.return-item-row:first');
        var newRow = firstRow.clone();
        
        // Reset values
        newRow.find('input[type=\"number\"]').val(1);
        
        // Fix Select2 (destroy and recreate)
        newRow.find('.select2-container').remove();
        newRow.find('select').removeClass('select2-hidden-accessible').removeAttr('data-select2-id').val('');
        
        // Show remove button
        newRow.find('.remove-item').css('visibility', 'visible');
        
        $('#itemsContainer').append(newRow);
        
        // Re-initialize Select2 on new row
        newRow.find('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
    });
    
    $(document).on('click', '.remove-item', function() {
        if ($('.return-item-row').length > 1) {
            $(this).closest('.return-item-row').remove();
        }
    });

    // Make sure initial select2 works fully within the dynamically loaded form if needed
    // The global app.js initializes .select2 but dynamically added ones need re-init
});
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>
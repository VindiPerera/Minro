<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

try {
    switch ($action) {

        // ─── Search products (for Select2 AJAX) ────────────────────────────
        case 'search':
            $q    = trim($_GET['q'] ?? '');
            $type = $_GET['type'] ?? ''; // 'accessory', 'part', 'both', or empty for all
            $sql  = "SELECT id, code, name, selling_price, stock_quantity, unit, type
                     FROM products WHERE status=1";
            $params = [];
            if ($q) {
                $sql .= " AND (name LIKE ? OR code LIKE ?)";
                $params[] = "%$q%";
                $params[] = "%$q%";
            }
            if ($type === 'sale') {
                $sql .= " AND type IN ('accessory','both')";
            } elseif ($type === 'part') {
                $sql .= " AND type IN ('part','both')";
            }
            $sql .= " ORDER BY name LIMIT 30";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            // Format for Select2
            $results = array_map(function($r) {
                return [
                    'id'    => $r['id'],
                    'text'  => $r['code'] . ' — ' . $r['name'] . ' (Stock: ' . $r['stock_quantity'] . ' ' . $r['unit'] . ')',
                    'name'  => $r['name'],
                    'code'  => $r['code'],
                    'price' => (float)$r['selling_price'],
                    'stock' => (int)$r['stock_quantity'],
                    'unit'  => $r['unit'],
                    'type'  => $r['type'],
                ];
            }, $rows);
            echo json_encode(['results' => $results]);
            break;

        // ─── Get single product details ────────────────────────────────────
        case 'get':
            $id   = (int)($_GET['id'] ?? 0);
            $barcode = trim($_GET['barcode'] ?? '');
            if ($id) {
                $stmt = $db->prepare("SELECT * FROM products WHERE id=? AND status=1");
                $stmt->execute([$id]);
            } elseif ($barcode) {
                $stmt = $db->prepare("SELECT * FROM products WHERE code=? AND status=1");
                $stmt->execute([$barcode]);
            } else {
                throw new Exception('No product identifier provided');
            }
            $product = $stmt->fetch();
            if (!$product) throw new Exception('Product not found');
            echo json_encode(['success'=>true,'product'=>$product]);
            break;

        // ─── Toggle product status ─────────────────────────────────────────
        case 'toggle':
            requireAuth('admin');
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("UPDATE products SET status = 1-status WHERE id=?")->execute([$id]);
            echo json_encode(['success'=>true]);
            break;

        // ─── Delete product ────────────────────────────────────────────────
        case 'delete':
            requireAuth('admin');
            $id = (int)($_POST['id'] ?? 0);
            // Check if product has been used in sales
            $used = $db->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id=?");
            $used->execute([$id]);
            if ($used->fetchColumn() > 0) throw new Exception('Cannot delete: product has been used in sales.');

            $usedR = $db->prepare("SELECT COUNT(*) FROM repair_job_parts WHERE product_id=?");
            $usedR->execute([$id]);
            if ($usedR->fetchColumn() > 0) throw new Exception('Cannot delete: product has been used in repairs.');

            $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            echo json_encode(['success'=>true,'message'=>'Product deleted.']);
            break;

        // ─── Stock adjustment ──────────────────────────────────────────────
        case 'adjust_stock':
            requireAuth('admin');
            $id   = (int)($_POST['id'] ?? 0);
            $qty  = (int)($_POST['qty'] ?? 0);
            $note = trim($_POST['note'] ?? 'Manual adjustment');
            if (!$id) throw new Exception('Invalid product');

            $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id=?");
            $stmt->execute([$id]);
            $cur = $stmt->fetchColumn();
            $newQty = max(0, $cur + $qty);

            $db->prepare("UPDATE products SET stock_quantity=? WHERE id=?")->execute([$newQty, $id]);
            logStockMovement($id, 'adjustment', abs($qty), $id, 'manual', $note);
            echo json_encode(['success'=>true,'new_qty'=>$newQty]);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>

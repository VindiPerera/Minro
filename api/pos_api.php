<?php
/**
 * Minro POS API - POS Endpoints
 */
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin', 'cashier');

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

try {
    switch ($action) {

        // ----------------------------------------------------------------
        case 'process_sale':
            $customerId    = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
            $cartJson      = $_POST['cart'] ?? '[]';
            $discount      = abs((float)($_POST['discount'] ?? 0));
            $paidAmount    = (float)($_POST['paid_amount'] ?? 0);
            $changeAmount  = (float)($_POST['change_amount'] ?? 0);
            $paymentMethod = $_POST['payment_method'] ?? 'cash';
            $notes         = trim($_POST['notes'] ?? '');
            $cart          = json_decode($cartJson, true);

            if (empty($cart)) throw new Exception('Cart is empty');

            // Calculate totals
            $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
            $total    = max(0, $subtotal - $discount);

            if ($paidAmount < $total) throw new Exception('Paid amount is insufficient');

            $db->beginTransaction();

            // Generate invoice number
            $invNum = generateInvoiceNumber();

            // Insert sale
            $stmt = $db->prepare("INSERT INTO sales (invoice_number, customer_id, sale_date, subtotal, discount, total, paid_amount, change_amount, payment_method, cashier_id, notes)
                                   VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$invNum, $customerId, $subtotal, $discount, $total, $paidAmount, $changeAmount, $paymentMethod, $_SESSION['user_id'], $notes]);
            $saleId = (int)$db->lastInsertId();

            // Insert items + deduct stock
            foreach ($cart as $item) {
                $itemTotal = $item['price'] * $item['qty'];
                $stmt2 = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, product_code, quantity, unit_price, total)
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt2->execute([$saleId, $item['id'], $item['name'], $item['code'], $item['qty'], $item['price'], $itemTotal]);

                // Deduct stock
                $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$item['qty'], $item['id']]);
                logStockMovement($item['id'], 'sale', -$item['qty'], $saleId, 'sale', "Sale: $invNum");
            }

            $db->commit();

            // Build receipt HTML for modal
            $settings    = getSettings();
            $companyName = $settings['company_name'] ?? 'Minro Mobile Repair';
            $companyAddr = $settings['company_address'] ?? '';
            $companyPhone= $settings['company_phone'] ?? '';
            $footerMsg   = $settings['receipt_footer'] ?? 'Thank you!';

            $customerName = 'Walk-in';
            if ($customerId) {
                $c = $db->prepare("SELECT name FROM customers WHERE id=?");
                $c->execute([$customerId]);
                $cn = $c->fetchColumn();
                if ($cn) $customerName = $cn;
            }

            $itemsHtml = '';
            foreach ($cart as $item) {
                $itemsHtml .= "<tr><td>{$item['name']}</td><td style='text-align:center'>{$item['qty']}</td><td style='text-align:right'>" . money($item['price']) . "</td><td style='text-align:right'>" . money($item['price'] * $item['qty']) . "</td></tr>";
            }

            $discHtml  = $discount > 0 ? "<tr><td style='color:#666'>Discount</td><td style='text-align:right;color:#dc2626'>− " . money($discount) . "</td></tr>" : '';
            $changeHtml = "<tr><td style='color:#666'>Paid (" . ucfirst($paymentMethod) . ")</td><td style='text-align:right'>" . money($paidAmount) . "</td></tr><tr><td style='color:#666'>Change</td><td style='text-align:right'>" . money($changeAmount) . "</td></tr>";

            $logoUrl = BASE_URL . '/assets/logo.png';
            $receiptHtml = "
            <div id='receiptPrint' style='font-family:Courier New,monospace;font-size:13px;max-width:360px;margin:0 auto;'>
              <div style='background:#1e293b;color:white;padding:16px;text-align:center;border-radius:8px 8px 0 0'>
                <div style='margin-bottom:6px'><img src='$logoUrl' alt='logo' style='max-height:44px;max-width:150px;object-fit:contain;filter:brightness(0) invert(1)'></div>
                <div style='font-size:20px;font-weight:800;letter-spacing:2px'>$companyName</div>
                " . ($companyAddr ? "<div style='font-size:11px;opacity:.8'>$companyAddr</div>" : '') . "
                " . ($companyPhone ? "<div style='font-size:11px;opacity:.8'>$companyPhone</div>" : '') . "
              </div>
              <div style='background:white;color:#111;padding:16px'>
                <div style='text-align:center;margin-bottom:10px'>
                  <svg data-barcode='$invNum'></svg>
                </div>
                <table style='width:100%'>
                  <tr><td style='color:#666'>Invoice:</td><td style='text-align:right;font-weight:700'>$invNum</td></tr>
                  <tr><td style='color:#666'>Date:</td><td style='text-align:right'>" . date('M d, Y h:i A') . "</td></tr>
                  <tr><td style='color:#666'>Customer:</td><td style='text-align:right'>$customerName</td></tr>
                </table>
                <hr style='border-top:1.5px dashed #ccc;margin:10px 0'>
                <table style='width:100%'>
                  <tr style='font-weight:700'><td>Item</td><td style='text-align:center'>Qty</td><td style='text-align:right'>Price</td><td style='text-align:right'>Total</td></tr>
                  $itemsHtml
                </table>
                <hr style='border-top:1.5px dashed #ccc;margin:10px 0'>
                <table style='width:100%'>
                  <tr><td style='color:#666'>Subtotal</td><td style='text-align:right'>" . money($subtotal) . "</td></tr>
                  $discHtml
                  <tr style='font-size:16px;font-weight:700'><td>TOTAL</td><td style='text-align:right'>" . money($total) . "</td></tr>
                  $changeHtml
                </table>
              </div>
              <div style='background:#f8f9fa;color:#555;padding:12px;text-align:center;font-size:11px;border-radius:0 0 8px 8px'>
                <div style='font-weight:700'>$footerMsg</div>
                <a href='" . BASE_URL . "/pos/receipt.php?id=$saleId' target='_blank' style='color:#2563eb;font-size:11px'>View Full Receipt →</a>
              </div>
            </div>";

            echo json_encode(['success' => true, 'sale_id' => $saleId, 'invoice_number' => $invNum, 'receipt_html' => $receiptHtml]);
            break;

        // ----------------------------------------------------------------
        case 'add_customer':
            $name  = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if (!$name || !$phone) throw new Exception('Name and phone are required');

            $stmt = $db->prepare("INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)");
            $stmt->execute([$name, $phone, $email]);
            $custId = (int)$db->lastInsertId();
            echo json_encode(['success' => true, 'customer' => ['id' => $custId, 'name' => $name, 'phone' => $phone]]);
            break;

        // ----------------------------------------------------------------
        case 'search_products':
            $q = trim($_GET['q'] ?? '');
            $stmt = $db->prepare("SELECT id, code, name, selling_price, stock_quantity FROM products WHERE status=1 AND type='accessory' AND (name LIKE ? OR code LIKE ?) LIMIT 10");
            $stmt->execute(["%$q%", "%$q%"]);
            echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
            break;

        // ----------------------------------------------------------------
        case 'void_sale':
            requireAuth('admin');
            $saleId = (int)($_POST['sale_id'] ?? 0);
            if (!$saleId) throw new Exception('Invalid sale ID');

            $items = $db->prepare("SELECT * FROM sale_items WHERE sale_id=?");
            $items->execute([$saleId]);
            $db->beginTransaction();
            foreach ($items->fetchAll() as $item) {
                $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
                logStockMovement($item['product_id'], 'return', $item['quantity'], $saleId, 'sale', 'Sale voided');
            }
            $db->prepare("UPDATE sales SET status='refunded' WHERE id=?")->execute([$saleId]);
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Sale voided successfully']);
            break;

        default:
            throw new Exception('Unknown action');
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

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

            $cashierName = 'Staff';
            $ca = $db->prepare("SELECT name FROM users WHERE id=?");
            $ca->execute([$_SESSION['user_id']]);
            $can = $ca->fetchColumn();
            if ($can) $cashierName = $can;

            $itemsHtml = '';
            foreach ($cart as $item) {
                $itemsHtml .= "<tr>";
                $itemsHtml .= "<td style='font-weight:700;width:35%;padding:4px 0;word-break:break-word;'>{$item['name']}</td>";
                $itemsHtml .= "<td style='text-align:center;width:15%;padding:4px 0;'>{$item['qty']}</td>";
                $itemsHtml .= "<td style='text-align:right;width:25%;padding:4px 0;white-space:nowrap;'>" . money($item['price']) . "</td>";
                $itemsHtml .= "<td style='text-align:right;width:25%;padding:4px 0;white-space:nowrap;'>" . money($item['price'] * $item['qty']) . "</td>";
                $itemsHtml .= "</tr>";
            }

            $discHtml  = $discount > 0 ? "<tr><td style='color:#555;padding:4px 0;font-size:15px;'>Discount</td><td style='text-align:right;color:#dc2626;font-weight:700;padding:4px 0;font-size:15px;white-space:nowrap;'>− " . money($discount) . "</td></tr>" : '';
            $changeHtml = "<tr><td style='color:#555;padding:4px 0;font-weight:normal;'>Paid (" . ucfirst($paymentMethod) . ")</td><td style='text-align:right;font-weight:normal;padding:4px 0;'><span style='margin-right:2px;'></span>" . money($paidAmount) . "</td></tr><tr><td style='color:#555;padding:4px 0;font-weight:normal;'>Change</td><td style='text-align:right;font-weight:normal;padding:4px 0;'><span style='margin-right:2px;'></span>" . money($changeAmount) . "</td></tr>";

            // Embed logo as base64 so it works in print popup (about:blank)
            $logoPath = BASE_PATH . '/assets/logo.png';
            $logoDataUri = '';
            if (file_exists($logoPath)) {
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoDataUri = 'data:image/png;base64,' . $logoData;
            }

            $logoHtml = $logoDataUri
                ? "<img src='$logoDataUri' alt='logo' style='max-height:80px;margin-bottom:5px;'>"
                : '';

            $receiptHtml = "
            <div id='receiptPrint' style='font-family:Arial,Helvetica,sans-serif;font-size:14px;max-width:380px;margin:0 auto;background:white;color:#000;padding:20px;box-sizing:border-box;'>
              <div style='text-align:center;margin-bottom:10px;'>
                $logoHtml
                <div style='font-size:26px;font-weight:900;letter-spacing:1px;margin-bottom:2px;'>$companyName</div>
                <div style='font-size:14px;line-height:1.4;margin-bottom:8px;'>
                    " . ($companyAddr ? "$companyAddr<br>" : '') . "
                    " . ($companyPhone ? "$companyPhone<br>" : '') . "
                </div>
              </div>
              <div style='border-top:1px dashed #000;margin:12px 0;'></div>
              <div style='text-align:center;font-size:20px;font-weight:700;letter-spacing:2px;margin:10px 0;margin-bottom:5px;'>SALES RECEIPT</div>
              <div style='text-align:center;font-size:22px;font-weight:900;margin-bottom:5px;letter-spacing:1px;'>$invNum</div>
              <div style='border-top:1px dashed #000;margin:12px 0;'></div>
              
              <div style='text-align:center;font-size:14px;margin-bottom:10px;'>Cashier: <strong>$cashierName</strong></div>
              
              <div style='font-size:16px;font-weight:700;letter-spacing:1px;margin-bottom:8px;'>CUSTOMER</div>
              <table style='width:100%;border-collapse:collapse;'>
                <tr><td style='color:#555;width:40%;padding:4px 0;'>Name</td><td style='text-align:right;font-weight:700;padding:4px 0;'>$customerName</td></tr>
                <tr><td style='color:#555;width:40%;padding:4px 0;'>Date</td><td style='text-align:right;font-weight:700;padding:4px 0;'>" . date('M d, Y h:i A') . "</td></tr>
              </table>
              
              <div style='border-top:1px dashed #000;margin:12px 0;'></div>
              <div style='font-size:16px;font-weight:700;letter-spacing:1px;margin-bottom:5px;'>PRODUCTS</div>
              <div style='border-top:2px solid #000;margin-bottom:10px;margin-top:0;'></div>
              
              <table style='width:100%;border-collapse:collapse;margin-bottom:15px;'>
                <thead>
                  <tr style='font-weight:700;'>
                    <td style='width:35%;padding-bottom:5px;border-bottom:1px solid #ccc;'>Item</td>
                    <td style='width:15%;text-align:center;padding-bottom:5px;border-bottom:1px solid #ccc;'>Qty</td>
                    <td style='width:25%;text-align:right;padding-bottom:5px;border-bottom:1px solid #ccc;'>Price</td>
                    <td style='width:25%;text-align:right;padding-bottom:5px;border-bottom:1px solid #ccc;'>Total</td>
                  </tr>
                </thead>
                <tbody>
                  $itemsHtml
                </tbody>
              </table>
              <div style='border-top:1px dashed #000;margin:12px 0;'></div>
              <table style='width:100%;border-collapse:collapse;'>
                <tr><td style='color:#555;padding:4px 0;font-size:15px;'>Subtotal</td><td style='text-align:right;font-weight:700;padding:4px 0;font-size:15px;white-space:nowrap;'>" . money($subtotal) . "</td></tr>
                $discHtml
              </table>
              <div style='border-top:1px dashed #000;margin:12px 0;'></div>
              <table style='width:100%;border-collapse:collapse;margin:8px 0;'>
                <tr style='font-weight:900;font-size:18px;'><td style='color:#000;padding:4px 0;'>TOTAL</td><td style='text-align:right;padding:4px 0;white-space:nowrap;'>" . money($total) . "</td></tr>
              </table>
              <div style='border-top:1px dashed #000;margin:12px 0;'></div>
              <table style='width:100%;border-collapse:collapse;'>
                $changeHtml
              </table>
              <div style='border-top:1px dashed #000;margin-top:20px;margin-bottom:12px;'></div>
              " . (!empty($notes) ? "<div style='font-size:14px;text-align:center;margin-bottom:20px;'><strong>Warranty Period:</strong> $notes</div>" : '') . "
              <div style='text-align:center;font-size:12px;margin-top:15px;line-height:1.5;'>
                $companyName | $companyPhone<br>
                $footerMsg<br>
                Items are non-refundable after 7 days.<br><br>
                <strong>Powered by JAAN Network</strong><br><br>
                <a href='" . BASE_URL . "/pos/receipt.php?id=$saleId' target='_blank' style='color:#2563eb;text-decoration:none;'>View Full Receipt →</a>
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

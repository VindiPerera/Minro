<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin', 'cashier');

$pageTitle = 'Point of Sale';
$db = getDB();

// Load brands for filter pills
$brands = $db->query("SELECT DISTINCT p.brand FROM products p WHERE p.status=1 AND p.brand IS NOT NULL ORDER BY p.brand")->fetchAll(PDO::FETCH_COLUMN);

// Load all products
$products = $db->query("SELECT p.* FROM products p WHERE p.status=1 ORDER BY p.name")->fetchAll();

// Load customers for dropdown
$customers = $db->query("SELECT id, name, phone FROM customers ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.pos-wrap { display: grid; grid-template-columns: 1fr 400px; height: calc(100vh - 64px); overflow: hidden; margin: -24px; }
.pos-left { overflow: hidden; display: flex; flex-direction: column; background: #0f172a; }
.pos-right { background: #1e293b; border-left: 1px solid #334155; display: flex; flex-direction: column; overflow: hidden; }
.pos-top-bar { padding: 14px 16px; border-bottom: 1px solid #334155; background: #1e293b; }
.products-area { flex: 1; overflow-y: auto; padding: 14px; }
.cart-header { padding: 14px 16px; border-bottom: 1px solid #334155; }
.cart-body { flex: 1; overflow-y: auto; padding: 8px 12px; }
.cart-footer { padding: 14px 16px; border-top: 1px solid #334155; }

.cat-pills { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
.cat-pills::-webkit-scrollbar { display: none; }
.cat-pill { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; background: #1e293b; border: 1px solid #334155; color: #94a3b8; cursor: pointer; white-space: nowrap; transition: all .15s; }
.cat-pill:hover, .cat-pill.active { background: rgba(37,99,235,.2); border-color: #2563eb; color: #60a5fa; }

.prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
.prod-card { background: #1e293b; border: 1.5px solid #334155; border-radius: 10px; padding: 12px 10px; cursor: pointer; transition: all .15s; text-align: center; }
.prod-card:hover:not(.out) { border-color: #2563eb; background: rgba(37,99,235,.1); transform: translateY(-1px); }
.prod-card.out { opacity: .5; cursor: not-allowed; }
.prod-icon { width: 42px; height: 42px; border-radius: 9px; background: rgba(37,99,235,.2); display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; color: #60a5fa; font-size: 16px; }
.prod-name  { font-size: 11.5px; font-weight: 600; color: #e2e8f0; margin-bottom: 3px; line-height: 1.3; height: 30px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.prod-price { font-size: 13px; font-weight: 700; color: #60a5fa; }
.prod-stock { font-size: 10.5px; margin-top: 2px; }

.ci { display: flex; align-items: center; gap: 8px; padding: 8px 6px; border-bottom: 1px solid #1e3a5f20; }
.ci:last-child { border-bottom: none; }
.ci-info { flex: 1; min-width: 0; }
.ci-name  { font-size: 12.5px; font-weight: 600; color: #e2e8f0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ci-price { font-size: 11px; color: #64748b; }
.ci-total  { font-size: 13px; font-weight: 700; color: #e2e8f0; white-space: nowrap; }
.ci-qty   { display: flex; align-items: center; gap: 4px; }
.ci-qty button { width: 22px; height: 22px; border-radius: 5px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center; }
.ci-qty button:hover { background: #2563eb; border-color: #2563eb; }
.ci-qty input { width: 36px; text-align: center; background: #0f172a; border: 1px solid #334155; color: #e2e8f0; border-radius: 5px; padding: 2px; font-size: 12px; }

.totals-table { width: 100%; font-size: 13px; }
.totals-table td { padding: 3px 0; }
.totals-table td:last-child { text-align: right; color: #e2e8f0; }
.totals-table .grand td { font-size: 17px; font-weight: 700; color: #f1f5f9; padding-top: 8px; border-top: 1px solid #334155; }
.totals-table .balance td { font-size: 14px; font-weight: 600; }

.pmt-btn { flex: 1; padding: 9px; border: 2px solid #334155; background: #0f172a; color: #94a3b8; border-radius: 8px; cursor: pointer; font-size: 11.5px; font-weight: 600; transition: all .15s; text-align: center; }
.pmt-btn:hover, .pmt-btn.active { border-color: #2563eb; color: #60a5fa; background: rgba(37,99,235,.15); }
</style>

<div class="pos-wrap">
  <!-- LEFT: Products -->
  <div class="pos-left">
    <div class="pos-top-bar">
      <div class="d-flex gap-2 mb-3">
        <div class="flex-grow-1 position-relative">
          <input type="text" id="productSearch" class="form-control" placeholder="🔍  Search products or scan barcode..." autofocus>
        </div>
        <button class="btn btn-outline-secondary" id="btnBarcodeMode" title="Barcode Mode">
          <i class="fas fa-barcode"></i>
        </button>
      </div>
      <div class="cat-pills">
        <span class="cat-pill active" data-brand="all">All</span>
        <span class="cat-pill" data-brand="__parts">Parts</span>
        <span class="cat-pill" data-brand="__accessories">Accessories</span>
        <?php foreach ($brands as $brand): ?>
        <span class="cat-pill" data-brand="<?= e($brand) ?>"><?= e($brand) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="products-area">
      <div class="prod-grid" id="productGrid">
        <?php foreach ($products as $p):
            $stockClass = $p['stock_quantity'] <= 0 ? 'out' : ($p['stock_quantity'] <= $p['low_stock_threshold'] ? 'low' : '');
            $icon = in_array(strtolower($p['name']), ['battery','charger']) ? 'fa-battery-full' : 'fa-mobile-alt';
            if (stripos($p['name'],'case')!==false) $icon='fa-shield-alt';
            elseif (stripos($p['name'],'screen')!==false||stripos($p['name'],'glass')!==false||stripos($p['name'],'display')!==false) $icon='fa-tv';
            elseif (stripos($p['name'],'cable')!==false||stripos($p['name'],'charger')!==false) $icon='fa-plug';
            elseif (stripos($p['name'],'earphone')!==false||stripos($p['name'],'headset')!==false||stripos($p['name'],'earbuds')!==false) $icon='fa-headphones';
            elseif (stripos($p['name'],'power bank')!==false) $icon='fa-battery-three-quarters';
        ?>
        <div class="prod-card <?= $stockClass ?>"
             data-id="<?= $p['id'] ?>"
             data-name="<?= e($p['name']) ?>"
             data-code="<?= e($p['code']) ?>"
             data-price="<?= $p['selling_price'] ?>"
             data-stock="<?= $p['stock_quantity'] ?>"
             data-brand="<?= e($p['brand'] ?? '') ?>"
             data-type="<?= e($p['type']) ?>">
          <div class="prod-icon"><i class="fas <?= $icon ?>"></i></div>
          <div class="prod-name"><?= e($p['name']) ?></div>
          <div class="prod-price"><?= money((float)$p['selling_price']) ?></div>
          <div class="prod-stock <?= $stockClass ?>">
            <?php if ($p['stock_quantity'] <= 0): ?>
              <i class="fas fa-times-circle"></i> Out of stock
            <?php elseif ($p['stock_quantity'] <= $p['low_stock_threshold']): ?>
              <i class="fas fa-exclamation-circle"></i> Low: <?= $p['stock_quantity'] ?> left
            <?php else: ?>
              <i class="fas fa-check-circle" style="color:#16a34a"></i> <?= $p['stock_quantity'] ?> in stock
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- RIGHT: Cart -->
  <div class="pos-right">
    <div class="cart-header">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="fw-bold" style="color:#f1f5f9"><i class="fas fa-shopping-cart me-2 text-primary"></i>Cart</div>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" id="btnHold" title="Hold Cart"><i class="fas fa-pause"></i></button>
          <button class="btn btn-sm btn-outline-danger" id="btnClearCart" title="Clear Cart"><i class="fas fa-trash"></i></button>
        </div>
      </div>
      <!-- Customer Select -->
      <select id="customerSelect" class="form-select form-select-sm select2" style="font-size:12px">
        <option value="">Walk-in Customer</option>
        <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> — <?= e($c['phone']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Cart Items -->
    <div class="cart-body" id="cartBody">
      <div id="emptyCart" class="text-center py-5">
        <i class="fas fa-shopping-cart fa-3x mb-3" style="color:#334155"></i>
        <p class="text-muted small">Cart is empty.<br>Click a product to add it.</p>
      </div>
      <div id="cartItems"></div>
    </div>

    <!-- Cart Footer: Totals + Payment -->
    <div class="cart-footer">
      <!-- Discount -->
      <div class="d-flex gap-2 mb-3 align-items-center">
        <label class="text-muted small mb-0" style="white-space:nowrap">Discount</label>
        <div class="input-group input-group-sm">
          <input type="number" id="discountValue" class="form-control" value="0" min="0" step="0.01">
          <select id="discountType" class="form-select" style="max-width:60px">
            <option value="flat">Rs</option>
            <option value="pct">%</option>
          </select>
        </div>
        <label class="text-muted small mb-0" style="white-space:nowrap">Note</label>
        <input type="text" id="saleNote" class="form-control form-control-sm" placeholder="Optional note">
      </div>

      <!-- Totals -->
      <table class="totals-table mb-3">
        <tr><td class="text-muted">Subtotal</td><td id="tSubtotal">Rs. 0.00</td></tr>
        <tr><td class="text-muted">Discount</td><td id="tDiscount" style="color:#fbbf24">— Rs. 0.00</td></tr>
        <tr class="grand"><td>TOTAL</td><td id="tTotal">Rs. 0.00</td></tr>
        <tr class="balance" style="color:#86efac"><td>Change</td><td id="tChange">Rs. 0.00</td></tr>
      </table>

      <!-- Payment Method -->
      <div class="d-flex gap-2 mb-3">
        <div class="pmt-btn active" data-method="cash"><i class="fas fa-money-bill-wave d-block fs-5 mb-1"></i>Cash</div>
        <div class="pmt-btn" data-method="card"><i class="fas fa-credit-card d-block fs-5 mb-1"></i>Card</div>
        <div class="pmt-btn" data-method="transfer"><i class="fas fa-university d-block fs-5 mb-1"></i>Transfer</div>
      </div>

      <!-- Paid Amount -->
      <div class="mb-3">
        <div class="input-group">
          <span class="input-group-text" style="font-weight:700">Rs.</span>
          <input type="number" id="paidAmount" class="form-control" placeholder="Amount received" step="0.01" min="0">
          <button class="btn btn-outline-secondary btn-sm" id="btnExact">Exact</button>
        </div>
      </div>

      <!-- Process Sale Button -->
      <button class="btn btn-primary w-100 py-2 fw-bold" id="btnProcessSale" style="font-size:15px" disabled>
        <i class="fas fa-check-circle me-2"></i>Process Sale
      </button>
    </div>
  </div>
</div>

<!-- Quick Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Quick Add Customer</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Name *</label>
          <input type="text" id="qcName" class="form-control form-control-sm" placeholder="Customer name">
        </div>
        <div class="mb-2">
          <label class="form-label">Phone *</label>
          <input type="text" id="qcPhone" class="form-control form-control-sm" placeholder="Phone number">
        </div>
        <div class="mb-0">
          <label class="form-label">Email</label>
          <input type="email" id="qcEmail" class="form-control form-control-sm" placeholder="Email (optional)">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-sm btn-primary" id="btnSaveCustomer">Save & Select</button>
      </div>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="fas fa-receipt me-2"></i>Sale Complete</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="receiptContent"></div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-outline-primary" onclick="printSection('receiptPrint')"><i class="fas fa-print me-2"></i>Print Receipt</button>
        <button class="btn btn-primary" id="btnNewSale"><i class="fas fa-plus me-2"></i>New Sale</button>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = '
<script>
// ================================================================
// MINRO POS - Cart Logic
// ================================================================
const CURRENCY = "Rs.";
let cart = [];
let paymentMethod = "cash";
let allProducts = ' . json_encode(array_values($products)) . ';

function fmt(n) {
  return CURRENCY + " " + parseFloat(n||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// ----------------------------------------------------------------
// Product Search & Filter
// ----------------------------------------------------------------
function filterProducts() {
  const search = $("#productSearch").val().toLowerCase();
  const brand  = $(".cat-pill.active").data("brand");
  $(".prod-card").each(function() {
    const name   = $(this).data("name").toLowerCase();
    const code   = $(this).data("code").toLowerCase();
    const pBrand = $(this).data("brand");
    const pType  = $(this).data("type");
    const match = (name.includes(search) || code.includes(search));
    let brandOk = true;
    if (brand && brand !== "all") {
      if (brand === "__parts")       brandOk = pType === "part";
      else if (brand === "__accessories") brandOk = pType === "accessory";
      else                           brandOk = pBrand === brand;
    }
    $(this).toggle(match && brandOk);
  });
}

$("#productSearch").on("input", filterProducts);
$(document).on("click", ".cat-pill", function() {
  $(".cat-pill").removeClass("active");
  $(this).addClass("active");
  filterProducts();
});

// Barcode scan (Enter key in search)
let barcodeMode = false;
$("#btnBarcodeMode").on("click", function() {
  barcodeMode = !barcodeMode;
  $(this).toggleClass("btn-outline-secondary btn-primary");
  $("#productSearch").attr("placeholder", barcodeMode ? "⚡ Scan barcode..." : "🔍  Search products or scan barcode...").focus();
});
$("#productSearch").on("keydown", function(e) {
  if (e.key === "Enter") {
    const q = $(this).val().trim();
    if (q) {
      const prod = allProducts.find(p => p.code.toLowerCase() === q.toLowerCase() || p.name.toLowerCase() === q.toLowerCase());
      if (prod) { addToCart(prod); $(this).val("").trigger("input"); }
      else { toast("Product not found: " + q, "error"); }
    }
  }
});

// ----------------------------------------------------------------
// Cart Functions
// ----------------------------------------------------------------
function addToCart(prod) {
  if (prod.stock_quantity <= 0) { toast("Product out of stock!", "warning"); return; }
  const idx = cart.findIndex(i => i.id == prod.id);
  if (idx >= 0) {
    if (cart[idx].qty >= prod.stock_quantity) { toast("Not enough stock!", "warning"); return; }
    cart[idx].qty++;
  } else {
    cart.push({ id: prod.id, name: prod.name, code: prod.code, price: parseFloat(prod.selling_price), qty: 1, maxStock: parseInt(prod.stock_quantity) });
  }
  renderCart();
}

function removeFromCart(id) {
  cart = cart.filter(i => i.id != id);
  renderCart();
}

function updateQty(id, qty) {
  qty = parseInt(qty);
  const item = cart.find(i => i.id == id);
  if (!item) return;
  if (qty <= 0) { removeFromCart(id); return; }
  if (qty > item.maxStock) { toast("Max stock: " + item.maxStock, "warning"); qty = item.maxStock; }
  item.qty = qty;
  renderCart();
}

function getSubtotal() { return cart.reduce((s, i) => s + i.price * i.qty, 0); }

function getDiscount() {
  const val  = parseFloat($("#discountValue").val()) || 0;
  const type = $("#discountType").val();
  const sub  = getSubtotal();
  return type === "pct" ? (sub * val / 100) : Math.min(val, sub);
}

function getTotal() { return Math.max(0, getSubtotal() - getDiscount()); }

function renderCart() {
  const $items = $("#cartItems");
  const $empty = $("#emptyCart");
  $items.empty();
  if (cart.length === 0) { $empty.show(); $items.hide(); } else { $empty.hide(); $items.show(); }
  cart.forEach(item => {
    const total = item.price * item.qty;
    $items.append(`
      <div class="ci" data-id="${item.id}">
        <div class="ci-info">
          <div class="ci-name">${item.name}</div>
          <div class="ci-price">${fmt(item.price)} each</div>
        </div>
        <div class="ci-qty">
          <button onclick="updateQty(${item.id}, ${item.qty-1})">−</button>
          <input type="number" value="${item.qty}" min="1" max="${item.maxStock}" onchange="updateQty(${item.id}, this.value)" />
          <button onclick="updateQty(${item.id}, ${item.qty+1})">+</button>
        </div>
        <div class="ci-total">${fmt(total)}</div>
        <button class="btn btn-sm" style="background:none;border:none;color:#ef4444;padding:2px" onclick="removeFromCart(${item.id})">
          <i class="fas fa-times"></i>
        </button>
      </div>
    `);
  });
  updateTotals();
}

function updateTotals() {
  const sub  = getSubtotal();
  const disc = getDiscount();
  const total = getTotal();
  const paid  = parseFloat($("#paidAmount").val()) || 0;
  const change = Math.max(0, paid - total);
  $("#tSubtotal").text(fmt(sub));
  $("#tDiscount").text("− " + fmt(disc));
  $("#tTotal").text(fmt(total));
  $("#tChange").text(fmt(change));
  $("#btnProcessSale").prop("disabled", cart.length === 0 || paid < total);
}

$(document).on("change input", "#discountValue, #discountType, #paidAmount", updateTotals);
$(document).on("click", ".prod-card:not(.out)", function() {
  const prod = allProducts.find(p => p.id == $(this).data("id"));
  if (prod) addToCart(prod);
});

// Payment method tabs
$(document).on("click", ".pmt-btn", function() {
  $(".pmt-btn").removeClass("active");
  $(this).addClass("active");
  paymentMethod = $(this).data("method");
});

// Exact amount button
$("#btnExact").on("click", function() {
  $("#paidAmount").val(getTotal().toFixed(2));
  updateTotals();
});

// Clear cart
$("#btnClearCart").on("click", function() {
  if (cart.length === 0) return;
  Swal.fire({ title:"Clear Cart?", text:"Remove all items from the cart?", icon:"warning", showCancelButton:true, confirmButtonColor:"#dc2626", cancelButtonColor:"#334155", background:"#1e293b", color:"#e2e8f0" })
  .then(r => { if (r.isConfirmed) { cart = []; renderCart(); } });
});

// ----------------------------------------------------------------
// Process Sale
// ----------------------------------------------------------------
$("#btnProcessSale").on("click", function() {
  if (cart.length === 0) return;
  const total = getTotal();
  const paid  = parseFloat($("#paidAmount").val()) || 0;
  if (paid < total) { toast("Paid amount is less than total!", "error"); return; }

  const data = {
    action: "process_sale",
    customer_id: $("#customerSelect").val() || "",
    cart: JSON.stringify(cart),
    discount: getDiscount().toFixed(2),
    discount_type: $("#discountType").val(),
    discount_value: $("#discountValue").val() || "0",
    total: total.toFixed(2),
    paid_amount: paid.toFixed(2),
    change_amount: (paid - total).toFixed(2),
    payment_method: paymentMethod,
    notes: $("#saleNote").val()
  };

  $("#btnProcessSale").prop("disabled", true).html(\'<i class="fas fa-spinner fa-spin me-2"></i>Processing...\');

  $.post("' . BASE_URL . '/api/pos_api.php", data, function(res) {
    if (res.success) {
      // Show receipt
      $("#receiptContent").html(res.receipt_html);
      // Render barcodes in modal
      if (typeof JsBarcode !== "undefined") {
        $("[data-barcode]").each(function() {
          try { JsBarcode(this, $(this).data("barcode"), { format:"CODE128", width:1.5, height:40, displayValue:true, fontSize:10, margin:3, lineColor:"#000", background:"#fff" }); } catch(e) {}
        });
      }
      const receiptModal = new bootstrap.Modal(document.getElementById("receiptModal"));
      receiptModal.show();
      // Reset cart
      cart = [];
      $("#discountValue").val("0");
      $("#paidAmount").val("");
      $("#customerSelect").val("").trigger("change");
      renderCart();
      // Reload page only after modal is closed (to update stock)
      document.getElementById("receiptModal").addEventListener("hidden.bs.modal", function() {
        location.reload();
      }, { once: true });
    } else {
      toast(res.message || "Sale failed!", "error");
    }
  }, "json").always(function() {
    $("#btnProcessSale").prop("disabled", false).html(\'<i class="fas fa-check-circle me-2"></i>Process Sale\');
  });
});

// New sale after receipt
$("#btnNewSale").on("click", function() {
  bootstrap.Modal.getInstance(document.getElementById("receiptModal")).hide();
});

// Quick customer
$("#btnSaveCustomer").on("click", function() {
  const name  = $("#qcName").val().trim();
  const phone = $("#qcPhone").val().trim();
  if (!name || !phone) { toast("Name and phone required", "error"); return; }
  $.post("' . BASE_URL . '/api/pos_api.php", { action:"add_customer", name, phone, email: $("#qcEmail").val() }, function(res) {
    if (res.success) {
      const opt = new Option(res.customer.name + " — " + res.customer.phone, res.customer.id, true, true);
      $("#customerSelect").append(opt).trigger("change");
      bootstrap.Modal.getInstance(document.getElementById("addCustomerModal")).hide();
      toast("Customer added!", "success");
    } else { toast(res.message, "error"); }
  }, "json");
});

// Init
$(document).ready(function() {
  $("#customerSelect").select2({ theme:"bootstrap-5", placeholder:"Walk-in Customer", allowClear:true });
  renderCart();
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>

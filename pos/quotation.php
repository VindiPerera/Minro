<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth('admin', 'cashier');

$db = getDB();
$products = $db->query("SELECT id, name, selling_price, code FROM products WHERE status=1 ORDER BY name")->fetchAll();

$settings  = getSettings();
$company   = $settings['company_name']    ?? 'Minro Mobile Store & Repair Center';
$addr      = $settings['company_address'] ?? 'No. 1, Main Street, Colombo';
$phone     = $settings['company_phone']   ?? '+94 77 123 4567';
$email     = $settings['company_email']   ?? 'info@minro.lk';
$currency  = $settings['currency_symbol'] ?? 'Rs.';

$qid = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6); 
$qID = "#" . $qid;
$today = date('Y-m-d');

$pageTitle = 'Quotation - ' . $qID;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
  .content-wrap { padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; max-width: 1400px; margin: 0 auto; height: auto;}
  
  /* Left Panel */
  .form-card { background: #f8f9fa; border: 3px solid #0f172a; border-radius: 12px; padding: 35px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
  .form-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
  .form-header h2 { font-weight: 800; margin: 0; font-size: 32px; color: #0f172a;}
  .form-header a { color: #2563eb; text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: 6px; font-size: 15px;}

  .form-control { border-radius: 8px; border: 1px solid #94a3b8; padding: 12px 15px; font-size: 15px; margin-bottom: 20px;}
  .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.2); }
  .form-label { font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #334155; }
  
  .btn-add { background: #86efac; color: #166534; font-weight: 700; padding: 12px 20px; border-radius: 8px; border: 2px solid #4ade80; transition: all 0.2s; width: 200px;}
  .btn-add:hover { background: #4ade80; }

  /* Right Panel */
  .preview-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 50px; border: 1px solid #e2e8f0; position: relative; color: #1e293b; min-height: 800px; display: flex; flex-direction: column;}
  
  .print-header { text-align: center; margin-bottom: 25px; }
  .print-header img { max-height: 80px; margin-bottom: 12px; }
  .print-header h3 { font-weight: 800; font-size: 26px; margin: 0 0 8px 0; color: #0f172a; }
  .print-header p { font-size: 14px; color: #475569; margin: 0; line-height: 1.5; }
  
  .print-meta-container { margin-bottom: 30px; display: flex; flex-direction: column; align-items: flex-start; }
  .print-meta { font-size: 14px; display: grid; grid-template-columns: 100px auto; gap: 12px 15px; text-align: left; width: 320px; }
  .print-meta strong { color: #475569; font-weight: 700; }
  .print-meta span { color: #1e293b; font-weight: 600; }
  
  .print-divider { border-bottom: 2px solid #e2e8f0; margin: 25px 0; display: none; }

  .print-customer { margin-bottom: 30px; font-size: 14px; display: none;}
  .print-customer h4 { font-size: 14px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 10px; }
  .print-customer div { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #2563eb; }

  .preview-table { width: 100%; margin-bottom: 30px; font-size: 14px; border-collapse: collapse; }
  .preview-table th { background: #0f172a; padding: 12px; font-weight: 700; color: #ffffff; border: 1px solid #0f172a; text-align: left; }
  .preview-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;}
  .preview-table tbody tr:nth-child(even) { background: #f8f9fa; }
  
  .print-summary-container { display: block; margin-bottom: 40px; }

  .print-totals { width: 100%; background: #f8f9fa; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; }
  .summary-row { display: flex; justify-content: space-between; margin-bottom: 12px; color: #64748b; font-size: 14px; }
  .summary-row span:last-child { color: #0f172a; font-weight: 600;}
  .summary-row.grand { font-weight: 700; font-size: 18px; border-top: 2px solid #cbd5e1; padding-top: 15px; margin-bottom: 0; color: #2563eb;}
  .summary-row.grand span:last-child { font-weight: 800; }
  
  .signature-box { display: flex; justify-content: space-between; margin-top: auto; padding-top: 60px; font-size: 14px; color: #0f172a; font-weight: 600; }
  .sig-line { width: 200px; text-align: center; border-top: 1px solid #94a3b8; padding-top: 10px; }
  
  .preview-action { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px dashed #cbd5e1; margin-top: 20px; }
  .btn-download { background: #2563eb; color: #fff; font-weight: 700; padding: 10px 24px; border-radius: 6px; border: none; transition: .2s; cursor: pointer; }
  .btn-download:hover { background: #1d4ed8; color: #fff;}
</style>

<div class="content-wrap">
  <!-- Left Side: Form -->
  <div>
    <div class="form-card">
      <div class="form-header">
        <h2>Quotation</h2>
        <a href="index.php">Open POS <i class="fas fa-cash-register"></i></a>
      </div>
      
      <form id="quoteForm">
        <div>
          <label class="form-label">Add products:</label>
          <input type="text" list="productList" id="qDesc" class="form-control" placeholder="Search product or enter custom name" required autocomplete="off">
          <datalist id="productList">
            <?php foreach($products as $p): ?>
            <option data-price="<?= $p['selling_price'] ?>" value="<?= e($p['name']) ?>"><?= e($p['code']) ?> - <?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div>
          <label class="form-label">Price:</label>
          <input type="number" id="qPrice" class="form-control" step="0.01" min="0" required>
        </div>
        <div>
          <label class="form-label">Discount:</label>
          <input type="number" id="qDiscount" class="form-control" step="0.01" min="0">
        </div>
        <div class="mb-4">
          <label class="form-label">Valid Date:</label>
          <div class="position-relative">
            <input type="text" id="qDate" class="form-control" placeholder="mm/dd/yyyy" required>
            <i class="far fa-calendar-alt position-absolute" style="right: 15px; top: 15px; color: #64748b; pointer-events: none;"></i>
          </div>
        </div>
        <div>
          <button type="submit" class="btn btn-add">Add Quotation</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Right Side: Preview -->
  <div>
    <div class="preview-card" id="printArea">
      <!-- Modern Header -->
      <div class="print-header">
        <img src="<?= BASE_URL ?>/assets/logo.png" alt="Company Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/150x50?text=LOGO';">
        <h3><?= e($company) ?></h3>
      </div>

      <div class="print-meta-container">
        <div class="print-meta">
          <strong>Quote No:</strong>
          <span><?= e($qid) ?></span>
          <strong>Date:</strong>
          <span><?= e($today) ?></span>
          <strong>Valid Until:</strong>
          <span id="lblValidDate"></span>
        </div>
      </div>

      <table class="preview-table" id="quoteTable">
        <thead>
          <tr>
            <th width="45%">Description</th>
            <th width="15%" class="text-center">Quantity</th>
            <th width="20%" class="text-end">Unit Price</th>
            <th width="20%" class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          <!-- Items will be injected here via JS -->
        </tbody>
      </table>

      <div class="print-summary-container">
        <div class="print-totals">
          <div class="summary-row">
            <span>Sub Total:</span>
            <span><?= e($currency) ?> <span id="lblProductTotal">0.00</span></span>
          </div>
          <div class="summary-row grand">
            <span>Grand Total:</span>
            <span><?= e($currency) ?> <span id="lblGrandTotal">0.00</span></span>
          </div>
        </div>
      </div>

      <div class="signature-box print-only" style="display:none;">
        <div class="sig-line">Prepared By</div>
        <div class="sig-line">Customer Acceptance</div>
      </div>
      
      <div class="print-footer" style="margin-top: auto; padding-top: 30px; text-align: center; border-top: 2px solid #e2e8f0; font-size: 13px; color: #64748b;">
        <p style="margin: 0;">
          <i class="fas fa-map-marker-alt"></i> <?= e($addr) ?> &nbsp; | &nbsp; 
          <i class="fas fa-phone-alt"></i> <?= e($phone) ?> &nbsp; | &nbsp; 
          <i class="fas fa-envelope"></i> <?= e($email) ?>
        </p>
      </div>

    </div>

    <!-- Action Bar Below Document -->
    <div class="preview-action no-print text-end">
      <span class="text-muted"><i class="fas fa-info-circle"></i> Ready to download?</span>
      <button class="btn btn-download" type="button" onclick="generatePDF()"><i class="fas fa-file-pdf"></i> Download PDF</button>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<!-- html2pdf for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
  let quoteItems = [];
  
  // Auto-fill price if an existing product is selected from the datalist
  document.getElementById('qDesc').addEventListener('input', function(e) {
    let val = this.value;
    let list = document.getElementById('productList');
    for (let i = 0; i < list.options.length; i++) {
        if (list.options[i].value === val) {
            let price = list.options[i].getAttribute('data-price');
            if(price) {
                document.getElementById('qPrice').value = parseFloat(price).toFixed(2);
            }
            break;
        }
    }
  });

  flatpickr("#qDate", {
    dateFormat: "Y-m-d",
    onChange: function(selectedDates, dateStr, instance) {
      document.getElementById('lblValidDate').textContent = dateStr;
    }
  });

  document.getElementById('quoteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    let desc = document.getElementById('qDesc').value;
    let price = parseFloat(document.getElementById('qPrice').value) || 0;
    let disc = parseFloat(document.getElementById('qDiscount').value) || 0;
    
    let subtotal = price - disc;
    if(subtotal < 0) subtotal = 0;

    quoteItems.push({
      desc: desc,
      qty: 1, 
      price: price,
      discount: disc,
      subtotal: subtotal
    });
    
    // Clear item inputs
    document.getElementById('qDesc').value = '';
    document.getElementById('qPrice').value = '';
    document.getElementById('qDiscount').value = '';
    document.getElementById('qDesc').focus();
    
    renderItems();
  });

  function renderItems() {
    let tbody = document.querySelector('#quoteTable tbody');
    tbody.innerHTML = '';
    
    let productTotal = 0;
    let grandTotal = 0;
    
    quoteItems.forEach((item) => {
      productTotal += item.price * item.qty;
      grandTotal += item.subtotal * item.qty;
      
      let tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${item.desc}</td>
        <td class="text-center">${item.qty}</td>
        <td class="text-end">${item.price.toFixed(2)}</td>
        <td class="text-end">${item.subtotal.toFixed(2)}</td>
      `;
      tbody.appendChild(tr);
    });
    
    document.getElementById('lblProductTotal').textContent = productTotal.toFixed(2);
    document.getElementById('lblGrandTotal').textContent = grandTotal.toFixed(2);
  }

  function generatePDF() {
    let btn = document.querySelector('.btn-download');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    // Show print-only elements properly for PDF
    document.querySelector('.signature-box').style.display = 'flex';
    
    setTimeout(() => {
        var element = document.getElementById('printArea');
        var opt = {
          margin:       [0.5, 0],
          filename:     'Quotation_<?= e($qid) ?>.pdf',
          image:        { type: 'jpeg', quality: 0.98 },
          html2canvas:  { scale: 2, useCORS: true },
          jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        
        html2pdf().set(opt).from(element).save().then(() => {
          document.querySelector('.signature-box').style.display = 'none';
          btn.innerHTML = '<i class="fas fa-file-pdf"></i> Download PDF';
          btn.disabled = false;
        });
    }, 100);
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
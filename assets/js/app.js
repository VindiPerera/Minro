/**
 * Minro POS - Main JavaScript
 */

$(document).ready(function () {

  // -------------------------------------------------------
  // Global AJAX Setup
  // -------------------------------------------------------
  $.ajaxSetup({
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });

  // -------------------------------------------------------
  // Initialize Select2 globally
  // -------------------------------------------------------
  $('select.select2').select2({ theme: 'bootstrap-5', width: '100%' });

  // -------------------------------------------------------
  // Initialize Flatpickr globally
  // -------------------------------------------------------
  $('input.datepicker').flatpickr({ dateFormat: 'Y-m-d', allowInput: true });
  $('input.datetimepicker').flatpickr({ dateFormat: 'Y-m-d H:i', enableTime: true, allowInput: true });

  // -------------------------------------------------------
  // DataTables global defaults
  // -------------------------------------------------------
  if ($.fn.DataTable) {
    $.extend(true, $.fn.dataTable.defaults, {
      language: {
        search: '<i class="fas fa-search"></i>',
        searchPlaceholder: 'Search...',
        lengthMenu: 'Show _MENU_',
        info: '_START_-_END_ of _TOTAL_',
        paginate: {
          previous: '<i class="fas fa-chevron-left"></i>',
          next: '<i class="fas fa-chevron-right"></i>'
        }
      },
      dom: "<'row mb-3'<'col-sm-6'l><'col-sm-6 d-flex justify-content-end'f>>" +
           "<'row'<'col-12'tr>>" +
           "<'row mt-3'<'col-sm-5'i><'col-sm-7 d-flex justify-content-end'p>>"
    });
  }

  // -------------------------------------------------------
  // Confirm Delete (data-confirm)
  // -------------------------------------------------------
  $(document).on('click', '[data-confirm]', function (e) {
    e.preventDefault();
    const msg  = $(this).data('confirm') || 'Are you sure?';
    const href = $(this).attr('href') || $(this).data('href');
    Swal.fire({
      title: 'Confirm Action',
      text: msg,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#334155',
      confirmButtonText: 'Yes, proceed!',
      background: '#1e293b',
      color: '#e2e8f0'
    }).then(r => { if (r.isConfirmed && href) window.location.href = href; });
  });

  // -------------------------------------------------------
  // Toast notification utility
  // -------------------------------------------------------
  window.toast = function (msg, type = 'success') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#0891b2' };
    Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      background: '#1e293b',
      color: '#e2e8f0'
    }).fire({ icon: type, title: msg });
  };

  // -------------------------------------------------------
  // Format currency
  // -------------------------------------------------------
  window.formatMoney = function (amount, symbol = 'Rs.') {
    return symbol + ' ' + parseFloat(amount || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  };

  // -------------------------------------------------------
  // Barcode generation utility
  // -------------------------------------------------------
  window.generateBarcode = function (elementId, value, options = {}) {
    const defaults = { format: 'CODE128', width: 2, height: 60, displayValue: true, fontSize: 12, margin: 5, lineColor: '#000', background: '#fff' };
    try {
      JsBarcode('#' + elementId, value, { ...defaults, ...options });
    } catch (e) {
      console.warn('Barcode error:', e);
    }
  };

  // -------------------------------------------------------
  // Print section utility
  // -------------------------------------------------------
  window.printSection = function (sectionId) {
    const el = document.getElementById(sectionId);
    if (!el) return;
    const win = window.open('', '_blank', 'width=600,height=800');
    win.document.write(`
      <html><head><title>Print</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <style>
        body { font-family: 'Courier New', monospace; background: white; color: black; }
        .no-print { display: none; }
      </style>
      </head><body>
      ${el.innerHTML}
      <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
      <script>
        window.onload = function() {
          if (typeof JsBarcode !== 'undefined') {
            document.querySelectorAll('[data-barcode]').forEach(function(el) {
              try { JsBarcode(el, el.getAttribute('data-barcode'), { format:'CODE128', width:2, height:50, displayValue:true, fontSize:11 }); } catch(e) {}
            });
          }
          setTimeout(() => { window.print(); window.close(); }, 500);
        };
      </scr` + `ipt>
      </body></html>`);
    win.document.close();
  };

  // -------------------------------------------------------
  // Number input + / - buttons
  // -------------------------------------------------------
  $(document).on('click', '.qty-plus',  function () {
    const inp = $(this).closest('.qty-control').find('input');
    inp.val(parseInt(inp.val() || 0) + 1).trigger('change');
  });
  $(document).on('click', '.qty-minus', function () {
    const inp = $(this).closest('.qty-control').find('input');
    const v = parseInt(inp.val() || 0) - 1;
    if (v >= 0) inp.val(v).trigger('change');
  });

  // -------------------------------------------------------
  // Auto-uppercase barcode inputs
  // -------------------------------------------------------
  $(document).on('input', '.barcode-input', function () {
    $(this).val($(this).val().toUpperCase());
  });

  // -------------------------------------------------------
  // Auto-dismiss alerts after 5s
  // -------------------------------------------------------
  setTimeout(() => {
    $('.alert.auto-dismiss').fadeOut(500, function() { $(this).remove(); });
  }, 5000);

});

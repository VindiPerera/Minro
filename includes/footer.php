  </div><!-- end page-content -->
</div><!-- end main-content -->
</div><!-- end wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- JsBarcode -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- Custom JS -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<script>
// Live date/time
function updateDateTime() {
    const now = new Date();
    const el = document.getElementById('liveDateTime');
    if (el) el.textContent = now.toLocaleDateString('en-US', {weekday:'short',year:'numeric',month:'short',day:'numeric'}) + ' ' + now.toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
updateDateTime(); setInterval(updateDateTime, 1000);

// Sidebar toggle
document.querySelectorAll('#sidebarToggle, #sidebarToggleMobile, #sidebarToggleBtn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.querySelector('.main-content').classList.toggle('expanded');
  });
});
</script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>



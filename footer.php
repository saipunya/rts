</div>
</main>
<footer>
    <!-- place footer here -->
    <div class="container text-center">
        <small>&copy; <?php echo date('Y'); ?> สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</small>
    </div>
</footer>
<!-- jQuery (required by DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-3gJwYp2Zb4d2Xk6Yf3g6k5Q5Y5e3y5p1a2b3c4d5e6f=" crossorigin="anonymous"></script>
<!-- Bootstrap JavaScript Libraries -->
<script
    src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
    crossorigin="anonymous"
></script>
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"
    integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+"
    crossorigin="anonymous"
></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    // Initialize DataTables on tables with class 'datatable'
    jQuery(function($){
        $('.datatable').each(function(){
            var opts = {
                responsive: true,
                pageLength: 25,
                lengthChange: true,
                columnDefs: [ { orderable: false, targets: 'no-sort' } ],
                language: {
                    search: "ค้นหา:",
                    lengthMenu: "แสดง _MENU_ รายการ",
                    info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
                    paginate: { previous: "ก่อนหน้า", next: "ถัดไป" },
                    infoEmpty: "แสดง 0 ถึง 0 จาก 0 รายการ",
                    zeroRecords: "ไม่พบข้อมูล"
                }
            };
            $(this).DataTable(opts);
        });
    });
</script>
</body>
</html>
<?php
// close stmt and mysqli safely if they exist
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    try {
        $stmt->close();
    } catch (Throwable $e) {
        // ignore: statement may already be closed
    }
}
if (isset($mysqli) && $mysqli instanceof mysqli) {
    try {
        $mysqli->close();
    } catch (Throwable $e) {
        // ignore
    }
}
?>

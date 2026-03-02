</div>
</main>
<footer>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-12 col-md-6">
                <div class="d-flex align-items-center gap-3 mb-3 mb-md-0">
                    <div class="footer-logo">
                        <i class="bi bi-tree-fill text-success" style="font-size: 2rem;"></i>
                    </div>
                    <div>
                        <h6 class="mb-1 text-white">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</h6>
                        <small class="text-white-50">ระบบจัดการยางพาราออนไลน์</small>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 text-md-end">
                <small class="text-white-50">&copy; <?php echo date('Y'); ?> สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</small>
                <div class="mt-2">
                    <small class="text-white-50">พัฒนาด้วยเทคโนโลยีสมัยใหม่</small>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
footer {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 2rem 0;
    color: #ffffff;
    margin-top: 3rem;
    position: relative;
}

footer::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.5), transparent);
}

.footer-logo i {
    filter: drop-shadow(0 2px 4px rgba(34, 197, 94, 0.3));
}
</style>
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
<!-- AOS Animation Library -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        once: true,
        offset: 100
    });

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

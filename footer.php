</div>
</main>
<footer>
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="footer-logo">
                        <i class="bi bi-tree-fill"></i>
                    </div>
                    <div>
                        <h6 class="mb-2 text-white footer-heading">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</h6>
                        <p class="mb-2 text-white-60 footer-text">ระบบจัดการการรวบรวมยางพาราและการชำระเงินสำหรับสมาชิก</p>
                        <div class="badge bg-success-subtle text-success-emphasis fw-semibold">เชื่อมโยงข้อมูลทุกลานรับซื้อ</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <h6 class="text-white mb-3 footer-heading">เมนูสำคัญ</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="dashboard.php">แดชบอร์ด</a></li>
                    <li><a href="members.php">สมาชิกและผู้ส่งมอบ</a></li>
                    <li><a href="rubbers.php">บันทึกรายการยาง</a></li>
                    <li><a href="prices.php">ราคาอ้างอิง</a></li>
                    <li><a href="report_rubber.php">รายงานสรุป</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h6 class="text-white mb-3 footer-heading">ติดต่อสหกรณ์</h6>
                <ul class="list-unstyled text-white-60 footer-contact">
                    <li><i class="bi bi-geo-alt-fill text-accent"></i> หมู่ 7 ต.ทุ่งลุยลาย อ.คอนสาร จ.ชัยภูมิ</li>
                    <li><i class="bi bi-telephone-outbound text-accent"></i> 044-123-456 ต่อ 101</li>
                    <li><i class="bi bi-envelope-open text-accent"></i> coop@tungluilai.or.th</li>
                    <li><i class="bi bi-clock-history text-accent"></i> เวลาทำการ: 08:30-16:30 น.</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom d-flex flex-column flex-md-row gap-2 justify-content-between align-items-start align-items-md-center mt-4">
            <div class="text-white-60 footer-bottom-text">&copy; <?php echo date('Y'); ?> สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด - ระบบการซื้อขายยางพารา</div>
            <div class="text-white-60 footer-bottom-text">อัปเดตล่าสุด: <?php echo date('d/m/Y'); ?> | พัฒนาด้วยเทคโนโลยีเว็บสมัยใหม่</div>
        </div>
    </div>
</footer>

<style>
footer {
    background: radial-gradient(circle at top, rgba(27,163,127,0.25), transparent 55%),
                linear-gradient(135deg, #0b1725 0%, #122c22 100%);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 2.5rem 0;
    color: #e2e8f0;
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
    background: linear-gradient(90deg, transparent, rgba(255, 209, 102, 0.45), transparent);
}

.footer-logo i {
    font-size: 2.4rem;
    color: var(--brand-accent, #ffd166);
    filter: drop-shadow(0 2px 4px rgba(255, 209, 102, 0.35));
}

.footer-heading {
    font-size: 1.25rem;
    letter-spacing: 0.02em;
}

.footer-text {
    font-size: 1.05rem;
}

.footer-links li + li {
    margin-top: 0.4rem;
}

.footer-links a {
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: color 0.2s ease;
    font-size: 1.05rem;
}

.footer-links a:hover {
    color: #fff;
}

.footer-contact li {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
    margin-bottom: 0.4rem;
    font-size: 1.05rem;
}

.footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.12);
    padding-top: 1rem;
}

.footer-bottom-text {
    font-size: 1rem;
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

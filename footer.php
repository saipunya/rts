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
                        <h6 class="mb-2 footer-heading">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</h6>
                        <p class="mb-2  footer-text">ระบบจัดการการรวบรวมยางพาราและการชำระเงินสำหรับสมาชิก</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <h6 class=" mb-3 footer-heading">เมนูสำคัญ</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="dashboard.php">แดชบอร์ด</a></li>
                    <li><a href="members.php">สมาชิกและผู้ส่งมอบ</a></li>
                    <li><a href="rubbers.php">บันทึกรายการยาง</a></li>
                    <li><a href="prices.php">ราคาอ้างอิง</a></li>
                    <li><a href="report_rubber.php">รายงานสรุป</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h6 class=" mb-3 footer-heading">ติดต่อสหกรณ์</h6>
                <ul class="list-unstyled  footer-contact">
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
    background: #d4edda;
    border-top: 1px solid #c3e6cb;
    padding: 2.5rem 0;
    color: #155724;
    margin-top: 3rem;
    position: relative;

    footer::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: #c3e6cb;
    }

    .footer-logo i {
        font-size: 2.4rem;
        color: #28a745;
        filter: none;
    }

    .footer-heading {
        font-size: 1rem;
        letter-spacing: 0.02em;
        color: #155724;
    }

    .footer-text {
        font-size: 1rem;
    }

    .footer-links li + li {
        margin-top: 0.4rem;
    }

    .footer-links a {
        color: #155724;
        text-decoration: none;
        transition: color 0.2s ease;
        font-size: 1rem;
    }

    .footer-links a:hover {
        color: #0d5322;
    }

    .footer-contact li {
        display: flex;
        gap: 0.6rem;
        align-items: flex-start;
        margin-bottom: 0.4rem;
        font-size: 1rem;
    }

    .footer-bottom {
        border-top: 1px solid #c3e6cb;
        padding-top: 1rem;
    }

    .footer-bottom-text {
        font-size: 1rem;
    }

    /* Enhanced Responsive Design */
    @media (max-width: 992px) {
        .footer-section h5 {
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .footer-section p,
        .footer-section li {
            font-size: 1rem;
        }
        
        .footer-section a {
            font-size: 1rem;
        }
    }

    @media (max-width: 768px) {
        .container {
            padding: 0 0.5rem;
        }
        
        .row.g-4 {
            gap: 2rem;
        }
        
        .col-12.col-lg-4,
        .col-6.col-lg-4 {
            flex: 0 0 100%;
            max-width: 100%;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .footer-section h5 {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }
        
        .footer-section p {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .footer-section ul {
            padding-left: 0;
            list-style: none;
        }
        
        .footer-section li {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .footer-section a {
            font-size: 1rem;
        }
        
        .footer-bottom {
            padding-top: 0.75rem;
            margin-top: 1rem;
            text-align: center;
        }
        
        .footer-bottom .d-flex {
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
        }
        
        .footer-bottom .text-muted {
            font-size: 0.9rem;
        }
        
        .footer-bottom a {
            font-size: 0.9rem;
        }
    }

    @media (max-width: 576px) {
        .container {
            padding: 0 0.25rem;
        }
        
        .row.g-4 {
            gap: 1.5rem;
        }
        
        .col-12.col-lg-4,
        .col-6.col-lg-4 {
            margin-bottom: 1rem;
        }
        
        .footer-section h5 {
            font-size: 1rem;
        }
        
        .footer-section p {
            font-size: 1rem;
        }
        
        .footer-section li {
            font-size: 1rem;
        }
        
        .footer-section a {
            font-size: 1rem;
        }
        
        .footer-bottom {
            padding-top: 0.5rem;
            margin-top: 0.75rem;
        }
        
        .footer-bottom .text-muted {
            font-size: 0.85rem;
        }
        
        .footer-bottom a {
            font-size: 0.85rem;
        }
    }

    /* Landscape orientation */
    @media (max-width: 768px) and (orientation: landscape) {
        .row.g-4 {
            gap: 1rem;
        }
        
        .col-12.col-lg-4,
        .col-6.col-lg-4 {
            margin-bottom: 0.75rem;
        }
    }

    /* Touch-friendly improvements */
    @media (hover: none) and (pointer: coarse) {
        .footer-section a {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
        }
        
        .footer-bottom a {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
        }
    }
</style>

<!-- jQuery (required by DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
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

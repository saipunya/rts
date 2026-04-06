</div>
</main>
<footer class="site-footer">
    <div class="footer-highlight"></div>
    <div class="container-md">
        <div class="footer-top">
            <div class="footer-brand">
                <div class="footer-logo">
                    <i class="bi bi-tree-fill"></i>
                </div>
                <div class="footer-brand-text">
                    <h5 class="footer-title">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</h5>
                    <p class="footer-text">ขับเคลื่อนการรวบรวมยางพาราและการดูแลสมาชิกด้วยระบบดิจิทัลที่ทันสมัย เชื่อมโยงข้อมูลการซื้อขาย การชำระเงิน และการบริการครบวงจร</p>
                </div>
                <div class="footer-meta">
                    <span><i class="bi bi-grid-1x2-fill"></i> ระบบบริหารจัดการครบวงจร</span>
                    <span><i class="bi bi-shield-check"></i> โปร่งใส ตรวจสอบได้ทุกขั้นตอน</span>
                </div>
            </div>
            <div class="footer-column">
                <h6 class="footer-heading">ติดต่อสหกรณ์</h6>
                <ul class="footer-contact list-unstyled">
                    <li><i class="bi bi-geo-alt-fill"></i> หมู่ 7 ต.ทุ่งลุยลาย อ.คอนสาร จ.ชัยภูมิ 36180</li>
                    <li><i class="bi bi-telephone-outbound"></i> 044-109752,089-9441753</li>
                    <li>
                        <!-- insert home icon -->
                         <i class="bi bi-house-door"></i> 080-0062515(ร้านค้าสหกรณ์)
                    </li>
                    <li><i class="bi bi-envelope-open"></i> tungluilay@gmail.com</li>
                    <li><i class="bi bi-clock-history"></i> เวลาทำการ 08:30 - 16:30 น. (จันทร์-ศุกร์)</li>
                </ul>
               
            </div>
        </div>
        <div class="footer-divider"></div>
        <div class="footer-bottom">
            <div class="footer-bottom-text">&copy; <?php echo date('Y'); ?> สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด - ระบบการซื้อขายยางพารา</div>
            <div class="footer-bottom-text">อัปเดตล่าสุด: <?php echo date('d/m/Y'); ?> | พัฒนาด้วยเทคโนโลยีเว็บสมัยใหม่</div>
        </div>
    </div>
</footer>

<style>
.site-footer {
    background: linear-gradient(180deg, #d4edda 0%, #f6fdf6 100%);
    border-top: 1px solid #c3e6cb;
    padding: 3rem 0 2.5rem;
    margin-top: 3rem;
    position: relative;
    overflow: hidden;
    color: #155724;
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}

.site-footer .footer-highlight {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at top right, rgba(40, 167, 69, 0.15), transparent 55%),
        radial-gradient(circle at bottom left, rgba(17, 122, 101, 0.12), transparent 55%);
    pointer-events: none;
    z-index: 0;
}

.site-footer .container {
    position: relative;
    z-index: 1;
}

.footer-top {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 2.5rem;
    align-items: flex-start;
}

.footer-brand {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.footer-logo {
    width: 72px;
    height: 72px;
    border-radius: 20px;
    background: rgba(40, 167, 69, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #28a745;
    font-size: 2.25rem;
    box-shadow: 0 12px 32px rgba(21, 87, 36, 0.18);
}

.footer-brand-text {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.footer-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
    color: #0d5322;
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}

.footer-text {
    font-size: 0.95rem;
    margin: 0;
    line-height: 1.6;
    color: #19692c;
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}

.footer-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.footer-meta span {
    display: inline-flex;
    gap: 0.5rem;
    align-items: center;
    background: rgba(255, 255, 255, 0.55);
    border: 1px solid rgba(195, 230, 203, 0.8);
    border-radius: 999px;
    padding: 0.3rem 0.8rem;
    font-size: 0.9rem;
    color: #155724;
    backdrop-filter: blur(4px);
}

.footer-meta i {
    color: #28a745;
    font-size: 1.2rem;
}

.footer-column {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.footer-heading {
    font-size: 1.1rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #0d5322;
    margin: 0;
}

.footer-links {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 0.55rem;
}

.footer-links a {
    font-size: 1.2rem;
    color: #155724;
    text-decoration: none;
    display: inline-flex;
    gap: 0.5rem;
    align-items: center;
    transition: color 0.2s ease, transform 0.2s ease;
}

.footer-links a:hover,
.footer-links a:focus {
    color: #0b5c1f;
    transform: translateX(4px);
}

.footer-links i {
    font-size: 1.15rem;
    color: #28a745;
}

.footer-contact {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 0.65rem;
}

.footer-contact li {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    font-size: 0.9rem;
    line-height: 1.6;
}

.footer-contact i {
    font-size: 1.35rem;
    color: #28a745;
    margin-top: 0.2rem;
}

.footer-cta {
    margin-top: 0.75rem;
}

.footer-button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.2rem;
    padding: 0.65rem 1.4rem;
    background: #28a745;
    color: #ffffff;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 8px 18px rgba(21, 87, 36, 0.18);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.footer-button:hover,
.footer-button:focus {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(21, 87, 36, 0.22);
    color: #ffffff;
}

.footer-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(21, 87, 36, 0.25), transparent);
    margin: 2.5rem 0 1.75rem;
}

.footer-bottom {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: space-between;
    align-items: center;
}

.footer-bottom-text {
    font-size: 0.85rem;
    color: rgba(13, 83, 34, 0.9);
    margin: 0;
}

@media (max-width: 1200px) {
    .footer-top {
        gap: 2rem;
    }
}

@media (max-width: 992px) {
    .footer-top {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .footer-bottom {
        gap: 1.5rem;
    }
}

@media (max-width: 768px) {
    .site-footer {
        padding: 2.5rem 0 2rem;
    }

    .footer-top {
        grid-template-columns: 1fr;
    }

    .footer-brand,
    .footer-column {
        text-align: center;
        align-items: center;
    }

    .footer-meta {
        justify-content: center;
    }

    .footer-links,
    .footer-contact {
        justify-items: center;
    }

    .footer-links a {
        justify-content: center;
    }

    .footer-contact li {
        justify-content: center;
        text-align: center;
    }

    .footer-contact i {
        display: none;
    }

    .footer-button {
        width: 100%;
        justify-content: center;
    }

    .footer-bottom {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 576px) {
    .footer-title {
        font-size: 1.45rem;
    }

    .footer-meta span {
        font-size: 1.05rem;
        width: 100%;
        justify-content: center;
    }

    .footer-bottom-text {
        font-size: 1.1rem;
    }
}

@media (hover: none) and (pointer: coarse) {
    .footer-links a,
    .footer-button {
        min-height: 48px;
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

<?php
require_once 'functions.php';
require_login();
include 'header.php';
$cu = current_user();
?>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
            <div class="small text-muted">สวัสดี <?php echo htmlspecialchars($cu['user_fullname'] ?? $cu['user_username']); ?> (<?php echo htmlspecialchars($cu['user_level']); ?>)</div>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house me-1"></i>หน้าแรก</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm ms-2"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-lightning-charge me-2"></i>Quick links</h5>
                    <p class="card-text small text-muted mb-2">ลิงก์ด่วนสำหรับใช้งานทั่วไป</p>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="index.php" class="btn btn-primary btn-sm"><i class="bi bi-house me-1"></i>ไปที่หน้าแรก</a>
                        <a href="rubbers.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-box me-1"></i>จัดการข้อมูลยาง</a>
                        <a href="members.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people me-1"></i>สมาชิก</a>
                        <a href="report_rubber.php" class="btn btn-outline-info btn-sm"><i class="bi bi-file-earmark-bar-graph me-1"></i>รายงานข้อมูลยางพารา (ค้นหา)</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Section Start -->
        <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-download me-2"></i>Export Data</h5>
                    <p class="card-text small text-muted mb-2">ส่งออกข้อมูลรายการตามรอบวันที่ที่รวบรวม (จากราคายาง)</p>
                    <?php
                    // ดึง pr_date จาก tbl_price โดยใช้ db() จาก functions.php
                    $dates = [];
                    $conn = db();
                    $sql = "SELECT DISTINCT pr_date FROM tbl_price ORDER BY pr_date DESC";
                    $result = $conn->query($sql);
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $dates[] = $row['pr_date'];
                        }
                    }
                    ?>
                    <form class="row g-2 align-items-end" method="get" action="export_rubbers_export.php" id="exportForm">
                        <div class="col-12">
                            <label for="pr_date" class="form-label mb-1">เลือกรอบวันที่ (pr_date)</label>
                            <select class="form-select form-select-sm" id="pr_date" name="pr_date" required>
                                <option value="">-- เลือกรอบวันที่ --</option>
                                <?php foreach($dates as $d): ?>
                                    <option value="<?php echo $d; ?>">
                                        <?php echo thai_date_format($d); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="button" onclick="exportType('pdf')" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>ส่งออก PDF</button>
                            
                            
                            <button type="button" onclick="exportType('excel')" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออก Excel</button>
                        </div>
                    </form>
                    <script>
                    function exportType(type) {
                        var form = document.getElementById('exportForm');
                        var pr_date = document.getElementById('pr_date').value;
                        if (!pr_date) { alert('กรุณาเลือกรอบวันที่'); return; }
                        var url = form.action + '?pr_date=' + encodeURIComponent(pr_date) + '&export_type=' + type;
                        // If exporting Excel (CSV/Excel-compatible), request server to prepend UTF-8 BOM so Thai characters display correctly in Excel
                        if (type === 'excel') {
                            url += '&bom=1';
                        }
                        window.open(url, '_blank');
                    }
                    </script>
                </div>
            </div>
        </div>
        <!-- Export Section End -->

        <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-person me-2"></i>Account</h5>
                    <p class="card-text mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($cu['user_username']); ?></p>
                    <p class="card-text mb-1"><strong>Fullname:</strong> <?php echo htmlspecialchars($cu['user_fullname']); ?></p>
                    <p class="card-text mb-0"><strong>Level:</strong> <?php echo htmlspecialchars($cu['user_level']); ?></p>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end align-items-center">
                    <div>
                        <a href="user_form.php?action=edit&id=<?php echo (int)($cu['user_id'] ?? 0); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square me-1"></i>แก้ไขโปรไฟล์</a>
                    </div>
                    
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-people me-2"></i>Members Management</h5>
                    <p class="card-text small text-muted">จัดการสมาชิก (CRUD) สำหรับผู้ใช้งานที่เข้าสู่ระบบ</p>
                    <div class="row gy-2">
                        <div class="col-auto"><a href="members.php" class="btn btn-success btn-sm"><i class="bi bi-list-ul me-1"></i>รายการสมาชิก</a></div>
                        <div class="col-auto"><a href="member_form.php?action=create" class="btn btn-outline-success btn-sm"><i class="bi bi-person-plus me-1"></i>เพิ่มสมาชิก</a></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (function_exists('is_admin') && is_admin()): ?>
        <div class="col-12 col-lg-6">
            <div class="card border-danger shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-danger"><i class="bi bi-person-gear me-2"></i>Admin — User Management</h5>
                    <p class="card-text small text-muted">จัดการบัญชีผู้ใช้งาน (เฉพาะผู้ดูแลระบบ)</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="users.php" class="btn btn-danger btn-sm"><i class="bi bi-list-ul me-1"></i>รายการผู้ใช้งาน</a>
                        <a href="user_form.php?action=create" class="btn btn-outline-danger btn-sm"><i class="bi bi-person-plus me-1"></i>สร้างผู้ใช้ใหม่</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card border-warning shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-warning"><i class="bi bi-cash-stack me-2"></i>Admin — Prices Management</h5>
                    <p class="card-text small text-muted">จัดการราคายาง (เฉพาะผู้ดูแลระบบ)</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="prices.php" class="btn btn-warning btn-sm"><i class="bi bi-list-ul me-1"></i>รายการราคายาง</a>
                        <a href="price_form.php?action=create" class="btn btn-outline-warning btn-sm"><i class="bi bi-plus-circle me-1"></i>เพิ่มราคายาง</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>
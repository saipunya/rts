<?php
require_once 'functions.php';
require_login();
include 'header.php';
$cu = current_user();
?>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Dashboard</h1>
            <div class="small text-muted">สวัสดี <?php echo htmlspecialchars($cu['user_fullname'] ?? $cu['user_username']); ?> (<?php echo htmlspecialchars($cu['user_level']); ?>)</div>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">หน้าแรก</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm ms-2">Logout</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Quick links</h5>
                    <p class="card-text small text-muted mb-2">ลิงก์ด่วนสำหรับใช้งานทั่วไป</p>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="index.php" class="btn btn-primary btn-sm">ไปที่หน้าแรก</a>
                        <a href="rubbers.php" class="btn btn-outline-primary btn-sm">จัดการข้อมูลยาง</a>
                        <a href="members.php" class="btn btn-outline-secondary btn-sm">สมาชิก</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Account</h5>
                    <p class="card-text mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($cu['user_username']); ?></p>
                    <p class="card-text mb-1"><strong>Fullname:</strong> <?php echo htmlspecialchars($cu['user_fullname']); ?></p>
                    <p class="card-text mb-0"><strong>Level:</strong> <?php echo htmlspecialchars($cu['user_level']); ?></p>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end align-items-center">
                    <div>
                        <a href="user_form.php?action=edit&id=<?php echo (int)($cu['user_id'] ?? 0); ?>" class="btn btn-sm btn-outline-primary">แก้ไขโปรไฟล์</a>
                    </div>
                    
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Members Management</h5>
                    <p class="card-text small text-muted">จัดการสมาชิก (CRUD) สำหรับผู้ใช้งานที่เข้าสู่ระบบ</p>
                    <div class="row gy-2">
                        <div class="col-auto"><a href="members.php" class="btn btn-success btn-sm">รายการสมาชิก</a></div>
                        <div class="col-auto"><a href="member_form.php?action=create" class="btn btn-outline-success btn-sm">เพิ่มสมาชิก</a></div>
                        <div class="col-auto"><a href="members_search.php" class="btn btn-outline-secondary btn-sm">ค้นหาสมาชิก</a></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (function_exists('is_admin') && is_admin()): ?>
        <div class="col-12 col-lg-6">
            <div class="card border-danger shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-danger">Admin — User Management</h5>
                    <p class="card-text small text-muted">จัดการบัญชีผู้ใช้งาน (เฉพาะผู้ดูแลระบบ)</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="users.php" class="btn btn-danger btn-sm">รายการผู้ใช้งาน</a>
                        <a href="user_form.php?action=create" class="btn btn-outline-danger btn-sm">สร้างผู้ใช้ใหม่</a>
                        <a href="users_search.php" class="btn btn-outline-secondary btn-sm">ค้นหาผู้ใช้งาน</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card border-warning shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-warning">Admin — Prices Management</h5>
                    <p class="card-text small text-muted">จัดการราคายาง (เฉพาะผู้ดูแลระบบ)</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="prices.php" class="btn btn-warning btn-sm">รายการราคายาง</a>
                        <a href="price_form.php?action=create" class="btn btn-outline-warning btn-sm">เพิ่มราคายาง</a>
                        <a href="price_form.php" class="btn btn-outline-secondary btn-sm">ฟอร์มราคา</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>
<?php
require_once 'functions.php';
require_login();
include 'header.php';
$cu = current_user();
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h3>Dashboard</h3>
            <p>สวัสดี <?php echo htmlspecialchars($cu['user_fullname'] ?? $cu['user_username']); ?> (<?php echo htmlspecialchars($cu['user_level']); ?>)</p>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Quick links</h5>
                <ul>
                    <li><a href="index.php">หน้าแรก</a></li>
                    <li><a href="members.php">บันทึกรวบรวมยาง</a></li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Account</h5>
                <p>Username: <?php echo htmlspecialchars($cu['user_username']); ?></p>
                <p>Fullname: <?php echo htmlspecialchars($cu['user_fullname']); ?></p>
                <p>Level: <?php echo htmlspecialchars($cu['user_level']); ?></p>
            </div>
        </div>
    </div>

    <!-- Members management (available to any logged-in user) -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card p-3">
                <h5>Members Management</h5>
                <p>จัดการสมาชิก (CRUD) สำหรับผู้ใช้งานที่เข้าสู่ระบบ</p>
                <ul>
                    <li><a href="members.php">รายการสมาชิก</a></li>
                    <li><a href="member_form.php">เพิ่ม/แก้ไขสมาชิก</a></li>
                    <li><a href="members_search.php">ค้นหาสมาชิก</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- User management (admin only) -->
    <?php if (function_exists('is_admin') && is_admin()): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="card p-3 border-danger">
                <h5>Admin: User Management</h5>
                <p>จัดการบัญชีผู้ใช้งาน (เฉพาะผู้ดูแลระบบ)</p>
                <ul>
                    <li><a href="users.php">รายการผู้ใช้งาน</a></li>
                    <li><a href="user_form.php?action=create">สร้างผู้ใช้ใหม่</a></li>
                    <li><a href="users_search.php">ค้นหาผู้ใช้งาน</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Prices management (admin only) -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card p-3 border-warning">
                <h5>Admin: Prices Management</h5>
                <p>จัดการราคายาง (เฉพาะผู้ดูแลระบบ)</p>
                <ul>
                    <li><a href="prices.php">รายการราคายาง</a></li>
                    <li><a href="price_form.php?action=create">เพิ่มราคายาง</a></li>
                    <li><a href="price_form.php?action=edit&id=1">แก้ไขราคายาง (ตัวอย่าง)</a></li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
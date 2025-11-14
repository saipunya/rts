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
                    <?php if (is_admin()): ?>
                        <li><a href="users.php">จัดการผู้ใช้ (Admin)</a></li>
                    <?php endif; ?>
                    <li><a href="index.php">หน้าแรก</a></li>
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

    <!-- Admin-only users management card -->
    <?php if (is_admin()): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="card p-3">
                <h5>Users Management</h5>
                <p>ส่วนจัดการผู้ใช้ สำหรับผู้ดูแลระบบ</p>
                <ul>
                    <li><a href="users.php">รายการผู้ใช้</a></li>
                    <li><a href="user_form.php">เพิ่มผู้ใช้ใหม่</a></li>
                    <li><a href="users.php?search=1">ค้นหาผู้ใช้</a></li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

</div>
<?php include 'footer.php'; ?>
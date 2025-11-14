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
</div>
<?php include 'footer.php'; ?>
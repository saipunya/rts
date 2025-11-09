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
</div>
<?php include 'footer.php'; ?>
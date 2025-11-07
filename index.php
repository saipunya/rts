<?php
// Start session and CSRF before any output
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; } // redirect if already logged-in
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
require('header.php');
?>
<style>
    .home-hero { background: linear-gradient(135deg,#f8fbff,#ffffff); border: 1px solid #e9eef5; border-radius: .75rem; padding: 2rem; }
    .step-card { border: 1px solid #e9eef5; border-radius: .75rem; }
</style>

<div class="container py-5">
    <div class="row align-items-center g-4">
        <div class="col-12 col-lg-7">
            <div class="home-hero">
                <h1 class="display-6 mb-2">ระบบรวบรวมยางพารา</h1>
                <p class="lead text-muted mb-3">จัดการรับซื้อ ชั่งน้ำหนัก บันทึกข้อมูล และรายงานผลได้อย่างรวดเร็วในที่เดียว</p>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">🌿 รับซื้อและบันทึกการขายจากสมาชิกอย่างเป็นระบบ</li>
                    <li class="mb-2">⚖️ บันทึกน้ำหนัก/คุณภาพและราคาตามเกณฑ์</li>
                    <li class="mb-2">📈 รายงานสรุปยอด คลัง และประวัติการซื้อขาย</li>
                </ul>
                <div class="mt-3">
                    <button class="btn btn-success" onclick="document.getElementById('username')?.focus();">เริ่มต้นใช้งาน</button>
                </div>
            </div>

            <div class="row g-3 mt-4">
                <div class="col-12 col-md-4">
                    <div class="h-100 p-3 step-card">
                        <div class="fw-semibold mb-1">1) รับยาง</div>
                        <div class="text-muted small">บันทึกผู้ขาย รายการ และเงื่อนไข</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="h-100 p-3 step-card">
                        <div class="fw-semibold mb-1">2) ชั่ง/คำนวณ</div>
                        <div class="text-muted small">บันทึกน้ำหนัก คุณภาพ และราคาอัตโนมัติ</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="h-100 p-3 step-card">
                        <div class="fw-semibold mb-1">3) สรุปผล</div>
                        <div class="text-muted small">ออกใบรับซื้อและดูรายงานแบบเรียลไทม์</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="card-title text-center mb-3">เข้าสู่ระบบ</h5>

                    <?php if (!empty($_SESSION['error'])): ?>
                        <div class="alert alert-danger small" role="alert">
                            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="login.php" autocomplete="on" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">ชื่อผู้ใช้งาน</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">รหัสผ่าน</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                                <label class="form-check-label" for="remember">จดจำฉัน</label>
                            </div>
                            <a href="forgot.php" class="small text-decoration-none">ลืมรหัสผ่าน?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">เข้าสู่ระบบ</button>
                    </form>
                </div>
            </div>
            <p class="text-center mt-3 small text-muted">© <?php echo date('Y'); ?> สหกรณ์</p>
        </div>
    </div>
</div>

<?php
require('footer.php');
?>
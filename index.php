<?php
// Start session and CSRF before any output
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; } // redirect if already logged-in
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
require('header.php');
?>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-12 col-sm-10 col-md-6 col-lg-4">
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
<?php
// Start session and CSRF before any output
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; } // redirect if already logged-in
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
require('header.php');
?>
<style>
    :root {
        --brand: #0d6efd;
        --brand-2: #38b6ff;
        --success: #198754;
        --bg-start: #f6fbff;
        --bg-end: #ffffff;
        --card-border: #e9eef5;
    }
    .hero-wrap { 
        background: radial-gradient(1200px 500px at -10% -20%, #e9f5ff 0%, #ffffff 55%) no-repeat;
        position: relative; overflow: hidden; border-radius: 1rem; border: 1px solid var(--card-border);
    }
    .hero-accent {
        display:inline-block; padding:.25rem .6rem; font-size:.75rem; border-radius:2rem;
        background: linear-gradient(90deg, rgba(13,110,253,.1), rgba(56,182,255,.12));
        color:#0b5ed7; border:1px solid rgba(13,110,253,.15);
    }
    .home-hero h1 { letter-spacing:.2px; }
    .feature-list li::marker { content: '✔ '; color: var(--success); }
    .feature-badges .badge {
        background: linear-gradient(90deg, #f1f7ff, #ffffff); border:1px solid var(--card-border); color:#0b5ed7;
    }
    .step-card {
        border: 1px solid var(--card-border); border-radius: .9rem; transition: transform .2s ease, box-shadow .2s ease;
        background: linear-gradient(180deg, #ffffff, #f9fbff);
    }
    .step-card:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(13,110,253,.08); }
    .login-glass.card {
        background: rgba(255,255,255,.9); backdrop-filter: blur(8px); border: 1px solid var(--card-border);
    }
    .form-control:focus { box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); border-color: var(--brand); }
    .btn-primary {
        background: linear-gradient(90deg, var(--brand), var(--brand-2)); border: none;
    }
    .btn-primary:hover { filter: brightness(.98); }
    .toggle-pass { border-color: var(--card-border); }
    .wave-sep {
        position:absolute; left:0; right:0; bottom:-1px; line-height:0;
    }
    @media (prefers-color-scheme: dark) {
        .hero-wrap { background: radial-gradient(1200px 500px at -10% -20%, #0b1220 0%, #0f172a 55%); border-color: rgba(255,255,255,.06); }
        .step-card { background: linear-gradient(180deg, #0f172a, #0b1220); border-color: rgba(255,255,255,.08); }
        .login-glass.card { background: rgba(15,23,42,.6); border-color: rgba(255,255,255,.08); }
        .feature-badges .badge { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.12); color:#cfe2ff; }
    }
</style>

<div class="container py-4 py-lg-5">
    <section class="hero-wrap p-4 p-md-5">
        <div class="row align-items-center g-4">
            <div class="col-12 col-lg-7">
                <div class="home-hero">
                    <span class="hero-accent mb-2">ระบบรวบรวมยางพารา</span>
                    <h1 class="display-6 fw-semibold mb-2">จัดการรับซื้อ ชั่งน้ำหนัก และรายงานผลได้ครบ จบในที่เดียว</h1>
                    <p class="lead text-muted mb-3">เพิ่มความแม่นยำ ลดงานเอกสาร และเห็นภาพรวมแบบเรียลไทม์</p>

                    <ul class="feature-list ps-3 mb-3">
                        <li class="mb-1">รับซื้อและบันทึกการขายจากสมาชิก</li>
                        <li class="mb-1">ชั่งน้ำหนัก/คุณภาพ คำนวณราคาอัตโนมัติ</li>
                        <li class="mb-1">รายงานยอด คลัง และประวัติการซื้อขาย</li>
                    </ul>

                    <div class="feature-badges d-flex flex-wrap gap-2">
                        <span class="badge rounded-pill">Realtime Reports</span>
                        <span class="badge rounded-pill">Role-based Access</span>
                        <span class="badge rounded-pill">Audit Logs</span>
                    </div>

                    <div class="mt-3">
                        <button class="btn btn-success" data-scroll="#login-card">เริ่มต้นใช้งาน</button>
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
                <div id="login-card" class="card login-glass shadow-sm">
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
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary toggle-pass" type="button" aria-label="แสดง/ซ่อนรหัสผ่าน">แสดง</button>
                                </div>
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

        <div class="wave-sep">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100" preserveAspectRatio="none">
                <path fill="currentColor" fill-opacity=".04" d="M0,64L80,58.7C160,53,320,43,480,58.7C640,75,800,117,960,117.3C1120,117,1280,75,1360,53.3L1440,32L1440,0L1360,0C1280,0,1120,0,960,0C800,0,640,0,480,0C320,0,160,0,80,0L0,0Z"></path>
            </svg>
        </div>
    </section>
</div>

<script>
    // Smooth scroll to login card
    document.querySelectorAll('[data-scroll]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var target = document.querySelector(this.getAttribute('data-scroll'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(function(){ document.getElementById('username')?.focus(); }, 350);
        });
    });
    // Toggle password visibility
    (function(){
        var btn = document.querySelector('.toggle-pass');
        var input = document.getElementById('password');
        if (btn && input) {
            btn.addEventListener('click', function(){
                var isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                this.textContent = isText ? 'แสดง' : 'ซ่อน';
                input.focus();
            });
        }
    })();
</script>

<?php
require('footer.php');
?>
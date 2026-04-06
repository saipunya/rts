<?php
require_once __DIR__ . '/functions.php';
$db = db();

$errors = [];
$msg = $_GET['msg'] ?? '';
$csrf = csrf_token();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'โทเค็นไม่ถูกต้อง';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errors[] = 'กรอกชื่อผู้ใช้และรหัสผ่าน';
        } else {
            $row = null;

            if ($st = $db->prepare('SELECT user_id, user_username, user_password, user_fullname, user_level, user_status FROM tbl_user WHERE user_username = ? LIMIT 1')) {
                $st->bind_param('s', $username);
                $st->execute();
                $res = $st->get_result();
                $row = $res->fetch_assoc() ?: null;
                $st->close();
            }

            if (!$row && ($st = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1'))) {
                $st->bind_param('s', $username);
                $st->execute();
                $res = $st->get_result();
                $row = $res->fetch_assoc() ?: null;
                $st->close();
            }

            if ($row) {
                $hash = $row['user_password'] ?? ($row['password'] ?? '');
                $uid = $row['user_id'] ?? ($row['id'] ?? null);
                $unameDb = $row['user_username'] ?? ($row['username'] ?? $username);
                $fullname = $row['user_fullname'] ?? ($row['fullname'] ?? $unameDb);
                $level = $row['user_level'] ?? ($row['role'] ?? 'user');
                $status = $row['user_status'] ?? ($row['status'] ?? 'active');

                $active = in_array(strtolower($status), ['active', '1', 'enabled', 'true'], true);

                if (!$active) {
                    $errors[] = 'บัญชีถูกระงับการใช้งาน';
                } elseif ($hash !== '' && password_verify($password, $hash)) {
                    $_SESSION['user_id'] = (int) $uid;
                    $_SESSION['user_username'] = (string) $unameDb;
                    $_SESSION['user_fullname'] = (string) $fullname;
                    $_SESSION['user_level'] = (string) $level;
                    $_SESSION['user_status'] = (string) $status;

                    $redirect = $_GET['redirect'] ?? 'dashboard.php';
                    if (strpos($redirect, '/') === 0) {
                        $redirect = ltrim($redirect, '/');
                    }

                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $errors[] = 'รหัสผ่านไม่ถูกต้อง';
                }
            } else {
                $errors[] = 'ไม่พบบัญชีผู้ใช้';
            }
        }
    }
}

include 'header.php';
?>
<style>
.login-shell,
.login-layout,
.login-layout .form-control,
.login-layout .form-label,
.login-layout .btn,
.login-layout .alert,
.login-layout p,
.login-layout h1,
.login-layout h2,
.login-layout span,
.login-layout div {
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}

.content-card {
    background: transparent;
    box-shadow: none;
    padding: 0;
}

.login-shell {
    min-height: clamp(540px, 72vh, 760px);
    display: grid;
    place-items: center;
    padding: 2rem 1rem 1rem;
}

.login-layout {
    width: min(1080px, 100%);
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(360px, 430px);
    background: #ffffff;
    border: 1px solid rgba(21, 87, 36, 0.12);
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 28px 60px rgba(16, 24, 40, 0.12);
}

.login-hero {
    position: relative;
    padding: 3rem;
    color: #f5fff6;
    background:
        radial-gradient(circle at top left, rgba(255, 255, 255, 0.18), transparent 34%),
        radial-gradient(circle at bottom right, rgba(255, 255, 255, 0.12), transparent 30%),
        linear-gradient(145deg, #1f6f43 0%, #2f9e57 48%, #95d5b2 100%);
}

.login-hero::after {
    content: '';
    position: absolute;
    inset: auto -80px -110px auto;
    width: 280px;
    height: 280px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
}

.login-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.9rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 1.2rem;
}

.login-hero h1 {
    font-size: clamp(2rem, 3vw, 3rem);
    line-height: 1.15;
    font-weight: 700;
    margin-bottom: 1rem;
}

.login-hero p {
    max-width: 34rem;
    font-size: 1.05rem;
    line-height: 1.8;
    color: rgba(255, 255, 255, 0.92);
    margin-bottom: 1.75rem;
}

.login-highlights {
    display: grid;
    gap: 0.85rem;
}

.login-highlight {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(8px);
}

.login-highlight i {
    font-size: 1.2rem;
}

.login-panel {
    padding: 2.5rem 2.25rem;
    background: linear-gradient(180deg, #fcfefc 0%, #f3faf5 100%);
}

.login-panel-head {
    margin-bottom: 1.5rem;
}

.login-kicker {
    display: inline-block;
    color: #157347;
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin-bottom: 0.35rem;
}

.login-title {
    font-size: 2rem;
    font-weight: 800;
    color: #163020;
    margin: 0;
}

.login-subtitle {
    margin: 0.5rem 0 0;
    color: #4f6b58;
    line-height: 1.7;
}

.login-alert {
    border: none;
    border-radius: 16px;
    padding: 0.9rem 1rem;
    font-size: 0.98rem;
    margin-bottom: 1rem;
}

.login-alert.alert-danger {
    background: #fff0f0;
    color: #a61b1b;
}

.login-alert.alert-info {
    background: #edf9f0;
    color: #1f6f43;
}

.login-form {
    display: grid;
    gap: 1rem;
}

.login-field {
    display: grid;
    gap: 0.5rem;
}

.form-label {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #294335;
}

.form-control {
    min-height: 54px;
    border-radius: 16px;
    border: 1px solid #d6e6d9;
    background: #ffffff;
    font-size: 1rem;
    padding: 0.85rem 1rem;
    box-shadow: inset 0 1px 2px rgba(16, 24, 40, 0.04);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.form-control::placeholder {
    color: #8aa092;
}

.form-control:focus {
    border-color: #2f9e57;
    box-shadow: 0 0 0 0.25rem rgba(47, 158, 87, 0.14);
    transform: translateY(-1px);
}

.login-actions {
    display: grid;
    gap: 0.85rem;
    margin-top: 0.25rem;
}

.login-btn {
    min-height: 54px;
    border: none;
    border-radius: 16px;
    background: linear-gradient(135deg, #1f6f43 0%, #2f9e57 55%, #74c69d 100%);
    color: #ffffff;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    box-shadow: 0 16px 30px rgba(31, 111, 67, 0.18);
    transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
}

.login-btn:hover,
.login-btn:focus {
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 20px 34px rgba(31, 111, 67, 0.24);
    filter: saturate(1.05);
}

.login-note {
    margin: 0;
    font-size: 0.95rem;
    color: #6c7f73;
    text-align: center;
}

@media (max-width: 991.98px) {
    .login-layout {
        grid-template-columns: 1fr;
    }

    .login-hero {
        padding: 2.25rem 1.5rem;
    }

    .login-panel {
        padding: 2rem 1.35rem 1.5rem;
    }
}

@media (max-width: 575.98px) {
    .login-shell {
        padding: 1rem 0.35rem 0;
    }

    .login-layout {
        border-radius: 22px;
    }

    .login-hero,
    .login-panel {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .login-title {
        font-size: 1.7rem;
    }
}
</style>

<section class="login-shell">
    <div class="login-layout">
        <div class="login-hero">
            <div class="login-badge"><i class="bi bi-shield-check"></i> ระบบงานสหกรณ์ยางพารา</div>
            <h1>จัดการข้อมูลรับซื้อยางให้เป็นระบบ ชัดเจน และตรวจสอบง่าย</h1>
            <p>เข้าสู่ระบบเพื่อดูข้อมูลการรับซื้อ ราคาอ้างอิง รายงาน และการจัดการสมาชิกภายในระบบเดียวที่ออกแบบให้ใช้งานง่ายทั้งคอมพิวเตอร์และมือถือ</p>

            <div class="login-highlights">
                <div class="login-highlight"><i class="bi bi-graph-up-arrow"></i><span>ติดตามข้อมูลและรายงานได้อย่างรวดเร็ว</span></div>
                <div class="login-highlight"><i class="bi bi-people"></i><span>จัดการสมาชิก ผู้ใช้งาน และรายการรับซื้ออย่างเป็นระเบียบ</span></div>
                <div class="login-highlight"><i class="bi bi-phone"></i><span>รองรับการใช้งานบนหน้าจอขนาดเล็กได้ดีขึ้น</span></div>
            </div>
        </div>

        <div class="login-panel">
            <div class="login-panel-head">
                <span class="login-kicker">Sign In</span>
                <h2 class="login-title">เข้าสู่ระบบ</h2>
                <p class="login-subtitle">กรอกชื่อผู้ใช้และรหัสผ่านเพื่อเข้าสู่ระบบบริหารจัดการข้อมูล</p>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-info login-alert"><?php echo e($msg); ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger login-alert"><?php echo e(implode(' | ', $errors)); ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

                <div class="login-field">
                    <label class="form-label" for="username"><i class="bi bi-person-fill"></i> ชื่อผู้ใช้</label>
                    <input class="form-control" id="username" name="username" required autofocus placeholder="กรอกชื่อผู้ใช้" value="<?php echo e($_POST['username'] ?? ''); ?>">
                </div>

                <div class="login-field">
                    <label class="form-label" for="password"><i class="bi bi-lock-fill"></i> รหัสผ่าน</label>
                    <input class="form-control" id="password" type="password" name="password" required placeholder="กรอกรหัสผ่าน">
                </div>

                <div class="login-actions">
                    <button class="btn login-btn w-100" type="submit"><i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ</button>
                    <p class="login-note">หากพบปัญหาในการใช้งาน กรุณาติดต่อผู้ดูแลระบบของสหกรณ์</p>
                </div>
            </form>
        </div>
    </div>
</section>
<?php include 'footer.php'; ?>
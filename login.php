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

                    session_regenerate_id(true);
                    $redirect = app_login_destination((string)($_GET['redirect'] ?? 'dashboard.php'));

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

.login-hero-icon {
    position: absolute;
    right: -40px;
    top: -30px;
    font-size: 6.5rem;
    opacity: 0.08;
    color: #ffffff;
    pointer-events: none;
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
/* Layered surfaces keep the form clear while giving the page depth. */
.login-shell{position:relative;isolation:isolate;padding:2.5rem 1rem;min-height:650px}
.login-shell::before,.login-shell::after{content:"";position:absolute;z-index:-1;pointer-events:none;border-radius:50%}
.login-shell::before{width:360px;height:360px;left:-60px;top:-50px;background:radial-gradient(circle,#a7d5b488,transparent 70%)}
.login-shell::after{width:300px;height:300px;right:-35px;bottom:-20px;background:radial-gradient(circle,#d7bc7770,transparent 70%)}
.login-layout{position:relative;border:1px solid #ffffff;border-radius:30px;box-shadow:0 2px 4px #153b2010,0 18px 40px #153b2020,0 45px 85px -30px #153b2045;grid-template-columns:minmax(0,1.05fr) minmax(360px,450px)}
.login-hero{padding:3.25rem 3rem;overflow:hidden;background:radial-gradient(ellipse at 0 0,#6eaa7840,transparent 60%),linear-gradient(145deg,#123c2a,#205b3b 60%,#37744c)}
.login-hero::before{content:"";position:absolute;width:420px;height:420px;border-radius:50%;border:1px solid #ffffff17;right:-260px;top:50px;box-shadow:0 0 0 45px #ffffff05,0 0 0 90px #ffffff04;pointer-events:none}
.login-hero::after{width:230px;height:230px;right:-90px;bottom:-120px;background:linear-gradient(145deg,#c2dcb31c,#ffffff08);border:1px solid #ffffff18}
.login-hero>*{position:relative;z-index:1}
.login-hero .login-hero-icon{position:absolute;width:200px;height:200px;right:-60px;top:-65px;opacity:.07}
.login-hero h1{font-size:clamp(1.9rem,3vw,2.6rem);line-height:1.4;letter-spacing:-.02em;text-shadow:0 3px 12px #071b2340;margin-bottom:1rem}
.login-hero p{font-size:.98rem;color:#e0eee3;line-height:1.85}
.login-badge{background:#ffffff12;border:1px solid #ffffff24;box-shadow:inset 0 1px 0 #ffffff16;font-size:.82rem;margin-bottom:1.8rem}
.login-highlights{gap:10px}
.login-highlight{padding:12px 14px;background:linear-gradient(120deg,#ffffff12,#ffffff06);border:1px solid #ffffff18;box-shadow:inset 0 1px 0 #ffffff0d,0 5px 12px #08241614;border-radius:12px;font-size:.88rem;line-height:1.6}
.login-highlight svg{width:20px;height:20px;flex-shrink:0;color:#c1dfbc}
.login-panel{position:relative;padding:3rem 2.5rem;background:linear-gradient(150deg,#fff 15%,#f6faf5);border-left:1px solid #fff;box-shadow:-15px 0 30px #0b302012}
.login-panel-head{margin-bottom:1.9rem}
.login-panel .login-kicker{font-size:.72rem;letter-spacing:.14em;color:#6a846b}
.login-panel .login-title{font-size:2rem;line-height:1.5;color:#183e29}
.login-panel .login-subtitle{font-size:.9rem;color:#6c7e70}
.login-panel .form-control{background:#fff;border-color:#d8e3d8;box-shadow:inset 0 2px 4px #183e2907,0 2px 3px #183e2903;border-radius:12px;min-height:54px;transform:none}
.login-panel .form-control:focus{border-color:#438454;box-shadow:0 0 0 4px #43845418;transform:none}
.login-panel .form-label{font-size:.9rem;font-weight:600}
.login-panel .login-btn{min-height:54px;border-radius:12px;background:linear-gradient(135deg,#245c38,#327447);border:1px solid #245c38;box-shadow:inset 0 1px 0 #ffffff22,0 4px 0 #163f27,0 10px 20px #245c3826;transform:none}
.login-panel .login-btn:hover{background:linear-gradient(135deg,#1c4d2e,#28603a);transform:none;box-shadow:inset 0 1px 0 #ffffff22,0 4px 0 #163f27,0 12px 22px #245c3833}
.login-panel .login-btn:active{transform:translateY(2px);box-shadow:0 2px 0 #163f27,0 5px 10px #245c3820}
.login-panel .login-btn:focus-visible{outline:3px solid #6b9f72;outline-offset:5px}
.login-note{font-size:.8rem;line-height:1.75;margin-top:10px}
@media(max-width:991px){.login-shell{padding:1.5rem 0;min-height:0}.login-layout{grid-template-columns:1fr;max-width:600px}.login-hero{padding:2rem}.login-hero h1{font-size:1.9rem}.login-hero p{margin-bottom:0}.login-highlights{display:none}.login-panel{padding:2rem;border-left:0;border-top:1px solid #fff;box-shadow:0 -10px 25px #0b302012}.login-badge{margin-bottom:1rem}}
@media(max-width:575px){.login-shell{padding:.5rem 0 1rem}.login-shell::before,.login-shell::after{display:none}.login-layout{border-radius:20px}.login-hero{padding:1.6rem 1.4rem}.login-hero h1{font-size:1.65rem;margin-bottom:.65rem}.login-hero p{display:none}.login-panel{padding:1.7rem 1.4rem}.login-panel .login-title{font-size:1.7rem}.login-panel-head{margin-bottom:1.4rem}}
</style>

<section class="login-shell">
    <div class="login-layout">
        <div class="login-hero">
            <div class="login-badge"><i data-lucide="shield-check" aria-hidden="true"></i> ระบบงานสหกรณ์ยางพารา</div>
            <i data-lucide="droplet" class="login-hero-icon" aria-hidden="true"></i>
            <h1>จัดการงานรับซื้อยาง<br>ได้อย่างมั่นใจ</h1>
            <p>เข้าสู่ระบบเพื่อดูข้อมูลการรับซื้อ ราคาอ้างอิง รายงาน และการจัดการสมาชิกภายในระบบเดียวที่ออกแบบให้ใช้งานง่ายทั้งคอมพิวเตอร์และมือถือ</p>

            <div class="login-highlights">
                <div class="login-highlight"><i data-lucide="bar-chart-2" aria-hidden="true"></i><span>ติดตามข้อมูลและรายงานได้อย่างรวดเร็ว</span></div>
                <div class="login-highlight"><i data-lucide="users" aria-hidden="true"></i><span>จัดการสมาชิก ผู้ใช้งาน และรายการรับซื้ออย่างเป็นระเบียบ</span></div>
                <div class="login-highlight"><i data-lucide="phone" aria-hidden="true"></i><span>รองรับการใช้งานบนหน้าจอขนาดเล็กได้ดีขึ้น</span></div>
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
                    <label class="form-label" for="username"><i data-lucide="user" aria-hidden="true"></i> ชื่อผู้ใช้</label>
                    <input class="form-control" id="username" name="username" required autofocus placeholder="กรอกชื่อผู้ใช้" value="<?php echo e($_POST['username'] ?? ''); ?>">
                </div>

                <div class="login-field">
                    <label class="form-label" for="password"><i data-lucide="lock" aria-hidden="true"></i> รหัสผ่าน</label>
                    <input class="form-control" id="password" type="password" name="password" required placeholder="กรอกรหัสผ่าน">
                </div>

                <div class="login-actions">
                    <button class="btn login-btn w-100" type="submit"><i data-lucide="log-in" class="me-2" aria-hidden="true"></i>เข้าสู่ระบบ</button>
                    <p class="login-note">หากพบปัญหาในการใช้งาน กรุณาติดต่อผู้ดูแลระบบของสหกรณ์</p>
                </div>
            </form>
        </div>
    </div>
</section>
<?php include 'footer.php'; ?>

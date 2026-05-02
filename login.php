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

                $active = in_array(strtolower((string) $status), ['active', '1', 'enabled', 'true'], true);

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
?>
<!doctype html>
<html lang="th" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <style>
        html, body {
            font-family: 'Sarabun', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-800">
    <?php
    $siteNavOuterClass = 'sticky top-0 z-50 border-b border-emerald-200 bg-emerald-100/95 text-emerald-950 shadow-sm backdrop-blur';
    $siteNavInnerClass = 'mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8';
    $siteNavBrandBadge = 'ระบบการรวบรวมยาง';
    $siteNavBrandTitle = 'สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด';
    $siteNavBrandIcon = 'banknotes';
    $siteNavNavId = 'loginNav';
    include __DIR__ . '/partials/site_nav.php';
    ?>

    <main class="bg-slate-50">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-6 sm:px-6 sm:py-8 lg:grid-cols-[minmax(0,1fr)_440px] lg:items-start lg:px-8">
            <section class="hidden lg:block">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">
                        <?php echo heroicon('shield-exclamation', 'h-4 w-4'); ?>
                        ระบบงานสหกรณ์ยางพารา
                    </div>
                    <h1 class="mt-5 text-4xl font-bold leading-tight text-slate-950">
                        เข้าสู่ระบบเพื่อจัดการข้อมูลรับซื้อยางอย่างเป็นระเบียบ
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-8 text-slate-600">
                        ใช้งานข้อมูลราคาอ้างอิง รายการรับซื้อ รายงาน และการจัดการสมาชิกในระบบเดียวที่ออกแบบให้สแกนข้อมูลได้เร็วและไม่รกตา
                    </p>

                    <div class="mt-8 grid max-w-2xl gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <span class="flex h-10 w-10 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                                <?php echo heroicon('chart-bar', 'h-5 w-5'); ?>
                            </span>
                            <p class="mt-4 text-sm font-semibold text-slate-900">ดูภาพรวมเร็ว</p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">ติดตามราคาและยอดรับซื้อ</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <span class="flex h-10 w-10 items-center justify-center rounded-md bg-cyan-50 text-cyan-700">
                                <?php echo heroicon('archive-box', 'h-5 w-5'); ?>
                            </span>
                            <p class="mt-4 text-sm font-semibold text-slate-900">จัดการรายการ</p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">บันทึกและตรวจสอบข้อมูล</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <span class="flex h-10 w-10 items-center justify-center rounded-md bg-amber-50 text-amber-700">
                                <?php echo heroicon('users', 'h-5 w-5'); ?>
                            </span>
                            <p class="mt-4 text-sm font-semibold text-slate-900">รองรับสมาชิก</p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">เชื่อมข้อมูลสมาชิกและทั่วไป</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700">Sign In</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-950">เข้าสู่ระบบ</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">กรอกชื่อผู้ใช้และรหัสผ่านเพื่อเข้าสู่ระบบบริหารจัดการข้อมูล</p>
                </div>

                <?php if ($msg): ?>
                    <div class="mt-5 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-800">
                        <?php echo e($msg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800">
                        <?php echo e(implode(' | ', $errors)); ?>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off" class="mt-6 space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

                    <div>
                        <label for="username" class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <?php echo heroicon('user-circle', 'h-5 w-5 text-slate-400'); ?>
                            ชื่อผู้ใช้
                        </label>
                        <input
                            class="min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-base outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                            id="username"
                            name="username"
                            required
                            autofocus
                            placeholder="กรอกชื่อผู้ใช้"
                            value="<?php echo e($_POST['username'] ?? ''); ?>"
                        >
                    </div>

                    <div>
                        <label for="password" class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <?php echo heroicon('shield-exclamation', 'h-5 w-5 text-slate-400'); ?>
                            รหัสผ่าน
                        </label>
                        <input
                            class="min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-base outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                            id="password"
                            type="password"
                            name="password"
                            required
                            placeholder="กรอกรหัสผ่าน"
                        >
                    </div>

                    <button class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700" type="submit">
                        <?php echo heroicon('arrow-right-on-rectangle', 'h-5 w-5'); ?>
                        เข้าสู่ระบบ
                    </button>

                    <p class="text-center text-sm leading-6 text-slate-500">
                        หากพบปัญหาในการใช้งาน กรุณาติดต่อผู้ดูแลระบบของสหกรณ์
                    </p>
                </form>
            </section>
        </div>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm text-slate-500 sm:px-6 lg:px-8">
            <p class="font-semibold text-slate-700">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</p>
            <p>&copy; <?php echo date('Y'); ?> ระบบการซื้อขายยางพารา</p>
        </div>
    </footer>
</body>
</html>

<?php
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: /C:/xampp/htdocs/rts/index.php'); exit; }

// User info for navbar
$username = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$fullname = htmlspecialchars($_SESSION['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
$level    = htmlspecialchars($_SESSION['user_level'] ?? '', ENT_QUOTES, 'UTF-8');
$status   = htmlspecialchars($_SESSION['user_status'] ?? '', ENT_QUOTES, 'UTF-8');

$errors = [];
$values = [
    'mem_group' => '',
    'mem_number' => '',
    'mem_fullname' => '',
    'mem_class' => '',
    'mem_saveby' => '',
    'mem_savedate' => '',
];

// If DB is unavailable, surface a concise message (and skip work later)
if (function_exists('db_connected') && !db_connected()) {
    $errors[] = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้';
    // Optionally log the underlying reason if available
    if (function_exists('db_connect_error') && db_connect_error()) {
        error_log('DB unavailable on member/create: ' . db_connect_error());
        if (defined('APP_DEBUG') && APP_DEBUG) {
            $errors[] = db_connect_error();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }

    $values['mem_group']   = trim($_POST['mem_group'] ?? '');
    $values['mem_number']  = trim($_POST['mem_number'] ?? '');
    $values['mem_fullname']= trim($_POST['mem_fullname'] ?? '');
    $values['mem_class']   = trim($_POST['mem_class'] ?? '');
    $values['mem_saveby']  = trim($_POST['mem_saveby'] ?? '');
    $values['mem_savedate']= trim($_POST['mem_savedate'] ?? '');

    foreach (['mem_group','mem_number','mem_fullname','mem_class','mem_saveby','mem_savedate'] as $f) {
        if ($values[$f] === '') {
            $errors[] = "กรุณากรอก {$f}";
        }
    }

    if (!$errors) {
        try {
            $stmt = $mysqli->prepare("INSERT INTO tbl_member (mem_group, mem_number, mem_fullname, mem_class, mem_saveby, mem_savedate) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                'ssssss',
                $values['mem_group'],
                $values['mem_number'],
                $values['mem_fullname'],
                $values['mem_class'],
                $values['mem_saveby'],
                $values['mem_savedate']
            );
            $stmt->execute();
            $stmt->close();

            header('Location: ../member_list.php'); // from /member/create.php go back to list
            exit;
        } catch (Throwable $e) {
            $errors[] = 'บันทึกไม่สำเร็จ';
            error_log('member/create insert failed: ' . $e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>เพิ่มสมาชิก</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      :root {
        --bg:#f0fdf4;
        --surface:#ffffff;
        --card:#ffffff;
        --text:#1e293b;
        --muted:#64748b;
        --brand:#10b981;
        --brand-600:#059669;
        --primary:#16a34a;
        --primary-600:#15803d;
        --ring:rgba(16,185,129,.25);
      }
      * { box-sizing: border-box; }
      body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans Thai", sans-serif; background: radial-gradient(900px 600px at 15% 0%, #dcfce7, #f0fdf4); color: var(--text); }
      a { color: inherit; text-decoration: none; }

      .navbar { position: sticky; top:0; z-index:10; backdrop-filter: blur(10px); background: rgba(255,255,255,.85); border-bottom:1px solid #d1fae5; }
      .nav-inner { max-width:1100px; margin:0 auto; display:flex; align-items:center; gap:16px; padding:12px 16px; }
      .brand { display:flex; align-items:center; gap:10px; font-weight:700; letter-spacing:.3px; }
      .brand-dot { width:10px; height:10px; border-radius:50%; background: linear-gradient(135deg, var(--brand), var(--brand-600)); box-shadow:0 0 10px rgba(16,185,129,.5); }
      .nav-links { margin-left:8px; display:flex; gap:8px; flex-wrap:wrap; }
      .nav-link { padding:6px 10px; border:1px solid transparent; color: var(--muted); border-radius:8px; }
      .nav-link:hover { border-color:#d1fae5; color: var(--primary-600); background:#f0fdf4; }
      .spacer { flex:1; }
      .user-chip { display:flex; align-items:center; gap:8px; padding:6px 10px; background:#ffffff; border:1px solid #d1fae5; border-radius:999px; color:#0f172a; }
      .badge { display:inline-block; padding:2px 8px; border-radius:999px; background:#ecfdf5; border:1px solid #d1fae5; font-size:12px; color:#065f46; }

      .container { max-width:1100px; margin:24px auto; padding:0 16px 40px; }
      .hero { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:16px; background: linear-gradient(135deg, #ecfdf5, #ffffff); border:1px solid #d1fae5; border-radius:16px; }
      .hero h1 { margin:0; font-size:24px; }
      .hero .meta { color: var(--muted); }

      .card { background:#ffffff; border:1px solid #d1fae5; border-radius:14px; padding:16px; box-shadow:0 2px 6px rgba(16,24,40,.05); margin-top:16px; }
      .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
      label{display:block;margin-top:10px}
      .input { width:100%; padding:10px 12px; margin-top:6px; box-sizing:border-box; border-radius:10px; background:#ffffff; border:1px solid #d1fae5; color:#0f172a; }
      .input:focus { outline: 2px solid #86efac; border-color:#10b981; }

      .actions{margin-top:16px;display:flex;gap:8px}
      .btn { display:inline-block; padding:10px 12px; border-radius:10px; background:#ffffff; border:1px solid #d1fae5; color:#065f46; cursor:pointer; }
      .btn:hover { background:#f0fdf4; border-color:#10b981; }
      .btn.primary { background: linear-gradient(180deg, var(--primary), var(--primary-600)); color:#fff; border-color:transparent; box-shadow:0 4px 10px rgba(20,83,45,.25); }
      .btn.primary:hover { filter:brightness(1.08); }
      .btn.ghost { background:transparent; border-color:#d1fae5; color:#065f46; }
      .btn.ghost:hover { background:#ecfdf5; }

      .error{margin:12px 0; padding:10px; border-radius:10px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca;}
    </style>
</head>
<body>
  <nav class="navbar">
    <div class="nav-inner">
      <div class="brand">
        <span class="brand-dot"></span>
        <a href="../dashboard.php">RTS Co‑Op</a>
      </div>
      <div class="nav-links">
        <a class="nav-link" href="../dashboard.php">แดชบอร์ด</a>
        <?php if (($_SESSION['user_level'] ?? '') === 'admin'): ?>
          <a class="nav-link" href="../admin_users.php">ผู้ใช้งาน</a>
          <a class="nav-link" href="../price_list.php">ราคายาง</a>
        <?php endif; ?>
        <a class="nav-link" href="../member_list.php">สมาชิก</a>
        <a class="nav-link" href="../purchase_list.php">รับซื้อยาง</a>
        <a class="nav-link" href="../report_daily.php">รายงาน</a>
      </div>
      <div class="spacer"></div>
      <div class="user-chip">
        <span><?php echo $fullname !== '' ? $fullname : $username; ?></span>
        <span class="badge"><?php echo $level ?: 'user'; ?></span>
        <span class="badge"><?php echo $status ?: 'active'; ?></span>
      </div>
      <a class="btn ghost" href="../logout.php">ออกจากระบบ</a>
    </div>
  </nav>

  <div class="container">
    <div class="hero">
      <div>
        <h1>เพิ่มสมาชิก</h1>
        <div class="meta">บันทึกข้อมูลสมาชิกใหม่เข้าสู่ระบบ</div>
      </div>
      <div class="actions">
        <a class="btn" href="../member_list.php">กลับรายการสมาชิก</a>
      </div>
    </div>

    <div class="card">
      <?php if ($errors): ?>
        <div class="error">
          <?php foreach ($errors as $e): ?>
            <div><?= e($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="row">
          <div>
            <label>กลุ่ม
              <input class="input" type="text" name="mem_group" required value="<?= e($values['mem_group']) ?>">
            </label>
          </div>
          <div>
            <label>หมายเลข
              <input class="input" type="text" name="mem_number" required value="<?= e($values['mem_number']) ?>">
            </label>
          </div>
        </div>
        <label>ชื่อ-นามสกุล
          <input class="input" type="text" name="mem_fullname" required value="<?= e($values['mem_fullname']) ?>">
        </label>
        <div class="row">
          <div>
            <label>ชั้น
              <input class="input" type="text" name="mem_class" required value="<?= e($values['mem_class']) ?>">
            </label>
          </div>
          <div>
            <label>บันทึกโดย
              <input class="input" type="text" name="mem_saveby" required value="<?= e($values['mem_saveby']) ?>">
            </label>
          </div>
        </div>
        <label>วันที่บันทึก
          <input class="input" type="date" name="mem_savedate" required value="<?= e($values['mem_savedate']) ?>">
        </label>

        <div class="actions">
          <button class="btn primary" type="submit">บันทึก</button>
          <a class="btn ghost" href="../member_list.php">ยกเลิก</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>

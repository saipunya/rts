<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require ('db.php');
require_admin();

// Ensure escaping helper exists
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Add user info for navbar + CSRF token for destructive actions
$username = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$fullname = htmlspecialchars($_SESSION['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
$level    = htmlspecialchars($_SESSION['user_level'] ?? '', ENT_QUOTES, 'UTF-8');
$status   = htmlspecialchars($_SESSION['user_status'] ?? '', ENT_QUOTES, 'UTF-8');
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Track DB errors locally
$db_error = null;

// Replace direct query with guarded execution
$rows = [];
try {
	if (isset($pdo) && $pdo instanceof PDO) {
		$rows = $pdo->query("SELECT pr_id, pr_year, pr_date, pr_number, pr_price, pr_saveby, pr_savedate
                     FROM tbl_price
                     ORDER BY pr_date DESC, pr_id DESC")->fetchAll(PDO::FETCH_ASSOC);
		if (!is_array($rows)) { $rows = []; }
	} else {
		$db_error = function_exists('db_error') ? db_error() : 'Database connection failed.';
	}
} catch (Throwable $e) {
	$db_error = 'Database connection failed.';
	if (function_exists('error_log')) { error_log('[price_list] ' . $e->getMessage()); }
}

$msg = flash();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการราคายาง</title>
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
      --danger:#dc2626;
      --danger-600:#b91c1c;
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

    .actions { display:flex; gap:8px; flex-wrap:wrap; }
    .btn { display:inline-block; padding:10px 12px; border-radius:10px; background:#ffffff; border:1px solid #d1fae5; color:#065f46; cursor:pointer; }
    .btn:hover { background:#f0fdf4; border-color:#10b981; }
    .btn.primary { background: linear-gradient(180deg, var(--primary), var(--primary-600)); color:#fff; border-color:transparent; box-shadow:0 4px 10px rgba(20,83,45,.25); }
    .btn.primary:hover { filter:brightness(1.08); }
    .btn.danger { background: linear-gradient(180deg, var(--danger), var(--danger-600)); color:#fff; border-color:transparent; }
    .btn.ghost { background:transparent; border-color:#d1fae5; color:#065f46; }
    .btn.ghost:hover { background:#ecfdf5; }

    .card { background:#ffffff; border:1px solid #d1fae5; border-radius:14px; padding:16px; box-shadow:0 2px 6px rgba(16,24,40,.05); margin-top:16px; }

    table { width:100%; border-collapse:separate; border-spacing:0; margin-top:8px; }
    thead th { background:#ecfdf5; color:#065f46; border-bottom:1px solid #d1fae5; padding:10px; text-align:left; }
    tbody td { padding:10px; }
    tr:not(:last-child) td { border-bottom:1px solid #eef2ff; }
    .right { text-align:right; }
    .muted { color:#6b7280; font-size:12px; }

    .msg { margin:12px 0; padding:10px; border-radius:10px; }
    .msg.info { background:#ecfeff; color:#0e7490; border:1px solid #a5f3fc; }
    .msg.error { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="nav-inner">
      <div class="brand">
        <span class="brand-dot"></span>
        <a href="dashboard.php">RTS Co‑Op</a>
      </div>
      <div class="nav-links">
        <a class="nav-link" href="dashboard.php">แดชบอร์ด</a>
        <a class="nav-link" href="admin_users.php">ผู้ใช้งาน</a>
        <a class="nav-link" href="member_list.php">สมาชิก</a>
        <a class="nav-link" href="price_list.php">ราคายาง</a>
      </div>
      <div class="spacer"></div>
      <div class="user-chip">
        <span><?php echo $fullname !== '' ? $fullname : $username; ?></span>
        <span class="badge"><?php echo $level ?: 'admin'; ?></span>
        <span class="badge"><?php echo $status ?: 'active'; ?></span>
      </div>
      <a class="btn ghost" href="logout.php">ออกจากระบบ</a>
    </div>
  </nav>

  <div class="container">
    <div class="hero">
      <div>
        <h1>จัดการราคายาง</h1>
        <div class="meta">บันทึก/แก้ไขราคายาง และรายการย้อนหลัง</div>
      </div>
      <div class="actions">
        <a class="btn primary" href="price_form.php">+ เพิ่มราคา</a>
        <a class="btn" href="dashboard.php">กลับหน้าหลัก</a>
      </div>
    </div>

    <div class="card">
      <?php if ($msg): ?><div class="msg info"><?php echo e($msg); ?></div><?php endif; ?>
      <?php if ($db_error): ?><div class="msg error"><?php echo e($db_error); ?></div><?php endif; ?>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>ปี</th>
            <th>วันที่</th>
            <th>เลขที่</th>
            <th class="right">ราคา</th>
            <th>บันทึกโดย</th>
            <th>บันทึกเมื่อ</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($db_error): ?>
            <tr><td colspan="8" class="muted">ไม่สามารถโหลดข้อมูลจากฐานข้อมูล</td></tr>
          <?php elseif (!$rows): ?>
            <tr><td colspan="8" class="muted">ยังไม่มีข้อมูล</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['pr_id']; ?></td>
              <td><?php echo e($r['pr_year']); ?></td>
              <td><?php echo e($r['pr_date']); ?></td>
              <td><?php echo e($r['pr_number']); ?></td>
              <td class="right"><?php echo number_format((float)$r['pr_price'], 2); ?></td>
              <td><?php echo e($r['pr_saveby']); ?></td>
              <td><?php echo e($r['pr_savedate']); ?></td>
              <td>
                <a class="btn" href="price_form.php?id=<?php echo (int)$r['pr_id']; ?>">แก้ไข</a>
                <form action="price_delete.php" method="post" style="display:inline" onsubmit="return confirm('ยืนยันการลบรายการนี้?');">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="pr_id" value="<?php echo (int)$r['pr_id']; ?>">
                  <button type="submit" class="btn danger">ลบ</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>

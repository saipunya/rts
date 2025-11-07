<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Require login and admin role
if (empty($_SESSION['user_id']) || (string)($_SESSION['user_level'] ?? '') !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Forbidden');
}

// CSRF token
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

try {
    $pdo = pdo();
} catch (Throwable $e) {
    error_log('Admin users DB connect error: ' . $e->getMessage());
    exit('DB error');
}

// Add user display info for navbar
$username = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$fullname = htmlspecialchars($_SESSION['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
$level    = htmlspecialchars($_SESSION['user_level'] ?? '', ENT_QUOTES, 'UTF-8');
$status   = htmlspecialchars($_SESSION['user_status'] ?? '', ENT_QUOTES, 'UTF-8');

// Handle actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'ไม่สามารถยืนยันคำขอได้'];
        header('Location: admin_users.php'); exit;
    }

    $action  = trim((string)($_POST['action'] ?? ''));
    $userId  = (int)($_POST['user_id'] ?? 0);

    if ($userId <= 0 || ($action !== 'activate' && $action !== 'deactivate')) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'คำขอไม่ถูกต้อง'];
        header('Location: admin_users.php'); exit;
    }

    // Prevent deactivating yourself
    if ($action === 'deactivate' && $userId === (int)($_SESSION['user_id'] ?? 0)) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'ไม่สามารถปิดใช้งานบัญชีของตนเองได้'];
        header('Location: admin_users.php'); exit;
    }

    try {
        $newStatus = $action === 'activate' ? 'active' : 'inactive';
        $stmt = $pdo->prepare('UPDATE `tbl_user` SET `user_status` = ? WHERE `user_id` = ?');
        $stmt->execute([$newStatus, $userId]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'อัปเดตสถานะผู้ใช้เรียบร้อย'];
    } catch (Throwable $e) {
        error_log('Admin users update error: ' . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'ไม่สามารถอัปเดตสถานะได้'];
    }

    header('Location: admin_users.php'); exit;
}

// Fetch users
$users = [];
try {
    $stmt = $pdo->query('SELECT `user_id`, `user_username`, `user_fullname`, `user_level`, `user_status` FROM `tbl_user` ORDER BY `user_id` DESC');
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Admin users list error: ' . $e->getMessage());
    exit('ไม่สามารถดึงข้อมูลผู้ใช้ได้');
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการผู้ใช้ (Admin)</title>
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
    .card { background:#ffffff; border:1px solid #d1fae5; border-radius:14px; padding:16px; box-shadow:0 2px 6px rgba(16,24,40,.05); }
    .btn { display:inline-block; padding:10px 12px; border-radius:10px; background:#ffffff; border:1px solid #d1fae5; color:#065f46; cursor:pointer; }
    .btn:hover { background:#f0fdf4; border-color:#10b981; }
    .btn.primary { background: linear-gradient(180deg, var(--primary), var(--primary-600)); color:#fff; border-color:transparent; box-shadow:0 4px 10px rgba(20,83,45,.25); }
    .btn.primary:hover { filter:brightness(1.08); }
    .btn.danger { background: linear-gradient(180deg, var(--danger), var(--danger-600)); color:#fff; border-color:transparent; }
    .btn.ghost { background:transparent; border-color:#d1fae5; color:#065f46; }
    .btn.ghost:hover { background:#ecfdf5; }
    .flash { margin:12px 0; padding:10px; border-radius:10px; }
    .flash.success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .flash.error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    table { width:100%; border-collapse:separate; border-spacing:0; }
    thead th { background:#ecfdf5; color:#065f46; border-bottom:1px solid #d1fae5; padding:10px; text-align:left; }
    tbody td { padding:10px; border-bottom:1px solid #eef2ff1a; }
    tr:not(:last-child) td { border-bottom:1px solid #eef2ff; }
    .tag { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; background:#e5e7eb; }
    .tag.active { background:#dcfce7; color:#166534; }
    .tag.inactive { background:#fee2e2; color:#991b1b; }
    .actions form { display:inline-block; margin:0 4px; }
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
        <h1>จัดการผู้ใช้ (Admin)</h1>
        <div class="meta">อนุมัติ/ปิดใช้งานบัญชีผู้ใช้งานระบบ</div>
      </div>
      <div class="actions">
        <a class="btn" href="dashboard.php">กลับแดชบอร์ด</a>
      </div>
    </div>

    <div class="card" style="margin-top:16px;">
      <?php if ($flash): ?>
        <div class="flash <?php echo h($flash['type']); ?>"><?php echo h($flash['msg']); ?></div>
      <?php endif; ?>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>ชื่อผู้ใช้</th>
            <th>ชื่อ-นามสกุล</th>
            <th>สิทธิ์</th>
            <th>สถานะ</th>
            <th>การจัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?php echo (int)$u['user_id']; ?></td>
              <td><?php echo h($u['user_username']); ?></td>
              <td><?php echo h($u['user_fullname']); ?></td>
              <td><?php echo h($u['user_level']); ?></td>
              <td>
                <?php $st = strtolower((string)$u['user_status']); ?>
                <span class="tag <?php echo $st === 'active' ? 'active' : ($st === 'inactive' ? 'inactive' : ''); ?>">
                  <?php echo h($u['user_status']); ?>
                </span>
              </td>
              <td class="actions">
                <?php if (strtolower((string)$u['user_status']) !== 'active'): ?>
                  <form method="post" action="admin_users.php">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                    <input type="hidden" name="action" value="activate">
                    <button class="btn primary" type="submit">อนุมัติ (Active)</button>
                  </form>
                <?php endif; ?>
                <?php if ((int)$u['user_id'] !== (int)($_SESSION['user_id'] ?? 0) && strtolower((string)$u['user_status']) === 'active'): ?>
                  <form method="post" action="admin_users.php">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                    <input type="hidden" name="action" value="deactivate">
                    <button class="btn danger" type="submit">ปิดใช้งาน</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>

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
    body { font-family: system-ui, Arial, sans-serif; background:#f7f7f7; padding:24px; }
    .box { max-width:980px; margin:0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:8px 10px; border-bottom:1px solid #eee; text-align:left; }
    .actions form { display:inline-block; margin:0 4px; }
    .btn { padding:6px 10px; border:0; border-radius:6px; cursor:pointer; color:#fff; }
    .btn-approve { background:#16a34a; }
    .btn-deactivate { background:#dc2626; }
    .tag { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; background:#e5e7eb; }
    .tag.active { background:#dcfce7; color:#166534; }
    .tag.inactive { background:#fee2e2; color:#991b1b; }
    .flash { margin:0 0 12px; padding:10px; border-radius:6px; }
    .flash.success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .flash.error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    a.button { display:inline-block; padding:8px 12px; background:#2563eb; color:#fff; border-radius:6px; text-decoration:none; }
    a.button:hover { background:#1d4ed8; }
  </style>
</head>
<body>
  <div class="box">
    <h1>จัดการผู้ใช้ (Admin)</h1>
    <p>
      <a class="button" href="dashboard.php">กลับแดชบอร์ด</a>
      <a class="button" href="logout.php" style="background:#6b7280">ออกจากระบบ</a>
    </p>

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
                <form method="post" action="admin_users.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                  <input type="hidden" name="action" value="activate">
                  <button class="btn btn-approve" type="submit">อนุมัติ (Active)</button>
                </form>
              <?php endif; ?>
              <?php if ((int)$u['user_id'] !== (int)($_SESSION['user_id'] ?? 0) && strtolower((string)$u['user_status']) === 'active'): ?>
                <form method="post" action="admin_users.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                  <input type="hidden" name="action" value="deactivate">
                  <button class="btn btn-deactivate" type="submit">ปิดใช้งาน</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>

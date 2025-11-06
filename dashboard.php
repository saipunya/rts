<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$username = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$fullname = htmlspecialchars($_SESSION['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
$level    = htmlspecialchars($_SESSION['user_level'] ?? '', ENT_QUOTES, 'UTF-8');
$status   = htmlspecialchars($_SESSION['user_status'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>แดชบอร์ด</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: system-ui, Arial, sans-serif; background:#f7f7f7; padding:24px; }
    .box { max-width:720px; margin:0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
    a.button { display:inline-block; padding:8px 12px; background:#2563eb; color:#fff; border-radius:6px; text-decoration:none; }
    a.button:hover { background:#1d4ed8; }
    .meta { color:#555; margin:8px 0 16px; }
  </style>
</head>
<body>
  <div class="box">
    <h1>ยินดีต้อนรับ, <?php echo $fullname !== '' ? $fullname : $username; ?></h1>
    <div class="meta">สิทธิ์: <?php echo $level; ?> | สถานะ: <?php echo $status; ?></div>
    <p>คุณเข้าสู่ระบบสำเร็จแล้ว</p>
    <?php if (($_SESSION['user_level'] ?? '') === 'admin'): ?>
      <p>
        <a class="button" href="admin_users.php">จัดการผู้ใช้ (Admin)</a> 
        <!-- เปลี่ยนลิงก์ไปหน้าจัดการราคายาง -->
        <a class="button" href="price_list.php">จัดการราคายาง</a>
         <!-- เปลี่ยนลิงก์ไปหน้าจัดการราคายาง -->
         <a class="button" href="add_rubber.php">บันทึกการรับยาง</a>
    </p>
    <?php endif; ?>
    <p><a class="button" href="logout.php">ออกจากระบบ</a></p>
  </div>
</body>
</html>

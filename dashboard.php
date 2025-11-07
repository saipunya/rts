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
    .navbar {
      position: sticky; top:0; z-index: 10;
      backdrop-filter: blur(10px);
      background: rgba(255,255,255,.85);
      border-bottom: 1px solid #d1fae5;
    }
    .nav-inner { max-width: 1100px; margin: 0 auto; display:flex; align-items:center; gap:16px; padding: 12px 16px; }
    .brand { display:flex; align-items:center; gap:10px; font-weight:700; letter-spacing:.3px; }
    .brand-dot { width:10px; height:10px; border-radius:50%; background: linear-gradient(135deg, var(--brand), var(--brand-600)); box-shadow: 0 0 10px rgba(16,185,129,.5); }
    .nav-links { margin-left: 8px; display:flex; gap:8px; flex-wrap: wrap; }
    .nav-link { padding:6px 10px; border:1px solid transparent; color: var(--muted); border-radius:8px; }
    .nav-link:hover { border-color: #d1fae5; color: var(--primary-600); background: #f0fdf4; }
    .spacer { flex:1; }
    .user-chip { display:flex; align-items:center; gap:8px; padding:6px 10px; background: #ffffff; border:1px solid #d1fae5; border-radius:999px; color:#0f172a; }
    .badge { display:inline-block; padding:2px 8px; border-radius:999px; background: #ecfdf5; border:1px solid #d1fae5; font-size:12px; color:#065f46; }
    .btn { display:inline-block; padding:10px 12px; border-radius:10px; background: #ffffff; border:1px solid #d1fae5; color:#065f46; }
    .btn:hover { background: #f0fdf4; border-color: #10b981; }
    .btn.primary { background: linear-gradient(180deg, var(--primary), var(--primary-600)); border-color: transparent; color: white; box-shadow: 0 4px 10px rgba(20,83,45,.25); }
    .btn.primary:hover { filter: brightness(1.08); }
    .btn.ghost { background: transparent; border-color: #d1fae5; color:#065f46; }
    .btn.ghost:hover { background: #ecfdf5; }
    .container { max-width:1100px; margin: 24px auto; padding: 0 16px 40px; }
    .hero { display:flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 16px; background: linear-gradient(135deg, #ecfdf5, #ffffff); border:1px solid #d1fae5; border-radius: 16px; }
    .hero h1 { margin:0; font-size: 24px; }
    .hero .meta { color: var(--muted); }
    .grid { display:grid; gap:16px; grid-template-columns: repeat(12, 1fr); margin-top: 18px; }
    .col-6 { grid-column: span 6; }
    .col-4 { grid-column: span 4; }
    @media (max-width: 900px) { .col-6, .col-4 { grid-column: span 12; } }
    .card {
      background: #ffffff;
      border:1px solid #d1fae5;
      border-radius:14px;
      padding:16px;
      min-height: 140px;
      display:flex; flex-direction:column; gap:12px;
      box-shadow: 0 2px 6px rgba(16,24,40,.05);
    }
    .card h3 { margin:0; font-size:18px; }
    .card p { margin:0; color: var(--muted); }
    .card .actions { display:flex; gap:8px; flex-wrap: wrap; margin-top: 8px; }
    .field { display:flex; gap:8px; }
    .input {
      flex:1; padding:10px 12px; border-radius:10px; background:#ffffff; border:1px solid #d1fae5; color:#0f172a;
    }
    .input:focus {
      outline: 2px solid #86efac;
      border-color: #10b981;
    }
    .footer { margin-top:24px; color: #64748b; text-align:center; font-size: 12px; }
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
        <?php if (($_SESSION['user_level'] ?? '') === 'admin'): ?>
          <a class="nav-link" href="admin_users.php">ผู้ใช้งาน</a>
          <a class="nav-link" href="price_list.php">ราคายาง</a>
        <?php endif; ?>
        <a class="nav-link" href="member_list.php">สมาชิก</a>
        <a class="nav-link" href="purchase_list.php">รับซื้อยาง</a>
        <a class="nav-link" href="report_daily.php">รายงาน</a>
      </div>
      <div class="spacer"></div>
      <div class="user-chip">
        <span><?php echo $fullname !== '' ? $fullname : $username; ?></span>
        <span class="badge"><?php echo $level ?: 'user'; ?></span>
        <span class="badge"><?php echo $status ?: 'active'; ?></span>
      </div>
      <a class="btn ghost" href="logout.php">ออกจากระบบ</a>
    </div>
  </nav>

  <div class="container">
    <div class="hero">
      <div>
        <h1>ยินดีต้อนรับ, <?php echo $fullname !== '' ? $fullname : $username; ?></h1>
        <div class="meta">แดชบอร์ดภาพรวมระบบสหกรณ์ยางพารา</div>
      </div>
      <div class="actions">
        <?php if (($_SESSION['user_level'] ?? '') === 'admin'): ?>
          <a class="btn primary" href="admin_users.php">จัดการผู้ใช้</a>
        <?php endif; ?>
        <a class="btn" href="add_rubber.php">บันทึกรับซื้อทันที</a>
      </div>
    </div>

    <div class="grid">
      <?php if (($_SESSION['user_level'] ?? '') === 'admin'): ?>
      <section class="card col-4">
        <h3>ผู้ใช้งานระบบ (User)</h3>
        <p>เจ้าหน้าที่/ผู้เกี่ยวข้อง จัดการสิทธิ์การใช้งานระบบ</p>
        <div class="actions">
          <a class="btn primary" href="admin_users.php">จัดการผู้ใช้</a>
        </div>
      </section>
      <?php endif; ?>

      <section class="card col-4">
        <h3>สมาชิกผู้ขายยาง (Member)</h3>
        <p>จัดการข้อมูลสมาชิก บุคคลทั่วไปที่มาขายยางให้สหกรณ์</p>
        <div class="actions">
          <a class="btn primary" href="member_list.php">จัดการสมาชิก</a>
          <a class="btn" href="member/create.php">เพิ่มสมาชิกใหม่</a>
        </div>
      </section>

      <?php if (($_SESSION['user_level'] ?? '') === 'admin'): ?>
      <section class="card col-4">
        <h3>ราคายาง (Rubber Prices)</h3>
        <p>ตั้งค่า/ปรับปรุงราคายางอ้างอิงประจำวัน</p>
        <div class="actions">
          <a class="btn primary" href="price_list.php">จัดการราคายาง</a>
          <a class="btn" href="price_add.php">เพิ่มราคา</a>
        </div>
      </section>
      <?php endif; ?>

      <section class="card col-6">
        <h3>รับซื้อยาง (Purchases)</h3>
        <p>ค้นหาสมาชิก บันทึกน้ำหนัก และรายการหักต่างๆ</p>
        <form class="field" action="purchase_list.php" method="get">
          <input class="input" type="text" name="q" placeholder="ค้นหาสมาชิก/รหัส/เบอร์โทร..." />
          <button class="btn" type="submit">ค้นหา</button>
          <a class="btn primary" href="add_rubber.php">บันทึกรับซื้อ</a>
        </form>
        <div class="actions">
          <a class="btn" href="purchase_list.php">ประวัติการรับซื้อ</a>
        </div>
      </section>

      <section class="card col-6">
        <h3>รายงาน (Reports)</h3>
        <p>สรุปผลการดำเนินงานรายวัน รายเดือน และรายปี</p>
        <div class="actions">
          <a class="btn primary" href="report_daily.php">รายวัน</a>
          <a class="btn" href="report_monthly.php">รายเดือน</a>
          <a class="btn" href="report_yearly.php">รายปี</a>
          <a class="btn ghost" href="reports.php">แดชบอร์ดรายงาน</a>
        </div>
      </section>
    </div>

    <div class="footer">RTS Co‑Op • ระบบบริหารจัดการรับซื้อยางพารา</div>
  </div>
</body>
</html>

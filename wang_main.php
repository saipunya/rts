<?php
require_once 'functions.php';
require_login();

// load counts and weights per lane
$db = db();
$lane_data = [];
for ($i = 1; $i <= 4; $i++) {
    $stm = $db->prepare('SELECT COUNT(*) AS c, COALESCE(SUM(wang_weight),0) AS w FROM tbl_wangyang WHERE wang_lan = ?');
    $lan = (string)$i;
    if ($stm) {
        $stm->bind_param('s', $lan);
        $stm->execute();
        $row = $stm->get_result()->fetch_assoc();
        $lane_data[$lan] = ['count' => (int)$row['c'], 'weight' => (float)$row['w']];
        $stm->close();
    } else {
        $lane_data[$lan] = ['count' => 0, 'weight' => 0];
    }
}
$cu = current_user();

$lane_colors = [
    '1' => ['bg' => '#dcfce7', 'border' => '#16a34a', 'badge' => '#16a34a', 'icon_bg' => '#bbf7d0', 'text' => '#14532d'],
    '2' => ['bg' => '#d1fae5', 'border' => '#059669', 'badge' => '#059669', 'icon_bg' => '#a7f3d0', 'text' => '#064e3b'],
    '3' => ['bg' => '#ccfbf1', 'border' => '#0d9488', 'badge' => '#0d9488', 'icon_bg' => '#99f6e4', 'text' => '#134e4a'],
    '4' => ['bg' => '#cffafe', 'border' => '#0891b2', 'badge' => '#0891b2', 'icon_bg' => '#a5f3fc', 'text' => '#164e63'],
];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>บันทึกวางยาง — เลือกลาน</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      font-family: 'Sarabun', sans-serif;
      min-height: 100vh;
      background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #f0fdfa 100%);
      color: #14532d;
    }
    /* ---- header ---- */
    .top-bar {
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid #bbf7d0;
      position: sticky; top: 0; z-index: 10;
      padding: 0.875rem 1.25rem;
    }
    .top-bar-inner {
      max-width: 720px; margin: 0 auto;
      display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
    }
    .brand { display:flex; align-items:center; gap:0.625rem; }
    .brand-icon {
      width:2.5rem; height:2.5rem;
      background:#16a34a; border-radius:0.625rem;
      display:flex; align-items:center; justify-content:center;
      flex-shrink:0;
    }
    .brand-icon svg { width:1.25rem; height:1.25rem; }
    .brand-title { font-size:1.1rem; font-weight:700; line-height:1.2; }
    .brand-sub { font-size:0.75rem; color:#16a34a; margin-top:1px; }
    /* nav buttons */
    .nav-btns { display:flex; gap:0.5rem; flex-shrink:0; }
    .nav-btn {
      display:inline-flex; align-items:center; gap:0.3rem;
      padding:0.45rem 0.9rem; border-radius:0.5rem;
      font-family:inherit; font-size:0.8rem; font-weight:600;
      cursor:pointer; text-decoration:none; border:1.5px solid transparent;
      transition:background .15s,color .15s;
      white-space:nowrap;
    }
    .nav-btn-secondary { background:#fff; border-color:#d1d5db; color:#374151; }
    .nav-btn-secondary:hover { background:#f3f4f6; }
    .nav-btn-danger { background:#fff; border-color:#fca5a5; color:#dc2626; }
    .nav-btn-danger:hover { background:#fef2f2; }
    /* ---- main ---- */
    .main { max-width:720px; margin:0 auto; padding:1.5rem 1rem 3rem; }
    .page-intro { margin-bottom:1.75rem; }
    .page-intro h2 { font-size:1.5rem; font-weight:700; margin:0 0 0.25rem; }
    .page-intro p { font-size:0.9rem; color:#4b7c5a; margin:0; }
    /* ---- lane grid ---- */
    .lane-grid {
      display:grid;
      grid-template-columns: repeat(2, 1fr);
      gap:1rem;
      margin-bottom:1.5rem;
    }
    @media(min-width:480px) { .lane-grid { grid-template-columns: repeat(2, 1fr); } }
    @media(min-width:680px) { .lane-grid { grid-template-columns: repeat(4, 1fr); } }
    .lane-card {
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      padding:1.5rem 1rem;
      border-radius:1rem;
      border:2px solid transparent;
      text-decoration:none;
      transition:transform .15s, box-shadow .15s;
      min-height:9rem;
      gap:0.625rem;
      box-shadow:0 1px 4px rgba(0,0,0,0.07);
    }
    .lane-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,0.12); }
    .lane-card:active { transform:translateY(0); }
    .lane-badge {
      display:inline-block;
      font-size:1.5rem; font-weight:700;
      width:3rem; height:3rem; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      margin-bottom:0.25rem;
    }
    .lane-label { font-size:1.05rem; font-weight:700; }
    .lane-stat { font-size:0.78rem; opacity:0.8; text-align:center; }
    /* ---- divider + all-lane ---- */
    .divider { border:none; border-top:1px solid #d1fae5; margin:0.5rem 0 1.25rem; }
    .all-lane-btn {
      display:flex; align-items:center; justify-content:center; gap:0.5rem;
      padding:0.75rem 1.5rem; border-radius:0.75rem;
      background:#fff; border:2px solid #6ee7b7; color:#047857;
      font-family:inherit; font-size:0.9rem; font-weight:600;
      text-decoration:none; transition:background .15s;
      width:100%;
    }
    .all-lane-btn:hover { background:#f0fdf4; }
  </style>
</head>
<body>
<header class="top-bar">
  <div class="top-bar-inner">
    <div class="brand">
      <div class="brand-icon">
        <svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3C7 3 3 7 3 12s4 9 9 9 9-4 9-9-4-9-9-9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v9l5 3"/></svg>
      </div>
      <div>
        <div class="brand-title">บันทึกวางยาง</div>
        <div class="brand-sub">เลือกลานเพื่อบันทึกข้อมูล</div>
      </div>
    </div>
    <div class="nav-btns">
      <a href="dashboard.php" class="nav-btn nav-btn-secondary">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v9a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1v-9"/></svg>
        Dashboard
      </a>
      <a href="logout.php" class="nav-btn nav-btn-danger">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/></svg>
        ออกจากระบบ
      </a>
    </div>
  </div>
</header>

<main class="main">
  <div class="page-intro">
    <h2>เลือกลาน</h2>
    <p>สวัสดี <?php echo htmlspecialchars($cu['user_fullname'] ?? $cu['user_username'] ?? ''); ?> — กดเลือกลานที่ต้องการบันทึกข้อมูลวางยางพารา</p>
  </div>

  <div class="lane-grid">
    <?php foreach ($lane_data as $lan => $data):
      $c = $lane_colors[$lan];
    ?>
    <a href="create_rubber.php?lane=<?php echo $lan; ?>" class="lane-card"
       style="background:<?php echo $c['bg']; ?>; border-color:<?php echo $c['border']; ?>; color:<?php echo $c['text']; ?>;">
      <div class="lane-badge" style="background:<?php echo $c['icon_bg']; ?>; color:<?php echo $c['badge']; ?>;">
        <?php echo $lan; ?>
      </div>
      <div class="lane-label">ลาน <?php echo $lan; ?></div>
      <div class="lane-stat">
        <?php echo number_format($data['count']); ?> รายการ<br>
        <?php echo number_format($data['weight'], 2); ?> กก.
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <hr class="divider">

  <a href="create_rubber.php" class="all-lane-btn">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
    ดูภาพรวมทุกลาน
  </a>
</main>
</body>
</html>

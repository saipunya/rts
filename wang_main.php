<?php
require_once 'functions.php';
require_login();

// load latest-day counts and bags per lane
$db = db();
$lane_data = [];
for ($i = 1; $i <= 4; $i++) {
    $lan = (string)$i;
    $latestDate = null;
    $stm = $db->prepare('SELECT MAX(wang_date) AS latest_date FROM tbl_wangyang WHERE wang_lan = ?');
    if ($stm) {
        $stm->bind_param('s', $lan);
        $stm->execute();
        $row = $stm->get_result()->fetch_assoc();
        $latestDate = $row['latest_date'] ?? null;
        $stm->close();
    }

    if ($latestDate) {
        $stm = $db->prepare('SELECT COUNT(*) AS c, COALESCE(SUM(wang_sack),0) AS sacks FROM tbl_wangyang WHERE wang_lan = ? AND wang_date = ?');
        if ($stm) {
            $stm->bind_param('ss', $lan, $latestDate);
            $stm->execute();
            $row = $stm->get_result()->fetch_assoc();
            $lane_data[$lan] = [
                'count' => (int)($row['c'] ?? 0),
                'sacks' => (float)($row['sacks'] ?? 0),
                'latest_date' => $latestDate,
            ];
            $stm->close();
        } else {
            $lane_data[$lan] = ['count' => 0, 'sacks' => 0, 'latest_date' => $latestDate];
        }
    } else {
        $lane_data[$lan] = ['count' => 0, 'sacks' => 0, 'latest_date' => null];
    }
}
$cu = current_user();

function format_thai_date_short(string $date): string {
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . ((int)date('Y', $ts) + 543);
}

$overviewSummary = [
    'entries' => 0,
    'sacks' => 0.0,
    'lanes' => 0,
    'latest_date' => null,
];

$stm = $db->prepare('SELECT MAX(wang_date) AS latest_date FROM tbl_wangyang');
if ($stm) {
    $stm->execute();
    $row = $stm->get_result()->fetch_assoc();
    $overviewSummary['latest_date'] = $row['latest_date'] ?? null;
    $stm->close();
}

if (!empty($overviewSummary['latest_date'])) {
    $stm = $db->prepare('SELECT COUNT(*) AS entries, COALESCE(SUM(wang_sack), 0) AS sacks, COUNT(DISTINCT wang_lan) AS lanes FROM tbl_wangyang WHERE wang_date = ?');
    if ($stm) {
        $stm->bind_param('s', $overviewSummary['latest_date']);
        $stm->execute();
        $row = $stm->get_result()->fetch_assoc();
        $overviewSummary['entries'] = (int)($row['entries'] ?? 0);
        $overviewSummary['sacks'] = (float)($row['sacks'] ?? 0);
        $overviewSummary['lanes'] = (int)($row['lanes'] ?? 0);
        $stm->close();
    }
}

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
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>บันทึกวางยาง - เลือกลาน</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <style>
  *,*::before,*::after{box-sizing:border-box}
  body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Sarabun', system-ui, -apple-system, "Segoe UI", sans-serif;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #eff6ff 100%);
    color: #14532d;
    -webkit-tap-highlight-color: transparent;
  }
  a{color:inherit;text-decoration:none}

  .container {
    width: 100%;
    padding-right: .75rem;
    padding-left: .75rem;
    margin-right: auto;
    margin-left: auto;
  }

  .app-header {
    position: sticky;
    top: 0;
    z-index: 1020;
    background: rgba(255,255,255,.9);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid #bbf7d0;
  }
  .header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    padding: .5rem 0;
  }
  .brand {
    display: flex;
    align-items: center;
    gap: .6rem;
  }
  .brand-icon {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: .65rem;
    background: #16a34a;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }
  .brand-icon i{width:1.2rem;height:1.2rem}
  .brand-title {
    font-size: .95rem;
    font-weight: 700;
    line-height: 1.2;
    color: #14532d;
  }
  .brand-subtitle {
    display: none;
    font-size: .78rem;
    color: #15803d;
  }
  .header-actions {
    display: flex;
    align-items: center;
    gap: .25rem;
  }
  .header-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .3rem;
    min-height: 2.25rem;
    padding: .35rem .5rem;
    border-radius: .65rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    font-size: .78rem;
    font-weight: 600;
    text-decoration: none;
    transition: background .15s;
    white-space: nowrap;
  }
  .header-actions .btn:active{background:#f1f5f9}
  .header-actions .btn span{display:none}
  .header-actions .btn-danger{border-color:#fca5a5;color:#dc2626}

  .date-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    font-size: .82rem;
    color: #2f6e43;
    padding: .4rem .75rem;
    margin-bottom: .75rem;
    background: rgba(255,255,255,.7);
    border-radius: 999px;
    border: 1px solid #bbf7d0;
  }
  .date-badge i{width:.95rem;height:.95rem}

  .summary-strip {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .5rem;
    margin-bottom: 1rem;
  }
  .summary-item {
    padding: .5rem .4rem;
    border-radius: .75rem;
    background: rgba(255,255,255,.85);
    border: 1px solid #d1fae5;
    text-align: center;
    min-height: 3.25rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }
  .summary-value {
    font-size: 1.2rem;
    font-weight: 800;
    color: #0f3d23;
    line-height: 1.1;
  }
  .summary-label {
    font-size: .65rem;
    color: #64748b;
    font-weight: 600;
  }

  .lanes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .75rem;
  }
  .lane-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .3rem;
    min-height: 9.5rem;
    border-radius: 1.25rem;
    border: 2.5px solid;
    padding: 1rem .6rem;
    transition: transform .12s ease, box-shadow .12s ease;
    cursor: pointer;
    -webkit-user-select: none;
    user-select: none;
    box-shadow: 0 4px 12px rgba(0,0,0,.05);
  }
  .lane-card:active{transform:scale(.96)}
  .lane-badge {
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    font-weight: 800;
    flex: 0 0 auto;
  }
  .lane-label {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.2;
  }
  .lane-stat {
    font-size: .75rem;
    opacity: .8;
    text-align: center;
    line-height: 1.3;
  }

  @media (hover:hover) {
    .lane-card:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.08)}
  }

  @media (min-width: 576px) {
    .container{max-width:540px;padding-right:1rem;padding-left:1rem}
    .header-inner{padding:.65rem 0}
    .brand-icon{width:2.5rem;height:2.5rem}
    .brand-icon i{width:1.35rem;height:1.35rem}
    .brand-title{font-size:1.05rem}
    .brand-subtitle{display:block}
    .header-actions .btn span{display:inline}
    .header-actions .btn{padding:.4rem .7rem}
    .lanes-grid{gap:1rem}
    .lane-card{min-height:11rem;border-radius:1.35rem}
    .lane-badge{width:3.5rem;height:3.5rem;font-size:1.65rem}
    .lane-label{font-size:1.1rem}
    .lane-stat{font-size:.82rem}
    .summary-item{min-height:3.5rem}
    .summary-value{font-size:1.3rem}
    .summary-label{font-size:.68rem}
  }

  @media (min-width: 768px) {
    .container{max-width:960px}
    .lanes-grid{grid-template-columns:repeat(4,1fr)}
    .lane-card{min-height:12rem;border-radius:1.5rem;gap:.4rem}
    .lane-badge{width:4rem;height:4rem;font-size:1.85rem}
    .lane-label{font-size:1.25rem}
    .lane-stat{font-size:.88rem}
    .summary-strip{margin-bottom:1.25rem}
    .summary-value{font-size:1.5rem}
    .date-badge{margin-bottom:1rem}
  }
  </style>
</head>
<body>
  <header class="app-header">
    <div class="container">
      <div class="header-inner">
        <a href="wang_main.php" class="brand">
          <span class="brand-icon"><i data-lucide="package-check" aria-hidden="true"></i></span>
          <div>
            <div class="brand-title">บันทึกวางยาง</div>
            <div class="brand-subtitle">เลือกลาน</div>
          </div>
        </a>
        <div class="header-actions">
          <a href="dashboard.php" class="btn">
            <i data-lucide="layout-dashboard" aria-hidden="true"></i>
            <span>แดชบอร์ด</span>
          </a>
          <a href="wang_summary.php" class="btn">
            <i data-lucide="clipboard-list" aria-hidden="true"></i>
            <span>สรุป</span>
          </a>
          <?php if (function_exists('is_admin') && is_admin()): ?>
          <a href="export_wang.php" class="btn" style="border-color:#86efac;color:#16a34a">
            <i data-lucide="file-down" aria-hidden="true"></i>
            <span>ส่งออก</span>
          </a>
          <?php endif; ?>
          <a href="logout.php" class="btn btn-danger">
            <i data-lucide="log-out" aria-hidden="true"></i>
            <span>ออกจากระบบ</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <main class="container" style="padding-top:1rem;padding-bottom:2rem">
    <div class="date-badge">
      <i data-lucide="calendar-days" aria-hidden="true"></i>
      <?php if (!empty($overviewSummary['latest_date'])): ?>
        วันที่ล่าสุด: <?php echo e(format_thai_date_short($overviewSummary['latest_date'])); ?>
      <?php else: ?>
        ยังไม่มีข้อมูล
      <?php endif; ?>
    </div>

    <div class="summary-strip">
      <div class="summary-item">
        <div class="summary-value"><?php echo number_format($overviewSummary['sacks'], 2); ?></div>
        <div class="summary-label">กระสอบรวม</div>
      </div>
      <div class="summary-item">
        <div class="summary-value"><?php echo number_format($overviewSummary['entries']); ?></div>
        <div class="summary-label">รายการ</div>
      </div>
      <div class="summary-item">
        <div class="summary-value"><?php echo number_format($overviewSummary['lanes']); ?></div>
        <div class="summary-label">ลานที่มีข้อมูล</div>
      </div>
    </div>

    <div class="lanes-grid">
      <?php foreach ($lane_data as $lan => $data):
        $c = $lane_colors[$lan];
      ?>
      <a href="create_rubber.php?lane=<?php echo e($lan); ?>" class="lane-card"
        style="background:<?php echo e($c['bg']); ?>;border-color:<?php echo e($c['border']); ?>;color:<?php echo e($c['text']); ?>;">
        <span class="lane-badge" style="background:<?php echo e($c['icon_bg']); ?>;color:<?php echo e($c['badge']); ?>;">
          <?php echo e($lan); ?>
        </span>
        <span class="lane-label">ลาน <?php echo e($lan); ?></span>
        <span class="lane-stat">
          <?php if (!empty($data['latest_date'])): ?>
            <?php echo number_format($data['sacks'], 2); ?> กระสอบ · <?php echo number_format($data['count']); ?> รายการ
          <?php else: ?>
            ยังไม่มีข้อมูล
          <?php endif; ?>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </main>

  <script>
  if (window.lucide && lucide.createIcons) {
    lucide.createIcons();
  }
  </script>
</body>
</html>

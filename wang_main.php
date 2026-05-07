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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>บันทึกวางยาง - เลือกลาน</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9Oer+R4zR4p8hTVU4hZ5pG1BSjYyV27lyZzGEjjqF2U6M" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <style>
  body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Sarabun', system-ui, -apple-system, "Segoe UI", sans-serif;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #eff6ff 100%);
    color: #14532d;
  }

  a {
    color: inherit;
  }

  .app-header {
    position: sticky;
    top: 0;
    z-index: 1020;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid #bbf7d0;
  }

  .container.main-shell {
    width: min(100% - 2rem, 960px);
    margin-left: auto;
    margin-right: auto;
  }

  .app-header.py-3 {
    padding-top: 1rem;
    padding-bottom: 1rem;
  }

  .app-header .main-shell {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }

  .app-header .main-shell>.d-flex,
  .header-actions {
    display: flex;
    align-items: center;
    gap: .75rem;
  }

  .brand-mark {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: .9rem;
    background: #16a34a;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .brand-title {
    color: #14532d;
    font-size: 1.08rem;
    font-weight: 700;
    line-height: 1.2;
  }

  .brand-subtitle {
    color: #15803d;
    font-size: .82rem;
  }

  .main-shell {
    max-width: 960px;
  }

  main.main-shell {
    padding-top: 1.5rem;
    padding-bottom: 3rem;
  }

  .hero-card {
    border: 1px solid #bbf7d0;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 18px 45px rgba(20, 83, 45, 0.08);
    padding: 1.35rem;
    margin-bottom: 1.35rem;
  }

  .hero-card>.d-flex {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }

  .hero-card h1,
  .h3 {
    margin: 0 0 .5rem;
    font-size: clamp(1.65rem, 4vw, 2.25rem);
    line-height: 1.15;
    color: #0f3d23;
  }

  .hero-card p {
    margin: 0;
    color: #2f6e43;
    font-size: 1rem;
  }

  .badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    border-radius: 999px;
    font-weight: 700;
    white-space: nowrap;
  }

  .text-bg-success-subtle {
    background: #dcfce7;
    color: #166534;
  }

  .border-success-subtle {
    border: 1px solid #bbf7d0;
  }

  section.row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
  }

  .lane-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 11rem;
    border-width: 2px;
    border-style: solid;
    border-radius: 1.25rem;
    text-decoration: none;
    transition: transform .16s ease, box-shadow .16s ease;
    padding: 1.35rem .85rem;
    box-shadow: 0 10px 26px rgba(15, 23, 42, .07);
  }

  .lane-card:hover,
  .lane-card:focus {
    transform: translateY(-3px);
    box-shadow: 0 14px 32px rgba(15, 23, 42, .12);
  }

  .lane-badge {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.65rem;
    font-weight: 700;
  }

  .lane-label {
    font-size: 1.1rem;
    font-weight: 700;
  }

  .lane-stat {
    font-size: .86rem;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    min-height: 44px;
    padding: .55rem 1rem;
    border-radius: 999px;
    border: 1px solid transparent;
    text-decoration: none;
    font-weight: 700;
    line-height: 1.2;
  }

  .btn-outline-secondary {
    background: #fff;
    border-color: #cbd5e1;
    color: #334155;
  }

  .btn-outline-secondary:hover {
    background: #f8fafc;
  }

  .btn-outline-danger {
    background: #fff;
    border-color: #fca5a5;
    color: #dc2626;
  }

  .btn-outline-danger:hover {
    background: #fef2f2;
  }

  .overview-btn {
    border-radius: 1rem;
    border: 2px solid #6ee7b7;
    color: #047857;
    background: #fff;
    font-weight: 700;
    width: 100%;
  }

  .overview-btn:hover {
    border-color: #10b981;
    color: #065f46;
    background: #f0fdf4;
  }

  .today-summary-card {
    margin-top: 1.5rem;
    padding: 1.15rem;
    border: 1px solid #86efac;
    border-radius: 1.25rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, .9), rgba(240, 253, 244, .95));
    box-shadow: 0 14px 34px rgba(20, 83, 45, .07);
  }

  .today-summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: 1rem;
  }

  .today-summary-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f3d23;
  }

  .today-summary-subtitle {
    font-size: .88rem;
    color: #2f6e43;
  }

  .today-summary-grid {
    display: grid;
    grid-template-columns: 1.4fr .8fr .8fr;
    gap: .85rem;
  }

  .today-summary-main,
  .today-summary-mini {
    border-radius: 1rem;
    border: 1px solid #d1fae5;
    background: #fff;
    padding: 1rem;
  }

  .today-summary-main {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 8.75rem;
  }

  .today-summary-kicker {
    font-size: .8rem;
    font-weight: 700;
    color: #166534;
    margin-bottom: .25rem;
  }

  .today-summary-value {
    font-size: clamp(2rem, 4vw, 3rem);
    line-height: 1;
    font-weight: 700;
    color: #0f3d23;
  }

  .today-summary-unit {
    font-size: 1rem;
    color: #166534;
    margin-left: .35rem;
  }

  .today-summary-mini {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: .35rem;
    min-height: 8.75rem;
  }

  .today-summary-mini-label {
    font-size: .75rem;
    font-weight: 700;
    color: #14532d;
    text-transform: uppercase;
    letter-spacing: .02em;
  }

  .today-summary-mini-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: #0f3d23;
  }

  .today-summary-mini-note {
    font-size: .82rem;
    color: #64748b;
  }

  .lane-summary-section {
    margin-top: 1.5rem;
    padding: 1.1rem;
    border: 1px solid #bbf7d0;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, .72);
    box-shadow: 0 14px 34px rgba(20, 83, 45, .06);
  }

  .lane-summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: 1rem;
  }

  .lane-summary-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f3d23;
  }

  .lane-summary-subtitle {
    font-size: .88rem;
    color: #2f6e43;
  }

  .lane-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .9rem;
  }

  .lane-summary-card {
    min-height: 10.5rem;
    border-width: 2px;
    border-style: solid;
    border-radius: 1.15rem;
    padding: 1rem;
    background: #fff;
    transition: transform .16s ease, box-shadow .16s ease;
    text-decoration: none;
  }

  .lane-summary-card:hover,
  .lane-summary-card:focus {
    transform: translateY(-3px);
    box-shadow: 0 14px 32px rgba(15, 23, 42, .12);
  }

  .lane-summary-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    margin-bottom: .85rem;
  }

  .lane-summary-num {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: .85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.05rem;
  }

  .lane-summary-lane {
    font-size: 1rem;
    font-weight: 700;
  }

  .lane-summary-date {
    font-size: .78rem;
    color: #64748b;
  }

  .lane-summary-metric {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .65rem;
    margin-bottom: .85rem;
  }

  .lane-summary-chip {
    padding: .55rem .65rem;
    border-radius: .85rem;
    background: #f8fdf8;
    border: 1px solid #e3f4e7;
  }

  .lane-summary-chip-label {
    font-size: .7rem;
    color: #14532d;
    margin-bottom: .15rem;
  }

  .lane-summary-chip-value {
    font-size: .98rem;
    font-weight: 700;
    color: #0f3d23;
  }

  .lane-summary-action {
    margin-top: auto;
    text-align: center;
    font-size: .82rem;
    font-weight: 700;
    color: #166534;
  }

  @media (max-width: 575.98px) {
    .app-header .main-shell {
      flex-direction: column;
      align-items: stretch;
    }

    .app-header .main-shell>.d-flex {
      justify-content: flex-start;
    }

    .brand-title {
      font-size: 1rem;
    }

    .header-actions {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      width: 100%;
    }

    .header-actions .btn {
      justify-content: center;
      min-height: 44px;
    }

    .hero-card {
      border-radius: 1rem;
    }

    .hero-card>.d-flex {
      flex-direction: column;
      align-items: flex-start;
    }

    section.row {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .75rem;
    }

    .lane-card {
      min-height: 9.5rem;
      border-radius: 1rem;
    }

    .lane-badge {
      width: 3rem;
      height: 3rem;
      font-size: 1.4rem;
    }

    .lane-summary-section {
      padding: .9rem;
      border-radius: 1rem;
    }

    .lane-summary-head {
      flex-direction: column;
      align-items: flex-start;
    }

    .lane-summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .today-summary-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (min-width: 576px) and (max-width: 991.98px) {
    section.row {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .lane-summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .today-summary-grid {
      grid-template-columns: 1fr 1fr;
    }
  }
  </style>
</head>

<body>
  <header class="app-header py-3">
    <div
      class="container main-shell d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="brand-mark">
          <i data-lucide="package-check" aria-hidden="true"></i>
        </span>
        <div>
          <div class="brand-title">บันทึกวางยาง</div>
          <div class="brand-subtitle">เลือกลานเพื่อบันทึกข้อมูล</div>
        </div>
      </div>
      <div class="header-actions d-flex gap-2 flex-shrink-0">
        <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-1">
          <i data-lucide="layout-dashboard" aria-hidden="true"></i>
          <span>แดชบอร์ด</span>
        </a>
        <a href="wang_summary.php" class="btn btn-outline-success rounded-pill d-inline-flex align-items-center gap-1">
          <i data-lucide="clipboard-list" aria-hidden="true"></i>
          <span>สรุป</span>
        </a>
        <a href="logout.php" class="btn btn-outline-danger rounded-pill d-inline-flex align-items-center gap-1">
          <i data-lucide="log-out" aria-hidden="true"></i>
          <span>ออกจากระบบ</span>
        </a>
      </div>
    </div>
  </header>

  <main class="container main-shell py-4 py-md-5">

    <section class="today-summary-card">
      <div class="today-summary-head">
        <div>
          <div class="today-summary-title">สรุปยอดของวันที่ล่าสุดที่มีข้อมูล</div>
          <div class="today-summary-subtitle">
            รวมยอดเฉพาะวันที่ล่าสุดที่มีข้อมูลในระบบ เพื่อให้ตรงกับยอดรวมจริงของวันนั้น
          </div>
        </div>
        <span
          class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill px-3 py-2">
          <i data-lucide="calendar-days" class="me-1" aria-hidden="true"></i>
          <?php echo !empty($overviewSummary['latest_date']) ? e(format_thai_date_short($overviewSummary['latest_date'])) : 'ยังไม่มีข้อมูล'; ?>
        </span>
      </div>

      <div class="today-summary-grid">
        <div class="today-summary-main">
          <div class="today-summary-kicker">กระสอบรวมของวันล่าสุด</div>
          <div class="today-summary-value">
            <?php echo number_format($overviewSummary['sacks'], 0); ?><span class="today-summary-unit">กระสอบ</span>
          </div>
          <div class="today-summary-mini-note mt-2">ข้อมูลรวมจากวันที่ล่าสุดที่มีรายการบันทึก</div>
        </div>
        <div class="today-summary-mini">
          <div class="today-summary-mini-label">รายการของวันล่าสุด</div>
          <div class="today-summary-mini-value"><?php echo number_format($overviewSummary['entries']); ?></div>
          <div class="today-summary-mini-note">จำนวนรายการทั้งหมดในวันนั้น</div>
        </div>
        <div class="today-summary-mini">
          <div class="today-summary-mini-label">ลานที่มีข้อมูล</div>
          <div class="today-summary-mini-value"><?php echo number_format($overviewSummary['lanes']); ?></div>
          <div class="today-summary-mini-note">นับเฉพาะลานที่มีข้อมูลในวันล่าสุด</div>
        </div>
      </div>
    </section>

    <section class="lane-summary-section">
      <div class="lane-summary-head">
        <div>
          <div class="lane-summary-title">สรุปผลการวางยางทั้ง 4 ลาน</div>
          <div class="lane-summary-subtitle">แสดงข้อมูลวันล่าสุดของแต่ละลานเพื่อดูภาพรวมได้เร็วขึ้น</div>
        </div>
        <span
          class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill px-3 py-2">
          <i data-lucide="bar-chart-3" class="me-1" aria-hidden="true"></i> สรุป 4 ลาน
        </span>
      </div>

      <div class="lane-summary-grid">
        <?php foreach ($lane_data as $lan => $data):
            $c = $lane_colors[$lan];
        ?>
        <a href="create_rubber.php?lane=<?php echo e($lan); ?>" class="lane-summary-card d-flex flex-column"
          style="background:<?php echo e($c['bg']); ?>; border-color:<?php echo e($c['border']); ?>; color:<?php echo e($c['text']); ?>;">
          <div class="lane-summary-top">
            <div class="d-flex align-items-center gap-2">
              <span class="lane-summary-num"
                style="background:<?php echo e($c['icon_bg']); ?>; color:<?php echo e($c['badge']); ?>;">
                <?php echo e($lan); ?>
              </span>
              <div>
                <div class="lane-summary-lane">ลาน <?php echo e($lan); ?></div>
                <div class="lane-summary-date">
                  <?php echo !empty($data['latest_date']) ? e(format_thai_date_short($data['latest_date'])) : 'ยังไม่มีข้อมูล'; ?>
                </div>
              </div>
            </div>
            <i data-lucide="chevron-right" aria-hidden="true"></i>
          </div>

          <div class="lane-summary-metric">
            <div class="lane-summary-chip">
              <div class="lane-summary-chip-label">รายการ</div>
              <div class="lane-summary-chip-value"><?php echo number_format($data['count']); ?></div>
            </div>
            <div class="lane-summary-chip">
              <div class="lane-summary-chip-label">กระสอบ</div>
              <div class="lane-summary-chip-value"><?php echo number_format($data['sacks']); ?></div>
            </div>
          </div>

          <div class="lane-summary-action">กดเพื่อเปิดลานนี้</div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
  </script>
  <script>
  if (window.lucide && lucide.createIcons) {
    lucide.createIcons();
  }
  </script>
</body>

</html>
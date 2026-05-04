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

  .app-header .main-shell > .d-flex,
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

  .hero-card > .d-flex {
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

  @media (max-width: 575.98px) {
    .app-header .main-shell {
      flex-direction: column;
      align-items: stretch;
    }

    .app-header .main-shell > .d-flex {
      justify-content: flex-start;
    }

    .brand-title {
      font-size: 1rem;
    }

    .header-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      width: 100%;
    }

    .header-actions .btn {
      justify-content: center;
      min-height: 44px;
    }

    .hero-card {
      border-radius: 1rem;
    }

    .hero-card > .d-flex {
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
  }

  @media (min-width: 576px) and (max-width: 991.98px) {
    section.row {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  </style>
</head>

<body>
  <header class="app-header py-3">
    <div class="container main-shell d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
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
          <span>Dashboard</span>
        </a>
        <a href="logout.php" class="btn btn-outline-danger rounded-pill d-inline-flex align-items-center gap-1">
          <i data-lucide="log-out" aria-hidden="true"></i>
          <span>ออกจากระบบ</span>
        </a>
      </div>
    </div>
  </header>

  <main class="container main-shell py-4 py-md-5">
    <section class="hero-card p-3 p-sm-4 mb-4">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
          <h1 class="h3 fw-bold mb-2">เลือกลาน</h1>
          <p class="mb-0 text-success-emphasis">
            สวัสดี <?php echo e($cu['user_fullname'] ?? $cu['user_username'] ?? ''); ?> กดเลือกลานที่ต้องการบันทึกข้อมูลวางยางพารา
          </p>
        </div>
        <span class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill px-3 py-2 align-self-start align-self-md-center">
          <i data-lucide="map" class="me-1" aria-hidden="true"></i> 4 ลานรับยาง
        </span>
      </div>
    </section>

    <section class="row g-3 g-md-4 mb-4" aria-label="เลือกลานรับยาง">
      <?php foreach ($lane_data as $lan => $data):
          $c = $lane_colors[$lan];
      ?>
      <div class="col-6 col-lg-3">
        <a href="create_rubber.php?lane=<?php echo e($lan); ?>"
          class="lane-card card h-100 text-center justify-content-center align-items-center p-3 p-sm-4"
          style="background:<?php echo e($c['bg']); ?>; border-color:<?php echo e($c['border']); ?>; color:<?php echo e($c['text']); ?>;">
          <span class="lane-badge mb-2" style="background:<?php echo e($c['icon_bg']); ?>; color:<?php echo e($c['badge']); ?>;">
            <?php echo e($lan); ?>
          </span>
          <span class="lane-label d-block mb-2">ลาน <?php echo e($lan); ?></span>
          <span class="lane-stat d-block">
            <?php echo number_format($data['count']); ?> รายการ<br>
            <?php echo number_format($data['weight'], 2); ?> กก.
          </span>
        </a>
      </div>
      <?php endforeach; ?>
    </section>

    <a href="create_rubber.php" class="overview-btn btn btn-lg w-100 d-inline-flex align-items-center justify-content-center gap-2">
      <i data-lucide="list" aria-hidden="true"></i>
      <span>ดูภาพรวมทุกลาน</span>
    </a>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
  <script>
  if (window.lucide && lucide.createIcons) {
    lucide.createIcons();
  }
  </script>
</body>

</html>

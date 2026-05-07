<?php
require_once 'functions.php';
require_login();

$db = db();
$cu = current_user();
$message = '';
$error = '';

function ensure_wang_daily_summary_table(mysqli $db): void {
    $db->query("
        CREATE TABLE IF NOT EXISTS tbl_wangyang_daily_summary (
            ws_date DATE NOT NULL PRIMARY KEY,
            ws_weight_per_bag DECIMAL(10,2) NOT NULL DEFAULT 0,
            ws_estimated_weight DECIMAL(18,2) NOT NULL DEFAULT 0,
            ws_saveby VARCHAR(255) NOT NULL DEFAULT '',
            ws_savedate DATETIME NOT NULL,
            INDEX idx_ws_savedate (ws_savedate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function format_thai_date_short(string $date): string {
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . ((int)date('Y', $ts) + 543);
}

ensure_wang_daily_summary_table($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $summaryDate = trim((string)($_POST['summary_date'] ?? ''));
    $weightPerBag = (float)($_POST['weight_per_bag'] ?? 0);

    if (!csrf_check($token)) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $summaryDate)) {
        $error = 'วันที่ไม่ถูกต้อง';
    } elseif ($weightPerBag < 0) {
        $error = 'น้ำหนักประมาณต่อถุงต้องไม่ติดลบ';
    } else {
        $totalBags = 0.0;
        $stmt = $db->prepare('SELECT COALESCE(SUM(wang_sack), 0) AS total_bags FROM tbl_wangyang WHERE wang_date = ?');
        if ($stmt) {
            $stmt->bind_param('s', $summaryDate);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $totalBags = (float)($row['total_bags'] ?? 0);
            $stmt->close();
        }

        $estimatedWeight = $totalBags * $weightPerBag;
        $saveBy = (string)($cu['user_fullname'] ?? $cu['user_username'] ?? '');
        $savedAt = date('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO tbl_wangyang_daily_summary
                (ws_date, ws_weight_per_bag, ws_estimated_weight, ws_saveby, ws_savedate)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ws_weight_per_bag = VALUES(ws_weight_per_bag),
                ws_estimated_weight = VALUES(ws_estimated_weight),
                ws_saveby = VALUES(ws_saveby),
                ws_savedate = VALUES(ws_savedate)
        ");

        if ($stmt) {
            $stmt->bind_param('sddss', $summaryDate, $weightPerBag, $estimatedWeight, $saveBy, $savedAt);
            if ($stmt->execute()) {
                $message = 'บันทึกสรุปวันที่ ' . format_thai_date_short($summaryDate) . ' เรียบร้อย';
            } else {
                $error = 'บันทึกไม่สำเร็จ: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'ไม่สามารถเตรียมคำสั่งบันทึกได้: ' . $db->error;
        }
    }
}

$rows = [];
$summaryRows = [];
$summaryTotals = [
    'member_count' => 0,
    'total_bags' => 0.0,
    'estimated_weight' => 0.0,
    'entry_count' => 0,
];

$sql = "
    SELECT
        w.wang_date AS summary_date,
        COUNT(*) AS entry_count,
        COUNT(DISTINCT CASE
            WHEN COALESCE(w.wang_mid, 0) > 0 THEN CONCAT('id:', w.wang_mid)
            ELSE CONCAT('name:', TRIM(w.wang_name))
        END) AS member_count,
        COALESCE(SUM(w.wang_sack), 0) AS total_bags,
        s.ws_weight_per_bag,
        ROUND(COALESCE(SUM(w.wang_sack), 0) * COALESCE(s.ws_weight_per_bag, 0), 2) AS ws_estimated_weight,
        s.ws_saveby,
        s.ws_savedate
    FROM tbl_wangyang w
    LEFT JOIN tbl_wangyang_daily_summary s ON s.ws_date = w.wang_date
    GROUP BY w.wang_date, s.ws_weight_per_bag, s.ws_saveby, s.ws_savedate
    ORDER BY w.wang_date DESC
    LIMIT 120
";

if ($result = $db->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $row['member_count'] = (int)($row['member_count'] ?? 0);
        $row['entry_count'] = (int)($row['entry_count'] ?? 0);
        $row['total_bags'] = (float)($row['total_bags'] ?? 0);
        $row['ws_weight_per_bag'] = $row['ws_weight_per_bag'] !== null ? (float)$row['ws_weight_per_bag'] : 0.0;
        $row['ws_estimated_weight'] = $row['ws_estimated_weight'] !== null ? (float)$row['ws_estimated_weight'] : 0.0;
        $rows[] = $row;
    }
    $result->free();
}

$summaryRows = array_slice($rows, 0, 2);
foreach ($summaryRows as $row) {
    $summaryTotals['member_count'] += $row['member_count'];
    $summaryTotals['entry_count'] += $row['entry_count'];
    $summaryTotals['total_bags'] += $row['total_bags'];
    $summaryTotals['estimated_weight'] += $row['ws_estimated_weight'];
}
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สรุปการวางยางรายวัน</title>
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
    text-decoration: none;
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

  .app-header .main-shell,
  .header-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
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

  .metric-card,
  .summary-card {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 9rem;
    border-width: 2px;
    border-style: solid;
    border-radius: 1.25rem;
    text-decoration: none;
    transition: transform .16s ease, box-shadow .16s ease;
    padding: 1.15rem .95rem;
    box-shadow: 0 10px 26px rgba(15, 23, 42, .07);
    background: #fff;
  }

  .metric-card:hover,
  .metric-card:focus,
  .summary-card:hover,
  .summary-card:focus {
    transform: translateY(-3px);
    box-shadow: 0 14px 32px rgba(15, 23, 42, .12);
  }

  .metric-label,
  .summary-label {
    font-size: .82rem;
    color: #14532d;
    margin-bottom: .35rem;
  }

  .metric-value,
  .summary-value {
    font-size: 1.6rem;
    line-height: 1.1;
    font-weight: 700;
    color: #0f3d23;
  }

  .summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
  }

  .summary-card {
    min-height: 15rem;
  }

  .summary-head {
    display: flex;
    justify-content: space-between;
    gap: .75rem;
    align-items: flex-start;
    margin-bottom: .9rem;
  }

  .summary-date {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f3d23;
  }

  .summary-sub {
    font-size: .82rem;
    color: #2f6e43;
  }

  .summary-pills {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: .9rem;
  }

  .mini-pill {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .4rem .7rem;
    border-radius: 999px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    font-size: .8rem;
    font-weight: 700;
  }

  .summary-body {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .65rem;
    margin-bottom: .95rem;
  }

  .summary-stat {
    padding: .65rem .75rem;
    border-radius: .85rem;
    background: #f8fdf8;
    border: 1px solid #e3f4e7;
  }

  .summary-stat-label {
    font-size: .72rem;
    color: #14532d;
    margin-bottom: .15rem;
  }

  .summary-stat-value {
    font-size: .98rem;
    font-weight: 700;
    color: #0f3d23;
  }

  .summary-footer {
    margin-top: auto;
  }

  .summary-table-wrap {
    overflow: auto;
  }

  .summary-table {
    min-width: 980px;
  }

  .summary-table thead th {
    white-space: nowrap;
    font-size: .86rem;
    color: #14532d;
    background: #eaf8ef;
    border-bottom: 1px solid #cfead6;
    text-transform: uppercase;
    letter-spacing: .02em;
  }

  .summary-table td {
    vertical-align: middle;
    border-color: #ecf7ef;
  }

  .summary-input {
    width: 10rem;
  }

  .form-label {
    margin-bottom: .35rem;
  }

  .form-control,
  .input-group-text {
    border-color: #cde9d5;
  }

  .form-control {
    min-height: 42px;
    box-shadow: none;
    background: #fff;
    font-size: .95rem;
  }

  .form-control:focus {
    border-color: #22c55e;
    box-shadow: 0 0 0 .18rem rgba(34, 197, 94, .12);
  }

  .input-group .form-control {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
  }

  .input-group .input-group-text {
    background: #f8fafc;
    color: #475569;
    border-left: 0;
    font-weight: 600;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    font-weight: 700;
    min-height: 44px;
    text-decoration: none;
  }

  .btn-success {
    background: linear-gradient(135deg, #16a34a, #15803d);
    border-color: #15803d;
    box-shadow: 0 10px 18px rgba(22, 163, 74, .18);
  }

  .summary-save-btn {
    border-radius: 999px;
    min-height: 42px;
  }

  .summary-clear-btn {
    border-radius: 999px;
    min-height: 42px;
  }

  .summary-save-btn:hover,
  .summary-save-btn:focus {
    color: #fff;
  }

  .btn-outline-secondary {
    background: rgba(255, 255, 255, .92);
    border-width: 1px;
    border-color: #cbd5e1;
    color: #334155;
    box-shadow: 0 8px 14px rgba(20, 83, 45, .05);
  }

  .btn-outline-secondary:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #94a3b8;
  }

  .btn-outline-success {
    background: rgba(255, 255, 255, .92);
    border-width: 1px;
    border-color: #b7e4c3;
    color: #166534;
    box-shadow: 0 8px 14px rgba(20, 83, 45, .05);
  }

  .btn-outline-success:hover {
    background: #f0fdf4;
    color: #14532d;
    border-color: #86efac;
  }

  .alert {
    border-radius: 1rem;
    border-color: #bbf7d0;
    box-shadow: 0 10px 24px rgba(20, 83, 45, .06);
  }

  .summary-weight-label {
    font-size: .78rem;
    color: #475569;
    font-weight: 600;
    margin-bottom: .25rem;
  }

  @media (max-width: 575.98px) {
    .app-header .main-shell {
      flex-direction: column;
      align-items: stretch;
    }

    .header-actions {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
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

    .summary-input {
      width: 100%;
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
    <div
      class="container main-shell d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="brand-mark">
          <i data-lucide="clipboard-list" aria-hidden="true"></i>
        </span>
        <div>
          <div class="brand-title">สรุปการวางยางรายวัน</div>
          <div class="brand-subtitle">คำนวณน้ำหนักประมาณจากจำนวนถุงต่อวัน</div>
        </div>
      </div>
      <div class="header-actions d-flex flex-wrap gap-2 flex-shrink-0">
        <a href="wang_main.php" class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-1">
          <i data-lucide="arrow-left" aria-hidden="true"></i>
          <span>วางยาง</span>
        </a>
        <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-1">
          <i data-lucide="layout-dashboard" aria-hidden="true"></i>
          <span>แดชบอร์ด</span>
        </a>
      </div>
    </div>
  </header>

  <main class="container main-shell py-4 py-md-5">
    <section class="hero-card p-3 p-sm-4 mb-4">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
          <h1 class="h3 fw-bold mb-2">สรุปการวางยาง</h1>
          <p class="mb-0 text-success-emphasis">สรุปรายวันให้เห็นครบทั้งคนที่เข้าร่วม กระสอบที่ได้
            และน้ำหนักประมาณจากค่าน้ำหนักต่อถุง</p>
        </div>
        <span
          class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill px-3 py-2 align-self-start align-self-md-center">
          <i data-lucide="calendar-days" class="me-1" aria-hidden="true"></i>สรุป 2 วันล่าสุด
        </span>
      </div>
    </section>

    <?php if ($message !== ''): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
      <i data-lucide="check-circle" aria-hidden="true"></i>
      <span><?php echo e($message); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i data-lucide="alert-triangle" aria-hidden="true"></i>
      <span><?php echo e($error); ?></span>
    </div>
    <?php endif; ?>

    <section class="row g-3 mb-4">
      <div class="col-12 col-md-3">
        <div class="metric-card" style="background:#dcfce7;border-color:#16a34a;">
          <div class="d-flex align-items-center gap-3">
            <span class="stat-icon bg-success-subtle text-success"><i data-lucide="calendar"
                aria-hidden="true"></i></span>
            <div>
              <div class="metric-label">จำนวนวันที่มีข้อมูล</div>
              <div class="metric-value"><?php echo number_format(count($summaryRows)); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="metric-card" style="background:#dbeafe;border-color:#2563eb;">
          <div class="d-flex align-items-center gap-3">
            <span class="stat-icon bg-primary-subtle text-primary"><i data-lucide="users" aria-hidden="true"></i></span>
            <div>
              <div class="metric-label">สมาชิกเข้าร่วมรวม</div>
              <div class="metric-value"><?php echo number_format($summaryTotals['member_count']); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="metric-card" style="background:#cffafe;border-color:#0ea5e9;">
          <div class="d-flex align-items-center gap-3">
            <span class="stat-icon bg-info-subtle text-info"><i data-lucide="package" aria-hidden="true"></i></span>
            <div>
              <div class="metric-label">กระสอบรวม</div>
              <div class="metric-value"><?php echo number_format($summaryTotals['total_bags']); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="metric-card" style="background:#fef3c7;border-color:#f59e0b;">
          <div class="d-flex align-items-center gap-3">
            <span class="stat-icon bg-warning-subtle text-warning"><i data-lucide="scale" aria-hidden="true"></i></span>
            <div>
              <div class="metric-label">น้ำหนักประมาณรวม</div>
              <div class="metric-value"><?php echo number_format($summaryTotals['estimated_weight'], 2); ?></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="surface-card overflow-hidden">
      <div
        class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 p-3 border-bottom border-success-subtle">
        <div>
          <h2 class="h5 fw-bold mb-1 text-success-emphasis">รายการสรุปรายวัน</h2>
          <div class="small text-muted">กรอกน้ำหนักประมาณต่อถุงในแต่ละวัน แล้วกดบันทึกเพื่อคำนวณน้ำหนักรวมของวันนั้น
          </div>
        </div>
        <div class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis rounded-pill">
          <i data-lucide="clipboard-list" aria-hidden="true"></i>คำนวณจากข้อมูลจริงในวันนั้น
        </div>
      </div>
      <div class="p-3 p-sm-4">
        <div class="summary-table-wrap">
          <table class="table table-hover align-middle mb-0 summary-table">
            <thead>
              <tr>
                <th>วันที่</th>
                <th class="text-end">สมาชิกเข้าร่วม</th>
                <th class="text-end">จำนวนรายการ</th>
                <th class="text-end">กระสอบ/ถุง</th>
                <th>น้ำหนักประมาณต่อถุง</th>
                <th class="text-end">น้ำหนักคำนวณ</th>

                <th class="text-center">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
              <tr>
                <td colspan="8" class="text-center text-success py-5">ยังไม่มีข้อมูลวางยาง</td>
              </tr>
              <?php endif; ?>
              <?php foreach ($rows as $index => $row): ?>
              <?php $formId = 'summary-form-' . $index; ?>
              <tr>
                <td data-label="วันที่" class="fw-semibold text-success-emphasis">
                  <form id="<?php echo e($formId); ?>" method="post"></form>
                  <input form="<?php echo e($formId); ?>" type="hidden" name="csrf_token"
                    value="<?php echo e(csrf_token()); ?>">
                  <input form="<?php echo e($formId); ?>" type="hidden" name="summary_date"
                    value="<?php echo e($row['summary_date']); ?>">
                  <?php echo e(format_thai_date_short($row['summary_date'])); ?>
                </td>
                <td data-label="สมาชิกเข้าร่วม" class="text-end"><?php echo number_format($row['member_count']); ?> ราย
                </td>
                <td data-label="จำนวนรายการ" class="text-end"><?php echo number_format($row['entry_count']); ?></td>
                <td data-label="กระสอบ/ถุง" class="text-end"><?php echo number_format($row['total_bags']); ?></td>
                <td data-label="น้ำหนักต่อถุง">
                  <div class="input-group input-group-sm summary-input shadow-sm">
                    <input form="<?php echo e($formId); ?>" type="number" name="weight_per_bag"
                      class="form-control text-end fw-semibold border-end-0" min="0" step="0.01"
                      value="<?php echo e(number_format($row['ws_weight_per_bag'], 2, '.', '')); ?>" placeholder="0.00">
                  </div>
                </td>
                <td data-label="น้ำหนักคำนวณ" class="text-end fw-semibold text-success-emphasis">
                  <?php echo number_format($row['ws_estimated_weight'], 2); ?> กก.
                </td>
                <td data-label="จัดการ" class="text-center">
                  <div class="d-inline-flex flex-column flex-sm-row gap-2">
                    <button form="<?php echo e($formId); ?>" type="submit"
                      class="btn btn-success btn-sm summary-save-btn d-inline-flex align-items-center gap-1 px-3 shadow-sm">
                      <i data-lucide="save" aria-hidden="true"></i>
                      <span>บันทึก</span>
                    </button>
                    <button type="button"
                      class="btn btn-outline-danger btn-sm summary-clear-btn d-inline-flex align-items-center gap-1 px-3"
                      data-summary-date="<?php echo e($row['summary_date']); ?>"
                      data-summary-label="<?php echo e(format_thai_date_short($row['summary_date'])); ?>"
                      onclick="clearSummary(this)">
                      <i data-lucide="trash-2" aria-hidden="true"></i>
                      <span>ลบ</span>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
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

  async function clearSummary(btn) {
    const summaryDate = btn.getAttribute('data-summary-date');
    const summaryLabel = btn.getAttribute('data-summary-label') || summaryDate;
    if (!summaryDate) return;
    if (!confirm('ล้างน้ำหนักประมาณต่อถุงของวันที่ ' + summaryLabel + ' ใช่หรือไม่?')) return;

    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>กำลังลบ';

    try {
      const res = await fetch('reset_wang_summary.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json; charset=utf-8'
        },
        body: JSON.stringify({
          summary_date: summaryDate
        })
      });
      const j = await res.json().catch(() => null);
      if (!res.ok || !j || !j.isOk) {
        throw new Error((j && j.message) ? j.message : 'Reset failed');
      }
      window.location.reload();
    } catch (err) {
      alert((err && err.message) ? err.message : 'ลบไม่สำเร็จ');
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
  </script>
</body>

</html>
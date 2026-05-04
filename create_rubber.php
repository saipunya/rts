<?php
require_once 'functions.php';
require_login();

$db = db();
$groups = [];
$stm = $db->prepare("SELECT DISTINCT mem_group FROM tbl_member WHERE mem_group IS NOT NULL AND mem_group <> '' ORDER BY mem_group ASC");
if ($stm) {
  $stm->execute();
  $res = $stm->get_result();
  while ($r = $res->fetch_assoc()) {
    $groupName = $r['mem_group'];
    $mstm = $db->prepare("SELECT mem_id, mem_fullname FROM tbl_member WHERE mem_group = ? ORDER BY mem_fullname ASC");
    if ($mstm) {
      $mstm->bind_param('s', $groupName);
      $mstm->execute();
      $mres = $mstm->get_result();
      $members = [];
      while ($m = $mres->fetch_assoc()) { $members[] = ['id' => (int)$m['mem_id'], 'name' => $m['mem_fullname']]; }
      $mstm->close();
    } else {
      $members = [];
    }
    $groups[] = ['id' => $groupName, 'name' => $groupName, 'members' => $members];
  }
  $stm->close();
}

$groups_js = ['groups' => $groups];

// load existing wang records to display in the table (optionally filter by lane)
$initial_records = [];
$selected_lane = isset($_GET['lane']) ? trim((string)$_GET['lane']) : '';
if ($selected_lane !== '') {
  $wstm = $db->prepare("SELECT wang_id, wang_date, wang_mid, wang_group, wang_name, wang_sack, wang_weight, wang_lan, wang_status FROM tbl_wangyang WHERE wang_lan = ? ORDER BY wang_savedate DESC LIMIT 500");
  if ($wstm) {
    $wstm->bind_param('s', $selected_lane);
    $wstm->execute();
    $wres = $wstm->get_result();
    while ($wr = $wres->fetch_assoc()) {
      $initial_records[] = [
        '__backendId' => 'db-' . (int)$wr['wang_id'],
        'member_id' => (int)$wr['wang_mid'],
        'farmer_name' => $wr['wang_name'],
        'group_name' => $wr['wang_group'],
        'lane' => $wr['wang_lan'],
        'bags' => (int)$wr['wang_sack'],
        'weight' => (float)$wr['wang_weight'],
        'date' => $wr['wang_date'],
        'status' => $wr['wang_status'] ?? ''
      ];
    }
    $wstm->close();
  }
} else {
  $wstm = $db->prepare("SELECT wang_id, wang_date, wang_mid, wang_group, wang_name, wang_sack, wang_weight, wang_lan, wang_status FROM tbl_wangyang ORDER BY wang_savedate DESC LIMIT 500");
  if ($wstm) {
    $wstm->execute();
    $wres = $wstm->get_result();
    while ($wr = $wres->fetch_assoc()) {
      $initial_records[] = [
        '__backendId' => 'db-' . (int)$wr['wang_id'],
        'member_id' => (int)$wr['wang_mid'],
        'farmer_name' => $wr['wang_name'],
        'group_name' => $wr['wang_group'],
        'lane' => $wr['wang_lan'],
        'bags' => (int)$wr['wang_sack'],
        'weight' => (float)$wr['wang_weight'],
        'date' => $wr['wang_date'],
        'status' => $wr['wang_status'] ?? ''
      ];
    }
    $wstm->close();
  }
}

$initial_records_js = json_encode($initial_records, JSON_UNESCAPED_UNICODE);
?>
<?php
$selected_lane = isset($_GET['lane']) ? trim((string)$_GET['lane']) : '';
$selected_lane_js = $selected_lane !== '' ? $selected_lane : '';
?>

<!doctype html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ระบบรวบรวมยางพารา</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9Oer+R4zR4p8hTVU4hZ5pG1BSjYyV27lyZzGEjjqF2U6M" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&amp;display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <style>
  body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Sarabun', system-ui, -apple-system, "Segoe UI", sans-serif;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 48%, #eff6ff 100%);
    color: #14532d;
  }

  *,
  *::before,
  *::after {
    box-sizing: border-box;
  }

  a {
    color: inherit;
    text-decoration: none;
  }

  .container.app-shell {
    width: min(100% - 2rem, 1160px);
    margin-left: auto;
    margin-right: auto;
  }

  .app-shell {
    max-width: 1160px;
  }

  .py-3 {
    padding-top: 1rem;
    padding-bottom: 1rem;
  }

  .py-4 {
    padding-top: 1.5rem;
    padding-bottom: 1.5rem;
  }

  .py-5 {
    padding-top: 3rem;
    padding-bottom: 3rem;
  }

  .p-3 {
    padding: 1rem;
  }

  .p-4 {
    padding: 1.5rem;
  }

  .px-3 {
    padding-left: 1rem;
    padding-right: 1rem;
  }

  .py-2 {
    padding-top: .5rem;
    padding-bottom: .5rem;
  }

  .mb-0 { margin-bottom: 0; }
  .mb-1 { margin-bottom: .25rem; }
  .mb-2 { margin-bottom: .5rem; }
  .mb-3 { margin-bottom: 1rem; }
  .mb-4 { margin-bottom: 1.5rem; }
  .mt-3 { margin-top: 1rem; }
  .me-1 { margin-right: .25rem; }

  .d-flex {
    display: flex;
  }

  .d-inline-flex {
    display: inline-flex;
  }

  .flex-column {
    flex-direction: column;
  }

  .align-items-center {
    align-items: center;
  }

  .justify-content-between {
    justify-content: space-between;
  }

  .justify-content-center {
    justify-content: center;
  }

  .gap-2 { gap: .5rem; }
  .gap-3 { gap: 1rem; }

  .flex-wrap {
    flex-wrap: wrap;
  }

  .flex-shrink-0 {
    flex-shrink: 0;
  }

  .flex-fill {
    flex: 1 1 auto;
  }

  .min-w-0 {
    min-width: 0;
  }

  .row {
    display: flex;
    flex-wrap: wrap;
    margin-left: -.5rem;
    margin-right: -.5rem;
  }

  .row > * {
    width: 100%;
    padding-left: .5rem;
    padding-right: .5rem;
  }

  .g-2,
  .g-3 {
    row-gap: 1rem;
  }

  .col-6 {
    flex: 0 0 auto;
    width: 50%;
  }

  .col-12 {
    flex: 0 0 auto;
    width: 100%;
  }

  .h3,
  .h4,
  .h5 {
    margin-top: 0;
    line-height: 1.25;
  }

  .h3 { font-size: clamp(1.55rem, 3vw, 2rem); }
  .h4 { font-size: 1.4rem; }
  .h5 { font-size: 1.15rem; }

  .fw-bold { font-weight: 700; }
  .fw-semibold { font-weight: 600; }
  .small { font-size: .9rem; }
  .text-center { text-align: center; }
  .text-muted { color: #64748b; }
  .text-success { color: #15803d; }
  .text-success-emphasis { color: #14532d; }
  .text-info { color: #0891b2; }
  .text-info-emphasis { color: #0e7490; }
  .text-primary { color: #2563eb; }
  .text-danger { color: #dc2626; }
  .bg-success-subtle { background: #dcfce7; }
  .bg-info-subtle { background: #cffafe; }
  .bg-primary-subtle { background: #dbeafe; }
  .bg-danger-subtle { background: #fee2e2; }
  .bg-light { background: #f8fafc; }
  .border { border: 1px solid currentColor; }
  .border-bottom { border-bottom: 1px solid #d1fae5; }
  .border-success-subtle { border-color: #bbf7d0 !important; }
  .overflow-hidden { overflow: hidden; }
  .w-100 { width: 100%; }
  .h-100 { height: 100%; }

  .app-header {
    position: sticky;
    top: 0;
    z-index: 1020;
    background: rgba(255, 255, 255, .9);
    border-bottom: 1px solid #bbf7d0;
    backdrop-filter: blur(10px);
  }

  .brand-mark,
  .stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .brand-mark {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: .9rem;
    background: #16a34a;
    color: #fff;
  }

  .hero-panel,
  .surface-card {
    border: 1px solid #bbf7d0;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, .86);
    box-shadow: 0 16px 42px rgba(20, 83, 45, .08);
  }

  .stat-icon {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: .9rem;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .3rem;
    min-height: 42px;
    padding: .55rem 1rem;
    border-radius: .75rem;
    border: 1px solid transparent;
    background: #fff;
    color: #14532d;
    font-weight: 700;
    cursor: pointer;
    line-height: 1.2;
  }

  .btn-sm {
    min-height: 36px;
    padding: .35rem .6rem;
  }

  .btn-success {
    background: #16a34a;
    border-color: #16a34a;
    color: #fff;
  }

  .btn-success:hover {
    background: #15803d;
    border-color: #15803d;
  }

  .btn-outline-success {
    border-color: #86efac;
    color: #166534;
  }

  .btn-outline-success:hover {
    background: #f0fdf4;
  }

  .btn-outline-secondary {
    border-color: #cbd5e1;
    color: #475569;
  }

  .btn-danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
  }

  .btn-outline-danger {
    border-color: #fecaca;
    color: #dc2626;
  }

  .rounded-pill {
    border-radius: 999px;
  }

  .rounded-circle {
    border-radius: 999px;
  }

  .badge {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    border-radius: 999px;
    font-weight: 700;
    line-height: 1.2;
  }

  #lane-badge {
    font-size: .72rem;
    padding: .18rem .55rem;
    line-height: 1.15;
  }

  .text-bg-danger {
    background: #dc2626;
    color: #fff;
  }

  .text-bg-success-subtle {
    background: #dcfce7;
    color: #166534;
  }

  .form-label {
    display: block;
    margin-bottom: .35rem;
  }

  .form-select,
  .form-control {
    display: block;
    width: 100%;
    min-height: 44px;
    padding: .55rem .75rem;
    border: 1px solid #bbf7d0;
    border-radius: .75rem;
    background: #fff;
    color: #14532d;
    font: inherit;
  }

  .form-select:focus,
  .form-control:focus {
    outline: none;
    border-color: #22c55e;
    box-shadow: 0 0 0 .2rem rgba(34, 197, 94, .14);
  }

  .alert {
    border-radius: .9rem;
    border: 1px solid #bbf7d0;
  }

  .alert-success {
    background: #f0fdf4;
    color: #166534;
  }

  .table-responsive {
    width: 100%;
    overflow-x: auto;
  }

  .table {
    width: 100%;
    border-collapse: collapse;
  }

  .table th {
    color: #166534;
    font-weight: 700;
    white-space: nowrap;
    background: #dcfce7;
    text-align: left;
    padding: .8rem 1rem;
  }

  .table td {
    vertical-align: middle;
    padding: .8rem 1rem;
    border-top: 1px solid #ecfdf5;
  }

  .table-hover tbody tr:hover {
    background: #f0fdf4;
  }

  table.single-lane th:nth-child(2),
  table.single-lane th:nth-child(3),
  table.single-lane td:nth-child(2),
  table.single-lane td:nth-child(3) {
    display: none;
  }

  .app-modal {
    position: fixed;
    inset: 0;
    z-index: 1050;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, .42);
    backdrop-filter: blur(5px);
  }

  .hidden {
    display: none !important;
  }

  .flex {
    display: flex !important;
  }

  .modal-panel {
    width: min(100%, 520px);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 1.25rem;
    background: #fff;
    box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
  }

  .fade-in {
    animation: fadeIn .22s ease-out;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @media (max-width: 640px) {
    .header-actions {
      display: grid !important;
      grid-template-columns: 1fr 1fr;
      width: 100%;
    }

    .header-actions .btn {
      justify-content: center;
      min-height: 44px;
    }

    #data-table-wrap thead {
      display: none;
    }

    #data-table tr {
      display: block;
      margin-bottom: .8rem;
      border: 1px solid #d1fae5;
      border-radius: 1rem;
      background: #fff;
      box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
      padding: .75rem .9rem;
    }

    #data-table td {
      display: flex;
      justify-content: space-between;
      gap: .75rem;
      padding: .4rem 0;
      border: 0;
      font-size: .92rem;
    }

    #data-table td::before {
      content: attr(data-label);
      color: #15803d;
      font-weight: 700;
      flex: 0 0 auto;
    }
  }

  @media (min-width: 576px) {
    .flex-sm-row { flex-direction: row; }
    .align-items-sm-center { align-items: center; }
    .d-sm-none { display: none !important; }
    .p-sm-4 { padding: 1.5rem; }
  }

  @media (min-width: 768px) {
    .flex-md-row { flex-direction: row; }
    .align-items-md-center { align-items: center; }
    .col-md-4 {
      flex: 0 0 auto;
      width: 33.333333%;
    }
  }
  </style>
</head>

<body>
  <div id="app">
    <header class="app-header py-3">
      <div class="container app-shell">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
          <div class="d-flex align-items-center gap-3 min-w-0">
            <span class="brand-mark">
              <i data-lucide="archive" aria-hidden="true"></i>
            </span>
            <div class="min-w-0">
              <h1 id="app-title" class="h5 fw-bold text-success-emphasis mb-1">ระบบรวบรวมยางพารา</h1>
              <div class="d-flex flex-wrap align-items-center gap-2">
                <p id="app-subtitle" class="small text-success mb-0">บันทึกข้อมูลวันวางยางพารา</p>
                <span id="lane-badge" class="badge text-bg-danger rounded-pill" style="display:none;">ลาน -</span>
              </div>
            </div>
          </div>
          <div class="header-actions d-flex gap-2 flex-shrink-0">
            <a href="wang_main.php" class="btn btn-outline-success rounded-pill">
              <i data-lucide="arrow-left" class="me-1" aria-hidden="true"></i>กลับ
            </a>
            <button id="btn-add" type="button" class="btn btn-success rounded-pill">
              <i data-lucide="plus" class="me-1" aria-hidden="true"></i>รายการ
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="container app-shell py-4">
      <section class="hero-panel p-3 p-sm-4 mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <h2 class="h3 fw-bold text-success-emphasis mb-2">รายการวางยาง</h2>
            <p class="mb-0 text-success">เพิ่มและติดตามข้อมูลวางยางประจำวัน แยกตามกลุ่ม เกษตรกร และลานรับยาง</p>
          </div>
          <span class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis px-3 py-2">
            <i data-lucide="calendar-days" class="me-1" aria-hidden="true"></i>วันนี้
          </span>
        </div>
      </section>

      <section class="row g-3 mb-4">
        <div class="col-12 col-md-4">
          <div class="surface-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
              <span class="stat-icon bg-success-subtle text-success">
                <i data-lucide="archive" aria-hidden="true"></i>
              </span>
              <div>
                <div class="small text-success">รายการทั้งหมด</div>
                <div id="stat-total" class="h4 fw-bold mb-0 text-success-emphasis">0</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="surface-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
              <span class="stat-icon bg-info-subtle text-info">
                <i data-lucide="scale" aria-hidden="true"></i>
              </span>
              <div>
                <div class="small text-info-emphasis">น้ำหนักรวม (กก.)</div>
                <div id="stat-weight" class="h4 fw-bold mb-0 text-success-emphasis">0</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="surface-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
              <span class="stat-icon bg-primary-subtle text-primary">
                <i data-lucide="users" aria-hidden="true"></i>
              </span>
              <div>
                <div class="small text-primary">เกษตรกร</div>
                <div id="stat-farmers" class="h4 fw-bold mb-0 text-success-emphasis">0</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="surface-card overflow-hidden mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 p-3 border-bottom border-success-subtle">
          <div>
            <h2 class="h5 fw-bold mb-1 text-success-emphasis">ข้อมูลวางยาง</h2>
            <div class="small text-muted">แสดงรายการล่าสุดสูงสุด 500 รายการ</div>
          </div>
          <button id="btn-add-top" type="button" class="btn btn-outline-success rounded-pill d-inline-flex d-sm-none">
            <i data-lucide="plus" class="me-1" aria-hidden="true"></i>เพิ่มรายการ
          </button>
        </div>
        <div class="table-responsive" id="data-table-wrap">
          <table class="table table-hover align-middle mb-0 <?php echo ($selected_lane !== '' ? 'single-lane' : ''); ?>">
            <thead class="table-success">
              <tr>
                <th>ชื่อเกษตรกร</th>
                <th>กลุ่ม</th>
                <th>ลาน</th>
                <th>กระสอบ</th>
                <th>น้ำหนัก (กก.)</th>
                <th>วันที่</th>
                <th class="text-center">จัดการ</th>
              </tr>
            </thead>
            <tbody id="data-table"></tbody>
          </table>
        </div>
        <div id="empty-state" class="py-5 text-center text-success">
          <i data-lucide="inbox" class="mb-3" style="width:48px;height:48px;" aria-hidden="true"></i>
          <p class="mb-0">ยังไม่มีรายการ กดปุ่ม "เพิ่มรายการ" เพื่อเริ่มต้น</p>
        </div>
      </section>
    </main>

    <div id="modal" class="app-modal hidden">
      <div class="modal-panel fade-in">
        <div class="d-flex align-items-center justify-content-between p-4 border-bottom border-success-subtle">
          <h2 class="h5 fw-bold text-success-emphasis mb-0">เพิ่มรายการรวบรวมยาง</h2>
          <button id="btn-close-modal" type="button" class="btn btn-sm btn-outline-secondary rounded-circle" aria-label="ปิด">
            <i data-lucide="x" aria-hidden="true"></i>
          </button>
        </div>
        <form id="form-add" class="p-4">
          <div class="mb-3">
            <label for="f-group" class="form-label fw-semibold text-success">กลุ่ม</label>
            <select id="f-group" required class="form-select">
              <option value="">เลือกกลุ่ม</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="f-name" class="form-label fw-semibold text-success">ชื่อเกษตรกร</label>
            <select id="f-name" required disabled class="form-select">
              <option value="">เลือกกลุ่มก่อน</option>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label for="f-lane" class="form-label fw-semibold text-success">ลาน</label>
              <select id="f-lane" required class="form-select">
                <option value="">เลือกลาน</option>
                <option value="1">ลาน 1</option>
                <option value="2">ลาน 2</option>
                <option value="3">ลาน 3</option>
                <option value="4">ลาน 4</option>
              </select>
            </div>
            <div class="col-6">
              <label for="f-bags" class="form-label fw-semibold text-success">จำนวนกระสอบ</label>
              <input id="f-bags" type="number" step="1" min="1" required class="form-control">
            </div>
          </div>
          <div class="mt-3">
            <label for="f-weight" class="form-label fw-semibold text-success">น้ำหนัก (กก.)</label>
            <input id="f-weight" type="number" step="0.01" min="0" required class="form-control">
          </div>
          <div id="date-display" class="alert alert-success py-2 mt-3 mb-3">
            วันที่: <span id="current-date-display"></span>
          </div>
          <button type="submit" id="btn-submit" class="btn btn-success w-100 rounded-pill">บันทึก</button>
        </form>
      </div>
    </div>

    <div id="delete-confirm" class="app-modal hidden">
      <div class="modal-panel fade-in text-center p-4" style="max-width:390px;">
        <div class="stat-icon bg-danger-subtle text-danger mx-auto mb-3">
          <i data-lucide="trash-2" aria-hidden="true"></i>
        </div>
        <p class="h5 fw-bold text-success-emphasis mb-1">ยืนยันการลบ?</p>
        <p class="text-success mb-4">รายการนี้จะถูกลบอย่างถาวร</p>
        <div class="d-flex gap-2">
          <button id="btn-cancel-del" type="button" class="btn btn-outline-success flex-fill">ยกเลิก</button>
          <button id="btn-confirm-del" type="button" class="btn btn-danger flex-fill">ลบ</button>
        </div>
      </div>
    </div>
  </div>
  <script>
  // Groups and members loaded from database
  const referenceData = <?php echo json_encode($groups_js, JSON_UNESCAPED_UNICODE); ?>;

  // State (initialized from server data)
  let records = <?php echo $initial_records_js ?? '[]'; ?>;
  const defaultLane = '<?php echo htmlspecialchars($selected_lane_js, ENT_QUOTES); ?>';

  // show current lane badge
  (function() {
    const badge = document.getElementById('lane-badge');
    if (!badge) return;
    if (defaultLane) {
      badge.textContent = 'ลาน ' + defaultLane;
      badge.style.display = 'inline-block';
    } else {
      badge.textContent = 'ทุกลาน';
      badge.style.display = 'inline-block';
    }
  })();
  let deleteTarget = null;

  const defaultConfig = {
    app_title: 'ระบบรวบรวมยางพารา',
    subtitle_text: 'บันทึกและติดตามการรวบรวมยางพารา',
    background_color: '#f0fdf4',
    surface_color: '#ffffff',
    text_color: '#14532d',
    primary_action_color: '#22c55e',
    secondary_action_color: '#065f46'
  };

  // Element SDK
  if (window.elementSdk && typeof window.elementSdk.init === 'function') {
    window.elementSdk.init({
      defaultConfig,
      onConfigChange: async (config) => {
        document.getElementById('app-title').textContent = config.app_title || defaultConfig.app_title;
        document.getElementById('app-subtitle').textContent = config.subtitle_text || defaultConfig.subtitle_text;
        const app = document.getElementById('app');
        app.style.background =
          `linear-gradient(135deg, ${config.background_color || defaultConfig.background_color}, ${config.surface_color || defaultConfig.surface_color})`;
      },
      mapToCapabilities: (config) => ({
        recolorables: [{
            get: () => config.background_color || defaultConfig.background_color,
            set: (v) => {
              config.background_color = v;
              window.elementSdk.setConfig({
                background_color: v
              });
            }
          },
          {
            get: () => config.surface_color || defaultConfig.surface_color,
            set: (v) => {
              config.surface_color = v;
              window.elementSdk.setConfig({
                surface_color: v
              });
            }
          },
          {
            get: () => config.text_color || defaultConfig.text_color,
            set: (v) => {
              config.text_color = v;
              window.elementSdk.setConfig({
                text_color: v
              });
            }
          },
          {
            get: () => config.primary_action_color || defaultConfig.primary_action_color,
            set: (v) => {
              config.primary_action_color = v;
              window.elementSdk.setConfig({
                primary_action_color: v
              });
            }
          },
          {
            get: () => config.secondary_action_color || defaultConfig.secondary_action_color,
            set: (v) => {
              config.secondary_action_color = v;
              window.elementSdk.setConfig({
                secondary_action_color: v
              });
            }
          }
        ],
        borderables: [],
        fontEditable: {
          get: () => config.font_family || 'Sarabun',
          set: (v) => {
            config.font_family = v;
            window.elementSdk.setConfig({
              font_family: v
            });
          }
        },
        fontSizeable: {
          get: () => config.font_size || 14,
          set: (v) => {
            config.font_size = v;
            window.elementSdk.setConfig({
              font_size: v
            });
          }
        }
      }),
      mapToEditPanelValues: (config) => new Map([
        ['app_title', config.app_title || defaultConfig.app_title],
        ['subtitle_text', config.subtitle_text || defaultConfig.subtitle_text]
      ])
    });
  } else {
    document.getElementById('app-title').textContent = defaultConfig.app_title;
    document.getElementById('app-subtitle').textContent = defaultConfig.subtitle_text;
  }

  // Data SDK
  const dataHandler = {
    onDataChanged(data) {
      records = data;
      renderTable();
      updateStats();
    }
  };

  if (window.dataSdk && typeof window.dataSdk.init === 'function') {
    (async () => {
      const r = await window.dataSdk.init(dataHandler);
      if (!r.isOk) console.error('Data SDK init failed');
    })();
  } else {
    console.warn('dataSdk not available — using local state only');
  }

  // Fallback helpers when dataSdk is not present
  async function createRecord(payload) {
    // Try server-side save first
    try {
      const res = await fetch('save_wang.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json; charset=utf-8'
        },
        body: JSON.stringify(payload)
      });
      if (res.ok) {
        const j = await res.json();
        if (j && j.isOk) {
          const id = 'db-' + j.id;
          const rec = Object.assign({
            __backendId: id
          }, payload);
          records.push(rec);
          dataHandler.onDataChanged(records);
          return {
            isOk: true,
            data: rec
          };
        }
      }
    } catch (e) {
      // ignore and fallback
    }

    // Fallback to dataSdk if available
    if (window.dataSdk && typeof window.dataSdk.create === 'function') {
      return await window.dataSdk.create(payload);
    }

    // Local fallback
    const id = 'local-' + Date.now() + Math.floor(Math.random() * 1000);
    const rec = Object.assign({
      __backendId: id
    }, payload);
    records.push(rec);
    dataHandler.onDataChanged(records);
    return {
      isOk: true,
      data: rec
    };
  }

  async function deleteRecord(target) {
    if (window.dataSdk && typeof window.dataSdk.delete === 'function') {
      return await window.dataSdk.delete(target);
    }
    records = records.filter(r => r.__backendId !== target.__backendId);
    dataHandler.onDataChanged(records);
    return {
      isOk: true
    };
  }

  function updateStats() {
    document.getElementById('stat-total').textContent = records.length;
    const farmers = new Set(records.map(r => r.farmer_name)).size;
    document.getElementById('stat-farmers').textContent = farmers;
    const totalWeight = records.reduce((s, r) => s + (parseFloat(r.weight) || 0), 0);
    document.getElementById('stat-weight').textContent = totalWeight.toFixed(2);
  }

  function renderTable() {
    const tbody = document.getElementById('data-table');
    const empty = document.getElementById('empty-state');
    empty.style.display = records.length === 0 ? 'block' : 'none';

    const existingRows = new Map([...tbody.children].map(el => [el.dataset.id, el]));
    const ids = new Set(records.map(r => r.__backendId));

    records.forEach(rec => {
      if (existingRows.has(rec.__backendId)) {
        const row = existingRows.get(rec.__backendId);
        row.children[0].textContent = rec.farmer_name;
        row.children[1].textContent = rec.group_name;
        row.children[2].textContent = rec.lane;
        row.children[3].textContent = rec.bags;
        row.children[4].textContent = (parseFloat(rec.weight) || 0) + ' กก.';
        row.children[5].textContent = formatThaiDate(rec.date);
        existingRows.delete(rec.__backendId);
      } else {
        const row = document.createElement('tr');
        row.dataset.id = rec.__backendId;
        row.className = '';
        row.innerHTML = `
          <td data-label="ชื่อเกษตรกร" class="fw-semibold text-success-emphasis">${esc(rec.farmer_name)}</td>
          <td data-label="กลุ่ม" class="text-success">${esc(rec.group_name)}</td>
          <td data-label="ลาน"><span class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis">ลาน ${esc(rec.lane)}</span></td>
          <td data-label="กระสอบ">${esc(String(rec.bags))} กระสอบ</td>
          <td data-label="น้ำหนัก">${esc(String(rec.weight || 0))} กก.</td>
          <td data-label="วันที่" class="text-success">${formatThaiDate(rec.date)}</td>
          <td data-label="จัดการ" class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="confirmDelete('${rec.__backendId}')"><i data-lucide="trash-2" aria-hidden="true"></i></button></td>
        `;
        tbody.appendChild(row);
        if (window.lucide && lucide.createIcons) lucide.createIcons();
      }
    });

    existingRows.forEach(el => el.remove());
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function formatThaiDate(dateStr) {
    const date = new Date(dateStr);
    const monthsTh = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม',
      'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    const day = date.getDate();
    const month = monthsTh[date.getMonth()];
    const year = date.getFullYear() + 543;
    return `${day} ${month} ${year}`;
  }

  function getTodayDateString() {
    const today = new Date();
    return today.toISOString().split('T')[0];
  }

  // Modal
  const modal = document.getElementById('modal');
  function openAddModal() {
    document.getElementById('current-date-display').textContent = formatThaiDate(getTodayDateString());
    // set default lane if provided from main page
    const laneEl = document.getElementById('f-lane');
    // if defaultLane provided, lock the lane select to prevent changes
    if (defaultLane) {
      if (laneEl) {
        laneEl.value = defaultLane;
        laneEl.disabled = true;
        laneEl.classList.add('bg-light');
        // ensure hidden input exists for graceful form submission if needed
        let hid = document.getElementById('f-lane-hidden');
        if (!hid) {
          hid = document.createElement('input');
          hid.type = 'hidden';
          hid.id = 'f-lane-hidden';
          hid.name = 'f-lane-hidden';
          document.getElementById('form-add').appendChild(hid);
        }
        hid.value = defaultLane;
      }
    } else {
      if (laneEl) {
        laneEl.disabled = false;
        laneEl.classList.remove('bg-light');
      }
      const hid = document.getElementById('f-lane-hidden');
      if (hid) hid.remove();
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
  document.getElementById('btn-add').onclick = openAddModal;
  const btnAddTop = document.getElementById('btn-add-top');
  if (btnAddTop) btnAddTop.onclick = openAddModal;
  document.getElementById('btn-close-modal').onclick = () => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  };

  // Populate group dropdown
  const groupSelect = document.getElementById('f-group');
  referenceData.groups.forEach(g => {
    const opt = document.createElement('option');
    opt.value = g.id;
    opt.textContent = g.name;
    groupSelect.appendChild(opt);
  });

  // Group selection changes farmer dropdown
  const farmerSelect = document.getElementById('f-name');
  groupSelect.addEventListener('change', () => {
    farmerSelect.innerHTML = '<option value="">เลือกเกษตรกร</option>';
    if (!groupSelect.value) {
      farmerSelect.disabled = true;
      return;
    }
    const group = referenceData.groups.find(g => g.id === groupSelect.value);
    if (group) {
      group.members.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.name;
        farmerSelect.appendChild(opt);
      });
      farmerSelect.disabled = false;
    }
  });

  document.getElementById('form-add').onsubmit = async (e) => {
    e.preventDefault();
    if (records.length >= 999) {
      document.getElementById('btn-submit').textContent = 'ถึงขีดจำกัดแล้ว (999)';
      return;
    }
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.textContent = 'กำลังบันทึก...';
    const groupId = document.getElementById('f-group').value;
    const group = referenceData.groups.find(g => g.id === groupId);
    const selectedMemberId = parseInt(document.getElementById('f-name').value) || null;
    const selectedMemberName = farmerSelect.options[farmerSelect.selectedIndex] ? farmerSelect.options[farmerSelect
      .selectedIndex].text : '';
    const result = await createRecord({
      member_id: selectedMemberId,
      farmer_name: selectedMemberName,
      group_name: group ? group.name : '',
      lane: document.getElementById('f-lane').value,
      bags: parseInt(document.getElementById('f-bags').value),
      weight: parseFloat(document.getElementById('f-weight').value) || 0,
      date: getTodayDateString()
    });
    btn.disabled = false;
    btn.textContent = 'บันทึก';
    if (result.isOk) {
      e.target.reset();
      groupSelect.value = '';
      farmerSelect.value = '';
      farmerSelect.disabled = true;
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
  };

  // Delete
  const delModal = document.getElementById('delete-confirm');
  window.confirmDelete = (id) => {
    deleteTarget = records.find(r => r.__backendId === id);
    delModal.classList.remove('hidden');
    delModal.classList.add('flex');
  };
  document.getElementById('btn-cancel-del').onclick = () => {
    delModal.classList.add('hidden');
    delModal.classList.remove('flex');
  };
  document.getElementById('btn-confirm-del').onclick = async () => {
    if (!deleteTarget) return;
    const btn = document.getElementById('btn-confirm-del');
    btn.disabled = true;
    btn.textContent = 'กำลังลบ...';
    await deleteRecord(deleteTarget);
    btn.disabled = false;
    btn.textContent = 'ลบ';
    delModal.classList.add('hidden');
    delModal.classList.remove('flex');
    deleteTarget = null;
  };

  // Render initial server-provided records
  try {
    renderTable();
    updateStats();
    if (window.lucide && lucide.createIcons) lucide.createIcons();
  } catch (e) {
    /* ignore if functions not ready */ }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
</body>

</html>

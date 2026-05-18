<?php
require_once 'functions.php';
require_login();

$db = db();
ensure_wangyang_form_columns($db);

function ensure_wangyang_form_columns(mysqli $db): void {
  $db->query("
    CREATE TABLE IF NOT EXISTS tbl_wangyang (
      wang_id INT(11) NOT NULL AUTO_INCREMENT,
      wang_date DATE NOT NULL,
      wang_mid INT(11) NOT NULL DEFAULT 0,
      wang_group VARCHAR(255) NOT NULL DEFAULT '',
      wang_number VARCHAR(255) NOT NULL DEFAULT '',
      wang_name VARCHAR(255) NOT NULL DEFAULT '',
      wang_class VARCHAR(255) NOT NULL DEFAULT '',
      wang_sack DECIMAL(18,2) NOT NULL DEFAULT 0.00,
      wang_weight DECIMAL(18,2) NOT NULL DEFAULT 0,
      wang_lan VARCHAR(255) NOT NULL DEFAULT '',
      wang_note TEXT NULL,
      wang_status VARCHAR(50) NOT NULL DEFAULT '',
      wang_saveby VARCHAR(255) NOT NULL DEFAULT '',
      wang_savedate DATETIME NOT NULL,
      PRIMARY KEY (wang_id),
      KEY idx_wang_date (wang_date),
      KEY idx_wang_lan (wang_lan),
      KEY idx_wang_savedate (wang_savedate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");

  $dbNameRes = $db->query('SELECT DATABASE() AS dbname');
  $dbNameRow = $dbNameRes ? $dbNameRes->fetch_assoc() : null;
  $dbName = (string)($dbNameRow['dbname'] ?? '');
  if ($dbNameRes) {
    $dbNameRes->free();
  }
  if ($dbName === '') return;

  $columns = [
    'wang_number' => "ALTER TABLE tbl_wangyang ADD COLUMN wang_number VARCHAR(255) NOT NULL DEFAULT '' AFTER wang_group",
    'wang_class' => "ALTER TABLE tbl_wangyang ADD COLUMN wang_class VARCHAR(255) NOT NULL DEFAULT '' AFTER wang_name",
    'wang_note' => "ALTER TABLE tbl_wangyang ADD COLUMN wang_note TEXT NULL AFTER wang_lan",
  ];
  $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tbl_wangyang' AND COLUMN_NAME = ?");
  if ($stmt) {
    foreach ($columns as $column => $sql) {
      $stmt->bind_param('ss', $dbName, $column);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      if ((int)($row['cnt'] ?? 0) === 0) {
        $db->query($sql);
      }
    }
    $stmt->close();
  }
  $db->query("ALTER TABLE tbl_wangyang MODIFY COLUMN wang_sack DECIMAL(18,2) NOT NULL DEFAULT 0.00");
}

// load existing wang records to display in the table (optionally filter by lane)
$initial_records = [];
$selected_lane = isset($_GET['lane']) ? trim((string)$_GET['lane']) : '';
if ($selected_lane !== '') {
  $wstm = $db->prepare("SELECT w.wang_id, w.wang_date, w.wang_mid, COALESCE(NULLIF(w.wang_group, ''), m.mem_group, '') AS wang_group, COALESCE(NULLIF(w.wang_number, ''), m.mem_number, '') AS wang_number, w.wang_name, COALESCE(NULLIF(w.wang_class, ''), m.mem_class, '') AS wang_class, w.wang_sack, w.wang_lan, w.wang_note, w.wang_status FROM tbl_wangyang w LEFT JOIN tbl_member m ON w.wang_mid = m.mem_id WHERE w.wang_lan = ? ORDER BY w.wang_savedate DESC LIMIT 500");
  if ($wstm) {
    $wstm->bind_param('s', $selected_lane);
    $wstm->execute();
    $wres = $wstm->get_result();
    while ($wr = $wres->fetch_assoc()) {
      $initial_records[] = [
        '__backendId' => 'db-' . (int)$wr['wang_id'],
        'member_id' => (int)$wr['wang_mid'],
        'member_number' => $wr['wang_number'],
        'member_class' => $wr['wang_class'],
        'farmer_name' => $wr['wang_name'],
        'group_name' => $wr['wang_group'],
        'lane' => $wr['wang_lan'],
        'bags' => (float)$wr['wang_sack'],
        'note' => $wr['wang_note'] ?? '',
        'date' => $wr['wang_date'],
        'status' => $wr['wang_status'] ?? ''
      ];
    }
    $wstm->close();
  }
} else {
  $wstm = $db->prepare("SELECT w.wang_id, w.wang_date, w.wang_mid, COALESCE(NULLIF(w.wang_group, ''), m.mem_group, '') AS wang_group, COALESCE(NULLIF(w.wang_number, ''), m.mem_number, '') AS wang_number, w.wang_name, COALESCE(NULLIF(w.wang_class, ''), m.mem_class, '') AS wang_class, w.wang_sack, w.wang_lan, w.wang_note, w.wang_status FROM tbl_wangyang w LEFT JOIN tbl_member m ON w.wang_mid = m.mem_id ORDER BY w.wang_savedate DESC LIMIT 500");
  if ($wstm) {
    $wstm->execute();
    $wres = $wstm->get_result();
    while ($wr = $wres->fetch_assoc()) {
      $initial_records[] = [
        '__backendId' => 'db-' . (int)$wr['wang_id'],
        'member_id' => (int)$wr['wang_mid'],
        'member_number' => $wr['wang_number'],
        'member_class' => $wr['wang_class'],
        'farmer_name' => $wr['wang_name'],
        'group_name' => $wr['wang_group'],
        'lane' => $wr['wang_lan'],
        'bags' => (float)$wr['wang_sack'],
        'note' => $wr['wang_note'] ?? '',
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
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>บันทึกวางยาง - รายการ</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&amp;display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <style>
  *,
  *::before,
  *::after {
    box-sizing: border-box
  }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Sarabun', system-ui, -apple-system, "Segoe UI", sans-serif;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 48%, #eff6ff 100%);
    color: #14532d;
    -webkit-tap-highlight-color: transparent;
  }

  a {
    color: inherit;
    text-decoration: none
  }

  .container {
    width: 100%;
    padding-right: .75rem;
    padding-left: .75rem;
    margin: 0 auto
  }

  /* ── Header ── */
  .app-header {
    position: sticky;
    top: 0;
    z-index: 1020;
    background: rgba(255, 255, 255, .9);
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
    flex-shrink: 0
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
  }

  .brand-icon i {
    width: 1.2rem;
    height: 1.2rem
  }

  .brand-title {
    font-size: .95rem;
    font-weight: 700;
    color: #14532d;
    line-height: 1.2
  }

  .brand-subtitle {
    display: none;
    font-size: .78rem;
    color: #15803d
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: .25rem
  }

  .header-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .25rem;
    min-height: 2.25rem;
    padding: .35rem .5rem;
    border-radius: .65rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    font-size: .78rem;
    font-weight: 600;
    white-space: nowrap;
  }

  .header-actions .btn:active {
    background: #f1f5f9
  }

  .header-actions .btn span {
    display: none
  }

  .header-actions .btn-primary {
    border-color: #86efac;
    color: #16a34a
  }

  .header-actions .btn-add-icon {
    border-color: #86efac;
    color: #16a34a;
    background: #f0fdf4
  }

  /* ── Top bar (lane badge + subtitle) ── */
  .top-bar {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .65rem;
    padding-top: .1rem;
  }

  .page-sub {
    font-size: .82rem;
    color: #15803d;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap
  }

  .lane-pill {
    background: #dc2626;
    color: #fff;
    border-radius: 999px;
    padding: .15rem .55rem;
    font-size: .72rem;
    font-weight: 600;
    white-space: nowrap;
  }

  /* ── Stats Strip ── */
  .stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .5rem;
    margin-bottom: .75rem;
  }

  .stat-cell {
    padding: .5rem .35rem;
    border-radius: .75rem;
    background: rgba(255, 255, 255, .85);
    border: 1px solid #d1fae5;
    text-align: center;
    min-height: 3rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  .stat-num {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f3d23;
    line-height: 1.1
  }

  .stat-lbl {
    font-size: .65rem;
    color: #64748b;
    font-weight: 600
  }

  /* ── Search + Add ── */
  .toolbar {
    display: flex;
    gap: .5rem;
    margin-bottom: .75rem;
    align-items: stretch;
  }

  .toolbar .srch {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
  }

  .toolbar .srch i {
    position: absolute;
    left: .75rem;
    width: 1rem;
    height: 1rem;
    color: #94a3b8;
    pointer-events: none;
  }

  .toolbar .srch input {
    width: 100%;
    min-height: 44px;
    padding: .5rem 2.5rem .5rem 2.4rem;
    border: 1px solid #bbf7d0;
    border-radius: .75rem;
    background: #fff;
    color: #14532d;
    font: inherit;
    font-size: .92rem;
  }

  .toolbar .srch input:focus {
    outline: none;
    border-color: #22c55e;
    box-shadow: 0 0 0 .2rem rgba(34, 197, 94, .14)
  }

  .toolbar .srch .cnt {
    position: absolute;
    right: .65rem;
    font-size: .7rem;
    color: #94a3b8;
    pointer-events: none;
  }

  .toolbar .add-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    min-height: 44px;
    padding: .5rem .8rem;
    border: none;
    border-radius: .75rem;
    background: #16a34a;
    color: #fff;
    font-weight: 700;
    font-size: .92rem;
    white-space: nowrap;
  }

  .toolbar .add-btn:active {
    background: #15803d
  }

  .toolbar .add-btn i {
    width: 1.15rem;
    height: 1.15rem
  }

  .toolbar .add-btn span {
    display: none
  }

  .toolbar .clr-btn {
    display: none;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    width: 44px;
    border-radius: .75rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #64748b;
  }

  .toolbar .clr-btn:active {
    background: #f1f5f9
  }

  .toolbar.has-text .clr-btn {
    display: inline-flex
  }

  /* ── Surface card ── */
  .card-box {
    border: 1px solid #bbf7d0;
    border-radius: 1.15rem;
    background: rgba(255, 255, 255, .86);
    box-shadow: 0 12px 30px rgba(20, 83, 45, .06);
    overflow: hidden;
  }

  /* ── Table (scrollable on mobile) ── */
  .tbl-wrap {
    display: block;
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch
  }

  .tbl-wrap table {
    width: 100%;
    border-collapse: collapse;
    min-width: 500px
  }

  .tbl-wrap th {
    color: #166534;
    font-weight: 700;
    white-space: nowrap;
    background: #dcfce7;
    text-align: left;
    padding: .5rem .6rem;
    font-size: .78rem;
  }

  .tbl-wrap td {
    vertical-align: middle;
    padding: .5rem .6rem;
    border-top: 1px solid #ecfdf5;
    font-size: .82rem;
  }

  /* Keep the group column visible; only hide lane when a lane is preselected */
  .single-lane th:nth-child(3),
  .single-lane td:nth-child(3) {
    display: none
  }

  #data-table td::before {
    display: none
  }

  .record-actions {
    display: inline-flex;
    gap: .25rem;
    white-space: nowrap
  }

  .record-actions .btn {
    min-height: 30px;
    padding: .2rem .45rem;
    border-radius: .5rem;
    border: 1px solid #d1fae5;
    background: #fafffa;
    color: #166534;
    font-size: .72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .15rem;
  }

  .record-actions .btn:active {
    background: #dcfce7
  }

  .record-actions .btn-danger {
    border-color: #fecaca;
    color: #dc2626
  }

  .record-actions .btn-danger:active {
    background: #fee2e2
  }

  .record-actions .btn i {
    width: .62rem;
    height: .62rem
  }

  .empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: #15803d
  }

  .empty-state i {
    width: 2.5rem;
    height: 2.5rem;
    margin-bottom: .65rem;
    opacity: .4
  }

  .empty-state p {
    margin: 0;
    font-size: .9rem
  }

  /* ── Modals ── */
  .app-modal {
    position: fixed;
    inset: 0;
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: .75rem;
    background: rgba(15, 23, 42, .42);
    backdrop-filter: blur(5px);
  }

  .hidden {
    display: none !important
  }

  .modal-panel {
    width: 100%;
    max-width: 480px;
    max-height: 92vh;
    overflow-y: auto;
    border-radius: 1.1rem;
    background: #fff;
    box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
  }

  .modal-hd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid #d1fae5;
  }

  .modal-hd h2 {
    font-size: 1rem;
    font-weight: 700;
    color: #0f3d23;
    margin: 0
  }

  .modal-x {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
  }

  .modal-x:active {
    background: #f1f5f9
  }

  .modal-bd {
    padding: 1.15rem
  }

  .fld {
    margin-bottom: .85rem
  }

  .fld label {
    display: block;
    margin-bottom: .25rem;
    font-size: .82rem;
    font-weight: 600;
    color: #15803d
  }

  .fld select,
  .fld input[type="number"],
  .fld input[type="text"],
  .fld textarea {
    display: block;
    width: 100%;
    min-height: 44px;
    padding: .55rem .75rem;
    border: 1px solid #bbf7d0;
    border-radius: .75rem;
    background: #fff;
    color: #14532d;
    font: inherit;
    font-size: .92rem;
  }

  .fld textarea {
    min-height: 76px;
    resize: vertical
  }

  .fld select:focus,
  .fld input:focus {
    outline: none;
    border-color: #22c55e;
    box-shadow: 0 0 0 .2rem rgba(34, 197, 94, .14);
  }

  .row2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .75rem
  }

  .member-results {
    list-style: none;
    margin: .35rem 0 0;
    padding: 0;
    border: 1px solid #bbf7d0;
    border-radius: .75rem;
    overflow: hidden;
    background: #fff;
    max-height: 230px;
    overflow-y: auto;
  }

  .member-results li {
    padding: .55rem .75rem;
    border-top: 1px solid #ecfdf5;
    cursor: pointer;
    color: #14532d;
  }

  .member-results li:first-child {
    border-top: 0
  }

  .member-results li:active,
  .member-results li:hover {
    background: #f0fdf4
  }

  .member-results strong {
    display: block;
    font-size: .9rem;
    font-weight: 700
  }

  .member-results .small {
    font-size: .75rem;
    color: #64748b;
    margin-top: .1rem
  }

  .member-results[hidden],
  .member-summary[hidden] {
    display: none !important
  }

  .member-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    padding: .55rem .65rem;
    margin-top: .45rem;
    border-radius: .75rem;
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #166534;
    font-size: .78rem;
  }

  .member-summary-text {
    min-width: 0
  }

  .member-summary .tag {
    font-weight: 700;
    color: #14532d
  }

  .member-summary button {
    flex-shrink: 0;
    border: 1px solid #fecaca;
    background: #fff;
    color: #dc2626;
    min-height: 32px;
    padding: .2rem .55rem;
    border-radius: 999px;
    font: inherit;
    font-size: .75rem;
    font-weight: 700;
  }

  .btn-go {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    width: 100%;
    min-height: 48px;
    margin-top: .25rem;
    padding: .65rem;
    border: none;
    border-radius: 999px;
    background: #16a34a;
    color: #fff;
    font-weight: 700;
    font-size: 1rem;
  }

  .btn-go:active {
    background: #15803d
  }

  .btn-go:disabled {
    opacity: .6
  }

  .date-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: .75rem;
    padding: .65rem .75rem;
    margin-bottom: .75rem;
    font-size: .82rem;
    color: #166534;
  }

  .date-box .lbl {
    display: block;
    font-size: .72rem;
    font-weight: 600;
    color: #15803d;
    margin-bottom: .1rem
  }

  /* ── Delete confirm ── */
  .del-box {
    text-align: center;
    max-width: 360px;
    margin: 0 auto;
    padding: 1.5rem 1rem
  }

  .del-box .ico {
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fee2e2;
    color: #dc2626;
    margin-bottom: .75rem;
  }

  .del-box .ico i {
    width: 1.4rem;
    height: 1.4rem
  }

  .del-box h2 {
    font-size: 1rem;
    font-weight: 700;
    color: #0f3d23;
    margin: 0 0 .25rem
  }

  .del-box p {
    font-size: .85rem;
    color: #64748b;
    margin: 0 0 1rem
  }

  .del-actions {
    display: flex;
    gap: .5rem
  }

  .del-actions .btn {
    flex: 1;
    min-height: 42px;
    border-radius: 999px;
    font-weight: 600;
    border: 1px solid #d1fae5;
    background: #fff;
    color: #166534;
    font-size: .88rem;
  }

  .del-actions .btn:active {
    background: #f0fdf4
  }

  .del-actions .btn-danger {
    border-color: #dc2626;
    background: #dc2626;
    color: #fff
  }

  .del-actions .btn-danger:active {
    background: #b91c1c
  }

  .fade-in {
    animation: fadeIn .22s ease-out
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(8px)
    }

    to {
      opacity: 1;
      transform: translateY(0)
    }
  }

  /* ── Utility classes used by JS ── */
  .fw-semibold {
    font-weight: 600
  }

  .text-success-emphasis {
    color: #14532d
  }

  .text-success {
    color: #15803d
  }

  .text-center {
    text-align: center
  }

  .me-1 {
    margin-right: .25rem
  }

  .badge {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    border-radius: 999px;
    font-weight: 600
  }

  .text-bg-success-subtle {
    background: #dcfce7;
    color: #166534
  }

  .border-success-subtle {
    border-color: #bbf7d0 !important
  }

  .border {
    border: 1px solid currentColor
  }

  .rounded-pill {
    border-radius: 999px
  }

  .btn-sm {
    min-height: 34px;
    padding: .25rem .55rem;
    font-size: .78rem
  }

  /* ── Tablet+ ── */
  @media (min-width: 576px) {
    .container {
      max-width: 540px;
      padding-right: 1rem;
      padding-left: 1rem
    }

    .header-inner {
      padding: .65rem 0
    }

    .brand-icon {
      width: 2.5rem;
      height: 2.5rem
    }

    .brand-icon i {
      width: 1.35rem;
      height: 1.35rem
    }

    .brand-title {
      font-size: 1.05rem
    }

    .brand-subtitle {
      display: block
    }

    .header-actions .btn span {
      display: inline
    }

    .header-actions .btn {
      padding: .4rem .7rem
    }

    .toolbar .add-btn span {
      display: inline
    }

    .stat-num {
      font-size: 1.25rem
    }

    .stat-cell {
      min-height: 3.25rem;
      padding: .55rem .4rem
    }
  }

  /* ── Desktop ── */
  @media (min-width: 768px) {
    .container {
      max-width: 960px
    }

    .tbl-wrap th {
      padding: .6rem .85rem;
      font-size: .85rem
    }

    .tbl-wrap td {
      padding: .6rem .85rem;
      font-size: .85rem
    }

    .record-actions {
      gap: .35rem
    }

    .record-actions .btn {
      min-height: 34px;
      padding: .25rem .55rem;
      border-radius: .6rem;
      font-size: .78rem;
      gap: .25rem
    }

    .stats-row {
      gap: .75rem;
      margin-bottom: 1rem
    }

    .toolbar {
      margin-bottom: 1rem
    }

    .page-sub {
      font-size: .88rem
    }

    @media (hover:hover) {
      .table-hover tbody tr:hover {
        background: #f0fdf4
      }

      .record-actions .btn:hover {
        background: #dcfce7
      }
    }
  }
  </style>
</head>

<body>
  <div id="app">
    <header class="app-header">
      <div class="container">
        <div class="header-inner">
          <a href="wang_main.php" class="brand">
            <span class="brand-icon"><i data-lucide="archive" aria-hidden="true"></i></span>
            <div>
              <div class="brand-title" id="app-title">ระบบรวบรวมยางพารา</div>
              <div class="brand-subtitle" id="app-subtitle">บันทึกข้อมูลวันวางยาง</div>
            </div>
          </a>
          <div class="header-actions">
            <a href="wang_main.php" class="btn">
              <i data-lucide="arrow-left" aria-hidden="true"></i>
              <span>กลับ</span>
            </a>
            <a href="wang_summary.php" class="btn">
              <i data-lucide="clipboard-list" aria-hidden="true"></i>
              <span>สรุป</span>
            </a>
            <button id="btn-add" type="button" class="btn btn-add-icon">
              <i data-lucide="plus" aria-hidden="true"></i>
              <span>เพิ่ม</span>
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="container" style="padding-top:.75rem;padding-bottom:2rem">
      <div class="top-bar">
        <span id="lane-badge" class="lane-pill" style="display:none">ลาน -</span>
        <span class="page-sub">บันทึกข้อมูลวันวางยางพารา · <span id="current-date-label"></span></span>
      </div>

      <div class="stats-row">
        <div class="stat-cell">
          <div class="stat-num" id="stat-total">0</div>
          <div class="stat-lbl">รายการ</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num" id="stat-bags">0</div>
          <div class="stat-lbl">กระสอบ</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num" id="stat-farmers">0</div>
          <div class="stat-lbl">เกษตรกร</div>
        </div>
      </div>

      <div class="toolbar" id="toolbar">
        <div class="srch">
          <i data-lucide="search" aria-hidden="true"></i>
          <input id="record-search-input" type="search" placeholder="ค้นหาชื่อ, กลุ่ม, กระสอบ, วันที่">
          <span id="search-summary" class="cnt"></span>
        </div>
        <button id="btn-clear-search" class="clr-btn" type="button" aria-label="ล้างคำค้น">
          <i data-lucide="x" aria-hidden="true"></i>
        </button>
        <button id="btn-add-top" type="button" class="add-btn">
          <i data-lucide="plus" aria-hidden="true"></i>
          <span>เพิ่มรายการ</span>
        </button>
      </div>

      <div class="card-box">
        <div class="tbl-wrap" id="data-table-wrap">
          <table class="<?php echo ($selected_lane !== '' ? 'single-lane' : ''); ?>">
            <thead>
              <tr>
                <th>ชื่อเกษตรกร</th>
                <th>กลุ่ม</th>
                <th>ลาน</th>
                <th>กระสอบ</th>
                <th class="text-center">จัดการ</th>
              </tr>
            </thead>
            <tbody id="data-table"></tbody>
          </table>
        </div>
        <div id="empty-state" class="empty-state">
          <i data-lucide="inbox" aria-hidden="true"></i>
          <p>ยังไม่มีรายการ กดปุ่ม "เพิ่มรายการ" เพื่อเริ่มต้น</p>
        </div>
      </div>
    </main>

    <div id="modal" class="app-modal hidden">
      <div class="modal-panel fade-in">
        <div class="modal-hd">
          <h2 id="form-modal-title">เพิ่มรายการรวบรวมยาง</h2>
          <button id="btn-close-modal" type="button" class="modal-x" aria-label="ปิด">
            <i data-lucide="x" aria-hidden="true"></i>
          </button>
        </div>
        <form id="form-add" class="modal-bd">
          <input type="hidden" id="f-backend-id" value="">
          <input type="hidden" id="f-date" value="">
          <input type="hidden" id="f-member-id" value="">
          <input type="hidden" id="f-member-name" value="">
          <input type="hidden" id="f-member-group" value="">
          <input type="hidden" id="f-member-number" value="">
          <input type="hidden" id="f-member-class" value="">

          <div class="fld">
            <label for="memberSearch">ค้นหาสมาชิก</label>
            <input id="memberSearch" type="text" placeholder="ชื่อ / เลขที่ / กลุ่ม / ชั้น" autocomplete="off">
            <ul id="memberResults" class="member-results" hidden></ul>
            <div id="memberSelected" class="member-summary" hidden></div>
          </div>

          <div class="row2">
            <div class="fld">
              <label for="f-lane">ลาน</label>
              <select id="f-lane" required class="form-select">
                <option value="">เลือกลาน</option>
                <option value="1">ลาน 1</option>
                <option value="2">ลาน 2</option>
                <option value="3">ลาน 3</option>
                <option value="4">ลาน 4</option>
              </select>
            </div>
            <div class="fld">
              <label for="f-bags">จำนวนกระสอบ</label>
              <input id="f-bags" type="number" step="0.01" min="0.01" required>
            </div>
          </div>

          <div class="date-box">
            <span class="lbl">วันที่</span>
            <span id="current-date-display"></span>
            <div id="record-summary" style="margin-top:.25rem"></div>
          </div>

          <div class="fld">
            <label for="f-note">หมายเหตุ</label>
            <textarea id="f-note" rows="3" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
          </div>

          <button type="submit" id="btn-submit" class="btn-go">บันทึก</button>
        </form>
      </div>
    </div>

    <div id="delete-confirm" class="app-modal hidden">
      <div class="modal-panel fade-in">
        <div class="del-box">
          <div class="ico"><i data-lucide="trash-2" aria-hidden="true"></i></div>
          <h2>ยืนยันการลบ?</h2>
          <p>รายการนี้จะถูกลบอย่างถาวร</p>
          <div class="del-actions">
            <button id="btn-cancel-del" type="button" class="btn">ยกเลิก</button>
            <button id="btn-confirm-del" type="button" class="btn btn-danger">ลบ</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
  // State (initialized from server data)
  let records = <?php echo $initial_records_js ?? '[]'; ?>;
  const defaultLane = '<?php echo htmlspecialchars($selected_lane_js, ENT_QUOTES); ?>';
  let searchTerm = '';
  let editingTarget = null;
  let formMode = 'create';
  let deleteTarget = null;

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

  (function() {
    const el = document.getElementById('current-date-label');
    if (el) el.textContent = formatThaiDate(getTodayDateString());
  })();

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

  async function upsertRecord(payload) {
    try {
      const res = await fetch('save_wang.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json; charset=utf-8'
        },
        body: JSON.stringify(payload)
      });
      const j = await res.json().catch(() => null);
      if (res.ok && j && j.isOk) {
        const backendId = 'db-' + j.id;
        const rec = Object.assign({
          __backendId: backendId
        }, payload);
        delete rec.backend_id;

        const idx = records.findIndex(r => r.__backendId === backendId);
        const updateIdx = idx >= 0 ? idx : records.findIndex(r => String(r.__backendId || '') === String(payload
          .backend_id || ''));
        if (payload.backend_id && updateIdx >= 0) {
          records[updateIdx] = rec;
        } else if (idx >= 0) {
          records[idx] = rec;
        } else {
          records.push(rec);
        }
        dataHandler.onDataChanged(records);
        return {
          isOk: true,
          data: rec
        };
      }
      throw new Error((j && j.message) ? j.message : 'Save failed');
    } catch (e) {
      if (window.dataSdk) {
        if (payload.backend_id && typeof window.dataSdk.update === 'function') {
          return await window.dataSdk.update(payload.backend_id, payload);
        }
        if (!payload.backend_id && typeof window.dataSdk.create === 'function') {
          return await window.dataSdk.create(payload);
        }
      }
      throw e;
    }
  }

  async function deleteRecord(target) {
    try {
      const backendId = String(target.__backendId || '');
      const res = await fetch('delete_wang.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json; charset=utf-8'
        },
        body: JSON.stringify({
          backend_id: backendId
        })
      });
      const j = await res.json().catch(() => null);
      if (res.ok && j && j.isOk) {
        records = records.filter(r => r.__backendId !== target.__backendId);
        dataHandler.onDataChanged(records);
        return {
          isOk: true
        };
      }
      throw new Error((j && j.message) ? j.message : 'Delete failed');
    } catch (e) {
      if (window.dataSdk && typeof window.dataSdk.delete === 'function') {
        return await window.dataSdk.delete(target);
      }
      throw e;
    }
  }

  function updateStats() {
    const visible = getVisibleRecords();
    document.getElementById('stat-total').textContent = visible.length;
    const totalBags = visible.reduce((s, r) => s + (parseFloat(r.bags) || 0), 0);
    document.getElementById('stat-bags').textContent = formatNumber(totalBags);
    const farmers = new Set(visible.map(r => r.farmer_name)).size;
    document.getElementById('stat-farmers').textContent = farmers;
    const summary = document.getElementById('search-summary');
    if (summary) {
      summary.textContent = visible.length < records.length ? `${visible.length}/${records.length}` : '';
    }
  }

  function renderTable() {
    const tbody = document.getElementById('data-table');
    const empty = document.getElementById('empty-state');
    const visible = getVisibleRecords();
    empty.style.display = visible.length === 0 ? 'block' : 'none';
    if (empty.querySelector('p')) {
      empty.querySelector('p').textContent = records.length === 0 ?
        'ยังไม่มีรายการ กดปุ่ม "เพิ่มรายการ" เพื่อเริ่มต้น' :
        'ไม่พบรายการที่ตรงกับคำค้น';
    }

    const existingRows = new Map([...tbody.children].map(el => [el.dataset.id, el]));

    visible.forEach(rec => {
      if (existingRows.has(rec.__backendId)) {
        const row = existingRows.get(rec.__backendId);
        row.children[0].innerHTML = esc(formatFarmerDisplay(rec.farmer_name, rec.date));
        row.children[1].textContent = rec.group_name;
        row.children[2].textContent = rec.lane;
        row.children[3].textContent = formatNumber(rec.bags);
        const actions = row.querySelector('.record-actions');
        if (actions) {
          actions.innerHTML = `
          <button type="button" class="btn btn-outline-success rounded-pill" title="แก้ไข"
            aria-label="แก้ไข" onclick="openEditModal('${rec.__backendId}')">
            <i data-lucide="pencil" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-outline-danger rounded-pill" title="ลบ"
            aria-label="ลบ" onclick="confirmDelete('${rec.__backendId}')">
            <i data-lucide="trash-2" aria-hidden="true"></i>
          </button>
        `;
        }
        existingRows.delete(rec.__backendId);
      } else {
        const row = document.createElement('tr');
        row.dataset.id = rec.__backendId;
        row.className = '';
        row.innerHTML = `
        <td data-label="ชื่อเกษตรกร" class="fw-semibold text-success-emphasis">${esc(formatFarmerDisplay(rec.farmer_name, rec.date))}</td>
        <td data-label="กลุ่ม" class="text-success">${esc(rec.group_name)}</td>
        <td data-label="ลาน"><span class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis">ลาน ${esc(rec.lane)}</span></td>
        <td data-label="กระสอบ">${esc(formatNumber(rec.bags))}</td>
        <td data-label="จัดการ" class="text-center">
          <div class="record-actions">
            <button type="button" class="btn btn-outline-success rounded-pill" title="แก้ไข"
              aria-label="แก้ไข" onclick="openEditModal('${rec.__backendId}')">
              <i data-lucide="pencil" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-outline-danger rounded-pill" title="ลบ"
              aria-label="ลบ" onclick="confirmDelete('${rec.__backendId}')">
              <i data-lucide="trash-2" aria-hidden="true"></i>
            </button>
          </div>
        </td>
      `;
        tbody.appendChild(row);
        if (window.lucide && lucide.createIcons) lucide.createIcons();
      }
    });

    existingRows.forEach(el => el.remove());
    if (window.lucide && lucide.createIcons) {
      lucide.createIcons();
    }
  }

  function normalizeText(value) {
    return String(value ?? '')
      .toLowerCase()
      .trim();
  }

  function getVisibleRecords() {
    const q = normalizeText(searchTerm);
    if (!q) return records;
    return records.filter(rec => {
      const haystack = [
        rec.farmer_name,
        rec.group_name,
        `ลาน ${rec.lane}`,
        rec.lane,
        rec.bags,
        rec.date,
        formatThaiDate(rec.date)
      ].map(normalizeText);
      return haystack.some(v => v.includes(q));
    });
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function formatNumber(value) {
    const n = Number(value || 0);
    return n.toLocaleString('th-TH', {
      minimumFractionDigits: Number.isInteger(n) ? 0 : 1,
      maximumFractionDigits: 2
    });
  }

  function formatFarmerDisplay(name, dateStr) {
    const cleanName = String(name || '').trim();
    const cleanDate = formatThaiDateShort(dateStr);
    return cleanDate ? `${cleanName} (${cleanDate})` : cleanName;
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

  function formatThaiDateShort(dateStr) {
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '';
    const monthsThShort = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.',
      'ธ.ค.'
    ];
    const day = date.getDate();
    const month = monthsThShort[date.getMonth()];
    const year = date.getFullYear() + 543;
    return `${day} ${month}${year}`;
  }

  function getTodayDateString() {
    const today = new Date();
    return today.toISOString().split('T')[0];
  }

  // Modal
  const modal = document.getElementById('modal');
  const form = document.getElementById('form-add');
  const formTitle = document.getElementById('form-modal-title');
  const submitBtn = document.getElementById('btn-submit');
  const backendIdInput = document.getElementById('f-backend-id');
  const dateInput = document.getElementById('f-date');
  const currentDateDisplay = document.getElementById('current-date-display');
  const recordSummary = document.getElementById('record-summary');
  const bagsInput = document.getElementById('f-bags');
  const memberSearchInput = document.getElementById('memberSearch');
  const memberResults = document.getElementById('memberResults');
  const memberSelectedBox = document.getElementById('memberSelected');
  const memberIdInput = document.getElementById('f-member-id');
  const memberNameInput = document.getElementById('f-member-name');
  const memberGroupInput = document.getElementById('f-member-group');
  const memberNumberInput = document.getElementById('f-member-number');
  const memberClassInput = document.getElementById('f-member-class');
  const laneSelect = document.getElementById('f-lane');
  const noteInput = document.getElementById('f-note');
  const searchInput = document.getElementById('record-search-input');
  const clearSearchBtn = document.getElementById('btn-clear-search');
  let memberSearchTimer = null;

  function setLaneState(locked, value) {
    if (!laneSelect) return;
    if (locked) {
      laneSelect.disabled = true;
      laneSelect.value = value || defaultLane || '';
      laneSelect.classList.add('bg-light');
    } else {
      laneSelect.disabled = false;
      laneSelect.classList.remove('bg-light');
      if (!laneSelect.value) {
        laneSelect.value = '';
      }
    }
  }

  function hideMemberResults() {
    if (!memberResults) return;
    memberResults.hidden = true;
    memberResults.innerHTML = '';
  }

  function renderMemberSelected(member) {
    if (!memberSelectedBox) return;
    const classText = member.mem_class === 'general' ? 'เกษตรกรทั่วไป' : (member.mem_class === 'member' ? 'สมาชิก' :
    '');
    const classSuffix = classText ? ` | ${esc(classText)}` : '';
    memberSelectedBox.hidden = false;
    memberSelectedBox.innerHTML = `
    <span class="member-summary-text">
      ใช้สมาชิก: <span class="tag">#${esc(member.mem_id || '')}</span>
      ${esc(member.mem_fullname || '')} | กลุ่ม: ${esc(member.mem_group || '')} | เลขที่: ${esc(member.mem_number || '-')}${classSuffix}
    </span>
    <button type="button" id="clearMember">เปลี่ยน</button>
  `;
    const clearBtn = memberSelectedBox.querySelector('#clearMember');
    if (clearBtn) clearBtn.addEventListener('click', clearSelectedMember);
  }

  function selectMember(member) {
    memberIdInput.value = String(member.mem_id || '');
    memberNameInput.value = String(member.mem_fullname || '');
    memberGroupInput.value = String(member.mem_group || '');
    memberNumberInput.value = String(member.mem_number || '');
    memberClassInput.value = String(member.mem_class || '');
    if (memberSearchInput) {
      const name = String(member.mem_fullname || '').trim();
      memberSearchInput.value = name;
    }
    hideMemberResults();
    renderMemberSelected(member);
    if (bagsInput) {
      bagsInput.focus();
      bagsInput.select?.();
    }
  }

  function clearSelectedMember(focusSearch = true) {
    memberIdInput.value = '';
    memberNameInput.value = '';
    memberGroupInput.value = '';
    memberNumberInput.value = '';
    memberClassInput.value = '';
    hideMemberResults();
    if (memberSelectedBox) {
      memberSelectedBox.hidden = true;
      memberSelectedBox.innerHTML = '';
    }
    if (memberSearchInput) {
      memberSearchInput.value = '';
      if (focusSearch) {
        memberSearchInput.focus();
        memberSearchInput.select?.();
      }
    }
  }

  function renderMemberResults(rows) {
    if (!memberResults) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      hideMemberResults();
      return;
    }
    memberResults.innerHTML = rows.map(member => {
      const payload = encodeURIComponent(JSON.stringify(member));
      return `
      <li data-member="${payload}">
        <strong>${esc(member.mem_fullname || '')}</strong>
        <div class="small">#${esc(member.mem_id || '')} | กลุ่ม: ${esc(member.mem_group || '')} | เลขที่: ${esc(member.mem_number || '')} | ชั้น: ${esc(member.mem_class || '')}</div>
      </li>
    `;
    }).join('');
    memberResults.hidden = false;
  }

  function searchMembers(term) {
    if (!term || term.length < 2) {
      hideMemberResults();
      return;
    }
    fetch('members_search.php?q=' + encodeURIComponent(term), {
        cache: 'no-store'
      })
      .then(res => res.ok ? res.json() : [])
      .then(renderMemberResults)
      .catch(() => hideMemberResults());
  }

  function resetFormFields() {
    form.reset();
    backendIdInput.value = '';
    dateInput.value = '';
    currentDateDisplay.textContent = '';
    recordSummary.textContent = '';
    if (noteInput) noteInput.value = '';
    clearSelectedMember(false);
    hideMemberResults();
    setLaneState(Boolean(defaultLane), defaultLane || '');
  }

  function setFormMode(mode, record = null) {
    formMode = mode;
    editingTarget = record;
    formTitle.textContent = mode === 'edit' ? 'แก้ไขจำนวนกระสอบ' : 'เพิ่มรายการรวบรวมยาง';
    submitBtn.textContent = mode === 'edit' ? 'บันทึกการแก้ไข' : 'บันทึก';
    const dateValue = record ? record.date : getTodayDateString();
    dateInput.value = dateValue;
    currentDateDisplay.textContent = formatThaiDate(dateValue);
    recordSummary.textContent = record ?
      `${record.farmer_name} · กลุ่ม ${record.group_name} · ลาน ${record.lane}` :
      'กรอกข้อมูลเพื่อบันทึกรายการใหม่';
  }

  function openModal(mode, record = null) {
    resetFormFields();
    setFormMode(mode, record);
    if (record) {
      backendIdInput.value = record.__backendId || '';
      selectMember({
        mem_id: record.member_id || '',
        mem_fullname: record.farmer_name || '',
        mem_group: record.group_name || '',
        mem_number: record.member_number || '',
        mem_class: record.member_class || ''
      });
      bagsInput.value = record.bags || '';
      if (noteInput) noteInput.value = record.note || '';
      laneSelect.value = record.lane || '';
      setLaneState(Boolean(defaultLane), defaultLane || record.lane || '');
    } else {
      backendIdInput.value = '';
      const fallbackLane = defaultLane || '';
      if (fallbackLane) {
        laneSelect.value = fallbackLane;
      }
      setLaneState(Boolean(defaultLane), fallbackLane);
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    window.setTimeout(() => {
      if (record && bagsInput) {
        bagsInput.focus();
        bagsInput.select?.();
      } else if (memberSearchInput) {
        memberSearchInput.focus();
      }
    }, 0);
  }

  function openAddModal() {
    openModal('create');
  }

  function openEditModal(id) {
    const record = records.find(r => r.__backendId === id);
    if (!record) return;
    openModal('edit', record);
  }

  window.openEditModal = openEditModal;
  document.getElementById('btn-add').onclick = openAddModal;
  const btnAddTop = document.getElementById('btn-add-top');
  if (btnAddTop) btnAddTop.onclick = openAddModal;
  document.getElementById('btn-close-modal').onclick = () => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  };

  if (memberSearchInput) {
    memberSearchInput.addEventListener('input', (e) => {
      clearTimeout(memberSearchTimer);
      memberSearchTimer = setTimeout(() => searchMembers(e.target.value.trim()), 250);
    });
  }

  if (memberResults) {
    memberResults.addEventListener('click', (e) => {
      const item = e.target.closest('li[data-member]');
      if (!item) return;
      try {
        selectMember(JSON.parse(decodeURIComponent(item.getAttribute('data-member'))));
      } catch (_) {}
    });
  }

  document.addEventListener('click', (e) => {
    if (!memberResults || !memberSearchInput) return;
    if (!memberResults.contains(e.target) && e.target !== memberSearchInput) {
      hideMemberResults();
    }
  });

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      searchTerm = searchInput.value;
      renderTable();
      updateStats();
    });
  }

  if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', () => {
      if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
      }
      searchTerm = '';
      renderTable();
      updateStats();
    });
  }

  form.onsubmit = async (e) => {
    e.preventDefault();
    if (records.length >= 999 && !backendIdInput.value) {
      submitBtn.textContent = 'ถึงขีดจำกัดแล้ว (999)';
      return;
    }
    const btn = submitBtn;
    btn.disabled = true;
    btn.textContent = 'กำลังบันทึก...';
    const isEditing = Boolean(backendIdInput.value);
    const selectedMemberId = parseInt(memberIdInput.value, 10) || 0;
    const selectedMemberName = memberNameInput.value.trim();
    const selectedMemberGroup = memberGroupInput.value.trim();
    const selectedMemberNumber = memberNumberInput.value.trim();
    const selectedMemberClass = memberClassInput.value.trim();
    try {
      const bags = parseFloat(bagsInput.value);
      if ((!isEditing && !selectedMemberId) || !selectedMemberName || !selectedMemberGroup) {
        throw new Error('กรุณาค้นหาและเลือกสมาชิกก่อนบันทึก');
      }
      if (!Number.isFinite(bags) || bags <= 0) {
        throw new Error('กรุณากรอกจำนวนกระสอบเป็นตัวเลขมากกว่า 0');
      }
      const payload = {
        member_id: selectedMemberId,
        member_number: selectedMemberNumber,
        member_class: selectedMemberClass,
        farmer_name: selectedMemberName,
        group_name: selectedMemberGroup,
        lane: laneSelect.value,
        bags,
        weight: 0,
        note: noteInput ? noteInput.value.trim() : '',
        date: dateInput.value || getTodayDateString()
      };
      if (backendIdInput.value) {
        payload.backend_id = backendIdInput.value;
      }
      const result = await upsertRecord(payload);
      if (result.isOk) {
        resetFormFields();
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      } else {
        throw new Error('Save failed');
      }
    } catch (err) {
      alert((err && err.message) ? err.message : 'บันทึกไม่สำเร็จ');
    } finally {
      btn.disabled = false;
      btn.textContent = formMode === 'edit' ? 'บันทึกการแก้ไข' : 'บันทึก';
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
    /* ignore if functions not ready */
  }
  </script>
</body>

</html>
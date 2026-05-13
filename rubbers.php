<?php
ob_start();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/header.php';

// ตรวจสอบการล็อกอิน - ถ้ายังไม่ล็อกอินให้ redirect ไปหน้า login
if (!is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$db = db();
$errors = [];
$msg = $_GET['msg'] ?? '';
$csrf = csrf_token();
$default_saveby = $_SESSION['user_name'] ?? 'เจ้าหน้าที่';
$today = date('Y-m-d');

// add: dompdf availability check
$hasDompdf = file_exists(__DIR__ . '/vendor/autoload.php');

// new: support lan=all
$lanParam = $_GET['lan'] ?? ($_POST['lan'] ?? '1');
if ($lanParam === 'all') {
  $currentLan = 'all';
} else {
  $currentLan = (int)$lanParam;
  if (!in_array($currentLan, [1, 2, 3, 4], true)) $currentLan = 1;
}

// new: search params and pagination controls
$search = trim((string)($_GET['search'] ?? ''));
$classFilter = trim((string)($_GET['class'] ?? 'all'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to   = trim((string)($_GET['date_to'] ?? ''));

// Force this page to show only the latest rubber price round (latest pr_date)
$latest_round_date = $today;
if ($res = $db->query("SELECT pr_date FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1")) {
  if ($r = $res->fetch_assoc()) {
    $d = (string)($r['pr_date'] ?? '');
    $dt = $d !== '' ? DateTime::createFromFormat('Y-m-d', $d) : null;
    if ($dt && $dt->format('Y-m-d') === $d) {
      $latest_round_date = $d;
    }
  }
  $res->free();
}

if ($date_from === '') {
  $date_from = $latest_round_date;
}
if ($date_to === '') {
  $date_to = $latest_round_date;
}

// added: member selection & search variables
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$msearch = isset($_GET['msearch']) ? trim((string)($_GET['msearch'])) : '';
$memberSelectedRow = null;

// สร้างตาราง
$db->query("CREATE TABLE IF NOT EXISTS tbl_rubber (
	ru_id INT(11) NOT NULL AUTO_INCREMENT,
	ru_date DATE NOT NULL,
	ru_lan VARCHAR(255) NOT NULL,
	ru_group VARCHAR(255) NOT NULL,
	ru_number VARCHAR(255) NOT NULL,
	ru_fullname VARCHAR(255) NOT NULL,
	ru_class VARCHAR(255) NOT NULL,
	ru_quantity DECIMAL(18,2) NOT NULL,
	ru_hoon DECIMAL(18,2) NOT NULL,
	ru_loan DECIMAL(18,2) NOT NULL,
	ru_shortdebt DECIMAL(18,2) NOT NULL,
	ru_deposit DECIMAL(18,2) NOT NULL,
	ru_tradeloan DECIMAL(18,2) NOT NULL,
	ru_insurance DECIMAL(18,2) NOT NULL,
  ru_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,      -- added
  ru_expend DECIMAL(18,2) NOT NULL DEFAULT 0.00,     -- added
  ru_netvalue DECIMAL(18,2) NOT NULL DEFAULT 0.00,   -- added
	ru_saveby VARCHAR(255) NOT NULL,
	ru_savedate DATE NOT NULL,
	PRIMARY KEY (ru_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;");

// added: ensure columns exist on old schema (portable across MySQL/MariaDB versions)
$ensureCols = [
  'ru_value' => "ALTER TABLE tbl_rubber ADD COLUMN ru_value DECIMAL(18,2) NOT NULL DEFAULT 0.00",
  'ru_expend' => "ALTER TABLE tbl_rubber ADD COLUMN ru_expend DECIMAL(18,2) NOT NULL DEFAULT 0.00",
  'ru_netvalue' => "ALTER TABLE tbl_rubber ADD COLUMN ru_netvalue DECIMAL(18,2) NOT NULL DEFAULT 0.00",
];
$dbNameRes = $db->query('SELECT DATABASE() AS dbname');
$dbNameRow = $dbNameRes ? $dbNameRes->fetch_assoc() : null;
$dbName = $dbNameRow['dbname'] ?? '';
if ($dbNameRes) { $dbNameRes->free(); }
if ($dbName !== '') {
  $colStmt = $db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
  if ($colStmt) {
    $tableName = 'tbl_rubber';
    foreach ($ensureCols as $colName => $alterSql) {
      $colStmt->bind_param('sss', $dbName, $tableName, $colName);
      $colStmt->execute();
      $cntRow = $colStmt->get_result()->fetch_assoc();
      $exists = $cntRow && (int)($cntRow['cnt'] ?? 0) > 0;
      if (!$exists) {
        $db->query($alterSql);
      }
    }
    $colStmt->close();
  }
}

// เตรียมค่าเริ่มต้นของฟอร์ม
$form = [
  'ru_id' => null,
  'ru_date' => $today,
  'ru_lan' => (string)($currentLan === 'all' ? 1 : $currentLan), // changed: default 1 if 'all'
  'ru_group' => '',
  'ru_number' => '',
  'ru_fullname' => '',
  'ru_class' => '',
  'ru_quantity' => '0.00',
  'ru_hoon' => '0.00',
  'ru_loan' => '0.00',
  'ru_shortdebt' => '0.00',
  'ru_deposit' => '0.00',
  'ru_tradeloan' => '0.00',
  'ru_insurance' => '0.00',
  'ru_savedate' => $today,
];

// added: if not editing rubber and member_id selected, load member to prefill
if (($member_id > 0) && (($_GET['action'] ?? '') !== 'edit')) {
  $stm = $db->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_class FROM tbl_member WHERE mem_id=?");
  $stm->bind_param('i', $member_id);
  $stm->execute();
  $rs = $stm->get_result();
  if ($memberSelectedRow = $rs->fetch_assoc()) {
    $form['ru_group'] = $memberSelectedRow['mem_group'];
    $form['ru_number'] = $memberSelectedRow['mem_number'];
    $form['ru_fullname'] = $memberSelectedRow['mem_fullname'];
    $form['ru_class'] = $memberSelectedRow['mem_class'];
  }
  $stm->close();
}

// โหลดข้อมูลสำหรับแก้ไข
if (($_GET['action'] ?? '') === 'edit') {
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0) {
    $st = $db->prepare("SELECT * FROM tbl_rubber WHERE ru_id=?");
    $st->bind_param('i', $id);
    $st->execute();
    $res = $st->get_result();
    if ($row = $res->fetch_assoc()) {
      $form = $row;
      // keep nav lane on edit
      if (isset($row['ru_lan']) && in_array((int)$row['ru_lan'], [1, 2, 3, 4], true)) {
        $currentLan = (int)$row['ru_lan'];
      }
    } else {
      $errors[] = 'ไม่พบรายการสำหรับแก้ไข';
    }
    $st->close();
  }
}

// จัดการ POST: create/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $errors[] = 'โทเค็นไม่ถูกต้อง';
  } else {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
      $id = (int)($_POST['ru_id'] ?? 0);
      if ($id > 0) {
        $st = $db->prepare("DELETE FROM tbl_rubber WHERE ru_id=?");
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        header('Location: rubbers.php?lan=' . ($currentLan === 'all' ? 'all' : (int)$currentLan) . '&msg=' . urlencode('ลบรายการแล้ว'));
        exit;
      }
      $errors[] = 'ระบุรายการที่จะลบไม่ถูกต้อง';
    } elseif ($action === 'save') {
      // added: enforce member-linked fields for new record
      $post_member_id = isset($_POST['ru_member_id']) ? (int)$_POST['ru_member_id'] : 0;
      $isNew = empty($_POST['ru_id']);
      if ($isNew && $post_member_id > 0) {
        $stm = $db->prepare("SELECT mem_group, mem_number, mem_fullname, mem_class FROM tbl_member WHERE mem_id=?");
        $stm->bind_param('i', $post_member_id);
        $stm->execute();
        $mr = $stm->get_result()->fetch_assoc();
        $stm->close();
        if ($mr) {
          $_POST['ru_group'] = $mr['mem_group'];
          $_POST['ru_number'] = $mr['mem_number'];
          $_POST['ru_fullname'] = $mr['mem_fullname'];
          $_POST['ru_class'] = $mr['mem_class'];
        } else {
          $errors[] = 'ไม่พบสมาชิกที่เลือก';
        }
      }
      // รับค่าและตรวจสอบ
      $data = [];
      $fieldsText = ['ru_lan', 'ru_group', 'ru_number', 'ru_fullname', 'ru_class']; // ensure ru_lan present
      $fieldsDate = ['ru_date', 'ru_savedate'];
      $fieldsNum  = ['ru_quantity', 'ru_hoon', 'ru_loan', 'ru_shortdebt', 'ru_deposit', 'ru_tradeloan', 'ru_insurance'];

      foreach ($fieldsText as $f) {
        $data[$f] = trim((string)($_POST[$f] ?? ''));
        if ($data[$f] === '') $errors[] = "กรุณากรอก {$f}";
      }
      foreach ($fieldsDate as $f) {
        $data[$f] = trim((string)($_POST[$f] ?? ''));
        $dt = DateTime::createFromFormat('Y-m-d', $data[$f]);
        if (!$dt || $dt->format('Y-m-d') !== $data[$f]) $errors[] = "รูปแบบวันที่ไม่ถูกต้อง: {$f}";
      }
      foreach ($fieldsNum as $f) {
        $val = $_POST[$f] ?? '';
        $flt = filter_var($val, FILTER_VALIDATE_FLOAT);
        if ($flt === false) {
          $errors[] = "ต้องเป็นตัวเลข: {$f}";
          $data[$f] = '0.00';
        } else {
          $data[$f] = number_format((float)$flt, 2, '.', '');
        }
      }

      // new: validate lane 1..4
      $lanVal = isset($_POST['ru_lan']) ? (int)$_POST['ru_lan'] : ($currentLan === 'all' ? 1 : $currentLan);
      if (!in_array($lanVal, [1, 2, 3, 4], true)) {
        $errors[] = 'ลานไม่ถูกต้อง';
      }
      $data['ru_lan'] = (string)$lanVal;

      // new: compute value/expend/netvalue using latest price
      $latestPrice = 0.0;
      if ($res = $db->query("SELECT pr_price FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1")) {
        if ($pr = $res->fetch_assoc()) $latestPrice = (float)$pr['pr_price'];
        $res->free();
      }
      $qty = (float)$data['ru_quantity'];
      $value  = $qty * $latestPrice;
      $expend = (float)$data['ru_hoon'] + (float)$data['ru_loan'] + (float)$data['ru_shortdebt'] + (float)$data['ru_deposit'] + (float)$data['ru_tradeloan'] + (float)$data['ru_insurance'];
      $net    = $value - $expend;

      $data['ru_value']    = number_format((float)$value, 2, '.', '');
      $data['ru_expend']   = number_format((float)$expend, 2, '.', '');
      $data['ru_netvalue'] = number_format((float)$net, 2, '.', '');

      // set ru_saveby from session (prefer fullname for consistency; fallback to username)
      $cuTmp = current_user();
      $savebyFull = $cuTmp['user_fullname'] ?? ($_SESSION['user_fullname'] ?? '');
      $savebyUser = $cuTmp['user_name'] ?? ($_SESSION['user_name'] ?? '');
      $data['ru_saveby'] = $savebyFull !== '' ? $savebyFull : ($savebyUser !== '' ? $savebyUser : 'เจ้าหน้าที่');

      if (!$errors) {
        $id = isset($_POST['ru_id']) && $_POST['ru_id'] !== '' ? (int)$_POST['ru_id'] : 0;
        if ($id > 0) {
          $st = $db->prepare("UPDATE tbl_rubber SET
						ru_date=?, ru_lan=?, ru_group=?, ru_number=?, ru_fullname=?, ru_class=?, ru_quantity=?,
						ru_hoon=?, ru_loan=?, ru_shortdebt=?, ru_deposit=?, ru_tradeloan=?, ru_insurance=?,
            ru_value=?, ru_expend=?, ru_netvalue=?,  -- added
						ru_saveby=?, ru_savedate=? WHERE ru_id=?");
          $st->bind_param(
            str_repeat('s', 18) . 'i',
            $data['ru_date'],
            $data['ru_lan'],
            $data['ru_group'],
            $data['ru_number'],
            $data['ru_fullname'],
            $data['ru_class'],
            $data['ru_quantity'],
            $data['ru_hoon'],
            $data['ru_loan'],
            $data['ru_shortdebt'],
            $data['ru_deposit'],
            $data['ru_tradeloan'],
            $data['ru_insurance'],
            $data['ru_value'], $data['ru_expend'], $data['ru_netvalue'],
            $data['ru_saveby'],
            $data['ru_savedate'],
            $id
          );
          $st->execute();
          $st->close();
          $lanRedirect = ($lanParam === 'all') ? 'all' : (int)$data['ru_lan'];
          header('Location: rubbers.php?lan=' . $lanRedirect . '&msg=' . urlencode('บันทึกการแก้ไขแล้ว'));
          exit;
        } else {
          $st = $db->prepare("INSERT INTO tbl_rubber
						(ru_date, ru_lan, ru_group, ru_number, ru_fullname, ru_class, ru_quantity,
						 ru_hoon, ru_loan, ru_shortdebt, ru_deposit, ru_tradeloan, ru_insurance,
             ru_value, ru_expend, ru_netvalue,  -- added
						 ru_saveby, ru_savedate)
						VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
          $st->bind_param(
            str_repeat('s', 18),
            $data['ru_date'],
            $data['ru_lan'],
            $data['ru_group'],
            $data['ru_number'],
            $data['ru_fullname'],
            $data['ru_class'],
            $data['ru_quantity'],
            $data['ru_hoon'],
            $data['ru_loan'],
            $data['ru_shortdebt'],
            $data['ru_deposit'],
            $data['ru_tradeloan'],
            $data['ru_insurance'],
            $data['ru_value'], $data['ru_expend'], $data['ru_netvalue'],
            $data['ru_saveby'],
            $data['ru_savedate']
          );
          $st->execute();
          $st->close();
          $lanRedirect = ($lanParam === 'all') ? 'all' : (int)$data['ru_lan'];
          header('Location: rubbers.php?lan=' . $lanRedirect . '&msg=' . urlencode('บันทึกข้อมูลแล้ว'));
          exit;
        }
      } else {
        $form = array_merge($form, $_POST); // คืนค่าฟอร์มเดิมเมื่อมี error
      }
    }
  }
}

$cu = current_user(); // ดึงข้อมูลผู้ใช้ปัจจุบัน
$isAdmin = isset($cu['user_level']) && $cu['user_level'] === 'admin';

// ดึงข้อมูลรายการและสรุปตามตัวกรอง
$rows = [];
$conds = [];
$binds = [];
$types = '';

if ($currentLan !== 'all') {
  $conds[] = 'ru_lan = ?';
  $types .= 's';
  $binds[] = (string)$currentLan;
}

if (!$isAdmin) {
  $svFull = $cu['user_fullname'] ?? ($_SESSION['user_fullname'] ?? '');
  $svUser = $cu['user_name'] ?? ($_SESSION['user_name'] ?? $svFull);
  $conds[] = '(ru_saveby = ? OR ru_saveby = ?)';
  $types .= 'ss';
  array_push($binds, $svFull, $svUser);
}

if ($search !== '') {
  $like = '%' . $search . '%';
  $conds[] = '(ru_group LIKE ? OR ru_number LIKE ? OR ru_fullname LIKE ? OR ru_class LIKE ?)';
  $types .= 'ssss';
  array_push($binds, $like, $like, $like, $like);
}

$dateFromValid = DateTime::createFromFormat('Y-m-d', $date_from);
if ($dateFromValid && $dateFromValid->format('Y-m-d') === $date_from) {
  $conds[] = 'ru_date >= ?';
  $types .= 's';
  $binds[] = $date_from;
}

$dateToValid = DateTime::createFromFormat('Y-m-d', $date_to);
if ($dateToValid && $dateToValid->format('Y-m-d') === $date_to) {
  $conds[] = 'ru_date <= ?';
  $types .= 's';
  $binds[] = $date_to;
}

if (in_array($classFilter, ['member', 'general'], true)) {
  $conds[] = 'LOWER(TRIM(ru_class)) = ?';
  $types .= 's';
  $binds[] = $classFilter;
}

$whereSql = $conds ? ' WHERE ' . implode(' AND ', $conds) : '';

$countSql = "SELECT COUNT(*) AS total_count FROM tbl_rubber" . $whereSql;
$totalCount = 0;
$countStmt = $db->prepare($countSql);
if ($countStmt) {
  if ($types !== '') {
    $countStmt->bind_param($types, ...$binds);
  }
  $countStmt->execute();
  $countRes = $countStmt->get_result();
  if ($countRes && ($countRow = $countRes->fetch_assoc())) {
    $totalCount = (int)($countRow['total_count'] ?? 0);
  }
  if ($countRes) {
    $countRes->free();
  }
  $countStmt->close();
}

$summary = [
  'sumQty' => 0.0,
  'sumValue' => 0.0,
  'sumExpend' => 0.0,
  'sumNet' => 0.0,
];
$summarySql = "SELECT
    COALESCE(SUM(ru_quantity), 0) AS sumQty,
    COALESCE(SUM(ru_value), 0) AS sumValue,
    COALESCE(SUM(ru_expend), 0) AS sumExpend,
    COALESCE(SUM(ru_netvalue), 0) AS sumNet
  FROM tbl_rubber" . $whereSql;
$summaryStmt = $db->prepare($summarySql);
if ($summaryStmt) {
  if ($types !== '') {
    $summaryStmt->bind_param($types, ...$binds);
  }
  $summaryStmt->execute();
  $summaryRes = $summaryStmt->get_result();
  if ($summaryRes && ($summaryRow = $summaryRes->fetch_assoc())) {
    $summary['sumQty'] = (float)($summaryRow['sumQty'] ?? 0);
    $summary['sumValue'] = (float)($summaryRow['sumValue'] ?? 0);
    $summary['sumExpend'] = (float)($summaryRow['sumExpend'] ?? 0);
    $summary['sumNet'] = (float)($summaryRow['sumNet'] ?? 0);
  }
  if ($summaryRes) {
    $summaryRes->free();
  }
  $summaryStmt->close();
}

$totalPages = max(1, (int)ceil(max(1, $totalCount) / $perPage));
if ($page > $totalPages) {
  $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$listSql = "SELECT * FROM tbl_rubber" . $whereSql . " ORDER BY ru_date DESC, ru_id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
$listStmt = $db->prepare($listSql);
if ($listStmt) {
  if ($types !== '') {
    $listStmt->bind_param($types, ...$binds);
  }
  $listStmt->execute();
  $listRes = $listStmt->get_result();
  if ($listRes) {
    while ($r = $listRes->fetch_assoc()) {
      $rows[] = $r;
    }
    $listRes->free();
  }
  $listStmt->close();
}

// new: build export query string for Excel/PDF buttons
$exportBaseParams = [
  'lan' => ($currentLan === 'all' ? 'all' : $currentLan),
  'search' => $search,
  'date_from' => $date_from,
  'date_to' => $date_to,
  'class' => $classFilter,
];
$exportQuery = http_build_query(array_filter($exportBaseParams, fn($v) => $v !== ''));
?>
<html lang="th">

<head>
  <meta charset="utf-8">
  <title>จัดการข้อมูลยางพารา</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
    crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Thai:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
  <style>
  .rubbers-page {
    --rubber-primary: #198754;
    --rubber-primary-soft: #e9f6ed;
    --rubber-surface: #ffffff;
    --rubber-border: #cfe2d4;
    --rubber-text: #245c38;
    --rubber-shadow: var(--bs-box-shadow-sm);
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    font-size: 16px;
    font-weight: 300;
  }

  .rubbers-page,
  .rubbers-page .card,
  .rubbers-page .table,
  .rubbers-page .form-control,
  .rubbers-page .form-select,
  .rubbers-page .form-label,
  .rubbers-page .nav-link,
  .rubbers-page .alert,
  .rubbers-page .badge,
  .rubbers-page .btn {
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    font-size: 16px;
    font-weight: 300;
  }

  .rubbers-page .page-shell {
    max-width: 1240px;
    margin: 0 auto;
  }

  .rubbers-page .container.py-4 {
    background: var(--rubber-surface);
    border-radius: 1.25rem;
    padding: 2rem;
    margin-top: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--rubber-shadow);
  }

  .rubbers-page .hero-panel {
    background: var(--bs-success-bg-subtle);
    color: var(--bs-success-text-emphasis);
    border-radius: 1.25rem;
    padding: 1.35rem 1.5rem;
    box-shadow: none;
    border: 1px solid var(--bs-success-border-subtle);
    margin-bottom: 1rem;
  }

  .rubbers-page .hero-title {
    font-size: 1.55rem;
    font-weight: 700;
    margin-bottom: 0.35rem;
  }

  .rubbers-page .hero-subtitle {
    margin-bottom: 0;
    color: #4e7c59;
  }

  .rubbers-page .hero-chip-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
  }

  .rubbers-page .hero-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.85rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid var(--bs-success-border-subtle);
    color: var(--bs-success-text-emphasis);
    font-weight: 600;
    white-space: nowrap;
  }

  .rubbers-page .surface-card {
    background: var(--rubber-surface);
    border: 1px solid var(--bs-border-color);
    border-radius: 1.15rem;
    box-shadow: var(--rubber-shadow);
  }

  .rubbers-page .surface-card .card-header,
  .rubbers-page .surface-card .card-footer {
    background: #fff;
    border-color: var(--rubber-border);
  }

  .rubbers-page .lane-tabs {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
  }

  .rubbers-page .lane-tabs .nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
  }

  .rubbers-page .lane-tabs .nav-link {
    border-radius: 999px;
    padding: 0.45rem 1rem;
    font-weight: 600;
    color: var(--bs-success-text-emphasis);
    background: var(--bs-success-bg-subtle);
    border: 1px solid var(--bs-success-border-subtle);
  }

  .rubbers-page .lane-tabs .nav-link.active {
    background: var(--bs-success);
    color: #fff;
    box-shadow: var(--bs-box-shadow-sm);
  }

  .rubbers-page .toolbar-actions {
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
  }

  .rubbers-page .form-control,
  .rubbers-page .input-group-text,
  .rubbers-page .list-group-item {
    border-radius: .85rem;
  }

  .rubbers-page .form-control {
    border: 1px solid #d5ddd8;
    min-height: 46px;
    box-shadow: none;
  }

  .rubbers-page .form-control:focus {
    border-color: #9fcca6;
    box-shadow: 0 0 0 .2rem rgba(25, 135, 84, 0.12);
  }

  .rubbers-page .input-group-text {
    background: var(--bs-success-bg-subtle);
    border: 1px solid #d5ddd8;
    color: var(--rubber-text);
    font-weight: 700;
  }

  .rubbers-page .field-inline {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
  }

  .rubbers-page .field-inline .field-label {
    margin: 0;
    min-width: 92px;
    flex: 0 0 auto;
    white-space: nowrap;
    font-weight: 600;
    color: #3f5f4b;
  }

  .rubbers-page .field-inline .field-control {
    flex: 1 1 auto;
    min-width: 0;
  }

  .rubbers-page .field-inline .field-control .form-control,
  .rubbers-page .field-inline .field-control .input-group {
    width: 100%;
  }

  .rubbers-page .field-inline.compact .field-label {
    min-width: 78px;
  }

  .rubbers-page .search-inline {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
  }

  .rubbers-page .search-inline .form-label {
    flex: 0 0 auto;
    margin: 0;
    white-space: nowrap;
  }

  .rubbers-page .search-inline .input-group {
    flex: 1 1 auto;
    min-width: 0;
  }

  .rubbers-page .member-summary {
    background: var(--bs-success-bg-subtle);
    border: 1px solid var(--bs-success-border-subtle);
    border-radius: .9rem;
    padding: .75rem .9rem;
  }

  .rubbers-page .metric-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .85rem;
  }

  .rubbers-page .metric-card {
    background: #ffffff;
    border: 1px solid var(--bs-success-border-subtle);
    border-radius: 1rem;
    padding: .9rem 1rem;
  }

  .rubbers-page .metric-label {
    display: block;
    color: #5d7568;
    font-size: .95rem;
    margin-bottom: .25rem;
  }

  .rubbers-page .summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .75rem;
  }

  .rubbers-page .summary-card {
    border-radius: 1rem;
    padding: .9rem 1rem;
    color: #183529;
    background: var(--bs-success-bg-subtle);
    border: 1px solid var(--bs-success-border-subtle);
  }

  .rubbers-page .summary-title {
    display: block;
    font-size: .9rem;
    color: #60786c;
    margin-bottom: .15rem;
  }

  .rubbers-page .table-shell {
    padding: 0 .75rem .75rem;
  }

  .table-responsive .table thead.sticky-header th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: var(--bs-success);
    color: #fff;
    box-shadow: inset 0 -1px 0 rgba(0, 0, 0, .05);
  }

  .table td.text-end,
  .table th.text-end {
    font-variant-numeric: tabular-nums;
  }

  .table-hover tbody tr:hover td {
    background-color: rgba(13, 110, 253, .04);
  }

  .table caption {
    color: #6c757d;
    padding-left: .5rem;
  }

  .rubbers-page .table {
    margin-bottom: 0;
  }

  .rubbers-page .table th {
    color: var(--rubber-text);
    font-weight: 700;
  }

  .rubbers-page .table td,
  .rubbers-page .table th {
    border-color: #e7efea;
    vertical-align: middle;
  }

  .rubbers-page .name-cell {
    min-width: 220px;
  }

  .rubbers-page .action-stack {
    display: flex;
    gap: .35rem;
    flex-wrap: wrap;
  }

  .rubbers-page .bottom-backlink {
    display: flex;
    justify-content: center;
    margin-top: 1rem;
  }

  /* Reduce font size for data table section */
  .rubbers-page .data-card .table-toolbar,
  .rubbers-page .data-card .table-shell,
  .rubbers-page .data-card .table,
  .rubbers-page .data-card .table th,
  .rubbers-page .data-card .table td,
  .rubbers-page .data-card .table-toolbar-title,
  .rubbers-page .data-card .table-toolbar-subtitle,
  .rubbers-page .data-card .btn {
    font-size: 14px !important;
    font-weight: 300 !important;
  }

  .rubbers-page .data-card .badge {
    font-size: 12px !important;
    font-weight: 400 !important;
  }

  /* Enhanced Responsive Design */
  @media (max-width: 992px) {
    .form-wrap {
      max-width: 100%;
      padding: 0 1rem;
    }

    .rubbers-page .hero-chip-wrap {
      justify-content: flex-start;
    }

    .rubbers-page .metric-grid,
    .rubbers-page .summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rubbers-page .field-inline {
      flex-direction: column;
      align-items: stretch;
      gap: 0.35rem;
    }

    .rubbers-page .field-inline .field-label {
      min-width: 0;
    }

    .rubbers-page .member-chooser .field-inline.compact {
      flex-direction: row;
      align-items: center;
      gap: 0.75rem;
    }

    .rubbers-page .member-chooser .field-inline.compact .field-label {
      min-width: 78px;
    }

    .rubbers-page .search-inline {
      flex-direction: row;
      align-items: center;
    }

    fieldset {
      padding: 0.75rem 1rem;
    }

    .nav-pills .nav-link {
      font-size: 0.9rem;
      padding: 0.4rem 0.8rem;
    }
  }

  @media (max-width: 768px) {
    .container.py-4 {
      padding: 1rem 0.5rem !important;
    }

    .rubbers-page .lane-toolbar,
    .rubbers-page .toolbar-actions,
    .rubbers-page .table-toolbar {
      flex-direction: column;
      align-items: stretch;
    }

    .rubbers-page .hero-title {
      font-size: 1.3rem;
    }

    h1.h4 {
      font-size: 1.25rem;
      margin-bottom: 1rem;
    }

    .rubbers-page .lane-tabs {
      flex-direction: column;
      align-items: stretch;
      gap: 0.5rem;
    }

    .rubbers-page .lane-tabs .nav {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.5rem;
      width: 100%;
    }

    .rubbers-page .lane-tabs .nav-item {
      width: 100%;
    }

    .rubbers-page .lane-tabs .nav-link {
      width: 100%;
      justify-content: center;
      white-space: nowrap;
    }

    .rubbers-page .lane-label {
      flex: 0 0 auto;
      white-space: nowrap;
    }

    .rubbers-page .lane-tabs .nav-item:first-child {
      grid-column: 1 / -1;
    }

    .nav-pills {
      gap: 0.5rem;
    }

    .ms-auto {
      margin-left: 0 !important;
      margin-top: 1rem;
      justify-content: center;
    }

    .ms-auto .d-flex {
      flex-direction: column;
      width: 100%;
      gap: 0.5rem;
    }

    .form-wrap {
      margin: 0;
      padding: 0;
    }

    fieldset {
      padding: 1rem;
      margin-bottom: 1rem;
    }

    fieldset legend {
      font-size: 1.1rem;
      padding: 0 0.5rem;
      margin-bottom: 0.5rem;
    }

    .row.g-3 {
      gap: 1rem;
    }

    .col-md-3,
    .col-md-9 {
      flex: 0 0 100%;
      max-width: 100%;
    }

    .input-group {
      flex-direction: column;
    }

    .input-group .input-group-text {
      border-bottom-left-radius: 0;
      border-bottom-right-radius: 0;
      border-top: none;
      border-left: none;
      border-right: none;
      justify-content: flex-start;
      padding: 0.5rem 0;
      background: transparent;
      color: #495057;
      font-weight: 600;
    }

    .input-group .form-control {
      border-top-left-radius: 0.375rem;
      border-top-right-radius: 0.375rem;
    }

    .row.row-cols-1.row-cols-sm-2.row-cols-md-3.row-cols-lg-4 {
      gap: 0.75rem;
    }

    .row-cols-lg-4>.col {
      flex: 0 0 calc(50% - 0.375rem);
      max-width: calc(50% - 0.375rem);
    }

    .card-footer {
      flex-direction: column;
      gap: 1rem;
      align-items: stretch !important;
    }

    .card-footer .d-flex {
      justify-content: center;
      gap: 0.5rem;
    }

    .btn {
      min-height: 44px;
      font-size: 0.95rem;
    }

    .btn-sm {
      min-height: 38px;
      font-size: 0.85rem;
    }

    /* Responsive table */
    .table-responsive {
      font-size: 0.85rem;
      margin: 0 -0.5rem;
      padding: 0 0.5rem;
    }

    .table th,
    .table td {
      padding: 0.4rem 0.3rem;
      white-space: nowrap;
    }

    .table th:nth-child(n+6),
    .table td:nth-child(n+6) {
      white-space: normal;
      min-width: 80px;
    }

    .table .d-flex.gap-1 {
      flex-direction: column;
      gap: 0.25rem !important;
    }

    .table .btn-sm {
      font-size: 0.75rem;
      padding: 0.25rem 0.4rem;
      min-height: auto;
    }

    /* Search form responsive */
    .card.mb-4.form-wrap {
      margin: 0 -0.5rem 1rem;
      padding: 0 0.5rem;
    }

    .row.gy-3.gx-3 {
      gap: 1rem;
    }

    .col-md-8,
    .col-md-2 {
      flex: 0 0 100%;
      max-width: 100%;
    }

    .badge.bg-secondary {
      font-size: 0.8rem;
      padding: 0.25rem 0.5rem;
    }

    /* Summary badges */
    .d-flex.flex-wrap.gap-2 {
      gap: 0.5rem !important;
    }

    .badge.p-2 {
      padding: 0.5rem 0.75rem !important;
      font-size: 0.8rem;
    }

    /* Alert messages */
    .alert {
      font-size: 0.9rem;
      padding: 0.75rem 1rem;
      margin: 0 -0.5rem 1rem;
      border-radius: 0;
    }
  }

  @media (max-width: 576px) {
    .container.py-4 {
      padding: 0.5rem 0.25rem !important;
    }

    h1.h4 {
      font-size: 1.1rem;
    }

    fieldset {
      padding: 0.75rem;
    }

    fieldset legend {
      font-size: 1rem;
    }

    .row-cols-lg-4>.col {
      flex: 0 0 100%;
      max-width: 100%;
    }

    .table-responsive {
      font-size: 0.8rem;
      margin: 0 -0.25rem;
      padding: 0 0.25rem;
    }

    .table th,
    .table td {
      padding: 0.3rem 0.2rem;
    }

    .table th:not(:first-child):not(:nth-child(2)):not(:nth-child(3)):not(:nth-child(4)):not(:nth-child(5)),
    .table td:not(:first-child):not(:nth-child(2)):not(:nth-child(3)):not(:nth-child(4)):not(:nth-child(5)) {
      display: none;
    }

    .badge.p-2 {
      padding: 0.4rem 0.6rem !important;
      font-size: 0.75rem;
    }

    .btn {
      font-size: 0.9rem;
      padding: 0.4rem 0.6rem;
    }

    .btn-sm {
      font-size: 0.75rem;
      padding: 0.3rem 0.4rem;
    }
  }

  /* Landscape orientation */
  @media (max-width: 768px) and (orientation: landscape) {
    .container.py-4 {
      padding: 0.5rem !important;
    }

    fieldset {
      padding: 0.75rem;
    }

    .row-cols-lg-4>.col {
      flex: 0 0 calc(33.333% - 0.5rem);
      max-width: calc(33.333% - 0.5rem);
    }
  }

  /* Touch-friendly improvements */
  @media (hover: none) and (pointer: coarse) {
    .table-hover tbody tr:hover td {
      background-color: transparent;
    }

    .btn,
    .form-control,
    .form-select {
      min-height: 44px;
    }

    .list-group-item-action {
      min-height: 44px;
      display: flex;
      align-items: center;
    }
  }

  /* Ensure input groups and numeric inputs can shrink/grow correctly on small screens */
  .rubbers-page .input-group .form-control {
    min-width: 0;
    /* allow flex children to shrink properly */
    width: 100%;
  }

  .rubbers-page .member-chooser .input-group,
  .rubbers-page .row .col>.input-group {
    width: 100%;
  }

  .rubbers-page .search-inline .input-group {
    flex-direction: row;
    flex-wrap: nowrap;
    width: 100%;
  }

  .rubbers-page .search-inline .input-group .input-group-text {
    border: 1px solid #d5ddd8;
    border-right: 0;
    border-radius: .85rem 0 0 .85rem;
    padding: 0.375rem 0.75rem;
    background: var(--bs-success-bg-subtle);
    color: var(--rubber-text);
  }

  .rubbers-page .search-inline .input-group .form-control {
    width: 1%;
    border-radius: 0 .85rem .85rem 0;
  }

  .rubbers-page .num-group .col {
    min-width: 0;
  }
  </style>
</head>

<body class="bg-light rubbers-page">
  <div class="container page-shell">
    <section class="hero-panel">
      <div class="row g-3 align-items-center">
        <div class="col-lg-7">
          <h1 class="hero-title"><i data-lucide="droplet" class="me-2" aria-hidden="true"></i>จัดการข้อมูลยางพารา</h1>
          <p class="hero-subtitle">บันทึกรับซื้อ ติดตามยอดหัก และตรวจสอบข้อมูลประจำรอบล่าสุดได้ในหน้าจอเดียว</p>
        </div>
        <div class="col-lg-5">
          <div class="hero-chip-wrap">
            <span class="hero-chip"><i data-lucide="calendar"
                aria-hidden="true"></i><?php echo e(thai_date_format($latest_round_date)); ?></span>
            <span class="hero-chip"><i data-lucide="check-square"
                aria-hidden="true"></i><?php echo number_format($totalCount); ?> รายการ</span>
          </div>
        </div>
      </div>
    </section>

    <!-- nav: add 'ทั้งหมด' -->
    <nav class="surface-card toolbar-card p-2">
      <div class="lane-toolbar">
        <div class="lane-tabs">
          <span class="lane-label">
            <i data-lucide="droplet" aria-hidden="true"></i>เลือกลานรับยาง
          </span>
          <ul class="nav nav-pills align-items-center small mb-0">
            <li class="nav-item">
              <a class="nav-link <?php echo ($currentLan === 'all') ? 'active' : ''; ?>" href="rubbers.php?lan=all">
                ทั้งหมด
              </a>
            </li>
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <li class="nav-item">
              <a class="nav-link <?php echo ($currentLan === $i) ? 'active' : ''; ?>"
                href="rubbers.php?lan=<?php echo $i; ?>">
                ลานที่ <?php echo $i; ?>
              </a>
            </li>
            <?php endfor; ?>
          </ul>
        </div>
        <div class="toolbar-actions">

        </div>
      </div>
    </nav>

    <?php if ($msg): ?>
    <div class="alert alert-success py-2"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
    <div class="alert alert-danger py-2"><?php echo e(implode(' | ', $errors)); ?></div>
    <?php endif; ?>

    <?php if ($currentLan !== 'all'): ?>
    <form method="post" autocomplete="off" class="card mb-4 form-wrap surface-card" id="rubberForm">
      <div class="card-header d-flex justify-content-between align-items-center small">
        <span>ลาน:
          <?php echo ($currentLan === 'all') ? 'ทั้งหมด (เพิ่มใช้ลาน 1 เริ่มต้น)' : 'ลาน ' . (int)$currentLan; ?></span>
        <span class="text-muted">
          <?php if (!empty($form['ru_id'])): ?>
          <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>แก้ไข #<?php echo (int)$form['ru_id']; ?>
          <?php else: ?>
          เพิ่มรายการใหม่
          <?php endif; ?>
        </span>
      </div>
      <div class="card-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="lan" value="<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>">
        <!-- keep lane/all -->
        <!-- changed: always include ru_member_id, prefill if selected via GET -->
        <input type="hidden" id="ru_member_id" name="ru_member_id"
          value="<?php echo !empty($memberSelectedRow['mem_id']) ? (int)$memberSelectedRow['mem_id'] : ''; ?>">
        <?php if (!empty($form['ru_id'])): ?>
        <input type="hidden" name="ru_id" value="<?php echo (int)$form['ru_id']; ?>">
        <?php endif; ?>
        <input type="hidden" name="ru_lan"
          value="<?php echo !empty($form['ru_id']) ? (int)$form['ru_lan'] : ($currentLan === 'all' ? 1 : (int)$currentLan); ?>">

        <?php if (empty($form['ru_id'])): ?>
        <fieldset>
          <legend>เลือกสมาชิก</legend>
          <!-- member chooser (unchanged logic, only wrapper) -->
          <div class="member-chooser">
            <div class="field-inline compact">
              <label class="field-label" for="memberSearch">ค้นหา</label>
              <div class="field-control">
                <input id="memberSearch" type="text" class="form-control" placeholder="ชื่อ / เลขที่ / กลุ่ม / ชั้น">
              </div>
            </div>
            <ul id="memberResults" class="list-group mt-1" hidden></ul>
            <div id="memberSelected" class="form-text mt-2 member-summary"
              <?php if (empty($memberSelectedRow)) echo 'hidden'; ?>>
              <?php if (!empty($memberSelectedRow)): ?>
              ใช้สมาชิก: <span class="tag">#<?php echo (int)$memberSelectedRow['mem_id']; ?></span>
              <?php echo e($memberSelectedRow['mem_fullname']); ?> |
              กลุ่ม: <?php echo e($memberSelectedRow['mem_group']); ?> |
              เลขที่: <?php echo e($memberSelectedRow['mem_number']); ?> |
              ชั้น: <?php echo e($memberSelectedRow['mem_class']); ?>
              <button type="button" id="clearMember" class="btn btn-sm btn-danger">เปลี่ยน</button>
              <?php endif; ?>
            </div>
          </div>
        </fieldset>
        <?php endif; ?>
        <hr>
        <fieldset>
          <legend>ข้อมูลพื้นฐาน</legend>
          <div class="row g-3">
            <div class="col-12 col-md-3">
              <div class="field-inline">
                <label class="field-label" for="ru_date">วันที่</label>
                <div class="field-control">
                  <input id="ru_date" type="date" name="ru_date" required class="form-control"
                    value="<?php echo e($form['ru_date']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-md-3">
              <div class="field-inline">
                <label class="field-label" for="ru_group">กลุ่ม</label>
                <div class="field-control">
                  <input id="ru_group" name="ru_group" required class="form-control"
                    <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?>
                    value="<?php echo e($form['ru_group']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-md-3">
              <div class="field-inline">
                <label class="field-label" for="ru_number">เลขที่</label>
                <div class="field-control">
                  <input id="ru_number" name="ru_number" required class="form-control"
                    <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?>
                    value="<?php echo e($form['ru_number']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-md-3">
              <div class="field-inline">
                <label class="field-label" for="ru_class">ชั้น</label>
                <div class="field-control">
                  <input id="ru_class" name="ru_class" required class="form-control"
                    <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?>
                    value="<?php echo e($form['ru_class']); ?>">
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3 my-2">
            <div class="col-12 col-lg-9">
              <div class="field-inline">
                <label class="field-label" for="ru_fullname">ชื่อ-สกุล</label>
                <div class="field-control">
                  <input id="ru_fullname" name="ru_fullname" required class="form-control"
                    <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?>
                    value="<?php echo e($form['ru_fullname']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-3">
              <div class="field-inline">
                <label class="field-label" for="ru_quantity">ปริมาณ</label>
                <div class="field-control">
                  <input name="ru_quantity" id="ru_quantity" required inputmode="decimal" step="0.01" min="0" class="form-control text-end"
                    value="<?php echo e($form['ru_quantity']); ?>">
                </div>
              </div>
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend>การหัก</legend>
          <div class="row g-3 num-group">
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_hoon">หุ้น</label>
                <div class="field-control">
                  <input id="ru_hoon" name="ru_hoon" required inputmode="decimal" step="0.01" min="0" class="form-control text-end"
                    value="<?php echo e($form['ru_hoon']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_loan">เงินกู้</label>
                <div class="field-control">
                  <input id="ru_loan" name="ru_loan" required inputmode="decimal" step="0.01" min="0" class="form-control text-end"
                    value="<?php echo e($form['ru_loan']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_shortdebt">หนี้สั้น</label>
                <div class="field-control">
                  <input id="ru_shortdebt" name="ru_shortdebt" required inputmode="decimal" step="0.01" min="0"
                    class="form-control text-end" value="<?php echo e($form['ru_shortdebt']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_deposit">เงินฝาก</label>
                <div class="field-control">
                  <input id="ru_deposit" name="ru_deposit" required inputmode="decimal" step="0.01" min="0" class="form-control text-end"
                    value="<?php echo e($form['ru_deposit']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_tradeloan">กู้ซื้อขาย</label>
                <div class="field-control">
                  <input id="ru_tradeloan" name="ru_tradeloan" required inputmode="decimal" step="0.01" min="0"
                    class="form-control text-end" value="<?php echo e($form['ru_tradeloan']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_insurance">ประกันภัย</label>
                <div class="field-control">
                  <input id="ru_insurance" name="ru_insurance" required inputmode="decimal" step="0.01" min="0"
                    class="form-control text-end" value="<?php echo e($form['ru_insurance']); ?>">
                </div>
              </div>
            </div>
          </div>
          <input type="hidden" name="ru_savedate" value="<?php echo e($form['ru_savedate']); ?>">
        </fieldset>
        <hr>
        <fieldset>
          <legend>ยอดเงินคงเหลือที่ได้รับ</legend>
          <div class="alert alert-info py-2">
            <?php
                // ดึงราคาล่าสุด
                $latestPrice = 0.00;
                $res = $db->query("SELECT pr_price FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1");
                if ($res && ($r = $res->fetch_assoc())) { $latestPrice = (float)$r['pr_price']; }
                $qty = isset($form['ru_quantity']) ? (float)$form['ru_quantity'] : 0;
                $amount = ($qty > 0 && $latestPrice > 0) ? $qty * $latestPrice : 0;

                // รวมยอดหักเริ่มต้น
                $deductTotal =
                  (float)$form['ru_hoon'] +
                  (float)$form['ru_loan'] +
                  (float)$form['ru_shortdebt'] +
                  (float)$form['ru_deposit'] +
                  (float)$form['ru_tradeloan'] +
                  (float)$form['ru_insurance'];

                // ค่าเริ่มต้นสำหรับแสดงผล (ถ้ามีค่าจากฐานข้อมูลให้ใช้ค่านั้น)
                $initialRuValue  = isset($form['ru_value'])    ? (float)$form['ru_value']    : $amount;
                $initialExpend   = isset($form['ru_expend'])   ? (float)$form['ru_expend']   : $deductTotal;
                $initialNetValue = isset($form['ru_netvalue']) ? (float)$form['ru_netvalue'] : ($initialRuValue - $initialExpend);
                ?>
            <p class="mb-3 small text-muted">
              ราคาล่าสุด: <span id="latestPrice"
                data-price="<?php echo $latestPrice; ?>"><?php echo number_format($latestPrice, 2); ?></span> บาท/กก.
            </p>
            <div class="metric-grid">
              <div class="metric-card">
                <span class="metric-label">มูลค่ายาง = ราคา x ปริมาณ</span>
                <strong id="ruValue" class="metric-value"><?php echo number_format($initialRuValue, 2); ?></strong>
              </div>
              <div class="metric-card">
                <span class="metric-label">ยอดหักรวม</span>
                <strong id="ruExpend" class="metric-value"><?php echo number_format($initialExpend, 2); ?></strong>
              </div>
              <div class="metric-card">
                <span class="metric-label">ยอดสุทธิที่ได้รับ</span>
                <strong id="ruNetValue" class="metric-value"><?php echo number_format($initialNetValue, 2); ?></strong>
              </div>
            </div>
            <script>
            (function() {
              const priceEl = document.getElementById('latestPrice');
              const price = parseFloat(priceEl?.dataset.price || '0') || 0;

              const qtyInput = document.getElementById('ru_quantity');
              const fields = ['ru_hoon', 'ru_loan', 'ru_shortdebt', 'ru_deposit', 'ru_tradeloan', 'ru_insurance'];

              const elValue = document.getElementById('ruValue');
              const elExpend = document.getElementById('ruExpend');
              const elNet = document.getElementById('ruNetValue');

              function num(v) {
                return parseFloat((v || '0').toString().replace(/,/g, '')) || 0;
              }

              function fmt(n) {
                return n.toLocaleString('en-US', {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
                });
              }

              function calc() {
                const q = qtyInput ? num(qtyInput.value) : 0;
                const ru_value = q * price;
                let ru_expend = 0;
                fields.forEach(id => {
                  const el = document.getElementById(id);
                  if (el) ru_expend += num(el.value);
                });
                const ru_net = ru_value - ru_expend;

                if (elValue) elValue.textContent = fmt(ru_value);
                if (elExpend) elExpend.textContent = fmt(ru_expend);
                if (elNet) elNet.textContent = fmt(ru_net);
              }

              if (qtyInput) qtyInput.addEventListener('input', calc);
              fields.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('input', calc);
              });
              calc();
            })();
            </script>
          </div>
        </fieldset>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
          <?php if (!empty($form['ru_id'])): ?>
          <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>แก้ไข #<?php echo (int)$form['ru_id']; ?>
          <?php else: ?>
          สร้างรายการใหม่
          <?php endif; ?>
        </small>
        <div>
          <button type="button" id="btnSave" class="btn btn-primary px-4">
            <i data-lucide="floppy2" class="me-1" aria-hidden="true"></i>บันทึก
          </button>
          <?php if (!empty($form['ru_id'])): ?>
          <a href="rubbers.php?lan=<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>"
            class="btn btn-outline-secondary ms-2">
            <i data-lucide="rotate-ccw" class="me-1" aria-hidden="true"></i>ยกเลิก
          </a>
          <?php endif; ?>
        </div>
      </div>
    </form>
    <?php else: ?>
    <div class="card mb-4 form-wrap surface-card">
      <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <span class="small fw-semibold">ค้นหาและกรองข้อมูลทุกลาน</span>
        <span class="small text-muted">ผลลัพธ์ทั้งหมด <?php echo number_format($totalCount); ?> รายการ</span>
      </div>
      <div class="card-body">
        <form method="get" class="row gy-3 gx-3 align-items-end">
          <input type="hidden" name="lan" value="all">
          <div class="col-12 col-lg-5">
            <label class="form-label" for="rubberSearchInput">คำค้น</label>
            <div class="input-group">
              <span class="input-group-text">ค้นหา</span>
              <input type="text" id="rubberSearchInput" name="search" class="form-control"
                value="<?php echo e($search); ?>" placeholder="เช่น กลุ่ม 1, 001, นายเอ, ป.6">
              <?php if ($search !== '' || $classFilter !== 'all' || $date_from !== $latest_round_date || $date_to !== $latest_round_date): ?>
              <a class="btn btn-outline-secondary" href="rubbers.php?lan=all" title="ล้างตัวกรอง">
                <i data-lucide="x-circle" class="me-1" aria-hidden="true"></i>ล้าง
              </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label" for="date_from">วันที่เริ่ม</label>
            <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo e($date_from); ?>">
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label" for="date_to">วันที่สิ้นสุด</label>
            <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo e($date_to); ?>">
          </div>
          <div class="col-12 col-lg-2">
            <label class="form-label" for="classFilter">ประเภท</label>
            <select name="class" id="classFilter" class="form-select">
              <option value="all" <?php echo $classFilter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
              <option value="member" <?php echo $classFilter === 'member' ? 'selected' : ''; ?>>สมาชิก</option>
              <option value="general" <?php echo $classFilter === 'general' ? 'selected' : ''; ?>>เกษตรกรทั่วไป</option>
            </select>
          </div>
          <div class="col-12 col-lg-1 d-grid">
            <button type="submit" class="btn btn-primary">
              <i data-lucide="check" class="me-1" aria-hidden="true"></i>ตกลง
            </button>
          </div>
        </form>

        <div class="mt-3 summary-grid">
          <div class="summary-card">
            <span class="summary-title">ปริมาณรวม</span>
            <span class="summary-value"><?php echo number_format($summary['sumQty'], 2); ?> กก.</span>
          </div>
          <div class="summary-card">
            <span class="summary-title">มูลค่ารวม</span>
            <span class="summary-value"><?php echo number_format($summary['sumValue'], 2); ?> ฿</span>
          </div>
          <div class="summary-card">
            <span class="summary-title">ยอดหักรวม</span>
            <span class="summary-value"><?php echo number_format($summary['sumExpend'], 2); ?> ฿</span>
          </div>
          <div class="summary-card">
            <span class="summary-title">ยอดสุทธิ</span>
            <span class="summary-value"><?php echo number_format($summary['sumNet'], 2); ?> ฿</span>
          </div>
        </div>
        <div class="mt-2 small text-muted">
          เงื่อนไข:
          <?php echo $search ? 'คำค้น="' . e($search) . '" ' : 'ทั้งหมด '; ?>
          <span class="ms-1">
            <?php echo $date_from ? e(thai_date_format($date_from)) : '-'; ?>
            ถึง <?php echo $date_to ? e(thai_date_format($date_to)) : '-'; ?>
          </span>
          <span class="ms-1">
            <?php echo $classFilter === 'member' ? 'เฉพาะสมาชิก' : ($classFilter === 'general' ? 'เฉพาะเกษตรกรทั่วไป' : 'ทุกประเภท'); ?>
          </span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ตาราง -->
    <div class="surface-card data-card">
      <div class="table-toolbar">
        <div class="text-center d-flex justify-content-center align-items-center gap-3 mt-2">
          <div style="font-size: 16px;">
            ผลลัพธ์ <?php echo number_format($totalCount); ?> รายการ
            <?php if ($totalPages > 1): ?>
            <span class="text-muted">| หน้า <?php echo number_format($page); ?>/<?php echo number_format($totalPages); ?></span>
            <?php endif; ?>
          </div>
          <div style="font-size: 16px;">
            <?php echo ($currentLan === 'all') ? 'แสดงข้อมูลทุกลาน' : 'แสดงข้อมูลลาน '.(int)$currentLan; ?></div>
        </div>
        <div class="text-end mb-2 p-2">
          <?php if (!empty($rows)): ?>
          <a href="export_rubbers_excel.php?<?php echo $exportQuery; ?>" class="btn btn-sm btn-outline-success">
            <i data-lucide="file-text" class="me-1" aria-hidden="true"></i>Excel
          </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="table-shell">
        <div class="table-responsive rounded-3">
          <table class="table table-sm table-hover align-middle caption-top">
            <thead class="table-light sticky-header">
              <tr>
                <th>ID</th>

                <th>วันที่</th>
                <th>ลาน</th>
                <th>กลุ่ม</th>
                <th>เลขที่</th>
                <th>ชื่อ-สกุล</th>
                <th class="text-end">ปริมาณ</th>
                <th class="text-end">หุ้น</th>
                <th class="text-end">เงินกู้</th>
                <th class="text-end">หนี้สั้น</th>
                <th class="text-end">เงินฝาก</th>
                <th class="text-end">ลูกหนี้การค้า</th>
                <th class="text-end">ประกันภัย</th>
                <th class="text-center">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
              <tr>
                <td colspan="17" class="text-center text-muted py-4">ยังไม่มีข้อมูล</td>
              </tr>
              <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?php echo e($r['ru_id']); ?></td>

                <td><?php echo thai_date_format($r['ru_date']); ?></td>
                <td><?php echo e($r['ru_lan']); ?></td>
                <td><?php echo e($r['ru_group']); ?></td>
                <td><?php echo e($r['ru_number']); ?></td>
                <td class="name-cell">
                  <?php echo e($r['ru_fullname']); ?>
                  <?php if ($r['ru_class'] == 'general'): ?>
                  <span class="badge bg-danger">เกษตรกร</span>
                  <?php elseif ($r['ru_class'] == 'member'): ?>
                  <span class="badge bg-success">สมาชิก</span>
                  <?php else: ?>
                  <span class="badge bg-secondary">ไม่ระบุ</span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?php echo number_format((float)$r['ru_quantity'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float)$r['ru_hoon'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float)$r['ru_loan'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float)$r['ru_shortdebt'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float)$r['ru_deposit'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float)$r['ru_tradeloan'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float)$r['ru_insurance'], 2); ?></td>
                <td>
                  <div class="action-stack">
                    <a href="rubbers.php?lan=<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>&action=edit&id=<?php echo (int)$r['ru_id']; ?>"
                      class="btn btn-sm btn-warning" title="แก้ไข" aria-label="แก้ไข">
                      <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
                    </a>
                    <form method="post" onsubmit="return confirm('ลบรายการนี้?');" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="lan"
                        value="<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>">
                      <input type="hidden" name="ru_id" value="<?php echo (int)$r['ru_id']; ?>">
                      <button type="submit" class="btn btn-sm btn-danger" title="ลบ" aria-label="ลบ">
                        <i data-lucide="trash-2" aria-hidden="true"></i>
                      </button>
                    </form>
                    <?php if ($hasDompdf): ?>
                    <a href="export_rubber_pdf.php?ru_id=<?php echo (int)$r['ru_id']; ?>" target="_blank"
                      class="btn btn-sm btn-outline-dark" title="ส่งออก PDF" aria-label="ส่งออก PDF">
                      <i data-lucide="file-text" aria-hidden="true"></i>
                    </a>
                    <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled
                      title="โปรดติดตั้ง dompdf ด้วย Composer ก่อน" aria-label="PDF ไม่พร้อมใช้งาน">
                      <i data-lucide="file-text" aria-hidden="true"></i>
                    </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($totalPages > 1): ?>
      <?php
        $fromItem = $totalCount > 0 ? (($page - 1) * $perPage + 1) : 0;
        $toItem = min($page * $perPage, $totalCount);
        $pageWindowStart = max(1, $page - 2);
        $pageWindowEnd = min($totalPages, $page + 2);
      ?>
      <div class="px-3 pb-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <div class="small text-muted">
          แสดงรายการ <?php echo number_format($fromItem); ?>-<?php echo number_format($toItem); ?>
          จากทั้งหมด <?php echo number_format($totalCount); ?> รายการ
        </div>
        <nav aria-label="Pagination">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $page > 1 ? 'rubbers.php?' . http_build_query(array_merge($exportBaseParams, ['page' => $page - 1])) : '#'; ?>">ก่อนหน้า</a>
            </li>
            <?php if ($pageWindowStart > 1): ?>
            <li class="page-item"><a class="page-link" href="rubbers.php?<?php echo http_build_query(array_merge($exportBaseParams, ['page' => 1])); ?>">1</a></li>
            <?php if ($pageWindowStart > 2): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <?php endif; ?>
            <?php for ($p = $pageWindowStart; $p <= $pageWindowEnd; $p++): ?>
            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
              <a class="page-link" href="rubbers.php?<?php echo http_build_query(array_merge($exportBaseParams, ['page' => $p])); ?>"><?php echo number_format($p); ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($pageWindowEnd < $totalPages): ?>
            <?php if ($pageWindowEnd < $totalPages - 1): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <li class="page-item"><a class="page-link" href="rubbers.php?<?php echo http_build_query(array_merge($exportBaseParams, ['page' => $totalPages])); ?>"><?php echo number_format($totalPages); ?></a></li>
            <?php endif; ?>
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $page < $totalPages ? 'rubbers.php?' . http_build_query(array_merge($exportBaseParams, ['page' => $page + 1])) : '#'; ?>">ถัดไป</a>
            </li>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>



    <?php if ($currentLan !== 'all'): ?>
    <!-- added: inline JS for member search/selection (แสดงเฉพาะโหมดเพิ่ม/แก้ไข) -->
    <script>
    (function() {
      const inCreateMode = <?php echo json_encode(empty($form['ru_id'])); ?>;
      if (!inCreateMode) return;

      const q = document.getElementById('memberSearch');
      const list = document.getElementById('memberResults');
      const selectedBox = document.getElementById('memberSelected');
      const clearBtn = document.getElementById('clearMember');
      const hid = document.getElementById('ru_member_id');

      const fGroup = document.getElementById('ru_group');
      const fNumber = document.getElementById('ru_number');
      const fName = document.getElementById('ru_fullname');
      const fClass = document.getElementById('ru_class');
      // new: reference quantity field for focusing
      const fQty = document.getElementById('ru_quantity');

      let t = null;

      function hideList() {
        list.hidden = true;
        list.innerHTML = '';
      }

      function lockFields(lock) {
        [fGroup, fNumber, fName, fClass].forEach(el => {
          if (lock) el.setAttribute('readonly', '');
          else el.removeAttribute('readonly');
        });
      }

      function renderSelected(m) {
        selectedBox.hidden = false;
        selectedBox.innerHTML =
          `ใช้สมาชิก: <span class="tag">#${m.mem_id}</span> ${m.mem_fullname} | กลุ่ม: ${m.mem_group} | เลขที่: ${m.mem_number} | ชั้น: ${m.mem_class} <button type="button" id="clearMember" class="btn btn-sm btn-danger">เปลี่ยน</button>`;
        // re-bind clear after replacing innerHTML
        selectedBox.querySelector('#clearMember').addEventListener('click', () => {
          hid.value = '';
          lockFields(false);
          selectedBox.hidden = true;
        });
      }

      function pick(m) {
        hid.value = m.mem_id;
        fGroup.value = m.mem_group;
        fNumber.value = m.mem_number;
        fName.value = m.mem_fullname;
        fClass.value = m.mem_class;
        lockFields(true);
        renderSelected(m);
        q.value = '';
        hideList();
        // new: focus quantity after member selection
        if (fQty) {
          fQty.focus();
          fQty.select();
        }
      }

      function search(term) {
        if (!term || term.length < 2) {
          hideList();
          return;
        }
        fetch('members_search.php?q=' + encodeURIComponent(term))
          .then(r => r.ok ? r.json() : [])
          .then(rows => {
            if (!Array.isArray(rows) || rows.length === 0) {
              hideList();
              return;
            }
            list.innerHTML = rows.map(m => `
            <li data-item='${JSON.stringify(m).replace(/'/g, "&apos;")}' class="list-group-item list-group-item-action">
              <strong>${m.mem_fullname}</strong>
              <div class="small">#${m.mem_id} | กลุ่ม: ${m.mem_group} | เลขที่: ${m.mem_number} | ชั้น: ${m.mem_class}</div>
            </li>`).join('');
            list.hidden = false;
          })
          .catch(() => hideList());
      }

      q && q.addEventListener('input', (e) => {
        clearTimeout(t);
        t = setTimeout(() => search(e.target.value.trim()), 250);
      });

      list.addEventListener('click', (e) => {
        const li = e.target.closest('li');
        if (!li) return;
        try {
          const m = JSON.parse(li.getAttribute('data-item').replace(/&apos;/g, "'"));
          pick(m);
        } catch (_) {}
      });

      document.addEventListener('click', (e) => {
        if (!list.contains(e.target) && e.target !== q) hideList();
      });

      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          hid.value = '';
          lockFields(false);
          selectedBox.hidden = true;
        });
      }

      // new: if member already preselected from server, focus quantity
      if (hid.value && fQty) {
        setTimeout(() => {
          fQty.focus();
          fQty.select();
        }, 50);
      }
    })();
    </script>
    <?php endif; ?>

    <!-- new: prevent Enter submit; only save button triggers submit -->
    <script>
    (function() {
      const form = document.getElementById('rubberForm');
      const saveBtn = document.getElementById('btnSave');
      if (!form || !saveBtn) return;
      let allowSubmit = false;

      // block Enter key from submitting form
      form.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target && e.target.tagName !== 'TEXTAREA') {
          e.preventDefault();
          e.stopPropagation();
        }
      }, true);

      // some browsers may still emit submit; only allow the Save button
      form.addEventListener('submit', function(e) {
        if (!allowSubmit) {
          e.preventDefault();
          return;
        }
        allowSubmit = false;
      }, true);

      // only allow submit via Save button (with HTML5 validation)
      saveBtn.addEventListener('click', function() {
        if (form.reportValidity && !form.reportValidity()) return;
        allowSubmit = true;
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
      });
    })();
    </script>

  </div><!-- /container -->
  <?php include 'footer.php'; ?>
  <script>
  (function() {
    function ensureEditIcon() {
      if (typeof lucide === 'undefined' || !lucide) return;
      var candidates = ['edit-2', 'edit', 'pencil', 'edit-3'];
      var found = null;
      for (var i = 0; i < candidates.length; i++) {
        if (lucide.icons && lucide.icons[candidates[i]]) {
          found = candidates[i];
          break;
        }
      }
      if (!found) return; // nothing to do
      // replace any data-lucide that points to a missing edit icon
      document.querySelectorAll('[data-lucide]').forEach(function(el) {
        var name = el.getAttribute('data-lucide');
        if (name === 'edit' || name === 'edit-2' || name === 'edit-3' || name === 'pencil') {
          if (name !== found) el.setAttribute('data-lucide', found);
        }
      });
      // recreate icons for swapped elements
      try {
        lucide.createIcons();
      } catch (e) {
        console && console.error && console.error('lucide.createIcons() failed', e);
      }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ensureEditIcon);
    else ensureEditIcon();
  })();
  </script>

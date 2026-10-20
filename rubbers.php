<?php
ob_start();
require_once __DIR__ . '/functions.php';

// ตรวจสอบการล็อกอิน - ถ้ายังไม่ล็อกอินให้ redirect ไปหน้า login
if (!is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$db = db();
ensure_weighing_schema($db);
$errors = [];
$msg = $_GET['msg'] ?? '';
$csrf = csrf_token();
$default_saveby = $_SESSION['user_name'] ?? 'เจ้าหน้าที่';
$today = date('Y-m-d');

// add: dompdf availability check
$hasDompdf = file_exists(__DIR__ . '/vendor/autoload.php');

// new: support lan=all
$lanParam = $_GET['lan'] ?? ($_POST['lan'] ?? 'all');
if ($lanParam === 'all') {
  $currentLan = 'all';
} else {
  $currentLan = (int)$lanParam;
  if (!in_array($currentLan, [1, 2, 3, 4], true)) $currentLan = 1;
}

// new: search params and pagination controls
$search = trim((string)($_GET['search'] ?? ''));
$workflowStatus = (string)($_GET['status'] ?? 'pending');
if (!in_array($workflowStatus, ['all','pending','completed','legacy'], true)) $workflowStatus = 'pending';
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

// Empty date filters include outstanding work across all price rounds.

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

$linkedWeighing = !empty($form['ru_id']) ? weighing_for_rubber($db, (int)$form['ru_id']) : null;
$receiptInfo = !empty($form['ru_id']) ? rubber_receipt_issued($db, (int)$form['ru_id']) : null;
$receiptEditLocked = $receiptInfo !== null && !is_admin();
if ($linkedWeighing && $linkedWeighing['deductions_saved_at'] === null) {
  foreach (rubber_deduction_fields() as $field) $form[$field] = '';
}

// จัดการ POST: create/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $errors[] = 'โทเค็นไม่ถูกต้อง';
  } else {
    $action = $_POST['action'] ?? '';
    $mutationId = (int)($_POST['ru_id'] ?? 0);
    if ($action === 'delete' && rubber_receipt_issued($db, $mutationId)) { http_response_code(409); exit('รายการที่ออกใบเสร็จแล้วไม่สามารถลบได้ ให้ admin ดำเนินการแก้ไข'); }
    if (in_array($action, ['save','delete'], true) && $mutationId > 0 && rubber_receipt_issued($db, $mutationId) && !is_admin()) {
      http_response_code(403);
      exit('รายการนี้ออกใบเสร็จแล้ว เฉพาะ admin เท่านั้นที่แก้ไขได้');
    }

    if ($action === 'delete') {
      $id = (int)($_POST['ru_id'] ?? 0);
      if ($id > 0 && weighing_for_rubber($db, $id)) {
        $errors[] = 'รายการนี้เชื่อมกับผลชั่ง ไม่สามารถลบจากหน้ายอดหักได้';
      } elseif ($id > 0) {
        $st = $db->prepare("DELETE FROM tbl_rubber WHERE ru_id=?");
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        header('Location: rubbers.php?lan=' . ($currentLan === 'all' ? 'all' : (int)$currentLan) . '&msg=' . urlencode('ลบรายการแล้ว'));
        exit;
      }
      $errors[] = 'ระบุรายการที่จะลบไม่ถูกต้อง';
    } elseif ($action === 'save') {
      $postedId = (int)($_POST['ru_id'] ?? 0);
      $postedWeighing = $postedId > 0 ? weighing_for_rubber($db, $postedId) : null;
      if ($postedId <= 0) $errors[] = 'กรุณานำรายการจากหน้าชั่งน้ำหนักมาใส่ยอดหัก';
      if ($postedWeighing) {
        // Never accept source details or weight from the browser for weighed purchases.
        $sourceStmt = $db->prepare('SELECT r.* FROM tbl_rubber r WHERE r.ru_id = ?');
        $sourceStmt->bind_param('i', $postedId);
        $sourceStmt->execute();
        $sourceRow = $sourceStmt->get_result()->fetch_assoc();
        $sourceStmt->close();
        foreach (['ru_date','ru_lan','ru_group','ru_number','ru_fullname','ru_class'] as $sourceKey) {
          $_POST[$sourceKey] = $sourceRow[$sourceKey];
        }
        $_POST['ru_quantity'] = $postedWeighing['net_weight'];
        $linkedWeighing = $postedWeighing;
      }
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
      $fieldsNum = ['ru_quantity'];
      try {
        $data = array_merge($data, normalize_rubber_deductions($_POST));
      } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage();
        foreach (rubber_deduction_fields() as $field) $data[$field] = '0.00';
      }

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
        if ($flt === false || $flt < 0) {
          $errors[] = "ต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป: {$f}";
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
      if ($postedWeighing) $latestPrice = (float)$postedWeighing['price_per_kg'];
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
          $db->begin_transaction();
          try {
          lock_rubber_for_edit($db, $id, is_admin());
          if ($postedWeighing) $data = refresh_weighed_financial_data($db, $id, $data);
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
          if ($postedWeighing) mark_weighing_deductions_saved($db, $id, $data['ru_saveby']);
          if (is_admin()) record_receipt_admin_edit($db, $id, $data['ru_saveby']);
          $db->commit();
          } catch (Throwable $error) {
            $db->rollback();
            if ($error instanceof DomainException) { http_response_code(403); exit($error->getMessage()); }
            throw $error;
          }
          $lanRedirect = ($lanParam === 'all') ? 'all' : (int)$data['ru_lan'];
          header('Location: rubbers.php?lan=' . $lanRedirect . ($postedWeighing ? '&date_from=' . urlencode($postedWeighing['round_date']) . '&date_to=' . urlencode($postedWeighing['round_date']) : '') . '&msg=' . urlencode('บันทึกการแก้ไขแล้ว'));
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
  $conds[] = '(ru_saveby = ? OR ru_saveby = ? OR EXISTS (SELECT 1 FROM tbl_wang_weighing q WHERE q.ru_id = tbl_rubber.ru_id))';
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

// Counts use the same lane, round, search and access filters, before status filtering.
$baseWhereSql = $conds ? ' WHERE ' . implode(' AND ', $conds) : '';
$statusSql = "SELECT COUNT(*) AS total,
    COALESCE(SUM(EXISTS (SELECT 1 FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id AND q.deductions_saved_at IS NULL)),0) AS pending,
    COALESCE(SUM(EXISTS (SELECT 1 FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id AND q.deductions_saved_at IS NOT NULL)),0) AS completed,
    COALESCE(SUM(NOT EXISTS (SELECT 1 FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id)),0) AS legacy
    FROM tbl_rubber" . $baseWhereSql;
$statusStmt = $db->prepare($statusSql);
if ($types !== '') $statusStmt->bind_param($types, ...$binds);
$statusStmt->execute();
$workflowCounts = array_map('intval', $statusStmt->get_result()->fetch_assoc());
$statusStmt->close();
if ($workflowStatus === 'pending') $conds[] = 'EXISTS (SELECT 1 FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id AND q.deductions_saved_at IS NULL)';
if ($workflowStatus === 'completed') $conds[] = 'EXISTS (SELECT 1 FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id AND q.deductions_saved_at IS NOT NULL)';
if ($workflowStatus === 'legacy') $conds[] = 'NOT EXISTS (SELECT 1 FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id)';
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

$listSql = "SELECT tbl_rubber.*, (SELECT z.issued_at FROM tbl_rubber_receipts z WHERE z.ru_id=tbl_rubber.ru_id) AS receipt_issued_at, (SELECT q.weighing_id FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id) AS linked_weighing_id, (SELECT q.weighed_by FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id) AS weighed_by, (SELECT q.deductions_saved_by FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id) AS deductions_saved_by, (SELECT q.deductions_saved_at FROM tbl_wang_weighing q WHERE q.ru_id=tbl_rubber.ru_id) AS deductions_saved_at FROM tbl_rubber" . $whereSql . " ORDER BY ru_date DESC, ru_id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
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
  'status' => $workflowStatus,
  'lan' => ($currentLan === 'all' ? 'all' : $currentLan),
  'search' => $search,
  'date_from' => $date_from,
  'date_to' => $date_to,
  'class' => $classFilter,
];
$exportQuery = http_build_query(array_filter($exportBaseParams, fn($v) => $v !== ''));
require_once __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/rubbers.css">
<div class="rubbers-page">
  <div class="container page-shell">
    <section class="hero-panel">
      <div class="row g-3 align-items-center">
        <div class="col-lg-7">
          <h1 class="hero-title"><i data-lucide="droplet" class="me-2" aria-hidden="true"></i>บันทึกยอดหัก</h1>
          <p class="hero-subtitle">ดึงน้ำหนักสุทธิจากการชั่ง แล้วบันทึกยอดหักของแต่ละรายการ</p>
        </div>
        <div class="col-lg-5">
          <div class="hero-chip-wrap">
            <span class="hero-chip"><i data-lucide="calendar"
                aria-hidden="true"></i>คิวจากการชั่ง</span>
            <span class="hero-chip"><i data-lucide="check-square"
                aria-hidden="true"></i><?php echo number_format($totalCount); ?> รายการ</span>
          </div>
        </div>
      </div>
    </section>

    <div class="row g-3 mb-3 workflow-grid" aria-label="สถานะบันทึกยอดหัก">
      <?php foreach (['pending'=>['ชั่งแล้ว รอบันทึกยอดหัก','pending'], 'completed'=>['บันทึกยอดหักแล้ว','completed'], 'legacy'=>['รายการเดิม','legacy'], 'all'=>['ทั้งหมด','total']] as $key=>$card): ?>
        <div class="col-6 col-lg-3"><a class="workflow-card <?php echo $workflowStatus === $key ? 'is-active' : ''; ?>" <?php echo $workflowStatus === $key ? 'aria-current="page"' : ''; ?> href="rubbers.php?<?php echo e(http_build_query(array_merge($exportBaseParams, ['status'=>$key]))); ?>"><span class="workflow-label"><?php echo e($card[0]); ?></span><span><strong class="workflow-number"><?php echo number_format($workflowCounts[$card[1]]); ?></strong><span class="workflow-unit">รายการ</span></span></a></div>
      <?php endforeach; ?>
    </div>
    <p class="small text-muted">เลือกสถานะเพื่อดูรายการ · จำนวนสรุปตามลานและเงื่อนไขค้นหา</p>
    <!-- nav: add 'ทั้งหมด' -->
    <nav class="surface-card toolbar-card p-2">
      <div class="lane-toolbar">
        <div class="lane-tabs">
          <span class="lane-label">
            <i data-lucide="droplet" aria-hidden="true"></i>เลือกลานรับยาง
          </span>
          <ul class="nav nav-pills align-items-center small mb-0">
            <li class="nav-item">
              <a class="nav-link <?php echo ($currentLan === 'all') ? 'active' : ''; ?>" href="rubbers.php?<?php echo e(http_build_query(array_merge($exportBaseParams, ['lan'=>'all']))); ?>">
                ทั้งหมด
              </a>
            </li>
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <li class="nav-item">
              <a class="nav-link <?php echo ($currentLan === $i) ? 'active' : ''; ?>"
                href="rubbers.php?<?php echo e(http_build_query(array_merge($exportBaseParams, ['lan'=>$i]))); ?>">
                ลานที่ <?php echo $i; ?>
              </a>
            </li>
            <?php endfor; ?>
          </ul>
        </div>

      </div>
    </nav>

    <?php if ($receiptInfo): ?><div class="alert alert-warning">รายการนี้ออกใบเสร็จแล้วเมื่อ <?php echo e($receiptInfo['issued_at']); ?> โดย <?php echo e($receiptInfo['issued_by']); ?> · <?php echo is_admin() ? 'คุณกำลังแก้ไขด้วยสิทธิ์ admin' : 'ต้องให้ admin ดำเนินการแก้ไข'; ?></div><?php endif; ?>
    <?php if ($msg): ?>
    <div class="alert alert-success py-2"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
    <div class="alert alert-danger py-2"><?php echo e(implode(' | ', $errors)); ?></div>
    <?php endif; ?>


    <?php if ($currentLan !== 'all' && !empty($form['ru_id']) && !$receiptEditLocked): ?>
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
          <legend><?php echo $linkedWeighing ? 'ข้อมูลจากการชั่ง' : 'ข้อมูลรายการ'; ?></legend>
          <?php if ($linkedWeighing): ?>
          <div class="source-overview">
            <div><span class="source-caption">ผู้ส่งยาง</span><strong><?php echo e($form['ru_fullname']); ?></strong><span class="person-meta">เลขที่ <?php echo e($form['ru_number']); ?> · กลุ่ม <?php echo e($form['ru_group']); ?> · ลาน <?php echo e($form['ru_lan']); ?></span></div>
            <div><span class="source-caption">น้ำหนักสุทธิ</span><strong><?php echo number_format((float)$linkedWeighing['net_weight'],2); ?> <small>กก.</small></strong></div>
            <div><span class="source-caption">ราคา / กก.</span><strong><?php echo number_format((float)$linkedWeighing['price_per_kg'],2); ?> <small>บาท</small></strong><span class="person-meta">รอบ <?php echo e(thai_date_format($linkedWeighing['round_date'])); ?></span></div>
          </div>
          <details class="source-details"><summary>ดูรายละเอียดรายการและผู้ชั่ง</summary>
          <?php endif; ?>
          <?php if ($linkedWeighing): ?><div class="alert alert-success py-2">อ้างอิงวางยาง #<?php echo (int)$linkedWeighing['wang_id']; ?> · น้ำหนักสุทธิ <?php echo number_format((float)$linkedWeighing['net_weight'], 2); ?> กก. · ราคา <?php echo number_format((float)$linkedWeighing['price_per_kg'], 2); ?> บาท/กก. · รอบ <?php echo e($linkedWeighing['round_date']); ?><br>ผู้ชั่ง: <?php echo e($linkedWeighing['weighed_by']); ?> · เวลาชั่ง: <?php echo e($linkedWeighing['weighed_at']); ?><?php if ($linkedWeighing['deductions_saved_at']): ?><br>ผู้บันทึกยอดหัก: <?php echo e($linkedWeighing['deductions_saved_by']); ?> · <?php echo e($linkedWeighing['deductions_saved_at']); ?><?php endif; ?></div><?php endif; ?>
          <div class="row g-3">
            <div class="col-12 col-md-3">
              <div class="field-inline">
                <label class="field-label" for="ru_date">วันที่</label>
                <div class="field-control">
                  <input id="ru_date" <?php echo $linkedWeighing ? 'readonly' : ''; ?> type="date" name="ru_date" required class="form-control"
                    value="<?php echo e($form['ru_date']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-md-3">
              <div class="field-inline">
                <label class="field-label" for="ru_group">กลุ่ม</label>
                <div class="field-control">
                  <input id="ru_group" name="ru_group" required class="form-control"
                    <?php if ($linkedWeighing || (empty($form['ru_id']) && $memberSelectedRow)) echo 'readonly'; ?>
                    value="<?php echo e($form['ru_group']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-md-3">
              <div class="field-inline">
                <label class="field-label" for="ru_number">เลขที่</label>
                <div class="field-control">
                  <input id="ru_number" name="ru_number" required class="form-control"
                    <?php if ($linkedWeighing || (empty($form['ru_id']) && $memberSelectedRow)) echo 'readonly'; ?>
                    value="<?php echo e($form['ru_number']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-md-3">
              <div class="field-inline">
                <label class="field-label" for="ru_class">ชั้น</label>
                <div class="field-control">
                  <input id="ru_class" name="ru_class" required class="form-control"
                    <?php if ($linkedWeighing || (empty($form['ru_id']) && $memberSelectedRow)) echo 'readonly'; ?>
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
                    <?php if ($linkedWeighing || (empty($form['ru_id']) && $memberSelectedRow)) echo 'readonly'; ?>
                    value="<?php echo e($form['ru_fullname']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-3">
              <div class="field-inline">
                <label class="field-label" for="ru_quantity">น้ำหนักสุทธิ (กก.)</label>
                <div class="field-control">
                  <input name="ru_quantity" id="ru_quantity" <?php echo $linkedWeighing ? 'readonly' : ''; ?> required inputmode="decimal" step="0.01" min="0" class="form-control text-end"
                    value="<?php echo e($form['ru_quantity']); ?>">
                </div>
              </div>
            </div>
          </div>
          <?php if ($linkedWeighing): ?></details><?php endif; ?>
        </fieldset>

        <fieldset>
          <legend>บันทึกยอดหัก (บาท)</legend>
          <p class="small text-muted">กรอกอย่างน้อย 1 ช่อง หากไม่มีรายการหัก ให้กรอก <strong>0</strong> ช่องใดช่องหนึ่ง แล้วกดบันทึก</p>
          <div class="row g-3 num-group">
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_hoon">หุ้น</label>
                <div class="field-control">
                  <input id="ru_hoon" name="ru_hoon" placeholder="0.00" inputmode="decimal" step="0.01" min="0" class="form-control text-end"
                    value="<?php echo e($form['ru_hoon']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_loan">เงินกู้</label>
                <div class="field-control">
                  <input id="ru_loan" name="ru_loan" placeholder="0.00" inputmode="decimal" step="0.01" min="0" class="form-control text-end"
                    value="<?php echo e($form['ru_loan']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_shortdebt">หนี้สั้น</label>
                <div class="field-control">
                  <input id="ru_shortdebt" name="ru_shortdebt" placeholder="0.00" inputmode="decimal" step="0.01" min="0"
                    class="form-control text-end" value="<?php echo e($form['ru_shortdebt']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_deposit">เงินฝาก</label>
                <div class="field-control">
                  <input id="ru_deposit" name="ru_deposit" placeholder="0.00" inputmode="decimal" step="0.01" min="0" class="form-control text-end"
                    value="<?php echo e($form['ru_deposit']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_tradeloan">กู้ซื้อขาย</label>
                <div class="field-control">
                  <input id="ru_tradeloan" name="ru_tradeloan" placeholder="0.00" inputmode="decimal" step="0.01" min="0"
                    class="form-control text-end" value="<?php echo e($form['ru_tradeloan']); ?>">
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="field-inline">
                <label class="field-label" for="ru_insurance">ประกันภัย</label>
                <div class="field-control">
                  <input id="ru_insurance" name="ru_insurance" placeholder="0.00" inputmode="decimal" step="0.01" min="0"
                    class="form-control text-end" value="<?php echo e($form['ru_insurance']); ?>">
                </div>
              </div>
            </div>
          </div>
          <input type="hidden" name="ru_savedate" value="<?php echo e($form['ru_savedate']); ?>">
        </fieldset>
        <hr>
        <fieldset>
          <legend>สรุปยอดรับเงิน</legend>
          <div class="alert alert-info py-2">
            <?php
                // ดึงราคาล่าสุด
                $latestPrice = 0.00;
                $res = $db->query("SELECT pr_price FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1");
                if ($res && ($r = $res->fetch_assoc())) { $latestPrice = (float)$r['pr_price']; }
                if ($linkedWeighing) $latestPrice = (float)$linkedWeighing['price_per_kg'];
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
              ราคาที่ใช้คำนวณ: <span id="latestPrice"
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
          <button type="button" id="btnSave" class="btn btn-success px-4">
            <i data-lucide="save" class="me-1" aria-hidden="true"></i>บันทึกยอดหัก
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
        <span class="small fw-semibold">ค้นหาและกรองข้อมูล<?php echo $currentLan === 'all' ? 'ทุกลาน' : 'ลาน ' . (int)$currentLan; ?></span>
        <span class="small text-muted">ผลลัพธ์ทั้งหมด <?php echo number_format($totalCount); ?> รายการ</span>
      </div>
      <div class="card-body">
        <form method="get" class="row gy-3 gx-3 align-items-end">
          <input type="hidden" name="status" value="<?php echo e($workflowStatus); ?>">
          <input type="hidden" name="lan" value="<?php echo e($currentLan); ?>">
          <div class="col-12 col-lg-4">
            <label class="form-label" for="rubberSearchInput">คำค้น</label>
            <div class="input-group">
              <span class="input-group-text">ค้นหา</span>
              <input type="text" id="rubberSearchInput" name="search" class="form-control"
                value="<?php echo e($search); ?>" placeholder="ค้นหาชื่อผู้ส่ง เลขสมาชิก หรือกลุ่ม">
              <?php if ($search !== '' || $classFilter !== 'all' || $date_from !== '' || $date_to !== ''): ?>
              <a class="btn btn-outline-secondary" href="rubbers.php?<?php echo e(http_build_query(['lan'=>$currentLan,'status'=>$workflowStatus])); ?>" title="ล้างตัวกรอง">
                <i data-lucide="x-circle" class="me-1" aria-hidden="true"></i>ล้าง
              </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label" for="date_from">รอบราคาตั้งแต่</label>
            <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo e($date_from); ?>">
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label" for="date_to">ถึงวันที่</label>
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
          <div class="col-12 col-lg-2 d-grid">
            <button type="submit" class="btn btn-success">
              <i data-lucide="check" class="me-1" aria-hidden="true"></i>ค้นหารายการ
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
            <?php if ($date_from === '' && $date_to === ''): ?>ทุกรอบราคา<?php else: ?><?php echo $date_from ? e(thai_date_format($date_from)) : 'ไม่จำกัดวันเริ่ม'; ?> ถึง <?php echo $date_to ? e(thai_date_format($date_to)) : 'ไม่จำกัดวันสิ้นสุด'; ?><?php endif; ?>
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
        <div><h2 class="table-heading"><?php echo ['pending'=>'รายการรอบันทึกยอดหัก','completed'=>'รายการบันทึกยอดหักแล้ว','legacy'=>'รายการเดิม','all'=>'รายการทั้งหมด'][$workflowStatus]; ?></h2><p class="table-hint"><?php echo number_format($totalCount); ?> รายการ · <?php echo $currentLan === 'all' ? 'ทุกลาน' : 'ลาน ' . (int)$currentLan; ?><?php if ($totalPages > 1): ?> · หน้า <?php echo $page; ?> / <?php echo $totalPages; ?><?php endif; ?></p></div>
        <?php if (!empty($rows) && $workflowStatus === 'all'): ?><a href="export_rubbers_excel.php?<?php echo e($exportQuery); ?>" class="btn btn-sm btn-outline-success"><i data-lucide="download" aria-hidden="true"></i>ส่งออก Excel</a><?php endif; ?>
      </div>
      <p class="small text-muted d-md-none px-3 mt-3 mb-2">เลื่อนตารางไปทางซ้ายเพื่อดูยอดเงินและปุ่มบันทึกยอดหัก</p>
      <div class="table-shell">
        <div class="table-responsive rounded-3">
          <table class="table table-sm table-hover align-middle caption-top">
            <thead><tr><th>รายการ / รอบราคา</th><th>ผู้ส่งยาง</th><th>ลาน</th><th class="text-end">น้ำหนัก (กก.)</th><th class="text-end">ยอดหัก (บาท)</th><th class="text-end">สุทธิ (บาท)</th><th>สถานะ / ผู้รับผิดชอบ</th><th>ดำเนินการ</th></tr></thead>
            <tbody>
              <?php if (!$rows): ?>
              <tr>
                <td colspan="8" class="empty-state"><strong>ไม่มีรายการในสถานะที่เลือก</strong>เลือกสถานะอื่น หรือปรับลานและช่วงวันที่เพื่อค้นหารายการ</td>
              </tr>
              <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><strong>#<?php echo (int)$r['ru_id']; ?></strong><span class="person-meta"><?php echo e(thai_date_format($r['ru_date'])); ?></span></td>
                <td><span class="person-name"><?php echo e($r['ru_fullname']); ?></span><span class="person-meta">เลขที่ <?php echo e($r['ru_number']); ?> · กลุ่ม <?php echo e($r['ru_group']); ?> · <?php echo $r['ru_class'] === 'member' ? 'สมาชิก' : 'เกษตรกรทั่วไป'; ?></span></td>
                <td><?php echo e($r['ru_lan']); ?></td>
                <td class="text-end amount"><?php echo number_format((float)$r['ru_quantity'], 2); ?></td>
                <td class="text-end amount"><?php echo number_format((float)$r['ru_expend'], 2); ?></td>
                <td class="text-end amount"><?php echo number_format((float)$r['ru_netvalue'], 2); ?></td>
                <td><span class="badge <?php echo !$r['linked_weighing_id'] ? 'text-bg-secondary' : ($r['deductions_saved_at'] ? 'text-bg-success' : 'text-bg-warning'); ?>"><?php echo !$r['linked_weighing_id'] ? 'รายการเดิม' : ($r['deductions_saved_at'] ? 'บันทึกยอดหักแล้ว' : 'รอบันทึกยอดหัก'); ?></span><?php if ($r['linked_weighing_id']): ?><div class="small text-muted">ผู้ชั่ง: <?php echo e($r['weighed_by']); ?><?php if ($r['deductions_saved_at']): ?><br>ผู้บันทึกยอดหัก: <?php echo e($r['deductions_saved_by']); ?><?php endif; ?></div><?php endif; ?></td>
                <td>
                  <div class="action-stack">
                    <?php if ($r['receipt_issued_at'] && !$isAdmin): ?><span class="badge text-bg-secondary">ออกใบเสร็จแล้ว · ล็อก</span><?php else: ?>
                    <a href="rubbers.php?lan=<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>&action=edit&id=<?php echo (int)$r['ru_id']; ?>#rubberForm"
                      class="btn btn-sm btn-success" title="บันทึกยอดหัก" aria-label="ใส่ / แก้ยอดหัก">
                      <i class="bi bi-pencil-square" aria-hidden="true"></i><?php echo $r['deductions_saved_at'] ? 'แก้ไข' : 'บันทึกยอดหัก'; ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!$r['linked_weighing_id'] && !$r['receipt_issued_at']): ?>
                    <form method="post" onsubmit="return confirm('ลบรายการนี้?');" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="lan"
                        value="<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>">
                      <input type="hidden" name="ru_id" value="<?php echo (int)$r['ru_id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ" aria-label="ลบ">
                        <i data-lucide="trash-2" aria-hidden="true"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($r['linked_weighing_id'] && !$r['deductions_saved_at']): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="กรุณาบันทึกยอดหักก่อนพิมพ์ใบเสร็จ">พิมพ์ใบเสร็จ</button>
                    <?php elseif ($hasDompdf): ?>
                    <a href="export_rubber_pdf.php?ru_id=<?php echo (int)$r['ru_id']; ?>" target="_blank"
                      class="btn btn-sm btn-outline-dark" title="พิมพ์ใบเสร็จ" aria-label="พิมพ์ใบเสร็จ">
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

      if (!q || !list || !hid) return;
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
      const deductionInputs = ['ru_hoon','ru_loan','ru_shortdebt','ru_deposit','ru_tradeloan','ru_insurance'].map(id => document.getElementById(id));
      deductionInputs.forEach(input => input?.addEventListener('input', () => deductionInputs[0]?.setCustomValidity('')));


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
        const entered = deductionInputs.some(input => input && input.value.trim() !== '');
        if (deductionInputs[0]) deductionInputs[0].setCustomValidity(entered ? '' : 'กรุณากรอกยอดหักอย่างน้อย 1 ช่อง หากไม่มีรายการหักให้กรอก 0');

        if (form.reportValidity && !form.reportValidity()) return;
        allowSubmit = true;
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
      });
    })();
    </script>

  </div><!-- /container -->

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

</div><!-- /rubbers-page -->
<?php include __DIR__ . '/footer.php'; ?>

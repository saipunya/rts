<?php
require_once __DIR__ . '/functions.php';

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

// new: search params for 'all' view
$search = trim((string)($_GET['search'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to   = trim((string)($_GET['date_to'] ?? ''));

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

// added: ensure columns exist on old schema
$db->query("ALTER TABLE tbl_rubber ADD COLUMN IF NOT EXISTS ru_value DECIMAL(18,2) NOT NULL DEFAULT 0.00");
$db->query("ALTER TABLE tbl_rubber ADD COLUMN IF NOT EXISTS ru_expend DECIMAL(18,2) NOT NULL DEFAULT 0.00");
$db->query("ALTER TABLE tbl_rubber ADD COLUMN IF NOT EXISTS ru_netvalue DECIMAL(18,2) NOT NULL DEFAULT 0.00");

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

      // set ru_saveby from session
      $data['ru_saveby'] = $_SESSION['user_name'] ?? 'เจ้าหน้าที่';

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

// ...existing code...
$cu = current_user(); // ดึงข้อมูลผู้ใช้ปัจจุบัน
$isAdmin = isset($cu['user_level']) && $cu['user_level'] === 'admin';
// ...existing code...
// ดึงข้อมูลรายการ (รองรับ all + filters)
$rows = [];
if ($currentLan === 'all') {
  // เฉพาะ admin เท่านั้นที่เห็นข้อมูลทุกลาน
  if (!$isAdmin) {
    // ถ้าไม่ใช่ admin ไม่ให้เห็นข้อมูลใด ๆ
    $rows = [];
  } else {
    $conds = [];
    $binds = [];
    $types = '';

    if ($search !== '') {
      $like = '%' . $search . '%';
      $conds[] = '(ru_group LIKE ? OR ru_number LIKE ? OR ru_fullname LIKE ? OR ru_class LIKE ?)';
      $types .= 'ssss';
      array_push($binds, $like, $like, $like, $like);
    }
    if ($date_from !== '') {
      $df = DateTime::createFromFormat('Y-m-d', $date_from);
      if ($df && $df->format('Y-m-d') === $date_from) {
        $conds[] = 'ru_date >= ?';
        $types .= 's';
        $binds[] = $date_from;
      }
    }
    if ($date_to !== '') {
      $dt = DateTime::createFromFormat('Y-m-d', $date_to);
      if ($dt && $dt->format('Y-m-d') === $date_to) {
        $conds[] = 'ru_date <= ?';
        $types .= 's';
        $binds[] = $date_to;
      }
    }
    $sql = "SELECT * FROM tbl_rubber";
    if ($conds) $sql .= ' WHERE ' . implode(' AND ', $conds);
    $sql .= ' ORDER BY ru_date DESC, ru_id DESC';

    if ($conds) {
      $st = $db->prepare($sql);
      $st->bind_param($types, ...$binds);
      $st->execute();
      $res = $st->get_result();
    } else {
      $res = $db->query($sql);
    }

    if ($res) {
      while ($r = $res->fetch_assoc()) $rows[] = $r;
      $res->free();
    }
    if (!empty($st)) $st->close();

    // new: aggregate summary
    $sumQty = 0.0; $sumValue = 0.0; $sumExpend = 0.0; $sumNet = 0.0;
    foreach ($rows as $ag) {
      $sumQty    += (float)$ag['ru_quantity'];
      $sumValue  += (float)$ag['ru_value'];
      $sumExpend += (float)$ag['ru_expend'];
      $sumNet    += (float)$ag['ru_netvalue'];
    }
  }
} else {
  // เฉพาะ user ปกติ filter ด้วย ru_saveby
  if (!$isAdmin && isset($cu['user_fullname'])) {
    $stl = $db->prepare("SELECT * FROM tbl_rubber WHERE ru_lan = ? AND ru_saveby = ? ORDER BY ru_date DESC, ru_id DESC");
    $lanStr = (string)$currentLan;
    $stl->bind_param('ss', $lanStr, $cu['user_fullname']);
  } else {
    $stl = $db->prepare("SELECT * FROM tbl_rubber WHERE ru_lan = ? ORDER BY ru_date DESC, ru_id DESC");
    $lanStr = (string)$currentLan;
    $stl->bind_param('s', $lanStr);
  }
  $stl->execute();
  $res = $stl->get_result();
  if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $res->free();
  }
  $stl->close();
}

// new: build export query string for PDF button
$exportBaseParams = [
  'lan' => ($currentLan === 'all' ? 'all' : $currentLan),
  'search' => $search,
  'date_from' => $date_from,
  'date_to' => $date_to,
];
$exportQuery = http_build_query(array_filter($exportBaseParams, fn($v) => $v !== ''));
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <title>จัดการข้อมูลยางพารา</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
  <style>
    /* ปรับเหลือเฉพาะส่วน dropdown member */
    .member-chooser {
      position: relative;
    }

    .member-chooser .dropdown {
      position: absolute;
      left: 0;
      right: 0;
      top: 100%;
      z-index: 1000;
    }

    #memberResults.list-group {
      max-height: 260px;
      overflow: auto;
    }

    .tag {
      display: inline-block;
      padding: .25rem .6rem;
      background: #eef2ff;
      color: #3730a3;
      border-radius: 50rem;
      font-size: 1.2rem;
    }

    .link-btn {
      background: none;
      border: none;
      color: #0d6efd;
      cursor: pointer;
      padding: 0 .25rem;
    }

    /* new compact form styles */
    .form-wrap { max-width: 960px; margin: 0 auto; }
    fieldset { border: 1px solid #e4e6eb; padding: .85rem 1.1rem 1rem; border-radius: .65rem; margin-bottom: 1rem; background:#fff; }
    fieldset legend { font-size: 1rem; font-weight: 600; width: auto; padding: 0 .6rem; margin-bottom: .2rem; }
    .num-group .input-group-text { min-width:70px; justify-content:center; }

    /* added: table polish */
    .table-responsive .table thead.sticky-header th {
      position: sticky; top: 0; z-index: 3;
      background: var(--bs-light);
      box-shadow: inset 0 -1px 0 rgba(0,0,0,.05);
    }
    .table td.text-end, .table th.text-end { font-variant-numeric: tabular-nums; }
    .table-hover tbody tr:hover td { background-color: rgba(13,110,253,.04); }
    .table caption { color:#6c757d; padding-left:.5rem; }
  </style>
</head>

<body class="bg-light">
  <div class="container py-4">
    <h1 class="h4 mb-3">จัดการข้อมูลยางพารา</h1>

    <!-- nav: add 'ทั้งหมด' -->
    <nav class="container-md mb-3 d-flex justify-content-between align-items-center">
      <ul class="nav nav-pills">
        <li class="nav-item">
          <a class="nav-link <?php echo ($currentLan === 'all') ? 'active' : ''; ?>" href="rubbers.php?lan=all">ทั้งหมด</a>
        </li>
        <?php for ($i = 1; $i <= 4; $i++): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo ($currentLan === $i) ? 'active' : ''; ?>" href="rubbers.php?lan=<?php echo $i; ?>">
              (ลานที่ <?php echo $i; ?>)
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>

    <?php if ($msg): ?>
      <div class="alert alert-success py-2"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger py-2"><?php echo e(implode(' | ', $errors)); ?></div>
    <?php endif; ?>

    <?php if ($currentLan !== 'all'): ?>
      <form method="post" autocomplete="off" class="card mb-4 form-wrap" id="rubberForm">
        <div class="card-header d-flex justify-content-between align-items-center small">
          <span>ลาน: <?php echo ($currentLan === 'all') ? 'ทั้งหมด (เพิ่มใช้ลาน 1 เริ่มต้น)' : 'ลาน ' . (int)$currentLan; ?></span>
          <span class="text-muted"><?php echo !empty($form['ru_id']) ? 'แก้ไข #' . (int)$form['ru_id'] : 'เพิ่มรายการใหม่'; ?></span>
        </div>
        <div class="card-body">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="lan" value="<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>"> <!-- keep lane/all -->
          <!-- changed: always include ru_member_id, prefill if selected via GET -->
          <input type="hidden" id="ru_member_id" name="ru_member_id" value="<?php echo !empty($memberSelectedRow['mem_id']) ? (int)$memberSelectedRow['mem_id'] : ''; ?>">
          <?php if (!empty($form['ru_id'])): ?>
            <input type="hidden" name="ru_id" value="<?php echo (int)$form['ru_id']; ?>">
          <?php endif; ?>
          <input type="hidden" name="ru_lan" value="<?php echo !empty($form['ru_id']) ? (int)$form['ru_lan'] : ($currentLan === 'all' ? 1 : (int)$currentLan); ?>">

          <?php if (empty($form['ru_id'])): ?>
          <fieldset>
            <legend>เลือกสมาชิก</legend>
            <!-- member chooser (unchanged logic, only wrapper) -->
            <div class="member-chooser">
              <div class="input-group">
                <span class="input-group-text">ค้นหา</span>
                <input id="memberSearch" type="text" class="form-control" placeholder="ชื่อ / เลขที่ / กลุ่ม / ชั้น">
              </div>
              <ul id="memberResults" class="list-group mt-1" hidden></ul>
              <div id="memberSelected" class="form-text mt-2" <?php if (empty($memberSelectedRow)) echo 'hidden'; ?>>
                <?php if (!empty($memberSelectedRow)): ?>
                  ใช้สมาชิก: <span class="tag">#<?php echo (int)$memberSelectedRow['mem_id']; ?></span>
                  <?php echo e($memberSelectedRow['mem_fullname']); ?> |
                  กลุ่ม: <?php echo e($memberSelectedRow['mem_group']); ?> |
                  เลขที่: <?php echo e($memberSelectedRow['mem_number']); ?> |
                  ชั้น: <?php echo e($memberSelectedRow['mem_class']); ?>
                  <button type="button" id="clearMember" class="link-btn">เปลี่ยน</button>
                <?php endif; ?>
              </div>
            </div>
          </fieldset>
          <?php endif; ?>

          <fieldset>
            <legend>ข้อมูลพื้นฐาน</legend>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">วันที่
                  <input type="date" name="ru_date" required class="form-control" value="<?php echo e($form['ru_date']); ?>">
                </label>
              </div>
              <div class=" col-md-3">
                <label class="form-label">กลุ่ม
                  <input id="ru_group" name="ru_group" required class="form-control" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_group']); ?>">
                </label>
              </div>
              <div class=" col-md-3">
                <label class="form-label">เลขที่
                  <input id="ru_number" name="ru_number" required class="form-control" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_number']); ?>">
                </label>
              </div>
              <div class="col-md-3">
                <label class="form-label">ชั้น
                  <input id="ru_class" name="ru_class" required class="form-control" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_class']); ?>">
                </label>
              </div>
             
              
            </div>
            <div class="row my-2">
              <div class="col-md-9">
              <div class="input-group">
              <span class="input-group-text">ชื่อ-สกุล</span>
                  <input id="ru_fullname" name="ru_fullname" required class="form-control" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_fullname']); ?>">
                </div>
              </div>
              <div class="col-md-3">
                <div class="input-group">
                  <span class="input-group-text">ปริมาณ</span>
                  <input name="ru_quantity" id="ru_quantity" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_quantity']); ?>">
                </div>
              </div>
              </div>
          </fieldset>

          <fieldset>
            <legend>การหัก</legend>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-2 num-group">
              <div class="col">
                <div class="input-group">
                  <span class="input-group-text">หุ้น</span>
                  <input id="ru_hoon" name="ru_hoon" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_hoon']); ?>">
                </div>
              </div>
              <div class="col">
                <div class="input-group">
                  <span class="input-group-text">เงินกู้</span>
                  <input id="ru_loan" name="ru_loan" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_loan']); ?>">
                </div>
              </div>
              <div class="col">
                <div class="input-group">
                  <span class="input-group-text">หนี้สั้น</span>
                  <input id="ru_shortdebt" name="ru_shortdebt" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_shortdebt']); ?>">
                </div>
              </div>
              <div class="col">
                <div class="input-group">
                  <span class="input-group-text">เงินฝาก</span>
                  <input id="ru_deposit" name="ru_deposit" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_deposit']); ?>">
                </div>
              </div>
              <div class="col">
                <div class="input-group">
                  <span class="input-group-text">กู้ซื้อขาย</span>
                  <input id="ru_tradeloan" name="ru_tradeloan" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_tradeloan']); ?>">
                </div>
              </div>
              <div class="col">
                <div class="input-group">
                  <span class="input-group-text">ประกันภัย</span>
                  <input id="ru_insurance" name="ru_insurance" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_insurance']); ?>">
                </div>
              </div>
            </div>
            <input type="hidden" name="ru_savedate" value="<?php echo e($form['ru_savedate']); ?>">
          </fieldset>
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
                <p class="mb-1 small text-muted">
                  ราคาล่าสุด: <span id="latestPrice" data-price="<?php echo $latestPrice; ?>"><?php echo number_format($latestPrice, 2); ?></span> บาท/กก.
                </p>
                <p class="mb-1">
                  มูลค่ายาง = ราคา x ปริมาณ: <strong id="ruValue"><?php echo number_format($initialRuValue, 2); ?></strong> บาท
                </p>
                <p class="mb-1">
                  ยอดหักรวม: <strong id="ruExpend"><?php echo number_format($initialExpend, 2); ?></strong> บาท
                </p>
                <p class="mb-0">
                  ยอดสุทธิ : <strong id="ruNetValue"><?php echo number_format($initialNetValue, 2); ?></strong> บาท
                </p>

                <script>
                  (function () {
                    const priceEl = document.getElementById('latestPrice');
                    const price = parseFloat(priceEl?.dataset.price || '0') || 0;

                    const qtyInput = document.getElementById('ru_quantity');
                    const fields = ['ru_hoon','ru_loan','ru_shortdebt','ru_deposit','ru_tradeloan','ru_insurance'];

                    const elValue  = document.getElementById('ruValue');
                    const elExpend = document.getElementById('ruExpend');
                    const elNet    = document.getElementById('ruNetValue');

                    function num(v){ return parseFloat((v || '0').toString().replace(/,/g,'')) || 0; }
                    function fmt(n){ return n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }

                    function calc(){
                      const q = qtyInput ? num(qtyInput.value) : 0;
                      const ru_value = q * price;
                      let ru_expend = 0;
                      fields.forEach(id => {
                        const el = document.getElementById(id);
                        if (el) ru_expend += num(el.value);
                      });
                      const ru_net = ru_value - ru_expend;

                      if (elValue)  elValue.textContent  = fmt(ru_value);
                      if (elExpend) elExpend.textContent = fmt(ru_expend);
                      if (elNet)    elNet.textContent    = fmt(ru_net);
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
          <small class="text-muted"><?php echo !empty($form['ru_id']) ? 'แก้ไข #' . (int)$form['ru_id'] : 'สร้างรายการใหม่'; ?></small>
          <div>
            <button type="button" id="btnSave" class="btn btn-primary px-4">
              <i class="bi bi-floppy2 me-1"></i>บันทึก
            </button>
            <?php if (!empty($form['ru_id'])): ?>
              <a href="rubbers.php?lan=<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-arrow-counterclockwise me-1"></i>ยกเลิก
              </a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    <?php else: ?>
      <!-- improved search layout -->
      <div class="card mb-4 form-wrap">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="small fw-semibold">ค้นหาข้อมูลทุกลาน</span>
          <span class="small text-muted">แสดงผล <?php echo count($rows); ?> รายการ</span>
        </div>
        <div class="card-body">
          <form method="get" class="row gy-3 gx-3">
            <input type="hidden" name="lan" value="all">
            <div class="col-md-4">
              <label class="form-label mb-1">คำค้น (กลุ่ม / เลขที่ / ชื่อ / ชั้น)</label>
              <div class="input-group">
                <span class="input-group-text">ค้นหา</span>
                <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="เช่น กลุ่ม 1, 001, นายเอ, ป.6">
                <?php if ($search !== ''): ?>
                  <a class="btn btn-outline-secondary" href="rubbers.php?lan=all" title="ล้าง">
                    <i class="bi bi-x-circle me-1"></i>ล้าง
                  </a>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1">ช่วงวันที่ (จาก)</label>
              <input type="date" name="date_from" class="form-control" value="<?php echo e($date_from); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1">ช่วงวันที่ (ถึง)</label>
              <input type="date" name="date_to" class="form-control" value="<?php echo e($date_to); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check2 me-1"></i>ตกลง
              </button>
            </div>
          </form>

          <!-- summary badges -->
          <div class="mt-3 d-flex flex-wrap gap-2">
            <div class="badge bg-secondary text-wrap p-2">
              ปริมาณรวม: <?php echo number_format($sumQty,2); ?> กก.
            </div>
            <div class="badge bg-info text-dark text-wrap p-2">
              มูลค่า (ru_value): <?php echo number_format($sumValue,2); ?> ฿
            </div>
            <div class="badge bg-warning text-dark text-wrap p-2">
              หักรวม (ru_expend): <?php echo number_format($sumExpend,2); ?> ฿
            </div>
            <div class="badge bg-success text-wrap p-2">
              สุทธิ (ru_netvalue): <?php echo number_format($sumNet,2); ?> ฿
            </div>
          </div>
          <?php if ($search || $date_from || $date_to): ?>
            <div class="mt-2 small text-muted">
              เงื่อนไข: 
              <?php echo $search ? 'คำค้น="'.e($search).'" ' : ''; ?>
              <?php echo $date_from ? 'จาก '.e($date_from).' ' : ''; ?>
              <?php echo $date_to ? 'ถึง '.e($date_to).' ' : ''; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- ตาราง -->
    <div class="table-responsive rounded-3 border shadow-sm">
      <table class="table table-sm table-hover align-middle caption-top">
        <caption>
          ผลลัพธ์ <?php echo number_format(count($rows)); ?> รายการ
          <span class="ms-2 text-muted">
            <?php echo ($currentLan === 'all') ? '(ทุกลาน)' : '(ลาน '.(int)$currentLan.')'; ?>
          </span>
          <?php if (!empty($rows)): ?>
            <!-- pdf export button shown only if dompdf installed -->
            <?php if ($hasDompdf): ?>
              <a href="export_rubbers_pdf.php?<?php echo $exportQuery; ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-2">
                <i class="bi bi-file-earmark-pdf me-1"></i>PDF ลานที่ <?php echo (int)$r['ru_lan']; ?>
              </a>
            <?php else: ?>
              <button type="button" class="btn btn-sm btn-outline-secondary ms-2" disabled title="โปรดติดตั้ง dompdf ด้วย Composer ก่อน">
                <i class="bi bi-file-earmark-pdf me-1"></i>PDF
              </button>
            <?php endif; ?>
            <!-- new: CSV export (per-row) -->
            <a href="export_rubbers_csv.php?<?php echo $exportQuery; ?>" class="btn btn-sm btn-outline-success ms-2">
              <i class="bi bi-filetype-csv me-1"></i>CSV
            </a>
          <?php endif; ?>
        </caption>
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
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="17" class="text-center text-muted py-4">ยังไม่มีข้อมูล</td>
            </tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['ru_id']; ?></td>
              <td><?php echo thai_date_format($r['ru_date']); ?></td>
              <td><?php echo e($r['ru_lan']); ?></td>
              <td><?php echo e($r['ru_group']); ?></td>
              <td><?php echo e($r['ru_number']); ?></td>
              <td>
                <?php echo e($r['ru_fullname']); ?>
                <!-- ถ้า  ru_class == 'general'  -->
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
                <div class="d-flex gap-1">
                  <a href="rubbers.php?lan=<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>&action=edit&id=<?php echo (int)$r['ru_id']; ?>" class="btn btn-sm btn-warning">
                    <i class="bi bi-pencil-square me-1"></i>แก้ไข
                  </a>
                  <form method="post" onsubmit="return confirm('ลบรายการนี้?');" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="lan" value="<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>">
                    <input type="hidden" name="ru_id" value="<?php echo (int)$r['ru_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger">
                      <i class="bi bi-trash me-1"></i>ลบ
                    </button>
                  </form>
                  <?php if ($hasDompdf): ?>
                    <a href="export_rubber_pdf.php?ru_id=<?php echo (int)$r['ru_id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                      <i class="bi bi-file-earmark-pdf me-1"></i>PDF 
                    </a>
                  <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled title="โปรดติดตั้ง dompdf ด้วย Composer ก่อน">
                      <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="row my-2 text-center">
      <a href="index.php"><button class="btn btn-sm btn-info"><i class="bi bi-arrow-left ms-2"></i>กลับหน้าหลัก</button></a>
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
            selectedBox.innerHTML = `ใช้สมาชิก: <span class="tag">#${m.mem_id}</span> ${m.mem_fullname} | กลุ่ม: ${m.mem_group} | เลขที่: ${m.mem_number} | ชั้น: ${m.mem_class} <button type="button" id="clearMember" class="link-btn">เปลี่ยน</button>`;
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
            if (fQty) { fQty.focus(); fQty.select(); }
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
            setTimeout(() => { fQty.focus(); fQty.select(); }, 50);
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

        // block Enter key from submitting form
        form.addEventListener('keydown', function(e) {
          if (e.key === 'Enter' && e.target && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
          }
        });

        // only allow submit via Save button (with HTML5 validation)
        saveBtn.addEventListener('click', function() {
          if (form.reportValidity && !form.reportValidity()) return;
          if (form.requestSubmit) form.requestSubmit();
          else form.submit();
        });

        // extra guard: prevent submits not coming from the Save button
        form.addEventListener('submit', function(e) {
          // when requestSubmit is used, submitter อาจไม่มี ให้ปล่อยผ่าน
          if (e.submitter && e.submitter.id !== 'btnSave') {
            e.preventDefault();
          }
        });
      })();
    </script>

  </div><!-- /container -->
</body>

</html>
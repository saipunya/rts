<?php
require_once __DIR__ . '/functions.php';

$db = db();
$errors = [];
$msg = $_GET['msg'] ?? '';
$csrf = csrf_token();
$default_saveby = $_SESSION['user_name'] ?? 'เจ้าหน้าที่';
$today = date('Y-m-d');

// new: support lan=all
$lanParam = $_GET['lan'] ?? ($_POST['lan'] ?? '1');
if ($lanParam === 'all') {
	$currentLan = 'all';
} else {
	$currentLan = (int)$lanParam;
	if (!in_array($currentLan, [1,2,3,4], true)) $currentLan = 1;
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
	ru_saveby VARCHAR(255) NOT NULL,
	ru_savedate DATE NOT NULL,
	PRIMARY KEY (ru_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;");

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
	'ru_saveby' => $default_saveby,
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
				if (isset($row['ru_lan']) && in_array((int)$row['ru_lan'], [1,2,3,4], true)) {
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
				$st->bind_param('i',$id);
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
			$fieldsText = ['ru_lan','ru_group','ru_number','ru_fullname','ru_class','ru_saveby']; // ensure ru_lan present
			$fieldsDate = ['ru_date','ru_savedate'];
			$fieldsNum  = ['ru_quantity','ru_hoon','ru_loan','ru_shortdebt','ru_deposit','ru_tradeloan','ru_insurance'];

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
			if (!in_array($lanVal, [1,2,3,4], true)) {
				$errors[] = 'ลานไม่ถูกต้อง';
			}
			$data['ru_lan'] = (string)$lanVal;

			if (!$errors) {
				$id = isset($_POST['ru_id']) && $_POST['ru_id'] !== '' ? (int)$_POST['ru_id'] : 0;
				if ($id > 0) {
					$st = $db->prepare("UPDATE tbl_rubber SET
						ru_date=?, ru_lan=?, ru_group=?, ru_number=?, ru_fullname=?, ru_class=?, ru_quantity=?,
						ru_hoon=?, ru_loan=?, ru_shortdebt=?, ru_deposit=?, ru_tradeloan=?, ru_insurance=?,
						ru_saveby=?, ru_savedate=? WHERE ru_id=?");
					$st->bind_param(
						str_repeat('s',15).'i',
						$data['ru_date'],$data['ru_lan'],$data['ru_group'],$data['ru_number'],$data['ru_fullname'],$data['ru_class'],$data['ru_quantity'],
						$data['ru_hoon'],$data['ru_loan'],$data['ru_shortdebt'],$data['ru_deposit'],$data['ru_tradeloan'],$data['ru_insurance'],
						$data['ru_saveby'],$data['ru_savedate'],$id
					);
					$st->execute();
					$st->close();
					$lanRedirect = ($lanParam === 'all') ? 'all' : (int)$data['ru_lan'];
					header('Location: rubbers.php?lan=' . $lanRedirect . '&msg=' . urlencode('บันทึกการแก้ไขแล้ว'));
					exit;
				} else {
					$st = $db->prepare("INSERT INTO tbl_rubber
						(ru_date, ru_lan, ru_group, ru_number, ru_fullname, ru_class, ru_quantity,
						 ru_hoon, ru_loan, ru_shortdebt, ru_deposit, ru_tradeloan, ru_insurance, ru_saveby, ru_savedate)
						VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
					$st->bind_param(
						str_repeat('s',15),
						$data['ru_date'],$data['ru_lan'],$data['ru_group'],$data['ru_number'],$data['ru_fullname'],$data['ru_class'],$data['ru_quantity'],
						$data['ru_hoon'],$data['ru_loan'],$data['ru_shortdebt'],$data['ru_deposit'],$data['ru_tradeloan'],$data['ru_insurance'],
						$data['ru_saveby'],$data['ru_savedate']
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

// ดึงข้อมูลรายการ (รองรับ all)
$rows = [];
if ($currentLan === 'all') {
	$res = $db->query("SELECT * FROM tbl_rubber ORDER BY ru_date DESC, ru_id DESC");
	if ($res) {
		while ($r = $res->fetch_assoc()) $rows[] = $r;
		$res->free();
	}
} else {
	$stl = $db->prepare("SELECT * FROM tbl_rubber WHERE ru_lan = ? ORDER BY ru_date DESC, ru_id DESC");
	$lanStr = (string)$currentLan;
	$stl->bind_param('s', $lanStr);
	$stl->execute();
	$res = $stl->get_result();
	if ($res) {
		while ($r = $res->fetch_assoc()) $rows[] = $r;
		$res->free();
	}
	$stl->close();
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการข้อมูลยางพารา</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<style>
/* ปรับเหลือเฉพาะส่วน dropdown member */
.member-chooser { position: relative; }
.member-chooser .dropdown { position:absolute; left:0; right:0; top:100%; z-index:1000; }
#memberResults.list-group { max-height:260px; overflow:auto; }
.tag { display:inline-block; padding:.25rem .6rem; background:#eef2ff; color:#3730a3; border-radius:50rem; font-size:.75rem; }
.link-btn { background:none; border:none; color:#0d6efd; cursor:pointer; padding:0 .25rem; }
</style>
</head>
<body class="bg-light">
<div class="container py-4">
<h1 class="h4 mb-3">จัดการข้อมูลยางพารา</h1>

<!-- nav: add 'ทั้งหมด' -->
<nav class="mb-3">
  <ul class="nav nav-pills">
	<li class="nav-item">
	  <a class="nav-link <?php echo ($currentLan === 'all') ? 'active' : ''; ?>" href="rubbers.php?lan=all">ทั้งหมด</a>
	</li>
    <?php for ($i=1; $i<=4; $i++): ?>
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



<form method="post" autocomplete="off" class="card mb-4">
  <div class="card-body">
  <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="lan" value="<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>"> <!-- keep lane/all -->
  <!-- changed: always include ru_member_id, prefill if selected via GET -->
  <input type="hidden" id="ru_member_id" name="ru_member_id" value="<?php echo !empty($memberSelectedRow['mem_id']) ? (int)$memberSelectedRow['mem_id'] : ''; ?>">
  <?php if (!empty($form['ru_id'])): ?>
    <input type="hidden" name="ru_id" value="<?php echo (int)$form['ru_id']; ?>">
  <?php endif; ?>

  <div class="row g-3">

    <?php if (empty($form['ru_id'])): ?>
    <!-- added: inline member search/typeahead -->
    <div class="col-12 member-chooser">
      <label class="form-label">ค้นหาสมาชิก</label>
      <div class="input-group">
        <span class="input-group-text">สมาชิก</span>
        <input id="memberSearch" type="text" class="form-control" placeholder="พิมพ์ชื่อ / เลขที่ / กลุ่ม / ชั้น">
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
    <?php endif; ?>

    <div class="col-md-2">
      <label class="form-label">วันที่
        <input type="date" name="ru_date" required class="form-control" value="<?php echo e($form['ru_date']); ?>">
      </label>
    </div>

    <!-- changed: lock lane to link -->
    <div class="col-md-2">
      <label class="form-label d-block">ลาน</label>
      <div class="form-control-plaintext fw-semibold">
        ลาน <?php echo !empty($form['ru_id']) ? (int)$form['ru_lan'] : ($currentLan === 'all' ? 1 : (int)$currentLan); ?>
      </div>
      <input type="hidden" name="ru_lan" value="<?php echo !empty($form['ru_id']) ? (int)$form['ru_lan'] : ($currentLan === 'all' ? 1 : (int)$currentLan); ?>">
    </div>

    <div class="col-md-2">
      <label class="form-label">กลุ่ม
        <input id="ru_group" name="ru_group" required class="form-control" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_group']); ?>">
      </label>
    </div>
    <div class="col-md-2">
      <label class="form-label">เลขที่
        <input id="ru_number" name="ru_number" required class="form-control" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_number']); ?>">
      </label>
    </div>
    <div class="col-md-3">
      <label class="form-label">ชื่อ-สกุล
        <input id="ru_fullname" name="ru_fullname" required class="form-control" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_fullname']); ?>">
      </label>
    </div>
    <div class="col-md-1">
      <label class="form-label">ชั้น
        <input id="ru_class" name="ru_class" required class="form-control" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_class']); ?>">
      </label>
    </div>

    <div class="col-md-2">
      <label class="form-label">ปริมาณ
        <input name="ru_quantity" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_quantity']); ?>">
      </label>
    </div>
    <div class="col-md-2">
      <label class="form-label">หุ้น
        <input name="ru_hoon" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_hoon']); ?>">
      </label>
    </div>
    <div class="col-md-2">
      <label class="form-label">เงินกู้
        <input name="ru_loan" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_loan']); ?>">
      </label>
    </div>
    <div class="col-md-2">
      <label class="form-label">หนี้ค้างสั้น
        <input name="ru_shortdebt" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_shortdebt']); ?>">
      </label>
    </div>
    <div class="col-md-2">
      <label class="form-label">เงินฝาก
        <input name="ru_deposit" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_deposit']); ?>">
      </label>
    </div>
    <div class="col-md-2">
      <label class="form-label">กู้ซื้อขาย
        <input name="ru_tradeloan" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_tradeloan']); ?>">
      </label>
    </div>
    <div class="col-md-2">
      <label class="form-label">ประกันภัย
        <input name="ru_insurance" required inputmode="decimal" class="form-control text-end" value="<?php echo e($form['ru_insurance']); ?>">
      </label>
    </div>

  
        <input name="ru_saveby" type="hidden"  class="form-control" value="<?php echo e($form['ru_saveby']); ?>">
        <input type="hidden" name="ru_savedate"  class="form-control" value="<?php echo e($form['ru_savedate']); ?>">
     
  </div>

  <div class="small mt-3 text-muted">
    <?php echo e(!empty($form['ru_id']) ? 'แก้ไขรายการ #' . (int)$form['ru_id'] : 'เพิ่มรายการใหม่'); ?>
  </div>
  <div class="mt-3">
    <button type="submit" class="btn btn-primary">บันทึก</button>
    <?php if (!empty($form['ru_id'])): ?>
      <a href="rubbers.php?lan=<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>" class="btn btn-outline-secondary ms-2">ยกเลิกแก้ไข</a>
    <?php endif; ?>
  </div>
  </div>
</form>

<table class="table table-striped table-hover table-bordered">
  <thead class="table-light">
    <tr>
      <th>ID</th>
      <th>วันที่</th>
      <th>ลาน</th>
      <th>กลุ่ม</th>
      <th>เลขที่</th>
      <th>ชื่อ-สกุล</th>
      <th>ชั้น</th>
      <th class="text-end">ปริมาณ</th>
      <th class="text-end">หุ้น</th>
      <th class="text-end">เงินกู้</th>
      <th class="text-end">หนี้สั้น</th>
      <th class="text-end">เงินฝาก</th>
      <th class="text-end">กู้ซื้อขาย</th>
      <th class="text-end">ประกันภัย</th>
      <th>จัดการ</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="17" style="text-align:center;color:#64748b;padding:20px;">ยังไม่มีข้อมูล</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?php echo (int)$r['ru_id']; ?></td>
        <td><?php echo e($r['ru_date']); ?></td>
        <td><?php echo e($r['ru_lan']); ?></td>
        <td><?php echo e($r['ru_group']); ?></td>
        <td><?php echo e($r['ru_number']); ?></td>
        <td><?php echo e($r['ru_fullname']); ?></td>
        <td><?php echo e($r['ru_class']); ?></td>
        <td class="text-end"><?php echo number_format((float)$r['ru_quantity'], 2); ?></td>
        <td class="text-end"><?php echo number_format((float)$r['ru_hoon'], 2); ?></td>
        <td class="text-end"><?php echo number_format((float)$r['ru_loan'], 2); ?></td>
        <td class="text-end"><?php echo number_format((float)$r['ru_shortdebt'], 2); ?></td>
        <td class="text-end"><?php echo number_format((float)$r['ru_deposit'], 2); ?></td>
        <td class="text-end"><?php echo number_format((float)$r['ru_tradeloan'], 2); ?></td>
        <td class="text-end"><?php echo number_format((float)$r['ru_insurance'], 2); ?></td>
        <td>
          <div class="d-flex gap-1">
            <a href="rubbers.php?lan=<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>&action=edit&id=<?php echo (int)$r['ru_id']; ?>" class="btn btn-sm btn-warning">แก้ไข</a>
            <form method="post" onsubmit="return confirm('ลบรายการนี้?');" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="lan" value="<?php echo ($currentLan === 'all') ? 'all' : (int)$currentLan; ?>">
              <input type="hidden" name="ru_id" value="<?php echo (int)$r['ru_id']; ?>">
              <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<!-- added: inline JS for member search/selection -->
<script>
(function(){
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

  let t = null;

  function hideList(){ list.hidden = true; list.innerHTML = ''; }
  function lockFields(lock){
    [fGroup, fNumber, fName, fClass].forEach(el => {
      if (lock) el.setAttribute('readonly','');
      else el.removeAttribute('readonly');
    });
  }
  function renderSelected(m){
    selectedBox.hidden = false;
    selectedBox.innerHTML = `ใช้สมาชิก: <span class="tag">#${m.mem_id}</span> ${m.mem_fullname} | กลุ่ม: ${m.mem_group} | เลขที่: ${m.mem_number} | ชั้น: ${m.mem_class} <button type="button" id="clearMember" class="link-btn">เปลี่ยน</button>`;
    // re-bind clear after replacing innerHTML
    selectedBox.querySelector('#clearMember').addEventListener('click', () => {
      hid.value = '';
      lockFields(false);
      selectedBox.hidden = true;
    });
  }

  function pick(m){
    hid.value = m.mem_id;
    fGroup.value = m.mem_group;
    fNumber.value = m.mem_number;
    fName.value  = m.mem_fullname;
    fClass.value = m.mem_class;
    lockFields(true);
    renderSelected(m);
    q.value = '';
    hideList();
  }

  function search(term){
    if (!term || term.length < 2){ hideList(); return; }
    fetch('members_search.php?q='+encodeURIComponent(term))
      .then(r => r.ok ? r.json() : [])
      .then(rows => {
        if (!Array.isArray(rows) || rows.length === 0){ hideList(); return; }
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

  if (clearBtn){
    clearBtn.addEventListener('click', () => {
      hid.value = '';
      lockFields(false);
      selectedBox.hidden = true;
    });
  }
})();
</script>

</div><!-- /container -->
</body>
</html>

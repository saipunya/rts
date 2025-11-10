<?php
require_once __DIR__ . '/functions.php';

$db = db();
$errors = [];
$msg = $_GET['msg'] ?? '';
$csrf = csrf_token();
$default_saveby = $_SESSION['user_name'] ?? 'เจ้าหน้าที่';
$today = date('Y-m-d');

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
	'ru_lan' => '',
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
				header('Location: rubbers.php?msg=' . urlencode('ลบรายการแล้ว'));
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
			$fieldsText = ['ru_lan','ru_group','ru_number','ru_fullname','ru_class','ru_saveby'];
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
					header('Location: rubbers.php?msg=' . urlencode('บันทึกการแก้ไขแล้ว'));
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
					header('Location: rubbers.php?msg=' . urlencode('บันทึกข้อมูลแล้ว'));
					exit;
				}
			} else {
				$form = array_merge($form, $_POST); // คืนค่าฟอร์มเดิมเมื่อมี error
			}
		}
	}
}

// ดึงข้อมูลรายการ
$rows = [];
$res = $db->query("SELECT * FROM tbl_rubber ORDER BY ru_date DESC, ru_id DESC");
if ($res) {
	while ($r = $res->fetch_assoc()) $rows[] = $r;
	$res->free();
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการข้อมูลยางพารา</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body { font-family: system-ui, Arial, sans-serif; margin:20px; background:#f8fafc; color:#0f172a;}
h1 { margin:0 0 16px; }
form, table { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px; }
label { display:block; margin-bottom:8px; font-size:14px; }
input { width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; }
.grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); }
button { padding:10px 16px; background:#2563eb; color:#fff; border:none; border-radius:8px; cursor:pointer; }
button:hover { background:#1d4ed8; }
.msg { margin:12px 0; padding:10px 14px; border-radius:8px; font-size:14px; }
.msg.ok { background:#dcfce7; color:#065f46; border:1px solid #86efac; }
.msg.err { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
table { width:100%; border-collapse:collapse; margin-top:24px; }
th,td { padding:8px 10px; text-align:left; border-bottom:1px solid #e2e8f0; font-size:13px; }
th { background:#eff6ff; color:#1e3a8a; position:sticky; top:0; }
td.num { text-align:right; font-variant-numeric: tabular-nums; }
.actions { display:flex; gap:6px; }
.small { font-size:12px; color:#64748b; margin-top:4px; }

/* added: nicer member chooser and dropdown */
.member-chooser { grid-column: 1 / -1; position: relative; }
.member-chooser .dropdown { 
  position:absolute; left:0; right:0; top:100%; z-index:10; 
  background:#fff; border:1px solid #e2e8f0; border-radius:8px; margin-top:4px; 
  max-height:260px; overflow:auto; list-style:none; padding:4px 0;
}
.member-chooser .dropdown li { padding:8px 10px; cursor:pointer; }
.member-chooser .dropdown li:hover { background:#f1f5f9; }
.link-btn { background:transparent; color:#2563eb; border:none; cursor:pointer; padding:0 4px; }
.tag { display:inline-block; padding:4px 8px; background:#eef2ff; color:#3730a3; border-radius:999px; font-size:12px; }
</style>
</head>
<body>
<h1>จัดการข้อมูลยางพารา</h1>

<?php if ($msg): ?>
  <div class="msg ok"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="msg err"><?php echo e(implode(' | ', $errors)); ?></div>
<?php endif; ?>

<?php
// removed: legacy GET-based member search UI. Replaced by inline search widget below.
// (The section between `<?php if (empty($form['ru_id'])): ?>` and its endif has been deleted)
?>

<form method="post" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
  <input type="hidden" name="action" value="save">
  <!-- changed: always include ru_member_id, prefill if selected via GET -->
  <input type="hidden" id="ru_member_id" name="ru_member_id" value="<?php echo !empty($memberSelectedRow['mem_id']) ? (int)$memberSelectedRow['mem_id'] : ''; ?>">
  <?php if (!empty($form['ru_id'])): ?>
    <input type="hidden" name="ru_id" value="<?php echo (int)$form['ru_id']; ?>">
  <?php endif; ?>
  <div class="grid">

    <?php if (empty($form['ru_id'])): ?>
    <!-- added: inline member search/typeahead -->
    <div class="member-chooser">
      <label>ค้นหาสมาชิก
        <input id="memberSearch" type="text" placeholder="พิมพ์ชื่อ / เลขที่ / กลุ่ม / ชั้น เพื่อค้นหา">
      </label>
      <ul id="memberResults" class="dropdown" hidden></ul>
      <div id="memberSelected" class="small" <?php if (empty($memberSelectedRow)) echo 'hidden'; ?>>
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

    <div><label>วันที่ <input type="date" name="ru_date" required value="<?php echo e($form['ru_date']); ?>"></label></div>
    <div><label>แลน <input name="ru_lan" required value="<?php echo e($form['ru_lan']); ?>"></label></div>

    <!-- updated: add IDs for JS and respect readonly when initially selected -->
    <div><label>กลุ่ม
      <input id="ru_group" name="ru_group" required <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_group']); ?>">
    </label></div>
    <div><label>เลขที่
      <input id="ru_number" name="ru_number" required <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_number']); ?>">
    </label></div>
    <div><label>ชื่อ-สกุล
      <input id="ru_fullname" name="ru_fullname" required <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_fullname']); ?>">
    </label></div>
    <div><label>ชั้น
      <input id="ru_class" name="ru_class" required <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_class']); ?>">
    </label></div>

    <div><label>ปริมาณ <input name="ru_quantity" required inputmode="decimal" value="<?php echo e($form['ru_quantity']); ?>"></label></div>
    <div><label>หุ้น <input name="ru_hoon" required inputmode="decimal" value="<?php echo e($form['ru_hoon']); ?>"></label></div>
    <div><label>เงินกู้ <input name="ru_loan" required inputmode="decimal" value="<?php echo e($form['ru_loan']); ?>"></label></div>
    <div><label>หนี้ค้างสั้น <input name="ru_shortdebt" required inputmode="decimal" value="<?php echo e($form['ru_shortdebt']); ?>"></label></div>
    <div><label>เงินฝาก <input name="ru_deposit" required inputmode="decimal" value="<?php echo e($form['ru_deposit']); ?>"></label></div>
    <div><label>กู้ซื้อขาย <input name="ru_tradeloan" required inputmode="decimal" value="<?php echo e($form['ru_tradeloan']); ?>"></label></div>
    <div><label>ประกันภัย <input name="ru_insurance" required inputmode="decimal" value="<?php echo e($form['ru_insurance']); ?>"></label></div>

    <div><label>ผู้บันทึก <input name="ru_saveby" required value="<?php echo e($form['ru_saveby']); ?>"></label></div>
    <div><label>บันทึกเมื่อ <input type="date" name="ru_savedate" required value="<?php echo e($form['ru_savedate']); ?>"></label></div>
  </div>
  <div class="small"><?php echo e(!empty($form['ru_id']) ? 'แก้ไขรายการ #' . (int)$form['ru_id'] : 'เพิ่มรายการใหม่'); ?></div>
  <div style="margin-top:12px;">
    <button type="submit">บันทึก</button>
    <?php if (!empty($form['ru_id'])): ?>
      <a href="rubbers.php" style="margin-left:8px;">ยกเลิกแก้ไข</a>
    <?php endif; ?>
  </div>
</form>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>วันที่</th>
      <th>แลน</th>
      <th>กลุ่ม</th>
      <th>เลขที่</th>
      <th>ชื่อ-สกุล</th>
      <th>ชั้น</th>
      <th class="num">ปริมาณ</th>
      <th class="num">หุ้น</th>
      <th class="num">เงินกู้</th>
      <th class="num">หนี้สั้น</th>
      <th class="num">เงินฝาก</th>
      <th class="num">กู้ซื้อขาย</th>
      <th class="num">ประกันภัย</th>
      <th>ผู้บันทึก</th>
      <th>บันทึกเมื่อ</th>
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
        <td class="num"><?php echo number_format((float)$r['ru_quantity'], 2); ?></td>
        <td class="num"><?php echo number_format((float)$r['ru_hoon'], 2); ?></td>
        <td class="num"><?php echo number_format((float)$r['ru_loan'], 2); ?></td>
        <td class="num"><?php echo number_format((float)$r['ru_shortdebt'], 2); ?></td>
        <td class="num"><?php echo number_format((float)$r['ru_deposit'], 2); ?></td>
        <td class="num"><?php echo number_format((float)$r['ru_tradeloan'], 2); ?></td>
        <td class="num"><?php echo number_format((float)$r['ru_insurance'], 2); ?></td>
        <td><?php echo e($r['ru_saveby']); ?></td>
        <td><?php echo e($r['ru_savedate']); ?></td>
        <td>
          <div class="actions">
            <a href="rubbers.php?action=edit&id=<?php echo (int)$r['ru_id']; ?>">แก้ไข</a>
            <form method="post" onsubmit="return confirm('ลบรายการนี้?');" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ru_id" value="<?php echo (int)$r['ru_id']; ?>">
              <button type="submit" style="background:#ef4444;">ลบ</button>
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
          <li data-item='${JSON.stringify(m).replace(/'/g, "&apos;")}'>
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

</body>
</html>

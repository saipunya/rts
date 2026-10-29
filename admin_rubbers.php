<?php
require_once 'functions.php';
require_admin();
include 'header.php';

$db = db();
ensure_weighing_schema($db);
$msg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$errors = [];
$csrf = csrf_token();
$currentUser = current_user();

function admin_rubber_redirect(array $params = []): void {
    $base = [
        'q' => $_GET['q'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'lan' => $_GET['lan'] ?? '',
        'class' => $_GET['class'] ?? '',
        'page' => $_GET['page'] ?? '',
    ];
    $query = http_build_query(array_filter(array_merge($base, $params), static fn($v) => $v !== '' && $v !== null));
    header('Location: admin_rubbers.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

function admin_rubber_valid_date(string $date): bool {
    if ($date === '') {
        return true;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

$emptyRubber = [
    'ru_id' => 0,
    'ru_date' => date('Y-m-d'),
    'ru_lan' => '1',
    'ru_group' => '',
    'ru_number' => '',
    'ru_fullname' => '',
    'ru_class' => 'member',
    'ru_quantity' => '0.00',
    'ru_value' => '0.00',
    'ru_hoon' => '0.00',
    'ru_loan' => '0.00',
    'ru_shortdebt' => '0.00',
    'ru_deposit' => '0.00',
    'ru_tradeloan' => '0.00',
    'ru_insurance' => '0.00',
    'ru_expend' => '0.00',
    'ru_netvalue' => '0.00',
    'ru_saveby' => $currentUser['user_fullname'] ?? $currentUser['user_username'] ?? 'ผู้ดูแลระบบ',
    'ru_savedate' => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'โทเค็นไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['ru_id'] ?? 0);

        if ($action === 'delete') {
            if ($id <= 0) {
                admin_rubber_redirect(['msg' => 'ไม่พบรายการที่ต้องการลบ']);
            }

            if (rubber_receipt_issued($db, $id) || weighing_for_rubber($db, $id)) {
                admin_rubber_redirect(['msg'=>'รายการนี้เชื่อมกับผลชั่งหรือใบเสร็จ ให้ดำเนินการแก้ไขข้อมูลแทนการลบ']);
            }
            $stmt = $db->prepare('DELETE FROM tbl_rubber WHERE ru_id = ? LIMIT 1');
            if (!$stmt) {
                die('Prepare failed: ' . $db->error);
            }
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            admin_rubber_redirect(['msg' => $ok ? 'ลบข้อมูลยางเรียบร้อยแล้ว' : 'ลบข้อมูลไม่สำเร็จ']);
        }

        if ($action === 'save') {
            if ($id <= 0) {
                $errors[] = 'ไม่พบรายการที่ต้องการแก้ไข';
            }

            $data = [];
            $textFields = ['ru_lan', 'ru_group', 'ru_number', 'ru_fullname', 'ru_class', 'ru_saveby'];
            $dateFields = ['ru_date', 'ru_savedate'];
            $numberFields = [
                'ru_quantity', 'ru_value', 'ru_hoon', 'ru_loan', 'ru_shortdebt',
                'ru_deposit', 'ru_tradeloan', 'ru_insurance', 'ru_expend', 'ru_netvalue',
            ];

            foreach ($textFields as $field) {
                $data[$field] = trim((string)($_POST[$field] ?? ''));
                if ($data[$field] === '') {
                    $errors[] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
                }
            }

            foreach ($dateFields as $field) {
                $data[$field] = trim((string)($_POST[$field] ?? ''));
                if ($data[$field] === '' || !admin_rubber_valid_date($data[$field])) {
                    $errors[] = 'รูปแบบวันที่ไม่ถูกต้อง';
                }
            }

            foreach ($numberFields as $field) {
                $raw = trim((string)($_POST[$field] ?? '0'));
                $value = filter_var($raw, FILTER_VALIDATE_FLOAT);
                if ($value === false) {
                    $errors[] = 'ข้อมูลตัวเลขไม่ถูกต้อง';
                    $value = 0;
                }
                $data[$field] = number_format((float)$value, 2, '.', '');
            }

            $lan = (int)$data['ru_lan'];
            if (!in_array($lan, [1, 2, 3, 4], true)) {
                $errors[] = 'ลานรับซื้อไม่ถูกต้อง';
            }
            $data['ru_lan'] = (string)$lan;

            $class = strtolower(trim($data['ru_class']));
            if (!in_array($class, ['member', 'general'], true)) {
                $errors[] = 'ประเภทข้อมูลไม่ถูกต้อง';
            }
            $data['ru_class'] = $class;

            if (!$errors) {
                $db->begin_transaction();
                try {
                lock_rubber_for_edit($db, $id, is_admin());
                assert_weighed_weight_unchanged($db, $id, $data['ru_quantity']);
                $auditStmt = $db->prepare('SELECT * FROM tbl_rubber WHERE ru_id=?');
                $auditStmt->bind_param('i', $id); $auditStmt->execute(); $auditBefore = $auditStmt->get_result()->fetch_assoc(); $auditStmt->close();
                $stmt = $db->prepare("UPDATE tbl_rubber SET
                    ru_date = ?, ru_lan = ?, ru_group = ?, ru_number = ?, ru_fullname = ?, ru_class = ?,
                    ru_quantity = ?, ru_value = ?, ru_hoon = ?, ru_loan = ?, ru_shortdebt = ?, ru_deposit = ?,
                    ru_tradeloan = ?, ru_insurance = ?, ru_expend = ?, ru_netvalue = ?, ru_saveby = ?, ru_savedate = ?
                    WHERE ru_id = ?");
                if (!$stmt) {
                    die('Prepare failed: ' . $db->error);
                }
                $stmt->bind_param(
                    str_repeat('s', 18) . 'i',
                    $data['ru_date'],
                    $data['ru_lan'],
                    $data['ru_group'],
                    $data['ru_number'],
                    $data['ru_fullname'],
                    $data['ru_class'],
                    $data['ru_quantity'],
                    $data['ru_value'],
                    $data['ru_hoon'],
                    $data['ru_loan'],
                    $data['ru_shortdebt'],
                    $data['ru_deposit'],
                    $data['ru_tradeloan'],
                    $data['ru_insurance'],
                    $data['ru_expend'],
                    $data['ru_netvalue'],
                    $data['ru_saveby'],
                    $data['ru_savedate'],
                    $id
                );
                $ok = $stmt->execute();
                $stmt->close();
                record_collection_audit($db, 'purchase_admin_edit', 0, $id, '', ['before'=>$auditBefore, 'after'=>$data]);
                record_receipt_admin_edit($db, $id, (string)($currentUser['user_fullname'] ?: $currentUser['user_username']));
                $db->commit();
                } catch (DomainException $error) { $db->rollback(); $errors[] = $error->getMessage(); }
                catch (Throwable $error) { $db->rollback(); throw $error; }
                if (!$errors) admin_rubber_redirect(['msg' => $ok ? 'บันทึกการแก้ไขข้อมูลยางเรียบร้อยแล้ว' : 'บันทึกข้อมูลไม่สำเร็จ']);
            }
        }
    }
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRubber = null;
if ($editId > 0) {
    $stmt = $db->prepare('SELECT * FROM tbl_rubber WHERE ru_id = ? LIMIT 1');
    if (!$stmt) {
        die('Prepare failed: ' . $db->error);
    }
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    $editRubber = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$editRubber) {
        $msg = 'ไม่พบรายการที่ต้องการแก้ไข';
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$lanFilter = trim((string)($_GET['lan'] ?? 'all'));
$classFilter = trim((string)($_GET['class'] ?? 'all'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$conds = [];
$types = '';
$binds = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $conds[] = '(ru_group LIKE ? OR ru_number LIKE ? OR ru_fullname LIKE ? OR ru_saveby LIKE ?)';
    $types .= 'ssss';
    array_push($binds, $like, $like, $like, $like);
}

if (admin_rubber_valid_date($dateFrom) && $dateFrom !== '') {
    $conds[] = 'ru_date >= ?';
    $types .= 's';
    $binds[] = $dateFrom;
}

if (admin_rubber_valid_date($dateTo) && $dateTo !== '') {
    $conds[] = 'ru_date <= ?';
    $types .= 's';
    $binds[] = $dateTo;
}

if (in_array($lanFilter, ['1', '2', '3', '4'], true)) {
    $conds[] = 'ru_lan = ?';
    $types .= 's';
    $binds[] = $lanFilter;
}

if (in_array($classFilter, ['member', 'general'], true)) {
    $conds[] = 'LOWER(TRIM(ru_class)) = ?';
    $types .= 's';
    $binds[] = $classFilter;
}

$whereSql = $conds ? ' WHERE ' . implode(' AND ', $conds) : '';

$totalRows = 0;
$stmt = $db->prepare('SELECT COUNT(*) AS total_rows FROM tbl_rubber' . $whereSql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$binds);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $totalRows = (int)($row['total_rows'] ?? 0);
    }
    $stmt->close();
}

$summary = [
    'total_quantity' => 0.0,
    'total_value' => 0.0,
    'total_expend' => 0.0,
    'total_netvalue' => 0.0,
];
$stmt = $db->prepare("SELECT
    COALESCE(SUM(ru_quantity), 0) AS total_quantity,
    COALESCE(SUM(ru_value), 0) AS total_value,
    COALESCE(SUM(ru_expend), 0) AS total_expend,
    COALESCE(SUM(ru_netvalue), 0) AS total_netvalue
    FROM tbl_rubber" . $whereSql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$binds);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        foreach ($summary as $key => $value) {
            $summary[$key] = (float)($row[$key] ?? 0);
        }
    }
    $stmt->close();
}

$totalPages = max(1, (int)ceil(max(1, $totalRows) / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$rubbers = [];
$stmt = $db->prepare('SELECT * FROM tbl_rubber' . $whereSql . ' ORDER BY ru_date DESC, ru_id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$binds);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rubbers[] = $row;
        }
    }
    $stmt->close();
}

$queryBase = [
    'q' => $q,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'lan' => $lanFilter,
    'class' => $classFilter,
];
$pageUrl = static function (int $targetPage) use ($queryBase): string {
    return 'admin_rubbers.php?' . http_build_query(array_filter(array_merge($queryBase, ['page' => $targetPage]), static fn($v) => $v !== '' && $v !== null));
};

$formRubber = $editRubber ?: $emptyRubber;
$formWeighing = $editRubber ? weighing_for_rubber($db, (int)$editRubber['ru_id']) : null;
?>
<style>
body {
  font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", sans-serif;
  background: #f6f8fb;
}

.admin-rubber-page {
  max-width: 1320px;
}

.admin-rubber-hero,
.admin-rubber-panel,
.admin-rubber-stat {
  border: 1px solid #d9e4dd;
  border-radius: 1rem;
  background: #fff;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.admin-rubber-hero {
  background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
}

.admin-rubber-icon,
.admin-rubber-stat-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.admin-rubber-icon {
  width: 3rem;
  height: 3rem;
  border-radius: .85rem;
  color: #fff;
  background: #15803d;
}

.admin-rubber-stat-icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: .75rem;
  color: #166534;
  background: #dcfce7;
}

.admin-rubber-page .form-control,
.admin-rubber-page .form-select,
.admin-rubber-page .btn {
  min-height: 42px;
  border-radius: .75rem;
}

.admin-rubber-page .btn {
  font-weight: 700;
}

.admin-rubber-page .table th {
  white-space: nowrap;
  background: #f0fdf4;
  color: #166534;
}

.admin-rubber-page .table td {
  vertical-align: middle;
}

.admin-rubber-page .number-cell {
  font-variant-numeric: tabular-nums;
}

.admin-rubber-actions .btn {
  min-width: 42px;
}

@media (max-width: 768px) {
  .admin-rubber-hero,
  .admin-rubber-panel,
  .admin-rubber-stat {
    border-radius: .85rem;
  }
}
</style>

<div class="container admin-rubber-page my-4">
  <section class="admin-rubber-hero p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="admin-rubber-icon"><i data-lucide="database" aria-hidden="true"></i></span>
        <div>
          <div class="text-uppercase text-success fw-semibold small mb-1">ผู้ดูแลระบบ</div>
          <h1 class="h3 fw-bold mb-1 text-success-emphasis">จัดการข้อมูลยาง</h1>
          <div class="text-success">ค้นหา แก้ไข และลบข้อมูลจากตาราง tbl_rubber</div>
        </div>
      </div>
      <a href="dashboard.php" class="btn btn-outline-success">
        <i data-lucide="arrow-left" class="me-1" aria-hidden="true"></i>กลับแดชบอร์ด
      </a>
    </div>
  </section>

  <?php if ($msg !== ''): ?>
  <div class="alert alert-info border-0 shadow-sm">
    <i data-lucide="info" class="me-1" aria-hidden="true"></i><?php echo e($msg); ?>
  </div>
  <?php endif; ?>

  <?php if ($errors): ?>
  <div class="alert alert-danger border-0 shadow-sm">
    <i data-lucide="triangle-alert" class="me-1" aria-hidden="true"></i><?php echo e(implode(' ', array_unique($errors))); ?>
  </div>
  <?php endif; ?>

  <section class="row g-3 mb-4">
    <div class="col-12 col-md-3">
      <div class="admin-rubber-stat p-3">
        <div class="d-flex align-items-center gap-3">
          <span class="admin-rubber-stat-icon"><i data-lucide="list" aria-hidden="true"></i></span>
          <div><div class="text-success fw-semibold">รายการที่พบ</div><div class="h4 mb-0 fw-bold"><?php echo number_format($totalRows); ?></div></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="admin-rubber-stat p-3">
        <div class="d-flex align-items-center gap-3">
          <span class="admin-rubber-stat-icon"><i data-lucide="scale" aria-hidden="true"></i></span>
          <div><div class="text-success fw-semibold">ปริมาณรวม</div><div class="h4 mb-0 fw-bold"><?php echo number_format($summary['total_quantity'], 2); ?></div></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="admin-rubber-stat p-3">
        <div class="d-flex align-items-center gap-3">
          <span class="admin-rubber-stat-icon"><i data-lucide="circle-dollar-sign" aria-hidden="true"></i></span>
          <div><div class="text-success fw-semibold">มูลค่ารวม</div><div class="h4 mb-0 fw-bold"><?php echo number_format($summary['total_value'], 2); ?></div></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="admin-rubber-stat p-3">
        <div class="d-flex align-items-center gap-3">
          <span class="admin-rubber-stat-icon"><i data-lucide="wallet" aria-hidden="true"></i></span>
          <div><div class="text-success fw-semibold">สุทธิรวม</div><div class="h4 mb-0 fw-bold"><?php echo number_format($summary['total_netvalue'], 2); ?></div></div>
        </div>
      </div>
    </div>
  </section>

  <section class="admin-rubber-panel p-3 p-md-4 mb-4">
    <form method="get" class="row g-3 align-items-end">
      <div class="col-12 col-lg-4">
        <label class="form-label" for="q"><i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา</label>
        <input type="text" class="form-control" id="q" name="q" value="<?php echo e($q); ?>" placeholder="ชื่อ เลขสมาชิก กลุ่ม หรือผู้บันทึก">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="date_from">ตั้งแต่วันที่</label>
        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo e($dateFrom); ?>">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="date_to">ถึงวันที่</label>
        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo e($dateTo); ?>">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="lan">ลาน</label>
        <select class="form-select" id="lan" name="lan">
          <option value="all" <?php echo $lanFilter === 'all' ? 'selected' : ''; ?>>ทุกลาน</option>
          <?php foreach ([1, 2, 3, 4] as $lan): ?>
          <option value="<?php echo $lan; ?>" <?php echo $lanFilter === (string)$lan ? 'selected' : ''; ?>>ลาน <?php echo $lan; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="class">ประเภท</label>
        <select class="form-select" id="class" name="class">
          <option value="all" <?php echo $classFilter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
          <option value="member" <?php echo $classFilter === 'member' ? 'selected' : ''; ?>>สมาชิก</option>
          <option value="general" <?php echo $classFilter === 'general' ? 'selected' : ''; ?>>เกษตรกรทั่วไป</option>
        </select>
      </div>
      <div class="col-12 d-flex flex-column flex-sm-row gap-2 justify-content-end">
        <a href="admin_rubbers.php" class="btn btn-outline-secondary">
          <i data-lucide="x" class="me-1" aria-hidden="true"></i>ล้าง
        </a>
        <button type="submit" class="btn btn-success">
          <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา
        </button>
      </div>
    </form>
  </section>

  <?php if ($editRubber): ?>
  <section class="admin-rubber-panel p-3 p-md-4 mb-4" id="edit-form">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
      <div>
        <h2 class="h5 fw-bold mb-1 text-success-emphasis"><i data-lucide="pencil" class="me-1" aria-hidden="true"></i>แก้ไขรายการ #<?php echo (int)$formRubber['ru_id']; ?></h2>
        <div class="text-secondary">แก้ไขข้อมูลตามคอลัมน์ใน tbl_rubber</div>
      </div>
      <a href="admin_rubbers.php?<?php echo http_build_query(array_filter($queryBase, static fn($v) => $v !== '' && $v !== null)); ?>" class="btn btn-outline-secondary align-self-start">
        <i data-lucide="x" class="me-1" aria-hidden="true"></i>ยกเลิกแก้ไข
      </a>
    </div>

    <form method="post" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="ru_id" value="<?php echo (int)$formRubber['ru_id']; ?>">

      <div class="col-12 col-md-3">
        <label class="form-label" for="ru_date">วันที่ชั่ง</label>
        <input type="date" class="form-control" id="ru_date" name="ru_date" value="<?php echo e($formRubber['ru_date']); ?>" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="ru_lan">ลาน</label>
        <select class="form-select" id="ru_lan" name="ru_lan" required>
          <?php foreach ([1, 2, 3, 4] as $lan): ?>
          <option value="<?php echo $lan; ?>" <?php echo (string)$formRubber['ru_lan'] === (string)$lan ? 'selected' : ''; ?>>ลาน <?php echo $lan; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="ru_group">กลุ่ม</label>
        <input type="text" class="form-control" id="ru_group" name="ru_group" value="<?php echo e($formRubber['ru_group']); ?>" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="ru_number">เลขที่</label>
        <input type="text" class="form-control" id="ru_number" name="ru_number" value="<?php echo e($formRubber['ru_number']); ?>" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label" for="ru_fullname">ชื่อ-สกุล</label>
        <input type="text" class="form-control" id="ru_fullname" name="ru_fullname" value="<?php echo e($formRubber['ru_fullname']); ?>" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="ru_class">ประเภท</label>
        <select class="form-select" id="ru_class" name="ru_class" required>
          <option value="member" <?php echo strtolower((string)$formRubber['ru_class']) === 'member' ? 'selected' : ''; ?>>สมาชิก</option>
          <option value="general" <?php echo strtolower((string)$formRubber['ru_class']) === 'general' ? 'selected' : ''; ?>>เกษตรกรทั่วไป</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="ru_quantity">ปริมาณ</label>
        <input type="number" step="0.01" class="form-control" id="ru_quantity" name="ru_quantity" value="<?php echo e($formRubber['ru_quantity']); ?>" <?php echo $formWeighing ? 'readonly aria-describedby="weight-lock-help"' : ''; ?> required>
        <?php if ($formWeighing): ?><div class="form-text" id="weight-lock-help">น้ำหนักอ้างอิงจากผลชั่ง แก้ไขได้ที่หน้าชั่งก่อนอนุมัติหรือส่งตรวจสอบยอดหักเท่านั้น</div><?php endif; ?>
      </div>

      <?php
        $moneyFields = [
          'ru_value' => 'มูลค่า',
          'ru_hoon' => 'หุ้น',
          'ru_loan' => 'เงินกู้',
          'ru_shortdebt' => 'หนี้สั้น',
          'ru_deposit' => 'เงินฝาก',
          'ru_tradeloan' => 'กู้ซื้อขาย',
          'ru_insurance' => 'ประกันภัย',
          'ru_expend' => 'ยอดหักรวม',
          'ru_netvalue' => 'ยอดสุทธิ',
        ];
      ?>
      <?php foreach ($moneyFields as $field => $label): ?>
      <div class="col-12 col-md-4 col-lg-3">
        <label class="form-label" for="<?php echo e($field); ?>"><?php echo e($label); ?></label>
        <input type="number" step="0.01" class="form-control" id="<?php echo e($field); ?>" name="<?php echo e($field); ?>" value="<?php echo e($formRubber[$field]); ?>" required>
      </div>
      <?php endforeach; ?>

      <div class="col-12 col-md-6">
        <label class="form-label" for="ru_saveby">ผู้บันทึก</label>
        <input type="text" class="form-control" id="ru_saveby" name="ru_saveby" value="<?php echo e($formRubber['ru_saveby']); ?>" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="ru_savedate">วันที่บันทึก</label>
        <input type="date" class="form-control" id="ru_savedate" name="ru_savedate" value="<?php echo e($formRubber['ru_savedate']); ?>" required>
      </div>
      <div class="col-12 d-flex flex-column flex-sm-row gap-2 justify-content-end">
        <button type="submit" class="btn btn-success">
          <i data-lucide="save" class="me-1" aria-hidden="true"></i>บันทึกการแก้ไข
        </button>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <section class="admin-rubber-panel p-0 overflow-hidden">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 px-3 px-md-4 py-3 border-bottom">
      <div class="fw-semibold text-success-emphasis">
        <i data-lucide="table" class="me-1" aria-hidden="true"></i>รายการข้อมูลยาง
      </div>
      <div class="text-secondary">หน้า <?php echo number_format($page); ?> / <?php echo number_format($totalPages); ?></div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>วันที่</th>
            <th>ลาน</th>
            <th>กลุ่ม/เลขที่</th>
            <th>ชื่อ-สกุล</th>
            <th>ประเภท</th>
            <th class="text-end">ปริมาณ</th>
            <th class="text-end">มูลค่า</th>
            <th class="text-end">สุทธิ</th>
            <th>ผู้บันทึก</th>
            <th class="text-end">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rubbers): ?>
          <tr><td colspan="11" class="text-center text-secondary py-5">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</td></tr>
          <?php endif; ?>
          <?php foreach ($rubbers as $row): ?>
          <tr>
            <td class="fw-semibold"><?php echo (int)$row['ru_id']; ?></td>
            <td class="text-nowrap"><?php echo e(thai_date_format((string)$row['ru_date'])); ?></td>
            <td><?php echo e($row['ru_lan']); ?></td>
            <td><?php echo e($row['ru_group']); ?> / <?php echo e($row['ru_number']); ?></td>
            <td><?php echo e($row['ru_fullname']); ?></td>
            <td>
              <?php if (strtolower((string)$row['ru_class']) === 'member'): ?>
              <span class="badge text-bg-success">สมาชิก</span>
              <?php else: ?>
              <span class="badge text-bg-warning text-dark">ทั่วไป</span>
              <?php endif; ?>
            </td>
            <td class="text-end number-cell"><?php echo number_format((float)$row['ru_quantity'], 2); ?></td>
            <td class="text-end number-cell"><?php echo number_format((float)$row['ru_value'], 2); ?></td>
            <td class="text-end number-cell fw-semibold"><?php echo number_format((float)$row['ru_netvalue'], 2); ?></td>
            <td><?php echo e($row['ru_saveby']); ?><br><a class="btn btn-sm collection-history-btn" title="ประวัติผู้ดำเนินการ" href="collection_history.php?ru_id=<?php echo (int)$row['ru_id']; ?>"><i data-lucide="history" aria-hidden="true"></i>ประวัติ</a></td>
            <td class="text-end">
              <div class="d-inline-flex gap-2 admin-rubber-actions">
                <a href="<?php echo e('admin_rubbers.php?' . http_build_query(array_filter(array_merge($queryBase, ['page' => $page, 'edit' => (int)$row['ru_id']]), static fn($v) => $v !== '' && $v !== null))); ?>#edit-form"
                  class="btn btn-sm btn-outline-primary" title="แก้ไข" aria-label="แก้ไข">
                  <i data-lucide="pencil" aria-hidden="true"></i>
                </a>
                <form method="post" class="m-0" onsubmit="return confirm('ลบข้อมูลยางรายการนี้หรือไม่?');">
                  <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="ru_id" value="<?php echo (int)$row['ru_id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ" aria-label="ลบ">
                    <i data-lucide="trash-2" aria-hidden="true"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 px-md-4 py-3 border-top">
      <div class="text-secondary">แสดง <?php echo number_format(count($rubbers)); ?> จาก <?php echo number_format($totalRows); ?> รายการ</div>
      <div class="btn-group">
        <a class="btn btn-outline-success <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo e($pageUrl(max(1, $page - 1))); ?>">
          <i data-lucide="chevron-left" aria-hidden="true"></i>
        </a>
        <span class="btn btn-outline-success disabled"><?php echo number_format($page); ?></span>
        <a class="btn btn-outline-success <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo e($pageUrl(min($totalPages, $page + 1))); ?>">
          <i data-lucide="chevron-right" aria-hidden="true"></i>
        </a>
      </div>
    </div>
    <?php endif; ?>
  </section>
</div>

<?php include 'footer.php'; ?>

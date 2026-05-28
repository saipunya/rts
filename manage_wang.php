<?php
require_once 'functions.php';
require_login();

$db = db();
$message = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$error = '';
$canManageWang = function_exists('is_admin') && is_admin();

function ensure_wang_manage_table(mysqli $db): void {
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
    if ($dbName === '') {
        return;
    }

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

function redirect_manage(array $params = []): void {
    $base = 'manage_wang.php';
    header('Location: ' . $base . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

function thai_date_short_manage(?string $date): string {
    $date = trim((string)$date);
    if ($date === '') {
        return '-';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . ((int)date('Y', $ts) + 543);
}

function bind_stmt_params(mysqli_stmt $stmt, string $types, array &$params): bool {
    $refs = [$types];
    foreach ($params as $key => &$value) {
        $refs[] = &$value;
    }
    return $stmt->bind_param(...$refs);
}

ensure_wang_manage_table($db);

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $action = (string)($_POST['action'] ?? '');
    $returnParams = [];
    if (isset($_POST['q']) && trim((string)$_POST['q']) !== '') {
        $returnParams['q'] = trim((string)$_POST['q']);
    }
    if ((int)($_POST['page'] ?? 1) > 1) {
        $returnParams['page'] = (int)$_POST['page'];
    }

    if (!$canManageWang) {
        $error = 'สิทธิ์ไม่เพียงพอ เฉพาะผู้ดูแลระบบเท่านั้นที่แก้ไขหรือลบข้อมูลได้';
    } elseif (!csrf_check($token)) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } elseif ($action === 'delete') {
        $wangId = (int)($_POST['wang_id'] ?? 0);
        if ($wangId <= 0) {
            $error = 'ไม่พบ ID ที่ต้องการลบ';
        } else {
            $stmt = $db->prepare('DELETE FROM tbl_wangyang WHERE wang_id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $wangId);
                if ($stmt->execute()) {
                    $returnParams['msg'] = 'ลบรายการ ID ' . $wangId . ' เรียบร้อย';
                    $stmt->close();
                    redirect_manage($returnParams);
                }
                $error = 'ลบไม่สำเร็จ: ' . $stmt->error;
                $stmt->close();
            } else {
                $error = 'ไม่สามารถเตรียมคำสั่งลบได้: ' . $db->error;
            }
        }
    } elseif ($action === 'update') {
        $wangId = (int)($_POST['wang_id'] ?? 0);
        $date = trim((string)($_POST['wang_date'] ?? ''));
        $memberId = max(0, (int)($_POST['wang_mid'] ?? 0));
        $number = trim((string)($_POST['wang_number'] ?? ''));
        $class = trim((string)($_POST['wang_class'] ?? ''));
        $name = trim((string)($_POST['wang_name'] ?? ''));
        $group = trim((string)($_POST['wang_group'] ?? ''));
        $lane = trim((string)($_POST['wang_lan'] ?? ''));
        $sack = filter_var($_POST['wang_sack'] ?? null, FILTER_VALIDATE_FLOAT);
        $note = trim((string)($_POST['wang_note'] ?? ''));

        if ($wangId <= 0) {
            $error = 'ไม่พบ ID ที่ต้องการแก้ไข';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'วันที่ไม่ถูกต้อง';
        } elseif ($name === '' || $group === '' || $lane === '' || $sack === false || $sack <= 0) {
            $error = 'กรุณากรอกชื่อ กลุ่ม ลาน และจำนวนกระสอบให้ถูกต้อง';
        } else {
            if ($memberId > 0) {
                $memberStmt = $db->prepare('SELECT mem_group, mem_number, mem_fullname, mem_class FROM tbl_member WHERE mem_id = ? LIMIT 1');
                if ($memberStmt) {
                    $memberStmt->bind_param('i', $memberId);
                    $memberStmt->execute();
                    $memberRow = $memberStmt->get_result()->fetch_assoc();
                    $memberStmt->close();
                    if ($memberRow) {
                        $group = trim((string)$memberRow['mem_group']);
                        $number = trim((string)$memberRow['mem_number']);
                        $name = trim((string)$memberRow['mem_fullname']);
                        $class = trim((string)$memberRow['mem_class']);
                    }
                }
            }

            $sack = round((float)$sack, 2);
            $saveBy = (string)(current_user()['user_fullname'] ?? current_user()['user_username'] ?? '');
            $savedAt = date('Y-m-d H:i:s');
            $stmt = $db->prepare("
                UPDATE tbl_wangyang
                SET wang_date = ?,
                    wang_mid = ?,
                    wang_group = ?,
                    wang_number = ?,
                    wang_name = ?,
                    wang_class = ?,
                    wang_sack = ?,
                    wang_lan = ?,
                    wang_note = ?,
                    wang_saveby = ?,
                    wang_savedate = ?
                WHERE wang_id = ?
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param('sissssdssssi', $date, $memberId, $group, $number, $name, $class, $sack, $lane, $note, $saveBy, $savedAt, $wangId);
                if ($stmt->execute()) {
                    $returnParams['msg'] = 'แก้ไขรายการ ID ' . $wangId . ' เรียบร้อย';
                    $stmt->close();
                    redirect_manage($returnParams);
                }
                $error = 'บันทึกไม่สำเร็จ: ' . $stmt->error;
                $stmt->close();
            } else {
                $error = 'ไม่สามารถเตรียมคำสั่งบันทึกได้: ' . $db->error;
            }
        }
    }
}

$where = '';
$params = [];
$types = '';
if ($q !== '') {
    $like = '%' . $q . '%';
    $id = ctype_digit($q) ? (int)$q : 0;
    $where = "WHERE w.wang_id = ? OR w.wang_mid = ? OR w.wang_name LIKE ? OR w.wang_group LIKE ? OR w.wang_number LIKE ? OR w.wang_lan LIKE ? OR w.wang_date LIKE ?";
    $params = [$id, $id, $like, $like, $like, $like, $like];
    $types = 'iisssss';
}

$totalRows = 0;
$countSql = "SELECT COUNT(*) AS c FROM tbl_wangyang w $where";
$countStmt = $db->prepare($countSql);
if ($countStmt) {
    if ($params) {
        bind_stmt_params($countStmt, $types, $params);
    }
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $totalRows = (int)($countRow['c'] ?? 0);
    $countStmt->close();
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$rows = [];
$sql = "
    SELECT
        w.wang_id,
        w.wang_date,
        w.wang_mid,
        COALESCE(NULLIF(w.wang_group, ''), m.mem_group, '') AS wang_group,
        COALESCE(NULLIF(w.wang_number, ''), m.mem_number, '') AS wang_number,
        w.wang_name,
        COALESCE(NULLIF(w.wang_class, ''), m.mem_class, '') AS wang_class,
        w.wang_sack,
        w.wang_lan,
        COALESCE(w.wang_note, '') AS wang_note,
        w.wang_saveby,
        w.wang_savedate
    FROM tbl_wangyang w
    LEFT JOIN tbl_member m ON w.wang_mid = m.mem_id
    $where
    ORDER BY w.wang_id DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
if ($stmt) {
    $allParams = array_merge($params, [$perPage, $offset]);
    $allTypes = $types . 'ii';
    bind_stmt_params($stmt, $allTypes, $allParams);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}

$baseParams = [];
if ($q !== '') {
    $baseParams['q'] = $q;
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการข้อมูลวางยาง</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
  a { color: inherit; text-decoration: none; }
  .app-header {
    position: sticky;
    top: 0;
    z-index: 1020;
    background: rgba(255,255,255,.9);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid #bbf7d0;
  }
  .main-shell { width: min(100% - 1.5rem, 1180px); margin: 0 auto; }
  .header-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem 0; }
  .brand { display: flex; align-items: center; gap: .65rem; min-width: 0; }
  .brand-icon {
    width: 2.45rem;
    height: 2.45rem;
    border-radius: .75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #16a34a;
    color: #fff;
    flex: 0 0 auto;
  }
  .brand-title { font-weight: 800; line-height: 1.2; }
  .brand-subtitle { color: #15803d; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .header-actions { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; justify-content: flex-end; }
  .header-actions .btn { min-height: 38px; border-radius: 999px; font-weight: 700; }
  .panel {
    border: 1px solid #bbf7d0;
    border-radius: 1rem;
    background: rgba(255,255,255,.92);
    box-shadow: 0 14px 34px rgba(20,83,45,.08);
  }
  .form-control, .form-select, .btn, .table { font-family: inherit; }
  .form-control, .form-select {
    border-color: #bbf7d0;
    border-radius: .75rem;
    min-height: 42px;
  }
  .form-control:focus, .form-select:focus {
    border-color: #22c55e;
    box-shadow: 0 0 0 .2rem rgba(34,197,94,.14);
  }
  .btn { border-radius: 999px; font-weight: 700; }
  .table thead th {
    background: #dcfce7;
    color: #166534;
    border-bottom: 1px solid #bbf7d0;
    white-space: nowrap;
  }
  .table > :not(caption) > * > * { padding: .75rem .7rem; vertical-align: middle; }
  .table tbody tr:hover td { background: #f8fdf8; }
  .id-badge {
    display: inline-flex;
    align-items: center;
    gap: .2rem;
    border-radius: 999px;
    padding: .2rem .55rem;
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    color: #166534;
    font-weight: 800;
  }
  .action-cell { min-width: 150px; }
  .modal-content { border-radius: 1rem; border-color: #bbf7d0; }
  @media (max-width: 768px) {
    .header-row { align-items: flex-start; flex-direction: column; }
    .header-actions { justify-content: flex-start; }
    .table { min-width: 900px; }
  }
  </style>
</head>
<body>
  <header class="app-header">
    <div class="main-shell">
      <div class="header-row">
        <a href="manage_wang.php" class="brand">
          <span class="brand-icon"><i data-lucide="database" aria-hidden="true"></i></span>
          <div class="min-w-0">
            <div class="brand-title">จัดการข้อมูลวางยาง</div>
            <div class="brand-subtitle">เรียงตาม ID ล่าสุด ค้นหา แก้ไข และลบรายการ</div>
          </div>
        </a>
        <div class="header-actions">
          <a href="wang_main.php" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1">
            <i data-lucide="package-check" aria-hidden="true"></i><span>วางยาง</span>
          </a>
          <a href="wang_summary.php" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1">
            <i data-lucide="clipboard-list" aria-hidden="true"></i><span>สรุป</span>
          </a>
          <a href="export_wang.php" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1">
            <i data-lucide="download" aria-hidden="true"></i><span>ส่งออก</span>
          </a>
          <?php if (!$canManageWang): ?>
          <span class="badge rounded-pill text-bg-light border text-success-emphasis px-3 py-2">
            <i data-lucide="lock" aria-hidden="true"></i> ดูข้อมูลเท่านั้น
          </span>
          <?php endif; ?>
          <a href="dashboard.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i data-lucide="gauge" aria-hidden="true"></i><span>Dashboard</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <main class="main-shell py-4">
    <?php if ($message !== ''): ?>
      <div class="alert alert-success border-0 shadow-sm"><?php echo e($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger border-0 shadow-sm"><?php echo e($error); ?></div>
    <?php endif; ?>

    <section class="panel p-3 p-md-4 mb-3">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-lg">
          <label for="q" class="form-label fw-semibold text-success-emphasis">
            <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา
          </label>
          <input type="text" class="form-control" id="q" name="q" value="<?php echo e($q); ?>" placeholder="ค้นหา ID, ID สมาชิก, ชื่อ, กลุ่ม, เลขสมาชิก, ลาน หรือวันที่ YYYY-MM-DD">
        </div>
        <div class="col-12 col-lg-auto d-flex gap-2">
          <button type="submit" class="btn btn-success flex-fill">
            <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา
          </button>
          <a href="manage_wang.php" class="btn btn-outline-secondary flex-fill">
            <i data-lucide="x" class="me-1" aria-hidden="true"></i>ล้าง
          </a>
        </div>
      </form>
    </section>

    <section class="panel overflow-hidden">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 px-md-4 py-3 border-bottom">
        <div class="fw-bold text-success-emphasis">
          <i data-lucide="list" class="me-1" aria-hidden="true"></i>
          รายการวางยาง
        </div>
        <div class="text-success">
          พบ <?php echo number_format($totalRows); ?> รายการ · หน้า <?php echo number_format($page); ?>/<?php echo number_format($totalPages); ?>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>วันที่</th>
              <th>ลาน</th>
              <th>ชื่อ</th>
              <th>กลุ่ม</th>
              <th>เลขสมาชิก</th>
              <th class="text-end">กระสอบ</th>
              <th>บันทึกโดย</th>
              <th>เวลาแก้ไขล่าสุด</th>
              <th class="text-center">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="10" class="text-center text-success py-5">ไม่พบข้อมูลวางยาง</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $payload = [
                    'wang_id' => (int)$row['wang_id'],
                    'wang_date' => (string)$row['wang_date'],
                    'wang_mid' => (int)$row['wang_mid'],
                    'wang_group' => (string)$row['wang_group'],
                    'wang_number' => (string)$row['wang_number'],
                    'wang_name' => (string)$row['wang_name'],
                    'wang_class' => (string)$row['wang_class'],
                    'wang_sack' => (float)$row['wang_sack'],
                    'wang_lan' => (string)$row['wang_lan'],
                    'wang_note' => (string)$row['wang_note'],
                ];
              ?>
              <tr>
                <td><span class="id-badge">#<?php echo (int)$row['wang_id']; ?></span></td>
                <td class="text-nowrap"><?php echo e(thai_date_short_manage((string)$row['wang_date'])); ?></td>
                <td><span class="badge rounded-pill text-bg-success">ลาน <?php echo e($row['wang_lan']); ?></span></td>
                <td class="fw-semibold"><?php echo e($row['wang_name']); ?></td>
                <td><?php echo e($row['wang_group']); ?></td>
                <td><?php echo e($row['wang_number'] !== '' ? $row['wang_number'] : '-'); ?></td>
                <td class="text-end fw-bold"><?php echo number_format((float)$row['wang_sack'], 2); ?></td>
                <td><?php echo e($row['wang_saveby'] !== '' ? $row['wang_saveby'] : '-'); ?></td>
                <td class="text-nowrap"><?php echo e($row['wang_savedate'] ? thai_date_format((string)$row['wang_savedate']) : '-'); ?></td>
                <td class="text-center action-cell">
                  <?php if ($canManageWang): ?>
                  <div class="d-inline-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-edit='<?php echo e(json_encode($payload, JSON_UNESCAPED_UNICODE)); ?>'>
                      <i data-lucide="pencil" class="me-1" aria-hidden="true"></i>แก้ไข
                    </button>
                    <form method="post" onsubmit="return confirm('ลบรายการ ID <?php echo (int)$row['wang_id']; ?> ?');">
                      <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="wang_id" value="<?php echo (int)$row['wang_id']; ?>">
                      <input type="hidden" name="q" value="<?php echo e($q); ?>">
                      <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i data-lucide="trash-2" class="me-1" aria-hidden="true"></i>ลบ
                      </button>
                    </form>
                  </div>
                  <?php else: ?>
                    <span class="badge rounded-pill text-bg-light border text-muted">
                      <i data-lucide="lock" class="me-1" aria-hidden="true"></i>เฉพาะ admin
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="d-flex justify-content-between align-items-center gap-2 px-3 px-md-4 py-3 border-top">
        <a class="btn btn-outline-success <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="manage_wang.php?<?php echo e(http_build_query(array_merge($baseParams, ['page' => max(1, $page - 1)]))); ?>">ก่อนหน้า</a>
        <span class="text-success fw-semibold"><?php echo number_format($page); ?> / <?php echo number_format($totalPages); ?></span>
        <a class="btn btn-outline-success <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="manage_wang.php?<?php echo e(http_build_query(array_merge($baseParams, ['page' => min($totalPages, $page + 1)]))); ?>">ถัดไป</a>
      </div>
      <?php endif; ?>
    </section>
  </main>

  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <form method="post" class="modal-content">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="q" value="<?php echo e($q); ?>">
        <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">แก้ไขรายการ <span id="edit-title-id"></span></h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12 col-md-3">
              <label class="form-label">ID</label>
              <input type="number" class="form-control" name="wang_id" id="edit-wang-id" readonly>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">วันที่</label>
              <input type="date" class="form-control" name="wang_date" id="edit-wang-date" required>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">ลาน</label>
              <select class="form-select" name="wang_lan" id="edit-wang-lan" required>
                <option value="">เลือกลาน</option>
                <option value="1">ลาน 1</option>
                <option value="2">ลาน 2</option>
                <option value="3">ลาน 3</option>
                <option value="4">ลาน 4</option>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">กระสอบ</label>
              <input type="number" step="0.01" min="0.01" class="form-control" name="wang_sack" id="edit-wang-sack" required>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">ID สมาชิก</label>
              <input type="number" min="0" class="form-control" name="wang_mid" id="edit-wang-mid">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">เลขสมาชิก</label>
              <input type="text" class="form-control" name="wang_number" id="edit-wang-number">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">ชั้น</label>
              <input type="text" class="form-control" name="wang_class" id="edit-wang-class">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">กลุ่ม</label>
              <input type="text" class="form-control" name="wang_group" id="edit-wang-group" required>
            </div>
            <div class="col-12">
              <label class="form-label">ชื่อ</label>
              <input type="text" class="form-control" name="wang_name" id="edit-wang-name" required>
            </div>
            <div class="col-12">
              <label class="form-label">หมายเหตุ</label>
              <textarea class="form-control" name="wang_note" id="edit-wang-note" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-success">
            <i data-lucide="save" class="me-1" aria-hidden="true"></i>บันทึก
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  (function() {
    const modalEl = document.getElementById('editModal');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    const fields = {
      id: document.getElementById('edit-wang-id'),
      date: document.getElementById('edit-wang-date'),
      mid: document.getElementById('edit-wang-mid'),
      number: document.getElementById('edit-wang-number'),
      className: document.getElementById('edit-wang-class'),
      group: document.getElementById('edit-wang-group'),
      name: document.getElementById('edit-wang-name'),
      sack: document.getElementById('edit-wang-sack'),
      lane: document.getElementById('edit-wang-lan'),
      note: document.getElementById('edit-wang-note'),
      title: document.getElementById('edit-title-id')
    };

    document.querySelectorAll('[data-edit]').forEach((button) => {
      button.addEventListener('click', () => {
        let row = {};
        try {
          row = JSON.parse(button.getAttribute('data-edit') || '{}');
        } catch (_) {
          row = {};
        }
        fields.id.value = row.wang_id || '';
        fields.date.value = row.wang_date || '';
        fields.mid.value = row.wang_mid || 0;
        fields.number.value = row.wang_number || '';
        fields.className.value = row.wang_class || '';
        fields.group.value = row.wang_group || '';
        fields.name.value = row.wang_name || '';
        fields.sack.value = row.wang_sack || '';
        fields.lane.value = row.wang_lan || '';
        fields.note.value = row.wang_note || '';
        fields.title.textContent = row.wang_id ? '#' + row.wang_id : '';
        if (modal) modal.show();
      });
    });

    if (window.lucide && lucide.createIcons) {
      lucide.createIcons();
    }
  })();
  </script>
</body>
</html>

<?php
require_once __DIR__ . '/functions.php';
require_login();
include __DIR__ . '/header.php';

$db = db();

$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to   = trim((string)($_GET['date_to'] ?? ''));
$lanParam  = trim((string)($_GET['lan'] ?? 'all'));

// validate dates
$df = $date_from !== '' ? DateTime::createFromFormat('Y-m-d', $date_from) : null;
if ($date_from !== '' && (!$df || $df->format('Y-m-d') !== $date_from)) $date_from = '';
$dt = $date_to !== '' ? DateTime::createFromFormat('Y-m-d', $date_to) : null;
if ($date_to !== '' && (!$dt || $dt->format('Y-m-d') !== $date_to)) $date_to = '';

// validate lan
$currentLan = 'all';
if ($lanParam !== '' && strtolower($lanParam) !== 'all') {
  $lanInt = (int)$lanParam;
  if (in_array($lanInt, [1,2,3,4], true)) $currentLan = (string)$lanInt;
}

// load price dates list
$conds = [];
$types = '';
$params = [];
if ($date_from !== '') { $conds[] = 'pr_date >= ?'; $types .= 's'; $params[] = $date_from; }
if ($date_to !== '')   { $conds[] = 'pr_date <= ?'; $types .= 's'; $params[] = $date_to; }
$whereSql = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

$dates = [];
$sql = "SELECT pr_date, pr_price FROM tbl_price $whereSql ORDER BY pr_date DESC";
$st = $db->prepare($sql);
if ($st) {
  if ($params) $st->bind_param($types, ...$params);
  $st->execute();
  $res = $st->get_result();
  if ($res) {
    while ($r = $res->fetch_assoc()) $dates[] = $r;
    $res->free();
  }
  $st->close();
}

// default UX: if no filter, show last 30 rounds only
if ($date_from === '' && $date_to === '' && count($dates) > 30) {
  $dates = array_slice($dates, 0, 30);
}

?>
<div class="container my-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-download me-2"></i>ส่งออกสรุปตามรอบวันที่ราคายาง</h1>
      <div class="small text-muted">เลือกหลายรอบวันที่ แล้วส่งออกเป็นไฟล์ Excel (ตารางแบบ น้ำหนัก/จำนวนเงิน ต่อรอบ)</div>
    </div>
    <div>
      <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>กลับ Dashboard</a>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="get" action="export_round_matrix.php">
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">วันที่เริ่มต้น (pr_date)</label>
          <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">วันที่สิ้นสุด (pr_date)</label>
          <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">ลาน</label>
          <select class="form-select" name="lan">
            <option value="all" <?php echo $currentLan === 'all' ? 'selected' : ''; ?>>ทุกลาน</option>
            <option value="1" <?php echo $currentLan === '1' ? 'selected' : ''; ?>>ลาน 1</option>
            <option value="2" <?php echo $currentLan === '2' ? 'selected' : ''; ?>>ลาน 2</option>
            <option value="3" <?php echo $currentLan === '3' ? 'selected' : ''; ?>>ลาน 3</option>
            <option value="4" <?php echo $currentLan === '4' ? 'selected' : ''; ?>>ลาน 4</option>
          </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
          <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>ค้นหา</button>
          <a class="btn btn-outline-secondary" href="export_round_matrix.php"><i class="bi bi-x-circle me-1"></i>ล้าง</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="export_round_matrix_excel.php" target="_blank" id="roundExportForm">
        <input type="hidden" name="lan" value="<?php echo e($currentLan); ?>">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <div class="fw-semibold">เลือกรอบวันที่ (<?php echo number_format(count($dates)); ?> รอบ)</div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(true)">เลือกทั้งหมด</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">ยกเลิกทั้งหมด</button>
            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออก Excel</button>
          </div>
        </div>

        <div class="row g-2">
          <div class="col-12 col-md-6">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="include_member" value="1" id="include_member" checked>
              <label class="form-check-label" for="include_member">รวมชีต/ตาราง: สมาชิก</label>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="include_general" value="1" id="include_general" checked>
              <label class="form-check-label" for="include_general">รวมชีต/ตาราง: เกษตรกรทั่วไป</label>
            </div>
          </div>
        </div>

        <hr>

        <?php if (!$dates): ?>
          <div class="alert alert-warning mb-0">ไม่พบรอบวันที่ในช่วงที่เลือก</div>
        <?php else: ?>
          <div class="row g-2">
            <?php foreach ($dates as $d): ?>
              <div class="col-6 col-md-3 col-lg-2">
                <div class="form-check">
                  <input class="form-check-input round-date" type="checkbox" name="dates[]" value="<?php echo e($d['pr_date']); ?>" id="d_<?php echo e(str_replace('-', '', $d['pr_date'])); ?>">
                  <label class="form-check-label" for="d_<?php echo e(str_replace('-', '', $d['pr_date'])); ?>">
                    <?php echo e(thai_date_format($d['pr_date'])); ?>
                    <span class="text-muted">(<?php echo number_format((float)$d['pr_price'], 2); ?>)</span>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<script>
function toggleAll(checked) {
  document.querySelectorAll('.round-date').forEach(cb => { cb.checked = checked; });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>

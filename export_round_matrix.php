<?php
require_once __DIR__ . '/functions.php';
require_login();
include __DIR__ . '/header.php';
?>

<style>
html,
body {
  font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  background: #eff7f1;
  color: #14532d;
}

.export-shell {
  max-width: 1240px;
}

.export-hero,
.export-panel {
  border: 1px solid #bbf7d0;
  border-radius: 1.25rem;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 16px 42px rgba(20, 83, 45, 0.08);
}

.export-hero {
  background: linear-gradient(135deg, rgba(240, 253, 244, 0.98), rgba(236, 253, 245, 0.95));
}

.export-badge,
.export-mini {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.export-badge {
  width: 3rem;
  height: 3rem;
  border-radius: 1rem;
  background: #16a34a;
  color: #fff;
  box-shadow: 0 10px 24px rgba(22, 163, 74, 0.24);
}

.export-mini {
  width: 2.35rem;
  color: #166534;
}

.form-control,
.form-select,
.btn,
.table,
.alert,
.badge,
.form-label {
  font-family: inherit;
}

.form-control,
.form-select {
  min-height: 46px;
  border-radius: .9rem;
  border-color: #bbf7d0;
}

.form-control:focus,
.form-select:focus {
  border-color: #22c55e;
  box-shadow: 0 0 0 .2rem rgba(34, 197, 94, .14);
}

.btn {
  min-height: 44px;
  border-radius: 999px;
  font-weight: 700;
}

.form-check-input:checked {
  background-color: #16a34a;
  border-color: #16a34a;
}

.round-chip {
  border: 1px solid #bbf7d0;
  border-radius: 1rem;
  background: #f8fdf8;
  padding: .85rem .9rem;
  height: 100%;
}

.round-chip .form-check-label {
  cursor: pointer;
  line-height: 1.35;
}

.toolbar-pill {
  border: 1px solid #bbf7d0;
  border-radius: 999px;
  background: #fff;
  padding: .5rem .75rem;
}

@media (max-width: 576px) {

  .export-hero,
  .export-panel {
    border-radius: 1rem;
  }
}
</style>

<?php
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
<div class="container export-shell my-4">
  <section class="export-hero p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="export-badge">
          <i data-lucide="download" aria-hidden="true"></i>
        </span>
        <div>
          <div class="text-uppercase text-success fw-semibold small mb-1">Export</div>
          <h1 class="h3 fw-bold mb-1 text-success-emphasis">ส่งออกสรุปตามรอบวันที่ราคายาง</h1>
          <div class="text-success">เลือกหลายรอบวันที่ แล้วส่งออกเป็นไฟล์ Excel พร้อมแยกข้อมูลสมาชิกและเกษตรกรทั่วไป
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="export-panel p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
      <div>
        <h2 class="h5 fw-bold mb-1 text-success-emphasis">
          <i data-lucide="sliders-horizontal" class="me-1" aria-hidden="true"></i>ตัวกรองรอบวันที่
        </h2>
        <div class="text-success">กรองช่วงวันที่และเลือกลานก่อนสร้างไฟล์ส่งออก</div>
      </div>
      <div class="toolbar-pill text-success d-inline-flex align-items-center gap-2">
        <i data-lucide="calendar-days" aria-hidden="true"></i>
        <span><?php echo number_format(count($dates)); ?> รอบ</span>
      </div>
    </div>

    <form class="row g-3 align-items-end" method="get" action="export_round_matrix.php">
      <div class="col-12 col-md-3">
        <label class="form-label mb-1"><i data-lucide="calendar" class="me-1"
            aria-hidden="true"></i>วันที่เริ่มต้น</label>
        <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label mb-1"><i data-lucide="calendar" class="me-1"
            aria-hidden="true"></i>วันที่สิ้นสุด</label>
        <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label mb-1"><i data-lucide="map-pin" class="me-1" aria-hidden="true"></i>ลาน</label>
        <select class="form-select" name="lan">
          <option value="all" <?php echo $currentLan === 'all' ? 'selected' : ''; ?>>ทุกลาน</option>
          <option value="1" <?php echo $currentLan === '1' ? 'selected' : ''; ?>>ลาน 1</option>
          <option value="2" <?php echo $currentLan === '2' ? 'selected' : ''; ?>>ลาน 2</option>
          <option value="3" <?php echo $currentLan === '3' ? 'selected' : ''; ?>>ลาน 3</option>
          <option value="4" <?php echo $currentLan === '4' ? 'selected' : ''; ?>>ลาน 4</option>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">
          <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา
        </button>
        <a class="btn btn-outline-secondary" href="export_round_matrix.php">
          <i data-lucide="x" class="me-1" aria-hidden="true"></i>ล้าง
        </a>
      </div>
    </form>
  </section>

  <section class="export-panel p-3 p-md-4">
    <form method="post" action="export_round_matrix_excel.php" target="_blank" id="roundExportForm">
      <input type="hidden" name="lan" value="<?php echo e($currentLan); ?>">

      <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
          <h2 class="h5 fw-bold mb-1 text-success-emphasis">
            <i data-lucide="list-checks" class="me-1" aria-hidden="true"></i>เลือกรอบวันที่
          </h2>
          <div class="text-success">เลือกได้หลายรอบก่อนส่งออกไฟล์ Excel</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAll(true)">
            <i data-lucide="check-square" class="me-1" aria-hidden="true"></i>เลือกทั้งหมด
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAll(false)">
            <i data-lucide="square" class="me-1" aria-hidden="true"></i>ยกเลิกทั้งหมด
          </button>
          <button type="submit" class="btn btn-success btn-sm">
            <i data-lucide="file-text" class="me-1" aria-hidden="true"></i>ส่งออก Excel
          </button>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-12 col-md-6">
          <div class="round-chip">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="include_member" value="1" id="include_member"
                checked>
              <label class="form-check-label" for="include_member">
                <i data-lucide="users" class="me-1" aria-hidden="true"></i>รวมชีต/ตาราง: สมาชิก
              </label>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="round-chip">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="include_general" value="1" id="include_general"
                checked>
              <label class="form-check-label" for="include_general">
                <i data-lucide="user-round" class="me-1" aria-hidden="true"></i>รวมชีต/ตาราง: เกษตรกรทั่วไป
              </label>
            </div>
          </div>
        </div>
      </div>

      <?php if (!$dates): ?>
      <div class="alert alert-warning border-0 shadow-sm mb-0">
        <i data-lucide="alert-triangle" class="me-1" aria-hidden="true"></i>ไม่พบรอบวันที่ในช่วงที่เลือก
      </div>
      <?php else: ?>
      <div class="row g-2">
        <?php foreach ($dates as $d): ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
          <div class="round-chip">
            <div class="form-check">
              <input class="form-check-input round-date" type="checkbox" name="dates[]"
                value="<?php echo e($d['pr_date']); ?>" id="d_<?php echo e(str_replace('-', '', $d['pr_date'])); ?>">
              <label class="form-check-label" for="d_<?php echo e(str_replace('-', '', $d['pr_date'])); ?>">
                <span class="d-block fw-semibold"><?php echo e(thai_date_format($d['pr_date'])); ?></span>
                <span class="text-success small"><?php echo number_format((float)$d['pr_price'], 2); ?> บาท</span>
              </label>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </form>
  </section>
</div>

<script>
function toggleAll(checked) {
  document.querySelectorAll('.round-date').forEach(cb => {
    cb.checked = checked;
  });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
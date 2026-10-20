<?php
require_once __DIR__ . '/functions.php';
require_login();
$db = db();
ensure_weighing_schema($db);
$error = '';
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
$date = trim((string)($_GET['date'] ?? ''));
$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if ($date !== '' && (!$parsed || $parsed->format('Y-m-d') !== $date)) $date = $today;
$lane = (string)($_GET['lan'] ?? ($_POST['lan'] ?? ''));
if (!in_array($lane, ['1','2','3','4'], true)) $lane = '';
$status = (string)($_GET['status'] ?? 'pending');
// Older links used all for the placement card; that card now shows only unweighed work.
if (!in_array($status, ['pending','deductions','completed'], true)) $status = 'pending';
// Select one lane before loading summaries, prices or placement records.
if ($lane === '') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') http_response_code(400);
    require __DIR__ . '/header.php';
    ?>
    <link rel="stylesheet" href="assets/css/weighing.css?v=<?php echo filemtime(__DIR__ . '/assets/css/weighing.css'); ?>">
    <div class="weigh-page lane-picker">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h1 class="h4 mb-1">ชั่งน้ำหนัก — เลือกลาน</h1><div class="text-muted">เลือกลานที่ต้องการชั่ง เพื่อเปิดรายการวางยางของลานนั้น</div></div>
        <a class="btn btn-outline-success" href="wang_main.php">กลับหน้าวางยาง</a>
      </div>
      <?php if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'): ?><div class="alert alert-warning">กรุณาเลือกลานก่อนบันทึกน้ำหนัก</div><?php endif; ?>
      <div class="row g-3 lane-options">
        <?php for ($i=1; $i<=4; $i++): ?>
          <div class="col-6 col-lg-3"><a class="card border-success h-100 text-center text-decoration-none" href="weigh_wang.php?<?php echo e(http_build_query(['lan'=>$i,'date'=>$date,'status'=>$status])); ?>"><div class="card-body py-3"><h2 class="h4 text-success">ลาน <?php echo $i; ?></h2><span class="text-muted">เข้าใช้งาน</span></div></a></div>
        <?php endfor; ?>
      </div>
    </div>
    <?php
    require __DIR__ . '/footer.php';
    return;
}
$counts = collection_workflow_counts($db, $date, $lane);
$prices = $db->query('SELECT pr_id, pr_date, pr_price FROM tbl_price ORDER BY pr_date DESC, pr_id DESC')->fetch_all(MYSQLI_ASSOC);
$defaultPriceId = (int)($prices[0]['pr_id'] ?? 0);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_check((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
        $editing = ($_POST['action'] ?? '') === 'update_weight';
        $actor = (string)(current_user()['user_fullname'] ?: current_user()['user_username']);
        if ($editing) {
            $id = update_wang_weighing($db, (int)($_POST['wang_id'] ?? 0), (string)($_POST['net_weight'] ?? ''), $actor, $lane);
        } else {
            $id = record_wang_weighing($db, (int)($_POST['wang_id'] ?? 0), (string)($_POST['net_weight'] ?? ''), (int)($_POST['price_id'] ?? 0), $actor, $lane);
        }
        $_SESSION['weighing_success'] = $editing ? 'แก้ไขน้ำหนักเรียบร้อย อัปเดตข้อมูลที่ส่งให้เจ้าหน้าที่ยอดหักแล้ว' : 'บันทึกน้ำหนักเรียบร้อย ส่งรายการเข้าคิวรอเจ้าหน้าที่บันทึกยอดหักแล้ว';
        header('Location: weigh_wang.php?' . http_build_query(['lan'=>$lane, 'date'=>$date, 'status'=>$editing ? 'deductions' : 'pending']));
        exit;
    } catch (InvalidArgumentException | DomainException | RuntimeException $e) {
        $error = $e instanceof mysqli_sql_exception ? 'บันทึกไม่สำเร็จ กรุณาโหลดหน้าใหม่แล้วตรวจสอบรายการอีกครั้ง' : $e->getMessage();
        if ($e instanceof mysqli_sql_exception) error_log((string)$e);
    }
}
$success = (string)($_SESSION['weighing_success'] ?? '');
unset($_SESSION['weighing_success']);
$sql = 'SELECT w.*, x.weighing_id, x.net_weight, x.ru_id, x.round_date, x.deductions_saved_at, x.weighed_by, x.deductions_saved_by, x.weight_updated_at, x.weight_updated_by
        FROM tbl_wangyang w LEFT JOIN tbl_wang_weighing x ON x.wang_id = w.wang_id
        WHERE (? = \'\' OR w.wang_date = ?)';
$sql .= ' AND w.wang_lan = ?';
$sql .= " AND LOWER(TRIM(w.wang_status)) NOT IN ('cancelled','canceled','deleted')";
if ($status === 'pending') $sql .= ' AND x.weighing_id IS NULL';
if ($status === 'deductions') $sql .= ' AND x.weighing_id IS NOT NULL AND x.deductions_saved_at IS NULL';
if ($status === 'completed') $sql .= ' AND x.deductions_saved_at IS NOT NULL';
$sql .= ' ORDER BY (x.weighing_id IS NOT NULL), w.wang_lan, w.wang_id';
$stmt = $db->prepare($sql);
$stmt->bind_param('sss', $date, $date, $lane);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
require __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="assets/css/weighing.css?v=<?php echo filemtime(__DIR__ . '/assets/css/weighing.css'); ?>">
<div class="weigh-page">
  <header class="weigh-heading">
    <div><h1>ชั่งน้ำหนัก <span class="lane-tag">ลาน <?php echo e($lane); ?></span></h1><p>รับข้อมูลวางยาง แล้วบันทึกน้ำหนักสุทธิ</p></div>
    <a class="btn btn-outline-success" href="weigh_wang.php?<?php echo e(http_build_query(['date'=>$date,'status'=>$status])); ?>">เปลี่ยนลาน</a>
  </header>
  <nav class="weigh-stages" aria-label="ขั้นตอนการชั่ง">
    <?php foreach (['pending'=>['วางยางแล้ว','รอชั่ง','pending_weighing'], 'deductions'=>['ชั่งแล้ว','รอยอดหัก','pending_deductions'], 'completed'=>['ยอดหักแล้ว','ล็อกน้ำหนัก','completed']] as $key=>$item): ?>
      <a class="stage <?php echo $status === $key ? 'active' : ''; ?>" <?php echo $status === $key ? 'aria-current="page"' : ''; ?> href="weigh_wang.php?<?php echo e(http_build_query(['date'=>$date,'lan'=>$lane,'status'=>$key])); ?>"><strong><?php echo number_format($counts[$item[2]]); ?></strong><span><?php echo e($item[0]); ?></span><small><?php echo e($item[1]); ?></small></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?php echo e($success); ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?php echo e($error); ?></div><?php endif; ?>
  <?php if (!$prices && $status === 'pending'): ?><div class="alert alert-warning">กำหนดราคาใน <a href="prices.php">หน้าราคายาง</a> ก่อนบันทึกน้ำหนัก</div><?php endif; ?>
  <div class="weigh-toolbar">
    <span><?php echo number_format(count($rows)); ?> รายการ</span>
    <details class="date-filter" <?php echo $date !== '' ? 'open' : ''; ?>>
      <summary>วันที่วางยาง: <?php echo $date !== '' ? e($date) : 'ทุกวัน'; ?></summary>
      <form method="get">
        <input type="hidden" name="status" value="<?php echo e($status); ?>"><input type="hidden" name="lan" value="<?php echo e($lane); ?>">
        <label class="visually-hidden" for="date">วันที่วางยาง</label><input class="form-control" type="date" id="date" name="date" value="<?php echo e($date); ?>">
        <button class="btn btn-success">แสดง</button>
        <?php if ($date !== ''): ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['lan'=>$lane,'status'=>$status])); ?>">ทุกวัน</a><?php endif; ?>
      </form>
    </details>
  </div>
  <div class="weigh-records">
    <?php foreach ($rows as $row): $wid=(int)$row['wang_id']; $posted=(int)($_POST['wang_id'] ?? 0) === $wid; ?>
      <article class="weigh-record <?php echo $row['weighing_id'] ? 'is-weighed' : ''; ?> <?php echo $row['deductions_saved_at'] ? 'is-complete' : ''; ?>" aria-labelledby="person-<?php echo $wid; ?>">
        <div class="record-heading">
          <div><h2 id="person-<?php echo $wid; ?>"><?php echo e($row['wang_name']); ?></h2><p>เลขที่ <?php echo e($row['wang_number']); ?> · กลุ่ม <?php echo e($row['wang_group']); ?></p></div>
          <span class="sack-count"><?php echo number_format((float)$row['wang_sack'],2); ?> <small>กระสอบ</small></span>
        </div>
        <div class="record-ref">#<?php echo $wid; ?> · วางยาง <?php echo e($row['wang_date']); ?></div>
        <?php if (!$row['weighing_id']): ?>
          <form method="post" class="weight-form">
            <input type="hidden" name="lan" value="<?php echo e($lane); ?>"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="wang_id" value="<?php echo $wid; ?>">
            <div><label for="weight-<?php echo $wid; ?>">น้ำหนักสุทธิ <small>(กก.)</small></label><input class="form-control weight-input" id="weight-<?php echo $wid; ?>" name="net_weight" type="number" inputmode="decimal" min="0.01" max="999999999999.99" step="0.01" required placeholder="0.00" value="<?php echo $posted ? e($_POST['net_weight'] ?? '') : ''; ?>"></div>
            <div><label for="price-<?php echo $wid; ?>">รอบราคา</label><select class="form-select" id="price-<?php echo $wid; ?>" name="price_id" required>
              <?php foreach ($prices as $price): ?><option value="<?php echo (int)$price['pr_id']; ?>" <?php echo (int)($posted ? ($_POST['price_id'] ?? $defaultPriceId) : $defaultPriceId) === (int)$price['pr_id'] ? 'selected' : ''; ?>><?php echo e($price['pr_date']); ?> · <?php echo number_format((float)$price['pr_price'],2); ?> บาท/กก.</option><?php endforeach; ?>
            </select></div>
            <button class="btn btn-success save-weight" <?php echo !$prices ? 'disabled' : ''; ?>><i data-lucide="check" aria-hidden="true"></i>บันทึกน้ำหนัก</button>
          </form>
        <?php else: ?>
          <div class="record-weight"><strong><?php echo number_format((float)$row['net_weight'],2); ?> <small>กก.</small></strong><span>รอบ <?php echo e($row['round_date']); ?></span></div>
          <?php if (!$row['deductions_saved_at']): ?>
            <details class="weight-edit"><summary>แก้ไขน้ำหนัก</summary>
              <form method="post" class="weight-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="lan" value="<?php echo e($lane); ?>"><input type="hidden" name="wang_id" value="<?php echo $wid; ?>"><input type="hidden" name="action" value="update_weight">
                <label for="correct-weight-<?php echo $wid; ?>">น้ำหนักใหม่ (กก.)</label><input class="form-control weight-input" id="correct-weight-<?php echo $wid; ?>" type="number" inputmode="decimal" name="net_weight" min="0.01" max="999999999999.99" step="0.01" required value="<?php echo $posted ? e($_POST['net_weight'] ?? $row['net_weight']) : e($row['net_weight']); ?>">
                <button class="btn btn-success save-weight"><i data-lucide="check" aria-hidden="true"></i>บันทึกน้ำหนักใหม่</button>
              </form>
            </details>
          <?php else: ?><div class="weight-locked"><i data-lucide="lock-keyhole" aria-hidden="true"></i>ล็อกน้ำหนัก · บันทึกยอดหักแล้ว</div><?php endif; ?>
          <details class="record-history"><summary>ผู้บันทึก / ประวัติ</summary><p>ผู้ชั่ง: <?php echo e($row['weighed_by']); ?><?php if ($row['weight_updated_at']): ?><br>แก้น้ำหนัก: <?php echo e($row['weight_updated_by']); ?><br><?php echo e($row['weight_updated_at']); ?><?php endif; ?><?php if ($row['deductions_saved_at']): ?><br>ยอดหัก: <?php echo e($row['deductions_saved_by']); ?><?php endif; ?></p></details>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if (!$rows): ?><div class="weigh-empty"><i data-lucide="clipboard-check" aria-hidden="true"></i><h2><?php echo $status === 'pending' ? 'ไม่มีรายการรอชั่ง' : 'ไม่มีรายการในขั้นตอนนี้'; ?></h2><p>ลาน <?php echo e($lane); ?> · <?php echo $date !== '' ? e($date) : 'ทุกวัน'; ?></p></div><?php endif; ?>
</div>
<script>
document.querySelectorAll('.weigh-page .weight-form').forEach(form => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"], button.save-weight');
    if (button) { button.dataset.originalLabel = button.textContent.trim(); button.disabled = true; button.textContent = 'กำลังบันทึก…'; }
  });
});
window.addEventListener('pageshow', () => {
  document.querySelectorAll('.weigh-page .save-weight[data-original-label]').forEach(button => { button.disabled = false; button.textContent = button.dataset.originalLabel; });
});
</script>
<?php require __DIR__ . '/footer.php'; ?>

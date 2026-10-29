<?php
require_once __DIR__ . '/functions.php';
require_login();
$db = db();
ensure_weighing_schema($db);
$error = '';
$round = latest_priced_collection_round($db);
$roundBarReadOnly = true;
$missingRoundReturnUrl = 'weigh_wang.php';
$missingRoundReturnLabel = 'กลับหน้าชั่งยาง';
$roundId = (int)($round['round_id'] ?? 0);
$lane = (string)($_GET['lan'] ?? ($_POST['lan'] ?? ''));
if (!in_array($lane, ['1','2','3','4'], true)) $lane = '';
$status = (string)($_GET['status'] ?? 'pending');
// Older links used all for the placement card; that card now shows only unweighed work.
if (!in_array($status, ['pending','deductions','completed'], true)) $status = 'pending';
// Select one lane before loading prices or placement records.
if ($lane === '') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') http_response_code(400);
    $countKey = ['pending'=>'pending_weighing', 'deductions'=>'pending_deductions', 'completed'=>'completed'][$status];
    $countLabel = ['pending'=>'รอชั่ง', 'deductions'=>'รอยอดหัก', 'completed'=>'ยอดหักแล้ว'][$status];
    require __DIR__ . '/header.php';
    ?>
    <link rel="stylesheet" href="assets/css/weighing.css?v=<?php echo filemtime(__DIR__ . '/assets/css/weighing.css'); ?>">
    <div class="weigh-page lane-picker">
      <?php if ($round) require __DIR__ . '/collection_round_bar.php'; ?>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h1 class="h4 mb-1">ชั่งน้ำหนัก — เลือกลาน</h1><div class="text-muted">เลือกลานที่ต้องการชั่ง เพื่อเปิดรายการวางยางของลานนั้น</div></div>
      </div>
      <?php if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'): ?><div class="alert alert-warning">กรุณาเลือกลานก่อนบันทึกน้ำหนัก</div><?php endif; ?>
      <p class="small text-muted mb-2">จำนวนรายการ<?php echo e($countLabel); ?>ในแต่ละลาน</p>
      <nav class="lane-options lane-badge-menu" aria-label="เลือกลานชั่งยาง">
        <?php for ($i=1; $i<=4; $i++): $laneCounts = weighing_window_counts($db, $round, (string)$i); $badgeCount = $laneCounts[$countKey]; ?>
          <a class="btn btn-success position-relative weigh-badge-button" href="weigh_wang.php?<?php echo e(http_build_query(['lan'=>$i,'round'=>$roundId,'status'=>$status])); ?>">
            <i data-lucide="map-pin" aria-hidden="true"></i>ลาน <?php echo $i; ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill <?php echo $badgeCount > 0 ? 'bg-danger' : 'bg-secondary'; ?>" title="<?php echo number_format($badgeCount); ?> รายการ"><span aria-hidden="true"><?php echo $badgeCount > 99 ? '99+' : number_format($badgeCount); ?></span><span class="visually-hidden"><?php echo e($countLabel); ?> <?php echo number_format($badgeCount); ?> รายการ</span></span>
          </a>
        <?php endfor; ?>
      </nav>
    </div>
    <?php
    require __DIR__ . '/missing_collection_round_modal.php';
    require __DIR__ . '/footer.php';
    return;
}
$counts = weighing_window_counts($db, $round, $lane);
$roundPrice = latest_collection_round_price($db, $roundId);
$prices = $roundPrice ? [$roundPrice] : [];
$defaultPriceId = (int)($roundPrice['pr_id'] ?? 0);
$canWeigh = $round && can_weigh_on_price_date($round['weigh_date']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!$round) throw new DomainException('ไม่พบรอบรวบรวมยาง');
        if (!csrf_check((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
        $editing = ($_POST['action'] ?? '') === 'update_weight';
        $actor = (string)(current_user()['user_fullname'] ?: current_user()['user_username']);
        if ($editing) {
            $id = update_wang_weighing($db, (int)($_POST['wang_id'] ?? 0), (string)($_POST['net_weight'] ?? ''), $actor, $lane, $roundId);
        } else {
            $id = record_wang_weighing($db, (int)($_POST['wang_id'] ?? 0), (string)($_POST['net_weight'] ?? ''), (int)($_POST['price_id'] ?? 0), $actor, $lane, $roundId);
        }
        $_SESSION['weighing_success'] = $editing ? 'แก้ไขน้ำหนักเรียบร้อย อัปเดตข้อมูลที่ส่งให้เจ้าหน้าที่ยอดหักแล้ว' : 'บันทึกน้ำหนักเรียบร้อย ส่งรายการเข้าคิวรออนุมัติแล้ว';
        header('Location: weigh_wang.php?' . http_build_query(['lan'=>$lane, 'round'=>$roundId, 'status'=>$editing ? 'deductions' : 'pending']));
        exit;
    } catch (InvalidArgumentException | DomainException | RuntimeException $e) {
        $error = $e instanceof mysqli_sql_exception ? 'บันทึกไม่สำเร็จ กรุณาโหลดหน้าใหม่แล้วตรวจสอบรายการอีกครั้ง' : $e->getMessage();
        if ($e instanceof mysqli_sql_exception) error_log((string)$e);
    }
}
$success = (string)($_SESSION['weighing_success'] ?? '');
unset($_SESSION['weighing_success']);
$sql = 'SELECT w.*, x.weighing_id, x.net_weight, x.price_per_kg, x.ru_id, x.round_date, x.deductions_saved_at, x.weighed_by, x.deductions_saved_by, x.approval_route, x.weight_updated_at, x.weight_updated_by
        FROM tbl_wangyang w LEFT JOIN tbl_wang_weighing x ON x.wang_id = w.wang_id
        WHERE w.wang_date BETWEEN ? AND ? AND (x.weighing_id IS NULL OR x.round_date=?)';
$sql .= ' AND w.wang_lan = ?';
$sql .= " AND LOWER(TRIM(w.wang_status)) NOT IN ('cancelled','canceled','deleted')";
if ($status === 'pending') $sql .= ' AND x.weighing_id IS NULL';
if ($status === 'deductions') $sql .= ' AND x.weighing_id IS NOT NULL AND x.deductions_saved_at IS NULL';
if ($status === 'completed') $sql .= ' AND x.deductions_saved_at IS NOT NULL';
$sql .= ' ORDER BY (x.weighing_id IS NOT NULL), w.wang_lan, w.wang_id';
$stmt = $db->prepare($sql);
$windowStart = $round['lay_start'] ?? '1000-01-01';
$windowEnd = $round['lay_end'] ?? '1000-01-01';
$windowDate = $round['weigh_date'] ?? '1000-01-01';
$stmt->bind_param('ssss', $windowStart, $windowEnd, $windowDate, $lane);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
require __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="assets/css/weighing.css?v=<?php echo filemtime(__DIR__ . '/assets/css/weighing.css'); ?>">
<div class="weigh-page">
  <?php if ($round): ?><div class="weigh-round-strip"><span>รอบ <?php echo e(thai_date_short_format($round['weigh_date'])); ?></span><strong><?php echo $roundPrice ? number_format((float)$roundPrice['pr_price'],2) . ' บาท/กก.' : 'ยังไม่มีราคา'; ?></strong></div><?php endif; ?>
  <header class="weigh-lane-banner" data-lane="<?php echo e($lane); ?>" aria-label="กำลังชั่งยาง ลาน <?php echo e($lane); ?>">
    <span class="weigh-lane-number" aria-hidden="true"><?php echo e($lane); ?></span>
    <div class="weigh-lane-copy"><p>กำลังชั่งยาง</p><h1>ลาน <?php echo e($lane); ?></h1></div>
    <a class="btn btn-outline-success" href="weigh_wang.php?<?php echo e(http_build_query(['round'=>$roundId,'status'=>$status])); ?>"><i data-lucide="arrow-left-right" aria-hidden="true"></i>เปลี่ยนลาน</a>
  </header>
  <nav class="weigh-stages" aria-label="ขั้นตอนการชั่ง">
    <?php foreach (['pending'=>['วางยางแล้ว','รอชั่ง','pending_weighing'], 'deductions'=>['ชั่งแล้ว','รออนุมัติ / ยอดหัก','pending_deductions'], 'completed'=>['ยอดหักแล้ว','ล็อกน้ำหนัก','completed']] as $key=>$item): ?>
      <?php $badgeCount = $counts[$item[2]]; ?>
      <a class="btn <?php echo $status === $key ? 'btn-success' : 'btn-outline-success'; ?> position-relative weigh-badge-button" <?php echo $status === $key ? 'aria-current="page"' : ''; ?> href="weigh_wang.php?<?php echo e(http_build_query(['round'=>$roundId,'lan'=>$lane,'status'=>$key])); ?>" title="<?php echo e($item[1]); ?>">
        <?php echo e($item[0]); ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill <?php echo $badgeCount > 0 ? 'bg-danger' : 'bg-secondary'; ?>" title="<?php echo number_format($badgeCount); ?> รายการ"><span aria-hidden="true"><?php echo $badgeCount > 99 ? '99+' : number_format($badgeCount); ?></span><span class="visually-hidden"><?php echo e($item[1]); ?> <?php echo number_format($badgeCount); ?> รายการ</span></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?php echo e($success); ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?php echo e($error); ?></div><?php endif; ?>
  <?php if ($round && !$canWeigh): ?><div class="alert alert-info">เริ่มชั่งยางได้ตั้งแต่วันที่ขาย / ชั่งยาง <?php echo e(thai_date_format($round['weigh_date'])); ?></div><?php endif; ?>
  <?php if ($round && !$prices && $status === 'pending'): ?><div class="alert alert-warning">กำหนดราคาใน <a href="prices.php">หน้าราคายาง</a> ก่อนบันทึกน้ำหนัก</div><?php endif; ?>
  <?php if ($rows): ?>
  <div class="weigh-member-search" role="search" aria-label="ค้นหาสมาชิกในลานนี้">
    <label class="visually-hidden" for="weighMemberSearch">ค้นหาชื่อสมาชิก เลขสมาชิก หรือหมายเหตุ</label>
    <i data-lucide="search" aria-hidden="true"></i>
    <input class="form-control" type="search" id="weighMemberSearch" placeholder="ค้นหาชื่อสมาชิก เลขสมาชิก หรือหมายเหตุ" autocomplete="off" aria-controls="weighRecords">
    <button class="btn btn-outline-secondary" type="button" id="clearWeighSearch" hidden>ล้างคำค้น</button>
  </div>
  <?php endif; ?>
  <div class="weigh-toolbar">
    <span id="weighResultCount" aria-live="polite"><?php echo number_format(count($rows)); ?> รายการ</span>

  </div>
  <div class="weigh-records" id="weighRecords">
    <?php foreach ($rows as $row): $wid=(int)$row['wang_id']; $posted=(int)($_POST['wang_id'] ?? 0) === $wid;
      $recordDetails = [
        'id'=>$wid, 'name'=>collection_display_name($row['wang_name'], $row['wang_note'] ?? null),
        'number'=>$row['wang_number'], 'group'=>$row['wang_group'], 'lane'=>'ลาน ' . $row['wang_lan'],
        'sacks'=>number_format((float)$row['wang_sack'],2) . ' กระสอบ', 'date'=>thai_date_format($row['wang_date']),
        'round'=>thai_date_format($row['round_date'] ?? ($round['weigh_date'] ?? '')),
        'price'=>number_format((float)($row['price_per_kg'] ?? $roundPrice['pr_price'] ?? 0),2) . ' บาท/กก.',
        'weigher'=>$row['weighed_by'] ?: 'ยังไม่ได้ชั่ง',
        'weightEditor'=>$row['weight_updated_by'] ? $row['weight_updated_by'] . ' · ' . thai_datetime_format($row['weight_updated_at']) : '—',
        'deductions'=>$row['deductions_saved_by'] ?: 'ยังไม่ได้บันทึกยอดหัก',
      ];
    ?>
      <article class="weigh-record <?php echo $row['weighing_id'] ? 'is-weighed' : ''; ?> <?php echo $row['deductions_saved_at'] ? 'is-complete' : ''; ?>" aria-labelledby="person-<?php echo $wid; ?>" data-member-search="<?php echo e(implode(' ', [$row['wang_name'], $row['wang_number'], $row['wang_note'] ?? ''])); ?>">
        <div class="record-heading">
          <div><h2 id="person-<?php echo $wid; ?>"><?php echo e(collection_display_name($row['wang_name'], $row['wang_note'] ?? null)); ?></h2><p>เลขที่ <?php echo e($row['wang_number']); ?> · กลุ่ม <?php echo e($row['wang_group']); ?></p></div>
          <div class="record-side"><span class="sack-count"><?php echo number_format((float)$row['wang_sack'],2); ?> <small>กระสอบ</small></span><button type="button" class="btn btn-sm record-info" data-bs-toggle="modal" data-bs-target="#weighRecordDetails" data-record="<?php echo e(json_encode($recordDetails, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); ?>" aria-label="ข้อมูลของ <?php echo e($row['wang_name']); ?>"><i data-lucide="info" aria-hidden="true"></i>ข้อมูล</button></div>
        </div>

        <?php if (!$row['weighing_id']): ?>
          <form method="post" class="weight-form">
            <input type="hidden" name="round" value="<?php echo $roundId; ?>"><input type="hidden" name="lan" value="<?php echo e($lane); ?>"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="wang_id" value="<?php echo $wid; ?>">
            <div><label for="weight-<?php echo $wid; ?>">น้ำหนักสุทธิ <small>(กก.)</small></label><input class="form-control weight-input" id="weight-<?php echo $wid; ?>" name="net_weight" type="number" inputmode="decimal" min="0.01" max="999999999999.99" step="0.01" required placeholder="0.00" value="<?php echo $posted ? e($_POST['net_weight'] ?? '') : ''; ?>"></div>
            <div class="weigh-price-field"><label>ราคาล่าสุดของรอบนี้</label><input type="hidden" name="price_id" value="<?php echo $defaultPriceId; ?>"><div class="form-control bg-light"><?php echo $roundPrice ? number_format((float)$roundPrice['pr_price'],2) . ' บาท/กก.' : 'ยังไม่มีราคา'; ?></div></div>
            <button class="btn btn-success save-weight" <?php echo !$prices || !$canWeigh ? 'disabled' : ''; ?>><i data-lucide="check" aria-hidden="true"></i><span class="save-label-wide">บันทึกน้ำหนัก</span><span class="save-label-short">บันทึก</span></button>
          </form>
        <?php else: ?>
          <div class="record-weight"><strong><?php echo number_format((float)$row['net_weight'],2); ?> <small>กก.</small></strong><span>รอบ <?php echo e(thai_date_short_format($row['round_date'])); ?></span></div>
          <?php if (!$row['deductions_saved_at'] && $row['approval_route'] === null): ?>
            <details class="weight-edit"><summary>แก้ไขน้ำหนัก</summary>
              <form method="post" class="weight-form">
                <input type="hidden" name="round" value="<?php echo $roundId; ?>"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="lan" value="<?php echo e($lane); ?>"><input type="hidden" name="wang_id" value="<?php echo $wid; ?>"><input type="hidden" name="action" value="update_weight">
                <label for="correct-weight-<?php echo $wid; ?>">น้ำหนักใหม่ (กก.)</label><input class="form-control weight-input" id="correct-weight-<?php echo $wid; ?>" type="number" inputmode="decimal" name="net_weight" min="0.01" max="999999999999.99" step="0.01" required value="<?php echo $posted ? e($_POST['net_weight'] ?? $row['net_weight']) : e($row['net_weight']); ?>">
                <button class="btn btn-success save-weight"><i data-lucide="check" aria-hidden="true"></i>บันทึกน้ำหนักใหม่</button>
              </form>
            </details>
          <?php else: ?><div class="weight-locked"><i data-lucide="lock-keyhole" aria-hidden="true"></i>ล็อกน้ำหนัก · ส่งดำเนินการแล้ว</div><?php endif; ?>

        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <div class="weigh-empty" id="weighSearchEmpty" role="status" hidden><i data-lucide="search" aria-hidden="true"></i><h2>ไม่พบสมาชิกที่ตรงกับคำค้น</h2><p>ค้นหาเฉพาะรายการในลานและขั้นตอนที่กำลังเปิดอยู่</p></div>
  <?php if (!$rows): ?><div class="weigh-empty"><i data-lucide="clipboard-check" aria-hidden="true"></i><h2><?php echo $status === 'pending' ? 'ไม่มีรายการรอชั่ง' : 'ไม่มีรายการในขั้นตอนนี้'; ?></h2><p>ลาน <?php echo e($lane); ?> · <?php echo e(collection_round_label($round)); ?></p></div><?php endif; ?>
</div>
<div class="modal fade" id="weighRecordDetails" tabindex="-1" aria-labelledby="weighRecordDetailsTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content weigh-details-modal">
  <div class="modal-header"><h2 class="modal-title fs-5" id="weighRecordDetailsTitle">ข้อมูลรายการวางยาง</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
  <div class="modal-body"><p class="record-detail-name" data-weigh-detail="name"></p><dl class="record-details-list">
    <?php foreach (['number'=>'เลขสมาชิก','group'=>'กลุ่ม','lane'=>'ลาน','sacks'=>'จำนวนกระสอบ','date'=>'วันที่วางยาง','round'=>'วันที่ราคายาง','price'=>'ราคาที่ใช้','weigher'=>'ผู้ชั่ง','weightEditor'=>'แก้น้ำหนักล่าสุด','deductions'=>'ผู้บันทึกยอดหัก'] as $key=>$label): ?><dt><?php echo e($label); ?></dt><dd data-weigh-detail="<?php echo e($key); ?>"></dd><?php endforeach; ?>
  </dl></div><div class="modal-footer"><a class="btn btn-outline-secondary" id="weighDetailHistory" href="collection_history.php"><i data-lucide="history" aria-hidden="true"></i>ประวัติทั้งหมด</a><button class="btn btn-success" type="button" data-bs-dismiss="modal">ปิด</button></div>
</div></div></div>
<script>
document.title = 'ชั่งยาง — ลาน ' + <?php echo json_encode($lane); ?>;
document.getElementById('weighRecordDetails').addEventListener('show.bs.modal', function(event) {
  if (!event.relatedTarget) return;
  const detail = JSON.parse(event.relatedTarget.dataset.record);
  document.getElementById('weighRecordDetailsTitle').textContent = 'ข้อมูลรายการ #' + detail.id;
  this.querySelectorAll('[data-weigh-detail]').forEach(element => { element.textContent = detail[element.dataset.weighDetail] || '—'; });
  document.getElementById('weighDetailHistory').href = 'collection_history.php?wang_id=' + encodeURIComponent(detail.id);
});
const memberSearch = document.getElementById('weighMemberSearch');
if (memberSearch) {
  const records = Array.from(document.querySelectorAll('#weighRecords [data-member-search]'));
  const clearSearch = document.getElementById('clearWeighSearch');
  const normalize = value => value.normalize('NFC').toLocaleLowerCase('th-TH').replace(/\s+/g, ' ').trim();
  const updateSearch = () => {
    const query = normalize(memberSearch.value);
    let visible = 0;
    records.forEach(record => {
      record.hidden = !normalize(record.dataset.memberSearch).includes(query);
      if (!record.hidden) visible++;
    });
    document.getElementById('weighResultCount').textContent = query ? `${visible.toLocaleString('th-TH')} / ${records.length.toLocaleString('th-TH')} รายการ` : `${records.length.toLocaleString('th-TH')} รายการ`;
    document.getElementById('weighSearchEmpty').hidden = visible > 0;
    clearSearch.hidden = !query;
  };
  memberSearch.addEventListener('input', updateSearch);
  clearSearch.addEventListener('click', () => { memberSearch.value = ''; updateSearch(); memberSearch.focus(); });
  updateSearch();
}
document.querySelectorAll('.weigh-page .weight-form').forEach(form => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"], button.save-weight');
    if (button) { button.dataset.originalHtml = button.innerHTML; button.disabled = true; button.textContent = 'กำลังบันทึก…'; }
  });
});
window.addEventListener('pageshow', () => {
  document.querySelectorAll('.weigh-page .save-weight[data-original-html]').forEach(button => { button.disabled = false; button.innerHTML = button.dataset.originalHtml; });
});
</script>
<?php require __DIR__ . '/missing_collection_round_modal.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>

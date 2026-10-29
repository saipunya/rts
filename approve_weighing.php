<?php
require_once __DIR__ . '/functions.php';
require_login();
$db = db();
ensure_weighing_schema($db);
$round = selected_collection_round($db, false);
$roundId = (int)($round['round_id'] ?? 0);
$lane = (string)($_GET['lan'] ?? 'all');
if (!in_array($lane, ['all','1','2','3','4'], true)) $lane = 'all';
$tab = (string)($_GET['tab'] ?? 'pending');
if (!in_array($tab, ['pending','review','ready'], true)) $tab = 'pending';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!$round) throw new DomainException('ไม่พบรอบรวบรวมยาง');
        if (!csrf_check((string)($_POST['csrf_token'] ?? ''))) throw new DomainException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
        $route = (string)($_POST['route'] ?? '');
        $actor = (string)(current_user()['user_fullname'] ?: current_user()['user_username']);
        $rubberId = (int)($_POST['ru_id'] ?? 0);
        if ($route === 'return') {
            return_weighed_purchase_to_approval($db, $rubberId, $actor, $roundId);
            $_SESSION['approval_success'] = 'ย้อนกลับไปรออนุมัติแล้ว กรุณาตรวจสอบและเลือกดำเนินการใหม่ หากพิมพ์ใบแจ้งรายการไปแล้วให้แจ้งเจ้าหน้าที่ที่เกี่ยวข้องยกเลิกใบเดิม';
            header('Location: approve_weighing.php?' . http_build_query(['round'=>$roundId, 'lan'=>$lane, 'tab'=>'pending']));
            exit;
        }
        approve_weighed_purchase($db, $rubberId, $route, $actor, $roundId);
        $_SESSION['approval_success'] = $route === 'zero' ? 'อนุมัติแล้ว ยอดหักทุกช่องเป็น 0 พร้อมพิมพ์ใบเสร็จ' : 'ส่งตรวจสอบยอดหักแล้ว กรุณาพิมพ์ใบตรวจสอบส่งให้เจ้าหน้าที่';
        if ($route === 'review') {
            header('Location: print_deduction_check.php?ru_id=' . $rubberId);
            exit;
        }
        header('Location: approve_weighing.php?' . http_build_query(['round'=>$roundId, 'lan'=>$lane, 'tab'=>$route === 'zero' ? 'ready' : 'review']));
        exit;
    } catch (Throwable $e) {
        $error = $e instanceof DomainException ? $e->getMessage() : 'ดำเนินการไม่สำเร็จ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
        if (!($e instanceof DomainException)) error_log((string)$e);
    }
}
$sql = "SELECT w.*, x.ru_id, x.net_weight, x.weighed_at, x.approval_route, x.approved_by, x.deductions_saved_at, z.issued_at,
    (SELECT h.returned_by FROM tbl_weighing_review_returns h WHERE h.ru_id=x.ru_id ORDER BY h.return_id DESC LIMIT 1) AS returned_by,
    (SELECT h.returned_at FROM tbl_weighing_review_returns h WHERE h.ru_id=x.ru_id ORDER BY h.return_id DESC LIMIT 1) AS returned_at
    FROM tbl_wang_weighing x JOIN tbl_wangyang w ON w.wang_id=x.wang_id
    LEFT JOIN tbl_rubber_receipts z ON z.ru_id=x.ru_id
    WHERE w.collection_round_id=? AND (?='all' OR w.wang_lan=?) AND LOWER(TRIM(w.wang_status)) NOT IN ('cancelled','canceled','deleted')
    ORDER BY x.weighed_at ASC, x.weighing_id ASC";
$stmt = $db->prepare($sql); $stmt->bind_param('iss', $roundId, $lane, $lane); $stmt->execute();
$groups = ['pending'=>[], 'review'=>[], 'ready'=>[]];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    if ($row['issued_at'] !== null) continue;
    $key = $row['deductions_saved_at'] !== null ? 'ready' : ($row['approval_route'] === 'review' ? 'review' : 'pending');
    $groups[$key][] = $row;
}
$stmt->close();
$success = (string)($_SESSION['approval_success'] ?? ''); unset($_SESSION['approval_success']);
require __DIR__ . '/header.php';
?>
<section class="approval-page">
<?php if ($round) require __DIR__ . '/collection_round_bar.php'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h1 class="h4">อนุมัติหลังชั่งน้ำหนัก</h1><p class="text-muted mb-0">เลือกอนุมัติไม่มียอดหัก หรือส่งตรวจสอบก่อนบันทึกยอดหัก</p></div>
  <a class="btn btn-outline-success" href="weigh_wang.php"><i data-lucide="scale" aria-hidden="true"></i>หน้าชั่งน้ำหนัก</a>
</div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" role="status"><?php echo e($success); ?></div><?php endif; ?>
<form method="get" class="approval-filter">
  <input type="hidden" name="round" value="<?php echo $roundId; ?>"><input type="hidden" name="tab" value="<?php echo e($tab); ?>">
  <label for="approval-lane">ลาน</label><select class="form-select w-auto" id="approval-lane" name="lan">
    <?php foreach (['all'=>'ทุกลาน','1'=>'ลาน 1','2'=>'ลาน 2','3'=>'ลาน 3','4'=>'ลาน 4'] as $value=>$label): ?><option value="<?php echo e($value); ?>" <?php echo $lane === (string)$value ? 'selected' : ''; ?>><?php echo e($label); ?></option><?php endforeach; ?>
  </select><button class="btn btn-success">แสดง</button>
</form>
<nav class="approval-stages" aria-label="สถานะอนุมัติ">
<?php foreach (['pending'=>'รออนุมัติ','review'=>'ตรวจสอบ / บันทึกยอดหัก','ready'=>'รอพิมพ์ใบเสร็จ'] as $key=>$label): ?>
  <a class="btn <?php echo $tab === $key ? 'btn-success' : 'btn-outline-success'; ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?> href="?<?php echo e(http_build_query(['round'=>$roundId,'lan'=>$lane,'tab'=>$key])); ?>"><?php echo e($label); ?> <span class="badge text-bg-light"><?php echo number_format(count($groups[$key])); ?></span></a>
<?php endforeach; ?>
</nav>
<?php if (in_array($tab, ['pending', 'ready'], true) && $groups[$tab]): ?>
<p class="small text-muted mb-2">เรียงตามเวลาชั่งเข้าคิว จากเก่าไปใหม่</p>
<?php if ($tab === 'ready'): ?><p class="small text-muted">รวมรายการอนุมัติไม่มียอดหักและรายการบันทึกยอดหักแล้ว ใช้ลำดับเดียวกันในรายการที่แสดง</p><?php endif; ?>
<div class="approval-list-wrap">
<table class="table approval-list mb-0">
  <thead><tr><th scope="col">ลำดับ</th><th scope="col">สมาชิก</th><th scope="col">ลาน</th><th scope="col">กระสอบ</th><th scope="col">น้ำหนัก (กก.)</th><th scope="col">ชั่งเมื่อ</th><th scope="col">ดำเนินการ</th></tr></thead>
  <tbody>
  <?php foreach ($groups[$tab] as $index=>$row): ?>
    <tr>
      <td class="approval-list-order" data-label="ลำดับ"><?php echo number_format($index + 1); ?></td>
      <td class="approval-list-member"><strong><?php echo e(collection_display_name($row['wang_name'], $row['wang_note'])); ?></strong><small>เลขที่ <?php echo e($row['wang_number']); ?> · กลุ่ม <?php echo e($row['wang_group']); ?> <a href="collection_history.php?wang_id=<?php echo (int)$row['wang_id']; ?>">ประวัติ</a></small>
        <?php if ($row['returned_at'] !== null): ?><small>ย้อนกลับโดย <?php echo e($row['returned_by']); ?> · <?php echo e(thai_datetime_format($row['returned_at'])); ?></small><?php endif; ?>
      </td>
      <td data-label="ลาน"><span class="badge text-bg-light">ลาน <?php echo e($row['wang_lan']); ?></span></td>
      <td data-label="กระสอบ" class="approval-list-number"><?php echo number_format((float)$row['wang_sack'], 2); ?></td>
      <td data-label="น้ำหนัก (กก.)" class="approval-list-number"><strong><?php echo number_format((float)$row['net_weight'], 2); ?></strong></td>
      <td data-label="ชั่งเมื่อ" class="approval-list-date"><?php echo e(thai_datetime_format($row['weighed_at'])); ?></td>
      <td class="approval-list-controls">
        <?php if ($tab === 'pending'): ?>
        <form method="post" class="approval-actions approval-actions-inline">
          <input type="hidden" name="round" value="<?php echo $roundId; ?>"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="ru_id" value="<?php echo (int)$row['ru_id']; ?>">
          <button class="btn btn-sm btn-success" name="route" value="zero" title="อนุมัติ — ไม่มียอดหัก" aria-label="อนุมัติ ไม่มียอดหัก"><i data-lucide="check-check" aria-hidden="true"></i><span>อนุมัติ</span></button>
          <button class="btn btn-sm btn-outline-primary" name="route" value="review" title="ส่งตรวจสอบยอดหัก / พิมพ์ใบแจ้งรายการ" aria-label="ส่งตรวจสอบยอดหักและพิมพ์ใบแจ้งรายการ"><i data-lucide="clipboard-list" aria-hidden="true"></i><span>ตรวจสอบ</span></button>
        </form>
        <?php else: ?>
          <small class="d-block text-muted mb-1"><?php echo $row['approval_route'] === 'zero' ? 'อนุมัติไม่มียอดหัก' : 'บันทึกยอดหักแล้ว'; ?></small>
          <a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="export_rubber_pdf.php?ru_id=<?php echo (int)$row['ru_id']; ?>"><i data-lucide="printer" aria-hidden="true"></i>พิมพ์ใบเสร็จ</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php elseif ($tab === 'review'): ?>
<div class="row g-3">
<?php foreach ($groups[$tab] as $index=>$row): ?>
  <div class="col-12 col-xl-6"><article class="card h-100"><div class="card-body">
    <div class="approval-record-heading"><h2 class="h5"><span class="badge text-bg-light me-1"><?php echo number_format($index + 1); ?></span><?php echo e(collection_display_name($row['wang_name'], $row['wang_note'])); ?></h2>
    <a class="btn btn-sm collection-history-btn" title="ประวัติผู้ดำเนินการ" href="collection_history.php?wang_id=<?php echo (int)$row['wang_id']; ?>"><i data-lucide="history" aria-hidden="true"></i>ประวัติ</a>
    </div>
    <p class="text-muted">#<?php echo (int)$row['wang_id']; ?> · เลขที่ <?php echo e($row['wang_number']); ?> · กลุ่ม <?php echo e($row['wang_group']); ?> · ลาน <?php echo e($row['wang_lan']); ?></p>
    <p><strong><?php echo number_format((float)$row['wang_sack'], 2); ?></strong> กระสอบ · น้ำหนัก <strong><?php echo number_format((float)$row['net_weight'], 2); ?></strong> กก.</p>
    <?php if ($row['returned_at'] !== null): ?><p class="small text-muted">ย้อนกลับไปรออนุมัติล่าสุดโดย <?php echo e($row['returned_by']); ?> · <?php echo e(thai_datetime_format($row['returned_at'])); ?></p><?php endif; ?>
    <?php if ($tab === 'pending'): ?>
      <form method="post" class="approval-actions">
        <input type="hidden" name="round" value="<?php echo $roundId; ?>"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="ru_id" value="<?php echo (int)$row['ru_id']; ?>">
        <button class="btn btn-success" name="route" value="zero"><i data-lucide="check-check" aria-hidden="true"></i>อนุมัติ — ไม่มียอดหัก</button>
        <button class="btn btn-outline-primary" name="route" value="review"><i data-lucide="clipboard-list" aria-hidden="true"></i>ส่งตรวจสอบ / พิมพ์ใบ</button>
      </form>
    <?php elseif ($tab === 'review'): ?>
      <div class="approval-actions"><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="print_deduction_check.php?ru_id=<?php echo (int)$row['ru_id']; ?>"><i data-lucide="printer" aria-hidden="true"></i>พิมพ์ใบตรวจสอบยอดหัก</a><a class="btn btn-success" href="rubbers.php?round=<?php echo $roundId; ?>&amp;action=edit&amp;id=<?php echo (int)$row['ru_id']; ?>"><i data-lucide="pencil" aria-hidden="true"></i>บันทึกยอดหัก</a></div>
      <form method="post" class="mt-2" onsubmit="return confirm('ย้อนกลับไปรออนุมัติหรือไม่? หากพิมพ์ใบแจ้งรายการแล้ว กรุณาแจ้งเจ้าหน้าที่ที่เกี่ยวข้องยกเลิกใบเดิม');">
        <input type="hidden" name="round" value="<?php echo $roundId; ?>"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="ru_id" value="<?php echo (int)$row['ru_id']; ?>">
        <button class="btn btn-outline-secondary" name="route" value="return"><i data-lucide="undo-2" aria-hidden="true"></i>ย้อนกลับไปรออนุมัติ</button>
      </form>
      <p class="small text-muted mt-2 mb-0">ส่งตรวจสอบโดย <?php echo e($row['approved_by']); ?> · เมื่อได้รับผลตรวจสอบแล้วจึงบันทึกยอดหัก</p>
    <?php else: ?>
      <a class="btn btn-success" target="_blank" rel="noopener" href="export_rubber_pdf.php?ru_id=<?php echo (int)$row['ru_id']; ?>"><i data-lucide="printer" aria-hidden="true"></i>พิมพ์ใบเสร็จ</a>
    <?php endif; ?>
  </div></article></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (!$groups[$tab]): ?><div class="alert alert-light border mt-3">ไม่มีรายการในขั้นตอนนี้</div><?php endif; ?>
</section>
<?php require __DIR__ . '/missing_collection_round_modal.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>

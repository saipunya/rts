<?php
require_once __DIR__ . '/functions.php';
require_login();
$db = db(); ensure_weighing_schema($db);
$wangId = max(0, (int)($_GET['wang_id'] ?? 0));
$rubberId = max(0, (int)($_GET['ru_id'] ?? 0));
if (!$wangId && $rubberId) {
    $linked = weighing_for_rubber($db, $rubberId);
    $wangId = (int)($linked['wang_id'] ?? 0);
}
$placement = null; $purchase = null; $weighing = null; $receipt = null;
if ($wangId) {
    $stmt = $db->prepare('SELECT w.*, x.ru_id FROM tbl_wangyang w LEFT JOIN tbl_wang_weighing x ON x.wang_id=w.wang_id WHERE w.wang_id=?');
    $stmt->bind_param('i', $wangId); $stmt->execute(); $placement = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $rubberId = (int)($placement['ru_id'] ?? 0);
}
if ($rubberId) {
    $stmt = $db->prepare('SELECT * FROM tbl_rubber WHERE ru_id=?');
    $stmt->bind_param('i', $rubberId); $stmt->execute(); $purchase = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$wangId && !is_admin() && !in_array((string)($purchase['ru_saveby'] ?? ''), array_filter([(string)(current_user()['user_fullname'] ?? ''), (string)(current_user()['user_username'] ?? '')]), true)) {
        http_response_code(403); exit('ไม่มีสิทธิ์ดูประวัติรายการนี้');
    }
    $weighing = weighing_for_rubber($db, $rubberId);
    $receipt = rubber_receipt_issued($db, $rubberId);
}
if (!$placement && !$purchase) { http_response_code(404); exit('ไม่พบรายการ'); }
$stmt = $db->prepare('SELECT * FROM tbl_collection_audit WHERE (? > 0 AND wang_id=?) OR (? > 0 AND ru_id=?) ORDER BY audit_id DESC');
$stmt->bind_param('iiii', $wangId, $wangId, $rubberId, $rubberId); $stmt->execute(); $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$labels = ['placement_create'=>'บันทึกวางยาง','placement_edit'=>'แก้ไขวางยาง','placement_delete'=>'ลบรายการวางยาง','weigh'=>'บันทึกน้ำหนัก','weight_edit'=>'แก้ไขน้ำหนัก','refer_review'=>'ส่งตรวจสอบยอดหัก','approve_zero'=>'อนุมัติไม่มียอดหัก','return_approval'=>'ย้อนกลับไปรออนุมัติ','deductions_save'=>'บันทึกยอดหัก','deductions_edit'=>'แก้ไขยอดหัก','purchase_edit'=>'แก้ไขข้อมูลรับซื้อ / ยอดหัก','purchase_admin_edit'=>'ผู้ดูแลแก้ไขข้อมูลรับซื้อ','admin_edit'=>'ผู้ดูแลบันทึกการแก้ไข','receipt_issue'=>'ออกใบเสร็จ','receipt_reprint'=>'เปิดพิมพ์ใบเสร็จซ้ำ','review_slip_open'=>'เปิดใบแจ้งตรวจสอบยอดหัก'];
$summary = [
 ['วางยาง (ข้อมูลล่าสุด)', $placement['wang_saveby'] ?? '', $placement['wang_savedate'] ?? ''],
 ['ชั่งน้ำหนัก', $weighing['weighed_by'] ?? '', $weighing['weighed_at'] ?? ''],
 ['แก้ไขน้ำหนักล่าสุด', $weighing['weight_updated_by'] ?? '', $weighing['weight_updated_at'] ?? ''],
 ['อนุมัติ / ส่งตรวจสอบ', $weighing['approved_by'] ?? '', $weighing['approved_at'] ?? ''],
 ['บันทึกยอดหักล่าสุด', $weighing['deductions_saved_by'] ?? '', $weighing['deductions_saved_at'] ?? ''],
 ['ออกใบเสร็จ', $receipt['issued_by'] ?? '', $receipt['issued_at'] ?? ''],
 ['แก้ไขหลังออกใบเสร็จล่าสุด', $receipt['last_edited_by'] ?? '', $receipt['last_edited_at'] ?? ''],
];
$historyRound = collection_round_by_id($db, (int)($placement['collection_round_id'] ?? $weighing['collection_round_id'] ?? 0));
require __DIR__ . '/header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h1 class="h4">ประวัติผู้ดำเนินการ</h1><p class="mb-0"><?php echo e(collection_display_name($placement['wang_name'] ?? $purchase['ru_fullname'], $placement['wang_note'] ?? null)); ?> · <?php echo $wangId ? 'วางยาง #' . $wangId : 'รับซื้อ #' . $rubberId; ?></p></div><a class="btn btn-outline-success align-self-start" href="approve_weighing.php"><i data-lucide="arrow-left" aria-hidden="true"></i>กลับหน้าอนุมัติ</a></div>
<?php if ($historyRound): ?><p class="text-muted"><?php echo e(collection_round_label($historyRound)); ?></p><?php endif; ?>
<div class="card mb-3"><div class="card-body"><h2 class="h6">ข้อมูลผู้ดำเนินการที่มีอยู่</h2>
<?php foreach ($summary as [$label,$person,$time]): if ($person === '') continue; ?><p class="mb-1"><strong><?php echo e($label); ?>:</strong> <?php echo e($person); ?> <span class="text-muted"><?php echo e(thai_datetime_format($time)); ?></span></p><?php endforeach; ?>
</div></div>
<p class="text-muted small">ประวัติรายครั้งเริ่มเก็บเมื่อเปิดใช้ระบบประวัติ ข้อมูลเก่าแสดงผู้ดำเนินการที่มีบันทึกอยู่ วันที่และเวลาเป็นเวลาประเทศไทย</p>
<div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>วันเวลา</th><th>การดำเนินการ</th><th>เจ้าหน้าที่</th><th>บัญชีผู้ใช้</th></tr></thead><tbody>
<?php foreach ($events as $event): ?><tr><td class="text-nowrap"><?php echo e(thai_datetime_format($event['recorded_at'])); ?></td><td><?php echo e($labels[$event['action']] ?? $event['action']); ?><?php if ($event['action'] === 'weight_edit'): $detail=json_decode($event['details'], true); ?><div class="small text-muted"><?php echo e($detail['before'] ?? ''); ?> → <?php echo e($detail['after'] ?? ''); ?> กก.</div><?php endif; ?></td><td><?php echo e($event['actor_name']); ?></td><td><?php echo e($event['actor_username']); ?><?php if ($event['actor_id'] !== null): ?> <small class="text-muted">#<?php echo (int)$event['actor_id']; ?></small><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$events): ?><tr><td colspan="4" class="text-muted text-center">ยังไม่มีประวัติรายครั้ง</td></tr><?php endif; ?>
</tbody></table></div>
<?php require __DIR__ . '/footer.php'; ?>

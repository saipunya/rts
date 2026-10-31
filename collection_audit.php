<?php
require_once __DIR__ . '/functions.php';
require_login();
require_admin();
$db = db(); ensure_weighing_schema($db);
$search = trim((string)($_GET['search'] ?? ''));
$lane = (string)($_GET['lan'] ?? 'all');
if (!in_array($lane, ['all','1','2','3','4'], true)) $lane = 'all';
// Include placements before weighing and older purchases without placement links.
$source = "(SELECT w.wang_id, x.ru_id, w.wang_name AS name, w.wang_note AS note,
    w.wang_number AS member_number, w.wang_group AS member_group, w.wang_lan AS lane,
    COALESCE(x.round_date,w.wang_date) AS business_date, w.wang_savedate AS saved_at,
    w.wang_saveby AS recorder, x.weighing_id, x.approval_route, x.deductions_saved_at, z.issued_at
    FROM tbl_wangyang w LEFT JOIN tbl_wang_weighing x ON x.wang_id=w.wang_id
    LEFT JOIN tbl_rubber_receipts z ON z.ru_id=x.ru_id
    UNION ALL
    SELECT 0, r.ru_id, r.ru_fullname, '', r.ru_number, r.ru_group, r.ru_lan, r.ru_date,
    r.ru_date, r.ru_saveby, NULL, NULL, NULL, z.issued_at
    FROM tbl_rubber r LEFT JOIN tbl_wang_weighing x ON x.ru_id=r.ru_id
    LEFT JOIN tbl_rubber_receipts z ON z.ru_id=r.ru_id WHERE x.ru_id IS NULL) records";
$conditions = []; $params = []; $types = '';
if ($lane !== 'all') { $conditions[] = 'records.lane=?'; $params[]=$lane; $types.='s'; }
if ($search !== '') {
    $conditions[] = "(CAST(records.wang_id AS CHAR)=? OR CAST(records.ru_id AS CHAR)=? OR records.name LIKE ? OR records.note LIKE ? OR records.member_number LIKE ? OR records.member_group LIKE ? OR records.recorder LIKE ? OR EXISTS (SELECT 1 FROM tbl_collection_audit a WHERE ((records.wang_id>0 AND a.wang_id=records.wang_id) OR (records.ru_id>0 AND a.ru_id=records.ru_id)) AND (a.actor_name LIKE ? OR a.actor_username LIKE ?)))";
    $like='%'.$search.'%'; $params=array_merge($params,[$search,$search,$like,$like,$like,$like,$like,$like,$like]); $types.='sssssssss';
}
$where=$conditions ? ' WHERE '.implode(' AND ',$conditions) : '';
$stmt=$db->prepare('SELECT COUNT(*) FROM '.$source.$where);
if ($types!=='') $stmt->bind_param($types,...$params);
$stmt->execute(); $total=(int)$stmt->get_result()->fetch_row()[0]; $stmt->close();
$perPage=25; $pages=max(1,(int)ceil($total/$perPage)); $page=max(1,min($pages,(int)($_GET['page']??1))); $offset=($page-1)*$perPage;
$stmt=$db->prepare('SELECT records.*, (SELECT MAX(a.recorded_at) FROM tbl_collection_audit a WHERE (records.wang_id>0 AND a.wang_id=records.wang_id) OR (records.ru_id>0 AND a.ru_id=records.ru_id)) AS latest_event FROM '.$source.$where.' ORDER BY COALESCE(latest_event,records.saved_at) DESC, records.wang_id DESC, records.ru_id DESC LIMIT ? OFFSET ?');
$params[]=$perPage; $params[]=$offset; $types.='ii'; $stmt->bind_param($types,...$params);
$stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
require __DIR__.'/header.php';
?>
<section>
<h1 class="h4">ตรวจสอบประวัติรายการ</h1>
<p class="text-muted">ผู้ดูแลระบบตรวจสอบผู้วางยาง ชั่งน้ำหนัก อนุมัติ บันทึกยอดหัก ออกใบเสร็จ และประวัติการแก้ไขรายรายการ</p>
<form method="get" class="d-flex flex-wrap gap-2 mb-3">
<label class="visually-hidden" for="auditSearch">ค้นหารายการหรือผู้ดำเนินการ</label>
<input class="form-control" style="flex:1 1 260px" id="auditSearch" name="search" value="<?php echo e($search); ?>" placeholder="ชื่อ เลขสมาชิก กลุ่ม เลขรายการ หรือผู้ดำเนินการ">
<label class="visually-hidden" for="auditLane">ลาน</label><select class="form-select w-auto" id="auditLane" name="lan">
<?php foreach (['all'=>'ทุกลาน','1'=>'ลาน 1','2'=>'ลาน 2','3'=>'ลาน 3','4'=>'ลาน 4'] as $value=>$label): ?><option value="<?php echo e($value); ?>" <?php echo (string)$value===$lane?'selected':''; ?>><?php echo e($label); ?></option><?php endforeach; ?>
</select><button class="btn btn-success">ค้นหา</button><a class="btn btn-outline-secondary" href="collection_audit.php">ล้าง</a>
</form>
<p class="small text-muted">พบ <?php echo number_format($total); ?> รายการ · หน้า <?php echo $page; ?> / <?php echo $pages; ?></p>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>รายการ</th><th>สมาชิก</th><th>วันที่รายการ</th><th>ลาน</th><th>สถานะ</th><th>ความเคลื่อนไหวล่าสุด</th><th>ตรวจสอบ</th></tr></thead><tbody>
<?php foreach ($rows as $row):
$status=$row['issued_at']!==null?'ออกใบเสร็จแล้ว':($row['deductions_saved_at']!==null?'บันทึกยอดหักแล้ว':($row['approval_route']==='review'?'รอบันทึกยอดหัก':($row['weighing_id']!==null?'รออนุมัติ':($row['wang_id']?'รอชั่งน้ำหนัก':'รายการเดิม'))));
$link='collection_history.php?'.http_build_query($row['wang_id']?['wang_id'=>$row['wang_id']]:['ru_id'=>$row['ru_id']]);
?>
<tr><td><?php if ($row['wang_id']): ?>วางยาง #<?php echo (int)$row['wang_id']; ?><br><?php endif; ?><?php if ($row['ru_id']): ?>ใบรับยาง #<?php echo (int)$row['ru_id']; ?><?php endif; ?></td><td><strong><?php echo e(collection_display_name($row['name'],$row['note'])); ?></strong><small class="d-block text-muted">เลขที่ <?php echo e($row['member_number']); ?> · กลุ่ม <?php echo e($row['member_group']); ?></small></td><td><?php echo e(thai_date_short_format($row['business_date'])); ?></td><td><?php echo e($row['lane']); ?></td><td><?php echo e($status); ?></td><td><?php echo e(thai_datetime_format($row['latest_event']??$row['saved_at'])); ?></td><td><a class="btn btn-sm btn-outline-success" href="<?php echo e($link); ?>"><i data-lucide="history" aria-hidden="true"></i>ดูประวัติ</a></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">ไม่พบรายการ</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($pages>1): ?><nav class="d-flex align-items-center justify-content-center gap-3" aria-label="หน้าประวัติรายการ"><?php if ($page>1): ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['search'=>$search,'lan'=>$lane,'page'=>$page-1])); ?>">ก่อนหน้า</a><?php endif; ?><span><?php echo $page; ?> / <?php echo $pages; ?></span><?php if ($page<$pages): ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['search'=>$search,'lan'=>$lane,'page'=>$page+1])); ?>">ถัดไป</a><?php endif; ?></nav><?php endif; ?>
</section>
<?php require __DIR__.'/footer.php'; ?>

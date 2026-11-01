<?php
require_once __DIR__.'/functions.php';
require_login(); require_admin();
$db=db(); ensure_weighing_schema($db);
$error=''; $message='';
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        if (!csrf_check((string)($_POST['csrf_token']??''))) throw new DomainException('โทเค็นไม่ถูกต้อง กรุณาโหลดใหม่');
        if (($_POST['confirmation']??'')!=='ยกเลิก') throw new DomainException('กรุณาพิมพ์คำว่า ยกเลิก เพื่อยืนยัน');
        cancel_collection_record($db,(int)($_POST['wang_id']??0),(int)($_POST['ru_id']??0),(string)($_POST['reason']??''));
        $_SESSION['cancellation_success']='ยกเลิกทั้งกระบวนการแล้ว รายการนี้จะไม่รวมในสถิติและยอดรวม';
        header('Location: cancel_collection.php?'.http_build_query(['wang_id'=>(int)($_POST['wang_id']??0),'ru_id'=>(int)($_POST['ru_id']??0)]));exit;
    } catch (DomainException $e) {$error=$e->getMessage();}
    catch (Throwable $e) {error_log((string)$e);$error='ยกเลิกไม่สำเร็จ ข้อมูลยังไม่ถูกเปลี่ยนแปลง กรุณาลองใหม่';}
}
$message=(string)($_SESSION['cancellation_success']??'');unset($_SESSION['cancellation_success']);
$search=trim((string)($_GET['search']??''));
$status=(string)($_GET['status']??'all');if(!in_array($status,['all','normal','cancelled'],true))$status='all';
$lane=(string)($_GET['lan']??'all');if(!in_array($lane,['all','1','2','3','4'],true))$lane='all';
$date=trim((string)($_GET['date']??''));if($date!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))$date='';
$source="(SELECT w.wang_id, x.ru_id, w.wang_name AS name, w.wang_note AS note, w.wang_number AS member_number, w.wang_group AS member_group, w.wang_lan AS lane,
    COALESCE(x.round_date,w.wang_date) AS business_date, w.wang_date AS placement_date, w.wang_sack AS sacks, x.net_weight, r.ru_value, r.ru_expend, r.ru_netvalue,
    CASE WHEN LOWER(TRIM(w.record_status)) IN ('cancelled','canceled','deleted') OR LOWER(TRIM(w.wang_status)) IN ('cancelled','canceled','deleted') OR LOWER(TRIM(COALESCE(r.record_status,''))) IN ('cancelled','canceled','deleted') THEN 'cancelled' ELSE 'normal' END AS status,
    x.weighing_id, x.approval_route, x.deductions_saved_at, z.issued_at
    FROM tbl_wangyang w LEFT JOIN tbl_wang_weighing x ON x.wang_id=w.wang_id LEFT JOIN tbl_rubber r ON r.ru_id=x.ru_id LEFT JOIN tbl_rubber_receipts z ON z.ru_id=x.ru_id
    UNION ALL
    SELECT 0, r.ru_id, r.ru_fullname, '', r.ru_number, r.ru_group, r.ru_lan, r.ru_date, NULL, NULL, r.ru_quantity, r.ru_value, r.ru_expend, r.ru_netvalue,
    CASE WHEN LOWER(TRIM(COALESCE(r.record_status,''))) IN ('cancelled','canceled','deleted') THEN 'cancelled' ELSE 'normal' END, NULL, NULL, NULL, z.issued_at
    FROM tbl_rubber r LEFT JOIN tbl_wang_weighing x ON x.ru_id=r.ru_id LEFT JOIN tbl_rubber_receipts z ON z.ru_id=r.ru_id WHERE x.ru_id IS NULL) records";
$selected=null;$wangId=max(0,(int)($_POST['wang_id']??$_GET['wang_id']??0));$rubberId=max(0,(int)($_POST['ru_id']??$_GET['ru_id']??0));
if ($wangId||$rubberId) {
    $stmt=$db->prepare('SELECT * FROM '.$source.' WHERE (wang_id=? AND ?>0) OR (ru_id=? AND ?>0) LIMIT 1');$stmt->bind_param('iiii',$wangId,$wangId,$rubberId,$rubberId);$stmt->execute();$selected=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$selected && !$error)$error='ไม่พบรายการ';
}
$conditions=[];$params=[];$types='';
if($search!=='') {$conditions[]='(CAST(wang_id AS CHAR)=? OR CAST(ru_id AS CHAR)=? OR name LIKE ? OR member_number LIKE ? OR member_group LIKE ? OR note LIKE ?)';$like='%'.$search.'%';$params=[$search,$search,$like,$like,$like,$like];$types='ssssss';}
foreach(['status'=>$status,'lane'=>$lane,'business_date'=>$date] as $column=>$value)if($value!==''&&$value!=='all'){$conditions[]=$column.'=?';$params[]=$value;$types.='s';}
$where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
$stmt=$db->prepare('SELECT COUNT(*) FROM '.$source.$where);if($types!=='')$stmt->bind_param($types,...$params);$stmt->execute();$total=(int)$stmt->get_result()->fetch_row()[0];$stmt->close();
$pages=max(1,(int)ceil($total/25));$page=max(1,min($pages,(int)($_GET['page']??1)));$offset=($page-1)*25;
$stmt=$db->prepare('SELECT * FROM '.$source.$where.' ORDER BY business_date DESC, wang_id DESC, ru_id DESC LIMIT 25 OFFSET ?');$params[]=$offset;$types.='i';$stmt->bind_param($types,...$params);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
require __DIR__.'/header.php';
?>
<section>
<h1 class="h4">ยกเลิกรายการทั้งกระบวนการ</h1>
<p class="text-muted">สำหรับ admin · เก็บข้อมูลและประวัติเดิมไว้ · รายการยกเลิกไม่รวมในสถิติและยอดรวมทุกส่วน</p>
<?php if($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
<?php if($selected): $cancel=collection_cancellation($db,(int)$selected['wang_id'],(int)$selected['ru_id']); ?>
<div class="card mb-4"><div class="card-body">
<h2 class="h5"><?php echo e(collection_display_name($selected['name'],$selected['note'])); ?> <span class="badge <?php echo $selected['status']==='cancelled'?'text-bg-danger':'text-bg-success'; ?>"><?php echo $selected['status']==='cancelled'?'ยกเลิก':'ปกติ'; ?></span></h2>
<p>วางยาง #<?php echo (int)$selected['wang_id']; ?> · ใบรับยาง #<?php echo (int)$selected['ru_id']; ?> · เลขสมาชิก <?php echo e($selected['member_number']); ?> · กลุ่ม <?php echo e($selected['member_group']); ?> · ลาน <?php echo e($selected['lane']); ?></p>
<p>วันที่วางยาง <?php echo e($selected['placement_date']?thai_date_format($selected['placement_date']):'ไม่มีข้อมูล'); ?> · วันที่รายการ <?php echo e(thai_date_format($selected['business_date'])); ?></p>
<div class="d-flex flex-wrap gap-2 mb-3"><?php foreach(['วางยาง','ชั่งน้ำหนัก','อนุมัติ','ใบแจ้งตรวจสอบ','บันทึกยอดหัก','ใบเสร็จ'] as $label): ?><span class="badge <?php echo $selected['status']==='cancelled'?'text-bg-danger':'text-bg-light'; ?>"><?php echo e($label); ?><?php echo $selected['status']==='cancelled'?' · ยกเลิก':''; ?></span><?php endforeach; ?></div>
<p>กระสอบ <?php echo $selected['sacks']===null?'—':number_format((float)$selected['sacks'],2); ?> · น้ำหนัก <?php echo $selected['net_weight']===null?'—':number_format((float)$selected['net_weight'],2); ?> กก.</p>
<p>ยอดเงินก่อนหัก <?php echo number_format((float)$selected['ru_value'],2); ?> · ยอดหัก <?php echo number_format((float)$selected['ru_expend'],2); ?> · รับสุทธิ <?php echo number_format((float)$selected['ru_netvalue'],2); ?> บาท</p>
<?php if($selected['issued_at']): ?><p class="text-danger">มีการออกใบเสร็จแล้ว ต้องแจ้งยกเลิกเอกสารเดิมแก่ผู้เกี่ยวข้อง และจัดการเงินที่จ่ายจริงแยกต่างหาก</p><?php endif; ?>
<?php if($cancel): ?><p>ยกเลิกโดย <?php echo e($cancel['actor_name']); ?> (<?php echo e($cancel['actor_username']); ?>) · <?php echo e(thai_datetime_format($cancel['cancelled_at'])); ?><br>เหตุผล: <?php echo e($cancel['reason']); ?></p>
<?php elseif($selected['status']!=='cancelled'): ?>
<form method="post" onsubmit="return confirm('ยืนยันยกเลิกทั้งกระบวนการ? รายการจะไม่รวมในสถิติและยอดรวม และไม่สามารถทำรายการต่อได้');">
<input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="wang_id" value="<?php echo (int)$selected['wang_id']; ?>"><input type="hidden" name="ru_id" value="<?php echo (int)$selected['ru_id']; ?>">
<label class="form-label" for="cancelReason">เหตุผลการยกเลิก</label><textarea class="form-control mb-3" name="reason" id="cancelReason" maxlength="2000" required rows="2"><?php echo e($_POST['reason']??''); ?></textarea>
<label class="form-label" for="cancelConfirm">พิมพ์คำว่า <strong>ยกเลิก</strong> เพื่อยืนยัน</label><input class="form-control mb-3" style="max-width:300px" id="cancelConfirm" name="confirmation" required autocomplete="off">
<button class="btn btn-danger" type="submit">ยกเลิกทั้งกระบวนการ</button>
</form>
<?php endif; ?>
<a class="btn btn-sm btn-outline-secondary mt-3" href="collection_history.php?<?php echo e(http_build_query($selected['wang_id']?['wang_id'=>$selected['wang_id']]:['ru_id'=>$selected['ru_id']])); ?>">ประวัติผู้ดำเนินการ</a>
</div></div>
<?php endif; ?>
<form method="get" class="d-flex flex-wrap gap-2 mb-3">
<input class="form-control" style="flex:1 1 250px" name="search" value="<?php echo e($search); ?>" aria-label="ค้นหา" placeholder="ชื่อ เลขสมาชิก กลุ่ม หรือเลขรายการ">
<input class="form-control w-auto" type="date" name="date" value="<?php echo e($date); ?>" aria-label="วันที่รายการ">
<select class="form-select w-auto" name="lan" aria-label="ลาน"><?php foreach(['all'=>'ทุกลาน','1'=>'ลาน 1','2'=>'ลาน 2','3'=>'ลาน 3','4'=>'ลาน 4'] as $value=>$label): ?><option value="<?php echo e($value); ?>" <?php echo (string)$value===$lane?'selected':''; ?>><?php echo e($label); ?></option><?php endforeach; ?></select>
<select class="form-select w-auto" name="status" aria-label="สถานะ"><?php foreach(['all'=>'ทุกสถานะ','normal'=>'ปกติ','cancelled'=>'ยกเลิก'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo $value===$status?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select>
<button class="btn btn-success">ค้นหา</button><a class="btn btn-outline-secondary" href="cancel_collection.php">ล้าง</a>
</form>
<p class="small text-muted">พบ <?php echo number_format($total); ?> รายการ (รวมรายการยกเลิกเพื่อการตรวจสอบ) · หน้า <?php echo $page; ?> / <?php echo $pages; ?></p>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>วางยาง / ใบรับยาง</th><th>สมาชิก</th><th>วันที่รายการ</th><th>ลาน</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td>#<?php echo (int)$row['wang_id']; ?> / #<?php echo (int)$row['ru_id']; ?></td><td><?php echo e(collection_display_name($row['name'],$row['note'])); ?><small class="d-block text-muted">เลขที่ <?php echo e($row['member_number']); ?> · กลุ่ม <?php echo e($row['member_group']); ?></small></td><td><?php echo e(thai_date_short_format($row['business_date'])); ?></td><td><?php echo e($row['lane']); ?></td><td><span class="badge <?php echo $row['status']==='cancelled'?'text-bg-danger':'text-bg-success'; ?>"><?php echo $row['status']==='cancelled'?'ยกเลิก':'ปกติ'; ?></span></td><td><a class="btn btn-sm btn-outline-secondary" href="?<?php echo e(http_build_query(['wang_id'=>$row['wang_id'],'ru_id'=>$row['ru_id']])); ?>">ตรวจสอบรายการ</a></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6">ไม่พบรายการ</td></tr><?php endif; ?>
</tbody></table></div>
<?php if($pages>1): ?><nav class="d-flex gap-3 justify-content-center"><?php foreach(['ก่อนหน้า'=>$page-1,'ถัดไป'=>$page+1] as $label=>$target): if($target<1||$target>$pages)continue; ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['search'=>$search,'date'=>$date,'lan'=>$lane,'status'=>$status,'page'=>$target])); ?>"><?php echo $label; ?></a><?php endforeach; ?></nav><?php endif; ?>
</section>
<?php require __DIR__.'/footer.php'; ?>

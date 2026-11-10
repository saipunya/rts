<?php
require_once __DIR__ . '/functions.php';
require_admin();
$db = db();
ensure_weighing_schema($db);
$id = max(0, (int)($_GET['ru_id'] ?? 0));
function admin_weight_row(mysqli $db, int $id): ?array {
    $stmt = $db->prepare('SELECT r.*, x.wang_id, x.net_weight, x.price_per_kg, x.approval_route, x.deductions_saved_at, w.wang_lan, w.collection_round_id, z.issued_at FROM tbl_rubber r JOIN tbl_wang_weighing x ON x.ru_id=r.ru_id JOIN tbl_wangyang w ON w.wang_id=x.wang_id LEFT JOIN tbl_rubber_receipts z ON z.ru_id=r.ru_id WHERE r.ru_id=?');
    $stmt->bind_param('i',$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    return $row ?: null;
}
$row = admin_weight_row($db,$id);
if (!$row) { http_response_code(404); exit('ไม่พบรายการชั่ง'); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_check((string)($_POST['csrf_token'] ?? ''))) throw new DomainException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
        $actor=(string)(current_user()['user_fullname'] ?: current_user()['user_username']);
        update_wang_weighing($db,(int)$row['wang_id'],(string)($_POST['net_weight']??''),$actor,(string)$row['wang_lan'],(int)$row['collection_round_id'],(string)($_POST['reason']??''),(string)($_POST['expected_weight']??''));
        $_SESSION['admin_weight_success']=true;
        header('Location: admin_weight_edit.php?ru_id='.$id); exit;
    } catch (Throwable $e) {
        $error=$e instanceof DomainException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'บันทึกไม่สำเร็จ กรุณาลองใหม่';
        if (!($e instanceof DomainException) && !($e instanceof InvalidArgumentException)) error_log('Admin weight edit: '.$e->getMessage());
    }
}
$success=!empty($_SESSION['admin_weight_success']); unset($_SESSION['admin_weight_success']);
require __DIR__ . '/header.php';
?>
<div class="container py-4" style="max-width:760px">
<h1 class="h4">แก้ไขน้ำหนัก — สำหรับ admin</h1>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" role="status">แก้ไขน้ำหนักและคำนวณยอดใหม่แล้ว พร้อมบันทึกประวัติการแก้ไข</div><?php endif; ?>
<div class="card"><div class="card-body">
<h2 class="h5"><?php echo e($row['ru_fullname']); ?></h2>
<p>รายการ #<?php echo $id; ?> · ลาน <?php echo e($row['wang_lan']); ?> · น้ำหนักปัจจุบัน <?php echo number_format((float)$row['net_weight'],2); ?> กก.</p>
<p>ราคาที่ใช้ <?php echo number_format((float)$row['price_per_kg'],2); ?> บาท/กก. · ยอดหัก <?php echo number_format((float)$row['ru_expend'],2); ?> บาท · รับสุทธิ <?php echo number_format((float)$row['ru_netvalue'],2); ?> บาท</p>
<?php if ($row['issued_at']): ?><div class="alert alert-warning">รายการนี้ออกใบเสร็จแล้ว หลังแก้ไขให้พิมพ์ใบเสร็จใหม่และแจ้งผู้เกี่ยวข้องให้ใช้ฉบับล่าสุด ประวัติใบเสร็จเดิมยังคงเก็บไว้</div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
<input type="hidden" name="expected_weight" value="<?php echo e($row['net_weight']); ?>">
<label class="form-label" for="newWeight">น้ำหนักใหม่ (กก.)</label>
<input class="form-control mb-3" id="newWeight" name="net_weight" type="number" inputmode="decimal" min="0.01" max="999999999999.99" step="0.01" required value="<?php echo e($_POST['net_weight']??$row['net_weight']); ?>">
<label class="form-label" for="weightReason">เหตุผลที่แก้ไข</label>
<textarea class="form-control mb-3" id="weightReason" name="reason" maxlength="1000" rows="3" required><?php echo e($_POST['reason']??''); ?></textarea>
<p class="small text-muted">คำนวณยอดเงินใหม่ตามราคาเดิม โดยคงยอดหักและสถานะดำเนินการไว้</p>
<button class="btn btn-success" type="submit">บันทึกน้ำหนักใหม่</button>
<a class="btn btn-outline-secondary" href="collection_history.php?ru_id=<?php echo $id; ?>">ดูประวัติ</a>
<?php if ($row['issued_at']): ?><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="export_rubber_pdf.php?ru_id=<?php echo $id; ?>">พิมพ์ใบเสร็จล่าสุด</a><?php endif; ?>
</form></div></div>
<a class="btn btn-link mt-3" href="weigh_wang.php?<?php echo e(http_build_query(['lan'=>$row['wang_lan'],'status'=>$row['deductions_saved_at']?'completed':'deductions'])); ?>">กลับหน้าชั่ง</a>
</div>
<?php require __DIR__ . '/footer.php'; ?>

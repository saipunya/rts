<?php
require_once __DIR__ . '/functions.php';
require_admin();
require_once __DIR__ . '/demo_reset_functions.php';
$db = db(); ensure_weighing_schema($db);
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_check((string)($_POST['csrf_token'] ?? ''))) throw new DomainException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
        if (trim((string)($_POST['confirmation'] ?? '')) !== 'ล้างข้อมูลเดโม' || ($_POST['acknowledge'] ?? '') !== '1') throw new DomainException('กรุณายืนยันการล้างข้อมูล และพิมพ์คำว่า ล้างข้อมูลเดโม ให้ตรงกัน');
        $user = current_user(); $actor = (string)(($user['user_fullname'] ?? '') ?: ($user['user_username'] ?? ''));
        clear_demo_data($db, ($_POST['include_members'] ?? '') === '1', $actor);
        foreach (['collection_round_id','deductions_success','approval_success','weighing_success'] as $key) unset($_SESSION[$key]);
        $_SESSION['demo_reset_success'] = 'ล้างข้อมูลเดโมเรียบร้อยแล้ว สามารถเริ่มวางยางและบันทึกราคายางใหม่ได้';
        header('Location: demo_reset.php'); exit;
    } catch (DomainException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log((string)$e); $error = 'ล้างข้อมูลไม่สำเร็จ ระบบยกเลิกการล้างทั้งหมด กรุณาลองใหม่'; }
}
$inventory = demo_reset_inventory($db);
$success = (string)($_SESSION['demo_reset_success'] ?? ''); unset($_SESSION['demo_reset_success']);
require __DIR__ . '/header.php';
?>
<section style="max-width:900px;margin:auto">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><h1 class="h4 text-danger">ล้างข้อมูลเดโม</h1><p class="text-muted mb-0">เริ่มสาธิตกระบวนการใหม่ โดยเก็บบัญชีผู้ใช้และสิทธิ์การเข้าใช้งานไว้</p></div><a class="btn btn-outline-secondary" href="index.php">กลับหน้าแรก</a></div>
  <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo e($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo e($error); ?></div><?php endif; ?>
  <div class="alert alert-warning">ข้อมูลที่เลือกจะถูกลบถาวรทั้งระบบ รวมถึงรายการที่อนุมัติและออกใบเสร็จแล้ว ประวัติจะเริ่มใหม่ พร้อมเก็บชื่อผู้ดำเนินการล้างข้อมูลครั้งนี้</div>
  <div class="table-responsive border rounded-3 mb-4"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>ข้อมูลที่จะล้าง</th><th class="text-end">จำนวนรายการปัจจุบัน</th></tr></thead><tbody>
    <?php foreach ($inventory as $table=>$item): ?><tr<?php echo $table === 'tbl_member' ? ' class="table-light"' : ''; ?>><td><?php echo e($item['label']); ?></td><td class="text-end"><?php echo number_format($item['count']); ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <form method="post" class="card border-danger-subtle"><div class="card-body">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="include_members" value="1" id="includeMembers" <?php echo isset($_POST['include_members']) ? 'checked' : ''; ?>><label class="form-check-label" for="includeMembers">ล้างข้อมูลสมาชิกด้วย</label><div class="form-text">หากไม่เลือก จะเก็บสมาชิกไว้สำหรับทดสอบวางยางรอบใหม่</div></div>
    <div class="mb-3"><label class="form-label" for="resetConfirmation">พิมพ์ <strong>ล้างข้อมูลเดโม</strong> เพื่อยืนยัน</label><input class="form-control" id="resetConfirmation" name="confirmation" autocomplete="off" required></div>
    <div class="form-check mb-3"><input class="form-check-input" id="resetAcknowledge" name="acknowledge" value="1" type="checkbox" required><label class="form-check-label" for="resetAcknowledge">ยืนยันว่าต้องการล้างข้อมูลตามรายการข้างต้น</label></div>
    <div class="d-flex flex-wrap gap-2"><button class="btn btn-danger" type="submit"><i data-lucide="trash-2" aria-hidden="true"></i>ล้างข้อมูลเดโมทั้งหมด</button><a class="btn btn-outline-secondary" href="index.php">ยกเลิก</a></div>
  </div></form>
</section>
<?php require __DIR__ . '/footer.php'; ?>

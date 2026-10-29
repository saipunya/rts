<?php
require_once __DIR__ . '/functions.php';
require_admin();
$db = db(); ensure_weighing_schema($db);
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_check((string)($_POST['csrf_token'] ?? ''))) throw new DomainException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
        $action = (string)($_POST['action'] ?? 'enable');
        $user = current_user(); $actor = (string)(($user['user_fullname'] ?? '') ?: ($user['user_username'] ?? ''));
        if ($action === 'disable') {
            record_collection_audit($db, 'demo_test_disable', 0, 0, $actor);
            unset($_SESSION['demo_test_sale_date'], $_SESSION['demo_test_user_id']);
            header('Location: wang_main.php'); exit;
        }
        if ($action !== 'enable') throw new DomainException('คำสั่งไม่ถูกต้อง');
        $date = trim((string)($_POST['sale_date'] ?? ''));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new DomainException('กรุณาระบุวันชั่งทดสอบให้ถูกต้อง');
        record_collection_audit($db, 'demo_test_enable', 0, 0, $actor, ['sale_date'=>$date,'placement_date'=>$parsed->modify('-2 days')->format('Y-m-d')]);
        $_SESSION['demo_test_sale_date'] = $date;
        $_SESSION['demo_test_user_id'] = (int)$user['user_id'];
        header('Location: wang_main.php'); exit;
    } catch (DomainException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log((string)$e); $error = 'ตั้งค่าโหมดทดสอบไม่สำเร็จ กรุณาลองใหม่'; }
}
$settings = demo_test_settings();
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
$latestPrice = latest_rubber_price($db);
$defaultDate = $settings['sale_date'] ?? max($today, (string)($latestPrice['pr_date'] ?? $today));
require __DIR__ . '/header.php';
?>
<section style="max-width:850px;margin:auto">
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="h4">ทดสอบครบกระบวนการในวันเดียว</h1><p class="text-muted mb-0">จำลองวันที่สำหรับทดสอบ โดยไม่ต้องรอให้ผ่านไป 3 วันจริง</p></div><a class="btn btn-outline-secondary align-self-start" href="wang_main.php">กลับหน้าวางยาง</a></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo e($error); ?></div><?php endif; ?>
<div class="alert alert-info">ใช้ได้เฉพาะ admin บัญชีนี้ ข้อมูลทดสอบจะบันทึกลงระบบเดโมจริง ส่วนวันเวลาที่บันทึกและชื่อผู้ดำเนินการยังเก็บตามจริง</div>
<form method="post" class="card card-body mb-4"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="enable"><label class="form-label" for="testSaleDate">วันที่ราคายาง / วันชั่งทดสอบ</label><input class="form-control mb-3" style="max-width:300px" type="date" id="testSaleDate" name="sale_date" value="<?php echo e($defaultDate); ?>" required><div><button class="btn btn-success" type="submit">เปิดโหมดทดสอบในวันเดียว</button></div></form>
<h2 class="h6">วิธีทดสอบ</h2><ol class="lh-lg"><li>เลือกวันชั่งทดสอบ แล้วเปิดโหมดทดสอบ</li><li>บันทึกวางยาง ระบบจะใช้วันที่จำลองย้อนหลังจากวันชั่ง 2 วัน</li><li>บันทึกราคายาง โดยใช้วันชั่งทดสอบเดียวกัน</li><li>ชั่งน้ำหนัก → อนุมัติหลังชั่ง → บันทึกยอดหัก → พิมพ์ใบเสร็จ ได้ทันที</li><li>กด “ปิดโหมดทดสอบ” เมื่อต้องการกลับไปใช้วันจริง</li></ol>
<p class="small text-muted">หากมีราคายางวันที่ใหม่กว่าอยู่แล้ว ให้เลือกวันชั่งทดสอบที่ไม่น้อยกว่าวันที่ราคาล่าสุด เพื่อให้คิวชั่งเรียกข้อมูลของการทดสอบนี้</p>
</section>
<?php require __DIR__ . '/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/app_bootstrap.php';
header('Cache-Control: no-store');
$settings=rts_license_settings();
$status=$GLOBALS['rts_license_status'] ?? rts_license_status();
if (empty($_SESSION['rts_license_csrf'])) $_SESSION['rts_license_csrf']=bin2hex(random_bytes(32));
$error=''; $success='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
    $token=$_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['rts_license_csrf'],$token)) {
        http_response_code(403); $error='แบบฟอร์มหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
    } elseif (!is_string($_POST['activation_code'] ?? null) || strlen($_POST['activation_code'])>16000) {
        $error='กรุณาวางรหัสใช้งานที่ได้รับจากผู้พัฒนา';
    } else {
        try {
            rts_license_activate($_POST['activation_code']);
            $_SESSION['rts_license_success']='เปิดใช้รหัสสำเร็จ ข้อมูลเดิมยังอยู่และสามารถใช้งานต่อได้';
            header('Location: activation.php',true,303); exit;
        } catch (DomainException $exception) { $error=$exception->getMessage(); }
        catch (Throwable $exception) { error_log('RTS activation: '.$exception->getMessage()); $error='เปิดใช้รหัสไม่สำเร็จ กรุณาตรวจรหัสหรือติดต่อผู้พัฒนา'; }
    }
}
$success=(string)($_SESSION['rts_license_success'] ?? ''); unset($_SESSION['rts_license_success']);
function rts_activation_escape($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>สิทธิ์ใช้งาน RTS Demo</title>
<link rel="stylesheet" href="assets/offline/bootstrap-5.3.3.min.css"><link rel="stylesheet" href="assets/offline/sarabun.css">
<style>body{font-family:Sarabun,Tahoma,sans-serif;background:#f3f7f4;color:#233d2c}.license-shell{max-width:960px}.form-control,.btn{min-height:44px}textarea{font:14px/1.6 Consolas,monospace;overflow-wrap:anywhere}.card{border-color:#dce9df;border-radius:14px}.contact-row{overflow-wrap:anywhere}.request-code{min-height:125px}.btn-success{background:#245f39;border-color:#245f39}</style></head><body>
<main class="container license-shell py-4 py-md-5">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><h1 class="h4">สิทธิ์ใช้งานระบบรวบรวมยาง</h1><p class="text-secondary mb-0">ทดลอง 60 วัน · ขอรหัสจากผู้พัฒนาเพื่อใช้งานต่อ</p></div><?php if ($status['allowed']): ?><a class="btn btn-outline-success" href="login.php">กลับเข้าสู่ระบบ</a><?php endif; ?></div>
<?php if ($success): ?><div class="alert alert-success" role="status"><?=rts_activation_escape($success)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?=rts_activation_escape($error)?></div><?php endif; ?>
<div class="alert <?=$status['allowed'] ? 'alert-success' : 'alert-warning'?>" role="status"><strong><?=rts_activation_escape($status['message'])?></strong>
<?php if (isset($status['expires_at'])): ?><div class="mt-1">ใช้ได้ถึง <?=rts_activation_escape(rts_license_date($status['expires_at']))?> น. (เวลาไทย)<?php if ($status['allowed']): ?> · เหลือ <?=(int)$status['remaining_days']?> วัน<?php endif; ?></div><?php elseif (($status['mode'] ?? '')==='permanent'): ?><div>สิทธิ์ไม่จำกัดเวลา</div><?php endif; ?>
<?php if (!$status['allowed']): ?><div class="mt-1">ข้อมูลเดิมยังอยู่ หลังเปิดใช้รหัสที่ถูกต้องจะทำงานต่อได้ ไม่ต้องติดตั้งใหม่ สามารถสำรองข้อมูลได้จากเมนู Start → RTS Demo → Backup RTS Demo</div><?php endif; ?></div>
<div class="row g-4"><section class="col-12 col-lg-5"><h2 class="h5 mb-3">1. ติดต่อขอรหัสใช้งาน</h2>
<div class="contact-row mb-2"><strong><?=rts_activation_escape($settings['developer_name'])?></strong></div>
<div class="contact-row mb-2">โทร <a href="tel:<?=rts_activation_escape(str_replace('-','',$settings['developer_phone']))?>"><?=rts_activation_escape($settings['developer_phone'])?></a></div>
<div class="contact-row mb-3">LINE ID: <strong><?=rts_activation_escape($settings['developer_line'])?></strong></div>
<p class="text-secondary">ส่งรหัสขอใช้งานด้านล่างให้ผู้พัฒนา โดยคัดลอกทั้งข้อความ รหัสนี้ระบุชุดติดตั้งในเครื่องนี้และไม่มีข้อมูลสมาชิก</p>
<label for="requestCode" class="form-label">รหัสขอใช้งานของเครื่องนี้</label><textarea id="requestCode" class="form-control request-code" readonly><?=rts_activation_escape($status['request'])?></textarea>
<button type="button" class="btn btn-outline-secondary mt-2 w-100" id="copyRequest" <?=$status['request']==='' ? 'disabled' : ''?>>คัดลอกรหัสขอใช้งาน</button><p id="copyStatus" class="small text-success mt-2" role="status"></p>
</section><section class="col-12 col-lg-7"><h2 class="h5 mb-3">2. ใส่รหัสที่ได้รับจากผู้พัฒนา</h2><form method="post" class="card shadow-sm"><div class="card-body p-3 p-md-4">
<input type="hidden" name="csrf_token" value="<?=rts_activation_escape($_SESSION['rts_license_csrf'])?>">
<label for="activationCode" class="form-label">รหัสใช้งาน</label><textarea id="activationCode" name="activation_code" class="form-control" rows="8" maxlength="16000" required spellcheck="false" autocomplete="off" placeholder="RTS1.…"></textarea>
<div class="form-text mb-3">รหัสใช้ได้เฉพาะชุดติดตั้งในเครื่องนี้ กรอกได้แม้ครบกำหนดทดลองแล้ว</div><button class="btn btn-success w-100" type="submit" <?=$status['request']==='' ? 'disabled' : ''?>>เปิดใช้งานต่อ</button>
</div></form></section></div>
</main><script>
document.getElementById('copyRequest').addEventListener('click',async()=>{const input=document.getElementById('requestCode');try{await navigator.clipboard.writeText(input.value);document.getElementById('copyStatus').textContent='คัดลอกรหัสแล้ว';}catch(e){input.focus();input.select();document.getElementById('copyStatus').textContent='เลือกข้อความแล้ว กด Ctrl+C เพื่อคัดลอก';}});
</script></body></html>

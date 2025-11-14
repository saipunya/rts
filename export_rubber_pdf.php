<?php
require_once __DIR__ . '/functions.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
  header('Content-Type: text/html; charset=UTF-8');
  http_response_code(500);
  echo 'ไม่พบ Dompdf (vendor/autoload.php). โปรดติดตั้ง: composer require dompdf/dompdf';
  exit;
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$ru_id = (int)($_GET['ru_id'] ?? 0);
if ($ru_id <= 0) {
  header('Content-Type: text/plain; charset=UTF-8');
  http_response_code(400);
  echo 'ru_id ไม่ถูกต้อง';
  exit;
}

$db = db();
$st = $db->prepare("SELECT * FROM tbl_rubber WHERE ru_id = ? LIMIT 1");
$st->bind_param('i', $ru_id);
$st->execute();
$res = $st->get_result();
$row = $res ? $res->fetch_assoc() : null;
$st->close();

if (!$row) {
  header('Content-Type: text/plain; charset=UTF-8');
  http_response_code(404);
  echo 'ไม่พบข้อมูล';
  exit;
}

// numeric values
$qty       = (float)$row['ru_quantity'];
$hoon      = (float)$row['ru_hoon'];
$loan      = (float)$row['ru_loan'];
$short     = (float)$row['ru_shortdebt'];
$deposit   = (float)$row['ru_deposit'];
$trade     = (float)$row['ru_tradeloan'];
$insure    = (float)$row['ru_insurance'];
$value     = isset($row['ru_value'])    ? (float)$row['ru_value']    : 0.0;
$expend    = isset($row['ru_expend'])   ? (float)$row['ru_expend']   : ($hoon+$loan+$short+$deposit+$trade+$insure);
$netvalue  = isset($row['ru_netvalue']) ? (float)$row['ru_netvalue'] : ($value-$expend);
$unitPrice = $qty > 0 ? $value / $qty : 0;

function nf($n){ return number_format((float)$n, 2); }

$printedAt = date('Y-m-d H:i:s');

// new: prepare Thai font embedding (use Sarabun fonts available in assets/fonts)
$fontDir     = __DIR__ . '/assets/fonts';
// prefer Sarabun fonts that exist in the repository
$mainFontTtf = $fontDir . '/Sarabun-Regular.ttf';
$boldFontTtf = $fontDir . '/Sarabun-Bold.ttf';
$hasThaiFont = file_exists($mainFontTtf) && file_exists($boldFontTtf);

$fontCss = $hasThaiFont
  ? "
  @font-face {
    font-family: 'Sarabun';
    src: url('assets/fonts/Sarabun-Regular.ttf') format('truetype');
    font-weight: normal; font-style: normal;
  }
  @font-face {
    font-family: 'Sarabun';
    src: url('assets/fonts/Sarabun-Bold.ttf') format('truetype');
    font-weight: bold; font-style: normal;
  }
  body { font-family: 'Sarabun', DejaVu Sans, sans-serif; font-size: 14px; color: #111; }
  "
  : "body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }";

// inject CSS with Thai font (replace the old body font rule)
$html = '
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <style>
    @page { margin: 16mm 14mm; }
    ' . $fontCss . '
    h1 { font-size: 16px; margin: 0 0 6px; }
    .meta { font-size: 11px; color: #555; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
    .no-border td { border: 0; padding: 2px 0; }
    .text-end { text-align: right; }
    .muted { color: #666; }
    .box { border: 1px solid #ccc; border-radius: 6px; padding: 8px 10px; margin-top: 8px; }
    .totals td { font-weight: bold; }
  </style>
</head>
<body>
  <h1>ใบสรุปการรับยาง (รายคน)</h1>
  <div class="meta">
    เลขที่รายการ: '.(int)$row['ru_id'].' | พิมพ์เมื่อ: '.e($printedAt).'
  </div>

  <table>
    <tr>
      <td>วันที่</td>
      <td>'.e($row['ru_date']).'</td>
      <td>ลาน</td>
      <td>'.e($row['ru_lan']).'</td>
    </tr>
    <tr>
      <td>กลุ่ม</td>
      <td>'.e($row['ru_group']).'</td>
      <td>เลขที่</td>
      <td>'.e($row['ru_number']).'</td>
    </tr>
    <tr>
      <td>ชื่อ-สกุล</td>
      <td>'.e($row['ru_fullname']).'</td>
      <td>ชั้น</td>
      <td>'.e($row['ru_class']).'</td>
    </tr>
  </table>

  <div class="box">
    <table class="no-border">
      <tr>
        <td>ปริมาณ (กก.)</td>
        <td class="text-end">'.nf($qty).'</td>
      </tr>
      <tr>
        <td>ราคา/กก. (อนุมาน)</td>
        <td class="text-end">'.($unitPrice > 0 ? nf($unitPrice) : '-').'</td>
      </tr>
      <tr>
        <td class="muted">มูลค่า</td>
        <td class="text-end">'.nf($value).'</td>
      </tr>
    </table>
  </div>

  <div class="box">
    <table>
      <tr><th colspan="2">รายละเอียดการหัก</th></tr>
      <tr><td>หุ้น</td><td class="text-end">'.nf($hoon).'</td></tr>
      <tr><td>เงินกู้</td><td class="text-end">'.nf($loan).'</td></tr>
      <tr><td>หนี้สั้น</td><td class="text-end">'.nf($short).'</td></tr>
      <tr><td>เงินฝาก</td><td class="text-end">'.nf($deposit).'</td></tr>
      <tr><td>กู้ซื้อขาย</td><td class="text-end">'.nf($trade).'</td></tr>
      <tr><td>ประกันภัย</td><td class="text-end">'.nf($insure).'</td></tr>
      <tr class="totals"><td>หักรวม</td><td class="text-end">'.nf($expend).'</td></tr>
      <tr class="totals"><td>ยอดสุทธิ</td><td class="text-end">'.nf($netvalue).'</td></tr>
    </table>
  </div>

  <table class="no-border" style="margin-top:10px;">
    <tr>
      <td>บันทึกโดย: '.e($row['ru_saveby']).'</td>
      <td class="text-end">วันที่บันทึก: '.e($row['ru_savedate']).'</td>
    </tr>
  </table>
</body>
</html>
';

$options = new Options();
$options->set('isRemoteEnabled', true);
// new: allow loading local assets (fonts) and set default Thai font when available
$options->setChroot(__DIR__);
if ($hasThaiFont) {
  $options->set('defaultFont', 'Sarabun');
}

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A5', 'portrait'); // compact sheet; change to A4 if preferred
$dompdf->render();

$filename = 'rubber_' . (int)$row['ru_id'] . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;

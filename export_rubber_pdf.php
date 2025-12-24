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

// Enable detailed errors for debugging 500 errors (remove in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use Dompdf\Dompdf;
use Dompdf\Options;

if (!function_exists('e')) {
  // normalize input to NFC (if Normalizer available) then escape for HTML
  function e($s){
    $s = (string)$s;
    if (class_exists('Normalizer')) {
      $s = Normalizer::normalize($s, Normalizer::FORM_C);
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
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

// --- สร้างเนื้อหา $card สำหรับแสดงข้อมูลรายบุคคล ---
$card = '<div class="card">
  <div class="header-card">
    <h1 class="title-row" style="text-align:center; margin-bottom: 0;">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด </h1>
    <h2 style="text-align:center; margin-bottom: 0;">เลขที่ 54 หมู่ที่ 4 ตำบลทุ่งลุยลาย อำเภอคอนสาร จังหวัดชัยภูมิ 36180 โทร. 044105752,0899441753</h2>
    <h3 style="text-align:center; margin-bottom: 0;">ใบรับยางก้อนถ้วย (ID: '.e($row['ru_id']).')</h3>
  </div>
  <table class="info-table kv" style="width:100%;">
    <tr>
      <td>
        <div class="k">ลาน ' .e($row['ru_lan']).'</div>
      </td>
      <td>
        <div class="k">กลุ่ม '.e($row['ru_group']).'</div>
      </td>
      <td>
        <div class="k">เลขที่ '.e($row['ru_number']).'</div>
      </td>
      <td>
        <div class="k">ชื่อ-สกุล '.e($row['ru_fullname']).'</div>
      </td>
    </tr>
  </table>
  <table class="columns" style="margin-bottom:12px;">
    <tr>
      <td class="left" style="width:50%;vertical-align:top;">
        <table class="data-table" style="margin-bottom:0;">
          <thead><tr><th colspan="2">รายการรับ</th></tr></thead>
          <tbody>
            <tr><td>ปริมาณ (กก.)</td><td class="text-end">'.nf($qty).'</td></tr>
            <tr><td>ราคา(บาท/กก.)</td><td class="text-end">'.nf($unitPrice).'</td></tr>
            <tr><td>ยอดเงินรวม</td><td class="text-end" style="font-weight: bold;">'.nf($value).'</td></tr>
          </tbody>
        </table>
      </td>
      <td class="right" style="width:50%;vertical-align:top;">
        <table class="data-table" style="margin-bottom:0;">
          <thead><tr><th colspan="2">รายการหัก</th></tr></thead>
          <tbody>
            <tr><td>หุ้น</td><td class="text-end">'.nf($hoon).'</td></tr>
            <tr><td>เงินกู้</td><td class="text-end">'.nf($loan).'</td></tr>
            <tr><td>หนี้สั้น</td><td class="text-end">'.nf($short).'</td></tr>
            <tr><td>เงินฝาก</td><td class="text-end">'.nf($deposit).'</td></tr>
            <tr><td>ลูกหนี้การค้า</td><td class="text-end">'.nf($trade).'</td></tr>
            <tr><td>ประกันภัย</td><td class="text-end">'.nf($insure).'</td></tr>
            <tr><td><b>รายการหักรวม</b></td><td class="text-end"><b>'.nf($expend).'</b></td></tr>
          </tbody>
        </table>
      </td>
    </tr>
  </table>
  <div class="kpi" style="text-align:right;">
    <span style="font-size: 20px;">ยอดรับสุทธิ: </span>
    <span class="kpi-value" style="font-size: 20px;">'.nf($netvalue).'</span>
    <span class="unit" style="font-size: 20px;">บาท</span>
  </div>
  <table class="signature-table" style="width:100%;margin-top:18px;">
    <tr>
      <td style="width:60%;padding-top:18px;">
        <div class="sig-line">&nbsp;</div>
        <div class="sig-caption" style="font-size: 16px;"> ผู้บันทึก: '.e($row['ru_saveby']).' </div>
      </td>
      <td style="width:40%;text-align:right;font-size:15px;vertical-align:bottom;">
        <div class="sig-caption " style="font-size:16px;">วันที่บันทึก: '.e(thai_date_format($row['ru_date'])).'</div>
      </td>
    </tr>
  </table>
</div>';

// เปลี่ยนเป็นใช้ฟอนต์จาก assets/fonts แบบอ้างไฟล์ตรง (เหมือน export_rubbers_pdf.php)
$fontDir = __DIR__ . '/assets/fonts';
$fontsByFamily = [];
$fontCss = '';
if (is_dir($fontDir)) {
  $ttf = glob($fontDir.'/*.ttf') ?: [];
  $otf = glob($fontDir.'/*.otf') ?: [];
  $tff = glob($fontDir.'/*.tff') ?: [];
  $files = array_merge($ttf, $otf, $tff);
  foreach ($files as $file) {
    $base = basename($file);
    $family = preg_replace('/[- ]?(Thin|ExtraLight|UltraLight|Light|Regular|Book|Normal|Medium|SemiBold|DemiBold|Bold|ExtraBold|UltraBold|Black|Heavy|Italic|Oblique)/i', '', pathinfo($base, PATHINFO_FILENAME));
    $weight = 400;
    if (stripos($base, 'Thin') !== false) $weight = 100;
    elseif (stripos($base, 'ExtraLight') !== false || stripos($base, 'UltraLight') !== false) $weight = 200;
    elseif (stripos($base, 'Light') !== false) $weight = 300;
    elseif (stripos($base, 'Medium') !== false) $weight = 500;
    elseif (stripos($base, 'SemiBold') !== false || stripos($base, 'DemiBold') !== false) $weight = 600;
    elseif (stripos($base, 'Bold') !== false) $weight = 700;
    elseif (stripos($base, 'ExtraBold') !== false || stripos($base, 'UltraBold') !== false) $weight = 800;
    elseif (stripos($base, 'Black') !== false || stripos($base, 'Heavy') !== false) $weight = 900;
    $style = (stripos($base, 'Italic') !== false || stripos($base, 'Oblique') !== false) ? 'italic' : 'normal';
    $fontsByFamily[$family][] = ['file' => $base, 'weight' => $weight, 'style' => $style];
  }
  foreach ($fontsByFamily as $family => $items) {
    foreach ($items as $it) {
      $ext = strtolower(pathinfo($it['file'], PATHINFO_EXTENSION));
      $fmt = ($ext === 'otf') ? 'opentype' : 'truetype';
      $fontCss .= '@font-face{font-family:"'.$family.'";font-style:'.$it['style'].';font-weight:'.$it['weight'].';src:url("assets/fonts/'.$it['file'].'") format("'.$fmt.'");}'."\n";
    }
  }
}
$preferredFamilies = ['THSarabunNew','Sarabun','NotoSansThai'];
$defaultFamily = null;
foreach ($preferredFamilies as $pf) {
  if (isset($fontsByFamily[$pf])) { $defaultFamily = $pf; break; }
}
if (!$defaultFamily && $fontsByFamily) {
  $keys = array_keys($fontsByFamily);
  $defaultFamily = $keys[0];
}
$hasThaiFonts = (bool)$defaultFamily;

// ปรับ CSS layout ให้ทันสมัยและเหมาะกับ print
$style = '
  @page { margin: 5mm; }
  html, body { width: 100%; height: 100%; }
  .container, .card { page-break-inside: avoid !important; }
  '.$fontCss.'
  body { font-family: '.($hasThaiFonts ? '"'.$defaultFamily.'", ' : '').'DejaVu Sans, sans-serif; font-size: 12px; color: #222; background: #fff; line-height: 1.15; }
  .container { max-width: 1100px; margin: 0 auto; }
  .card { background: #fff; border-radius: 6px; border: 1px solid #ddd; padding: 4px; margin-bottom: 2px; }
  .columns { width: 100%; border-collapse: separate; table-layout: fixed; }
  .columns td { vertical-align: top; padding-top: 0; }
  h1, .title-row { font-size: 16px; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 2px; letter-spacing: 0.2px; }
  .header-card h2 { font-size: 12px; margin: 0; }
  .header-card h3 { font-size: 13px; margin: 2px 0 4px; }
  .meta { font-size: 9px; color: #64748b; margin-bottom: 2px; }
  .text-end { text-align: right; font-variant-numeric: tabular-nums; }
  .unit { color: #64748b; font-size: 10px; }
  .header-card { padding-bottom: 2px; border-bottom: 1px solid #e0e7ff; margin-bottom: 2px; }
  .table, table { width: 100%; border-collapse: collapse; }
  .info-table td { padding: 1px 2px; font-size: 14px; }
  .info-table.kv .k { color: #334155; font-weight: 600; font-size: 16px; }
  .info-table.kv td { border: 1px solid #e5e7eb; }
  .data-table { border: 1px solid #999; border-radius: 4px; background: #fff; margin-bottom: 2px; }
  .data-table thead th { padding: 2px; color: #000; font-weight: 700; text-align: left; border-bottom: 1px solid #999; font-size: 14px; }
  .data-table td, .data-table th { padding: 2px; border: 1px solid #e5e7eb; background: #fff; font-size: 14px; height: auto; }
  .data-table tr:nth-child(even) td { background: #f7f7f7; }
  .kpi { margin-top: 2px; background: #f7f7f7; padding: 2px 4px; page-break-inside: avoid; font-size: 12px; }
  .kpi .kpi-value { font-size: 14px; font-weight: 700; color: #000; }
  .signature-table { margin-top: 4px; page-break-inside: avoid; }
  .signature-table tr, .signature-table td { page-break-inside: avoid; }
  .sig-line { border-bottom: 1px dotted #6366f1; width: 55%; height: 8px; display: block; margin-bottom:2px; }
  .sig-caption { font-size: 11px; color: #64748b; margin-top: 1px; }
  .sig-name { font-size: 11px; margin-top: 1px; font-weight: 600; }
  table td, table th{ font-size: 14px !important; }
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .signature-table tr, .signature-table td { font-size: 12px; }
  .signature-table { font-size: 12px; }

';

$html = '<!doctype html><html lang="th"><head><meta charset="UTF-8"><style>'.$style.'</style></head><body>
  <div class="container">' . $card . '</div>
</body></html>';

// Normalize final HTML to NFC (helps combining marks ordering)
if (class_exists('Normalizer')) {
  $html = Normalizer::normalize($html, Normalizer::FORM_C);
}

// Dompdf options (ตั้งค่าเหมือน export_rubbers_pdf.php)
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('chroot', __DIR__);
if (!empty($defaultFamily)) {
  $options->set('defaultFont', $defaultFamily);
}

// Disable font subsetting which can cause Thai combining characters (สระ/วรรณยุกต์) to render incorrectly
// Set both common option names to cover dompdf versions
$options->set('isFontSubsettingEnabled', false);
$options->set('enable_font_subsetting', false);

$dompdf = new Dompdf($options);
try {
  $dompdf->loadHtml($html, 'UTF-8');
  // Set to A4 landscape (แนวนอน)
 // --- กำหนดกระดาษขนาด 22.5 cm x 15 cm แนวนอน ---
$paperWidth  = 22.5 * 28.3464567;   // 637 pt
$paperHeight = 15   * 28.3464567;   // 425 pt

// ใช้ custom paper size
$dompdf->setPaper([0, 0, $paperWidth, $paperHeight]);
// -----------------------------------------------
  $dompdf->render();

  // Add page numbers at bottom-right
  $canvas = $dompdf->getCanvas();
  $w = $canvas->get_width();
  $h = $canvas->get_height();
  $fontMetrics = $dompdf->getFontMetrics();
  $font = $fontMetrics->getFont($defaultFamily ?: 'THSarabunNew', 'normal');
  // $canvas->page_text($w - 100, $h - 24, "หน้า {PAGE_NUM}/{PAGE_COUNT}", $font, 10, [0,0,0]);

  $filename = 'rubber_' . (int)$row['ru_id'] . '.pdf';
  $dompdf->stream($filename, ['Attachment' => false]);
  exit;
} catch (\Exception $e) {
  // Output a readable error for debugging 500
  header('Content-Type: text/plain; charset=UTF-8');
  http_response_code(500);
  echo "เกิดข้อผิดพลาดขณะสร้าง PDF:\n" . $e->getMessage();
  // also log to php error log
  error_log("export_rubber_pdf.php error: " . $e->getMessage());
  exit;
}
?>

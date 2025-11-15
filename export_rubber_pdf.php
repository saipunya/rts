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

// เปลี่ยนเป็นใช้ฟอนต์ THSarabunNew จาก assets/fonts (4 น้ำหนัก/สไตล์)
$fontDir = __DIR__ . '/assets/fonts';
$fontNormal     = $fontDir . '/THSarabunNew.ttf';
$fontBold       = $fontDir . '/THSarabunNew-Bold.ttf';
$fontItalic     = $fontDir . '/THSarabunNew-italic.ttf';
$fontBoldItalic = $fontDir . '/THSarabunNew-BoldItalic.ttf';
if (!file_exists($fontNormal) || !file_exists($fontBold) || !file_exists($fontItalic) || !file_exists($fontBoldItalic)) {
  header('Content-Type: text/html; charset=UTF-8');
  http_response_code(500);
  echo 'ไม่พบไฟล์ฟอนต์ THSarabunNew ครบทั้ง 4 แบบ ในโฟลเดอร์ assets/fonts';
  exit;
}
$normalData     = base64_encode(file_get_contents($fontNormal));
$boldData       = base64_encode(file_get_contents($fontBold));
$italicData     = base64_encode(file_get_contents($fontItalic));
$boldItalicData = base64_encode(file_get_contents($fontBoldItalic));
$fontCss = "\n@font-face {\n  font-family: 'THSarabunNew';\n  src: url('data:font/truetype;charset=utf-8;base64,{$normalData}') format('truetype');\n  font-weight: 400; font-style: normal;\n}\n@font-face {\n  font-family: 'THSarabunNew';\n  src: url('data:font/truetype;charset=utf-8;base64,{$boldData}') format('truetype');\n  font-weight: 700; font-style: normal;\n}\n@font-face {\n  font-family: 'THSarabunNew';\n  src: url('data:font/truetype;charset=utf-8;base64,{$italicData}') format('truetype');\n  font-weight: 400; font-style: italic;\n}\n@font-face {\n  font-family: 'THSarabunNew';\n  src: url('data:font/truetype;charset=utf-8;base64,{$boldItalicData}') format('truetype');\n  font-weight: 700; font-style: italic;\n}\nbody { font-family: 'THSarabunNew', DejaVu Sans, sans-serif; font-size: 14px; color: #111; line-height: 1.25; }\n";
$preferredFontName = 'THSarabunNew';

$card = '<div class="card">'
  . '<div class="header-card">'
      . '<div class="title-row">ใบสรุปการรับยาง (รายคน)</div>'
      . '<div class="meta">เลขที่รายการ: <span class="badge">'.(int)$row['ru_id'].'</span> • พิมพ์เมื่อ: '.e($printedAt).'</div>'
    . '</div>'
  . '<table class="columns" width="100%" cellspacing="0" cellpadding="0">'
  . '<tr>'
    // left column: member + totals
    . '<td style="width:50%; vertical-align:top; padding-right:10px;">'
      . '<table class="info-table kv">'
        . '<tr><td class="k">วันที่</td><td class="v">'.e($row['ru_date']).'</td></tr>'
        . '<tr><td class="k">เลขที่</td><td class="v">'.e($row['ru_number']).'</td></tr>'
        . '<tr><td class="k">ลาน</td><td class="v">'.e($row['ru_lan']).'</td></tr>'
        . '<tr><td class="k">กลุ่ม</td><td class="v"><span class="chip">'.e($row['ru_group']).'</span></td></tr>'
        . '<tr><td class="k">ชื่อ-สกุล</td><td class="v">'.e($row['ru_fullname']).'</td></tr>'
        . '<tr><td class="k">ชั้น</td><td class="v"><span class="chip">'.e($row['ru_class']).'</span></td></tr>'
      . '</table>'

      . '<div class="col-block box">'
        . '<table class="data-table">'
            . '<thead><tr><th colspan="2">สรุปยอด</th></tr></thead>'
            . '<tbody>'
              . '<tr><td>ปริมาณ (กก.)</td><td class="text-end">'.nf($qty).'</td></tr>'
              . '<tr><td>ราคา/กก. (อนุมาน)</td><td class="text-end">'.($unitPrice > 0 ? nf($unitPrice) : '-').'</td></tr>'
              . '<tr class="muted"><td>มูลค่า</td><td class="text-end">'.nf($value).' <span class="unit">บาท</span></td></tr>'
              . '<tr class="totals"><td>หักรวม</td><td class="text-end">'.nf($expend).' <span class="unit">บาท</span></td></tr>'
              . '<tr class="totals"><td>ยอดสุทธิ</td><td class="text-end">'.nf($netvalue).' <span class="unit">บาท</span></td></tr>'
            . '</tbody>'
          . '</table>'
      . '</div>'

     
    . '</td>'

    // right column: deductions
    . '<td style="width:50%; vertical-align:top; padding-left:10px;">'
      . '<div class="col-block box">'
        . '<table class="data-table">'
            . '<thead><tr><th colspan="2">รายละเอียดการหัก</th></tr></thead>'
            . '<tbody>'
              . '<tr><td>หุ้น</td><td class="text-end">'.nf($hoon).'</td></tr>'
              . '<tr><td>เงินกู้</td><td class="text-end">'.nf($loan).'</td></tr>'
              . '<tr><td>หนี้สั้น</td><td class="text-end">'.nf($short).'</td></tr>'
              . '<tr><td>เงินฝาก</td><td class="text-end">'.nf($deposit).'</td></tr>'
              . '<tr><td>กู้ซื้อขาย</td><td class="text-end">'.nf($trade).'</td></tr>'
              . '<tr><td>ประกันภัย</td><td class="text-end">'.nf($insure).'</td></tr>'
              . '<tr class="totals"><td>หักรวม</td><td class="text-end">'.nf($expend).' <span class="unit">บาท</span></td></tr>'
              . '<tr class="totals"><td>ยอดสุทธิ</td><td class="text-end">'.nf($netvalue).' <span class="unit">บาท</span></td></tr>'
            . '</tbody>'
          . '</table>'
      . '</div>'
      . '<div class="kpi">'
         . '<table style="width:100%">'
           . '<tr><td>ยอดสุทธิ</td><td class="text-end"><span class="kpi-value">'.nf($netvalue).'</span> <span class="unit">บาท</span></td></tr>'
         . '</table>'
       . '</div>'
    . '</td>'
  . '</tr>'
  . '</table>'

   
. '</div>'
. '<div>'
. '<table class="no-border signature-table" style="width:100%;">'
. '<tr>'
  . '<td style="width:60%">'
    . '<div class="sig-line"></div>'
    . '<div class="sig-caption">(ลงลายมือชื่อผู้บันทึก)</div>'
    . '<div class="sig-name">'.e($row['ru_saveby']).'</div>'
  . '</td>'
  . '<td class="text-end" style="width:40%">วันที่บันทึก:<br>'.e($row['ru_savedate']).'</td>'
. '</tr>'
. '</table>'
. '</div>';

$html = '<!doctype html><html lang="th"><head><meta charset="UTF-8"><style>
@page { margin: 12mm 10mm; }
' . $fontCss . '

body {
  font-size: 15px;
  line-height: 1.5;
  color: #222;
  background: #f8fafc;
}
.container {
  display: block;
  box-sizing: border-box;
  max-width: 1100px;
  margin: 0 auto;
  padding: 0;
}
.card {
  box-sizing: border-box;
  padding: 24px 24px 18px 24px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 2px 12px 0 rgba(0,0,0,0.08);
  margin-bottom: 18px;
  border: 1px solid #e5e7eb;
}
.columns {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 0;
  table-layout: fixed;
}
.columns td {
  vertical-align: top;
  padding-top: 8px;
}
.left { width: 50%; }
.right { width: 50%; }

h1, .title-row {
  font-size: 26px;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 4px;
  letter-spacing: 0.5px;
}
.meta {
  font-size: 13px;
  color: #64748b;
  margin-bottom: 12px;
}
.text-end {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.muted {
  color: #94a3b8;
}
.unit {
  color: #64748b;
  font-size: 13px;
}
.badge {
  display: inline-block;
  padding: 2px 10px;
  background: #e0e7ff;
  color: #3730a3;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.2px;
}
.chip {
  display: inline-block;
  padding: 2px 10px;
  background: #f1f5f9;
  color: #0f172a;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  font-size: 13px;
  font-weight: 500;
}
.header-card {
  padding-bottom: 10px;
  border-bottom: 2px solid #e0e7ff;
  margin-bottom: 16px;
}
.box {
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 14px 16px;
  margin-top: 12px;
  background: #f9fafb;
  box-shadow: 0 1px 4px 0 rgba(30,41,59,0.04);
}
.table, table {
  width: 100%;
  border-collapse: collapse;
}
.info-table td {
  padding: 6px 10px;
}
.info-table.kv .k {
  width: 32%;
  color: #334155;
  font-weight: 600;
}
.info-table.kv .v {
  width: 68%;
  color: #0f172a;
  font-weight: 500;
}
.info-table.kv td {
  border: 1px solid #e5e7eb;
}
.data-table {
  border: 1.5px solid #6366f1;
  border-radius: 10px;
  background: #fff;
  margin-bottom: 0;
}
.data-table thead th {
  padding: 10px 10px;
  background: #6366f1;
  color: #fff;
  font-weight: 700;
  text-align: left;
  border-bottom: 1.5px solid #6366f1;
  font-size: 15px;
  letter-spacing: 0.2px;
}
.data-table td, .data-table th {
  padding: 9px 10px;
  border: 1px solid #e5e7eb;
  background: #fff;
}
.data-table tr:nth-child(even) td {
  background: #f1f5f9;
}
.data-table .totals td {
  font-weight: 700;
  background: #e0e7ff;
  border-top: 1.5px solid #6366f1;
}
.kpi {
  margin-top: 14px;
  background: #f1f5f9;
  border: 1.5px dashed #6366f1;
  border-radius: 10px;
  padding: 12px 16px;
  page-break-inside: avoid;
  box-shadow: 0 1px 4px 0 rgba(99,102,241,0.06);
}
.kpi .kpi-value {
  font-size: 22px;
  font-weight: 800;
  color: #3730a3;
}
.signature-table {
  margin-top: 18px;
  page-break-inside: avoid;
}
.signature-table tr, .signature-table td {
  page-break-inside: avoid;
}
.sig-line {
  border-bottom: 1.5px dotted #6366f1;
  width: 88%;
  height: 18px;
  display: block;
}
.sig-caption {
  font-size: 12px;
  color: #64748b;
  margin-top: 4px;
}
.sig-name {
  font-size: 14px;
  margin-top: 6px;
  font-weight: 600;
}
* { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
</style></head><body>
  <div class="container">'
    . $card . '
  </div>
</body></html>';

// Normalize final HTML to NFC (helps combining marks ordering)
if (class_exists('Normalizer')) {
  $html = Normalizer::normalize($html, Normalizer::FORM_C);
}

$options = new Options();
$options->set('isRemoteEnabled', true);
// new: allow loading local assets (fonts) and set default Thai font when available
$options->setChroot(__DIR__);
if (!empty($preferredFontName)) {
  $options->set('defaultFont', $preferredFontName);
}

// Disable font subsetting which can cause Thai combining characters (สระ/วรรณยุกต์) to render incorrectly
// Set both common option names to cover dompdf versions
$options->set('isFontSubsettingEnabled', false);
$options->set('enable_font_subsetting', false);
// Enable the html5 parser for better layout/typography handling
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
try {
  $dompdf->loadHtml($html, 'UTF-8');
  // Set to A4 landscape (แนวนอน)
  $dompdf->setPaper('A4', 'landscape');
  $dompdf->render();

  // Add page numbers at bottom-right
  $canvas = $dompdf->getCanvas();
  $w = $canvas->get_width();
  $h = $canvas->get_height();
  $fontMetrics = $dompdf->getFontMetrics();
  $font = $fontMetrics->getFont($preferredFontName ?: 'THSarabunNew', 'normal');
  $canvas->page_text($w - 100, $h - 24, "หน้า {PAGE_NUM}/{PAGE_COUNT}", $font, 10, [0,0,0]);

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

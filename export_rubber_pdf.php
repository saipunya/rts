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

// new: force using Sarabun font from /fonts (require Sarabun-Regular.ttf and Sarabun-Bold.ttf)
$fontDir = __DIR__ . '/fonts';
$mainFontTtf = $fontDir . '/Sarabun-Regular.ttf';
$boldFontTtf = $fontDir . '/Sarabun-Bold.ttf';
if (!file_exists($mainFontTtf) || !file_exists($boldFontTtf)) {
  header('Content-Type: text/html; charset=UTF-8');
  http_response_code(500);
  echo 'ไม่พบไฟล์ฟอนต์ Sarabun ในโฟลเดอร์ fonts. โปรดตรวจสอบว่า Sarabun-Regular.ttf และ Sarabun-Bold.ttf อยู่ในโฟลเดอร์ fonts';
  exit;
}

// Embed fonts as base64 data URIs to avoid remote loading / chroot issues
$regularData = base64_encode(file_get_contents($mainFontTtf));
$boldData = base64_encode(file_get_contents($boldFontTtf));
$fontCss = "\n@font-face {\n  font-family: 'Sarabun';\n  src: url('data:font/truetype;charset=utf-8;base64,{$regularData}') format('truetype');\n  font-weight: normal; font-style: normal;\n}\n@font-face {\n  font-family: 'Sarabun';\n  src: url('data:font/truetype;charset=utf-8;base64,{$boldData}') format('truetype');\n  font-weight: bold; font-style: normal;\n}\nbody { font-family: 'Sarabun', DejaVu Sans, sans-serif; font-size: 14px; color: #111; line-height: 1.25; }\n";
$preferredFontName = 'Sarabun';

// build one receipt card with two side-by-side columns using inline td widths (dompdf-friendly)
$card = '<div class="card">'
  . '<div class="header-card">'
      . '<div class="title-row">ใบสรุปการรับยาง (รายคน)</div>'
      . '<div class="meta">เลขที่รายการ: '.(int)$row['ru_id'].' • พิมพ์เมื่อ: '.e($printedAt).'</div>'
    . '</div>'
  . '<table class="columns" width="100%" cellspacing="0" cellpadding="0">'
  . '<tr>'
    // left column: member + totals
    . '<td style="width:50%; vertical-align:top; padding-right:10px;">'
      . '<table class="info-table kv">'
        . '<tr><td class="k">วันที่</td><td class="v">'.e($row['ru_date']).'</td></tr>'
        . '<tr><td class="k">เลขที่</td><td class="v">'.e($row['ru_number']).'</td></tr>'
        . '<tr><td class="k">ลาน</td><td class="v">'.e($row['ru_lan']).'</td></tr>'
        . '<tr><td class="k">กลุ่ม</td><td class="v">'.e($row['ru_group']).'</td></tr>'
        . '<tr><td class="k">ชื่อ-สกุล</td><td class="v">'.e($row['ru_fullname']).'</td></tr>'
        . '<tr><td class="k">ชั้น</td><td class="v">'.e($row['ru_class']).'</td></tr>'
      . '</table>'

      . '<div class="col-block box">'
        . '<table class="data-table">'
          . '<tr><th colspan="2">สรุปยอด</th></tr>'
          . '<tr><td>ปริมาณ (กก.)</td><td class="text-end">'.nf($qty).'</td></tr>'
          . '<tr><td>ราคา/กก. (อนุมาน)</td><td class="text-end">'.($unitPrice > 0 ? nf($unitPrice) : '-').'</td></tr>'
          . '<tr class="muted"><td>มูลค่า</td><td class="text-end">'.nf($value).' <span class="unit">บาท</span></td></tr>'
          . '<tr class="totals"><td>หักรวม</td><td class="text-end">'.nf($expend).' <span class="unit">บาท</span></td></tr>'
          . '<tr class="totals"><td>ยอดสุทธิ</td><td class="text-end">'.nf($netvalue).' <span class="unit">บาท</span></td></tr>'
        . '</table>'
      . '</div>'

     
    . '</td>'

    // right column: deductions
    . '<td style="width:50%; vertical-align:top; padding-left:10px;">'
      . '<div class="col-block box">'
        . '<table class="data-table">'
          . '<tr><th colspan="2">รายละเอียดการหัก</th></tr>'
          . '<tr><td>หุ้น</td><td class="text-end">'.nf($hoon).'</td></tr>'
          . '<tr><td>เงินกู้</td><td class="text-end">'.nf($loan).'</td></tr>'
          . '<tr><td>หนี้สั้น</td><td class="text-end">'.nf($short).'</td></tr>'
          . '<tr><td>เงินฝาก</td><td class="text-end">'.nf($deposit).'</td></tr>'
          . '<tr><td>กู้ซื้อขาย</td><td class="text-end">'.nf($trade).'</td></tr>'
          . '<tr><td>ประกันภัย</td><td class="text-end">'.nf($insure).'</td></tr>'
          . '<tr class="totals"><td>หักรวม</td><td class="text-end">'.nf($expend).' <span class="unit">บาท</span></td></tr>'
          . '<tr class="totals"><td>ยอดสุทธิ</td><td class="text-end">'.nf($netvalue).' <span class="unit">บาท</span></td></tr>'
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
@page { margin: 8mm 6mm; }
' . $fontCss . '

/* layout */
.container { display: block; box-sizing: border-box; }
.card { box-sizing: border-box; padding: 8px; }
.columns { width: 100%; border-collapse: collapse; table-layout: fixed; }
.columns td { vertical-align: top; padding-top: 4px; }
.left { width: 50%; }
.right { width: 50%; }

/* base */
body { font-size: 13px; line-height: 1.25; color: #111; }
h1 { font-size: 18px; margin: 0 0 6px; }
.meta { font-size: 12px; color: #555; margin-bottom: 8px; }
.text-end { text-align: right; }
/* align numbers neatly */
.text-end { font-variant-numeric: tabular-nums; }
.muted { color: #666; }
.full-width { display: block; width: 100%; }
.unit { color: #666; font-size: 12px; }

/* header */
.header-card { padding-bottom: 6px; border-bottom: 1px solid #e5e7eb; margin-bottom: 8px; }
.title-row { font-size: 20px; font-weight: 700; color: #111; margin-bottom: 2px; }

/* card/box */
.box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; margin-top: 8px; background: #fafafa; }

/* tables */
.table, table { width: 100%; border-collapse: collapse; }
.info-table td { padding: 4px 6px; }
.info-table.kv .k { width: 30%; color: #444; }
.info-table.kv .v { width: 70%; color: #111; }
.data-table th, .data-table td { padding: 7px 8px; border-bottom: 1px solid #ececec; }
.data-table th { background: #f3f4f6; font-weight: 600; text-align: left; color: #222; }
.data-table { page-break-inside: avoid; }
.data-table tr { page-break-inside: avoid; }
.data-table tr:last-child td { border-bottom: 0; }
.data-table .totals td { font-weight: 700; background: #f9fafb; border-top: 1px solid #ddd; }

/* KPI */
.kpi { margin-top: 8px; background: #fefce8; border: 1px solid #fde68a; border-radius: 6px; padding: 8px 10px; page-break-inside: avoid; }
.kpi .kpi-value { font-size: 18px; font-weight: 700; }

/* signature */
.signature-table { margin-top: 14px; page-break-inside: avoid; }
.signature-table tr, .signature-table td { page-break-inside: avoid; }
.sig-line { border-bottom: 1px dotted #000; width: 88%; height: 18px; display: block; }
.sig-caption { font-size: 11px; color: #666; margin-top: 4px; }
.sig-name { font-size: 12px; margin-top: 6px; }
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
  $font = $fontMetrics->getFont($preferredFontName ?: 'Sarabun', 'normal');
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

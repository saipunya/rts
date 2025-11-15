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

// new: prepare Thai font embedding (prefer Kanit if present, fallback to Sarabun)
$assetFontDirs = [__DIR__ . '/assets/fonts', __DIR__ . '/fonts'];
$kanitRegular = null;
$kanitBold = null;
foreach ($assetFontDirs as $d) {
  if (file_exists($d . '/Kanit-Regular.ttf') && file_exists($d . '/Kanit-Bold.ttf')) {
    $kanitRegular = $d . '/Kanit-Regular.ttf';
    $kanitBold = $d . '/Kanit-Bold.ttf';
    $kanitWebPath = str_replace(__DIR__ . '/', '', $d) . '/';
    break;
  }
}

$fontDir     = __DIR__ . '/assets/fonts';
// prefer Sarabun fonts that exist in the repository
$mainFontTtf = $fontDir . '/Sarabun-Regular.ttf';
$boldFontTtf = $fontDir . '/Sarabun-Bold.ttf';
$hasSarabun = file_exists($mainFontTtf) && file_exists($boldFontTtf);
$hasKanit = $kanitRegular !== null && $kanitBold !== null;

if ($hasKanit) {
  // use Kanit from the detected folder
  $fontCss = "
  @font-face {
    font-family: 'Kanit';
    src: url('" . $kanitWebPath . "Kanit-Regular.ttf') format('truetype');
    font-weight: normal; font-style: normal;
  }
  @font-face {
    font-family: 'Kanit';
    src: url('" . $kanitWebPath . "Kanit-Bold.ttf') format('truetype');
    font-weight: bold; font-style: normal;
  }
  body { font-family: 'Kanit', 'Sarabun', DejaVu Sans, sans-serif; font-size: 14px; color: #111; line-height: 1.25; }
  ";
  $preferredFontName = 'Kanit';
} elseif ($hasSarabun) {
  $fontCss = "
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
  body { font-family: 'Sarabun', DejaVu Sans, sans-serif; font-size: 14px; color: #111; line-height: 1.25; }
  ";
  $preferredFontName = 'Sarabun';
} else {
  $fontCss = "body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; line-height: 1.25; }";
  $preferredFontName = 'DejaVu Sans';
}

// build one receipt card with two side-by-side columns using inline td widths (dompdf-friendly)
$card = '<div class="card">'
  . '<h1 class="full-width">ใบสรุปการรับยาง (รายคน)</h1>'
  . '<div class="meta full-width">เลขที่รายการ: '.(int)$row['ru_id'].' | พิมพ์เมื่อ: '.e($printedAt).'</div>'
  . '<table class="columns" width="100%" cellspacing="0" cellpadding="0">'
  . '<tr>'
    // left column: member + totals
    . '<td style="width:50%; vertical-align:top; padding-right:10px;">'
      . '<table style="width:100%;">'
        . '<tr><td style="width:25%">วันที่</td><td style="width:25%">'.e($row['ru_date']).'</td><td style="width:25%">ลาน</td><td style="width:25%">'.e($row['ru_lan']).'</td></tr>'
        . '<tr><td>กลุ่ม</td><td>'.e($row['ru_group']).'</td><td>เลขที่</td><td>'.e($row['ru_number']).'</td></tr>'
        . '<tr><td>ชื่อ-สกุล</td><td>'.e($row['ru_fullname']).'</td><td>ชั้น</td><td>'.e($row['ru_class']).'</td></tr>'
      . '</table>'

      . '<div class="col-block box">'
        . '<table class="no-border" style="width:100%;">'
          . '<tr><td>ปริมาณ (กก.)</td><td class="text-end">'.nf($qty).'</td></tr>'
          . '<tr><td>ราคา/กก. (อนุมาน)</td><td class="text-end">'.($unitPrice > 0 ? nf($unitPrice) : '-').'</td></tr>'
          . '<tr><td class="muted">มูลค่า</td><td class="text-end">'.nf($value).'</td></tr>'
          . '<tr class="totals"><td>หักรวม</td><td class="text-end">'.nf($expend).'</td></tr>'
          . '<tr class="totals"><td>ยอดสุทธิ</td><td class="text-end">'.nf($netvalue).'</td></tr>'
        . '</table>'
      . '</div>'

     
    . '</td>'

    // right column: deductions
    . '<td style="width:50%; vertical-align:top; padding-left:10px;">'
      . '<div class="col-block box">'
        . '<table style="width:100%;">'
          . '<tr><th colspan="2">รายละเอียดการหัก</th></tr>'
          . '<tr><td>หุ้น</td><td class="text-end">'.nf($hoon).'</td></tr>'
          . '<tr><td>เงินกู้</td><td class="text-end">'.nf($loan).'</td></tr>'
          . '<tr><td>หนี้สั้น</td><td class="text-end">'.nf($short).'</td></tr>'
          . '<tr><td>เงินฝาก</td><td class="text-end">'.nf($deposit).'</td></tr>'
          . '<tr><td>กู้ซื้อขาย</td><td class="text-end">'.nf($trade).'</td></tr>'
          . '<tr><td>ประกันภัย</td><td class="text-end">'.nf($insure).'</td></tr>'
          . '<tr class="totals"><td>หักรวม</td><td class="text-end">'.nf($expend).'</td></tr>'
          . '<tr class="totals"><td>ยอดสุทธิ</td><td class="text-end">'.nf($netvalue).'</td></tr>'
        . '</table>'
      . '</div>'
    . '</td>'
  . '</tr>'
  . '</table>'

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

/* single page with two side-by-side columns using table layout */
.container { display: block; box-sizing: border-box; }
.card { box-sizing: border-box; padding: 6px; }
.columns { width: 100%; border-collapse: collapse; table-layout: fixed; }
.columns td { vertical-align: top; }
.left { width: 50%; }
.right { width: 50%; }

body { font-size: 13px; line-height: 1.14; color: #111; }
h1 { font-size: 18px; margin: 0 0 6px; }
.meta { font-size: 12px; color: #555; margin-bottom: 8px; }
table { width: 100%; border-collapse: collapse; margin: 0 0 6px; }
th, td { padding: 6px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
.no-border td { border: 0; padding: 2px 0; }
.text-end { text-align: right; }
.muted { color: #666; }
.box { border: 1px solid #ddd; border-radius: 4px; padding: 6px 8px; margin-top: 6px; }
.totals td { font-weight: bold; }
.signature-table td { vertical-align: bottom; padding-top: 8px; }
.sig-line { border-bottom: 1px dotted #000; width: 88%; height: 18px; display: block; }
.sig-caption { font-size: 11px; color: #666; margin-top: 4px; }
.sig-name { font-size: 12px; margin-top: 6px; }
.full-width { display: block; width: 100%; }
</style></head><body>
  <div class="container">'
    . $card . '
  </div>
</body></html>';

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
$dompdf->loadHtml($html, 'UTF-8');
// Set to A4 landscape (แนวนอน)
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'rubber_' . (int)$row['ru_id'] . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;

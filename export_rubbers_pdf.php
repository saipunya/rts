<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// optional debug view: use ?debug=1 to print HTML instead of PDF
$debug = isset($_GET['debug']) && $_GET['debug'] !== '0';
if ($debug) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
// add: raise limits to reduce render-time 500 errors
@ini_set('memory_limit', '256M');
@set_time_limit(60);
mb_internal_encoding('UTF-8');

// readable error response
function fail($msg) {
  http_response_code(500);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><meta charset="utf-8"><title>Export PDF Error</title><div style="font-family:Arial,sans-serif;padding:16px">';
  echo '<h3>ไม่สามารถส่งออกเป็น PDF</h3><p style="color:#b00020">'.$msg.'</p>';
  echo '<ol><li>รัน: <code>composer require dompdf/dompdf</code> ในโฟลเดอร์ /C:/xampp/htdocs/rts</li>';
  echo '<li>ตรวจสอบไฟล์ <code>/C:/xampp/htdocs/rts/vendor/autoload.php</code> มีอยู่</li>';
  echo '<li>เปิดใช้งาน PHP extensions <code>mbstring</code> และ <code>gd</code> แล้วรีสตาร์ท Apache</li></ol></div>';
  exit;
}

// find Composer autoload
$autoload = null;
foreach ([__DIR__.'/vendor/autoload.php', dirname(__DIR__).'/vendor/autoload.php'] as $cand) {
  if (is_file($cand)) { $autoload = $cand; break; }
}
if (!$autoload) fail('ไม่พบไฟล์ vendor/autoload.php');

require_once $autoload;
if (!class_exists('Dompdf\\Dompdf')) fail('ไม่พบคลาส Dompdf โปรดติดตั้งแพ็คเกจ dompdf/dompdf');

use Dompdf\Dompdf;
use Dompdf\Options;

$db = db();

// read filters (same as rubbers.php)
$lanParam  = $_GET['lan'] ?? 'all';
$search    = trim((string)($_GET['search'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to   = trim((string)($_GET['date_to'] ?? ''));

$currentLan = ($lanParam === 'all') ? 'all' : (int)$lanParam;
if ($currentLan !== 'all' && !in_array($currentLan, [1,2,3,4], true)) $currentLan = 1;

// build query with optional filters
$rows  = [];
$conds = [];
$binds = [];
$types = '';

if ($currentLan !== 'all') { $conds[] = 'ru_lan = ?'; $types .= 's'; $binds[] = (string)$currentLan; }
if ($search !== '') {
  $like = '%' . $search . '%';
  $conds[] = '(ru_group LIKE ? OR ru_number LIKE ? OR ru_fullname LIKE ? OR ru_class LIKE ?)';
  $types .= 'ssss'; array_push($binds, $like, $like, $like, $like);
}
if ($date_from !== '') {
  $df = DateTime::createFromFormat('Y-m-d', $date_from);
  if ($df && $df->format('Y-m-d') === $date_from) { $conds[] = 'ru_date >= ?'; $types .= 's'; $binds[] = $date_from; }
}
if ($date_to !== '') {
  $dt = DateTime::createFromFormat('Y-m-d', $date_to);
  if ($dt && $dt->format('Y-m-d') === $date_to) { $conds[] = 'ru_date <= ?'; $types .= 's'; $binds[] = $date_to; }
}

$sql = "SELECT * FROM tbl_rubber";
if ($conds) $sql .= ' WHERE ' . implode(' AND ', $conds);
$sql .= ' ORDER BY ru_date DESC, ru_id DESC';

$st = null; $res = null;
if ($conds) {
  $st = $db->prepare($sql);
  if (!$st) fail('เตรียมคำสั่ง SQL ล้มเหลว: '.$db->error);
  $st->bind_param($types, ...$binds);
  $st->execute();
  $res = $st->get_result();
} else {
  $res = $db->query($sql);
}
if (!$res) fail('ดึงข้อมูลล้มเหลว: '.$db->error);

while ($r = $res->fetch_assoc()) $rows[] = $r;
$res->free();
if ($st) $st->close();

// aggregate totals
$sumQty = 0.0; $sumValue = 0.0; $sumExpend = 0.0; $sumNet = 0.0;
foreach ($rows as $ag) {
  $sumQty    += (float)$ag['ru_quantity'];
  $sumValue  += isset($ag['ru_value'])    ? (float)$ag['ru_value']    : 0.0;
  $sumExpend += isset($ag['ru_expend'])   ? (float)$ag['ru_expend']   : 0.0;
  $sumNet    += isset($ag['ru_netvalue']) ? (float)$ag['ru_netvalue'] : 0.0;
}

// สร้างข้อมูลสรุปแยกตามลาน
$lanStats = [];
foreach ($rows as $r) {
  $lan = $r['ru_lan'];
  if (!isset($lanStats[$lan])) {
    $lanStats[$lan] = [
      'count' => 0,
      'qty' => 0.0,
      'value' => 0.0,
      'expend' => 0.0,
      'net' => 0.0
    ];
  }
  $lanStats[$lan]['count']++;
  $lanStats[$lan]['qty']    += (float)($r['ru_quantity'] ?? 0);
  $lanStats[$lan]['value']  += (float)($r['ru_value'] ?? 0);
  $lanStats[$lan]['expend'] += (float)($r['ru_expend'] ?? 0);
  $lanStats[$lan]['net']    += (float)($r['ru_netvalue'] ?? 0);
}

function fmt2($n){ return number_format((float)$n, 2); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$title    = 'รายงานข้อมูลยางพารา';
$subtitle = ($currentLan === 'all') ? 'ทุกลาน' : ('ลาน ' . $currentLan);
$condTxt  = [];
if ($search !== '')    $condTxt[] = 'คำค้น "' . h($search) . '"';
if ($date_from !== '') $condTxt[] = 'จาก ' . h($date_from);
if ($date_to   !== '') $condTxt[] = 'ถึง '  . h($date_to);
$condStr  = $condTxt ? ('เงื่อนไข: ' . implode(' | ', $condTxt)) : '';

// replace previous hard-coded font detection with automatic scan of assets/fonts
$fontDir = __DIR__ . '/assets/fonts';

// helper: map common weight tokens to numeric weight (compatible with PHP 7.x)
function mapWeight(string $name): int {
  $n = strtolower($name);
  if (strpos($n, 'thin') !== false) return 100;
  if (strpos($n, 'extralight') !== false || strpos($n, 'ultralight') !== false) return 200;
  if (strpos($n, 'light') !== false) return 300;
  if (strpos($n, 'regular') !== false || strpos($n, 'normal') !== false || strpos($n, 'book') !== false) return 400;
  if (strpos($n, 'medium') !== false) return 500;
  if (strpos($n, 'semibold') !== false || strpos($n, 'demibold') !== false) return 600;
  if (strpos($n, 'bold') !== false) return 700;
  if (strpos($n, 'extrabold') !== false || strpos($n, 'ultrabold') !== false) return 800;
  if (strpos($n, 'black') !== false || strpos($n, 'heavy') !== false) return 900;
  return 400;
}

// helper: detect italic (compatible with PHP 7.x)
function isItalic(string $name): bool {
  $n = strtolower($name);
  return (strpos($n, 'italic') !== false || strpos($n, 'oblique') !== false);
}

// helper: derive family name from filename (strip common weight/style tokens)
function familyFromFilename(string $basename): string {
  $name = preg_replace('/\.(ttf|otf|tff)$/i', '', $basename);
  $tokens = ['-Thin','-ExtraLight','-UltraLight','-Light','-Regular','-Book','-Normal','-Medium','-SemiBold','-DemiBold','-Bold','-ExtraBold','-UltraBold','-Black','-Heavy','-Italic','-Oblique',
             ' Thin',' ExtraLight',' UltraLight',' Light',' Regular',' Book',' Normal',' Medium',' SemiBold',' DemiBold',' Bold',' ExtraBold',' UltraBold',' Black',' Heavy',' Italic',' Oblique'];
  $name = str_replace($tokens, '', $name);
  return trim($name);
}

// scan fonts directory (robust glob handling)
$fontsByFamily = [];
$fontCss = '';
if (is_dir($fontDir)) {
  $ttf = glob($fontDir.'/*.ttf') ?: [];
  $otf = glob($fontDir.'/*.otf') ?: [];
  $tff = glob($fontDir.'/*.tff') ?: []; // common typo extension
  $files = array_merge($ttf, $otf, $tff);

  foreach ($files as $file) {
    $base = basename($file);
    $family = familyFromFilename($base);
    if ($family === '') continue;
    $weight = mapWeight($base);
    $style  = isItalic($base) ? 'italic' : 'normal';
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

// pick default Thai family (prefer THSarabunNew, Sarabun, NotoSansThai)
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

// build CSS and HTML (inject $fontCss and selected family)
$style = '
  @page { margin: 20px 24px; }
  '.$fontCss.'
  body { font-family: '.($hasThaiFonts ? '"'.$defaultFamily.'", ' : '').'DejaVu Sans, sans-serif; font-size: 16px; color: #111; line-height: 1.45; }
  table, th, td { font-family: '.($hasThaiFonts ? '"'.$defaultFamily.'", ' : '').'DejaVu Sans, sans-serif; font-size: 15px; }
  h1 { font-size: 24px; margin: 0 0 4px; '.($hasThaiFonts ? 'font-weight:700;' : '').' }
  .muted { color: #666; font-size: 13px; }
  .summary { margin: 8px 0 12px; display: flex; gap: 10px; flex-wrap: wrap; }
  .badge { border: 1px solid #ddd; border-radius: 6px; padding: 4px 8px; font-size: 15px; }
  table { width: 100%; border-collapse: collapse; }
  thead th { background: #f2f4f7; text-align: left; border-bottom: 1px solid #ccc; padding: 8px 6px; font-size: 15px; }
  tbody td { border-bottom: 1px solid #eee; padding: 7px; font-size: 15px; }
  td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
  .footer { margin-top: 8px; font-size: 13px; color:#555; }
';

$html = '
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<style>'.$style.'</style>
</head>
<body>
  '.(!$hasThaiFonts ? '<div class="muted">หมายเหตุ: ไม่พบฟอนต์ไทยใน assets/fonts จะใช้ DejaVu Sans</div>' : '').'
  <h1>'.h($title).'</h1>
  <div class="muted">'.h($subtitle).'</div>
  '.($condStr ? '<div class="muted">'.$condStr.'</div>' : '').'
  <div class="summary">
    <div class="badge">จำนวนรายการ: '.count($rows).'</div>
    <div class="badge">ปริมาณรวม: '.fmt2($sumQty).' กก.</div>
    <div class="badge">มูลค่า: '.fmt2($sumValue).' ฿</div>
    <div class="badge">หักรวม: '.fmt2($sumExpend).' ฿</div>
    <div class="badge">สุทธิ: '.fmt2($sumNet).' ฿</div>
  </div>
  <div class="summary" style="flex-wrap:wrap;gap:18px 24px;">';
foreach ([1,2,3,4] as $lan) {
  $s = $lanStats[$lan] ?? ['count'=>0,'qty'=>0,'value'=>0,'expend'=>0,'net'=>0];
  $html .= '<div style="background:#f8fafc;border-radius:10px;padding:12px 18px;min-width:220px;box-shadow:0 1px 3px #0001;display:inline-block;">
    <div style="font-size:17px;font-weight:bold;margin-bottom:4px;">ลาน '.h($lan).'</div>
    <div>จำนวนรายการ: <b>'.h($s['count']).'</b></div>
    <div>ปริมาณรวม: <b>'.fmt2($s['qty']).'</b> กก.</div>
    <div>มูลค่า: <b>'.fmt2($s['value']).'</b> ฿</div>
    <div>หักรวม: <b>'.fmt2($s['expend']).'</b> ฿</div>
    <div>สุทธิ: <b>'.fmt2($s['net']).'</b> ฿</div>
  </div>';
}
$html .= '</div>
  <table>
    <thead>
      <tr>
        <th>วันที่</th>
        <th>ลาน</th>
        <th>กลุ่ม</th>
        <th>เลขที่</th>
        <th>ชื่อ-สกุล</th>
        <th class="num">ปริมาณ</th>
        <th class="num">ยอดรับ</th>
        <th class="num">ยอดจ่าย</th>
        <th class="num">คงเหลือ</th>
      </tr>
    </thead>
    <tbody>';
if (!$rows) {
  $html .= '<tr><td colspan="9" class="muted">ไม่มีข้อมูล</td></tr>';
} else {
  foreach ($rows as $r) {
    // Calculate values for each row
    $quantity = (float)($r['ru_quantity'] ?? 0);
    $income = (float)($r['ru_value'] ?? 0); // ยอดรับ
    $expense = (float)($r['ru_expend'] ?? 0); // ยอดจ่าย
    $balance = (float)($r['ru_netvalue'] ?? 0); // คงเหลือ
    $html .= '
      <tr>
        <td>'.h(format_thai_date($r['ru_date'])).'</td>
        <td>'.h($r['ru_lan']).'</td>
        <td>'.h($r['ru_group']).'</td>
        <td>'.h($r['ru_number']).'</td>
        <td>'.h($r['ru_fullname']).'</td>
        <td class="num">'.fmt2($quantity).'</td>
        <td class="num">'.fmt2($income).'</td>
        <td class="num">'.fmt2($expense).'</td>
        <td class="num">'.fmt2($balance).'</td>
      </tr>';
  }
  // Add summary row
  $html .= '\n      <tr style="font-weight:bold;background:#f9fafb">'
    .'<td colspan="5" style="text-align:right">รวมทั้งสิ้น</td>'
    .'<td class="num">'.fmt2($sumQty).'</td>'
    .'<td class="num">'.fmt2($sumValue).'</td>'
    .'<td class="num">'.fmt2($sumExpend).'</td>'
    .'<td class="num">'.fmt2($sumNet).'</td>'
    .'</tr>';
}
$html .= '
    </tbody>
  </table>
  <div class="footer">พิมพ์เมื่อ: '.date('Y-m-d H:i').'</div>
</body>
</html>';

// debug: show HTML to verify page renders
if ($debug) {
  header('Content-Type: text/html; charset=utf-8');
  echo $html;
  exit;
}

// dompdf options (ensure fonts load from local chroot)
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('chroot', __DIR__);
// set defaultFont to selected Thai family if available
$options->set('defaultFont', $hasThaiFonts ? $defaultFamily : 'DejaVu Sans');

// keep font cache for Windows
$fontCacheDir = __DIR__ . '/storage/font_cache';
if (!is_dir($fontCacheDir)) { @mkdir($fontCacheDir, 0777, true); }
$options->set('fontCache', $fontCacheDir);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
// pick landscape if many columns
$dompdf->setPaper('A4', 'landscape');

try {
  $dompdf->render();
} catch (Throwable $e) {
  fail('DomPDF render ล้มเหลว: ' . $e->getMessage());
}

// ensure no prior output before streaming
if (ob_get_length()) { @ob_end_clean(); }

$filename = 'rubbers_' . ($currentLan === 'all' ? 'all' : 'lan'.$currentLan) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;

function format_thai_date($date) {
  $months = [
    '', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
    'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
  ];
  $parts = explode('-', $date);
  if (count($parts) !== 3) return $date;
  $y = (int)$parts[0] + 543;
  $m = (int)$parts[1];
  $d = (int)$parts[2];
  return $d.' '.$months[$m].' '.$y;
}

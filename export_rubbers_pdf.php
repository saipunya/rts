<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// optional debug view: use ?debug=1 to print HTML instead of PDF
$debug = isset($_GET['debug']) && $_GET['debug'] !== '0';
if ($debug) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
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

function fmt2($n){ return number_format((float)$n, 2); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$title    = 'รายงานข้อมูลยางพารา';
$subtitle = ($currentLan === 'all') ? 'ทุกลาน' : ('ลาน ' . $currentLan);
$condTxt  = [];
if ($search !== '')    $condTxt[] = 'คำค้น "' . h($search) . '"';
if ($date_from !== '') $condTxt[] = 'จาก ' . h($date_from);
if ($date_to   !== '') $condTxt[] = 'ถึง '  . h($date_to);
$condStr  = $condTxt ? ('เงื่อนไข: ' . implode(' | ', $condTxt)) : '';

// replace previous hard-coded font detection with automatic scan of /fonts
$fontDir = __DIR__ . '/fonts';

// helper: map common weight tokens to numeric weight
function mapWeight(string $name): int {
  $n = strtolower($name);
  if (str_contains($n, 'thin')) return 100;
  if (str_contains($n, 'extralight') || str_contains($n, 'ultralight')) return 200;
  if (str_contains($n, 'light')) return 300;
  if (str_contains($n, 'regular') || str_contains($n, 'normal') || str_contains($n, 'book')) return 400;
  if (str_contains($n, 'medium')) return 500;
  if (str_contains($n, 'semibold') || str_contains($n, 'demibold')) return 600;
  if (str_contains($n, 'bold')) return 700;
  if (str_contains($n, 'extrabold') || str_contains($n, 'ultrabold')) return 800;
  if (str_contains($n, 'black') || str_contains($n, 'heavy')) return 900;
  return 400;
}

// helper: detect italic
function isItalic(string $name): bool {
  $n = strtolower($name);
  return (str_contains($n, 'italic') || str_contains($n, 'oblique'));
}

// helper: derive family name from filename (strip common weight/style tokens)
function familyFromFilename(string $basename): string {
  $name = preg_replace('/\.(ttf|otf|tff)$/i', '', $basename);
  $tokens = ['-Thin','-ExtraLight','-UltraLight','-Light','-Regular','-Book','-Normal','-Medium','-SemiBold','-DemiBold','-Bold','-ExtraBold','-UltraBold','-Black','-Heavy','-Italic','-Oblique',
             ' Thin',' ExtraLight',' UltraLight',' Light',' Regular',' Book',' Normal',' Medium',' SemiBold',' DemiBold',' Bold',' ExtraBold',' UltraBold',' Black',' Heavy',' Italic',' Oblique'];
  $name = str_replace($tokens, '', $name);
  return trim($name);
}

// scan fonts directory
$fontsByFamily = [];
$fontCss = '';
if (is_dir($fontDir)) {
  $files = array_merge(
    glob($fontDir.'/*.ttf'),
    glob($fontDir.'/*.otf'),
    glob($fontDir.'/*.tff') // common typo extension
  );
  foreach ($files as $file) {
    $base = basename($file);
    $family = familyFromFilename($base);
    if ($family === '') continue;
    $weight = mapWeight($base);
    $style  = isItalic($base) ? 'italic' : 'normal';
    $fontsByFamily[$family][] = ['file' => $base, 'weight' => $weight, 'style' => $style];
  }
  // build @font-face css
  foreach ($fontsByFamily as $family => $items) {
    foreach ($items as $it) {
      $ext = strtolower(pathinfo($it['file'], PATHINFO_EXTENSION));
      $fmt = ($ext === 'otf') ? 'opentype' : 'truetype';
      $fontCss .= '@font-face{font-family:"'.$family.'";font-style:'.$it['style'].';font-weight:'.$it['weight'].';src:url("fonts/'.$it['file'].'") format("'.$fmt.'");}'."\n";
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
  body { font-family: '.($hasThaiFonts ? '"'.$defaultFamily.'", ' : '').'DejaVu Sans, sans-serif; font-size: 12px; color: #111; line-height: 1.45; }
  table, th, td { font-family: '.($hasThaiFonts ? '"'.$defaultFamily.'", ' : '').'DejaVu Sans, sans-serif; }
  h1 { font-size: 18px; margin: 0 0 4px; '.($hasThaiFonts ? 'font-weight:700;' : '').' }
  .muted { color: #666; font-size: 11px; }
  .summary { margin: 8px 0 12px; display: flex; gap: 10px; flex-wrap: wrap; }
  .badge { border: 1px solid #ddd; border-radius: 6px; padding: 4px 8px; }
  table { width: 100%; border-collapse: collapse; }
  thead th { background: #f2f4f7; text-align: left; border-bottom: 1px solid #ccc; padding: 6px 5px; }
  tbody td { border-bottom: 1px solid #eee; padding: 5px; }
  td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
  .footer { margin-top: 8px; font-size: 11px; color:#555; }
';

$html = '
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<style>'.$style.'</style>
</head>
<body>
  '.(!$hasThaiFonts ? '<div class="muted">หมายเหตุ: ไม่พบฟอนต์ไทยในโฟลเดอร์ /fonts จะใช้ DejaVu Sans</div>' : '').'
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
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>วันที่</th>
        <th>ลาน</th>
        <th>กลุ่ม</th>
        <th>เลขที่</th>
        <th>ชื่อ-สกุล</th>
        <th>ชั้น</th>
        <th class="num">ปริมาณ</th>
        <th class="num">หุ้น</th>
        <th class="num">เงินกู้</th>
        <th class="num">หนี้สั้น</th>
        <th class="num">เงินฝาก</th>
        <th class="num">กู้ซื้อขาย</th>
        <th class="num">ประกันภัย</th>
        <th class="num">มูลค่า</th>
        <th class="num">หักรวม</th>
        <th class="num">สุทธิ</th>
      </tr>
    </thead>
    <tbody>';
if (!$rows) {
  $html .= '<tr><td colspan="17" class="muted">ไม่มีข้อมูล</td></tr>';
} else {
  foreach ($rows as $r) {
    $html .= '
      <tr>
        <td>'.(int)$r['ru_id'].'</td>
        <td>'.h($r['ru_date']).'</td>
        <td>'.h($r['ru_lan']).'</td>
        <td>'.h($r['ru_group']).'</td>
        <td>'.h($r['ru_number']).'</td>
        <td>'.h($r['ru_fullname']).'</td>
        <td>'.h($r['ru_class']).'</td>
        <td class="num">'.fmt2($r['ru_quantity']).'</td>
        <td class="num">'.fmt2($r['ru_hoon']).'</td>
        <td class="num">'.fmt2($r['ru_loan']).'</td>
        <td class="num">'.fmt2($r['ru_shortdebt']).'</td>
        <td class="num">'.fmt2($r['ru_deposit']).'</td>
        <td class="num">'.fmt2($r['ru_tradeloan']).'</td>
        <td class="num">'.fmt2($r['ru_insurance']).'</td>
        <td class="num">'.fmt2($r['ru_value']    ?? 0).'</td>
        <td class="num">'.fmt2($r['ru_expend']   ?? 0).'</td>
        <td class="num">'.fmt2($r['ru_netvalue'] ?? 0).'</td>
      </tr>';
  }
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

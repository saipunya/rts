<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// optional debug view: use ?debug=1 to print HTML instead of PDF
$debug = isset($_GET['debug']) && $_GET['debug'] !== '0';
if ($debug) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
// add: raise limits to reduce render-time 500 errors
@ini_set('memory_limit', '512M');
@set_time_limit(120);
mb_internal_encoding('UTF-8');

// readable error response
function fail($msg) {
  http_response_code(500);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><meta charset="utf-8"><title>Export PDF Error</title><div style="font-family:Arial,sans-serif;padding:16px">';
  echo '<h3>ไม่สามารถส่งออกเป็น PDF</h3><p style="color:#b00020">'.$msg.'</p>';
  echo '<ol><li>รัน: <code>composer require dompdf/dompdf</code> ในโฟลเดอร์โปรเจค</li>';
  echo '<li>ตรวจสอบไฟล์ <code>vendor/autoload.php</code> มีอยู่</li>';
  echo '<li>เปิดใช้งาน PHP extensions <code>mbstring</code> และ <code>gd</code> แล้วรีสตาร์ท web server</li></ol></div>';
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
// จำกัด 100 รายการเพื่อลด memory usage
$sql .= ' LIMIT 100';

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

// ใช้ฟอนต์ดีฟอลต์ THSarabunNew โดยไม่สแกนไฟล์ฟอนต์เอง (ลดการใช้หน่วยความจำอย่างมาก)
$defaultFamily = 'THSarabunNew';
$hasThaiFonts = true;

// CSS แบบเรียบง่ายเพื่อลดภาระ dompdf
$style = '
  @page { margin: 15px; }
  body { font-family: "'.$defaultFamily.'", DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
  table { width: 100%; border-collapse: collapse; font-size: 10px; }
  th { background: #f2f4f7; border: 1px solid #ccc; padding: 3px; text-align: left; }
  td { border: 1px solid #eee; padding: 2px; }
  td.num, th.num { text-align: right; }
  .summary { margin: 4px 0 6px; }
  .muted { color: #666; font-size: 9px; }
  .footer { margin-top: 5px; font-size: 9px; color:#555; }
';

$html = '
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<style>'.$style.'</style>
</head>
<body>
  <div class="summary">';
foreach ([1,2,3,4] as $lan) {
  $s = $lanStats[$lan] ?? ['count'=>0,'qty'=>0,'value'=>0,'expend'=>0,'net'=>0];
  $html .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;padding:6px 10px;display:inline-block;margin:2px;">
    <div style="font-size:12px;font-weight:bold;margin-bottom:2px;">ลาน '.h($lan).'</div>
    <div style="font-size:10px;">รายการ: <b>'.h($s['count']).'</b> | ปริมาณ: <b>'.fmt2($s['qty']).'</b> กก.</div>
    <div style="font-size:10px;">มูลค่า: <b>'.fmt2($s['value']).'</b> | หัก: <b>'.fmt2($s['expend']).'</b> | สุทธิ: <b>'.fmt2($s['net']).'</b> ฿</div>
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

// dompdf options (ปรับให้ใช้หน่วยความจำต่ำลง)
$options = new Options();
// ปิด HTML5 parser เพื่อลดการใช้หน่วยความจำ
$options->set('isHtml5ParserEnabled', false);
$options->set('isRemoteEnabled', false);
$options->set('chroot', __DIR__);
// ใช้ฟอนต์ดีฟอลต์ ถ้ามีฟอนต์ไทยให้ใช้ ตามเดิม
$options->set('defaultFont', $hasThaiFonts ? $defaultFamily : 'DejaVu Sans');
// ปิด font cache และ font subsetting ลดงานประมวลผลฟอนต์
$options->set('fontCache', false);
$options->set('isFontSubsettingEnabled', false);

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
  // รองรับทั้ง YYYY-MM-DD และ YYYY-MM-DD HH:MM:SS
  $dateOnly = substr($date, 0, 10);
  $parts = explode('-', $dateOnly);
  if (count($parts) !== 3) return $date;
  $y = (int)$parts[0] + 543;
  $m = (int)$parts[1];
  $d = (int)$parts[2];
  if ($m < 1 || $m > 12) return $date;
  return $d.' '.$months[$m].' '.$y;
}

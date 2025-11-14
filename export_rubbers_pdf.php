<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// robust autoload discovery
function fail($msg) {
  http_response_code(500);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><meta charset="utf-8"><title>Export PDF Error</title><div style="font-family:Arial,sans-serif;padding:16px">';
  echo '<h3>ไม่สามารถส่งออกเป็น PDF</h3><p style="color:#b00020">'.$msg.'</p>';
  echo '<ol><li>รัน: <code>composer require dompdf/dompdf</code> ในโฟลเดอร์ /C:/xampp/htdocs/rts</li>';
  echo '<li>ตรวจสอบว่าไฟล์ <code>/C:/xampp/htdocs/rts/vendor/autoload.php</code> มีอยู่</li>';
  echo '<li>เปิดใช้งาน PHP extensions mbstring และ gd ใน XAMPP</li></ol></div>';
  exit;
}

$autoload = null;
foreach ([__DIR__.'/vendor/autoload.php', __DIR__.'/../vendor/autoload.php', dirname(__DIR__).'/vendor/autoload.php'] as $cand) {
  if (is_file($cand)) { $autoload = $cand; break; }
}
if (!$autoload) fail('ไม่พบไฟล์ vendor/autoload.php');

require_once $autoload;
if (!class_exists('Dompdf\\Dompdf')) fail('ไม่พบคลาส Dompdf โปรดติดตั้งแพ็คเกจ dompdf/dompdf');

// read filters
$lanParam   = $_GET['lan'] ?? 'all';
$search     = trim((string)($_GET['search'] ?? ''));
$date_from  = trim((string)($_GET['date_from'] ?? ''));
$date_to    = trim((string)($_GET['date_to'] ?? ''));

$currentLan = ($lanParam === 'all') ? 'all' : (int)$lanParam;
if ($currentLan !== 'all' && !in_array($currentLan, [1,2,3,4], true)) $currentLan = 1;

// build query (reuse logic)
$rows = [];
if ($currentLan === 'all') {
  $conds = [];
  $binds = [];
  $types = '';
  if ($search !== '') {
    $like = '%' . $search . '%';
    $conds[] = '(ru_group LIKE ? OR ru_number LIKE ? OR ru_fullname LIKE ? OR ru_class LIKE ?)';
    $types .= 'ssss';
    array_push($binds, $like, $like, $like, $like);
  }
  if ($date_from !== '' && DateTime::createFromFormat('Y-m-d', $date_from)) {
    $conds[] = 'ru_date >= ?';
    $types .= 's';
    $binds[] = $date_from;
  }
  if ($date_to !== '' && DateTime::createFromFormat('Y-m-d', $date_to)) {
    $conds[] = 'ru_date <= ?';
    $types .= 's';
    $binds[] = $date_to;
  }
  $sql = "SELECT * FROM tbl_rubber";
  if ($conds) $sql .= ' WHERE ' . implode(' AND ', $conds);
  $sql .= ' ORDER BY ru_date DESC, ru_id DESC';
  if ($conds) {
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$binds);
    $st->execute();
    $res = $st->get_result();
  } else {
    $res = $db->query($sql);
  }
  if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
  if (!empty($st)) $st->close();
} else {
  $st = $db->prepare("SELECT * FROM tbl_rubber WHERE ru_lan=? ORDER BY ru_date DESC, ru_id DESC");
  $lanStr = (string)$currentLan;
  $st->bind_param('s', $lanStr);
  $st->execute();
  $res = $st->get_result();
  if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
  $st->close();
}

// summary
$sumQty=0;$sumValue=0;$sumExpend=0;$sumNet=0;
foreach ($rows as $r) {
  $sumQty    += (float)$r['ru_quantity'];
  $sumValue  += (float)($r['ru_value'] ?? 0);
  $sumExpend += (float)($r['ru_expend'] ?? 0);
  $sumNet    += (float)($r['ru_netvalue'] ?? 0);
}

$html = '<h3 style="text-align:center">รายงานข้อมูลยางพารา '.($currentLan==='all'?'ทุกลาน':'ลาน '.$currentLan).'</h3>';

if ($search || $date_from || $date_to) {
  $html .= '<p style="font-size:10pt;">เงื่อนไข: '
    . ($search ? 'คำค้น="'.htmlspecialchars($search,ENT_QUOTES).'" ' : '')
    . ($date_from ? 'จาก '.$date_from.' ' : '')
    . ($date_to ? 'ถึง '.$date_to.' ' : '')
    . '</p>';
}

$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:10pt;">
<thead>
<tr style="background:#f0f0f0;">
  <th>ID</th>
  <th>วันที่</th>
  <th>ลาน</th>
  <th>กลุ่ม</th>
  <th>เลขที่</th>
  <th>ชื่อ-สกุล</th>
  <th>ชั้น</th>
  <th>ปริมาณ</th>
  <th>มูลค่า</th>
  <th>หักรวม</th>
  <th>สุทธิ</th>
</tr>
</thead><tbody>';

if (!$rows) {
  $html .= '<tr><td colspan="11" style="text-align:center;color:#888;">ไม่มีข้อมูล</td></tr>';
} else {
  foreach ($rows as $r) {
    $html .= '<tr>'
      . '<td>'.$r['ru_id'].'</td>'
      . '<td>'.$r['ru_date'].'</td>'
      . '<td>'.$r['ru_lan'].'</td>'
      . '<td>'.htmlspecialchars($r['ru_group']).'</td>'
      . '<td>'.htmlspecialchars($r['ru_number']).'</td>'
      . '<td>'.htmlspecialchars($r['ru_fullname']).'</td>'
      . '<td>'.htmlspecialchars($r['ru_class']).'</td>'
      . '<td style="text-align:right;">'.number_format((float)$r['ru_quantity'],2).'</td>'
      . '<td style="text-align:right;">'.number_format((float)($r['ru_value'] ?? 0),2).'</td>'
      . '<td style="text-align:right;">'.number_format((float)($r['ru_expend'] ?? 0),2).'</td>'
      . '<td style="text-align:right;">'.number_format((float)($r['ru_netvalue'] ?? 0),2).'</td>'
      . '</tr>';
  }
}

$html .= '</tbody>';
$html .= '<tfoot><tr style="background:#fafafa;font-weight:bold;">'
  . '<td colspan="7" style="text-align:right;">รวม</td>'
  . '<td style="text-align:right;">'.number_format($sumQty,2).'</td>'
  . '<td style="text-align:right;">'.number_format($sumValue,2).'</td>'
  . '<td style="text-align:right;">'.number_format($sumExpend,2).'</td>'
  . '<td style="text-align:right;">'.number_format($sumNet,2).'</td>'
  . '</tr></tfoot>';
$html .= '</table>';

use Dompdf\Dompdf;
use Dompdf\Options;

// dompdf options
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
// optional: restrict file access to project root
$options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');

// render
$dompdf->render();

// clean any previous output to avoid header issues
if (ob_get_length()) { ob_end_clean(); }

// stream inline (Attachment=false)
$filename = 'rubbers_' . ($currentLan === 'all' ? 'all' : 'lan'.$currentLan) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;

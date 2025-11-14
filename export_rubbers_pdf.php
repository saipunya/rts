<?php
declare(strict_types=1);

require __DIR__ . '/functions.php';
require __DIR__ . '/vendor/autoload.php';

$db = db();

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

// ensure temp/font dirs exist
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
$fontDir = __DIR__ . '/fonts'; // optional custom fonts

// minimal mPDF init (Thai support if fonts copied to /fonts)
$mpdf = new \Mpdf\Mpdf([
  'tempDir' => $tmpDir,
  // change default font if you add THSarabunNew to /fonts
  // 'fontDir' => [$fontDir, \Mpdf\Config\ConfigVariables::getDefaults()['fontDir']],
  'default_font' => 'dejavusans', // switch to 'thsarabun' if you add Thai fonts
]);

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

$mpdf->SetTitle('รายงานข้อมูลยางพารา');
$mpdf->WriteHTML($html);
$mpdf->Output('rubbers_report.pdf', \Mpdf\Output\Destination::INLINE);

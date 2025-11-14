<?php
require_once __DIR__ . '/functions.php';

$db = db();

// params
$lanParam  = $_GET['lan'] ?? '1';
$search    = trim((string)($_GET['search'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to   = trim((string)($_GET['date_to'] ?? ''));

if ($lanParam === 'all') {
  $currentLan = 'all';
} else {
  $currentLan = (int)$lanParam;
  if (!in_array($currentLan, [1, 2, 3, 4], true)) $currentLan = 1;
}

// build conditions (reuse logic from rubbers.php)
$conds = [];
$types = '';
$binds = [];

if ($currentLan !== 'all') {
  $conds[] = 'ru_lan = ?';
  $types  .= 's';
  $binds[] = (string)$currentLan;
}

if ($search !== '') {
  $like = '%' . $search . '%';
  $conds[] = '(ru_group LIKE ? OR ru_number LIKE ? OR ru_fullname LIKE ? OR ru_class LIKE ?)';
  $types .= 'ssss';
  array_push($binds, $like, $like, $like, $like);
}

if ($date_from !== '') {
  $df = DateTime::createFromFormat('Y-m-d', $date_from);
  if ($df && $df->format('Y-m-d') === $date_from) {
    $conds[] = 'ru_date >= ?';
    $types  .= 's';
    $binds[] = $date_from;
  }
}

if ($date_to !== '') {
  $dt = DateTime::createFromFormat('Y-m-d', $date_to);
  if ($dt && $dt->format('Y-m-d') === $date_to) {
    $conds[] = 'ru_date <= ?';
    $types  .= 's';
    $binds[] = $date_to;
  }
}

$sql = "SELECT
  ru_id, ru_date, ru_lan, ru_group, ru_number, ru_fullname, ru_class,
  ru_quantity, ru_hoon, ru_loan, ru_shortdebt, ru_deposit, ru_tradeloan, ru_insurance,
  ru_value, ru_expend, ru_netvalue, ru_saveby, ru_savedate
FROM tbl_rubber";
if ($conds) $sql .= ' WHERE ' . implode(' AND ', $conds);
$sql .= ' ORDER BY ru_date DESC, ru_id DESC';

// query
if ($conds) {
  $st = $db->prepare($sql);
  $st->bind_param($types, ...$binds);
  $st->execute();
  $res = $st->get_result();
} else {
  $res = $db->query($sql);
}

// headers
$filename = 'rubbers_' . ($currentLan === 'all' ? 'all' : $currentLan) . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// output with BOM for Excel
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

// header row (Thai labels)
fputcsv($out, [
  'ID','วันที่','ลาน','กลุ่ม','เลขที่','ชื่อ-สกุล','ชั้น',
  'ปริมาณ','หุ้น','เงินกู้','หนี้สั้น','เงินฝาก','กู้ซื้อขาย','ประกันภัย',
  'มูลค่า','หักรวม','สุทธิ','บันทึกโดย','วันที่บันทึก'
]);

// helper for numeric formatting (machine-friendly)
$fmt = function($v){ return number_format((float)$v, 2, '.', ''); };

// rows
if ($res) {
  while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
      (int)$row['ru_id'],
      $row['ru_date'],
      $row['ru_lan'],
      $row['ru_group'],
      $row['ru_number'],
      $row['ru_fullname'],
      $row['ru_class'],
      $fmt($row['ru_quantity']),
      $fmt($row['ru_hoon']),
      $fmt($row['ru_loan']),
      $fmt($row['ru_shortdebt']),
      $fmt($row['ru_deposit']),
      $fmt($row['ru_tradeloan']),
      $fmt($row['ru_insurance']),
      $fmt($row['ru_value']),
      $fmt($row['ru_expend']),
      $fmt($row['ru_netvalue']),
      $row['ru_saveby'],
      $row['ru_savedate'],
    ]);
  }
  if (!empty($st)) $st->close();
}

fclose($out);
exit;

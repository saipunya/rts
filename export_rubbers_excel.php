<?php
require_once __DIR__ . '/functions.php';
require_login();

@set_time_limit(120);

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
$st = null;
if ($conds) {
	$st = $db->prepare($sql);
	if (!$st) {
		http_response_code(500);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'SQL prepare failed: ' . $db->error;
		exit;
	}
	$st->bind_param($types, ...$binds);
	$st->execute();
	$res = $st->get_result();
} else {
	$res = $db->query($sql);
}

function xh(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function xmlCell(string $type, string $data, string $styleId = ''): string {
	$attrs = $styleId !== '' ? ' ss:StyleID="' . xh($styleId) . '"' : '';
	return '<Cell' . $attrs . '><Data ss:Type="' . xh($type) . '">' . xh($data) . '</Data></Cell>';
}

function xmlRow(array $cells): string {
	return '<Row>' . implode('', $cells) . "</Row>\n";
}

$filename = 'rubbers_' . ($currentLan === 'all' ? 'all' : $currentLan) . '_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

if (ob_get_length()) { @ob_end_clean(); }

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
	. ' xmlns:o="urn:schemas-microsoft-com:office:office"'
	. ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
	. ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
	. ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

// styles
// sHeader: grey header, bold
// sNum: 2 decimals, right
// sText: left

echo '<Styles>';
echo '<Style ss:ID="sHeader"><Font ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
	. '<Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>'
	. '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
	. '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
	. '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
	. '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';

echo '<Style ss:ID="sText"><Alignment ss:Horizontal="Left" ss:Vertical="Center"/>'
	. '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
	. '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
	. '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
	. '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/></Borders></Style>';

echo '<Style ss:ID="sTextC"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
	. '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
	. '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
	. '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
	. '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/></Borders></Style>';

echo '<Style ss:ID="sNum"><NumberFormat ss:Format="0.00"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/>'
	. '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
	. '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
	. '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
	. '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/></Borders></Style>';

echo '</Styles>' . "\n";

// sheet

echo '<Worksheet ss:Name="รายการรับซื้อ"><Table>' . "\n";

// header row

echo xmlRow([
	xmlCell('String', 'ID', 'sHeader'),
	xmlCell('String', 'วันที่', 'sHeader'),
	xmlCell('String', 'ลาน', 'sHeader'),
	xmlCell('String', 'กลุ่ม', 'sHeader'),
	xmlCell('String', 'เลขที่', 'sHeader'),
	xmlCell('String', 'ชื่อ-สกุล', 'sHeader'),
	xmlCell('String', 'ชั้น', 'sHeader'),
	xmlCell('String', 'ปริมาณ', 'sHeader'),
	xmlCell('String', 'หุ้น', 'sHeader'),
	xmlCell('String', 'เงินกู้', 'sHeader'),
	xmlCell('String', 'หนี้สั้น', 'sHeader'),
	xmlCell('String', 'เงินฝาก', 'sHeader'),
	xmlCell('String', 'กู้ซื้อขาย', 'sHeader'),
	xmlCell('String', 'ประกันภัย', 'sHeader'),
	xmlCell('String', 'มูลค่า', 'sHeader'),
	xmlCell('String', 'หักรวม', 'sHeader'),
	xmlCell('String', 'สุทธิ', 'sHeader'),
	xmlCell('String', 'บันทึกโดย', 'sHeader'),
	xmlCell('String', 'วันที่บันทึก', 'sHeader'),
]);

if ($res) {
	while ($row = $res->fetch_assoc()) {
		echo xmlRow([
			xmlCell('Number', (string)(int)$row['ru_id'], 'sTextC'),
			xmlCell('String', $row['ru_date'] ? thai_date_format((string)$row['ru_date']) : '', 'sTextC'),
			xmlCell('String', (string)$row['ru_lan'], 'sTextC'),
			xmlCell('String', (string)$row['ru_group'], 'sTextC'),
			xmlCell('String', (string)$row['ru_number'], 'sTextC'),
			xmlCell('String', (string)$row['ru_fullname'], 'sText'),
			xmlCell('String', (string)$row['ru_class'], 'sTextC'),
			xmlCell('Number', number_format((float)$row['ru_quantity'], 2, '.', ''), 'sNum'),
			xmlCell('Number', number_format((float)$row['ru_hoon'], 2, '.', ''), 'sNum'),
			xmlCell('Number', number_format((float)$row['ru_loan'], 2, '.', ''), 'sNum'),
			xmlCell('Number', number_format((float)$row['ru_shortdebt'], 2, '.', ''), 'sNum'),
			xmlCell('Number', number_format((float)$row['ru_deposit'], 2, '.', ''), 'sNum'),
			xmlCell('Number', number_format((float)$row['ru_tradeloan'], 2, '.', ''), 'sNum'),
			xmlCell('Number', number_format((float)$row['ru_insurance'], 2, '.', ''), 'sNum'),
			xmlCell('Number', number_format((float)$row['ru_value'], 2, '.', ''), 'sNum'),
			xmlCell('Number', number_format((float)$row['ru_expend'], 2, '.', ''), 'sNum'),
			xmlCell('Number', number_format((float)$row['ru_netvalue'], 2, '.', ''), 'sNum'),
			xmlCell('String', (string)$row['ru_saveby'], 'sText'),
			xmlCell('String', $row['ru_savedate'] ? thai_date_format((string)$row['ru_savedate']) : '', 'sTextC'),
		]);
	}
	if ($st) $st->close();
}

echo '</Table></Worksheet>' . "\n";
echo '</Workbook>';
exit;

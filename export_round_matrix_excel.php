<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();

@set_time_limit(120);

$db = db();

// Inputs
$dates = $_POST['dates'] ?? [];
if (!is_array($dates)) $dates = [];

$lanParam = trim((string)($_POST['lan'] ?? 'all'));
$currentLan = 'all';
if ($lanParam !== '' && strtolower($lanParam) !== 'all') {
	$lanInt = (int)$lanParam;
	if (in_array($lanInt, [1,2,3,4], true)) $currentLan = (string)$lanInt;
}

$includeMember = !empty($_POST['include_member']);
$includeGeneral = !empty($_POST['include_general']);

// Validate dates (Y-m-d) and keep unique
$validDates = [];
foreach ($dates as $d) {
	$d = trim((string)$d);
	if ($d === '') continue;
	$dt = DateTime::createFromFormat('Y-m-d', $d);
	if ($dt && $dt->format('Y-m-d') === $d) $validDates[$d] = true;
}
$validDates = array_keys($validDates);
if (!$validDates) {
	header('Content-Type: text/html; charset=utf-8');
	echo '<!doctype html><meta charset="utf-8"><title>Export</title><div style="font-family:Tahoma,Arial,sans-serif;padding:16px">';
	echo '<h3>ไม่สามารถส่งออกได้</h3><p>กรุณาเลือกรอบวันที่อย่างน้อย 1 รอบ</p>';
	echo '</div>';
	exit;
}

// Sort ascending (เหมาะกับการทำคอลัมน์เรียงเวลา)
sort($validDates);

// Load prices for those dates
$priceByDate = [];
{
	$placeholders = implode(',', array_fill(0, count($validDates), '?'));
	$sql = "SELECT pr_date, pr_price FROM tbl_price WHERE pr_date IN ($placeholders)";
	$st = $db->prepare($sql);
	if ($st) {
		$types = str_repeat('s', count($validDates));
		$st->bind_param($types, ...$validDates);
		$st->execute();
		$res = $st->get_result();
		if ($res) {
			while ($r = $res->fetch_assoc()) {
				$priceByDate[$r['pr_date']] = (float)$r['pr_price'];
			}
			$res->free();
		}
		$st->close();
	}
}

// Query aggregated rubber by person + date
$rows = [];
{
	$placeholders = implode(',', array_fill(0, count($validDates), '?'));
	$conds = ["r.ru_date IN ($placeholders)"];
	$params = $validDates;
	$types = str_repeat('s', count($validDates));

	if ($currentLan !== 'all') {
		$conds[] = 'r.ru_lan = ?';
		$params[] = $currentLan;
		$types .= 's';
	}

	$sql = "SELECT
		r.ru_class,
		r.ru_group,
		r.ru_number,
		r.ru_fullname,
		r.ru_date,
		SUM(r.ru_quantity) AS total_qty,
		SUM(r.ru_value)    AS total_value
	FROM tbl_rubber r
	WHERE " . implode(' AND ', $conds) . "
	GROUP BY r.ru_class, r.ru_group, r.ru_number, r.ru_fullname, r.ru_date
	ORDER BY r.ru_class ASC, CAST(r.ru_group AS UNSIGNED) ASC, r.ru_group ASC, r.ru_number ASC, r.ru_fullname ASC, r.ru_date ASC";

	$st = $db->prepare($sql);
	if (!$st) {
		http_response_code(500);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'SQL prepare failed: ' . $db->error;
		exit;
	}
	$st->bind_param($types, ...$params);
	$st->execute();
	$res = $st->get_result();
	if ($res) {
		$rows = $res->fetch_all(MYSQLI_ASSOC);
		$res->free();
	}
	$st->close();
}

// Build pivot
$people = [];
foreach ($rows as $r) {
	$class = (string)($r['ru_class'] ?? '');
	$group = (string)($r['ru_group'] ?? '');
	$number = (string)($r['ru_number'] ?? '');
	$fullname = (string)($r['ru_fullname'] ?? '');
	$date = (string)($r['ru_date'] ?? '');

	$key = $class . '|' . $group . '|' . $number . '|' . $fullname;
	if (!isset($people[$key])) {
		$people[$key] = [
			'class' => $class,
			'group' => $group,
			'number' => $number,
			'fullname' => $fullname,
			'by_date' => []
		];
	}
	$people[$key]['by_date'][$date] = [
		'qty' => (float)($r['total_qty'] ?? 0),
		'value' => (float)($r['total_value'] ?? 0),
	];
}

// Partition
$members = [];
$generals = [];
foreach ($people as $p) {
	$class = strtolower((string)$p['class']);
	if ($class === 'member') $members[] = $p;
	else $generals[] = $p;
}

// Excel 2003 XML (SpreadsheetML) supports multiple worksheets without external libraries.
function xh(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cell(string $type, string $data, string $styleId = '', ?int $mergeAcross = null, ?int $mergeDown = null, ?int $index = null): string {
	$attrs = '';
	if ($styleId !== '') $attrs .= ' ss:StyleID="' . xh($styleId) . '"';
	if ($mergeAcross !== null && $mergeAcross > 0) $attrs .= ' ss:MergeAcross="' . (int)$mergeAcross . '"';
	if ($mergeDown !== null && $mergeDown > 0) $attrs .= ' ss:MergeDown="' . (int)$mergeDown . '"';
	if ($index !== null && $index > 1) $attrs .= ' ss:Index="' . (int)$index . '"';
	return '<Cell' . $attrs . '><Data ss:Type="' . xh($type) . '">' . xh($data) . '</Data></Cell>';
}

function row(array $cells): string {
	return "<Row>" . implode('', $cells) . "</Row>\n";
}

function renderWorksheet(string $sheetName, array $data, array $validDates, array $priceByDate, bool $withMemberCols, string $lanTitle): string {
	$baseCols = $withMemberCols ? 4 : 2; // ลำดับ + ชื่อ + (เลขทะเบียน + กลุ่ม)
	$colCount = $baseCols + (count($validDates) * 2) + 2; // + รวมท้ายแถว (น้ำหนักรวม/ยอดเงินรวม)
	$xml = '<Worksheet ss:Name="' . xh($sheetName) . '"><Table>' . "\n";

	// Title
	$xml .= row([
		cell('String', 'สรุปรวบรวมยางตามรอบวันที่ราคายาง - ' . $sheetName, 'sTitle', $colCount - 1),
	]);
	$xml .= row([
		cell('String', $lanTitle, 'sSub', $colCount - 1),
	]);

	// Rounds line
	$parts = [];
	foreach ($validDates as $d) {
		$price = isset($priceByDate[$d]) ? number_format((float)$priceByDate[$d], 2, '.', '') : '';
		$parts[] = thai_date_format($d) . ($price !== '' ? (' (' . $price . ')') : '');
	}
	$xml .= row([
		cell('String', 'รอบวันที่: ' . implode(' | ', $parts), 'sNote', $colCount - 1),
	]);
	$xml .= row([cell('String', '', '', $colCount - 1)]);

	// Header rows (2 rows)
	$cells1 = [];
	$cells1[] = cell('String', 'ลำดับ', 'sHeaderC', null, 1);
	$cells1[] = cell('String', 'ชื่อ-สกุล', 'sHeader', null, 1);
	if ($withMemberCols) {
		$cells1[] = cell('String', 'เลขทะเบียน', 'sHeaderC', null, 1);
		$cells1[] = cell('String', 'กลุ่ม', 'sHeaderC', null, 1);
	}
	foreach ($validDates as $d) {
		$price = isset($priceByDate[$d]) ? number_format((float)$priceByDate[$d], 2, '.', '') : '';
		$hdr = thai_date_format($d) . ($price !== '' ? (' (' . $price . ')') : '');
		$cells1[] = cell('String', $hdr, 'sHeaderC', 1, null);
	}
	$cells1[] = cell('String', 'น้ำหนักรวม', 'sHeaderC', null, 1);
	$cells1[] = cell('String', 'ยอดเงินรวม', 'sHeaderC', null, 1);
	$xml .= row($cells1);

	$cells2 = [];
	// skip merged-down fixed columns by using Index
	$startIdx = $baseCols + 1;
	foreach ($validDates as $i => $_d) {
		$idx = $startIdx + ($i * 2);
		$cells2[] = cell('String', 'น้ำหนัก', 'sHeaderC', null, null, $idx);
		$cells2[] = cell('String', 'จำนวนเงิน', 'sHeaderC');
	}
	$xml .= row($cells2);

	// Data rows
	$totQty = array_fill_keys($validDates, 0.0);
	$totVal = array_fill_keys($validDates, 0.0);
	$grandQty = 0.0;
	$grandVal = 0.0;

	$idx = 1;
	foreach ($data as $p) {
		$rowCells = [];
		$rowQtyTotal = 0.0;
		$rowValTotal = 0.0;
		$rowCells[] = cell('Number', (string)$idx, 'sTextC');
		$rowCells[] = cell('String', (string)$p['fullname'], 'sText');
		if ($withMemberCols) {
			$rowCells[] = cell('String', (string)$p['number'], 'sTextC');
			$rowCells[] = cell('String', (string)$p['group'], 'sTextC');
		}
		foreach ($validDates as $d) {
			$cellData = $p['by_date'][$d] ?? null;
			$qty = $cellData ? (float)$cellData['qty'] : 0.0;
			$val = $cellData ? (float)$cellData['value'] : 0.0;
			$totQty[$d] += $qty;
			$totVal[$d] += $val;
			$rowQtyTotal += $qty;
			$rowValTotal += $val;
			$rowCells[] = $qty != 0.0 ? cell('Number', number_format($qty, 2, '.', ''), 'sNum') : cell('String', '', 'sText');
			$rowCells[] = $val != 0.0 ? cell('Number', number_format($val, 2, '.', ''), 'sNum') : cell('String', '', 'sText');
		}
		$grandQty += $rowQtyTotal;
		$grandVal += $rowValTotal;
		$rowCells[] = $rowQtyTotal != 0.0 ? cell('Number', number_format($rowQtyTotal, 2, '.', ''), 'sNumB') : cell('String', '', 'sText');
		$rowCells[] = $rowValTotal != 0.0 ? cell('Number', number_format($rowValTotal, 2, '.', ''), 'sNumB') : cell('String', '', 'sText');

		$xml .= row($rowCells);
		$idx++;
	}

	// Total row
	$totalRow = [];
	$totalRow[] = cell('String', 'รวม', 'sTotal', $baseCols - 1);
	foreach ($validDates as $d) {
		$totalRow[] = cell('Number', number_format((float)($totQty[$d] ?? 0), 2, '.', ''), 'sTotalNum');
		$totalRow[] = cell('Number', number_format((float)($totVal[$d] ?? 0), 2, '.', ''), 'sTotalNum');
	}
	$totalRow[] = cell('Number', number_format($grandQty, 2, '.', ''), 'sTotalNum');
	$totalRow[] = cell('Number', number_format($grandVal, 2, '.', ''), 'sTotalNum');
	$xml .= row($totalRow);

	$xml .= '</Table></Worksheet>' . "\n";
	return $xml;
}

$filename = 'round_matrix_' . ($currentLan === 'all' ? 'all' : ('lan'.$currentLan)) . '_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// prevent any previous output
if (ob_get_length()) { @ob_end_clean(); }

$lanTitle = ($currentLan === 'all') ? 'ทุกลาน' : ('ลาน ' . $currentLan);

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
  . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
  . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
  . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
  . ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

// Styles
echo '<Styles>';
echo '<Style ss:ID="sTitle"><Font ss:Bold="1" ss:Size="16"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
echo '<Style ss:ID="sSub"><Font ss:Bold="1" ss:Size="12"/><Alignment ss:Horizontal="Center"/></Style>';
echo '<Style ss:ID="sNote"><Font ss:Size="10" ss:Color="#555555"/><Alignment ss:Horizontal="Center"/></Style>';
echo '<Style ss:ID="sHeader"><Font ss:Bold="1"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/>'
  . '<Interior ss:Color="#F1F1F1" ss:Pattern="Solid"/>'
  . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
  . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
  . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
  . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="sHeaderC"><Font ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
  . '<Interior ss:Color="#F1F1F1" ss:Pattern="Solid"/>'
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
echo '<Style ss:ID="sNumB"><NumberFormat ss:Format="0.00"/><Font ss:Bold="1"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/>'
  . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
  . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
  . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>'
  . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/></Borders></Style>';
echo '<Style ss:ID="sTotal"><Font ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
  . '<Interior ss:Color="#F9F9F9" ss:Pattern="Solid"/>'
  . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
  . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
  . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
  . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="sTotalNum"><NumberFormat ss:Format="0.00"/><Font ss:Bold="1"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/>'
  . '<Interior ss:Color="#F9F9F9" ss:Pattern="Solid"/>'
  . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
  . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
  . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
  . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '</Styles>' . "\n";

$sheetsWritten = 0;
if ($includeMember) {
	echo renderWorksheet('สมาชิก', $members, $validDates, $priceByDate, true, $lanTitle);
	$sheetsWritten++;
}
if ($includeGeneral) {
	echo renderWorksheet('เกษตรกรทั่วไป', $generals, $validDates, $priceByDate, false, $lanTitle);
	$sheetsWritten++;
}

if ($sheetsWritten === 0) {
	echo renderWorksheet('ว่าง', [], $validDates, $priceByDate, false, $lanTitle);
}

echo '</Workbook>';
exit;

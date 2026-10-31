<?php
require_once __DIR__ . '/functions.php';
require_login();
require_admin();
$db = db();
ensure_weighing_schema($db);
$search = trim((string)($_GET['search'] ?? ''));
$lane = (string)($_GET['lan'] ?? 'all');
if (!in_array($lane, ['all','1','2','3','4'], true)) $lane = 'all';
$excel = ($_GET['format'] ?? '') === 'excel';
$latestPrice = $excel ? null : latest_rubber_price($db);
$printRoundDate = $excel ? null : (string)($latestPrice['pr_date'] ?? '');
$result = issued_rubber_receipts($db, $search, $lane, 1, true, $printRoundDate);
$headings = ['ลำดับ','รอบขายยาง','ชื่อผู้ส่งยาง','เลขสมาชิก','กลุ่ม','ลาน','กระสอบ','น้ำหนัก (กก.)','ยอดเงินก่อนหัก (บาท)','ยอดหัก (บาท)','รับสุทธิ (บาท)','ออกใบเสร็จเมื่อ'];
$exportRows = [];
foreach ($result['rows'] as $index=>$row) {
    $exportRows[] = [$index+1, thai_date_short_format($row['collection_weigh_date'] ?? $row['ru_date']),
        collection_display_name($row['ru_fullname'], $row['wang_note']), $row['ru_number'], $row['ru_group'], $row['ru_lan'],
        $row['wang_sack'] === null ? '' : $row['wang_sack'], $row['ru_quantity'], $row['ru_value'], $row['ru_expend'], $row['ru_netvalue'], thai_datetime_format($row['issued_at'])];
}
if ($excel) {
    // SpreadsheetML keeps member numbers as text and money as actual numeric cells.
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="printed_receipts.xls"');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="Money"><NumberFormat ss:Format="#,##0.00"/></Style></Styles><Worksheet ss:Name="ใบเสร็จที่พิมพ์แล้ว"><Table>';
    foreach (array_merge([$headings], $exportRows) as $rowIndex=>$values) {
        echo '<Row>';
        foreach ($values as $column=>$value) {
            $numeric = $rowIndex > 0 && in_array($column, [0,6,7,8,9,10], true) && $value !== '';
            $style = $numeric && $column >= 6 ? ' ss:StyleID="Money"' : '';
            echo '<Cell' . $style . '><Data ss:Type="' . ($numeric ? 'Number' : 'String') . '">' . htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        }
        echo '</Row>';
    }
    echo '</Table></Worksheet></Workbook>';
    exit;
}
// The printed report has one round, already shown in its page heading.
array_splice($headings, 1, 1);
foreach ($exportRows as &$printValues) array_splice($printValues, 1, 1);
unset($printValues);

?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><title>รายการใบเสร็จที่พิมพ์แล้ว</title>
<style>
@page{size:A4 landscape;margin:10mm}body{font-family:Tahoma,sans-serif;color:#000;font-size:12px}h1{font-size:18px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #aaa;padding:6px;text-align:left}th{background:#eee}thead{display:table-header-group}tr{break-inside:avoid}.number{text-align:right;white-space:nowrap}.controls{margin-bottom:16px}.controls button{padding:10px 18px;cursor:pointer}@media print{.controls{display:none}}
</style></head><body>
<div class="controls"><button type="button" onclick="window.print()">พิมพ์รายการ</button></div>
<h1>รายการใบเสร็จที่พิมพ์แล้ว</h1>
<p><?php echo $printRoundDate !== '' ? 'รอบวันที่ราคายางล่าสุด ' . e(thai_date_format($printRoundDate)) : 'ยังไม่มีข้อมูลราคายางสำหรับพิมพ์รายการ'; ?></p>
<p><?php echo $lane === 'all' ? 'ทุกลาน' : 'ลาน ' . e($lane); ?><?php if ($search !== ''): ?> · คำค้น: <?php echo e($search); ?><?php endif; ?> · <?php echo number_format($result['total']); ?> รายการ</p>
<table><thead><tr><?php foreach ($headings as $label): ?><th><?php echo e($label); ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($exportRows as $values): ?><tr><?php foreach ($values as $column=>$value): ?><td<?php echo in_array($column,[5,6,7,8,9],true) ? ' class="number"' : ''; ?>><?php echo e(in_array($column,[5,6,7,8,9],true) && $value !== '' ? number_format((float)$value,2) : $value); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$exportRows): ?><tr><td colspan="11">ไม่พบรายการตามตัวกรอง</td></tr><?php endif; ?>
</tbody></table>
</body></html>

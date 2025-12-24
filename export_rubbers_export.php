<?php

if ($type === 'excel') {

// Header สำหรับ Excel (UTF-16LE)
header('Content-Type: text/csv; charset=UTF-16LE');
header('Content-Disposition: attachment; filename="rubbers_export.csv"');

// BOM สำหรับ UTF-16LE
echo "\xFF\xFE";

$out = fopen('php://output', 'w');

// ฟังก์ชันเขียน CSV เป็น UTF-16LE (Excel Friendly)
function fputcsv_utf16le($handle, array $row) {
    $csv = fopen('php://temp', 'r+');
    fputcsv($csv, $row);
    rewind($csv);
    $data = stream_get_contents($csv);
    fclose($csv);

    // แปลง UTF-8 → UTF-16LE
    $data = mb_convert_encoding($data, 'UTF-16LE', 'UTF-8');
    fwrite($handle, $data);
}

// ===== หัวรายงาน =====
fputcsv_utf16le($out, ['รายงานข้อมูลรับซื้อยาง']);

if ($pr_date) {
    fputcsv_utf16le($out, ['รอบวันที่', thai_date($pr_date)]);
} elseif ($scope === 'year') {
    fputcsv_utf16le($out, ['ประจำปี', $year + 543]);
} elseif ($scope === 'month') {
    fputcsv_utf16le($out, ['ประจำเดือน', $month . '/' . ($year + 543)]);
} elseif ($scope === 'period') {
    fputcsv_utf16le($out, ['ช่วงวันที่', thai_date($period_start) . ' ถึง ' . thai_date($period_end)]);
}

// เว้นบรรทัด
fputcsv_utf16le($out, []);

// ===== หัวตาราง =====
fputcsv_utf16le($out, [
    'ID',
    'วันที่บันทึก',
    'ชื่อ-สกุล',
    'ปริมาณ (กก.)',
    'ยอดเงินรวม',
    'ยอดรับสุทธิ'
]);

// ===== ข้อมูล =====
$total_qty = 0;
$total_value = 0;
$total_net = 0;

foreach ($rows as $row) {
    $net = $row['ru_netvalue']
        ?? ($row['ru_value']
            - ($row['ru_hoon']
            + $row['ru_loan']
            + $row['ru_shortdebt']
            + $row['ru_deposit']
            + $row['ru_tradeloan']
            + $row['ru_insurance']));

    $total_qty += $row['ru_quantity'];
    $total_value += $row['ru_value'];
    $total_net += $net;

    fputcsv_utf16le($out, [
        $row['ru_id'],
        thai_date($row['ru_date']),
        $row['ru_fullname'],
        number_format($row['ru_quantity'], 2),
        number_format($row['ru_value'], 2),
        number_format($net, 2),
    ]);
}

// ===== รวมทั้งสิ้น =====
fputcsv_utf16le($out, [
    '',
    '',
    'รวมทั้งสิ้น',
    number_format($total_qty, 2),
    number_format($total_value, 2),
    number_format($total_net, 2),
]);

fclose($out);
exit;
}
?>

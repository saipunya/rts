<?php
include "functions.php";
require_once __DIR__ . '/vendor/autoload.php'; // เรียกใช้ dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

$mysqli = db();

// รับค่าจาก query string
$keyword = $_GET['keyword'] ?? '';
$date_start = $_GET['date_start'] ?? '';
$date_end = $_GET['date_end'] ?? '';

// เงื่อนไขค้นหา
$where = [];
$params = [];
$types = '';
if ($keyword) {
    $where[] = "(ru_fullname LIKE ? OR ru_number LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $types .= 'ss';
}
if ($date_start) {
    $where[] = "ru_date >= ?";
    $params[] = $date_start;
    $types .= 's';
}
if ($date_end) {
    $where[] = "ru_date <= ?";
    $params[] = $date_end;
    $types .= 's';
}
$where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$sql = "SELECT ru_fullname, ru_number, SUM(ru_quantity) AS total_quantity, SUM(ru_value) AS total_value, SUM(ru_netvalue) AS total_netvalue FROM tbl_rubber $where_sql GROUP BY ru_fullname, ru_number ORDER BY ru_fullname";
$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$results = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// สร้าง HTML สำหรับ PDF
$html = '<h3 style="text-align:center;">รายงานข้อมูลยางพารา (สรุปยอดรวม)</h3>';
// เพิ่มช่วงวันที่ที่ค้นหา (ภาษาไทย)
if ($date_start || $date_end) {
    $html .= '<div style="margin-bottom:10px; text-align:center; font-size:14px;">';
    $html .= '<b>ช่วงวันที่ที่ค้นหา:</b> ';
    if ($date_start && $date_end) {
        $html .= thai_date_format($date_start) . ' ถึง ' . thai_date_format($date_end);
    } elseif ($date_start) {
        $html .= 'ตั้งแต่ ' . thai_date_format($date_start);
    } elseif ($date_end) {
        $html .= 'ถึง ' . thai_date_format($date_end);
    }
    $html .= '</div>';
}
$html .= '<table border="1" cellspacing="0" cellpadding="5" width="100%">';
$html .= '<thead><tr style="background:#eee;"><th>ชื่อ-สกุล</th><th>รหัสสมาชิก</th><th>น้ำหนักรวม (กก.)</th><th>ปริมาณยางรวม (บาท)</th><th>ยอดเงินรวม (บาท)</th></tr></thead><tbody>';
if ($results) {
    foreach ($results as $row) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['ru_fullname']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['ru_number']) . '</td>';
        $html .= '<td style="text-align:right;">' . number_format($row['total_quantity'], 2) . '</td>';
        $html .= '<td style="text-align:right;">' . number_format($row['total_value'], 2) . '</td>';
        $html .= '<td style="text-align:right;">' . number_format($row['total_netvalue'], 2) . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="5" style="text-align:center;">ไม่พบข้อมูล</td></tr>';
}
$html .= '</tbody></table>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Sarabun');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('total_rubber_report.pdf', ["Attachment" => 1]);
exit;

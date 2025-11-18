<?php
require_once __DIR__ . '/functions.php';
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(500);
    echo 'ไม่พบ Dompdf (vendor/autoload.php). โปรดติดตั้ง: composer require dompdf/dompdf';
    exit;
}
require_once $autoload;
use Dompdf\Dompdf;
use Dompdf\Options;

// รับค่าพารามิเตอร์
$type = $_GET['export_type'] ?? 'pdf';
$scope = $_GET['export_scope'] ?? 'year';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$period_start = $_GET['period_start'] ?? null;
$period_end = $_GET['period_end'] ?? null;

// สร้าง query ตามช่วงข้อมูล
$where = '';
$params = [];
$types = '';
if ($scope === 'year') {
    $where = 'WHERE YEAR(ru_date) = ?';
    $params[] = $year;
    $types .= 'i';
} elseif ($scope === 'month') {
    $where = 'WHERE YEAR(ru_date) = ? AND MONTH(ru_date) = ?';
    $params[] = $year;
    $params[] = $month;
    $types .= 'ii';
} elseif ($scope === 'period' && $period_start && $period_end) {
    $where = 'WHERE ru_date BETWEEN ? AND ?';
    $params[] = $period_start;
    $params[] = $period_end;
    $types .= 'ss';
}

$db = db();
$sql = "SELECT * FROM tbl_rubber $where ORDER BY ru_date, ru_id";
$st = $db->prepare($sql);
if ($types) {
    $st->bind_param($types, ...$params);
}
$st->execute();
$res = $st->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();

if ($type === 'excel') {
    // ส่งออกเป็น CSV (Excel)
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rubbers_export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'วันที่', 'ชื่อ-สกุล', 'ปริมาณ(กก.)', 'ยอดเงินรวม', 'ยอดรับสุทธิ']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['ru_id'],
            $row['ru_date'],
            $row['ru_fullname'],
            $row['ru_quantity'],
            $row['ru_value'],
            $row['ru_netvalue'] ?? ($row['ru_value'] - ($row['ru_hoon']+$row['ru_loan']+$row['ru_shortdebt']+$row['ru_deposit']+$row['ru_tradeloan']+$row['ru_insurance']))
        ]);
    }
    fclose($out);
    exit;
}

// ส่งออกเป็น PDF
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('chroot', __DIR__);
$options->set('isFontSubsettingEnabled', false);
$options->set('enable_font_subsetting', false);
$defaultFont = 'THSarabunNew';
$options->set('defaultFont', $defaultFont);
$dompdf = new Dompdf($options);

function nf($n) { return number_format((float)$n, 2); }
function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$style = 'body { font-family: THSarabunNew, DejaVu Sans, sans-serif; font-size: 16px; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ccc; padding: 4px; } th { background: #f1f1f1; }';
$html = '<!doctype html><html lang="th"><head><meta charset="UTF-8"><style>'.$style.'</style></head><body>';
$html .= '<h2 style="text-align:center">รายงานข้อมูลรับซื้อยาง '; 
if ($scope === 'year') $html .= 'ประจำปี '.e($year);
elseif ($scope === 'month') $html .= 'ประจำเดือน '.e($month).'/'.e($year);
elseif ($scope === 'period') $html .= 'ช่วงวันที่ '.e($period_start).' ถึง '.e($period_end);
$html .= '</h2>';
$html .= '<table><thead><tr><th>ID</th><th>วันที่</th><th>ชื่อ-สกุล</th><th>ปริมาณ(กก.)</th><th>ยอดเงินรวม</th><th>ยอดรับสุทธิ</th></tr></thead><tbody>';
foreach ($rows as $row) {
    $net = $row['ru_netvalue'] ?? ($row['ru_value'] - ($row['ru_hoon']+$row['ru_loan']+$row['ru_shortdebt']+$row['ru_deposit']+$row['ru_tradeloan']+$row['ru_insurance']));
    $html .= '<tr>';
    $html .= '<td>'.e($row['ru_id']).'</td>';
    $html .= '<td>'.e($row['ru_date']).'</td>';
    $html .= '<td>'.e($row['ru_fullname']).'</td>';
    $html .= '<td style="text-align:right">'.nf($row['ru_quantity']).'</td>';
    $html .= '<td style="text-align:right">'.nf($row['ru_value']).'</td>';
    $html .= '<td style="text-align:right">'.nf($net).'</td>';
    $html .= '</tr>';
}
$html .= '</tbody></table>';
$html .= '</body></html>';

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('rubbers_export.pdf', ['Attachment' => false]);
exit;

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
$pr_date = $_GET['pr_date'] ?? null;
$scope = $_GET['export_scope'] ?? 'year';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y'));
$month = isset($_GET['month']) ? (int)$_GET['month'] : (isset($_POST['month']) ? (int)$_POST['month'] : (int)date('n'));
$period_start = $_GET['period_start'] ?? null;
$period_end = $_GET['period_end'] ?? null;

$user = current_user(); // สมมติว่ามีฟังก์ชัน current_user() คืนค่า user id
$user_id = $user['id'];
$is_admin = is_admin();

// สร้าง query ตามช่วงข้อมูล (ใช้ ru_date แทน ru_savedate)
$where = '';
$params = [];
$types = '';
if ($pr_date) {
    $where = 'WHERE DATE(ru_date) = ?';
    $params[] = $pr_date;
    $types .= 's';
    if (!$is_admin) {
        $where .= ' AND ru_saveby = ?';
        $params[] = $user_id;
        $types .= 'i';
    }
} else {
    if ($scope === 'year') {
        $where = 'WHERE YEAR(ru_date) = ?';
        $params[] = $year;
        $types .= 'i';
        if (!$is_admin) {
            $where .= ' AND ru_saveby = ?';
            $params[] = $user_id;
            $types .= 'i';
        }
    } elseif ($scope === 'month') {
        $where = 'WHERE YEAR(ru_date) = ? AND MONTH(ru_date) = ?';
        $params[] = $year;
        $params[] = $month;
        $types .= 'ii';
        if (!$is_admin) {
            $where .= ' AND ru_saveby = ?';
            $params[] = $user_id;
            $types .= 'i';
        }
    } elseif ($scope === 'period' && $period_start && $period_end) {
        $where = 'WHERE DATE(ru_date) BETWEEN ? AND ?';
        $params[] = $period_start;
        $params[] = $period_end;
        $types .= 'ss';
        if (!$is_admin) {
            $where .= ' AND ru_saveby = ?';
            $params[] = $user_id;
            $types .= 'i';
        }
    }
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

function nf($n) { return number_format((float)$n, 2); }
if (!function_exists('e')) {
    function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

function thai_date($date) {
    $months = [
        '', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    $ts = strtotime($date);
    if (!$ts) return $date;
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts) + 543;
    return "$d {$months[$m]} $y";
}

if ($type === 'excel') {
    // ส่งออกเป็น Excel แบบเข้ากันได้กับภาษาไทย
    // ใช้ UTF-16LE + BOM และตั้ง Content-Type เป็น application/vnd.ms-excel
    header('Content-Type: application/vnd.ms-excel; charset=UTF-16LE');
    header('Content-Disposition: attachment; filename="rubbers_export.xls"');

    // สร้างข้อมูลเป็นบรรทัด CSV ด้วย comma และขึ้นบรรทัดด้วย \r\n
    $lines = [];
    // เพิ่มหัวรายงานแสดงรอบวันที่
    $lines[] = 'รอบวันที่,' . (thai_date_format($pr_date));
    $lines[] = 'ID,วันที่บันทึก,ชื่อ-สกุล,ปริมาณ(กก.),ยอดเงินรวม,ยอดรับสุทธิ';

    $total_qty = 0;
    $total_value = 0;
    $total_net = 0;
    foreach ($rows as $row) {
        $net = $row['ru_netvalue'] ?? ($row['ru_value'] - ($row['ru_hoon']+$row['ru_loan']+$row['ru_shortdebt']+$row['ru_deposit']+$row['ru_tradeloan']+$row['ru_insurance']));
        $total_qty += $row['ru_quantity'];
        $total_value += $row['ru_value'];
        $total_net += $net;
        // escape คอมมา/เครื่องหมายคำพูด ตามกติกา CSV
        $cols = [
            $row['ru_id'],
            thai_date($row['ru_date']),
            $row['ru_fullname'],
            $row['ru_quantity'],
            $row['ru_value'],
            $net
        ];
        foreach ($cols as &$c) {
            $c = (string)$c;
            if (strpos($c, '"') !== false || strpos($c, ',') !== false || strpos($c, "\n") !== false || strpos($c, "\r") !== false) {
                $c = '"' . str_replace('"', '""', $c) . '"';
            }
        }
        unset($c);
        $lines[] = implode(',', $cols);
    }
    // แถวรวมทั้งสิ้น
    $lines[] = ',,รวมทั้งสิ้น,' . $total_qty . ',' . $total_value . ',' . $total_net;

    $content = implode("\r\n", $lines) . "\r\n";

    // เขียน BOM ของ UTF-16LE แล้วตามด้วยเนื้อหาแปลงเป็น UTF-16LE
    echo "\xFF\xFE"; // BOM
    echo mb_convert_encoding($content, 'UTF-16LE', 'UTF-8');
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

$style = 'body { font-family: THSarabunNew, DejaVu Sans, sans-serif; font-size: 22px; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ccc; padding: 6px; } th { background: #f1f1f1; }';
$html = '<!doctype html><html lang="th"><head><meta charset="UTF-8"><style>'.$style.'</style></head><body>';
$html .= '<h2 style="text-align:center">รายงานข้อมูลรับซื้อยาง';
if ($pr_date) {
    $html .= '<br>รอบวันที่ '.e(thai_date_format($pr_date));
} 
if ($scope === 'year') $html .= 'ประจำปี '.e($year+543);
elseif ($scope === 'month') $html .= 'ประจำเดือน '.e($month).'/'.e($year+543);
elseif ($scope === 'period') $html .= 'ช่วงวันที่ '.e(thai_date($period_start)).' ถึง '.e(thai_date($period_end));
$html .= '</h2>';
$html .= '<table><thead><tr><th>ID</th><th>วันที่บันทึก</th><th>ชื่อ-สกุล</th><th>ปริมาณ(กก.)</th><th>ยอดเงินรวม</th><th>ยอดรับสุทธิ</th></tr></thead><tbody>';

$total_qty = 0;
$total_value = 0;
$total_net = 0;
foreach ($rows as $row) {
    $net = $row['ru_netvalue'] ?? ($row['ru_value'] - ($row['ru_hoon']+$row['ru_loan']+$row['ru_shortdebt']+$row['ru_deposit']+$row['ru_tradeloan']+$row['ru_insurance']));
    $total_qty += $row['ru_quantity'];
    $total_value += $row['ru_value'];
    $total_net += $net;
    $html .= '<tr>';
    $html .= '<td>'.e($row['ru_id']).'</td>';
    $html .= '<td>'.e(thai_date($row['ru_date'])).'</td>';
    $html .= '<td>'.e($row['ru_fullname']).'</td>';
    $html .= '<td style="text-align:right">'.nf($row['ru_quantity']).'</td>';
    $html .= '<td style="text-align:right">'.nf($row['ru_value']).'</td>';
    $html .= '<td style="text-align:right">'.nf($net).'</td>';
    $html .= '</tr>';
}
// แถวรวมทั้งสิ้น
$html .= '<tr>';
$html .= '<td colspan="3" style="text-align:right;font-weight:bold">รวมทั้งสิ้น</td>';
$html .= '<td style="text-align:right;font-weight:bold">'.nf($total_qty).'</td>';
$html .= '<td style="text-align:right;font-weight:bold">'.nf($total_value).'</td>';
$html .= '<td style="text-align:right;font-weight:bold">'.nf($total_net).'</td>';
$html .= '</tr>';
$html .= '</tbody></table>';
$html .= '</body></html>';

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('rubbers_export.pdf', ['Attachment' => false]);
exit;

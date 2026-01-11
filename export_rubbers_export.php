<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';

// รับค่าพารามิเตอร์
$type = $_GET['export_type'] ?? 'pdf';
$pr_date = $_GET['pr_date'] ?? null;
$scope = $_GET['export_scope'] ?? 'year';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y'));
$month = isset($_GET['month']) ? (int)$_GET['month'] : (isset($_POST['month']) ? (int)$_POST['month'] : (int)date('n'));
$period_start = $_GET['period_start'] ?? null;
$period_end = $_GET['period_end'] ?? null;

$user = current_user();
$user_id = $user['user_id'] ?? null; // แก้ key ให้ถูกต้อง
$is_admin = is_admin();

// สร้าง query ตามช่วงข้อมูล (ใช้ ru_date แทน ru_savedate)
$where = '';
$params = [];
$types = '';
if ($pr_date) {
    $where = 'WHERE DATE(ru_date) = ?';
    $params[] = $pr_date;
    $types .= 's';
    if (!$is_admin && $user_id !== null) {
        $where .= ' AND ru_saveby = ?';
        $params[] = $user_id;
        $types .= 'i';
    }
} else {
    if ($scope === 'year') {
        $where = 'WHERE YEAR(ru_date) = ?';
        $params[] = $year;
        $types .= 'i';
        if (!$is_admin && $user_id !== null) {
            $where .= ' AND ru_saveby = ?';
            $params[] = $user_id;
            $types .= 'i';
        }
    } elseif ($scope === 'month') {
        $where = 'WHERE YEAR(ru_date) = ? AND MONTH(ru_date) = ?';
        $params[] = $year;
        $params[] = $month;
        $types .= 'ii';
        if (!$is_admin && $user_id !== null) {
            $where .= ' AND ru_saveby = ?';
            $params[] = $user_id;
            $types .= 'i';
        }
    } elseif ($scope === 'period' && $period_start && $period_end) {
        $where = 'WHERE DATE(ru_date) BETWEEN ? AND ?';
        $params[] = $period_start;
        $params[] = $period_end;
        $types .= 'ss';
        if (!$is_admin && $user_id !== null) {
            $where .= ' AND ru_saveby = ?';
            $params[] = $user_id;
            $types .= 'i';
        }
    }
}

$db = db();
// ใช้รูปแบบสรุปคล้าย export_total_sale: รวมตามชื่อ-สกุล และเลขที่สมาชิก
$sql = "SELECT ru_fullname, ru_number,
           SUM(ru_quantity) AS total_quantity,
           SUM(ru_value)    AS total_value,
           SUM(ru_netvalue) AS total_netvalue
    FROM tbl_rubber $where
    GROUP BY ru_fullname, ru_number
    ORDER BY ru_fullname";
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
    // ส่งออกเป็น Excel (HTML Table) ด้วย UTF-8 + BOM ให้ Excel แสดงภาษาไทยถูกต้อง
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rubbers_export.xls"');

    // BOM สำหรับ UTF-8
    echo "\xEF\xBB\xBF";

    $style = 'body { font-family: THSarabunNew, Sarabun, DejaVu Sans, Tahoma, sans-serif; font-size: 12pt; } '
           . 'table { width: 100%; border-collapse: collapse; } '
           . 'th, td { border: 1px solid #ccc; padding: 6px; } '
           . 'th { background: #f1f1f1; } '
           . '.t-right { text-align: right; }';

    $html = '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><style>' . $style . '</style></head><body>';
    $html .= '<h3 style="text-align:center">รายงานข้อมูลรับซื้อยาง';
    if ($pr_date) {
        $html .= '<br>รอบวันที่ ' . e(thai_date_format($pr_date));
    }
    if ($scope === 'year') $html .= ' ประจำปี ' . e($year + 543);
    elseif ($scope === 'month') $html .= ' ประจำเดือน ' . e($month) . '/' . e($year + 543);
    elseif ($scope === 'period') $html .= ' ช่วงวันที่ ' . e(thai_date($period_start)) . ' ถึง ' . e(thai_date($period_end));
    $html .= '</h3>';

        $html .= '<table><thead><tr>'
            . '<th>ชื่อ-สกุล</th>'
            . '<th>เลขที่สมาชิก</th>'
            . '<th>ปริมาณรวม (กก.)</th>'
            . '<th>ยอดเงินรวม</th>'
            . '<th>รับสุทธิรวม</th>'
            . '</tr></thead><tbody>';

        $total_qty = 0; $total_value = 0; $total_net = 0;
        foreach ($rows as $row) {
          $qty = (float)($row['total_quantity'] ?? 0);
          $val = (float)($row['total_value'] ?? 0);
          $net = (float)($row['total_netvalue'] ?? 0);
          $total_qty += $qty;
          $total_value += $val;
          $total_net += $net;
          $html .= '<tr>'
              . '<td>' . e($row['ru_fullname']) . '</td>'
              . '<td>' . e($row['ru_number']) . '</td>'
              . '<td class="t-right">' . nf($qty) . '</td>'
              . '<td class="t-right">' . nf($val) . '</td>'
              . '<td class="t-right">' . nf($net) . '</td>'
              . '</tr>';
        }
        $html .= '<tr>'
            . '<td colspan="2" class="t-right" style="font-weight:bold">รวมทั้งสิ้น</td>'
            . '<td class="t-right" style="font-weight:bold">' . nf($total_qty) . '</td>'
            . '<td class="t-right" style="font-weight:bold">' . nf($total_value) . '</td>'
            . '<td class="t-right" style="font-weight:bold">' . nf($total_net) . '</td>'
            . '</tr>';

    $html .= '</tbody></table></body></html>';

    echo $html;
    exit;
}

// ส่วน PDF ใช้ Dompdf เฉพาะเมื่อขอ export เป็น PDF
// หมายเหตุ: การสร้าง PDF จากข้อมูลจำนวนมากจะใช้หน่วยความจำสูง
// เพื่อหลีกเลี่ยง Fatal error บนโฮสต์ที่จำกัด memory (เช่น 256MB)
// ถ้าจำนวนแถวมากเกินไป จะให้ผู้ใช้ใช้โหมด Excel แทน

$maxPdfRows = 800; // กำหนดจำนวนแถวสูงสุดที่ยอมให้ทำ PDF ได้อย่างปลอดภัย
if ($type === 'pdf' && count($rows) > $maxPdfRows) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="th"><head><meta charset="UTF-8"><title>ส่งออก PDF ไม่สำเร็จ</title></head><body style="font-family:Tahoma,Arial,sans-serif;font-size:14px;">';
    echo '<h3>ไม่สามารถส่งออกเป็น PDF ทั้งหมดได้</h3>';
    echo '<p>มีข้อมูลจำนวน <strong>' . number_format(count($rows)) . '</strong> รายการ ซึ่งมากเกินไปสำหรับการสร้าง PDF บนเซิร์ฟเวอร์ (จำกัดหน่วยความจำ 256MB).</p>';
    echo '<p>แนะนำให้:</p><ul>';
    echo '<li>ใช้การส่งออกแบบ <strong>Excel</strong> (เมนูเดิม) เพื่อได้ข้อมูลครบทุกแถว</li>';
    echo '<li>หรือเลือกช่วงวันที่/ปี/เดือนให้แคบลง แล้วลองส่งออก PDF อีกครั้ง</li>';
    echo '</ul>';
    echo '</body></html>';
    exit;
}

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

$options = new Options();
$options->set('isRemoteEnabled', false);
// ปิด HTML5 parser เพื่อประหยัดหน่วยความจำ
$options->set('isHtml5ParserEnabled', false);
$options->set('chroot', __DIR__);
// ปิด font subsetting ทั้งสอง flag
$options->set('isFontSubsettingEnabled', false);
$options->set('enable_font_subsetting', false);
$defaultFont = 'THSarabunNew';
$options->set('defaultFont', $defaultFont);
$dompdf = new Dompdf($options);

// CSS แบบเรียบง่าย และฟอนต์เล็กลงเพื่อลดภาระในการจัดหน้า
$style = 'body { font-family: THSarabunNew, DejaVu Sans, sans-serif; font-size: 14px; } '
    . 'table { width: 100%; border-collapse: collapse; font-size: 12px; } '
    . 'th, td { border: 1px solid #ccc; padding: 3px; } '
    . 'th { background: #f1f1f1; }';
$html = '<!doctype html><html lang="th"><head><meta charset="UTF-8"><style>'.$style.'</style></head><body>';
$html .= '<h2 style="text-align:center">รายงานข้อมูลรับซื้อยาง (สรุปยอดรวมต่อสมาชิก)';
if ($pr_date) {
    $html .= '<br>รอบวันที่ '.e(thai_date_format($pr_date));
}
if ($scope === 'year') $html .= 'ประจำปี '.e($year+543);
elseif ($scope === 'month') $html .= 'ประจำเดือน '.e($month).'/'.e($year+543);
elseif ($scope === 'period') $html .= 'ช่วงวันที่ '.e(thai_date($period_start)).' ถึง '.e(thai_date($period_end));
$html .= '</h2>';
$html .= '<table><thead><tr><th>ชื่อ-สกุล</th><th>เลขที่สมาชิก</th><th>ปริมาณรวม (กก.)</th><th>ยอดเงินรวม</th><th>รับสุทธิรวม</th></tr></thead><tbody>';
$total_qty = 0;
$total_value = 0;
$total_net = 0;
foreach ($rows as $row) {
    $qty = (float)($row['total_quantity'] ?? 0);
    $val = (float)($row['total_value'] ?? 0);
    $net = (float)($row['total_netvalue'] ?? 0);
    $total_qty += $qty;
    $total_value += $val;
    $total_net += $net;
    $html .= '<tr>';
    $html .= '<td>'.e($row['ru_fullname']).'</td>';
    $html .= '<td>'.e($row['ru_number']).'</td>';
    $html .= '<td style="text-align:right">'.nf($qty).'</td>';
    $html .= '<td style="text-align:right">'.nf($val).'</td>';
    $html .= '<td style="text-align:right">'.nf($net).'</td>';
    $html .= '</tr>';
}
// แถวรวมทั้งสิ้น
$html .= '<tr>';
$html .= '<td colspan="2" style="text-align:right;font-weight:bold">รวมทั้งสิ้น</td>';
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

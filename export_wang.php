<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_admin();

@set_time_limit(120);
@ini_set('memory_limit', '512M');
mb_internal_encoding('UTF-8');

$db = db();

function fail_export(string $message, int $code = 500): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Export Wang Error</title>';
    echo '<div style="font-family:Arial,sans-serif;padding:16px">';
    echo '<h3>ไม่สามารถส่งออกข้อมูลวางยาง</h3>';
    echo '<p style="color:#b00020">' . e($message) . '</p>';
    echo '</div>';
    exit;
}

function valid_date(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
}

function selected(string $actual, string $expected): string {
    return $actual === $expected ? ' selected' : '';
}

function attr_selected($actual, $expected): string {
    return (string)$actual === (string)$expected ? ' selected' : '';
}

function export_xls_header(string $filename): void {
    if (ob_get_length()) {
        @ob_end_clean();
    }
    header('Content-Type: application/vnd.ms-excel; charset=UTF-16LE');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

function xh(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function xml_cell(string $type, string $data, string $styleId = ''): string {
    $attrs = $styleId !== '' ? ' ss:StyleID="' . xh($styleId) . '"' : '';
    return '<Cell' . $attrs . '><Data ss:Type="' . xh($type) . '">' . xh($data) . '</Data></Cell>';
}

function xml_row(array $cells): string {
    return '<Row>' . implode('', $cells) . "</Row>\n";
}

$format = strtolower(trim((string)($_GET['format'] ?? '')));
$date = valid_date($_GET['date'] ?? null) ?? date('Y-m-d');
$lane = trim((string)($_GET['lane'] ?? 'all'));
$memberId = (int)($_GET['member_id'] ?? 0);
$memberQuery = trim((string)($_GET['member_query'] ?? ''));

if ($lane !== 'all' && !in_array((int)$lane, [1, 2, 3, 4], true)) {
    $lane = 'all';
}

$memberOptions = [];
$memberSql = "
    SELECT mem_id, mem_number, mem_fullname, COALESCE(mem_group, '') AS mem_group
    FROM tbl_member
    ORDER BY mem_fullname ASC, mem_number ASC
";
if ($memberResult = $db->query($memberSql)) {
    while ($row = $memberResult->fetch_assoc()) {
        $memberOptions[] = $row;
    }
    $memberResult->free();
}

$memberName = '';
foreach ($memberOptions as $member) {
    if ((int)$member['mem_id'] === $memberId) {
        $memberName = trim((string)$member['mem_fullname']);
        break;
    }
}
if ($memberId <= 0 && $memberQuery !== '') {
    $like = '%' . $memberQuery . '%';
    $stmt = $db->prepare("
        SELECT mem_id, mem_fullname
        FROM tbl_member
        WHERE mem_fullname LIKE ? OR mem_number LIKE ? OR mem_group LIKE ? OR mem_class LIKE ?
        ORDER BY mem_fullname ASC, mem_number ASC
        LIMIT 2
    ");
    if ($stmt) {
        $stmt->bind_param('ssss', $like, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        $matched = [];
        while ($row = $res->fetch_assoc()) {
            $matched[] = $row;
        }
        $stmt->close();
        if (count($matched) === 1) {
            $memberId = (int)$matched[0]['mem_id'];
            $memberName = trim((string)$matched[0]['mem_fullname']);
        }
    }
}

$conds = [];
$types = '';
$binds = [];

$conds[] = 'w.wang_date = ?';
$types .= 's';
$binds[] = $date;

if ($lane !== 'all') {
    $conds[] = 'w.wang_lan = ?';
    $types .= 's';
    $binds[] = $lane;
}

if ($memberId > 0) {
    $conds[] = 'w.wang_mid = ?';
    $types .= 'i';
    $binds[] = $memberId;
}

$sql = "
    SELECT
        w.wang_id,
        w.wang_date,
        w.wang_mid,
        w.wang_group,
        w.wang_name,
        w.wang_sack,
        w.wang_weight,
        w.wang_lan,
        COALESCE(w.wang_note, '') AS wang_note,
        w.wang_status,
        w.wang_saveby,
        w.wang_savedate,
        COALESCE(m.mem_number, '') AS mem_number,
        COALESCE(m.mem_fullname, w.wang_name) AS member_name,
        COALESCE(m.mem_group, w.wang_group) AS member_group
    FROM tbl_wangyang w
    LEFT JOIN tbl_member m ON m.mem_id = w.wang_mid
";

if ($conds) {
    $sql .= ' WHERE ' . implode(' AND ', $conds);
}
$sql .= ' ORDER BY w.wang_lan ASC, member_name ASC, w.wang_savedate ASC, w.wang_id ASC';

$rows = [];
if ($conds) {
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        fail_export('เตรียมคำสั่ง SQL ไม่สำเร็จ: ' . $db->error);
    }
    $stmt->bind_param($types, ...$binds);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $db->query($sql);
    $stmt = null;
}

if (!$result) {
    fail_export('ดึงข้อมูลไม่สำเร็จ: ' . $db->error);
}

while ($row = $result->fetch_assoc()) {
    $row['wang_sack'] = (float)($row['wang_sack'] ?? 0);
    $rows[] = $row;
}
$result->free();
if (!empty($stmt)) {
    $stmt->close();
}

$summary = [
    'count' => count($rows),
    'bags' => 0.0,
    'members' => [],
    'lanes' => [],
];

foreach ($rows as $row) {
    $summary['bags'] += (float)($row['wang_sack'] ?? 0);
    $memberKey = trim((string)($row['wang_mid'] ?? 0)) !== '0'
        ? ('id:' . (int)$row['wang_mid'])
        : ('name:' . trim((string)$row['member_name']));
    $summary['members'][$memberKey] = true;
    $summary['lanes'][(string)($row['wang_lan'] ?? '')] = true;
}

$memberCount = count($summary['members']);
$laneCount = count(array_filter(array_keys($summary['lanes']), static fn($v) => $v !== ''));

$title = 'รายงานข้อมูลวางยาง';
$subtitleParts = [
    'วันที่ ' . thai_date_format($date),
];
if ($lane !== 'all') {
    $subtitleParts[] = 'ลาน ' . $lane;
}
if ($memberId > 0) {
    $subtitleParts[] = 'สมาชิก ' . ($memberName !== '' ? $memberName : ('#' . $memberId));
}
$subtitle = implode(' | ', $subtitleParts);

if ($format === 'pdf' || $format === 'excel') {
    $filters = [
        'date' => $date,
    'lane' => $lane,
    'member_id' => $memberId,
    'member_name' => $memberName,
    'member_query' => $memberQuery,
];

    if ($format === 'pdf') {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            fail_export('ไม่พบ vendor/autoload.php');
        }
        require_once $autoload;
        if (!class_exists('Dompdf\\Dompdf')) {
            fail_export('ไม่พบ Dompdf โปรดติดตั้งแพ็กเกจ dompdf/dompdf');
        }

        $style = '
            @import url("https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap");
            @font-face { font-family: "Sarabun"; font-style: normal; font-weight: 400; src: url("assets/fonts/Sarabun-Regular.ttf") format("truetype"); }
            @font-face { font-family: "Sarabun"; font-style: normal; font-weight: 700; src: url("assets/fonts/Sarabun-Bold.ttf") format("truetype"); }
            @page { margin: 10px 12px; }
            body { font-family: "Sarabun", DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.24; color: #173d26; }
            h1,h2,p { margin: 0; }
            .topbar { width: 100%; margin-bottom: 6px; }
            .topbar td { border: 0; padding: 0; vertical-align: top; }
            .title { font-size: 19px; font-weight: 700; line-height: 1.15; }
            .subtitle { font-size: 11px; color: #4b5563; margin-top: 1px; }
            .export-by { text-align: right; font-size: 10.5px; color: #6b7280; white-space: nowrap; }
            .summary-box { margin: 5px 2px 6px; }
            .summary { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; }
            .summary th { width: 25%; border: 1.2px solid #b8e2c2; background: #f0fdf4; padding: 3px 7px; text-align: left; font-size: 10.5px; font-weight: 400; color: #4b5563; line-height: 1.1; }
            .summary td { width: 25%; border: 1.2px solid #b8e2c2; background: #f0fdf4; padding: 3px 7px 4px; font-size: 14.5px; font-weight: 700; color: #14532d; line-height: 1.1; }
            .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12px; line-height: 1.18; }
            .data-table th { background: #dcfce7; border: 1px solid #c7ead0; padding: 3px 4px; text-align: left; font-weight: 700; }
            .data-table td { border: 1px solid #e5f3ea; padding: 2px 4px; vertical-align: top; }
            .col-date { width: 11%; }
            .col-lane { width: 6%; }
            .col-number { width: 10%; }
            .col-name { width: 25%; }
            .col-group { width: 16%; }
            .col-bags { width: 7%; }
            .col-saveby { width: 10%; }
            .col-time { width: 15%; }
            .nowrap { white-space: nowrap; }
            .num { text-align: right; white-space: nowrap; }
            .muted { color: #6b7280; }
            .total-row td { font-weight: 700; background: #f8fafc; }
            .footer { margin-top: 5px; font-size: 10.5px; color: #6b7280; }
        ';

        $html = '<!doctype html><html lang="th"><head><meta charset="utf-8"><style>' . $style . '</style></head><body>';
        $html .= '<table class="topbar"><tr>';
        $html .= '<td><div class="title">' . e($title) . '</div><div class="subtitle">' . e($subtitle) . '</div></td>';
        $html .= '<td class="export-by">ส่งออกโดยผู้ดูแลระบบ<br>พิมพ์เมื่อ ' . e(date('Y-m-d H:i')) . '</td>';
        $html .= '</tr></table>';
        $html .= '<div class="summary-box"><table class="summary">';
        $html .= '<tr><th>รายการ</th><th>กระสอบรวม</th><th>สมาชิก</th><th>ลาน</th></tr>';
        $html .= '<tr><td>' . number_format($summary['count']) . '</td><td>' . number_format($summary['bags']) . '</td><td>' . number_format($memberCount) . '</td><td>' . number_format($laneCount) . '</td></tr>';
        $html .= '</table></div>';
        $html .= '<table class="data-table"><thead><tr>';
        $html .= '<th class="col-date">วันที่</th><th class="col-lane">ลาน</th><th class="col-number">เลขที่สมาชิก</th><th class="col-name">ชื่อสมาชิก</th><th class="col-group">กลุ่ม</th><th class="col-bags num">กระสอบ</th>';
        $html .= '</tr></thead><tbody>';

        if (!$rows) {
            $html .= '<tr><td colspan="6" class="muted">ไม่พบข้อมูลตามเงื่อนไข</td></tr>';
        } else {
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td class="nowrap">' . e(thai_date_format((string)$row['wang_date'])) . '</td>';
                $html .= '<td class="nowrap">' . e('ลาน ' . (string)$row['wang_lan']) . '</td>';
                $html .= '<td class="nowrap">' . e((string)($row['mem_number'] ?: '-')) . '</td>';
                $html .= '<td>' . e((string)$row['member_name']) . '</td>';
                $html .= '<td>' . e((string)$row['member_group']) . '</td>';
                $html .= '<td class="num">' . number_format((float)$row['wang_sack'], 2) . '</td>';
                $html .= '</tr>';
            }
            $html .= '<tr class="total-row">';
            $html .= '<td colspan="5" class="num">รวม</td>';
            $html .= '<td class="num">' . number_format($summary['bags']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">รายงานนี้สร้างจากระบบวางยางตามเงื่อนไขที่เลือก</div>';
        $html .= '</body></html>';

        $options = new Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', __DIR__);
        $options->set('defaultFont', 'Sarabun');
        $options->set('fontCache', false);
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');

        try {
            $dompdf->render();
        } catch (Throwable $e) {
            fail_export('DomPDF render ล้มเหลว: ' . $e->getMessage());
        }

        if (ob_get_length()) {
            @ob_end_clean();
        }

        $filename = 'wang_' . $date . ($lane !== 'all' ? '_lane' . $lane : '_alllanes');
        if ($memberId > 0) {
            $filename .= '_member' . $memberId;
        }
        $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
        exit;
    }

    $filename = 'wang_' . $date . ($lane !== 'all' ? '_lane' . $lane : '_alllanes');
    if ($memberId > 0) {
        $filename .= '_member' . $memberId;
    }
    $filename .= '.xls';
    export_xls_header($filename);
    ob_start();

    echo "<?xml version=\"1.0\" encoding=\"UTF-16LE\"?>\n";
echo "
<?mso-application progid=\"Excel.Sheet\"?>\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
  echo '<Styles>';
    echo '<Style ss:ID="sTitle">
    <Font ss:Bold="1" ss:Size="18"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    </Style>';
    echo '<Style ss:ID="sSub">
    <Font ss:Size="14"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    </Style>';
    echo '<Style ss:ID="sBorder">
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    </Style>';
    echo '<Style ss:ID="sHeader">
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:Bold="1" ss:Size="14"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
    </Style>';
    echo '<Style ss:ID="sText">
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:Size="14"/><Alignment ss:Vertical="Center"/>
    </Style>';
    echo '<Style ss:ID="sCenter">
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:Size="14"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    </Style>';
    echo '<Style ss:ID="sNum">
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:Size="14"/><NumberFormat ss:Format="0"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
    </Style>';
    echo '<Style ss:ID="sTotalText">
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:Bold="1" ss:Size="14"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#FFF9C4" ss:Pattern="Solid"/>
    </Style>';
    echo '<Style ss:ID="sTotalNum">
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
      <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:Bold="1" ss:Size="14"/><NumberFormat ss:Format="0"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Interior ss:Color="#FFF9C4" ss:Pattern="Solid"/>
    </Style>';
    echo '</Styles>';
  echo '<Worksheet ss:Name="วางยาง">
    <Table ss:ExpandedColumnCount="9" ss:ExpandedRowCount="' . (4 + count($rows) + 1) . '">';
      echo '<Column ss:AutoFitWidth="1" ss:Width="80"/>
      <Column ss:AutoFitWidth="1" ss:Width="50"/>
      <Column ss:AutoFitWidth="1" ss:Width="80"/>
      <Column ss:AutoFitWidth="1" ss:Width="160"/>
      <Column ss:AutoFitWidth="1" ss:Width="120"/>
      <Column ss:AutoFitWidth="1" ss:Width="60"/>
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="120"/>
      <Column ss:AutoFitWidth="1" ss:Width="140"/>' . "\n";
      echo xml_row([xml_cell('String', $title, 'sTitle')]);
      echo xml_row([xml_cell('String', $subtitle, 'sSub')]);
      echo xml_row([xml_cell('String', 'รายการ: ' . number_format($summary['count']) . ' | กระสอบรวม: ' .
      number_format($summary['bags']) . ' | สมาชิก: ' . number_format($memberCount) . ' | ลาน: ' .
      number_format($laneCount), 'sSub')]);
      echo xml_row([
      xml_cell('String', 'วันที่', 'sHeader'),
      xml_cell('String', 'ลาน', 'sHeader'),
      xml_cell('String', 'เลขที่สมาชิก', 'sHeader'),
      xml_cell('String', 'ชื่อสมาชิก', 'sHeader'),
      xml_cell('String', 'กลุ่ม', 'sHeader'),
      xml_cell('String', 'กระสอบ', 'sHeader'),
      xml_cell('String', 'หมายเหตุ', 'sHeader'),
      xml_cell('String', 'บันทึกโดย', 'sHeader'),
      xml_cell('String', 'วันที่บันทึก', 'sHeader'),
      ]);

      if (!$rows) {
      echo xml_row([xml_cell('String', 'ไม่พบข้อมูลตามเงื่อนไข', 'sText')]);
      } else {
      foreach ($rows as $row) {
      echo xml_row([
      xml_cell('String', thai_date_format((string)$row['wang_date']), 'sText'),
      xml_cell('String', 'ลาน ' . (string)$row['wang_lan'], 'sCenter'),
      xml_cell('String', (string)($row['mem_number'] ?: '-'), 'sCenter'),
      xml_cell('String', (string)$row['member_name'], 'sText'),
      xml_cell('String', (string)$row['member_group'], 'sText'),
      xml_cell('Number', number_format((float)$row['wang_sack'], 2, '.', ''), 'sNum'),
      xml_cell('String', (string)($row['wang_note'] ?? ''), 'sText'),
      xml_cell('String', (string)($row['wang_saveby'] ?: '-'), 'sText'),
      xml_cell('String', !empty($row['wang_savedate']) ? thai_date_format(substr((string)$row['wang_savedate'], 0, 10))
      . ' ' . substr((string)$row['wang_savedate'], 11, 5) : '-', 'sText'),
      ]);
      }
      echo xml_row([
      xml_cell('String', 'รวม', 'sTotalText'),
      xml_cell('String', '', 'sTotalText'),
      xml_cell('String', '', 'sTotalText'),
      xml_cell('String', '', 'sTotalText'),
      xml_cell('String', '', 'sTotalText'),
      xml_cell('Number', number_format($summary['bags'], 0, '.', ''), 'sTotalNum'),
      xml_cell('String', '', 'sTotalText'),
      xml_cell('String', '', 'sTotalText'),
      xml_cell('String', '', 'sTotalText'),
      ]);
      }

      echo '</Table>
  </Worksheet>
</Workbook>';
$xmlContent = ob_get_clean();
echo "\xFF\xFE";
echo mb_convert_encoding($xmlContent, 'UTF-16LE', 'UTF-8');
exit;
}

$cu = current_user();
$today = date('Y-m-d');
$adminName = (string)($cu['user_fullname'] ?? $cu['user_username'] ?? '');

?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ส่งออกข้อมูลวางยาง</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <style>
  html,
  body {
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    font-size: 16px;
    font-weight: 300;
    background: linear-gradient(180deg, #f4faf6 0%, #f8faf9 42%, #ffffff 100%);
    color: #173d26;
  }

  .container,
  .card,
  .table,
  .form-control,
  .form-select,
  .form-label,
  .btn,
  .btn-sm,
  .nav-link,
  .alert,
  .badge {
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    font-size: 16px;
    font-weight: 300;
  }

  .small,
  .form-text {
    font-size: 14px !important;
    font-weight: 300 !important;
  }

  .export-shell {
    max-width: 1140px;
  }

  .card {
    border-color: rgba(47, 110, 67, 0.12);
    border-radius: .9rem;
  }

  .dashboard-hero {
    border-color: #c7dfcf !important;
    background: linear-gradient(135deg, #ffffff 0%, #f3fbf5 100%);
  }

  .card-title {
    color: #245c38;
    font-weight: 600;
    line-height: 1.35;
  }

  .page-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .5rem;
  }

  .dashboard-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .75rem;
  }

  .dashboard-summary-item {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
    border: 1px solid #d7f3de;
    border-radius: 1rem;
    background: #f8fdf8;
    padding: 1rem;
    min-height: 92px;
    box-shadow: 0 10px 24px rgba(16, 24, 40, 0.04);
  }

  .dashboard-summary-label {
    display: flex;
    align-items: center;
    gap: .35rem;
    font-size: .85rem;
    color: #15803d;
    margin-bottom: .2rem;
  }

  .dashboard-summary-value {
    font-size: 1.45rem;
    font-weight: 700;
    color: #14532d;
    line-height: 1.2;
  }

  .dashboard-summary-icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: .8rem;
    background: #e8f4eb;
    color: #2f6e43;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .dashboard-summary-icon .lucide {
    width: 1.15rem;
    height: 1.15rem;
  }

  .export-form-card .card-body,
  .condition-card .card-body {
    padding: 1.35rem;
  }

  .field-panel {
    padding: .85rem;
    border: 1px solid #edf5ef;
    border-radius: .85rem;
    background: #fbfefc;
  }

  .field-panel .form-label {
    color: #245c38;
    font-weight: 600;
  }

  .form-control,
  .form-select {
    min-height: 44px;
    border-color: #d7eade;
  }

  .form-control:focus,
  .form-select:focus {
    border-color: #68ae7a;
    box-shadow: 0 0 0 .2rem rgba(25, 135, 84, .12);
  }

  .btn,
  .btn-sm {
    min-height: 40px;
    font-weight: 600;
  }

  .suggest-wrap {
    position: relative;
  }

  .suggest-list {
    position: absolute;
    top: calc(100% + .25rem);
    left: 0;
    right: 0;
    z-index: 1050;
    max-height: 16rem;
    overflow: auto;
    border: 1px solid #d7eade;
    border-radius: .75rem;
    background: #fff;
    box-shadow: 0 12px 28px rgba(16, 24, 40, .1);
  }

  .suggest-list.hidden {
    display: none;
  }

  .suggest-item {
    display: block;
    width: 100%;
    padding: .75rem .9rem;
    border: 0;
    border-bottom: 1px solid #eef5f1;
    background: #fff;
    text-align: left;
    color: #14532d;
    cursor: pointer;
  }

  .suggest-item:last-child {
    border-bottom: 0;
  }

  .suggest-item:hover,
  .suggest-item:focus {
    background: #f0fdf4;
    outline: none;
  }

  .suggest-main {
    font-weight: 700;
  }

  .suggest-sub {
    font-size: .85rem;
    color: #64748b;
  }

  .filter-chip {
    border: 1px solid #bbf7d0;
    border-radius: 999px;
    background: #fff;
    color: #166534;
    padding: .35rem .65rem;
    font-size: .88rem;
    white-space: nowrap;
  }

  .export-tip {
    border-left: 4px solid #68ae7a;
    background: #f8fdf8;
  }

  .condition-list {
    display: grid;
    gap: .75rem;
    margin-bottom: 1rem;
  }

  .condition-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .8rem .9rem;
    border: 1px solid #edf5ef;
    border-radius: .85rem;
    background: #fbfefc;
  }

  .condition-item i {
    color: #2f6e43;
    flex: 0 0 auto;
  }

  .condition-label {
    font-size: .82rem;
    color: #6b7e70;
    margin-bottom: .05rem;
  }

  .condition-value {
    color: #173d26;
    font-weight: 600;
    overflow-wrap: anywhere;
  }

  .icon-box {
    width: 2.7rem;
    height: 2.7rem;
    border-radius: .85rem;
    background: #e8f4eb;
    color: #2f6e43;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .btn .lucide,
  .btn-sm .lucide,
  .card-title .lucide {
    width: 1rem;
    height: 1rem;
  }

  @media (max-width: 992px) {
    .container.my-4 {
      padding-left: 1rem;
      padding-right: 1rem;
    }

    .dashboard-summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 768px) {
    .container.my-4 {
      margin-top: 1rem !important;
      margin-bottom: 1rem !important;
      padding-left: .5rem;
      padding-right: .5rem;
    }

    .page-heading {
      flex-direction: column;
      align-items: stretch !important;
    }

    .page-actions {
      flex-direction: column;
      align-items: stretch;
    }

    .page-actions .btn,
    .export-buttons .btn {
      width: 100%;
      justify-content: center;
    }

    .card-body {
      padding: 1rem;
    }

    .dashboard-summary-item {
      min-height: 84px;
    }
  }

  @media (max-width: 576px) {
    .dashboard-summary-grid {
      grid-template-columns: 1fr;
    }

    .card-title {
      font-size: 1rem;
    }
  }
  </style>
</head>

<body>
  <main class="container export-shell my-4">
    <div
      class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-3 page-heading">
      <div>
        <h1 class="h4 mb-0"><i data-lucide="download" class="me-2" aria-hidden="true"></i>ส่งออกข้อมูลวางยาง</h1>
        <div class="small text-muted">สวัสดี <?php echo e($adminName); ?> (Administrator)</div>
      </div>
      <div class="page-actions">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
          <i data-lucide="gauge" aria-hidden="true"></i><span>Dashboard</span>
        </a>
        <a href="wang_main.php" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1">
          <i data-lucide="package-check" aria-hidden="true"></i><span>วางยาง</span>
        </a>
        <a href="wang_summary.php" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1">
          <i data-lucide="clipboard-list" aria-hidden="true"></i><span>สรุปวางยาง</span>
        </a>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="card shadow-sm dashboard-hero">
          <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
              <div class="d-flex align-items-start gap-3">
                <span class="icon-box"><i data-lucide="file-down" aria-hidden="true"></i></span>
                <div>
                  <h2 class="h5 mb-1">เครื่องมือส่งออกข้อมูลวางยาง</h2>
                  <div class="text-muted">เลือกวันที่ ลาน และสมาชิกรายคน แล้วส่งออกเป็น PDF หรือ Excel</div>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                  <?php echo e(thai_date_format($date)); ?>
                </span>
                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                  <?php echo $lane === 'all' ? 'ทุกลาน' : e('ลาน ' . $lane); ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="dashboard-summary-grid">
          <div class="dashboard-summary-item">
            <div>
              <div class="dashboard-summary-label">รายการทั้งหมด</div>
              <div class="dashboard-summary-value"><?php echo number_format($summary['count']); ?></div>
            </div>
            <span class="dashboard-summary-icon"><i data-lucide="list-checks" aria-hidden="true"></i></span>
          </div>
          <div class="dashboard-summary-item">
            <div>
              <div class="dashboard-summary-label">กระสอบรวม</div>
              <div class="dashboard-summary-value"><?php echo number_format($summary['bags']); ?></div>
            </div>
            <span class="dashboard-summary-icon"><i data-lucide="package" aria-hidden="true"></i></span>
          </div>
          <div class="dashboard-summary-item">
            <div>
              <div class="dashboard-summary-label">สมาชิก</div>
              <div class="dashboard-summary-value"><?php echo number_format($memberCount); ?></div>
            </div>
            <span class="dashboard-summary-icon"><i data-lucide="users" aria-hidden="true"></i></span>
          </div>
          <div class="dashboard-summary-item">
            <div>
              <div class="dashboard-summary-label">ลานที่มีข้อมูล</div>
              <div class="dashboard-summary-value"><?php echo number_format($laneCount); ?></div>
            </div>
            <span class="dashboard-summary-icon"><i data-lucide="map-pinned" aria-hidden="true"></i></span>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-7">
        <div class="card h-100 shadow-sm export-form-card">
          <div class="card-body p-3 p-md-4">
            <h5 class="card-title"><i data-lucide="sliders-horizontal" class="me-2"
                aria-hidden="true"></i>ตั้งค่าการส่งออก</h5>
            <p class="card-text small text-muted mb-3">กำหนดเงื่อนไขข้อมูลที่ต้องการส่งออก</p>
            <form method="get" class="row g-3">
              <div class="col-12 col-md-6">
                <div class="field-panel h-100">
                  <label class="form-label mb-1" for="date">วันที่</label>
                  <input type="date" id="date" name="date" class="form-control" value="<?php echo e($date); ?>"
                    required>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="field-panel h-100">
                  <label class="form-label mb-1" for="lane">ลาน</label>
                  <select id="lane" name="lane" class="form-select">
                    <option value="all" <?php echo selected($lane, 'all'); ?>>ทุกลาน</option>
                    <option value="1" <?php echo selected($lane, '1'); ?>>ลาน 1</option>
                    <option value="2" <?php echo selected($lane, '2'); ?>>ลาน 2</option>
                    <option value="3" <?php echo selected($lane, '3'); ?>>ลาน 3</option>
                    <option value="4" <?php echo selected($lane, '4'); ?>>ลาน 4</option>
                  </select>
                </div>
              </div>
              <div class="col-12">
                <div class="field-panel">
                  <label class="form-label mb-1" for="member_query">สมาชิก</label>
                  <input type="hidden" id="member_id" name="member_id" value="<?php echo (int)$memberId; ?>">
                  <div class="suggest-wrap">
                    <input type="text" id="member_query" name="member_query" class="form-control" autocomplete="off"
                      placeholder="พิมพ์ชื่อ เลขที่ หรือกลุ่มเพื่อค้นหา"
                      value="<?php echo e($memberQuery !== '' ? $memberQuery : $memberName); ?>">
                    <div id="member_suggest" class="suggest-list hidden"></div>
                  </div>
                  <div class="form-text">เลือกจากรายการแนะนำเพื่อส่งออกเป็นรายบุคคล หรือเว้นว่างเพื่อส่งออกทุกคน</div>
                </div>
              </div>
              <div class="col-12">
                <div class="d-flex flex-column flex-sm-row gap-2 export-buttons">
                  <button type="submit" name="format" value="pdf"
                    class="btn btn-success btn-sm d-inline-flex align-items-center justify-content-center gap-1">
                    <i data-lucide="file-text" aria-hidden="true"></i><span>ส่งออก PDF</span>
                  </button>
                  <button type="submit" name="format" value="excel"
                    class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center gap-1">
                    <i data-lucide="table-2" aria-hidden="true"></i><span>ส่งออก Excel</span>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="card h-100 shadow-sm condition-card">
          <div class="card-body p-3 p-md-4">
            <h5 class="card-title"><i data-lucide="info" class="me-2" aria-hidden="true"></i>เงื่อนไขปัจจุบัน</h5>
            <p class="card-text small text-muted mb-3">ตรวจเงื่อนไขก่อนดาวน์โหลดไฟล์</p>
            <div class="condition-list">
              <div class="condition-item">
                <i data-lucide="calendar-days" aria-hidden="true"></i>
                <div>
                  <div class="condition-label">วันที่</div>
                  <div class="condition-value"><?php echo e(thai_date_format($date)); ?></div>
                </div>
              </div>
              <div class="condition-item">
                <i data-lucide="map-pinned" aria-hidden="true"></i>
                <div>
                  <div class="condition-label">ลาน</div>
                  <div class="condition-value"><?php echo $lane === 'all' ? 'ทุกลาน' : e('ลาน ' . $lane); ?></div>
                </div>
              </div>
              <div class="condition-item">
                <i data-lucide="users" aria-hidden="true"></i>
                <div>
                  <div class="condition-label">สมาชิก</div>
                  <div class="condition-value">
                    <?php echo $memberId > 0 ? e($memberName !== '' ? $memberName : ('#' . (string)$memberId)) : 'ทุกคน'; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="export-tip rounded-3 p-3">
              <div class="fw-semibold text-success mb-1">
                <i data-lucide="circle-help" class="me-1" aria-hidden="true"></i>ส่งออกรายบุคคล
              </div>
              <div class="small text-muted">พิมพ์ชื่อสมาชิกแล้วเลือกจากรายการแนะนำ
                หากต้องการส่งออกทุกคนให้ล้างช่องสมาชิกก่อนส่งออก</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
  <script>
  const memberQueryInput = document.getElementById('member_query');
  const memberIdInput = document.getElementById('member_id');
  const memberSuggest = document.getElementById('member_suggest');
  const exportForm = document.querySelector('form');
  let memberSuggestTimer = null;
  let memberSuggestAbort = null;
  let memberSelected = false;

  function hideMemberSuggest() {
    if (!memberSuggest) return;
    memberSuggest.classList.add('hidden');
    memberSuggest.innerHTML = '';
  }

  function setMemberSelection(member) {
    if (!memberQueryInput || !memberIdInput) return;
    memberIdInput.value = String(member.mem_id || 0);
    memberQueryInput.value = `${member.mem_number ? member.mem_number + ' - ' : ''}${member.mem_fullname || ''}`;
    memberSelected = true;
    hideMemberSuggest();
  }

  function renderMemberSuggest(items) {
    if (!memberSuggest) return;
    if (!items.length) {
      hideMemberSuggest();
      return;
    }
    memberSuggest.innerHTML = items.map(item => `
        <button type="button" class="suggest-item">
          <div class="suggest-main">${item.mem_number ? item.mem_number + ' - ' : ''}${item.mem_fullname}</div>
          <div class="suggest-sub">${item.mem_group || ''}${item.mem_class ? ' · ชั้น ' + item.mem_class : ''}</div>
        </button>
      `).join('');
    memberSuggest.classList.remove('hidden');
    Array.from(memberSuggest.querySelectorAll('.suggest-item')).forEach((btn, idx) => {
      btn.addEventListener('click', () => setMemberSelection(items[idx]));
    });
  }

  async function searchMembers(term) {
    if (memberSuggestAbort) memberSuggestAbort.abort();
    memberSuggestAbort = new AbortController();
    try {
      const res = await fetch('members_search.php?q=' + encodeURIComponent(term), {
        signal: memberSuggestAbort.signal
      });
      const items = await res.json().catch(() => []);
      renderMemberSuggest(Array.isArray(items) ? items : []);
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      hideMemberSuggest();
    }
  }

  if (memberQueryInput) {
    memberQueryInput.addEventListener('input', () => {
      memberSelected = false;
      if (memberIdInput) memberIdInput.value = '0';
      const term = memberQueryInput.value.trim();
      if (memberSuggestTimer) clearTimeout(memberSuggestTimer);
      if (term.length < 2) {
        hideMemberSuggest();
        return;
      }
      memberSuggestTimer = setTimeout(() => searchMembers(term), 220);
    });

    memberQueryInput.addEventListener('focus', () => {
      const term = memberQueryInput.value.trim();
      if (term.length >= 2 && memberIdInput && memberIdInput.value === '0' && !memberSelected) {
        searchMembers(term);
      }
    });
  }

  document.addEventListener('click', (e) => {
    if (!memberSuggest || !memberQueryInput) return;
    if (!memberSuggest.contains(e.target) && e.target !== memberQueryInput) {
      hideMemberSuggest();
    }
  });

  if (exportForm) {
    exportForm.addEventListener('submit', () => {
      if (memberQueryInput && memberIdInput) {
        const raw = memberQueryInput.value.trim();
        if (raw === '') {
          memberIdInput.value = '0';
        }
      }
    });
  }

  if (window.lucide && lucide.createIcons) lucide.createIcons();
  </script>
</body>

</html>

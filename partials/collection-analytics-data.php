<?php
if (!isset($db) || !($db instanceof mysqli) || !is_logged_in()) { http_response_code(404); exit; }
// Summary filters (for daily/lan round summary)
$sum_date_from = is_string($_GET['date_from'] ?? null) ? trim($_GET['date_from']) : '';
$sum_filter_error = '';
$sum_date_to = is_string($_GET['date_to'] ?? null) ? trim($_GET['date_to']) : '';
$sum_lan_param = is_string($_GET['lan'] ?? null) ? trim($_GET['lan']) : 'all';

$sum_lan = 'all';
if ($sum_lan_param !== '' && strtolower($sum_lan_param) !== 'all') {
	$lanInt = (int)$sum_lan_param;
	if (in_array($lanInt, [1, 2, 3, 4], true)) {
		$sum_lan = (string)$lanInt;
	}
}

// validate date filters (Y-m-d)
$dt = $sum_date_from !== '' ? DateTime::createFromFormat('Y-m-d', $sum_date_from) : null;
if ($sum_date_from !== '' && (!$dt || $dt->format('Y-m-d') !== $sum_date_from)) {
	$sum_date_from = '';
	$sum_filter_error = 'วันที่เริ่มต้นไม่ถูกต้อง กรุณาเลือกวันที่ใหม่';
}
$dt = $sum_date_to !== '' ? DateTime::createFromFormat('Y-m-d', $sum_date_to) : null;
if ($sum_date_to !== '' && (!$dt || $dt->format('Y-m-d') !== $sum_date_to)) {
	$sum_date_to = '';
	$sum_filter_error = 'วันที่สิ้นสุดไม่ถูกต้อง กรุณาเลือกวันที่ใหม่';
}

if ($sum_date_from !== '' && $sum_date_to !== '' && $sum_date_from > $sum_date_to) {
    $sum_filter_error = 'วันที่เริ่มต้นต้องไม่อยู่หลังวันที่สิ้นสุด กรุณาตรวจช่วงวันที่';
}

foreach (['date_from','date_to','lan'] as $filterKey) {
    if (isset($_GET[$filterKey]) && !is_string($_GET[$filterKey])) $sum_filter_error = 'รูปแบบตัวกรองไม่ถูกต้อง กรุณาเลือกข้อมูลใหม่';
}
if (!in_array(strtolower($sum_lan_param), ['', 'all', '1', '2', '3', '4'], true)) $sum_filter_error = 'ลานรับซื้อไม่ถูกต้อง กรุณาเลือกลานใหม่';

// Daily/Lan summary query
$sum_where = $sum_filter_error === '' ? [] : ['1=0'];
$sum_params = [];
$sum_types = '';
if ($sum_date_from !== '') {
	$sum_where[] = 'r.ru_date >= ?';
	$sum_params[] = $sum_date_from;
	$sum_types .= 's';
}
if ($sum_date_to !== '') {
	$sum_where[] = 'r.ru_date <= ?';
	$sum_params[] = $sum_date_to;
	$sum_types .= 's';
}
if ($sum_lan !== 'all') {
	$sum_where[] = 'r.ru_lan = ?';
	$sum_params[] = $sum_lan;
	$sum_types .= 's';
}
$sum_where_sql = $sum_where ? ('WHERE ' . implode(' AND ', $sum_where)) : '';

$summary_rows = [];
$sql = "SELECT r.ru_date, r.ru_lan, ROUND(SUM(r.ru_value)/NULLIF(SUM(r.ru_quantity),0),2) AS pr_price,
		SUM(r.ru_quantity) AS total_qty,
		SUM(r.ru_value) AS total_value,
		SUM(r.ru_expend) AS total_expend,
		SUM(r.ru_netvalue) AS total_net,
		COUNT(*) AS row_count
	FROM (SELECT * FROM tbl_rubber WHERE LOWER(TRIM(COALESCE(record_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564)) r
	$sum_where_sql
	GROUP BY r.ru_date, r.ru_lan
	ORDER BY r.ru_date DESC, CAST(r.ru_lan AS UNSIGNED) ASC";

$stmt = $db->prepare($sql);
if ($stmt) {
	if ($sum_params) {
		$stmt->bind_param($sum_types, ...$sum_params);
	}
	$stmt->execute();
	$res = $stmt->get_result();
	if ($res) {
		$summary_rows = $res->fetch_all(MYSQLI_ASSOC);
		$res->free();
	}
	$stmt->close();
}

$analytics = rts_collection_analytics($summary_rows);
$chartSummaryByDate = [];
if (!empty($summary_rows)) {
	foreach ($summary_rows as $row) {
		$ruDate = (string)($row['ru_date'] ?? '');
		if ($ruDate === '') {
			continue;
		}
		if (!isset($chartSummaryByDate[$ruDate])) {
			$chartSummaryByDate[$ruDate] = [
				'label' => thai_date_format($ruDate),
				'quantity' => 0.0,
				'value' => 0.0,
			];
		}
		$chartSummaryByDate[$ruDate]['quantity'] += (float)($row['total_qty'] ?? 0);
		$chartSummaryByDate[$ruDate]['value'] += (float)($row['total_value'] ?? 0);
	}
}
ksort($chartSummaryByDate);
$chartLabels = array_values(array_map(static function ($item) {
	return $item['label'];
}, $chartSummaryByDate));
$chartQuantities = array_values(array_map(static function ($item) {
	return round((float)$item['quantity'], 2);
}, $chartSummaryByDate));
$chartValues = array_values(array_map(static function ($item) {
	return round((float)$item['value'], 2);
}, $chartSummaryByDate));

$chartMemberCounts = [];
$chartGeneralCounts = [];
if (!empty($chartSummaryByDate)) {
	$countSql = "SELECT
		r.ru_date,
		COUNT(DISTINCT CASE WHEN LOWER(TRIM(r.ru_class)) = 'member' THEN NULLIF(TRIM(r.ru_number), '') END) AS member_people,
		COUNT(DISTINCT CASE WHEN LOWER(TRIM(r.ru_class)) = 'general' THEN NULLIF(TRIM(r.ru_fullname), '') END) AS general_people
	FROM (SELECT * FROM tbl_rubber WHERE LOWER(TRIM(COALESCE(record_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564)) r
	$sum_where_sql
	GROUP BY r.ru_date
	ORDER BY r.ru_date ASC";
	$countStmt = $db->prepare($countSql);
	if ($countStmt) {
		if (!empty($sum_params)) {
			$countStmt->bind_param($sum_types, ...$sum_params);
		}
		$countStmt->execute();
		$countRes = $countStmt->get_result();
		$countByDate = [];
		if ($countRes) {
			while ($cr = $countRes->fetch_assoc()) {
				$d = (string)($cr['ru_date'] ?? '');
				if ($d === '') {
					continue;
				}
				$countByDate[$d] = [
					'member' => (int)($cr['member_people'] ?? 0),
					'general' => (int)($cr['general_people'] ?? 0),
				];
			}
			$countRes->free();
		}
		$countStmt->close();

		foreach (array_keys($chartSummaryByDate) as $dateKey) {
			$chartMemberCounts[] = (int)($countByDate[$dateKey]['member'] ?? 0);
			$chartGeneralCounts[] = (int)($countByDate[$dateKey]['general'] ?? 0);
		}
	}
}

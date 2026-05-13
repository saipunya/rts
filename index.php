<?php
require_once 'functions.php';
include 'header.php';

// added: use db() instead of undefined $mysqli
$db = db();
?>



<style>
html, body {
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    font-size: 16px;
    font-weight: 300;
	background: linear-gradient(180deg, #f3f8f4 0%, #eef6f0 100%);
    color: var(--bs-body-color);
}

.container {
	background: rgba(255, 255, 255, 0.72);
	border: 1px solid rgba(21, 87, 36, 0.08);
	border-radius: 1.5rem;
	padding: 2rem;
    margin-top: 2rem;
    margin-bottom: 2rem;
	box-shadow: 0 24px 48px rgba(16, 24, 40, 0.06);
	backdrop-filter: blur(10px);
}

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

.hero-section {
    padding: 0.5rem 0 2rem;
}

.hero-panel {
	background: linear-gradient(135deg, #ffffff 0%, #f5fbf6 100%);
	border: 1px solid rgba(47, 110, 67, 0.12);
	border-radius: 1.5rem;
	padding: 2rem;
	box-shadow: 0 18px 40px rgba(16, 24, 40, 0.06);
}

.hero-badge {
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
	padding: 0.45rem 0.9rem;
	border-radius: 999px;
	background: #e8f5eb;
	color: #2f6e43;
	font-size: 0.95rem;
	font-weight: 600;
	margin-bottom: 1rem;
}

.hero-title {
    font-size: 2.35rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 0.75rem;
	color: #204e31;
}

	.hero-subtitle {
		font-weight: 600;
		margin-bottom: 0.75rem;
		color: #4a6b55;
	}

	.hero-description {
		font-size: 1.05rem;
		color: #617565;
		margin-bottom: 1.5rem;
	}

	.hero-actions {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.75rem;
		flex-wrap: wrap;
		margin-bottom: 1rem;
	}

	.hero-meta {
		display: flex;
		justify-content: center;
		gap: 0.75rem;
		flex-wrap: wrap;
	}

	.hero-meta span {
		display: inline-flex;
		align-items: center;
		gap: 0.45rem;
		padding: 0.45rem 0.8rem;
		border-radius: 999px;
		background: #f2f8f3;
		color: #4e6656;
		font-size: 0.95rem;
	}

	.quick-actions {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: 0.75rem;
		margin: 0 0 1.5rem;
	}

	.quick-action {
		display: flex;
		align-items: center;
		gap: 0.85rem;
		padding: 1rem 1.1rem;
		border-radius: 0.9rem;
		border: 1px solid rgba(47, 110, 67, 0.12);
		background: #ffffff;
		color: #204e31;
		text-decoration: none;
		box-shadow: 0 10px 24px rgba(16, 24, 40, 0.05);
		transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
		min-height: 74px;
	}

	.quick-action:hover,
	.quick-action:focus {
		transform: translateY(-2px);
		box-shadow: 0 16px 30px rgba(16, 24, 40, 0.08);
		border-color: rgba(47, 110, 67, 0.22);
		color: #184d2d;
	}

	.quick-action i {
		width: 2.4rem;
		height: 2.4rem;
		border-radius: 0.75rem;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: #e8f5eb;
		color: #2f6e43;
		flex: 0 0 auto;
	}

	.quick-action strong {
		display: block;
		font-size: 1rem;
		line-height: 1.2;
	}

	.quick-action span {
		display: block;
		font-size: 0.9rem;
		color: #6b7e70;
		line-height: 1.3;
	}

	.index-toolbar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 1rem;
		flex-wrap: wrap;
		padding: 1rem 1.25rem;
		margin-bottom: 1.5rem;
		background: #f7fbf8;
		border: 1px solid rgba(47, 110, 67, 0.12);
		border-radius: 1rem;
	}

	.index-toolbar h2 {
		font-size: 1.35rem;
		font-weight: 600;
		color: #28553a;
	}

	.index-toolbar-note {
		margin: 0;
		font-size: 0.95rem;
		color: #708374;
	}

	.stat-card {
		position: relative;
		height: 100%;
		padding: 1.4rem 1.5rem 1.4rem 1.75rem;
		border: 1px solid rgba(47, 110, 67, 0.12);
		border-radius: 1rem;
		background: #ffffff;
		box-shadow: 0 12px 28px rgba(16, 24, 40, 0.05);
		overflow: hidden;
		transition: transform 0.2s ease, box-shadow 0.2s ease;
	}

	.stat-card::before {
		content: '';
		position: absolute;
		left: 0;
		top: 0;
		bottom: 0;
		width: 6px;
		background: var(--bs-success);
	}

	.stat-card.stat-card-accent-2::before {
		background: #68ae7a;
	}

	.stat-card.stat-card-accent-3::before {
		background: #4f8f61;
	}

	.stat-card.stat-card-accent-4::before {
		background: #2f6e43;
	}

	.stat-card.stat-card-accent-5::before {
		background: #8bbf95;
	}

	.stat-card:hover {
		transform: translateY(-2px);
		box-shadow: 0 18px 32px rgba(16, 24, 40, 0.08);
	}

	.stat-label {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		margin-bottom: 0.75rem;
		font-size: 1rem;
		font-weight: 600;
		color: #5f7465;
	}

	.stat-label i {
		font-size: 1.25rem;
		color: var(--bs-success);
	}

	.stat-value {
		font-size: 2.1rem;
		font-weight: 700;
		line-height: 1.2;
		margin-bottom: 0.35rem;
		color: #183524;
	}

	.stat-sub {
		font-size: 0.95rem;
		color: #7b8d7f;
	}

	.stat-sub .fw-semibold {
		color: var(--bs-success-text-emphasis);
	}

	.announcement-card {
		background: linear-gradient(135deg, #fff7ed 0%, #fffdf5 100%);
		border: 1px solid rgba(245, 158, 11, 0.25);
		border-radius: 1rem;
		box-shadow: 0 12px 28px rgba(16, 24, 40, 0.05);
	}

	.announcement-icon {
		width: 3rem;
		height: 3rem;
		border-radius: 0.9rem;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: #fef3c7;
		color: #b45309;
		flex: 0 0 auto;
	}

	.announcement-pill {
		display: inline-flex;
		align-items: center;
		gap: 0.4rem;
		padding: 0.45rem 0.75rem;
		border-radius: 999px;
		background: #ffffff;
		border: 1px solid rgba(245, 158, 11, 0.25);
		color: #7c2d12;
		font-weight: 600;
	}

	.announcement-note {
		color: #7c2d12;
	}

	.live-round-card {
		border-radius: 1rem;
		border: 1px solid rgba(47, 110, 67, 0.14);
		box-shadow: 0 12px 28px rgba(16, 24, 40, 0.05);
		overflow: hidden;
	}

	.live-round-card .card-body {
		padding: 1.1rem 1.15rem;
	}

	.live-round-badge {
		display: inline-flex;
		align-items: center;
		gap: 0.4rem;
		padding: 0.4rem 0.7rem;
		border-radius: 999px;
		background: #eef8f0;
		color: #2f6e43;
		font-weight: 600;
		font-size: 0.92rem;
	}

	.live-round-time {
		display: inline-flex;
		align-items: center;
		gap: 0.35rem;
		color: #6b7e70;
		font-size: 0.92rem;
	}

	.live-round-grid {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 0.75rem;
	}

	.live-round-tile {
		border-radius: 1rem;
		border: 1px solid rgba(47, 110, 67, 0.12);
		box-shadow: 0 12px 28px rgba(16, 24, 40, 0.04);
		overflow: hidden;
	}

	.live-round-tile .card-body {
		padding: 1rem 1.05rem;
	}

	.live-round-icon {
		width: 3.1rem;
		height: 3.1rem;
		border-radius: 0.95rem;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: #e8f5eb;
		color: #2f6e43;
		flex: 0 0 auto;
	}

	.live-round-value {
		font-size: 1.9rem;
		font-weight: 700;
		line-height: 1.1;
		color: #183524;
	}

	.live-round-label {
		font-size: 0.95rem;
		color: #617565;
	}

	.live-round-foot {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 0.75rem;
		flex-wrap: wrap;
		margin-top: 0.75rem;
		padding-top: 0.75rem;
		border-top: 1px dashed rgba(47, 110, 67, 0.14);
	}

	@media (max-width: 992px) {
		.live-round-grid {
			grid-template-columns: 1fr;
		}
	}

	.average-card {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 1rem;
	}

	.average-card-icon {
		width: 3rem;
		height: 3rem;
		border-radius: 0.9rem;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex: 0 0 auto;
		background: #e8f5eb;
		color: #2f6e43;
	}

	.average-card-icon.is-accent {
		background: #eef2ff;
		color: #4f46e5;
	}

	.average-card-value {
		font-size: 2rem;
		font-weight: 700;
		line-height: 1.15;
		margin-bottom: 0.35rem;
		color: #183524;
	}

	.average-card-note {
		font-size: 0.95rem;
		color: #6f8073;
	}

	.chart-card {
		border: 1px solid rgba(47, 110, 67, 0.12);
		border-radius: 1rem;
		background: #ffffff;
		box-shadow: 0 12px 28px rgba(16, 24, 40, 0.05);
		overflow: hidden;
		height: 100%;
	}

	.chart-card .card-header {
		background: #f7fbf8;
		border-bottom: 1px solid rgba(47, 110, 67, 0.12);
		font-weight: 600;
		color: #28553a;
	}

	.chart-wrap {
		position: relative;
		width: 100%;
		height: 320px;
	}

	.section-title {
		display: flex;
		align-items: center;
		gap: 0.6rem;
		padding: 0.95rem 1.1rem;
		margin-bottom: 1rem;
		border: 1px solid rgba(47, 110, 67, 0.12);
		border-radius: 0.9rem;
		background: #f7fbf8;
		color: #28553a;
		font-size: 1.08rem;
		font-weight: 600;
	}

	.card-table {
		margin-bottom: 1.5rem;
		border: 1px solid rgba(47, 110, 67, 0.12);
		border-radius: 1rem;
		background: #ffffff;
		box-shadow: 0 12px 28px rgba(16, 24, 40, 0.05);
		overflow: hidden;
	}

	.table-responsive {
		overflow-x: auto;
		-webkit-overflow-scrolling: touch;
	}

	.table {
		font-size: 1.2rem;
		margin-bottom: 0;
	}

	.table th {
		font-size: 1.15rem;
		font-weight: 600;
		padding: 0.95rem 0.85rem;
		background: var(--bs-success);
		color: #fff;
		border: none;
	}

	.table td {
		font-size: 1.2rem;
		padding: 0.85rem 0.75rem;
		vertical-align: middle;
		color: #2f3d33;
	}

	.lan-summary-table,
	.daily-summary-table {
		font-size: 1.2rem;
	}

	.lan-summary-table th,
	.daily-summary-table th {
		white-space: nowrap;
	}

	.table-success {
		--bs-table-bg: #e9f6ed;
		--bs-table-striped-bg: #e9f6ed;
		--bs-table-hover-bg: #dff0e4;
		color: #24472d;
	}

	.daily-summary-section .daily-summary-table {
		min-width: 900px;
		table-layout: auto;
	}

	.daily-summary-section .daily-summary-table th,
	.daily-summary-section .daily-summary-table td {
		white-space: nowrap;
	}

	.daily-summary-section .daily-summary-table th:last-child,
	.daily-summary-section .daily-summary-table td:last-child {
		min-width: 180px;
	}

	@media (max-width: 992px) {
		.hero-panel {
			padding: 1.5rem;
		}

		.quick-actions {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}

		.index-toolbar {
			flex-direction: column;
			align-items: stretch !important;
			text-align: center;
		}

		.index-toolbar h2 {
			font-size: 1.5rem;
		}

		.stat-value {
			font-size: 2rem;
		}

		.stat-label {
			font-size: 1rem;
		}

		.daily-summary-section {
			margin-left: -0.25rem;
			margin-right: -0.25rem;
		}

		.daily-summary-section .section-title {
			font-size: 1.1rem;
			padding: 0.9rem 1rem;
		}

		.daily-summary-section .card-table {
			margin: 0;
		}

		.daily-summary-section .table-responsive {
			padding: 0;
		}

		.daily-summary-section .daily-summary-table {
			min-width: 820px;
		}
	}

	@media (max-width: 768px) {
		.index-toolbar {
			padding: 1rem 1.1rem;
		}

		.index-toolbar h2 {
			font-size: 1.35rem;
		}

		.stat-card {
			padding: 1.1rem 1rem 1rem 1.25rem;
		}

		.stat-value {
			font-size: 1.9rem;
		}

		.stat-label {
			font-size: 0.98rem;
		}

		.stat-sub {
			font-size: 0.9rem;
		}

		.average-card {
			flex-direction: row;
		}

		.average-card-value {
			font-size: 1.8rem;
		}

		.card-table {
			margin: 0 -0.5rem;
		}

		.table-responsive {
			font-size: 1.2rem;
			padding: 0 0.5rem;
		}

		.table th {
			font-size: 0.98rem;
		}

		.table th,
		.table td {
			padding: 0.65rem 0.55rem;
		}

		.section-title {
			font-size: 1.2rem;
			padding: 0.85rem 1rem;
		}

		.daily-summary-section .daily-summary-table {
			min-width: 780px;
		}

		.daily-summary-section .daily-summary-table th,
		.daily-summary-section .daily-summary-table td {
			font-size: 0.98rem;
			padding: 0.6rem 0.5rem;
		}
	}

	@media (max-width: 576px) {
		.quick-actions {
			grid-template-columns: 1fr;
		}

		.container {
			padding: 1.25rem 1rem;
			border-radius: 1rem;
			margin-top: 1rem;
			margin-bottom: 1rem;
		}

		.hero-panel {
			padding: 1.25rem 1rem;
		}

		.hero-title {
			font-size: 1.8rem;
		}

		.hero-description {
			font-size: 1.05rem;
		}

		.hero-actions .btn {
			width: 100%;
		}

		.index-toolbar h2 {
			font-size: 1.2rem;
		}

		.stat-card {
			padding: 0.85rem 0.85rem 0.85rem 1rem;
		}

		.stat-value {
			font-size: 1.8rem;
		}

		.stat-label {
			font-size: 1.1rem;
		}

		.table-responsive {
			font-size: 0.95rem;
			padding: 0 0.25rem;
		}

	}

	.stat-card:hover {
		transform: none;
	}

	@media (max-width: 768px) {
		.chart-wrap {
			height: 260px;
		}
	}

</style>

<?php
// late price from tbl_price
$stmt = $db->prepare("SELECT pr_price FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1"); // changed: $mysqli -> $db
if ($stmt) {
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	if ($row) {
		$latest_price = $row['pr_price'];
	} else {
		$latest_price = 0;
	}

} else {
	$latest_price = 0;
}
// pr_date of latest_price


$stmt = $db->prepare("SELECT pr_date FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1"); // changed: $mysqli -> $db
if ($stmt) {
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	$latest_price_date = $row ? $row['pr_date'] : null;
} else {
	$latest_price_date = null;
}

$onlinePresenceStats = fetch_online_presence_stats($db);
$rubberAnnouncement = fetch_rubber_collection_announcement($db, $latest_price_date);
$rubberLiveSummary = fetch_rubber_round_live_summary($db, $latest_price_date);

// added: safe display for latest price date
$latest_price_date_text = $latest_price_date ? thai_date_format($latest_price_date) : '-';

// Sum totals for the date of latest price (match by ru_date == $latest_price_date)
// ยอดปริมาณรวม: ผลรวม ru_quantity ของ tbl_rubber ที่ ru_date == pr_date ล่าสุด
// ยอดเงินรวม: ผลรวม ru_quantity * ราคายางล่าสุด (pr_price)
$price_date_total_quantity = 0;
$price_date_total_value = 0;
if ($latest_price_date) {
    // Query จากฐานข้อมูลโดยตรงเพื่อให้ได้ผลรวมที่ถูกต้องทุกลาน (ไม่ใช้ LIMIT)
	$stmt = $db->prepare("SELECT SUM(ru_quantity) as total_qty FROM tbl_rubber WHERE ru_date = ?");
    if ($stmt) {
        $stmt->bind_param('s', $latest_price_date);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $price_date_total_quantity = $row['total_qty'] ? (float)$row['total_qty'] : 0;
        $stmt->close();
    }
    $price_date_total_value = $price_date_total_quantity * $latest_price;
}

// Summary filters (for daily/lan round summary)
$sum_date_from = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$sum_date_to   = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
$sum_lan_param = isset($_GET['lan']) ? trim((string)$_GET['lan']) : 'all';

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
}
$dt = $sum_date_to !== '' ? DateTime::createFromFormat('Y-m-d', $sum_date_to) : null;
if ($sum_date_to !== '' && (!$dt || $dt->format('Y-m-d') !== $sum_date_to)) {
	$sum_date_to = '';
}

// Total record count for display
$total_records = 0;
if ($rs = $db->query("SELECT COUNT(*) AS cnt FROM tbl_rubber")) {
	$row = $rs->fetch_assoc();
	$total_records = $row && $row['cnt'] ? (int)$row['cnt'] : 0;
	$rs->free();
}

// Query ค่าเฉลี่ยต่อคนของรอบล่าสุด (วันที่ราคายางล่าสุด)
$latest_round_total_quantity = 0;
$latest_round_total_net = 0;
$latest_round_member_people = 0;
$latest_round_general_people = 0;
$latest_round_unique_people = 0;
if ($latest_price_date) {
	$latestRoundSql = "SELECT
		COALESCE(SUM(ru_quantity), 0) AS total_qty,
		COALESCE(SUM(ru_netvalue), 0) AS total_net,
		COUNT(DISTINCT CASE WHEN LOWER(TRIM(ru_class)) = 'member' THEN TRIM(ru_number) END) AS member_people,
		COUNT(DISTINCT CASE WHEN LOWER(TRIM(ru_class)) = 'general' THEN TRIM(ru_fullname) END) AS general_people
	FROM tbl_rubber
	WHERE ru_date = ?";
	$latestRoundStmt = $db->prepare($latestRoundSql);
	if ($latestRoundStmt) {
		$latestRoundStmt->bind_param('s', $latest_price_date);
		$latestRoundStmt->execute();
		$latestRoundRes = $latestRoundStmt->get_result();
		if ($latestRoundRes) {
			$row = $latestRoundRes->fetch_assoc();
			$latest_round_total_quantity = $row['total_qty'] ? (float)$row['total_qty'] : 0;
			$latest_round_total_net = $row['total_net'] ? (float)$row['total_net'] : 0;
			$latest_round_member_people = (int)($row['member_people'] ?? 0);
			$latest_round_general_people = (int)($row['general_people'] ?? 0);
			$latestRoundRes->free();
		}
		$latestRoundStmt->close();
	}
	$latest_round_unique_people = $latest_round_member_people + $latest_round_general_people;
}

$latest_average_quantity_per_person = $latest_round_unique_people > 0 ? $latest_round_total_quantity / $latest_round_unique_people : 0;
$latest_average_net_per_person = $latest_round_unique_people > 0 ? $latest_round_total_net / $latest_round_unique_people : 0;

// Query ปริมาณรวมและยอดเงินรวมสะสมของทุกลานจากฐานข้อมูลโดยตรง
$all_total_quantity = 0;
$all_total_value = 0;
$all_stats = $db->query("SELECT
	COALESCE(SUM(ru_quantity), 0) AS total_qty,
	COALESCE(SUM(ru_value), 0) AS total_value
FROM tbl_rubber");
if ($all_stats) {
    $row = $all_stats->fetch_assoc();
    $all_total_quantity = $row['total_qty'] ? (float)$row['total_qty'] : 0;
    $all_total_value = $row['total_value'] ? (float)$row['total_value'] : 0;
    $all_stats->free();
}

// Daily/Lan summary query
$sum_where = [];
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
$sql = "SELECT r.ru_date, r.ru_lan, MAX(p.pr_price) AS pr_price,
		SUM(r.ru_quantity) AS total_qty,
		SUM(r.ru_value) AS total_value,
		SUM(r.ru_expend) AS total_expend,
		SUM(r.ru_netvalue) AS total_net,
		COUNT(*) AS row_count
	FROM tbl_rubber r
	LEFT JOIN tbl_price p ON p.pr_date = r.ru_date
	$sum_where_sql
	GROUP BY r.ru_date, r.ru_lan
	ORDER BY r.ru_date DESC, CAST(r.ru_lan AS UNSIGNED) ASC
	LIMIT 200";

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
		COUNT(DISTINCT CASE WHEN LOWER(r.ru_class) = 'member' THEN TRIM(r.ru_number) END) AS member_people,
		COUNT(DISTINCT CASE WHEN LOWER(r.ru_class) = 'general' THEN TRIM(r.ru_fullname) END) AS general_people
	FROM tbl_rubber r
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
?>

	<?php
	if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
	$logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['member_id']);
	$username = $_SESSION['username'] ?? ($_SESSION['user_name'] ?? '');
	$target = $logged_in ? 'rubbers.php?lan=all' : 'login.php?redirect=' . urlencode('rubbers.php?lan=all');
	?>

	<div class="hero-section mb-4">
		<div class="hero-panel text-center">
			<div class="hero-badge">
				<i data-lucide="droplet" aria-hidden="true"></i>
				ระบบภาพรวมข้อมูลยางพารา
			</div>
			<h1 class="hero-title">ระบบจัดการยางพารา</h1>
			<h4 class="hero-subtitle">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</h4>
			<p class="hero-description">ดูภาพรวมการรวบรวมยาง ราคาอ้างอิง และสรุปข้อมูลสำคัญในหน้าเดียวแบบอ่านง่าย</p>
			<div class="hero-actions">
				<a href="allmember.php" class="btn btn-success btn-lg rounded-pill px-4 fw-semibold">
					<i data-lucide="user" aria-hidden="true"></i> สำหรับสมาชิก
				</a>
				<a href="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-success btn-lg rounded-pill px-4 fw-semibold">
					<i data-lucide="clipboard" aria-hidden="true"></i> ดูรายการรับซื้อ
				</a>
			</div>
			<div class="hero-meta">
				<span><i data-lucide="coins" aria-hidden="true"></i> ราคาล่าสุด <?php echo number_format($latest_price,2); ?> บาท</span>
				<span><i data-lucide="calendar" aria-hidden="true"></i> วันที่ <?php echo htmlspecialchars($latest_price_date_text); ?></span>
				<span><i data-lucide="database" aria-hidden="true"></i> ทั้งหมด <?php echo number_format($total_records); ?> รายการ</span>
			</div>
		</div>
	</div>

	<?php if (!empty($rubberAnnouncement['show'])): ?>
	<div class="row g-3 mb-4">
		<div class="col-12">
			<div class="card announcement-card">
				<div class="card-body p-3 p-md-4">
					<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
						<div class="d-flex align-items-start gap-3">
							<div class="announcement-icon" aria-hidden="true">
								<i data-lucide="megaphone" class="fs-4"></i>
							</div>
							<div>
								<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
									<span class="badge text-bg-warning text-dark">ประกาศอัตโนมัติ</span>
									<h2 class="h5 mb-0">วันวางยางและวันชั่งยางรอบล่าสุด</h2>
								</div>
								<div class="announcement-note mb-2">
									<?php echo e($rubberAnnouncement['lay_start_text'] ?? '-'); ?> ถึง <?php echo e($rubberAnnouncement['lay_end_text'] ?? '-'); ?>
									สำหรับการวางยาง และชั่งยางวันที่ <?php echo e($rubberAnnouncement['weigh_date_text'] ?? '-'); ?>
								</div>
								<div class="small text-muted">
									ประกาศนี้จะแสดงจนถึงวันชั่งยางของราคายางล่าสุด และจะหายไปอัตโนมัติหลังวันชั่งยาง
								</div>
							</div>
						</div>
						<div class="d-flex flex-column gap-2">
							<span class="announcement-pill"><i data-lucide="calendar-range" aria-hidden="true"></i> วางยาง <?php echo e($rubberAnnouncement['lay_start_text'] ?? '-'); ?></span>
							<span class="announcement-pill"><i data-lucide="calendar-check" aria-hidden="true"></i> วางยาง <?php echo e($rubberAnnouncement['lay_end_text'] ?? '-'); ?></span>
							<span class="announcement-pill"><i data-lucide="scale" aria-hidden="true"></i> ชั่งยาง <?php echo e($rubberAnnouncement['weigh_date_text'] ?? '-'); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div id="liveRoundSection" class="row g-3 mb-4<?php echo empty($rubberLiveSummary['show']) ? ' d-none' : ''; ?>">
		<div class="col-12">
			<div class="card live-round-card">
				<div class="card-body">
					<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
						<div>
							<div class="live-round-badge mb-2">
								<i data-lucide="radio" aria-hidden="true"></i> อัปเดตทุก 5 นาที
							</div>
							<h2 class="h5 mb-1">สรุปข้อมูลรอบวางยางล่าสุด</h2>
							<div class="small text-muted">
								<?php echo e($rubberLiveSummary['lay_start_text'] ?? '-'); ?> ถึง <?php echo e($rubberLiveSummary['lay_end_text'] ?? '-'); ?>
								และชั่งยางวันที่ <?php echo e($rubberLiveSummary['weigh_date_text'] ?? '-'); ?>
							</div>
						</div>
						<div class="live-round-time">
							<i data-lucide="clock-3" aria-hidden="true"></i>
							อัปเดตล่าสุด <span id="liveRoundUpdatedAt"><?php echo e($rubberLiveSummary['updated_at_text'] ?? '-'); ?></span>
						</div>
					</div>

					<div class="live-round-grid">
						<div class="card live-round-tile bg-success-subtle">
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-start gap-3">
									<div>
										<div class="live-round-label mb-1">น้ำหนักรวม</div>
										<div class="live-round-value" id="liveRoundQuantity"><?php echo number_format((float)($rubberLiveSummary['total_quantity'] ?? 0), 2); ?> kg</div>
									</div>
								<div class="live-round-icon" aria-hidden="true">
									<i data-lucide="scale" class="fs-3"></i>
								</div>
							</div>
								<div class="live-round-foot">
									<span class="small text-success-emphasis">รวมทุกลานในรอบล่าสุด</span>
								</div>
							</div>
						</div>

						<div class="card live-round-tile bg-warning-subtle">
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-start gap-3">
									<div>
										<div class="live-round-label mb-1">ยอดเงินหัก</div>
										<div class="live-round-value" id="liveRoundDeduct"><?php echo number_format((float)($rubberLiveSummary['total_expend'] ?? 0), 2); ?> ฿</div>
									</div>
									<div class="live-round-icon" aria-hidden="true" style="background:#fff4db;color:#b45309;">
										<i data-lucide="file-text" class="fs-3"></i>
									</div>
								</div>
								<div class="live-round-foot">
									<span class="small text-warning-emphasis">หักรวมจากทุกรายการในรอบนี้</span>
								</div>
							</div>
						</div>

						<div class="card live-round-tile bg-primary-subtle">
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-start gap-3">
									<div>
										<div class="live-round-label mb-1">ยอดเงินที่จ่าย</div>
										<div class="live-round-value" id="liveRoundNet"><?php echo number_format((float)($rubberLiveSummary['total_net'] ?? 0), 2); ?> ฿</div>
									</div>
									<div class="live-round-icon" aria-hidden="true" style="background:#e0ecff;color:#1d4ed8;">
										<i data-lucide="wallet" class="fs-3"></i>
									</div>
								</div>
								<div class="live-round-foot">
									<span class="small text-primary-emphasis">จ่ายสุทธิหลังหักทั้งหมด</span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="quick-actions" aria-label="ทางลัดงานหลัก">
		<a class="quick-action" href="rubbers.php?lan=all">
			<i data-lucide="clipboard-list" aria-hidden="true"></i>
			<span>
				<strong>ดูรายการรับซื้อ</strong>
				ตรวจสอบและค้นหารายการยางได้ทันที
			</span>
		</a>
		<a class="quick-action" href="member_form.php?action=create">
			<i data-lucide="user-plus" aria-hidden="true"></i>
			<span>
				<strong>เพิ่มสมาชิก</strong>
				บันทึกข้อมูลสมาชิกหรือเกษตรกรใหม่
			</span>
		</a>
		<a class="quick-action" href="price_form.php?action=create">
			<i data-lucide="dollar-sign" aria-hidden="true"></i>
			<span>
				<strong>เพิ่มราคายาง</strong>
				ประกาศราคาสำหรับรอบล่าสุดอย่างรวดเร็ว
			</span>
		</a>
		<a class="quick-action" href="members.php">
			<i data-lucide="users" aria-hidden="true"></i>
			<span>
				<strong>ค้นหาสมาชิก</strong>
				เปิดรายชื่อและแก้ไขข้อมูลที่เกี่ยวข้อง
			</span>
		</a>
	</div>

	<div class="index-toolbar">
			<h2 class="d-flex align-items-center gap-2 mb-0">
				<i data-lucide="grid" aria-hidden="true"></i>
				ภาพรวมวันนี้
			</h2>
		<p class="index-toolbar-note">สรุปข้อมูลสำคัญของระบบในรูปแบบที่อ่านง่ายและอัปเดตจากฐานข้อมูลล่าสุด</p>
	</div>

	<!-- Quick stats -->
	<div class="row g-3 mb-4">
		<div class="col-12 col-md-6 col-lg-4">
			<div class="stat-card">
				<div class="stat-label"><i data-lucide="tag" aria-hidden="true"></i>ราคาที่ใช้คำนวณ</div>
				<div class="stat-value"><?php echo number_format($latest_price,2); ?> ฿</div>
				<div class="stat-sub">อัปเดต: <span class="fw-semibold"><?php echo $latest_price_date_text; ?></span></div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-lg-4">
			<div class="stat-card stat-card-accent-2">
				<div class="stat-label"><i data-lucide="calendar-check" aria-hidden="true"></i>ปริมาณรวม (<?php echo $latest_price_date_text; ?>)</div>
				<div class="stat-value"><?php echo number_format($price_date_total_quantity,2); ?> kg</div>
				<div class="stat-sub">อ้างอิงวันที่ราคายาง</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-lg-4">
			<div class="stat-card stat-card-accent-3">
				<div class="stat-label"><i data-lucide="file-text" aria-hidden="true"></i>ยอดเงินรวม (<?php echo $latest_price_date_text; ?>)</div>
				<div class="stat-value"><?php echo number_format($price_date_total_value,2); ?> ฿</div>
				<div class="stat-sub">ปริมาณ x ราคาล่าสุด</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mb-4">
		<div class="col-12 col-lg-6">
			<div class="stat-card stat-card-accent-4">
				<div class="stat-label"><i data-lucide="box" aria-hidden="true"></i>ปริมาณยางทั้งหมด</div>
				<div class="stat-value"><?php echo number_format($all_total_quantity,2); ?> kg</div>
				<div class="stat-sub">รวมจากรายการรับซื้อทั้งหมด</div>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="stat-card stat-card-accent-5">
				<div class="stat-label"><i data-lucide="dollar-sign" aria-hidden="true"></i>ยอดเงินทั้งหมด</div>
				<div class="stat-value"><?php echo number_format($all_total_value,2); ?> ฿</div>
				<div class="stat-sub">รวมยอดเงินจากแต่ละช่วงราคา</div>
			</div>
		</div>
	</div>

	<div class="section-title">
		<i data-lucide="bar-chart-3" aria-hidden="true"></i>
		กราฟสรุปตามรอบ
	</div>
	<div class="row g-3 mb-4">
		<div class="col-12 col-lg-6">
			<div class="chart-card">
				<div class="card-header px-3 py-2">
					<i data-lucide="bar-chart-2" class="me-1" aria-hidden="true"></i>ปริมาณยางในแต่ละรอบ
				</div>
				<div class="card-body p-3">
					<div class="chart-wrap">
						<canvas id="quantityChart" aria-label="กราฟปริมาณยางในแต่ละรอบ" role="img"></canvas>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="chart-card">
				<div class="card-header px-3 py-2">
					<i data-lucide="coins" class="me-1" aria-hidden="true"></i>จำนวนเงินในแต่ละรอบ
				</div>
				<div class="card-body p-3">
					<div class="chart-wrap">
						<canvas id="valueChart" aria-label="กราฟจำนวนเงินในแต่ละรอบ" role="img"></canvas>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="section-title">
		<i data-lucide="users" aria-hidden="true"></i>
		กราฟจำนวนคนที่รวบรวมตามรอบ
	</div>
	<div class="row g-3 mb-4">
		<div class="col-12 col-lg-6">
			<div class="chart-card">
				<div class="card-header px-3 py-2">
					<i data-lucide="user" class="me-1" aria-hidden="true"></i>จำนวนสมาชิกที่รวบรวมในแต่ละรอบ
				</div>
				<div class="card-body p-3">
					<div class="chart-wrap">
						<canvas id="memberCountChart" aria-label="กราฟจำนวนสมาชิกที่รวบรวมในแต่ละรอบ" role="img"></canvas>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="chart-card">
				<div class="card-header px-3 py-2">
					<i data-lucide="users" class="me-1" aria-hidden="true"></i>จำนวนเกษตรกรทั่วไปที่รวบรวมในแต่ละรอบ
				</div>
				<div class="card-body p-3">
					<div class="chart-wrap">
						<canvas id="generalCountChart" aria-label="กราฟจำนวนเกษตรกรทั่วไปที่รวบรวมในแต่ละรอบ" role="img"></canvas>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="section-title">
		<i data-lucide="users-round" aria-hidden="true"></i>
		ค่าเฉลี่ยต่อคนรอบล่าสุด
	</div>
	<div class="row g-3 mb-4">
		<div class="col-12 col-lg-6">
			<div class="stat-card">
				<div class="average-card">
					<div>
						<div class="stat-label mb-2"><i data-lucide="scale" aria-hidden="true"></i>ยอดปริมาณยางเฉลี่ยต่อคน</div>
						<div class="average-card-value"><?php echo number_format($latest_average_quantity_per_person,2); ?> kg</div>
						<div class="average-card-note">รอบวันที่ <?php echo htmlspecialchars($latest_price_date_text); ?> · คำนวณจากปริมาณยางรวม ÷ ผู้ส่งทั้งหมด <?php echo number_format($latest_round_unique_people); ?> คน</div>
					</div>
					<div class="average-card-icon" aria-hidden="true">
						<i data-lucide="scale" class="fs-4"></i>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="stat-card stat-card-accent-5">
				<div class="average-card">
					<div>
						<div class="stat-label mb-2"><i data-lucide="wallet" aria-hidden="true"></i>รายรับเฉลี่ยต่อคน</div>
						<div class="average-card-value"><?php echo number_format($latest_average_net_per_person,2); ?> ฿</div>
						<div class="average-card-note">รอบวันที่ <?php echo htmlspecialchars($latest_price_date_text); ?> · คำนวณจากยอดสุทธิรวม (หักยอดหักแล้ว) ÷ ผู้ส่งทั้งหมด <?php echo number_format($latest_round_unique_people); ?> คน</div>
					</div>
					<div class="average-card-icon is-accent" aria-hidden="true">
						<i data-lucide="coins" class="fs-4"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ปริมาณรวบรวมแต่ละลาน (เฉพาะวันที่ราคายางล่าสุด) -->
	<div class="row mb-4">
		<div class="col-12">
					<div class="section-title">
						<i data-lucide="bar-chart-2" aria-hidden="true"></i>
						ปริมาณรวบรวมแต่ละลาน (วันที่ราคายาง: <?php echo htmlspecialchars($latest_price_date_text); ?>)
					</div>
			<div class="card-table">
				<div class="table-responsive">
					<table class="table table-hover table-sm mb-0 lan-summary-table">
					<thead>
						<tr class="text-center">
							<th>ลาน</th>
							<th>ปริมาณรวม (kg)</th>

							<th>ยอดเงินรวม (฿)</th>
						</tr>
					</thead>
					<tbody>
					<?php
					$grand_total_qty = 0;
					$grand_total_value = 0;

					if ($latest_price_date) {
						$lan_sql = "SELECT ru_lan, SUM(ru_quantity) AS total_qty
									FROM tbl_rubber
									WHERE ru_date = ?
									GROUP BY ru_lan
									ORDER BY CAST(ru_lan AS UNSIGNED) ASC";
						$lan_stmt = $db->prepare($lan_sql);
						if ($lan_stmt) {
							$lan_stmt->bind_param('s', $latest_price_date);
							$lan_stmt->execute();
							$lan_res = $lan_stmt->get_result();
							if ($lan_res) {
								while ($lan_row = $lan_res->fetch_assoc()) {
									$lan = $lan_row['ru_lan'] ?? '-';
									$qty = (float)$lan_row['total_qty'];
									$value = $qty * $latest_price;

									$grand_total_qty += $qty;
									$grand_total_value += $value;

									echo '<tr class="text-center">';
									echo '<td>'.htmlspecialchars($lan).'</td>';
									echo '<td>'.number_format($qty,2).'</td>';
									echo '<td>'.number_format($value,2).'</td>';
									echo '</tr>';
								}
								$lan_res->free();
							}
							$lan_stmt->close();
						}
					}
					?>
					<tr class="table-success fw-bold text-center">
						<td>รวมทั้งหมด</td>
						<td><?php echo number_format($grand_total_qty,2); ?></td>
						<td><?php echo number_format($grand_total_value,2); ?></td>
					</tr>
					</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- Daily summary table (all lans combined) -->
	<div class="row daily-summary-section">
		<div class="col-12">
					<div class="section-title">
						<i data-lucide="clipboard" aria-hidden="true"></i>
						สรุปรับซื้อรายวัน (รวมทุกลาน)
					</div>

			<div class="card-table">
				<div class="table-responsive">
					<table class="table table-striped table-hover w-100 mb-0 daily-summary-table">
						<thead>
							<tr class="text-center">
								<th>วันที่</th>
								<th>ราคา (฿/kg)</th>
								<th>ปริมาณรวม (kg)</th>
								<th>เงินรวม (฿)</th>
								<th>ยอดหัก (฿)</th>
								<th>คงเหลือ/สุทธิ (฿)</th>
								<th>รายการ</th>
								<th>สมาชิก/เกษตรกร (คน)</th>
							</tr>
						</thead>
						<tbody>
							<?php
							// รวมข้อมูลทุกลานให้เหลือวันที่ละ 1 แถว
							$daily_summary = [];
							if (!empty($summary_rows)) {
								foreach ($summary_rows as $row) {
									$ruDate = $row['ru_date'] ?? '';
									if ($ruDate === '') {
										continue;
									}
									if (!isset($daily_summary[$ruDate])) {
										$daily_summary[$ruDate] = [
											'ru_date'      => $ruDate,
											'total_qty'    => 0.0,
											'total_value'  => 0.0,
											'total_expend' => 0.0,
											'total_net'    => 0.0,
											'row_count'    => 0,
											'pr_price'     => null,
										];
									}

									$qty    = isset($row['total_qty']) ? (float)$row['total_qty'] : 0.0;
									$value  = isset($row['total_value']) ? (float)$row['total_value'] : 0.0;
									$expend = isset($row['total_expend']) ? (float)$row['total_expend'] : 0.0;
									$net    = isset($row['total_net']) ? (float)$row['total_net'] : 0.0;
									$count  = isset($row['row_count']) ? (int)$row['row_count'] : 0;

									$daily_summary[$ruDate]['total_qty']    += $qty;
									$daily_summary[$ruDate]['total_value']  += $value;
									$daily_summary[$ruDate]['total_expend'] += $expend;
									$daily_summary[$ruDate]['total_net']    += $net;
									$daily_summary[$ruDate]['row_count']    += $count;

									if ($daily_summary[$ruDate]['pr_price'] === null && isset($row['pr_price']) && $row['pr_price'] !== null) {
										$daily_summary[$ruDate]['pr_price'] = (float)$row['pr_price'];
									}
								}
							}

							$sum_total_qty    = 0.0;
							$sum_total_value  = 0.0;
							$sum_total_expend = 0.0;
							$sum_total_net    = 0.0;
							$sum_total_rows   = 0;

								// นับจำนวน "คน" (ไม่ซ้ำ) ต่อวัน แยก member/general
								// member: นับตามเลขสมาชิก (ru_number)
								// general: นับตามชื่อ-สกุล (ru_fullname) (ถ้าชื่อซ้ำให้นับเป็นคนเดียวกัน)
								$people_count_by_date = []; // [ru_date => ['member'=>int,'general'=>int]]
								$countSql = "SELECT
									r.ru_date,
									COUNT(DISTINCT CASE WHEN LOWER(r.ru_class) = 'member' THEN TRIM(r.ru_number) END)    AS member_people,
									COUNT(DISTINCT CASE WHEN LOWER(r.ru_class) = 'general' THEN TRIM(r.ru_fullname) END) AS general_people
								FROM tbl_rubber r
								$sum_where_sql
								GROUP BY r.ru_date";
								$countStmt = $db->prepare($countSql);
								if ($countStmt) {
									if (!empty($sum_params)) {
										$countStmt->bind_param($sum_types, ...$sum_params);
									}
									$countStmt->execute();
									$countRes = $countStmt->get_result();
									if ($countRes) {
										while ($cr = $countRes->fetch_assoc()) {
											$d = (string)($cr['ru_date'] ?? '');
											if ($d === '') continue;
											$people_count_by_date[$d] = [
												'member'  => isset($cr['member_people']) ? (int)$cr['member_people'] : 0,
												'general' => isset($cr['general_people']) ? (int)$cr['general_people'] : 0,
											];
										}
										$countRes->free();
									}
									$countStmt->close();
								}
							?>

							<?php if (!empty($daily_summary)): ?>
								<?php foreach ($daily_summary as $ruDate => $row): ?>
									<?php
									$qty    = (float)$row['total_qty'];
									$value  = (float)$row['total_value'];
									$expend = (float)$row['total_expend'];
									$net    = (float)$row['total_net'];
									$count  = (int)$row['row_count'];

									$sum_total_qty    += $qty;
									$sum_total_value  += $value;
									$sum_total_expend += $expend;
									$sum_total_net    += $net;
									$sum_total_rows   += $count;

									$price = $row['pr_price'] !== null ? (float)$row['pr_price'] : null;
									if ($price === null && $qty > 0) {
										$price = $value / $qty;
									}

									$member_count  = $people_count_by_date[$ruDate]['member'] ?? 0;
									$general_count = $people_count_by_date[$ruDate]['general'] ?? 0;
									?>
									<tr class="text-center">
										<td class="text-nowrap"><?php echo htmlspecialchars($ruDate ? thai_date_format((string)$ruDate) : '-'); ?></td>
										<td><?php echo $price !== null ? number_format($price, 2) : '-'; ?></td>
										<td><?php echo number_format($qty, 2); ?></td>
										<td><?php echo number_format($value, 2); ?></td>
										<td><?php echo number_format($expend, 2); ?></td>
										<td class="fw-semibold"><?php echo number_format($net, 2); ?></td>
										<td><?php echo number_format($count); ?></td>
										<td><?php echo number_format($member_count); ?>/<?php echo number_format($general_count); ?></td>
									</tr>
								<?php endforeach; ?>
								<tr class="table-success fw-bold text-center">
									<td colspan="2">รวมตามตัวกรอง</td>
									<td><?php echo number_format($sum_total_qty, 2); ?></td>
									<td><?php echo number_format($sum_total_value, 2); ?></td>
									<td><?php echo number_format($sum_total_expend, 2); ?></td>
									<td><?php echo number_format($sum_total_net, 2); ?></td>
									<td><?php echo number_format($sum_total_rows); ?></td>
									<td>-</td>
								</tr>
							<?php else: ?>
								<tr>
									<td colspan="8" class="text-center text-muted py-4">ยังไม่มีข้อมูลสรุปในช่วงที่เลือก</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<div class="text-center mt-2 py-2">
		<div class="text-muted mb-3 fs-5">ต้องการเพิ่มข้อมูลการรวบรวมยาง?</div>
			<a href="<?php echo htmlspecialchars($target); ?>" class="btn btn-success btn-lg rounded-pill px-4 shadow-sm fw-semibold">
				<i data-lucide="plus-circle" class="me-2" aria-hidden="true"></i>บันทึกข้อมูล
			</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
  const section = document.getElementById('liveRoundSection');
  if (!section) return;

  const qtyNode = document.getElementById('liveRoundQuantity');
  const deductNode = document.getElementById('liveRoundDeduct');
  const netNode = document.getElementById('liveRoundNet');
  const updatedNode = document.getElementById('liveRoundUpdatedAt');
  const numberFmt = new Intl.NumberFormat('th-TH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  async function refreshLiveRound() {
    try {
      const res = await fetch('rubber_round_live_summary.php', { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();

      if (!data || !data.show) {
        section.classList.add('d-none');
        return;
      }

      section.classList.remove('d-none');
      if (qtyNode) qtyNode.textContent = numberFmt.format(Number(data.total_quantity || 0)) + ' kg';
      if (deductNode) deductNode.textContent = numberFmt.format(Number(data.total_expend || 0)) + ' ฿';
      if (netNode) netNode.textContent = numberFmt.format(Number(data.total_net || 0)) + ' ฿';
      if (updatedNode && data.updated_at_text) updatedNode.textContent = data.updated_at_text;
    } catch (error) {
      // keep the latest known values if refresh fails
    }
  }

  refreshLiveRound();
  window.setInterval(refreshLiveRound, 300000);
})();

(function() {
  const chartLabels = <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const chartQuantities = <?php echo json_encode($chartQuantities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const chartValues = <?php echo json_encode($chartValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const chartMemberCounts = <?php echo json_encode($chartMemberCounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const chartGeneralCounts = <?php echo json_encode($chartGeneralCounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const quantityCanvas = document.getElementById('quantityChart');
  const valueCanvas = document.getElementById('valueChart');
  const memberCountCanvas = document.getElementById('memberCountChart');
  const generalCountCanvas = document.getElementById('generalCountChart');

  if (!window.Chart || !quantityCanvas || !valueCanvas || !memberCountCanvas || !generalCountCanvas) {
    return;
  }

  const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: {
        labels: {
          usePointStyle: true,
          boxWidth: 10
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            const value = context.parsed.y ?? 0;
            return context.dataset.label + ': ' + new Intl.NumberFormat('th-TH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            }).format(value);
          }
        }
      }
    },
    scales: {
      x: {
        ticks: {
          maxRotation: 45,
          minRotation: 0,
          autoSkip: true
        },
        grid: {
          color: 'rgba(34, 197, 94, 0.08)'
        }
      },
      y: {
        beginAtZero: true,
        grid: {
          color: 'rgba(34, 197, 94, 0.08)'
        }
      }
    }
  };

  new Chart(quantityCanvas.getContext('2d'), {
    type: 'line',
    data: {
      labels: chartLabels,
      datasets: [{
        label: 'ปริมาณยาง (กก.)',
        data: chartQuantities,
        borderColor: '#16a34a',
        backgroundColor: 'rgba(22, 163, 74, 0.18)',
        pointBackgroundColor: '#16a34a',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 3,
        tension: 0.25,
        fill: true
      }]
    },
    options: commonOptions
  });

  new Chart(valueCanvas.getContext('2d'), {
    type: 'line',
    data: {
      labels: chartLabels,
      datasets: [{
        label: 'จำนวนเงิน (บาท)',
        data: chartValues,
        borderColor: '#0ea5e9',
        backgroundColor: 'rgba(14, 165, 233, 0.16)',
        pointBackgroundColor: '#0ea5e9',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 3,
        tension: 0.25,
        fill: true
      }]
    },
    options: commonOptions
  });

  new Chart(memberCountCanvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels: chartLabels,
      datasets: [{
        label: 'จำนวนสมาชิก (คน)',
        data: chartMemberCounts,
        borderColor: '#16a34a',
        backgroundColor: 'rgba(22, 163, 74, 0.28)',
        borderWidth: 1,
        borderRadius: 8
      }]
    },
    options: commonOptions
  });

  new Chart(generalCountCanvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels: chartLabels,
      datasets: [{
        label: 'จำนวนเกษตรกรทั่วไป (คน)',
        data: chartGeneralCounts,
        borderColor: '#f59e0b',
        backgroundColor: 'rgba(245, 158, 11, 0.28)',
        borderWidth: 1,
        borderRadius: 8
      }]
    },
    options: commonOptions
  });
})();
</script>

<?php
include 'footer.php';
?>

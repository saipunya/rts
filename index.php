<?php
require_once 'functions.php';
include 'header.php';

// added: use db() instead of undefined $mysqli
$db = db();
?>

<style>
	.index-toolbar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 1rem;
		flex-wrap: wrap;
		padding-bottom: .75rem;
		border-bottom: 1px solid rgba(148,163,184,0.35);
		margin-bottom: 1.25rem;
	}
	.index-toolbar h2 {
		font-size: 1.65rem;
		font-weight: 700;
		margin: 0;
		letter-spacing: .02em;
		color: #0f172a;
	}
	.index-actions {
		display: flex;
		flex-wrap: wrap;
		gap: .5rem;
		align-items: center;
		justify-content: flex-end;
	}
	.stat-card {
		border-radius: 1rem;
		border: 1px solid rgba(226,232,240,0.95);
		box-shadow: 0 10px 24px rgba(15,23,42,0.04);
		padding: 1.1rem 1.25rem;
		height: 100%;
		background: #fff;
	}
	.stat-label {
		color: #64748b;
		font-size: 1.15rem;
		display: flex;
		align-items: center;
		gap: .5rem;
		margin-bottom: .35rem;
	}
	.stat-value {
		font-size: 2.1rem;
		font-weight: 800;
		color: #0f172a;
		line-height: 1.1;
	}
	.stat-sub {
		color: #64748b;
		font-size: 1.05rem;
		margin-top: .25rem;
	}
	.filter-bar {
		display: flex;
		flex-wrap: wrap;
		gap: .5rem;
		align-items: end;
		justify-content: space-between;
		margin: 1rem 0 1.25rem;
		padding: .9rem 1rem;
		border-radius: 1rem;
		border: 1px solid rgba(226,232,240,0.95);
		background: #f8fafc;
	}
	.filter-bar .form-label {
		margin-bottom: .15rem;
		color: #475569;
		font-weight: 600;
	}
	.filter-bar .form-control {
		min-width: 220px;
	}
	.filter-actions {
		display: flex;
		gap: .5rem;
		flex-wrap: wrap;
	}
	@media (max-width: 768px) {
		.filter-bar .form-control { min-width: 100%; }
		.index-actions { width: 100%; justify-content: flex-start; }
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

// Query ปริมาณรวมและยอดเงินรวมของทุกลานจากฐานข้อมูลโดยตรง (ไม่ใช้ LIMIT)
$all_total_quantity = 0;
$all_total_value = 0;
$all_stats = $db->query("SELECT SUM(ru_quantity) as total_qty FROM tbl_rubber");
if ($all_stats) {
    $row = $all_stats->fetch_assoc();
    $all_total_quantity = $row['total_qty'] ? (float)$row['total_qty'] : 0;
    // คำนวณยอดเงินรวมด้วยราคายางล่าสุด (เหมือนกับการคำนวณวันที่ราคายาง)
    $all_total_value = $all_total_quantity * $latest_price;
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
?>

	<?php
	if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
	$logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['member_id']);
	$username = $_SESSION['username'] ?? ($_SESSION['user_name'] ?? '');
	$target = $logged_in ? 'rubbers.php?lan=all' : 'login.php?redirect=' . urlencode('rubbers.php?lan=all');
	?>

	<div class="index-toolbar">
		<h2 class="d-flex align-items-center gap-2">
			<i class="bi bi-grid-1x2-fill text-primary"></i>
			ภาพรวมวันนี้
		</h2>
		<div class="index-actions">
			<a class="btn btn-outline-secondary" href="prices.php"><i class="bi bi-cash-coin me-1"></i>ราคายาง</a>
			<a class="btn btn-outline-secondary" href="dashboard.php"><i class="bi bi-gear me-1"></i>ตั้งค่า</a>
			<a class="btn btn-primary" href="<?php echo htmlspecialchars($target); ?>"><i class="bi bi-plus-circle me-1"></i>บันทึกข้อมูล</a>
			<?php if ($logged_in): ?>
				<a class="btn btn-outline-danger" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>ออกจากระบบ<?php echo $username ? ' ('.htmlspecialchars($username).')' : ''; ?></a>
			<?php else: ?>
				<a class="btn btn-outline-primary" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</a>
			<?php endif; ?>
		</div>
	</div>

	<form class="filter-bar" method="get" action="index.php">
		<div class="d-flex flex-wrap gap-3 align-items-end" style="flex:1;">
			<div>
				<label class="form-label" for="date_from">วันที่เริ่มต้น</label>
				<input id="date_from" name="date_from" type="date" value="<?php echo htmlspecialchars($sum_date_from); ?>" class="form-control" />
			</div>
			<div>
				<label class="form-label" for="date_to">วันที่สิ้นสุด</label>
				<input id="date_to" name="date_to" type="date" value="<?php echo htmlspecialchars($sum_date_to); ?>" class="form-control" />
			</div>
			<div>
				<label class="form-label" for="lan">ลาน</label>
				<select id="lan" name="lan" class="form-control">
					<option value="all" <?php echo $sum_lan === 'all' ? 'selected' : ''; ?>>ทุกลาน</option>
					<option value="1" <?php echo $sum_lan === '1' ? 'selected' : ''; ?>>ลาน 1</option>
					<option value="2" <?php echo $sum_lan === '2' ? 'selected' : ''; ?>>ลาน 2</option>
					<option value="3" <?php echo $sum_lan === '3' ? 'selected' : ''; ?>>ลาน 3</option>
					<option value="4" <?php echo $sum_lan === '4' ? 'selected' : ''; ?>>ลาน 4</option>
				</select>
			</div>
		</div>
		<div class="filter-actions">
			<button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>แสดงสรุป</button>
			<a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-x-circle me-1"></i>ล้าง</a>
		</div>
	</form>

	<!-- Quick stats -->
	<div class="row g-3 mb-4">
		<div class="col-12 col-md-6 col-lg-4">
			<div class="stat-card">
				<div class="stat-label"><i class="bi bi-tag-fill text-primary"></i>ราคาที่ใช้คำนวณ</div>
				<div class="stat-value"><?php echo number_format($latest_price,2); ?> ฿</div>
				<div class="stat-sub">อัปเดต: <span class="fw-semibold text-danger"><?php echo $latest_price_date_text; ?></span></div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-lg-4">
			<div class="stat-card">
				<div class="stat-label"><i class="bi bi-box-seam-fill text-success"></i>ปริมาณรวม (ทุกลาน)</div>
				<div class="stat-value"><?php echo number_format($all_total_quantity,2); ?> kg</div>
				<div class="stat-sub">จำนวนรายการรับซื้อ: <?php echo number_format($total_records); ?> รายการ</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-lg-4">
			<div class="stat-card">
				<div class="stat-label"><i class="bi bi-currency-dollar text-warning"></i>ยอดเงินรวม (ทุกลาน)</div>
				<div class="stat-value"><?php echo number_format($all_total_value,2); ?> ฿</div>
				<div class="stat-sub">คำนวณจากราคาล่าสุด</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-lg-4">
			<div class="stat-card">
				<div class="stat-label"><i class="bi bi-calendar2-check-fill text-info"></i>ปริมาณรวม (วันที่ราคายาง)</div>
				<div class="stat-value"><?php echo number_format($price_date_total_quantity,2); ?> kg</div>
				<div class="stat-sub">อ้างอิงวันที่ราคายาง</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-lg-4">
			<div class="stat-card">
				<div class="stat-label"><i class="bi bi-receipt-cutoff text-danger"></i>ยอดเงินรวม (วันที่ราคายาง)</div>
				<div class="stat-value"><?php echo number_format($price_date_total_value,2); ?> ฿</div>
				<div class="stat-sub">ปริมาณ x ราคาล่าสุด</div>
			</div>
		</div>
	</div>
	<!-- ปริมาณรวบรวมแต่ละลาน -->
	<div class="row mb-4">
		<div class="col-12">
			<div class="section-title"><i class="bi bi-bar-chart-line-fill"></i>ปริมาณรวบรวมแต่ละลาน</div>
			<div class="card-table">
				<table class="table table-hover table-sm mb-0">
					<thead>
						<tr class="text-center">
							<th>ลาน</th>
							<th>ปริมาณรวม (kg)</th>
							<th>ยอดเงินรวม (฿)</th>
						</tr>
					</thead>
					<tbody>
					<?php
					// Query รวมปริมาณแต่ละลานจากฐานข้อมูลโดยตรง (ไม่ใช้ LIMIT)
					$lan_query = "SELECT ru_lan, SUM(ru_quantity) as total_qty FROM tbl_rubber GROUP BY ru_lan ORDER BY ru_lan ASC";
					$lan_res = $db->query($lan_query);
					$grand_total_qty = 0;
					$grand_total_value = 0;
					if ($lan_res) {
						while ($lan_row = $lan_res->fetch_assoc()) {
							$lan = $lan_row['ru_lan'] ?? '-';
							$qty = (float)$lan_row['total_qty'];
							$value = $qty * $latest_price; // คำนวณยอดเงินด้วยราคายางล่าสุด
							
							// สะสมยอดรวม
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
	
	<!-- Daily/Lan summary table -->
	<div class="row">
		<div class="col-12">
			<div class="section-title"><i class="bi bi-clipboard-data"></i>สรุปรับซื้อแยกตามวันที่/ลาน</div>
			<div class="card-table">
				<div class="table-responsive">
					<table class="table table-striped table-hover w-100 mb-0">
						<thead>
							<tr class="text-center">
								<th>วันที่</th>
								<th>ลาน</th>
								<th>ราคา (฿/kg)</th>
								<th>ปริมาณรวม (kg)</th>
								<th>เงินรวม (฿)</th>
								<th>ยอดหัก (฿)</th>
								<th>คงเหลือ/สุทธิ (฿)</th>
								<th>รายการ</th>
								<th>ดูรายละเอียด</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$sum_total_qty = 0.0;
							$sum_total_value = 0.0;
							$sum_total_expend = 0.0;
							$sum_total_net = 0.0;
							$sum_total_rows = 0;
							?>
							<?php if (!empty($summary_rows)): ?>
								<?php foreach ($summary_rows as $row): ?>
									<?php
									$qty = isset($row['total_qty']) ? (float)$row['total_qty'] : 0.0;
									$value = isset($row['total_value']) ? (float)$row['total_value'] : 0.0;
									$expend = isset($row['total_expend']) ? (float)$row['total_expend'] : 0.0;
									$net = isset($row['total_net']) ? (float)$row['total_net'] : 0.0;
									$sum_total_qty += $qty;
									$sum_total_value += $value;
									$sum_total_expend += $expend;
									$sum_total_net += $net;
									$sum_total_rows += isset($row['row_count']) ? (int)$row['row_count'] : 0;
									$price = isset($row['pr_price']) && $row['pr_price'] !== null ? (float)$row['pr_price'] : null;
									if ($price === null && $qty > 0) {
										$price = $value / $qty;
									}
									$ruDate = $row['ru_date'] ?? '';
									$lan = $row['ru_lan'] ?? '';
									$detailUrl = 'rubbers.php?lan=' . urlencode((string)$lan) . '&date_from=' . urlencode((string)$ruDate) . '&date_to=' . urlencode((string)$ruDate);
									?>
									<tr class="text-center">
										<td class="text-nowrap"><?php echo htmlspecialchars($ruDate ? thai_date_format((string)$ruDate) : '-'); ?></td>
										<td class="text-nowrap"><?php echo htmlspecialchars((string)$lan); ?></td>
										<td><?php echo $price !== null ? number_format((float)$price, 2) : '-'; ?></td>
										<td><?php echo number_format($qty, 2); ?></td>
										<td><?php echo number_format($value, 2); ?></td>
										<td><?php echo number_format($expend, 2); ?></td>
										<td class="fw-semibold"><?php echo number_format($net, 2); ?></td>
										<td><?php echo number_format((int)($row['row_count'] ?? 0)); ?></td>
										<td><a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($detailUrl); ?>">เปิด</a></td>
									</tr>
								<?php endforeach; ?>
								<tr class="table-success fw-bold text-center">
									<td colspan="3">รวมตามตัวกรอง</td>
									<td><?php echo number_format($sum_total_qty, 2); ?></td>
									<td><?php echo number_format($sum_total_value, 2); ?></td>
									<td><?php echo number_format($sum_total_expend, 2); ?></td>
									<td><?php echo number_format($sum_total_net, 2); ?></td>
									<td><?php echo number_format($sum_total_rows); ?></td>
									<td></td>
								</tr>
							<?php else: ?>
								<tr>
									<td colspan="9" class="text-center text-muted py-4">ยังไม่มีข้อมูลสรุปในช่วงที่เลือก</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<div class="text-center mt-4">
		<div class="text-muted mb-2">ต้องการเพิ่มข้อมูลการรวบรวมยาง?</div>
		<a href="<?php echo htmlspecialchars($target); ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>บันทึกข้อมูล</a>
	</div>

<?php
include 'footer.php';
?>
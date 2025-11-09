<?php
require_once 'functions.php';
include 'header.php';
?>

<style>
:root{
  --bg-green: #f3fbf5;
  --accent-green: #2e7d32;
  --muted-green: #a5d6a7;
  --card-shadow: 0 6px 18px rgba(46,125,50,0.08);
}
body {
  background-color: var(--bg-green);
}
.header-lead { color: #155724; }
.card.stat { border: none; box-shadow: var(--card-shadow); border-radius:12px; }
.card.stat .value { font-weight:700; color: var(--accent-green); }
.table thead { background: linear-gradient(90deg, rgba(165,214,167,0.25), rgba(243,251,245,0.25)); }
.table tbody tr:hover { background-color: rgba(46,125,50,0.04); }
.form-inline .form-control { min-width:150px; }
.btn-cta { background: linear-gradient(90deg, var(--muted-green), var(--accent-green)); border: none; color: #fff; }
.small-note { color: rgba(0,0,0,0.6); font-size:0.95rem; }
@media (max-width: 576px){ .form-inline { display:block; } .form-inline .form-control { width:100%; margin-bottom:8px; } }
</style>

<?php
// Sample data (would be replaced with DB calls in production)
$listings = [
	['id'=>1,'seller'=>'Chaichan Farm','type'=>'Natural','quantity'=>2000,'unit'=>'kg','price'=>45,'location'=>'Surin','posted'=>'2025-11-01'],
	['id'=>2,'seller'=>'Srisuk Co.','type'=>'RSS','quantity'=>1200,'unit'=>'kg','price'=>48,'location'=>'Nakhon Ratchasima','posted'=>'2025-11-03'],
];
// late price from tbl_price
$stmt = $mysqli->prepare("SELECT pr_price FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1");
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


$stmt = $mysqli->prepare("SELECT pr_date FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1");
if ($stmt) {
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	$latest_price_date = $row ? $row['pr_date'] : null;
}

// Read filters from GET
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$filter_location = isset($_GET['location']) ? trim($_GET['location']) : '';
$filter_min = isset($_GET['min_qty']) && is_numeric($_GET['min_qty']) ? (int)$_GET['min_qty'] : null;
$filter_max = isset($_GET['max_qty']) && is_numeric($_GET['max_qty']) ? (int)$_GET['max_qty'] : null;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Simple filtering
$filtered = array_filter($listings, function($l) use ($filter_type, $filter_location, $filter_min, $filter_max, $search) {
	if ($filter_type !== '' && strcasecmp($l['type'], $filter_type) !== 0) return false;
	if ($filter_location !== '' && stripos($l['location'], $filter_location) === false) return false;
	if ($filter_min !== null && $l['quantity'] < $filter_min) return false;
	if ($filter_max !== null && $l['quantity'] > $filter_max) return false;
	if ($search !== '' && stripos($l['seller'].' '.$l['type'].' '.$l['location'], $search) === false) return false;
	return true;
});

// Stats
$total_listings = count($filtered);
$total_quantity = array_reduce($filtered, function($carry, $item){ return $carry + $item['quantity']; }, 0);
$avg_price = $total_listings ? round(array_reduce($filtered, function($c,$i){return $c+$i['price'];},0)/$total_listings,2) : 0;
?>

<div class="container-xl my-5">
	<!-- Quick stats -->
	<div class="row mb-3">
		<div class="col-sm-4 mb-3">
			<div class="card stat p-3 text-center">
				<div class="mb-1 text-muted">ราคาที่ใช้คำนวณ <span class="text-danger fw-bold"><?php echo number_format($latest_price, 2); ?> ฿</span>(<?php echo thai_date_format($latest_price_date); ?>)</div>
				<div class="value display-4">
					<?php echo number_format($latest_price,2); ?> ฿
				</div>
			</div>
		</div>
		<div class="col-sm-4 mb-3">
			<div class="card stat p-3 text-center">
				<div class="mb-1 text-muted">ปริมาณรวม</div>
				<div class="value display-4"> 1,300 kg</div>
			</div>
		</div>
		<div class="col-sm-4 mb-3">
			<div class="card stat p-3 text-center">
				<div class="mb-1 text-muted">ยอดเงินรวม</div>
				<div class="value display-4">12,500 ฿</div>
			</div>
		</div>
	</div>

	
	<!-- Listings table -->
	<div class="row">
		<div class="col-12">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th>#</th>
								<th>ผู้ขาย</th>
								<th>ประเภท</th>
								<th>ปริมาณ</th>
                                <th>จำนวนเงิน</th>
								<th>รายการหัก</th>
								<th>ประกาศเมื่อ</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($filtered as $item): ?>
								<tr>
									<td><?php echo (int)$item['id']; ?></td>
									<td><?php echo htmlspecialchars($item['seller']); ?></td>
									<td>สมาชิก</td>
									<td><?php echo number_format($item['quantity']) . ' ' . htmlspecialchars($item['unit']); ?></td>
									<td><?php echo htmlspecialchars(number_format($item['price'],2)); ?></td>
									<td><?php echo htmlspecialchars($item['location']); ?></td>
									<td><?php echo htmlspecialchars($item['posted']); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
		</div>
	</div>

	<!-- Call to action -->
	<div class="row mt-4">
		<div class="col-12 text-center">
			<p>ต้องการเพิ่มข้อมูลการรวบรวมยาง? <a href="#" class="btn btn-cta">บันทึกข้อมูล</a></p>
		</div>
	</div>
</div>

<?php
include 'footer.php';
?>
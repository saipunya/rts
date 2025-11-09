<?php
include 'header.php';
?>         

<?php
// Sample data (would be replaced with DB calls in production)
$listings = [
	['id'=>1,'seller'=>'Chaichan Farm','type'=>'Natural','quantity'=>2000,'unit'=>'kg','price'=>45,'location'=>'Surin','posted'=>'2025-11-01'],
	['id'=>2,'seller'=>'Srisuk Co.','type'=>'RSS','quantity'=>1200,'unit'=>'kg','price'=>48,'location'=>'Nakhon Ratchasima','posted'=>'2025-11-03'],
	['id'=>3,'seller'=>'Banpong Group','type'=>'Latex','quantity'=>500,'unit'=>'kg','price'=>50,'location'=>'Rayong','posted'=>'2025-10-28'],
	['id'=>4,'seller'=>'GreenTree','type'=>'Natural','quantity'=>3000,'unit'=>'kg','price'=>44,'location'=>'Surin','posted'=>'2025-11-05'],
	['id'=>5,'seller'=>'RubberCo','type'=>'RSS','quantity'=>800,'unit'=>'kg','price'=>47,'location'=>'Chumphon','posted'=>'2025-11-02'],
];

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

<div class="container mt-4">
	<div class="row">
		<div class="col-12 text-center mb-4">
			<h1>ระบบการซื้อขาย รวบรวม ยาง</h1>
			<p class="lead">รวมประกาศซื้อขายยางจากเกษตรกรและผู้รวบรวม — ค้นหาและติดต่อผู้ขายได้ตรง</p>
		</div>
	</div>

	<!-- Quick stats -->
	<div class="row mb-3">
		<div class="col-sm-4">
			<div class="card p-3">
				<h5 class="mb-0">รายการที่พบ</h5>
				<p class="display-4 mb-0"><?php echo $total_listings; ?></p>
			</div>
		</div>
		<div class="col-sm-4">
			<div class="card p-3">
				<h5 class="mb-0">ปริมาณรวม</h5>
				<p class="display-4 mb-0"><?php echo number_format($total_quantity); ?> kg</p>
			</div>
		</div>
		<div class="col-sm-4">
			<div class="card p-3">
				<h5 class="mb-0">ราคาเฉลี่ย</h5>
				<p class="display-4 mb-0"><?php echo $avg_price; ?> ฿/kg</p>
			</div>
		</div>
	</div>

	<!-- Search / filter form -->
	<div class="row mb-4">
		<div class="col-12">
			<form method="get" class="form-inline">
				<input type="text" name="q" class="form-control mr-2 mb-2" placeholder="ค้นหา (ผู้ขาย, ประเภท, จังหวัด)" value="<?php echo htmlspecialchars($search); ?>">
				<select name="type" class="form-control mr-2 mb-2">
					<option value="">ทั้งหมดประเภท</option>
					<option value="Natural" <?php if($filter_type==='Natural') echo 'selected'; ?>>Natural</option>
					<option value="RSS" <?php if($filter_type==='RSS') echo 'selected'; ?>>RSS</option>
					<option value="Latex" <?php if($filter_type==='Latex') echo 'selected'; ?>>Latex</option>
				</select>
				<input type="text" name="location" class="form-control mr-2 mb-2" placeholder="จังหวัด" value="<?php echo htmlspecialchars($filter_location); ?>">
				<input type="number" name="min_qty" class="form-control mr-2 mb-2" placeholder="ขั้นต่ำ kg" value="<?php echo $filter_min !== null ? $filter_min : ''; ?>">
				<input type="number" name="max_qty" class="form-control mr-2 mb-2" placeholder="สูงสุด kg" value="<?php echo $filter_max !== null ? $filter_max : ''; ?>">
				<button type="submit" class="btn btn-primary mb-2">ค้นหา</button>
				<a href="index.php" class="btn btn-secondary mb-2 ml-2">รีเซ็ต</a>
			</form>
		</div>
	</div>

	<!-- Listings table -->
	<div class="row">
		<div class="col-12">
			<?php if ($total_listings === 0): ?>
				<div class="alert alert-info">ไม่พบรายการตามเงื่อนไขที่ค้นหา</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-striped">
						<thead>
							<tr>
								<th>#</th>
								<th>ผู้ขาย</th>
								<th>ประเภท</th>
								<th>ปริมาณ</th>
								<th>ราคา (฿/kg)</th>
								<th>จังหวัด</th>
								<th>ประกาศเมื่อ</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($filtered as $item): ?>
								<tr>
									<td><?php echo (int)$item['id']; ?></td>
									<td><?php echo htmlspecialchars($item['seller']); ?></td>
									<td><?php echo htmlspecialchars($item['type']); ?></td>
									<td><?php echo number_format($item['quantity']) . ' ' . htmlspecialchars($item['unit']); ?></td>
									<td><?php echo htmlspecialchars($item['price']); ?></td>
									<td><?php echo htmlspecialchars($item['location']); ?></td>
									<td><?php echo htmlspecialchars($item['posted']); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Call to action -->
	<div class="row mt-4">
		<div class="col-12 text-center">
			<p>ต้องการลงประกาศขายหรือรวบรวมยาง? <a href="#" class="btn btn-success">สร้างประกาศใหม่</a></p>
		</div>
	</div>
</div>

<?php
include 'footer.php';
?>
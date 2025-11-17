<?php
require_once 'functions.php';
include 'header.php';

// added: use db() instead of undefined $mysqli
$db = db();
?>

<style>
	*{
		font-size: 1.2rem;
	}
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
.small-note { color: rgba(0,0,0,0.6); font-size:1.5rem; }
@media (max-width: 576px){ .form-inline { display:block; } .form-inline .form-control { width:100%; margin-bottom:8px; } }
</style>

<?php
// Load recent entries from tbl_rubber and map to listing fields used by the table
$listings = [];
$res = $db->query("SELECT ru_id, ru_fullname, ru_class, ru_quantity, ru_netvalue, ru_group, ru_expend, ru_number, ru_date FROM tbl_rubber ORDER BY ru_date DESC, ru_id DESC LIMIT 200");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $listings[] = [
            'id' => (int)$row['ru_id'],
            'seller' => $row['ru_fullname'],
            'member_no' => $row['ru_number'],
            'type' => $row['ru_class'],
            'quantity' => (float)$row['ru_quantity'],
            'unit' => 'kg',
            'price' => (float)$row['ru_netvalue'],
            'location' => $row['ru_group'],
            'deductions' => isset($row['ru_expend']) ? (float)$row['ru_expend'] : 0.0,
            'posted' => $row['ru_date'],
        ];
    }
    $res->free();
}

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

// added: compute latest rubber collection date and totals for that date
$stmt = $db->prepare("SELECT ru_date FROM tbl_rubber ORDER BY ru_date DESC, ru_id DESC LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $latest_rubber_date = $row ? $row['ru_date'] : null;
} else {
    $latest_rubber_date = null;
}
$latest_rubber_date_text = $latest_rubber_date ? thai_date_format($latest_rubber_date) : '-';

// Sum totals for the latest collection round (match by ru_date)
$latest_total_quantity = 0;
$latest_total_value = 0;
if ($latest_rubber_date) {
    foreach ($listings as $it) {
        if ($it['posted'] === $latest_rubber_date) {
            $latest_total_quantity += $it['quantity'];
            $latest_total_value += $it['price'];
        }
    }
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
	<?php
	if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
	$logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['member_id']);
	$username = $_SESSION['username'] ?? ($_SESSION['user_name'] ?? '');
	?>
	<div class="d-flex justify-content-end mb-3">
		<ul class="nav">
			<li class="nav-item"><a class="nav-link" href="dashboard.php">หน้าจัดการข้อมูล</a></li>
			<?php if ($logged_in): ?>
				<li class="nav-item"><a class="nav-link" href="rubbers.php?lan=all">ข้อมูลยางทั้งหมด</a></li>
				<li class="nav-item"><a class="nav-link" href="logout.php">ออกจากระบบ<?php echo $username ? ' ('.htmlspecialchars($username).')' : ''; ?></a></li>
			<?php else: ?>
				<li class="nav-item"><a class="nav-link" href="login.php">เข้าสู่ระบบ</a></li> 
			<?php endif; ?>
		</ul>
	</div>
	<!-- Quick stats -->
	<div class="row mb-3">
		<div class="col-sm-4 mb-3">
			<div class="card stat p-3 text-center">
				<div class="mb-1 text-muted">ราคาที่ใช้คำนวณ (<span class="text-danger fw-bold"><?php echo $latest_price_date_text; ?></span>)</div> <!-- changed: use pre-formatted text -->
				<div class="value display-4">
					<?php echo number_format($latest_price,2); ?> ฿
				</div>
			</div>
		</div>
		<div class="col-sm-4 mb-3">
			<div class="card stat p-3 text-center">
				<div class="mb-1 text-muted">ปริมาณรวม</div>
				<div class="value display-4"> <?php echo number_format($latest_total_quantity,2); ?> kg</div>
			</div>
		</div>
		<div class="col-sm-4 mb-3">
			<div class="card stat p-3 text-center">
				<div class="mb-1 text-muted">ยอดเงินรวม</div>
				<div class="value display-4"><?php echo number_format($latest_total_value,2); ?> ฿</div>
			</div>
		</div>
	</div>

	
	<!-- Listings table -->
	<div class="row">
		<div class="col-12">
				<div class="table-responsive">
					<table class="table table-striped table-hover datatable w-100">
						<thead>
							<tr>
									<th>เลขที่สมาชิก</th>
									<th>ผู้ขาย</th>
									<th>ประเภท</th>
								<th>ปริมาณ</th>
                                <th>จำนวนเงิน</th>
								<th>รายการหัก</th>
								<th>เมื่อ</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($filtered as $item): ?>
									<tr>
									<td><?php echo htmlspecialchars($item['member_no'] ?? '-'); ?></td>
									<td class="text-nowrap"><?php echo htmlspecialchars($item['seller']); ?></td>
									<td><?php echo htmlspecialchars($item['type']); ?></td>
								<td><?php echo number_format($item['quantity']) . ' ' . htmlspecialchars($item['unit']); ?></td>
								<td><?php echo htmlspecialchars(number_format($item['price'],2)); ?></td>
								<td><?php echo number_format($item['deductions'],2); ?></td>
								<td><?php echo htmlspecialchars(thai_date_format($item['posted'])); ?></td>
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
			<?php
			if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
			$logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['member_id']);
			$target = $logged_in ? 'rubbers.php?lan=all' : 'login.php?redirect=' . urlencode('rubbers.php?lan=all');
			?>
			<p>ต้องการเพิ่มข้อมูลการรวบรวมยาง? <a href="<?php echo htmlspecialchars($target); ?>" class="btn btn-cta">บันทึกข้อมูล</a></p>
		</div>
	</div>
</div>

<?php
include 'footer.php';
?>
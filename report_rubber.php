<?php
include "functions.php";
include "header.php";

// รับค่าจากฟอร์ม
$keyword = $_GET['keyword'] ?? '';
$date_start = $_GET['date_start'] ?? '';
$date_end = $_GET['date_end'] ?? '';

?>
<div class="container mt-4">
    <h3>รายงานข้อมูลยางพารา (ค้นหา)</h3>
    <form class="row g-3 mb-4" method="get">
        <div class="col-md-4">
            <label class="form-label">ชื่อ-สกุล หรือ รหัสสมาชิก</label>
            <input type="text" class="form-control" name="keyword" value="<?= e($keyword) ?>" placeholder="ค้นหาด้วยชื่อหรือรหัสสมาชิก">
        </div>
        <div class="col-md-2">
            <label class="form-label">วันที่เริ่มต้น</label>
            <input type="date" class="form-control" name="date_start" value="<?= e($date_start) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">วันที่สิ้นสุด</label>
            <input type="date" class="form-control" name="date_end" value="<?= e($date_end) ?>">
        </div>
        <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary">ค้นหา</button>
        </div>
    </form>
    <div class="mb-3">
        <a href="export_total_sale.php?keyword=<?=urlencode($keyword)?>&date_start=<?=urlencode($date_start)?>&date_end=<?=urlencode($date_end)?>" class="btn btn-success" target="_blank">
            ส่งออก PDF (สรุปยอดรวม)
        </a>
    </div>
    <?php if ($date_start || $date_end): ?>
        <div class="alert alert-info mb-3">
            <b>ช่วงวันที่ที่ค้นหา:</b>
            <?php
                if ($date_start && $date_end) {
                    echo e(thai_date_format($date_start)) . ' ถึง ' . e(thai_date_format($date_end));
                } elseif ($date_start) {
                    echo 'ตั้งแต่ ' . e(thai_date_format($date_start));
                } elseif ($date_end) {
                    echo 'ถึง ' . e(thai_date_format($date_end));
                }
            ?>
        </div>
    <?php endif; ?>
<?php
$mysqli = db();
$where = [];
$params = [];
$types = '';
if ($keyword) {
    $where[] = "(ru_fullname LIKE ? OR ru_number LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $types .= 'ss';
}
if ($date_start) {
    $where[] = "ru_date >= ?";
    $params[] = $date_start;
    $types .= 's';
}
if ($date_end) {
    $where[] = "ru_date <= ?";
    $params[] = $date_end;
    $types .= 's';
}
$where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";
$sql = "SELECT ru_fullname, ru_number, SUM(ru_quantity) AS total_quantity, SUM(ru_value) AS total_value, SUM(ru_netvalue) AS total_netvalue FROM tbl_rubber $where_sql GROUP BY ru_fullname, ru_number ORDER BY ru_fullname";
$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$results = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

if ($results) {
    echo '<div class="table-responsive"><table class="table table-bordered table-striped">';
    echo '<thead><tr><th>ชื่อ-สกุล</th><th>รหัสสมาชิก</th><th>น้ำหนักรวม (กก.)</th><th>ปริมาณยางรวม (บาท)</th><th>ยอดเงินรวม (บาท)</th></tr></thead><tbody>';
    foreach ($results as $row) {
        echo '<tr>';
        echo '<td>' . e($row['ru_fullname']) . '</td>';
        echo '<td>' . e($row['ru_number']) . '</td>';
        echo '<td class="text-end">' . number_format($row['total_quantity'], 2) . '</td>';
        echo '<td class="text-end">' . number_format($row['total_value'], 2) . '</td>';
        echo '<td class="text-end">' . number_format($row['total_netvalue'], 2) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
} else {
    echo '<div class="alert alert-warning">ไม่พบข้อมูล</div>';
}
?>
</div>
<?php include "footer.php"; ?>

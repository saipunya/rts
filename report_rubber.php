<?php
include "config.php";
include "header.php";

// รับค่าจากฟอร์ม
$name = $_GET['name'] ?? '';
$number = $_GET['ru_number'] ?? '';
$date_start = $_GET['date_start'] ?? '';
$date_end = $_GET['date_end'] ?? '';

// ฟอร์มค้นหา
?>
<div class="container mt-4">
    <h3>รายงานข้อมูลยางพารา (ค้นหา)</h3>
    <form class="row g-3 mb-4" method="get">
        <div class="col-md-3">
            <label class="form-label">ชื่อ-สกุล</label>
            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($name) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">รหัสสมาชิก</label>
            <input type="text" class="form-control" name="ru_number" value="<?= htmlspecialchars($number) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">วันที่เริ่มต้น</label>
            <input type="date" class="form-control" name="date_start" value="<?= htmlspecialchars($date_start) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">วันที่สิ้นสุด</label>
            <input type="date" class="form-control" name="date_end" value="<?= htmlspecialchars($date_end) ?>">
        </div>
        <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary">ค้นหา</button>
        </div>
    </form>
    <div class="mb-3">
        <a href="export_total_sale.php?name=<?=urlencode($name)?>&ru_number=<?=urlencode($number)?>&date_start=<?=urlencode($date_start)?>&date_end=<?=urlencode($date_end)?>" class="btn btn-success" target="_blank">
            ส่งออก PDF (สรุปยอดรวม)
        </a>
    </div>

<?php
// เงื่อนไขค้นหา
$where = [];
$params = [];
if ($name) {
    $where[] = "ru_fullname LIKE ?";
    $params[] = "%$name%";
}
if ($number) {
    $where[] = "ru_number LIKE ?";
    $params[] = "%$number%";
}
if ($date_start) {
    $where[] = "ru_date >= ?";
    $params[] = $date_start;
}
if ($date_end) {
    $where[] = "ru_date <= ?";
    $params[] = $date_end;
}
$where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// ดึงข้อมูลสรุป
$sql = "SELECT ru_fullname, ru_number, SUM(ru_quantity) AS total_quantity, SUM(ru_value) AS total_value, SUM(ru_netvalue) AS total_netvalue FROM tbl_rubber $where_sql GROUP BY ru_fullname, ru_number ORDER BY ru_fullname";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($results) {
    echo '<div class="table-responsive"><table class="table table-bordered table-striped">';
    echo '<thead><tr><th>ชื่อ-สกุล</th><th>รหัสสมาชิก</th><th>น้ำหนักรวม (กก.)</th><th>ปริมาณยางรวม (บาท)</th><th>ยอดเงินรวม (บาท)</th></tr></thead><tbody>';
    foreach ($results as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['ru_fullname']) . '</td>';
        echo '<td>' . htmlspecialchars($row['ru_number']) . '</td>';
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

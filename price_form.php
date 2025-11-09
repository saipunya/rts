<?php
// filepath: /Users/sumet/Desktop/rts/price_form.php
require_once 'functions.php';
require_admin();
include 'header.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'create';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// default values
$pr = [
    'pr_id' => 0,
    'pr_year' => date('Y') + 543, // Thai year default
    'pr_date' => date('Y-m-d'),
    'pr_number' => '',
    'pr_price' => '0.00',
];

if ($action === 'edit' && $id > 0) {
    $stmt = $mysqli->prepare("SELECT pr_id, pr_year, pr_date, pr_number, pr_price FROM tbl_price WHERE pr_id = ? LIMIT 1");
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if ($row) {
        $pr = $row;
    } else {
        header('Location: prices.php?msg=' . urlencode('ไม่พบข้อมูล')); exit;
    }
}

?>
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-8">
            <h3><?php echo ($action === 'edit') ? 'แก้ไขราคา' : 'เพิ่มราคายาง'; ?></h3>
        </div>
        <div class="col-4 text-end">
            <a href="prices.php" class="btn btn-secondary">กลับ</a>
        </div>
    </div>

    <form method="post" action="price_save.php">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
        <input type="hidden" name="pr_id" value="<?php echo (int)$pr['pr_id']; ?>">

        <div class="mb-3">
            <label class="form-label">ปี (พ.ศ.)</label>
            <input type="number" name="pr_year" class="form-control" value="<?php echo htmlspecialchars($pr['pr_year']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">วันที่</label>
            <input type="date" name="pr_date" class="form-control" value="<?php echo htmlspecialchars($pr['pr_date']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">รอบ (เช่น รอบที่ 1)</label>
            <input type="text" name="pr_number" class="form-control" value="<?php echo htmlspecialchars($pr['pr_number']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">ราคา</label>
            <input type="text" name="pr_price" class="form-control" value="<?php echo htmlspecialchars($pr['pr_price']); ?>" required>
        </div>

        <button type="submit" class="btn btn-primary"><?php echo ($action === 'edit') ? 'บันทึกการแก้ไข' : 'บันทึก'; ?></button>
    </form>
</div>

<?php include 'footer.php'; ?>

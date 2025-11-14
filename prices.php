<?php
// filepath: /Users/sumet/Desktop/rts/prices.php
require_once 'functions.php';
require_admin();
include 'header.php';

$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// fetch prices
$stmt = $mysqli->prepare("SELECT pr_id, pr_year, pr_date, pr_number, pr_price, pr_saveby, pr_savedate FROM tbl_price ORDER BY pr_date DESC, pr_id DESC");
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->execute();
$result = $stmt->get_result();
$prices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-6">
            <h3>ราคายาง (Price list)</h3>
        </div>
        <div class="col-6 text-end">
            <a href="price_form.php?action=create" class="btn btn-success">เพิ่มราคายาง</a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="card card-table">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-striped table-sm datatable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ปี</th>
                            <th>วันที่</th>
                            <th>รอบ</th>
                            <th>ราคา</th>
                            <th>บันทึกโดย</th>
                            <th>วันที่บันทึก</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prices as $p): ?>
                            <tr>
                                <td><?php echo (int)$p['pr_id']; ?></td>
                                <td><?php echo (int)$p['pr_year']; ?></td>
                                <td><?php echo htmlspecialchars($p['pr_date']); ?></td>
                                <td><?php echo htmlspecialchars($p['pr_number']); ?></td>
                                <td><?php echo number_format((float)$p['pr_price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($p['pr_saveby']); ?></td>
                                <td><?php echo htmlspecialchars($p['pr_savedate']); ?></td>
                                <td>
                                    <a href="price_form.php?action=edit&id=<?php echo (int)$p['pr_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="price_delete.php" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('ลบราคานี้หรือไม่?');">
                                        <input type="hidden" name="id" value="<?php echo (int)$p['pr_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

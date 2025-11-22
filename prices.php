<?php
// filepath: /Users/sumet/Desktop/rts/prices.php
require_once 'functions.php';
// require_admin();
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
            <h3><i class="bi bi-cash-coin me-2"></i>ราคายาง (Price list)</h3>
        </div>
        <div class="col-6 text-end">
            <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
                <a href="dashboard.php" class="btn btn-secondary me-2"><i class="bi bi-house-door me-1"></i>กลับหน้า dashboard</a>
            <?php else: ?>
                <a href="index" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>กลับหน้าหลัก</a>
            <?php endif; ?>



            <?php if (function_exists('is_admin') && is_admin()): ?>
                <a href="price_form.php?action=create" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>เพิ่มราคายาง</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="card card-table">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-striped table-sm datatable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><i class="bi bi-calendar2-week me-1"></i>ปี</th>
                            <th><i class="bi bi-calendar-date me-1"></i>วันที่</th>
                            <th><i class="bi bi-clock-history me-1"></i>รอบ</th>
                            <th><i class="bi bi-cash-stack me-1"></i>ราคา</th>
                         <!-- admin เท่่านั้นที่เห็น -->
                            <?php if (function_exists('is_admin') && is_admin()): ?>
                                <th class="no-sort"><i class="bi bi-gear me-1"></i>จัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prices as $p): ?>
                            <tr>
                                <td><?php echo (int)$p['pr_id']; ?></td>
                                <td><?php echo (int)$p['pr_year']; ?></td>
                                <td><i class="bi bi-calendar-date me-1 text-secondary"></i><?php echo thai_date_format($p['pr_date']); ?></td>
                                <td><i class="bi bi-clock-history me-1 text-secondary"></i><?php echo htmlspecialchars($p['pr_number']); ?></td>
                                <td><i class="bi bi-cash-stack me-1 text-success"></i><?php echo number_format((float)$p['pr_price'], 2); ?></td>
                                
                                <?php if (function_exists('is_admin') && is_admin()): ?>
                                    <td>
                                        <a href="price_form.php?action=edit&id=<?php echo (int)$p['pr_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="price_delete.php" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('ลบราคานี้หรือไม่?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$p['pr_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

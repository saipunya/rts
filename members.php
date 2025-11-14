<?php
require_once 'functions.php';
require_login();
include 'header.php';

// fetch messages
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// search query (optional)
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// fetch members
if ($q !== '' && mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';
    $stmt = $mysqli->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_class, mem_saveby, mem_savedate FROM tbl_member WHERE mem_fullname LIKE ? OR mem_number LIKE ? OR mem_group LIKE ? OR mem_class LIKE ? ORDER BY mem_id ASC");
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('ssss', $like, $like, $like, $like);
} else {
    $stmt = $mysqli->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_class, mem_saveby, mem_savedate FROM tbl_member ORDER BY mem_id ASC");
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
}
$stmt->execute();
$result = $stmt->get_result();
$members = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-6">
            <h3>สมาชิกสหกรณ์</h3>
        </div>
        <div class="col-6 text-end">
            <a href="member_form.php?action=create" class="btn btn-success">เพิ่มสมาชิก</a>
        </div>
    </div>

    <!-- Search form -->
    <div class="row mb-3">
        <div class="col-12">
            <form method="get" class="row g-2">
                <div class="col-auto">
                    <input type="text" name="q" class="form-control" placeholder="ค้นหาสมาชิก (อย่างน้อย 2 ตัวอักษร)" value="<?php echo htmlspecialchars($q); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">ค้นหา</button>
                    <a href="members.php" class="btn btn-secondary">ล้าง</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>กลุ่ม</th>
                    <th>เลขที่สมาชิก</th>
                    <th>ชื่อ-สกุล</th>
                    <th>ชั้น</th>
                    <th>บันทึกโดย</th>
                    <th>วันที่บันทึก</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?php echo (int)$m['mem_id']; ?></td>
                        <td><?php echo htmlspecialchars($m['mem_group']); ?></td>
                        <td><?php echo htmlspecialchars($m['mem_number']); ?></td>
                        <td><?php echo htmlspecialchars($m['mem_fullname']); ?></td>
                        <td><?php echo htmlspecialchars($m['mem_class']); ?></td>
                        <td><?php echo htmlspecialchars($m['mem_saveby']); ?></td>
                        <td><?php echo htmlspecialchars(thai_date_format($m['mem_savedate'])); ?></td>
                        <td>
                            <a href="member_form.php?action=edit&id=<?php echo (int)$m['mem_id']; ?>" class="btn btn-sm btn-primary">แก้ไข</a>
                            <form method="post" action="member_delete.php" style="display:inline-block;" onsubmit="return confirm('ลบสมาชิก?');">
                                <input type="hidden" name="id" value="<?php echo (int)$m['mem_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php';

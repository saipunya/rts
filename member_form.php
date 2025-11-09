<?php
require_once 'functions.php';
require_admin();
include 'header.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'create';
$member = [
    'mem_id' => 0,
    'mem_group' => '',
    'mem_number' => '',
    'mem_fullname' => '',
    'mem_class' => ''
];

if ($action === 'edit') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        header('Location: members.php?msg=' . urlencode('Invalid member id'));
        exit;
    }
    $stmt = $mysqli->prepare('SELECT mem_id, mem_group, mem_number, mem_fullname, mem_class FROM tbl_member WHERE mem_id = ? LIMIT 1');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();
    if (!$data) {
        header('Location: members.php?msg=' . urlencode('Member not found'));
        exit;
    }
    $member = $data;
}
?>
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-6">
            <h3><?php echo $action === 'edit' ? 'แก้ไขสมาชิก' : 'เพิ่มสมาชิก'; ?></h3>
        </div>
        <div class="col-6 text-end">
            <a href="members.php" class="btn btn-secondary">กลับไปหน้ารายการ</a>
        </div>
    </div>

    <form method="post" action="member_save.php">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
        <input type="hidden" name="id" value="<?php echo (int)$member['mem_id']; ?>">

        <div class="mb-3">
            <label class="form-label">กลุ่ม</label>
            <input type="text" name="mem_group" class="form-control" required value="<?php echo htmlspecialchars($member['mem_group']); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">เลขที่สมาชิก</label>
            <input type="text" name="mem_number" class="form-control" required value="<?php echo htmlspecialchars($member['mem_number']); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">ชื่อ-สกุล</label>
            <input type="text" name="mem_fullname" class="form-control" required value="<?php echo htmlspecialchars($member['mem_fullname']); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">ชั้น</label>
            <input type="text" name="mem_class" class="form-control" required value="<?php echo htmlspecialchars($member['mem_class']); ?>">
        </div>

        <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'บันทึกการแก้ไข' : 'บันทึก'; ?></button>
    </form>
</div>
<?php include 'footer.php';

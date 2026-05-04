<?php
require_once 'functions.php';
require_login();
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
<style>
.member-form-shell {
  max-width: 920px;
}

.member-hero,
.member-panel {
  border: 1px solid #bbf7d0;
  border-radius: 1.25rem;
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 16px 42px rgba(20, 83, 45, 0.08);
}

.member-icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: .9rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #16a34a;
  color: #fff;
  flex: 0 0 auto;
}

.form-label {
  font-weight: 700;
  color: #166534;
}

.form-select,
.form-control {
  min-height: 46px;
  border-radius: .9rem;
  border-color: #bbf7d0;
}

.form-select:focus,
.form-control:focus {
  border-color: #22c55e;
  box-shadow: 0 0 0 .2rem rgba(34, 197, 94, .14);
}

.btn {
  min-height: 44px;
  border-radius: 999px;
  font-weight: 700;
}

@media (max-width: 576px) {
  .member-hero,
  .member-panel {
    border-radius: 1rem;
  }
}
</style>

<div class="container member-form-shell my-4">
  <section class="member-hero p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="member-icon">
          <i data-lucide="users" aria-hidden="true"></i>
        </span>
        <div>
          <h1 class="h4 fw-bold mb-1 text-success-emphasis"><?php echo $action === 'edit' ? 'แก้ไขสมาชิก' : 'เพิ่มสมาชิก'; ?></h1>
          <div class="text-success">จัดการข้อมูลสมาชิกและเกษตรกรทั่วไป</div>
        </div>
      </div>
      <a href="members.php" class="btn btn-outline-success">
        <i data-lucide="arrow-left" class="me-1" aria-hidden="true"></i>กลับหน้ารายการ
      </a>
    </div>
  </section>

  <section class="member-panel p-3 p-md-4">
    <form method="post" action="member_save.php">
      <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
      <input type="hidden" name="id" value="<?php echo (int)$member['mem_id']; ?>">

      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label for="mem_class" class="form-label">
            <i data-lucide="layers-3" class="me-1" aria-hidden="true"></i>ชั้น
          </label>
          <select class="form-select" name="mem_class" id="mem_class" required>
            <option value="" selected>++โปรดเลือก++</option>
            <option value="member" <?php echo $member['mem_class'] === 'member' ? 'selected' : ''; ?>>สมาชิก</option>
            <option value="general" <?php echo $member['mem_class'] === 'general' ? 'selected' : ''; ?>>เกษตรกรทั่วไป</option>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="mem_group">
            <i data-lucide="folder-open" class="me-1" aria-hidden="true"></i>กลุ่ม
          </label>
          <input type="text" name="mem_group" id="mem_group" class="form-control" required
            value="<?php echo htmlspecialchars($member['mem_group']); ?>" placeholder="เช่น 1, 2, 3, 9">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="mem_number">
            <i data-lucide="hash" class="me-1" aria-hidden="true"></i>เลขที่สมาชิก
          </label>
          <input type="text" name="mem_number" id="mem_number" class="form-control" required
            value="<?php echo htmlspecialchars($member['mem_number']); ?>" placeholder="เลขที่สมาชิก">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="mem_fullname">
            <i data-lucide="user" class="me-1" aria-hidden="true"></i>ชื่อ-สกุล
          </label>
          <input type="text" name="mem_fullname" id="mem_fullname" class="form-control" required
            value="<?php echo htmlspecialchars($member['mem_fullname']); ?>" placeholder="ชื่อ-สกุลสมาชิก">
        </div>
      </div>

      <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mt-4">
        <a href="members.php" class="btn btn-outline-secondary">
          <i data-lucide="x" class="me-1" aria-hidden="true"></i>ยกเลิก
        </a>
        <button type="submit" class="btn btn-success">
          <i data-lucide="save" class="me-1" aria-hidden="true"></i><?php echo $action === 'edit' ? 'บันทึกการแก้ไข' : 'บันทึก'; ?>
        </button>
      </div>
    </form>
  </section>
</div>
<?php include 'footer.php';

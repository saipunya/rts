<?php
require_once 'functions.php';
require_admin();
include 'header.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'create';
$user = [
    'user_id' => 0,
    'user_username' => '',
    'user_fullname' => '',
    'user_level' => 'user',
    'user_status' => 'active'
];

if ($action === 'edit') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        header('Location: users.php?msg=' . urlencode('Invalid user id'));
        exit;
    }
    // use central USER_TABLE constant to avoid mismatched table names
    $stmt = $mysqli->prepare('SELECT user_id, user_username, user_fullname, user_level, user_status FROM ' . USER_TABLE . ' WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();
    if (!$data) {
        header('Location: users.php?msg=' . urlencode('User not found'));
        exit;
    }
    $user = $data;
}
?>
<style>
  html, body {
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    background: #eff7f1;
    color: #14532d;
  }

  .user-form-shell {
    max-width: 980px;
  }

  .user-hero,
  .user-panel {
    border: 1px solid #bbf7d0;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 16px 42px rgba(20, 83, 45, 0.08);
  }

  .user-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #16a34a;
    color: #fff;
    box-shadow: 0 10px 24px rgba(22, 163, 74, 0.24);
    flex: 0 0 auto;
  }

  .form-label {
    font-weight: 700;
    color: #166534;
  }

  .form-control,
  .form-select {
    min-height: 46px;
    border-radius: .9rem;
    border-color: #bbf7d0;
  }

  .form-control:focus,
  .form-select:focus {
    border-color: #22c55e;
    box-shadow: 0 0 0 .2rem rgba(34, 197, 94, .14);
  }

  .btn {
    min-height: 44px;
    border-radius: 999px;
    font-weight: 700;
  }

  .field-icon {
    width: 2.35rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #166534;
  }

  .helper-note {
    color: #15803d;
    font-size: .95rem;
  }

  @media (max-width: 576px) {
    .user-hero,
    .user-panel {
      border-radius: 1rem;
    }
  }
</style>

<div class="container user-form-shell my-4">
  <section class="user-hero p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="user-icon">
          <i data-lucide="shield" aria-hidden="true"></i>
        </span>
        <div>
          <div class="text-uppercase text-success fw-semibold small mb-1">Users</div>
          <h1 class="h3 fw-bold mb-1 text-success-emphasis"><?php echo $action === 'edit' ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้'; ?></h1>
          <div class="text-success">จัดการบัญชีผู้ใช้งานระบบ</div>
        </div>
      </div>
      <a href="users.php" class="btn btn-outline-success">
        <i data-lucide="arrow-left" class="me-1" aria-hidden="true"></i>กลับหน้ารายการ
      </a>
    </div>
  </section>

  <section class="user-panel p-3 p-md-4">
    <form method="post" action="user_save.php">
      <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
      <input type="hidden" name="id" value="<?php echo (int)$user['user_id']; ?>">

      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label" for="username">
            <i data-lucide="user" class="me-1" aria-hidden="true"></i>Username
          </label>
          <div class="input-group">
            <span class="input-group-text field-icon"><i data-lucide="at-sign" aria-hidden="true"></i></span>
            <input type="text" name="username" id="username" class="form-control" required value="<?php echo htmlspecialchars($user['user_username']); ?>" placeholder="username">
          </div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="password">
            <i data-lucide="key-round" class="me-1" aria-hidden="true"></i>Password
          </label>
          <input type="password" name="password" id="password" class="form-control" <?php if ($action === 'create') echo 'required'; ?> placeholder="<?php echo $action === 'edit' ? 'เว้นว่างไว้ถ้าไม่เปลี่ยนรหัสผ่าน' : 'กรอกรหัสผ่าน'; ?>">
          <?php if ($action === 'edit'): ?>
            <div class="helper-note mt-2">
              <i data-lucide="info" class="me-1" aria-hidden="true"></i>ถ้าไม่ต้องการเปลี่ยนรหัสผ่าน ให้เว้นช่องนี้ว่างไว้
            </div>
          <?php endif; ?>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="fullname">
            <i data-lucide="user" class="me-1" aria-hidden="true"></i>Full name
          </label>
          <input type="text" name="fullname" id="fullname" class="form-control" required value="<?php echo htmlspecialchars($user['user_fullname']); ?>" placeholder="ชื่อ-สกุล">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="level">
            <i data-lucide="shield" class="me-1" aria-hidden="true"></i>Level
          </label>
          <select name="level" id="level" class="form-select">
            <option value="admin" <?php if ($user['user_level'] === 'admin') echo 'selected'; ?>>admin</option>
            <option value="user" <?php if ($user['user_level'] === 'user') echo 'selected'; ?>>user</option>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label" for="status">
            <i data-lucide="toggle-right" class="me-1" aria-hidden="true"></i>Status
          </label>
          <select name="status" id="status" class="form-select">
            <option value="active" <?php if ($user['user_status'] === 'active') echo 'selected'; ?>>active</option>
            <option value="inactive" <?php if ($user['user_status'] === 'inactive') echo 'selected'; ?>>inactive</option>
          </select>
        </div>
      </div>

      <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mt-4">
        <a href="users.php" class="btn btn-outline-secondary">
          <i data-lucide="x" class="me-1" aria-hidden="true"></i>ยกเลิก
        </a>
        <button type="submit" class="btn btn-success">
          <i data-lucide="save" class="me-1" aria-hidden="true"></i><?php echo $action === 'edit' ? 'บันทึกการแก้ไข' : 'สร้างผู้ใช้'; ?>
        </button>
      </div>
    </form>
  </section>
</div>

<?php include 'footer.php'; ?>

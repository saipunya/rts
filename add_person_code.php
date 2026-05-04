<?php
require_once 'functions.php';
require_login();
include 'header.php';
?>

<?php

// Handle form submission for updating person code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_person_code') {
    $mem_id = (int)($_POST['mem_id'] ?? 0);
    $person_code = trim($_POST['mem_personcode'] ?? '');
    
    if ($mem_id > 0 && !empty($person_code)) {
        // Validate person code is 4 digits
        if (strlen($person_code) === 4 && ctype_digit($person_code)) {
            $stmt = $mysqli->prepare("UPDATE tbl_member SET mem_personcode = ? WHERE mem_id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $person_code, $mem_id);
                if ($stmt->execute()) {
                    $success_msg = "อัพเดทรหัสบุคคลเรียบร้อยแล้ว";
                } else {
                    $error_msg = "เกิดข้อผิดพลาดในการอัพเดท: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง: " . $mysqli->error;
            }
        } else {
            $error_msg = "รหัสบุคคลต้องเป็นตัวเลข 4 ตัวเท่านั้น";
        }
    } else {
        $error_msg = "ข้อมูลไม่ครบถ้วน";
    }
}

// Search functionality - show all members initially, filter when searching
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$members = [];
$editing_member = null;

// Always load data - either all members or filtered results
if ($q !== '' && mb_strlen($q) >= 2) {
    // Search with query
    $like = '%' . $q . '%';
    $stmt = $mysqli->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_birthtext, mem_class, mem_personcode, mem_saveby, mem_savedate FROM tbl_member WHERE mem_fullname LIKE ? OR mem_number LIKE ? OR mem_group LIKE ? ORDER BY mem_fullname ASC");
    if ($stmt) {
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    // Load all members when no search query or query is too short
    $stmt = $mysqli->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_birthtext, mem_class, mem_personcode, mem_saveby, mem_savedate FROM tbl_member ORDER BY mem_fullname ASC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $members = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Handle edit request
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $mysqli->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_birthtext, mem_class, mem_personcode, mem_saveby, mem_savedate FROM tbl_member WHERE mem_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $editing_member = $result->fetch_assoc();
        $stmt->close();
    }
}
?>
<style>
html, body {
  font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  background: #eff7f1;
  color: #14532d;
}

.person-code-shell {
  max-width: 1240px;
}

.person-hero,
.person-panel,
.person-edit,
.person-table {
  border: 1px solid #bbf7d0;
  border-radius: 1.25rem;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 16px 42px rgba(20, 83, 45, 0.08);
}

.person-hero {
  background: linear-gradient(135deg, rgba(240, 253, 244, 0.98), rgba(236, 253, 245, 0.95));
}

.person-badge,
.person-mini-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.person-badge {
  width: 3rem;
  height: 3rem;
  border-radius: 1rem;
  background: #16a34a;
  color: #fff;
  box-shadow: 0 10px 24px rgba(22, 163, 74, 0.24);
}

.person-mini-icon {
  width: 2.35rem;
  color: #166534;
}

.form-control,
.btn,
.table,
.alert,
.badge,
.form-label {
  font-family: inherit;
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

.table thead th {
  background: #f0fdf4;
  color: #166534;
  border-bottom: 1px solid #bbf7d0;
  white-space: nowrap;
}

.table > :not(caption) > * > * {
  padding: .85rem .8rem;
  vertical-align: middle;
}

.table tbody tr:hover td {
  background: #f8fdf8;
}

.person-code-input {
  font-family: monospace;
  font-size: 1.2rem;
  text-align: center;
  letter-spacing: 0.2em;
}

@media (max-width: 768px) {
  .person-hero,
  .person-panel,
  .person-edit,
  .person-table {
    border-radius: 1rem;
  }
}
</style>

<div class="container person-code-shell my-4">
  <section class="person-hero p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="person-badge">
          <i data-lucide="id-card" aria-hidden="true"></i>
        </span>
        <div>
          <div class="text-uppercase text-success fw-semibold small mb-1">Person code</div>
          <h1 class="h3 fw-bold mb-1 text-success-emphasis">เพิ่มรหัสบุคคลสมาชิก</h1>
          <div class="text-success">ค้นหาและบันทึกรหัสบุคคลของสมาชิกได้ในหน้าเดียว</div>
        </div>
      </div>
      <a href="members.php" class="btn btn-outline-success">
        <i data-lucide="arrow-left" class="me-1" aria-hidden="true"></i>กลับหน้าสมาชิก
      </a>
    </div>
  </section>

  <?php if (isset($success_msg)): ?>
    <div class="alert alert-success border-0 shadow-sm">
      <i data-lucide="check-circle" class="me-1" aria-hidden="true"></i><?php echo htmlspecialchars($success_msg); ?>
    </div>
  <?php endif; ?>

  <?php if (isset($error_msg)): ?>
    <div class="alert alert-danger border-0 shadow-sm">
      <i data-lucide="alert-circle" class="me-1" aria-hidden="true"></i><?php echo htmlspecialchars($error_msg); ?>
    </div>
  <?php endif; ?>

  <?php if ($editing_member): ?>
    <section class="person-edit p-3 p-md-4 mb-4">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
          <h2 class="h5 fw-bold mb-1 text-success-emphasis">
            <i data-lucide="pencil" class="me-1" aria-hidden="true"></i>แก้ไขรหัสบุคคล
          </h2>
          <div class="text-success"><?php echo htmlspecialchars($editing_member['mem_fullname']); ?></div>
        </div>
        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
          <i data-lucide="user-check" class="me-1" aria-hidden="true"></i><?php echo htmlspecialchars($editing_member['mem_number']); ?>
        </span>
      </div>

      <form method="post" action="add_person_code.php">
        <input type="hidden" name="action" value="update_person_code">
        <input type="hidden" name="mem_id" value="<?php echo (int)$editing_member['mem_id']; ?>">

        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label"><i data-lucide="user" class="me-1" aria-hidden="true"></i>ชื่อสมาชิก</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_fullname']); ?>" readonly>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label"><i data-lucide="hash" class="me-1" aria-hidden="true"></i>เลขที่สมาชิก</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_number']); ?>" readonly>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label"><i data-lucide="folder" class="me-1" aria-hidden="true"></i>กลุ่ม</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_group']); ?>" readonly>
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label"><i data-lucide="cake" class="me-1" aria-hidden="true"></i>วันเกิด</label>
            <input type="text" class="form-control" value="<?php echo !empty($editing_member['mem_birthtext']) ? htmlspecialchars($editing_member['mem_birthtext']) : '-'; ?>" readonly>
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label"><i data-lucide="badge-check" class="me-1" aria-hidden="true"></i>ชั้น</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_class']); ?>" readonly>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label"><i data-lucide="user-cog" class="me-1" aria-hidden="true"></i>บันทึกโดย</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_saveby']); ?>" readonly>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label"><i data-lucide="calendar-days" class="me-1" aria-hidden="true"></i>วันที่บันทึก</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars(thai_date_format($editing_member['mem_savedate'])); ?>" readonly>
          </div>
          <div class="col-12 col-md-4">
            <label for="mem_personcode" class="form-label">
              <i data-lucide="barcode" class="me-1" aria-hidden="true"></i>รหัสบุคคล <span class="text-danger">*</span>
            </label>
            <input type="text"
                   id="mem_personcode"
                   name="mem_personcode"
                   class="form-control person-code-input"
                   value="<?php echo htmlspecialchars($editing_member['mem_personcode']); ?>"
                   maxlength="4"
                   pattern="[0-9]{4}"
                   required
                   placeholder="1234">
            <div class="form-text">ต้องเป็นตัวเลข 4 ตัวเท่านั้น</div>
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mt-4">
          <a href="add_person_code.php" class="btn btn-outline-secondary">
            <i data-lucide="x" class="me-1" aria-hidden="true"></i>ยกเลิก
          </a>
          <button type="submit" class="btn btn-success">
            <i data-lucide="save" class="me-1" aria-hidden="true"></i>บันทึกรหัสบุคคล
          </button>
        </div>
      </form>
    </section>
  <?php else: ?>
    <section class="person-panel p-3 p-md-4 mb-4">
      <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center mb-3">
        <div>
          <h2 class="h5 fw-bold mb-1 text-success-emphasis">
            <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหาสมาชิก
          </h2>
          <div class="text-success">พิมพ์ชื่อสมาชิก เลขที่ หรือกลุ่ม แล้วรอผลลัพธ์</div>
        </div>
        <a href="add_person_code.php" class="btn btn-outline-success">
          <i data-lucide="refresh-cw" class="me-1" aria-hidden="true"></i>ล้าง
        </a>
      </div>

      <form method="get" class="row g-2 position-relative align-items-end" id="search-form">
        <div class="col-12 col-lg">
          <label for="search-input" class="form-label">
            <i data-lucide="search" class="me-1" aria-hidden="true"></i>คำค้นหา
          </label>
          <input type="text"
                 name="q"
                 id="search-input"
                 class="form-control"
                 placeholder="ค้นหาชื่อสมาชิก (พิมพ์และรอผลลัพธ์)"
                 value="<?php echo htmlspecialchars($q); ?>"
                 autocomplete="off">
        </div>
        <div class="col-12 col-lg-auto d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1 flex-lg-grow-0">
            <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา
          </button>
          <a href="add_person_code.php" class="btn btn-outline-secondary flex-grow-1 flex-lg-grow-0">
            <i data-lucide="x" class="me-1" aria-hidden="true"></i>ล้าง
          </a>
        </div>
      </form>
    </section>

    <?php if (!empty($members)): ?>
      <section class="person-table p-0 overflow-hidden">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 px-3 px-md-4 py-3 border-bottom">
          <div class="fw-semibold text-success-emphasis">
            <i data-lucide="list" class="me-1" aria-hidden="true"></i>รายการสมาชิก
          </div>
          <div class="text-success">
            <?php if ($q !== '' && mb_strlen($q) >= 2): ?>
              พบ <?php echo count($members); ?> รายการ
            <?php else: ?>
              แสดงทั้งหมด <?php echo count($members); ?> รายการ
            <?php endif; ?>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th><i data-lucide="hash" class="me-1" aria-hidden="true"></i>เลขสมาชิก</th>
                <th><i data-lucide="folder" class="me-1" aria-hidden="true"></i>กลุ่ม</th>
                <th><i data-lucide="user" class="me-1" aria-hidden="true"></i>ชื่อ-สกุล</th>
                <th><i data-lucide="badge-check" class="me-1" aria-hidden="true"></i>ชั้น</th>
                <th><i data-lucide="barcode" class="me-1" aria-hidden="true"></i>รหัสบุคคล</th>
                <th><i data-lucide="settings-2" class="me-1" aria-hidden="true"></i>จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($members as $member): ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($member['mem_number']); ?></td>
                  <td><?php echo htmlspecialchars($member['mem_group']); ?></td>
                  <td><?php echo htmlspecialchars($member['mem_fullname']); ?></td>
                  <td>
                    <span class="badge <?php echo $member['mem_class'] == 'member' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                      <?php echo $member['mem_class'] == 'member' ? 'สมาชิก' : 'เกษตรกร'; ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!empty($member['mem_personcode'])): ?>
                      <span class="badge bg-success"><?php echo htmlspecialchars($member['mem_personcode']); ?></span>
                    <?php else: ?>
                      <span class="badge bg-secondary">ยังไม่มี</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-nowrap">
                    <a href="add_person_code.php?edit_id=<?php echo (int)$member['mem_id']; ?>" class="btn btn-sm btn-outline-primary">
                      <i data-lucide="pencil" class="me-1" aria-hidden="true"></i>แก้ไขรหัส
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php elseif ($q !== '' && mb_strlen($q) >= 2): ?>
      <div class="alert alert-info border-0 shadow-sm">
        <i data-lucide="info" class="me-1" aria-hidden="true"></i>ไม่พบสมาชิกที่ค้นหา: "<?php echo htmlspecialchars($q); ?>"
      </div>
    <?php else: ?>
      <div class="alert alert-info border-0 shadow-sm">
        <i data-lucide="info" class="me-1" aria-hidden="true"></i>ไม่พบข้อมูลสมาชิกในระบบ
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-search functionality
    const searchInput = document.getElementById('search-input');
    const searchForm = document.getElementById('search-form');
    let searchTimeout;
    
    if (searchInput && searchForm) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Wait for user to stop typing (500ms delay)
            searchTimeout = setTimeout(function() {
                if (query.length >= 2) {
                    // Update URL and submit form
                    const newUrl = 'add_person_code.php?q=' + encodeURIComponent(query);
                    window.location.href = newUrl;
                } else if (query.length === 0) {
                    // If input is cleared, go to base page
                    window.location.href = 'add_person_code.php';
                }
            }, 500);
        });
    }
    
    // Auto-format person code input
    const personCodeInput = document.getElementById('mem_personcode');
    if (personCodeInput) {
        personCodeInput.addEventListener('input', function(e) {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limit to 4 characters
            if (this.value.length > 4) {
                this.value = this.value.substring(0, 4);
            }
        });
        
        personCodeInput.addEventListener('keypress', function(e) {
            // Only allow number keys
            const char = String.fromCharCode(e.which || e.keyCode);
            if (!/[0-9]/.test(char)) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>

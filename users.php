<?php
require_once 'functions.php';
require_admin();
include 'header.php';

// fetch messages
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// search query (optional)
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// fetch users (use actual table name tbl_user)
if ($q !== '' && mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';
    $stmt = $mysqli->prepare("SELECT user_id, user_username, user_fullname, user_level, user_status FROM tbl_user WHERE user_username LIKE ? OR user_fullname LIKE ? ORDER BY user_id ASC");
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('ss', $like, $like);
} else {
    $stmt = $mysqli->prepare("SELECT user_id, user_username, user_fullname, user_level, user_status FROM tbl_user ORDER BY user_id ASC");
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$userCount = count($users);
$adminCount = 0;
$activeCount = 0;
foreach ($users as $userRow) {
    if (($userRow['user_level'] ?? '') === 'admin') {
        $adminCount++;
    }
    if (($userRow['user_status'] ?? '') === 'active') {
        $activeCount++;
    }
}
?>
<style>
html,
body {
  font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  background: #eff7f1;
  color: #14532d;
}

.users-shell {
  max-width: 1240px;
}

.users-hero,
.users-panel,
.users-stat {
  border: 1px solid #bbf7d0;
  border-radius: 1.25rem;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 16px 42px rgba(20, 83, 45, 0.08);
}

.users-hero {
  background: linear-gradient(135deg, rgba(240, 253, 244, 0.98), rgba(236, 253, 245, 0.95));
}

.users-badge,
.stat-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  color: #166534;
}

.users-badge {
  width: 3rem;
  height: 3rem;
  border-radius: 1rem;
  background: #16a34a;
  color: #fff;
  box-shadow: 0 10px 24px rgba(22, 163, 74, 0.24);
}

.stat-icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: .85rem;
  background: #dcfce7;
}

.form-control,
.btn,
.table,
.alert,
.badge {
  font-family: inherit;
}

.form-control {
  min-height: 46px;
  border-radius: .9rem;
  border-color: #bbf7d0;
}

.form-control:focus {
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

.table> :not(caption)>*>* {
  padding: .85rem .8rem;
  vertical-align: middle;
}

.table tbody tr:hover td {
  background: #f8fdf8;
}

.action-group .btn {
  min-width: 88px;
}

.table-responsive {
  border-radius: 1rem;
  overflow: hidden;
}

@media (max-width: 768px) {

  .users-hero,
  .users-panel,
  .users-stat {
    border-radius: 1rem;
  }

  .action-group .btn {
    min-width: 0;
  }
}
</style>

<div class="container users-shell my-4">
  <section class="users-hero p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="users-badge">
          <i data-lucide="users" aria-hidden="true"></i>
        </span>
        <div>
          <div class="text-uppercase text-success fw-semibold small mb-1">ผู้ดูแลระบบ</div>
          <h1 class="h3 fw-bold mb-1 text-success-emphasis">บัญชีผู้ใช้</h1>
          <div class="text-success">จัดการบัญชีผู้ใช้งานระบบ</div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="user_form.php?action=create" class="btn btn-success">
          <i data-lucide="user-plus" class="me-1" aria-hidden="true"></i>เพิ่มผู้ใช้
        </a>
      </div>
    </div>
  </section>

  <section class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="users-stat p-3 p-lg-4">
        <div class="d-flex align-items-center gap-3">
          <span class="stat-icon"><i data-lucide="users" aria-hidden="true"></i></span>
          <div>
            <div class="text-success-emphasis fw-semibold">ทั้งหมด</div>
            <div class="h4 mb-0 fw-bold"><?php echo number_format($userCount); ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="users-stat p-3 p-lg-4">
        <div class="d-flex align-items-center gap-3">
          <span class="stat-icon"><i data-lucide="shield" aria-hidden="true"></i></span>
          <div>
            <div class="text-success-emphasis fw-semibold">แอดมิน</div>
            <div class="h4 mb-0 fw-bold"><?php echo number_format($adminCount); ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="users-stat p-3 p-lg-4">
        <div class="d-flex align-items-center gap-3">
          <span class="stat-icon"><i data-lucide="badge-check" aria-hidden="true"></i></span>
          <div>
            <div class="text-success-emphasis fw-semibold">ใช้งานอยู่</div>
            <div class="h4 mb-0 fw-bold"><?php echo number_format($activeCount); ?></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="users-panel p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center mb-3">
      <div>
        <h2 class="h5 fw-bold mb-1 text-success-emphasis">ค้นหาผู้ใช้</h2>
        <div class="text-success">ค้นหาจากชื่อผู้ใช้หรือชื่อ-สกุล</div>
      </div>
      <a href="users.php" class="btn btn-outline-success">
        <i data-lucide="refresh-cw" class="me-1" aria-hidden="true"></i>ล้างตัวกรอง
      </a>
    </div>

    <form method="get" class="row g-2 position-relative align-items-end" id="user-search-form">
      <div class="col-12 col-lg">
        <label for="user-search-input" class="form-label">
          <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา
        </label>
        <input type="text" name="q" id="user-search-input" class="form-control" placeholder="ค้นหาผู้ใช้ (อย่างน้อย 2 ตัวอักษร)"
          autocomplete="off" value="<?php echo htmlspecialchars($q); ?>">
        <div id="user-suggestions" class="list-group position-absolute shadow-sm"
          style="z-index:1050; width:100%; display:none;"></div>
      </div>
      <div class="col-12 col-lg-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1 flex-lg-grow-0">
          <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา
        </button>
        <a href="users.php" class="btn btn-outline-secondary flex-grow-1 flex-lg-grow-0">
          <i data-lucide="x" class="me-1" aria-hidden="true"></i>ล้าง
        </a>
      </div>
    </form>
  </section>

  <?php if ($msg): ?>
  <div class="alert alert-info border-0 shadow-sm">
    <i data-lucide="info" class="me-1" aria-hidden="true"></i><?php echo htmlspecialchars($msg); ?>
  </div>
  <?php endif; ?>

  <section class="users-panel p-0 overflow-hidden">
    <div
      class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 px-3 px-md-4 py-3 border-bottom">
      <div class="fw-semibold text-success-emphasis">
        <i data-lucide="list" class="me-1" aria-hidden="true"></i>รายการผู้ใช้
      </div>
      <div class="text-success">
        แสดง <?php echo number_format($userCount); ?> รายการ
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th><i data-lucide="hash" class="me-1" aria-hidden="true"></i>#</th>
            <th><i data-lucide="user" class="me-1" aria-hidden="true"></i>ชื่อผู้ใช้</th>
            <th><i data-lucide="user" class="me-1" aria-hidden="true"></i>ชื่อ-สกุล</th>
            <th><i data-lucide="shield" class="me-1" aria-hidden="true"></i>ระดับ</th>
            <th><i data-lucide="badge-check" class="me-1" aria-hidden="true"></i>สถานะ</th>
            <th class="text-nowrap"><i data-lucide="settings-2" class="me-1" aria-hidden="true"></i>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <?php
              $levelBadge = $u['user_level'] === 'admin' ? 'bg-danger' : 'bg-secondary';
              $statusBadge = $u['user_status'] === 'active' ? 'bg-success' : 'bg-warning text-dark';
              $levelLabel = $u['user_level'] === 'admin' ? 'แอดมิน' : 'ผู้ใช้';
              $statusLabel = $u['user_status'] === 'active' ? 'ใช้งานอยู่' : 'ไม่ใช้งาน';
              $isAdmin = $u['user_level'] === 'admin';
            ?>
          <tr>
            <td class="fw-semibold"><?php echo (int)$u['user_id']; ?></td>
            <td><?php echo htmlspecialchars($u['user_username']); ?></td>
            <td><?php echo htmlspecialchars($u['user_fullname']); ?></td>
            <td><span class="badge <?php echo $levelBadge; ?>"><?php echo htmlspecialchars($levelLabel); ?></span></td>
            <td><span class="badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
            </td>
            <td class="text-nowrap">
              <div class="d-flex gap-2 action-group">
                <?php if ($isAdmin): ?>
                <span class="btn btn-sm btn-outline-primary disabled" aria-disabled="true" tabindex="-1">
                  <i data-lucide="pencil" class="me-1" aria-hidden="true"></i>แก้ไข
                </span>
                <span class="btn btn-sm btn-outline-danger disabled" aria-disabled="true" tabindex="-1">
                  <i data-lucide="trash-2" class="me-1" aria-hidden="true"></i>ลบ
                </span>
                <?php else: ?>
                <a href="user_form.php?action=edit&id=<?php echo (int)$u['user_id']; ?>"
                  class="btn btn-sm btn-outline-primary">
                  <i data-lucide="pencil" class="me-1" aria-hidden="true"></i>แก้ไข
                </a>
                <form method="post" action="user_delete.php" onsubmit="return confirm('ลบบัญชีผู้ใช้นี้หรือไม่?');">
                  <input type="hidden" name="id" value="<?php echo (int)$u['user_id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i data-lucide="trash-2" class="me-1" aria-hidden="true"></i>ลบ
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
(function() {
  const input = document.getElementById('user-search-input');
  const sugg = document.getElementById('user-suggestions');
  let debounceTimer = null;

  function clearSuggestions() {
    sugg.innerHTML = '';
    sugg.style.display = 'none';
  }

  function render(items) {
    sugg.innerHTML = '';
    if (!items || items.length === 0) {
      clearSuggestions();
      return;
    }
    items.forEach(it => {
      const a = document.createElement('a');
      a.href = '#';
      a.className = 'list-group-item list-group-item-action';
      a.dataset.value = it.user_username || it.user_fullname || '';
      a.innerText = (it.user_username ? (it.user_username + ' - ') : '') + (it.user_fullname || '');
      a.addEventListener('click', function(e) {
        e.preventDefault();
        input.value = this.dataset.value;
        document.getElementById('user-search-form').submit();
      });
      sugg.appendChild(a);
    });
    sugg.style.display = 'block';
  }

  input.addEventListener('input', function() {
    const v = this.value.trim();
    if (debounceTimer) clearTimeout(debounceTimer);
    if (v.length < 2) {
      clearSuggestions();
      return;
    }
    debounceTimer = setTimeout(() => {
      fetch('users_search.php?q=' + encodeURIComponent(v))
        .then(r => r.json())
        .then(data => render(data))
        .catch(() => clearSuggestions());
    }, 250);
  });

  document.addEventListener('click', function(e) {
    if (!document.getElementById('user-search-form').contains(e.target)) {
      clearSuggestions();
    }
  });
})();
</script>

<?php include 'footer.php'; ?>

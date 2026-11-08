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

$memberCount = count($members);
$generalCount = 0;
foreach ($members as $memberRow) {
    if (($memberRow['mem_class'] ?? '') === 'general') {
        $generalCount++;
    }
}
$memberOnlyCount = $memberCount - $generalCount;
?>

<!-- Removed Google Fonts, now using local Sarabun -->

<style>
  html, body {
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    background: #eff7f1;
    color: #14532d;
  }

  .members-shell {
    max-width: 1240px;
  }

  .members-hero,
  .members-panel,
  .members-search,
  .members-stat {
    border: 1px solid #bbf7d0;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 16px 42px rgba(20, 83, 45, 0.08);
  }

  .members-hero {
    background:
      linear-gradient(135deg, rgba(240, 253, 244, 0.98), rgba(236, 253, 245, 0.95));
  }

  .member-badge {
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

  .members-stat {
    min-height: 100%;
  }

  .stat-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: .85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #dcfce7;
    color: #166534;
    flex: 0 0 auto;
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

  .table > :not(caption) > * > * {
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
    .members-hero,
    .members-panel,
    .members-search,
    .members-stat {
      border-radius: 1rem;
    }

    .action-group .btn {
      min-width: 0;
    }
  }
</style>

<div class="container members-shell my-4">
  <section class="members-hero p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="member-badge">
          <i data-lucide="users" aria-hidden="true"></i>
        </span>
        <div>
          <div class="text-uppercase text-success fw-semibold small mb-1">สมาชิก</div>
          <h1 class="h3 fw-bold mb-1 text-success-emphasis">สมาชิกสหกรณ์</h1>
          <div class="text-success">จัดการรายชื่อสมาชิกและเกษตรกรทั่วไป</div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="member_form.php?action=create" class="btn btn-success">
          <i data-lucide="user-plus" class="me-1" aria-hidden="true"></i>เพิ่มสมาชิก
        </a>
      </div>
    </div>
  </section>

  <section class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="members-stat p-3 p-lg-4">
        <div class="d-flex align-items-center gap-3">
          <span class="stat-icon"><i data-lucide="users" aria-hidden="true"></i></span>
          <div>
            <div class="text-success-emphasis fw-semibold">ทั้งหมด</div>
            <div class="h4 mb-0 fw-bold"><?php echo number_format($memberCount); ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="members-stat p-3 p-lg-4">
        <div class="d-flex align-items-center gap-3">
          <span class="stat-icon"><i data-lucide="user-round" aria-hidden="true"></i></span>
          <div>
            <div class="text-success-emphasis fw-semibold">สมาชิก</div>
            <div class="h4 mb-0 fw-bold"><?php echo number_format($memberOnlyCount); ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="members-stat p-3 p-lg-4">
        <div class="d-flex align-items-center gap-3">
          <span class="stat-icon"><i data-lucide="sprout" aria-hidden="true"></i></span>
          <div>
            <div class="text-success-emphasis fw-semibold">เกษตรกรทั่วไป</div>
            <div class="h4 mb-0 fw-bold"><?php echo number_format($generalCount); ?></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="members-panel p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center mb-3">
      <div>
        <h2 class="h5 fw-bold mb-1 text-success-emphasis">ค้นหาสมาชิก</h2>
        <div class="text-success">พิมพ์ชื่อ เลขที่ หรือกลุ่มเพื่อค้นหาได้รวดเร็ว</div>
      </div>
      <div class="d-flex gap-2">
        <a href="members.php" class="btn btn-outline-success">
          <i data-lucide="refresh-cw" class="me-1" aria-hidden="true"></i>ล้างตัวกรอง
        </a>
      </div>
    </div>

    <form method="get" class="row g-2 position-relative align-items-end" id="member-search-form">
      <div class="col-12 col-lg">
        <label for="member-search-input" class="form-label">
          <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา
        </label>
        <input type="text" name="q" id="member-search-input" class="form-control" placeholder="ค้นหาสมาชิก (อย่างน้อย 2 ตัวอักษร)" autocomplete="off" value="<?php echo htmlspecialchars($q); ?>">
        <div id="member-suggestions" class="list-group position-absolute shadow-sm" style="z-index:1050; width:100%; display:none;"></div>
      </div>
      <div class="col-12 col-lg-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1 flex-lg-grow-0">
          <i data-lucide="search" class="me-1" aria-hidden="true"></i>ค้นหา
        </button>
        <a href="members.php" class="btn btn-outline-secondary flex-grow-1 flex-lg-grow-0">
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

  <section class="members-panel p-0 overflow-hidden">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 px-3 px-md-4 py-3 border-bottom">
      <div class="fw-semibold text-success-emphasis">
        <i data-lucide="list" class="me-1" aria-hidden="true"></i>รายการสมาชิก
      </div>
      <div class="text-success">
        แสดง <?php echo number_format($memberCount); ?> รายการ
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th><i data-lucide="folder" class="me-1" aria-hidden="true"></i>กลุ่ม</th>
            <th><i data-lucide="hash" class="me-1" aria-hidden="true"></i>เลขสมาชิก</th>
            <th><i data-lucide="user" class="me-1" aria-hidden="true"></i>ชื่อ-สกุล</th>
            <th><i data-lucide="badge-check" class="me-1" aria-hidden="true"></i>ชั้น</th>
            <th><i data-lucide="user-cog" class="me-1" aria-hidden="true"></i>บันทึกโดย</th>
            <th><i data-lucide="calendar-days" class="me-1" aria-hidden="true"></i>วันที่บันทึก</th>
            <th class="text-nowrap"><i data-lucide="settings-2" class="me-1" aria-hidden="true"></i>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m): ?>
            <?php
              $classLabel = $m['mem_class'] === 'general' ? 'เกษตรกรทั่วไป' : 'สมาชิก';
              $classBadge = $m['mem_class'] === 'general' ? 'bg-warning text-dark' : 'bg-success';
            ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($m['mem_group']); ?></td>
              <td><?php echo htmlspecialchars($m['mem_number']); ?></td>
              <td><?php echo htmlspecialchars($m['mem_fullname']); ?></td>
              <td><span class="badge <?php echo $classBadge; ?>"><?php echo htmlspecialchars($classLabel); ?></span></td>
              <td><?php echo htmlspecialchars($m['mem_saveby']); ?></td>
              <td class="text-nowrap"><?php echo htmlspecialchars(thai_date_format($m['mem_savedate'])); ?></td>
              <td class="text-nowrap">
                <div class="d-flex gap-2 action-group">
                  <a href="member_form.php?action=edit&id=<?php echo (int)$m['mem_id']; ?>" class="btn btn-sm btn-outline-primary">
                    <i data-lucide="pencil" class="me-1" aria-hidden="true"></i>แก้ไข
                  </a>
                  <form method="post" action="member_delete.php" onsubmit="return confirm('ลบสมาชิก?');">
                    <input type="hidden" name="id" value="<?php echo (int)$m['mem_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i data-lucide="trash-2" class="me-1" aria-hidden="true"></i>ลบ
                    </button>
                  </form>
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
(function(){
    const input = document.getElementById('member-search-input');
    const sugg = document.getElementById('member-suggestions');
    let debounceTimer = null;

    function clearSuggestions(){
        sugg.innerHTML = '';
        sugg.style.display = 'none';
    }

    function render(items){
        sugg.innerHTML = '';
        if (!items || items.length === 0) { clearSuggestions(); return; }
        items.forEach(it => {
            const a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action';
            a.dataset.value = it.mem_fullname || it.mem_number || '';
            a.innerText = (it.mem_number ? (it.mem_number + ' - ') : '') + (it.mem_fullname || '');
            a.addEventListener('click', function(e){
                e.preventDefault();
                input.value = this.dataset.value;
                document.getElementById('member-search-form').submit();
            });
            sugg.appendChild(a);
        });
        sugg.style.display = 'block';
    }

    input.addEventListener('input', function(){
        const v = this.value.trim();
        if (debounceTimer) clearTimeout(debounceTimer);
        if (v.length < 2){ clearSuggestions(); return; }
        debounceTimer = setTimeout(()=>{
            fetch('members_search.php?q=' + encodeURIComponent(v))
                .then(r=>r.json())
                .then(data=>render(data))
                .catch(()=>clearSuggestions());
        }, 250);
    });

    document.addEventListener('click', function(e){
        if (!document.getElementById('member-search-form').contains(e.target)) {
            clearSuggestions();
        }
    });
})();
</script>

<?php include 'footer.php'; ?>

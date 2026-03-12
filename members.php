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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Thai:wght@400;600;700&display=swap" rel="stylesheet">

<style>
	html, body {
		font-family: 'Noto Serif Thai', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
		font-size: 16px;
		font-weight: 300;
		background: #eef6f0;
		color: var(--bs-body-color);
	}

	.container {
		background: var(--bs-body-bg);
		border-radius: 1.25rem;
		padding: 2rem;
		margin-top: 2rem;
		margin-bottom: 2rem;
		box-shadow: var(--bs-box-shadow-sm);
	}

	.card,
	.table,
	.form-control,
	.form-select,
	.form-label,
	.btn,
	.btn-sm,
	.nav-link,
	.alert,
	.badge {
		font-family: 'Noto Serif Thai', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
		font-size: 16px;
		font-weight: 300;
	}

	.small,
	.form-text {
		font-size: 14px !important;
		font-weight: 300 !important;
	}

	/* Match rubbers.php baseline scale */
	html, body {
	  font-size: 18px;
	}

	.container,
	.card,
	.table,
	.form-control,
	.form-select,
	.form-label,
	.btn,
	.btn-sm,
	.nav-link,
	.alert,
	.badge {
	  font-size: 1rem;
	}

/* Enhanced Responsive Design */
@media (max-width: 992px) {
  .container.mt-4 {
    padding: 0 1rem;
  }
  
  h3 {
    font-size: 1.5rem;
  }
}

@media (max-width: 768px) {
  .container.mt-4 {
    padding: 0 0.5rem;
    margin-top: 1rem !important;
  }
  
  .row.mb-3 {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch !important;
    text-align: center;
  }
  
  .col-6 {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  h3 {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
  }
  
  .col-6.text-end {
    text-align: center !important;
  }
  
  .col-6.text-end .btn {
    display: block;
    width: 100%;
    margin-bottom: 0.5rem;
    min-height: 44px;
  }
  
  .row.mb-3 {
    margin-bottom: 1rem !important;
  }
  
  .row.g-2.position-relative {
    gap: 0.75rem;
  }
  
  .col-auto {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  .form-control {
    min-height: 44px;
    font-size: 1rem;
  }
  
  .btn {
    min-height: 44px;
    font-size: 1rem;
  }
  
  .table-responsive {
    font-size: 0.85rem;
    margin: 0 -0.5rem;
    padding: 0 0.5rem;
  }
  
  .table th,
  .table td {
    padding: 0.5rem 0.3rem;
  }
  
  .table .btn-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.4rem;
    min-height: auto;
  }
  
  .alert {
    font-size: 0.9rem;
    padding: 0.75rem 1rem;
    margin: 0 -0.5rem 1rem;
  }
}

@media (max-width: 576px) {
  .container.mt-4 {
    padding: 0 0.25rem;
  }
  
  h3 {
    font-size: 1.2rem;
  }
  
  .table-responsive {
    font-size: 0.8rem;
    padding: 0 0.25rem;
  }
  
  .table th,
  .table td {
    padding: 0.4rem 0.2rem;
  }
  
  .table th:not(:first-child):not(:nth-child(2)):not(:nth-child(3)):not(:nth-child(4)):not(:nth-child(5)):not(:nth-child(7)),
  .table td:not(:first-child):not(:nth-child(2)):not(:nth-child(3)):not(:nth-child(4)):not(:nth-child(5)):not(:nth-child(7)) {
    display: none;
  }
  
  .table .btn-sm {
    font-size: 0.7rem;
    padding: 0.2rem 0.3rem;
  }
}

/* Landscape orientation */
@media (max-width: 768px) and (orientation: landscape) {
  .container.mt-4 {
    margin-top: 0.5rem !important;
  }
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {
  .table-hover tbody tr:hover td {
    background-color: transparent;
  }
  
  .btn,
  .form-control {
    min-height: 44px;
  }
}
</style>

    <div class="row mb-3">
        <div class="col-6">
            <h3>สมาชิกสหกรณ์</h3>
        </div>
        <div class="col-6 text-end">
            <a href="member_form.php?action=create" class="btn btn-success">+เพิ่มสมาชิก</a>
        </div>
    </div>

    <!-- Search form -->
    <div class="row mb-3">
        <div class="col-12">
            <form method="get" class="row g-2 position-relative" id="member-search-form">
                <div class="col-auto" style="flex:1;">
                    <input type="text" name="q" id="member-search-input" class="form-control" placeholder="ค้นหาสมาชิก (อย่างน้อย 2 ตัวอักษร)" autocomplete="off" value="<?php echo htmlspecialchars($q); ?>">
                    <div id="member-suggestions" class="list-group position-absolute" style="z-index:1050; width:100%; display:none;"></div>
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
    
                    <th>กลุ่ม</th>
                    <th>เลขสมาชิก</th>
                    <th>ชื่อ-สกุล</th>
                    <th>ชั้น</th>
                    <th>บันทึกโดย</th>
                    <th>วันที่บันทึก</th>
                    <th class="text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['mem_group']); ?></td>
                        <td><?php echo htmlspecialchars($m['mem_number']); ?></td>
                        <td><?php echo htmlspecialchars($m['mem_fullname']); ?></td>
                        <td><?php echo htmlspecialchars($m['mem_class']); ?></td>
                        <td><?php echo htmlspecialchars($m['mem_saveby']); ?></td>
                        <td ><?php echo htmlspecialchars(thai_date_format($m['mem_savedate'])); ?></td>
                        <td class="text-nowrap">
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

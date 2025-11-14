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
            <a href="dashboard.php" class="btn btn-secondary me-2">กลับหน้า dashboard</a>
            <a href="rubbers.php" class="btn btn-info">หน้ารวบรวมยาง</a>
            <a href="member_form.php?action=create" class="btn btn-success">เพิ่มสมาชิก</a>
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
        <div class="container">
            <div class="row my-2">
                <div class="col-12 text-center">
                    <a href="dashboard.php" class="btn btn-secondary">กลับไปหน้าหลัก</a>
                </div>
            </div>
        </div>
    </div>
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

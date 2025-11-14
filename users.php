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
?>
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-6">
            <h3>Users</h3>
        </div>
        <div class="col-6 text-end">
            <a href="user_form.php?action=create" class="btn btn-success">Create User</a>
        </div>
    </div>

    <!-- Search form -->
    <div class="row mb-3">
        <div class="col-12">
            <form method="get" class="row g-2 position-relative" id="user-search-form">
                <div class="col-auto" style="flex:1;">
                    <input type="text" name="q" id="user-search-input" class="form-control" placeholder="Search users (min 2 chars)" autocomplete="off" value="<?php echo htmlspecialchars($q); ?>">
                    <div id="user-suggestions" class="list-group position-absolute" style="z-index:1050; width:100%; display:none;"></div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="users.php" class="btn btn-secondary">Clear</a>
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
                    <th>Username</th>
                    <th>Fullname</th>
                    <th>Level</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo (int)$u['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($u['user_username']); ?></td>
                        <td><?php echo htmlspecialchars($u['user_fullname']); ?></td>
                        <td><?php echo htmlspecialchars($u['user_level']); ?></td>
                        <td><?php echo htmlspecialchars($u['user_status']); ?></td>
                        <td>
                            <a href="user_form.php?action=edit&id=<?php echo (int)$u['user_id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <form method="post" action="user_delete.php" style="display:inline-block;" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="id" value="<?php echo (int)$u['user_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function(){
    const input = document.getElementById('user-search-input');
    const sugg = document.getElementById('user-suggestions');
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
            a.dataset.value = it.user_username || it.user_fullname || '';
            a.innerText = (it.user_username ? (it.user_username + ' - ') : '') + (it.user_fullname || '');
            a.addEventListener('click', function(e){
                e.preventDefault();
                input.value = this.dataset.value;
                document.getElementById('user-search-form').submit();
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
            fetch('users_search.php?q=' + encodeURIComponent(v))
                .then(r=>r.json())
                .then(data=>render(data))
                .catch(()=>clearSuggestions());
        }, 250);
    });

    document.addEventListener('click', function(e){
        if (!document.getElementById('user-search-form').contains(e.target)) {
            clearSuggestions();
        }
    });
})();
</script>

<?php include 'footer.php'; ?>

<?php
// ...existing code...
require_once 'functions.php';
require_admin();
include 'header.php';

// fetch messages
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// fetch users
$stmt = $mysqli->prepare("SELECT user_id, user_username, user_fullname, user_level, user_status FROM users ORDER BY user_id ASC");
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
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

<?php include 'footer.php'; ?>

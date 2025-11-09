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
    $stmt = $mysqli->prepare('SELECT user_id, user_username, user_fullname, user_level, user_status FROM users WHERE user_id = ? LIMIT 1');
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
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-6">
            <h3><?php echo $action === 'edit' ? 'Edit User' : 'Create User'; ?></h3>
        </div>
        <div class="col-6 text-end">
            <a href="users.php" class="btn btn-secondary">Back to list</a>
        </div>
    </div>

    <form method="post" action="user_save.php">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
        <input type="hidden" name="id" value="<?php echo (int)$user['user_id']; ?>">

        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($user['user_username']); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" <?php if ($action === 'create') echo 'required'; ?> placeholder="<?php echo $action === 'edit' ? 'Leave blank to keep current password' : ''; ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Full name</label>
            <input type="text" name="fullname" class="form-control" required value="<?php echo htmlspecialchars($user['user_fullname']); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Level</label>
            <select name="level" class="form-control">
                <option value="admin" <?php if ($user['user_level'] === 'admin') echo 'selected'; ?>>admin</option>
                <option value="user" <?php if ($user['user_level'] === 'user') echo 'selected'; ?>>user</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="active" <?php if ($user['user_status'] === 'active') echo 'selected'; ?>>active</option>
                <option value="inactive" <?php if ($user['user_status'] === 'inactive') echo 'selected'; ?>>inactive</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'Save changes' : 'Create user'; ?></button>
    </form>
</div>

<?php include 'footer.php'; ?>

<?php
require_once 'functions.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if ($username === '' || $password === '') {
        header('Location: login.php?msg=' . urlencode('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน'));
        exit;
    }
    $stmt = $mysqli->prepare('SELECT user_id, user_username, user_password, user_fullname, user_level, user_status FROM tbl_user WHERE user_username = ? LIMIT 1');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
    if (!$user) {
        header('Location: login.php?msg=' . urlencode('ไม่พบผู้ใช้'));
        exit;
    }
    if ($user['user_status'] !== 'active') {
        header('Location: login.php?msg=' . urlencode('บัญชีผู้ใช้ถูกระงับ'));
        exit;
    }
    if (!password_verify($password, $user['user_password'])) {
        header('Location: login.php?msg=' . urlencode('รหัสผ่านไม่ถูกต้อง'));
        exit;
    }
    // success: set session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_username'] = $user['user_username'];
    $_SESSION['user_fullname'] = $user['user_fullname'];
    $_SESSION['user_level'] = $user['user_level'];
    $_SESSION['user_status'] = $user['user_status'];

    header('Location: dashboard.php');
    exit;
}
include 'header.php';
?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h3 class="mb-3">Login</h3>
            <?php if ($msg): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <form method="post" action="login.php">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
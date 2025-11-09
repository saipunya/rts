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
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';

    if ($username === '' || $password === '' || $fullname === '') {
        header('Location: register.php?msg=' . urlencode('กรุณากรอกข้อมูลให้ครบถ้วน'));
        exit;
    }

    // ensure username is not admin reserved
    if (strtolower($username) === 'admin') {
        header('Location: register.php?msg=' . urlencode('ชื่อนี้ไม่สามารถใช้งานได้'));
        exit;
    }

    // check uniqueness
    $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE user_username = ? LIMIT 1');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->fetch_assoc()) {
        $stmt->close();
        header('Location: register.php?msg=' . urlencode('ชื่อผู้ใช้ถูกใช้งานแล้ว'));
        exit;
    }
    $stmt->close();

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $level = 'user';
    $status = 'active';

    $stmt = $mysqli->prepare('INSERT INTO users (user_username, user_password, user_fullname, user_level, user_status) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('sssss', $username, $hash, $fullname, $level, $status);
    if ($stmt->execute()) {
        $stmt->close();
        header('Location: login.php?msg=' . urlencode('ลงทะเบียนสำเร็จ โปรดเข้าสู่ระบบ'));
        exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        header('Location: register.php?msg=' . urlencode('การลงทะเบียนล้มเหลว: ' . $err));
        exit;
    }
}

include 'header.php';
?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h3 class="mb-3">Register</h3>
            <?php if ($msg): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <form method="post" action="register.php">
                <div class="mb-3">
                    <label class="form-label">Full name</label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Register</button>
                <a href="login.php" class="btn btn-link">Login</a>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
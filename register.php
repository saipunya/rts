<?php
require_once 'functions.php';
if(is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
$fullname = $_POST['fullname'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$level = 'user';
$status = 'active';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($fullname) === '' || trim($username) === '' || trim($password) === '') {
        $msg = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        // check if username exists
        $stmt = $mysqli->prepare('SELECT user_id FROM tbl_user WHERE user_username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->fetch_assoc()) {
            $msg = 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว';
        } else {            
            // create user
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $level = 'user';
            $status = 'active';
            $stmt = $mysqli->prepare('INSERT INTO tbl_user (user_username, user_password, user_fullname, user_level, user_status) VALUES (?, ?, ?, ?, ?)');
            if (!$stmt) {
                die('Prepare failed: ' . $mysqli->error);
            }
            $stmt->bind_param('sssss', $username, $hash, $fullname, $level, $status);
            if ($stmt->execute()) {
                header('Location: login.php?msg=' . urlencode('สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ'));
                exit;
            } else {
                $msg = 'การสมัครสมาชิกล้มเหลว: ' . $stmt->error;
            }
            $stmt->close();
        }
        $stmt->close();
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
                <div class="mb-">

                </div>
                <button type="submit" class="btn btn-primary">Register</button>
                <a href="login.php" class="btn btn-link">Login</a>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
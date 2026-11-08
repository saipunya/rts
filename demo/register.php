<?php
require_once 'functions.php';

// handle messages
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// Process form submission (simple escaped INSERT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';

    if ($username === '' || $password === '' || $fullname === '') {
        header('Location: register.php?msg=' . urlencode('กรุณากรอกข้อมูลให้ครบถ้วน'));
        exit;
    }

    if (strtolower($username) === 'admin') {
        header('Location: register.php?msg=' . urlencode('ชื่อนี้ไม่สามารถใช้งานได้'));
        exit;
    }

    // simple uniqueness check and insert using escaped values
    $esc_username = $mysqli->real_escape_string($username);
    $checkSql = "SELECT user_id FROM tbl_user WHERE user_username = '{$esc_username}' LIMIT 1";
    $res = $mysqli->query($checkSql);
    if ($res === false) {
        header('Location: register.php?msg=' . urlencode('เกิดข้อผิดพลาด: ' . $mysqli->error));
        exit;
    }
    if ($res->fetch_assoc()) {
        $res->free();
        header('Location: register.php?msg=' . urlencode('ชื่อผู้ใช้ถูกใช้งานแล้ว'));
        exit;
    }
    $res->free();

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $level = 'user';
    $status = 'active';

    $esc_hash = $mysqli->real_escape_string($hash);
    $esc_fullname = $mysqli->real_escape_string($fullname);
    $esc_level = $mysqli->real_escape_string($level);
    $esc_status = $mysqli->real_escape_string($status);

    $insertSql = "INSERT INTO tbl_user (user_username, user_password, user_fullname, user_level, user_status)
                  VALUES ('{$esc_username}', '{$esc_hash}', '{$esc_fullname}', '{$esc_level}', '{$esc_status}')";
    if ($mysqli->query($insertSql)) {
        header('Location: login.php?msg=' . urlencode('ลงทะเบียนสำเร็จ โปรดเข้าสู่ระบบ'));
        exit;
    } else {
        header('Location: register.php?msg=' . urlencode('การลงทะเบียนล้มเหลว: ' . $mysqli->error));
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
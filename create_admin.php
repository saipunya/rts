<?php
// create_admin.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'functions.php';
// This script creates an initial admin user only if no admin exists.
// IMPORTANT: remove this file after use.
header('Content-Type: text/html; charset=utf-8');

echo '<div style="max-width:800px;margin:40px auto;font-family:Arial,Helvetica,sans-serif">';
// check existing admin
$stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM tbl_user WHERE user_level = 'admin'");
if (!$stmt) {
    echo '<h3>Error preparing statement: ' . htmlspecialchars($mysqli->error) . '</h3>';
    echo '</div>';
    exit;
}
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if ($row && (int)$row['cnt'] > 0) {
    echo '<h3>Admin user already exists.</h3>';
    echo '<p>If you need to reset the password, do so directly in the database or implement a secure reset flow.</p>';
    echo '<p>Delete this file (create_admin.php) for security.</p>';
    echo '</div>';
    exit;
}

// Auto-create path: call create_admin.php?autocreate=1&pw=YOURPASSWORD[&username=admin]
if (isset($_GET['autocreate']) && $_GET['autocreate'] == '1' && isset($_GET['pw'])) {
    $username = isset($_GET['username']) && trim($_GET['username']) !== '' ? trim($_GET['username']) : 'admin';
    $fullname = isset($_GET['fullname']) && trim($_GET['fullname']) !== '' ? trim($_GET['fullname']) : 'Administrator';
    $password = $_GET['pw'];
    if (strlen($password) < 6) {
        echo '<h3>Error: รหัสผ่านควรมีความยาวอย่างน้อย 6 ตัวอักษร</h3>';
        echo '</div>';
        exit;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $level = 'admin';
    $status = 'active';
    $stmt = $mysqli->prepare('INSERT INTO tbl_user (user_username, user_password, user_fullname, user_level, user_status) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        echo '<h3>Prepare failed: ' . htmlspecialchars($mysqli->error) . '</h3>';
        echo '</div>';
        exit;
    }
    $stmt->bind_param('sssss', $username, $hash, $fullname, $level, $status);
    if ($stmt->execute()) {
        echo '<h2>Admin created</h2>';
        echo '<p>Username: <strong>' . htmlspecialchars($username) . '</strong></p>';
        echo '<p>Password (please save now): <strong>' . htmlspecialchars($password) . '</strong></p>';
        echo '<p>หลังจากบันทึกรหัสผ่านแล้ว ให้ลบไฟล์นี้ (create_admin.php) ทันที</p>';
    } else {
        echo '<h3>Insert failed: ' . htmlspecialchars($stmt->error) . '</h3>';
    }
    $stmt->close();
    echo '</div>';
    exit;
}

// If form submitted, create admin with provided password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) && trim($_POST['username']) !== '' ? trim($_POST['username']) : 'admin';
    $fullname = isset($_POST['fullname']) && trim($_POST['fullname']) !== '' ? trim($_POST['fullname']) : 'Administrator';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';

    if ($password === '' || $confirm === '') {
        echo '<h3>Error: กรุณากรอกรหัสผ่านและยืนยันรหัสผ่าน</h3>';
        echo '<p><a href="create_admin.php">กลับ</a></p>';
        echo '</div>';
        exit;
    }
    if ($password !== $confirm) {
        echo '<h3>Error: รหัสผ่านไม่ตรงกัน</h3>';
        echo '<p><a href="create_admin.php">กลับ</a></p>';
        echo '</div>';
        exit;
    }
    if (strlen($password) < 6) {
        echo '<h3>Error: รหัสผ่านควรมีความยาวอย่างน้อย 6 ตัวอักษร</h3>';
        echo '<p><a href="create_admin.php">กลับ</a></p>';
        echo '</div>';
        exit;
    }

    // insert admin
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $level = 'admin';
    $status = 'active';

    $stmt = $mysqli->prepare('INSERT INTO tbl_user (user_username, user_password, user_fullname, user_level, user_status) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        echo '<h3>Prepare failed: ' . htmlspecialchars($mysqli->error) . '</h3>';
        echo '</div>';
        exit;
    }
    $stmt->bind_param('sssss', $username, $hash, $fullname, $level, $status);
    if ($stmt->execute()) {
        echo '<h2>Admin created</h2>';
        echo '<p>Username: <strong>' . htmlspecialchars($username) . '</strong></p>';
        echo '<p>Password (please save now): <strong>' . htmlspecialchars($password) . '</strong></p>';
        echo '<p>หลังจากบันทึกรหัสผ่านแล้ว ให้ลบไฟล์นี้ (create_admin.php) ทันที</p>';
    } else {
        echo '<h3>Insert failed: ' . htmlspecialchars($stmt->error) . '</h3>';
    }
    $stmt->close();

    echo '</div>';
    exit;
}

// Show form to enter desired password
?>
<h2>สร้างบัญชีผู้ดูแลระบบ (admin)</h2>
<p>สร้าง admin เริ่มต้น พร้อมตั้งรหัสผ่านของคุณเอง</p>
<form method="post" action="create_admin.php">
    <div style="margin-bottom:8px;">
        <label>Username (default: admin)</label><br>
        <input type="text" name="username" value="admin" style="width:100%;padding:8px;">
    </div>
    <div style="margin-bottom:8px;">
        <label>Full name (default: Administrator)</label><br>
        <input type="text" name="fullname" value="Administrator" style="width:100%;padding:8px;">
    </div>
    <div style="margin-bottom:8px;">
        <label>Password</label><br>
        <input type="password" name="password" style="width:100%;padding:8px;">
    </div>
    <div style="margin-bottom:8px;">
        <label>Confirm Password</label><br>
        <input type="password" name="confirm" style="width:100%;padding:8px;">
    </div>
    <div>
        <button type="submit" style="padding:8px 12px;">Create admin</button>
    </div>
</form>
<p style="margin-top:12px;color:#a00">หมายเหตุ: เมื่อสร้างเสร็จให้ลบไฟล์ create_admin.php นี้ทันที</p>
<?php
echo '</div>';
?>
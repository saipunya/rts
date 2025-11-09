<?php
require_once 'functions.php';
if(is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
$fullname = $_POST['fullname'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
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
?>
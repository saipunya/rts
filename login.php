<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require __DIR__ . '/config.php';

// Redirect non-POST requests back to the login page
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php');
    exit;
}

function back_with_error(string $msg): void {
    $_SESSION['error'] = $msg;
    header('Location: index.php');
    exit;
}

// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    back_with_error('ไม่สามารถยืนยันคำขอได้ กรุณาลองใหม่');
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$remember = !empty($_POST['remember']);

if ($username === '' || $password === '') {
    back_with_error('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
}

// Better DB error handling against your actual schema (tbl_user)
try {
    $pdo = pdo();

    // Use backticks to avoid identifier issues and select from tbl_user
    $stmt = $pdo->prepare('
        SELECT 
            `user_id`       AS id,
            `user_username` AS username,
            `user_password` AS password,
            `user_fullname` AS fullname,
            `user_level`    AS level,
            `user_status`   AS status
        FROM `tbl_user`
        WHERE `user_username` = ?
        LIMIT 1
    ');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
} catch (Throwable $e) {
    error_log('DB failure: ' . $e->getMessage());
    if (defined('APP_DEBUG') && APP_DEBUG) {
        back_with_error('DB error: ' . $e->getMessage());
    }
    back_with_error('เกิดข้อผิดพลาดของระบบ');
}

if (!$user) {
    back_with_error('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
}

// Robust password check (supports password_hash, plaintext, MD5, SHA1)
$stored = (string)($user['password'] ?? '');
$valid = false;

if ($stored !== '') {
    $info = password_get_info($stored);
    if (($info['algo'] ?? 0) !== 0) {
        $valid = password_verify($password, $stored);
    } else {
        if (hash_equals($stored, $password)) {
            $valid = true; // plaintext
        } elseif (hash_equals($stored, md5($password))) {
            $valid = true; // legacy MD5
        } elseif (hash_equals($stored, sha1($password))) {
            $valid = true; // legacy SHA1
        }
    }
}

if (!$valid) {
    back_with_error('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
}

session_regenerate_id(true);
$_SESSION['user_id']    = (int)$user['id'];
$_SESSION['username']   = (string)$user['username'];
$_SESSION['fullname']   = (string)($user['fullname'] ?? '');
$_SESSION['user_level'] = (string)($user['level'] ?? '');
$_SESSION['user_status']= (string)($user['status'] ?? '');

// Remember me (temporarily disabled — add columns to tbl_user before enabling)
if ($remember) {
    // TODO: add columns `remember_token` VARCHAR(64) and `remember_expiry` DATETIME to `tbl_user` and then
    // update them here. For now, skip DB update to avoid errors.
    /*
    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiryDt  = (new DateTime('+30 days'))->format('Y-m-d H:i:s');
    $upd = $pdo->prepare('UPDATE `tbl_user` SET `remember_token` = ?, `remember_expiry` = ? WHERE `user_id` = ?');
    $upd->execute([$tokenHash, $expiryDt, $user['id']]);
    setcookie('remember', $user['id'] . ':' . $token, [...]);
    */
}

// Rotate CSRF token after use
unset($_SESSION['csrf_token']);

// Redirect to the dashboard after login
header('Location: dashboard.php');
exit;
?>

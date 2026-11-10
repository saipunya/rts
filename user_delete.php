<?php
// user_delete.php
require_once 'functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php?msg=' . urlencode('Invalid request'));
    exit;
}
if (!csrf_check((string)($_POST['csrf_token']??''))) {header('Location: users.php?'.http_build_query(['msg'=>'โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่']));exit;}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header('Location: users.php?msg=' . urlencode('Invalid user id'));
    exit;
}

// Prevent deleting currently logged-in admin
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
    header('Location: users.php?msg=' . urlencode('Cannot delete currently logged-in user'));
    exit;
}

$db=db();ensure_user_permission_schema($db);
try {
    $db->begin_transaction();
    $stmt=$db->prepare('SELECT user_id,user_level FROM tbl_user WHERE user_id=? FOR UPDATE');
    $stmt->bind_param('i',$id);$stmt->execute();$target=$stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$target) throw new DomainException('ไม่พบผู้ใช้');
    if ($target['user_level']==='admin') throw new DomainException('ไม่สามารถลบบัญชี admin จากหน้านี้');
    $stmt=$db->prepare('DELETE FROM tbl_user_menu_permissions WHERE user_id=?');
    $stmt->bind_param('i',$id);$stmt->execute();$stmt->close();
    $stmt=$db->prepare('DELETE FROM '.USER_TABLE.' WHERE user_id=? LIMIT 1');
    $stmt->bind_param('i',$id);$stmt->execute();$stmt->close();
    $db->commit();
    header('Location: users.php?'.http_build_query(['msg'=>'ลบบัญชีและสิทธิ์เมนูเรียบร้อย']));exit;
} catch (Throwable $error) {
    $db->rollback();error_log('user_delete: '.$error->getMessage());
    header('Location: users.php?'.http_build_query(['msg'=>$error instanceof DomainException?$error->getMessage():'ลบผู้ใช้ไม่สำเร็จ กรุณาลองใหม่']));exit;
}

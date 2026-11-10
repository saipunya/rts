<?php
require_once __DIR__.'/functions.php';
require_admin();
function user_save_redirect(string $message): void {
    header('Location: users.php?'.http_build_query(['msg'=>$message]));exit;
}
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') user_save_redirect('คำขอไม่ถูกต้อง');
$db=db();
ensure_user_permission_schema($db);
try {
    if (!csrf_check((string)($_POST['csrf_token']??''))) throw new DomainException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
    $action=(string)($_POST['action']??$_POST['act']??'create');
    $id=(int)($_POST['id']??0);
    $username=trim((string)($_POST['user_username']??$_POST['username']??''));
    $fullname=trim((string)($_POST['user_fullname']??$_POST['fullname']??''));
    $level=(string)($_POST['user_level']??$_POST['level']??'user');
    $status=(string)($_POST['user_status']??$_POST['status']??'active');
    $password=(string)($_POST['user_password']??$_POST['password']??'');
    if ($action==='edit' && ((!isset($_POST['user_level']) && !isset($_POST['level'])) || (!isset($_POST['user_status']) && !isset($_POST['status'])))) throw new DomainException('กรุณาระบุระดับและสถานะผู้ใช้');
    if (!in_array($action,['create','edit'],true) || ($action==='edit' && $id<=0)) throw new DomainException('รายการผู้ใช้ไม่ถูกต้อง');
    if ($username==='' || $fullname==='') throw new DomainException('กรุณากรอกชื่อผู้ใช้และชื่อ–นามสกุล');
    if (!in_array($level,['admin','user'],true) || !in_array($status,['active','inactive'],true)) throw new DomainException('ระดับหรือสถานะผู้ใช้ไม่ถูกต้อง');
    if ($action==='create' && $password==='') throw new DomainException('กรุณากรอกรหัสผ่านสำหรับผู้ใช้ใหม่');
    $setPermissions=$action==='create' || isset($_POST['permissions_present']);
    $selected=rts_normalize_permissions($_POST['permissions']??[]);
    $actor=(int)$_SESSION['user_id'];
    if ($action==='edit' && $id===$actor && ($level!=='admin' || $status!=='active')) throw new DomainException('ไม่สามารถลดสิทธิ์หรือระงับบัญชี admin ที่กำลังใช้งานอยู่');
    $db->begin_transaction();
    try {
        if ($action==='edit') {
            $stmt=$db->prepare('SELECT user_id,user_level,user_status FROM tbl_user WHERE user_id=? FOR UPDATE');
            $stmt->bind_param('i',$id);$stmt->execute();$before=$stmt->get_result()->fetch_assoc();$stmt->close();
            if (!$before) throw new DomainException('ไม่พบผู้ใช้');
            if ($before['user_level']==='admin' && $before['user_status']==='active' && ($level!=='admin' || $status!=='active')) {
                $admins=$db->query("SELECT user_id FROM tbl_user WHERE user_level='admin' AND user_status='active' FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
                if (count($admins)<=1) throw new DomainException('ต้องมี admin ที่ใช้งานได้อย่างน้อยหนึ่งบัญชี');
            }
        }
        $stmt=$db->prepare('SELECT user_id FROM tbl_user WHERE user_username=? AND user_id<>? LIMIT 1');
        $exclude=$action==='edit'?$id:0;$stmt->bind_param('si',$username,$exclude);$stmt->execute();$duplicate=$stmt->get_result()->fetch_assoc();$stmt->close();
        if ($duplicate) throw new DomainException('ชื่อผู้ใช้นี้มีอยู่แล้ว');
        if ($action==='create') {
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $stmt=$db->prepare('INSERT INTO tbl_user (user_username,user_password,user_fullname,user_level,user_status) VALUES (?,?,?,?,?)');
            $stmt->bind_param('sssss',$username,$hash,$fullname,$level,$status);$stmt->execute();$id=(int)$stmt->insert_id;$stmt->close();
        } elseif ($password!=='') {
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $stmt=$db->prepare('UPDATE tbl_user SET user_username=?,user_password=?,user_fullname=?,user_level=?,user_status=? WHERE user_id=?');
            $stmt->bind_param('sssssi',$username,$hash,$fullname,$level,$status,$id);$stmt->execute();$stmt->close();
        } else {
            $stmt=$db->prepare('UPDATE tbl_user SET user_username=?,user_fullname=?,user_level=?,user_status=? WHERE user_id=?');
            $stmt->bind_param('ssssi',$username,$fullname,$level,$status,$id);$stmt->execute();$stmt->close();
        }
        if ($setPermissions) rts_save_user_permissions($db,$id,$level==='admin'?array_keys(rts_permission_catalog()):$selected,$actor);
        $db->commit();
    } catch (Throwable $error) {$db->rollback();throw $error;}
    user_save_redirect('บันทึกบัญชีผู้ใช้และสิทธิ์เมนูเรียบร้อย');
} catch (DomainException $error) {user_save_redirect($error->getMessage());}
catch (Throwable $error) {error_log('user_save: '.$error->getMessage());user_save_redirect('บันทึกผู้ใช้ไม่สำเร็จ กรุณาลองใหม่');}

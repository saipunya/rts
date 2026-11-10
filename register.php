<?php
require_once 'functions.php';

$msg = is_string($_GET['msg'] ?? null) ? $_GET['msg'] : '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_check((string)($_POST['csrf_token']??''))) throw new DomainException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
        $username=trim((string)($_POST['username']??''));
        $password=(string)($_POST['password']??'');
        $fullname=trim((string)($_POST['fullname']??''));
        if ($username==='' || $password==='' || $fullname==='') throw new DomainException('กรุณากรอกข้อมูลให้ครบถ้วน');
        if (strtolower($username)==='admin') throw new DomainException('ชื่อนี้ไม่สามารถใช้งานได้');
        $db=db();ensure_user_permission_schema($db);
        $db->begin_transaction();
        try {
            $stmt=$db->prepare('SELECT user_id FROM tbl_user WHERE user_username=? LIMIT 1');
            $stmt->bind_param('s',$username);$stmt->execute();$duplicate=$stmt->get_result()->fetch_assoc();$stmt->close();
            if ($duplicate) throw new DomainException('ชื่อผู้ใช้ถูกใช้งานแล้ว');
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $stmt=$db->prepare("INSERT INTO tbl_user (user_username,user_password,user_fullname,user_level,user_status) VALUES (?,?,?,'user','active')");
            $stmt->bind_param('sss',$username,$hash,$fullname);$stmt->execute();$id=(int)$stmt->insert_id;$stmt->close();
            // Self-registration must never inherit unrestricted legacy permissions.
            rts_save_user_permissions($db,$id,[],0);
            $db->commit();
        } catch (Throwable $error) {$db->rollback();throw $error;}
        header('Location: login.php?'.http_build_query(['msg'=>'ลงทะเบียนสำเร็จ กรุณาติดต่อ admin เพื่อกำหนดสิทธิ์เมนู']));exit;
    } catch (DomainException $error) {$msg=$error->getMessage();}
    catch (Throwable $error) {error_log('register: '.$error->getMessage());$msg='ลงทะเบียนไม่สำเร็จ กรุณาลองใหม่';}
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
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
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
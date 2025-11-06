<?php
require_once __DIR__ . '/../config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$errors = [];
// Load existing
$stmt = $mysqli->prepare("SELECT mem_group, mem_number, mem_fullname, mem_class, mem_saveby, mem_savedate FROM tbl_member WHERE mem_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($mem_group, $mem_number, $mem_fullname, $mem_class, $mem_saveby, $mem_savedate);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: index.php');
    exit;
}
$stmt->close();

$values = [
    'mem_group' => $mem_group,
    'mem_number' => $mem_number,
    'mem_fullname' => $mem_fullname,
    'mem_class' => $mem_class,
    'mem_saveby' => $mem_saveby,
    'mem_savedate' => $mem_savedate,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }

    $values['mem_group']   = trim($_POST['mem_group'] ?? '');
    $values['mem_number']  = trim($_POST['mem_number'] ?? '');
    $values['mem_fullname']= trim($_POST['mem_fullname'] ?? '');
    $values['mem_class']   = trim($_POST['mem_class'] ?? '');
    $values['mem_saveby']  = trim($_POST['mem_saveby'] ?? '');
    $values['mem_savedate']= trim($_POST['mem_savedate'] ?? '');

    foreach (['mem_group','mem_number','mem_fullname','mem_class','mem_saveby','mem_savedate'] as $f) {
        if ($values[$f] === '') {
            $errors[] = "กรุณากรอก {$f}";
        }
    }

    if (!$errors) {
        $u = $mysqli->prepare("UPDATE tbl_member SET mem_group=?, mem_number=?, mem_fullname=?, mem_class=?, mem_saveby=?, mem_savedate=? WHERE mem_id=?");
        if (!$u) {
            $errors[] = 'เตรียมคำสั่งล้มเหลว';
        } else {
            $u->bind_param(
                'ssssssi',
                $values['mem_group'],
                $values['mem_number'],
                $values['mem_fullname'],
                $values['mem_class'],
                $values['mem_saveby'],
                $values['mem_savedate'],
                $id
            );
            if ($u->execute()) {
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'บันทึกไม่สำเร็จ';
            }
            $u->close();
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>แก้ไขสมาชิก</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:system-ui,Arial,sans-serif;max-width:700px;margin:20px auto;padding:0 12px}
        label{display:block;margin-top:10px}
        input{width:100%;padding:8px;margin-top:4px;box-sizing:border-box}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .actions{margin-top:16px;display:flex;gap:8px}
        a.button,button{background:#0d6efd;color:#fff;border:0;padding:8px 12px;border-radius:4px;text-decoration:none;cursor:pointer}
        a.button.secondary{background:#6c757d}
        .error{color:#dc3545}
    </style>
</head>
<body>
    <h2>แก้ไขสมาชิก #<?= e($id) ?></h2>

    <?php if ($errors): ?>
        <div class="error">
            <?php foreach ($errors as $e): ?>
                <div><?= e($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="row">
            <div>
                <label>กลุ่ม
                    <input type="text" name="mem_group" required value="<?= e($values['mem_group']) ?>">
                </label>
            </div>
            <div>
                <label>หมายเลข
                    <input type="text" name="mem_number" required value="<?= e($values['mem_number']) ?>">
                </label>
            </div>
        </div>
        <label>ชื่อ-นามสกุล
            <input type="text" name="mem_fullname" required value="<?= e($values['mem_fullname']) ?>">
        </label>
        <div class="row">
            <div>
                <label>ชั้น
                    <input type="text" name="mem_class" required value="<?= e($values['mem_class']) ?>">
                </label>
            </div>
            <div>
                <label>บันทึกโดย
                    <input type="text" name="mem_saveby" required value="<?= e($values['mem_saveby']) ?>">
                </label>
            </div>
        </div>
        <label>วันที่บันทึก
            <input type="date" name="mem_savedate" required value="<?= e($values['mem_savedate']) ?>">
        </label>

        <div class="actions">
            <button type="submit">บันทึก</button>
            <a class="button secondary" href="index.php">ยกเลิก</a>
        </div>
    </form>
</body>
</html>

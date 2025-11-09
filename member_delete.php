<?php
require_once 'functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: members.php?msg=' . urlencode('Invalid request'));
    exit;
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header('Location: members.php?msg=' . urlencode('Invalid member id'));
    exit;
}

$stmt = $mysqli->prepare('DELETE FROM tbl_member WHERE mem_id = ?');
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$err = '';
if (!$ok) {
    $err = $stmt->error;
}
$stmt->close();

if (!empty($err)) {
    header('Location: members.php?msg=' . urlencode('Error: ' . $err));
} else {
    header('Location: members.php?msg=' . urlencode('ลบสมาชิกเรียบร้อยแล้ว'));
}
exit;

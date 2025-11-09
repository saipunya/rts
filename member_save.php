<?php
require_once 'functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: members.php?msg=' . urlencode('Invalid request'));
    exit;
}
$action = isset($_POST['action']) ? $_POST['action'] : 'create';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$mem_group = isset($_POST['mem_group']) ? trim($_POST['mem_group']) : '';
$mem_number = isset($_POST['mem_number']) ? trim($_POST['mem_number']) : '';
$mem_fullname = isset($_POST['mem_fullname']) ? trim($_POST['mem_fullname']) : '';
$mem_class = isset($_POST['mem_class']) ? trim($_POST['mem_class']) : '';

if ($mem_group === '' || $mem_number === '' || $mem_fullname === '' || $mem_class === '') {
    header('Location: members.php?msg=' . urlencode('กรุณากรอกข้อมูลให้ครบ'));
    exit;
}

// determine saveby
$cu = current_user();
$saveby = $cu['user_fullname'] ?? $cu['user_username'] ?? 'system';
$savedate = date('Y-m-d');

if ($action === 'create') {
    $stmt = $mysqli->prepare('INSERT INTO tbl_member (mem_group, mem_number, mem_fullname, mem_class, mem_saveby, mem_savedate) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('ssssss', $mem_group, $mem_number, $mem_fullname, $mem_class, $saveby, $savedate);
    $ok = $stmt->execute();
    if (!$ok) {
        $err = $stmt->error;
    }
    $stmt->close();
    if (!empty($err)) {
        header('Location: members.php?msg=' . urlencode('Error: ' . $err));
    } else {
        header('Location: members.php?msg=' . urlencode('เพิ่มสมาชิกเรียบร้อยแล้ว'));
    }
    exit;
} elseif ($action === 'edit') {
    if ($id <= 0) {
        header('Location: members.php?msg=' . urlencode('Invalid member id'));
        exit;
    }
    $stmt = $mysqli->prepare('UPDATE tbl_member SET mem_group = ?, mem_number = ?, mem_fullname = ?, mem_class = ?, mem_saveby = ?, mem_savedate = ? WHERE mem_id = ?');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('ssssssi', $mem_group, $mem_number, $mem_fullname, $mem_class, $saveby, $savedate, $id);
    $ok = $stmt->execute();
    if (!$ok) {
        $err = $stmt->error;
    }
    $stmt->close();
    if (!empty($err)) {
        header('Location: members.php?msg=' . urlencode('Error: ' . $err));
    } else {
        header('Location: members.php?msg=' . urlencode('แก้ไขสมาชิกเรียบร้อยแล้ว'));
    }
    exit;
} else {
    header('Location: members.php?msg=' . urlencode('Unknown action'));
    exit;
}

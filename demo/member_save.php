<?php
require_once 'functions.php';
require_login();

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

$mem_personcode = trim($_POST['mem_personcode'] ?? '');
$mem_birthtext = trim($_POST['mem_birthtext'] ?? '');

$fieldLabels = [
    'mem_group' => 'กลุ่ม',
    'mem_number' => 'เลขที่สมาชิก',
    'mem_fullname' => 'ชื่อ-สกุล',
    'mem_class' => 'ชั้น',
    'mem_personcode' => 'รหัสบุคคล',
];
$missingFields = [];
if ($mem_group === '') $missingFields[] = $fieldLabels['mem_group'];
if ($mem_number === '') $missingFields[] = $fieldLabels['mem_number'];
if ($mem_fullname === '') $missingFields[] = $fieldLabels['mem_fullname'];
if ($mem_class === '') $missingFields[] = $fieldLabels['mem_class'];
if ($mem_personcode === '') $missingFields[] = $fieldLabels['mem_personcode'];

if ($missingFields) {
    header('Location: members.php?msg=' . urlencode('กรุณากรอกข้อมูลให้ครบ: ' . implode(', ', $missingFields)));
    exit;
}

if (!in_array($mem_class, ['member', 'general'], true)) {
    header('Location: members.php?msg=' . urlencode('ประเภทสมาชิกไม่ถูกต้อง'));
    exit;
}

if (!preg_match('/^[0-9]{4}$/D', $mem_personcode)) {
    header('Location: members.php?msg=' . urlencode('รหัสบุคคลต้องเป็นตัวเลข 4 หลัก'));
    exit;
}

// determine saveby
$cu = current_user();
$saveby = $cu['user_fullname'] ?? $cu['user_username'] ?? 'system';
$savedate = date('Y-m-d');

$limitedFields = [
    [$mem_group, 255, 'กลุ่ม'],
    [$mem_number, 255, 'เลขที่สมาชิก'],
    [$mem_fullname, 255, 'ชื่อ-สกุล'],
    [$mem_class, 255, 'ชั้น'],
    [$mem_birthtext, 8, 'ข้อมูลวันเกิด'],
    [$saveby, 255, 'ผู้บันทึก'],
];
foreach ($limitedFields as [$value, $maxLength, $label]) {
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        header('Location: members.php?msg=' . urlencode($label . 'ต้องไม่เกิน ' . $maxLength . ' ตัวอักษร'));
        exit;
    }
}
$mem_birthtext = $mem_birthtext === '' ? null : $mem_birthtext;

if ($action === 'create') {
    $stmt = $mysqli->prepare('INSERT INTO tbl_member (mem_group, mem_number, mem_fullname, mem_class, mem_personcode, mem_birthtext, mem_saveby, mem_savedate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('ssssssss', $mem_group, $mem_number, $mem_fullname, $mem_class, $mem_personcode, $mem_birthtext, $saveby, $savedate);
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
    $stmt = $mysqli->prepare('UPDATE tbl_member SET mem_group = ?, mem_number = ?, mem_fullname = ?, mem_class = ?, mem_personcode = ?, mem_birthtext = ?, mem_saveby = ?, mem_savedate = ? WHERE mem_id = ?');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('ssssssssi', $mem_group, $mem_number, $mem_fullname, $mem_class, $mem_personcode, $mem_birthtext, $saveby, $savedate, $id);
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

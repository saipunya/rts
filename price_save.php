<?php
// filepath: /Users/sumet/Desktop/rts/price_save.php
require_once 'functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: prices.php?msg=' . urlencode('Invalid request')); exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : 'create';
$pr_id = isset($_POST['pr_id']) ? (int)$_POST['pr_id'] : 0;
$pr_year = isset($_POST['pr_year']) ? (int)$_POST['pr_year'] : 0;
$pr_date = isset($_POST['pr_date']) ? trim($_POST['pr_date']) : '';
$pr_number = isset($_POST['pr_number']) ? trim($_POST['pr_number']) : '';
$pr_price = isset($_POST['pr_price']) ? trim($_POST['pr_price']) : '';

// basic validation
$errors = [];
if ($pr_year <= 0) $errors[] = 'กรุณากรอกปี';
if ($pr_date === '') $errors[] = 'กรุณากรอกวันที่';
if ($pr_number === '') $errors[] = 'กรุณากรอกรอบ';
if ($pr_price === '' || !is_numeric(str_replace(',', '', $pr_price))) $errors[] = 'กรุณากรอกราคาเป็นตัวเลข';

if (!empty($errors)) {
    header('Location: price_form.php?action=' . urlencode($action) . '&id=' . urlencode($pr_id) . '&msg=' . urlencode(implode('; ', $errors)));
    exit;
}

// normalize price
$pr_price = str_replace(',', '', $pr_price);
$pr_price = (float)$pr_price;

// determine saveby
$cu = current_user();
$pr_saveby = '';
if ($cu) {
    $pr_saveby = $cu['user_fullname'] ?? $cu['user_username'] ?? '';
}
if ($pr_saveby === '') $pr_saveby = 'system';

$pr_savedate = date('Y-m-d');

if ($action === 'create') {
    $stmt = $mysqli->prepare("INSERT INTO tbl_price (pr_year, pr_date, pr_number, pr_price, pr_saveby, pr_savedate) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('issdss', $pr_year, $pr_date, $pr_number, $pr_price, $pr_saveby, $pr_savedate);
    $ok = $stmt->execute();
    if (!$ok) {
        $err = $stmt->error;
    }
    $stmt->close();
    if (!empty($err)) {
        header('Location: price_form.php?action=create&msg=' . urlencode('Error: ' . $err)); exit;
    }
    header('Location: prices.php?msg=' . urlencode('บันทึกเรียบร้อย')); exit;
} elseif ($action === 'edit' && $pr_id > 0) {
    $stmt = $mysqli->prepare("UPDATE tbl_price SET pr_year = ?, pr_date = ?, pr_number = ?, pr_price = ?, pr_saveby = ?, pr_savedate = ? WHERE pr_id = ? LIMIT 1");
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('issdssi', $pr_year, $pr_date, $pr_number, $pr_price, $pr_saveby, $pr_savedate, $pr_id);
    $ok = $stmt->execute();
    if (!$ok) {
        $err = $stmt->error;
    }
    $stmt->close();
    if (!empty($err)) {
        header('Location: price_form.php?action=edit&id=' . urlencode($pr_id) . '&msg=' . urlencode('Error: ' . $err)); exit;
    }
    header('Location: prices.php?msg=' . urlencode('แก้ไขเรียบร้อย')); exit;
} else {
    header('Location: prices.php?msg=' . urlencode('Invalid action')); exit;
}

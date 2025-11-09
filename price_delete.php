<?php
// filepath: /Users/sumet/Desktop/rts/price_delete.php
require_once 'functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: prices.php?msg=' . urlencode('Invalid request')); exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header('Location: prices.php?msg=' . urlencode('Invalid id')); exit;
}

$stmt = $mysqli->prepare("DELETE FROM tbl_price WHERE pr_id = ? LIMIT 1");
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
if (!$ok) {
    $err = $stmt->error;
}
$stmt->close();

if (!empty($err)) {
    header('Location: prices.php?msg=' . urlencode('Error: ' . $err)); exit;
}

header('Location: prices.php?msg=' . urlencode('ลบเรียบร้อย')); exit;

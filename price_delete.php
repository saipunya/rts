<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require __DIR__ . '/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = isset($_POST['pr_id']) ? (int)$_POST['pr_id'] : 0;
  if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM tbl_price WHERE pr_id = ?");
    $stmt->execute([$id]);
    flash('ลบข้อมูลสำเร็จ');
  }
}
header('Location: price_list.php'); exit;

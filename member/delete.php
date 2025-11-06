<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo "Invalid CSRF token.";
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0) {
    $stmt = $mysqli->prepare("DELETE FROM tbl_member WHERE mem_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: index.php');
exit;

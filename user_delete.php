<?php
// user_delete.php
require_once 'functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php?msg=' . urlencode('Invalid request'));
    exit;
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header('Location: users.php?msg=' . urlencode('Invalid user id'));
    exit;
}

// Optional: prevent deleting the last admin (not implemented here)

$stmt = $mysqli->prepare('DELETE FROM users WHERE user_id = ? LIMIT 1');
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    $stmt->close();
    header('Location: users.php?msg=' . urlencode('User deleted'));
    exit;
} else {
    $err = $stmt->error;
    $stmt->close();
    header('Location: users.php?msg=' . urlencode('Delete failed: ' . $err));
    exit;
}
?>
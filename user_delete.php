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

// Prevent deleting currently logged-in admin
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
    header('Location: users.php?msg=' . urlencode('Cannot delete currently logged-in user'));
    exit;
}

// Use central USER_TABLE constant
$stmt = $mysqli->prepare('DELETE FROM ' . USER_TABLE . ' WHERE user_id = ? LIMIT 1');
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
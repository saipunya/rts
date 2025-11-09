<?php
// user_save.php
// Save new user or update existing user
include 'functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php?msg=' . urlencode('Invalid request'));
    exit;
}
$action = isset($_POST['action']) ? $_POST['action'] : 'create';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
$level = isset($_POST['level']) ? trim($_POST['level']) : 'user';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'active';

// basic validation
if ($username === '' || $fullname === '') {
    header('Location: users.php?msg=' . urlencode('Username and fullname are required'));
    exit;
}
if (!in_array($level, ['admin', 'user'])) $level = 'user';
if (!in_array($status, ['active', 'inactive'])) $status = 'active';

// create
if ($action === 'create') {
    // check username unique
    $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE user_username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->fetch_assoc()) {
        $stmt->close();
        header('Location: user_form.php?action=create&msg=' . urlencode('Username already exists'));
        exit;
    }
    $stmt->close();

    if ($password === '') {
        header('Location: user_form.php?action=create&msg=' . urlencode('Password is required'));
        exit;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare('INSERT INTO users (user_username, user_password, user_fullname, user_level, user_status) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('sssss', $username, $hash, $fullname, $level, $status);
    if ($stmt->execute()) {
        $stmt->close();
        header('Location: users.php?msg=' . urlencode('User created'));
        exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        header('Location: users.php?msg=' . urlencode('Insert failed: ' . $err));
        exit;
    }
}

// update
if ($action === 'edit') {
    if ($id <= 0) {
        header('Location: users.php?msg=' . urlencode('Invalid user id'));
        exit;
    }
    // fetch existing
    $stmt = $mysqli->prepare('SELECT user_id, user_username, user_password FROM users WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res->fetch_assoc();
    $stmt->close();
    if (!$existing) {
        header('Location: users.php?msg=' . urlencode('User not found'));
        exit;
    }
    // if username changed, ensure unique
    if ($username !== $existing['user_username']) {
        $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE user_username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r->fetch_assoc()) {
            $stmt->close();
            header('Location: user_form.php?action=edit&id=' . $id . '&msg=' . urlencode('Username already in use'));
            exit;
        }
        $stmt->close();
    }
    // build update query
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $mysqli->prepare('UPDATE users SET user_username = ?, user_password = ?, user_fullname = ?, user_level = ?, user_status = ? WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('sssssi', $username, $hash, $fullname, $level, $status, $id);
    } else {
        $stmt = $mysqli->prepare('UPDATE users SET user_username = ?, user_fullname = ?, user_level = ?, user_status = ? WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('ssssi', $username, $fullname, $level, $status, $id);
    }
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    if ($stmt->execute()) {
        $stmt->close();
        header('Location: users.php?msg=' . urlencode('User updated'));
        exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        header('Location: users.php?msg=' . urlencode('Update failed: ' . $err));
        exit;
    }
}

// fallback
header('Location: users.php?msg=' . urlencode('Unknown action'));
exit;
?>
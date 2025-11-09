<?php
require_once 'functions.php';
require_admin();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php?msg=' . urlencode('Invalid request'));
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : 'create';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$username = isset($_POST['user_username']) ? trim($_POST['user_username']) : '';
$fullname = isset($_POST['user_fullname']) ? trim($_POST['user_fullname']) : '';
$level = isset($_POST['user_level']) ? trim($_POST['user_level']) : '';
$status = isset($_POST['user_status']) ? trim($_POST['user_status']) : '';
$password = isset($_POST['user_password']) ? $_POST['user_password'] : '';

// basic validation
if ($username === '' || $fullname === '' || $level === '' || $status === '') {
    header('Location: users.php?msg=' . urlencode('Please fill required fields'));
    exit;
}

try {
    $mysqli->begin_transaction();

    // check duplicate username
    if ($action === 'create') {
        $checkSql = "SELECT user_id FROM tbl_user WHERE user_username = ? LIMIT 1";
        $stmt = $mysqli->prepare($checkSql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $mysqli->rollback();
            header('Location: users.php?msg=' . urlencode('Username already exists'));
            exit;
        }
        $stmt->close();

        if ($password === '') {
            $mysqli->rollback();
            header('Location: users.php?msg=' . urlencode('Password is required for new user'));
            exit;
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $insertSql = "INSERT INTO tbl_user (user_username, user_password, user_fullname, user_level, user_status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insertSql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        $stmt->bind_param('sssss', $username, $hashed, $fullname, $level, $status);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $stmt->close();
        $mysqli->commit();
        header('Location: users.php?msg=' . urlencode('User created'));
        exit;
    } elseif ($action === 'edit') {
        // ensure user exists
        $checkSql = "SELECT user_id FROM tbl_user WHERE user_id = ? LIMIT 1";
        $stmt = $mysqli->prepare($checkSql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            $mysqli->rollback();
            header('Location: users.php?msg=' . urlencode('User not found'));
            exit;
        }
        $stmt->close();

        // check username duplicate excluding current id
        $checkSql = "SELECT user_id FROM tbl_user WHERE user_username = ? AND user_id <> ? LIMIT 1";
        $stmt = $mysqli->prepare($checkSql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        $stmt->bind_param('si', $username, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $mysqli->rollback();
            header('Location: users.php?msg=' . urlencode('Username already used by another user'));
            exit;
        }
        $stmt->close();

        if ($password !== '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $updateSql = "UPDATE tbl_user SET user_username = ?, user_password = ?, user_fullname = ?, user_level = ?, user_status = ? WHERE user_id = ?";
            $stmt = $mysqli->prepare($updateSql);
            if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
            $stmt->bind_param('sssssi', $username, $hashed, $fullname, $level, $status, $id);
        } else {
            $updateSql = "UPDATE tbl_user SET user_username = ?, user_fullname = ?, user_level = ?, user_status = ? WHERE user_id = ?";
            $stmt = $mysqli->prepare($updateSql);
            if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
            $stmt->bind_param('ssssi', $username, $fullname, $level, $status, $id);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $stmt->close();
        $mysqli->commit();
        header('Location: users.php?msg=' . urlencode('User updated'));
        exit;
    } else {
        $mysqli->rollback();
        header('Location: users.php?msg=' . urlencode('Unknown action'));
        exit;
    }
} catch (Exception $e) {
    if ($mysqli->errno) $mysqli->rollback();
    // log error somewhere in production
    header('Location: users.php?msg=' . urlencode('Database error'));
    exit;
}
?>
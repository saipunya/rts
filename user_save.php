<?php
require_once 'functions.php';
require_admin();

// enable detailed errors in development only
define('DEV', true);
if (DEV) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// basic mysqli existence/connection check
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    error_log('user_save.php: $mysqli not available');
    header('Location: users.php?msg=' . urlencode('Server error: missing DB connection'));
    exit;
}
if ($mysqli->connect_errno) {
    error_log('user_save.php: MySQL connect error: ' . $mysqli->connect_error);
    header('Location: users.php?msg=' . urlencode('DB connection error'));
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php?msg=' . urlencode('Invalid request'));
    exit;
}

// accept common alternative field names to avoid form/name mismatch
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_POST['act']) ? $_POST['act'] : 'create');
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$username = '';
if (isset($_POST['user_username'])) $username = trim($_POST['user_username']);
elseif (isset($_POST['username'])) $username = trim($_POST['username']);

$fullname = '';
if (isset($_POST['user_fullname'])) $fullname = trim($_POST['user_fullname']);
elseif (isset($_POST['fullname'])) $fullname = trim($_POST['fullname']);

$level = '';
if (isset($_POST['user_level'])) $level = trim($_POST['user_level']);
elseif (isset($_POST['level'])) $level = trim($_POST['level']);

$status = '';
if (isset($_POST['user_status'])) $status = trim($_POST['user_status']);
elseif (isset($_POST['status'])) $status = trim($_POST['status']);

$password = '';
if (isset($_POST['user_password'])) $password = $_POST['user_password'];
elseif (isset($_POST['password'])) $password = $_POST['password'];

// basic validation
if ($username === '' || $fullname === '' || $level === '' || $status === '') {
    if (defined('DEV') && DEV) {
        error_log('user_save.php: Missing required fields. POST=' . print_r($_POST, true));
    }
    header('Location: users.php?msg=' . urlencode('Please fill required fields'));
    exit;
}

$transactionStarted = false;
$usingAutocommitFallback = false;

try {
    // start transaction (fallback to autocommit(false) if not available)
    if (method_exists($mysqli, 'begin_transaction')) {
        if (!$mysqli->begin_transaction()) {
            throw new Exception('Failed to start transaction: ' . $mysqli->error);
        }
        $transactionStarted = true;
    } else {
        // older PHP/MySQL setups
        if (!$mysqli->autocommit(false)) {
            throw new Exception('Failed to disable autocommit: ' . $mysqli->error);
        }
        $transactionStarted = true;
        $usingAutocommitFallback = true;
    }

    // check duplicate username
    if ($action === 'create') {
        $checkSql = "SELECT user_id FROM tbl_user WHERE user_username = ? LIMIT 1";
        $stmt = $mysqli->prepare($checkSql);
        if (!$stmt) throw new Exception('Prepare failed (check dup): ' . $mysqli->error);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            if ($transactionStarted) $mysqli->rollback();
            if ($usingAutocommitFallback) $mysqli->autocommit(true);
            header('Location: users.php?msg=' . urlencode('Username already exists'));
            exit;
        }
        $stmt->close();

        if ($password === '') {
            if ($transactionStarted) $mysqli->rollback();
            if ($usingAutocommitFallback) $mysqli->autocommit(true);
            header('Location: users.php?msg=' . urlencode('Password is required for new user'));
            exit;
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $insertSql = "INSERT INTO tbl_user (user_username, user_password, user_fullname, user_level, user_status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insertSql);
        if (!$stmt) throw new Exception('Prepare failed (insert): ' . $mysqli->error);
        $stmt->bind_param('sssss', $username, $hashed, $fullname, $level, $status);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('Execute failed (insert): ' . $err);
        }
        $stmt->close();

        // commit and restore autocommit if needed
        if (!$mysqli->commit()) throw new Exception('Commit failed: ' . $mysqli->error);
        if ($usingAutocommitFallback) $mysqli->autocommit(true);

        header('Location: users.php?msg=' . urlencode('User created'));
        exit;
    } elseif ($action === 'edit') {
        // ensure user exists
        $checkSql = "SELECT user_id FROM tbl_user WHERE user_id = ? LIMIT 1";
        $stmt = $mysqli->prepare($checkSql);
        if (!$stmt) throw new Exception('Prepare failed (exist): ' . $mysqli->error);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            if ($transactionStarted) $mysqli->rollback();
            if ($usingAutocommitFallback) $mysqli->autocommit(true);
            header('Location: users.php?msg=' . urlencode('User not found'));
            exit;
        }
        $stmt->close();

        // check username duplicate excluding current id
        $checkSql = "SELECT user_id FROM tbl_user WHERE user_username = ? AND user_id <> ? LIMIT 1";
        $stmt = $mysqli->prepare($checkSql);
        if (!$stmt) throw new Exception('Prepare failed (dup exclude): ' . $mysqli->error);
        $stmt->bind_param('si', $username, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            if ($transactionStarted) $mysqli->rollback();
            if ($usingAutocommitFallback) $mysqli->autocommit(true);
            header('Location: users.php?msg=' . urlencode('Username already used by another user'));
            exit;
        }
        $stmt->close();

        if ($password !== '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $updateSql = "UPDATE tbl_user SET user_username = ?, user_password = ?, user_fullname = ?, user_level = ?, user_status = ? WHERE user_id = ?";
            $stmt = $mysqli->prepare($updateSql);
            if (!$stmt) throw new Exception('Prepare failed (update pw): ' . $mysqli->error);
            $stmt->bind_param('sssssi', $username, $hashed, $fullname, $level, $status, $id);
        } else {
            $updateSql = "UPDATE tbl_user SET user_username = ?, user_fullname = ?, user_level = ?, user_status = ? WHERE user_id = ?";
            $stmt = $mysqli->prepare($updateSql);
            if (!$stmt) throw new Exception('Prepare failed (update): ' . $mysqli->error);
            $stmt->bind_param('ssssi', $username, $fullname, $level, $status, $id);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('Execute failed (update): ' . $err);
        }
        $stmt->close();

        // commit and restore autocommit if needed
        if (!$mysqli->commit()) throw new Exception('Commit failed: ' . $mysqli->error);
        if ($usingAutocommitFallback) $mysqli->autocommit(true);

        header('Location: users.php?msg=' . urlencode('User updated'));
        exit;
    } else {
        if ($transactionStarted) $mysqli->rollback();
        if ($usingAutocommitFallback) $mysqli->autocommit(true);
        header('Location: users.php?msg=' . urlencode('Unknown action'));
        exit;
    }
} catch (Exception $e) {
    // safe rollback if transaction was started
    if (isset($transactionStarted) && $transactionStarted && isset($mysqli) && ($mysqli instanceof mysqli)) {
        $mysqli->rollback();
        if ($usingAutocommitFallback) $mysqli->autocommit(true);
    }
    // always log the detailed error
    error_log('user_save.php Exception: ' . $e->getMessage() . ' POST=' . print_r($_POST, true));

    // expose message only in DEV for debugging
    if (defined('DEV') && DEV) {
        $msg = 'Database error: ' . $e->getMessage();
    } else {
        $msg = 'Database error';
    }
    header('Location: users.php?msg=' . urlencode($msg));
    exit;
}
?>
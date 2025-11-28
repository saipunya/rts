<?php
require_once 'functions.php';
require_admin();

// enable detailed errors in development only
define('DEV', false);
if (DEV) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// new helper: respond - show debug info in DEV, otherwise redirect
function respond($msg, $debug = null) {
    if (defined('DEV') && DEV) {
        echo "<h2>Debug message</h2>\n";
        echo "<pre>" . htmlspecialchars($msg) . "</pre>\n";
        if ($debug !== null) {
            echo "<h3>Debug data</h3>\n<pre>";
            if (is_array($debug) || is_object($debug)) {
                print_r($debug);
            } else {
                echo htmlspecialchars((string)$debug);
            }
            echo "</pre>\n";
        }
        // also show $_POST and last mysqli error if available
        echo "<h3>\$_POST</h3>\n<pre>";
        print_r($_POST);
        echo "</pre>\n";
        if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
            echo "<h3>MySQLi error</h3>\n<pre>";
            echo htmlspecialchars($GLOBALS['mysqli']->error);
            echo "</pre>\n";
        }
        exit;
    } else {
        // Redirect to dashboard if user created
        if ($msg === 'User created') {
            header('Location: dashboard.php?msg=' . urlencode($msg));
        } else {
            header('Location: users.php?msg=' . urlencode($msg));
        }
        exit;
    }
}

// basic mysqli existence/connection check
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    error_log('user_save.php: $mysqli not available');
    respond('Server error: missing DB connection');
}
if ($mysqli->connect_errno) {
    error_log('user_save.php: MySQL connect error: ' . $mysqli->connect_error);
    respond('DB connection error: ' . $mysqli->connect_error);
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('Invalid request');
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

// === CHANGED: allow defaults for create ===
// If creating and level/status not provided, set defaults so registration forms without those fields work.
if ($action === 'create') {
    if ($level === '') $level = 'user';
    if ($status === '') $status = 'active';
    // now require only username/fullname (password checked later)
    if ($username === '' || $fullname === '') {
        error_log('user_save.php: Missing required fields. POST=' . print_r($_POST, true));
        respond('Please fill required fields', ['missing' => ['username'=>$username==='','fullname'=>$fullname==='']]);
    }
} else {
    // For edit, require level/status values (keep stricter validation)
    if ($username === '' || $fullname === '' || $level === '' || $status === '') {
        error_log('user_save.php: Missing required fields for edit. POST=' . print_r($_POST, true));
        respond('Please fill required fields', ['missing' => ['username'=>$username==='','fullname'=>$fullname==='','level'=>$level==='','status'=>$status==='']]);
    }
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
            respond('Username already exists');
        }
        $stmt->close();

        if ($password === '') {
            if ($transactionStarted) $mysqli->rollback();
            if ($usingAutocommitFallback) $mysqli->autocommit(true);
            respond('Password is required for new user');
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

        respond('User created', ['insert_id' => $mysqli->insert_id]);
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
            respond('User not found');
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
            respond('Username already used by another user');
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

        respond('User updated', ['affected_rows' => $mysqli->affected_rows]);
    } else {
        if ($transactionStarted) $mysqli->rollback();
        if ($usingAutocommitFallback) $mysqli->autocommit(true);
        respond('Unknown action', ['action' => $action]);
    }
} catch (Exception $e) {
    // safe rollback if transaction was started
    if (isset($transactionStarted) && $transactionStarted && isset($mysqli) && ($mysqli instanceof mysqli)) {
        $mysqli->rollback();
        if ($usingAutocommitFallback) $mysqli->autocommit(true);
    }
    // always log the detailed error
    error_log('user_save.php Exception: ' . $e->getMessage() . ' POST=' . print_r($_POST, true));
    respond('Database error: ' . $e->getMessage(), ['exception' => $e->__toString()]);
}
?>
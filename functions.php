<?php
session_start();
$host = 'localhost';
$username = 'rts_user';
$password = 'sumet4631022';
$database = 'rts_db';
$mydb = mysqli_connect($host, $username, $password, $database);
if (!$mydb) {
    die("Connection failed: " . mysqli_connect_error());
} else {
    // Connection successful
    echo "Database connection established.";
}
$mysqli = new mysqli($host, $username, $password, $database);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// helper to check admin
function is_admin(): bool {
    return isset($_SESSION['user_level']) && $_SESSION['user_level'] === 'admin';
}

function require_admin() {
    if (!is_admin()) {
        header('Location: index.php?msg=' . urlencode('Access denied: admin only'));
        exit;
    }
}

// helper to check logged in
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php?msg=' . urlencode('Please login'));
        exit;
    }
}

function current_user() {
    if (!is_logged_in()) return null;
    return [
        'user_id' => $_SESSION['user_id'],
        'user_username' => $_SESSION['user_username'] ?? null,
        'user_fullname' => $_SESSION['user_fullname'] ?? null,
        'user_level' => $_SESSION['user_level'] ?? null,
        'user_status' => $_SESSION['user_status'] ?? null,
    ];
}
?>
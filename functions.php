<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

function db(): mysqli {
	static $db;
	if (!$db) {
		$db = new mysqli('localhost', 'rts_user', 'sumet4631022', 'rts_db');
		if ($db->connect_errno) {
			die('DB connect error: ' . $db->connect_error);
		}
		$db->set_charset('utf8mb4');
	}
	return $db;
}

// Backwards compatibility: expose a global $mysqli variable many files expect
// and a USER_TABLE constant for the users table name.
if (!defined('USER_TABLE')) {
	define('USER_TABLE', 'tbl_user');
}
// Provide $mysqli global for older code that uses it
$GLOBALS['mysqli'] = db();

function e($v): string {
	return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

function csrf_check(string $token): bool {
	return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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

// add function thai_date_format, thai month_name
function thai_date_format(string $date_str): string {
    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];
    $date = strtotime($date_str);
    if ($date === false) {
        return '';
    }
    $day = date('j', $date);
    $month = (int)date('n', $date);
    $year = (int)date('Y', $date) + 543; //
    return sprintf('%d %s %d', $day, $months[$month], $year);
}

?>
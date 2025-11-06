<?php
declare(strict_types=1);

// Adjust to your environment
// Replaced DB_DSN with explicit host/db constants and normalized host to 127.0.0.1
const DB_HOST = '127.0.0.1';
const DB_NAME = 'rts_db';
const DB_USER = 'rts_user';
const DB_PASS = 'sumetchoorat4631022';
const DB_PORT = 3306;
const APP_DEBUG = true; // show detailed errors on the login page during debugging

function pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Fixed DSN to use MySQL with host/port/dbname/charset
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4;port=' . DB_PORT;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            throw $e;
        }
    }
    return $pdo;
}

// Make mysqli throw exceptions and use utf8mb4
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    try {
        // Uses the normalized host/port/user/pass defined above
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $mysqli->set_charset('utf8mb4');
    } catch (Throwable $e) {
        $GLOBALS['DB_CONNECT_ERROR'] = $e->getMessage();
        // Intentionally not echoing; page code will handle user-friendly messaging.
        error_log('DB connect failed: ' . $e->getMessage());
    }
}

// Helper functions for availability/status
if (!function_exists('db_connected')) {
    function db_connected(): bool {
        return isset($GLOBALS['mysqli']) && ($GLOBALS['mysqli'] instanceof mysqli);
    }
}
if (!function_exists('db_connect_error')) {
    function db_connect_error(): string {
        return isset($GLOBALS['DB_CONNECT_ERROR']) ? (string)$GLOBALS['DB_CONNECT_ERROR'] : '';
    }
}

// Sessions + CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_token() {
    return $_SESSION['csrf'] ?? '';
}
function verify_csrf($token) {
    return hash_equals($_SESSION['csrf'] ?? '', $token ?? '');
}

// Simple escape for HTML
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

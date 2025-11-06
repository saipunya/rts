<?php
declare(strict_types=1);

// Adjust to your environment
const DB_DSN  = 'localhost;dbname=rts_db;charset=utf8mb4';
const DB_USER = 'rts_user';
const DB_PASS = 'sumetchoorat4631022';
const DB_PORT = 3306;
const APP_DEBUG = true; // show detailed errors on the login page during debugging

function pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN . ';port=' . DB_PORT, DB_USER, DB_PASS, [
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

// Basic DB config (adjust as needed)
const DB_HOST = 'localhost';
const DB_NAME = 'rts_db'; // change if your DB name is different

// Make mysqli throw exceptions and use utf8mb4
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    try {
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

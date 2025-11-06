<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Unset all session values
$_SESSION = [];

// Delete session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? false);
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: index.php');
exit;

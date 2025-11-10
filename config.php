<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

// Deprecated: use functions.php (mysqli) instead of PDO.
require_once __DIR__ . '/functions.php';

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

<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

function pdo(): PDO {
	static $pdo;
	if (!$pdo) {
		$host = '127.0.0.1';
		$db   = 'rts';      // สร้างฐานข้อมูล rts ล่วงหน้า
		$user = 'root';     // XAMPP ค่าปริยาย
		$pass = '';
		$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
		$pdo = new PDO($dsn, $user, $pass, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	}
	return $pdo;
}

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

<?php
declare(strict_types=1);

// Reuse centralized connection settings (uses port from config)
require_once __DIR__ . '/config.php';

try {
	$pdo = pdo();
} catch (Throwable $e) {
	$GLOBALS['DB_ERROR'] = (defined('APP_DEBUG') && APP_DEBUG)
		? 'Database connection failed: ' . $e->getMessage()
		: 'Database connection failed.';
	if (function_exists('error_log')) { error_log('[db] ' . $e->getMessage()); }
	$pdo = null;
}

// PDO options are already set in pdo() from config.php

// Helper to retrieve last DB error message (if any)
if (!function_exists('db_error')) {
	function db_error(): ?string {
		return $GLOBALS['DB_ERROR'] ?? null;
	}
}

function e(?string $s): string {
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function require_admin(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
  if (empty($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
  }
  if (($_SESSION['user_level'] ?? '') !== 'admin') {
    header('Location: dashboard.php'); exit;
  }
}

function flash(?string $message = null): ?string {
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
  if ($message !== null) { $_SESSION['flash'] = $message; return null; }
  $m = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $m;
}

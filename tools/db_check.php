<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "DB diagnostics\n";
echo "---------------\n";

echo "- PDO: ";
try {
    $pdo = pdo();
    $pdo->query('SELECT 1');
    echo "OK\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

echo "- mysqli: ";
try {
    if (function_exists('db_connected') && db_connected()) {
        $GLOBALS['mysqli']->query('SELECT 1');
        echo "OK\n";
    } else {
        throw new RuntimeException(db_connect_error() ?: 'not connected');
    }
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

echo "\nNote: Ensure DB_HOST and DB_PORT match your MySQL instance.\n";

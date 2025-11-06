<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = pdo();
    echo "Connected OK\n";
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "Database: {$db}\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";

    $cols = $pdo->query("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_user'
    ")->fetchAll(PDO::FETCH_COLUMN);
    echo "tbl_user columns: " . implode(', ', $cols) . "\n";

    $sample = $pdo->query('SELECT user_id, user_username FROM tbl_user LIMIT 1')->fetch();
    echo "Sample row: " . json_encode($sample, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

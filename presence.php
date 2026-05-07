<?php
require_once __DIR__ . '/functions.php';

$db = db();
touch_online_presence($db);
$stats = fetch_online_presence_stats($db);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'ok' => true,
    'online_users' => (int)($stats['online_users'] ?? 0),
    'online_guests' => (int)($stats['online_guests'] ?? 0),
    'online_total' => (int)($stats['online_total'] ?? 0),
    'last_seen_at' => $stats['last_seen_at'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

<?php
require_once 'functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$db = db();

function ensure_wangyang_table(mysqli $db): void {
    $db->query("
        CREATE TABLE IF NOT EXISTS tbl_wangyang (
            wang_id INT(11) NOT NULL AUTO_INCREMENT,
            wang_date DATE NOT NULL,
            wang_mid INT(11) NOT NULL DEFAULT 0,
            wang_group VARCHAR(255) NOT NULL DEFAULT '',
            wang_name VARCHAR(255) NOT NULL DEFAULT '',
            wang_sack INT(11) NOT NULL DEFAULT 0,
            wang_weight DECIMAL(18,2) NOT NULL DEFAULT 0,
            wang_lan VARCHAR(255) NOT NULL DEFAULT '',
            wang_status VARCHAR(50) NOT NULL DEFAULT '',
            wang_saveby VARCHAR(255) NOT NULL DEFAULT '',
            wang_savedate DATETIME NOT NULL,
            PRIMARY KEY (wang_id),
            KEY idx_wang_date (wang_date),
            KEY idx_wang_lan (wang_lan),
            KEY idx_wang_savedate (wang_savedate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function respond(bool $ok, array $extra = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode(array_merge(['isOk' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, ['message' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$backendId = (string)($payload['backend_id'] ?? '');
$wangId = 0;
if (preg_match('/^db-(\d+)$/', $backendId, $m)) {
    $wangId = (int)$m[1];
} elseif (isset($payload['wang_id'])) {
    $wangId = (int)$payload['wang_id'];
}

if ($wangId <= 0) {
    respond(false, ['message' => 'Invalid id'], 400);
}

ensure_wangyang_table($db);

$stmt = $db->prepare('DELETE FROM tbl_wangyang WHERE wang_id = ? LIMIT 1');
if (!$stmt) {
    respond(false, ['message' => 'Prepare failed: ' . $db->error], 500);
}

$stmt->bind_param('i', $wangId);
if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    respond(false, ['message' => 'Delete failed: ' . $err], 500);
}

$affected = $stmt->affected_rows;
$stmt->close();

respond(true, ['deleted' => $affected > 0]);

<?php
require_once 'functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$db = db();
$cu = current_user();

function ensure_wang_daily_summary_table(mysqli $db): void {
    $db->query("
        CREATE TABLE IF NOT EXISTS tbl_wangyang_daily_summary (
            ws_date DATE NOT NULL PRIMARY KEY,
            ws_weight_per_bag DECIMAL(10,2) NOT NULL DEFAULT 0,
            ws_estimated_weight DECIMAL(18,2) NOT NULL DEFAULT 0,
            ws_saveby VARCHAR(255) NOT NULL DEFAULT '',
            ws_savedate DATETIME NOT NULL,
            INDEX idx_ws_savedate (ws_savedate)
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

$summaryDate = trim((string)($payload['summary_date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $summaryDate)) {
    respond(false, ['message' => 'Invalid date'], 400);
}

ensure_wang_daily_summary_table($db);

$saveBy = (string)($cu['user_fullname'] ?? $cu['user_username'] ?? '');
$savedAt = date('Y-m-d H:i:s');

$stmt = $db->prepare("
    INSERT INTO tbl_wangyang_daily_summary
        (ws_date, ws_weight_per_bag, ws_estimated_weight, ws_saveby, ws_savedate)
    VALUES
        (?, 0, 0, ?, ?)
    ON DUPLICATE KEY UPDATE
        ws_weight_per_bag = 0,
        ws_estimated_weight = 0,
        ws_saveby = VALUES(ws_saveby),
        ws_savedate = VALUES(ws_savedate)
");

if (!$stmt) {
    respond(false, ['message' => 'Prepare failed: ' . $db->error], 500);
}

$stmt->bind_param('sss', $summaryDate, $saveBy, $savedAt);
if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    respond(false, ['message' => 'Reset failed: ' . $err], 500);
}

$stmt->close();
respond(true, ['summary_date' => $summaryDate]);

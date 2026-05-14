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

$memberId = (int)($payload['member_id'] ?? 0);
$farmerName = trim((string)($payload['farmer_name'] ?? ''));
$groupName = trim((string)($payload['group_name'] ?? ''));
$lane = trim((string)($payload['lane'] ?? ''));
$bags = (int)($payload['bags'] ?? 0);
$weight = (float)($payload['weight'] ?? 0);
$date = trim((string)($payload['date'] ?? ''));

$backendId = trim((string)($payload['backend_id'] ?? ''));
$wangId = 0;
if (preg_match('/^db-(\d+)$/', $backendId, $m)) {
    $wangId = (int)$m[1];
} elseif (isset($payload['wang_id'])) {
    $wangId = (int)$payload['wang_id'];
}

if ($farmerName === '' || $groupName === '' || $lane === '' || $bags <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    respond(false, ['message' => 'Invalid payload'], 400);
}

ensure_wangyang_table($db);

$saveBy = (string)(current_user()['user_fullname'] ?? current_user()['user_username'] ?? '');
$savedAt = date('Y-m-d H:i:s');
$status = 'active';

if ($wangId > 0) {
    $stmt = $db->prepare("
        UPDATE tbl_wangyang
        SET wang_date = ?,
            wang_mid = ?,
            wang_group = ?,
            wang_name = ?,
            wang_sack = ?,
            wang_weight = ?,
            wang_lan = ?,
            wang_saveby = ?,
            wang_savedate = ?
        WHERE wang_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        respond(false, ['message' => 'Prepare failed: ' . $db->error], 500);
    }

    $stmt->bind_param(
        'sissidsssi',
        $date,
        $memberId,
        $groupName,
        $farmerName,
        $bags,
        $weight,
        $lane,
        $saveBy,
        $savedAt,
        $wangId
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        respond(false, ['message' => 'Update failed: ' . $err], 500);
    }

    $stmt->close();
    respond(true, ['id' => $wangId, 'updated' => true]);
}

$stmt = $db->prepare("
    INSERT INTO tbl_wangyang
        (wang_date, wang_mid, wang_group, wang_name, wang_sack, wang_weight, wang_lan, wang_status, wang_saveby, wang_savedate)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    respond(false, ['message' => 'Prepare failed: ' . $db->error], 500);
}

$stmt->bind_param(
    'sissidssss',
    $date,
    $memberId,
    $groupName,
    $farmerName,
    $bags,
    $weight,
    $lane,
    $status,
    $saveBy,
    $savedAt
);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    respond(false, ['message' => 'Save failed: ' . $err], 500);
}

$insertId = (int)$stmt->insert_id;
$stmt->close();

respond(true, ['id' => $insertId, 'created' => true]);

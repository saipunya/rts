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
            wang_number VARCHAR(255) NOT NULL DEFAULT '',
            wang_name VARCHAR(255) NOT NULL DEFAULT '',
            wang_class VARCHAR(255) NOT NULL DEFAULT '',
            wang_sack DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            wang_weight DECIMAL(18,2) NOT NULL DEFAULT 0,
            wang_lan VARCHAR(255) NOT NULL DEFAULT '',
            wang_note TEXT NULL,
            wang_status VARCHAR(50) NOT NULL DEFAULT '',
            wang_saveby VARCHAR(255) NOT NULL DEFAULT '',
            wang_savedate DATETIME NOT NULL,
            PRIMARY KEY (wang_id),
            KEY idx_wang_date (wang_date),
            KEY idx_wang_lan (wang_lan),
            KEY idx_wang_savedate (wang_savedate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $dbNameRes = $db->query('SELECT DATABASE() AS dbname');
    $dbNameRow = $dbNameRes ? $dbNameRes->fetch_assoc() : null;
    $dbName = (string)($dbNameRow['dbname'] ?? '');
    if ($dbNameRes) {
        $dbNameRes->free();
    }
    if ($dbName !== '') {
        $addColumns = [
            'wang_number' => "ALTER TABLE tbl_wangyang ADD COLUMN wang_number VARCHAR(255) NOT NULL DEFAULT '' AFTER wang_group",
            'wang_class' => "ALTER TABLE tbl_wangyang ADD COLUMN wang_class VARCHAR(255) NOT NULL DEFAULT '' AFTER wang_name",
            'wang_note' => "ALTER TABLE tbl_wangyang ADD COLUMN wang_note TEXT NULL AFTER wang_lan",
        ];
        $addStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tbl_wangyang' AND COLUMN_NAME = ?");
        if ($addStmt) {
            foreach ($addColumns as $column => $sql) {
                $addStmt->bind_param('ss', $dbName, $column);
                $addStmt->execute();
                $row = $addStmt->get_result()->fetch_assoc();
                if ((int)($row['cnt'] ?? 0) === 0) {
                    $db->query($sql);
                }
            }
            $addStmt->close();
        }

        $stmt = $db->prepare("
            SELECT DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tbl_wangyang' AND COLUMN_NAME = 'wang_sack'
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('s', $dbName);
            $stmt->execute();
            $col = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $isDecimal = $col
                && strtolower((string)$col['DATA_TYPE']) === 'decimal'
                && (int)$col['NUMERIC_PRECISION'] >= 18
                && (int)$col['NUMERIC_SCALE'] >= 2;
            if (!$isDecimal) {
                $db->query("ALTER TABLE tbl_wangyang MODIFY COLUMN wang_sack DECIMAL(18,2) NOT NULL DEFAULT 0.00");
            }
        }
    }
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
$memberNumber = trim((string)($payload['member_number'] ?? ''));
$memberClass = trim((string)($payload['member_class'] ?? ''));
$farmerName = trim((string)($payload['farmer_name'] ?? ''));
$groupName = trim((string)($payload['group_name'] ?? ''));
$lane = trim((string)($payload['lane'] ?? ''));
$bags = filter_var($payload['bags'] ?? null, FILTER_VALIDATE_FLOAT);
$weight = (float)($payload['weight'] ?? 0);
$note = trim((string)($payload['note'] ?? ''));
$date = trim((string)($payload['date'] ?? ''));

$backendId = trim((string)($payload['backend_id'] ?? ''));
$wangId = 0;
if (preg_match('/^db-(\d+)$/', $backendId, $m)) {
    $wangId = (int)$m[1];
} elseif (isset($payload['wang_id'])) {
    $wangId = (int)$payload['wang_id'];
}

if ($farmerName === '' || $groupName === '' || $lane === '' || $bags === false || $bags <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    respond(false, ['message' => 'Invalid payload'], 400);
}
$bags = round((float)$bags, 2);

ensure_wangyang_table($db);

if ($memberId > 0) {
    $memberStmt = $db->prepare('SELECT mem_group, mem_number, mem_fullname, mem_class FROM tbl_member WHERE mem_id = ? LIMIT 1');
    if ($memberStmt) {
        $memberStmt->bind_param('i', $memberId);
        $memberStmt->execute();
        $memberRow = $memberStmt->get_result()->fetch_assoc();
        $memberStmt->close();
        if (!$memberRow) {
            respond(false, ['message' => 'ไม่พบสมาชิกที่เลือก'], 400);
        }
        $groupName = trim((string)$memberRow['mem_group']);
        $memberNumber = trim((string)$memberRow['mem_number']);
        $farmerName = trim((string)$memberRow['mem_fullname']);
        $memberClass = trim((string)$memberRow['mem_class']);
    }
}

$saveBy = (string)(current_user()['user_fullname'] ?? current_user()['user_username'] ?? '');
$savedAt = date('Y-m-d H:i:s');
$status = 'active';

if ($wangId > 0) {
    $stmt = $db->prepare("
        UPDATE tbl_wangyang
        SET wang_date = ?,
            wang_mid = ?,
            wang_group = ?,
            wang_number = ?,
            wang_name = ?,
            wang_class = ?,
            wang_sack = ?,
            wang_weight = ?,
            wang_lan = ?,
            wang_note = ?,
            wang_saveby = ?,
            wang_savedate = ?
        WHERE wang_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        respond(false, ['message' => 'Prepare failed: ' . $db->error], 500);
    }

    $stmt->bind_param(
        'sissssddssssi',
        $date,
        $memberId,
        $groupName,
        $memberNumber,
        $farmerName,
        $memberClass,
        $bags,
        $weight,
        $lane,
        $note,
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
        (wang_date, wang_mid, wang_group, wang_number, wang_name, wang_class, wang_sack, wang_weight, wang_lan, wang_note, wang_status, wang_saveby, wang_savedate)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    respond(false, ['message' => 'Prepare failed: ' . $db->error], 500);
}

$stmt->bind_param(
    'sissssddsssss',
    $date,
    $memberId,
    $groupName,
    $memberNumber,
    $farmerName,
    $memberClass,
    $bags,
    $weight,
    $lane,
    $note,
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

<?php
require_once __DIR__ . '/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['isOk' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['isOk' => false, 'error' => 'Invalid JSON']);
    exit;
}

$wang_date = isset($data['date']) ? trim((string)$data['date']) : '';
$wang_mid = isset($data['member_id']) ? (int)$data['member_id'] : 0;
$wang_group = isset($data['group_name']) ? trim((string)$data['group_name']) : '';
$wang_name = isset($data['farmer_name']) ? trim((string)$data['farmer_name']) : '';
$wang_sack = isset($data['bags']) ? (int)$data['bags'] : 0;
$wang_weight = isset($data['weight']) ? (float)$data['weight'] : 0.0;
$wang_lan = isset($data['lane']) ? trim((string)$data['lane']) : '';
$wang_status = isset($data['status']) && $data['status'] !== '' ? trim((string)$data['status']) : 'check';

if ($wang_date === '' || $wang_mid <= 0 || $wang_name === '' ) {
    echo json_encode(['isOk' => false, 'error' => 'Missing required fields']);
    exit;
}

$db = db();
$cu = current_user();
$saveby = $cu['user_username'] ?? $cu['user_fullname'] ?? 'system';

// include wang_status with default value 'check'
$stmt = $db->prepare('INSERT INTO tbl_wangyang (wang_date, wang_mid, wang_group, wang_name, wang_sack, wang_weight, wang_lan, wang_status, wang_saveby, wang_savedate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
if (!$stmt) {
    echo json_encode(['isOk' => false, 'error' => 'Prepare failed: ' . $db->error]);
    exit;
}

$stmt->bind_param('sissidsss', $wang_date, $wang_mid, $wang_group, $wang_name, $wang_sack, $wang_weight, $wang_lan, $wang_status, $saveby);
$ok = $stmt->execute();
if (!$ok) {
    $err = $stmt->error;
}
$insertId = $db->insert_id;
$stmt->close();

if (!empty($err)) {
    echo json_encode(['isOk' => false, 'error' => $err]);
    exit;
}

echo json_encode(['isOk' => true, 'id' => (int)$insertId]);

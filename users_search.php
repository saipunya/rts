<?php
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([]);
    exit;
}
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}
$db = db();
$like = '%' . $q . '%';
$rows = [];
$stm = $db->prepare("SELECT user_id, user_username, user_fullname, user_level, user_status
                     FROM tbl_user
                     WHERE user_username LIKE ? OR user_fullname LIKE ?
                     ORDER BY user_fullname ASC
                     LIMIT 20");
$stm->bind_param('ss', $like, $like);
$stm->execute();
$res = $stm->get_result();
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'user_id' => (int)$r['user_id'],
        'user_username' => $r['user_username'],
        'user_fullname' => $r['user_fullname'],
        'user_level' => $r['user_level'],
        'user_status' => $r['user_status'],
    ];
}
$stm->close();
echo json_encode($rows, JSON_UNESCAPED_UNICODE);

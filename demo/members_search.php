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

$stm = $db->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_class
                     FROM tbl_member
                     WHERE mem_fullname LIKE ? OR mem_number LIKE ? OR mem_group LIKE ? OR mem_class LIKE ?
                     ORDER BY mem_fullname ASC
                     LIMIT 20");
$stm->bind_param('ssss', $like, $like, $like, $like);
$stm->execute();
$res = $stm->get_result();
while ($r = $res->fetch_assoc()) {
	$rows[] = [
		'mem_id' => (int)$r['mem_id'],
		'mem_group' => $r['mem_group'],
		'mem_number' => $r['mem_number'],
		'mem_fullname' => $r['mem_fullname'],
		'mem_class' => $r['mem_class'],
	];
}
$stm->close();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

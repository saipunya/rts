<?php
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

$db = db();
$priceDate = isset($_GET['pr_date']) ? trim((string)$_GET['pr_date']) : null;
$summary = fetch_rubber_round_live_summary($db, $priceDate);

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

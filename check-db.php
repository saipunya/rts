<?php
// Temporary read-only hosting check. Never prints credentials or application rows.
header('Content-Type: text/plain; charset=utf-8');
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'mysqli: ' . (extension_loaded('mysqli') ? 'OK' : 'MISSING') . "\n";
echo 'mbstring: ' . (extension_loaded('mbstring') ? 'OK' : 'MISSING') . "\n";
echo 'mysqlnd/get_result: ' . (function_exists('mysqli_stmt_get_result') ? 'OK' : 'MISSING') . "\n";
foreach (array('app_bootstrap.php','functions.php','cancellation_functions.php','collection_round_functions.php','weighing_functions.php','public_dashboard_data.php','header.php','footer.php','partials/public-status-records.php') as $file) {
    echo 'FILE ' . $file . ': ' . (is_file(__DIR__ . '/' . $file) ? 'OK' : 'MISSING') . "\n";
}
if (!is_file(__DIR__ . '/db_config.php')) { echo "CONFIG: MISSING\n"; exit; }
try { require __DIR__ . '/db_config.php'; }
catch (Throwable $error) { echo "CONFIG: PHP ERROR (check syntax)\n"; exit; }
foreach (array('db_host','db_user','db_pass','db_name') as $key) {
    if (!isset($$key) || !is_string($$key)) { echo 'CONFIG: invalid variable ' . $key . "\n"; exit; }
}
echo "CONFIG: OK\n";
if (!extension_loaded('mysqli')) exit;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $probe = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $probe->set_charset('utf8mb4');
    $probe->query('SELECT 1');
    echo "DB CONNECTION: OK\n";
} catch (Throwable $error) {
    $code = (int)$error->getCode();
    $reasons = array(1045=>'Login rejected: check database username/password/allowed host',1044=>'Database access denied: assign user permissions',1049=>'Database not found: check database name',2002=>'Database server/socket unreachable: check host setting',2003=>'Database server unreachable: check host/port');
    echo 'DB ERROR CODE: ' . $code . "\n";
    echo isset($reasons[$code]) ? $reasons[$code] . "\n" : "Check hosting PHP/MySQL error log\n";
    exit;
}
foreach (array('tbl_price','tbl_rubber','tbl_wangyang','tbl_wang_weighing','tbl_rubber_receipts') as $table) {
    try { $probe->query('SELECT 1 FROM `' . $table . '` LIMIT 0'); echo 'TABLE ' . $table . ": OK\n"; }
    catch (Throwable $error) { echo 'TABLE ' . $table . ': ERROR CODE ' . (int)$error->getCode() . "\n"; }
}
$probe->close();
echo "Check complete. Remove this temporary file after diagnosis.\n";
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $data = getenv('RTS_DEMO_DATA');
    if (!$data) throw new RuntimeException('Missing local data directory.');
    require $data . '/db_config.php';
    if ($db_host !== '127.0.0.1' || $db_name !== 'rts_demo') throw new RuntimeException('Unexpected demo database.');
    $db = new mysqli($db_host, $db_user, $db_pass, '', (int)($db_port ?? 33188));
    if (($argv[1] ?? '') === 'check') {
        $db->select_db($db_name);
        foreach (['tbl_user','tbl_member','tbl_rubber','tbl_price','tbl_wangyang','tbl_wang_weighing'] as $table) {
            $db->query("SELECT 1 FROM `$table` LIMIT 1");
        }
    }
    echo "OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n"); exit(1);
}

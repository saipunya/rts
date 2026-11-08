<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    foreach (['mysqli', 'mbstring', 'dom', 'gd', 'openssl'] as $extension) {
        if (!extension_loaded($extension)) throw new RuntimeException("Enable PHP extension: $extension");
    }
    $demoData = getenv('RTS_DEMO_DATA');
    $config = $demoData ? $demoData . '/db_config.php' : dirname(__DIR__) . '/db_config.php';
    if (!is_file($config)) throw new RuntimeException('Copy db_config.sample.php to db_config.php and configure the local demo database first.');
    require $config;
    if (!in_array($db_host, ['localhost', '127.0.0.1'], true) || $db_name !== 'rts_demo') {
        throw new RuntimeException('Installer accepts only localhost/127.0.0.1 and database rts_demo. Never use production configuration.');
    }
    echo "New demo administrator username [demo_admin]: ";
    $username = trim((string)fgets(STDIN)) ?: 'demo_admin';
    echo "New password (at least 10 characters; visible while typing): ";
    $password = rtrim((string)fgets(STDIN), "\r\n");
    if (strlen($username) > 100 || strlen($password) < 10) throw new RuntimeException('Username must be <=100 characters and password >=10 characters.');
    $server = new mysqli($db_host, $db_user, $db_pass, '', (int)($db_port ?? 3306));
    $server->set_charset('utf8mb4');
    $server->query('CREATE DATABASE IF NOT EXISTS rts_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $server->select_db('rts_demo');
    if ($server->query('SHOW TABLES')->num_rows !== 0) throw new RuntimeException('rts_demo is not empty. Installation stopped without changing existing tables.');
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $server->multi_query($sql);
    do {
        if ($result = $server->store_result()) $result->free();
        if (!$server->more_results()) break;
    } while ($server->next_result());
    require_once dirname(__DIR__) . '/functions.php';
    $database = db();
    ensure_weighing_schema($database);
    ensure_member_portal_usage_table($database);
    ensure_online_presence_table($database);
    $database->begin_transaction();
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $database->prepare("INSERT INTO tbl_user (user_username,user_password,user_fullname,user_level,user_status) VALUES (?,?,'ผู้ดูแลระบบทดลอง','admin','active')");
        $stmt->bind_param('ss', $username, $hash); $stmt->execute(); $stmt->close();
        $stmt = $database->prepare("INSERT INTO tbl_member (mem_group,mem_number,mem_fullname,mem_class,mem_saveby,mem_savedate,mem_personcode,mem_birthtext) VALUES ('1',?,?,'member','demo',CURDATE(),'1234','')");
        for ($i = 1; $i <= 5; $i++) {
            $number = sprintf('D%03d', $i); $name = 'สมาชิกสมมติ ' . $i;
            $stmt->bind_param('ss', $number, $name); $stmt->execute();
        }
        $stmt->close(); $database->commit();
    } catch (Throwable $error) { $database->rollback(); throw $error; }
    rts_license_initialize();
    echo "Installed: rts_demo with 5 fictional members. Start the app and log in with your new account.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Installation stopped: ' . $error->getMessage() . "\nIf a fresh installation failed after schema creation, use a NEW empty rts_demo database before retrying. Do not delete an existing demo database containing work you need.\n");
    exit(1);
}

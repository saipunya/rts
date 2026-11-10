<?php
require __DIR__.'/../db_config.php';
$db=new mysqli($db_host,$db_user,$db_pass);
$name='rts_acl_test_'.bin2hex(random_bytes(5));
$db->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");$db->select_db($name);$db->set_charset('utf8mb4');
$GLOBALS['acl_test_db']=$db;
function db(): mysqli {return $GLOBALS['acl_test_db'];}
function is_admin(): bool {return ($_SESSION['user_level']??'')==='admin';}
function e($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function nav_active($pages): string {return '';}
require __DIR__.'/../app_bootstrap.php';
require __DIR__.'/../permission_functions.php';
function check_acl(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);echo "PASS: $message\n";}
try {
    $db->query('CREATE TABLE tbl_user (user_id INT PRIMARY KEY,user_username VARCHAR(255),user_fullname VARCHAR(255),user_level VARCHAR(20),user_status VARCHAR(20)) ENGINE=InnoDB');
    $db->query("INSERT INTO tbl_user VALUES (1,'admin','Administrator','admin','active'),(2,'weigher','Weighing operator','user','active'),(3,'legacy','Existing staff','user','active')");
    ensure_user_permission_schema($db);
    check_acl(array_keys(rts_permission_catalog())===['placement','weighing','approval','deductions','receipts'],'Only five operational menus can be assigned');
    foreach (['reports','analysis','members','dashboard'] as $key) {
        try { rts_normalize_permissions([$key]); throw new RuntimeException('Non-operational module accepted'); } catch (DomainException $error) {}
    }
    check_acl(rts_permissions_for_user($db,3)===array_keys(rts_permission_catalog()),'Unconfigured existing accounts retain ordinary menus');
    rts_save_user_permissions($db,2,['weighing','weighing'],1);
    check_acl(rts_permissions_for_user($db,2)===['weighing'],'Selected permissions are canonical and deduplicated');
    $_SESSION['user_id']=2;$_SESSION['user_level']='admin';
    check_acl(rts_can('weighing') && !rts_can('approval') && !is_admin(),'Database role overrides a stale administrator session');
    check_acl(rts_can_page('weigh_wang.php') && !rts_can_page('save_wang.php') && !rts_can_page('prices.php') && !rts_can_page('users.php'),'Direct read and write routes respect permissions and admin-only boundaries');
    check_acl(rts_first_allowed_page()==='weigh_wang.php' && rts_login_destination('dashboard.php')===app_url('weigh_wang.php'),'Login lands on an allowed menu');
    ob_start();require __DIR__.'/../partials/operations-navigation.php';$menu=ob_get_clean();
    check_acl(strpos($menu,'href="weigh_wang.php"')!==false && strpos($menu,'href="approve_weighing.php"')===false && strpos($menu,'href="users.php"')===false,'Navigation renders only permitted work menus');
    rts_save_user_permissions($db,2,[],1);
    check_acl(!rts_can_page('weigh_wang.php') && rts_first_allowed_page()==='index.php','An explicit empty selection removes all internal work menus');
    rts_save_user_permissions($db,2,['placement'],1);
    check_acl(rts_can_page('members_search.php') && !rts_can_page('member_save.php'),'Placement can search members without receiving member management rights');
    rts_save_user_permissions($db,2,['approval'],1);
    check_acl(rts_can_page('export_rubber_pdf.php') && !rts_can_page('printed_receipts.php'),'Approval can finish its own print workflow without the receipt-list menu');
    try {rts_normalize_permissions(['@admin']);throw new RuntimeException('Unknown module accepted');}catch(DomainException $error){echo "PASS: Admin-only permissions cannot be injected\n";}
    $db->query("UPDATE tbl_user_menu_permissions SET permissions='invalid json' WHERE user_id=2");unset($GLOBALS['rts_current_acl']);
    check_acl(!rts_can_page('approve_weighing.php'),'Invalid stored permissions fail closed');
    $_SESSION['user_id']=1;unset($GLOBALS['rts_current_acl']);
    check_acl(rts_can_page('users.php') && rts_can_page('prices.php') && rts_can_page('weigh_wang.php'),'Administrators retain all work and management menus');
    $_SESSION['user_id']=2;$_SESSION['user_level']='user';unset($GLOBALS['rts_current_acl']);
    $db->query("UPDATE tbl_user SET user_status='inactive' WHERE user_id=2");
    check_acl(!rts_can_page('weigh_wang.php') && empty($_SESSION['user_id']),'Suspended accounts cannot keep existing sessions');
    check_acl(rts_can_page('index.php') && rts_can_page('public_collection_list.php') && rts_can_page('public_collection_status.php'),'Public QR tracking remains accessible without staff permissions');
    echo "All user permission checks passed.\n";
} finally {$db->query("DROP DATABASE `$name`");$db->close();}

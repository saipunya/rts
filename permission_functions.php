<?php
declare(strict_types=1);

function rts_permission_catalog(): array {
    return [
        'placement'=>['label'=>'วางยาง','description'=>'บันทึกวางยางและดูสรุปการวางยาง','page'=>'wang_main.php','icon'=>'package-check'],
        'weighing'=>['label'=>'ชั่งยาง','description'=>'เรียกรายการและบันทึกน้ำหนัก','page'=>'weigh_wang.php','icon'=>'scale'],
        'approval'=>['label'=>'อนุมัติหลังชั่ง','description'=>'อนุมัติไม่มียอดหัก ส่งตรวจสอบ และพิมพ์ใบแจ้งรายการ','page'=>'approve_weighing.php','icon'=>'clipboard-check'],
        'deductions'=>['label'=>'บันทึกยอดหัก','description'=>'บันทึกยอดหักและเปิดใบเสร็จหลังบันทึก','page'=>'rubbers.php','icon'=>'clipboard-list'],
        'receipts'=>['label'=>'พิมพ์ใบเสร็จ','description'=>'ดูรายการใบเสร็จและพิมพ์ซ้ำ','page'=>'printed_receipts.php','icon'=>'receipt'],
    ];
}

function rts_admin_navigation_catalog(): array {
    return [
        'reports'=>['label'=>'รายงานและส่งออก','description'=>'รายงานรับซื้อและส่งออกข้อมูลตามสิทธิ์เดิม','page'=>'report_rubber.php','icon'=>'file-bar-chart'],
        'analysis'=>['label'=>'วิเคราะห์การรวบรวม','description'=>'ดูและเปรียบเทียบข้อมูลการรวบรวม','page'=>'collection_analysis.php','icon'=>'bar-chart-3'],
        'members'=>['label'=>'จัดการสมาชิก','description'=>'รายการสมาชิก เพิ่ม แก้ไข และลบตามเงื่อนไขเดิม','page'=>'members.php','icon'=>'users'],
        'dashboard'=>['label'=>'ศูนย์งานสหกรณ์','description'=>'ภาพรวมและข้อมูลสรุปสำหรับเจ้าหน้าที่','page'=>'dashboard.php','icon'=>'gauge'],
    ];
}

// Any listed module may access a shared read/print endpoint. Administrator-only
// actions keep their existing role checks as well as this central route guard.
function rts_route_permissions(string $page): array {
    $routes = [
        'index.php'=>[], 'public_collection_list.php'=>[], 'public_collection_status.php'=>[],
        'rubber_round_live_summary.php'=>[], 'allmember.php'=>[], 'login.php'=>[], 'logout.php'=>[],
        'register.php'=>[], 'presence.php'=>[],
        'wang_main.php'=>['placement'], 'create_rubber.php'=>['placement'],
        'save_wang.php'=>['placement'], 'delete_wang.php'=>['placement'],
        'wang_summary.php'=>['placement'], 'reset_wang_summary.php'=>['placement'],
        'manage_wang.php'=>['placement'],
        'weigh_wang.php'=>['weighing'], 'approve_weighing.php'=>['approval'],
        'print_deduction_check.php'=>['approval'], 'rubbers.php'=>['deductions'],
        'printed_receipts.php'=>['receipts'], 'export_printed_receipts.php'=>['receipts'],
        'export_rubber_pdf.php'=>['receipts','approval','deductions'],
        'report_rubber.php'=>['@admin'], 'export_rubbers_excel.php'=>['@admin'],
        'export_rubbers_csv.php'=>['@admin'], 'export_rubbers_pdf.php'=>['@admin'],
        'export_rubbers_export.php'=>['@admin'], 'export_total_sale.php'=>['@admin'],
        'export_round_matrix.php'=>['@admin'], 'export_round_matrix_excel.php'=>['@admin'],
        'collection_analysis.php'=>['@admin'],
        'members.php'=>['@admin'], 'member_form.php'=>['@admin'],
        'member_save.php'=>['@admin'], 'member_delete.php'=>['@admin'],
        'add_person_code.php'=>['@admin'], 'add_birth_date.php'=>['@admin'],
        'member_usage_summary.php'=>['@admin'], 'members_search.php'=>['placement'],
        'dashboard.php'=>['@admin'],
        'collection_history.php'=>['placement','weighing','approval','deductions','receipts'],
        'prices.php'=>['@admin'], 'price_form.php'=>['@admin'], 'price_save.php'=>['@admin'],
        'price_delete.php'=>['@admin'], 'collection_rounds.php'=>['@admin'],
        'collection_audit.php'=>['@admin'], 'cancel_collection.php'=>['@admin'],
        'admin_rubbers.php'=>['@admin'], 'export_wang.php'=>['@admin'],
        'demo_reset.php'=>['@admin'], 'demo_test.php'=>['@admin'],
        'admin_weight_edit.php'=>['@admin'],
        'users.php'=>['@admin'], 'users_search.php'=>['@admin'],
        'user_form.php'=>['@admin'], 'user_save.php'=>['@admin'], 'user_delete.php'=>['@admin'],
    ];
    // Unknown PHP entry points require an administrator until explicitly classified.
    return $routes[$page] ?? ['@admin'];
}

function ensure_user_permission_schema(mysqli $db): void {
    static $ready = [];
    $key = $db->thread_id;
    if (isset($ready[$key])) return;
    $db->query("CREATE TABLE IF NOT EXISTS tbl_user_menu_permissions (
        user_id INT NOT NULL PRIMARY KEY,
        permissions TEXT NOT NULL,
        updated_by INT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready[$key] = true;
}

function rts_normalize_permissions($selected): array {
    if (!is_array($selected)) throw new DomainException('รูปแบบสิทธิ์เมนูไม่ถูกต้อง');
    $catalog = rts_permission_catalog();
    foreach ($selected as $key) {
        if (!is_string($key) || !isset($catalog[$key])) throw new DomainException('พบสิทธิ์เมนูที่ไม่ถูกต้อง');
    }
    return array_values(array_intersect(array_keys($catalog),$selected));
}

function rts_permissions_for_user(mysqli $db,int $id): array {
    ensure_user_permission_schema($db);
    $stmt=$db->prepare('SELECT permissions FROM tbl_user_menu_permissions WHERE user_id=?');
    $stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
    // Existing accounts retain their ordinary menus until an administrator configures them.
    if (!$row) return array_keys(rts_permission_catalog());
    $decoded=json_decode($row['permissions'],true);
    if (!is_array($decoded)) return [];
    return array_values(array_intersect(array_keys(rts_permission_catalog()),array_filter($decoded,'is_string')));
}

function rts_save_user_permissions(mysqli $db,int $id,array $selected,int $actor): void {
    $selected=rts_normalize_permissions($selected);
    $json=json_encode($selected,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $now=(new DateTimeImmutable('now',new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $stmt=$db->prepare('INSERT INTO tbl_user_menu_permissions (user_id,permissions,updated_by,updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE permissions=VALUES(permissions),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)');
    $stmt->bind_param('isis',$id,$json,$actor,$now);$stmt->execute();$stmt->close();
    unset($GLOBALS['rts_current_acl']);
}

function rts_current_acl(): array {
    $id=(int)($_SESSION['user_id']??0);
    if (!$id) return ['id'=>0,'admin'=>false,'permissions'=>[]];
    if (isset($GLOBALS['rts_current_acl']) && $GLOBALS['rts_current_acl']['id']===$id) return $GLOBALS['rts_current_acl'];
    $db=db();
    $stmt=$db->prepare('SELECT user_id,user_username,user_fullname,user_level,user_status FROM tbl_user WHERE user_id=?');
    $stmt->bind_param('i',$id);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$user || !in_array(strtolower((string)$user['user_status']),['active','1','enabled','true'],true)) {
        foreach (['user_id','user_username','user_fullname','user_level','user_status'] as $key) unset($_SESSION[$key]);
        unset($GLOBALS['rts_current_acl']);
        return ['id'=>0,'admin'=>false,'permissions'=>[]];
    }
    foreach (['user_username','user_fullname','user_level','user_status'] as $key) $_SESSION[$key]=$user[$key];
    $context=['id'=>$id,'admin'=>$user['user_level']==='admin','permissions'=>rts_permissions_for_user($db,$id)];
    $GLOBALS['rts_current_acl']=$context;
    return $context;
}

function rts_can(string $permission): bool {
    $context=rts_current_acl();
    return $context['id']>0 && ($context['admin'] || in_array($permission,$context['permissions'],true));
}

function rts_can_page(string $page): bool {
    $required=rts_route_permissions($page);
    if (!$required) return true;
    $context=rts_current_acl();
    if (!$context['id']) return false;
    if ($context['admin']) return true;
    if (in_array('@admin',$required,true)) return false;
    return (bool)array_intersect($required,$context['permissions']);
}

function rts_first_allowed_page(): string {
    $context=rts_current_acl();
    if ($context['admin']) return 'dashboard.php';
    foreach (rts_permission_catalog() as $key=>$module) if (in_array($key,$context['permissions'],true)) return $module['page'];
    return 'index.php';
}

function rts_login_destination(string $destination): string {
    $safe=app_login_destination($destination);
    $page=basename((string)parse_url($safe,PHP_URL_PATH));
    return rts_can_page($page) ? $safe : app_url(rts_first_allowed_page());
}

function rts_enforce_request_permissions(): void {
    if (PHP_SAPI==='cli') return;
    // Refresh role/status even on public pages so stale sessions cannot retain admin access.
    rts_current_acl();
    $page=basename((string)($_SERVER['SCRIPT_FILENAME']??$_SERVER['SCRIPT_NAME']??''));
    if (rts_can_page($page)) return;
    $isJson = strpos((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')!==false
        || in_array($page,['save_wang.php','delete_wang.php','reset_wang_summary.php','members_search.php','users_search.php'],true);
    $loggedIn=!empty($_SESSION['user_id']);
    if (!$loggedIn && !$isJson && ($_SERVER['REQUEST_METHOD']??'GET')==='GET') {
        $query=http_build_query($_GET);
        header('Location: '.app_url('login.php?'.http_build_query(['redirect'=>$page.($query!==''?'?'.$query:'')])));
        exit;
    }
    http_response_code($loggedIn?403:401);
    header('Cache-Control: no-store');
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['isOk'=>false,'message'=>'บัญชีนี้ไม่ได้รับสิทธิ์เข้าถึงเมนูนี้'],JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $back=e(app_url($loggedIn?rts_first_allowed_page():'login.php'));
        echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ไม่มีสิทธิ์เข้าถึง</title><body style="font-family:Tahoma,sans-serif;padding:24px;color:#23332e"><h1>ไม่มีสิทธิ์เข้าถึงเมนูนี้</h1><p>กรุณาติดต่อผู้ดูแลระบบเพื่อกำหนดสิทธิ์ให้บัญชีของคุณ</p><a href="'.$back.'">กลับหน้าที่ใช้งานได้</a></body></html>';
    }
    exit;
}

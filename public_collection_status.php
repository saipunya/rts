<?php
declare(strict_types=1);
require_once __DIR__.'/functions.php';
require_once __DIR__.'/public_dashboard_data.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405); header('Allow: GET');
    echo json_encode(['message'=>'รองรับการอ่านข้อมูลเท่านั้น'],JSON_UNESCAPED_UNICODE); exit;
}
try {
    if (isset($_GET['member']) && !is_string($_GET['member'])) throw new InvalidArgumentException('คำค้นไม่ถูกต้อง');
    $member = trim((string)($_GET['member'] ?? ''));
    $stage = is_string($_GET['stage'] ?? null) ? $_GET['stage'] : 'all';
    $page = filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT);
    if ($page === false || $page < 1) throw new InvalidArgumentException('เลขหน้าไม่ถูกต้อง');
    $dashboard = rts_public_dashboard(db(),$member,$stage,$page);
    if (($_GET['view'] ?? '') === 'list') {
        $listUrl = static function(string $selected,int $number=1) use ($member): string {
            return 'public_collection_list.php?'.http_build_query(['stage'=>$selected,'member'=>$member,'page'=>$number]);
        };
        $render = static function(string $template) use ($dashboard,$listUrl): string {
            ob_start();
            try { require __DIR__.'/partials/'.$template; return ob_get_clean(); }
            catch (Throwable $error) { ob_end_clean(); throw $error; }
        };
        $dashboard['records_html'] = $render('public-status-records.php');
        $dashboard['pagination_html'] = $render('public-list-pagination.php');
    }
    echo json_encode($dashboard,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code($error instanceof InvalidArgumentException ? 400 : 503);
    if (!($error instanceof InvalidArgumentException)) error_log((string)$error);
    echo json_encode(['message'=>$error instanceof InvalidArgumentException ? $error->getMessage() : 'ยังอัปเดตข้อมูลไม่ได้ กรุณาลองอีกครั้ง'],JSON_UNESCAPED_UNICODE);
}

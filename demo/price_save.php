<?php
require_once __DIR__ . '/functions.php';
require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { header('Location: prices.php'); exit; }
$db = db(); ensure_weighing_schema($db);
$action = (string)($_POST['action'] ?? 'create');
$id = (int)($_POST['pr_id'] ?? 0);

$date = trim((string)($_POST['pr_date'] ?? ''));
$number = trim((string)($_POST['pr_number'] ?? ''));
$rawPrice = str_replace(',', '', trim((string)($_POST['pr_price'] ?? '')));
$error = '';
try {
    if (!csrf_check((string)($_POST['csrf_token'] ?? ''))) throw new DomainException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
    if (!in_array($action, ['create','edit'], true) || ($action === 'edit' && $id <= 0)) throw new DomainException('รายการราคาไม่ถูกต้อง');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new DomainException('กรุณากรอกวันที่ราคายาง / วันชั่งให้ถูกต้อง');
    if ($number === '') throw new DomainException('กรุณากรอกเลขที่ประกาศราคา');
    if (!is_numeric($rawPrice) || !is_finite((float)$rawPrice) || (float)$rawPrice <= 0) throw new DomainException('ราคายางต้องมากกว่า 0');
    $price = number_format((float)$rawPrice, 2, '.', '');
    if ((float)$price <= 0) throw new DomainException('ราคายางต้องไม่น้อยกว่า 0.01');
    $year = (int)$parsed->format('Y') + 543;
    $user = current_user(); $actor = (string)(($user['user_fullname'] ?? '') ?: ($user['user_username'] ?? ''));
    $savedDate = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
    $db->begin_transaction();
    try {
        $round = price_date_collection_round($db, $date);
        $roundId = (int)$round['round_id'];
        $before = null;
        if ($action === 'edit') {
            $stmt = $db->prepare('SELECT * FROM tbl_price WHERE pr_id=? FOR UPDATE');
            $stmt->bind_param('i', $id); $stmt->execute(); $before = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$before) throw new DomainException('ไม่พบรายการราคา');
            $stmt = $db->prepare('UPDATE tbl_price SET pr_year=?,pr_date=?,pr_number=?,pr_price=?,pr_saveby=?,pr_savedate=?,collection_round_id=? WHERE pr_id=?');
            $stmt->bind_param('isssssii', $year, $date, $number, $price, $actor, $savedDate, $roundId, $id);
        } else {
            $stmt = $db->prepare('INSERT INTO tbl_price (pr_year,pr_date,pr_number,pr_price,pr_saveby,pr_savedate,collection_round_id) VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param('isssssi', $year, $date, $number, $price, $actor, $savedDate, $roundId);
        }
        $stmt->execute(); if ($action === 'create') $id = (int)$stmt->insert_id; $stmt->close();
        record_collection_audit($db, $action === 'create' ? 'price_create' : 'price_edit', 0, 0, $actor, ['pr_id'=>$id,'round_id'=>$roundId,'before'=>$before,'after'=>['pr_date'=>$date,'pr_price'=>$price,'pr_number'=>$number]]);
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
    header('Location: prices.php?msg=' . urlencode('บันทึกราคายางเรียบร้อย')); exit;
} catch (DomainException $e) { $error = $e->getMessage(); }
catch (Throwable $e) { error_log((string)$e); $error = 'บันทึกราคาไม่สำเร็จ กรุณาตรวจสอบข้อมูลแล้วลองใหม่'; }
header('Location: price_form.php?' . http_build_query(['action'=>$action === 'edit' ? 'edit' : 'create','id'=>$id,'msg'=>$error]));
exit;

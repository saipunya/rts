<?php
require_once __DIR__ . '/functions.php';
require_login();
$db = db();
ensure_weighing_schema($db);
$round = selected_collection_round($db);
$roundId = (int)($round['round_id'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$id = max(0, (int)($_GET['id'] ?? 0));
$errors = [];
$posted = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id = max(0, (int)($_POST['ru_id'] ?? 0));
    $posted = $_POST;
    try {
        if (!csrf_check((string)($_POST['csrf_token'] ?? ''))) throw new DomainException('โทเค็นไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
        $user = current_user();
        $actor = (string)(($user['user_fullname'] ?? '') ?: ($user['user_username'] ?? 'เจ้าหน้าที่'));
        save_round_rubber_deductions($db, $id, $_POST, $actor, $roundId);
        $_SESSION['deductions_success'] = 'บันทึกยอดหักแล้ว เปิดใบเสร็จสำหรับพิมพ์เรียบร้อย';
        $receiptUrl = 'export_rubber_pdf.php?' . http_build_query(['ru_id'=>$id]);
        $queueUrl = 'rubbers.php?' . http_build_query(['round'=>$roundId]);
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html><html lang="th"><meta charset="utf-8"><title>เปิดใบเสร็จ</title>
        <p>บันทึกยอดหักเรียบร้อย <a href="<?php echo e($receiptUrl); ?>">เปิดใบเสร็จ</a> · <a href="<?php echo e($queueUrl); ?>">กลับบันทึกยอดหัก</a></p>
        <script>
        if (window.opener && !window.opener.closed) {
          try { window.opener.location.replace(<?php echo json_encode($queueUrl); ?>); } catch (error) {}
        }
        window.location.replace(<?php echo json_encode($receiptUrl); ?>);
        </script></html>
        <?php
        exit;
    } catch (InvalidArgumentException | DomainException $error) {
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        error_log((string)$error);
        $errors[] = 'บันทึกไม่สำเร็จ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
    }
}
// The round queue is fixed on the server; old date, lane or status parameters cannot broaden it.
$from = ' FROM (SELECT * FROM tbl_rubber WHERE LOWER(TRIM(COALESCE(record_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564)) r JOIN tbl_wang_weighing x ON x.ru_id=r.ru_id JOIN (SELECT * FROM tbl_wangyang WHERE LOWER(TRIM(COALESCE(record_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564) AND LOWER(TRIM(COALESCE(wang_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564)) w ON w.wang_id=x.wang_id';
$where = " WHERE w.collection_round_id=? AND x.approval_route='review' AND x.deductions_saved_at IS NULL
    AND LOWER(TRIM(COALESCE(w.wang_status, ''))) NOT IN ('cancelled','canceled','deleted')";
$params = [$roundId]; $types = 'i';
if ($search !== '') {
    $where .= ' AND (r.ru_fullname LIKE ? OR r.ru_number LIKE ? OR r.ru_group LIKE ? OR w.wang_note LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like); $types .= 'ssss';
}
$stmt = $db->prepare('SELECT COUNT(*)' . $from . $where);
$stmt->bind_param($types, ...$params); $stmt->execute(); $totalCount = (int)$stmt->get_result()->fetch_row()[0]; $stmt->close();
$totalPages = max(1, (int)ceil($totalCount / $perPage)); $page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$stmt = $db->prepare('SELECT r.ru_id, r.ru_fullname, r.ru_number, r.ru_group, x.net_weight, w.wang_note' . $from . $where . ' ORDER BY x.approved_at, r.ru_id LIMIT ? OFFSET ?');
$listParams = array_merge($params, [$perPage, $offset]); $listTypes = $types . 'ii';
$stmt->bind_param($listTypes, ...$listParams); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$selected = null;
if ($id > 0) {
    $stmt = $db->prepare("SELECT r.*, x.net_weight, w.wang_note" . $from . " WHERE r.ru_id=? AND w.collection_round_id=? AND x.approval_route='review' AND x.deductions_saved_at IS NULL AND LOWER(TRIM(COALESCE(w.wang_status, ''))) NOT IN ('cancelled','canceled','deleted')");
    $stmt->bind_param('ii', $id, $roundId); $stmt->execute(); $selected = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$selected) $errors[] = 'ไม่พบรายการในคิวบันทึกยอดหักของรอบนี้';
}
$success = (string)($_SESSION['deductions_success'] ?? ''); unset($_SESSION['deductions_success']);
$fields = ['ru_hoon'=>'หุ้น', 'ru_loan'=>'เงินกู้', 'ru_shortdebt'=>'หนี้สั้น', 'ru_deposit'=>'เงินฝาก', 'ru_tradeloan'=>'กู้ซื้อขาย', 'ru_insurance'=>'ประกันภัย'];
require __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="assets/css/deductions.css?v=<?php echo filemtime(__DIR__ . '/assets/css/deductions.css'); ?>">
<section class="deductions-page">
  <?php if (!$round): $roundBarReadOnly = true; require __DIR__ . '/collection_round_bar.php'; endif; ?>
  <header class="deductions-heading"><div><h1>บันทึกยอดหัก</h1><p>รายการที่ส่งตรวจสอบและรอบันทึกยอดหักในรอบนี้</p></div><span class="deductions-date"><i data-lucide="calendar-days" aria-hidden="true"></i><?php echo e(collection_round_label($round)); ?></span></header>
  <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo e($success); ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?php echo e(implode(' · ', array_unique($errors))); ?></div><?php endif; ?>
  <?php if ($selected): ?>
  <form method="post" target="_blank" rel="opener" class="deductions-form" id="rubberForm" autocomplete="off">
    <input type="hidden" name="round" value="<?php echo $roundId; ?>"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="ru_id" value="<?php echo (int)$selected['ru_id']; ?>">
    <div class="deductions-source"><div><h2><?php echo e(collection_display_name($selected['ru_fullname'], $selected['wang_note'])); ?></h2><p>รายการ #<?php echo (int)$selected['ru_id']; ?> · เลขที่ <?php echo e($selected['ru_number']); ?> · กลุ่ม <?php echo e($selected['ru_group']); ?></p></div><strong><?php echo number_format((float)$selected['net_weight'],2); ?> <small>กก.</small></strong></div>
    <div class="deduction-fields">
      <?php foreach ($fields as $key=>$label): ?><div><label for="<?php echo e($key); ?>"><?php echo e($label); ?> <small>(บาท)</small></label><input class="form-control" id="<?php echo e($key); ?>" name="<?php echo e($key); ?>" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00" value="<?php echo e($posted[$key] ?? ''); ?>"></div><?php endforeach; ?>
    </div>
    <p class="deductions-help">กรอกยอดหักตามผลตรวจสอบ หากไม่มียอดหักให้กรอก 0 อย่างน้อยหนึ่งช่อง ช่องที่เว้นว่างจะเป็น 0</p>
    <div class="deductions-totals"><span>ยอดหักรวม <strong id="deductionTotal">0.00</strong> บาท</span><span>รับสุทธิ <strong id="deductionNet"><?php echo number_format((float)$selected['ru_value'],2); ?></strong> บาท</span></div>
    <div class="deductions-actions"><button class="btn btn-success" type="submit"><i data-lucide="printer" aria-hidden="true"></i>บันทึกยอดหักและพิมพ์ใบเสร็จ</button><a class="btn btn-outline-secondary" href="rubbers.php?<?php echo e(http_build_query(['round'=>$roundId,'search'=>$search])); ?>">ยกเลิก</a></div>
  </form>
  <?php endif; ?>
  <form method="get" class="deductions-search"><input type="hidden" name="round" value="<?php echo $roundId; ?>"><label class="visually-hidden" for="dailySearch">ค้นหารายการของรอบนี้</label><input class="form-control" type="search" id="dailySearch" name="search" value="<?php echo e($search); ?>" placeholder="ค้นหาชื่อ เลขสมาชิก กลุ่ม หรือหมายเหตุของรอบนี้"><button class="btn btn-success" type="submit"><i data-lucide="search" aria-hidden="true"></i>ค้นหา</button><?php if ($search !== ''): ?><a class="btn btn-outline-secondary" href="rubbers.php">ล้าง</a><?php endif; ?></form>
  <p class="deductions-count">รอบันทึกยอดหัก <?php echo number_format($totalCount); ?> รายการ</p>
  <div class="table-responsive deductions-table"><table class="table table-hover align-middle mb-0"><thead><tr><th>รายการ</th><th>ผู้ส่งยาง</th><th class="text-end">น้ำหนัก (กก.)</th><th>ดำเนินการ</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr<?php echo (int)$row['ru_id'] === $id ? ' class="table-active"' : ''; ?>><td>#<?php echo (int)$row['ru_id']; ?></td><td><strong><?php echo e(collection_display_name($row['ru_fullname'], $row['wang_note'])); ?></strong><small>เลขที่ <?php echo e($row['ru_number']); ?> · กลุ่ม <?php echo e($row['ru_group']); ?></small></td><td class="text-end"><?php echo number_format((float)$row['net_weight'],2); ?></td><td><div class="deductions-row-actions"><a class="btn btn-sm btn-success" href="?<?php echo e(http_build_query(['round'=>$roundId,'action'=>'edit','id'=>$row['ru_id'],'search'=>$search,'page'=>$page])); ?>#rubberForm"><i data-lucide="pencil" aria-hidden="true"></i>บันทึกยอดหัก</a><a class="btn btn-sm collection-history-btn" href="collection_history.php?ru_id=<?php echo (int)$row['ru_id']; ?>"><i data-lucide="history" aria-hidden="true"></i>ประวัติ</a></div></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-5"><?php echo $search !== '' ? 'ไม่พบรายการของรอบนี้ที่ตรงกับคำค้น' : 'ไม่มีรายการรอบันทึกยอดหักในรอบนี้'; ?></td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if ($totalPages > 1): ?><nav class="deductions-pagination" aria-label="หน้ารายการยอดหัก"><?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['round'=>$roundId,'search'=>$search,'page'=>$page-1])); ?>">ก่อนหน้า</a><?php endif; ?><span>หน้า <?php echo $page; ?> / <?php echo $totalPages; ?></span><?php if ($page < $totalPages): ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['round'=>$roundId,'search'=>$search,'page'=>$page+1])); ?>">ถัดไป</a><?php endif; ?></nav><?php endif; ?>
</section>
<?php if ($selected): ?>
<script>
(() => {
  const form = document.getElementById('rubberForm');
  const baseValue = <?php echo json_encode((float)$selected['ru_value']); ?>;
  const fields = Array.from(form.querySelectorAll('input[type="number"]'));
  const format = value => value.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const update = () => {
    const total = fields.reduce((sum, input) => sum + (Number(input.value) || 0), 0);
    document.getElementById('deductionTotal').textContent = format(total);
    document.getElementById('deductionNet').textContent = format(baseValue - total);
  };
  fields.forEach(input => input.addEventListener('input', update)); update();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>

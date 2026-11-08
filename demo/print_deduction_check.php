<?php
require_once __DIR__ . '/functions.php';
require_login();
$db = db(); ensure_weighing_schema($db);
$id = (int)($_GET['ru_id'] ?? 0);
$stmt = $db->prepare("SELECT w.*, x.net_weight, x.round_date, x.approved_by, c.weigh_date AS collection_weigh_date FROM tbl_wang_weighing x JOIN tbl_wangyang w ON w.wang_id=x.wang_id LEFT JOIN tbl_collection_rounds c ON c.round_id=w.collection_round_id WHERE x.ru_id=? AND x.approval_route='review'");
$stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$row) { http_response_code(404); exit('ไม่พบรายการส่งตรวจสอบยอดหัก'); }
$approvalReturnUrl = 'approve_weighing.php?' . http_build_query([
    'round'=>(int)$row['collection_round_id'], 'lan'=>(string)$row['wang_lan'], 'tab'=>'pending',
]);
$isCancelled = collection_is_cancelled($row);
record_collection_audit($db, 'review_slip_open', (int)$row['wang_id'], $id, '');
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ใบตรวจสอบยอดหัก #<?php echo (int)$row['wang_id']; ?></title>
<style>
body{font-family:Tahoma,sans-serif;margin:20px;color:#000}.slip{width:80mm;max-width:100%;border:1px dashed #888;padding:4mm;box-sizing:border-box}h1{font-size:16px;text-align:center;margin:0 0 10px}p{font-size:14px;margin:8px 0;overflow-wrap:anywhere}.name{font-weight:bold;font-size:16px}.note{font-size:12px;border-top:1px dashed #888;padding-top:8px}.controls{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px}.controls button,.controls a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:9px 16px;border:1px solid #ccdcd1;border-radius:10px;font:600 14px Tahoma,sans-serif;text-decoration:none;cursor:pointer;box-sizing:border-box}.controls button{color:#fff;background:#245c38;border-color:#245c38}.controls button:hover{background:#19482a}.controls a{color:#425c4b;background:#f6f9f7}.controls a:hover{background:#e8f3eb}.controls button:focus-visible,.controls a:focus-visible{outline:3px solid #88b79a;outline-offset:3px}@media print{@page{size:80mm 60mm;margin:4mm}body{margin:0}.controls{display:none}.slip{border:0;padding:0;width:100%}}
</style></head><body>
<div class="controls"><button id="printCheck" type="button">พิมพ์ใบตรวจสอบ</button><a href="<?php echo e($approvalReturnUrl); ?>">กลับหน้ารออนุมัติ</a></div>
<article class="slip" aria-label="ใบตรวจสอบยอดหัก">
<?php if ($isCancelled): ?><p style="position:fixed;top:25mm;left:20mm;font-size:36px;font-weight:bold;color:#c00000;opacity:.5;transform:rotate(-20deg)">ยกเลิก</p><?php endif; ?>
<p class="name">ชื่อ: <?php echo e(collection_display_name($row['wang_name'], $row['wang_note'])); ?></p>
<p>วันที่ชั่งยาง: <?php echo e(thai_date_format($row['round_date'])); ?></p>
<p>กลุ่ม: <?php echo e($row['wang_group']); ?></p>
<p>จำนวนกระสอบ: <strong><?php echo number_format((float)$row['wang_sack'],2); ?></strong> กระสอบ</p>
<p>น้ำหนัก: <strong><?php echo number_format((float)$row['net_weight'],2); ?></strong> กก.</p>
</article>
<script>
let printRequested = false;
document.getElementById('printCheck').addEventListener('click', () => {
  printRequested = true;
  window.print();
});
window.addEventListener('afterprint', () => {
  if (printRequested) window.location.assign(<?php echo json_encode($approvalReturnUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
});
</script>
</body></html>

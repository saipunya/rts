<?php
require_once __DIR__ . '/functions.php';
require_login();
$db = db(); ensure_weighing_schema($db);
$search = trim((string)($_GET['search'] ?? ''));
$lane = (string)($_GET['lan'] ?? 'all');
if (!in_array($lane, ['all','1','2','3','4'], true)) $lane = 'all';
$result = issued_rubber_receipts($db, $search, $lane, (int)($_GET['page'] ?? 1));
require __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="assets/css/printed-receipts.css?v=<?php echo filemtime(__DIR__ . '/assets/css/printed-receipts.css'); ?>">
<section class="printed-receipts">
  <div class="receipt-heading"><div><h1>พิมพ์ใบเสร็จแล้ว</h1><p>รายการที่ออกใบเสร็จแล้ว เรียกดูประวัติหรือพิมพ์ซ้ำได้</p></div><a class="btn btn-outline-success" href="approve_weighing.php?tab=ready"><i data-lucide="printer" aria-hidden="true"></i>รายการรอพิมพ์ใบเสร็จ</a></div>
  <form method="get" class="receipt-filters">
    <div class="receipt-search"><label for="receipt-search">ค้นหารายการ</label><input class="form-control" type="search" id="receipt-search" name="search" value="<?php echo e($search); ?>" placeholder="ชื่อ เลขสมาชิก กลุ่ม หรือหมายเหตุ"></div>
    <div><label for="receipt-lane">ลาน</label><select class="form-select" id="receipt-lane" name="lan">
      <?php foreach (['all'=>'ทุกลาน','1'=>'ลาน 1','2'=>'ลาน 2','3'=>'ลาน 3','4'=>'ลาน 4'] as $value=>$label): ?><option value="<?php echo e($value); ?>" <?php echo $lane === (string)$value ? 'selected' : ''; ?>><?php echo e($label); ?></option><?php endforeach; ?>
    </select></div><button class="btn btn-success"><i data-lucide="search" aria-hidden="true"></i>ค้นหา</button>
    <?php if ($search !== '' || $lane !== 'all'): ?><a class="btn btn-outline-secondary" href="printed_receipts.php">ล้างตัวกรอง</a><?php endif; ?>
  </form>
  <p class="receipt-total"><?php echo number_format($result['total']); ?> รายการ<?php if ($result['total']): ?> · หน้า <?php echo $result['page']; ?> / <?php echo $result['total_pages']; ?><?php endif; ?></p>
  <div class="table-responsive receipt-table"><table class="table table-hover align-middle mb-0"><thead><tr><th>รายการ</th><th>ชื่อผู้ส่งยาง</th><th class="text-end">น้ำหนัก (กก.)</th><th class="text-end">ยอดหัก (บาท)</th><th class="text-end">รับสุทธิ (บาท)</th><th>จัดการ</th></tr></thead><tbody>
  <?php foreach ($result['rows'] as $row):
    $details = [
        'id'=>(int)$row['ru_id'],
        'name'=>collection_display_name($row['ru_fullname'], $row['wang_note']),
        'round'=>$row['collection_weigh_date'] ? 'รอบขายยาง ' . thai_date_format($row['collection_weigh_date']) : 'รายการเดิม (ยังไม่ระบุรอบ)',
        'lane'=>'ลาน ' . $row['ru_lan'],
        'sacks'=>$row['wang_sack'] !== null ? number_format((float)$row['wang_sack'],2) . ' กระสอบ' : 'ไม่มีข้อมูล',
        'issuedAt'=>thai_datetime_format($row['issued_at']),
        'issuedBy'=>$row['issued_by'],
    ];
  ?>
    <tr><td><strong>#<?php echo (int)$row['ru_id']; ?></strong><small>รอบ <?php echo e(thai_date_short_format($row['collection_weigh_date'] ?? $row['ru_date'])); ?></small></td>
    <td><strong><?php echo e(collection_display_name($row['ru_fullname'], $row['wang_note'])); ?></strong><small>เลขที่ <?php echo e($row['ru_number']); ?> · กลุ่ม <?php echo e($row['ru_group']); ?></small></td>
    <td class="text-end"><?php echo number_format((float)$row['ru_quantity'],2); ?></td><td class="text-end"><?php echo number_format((float)$row['ru_expend'],2); ?></td><td class="text-end fw-semibold text-success"><?php echo number_format((float)$row['ru_netvalue'],2); ?></td>
    <td><div class="receipt-row-actions"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#receiptDetailsModal" data-receipt="<?php echo e(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); ?>" aria-haspopup="dialog"><i data-lucide="info" aria-hidden="true"></i>รายละเอียด</button><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="export_rubber_pdf.php?ru_id=<?php echo (int)$row['ru_id']; ?>"><i data-lucide="printer" aria-hidden="true"></i>พิมพ์ซ้ำ</a></div></td></tr>
  <?php endforeach; ?>
  <?php if (!$result['rows']): ?><tr><td colspan="6" class="text-center text-muted py-5"><?php echo $search !== '' || $lane !== 'all' ? 'ไม่พบรายการตามตัวกรอง' : 'ยังไม่มีรายการที่พิมพ์ใบเสร็จแล้ว'; ?></td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if ($result['total_pages'] > 1): ?><nav class="receipt-pagination" aria-label="หน้ารายการใบเสร็จ">
    <?php if ($result['page'] > 1): ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['search'=>$search,'lan'=>$lane,'page'=>$result['page']-1])); ?>"><i data-lucide="chevron-left" aria-hidden="true"></i>ก่อนหน้า</a><?php endif; ?>
    <span>หน้า <?php echo $result['page']; ?> / <?php echo $result['total_pages']; ?></span>
    <?php if ($result['page'] < $result['total_pages']): ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['search'=>$search,'lan'=>$lane,'page'=>$result['page']+1])); ?>">ถัดไป<i data-lucide="chevron-right" aria-hidden="true"></i></a><?php endif; ?>
  </nav><?php endif; ?>
  <div class="modal fade" id="receiptDetailsModal" tabindex="-1" aria-labelledby="receiptDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content receipt-details">
      <div class="modal-header"><h2 class="modal-title fs-5" id="receiptDetailsTitle">รายละเอียดใบเสร็จ</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
      <div class="modal-body"><p class="receipt-details-name" data-detail="name"></p><dl class="receipt-details-grid">
        <dt>รอบขายยาง</dt><dd data-detail="round"></dd>
        <dt>ลาน</dt><dd data-detail="lane"></dd>
        <dt>จำนวนกระสอบ</dt><dd data-detail="sacks"></dd>
        <dt>ออกใบเสร็จเมื่อ</dt><dd data-detail="issuedAt"></dd>
        <dt>ผู้ดำเนินการ</dt><dd data-detail="issuedBy"></dd>
      </dl></div>
      <div class="modal-footer"><a class="btn btn-sm collection-history-btn" id="receiptDetailsHistory" href="collection_history.php"><i data-lucide="history" aria-hidden="true"></i>ประวัติผู้ดำเนินการ</a><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ปิด</button></div>
    </div></div>
  </div>
</section>
<script>
document.getElementById('receiptDetailsModal').addEventListener('show.bs.modal', function(event) {
  const trigger = event.relatedTarget;
  if (!trigger) return;
  const detail = JSON.parse(trigger.getAttribute('data-receipt'));
  document.getElementById('receiptDetailsTitle').textContent = 'รายละเอียดใบเสร็จ #' + detail.id;
  this.querySelectorAll('[data-detail]').forEach(element => {
    element.textContent = detail[element.dataset.detail] || '—';
  });
  document.getElementById('receiptDetailsHistory').href = 'collection_history.php?ru_id=' + encodeURIComponent(detail.id);
});
</script>
<?php require __DIR__ . '/footer.php'; ?>

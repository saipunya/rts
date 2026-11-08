<?php
if (!isset($feedRows,$feedId,$feedTitle,$feedDescription) || !is_array($feedRows)) { http_response_code(404); exit; }
$feedQuantity=array_sum(array_column($feedRows,'ru_quantity'));
$feedSackRows=array_filter($feedRows,static function(array $row): bool { return $row['wang_sack'] !== null; });
$feedSacks=array_sum(array_column($feedSackRows,'wang_sack'));
?>
<section class="public-recent" id="<?php echo e($feedId); ?>" aria-labelledby="<?php echo e($feedId); ?>Title">
  <div class="public-section-heading"><div><h2 id="<?php echo e($feedId); ?>Title"><?php echo e($feedTitle); ?></h2><p><?php echo e($feedDescription); ?></p></div><span class="public-data-label">แสดง <?php echo number_format(count($feedRows)); ?> รายการ</span></div>
  <div class="table-responsive public-recent-table"><table class="table table-hover mb-0"><caption class="visually-hidden"><?php echo e($feedTitle); ?> ไม่เกิน 20 รายการ</caption><colgroup><col class="public-recent-name-col"><col class="public-recent-lane-col"><col class="public-recent-sacks-col"><col class="public-recent-weight-col"></colgroup><thead><tr><th scope="col" class="public-recent-name-col">ชื่อสมาชิก / ผู้ส่งยาง</th><th scope="col" class="public-recent-lane-col">ลาน</th><th scope="col" class="text-end public-recent-sacks-col">กระสอบ</th><th scope="col" class="text-end public-recent-weight-col">น้ำหนัก (กก.)</th></tr></thead><tbody>
  <?php foreach($feedRows as $collection): ?>
    <tr data-record-id="<?php echo (int)$collection['ru_id']; ?>"><td class="public-member-name"><?php echo e($collection['collection_name']); ?><span class="public-record-number">รายการ #<?php echo (int)$collection['ru_id']; ?></span></td><td><?php echo e($collection['ru_lan']); ?></td><td class="text-end"><?php echo $collection['wang_sack'] === null ? '—' : number_format((float)$collection['wang_sack'],2); ?></td><td class="text-end"><?php echo number_format((float)$collection['ru_quantity'],2); ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$feedRows): ?><tr><td colspan="4" class="text-center text-secondary py-4">ยังไม่มีรายการในขั้นตอนนี้</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if ($feedRows): ?><div class="public-recent-totals"><strong>รวม <?php echo number_format(count($feedRows)); ?> รายการที่แสดง</strong><span>กระสอบ <strong><?php echo $feedSackRows ? number_format((float)$feedSacks,2) : '—'; ?></strong></span><span>น้ำหนัก <strong><?php echo number_format((float)$feedQuantity,2); ?></strong> กก.</span></div><?php endif; ?>
  <?php if (count($feedSackRows)<count($feedRows)): ?><p class="public-note">— หมายถึงไม่มีข้อมูลจำนวนกระสอบ ยอดรวมกระสอบนับเฉพาะรายการที่มีข้อมูล</p><?php endif; ?>
</section>

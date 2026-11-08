<link rel="stylesheet" href="assets/css/collection-rounds.css?v=<?php echo filemtime(__DIR__ . '/assets/css/collection-rounds.css'); ?>">
<div class="collection-round-bar">
  <?php if ($round): ?>
    <strong><?php echo e(collection_round_label($round)); ?></strong>
    <div class="collection-round-description">วันที่ราคายาง / วันชั่ง <?php echo e(thai_date_format($round['weigh_date'])); ?> · เรียกเฉพาะรายการวางยาง <?php echo e(thai_date_format($round['lay_start'])); ?> – <?php echo e(thai_date_format($round['lay_end'])); ?></div>
  <?php else: ?><strong>ยังไม่มีราคายาง</strong><p class="collection-round-description">บันทึกวางยางได้ตามปกติ เมื่อมีราคายางล่าสุดจึงจะเรียกรายการย้อนหลัง 2 วันมาชั่งได้</p><?php endif; ?>
  <?php if (is_admin()): ?><a class="btn btn-sm btn-outline-secondary collection-round-settings" href="price_form.php?action=create"><i data-lucide="coins" aria-hidden="true"></i>บันทึกราคายาง</a><?php endif; ?>
</div>

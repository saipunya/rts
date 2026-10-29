<?php if (!$round): ?>
<div class="modal fade" id="missingRoundModal" tabindex="-1" aria-labelledby="missingRoundTitle" aria-describedby="missingRoundMessage" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4">
    <div class="modal-header"><h2 class="modal-title fs-5" id="missingRoundTitle">ไม่พบรอบรวบรวมยาง</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
    <div class="modal-body" id="missingRoundMessage">ไม่พบรอบที่เลือกหรือยังไม่มีราคายาง กรุณาตรวจสอบวันที่ราคายางล่าสุดก่อนดำเนินการ</div>
    <div class="modal-footer"><a class="btn btn-outline-secondary" href="<?php echo e($missingRoundReturnUrl ?? 'approve_weighing.php'); ?>"><?php echo e($missingRoundReturnLabel ?? 'กลับหน้าอนุมัติ'); ?></a><button type="button" class="btn btn-success" data-bs-dismiss="modal">ตกลง</button></div>
  </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  bootstrap.Modal.getOrCreateInstance(document.getElementById('missingRoundModal')).show();
});
</script>
<?php endif; ?>

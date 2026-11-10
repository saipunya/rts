<?php
if (!isset($dashboard,$listUrl) || !is_array($dashboard) || !is_callable($listUrl)) { http_response_code(404); exit; }
if ($dashboard['total_pages']>1): ?>
<nav class="public-pagination" aria-label="หน้ารายการตามสถานะ">
  <?php if ($dashboard['page']>1): ?><a class="btn btn-outline-success" rel="prev" href="<?php echo e($listUrl($dashboard['stage'],$dashboard['page']-1)); ?>">ก่อนหน้า</a><?php endif; ?>
  <span>หน้า <?php echo $dashboard['page']; ?> / <?php echo $dashboard['total_pages']; ?></span>
  <?php if ($dashboard['page']<$dashboard['total_pages']): ?><a class="btn btn-outline-success" rel="next" href="<?php echo e($listUrl($dashboard['stage'],$dashboard['page']+1)); ?>">ถัดไป</a><?php endif; ?>
</nav>
<?php endif; ?>

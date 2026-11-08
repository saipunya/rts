<?php
require_once __DIR__.'/functions.php';
require_login();
require_once __DIR__.'/collection_analytics.php';
$db=db(); ensure_weighing_schema($db);
require __DIR__.'/partials/collection-analytics-data.php';
include __DIR__.'/header.php';
?>
<link rel="stylesheet" media="screen" href="assets/css/collection-analytics.css?v=<?php echo filemtime(__DIR__.'/assets/css/collection-analytics.css'); ?>">
<div class="home-dashboard staff-analysis-page">
  <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4"><div><h1 class="h4 mb-1">รายงานวิเคราะห์สำหรับเจ้าหน้าที่</h1><p class="text-secondary mb-0">เลือกช่วงวันที่และลานเพื่อเปรียบเทียบข้อมูลการรับซื้อ</p></div><a href="dashboard.php" class="btn btn-outline-secondary">กลับศูนย์งาน</a></div>
  <?php require __DIR__.'/partials/collection-analytics-panel.php'; ?>
  <section id="dailySummary" aria-labelledby="analysisTableTitle"><div class="section-title"><h2 id="analysisTableTitle" class="h6 mb-0">ตารางรับซื้อรายวันตามตัวกรอง</h2></div><div class="table-responsive"><table class="table table-striped table-hover mb-0"><thead><tr><th>วันที่รับซื้อ</th><th class="text-end">ราคาเฉลี่ยถ่วงน้ำหนัก (บาท/กก.)</th><th class="text-end">ปริมาณ (กก.)</th><th class="text-end">มูลค่ารับซื้อ (บาท)</th><th class="text-end">ยอดหัก (บาท)</th><th class="text-end">ยอดสุทธิ (บาท)</th><th class="text-end">รายการ</th><th class="text-end">สมาชิก/ทั่วไป (คน)</th></tr></thead><tbody>
  <?php foreach(array_reverse($analytics['days']) as $day): $people=$countByDate[$day['date']] ?? ['member'=>0,'general'=>0]; ?>
    <tr><td class="text-nowrap"><?php echo e(thai_date_format($day['date'])); ?></td><td class="text-end"><?php echo $day['price']===null ? '—' : number_format($day['price'],2); ?></td><td class="text-end"><?php echo number_format($day['quantity'],2); ?></td><td class="text-end"><?php echo number_format($day['value'],2); ?></td><td class="text-end"><?php echo number_format($day['deductions'],2); ?></td><td class="text-end"><?php echo number_format($day['net'],2); ?></td><td class="text-end"><?php echo number_format($day['records']); ?></td><td class="text-end"><?php echo number_format($people['member']).'/'.number_format($people['general']); ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$analytics['days']): ?><tr><td colspan="8" class="text-center text-secondary py-4">ไม่พบรายการในช่วงที่เลือก</td></tr><?php endif; ?>
  </tbody><tfoot><tr class="table-light fw-bold"><td colspan="2">รวมตามตัวกรอง</td><?php foreach(['quantity','value','deductions','net'] as $field): ?><td class="text-end"><?php echo number_format($analytics['totals'][$field],2); ?></td><?php endforeach; ?><td class="text-end"><?php echo number_format($analytics['totals']['records']); ?></td><td class="text-end">—</td></tr></tfoot></table></div></section>
</div>
<script src="assets/vendor/chart-4.4.1.umd.min.js"></script>
<script src="assets/js/collection-analytics.js?v=<?php echo filemtime(__DIR__.'/assets/js/collection-analytics.js'); ?>"></script>
<?php include __DIR__.'/footer.php'; ?>

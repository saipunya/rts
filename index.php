<?php
require_once __DIR__.'/functions.php';
require_once __DIR__.'/public_collection_feeds.php';
$db=db(); ensure_weighing_schema($db);
$currentCollectionRound=latest_priced_collection_round($db);
$currentRoundPrice=$currentCollectionRound ? latest_collection_round_price($db,(int)$currentCollectionRound['round_id']) : null;
$latest_price_date=$currentCollectionRound['weigh_date'] ?? null;
$latest_price_date_text=$latest_price_date ? thai_date_format($latest_price_date) : 'ยังไม่มีรอบราคา';
$rubberAnnouncement=fetch_rubber_collection_announcement($db,$latest_price_date);
$rubberLiveSummary=fetch_rubber_round_live_summary($db,$latest_price_date);
$validRubber="(SELECT * FROM tbl_rubber WHERE LOWER(TRIM(COALESCE(record_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564))";
$totals=$db->query("SELECT COALESCE(SUM(ru_quantity),0) quantity,COALESCE(SUM(ru_value),0) value,COUNT(*) records,COUNT(DISTINCT ru_date) days FROM $validRubber r")->fetch_assoc();
$latest=['quantity'=>0,'value'=>0,'records'=>0,'members'=>0,'general'=>0];
$lanes=[];
if ($latest_price_date) {
    $stmt=$db->prepare("SELECT COALESCE(SUM(ru_quantity),0) quantity,COALESCE(SUM(ru_value),0) value,COUNT(*) records,COUNT(DISTINCT CASE WHEN LOWER(TRIM(ru_class))='member' THEN NULLIF(TRIM(ru_number),'') END) members,COUNT(DISTINCT CASE WHEN LOWER(TRIM(ru_class))='general' THEN NULLIF(TRIM(ru_fullname),'') END) general FROM $validRubber r WHERE ru_date=?");
    $stmt->bind_param('s',$latest_price_date);$stmt->execute();$latest=$stmt->get_result()->fetch_assoc();$stmt->close();
    $stmt=$db->prepare("SELECT ru_lan,COALESCE(SUM(ru_quantity),0) quantity,COALESCE(SUM(ru_value),0) value,COUNT(*) records FROM $validRubber r WHERE ru_date=? GROUP BY ru_lan ORDER BY CAST(ru_lan AS UNSIGNED),ru_lan");
    $stmt->bind_param('s',$latest_price_date);$stmt->execute();$lanes=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
}
$history=$db->query("SELECT ru_date,COALESCE(SUM(ru_quantity),0) quantity,COALESCE(SUM(ru_value),0) value FROM $validRubber r GROUP BY ru_date ORDER BY ru_date DESC LIMIT 12")->fetch_all(MYSQLI_ASSOC);
$history=array_reverse($history);
$collectionFeeds=rts_public_collection_feeds($db);
$publicData=['dates'=>array_column($history,'ru_date'),'quantity'=>array_map('floatval',array_column($history,'quantity')),'value'=>array_map('floatval',array_column($history,'value'))];
include __DIR__.'/header.php';
?>
<link rel="stylesheet" media="screen" href="assets/css/public-overview.css?v=<?php echo filemtime(__DIR__.'/assets/css/public-overview.css'); ?>">
<div class="public-overview home-dashboard">
  <section class="public-intro" aria-labelledby="publicTitle"><div><span class="public-eyebrow">ระบบงานสหกรณ์การเกษตร</span><h1 id="publicTitle">ระบบรวบรวมยางพารา</h1><p class="public-cooperative">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</p><p class="public-description">เชื่อมโยงการวางยาง ชั่งน้ำหนัก ตรวจสอบยอด และออกใบเสร็จ<br class="d-none d-md-block"> พร้อมข้อมูลสรุปการรวบรวมที่ติดตามได้</p><div class="public-entry"><a href="allmember.php" class="btn btn-success public-member-entry"><i data-lucide="users" aria-hidden="true"></i><span>สำหรับสมาชิก</span><i data-lucide="arrow-right" aria-hidden="true"></i></a><a href="<?php echo is_logged_in() ? 'dashboard.php' : 'login.php'; ?>" class="btn btn-outline-secondary public-staff-entry"><i data-lucide="log-in" aria-hidden="true"></i><?php echo is_logged_in() ? 'ศูนย์งานเจ้าหน้าที่' : 'เข้าสู่ระบบเจ้าหน้าที่'; ?></a></div><p class="public-member-help">ดูข้อมูลการรวบรวมยางของตนเองผ่านระบบสมาชิก</p></div><aside class="public-price" aria-label="ราคาประกาศล่าสุด"><span>ราคาประกาศล่าสุด</span><strong><?php echo $currentRoundPrice ? number_format((float)$currentRoundPrice['pr_price'],2) : '—'; ?></strong><span>บาทต่อกิโลกรัม</span><small><?php echo e($latest_price_date ? 'รอบวันที่ '.$latest_price_date_text : $latest_price_date_text); ?></small></aside></section>
  <ol class="public-workflow" aria-label="ขั้นตอนของระบบ"><li><span>01</span>บันทึกวางยาง</li><li><span>02</span>ชั่งน้ำหนัก</li><li><span>03</span>ตรวจสอบและบันทึกยอดหัก</li><li><span>04</span>ออกใบเสร็จ</li></ol>
  <div class="public-notice-grid">
  <?php if (!empty($rubberAnnouncement['show'])): ?>
  <div class="row g-3 mb-4">
    <div class="col-12">
      <div class="card announcement-card">
        <div class="card-body p-3 p-md-4">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div class="d-flex align-items-start gap-3">
              <div class="announcement-icon" aria-hidden="true">
                <i data-lucide="megaphone" class="fs-4"></i>
              </div>
              <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                  <span class="badge text-bg-warning text-dark">ประกาศอัตโนมัติ</span>
                  <h2 class="h5 mb-0">วันวางยางและวันชั่งยางรอบล่าสุด</h2>
                </div>
                <div class="announcement-note mb-2">
                  <?php echo e($rubberAnnouncement['lay_start_text'] ?? '-'); ?> ถึง
                  <?php echo e($rubberAnnouncement['lay_end_text'] ?? '-'); ?>
                  สำหรับการวางยาง และชั่งยางวันที่ <?php echo e($rubberAnnouncement['weigh_date_text'] ?? '-'); ?>
                </div>
                <div class="small text-muted">
                  ประกาศนี้แสดงตามรอบขายยางที่ตั้งค่า และแสดงจนถึงวันชั่งยาง และจะหายไปอัตโนมัติหลังวันชั่งยาง
                </div>
              </div>
            </div>
            <div class="d-flex flex-column gap-2">
              <span class="announcement-pill"><i data-lucide="calendar-range" aria-hidden="true"></i> วางยาง
                <?php echo e($rubberAnnouncement['lay_start_text'] ?? '-'); ?></span>
              <span class="announcement-pill"><i data-lucide="calendar-check" aria-hidden="true"></i> วางยาง
                <?php echo e($rubberAnnouncement['lay_end_text'] ?? '-'); ?></span>
              <span class="announcement-pill"><i data-lucide="scale" aria-hidden="true"></i> ชั่งยาง
                <?php echo e($rubberAnnouncement['weigh_date_text'] ?? '-'); ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div id="liveRoundSection" class="row g-3 mb-4<?php echo empty($rubberLiveSummary['show']) ? ' d-none' : ''; ?>">
    <div class="col-12">
      <div class="card live-round-card">
        <div class="card-body">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
            <div>
              <div class="live-round-badge mb-2">
                <i data-lucide="radio" aria-hidden="true"></i> อัปเดตทุก 5 นาที
              </div>
              <h2 class="h5 mb-1">สรุปข้อมูลรอบวางยางล่าสุด</h2>
              <div class="small text-muted">
                <?php echo e($rubberLiveSummary['lay_start_text'] ?? '-'); ?> ถึง
                <?php echo e($rubberLiveSummary['lay_end_text'] ?? '-'); ?>
                และชั่งยางวันที่ <?php echo e($rubberLiveSummary['weigh_date_text'] ?? '-'); ?>
              </div>
            </div>
            <div class="live-round-time">
              <i data-lucide="clock-3" aria-hidden="true"></i>
              อัปเดตล่าสุด <span
                id="liveRoundUpdatedAt"><?php echo e($rubberLiveSummary['updated_at_text'] ?? '-'); ?></span>
            </div>
          </div>

          <div class="live-round-grid">
            <div class="card live-round-tile bg-success-subtle">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3">
                  <div>
                    <div class="live-round-label mb-1">น้ำหนักรวม</div>
                    <div class="live-round-value" id="liveRoundQuantity">
                      <?php echo number_format((float)($rubberLiveSummary['total_quantity'] ?? 0), 2); ?> kg</div>
                  </div>
                  <div class="live-round-icon" aria-hidden="true">
                    <i data-lucide="scale" class="fs-3"></i>
                  </div>
                </div>
                <div class="live-round-foot">
                  <span class="small text-success-emphasis">รวมทุกลานในรอบล่าสุด</span>
                </div>
              </div>
            </div>

            <div class="card live-round-tile bg-warning-subtle">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3">
                  <div>
                    <div class="live-round-label mb-1">ยอดเงินหัก</div>
                    <div class="live-round-value" id="liveRoundDeduct">
                      <?php echo number_format((float)($rubberLiveSummary['total_expend'] ?? 0), 2); ?> ฿</div>
                  </div>
                  <div class="live-round-icon" aria-hidden="true" style="background:#fff4db;color:#b45309;">
                    <i data-lucide="file-text" class="fs-3"></i>
                  </div>
                </div>
                <div class="live-round-foot">
                  <span class="small text-warning-emphasis">หักรวมจากทุกรายการในรอบนี้</span>
                </div>
              </div>
            </div>

            <div class="card live-round-tile bg-primary-subtle">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3">
                  <div>
                    <div class="live-round-label mb-1">ยอดเงินที่จ่าย</div>
                    <div class="live-round-value" id="liveRoundNet">
                      <?php echo number_format((float)($rubberLiveSummary['total_net'] ?? 0), 2); ?> ฿</div>
                  </div>
                  <div class="live-round-icon" aria-hidden="true" style="background:#e0ecff;color:#1d4ed8;">
                    <i data-lucide="wallet" class="fs-3"></i>
                  </div>
                </div>
                <div class="live-round-foot">
                  <span class="small text-primary-emphasis">จ่ายสุทธิหลังหักทั้งหมด</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  </div>
  <div class="public-content-grid">
  <section class="public-summary" aria-labelledby="latestSummaryTitle"><div class="public-section-heading"><div><h2 id="latestSummaryTitle">สรุปการรวบรวมรอบล่าสุด</h2><p><?php echo e($latest_price_date ? 'รอบวันที่ '.$latest_price_date_text : $latest_price_date_text); ?> · รวมทุกลาน ไม่รวมรายการยกเลิก</p></div><span class="public-data-label">ข้อมูลจากรายการรับซื้อ</span></div>
    <div class="public-kpis"><article><span>ปริมาณรวบรวม</span><strong><?php echo number_format((float)$latest['quantity'],2); ?><small>กิโลกรัม</small></strong></article><article><span>มูลค่ารับซื้อ</span><strong><?php echo number_format((float)$latest['value'],2); ?><small>บาท</small></strong></article><article><span>ผู้ส่งยางในรอบ</span><strong><?php echo number_format((int)$latest['members']+(int)$latest['general']); ?><small>คน</small></strong></article><article><span>รายการรับซื้อ</span><strong><?php echo number_format((int)$latest['records']); ?><small>รายการ</small></strong></article></div>
    <p class="public-note">มูลค่ารับซื้อใช้ยอดที่บันทึกจริงตามราคาตอนชั่ง ผู้ส่งยางนับสมาชิกตามเลขสมาชิกและเกษตรกรทั่วไปตามชื่อภายในรอบนั้น</p>
  </section>
  <?php
  $feedRows=$collectionFeeds['weighed']; $feedId='recentWeighed';
  $feedTitle='20 รายการล่าสุดที่ชั่งแล้ว';
  $feedDescription='เรียงตามเวลายืนยันผลชั่งล่าสุด · รวมทุกลาน ไม่รวมรายการยกเลิก';
  require __DIR__.'/partials/public-collection-feed.php';
  ?>
  <?php
  $feedRows=$collectionFeeds['receipts']; $feedId='recentReceipts';
  $feedTitle='20 รายการล่าสุดที่พิมพ์ใบเสร็จแล้ว';
  $feedDescription='แสดงตามรายการในหน้า “พิมพ์ใบเสร็จแล้ว” · ไม่รวมรายการยกเลิก';
  require __DIR__.'/partials/public-collection-feed.php';
  ?>
  <section class="public-history" aria-labelledby="publicHistoryTitle"><div class="public-section-heading"><div><h2 id="publicHistoryTitle">ภาพรวมการรวบรวมที่ผ่านมา</h2><p>ข้อมูล <?php echo number_format(count($history)); ?> วันที่มีรายการล่าสุด (สูงสุด 12 วัน) · รวมทุกลาน</p></div></div>
    <?php if ($history): ?><div id="publicChartStatus" class="alert alert-warning" hidden>ยังแสดงกราฟไม่ได้ ข้อมูลสรุปและตารางด้านล่างยังใช้งานได้</div><div class="public-charts"><article><header><h3>ปริมาณยางที่รวบรวม</h3><span>กิโลกรัม</span></header><div class="public-chart-canvas"><canvas id="publicQuantityChart" role="img" aria-label="สรุปปริมาณรวบรวมย้อนหลัง">ดูตัวเลขปริมาณรวบรวมในข้อมูลสรุป</canvas></div></article><article><header><h3>มูลค่ารับซื้อ</h3><span>บาท</span></header><div class="public-chart-canvas"><canvas id="publicValueChart" role="img" aria-label="สรุปมูลค่ารับซื้อย้อนหลัง">ดูตัวเลขมูลค่ารับซื้อในข้อมูลสรุป</canvas></div></article></div><noscript><p class="public-note">เปิด JavaScript เพื่อดูกราฟ หรือตรวจข้อมูลจากตัวเลขสรุปและตารางรายลาน</p></noscript><?php else: ?><div class="public-empty">ยังไม่มีรายการรับซื้อสำหรับแสดงภาพรวม</div><?php endif; ?>
  </section>
  <section class="public-lifetime" aria-labelledby="lifetimeTitle"><h2 id="lifetimeTitle">ภาพรวมสะสมของระบบ</h2><div><span>ปริมาณรวบรวมทั้งหมด<strong id="publicQuantityTotal"><?php echo number_format((float)$totals['quantity'],2); ?> <small>กก.</small></strong></span><span>มูลค่ารับซื้อทั้งหมด<strong><?php echo number_format((float)$totals['value'],2); ?> <small>บาท</small></strong></span><span>รายการรับซื้อทั้งหมด<strong><?php echo number_format((int)$totals['records']); ?> <small>รายการ</small></strong></span><span>วันที่มีการรับซื้อ<strong><?php echo number_format((int)$totals['days']); ?> <small>วัน</small></strong></span></div><p class="public-note">รวมข้อมูลตั้งแต่เริ่มมีรายการในระบบ โดยไม่รวมรายการที่ยกเลิก ไม่ใช่ยอดเฉพาะ 12 วันที่แสดงในกราฟ</p></section>
  <section aria-labelledby="publicLanesTitle"><div class="public-section-heading"><div><h2 id="publicLanesTitle">สรุปรับซื้อรายลานรอบล่าสุด</h2><p><?php echo e($latest_price_date_text); ?></p></div></div><div class="table-responsive public-lane-table"><table class="table table-hover mb-0"><thead><tr><th>ลานรับซื้อ</th><th class="text-end">ปริมาณ (กก.)</th><th class="text-end">มูลค่ารับซื้อ (บาท)</th><th class="text-end">รายการ</th></tr></thead><tbody><?php foreach($lanes as $lane): ?><tr><td>ลาน <?php echo e($lane['ru_lan']); ?></td><td class="text-end"><?php echo number_format((float)$lane['quantity'],2); ?></td><td class="text-end"><?php echo number_format((float)$lane['value'],2); ?></td><td class="text-end"><?php echo number_format((int)$lane['records']); ?></td></tr><?php endforeach; ?><?php if (!$lanes): ?><tr><td colspan="4" class="text-center text-secondary py-4">ยังไม่มีรายการรับซื้อในรอบนี้</td></tr><?php endif; ?></tbody></table></div></section>
  </div>
  <p class="public-note public-snapshot-note">ข้อมูลสรุปตามรายการที่บันทึกในระบบ ณ เวลาที่เปิดหน้า ไม่ใช่การยืนยันสถานะการจ่ายเงิน</p>
  <script type="application/json" id="publicOverviewData"><?php echo json_encode($publicData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); ?></script>
</div>
<script src="assets/js/public-overview.js?v=<?php echo filemtime(__DIR__.'/assets/js/public-overview.js'); ?>"></script>
<?php include __DIR__.'/footer.php'; ?>

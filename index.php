<?php
require_once __DIR__.'/functions.php';
require_once __DIR__.'/public_dashboard_data.php';
$db = db();
ensure_weighing_schema($db);
$member = is_string($_GET['member'] ?? null) ? trim($_GET['member']) : '';
if (mb_strlen($member) > 100) $member = '';
$dashboard = rts_public_dashboard($db,$member);
$history = $db->query("SELECT ru_date,COALESCE(SUM(ru_quantity),0) quantity FROM tbl_rubber
    WHERE LOWER(TRIM(COALESCE(record_status,'normal'))) NOT IN ('cancelled','canceled','deleted')
    GROUP BY ru_date ORDER BY ru_date DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$history = array_reverse($history);
$historyMax = max(array_merge([1.0],array_map('floatval',array_column($history,'quantity'))));
require __DIR__.'/header.php';
?>
<link rel="stylesheet" media="screen"
  href="assets/css/public-overview.css?v=<?php echo filemtime(__DIR__.'/assets/css/public-overview.css'); ?>">
<div class="public-overview" id="publicDashboard">
  <?php if (is_logged_in() && !is_admin() && !rts_current_acl()['permissions']): ?><p class="alert alert-info">บัญชีนี้ยังไม่มีเมนูงานที่ได้รับอนุญาต กรุณาติดต่อ admin เพื่อกำหนดสิทธิ์ คุณยังดูข้อมูลสาธารณะได้ตามปกติ</p><?php endif; ?>
  <section class="public-intro" aria-labelledby="publicTitle">
    <div class="public-intro-copy"><span class="public-eyebrow"><span class="public-live-dot" aria-hidden="true"></span>
        เชื่อมทุกขั้นตอน เห็นทุกความเคลื่อนไหว</span>
      <h1 id="publicTitle">ติดตามการรวบรวม<br><span>ง่ายขึ้น ในหน้าเดียว</span></h1>
      <p>สมาชิกดูสถานะได้ทันที ไม่ต้องเข้าสู่ระบบ<br class="d-none d-sm-block"> ตั้งแต่วางยาง ชั่งน้ำหนัก
        จนถึงออกใบเสร็จ</p>
      <div class="public-entry"><a href="#tracking" class="btn btn-success"><i data-lucide="search"
            aria-hidden="true"></i>ติดตามรายการของฉัน</a><a href="#overview" class="public-text-link">ดูภาพรวมการรวบรวม
          <span aria-hidden="true">↗</span></a></div>
    </div>
    <aside class="public-price" aria-label="ราคายางรอบล่าสุด"><span><i data-lucide="trending-up" aria-hidden="true"></i>
        ราคายางรอบล่าสุด</span><strong
        id="publicPrice"><?php echo $dashboard['round'] ? number_format($dashboard['round']['price'],2) : '—'; ?><small>บาท
          / กก.</small></strong>
      <p id="publicPriceDate">
        <?php echo $dashboard['round'] ? 'รอบวันที่ '.e(thai_date_format($dashboard['round']['date'])) : 'รอประกาศราคายาง'; ?>
      </p>
      <div class="public-price-foot"><i data-lucide="shield-check" aria-hidden="true"></i> ข้อมูลจากระบบสหกรณ์</div>
    </aside>
  </section>

  <section id="overview" aria-labelledby="latestSummaryTitle" class="public-summary">
    <div class="public-section-heading">
      <div><span class="public-eyebrow">ภาพรวมการดำเนินงาน</span>
        <h2 id="latestSummaryTitle">ทุกขั้นตอน เชื่อมต่อกัน</h2>
        <p id="publicRoundLabel">
          <?php echo $dashboard['round'] ? 'รอบวันที่ '.e(thai_date_format($dashboard['round']['date'])) : 'ยังไม่มีรอบราคายาง'; ?>
        </p>
      </div><span class="public-round-state"
        id="publicRoundState"><?php echo !$dashboard['round'] ? 'รอประกาศราคา' : ($dashboard['round']['is_today'] ? 'รอบวันนี้' : ($dashboard['round']['is_future'] ? 'รอบที่ประกาศล่วงหน้า' : 'ข้อมูลรอบล่าสุด')); ?></span>
    </div>
    <div class="public-kpis">
      <?php foreach (['placed'=>['package-check','วางยางในรอบ','รายการ'],'weighed'=>['scale','ชั่งน้ำหนักแล้ว','รายการ'],'weight'=>['bar-chart-3','น้ำหนักรวม','กก.']] as $key=>$metric): ?>
      <article><span class="public-metric-icon"><i data-lucide="<?php echo e($metric[0]); ?>"
            aria-hidden="true"></i></span>
        <div><span><?php echo e($metric[1]); ?></span><strong
            data-public-metric="<?php echo e($key); ?>"><?php echo number_format($dashboard['summary'][$key],$key==='weight'?2:0); ?></strong><small><?php echo e($metric[2]); ?></small>
        </div>
      </article>
      <?php endforeach; ?>
      <article class="public-participants-card">
        <span class="public-metric-icon"><i data-lucide="users" aria-hidden="true"></i></span>
        <div class="public-participants"><span>ผู้ส่งยางในรอบ</span>
          <dl>
            <div>
              <dt>สมาชิก</dt>
              <dd><strong
                  data-public-metric="members"><?php echo number_format($dashboard['summary']['members']); ?></strong><small>คน</small>
              </dd>
            </div>
            <div>
              <dt>เกษตรกรทั่วไป</dt>
              <dd><strong
                  data-public-metric="general"><?php echo number_format($dashboard['summary']['general']); ?></strong><small>คน</small>
              </dd>
            </div>
          </dl>
        </div>
      </article>
    </div>
    <p class="public-note">จำนวนสมาชิกและเกษตรกรทั่วไปนับคนไม่ซ้ำจากรายการในรอบนี้ รวมผู้ที่รอชั่ง และไม่รวมรายการยกเลิก
    </p>
    <div class="public-progress">
      <div><span>ความคืบหน้าการชั่ง</span><strong
          id="publicProgressText"><?php echo $dashboard['summary']['weighed']; ?> /
          <?php echo $dashboard['summary']['placed']; ?> รายการ</strong></div><progress id="publicProgress"
        value="<?php echo $dashboard['summary']['weighed']; ?>"
        max="<?php echo max(1,$dashboard['summary']['placed']); ?>" aria-label="ความคืบหน้าการชั่ง"></progress>
    </div>
  </section>

  <section id="tracking" class="public-tracking" aria-labelledby="trackingTitle">
    <div class="public-section-heading">
      <div><span class="public-eyebrow">สำหรับสมาชิกและผู้ส่งยาง</span>
        <h2 id="trackingTitle">รายการของฉัน ถึงขั้นตอนไหนแล้ว?</h2>
        <p>ค้นหาด้วยชื่อหรือเลขสมาชิก หรือดูความเคลื่อนไหวล่าสุดของทุกลาน</p>
      </div><span class="public-live-label"><span class="public-live-dot" aria-hidden="true"></span> อัปเดตทุก 10
        วินาที</span>
    </div>
    <div class="public-tracking-layout">
      <div class="public-board">
        <form id="publicMemberSearch" class="public-search" method="get" action="index.php#tracking" role="search">
          <div><span class="public-search-icon"><i data-lucide="search" aria-hidden="true"></i></span><input
              id="publicMemberNumber" name="member" type="search" maxlength="100" autocomplete="off"
              placeholder="พิมพ์ชื่อ นามสกุล หรือเลขสมาชิก" value="<?php echo e($member); ?>"><button
              class="btn btn-success" type="submit">ค้นหา</button><button class="btn btn-outline-secondary"
              type="button" id="publicClearSearch" <?php echo $member === '' ? 'hidden' : ''; ?>>ล้าง</button></div>

        </form>

        <div class="public-tabs" role="group" aria-label="กรองสถานะรายการ"><button type="button" class="is-active"
            aria-pressed="true" data-public-stage="all">ทั้งหมด</button><button type="button" aria-pressed="false"
            data-public-stage="weighed">ชั่งแล้ว</button><button type="button" aria-pressed="false"
            data-public-stage="issued">ออกใบเสร็จแล้ว</button></div>
        <div class="public-board-meta">
          <p id="publicResultLabel" role="status">
            <?php echo $member !== '' ? 'คำค้น “'.e($member).'” · ' : ''; ?><?php echo number_format($dashboard['matched']); ?>
            รายการ<?php echo $dashboard['matched']>20?' · แสดง 20 รายการล่าสุด':''; ?></p><button id="publicRefresh"
            type="button" aria-label="อัปเดตข้อมูลตอนนี้" title="อัปเดตข้อมูลตอนนี้"><i data-lucide="refresh-cw"
              aria-hidden="true"></i></button>
        </div>
        <div class="public-all-records"><a id="publicAllRecords"
            href="public_collection_list.php?<?php echo e(http_build_query(['member'=>$member,'stage'=>'all'])); ?>">ดูรายการทั้งหมดตามสถานะ
            <i data-lucide="arrow-up-right" aria-hidden="true"></i></a></div>
        <div id="publicRecords" class="public-records">
          <?php require __DIR__.'/partials/public-status-records.php'; ?>
        </div>
        <div class="public-update"><span id="publicConnection" role="status">อัปเดตล่าสุด <time
              datetime="<?php echo e($dashboard['updated_at']); ?>"><?php echo e((new DateTimeImmutable($dashboard['updated_at']))->format('H:i:s')); ?></time></span><span>เวลาประเทศไทย</span>
        </div>
        <noscript>
          <p class="public-note">เปิด JavaScript เพื่ออัปเดตอัตโนมัติ หรือค้นหาและโหลดหน้าใหม่เพื่อดูสถานะล่าสุด</p>
        </noscript>
      </div>
      <aside class="public-guide"><span class="public-guide-icon"><i data-lucide="scan-line"
            aria-hidden="true"></i></span>
        <h3>สแกนครั้งเดียว<br>ติดตามได้ตลอดรอบ</h3>
        <p>เปิดหน้านี้ค้างไว้ ระบบจะอัปเดตสถานะให้เอง ไม่ต้องกดรีเฟรช</p>
        <ol>
          <li><span>1</span>
            <div><strong>วางยางแล้ว</strong><small>รอเรียกรายการเข้าชั่ง</small></div>
          </li>
          <li><span>2</span>
            <div><strong>ชั่งแล้ว</strong><small>อยู่ระหว่างอนุมัติหรือตรวจสอบ</small></div>
          </li>
          <li><span>3</span>
            <div><strong>รอออกใบเสร็จ</strong><small>ตรวจสอบยอดเรียบร้อยแล้ว</small></div>
          </li>
          <li><span>4</span>
            <div><strong>ออกใบเสร็จแล้ว</strong><small>ระบบบันทึกการออกใบเสร็จแล้ว</small></div>
          </li>
        </ol>
        <p class="public-guide-note">สถานะแสดงความคืบหน้าของรายการ ไม่ใช่ลำดับเรียกรับเงินหรือการยืนยันจ่ายเงิน</p>
      </aside>
    </div>

  </section>

  <section class="public-lanes" aria-labelledby="publicLanesTitle">
    <div class="public-section-heading"><div><span class="public-eyebrow">ปริมาณรวบรวมแยกตามลาน</span><h2 id="publicLanesTitle">น้ำหนักยางในแต่ละลาน</h2><p id="publicLaneRound"><?php echo $dashboard['round'] ? 'รอบวันที่ '.e(thai_date_format($dashboard['round']['date'])) : 'ยังไม่มีรอบราคายาง'; ?></p></div><span class="public-live-label"><span class="public-live-dot" aria-hidden="true"></span> อัปเดตทุก 10 วินาที</span></div>
    <div class="public-lane-cards" id="publicLaneCards">
      <?php foreach ($dashboard['lanes'] as $lane): ?>
      <article class="public-lane-card" data-lane="<?php echo e($lane['lane']); ?>">
        <span class="public-lane-number"><?php echo $lane['lane']!=='' ? 'ลาน '.e($lane['lane']) : 'ไม่ระบุลาน'; ?></span>
        <div class="public-lane-weight"><strong><?php echo number_format($lane['weight'],2); ?></strong><small>กก.</small></div>
        <progress value="<?php echo $lane['weight']; ?>" max="<?php echo max(1,$dashboard['summary']['weight']); ?>" aria-label="สัดส่วนน้ำหนัก<?php echo $lane['lane']!=='' ? 'ลาน '.e($lane['lane']) : 'ไม่ระบุลาน'; ?>"></progress>
        <span class="public-lane-count"><?php echo number_format($lane['weighed']); ?> รายการชั่งแล้ว</span>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="public-lane-total"><span>รวมทุกลาน</span><div><strong id="publicLaneTotal"><?php echo number_format($dashboard['summary']['weight'],2); ?></strong> กก.</div></div>
    <p class="public-note">รวมเฉพาะน้ำหนักที่ยืนยันชั่งแล้วของรอบล่าสุด ไม่รวมรายการยกเลิก และไม่เปลี่ยนตามคำค้นหรือตัวกรองรายการ</p>
  </section>

  <section class="public-about" aria-labelledby="publicAboutTitle">
    <div><span class="public-eyebrow">ข้อมูลจริง เพื่อการทำงานที่เป็นระบบ</span>
      <h2 id="publicAboutTitle">จากลานรวบรวม<br>สู่ข้อมูลที่ติดตามได้</h2>
      <p>เชื่อมโยงรายการวางยาง น้ำหนัก และใบเสร็จในกระบวนการเดียว ช่วยให้สมาชิกติดตามความคืบหน้า
        และสหกรณ์ดูแลการรวบรวมได้อย่างเป็นระเบียบ</p><a class="public-text-link"
        href="<?php echo is_logged_in()?rts_first_allowed_page():'login.php'; ?>">เข้าสู่ระบบเจ้าหน้าที่ <span
          aria-hidden="true">↗</span></a>
    </div>
    <div class="public-history">
      <h3>ปริมาณรวบรวมที่ผ่านมา</h3>
      <p>สูงสุด 8 รอบวันที่มีรายการรับซื้อ · กิโลกรัม</p><?php if ($history): ?><div class="public-history-bars">
        <?php foreach ($history as $day): ?><div><span><?php echo e(thai_date_short_format($day['ru_date'])); ?></span>
          <div><span style="width:<?php echo max(1,round((float)$day['quantity']/$historyMax*100,2)); ?>%"></span></div>
          <strong><?php echo number_format((float)$day['quantity'],2); ?></strong>
        </div><?php endforeach; ?></div><?php else: ?><p class="public-empty">ยังไม่มีข้อมูลรับซื้อย้อนหลัง</p>
      <?php endif; ?>
    </div>
  </section>
  <script type="application/json" id="publicDashboardData">
  <?php echo json_encode($dashboard,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); ?>
  </script>
</div>
<script src="assets/js/public-overview.js?v=<?php echo filemtime(__DIR__.'/assets/js/public-overview.js'); ?>" defer>
</script>
<?php require __DIR__.'/footer.php'; ?>
<?php
require_once __DIR__.'/functions.php';
require_once __DIR__.'/public_dashboard_data.php';
require_once __DIR__.'/participant_type.php';
$db = db();
ensure_weighing_schema($db);
$member = is_string($_GET['member'] ?? null) ? trim($_GET['member']) : '';
if (mb_strlen($member) > 100) $member = '';
$dashboard = rts_public_dashboard($db,$member);
require __DIR__.'/header.php';
?>
<link rel="stylesheet" media="screen"
  href="assets/css/public-overview.css?v=<?php echo filemtime(__DIR__.'/assets/css/public-overview.css'); ?>">
<div class="public-overview" id="publicDashboard">
  <?php if (is_logged_in() && !is_admin() && !rts_current_acl()['permissions']): ?><p class="alert alert-info">
    บัญชีนี้ยังไม่มีเมนูงานที่ได้รับอนุญาต กรุณาติดต่อ admin เพื่อกำหนดสิทธิ์ คุณยังดูข้อมูลสาธารณะได้ตามปกติ</p>
  <?php endif; ?>
  <section class="public-intro" aria-labelledby="publicTitle">
    <div class="public-intro-copy">
      <h1 id="publicTitle">ติดตามการรวบรวม<br><span>ในหน้าเดียว</span></h1>

      <div class="public-entry">
        <a href="#tracking" class="btn btn-success"><i data-lucide="search" aria-hidden="true"></i>ติดตามรายการของฉัน</a>
        <a href="que.php" class="btn btn-outline-success" target="_blank" rel="noopener"><i data-lucide="tv" aria-hidden="true"></i>แสดงคิวล่าสุด</a>
        <a href="allmember.php" class="btn btn-outline-success"><i data-lucide="users" aria-hidden="true"></i>สำหรับสมาชิก</a>
        <a href="#overview" class="public-text-link">ดูภาพรวมการรวบรวม <i data-lucide="arrow-down" aria-hidden="true"></i></a>
      </div>
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
      <div>
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

    <div class="public-progress">
      <div><span>ความคืบหน้าการชั่ง</span><strong
          id="publicProgressText"><?php echo $dashboard['summary']['weighed']; ?> /
          <?php echo $dashboard['summary']['placed']; ?> รายการ</strong></div><progress id="publicProgress"
        value="<?php echo $dashboard['summary']['weighed']; ?>"
        max="<?php echo max(1,$dashboard['summary']['placed']); ?>" aria-label="ความคืบหน้าการชั่ง"></progress>
    </div>
  </section>

  <section class="public-lanes" aria-labelledby="publicLanesTitle">
    <div class="public-section-heading">
      <div>
        <h2 id="publicLanesTitle">น้ำหนักยางในแต่ละลาน</h2>
        <p id="publicLaneRound">
          <?php echo $dashboard['round'] ? 'รอบวันที่ '.e(thai_date_format($dashboard['round']['date'])) : 'ยังไม่มีรอบราคายาง'; ?>
        </p>
      </div>
      <span class="public-live-label"><span class="public-live-dot" aria-hidden="true"></span> อัปเดตทุก 10 วินาที</span>
    </div>
    <div class="public-lane-total" aria-label="น้ำหนักรวมทุกลาน">
      <span class="public-lane-total-label"><i data-lucide="layers" aria-hidden="true"></i>รวมทุกลาน</span>
      <div><strong id="publicLaneTotal"><?php echo number_format($dashboard['summary']['weight'],2); ?></strong><span>กก.</span></div>
    </div>
    <div class="public-lane-cards" id="publicLaneCards">
      <?php foreach ($dashboard['lanes'] as $lane): ?>
      <article class="public-lane-card" data-lane="<?php echo e($lane['lane']); ?>">
        <span
          class="public-lane-number"><?php echo $lane['lane']!=='' ? 'ลาน '.e($lane['lane']) : 'ไม่ระบุลาน'; ?></span>
        <div class="public-lane-weight">
          <strong><?php echo number_format($lane['weight'],2); ?></strong><small>กก.</small>
        </div>
        <progress value="<?php echo $lane['weight']; ?>" max="<?php echo max(1,$dashboard['summary']['weight']); ?>"
          aria-label="สัดส่วนน้ำหนัก<?php echo $lane['lane']!=='' ? 'ลาน '.e($lane['lane']) : 'ไม่ระบุลาน'; ?>"></progress>
        <span class="public-lane-count"><?php echo number_format($lane['weighed']); ?> รายการชั่งแล้ว</span>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="tracking" class="public-tracking" aria-labelledby="trackingTitle">
    <div class="public-section-heading">
      <div>
        <h2 id="trackingTitle">รายการของฉัน ถึงขั้นตอนไหนแล้ว?</h2>
        <p>ค้นหาด้วยชื่อหรือเลขสมาชิก · แสดง 10 รายการล่าสุดในแต่ละสถานะ</p>
      </div><span class="public-live-label"><span class="public-live-dot" aria-hidden="true"></span> อัปเดตทุก 10
        วินาที</span>
    </div>
    <div class="public-tracking-board">
      <form id="publicMemberSearch" class="public-search" method="get" action="index.php#tracking" role="search">
        <div><span class="public-search-icon"><i data-lucide="search" aria-hidden="true"></i></span><input
            id="publicMemberNumber" name="member" type="search" maxlength="100" autocomplete="off"
            placeholder="พิมพ์ชื่อ นามสกุล หรือเลขสมาชิก" value="<?php echo e($member); ?>"><button
            class="btn btn-success" type="submit"><i data-lucide="search" aria-hidden="true"></i>ค้นหา</button><button
            class="btn btn-outline-secondary" type="button" id="publicClearSearch"
            <?php echo $member === '' ? 'hidden' : ''; ?>><i data-lucide="x" aria-hidden="true"></i>ล้าง</button></div>
      </form>
      <div class="public-columns-heading">
        <p id="publicResultLabel" role="status">
          <?php echo $member !== '' ? 'คำค้น “'.e($member).'” · ' : ''; ?><?php echo number_format($dashboard['matched']); ?>
          รายการ</p>
        <button id="publicRefresh" type="button" aria-label="อัปเดตข้อมูลตอนนี้" title="อัปเดตข้อมูลตอนนี้"><i
            data-lucide="refresh-cw" aria-hidden="true"></i></button>
      </div>
      <div class="public-status-columns">
        <?php foreach (['weighed'=>['scale','รายการที่ชั่งแล้ว'],'issued'=>['receipt','สถานะรับเงิน (ออกใบเสร็จแล้ว)']] as $stage=>$column): ?>
        <section class="public-status-column" aria-labelledby="public-<?php echo e($stage); ?>-title">
          <header><span class="public-column-icon"><i data-lucide="<?php echo e($column[0]); ?>"
                aria-hidden="true"></i></span>
            <h3 id="public-<?php echo e($stage); ?>-title"><?php echo e($column[1]); ?></h3><strong
              id="public-<?php echo e($stage); ?>-count">—</strong>
          </header>
          <div id="public-<?php echo e($stage); ?>-records" class="public-records" aria-live="polite">
            <div class="public-empty"><i data-lucide="clipboard-list"
                aria-hidden="true"></i><strong>กำลังโหลดรายการ</strong></div>
          </div>
          <div class="public-column-footer"><a class="public-all-records" data-public-list="<?php echo e($stage); ?>"
              href="public_collection_list.php?<?php echo e(http_build_query(['member'=>$member,'stage'=>$stage])); ?>"><i
                data-lucide="list" aria-hidden="true"></i>ดูรายการทั้งหมด</a></div>
        </section>
        <?php endforeach; ?>
      </div>
      <dialog class="public-record-dialog" id="publicRecordDialog" aria-labelledby="publicRecordDialogTitle">
        <div class="public-record-dialog-heading"><h3 id="publicRecordDialogTitle">ข้อมูลรายการ</h3>
          <button id="publicRecordDialogClose" type="button" aria-label="ปิดข้อมูลรายการ"><i data-lucide="x" aria-hidden="true"></i></button>
        </div>
        <dl class="public-record-dialog-details">
          <div><dt>ชื่อ</dt><dd id="publicRecordName">—</dd></div>
          <div id="publicRecordParticipantType" hidden>
            <dt>ประเภทผู้ส่งยาง</dt>
            <dd><?php foreach (collection_participant_labels() as $type => $label): ?>
              <span data-participant-type="<?php echo e($type); ?>" hidden><?php echo collection_participant_icon($type, true); ?> <?php echo e($label); ?></span>
            <?php endforeach; ?></dd>
          </div>
          <div><dt>เลขสมาชิก</dt><dd id="publicRecordNumber">—</dd></div>
          <div><dt>ลาน</dt><dd id="publicRecordLane">—</dd></div>
          <div><dt>จำนวนกระสอบ</dt><dd id="publicRecordSacks">—</dd></div>
          <div><dt>สถานะ</dt><dd id="publicRecordStatus">—</dd></div>
          <div><dt>ยอดหัก</dt><dd id="publicRecordDeduction">—</dd></div>
          <div><dt>อัปเดตเมื่อ</dt><dd id="publicRecordTime">—</dd></div>
        </dl>
      </dialog>
      <p class="public-note public-receipt-note">สถานะออกใบเสร็จหมายถึงระบบบันทึกการออกใบเสร็จแล้ว
        ไม่ใช่การยืนยันว่าจ่ายเงินแล้ว</p>
      <div class="public-update">
        <span id="publicConnection" role="status">อัปเดตล่าสุด
          <time
            datetime="<?php echo e($dashboard['updated_at']); ?>"><?php echo e((new DateTimeImmutable($dashboard['updated_at']))->format('H:i:s')); ?>
          </time>
        </span>
        <span>เวลาประเทศไทย</span>
      </div>
      <noscript>
        <p class="public-note">เปิด JavaScript เพื่ออัปเดตอัตโนมัติ หรือค้นหาและโหลดหน้าใหม่เพื่อดูสถานะล่าสุด</p>
      </noscript>
    </div>

  </section>

  <script type="application/json" id="publicDashboardData">
  <?php echo json_encode($dashboard,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); ?>
  </script>
</div>
<script src="assets/js/public-overview.js?v=<?php echo filemtime(__DIR__.'/assets/js/public-overview.js'); ?>" defer>
</script>
<?php require __DIR__.'/footer.php'; ?>

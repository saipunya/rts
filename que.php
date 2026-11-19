<?php
require_once __DIR__.'/functions.php';
require_once __DIR__.'/public_dashboard_data.php';
require_once __DIR__.'/participant_type.php';
$db = db();
ensure_weighing_schema($db);
$dashboard = rts_public_dashboard($db);
$weighedQueue = rts_public_dashboard($db,'','weighed');
$issuedQueue = rts_public_dashboard($db,'','issued');
$roundLabel = $dashboard['round'] ? 'รอบวันที่ '.thai_date_format($dashboard['round']['date']) : 'ยังไม่มีรอบราคายาง';
$updatedAt = $dashboard['updated_at'];
$participantLabels = collection_participant_labels();
$queueColumns = [
    'weighed'=>['title'=>'ชั่งแล้ว','description'=>'รายการที่ชั่งน้ำหนักแล้วและยังไม่ออกใบเสร็จ','data'=>$weighedQueue],
    'issued'=>['title'=>'รอรับเงิน (ออกใบเสร็จแล้ว)','description'=>'รายการที่ระบบบันทึกการออกใบเสร็จแล้ว','data'=>$issuedQueue],
];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#173d2d">
  <title>คิวล่าสุด · RTS</title>
  <link rel="stylesheet" href="assets/css/participant-type.css?v=<?php echo filemtime(__DIR__.'/assets/css/participant-type.css'); ?>">
  <link rel="stylesheet" href="assets/css/public-queue.css?v=<?php echo filemtime(__DIR__.'/assets/css/public-queue.css'); ?>">
</head>
<body class="public-queue-page">
  <header class="queue-topbar">
    <div class="queue-brand"><span class="queue-brand-mark" aria-hidden="true">RTS</span>
      <div><h1>คิวล่าสุด</h1><p id="queueRoundLabel"><?php echo e($roundLabel); ?></p></div>
    </div>
    <div class="queue-live"><span class="queue-live-dot" aria-hidden="true"></span>
      <span id="queueUpdated">อัปเดตล่าสุด <?php echo e((new DateTimeImmutable($updatedAt))->format('H:i:s')); ?> น.</span>
    </div>
  </header>
  <main class="queue-columns" id="queueColumns">
    <?php foreach ($queueColumns as $key=>$column): $data=$column['data']; ?>
    <section class="queue-column queue-column-<?php echo e($key); ?>" data-queue-column="<?php echo e($key); ?>" aria-labelledby="queue-<?php echo e($key); ?>-title">
      <header class="queue-column-heading">
        <div><h2 id="queue-<?php echo e($key); ?>-title"><?php echo e($column['title']); ?></h2>
          <p><?php echo e($column['description']); ?></p></div>
        <strong class="queue-count" data-queue-count><?php echo number_format($data['matched']); ?></strong>
      </header>
      <p class="queue-latest-note">แสดง 10 รายการล่าสุด · ใหม่สุดอยู่บน</p>
      <div class="queue-records" data-queue-records aria-live="polite" style="grid-template-rows:repeat(<?php echo max(1,min(10,count($data['records']))); ?>,minmax(0,1fr))">
        <?php if ($data['records']): foreach (array_slice($data['records'],0,10) as $index=>$record): ?>
        <article class="queue-record">
          <span class="queue-record-rank"><?php echo $index + 1; ?></span>
          <div class="queue-record-main">
            <div class="queue-record-name">
              <?php echo collection_participant_icon($record['participant_type'] ?? null); ?>
              <strong><?php echo e($record['name']); ?></strong>
            </div>
            <span>เลขสมาชิก <?php echo e($record['member_number'] ?: '—'); ?> <span aria-hidden="true">·</span>
              ลาน <?php echo e($record['lane'] ?: '—'); ?> <span aria-hidden="true">·</span>
              <?php echo ($record['sacks'] ?? null) === null ? 'ไม่ระบุจำนวนกระสอบ' : number_format((float)$record['sacks'],2).' กระสอบ'; ?></span>
          </div>
          <time datetime="<?php echo e($record['activity_at']); ?>"><?php echo e((new DateTimeImmutable($record['activity_at']))->format('H:i')); ?> น.</time>
        </article>
        <?php endforeach; else: ?>
        <div class="queue-empty">ยังไม่มีรายการในคิวนี้</div>
        <?php endif; ?>
      </div>
    </section>
    <?php endforeach; ?>
  </main>
  <footer class="queue-footer">
    <div class="queue-type-legend">
      <?php foreach ($participantLabels as $type => $label): ?>
        <span><?php echo collection_participant_icon($type, true); ?><?php echo e($label); ?></span>
      <?php endforeach; ?>
    </div>
    <span>ข้อมูลสำหรับติดตามคิวเท่านั้น · การออกใบเสร็จไม่ใช่การยืนยันว่าจ่ายเงินแล้ว</span>
    <span>ระบบอัปเดตอัตโนมัติทุก 10 วินาที</span>
  </footer>
  <script type="application/json" id="queueInitialData"><?php echo json_encode([
      'round'=>$dashboard['round'],
      'updated_at'=>$updatedAt,
      'participant_labels'=>$participantLabels,
      'columns'=>[
          'weighed'=>['matched'=>$weighedQueue['matched'],'records'=>$weighedQueue['records']],
          'issued'=>['matched'=>$issuedQueue['matched'],'records'=>$issuedQueue['records']],
      ],
  ],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); ?></script>
  <script src="assets/js/public-queue.js?v=<?php echo filemtime(__DIR__.'/assets/js/public-queue.js'); ?>" defer></script>
</body>
</html>

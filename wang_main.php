<?php
require_once 'functions.php';
require_login();

// Load counts and bags for the selected collection round.
$db = db();
ensure_weighing_schema($db);
ensure_wang_collection_table($db);
$round = latest_priced_collection_round($db);
$roundBarReadOnly = true;
$roundId = (int)($round['round_id'] ?? 0);
$currentWangDate = placement_entry_date();
$workflowCounts = weighing_window_counts($db, $round);
$lane_data = [];
for ($i = 1; $i <= 4; $i++) {
    $lan = (string)$i;
    $stm = $db->prepare('SELECT COUNT(*) AS c, COALESCE(SUM(wang_sack),0) AS sacks FROM (SELECT * FROM tbl_wangyang WHERE LOWER(TRIM(COALESCE(record_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564) AND LOWER(TRIM(COALESCE(wang_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564)) tbl_wangyang WHERE wang_lan = ? AND wang_date = ?');
    if ($stm) {
        $stm->bind_param('ss', $lan, $currentWangDate);
        $stm->execute();
        $row = $stm->get_result()->fetch_assoc();
        $lane_data[$lan] = [
            'count' => (int)($row['c'] ?? 0),
            'sacks' => (float)($row['sacks'] ?? 0),
            'date' => $currentWangDate,
        ];
        $stm->close();
    } else {
        $lane_data[$lan] = ['count' => 0, 'sacks' => 0, 'date' => $currentWangDate];
    }
}
$cu = current_user();

function format_thai_date_short(string $date): string {
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . ((int)date('Y', $ts) + 543);
}

$overviewSummary = [
    'entries' => 0,
    'sacks' => 0.0,
    'lanes' => 0,
    'current_date' => $currentWangDate,
];

$stm = $db->prepare('SELECT COUNT(*) AS entries, COALESCE(SUM(wang_sack), 0) AS sacks, COUNT(DISTINCT wang_lan) AS lanes FROM (SELECT * FROM tbl_wangyang WHERE LOWER(TRIM(COALESCE(record_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564) AND LOWER(TRIM(COALESCE(wang_status,0x6e6f726d616c))) NOT IN (0x63616e63656c6c6564,0x63616e63656c6564,0x64656c65746564)) tbl_wangyang WHERE wang_date = ?');
if ($stm) {
    $stm->bind_param('s', $currentWangDate);
    $stm->execute();
    $row = $stm->get_result()->fetch_assoc();
    $overviewSummary['entries'] = (int)($row['entries'] ?? 0);
    $overviewSummary['sacks'] = (float)($row['sacks'] ?? 0);
    $overviewSummary['lanes'] = (int)($row['lanes'] ?? 0);
    $stm->close();
}

// Use the same authenticated route and the same queries for initial and live data.
if (($_GET['ajax'] ?? '') === 'summary') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode([
        'summary'=>$overviewSummary, 'lanes'=>$lane_data, 'workflow'=>$workflowCounts,
        'date_label'=>format_thai_date_short($currentWangDate), 'round_id'=>$roundId,
        'round_label'=>$round ? collection_round_label($round) : 'ยังไม่มีราคายาง',
        'round_description'=>$round ? 'วันที่ราคายาง / วันชั่ง '.thai_date_format($round['weigh_date']).' · เรียกเฉพาะรายการวางยาง '.thai_date_format($round['lay_start']).' – '.thai_date_format($round['lay_end']) : 'บันทึกวางยางได้ตามปกติ เมื่อมีราคายางล่าสุดจึงจะเรียกรายการย้อนหลัง 2 วันมาชั่งได้',
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
header('Cache-Control: no-store, private');

$lane_colors = [
    '1' => ['bg' => '#dcfce7', 'border' => '#16a34a', 'badge' => '#16a34a', 'icon_bg' => '#bbf7d0', 'text' => '#14532d'],
    '2' => ['bg' => '#d1fae5', 'border' => '#059669', 'badge' => '#059669', 'icon_bg' => '#a7f3d0', 'text' => '#064e3b'],
    '3' => ['bg' => '#ccfbf1', 'border' => '#0d9488', 'badge' => '#0d9488', 'icon_bg' => '#99f6e4', 'text' => '#134e4a'],
    '4' => ['bg' => '#cffafe', 'border' => '#0891b2', 'badge' => '#0891b2', 'icon_bg' => '#a5f3fc', 'text' => '#164e63'],
];
require __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="assets/css/wang-main.css?v=<?php echo filemtime(__DIR__ . '/assets/css/wang-main.css'); ?>">
<section class="wang-main" id="wangMainLive">
  <?php require __DIR__ . '/collection_round_bar.php'; ?>
  <header class="wang-heading">
    <div><span class="wang-eyebrow"><i data-lucide="package-check" aria-hidden="true"></i>งานรวบรวมยาง</span><h1>วางยาง — เลือกลาน</h1><p>เลือกลานเพื่อบันทึกวางยาง และติดตามรายการวางยางของวันนี้</p></div>
    <nav class="wang-actions" aria-label="จัดการข้อมูลวางยาง">
      <a href="wang_summary.php" class="btn btn-outline-success"><i data-lucide="bar-chart-3" aria-hidden="true"></i>สรุปวางยาง</a>
      <a href="manage_wang.php" class="btn btn-outline-secondary"><i data-lucide="list" aria-hidden="true"></i>จัดการรายการ</a>
      <?php if (is_admin()): ?><a href="export_wang.php" class="btn btn-outline-success"><i data-lucide="download" aria-hidden="true"></i>ส่งออก</a><?php endif; ?>
    </nav>
  </header>
    <section class="workflow-links" aria-label="สถานะงานในรอบนี้">
      <?php foreach (['pending_weighing'=>['รอชั่งน้ำหนัก','pending'], 'pending_deductions'=>['ชั่งแล้ว รออนุมัติ / ยอดหัก','deductions'], 'completed'=>['บันทึกยอดหักแล้ว','completed']] as $key=>$item): ?>
        <a href="weigh_wang.php?<?php echo e(http_build_query(['round'=>$roundId,'status'=>$item[1]])); ?>" class="btn btn-outline-success" data-workflow="<?php echo e($key); ?>" data-workflow-status="<?php echo e($item[1]); ?>"><?php echo e($item[0]); ?> <strong><?php echo $workflowCounts[$key]; ?></strong> รายการ</a>
      <?php endforeach; ?>
    </section>

    <div class="date-badge">
      <i data-lucide="calendar-days" aria-hidden="true"></i>
      <span id="wangCurrentDate">วันที่ปัจจุบัน: <?php echo e(format_thai_date_short($overviewSummary['current_date'])); ?></span>
    </div>

    <div class="summary-strip">
      <div class="summary-item">
        <div class="summary-value" data-summary="sacks"><?php echo number_format($overviewSummary['sacks'], 2); ?></div>
        <div class="summary-label">กระสอบรวม</div>
      </div>
      <div class="summary-item">
        <div class="summary-value" data-summary="entries"><?php echo number_format($overviewSummary['entries']); ?></div>
        <div class="summary-label">รายการ</div>
      </div>
      <div class="summary-item">
        <div class="summary-value" data-summary="lanes"><?php echo number_format($overviewSummary['lanes']); ?></div>
        <div class="summary-label">ลานที่มีข้อมูล</div>
      </div>
    </div>

    <div class="lanes-grid">
      <?php foreach ($lane_data as $lan => $data):
        $c = $lane_colors[$lan];
      ?>
      <a href="create_rubber.php?lane=<?php echo e($lan); ?>" class="lane-card text-decoration-none"
        style="background:<?php echo e($c['bg']); ?>;border-color:<?php echo e($c['border']); ?>;color:<?php echo e($c['text']); ?>;">
        <span class="lane-badge" style="background:<?php echo e($c['icon_bg']); ?>;color:<?php echo e($c['badge']); ?>;">
          <?php echo e($lan); ?>
        </span>
        <span class="lane-label">ลาน <?php echo e($lan); ?></span>
        <span class="lane-stat" data-lane="<?php echo e($lan); ?>">
          <?php if ((int)$data['count'] > 0): ?>
            <?php echo number_format($data['sacks'], 2); ?> กระสอบ · <?php echo number_format($data['count']); ?> รายการ
          <?php else: ?>
            วันนี้ยังไม่มีข้อมูล
          <?php endif; ?>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
<p id="wangLiveStatus" class="small text-muted mt-3 mb-0" role="status" aria-live="polite">อัปเดตอัตโนมัติทุก 5 วินาที</p>
</section>
<script src="assets/js/wang-main-live.js?v=<?php echo filemtime(__DIR__.'/assets/js/wang-main-live.js'); ?>" defer></script>
<?php require __DIR__ . '/footer.php'; ?>

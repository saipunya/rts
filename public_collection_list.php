<?php
require_once __DIR__.'/functions.php';
require_once __DIR__.'/public_dashboard_data.php';
$db = db();
ensure_weighing_schema($db);
$member = is_string($_GET['member'] ?? null) ? trim($_GET['member']) : '';
if (mb_strlen($member)>100) $member='';
$stage = is_string($_GET['stage'] ?? null) ? $_GET['stage'] : 'all';
$page = filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT) ?: 1;
$dashboard = rts_public_dashboard($db,$member,$stage,$page);
$stage = $dashboard['stage'];
$stages=['all'=>'ทั้งหมด','waiting'=>'วางยางแล้ว','weighed'=>'ชั่งแล้ว','ready'=>'รอออกใบเสร็จ','issued'=>'ออกใบเสร็จแล้ว'];
$listUrl = static function(string $selected,int $number=1) use ($member): string {
    return 'public_collection_list.php?'.http_build_query(['stage'=>$selected,'member'=>$member,'page'=>$number]);
};
require __DIR__.'/header.php';
?>
<link rel="stylesheet" media="screen" href="assets/css/public-overview.css?v=<?php echo filemtime(__DIR__.'/assets/css/public-overview.css'); ?>">
<div class="public-overview public-list-page" id="publicListPage">
  <div class="public-section-heading"><div><span class="public-eyebrow">ติดตามการรวบรวมยาง · ทุกลาน</span><h1>รายการทั้งหมดตามสถานะ</h1><p id="publicListRound"><?php echo $dashboard['round'] ? 'รอบวันที่ '.e(thai_date_format($dashboard['round']['date'])) : 'ยังไม่มีรอบราคายาง'; ?> · แสดงหน้าละ 20 รายการ</p></div><a href="index.php#tracking" class="btn btn-outline-success">กลับหน้าหลัก</a></div>
  <p class="public-live-label"><span class="public-live-dot" aria-hidden="true"></span> ตรวจข้อมูลทุก 10 วินาที · หน้า 2 เป็นต้นไปจะแจ้งเตือนก่อนเปลี่ยนรายการ</p>
  <div class="public-board">
    <form class="public-search" method="get" action="public_collection_list.php" role="search"><input type="hidden" name="stage" value="<?php echo e($stage); ?>"><label for="publicListSearch">ค้นหาชื่อ นามสกุล หรือเลขสมาชิก</label><div><span class="public-search-icon"><i data-lucide="search" aria-hidden="true"></i></span><input type="search" id="publicListSearch" name="member" maxlength="100" value="<?php echo e($member); ?>" placeholder="พิมพ์ชื่อหรือเลขสมาชิก"><button type="submit" class="btn btn-success">ค้นหา</button><?php if ($member!==''): ?><a href="public_collection_list.php?<?php echo e(http_build_query(['stage'=>$stage])); ?>" class="btn btn-outline-secondary">ล้าง</a><?php endif; ?></div></form>
    <nav class="public-tabs" aria-label="เลือกสถานะรายการ"><?php foreach($stages as $key=>$label): ?><a href="<?php echo e($listUrl($key)); ?>" class="<?php echo $stage===$key?'is-active':''; ?>" <?php echo $stage===$key?'aria-current="page"':''; ?>><?php echo e($label); ?></a><?php endforeach; ?></nav>
    <div class="public-board-meta"><p id="publicListResult"><?php echo e($stages[$stage]); ?><?php echo $member!==''?' · คำค้น “'.e($member).'”':''; ?> · <?php echo number_format($dashboard['matched']); ?> รายการ · หน้า <?php echo $dashboard['page']; ?> / <?php echo $dashboard['total_pages']; ?></p><a id="publicListRefresh" class="public-text-link" href="<?php echo e($listUrl($stage,$dashboard['page'])); ?>" aria-label="อัปเดตข้อมูล">อัปเดต</a></div>
    <div id="publicListChanges" class="public-list-changes" role="status" hidden><span>มีข้อมูลเปลี่ยนแปลง รายการที่กำลังดูยังคงเดิม</span><button id="publicListApply" type="button" class="btn btn-outline-success">แสดงข้อมูลล่าสุด</button></div>
    <div class="public-records" id="publicListRecords"><?php require __DIR__.'/partials/public-status-records.php'; ?></div>
    <div id="publicListPagination"><?php require __DIR__.'/partials/public-list-pagination.php'; ?></div>
    <div class="public-update"><span id="publicListConnection" role="status">ข้อมูล ณ <?php echo e((new DateTimeImmutable($dashboard['updated_at']))->format('d/m/Y H:i:s')); ?></span><span>เวลาประเทศไทย</span></div>
  </div>
  <p class="public-note">แสดงข้อมูลครบทุกหน้าของสถานะที่เลือกในรอบล่าสุด ไม่รวมรายการยกเลิก · “ชั่งแล้ว” รวมทุกรายการที่ผ่านการชั่ง แม้ดำเนินการขั้นตอนต่อไปแล้ว · สถานะไม่ใช่การยืนยันจ่ายเงิน</p>
  <script type="application/json" id="publicListData"><?php echo json_encode($dashboard,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); ?></script>
</div>
<script src="assets/js/public-collection-list.js?v=<?php echo filemtime(__DIR__.'/assets/js/public-collection-list.js'); ?>" defer></script>
<?php require __DIR__.'/footer.php'; ?>

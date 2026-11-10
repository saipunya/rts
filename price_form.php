<?php
require_once 'functions.php';
require_admin();
$db = db(); ensure_weighing_schema($db);

$action = ($_GET['action'] ?? '') === 'edit' ? 'edit' : 'create';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($action === 'edit' && $id <= 0) {
    header('Location: prices.php?msg=' . urlencode('ไม่พบรายการราคาที่ต้องการแก้ไข'));
    exit;
}

// default values
$pr = [
    'pr_id' => 0,
    'pr_year' => date('Y') + 543, // Thai year default
    'pr_date' => (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d'),
    'pr_number' => '',
    'pr_price' => '',
];

if ($action === 'edit' && $id > 0) {
    $stmt = $mysqli->prepare("SELECT pr_id, pr_year, pr_date, pr_number, pr_price, collection_round_id FROM tbl_price WHERE pr_id = ? LIMIT 1");
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if ($row) {
        $pr = $row;
    } else {
        header('Location: prices.php?msg=' . urlencode('ไม่พบข้อมูล')); exit;
    }
}

$historyPerPage = 15;
$historyTotal = (int)$db->query('SELECT COUNT(*) FROM tbl_price')->fetch_row()[0];
$historyPages = max(1, (int)ceil($historyTotal / $historyPerPage));
$historyPage = max(1, min((int)($_GET['history_page'] ?? 1), $historyPages));
$historyOffset = ($historyPage - 1) * $historyPerPage;
$historyStmt = $db->prepare('SELECT pr_id, pr_date, pr_number, pr_price, pr_saveby FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT ? OFFSET ?');
$historyStmt->bind_param('ii', $historyPerPage, $historyOffset);
$historyStmt->execute(); $priceHistory = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC); $historyStmt->close();

$latestPrice = latest_rubber_price($db);
$placementDate = new DateTimeImmutable($pr['pr_date'], new DateTimeZone('Asia/Bangkok'));
$windowStart = $placementDate->modify('-2 days')->format('Y-m-d');
$windowEnd = $placementDate->modify('-1 day')->format('Y-m-d');
$priceDateTs = strtotime((string)($pr['pr_date'] ?? ''));
if ($priceDateTs !== false) {
    $pr['pr_year'] = (int)date('Y', $priceDateTs) + 543;
}

?>
<?php include __DIR__.'/header.php'; ?>
<link rel="stylesheet" href="assets/css/prices.css?v=<?php echo filemtime(__DIR__.'/assets/css/prices.css'); ?>">
<div class="container price-form-shell my-4">
    <section class="price-hero p-3 p-md-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="price-badge">
                    <i data-lucide="coins" aria-hidden="true"></i>
                </span>
                <div>
                    <div class="text-uppercase text-success fw-semibold small mb-1">ราคายาง</div>
                    <h1 class="h3 fw-bold mb-1 text-success-emphasis"><?php echo ($action === 'edit') ? 'แก้ไขราคายาง' : 'กำหนดราคายาง'; ?></h1>
                    <div class="text-success">กำหนดราคา วันที่ชั่ง และเลขที่ประกาศในหน้าเดียว</div>
                </div>
            </div>
            <a href="prices.php" class="btn btn-outline-success">
                <i data-lucide="arrow-left" class="me-1" aria-hidden="true"></i>กลับหน้าราคา
            </a>
        </div>
    </section>

    <div class="price-current" aria-label="ราคายางล่าสุด">
      <div><span>ราคาใช้งานล่าสุด</span><strong><?php echo $latestPrice ? number_format((float)$latestPrice['pr_price'],2) : '—'; ?><small>บาท / กก.</small></strong></div>
      <div><span>วันที่ราคายาง / วันชั่ง</span><strong class="price-current-date"><?php echo $latestPrice ? e(thai_date_format($latestPrice['pr_date'])) : 'ยังไม่มีประกาศราคา'; ?></strong></div>
    </div>
    <?php if (!empty($_GET['msg'])): ?><div class="alert alert-warning" role="alert"><?php echo e($_GET['msg']); ?></div><?php endif; ?>
    <section class="price-panel p-3 p-md-4">
        <form method="post" action="price_save.php">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
            <input type="hidden" name="pr_id" value="<?php echo (int)$pr['pr_id']; ?>">

            <p class="text-muted small">วันที่ราคายางคือวันที่ขาย / ชั่งยาง ระบบจะเรียกชั่งเฉพาะรายการวางยางย้อนหลัง 2 วันก่อนวันที่ราคาล่าสุด โดยไม่ต้องตั้งค่ารอบแยก</p>
            <div class="price-window-preview" role="status"><strong>รายการวางยางที่เรียกชั่งได้</strong><span><span id="priceWindowStart"><?php echo e(thai_date_format($windowStart)); ?></span> – <span id="priceWindowEnd"><?php echo e(thai_date_format($windowEnd)); ?></span></span><small>ย้อนหลัง 2 วัน ไม่รวมวันที่ราคายาง · รายการที่ชั่งแล้วใช้ราคาที่บันทึกตอนชั่ง</small></div>
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="pr_year">
                        <i data-lucide="calendar-range" class="me-1" aria-hidden="true"></i>ปี (พ.ศ.)
                    </label>
                    <input type="number" name="pr_year" id="pr_year" class="form-control" readonly aria-describedby="priceYearHelp" value="<?php echo htmlspecialchars((string)$pr['pr_year']); ?>" required>
                    <small id="priceYearHelp" class="form-text">คำนวณจากวันที่ราคายาง</small>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="pr_date">
                        <i data-lucide="calendar-days" class="me-1" aria-hidden="true"></i>วันที่ราคายาง / วันชั่ง
                    </label>
                    <input type="date" name="pr_date" id="pr_date" class="form-control" value="<?php echo htmlspecialchars($pr['pr_date']); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="pr_number">
                        <i data-lucide="hash" class="me-1" aria-hidden="true"></i>เลขที่ประกาศราคา
                    </label>
                    <input type="text" name="pr_number" id="pr_number" class="form-control" value="<?php echo htmlspecialchars($pr['pr_number']); ?>" placeholder="เช่น ประกาศที่ 1" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="pr_price">
                        <i data-lucide="circle-dollar-sign" class="me-1" aria-hidden="true"></i>ราคา (บาท / กก.)
                    </label>
                    <input type="number" name="pr_price" id="pr_price" class="form-control" inputmode="decimal" min="0.01" step="0.01" placeholder="เช่น 56.50" value="<?php echo htmlspecialchars($pr['pr_price']); ?>" required>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mt-4">
                <a href="prices.php" class="btn btn-outline-secondary">
                    <i data-lucide="x" class="me-1" aria-hidden="true"></i>ยกเลิก
                </a>
                <button type="submit" class="btn btn-success">
                    <i data-lucide="save" class="me-1" aria-hidden="true"></i><?php echo ($action === 'edit') ? 'บันทึกการแก้ไข' : 'บันทึก'; ?>
                </button>
            </div>
        </form>
    </section>
    <section class="price-panel mt-4 overflow-hidden" aria-labelledby="priceHistoryHeading">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-bottom">
        <div><h2 class="h5 fw-bold text-success-emphasis mb-1" id="priceHistoryHeading">ราคายางที่บันทึกไว้</h2><span class="small text-muted"><?php echo number_format($historyTotal); ?> รายการ · เรียงวันที่ล่าสุดก่อน</span></div>
        <a class="btn btn-sm btn-outline-success" href="prices.php">ดูราคาทั้งหมด</a>
      </div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0 price-history-table"><thead class="table-light"><tr><th>วันที่ราคายาง</th><th class="d-none d-sm-table-cell">เลขที่ประกาศ</th><th class="text-end">บาท/กก.</th><th class="d-none d-md-table-cell">ผู้บันทึก</th><th class="text-end">จัดการ</th></tr></thead><tbody>
        <?php foreach ($priceHistory as $history): ?><tr<?php echo $action === 'edit' && (int)$history['pr_id'] === $id ? ' class="table-success" aria-current="true"' : ''; ?>>
          <td><?php echo e(thai_date_short_format($history['pr_date'])); ?><small class="d-block d-sm-none text-muted mt-1">ประกาศ <?php echo e($history['pr_number']); ?></small></td>
          <td class="d-none d-sm-table-cell"><?php echo e($history['pr_number']); ?></td><td class="text-end fw-bold text-success"><?php echo number_format((float)$history['pr_price'],2); ?></td><td class="d-none d-md-table-cell"><?php echo e($history['pr_saveby']); ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-success" href="price_form.php?<?php echo e(http_build_query(['action'=>'edit','id'=>$history['pr_id'],'history_page'=>$historyPage])); ?>" aria-label="แก้ไขราคาวันที่ <?php echo e(thai_date_format($history['pr_date'])); ?>"><i data-lucide="pencil" aria-hidden="true"></i><span class="d-none d-sm-inline">แก้ไข</span></a></td>
        </tr><?php endforeach; ?>
        <?php if (!$priceHistory): ?><tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีรายการราคายางก่อนหน้า</td></tr><?php endif; ?>
      </tbody></table></div>
      <?php if ($historyPages > 1): ?><nav class="d-flex flex-wrap align-items-center justify-content-center gap-3 p-3 border-top" aria-label="หน้ารายการราคายาง">
        <?php if ($historyPage > 1): ?><a class="btn btn-sm btn-outline-secondary" href="?<?php echo e(http_build_query(['action'=>$action,'id'=>$id,'history_page'=>$historyPage-1])); ?>#priceHistoryHeading">ก่อนหน้า</a><?php endif; ?>
        <span class="small text-muted">หน้า <?php echo $historyPage; ?> / <?php echo $historyPages; ?></span>
        <?php if ($historyPage < $historyPages): ?><a class="btn btn-sm btn-outline-secondary" href="?<?php echo e(http_build_query(['action'=>$action,'id'=>$id,'history_page'=>$historyPage+1])); ?>#priceHistoryHeading">ถัดไป</a><?php endif; ?>
      </nav><?php endif; ?>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('pr_date');
    const yearInput = document.getElementById('pr_year');
    if (!dateInput || !yearInput) return;

    const dateFormat = new Intl.DateTimeFormat('th-TH',{day:'numeric',month:'long',year:'numeric',timeZone:'Asia/Bangkok'});
    function syncThaiYear() {
        const match = /^(\d{4})-\d{2}-\d{2}$/.exec(dateInput.value || '');
        if (match) {
            yearInput.value = String(Number(match[1]) + 543);
            const date = new Date(dateInput.value + 'T00:00:00+07:00');
            if (Number.isNaN(date.getTime())) return;
            const start = new Date(date); start.setUTCDate(start.getUTCDate()-2);
            const end = new Date(date); end.setUTCDate(end.getUTCDate()-1);
            document.getElementById('priceWindowStart').textContent = dateFormat.format(start);
            document.getElementById('priceWindowEnd').textContent = dateFormat.format(end);
        }
    }

    dateInput.addEventListener('change', syncThaiYear);
    syncThaiYear();
});
</script>

<?php include 'footer.php'; ?>

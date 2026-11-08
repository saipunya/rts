<?php
require_once __DIR__ . '/functions.php';
require_login();
$db = db(); ensure_weighing_schema($db);
$search = trim((string)($_GET['search'] ?? ''));
$lane = (string)($_GET['lan'] ?? 'all');
if (!in_array($lane, ['all','1','2','3','4'], true)) $lane = 'all';
$result = issued_rubber_receipts($db, $search, $lane, (int)($_GET['page'] ?? 1));
require __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="assets/css/printed-receipts.css?v=<?php echo filemtime(__DIR__ . '/assets/css/printed-receipts.css'); ?>">
<section class="printed-receipts">
  <div class="receipt-heading"><div><h1>พิมพ์ใบเสร็จแล้ว</h1><p>เรียงตามเวลาอนุมัติไม่มียอดหัก / บันทึกยอดหักเสร็จ ล่าสุดอยู่บนสุด</p></div><a class="btn btn-outline-success" href="approve_weighing.php?tab=pending"><i data-lucide="printer" aria-hidden="true"></i>อนุมัติหลังชั่ง</a></div>
  <form method="get" class="receipt-filters" id="receiptFilters">
    <div class="receipt-search"><label for="receipt-search">ค้นหารายการ</label><input class="form-control" type="search" id="receipt-search" name="search" value="<?php echo e($search); ?>" placeholder="ชื่อ เลขสมาชิก กลุ่ม หรือหมายเหตุ"></div>
    <div><label for="receipt-lane">ลาน</label><select class="form-select" id="receipt-lane" name="lan">
      <?php foreach (['all'=>'ทุกลาน','1'=>'ลาน 1','2'=>'ลาน 2','3'=>'ลาน 3','4'=>'ลาน 4'] as $value=>$label): ?><option value="<?php echo e($value); ?>" <?php echo $lane === (string)$value ? 'selected' : ''; ?>><?php echo e($label); ?></option><?php endforeach; ?>
    </select></div><button class="btn btn-success"><i data-lucide="search" aria-hidden="true"></i>ค้นหา</button>
    <a class="btn btn-outline-secondary" id="clearReceiptFilters" href="printed_receipts.php" <?php echo $search === '' && $lane === 'all' ? 'hidden' : ''; ?>>ล้างตัวกรอง</a>
  </form>
  <?php if (is_admin()): ?>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-success" data-receipt-export="print" target="_blank" rel="noopener" href="export_printed_receipts.php?<?php echo e(http_build_query(['search'=>$search,'lan'=>$lane,'format'=>'print'])); ?>"><i data-lucide="printer" aria-hidden="true"></i>พิมพ์รายการรอบล่าสุด</a>
    <a class="btn btn-success" data-receipt-export="excel" href="export_printed_receipts.php?<?php echo e(http_build_query(['search'=>$search,'lan'=>$lane,'format'=>'excel'])); ?>"><i data-lucide="file-spreadsheet" aria-hidden="true"></i>ส่งออก Excel</a>
  </div>
  <?php endif; ?>
  <p id="receiptSearchStatus" class="small text-muted" role="status" aria-live="polite"></p>
  <div id="receiptSearchResults">
  <p class="receipt-total" role="status" aria-live="polite"><?php echo number_format($result['total']); ?> รายการ<?php if ($result['total']): ?> · หน้า <?php echo $result['page']; ?> / <?php echo $result['total_pages']; ?><?php endif; ?></p>
  <div class="table-responsive receipt-table"><table class="table table-hover align-middle mb-0"><thead><tr><th>รายการ</th><th>ชื่อผู้ส่งยาง</th><th class="text-end">น้ำหนัก (กก.)</th><th class="text-end">ยอดหัก (บาท)</th><th class="text-end">รับสุทธิ (บาท)</th><th>จัดการ</th></tr></thead><tbody>
  <?php foreach ($result['rows'] as $row):
    $hasDeductions = (float)$row['ru_expend'] > 0;
    $deductionLabel = $hasDeductions ? 'มียอดหัก' : 'ไม่มียอดหัก';
    $details = [
        'id'=>(int)$row['ru_id'],
        'name'=>collection_display_name($row['ru_fullname'], $row['wang_note']),
        'round'=>$row['collection_weigh_date'] ? 'รอบขายยาง ' . thai_date_format($row['collection_weigh_date']) : 'รายการเดิม (ยังไม่ระบุรอบ)',
        'lane'=>'ลาน ' . $row['ru_lan'],
        'sacks'=>$row['wang_sack'] !== null ? number_format((float)$row['wang_sack'],2) . ' กระสอบ' : 'ไม่มีข้อมูล',
        'issuedAt'=>thai_datetime_format($row['issued_at']),
        'issuedBy'=>$row['issued_by'],
    ];
  ?>
    <tr><td><strong>#<?php echo (int)$row['ru_id']; ?></strong><small>รอบ <?php echo e(thai_date_short_format($row['collection_weigh_date'] ?? $row['ru_date'])); ?></small></td>
    <td><strong><?php echo e(collection_display_name($row['ru_fullname'], $row['wang_note'])); ?></strong> <span class="receipt-deduction-badge <?php echo $hasDeductions ? 'has-deductions' : 'no-deductions'; ?>" title="<?php echo e($deductionLabel); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="color:<?php echo $hasDeductions ? '#d9480f' : '#15803d'; ?>;stroke:<?php echo $hasDeductions ? '#d9480f' : '#15803d'; ?>;width:22px;height:22px" fill="none" stroke="<?php echo $hasDeductions ? '#d9480f' : '#15803d'; ?>" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><?php if ($hasDeductions): ?><path d="M8 12h8"/><?php else: ?><path d="m8 12 3 3 5-6"/><?php endif; ?></svg><span class="visually-hidden"><?php echo e($deductionLabel); ?></span></span><small>เลขที่ <?php echo e($row['ru_number']); ?> · กลุ่ม <?php echo e($row['ru_group']); ?></small></td>
    <td class="text-end"><?php echo number_format((float)$row['ru_quantity'],2); ?></td><td class="text-end"><?php echo number_format((float)$row['ru_expend'],2); ?></td><td class="text-end fw-semibold text-success"><?php echo number_format((float)$row['ru_netvalue'],2); ?></td>
    <td><div class="receipt-row-actions"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#receiptDetailsModal" data-receipt="<?php echo e(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); ?>" aria-haspopup="dialog"><i data-lucide="info" aria-hidden="true"></i>รายละเอียด</button><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="export_rubber_pdf.php?ru_id=<?php echo (int)$row['ru_id']; ?>"><i data-lucide="printer" aria-hidden="true"></i>พิมพ์ซ้ำ</a></div></td></tr>
  <?php endforeach; ?>
  <?php if (!$result['rows']): ?><tr><td colspan="6" class="text-center text-muted py-5"><?php echo $search !== '' || $lane !== 'all' ? 'ไม่พบรายการตามตัวกรอง' : 'ยังไม่มีรายการที่พิมพ์ใบเสร็จแล้ว'; ?></td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if ($result['total_pages'] > 1): ?><nav class="receipt-pagination" aria-label="หน้ารายการใบเสร็จ">
    <?php if ($result['page'] > 1): ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['search'=>$search,'lan'=>$lane,'page'=>$result['page']-1])); ?>"><i data-lucide="chevron-left" aria-hidden="true"></i>ก่อนหน้า</a><?php endif; ?>
    <span>หน้า <?php echo $result['page']; ?> / <?php echo $result['total_pages']; ?></span>
    <?php if ($result['page'] < $result['total_pages']): ?><a class="btn btn-outline-secondary" href="?<?php echo e(http_build_query(['search'=>$search,'lan'=>$lane,'page'=>$result['page']+1])); ?>">ถัดไป<i data-lucide="chevron-right" aria-hidden="true"></i></a><?php endif; ?>
  </nav><?php endif; ?>
  </div>
  <div class="modal fade" id="receiptDetailsModal" tabindex="-1" aria-labelledby="receiptDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content receipt-details">
      <div class="modal-header"><h2 class="modal-title fs-5" id="receiptDetailsTitle">รายละเอียดใบเสร็จ</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
      <div class="modal-body"><p class="receipt-details-name" data-detail="name"></p><dl class="receipt-details-grid">
        <dt>รอบขายยาง</dt><dd data-detail="round"></dd>
        <dt>ลาน</dt><dd data-detail="lane"></dd>
        <dt>จำนวนกระสอบ</dt><dd data-detail="sacks"></dd>
        <dt>ออกใบเสร็จเมื่อ</dt><dd data-detail="issuedAt"></dd>
        <dt>ผู้ดำเนินการ</dt><dd data-detail="issuedBy"></dd>
      </dl></div>
      <div class="modal-footer"><?php if (is_admin()): ?><a class="btn btn-sm collection-history-btn" id="receiptDetailsHistory" href="collection_history.php"><i data-lucide="history" aria-hidden="true"></i>ประวัติผู้ดำเนินการ</a><?php endif; ?><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ปิด</button></div>
    </div></div>
  </div>
</section>
<script>
(() => {
  const form = document.getElementById('receiptFilters');
  const input = document.getElementById('receipt-search');
  const lane = document.getElementById('receipt-lane');
  const results = document.getElementById('receiptSearchResults');
  const status = document.getElementById('receiptSearchStatus');
  const clear = document.getElementById('clearReceiptFilters');
  clear.style.display = clear.hidden ? 'none' : '';
  let timer, controller, generation = 0;
  const cancel = () => { clearTimeout(timer); generation++; if (controller) controller.abort(); };
  const searchUrl = () => {
    const url = new URL(form.action || location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    return url;
  };
  const updateExportLinks = () => {
    document.querySelectorAll('[data-receipt-export]').forEach(link => {
      const url = new URL('export_printed_receipts.php', location.href);
      url.search = new URLSearchParams({search: input.value, lan: lane.value, format: link.dataset.receiptExport});
      link.href = url.href;
    });
  };
  async function load(url) {
    cancel();
    updateExportLinks();
    const current = generation;
    controller = new AbortController();
    results.setAttribute('aria-busy', 'true');
    status.textContent = 'กำลังค้นหา…';
    try {
      const response = await fetch(url, {signal: controller.signal});
      if (!response.ok) throw new Error('Search failed');
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      const content = doc.getElementById('receiptSearchResults');
      if (current !== generation) return;
      if (!content) { location.assign(response.url); return; }
      results.innerHTML = content.innerHTML;
      history.replaceState(null, '', url);
      clear.hidden = input.value.trim() === '' && lane.value === 'all';
      clear.style.display = clear.hidden ? 'none' : '';
      status.textContent = '';
      if (window.lucide) window.lucide.createIcons();
    } catch (error) {
      if (error.name !== 'AbortError' && current === generation) status.textContent = 'ค้นหาไม่สำเร็จ กรุณากดค้นหาเพื่อลองใหม่';
    } finally {
      if (current === generation) results.removeAttribute('aria-busy');
    }
  }
  input.addEventListener('input', event => {
    updateExportLinks();
    cancel();
    if (!event.isComposing) timer = setTimeout(() => load(searchUrl()), 350);
  });
  input.addEventListener('compositionend', () => { cancel(); timer = setTimeout(() => load(searchUrl()), 350); });
  lane.addEventListener('change', () => load(searchUrl()));
  form.addEventListener('submit', event => { event.preventDefault(); load(searchUrl()); });
  clear.addEventListener('click', event => { event.preventDefault(); input.value = ''; lane.value = 'all'; load(searchUrl()); });
  results.addEventListener('click', event => {
    const link = event.target.closest('.receipt-pagination a');
    if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
      event.preventDefault(); load(new URL(link.href));
    }
  });
})();
document.getElementById('receiptDetailsModal').addEventListener('show.bs.modal', function(event) {
  const trigger = event.relatedTarget;
  if (!trigger) return;
  const detail = JSON.parse(trigger.getAttribute('data-receipt'));
  document.getElementById('receiptDetailsTitle').textContent = 'รายละเอียดใบเสร็จ #' + detail.id;
  this.querySelectorAll('[data-detail]').forEach(element => {
    element.textContent = detail[element.dataset.detail] || '—';
  });
  const historyLink = document.getElementById('receiptDetailsHistory');
  if (historyLink) historyLink.href = 'collection_history.php?ru_id=' + encodeURIComponent(detail.id);
});
</script>
<?php require __DIR__ . '/footer.php'; ?>

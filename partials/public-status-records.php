<?php
if (!isset($dashboard) || !is_array($dashboard)) { http_response_code(404); exit; }
$statusLabels=['waiting'=>'วางยางแล้ว','weighed'=>'ชั่งแล้ว','ready'=>'รอออกใบเสร็จ','issued'=>'ออกใบเสร็จแล้ว'];
?>
<?php foreach ($dashboard['records'] as $record): ?>
<article class="public-status-record"><div><strong class="public-record-name"><?php
  $deductionState = in_array($record['deduction_state'] ?? '', ['has','none'], true) ? $record['deduction_state'] : 'pending';
  $deductionLabel = ['has'=>'มียอดหัก','none'=>'ไม่มียอดหัก','pending'=>'รอยืนยันยอดหัก'][$deductionState];
?><span class="public-deduction-icon public-deduction-<?php echo e($deductionState); ?>" role="img" aria-label="<?php echo e($deductionLabel); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><?php if ($deductionState === 'has'): ?><path d="M8 12h8"/><?php elseif ($deductionState === 'none'): ?><path d="m8 12 3 3 5-6"/><?php else: ?><path d="M12 7v5l3 2"/><?php endif; ?></svg></span><span><?php echo e($record['name']); ?></span></strong><span>เลขสมาชิก <?php echo e($record['member_number'] ?: '—'); ?> <span aria-hidden="true">·</span> ลาน <?php echo e($record['lane']); ?> <span aria-hidden="true">·</span> <?php echo ($record['sacks'] ?? null) === null ? 'ไม่ระบุจำนวนกระสอบ' : number_format((float)$record['sacks'],2).' กระสอบ'; ?></span></div><div><span class="public-status public-status-<?php echo e($record['status']); ?>"><?php echo e($statusLabels[$record['status']]); ?></span><time datetime="<?php echo e($record['activity_at']); ?>"><?php echo e((new DateTimeImmutable($record['activity_at']))->format('d/m H:i')); ?></time></div></article>
<?php endforeach; ?>
<?php if (!$dashboard['records']): ?><div class="public-empty"><i data-lucide="clipboard-list" aria-hidden="true"></i><strong><?php echo $dashboard['member']!==''?'ยังไม่พบรายการที่ตรงกับคำค้นในรอบล่าสุด':'ยังไม่มีรายการในขั้นตอนนี้'; ?></strong><p>ตรวจสอบชื่อ เลขสมาชิก และวันที่รอบ หรือสอบถามเจ้าหน้าที่ประจำลาน</p></div><?php endif; ?>

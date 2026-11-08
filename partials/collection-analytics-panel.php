<?php
if (!isset($analytics) || !function_exists('is_logged_in') || !is_logged_in()) { http_response_code(404); exit; }
$analyticsTotals=$analytics['totals'];
$analysisDays=$analytics['days'];
$analysisFirst=$analysisDays ? $analysisDays[0]['date'] : null;
$analysisLast=$analysisDays ? $analysisDays[count($analysisDays)-1]['date'] : null;
$analyticsScope=$sum_lan==='all' ? 'ทุกลาน' : 'ลาน '.$sum_lan;
$analyticsPayload=[
    'dates'=>array_column($analysisDays,'date'),
    'quantity'=>array_column($analysisDays,'quantity'),
    'movingAverage'=>array_column($analysisDays,'moving_average'),
    'value'=>array_column($analysisDays,'value'),
    'net'=>array_column($analysisDays,'net'),
    'price'=>array_column($analysisDays,'price'),
    'members'=>$chartMemberCounts,
    'general'=>$chartGeneralCounts,
    'lanes'=>array_map(static function(array $lane): string { return $lane['lane']==='' ? 'ไม่ระบุลาน' : 'ลาน '.$lane['lane']; },$analytics['lanes']),
    'laneQuantity'=>array_column($analytics['lanes'],'quantity'),
];
?>
<section class="collection-analytics" aria-labelledby="analysisTitle">
  <div class="analysis-heading"><div><div class="analysis-eyebrow">ข้อมูลเพื่อการบริหาร</div><h2 id="analysisTitle">วิเคราะห์การรวบรวมยาง</h2><p>อ้างอิงรายการรับซื้อที่ไม่ถูกยกเลิก · ใช้มูลค่าที่บันทึกจริงตามราคาตอนชั่ง</p></div><a href="#dailySummary" class="btn btn-outline-secondary"><i data-lucide="table-2" class="me-2" aria-hidden="true"></i>ดูตารางข้อมูล</a></div>
  <form method="get" action="collection_analysis.php#analysisTitle" class="analysis-filters">
    <div><label for="analysisFrom" class="form-label">วันที่รับซื้อตั้งแต่</label><input id="analysisFrom" type="date" name="date_from" class="form-control" value="<?php echo e($sum_date_from); ?>"></div>
    <div><label for="analysisTo" class="form-label">ถึงวันที่</label><input id="analysisTo" type="date" name="date_to" class="form-control" value="<?php echo e($sum_date_to); ?>"></div>
    <div><label for="analysisLane" class="form-label">ลานรับซื้อ</label><select id="analysisLane" name="lan" class="form-select"><option value="all">ทุกลาน</option><?php for($lane=1;$lane<=4;$lane++): ?><option value="<?php echo $lane; ?>" <?php echo $sum_lan===(string)$lane ? 'selected' : ''; ?>>ลาน <?php echo $lane; ?></option><?php endfor; ?></select></div>
    <div class="analysis-filter-actions"><button type="submit" class="btn btn-success"><i data-lucide="filter" class="me-2" aria-hidden="true"></i>วิเคราะห์ข้อมูล</button><a href="collection_analysis.php#analysisTitle" class="btn btn-outline-secondary">ทั้งหมด</a></div>
  </form>
  <?php if ($sum_filter_error!==''): ?><div class="alert alert-warning mt-3" role="alert"><?php echo e($sum_filter_error); ?></div><?php endif; ?>
  <div class="analysis-scope"><span><i data-lucide="calendar-days" aria-hidden="true"></i><?php echo e($analyticsScope); ?> · <?php echo $analysisFirst ? e(thai_date_format($analysisFirst).' – '.thai_date_format($analysisLast)) : 'ไม่มีรายการในช่วงที่เลือก'; ?></span><span><?php echo number_format(count($analysisDays)); ?> วันที่มีรายการ · <?php echo number_format($analyticsTotals['records']); ?> รายการรับซื้อ</span></div>
  <div class="analysis-kpis">
    <article class="analysis-kpi"><div class="analysis-kpi-label">ปริมาณรวบรวม</div><div class="analysis-kpi-value"><?php echo number_format($analyticsTotals['quantity'],2); ?><small>กก.</small></div><div class="analysis-kpi-note">รวมตามช่วงวันที่และลานที่เลือก</div></article>
    <article class="analysis-kpi"><div class="analysis-kpi-label">มูลค่ารับซื้อ</div><div class="analysis-kpi-value"><?php echo number_format($analyticsTotals['value'],2); ?><small>บาท</small></div><div class="analysis-kpi-note">ก่อนหักยอดหักตามรายการ</div></article>
    <article class="analysis-kpi"><div class="analysis-kpi-label">ยอดสุทธิตามรายการ</div><div class="analysis-kpi-value"><?php echo number_format($analyticsTotals['net'],2); ?><small>บาท</small></div><div class="analysis-kpi-note">ยอดหักรวม <?php echo number_format($analyticsTotals['deductions'],2); ?> บาท</div></article>
    <article class="analysis-kpi"><div class="analysis-kpi-label">ราคาเฉลี่ยถ่วงน้ำหนัก</div><div class="analysis-kpi-value"><?php echo $analytics['weighted_price']===null ? '—' : number_format($analytics['weighted_price'],2); ?><small>บาท/กก.</small></div><div class="analysis-kpi-note">มูลค่ารับซื้อ ÷ ปริมาณรวม</div></article>
  </div>
  <?php if ($analysisDays): ?>
  <div class="analysis-observation"><i data-lucide="activity" aria-hidden="true"></i><div>วันที่มีรายการล่าสุดในตัวกรอง <strong><?php echo e(thai_date_format($analysisLast)); ?></strong> รวบรวม <?php echo number_format($analytics['latest']['quantity'],2); ?> กก.<?php if ($analytics['quantity_change']!==null): ?> · <?php echo $analytics['quantity_change']>0 ? 'เพิ่มขึ้น' : ($analytics['quantity_change']<0 ? 'ลดลง' : 'เท่าเดิม'); ?> <strong><?php echo number_format(abs($analytics['quantity_change']),1); ?>%</strong> เทียบวันที่มีรายการก่อนหน้า <?php echo e(thai_date_format($analytics['previous']['date'])); ?><?php else: ?> · ยังไม่มีปริมาณวันก่อนหน้าที่มากกว่าศูนย์สำหรับคำนวณอัตราเปลี่ยนแปลง<?php endif; ?></div></div>
  <p id="analyticsChartStatus" class="alert alert-warning" hidden>โหลดกราฟไม่สำเร็จ ตัวเลขสรุปและตารางด้านล่างยังแสดงข้อมูลตามตัวกรองได้</p>
  <noscript><p class="alert alert-info">เปิด JavaScript เพื่อดูกราฟ หรือตรวจข้อมูลจากตารางสรุปด้านล่าง</p></noscript>
  <div class="analytics-chart-grid">
    <article class="analysis-chart analysis-chart-wide"><header><div><h3>แนวโน้มปริมาณรวบรวม</h3><p>ปริมาณจริงและค่าเฉลี่ย 3 วันรับซื้อ</p></div><span>กิโลกรัม</span></header><div class="analysis-canvas"><canvas id="quantityChart" role="img" aria-label="ปริมาณยางรายวันและค่าเฉลี่ย 3 วันที่มีรายการรับซื้อ">ดูตารางสรุปรับซื้อรายวันด้านล่าง</canvas></div></article>
    <article class="analysis-chart"><header><div><h3>ราคาเฉลี่ยที่รับซื้อจริง</h3><p>ถ่วงน้ำหนักรวมทุกลานที่เลือกในแต่ละวัน</p></div><span>บาท/กก.</span></header><div class="analysis-canvas"><canvas id="weightedPriceChart" role="img" aria-label="ราคาเฉลี่ยถ่วงน้ำหนักรายวัน">ดูตารางสรุปรับซื้อรายวันด้านล่าง</canvas></div></article>
    <article class="analysis-chart"><header><div><h3>มูลค่ารับซื้อและยอดสุทธิ</h3><p>เปรียบเทียบยอดตามรายการในแต่ละวัน</p></div><span>บาท</span></header><div class="analysis-canvas"><canvas id="valueChart" role="img" aria-label="มูลค่ารับซื้อและยอดสุทธิรายวัน">ดูตารางสรุปรับซื้อรายวันด้านล่าง</canvas></div></article>
    <article class="analysis-chart"><header><div><h3>เปรียบเทียบปริมาณแต่ละลาน</h3><p>รวมตลอดช่วงที่เลือก เรียงจากมากไปน้อย</p></div><span>กิโลกรัม</span></header><div class="analysis-canvas"><canvas id="laneQuantityChart" role="img" aria-label="เปรียบเทียบปริมาณรับซื้อตามลาน">ดูข้อมูลรายลานในตาราง</canvas></div></article>
    <article class="analysis-chart analysis-chart-full"><header><div><h3>ผู้ส่งยางแยกตามประเภท</h3><p>จำนวนไม่ซ้ำภายในแต่ละวัน ไม่ใช่จำนวนคนไม่ซ้ำตลอดช่วง</p></div><span>คน/วัน</span></header><div class="analysis-canvas analysis-canvas-people"><canvas id="peopleChart" role="img" aria-label="จำนวนสมาชิกและเกษตรกรทั่วไปไม่ซ้ำในแต่ละวัน">ดูตารางสรุปรับซื้อรายวันด้านล่าง</canvas></div></article>
  </div>
  <p class="analysis-method">กราฟแสดงเฉพาะวันที่มีรายการ ไม่เติมวันที่ไม่มีข้อมูลเป็นศูนย์ · ค่าเฉลี่ย 3 วันรับซื้อเริ่มแสดงเมื่อมีข้อมูลครบ 3 วัน · จำนวนผู้ส่งนับสมาชิกตามเลขสมาชิก และเกษตรกรทั่วไปตามชื่อที่ระบุไว้ · รวมรายการรับซื้อที่ไม่ยกเลิกโดยไม่กรองสถานะอนุมัติ · ยอดสุทธิเป็นยอดตามรายการ ไม่ใช่สถานะการจ่ายเงิน</p>
  <?php else: ?><div class="analysis-empty"><i data-lucide="bar-chart-3" aria-hidden="true"></i><h3>ไม่พบรายการสำหรับการวิเคราะห์</h3><p>ลองเปลี่ยนช่วงวันที่หรือลานรับซื้อ กราฟจะแสดงเมื่อมีข้อมูลจริง</p></div><?php endif; ?>
  <script type="application/json" id="collectionAnalyticsData"><?php echo json_encode($analyticsPayload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); ?></script>
</section>

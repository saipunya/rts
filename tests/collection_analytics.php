<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/collection_analytics.php';
function analytics_check(bool $value,string $label): void { if (!$value) throw new RuntimeException($label); echo "PASS: $label\n"; }
$rows=[
 ['ru_date'=>'2026-11-02','ru_lan'=>'1','total_qty'=>100,'total_value'=>1000,'total_net'=>990,'total_expend'=>10,'row_count'=>1],
 ['ru_date'=>'2026-11-01','ru_lan'=>'2','total_qty'=>300,'total_value'=>6000,'total_net'=>5900,'total_expend'=>100,'row_count'=>3],
 ['ru_date'=>'2026-11-01','ru_lan'=>'1','total_qty'=>100,'total_value'=>1000,'total_net'=>990,'total_expend'=>10,'row_count'=>1],
 ['ru_date'=>'2026-11-05','ru_lan'=>'1','total_qty'=>200,'total_value'=>4000,'total_net'=>3900,'total_expend'=>100,'row_count'=>2],
];
$a=rts_collection_analytics($rows);
analytics_check($a['totals']['quantity']===700.0 && $a['totals']['value']===12000.0,'Aggregate every lane and day in the selected data');
analytics_check(abs($a['days'][0]['price']-17.5)<0.000001,'Daily price is weighted by quantity, never the first lane price or an unweighted mean');
analytics_check(abs($a['weighted_price']-12000/700)<0.000001,'Period price uses total recorded value divided by total quantity');
analytics_check($a['totals']['net']===11780.0 && $a['totals']['deductions']===220.0,'Preserve stored net and deduction totals');
analytics_check(array_column($a['days'],'date')===['2026-11-01','2026-11-02','2026-11-05'],'Dates are chronological without inventing missing-day records');
analytics_check($a['days'][0]['moving_average']===null && $a['days'][1]['moving_average']===null,'Moving average is absent until three buying days exist');
analytics_check(abs($a['days'][2]['moving_average']-700/3)<0.000001,'Moving average uses three buying days including date gaps');
analytics_check($a['quantity_change']===100.0,'Volume change compares the last two buying days, not different periods');
analytics_check($a['totals']['records']===7 && $a['lanes'][0]['lane']==='1','Record counts and lane ranking remain consistent');
$empty=rts_collection_analytics([]);
analytics_check($empty['weighted_price']===null && $empty['latest']===null && $empty['quantity_change']===null,'Empty data has no invented price or change percentage');
$zero=rts_collection_analytics([['ru_date'=>'2026-11-01','ru_lan'=>'1'],['ru_date'=>'2026-11-02','ru_lan'=>'1','total_qty'=>100,'total_value'=>2000]]);
analytics_check($zero['days'][0]['price']===null && $zero['quantity_change']===null,'Zero-weight days do not create prices or infinite percentage changes');
$large=[];
for($i=0;$i<250;$i++) $large[]=['ru_date'=>(new DateTimeImmutable('2025-01-01'))->modify('+'.$i.' days')->format('Y-m-d'),'ru_lan'=>'1','total_qty'=>1,'total_value'=>10];
analytics_check(count(rts_collection_analytics($large)['days'])===250,'Analytics cover more than 200 grouped rows');

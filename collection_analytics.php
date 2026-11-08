<?php
declare(strict_types=1);

/** Summarize already-filtered daily/lane aggregates; never recalculate stored purchases. */
function rts_collection_analytics(array $rows): array {
    $days=[]; $lanes=[];
    $totals=['quantity'=>0.0,'value'=>0.0,'net'=>0.0,'deductions'=>0.0,'records'=>0];
    foreach ($rows as $row) {
        $date=(string)($row['ru_date'] ?? '');
        if ($date==='') continue;
        $lane=(string)($row['ru_lan'] ?? '');
        if (!isset($days[$date])) $days[$date]=['date'=>$date,'quantity'=>0.0,'value'=>0.0,'net'=>0.0,'deductions'=>0.0,'records'=>0];
        if (!isset($lanes[$lane])) $lanes[$lane]=['lane'=>$lane,'quantity'=>0.0,'value'=>0.0];
        foreach (['quantity'=>'total_qty','value'=>'total_value','net'=>'total_net','deductions'=>'total_expend'] as $field=>$source) {
            $amount=(float)($row[$source] ?? 0);
            $days[$date][$field]+=$amount; $totals[$field]+=$amount;
            if ($field==='quantity' || $field==='value') $lanes[$lane][$field]+=$amount;
        }
        $records=(int)($row['row_count'] ?? 0);
        $days[$date]['records']+=$records; $totals['records']+=$records;
    }
    ksort($days);
    $series=array_values($days);
    foreach ($series as $i=>&$day) {
        $day['price']=$day['quantity']>0 ? $day['value']/$day['quantity'] : null;
        $day['moving_average']=$i>=2 ? ($series[$i]['quantity']+$series[$i-1]['quantity']+$series[$i-2]['quantity'])/3 : null;
    }
    unset($day);
    $latest=$series ? $series[count($series)-1] : null;
    $previous=count($series)>=2 ? $series[count($series)-2] : null;
    $change=$previous && $previous['quantity']>0 ? ($latest['quantity']-$previous['quantity'])/$previous['quantity']*100 : null;
    $lanes=array_values($lanes);
    usort($lanes,static function(array $a,array $b): int { return ($b['quantity']<=>$a['quantity']) ?: strnatcmp($a['lane'],$b['lane']); });
    return ['totals'=>$totals,'days'=>$series,'lanes'=>$lanes,'weighted_price'=>$totals['quantity']>0 ? $totals['value']/$totals['quantity'] : null,'latest'=>$latest,'previous'=>$previous,'quantity_change'=>$change];
}

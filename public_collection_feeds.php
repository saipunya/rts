<?php
declare(strict_types=1);

/** Each feed records a workflow milestone. A receipt does not remove a confirmed weighing. */
function rts_public_collection_feeds(mysqli $db): array {
    $weighed=$db->query("SELECT r.ru_id,r.ru_lan,x.net_weight AS ru_quantity,
        COALESCE(NULLIF(TRIM(r.ru_fullname),''),NULLIF(TRIM(w.wang_name),''),'ไม่ระบุชื่อ') collection_name,
        w.wang_sack
        FROM tbl_wang_weighing x JOIN tbl_rubber r ON r.ru_id=x.ru_id
        LEFT JOIN tbl_wangyang w ON w.wang_id=x.wang_id
        WHERE LOWER(TRIM(COALESCE(r.record_status,'normal'))) NOT IN ('cancelled','canceled','deleted')
          AND LOWER(TRIM(COALESCE(w.record_status,'normal'))) NOT IN ('cancelled','canceled','deleted')
          AND LOWER(TRIM(COALESCE(w.wang_status,''))) NOT IN ('cancelled','canceled','deleted')
        ORDER BY x.weighed_at DESC,x.weighing_id DESC,r.ru_id DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
    // Reuse the printed-receipts page's membership and ordering. Publish only the fields needed here.
    $issued=issued_rubber_receipts($db);
    $receipts=[];
    foreach (array_slice($issued['rows'],0,20) as $row) {
        $receipts[]=['ru_id'=>$row['ru_id'],'ru_lan'=>$row['ru_lan'],'ru_quantity'=>$row['ru_quantity'],
            'collection_name'=>trim((string)$row['ru_fullname']) ?: 'ไม่ระบุชื่อ','wang_sack'=>$row['wang_sack']];
    }
    return ['weighed'=>$weighed,'receipts'=>$receipts];
}

<?php
declare(strict_types=1);

function demo_reset_tables(): array {
    // Child records precede their parents; user accounts are deliberately excluded.
    return [
        'tbl_collection_cancellations'=>'ประวัติยกเลิกรายการ',
        'tbl_collection_audit'=>'ประวัติการบันทึกและแก้ไข',
        'tbl_weighing_review_returns'=>'ประวัติย้อนกลับการอนุมัติ',
        'tbl_rubber_receipts'=>'ใบเสร็จ',
        'tbl_wang_weighing'=>'ผลชั่งและการอนุมัติ / ยอดหัก',
        'tbl_rubber'=>'รายการรับซื้อและยอดหัก',
        'tbl_wangyang_daily_summary'=>'สรุปวางยางรายวัน',
        'tbl_wangyang'=>'รายการวางยาง',
        'tbl_price'=>'ราคายาง',
        'tbl_collection_rounds'=>'รอบขายยาง',
        'tbl_member_portal_log'=>'ประวัติการเข้าใช้ของสมาชิก',
        'tbl_online_presence'=>'สถานะออนไลน์',
        'tbl_member'=>'ข้อมูลสมาชิก (เลือกเพิ่มเติม)',
    ];
}

function demo_reset_inventory(mysqli $db): array {
    $existing = [];
    foreach ($db->query('SHOW TABLE STATUS')->fetch_all(MYSQLI_ASSOC) as $table) $existing[$table['Name']] = $table['Engine'];
    $inventory = [];
    foreach (demo_reset_tables() as $table=>$label) {
        if (!isset($existing[$table])) continue;
        $count = (int)$db->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
        $inventory[$table] = ['label'=>$label,'count'=>$count,'engine'=>$existing[$table]];
    }
    return $inventory;
}

function clear_demo_data(mysqli $db, bool $includeMembers, string $actor): array {
    if (!is_admin()) throw new DomainException('เฉพาะผู้ดูแลระบบเท่านั้นที่ล้างข้อมูลเดโมได้');
    $inventory = demo_reset_inventory($db);
    if (!$includeMembers) unset($inventory['tbl_member']);
    foreach ($inventory as $item) {
        if (strtolower((string)$item['engine']) !== 'innodb') throw new DomainException('ไม่สามารถล้างข้อมูลได้ เนื่องจากมีตารางที่ไม่รองรับการย้อนคืนธุรกรรม');
    }
    $deleted = [];
    $db->begin_transaction();
    try {
        foreach ($inventory as $table=>$item) {
            $db->query("DELETE FROM `$table`");
            $deleted[$table] = $db->affected_rows;
        }
        record_collection_audit($db, 'demo_reset', 0, 0, $actor, ['include_members'=>$includeMembers,'deleted'=>$deleted]);
        $db->commit();
        return $deleted;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

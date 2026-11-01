<?php
declare(strict_types=1);

function ensure_cancellation_schema(mysqli $db): void {
    foreach (['tbl_wangyang','tbl_rubber'] as $table) {
        $exists = $db->query("SHOW TABLES LIKE '$table'")->num_rows > 0;
        if (!$exists) continue;
        $columns = array_column($db->query("SHOW COLUMNS FROM `$table`")->fetch_all(MYSQLI_ASSOC), 'Field');
        if (!in_array('record_status', $columns, true)) {
            $db->query("ALTER TABLE `$table` ADD COLUMN record_status VARCHAR(20) NOT NULL DEFAULT 'normal'");
        }
    }
    if ($db->query("SHOW TABLES LIKE 'tbl_wangyang'")->num_rows && $db->query("SHOW TABLES LIKE 'tbl_wang_weighing'")->num_rows && $db->query("SHOW TABLES LIKE 'tbl_rubber'")->num_rows) {
        $db->query("UPDATE tbl_wangyang SET record_status='cancelled' WHERE LOWER(TRIM(COALESCE(wang_status,''))) IN ('cancelled','canceled','deleted') AND record_status<>'cancelled'");
        $db->query("UPDATE tbl_rubber r JOIN tbl_wang_weighing x ON x.ru_id=r.ru_id JOIN tbl_wangyang w ON w.wang_id=x.wang_id SET r.record_status='cancelled' WHERE w.record_status='cancelled' AND r.record_status<>'cancelled'");
    }
    $db->query("CREATE TABLE IF NOT EXISTS tbl_collection_cancellations (
        cancellation_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        wang_id INT NULL, ru_id INT NULL,
        cancelled_at DATETIME NOT NULL,
        actor_id INT NULL, actor_username VARCHAR(255) NOT NULL, actor_name VARCHAR(255) NOT NULL,
        reason TEXT NOT NULL, original_data LONGTEXT NOT NULL,
        UNIQUE KEY uq_cancel_wang (wang_id), UNIQUE KEY uq_cancel_rubber (ru_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function collection_is_cancelled(array $row): bool {
    return in_array(strtolower(trim((string)($row['record_status'] ?? ''))), ['cancelled','canceled','deleted'], true)
        || in_array(strtolower(trim((string)($row['wang_status'] ?? ''))), ['cancelled','canceled','deleted'], true);
}

function collection_cancellation(mysqli $db, int $wangId = 0, int $rubberId = 0): ?array {
    $stmt=$db->prepare('SELECT * FROM tbl_collection_cancellations WHERE (wang_id=? AND ?>0) OR (ru_id=? AND ?>0) LIMIT 1');
    $stmt->bind_param('iiii',$wangId,$wangId,$rubberId,$rubberId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
    return $row ?: null;
}

function assert_rubber_not_cancelled(mysqli $db, int $id): void {
    $stmt=$db->prepare('SELECT r.record_status, w.wang_status FROM tbl_rubber r LEFT JOIN tbl_wang_weighing x ON x.ru_id=r.ru_id LEFT JOIN tbl_wangyang w ON w.wang_id=x.wang_id WHERE r.ru_id=?');
    $stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
    if ($row && collection_is_cancelled($row)) throw new DomainException('รายการนี้ยกเลิกแล้ว ไม่สามารถดำเนินการต่อได้');
}

// One transaction retains the whole chain and its financial values for audit.
function cancel_collection_record(mysqli $db, int $wangId, int $rubberId, string $reason): void {
    if (!is_admin()) throw new DomainException('เฉพาะ admin เท่านั้นที่ยกเลิกรายการได้');
    $reason=trim($reason);
    if ($reason==='' || mb_strlen($reason)>2000) throw new DomainException('กรุณาระบุเหตุผลการยกเลิก ไม่เกิน 2,000 ตัวอักษร');
    $tables=$db->query("SHOW TABLE STATUS WHERE Name IN ('tbl_wangyang','tbl_rubber','tbl_wang_weighing','tbl_collection_cancellations','tbl_collection_audit')")->fetch_all(MYSQLI_ASSOC);
    foreach ($tables as $table) if (strtolower((string)$table['Engine'])!=='innodb') throw new DomainException('ตารางข้อมูลต้องรองรับธุรกรรม InnoDB ก่อนยกเลิกรายการ');
    $db->begin_transaction();
    try {
        $stmt=$db->prepare('SELECT * FROM tbl_wang_weighing WHERE (wang_id=? AND ?>0) OR (ru_id=? AND ?>0) LIMIT 1');
        $stmt->bind_param('iiii',$wangId,$wangId,$rubberId,$rubberId);$stmt->execute();$weighing=$stmt->get_result()->fetch_assoc();$stmt->close();
        if ($weighing) {
            if (($wangId && $wangId!==(int)$weighing['wang_id']) || ($rubberId && $rubberId!==(int)$weighing['ru_id'])) throw new DomainException('รายการวางยางและใบรับยางไม่ตรงกัน');
            $wangId=(int)$weighing['wang_id'];$rubberId=(int)$weighing['ru_id'];
        } elseif ($wangId && $rubberId) throw new DomainException('รายการวางยางและใบรับยางไม่เชื่อมโยงกัน');
        $purchase=null;$placement=null;
        // Match the purchase-first lock order used by receipt issuance and deductions.
        if ($rubberId) {
            $stmt=$db->prepare('SELECT * FROM tbl_rubber WHERE ru_id=? FOR UPDATE');$stmt->bind_param('i',$rubberId);$stmt->execute();$purchase=$stmt->get_result()->fetch_assoc();$stmt->close();
            if (!$purchase) throw new DomainException('ไม่พบรายการรับซื้อ');
        }
        if ($wangId) {
            $stmt=$db->prepare('SELECT * FROM tbl_wangyang WHERE wang_id=? FOR UPDATE');$stmt->bind_param('i',$wangId);$stmt->execute();$placement=$stmt->get_result()->fetch_assoc();$stmt->close();
            if (!$placement) throw new DomainException('ไม่พบรายการวางยาง');
            $stmt=$db->prepare('SELECT * FROM tbl_wang_weighing WHERE wang_id=? FOR UPDATE');$stmt->bind_param('i',$wangId);$stmt->execute();$lockedWeighing=$stmt->get_result()->fetch_assoc();$stmt->close();
            if ((int)($lockedWeighing['ru_id']??0)!==$rubberId) throw new DomainException('รายการเปลี่ยนแปลงระหว่างดำเนินการ กรุณาโหลดใหม่');
            $weighing=$lockedWeighing;
        }
        if (!$placement && !$purchase) throw new DomainException('ไม่พบรายการ');
        if (($placement && collection_is_cancelled($placement)) || ($purchase && collection_is_cancelled($purchase)) || collection_cancellation($db,$wangId,$rubberId)) throw new DomainException('รายการนี้ยกเลิกแล้ว');
        $receipt=$rubberId ? rubber_receipt_issued($db,$rubberId) : null;
        $before=['placement'=>$placement,'weighing'=>$weighing,'purchase'=>$purchase,'receipt'=>$receipt];
        $snapshot=json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $user=current_user();$actorId=(int)$user['user_id'];$username=(string)($user['user_username']??'');$actor=(string)(($user['user_fullname']??'')?:$username);
        $now=(new DateTimeImmutable('now',new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
        $storedWang=$wangId?:null;$storedRubber=$rubberId?:null;
        $stmt=$db->prepare('INSERT INTO tbl_collection_cancellations (wang_id,ru_id,cancelled_at,actor_id,actor_username,actor_name,reason,original_data) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->bind_param('iisissss',$storedWang,$storedRubber,$now,$actorId,$username,$actor,$reason,$snapshot);$stmt->execute();$stmt->close();
        if ($wangId) {$stmt=$db->prepare("UPDATE tbl_wangyang SET record_status='cancelled', wang_status='cancelled' WHERE wang_id=?");$stmt->bind_param('i',$wangId);$stmt->execute();$stmt->close();}
        if ($rubberId) {$stmt=$db->prepare("UPDATE tbl_rubber SET record_status='cancelled' WHERE ru_id=?");$stmt->bind_param('i',$rubberId);$stmt->execute();$stmt->close();}
        record_collection_audit($db,'cancel_all',$wangId,$rubberId,$actor,['reason'=>$reason,'before'=>$before,'status'=>'cancelled']);
        $db->commit();
    } catch (Throwable $error) {$db->rollback();throw $error;}
}

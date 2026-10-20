<?php
declare(strict_types=1);

function ensure_weighing_schema(mysqli $db): void {
    ensure_wang_collection_table($db);
    $db->query("CREATE TABLE IF NOT EXISTS tbl_wang_weighing (
        weighing_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        wang_id INT NOT NULL,
        ru_id INT NOT NULL,
        net_weight DECIMAL(18,2) NOT NULL,
        price_id INT NOT NULL,
        price_per_kg DECIMAL(18,2) NOT NULL,
        round_date DATE NOT NULL,
        weighed_by VARCHAR(255) NOT NULL,
        weighed_at DATETIME NOT NULL,
        deductions_saved_at DATETIME NULL,
        deductions_saved_by VARCHAR(255) NULL,
        UNIQUE KEY uq_weighing_wang (wang_id),
        UNIQUE KEY uq_weighing_rubber (ru_id),
        KEY idx_weighing_round (round_date),
        CONSTRAINT fk_weighing_wang FOREIGN KEY (wang_id) REFERENCES tbl_wangyang(wang_id) ON DELETE RESTRICT,
        CONSTRAINT fk_weighing_rubber FOREIGN KEY (ru_id) REFERENCES tbl_rubber(ru_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS tbl_rubber_receipts (
        ru_id INT NOT NULL PRIMARY KEY,
        issued_at DATETIME NOT NULL,
        issued_by VARCHAR(255) NOT NULL,
        original_data LONGTEXT NOT NULL,
        last_edited_at DATETIME NULL,
        last_edited_by VARCHAR(255) NULL,
        CONSTRAINT fk_receipt_rubber FOREIGN KEY (ru_id) REFERENCES tbl_rubber(ru_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Upgrade installations created before deduction completion was tracked.
    $columns = $db->query('SHOW COLUMNS FROM tbl_wang_weighing')->fetch_all(MYSQLI_ASSOC);
    $names = array_column($columns, 'Field');
    if (!in_array('deductions_saved_at', $names, true)) $db->query('ALTER TABLE tbl_wang_weighing ADD COLUMN deductions_saved_at DATETIME NULL');
    if (!in_array('deductions_saved_by', $names, true)) $db->query('ALTER TABLE tbl_wang_weighing ADD COLUMN deductions_saved_by VARCHAR(255) NULL');
    if (!in_array('weight_updated_at', $names, true)) $db->query('ALTER TABLE tbl_wang_weighing ADD COLUMN weight_updated_at DATETIME NULL');
    if (!in_array('weight_updated_by', $names, true)) $db->query('ALTER TABLE tbl_wang_weighing ADD COLUMN weight_updated_by VARCHAR(255) NULL');

}

function weighing_for_rubber(mysqli $db, int $id): ?array {
    $stmt = $db->prepare('SELECT * FROM tbl_wang_weighing WHERE ru_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function wang_has_weighing(mysqli $db, int $id): bool {
    $stmt = $db->prepare('SELECT weighing_id FROM tbl_wang_weighing WHERE wang_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

// One source row creates exactly one purchase row. Both writes succeed together.
function record_wang_weighing(mysqli $db, int $wangId, string $weight, int $priceId, string $actor, ?string $expectedLane = null): int {
    $weight = trim($weight);
    if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $weight) || (float)$weight <= 0) {
        throw new InvalidArgumentException('กรุณากรอกน้ำหนักสุทธิมากกว่า 0 ทศนิยมไม่เกิน 2 ตำแหน่ง');
    }
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT * FROM tbl_wangyang WHERE wang_id = ? FOR UPDATE');
        $stmt->bind_param('i', $wangId);
        $stmt->execute();
        $wang = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$wang) throw new RuntimeException('ไม่พบรายการวางยาง');
        if ($expectedLane !== null && (!in_array($expectedLane, ['1','2','3','4'], true) || (string)$wang['wang_lan'] !== $expectedLane)) {
            throw new RuntimeException('รายการวางยางไม่ตรงกับลานที่เลือก กรุณาเลือกรายการใหม่');
        }
        if (wang_has_weighing($db, $wangId)) throw new RuntimeException('รายการนี้ชั่งและส่งไปใส่ยอดหักแล้ว');
        if (in_array(strtolower($wang['wang_status']), ['cancelled', 'canceled', 'deleted'], true)) {
            throw new RuntimeException('รายการวางยางนี้ถูกยกเลิกแล้ว');
        }
        if (!in_array((string)$wang['wang_lan'], ['1', '2', '3', '4'], true)
            || trim($wang['wang_number']) === '' || trim($wang['wang_name']) === ''
            || trim($wang['wang_class']) === '' || trim($wang['wang_group']) === '') {
            throw new RuntimeException('กรุณาแก้ไขข้อมูลผู้ส่งยาง เลขที่ กลุ่ม ชั้น และลานให้ครบก่อนชั่ง');
        }
        $stmt = $db->prepare('SELECT pr_id, pr_date, pr_price FROM tbl_price WHERE pr_id = ? LOCK IN SHARE MODE');
        $stmt->bind_param('i', $priceId);
        $stmt->execute();
        $price = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$price || (float)$price['pr_price'] <= 0) throw new RuntimeException('กรุณาเลือกรอบราคาที่มีราคามากกว่า 0');
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
        $savedDate = substr($now, 0, 10);
        $stmt = $db->prepare('INSERT INTO tbl_rubber
            (ru_date, ru_lan, ru_group, ru_number, ru_fullname, ru_class, ru_quantity,
             ru_hoon, ru_loan, ru_shortdebt, ru_deposit, ru_tradeloan, ru_insurance,
             ru_value, ru_expend, ru_netvalue, ru_saveby, ru_savedate)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0,
                    ROUND(CAST(? AS DECIMAL(18,2)) * CAST(? AS DECIMAL(18,2)), 2), 0, ROUND(CAST(? AS DECIMAL(18,2)) * CAST(? AS DECIMAL(18,2)), 2), ?, ?)');
        $stmt->bind_param('sssssssssssss', $price['pr_date'], $wang['wang_lan'], $wang['wang_group'],
            $wang['wang_number'], $wang['wang_name'], $wang['wang_class'], $weight,
            $weight, $price['pr_price'], $weight, $price['pr_price'], $actor, $savedDate);
        $stmt->execute();
        $rubberId = $db->insert_id;
        $stmt->close();
        $stmt = $db->prepare('INSERT INTO tbl_wang_weighing
            (wang_id, ru_id, net_weight, price_id, price_per_kg, round_date, weighed_by, weighed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iisissss', $wangId, $rubberId, $weight, $priceId, $price['pr_price'], $price['pr_date'], $actor, $now);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('UPDATE tbl_wangyang SET wang_weight = ? WHERE wang_id = ?');
        $stmt->bind_param('si', $weight, $wangId);
        $stmt->execute();
        $stmt->close();
        $db->commit();
        return $rubberId;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}

function mark_weighing_deductions_saved(mysqli $db, int $rubberId, string $actor): void {
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE tbl_wang_weighing SET deductions_saved_at = ?, deductions_saved_by = ? WHERE ru_id = ?');
    $stmt->bind_param('ssi', $now, $actor, $rubberId);
    $stmt->execute();
    $stmt->close();
}

function collection_workflow_counts(mysqli $db, string $date, string $lane = 'all'): array {
    $sql = "SELECT COUNT(*) AS placed,
        COALESCE(SUM(x.weighing_id IS NULL),0) AS pending_weighing,
        COALESCE(SUM(x.weighing_id IS NOT NULL AND x.deductions_saved_at IS NULL),0) AS pending_deductions,
        COALESCE(SUM(x.deductions_saved_at IS NOT NULL),0) AS completed
        FROM tbl_wangyang w LEFT JOIN tbl_wang_weighing x ON x.wang_id = w.wang_id
        WHERE (? = '' OR w.wang_date = ?) AND LOWER(TRIM(w.wang_status)) NOT IN ('cancelled','canceled','deleted')";
    if ($lane !== 'all') $sql .= ' AND w.wang_lan = ?';
    $stmt = $db->prepare($sql);
    if ($lane === 'all') $stmt->bind_param('ss', $date, $date); else $stmt->bind_param('sss', $date, $date, $lane);
    $stmt->execute();
    $counts = array_map('intval', $stmt->get_result()->fetch_assoc());
    $stmt->close();
    return $counts;
}

function rubber_deduction_fields(): array {
    return ['ru_hoon', 'ru_loan', 'ru_shortdebt', 'ru_deposit', 'ru_tradeloan', 'ru_insurance'];
}

function normalize_rubber_deductions(array $input): array {
    $entered = false;
    $values = [];
    foreach (rubber_deduction_fields() as $field) {
        $raw = trim((string)($input[$field] ?? ''));
        if ($raw !== '') $entered = true;
        if ($raw === '') {
            $values[$field] = '0.00';
            continue;
        }
        $value = filter_var($raw, FILTER_VALIDATE_FLOAT);
        if ($value === false || !is_finite((float)$value) || $value < 0) {
            throw new InvalidArgumentException('ยอดหักต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป');
        }
        $values[$field] = number_format((float)$value, 2, '.', '');
    }
    if (!$entered) throw new InvalidArgumentException('กรุณากรอกยอดหักอย่างน้อย 1 ช่อง หากไม่มีรายการหักให้กรอก 0 ช่องใดช่องหนึ่ง');
    return $values;
}

function rubber_receipt_ready(mysqli $db, int $rubberId): bool {
    $weighing = weighing_for_rubber($db, $rubberId);
    // Legacy purchases were entered before the weighing workflow existed.
    return $weighing === null || $weighing['deductions_saved_at'] !== null;
}

function rubber_receipt_issued(mysqli $db, int $id): ?array {
    $stmt = $db->prepare('SELECT * FROM tbl_rubber_receipts WHERE ru_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Call inside a transaction. Issuance and edits lock the same purchase first.
function lock_rubber_for_edit(mysqli $db, int $id, bool $admin): void {
    $stmt = $db->prepare('SELECT ru_id FROM tbl_rubber WHERE ru_id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$exists) throw new RuntimeException('ไม่พบรายการ');
    $stmt = $db->prepare('SELECT ru_id FROM tbl_rubber_receipts WHERE ru_id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $issued = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($issued && !$admin) throw new DomainException('รายการนี้ออกใบเสร็จแล้ว เฉพาะ admin เท่านั้นที่แก้ไขได้');
}

// Caller holds the purchase row lock and has successfully rendered its PDF.
function record_rubber_receipt_issue(mysqli $db, array $row, string $actor): void {
    $id = (int)$row['ru_id'];
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $snapshot = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = $db->prepare('INSERT INTO tbl_rubber_receipts (ru_id, issued_at, issued_by, original_data)
        VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE ru_id = tbl_rubber_receipts.ru_id');
    $stmt->bind_param('isss', $id, $now, $actor, $snapshot);
    $stmt->execute();
    $stmt->close();
}

function record_receipt_admin_edit(mysqli $db, int $id, string $actor): void {
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE tbl_rubber_receipts SET last_edited_at = ?, last_edited_by = ? WHERE ru_id = ?');
    $stmt->bind_param('ssi', $now, $actor, $id);
    $stmt->execute();
    $stmt->close();
}

function wang_record_lock_label(array $state): string {
    if (!empty($state['receipt_issued_at'])) return 'ออกใบเสร็จแล้ว — ล็อกข้อมูลวางยาง';
    if (!empty($state['deductions_saved_at'])) return 'บันทึกยอดหักแล้ว — ล็อกข้อมูลวางยาง';
    if (!empty($state['weighing_id'])) return 'ชั่งน้ำหนักแล้ว — ล็อกข้อมูลวางยาง';
    return '';
}

function wang_record_lock_reason(mysqli $db, int $id): string {
    $stmt = $db->prepare('SELECT x.weighing_id, x.deductions_saved_at, z.issued_at AS receipt_issued_at
        FROM tbl_wang_weighing x LEFT JOIN tbl_rubber_receipts z ON z.ru_id=x.ru_id WHERE x.wang_id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $state = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return wang_record_lock_label($state ?: []);
}

function update_wang_weighing(mysqli $db, int $wangId, string $weight, string $actor, string $lane): int {
    $weight = trim($weight);
    if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $weight) || (float)$weight <= 0) {
        throw new InvalidArgumentException('กรุณากรอกน้ำหนักสุทธิมากกว่า 0 ทศนิยมไม่เกิน 2 ตำแหน่ง');
    }
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT wang_lan FROM tbl_wangyang WHERE wang_id=? FOR UPDATE');
        $stmt->bind_param('i', $wangId); $stmt->execute(); $source = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$source || !in_array($lane, ['1','2','3','4'], true) || $source['wang_lan'] !== $lane) throw new RuntimeException('รายการวางยางไม่ตรงกับลานที่เลือก');
        $stmt = $db->prepare('SELECT ru_id FROM tbl_wang_weighing WHERE wang_id=?');
        $stmt->bind_param('i', $wangId); $stmt->execute(); $id = (int)($stmt->get_result()->fetch_row()[0] ?? 0); $stmt->close();
        if (!$id) throw new RuntimeException('ยังไม่มีผลชั่งสำหรับรายการนี้');
        lock_rubber_for_edit($db, $id, false);
        $stmt = $db->prepare('SELECT * FROM tbl_wang_weighing WHERE wang_id=? FOR UPDATE');
        $stmt->bind_param('i', $wangId); $stmt->execute(); $weighing = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($weighing['deductions_saved_at'] !== null) throw new RuntimeException('รายการนี้บันทึกยอดหักแล้ว ไม่สามารถแก้น้ำหนักจากหน้าชั่งได้');
        $stmt = $db->prepare('UPDATE tbl_rubber SET ru_quantity=?, ru_value=ROUND(CAST(? AS DECIMAL(18,2))*CAST(? AS DECIMAL(18,2)),2), ru_netvalue=ROUND(CAST(? AS DECIMAL(18,2))*CAST(? AS DECIMAL(18,2)),2)-ru_expend WHERE ru_id=?');
        $price = $weighing['price_per_kg'];
        $stmt->bind_param('sssssi', $weight, $weight, $price, $weight, $price, $id); $stmt->execute(); $stmt->close();
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE tbl_wang_weighing SET net_weight=?, weight_updated_at=?, weight_updated_by=? WHERE wang_id=?');
        $stmt->bind_param('sssi', $weight, $now, $actor, $wangId); $stmt->execute(); $stmt->close();
        $stmt = $db->prepare('UPDATE tbl_wangyang SET wang_weight=? WHERE wang_id=?');
        $stmt->bind_param('si', $weight, $wangId); $stmt->execute(); $stmt->close();
        $db->commit();
        return $id;
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

// Read current weight after acquiring the purchase lock, never reuse a stale form snapshot.
function refresh_weighed_financial_data(mysqli $db, int $id, array $data): array {
    $stmt = $db->prepare('SELECT r.*, x.net_weight, x.price_per_kg FROM tbl_rubber r JOIN tbl_wang_weighing x ON x.ru_id=r.ru_id WHERE r.ru_id=? FOR UPDATE');
    $stmt->bind_param('i', $id); $stmt->execute(); $fresh = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$fresh) return $data;
    foreach (['ru_date','ru_lan','ru_group','ru_number','ru_fullname','ru_class'] as $field) $data[$field] = $fresh[$field];
    $data['ru_quantity'] = $fresh['net_weight'];
    $stmt = $db->prepare('SELECT v.amount AS ru_value, v.amount-CAST(? AS DECIMAL(18,2)) AS ru_netvalue FROM (SELECT ROUND(CAST(? AS DECIMAL(18,2))*CAST(? AS DECIMAL(18,2)),2) AS amount) v');
    $stmt->bind_param('sss', $data['ru_expend'], $fresh['net_weight'], $fresh['price_per_kg']); $stmt->execute(); $totals = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return array_merge($data, $totals);
}

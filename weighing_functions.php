<?php
declare(strict_types=1);

function ensure_weighing_schema(mysqli $db): void {
    ensure_wang_collection_table($db);
    ensure_cancellation_schema($db);
    ensure_collection_round_schema($db);
    $db->query("CREATE TABLE IF NOT EXISTS tbl_collection_audit (
        audit_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        wang_id INT NOT NULL DEFAULT 0,
        ru_id INT NOT NULL DEFAULT 0,
        action VARCHAR(60) NOT NULL,
        actor_id INT NULL,
        actor_username VARCHAR(255) NOT NULL,
        actor_name VARCHAR(255) NOT NULL,
        recorded_at DATETIME NOT NULL,
        details LONGTEXT NOT NULL,
        KEY idx_collection_audit_wang (wang_id, audit_id),
        KEY idx_collection_audit_rubber (ru_id, audit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
    $db->query("CREATE TABLE IF NOT EXISTS tbl_weighing_review_returns (
        return_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        ru_id INT NOT NULL,
        referred_at DATETIME NULL,
        referred_by VARCHAR(255) NULL,
        returned_at DATETIME NOT NULL,
        returned_by VARCHAR(255) NOT NULL,
        KEY idx_review_returns_purchase (ru_id, return_id),
        CONSTRAINT fk_review_return_rubber FOREIGN KEY (ru_id) REFERENCES tbl_rubber(ru_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $approvalColumns = array_column($db->query('SHOW COLUMNS FROM tbl_wang_weighing')->fetch_all(MYSQLI_ASSOC), 'Field');
    foreach (['approval_route'=>"VARCHAR(20) NULL", 'approved_at'=>"DATETIME NULL", 'approved_by'=>"VARCHAR(255) NULL"] as $column=>$definition) {
        if (!in_array($column, $approvalColumns, true)) $db->query("ALTER TABLE tbl_wang_weighing ADD COLUMN $column $definition");
    }
    // Upgrade installations created before deduction completion was tracked.
    $columns = $db->query('SHOW COLUMNS FROM tbl_wang_weighing')->fetch_all(MYSQLI_ASSOC);
    $names = array_column($columns, 'Field');
    if (!in_array('deductions_saved_at', $names, true)) $db->query('ALTER TABLE tbl_wang_weighing ADD COLUMN deductions_saved_at DATETIME NULL');
    if (!in_array('deductions_saved_by', $names, true)) $db->query('ALTER TABLE tbl_wang_weighing ADD COLUMN deductions_saved_by VARCHAR(255) NULL');
    if (!in_array('weight_updated_at', $names, true)) $db->query('ALTER TABLE tbl_wang_weighing ADD COLUMN weight_updated_at DATETIME NULL');
    if (!in_array('weight_updated_by', $names, true)) $db->query('ALTER TABLE tbl_wang_weighing ADD COLUMN weight_updated_by VARCHAR(255) NULL');

}

function weighing_for_rubber(mysqli $db, int $id): ?array {
    $stmt = $db->prepare('SELECT x.*, w.wang_note, w.collection_round_id, c.weigh_date AS collection_weigh_date FROM tbl_wang_weighing x JOIN tbl_wangyang w ON w.wang_id=x.wang_id LEFT JOIN tbl_collection_rounds c ON c.round_id=w.collection_round_id WHERE x.ru_id = ?');
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
function record_wang_weighing(mysqli $db, int $wangId, string $weight, int $priceId, string $actor, ?string $expectedLane = null, ?int $expectedRoundId = null): int {
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
        if (collection_is_cancelled($wang)) {
            throw new RuntimeException('รายการวางยางนี้ถูกยกเลิกแล้ว');
        }
        if (!in_array((string)$wang['wang_lan'], ['1', '2', '3', '4'], true)
            || trim($wang['wang_number']) === '' || trim($wang['wang_name']) === ''
            || trim($wang['wang_class']) === '' || trim($wang['wang_group']) === '') {
            throw new RuntimeException('กรุณาแก้ไขข้อมูลผู้ส่งยาง เลขที่ กลุ่ม ชั้น และลานให้ครบก่อนชั่ง');
        }
        $price = latest_rubber_price($db, true);
        if (!$price || (float)$price['pr_price'] <= 0) throw new DomainException('กรุณาบันทึกราคายางก่อนเริ่มชั่ง');
        $round = price_date_collection_round($db, $price['pr_date']);
        $roundId = (int)$round['round_id'];
        if ($expectedRoundId !== null && $expectedRoundId !== $roundId) throw new DomainException('รอบราคาล่าสุดเปลี่ยนแล้ว กรุณาโหลดหน้าใหม่');
        if ((int)$price['pr_id'] !== $priceId) throw new DomainException('ราคาล่าสุดเปลี่ยนแล้ว กรุณาโหลดหน้าใหม่');
        if (!can_weigh_on_price_date($round['weigh_date'])) throw new DomainException('ยังไม่ถึงวันที่ขาย / ชั่งยางของราคาล่าสุด');
        if ($wang['wang_date'] < $round['lay_start'] || $wang['wang_date'] > $round['lay_end']) throw new DomainException('เรียกชั่งได้เฉพาะรายการวางยาง 2 วันก่อนวันที่ราคายางล่าสุดเท่านั้น');
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
        $savedDate = substr($now, 0, 10);
        $stmt = $db->prepare('INSERT INTO tbl_rubber
            (ru_date, ru_lan, ru_group, ru_number, ru_fullname, ru_class, ru_quantity,
             ru_hoon, ru_loan, ru_shortdebt, ru_deposit, ru_tradeloan, ru_insurance,
             ru_value, ru_expend, ru_netvalue, ru_saveby, ru_savedate)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0,
                    ROUND(CAST(? AS DECIMAL(18,2)) * CAST(? AS DECIMAL(18,2)), 2), 0, ROUND(CAST(? AS DECIMAL(18,2)) * CAST(? AS DECIMAL(18,2)), 2), ?, ?)');
        $stmt->bind_param('sssssssssssss', $round['weigh_date'], $wang['wang_lan'], $wang['wang_group'],
            $wang['wang_number'], $wang['wang_name'], $wang['wang_class'], $weight,
            $weight, $price['pr_price'], $weight, $price['pr_price'], $actor, $savedDate);
        $stmt->execute();
        $rubberId = $db->insert_id;
        $stmt->close();
        $stmt = $db->prepare('INSERT INTO tbl_wang_weighing
            (wang_id, ru_id, net_weight, price_id, price_per_kg, round_date, weighed_by, weighed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iisissss', $wangId, $rubberId, $weight, $priceId, $price['pr_price'], $round['weigh_date'], $actor, $now);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('UPDATE tbl_wangyang SET wang_weight = ?, collection_round_id=? WHERE wang_id = ?');
        $stmt->bind_param('sii', $weight, $roundId, $wangId);
        $stmt->execute();
        $stmt->close();
        record_collection_audit($db, 'weigh', $wangId, $rubberId, $actor, ['net_weight'=>$weight, 'price'=>$price]);
        $db->commit();
        return $rubberId;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}

function mark_weighing_deductions_saved(mysqli $db, int $rubberId, string $actor): void {
    $approval = weighing_for_rubber($db, $rubberId);
    if ($approval && $approval['deductions_saved_at'] === null && !in_array($approval['approval_route'], ['zero', 'review'], true)) throw new DomainException('กรุณาดำเนินการจากหน้าอนุมัติก่อน');
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE tbl_wang_weighing SET deductions_saved_at = ?, deductions_saved_by = ? WHERE ru_id = ?');
    $stmt->bind_param('ssi', $now, $actor, $rubberId);
    $stmt->execute();
    $stmt->close();
    record_collection_audit($db, $approval && $approval['deductions_saved_at'] !== null ? 'deductions_edit' : 'deductions_save', 0, $rubberId, $actor, ['previous'=>$approval]);
}

function collection_workflow_counts(mysqli $db, string $date, string $lane = 'all', ?int $roundId = null): array {
    $sql = "SELECT COUNT(*) AS placed,
        COALESCE(SUM(x.weighing_id IS NULL),0) AS pending_weighing,
        COALESCE(SUM(x.weighing_id IS NOT NULL AND x.deductions_saved_at IS NULL),0) AS pending_deductions,
        COALESCE(SUM(x.deductions_saved_at IS NOT NULL),0) AS completed
        FROM tbl_wangyang w LEFT JOIN tbl_wang_weighing x ON x.wang_id = w.wang_id
        WHERE (? = '' OR w.wang_date = ?) AND LOWER(TRIM(COALESCE(w.record_status, 'normal'))) NOT IN ('cancelled','canceled','deleted') AND LOWER(TRIM(COALESCE(w.wang_status, ''))) NOT IN ('cancelled','canceled','deleted')";
    $params = [$date, $date]; $types = 'ss';
    if ($lane !== 'all') { $sql .= ' AND w.wang_lan = ?'; $params[] = $lane; $types .= 's'; }
    if ($roundId !== null) { $sql .= ' AND w.collection_round_id = ?'; $params[] = $roundId; $types .= 'i'; }
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
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
    assert_rubber_not_cancelled($db, $rubberId);
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
    assert_rubber_not_cancelled($db, $id);
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
    assert_rubber_not_cancelled($db, $id);
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $alreadyIssued = rubber_receipt_issued($db, $id) !== null;
    $snapshot = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = $db->prepare('INSERT INTO tbl_rubber_receipts (ru_id, issued_at, issued_by, original_data)
        VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE ru_id = tbl_rubber_receipts.ru_id');
    $stmt->bind_param('isss', $id, $now, $actor, $snapshot);
    $stmt->execute();
    $stmt->close();
    record_collection_audit($db, $alreadyIssued ? 'receipt_reprint' : 'receipt_issue', 0, $id, $actor, ['receipt'=>$row]);
}

function record_receipt_admin_edit(mysqli $db, int $id, string $actor): void {
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE tbl_rubber_receipts SET last_edited_at = ?, last_edited_by = ? WHERE ru_id = ?');
    $stmt->bind_param('ssi', $now, $actor, $id);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare('SELECT * FROM tbl_rubber WHERE ru_id=?');
    $stmt->bind_param('i', $id); $stmt->execute(); $purchase = $stmt->get_result()->fetch_assoc(); $stmt->close();
    record_collection_audit($db, 'admin_edit', 0, $id, $actor, ['after'=>$purchase]);
}

function wang_record_lock_label(array $state): string {
    if (collection_is_cancelled($state)) return 'ยกเลิก';
    if (!empty($state['receipt_issued_at'])) return 'ออกใบเสร็จแล้ว — ล็อกข้อมูลวางยาง';
    if (!empty($state['deductions_saved_at'])) return 'บันทึกยอดหักแล้ว — ล็อกข้อมูลวางยาง';
    if (!empty($state['weighing_id'])) return 'ชั่งน้ำหนักแล้ว — ล็อกข้อมูลวางยาง';
    return '';
}

function wang_record_lock_reason(mysqli $db, int $id): string {
    $stmt = $db->prepare('SELECT w.record_status, w.wang_status, x.weighing_id, x.deductions_saved_at, z.issued_at AS receipt_issued_at
        FROM tbl_wangyang w LEFT JOIN tbl_wang_weighing x ON x.wang_id=w.wang_id LEFT JOIN tbl_rubber_receipts z ON z.ru_id=x.ru_id WHERE w.wang_id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $state = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return wang_record_lock_label($state ?: []);
}

function update_wang_weighing(mysqli $db, int $wangId, string $weight, string $actor, string $lane, ?int $expectedRoundId = null): int {
    $weight = trim($weight);
    if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $weight) || (float)$weight <= 0) {
        throw new InvalidArgumentException('กรุณากรอกน้ำหนักสุทธิมากกว่า 0 ทศนิยมไม่เกิน 2 ตำแหน่ง');
    }
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT wang_lan, collection_round_id FROM tbl_wangyang WHERE wang_id=? FOR UPDATE');
        $stmt->bind_param('i', $wangId); $stmt->execute(); $source = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($source && $expectedRoundId !== null && (int)$source['collection_round_id'] !== $expectedRoundId) throw new DomainException('รายการไม่อยู่ในรอบที่เลือก');
        if (!$source || !in_array($lane, ['1','2','3','4'], true) || $source['wang_lan'] !== $lane) throw new RuntimeException('รายการวางยางไม่ตรงกับลานที่เลือก');
        $stmt = $db->prepare('SELECT ru_id FROM tbl_wang_weighing WHERE wang_id=?');
        $stmt->bind_param('i', $wangId); $stmt->execute(); $id = (int)($stmt->get_result()->fetch_row()[0] ?? 0); $stmt->close();
        if (!$id) throw new RuntimeException('ยังไม่มีผลชั่งสำหรับรายการนี้');
        lock_rubber_for_edit($db, $id, false);
        $stmt = $db->prepare('SELECT * FROM tbl_wang_weighing WHERE wang_id=? FOR UPDATE');
        $stmt->bind_param('i', $wangId); $stmt->execute(); $weighing = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($weighing['deductions_saved_at'] !== null || $weighing['approval_route'] !== null) throw new RuntimeException('รายการนี้ส่งตรวจสอบหรืออนุมัติแล้ว ไม่สามารถแก้น้ำหนักจากหน้าชั่งได้');
        $stmt = $db->prepare('UPDATE tbl_rubber SET ru_quantity=?, ru_value=ROUND(CAST(? AS DECIMAL(18,2))*CAST(? AS DECIMAL(18,2)),2), ru_netvalue=ROUND(CAST(? AS DECIMAL(18,2))*CAST(? AS DECIMAL(18,2)),2)-ru_expend WHERE ru_id=?');
        $price = $weighing['price_per_kg'];
        $stmt->bind_param('sssssi', $weight, $weight, $price, $weight, $price, $id); $stmt->execute(); $stmt->close();
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE tbl_wang_weighing SET net_weight=?, weight_updated_at=?, weight_updated_by=? WHERE wang_id=?');
        $stmt->bind_param('sssi', $weight, $now, $actor, $wangId); $stmt->execute(); $stmt->close();
        $stmt = $db->prepare('UPDATE tbl_wangyang SET wang_weight=? WHERE wang_id=?');
        $stmt->bind_param('si', $weight, $wangId); $stmt->execute(); $stmt->close();
        record_collection_audit($db, 'weight_edit', $wangId, $id, $actor, ['before'=>$weighing['net_weight'], 'after'=>$weight]);
        $db->commit();
        return $id;
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

// Read current weight after acquiring the purchase lock, never reuse a stale form snapshot.
function refresh_weighed_financial_data(mysqli $db, int $id, array $data): array {
    $stmt = $db->prepare('SELECT r.*, x.net_weight, x.price_per_kg FROM tbl_rubber r JOIN tbl_wang_weighing x ON x.ru_id=r.ru_id WHERE r.ru_id=? FOR UPDATE');
    $stmt->bind_param('i', $id); $stmt->execute(); $fresh = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$fresh) return $data;
    $approval = weighing_for_rubber($db, $id);
    if ($approval['deductions_saved_at'] === null && $approval['approval_route'] !== 'review') throw new DomainException('กรุณาส่งตรวจสอบยอดหักจากหน้าอนุมัติก่อนบันทึกยอดหัก');
    foreach (['ru_date','ru_lan','ru_group','ru_number','ru_fullname','ru_class'] as $field) $data[$field] = $fresh[$field];
    $data['ru_quantity'] = $fresh['net_weight'];
    $stmt = $db->prepare('SELECT v.amount AS ru_value, v.amount-CAST(? AS DECIMAL(18,2)) AS ru_netvalue FROM (SELECT ROUND(CAST(? AS DECIMAL(18,2))*CAST(? AS DECIMAL(18,2)),2) AS amount) v');
    $stmt->bind_param('sss', $data['ru_expend'], $fresh['net_weight'], $fresh['price_per_kg']); $stmt->execute(); $totals = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return array_merge($data, $totals);
}

// Lock purchase before weighing, matching deduction saves and receipt issuance.
function approve_weighed_purchase(mysqli $db, int $id, string $route, string $actor, ?int $expectedRoundId = null): void {
    if (!in_array($route, ['zero', 'review'], true)) throw new DomainException('ขั้นตอนไม่ถูกต้อง');
    $db->begin_transaction();
    try {
        lock_rubber_for_edit($db, $id, false);
        $stmt = $db->prepare('SELECT * FROM tbl_wang_weighing WHERE ru_id=? FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($expectedRoundId !== null) assert_purchase_collection_round($db, $id, $expectedRoundId);
        if (!$row || $row['deductions_saved_at'] !== null || $row['approval_route'] !== null) throw new DomainException('รายการนี้ดำเนินการแล้ว หรือไม่พบผลชั่ง กรุณาโหลดหน้าใหม่');
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE tbl_wang_weighing SET approval_route=?, approved_at=?, approved_by=? WHERE ru_id=?');
        $stmt->bind_param('sssi', $route, $now, $actor, $id); $stmt->execute(); $stmt->close();
        if ($route === 'zero') {
            $stmt = $db->prepare('UPDATE tbl_rubber SET ru_hoon=0, ru_loan=0, ru_shortdebt=0, ru_deposit=0, ru_tradeloan=0, ru_insurance=0, ru_expend=0, ru_netvalue=ru_value WHERE ru_id=?');
            $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
            mark_weighing_deductions_saved($db, $id, $actor);
        }
        record_collection_audit($db, $route === 'zero' ? 'approve_zero' : 'refer_review', (int)$row['wang_id'], $id, $actor);
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

// Preserve every return and serialize against deduction saves and receipt issuance.
function return_weighed_purchase_to_approval(mysqli $db, int $id, string $actor, ?int $expectedRoundId = null): void {
    $db->begin_transaction();
    try {
        lock_rubber_for_edit($db, $id, false);
        $stmt = $db->prepare('SELECT * FROM tbl_wang_weighing WHERE ru_id=? FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($expectedRoundId !== null) assert_purchase_collection_round($db, $id, $expectedRoundId);
        if (!$row || $row['approval_route'] !== 'review' || $row['deductions_saved_at'] !== null) {
            throw new DomainException('ย้อนกลับได้เฉพาะรายการส่งตรวจสอบที่ยังไม่ได้บันทึกยอดหัก กรุณาโหลดหน้าใหม่');
        }
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('INSERT INTO tbl_weighing_review_returns (ru_id, referred_at, referred_by, returned_at, returned_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issss', $id, $row['approved_at'], $row['approved_by'], $now, $actor); $stmt->execute(); $stmt->close();
        $stmt = $db->prepare('UPDATE tbl_wang_weighing SET approval_route=NULL, approved_at=NULL, approved_by=NULL WHERE ru_id=?');
        $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
        record_collection_audit($db, 'return_approval', (int)$row['wang_id'], $id, $actor, ['previous_referral'=>$row]);
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

// Append events inside the caller's transaction; actor identity comes from the session.
function record_collection_audit(mysqli $db, string $action, int $wangId, int $rubberId, string $actor, array $details = []): void {
    $user = current_user() ?? [];
    $actorId = isset($user['user_id']) ? (int)$user['user_id'] : null;
    $username = (string)($user['user_username'] ?? '');
    $actorName = (string)(($user['user_fullname'] ?? '') ?: ($username ?: $actor));
    if (!$wangId && $rubberId) {
        $stmt = $db->prepare('SELECT wang_id FROM tbl_wang_weighing WHERE ru_id=?');
        $stmt->bind_param('i', $rubberId); $stmt->execute(); $wangId = (int)($stmt->get_result()->fetch_row()[0] ?? 0); $stmt->close();
    }
    $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $stmt = $db->prepare('INSERT INTO tbl_collection_audit (wang_id, ru_id, action, actor_id, actor_username, actor_name, recorded_at, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iisissss', $wangId, $rubberId, $action, $actorId, $username, $actorName, $now, $json);
    $stmt->execute(); $stmt->close();
}

// Keep placement writes and their before/after history atomic.
function execute_wang_with_audit(mysqli $db, mysqli_stmt $mutation, int $id, string $action, ?int $roundId = null): bool {
    $db->begin_transaction();
    try {
        $before = null;
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM tbl_wangyang WHERE wang_id=? FOR UPDATE');
            $stmt->bind_param('i', $id); $stmt->execute(); $before = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($before && collection_is_cancelled($before)) throw new DomainException('รายการนี้ยกเลิกแล้ว ไม่สามารถแก้ไขหรือลบได้');
        }
        if ($before && $roundId !== null && (int)$before['collection_round_id'] !== $roundId) throw new DomainException('รายการไม่อยู่ในรอบที่เลือก');
        $ok = $mutation->execute();
        if (!$ok) throw new RuntimeException('บันทึกข้อมูลไม่สำเร็จ');
        if ($mutation->affected_rows > 0) {
            $recordId = $id ?: (int)$mutation->insert_id;
            $stmt = $db->prepare('SELECT * FROM tbl_wangyang WHERE wang_id=?');
            $stmt->bind_param('i', $recordId); $stmt->execute(); $after = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($after) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$after['wang_date']);
                if (!$date || $date->format('Y-m-d') !== $after['wang_date']) throw new DomainException('วันที่วางยางไม่ถูกต้อง');
            }
            if ($before && $after && $before['wang_date'] !== $after['wang_date']) {
                if (!is_admin()) throw new DomainException('เฉพาะ admin เท่านั้นที่แก้ไขวันที่วางยางได้');
                if (wang_has_weighing($db, $recordId)) throw new DomainException('รายการที่ชั่งน้ำหนักแล้วไม่สามารถแก้ไขวันที่วางยางได้');
            }
            $effectiveRoundId = $roundId ?? 0;
            if ($after && $effectiveRoundId) {
                $round = collection_round_by_id($db, $effectiveRoundId, true);
                if (!$round || $after['wang_date'] < $round['lay_start'] || $after['wang_date'] > $round['lay_end']) throw new DomainException('วันที่วางยางต้องอยู่ในช่วงวางยาง 2 วันของรอบนี้');
                $stmt = $db->prepare('UPDATE tbl_wangyang SET collection_round_id=? WHERE wang_id=?');
                $stmt->bind_param('ii', $effectiveRoundId, $recordId); $stmt->execute(); $stmt->close();
                $after['collection_round_id'] = $effectiveRoundId;
            }
            record_collection_audit($db, $action, $recordId, 0, '', ['before'=>$before, 'after'=>$after]);
        }
        $db->commit();
        return $ok;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

// Purchase editors must preserve the confirmed weight; corrections belong to the weighing page.
// Caller holds the purchase lock, so approval cannot race this check.
function assert_weighed_weight_unchanged(mysqli $db, int $id, string $weight): void {
    $weighing = weighing_for_rubber($db, $id);
    if ($weighing && number_format((float)$weight, 2, '.', '') !== number_format((float)$weighing['net_weight'], 2, '.', '')) {
        throw new DomainException('ไม่สามารถแก้น้ำหนักจากหน้าจัดการได้ ให้แก้ไขจากหน้าชั่งก่อนอนุมัติหรือส่งตรวจสอบยอดหักเท่านั้น');
    }
}

function delete_unlinked_rubber(mysqli $db, int $id): void {
    if (!is_admin()) throw new DomainException('เฉพาะ admin เท่านั้นที่ลบข้อมูลได้');
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT * FROM tbl_rubber WHERE ru_id=? FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute(); $before = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$before) throw new DomainException('ไม่พบรายการที่ต้องการลบ');
        if (collection_is_cancelled($before)) throw new DomainException('รายการนี้ยกเลิกแล้ว ต้องเก็บไว้ตรวจสอบ');
        if (rubber_receipt_issued($db, $id) || weighing_for_rubber($db, $id)) {
            throw new DomainException('รายการนี้เชื่อมกับผลชั่งหรือใบเสร็จ ให้ดำเนินการแก้ไขข้อมูลแทนการลบ');
        }
        $stmt = $db->prepare('DELETE FROM tbl_rubber WHERE ru_id=? LIMIT 1');
        $stmt->bind_param('i', $id); $stmt->execute(); $deleted = $stmt->affected_rows; $stmt->close();
        if ($deleted !== 1) throw new DomainException('ไม่พบรายการที่ต้องการลบ');
        record_collection_audit($db, 'purchase_admin_delete', 0, $id, '', ['before'=>$before, 'after'=>null]);
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

function issued_rubber_receipts(mysqli $db, string $search = '', string $lane = 'all', int $page = 1, bool $allRows = false, ?string $roundDate = null): array {
    if (!in_array($lane, ['all','1','2','3','4'], true)) $lane = 'all';
    $from = ' FROM tbl_rubber_receipts z JOIN tbl_rubber r ON r.ru_id=z.ru_id
        LEFT JOIN tbl_wang_weighing x ON x.ru_id=r.ru_id LEFT JOIN tbl_wangyang w ON w.wang_id=x.wang_id LEFT JOIN tbl_collection_rounds c ON c.round_id=w.collection_round_id';
    $conditions = ["COALESCE(r.record_status,'normal') NOT IN ('cancelled','canceled','deleted')"]; $params = []; $types = '';
    if ($roundDate !== null) { $conditions[] = 'COALESCE(x.round_date, r.ru_date)=?'; $params[] = $roundDate; $types .= 's'; }
    if ($lane !== 'all') { $conditions[] = 'r.ru_lan=?'; $params[] = $lane; $types .= 's'; }
    if (trim($search) !== '') {
        $conditions[] = '(r.ru_fullname LIKE ? OR r.ru_number LIKE ? OR r.ru_group LIKE ? OR w.wang_note LIKE ?)';
        $like = '%' . trim($search) . '%';
        for ($i=0; $i<4; $i++) { $params[] = $like; $types .= 's'; }
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $stmt = $db->prepare('SELECT COUNT(*)' . $from . $where);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute(); $total = (int)$stmt->get_result()->fetch_row()[0]; $stmt->close();
    $perPage = 25; $totalPages = max(1, (int)ceil($total / $perPage)); $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $stmt = $db->prepare('SELECT r.*, w.wang_id, w.wang_note, w.wang_sack, c.weigh_date AS collection_weigh_date, z.issued_at, z.issued_by' . $from . $where . ' ORDER BY COALESCE(x.deductions_saved_at, z.issued_at) DESC, r.ru_id DESC' . ($allRows ? '' : ' LIMIT ? OFFSET ?'));
    if (!$allRows) { $params[] = $perPage; $params[] = $offset; $types .= 'ii'; }
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return ['rows'=>$rows, 'total'=>$total, 'page'=>$page, 'total_pages'=>$totalPages, 'per_page'=>$perPage];
}

// A round deduction entry always uses the locked source weight and fixed price.
function save_round_rubber_deductions(mysqli $db, int $id, array $input, string $actor, int $roundId): void {
    $deductions = normalize_rubber_deductions($input);
    $db->begin_transaction();
    try {
        lock_rubber_for_edit($db, $id, false);
        $stmt = $db->prepare('SELECT * FROM tbl_rubber WHERE ru_id=? FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute(); $before = $stmt->get_result()->fetch_assoc(); $stmt->close();
        $weighing = weighing_for_rubber($db, $id);
        assert_purchase_collection_round($db, $id, $roundId);
        if (!$before || !$weighing || $weighing['approval_route'] !== 'review' || $weighing['deductions_saved_at'] !== null) {
            throw new DomainException('รายการนี้ไม่อยู่ในคิวบันทึกยอดหักของรอบนี้ หรือดำเนินการไปแล้ว กรุณาโหลดรายการใหม่');
        }
        $values = array_values($deductions);
        $stmt = $db->prepare('SELECT CAST(? AS DECIMAL(18,2))+CAST(? AS DECIMAL(18,2))+CAST(? AS DECIMAL(18,2))+CAST(? AS DECIMAL(18,2))+CAST(? AS DECIMAL(18,2))+CAST(? AS DECIMAL(18,2)) AS total');
        $stmt->bind_param('ssssss', ...$values); $stmt->execute(); $total = $stmt->get_result()->fetch_row()[0]; $stmt->close();
        $financial = refresh_weighed_financial_data($db, $id, ['ru_expend'=>$total]);
        $savedDate = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
        $params = array_merge($values, [$financial['ru_value'], $total, $financial['ru_netvalue'], $actor, $savedDate, $id]);
        $stmt = $db->prepare('UPDATE tbl_rubber SET ru_hoon=?, ru_loan=?, ru_shortdebt=?, ru_deposit=?, ru_tradeloan=?, ru_insurance=?, ru_value=?, ru_expend=?, ru_netvalue=?, ru_saveby=?, ru_savedate=? WHERE ru_id=?');
        $stmt->bind_param('sssssssssssi', ...$params); $stmt->execute(); $stmt->close();
        record_collection_audit($db, 'purchase_edit', 0, $id, $actor, ['before'=>$before, 'after'=>$deductions + ['ru_expend'=>$total, 'ru_netvalue'=>$financial['ru_netvalue']]]);
        mark_weighing_deductions_saved($db, $id, $actor);
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

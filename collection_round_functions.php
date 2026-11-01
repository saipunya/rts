<?php
declare(strict_types=1);

function placement_entry_date(): string {
    return (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
}

function can_weigh_on_price_date(string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Bangkok'));
    return $parsed && $parsed->format('Y-m-d') === $date && placement_entry_date() >= $date;
}

function ensure_collection_round_schema(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS tbl_collection_rounds (
        round_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        weigh_date DATE NOT NULL,
        lay_start DATE NOT NULL,
        lay_end DATE NOT NULL,
        created_at DATETIME NOT NULL,
        created_by VARCHAR(255) NOT NULL,
        UNIQUE KEY uq_collection_weigh_date (weigh_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach (['tbl_wangyang','tbl_price'] as $table) {
        $columns = array_column($db->query("SHOW COLUMNS FROM $table")->fetch_all(MYSQLI_ASSOC), 'Field');
        if (!in_array('collection_round_id', $columns, true)) {
            $db->query("ALTER TABLE $table ADD COLUMN collection_round_id INT NULL, ADD KEY idx_collection_round (collection_round_id)");
        }
    }
}

function collection_round_by_id(mysqli $db, int $id, bool $lock = false): ?array {
    $stmt = $db->prepare('SELECT * FROM tbl_collection_rounds WHERE round_id=?' . ($lock ? ' LOCK IN SHARE MODE' : ''));
    $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $row ?: null;
}

function collection_round_options(mysqli $db): array {
    return $db->query('SELECT * FROM tbl_collection_rounds ORDER BY weigh_date DESC, round_id DESC')->fetch_all(MYSQLI_ASSOC);
}

// Internal round metadata is derived from the price date; operators never configure it separately.
function price_date_collection_round(mysqli $db, string $date): array {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new DomainException('วันที่ราคายางไม่ถูกต้อง');
    $stmt = $db->prepare('SELECT * FROM tbl_collection_rounds WHERE weigh_date=?');
    $stmt->bind_param('s', $date); $stmt->execute(); $round = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($round) return $round;
    $start = $parsed->modify('-2 days')->format('Y-m-d'); $end = $parsed->modify('-1 day')->format('Y-m-d');
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $user = current_user(); $actor = (string)(($user['user_fullname'] ?? '') ?: ($user['user_username'] ?? 'ระบบ'));
    $stmt = $db->prepare('INSERT INTO tbl_collection_rounds (weigh_date,lay_start,lay_end,created_at,created_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE round_id=LAST_INSERT_ID(round_id)');
    $stmt->bind_param('sssss', $date, $start, $end, $now, $actor); $stmt->execute(); $stmt->close();
    $stmt = $db->prepare('SELECT * FROM tbl_collection_rounds WHERE weigh_date=?');
    $stmt->bind_param('s', $date); $stmt->execute(); $round = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $round;
}

function latest_rubber_price(mysqli $db, bool $lock = false): ?array {
    $sql = 'SELECT * FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1' . ($lock ? ' LOCK IN SHARE MODE' : '');
    return $db->query($sql)->fetch_assoc() ?: null;
}

function latest_priced_collection_round(mysqli $db): ?array {
    $price = latest_rubber_price($db);
    return $price ? price_date_collection_round($db, $price['pr_date']) : null;
}

function selected_collection_round(mysqli $db, bool $failIfMissing = true): ?array {
    $requested = $_POST['round'] ?? $_GET['round'] ?? null;
    if ($requested !== null) {
        $round = collection_round_by_id($db, (int)$requested);
        if (!$round && $failIfMissing) { http_response_code(404); exit('ไม่พบรอบรวบรวมยาง'); }
        return $round ?: null;
    }
    return latest_priced_collection_round($db);
}

function create_collection_round(mysqli $db, string $weighDate, string $actor): int {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $weighDate, new DateTimeZone('Asia/Bangkok'));
    if (!$date || $date->format('Y-m-d') !== $weighDate) throw new DomainException('กรุณากำหนดวันชั่งที่ถูกต้อง');
    $start = $date->modify('-2 days')->format('Y-m-d');
    $end = $date->modify('-1 day')->format('Y-m-d');
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT round_id FROM tbl_collection_rounds WHERE lay_start<=? AND lay_end>=? FOR UPDATE');
        $stmt->bind_param('ss', $end, $start); $stmt->execute(); $overlap = $stmt->get_result()->num_rows > 0; $stmt->close();
        if ($overlap) throw new DomainException('ช่วงวันวางยางของรอบนี้ซ้อนกับรอบที่มีอยู่แล้ว');
        $stmt = $db->prepare('INSERT INTO tbl_collection_rounds (weigh_date,lay_start,lay_end,created_at,created_by) VALUES (?,?,?,?,?)');
        $stmt->bind_param('sssss', $weighDate, $start, $end, $now, $actor); $stmt->execute(); $id = (int)$db->insert_id; $stmt->close();
        // Only dates inside this explicitly configured placement window are assigned.
        // Existing weights, financial dates and frozen prices are never rewritten.
        $stmt = $db->prepare('UPDATE tbl_wangyang SET collection_round_id=? WHERE collection_round_id IS NULL AND wang_date BETWEEN ? AND ?');
        $stmt->bind_param('iss', $id, $start, $end); $stmt->execute(); $assigned = $stmt->affected_rows; $stmt->close();
        record_collection_audit($db, 'round_create', 0, 0, $actor, ['round_id'=>$id,'weigh_date'=>$weighDate,'lay_start'=>$start,'lay_end'=>$end,'assigned_placements'=>$assigned]);
        $db->commit(); return $id;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

function latest_collection_round_price(mysqli $db, int $roundId, bool $lock = false): ?array {
    $stmt = $db->prepare('SELECT p.* FROM tbl_price p JOIN tbl_collection_rounds c ON c.weigh_date=p.pr_date WHERE c.round_id=? ORDER BY p.pr_id DESC LIMIT 1' . ($lock ? ' LOCK IN SHARE MODE' : ''));
    $stmt->bind_param('i', $roundId); $stmt->execute(); $price = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $price ?: null;
}

function collection_round_label(?array $round): string {
    return $round ? 'รอบขายยาง ' . thai_date_format($round['weigh_date']) : 'ยังไม่ได้กำหนดรอบรวบรวม';
}

function assert_purchase_collection_round(mysqli $db, int $id, int $roundId): void {
    $stmt = $db->prepare('SELECT w.collection_round_id FROM tbl_wang_weighing x JOIN tbl_wangyang w ON w.wang_id=x.wang_id WHERE x.ru_id=?');
    $stmt->bind_param('i', $id); $stmt->execute(); $actual = (int)($stmt->get_result()->fetch_row()[0] ?? 0); $stmt->close();
    if ($roundId <= 0 || $actual !== $roundId) throw new DomainException('รายการไม่อยู่ในรอบรวบรวมที่เลือก');
}

// Reporting dates follow the configured sale date; unassigned legacy prices retain their old date.
function collection_price_dates_sql(): string {
    return 'SELECT p.pr_date,p.pr_price,p.pr_id FROM tbl_price p JOIN (SELECT pr_date,MAX(pr_id) AS latest_id FROM tbl_price GROUP BY pr_date) d ON d.latest_id=p.pr_id';
}

function weighing_window_counts(mysqli $db, ?array $round, string $lane = 'all'): array {
    $start = $round['lay_start'] ?? '1000-01-01'; $end = $round['lay_end'] ?? '1000-01-01'; $date = $round['weigh_date'] ?? '1000-01-01';
    $sql = "SELECT COUNT(*) AS placed,
        COALESCE(SUM(x.weighing_id IS NULL),0) AS pending_weighing,
        COALESCE(SUM(x.weighing_id IS NOT NULL AND x.deductions_saved_at IS NULL),0) AS pending_deductions,
        COALESCE(SUM(x.deductions_saved_at IS NOT NULL),0) AS completed
        FROM tbl_wangyang w LEFT JOIN tbl_wang_weighing x ON x.wang_id=w.wang_id
        WHERE w.wang_date BETWEEN ? AND ? AND (x.weighing_id IS NULL OR x.round_date=?)
        AND LOWER(TRIM(COALESCE(w.record_status, 'normal'))) NOT IN ('cancelled','canceled','deleted') AND LOWER(TRIM(COALESCE(w.wang_status, ''))) NOT IN ('cancelled','canceled','deleted')";
    $params = [$start,$end,$date]; $types = 'sss';
    if ($lane !== 'all') { $sql .= ' AND w.wang_lan=?'; $params[]=$lane; $types.='s'; }
    $stmt=$db->prepare($sql); $stmt->bind_param($types,...$params); $stmt->execute(); $counts=$stmt->get_result()->fetch_assoc(); $stmt->close();
    return array_map('intval',$counts);
}

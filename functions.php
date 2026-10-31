<?php
declare(strict_types=1);

// Report incomplete uploads before requiring any shared application files.
$requiredAppFiles = ['app_bootstrap.php', 'collection_round_functions.php', 'weighing_functions.php'];
$missingAppFiles = [];
foreach ($requiredAppFiles as $requiredAppFile) {
    if (!is_file(__DIR__ . '/' . $requiredAppFile)) $missingAppFiles[] = $requiredAppFile;
}
if ($missingAppFiles) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('อัปโหลดไฟล์ระบบไม่ครบ กรุณาอัปโหลดไฟล์ต่อไปนี้ไว้ในโฟลเดอร์เดียวกับ functions.php: ' . implode(', ', $missingAppFiles));
}
unset($requiredAppFiles, $missingAppFiles, $requiredAppFile);

require_once __DIR__ . '/app_bootstrap.php';

function db(): mysqli {
	static $db;
	if (!$db) {
		$config_path = __DIR__ . '/db_config.php';
		if (!file_exists($config_path)) {
			// แจ้งเตือนถ้าไม่มีไฟล์ Config (สำคัญมากบน Production)
			die('Error: Missing database configuration file (db_config.php). Please create it from db_config.sample.php');
		}
		require $config_path;

		$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
		if ($db->connect_errno) {
			die('DB connect error: ' . $db->connect_error);
		}
		$db->set_charset('utf8mb4');
	}
	return $db;
}

// Backwards compatibility: expose a global $mysqli variable many files expect
// and a USER_TABLE constant for the users table name.
if (!defined('USER_TABLE')) {
	define('USER_TABLE', 'tbl_user');
}
// Provide $mysqli global for older code that uses it
$GLOBALS['mysqli'] = db();

function e($v): string {
	return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Format a placement note for display without changing the stored person's name.
function collection_display_name(?string $name, ?string $note = null): string {
    $name = (string)$name;
    $note = trim((string)$note);
    if ($note === '') return $name;
    $suffix = substr($note, 0, 1) === '(' && substr($note, -1) === ')' ? $note : '(' . $note . ')';
    return $name . ' ' . $suffix;
}

function csrf_token(): string {
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

function csrf_check(string $token): bool {
	return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// helper to check admin
function is_admin(): bool {
    return isset($_SESSION['user_level']) && $_SESSION['user_level'] === 'admin';
}

function require_admin() {
    if (!is_admin()) {
        header('Location: index.php?msg=' . urlencode('Access denied: admin only'));
        exit;
    }
}

// helper to check logged in
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php?msg=' . urlencode('Please login'));
        exit;
    }
}

function current_user() {
    if (!is_logged_in()) return null;
    return [
        'user_id' => $_SESSION['user_id'],
        'user_username' => $_SESSION['user_username'] ?? null,
        'user_fullname' => $_SESSION['user_fullname'] ?? null,
        'user_level' => $_SESSION['user_level'] ?? null,
        'user_status' => $_SESSION['user_status'] ?? null,
    ];
}

function ensure_member_portal_usage_table(mysqli $db): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS tbl_member_portal_log (
            log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            mem_id INT UNSIGNED NOT NULL,
            mem_number VARCHAR(50) NOT NULL,
            mem_fullname VARCHAR(255) NOT NULL,
            action_type VARCHAR(20) NOT NULL DEFAULT 'login',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (log_id),
            KEY idx_mem_id (mem_id),
            KEY idx_action_type (action_type),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $db->query($sql);
    $initialized = true;
}

function log_member_portal_usage(array $member, string $actionType = 'login'): void {
    if (empty($member['mem_id'])) {
        return;
    }

    $db = db();
    ensure_member_portal_usage_table($db);

    $memId = (int)$member['mem_id'];
    $memNumber = substr((string)($member['mem_number'] ?? ''), 0, 50);
    $memFullname = substr((string)($member['mem_fullname'] ?? ''), 0, 255);
    $ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $stmt = $db->prepare('INSERT INTO tbl_member_portal_log (mem_id, mem_number, mem_fullname, action_type, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('isssss', $memId, $memNumber, $memFullname, $actionType, $ipAddress, $userAgent);
        $stmt->execute();
        $stmt->close();
    }
}

function clear_member_portal_log_entries(?mysqli $db = null): bool {
    $db = $db ?: db();
    ensure_member_portal_usage_table($db);

    if ($db->query("DELETE FROM tbl_member_portal_log")) {
        return true;
    }

    return false;
}

function fetch_member_portal_usage_stats(mysqli $db): array {
    ensure_member_portal_usage_table($db);

    $stats = [
        'total_logins' => 0,
        'unique_members' => 0,
        'today_logins' => 0,
        'latest_login_at' => null,
        'latest_member_name' => null,
    ];

    $sql = "
        SELECT
            COUNT(*) AS total_logins,
            COUNT(DISTINCT mem_id) AS unique_members,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_logins,
            MAX(created_at) AS latest_login_at,
            SUBSTRING_INDEX(
                GROUP_CONCAT(mem_fullname ORDER BY created_at DESC, log_id DESC SEPARATOR '||'),
                '||',
                1
            ) AS latest_member_name
        FROM tbl_member_portal_log
        WHERE action_type = 'login'
    ";

    if ($result = $db->query($sql)) {
        if ($row = $result->fetch_assoc()) {
            $stats['total_logins'] = (int)($row['total_logins'] ?? 0);
            $stats['unique_members'] = (int)($row['unique_members'] ?? 0);
            $stats['today_logins'] = (int)($row['today_logins'] ?? 0);
            $stats['latest_login_at'] = $row['latest_login_at'] ?? null;
            $stats['latest_member_name'] = $row['latest_member_name'] ?? null;
        }
        $result->free();
    }

    return $stats;
}

function fetch_member_portal_recent_log_entries(int $limit = 10): array {
    $db = db();
    ensure_member_portal_usage_table($db);

    $limit = max(1, $limit);
    $rows = [];
    $sql = "
        SELECT
            l.mem_id,
            l.mem_number,
            l.mem_fullname,
            COALESCE(m.mem_group, '') AS mem_group,
            COALESCE(m.mem_class, '') AS mem_class,
            l.action_type,
            l.ip_address,
            l.user_agent,
            l.created_at
        FROM tbl_member_portal_log l
        LEFT JOIN tbl_member m ON m.mem_id = l.mem_id
        WHERE l.action_type = 'login'
        ORDER BY l.created_at DESC, l.log_id DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }

    return $rows;
}

function fetch_member_portal_period_stats(): array {
    $db = db();
    ensure_member_portal_usage_table($db);

    $today = date('Y-m-d');
    $month = date('Y-m');
    $year = date('Y');
    $monthNames = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];
    $monthNumber = (int)date('n');
    $thaiYear = (int)$year + 543;

    $stats = [
        'today' => 0,
        'month' => 0,
        'year' => 0,
        'today_label' => thai_date_format($today),
        'month_label' => $monthNames[$monthNumber] . ' ' . $thaiYear,
        'year_label' => 'พ.ศ. ' . $thaiYear,
    ];

    $sql = "
        SELECT
            SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) AS today_count,
            SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = ? THEN 1 ELSE 0 END) AS month_count,
            SUM(CASE WHEN YEAR(created_at) = ? THEN 1 ELSE 0 END) AS year_count
        FROM tbl_member_portal_log
        WHERE action_type = 'login'
    ";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $yearInt = (int)$year;
        $stmt->bind_param('ssi', $today, $month, $yearInt);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $stats['today'] = (int)($row['today_count'] ?? 0);
            $stats['month'] = (int)($row['month_count'] ?? 0);
            $stats['year'] = (int)($row['year_count'] ?? 0);
        }
        if ($res) {
            $res->free();
        }
        $stmt->close();
    }

    return $stats;
}

// add function thai_date_format, thai month_name
function thai_date_format(string $date_str): string {
    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];
    $date = strtotime($date_str);
    if ($date === false) {
        return '';
    }
    $day = date('j', $date);
    $month = (int)date('n', $date);
    $year = (int)date('Y', $date) + 543; //
    return sprintf('%d %s %d', $day, $months[$month], $year);
}

function thai_date_short_format(string $value): string {
    $value = trim($value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Bangkok'));
    if (!$date || $date->format('Y-m-d') !== $value) return '';
    $months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return $date->format('j') . ' ' . $months[(int)$date->format('n') - 1] . ' ' . ((int)$date->format('Y') + 543);
}

function thai_datetime_format(string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    foreach (['Y-m-d H:i:s','Y-m-d H:i','Y-m-d'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('Asia/Bangkok'));
        if (!$date || $date->format($format) !== $value) continue;
        $label = thai_date_format($date->format('Y-m-d'));
        if ($format === 'Y-m-d') return $label;
        return $label . ' เวลา ' . $date->format($format === 'Y-m-d H:i:s' ? 'H:i:s' : 'H:i') . ' น.';
    }
    return '';
}

function fetch_rubber_collection_announcement(?mysqli $db = null, ?string $priceDate = null): array {
    $db = $db ?: db();
    $round = null;
    if ($priceDate !== null && $priceDate !== '') {
        $stmt = $db->prepare('SELECT * FROM tbl_collection_rounds WHERE weigh_date=?');
        $stmt->bind_param('s', $priceDate); $stmt->execute(); $round = $stmt->get_result()->fetch_assoc(); $stmt->close();
    }
    if (!$round) $round = selected_collection_round($db);
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
    return [
        'show'=>$round !== null && $today <= $round['weigh_date'],
        'round_id'=>$round ? (int)$round['round_id'] : null,
        'price_date'=>$round['weigh_date'] ?? null,
        'lay_start'=>$round['lay_start'] ?? null,
        'lay_end'=>$round['lay_end'] ?? null,
        'weigh_date'=>$round['weigh_date'] ?? null,
        'price_date_text'=>$round ? thai_date_format($round['weigh_date']) : null,
        'lay_start_text'=>$round ? thai_date_format($round['lay_start']) : null,
        'lay_end_text'=>$round ? thai_date_format($round['lay_end']) : null,
        'weigh_date_text'=>$round ? thai_date_format($round['weigh_date']) : null,
        'today_text'=>thai_date_format($today),
    ];
}

function fetch_rubber_round_live_summary(?mysqli $db = null, ?string $priceDate = null): array {
    $db = $db ?: db();
    $announcement = fetch_rubber_collection_announcement($db, $priceDate);

    if (empty($announcement['show'])) {
        return [
            'show' => false,
            'price_date' => $announcement['price_date'] ?? null,
            'lay_start_text' => $announcement['lay_start_text'] ?? null,
            'lay_end_text' => $announcement['lay_end_text'] ?? null,
            'weigh_date_text' => $announcement['weigh_date_text'] ?? null,
            'total_quantity' => 0.0,
            'total_expend' => 0.0,
            'total_net' => 0.0,
            'updated_at_text' => null,
        ];
    }

    $priceDateValue = (string)($announcement['price_date'] ?? '');
    $summary = [
        'show' => true,
        'price_date' => $priceDateValue,
        'lay_start_text' => $announcement['lay_start_text'] ?? null,
        'lay_end_text' => $announcement['lay_end_text'] ?? null,
        'weigh_date_text' => $announcement['weigh_date_text'] ?? null,
        'total_quantity' => 0.0,
        'total_expend' => 0.0,
        'total_net' => 0.0,
        'updated_at_text' => null,
    ];

    if ($priceDateValue !== '') {
        $sql = "SELECT
            COALESCE(SUM(ru_quantity), 0) AS total_quantity,
            COALESCE(SUM(ru_expend), 0) AS total_expend,
            COALESCE(SUM(ru_netvalue), 0) AS total_net
        FROM tbl_rubber
        WHERE EXISTS (SELECT 1 FROM tbl_wang_weighing x JOIN tbl_wangyang w ON w.wang_id=x.wang_id WHERE x.ru_id=tbl_rubber.ru_id AND w.collection_round_id=?)";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $roundId = (int)($announcement['round_id'] ?? 0);
            $stmt->bind_param('i', $roundId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $summary['total_quantity'] = (float)($row['total_quantity'] ?? 0);
                $summary['total_expend'] = (float)($row['total_expend'] ?? 0);
                $summary['total_net'] = (float)($row['total_net'] ?? 0);
            }
            if ($res) {
                $res->free();
            }
            $stmt->close();
        }
    }

    $summary['updated_at_text'] = thai_date_format(date('Y-m-d')) . ' ' . date('H:i');

    return $summary;
}

function online_presence_window_seconds(): int {
    return 300;
}

function online_presence_prune_seconds(): int {
    return 3600;
}

function ensure_online_presence_table(mysqli $db): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS tbl_online_presence (
            session_id VARCHAR(128) NOT NULL,
            user_id INT UNSIGNED DEFAULT NULL,
            user_name VARCHAR(255) DEFAULT NULL,
            visitor_type ENUM('guest', 'user') NOT NULL DEFAULT 'guest',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (session_id),
            KEY idx_user_id (user_id),
            KEY idx_visitor_type (visitor_type),
            KEY idx_last_seen (last_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $db->query($sql);
    $initialized = true;
}

function prune_online_presence_records(mysqli $db): void {
    ensure_online_presence_table($db);

    $cutoff = date('Y-m-d H:i:s', time() - online_presence_prune_seconds());
    $stmt = $db->prepare('DELETE FROM tbl_online_presence WHERE last_seen < ?');
    if ($stmt) {
        $stmt->bind_param('s', $cutoff);
        $stmt->execute();
        $stmt->close();
    }
}

function touch_online_presence(?mysqli $db = null): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionId = session_id();
    if ($sessionId === '') {
        return;
    }

    $db = $db ?: db();
    ensure_online_presence_table($db);

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $userName = trim((string)($_SESSION['user_fullname'] ?? $_SESSION['user_username'] ?? ''));
    if ($userName === '') {
        $userName = null;
    }
    $visitorType = $userId ? 'user' : 'guest';
    $ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $sql = "
        INSERT INTO tbl_online_presence
            (session_id, user_id, user_name, visitor_type, ip_address, user_agent, created_at, last_seen)
        VALUES
            (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            user_name = VALUES(user_name),
            visitor_type = VALUES(visitor_type),
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent),
            last_seen = NOW()
    ";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sissss', $sessionId, $userId, $userName, $visitorType, $ipAddress, $userAgent);
        $stmt->execute();
        $stmt->close();
    }

    prune_online_presence_records($db);
}

function clear_online_presence_for_current_session(?mysqli $db = null): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionId = session_id();
    if ($sessionId === '') {
        return;
    }

    $db = $db ?: db();
    ensure_online_presence_table($db);

    $stmt = $db->prepare('DELETE FROM tbl_online_presence WHERE session_id = ?');
    if ($stmt) {
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $stmt->close();
    }
}

function fetch_online_presence_stats(?mysqli $db = null): array {
    $db = $db ?: db();
    ensure_online_presence_table($db);
    prune_online_presence_records($db);

    $cutoff = date('Y-m-d H:i:s', time() - online_presence_window_seconds());
    $stats = [
        'online_users' => 0,
        'online_guests' => 0,
        'online_total' => 0,
        'last_seen_at' => null,
    ];

    $sql = "
        SELECT
            COALESCE(COUNT(DISTINCT CASE WHEN visitor_type = 'user' THEN user_id END), 0) AS online_users,
            COALESCE(COUNT(DISTINCT CASE WHEN visitor_type = 'guest' THEN session_id END), 0) AS online_guests,
            MAX(last_seen) AS last_seen_at
        FROM tbl_online_presence
        WHERE last_seen >= ?
    ";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $cutoff);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $row = $res->fetch_assoc() ?: [];
            $stats['online_users'] = (int)($row['online_users'] ?? 0);
            $stats['online_guests'] = (int)($row['online_guests'] ?? 0);
            $stats['online_total'] = $stats['online_users'] + $stats['online_guests'];
            $stats['last_seen_at'] = $row['last_seen_at'] ?? null;
            $res->free();
        }
        $stmt->close();
    }

    return $stats;
}

// The legacy SQL dump does not include the rubber collection table.
function ensure_wang_collection_table(mysqli $db): void {
    $db->query("
        CREATE TABLE IF NOT EXISTS tbl_wangyang (
            wang_id INT(11) NOT NULL AUTO_INCREMENT,
            wang_date DATE NOT NULL,
            wang_mid INT(11) NOT NULL DEFAULT 0,
            wang_group VARCHAR(255) NOT NULL DEFAULT '',
            wang_number VARCHAR(255) NOT NULL DEFAULT '',
            wang_name VARCHAR(255) NOT NULL DEFAULT '',
            wang_class VARCHAR(255) NOT NULL DEFAULT '',
            wang_sack DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            wang_weight DECIMAL(18,2) NOT NULL DEFAULT 0,
            wang_lan VARCHAR(255) NOT NULL DEFAULT '',
            wang_note TEXT NULL,
            wang_status VARCHAR(50) NOT NULL DEFAULT '',
            wang_saveby VARCHAR(255) NOT NULL DEFAULT '',
            wang_savedate DATETIME NOT NULL,
            PRIMARY KEY (wang_id),
            KEY idx_wang_date (wang_date),
            KEY idx_wang_lan (wang_lan),
            KEY idx_wang_savedate (wang_savedate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

require_once __DIR__ . '/collection_round_functions.php';
require_once __DIR__ . '/weighing_functions.php';

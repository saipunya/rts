<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

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

function member_portal_log_path(): string {
    return __DIR__ . '/storage/member_portal_log.json';
}

function ensure_member_portal_log_storage(): void {
    $dir = dirname(member_portal_log_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

function read_member_portal_log_entries(): array {
    $path = member_portal_log_path();
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    // Allow either a flat array of entries or a wrapped document with "entries"
    if (isset($decoded['entries']) && is_array($decoded['entries'])) {
        return $decoded['entries'];
    }

    return array_values(array_filter($decoded, 'is_array'));
}

function write_member_portal_log_entries(array $entries): bool {
    ensure_member_portal_log_storage();
    $path = member_portal_log_path();

    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return false;
    }

    $ok = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        $payload = json_encode([
            'updated_at' => date('c'),
            'entries' => array_values($entries),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($payload !== false) {
            $ok = fwrite($fp, $payload) !== false;
        }
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $ok;
}

function append_member_portal_log_entry(array $entry): void {
    ensure_member_portal_log_storage();
    $path = member_portal_log_path();

    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return;
    }

    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $existing = [];
        if (trim((string)$raw) !== '') {
            $decoded = json_decode((string)$raw, true);
            if (is_array($decoded)) {
                if (isset($decoded['entries']) && is_array($decoded['entries'])) {
                    $existing = $decoded['entries'];
                } else {
                    $existing = array_values(array_filter($decoded, 'is_array'));
                }
            }
        }

        $existing[] = $entry;
        ftruncate($fp, 0);
        rewind($fp);
        $payload = json_encode([
            'updated_at' => date('c'),
            'entries' => array_values($existing),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($payload !== false) {
            fwrite($fp, $payload);
        }
        fflush($fp);
        flock($fp, LOCK_UN);
    }

    fclose($fp);
}

function clear_member_portal_log_entries(): bool {
    return write_member_portal_log_entries([]);
}

function sync_member_portal_logs_from_db_if_needed(mysqli $db): void {
    $path = member_portal_log_path();
    if (is_file($path)) {
        return;
    }

    ensure_member_portal_usage_table($db);
    $entries = [];
    $sql = "SELECT
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
            ORDER BY l.log_id ASC";

    try {
        $res = $db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $entries[] = [
                    'mem_id' => (int)($row['mem_id'] ?? 0),
                    'mem_number' => (string)($row['mem_number'] ?? ''),
                    'mem_fullname' => (string)($row['mem_fullname'] ?? ''),
                    'mem_group' => (string)($row['mem_group'] ?? ''),
                    'mem_class' => (string)($row['mem_class'] ?? ''),
                    'action_type' => (string)($row['action_type'] ?? 'login'),
                    'ip_address' => (string)($row['ip_address'] ?? ''),
                    'user_agent' => (string)($row['user_agent'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }
            $res->free();
        }
    } catch (Throwable $e) {
        return;
    }

    if ($entries) {
        write_member_portal_log_entries($entries);
    }
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

    append_member_portal_log_entry([
        'mem_id' => $memId,
        'mem_number' => $memNumber,
        'mem_fullname' => $memFullname,
        'mem_group' => substr((string)($member['mem_group'] ?? ''), 0, 50),
        'mem_class' => substr((string)($member['mem_class'] ?? ''), 0, 20),
        'action_type' => $actionType,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function fetch_member_portal_usage_stats(mysqli $db): array {
    $stats = [
        'total_logins' => 0,
        'unique_members' => 0,
        'today_logins' => 0,
        'latest_login_at' => null,
        'latest_member_name' => null,
    ];

    $pathExists = is_file(member_portal_log_path());
    if (!$pathExists) {
        sync_member_portal_logs_from_db_if_needed($db);
    }

    $entries = read_member_portal_log_entries();
    $loginEntries = array_values(array_filter($entries, static function ($entry): bool {
        return is_array($entry) && (string)($entry['action_type'] ?? 'login') === 'login';
    }));

    if (empty($loginEntries)) {
        return $stats;
    }

    $stats['total_logins'] = count($loginEntries);
    $stats['unique_members'] = count(array_unique(array_map(static function ($entry) {
        return (int)($entry['mem_id'] ?? 0);
    }, $loginEntries)));

    $today = date('Y-m-d');
    foreach ($loginEntries as $entry) {
        $createdAt = (string)($entry['created_at'] ?? '');
        if ($createdAt !== '' && strpos($createdAt, $today) === 0) {
            $stats['today_logins']++;
        }
    }

    usort($loginEntries, static function ($a, $b): int {
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });
    $latest = $loginEntries[0] ?? null;
    if ($latest) {
        $stats['latest_login_at'] = $latest['created_at'] ?? null;
        $stats['latest_member_name'] = $latest['mem_fullname'] ?? null;
    }

    return $stats;
}

function fetch_member_portal_recent_log_entries(int $limit = 10): array {
    $pathExists = is_file(member_portal_log_path());
    if (!$pathExists) {
        sync_member_portal_logs_from_db_if_needed(db());
    }

    $entries = read_member_portal_log_entries();
    $loginEntries = array_values(array_filter($entries, static function ($entry): bool {
        return is_array($entry) && (string)($entry['action_type'] ?? 'login') === 'login';
    }));

    usort($loginEntries, static function ($a, $b): int {
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });

    return array_slice($loginEntries, 0, max(1, $limit));
}

function fetch_member_portal_period_stats(): array {
    $pathExists = is_file(member_portal_log_path());
    if (!$pathExists) {
        sync_member_portal_logs_from_db_if_needed(db());
    }

    $entries = read_member_portal_log_entries();
    $loginEntries = array_values(array_filter($entries, static function ($entry): bool {
        return is_array($entry) && (string)($entry['action_type'] ?? 'login') === 'login';
    }));

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

    foreach ($loginEntries as $entry) {
        $createdAt = (string)($entry['created_at'] ?? '');
        if ($createdAt === '') {
            continue;
        }
        $entryDate = substr($createdAt, 0, 10);
        if ($entryDate === $today) {
            $stats['today']++;
        }
        if (substr($createdAt, 0, 7) === $month) {
            $stats['month']++;
        }
        if (substr($createdAt, 0, 4) === $year) {
            $stats['year']++;
        }
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

?>

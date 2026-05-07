<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

function cli_arg_exists(array $argv, string $name): bool {
    foreach ($argv as $arg) {
        if ($arg === $name) {
            return true;
        }
    }
    return false;
}

function cli_arg_value(array $argv, string $name, ?string $default = null): ?string {
    $prefix = $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function read_json_entries_from_file(string $path): array {
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

    if (isset($decoded['entries']) && is_array($decoded['entries'])) {
        return $decoded['entries'];
    }

    return array_values(array_filter($decoded, static fn($row): bool => is_array($row)));
}

function normalize_entry(array $entry): ?array {
    $actionType = strtolower(trim((string)($entry['action_type'] ?? 'login')));
    if ($actionType === '') {
        $actionType = 'login';
    }

    $createdAt = trim((string)($entry['created_at'] ?? ''));
    if ($createdAt === '') {
        $createdAt = date('Y-m-d H:i:s');
    }

    $normalized = [
        'mem_id' => (int)($entry['mem_id'] ?? 0),
        'mem_number' => substr(trim((string)($entry['mem_number'] ?? '')), 0, 50),
        'mem_fullname' => substr(trim((string)($entry['mem_fullname'] ?? '')), 0, 255),
        'action_type' => substr($actionType, 0, 20),
        'ip_address' => substr(trim((string)($entry['ip_address'] ?? '')), 0, 45),
        'user_agent' => substr(trim((string)($entry['user_agent'] ?? '')), 0, 255),
        'created_at' => $createdAt,
    ];

    if ($normalized['mem_id'] <= 0) {
        return null;
    }

    if ($normalized['mem_number'] === '' || $normalized['mem_fullname'] === '') {
        return null;
    }

    return $normalized;
}

function entry_exists(mysqli $db, array $entry): bool {
    $sql = "
        SELECT 1
        FROM tbl_member_portal_log
        WHERE mem_id = ?
          AND mem_number = ?
          AND mem_fullname = ?
          AND action_type = ?
          AND created_at = ?
          AND COALESCE(ip_address, '') = ?
          AND COALESCE(user_agent, '') = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $memId = (int)$entry['mem_id'];
    $memNumber = (string)$entry['mem_number'];
    $memFullname = (string)$entry['mem_fullname'];
    $actionType = (string)$entry['action_type'];
    $createdAt = (string)$entry['created_at'];
    $ipAddress = (string)$entry['ip_address'];
    $userAgent = (string)$entry['user_agent'];

    $stmt->bind_param('issssss', $memId, $memNumber, $memFullname, $actionType, $createdAt, $ipAddress, $userAgent);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $exists;
}

function insert_entry(mysqli $db, array $entry): bool {
    $sql = "
        INSERT INTO tbl_member_portal_log
            (mem_id, mem_number, mem_fullname, action_type, ip_address, user_agent, created_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $memId = (int)$entry['mem_id'];
    $memNumber = (string)$entry['mem_number'];
    $memFullname = (string)$entry['mem_fullname'];
    $actionType = (string)$entry['action_type'];
    $ipAddress = (string)$entry['ip_address'];
    $userAgent = (string)$entry['user_agent'];
    $createdAt = (string)$entry['created_at'];

    $stmt->bind_param('issssss', $memId, $memNumber, $memFullname, $actionType, $ipAddress, $userAgent, $createdAt);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

$argv = $_SERVER['argv'] ?? [];
$source = cli_arg_value($argv, '--source', __DIR__ . '/../storage/member_portal_log.json');
$dryRun = cli_arg_exists($argv, '--dry-run');
$truncate = cli_arg_exists($argv, '--truncate');

$db = db();
ensure_member_portal_usage_table($db);

$entries = read_json_entries_from_file($source);
$normalized = [];
foreach ($entries as $entry) {
    $item = normalize_entry($entry);
    if ($item !== null) {
        $normalized[] = $item;
    }
}

if (PHP_SAPI === 'cli') {
    fwrite(STDOUT, "Source: {$source}\n");
    fwrite(STDOUT, 'Found entries: ' . count($entries) . "\n");
    fwrite(STDOUT, 'Valid entries: ' . count($normalized) . "\n");
}

if ($dryRun) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, "Dry run enabled. No database changes were made.\n");
    }
    exit(0);
}

if ($truncate) {
    if (!$db->query('TRUNCATE TABLE tbl_member_portal_log')) {
        fwrite(STDERR, "Failed to truncate tbl_member_portal_log: " . $db->error . "\n");
        exit(1);
    }
}

$inserted = 0;
$skipped = 0;
foreach ($normalized as $entry) {
    if (entry_exists($db, $entry)) {
        $skipped++;
        continue;
    }
    if (insert_entry($db, $entry)) {
        $inserted++;
    } else {
        fwrite(STDERR, "Failed to insert log entry for mem_id={$entry['mem_id']}\n");
    }
}

if (PHP_SAPI === 'cli') {
    fwrite(STDOUT, "Inserted: {$inserted}\n");
    fwrite(STDOUT, "Skipped existing: {$skipped}\n");
}

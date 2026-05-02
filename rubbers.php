<?php
ob_start();
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$db = db();
$errors = [];
$msg = $_GET['msg'] ?? '';
$csrf = csrf_token();
$today = date('Y-m-d');
$hasDompdf = file_exists(__DIR__ . '/vendor/autoload.php');

$lanParam = $_GET['lan'] ?? ($_POST['lan'] ?? '1');
if ($lanParam === 'all') {
    $currentLan = 'all';
} else {
    $currentLan = (int) $lanParam;
    if (!in_array($currentLan, [1, 2, 3, 4], true)) {
        $currentLan = 1;
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$date_from = trim((string) ($_GET['date_from'] ?? ''));
$date_to = trim((string) ($_GET['date_to'] ?? ''));

$latest_round_date = $today;
if ($res = $db->query("SELECT pr_date FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1")) {
    if ($r = $res->fetch_assoc()) {
        $d = (string) ($r['pr_date'] ?? '');
        $dt = $d !== '' ? DateTime::createFromFormat('Y-m-d', $d) : null;
        if ($dt && $dt->format('Y-m-d') === $d) {
            $latest_round_date = $d;
        }
    }
    $res->free();
}
$date_from = $latest_round_date;
$date_to = $latest_round_date;

$latestPrice = 0.0;
if ($res = $db->query("SELECT pr_price FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1")) {
    if ($pr = $res->fetch_assoc()) {
        $latestPrice = (float) $pr['pr_price'];
    }
    $res->free();
}

$member_id = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;
$memberSelectedRow = null;

$db->query("CREATE TABLE IF NOT EXISTS tbl_rubber (
    ru_id INT(11) NOT NULL AUTO_INCREMENT,
    ru_date DATE NOT NULL,
    ru_lan VARCHAR(255) NOT NULL,
    ru_group VARCHAR(255) NOT NULL,
    ru_number VARCHAR(255) NOT NULL,
    ru_fullname VARCHAR(255) NOT NULL,
    ru_class VARCHAR(255) NOT NULL,
    ru_quantity DECIMAL(18,2) NOT NULL,
    ru_hoon DECIMAL(18,2) NOT NULL,
    ru_loan DECIMAL(18,2) NOT NULL,
    ru_shortdebt DECIMAL(18,2) NOT NULL,
    ru_deposit DECIMAL(18,2) NOT NULL,
    ru_tradeloan DECIMAL(18,2) NOT NULL,
    ru_insurance DECIMAL(18,2) NOT NULL,
    ru_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    ru_expend DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    ru_netvalue DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    ru_saveby VARCHAR(255) NOT NULL,
    ru_savedate DATE NOT NULL,
    PRIMARY KEY (ru_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;");

$ensureCols = [
    'ru_value' => "ALTER TABLE tbl_rubber ADD COLUMN ru_value DECIMAL(18,2) NOT NULL DEFAULT 0.00",
    'ru_expend' => "ALTER TABLE tbl_rubber ADD COLUMN ru_expend DECIMAL(18,2) NOT NULL DEFAULT 0.00",
    'ru_netvalue' => "ALTER TABLE tbl_rubber ADD COLUMN ru_netvalue DECIMAL(18,2) NOT NULL DEFAULT 0.00",
];
$dbNameRes = $db->query('SELECT DATABASE() AS dbname');
$dbNameRow = $dbNameRes ? $dbNameRes->fetch_assoc() : null;
$dbName = $dbNameRow['dbname'] ?? '';
if ($dbNameRes) {
    $dbNameRes->free();
}
if ($dbName !== '') {
    $colStmt = $db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    if ($colStmt) {
        $tableName = 'tbl_rubber';
        foreach ($ensureCols as $colName => $alterSql) {
            $colStmt->bind_param('sss', $dbName, $tableName, $colName);
            $colStmt->execute();
            $cntRow = $colStmt->get_result()->fetch_assoc();
            if (!$cntRow || (int) ($cntRow['cnt'] ?? 0) === 0) {
                $db->query($alterSql);
            }
        }
        $colStmt->close();
    }
}

$form = [
    'ru_id' => null,
    'ru_date' => $today,
    'ru_lan' => (string) ($currentLan === 'all' ? 1 : $currentLan),
    'ru_group' => '',
    'ru_number' => '',
    'ru_fullname' => '',
    'ru_class' => '',
    'ru_quantity' => '0.00',
    'ru_hoon' => '0.00',
    'ru_loan' => '0.00',
    'ru_shortdebt' => '0.00',
    'ru_deposit' => '0.00',
    'ru_tradeloan' => '0.00',
    'ru_insurance' => '0.00',
    'ru_savedate' => $today,
];

if ($member_id > 0 && (($_GET['action'] ?? '') !== 'edit')) {
    $stm = $db->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_class FROM tbl_member WHERE mem_id=?");
    $stm->bind_param('i', $member_id);
    $stm->execute();
    $rs = $stm->get_result();
    if ($memberSelectedRow = $rs->fetch_assoc()) {
        $form['ru_group'] = $memberSelectedRow['mem_group'];
        $form['ru_number'] = $memberSelectedRow['mem_number'];
        $form['ru_fullname'] = $memberSelectedRow['mem_fullname'];
        $form['ru_class'] = $memberSelectedRow['mem_class'];
    }
    $stm->close();
}

if (($_GET['action'] ?? '') === 'edit') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $st = $db->prepare("SELECT * FROM tbl_rubber WHERE ru_id=?");
        $st->bind_param('i', $id);
        $st->execute();
        $res = $st->get_result();
        if ($row = $res->fetch_assoc()) {
            $form = $row;
            if (isset($row['ru_lan']) && in_array((int) $row['ru_lan'], [1, 2, 3, 4], true)) {
                $currentLan = (int) $row['ru_lan'];
            }
        } else {
            $errors[] = 'ไม่พบรายการสำหรับแก้ไข';
        }
        $st->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'โทเค็นไม่ถูกต้อง';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = (int) ($_POST['ru_id'] ?? 0);
            if ($id > 0) {
                $st = $db->prepare("DELETE FROM tbl_rubber WHERE ru_id=?");
                $st->bind_param('i', $id);
                $st->execute();
                $st->close();
                header('Location: rubbers.php?lan=' . ($currentLan === 'all' ? 'all' : (int) $currentLan) . '&msg=' . urlencode('ลบรายการแล้ว'));
                exit;
            }
            $errors[] = 'ระบุรายการที่จะลบไม่ถูกต้อง';
        } elseif ($action === 'save') {
            $post_member_id = isset($_POST['ru_member_id']) ? (int) $_POST['ru_member_id'] : 0;
            $isNew = empty($_POST['ru_id']);
            if ($isNew && $post_member_id > 0) {
                $stm = $db->prepare("SELECT mem_group, mem_number, mem_fullname, mem_class FROM tbl_member WHERE mem_id=?");
                $stm->bind_param('i', $post_member_id);
                $stm->execute();
                $mr = $stm->get_result()->fetch_assoc();
                $stm->close();
                if ($mr) {
                    $_POST['ru_group'] = $mr['mem_group'];
                    $_POST['ru_number'] = $mr['mem_number'];
                    $_POST['ru_fullname'] = $mr['mem_fullname'];
                    $_POST['ru_class'] = $mr['mem_class'];
                } else {
                    $errors[] = 'ไม่พบสมาชิกที่เลือก';
                }
            }

            $data = [];
            foreach (['ru_lan', 'ru_group', 'ru_number', 'ru_fullname', 'ru_class'] as $f) {
                $data[$f] = trim((string) ($_POST[$f] ?? ''));
                if ($data[$f] === '') {
                    $errors[] = "กรุณากรอก {$f}";
                }
            }
            foreach (['ru_date', 'ru_savedate'] as $f) {
                $data[$f] = trim((string) ($_POST[$f] ?? ''));
                $dt = DateTime::createFromFormat('Y-m-d', $data[$f]);
                if (!$dt || $dt->format('Y-m-d') !== $data[$f]) {
                    $errors[] = "รูปแบบวันที่ไม่ถูกต้อง: {$f}";
                }
            }
            foreach (['ru_quantity', 'ru_hoon', 'ru_loan', 'ru_shortdebt', 'ru_deposit', 'ru_tradeloan', 'ru_insurance'] as $f) {
                $flt = filter_var($_POST[$f] ?? '', FILTER_VALIDATE_FLOAT);
                if ($flt === false) {
                    $errors[] = "ต้องเป็นตัวเลข: {$f}";
                    $data[$f] = '0.00';
                } else {
                    $data[$f] = number_format((float) $flt, 2, '.', '');
                }
            }

            $lanVal = isset($_POST['ru_lan']) ? (int) $_POST['ru_lan'] : ($currentLan === 'all' ? 1 : $currentLan);
            if (!in_array($lanVal, [1, 2, 3, 4], true)) {
                $errors[] = 'ลานไม่ถูกต้อง';
            }
            $data['ru_lan'] = (string) $lanVal;

            $qty = (float) $data['ru_quantity'];
            $value = $qty * $latestPrice;
            $expend = (float) $data['ru_hoon'] + (float) $data['ru_loan'] + (float) $data['ru_shortdebt'] + (float) $data['ru_deposit'] + (float) $data['ru_tradeloan'] + (float) $data['ru_insurance'];
            $net = $value - $expend;

            $data['ru_value'] = number_format($value, 2, '.', '');
            $data['ru_expend'] = number_format($expend, 2, '.', '');
            $data['ru_netvalue'] = number_format($net, 2, '.', '');

            $cuTmp = current_user();
            $savebyFull = $cuTmp['user_fullname'] ?? ($_SESSION['user_fullname'] ?? '');
            $savebyUser = $cuTmp['user_name'] ?? ($_SESSION['user_name'] ?? '');
            $data['ru_saveby'] = $savebyFull !== '' ? $savebyFull : ($savebyUser !== '' ? $savebyUser : 'เจ้าหน้าที่');

            if (!$errors) {
                $id = isset($_POST['ru_id']) && $_POST['ru_id'] !== '' ? (int) $_POST['ru_id'] : 0;
                if ($id > 0) {
                    $st = $db->prepare("UPDATE tbl_rubber SET
                        ru_date=?, ru_lan=?, ru_group=?, ru_number=?, ru_fullname=?, ru_class=?, ru_quantity=?,
                        ru_hoon=?, ru_loan=?, ru_shortdebt=?, ru_deposit=?, ru_tradeloan=?, ru_insurance=?,
                        ru_value=?, ru_expend=?, ru_netvalue=?, ru_saveby=?, ru_savedate=? WHERE ru_id=?");
                    $st->bind_param(
                        str_repeat('s', 18) . 'i',
                        $data['ru_date'], $data['ru_lan'], $data['ru_group'], $data['ru_number'], $data['ru_fullname'], $data['ru_class'],
                        $data['ru_quantity'], $data['ru_hoon'], $data['ru_loan'], $data['ru_shortdebt'], $data['ru_deposit'], $data['ru_tradeloan'],
                        $data['ru_insurance'], $data['ru_value'], $data['ru_expend'], $data['ru_netvalue'], $data['ru_saveby'], $data['ru_savedate'], $id
                    );
                    $st->execute();
                    $st->close();
                    $lanRedirect = ($lanParam === 'all') ? 'all' : (int) $data['ru_lan'];
                    header('Location: rubbers.php?lan=' . $lanRedirect . '&msg=' . urlencode('บันทึกการแก้ไขแล้ว'));
                    exit;
                }

                $st = $db->prepare("INSERT INTO tbl_rubber
                    (ru_date, ru_lan, ru_group, ru_number, ru_fullname, ru_class, ru_quantity,
                     ru_hoon, ru_loan, ru_shortdebt, ru_deposit, ru_tradeloan, ru_insurance,
                     ru_value, ru_expend, ru_netvalue, ru_saveby, ru_savedate)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $st->bind_param(
                    str_repeat('s', 18),
                    $data['ru_date'], $data['ru_lan'], $data['ru_group'], $data['ru_number'], $data['ru_fullname'], $data['ru_class'],
                    $data['ru_quantity'], $data['ru_hoon'], $data['ru_loan'], $data['ru_shortdebt'], $data['ru_deposit'], $data['ru_tradeloan'],
                    $data['ru_insurance'], $data['ru_value'], $data['ru_expend'], $data['ru_netvalue'], $data['ru_saveby'], $data['ru_savedate']
                );
                $st->execute();
                $st->close();
                $lanRedirect = ($lanParam === 'all') ? 'all' : (int) $data['ru_lan'];
                header('Location: rubbers.php?lan=' . $lanRedirect . '&msg=' . urlencode('บันทึกข้อมูลแล้ว'));
                exit;
            }

            $form = array_merge($form, $_POST);
        }
    }
}

$cu = current_user();
$isAdmin = isset($cu['user_level']) && $cu['user_level'] === 'admin';
$rows = [];

if ($currentLan === 'all') {
    $conds = [];
    $binds = [];
    $types = '';

    if (!$isAdmin) {
        $svFull = $cu['user_fullname'] ?? ($_SESSION['user_fullname'] ?? '');
        $svUser = $cu['user_name'] ?? ($_SESSION['user_name'] ?? $svFull);
        $conds[] = '(ru_saveby = ? OR ru_saveby = ?)';
        $types .= 'ss';
        array_push($binds, $svFull, $svUser);
    }
    if ($search !== '') {
        $like = '%' . $search . '%';
        $conds[] = '(ru_group LIKE ? OR ru_number LIKE ? OR ru_fullname LIKE ? OR ru_class LIKE ?)';
        $types .= 'ssss';
        array_push($binds, $like, $like, $like, $like);
    }
    if ($date_from !== '') {
        $conds[] = 'ru_date >= ?';
        $types .= 's';
        $binds[] = $date_from;
    }
    if ($date_to !== '') {
        $conds[] = 'ru_date <= ?';
        $types .= 's';
        $binds[] = $date_to;
    }

    $sql = "SELECT * FROM tbl_rubber";
    if ($conds) {
        $sql .= ' WHERE ' . implode(' AND ', $conds);
    }
    $sql .= ' ORDER BY ru_date DESC, ru_id DESC';

    if ($conds) {
        $st = $db->prepare($sql);
        if ($types !== '') {
            $st->bind_param($types, ...$binds);
        }
        $st->execute();
        $res = $st->get_result();
    } else {
        $res = $db->query($sql);
    }
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    if (!empty($st)) {
        $st->close();
    }
} else {
    if (!$isAdmin) {
        $stl = $db->prepare("SELECT * FROM tbl_rubber WHERE ru_lan = ? AND ru_date = ? AND (ru_saveby = ? OR ru_saveby = ?) ORDER BY ru_date DESC, ru_id DESC");
        $lanStr = (string) $currentLan;
        $svFull = $cu['user_fullname'] ?? ($_SESSION['user_fullname'] ?? '');
        $svUser = $cu['user_name'] ?? ($_SESSION['user_name'] ?? $svFull);
        $stl->bind_param('ssss', $lanStr, $latest_round_date, $svFull, $svUser);
    } else {
        $stl = $db->prepare("SELECT * FROM tbl_rubber WHERE ru_lan = ? AND ru_date = ? ORDER BY ru_date DESC, ru_id DESC");
        $lanStr = (string) $currentLan;
        $stl->bind_param('ss', $lanStr, $latest_round_date);
    }
    $stl->execute();
    $res = $stl->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    $stl->close();
}

$sumQty = 0.0;
$sumValue = 0.0;
$sumExpend = 0.0;
$sumNet = 0.0;
foreach ($rows as $ag) {
    $sumQty += (float) $ag['ru_quantity'];
    $sumValue += (float) $ag['ru_value'];
    $sumExpend += (float) $ag['ru_expend'];
    $sumNet += (float) $ag['ru_netvalue'];
}

$exportBaseParams = [
    'lan' => ($currentLan === 'all' ? 'all' : $currentLan),
    'search' => $search,
    'date_from' => $date_from,
    'date_to' => $date_to,
];
$exportQuery = http_build_query(array_filter($exportBaseParams, fn($v) => $v !== ''));

$initialRuValue = ((float) $form['ru_quantity']) * $latestPrice;
$initialExpend = (float) $form['ru_hoon'] + (float) $form['ru_loan'] + (float) $form['ru_shortdebt'] + (float) $form['ru_deposit'] + (float) $form['ru_tradeloan'] + (float) $form['ru_insurance'];
$initialNetValue = $initialRuValue - $initialExpend;
if (isset($form['ru_value'])) {
    $initialRuValue = (float) $form['ru_value'];
}
if (isset($form['ru_expend'])) {
    $initialExpend = (float) $form['ru_expend'];
}
if (isset($form['ru_netvalue'])) {
    $initialNetValue = (float) $form['ru_netvalue'];
}
?>
<!doctype html>
<html lang="th" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการข้อมูลยางพารา</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <style>
        html, body {
            font-family: 'Sarabun', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-800">
    <?php
    $siteNavOuterClass = 'sticky top-0 z-50 border-b border-emerald-200 bg-emerald-100/95 text-emerald-950 shadow-sm backdrop-blur';
    $siteNavInnerClass = 'mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8';
    $siteNavBrandBadge = 'ระบบการรวบรวมยาง';
    $siteNavBrandTitle = 'สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด';
    $siteNavBrandIcon = 'banknotes';
    $siteNavNavId = 'rubberNav';
    include __DIR__ . '/partials/site_nav.php';
    ?>

    <main>
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <section class="border-b border-slate-200 pb-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">
                            <?php echo heroicon('archive-box', 'h-4 w-4'); ?>
                            จัดการข้อมูลยางพารา
                        </div>
                        <h1 class="mt-4 text-3xl font-bold leading-tight text-slate-950">บันทึกรับซื้อยาง</h1>
                        <p class="mt-2 text-sm leading-6 text-slate-500">รอบล่าสุด <?php echo e(thai_date_format($latest_round_date)); ?> · <?php echo number_format(count($rows)); ?> รายการ</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold <?php echo $currentLan === 'all' ? 'bg-emerald-600 text-white' : 'border border-slate-300 bg-white text-slate-700'; ?>" href="rubbers.php?lan=all">ทั้งหมด</a>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <a class="inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold <?php echo $currentLan === $i ? 'bg-emerald-600 text-white' : 'border border-slate-300 bg-white text-slate-700'; ?>" href="rubbers.php?lan=<?php echo $i; ?>">ลาน <?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            </section>

            <?php if ($msg): ?>
                <div class="mt-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?php echo e($msg); ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?php echo e(implode(' | ', $errors)); ?></div>
            <?php endif; ?>

            <?php if ($currentLan !== 'all'): ?>
                <form method="post" autocomplete="off" class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" id="rubberForm">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="lan" value="<?php echo (int) $currentLan; ?>">
                    <input type="hidden" id="ru_member_id" name="ru_member_id" value="<?php echo !empty($memberSelectedRow['mem_id']) ? (int) $memberSelectedRow['mem_id'] : ''; ?>">
                    <?php if (!empty($form['ru_id'])): ?>
                        <input type="hidden" name="ru_id" value="<?php echo (int) $form['ru_id']; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="ru_lan" value="<?php echo !empty($form['ru_id']) ? (int) $form['ru_lan'] : (int) $currentLan; ?>">

                    <div class="flex flex-col gap-2 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-950"><?php echo !empty($form['ru_id']) ? 'แก้ไขรายการ #' . (int) $form['ru_id'] : 'เพิ่มรายการใหม่'; ?></h2>
                            <p class="mt-1 text-sm text-slate-500">ลาน <?php echo (int) $currentLan; ?></p>
                        </div>
                        <div class="text-sm font-semibold text-emerald-700">ราคาล่าสุด <?php echo number_format($latestPrice, 2); ?> บาท/กก.</div>
                    </div>

                    <?php if (empty($form['ru_id'])): ?>
                        <section class="mt-5">
                            <label for="memberSearch" class="mb-2 block text-sm font-semibold text-slate-700">เลือกสมาชิก</label>
                            <div class="relative">
                                <input id="memberSearch" type="text" class="min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="ค้นหาชื่อ / เลขที่ / กลุ่ม / ชั้น">
                                <ul id="memberResults" class="absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-lg" hidden></ul>
                            </div>
                            <div id="memberSelected" class="mt-2 rounded-md bg-emerald-50 px-3 py-2 text-sm leading-6 text-emerald-800" <?php if (empty($memberSelectedRow)) echo 'hidden'; ?>>
                                <?php if (!empty($memberSelectedRow)): ?>
                                    ใช้สมาชิก: #<?php echo (int) $memberSelectedRow['mem_id']; ?>
                                    <?php echo e($memberSelectedRow['mem_fullname']); ?> |
                                    กลุ่ม: <?php echo e($memberSelectedRow['mem_group']); ?> |
                                    เลขที่: <?php echo e($memberSelectedRow['mem_number']); ?> |
                                    ชั้น: <?php echo e($memberSelectedRow['mem_class']); ?>
                                    <button type="button" id="clearMember" class="ml-2 rounded-md bg-rose-600 px-2 py-1 text-xs font-semibold text-white">เปลี่ยน</button>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="block text-sm font-semibold text-slate-700">วันที่
                            <input type="date" name="ru_date" required class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" value="<?php echo e($form['ru_date']); ?>">
                        </label>
                        <label class="block text-sm font-semibold text-slate-700">กลุ่ม
                            <input id="ru_group" name="ru_group" required class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_group']); ?>">
                        </label>
                        <label class="block text-sm font-semibold text-slate-700">เลขที่
                            <input id="ru_number" name="ru_number" required class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_number']); ?>">
                        </label>
                        <label class="block text-sm font-semibold text-slate-700">ชั้น
                            <input id="ru_class" name="ru_class" required class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_class']); ?>">
                        </label>
                    </section>

                    <section class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                        <label class="block text-sm font-semibold text-slate-700">ชื่อ-สกุล
                            <input id="ru_fullname" name="ru_fullname" required class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" <?php if (empty($form['ru_id']) && $memberSelectedRow) echo 'readonly'; ?> value="<?php echo e($form['ru_fullname']); ?>">
                        </label>
                        <label class="block text-sm font-semibold text-slate-700">ปริมาณ
                            <input name="ru_quantity" id="ru_quantity" required inputmode="decimal" class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3 text-right text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" value="<?php echo e($form['ru_quantity']); ?>">
                        </label>
                    </section>

                    <section class="mt-5">
                        <h3 class="text-sm font-bold text-slate-800">รายการหัก</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <?php
                            $deductFields = [
                                'ru_hoon' => 'หุ้น',
                                'ru_loan' => 'เงินกู้',
                                'ru_shortdebt' => 'หนี้สั้น',
                                'ru_deposit' => 'เงินฝาก',
                                'ru_tradeloan' => 'กู้ซื้อขาย',
                                'ru_insurance' => 'ประกันภัย',
                            ];
                            ?>
                            <?php foreach ($deductFields as $field => $label): ?>
                                <label class="block text-sm font-semibold text-slate-700"><?php echo e($label); ?>
                                    <input id="<?php echo e($field); ?>" name="<?php echo e($field); ?>" required inputmode="decimal" class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3 text-right text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" value="<?php echo e($form[$field]); ?>">
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="ru_savedate" value="<?php echo e($form['ru_savedate']); ?>">
                    </section>

                    <section class="mt-5 grid gap-3 border-t border-slate-200 pt-5 sm:grid-cols-3">
                        <div class="rounded-md bg-emerald-50 px-4 py-3">
                            <p class="text-xs font-semibold text-emerald-700">มูลค่ายาง</p>
                            <p id="ruValue" class="mt-1 text-xl font-bold text-slate-950"><?php echo number_format($initialRuValue, 2); ?></p>
                        </div>
                        <div class="rounded-md bg-amber-50 px-4 py-3">
                            <p class="text-xs font-semibold text-amber-700">ยอดหักรวม</p>
                            <p id="ruExpend" class="mt-1 text-xl font-bold text-slate-950"><?php echo number_format($initialExpend, 2); ?></p>
                        </div>
                        <div class="rounded-md bg-cyan-50 px-4 py-3">
                            <p class="text-xs font-semibold text-cyan-700">ยอดสุทธิ</p>
                            <p id="ruNetValue" class="mt-1 text-xl font-bold text-slate-950"><?php echo number_format($initialNetValue, 2); ?></p>
                        </div>
                    </section>
                    <span id="latestPrice" data-price="<?php echo $latestPrice; ?>" hidden></span>

                    <div class="mt-5 flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500"><?php echo !empty($form['ru_id']) ? 'แก้ไข #' . (int) $form['ru_id'] : 'สร้างรายการใหม่'; ?></p>
                        <div class="flex gap-2">
                            <button type="button" id="btnSave" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                <?php echo heroicon('plus', 'h-4 w-4'); ?>
                                บันทึก
                            </button>
                            <?php if (!empty($form['ru_id'])): ?>
                                <a href="rubbers.php?lan=<?php echo (int) $currentLan; ?>" class="inline-flex min-h-11 items-center justify-center rounded-md border border-slate-300 bg-white px-5 py-2 text-sm font-semibold text-slate-700 no-underline hover:no-underline">ยกเลิก</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <form method="get" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_auto] lg:items-end">
                        <input type="hidden" name="lan" value="all">
                        <label class="block text-sm font-semibold text-slate-700">คำค้น
                            <input type="text" name="search" class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" value="<?php echo e($search); ?>" placeholder="กลุ่ม / เลขที่ / ชื่อ / ชั้น">
                        </label>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">รอบล่าสุด</p>
                            <div class="mt-2 flex min-h-11 items-center rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600"><?php echo $date_from ? e(thai_date_format($date_from)) : '-'; ?></div>
                        </div>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">ค้นหา</button>
                    </form>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-md bg-slate-50 px-4 py-3"><p class="text-xs text-slate-500">ปริมาณรวม</p><p class="mt-1 text-lg font-bold"><?php echo number_format($sumQty, 2); ?> กก.</p></div>
                        <div class="rounded-md bg-slate-50 px-4 py-3"><p class="text-xs text-slate-500">มูลค่ารวม</p><p class="mt-1 text-lg font-bold"><?php echo number_format($sumValue, 2); ?> ฿</p></div>
                        <div class="rounded-md bg-slate-50 px-4 py-3"><p class="text-xs text-slate-500">ยอดหักรวม</p><p class="mt-1 text-lg font-bold"><?php echo number_format($sumExpend, 2); ?> ฿</p></div>
                        <div class="rounded-md bg-slate-50 px-4 py-3"><p class="text-xs text-slate-500">ยอดสุทธิ</p><p class="mt-1 text-lg font-bold"><?php echo number_format($sumNet, 2); ?> ฿</p></div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="mt-6 border-t border-slate-200 pt-6">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-950">ผลลัพธ์ <?php echo number_format(count($rows)); ?> รายการ</h2>
                        <p class="mt-1 text-sm text-slate-500"><?php echo $currentLan === 'all' ? 'แสดงข้อมูลทุกลาน' : 'แสดงข้อมูลลาน ' . (int) $currentLan; ?></p>
                    </div>
                    <?php if (!empty($rows)): ?>
                        <a href="export_rubbers_excel.php?<?php echo $exportQuery; ?>" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 no-underline hover:bg-emerald-100 hover:no-underline">
                            <?php echo heroicon('document', 'h-4 w-4'); ?>
                            Excel
                        </a>
                    <?php endif; ?>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-[1180px] w-full text-sm">
                            <thead class="bg-slate-100 text-slate-600">
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold">ID</th>
                                    <th class="px-3 py-3 text-left font-semibold">วันที่</th>
                                    <th class="px-3 py-3 text-left font-semibold">ลาน</th>
                                    <th class="px-3 py-3 text-left font-semibold">กลุ่ม</th>
                                    <th class="px-3 py-3 text-left font-semibold">เลขที่</th>
                                    <th class="px-3 py-3 text-left font-semibold">ชื่อ-สกุล</th>
                                    <th class="px-3 py-3 text-right font-semibold">ปริมาณ</th>
                                    <th class="px-3 py-3 text-right font-semibold">หุ้น</th>
                                    <th class="px-3 py-3 text-right font-semibold">เงินกู้</th>
                                    <th class="px-3 py-3 text-right font-semibold">หนี้สั้น</th>
                                    <th class="px-3 py-3 text-right font-semibold">เงินฝาก</th>
                                    <th class="px-3 py-3 text-right font-semibold">ลูกหนี้</th>
                                    <th class="px-3 py-3 text-right font-semibold">ประกัน</th>
                                    <th class="px-3 py-3 text-center font-semibold">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!$rows): ?>
                                    <tr><td colspan="14" class="px-4 py-12 text-center text-slate-500">ยังไม่มีข้อมูล</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $r): ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-3 py-3"><?php echo e($r['ru_id']); ?></td>
                                            <td class="whitespace-nowrap px-3 py-3"><?php echo thai_date_format($r['ru_date']); ?></td>
                                            <td class="px-3 py-3"><?php echo e($r['ru_lan']); ?></td>
                                            <td class="px-3 py-3"><?php echo e($r['ru_group']); ?></td>
                                            <td class="px-3 py-3"><?php echo e($r['ru_number']); ?></td>
                                            <td class="min-w-[220px] px-3 py-3">
                                                <span class="font-semibold text-slate-900"><?php echo e($r['ru_fullname']); ?></span>
                                                <span class="ml-2 rounded-md px-2 py-1 text-xs font-semibold <?php echo $r['ru_class'] === 'member' ? 'bg-emerald-50 text-emerald-700' : ($r['ru_class'] === 'general' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600'); ?>">
                                                    <?php echo $r['ru_class'] === 'member' ? 'สมาชิก' : ($r['ru_class'] === 'general' ? 'เกษตรกร' : 'ไม่ระบุ'); ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-3 text-right"><?php echo number_format((float) $r['ru_quantity'], 2); ?></td>
                                            <td class="px-3 py-3 text-right"><?php echo number_format((float) $r['ru_hoon'], 2); ?></td>
                                            <td class="px-3 py-3 text-right"><?php echo number_format((float) $r['ru_loan'], 2); ?></td>
                                            <td class="px-3 py-3 text-right"><?php echo number_format((float) $r['ru_shortdebt'], 2); ?></td>
                                            <td class="px-3 py-3 text-right"><?php echo number_format((float) $r['ru_deposit'], 2); ?></td>
                                            <td class="px-3 py-3 text-right"><?php echo number_format((float) $r['ru_tradeloan'], 2); ?></td>
                                            <td class="px-3 py-3 text-right"><?php echo number_format((float) $r['ru_insurance'], 2); ?></td>
                                            <td class="px-3 py-3">
                                                <div class="flex justify-center gap-2">
                                                    <a href="rubbers.php?lan=<?php echo $currentLan === 'all' ? 'all' : (int) $currentLan; ?>&action=edit&id=<?php echo (int) $r['ru_id']; ?>" class="inline-flex min-h-9 items-center rounded-md bg-amber-500 px-3 text-xs font-semibold text-white no-underline hover:no-underline">แก้ไข</a>
                                                    <form method="post" onsubmit="return confirm('ลบรายการนี้?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="lan" value="<?php echo $currentLan === 'all' ? 'all' : (int) $currentLan; ?>">
                                                        <input type="hidden" name="ru_id" value="<?php echo (int) $r['ru_id']; ?>">
                                                        <button type="submit" class="inline-flex min-h-9 items-center rounded-md bg-rose-600 px-3 text-xs font-semibold text-white">ลบ</button>
                                                    </form>
                                                    <?php if ($hasDompdf): ?>
                                                        <a href="export_rubber_pdf.php?ru_id=<?php echo (int) $r['ru_id']; ?>" target="_blank" class="inline-flex min-h-9 items-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 no-underline hover:no-underline">PDF</a>
                                                    <?php else: ?>
                                                        <button class="inline-flex min-h-9 items-center rounded-md border border-slate-200 bg-slate-100 px-3 text-xs font-semibold text-slate-400" disabled>PDF</button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm text-slate-500 sm:px-6 lg:px-8">
            <p class="font-semibold text-slate-700">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</p>
            <p>&copy; <?php echo date('Y'); ?> ระบบการซื้อขายยางพารา</p>
        </div>
    </footer>

    <?php if ($currentLan !== 'all'): ?>
        <script>
            (function () {
                const inCreateMode = <?php echo json_encode(empty($form['ru_id'])); ?>;
                if (!inCreateMode) return;

                const q = document.getElementById('memberSearch');
                const list = document.getElementById('memberResults');
                const selectedBox = document.getElementById('memberSelected');
                const clearBtn = document.getElementById('clearMember');
                const hid = document.getElementById('ru_member_id');
                const fGroup = document.getElementById('ru_group');
                const fNumber = document.getElementById('ru_number');
                const fName = document.getElementById('ru_fullname');
                const fClass = document.getElementById('ru_class');
                const fQty = document.getElementById('ru_quantity');
                let t = null;

                function hideList() {
                    list.hidden = true;
                    list.innerHTML = '';
                }

                function lockFields(lock) {
                    [fGroup, fNumber, fName, fClass].forEach((el) => {
                        if (!el) return;
                        if (lock) el.setAttribute('readonly', '');
                        else el.removeAttribute('readonly');
                    });
                }

                function bindClear() {
                    const btn = selectedBox.querySelector('#clearMember');
                    if (!btn) return;
                    btn.addEventListener('click', () => {
                        hid.value = '';
                        lockFields(false);
                        selectedBox.hidden = true;
                    });
                }

                function renderSelected(m) {
                    selectedBox.hidden = false;
                    selectedBox.innerHTML = `ใช้สมาชิก: #${m.mem_id} ${m.mem_fullname} | กลุ่ม: ${m.mem_group} | เลขที่: ${m.mem_number} | ชั้น: ${m.mem_class} <button type="button" id="clearMember" class="ml-2 rounded-md bg-rose-600 px-2 py-1 text-xs font-semibold text-white">เปลี่ยน</button>`;
                    bindClear();
                }

                function pick(m) {
                    hid.value = m.mem_id;
                    fGroup.value = m.mem_group;
                    fNumber.value = m.mem_number;
                    fName.value = m.mem_fullname;
                    fClass.value = m.mem_class;
                    lockFields(true);
                    renderSelected(m);
                    q.value = '';
                    hideList();
                    if (fQty) {
                        fQty.focus();
                        fQty.select();
                    }
                }

                function search(term) {
                    if (!term || term.length < 2) {
                        hideList();
                        return;
                    }
                    fetch('members_search.php?q=' + encodeURIComponent(term))
                        .then((r) => r.ok ? r.json() : [])
                        .then((rows) => {
                            if (!Array.isArray(rows) || rows.length === 0) {
                                hideList();
                                return;
                            }
                            list.innerHTML = rows.map((m) => `
                                <li data-item='${JSON.stringify(m).replace(/'/g, "&apos;")}' class="cursor-pointer px-3 py-3 text-sm hover:bg-emerald-50">
                                    <strong class="block text-slate-900">${m.mem_fullname}</strong>
                                    <span class="text-slate-500">#${m.mem_id} | กลุ่ม: ${m.mem_group} | เลขที่: ${m.mem_number} | ชั้น: ${m.mem_class}</span>
                                </li>`).join('');
                            list.hidden = false;
                        })
                        .catch(() => hideList());
                }

                q && q.addEventListener('input', (e) => {
                    clearTimeout(t);
                    t = setTimeout(() => search(e.target.value.trim()), 250);
                });

                list && list.addEventListener('click', (e) => {
                    const li = e.target.closest('li');
                    if (!li) return;
                    try {
                        pick(JSON.parse(li.getAttribute('data-item').replace(/&apos;/g, "'")));
                    } catch (_) {}
                });

                document.addEventListener('click', (e) => {
                    if (list && !list.contains(e.target) && e.target !== q) hideList();
                });

                if (clearBtn) bindClear();
                if (hid && hid.value && fQty) {
                    setTimeout(() => {
                        fQty.focus();
                        fQty.select();
                    }, 50);
                }
            })();
        </script>
    <?php endif; ?>

    <script>
        (function () {
            const priceEl = document.getElementById('latestPrice');
            const price = parseFloat(priceEl?.dataset.price || '0') || 0;
            const qtyInput = document.getElementById('ru_quantity');
            const fields = ['ru_hoon', 'ru_loan', 'ru_shortdebt', 'ru_deposit', 'ru_tradeloan', 'ru_insurance'];
            const elValue = document.getElementById('ruValue');
            const elExpend = document.getElementById('ruExpend');
            const elNet = document.getElementById('ruNetValue');

            function num(v) { return parseFloat((v || '0').toString().replace(/,/g, '')) || 0; }
            function fmt(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
            function calc() {
                const q = qtyInput ? num(qtyInput.value) : 0;
                const ruValue = q * price;
                let ruExpend = 0;
                fields.forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) ruExpend += num(el.value);
                });
                if (elValue) elValue.textContent = fmt(ruValue);
                if (elExpend) elExpend.textContent = fmt(ruExpend);
                if (elNet) elNet.textContent = fmt(ruValue - ruExpend);
            }

            if (qtyInput) qtyInput.addEventListener('input', calc);
            fields.forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('input', calc);
            });
            calc();
        })();

        (function () {
            const form = document.getElementById('rubberForm');
            const saveBtn = document.getElementById('btnSave');
            if (!form || !saveBtn) return;

            form.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && e.target && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                }
            });
            saveBtn.addEventListener('click', function () {
                if (form.reportValidity && !form.reportValidity()) return;
                if (form.requestSubmit) form.requestSubmit();
                else form.submit();
            });
            form.addEventListener('submit', function (e) {
                if (e.submitter && e.submitter.id !== 'btnSave') {
                    e.preventDefault();
                }
            });
        })();
    </script>
</body>
</html>

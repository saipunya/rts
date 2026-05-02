<?php
require_once 'functions.php';

$db = db();

$stmt = $db->prepare("SELECT pr_price, pr_date FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $latest_price = $row ? (float) ($row['pr_price'] ?? 0) : 0.0;
    $latest_price_date = $row['pr_date'] ?? null;
    if ($res) {
        $res->free();
    }
    $stmt->close();
} else {
    $latest_price = 0.0;
    $latest_price_date = null;
}

$latest_price_date_text = $latest_price_date ? thai_date_format((string) $latest_price_date) : '-';

$price_date_total_quantity = 0.0;
$price_date_total_value = 0.0;
if ($latest_price_date) {
    $stmt = $db->prepare("SELECT SUM(ru_quantity) AS total_qty FROM tbl_rubber WHERE ru_date = ?");
    if ($stmt) {
        $stmt->bind_param('s', $latest_price_date);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $price_date_total_quantity = $row && $row['total_qty'] ? (float) $row['total_qty'] : 0.0;
        if ($res) {
            $res->free();
        }
        $stmt->close();
    }
    $price_date_total_value = $price_date_total_quantity * $latest_price;
}

$sum_date_from = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$sum_date_to = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$sum_lan_param = isset($_GET['lan']) ? trim((string) $_GET['lan']) : 'all';

$sum_lan = 'all';
if ($sum_lan_param !== '' && strtolower($sum_lan_param) !== 'all') {
    $lanInt = (int) $sum_lan_param;
    if (in_array($lanInt, [1, 2, 3, 4], true)) {
        $sum_lan = (string) $lanInt;
    }
}

$dt = $sum_date_from !== '' ? DateTime::createFromFormat('Y-m-d', $sum_date_from) : null;
if ($sum_date_from !== '' && (!$dt || $dt->format('Y-m-d') !== $sum_date_from)) {
    $sum_date_from = '';
}

$dt = $sum_date_to !== '' ? DateTime::createFromFormat('Y-m-d', $sum_date_to) : null;
if ($sum_date_to !== '' && (!$dt || $dt->format('Y-m-d') !== $sum_date_to)) {
    $sum_date_to = '';
}

$total_records = 0;
if ($rs = $db->query("SELECT COUNT(*) AS cnt FROM tbl_rubber")) {
    $row = $rs->fetch_assoc();
    $total_records = $row && $row['cnt'] ? (int) $row['cnt'] : 0;
    $rs->free();
}

$all_total_quantity = 0.0;
$all_total_value = 0.0;
if ($all_stats = $db->query("SELECT SUM(ru_quantity) AS total_qty FROM tbl_rubber")) {
    $row = $all_stats->fetch_assoc();
    $all_total_quantity = $row && $row['total_qty'] ? (float) $row['total_qty'] : 0.0;
    $all_total_value = $all_total_quantity * $latest_price;
    $all_stats->free();
}

$sum_where = [];
$sum_params = [];
$sum_types = '';
if ($sum_date_from !== '') {
    $sum_where[] = 'r.ru_date >= ?';
    $sum_params[] = $sum_date_from;
    $sum_types .= 's';
}
if ($sum_date_to !== '') {
    $sum_where[] = 'r.ru_date <= ?';
    $sum_params[] = $sum_date_to;
    $sum_types .= 's';
}
if ($sum_lan !== 'all') {
    $sum_where[] = 'r.ru_lan = ?';
    $sum_params[] = $sum_lan;
    $sum_types .= 's';
}
$sum_where_sql = $sum_where ? ('WHERE ' . implode(' AND ', $sum_where)) : '';

$summary_rows = [];
$sql = "SELECT r.ru_date, r.ru_lan, MAX(p.pr_price) AS pr_price,
        SUM(r.ru_quantity) AS total_qty,
        SUM(r.ru_value) AS total_value,
        SUM(r.ru_expend) AS total_expend,
        SUM(r.ru_netvalue) AS total_net,
        COUNT(*) AS row_count
    FROM tbl_rubber r
    LEFT JOIN tbl_price p ON p.pr_date = r.ru_date
    $sum_where_sql
    GROUP BY r.ru_date, r.ru_lan
    ORDER BY r.ru_date DESC, CAST(r.ru_lan AS UNSIGNED) ASC
    LIMIT 200";

$stmt = $db->prepare($sql);
if ($stmt) {
    if ($sum_params) {
        $stmt->bind_param($sum_types, ...$sum_params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $summary_rows = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
    $stmt->close();
}

$lan_rows = [];
$grand_total_qty = 0.0;
$grand_total_value = 0.0;
if ($latest_price_date) {
    $lan_sql = "SELECT ru_lan, SUM(ru_quantity) AS total_qty
        FROM tbl_rubber
        WHERE ru_date = ?
        GROUP BY ru_lan
        ORDER BY CAST(ru_lan AS UNSIGNED) ASC";
    $lan_stmt = $db->prepare($lan_sql);
    if ($lan_stmt) {
        $lan_stmt->bind_param('s', $latest_price_date);
        $lan_stmt->execute();
        $lan_res = $lan_stmt->get_result();
        if ($lan_res) {
            while ($lan_row = $lan_res->fetch_assoc()) {
                $qty = (float) ($lan_row['total_qty'] ?? 0);
                $value = $qty * $latest_price;
                $grand_total_qty += $qty;
                $grand_total_value += $value;
                $lan_rows[] = [
                    'lan' => (string) ($lan_row['ru_lan'] ?? '-'),
                    'qty' => $qty,
                    'value' => $value,
                ];
            }
            $lan_res->free();
        }
        $lan_stmt->close();
    }
}

$daily_summary = [];
foreach ($summary_rows as $row) {
    $ruDate = $row['ru_date'] ?? '';
    if ($ruDate === '') {
        continue;
    }

    if (!isset($daily_summary[$ruDate])) {
        $daily_summary[$ruDate] = [
            'ru_date' => $ruDate,
            'total_qty' => 0.0,
            'total_value' => 0.0,
            'total_expend' => 0.0,
            'total_net' => 0.0,
            'row_count' => 0,
            'pr_price' => null,
        ];
    }

    $daily_summary[$ruDate]['total_qty'] += (float) ($row['total_qty'] ?? 0);
    $daily_summary[$ruDate]['total_value'] += (float) ($row['total_value'] ?? 0);
    $daily_summary[$ruDate]['total_expend'] += (float) ($row['total_expend'] ?? 0);
    $daily_summary[$ruDate]['total_net'] += (float) ($row['total_net'] ?? 0);
    $daily_summary[$ruDate]['row_count'] += (int) ($row['row_count'] ?? 0);

    if ($daily_summary[$ruDate]['pr_price'] === null && isset($row['pr_price']) && $row['pr_price'] !== null) {
        $daily_summary[$ruDate]['pr_price'] = (float) $row['pr_price'];
    }
}

$people_count_by_date = [];
$countSql = "SELECT
        r.ru_date,
        COUNT(DISTINCT CASE WHEN LOWER(r.ru_class) = 'member' THEN TRIM(r.ru_number) END) AS member_people,
        COUNT(DISTINCT CASE WHEN LOWER(r.ru_class) = 'general' THEN TRIM(r.ru_fullname) END) AS general_people
    FROM tbl_rubber r
    $sum_where_sql
    GROUP BY r.ru_date";
$countStmt = $db->prepare($countSql);
if ($countStmt) {
    if (!empty($sum_params)) {
        $countStmt->bind_param($sum_types, ...$sum_params);
    }
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    if ($countRes) {
        while ($cr = $countRes->fetch_assoc()) {
            $d = (string) ($cr['ru_date'] ?? '');
            if ($d === '') {
                continue;
            }
            $people_count_by_date[$d] = [
                'member' => (int) ($cr['member_people'] ?? 0),
                'general' => (int) ($cr['general_people'] ?? 0),
            ];
        }
        $countRes->free();
    }
    $countStmt->close();
}

$logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['member_id']);
$target = $logged_in ? 'rubbers.php?lan=all' : 'login.php?redirect=' . urlencode('rubbers.php?lan=all');
?>
<!doctype html>
<html lang="th" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ระบบจัดการยางพารา</title>
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
    $siteNavNavId = 'indexNav';
    include __DIR__ . '/partials/site_nav.php';
    ?>

    <main class="bg-slate-50">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <section class="border-b border-slate-200 pb-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="inline-flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">
                            <?php echo heroicon('chart-bar', 'h-4 w-4'); ?>
                            ภาพรวมระบบ
                        </div>
                        <h1 class="mt-4 text-3xl font-bold leading-tight text-slate-950 sm:text-4xl">
                            ระบบจัดการยางพารา
                        </h1>
                        <p class="mt-3 max-w-2xl text-base leading-8 text-slate-600">
                            ติดตามราคายางล่าสุด ปริมาณรับซื้อ ยอดเงินรวม และสรุปรายวันในหน้าเดียวที่ออกแบบให้อ่านง่ายและทำงานซ้ำได้เร็ว
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="allmember.php" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700">
                            <?php echo heroicon('users', 'h-5 w-5'); ?>
                            สำหรับสมาชิก
                        </a>
                        <a href="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                            <?php echo heroicon('archive-box', 'h-5 w-5'); ?>
                            ดูรายการรับซื้อ
                        </a>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 py-6 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-emerald-700">ราคาล่าสุด</p>
                            <p class="mt-3 text-3xl font-bold leading-none text-slate-950"><?php echo number_format($latest_price, 2); ?> ฿</p>
                            <p class="mt-3 text-sm leading-6 text-slate-500">อัปเดตวันที่ <?php echo htmlspecialchars($latest_price_date_text, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                            <?php echo heroicon('banknotes', 'h-5 w-5'); ?>
                        </span>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-slate-500">ปริมาณรวมทั้งหมด</p>
                    <p class="mt-3 text-2xl font-bold leading-tight text-slate-950"><?php echo number_format($all_total_quantity, 2); ?></p>
                    <p class="mt-2 text-sm text-slate-500">กิโลกรัม</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-slate-500">ยอดเงินรวมทั้งหมด</p>
                    <p class="mt-3 text-2xl font-bold leading-tight text-slate-950"><?php echo number_format($all_total_value, 2); ?></p>
                    <p class="mt-2 text-sm text-slate-500">บาท</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-slate-500">รายการทั้งหมด</p>
                    <p class="mt-3 text-2xl font-bold leading-tight text-slate-950"><?php echo number_format($total_records); ?></p>
                    <p class="mt-2 text-sm text-slate-500">รายการ</p>
                </div>
            </section>

            <section class="grid gap-4 pb-6 lg:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan-50 text-cyan-700">
                            <?php echo heroicon('calendar-days', 'h-5 w-5'); ?>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-500">ปริมาณวันล่าสุด</p>
                            <p class="mt-2 text-2xl font-bold text-slate-950"><?php echo number_format($price_date_total_quantity, 2); ?> kg</p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">อ้างอิงวันที่ <?php echo htmlspecialchars($latest_price_date_text, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-amber-50 text-amber-700">
                            <?php echo heroicon('chart-bar', 'h-5 w-5'); ?>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-500">มูลค่าวันล่าสุด</p>
                            <p class="mt-2 text-2xl font-bold text-slate-950"><?php echo number_format($price_date_total_value, 2); ?> ฿</p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">ปริมาณของวันที่ราคาล่าสุด x ราคาล่าสุด</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-t border-slate-200 py-6">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-950">ปริมาณรวบรวมแต่ละลาน</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">วันที่อ้างอิง <?php echo htmlspecialchars($latest_price_date_text, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-[640px] w-full text-sm">
                            <thead class="bg-slate-100 text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">ลาน</th>
                                    <th class="px-4 py-3 text-right font-semibold">ปริมาณรวม (kg)</th>
                                    <th class="px-4 py-3 text-right font-semibold">ยอดเงินรวม (฿)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($lan_rows as $row): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-4 py-4 font-semibold text-slate-900">ลาน <?php echo htmlspecialchars($row['lan'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-slate-700"><?php echo number_format($row['qty'], 2); ?></td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right font-semibold text-slate-900"><?php echo number_format($row['value'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="bg-emerald-50 font-bold text-emerald-900">
                                    <td class="px-4 py-4">รวมทั้งหมด</td>
                                    <td class="px-4 py-4 text-right"><?php echo number_format($grand_total_qty, 2); ?></td>
                                    <td class="px-4 py-4 text-right"><?php echo number_format($grand_total_value, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="border-t border-slate-200 py-6">
                <div class="mb-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <div>
                        <h2 class="text-xl font-bold text-slate-950">สรุปรับซื้อรายวัน</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">รวมทุกลาน พร้อมจำนวนรายการและจำนวนสมาชิก/เกษตรกร</p>
                    </div>

                    <form method="get" class="grid gap-3 sm:grid-cols-2 lg:min-w-[34rem] lg:grid-cols-[1fr_1fr_1fr_auto]">
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($sum_date_from, ENT_QUOTES, 'UTF-8'); ?>" class="min-h-11 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($sum_date_to, ENT_QUOTES, 'UTF-8'); ?>" class="min-h-11 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                        <select name="lan" class="min-h-11 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" <?php echo $sum_lan === 'all' ? 'selected' : ''; ?>>ทุกลาน</option>
                            <option value="1" <?php echo $sum_lan === '1' ? 'selected' : ''; ?>>ลาน 1</option>
                            <option value="2" <?php echo $sum_lan === '2' ? 'selected' : ''; ?>>ลาน 2</option>
                            <option value="3" <?php echo $sum_lan === '3' ? 'selected' : ''; ?>>ลาน 3</option>
                            <option value="4" <?php echo $sum_lan === '4' ? 'selected' : ''; ?>>ลาน 4</option>
                        </select>
                        <button type="submit" class="min-h-11 rounded-md bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700">
                            กรอง
                        </button>
                    </form>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-[980px] w-full text-sm">
                            <thead class="bg-slate-100 text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">วันที่</th>
                                    <th class="px-4 py-3 text-right font-semibold">ราคา</th>
                                    <th class="px-4 py-3 text-right font-semibold">ปริมาณ</th>
                                    <th class="px-4 py-3 text-right font-semibold">เงินรวม</th>
                                    <th class="px-4 py-3 text-right font-semibold">ยอดหัก</th>
                                    <th class="px-4 py-3 text-right font-semibold">สุทธิ</th>
                                    <th class="px-4 py-3 text-right font-semibold">รายการ</th>
                                    <th class="px-4 py-3 text-right font-semibold">สมาชิก/ทั่วไป</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                $sum_total_qty = 0.0;
                                $sum_total_value = 0.0;
                                $sum_total_expend = 0.0;
                                $sum_total_net = 0.0;
                                $sum_total_rows = 0;
                                ?>
                                <?php if (!empty($daily_summary)): ?>
                                    <?php foreach ($daily_summary as $ruDate => $row): ?>
                                        <?php
                                        $qty = (float) $row['total_qty'];
                                        $value = (float) $row['total_value'];
                                        $expend = (float) $row['total_expend'];
                                        $net = (float) $row['total_net'];
                                        $count = (int) $row['row_count'];

                                        $sum_total_qty += $qty;
                                        $sum_total_value += $value;
                                        $sum_total_expend += $expend;
                                        $sum_total_net += $net;
                                        $sum_total_rows += $count;

                                        $price = $row['pr_price'] !== null ? (float) $row['pr_price'] : null;
                                        if ($price === null && $qty > 0) {
                                            $price = $value / $qty;
                                        }

                                        $member_count = $people_count_by_date[$ruDate]['member'] ?? 0;
                                        $general_count = $people_count_by_date[$ruDate]['general'] ?? 0;
                                        ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="whitespace-nowrap px-4 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($ruDate ? thai_date_format((string) $ruDate) : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right text-slate-700"><?php echo $price !== null ? number_format($price, 2) : '-'; ?></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right text-slate-700"><?php echo number_format($qty, 2); ?></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right text-slate-700"><?php echo number_format($value, 2); ?></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right text-slate-700"><?php echo number_format($expend, 2); ?></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right font-semibold text-slate-900"><?php echo number_format($net, 2); ?></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right text-slate-700"><?php echo number_format($count); ?></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right text-slate-700"><?php echo number_format($member_count); ?>/<?php echo number_format($general_count); ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <tr class="bg-emerald-50 font-bold text-emerald-900">
                                        <td class="px-4 py-4">รวมตามตัวกรอง</td>
                                        <td class="px-4 py-4 text-right">-</td>
                                        <td class="px-4 py-4 text-right"><?php echo number_format($sum_total_qty, 2); ?></td>
                                        <td class="px-4 py-4 text-right"><?php echo number_format($sum_total_value, 2); ?></td>
                                        <td class="px-4 py-4 text-right"><?php echo number_format($sum_total_expend, 2); ?></td>
                                        <td class="px-4 py-4 text-right"><?php echo number_format($sum_total_net, 2); ?></td>
                                        <td class="px-4 py-4 text-right"><?php echo number_format($sum_total_rows); ?></td>
                                        <td class="px-4 py-4 text-right">-</td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-4 py-12 text-center text-slate-500">
                                            <?php echo heroicon('inbox', 'mx-auto mb-3 h-8 w-8 text-slate-400'); ?>
                                            ยังไม่มีข้อมูลสรุปในช่วงที่เลือก
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="border-t border-slate-200 py-6 text-center">
                <p class="text-base font-semibold text-slate-800">ต้องการเพิ่มข้อมูลการรวบรวมยาง?</p>
                <a href="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" class="mt-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    <?php echo heroicon('plus', 'h-5 w-5'); ?>
                    บันทึกข้อมูล
                </a>
            </section>
        </div>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm text-slate-500 sm:px-6 lg:px-8">
            <p class="font-semibold text-slate-700">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</p>
            <p>&copy; <?php echo date('Y'); ?> ระบบการซื้อขายยางพารา</p>
        </div>
    </footer>
</body>
</html>

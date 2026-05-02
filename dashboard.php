<?php
require_once 'functions.php';
require_login();

$cu = current_user();
$conn = db();
$dates = [];
$sql = "SELECT DISTINCT pr_date FROM tbl_price ORDER BY pr_date DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['pr_date'];
    }
}

$displayName = $cu['user_fullname'] ?? $cu['user_username'] ?? 'ผู้ใช้งาน';
$userLevel = $cu['user_level'] ?? '-';
$isAdmin = function_exists('is_admin') && is_admin();
?>
<!doctype html>
<html lang="th" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
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
    $siteNavNavId = 'dashboardNav';
    include __DIR__ . '/partials/site_nav.php';
    ?>

    <main class="bg-slate-50">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <section class="border-b border-slate-200 pb-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">
                            <?php echo heroicon('chart-bar', 'h-4 w-4'); ?>
                            แดชบอร์ดระบบ
                        </div>
                        <h1 class="mt-4 text-3xl font-bold leading-tight text-slate-950 sm:text-4xl">Dashboard</h1>
                        <p class="mt-2 text-base leading-7 text-slate-600">
                            สวัสดี <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                            <span class="font-semibold text-slate-800">(<?php echo htmlspecialchars($userLevel, ENT_QUOTES, 'UTF-8'); ?>)</span>
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[34rem]">
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Username</p>
                            <p class="mt-2 truncate text-sm font-semibold text-slate-950"><?php echo htmlspecialchars($cu['user_username'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Fullname</p>
                            <p class="mt-2 truncate text-sm font-semibold text-slate-950"><?php echo htmlspecialchars($cu['user_fullname'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Level</p>
                            <p class="mt-2 truncate text-sm font-semibold text-slate-950"><?php echo htmlspecialchars($userLevel, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 py-6 lg:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                            <?php echo heroicon('user-circle', 'h-5 w-5'); ?>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-slate-950">Account</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">ข้อมูลบัญชีที่ใช้งานอยู่</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3">
                        <div class="rounded-md bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Username</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($cu['user_username'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="rounded-md bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Fullname</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($cu['user_fullname'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="rounded-md bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Level</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($userLevel, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <a href="user_form.php?action=edit&id=<?php echo (int) ($cu['user_id'] ?? 0); ?>" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-semibold text-sky-700 no-underline transition hover:bg-sky-100 hover:no-underline">
                            <?php echo heroicon('pencil', 'h-4 w-4'); ?>
                            แก้ไขโปรไฟล์
                        </a>
                        <a href="wang_main.php" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white no-underline shadow-sm transition hover:bg-emerald-700 hover:no-underline">
                            <?php echo heroicon('archive-box', 'h-4 w-4'); ?>
                            วางยาง
                        </a>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-amber-50 text-amber-700">
                            <?php echo heroicon('document-text', 'h-5 w-5'); ?>
                        </span>
                        <div>
                            <h2 class="text-lg font-bold text-slate-950">Export Data</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">ส่งออกข้อมูลรายการตามรอบวันที่ที่รวบรวม</p>
                        </div>
                    </div>

                    <form class="mt-5 space-y-4" method="get" action="export_rubbers_export.php" id="exportForm">
                        <div>
                            <label for="pr_date" class="mb-2 block text-sm font-semibold text-slate-700">เลือกรอบวันที่</label>
                            <select id="pr_date" name="pr_date" required class="min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                <option value="">-- เลือกรอบวันที่ --</option>
                                <?php foreach ($dates as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(thai_date_format($d), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <button type="button" onclick="exportType('pdf')" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700">
                                <?php echo heroicon('document-text', 'h-4 w-4'); ?>
                                PDF
                            </button>
                            <button type="button" onclick="exportType('excel')" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                <?php echo heroicon('document', 'h-4 w-4'); ?>
                                Excel
                            </button>
                            <a href="export_round_matrix.php" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 no-underline transition hover:bg-emerald-100 hover:no-underline">
                                <?php echo heroicon('calendar-days', 'h-4 w-4'); ?>
                                หลายรอบ
                            </a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="border-t border-slate-200 py-6">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan-50 text-cyan-700">
                            <?php echo heroicon('users', 'h-5 w-5'); ?>
                        </span>
                        <div>
                            <h2 class="text-lg font-bold text-slate-950">Members Management</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">จัดการสมาชิกและรหัสบุคคล</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <a href="members.php" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white no-underline transition hover:bg-emerald-700 hover:no-underline">
                            <?php echo heroicon('list-bullet', 'h-4 w-4'); ?>
                            รายการสมาชิก
                        </a>
                        <a href="member_form.php?action=create" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 no-underline transition hover:bg-emerald-100 hover:no-underline">
                            <?php echo heroicon('user-plus', 'h-4 w-4'); ?>
                            เพิ่มสมาชิก
                        </a>
                        <a href="add_person_code.php" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 no-underline transition hover:bg-emerald-100 hover:no-underline">
                            <?php echo heroicon('user-plus', 'h-4 w-4'); ?>
                            เพิ่มรหัสบุคคล
                        </a>
                    </div>
                </div>
            </section>

            <?php if ($isAdmin): ?>
                <section class="border-t border-slate-200 py-6">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-lg border border-rose-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-rose-50 text-rose-700">
                                    <?php echo heroicon('user-circle', 'h-5 w-5'); ?>
                                </span>
                                <div>
                                    <h2 class="text-lg font-bold text-slate-950">User Management</h2>
                                    <p class="mt-1 text-sm leading-6 text-slate-500">จัดการบัญชีผู้ใช้งาน</p>
                                </div>
                            </div>
                            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                                <a href="users.php" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white no-underline transition hover:bg-rose-700 hover:no-underline">
                                    <?php echo heroicon('list-bullet', 'h-4 w-4'); ?>
                                    รายการผู้ใช้งาน
                                </a>
                                <a href="user_form.php?action=create" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 no-underline transition hover:bg-rose-100 hover:no-underline">
                                    <?php echo heroicon('user-plus', 'h-4 w-4'); ?>
                                    สร้างผู้ใช้ใหม่
                                </a>
                            </div>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-amber-50 text-amber-700">
                                    <?php echo heroicon('banknotes', 'h-5 w-5'); ?>
                                </span>
                                <div>
                                    <h2 class="text-lg font-bold text-slate-950">Prices Management</h2>
                                    <p class="mt-1 text-sm leading-6 text-slate-500">จัดการราคายาง</p>
                                </div>
                            </div>
                            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                                <a href="prices.php" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white no-underline transition hover:bg-amber-700 hover:no-underline">
                                    <?php echo heroicon('list-bullet', 'h-4 w-4'); ?>
                                    รายการราคายาง
                                </a>
                                <a href="price_form.php?action=create" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 no-underline transition hover:bg-amber-100 hover:no-underline">
                                    <?php echo heroicon('plus', 'h-4 w-4'); ?>
                                    เพิ่มราคายาง
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm text-slate-500 sm:px-6 lg:px-8">
            <p class="font-semibold text-slate-700">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</p>
            <p>&copy; <?php echo date('Y'); ?> ระบบการซื้อขายยางพารา</p>
        </div>
    </footer>

    <script>
        function exportType(type) {
            var form = document.getElementById('exportForm');
            var prDate = document.getElementById('pr_date').value;
            if (!prDate) {
                alert('กรุณาเลือกรอบวันที่');
                return;
            }
            var url = form.action + '?pr_date=' + encodeURIComponent(prDate) + '&export_type=' + type;
            if (type === 'excel') {
                url += '&bom=1';
            }
            window.open(url, '_blank');
        }
    </script>
</body>
</html>

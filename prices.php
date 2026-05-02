<?php
require_once 'functions.php';

$db = db();
$msg = isset($_GET['msg']) ? trim((string) $_GET['msg']) : '';
$canManagePrices = function_exists('is_admin') && is_admin();

$stmt = $db->prepare("SELECT pr_id, pr_year, pr_date, pr_number, pr_price, pr_saveby, pr_savedate FROM tbl_price ORDER BY pr_date DESC, pr_id DESC");
if (!$stmt) {
    die('Prepare failed: ' . $db->error);
}

$stmt->execute();
$result = $stmt->get_result();
$prices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$priceCount = count($prices);
$latestPrice = $prices[0]['pr_price'] ?? null;
$latestDate = $prices[0]['pr_date'] ?? null;
?>
<!doctype html>
<html lang="th" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ราคายาง</title>
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
<body class="min-h-full bg-gradient-to-br from-emerald-50 via-slate-50 to-teal-50 text-slate-700">
    <?php
    $siteNavOuterClass = 'sticky top-0 z-50 border-b border-emerald-200 bg-emerald-100/95 text-emerald-950 shadow-sm backdrop-blur';
    $siteNavInnerClass = 'mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8';
    $siteNavBrandBadge = 'ระบบการรวบรวมยาง';
    $siteNavBrandTitle = 'สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด';
    $siteNavBrandIcon = 'banknotes';
    $siteNavNavId = 'priceNav';
    include __DIR__ . '/partials/site_nav.php';
    ?>

    <main class="min-h-full">
        <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-3xl border border-emerald-100 bg-white/85 shadow-[0_18px_45px_rgba(15,23,42,0.08)] backdrop-blur">
                <div class="border-b border-emerald-100 bg-gradient-to-r from-emerald-50 via-white to-teal-50 px-6 py-5 sm:px-8">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-100/70 px-3 py-1 text-sm font-medium text-emerald-800">
                                <?php echo heroicon('banknotes', 'h-4 w-4'); ?>
                                ระบบจัดการราคายาง
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold tracking-tight text-slate-900">ราคายาง</h1>
                                <p class="mt-1 text-sm text-slate-500">รายการราคายางล่าสุด เรียงจากวันที่ใหม่ไปเก่า</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <?php if ($canManagePrices): ?>
                                <a href="dashboard.php" class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                    <?php echo heroicon('arrow-left', 'h-4 w-4'); ?>
                                    กลับ Dashboard
                                </a>
                                <a href="price_form.php?action=create" class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                    <?php echo heroicon('plus', 'h-4 w-4'); ?>
                                    เพิ่มราคายาง
                                </a>
                            <?php else: ?>
                              
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-6 sm:px-8">
                    <?php if ($msg !== ''): ?>
                        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                            <div class="flex items-start gap-2">
                                <?php echo heroicon('information-circle', 'mt-0.5 h-4 w-4 flex-none'); ?>
                                <p><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <section class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-emerald-700">จำนวนรายการ</p>
                                    <p class="mt-2 text-3xl font-bold text-slate-900"><?php echo number_format($priceCount); ?></p>
                                </div>
                                <div class="rounded-2xl bg-emerald-100 p-3 text-emerald-700">
                                    <?php echo heroicon('list-bullet', 'h-6 w-6'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-teal-100 bg-gradient-to-br from-teal-50 to-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-teal-700">วันที่ล่าสุด</p>
                                    <p class="mt-2 text-xl font-bold text-slate-900">
                                        <?php echo $latestDate ? htmlspecialchars(thai_date_format((string) $latestDate), ENT_QUOTES, 'UTF-8') : '-'; ?>
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-teal-100 p-3 text-teal-700">
                                    <?php echo heroicon('calendar-days', 'h-6 w-6'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-amber-700">ราคาล่าสุด</p>
                                    <p class="mt-2 text-3xl font-bold text-slate-900">
                                        <?php echo $latestPrice !== null ? number_format((float) $latestPrice, 2) : '-'; ?>
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-amber-100 p-3 text-amber-700">
                                    <?php echo heroicon('banknotes', 'h-6 w-6'); ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">ตารางราคายาง</h2>
                                <p class="text-sm text-slate-500">ค้นหาได้จากปี, วันที่, รอบ หรือราคา</p>
                            </div>

                            <label class="relative block w-full sm:w-96">
                                <span class="sr-only">ค้นหา</span>
                                <?php echo heroicon('magnifying-glass', 'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400'); ?>
                                <input
                                    id="priceSearch"
                                    type="search"
                                    placeholder="ค้นหาในตาราง..."
                                    class="w-full rounded-full border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100"
                                >
                            </label>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">#</th>
                                        <th class="px-4 py-3">ปี</th>
                                        <th class="px-4 py-3">วันที่</th>
                                        <th class="px-4 py-3">รอบ</th>
                                        <th class="px-4 py-3">ราคา</th>
                                <?php if ($canManagePrices): ?>
                                    <th class="px-4 py-3 text-right">จัดการ</th>
                                <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody id="priceTableBody" class="divide-y divide-slate-100 bg-white">
                                    <?php foreach ($prices as $p): ?>
                                        <tr class="price-row hover:bg-emerald-50/50">
                                            <td class="whitespace-nowrap px-4 py-4 font-medium text-slate-900"><?php echo (int) $p['pr_id']; ?></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-slate-700"><?php echo (int) $p['pr_year']; ?></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-slate-700">
                                                <?php echo htmlspecialchars(thai_date_format((string) $p['pr_date']), ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-4 text-slate-700">
                                                <?php echo htmlspecialchars((string) $p['pr_number'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-4">
                                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1.5 font-semibold text-emerald-700">
                                                    <?php echo heroicon('banknotes', 'h-4 w-4'); ?>
                                                    <?php echo number_format((float) $p['pr_price'], 2); ?> บาท
                                                </span>
                                            </td>
                                            <?php if ($canManagePrices): ?>
                                                <td class="whitespace-nowrap px-4 py-4 text-right">
                                                    <div class="inline-flex items-center gap-2">
                                                        <a
                                                            href="price_form.php?action=edit&id=<?php echo (int) $p['pr_id']; ?>"
                                                            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-sky-200 bg-sky-50 text-sky-700 transition hover:border-sky-300 hover:bg-sky-100"
                                                            title="แก้ไข"
                                                        >
                                                            <?php echo heroicon('pencil', 'h-4 w-4'); ?>
                                                        </a>
                                                        <form method="post" action="price_delete.php" onsubmit="return confirm('ลบราคานี้หรือไม่?');">
                                                            <input type="hidden" name="id" value="<?php echo (int) $p['pr_id']; ?>">
                                                            <button
                                                                type="submit"
                                                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-rose-700 transition hover:border-rose-300 hover:bg-rose-100"
                                                                title="ลบ"
                                                            >
                                                                <?php echo heroicon('trash', 'h-4 w-4'); ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div id="noMatchState" class="hidden border-t border-slate-200 px-6 py-12 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                <?php echo heroicon('magnifying-glass', 'h-6 w-6'); ?>
                            </div>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">ไม่พบรายการที่ค้นหา</h3>
                            <p class="mt-2 text-sm text-slate-500">ลองพิมพ์ปี วันที่ รอบ หรือราคาที่ต้องการค้นหาใหม่</p>
                        </div>

                        <?php if (empty($prices)): ?>
                            <div class="border-t border-slate-200 px-6 py-14 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                    <?php echo heroicon('inbox', 'h-6 w-6'); ?>
                                </div>
                                <h3 class="mt-4 text-base font-semibold text-slate-900">ยังไม่มีข้อมูลราคายาง</h3>
                                <p class="mt-2 text-sm text-slate-500">กดปุ่ม "เพิ่มราคายาง" เพื่อสร้างรายการแรก</p>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </section>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('priceSearch');
        const rows = Array.from(document.querySelectorAll('.price-row'));
        const noMatchState = document.getElementById('noMatchState');

        if (searchInput && rows.length) {
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim().toLowerCase();
                let visibleCount = 0;

                rows.forEach((row) => {
                    const text = row.textContent.toLowerCase();
                    const isVisible = query === '' || text.includes(query);
                    row.classList.toggle('hidden', !isVisible);
                    if (isVisible) {
                        visibleCount += 1;
                    }
                });

                if (noMatchState) {
                    noMatchState.classList.toggle('hidden', !(query !== '' && visibleCount === 0));
                }
            });
        }
    </script>
</body>
</html>

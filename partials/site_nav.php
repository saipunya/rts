<?php
$siteNavCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
$siteNavIsLoggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['user_username']) || !empty($_SESSION['username']) || !empty($_SESSION['member_id']);

$siteNavOuterClass = $siteNavOuterClass ?? 'sticky top-0 z-50 border-b border-emerald-200 bg-emerald-100/90 text-emerald-950 shadow-sm backdrop-blur';
$siteNavInnerClass = $siteNavInnerClass ?? 'mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8';
$siteNavBrandHref = $siteNavBrandHref ?? 'index.php';
$siteNavBrandBadge = $siteNavBrandBadge ?? 'ระบบการรวบรวมยาง';
$siteNavBrandTitle = $siteNavBrandTitle ?? 'สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด';
$siteNavBrandIcon = $siteNavBrandIcon ?? 'banknotes';
$siteNavNavId = $siteNavNavId ?? 'siteNav';
?>
<nav class="<?php echo htmlspecialchars($siteNavOuterClass, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="<?php echo htmlspecialchars($siteNavInnerClass, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center justify-between gap-3">
                <a href="<?php echo htmlspecialchars($siteNavBrandHref, ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-3 rounded-2xl px-1 py-1 no-underline transition hover:bg-white/50 hover:no-underline" style="text-decoration:none;">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm">
                        <?php echo heroicon($siteNavBrandIcon, 'h-5 w-5'); ?>
                    </span>
                    <span class="min-w-0 leading-tight no-underline [text-decoration:none]">
                        <span class="block text-sm font-semibold uppercase tracking-[0.22em] text-emerald-700"><?php echo htmlspecialchars($siteNavBrandBadge, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="block truncate text-base font-bold text-emerald-950 sm:text-lg"><?php echo htmlspecialchars($siteNavBrandTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                </a>

                <button
                    class="site-nav-toggle inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-emerald-200 bg-white text-emerald-800 shadow-sm transition hover:bg-emerald-50 lg:hidden"
                    type="button"
                    aria-controls="<?php echo htmlspecialchars($siteNavNavId, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-expanded="false"
                    aria-label="สลับเมนูนำทาง"
                >
                    <span class="site-nav-icon-open">
                        <?php echo heroicon('bars-3', 'h-5 w-5'); ?>
                    </span>
                    <span class="site-nav-icon-close hidden">
                        <?php echo heroicon('x-mark', 'h-5 w-5'); ?>
                    </span>
                </button>
            </div>

            <div
                class="site-nav hidden flex-col gap-2 rounded-3xl border border-emerald-200 bg-white/95 p-3 shadow-sm lg:flex lg:flex-row lg:flex-wrap lg:items-center lg:justify-end lg:border-transparent lg:bg-transparent lg:p-0 lg:shadow-none"
                id="<?php echo htmlspecialchars($siteNavNavId, ENT_QUOTES, 'UTF-8'); ?>"
            >
                <a href="index.php" class="<?php echo $siteNavCurrentPage === 'index.php' ? 'bg-white text-emerald-950 shadow-sm ring-1 ring-emerald-200' : 'text-emerald-800 hover:bg-emerald-50 hover:text-emerald-950'; ?> inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-base font-medium no-underline transition hover:no-underline">
                    <?php echo heroicon('home', 'h-5 w-5'); ?>
                    <span>หน้าหลัก</span>
                </a>
                <a href="rubbers.php" class="<?php echo $siteNavCurrentPage === 'rubbers.php' ? 'bg-white text-emerald-950 shadow-sm ring-1 ring-emerald-200' : 'text-emerald-800 hover:bg-emerald-50 hover:text-emerald-950'; ?> inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-base font-medium no-underline transition hover:no-underline">
                    <?php echo heroicon('archive-box', 'h-5 w-5'); ?>
                    <span>รวบรวมยาง</span>
                </a>
                <a href="prices.php" class="<?php echo $siteNavCurrentPage === 'prices.php' ? 'bg-white text-emerald-950 shadow-sm ring-1 ring-emerald-200' : 'text-emerald-800 hover:bg-emerald-50 hover:text-emerald-950'; ?> inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-base font-medium no-underline transition hover:no-underline">
                    <?php echo heroicon('banknotes', 'h-5 w-5'); ?>
                    <span>ราคาอ้างอิง</span>
                </a>

                <?php if ($siteNavIsLoggedIn): ?>
                    <a href="dashboard.php" class="<?php echo $siteNavCurrentPage === 'dashboard.php' ? 'bg-white text-emerald-950 shadow-sm ring-1 ring-emerald-200' : 'text-emerald-800 hover:bg-emerald-50 hover:text-emerald-950'; ?> inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-base font-medium no-underline transition hover:no-underline">
                        <?php echo heroicon('chart-bar', 'h-5 w-5'); ?>
                        <span>แดชบอร์ด</span>
                    </a>
                    <a href="logout.php" class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-base font-semibold text-rose-700 no-underline transition hover:bg-rose-50 hover:text-rose-800 hover:no-underline">
                        <?php echo heroicon('arrow-right-on-rectangle', 'h-5 w-5'); ?>
                        <span>ออกจากระบบ</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-base font-semibold text-sky-700 no-underline transition hover:bg-sky-50 hover:text-sky-800 hover:no-underline">
                        <?php echo heroicon('arrow-right-on-rectangle', 'h-5 w-5'); ?>
                        <span>เข้าสู่ระบบ</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<script>
    (function () {
        const toggle = document.querySelector('.site-nav-toggle');
        const nav = document.getElementById(<?php echo json_encode($siteNavNavId, JSON_UNESCAPED_UNICODE); ?>);
        const openIcon = document.querySelector('.site-nav-icon-open');
        const closeIcon = document.querySelector('.site-nav-icon-close');
        if (!toggle || !nav) return;

        toggle.addEventListener('click', function () {
            const isOpen = nav.classList.toggle('hidden') === false;
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (openIcon && closeIcon) {
                openIcon.classList.toggle('hidden', isOpen);
                closeIcon.classList.toggle('hidden', !isOpen);
            }
        });
    })();
</script>

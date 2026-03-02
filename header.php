<?php
// functions.php should be required by pages that need it to avoid double-includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$displayName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'ผู้ใช้งานระบบ';
$cycleLabel = $_SESSION['current_cycle_label'] ?? 'รอบรับซื้อปัจจุบัน';
$displayNameSafe = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!doctype html>
<html lang="en">
    <head>
        <title>++ระบบการรวบรวมยาง สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด ++</title>
        <!-- Required meta tags -->
        <meta charset="utf-8" />
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1, shrink-to-fit=no"
        />
        <!-- Bootstrap CSS v5.3 -->
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous"
        />
        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <!-- DataTables CSS (Bootstrap5 integration) -->
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
        <!-- AOS Animation Library -->
        <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
        <style>
            /* Fonts */
            @font-face { font-family: 'THSarabunNew'; font-style: normal; font-weight: 400; src: url('assets/fonts/THSarabunNew.ttf') format('truetype'); }
            @font-face { font-family: 'THSarabunNew'; font-style: normal; font-weight: 700; src: url('assets/fonts/THSarabunNew-Bold.ttf') format('truetype'); }
            @font-face { font-family: 'THSarabunNew'; font-style: italic; font-weight: 400; src: url('assets/fonts/THSarabunNew-italic.ttf') format('truetype'); }
            @font-face { font-family: 'THSarabunNew'; font-style: italic; font-weight: 700; src: url('assets/fonts/THSarabunNew-BoldItalic.ttf') format('truetype'); }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            :root {
                font-size: 19px; /* was ~16px, enlarge all rem-based text */
                --brand-dark: #0b1724;
                --brand-primary: #1ba37f;
                --brand-secondary: #0d9488;
                --brand-accent: #ffd166;
                --brand-soft: #e6f4f1;
            }

            /* Make any intentionally-small helper text readable */
            .small,
            .form-text {
                font-size: 15px !important;
            }

            body {
                min-height: 100vh;
                background: linear-gradient(180deg, #f7f9fb 0%, #eef2f4 35%, #e6edef 100%);
                font-family: 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
                color: #475467;
            }

            body::before {
                content: "";
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: 
                    radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                    radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
                    radial-gradient(circle at 40% 40%, rgba(255, 182, 193, 0.1) 0%, transparent 50%);
                pointer-events: none;
                z-index: -1;
            }

            main {
                padding: 1.5rem 0 2rem;
            }

            .app-shell {
                max-width: 1200px;
                margin: 0 auto;
            }

            .app-header {
                background: var(--brand-dark);
                color: #fff;
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
                position: relative;
                z-index: 10;
            }

            .topbar {
                background: linear-gradient(90deg, rgba(11, 23, 36, 0.95), rgba(13, 148, 136, 0.9));
                font-size: 1.1rem;
            }

            .status-pill {
                display: inline-flex;
                gap: 0.5rem;
                align-items: center;
                padding: 0.35rem 0.85rem;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.08);
                color: #f8fafc;
                font-size: 1.1rem;
            }

            .topbar-link {
                color: rgba(255, 255, 255, 0.85);
                text-decoration: none;
                font-weight: 500;
                transition: color 0.2s ease;
                font-size: 1.1rem;
            }

            .topbar-link:hover {
                color: #fff;
            }

            .topbar-actions {
                font-size: 1.2rem;
            }

            .topbar-info {
                font-size: 1.15rem;
            }

            .text-accent {
                color: var(--brand-accent) !important;
            }

            .page-hero {
                background: linear-gradient(120deg, rgba(27, 163, 127, 0.95) 0%, rgba(13, 148, 136, 0.9) 55%, rgba(10, 37, 64, 0.85) 100%);
                color: #ffffff;
                border-radius: 1rem;
                padding: 1.75rem 2rem;
                box-shadow: 0 16px 40px rgba(13, 74, 58, 0.35);
                position: relative;
                margin-bottom: 1.5rem;
            }

            .page-hero-inner {
                position: relative;
                z-index: 1;
            }

            .page-hero h1 {
                font-size: 2.75rem; /* bigger title */
                font-weight: 700;
                letter-spacing: .04em;
            }

            .page-hero h5 {
                font-size: 1.55rem;
                font-weight: 400;
                opacity: .95;
            }

            .page-hero .badge-pill {
                font-size: .95rem;
                background-color: rgba(255,255,255,.2);
                border-radius: 999px;
                padding: .25rem .9rem;
                backdrop-filter: blur(6px);
            }

            .hero-stats .stat-card {
                background: rgba(255, 255, 255, 0.14);
                border: 1px solid rgba(255, 255, 255, 0.25);
                border-radius: 0.85rem;
                padding: 1rem 1.1rem;
                display: flex;
                gap: 0.9rem;
                align-items: center;
                color: #f8fafc;
                min-height: 110px;
            }

            .stat-icon {
                width: 48px;
                height: 48px;
                border-radius: 999px;
                background: rgba(0, 0, 0, 0.15);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 1.3rem;
            }

            .stat-label {
                font-size: 1.2rem;
                opacity: 0.85;
            }

            .stat-value {
                font-size: 1.6rem;
                font-weight: 700;
            }

            .content-card {
                background: #ffffff;
                border-radius: 0.75rem;
                padding: 1.5rem;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                border: 1px solid #e9ecef;
                margin-bottom: 1.5rem;
            }

            .section-title {
                font-size: 1.35rem;
                font-weight: 600;
                color: #495057;
                display: flex;
                align-items: center;
                gap: .5rem;
                margin-bottom: 1rem;
                padding-bottom: 0.5rem;
                border-bottom: 2px solid #e9ecef;
            }

            .section-title i {
                color: #4a90e2;
            }

            /* Bigger font inside tables */
            table,
            .dataTable,
            table.dataTable tbody td,
            table.dataTable thead th,
            table.table td,
            table.table th {
                font-size: 1.3rem; /* increase cell & header size */
            }

            .btn-sm {
                font-size: 1.1rem;
                padding: .25rem .7rem;
            }

            .btn {
                font-size: 1.1rem;
                padding: 0.55rem 1.1rem;
                border-radius: 0.5rem;
                font-weight: 500;
                transition: all 0.2s ease;
            }

            .btn::before {
                display: none;
            }

            .btn-primary {
                background: #4a90e2;
                border: none;
                box-shadow: 0 2px 4px rgba(74, 144, 226, 0.3);
            }

            .btn-primary:hover {
                background: #357abd;
                box-shadow: 0 4px 8px rgba(74, 144, 226, 0.4);
            }

            .btn-outline-primary {
                background: transparent;
                border: 2px solid #4a90e2;
                color: #4a90e2;
            }

            .btn-outline-primary:hover {
                background: #4a90e2;
                border-color: #4a90e2;
                color: white;
            }

            .btn-outline-secondary {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                color: #6c757d;
            }

            .btn-outline-secondary:hover {
                background: #e9ecef;
                border-color: #ced4da;
                color: #495057;
            }

            .btn-danger, .btn-outline-danger {
                background: #dc3545;
                border-color: #dc3545;
                color: white;
            }

            .btn-outline-danger:hover {
                background: #c82333;
            }

            .card-table {
                border-radius: .85rem;
                border: 1px solid rgba(226, 232, 240, 0.8);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
                overflow: hidden;
            }

            table.dataTable thead th {
                background-color: #f1f5f9;
                border-bottom: 1px solid #e2e8f0;
                font-weight: 600;
            }

            .navbar-brand {
                font-weight: 700;
                letter-spacing: .02em;
            }

            header .navbar {
                box-shadow: 0 2px 8px rgba(15,23,42,0.12);
                background: linear-gradient(90deg, rgba(11, 23, 36, 0.95), rgba(11, 102, 98, 0.9));
                backdrop-filter: blur(14px);
            }

            .brand-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                background: rgba(255, 255, 255, 0.12);
                font-size: 1.5rem;
            }

            .brand-title {
                font-size: 1.35rem;
                text-transform: uppercase;
                letter-spacing: 0.06em;
            }

            .brand-subtitle {
                font-size: 1.05rem;
            }

            .navbar-nav .nav-link {
                color: rgba(255, 255, 255, 0.85);
                font-weight: 500;
                padding: 0.5rem 0.75rem;
                border-radius: 0.5rem;
                transition: background-color 0.2s ease, color 0.2s ease;
                font-size: 1.1rem;
            }

            .navbar-nav .nav-link:hover,
            .navbar-nav .nav-link:focus,
            .navbar-nav .nav-link.active {
                color: #fff;
                background: rgba(255, 255, 255, 0.1);
            }

            .nav-cta {
                background: var(--brand-accent);
                color: #1f2937 !important;
                font-weight: 700;
                box-shadow: 0 10px 25px rgba(255, 209, 102, 0.35);
                padding: 0.45rem 1.4rem;
            }

            .nav-cta:hover {
                background: #ffe08a;
                color: #111827 !important;
            }

            .navbar-toggler {
                border: 1px solid rgba(255, 255, 255, 0.4);
            }

            footer {
                padding: 1.5rem 0 1.75rem;
                color: #6b7280;
            }

            @media (max-width: 768px) {
                main {
                    padding-top: 1rem;
                }
                .page-hero {
                    padding: 1.1rem 1.25rem;
                }
                .page-hero h1 {
                    font-size: 2rem;
                }
                .page-hero h5 {
                    font-size: 1.2rem;
                }
                .content-card {
                    padding: 1.1rem 1.2rem;
                }
            }
        </style>
    </head>
    <body>
        <header class="app-header">
            <div class="topbar">
                <div class="app-shell d-flex flex-wrap gap-2 justify-content-between align-items-center py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 text-white-75 small">
                        <span class="status-pill">
                            <i class="bi bi-droplet-half"></i>
                            <span>สถานะรอบรับซื้อ: <strong><?php echo htmlspecialchars($cycleLabel, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                        </span>
                        <span class="d-none d-md-inline opacity-75">อัปเดตล่าสุด: <?php echo date('d M Y'); ?></span>
                    </div>
                    <div class="topbar-actions d-flex flex-wrap align-items-center gap-3">
                        <span class="d-flex align-items-center gap-2 text-white small">
                            <i class="bi bi-person-circle text-accent"></i>
                            <span><?php echo $displayNameSafe; ?></span>
                        </span>
                        <a href="dashboard.php" class="topbar-link">แดชบอร์ด</a>
                        <a href="report_rubber.php" class="topbar-link">รายงาน</a>
                        <a href="logout.php" class="topbar-link text-warning fw-semibold">ออกจากระบบ</a>
                    </div>
                </div>
            </div>
            <nav class="navbar navbar-expand-lg navbar-dark py-3" aria-label="Primary navigation">
                <div class="app-shell">
                    <a class="navbar-brand d-flex align-items-center gap-3 text-white" href="index.php">
                        <span class="brand-icon d-inline-flex align-items-center justify-content-center">
                            <i class="bi bi-tree-fill"></i>
                        </span>
                        <div class="lh-sm">
                            <div class="brand-title">ระบบการซื้อขายยาง</div>
                            <small class="brand-subtitle text-white-50">สหกรณ์การเกษตรโครงการทุ่งลุยลาย</small>
                        </div>
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse mt-3 mt-lg-0" id="mainNav">
                        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">แดชบอร์ด</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'members.php' ? 'active' : ''; ?>" href="members.php">สมาชิก</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'rubbers.php' ? 'active' : ''; ?>" href="rubbers.php">ยางพารา</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'prices.php' ? 'active' : ''; ?>" href="prices.php">ราคาอ้างอิง</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage === 'report_rubber.php' ? 'active' : ''; ?>" href="report_rubber.php">รายงาน</a>
                            </li>
                            <li class="nav-item ms-lg-2">
                                <a class="btn nav-cta" href="rubbers.php?lan=all">บันทึกรายการยาง</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
        <main>
            <div class="app-shell">
                <div class="page-hero mt-3">
                    <div class="page-hero-inner d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                        <div>
                            <h1 class="mb-1">ระบบการซื้อขาย รวบรวม ยาง</h1>
                            <h5 class="mb-0">สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</h5>
                        </div>
                        <div class="text-md-end">
                            <span class="badge-pill text-light d-inline-flex align-items-center gap-1">
                                <i class="bi bi-speedometer2"></i>
                                <span>ระบบงานจัดการยางพารา</span>
                            </span>
                        </div>
                    </div>
                    <div class="hero-stats row mt-4 g-3">
                        <div class="col-12 col-sm-4">
                            <div class="stat-card" data-aos="fade-up" data-aos-delay="50">
                                <div class="stat-icon text-success">
                                    <i class="bi bi-journal-check"></i>
                                </div>
                                <div>
                                    <p class="stat-label mb-1">บันทึกการรับซื้อ</p>
                                    <p class="stat-value mb-1">ควบคุมการรับยาง</p>
                                    <small class="text-white-75">ตรวจสอบรายการได้ทันทีในเมนู “ยางพารา”</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                                <div class="stat-icon text-warning">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div>
                                    <p class="stat-label mb-1">ราคาและรอบ</p>
                                    <p class="stat-value mb-1">อัปเดตราคาทันตลาด</p>
                                    <small class="text-white-75">จัดการเกณฑ์ราคาในเมนู “ราคาอ้างอิง”</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="stat-card" data-aos="fade-up" data-aos-delay="150">
                                <div class="stat-icon text-info">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <p class="stat-label mb-1">สมาชิกและผู้ส่งมอบ</p>
                                    <p class="stat-value mb-1">ข้อมูลครบทุกลาน</p>
                                    <small class="text-white-75">บริหารสมาชิกในเมนู “สมาชิก”</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-card mt-3">
                    <!-- หน้าหลักแต่ละเพจจะต่อเนื่องจาก container นี้ -->
                    <!-- เนื้อหาของแต่ละหน้าให้วางต่อจาก div นี้ และปิด div/แท็กต่าง ๆ ใน footer.php -->
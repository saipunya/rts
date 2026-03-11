<?php
// functions.php should be required by pages that need it to avoid double-includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$displayName = $_SESSION['user_fullname'] ?? $_SESSION['user_username'] ?? $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'ผู้ใช้งานระบบ';
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

            @media (max-width: 992px) {
                .topbar .app-shell {
                    align-items: center;
                }

                .topbar-actions.collapse {
                    width: 100%;
                }

                .topbar-actions.collapse .topbar-link {
                    display: block;
                    width: 100%;
                    text-align: left;
                    padding: 0.65rem 0.75rem;
                }

                .topbar-actions.collapse.show {
                    margin-top: 0.75rem;
                    padding-top: 0.75rem;
                    border-top: 1px solid #b8dabc;
                }

                .topbar-actions.collapse.show > * {
                    margin-bottom: 0.35rem;
                }

                .topbar-actions.collapse.show > *:last-child {
                    margin-bottom: 0;
                }

                .topbar-actions .d-flex.align-items-center.gap-2 {
                    width: 100%;
                    justify-content: flex-start;
                    padding: 0.65rem 0.75rem;
                    background: rgba(255, 255, 255, 0.4);
                    border-radius: 0.5rem;
                }

                .topbar-actions .topbar-link.text-warning,
                .topbar-actions .topbar-link.text-primary {
                    font-weight: 600;
                }
            }

            :root {
                font-size: 18px; /* slightly larger baseline for THSarabun readability */
                --brand-dark: #5a6c7d;
                --brand-primary: #6b8fa3;
                --brand-secondary: #8fa3b1;
                --brand-accent: #d4a574;
                --brand-soft: #f0f4f7;
            }

            html {
                font-size: 18px;
            }

            /* Make any intentionally-small helper text readable */
            .small,
            .form-text {
                font-size: 1rem !important;
            }

            body {
                min-height: 100vh;
                background: #f5f7f9;
                font-family: 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
                font-size: 1rem;
                line-height: 1.5;
                color: #475467;
            }

            /* Typography baseline for all main pages */
            .content-card,
            .card,
            .table,
            .form-control,
            .form-select,
            .form-label,
            .nav-link,
            .dropdown-item,
            .alert,
            .badge {
                font-size: 1rem;
            }

            body::before {
                content: "";
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
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
                background: #d4edda;
                color: #155724;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                position: relative;
                z-index: 10;
                border-bottom: 1px solid #c3e6cb;
            }

            .topbar {
                background: #c3e6cb;
                font-size: 1rem;
                border-bottom: 1px solid #b8dabc;
            }

            .status-pill {
                display: inline-flex;
                gap: 0.5rem;
                align-items: center;
                padding: 0.35rem 0.85rem;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.3);
                color: #155724;
                font-size: 1rem;
            }

            .topbar-link {
                color: #155724;
                text-decoration: none;
                font-weight: 500;
                transition: color 0.2s ease;
                font-size: 0.9rem;
            }

            .topbar-link:hover {
                color: #0d5322;
            }

            .topbar-actions {
                font-size: 1rem;
            }

            .topbar-info {
                font-size: 1rem;
            }

            .topbar-toggle {
                background: rgba(255, 255, 255, 0.25);
                border: 1px solid #b8dabc;
                border-radius: 0.75rem;
                width: 48px;
                height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                transition: background 0.2s ease, border-color 0.2s ease;
            }

            .topbar-toggle:hover,
            .topbar-toggle:focus {
                background: rgba(255, 255, 255, 0.4);
                border-color: #9fc9a6;
                outline: none;
            }

            .topbar-toggle .toggle-bars {
                width: 22px;
                height: 18px;
                position: relative;
            }

            .topbar-toggle .bar {
                position: absolute;
                left: 0;
                right: 0;
                height: 2.5px;
                border-radius: 999px;
                background: #0d5322;
                transition: transform 0.25s ease, top 0.25s ease, opacity 0.2s ease;
            }

            .topbar-toggle .bar:nth-child(1) {
                top: 0;
            }

            .topbar-toggle .bar:nth-child(2) {
                top: 7.5px;
            }

            .topbar-toggle .bar:nth-child(3) {
                top: 15px;
            }

            .topbar-toggle[aria-expanded="true"] .bar:nth-child(1) {
                top: 7.5px;
                transform: rotate(45deg);
            }

            .topbar-toggle[aria-expanded="true"] .bar:nth-child(2) {
                opacity: 0;
            }

            .topbar-toggle[aria-expanded="true"] .bar:nth-child(3) {
                top: 7.5px;
                transform: rotate(-45deg);
            }

            .text-accent {
                color: var(--brand-accent) !important;
            }

            .page-hero {
                color: #495057;
                border-radius: 0.75rem;
                padding: 1.75rem 2rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                position: relative;
                margin-bottom: 1.5rem;
                background: #ffffff;
                border: 1px solid #e9ecef;
            }

            .page-hero-inner {
                position: relative;
                z-index: 1;
            }

            .page-hero h1 {
                font-size: 2rem; /* normal title size */
                font-weight: 700;
                letter-spacing: .04em;
            }

            .page-hero h5 {
                font-size: 1.25rem;
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
                font-size: 1.1rem;
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
                color: #28a745;
            }

            /* Normal font inside tables */
            table,
            .dataTable,
            table.dataTable tbody td,
            table.dataTable thead th,
            table.table td,
            table.table th {
                font-size: 1rem; /* normal cell & header size */
            }

            .btn-sm {
                font-size: 1rem;
                padding: .25rem .5rem;
            }

            .btn {
                font-size: 1rem;
                padding: 0.375rem 0.75rem;
                border-radius: 0.375rem;
                font-weight: 500;
                transition: all 0.2s ease;
            }

            .btn::before {
                display: none;
            }

            .btn-primary {
                background: #28a745;
                border: none;
                box-shadow: none;
            }

            .btn-primary:hover {
                background: #218838;
                box-shadow: none;
            }

            .btn-outline-primary {
                background: transparent;
                border: 2px solid #28a745;
                color: #28a745;
            }

            .btn-outline-primary:hover {
                background: #28a745;
                border-color: #28a745;
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
                box-shadow: none;
                background: #d4edda;
                backdrop-filter: none;
                border-bottom: 1px solid #c3e6cb;
            }

            .brand-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                background: #d4edda;
                font-size: 1.5rem;
                color: #28a745;
            }

            .brand-title {
                font-size: 1.1rem;
                text-transform: uppercase;
                letter-spacing: 0.06em;
            }

            .brand-subtitle {
                font-size: 1rem;
            }

            .navbar-nav .nav-link,
            .topbar-actions .topbar-link {
                color: #155724;
                font-weight: 500;
                padding: 0.5rem 0.75rem;
                border-radius: 0.5rem;
                transition: background-color 0.2s ease, color 0.2s ease;
                font-size: 1.2rem;
            }

            .navbar-nav .nav-link:hover,
            .navbar-nav .nav-link:focus,
            .navbar-nav .nav-link.active,
            .topbar-actions .topbar-link:hover,
            .topbar-actions .topbar-link:focus,
            .topbar-actions .topbar-link.active {
                color: #0d5322;
                background: rgba(255, 255, 255, 0.3);
            }

            .nav-cta {
                background: #28a745;
                color: #ffffff !important;
                font-weight: 700;
                box-shadow: none;
                padding: 0.45rem 1.4rem;
            }

            .nav-cta:hover {
                background: #218838;
                color: #ffffff !important;
            }

            .navbar-toggler {
                border: 1px solid #e9ecef;
            }

            footer {
                padding: 1.5rem 0 1.75rem;
                color: #6b7280;
            }

            /* Enhanced Responsive Design */
            @media (max-width: 992px) {
                .app-shell {
                    max-width: 100%;
                    padding: 0 1rem;
                }
                
                .topbar {
                    padding: 0.75rem 0;
                }
                
                .status-pill {
                    font-size: 0.9rem;
                    padding: 0.25rem 0.65rem;
                }
                
                .topbar-link {
                    font-size: 0.9rem;
                }
            }

            @media (max-width: 768px) {
                main {
                    padding-top: 1rem;
                }
                
                .page-hero {
                    padding: 1.1rem 1.25rem;
                }
                
                .page-hero h1 {
                    font-size: 1.5rem;
                }
                
                .page-hero h5 {
                    font-size: 1rem;
                }
                
                .content-card {
                    padding: 1.1rem 1.2rem;
                }
                
                .topbar {
                    padding: 0.5rem 0;
                }
                
                .topbar .app-shell {
                    flex-direction: column;
                    align-items: stretch !important;
                    gap: 0.75rem;
                }
                
                .topbar-info {
                    order: 1;
                    justify-content: center;
                }
                
                .topbar-actions {
                    order: 2;
                    justify-content: center;
                    flex-wrap: wrap !important;
                    gap: 0.5rem !important;
                }
                
                .topbar-link {
                    font-size: 1.1rem;
                    padding: 0.25rem 0.5rem;
                }
                
                .status-pill {
                    font-size: 1rem;
                    padding: 0.2rem 0.5rem;
                    justify-content: center;
                }
                
                /* Mobile navigation improvements */
                .navbar-nav {
                    text-align: center;
                }
                
                .navbar-nav .nav-link {
                    padding: 0.75rem 1rem;
                    border-radius: 0.5rem;
                    margin: 0.25rem 0;
                }
                
                .nav-cta {
                    margin-top: 0.5rem;
                    width: 100%;
                    text-align: center;
                }
                
                /* Responsive tables */
                .table-responsive {
                    font-size: 0.9rem;
                }
                
                .table th,
                .table td {
                    padding: 0.5rem;
                    vertical-align: middle;
                }
                
                /* Responsive forms */
                .form-control,
                .form-select {
                    font-size: 1rem;
                    padding: 0.5rem;
                }
                
                .form-label {
                    font-size: 1rem;
                    margin-bottom: 0.25rem;
                }
                
                /* Responsive buttons */
                .btn {
                    font-size: 1rem;
                    padding: 0.5rem 0.75rem;
                    min-height: 44px; /* Touch-friendly */
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .btn-sm {
                    font-size: 1rem;
                    padding: 0.375rem 0.5rem;
                    min-height: 38px;
                }
                
                /* Responsive cards */
                .card {
                    margin-bottom: 1rem;
                }
                
                .stat-value {
                    font-size: 1.4rem;
                }
                
                .stat-label {
                    font-size: 1rem;
                }
            }

            @media (max-width: 576px) {
                :root {
                    font-size: 16px; /* Smaller base font for very small screens */
                }
                
                body {
                    font-size: 1rem;
                }
                
                .app-shell {
                    padding: 0 0.5rem;
                }
                
                .page-hero {
                    padding: 1rem;
                    margin-bottom: 1rem;
                }
                
                .page-hero h1 {
                    font-size: 1.25rem;
                }
                
                .content-card {
                    padding: 1rem;
                }
                
                .topbar-info .d-none.d-md-inline {
                    display: none !important;
                }
                
                .topbar-actions {
                    gap: 0.25rem !important;
                }
                
                .topbar-link {
                    font-size: 1rem;
                    padding: 0.2rem 0.4rem;
                }
                
                .status-pill {
                    font-size: 0.8rem;
                    padding: 0.15rem 0.4rem;
                }
                
                /* Extra small screen adjustments */
                .table-responsive {
                    font-size: 0.85rem;
                }
                
                .table th,
                .table td {
                    padding: 0.4rem 0.3rem;
                }
                
                .btn {
                    font-size: 0.9rem;
                    padding: 0.4rem 0.6rem;
                }
                
                .btn-sm {
                    font-size: 0.8rem;
                    padding: 0.3rem 0.4rem;
                }
                
                .stat-value {
                    font-size: 1rem;
                }
                
                .stat-label {
                    font-size: 0.9rem;
                }
                
                /* Hide some less critical elements on very small screens */
                .section-title i {
                    display: none;
                }
            }

            /* Landscape orientation adjustments */
            @media (max-width: 768px) and (orientation: landscape) {
                .page-hero {
                    padding: 0.75rem 1rem;
                }
                
                .content-card {
                    padding: 1rem;
                }
                
                .topbar {
                    padding: 0.4rem 0;
                }
            }

            /* Touch-friendly improvements */
            @media (hover: none) and (pointer: coarse) {
                .btn,
                .nav-link,
                .topbar-link {
                    min-height: 44px;
                }
                
                .form-control,
                .form-select {
                    min-height: 44px;
                }
                
                .table-hover tbody tr:hover td {
                    background-color: transparent;
                }
            }
        </style>
    </head>
    <body>
        <header class="app-header">
            <div class="topbar">
                <div class="app-shell d-flex flex-wrap gap-2 justify-content-between align-items-center py-2">
                    <div class="topbar-info d-flex flex-wrap align-items-center gap-2 text-white-75 small">
                        <span class="status-pill">
                            <i class="bi bi-droplet-half"></i>
                            <span class="d-none d-md-inline fs-6">ระบบการรวบรวมยาง</span>
                        </span>
                        
                    </div>
                    <button
                        class="topbar-toggle d-lg-none"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#topbarNav"
                        aria-controls="topbarNav"
                        aria-expanded="false"
                        aria-label="สลับเมนูนำทาง"
                    >
                        <span class="toggle-bars" aria-hidden="true">
                            <span class="bar"></span>
                            <span class="bar"></span>
                            <span class="bar"></span>
                        </span>
                    </button>
                    <div class="topbar-actions collapse d-lg-flex flex-wrap align-items-center gap-3" id="topbarNav">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle text-accent"></i>
                            <span><?php echo $displayNameSafe; ?></span>
                        </div>
                       <!-- เพิ่มหน้าหลัก -->
                        <a href="index.php" class="topbar-link"><i class="bi bi-house me-1"></i>หน้าหลัก</a>
                        <a href="rubbers.php" class="topbar-link"><i class="bi bi-droplet me-1"></i>รวบรวมยาง</a>
                        <a href="prices.php" class="topbar-link"><i class="bi bi-cash-coin me-1"></i>ราคาอ้างอิง</a>

                      
                        
                        <?php if (!empty($_SESSION['user_id']) || !empty($_SESSION['user_username']) || !empty($_SESSION['username']) || !empty($_SESSION['member_id'])): ?>
                        <a href="dashboard.php" class="topbar-link"><i class="bi bi-speedometer2 me-1"></i>แดชบอร์ด</a>
                        <a href="logout.php" class="topbar-link text-danger fw-semibold"><i class="bi bi-box-arrow-right me-1"></i>ออกจากระบบ</a>
                        <?php else: ?>
                        <a href="login.php" class="topbar-link text-primary fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
                    </header>
        <main>
            <div class="app-shell">

                <div class="content-card">
                    <!-- หน้าหลักแต่ละเพจจะต่อเนื่องจาก container นี้ -->
                    <!-- เนื้อหาของแต่ละหน้าให้วางต่อจาก div นี้ และปิด div/แท็กต่าง ๆ ใน footer.php -->
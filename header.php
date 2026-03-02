<?php
// functions.php should be required by pages that need it to avoid double-includes
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
                font-size: 18px; /* was ~16px, enlarge all rem-based text */
            }

            /* Make any intentionally-small helper text readable */
            .small,
            .form-text {
                font-size: 14px !important;
            }

            body {
                min-height: 100vh;
                background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
                font-family: 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
                color: #495057;
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

            .page-hero {
                background: linear-gradient(135deg, #4a90e2 0%, #5fa3e8 100%);
                color: #ffffff;
                border-radius: 1rem;
                padding: 1.75rem 2rem;
                box-shadow: 0 4px 15px rgba(74, 144, 226, 0.25);
                position: relative;
                margin-bottom: 1.5rem;
            }

            .page-hero-inner {
                position: relative;
                z-index: 1;
            }

            .page-hero h1 {
                font-size: 2.4rem; /* bigger title */
                font-weight: 700;
                letter-spacing: .04em;
            }

            .page-hero h5 {
                font-size: 1.35rem;
                font-weight: 400;
                opacity: .95;
            }

            .page-hero .badge-pill {
                font-size: .95rem;
                background-color: rgba(15,23,42,.15);
                border-radius: 999px;
                padding: .25rem .9rem;
                backdrop-filter: blur(6px);
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
                font-size: 1.25rem;
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
                font-size: 1.2rem; /* increase cell & header size */
            }

            .btn-sm {
                font-size: 1.1rem;
                padding: .25rem .7rem;
            }

            .btn {
                font-size: 1rem;
                padding: 0.5rem 1rem;
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
                letter-spacing: .06em;
            }

            header .navbar {
                box-shadow: 0 2px 8px rgba(15,23,42,0.06);
                background-color: rgba(15,23,42,0.88) !important;
                backdrop-filter: blur(14px);
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
                </div>

                <div class="content-card mt-3">
                    <!-- หน้าหลักแต่ละเพจจะต่อเนื่องจาก container นี้ -->
                    <!-- เนื้อหาของแต่ละหน้าให้วางต่อจาก div นี้ และปิด div/แท็กต่าง ๆ ใน footer.php -->
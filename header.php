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

        <style>
            /* Custom layout tweaks */
            body { background-color: #f8fafc; }
            .navbar-brand { font-weight: 700; letter-spacing: .5px; }
            header .navbar { box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
            .page-hero { padding: 1.25rem 0; background: linear-gradient(90deg,#0d6efd10,#0d6efd05); border-radius: .5rem; }
            .card-table { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
            table.dataTable thead th { background-color: #f1f5f9; }
            .btn-sm { padding: .25rem .5rem; }
            footer { padding: 1.25rem 0; color: #6b7280; }
        </style>
    </head>

    <body>
        <header>
            <!-- place navbar here -->
            <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
                <div class="container-fluid">
                    <a class="navbar-brand" href="index.php">RTS</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item active">
                                <a class="nav-link" href="index.php" aria-current="page">Home</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="prices.php">ราคายาง</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Features</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Pricing</a>
                            </li>
                        </ul>
                        <ul class="navbar-nav ms-auto">
                            <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
                                <?php if (function_exists('current_user')): $cu = current_user(); endif; ?>
                                
                                <li class="nav-item">
                                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="logout.php">Logout</a>
                                </li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="login.php">Login</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
        <main>
            <div class="container">
                <div class="row mt-3 mb-3"></div>
                    <div class="col-12 text-center page-hero">
                        <h1 class="mb-1">ระบบการซื้อขาย รวบรวม ยาง</h1>
                        <h5>สหกรณ์การเกษตรโครงการทุ่งลุยลาย จำกัด</h5>
                    </div>
                </div>
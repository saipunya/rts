<?php
require_once __DIR__ . '/functions.php';

db(); // ensure connection & session

const MEMBER_SESSION_KEY = 'member_portal';

function normalize_person_code(?string $value): string {
    if ($value === null) {
        return '';
    }
    return preg_replace('/\D+/', '', (string)$value);
}

function validate_person_code(string $code): bool {
    return strlen($code) === 4 && ctype_digit($code);
}


$member = $_SESSION[MEMBER_SESSION_KEY] ?? null;
$errors = [];
$loginMsg = '';

if (isset($_GET['logout'])) {
    unset($_SESSION[MEMBER_SESSION_KEY]);
    header('Location: allmember.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'member_login') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'ไม่สามารถยืนยันความถูกต้องของคำขอได้ กรุณาลองใหม่อีกครั้ง';
    } else {
        $memNumber = trim((string)($_POST['mem_number'] ?? ''));
        $personCode = normalize_person_code($_POST['mem_personcode'] ?? '');
        if ($memNumber === '' || $personCode === '') {
            $errors[] = 'กรุณากรอกเลขสมาชิกและรหัสบุคคล';
        } elseif (!validate_person_code($personCode)) {
            $errors[] = 'รหัสบุคคลต้องเป็นตัวเลข 4 หลัก';
        } else {
            $db = db();
            $stm = $db->prepare('SELECT mem_id, mem_fullname, mem_number, mem_personcode, mem_group, mem_class FROM tbl_member WHERE mem_number = ? LIMIT 1');
            if ($stm) {
                $stm->bind_param('s', $memNumber);
                $stm->execute();
                $res = $stm->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stm->close();

                if ($row) {
                    $storedPersonCode = normalize_person_code($row['mem_personcode'] ?? '');
                    if ($storedPersonCode !== '' && $storedPersonCode === $personCode) {
                        $_SESSION[MEMBER_SESSION_KEY] = [
                            'mem_id' => (int)$row['mem_id'],
                            'mem_number' => $row['mem_number'],
                            'mem_fullname' => $row['mem_fullname'],
                            'mem_group' => $row['mem_group'],
                            'mem_class' => $row['mem_class'],
                            'mem_personcode' => $storedPersonCode,
                        ];
                        header('Location: allmember.php');
                        exit;
                    }
                    $errors[] = 'รหัสบุคคลไม่ถูกต้อง';
                } else {
                    $errors[] = 'ไม่พบข้อมูลเลขสมาชิกนี้';
                }
            } else {
                $errors[] = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . db()->error;
            }
        }
    }
}

$member = $_SESSION[MEMBER_SESSION_KEY] ?? null;
$csrf = csrf_token();

$availableYears = [];
$selectedYear = null;
$summaryRows = [];
$totalsByYear = [];
$overallTotals = [
    'quantity' => 0.0,
    'value' => 0.0,
    'expend' => 0.0,
    'netvalue' => 0.0,
];
$deductionTotalsByYear = [];
$overallDeductionBreakdown = [
    'hoon' => 0.0,
    'loan' => 0.0,
    'shortdebt' => 0.0,
    'deposit' => 0.0,
    'tradeloan' => 0.0,
    'insurance' => 0.0,
];
$deductionLabels = [
    'hoon' => 'หุ้น',
    'loan' => 'เงินกู้',
    'shortdebt' => 'หนี้สั้น',
    'deposit' => 'เงินฝาก',
    'tradeloan' => 'กู้ซื้อขาย',
    'insurance' => 'ประกันภัย',
];

if ($member) {
    $db = db();
    if ($stmtYear = $db->prepare('SELECT DISTINCT YEAR(ru_date) AS y FROM tbl_rubber WHERE ru_number = ? ORDER BY y DESC')) {
        $stmtYear->bind_param('s', $member['mem_number']);
        $stmtYear->execute();
        $resYear = $stmtYear->get_result();
        while ($yr = $resYear->fetch_assoc()) {
            if (!empty($yr['y'])) {
                $availableYears[] = (int)$yr['y'];
            }
        }
        $stmtYear->close();
    }

    if (isset($_GET['year']) && $_GET['year'] !== '') {
        $candidate = (int)$_GET['year'];
        if (in_array($candidate, $availableYears, true)) {
            $selectedYear = $candidate;
        }
    }

    $sql = 'SELECT YEAR(ru_date) AS year,
                   ru_date,
                   SUM(ru_quantity) AS total_quantity,
                   SUM(ru_value) AS total_value,
                   SUM(ru_expend) AS total_expend,
                   SUM(ru_netvalue) AS total_netvalue,
                   SUM(ru_hoon) AS total_hoon,
                   SUM(ru_loan) AS total_loan,
                   SUM(ru_shortdebt) AS total_shortdebt,
                   SUM(ru_deposit) AS total_deposit,
                   SUM(ru_tradeloan) AS total_tradeloan,
                   SUM(ru_insurance) AS total_insurance
            FROM tbl_rubber
            WHERE ru_number = ?';
    $types = 's';
    $params = [$member['mem_number']];
    if ($selectedYear !== null) {
        $sql .= ' AND YEAR(ru_date) = ?';
        $types .= 'i';
        $params[] = $selectedYear;
    }
    $sql .= ' GROUP BY YEAR(ru_date), ru_date ORDER BY year DESC, ru_date DESC';

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $year = (int)$row['year'];
            if (!isset($summaryRows[$year])) {
                $summaryRows[$year] = [];
                $totalsByYear[$year] = [
                    'quantity' => 0.0,
                    'value' => 0.0,
                    'expend' => 0.0,
                    'netvalue' => 0.0,
                ];
                $deductionTotalsByYear[$year] = [
                    'hoon' => 0.0,
                    'loan' => 0.0,
                    'shortdebt' => 0.0,
                    'deposit' => 0.0,
                    'tradeloan' => 0.0,
                    'insurance' => 0.0,
                ];
            }
            $summaryRows[$year][] = [
                'ru_date' => $row['ru_date'],
                'total_quantity' => (float)$row['total_quantity'],
                'total_value' => (float)$row['total_value'],
                'total_expend' => (float)$row['total_expend'],
                'total_netvalue' => (float)$row['total_netvalue'],
                'total_hoon' => (float)$row['total_hoon'],
                'total_loan' => (float)$row['total_loan'],
                'total_shortdebt' => (float)$row['total_shortdebt'],
                'total_deposit' => (float)$row['total_deposit'],
                'total_tradeloan' => (float)$row['total_tradeloan'],
                'total_insurance' => (float)$row['total_insurance'],
            ];
            $totalsByYear[$year]['quantity'] += (float)$row['total_quantity'];
            $totalsByYear[$year]['value'] += (float)$row['total_value'];
            $totalsByYear[$year]['expend'] += (float)$row['total_expend'];
            $totalsByYear[$year]['netvalue'] += (float)$row['total_netvalue'];
            $deductionTotalsByYear[$year]['hoon'] += (float)$row['total_hoon'];
            $deductionTotalsByYear[$year]['loan'] += (float)$row['total_loan'];
            $deductionTotalsByYear[$year]['shortdebt'] += (float)$row['total_shortdebt'];
            $deductionTotalsByYear[$year]['deposit'] += (float)$row['total_deposit'];
            $deductionTotalsByYear[$year]['tradeloan'] += (float)$row['total_tradeloan'];
            $deductionTotalsByYear[$year]['insurance'] += (float)$row['total_insurance'];
            $overallTotals['quantity'] += (float)$row['total_quantity'];
            $overallTotals['value'] += (float)$row['total_value'];
            $overallTotals['expend'] += (float)$row['total_expend'];
            $overallTotals['netvalue'] += (float)$row['total_netvalue'];
            $overallDeductionBreakdown['hoon'] += (float)$row['total_hoon'];
            $overallDeductionBreakdown['loan'] += (float)$row['total_loan'];
            $overallDeductionBreakdown['shortdebt'] += (float)$row['total_shortdebt'];
            $overallDeductionBreakdown['deposit'] += (float)$row['total_deposit'];
            $overallDeductionBreakdown['tradeloan'] += (float)$row['total_tradeloan'];
            $overallDeductionBreakdown['insurance'] += (float)$row['total_insurance'];
        }
        $stmt->close();
    }
}

?><!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2e7d32">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="สมาชิกยาง">
    <title>ระบบสมาชิก – ข้อมูลยาง</title>
    <link rel="manifest" href="manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-dark:  #1b5e20;
            --green-main:  #2e7d32;
            --green-mid:   #4caf50;
            --green-light: #81c784;
            --green-pale:  #e8f5e9;
            --amber:       #f59e0b;
            --amber-pale:  #fef3c7;
            --red-pale:    #fef2f2;
            --red-main:    #dc2626;
            --surface:     #ffffff;
            --bg:          #f0f4f0;
            --text-main:   #1a2e1a;
            --text-muted:  #6b7c6b;
            --radius-lg:   1.25rem;
            --radius-md:   0.875rem;
            --radius-sm:   0.5rem;
            --shadow-sm:   0 2px 8px rgba(0,0,0,.07);
            --shadow-md:   0 4px 20px rgba(0,0,0,.10);
            --shadow-lg:   0 10px 40px rgba(0,0,0,.14);
        }

        * { -webkit-tap-highlight-color: transparent; }

        html { scroll-behavior: smooth; }

        body {
            background: var(--bg);
            min-height: 100dvh;
            font-family: 'Sarabun', sans-serif;
            font-size: 16px;
            color: var(--text-main);
            overscroll-behavior-y: contain;
        }

        /* ───── LOGIN PAGE ───── */
        .login-wrap {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(160deg, var(--green-dark) 0%, var(--green-mid) 60%, #a5d6a7 100%);
            padding: 1.5rem 1rem env(safe-area-inset-bottom, 1rem);
        }
        .login-logo {
            width: 72px; height: 72px;
            background: rgba(255,255,255,.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem;
            margin-bottom: 1.2rem;
            backdrop-filter: blur(6px);
            box-shadow: 0 4px 20px rgba(0,0,0,.2);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,.97);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 2rem 1.75rem 1.75rem;
            backdrop-filter: blur(12px);
        }
        .login-card h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--green-dark);
            margin-bottom: .25rem;
        }
        .login-card .subtitle {
            font-size: .9rem;
            color: var(--text-muted);
            margin-bottom: 1.75rem;
        }
        .field-wrap { position: relative; margin-bottom: 1.1rem; }
        .field-wrap label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--green-dark);
            text-transform: uppercase;
            letter-spacing: .04em;
            display: block;
            margin-bottom: .35rem;
        }
        .field-wrap .form-control {
            border: 1.5px solid #d4e4d4;
            border-radius: var(--radius-sm);
            padding: .8rem 1rem;
            font-size: 1.05rem;
            font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
            background: #fff;
        }
        .field-wrap .form-control:focus {
            border-color: var(--green-mid);
            box-shadow: 0 0 0 3px rgba(76,175,80,.18);
            outline: none;
        }
        .pin-input {
            letter-spacing: .5em;
            font-size: 1.6rem !important;
            text-align: center;
            font-weight: 700 !important;
            padding: .9rem 1rem !important;
        }
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--green-main), var(--green-mid));
            border: none;
            border-radius: var(--radius-sm);
            color: #fff;
            font-size: 1.05rem;
            font-weight: 600;
            padding: .9rem;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
            box-shadow: 0 4px 14px rgba(46,125,50,.35);
            font-family: inherit;
        }
        .btn-login:active { transform: scale(.98); opacity: .9; }
        .login-error {
            background: var(--red-pale);
            border: 1px solid #fecaca;
            border-radius: var(--radius-sm);
            color: var(--red-main);
            padding: .75rem 1rem;
            font-size: .9rem;
            display: flex; align-items: flex-start; gap: .5rem;
            margin-bottom: 1.1rem;
        }
        .login-home-link {
            margin-top: 1.4rem;
            text-align: center;
            font-size: .9rem;
        }
        .login-home-link a {
            color: rgba(255,255,255,.85);
            text-decoration: none;
            display: inline-flex; align-items: center; gap: .35rem;
        }
        .login-home-link a:hover { color: #fff; }

        /* ───── APP SHELL (logged-in) ───── */
        .app-wrap {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        /* Top App Bar */
        .app-bar {
            position: sticky; top: 0; z-index: 100;
            background: linear-gradient(135deg, var(--green-dark), var(--green-main));
            color: #fff;
            padding: .85rem 1rem .85rem;
            padding-top: calc(.85rem + env(safe-area-inset-top, 0px));
            box-shadow: 0 2px 12px rgba(0,0,0,.18);
        }
        .app-bar-inner {
            display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            max-width: 800px; margin: 0 auto;
        }
        .app-bar-title { font-size: 1rem; font-weight: 700; line-height: 1.2; }
        .app-bar-sub { font-size: .75rem; opacity: .8; margin-top: .1rem; }
        .btn-logout {
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.3);
            color: #fff;
            border-radius: 999px;
            padding: .35rem .85rem;
            font-size: .8rem;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            transition: background .2s;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: .3rem;
        }
        .btn-logout:hover { background: rgba(255,255,255,.28); color: #fff; }

        /* Main content */
        .app-content {
            flex: 1;
            padding: 1rem 1rem env(safe-area-inset-bottom, 1rem);
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        /* ─── Year filter chips ─── */
        .year-filter-wrap {
            display: flex;
            gap: .5rem;
            overflow-x: auto;
            padding-bottom: .5rem;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
            margin: .75rem 0;
        }
        .year-filter-wrap::-webkit-scrollbar { display: none; }
        .year-chip {
            flex-shrink: 0;
            padding: .45rem 1rem;
            border-radius: 999px;
            border: 1.5px solid #c8e6c9;
            background: #fff;
            color: var(--green-main);
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .18s;
            white-space: nowrap;
            text-decoration: none;
            font-family: inherit;
        }
        .year-chip:hover, .year-chip.active {
            background: var(--green-main);
            border-color: var(--green-main);
            color: #fff;
            box-shadow: 0 2px 8px rgba(46,125,50,.3);
        }

        /* ─── Stat cards (2-up grid on mobile) ─── */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .stat-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,.05);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px;
        }
        .stat-card.green::before { background: linear-gradient(90deg, var(--green-main), var(--green-light)); }
        .stat-card.amber::before { background: linear-gradient(90deg, var(--amber), #fcd34d); }
        .stat-card.blue::before  { background: linear-gradient(90deg, #1d4ed8, #60a5fa); }
        .stat-card .stat-label { font-size: .78rem; color: var(--text-muted); font-weight: 500; margin-bottom: .3rem; }
        .stat-card .stat-icon {
            position: absolute; top: .8rem; right: .8rem;
            font-size: 1.5rem; opacity: .12;
        }
        .stat-card .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-card .stat-value.green { color: var(--green-main); }
        .stat-card .stat-value.amber { color: #b45309; }
        .stat-card .stat-value.blue  { color: #1d4ed8; }
        .stat-card .stat-unit { font-size: .75rem; font-weight: 400; }

        /* ─── Section header ─── */
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin: 1.25rem 0 .75rem;
        }
        .section-title {
            font-size: .95rem;
            font-weight: 700;
            color: var(--green-dark);
            display: flex; align-items: center; gap: .4rem;
        }
        .section-badge {
            background: var(--green-pale);
            color: var(--green-main);
            border-radius: 999px;
            padding: .2rem .6rem;
            font-size: .75rem;
            font-weight: 600;
        }

        /* ─── Deduction summary card ─── */
        .deduct-summary {
            background: var(--surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,.05);
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .deduct-summary-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .85rem 1rem;
            cursor: pointer;
            user-select: none;
        }
        .deduct-summary-header .label {
            font-size: .9rem; font-weight: 600;
            display: flex; align-items: center; gap: .4rem;
            color: var(--green-dark);
        }
        .deduct-summary-header .chevron {
            transition: transform .25s;
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        .deduct-summary-header[aria-expanded="true"] .chevron { transform: rotate(180deg); }
        .deduct-chips {
            display: flex; flex-wrap: wrap; gap: .4rem;
            padding: 0 1rem 1rem;
        }
        .deduct-chip {
            display: inline-flex; align-items: center; gap: .25rem;
            background: var(--amber-pale);
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 999px;
            padding: .3rem .75rem;
            font-size: .8rem;
            font-weight: 600;
        }

        /* ─── Year section ─── */
        .year-section {
            background: var(--surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,.05);
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .year-section-header {
            padding: .9rem 1rem;
            background: linear-gradient(90deg, var(--green-pale), #f1f8e9);
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
            flex-wrap: wrap;
        }
        .year-pill {
            background: var(--green-main);
            color: #fff;
            border-radius: 999px;
            padding: .3rem .85rem;
            font-size: .875rem;
            font-weight: 700;
            display: inline-flex; align-items: center; gap: .35rem;
        }
        .year-meta { font-size: .8rem; color: var(--text-muted); }
        .btn-year-deduct {
            background: rgba(46,125,50,.1);
            border: 1px solid rgba(46,125,50,.25);
            color: var(--green-main);
            border-radius: var(--radius-sm);
            padding: .3rem .75rem;
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            font-family: inherit;
            display: inline-flex; align-items: center; gap: .3rem;
            transition: background .2s;
        }
        .btn-year-deduct:hover { background: rgba(46,125,50,.18); }

        /* ─── Transaction rows (mobile-card style) ─── */
        .txn-list { padding: .25rem 0; }
        .txn-item {
            border-bottom: 1px solid #f0f4f0;
        }
        .txn-item:last-child { border-bottom: none; }
        .txn-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: .35rem;
            align-items: center;
            padding: .85rem 1rem;
            cursor: pointer;
            transition: background .15s;
        }
        .txn-row:active, .txn-row.expanded { background: #f9fdf9; }
        .txn-date { font-size: .85rem; font-weight: 600; color: var(--text-main); }
        .txn-qty  { font-size: .78rem; color: var(--text-muted); margin-top: .1rem; }
        .txn-value-col { text-align: right; }
        .txn-gross { font-size: .85rem; font-weight: 600; color: #1d4ed8; }
        .txn-net   { font-size: .85rem; font-weight: 700; color: var(--green-main); }
        .txn-expend-col { text-align: right; }
        .txn-expend { font-size: .8rem; color: #b45309; font-weight: 600; }
        .txn-chevron {
            font-size: .9rem; color: var(--text-muted);
            transition: transform .22s;
        }
        .txn-row.expanded .txn-chevron { transform: rotate(180deg); }
        .txn-detail {
            padding: .75rem 1rem 1rem;
            background: #fafcfa;
            border-top: 1px dashed #e0ede0;
        }
        .txn-detail-title { font-size: .78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; margin-bottom: .5rem; }
        .txn-detail-chips { display: flex; flex-wrap: wrap; gap: .35rem; }
        .txn-detail-chip {
            background: var(--amber-pale);
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: var(--radius-sm);
            padding: .25rem .6rem;
            font-size: .78rem;
            font-weight: 600;
        }

        /* ─── Year totals footer ─── */
        .year-totals {
            background: linear-gradient(90deg, var(--green-pale), #f1f8e9);
            padding: .8rem 1rem;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .5rem;
            border-top: 2px solid #c8e6c9;
        }
        .year-total-item { text-align: center; }
        .year-total-label { font-size: .7rem; color: var(--text-muted); font-weight: 500; }
        .year-total-value { font-size: .9rem; font-weight: 700; color: var(--green-dark); }
        .year-total-value.amber { color: #b45309; }

        /* ─── Empty state ─── */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 3rem; opacity: .35; display: block; margin-bottom: .75rem; }

        /* ─── Install banner ─── */
        #pwa-install-banner {
            display: none;
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 200;
            background: var(--surface);
            border-top: 1px solid #e2e8e2;
            box-shadow: 0 -4px 20px rgba(0,0,0,.12);
            padding: 1rem 1.25rem calc(1rem + env(safe-area-inset-bottom, 0px));
        }
        #pwa-install-banner.show { display: flex; align-items: center; gap: 1rem; }
        #pwa-install-banner .banner-text { flex: 1; font-size: .875rem; }
        #pwa-install-banner .banner-text strong { display: block; color: var(--green-dark); }
        .btn-install {
            background: var(--green-main);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            padding: .55rem 1.1rem;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-dismiss {
            background: none; border: none;
            color: var(--text-muted); font-size: 1.2rem;
            cursor: pointer; padding: .25rem;
        }

        @media (min-width: 600px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
            .txn-row { grid-template-columns: 1.5fr 1fr 1fr auto; }
        }
    </style>
</head>
<body>

<?php if (!$member): ?>
<!-- ═══════════════ LOGIN ═══════════════ -->
<div class="login-wrap">
    <div class="login-logo">🌿</div>
    <div class="login-card">
        <h1>เข้าสู่ระบบสมาชิก</h1>
        <p class="subtitle">ใช้เลขสมาชิก&nbsp;และ&nbsp;รหัสบุคคล&nbsp;4&nbsp;หลัก เพื่อดูข้อมูลยางของคุณ</p>

        <?php if ($errors): ?>
            <div class="login-error">
                <i class="bi bi-exclamation-circle-fill" style="flex-shrink:0;margin-top:.1rem;"></i>
                <span><?php echo e(implode(' ', $errors)); ?></span>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="member_login">

            <div class="field-wrap">
                <label for="mem_number">เลขสมาชิก</label>
                <input type="text" id="mem_number" name="mem_number"
                       class="form-control" required maxlength="255"
                       placeholder="รหัสสมาชิก" autofocus
                       inputmode="text" autocomplete="username">
            </div>

            <div class="field-wrap">
                <label for="mem_personcode">รหัสบุคคล</label>
                <input type="password" id="mem_personcode" name="mem_personcode"
                       class="form-control pin-input" required maxlength="4" minlength="4"
                       pattern="\d{4}" inputmode="numeric" placeholder="••••"
                       autocomplete="current-password">
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i>&nbsp; เข้าสู่ระบบ
            </button>
        </form>
    </div>
    <div class="login-home-link">
        <a href="index.php"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════ APP SHELL ═══════════════ -->
<div class="app-wrap">

    <!-- App Bar -->
    <header class="app-bar">
        <div class="app-bar-inner">
            <div>
                <div class="app-bar-title">🌿 <?php echo e($member['mem_fullname']); ?></div>
                <div class="app-bar-sub">สมาชิก <?php echo e($member['mem_number']); ?> &nbsp;|&nbsp; กลุ่ม <?php echo e($member['mem_group']); ?> ชั้น <?php echo e($member['mem_class']); ?></div>
            </div>
            <a href="allmember.php?logout=1" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i> ออก
            </a>
        </div>
    </header>

    <main class="app-content">

        <?php if ($availableYears): ?>
        <!-- Year Filter Chips -->
        <div class="year-filter-wrap">
            <a href="allmember.php" class="year-chip <?php echo $selectedYear === null ? 'active' : ''; ?>">ทั้งหมด</a>
            <?php foreach ($availableYears as $yo): ?>
                <a href="allmember.php?year=<?php echo (int)$yo; ?>"
                   class="year-chip <?php echo $selectedYear === (int)$yo ? 'active' : ''; ?>">
                    พ.ศ. <?php echo (int)$yo + 543; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($summaryRows): ?>

        <!-- ── Overall stat cards ── -->
        <div class="stats-grid">
            <div class="stat-card green">
                <i class="bi bi-box-seam stat-icon"></i>
                <div class="stat-label">ปริมาณรวม</div>
                <div class="stat-value green"><?php echo number_format($overallTotals['quantity'], 2); ?> <span class="stat-unit">กก.</span></div>
            </div>
            <div class="stat-card blue">
                <i class="bi bi-cash-stack stat-icon"></i>
                <div class="stat-label">ยอดเงินรวม</div>
                <div class="stat-value blue">฿<?php echo number_format($overallTotals['value'], 2); ?></div>
            </div>
            <div class="stat-card amber">
                <i class="bi bi-arrow-down-circle stat-icon"></i>
                <div class="stat-label">ยอดหักรวม</div>
                <div class="stat-value amber">฿<?php echo number_format($overallTotals['expend'], 2); ?></div>
            </div>
            <div class="stat-card green">
                <i class="bi bi-wallet2 stat-icon"></i>
                <div class="stat-label">รับสุทธิรวม</div>
                <div class="stat-value green">฿<?php echo number_format($overallTotals['netvalue'], 2); ?></div>
            </div>
        </div>

        <!-- ── Overall deduction breakdown ── -->
        <div class="deduct-summary">
            <div class="deduct-summary-header" data-bs-toggle="collapse" data-bs-target="#overallDeduct"
                 aria-expanded="false" aria-controls="overallDeduct">
                <span class="label"><i class="bi bi-receipt-cutoff"></i> รายละเอียดยอดหักรวมทั้งหมด</span>
                <i class="bi bi-chevron-down chevron"></i>
            </div>
            <div class="collapse" id="overallDeduct">
                <div class="deduct-chips">
                    <?php foreach ($deductionLabels as $key => $label): ?>
                        <span class="deduct-chip"><i class="bi bi-tag-fill"></i> <?php echo e($label); ?>: <?php echo number_format($overallDeductionBreakdown[$key] ?? 0.0, 2); ?> ฿</span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── Per-year sections ── -->
        <?php foreach ($summaryRows as $year => $rows):
            $yearCollapseId = 'yd-' . $year;
        ?>
        <div class="year-section">
            <div class="year-section-header">
                <div>
                    <span class="year-pill"><i class="bi bi-calendar3"></i> พ.ศ. <?php echo $year + 543; ?></span>
                    <span class="year-meta ms-2"><?php echo count($rows); ?> วัน</span>
                </div>
                <button class="btn-year-deduct" type="button"
                        data-bs-toggle="collapse" data-bs-target="#<?php echo e($yearCollapseId); ?>"
                        aria-expanded="false">
                    <i class="bi bi-bar-chart-line"></i> ยอดหักต่อปี
                </button>
            </div>

            <!-- year deduction breakdown -->
            <div class="collapse" id="<?php echo e($yearCollapseId); ?>">
                <div class="deduct-chips" style="padding:.75rem 1rem;">
                    <?php foreach ($deductionLabels as $key => $label): ?>
                        <span class="deduct-chip"><?php echo e($label); ?>: <?php echo number_format($deductionTotalsByYear[$year][$key] ?? 0.0, 2); ?> ฿</span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- transaction list -->
            <div class="txn-list">
                <?php foreach ($rows as $index => $row):
                    $cid = 'txd-' . $year . '-' . $index; ?>
                <div class="txn-item">
                    <div class="txn-row" data-bs-toggle="collapse" data-bs-target="#<?php echo e($cid); ?>"
                         aria-expanded="false" aria-controls="<?php echo e($cid); ?>"
                         onclick="this.classList.toggle('expanded')">
                        <div>
                            <div class="txn-date"><i class="bi bi-calendar-event text-success me-1"></i><?php echo e(thai_date_format($row['ru_date'])); ?></div>
                            <div class="txn-qty"><i class="bi bi-box-seam me-1"></i><?php echo number_format($row['total_quantity'], 2); ?> กก.</div>
                        </div>
                        <div class="txn-value-col">
                            <div class="txn-gross">฿<?php echo number_format($row['total_value'], 2); ?></div>
                            <div class="txn-expend" style="font-size:.73rem;">-<?php echo number_format($row['total_expend'], 2); ?></div>
                        </div>
                        <div class="txn-value-col">
                            <div class="txn-net">฿<?php echo number_format($row['total_netvalue'], 2); ?></div>
                            <div style="font-size:.7rem;color:var(--text-muted);">สุทธิ</div>
                        </div>
                        <i class="bi bi-chevron-down txn-chevron"></i>
                    </div>
                    <div class="collapse" id="<?php echo e($cid); ?>">
                        <div class="txn-detail">
                            <div class="txn-detail-title"><i class="bi bi-scissors me-1"></i>รายการหัก</div>
                            <div class="txn-detail-chips">
                                <?php
                                $deductions = [
                                    'หุ้น'       => $row['total_hoon'],
                                    'เงินกู้'    => $row['total_loan'],
                                    'หนี้สั้น'   => $row['total_shortdebt'],
                                    'เงินฝาก'   => $row['total_deposit'],
                                    'กู้ซื้อขาย' => $row['total_tradeloan'],
                                    'ประกันภัย'  => $row['total_insurance'],
                                ];
                                foreach ($deductions as $lbl => $amt):
                                    if ($amt > 0):
                                ?>
                                    <span class="txn-detail-chip"><?php echo e($lbl); ?>: <?php echo number_format($amt, 2); ?> ฿</span>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- year totals footer -->
            <div class="year-totals">
                <div class="year-total-item">
                    <div class="year-total-label">ปริมาณรวม</div>
                    <div class="year-total-value"><?php echo number_format($totalsByYear[$year]['quantity'], 2); ?> กก.</div>
                </div>
                <div class="year-total-item">
                    <div class="year-total-label">ยอดหัก</div>
                    <div class="year-total-value amber">฿<?php echo number_format($totalsByYear[$year]['expend'], 2); ?></div>
                </div>
                <div class="year-total-item">
                    <div class="year-total-label">รับสุทธิ</div>
                    <div class="year-total-value">฿<?php echo number_format($totalsByYear[$year]['netvalue'], 2); ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            ยังไม่มีข้อมูลการรวบรวมยาง<?php echo $selectedYear ? ' สำหรับปีที่เลือก' : ''; ?>
            <?php if ($selectedYear): ?>
                <div class="mt-3"><a href="allmember.php" class="year-chip">ดูทั้งหมด</a></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<!-- PWA Install Banner -->
<div id="pwa-install-banner">
    <div class="banner-text">
        <strong>เพิ่มลงหน้าจอหลัก</strong>
        <span>เปิดแอปได้เร็วขึ้นโดยไม่ต้องเปิดเบราว์เซอร์</span>
    </div>
    <button class="btn-install" id="btn-pwa-install">ติดตั้ง</button>
    <button class="btn-dismiss" id="btn-pwa-dismiss" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
<script>
(function () {
    /* Service Worker */
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(() => {});
    }

    /* Toggle txn chevron via Bootstrap collapse events */
    document.querySelectorAll('.txn-row').forEach(function(row) {
        var targetId = row.getAttribute('data-bs-target');
        if (!targetId) return;
        var panel = document.querySelector(targetId);
        if (!panel) return;
        panel.addEventListener('show.bs.collapse', function () { row.classList.add('expanded'); });
        panel.addEventListener('hide.bs.collapse', function () { row.classList.remove('expanded'); });
    });

    /* deduct-summary-header chevron */
    document.querySelectorAll('.deduct-summary-header').forEach(function(hdr) {
        var targetId = hdr.getAttribute('data-bs-target');
        if (!targetId) return;
        var panel = document.querySelector(targetId);
        if (!panel) return;
        panel.addEventListener('show.bs.collapse', function () { hdr.setAttribute('aria-expanded', 'true'); });
        panel.addEventListener('hide.bs.collapse', function () { hdr.setAttribute('aria-expanded', 'false'); });
    });

    /* PWA Install prompt */
    var deferredPrompt = null;
    var banner = document.getElementById('pwa-install-banner');
    var btnInstall = document.getElementById('btn-pwa-install');
    var btnDismiss = document.getElementById('btn-pwa-dismiss');

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        if (banner && !sessionStorage.getItem('pwa-dismissed')) {
            banner.classList.add('show');
        }
    });

    if (btnInstall) {
        btnInstall.addEventListener('click', function () {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function () {
                    deferredPrompt = null;
                    banner.classList.remove('show');
                });
            }
        });
    }

    if (btnDismiss) {
        btnDismiss.addEventListener('click', function () {
            banner.classList.remove('show');
            sessionStorage.setItem('pwa-dismissed', '1');
        });
    }

    window.addEventListener('appinstalled', function () {
        if (banner) banner.classList.remove('show');
    });
})();
</script>
</body>
</html>

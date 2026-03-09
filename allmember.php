<?php
require_once __DIR__ . '/functions.php';

db(); // ensure connection & session

const MEMBER_SESSION_KEY = 'member_portal';

function normalize_birth_digits(?string $value): string {
    if ($value === null) {
        return '';
    }
    return preg_replace('/\D+/', '', (string)$value);
}

function ensure_ddmmyyyy(string $digits): string {
    if (strlen($digits) !== 8) {
        return $digits;
    }

    $dd = (int)substr($digits, 0, 2);
    $mm = (int)substr($digits, 2, 2);
    $yyyy = (int)substr($digits, 4, 4);
    if ($dd >= 1 && $dd <= 31 && $mm >= 1 && $mm <= 12) {
        return sprintf('%02d%02d%04d', $dd, $mm, $yyyy);
    }

    $yyyy = (int)substr($digits, 0, 4);
    $mm = (int)substr($digits, 4, 2);
    $dd = (int)substr($digits, 6, 2);
    if ($dd >= 1 && $dd <= 31 && $mm >= 1 && $mm <= 12) {
        return sprintf('%02d%02d%04d', $dd, $mm, $yyyy);
    }

    return $digits;
}

function format_ddmmyyyy_display(string $digits): string {
    $digits = ensure_ddmmyyyy($digits);
    if (strlen($digits) === 8) {
        return sprintf('%s/%s/%s', substr($digits, 0, 2), substr($digits, 2, 2), substr($digits, 4, 4));
    }
    return $digits;
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
        $birthInputDigits = normalize_birth_digits($_POST['mem_birthtext'] ?? '');
        if ($memNumber === '' || $birthInputDigits === '') {
            $errors[] = 'กรุณากรอกเลขสมาชิกและวันเดือนปีเกิด';
        } elseif (strlen($birthInputDigits) !== 8) {
            $errors[] = 'วันเดือนปีเกิดต้องเป็นตัวเลข 8 หลัก รูปแบบ DDMMYYYY';
        } else {
            $day = (int)substr($birthInputDigits, 0, 2);
            $month = (int)substr($birthInputDigits, 2, 2);
            $year = (int)substr($birthInputDigits, 4, 4);
            if (!checkdate($month, $day, $year)) {
                $errors[] = 'วันเดือนปีเกิดไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง (DDMMYYYY)';
            } else {
                $db = db();
                $stm = $db->prepare('SELECT mem_id, mem_fullname, mem_number, mem_birthtext, mem_group, mem_class FROM tbl_member WHERE mem_number = ? LIMIT 1');
                if ($stm) {
                    $stm->bind_param('s', $memNumber);
                    $stm->execute();
                    $res = $stm->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $stm->close();

                    if ($row) {
                        $storedDigits = ensure_ddmmyyyy(normalize_birth_digits($row['mem_birthtext'] ?? ''));
                        if ($storedDigits !== '' && $storedDigits === ensure_ddmmyyyy($birthInputDigits)) {
                            $_SESSION[MEMBER_SESSION_KEY] = [
                                'mem_id' => (int)$row['mem_id'],
                                'mem_number' => $row['mem_number'],
                                'mem_fullname' => $row['mem_fullname'],
                                'mem_group' => $row['mem_group'],
                                'mem_class' => $row['mem_class'],
                                'mem_birthtext' => $storedDigits,
                            ];
                            header('Location: allmember.php');
                            exit;
                        }
                        $errors[] = 'วันเดือนปีเกิดไม่ถูกต้อง';
                    } else {
                        $errors[] = 'ไม่พบข้อมูลเลขสมาชิกนี้';
                    }
                } else {
                    $errors[] = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . db()->error;
                }
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
                   ru_lan,
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
    $sql .= ' GROUP BY YEAR(ru_date), ru_date, ru_lan ORDER BY year DESC, ru_date DESC, ru_lan ASC';

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
                'ru_lan' => $row['ru_lan'],
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Rubber Data Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Sarabun', sans-serif;
        }
        .portal-card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(15, 68, 45, 0.08);
            border: 1px solid rgba(15, 68, 45, 0.08);
        }
        .portal-header {
            background: linear-gradient(135deg, #81c784, #4caf50);
            color: #ffffff;
            border-radius: 1rem 1rem 0 0;
            padding: 2rem;
        }
        .summary-card {
            border-radius: 1rem;
            background: #ffffff;
            border: 1px solid rgba(76, 175, 80, 0.15);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.08);
        }
        .year-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            background: rgba(76, 175, 80, 0.12);
            color: #2e7d32;
            font-weight: 600;
        }
        .table thead {
            background: rgba(76, 175, 80, 0.08);
            color: #1b5e20;
        }
        .login-card {
            max-width: 480px;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: 0 10px 25px rgba(15, 68, 45, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.15);
        }
        .deduction-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            background: rgba(255, 193, 7, 0.18);
            color: #8a6d1d;
            border: 1px solid rgba(255, 193, 7, 0.3);
            font-weight: 600;
        }
        .collapse-card-button {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-11">
                <?php if (!$member): ?>
                    <div class="login-card">
                        <h2 class="text-center mb-3">เข้าสู่ระบบสมาชิก</h2>
                        <p class="text-center text-muted mb-4">ใช้เลขสมาชิกและวันเดือนปีเกิด (DDMMYYYY)เพื่อดูสรุปการรวบรวมยางของคุณ</p>
                        <?php if ($errors): ?>
                            <div class="alert alert-warning" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e(implode(' | ', $errors)); ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                            <input type="hidden" name="action" value="member_login">
                            <div class="mb-3">
                                <label class="form-label">เลขสมาชิก (Username)</label>
                                <input type="text" name="mem_number" class="form-control" required maxlength="255" placeholder="ใส่รหัสสมาชิก" autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">วันเดือนปีเกิด (Password) <span class="text-muted">รูปแบบ DDMMYYYY</span></label>
                                <input type="text" name="mem_birthtext" class="form-control" required maxlength="8" minlength="8" pattern="\d{8}" inputmode="numeric" placeholder="เช่น 15021990">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">เข้าสู่ระบบ</button>
                            </div>
                        </form>
                        <div class="text-end mt-3">
                            <!-- add icon home -->
                            <a href="index.php" class="text-decoration-none"><i class="bi bi-house-door"></i> กลับไปหน้าหลัก</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="portal-card mb-4">
                        <div class="portal-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                            <div>
                                <h1 class="h3 mb-1">สรุปการรวบรวมยางของสมาชิก</h1>
                                <div class="fs-5">คุณ <?php echo e($member['mem_fullname']); ?> (เลขสมาชิก <?php echo e($member['mem_number']); ?>)</div>
                                <div class="text-white-50">กลุ่ม: <?php echo e($member['mem_group']); ?> | ชั้น: <?php echo e($member['mem_class']); ?></div>
                            </div>
                            <div class="text-md-end">
                                <div class="small">วันเดือนปีเกิดในระบบ: <?php echo e(format_ddmmyyyy_display($member['mem_birthtext'] ?? '')); ?></div>
                                <a href="allmember.php?logout=1" class="btn btn-outline-light btn-sm mt-2">ออกจากระบบ</a>
                            </div>
                        </div>
                        <div class="p-4">
                            <?php if ($availableYears): ?>
                                <form class="row g-3 align-items-end" method="get">
                                    <div class="col-md-4 col-lg-3">
                                        <label class="form-label">เลือกปีที่ต้องการดู</label>
                                        <select name="year" class="form-select" onchange="this.form.submit()">
                                            <option value="">ทั้งหมด</option>
                                            <?php foreach ($availableYears as $yearOption): ?>
                                                <option value="<?php echo (int)$yearOption; ?>" <?php echo ($selectedYear === (int)$yearOption) ? 'selected' : ''; ?>><?php echo (int)$yearOption + 543; ?> (พ.ศ.)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-auto">
                                        <button type="submit" class="btn btn-success">แสดงข้อมูล</button>
                                        <?php if ($selectedYear !== null): ?>
                                            <a href="allmember.php" class="btn btn-outline-secondary ms-2">ล้างตัวกรอง</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">ยังไม่มีข้อมูลการรวบรวมยางสำหรับเลขสมาชิกนี้</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($summaryRows): ?>
                        <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-lg-4 mb-4">
                            <div class="col">
                                <div class="summary-card p-3 h-100">
                                    <div class="text-muted">ปริมาณรวม</div>
                                    <div class="display-6 fw-bold text-success"><?php echo number_format($overallTotals['quantity'], 2); ?> <small class="fs-5">กก.</small></div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="summary-card p-3 h-100">
                                    <div class="text-muted">ยอดเงินรวม</div>
                                    <div class="display-6 fw-bold text-success">฿<?php echo number_format($overallTotals['value'], 2); ?></div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="summary-card p-3 h-100">
                                    <div class="text-muted">ยอดหักรวม</div>
                                    <div class="display-6 fw-bold text-warning">฿<?php echo number_format($overallTotals['expend'], 2); ?></div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="summary-card p-3 h-100">
                                    <div class="text-muted">รับสุทธิรวม</div>
                                    <div class="display-6 fw-bold text-success">฿<?php echo number_format($overallTotals['netvalue'], 2); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <button class="btn btn-outline-secondary collapse-card-button" type="button" data-bs-toggle="collapse" data-bs-target="#overallDeduction" aria-expanded="false" aria-controls="overallDeduction">
                                <i class="bi bi-list-ul me-1"></i>ดูรายละเอียดยอดหักรวมทั้งหมด
                            </button>
                            <div class="collapse mt-3" id="overallDeduction">
                                <div class="card card-body">
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($deductionLabels as $key => $label): ?>
                                            <span class="deduction-badge"><?php echo e($label); ?>: <?php echo number_format($overallDeductionBreakdown[$key] ?? 0.0, 2); ?> ฿</span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php foreach ($summaryRows as $year => $rows): ?>
                            <?php $yearCollapseId = 'year-deduct-' . $year; ?>
                            <div class="card mb-4">
                                <div class="card-header bg-white border-0">
                                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                        <span class="year-badge"><i class="bi bi-calendar3"></i> ปี <?php echo $year + 543; ?> (พ.ศ.)</span>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted">จำนวนรอบทั้งหมด <?php echo count($rows); ?> รอบ</span>
                                            <button class="btn btn-outline-secondary btn-sm collapse-card-button" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo e($yearCollapseId); ?>" aria-expanded="false" aria-controls="<?php echo e($yearCollapseId); ?>">
                                                <i class="bi bi-list-ul me-1"></i>ดูยอดหักรวมต่อปี
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="collapse" id="<?php echo e($yearCollapseId); ?>">
                                    <div class="px-4 pb-3">
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($deductionLabels as $key => $label): ?>
                                                <span class="deduction-badge"><?php echo e($label); ?>: <?php echo number_format($deductionTotalsByYear[$year][$key] ?? 0.0, 2); ?> ฿</span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th scope="col">รอบวันที่</th>
                                                    <!-- <th scope="col">ช่องทาง (ลาน)</th> -->
                                                    <th scope="col" class="text-end">ปริมาณรวม (กก.)</th>
                                                    <th scope="col" class="text-end">ยอดเงินรวม (บาท)</th>
                                                    <th scope="col" class="text-end">ยอดหักรวม (บาท)</th>
                                                    <th scope="col" class="text-end">รับสุทธิ (บาท)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rows as $index => $row): ?>
                                                    <?php $collapseId = 'deduct-' . $year . '-' . $index; ?>
                                                    <tr>
                                                        <td><?php echo e(thai_date_format($row['ru_date'])); ?></td>
                                                        <!-- <td><?php // echo e($row['ru_lan']); ?></td> -->
                                                        <td class="text-end"><?php echo number_format($row['total_quantity'], 2); ?></td>
                                                        <td class="text-end"><?php echo number_format($row['total_value'], 2); ?></td>
                                                        <td class="text-end">
                                                            <div class="text-end">
                                                                <?php echo number_format($row['total_expend'], 2); ?>
                                                                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo e($collapseId); ?>" aria-expanded="false" aria-controls="<?php echo e($collapseId); ?>" title="ดูรายละเอียดการหัก">
                                                                    <i class="bi bi-list-ul"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td class="text-end"><?php echo number_format($row['total_netvalue'], 2); ?></td>
                                                    </tr>
                                                    <tr class="collapse bg-light" id="<?php echo e($collapseId); ?>">
                                                        <td colspan="6">
                                                            <div class="p-3">
                                                                <div class="fw-semibold mb-2">รายละเอียดยอดหัก</div>
                                                                <?php
                                                                    $deductions = [
                                                                        'หุ้น' => $row['total_hoon'],
                                                                        'เงินกู้' => $row['total_loan'],
                                                                        'หนี้สั้น' => $row['total_shortdebt'],
                                                                        'เงินฝาก' => $row['total_deposit'],
                                                                        'กู้ซื้อขาย' => $row['total_tradeloan'],
                                                                        'ประกันภัย' => $row['total_insurance'],
                                                                    ];
                                                                ?>
                                                                <div class="d-flex flex-wrap gap-2">
                                                                    <?php foreach ($deductions as $label => $amount): ?>
                                                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                                            <?php echo e($label); ?>: <?php echo number_format($amount, 2); ?> ฿
                                                                        </span>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <th class="text-end">รวมทั้งปี</th>
                                                    <th class="text-end"><?php echo number_format($totalsByYear[$year]['quantity'], 2); ?></th>
                                                    <th class="text-end"><?php echo number_format($totalsByYear[$year]['value'], 2); ?></th>
                                                    <th class="text-end"><?php echo number_format($totalsByYear[$year]['expend'], 2); ?></th>
                                                    <th class="text-end"><?php echo number_format($totalsByYear[$year]['netvalue'], 2); ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">ยังไม่มีข้อมูลการรวบรวมยางสำหรับเกณฑ์ที่เลือก</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>

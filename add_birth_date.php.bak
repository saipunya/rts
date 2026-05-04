<?php
require_once __DIR__ . '/functions.php';
require_login();

$mysqli = db();

function format_birthtext(?string $text): string {
    if (empty($text)) {
        return '-';
    }
    $digits = preg_replace('/\D+/', '', (string)$text);
    if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $digits, $m)) {
        return sprintf('%s/%s/%s', $m[1], $m[2], $m[3]);
    }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $digits, $m)) {
        return sprintf('%s/%s/%s', $m[3], $m[2], $m[1]);
    }
    return $text;
}

$msg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$error = '';
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$members = [];
$memberEdit = null;
$newBirthtext = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_check($token)) {
        $error = 'ไม่สามารถยืนยันความถูกต้องของคำขอได้ กรุณาลองใหม่อีกครั้ง';
    } else {
        $editId = (int)($_POST['mem_id'] ?? 0);
        $newBirthtext = isset($_POST['mem_birthtext']) ? preg_replace('/\D+/', '', (string)$_POST['mem_birthtext']) : '';
        $redirectQ = isset($_POST['redirect_q']) ? trim((string)$_POST['redirect_q']) : '';
        if ($editId <= 0) {
            $error = 'ไม่พบรหัสสมาชิกที่ต้องการแก้ไข';
        } elseif ($newBirthtext === '') {
            $error = 'กรุณากรอกวันเกิดของสมาชิก';
        } elseif (strlen($newBirthtext) !== 8) {
            $error = 'รูปแบบวันเกิดต้องเป็นตัวเลข 8 หลัก (DDMMYYYY)';
        } else {
            $day = substr($newBirthtext, 0, 2);
            $month = substr($newBirthtext, 2, 2);
            $year = substr($newBirthtext, 4, 4);
            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                $error = 'วันเกิดไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง (DDMMYYYY)';
            } else {
                $newBirthtext = sprintf('%02d%02d%04d', (int)$day, (int)$month, (int)$year);
            }
        }

        if ($error === '') {
            $saveby = $_SESSION['user_fullname'] ?? $_SESSION['user_username'] ?? 'system';
            $savedate = date('Y-m-d');
            $stmt = $mysqli->prepare('UPDATE tbl_member SET mem_birthtext = ?, mem_saveby = ?, mem_savedate = ? WHERE mem_id = ?');
            if (!$stmt) {
                $error = 'ไม่สามารถเตรียมคำสั่งบันทึกได้: ' . $mysqli->error;
            } else {
                $stmt->bind_param('sssi', $newBirthtext, $saveby, $savedate, $editId);
                if ($stmt->execute()) {
                    $stmt->close();
                    $params = ['msg' => 'บันทึกวันเกิดสมาชิกเรียบร้อยแล้ว'];
                    if ($redirectQ !== '') {
                        $params['q'] = $redirectQ;
                    }
                    $params['edit'] = $editId;
                    $queryString = http_build_query($params);
                    header('Location: add_birth_date.php?' . $queryString . '#editForm');
                    exit;
                }
                $error = 'ไม่สามารถบันทึกข้อมูลได้: ' . $stmt->error;
                $stmt->close();
            }
        }
        $q = $redirectQ !== '' ? $redirectQ : $q;
    }
}

if ($q !== '' && mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';
    $stmt = $mysqli->prepare('SELECT mem_id, mem_group, mem_number, mem_fullname, mem_class, mem_birthtext, mem_saveby, mem_savedate
                               FROM tbl_member
                               WHERE mem_fullname LIKE ? OR mem_number LIKE ? OR mem_group LIKE ? OR mem_class LIKE ?
                               ORDER BY mem_fullname ASC
                               LIMIT 50');
    if ($stmt) {
        $stmt->bind_param('ssss', $like, $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = $result->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
    }
} elseif ($q !== '') {
    $error = $error !== '' ? $error : 'กรุณากรอกคำค้นหาอย่างน้อย 2 ตัวอักษร';
}

if ($editId > 0) {
    $stmt = $mysqli->prepare('SELECT mem_id, mem_group, mem_number, mem_fullname, mem_class, mem_birthtext FROM tbl_member WHERE mem_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $res = $stmt->get_result();
        $memberEdit = $res->fetch_assoc() ?: null;
        $stmt->close();
        if ($memberEdit && $newBirthtext !== null) {
            $memberEdit['mem_birthtext'] = $newBirthtext;
        }
    }
}

include __DIR__ . '/header.php';
?>
<main>
    <div class="app-shell">
        <div class="page-hero" data-aos="fade-up">
            <div class="page-hero-inner">
                <h1 class="mb-1">จัดการวันเกิดสมาชิก</h1>
                <h5 class="text-muted">ค้นหาและบันทึกข้อมูลวันเกิดสำหรับสมาชิกสหกรณ์</h5>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <span class="badge bg-success-subtle text-success border border-success-subtle">ฐานข้อมูล: tbl_member</span>
                    <span class="badge bg-light text-muted">ฟิลด์วันเกิด: mem_birthtext (รูปแบบ DDMMYYYY)</span>
                </div>
            </div>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-success" role="alert" data-aos="zoom-in">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo e($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-warning" role="alert" data-aos="zoom-in">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <div class="content-card" data-aos="fade-up">
            <form class="row g-3 align-items-end" method="get" action="add_birth_date.php">
                <div class="col-md-6">
                    <label class="form-label" for="q">ค้นหาสมาชิก</label>
                    <input type="text" name="q" id="q" class="form-control" placeholder="พิมพ์ชื่อ, เลขสมาชิก, กลุ่ม หรือชั้น" value="<?php echo e($q); ?>">
                    <div class="form-text">ต้องการค้นหาอย่างน้อย 2 ตัวอักษร</div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> ค้นหา</button>
                    <?php if ($q !== ''): ?>
                        <a href="add_birth_date.php" class="btn btn-outline-secondary ms-2">ล้างการค้นหา</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="content-card" data-aos="fade-up">
            <h5 class="section-title"><i class="bi bi-people"></i> รายชื่อสมาชิก</h5>
            <?php if ($q === '' || mb_strlen($q) < 2): ?>
                <p class="text-muted mb-0">กรุณาค้นหาสมาชิกก่อน เพื่อแสดงข้อมูลวันเกิด</p>
            <?php elseif (empty($members)): ?>
                <p class="text-muted mb-0">ไม่พบข้อมูลสมาชิกที่ตรงกับ "<?php echo e($q); ?>"</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">รหัส</th>
                                <th scope="col">ชื่อ-สกุล</th>
                                <th scope="col">กลุ่ม</th>
                                <th scope="col">เลขสมาชิก</th>
                                <th scope="col">ชั้น</th>
                                <th scope="col">วันเกิด (mem_birthtext)</th>
                                <th scope="col">บันทึกล่าสุด</th>
                                <th scope="col" class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr id="member-<?php echo (int)$member['mem_id']; ?>">
                                    <td><?php echo (int)$member['mem_id']; ?></td>
                                    <td><?php echo e($member['mem_fullname']); ?></td>
                                    <td><?php echo e($member['mem_group']); ?></td>
                                    <td><?php echo e($member['mem_number']); ?></td>
                                    <td><?php echo e($member['mem_class']); ?></td>
                                    <td>
                                        <?php
                                            $formatted = format_birthtext($member['mem_birthtext'] ?? '');
                                            echo e($formatted);
                                        ?>
                                    </td>
                                    <td>
                                        <div class="small text-muted">
                                            <?php if (!empty($member['mem_saveby'])): ?>บันทึกโดย: <?php echo e($member['mem_saveby']); ?><br><?php endif; ?>
                                            <?php if (!empty($member['mem_savedate'])): ?>เมื่อ: <?php echo e(thai_date_format($member['mem_savedate'])); ?><?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $linkParams = ['edit' => (int)$member['mem_id']];
                                            if ($q !== '') {
                                                $linkParams['q'] = $q;
                                            }
                                            $editLink = 'add_birth_date.php?' . http_build_query($linkParams) . '#editForm';
                                        ?>
                                        <a href="<?php echo e($editLink); ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square me-1"></i> แก้ไข</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($memberEdit): ?>
            <div class="content-card" id="editForm" data-aos="fade-up">
                <h5 class="section-title"><i class="bi bi-pencil"></i> แก้ไขวันเกิดสำหรับสมาชิก</h5>
                <form method="post" action="add_birth_date.php" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="mem_id" value="<?php echo (int)$memberEdit['mem_id']; ?>">
                    <input type="hidden" name="redirect_q" value="<?php echo e($q); ?>">

                    <div class="col-md-6">
                        <label class="form-label">ชื่อ-สกุลสมาชิก</label>
                        <input type="text" class="form-control" value="<?php echo e($memberEdit['mem_fullname']); ?>" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">กลุ่ม</label>
                        <input type="text" class="form-control" value="<?php echo e($memberEdit['mem_group']); ?>" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">เลขสมาชิก</label>
                        <input type="text" class="form-control" value="<?php echo e($memberEdit['mem_number']); ?>" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ชั้น</label>
                        <input type="text" class="form-control" value="<?php echo e($memberEdit['mem_class']); ?>" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="mem_birthtext">วันเกิด (DDMMYYYY)</label>
                        <input type="text" name="mem_birthtext" id="mem_birthtext" class="form-control" maxlength="8" minlength="8" pattern="\d{8}" inputmode="numeric" placeholder="เช่น 15021990" value="<?php echo e($memberEdit['mem_birthtext'] ?? ''); ?>" required>
                        <div class="form-text">กรอกเป็นตัวเลข 8 หลักตามรูปแบบ วัน-เดือน-ปี (ค.ศ.)</div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i> บันทึกข้อมูล</button>
                        <?php
                            $cancelParams = [];
                            if ($q !== '') {
                                $cancelParams['q'] = $q;
                            }
                            $cancelLink = empty($cancelParams) ? 'add_birth_date.php' : 'add_birth_date.php?' . http_build_query($cancelParams);
                        ?>
                        <a href="<?php echo e($cancelLink); ?>" class="btn btn-outline-secondary">ยกเลิก</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/footer.php';

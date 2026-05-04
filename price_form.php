<?php
// filepath: /Users/sumet/Desktop/rts/price_form.php
require_once 'functions.php';
require_admin();
include 'header.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'create';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// default values
$pr = [
    'pr_id' => 0,
    'pr_year' => date('Y') + 543, // Thai year default
    'pr_date' => date('Y-m-d'),
    'pr_number' => '',
    'pr_price' => '0.00',
];

if ($action === 'edit' && $id > 0) {
    $stmt = $mysqli->prepare("SELECT pr_id, pr_year, pr_date, pr_number, pr_price FROM tbl_price WHERE pr_id = ? LIMIT 1");
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if ($row) {
        $pr = $row;
    } else {
        header('Location: prices.php?msg=' . urlencode('ไม่พบข้อมูล')); exit;
    }
}

?>
<!-- Removed Google Fonts, now using local Sarabun -->
<style>
html, body {
    font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    background: #eff7f1;
    color: #14532d;
}

.price-form-shell {
    max-width: 960px;
}

.price-hero,
.price-panel {
    border: 1px solid #bbf7d0;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 16px 42px rgba(20, 83, 45, 0.08);
}

.price-hero {
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.98), rgba(236, 253, 245, 0.95));
}

.price-badge,
.price-mini {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.price-badge {
    width: 3rem;
    height: 3rem;
    border-radius: 1rem;
    background: #16a34a;
    color: #fff;
    box-shadow: 0 10px 24px rgba(22, 163, 74, 0.24);
}

.price-mini {
    width: 2.35rem;
    color: #166534;
}

.form-label {
    font-weight: 700;
    color: #166534;
}

.form-control,
.form-select {
    min-height: 46px;
    border-radius: .9rem;
    border-color: #bbf7d0;
}

.form-control:focus,
.form-select:focus {
    border-color: #22c55e;
    box-shadow: 0 0 0 .2rem rgba(34, 197, 94, .14);
}

.btn {
    min-height: 44px;
    border-radius: 999px;
    font-weight: 700;
}

@media (max-width: 576px) {
    .price-hero,
    .price-panel {
        border-radius: 1rem;
    }
}
</style>
<div class="container price-form-shell my-4">
    <section class="price-hero p-3 p-md-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="price-badge">
                    <i data-lucide="coins" aria-hidden="true"></i>
                </span>
                <div>
                    <div class="text-uppercase text-success fw-semibold small mb-1">Price</div>
                    <h1 class="h3 fw-bold mb-1 text-success-emphasis"><?php echo ($action === 'edit') ? 'แก้ไขราคายาง' : 'เพิ่มราคายาง'; ?></h1>
                    <div class="text-success">บันทึกราคายางและรอบการประกาศราคา</div>
                </div>
            </div>
            <a href="prices.php" class="btn btn-outline-success">
                <i data-lucide="arrow-left" class="me-1" aria-hidden="true"></i>กลับหน้าราคา
            </a>
        </div>
    </section>

    <section class="price-panel p-3 p-md-4">
        <form method="post" action="price_save.php">
            <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
            <input type="hidden" name="pr_id" value="<?php echo (int)$pr['pr_id']; ?>">

            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="pr_year">
                        <i data-lucide="calendar-range" class="me-1" aria-hidden="true"></i>ปี (พ.ศ.)
                    </label>
                    <input type="number" name="pr_year" id="pr_year" class="form-control" value="<?php echo htmlspecialchars($pr['pr_year']); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="pr_date">
                        <i data-lucide="calendar-days" class="me-1" aria-hidden="true"></i>วันที่
                    </label>
                    <input type="date" name="pr_date" id="pr_date" class="form-control" value="<?php echo htmlspecialchars($pr['pr_date']); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="pr_number">
                        <i data-lucide="hash" class="me-1" aria-hidden="true"></i>รอบ
                    </label>
                    <input type="text" name="pr_number" id="pr_number" class="form-control" value="<?php echo htmlspecialchars($pr['pr_number']); ?>" placeholder="เช่น รอบที่ 1" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="pr_price">
                        <i data-lucide="circle-dollar-sign" class="me-1" aria-hidden="true"></i>ราคา
                    </label>
                    <input type="text" name="pr_price" id="pr_price" class="form-control" value="<?php echo htmlspecialchars($pr['pr_price']); ?>" required>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mt-4">
                <a href="prices.php" class="btn btn-outline-secondary">
                    <i data-lucide="x" class="me-1" aria-hidden="true"></i>ยกเลิก
                </a>
                <button type="submit" class="btn btn-success">
                    <i data-lucide="save" class="me-1" aria-hidden="true"></i><?php echo ($action === 'edit') ? 'บันทึกการแก้ไข' : 'บันทึก'; ?>
                </button>
            </div>
        </form>
    </section>
</div>

<?php include 'footer.php'; ?>

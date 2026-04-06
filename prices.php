<?php
// filepath: /Users/sumetmac/Desktop/GitHub/rts-1/prices.php
require_once 'functions.php';
// require_admin();
include 'header.php';

$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// fetch prices
$stmt = $mysqli->prepare("SELECT pr_id, pr_year, pr_date, pr_number, pr_price, pr_saveby, pr_savedate FROM tbl_price ORDER BY pr_date DESC, pr_id DESC");
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->execute();
$result = $stmt->get_result();
$prices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!-- Removed Google Fonts, now using local Sarabun -->
<style>
/* Match rubbers.php baseline scale */
html, body {
  font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  font-size: 16px;
  font-weight: 300;
}
.container,
.card,
.table,
.form-control,
.form-select,
.form-label,
.btn,
.btn-sm,
.nav-link,
.alert,
.badge {
  font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  font-size: 16px;
  font-weight: 300;
}
.small, .form-text {
  font-size: 14px !important;
  font-weight: 300 !important;
}

/* Enhanced Responsive Design */
@media (max-width: 992px) {
  .container.mt-4 {
    padding: 0 1rem;
  }
  
  h3 {
    font-size: 1.5rem;
  }
}

@media (max-width: 768px) {
  .container.mt-4 {
    padding: 0 0.5rem;
    margin-top: 1rem !important;
  }
  
  .row.mb-3 {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch !important;
    text-align: center;
  }
  
  .col-6 {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  h3 {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
  }
  
  .col-6.text-end {
    text-align: center !important;
  }
  
  .col-6.text-end .btn {
    display: block;
    width: 100%;
    margin-bottom: 0.5rem;
    min-height: 44px;
  }
  
  .row.mb-3 {
    margin-bottom: 1rem !important;
  }
  
  .table-responsive {
    font-size: 0.85rem;
    margin: 0 -0.5rem;
    padding: 0 0.5rem;
  }
  
  .table th,
  .table td {
    padding: 0.5rem 0.3rem;
  }
  
  .table .btn-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.4rem;
    min-height: auto;
  }
  
  .alert {
    font-size: 0.9rem;
    padding: 0.75rem 1rem;
    margin: 0 -0.5rem 1rem;
  }
  
  .card {
    margin-bottom: 1rem;
  }
  
  .card-body {
    padding: 1rem;
  }
}

@media (max-width: 576px) {
  .container.mt-4 {
    padding: 0 0.25rem;
  }
  
  h3 {
    font-size: 1.2rem;
  }
  
  .table-responsive {
    font-size: 0.8rem;
    padding: 0 0.25rem;
  }
  
  .table th,
  .table td {
    padding: 0.4rem 0.2rem;
  }
  
  .table th:not(:first-child):not(:nth-child(2)):not(:nth-child(4)),
  .table td:not(:first-child):not(:nth-child(2)):not(:nth-child(4)) {
    display: none;
  }
  
  .table .btn-sm {
    font-size: 0.7rem;
    padding: 0.2rem 0.3rem;
  }
  
  .card-body {
    padding: 0.75rem;
  }
}

/* Landscape orientation */
@media (max-width: 768px) and (orientation: landscape) {
  .container.mt-4 {
    margin-top: 0.5rem !important;
  }
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {
  .table-hover tbody tr:hover td {
    background-color: transparent;
  }
  
  .btn,
  .form-control {
    min-height: 44px;
  }
}
</style>
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-6">
            <h3><i class="bi bi-cash-coin me-2"></i>ราคายาง (Price list)</h3>
        </div>
        <div class="col-6 text-end">
            <?php if (function_exists('is_admin') && is_admin()): ?>
                <a href="price_form.php?action=create" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>เพิ่มราคายาง</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="card card-table">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-striped table-sm datatable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><i class="bi bi-calendar2-week me-1"></i>ปี</th>
                            <th><i class="bi bi-calendar-date me-1"></i>วันที่</th>
                            <th><i class="bi bi-clock-history me-1"></i>รอบ</th>
                            <th><i class="bi bi-cash-stack me-1"></i>ราคา</th>
                         <!-- admin เท่่านั้นที่เห็น -->
                            <?php if (function_exists('is_admin') && is_admin()): ?>
                                <th class="no-sort"><i class="bi bi-gear me-1"></i>จัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prices as $p): ?>
                            <tr>
                                <td><?php echo (int)$p['pr_id']; ?></td>
                                <td><?php echo (int)$p['pr_year']; ?></td>
                                <td><i class="bi bi-calendar-date me-1 text-secondary"></i><?php echo thai_date_format($p['pr_date']); ?></td>
                                <td><i class="bi bi-clock-history me-1 text-secondary"></i><?php echo htmlspecialchars($p['pr_number']); ?></td>
                                <td><i class="bi bi-cash-stack me-1 text-success"></i><?php echo number_format((float)$p['pr_price'], 2); ?></td>
                                
                                <?php if (function_exists('is_admin') && is_admin()): ?>
                                    <td>
                                        <a href="price_form.php?action=edit&id=<?php echo (int)$p['pr_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="price_delete.php" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('ลบราคานี้หรือไม่?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$p['pr_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

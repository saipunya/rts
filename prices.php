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
html, body {
  font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", sans-serif;
  font-size: 16px;
  font-weight: 300;
  background: #f6f8fb;
}

.price-page {
  max-width: 1180px;
}

.price-hero {
  border: 1px solid #e5e7eb;
  background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
  border-radius: 1.25rem;
  padding: 1.25rem;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.price-title {
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.price-subtitle {
  color: #64748b;
  margin: .25rem 0 0;
}

.price-card {
  border: 1px solid #e5e7eb;
  border-radius: 1.25rem;
  overflow: hidden;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.price-table {
  margin-bottom: 0;
  vertical-align: middle;
}

.price-table thead th {
  background: #f8fafc;
  color: #334155;
  font-weight: 700;
  white-space: nowrap;
  padding: .9rem .85rem;
  border-bottom: 1px solid #e5e7eb;
}

.price-table tbody td {
  padding: .85rem;
  color: #334155;
}

.price-table tbody tr:hover td {
  background: #f1f5f9;
  transition: background-color 0.3s ease;
}

.price-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  border-radius: 999px;
  padding: .35rem .7rem;
  background: #ecfdf5;
  color: #047857;
  font-weight: 700;
}

.action-group {
  display: inline-flex;
  gap: .4rem;
  align-items: center;
}

.action-group form {
  margin: 0;
}

.action-group .btn {
  padding: 0.5rem 0.75rem;
  font-size: 0.9rem;
  transition: background-color 0.3s ease, transform 0.2s ease;
}

.action-group .btn:hover {
  background-color: #e2e8f0;
  transform: scale(1.05);
}

@media (max-width: 768px) {
  .container.price-page {
    width: 100%;
    max-width: 100%;
    padding-left: 0.85rem;
    padding-right: 0.85rem;
    margin-top: 1rem !important;
  }

  .price-hero {
    padding: 1rem;
    border-radius: 1rem;
  }

  .price-toolbar {
    display: flex;
    flex-direction: column;
    align-items: stretch !important;
    gap: 0.85rem;
  }

  .price-title {
    font-size: 1.25rem;
    line-height: 1.35;
  }

  .price-subtitle {
    font-size: 0.92rem;
    line-height: 1.5;
  }

  .price-toolbar .btn {
    width: 100%;
    min-height: 44px;
  }

  .price-card {
    border: 0;
    box-shadow: none;
    background: transparent;
  }

  .price-card .card-body {
    padding: 0 !important;
  }

  .table-responsive {
    overflow: visible !important;
  }

  .responsive-table {
    display: block !important;
    width: 100% !important;
    min-width: 0 !important;
    border-collapse: separate !important;
  }

  .responsive-table thead {
    display: none !important;
  }

  .responsive-table tbody {
    display: block !important;
    width: 100% !important;
  }

  .responsive-table tr {
    display: block !important;
    width: 100% !important;
    box-sizing: border-box;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 1rem;
    padding: 0.85rem;
    margin-bottom: 0.85rem;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
  }

  .responsive-table td {
    display: grid !important;
    grid-template-columns: 82px minmax(0, 1fr);
    align-items: center;
    gap: 0.75rem;
    width: 100% !important;
    box-sizing: border-box;
    border: 0 !important;
    padding: 0.55rem 0 !important;
    text-align: right;
    white-space: normal !important;
    word-break: break-word;
  }

  .responsive-table td::before {
    content: attr(data-label);
    color: #64748b;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
  }

  .responsive-table td > * {
    min-width: 0;
  }

  .responsive-table td[data-label="ราคา"] {
    font-size: 1rem;
  }

  .responsive-table td[data-label="ราคา"] .price-badge {
    justify-self: end;
    max-width: 100%;
    white-space: nowrap;
  }

  .responsive-table td[data-label="จัดการ"] {
    align-items: center;
  }

  .responsive-table td[data-label="จัดการ"] .action-group {
    justify-self: end;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
  }

  .responsive-table td[data-label="จัดการ"] form {
    margin: 0 !important;
  }

  .responsive-table .btn-sm {
    min-width: 40px;
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
}
@media (max-width: 420px) {
  .price-title {
    font-size: 1.12rem;
  }

  .responsive-table td {
    font-size: .92rem;
    padding: 0.5rem;
  }

  .responsive-table tr {
    padding: 0.75rem;
  }

  .action-group .btn {
    font-size: 0.8rem;
    padding: 0.4rem 0.6rem;
  }
}
</style>

<div class="container price-page mt-4 mb-4">
  <div class="price-hero mb-3">
    <div class="price-toolbar d-flex justify-content-between align-items-center">
      <div>
        <h3 class="price-title">
          <i class="bi bi-cash-coin me-2 text-success"></i>ราคายาง
        </h3>
        <p class="price-subtitle">รายการราคายางล่าสุด เรียงจากวันที่ใหม่ไปเก่า</p>
      </div>

      <?php if (function_exists('is_admin') && is_admin()): ?>
        <a href="price_form.php?action=create" class="btn btn-success rounded-pill px-4">
          <i class="bi bi-plus-circle me-1"></i>เพิ่มราคายาง
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-info rounded-4 border-0 shadow-sm">
      <i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($msg); ?>
    </div>
  <?php endif; ?>

  <div class="card price-card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-sm datatable responsive-table price-table">
          <thead>
            <tr>
              <th>#</th>
              <th><i class="bi bi-calendar2-week me-1"></i>ปี</th>
              <th><i class="bi bi-calendar-date me-1"></i>วันที่</th>
              <th><i class="bi bi-clock-history me-1"></i>รอบ</th>
              <th><i class="bi bi-cash-stack me-1"></i>ราคา</th>
              <?php if (function_exists('is_admin') && is_admin()): ?>
                <th class="no-sort text-end"><i class="bi bi-gear me-1"></i>จัดการ</th>
              <?php endif; ?>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($prices as $p): ?>
              <tr>
                <td data-label="#"><?php echo (int)$p['pr_id']; ?></td>
                <td data-label="ปี"><?php echo (int)$p['pr_year']; ?></td>
                <td data-label="วันที่">
                  <i class="bi bi-calendar-date me-1 text-secondary"></i>
                  <?php echo thai_date_format($p['pr_date']); ?>
                </td>
                <td data-label="รอบ">
                  <i class="bi bi-clock-history me-1 text-secondary"></i>
                  <?php echo htmlspecialchars($p['pr_number']); ?>
                </td>
                <td data-label="ราคา">
                  <span class="price-badge">
                    <i class="bi bi-cash-stack"></i>
                    <?php echo number_format((float)$p['pr_price'], 2); ?>
                  </span>
                </td>

                <?php if (function_exists('is_admin') && is_admin()): ?>
                  <td data-label="จัดการ" class="text-end">
                    <div class="action-group">
                      <a href="price_form.php?action=edit&id=<?php echo (int)$p['pr_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </a>

                      <form method="post" action="price_delete.php" onsubmit="return confirm('ลบราคานี้หรือไม่?');">
                        <input type="hidden" name="id" value="<?php echo (int)$p['pr_id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Delete">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </div>
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

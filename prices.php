<?php
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

$priceChartRows = array_reverse($prices);
$priceChartLabels = array_values(array_map(static function ($row) {
    return thai_date_format((string)($row['pr_date'] ?? ''));
}, $priceChartRows));
$priceChartValues = array_values(array_map(static function ($row) {
    return round((float)($row['pr_price'] ?? 0), 2);
}, $priceChartRows));
$latestPrice = !empty($prices) ? (float)($prices[0]['pr_price'] ?? 0) : 0;
$latestPriceDate = !empty($prices) ? thai_date_format((string)($prices[0]['pr_date'] ?? '')) : '-';
$rubberAnnouncement = fetch_rubber_collection_announcement($db, !empty($prices) ? (string)($prices[0]['pr_date'] ?? '') : null);
?>
<style>
html,
body {
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

.announcement-card {
  background: linear-gradient(135deg, #fff7ed 0%, #fffdf5 100%);
  border: 1px solid rgba(245, 158, 11, 0.25);
  border-radius: 1.25rem;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.announcement-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 0.9rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #fef3c7;
  color: #b45309;
  flex: 0 0 auto;
}

.announcement-pill {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  border-radius: 999px;
  padding: .35rem .7rem;
  background: #ffffff;
  border: 1px solid rgba(245, 158, 11, 0.25);
  color: #7c2d12;
  font-weight: 600;
}

.announcement-note {
  color: #7c2d12;
}

.price-chart-card {
  border: 1px solid #e5e7eb;
  border-radius: 1.25rem;
  overflow: hidden;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
  background: #fff;
}

.price-chart-wrap {
  position: relative;
  width: 100%;
  height: 340px;
}

.price-chart-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
}

.price-chart-pill {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  border-radius: 999px;
  padding: .35rem .7rem;
  background: #f1f5f9;
  color: #334155;
  font-weight: 600;
  font-size: .95rem;
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
</style>

<div class="container price-page mt-4 mb-4">
  <div class="price-hero mb-3">
    <div class="price-toolbar d-flex justify-content-between align-items-center">
      <div>
        <h3 class="price-title">
          <i data-lucide="dollar-sign" class="me-2 text-success" aria-hidden="true"></i>ราคายาง
        </h3>
        <p class="price-subtitle">รายการราคายางล่าสุด เรียงจากวันที่ใหม่ไปเก่า</p>
      </div>

      <?php if (function_exists('is_admin') && is_admin()): ?>
      <a href="price_form.php?action=create" class="btn btn-success rounded-pill px-4">
        <i data-lucide="plus-circle" class="me-1" aria-hidden="true"></i>เพิ่มราคายาง
      </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="alert alert-info rounded-4 border-0 shadow-sm">
    <i data-lucide="info" class="me-1" aria-hidden="true"></i><?php echo htmlspecialchars($msg); ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($rubberAnnouncement['show'])): ?>
  <div class="card announcement-card mb-3">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div class="d-flex align-items-start gap-3">
          <div class="announcement-icon" aria-hidden="true">
            <i data-lucide="megaphone" class="fs-4"></i>
          </div>
          <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <span class="badge text-bg-warning text-dark">ประกาศอัตโนมัติ</span>
              <h4 class="h5 mb-0">วันวางยางและวันชั่งยางรอบล่าสุด</h4>
            </div>
            <div class="announcement-note mb-2">
              <?php echo e($rubberAnnouncement['lay_start_text'] ?? '-'); ?> ถึง <?php echo e($rubberAnnouncement['lay_end_text'] ?? '-'); ?>
              สำหรับการวางยาง และชั่งยางวันที่ <?php echo e($rubberAnnouncement['weigh_date_text'] ?? '-'); ?>
            </div>
            <div class="small text-secondary">
              ประกาศนี้จะแสดงจนถึงวันชั่งยาง และจะหายไปอัตโนมัติหลังวันชั่งยาง
            </div>
          </div>
        </div>
        <div class="d-flex flex-column gap-2">
          <span class="announcement-pill"><i data-lucide="calendar-range" aria-hidden="true"></i> วางยาง <?php echo e($rubberAnnouncement['lay_start_text'] ?? '-'); ?></span>
          <span class="announcement-pill"><i data-lucide="calendar-check" aria-hidden="true"></i> วางยาง <?php echo e($rubberAnnouncement['lay_end_text'] ?? '-'); ?></span>
          <span class="announcement-pill"><i data-lucide="scale" aria-hidden="true"></i> ชั่งยาง <?php echo e($rubberAnnouncement['weigh_date_text'] ?? '-'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($prices)): ?>
  <div class="price-chart-card mb-3">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
          <h4 class="h5 mb-1">
            <i data-lucide="chart-line" class="me-2 text-success" aria-hidden="true"></i>กราฟแนวโน้มราคายาง
          </h4>
          <div class="text-secondary small">แสดงการเปลี่ยนแปลงของราคายางตามวันที่ที่บันทึกไว้ในระบบ</div>
        </div>
        <div class="price-chart-meta">
          <span class="price-chart-pill"><i data-lucide="calendar" aria-hidden="true"></i><?php echo htmlspecialchars($latestPriceDate); ?></span>
          <span class="price-chart-pill"><i data-lucide="dollar-sign" aria-hidden="true"></i><?php echo number_format($latestPrice, 2); ?> บาท</span>
        </div>
      </div>
      <div class="price-chart-wrap">
        <canvas id="priceTrendChart" aria-label="กราฟแนวโน้มราคายาง" role="img"></canvas>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="card price-card">
    <div class="card-body">
      <?php if (empty($prices)): ?>
      <div class="text-center py-5">
        <div class="mb-2 text-secondary">
          <i data-lucide="inbox" class="fs-1" aria-hidden="true"></i>
        </div>
        <h5 class="fw-bold mb-1">ยังไม่มีข้อมูลราคายาง</h5>
        <p class="text-secondary mb-0">เพิ่มรายการแรกเพื่อเริ่มแสดงข้อมูลในหน้านี้</p>
      </div>
      <?php else: ?>
      <div class="d-none d-md-block">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col">#</th>
                <th scope="col"><i data-lucide="calendar" class="me-1" aria-hidden="true"></i>ปี</th>
                <th scope="col"><i data-lucide="calendar" class="me-1" aria-hidden="true"></i>วันที่</th>
                <th scope="col"><i data-lucide="history" class="me-1" aria-hidden="true"></i>รอบ</th>
                <th scope="col"><i data-lucide="dollar-sign" class="me-1" aria-hidden="true"></i>ราคา</th>
                <?php if (function_exists('is_admin') && is_admin()): ?>
                <th scope="col" class="text-end"><i data-lucide="settings" class="me-1" aria-hidden="true"></i>จัดการ
                </th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($prices as $p): ?>
              <tr>
                <td><?php echo (int)$p['pr_id']; ?></td>
                <td><?php echo (int)$p['pr_year']; ?></td>
                <td>
                  <i data-lucide="calendar" class="me-1 text-secondary" aria-hidden="true"></i>
                  <?php echo thai_date_format($p['pr_date']); ?>
                </td>
                <td>
                  <i data-lucide="history" class="me-1 text-secondary" aria-hidden="true"></i>
                  <?php echo htmlspecialchars($p['pr_number']); ?>
                </td>
                <td>
                  <span class="price-badge">
                    <i data-lucide="dollar-sign" aria-hidden="true"></i>
                    <?php echo number_format((float)$p['pr_price'], 2); ?>
                  </span>
                </td>
                <?php if (function_exists('is_admin') && is_admin()): ?>
                <td class="text-end">
                  <div class="d-inline-flex gap-2">
	                    <a href="price_form.php?action=edit&id=<?php echo (int)$p['pr_id']; ?>"
                      class="btn btn-sm btn-outline-primary rounded-pill" title="แก้ไข" aria-label="แก้ไข">
                      <i data-lucide="pencil" aria-hidden="true"></i>
                    </a>
                    <form method="post" action="price_delete.php" class="m-0"
                      onsubmit="return confirm('ลบราคานี้หรือไม่?');">
                      <input type="hidden" name="id" value="<?php echo (int)$p['pr_id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="ลบ">
                        <i data-lucide="trash-2" aria-hidden="true"></i>
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

      <div class="d-md-none">
        <div class="row g-3">
          <?php foreach ($prices as $p): ?>
          <div class="col-12">
            <div class="border rounded-4 bg-white p-3 shadow-sm">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <div class="text-secondary small">#<?php echo (int)$p['pr_id']; ?></div>
                  <h5 class="mb-1 fs-6 fw-bold text-dark">
                    ปี <?php echo (int)$p['pr_year']; ?>
                  </h5>
                  <div class="text-secondary small">
                    <i data-lucide="calendar" class="me-1"
                      aria-hidden="true"></i><?php echo thai_date_format($p['pr_date']); ?>
                  </div>
                </div>
                <span class="price-badge flex-shrink-0">
                  <i data-lucide="dollar-sign" aria-hidden="true"></i>
                  <?php echo number_format((float)$p['pr_price'], 2); ?>
                </span>
              </div>

              <div class="row g-2 small">
                <div class="col-12">
                  <div class="d-flex justify-content-between border-top pt-2">
                <span class="text-secondary">รอบ</span>
                    <span class="fw-medium text-dark"><?php echo htmlspecialchars($p['pr_number']); ?></span>
                  </div>
                </div>
              </div>

              <?php if (function_exists('is_admin') && is_admin()): ?>
	              <div class="d-flex gap-2 mt-3">
	                <a href="price_form.php?action=edit&id=<?php echo (int)$p['pr_id']; ?>"
                  class="btn btn-sm btn-outline-primary rounded-pill flex-fill" title="แก้ไข" aria-label="แก้ไข">
                  <i data-lucide="pencil" class="me-1" aria-hidden="true"></i>แก้ไข
                </a>
                <form method="post" action="price_delete.php" class="flex-fill m-0"
                  onsubmit="return confirm('ลบราคานี้หรือไม่?');">
                  <input type="hidden" name="id" value="<?php echo (int)$p['pr_id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill w-100">
                    <i data-lucide="trash-2" class="me-1" aria-hidden="true"></i>ลบ
                  </button>
                </form>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($prices)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const canvas = document.getElementById('priceTrendChart');
  if (!canvas || typeof Chart === 'undefined') return;

  const labels = <?php echo json_encode($priceChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const values = <?php echo json_encode($priceChartValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'ราคายาง',
        data: values,
        borderColor: '#16a34a',
        backgroundColor: 'rgba(22, 163, 74, 0.12)',
        pointBackgroundColor: '#16a34a',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        borderWidth: 3,
        tension: 0.28,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const value = Number(context.parsed.y || 0).toLocaleString('th-TH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              });
              return ' ราคา ' + value + ' บาท';
            }
          }
        }
      },
      scales: {
        x: {
          ticks: {
            maxRotation: 0,
            autoSkip: true,
            color: '#64748b'
          },
          grid: {
            color: 'rgba(148, 163, 184, 0.15)'
          }
        },
        y: {
          beginAtZero: false,
          ticks: {
            color: '#64748b',
            callback: function(value) {
              return Number(value).toLocaleString('th-TH', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
              });
            }
          },
          grid: {
            color: 'rgba(148, 163, 184, 0.15)'
          }
        }
      }
    }
  });
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>

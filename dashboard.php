<?php
require_once 'functions.php';
require_login();
include 'header.php';
$cu = current_user();

$conn = db();
$memberPortalStats = fetch_member_portal_usage_stats($conn);

function get_latest_collection_date(mysqli $conn): string {
    $latest = '';
    $sql = "SELECT pr_date FROM tbl_price ORDER BY pr_date DESC, pr_id DESC LIMIT 1";
    if ($result = $conn->query($sql)) {
        if ($row = $result->fetch_assoc()) {
            $latest = (string)($row['pr_date'] ?? '');
        }
        $result->free();
    }

    if ($latest === '') {
        $sql = "SELECT MAX(ru_date) AS latest_date FROM tbl_rubber";
        if ($result = $conn->query($sql)) {
            if ($row = $result->fetch_assoc()) {
                $latest = (string)($row['latest_date'] ?? '');
            }
            $result->free();
        }
    }

    return $latest;
}

function fetch_top_rubber_quantity(mysqli $conn, string $class, ?string $roundDate = null): array {
    $where = ['LOWER(TRIM(ru_class)) = ?'];
    $types = 's';
    $params = [strtolower($class)];

    if ($roundDate !== null && $roundDate !== '') {
        $where[] = 'ru_date = ?';
        $types .= 's';
        $params[] = $roundDate;
    }

    $sql = "SELECT
            TRIM(ru_group) AS ru_group,
            TRIM(ru_number) AS ru_number,
            TRIM(ru_fullname) AS ru_fullname,
            SUM(ru_quantity) AS total_quantity,
            COUNT(*) AS entry_count
        FROM tbl_rubber
        WHERE " . implode(' AND ', $where) . "
        GROUP BY TRIM(ru_group), TRIM(ru_number), TRIM(ru_fullname)
        HAVING total_quantity > 0
        ORDER BY total_quantity DESC, ru_fullname ASC
        LIMIT 10";

    $rows = [];
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function fetch_latest_deduction_summary(mysqli $conn, string $class, ?string $roundDate = null): array {
    $where = ['LOWER(TRIM(ru_class)) = ?'];
    $types = 's';
    $params = [strtolower($class)];

    if ($roundDate !== null && $roundDate !== '') {
        $where[] = 'ru_date = ?';
        $types .= 's';
        $params[] = $roundDate;
    }

    $sql = "SELECT
            SUM(ru_hoon) AS total_hoon,
            SUM(ru_loan) AS total_loan,
            SUM(ru_shortdebt) AS total_shortdebt,
            SUM(ru_deposit) AS total_deposit,
            SUM(ru_tradeloan) AS total_tradeloan,
            SUM(ru_insurance) AS total_insurance,
            SUM(ru_expend) AS total_expend,
            SUM(ru_value) AS total_value,
            SUM(ru_netvalue) AS total_netvalue,
            SUM(ru_quantity) AS total_quantity,
            COUNT(*) AS entry_count
        FROM tbl_rubber
        WHERE " . implode(' AND ', $where);

    $summary = [
        'total_hoon' => 0.0,
        'total_loan' => 0.0,
        'total_shortdebt' => 0.0,
        'total_deposit' => 0.0,
        'total_tradeloan' => 0.0,
        'total_insurance' => 0.0,
        'total_expend' => 0.0,
        'total_value' => 0.0,
        'total_netvalue' => 0.0,
        'total_quantity' => 0.0,
        'entry_count' => 0,
    ];

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && ($row = $result->fetch_assoc())) {
            foreach ($summary as $key => $defaultValue) {
                if (array_key_exists($key, $row) && $row[$key] !== null) {
                    $summary[$key] = (float)$row[$key];
                }
            }
            $summary['entry_count'] = (int)($row['entry_count'] ?? 0);
        }
        if ($result) {
            $result->free();
        }
        $stmt->close();
    }

    $summary['total_deduct'] = array_sum([
        $summary['total_hoon'],
        $summary['total_loan'],
        $summary['total_shortdebt'],
        $summary['total_deposit'],
        $summary['total_tradeloan'],
        $summary['total_insurance'],
    ]);

    return $summary;
}

function fetch_latest_collection_group_summary(mysqli $conn, string $class, ?string $roundDate = null): array {
    $where = ['LOWER(TRIM(ru_class)) = ?'];
    $types = 's';
    $params = [strtolower($class)];

    if ($roundDate !== null && $roundDate !== '') {
        $where[] = 'ru_date = ?';
        $types .= 's';
        $params[] = $roundDate;
    }

    $isMember = strtolower($class) === 'member';

    if ($isMember) {
        $sql = "SELECT
                TRIM(ru_group) AS ru_group,
                COUNT(DISTINCT TRIM(ru_number)) AS person_count,
                SUM(ru_quantity) AS total_quantity,
                COUNT(*) AS entry_count
            FROM tbl_rubber
            WHERE " . implode(' AND ', $where) . "
            GROUP BY TRIM(ru_group)
            ORDER BY CAST(TRIM(ru_group) AS UNSIGNED), TRIM(ru_group)";
    } else {
        $sql = "SELECT
                COUNT(DISTINCT TRIM(ru_number)) AS person_count,
                SUM(ru_quantity) AS total_quantity,
                COUNT(*) AS entry_count
            FROM tbl_rubber
            WHERE " . implode(' AND ', $where);
    }

    $rows = [];
    $summary = [
        'person_count' => 0,
        'total_quantity' => 0.0,
        'entry_count' => 0,
        'groups' => [],
    ];

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            if ($isMember) {
                while ($row = $result->fetch_assoc()) {
                    $groupName = trim((string)($row['ru_group'] ?? ''));
                    $rows[] = [
                        'ru_group' => $groupName !== '' ? $groupName : '-',
                        'person_count' => (int)($row['person_count'] ?? 0),
                        'total_quantity' => (float)($row['total_quantity'] ?? 0),
                        'entry_count' => (int)($row['entry_count'] ?? 0),
                    ];
                    $summary['person_count'] += (int)($row['person_count'] ?? 0);
                    $summary['total_quantity'] += (float)($row['total_quantity'] ?? 0);
                    $summary['entry_count'] += (int)($row['entry_count'] ?? 0);
                }
            } elseif ($row = $result->fetch_assoc()) {
                $summary['person_count'] = (int)($row['person_count'] ?? 0);
                $summary['total_quantity'] = (float)($row['total_quantity'] ?? 0);
                $summary['entry_count'] = (int)($row['entry_count'] ?? 0);
            }
            $result->free();
        }
        $stmt->close();
    }

    $summary['groups'] = $rows;
    return $summary;
}

function render_deduction_summary_card(string $title, string $subtitle, array $summary, string $tone = 'success'): void {
    $chips = [
        'หุ้น' => $summary['total_hoon'] ?? 0,
        'เงินกู้' => $summary['total_loan'] ?? 0,
        'หนี้สั้น' => $summary['total_shortdebt'] ?? 0,
        'เงินฝาก' => $summary['total_deposit'] ?? 0,
        'กู้ซื้อขาย' => $summary['total_tradeloan'] ?? 0,
        'ประกันภัย' => $summary['total_insurance'] ?? 0,
    ];
    ?>
<div class="col-12 col-lg-6">
  <div class="card h-100 shadow-sm deduction-card border-<?php echo e($tone); ?>">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
          <h5 class="card-title mb-1"><i data-lucide="scissors" class="me-2"
              aria-hidden="true"></i><?php echo e($title); ?></h5>
          <p class="card-text small text-muted mb-0"><?php echo e($subtitle); ?></p>
        </div>
        <span class="badge text-bg-<?php echo e($tone); ?>">ล่าสุด</span>
      </div>

      <div class="deduction-summary-grid mb-3">
        <div class="deduction-summary-item">
          <div class="deduction-label">ยอดหักรวม</div>
          <div class="deduction-value">฿<?php echo number_format((float)($summary['total_deduct'] ?? 0), 2); ?></div>
        </div>
        <div class="deduction-summary-item">
          <div class="deduction-label">รายการ</div>
          <div class="deduction-value"><?php echo number_format((int)($summary['entry_count'] ?? 0)); ?> รายการ</div>
        </div>
      </div>

      <div class="deduction-chip-wrap">
        <?php foreach ($chips as $label => $amount): ?>
        <?php if ((float)$amount > 0): ?>
        <span class="deduction-chip">
          <?php echo e($label); ?>: <?php echo number_format((float)$amount, 2); ?> ฿
        </span>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty(array_filter($chips, fn($v) => (float)$v > 0))): ?>
        <div class="text-muted small py-2">ยังไม่มีรายการหักในรอบนี้</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
}

function render_collection_summary_card(string $title, string $subtitle, array $summary, string $tone = 'success', bool $memberMode = true): void {
    ?>
<div class="col-12 col-lg-6">
  <div class="card h-100 shadow-sm collection-card border-<?php echo e($tone); ?>">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
          <h5 class="card-title mb-1"><i data-lucide="database" class="me-2"
              aria-hidden="true"></i><?php echo e($title); ?></h5>
          <p class="card-text small text-muted mb-0"><?php echo e($subtitle); ?></p>
        </div>
        <span class="badge text-bg-<?php echo e($tone); ?>">ล่าสุด</span>
      </div>

      <div class="collection-summary-grid mb-3">
        <div class="collection-summary-item">
          <div class="collection-label">จำนวนคน</div>
          <div class="collection-value"><?php echo number_format((int)($summary['person_count'] ?? 0)); ?> คน</div>
        </div>
        <div class="collection-summary-item">
          <div class="collection-label">ปริมาณรวม</div>
          <div class="collection-value"><?php echo number_format((float)($summary['total_quantity'] ?? 0), 2); ?> กก.
          </div>
        </div>
      </div>

      <?php if ($memberMode): ?>
      <div class="collection-chip-wrap">
        <?php if (!empty($summary['groups'])): ?>
        <?php foreach ($summary['groups'] as $groupRow): ?>
        <span class="collection-chip">
          กลุ่ม <?php echo e($groupRow['ru_group']); ?>:
          <?php echo number_format((int)($groupRow['person_count'] ?? 0)); ?> คน /
          <?php echo number_format((float)($groupRow['total_quantity'] ?? 0), 2); ?> กก.
        </span>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="text-muted small py-2">ยังไม่มีข้อมูลสมาชิกในรอบนี้</div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="collection-chip-wrap">
        <span class="collection-chip">
          <?php echo number_format((int)($summary['person_count'] ?? 0)); ?> คนที่รวบรวมในรอบนี้
        </span>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
}

function render_top_rubber_table(string $title, string $subtitle, array $rows): void {
    static $topRubberTableIndex = 0;
    $topRubberTableIndex++;
    $collapseId = 'topRubberTable' . $topRubberTableIndex;
    ?>
<div class="col-12 col-lg-6">
  <div class="card h-100 shadow-sm top-rubber-card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-3 top-rubber-heading">
        <div>
          <h5 class="card-title mb-1"><i data-lucide="trophy" class="me-2"
              aria-hidden="true"></i><?php echo e($title); ?></h5>
          <p class="card-text small text-muted mb-0"><?php echo e($subtitle); ?></p>
        </div>
        <div class="d-flex align-items-center gap-2 top-rubber-actions">
          <span class="badge text-bg-success">Top 10</span>
          <button type="button" class="btn btn-outline-success btn-sm top-rubber-toggle" data-bs-toggle="collapse"
            data-bs-target="#<?php echo e($collapseId); ?>" aria-expanded="false"
            aria-controls="<?php echo e($collapseId); ?>">แสดงข้อมูล</button>
        </div>
      </div>

      <div class="collapse" id="<?php echo e($collapseId); ?>">
        <?php if (empty($rows)): ?>
        <div class="text-muted small py-3">ยังไม่มีข้อมูลสำหรับแสดงอันดับ</div>
        <?php else: ?>
        <div class="top-rubber-table-wrap">
          <table class="table table-sm align-middle top-rubber-table mb-0">
            <thead>
              <tr>
                <th class="text-center rank-col">#</th>
                <th>ชื่อ-สกุล/เลขที่</th>

                <th class="text-end quantity-col">รวม (กก.)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $idx => $row): ?>
              <tr>
                <td class="text-center fw-semibold"><?php echo $idx + 1; ?></td>
                <td class="top-rubber-name">
                  <?php echo e($row['ru_fullname'] ?? '-'); ?>/[<?php echo e(($row['ru_number'] ?? '') !== '' ? $row['ru_number'] : '-'); ?>]
                </td>
                <td class="text-end fw-semibold"><?php echo number_format((float)($row['total_quantity'] ?? 0), 2); ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
}

$latestCollectionDate = get_latest_collection_date($conn);
$latestMemberTop = fetch_top_rubber_quantity($conn, 'member', $latestCollectionDate);
$latestGeneralTop = fetch_top_rubber_quantity($conn, 'general', $latestCollectionDate);
$allMemberTop = fetch_top_rubber_quantity($conn, 'member');
$allGeneralTop = fetch_top_rubber_quantity($conn, 'general');
$latestMemberDeduction = fetch_latest_deduction_summary($conn, 'member', $latestCollectionDate);
$latestGeneralDeduction = fetch_latest_deduction_summary($conn, 'general', $latestCollectionDate);
$latestMemberCollectionSummary = fetch_latest_collection_group_summary($conn, 'member', $latestCollectionDate);
$latestGeneralCollectionSummary = fetch_latest_collection_group_summary($conn, 'general', $latestCollectionDate);
?>
<!-- Removed Google Fonts, now using local Sarabun -->
<style>
html,
body {
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

.small,
.form-text {
  font-size: 14px !important;
  font-weight: 300 !important;
}

.top-rubber-card .card-title {
  line-height: 1.35;
}

.top-rubber-heading {
  flex-direction: row;
}

.top-rubber-actions {
  flex-shrink: 0;
}

.top-rubber-toggle {
  white-space: nowrap;
}

.top-rubber-table {
  table-layout: fixed;
  width: 100%;
}

.top-rubber-table th {
  color: #245c38;
  font-weight: 700;
  white-space: nowrap;
}

.top-rubber-table td,
.top-rubber-table th {
  border-color: #e7efea;
  vertical-align: middle;
}

.top-rubber-table .rank-col {
  width: 2.4rem;
}

.top-rubber-table .number-col {
  width: 4.4rem;
}

.top-rubber-table .quantity-col {
  width: 7.5rem;
}

.top-rubber-name {
  overflow-wrap: anywhere;
}

.top-rubber-number {
  overflow-wrap: anywhere;
}

.deduction-card {
  border-color: #bbf7d0;
}

.deduction-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .75rem;
}

.deduction-summary-item {
  border: 1px solid #d7f3de;
  border-radius: 1rem;
  background: #f8fdf8;
  padding: .85rem .9rem;
}

.deduction-label {
  font-size: .85rem;
  color: #15803d;
  margin-bottom: .15rem;
}

.deduction-value {
  font-weight: 700;
  color: #14532d;
}

.deduction-chip-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
}

.deduction-chip {
  border: 1px solid #bbf7d0;
  border-radius: 999px;
  background: #fff;
  color: #166534;
  padding: .35rem .65rem;
  font-size: .88rem;
  white-space: nowrap;
}

.collection-card {
  border-color: #bbf7d0;
}

.collection-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .75rem;
}

.collection-summary-item {
  border: 1px solid #d7f3de;
  border-radius: 1rem;
  background: #f8fdf8;
  padding: .85rem .9rem;
}

.collection-label {
  font-size: .85rem;
  color: #15803d;
  margin-bottom: .15rem;
}

.collection-value {
  font-weight: 700;
  color: #14532d;
}

.collection-chip-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
}

.collection-chip {
  border: 1px solid #bbf7d0;
  border-radius: 999px;
  background: #fff;
  color: #166534;
  padding: .35rem .65rem;
  font-size: .88rem;
  white-space: nowrap;
}

@media (max-width: 576px) {
  .top-rubber-table {
    font-size: 0.9rem;
  }

  .top-rubber-table th,
  .top-rubber-table td {
    padding-left: 0.25rem;
    padding-right: 0.25rem;
  }

  .top-rubber-table .rank-col {
    width: 2rem;
  }

  .top-rubber-table .quantity-col {
    width: 5.8rem;
    white-space: normal;
  }

  .deduction-summary-grid {
    grid-template-columns: 1fr;
  }

  .collection-summary-grid {
    grid-template-columns: 1fr;
  }
}

/* Enhanced Responsive Design */
@media (max-width: 992px) {
  .container.my-4 {
    padding: 0 1rem;
  }

  .h4 {
    font-size: 1.3rem;
  }

  .card {
    margin-bottom: 1rem;
  }
}

@media (max-width: 768px) {
  .container.my-4 {
    padding: 0 0.5rem;
    margin-top: 1rem !important;
    margin-bottom: 1rem !important;
  }

  .d-flex.justify-content-between.align-items-center.mb-3 {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch !important;
    text-align: center;
  }

  .h4 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
  }

  .small.text-muted {
    font-size: 0.9rem;
  }

  .d-flex {
    flex-direction: column;
    gap: 0.5rem;
  }

  .btn-sm {
    width: 100%;
    min-height: 44px;
    font-size: 1rem;
    justify-content: center;
  }

  .row.g-3 {
    gap: 1rem;
  }

  .col-12.col-md-6 {
    flex: 0 0 100%;
    max-width: 100%;
  }

  .card {
    margin-bottom: 1rem;
  }

  .card-body {
    padding: 1rem;
  }

  .card-title {
    font-size: 1.1rem;
    margin-bottom: 0.75rem;
  }

  .card-text {
    font-size: 1rem;
    margin-bottom: 0.5rem;
  }

  .small.text-muted {
    font-size: 0.85rem;
  }

  .d-flex.flex-wrap.gap-2 {
    flex-direction: column;
    gap: 0.5rem !important;
  }

  .btn {
    min-height: 44px;
    font-size: 1rem;
  }

  .form-select {
    min-height: 44px;
    font-size: 1rem;
  }

  .form-label {
    font-size: 1rem;
    margin-bottom: 0.5rem;
  }

  .card-footer {
    padding: 1rem;
    background: transparent;
    border-top: 1px solid #e9ecef;
  }

  .card-footer .d-flex {
    justify-content: center;
  }

  .border-danger,
  .border-warning {
    border-width: 2px !important;
  }
}

@media (max-width: 576px) {
  .container.my-4 {
    padding: 0 0.25rem;
  }

  .h4 {
    font-size: 1.1rem;
  }

  .card-body {
    padding: 0.75rem;
  }

  .card-title {
    font-size: 1rem;
  }

  .card-text {
    font-size: 0.9rem;
  }

  .btn-sm {
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
  }

  .form-select {
    font-size: 1rem;
  }

  .card-footer {
    padding: 0.75rem;
  }
}

/* Landscape orientation */
@media (max-width: 768px) and (orientation: landscape) {
  .container.my-4 {
    margin-top: 0.5rem !important;
    margin-bottom: 0.5rem !important;
  }

  .card-body {
    padding: 0.75rem;
  }
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {

  .btn,
  .form-select {
    min-height: 44px;
  }

  .card:hover {
    transform: none;
  }
}
</style>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0"><i data-lucide="gauge" class="me-2" aria-hidden="true"></i>Dashboard</h1>
      <div class="small text-muted">สวัสดี <?php echo htmlspecialchars($cu['user_fullname'] ?? $cu['user_username']); ?>
        (<?php echo htmlspecialchars($cu['user_level']); ?>)</div>
    </div>
  </div>

  <div class="row g-3">
    <!-- <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i  data-lucide="lightning-charge" class="me-2" aria-hidden="true"></i>Quick links</h5>
                    <p class="card-text small text-muted mb-2">ลิงก์ด่วนสำหรับใช้งานทั่วไป</p>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="index.php" class="btn btn-primary btn-sm"><i  data-lucide="home" class="me-1" aria-hidden="true"></i>ไปที่หน้าแรก</a>
                        <a href="rubbers.php" class="btn btn-outline-primary btn-sm"><i  data-lucide="box" class="me-1" aria-hidden="true"></i>จัดการข้อมูลยาง</a>
                        <a href="members.php" class="btn btn-outline-secondary btn-sm"><i  data-lucide="users" class="me-1" aria-hidden="true"></i>สมาชิก</a>
                        <a href="report_rubber.php" class="btn btn-outline-info btn-sm"><i  data-lucide="bar-chart-2" class="me-1" aria-hidden="true"></i>รายงานข้อมูลยางพารา (ค้นหา)</a>
                    </div>
                </div>
            </div>
        </div> -->



    <div class="col-12 col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <h5 class="card-title"><i data-lucide="user" class="me-2" aria-hidden="true"></i>Account</h5>
          <p class="card-text mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($cu['user_username']); ?></p>
          <p class="card-text mb-1"><strong>Fullname:</strong> <?php echo htmlspecialchars($cu['user_fullname']); ?></p>
          <p class="card-text mb-0"><strong>Level:</strong> <?php echo htmlspecialchars($cu['user_level']); ?></p>
        </div>
        <div class="card-footer bg-transparent d-flex justify-content-end align-items-center">
          <div>
            <a href="user_form.php?action=edit&id=<?php echo (int)($cu['user_id'] ?? 0); ?>"
              class="btn btn-sm btn-outline-primary"><i data-lucide="edit" class="me-1"
                aria-hidden="true"></i>แก้ไขโปรไฟล์</a>
          </div>

        </div>
      </div>
    </div>


    <!-- Export Section Start -->
    <div class="col-12 col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <h5 class="card-title"><i data-lucide="download" class="me-2" aria-hidden="true"></i>Export Data</h5>
          <p class="card-text text-muted mb-2">ส่งออกข้อมูลรายการตามรอบวันที่ที่รวบรวม (จากราคายาง)</p>
          <?php
                    // ดึง pr_date จาก tbl_price โดยใช้ db() จาก functions.php
                    $dates = [];
                    $sql = "SELECT DISTINCT pr_date FROM tbl_price ORDER BY pr_date DESC";
                    $result = $conn->query($sql);
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $dates[] = $row['pr_date'];
                        }
                    }
                    ?>
          <form class="row g-2 align-items-end" method="get" action="export_rubbers_export.php" id="exportForm">
            <div class="col-12">
              <label for="pr_date" class="form-label mb-1">เลือกรอบวันที่ (pr_date)</label>
              <select class="form-select form-select-sm" id="pr_date" name="pr_date" required>
                <option value="">-- เลือกรอบวันที่ --</option>
                <?php foreach($dates as $d): ?>
                <option value="<?php echo $d; ?>">
                  <?php echo thai_date_format($d); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="button" onclick="exportType('pdf')" class="btn btn-success btn-sm"><i
                  data-lucide="file-text" class="me-1" aria-hidden="true"></i>ส่งออก PDF</button>


              <button type="button" onclick="exportType('excel')" class="btn btn-primary btn-sm"><i
                  data-lucide="file-text" class="me-1" aria-hidden="true"></i>ส่งออก Excel</button>
              <a href="export_round_matrix.php" class="btn btn-outline-primary btn-sm"><i data-lucide="calendar"
                  class="me-1" aria-hidden="true"></i>หลายรอบวันที่</a>
            </div>
          </form>
          <script>
          function exportType(type) {
            var form = document.getElementById('exportForm');
            var pr_date = document.getElementById('pr_date').value;
            if (!pr_date) {
              alert('กรุณาเลือกรอบวันที่');
              return;
            }
            var url = form.action + '?pr_date=' + encodeURIComponent(pr_date) + '&export_type=' + type;
            // If exporting Excel (CSV/Excel-compatible), request server to prepend UTF-8 BOM so Thai characters display correctly in Excel
            if (type === 'excel') {
              url += '&bom=1';
            }
            window.open(url, '_blank');
          }
          </script>
        </div>
      </div>
    </div>
    <!-- Export Section End -->
    <div class="col-12 mb-4">
      <div class="card h-100 shadow-sm border-success-subtle">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
          <div class="d-flex align-items-center gap-3">
            <span
              class="d-inline-flex align-items-center justify-content-center rounded-4 bg-success-subtle text-success"
              style="width:3rem;height:3rem;">
              <i data-lucide="activity" aria-hidden="true"></i>
            </span>
            <div>
              <h2 class="h5 mb-1">สรุปการใช้งานสมาชิก</h2>
              <div class="text-muted">ดูการเข้าสู่ระบบ ประวัติล่าสุด และภาพรวมการใช้งานในหน้าแยก</div>
            </div>
          </div>
          <div class="d-flex flex-column flex-sm-row gap-2">
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
              <?php echo number_format((int)($memberPortalStats['total_logins'] ?? 0)); ?> ครั้ง
            </span>
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
              <?php echo number_format((int)($memberPortalStats['today_logins'] ?? 0)); ?> วันนี้
            </span>
            <a href="member_usage_summary.php" class="btn btn-success">
              <i data-lucide="arrow-right" class="me-1" aria-hidden="true"></i>เปิดหน้าสรุป
            </a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title"><i data-lucide="users" class="me-2" aria-hidden="true"></i>Members Management</h5>
          <p class="card-text small text-muted">จัดการสมาชิก (CRUD) สำหรับผู้ใช้งานที่เข้าสู่ระบบ</p>
          <div class="row gy-2">
            <div class="col-auto"><a href="members.php" class="btn btn-success btn-sm"><i data-lucide="list"
                  class="me-1" aria-hidden="true"></i>รายการสมาชิก</a></div>
            <div class="col-auto"><a href="member_form.php?action=create" class="btn btn-outline-success btn-sm"><i
                  data-lucide="user-plus" class="me-1" aria-hidden="true"></i>เพิ่มสมาชิก</a></div>
            <div class="col-auto"><a href="add_person_code.php" class="btn btn-outline-success btn-sm"><i
                  data-lucide="user-plus" class="me-1" aria-hidden="true"></i>เพิ่มรหัสบุคคล</a></div>
          </div>
        </div>
      </div>
    </div>

    <?php if (function_exists('is_admin') && is_admin()): ?>
    <div class="col-12 col-lg-6">
      <div class="card border-danger shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title text-danger"><i data-lucide="person-gear" class="me-2" aria-hidden="true"></i>Admin —
            User Management</h5>
          <p class="card-text small text-muted">จัดการบัญชีผู้ใช้งาน (เฉพาะผู้ดูแลระบบ)</p>
          <div class="d-flex flex-wrap gap-2">
            <a href="users.php" class="btn btn-danger btn-sm"><i data-lucide="list" class="me-1"
                aria-hidden="true"></i>รายการผู้ใช้งาน</a>
            <a href="user_form.php?action=create" class="btn btn-outline-danger btn-sm"><i data-lucide="user-plus"
                class="me-1" aria-hidden="true"></i>สร้างผู้ใช้ใหม่</a>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card border-warning shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title text-warning"><i data-lucide="dollar-sign" class="me-2" aria-hidden="true"></i>Admin —
            Prices Management</h5>
          <p class="card-text small text-muted">จัดการราคายาง (เฉพาะผู้ดูแลระบบ)</p>
          <div class="d-flex flex-wrap gap-2">
            <a href="prices.php" class="btn btn-warning btn-sm"><i data-lucide="list" class="me-1"
                aria-hidden="true"></i>รายการราคายาง</a>
            <a href="price_form.php?action=create" class="btn btn-outline-warning btn-sm"><i data-lucide="plus-circle"
                class="me-1" aria-hidden="true"></i>เพิ่มราคายาง</a>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
        <div>
          <h2 class="h5 mb-1"><i data-lucide="scissors" class="me-2" aria-hidden="true"></i>สรุปรายการหักรอบล่าสุด</h2>
          <div class="small text-muted">
            <?php if ($latestCollectionDate !== ''): ?>
            รอบล่าสุด: <?php echo e(thai_date_format($latestCollectionDate)); ?>
            <?php else: ?>
            ยังไม่พบรอบการรวบรวม
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php
        $latestDeductSubtitle = $latestCollectionDate !== ''
            ? 'รอบการรวบรวมล่าสุด ' . thai_date_format($latestCollectionDate)
            : 'รอบการรวบรวมล่าสุด';
        render_deduction_summary_card('สมาชิก', $latestDeductSubtitle, $latestMemberDeduction, 'success');
        render_deduction_summary_card('เกษตรกรทั่วไป', $latestDeductSubtitle, $latestGeneralDeduction, 'warning');
    ?>

    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
        <div>
          <h2 class="h5 mb-1"><i data-lucide="users" class="me-2" aria-hidden="true"></i>สรุปจำนวนที่รวบรวมรอบล่าสุด
          </h2>
          <div class="small text-muted">
            <?php if ($latestCollectionDate !== ''): ?>
            รอบล่าสุด: <?php echo e(thai_date_format($latestCollectionDate)); ?>
            <?php else: ?>
            ยังไม่พบรอบการรวบรวม
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php
        $latestCollectionSubtitle = $latestCollectionDate !== ''
            ? 'รอบการรวบรวมล่าสุด ' . thai_date_format($latestCollectionDate)
            : 'รอบการรวบรวมล่าสุด';
        render_collection_summary_card('สมาชิก', $latestCollectionSubtitle, $latestMemberCollectionSummary, 'success', true);
        render_collection_summary_card('เกษตรกรทั่วไป', $latestCollectionSubtitle, $latestGeneralCollectionSummary, 'warning', false);
    ?>



    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mt-2 top-rubber-section-title">
        <div>
          <h2 class="h5 mb-1"><i data-lucide="bar-chart-3" class="me-2" aria-hidden="true"></i>อันดับปริมาณยางสูงสุด
          </h2>
          <div class="small text-muted">
            <?php if ($latestCollectionDate !== ''): ?>
            รอบล่าสุด: <?php echo e(thai_date_format($latestCollectionDate)); ?>
            <?php else: ?>
            ยังไม่พบรอบการรวบรวม
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php
        $latestSubtitle = $latestCollectionDate !== ''
            ? 'รอบการรวบรวมล่าสุด ' . thai_date_format($latestCollectionDate)
            : 'รอบการรวบรวมล่าสุด';
        render_top_rubber_table('10 อันดับสมาชิกที่มีปริมาณยางสูงสุด', $latestSubtitle, $latestMemberTop);
        render_top_rubber_table('10 อันดับเกษตรกรที่มีปริมาณยางสูงสุด', $latestSubtitle, $latestGeneralTop);
        render_top_rubber_table('10 อันดับสมาชิกที่มีปริมาณยางสูงสุด', 'ตั้งแต่มีการรวบรวมมา', $allMemberTop);
        render_top_rubber_table('10 อันดับเกษตรกรที่มีปริมาณยางสูงสุด', 'ตั้งแต่มีการรวบรวมมา', $allGeneralTop);
        ?>

  </div>
</div>

<script>
document.querySelectorAll('.top-rubber-toggle').forEach(function(button) {
  var targetSelector = button.getAttribute('data-bs-target');
  var target = targetSelector ? document.querySelector(targetSelector) : null;
  if (!target) return;

  target.addEventListener('shown.bs.collapse', function() {
    button.textContent = 'ซ่อนข้อมูล';
    button.setAttribute('aria-expanded', 'true');
  });

  target.addEventListener('hidden.bs.collapse', function() {
    button.textContent = 'แสดงข้อมูล';
    button.setAttribute('aria-expanded', 'false');
  });
});
</script>

<?php include 'footer.php'; ?>

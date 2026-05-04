<?php
require_once 'functions.php';
require_login();

$conn = db();
$flashMsg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_member_portal_logs') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $flashMsg = 'ไม่สามารถยืนยันคำขอได้ กรุณาลองใหม่อีกครั้ง';
    } else {
        if (clear_member_portal_log_entries()) {
            header('Location: member_usage_summary.php?msg=' . urlencode('ล้างประวัติการใช้งานสมาชิกเรียบร้อยแล้ว'));
            exit;
        }
        $flashMsg = 'ไม่สามารถล้างประวัติการใช้งานได้';
    }
}

include 'header.php';

$stats = fetch_member_portal_usage_stats($conn);
$periodStats = fetch_member_portal_period_stats();
$recentLogs = fetch_member_portal_recent_log_entries(20);
$csrfToken = csrf_token();

$latestLoginText = '-';
if (!empty($stats['latest_login_at'])) {
    $timestamp = strtotime((string)$stats['latest_login_at']);
    if ($timestamp !== false) {
        $latestLoginText = thai_date_format(date('Y-m-d', $timestamp)) . ' ' . date('H:i', $timestamp);
    }
}
?>
<style>
html,
body {
  font-family: 'Sarabun', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  background: #eff7f1;
  color: #14532d;
}

.usage-shell {
  max-width: 1240px;
}

.usage-hero,
.usage-panel,
.usage-card,
.usage-log-card {
  border: 1px solid #bbf7d0;
  border-radius: 1.25rem;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 16px 42px rgba(20, 83, 45, 0.08);
}

.usage-hero {
  background: linear-gradient(135deg, rgba(240, 253, 244, 0.98), rgba(236, 253, 245, 0.95));
}

.usage-badge {
  width: 3rem;
  height: 3rem;
  border-radius: 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #16a34a;
  color: #fff;
  box-shadow: 0 10px 24px rgba(22, 163, 74, 0.24);
  flex: 0 0 auto;
}

.usage-card {
  min-height: 100%;
  position: relative;
  padding: 1.2rem 1.25rem;
}

.usage-card .usage-label {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-bottom: .65rem;
  font-size: .98rem;
  font-weight: 600;
  color: #5f7465;
}

.usage-card .usage-value {
  font-size: 1.7rem;
  font-weight: 700;
  line-height: 1.2;
  color: #183524;
}

.usage-card .usage-sub {
  font-size: .9rem;
  color: #7b8d7f;
  margin-top: .25rem;
}

.usage-card .usage-icon {
  position: absolute;
  right: 1rem;
  top: 1rem;
  width: 2.5rem;
  height: 2.5rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: .9rem;
  background: #e8f5eb;
  color: #2f6e43;
}

.usage-log-card {
  overflow: hidden;
}

.usage-log-table th {
  color: #245c38;
  font-weight: 700;
  white-space: nowrap;
}

.usage-log-table td,
.usage-log-table th {
  border-color: #e7efea;
  vertical-align: middle;
}

.usage-pill {
  border: 1px solid #bbf7d0;
  background: #f8fdf8;
  color: #166534;
  border-radius: 999px;
  padding: .35rem .7rem;
  font-weight: 600;
}

@media (max-width: 576px) {

  .usage-hero,
  .usage-panel,
  .usage-card,
  .usage-log-card {
    border-radius: 1rem;
  }
}
</style>

<div class="container usage-shell my-4">
  <section class="usage-hero p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <span class="usage-badge">
          <i data-lucide="activity" aria-hidden="true"></i>
        </span>
        <div>
          <div class="text-uppercase text-success fw-semibold small mb-1">Member Usage</div>
          <h1 class="h3 fw-bold mb-1 text-success-emphasis">สรุปการใช้งานสมาชิก</h1>
          <div class="text-success">ข้อมูลการเข้าสู่ระบบของหน้า allmember.php จากไฟล์ JSON</div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <form method="post" class="d-inline"
          onsubmit="return confirm('ต้องการล้างประวัติการใช้งานสมาชิกทั้งหมดหรือไม่?');">
          <input type="hidden" name="action" value="clear_member_portal_logs">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
          <button type="submit" class="btn btn-outline-danger">
            <i data-lucide="trash-2" class="me-1" aria-hidden="true"></i>เคลียร์ข้อมูล
          </button>
        </form>
      </div>
    </div>
  </section>

  <?php if ($flashMsg !== ''): ?>
  <div class="alert alert-info border-0 shadow-sm mb-4">
    <i data-lucide="info" class="me-1" aria-hidden="true"></i><?php echo e($flashMsg); ?>
  </div>
  <?php endif; ?>

  <section class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
      <div class="usage-card">
        <span class="usage-icon"><i data-lucide="log-in" aria-hidden="true"></i></span>
        <div class="usage-label"><i data-lucide="hash" aria-hidden="true"></i>จำนวนครั้งที่เข้าสู่ระบบ</div>
        <div class="usage-value"><?php echo number_format((int)($stats['total_logins'] ?? 0)); ?></div>
        <div class="usage-sub">สะสมทั้งหมด</div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="usage-card">
        <span class="usage-icon"><i data-lucide="users" aria-hidden="true"></i></span>
        <div class="usage-label"><i data-lucide="user-check" aria-hidden="true"></i>สมาชิกไม่ซ้ำ</div>
        <div class="usage-value"><?php echo number_format((int)($stats['unique_members'] ?? 0)); ?></div>
        <div class="usage-sub">ที่เคยเข้าใช้งาน</div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="usage-card">
        <span class="usage-icon"><i data-lucide="calendar-days" aria-hidden="true"></i></span>
        <div class="usage-label"><i data-lucide="calendar-check" aria-hidden="true"></i>วันนี้</div>
        <div class="usage-value"><?php echo number_format((int)($stats['today_logins'] ?? 0)); ?></div>
        <div class="usage-sub">การเข้าสู่ระบบวันนี้</div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="usage-card">
        <span class="usage-icon"><i data-lucide="clock-3" aria-hidden="true"></i></span>
        <div class="usage-label"><i data-lucide="activity" aria-hidden="true"></i>ล่าสุด</div>
        <div class="usage-value" style="font-size:1.05rem; line-height:1.35;"><?php echo e($latestLoginText); ?></div>
        <div class="usage-sub"><?php echo e($stats['latest_member_name'] ?? '-'); ?></div>
      </div>
    </div>
  </section>

  <section class="row g-3 mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h2 class="h5 fw-bold mb-1 text-success-emphasis">
            <i data-lucide="calendar-range" class="me-1" aria-hidden="true"></i>สรุปตามช่วงเวลา
          </h2>
          <div class="text-success">แยกยอดการใช้งานตามวันนี้ เดือนนี้ และปีนี้</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="usage-card">
        <span class="usage-icon"><i data-lucide="calendar-check" aria-hidden="true"></i></span>
        <div class="usage-label"><i data-lucide="sun" aria-hidden="true"></i>วันนี้</div>
        <div class="usage-value"><?php echo number_format((int)($periodStats['today'] ?? 0)); ?></div>
        <div class="usage-sub"><?php echo e($periodStats['today_label'] ?? thai_date_format(date('Y-m-d'))); ?></div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="usage-card">
        <span class="usage-icon"><i data-lucide="calendar-days" aria-hidden="true"></i></span>
        <div class="usage-label"><i data-lucide="calendar" aria-hidden="true"></i>เดือนนี้</div>
        <div class="usage-value"><?php echo number_format((int)($periodStats['month'] ?? 0)); ?></div>
        <div class="usage-sub"><?php echo e($periodStats['month_label'] ?? thai_date_format(date('Y-m-d'))); ?></div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="usage-card">
        <span class="usage-icon"><i data-lucide="calendar-range" aria-hidden="true"></i></span>
        <div class="usage-label"><i data-lucide="calendar" aria-hidden="true"></i>ปีนี้</div>
        <div class="usage-value"><?php echo number_format((int)($periodStats['year'] ?? 0)); ?></div>
        <div class="usage-sub"><?php echo e($periodStats['year_label'] ?? ((int)date('Y') + 543)); ?></div>
      </div>
    </div>
  </section>

  <section class="usage-panel p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
      <div>
        <h2 class="h5 fw-bold mb-1 text-success-emphasis">
          <i data-lucide="history" class="me-1" aria-hidden="true"></i>ประวัติการเข้าดูข้อมูลล่าสุด
        </h2>
        <div class="text-success">อ่านจากไฟล์ JSON ของหน้า allmember.php</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="usage-pill">
          <?php echo number_format(count($recentLogs)); ?> รายการล่าสุด
        </span>
        <span class="usage-pill">
          <?php echo e($stats['latest_member_name'] ?? '-'); ?>
        </span>
      </div>
    </div>

    <div class="usage-log-card">
      <div class="table-responsive">
        <table class="table table-hover mb-0 usage-log-table">
          <thead>
            <tr>
              <th>เวลา</th>
              <th>สมาชิก</th>
              <th>กลุ่ม</th>
              <th>ชั้น</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($recentLogs)): ?>
            <?php foreach ($recentLogs as $log): ?>
            <tr>
              <td class="text-nowrap">
                <?php
                      $createdAt = (string)($log['created_at'] ?? '');
                      echo $createdAt !== '' ? htmlspecialchars(thai_date_format(substr($createdAt, 0, 10)) . ' ' . substr($createdAt, 11, 5)) : '-';
                    ?>
              </td>
              <td><?php echo htmlspecialchars($log['mem_fullname'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($log['mem_group'] ?? '-'); ?></td>
              <td>
                <?php
                      $classValue = (string)($log['mem_class'] ?? '');
                      echo htmlspecialchars($classValue === 'member' ? 'สมาชิก' : ($classValue === 'general' ? 'เกษตรกรทั่วไป' : '-'));
                    ?>
              </td>
              <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">ยังไม่มีประวัติการใช้งาน</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>

<?php include 'footer.php'; ?>
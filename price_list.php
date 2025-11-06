<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require ('db.php');
require_admin();

// Track DB errors locally
$db_error = null;

// Replace direct query with guarded execution
$rows = [];
try {
	if (isset($pdo) && $pdo instanceof PDO) {
		$rows = $pdo->query("SELECT pr_id, pr_year, pr_date, pr_number, pr_price, pr_saveby, pr_savedate
                     FROM tbl_price
                     ORDER BY pr_date DESC, pr_id DESC")->fetchAll();
	} else {
		$db_error = function_exists('db_error') ? db_error() : 'Database connection failed.';
	}
} catch (Throwable $e) {
	$db_error = 'Database connection failed.';
	if (function_exists('error_log')) { error_log('[price_list] ' . $e->getMessage()); }
}

$msg = flash();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการราคายาง</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: system-ui, Arial, sans-serif; background:#f7f7f7; padding:24px; }
    .box { max-width:1000px; margin:0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
    a.button, button.button { display:inline-block; padding:8px 12px; background:#2563eb; color:#fff; border:0; border-radius:6px; text-decoration:none; cursor:pointer; }
    a.button:hover, button.button:hover { background:#1d4ed8; }
    table { width:100%; border-collapse:collapse; margin-top:14px; }
    th, td { padding:8px 10px; border-bottom:1px solid #e5e7eb; text-align:left; }
    th { background:#f3f4f6; }
    .right { text-align:right; }
    .muted { color:#6b7280; font-size:12px; }
    .danger { background:#dc2626; }
    .danger:hover { background:#b91c1c; }
    .toolbar { display:flex; gap:8px; align-items:center; justify-content:space-between; }
    .msg { background:#ecfeff; color:#0e7490; padding:8px 10px; border-radius:6px; margin-top:10px; }
    /* Error message style */
    .msg.error { background:#fee2e2; color:#b91c1c; }
  </style>
</head>
<body>
  <div class="box">
    <div class="toolbar">
      <h1>จัดการราคายาง</h1>
      <div>
        <a class="button" href="price_form.php">+ เพิ่มราคา</a>
        <a class="button" href="dashboard.php">กลับหน้าหลัก</a>
      </div>
    </div>

    <?php if ($msg): ?><div class="msg"><?php echo e($msg); ?></div><?php endif; ?>
    <?php if ($db_error): ?><div class="msg error"><?php echo e($db_error); ?></div><?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>ปี</th>
          <th>วันที่</th>
          <th>เลขที่</th>
          <th class="right">ราคา</th>
          <th>บันทึกโดย</th>
          <th>บันทึกเมื่อ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($db_error): ?>
        <tr><td colspan="8" class="muted">ไม่สามารถโหลดข้อมูลจากฐานข้อมูล</td></tr>
      <?php elseif (!$rows): ?>
        <tr><td colspan="8" class="muted">ยังไม่มีข้อมูล</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?php echo (int)$r['pr_id']; ?></td>
          <td><?php echo e($r['pr_year']); ?></td>
          <td><?php echo e($r['pr_date']); ?></td>
          <td><?php echo e($r['pr_number']); ?></td>
          <td class="right"><?php echo number_format((float)$r['pr_price'], 2); ?></td>
          <td><?php echo e($r['pr_saveby']); ?></td>
          <td><?php echo e($r['pr_savedate']); ?></td>
          <td>
            <a class="button" href="price_form.php?id=<?php echo (int)$r['pr_id']; ?>">แก้ไข</a>
            <form action="price_delete.php" method="post" style="display:inline" onsubmit="return confirm('ยืนยันการลบรายการนี้?');">
              <input type="hidden" name="pr_id" value="<?php echo (int)$r['pr_id']; ?>">
              <button type="submit" class="button danger">ลบ</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>

<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require __DIR__ . '/db.php'; // expects $pdo (PDO) ready

// Simple auth gate (adjust / remove as needed)
if (!isset($_SESSION['user_level'])) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

if (empty($_SESSION['csrf_token'])) {
  try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
  catch(Throwable $e) { $_SESSION['csrf_token'] = bin2hex(substr(str_shuffle('abcdef0123456789'),0,32)); }
}
$csrf = $_SESSION['csrf_token'];

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$msg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
    $errors[] = 'CSRF token ผิดพลาด';
  } else {
    $pr_year    = trim($_POST['pr_year'] ?? '');
    $pr_date    = trim($_POST['pr_date'] ?? '');
    $pr_number  = trim($_POST['pr_number'] ?? '');
    $pr_price   = trim($_POST['pr_price'] ?? '');
    $pr_saveby  = trim($_POST['pr_saveby'] ?? '');
    $pr_savedate= date('Y-m-d');
    if ($pr_year === '' || !ctype_digit($pr_year)) $errors[] = 'ปี ไม่ถูกต้อง';
    if ($pr_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$pr_date)) $errors[] = 'วันที่ ไม่ถูกต้อง (YYYY-MM-DD)';
    if ($pr_number === '') $errors[] = 'เลขที่ ต้องระบุ';
    if ($pr_price === '' || !is_numeric($pr_price)) $errors[] = 'ราคา ไม่ถูกต้อง';
    if ($pr_saveby === '') $errors[] = 'ผู้บันทึก ต้องระบุ';

    if (!$errors) {
      try {
        $stmt = $pdo->prepare("INSERT INTO tbl_price (pr_year, pr_date, pr_number, pr_price, pr_saveby, pr_savedate)
                               VALUES (?,?,?,?,?,?)");
        $stmt->execute([
          $pr_year,
          $pr_date,
            $pr_number,
          number_format((float)$pr_price, 2, '.', ''),
          $pr_saveby,
          $pr_savedate
        ]);
        $msg = 'บันทึกข้อมูลสำเร็จ';
      } catch (Throwable $e) {
        if (function_exists('error_log')) error_log('[price_list insert] '.$e->getMessage());
        $errors[] = 'บันทึกข้อมูลล้มเหลว';
      }
    }
  }
}

$rows = [];
try {
  $q = $pdo->query("SELECT pr_id, pr_year, pr_date, pr_number, pr_price, pr_saveby, pr_savedate
                    FROM tbl_price
                    ORDER BY pr_date DESC, pr_id DESC");
  $rows = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
  if (function_exists('error_log')) error_log('[price_list select] '.$e->getMessage());
  $errors[] = 'ดึงข้อมูลล้มเหลว';
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>รายการราคายาง</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body { font-family: system-ui, Arial, sans-serif; margin:20px; background:#f6fdf9; color:#1e293b;}
h1 { margin:0 0 16px; }
form, table { background:#fff; border:1px solid #d1fae5; border-radius:10px; padding:16px; }
label { display:block; margin-bottom:8px; font-size:14px; }
input { width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; }
.grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); }
button { padding:10px 16px; background:#059669; color:#fff; border:none; border-radius:8px; cursor:pointer; }
button:hover { background:#047857; }
.msg { margin:12px 0; padding:10px 14px; border-radius:8px; font-size:14px; }
.msg.ok { background:#dcfce7; color:#065f46; border:1px solid #86efac; }
.msg.err { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
table { width:100%; border-collapse:collapse; margin-top:24px; }
th,td { padding:8px 10px; text-align:left; border-bottom:1px solid #e2e8f0; font-size:14px; }
th { background:#ecfdf5; color:#065f46; }
td.num { text-align:right; font-variant-numeric: tabular-nums; }
.empty { text-align:center; color:#64748b; padding:24px 0; }
.actions { display:flex; gap:6px; }
.small { font-size:12px; color:#64748b; margin-top:4px; }
</style>
</head>
<body>
<h1>รายการราคายาง</h1>

<?php if ($msg): ?>
  <div class="msg ok"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="msg err"><?php echo e(implode(' | ', $errors)); ?></div>
<?php endif; ?>

<form method="post" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
  <input type="hidden" name="action" value="add">
  <div class="grid">
    <div>
      <label>ปี (พ.ศ.) <input name="pr_year" required pattern="\d{4}" value="<?php echo e(date('Y')+543); ?>"></label>
    </div>
    <div>
      <label>วันที่ <input name="pr_date" type="date" required value="<?php echo e(date('Y-m-d')); ?>"></label>
    </div>
    <div>
      <label>เลขที่ <input name="pr_number" required placeholder="รอบที่ 1"></label>
    </div>
    <div>
      <label>ราคา (บาท) <input name="pr_price" required pattern="\d+(\.\d{1,2})?" placeholder="25.32"></label>
    </div>
    <div>
      <label>ผู้บันทึก <input name="pr_saveby" required value="<?php echo e($_SESSION['fullname'] ?? ($_SESSION['username'] ?? '')); ?>"></label>
    </div>
  </div>
  <div class="small">บันทึกวันนี้: <?php echo e(date('Y-m-d')); ?></div>
  <div style="margin-top:12px;">
    <button type="submit">บันทึกราคา</button>
  </div>
</form>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>ปี</th>
      <th>วันที่</th>
      <th>เลขที่</th>
      <th>ราคา</th>
      <th>ผู้บันทึก</th>
      <th>บันทึกเมื่อ</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="empty">ยังไม่มีข้อมูล</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?php echo (int)$r['pr_id']; ?></td>
        <td><?php echo e($r['pr_year']); ?></td>
        <td><?php echo e($r['pr_date']); ?></td>
        <td><?php echo e($r['pr_number']); ?></td>
        <td class="num"><?php echo number_format((float)$r['pr_price'], 2); ?></td>
        <td><?php echo e($r['pr_saveby']); ?></td>
        <td><?php echo e($r['pr_savedate']); ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
</body>
</html>

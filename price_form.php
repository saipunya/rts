<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require __DIR__ . '/db.php';
require_admin();

// Add DB error tracking
$db_error = null;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$data = [
  'pr_year' => '',
  'pr_date' => '',
  'pr_number' => '',
  'pr_price' => '',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = isset($_POST['pr_id']) ? (int)$_POST['pr_id'] : 0;
  $isEdit = $id > 0;

  $data['pr_year'] = trim($_POST['pr_year'] ?? '');
  $data['pr_date'] = trim($_POST['pr_date'] ?? '');
  $data['pr_number'] = trim($_POST['pr_number'] ?? '');
  $data['pr_price'] = trim($_POST['pr_price'] ?? '');

  if ($data['pr_year'] === '' || !ctype_digit($data['pr_year'])) {
    $errors['pr_year'] = 'กรุณากรอกปีเป็นตัวเลข';
  }
  if ($data['pr_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['pr_date'])) {
    $errors['pr_date'] = 'กรุณาเลือกวันที่';
  }
  if ($data['pr_number'] === '') {
    $errors['pr_number'] = 'กรุณากรอกเลขที่';
  }
  if ($data['pr_price'] === '' || !is_numeric($data['pr_price'])) {
    $errors['pr_price'] = 'กรุณากรอกราคาเป็นตัวเลข';
  }

  if (!$errors) {
    // Guard DB usage
    if (!isset($pdo) || !($pdo instanceof PDO)) {
      $db_error = function_exists('db_error') ? db_error() : 'Database connection failed.';
      $errors['__db'] = 'ไม่สามารถเชื่อมต่อฐานข้อมูล';
    } else {
      $saveby = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'system');
      try {
        if ($isEdit) {
          $stmt = $pdo->prepare("UPDATE tbl_price
                             SET pr_year = ?, pr_date = ?, pr_number = ?, pr_price = ?, pr_saveby = ?, pr_savedate = CURDATE()
                             WHERE pr_id = ?");
          $stmt->execute([
            (int)$data['pr_year'],
            $data['pr_date'],
            $data['pr_number'],
            (float)$data['pr_price'],
            $saveby,
            $id
          ]);
          flash('แก้ไขข้อมูลสำเร็จ');
        } else {
          $stmt = $pdo->prepare("INSERT INTO tbl_price (pr_year, pr_date, pr_number, pr_price, pr_saveby, pr_savedate)
                             VALUES (?, ?, ?, ?, ?, CURDATE())");
          $stmt->execute([
            (int)$data['pr_year'],
            $data['pr_date'],
            $data['pr_number'],
            (float)$data['pr_price'],
            $saveby
          ]);
          flash('บันทึกข้อมูลสำเร็จ');
        }
        header('Location: price_list.php'); exit;
      } catch (Throwable $e) {
        $db_error = 'Database connection failed.';
        if (function_exists('error_log')) { error_log('[price_form] ' . $e->getMessage()); }
        $errors['__db'] = 'เกิดข้อผิดพลาดเกี่ยวกับฐานข้อมูล';
      }
    }
  }
} else if ($isEdit) {
  // Guard DB usage when loading existing record
  if (isset($pdo) && $pdo instanceof PDO) {
    try {
      $stmt = $pdo->prepare("SELECT pr_year, pr_date, pr_number, pr_price FROM tbl_price WHERE pr_id = ?");
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if (!$row) { header('Location: price_list.php'); exit; }
      $data = [
        'pr_year' => (string)$row['pr_year'],
        'pr_date' => (string)$row['pr_date'],
        'pr_number' => (string)$row['pr_number'],
        'pr_price' => (string)$row['pr_price'],
      ];
    } catch (Throwable $e) {
      $db_error = 'Database connection failed.';
      if (function_exists('error_log')) { error_log('[price_form] ' . $e->getMessage()); }
      // Keep defaults and show error message
    }
  } else {
    $db_error = function_exists('db_error') ? db_error() : 'Database connection failed.';
  }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title><?php echo $isEdit ? 'แก้ไขราคา' : 'เพิ่มราคา'; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: system-ui, Arial, sans-serif; background:#f7f7f7; padding:24px; }
    .box { max-width:640px; margin:0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
    label { display:block; margin:10px 0 6px; }
    input { width:100%; padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; }
    .error { color:#dc2626; font-size:12px; margin-top:4px; }
    .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .actions { margin-top:16px; display:flex; gap:8px; }
    a.button, button.button { display:inline-block; padding:8px 12px; background:#2563eb; color:#fff; border:0; border-radius:6px; text-decoration:none; cursor:pointer; }
    a.button:hover, button.button:hover { background:#1d4ed8; }
    .secondary { background:#6b7280; }
    .secondary:hover { background:#4b5563; }
    /* Add message styles to surface DB errors */
    .msg { background:#ecfeff; color:#0e7490; padding:8px 10px; border-radius:6px; margin-bottom:10px; }
    .msg.error { background:#fee2e2; color:#b91c1c; }
  </style>
</head>
<body>
  <div class="box">
    <h1><?php echo $isEdit ? 'แก้ไขราคา' : 'เพิ่มราคา'; ?></h1>

    <?php if ($db_error): ?><div class="msg error"><?php echo e($db_error); ?></div><?php endif; ?>
    <?php if (isset($errors['__db'])): ?><div class="error"><?php echo e($errors['__db']); ?></div><?php endif; ?>

    <form method="post">
      <?php if ($isEdit): ?>
        <input type="hidden" name="pr_id" value="<?php echo (int)$id; ?>">
      <?php endif; ?>

      <div class="row">
        <div>
          <label>ปี</label>
          <input type="number" name="pr_year" value="<?php echo e($data['pr_year']); ?>" required>
          <?php if (isset($errors['pr_year'])): ?><div class="error"><?php echo e($errors['pr_year']); ?></div><?php endif; ?>
        </div>
        <div>
          <label>วันที่</label>
          <input type="date" name="pr_date" value="<?php echo e($data['pr_date']); ?>" required>
          <?php if (isset($errors['pr_date'])): ?><div class="error"><?php echo e($errors['pr_date']); ?></div><?php endif; ?>
        </div>
      </div>

      <label>เลขที่</label>
      <input type="text" name="pr_number" value="<?php echo e($data['pr_number']); ?>" required>
      <?php if (isset($errors['pr_number'])): ?><div class="error"><?php echo e($errors['pr_number']); ?></div><?php endif; ?>

      <label>ราคา</label>
      <input type="number" step="0.01" name="pr_price" value="<?php echo e($data['pr_price']); ?>" required>
      <?php if (isset($errors['pr_price'])): ?><div class="error"><?php echo e($errors['pr_price']); ?></div><?php endif; ?>

      <div class="actions">
        <button type="submit" class="button">บันทึก</button>
        <a class="button secondary" href="price_list.php">ยกเลิก</a>
      </div>
    </form>
  </div>
</body>
</html>

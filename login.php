<?php
require_once __DIR__ . '/functions.php';
$db = db();

$errors = [];
$msg = $_GET['msg'] ?? '';
$csrf = csrf_token();

// redirect if already logged-in
if (!empty($_SESSION['user_id'])) {
	header('Location: index.php');
	exit;
}

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_check($_POST['csrf_token'] ?? '')) {
		$errors[] = 'โทเค็นไม่ถูกต้อง';
	} else {
		$username = trim((string)($_POST['username'] ?? ''));
		$password = (string)($_POST['password'] ?? '');
		if ($username === '' || $password === '') {
			$errors[] = 'กรอกชื่อผู้ใช้และรหัสผ่าน';
		} else {
			$row = null;

			 // updated: correct columns for tbl_user
			if ($st = $db->prepare('SELECT user_id, user_username, user_password, user_fullname, user_level, user_status FROM tbl_user WHERE user_username = ? LIMIT 1')) {
				$st->bind_param('s', $username);
				$st->execute();
				$res = $st->get_result();
				$row = $res->fetch_assoc() ?: null;
				$st->close();
			}

			// fallback to users
			if (!$row && ($st = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1'))) {
				$st->bind_param('s', $username);
				$st->execute();
				$res = $st->get_result();
				$row = $res->fetch_assoc() ?: null;
				$st->close();
			}

			if ($row) {
				 // updated: map real columns
				$hash = $row['user_password'] ?? ($row['password'] ?? '');
				$uid  = $row['user_id'] ?? ($row['id'] ?? null);
				$uname_db = $row['user_username'] ?? ($row['username'] ?? $username);
				$fullname = $row['user_fullname'] ?? ($row['fullname'] ?? $uname_db);
				$level = $row['user_level'] ?? ($row['role'] ?? 'user');
				$status = $row['user_status'] ?? ($row['status'] ?? 'active');

				// added: status check
				$active = in_array(strtolower($status), ['active','1','enabled','true'], true);

				if (!$active) {
					$errors[] = 'บัญชีถูกระงับ';
				} elseif ($hash !== '' && password_verify($password, $hash)) {
					// set session keys expected by the rest of the app
					$_SESSION['user_id'] = (int)$uid;
					$_SESSION['user_username'] = (string)$uname_db;
					$_SESSION['user_fullname'] = (string)$fullname;
					$_SESSION['user_level'] = (string)$level;
					$_SESSION['user_status'] = (string)$status;

					$redirect = $_GET['redirect'] ?? 'dashboard.php';
					if (strpos($redirect, '/') === 0) $redirect = ltrim($redirect, '/'); // simple safety
					header('Location: ' . $redirect);
					exit;
				} else {
					$errors[] = 'รหัสผ่านไม่ถูกต้อง';
				}
			} else {
				$errors[] = 'ไม่พบบัญชีผู้ใช้';
			}
		}
	}
}

// view
include 'header.php';
?>
<style>
.login-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 24px rgba(0,0,0,0.08);
  padding: 2.5rem 1rem 2rem 2rem;
  margin-top: 60px;
}
.login-title {
  font-weight: 700;
  letter-spacing: 1px;
  color: #2d3748;
}
.login-btns .btn {
  min-width: 120px;
}
</style>
<div class="container" style="max-width:480px;">
  <div class="login-card">
    <div class="row mb-4">
      <div class="col-12 text-center">
        <h3 class="login-title mb-0">เข้าสู่ระบบ</h3>
      </div>
    </div>
    <?php if ($msg): ?>
      <div class="alert alert-info text-center"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger text-center"><?php echo e(implode(' | ', $errors)); ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
      <div class="mb-3">
        <label class="form-label">ชื่อผู้ใช้</label>
        <input class="form-control" name="username" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label">รหัสผ่าน</label>
        <input class="form-control" type="password" name="password" required>
      </div>
      <div class="d-flex justify-content-between login-btns">
        <button class="btn btn-primary" type="submit">เข้าสู่ระบบ</button>
        <a href="index.php" class="btn btn-outline-secondary">กลับหน้าหลัก</a>
      </div>
    </form>
  </div>
</div>
<?php include 'footer.php'; ?>
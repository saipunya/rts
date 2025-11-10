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

			// try tbl_user
			if ($st = $db->prepare('SELECT * FROM tbl_user WHERE user_name = ? LIMIT 1')) {
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
				// detect columns
				$hash = $row['user_pass'] ?? ($row['user_password'] ?? ($row['password'] ?? ''));
				$uid  = $row['user_id'] ?? ($row['id'] ?? null);
				$uname= $row['user_name'] ?? ($row['username'] ?? $username);
				$role = $row['user_role'] ?? ($row['role'] ?? null);

				if ($hash !== '' && password_verify($password, $hash)) {
					$_SESSION['user_id'] = (int)$uid;
					$_SESSION['user_name'] = (string)$uname;
					if ($role !== null) $_SESSION['user_role'] = (string)$role;

					$redirect = $_GET['redirect'] ?? 'index.php';
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
<div class="container" style="max-width:480px;margin-top:60px;">
	<h3 class="mb-3">เข้าสู่ระบบ</h3>
	<?php if ($msg): ?>
		<div class="alert alert-info"><?php echo e($msg); ?></div>
	<?php endif; ?>
	<?php if ($errors): ?>
		<div class="alert alert-danger"><?php echo e(implode(' | ', $errors)); ?></div>
	<?php endif; ?>

	<form method="post" autocomplete="off">
		<input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
		<div class="mb-3">
			<label class="form-label">ชื่อผู้ใช้</label>
			<input class="form-control" name="username" required>
		</div>
		<div class="mb-3">
			<label class="form-label">รหัสผ่าน</label>
			<input class="form-control" type="password" name="password" required>
		</div>
		<button class="btn btn-primary w-100" type="submit">เข้าสู่ระบบ</button>
	</form>
</div>
<?php include 'footer.php'; ?>
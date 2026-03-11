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
					$errors[] = 'บัญชีถูกระงับการใช้งาน';
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Thai:wght@400;600;700&display=swap" rel="stylesheet">
<style>
html, body {
  font-family: 'Noto Serif Thai', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  font-size: 16px;
  font-weight: 300;
}

.login-card,
.form-control,
.form-select,
.form-label,
.btn,
.btn-sm,
.nav-link,
.alert,
.badge {
  font-family: 'Noto Serif Thai', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  font-size: 16px;
  font-weight: 300;
}

.small,
.form-text {
  font-size: 14px !important;
  font-weight: 300 !important;
}

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
.form-control {
  font-size: 1.2rem;
}
.form-label {
  font-size: 1.2rem;
}

/* Enhanced Responsive Design */
@media (max-width: 992px) {
  .login-card {
    margin: 40px 1rem 0;
    padding: 2rem 1.5rem;
  }
  
  .login-title {
    font-size: 1.5rem;
  }
}

@media (max-width: 768px) {
  .login-card {
    margin: 20px 0.5rem 0;
    padding: 1.5rem 1rem;
    border-radius: 12px;
  }
  
  .login-title {
    font-size: 1.3rem;
    margin-bottom: 1rem;
  }
  
  .row.mb-4 {
    margin-bottom: 1rem !important;
  }
  
  .mb-3 {
    margin-bottom: 1rem !important;
  }
  
  .mb-4 {
    margin-bottom: 1.5rem !important;
  }
  
  .form-label {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
  }
  
  .form-control {
    font-size: 1.2rem;
    padding: 0.75rem 1rem;
    min-height: 48px;
    border-radius: 8px;
  }
  
  .btn {
    min-height: 48px;
    font-size: 1.2rem;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
  }
  
  .alert {
    font-size: 1.2rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border-radius: 8px;
  }
  
  .text-center {
    text-align: center !important;
  }
}

@media (max-width: 576px) {
  .login-card {
    margin: 10px 0.25rem 0;
    padding: 1.25rem 0.75rem;
    border-radius: 8px;
  }
  
  .login-title {
    font-size: 1.2rem;
  }
  
  .container-fluid {
    padding: 0 0.5rem;
  }
  
  .form-label {
    font-size: 1.2rem;
  }
  
  .form-control {
    font-size: 1.2rem;
    padding: 0.6rem 0.8rem;
    min-height: 44px;
  }
  
  .btn {
    min-height: 44px;
    font-size: 1.2rem;
    padding: 0.6rem 1.5rem;
  }
  
  .alert {
    font-size: 1.2rem;
    padding: 0.6rem 0.8rem;
  }
  
  .row.mb-4 {
    margin-bottom: 0.75rem !important;
  }
  
  .mb-3 {
    margin-bottom: 0.75rem !important;
  }
  
  .mb-4 {
    margin-bottom: 1.25rem !important;
  }
}

/* Landscape orientation */
@media (max-width: 768px) and (orientation: landscape) {
  .login-card {
    margin-top: 10px;
    padding: 1rem 1.5rem;
  }
  
  .login-title {
    font-size: 1.2rem;
    margin-bottom: 0.75rem;
  }
  
  .mb-3 {
    margin-bottom: 0.5rem !important;
  }
  
  .mb-4 {
    margin-bottom: 1rem !important;
  }
}

/* Extra small screens */
@media (max-width: 480px) {
  .login-card {
    padding: 1rem 0.5rem;
  }
  
  .login-title {
    font-size: 1.1rem;
  }
  
  .form-control {
    padding: 0.5rem 0.7rem;
  }
  
  .btn {
    padding: 0.5rem 1rem;
  }
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {
  .form-control,
  .btn {
    min-height: 44px;
  }
  
  .btn:hover {
    transform: none;
  }
  
  .form-control:focus {
    transform: none;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .login-card {
    border: 2px solid #000;
  }
  
  .form-control {
    border: 2px solid #000;
  }
  
  .btn {
    border: 2px solid #000;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .login-card,
  .btn,
  .form-control {
    transition: none;
  }
}
</style>

  <div class="login-card container-fluid">
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
      <div class="text-center">
        <button class="btn btn-primary" type="submit">เข้าสู่ระบบ</button>
      </div>
    </form>
  </div>
<?php include 'footer.php'; ?>
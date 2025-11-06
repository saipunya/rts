<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล (ปรับตามสภาพแวดล้อมของคุณ)
require('functions.php');

$errors = [];
$success = null;

// ค่าดีฟอลต์ของฟอร์ม
$username = '';
$fullname = '';
$level = 'user';
$status = 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['user_username'] ?? '');
	$password = $_POST['user_password'] ?? '';
	$confirm  = $_POST['confirm_password'] ?? '';
	$fullname = trim($_POST['user_fullname'] ?? '');
	$level    = trim($_POST['user_level'] ?? 'user');
	$status   = trim($_POST['user_status'] ?? 'active');

	// ตรวจสอบความถูกต้องของข้อมูล
	if ($username === '') $errors[] = 'กรุณากรอกชื่อผู้ใช้';
	if ($fullname === '') $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
	if ($password === '') $errors[] = 'กรุณากรอกรหัสผ่าน';
	if ($password !== '' && strlen($password) < 6) $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
	if ($password !== $confirm) $errors[] = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
	if ($level === '') $errors[] = 'กรุณาเลือกสิทธิ์ผู้ใช้';
	if ($status === '') $errors[] = 'กรุณาเลือกสถานะ';

	if (!$errors) {
		if ($mysqli->connect_errno) {
			$errors[] = 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $mysqli->connect_error;
		} else {
			$mysqli->set_charset('utf8mb4');

			// ตรวจสอบชื่อผู้ใช้ซ้ำ
			$stmt = $mysqli->prepare('SELECT user_id FROM tbl_user WHERE user_username = ? LIMIT 1');
			if ($stmt) {
				$stmt->bind_param('s', $username);
				$stmt->execute();
				$stmt->store_result();
				if ($stmt->num_rows > 0) {
					$errors[] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
				}
				$stmt->close();
			} else {
				$errors[] = 'ไม่สามารถเตรียมคำสั่งตรวจสอบผู้ใช้ได้';
			}

			// บันทึกข้อมูลเมื่อไม่มีข้อผิดพลาด
			if (!$errors) {
				$hash = password_hash($password, PASSWORD_BCRYPT);
				$stmt = $mysqli->prepare('INSERT INTO tbl_user (user_username, user_password, user_fullname, user_level, user_status) VALUES (?, ?, ?, ?, ?)');
				if ($stmt) {
					$stmt->bind_param('sssss', $username, $hash, $fullname, $level, $status);
					if ($stmt->execute()) {
						$success = 'สมัครสมาชิกสำเร็จ';
						// เคลียร์ค่าฟอร์ม
						$username = '';
						$fullname = '';
						$level = 'user';
						$status = 'active';
					} else {
						$errors[] = 'ไม่สามารถบันทึกข้อมูลได้: ' . $stmt->error;
					}
					$stmt->close();
				} else {
					$errors[] = 'ไม่สามารถเตรียมคำสั่งบันทึกข้อมูลได้';
				}
			}

			$mysqli->close();
		}
	}
}

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
	<meta charset="utf-8">
	<title>สมัครสมาชิก</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		body { font-family: system-ui, Arial, sans-serif; background:#f7f7f7; padding:24px; }
		.container { max-width: 420px; margin: 0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
		h1 { font-size: 20px; margin: 0 0 16px; }
		label { display:block; margin:12px 0 6px; font-weight:600; }
		input, select { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; }
		button { margin-top:16px; width:100%; padding:10px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; }
		button:hover { background:#1d4ed8; }
		.alert { padding:10px 12px; border-radius:6px; margin-bottom:12px; }
		.alert-error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
		.alert-success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
		.small { color:#666; font-size:12px; }
	</style>
</head>
<body>
	<div class="container">
		<h1>สมัครสมาชิก</h1>

		<?php if ($errors): ?>
			<div class="alert alert-error">
				<ul style="margin:0 0 0 16px; padding:0;">
					<?php foreach ($errors as $err): ?>
						<li><?php echo e($err); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ($success): ?>
			<div class="alert alert-success"><?php echo e($success); ?></div>
		<?php endif; ?>

		<form method="post" action="">
			<label for="user_username">ชื่อผู้ใช้</label>
			<input type="text" id="user_username" name="user_username" required maxlength="255" value="<?php echo e($username); ?>">

			<label for="user_fullname">ชื่อ-นามสกุล</label>
			<input type="text" id="user_fullname" name="user_fullname" required maxlength="255" value="<?php echo e($fullname); ?>">

			<label for="user_password">รหัสผ่าน</label>
			<input type="password" id="user_password" name="user_password" required minlength="6" maxlength="255">

			<label for="confirm_password">ยืนยันรหัสผ่าน</label>
			<input type="password" id="confirm_password" name="confirm_password" required minlength="6" maxlength="255">

			<label for="user_level">สิทธิ์ผู้ใช้</label>
			<select id="user_level" name="user_level" required>
				<option value="user" <?php echo $level === 'user' ? 'selected' : ''; ?>>ผู้ใช้ (user)</option>
				<option value="admin" <?php echo $level === 'admin' ? 'selected' : ''; ?>>ผู้ดูแล (admin)</option>
			</select>

			<label for="user_status">สถานะ</label>
			<select id="user_status" name="user_status" required>
				<option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>เปิดใช้งาน (active)</option>
				<option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>ปิดใช้งาน (inactive)</option>
			</select>

			<button type="submit">สมัครสมาชิก</button>
			<p class="small">หมายเหตุ: รหัสผ่านจะถูกเก็บแบบเข้ารหัส (bcrypt)</p>
		</form>
	</div>
</body>
</html>

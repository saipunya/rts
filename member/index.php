<?php
require_once __DIR__ . '/../config.php';

// Fetch all members
$res = $mysqli->query("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_class, mem_saveby, mem_savedate FROM tbl_member ORDER BY mem_id DESC");
$members = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>Members</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:system-ui,Arial,sans-serif;max-width:1000px;margin:20px auto;padding:0 12px}
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #ddd;padding:8px}
        th{background:#f5f5f5;text-align:left}
        .actions{white-space:nowrap}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin:12px 0}
        a.button, button{background:#0d6efd;color:#fff;border:0;padding:8px 12px;border-radius:4px;text-decoration:none;cursor:pointer}
        a.button.secondary{background:#6c757d}
        form{display:inline}
    </style>
</head>
<body>
    <div class="topbar">
        <h2>รายการสมาชิก (tbl_member)</h2>
        <a class="button" href="create.php">เพิ่มสมาชิก</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>กลุ่ม</th>
                <th>หมายเลข</th>
                <th>ชื่อ-นามสกุล</th>
                <th>ชั้น</th>
                <th>บันทึกโดย</th>
                <th>วันที่บันทึก</th>
                <th>การกระทำ</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$members): ?>
            <tr><td colspan="8" style="text-align:center;color:#777">ไม่มีข้อมูล</td></tr>
        <?php else: foreach ($members as $m): ?>
            <tr>
                <td><?= e($m['mem_id']) ?></td>
                <td><?= e($m['mem_group']) ?></td>
                <td><?= e($m['mem_number']) ?></td>
                <td><?= e($m['mem_fullname']) ?></td>
                <td><?= e($m['mem_class']) ?></td>
                <td><?= e($m['mem_saveby']) ?></td>
                <td><?= e($m['mem_savedate']) ?></td>
                <td class="actions">
                    <a class="button secondary" href="edit.php?id=<?= urlencode($m['mem_id']) ?>">แก้ไข</a>
                    <form method="post" action="delete.php" onsubmit="return confirm('ลบข้อมูลนี้หรือไม่?');">
                        <input type="hidden" name="id" value="<?= e($m['mem_id']) ?>">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <button type="submit" style="background:#dc3545">ลบ</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</body>
</html>

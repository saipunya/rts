
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
      <label>ผู้บันทึก <input name="pr_saveby" required value="<?php echo e($default_saveby); ?>"></label>
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

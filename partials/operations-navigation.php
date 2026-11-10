<?php
if (!function_exists('rts_can')) { http_response_code(404); exit; }
?>
<a href="index.php" class="topbar-link<?php echo nav_active('index.php'); ?>"><i data-lucide="home" class="me-1" aria-hidden="true"></i>หน้าหลัก</a>
<?php foreach (['placement','weighing','approval','deductions','receipts'] as $permissionNavKey): $permissionNavItem=rts_permission_catalog()[$permissionNavKey]; if (!rts_can($permissionNavKey)) continue; ?>
<a href="<?php echo e($permissionNavItem['page']); ?>" class="topbar-link<?php echo nav_active($permissionNavItem['page']); ?>"><i data-lucide="<?php echo e($permissionNavItem['icon']); ?>" class="me-1" aria-hidden="true"></i><?php echo e($permissionNavItem['label']); ?></a>
<?php endforeach; ?>
<details class="management-menu"><summary class="topbar-link<?php echo nav_active(['prices.php','price_form.php','collection_audit.php','collection_history.php','cancel_collection.php','dashboard.php','collection_analysis.php','report_rubber.php','members.php','member_form.php','users.php','user_form.php']); ?>"><i data-lucide="settings" class="me-1" aria-hidden="true"></i>จัดการระบบ <span aria-hidden="true">▾</span></summary>
<nav class="management-menu-items" aria-label="จัดการระบบ">
<?php if (is_admin()): ?>
<a href="price_form.php?action=create" class="topbar-link<?php echo nav_active(['prices.php','price_form.php']); ?>"><i data-lucide="coins" class="me-1" aria-hidden="true"></i>กำหนดราคายาง</a>
<a href="collection_audit.php" class="topbar-link<?php echo nav_active(['collection_audit.php','collection_history.php']); ?>"><i data-lucide="history" class="me-1" aria-hidden="true"></i>ตรวจสอบประวัติ</a>
<a href="cancel_collection.php" class="topbar-link<?php echo nav_active('cancel_collection.php'); ?>"><i data-lucide="ban" class="me-1" aria-hidden="true"></i>ยกเลิกรายการ</a>
<a href="users.php" class="topbar-link<?php echo nav_active(['users.php','user_form.php']); ?>"><i data-lucide="shield" class="me-1" aria-hidden="true"></i>บัญชีผู้ใช้ / สิทธิ์เมนู</a>
<?php endif; ?>
<?php foreach (rts_admin_navigation_catalog() as $permissionNavKey=>$permissionNavItem): if (!is_admin()) continue; ?>
<a href="<?php echo e($permissionNavItem['page']); ?>" class="topbar-link<?php echo nav_active($permissionNavItem['page']); ?>"><i data-lucide="<?php echo e($permissionNavItem['icon']); ?>" class="me-1" aria-hidden="true"></i><?php echo e($permissionNavItem['label']); ?></a>
<?php endforeach; ?>
<a href="logout.php" class="topbar-link text-danger fw-semibold"><i data-lucide="log-out" class="me-1" aria-hidden="true"></i>ออกจากระบบ</a>
</nav></details>

<?php
// Compatibility for bookmarked links after removal of the test mode.
require_once __DIR__ . '/functions.php';
require_login();
require_admin();
unset($_SESSION['demo_test_sale_date'], $_SESSION['demo_test_user_id']);
header('Location: manage_wang.php');
exit;

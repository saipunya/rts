<?php
// Run with: php tests/weighing_integration.php
// Creates an isolated database using local db_config.php and always removes it.
require_once __DIR__ . '/../functions.php';
require __DIR__ . '/../db_config.php';
$testDb = new mysqli($db_host, $db_user, $db_pass);
$name = 'rts_weighing_test_' . bin2hex(random_bytes(5));
$testDb->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
$testDb->select_db($name);
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function expect_failure(callable $operation, string $message): void {
    try { $operation(); } catch (Throwable $e) { echo "PASS: $message\n"; return; }
    throw new RuntimeException('Expected rejection: ' . $message);
}
try {
    expect_failure(fn() => normalize_rubber_deductions([]), 'Reject all deduction fields left blank');
    expect_failure(fn() => normalize_rubber_deductions(['ru_hoon'=>'  ']), 'Whitespace does not confirm deductions');
    expect_failure(fn() => normalize_rubber_deductions(['ru_loan'=>'-1']), 'Reject negative deduction');
    $zero = normalize_rubber_deductions(['ru_deposit'=>'0']);
    check(count($zero) === 6 && array_unique(array_values($zero)) === ['0.00'], 'Explicit zero in any one field confirms no deductions and normalizes blanks');
    $partial = normalize_rubber_deductions(['ru_loan'=>'125.50']);
    check($partial['ru_loan'] === '125.50' && $partial['ru_hoon'] === '0.00', 'One entered deduction leaves other fields at zero');
    foreach (['tbl_rubber', 'tbl_price'] as $table) {
        $ddl = db()->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
        $testDb->query($ddl);
    }
    ensure_weighing_schema($testDb);
    $testDb->query('ALTER TABLE tbl_wang_weighing DROP COLUMN deductions_saved_at, DROP COLUMN deductions_saved_by');
    ensure_weighing_schema($testDb);
    check(in_array('deductions_saved_at', array_column($testDb->query('SHOW COLUMNS FROM tbl_wang_weighing')->fetch_all(MYSQLI_ASSOC), 'Field'), true), 'Upgrade existing weighing table for completion tracking');
    $testDb->query("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
    $testDb->query("INSERT INTO tbl_price (pr_year, pr_number, pr_date, pr_price, pr_saveby, pr_savedate) VALUES (2569, 'test', '2026-10-20', 50.25, 'test', '2026-10-20')");
    $priceId = $testDb->insert_id;
    $testDb->query("INSERT INTO tbl_wangyang (wang_date, wang_group, wang_number, wang_name, wang_class, wang_sack, wang_lan, wang_status, wang_savedate)
        VALUES ('2026-10-19', '1', '001', 'Test member', 'member', 4, '1', 'active', NOW())");
    $wangId = $testDb->insert_id;
    check(wang_record_lock_reason($testDb, $wangId) === '', 'Placement before weighing remains editable');
    check(collection_workflow_counts($testDb, '2026-10-19')['pending_weighing'] === 1, 'New placement waits for weighing');
    check(collection_workflow_counts($testDb, '2026-10-19', '2')['placed'] === 0, 'Summary respects lane');
    foreach (['0', '-1', '1.001', 'abc', '1e3'] as $invalid) {
        expect_failure(fn() => record_wang_weighing($testDb, $wangId, $invalid, $priceId, 'test'), "Reject invalid weight $invalid");
    }
    expect_failure(fn() => record_wang_weighing($testDb, $wangId, '100.50', 0, 'test'), 'Reject missing price');
    check((int)$testDb->query('SELECT COUNT(*) FROM tbl_rubber')->fetch_row()[0] === 0, 'Invalid requests create no purchases');
    expect_failure(fn() => record_wang_weighing($testDb, $wangId, '100.50', $priceId, 'test', '2'), 'Reject weighing a source from another lane');
    $rubberId = record_wang_weighing($testDb, $wangId, '100.50', $priceId, 'test', '1');
    check(collection_workflow_counts($testDb, '')['pending_deductions'] === 1, 'Weighing moves placement into deductions queue across dates');
    check(weighing_for_rubber($testDb, $rubberId)['deductions_saved_at'] === null, 'Zero default deductions are still pending');
    check(!rubber_receipt_ready($testDb, $rubberId), 'Pending deductions cannot print a receipt');
    $rubber = $testDb->query("SELECT * FROM tbl_rubber WHERE ru_id = $rubberId")->fetch_assoc();
    check($rubber['ru_quantity'] === '100.50' && $rubber['ru_value'] === '5050.13', 'Transfer exact net weight and calculate rounded purchase value');
    check($rubber['ru_expend'] === '0.00' && $rubber['ru_netvalue'] === '5050.13', 'Start with zero deductions');
    check($rubber['ru_date'] === '2026-10-20', 'Purchase uses selected round date independently of placement date');
    check($testDb->query("SELECT wang_weight FROM tbl_wangyang WHERE wang_id=$wangId")->fetch_row()[0] === '100.50', 'Placement shows confirmed weight');
    expect_failure(fn() => record_wang_weighing($testDb, $wangId, '200', $priceId, 'test'), 'Reject repeated confirmation');
    check((int)$testDb->query('SELECT COUNT(*) FROM tbl_rubber')->fetch_row()[0] === 1, 'Repeated confirmation creates no duplicate');
    $testDb->query("UPDATE tbl_price SET pr_price=99 WHERE pr_id=$priceId");
    check(weighing_for_rubber($testDb, $rubberId)['price_per_kg'] === '50.25', 'Confirmed price stays fixed when price changes');
    expect_failure(fn() => $testDb->query("DELETE FROM tbl_wangyang WHERE wang_id=$wangId"), 'Protect source from deletion');
    expect_failure(fn() => $testDb->query("DELETE FROM tbl_rubber WHERE ru_id=$rubberId"), 'Protect linked purchase from deletion');
    $testDb->query("INSERT INTO tbl_wangyang (wang_date, wang_group, wang_number, wang_name, wang_class, wang_sack, wang_lan, wang_savedate)
        VALUES ('2026-10-20', '2', '002', 'Second member', 'member', 1, '2', NOW())");
    $secondId = $testDb->insert_id;
    $testDb->query("CREATE TRIGGER fail_weighing BEFORE INSERT ON tbl_wang_weighing FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Simulated failure'");
    expect_failure(fn() => record_wang_weighing($testDb, $secondId, '10', $priceId, 'test'), 'Simulate failure after purchase insertion');
    check((int)$testDb->query('SELECT COUNT(*) FROM tbl_rubber')->fetch_row()[0] === 1 && !wang_has_weighing($testDb, $secondId), 'Rollback leaves no partial purchase or weighing');
    expect_failure(fn() => update_wang_weighing($testDb, $wangId, '0', 'weight-editor', '1'), 'Reject invalid corrected weight');
    expect_failure(fn() => update_wang_weighing($testDb, $wangId, '125.25', 'weight-editor', '2'), 'Reject cross-lane weight correction');
    update_wang_weighing($testDb, $wangId, '125.25', 'weight-editor', '1');
    $updated = $testDb->query("SELECT * FROM tbl_rubber WHERE ru_id=$rubberId")->fetch_assoc();
    $weighed = weighing_for_rubber($testDb, $rubberId);
    check($updated['ru_quantity'] === '125.25' && $updated['ru_value'] === '6293.81' && $updated['ru_netvalue'] === '6293.81', 'Correction updates purchase weight and money using original price');
    check($weighed['net_weight'] === '125.25' && $weighed['weight_updated_by'] === 'weight-editor' && $weighed['weighed_by'] === 'test', 'Correction records editor separately from original weigher');
    check($testDb->query("SELECT wang_weight FROM tbl_wangyang WHERE wang_id=$wangId")->fetch_row()[0] === '125.25', 'Correction synchronizes placement weight');
    check((int)$testDb->query('SELECT COUNT(*) FROM tbl_rubber')->fetch_row()[0] === 1 && $weighed['deductions_saved_at'] === null, 'Correction creates no duplicate or deduction confirmation');
    $testDb->begin_transaction();
    lock_rubber_for_edit($testDb, $rubberId, false);
    $fresh = refresh_weighed_financial_data($testDb, $rubberId, ['ru_quantity'=>'9999','ru_expend'=>'10.00']);
    $testDb->commit();
    check($fresh['ru_quantity'] === '125.25' && $fresh['ru_value'] === '6293.81' && $fresh['ru_netvalue'] === '6283.81', 'Deduction save refreshes corrected weight rather than stale form data');
    update_wang_weighing($testDb, $wangId, '100.50', 'weight-editor', '1');
    $testDb->begin_transaction();
    mark_weighing_deductions_saved($testDb, $rubberId, 'finance');
    $testDb->rollback();
    check(weighing_for_rubber($testDb, $rubberId)['deductions_saved_at'] === null, 'Rollback preserves pending deduction status');
    $testDb->begin_transaction();
    mark_weighing_deductions_saved($testDb, $rubberId, 'finance');
    $testDb->commit();
    expect_failure(fn() => update_wang_weighing($testDb, $wangId, '200', 'weight-editor', '1'), 'Zero deduction confirmation locks weight corrections');
    check(weighing_for_rubber($testDb, $rubberId)['net_weight'] === '100.50', 'Rejected correction leaves saved weight unchanged');
    check(rubber_receipt_ready($testDb, $rubberId), 'Confirmed zero deductions enable receipt printing');
    $completed = weighing_for_rubber($testDb, $rubberId);
    check(str_contains(wang_record_lock_reason($testDb, $wangId), 'บันทึกยอดหักแล้ว'), 'Completed deductions explicitly lock placement data');
    check($completed['weighed_by'] === 'test' && $completed['deductions_saved_by'] === 'finance', 'Weighing and deduction staff are recorded independently');
    check($completed['deductions_saved_at'] !== null && $completed['deductions_saved_by'] === 'finance', 'Explicit saving records completion and actor even for zero deductions');
    $counts = collection_workflow_counts($testDb, '2026-10-19');
    check($counts['completed'] === 1 && $counts['pending_deductions'] === 0 && $counts['pending_weighing'] === 0, 'Completed work leaves pending queues');
    $testDb->begin_transaction();
    lock_rubber_for_edit($testDb, $rubberId, false);
    $receiptRow = $testDb->query("SELECT * FROM tbl_rubber WHERE ru_id=$rubberId")->fetch_assoc();
    record_rubber_receipt_issue($testDb, $receiptRow, 'receipt-staff');
    $testDb->rollback();
    check(rubber_receipt_issued($testDb, $rubberId) === null, 'Failed receipt issuance leaves purchase unlocked');
    $testDb->begin_transaction();
    lock_rubber_for_edit($testDb, $rubberId, false);
    record_rubber_receipt_issue($testDb, $receiptRow, 'receipt-staff');
    $testDb->commit();
    check(str_contains(wang_record_lock_reason($testDb, $wangId), 'ออกใบเสร็จแล้ว'), 'Issued receipt lock takes priority over deduction status');
    check(rubber_receipt_issued($testDb, $rubberId)['issued_by'] === 'receipt-staff', 'Receipt issuance records responsible staff');
    $testDb->begin_transaction();
    expect_failure(fn() => lock_rubber_for_edit($testDb, $rubberId, false), 'Regular staff cannot edit an issued receipt');
    $testDb->rollback();
    $testDb->begin_transaction();
    lock_rubber_for_edit($testDb, $rubberId, true);
    record_receipt_admin_edit($testDb, $rubberId, 'admin-test');
    $testDb->commit();
    check(rubber_receipt_issued($testDb, $rubberId)['last_edited_by'] === 'admin-test', 'Admin may edit and is recorded');
    $testDb->begin_transaction();
    lock_rubber_for_edit($testDb, $rubberId, true);
    $changed = $receiptRow; $changed['ru_hoon'] = '999.00';
    record_rubber_receipt_issue($testDb, $changed, 'reprint-staff');
    $testDb->commit();
    $receipt = rubber_receipt_issued($testDb, $rubberId);
    check($receipt['issued_by'] === 'receipt-staff' && json_decode($receipt['original_data'],true)['ru_hoon'] === '0.00', 'Reprinting preserves original issuer and receipt snapshot');
    echo "All weighing checks passed.\n";
} finally {
    $testDb->query("DROP DATABASE `$name`");
}

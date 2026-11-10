<?php
// Isolated fixtures; never modify an application database.
require_once __DIR__.'/../public_dashboard_data.php';
require __DIR__.'/../db_config.php';
$db = new mysqli($db_host,$db_user,$db_pass);
$name = 'rts_public_test_'.bin2hex(random_bytes(5));
$db->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
$db->select_db($name); $db->set_charset('utf8mb4');
function check_public(bool $condition,string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
try {
    $db->query('CREATE TABLE tbl_price (pr_id INT PRIMARY KEY,pr_date DATE,pr_price DECIMAL(12,2))');
    $db->query('CREATE TABLE tbl_wangyang (wang_id INT PRIMARY KEY,wang_date DATE,wang_number VARCHAR(50),wang_name VARCHAR(255),wang_lan VARCHAR(10),record_status VARCHAR(20),wang_status VARCHAR(20),wang_savedate DATETIME)');
    $db->query('CREATE TABLE tbl_wang_weighing (weighing_id INT PRIMARY KEY,wang_id INT,ru_id INT,net_weight DECIMAL(12,2),round_date DATE,approval_route VARCHAR(20),deductions_saved_at DATETIME,approved_at DATETIME,weighed_at DATETIME)');
    $db->query('CREATE TABLE tbl_rubber (ru_id INT PRIMARY KEY,ru_number VARCHAR(50),record_status VARCHAR(20))');
    $db->query('CREATE TABLE tbl_rubber_receipts (ru_id INT PRIMARY KEY,issued_at DATETIME)');
    check_public(rts_public_dashboard($db)['round'] === null,'No price returns a usable empty dashboard');
    $db->query("INSERT INTO tbl_price VALUES (1,'2026-11-10',55),(2,'2026-11-10',56)");
    for($i=1;$i<=35;$i++) $db->query("INSERT INTO tbl_wangyang VALUES ($i,'2026-11-09',LPAD($i,3,'0'),'สมชาย นามสกุลส่วนตัว','1','normal','active','2026-11-09 09:00:00')");
    $db->query("INSERT INTO tbl_wangyang VALUES (36,'2026-11-10','today','Same day','1','normal','active',NOW()),(37,'2026-11-07','old','Old placement','1','normal','active',NOW()),(38,'2026-11-09','cancel','Cancelled','1',' CANCELLED ','active',NOW())");
    $db->query("ALTER TABLE tbl_wangyang ADD COLUMN wang_note TEXT NULL, ADD COLUMN wang_sack DECIMAL(18,2) NULL, ADD COLUMN wang_mid INT NOT NULL DEFAULT 0, ADD COLUMN wang_class VARCHAR(20) NOT NULL DEFAULT ''");
    $db->query('UPDATE tbl_wangyang SET wang_sack=12.50 WHERE wang_id=1');
    $db->query('UPDATE tbl_wangyang SET wang_sack=0 WHERE wang_id=2');
    $db->query("UPDATE tbl_wangyang SET wang_class='member' WHERE wang_id<=25");
    $db->query("UPDATE tbl_wangyang SET wang_class='general',wang_mid=wang_id WHERE wang_id BETWEEN 26 AND 35");
    $db->query("UPDATE tbl_wangyang SET wang_number='024' WHERE wang_id=25");
    $db->query('UPDATE tbl_wangyang SET wang_mid=34 WHERE wang_id=35');
    $db->query("UPDATE tbl_wangyang SET wang_lan=CASE WHEN wang_id=2 THEN '2' WHEN wang_id=3 THEN '3' WHEN wang_id IN (4,5) THEN '4' ELSE wang_lan END");
    $db->query("UPDATE tbl_wangyang SET wang_note='สวนทดสอบ' WHERE wang_id=1");
    foreach([1=>null,2=>'review',3=>'zero',4=>'review',5=>'review'] as $id=>$route){
        $db->query("INSERT INTO tbl_rubber VALUES ($id,LPAD($id,3,'0'),'normal')");
        $route=$route===null?'NULL':"'$route'";
        $saved=$id>=4?"'2026-11-10 10:00:00'":'NULL';
        $db->query("INSERT INTO tbl_wang_weighing VALUES ($id,$id,$id,100,'2026-11-10',$route,$saved,'2026-11-10 09:00:00','2026-11-10 08:00:00')");
    }
    $db->query('ALTER TABLE tbl_rubber ADD COLUMN ru_expend DECIMAL(12,2) NOT NULL DEFAULT 0, ADD COLUMN ru_class VARCHAR(20) NULL');
    $db->query('UPDATE tbl_rubber SET ru_expend=125 WHERE ru_id=4');
    $db->query("INSERT INTO tbl_rubber_receipts VALUES (5,'2026-11-10 11:00:00')");
    $initial=rts_public_dashboard($db);
    check_public($initial['round']['price']===56.0 && $initial['round']['lay_start']==='2026-11-08','Newest price defines the two preceding placement days');
    check_public($initial['summary']===['placed'=>35,'weighed'=>5,'issued'=>1,'weight'=>500.0,'members'=>24,'general'=>9],'Summary counts milestones once and excludes same-day, old and cancelled placements');
    check_public($initial['summary']['members']===24 && $initial['summary']['general']===9, 'Participants are deduplicated by member number or general farmer ID across all round records');
    check_public(rts_public_dashboard($db,'001','issued')['summary']['members']===24, 'Participant totals do not change with search or status filters');
    check_public(count($initial['records'])===20 && $initial['matched']===35,'Latest list is bounded without truncating totals');
    $pageTwo=rts_public_dashboard($db,'','all',2);
    check_public($initial['total_pages']===2 && count($pageTwo['records'])===15 && !array_intersect(array_column($initial['records'],'id'),array_column($pageTwo['records'],'id')), 'Pagination reaches all records without overlap');
    check_public(rts_public_dashboard($db,'','all',999)['page']===2 && rts_public_dashboard($db,'','all',0)['page']===1, 'Page numbers clamp to available pages');
    check_public(rts_public_dashboard($db,'','waiting')['matched']===30 && rts_public_dashboard($db,'','ready')['matched']===2, 'Waiting and ready filters include all matching records');
    check_public(array_column($initial['lanes'],'weight')===[100.0,100.0,100.0,200.0] && array_sum(array_column($initial['lanes'],'weight'))===$initial['summary']['weight'], 'Per-lane confirmed weights reconcile with the round total');
    check_public(rts_public_dashboard($db,'001','issued')['lanes']===$initial['lanes'], 'Lane totals include all round records regardless of search, filters or pagination');
    $byId=[];foreach($initial['records'] as $record)$byId[$record['id']]=$record;
    check_public($byId[1]['status']==='weighed' && $byId[2]['status']==='weighed' && $byId[3]['status']==='ready' && $byId[4]['status']==='ready' && $byId[5]['status']==='issued','Status follows actual weighing, approval, deduction and receipt milestones');
    check_public($byId[1]['deduction_state']==='pending' && $byId[2]['deduction_state']==='pending' && $byId[3]['deduction_state']==='none' && $byId[4]['deduction_state']==='has' && $byId[5]['deduction_state']==='none', 'Deduction icons distinguish positive, confirmed zero and unconfirmed amounts');
    check_public($byId[1]['sacks']===12.5 && $byId[2]['sacks']===0.0 && $byId[3]['sacks']===null, 'Sack counts preserve decimals, zero and missing data');
    $member=rts_public_dashboard($db,'001');
    check_public($member['matched']===1 && $member['records'][0]['member_number']==='001','Exact member search preserves leading zeroes and finds records beyond latest rows');
    check_public(rts_public_dashboard($db,'สมชาย')['matched']===35 && rts_public_dashboard($db,'นามสกุลส่วนตัว')['matched']===35, 'Search accepts Thai first names and surnames across the entire round');
    check_public(rts_public_dashboard($db,'สมชาย   นามสกุลส่วนตัว','issued')['matched']===1, 'Full names tolerate repeated spaces and combine with status filters');
    check_public(rts_public_dashboard($db,'%')['matched']===0 && rts_public_dashboard($db,'_')['matched']===0, 'Search treats LIKE wildcard characters literally');
    check_public(rts_public_dashboard($db,'01')['matched']===0 && rts_public_dashboard($db,"' OR 1=1 --")['matched']===0,'Search is exact and parameterized');
    check_public(rts_public_dashboard($db,'','weighed')['matched']===5 && rts_public_dashboard($db,'','issued')['matched']===1,'Filters respect milestones; receipts remain part of weighed totals');
    $publicJson=json_encode($initial,JSON_UNESCAPED_UNICODE);
    check_public(str_contains($publicJson,'สมชาย นามสกุลส่วนตัว (สวนทดสอบ)') && !str_contains($publicJson,'approval_route') && !str_contains($publicJson,'net_weight') && !str_contains($publicJson,'ru_expend'),'Public response includes full name and note but omits financial and internal fields');
    $db->query("UPDATE tbl_rubber SET record_status='cancelled' WHERE ru_id=5");
    check_public(rts_public_dashboard($db)['lanes'][3]['weight']===100.0, 'Cancelled purchases are excluded from lane weights');
    check_public(rts_public_dashboard($db)['summary']['members']===23 && rts_public_dashboard($db)['summary']['general']===9, 'Cancelled participant records do not inflate participant totals');
    check_public(rts_public_dashboard($db)['summary']['issued']===0,'Cancellation disappears from both receipts and overall metrics');
    $db->query("UPDATE tbl_wang_weighing SET round_date='2026-11-09' WHERE wang_id=2");
    check_public(rts_public_dashboard($db)['lanes'][1]['weight']===0.0, 'Weights from another sale round do not enter lane totals');
    check_public(rts_public_dashboard($db)['summary']['placed']===33,'Previously weighed overlapping placements cannot enter a new round');
    $before=$db->query('SELECT * FROM tbl_wangyang ORDER BY wang_id')->fetch_all(MYSQLI_ASSOC);
    rts_public_dashboard($db);rts_public_dashboard($db,'001','issued');
    check_public($before===$db->query('SELECT * FROM tbl_wangyang ORDER BY wang_id')->fetch_all(MYSQLI_ASSOC),'Repeated public reads do not modify placements');
    try {rts_public_dashboard($db,str_repeat('1',101));throw new RuntimeException('Expected length rejection');} catch(InvalidArgumentException $e){echo "PASS: Oversized searches are rejected\n";}
    check_public(rts_public_display_name('สมชาย ใจดี','(สวนเหนือ)')==='สมชาย ใจดี (สวนเหนือ)' && rts_public_display_name('สมชาย ใจดี','  ')==='สมชาย ใจดี', 'Display notes have one pair of parentheses and empty notes are omitted');
    $dashboard=['records'=>[['name'=>'สมชาย ใจดี (<script>alert(1)</script>)','member_number'=>'001','lane'=>'1','status'=>'weighed','activity_at'=>'2026-11-10T08:00:00+07:00']],'member'=>''];
    function e($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
    ob_start();require __DIR__.'/../partials/public-status-records.php';$html=ob_get_clean();
    check_public(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>'), 'Names and notes are escaped in the initial HTML');
    echo "All public dashboard checks passed.\n";
} finally {$db->query("DROP DATABASE `$name`");$db->close();}

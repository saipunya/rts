<?php
declare(strict_types=1);

// Public projections only. These queries never approve, edit or issue a receipt.
function rts_public_display_name(string $name, ?string $note = null): string {
    $name = trim($name) ?: 'ไม่ระบุชื่อ';
    $note = trim((string)$note);
    if ($note === '') return $name;
    $suffix = substr($note, 0, 1) === '(' && substr($note, -1) === ')' ? $note : '(' . $note . ')';
    return $name . ' ' . $suffix;
}

function rts_public_dashboard(mysqli $db, string $member = '', string $stage = 'all', int $page = 1): array {
    $member = trim($member);
    if (mb_strlen($member) > 100) throw new InvalidArgumentException('คำค้นต้องไม่เกิน 100 ตัวอักษร');
    if (!in_array($stage, ['all', 'waiting', 'weighed', 'ready', 'issued'], true)) $stage = 'all';
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $data = ['updated_at'=>$now->format(DateTimeInterface::ATOM), 'round'=>null,
        'summary'=>['placed'=>0,'weighed'=>0,'issued'=>0,'weight'=>0.0,'members'=>0,'general'=>0],
        'records'=>[], 'matched'=>0, 'limit'=>20, 'page'=>1, 'total_pages'=>1, 'member'=>$member, 'stage'=>$stage];
    for ($lane=1;$lane<=4;$lane++) $data['lanes'][] = ['lane'=>(string)$lane,'weight'=>0.0,'weighed'=>0];
    $price = $db->query('SELECT pr_date,pr_price FROM tbl_price ORDER BY pr_date DESC,pr_id DESC LIMIT 1')->fetch_assoc();
    if (!$price) return $data;
    $date = new DateTimeImmutable($price['pr_date'], new DateTimeZone('Asia/Bangkok'));
    $start = $date->modify('-2 days')->format('Y-m-d');
    $end = $date->modify('-1 day')->format('Y-m-d');
    $sale = $date->format('Y-m-d');
    $data['round'] = ['date'=>$sale,'lay_start'=>$start,'lay_end'=>$end,'price'=>(float)$price['pr_price'],
        'is_today'=>$sale === $now->format('Y-m-d'), 'is_future'=>$sale > $now->format('Y-m-d')];
    $from = " FROM tbl_wangyang w
        LEFT JOIN tbl_wang_weighing x ON x.wang_id=w.wang_id
        LEFT JOIN tbl_rubber r ON r.ru_id=x.ru_id
        LEFT JOIN tbl_rubber_receipts z ON z.ru_id=x.ru_id";
    $where = " WHERE ((x.weighing_id IS NULL AND w.wang_date BETWEEN ? AND ?) OR x.round_date=?)
        AND LOWER(TRIM(COALESCE(w.record_status,'normal'))) NOT IN ('cancelled','canceled','deleted')
        AND LOWER(TRIM(COALESCE(w.wang_status,''))) NOT IN ('cancelled','canceled','deleted')
        AND LOWER(TRIM(COALESCE(r.record_status,'normal'))) NOT IN ('cancelled','canceled','deleted')";
    $participantClass = "LOWER(TRIM(COALESCE(NULLIF(TRIM(w.wang_class),''),r.ru_class,'')))";
    $memberIdentity = "COALESCE(CONCAT('number:',NULLIF(TRIM(COALESCE(NULLIF(w.wang_number,''),r.ru_number)),'')),CONCAT('id:',NULLIF(w.wang_mid,0)),CONCAT('name:',NULLIF(TRIM(w.wang_name),'')))";
    $generalIdentity = "COALESCE(CONCAT('id:',NULLIF(w.wang_mid,0)),CONCAT('number:',NULLIF(TRIM(COALESCE(NULLIF(w.wang_number,''),r.ru_number)),'')),CONCAT('name:',NULLIF(TRIM(w.wang_name),'')))";
    $stmt = $db->prepare("SELECT COUNT(*) placed, COALESCE(SUM(x.weighing_id IS NOT NULL),0) weighed,
        COALESCE(SUM(z.issued_at IS NOT NULL),0) issued, COALESCE(SUM(x.net_weight),0) weight,
        COUNT(DISTINCT CASE WHEN $participantClass='member' THEN $memberIdentity END) members,
        COUNT(DISTINCT CASE WHEN $participantClass='general' THEN $generalIdentity END) general".$from.$where);
    $stmt->bind_param('sss', $start, $end, $sale); $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $data['summary'] = ['placed'=>(int)$summary['placed'],'weighed'=>(int)$summary['weighed'],
        'issued'=>(int)$summary['issued'],'weight'=>(float)$summary['weight'],
        'members'=>(int)$summary['members'],'general'=>(int)$summary['general']];
    $stmt = $db->prepare("SELECT TRIM(w.wang_lan) lane, COALESCE(SUM(x.net_weight),0) weight, COUNT(*) weighed".
        $from.$where.' AND x.weighing_id IS NOT NULL GROUP BY TRIM(w.wang_lan) ORDER BY CAST(lane AS UNSIGNED),lane');
    $stmt->bind_param('sss',$start,$end,$sale); $stmt->execute();
    $lanes = [];
    foreach ($data['lanes'] as $lane) $lanes[$lane['lane']] = $lane;
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $lane) {
        $key = (string)$lane['lane'];
        $lanes[$key] = ['lane'=>$key,'weight'=>(float)$lane['weight'],'weighed'=>(int)$lane['weighed']];
    }
    $stmt->close();
    $data['lanes'] = array_values($lanes);
    $params = [$start,$end,$sale]; $types = 'sss';
    if ($member !== '') {
        $numberMatch = "TRIM(COALESCE(NULLIF(w.wang_number,''),r.ru_number,''))=?";
        $params[] = $member; $types .= 's';
        if (preg_match('/^[0-9]+$/D', $member)) {
            $where .= ' AND ' . $numberMatch;
        } else {
            $nameMatches = [];
            foreach ((preg_split('/\s+/u', $member, -1, PREG_SPLIT_NO_EMPTY) ?: []) as $word) {
                $nameMatches[] = "w.wang_name LIKE ? ESCAPE '!'";
                $params[] = '%' . strtr($word, ['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
                $types .= 's';
            }
            $where .= ' AND (' . $numberMatch . ' OR (' . ($nameMatches ? implode(' AND ', $nameMatches) : '0=1') . '))';
        }
    }
    if ($stage === 'waiting') $where .= ' AND x.weighing_id IS NULL';
    if ($stage === 'ready') $where .= " AND z.issued_at IS NULL AND (x.deductions_saved_at IS NOT NULL OR x.approval_route='zero')";
    if ($stage === 'weighed') $where .= ' AND x.weighing_id IS NOT NULL';
    if ($stage === 'issued') $where .= ' AND z.issued_at IS NOT NULL';
    $stmt = $db->prepare('SELECT COUNT(*)'.$from.$where);
    $stmt->bind_param($types, ...$params); $stmt->execute();
    $data['matched'] = (int)$stmt->get_result()->fetch_row()[0]; $stmt->close();
    $data['total_pages'] = max(1,(int)ceil($data['matched'] / $data['limit']));
    $data['page'] = max(1,min($page,$data['total_pages']));
    $offset = ($data['page'] - 1) * $data['limit'];
    $stmt = $db->prepare("SELECT w.wang_id, COALESCE(NULLIF(w.wang_number,''),r.ru_number,'') member_number,
        w.wang_name, w.wang_note, w.wang_lan, w.wang_sack, r.ru_expend, x.weighing_id, x.approval_route, x.deductions_saved_at, z.issued_at,
        COALESCE(z.issued_at,x.deductions_saved_at,x.approved_at,x.weighed_at,w.wang_savedate) activity_at".
        $from.$where.' ORDER BY activity_at DESC,w.wang_id DESC LIMIT ? OFFSET ?');
    $params[] = $data['limit']; $params[] = $offset; $types .= 'ii';
    $stmt->bind_param($types, ...$params); $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $status = $row['issued_at'] ? 'issued' :
            (($row['deductions_saved_at'] || $row['approval_route'] === 'zero') ? 'ready' : ($row['weighing_id'] ? 'weighed' : 'waiting'));
        $deductionsConfirmed = $row['deductions_saved_at'] || $row['approval_route'] === 'zero' || $row['issued_at'];
        $deductionState = $deductionsConfirmed ? ((float)$row['ru_expend'] > 0 ? 'has' : 'none') : 'pending';
        // Publish the requested display name and note, without financial or internal workflow fields.
        $data['records'][] = ['id'=>(int)$row['wang_id'],'member_number'=>(string)$row['member_number'],
            'name'=>rts_public_display_name((string)$row['wang_name'], $row['wang_note']),'lane'=>(string)$row['wang_lan'],
            'sacks'=>$row['wang_sack'] === null ? null : (float)$row['wang_sack'],
            'status'=>$status,'deduction_state'=>$deductionState,'activity_at'=>(new DateTimeImmutable($row['activity_at'],new DateTimeZone('Asia/Bangkok')))->format(DateTimeInterface::ATOM)];
    }
    $stmt->close();
    return $data;
}

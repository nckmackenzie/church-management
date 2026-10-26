<?php
/**
 * Task 9.7 through Apache: group income statement (statement + drill), journal report, contribution / expense reports
 * (congregation and parish), their JSON / Excel endpoints, paging, search, 400 cases, escaping, no PHP warnings.
 * Fixtures use the year 2098 and the marker "zz t97h"; self-cleaning, CLI-only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir(__DIR__ . '/../app'); require 'boostrap.php';
$pdo = (new Database)->pdo(); $_SESSION = ['congId' => 11, 'userId' => 64]; $base = URLROOT; $pass = 0; $fail = 0; $phpErrors = [];
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x97h($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
function ok97h($n, $c, $x = '') { global $pass, $fail; if ($c) { $pass++; echo "PASS $n\n"; } else { $fail++; echo "FAIL $n" . ($x === '' ? '' : ": $x") . "\n"; } }
require_once __DIR__ . '/dev_session_helper.php';
function http97($path, $sid) {
    global $base, $phpErrors;
    $ch = curl_init($base . '/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $h = substr((string) $raw, 0, $hs); $b = substr((string) $raw, $hs);
    if (stripos($h, 'spreadsheetml') === false && preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', $b, $m)) { $phpErrors[] = $path . ' -> ' . $m[2]; }
    return [$code, $b, $h];
}
function sheet97($bytes) {
    $tmp = tempnam(sys_get_temp_dir(), 't97') . '.xlsx'; file_put_contents($tmp, $bytes);
    $z = new ZipArchive; if ($z->open($tmp) !== true) { @unlink($tmp); return null; }
    $xml = $z->getFromName('xl/worksheets/sheet1.xml'); $z->close(); @unlink($tmp); return $xml;
}
function nums97($xml) { preg_match_all('~<c [^>]*><v>(-?[0-9.]+)</v></c>~', (string) $xml, $m); return $m[1]; }
function js97($b) { return json_decode($b, true); }
function qs97(array $p) { return http_build_query($p); }

$created = ['entries' => [], 'reqs' => [], 'mmf' => [], 'exps' => [], 'headers' => [], 'groups' => [], 'districts' => [], 'subs' => [], 'congs' => []];
$apacheErrorLog = 'E:/xampp/apache/logs/error.log'; $errorOffset = is_file($apacheErrorLog) ? filesize($apacheErrorLog) : 0;
function entry97h($type, $txn, $date, $deleted, array $lines, $c = 11, $journalNo = null, $reference = null, $description = 'zz t97h entry') {
    global $created;
    $id = x97h('INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,?,?,?,?,?,?)', [$c, $date, $type, $txn, $journalNo, $reference, $description, $deleted ? 1 : 0]);
    $created['entries'][] = $id;
    foreach ($lines as $i => [$acc, $dc, $amt, $memo]) { x97h('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $acc, $dc, $amt, $memo ?? null, $i + 1]); }
    return $id;
}
function two97h($a, $b, $amt) { return [[$a, 'DEBIT', $amt, null], [$b, 'CREDIT', $amt, null]]; }

try {
    $s11 = mkSession(64, 11); $s2 = mkSession(64, 2); $sP = mkSession(1, 1);
    $cong = 11; $CASH = 34; $INC = 30; $EXA = 26; $EXB = 27;
    $XSS = 'zz t97h <script>alert(1)</script>';
    $other = x97h("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t97h other', 0, 0)"); $created['congs'][] = $other;
    $G = x97h("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zzh', ?, 1, 0, ?)", [$XSS . ' group', $cong]); $created['groups'][] = $G;
    $GO = x97h("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zzho', 'zz t97h foreign group', 1, 0, ?)", [$other]); $created['groups'][] = $GO;
    $GD = x97h("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zzhd', 'zz t97h deleted group', 1, 1, ?)", [$cong]); $created['groups'][] = $GD;
    $D = x97h("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t97h district', 0, ?)", [$cong]); $created['districts'][] = $D;
    $DO = x97h("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t97h foreign district', 0, ?)", [$other]); $created['districts'][] = $DO;
    $sub = x97h("INSERT INTO tblbanksubaccounts (AccountName, BankId, AccountId, GroupDistrict, GroupId, DistrictId, Deleted) VALUES ('zz t97h sub', 105, ?, 'group', ?, NULL, 0)", [$INC, $G]); $created['subs'][] = $sub;
    $n = 0;
    $req = function ($amount, $date, $purpose, $entity = null, $type = 'group') use (&$created, $G, &$n, $cong) {
        $id = x97h('INSERT INTO tblfundrequisition (ReqNo, RequisitionDate, RequestType, DistrictId, GroupId, Purpose, AmountRequested, AmountApproved, ApprovalDate, Status, DontDeduct, Deleted, CongregationId) VALUES (?,?,?,?,?,?,?,?,?,1,1,0,?)',
            [972000 + (++$n), $date, $type, $type === 'district' ? $entity : null, $type === 'group' ? ($entity ?? $G) : null, $purpose, $amount, $amount, $date, $cong]);
        $created['reqs'][] = $id; return $id;
    };
    foreach ([['10000.00', '2098-02-10', $XSS . ' requisition one'], ['5000.00', '2098-02-11', 'zz t97h requisition two'], ['2500.00', '2098-02-12', 'zz t97h requisition three']] as [$a, $d, $p]) { $r = $req($a, $d, $p); entry97h(12, $r, $d, false, two97h($CASH, 100, $a)); }
    $rv = $req('999.00', '2098-02-13', 'zz t97h requisition voided'); entry97h(12, $rv, '2098-02-13', true, two97h($CASH, 100, '999.00'));
    $rd = $req('1000.00', '2098-04-01', 'zz t97h district requisition', $D, 'district'); entry97h(12, $rd, '2098-04-01', false, two97h($CASH, 100, '1000.00'));
    $mmf = function ($debit, $date, $narr) use (&$created, $G, $cong) { $id = x97h("INSERT INTO tblmmf (TransactionDate, `Type`, DistrictId, GroupId, Debit, Credit, SubAccountId, Narration, TransactionType, TransactionId, CongregationId, Deleted) VALUES (?, 'group', NULL, ?, ?, 0, NULL, ?, 17, NULL, ?, 0)", [$date, $G, $debit, $narr, $cong]); $created['mmf'][] = $id; return $id; };
    $m1 = $mmf('3000.00', '2098-05-05', 'zz t97h collection one'); entry97h(17, $m1, '2098-05-05', false, two97h($CASH, $INC, '3000.00'));
    $exp = function ($acc, $amount, $narr, $ref = 'zz t97h ref') use (&$created, $G, $cong, &$n) { $id = x97h("INSERT INTO tblexpenses (fiscalYearId, voucherNo, expenseType, expenseDate, accountId, groupId, districtId, paymethodId, amount, narration, paymentReference, status, postedBy, postedDate, deleted, congregationId) VALUES (12,?,2,'2098-07-07',?,?,NULL,1,?,?,?,1,64,'2098-07-07',0,?)", [973000 + (++$n), $acc, $G, $amount, $narr, $ref, $cong]); $created['exps'][] = $id; return $id; };
    $e1 = $exp($EXA, '2000.00', $XSS . ' expense one', $XSS . ' ref'); entry97h(2, $e1, '2098-07-07', false, two97h($EXA, $CASH, '2000.00'));
    $e2 = $exp($EXA, '1000.00', 'zz t97h expense two'); entry97h(2, $e2, '2098-07-07', false, two97h($EXA, $CASH, '1000.00'));
    $e3 = $exp($EXB, '700.00', 'zz t97h expense three'); entry97h(2, $e3, '2098-07-07', false, two97h($EXB, $CASH, '700.00'));
    // journals
    $jn = [972101, 972102, 972103];
    entry97h(5, $jn[0], '2098-03-01', false, [[98, 'DEBIT', '1234.50', 'zz t97h <b>memo</b>'], [$INC, 'CREDIT', '1234.50', null]], 11, $jn[0], 'zz t97h ref <i>one</i>', $XSS . ' journal one');
    entry97h(5, $jn[1], '2098-03-02', false, two97h(98, $INC, '20.00'), 11, $jn[1], 'zz t97h ref two', 'zz t97h journal two');
    entry97h(5, $jn[2], '2098-03-03', true, two97h(98, $INC, '30.00'), 11, $jn[2], 'zz t97h voided', 'zz t97h voided journal');
    entry97h(5, 972104, '2098-03-03', false, two97h(98, $INC, '40.00'), $other, 972104, 'zz t97h other', 'zz t97h other journal');
    // contributions and expenses
    $hOk = x97h("INSERT INTO tblcontributions_header (fiscalYearId, postedBy, postedDate, status, Deleted, receiptNo, congregationId) VALUES (12, 64, '2098-08-01', 1, 0, 'ZZ97H-OK', ?)", [$cong]); $created['headers'][] = $hOk;
    $hDel = x97h("INSERT INTO tblcontributions_header (fiscalYearId, postedBy, postedDate, status, Deleted, receiptNo, congregationId) VALUES (12, 64, '2098-08-01', 1, 1, 'ZZ97H-DEL', ?)", [$cong]); $created['headers'][] = $hDel;
    $hOth = x97h("INSERT INTO tblcontributions_header (fiscalYearId, postedBy, postedDate, status, Deleted, receiptNo, congregationId) VALUES (12, 64, '2098-08-01', 1, 0, 'ZZ97H-OTH', ?)", [$other]); $created['headers'][] = $hOth;
    $det = function ($h, $date, $acc, $amount, $cat, $group = null, $district = null, $ref = 'zz t97h pay') { return x97h('INSERT INTO tblcontributions_details (HeaderId, contributionDate, contributionTypeId, paymentMethodId, amount, category, contributotGroup, contributotDistrict, paymentReference, narration, incomeType, forGroup) VALUES (?,?,?,1,?,?,?,?,?,?,1,0)', [$h, $date, $acc, $amount, $cat, $group, $district, $ref, 'zz t97h']); };
    $det($hOk, '2098-08-02', $INC, '1000.00', 2, $G, null, $XSS . ' payref'); $det($hOk, '2098-08-03', 31, '250.50', 3, null, $D); $det($hOk, '2098-08-04', $INC, '75.00', 2, $G);
    $det($hDel, '2098-08-02', $INC, '3333.00', 3, null, $D); $det($hOth, '2098-08-02', $INC, '5555.00', 3, null, $D);
    $accContribOk = [$INC];

    echo "== pages\n";
    foreach (['reports/groupsincomestatement' => 'grouppl-v2.js', 'reports/journals' => 'journals-v1.js', 'reports/contributions' => 'filter-list-v1.js', 'reports/expenses' => 'filter-list-v1.js'] as $path => $module) {
        [$c, $b] = http97($path, $s11);
        ok97h("$path renders with its module $module", $c === 200 && strpos($b, $module) !== false, "status $c");
    }
    [$c, $b] = http97('reports/groupsincomestatement', $s11);
    ok97h('group statement page lists this congregation\'s groups and districts, escaped (no raw script tag), and no other congregation\'s or deleted group', $c === 200 && stripos($b, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && stripos($b, '<script>alert') === false && strpos($b, 'value="group:' . $G . '"') !== false && strpos($b, 'value="district:' . $D . '"') !== false && strpos($b, 'zz t97h foreign group') === false && stripos($b, 'zz t97h deleted group') === false);
    foreach (['reports/groupplrevenuedetailed?type=requisitions' => 'grouppl-detail-v2.js', 'reports/groupplexpensedetailed?account=26' => 'grouppl-detail-v2.js'] as $path => $module) { [$c, $b] = http97($path, $s11); ok97h("$path renders its module and data endpoint", $c === 200 && strpos($b, $module) !== false && strpos($b, 'groupplrevenuedetailedrpt') !== false || strpos($b, 'groupplexpensedetailedrpt') !== false, "status $c"); }
    foreach (['parishreports/contributions', 'parishreports/bycontributor', 'parishreports/expenses'] as $path) { [$c, $b] = http97($path, $sP); ok97h("$path renders with the shared module and its endpoint", $c === 200 && strpos($b, 'filter-list-v1.js') !== false && strpos($b, 'id="listapp"') !== false, "status $c"); }

    echo "== group income statement JSON\n";
    $P = ['group' => $G, 'start' => '2098-01-01', 'end' => '2098-12-31'];
    [$c, $b, $h] = http97('reports/groupincomestatementrpt?' . qs97($P), $s11); $j = js97($b);
    ok97h('statement answers JSON: requisitions 17,500.00 (voided entry out), collections 3,000.00, expenses 3,700.00, result 16,800.00', $c === 200 && stripos($h, 'application/json') !== false && $j['revenue']['total'] === '20,500.00' && $j['expenses']['total'] === '3,700.00' && $j['result']['amount'] === '16,800.00', $b);
    ok97h('rows: the old labels, the accounts by name (never a path)', array_column($j['revenue']['rows'], 'label') === ['Group Requisitions', 'Group Collections'] && count($j['expenses']['rows']) === 2 && strpos(json_encode($j['expenses']['rows']), '>') === false);
    ok97h('the group name comes back as data with its markup intact; the response is JSON', strpos($j['name'], '<script>') !== false && stripos($h, 'application/json') !== false);
    [$c, $b] = http97('reports/groupincomestatementrpt?' . qs97(['district' => $D, 'start' => '2098-01-01', 'end' => '2098-12-31']), $s11); $jd = js97($b);
    ok97h('district statement: District Requisitions 1,000.00 only', $c === 200 && $jd['revenue']['rows'][0]['label'] === 'District Requisitions' && $jd['revenue']['total'] === '1,000.00' && $jd['expenses']['total'] === '0.00');
    [$c, $b, $h] = http97('reports/groupincomestatementrpt?' . qs97($P + ['format' => 'xlsx']), $s11); $xml = sheet97($b);
    ok97h('Excel: a workbook with the same figures as real numbers (17500.00, 3000.00, 20500.00, 3700.00, 16800.00), attachment header', $c === 200 && stripos($h, 'spreadsheetml') !== false && stripos($h, 'attachment') !== false && $xml !== null && count(array_intersect(['17500.00', '3000.00', '20500.00', '3700.00', '16800.00'], nums97($xml))) === 5, json_encode(nums97($xml)));
    ok97h('Excel: text is escaped in the XML (no raw script tag) and there is no congregation name', $xml !== null && strpos($xml, '<script>') === false && strpos($xml, '&lt;script&gt;') !== false && !preg_match('/bethany/i', $xml));

    echo "== drill: totals equal the figure clicked, search, paging, Excel\n";
    $D1 = ['type' => 'requisitions'] + $P;
    [$c, $b] = http97('reports/groupplrevenuedetailedrpt?' . qs97($D1), $s11); $j1 = js97($b);
    ok97h('requisitions drill: 3 rows (the voided entry is out), total 17,500.00, columns date / source / narration / amount', $c === 200 && $j1['matching'] === 3 && $j1['total'] === '17,500.00' && array_column($j1['columns'], 'key') === ['date', 'no', 'narr', 'amount'], $b);
    [$c, $b] = http97('reports/groupplrevenuedetailedrpt?' . qs97($D1 + ['pageSize' => 2, 'page' => 2]), $s11); $j2 = js97($b);
    ok97h('page 2 of 2 holds the third row; the total is still the total of all matching rows', $c === 200 && count($j2['rows']) === 1 && $j2['page'] === 2 && $j2['pages'] === 2 && $j2['total'] === '17,500.00');
    [$c, $b] = http97('reports/groupplrevenuedetailedrpt?' . qs97($D1 + ['search' => 'requisition two']), $s11); $j3 = js97($b);
    ok97h('search narrows the rows and the total (5,000.00)', $j3['matching'] === 1 && $j3['total'] === '5,000.00');
    [$c, $b] = http97('reports/groupplrevenuedetailedrpt?' . qs97($D1 + ['search' => '<script>']), $s11); $j4 = js97($b);
    ok97h('search for markup finds the row and returns it as data', $c === 200 && $j4['matching'] === 1 && strpos($j4['rows'][0]['narr'], '<script>') !== false);
    [$c, $b, $h] = http97('reports/groupplrevenuedetailedrpt?' . qs97($D1 + ['format' => 'xlsx']), $s11); $xml = sheet97($b);
    ok97h('Excel of the drill holds all three rows and the total as numbers', $c === 200 && stripos($h, 'spreadsheetml') !== false && count(array_intersect(['10000.00', '5000.00', '2500.00', '17500.00'], nums97($xml))) === 4 && strpos($xml, '<script>') === false, json_encode(nums97($xml)));
    [$c, $b, $h] = http97('reports/groupplrevenuedetailedrpt?' . qs97($D1 + ['format' => 'xlsx', 'search' => 'requisition two']), $s11); $xml = sheet97($b);
    ok97h('searched Excel holds only the matching row', $c === 200 && in_array('5000.00', nums97($xml), true) && !in_array('10000.00', nums97($xml), true));
    [$c, $b] = http97('reports/groupplrevenuedetailedrpt?' . qs97(['type' => 'collections'] + $P), $s11); $jc = js97($b);
    ok97h('collections drill: one group collection 3,000.00 with its edit link', $c === 200 && $jc['total'] === '3,000.00' && $jc['rows'][0]['no'] === 'Collection ' . $m1 && strpos((string) $jc['rows'][0]['link'], '/groupcollections/edit/' . $m1) !== false, $b);
    [$c, $b] = http97('reports/groupplexpensedetailedrpt?' . qs97(['account' => $EXA] + $P), $s11); $je = js97($b);
    ok97h('expense drill of account 26: 2 rows, total 3,000.00, voucher links', $c === 200 && $je['matching'] === 2 && $je['total'] === '3,000.00' && strpos((string) $je['rows'][0]['link'], '/expenses/print/') !== false);
    [$c, $b] = http97('reports/groupplexpensedetailedrpt?' . qs97(['account' => $EXA, 'format' => 'xlsx'] + $P), $s11); $xml = sheet97($b);
    ok97h('Excel of the expense drill: amounts 2000.00 and 1000.00, total 3000.00', $c === 200 && count(array_intersect(['2000.00', '1000.00', '3000.00'], nums97($xml))) === 3);
    ok97h('the figures on the statement equal the drill totals', $j['expenses']['rows'][0]['amount'] === $je['total'] && $j['revenue']['rows'][0]['amount'] === $j1['total'] && $j['revenue']['rows'][1]['amount'] === $jc['total']);

    echo "== group income statement: 400 cases\n";
    $bad = [
        'no group or district' => ['start' => '2098-01-01', 'end' => '2098-12-31'],
        'both group and district' => ['group' => $G, 'district' => $D, 'start' => '2098-01-01', 'end' => '2098-12-31'],
        'foreign group' => ['group' => $GO, 'start' => '2098-01-01', 'end' => '2098-12-31'],
        'foreign district' => ['district' => $DO, 'start' => '2098-01-01', 'end' => '2098-12-31'],
        'deleted group' => ['group' => $GD, 'start' => '2098-01-01', 'end' => '2098-12-31'],
        'unknown group' => ['group' => 999999, 'start' => '2098-01-01', 'end' => '2098-12-31'],
        'SQL in the group' => ['group' => '1 OR 1=1', 'start' => '2098-01-01', 'end' => '2098-12-31'],
        'array group' => ['group' => [$G], 'start' => '2098-01-01', 'end' => '2098-12-31'],
        'impossible date' => ['group' => $G, 'start' => '2098-02-30', 'end' => '2098-12-31'],
        'text date' => ['group' => $G, 'start' => 'yesterday', 'end' => '2098-12-31'],
        'reversed dates' => ['group' => $G, 'start' => '2098-12-31', 'end' => '2098-01-01'],
        'missing end' => ['group' => $G, 'start' => '2098-01-01'],
    ];
    foreach ($bad as $label => $params) { [$c, $b] = http97('reports/groupincomestatementrpt?' . qs97($params), $s11); ok97h("statement: $label answers 400 with a message", $c === 400 && js97($b)['success'] === false && js97($b)['message'] !== '', "status $c"); }
    [$c] = http97('reports/groupincomestatementrpt?' . qs97($P), $s2);
    ok97h('statement: another congregation\'s session asking for this congregation\'s group answers 400', $c === 400, "status $c");
    foreach (['bad type' => ['type' => 'x'] + $P, 'missing type' => $P, 'foreign group' => ['type' => 'requisitions', 'group' => $GO, 'start' => '2098-01-01', 'end' => '2098-12-31'], 'reversed dates' => ['type' => 'requisitions', 'group' => $G, 'start' => '2098-12-31', 'end' => '2098-01-01'], 'array type' => ['type' => ['requisitions']] + $P] as $label => $params) {
        [$c, $b] = http97('reports/groupplrevenuedetailedrpt?' . qs97($params), $s11); ok97h("revenue drill: $label answers 400", $c === 400, "status $c");
    }
    foreach (['missing account' => $P, 'unknown account' => ['account' => 99999999] + $P, 'text account' => ['account' => '26 OR 1=1'] + $P, 'array account' => ['account' => [26]] + $P, 'foreign group' => ['account' => $EXA, 'group' => $GO, 'start' => '2098-01-01', 'end' => '2098-12-31'], 'foreign district' => ['account' => $EXA, 'district' => $DO, 'start' => '2098-01-01', 'end' => '2098-12-31'], 'bad date' => ['account' => $EXA, 'group' => $G, 'start' => 'x', 'end' => '2098-12-31']] as $label => $params) {
        [$c, $b] = http97('reports/groupplexpensedetailedrpt?' . qs97($params), $s11); ok97h("expense drill: $label answers 400", $c === 400, "status $c");
    }
    foreach (['page 0' => ['page' => 0], 'page text' => ['page' => 'a'], 'pageSize 0' => ['pageSize' => 0], 'pageSize 201' => ['pageSize' => 201], 'search array' => ['search' => ['a']], 'search 101 chars' => ['search' => str_repeat('a', 101)]] as $label => $extra) {
        [$c] = http97('reports/groupplrevenuedetailedrpt?' . qs97($D1 + $extra), $s11); ok97h("drill paging: $label answers 400", $c === 400, "status $c");
    }

    echo "== journal report\n";
    $J = ['from' => '2098-03-01', 'to' => '2098-03-31'];
    [$c, $b, $h] = http97('reports/journalsrpt?' . qs97($J), $s11); $jj = js97($b);
    ok97h('JSON: the two active journals of this congregation, none voided, none of the other congregation; each block balances', $c === 200 && stripos($h, 'application/json') !== false && $jj['matching'] === 2 && array_column($jj['journals'], 'journalNo') === [$jn[0], $jn[1]] && $jj['journals'][0]['balanced'] && $jj['journals'][1]['balanced'], $b);
    ok97h('totals: debits = credits = 1,254.50', $jj['debits'] === '1,254.50' && $jj['credits'] === '1,254.50');
    ok97h('reference and memo are returned as data, with markup intact for the client to render as text', $jj['journals'][0]['reference'] === 'zz t97h ref <i>one</i>' && $jj['journals'][0]['lines'][0]['memo'] === 'zz t97h <b>memo</b>');
    [$c, $b] = http97('reports/journalsrpt?' . qs97($J + ['pageSize' => 1, 'page' => 2]), $s11); $jp = js97($b);
    ok97h('page 2 (page size 1) holds the second whole journal; totals still cover both', $c === 200 && count($jp['journals']) === 1 && $jp['journals'][0]['journalNo'] === $jn[1] && $jp['pages'] === 2 && $jp['debits'] === '1,254.50');
    foreach (['journal number' => (string) $jn[1], 'reference' => 'ref two', 'narration' => 'journal two', 'memo' => 'zz t97h <b>memo', 'amount with commas' => '1,234.50', 'amount plain' => '1234.50'] as $label => $needle) {
        [$c, $b] = http97('reports/journalsrpt?' . qs97($J + ['search' => $needle]), $s11); $js = js97($b);
        ok97h("search by $label finds exactly its journal", $c === 200 && $js['matching'] === 1, $b);
    }
    [$c, $b, $h] = http97('reports/journalsrpt?' . qs97($J + ['format' => 'xlsx']), $s11); $xml = sheet97($b);
    ok97h('Excel: every line of both journals, the totals and no markup', $c === 200 && stripos($h, 'spreadsheetml') !== false && count(array_intersect(['1234.50', '20.00', '1254.50'], nums97($xml))) === 3 && strpos($xml, '<b>') === false, json_encode(nums97($xml)));
    [$c, $b] = http97('reports/journalsrpt?' . qs97($J + ['format' => 'xlsx', 'search' => 'journal two']), $s11); $xml = sheet97($b);
    ok97h('searched Excel holds only the matching journal', $c === 200 && !in_array('1234.50', nums97($xml), true) && in_array('20.00', nums97($xml), true));
    [$c, $b] = http97('reports/journalsrpt?' . qs97($J), $s2); $j2s = js97($b);
    ok97h('another congregation\'s session sees none of these journals', $c === 200 && !in_array($jn[0], array_column($j2s['journals'], 'journalNo'), true) && !in_array($jn[1], array_column($j2s['journals'], 'journalNo'), true));
    foreach (['missing from' => ['to' => '2098-03-31'], 'missing to' => ['from' => '2098-03-01'], 'impossible date' => ['from' => '2098-02-30', 'to' => '2098-03-31'], 'text date' => ['from' => 'x', 'to' => '2098-03-31'], 'reversed dates' => ['from' => '2098-04-01', 'to' => '2098-03-01'], 'array from' => ['from' => ['2098-03-01'], 'to' => '2098-03-31'], 'page 0' => $J + ['page' => 0], 'pageSize 201' => $J + ['pageSize' => 201], 'search 101 chars' => $J + ['search' => str_repeat('a', 101)]] as $label => $params) {
        [$c] = http97('reports/journalsrpt?' . qs97($params), $s11); ok97h("journals: $label answers 400", $c === 400, "status $c");
    }

    echo "== contribution and expense reports (congregation)\n";
    $CP = ['start' => '2098-08-01', 'end' => '2098-08-31'];
    [$c, $b] = http97('reports/contributionsrpt?' . qs97(['type' => 1] + $CP), $s11); $jc1 = js97($b);
    ok97h('contributions (all): the approved, undeleted receipt lines of this congregation: 1,325.50; not the deleted or other-congregation receipts', $c === 200 && $jc1['matching'] === 3 && $jc1['total'] === '1,325.50', $b);
    ok97h('contribution rows carry the receipt number with its link', $jc1['rows'][0]['receipt'] === 'ZZ97H-OK' && strpos((string) $jc1['rows'][0]['link'], '/contributions/edit/' . $hOk) !== false);
    [$c, $b] = http97('reports/contributionsrpt?' . qs97(['type' => 2, 'account' => [31]] + $CP), $s11); $jc2 = js97($b);
    ok97h('contributions by account: only account 31 (250.50)', $c === 200 && $jc2['matching'] === 1 && $jc2['total'] === '250.50');
    [$c, $b] = http97('reports/contributionsrpt?' . qs97(['type' => 3] + $CP), $s11); $jc3 = js97($b);
    ok97h('contributions by district: one district total 250.50; the deleted receipt\'s 3,333.00 and the other congregation\'s 5,555.00 are out', $c === 200 && $jc3['total'] === '250.50' && $jc3['matching'] === 1, $b);
    [$c, $b] = http97('reports/contributionsrpt?' . qs97(['type' => 1, 'pageSize' => 2, 'page' => 2] + $CP), $s11); $jc4 = js97($b);
    ok97h('contributions page 2: the third line; the total stays the total of all matching lines', $c === 200 && count($jc4['rows']) === 1 && $jc4['total'] === '1,325.50' && $jc4['pages'] === 2);
    [$c, $b] = http97('reports/contributionsrpt?' . qs97(['type' => 1, 'search' => '<script>'] + $CP), $s11); $jc5 = js97($b);
    ok97h('contributions search by markup finds the line and returns it as data', $c === 200 && $jc5['matching'] === 2 && strpos($jc5['rows'][0]['reference'] . $jc5['rows'][0]['cont'], '<SCRIPT>') !== false);
    [$c, $b, $h] = http97('reports/contributionsrpt?' . qs97(['type' => 1, 'format' => 'xlsx'] + $CP), $s11); $xml = sheet97($b);
    ok97h('contributions Excel: all three lines and the total as numbers, markup escaped', $c === 200 && stripos($h, 'spreadsheetml') !== false && count(array_intersect(['1000.00', '250.50', '75.00', '1325.50'], nums97($xml))) === 4 && strpos($xml, '<SCRIPT>') === false, json_encode(nums97($xml)));
    [$c, $b, $h] = http97('reports/contributionsrpt?' . qs97(['type' => 3, 'format' => 'xlsx'] + $CP), $s11); $xml = sheet97($b);
    ok97h('contributions-by-district Excel works', $c === 200 && in_array('250.50', nums97($xml), true));
    $EP = ['start' => '2098-07-01', 'end' => '2098-07-31'];
    [$c, $b] = http97('reports/expensesrpt?' . qs97(['type' => 1] + $EP), $s11); $jx = js97($b);
    ok97h('expenses (all): the three approved expenses of this congregation: 3,700.00', $c === 200 && $jx['matching'] === 3 && $jx['total'] === '3,700.00', $b);
    ok97h('expense rows carry the voucher number with its link and the group as cost centre', strpos((string) $jx['rows'][0]['link'], '/expenses/print/') !== false && $jx['rows'][0]['costcenter'] !== '');
    [$c, $b] = http97('reports/expensesrpt?' . qs97(['type' => 2, 'account' => [$EXB]] + $EP), $s11); $jx2 = js97($b);
    ok97h('expenses by account: 700.00', $c === 200 && $jx2['matching'] === 1 && $jx2['total'] === '700.00');
    [$c, $b, $h] = http97('reports/expensesrpt?' . qs97(['type' => 1, 'format' => 'xlsx'] + $EP), $s11); $xml = sheet97($b);
    ok97h('expenses Excel: amounts as numbers, markup escaped', $c === 200 && stripos($h, 'spreadsheetml') !== false && count(array_intersect(['2000.00', '1000.00', '700.00', '3700.00'], nums97($xml))) === 4 && strpos($xml, '<script>') === false);
    [$c, $b] = http97('reports/expensesrpt?' . qs97(['type' => 1, 'search' => '700.00'] + $EP), $s11); $jx4 = js97($b);
    ok97h('expenses search by amount', $c === 200 && $jx4['matching'] === 1);
    $badC = [
        'contributions: reversed dates' => ['reports/contributionsrpt', ['type' => 1, 'start' => '2098-08-31', 'end' => '2098-08-01']],
        'contributions: bad type' => ['reports/contributionsrpt', ['type' => 4] + $CP],
        'contributions: unknown account' => ['reports/contributionsrpt', ['type' => 2, 'account' => [99999999]] + $CP],
        'contributions: by account without an account' => ['reports/contributionsrpt', ['type' => 2] + $CP],
        'contributions: SQL in the account' => ['reports/contributionsrpt', ['type' => 2, 'account' => ['30) OR 1=1 -- ']] + $CP],
        'contributions: an expense account' => ['reports/contributionsrpt', ['type' => 2, 'account' => [$EXA]] + $CP],
        'contributions: impossible date' => ['reports/contributionsrpt', ['type' => 1, 'start' => '2098-02-30', 'end' => '2098-08-31']],
        'contributions: page 0' => ['reports/contributionsrpt', ['type' => 1, 'page' => 0] + $CP],
        'contributions: pageSize 201' => ['reports/contributionsrpt', ['type' => 1, 'pageSize' => 201] + $CP],
        'expenses: reversed dates' => ['reports/expensesrpt', ['type' => 1, 'start' => '2098-07-31', 'end' => '2098-07-01']],
        'expenses: bad type' => ['reports/expensesrpt', ['type' => 3] + $EP],
        'expenses: a contribution account' => ['reports/expensesrpt', ['type' => 2, 'account' => [$INC]] + $EP],
        'expenses: missing dates' => ['reports/expensesrpt', ['type' => 1]],
    ];
    foreach ($badC as $label => [$path, $params]) { [$c, $b] = http97($path . '?' . qs97($params), $s11); ok97h("$label answers 400", $c === 400, "status $c"); }
    [$c] = http97('reports/contributionsrpt?' . qs97(['type' => 2, 'account' => [$INC]] + $CP), $s2);
    [$c, $b] = http97('reports/contributionsrpt?' . qs97(['type' => 1] + $CP), $s2); $jo = js97($b);
    ok97h('another congregation\'s session sees none of this congregation\'s receipts', $c === 200 && !preg_grep('/ZZ97H-OK/', array_column($jo['rows'], 'receipt')));

    echo "== parish reports\n";
    $PP = ['congregations' => [$cong, $other], 'accounts' => [$INC, 31], 'start' => '2098-08-01', 'end' => '2098-08-31'];
    [$c, $b] = http97('parishreports/contributionsrpt?' . qs97($PP), $sP); $jp1 = js97($b);
    ok97h('parish contributions: this congregation\'s approved lines (1,325.50) and the other\'s (5,555.00); the deleted receipt is out', $c === 200 && $jp1['matching'] === 4 && $jp1['total'] === '6,880.50', $b);
    [$c, $b] = http97('parishreports/bycontributorrpt?' . qs97($PP), $sP); $jp2 = js97($b);
    ok97h('parish by contributor: one total per contributor and the same grand total as the contributions report', $c === 200 && $jp2['total'] === $jp1['total'] && $jp2['matching'] <= $jp1['matching'], $b);
    [$c, $b] = http97('parishreports/expensesrpt?' . qs97(['congregations' => [$cong], 'accounts' => [$EXA, $EXB], 'start' => '2098-07-01', 'end' => '2098-07-31']), $sP); $jp3 = js97($b);
    ok97h('parish expenses: per congregation and account, 3,700.00', $c === 200 && $jp3['total'] === '3,700.00' && $jp3['matching'] === 2, $b);
    foreach (['contributionsrpt' => $PP, 'bycontributorrpt' => $PP, 'expensesrpt' => ['congregations' => [$cong], 'accounts' => [$EXA], 'start' => '2098-07-01', 'end' => '2098-07-31']] as $ep => $params) {
        [$c, $b, $h] = http97('parishreports/' . $ep . '?' . qs97($params + ['format' => 'xlsx']), $sP); $xml = sheet97($b);
        ok97h("parish $ep Excel is a workbook with real numbers", $c === 200 && stripos($h, 'spreadsheetml') !== false && $xml !== null && count(nums97($xml)) >= 1);
        [$c] = http97('parishreports/' . $ep . '?' . qs97(array_merge($params, ['start' => $params['end'], 'end' => $params['start']])), $sP); ok97h("parish $ep: reversed dates answer 400", $c === 400, "status $c");
        [$c] = http97('parishreports/' . $ep . '?' . qs97(array_merge($params, ['congregations' => [999999]])), $sP); ok97h("parish $ep: a congregation outside the parish scope answers 400", $c === 400, "status $c");
        [$c] = http97('parishreports/' . $ep . '?' . qs97(array_merge($params, ['accounts' => [99999999]])), $sP); ok97h("parish $ep: an account outside the list answers 400", $c === 400, "status $c");
        [$c] = http97('parishreports/' . $ep . '?' . qs97(array_merge($params, ['accounts' => []])), $sP); ok97h("parish $ep: an empty account list answers 400", $c === 400, "status $c");
        [$c] = http97('parishreports/' . $ep . '?' . qs97(array_merge($params, ['pageSize' => 0])), $sP); ok97h("parish $ep: pageSize 0 answers 400", $c === 400, "status $c");
    }
    [$c, $b] = http97('parishreports/contributionsrpt?' . qs97($PP + ['pageSize' => 2, 'page' => 2]), $sP); $jpp = js97($b);
    ok97h('parish page 2 holds the remaining lines; the total is of all matching lines', $c === 200 && $jpp['page'] === 2 && count($jpp['rows']) === 2 && $jpp['total'] === '6,880.50');
    [$c, $b] = http97('parishreports/contributionsrpt?' . qs97($PP + ['search' => '5,555.00']), $sP); $jps = js97($b);
    ok97h('parish search by amount', $c === 200 && $jps['matching'] === 1 && $jps['total'] === '5,555.00');

    echo "== source code: no innerHTML, no sibling walk, no HTML built by the endpoints\n";
    $root = __DIR__ . '/../public/dist/js/pages/reports/';
    $files = ['filter-list-v1.js', 'journals-v1.js', 'is/list-report-v1.js', 'pl/grouppl-v2.js', 'pl/grouppl-detail-v2.js'];
    $badJs = []; foreach ($files as $f) { $t = file_get_contents($root . $f); if (preg_match('/innerHTML|insertAdjacentHTML|nextSibling|\.html\(|document\.write/', $t) || ($f !== 'pl/grouppl-detail-v2.js' && strpos($t, 'textContent') === false)) { $badJs[] = $f; } }
    ok97h('the new report scripts write server text with textContent only', !$badJs, implode(', ', $badJs));
    $views = ['reports/groupsincomestatement', 'reports/groupplrevenuedetailed', 'reports/groupplexpensedetailed', 'reports/journals', 'reports/contributions', 'reports/expenses', 'parishreports/contributions', 'parishreports/bycontributor', 'parishreports/expenses'];
    $badV = []; foreach ($views as $v) { $t = file_get_contents(__DIR__ . '/../app/views/' . $v . '.php'); if (preg_match('/\.html\(|innerHTML|nextSibling|DataTable\(/', $t)) { $badV[] = $v; } }
    ok97h('the changed views have no .html() / innerHTML / DataTable injection of server HTML', !$badV, implode(', ', $badV));
    $ctl = file_get_contents(__DIR__ . '/../app/controllers/Reports.php') . file_get_contents(__DIR__ . '/../app/controllers/Parishreports.php');
    $bodies = ''; foreach (['Reports' => ['contributionsrpt', 'expensesrpt', 'journalsrpt', 'groupincomestatementrpt', 'groupplrevenuedetailedrpt', 'groupplexpensedetailedrpt'], 'Parishreports' => ['contributionsrpt', 'bycontributorrpt', 'expensesrpt']] as $cl => $ms) { $t = file_get_contents(__DIR__ . '/../app/controllers/' . $cl . '.php'); foreach ($ms as $m) { $a = strpos($t, 'public function ' . $m . '()'); $b = strpos($t, 'public function ', $a + 10); $bodies .= substr($t, $a, ($b === false ? strlen($t) : $b) - $a); } }
    ok97h('the report endpoints build no HTML (no table markup in their code)', $bodies !== '' && !preg_match('/<(td|tr|table|th)\b/i', $bodies));
    ok97h('HTTP responses contain no PHP warning/notice/fatal text', !$phpErrors, implode(', ', $phpErrors));
    clearstatcache(true, $apacheErrorLog); $new = '';
    if (is_file($apacheErrorLog) && filesize($apacheErrorLog) > $errorOffset) { $hh = fopen($apacheErrorLog, 'rb'); fseek($hh, $errorOffset); $new = stream_get_contents($hh); fclose($hh); }
    ok97h('Apache PHP error log has no new warning/error', !preg_match('/PHP (Warning|Fatal|Parse|Notice)|SQLSTATE|PDOException/i', $new), trim(substr($new, 0, 300)));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach (array_reverse($created['entries']) as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    foreach ($created['headers'] as $id) { $pdo->prepare('DELETE FROM tblcontributions_details WHERE HeaderId = ?')->execute([$id]); $pdo->prepare('DELETE FROM tblcontributions_header WHERE ID = ?')->execute([$id]); }
    foreach ($created['mmf'] as $id) { $pdo->prepare('DELETE FROM tblmmf WHERE ID = ?')->execute([$id]); }
    foreach ($created['exps'] as $id) { $pdo->prepare('DELETE FROM tblexpenses WHERE ID = ?')->execute([$id]); }
    foreach ($created['reqs'] as $id) { $pdo->prepare('DELETE FROM tblfundrequisition WHERE ID = ?')->execute([$id]); }
    foreach ($created['subs'] as $id) { $pdo->prepare('DELETE FROM tblbanksubaccounts WHERE ID = ?')->execute([$id]); }
    foreach ($created['groups'] as $id) { $pdo->prepare('DELETE FROM tblgroups WHERE ID = ?')->execute([$id]); }
    foreach ($created['districts'] as $id) { $pdo->prepare('DELETE FROM tbldistricts WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    foreach ($sessions as $sid) { $f = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid; if (is_file($f)) { unlink($f); } }
}
echo "$pass passed / $fail failed\n"; exit($fail ? 1 : 0);

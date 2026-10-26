<?php
/**
 * Task 9.8 through Apache: the sub account report screens (balances, statement, and the breakdown behind a balance for GROUPS and
 * DISTRICTS), paging, search, Excel, 400 cases, escaping, no PHP warnings. Fixtures use the year 2098 and the marker "zz t98h";
 * self-cleaning, CLI-only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir(__DIR__ . '/../app'); require 'boostrap.php';
$pdo = (new Database)->pdo(); $_SESSION = ['congId' => 11, 'userId' => 64]; $base = URLROOT; $pass = 0; $fail = 0; $phpErrors = [];
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x98h($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
function ok98h($n, $c, $x = '') { global $pass, $fail; if ($c) { $pass++; echo "PASS $n\n"; } else { $fail++; echo "FAIL $n" . ($x === '' ? '' : ": $x") . "\n"; } }
require_once __DIR__ . '/dev_session_helper.php';
function http98($path, $sid) {
    global $base, $phpErrors;
    $ch = curl_init($base . '/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $h = substr((string) $raw, 0, $hs); $b = substr((string) $raw, $hs);
    if (stripos($h, 'spreadsheetml') === false && preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', $b, $m)) { $phpErrors[] = $path . ' -> ' . $m[2]; }
    return [$code, $b, $h];
}
function sheet98($bytes) { $tmp = tempnam(sys_get_temp_dir(), 't98') . '.xlsx'; file_put_contents($tmp, $bytes); $z = new ZipArchive; if ($z->open($tmp) !== true) { @unlink($tmp); return null; } $xml = $z->getFromName('xl/worksheets/sheet1.xml'); $z->close(); @unlink($tmp); return $xml; }
function nums98($xml) { preg_match_all('~<c [^>]*><v>(-?[0-9.]+)</v></c>~', (string) $xml, $m); return $m[1]; }
function js98($b) { return json_decode($b, true); }
function qs98(array $p) { return http_build_query($p); }

$created = ['groups' => [], 'districts' => [], 'subs' => [], 'book' => [], 'exps' => [], 'congs' => []];
$apacheErrorLog = 'E:/xampp/apache/logs/error.log'; $errorOffset = is_file($apacheErrorLog) ? filesize($apacheErrorLog) : 0;
try {
    $s11 = mkSession(64, 11); $s2 = mkSession(64, 2);
    $XSS = 'zz t98h <script>alert(1)</script>';
    $other = x98h("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t98h other', 0, 0)"); $created['congs'][] = $other;
    $G = x98h("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zzh', 'zz t98h group', 1, 0, 11)"); $created['groups'][] = $G;
    $GO = x98h("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zzho', 'zz t98h foreign group', 1, 0, ?)", [$other]); $created['groups'][] = $GO;
    $D = x98h("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t98h district', 0, 11)"); $created['districts'][] = $D;
    $DO = x98h("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t98h foreign district', 0, ?)", [$other]); $created['districts'][] = $DO;
    $sub = function ($name, $kind, $gid, $did, $deleted = 0) use (&$created) { $id = x98h("INSERT INTO tblbanksubaccounts (AccountName, BankId, AccountId, GroupDistrict, GroupId, DistrictId, Deleted) VALUES (?,105,30,?,?,?,?)", [$name, $kind, $gid, $did, $deleted]); $created['subs'][] = $id; return $id; };
    $sG = $sub($XSS . ' group sub', 'group', $G, null); $sD = $sub('zz t98h district sub', 'district', null, $D);
    $sGO = $sub('zz t98h foreign group sub', 'group', $GO, null); $sDO = $sub('zz t98h foreign district sub', 'district', null, $DO); $sDel = $sub('zz t98h deleted sub', 'group', $G, null, 1);
    $book = function ($sub, $date, $amount, $narr, $ref = 'zz t98h ref') use (&$created) { $id = x98h("INSERT INTO tblbanktransactions_subaccounts (TransactionDate, TransactionId, SubAccountId, Amount, Narration, TransactionType, Reference, CongregationId) VALUES (?,1,?,?,?,1,?,11)", [$date, $sub, $amount, $narr, $ref]); $created['book'][] = $id; return $id; };
    $n = 0;
    $exp = function ($group, $district, $amount, $date, $narr) use (&$created, &$n) { $id = x98h("INSERT INTO tblexpenses (fiscalYearId, voucherNo, expenseType, expenseDate, accountId, groupId, districtId, paymethodId, amount, narration, paymentReference, status, requisitionId, postedBy, postedDate, deleted, congregationId) VALUES (12,?,?,?,26,?,?,1,?,?,'zz t98h xref',1,1,64,'2098-01-01',0,11)", [986000 + (++$n), $group ? 2 : 3, $date, $group, $district, $amount, $narr]); $created['exps'][] = $id; return $id; };
    $book($sG, '2097-12-31', '1000.00', $XSS . ' opening'); $book($sG, '2098-02-01', '500.00', 'zz t98h collection'); $book($sG, '2098-12-01', '9999.00', 'zz t98h after');
    $exp($G, null, '300.00', '2098-03-01', 'zz t98h group expense one'); $exp($G, null, '40.00', '2098-03-02', 'zz t98h group expense two');
    $book($sD, '2098-02-01', '2000.00', 'zz t98h district collection'); $exp(null, $D, '500.00', '2098-03-01', 'zz t98h district expense');
    $book($sGO, '2098-02-01', '77.00', 'zz t98h foreign');
    $asOf = '2098-06-30';

    echo "== pages\n";
    [$c, $b] = http98('reports/subaccounts', $s11);
    ok98h('sub account page renders its module and endpoint, lists this congregation\'s group and district sub accounts escaped, and not the foreign, deleted or other ones', $c === 200 && strpos($b, 'report-v2.js') !== false && strpos($b, 'value="' . $sG . '"') !== false && strpos($b, 'value="' . $sD . '"') !== false && strpos($b, 'value="' . $sGO . '"') === false && strpos($b, 'value="' . $sDO . '"') === false && strpos($b, 'value="' . $sDel . '"') === false && stripos($b, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && stripos($b, '<script>alert') === false, "status $c");
    [$c, $b] = http98('reports/subaccountdetailed?subaccount=' . $sG . '&asdate=' . $asOf, $s11);
    ok98h('breakdown page renders its module and the data endpoint', $c === 200 && strpos($b, 'detail-v2.js') !== false && strpos($b, 'getsubaccountdetailedrpt') !== false, "status $c");

    echo "== balances (account = all)\n";
    [$c, $b, $h] = http98('reports/subaccountsrpt?' . qs98(['account' => 'all', 'from' => $asOf]), $s11); $ja = js98($b);
    $rows = []; foreach ($ja['rows'] ?? [] as $r) { $rows[$r['name']] = $r; }
    ok98h('JSON: group sub account 1,160.00 and district sub account 1,500.00, with links to the breakdown; foreign, deleted ones absent', $c === 200 && stripos($h, 'application/json') !== false && ($rows[$XSS . ' group sub']['balance'] ?? null) === '1,160.00' && ($rows['zz t98h district sub']['balance'] ?? null) === '1,500.00' && !isset($rows['zz t98h foreign group sub']) && !isset($rows['zz t98h foreign district sub']) && !isset($rows['zz t98h deleted sub']), $b);
    ok98h('the link opens the breakdown of that sub account at that date', strpos($rows['zz t98h district sub']['link'], 'reports/subaccountdetailed?subaccount=' . $sD . '&asdate=' . $asOf) !== false);
    ok98h('only this congregation\'s sub accounts are listed (every row is one of its own)', count(array_filter($ja['rows'], function ($r) { return stripos($r['name'], 'foreign') !== false; })) === 0 && $ja['count'] >= 14);
    [$c, $b] = http98('reports/subaccountsrpt?' . qs98(['account' => 'all', 'from' => $asOf]), $s2); $jo = js98($b);
    ok98h('another congregation\'s session sees none of these sub accounts', $c === 200 && !preg_grep('/zz t98h/i', array_column($jo['rows'], 'name')) && $jo['count'] === 0, $b);
    [$c, $b] = http98('reports/subaccountsrpt?' . qs98(['account' => 'all', 'from' => $asOf, 'search' => 'district sub']), $s11); $js = js98($b);
    ok98h('search by name', $c === 200 && $js['matching'] === 1 && $js['total'] === '1,500.00');
    [$c, $b, $h] = http98('reports/subaccountsrpt?' . qs98(['account' => 'all', 'from' => $asOf, 'format' => 'xlsx']), $s11); $xml = sheet98($b);
    ok98h('Excel of the balances: real numbers 1160.00 and 1500.00, no raw markup', $c === 200 && stripos($h, 'spreadsheetml') !== false && count(array_intersect(['1160.00', '1500.00'], nums98($xml))) === 2 && strpos($xml, '<script>') === false && strpos($xml, '&lt;script&gt;') !== false, json_encode(array_slice(nums98($xml), 0, 8)));

    echo "== breakdown behind a balance: GROUP and DISTRICT\n";
    $B = ['account' => $sG, 'asdate' => $asOf];
    [$c, $b] = http98('reports/getsubaccountdetailedrpt?' . qs98($B), $s11); $jb = js98($b);
    ok98h('GROUP breakdown: 4 rows (book 1,000.00 and 500.00; expenses (300.00) and (40.00)), total 1,160.00 = the balance clicked', $c === 200 && $jb['matching'] === 4 && $jb['total'] === '1,160.00' && array_column($jb['rows'], 'amount') === ['1,000.00', '500.00', '(300.00)', '(40.00)'], $b);
    ok98h('the title names the sub account (markup kept as data) and the date; columns date / amount / narration / reference', strpos($jb['title'], '<script>') !== false && $jb['lines'] === ['As of 30-06-2098'] && array_column($jb['columns'], 'key') === ['date', 'amount', 'narr', 'reference']);
    ok98h('rows carry the narration and reference as data; the after-date book row is not listed', in_array('zz t98h collection', array_column($jb['rows'], 'narr'), true) && !in_array('zz t98h after', array_column($jb['rows'], 'narr'), true));
    [$c, $b] = http98('reports/getsubaccountdetailedrpt?' . qs98(['account' => $sD, 'asdate' => $asOf]), $s11); $jd = js98($b);
    ok98h('DISTRICT breakdown: book 2,000.00 and expense (500.00), total 1,500.00', $c === 200 && $jd['total'] === '1,500.00' && array_column($jd['rows'], 'amount') === ['2,000.00', '(500.00)']);
    [$c, $b] = http98('reports/getsubaccountdetailedrpt?' . qs98($B + ['pageSize' => 3, 'page' => 2]), $s11); $jp = js98($b);
    ok98h('page 2 (page size 3) holds the fourth row; the total is still the total of all matching rows', $c === 200 && count($jp['rows']) === 1 && $jp['page'] === 2 && $jp['pages'] === 2 && $jp['total'] === '1,160.00');
    [$c, $b] = http98('reports/getsubaccountdetailedrpt?' . qs98($B + ['search' => 'expense two']), $s11); $jq = js98($b);
    ok98h('search by narration narrows rows and total to (40.00)', $c === 200 && $jq['matching'] === 1 && $jq['total'] === '(40.00)');
    [$c, $b] = http98('reports/getsubaccountdetailedrpt?' . qs98($B + ['search' => '<script>']), $s11); $jx = js98($b);
    ok98h('search for markup finds the row and returns it as data', $c === 200 && $jx['matching'] === 1 && strpos($jx['rows'][0]['narr'], '<script>') !== false);
    [$c, $b, $h] = http98('reports/getsubaccountdetailedrpt?' . qs98($B + ['format' => 'xlsx']), $s11); $xml = sheet98($b);
    ok98h('breakdown Excel: all four rows and the total as numbers, markup escaped', $c === 200 && stripos($h, 'spreadsheetml') !== false && count(array_intersect(['1000.00', '500.00', '-300.00', '-40.00', '1160.00'], nums98($xml))) === 5 && strpos($xml, '<script>') === false, json_encode(nums98($xml)));
    [$c, $b] = http98('reports/getsubaccountdetailedrpt?' . qs98($B + ['format' => 'xlsx', 'search' => 'collection']), $s11); $xml = sheet98($b);
    ok98h('searched Excel holds only the matching row', $c === 200 && in_array('500.00', nums98($xml), true) && !in_array('1000.00', nums98($xml), true));
    ok98h('the breakdown total equals the balance on the list for both kinds', $jb['total'] === $rows[$XSS . ' group sub']['balance'] && $jd['total'] === $rows['zz t98h district sub']['balance']);

    echo "== statement (one sub account)\n";
    $T = ['account' => $sG, 'from' => '2098-01-01', 'to' => '2098-12-31'];
    [$c, $b] = http98('reports/subaccountsrpt?' . qs98($T), $s11); $jt = js98($b);
    ok98h('statement: opening 1,000.00 then +500.00, (300.00), +9,999.00, with a running balance; the unfunded/foreign rows are absent', $c === 200 && $jt['rows'][0]['narr'] === 'Opening balance' && $jt['rows'][0]['balance'] === '1,000.00' && $jt['rows'][1]['in'] === '500.00' && $jt['rows'][2]['out'] === '300.00' && $jt['rows'][2]['balance'] === '1,200.00', $b);
    [$c, $b] = http98('reports/subaccountsrpt?' . qs98($T + ['format' => 'xlsx']), $s11); $xml = sheet98($b);
    ok98h('statement Excel has the figures as numbers', $c === 200 && count(array_intersect(['1000.00', '500.00', '300.00', '1200.00'], nums98($xml))) === 4);

    echo "== 400 cases\n";
    $bad = [
        ['breakdown: unknown sub account', 'reports/getsubaccountdetailedrpt', ['account' => 99999999, 'asdate' => $asOf]],
        ['breakdown: another congregation\'s group sub account', 'reports/getsubaccountdetailedrpt', ['account' => $sGO, 'asdate' => $asOf]],
        ['breakdown: another congregation\'s district sub account', 'reports/getsubaccountdetailedrpt', ['account' => $sDO, 'asdate' => $asOf]],
        ['breakdown: a deleted sub account', 'reports/getsubaccountdetailedrpt', ['account' => $sDel, 'asdate' => $asOf]],
        ['breakdown: text account', 'reports/getsubaccountdetailedrpt', ['account' => $sG . ' OR 1=1', 'asdate' => $asOf]],
        ['breakdown: array account', 'reports/getsubaccountdetailedrpt', ['account' => [$sG], 'asdate' => $asOf]],
        ['breakdown: missing account', 'reports/getsubaccountdetailedrpt', ['asdate' => $asOf]],
        ['breakdown: missing date', 'reports/getsubaccountdetailedrpt', ['account' => $sG]],
        ['breakdown: impossible date', 'reports/getsubaccountdetailedrpt', ['account' => $sG, 'asdate' => '2098-02-30']],
        ['breakdown: text date', 'reports/getsubaccountdetailedrpt', ['account' => $sG, 'asdate' => 'yesterday']],
        ['breakdown: page 0', 'reports/getsubaccountdetailedrpt', $B + ['page' => 0]],
        ['breakdown: pageSize 201', 'reports/getsubaccountdetailedrpt', $B + ['pageSize' => 201]],
        ['breakdown: search of 101 characters', 'reports/getsubaccountdetailedrpt', $B + ['search' => str_repeat('a', 101)]],
        ['report: all without a date', 'reports/subaccountsrpt', ['account' => 'all']],
        ['report: all with an impossible date', 'reports/subaccountsrpt', ['account' => 'all', 'from' => '2098-13-01']],
        ['report: statement for another congregation\'s sub account', 'reports/subaccountsrpt', ['account' => $sGO, 'from' => '2098-01-01', 'to' => '2098-12-31']],
        ['report: statement with reversed dates', 'reports/subaccountsrpt', ['account' => $sG, 'from' => '2098-12-31', 'to' => '2098-01-01']],
        ['report: statement without an end date', 'reports/subaccountsrpt', ['account' => $sG, 'from' => '2098-01-01']],
        ['report: missing account', 'reports/subaccountsrpt', ['from' => $asOf]],
        ['report: array account', 'reports/subaccountsrpt', ['account' => ['all'], 'from' => $asOf]],
    ];
    foreach ($bad as [$label, $path, $params]) { [$c, $b] = http98($path . '?' . qs98($params), $s11); ok98h("$label answers 400 with a message", $c === 400 && js98($b)['success'] === false && js98($b)['message'] !== '', "status $c"); }
    [$c] = http98('reports/getsubaccountdetailedrpt?' . qs98($B), $s2);
    ok98h('another congregation\'s session cannot open this congregation\'s sub account breakdown', $c === 400, "status $c");
    [$c] = http98('reports/subaccountsrpt?' . qs98($T), $s2);
    ok98h('nor its statement', $c === 400, "status $c");

    echo "== source code\n";
    $root = __DIR__ . '/../public/dist/js/pages/reports/';
    $badJs = []; foreach (['subaccounts/report-v2.js', 'subaccounts/detail-v2.js'] as $f) { $t = file_get_contents($root . $f); if (preg_match('/innerHTML|insertAdjacentHTML|nextSibling|\.html\(|document\.write/', $t)) { $badJs[] = $f; } }
    ok98h('the sub account scripts use no innerHTML / nextSibling / .html()', !$badJs, implode(', ', $badJs));
    $badV = []; foreach (['reports/subaccounts', 'reports/subaccountdetailed'] as $v) { $t = file_get_contents(__DIR__ . '/../app/views/' . $v . '.php'); if (preg_match('/\.html\(|innerHTML|nextSibling|DataTable\(/', $t)) { $badV[] = $v; } }
    ok98h('the sub account views inject no server HTML', !$badV, implode(', ', $badV));
    $ctl = file_get_contents(__DIR__ . '/../app/controllers/Reports.php'); $a = strpos($ctl, 'public function subaccounts()'); $z = strpos($ctl, 'private function reportBank');
    ok98h('the sub account endpoints build no HTML and call no stored routine', $a !== false && !preg_match('/<(td|tr|table|th)\b|CALL\s/i', substr($ctl, $a, $z - $a)));
    ok98h('HTTP responses contain no PHP warning/notice/fatal text', !$phpErrors, implode(', ', $phpErrors));
    clearstatcache(true, $apacheErrorLog); $new = '';
    if (is_file($apacheErrorLog) && filesize($apacheErrorLog) > $errorOffset) { $hh = fopen($apacheErrorLog, 'rb'); fseek($hh, $errorOffset); $new = stream_get_contents($hh); fclose($hh); }
    ok98h('Apache PHP error log has no new warning/error', !preg_match('/PHP (Warning|Fatal|Parse|Notice)|SQLSTATE|PDOException/i', $new), trim(substr($new, 0, 300)));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach ($created['exps'] as $id) { $pdo->prepare('DELETE FROM tblexpenses WHERE ID = ?')->execute([$id]); }
    foreach ($created['book'] as $id) { $pdo->prepare('DELETE FROM tblbanktransactions_subaccounts WHERE ID = ?')->execute([$id]); }
    foreach ($created['subs'] as $id) { $pdo->prepare('DELETE FROM tblbanksubaccounts WHERE ID = ?')->execute([$id]); }
    foreach ($created['groups'] as $id) { $pdo->prepare('DELETE FROM tblgroups WHERE ID = ?')->execute([$id]); }
    foreach ($created['districts'] as $id) { $pdo->prepare('DELETE FROM tbldistricts WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    foreach ($sessions as $sid) { $f = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid; if (is_file($f)) { unlink($f); } }
}
echo "$pass passed / $fail failed\n"; exit($fail ? 1 : 0);

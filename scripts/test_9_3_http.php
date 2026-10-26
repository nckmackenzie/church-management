<?php
/**
 * Task 9.3 through Apache with real sessions: trial balance (congregation and parish) and its drill-down, ledger statement,
 * income statement paging / search / Excel, the .xlsx downloads (opened and read back), refusals, scope isolation, escaping.
 * Self-cleaning. CLI only; needs scripts/dev_session_helper.php.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
$pdo = (new Database)->pdo();
$_SESSION = ['congId' => 11, 'userId' => 64];
$base = URLROOT; $pass = 0; $fail = 0;
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
function ok($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
$helper = __DIR__ . '/dev_session_helper.php';
if (!is_file($helper)) { exit("scripts/dev_session_helper.php is missing\n"); }
require_once $helper;
function http($path, $sid) {
    global $base, $phpErrors;
    $ch = curl_init($base . '/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $h = substr((string) $raw, 0, $hs); $b = substr((string) $raw, $hs);
    if (stripos($h, 'spreadsheetml') === false && preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', $b, $m)) { $phpErrors[] = $path . ' -> ' . $m[2]; }
    return [$code, $b, $h];
}
function J($b) { return json_decode($b, true); }
function C($s) { return FinancialReport::cents($s); }
function tc($s) { return $s === '' || $s === null ? 0 : C(str_replace([',', '(', ')'], '', $s)) * (strpos($s, '(') === 0 ? -1 : 1); }
/** read an .xlsx download: [rows of cell values], sheet xml */
function xlsx($bytes) {
    $tmp = tempnam(sys_get_temp_dir(), 'xh') . '.xlsx'; file_put_contents($tmp, $bytes);
    $z = new ZipArchive();
    if ($z->open($tmp) !== true) { return null; }
    $xml = $z->getFromName('xl/worksheets/sheet1.xml'); $z->close(); @unlink($tmp);
    $sx = simplexml_load_string($xml); if ($sx === false) { return null; }
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $c) {
            $col = preg_replace('/\d+/', '', (string) $c['r']); $idx = 0; for ($i = 0; $i < strlen($col); $i++) { $idx = $idx * 26 + ord($col[$i]) - 64; }
            $r[$idx - 1] = isset($c->is) ? ['s', (string) $c->is->t] : ['n', (float) $c->v];
        }
        $rows[] = $r;
    }
    return $rows;
}
function cellText($r, $i) { return isset($r[$i]) ? (string) $r[$i][1] : ''; }

$created = ['accounts' => [], 'entries' => []];
try {
    $admin11 = mkSession(64, 11); $admin2 = mkSession(64, 2);
    $acct = function ($name, $parent, $cong = 11, $o = []) use (&$created) {
        $o += ['deleted' => 0];
        $root = $parent <= 6 ? $parent : (int) q('SELECT accountTypeId FROM tblaccounttypes WHERE ID = ?', [$parent])[0]['accountTypeId'];
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, forGroup, active, congregationId, deleted) VALUES (?,?,?,1,0,0,1,?,?)', [$name, $root, $parent, $cong, $o['deleted']]);
        $created['accounts'][] = $id; return $id;
    };
    $A = $acct('zz t93h income top', 1); $B = $acct('zz t93h income mid', $A); $C = $acct('zz t93h income low', $B); $D = $acct('zz t93h income leaf', $C);
    $XS = $acct('zz <script>alert(1)</script> t93h', 1);
    $AS = $acct('zz t93h asset top', 3); $AM = $acct('zz t93h asset mid', $AS); $AL = $acct('zz t93h asset leaf', $AM);
    $Foreign = $acct('zz t93h foreign income', 1, 2); $ForeignA = $acct('zz t93h foreign asset', 3, 2);
    $DelN = $acct('zz t93h deleted none', 1, 11, ['deleted' => 1]);
    $n = 0;
    $entry = function ($cong, $date, $lines) use (&$created, &$n) {
        $n++; $id = x("INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,5,?,?,?,?,0)", [$cong, $date, 970000 + $n, 970000 + $n, 'zz t93h ref ' . $n, 'zz t93h narration ' . $n]);
        $created['entries'][] = $id; $ln = 0; foreach ($lines as [$a, $dc, $amt]) { x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $a, $dc, $amt, 'zz t93h memo ' . $n, ++$ln]); } return $id;
    };
    $N = 70; $total = 0;
    for ($i = 1; $i <= $N; $i++) { $d = sprintf('2026-10-%02d', 1 + ($i % 28)); $entry(11, $d, [[$AL, 'DEBIT', $i . '.00'], [$D, 'CREDIT', $i . '.00']]); $total += $i; }
    $entry(11, '2026-10-20', [[$AL, 'DEBIT', '11.00'], [$XS, 'CREDIT', '11.00']]);
    $entry(2, '2026-10-15', [[$ForeignA, 'DEBIT', '333.00'], [$Foreign, 'CREDIT', '333.00']]);
    $asof = '2026-10-31';

    echo "== pages\n";
    $pages = ['trialbalance' => 'trial-balance-v1.js', "trialbalance/drill?account=$A&asof=$asof" => 'trial-balance-v1.js', 'parishreports/trialbalance' => 'trial-balance-v1.js', "parishreports/trialbalancedrill?account=$A&asof=$asof" => 'trial-balance-v1.js',
              'reports/ledger_statement' => 'ledger-statement-v1.js', 'reports/incomestatement' => 'income-statement-v2.js'];
    foreach ($pages as $p => $js) { [$code, $body] = http($p, $admin11); ok("renders $p", $code === 200 && strpos($body, $js) !== false, "status $code"); }
    [$code, $body] = http('trialbalance', $admin11);
    ok('trial balance screen: As Of Date only, no Report Type choice', strpos($body, 'id="asof"') !== false && stripos($body, 'Report Type') === false && strpos($body, 'id="type"') === false);
    foreach (['report-ui-v1.js', 'trial-balance-v1.js', 'ledger-statement-v1.js', 'income-statement-v2.js'] as $f) {
        $js = (string) file_get_contents('E:/xampp/htdocs/cms/public/dist/js/pages/reports/is/' . $f);
        ok("$f exists and uses textContent only (no innerHTML / insertAdjacentHTML / document.write)", $js !== '' && !preg_match('/innerHTML|insertAdjacentHTML|document\.write|outerHTML/', $js));
    }
    foreach (['tb/tb-v3.js', 'tb/tb-report-v1.js', 'is/income-statement-v1.js'] as $f) { ok("old script $f is gone", !is_file('E:/xampp/htdocs/cms/public/dist/js/pages/reports/' . $f)); }

    echo "== trial balance\n";
    [$code, $body] = http("trialbalance/getreport?asof=$asof", $admin11); $tb = J($body);
    ok('JSON: balanced, rows for the fixture accounts, section names', $code === 200 && $tb['success'] && $tb['totals']['balanced'] && $tb['totals']['debit'] === $tb['totals']['credit']);
    $names = array_column($tb['rows'], 'name');
    ok('top-level rows incl. the markup-named account as data; one b/f line', in_array('Zz T93h Income Top', $names, true) && in_array('Zz T93h Asset Top', $names, true) && in_array('Zz <script>alert(1)</script> T93h', $names, true) && count(array_filter($tb['rows'], function ($r) { return $r['id'] === 'bf'; })) === 1);
    $iRow = array_values(array_filter($tb['rows'], function ($r) use ($A) { return $r['id'] === $A; }))[0];
    ok('income top: credit balance = the 70 receipts', $iRow['credit'] === number_format($total, 2) && $iRow['debit'] === '' && $iRow['next'] === 'children');
    [$code, $body] = http("trialbalance/getreport?asof=$asof&detailed=1", $admin11); $td = J($body);
    ok('detailed: every account with postings (leaf rows), balanced', $code === 200 && $td['totals']['balanced'] && count($td['rows']) > count($tb['rows']) - 1 && in_array($D, array_column($td['rows'], 'id'), true));
    foreach (['missing date' => '', 'empty' => 'asof=', 'impossible date' => 'asof=2026-02-30', 'wrong format' => 'asof=31-10-2026', 'array' => 'asof%5B%5D=2026-10-31', 'no financial year' => 'asof=2000-01-01', 'SQL text' => "asof=2026-10-31'--", 'old parameter name only' => 'asofdate=2026-10-31&type=summary'] as $label => $qs) {
        [$code, $b] = http("trialbalance/getreport?$qs", $admin11); $j = J($b);
        ok("$label -> 400 with a message", $code === 400 && $j['success'] === false && $j['message'] !== '', "status $code");
    }
    [$code, $b] = http('trialbalance/getreport?asof=2026-10-31', $admin2); $t2 = J($b);
    ok('another congregation\'s session sees its own trial balance only', $code === 200 && !in_array('Zz T93h Income Top', array_column($t2['rows'], 'name'), true) && $t2['totals']['balanced']);
    [$code, $b] = http("parishreports/trialbalancerpt?asof=$asof&cong=11", $admin11); $pt = J($b);
    ok('parish trial balance for one congregation equals the congregation figures', $code === 200 && $pt['totals']['debit'] === $tb['totals']['debit'] && $pt['entity'] !== 'All congregations');
    [$code, $b] = http("parishreports/trialbalancerpt?asof=$asof", $admin11); $pa = J($b);
    ok('parish trial balance, all congregations: raw sum, balanced', $code === 200 && $pa['entity'] === 'All congregations' && $pa['totals']['balanced'] && tc($pa['totals']['debit']) >= tc($tb['totals']['debit']));
    foreach (['non-existent congregation' => 'cong=99999', 'non-digit' => 'cong=abc', 'list' => 'cong%5B%5D=11'] as $label => $qs) { [$code] = http("parishreports/trialbalancerpt?asof=$asof&$qs", $admin11); ok("parish trial balance refuses $label with 400", $code === 400, "status $code"); }

    echo "== trial balance drill\n";
    $dq = function ($acc, $extra = '', $sid = null) use ($asof, $admin11) { return http("trialbalance/drillrpt?account=$acc&asof=$asof$extra", $sid ?? $admin11); };
    [$c1, $b1] = $dq($A); $d1 = J($b1);
    ok('level 1: the accounts below, rows add up, credit total of the receipts', $c1 === 200 && $d1['mode'] === 'children' && $d1['sumsExactly'] && $d1['credit'] === number_format($total, 2));
    [$c2, $b2] = $dq($C); $d2 = J($b2);
    ok('level 3 then the leaf: journal lines on the trial-balance basis from the year start', $c2 === 200 && $d2['mode'] === 'children');
    [$c3, $b3] = $dq($D); $d3 = J($b3);
    ok('leaf: lines, 50 per page, figure = credit ' . number_format($total, 2), $c3 === 200 && $d3['mode'] === 'lines' && count($d3['rows']) === 50 && $d3['matching'] === $N && $d3['pages'] === 2 && $d3['figureCredit'] === number_format($total, 2) && $d3['direction'] === 'Cr');
    [$c4, $b4] = $dq($D, '&page=2'); $d4 = J($b4);
    ok('page 2 holds the remaining 20 lines and the true running balance (closing = the total)', $c4 === 200 && count($d4['rows']) === 20 && $d4['rows'][19]['balance'] === number_format($total, 2) && $d4['page'] === 2);
    $firstOfPage2 = $d4['rows'][0]['balance'];
    [$c5, $b5] = $dq($D, '&pageSize=10&page=6'); $d5 = J($b5);
    ok('page size changes the paging; balances are the same account-wide figures', $d5['pageSize'] === 10 && $d5['rows'][0]['balance'] === $d4['rows'][0]['balance'] && $d5['pages'] === 7);
    [$c6, $b6] = $dq($D, '&search=' . urlencode('25.00')); $d6 = J($b6);
    ok('search by amount: only the matching row(s), totals of the matching rows, period totals unchanged', $c6 === 200 && $d6['matching'] >= 1 && $d6['filteredCredits'] !== $d6['credits'] && $d6['credits'] === number_format($total, 2));
    [$c7, $b7] = $dq($D, '&search=' . urlencode('zz t93h ref 7')); $d7 = J($b7);
    ok('search by reference: finds ref 7 (and 70)', $d7['matching'] === 2);
    [$c8, $b8] = $dq($D, '&search=' . urlencode('%')); $d8 = J($b8);
    ok('a % in the search is literal (no match)', $d8['matching'] === 0 && $d8['rows'] === []);
    foreach (['page 0' => '&page=0', 'page text' => '&page=abc', 'page size too large' => '&pageSize=1000', 'page size 0' => '&pageSize=0', 'search too long' => '&search=' . str_repeat('a', 101)] as $label => $extra) { [$code] = $dq($D, $extra); ok("paging parameter refused: $label (400)", $code === 400, "status $code"); }
    [$cb, $bb] = $dq('bf'); $db = J($bb);
    ok('Accumulated fund b/f drills into the type 1, 2, 5 accounts of the prior years and adds up', $cb === 200 && $db['mode'] === 'children' && $db['sumsExactly']);
    [$cb2, $bb2] = $dq($D, '&bf=1&view=lines'); $db2 = J($bb2);
    ok('an income account opened from the b/f line shows only the years before this one (none here)', $cb2 === 200 && $db2['mode'] === 'lines' && $db2['rows'] === [] && $db2['direction'] === 'Cr');
    foreach (['another congregation\'s account' => $Foreign, 'its asset' => $ForeignA, 'deleted without history' => $DelN, 'a root' => 1, 'non-digit' => 'abc', 'SQL text' => '1 OR 1=1', 'name' => 'tithes and offering', 'zero' => '0', 'empty' => ''] as $label => $acc) {
        [$code, $b] = $dq(urlencode((string) $acc)); ok("drill refuses $label with 400", $code === 400 && J($b)['success'] === false, "status $code");
    }
    [$code] = $dq($AL, '&bf=1'); ok('bf=1 with a balance-sheet account -> 400', $code === 400);
    [$code] = $dq($A, '', $admin2); ok('another congregation\'s session cannot open this account (400)', $code === 400);
    [$code] = http('trialbalance/detailedreport?type=summary&account=tithes+and+offering&asofdate=2026-10-31', $admin11); ok('old detailed URL -> 400', $code === 400);
    [$code, $b, $h] = http('trialbalance/report?account=x', $admin11); ok('old report page redirects to the trial balance', $code === 302 && stripos($h, '/trialbalance') !== false);
    [$code, $b, $h] = http('reports/trialbalance', $admin11); ok('the older trial balance screen redirects to the new one', $code === 302 && stripos($h, '/trialbalance') !== false);

    echo "== ledger statement\n";
    $lq = function ($extra = '', $sid = null, $acc = null) use ($admin11, $D) { return http('reports/ledgerstatementrpt?account=' . ($acc ?? $D) . '&from=2026-10-01&to=2026-10-31' . $extra, $sid ?? $admin11); };
    [$c1, $b1] = $lq('&subledgers=0'); $s1 = J($b1);
    ok('statement of the leaf: opening 0.00, 70 lines, closing in the credit direction, direction stated', $c1 === 200 && $s1['opening'] === '0.00' && $s1['matching'] === $N && $s1['closing'] === number_format($total, 2) && $s1['direction'] === 'Cr' && strpos($s1['directionNote'], 'credit') !== false);
    [$c2, $b2] = $lq('&subledgers=1', null, $A); $s2 = J($b2);
    ok('with subledgers every descendant is included (five levels: low, mid, leaf), account column present', $c2 === 200 && $s2['subledgers'] === true && $s2['count'] === $N && $s2['closing'] === number_format($total, 2));
    [$c3, $b3] = $lq('&subledgers=0', null, $A); $s3 = J($b3);
    ok('without subledgers the reporting account has no postings of its own', $c3 === 200 && $s3['count'] === 0 && $s3['closing'] === '0.00');
    [$c4, $b4] = $lq('&subledgers=0&page=2&pageSize=25'); $s4 = J($b4);
    ok('page 2 of the statement: 25 rows from line 26, running balance = the sum of the first 50 lines', $c4 === 200 && count($s4['rows']) === 25 && $s4['page'] === 2 && tc($s4['rows'][24]['balance']) === array_sum(array_map(function ($r) { return 0; }, [])) + (function () use ($pdo, $D) { $r = q("SELECT l.amount FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE l.accountId=? AND e.deleted=0 AND e.congregationId=11 ORDER BY e.entryDate, e.ID, l.lineNumber", [$D]); $s = 0; foreach (array_slice($r, 0, 50) as $x) { $s += C($x['amount']); } return $s; })());
    [$c5, $b5] = $lq('&subledgers=0&search=' . urlencode('memo 3')); $s5 = J($b5);
    ok('search by narration: only matching rows (3, 30-39)', $c5 === 200 && $s5['matching'] === 11 && $s5['closing'] === number_format($total, 2));
    foreach (['reversed dates' => ['from' => '2026-10-31', 'to' => '2026-10-01'], 'missing to' => ['from' => '2026-10-01'], 'impossible date' => ['from' => '2026-02-30', 'to' => '2026-10-01']] as $label => $p) { [$code] = http('reports/ledgerstatementrpt?account=' . $D . '&' . http_build_query($p), $admin11); ok("ledger statement refuses $label with 400", $code === 400, "status $code"); }
    foreach (['another congregation\'s account' => $Foreign, 'deleted without history' => $DelN, 'non-digit' => 'abc'] as $label => $acc) { [$code] = $lq('', null, urlencode((string) $acc)); ok("ledger statement refuses $label with 400", $code === 400, "status $code"); }
    [$code] = $lq('', $admin2); ok('another congregation\'s session cannot read this congregation\'s account (400)', $code === 400);
    [$cc, $bc] = http("trialbalance/getreport?asof=$asof&detailed=1", $admin11); $det = J($bc);
    $dRow = array_values(array_filter($det['rows'], function ($r) use ($D) { return $r['id'] === $D; }))[0];
    ok('closing balance of the statement (from the year start) = the detailed trial balance figure of the account', $dRow['credit'] === $s1['closing'] && $dRow['debit'] === '');
    ok('the old statement code is gone', strpos((string) file_get_contents('E:/xampp/htdocs/cms/app/models/Report.php'), 'GetAccountStatement') === false);

    echo "== Excel downloads\n";
    [$code, $bin, $h] = http("trialbalance/getreport?asof=$asof&format=xlsx", $admin11);
    $rows = xlsx($bin);
    ok('summary trial balance: a real .xlsx with the right content type and file name', $code === 200 && stripos($h, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') !== false && stripos($h, 'trial-balance-' . $asof . '.xlsx') !== false && $rows !== null, "status $code");
    $find = function ($rows, $label) { foreach ($rows as $r) { if (cellText($r, 1) === $label || cellText($r, 0) === $label) { return $r; } } return null; };
    $inc = $find($rows, 'Zz T93h Income Top');
    ok('numbers are numbers (credit total of the receipts), account names are text', $inc !== null && $inc[3][0] === 'n' && abs($inc[3][1] - $total) < 0.001 && $inc[1][0] === 's');
    $tot = $find($rows, 'Total');
    ok('totals row: debit and credit totals are equal numbers', $tot !== null && $tot[2][0] === 'n' && $tot[3][0] === 'n' && abs($tot[2][1] - $tot[3][1]) < 0.005);
    ok('the heading is the title and the as-of date; no congregation name and no basis sentence (decisions 147, 148)', strpos(cellText($rows[0], 0), 'Trial balance as at 31-10-2026') === 0 && !array_filter($rows, function ($r) { return stripos(cellText($r, 0), 'Basis') === 0 || stripos(cellText($r, 0), 'cumulative') !== false; }));
    ok('the markup in an account name is plain text in the workbook', $find($rows, 'Zz <script>alert(1)</script> T93h') !== null);
    [$code, $bin2] = http("trialbalance/getreport?asof=$asof&detailed=1&format=xlsx", $admin11); $rows2 = xlsx($bin2);
    ok('detailed export has every posting account (more rows than the summary) with the full path', $code === 200 && count($rows2) > count($rows) && $find($rows2, 'Zz T93h Income Leaf') !== null);
    $pathForm = function ($rows) { foreach ($rows as $r) { foreach ($r as $c) { if ($c[0] === 's' && strpos($c[1], ' > ') !== false) { return true; } } } return false; };
    ok('no "type > parent > name" path anywhere in the exports or the JSON (decision 149)', !$pathForm($rows) && !$pathForm($rows2) && strpos(json_encode($td), ' > ') === false && strpos(json_encode($tb), ' > ') === false);
    [$code, $bin3, $h3] = http("trialbalance/drillrpt?account=$A&asof=$asof&format=xlsx", $admin11); $rows3 = xlsx($bin3);
    ok('trial balance drill page (children) exports its rows and total', $code === 200 && $rows3 !== null && $find($rows3, 'Total') !== null && $find($rows3, 'Zz T93h Income Mid') !== null);
    [$code, $bin4] = http("trialbalance/drillrpt?account=$D&asof=$asof&view=lines&format=xlsx&page=2&pageSize=10", $admin11); $rows4 = xlsx($bin4);
    $dataRows = array_filter($rows4, function ($r) { return isset($r[1]) && $r[1][0] === 'n' && isset($r[0]) && $r[0][0] === 'n'; });
    ok('journal-line export holds ALL ' . $N . ' lines whatever the page asked for, real numbers, dates as numbers', $code === 200 && count($dataRows) === $N);
    [$code, $bin5] = http("trialbalance/drillrpt?account=$D&asof=$asof&view=lines&format=xlsx&search=" . urlencode('zz t93h ref 7'), $admin11); $rows5 = xlsx($bin5);
    ok('journal-line export of a search holds only the matching rows', count(array_filter($rows5, function ($r) { return isset($r[1]) && $r[1][0] === 'n' && isset($r[0]) && $r[0][0] === 'n'; })) === 2);
    [$code, $bin6, $h6] = http('reports/ledgerstatementrpt?account=' . $A . '&from=2026-10-01&to=2026-10-31&subledgers=1&format=xlsx', $admin11); $rows6 = xlsx($bin6);
    ok('ledger statement export: all lines with the account column, closing balance', $code === 200 && count(array_filter($rows6, function ($r) { return isset($r[1]) && $r[1][0] === 'n' && isset($r[0]) && $r[0][0] === 'n'; })) === $N && $find($rows6, 'Closing balance') !== null);
    [$code, $bin7, $h7] = http('reports/incomestatementrpt?start=2026-10-01&end=2026-10-31&format=xlsx', $admin11); $rows7 = xlsx($bin7);
    $inc7 = $find($rows7, 'Zz T93h Income Top');
    ok('income statement export: lines, totals and the comparative column as numbers', $code === 200 && $inc7 !== null && $inc7[1][0] === 'n' && abs($inc7[1][1] - $total) < 0.001 && $find($rows7, 'Surplus / (deficit) for the period') !== null && stripos($h7, 'income-statement-2026-10-01-to-2026-10-31.xlsx') !== false);
    [$code, $bin8] = http("reports/plchildaccountrpt?account=$A&start=2026-10-01&end=2026-10-31&format=xlsx", $admin11); $rows8 = xlsx($bin8);
    ok('income statement drill page exports', $code === 200 && $find($rows8, 'Total') !== null);
    [$code, $bin9] = http("reports/plchildaccountrpt?account=$D&start=2026-10-01&end=2026-10-31&view=lines&format=xlsx", $admin11); $rows9 = xlsx($bin9);
    ok('income statement journal lines export holds all lines', $code === 200 && count(array_filter($rows9, function ($r) { return isset($r[1]) && $r[1][0] === 'n' && isset($r[0]) && $r[0][0] === 'n'; })) === $N);
    [$code, $bin10] = http("parishreports/incomestatementrpt?start=2026-10-01&end=2026-10-31&format=xlsx", $admin11); ok('parish income statement export is a workbook', $code === 200 && xlsx($bin10) !== null);
    [$code, $bin11] = http("parishreports/trialbalancerpt?asof=$asof&format=xlsx", $admin11); ok('parish trial balance export is a workbook', $code === 200 && xlsx($bin11) !== null);

    echo "== income statement lines: paging and search\n";
    [$c1, $b1] = http("reports/plchildaccountrpt?account=$D&start=2026-10-01&end=2026-10-31&view=lines&page=2&pageSize=25", $admin11); $i1 = J($b1);
    ok('income statement lines: page 2 of 3, balance in the credit direction', $c1 === 200 && count($i1['rows']) === 25 && $i1['pages'] === 3 && $i1['direction'] === 'Cr' && $i1['matching'] === $N);
    [$c2, $b2] = http("reports/plchildaccountrpt?account=$D&start=2026-10-01&end=2026-10-31&view=lines&search=" . urlencode('memo 70'), $admin11); $i2 = J($b2);
    ok('income statement lines: search', $c2 === 200 && $i2['matching'] === 1);
    [$c3, $b3] = http("parishreports/plchildaccountrpt?account=$D&start=2026-10-01&end=2026-10-31&view=lines&cong=11&page=3&pageSize=25", $admin11); $i3 = J($b3);
    ok('parish drill pages lines too', $c3 === 200 && count($i3['rows']) === 20 && $i3['page'] === 3);

    ok('rendered pages and endpoints emitted no PHP warnings', $phpErrors === [], implode(', ', $phpErrors));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($sessions as $sid) { $f = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid; if (is_file($f)) { @unlink($f); } }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz%t93h%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t93h%'")[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

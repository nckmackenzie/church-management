<?php
/**
 * Task 9.4 through Apache with real sessions: balance sheet (congregation and parish), every drill level, Excel downloads read back,
 * refusals, scope isolation, escaping; decisions 144 (income accounts start every financial year at zero), 147 (no congregation
 * heading), 148 (no basis text), 149 (account names only, never the path). Self-cleaning. CLI only.
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
function hasPath($v) { if (is_array($v)) { foreach ($v as $x) { if (hasPath($x)) { return true; } } return false; } return is_string($v) && strpos($v, ' > ') !== false; }

$created = ['accounts' => [], 'entries' => [], 'congs' => []];
try {
    // two test congregations: the numbers below are then exact, whatever the real data holds
    $T  = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t94h main', 0, 0)"); $created['congs'][] = $T;
    $T2 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t94h other', 0, 0)"); $created['congs'][] = $T2;
    $admin11 = mkSession(64, $T); $admin2 = mkSession(64, $T2);
    $acct = function ($name, $parent, $cong = null, $o = []) use (&$created, $T) {
        $cong = $cong ?? $T;
        $o += ['deleted' => 0];
        $root = $parent <= 6 ? $parent : (int) q('SELECT accountTypeId FROM tblaccounttypes WHERE ID = ?', [$parent])[0]['accountTypeId'];
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, forGroup, active, congregationId, deleted) VALUES (?,?,?,1,0,0,1,?,?)', [$name, $root, $parent, $cong, $o['deleted']]);
        $created['accounts'][] = $id; return $id;
    };
    $A = $acct('zz t94h asset top', 3); $AM = $acct('zz t94h asset mid', $A); $AL = $acct('zz t94h asset leaf', $AM);
    $XS = $acct('zz <script>alert(1)</script> t94h', 3); $XSL = $acct('zz t94h asset markup leaf', $XS);
    $Q = $acct('zz t94h equity top', 6); $QL = $acct('zz t94h equity leaf', $Q);
    $I = $acct('zz t94h income top', 1); $IL = $acct('zz t94h income leaf', $I);
    $E = $acct('zz t94h expense top', 2); $EL = $acct('zz t94h expense leaf', $E);
    $D = $acct('zz t94h dividend top', 5); $DL = $acct('zz t94h dividend leaf', $D);
    $Foreign = $acct('zz t94h foreign asset', 3, $T2); $ForeignQ = $acct('zz t94h foreign equity', 6, $T2);
    $DelN = $acct('zz t94h deleted none', 3, $T, ['deleted' => 1]);
    $Dup1 = $acct('zz t94h same name', $A); $Dup2 = $acct('zz t94h same name', $XS);
    $n = 0;
    $entry = function ($cong, $date, $lines) use (&$created, &$n) {
        $n++; $id = x("INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,5,?,?,?,?,0)", [$cong, $date, 980000 + $n, 980000 + $n, 'zz t94h ref ' . $n, 'zz t94h narration ' . $n]);
        $created['entries'][] = $id; $ln = 0; foreach ($lines as [$a, $dc, $amt]) { x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $a, $dc, $amt, 'zz t94h memo ' . $n, ++$ln]); } return $id;
    };
    $entry($T, '2025-09-29', [[$AL, 'DEBIT', '10.00'], [$IL, 'CREDIT', '10.00']]);
    $entry($T, '2025-09-30', [[$AL, 'DEBIT', '5.00'], [$IL, 'CREDIT', '5.00']]);
    $entry($T, '2025-10-01', [[$AL, 'DEBIT', '7.00'], [$IL, 'CREDIT', '7.00']]);
    $entry($T, '2025-10-02', [[$EL, 'DEBIT', '3.00'], [$AL, 'CREDIT', '3.00']]);
    $entry($T, '2025-10-03', [[$DL, 'DEBIT', '2.00'], [$AL, 'CREDIT', '2.00']]);
    $entry($T, '2025-10-04', [[$AL, 'DEBIT', '100.00'], [$QL, 'CREDIT', '100.00']]);
    $entry($T, '2025-10-05', [[$XSL, 'DEBIT', '1.00'], [$QL, 'CREDIT', '1.00']]);
    $entry($T2, '2025-10-04', [[$Foreign, 'DEBIT', '333.00'], [$ForeignQ, 'CREDIT', '333.00']]);
    $asof = '2025-10-31';

    echo "== pages\n";
    foreach (['reports/balancesheet', "reports/balancesheetdrill?account=$A&asof=$asof", 'parishreports/balancesheet', "parishreports/balancesheetdrill?account=$A&asof=$asof"] as $p) {
        [$code, $body] = http($p, $admin11); ok("renders $p", $code === 200 && strpos($body, 'balance-sheet-v1.js') !== false && strpos($body, 'id="isapp"') !== false, "status $code");
    }
    [$code, $body] = http('reports/balancesheet', $admin11);
    ok('the balance sheet screen has an As Of Date only', strpos($body, 'id="asof"') !== false && strpos($body, 'todate') === false);
    foreach (['balance-sheet-v1.js', 'report-ui-v1.js'] as $f) { $js = (string) file_get_contents('E:/xampp/htdocs/cms/public/dist/js/pages/reports/is/' . $f); ok("$f has no innerHTML / insertAdjacentHTML / document.write", $js !== '' && !preg_match('/innerHTML|insertAdjacentHTML|document\.write|outerHTML/', $js)); }
    foreach (['app/views/reports/balancesheetdetailed.php', 'app/views/reports/balancesheetchilddetails.php'] as $f) { ok("old view $f is gone", !is_file('E:/xampp/htdocs/cms/' . $f)); }

    echo "== balance sheet JSON\n";
    [$code, $body] = http("reports/balancesheetrpt?asof=$asof", $admin11); $r = J($body);
    ok('report: success, no congregation heading (decision 147), no explanatory text fields (decision 148)', $code === 200 && $r['success'] && $r['entity'] === '' && !isset($r['basis']) && !preg_match('/cumulative to the date/i', $body));
    $names = array_column($r['assets']['rows'], 'name');
    ok('assets: fixture accounts by name, the markup in a name comes back as data', in_array('Zz T94h Asset Top', $names, true) && in_array('Zz <script>alert(1)</script> T94h', $names, true));
    ok('equity block: the type 6 account, then b/f, surplus to date, dividends (non-zero)', array_column($r['equity']['rows'], 'name') === ['Zz T94h Equity Top'] && array_column($r['fund']['rows'], 'name') === ['Accumulated fund b/f', 'Surplus / (deficit) for the year to date', 'Dividends']);
    $eq = []; foreach (array_merge($r['equity']['rows'], $r['fund']['rows']) as $x) { $eq[$x['name']] = $x; }
    ok('b/f = 15.00 (earlier year), surplus 7 - 3 = 4.00, dividends (2.00); total assets = 15 + 1 + ... balanced', $eq['Accumulated fund b/f']['amount'] === '15.00' && $eq['Surplus / (deficit) for the year to date']['amount'] === '4.00' && $eq['Dividends']['amount'] === '(2.00)' && $r['balanced'] === true && $r['difference']['amount'] === '0.00');
    ok('comparative = the last financial year-end (2025-09-30)', $r['cmpAsOf'] === '2025-09-30' && $r['assets']['cmpTotal'] !== null);
    ok('no "type > parent > name" path anywhere in the JSON (decision 149)', !hasPath($r));
    [$code, $body] = http("reports/balancesheetrpt?asof=$asof", $admin2); $r2 = J($body);
    ok('another congregation\'s session sees its own balance sheet only', $code === 200 && !in_array('Zz T94h Asset Top', array_column($r2['assets']['rows'], 'name'), true) && in_array('Zz T94h Foreign Asset', array_column($r2['assets']['rows'], 'name'), true));
    foreach (['missing' => '', 'empty' => 'asof=', 'impossible' => 'asof=2026-02-30', 'format' => 'asof=31-10-2025', 'array' => 'asof%5B%5D=2025-10-31', 'no financial year' => 'asof=2000-01-01', 'SQL text' => "asof=2025-10-31'--", 'the old parameter' => 'todate=2025-10-31'] as $label => $qs) {
        [$code, $b] = http("reports/balancesheetrpt?$qs", $admin11); $j = J($b); ok("$label -> 400 with a message", $code === 400 && $j['success'] === false && $j['message'] !== '', "status $code");
    }
    [$code, $b] = http("parishreports/balancesheetrpt?asof=$asof&cong=$T", $admin11); $pr = J($b);
    ok('parish, one congregation: the line naming the scope is the only heading', $code === 200 && $pr['entity'] !== '' && $pr['assets']['total'] === $r['assets']['total']);
    [$code, $b] = http("parishreports/balancesheetrpt?asof=$asof", $admin11); $pa = J($b);
    ok('parish, all congregations: "All congregations", raw sum, balanced', $code === 200 && $pa['entity'] === 'All congregations' && $pa['balanced'] === true);
    foreach (['non-existent congregation' => 'cong=99999', 'non-digit' => 'cong=abc', 'list' => 'cong%5B%5D=' . $T] as $label => $qs) { [$code] = http("parishreports/balancesheetrpt?asof=$asof&$qs", $admin11); ok("parish refuses $label with 400", $code === 400, "status $code"); }

    echo "== drill\n";
    $dq = function ($acc, $extra = '', $sid = null) use ($asof, $admin11) { return http("reports/balancesheetdrillrpt?account=$acc&asof=$asof$extra", $sid ?? $admin11); };
    [$c1, $b1] = $dq($A); $d1 = J($b1);
    ok('level 1: rows add up, amount = the asset top figure', $c1 === 200 && $d1['mode'] === 'children' && $d1['sumsExactly'] && $d1['amount'] === '117.00');
    [$c2, $b2] = $dq($AL); $d2 = J($b2);
    ok('leaf: journal lines from inception with the cumulative running balance and closing = the figure', $c2 === 200 && $d2['mode'] === 'lines' && $d2['opening'] === '0.00' && $d2['matching'] === 6 && $d2['closing'] === '117.00' && $d2['direction'] === 'Dr', $b2);
    [$cb, $bb] = $dq('bf'); $db = J($bb);
    ok('Accumulated fund b/f opens into the earlier years\' income / expense accounts and adds up', $cb === 200 && $db['mode'] === 'children' && $db['sumsExactly'] && $db['amount'] === '15.00' && $db['fund'] === 'bf');
    [$cb2, $bb2] = $dq($IL, '&fund=bf&view=lines'); $db2 = J($bb2);
    ok('an income account opened from b/f: the earlier years only (10 + 5), cumulative', $cb2 === 200 && $db2['matching'] === 2 && $db2['closing'] === '15.00' && $db2['direction'] === 'Cr');
    [$cd, $bd] = $dq('div'); $dd = J($bd);
    ok('dividends open into the type 5 accounts', $cd === 200 && $dd['mode'] === 'children' && $dd['sumsExactly'] && $dd['amount'] === '(2.00)');
    [$cd2, $bd2] = $dq($DL, '&fund=div&view=lines'); $dd2 = J($bd2);
    ok('the dividend account lines (year to date)', $cd2 === 200 && $dd2['matching'] === 1);
    [$cp, $bp] = $dq($AL, '&view=lines&pageSize=2&page=3'); $dp = J($bp);
    ok('paging: page 3 of 2 per page holds lines 5-6 with the true running balance (117.00 at the end)', $cp === 200 && count($dp['rows']) === 2 && $dp['pages'] === 3 && $dp['rows'][1]['balance'] === '117.00');
    [$cs, $bs_] = $dq($AL, '&view=lines&search=' . urlencode('100.00')); $ds = J($bs_);
    ok('search by amount', $cs === 200 && $ds['matching'] === 1 && $ds['closing'] === '117.00');
    foreach (['another congregation\'s asset' => $Foreign, 'deleted without history' => $DelN, 'a root' => 3, 'non-digit' => 'abc', 'SQL text' => '1 OR 1=1', 'a name' => 'cash and cash equivalents', 'zero' => '0', 'empty' => '', 'an income account without fund' => $IL, 'an expense account without fund' => $EL] as $label => $acc) {
        [$code, $b] = $dq(urlencode((string) $acc)); ok("drill refuses $label with 400", $code === 400 && J($b)['success'] === false, "status $code");
    }
    [$code] = $dq($AL, '&fund=bf'); ok('fund=bf with an asset account -> 400', $code === 400);
    [$code] = $dq($IL, '&fund=div'); ok('fund=div with an income account -> 400', $code === 400);
    [$code] = $dq($AL, '&fund=zzz'); ok('an unknown fund value -> 400', $code === 400);
    [$code] = $dq($A, '', $admin2); ok('another congregation\'s session cannot open the account (400)', $code === 400);
    [$code, $b, $h] = http('reports/balancesheetdetailed?account=cash', $admin11); ok('old balancesheetdetailed redirects to the balance sheet', $code === 302 && stripos($h, 'balancesheet') !== false);
    [$code] = http('reports/getbalancesheetdetailedrpt?asdate=2025-10-31&account=cash+at+bank', $admin11); ok('old getbalancesheetdetailedrpt -> 400, nothing queried', $code === 400);
    [$code, $b, $h] = http('reports/balancesheetchilddetails?account=x', $admin11); ok('old balancesheetchilddetails redirects', $code === 302);
    [$code] = http('reports/balancesheetchilddetailsrpt?account=x&asdate=2025-10-31', $admin11); ok('old balancesheetchilddetailsrpt -> 400', $code === 400);

    echo "== Excel\n";
    [$code, $bin, $h] = http("reports/balancesheetrpt?asof=$asof&format=xlsx", $admin11); $rows = xlsx($bin);
    ok('statement: a real .xlsx with the right content type and file name', $code === 200 && $rows !== null && stripos($h, 'spreadsheetml.sheet') !== false && stripos($h, 'balance-sheet-2025-10-31.xlsx') !== false);
    $find = function ($rows, $label) { foreach ($rows as $r) { if (cellText($r, 0) === $label) { return $r; } } return null; };
    $ta = $find($rows, 'Total assets');
    ok('title and date only (no congregation name, no basis text); the amounts are numbers and a comparative column exists', strpos(cellText($rows[0], 0), 'Balance sheet as at 31-10-2025') === 0 && $ta !== null && $ta[1][0] === 'n' && isset($ta[2]) && $find($rows, 'Total liabilities, equity and accumulated fund') !== null && $find($rows, 'Accumulated fund b/f') !== null);
    $dv = $find($rows, 'Dividends');
    ok('contra / deduction as a negative number; markup name is plain text', $dv !== null && $dv[1][1] == -2 && $find($rows, 'Zz <script>alert(1)</script> T94h') !== null);
    ok('no path form in the workbook (decision 149)', (function () use ($rows) { foreach ($rows as $r) { foreach ($r as $c) { if ($c[0] === 's' && strpos($c[1], ' > ') !== false) { return false; } } } return true; })());
    [$code, $bin2] = http("reports/balancesheetdrillrpt?account=$A&asof=$asof&format=xlsx", $admin11); $rows2 = xlsx($bin2);
    ok('drill page (children) exports its rows and total', $code === 200 && $rows2 !== null && $find($rows2, 'Total') !== null);
    [$code, $bin3] = http("reports/balancesheetdrillrpt?account=$AL&asof=$asof&view=lines&format=xlsx&page=2&pageSize=2", $admin11); $rows3 = xlsx($bin3);
    ok('lines export holds all 6 lines whatever the page', $code === 200 && count(array_filter($rows3, function ($r) { return isset($r[1]) && $r[1][0] === 'n' && isset($r[0]) && $r[0][0] === 'n'; })) === 6 && $find($rows3, 'Closing balance') !== null);
    [$code, $bin4] = http("reports/balancesheetdrillrpt?account=$AL&asof=$asof&view=lines&format=xlsx&search=" . urlencode('100.00'), $admin11); $rows4 = xlsx($bin4);
    ok('lines export of a search holds only the matching row', count(array_filter($rows4, function ($r) { return isset($r[1]) && $r[1][0] === 'n' && isset($r[0]) && $r[0][0] === 'n'; })) === 1);
    [$code, $bin5] = http("parishreports/balancesheetrpt?asof=$asof&format=xlsx", $admin11); $rows5 = xlsx($bin5);
    ok('parish statement exports with the scope line only', $code === 200 && $rows5 !== null && cellText($rows5[0], 0) === 'All congregations');

    echo "== decisions 144, 147, 148, 149 on the other reports\n";
    $lq = function ($acc, $from, $to, $extra = '') use ($admin11) { return http("reports/ledgerstatementrpt?account=$acc&from=$from&to=$to&subledgers=0$extra", $admin11); };
    [$c, $b] = $lq($IL, '2025-09-15', '2025-10-31'); $ls = J($b);
    ok('ledger statement of an income account across the year end: opening 0.00 (nothing since the year start), 10 + 5 in the old year, restart at 7.00', $c === 200 && $ls['opening'] === '0.00' && $ls['matching'] === 3 && $ls['rows'][0]['balance'] === '10.00' && $ls['rows'][1]['balance'] === '15.00' && $ls['rows'][2]['balance'] === '7.00' && $ls['closing'] === '7.00', $b);
    ok('the rows carry the year so the screen draws the "new financial year" separator', $ls['rows'][2]['fy'] === '2025-10-01' && $ls['rows'][2]['prevFy'] === '2024-10-01' && $ls['rows'][1]['fy'] === '2024-10-01');
    [$c, $b] = $lq($IL, '2025-09-30', '2025-10-31'); $ls2 = J($b);
    ok('a statement that starts inside the old year: opening = the movement from the year start to the day before (10.00)', $ls2['opening'] === '10.00' && $ls2['rows'][0]['balance'] === '15.00');
    [$c, $b] = $lq($AL, '2025-09-30', '2025-10-31'); $ls3 = J($b);
    ok('a balance-sheet account keeps its cumulative opening balance (10.00) and never restarts', $ls3['opening'] === '10.00' && $ls3['rows'][0]['balance'] === '15.00' && $ls3['rows'][1]['balance'] === '22.00' && $ls3['rows'][0]['fy'] === null);
    [$c, $b] = http("trialbalance/getreport?asof=$asof", $admin11); $tb = J($b);
    ok('trial balance JSON: no congregation heading, no basis text, no path', $c === 200 && $tb['entity'] === '' && !preg_match('/cumulative to the date|run from/i', $b) && !hasPath($tb));
    [$c, $b] = http("trialbalance/getreport?asof=$asof&detailed=1", $admin11); $td = J($b);
    ok('detailed trial balance: account names only', $c === 200 && !hasPath($td) && in_array('Zz T94h Asset Leaf', array_column($td['rows'], 'name'), true));
    [$c, $b] = http("trialbalance/getreport?asof=$asof&detailed=1&format=xlsx", $admin11); $tx = xlsx($b);
    ok('detailed trial balance Excel: names only, first line is the title', $tx !== null && cellText($tx[0], 0) === 'Detailed trial balance as at 31-10-2025');
    $pathX = false; foreach ($tx as $r) { foreach ($r as $cc) { if ($cc[0] === 's' && strpos($cc[1], ' > ') !== false) { $pathX = true; } } }
    ok('detailed trial balance Excel has no path form', !$pathX && count(array_filter($tx, function ($r) { return cellText($r, 1) === 'Zz T94h Asset Leaf'; })) === 1);
    [$c, $b] = http("reports/incomestatementrpt?start=2025-10-01&end=2025-10-31", $admin11); $is = J($b);
    ok('income statement JSON: no congregation heading', $c === 200 && $is['entity'] === '' && !hasPath($is));
    [$c, $b] = http("reports/incomestatementrpt?start=2025-10-01&end=2025-10-31&format=xlsx", $admin11); $ix = xlsx($b);
    ok('income statement Excel starts with the title', cellText($ix[0], 0) === 'Income statement for the period 01-10-2025 to 31-10-2025');
    [$c, $b] = http("reports/plchildaccountrpt?account=$IL&start=2025-09-15&end=2025-10-31&view=lines", $admin11); $il = J($b);
    ok('income statement drill across the year end: restarts at zero too (fy flags present)', $c === 200 && $il['matching'] === 3 && $il['rows'][2]['balance'] === '7.00' && $il['rows'][2]['prevFy'] === '2024-10-01');
    [$c, $b] = http('reports/ledger_statement', $admin11); $o = [];
    preg_match_all('~<option value="(\d+)"[^>]*>(.*?)</option>~s', $b, $m, PREG_SET_ORDER); foreach ($m as $x) { $o[$x[1]] = html_entity_decode($x[2], ENT_QUOTES, 'UTF-8'); }
    ok('dropdown labels: names only; same-name accounts told apart by the parent in brackets', $o[$AL] === 'zz t94h asset leaf' && $o[$Dup1] === 'zz t94h same name (zz t94h asset top)' && $o[$Dup2] === 'zz t94h same name (zz <script>alert(1)</script> t94h)');
    $ledgerJs = (string) file_get_contents('E:/xampp/htdocs/cms/public/dist/js/pages/reports/is/ledger-statement-v1.js');
    ok('the ledger statement screen no longer prints the path line', strpos($ledgerJs, 'first.path') === false);
    $tbJs = (string) file_get_contents('E:/xampp/htdocs/cms/public/dist/js/pages/reports/is/trial-balance-v1.js');
    ok('the trial balance screen has no basis sentence', stripos($tbJs, 'cumulative to the date') === false);
    $uiJs = (string) file_get_contents('E:/xampp/htdocs/cms/public/dist/js/pages/reports/is/report-ui-v1.js');
    ok('the line list draws a "new financial year" separator row', strpos($uiJs, 'New financial year from') !== false);

    ok('rendered pages and endpoints emitted no PHP warnings', $phpErrors === [], implode(', ', $phpErrors));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    foreach ($sessions as $sid) { $f = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid; if (is_file($f)) { @unlink($f); } }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz%t94h%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t94h%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM tblcongregation WHERE CongregationName LIKE 'zz t94h%'")[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

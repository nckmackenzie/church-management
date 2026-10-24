<?php
/**
 * Task 9.2 through Apache with real sessions: congregation and parish income statements, the account-ID drill-down, refusals,
 * escaping, scope isolation and the selector labels of decision 139. Self-cleaning. CLI only; needs scripts/dev_session_helper.php.
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
function http($method, $path, $sid, $body = null) {
    global $base, $phpErrors;
    $ch = curl_init($base . '/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $h = substr((string) $raw, 0, $hs); $b = substr((string) $raw, $hs);
    if (preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', $b, $m)) { $phpErrors[] = $path . ' -> ' . $m[2]; }
    return [$code, $b, $h];
}
function tc($s) { if ($s === null) { return null; } $neg = $s[0] === '('; $v = FinancialReport::cents(str_replace([',', '(', ')'], '', $s)); return $neg ? -$v : $v; }
function options($html) { preg_match_all('~<option value="([^"]*)"[^>]*>(.*?)</option>~s', $html, $m, PREG_SET_ORDER); $o = []; foreach ($m as $r) { $o[$r[1]][] = html_entity_decode($r[2], ENT_QUOTES, 'UTF-8'); } return $o; }

$created = ['accounts' => [], 'entries' => []];
try {
    $admin11 = mkSession(64, 11); $admin2 = mkSession(64, 2);
    $acct = function ($name, $parent, $cong = 11, $o = []) use (&$created) {
        $o += ['active' => 1, 'deleted' => 0];
        $root = $parent <= 6 ? $parent : (int) q('SELECT accountTypeId FROM tblaccounttypes WHERE ID = ?', [$parent])[0]['accountTypeId'];
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, forGroup, active, congregationId, deleted) VALUES (?,?,?,1,0,0,?,?,?)', [$name, $root, $parent, $o['active'], $cong, $o['deleted']]);
        $created['accounts'][] = $id; return $id;
    };
    $A = $acct('zz t92h income top', 1); $B = $acct('zz t92h income mid', $A); $C = $acct('zz t92h income low', $B); $D = $acct('zz t92h income leaf', $C);
    $XS = $acct('zz <script>alert(1)</script> t92h', 1);
    $Foreign = $acct('zz t92h foreign income', 1, 2); $DelN = $acct('zz t92h deleted no history', 1, 11, ['deleted' => 1]);
    $Asset = $acct('zz t92h asset top', 3); $AssetL = $acct('zz t92h asset leaf', $Asset);
    $ForeignAsset = $acct('zz t92h foreign asset', 3, 2);
    $Dup1 = $acct('zz t92h duplicate', $A); $Dup2 = $acct('zz t92h duplicate', $XS);
    $n = 0;
    $entry = function ($cong, $date, $lines) use (&$created, &$n) {
        $n++; $id = x("INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,5,?,?,?,'zz t92h',0)", [$cong, $date, 960000 + $n, 960000 + $n, 'zz t92h ' . $n]);
        $created['entries'][] = $id; $ln = 0; foreach ($lines as [$a, $dc, $amt]) { x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $a, $dc, $amt, 'zz t92h memo', ++$ln]); } return $id;
    };
    $entry(11, '2026-10-15', [[$AssetL, 'DEBIT', '777.00'], [$D, 'CREDIT', '777.00']]);
    $entry(11, '2026-10-16', [[$AssetL, 'DEBIT', '11.00'], [$XS, 'CREDIT', '11.00']]);
    $entry(2, '2026-10-15', [[$ForeignAsset, 'DEBIT', '333.00'], [$Foreign, 'CREDIT', '333.00']]);
    $q = 'start=2026-10-01&end=2026-10-31';

    echo "== pages\n";
    foreach (['reports/incomestatement', 'parishreports/incomestatement', "reports/plchildaccount?account=$A&$q", "parishreports/plchildaccount?account=$A&$q"] as $p) {
        [$code, $body] = http('GET', $p, $admin11);
        ok("renders $p", $code === 200 && strpos($body, 'income-statement-v1.js') !== false && strpos($body, 'id="isapp"') !== false, "status $code");
    }
    $js = (string) file_get_contents('E:/xampp/htdocs/cms/public/dist/js/pages/reports/is/income-statement-v1.js');
    ok('the screen script puts server text on the page with textContent only (no innerHTML / insertAdjacentHTML / document.write)', strpos($js, 'textContent') !== false && !preg_match('/innerHTML|insertAdjacentHTML|document\.write|outerHTML/', $js));
    [$code, $body] = http('GET', 'parishreports/incomestatement', $admin11);
    $po = options($body);
    ok('parish form: "All" and one option per congregation', isset($po['0']) && isset($po['11']));

    echo "== congregation income statement\n";
    [$code, $body] = http('GET', "reports/incomestatementrpt?$q", $admin11); $r = json_decode($body, true);
    ok('JSON report for the session congregation', $code === 200 && $r['success'] === true && $r['income']['title'] === 'Income' && $r['start'] === '2026-10-01' && $r['entity'] !== '', $body);
    $names = array_column($r['income']['rows'], 'name');
    ok('top-level lines include the fixture accounts, the markup in a name comes back as data', in_array('Zz T92h Income Top', $names, true) && in_array('Zz <script>alert(1)</script> T92h', $names, true));
    $row = array_values(array_filter($r['income']['rows'], function ($x) use ($A) { return $x['id'] === $A; }))[0];
    ok('fixture line carries 777.00 and the comparative is null/zero-safe', $row['amount'] === '777.00' && $row['next'] === 'children');
    ok('another congregation\'s income is not in the report', !in_array('Zz T92h Foreign Income', $names, true));
    [$code, $body] = http('GET', "reports/incomestatementrpt?$q", $admin2); $r2 = json_decode($body, true);
    $n2 = array_column($r2['income']['rows'], 'name');
    ok('a session of another congregation sees its own income only', $code === 200 && in_array('Zz T92h Foreign Income', $n2, true) && !in_array('Zz T92h Income Top', $n2, true) && tc($r2['income']['total']) === 33300);

    echo "== date validation\n";
    foreach (['missing start' => 'end=2026-10-31', 'missing end' => 'start=2026-10-01', 'reversed' => 'start=2026-10-31&end=2026-10-01', 'impossible date' => 'start=2026-02-30&end=2026-10-01', 'wrong format' => 'start=01-10-2026&end=2026-10-31', 'array' => 'start%5B%5D=2026-10-01&end=2026-10-31', 'empty' => 'start=&end=', 'SQL text' => "start=2026-10-01'--&end=2026-10-31"] as $label => $qs) {
        [$code, $body] = http('GET', "reports/incomestatementrpt?$qs", $admin11); $j = json_decode($body, true);
        ok("$label -> 400 with a message", $code === 400 && $j['success'] === false && $j['message'] !== '', "status $code $body");
    }
    [$code] = http('GET', "reports/plchildaccountrpt?account=$A&start=2026-10-31&end=2026-10-01", $admin11);
    ok('reversed dates on the drill -> 400', $code === 400);
    [$code, $body] = http('GET', 'reports/incomestatementrpt?start=2025-09-15&end=2026-10-15', $admin11);
    ok('a range across financial years is allowed', $code === 200);

    echo "== drill-down by account ID\n";
    $level = function ($acc, $extra = '', $sid = null) use ($q, $admin11) { return http('GET', "reports/plchildaccountrpt?account=$acc&$q$extra", $sid ?? $admin11); };
    [$c1, $b1] = $level($A); $d1 = json_decode($b1, true);
    ok('level 1: next accounts, rows add up to the figure clicked', $c1 === 200 && $d1['mode'] === 'children' && $d1['sumsExactly'] === true && $d1['rowsTotal'] === $d1['amount'] && $d1['amount'] === '777.00');
    [$c2, $b2] = $level($B); $d2 = json_decode($b2, true);
    [$c3, $b3] = $level($C); $d3 = json_decode($b3, true);
    ok('levels 2 and 3 add up too and the breadcrumb grows', $d2['mode'] === 'children' && $d3['mode'] === 'children' && $d2['sumsExactly'] && $d3['sumsExactly'] && array_column($d3['crumbs'], 'name') === ['Zz T92h Income Top', 'Zz T92h Income Mid', 'Zz T92h Income Low']);
    [$c4, $b4] = $level($D); $d4 = json_decode($b4, true);
    ok('level 4 (leaf) shows the journal lines with the entry, narration, type and running balance', $c4 === 200 && $d4['mode'] === 'lines' && count($d4['rows']) === 1 && $d4['movement'] === '777.00' && $d4['closing'] === '777.00' && $d4['opening'] === '0.00' && $d4['rows'][0]['credit'] === '777.00' && $d4['rows'][0]['balance'] === '777.00' && $d4['rows'][0]['memo'] === 'zz t92h memo' && $d4['rows'][0]['entryId'] > 0);
    [$c5, $b5] = $level($A, '&view=lines'); $d5 = json_decode($b5, true);
    ok('view=lines on a parent lists the lines of its whole subtree', $d5['mode'] === 'lines' && $d5['movement'] === '777.00');
    [$c6, $b6] = $level($A, '&direct=1'); $d6 = json_decode($b6, true);
    ok('direct=1 lists only the account\'s own postings (none here)', $c6 === 200 && $d6['mode'] === 'lines' && $d6['rows'] === [] && $d6['movement'] === '0.00');
    [$c7, $b7] = $level($XS); $d7 = json_decode($b7, true);
    ok('an account name with markup is returned as plain data (the page renders it with textContent)', $d7['rows'][0]['account'] === 'Zz <script>alert(1)</script> T92h' || $d7['name'] === 'Zz <script>alert(1)</script> T92h');
    [$cp, $bp] = http('GET', "parishreports/plchildaccountrpt?account=$A&$q&cong=11", $admin11); $dp = json_decode($bp, true);
    ok('parish drill works on the same account IDs', $cp === 200 && $dp['mode'] === 'children' && $dp['amount'] === '777.00');

    echo "== refusals\n";
    foreach (['another congregation\'s account' => $Foreign, 'deleted account without history' => $DelN, 'an asset account' => $AssetL, 'a root account' => 1, 'the expense root' => 2, 'non-digit' => 'abc', 'SQL text' => '1 OR 1=1', 'name instead of ID' => 'tithes and offering', 'zero' => '0', 'negative' => '-5', 'empty' => ''] as $label => $acc) {
        [$code, $body] = $level(urlencode((string) $acc)); $j = json_decode($body, true);
        ok("drill refuses $label with 400", $code === 400 && ($j['success'] ?? null) === false, "status $code");
    }
    [$code, $body] = $level($A, '', $admin2); $j = json_decode($body, true);
    ok('another congregation\'s session cannot open this congregation\'s account (400)', $code === 400);
    [$code, $body] = $level(32, '', $admin2); $j = json_decode($body, true);
    ok('a shared account opened from another congregation shows no lines of congregation 11', $code === 200 && (($j['mode'] ?? '') !== 'lines' || $j['rows'] === []) && !preg_match('/zz t92h/i', $body));
    [$code] = http('GET', 'reports/plchildaccountrpt?account=tithes+and+offering&sdate=2026-10-01&edate=2026-10-31', $admin11);
    ok('old name-based URL (account=name, sdate / edate) -> 400, nothing queried', $code === 400);
    [$code] = http('GET', 'reports/plchildaccountdetailedrpt?account=tithes+and+offering&sdate=2026-10-01&edate=2026-10-31', $admin11);
    ok('old detailed URL -> 400', $code === 400);
    [$code, $body, $h] = http('GET', 'reports/plchildaccountdetailed?account=tithes+and+offering', $admin11);
    ok('old detailed page redirects to the income statement', $code === 302 && stripos($h, 'incomestatement') !== false, "status $code");

    echo "== parish statement\n";
    [$code, $body] = http('GET', "parishreports/incomestatementrpt?$q", $admin11); $pr = json_decode($body, true);
    ok('all congregations: raw sum includes congregation 11 and congregation 2 income (777 + 11 + 333)', $code === 200 && $pr['entity'] === 'All congregations' && tc($pr['income']['total']) >= 112100, $body);
    [$code, $body] = http('GET', "parishreports/incomestatementrpt?$q&cong=2", $admin11); $p2 = json_decode($body, true);
    ok('one congregation selected: only its figures', $code === 200 && tc($p2['income']['total']) === 33300);
    foreach (['non-existent congregation' => 'cong=99999', 'non-digit' => 'cong=abc', 'SQL text' => 'cong=1+OR+1%3D1', 'list' => 'cong%5B%5D=2'] as $label => $qs) {
        [$code] = http('GET', "parishreports/incomestatementrpt?$q&$qs", $admin11);
        ok("parish report refuses $label with 400", $code === 400, "status $code");
    }
    [$code] = http('GET', "parishreports/incomestatementrpt?start=2026-10-31&end=2026-10-01", $admin11);
    ok('parish report refuses reversed dates with 400', $code === 400);
    [$code] = http('GET', "parishreports/plchildaccountrpt?account=$Foreign&$q&cong=11", $admin11);
    ok('parish drill: an account that belongs to a congregation outside the selection -> 400', $code === 400);

    echo "== selector labels (decision 139)\n";
    [$code, $body] = http('GET', 'reports/ledger_statement', $admin11);
    $o = options($body);
    ok('ledger statement: label is the account name only', $o[$A][0] === 'zz t92h income top' && $o[$AssetL][0] === 'zz t92h asset leaf');
    ok('two accounts with one name are told apart by the parent in brackets', $o[$Dup1][0] === 'zz t92h duplicate (zz t92h income top)' && $o[$Dup2][0] === 'zz t92h duplicate (zz <script>alert(1)</script> t92h)');
    ok('the markup in a label is escaped in the page', strpos($body, '<script>alert(1)</script>') === false && strpos($body, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false);
    [$code, $body] = http('GET', 'reports/contributions', $admin11); $oc = options($body);
    ok('contribution filter: names only, sorted by name', isset($oc[32]) && $oc[32][0] === 'tithes and offering' && !array_filter(array_map(function ($v) { return $v[0]; }, $oc), function ($l) { return strpos($l, ' > ') !== false; }));
    ok('rendered pages and endpoints emitted no PHP warnings', $phpErrors === [], implode(', ', $phpErrors));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($sessions as $sid) { $f = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid; if (is_file($f)) { @unlink($f); } }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz%t92h%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t92h%'")[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

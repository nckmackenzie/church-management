<?php
/**
 * Task 9.5 through Apache with real sessions: bank reconciliation, cleared / uncleared lists and cashflow (book balance from the
 * journal, strict inputs, forged banks, escaping), the banking report's escaping, and the balance sheet with the accumulated fund
 * block of decision 150 (screen, drills, Excel). Self-cleaning (zz t95h prefix). CLI only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
$pdo = (new Database)->pdo();
$_SESSION = ['congId' => 11, 'userId' => 64];
$base = URLROOT; $pass = 0; $fail = 0; $phpErrors = [];
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
/** the value cells of the row whose label cell is exactly $label, as texts */
function rowCells($html, $label) {
    if (!preg_match('~<tr><td>' . preg_quote($label, '~') . '</td>(.*?)</tr>~s', $html, $m)) { return null; }
    preg_match_all('~<td>(?:<a [^>]*>)?([^<]*)(?:</a>)?</td>~', $m[1], $c);
    return $c[1];
}

$created = ['accounts' => [], 'entries' => [], 'congs' => [], 'postings' => []];
try {
    $T  = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t95h main', 0, 0)"); $created['congs'][] = $T;
    $T2 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t95h other', 0, 0)"); $created['congs'][] = $T2;
    $T3 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t95h fund', 0, 0)"); $created['congs'][] = $T3;
    $s1 = mkSession(64, $T); $s2 = mkSession(64, $T2); $s3 = mkSession(64, $T3);
    $acct = function ($name, $root, $cong, $bank = 0, $parent = null) use (&$created) {
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, forGroup, active, congregationId, deleted) VALUES (?,?,?,1,?,0,1,?,0)', [$name, $root, $parent ?? ($bank ? 33 : $root), $bank, $cong]);
        $created['accounts'][] = $id; return $id;
    };
    $B1 = $acct('zz t95h bank one', 3, $T, 1); $BF = $acct('zz t95h foreign bank', 3, $T2, 1);
    $INC = $acct('zz t95h income', 1, $T); $INC3 = $acct('zz t95h income fund', 1, $T3); $AS3 = $acct('zz t95h asset fund', 3, $T3);
    $n = 0;
    $entry = function ($cong, $date, array $lines) use (&$created, &$n) {
        $n++; $txn = 960000 + $n;
        $id = x('INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,5,?,?,?,?,0)', [$cong, $date, $txn, $txn, 'zz t95h ref ' . $n, 'zz t95h desc ' . $n]);
        $created['entries'][] = $id; $ln = 0;
        foreach ($lines as [$a, $dc, $amt]) { x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $a, $dc, $amt, 'zz t95h memo ' . $n, ++$ln]); }
    };
    $DR = 'DEBIT'; $CR = 'CREDIT';
    $entry($T, '2025-09-01', [[$B1, $DR, '1000.00'], [$INC, $CR, '1000.00']]);
    $entry($T, '2025-09-10', [[$INC, $DR, '200.00'], [$B1, $CR, '200.00']]);
    $entry($T, '2025-10-05', [[$B1, $DR, '50.00'], [$INC, $CR, '50.00']]);
    // the fund fixture: 300.00 surplus in the previous financial year, 100.00 in the current one
    $entry($T3, '2025-05-20', [[$AS3, $DR, '300.00'], [$INC3, $CR, '300.00']]);
    $entry($T3, '2025-10-15', [[$AS3, $DR, '100.00'], [$INC3, $CR, '100.00']]);
    $method = (int) q('SELECT ID FROM tbltransactionmethods ORDER BY ID LIMIT 1')[0]['ID'];
    $post = function ($date, $debit, $credit, $cleared, $clearedOn, $ref) use (&$created, $B1, $T, $method) {
        $created['postings'][] = x('INSERT INTO tblbankpostings (bankId, transactionDate, debit, credit, transactionMethod, reference, cleared, clearedDare, transactionType, transactionId, deleted, congregationId) VALUES (?,?,?,?,?,?,?,?,9,0,0,?)',
                                   [$B1, $date, $debit, $credit, $method, $ref, $cleared, $clearedOn, $T]);
    };
    $post('2025-09-01', '1000.00', '0.00', 1, '2025-09-05', 'zz t95h dep cleared');
    $post('2025-09-10', '0.00', '200.00', 0, null, 'zz t95h <script>alert(1)</script> wd');
    $post('2025-09-20', '300.00', '0.00', 1, '2025-10-15', 'zz t95h dep late');
    $post('2025-09-25', '25.00', '0.00', 1, null, 'zz t95h dep null clear date');
    $post('2025-09-21', '0.00', '0.00', 0, null, 'zz t95h zero');
    $post('2025-08-31', '55.00', '0.00', 0, null, 'zz t95h before range');
    $post('2025-10-01', '65.00', '0.00', 0, null, 'zz t95h after range');

    echo "== bank reconciliation\n";
    $rq = "bankreconcilliations/bankrecon?bank=$B1&from=2025-09-01&to=2025-09-30&balance=800.00";
    [$code, $body] = http($rq, $s1);
    $book = FinancialReport::money(FinancialReport::accountNet($pdo, [$T], $B1, '2025-09-30'));
    ok('reconciliation renders (200) with the bank selected', $code === 200 && strpos($body, 'Bank Reconcilliation 01-09-2025 - 30-09-2025') !== false, "status $code");
    ok('book balance = the bank\'s journal balance (800.00), labelled as the ledger journal', rowCells($body, 'Book balance at 30-09-2025 (per the ledger journal)') === [$book] && $book === '800.00', json_encode(rowCells($body, 'Book balance at 30-09-2025 (per the ledger journal)')));
    ok('uncleared deposits 325.00 (including NULL clear date) and withdrawals 200.00 from the range cashbook, labelled', rowCells($body, 'Less:uncleared deposits (per cashbook)') === ['325.00'] && rowCells($body, 'Add:uncleared withdrawals (per cashbook)') === ['200.00']);
    ok('expected 675.00 = 800 - 325 + 200; statement 800.00; variance 125.00', rowCells($body, 'Expected Balance') === ['675.00'] && rowCells($body, 'Actual/Bank Balance') === ['800.00'] && rowCells($body, 'Variance') === ['125.00']);
    ok('the totals link to the uncleared list for the same bank and dates', preg_match('~uncleared\?type=deposit&bank=' . $B1 . '&sdate=2025-09-01&edate=2025-09-30~', $body) === 1 && preg_match('~uncleared\?type=withdraw&bank=~', $body) === 1);
    [$code, $body2] = http("bankreconcilliations/bankrecon?bank=$B1&from=2025-09-01&to=2025-10-31&balance=-12.5", $s1);
    ok('a later date moves the journal balance (850.00) and a negative statement balance is accepted', $code === 200 && rowCells($body2, 'Book balance at 31-10-2025 (per the ledger journal)') === ['850.00'] && rowCells($body2, 'Actual/Bank Balance') === ['-12.50']);
    [$code, $body3] = http("bankreconcilliations/bankrecon?bank=$B1&from=2025-09-01&to=2025-09-30&balance=0", $s1);
    ok('balance 0 is a valid statement balance', $code === 200 && rowCells($body3, 'Actual/Bank Balance') === ['0.00']);

    echo "== inputs\n";
    $bad = [];
    foreach (['impossible date' => "bank=$B1&from=2025-02-30&to=2025-09-30&balance=1", 'text date' => "bank=$B1&from=yesterday&to=2025-09-30&balance=1", 'dd-mm-yyyy' => "bank=$B1&from=01-09-2025&to=30-09-2025&balance=1",
             'from after to' => "bank=$B1&from=2025-10-01&to=2025-09-01&balance=1", 'missing to' => "bank=$B1&from=2025-09-01&balance=1", 'missing balance' => "bank=$B1&from=2025-09-01&to=2025-09-30",
             'text balance' => "bank=$B1&from=2025-09-01&to=2025-09-30&balance=abc", 'three decimals' => "bank=$B1&from=2025-09-01&to=2025-09-30&balance=1.234", 'exponent' => "bank=$B1&from=2025-09-01&to=2025-09-30&balance=1e5",
             'array date' => "bank=$B1&from%5B%5D=2025-09-01&to=2025-09-30&balance=1", 'foreign bank' => "bank=$BF&from=2025-09-01&to=2025-09-30&balance=1", 'no bank' => "from=2025-09-01&to=2025-09-30&balance=1", 'SQL in bank' => "bank=" . urlencode("$B1 OR 1=1") . "&from=2025-09-01&to=2025-09-30&balance=1"] as $label => $qs) {
        foreach (['bankrecon', 'cashflow_report'] as $ep) { [$c] = http("bankreconcilliations/$ep?$qs", $s1); if ($c !== 400) { $bad[] = "$ep $label=$c"; } }
    }
    ok('reconciliation and cashflow refuse (400): impossible / text / wrong-format / reversed / missing dates, bad balances, forged banks', !$bad, implode(', ', $bad));
    [$c] = http("bankreconcilliations/bankrecon?bank=$B1&from=2025-09-01&to=2025-09-30&balance=800.00", $s2);
    ok('another congregation\'s session cannot use this bank (400)', $c === 400);
    $bad = [];
    foreach (['uncleared', 'cleared'] as $k) {
        foreach (['bad type' => "type=refund&bank=$B1&sdate=2025-09-01&edate=2025-09-30", 'no type' => "bank=$B1&sdate=2025-09-01&edate=2025-09-30", 'bad date' => "type=deposit&bank=$B1&sdate=2025-13-01&edate=2025-09-30",
                 'reversed' => "type=deposit&bank=$B1&sdate=2025-10-01&edate=2025-09-30", 'foreign bank' => "type=deposit&bank=$BF&sdate=2025-09-01&edate=2025-09-30", 'no dates' => "type=deposit&bank=$B1"] as $label => $qs) {
            [$c] = http("bankreconcilliations/{$k}report?$qs", $s1); if ($c !== 400) { $bad[] = "$k $label=$c"; }
        }
    }
    ok('the uncleared / cleared list endpoints refuse bad type, dates and forged banks with 400', !$bad, implode(', ', $bad));

    echo "== uncleared and cleared detail\n";
    [$c, $b] = http("bankreconcilliations/unclearedreport?type=deposit&bank=$B1&sdate=2025-09-01&edate=2025-09-30", $s1); $j = J($b);
    ok('uncleared deposits: late-clear and NULL-clear-date rows only; day before/after and zero row are not listed', $c === 200 && $j['success'] && count($j['results']) === 2 && array_column($j['results'], 'amount') === ['300.00', '25.00'] && array_column($j['results'], 'transactionDate') === ['20-09-2025', '25-09-2025'], $b);
    [$c, $b] = http("bankreconcilliations/unclearedreport?type=withdraw&bank=$B1&sdate=2025-09-01&edate=2025-09-30", $s1); $j = J($b);
    ok('uncleared withdrawals: the 200.00 row; the reference comes back as data (upper-cased), never as markup in the page', $c === 200 && count($j['results']) === 1 && $j['results'][0]['amount'] === '200.00' && strpos($j['results'][0]['reference'], '<SCRIPT>') !== false);
    [$c, $b] = http("bankreconcilliations/clearedreport?type=deposit&bank=$B1&sdate=2025-09-01&edate=2025-09-30", $s1); $j = J($b);
    ok('cleared deposits: the 1,000.00 row', $c === 200 && count($j['results']) === 1 && $j['results'][0]['amount'] === '1000.00');
    [$c, $b] = http("bankreconcilliations/clearedreport?type=withdraw&bank=$B1&sdate=2025-09-01&edate=2025-09-30", $s1); $j = J($b);
    ok('cleared withdrawals: none', $c === 200 && $j['success'] && $j['results'] === []);
    foreach (['uncleared', 'cleared'] as $k) { [$c, $b] = http("bankreconcilliations/$k?type=deposit&bank=$B1&sdate=2025-09-01&edate=2025-09-30", $s1); ok("the $k detail page renders", $c === 200, "status $c"); }
    foreach (['bankings/reconcilliation-report.js', 'bankings/cleared-bakings-report.js'] as $js) {
        $src = file_get_contents(__DIR__ . '/../public/dist/js/pages/' . $js);
        ok("$js puts the rows on the page as text (no innerHTML / insertAdjacentHTML for server values)", strpos($src, 'textContent') !== false && strpos($src, 'insertAdjacentHTML') === false && !preg_match('~\$\{dt\.reference\}~', $src));
    }

    echo "== cashflow\n";
    [$code, $body] = http("bankreconcilliations/cashflow_report?bank=$B1&from=2025-10-01&to=2025-10-31&balance=850.00", $s1);
    ok('cashflow renders; opening balance entering 01-10-2025 = 800.00 and closing book balance = 850.00, both journal-labelled', $code === 200 && rowCells($body, 'Opening Balance at 01-10-2025 (per the ledger journal)') === ['800.00'] && rowCells($body, 'Closing book balance at 31-10-2025 (per the ledger journal)') === ['850.00']);
    ok('movements are cashbook figures and labelled so: nothing was banked in October', rowCells($body, 'Cleared Deposits (per cashbook)') === ['0.00'] && rowCells($body, 'Uncleared Withdrawals (per cashbook)') === ['0.00']);
    [$code, $bodyW] = http("bankreconcilliations/cashflow_report?bank=$B1&from=2025-09-01&to=2025-10-31&balance=0", $s1);
    ok('over September and October the 300.00 deposit is cleared (cleared 15-10-2025): cleared deposits 1,300.00, uncleared withdrawals 200.00', rowCells($bodyW, 'Cleared Deposits (per cashbook)') === ['1,300.00'] && rowCells($bodyW, 'Uncleared Withdrawals (per cashbook)') === ['200.00']);
    [$code, $body] = http("bankreconcilliations/cashflow_report?bank=$B1&from=2025-09-01&to=2025-09-30&balance=0", $s1);
    ok('cashflow from first posting: opening 0.00, closing book 800.00, cashbook deposits shown', $code === 200 && rowCells($body, 'Opening Balance at 01-09-2025 (per the ledger journal)') === ['0.00'] && rowCells($body, 'Closing book balance at 30-09-2025 (per the ledger journal)') === ['800.00'] && rowCells($body, 'Cleared Deposits (per cashbook)') === ['1,000.00']);
    ok('cashflow expected statement balance equals reconciliation 675.00, not old mixed-source 875.00', rowCells($body, 'Expected statement balance') === ['675.00'] && rowCells($body, 'Expected statement balance') !== ['875.00']);

    echo "== Clear Banking summary inputs\n";
    [$c, $b] = http("clearbankings/getbankings?bank=$B1&from=2025-09-01&to=2025-09-30", $s1); $cb = J($b);
    ok('Clear Banking valid range returns cleared cashbook summary with no warning', $c === 200 && $cb['values'] === ['debits' => '1325.00', 'credits' => '0.00'] && is_array($cb['bankings']), $b);
    [$c, $b] = http("clearbankings/getValues?bank=$B1&from=2025-09-01&to=2025-09-30", $s1); $cv = J($b);
    ok('Clear Banking getValues uses its two real fields in one summary (no nonexistent index)', $c === 200 && $cv['debits'] === '1325.00' && $cv['credits'] === '0.00' && !array_key_exists('balance', $cv), $b);
    $bad = [];
    foreach (["bank=$B1&from=2025-02-30&to=2025-09-30", "bank=$B1&from=2025-10-01&to=2025-09-30", "bank=$BF&from=2025-09-01&to=2025-09-30", "from=2025-09-01&to=2025-09-30", "bank%5B%5D=$B1&from=2025-09-01&to=2025-09-30"] as $qs) {
        foreach (['getbankings', 'getValues'] as $ep) { [$bc] = http("clearbankings/$ep?$qs", $s1); if ($bc !== 400) { $bad[] = "$ep $qs=$bc"; } }
    }
    [$bc] = http("clearbankings/getbankings?bank=$B1&from=2025-09-01&to=2025-09-30&status=bad", $s1); if ($bc !== 400) { $bad[] = "bad status=$bc"; }
    ok('Clear Banking summaries refuse malformed/reversed dates, missing/array/foreign banks and bad status with 400', !$bad, implode(', ', $bad));

    echo "== screens and the banking report\n";
    foreach (['bankreconcilliations', 'bankreconcilliations/cashflow_statement'] as $p) { [$c, $b] = http($p, $s1); ok("renders $p with the bank in the list", $c === 200 && stripos($b, 'zz t95h bank one') !== false); }
    [$c, $b] = http("reports/bankingrpt?bank=$B1&from=2025-09-01&to=2025-09-30&status=0", $s1);
    ok('banking report (cashbook rows): the reference is escaped in the page and the total is labelled as a cashbook total', $c === 200 && strpos($b, '<SCRIPT>') === false && strpos($b, '&lt;SCRIPT&gt;') !== false && strpos($b, 'Total (cashbook rows listed):') !== false, substr($b, 0, 200));
    foreach (['status=2', 'status=', 'from=2025-02-30&status=0'] as $qs) { [$c] = http("reports/bankingrpt?bank=$B1&from=2025-09-01&to=2025-09-30&$qs", $s1); ok("banking report refuses $qs with 400", $c === 400); }
    [$c] = http("parishreports/bankingrpt?bank=$B1&from=2025-09-01&to=2025-09-30&status=7", $s1);
    ok('parish banking report refuses a bad status with 400', $c === 400);

    echo "== balance sheet: the accumulated fund block (decision 150)\n";
    $asof = '2025-10-31';
    [$c, $b] = http("reports/balancesheetrpt?asof=$asof", $s3); $r = J($b);
    $fundNames = array_column($r['fund']['rows'], 'name');
    ok('the block: Accumulated fund b/f, Surplus / (deficit) for the year to date (no dividends line when zero), then the c/f total', $c === 200 && $fundNames === ['Accumulated fund b/f', 'Surplus / (deficit) for the year to date'] && $r['fund']['total'] === '400.00', json_encode($fundNames) . ' ' . ($r['fund']['total'] ?? ''));
    $rows = []; foreach ($r['fund']['rows'] as $row) { $rows[$row['name']] = $row; }
    ok('b/f 300.00 (previous year\'s surplus), surplus to date 100.00, c/f 400.00; equity holds only the other type 6 accounts; balanced', $rows['Accumulated fund b/f']['amount'] === '300.00' && $rows['Surplus / (deficit) for the year to date']['amount'] === '100.00' && $r['equityTotal']['total'] === '400.00' && $r['balanced'] === true && $r['equity']['rows'] === []);
    ok('comparative column at the last year-end: b/f 0.00 (nothing before the previous year) and c/f 300.00 = this year\'s b/f', $rows['Accumulated fund b/f']['cmp'] === '0.00' && $r['fund']['cmpTotal'] === '300.00' && $rows['Accumulated fund b/f']['amount'] === $r['fund']['cmpTotal']);
    ok('the fund account was identified (not by name) and no diagnostic is shown', is_int($r['fund']['fundAccountId']) && $r['fund']['diagnostic'] === null);
    [$c, $b] = http("reports/balancesheetdrillrpt?account=bf&asof=$asof", $s3); $d = J($b);
    ok('b/f opens the prior years\' surplus (300.00); the fund account at the year start is empty here and not listed', $c === 200 && $d['mode'] === 'children' && $d['amount'] === '300.00' && $d['sumsExactly'] && array_column($d['rows'], 'id') === ['pys'], $b);
    [$c, $b] = http("reports/balancesheetdrillrpt?account=pys&asof=$asof", $s3); $d = J($b);
    ok('prior years\' surplus opens the earlier years\' accounts and adds up', $c === 200 && $d['mode'] === 'children' && $d['sumsExactly'] && $d['rowsTotal'] === '300.00');
    $fa = (int) $r['fund']['fundAccountId'];
    [$c, $b] = http("reports/balancesheetdrillrpt?account=$fa&asof=$asof&fund=fa", $s3);
    ok('the fund account at the year start can be opened (fund=fa)', $c === 200 && J($b)['success'] !== false, "status $c");
    [$c] = http("reports/balancesheetdrillrpt?account=$fa&asof=$asof&fund=fm", $s3); ok('the in-year movement view (fund=fm) answers', $c === 200);
    [$c] = http("reports/balancesheetdrillrpt?account=$INC3&asof=$asof&fund=fa", $s3); ok('an income account under fund=fa is refused (400)', $c === 400);
    [$c] = http("reports/balancesheetdrillrpt?account=$AS3&asof=$asof&fund=fm", $s3); ok('an asset account under fund=fm is refused (400)', $c === 400);
    [$c] = http("reports/balancesheetdrillrpt?account=$fa&asof=$asof&fund=zz", $s3); ok('an unknown fund value is refused (400)', $c === 400);
    [$c] = http("reports/balancesheetdrillrpt?account=$INC3&asof=$asof&fund=bf&view=lines", $s3); ok('an income account opened from prior years\' surplus lists its lines', $c === 200);
    [$c, $b, $h] = http("reports/balancesheetrpt?asof=$asof&format=xlsx", $s3);
    $x = xlsx($b); $labels = []; if ($x) { foreach ($x as $row) { $labels[] = cellText($row, 0); } }
    ok('Excel: the block rows and "Accumulated fund c/f" are in the workbook, followed by the equity total', $c === 200 && in_array('Accumulated fund b/f', $labels, true) && in_array('Surplus / (deficit) for the year to date', $labels, true) && in_array('Accumulated fund c/f', $labels, true) && array_search('Accumulated fund c/f', $labels, true) < array_search('Total equity and accumulated fund', $labels, true), json_encode($labels));
    $cf = null; foreach ($x ?: [] as $row) { if (cellText($row, 0) === 'Accumulated fund c/f') { $cf = $row[1][1] ?? null; } }
    ok('Excel: c/f is a real number, 400', $cf !== null && abs($cf - 400.0) < 0.001);
    [$c, $b] = http("reports/balancesheetrpt?asof=2025-09-30", $s3); $r0 = J($b);
    ok('the block at the year-end: c/f 300.00 equals this year\'s b/f of 300.00', $r0['fund']['total'] === '300.00' && $rows['Accumulated fund b/f']['amount'] === '300.00');
    [$c, $b] = http("trialbalance/getreport?asof=$asof", $s3); $tbj = J($b);
    $tbNames = array_column($tbj['rows'] ?? [], 'name');
    ok('trial balance: the computed line reads "Prior years\' surplus b/f" and nothing calls it Accumulated fund b/f', $c === 200 && in_array("Prior years' surplus b/f", $tbNames, true) && !in_array('Accumulated fund b/f', $tbNames, true), json_encode($tbNames));
    [$c, $b, $h] = http("trialbalance/getreport?asof=$asof&format=xlsx", $s3); $tx = xlsx($b); $tl = []; foreach ($tx ?: [] as $row) { $tl[] = cellText($row, 1); }
    ok('trial balance Excel: the same label', $c === 200 && in_array("Prior years' surplus b/f", $tl, true) && !in_array('Accumulated fund b/f', $tl, true), json_encode($tl));

    ok('rendered pages and endpoints emitted no PHP warnings', !$phpErrors, implode('; ', $phpErrors));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach ($created['postings'] as $id) { $pdo->prepare('DELETE FROM tblbankpostings WHERE ID = ?')->execute([$id]); }
    $pdo->prepare("DELETE FROM tblbankpostings WHERE reference LIKE 'zz t95h%'")->execute();
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    $pdo->prepare("DELETE FROM journal_entries WHERE reference LIKE 'zz t95h%'")->execute();
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    foreach ($sessions ?? [] as $sid) { @unlink(session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid); }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz t95h%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t95h%'")[0]['n']
          + (int) q("SELECT COUNT(*) n FROM tblbankpostings WHERE reference LIKE 'zz t95h%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM tblcongregation WHERE CongregationName LIKE 'zz t95h%'")[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left fixture rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

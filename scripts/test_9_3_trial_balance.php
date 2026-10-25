<?php
/**
 * Task 9.3: trial balance (TrialBalanceReport on FinancialReport), ledger statement closing = detailed trial balance, Excel writer,
 * line paging / search, include-subledgers, fiscal-year delete guard. Self-cleaning fixtures + read-only parity with the old
 * trial balance on the real dev data. CLI only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Year.php';

$pdo = (new Database)->pdo();
$pass = 0; $fail = 0;
function ok($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
function C($s) { return FinancialReport::cents($s); }
function tcents($s) { if ($s === '' || $s === null) { return 0; } return C(str_replace([',', '(', ')'], '', $s)); }
function rowNet($r) { return $r['debitRaw'] - $r['creditRaw']; }

$created = ['accounts' => [], 'entries' => [], 'congs' => [], 'years' => []];
try {
    $T  = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t93 main', 0, 0)"); $created['congs'][] = $T;
    $T2 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t93 second', 0, 0)"); $created['congs'][] = $T2;
    $parentOf = []; $rootOf = [];
    $acct = function ($name, $parent, $cong) use (&$created, &$parentOf, &$rootOf) {
        $root = $parent <= 6 ? $parent : $rootOf[$parent];
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, forGroup, active, congregationId, deleted) VALUES (?,?,?,1,0,0,1,?,0)', [$name, $root, $parent, $cong]);
        $created['accounts'][] = $id; $parentOf[$id] = $parent; $rootOf[$id] = $root; return $id;
    };
    $A2 = $acct('zz t93 asset top', 3, $T); $A3 = $acct('zz t93 asset mid', $A2, $T); $A4 = $acct('zz t93 asset low', $A3, $T); $A5 = $acct('zz t93 asset leaf', $A4, $T); $A5b = $acct('zz t93 asset leaf b', $A4, $T);
    $I2 = $acct('zz t93 income top', 1, $T); $I3 = $acct('zz t93 income mid', $I2, $T); $I4 = $acct('zz t93 income leaf', $I3, $T); $Iz = $acct('zz t93 income zero', $I2, $T);
    $E2 = $acct('zz t93 expense top', 2, $T); $E3 = $acct('zz t93 expense leaf', $E2, $T);
    $Q2 = $acct('zz t93 equity top', 6, $T); $Q3 = $acct('zz t93 equity leaf', $Q2, $T);
    $L2 = $acct('zz t93 liability top', 4, $T); $L3 = $acct('zz t93 liability leaf', $L2, $T);
    $D2 = $acct('zz t93 dividend top', 5, $T); $D3 = $acct('zz t93 dividend leaf', $D2, $T);
    $X = $acct('zz t93 loop x', 3, $T2); $Y = $acct('zz t93 loop y', $X, $T2);
    $pdo->prepare('UPDATE tblaccounttypes SET parentId = ? WHERE ID = ?')->execute([$Y, $X]);
    $T2A = $acct('zz t93 asset top b', 3, $T2); $T2A3 = $acct('zz t93 asset leaf c', $T2A, $T2);

    $fx = []; $n = 0;
    $entry = function ($date, array $lines, $cong = null, $deleted = 0) use (&$created, &$fx, &$n, $T) {
        $cong = $cong ?? $T; $n++; $txn = 930000 + $n;
        $id = x('INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,5,?,?,?,?,?)', [$cong, $date, $txn, $txn, 'zz t93 ref ' . $n, 'zz t93 desc ' . $n, $deleted]);
        $created['entries'][] = $id; $ln = 0; $rec = [];
        foreach ($lines as [$a, $dc, $amt]) { x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $a, $dc, $amt, 'zz t93 memo ' . $n, ++$ln]); $rec[] = [$a, $dc, C($amt)]; }
        $fx[] = ['date' => $date, 'cong' => $cong, 'deleted' => $deleted, 'lines' => $rec]; return $id;
    };
    $DR = 'DEBIT'; $CR = 'CREDIT';
    $entry('2024-12-01', [[$A5, $DR, '5000.00'], [$Q3, $CR, '5000.00']]);                 // FY 2024-25
    $entry('2025-05-20', [[$A5, $DR, '300.00'], [$I4, $CR, '300.00']]);
    $entry('2025-05-21', [[$E3, $DR, '100.00'], [$A5, $CR, '100.00']]);
    $entry('2025-09-30', [[$A5b, $DR, '60.00'], [$I4, $CR, '60.00']]);                     // last day of FY 2024-25
    $entry('2025-10-01', [[$A5, $DR, '1000.00'], [$I4, $CR, '1000.00']]);                  // first day of FY 2025-26
    $entry('2025-11-10', [[$A5b, $DR, '500.00'], [$L3, $CR, '500.00']]);
    $entry('2026-01-15', [[$E3, $DR, '200.00'], [$A5, $CR, '200.00']]);
    $entry('2026-02-10', [[$I4, $DR, '50.00'], [$A5b, $CR, '50.00']]);                     // contra
    $entry('2026-03-01', [[$D3, $DR, '40.00'], [$A5, $CR, '40.00']]);
    $entry('2026-03-02', [[$Iz, $DR, '70.00'], [$A5, $CR, '70.00']]);
    $entry('2026-03-03', [[$A5, $DR, '70.00'], [$Iz, $CR, '70.00']]);                       // nets to zero
    $entry('2026-09-30', [[$A5, $DR, '11.00'], [$I4, $CR, '11.00']]);
    $entry('2026-10-01', [[$A5, $DR, '13.00'], [$I4, $CR, '13.00']]);                       // first day of FY 2026-27
    $entry('2026-10-10', [[$E3, $DR, '20.00'], [$L3, $CR, '20.00']]);
    $entry('2026-02-12', [[$A5, $DR, '9999.00'], [$I4, $CR, '9999.00']], null, 1);          // voided
    $entry('2026-02-14', [[$T2A3, $DR, '8.00'], [$X, $CR, '8.00']], $T2);                    // loop account: unclassified
    $entry('2026-02-15', [[$T2A3, $DR, '90.00'], [$Q3, $CR, '90.00']], $T2);                 // another congregation's lines on its own scope

    $naive = function ($accounts, $from, $to, $cong) use (&$fx) {
        $set = array_flip($accounts); $dr = 0; $cr = 0;
        foreach ($fx as $e) { if ($e['deleted'] || $e['cong'] !== $cong || $e['date'] > $to || ($from !== null && $e['date'] < $from)) { continue; }
            foreach ($e['lines'] as [$a, $dc, $c]) { if (isset($set[$a])) { if ($dc === 'DEBIT') { $dr += $c; } else { $cr += $c; } } } }
        return [$dr, $cr];
    };
    $subtree = function ($id) use (&$parentOf) { $out = [$id => true]; do { $add = false; foreach ($parentOf as $a => $p) { if (isset($out[$p]) && !isset($out[$a])) { $out[$a] = true; $add = true; } } } while ($add); return array_keys($out); };
    $netDr = function ($id, $from, $to) use ($naive, $subtree, $T) { [$d, $c] = $naive($subtree($id), $from, $to, $T); return $d - $c; };
    $ofRoot = function ($r) use (&$rootOf) { return array_keys(array_filter($rootOf, function ($x) use ($r) { return $x === $r; })); };
    $years = ['2025-10-01' => ['2025-10-01', '2024-10-01'], '2026-03-31' => ['2025-10-01', '2024-10-01'], '2026-09-30' => ['2025-10-01', '2024-10-01'], '2026-10-01' => ['2026-10-01', '2025-10-01']];

    echo "== basis at several as-of dates\n";
    foreach ($years as $asOf => [$ys]) {
        $r = TrialBalanceReport::report($pdo, [$T], 'x', $asOf, false);
        $byId = []; foreach ($r['rows'] as $row) { $byId[$row['id']] = $row; }
        $nz = function ($id) use (&$byId) { return isset($byId[$id]) ? rowNet($byId[$id]) : 0; };
        ok("$asOf: debits = credits (" . $r['totals']['debit'] . ')', $r['totals']['balanced'] && $r['totals']['debitRaw'] === $r['totals']['creditRaw']);
        ok("$asOf: asset top is cumulative (all years)", $nz($A2) === $netDr($A2, null, $asOf));
        ok("$asOf: income top runs from the year start $ys", $nz($I2) === $netDr($I2, $ys, $asOf));
        ok("$asOf: expense top runs from the year start", $nz($E2) === $netDr($E2, $ys, $asOf));
        ok("$asOf: liability and equity are cumulative", $nz($Q2) === $netDr($Q2, null, $asOf));
        $prior = 0; foreach ([1, 2, 5] as $root) { [$d, $c] = $naive($ofRoot($root), null, date('Y-m-d', strtotime($ys . ' -1 day')), $T); $prior += $d - $c; }
        ok("$asOf: ONE accumulated fund b/f line = types 1, 2, 5 before the year start", count(array_filter($r['rows'], function ($x) { return $x['id'] === 'bf'; })) === 1 && rowNet($byId['bf']) === $prior, rowNet($byId['bf']) . ' vs ' . $prior);
        ok("$asOf: the zero-net income account is hidden, the section rows show", !isset($byId[$Iz]) && isset($byId[$I2]));
        $d = TrialBalanceReport::report($pdo, [$T], 'x', $asOf, true);
        ok("$asOf: detailed trial balance balances too", $d['totals']['balanced']);
        $sec = function ($rep) { $s = []; foreach ($rep['rows'] as $row) { $s[$row['section']] = ($s[$row['section']] ?? 0) + rowNet($row); } ksort($s); return $s; };
        ok("$asOf: detailed = summary: the net of every section is the same", $sec($r) === $sec($d), json_encode($sec($r)) . ' / ' . json_encode($sec($d)));
    }
    ok('a date with no financial year is refused (400 via fail(): checked over HTTP); the library says so', (function () use ($pdo, $T) { try { FinancialReport::trialBalance($pdo, [$T], '2000-01-01'); return false; } catch (FinancialReportException $e) { return strpos($e->getMessage(), 'No financial year') === 0; } })());
    $none = TrialBalanceReport::report($pdo, [$T], 'x', '2025-10-01', false);
    ok('totals always show (also when little is left to show)', isset($none['totals']['debit']) && isset($none['totals']['credit']));

    echo "== unclassified postings\n";
    $u = TrialBalanceReport::report($pdo, [$T2], 'b', '2026-03-31', false);
    ok('the diagnostic is shown, the unclassified row is listed and the debits still equal the credits', $u['unclassified']['present'] && $u['totals']['balanced'] && count($u['unclassified']['rows']) >= 1);

    echo "== drill: every level adds up, on the right basis, down to the lines\n";
    $asOf = '2026-03-31'; $ys = '2025-10-01';
    $rep = TrialBalanceReport::report($pdo, [$T], 'x', $asOf, false);
    $bad = []; $levels = 0; $lineNodes = 0;
    $walk = function ($account, $net, $label, $bf) use (&$walk, &$bad, &$levels, &$lineNodes, $pdo, $T, $asOf) {
        $d = TrialBalanceReport::drill($pdo, [$T], $account, $asOf, 'children', false, $bf);
        $levels++;
        if ($d['mode'] === 'children') {
            $nodeNet = $d['debitRaw'] - $d['creditRaw'];
            if (!$d['sumsExactly'] || $d['rowsDebitRaw'] - $d['rowsCreditRaw'] !== $nodeNet || $nodeNet !== $net) { $bad[] = "$label level: rows " . ($d['rowsDebitRaw'] - $d['rowsCreditRaw']) . " node $nodeNet clicked $net"; }
            foreach ($d['rows'] as $row) {
                $rn = $row['debitRaw'] - $row['creditRaw'];
                if ($row['direct']) {
                    $l = TrialBalanceReport::drill($pdo, [$T], $row['id'], $asOf, 'lines', true, $bf); $levels++; $lineNodes++;
                    if (tcents($l['figureDebit']) - tcents($l['figureCredit']) !== $rn) { $bad[] = "$label direct lines figure vs $rn"; }
                } else { $walk($row['id'], $rn, $label . '>' . $row['name'], $bf); }
            }
        } else {
            $lineNodes++;
            if (tcents($d['figureDebit']) - tcents($d['figureCredit']) !== $net) { $bad[] = "$label lines figure " . $d['figureDebit'] . '/' . $d['figureCredit'] . " vs $net"; }
        }
    };
    foreach ($rep['rows'] as $row) {
        if ($row['id'] === 'bf') { $walk('bf', rowNet($row), 'bf', true); continue; }
        $walk($row['id'], rowNet($row), $row['name'], false);
    }
    ok("drill: all $levels levels add up and the $lineNodes line lists agree with the figure clicked (income / expense from the year start, balance sheet types from inception)", $levels >= 10 && $lineNodes >= 5 && !$bad, implode('; ', $bad));
    $l1 = TrialBalanceReport::drill($pdo, [$T], $I4, $asOf, 'lines', false, false);
    ok('an income account lists lines from the year start and starts at zero: no opening balance carried from earlier years (decision 144)', $l1['opening'] === '0.00' && $l1['rows'][0]['date'] === '01-10-2025');
    $l2 = TrialBalanceReport::drill($pdo, [$T], $A5, $asOf, 'lines', false, false);
    ok('a balance-sheet account lists lines from inception (opening balance 0.00)', $l2['opening'] === '0.00' && $l2['rows'][0]['date'] === '01-12-2024');
    $bfl = TrialBalanceReport::drill($pdo, [$T], $I4, $asOf, 'lines', false, true);
    ok('under the accumulated fund b/f the income account shows only the prior years (credit direction)', $bfl['direction'] === 'Cr' && count($bfl['rows']) === 2 && $bfl['movement'] === '360.00');
    $c = TrialBalanceReport::drill($pdo, [$T], $I2, $asOf, 'children', false, false);
    ok('crumbs and ID-only navigation: the drill answers for an account ID and refuses nothing else here', $c['mode'] === 'children' && array_column($c['crumbs'], 'name') === ['Zz T93 Income Top']);

    echo "== decision 144: income accounts start every financial year at zero\n";
    $span = FinancialReport::linesPage($pdo, [$T], $I4, ['kind' => 'range', 'from' => '2025-09-15', 'to' => '2025-10-31', 'direction' => 'normal'], false, 1, 0, '');
    ok('a statement across the year end: opening = the movement from the year start to the day before (300.00)', $span['opening'] === 30000 && count($span['rows']) === 2);
    ok('the running balance restarts at zero at the new financial year (360.00 on 30 Sep, 1,000.00 on 1 Oct)', $span['rows'][0]['balance'] === 36000 && $span['rows'][1]['balance'] === 100000);
    ok('the rows carry the year (fy) and the year of the row before (prevFy) so the screen can draw a separator', $span['rows'][0]['fy'] === '2024-10-01' && $span['rows'][1]['fy'] === '2025-10-01' && $span['rows'][1]['prevFy'] === '2024-10-01' && $span['rows'][0]['prevFy'] === null);
    ok('closing = the last year\'s running balance, movement = both years together', $span['closing'] === 100000 && $span['movement'] === 106000);
    $cum = FinancialReport::linesPage($pdo, [$T], $I4, ['kind' => 'range', 'from' => null, 'to' => '2025-09-30', 'direction' => 'credit', 'fy' => false], false, 1, 0, '');
    ok('fy => false (the accumulated fund b/f view) keeps one cumulative running balance over the earlier years', $cum['opening'] === 0 && $cum['closing'] === 36000 && $cum['rows'][1]['balance'] === 36000);
    $bal = FinancialReport::linesPage($pdo, [$T], $A5, ['kind' => 'range', 'from' => '2025-09-15', 'to' => '2025-10-31', 'direction' => 'normal'], false, 1, 0, '');
    ok('a balance-sheet account keeps its cumulative opening balance and never restarts', $bal['restartsEachYear'] === false && $bal['opening'] === $naive([$A5], null, '2025-09-14', $T)[0] - $naive([$A5], null, '2025-09-14', $T)[1]);

    echo "== line paging and search (server side)\n";
    $per = ['kind' => 'range', 'from' => '2024-10-01', 'to' => '2026-12-31', 'direction' => 'normal'];
    $all = FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 0, '');
    ok('page size 0 returns every row (export)', count($all['rows']) === $all['matching'] && $all['matching'] >= 13);
    $p1 = FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 5, '');
    $p2 = FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 2, 5, '');
    ok('page 2 starts where page 1 ends and carries the TRUE running balance', $p1['pages'] >= 3 && $p2['rows'][0]['entryId'] === $all['rows'][5]['entryId'] && $p2['rows'][0]['balance'] === $all['rows'][5]['balance'] && $p2['rows'][4]['balance'] === $all['rows'][9]['balance']);
    ok('rows are ordered by date, entry ID, line number', (function () use ($all) { $prev = ''; foreach ($all['rows'] as $r) { $k = $r['date'] . sprintf('%09d%05d', $r['entryId'], $r['lineNumber']); if ($k < $prev) { return false; } $prev = $k; } return true; })());
    $s1 = FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, 'zz t93 ref 7');
    ok('search by reference returns only matching rows, balances stay true', $s1['matching'] === 1 && $s1['rows'][0]['reference'] === 'zz t93 ref 7' && (function () use ($all, $s1) { foreach ($all['rows'] as $r) { if ($r['entryId'] === $s1['rows'][0]['entryId'] && $r['accountId'] === $s1['rows'][0]['accountId']) { return $r['balance'] === $s1['rows'][0]['balance']; } } return false; })());
    ok('search by date (dd-mm-yyyy and yyyy-mm-dd)', FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, '15-01-2026')['matching'] === 1 && FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, '2026-01-15')['matching'] === 1);
    ok('search by amount, with or without separators', FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, '5,000')['matching'] === 1 && FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, '5000.00')['matching'] === 1);
    ok('search by narration, type and entry number', FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, 'zz t93 memo 3')['matching'] === 1 && FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, 'journal entries')['matching'] === $all['matching'] && FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, (string) $all['rows'][0]['entryId'])['matching'] >= 1);
    ok('LIKE wildcards in the search text are literal', FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, '%')['matching'] === 0 && FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 50, '_')['matching'] === 0);
    ok('totals of the matching rows, and the period totals unchanged by a search', $s1['filtered']['debit'] + $s1['filtered']['credit'] > 0 && $s1['totals'] === $all['totals'] && $s1['closing'] === $all['closing']);
    ok('page size is capped', FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 100000, '')['pageSize'] === FinancialReport::MAX_PAGE_SIZE);

    echo "== include subledgers = every descendant\n";
    $with = FinancialReport::linesPage($pdo, [$T], $A2, $per, true, 1, 0, '');
    $without = FinancialReport::linesPage($pdo, [$T], $A2, $per, false, 1, 0, '');
    $want = $naive($subtree($A2), '2024-10-01', '2026-12-31', $T);
    ok('with subledgers: all five levels below the account are covered (2 leaves), totals equal the independent sum', $with['totals']['debit'] === $want[0] && $with['totals']['credit'] === $want[1] && $without['count'] === 0);
    ok('without subledgers only its own postings (none on this reporting account)', $without['rows'] === []);

    echo "== ledger statement closing = detailed trial balance\n";
    $chk = function ($cong, $dates) use ($pdo) {
        $res = ['n' => 0, 'bad' => []];
        foreach ($dates as $asOf) {
            $fy = FinancialReport::financialYear($pdo, $asOf);
            $d = TrialBalanceReport::report($pdo, [$cong], 'x', $asOf, true);
            foreach ($d['rows'] as $row) {
                if (!is_int($row['id']) || !empty($row['unclassified'])) { continue; }
                $root = (int) q('SELECT accountTypeId FROM tblaccounttypes WHERE ID = ?', [$row['id']])[0]['accountTypeId'];
                $chart = FinancialReport::chart($pdo, [$cong]); $root = $chart['nodes'][$row['id']]['root'];
                $from = in_array($root, [3, 4, 6], true) ? '2000-01-01' : $fy['start'];
                $L = FinancialReport::linesPage($pdo, [$cong], $row['id'], ['kind' => 'range', 'from' => $from, 'to' => $asOf, 'direction' => 'normal'], false, 1, 1, '');
                $closingNet = $L['closingNet'];
                $res['n']++;
                if ($closingNet !== rowNet($row)) { $res['bad'][] = "$asOf account {$row['id']}: statement $closingNet vs trial balance " . rowNet($row); }
            }
        }
        return $res;
    };
    $r1 = $chk($T, ['2026-03-31', '2026-10-24']);
    ok("fixtures: the ledger statement closing equals the detailed trial balance for all {$r1['n']} account-dates", $r1['n'] >= 8 && !$r1['bad'], implode('; ', array_slice($r1['bad'], 0, 3)));
    $r2 = $chk(11, ['2025-09-30', '2026-10-24']);
    ok("real data (congregation 11): the ledger statement closing equals the detailed trial balance for all {$r2['n']} account-dates (2025-09-30 and 2026-10-24)", $r2['n'] > 20 && !$r2['bad'], implode('; ', array_slice($r2['bad'], 0, 3)));

    echo "== Excel writer\n";
    $bin = XlsxWriter::build('Test <sheet>', [[XlsxWriter::s('Head', true), XlsxWriter::s('=1+1'), XlsxWriter::s("a & b <c> \"q\"")], [XlsxWriter::m(-12345), XlsxWriter::m(100), XlsxWriter::n(7), XlsxWriter::d('2026-10-24')], [null, XlsxWriter::m(0)]], [10, 12]);
    $tmp = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx'; file_put_contents($tmp, $bin);
    $zip = new ZipArchive();
    ok('the workbook is a valid zip with the expected parts', $zip->open($tmp) === true && $zip->numFiles === 6 && $zip->locateName('xl/worksheets/sheet1.xml') !== false && $zip->locateName('[Content_Types].xml') !== false);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    ok('every XML part is well-formed', (function () use ($zip) { for ($i = 0; $i < $zip->numFiles; $i++) { if (@simplexml_load_string($zip->getFromIndex($i)) === false) { return false; } } return true; })());
    ok('money is a real number (-123.45, 1.00), count is a number, the date is a serial number with a date style', strpos($sheet, '<c r="A2" s="2"><v>-123.45</v></c>') !== false && strpos($sheet, '<c r="B2" s="2"><v>1.00</v></c>') !== false && strpos($sheet, '<c r="C2" s="5"><v>7</v></c>') !== false && strpos($sheet, '<c r="D2" s="4"><v>46319</v></c>') !== false, $sheet);
    ok('text is an inline string, never a formula; markup is escaped', strpos($sheet, '<t xml:space="preserve">=1+1</t>') !== false && strpos($sheet, 'a &amp; b &lt;c&gt; &quot;q&quot;') !== false && strpos($sheet, '<f>') === false);
    ok('the sheet name is cleaned and the widths are written', strpos($zip->getFromName('xl/workbook.xml'), 'name="Test &lt;sheet&gt;"') !== false && strpos($sheet, '<col min="1" max="1" width="10"') !== false);
    $zip->close(); @unlink($tmp);

    echo "== fiscal-year delete guard\n";
    $yid = x("INSERT INTO tblfiscalyears (yearName, startDate, endDate, closed, deleted) VALUES ('zz t93 2041', '2040-10-01', '2041-09-30', 1, 0)"); $created['years'][] = $yid;
    $ym = new Year();
    $voidId = $entry('2041-01-15', [[$A5, $DR, '1.00'], [$Q3, $CR, '1.00']], null, 1);
    ok('a year with only voided entries can be deleted (no blocker)', $ym->deleteBlockers($yid) === [], json_encode($ym->deleteBlockers($yid)));
    $actId = $entry('2041-02-15', [[$A5, $DR, '2.00'], [$Q3, $CR, '2.00']]);
    $bl = $ym->deleteBlockers($yid);
    ok('a year with an active entry cannot be deleted; the reason names the ledger entries', count($bl) === 1 && strpos($bl[0], '1 ledger entries') !== false, json_encode($bl));
    $src = file_get_contents('E:/xampp/htdocs/cms/app/models/Year.php');
    ok('Year no longer reads tblledger', strpos($src, 'tblledger') === false);

    echo "== parity with the old trial balance on real dev data (read-only)\n";
    foreach (['2025-09-30', '2026-10-24'] as $asOf) {
        $fy = FinancialReport::financialYear($pdo, $asOf);
        $prevDay = date('Y-m-d', strtotime($fy['start'] . ' -1 day'));
        $norm = function ($name) { $n = ucwords(strtolower(trim($name))); return $n === 'Cash At Bank' ? 'Cash And Cash Equivalents' : $n; };   // the mirror calls every bank line 'cash at bank'
        $new = TrialBalanceReport::report($pdo, [11], 'x', $asOf, false);
        $newNet = []; foreach ($new['rows'] as $row) { $newNet[$row['name']] = rowNet($row); }
        // old trial balance: movement from the year start, by parent account name
        $old = []; $oldRoot = [];
        foreach (q('SELECT parentaccount p, accountId a, SUM(debit-credit) n FROM tblledger WHERE deleted=0 AND congregationId=11 AND transactionDate BETWEEN ? AND ? GROUP BY parentaccount, accountId', [$fy['start'], $asOf]) as $o) { $k = $norm($o['p']); $old[$k] = ($old[$k] ?? 0) + C($o['n']); $oldRoot[$k] = (int) $o['a']; }
        // opening balances of types 3, 4, 6 in the journal (the old screen has none) by top-level account
        $chart = FinancialReport::chart($pdo, [11]);
        $roll = FinancialReport::rollup($chart, FinancialReport::balances($pdo, [11], null, $prevDay))['total'];
        $open = []; foreach ($chart['nodes'] as $nd) { if ($nd['depth'] === 2 && in_array($nd['root'], [3, 4, 6], true)) { $b = $roll[$nd['id']] ?? ['idr' => 0, 'icr' => 0]; $open[ucwords($nd['name'])] = $b['idr'] - $b['icr']; } }
        $jp = FinancialReport::balances($pdo, [11], $fy['start'], $asOf)[188] ?? ['idr' => 0, 'icr' => 0];
        $m188 = $jp['idr'] - $jp['icr'];                       // account 188 in the period, journal
        $diff = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($newNet))) as $name) {
            if ($name === "Prior years' surplus b/f") { continue; }
            $expected = ($old[$name] ?? 0) + ($open[$name] ?? 0);
            if (($newNet[$name] ?? 0) !== $expected) { $diff[$name] = ($newNet[$name] ?? 0) - $expected; }
        }
        $expectDiff = $m188 === 0 ? [] : ['Administrative Costs' => $m188, 'Due To Parish' => -$m188];
        ksort($diff); ksort($expectDiff);
        ok("$asOf: new figure = old period movement + opening balance (types 3, 4, 6) for every top-level line; the only difference is account 188 (stamped 'due to parish' by the mirror, an expense in the chart): " . json_encode($diff), $diff === $expectDiff, json_encode($diff) . ' vs ' . json_encode($expectDiff));
        $sumOld = array_sum($old);
        ok("$asOf: the old movements add up to nil and the new trial balance balances with the b/f line and no unclassified postings", $sumOld === 0 && $new['totals']['balanced'] && !$new['unclassified']['present'], "old sum $sumOld");
        // where account 188 shows: the opening balance (cumulative before the year start): mirror vs journal for 'due to parish'
        $mo = (int) 0; foreach (q("SELECT SUM(debit-credit) n FROM tblledger WHERE deleted=0 AND congregationId=11 AND accountId IN (3,4,6) AND LOWER(parentaccount)='due to parish' AND transactionDate < ?", [$fy['start']]) as $o) { $mo = C($o['n']); }
        $j188 = FinancialReport::balances($pdo, [11], null, $prevDay)[188] ?? ['idr' => 0, 'icr' => 0];
        ok("$asOf: opening balance of 'due to parish': mirror minus journal equals account 188's own balance before the year start (" . FinancialReport::money($j188['idr'] - $j188['icr']) . ')', $mo - ($open['Due To Parish'] ?? 0) === $j188['idr'] - $j188['icr'], FinancialReport::money($mo) . ' / ' . FinancialReport::money($open['Due To Parish'] ?? 0));
        echo "INFO $asOf: new totals debit " . $new['totals']['debit'] . ' credit ' . $new['totals']['credit'] . '; period movement of 188 ' . FinancialReport::money($m188) . "\n";
    }
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    $pdo->prepare("DELETE FROM journal_entries WHERE reference LIKE 'zz t93%'")->execute();
    foreach ($created['years'] as $id) { $pdo->prepare('DELETE FROM tblfiscalyears WHERE ID = ?')->execute([$id]); }
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz t93%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t93%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM tblcongregation WHERE CongregationName LIKE 'zz t93%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM tblfiscalyears WHERE yearName LIKE 'zz t93%'")[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left fixture rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

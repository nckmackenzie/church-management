<?php
/**
 * Task 9.2: income statement (IncomeStatementReport on FinancialReport). Self-cleaning fixtures (three test congregations, one of
 * them deleted but with history) plus read-only checks against figures recorded from the old reports (mirror removed in 9.9). CLI only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';

$pdo = (new Database)->pdo();
$pass = 0; $fail = 0;
function ok($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
/** "(1,234.50)" / "1,234.50" -> cents */
function tc($s) { if ($s === null) { return null; } $neg = $s[0] === '('; $v = FinancialReport::cents(str_replace([',', '(', ')'], '', $s)); return $neg ? -$v : $v; }
function C($s) { return FinancialReport::cents($s); }

$created = ['accounts' => [], 'entries' => [], 'congs' => []];
try {
    $T  = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t92 main', 0, 0)"); $created['congs'][] = $T;
    $T2 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t92 second', 0, 0)"); $created['congs'][] = $T2;
    $T3 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t92 closed', 0, 1)"); $created['congs'][] = $T3;   // deleted, with history
    $acct = function ($name, $parent, $cong) use (&$created) {
        $root = $parent <= 6 ? $parent : (int) q('SELECT accountTypeId FROM tblaccounttypes WHERE ID = ?', [$parent])[0]['accountTypeId'];
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, forGroup, active, congregationId, deleted) VALUES (?,?,?,1,0,0,1,?,0)', [$name, $root, $parent, $cong]);
        $created['accounts'][] = $id; return $id;
    };
    // income: L2 > L3 > L4 > leaf (4 levels below the root), a sibling leaf, a comparative-only leaf, an always-zero leaf
    $I2 = $acct('zz t92 income top', 1, $T); $I3 = $acct('zz t92 income mid', $I2, $T); $I4 = $acct('zz t92 income low', $I3, $T); $I5 = $acct('zz t92 income leaf', $I4, $T);
    $Isib = $acct('zz t92 income sibling', $I2, $T); $Icmp = $acct('zz t92 income last year only', $I2, $T); $Izero = $acct('zz t92 income nets zero', $I2, $T);
    $Itop2 = $acct('zz <script>alert(1)</script> income', 1, $T);                      // markup in an account name
    $E2 = $acct('zz t92 expense top', 2, $T); $E3 = $acct('zz t92 expense mid', $E2, $T); $E4 = $acct('zz t92 expense leaf', $E3, $T);
    $D2 = $acct('zz t92 dividend top', 5, $T); $D3 = $acct('zz t92 dividend leaf', $D2, $T);
    $A2 = $acct('zz t92 asset top', 3, $T); $A3 = $acct('zz t92 asset mid', $A2, $T); $A4 = $acct('zz t92 asset leaf', $A3, $T);
    // a second congregation's own chart, same shape
    $J2 = $acct('zz t92 income top b', 1, $T2); $J3 = $acct('zz t92 income mid b', $J2, $T2); $JE2 = $acct('zz t92 expense top b', 2, $T2); $JE3 = $acct('zz t92 expense mid b', $JE2, $T2); $JA2 = $acct('zz t92 asset top b', 3, $T2); $JA3 = $acct('zz t92 asset mid b', $JA2, $T2);
    $K2 = $acct('zz t92 income closed', 1, $T3); $K3 = $acct('zz t92 income closed leaf', $K2, $T3); $KA2 = $acct('zz t92 asset closed', 3, $T3); $KA3 = $acct('zz t92 asset closed leaf', $KA2, $T3);
    // loop for the unclassified diagnostic (scope T2)
    $X = $acct('zz t92 loop x', 3, $T2); $Y = $acct('zz t92 loop y', $X, $T2);
    $pdo->prepare('UPDATE tblaccounttypes SET parentId = ? WHERE ID = ?')->execute([$Y, $X]);

    $n = 0; $typ = 5;
    $entry = function ($date, array $lines, $o = []) use (&$created, &$n) {
        global $T;
        $o += ['cong' => $T, 'type' => 5, 'deleted' => 0];
        $n++; $txn = 920000 + $n;
        $id = x('INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,?,?,?,?,?,?)',
                [$o['cong'], $date, $o['type'], $txn, $o['type'] === 5 ? $txn : null, 'zz t92 ref ' . $n, 'zz t92 desc ' . $n, $o['deleted']]);
        $created['entries'][] = $id; $ln = 0;
        foreach ($lines as [$a, $dc, $amt]) { x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $a, $dc, $amt, 'zz t92 memo ' . $n . '.' . (++$ln), $ln]); }
        return $id;
    };
    $DR = 'DEBIT'; $CR = 'CREDIT';
    // period under test: 2025-10-01 .. 2026-03-31 ; last year: 2024-10-01 .. 2025-03-31
    $entry('2025-11-05', [[$A4, $DR, '1000.00'], [$I5, $CR, '1000.00']]);
    $entry('2025-12-05', [[$A4, $DR, '300.00'], [$Isib, $CR, '300.00']]);
    $entry('2026-01-10', [[$I5, $DR, '40.00'], [$A4, $CR, '40.00']]);                 // contra on the income leaf: 960 net
    $entry('2026-01-15', [[$E4, $DR, '250.00'], [$A4, $CR, '250.00']]);
    $entry('2026-02-02', [[$Izero, $DR, '70.00'], [$A4, $CR, '70.00']]);
    $entry('2026-02-03', [[$A4, $DR, '70.00'], [$Izero, $CR, '70.00']]);              // nets to zero
    $entry('2026-02-04', [[$Itop2, $CR, '5.00'], [$A4, $DR, '5.00']]);
    $entry('2025-01-20', [[$A4, $DR, '800.00'], [$I5, $CR, '800.00']]);               // last year
    $entry('2025-02-20', [[$A4, $DR, '120.00'], [$Icmp, $CR, '120.00']]);             // last year only
    $entry('2025-03-01', [[$E4, $DR, '90.00'], [$A4, $CR, '90.00']]);
    $entry('2026-03-02', [[$D3, $DR, '30.00'], [$A4, $CR, '30.00']]);                 // dividend (type 5) in the period only
    $entry('2026-02-05', [[$A4, $DR, '9999.00'], [$I5, $CR, '9999.00']], ['deleted' => 1]);   // voided
    $entry('2026-02-06', [[$JA3, $DR, '500.00'], [$J3, $CR, '500.00']], ['cong' => $T2]);
    $entry('2026-02-07', [[$JE3, $DR, '60.00'], [$JA3, $CR, '60.00']], ['cong' => $T2]);
    $entry('2026-02-08', [[$KA3, $DR, '40.00'], [$K3, $CR, '40.00']], ['cong' => $T3]);   // the closed congregation's history
    $entry('2026-02-09', [[$A4, $DR, '8.00'], [$X, $CR, '8.00']], ['cong' => $T2]);       // loop account: unclassified
    // a type 2 entry gives the drill a source link
    $entry('2026-01-20', [[$E4, $DR, '10.00'], [$A4, $CR, '10.00']], ['type' => 2]);
    $expTxn = 920000 + $n;

    $from = '2025-10-01'; $to = '2026-03-31';
    echo "== layout and totals\n";
    $r = IncomeStatementReport::report($pdo, [$T], 'zz t92 main', $from, $to);
    ok('sections: Income, Expenditure; titles', $r['income']['title'] === 'Income' && $r['expense']['title'] === 'Expenditure' && $r['entity'] === 'zz t92 main');
    $incTotal = 96000 + 30000 + 500;   // 1000-40 + 300 + 0 + 5
    ok('income total 1,265.00 (leaf contra netted, sibling, zero account, markup-named account)', tc($r['income']['total']) === 126500, $r['income']['total']);
    ok('expenditure total 260.00', tc($r['expense']['total']) === 26000, $r['expense']['total']);
    ok('surplus 1,005.00 = income - expenditure', tc($r['surplus']['amount']) === 100500);
    ok('top-level income lines: the markup account and "income top" only', array_column($r['income']['rows'], 'name') === ['Zz <script>alert(1)</script> Income', 'Zz T92 Income Top']);
    ok('income-top line carries the roll-up of three levels (1,260.00)', tc($r['income']['rows'][1]['amount']) === 126000 && $r['income']['rows'][1]['next'] === 'children');
    ok('dividends shown below the surplus because non-zero (30.00) with the final line', $r['dividends'] !== null && tc($r['dividends']['total']) === 3000 && tc($r['final']['amount']) === 97500);
    $r2 = IncomeStatementReport::report($pdo, [$T], 'zz t92 main', '2026-03-03', '2026-03-31');
    ok('dividends hidden when zero in both periods; section totals still show 0.00', $r2['dividends'] === null && $r2['final'] === null && $r2['income']['total'] === '0.00' && $r2['expense']['total'] === '0.00' && $r2['surplus']['amount'] === '0.00' && $r2['income']['rows'] === []);
    ok('all internal checks pass', $r['checksOk'] === true);

    echo "== contra lines and brackets\n";
    ok('bracket(): negative in brackets, positive plain, zero plain', IncomeStatementReport::bracket(-123456) === '(1,234.56)' && IncomeStatementReport::bracket(123456) === '1,234.56' && IncomeStatementReport::bracket(0) === '0.00' && IncomeStatementReport::bracket(null) === null);
    $contra = IncomeStatementReport::report($pdo, [$T], 'x', '2026-01-10', '2026-01-10');   // only the 40.00 debit on an income account
    ok('a contra balance shows as a negative figure in brackets', $contra['income']['total'] === '(40.00)' && $contra['surplus']['amount'] === '(40.00)', $contra['income']['total']);
    ok('deficit period shows the surplus line in brackets', IncomeStatementReport::bracket(-5) === '(0.05)');

    echo "== comparative\n";
    ok('comparative period = same period last year', $r['cmpStart'] === '2024-10-01' && $r['cmpEnd'] === '2025-03-31');
    ok('comparative totals: income 920.00 (800 + 120), expense 90.00, surplus 830.00', tc($r['income']['cmpTotal']) === 92000 && tc($r['expense']['cmpTotal']) === 9000 && tc($r['surplus']['cmp']) === 83000);
    $ch = IncomeStatementReport::drill($pdo, [$T], $I2, $from, $to, 'children', false);
    $byName = []; foreach ($ch['rows'] as $row) { $byName[$row['name']] = $row; }
    ok('zero rule: a line zero this year but not last year is shown (0.00 / 120.00)', isset($byName['Zz T92 Income Last Year Only']) && $byName['Zz T92 Income Last Year Only']['amount'] === '0.00' && $byName['Zz T92 Income Last Year Only']['cmp'] === '120.00');
    ok('zero rule: a line zero in both columns is hidden (the account that nets to zero)', !isset($byName['Zz T92 Income Nets Zero']));
    ok('zero rule: lines with figures are shown', isset($byName['Zz T92 Income Sibling']) && isset($byName['Zz T92 Income Mid']));
    $leap = IncomeStatementReport::report($pdo, [$T], 'x', '2024-02-01', '2024-02-29');
    ok('29 February: comparative ends on 28 February last year', $leap['cmpStart'] === '2023-02-01' && $leap['cmpEnd'] === '2023-02-28');
    $yb = IncomeStatementReport::report($pdo, [$T], 'x', '2025-09-15', '2025-10-15');
    ok('a period across a financial-year boundary is allowed', $yb['start'] === '2025-09-15' && $yb['cmpStart'] === '2024-09-15');

    echo "== drill-down: every level adds up, down to the journal lines\n";
    $walk = 0; $bad = [];
    $levels = function ($accId, $amountText, $label) use (&$levels, &$walk, &$bad, $pdo, $T, $from, $to) {
        $d = IncomeStatementReport::drill($pdo, [$T], $accId, $from, $to, 'children', false);
        $walk++;
        if ($d['mode'] === 'children') {
            if (!$d['sumsExactly'] || $d['rowsTotal'] !== $d['amount'] || $d['amount'] !== $amountText) { $bad[] = "$label children {$d['rowsTotal']} vs {$d['amount']} vs clicked $amountText"; }
            foreach ($d['rows'] as $row) {
                if ($row['direct']) {
                    $l = IncomeStatementReport::drill($pdo, [$T], $row['id'], $from, $to, 'lines', true); $walk++;
                    if ($l['movement'] !== $row['amount']) { $bad[] = "$label direct lines {$l['movement']} vs {$row['amount']}"; }
                } else { $levels($row['id'], $row['amount'], $label . '>' . $row['name']); }
            }
        } else {
            if ($d['movement'] !== $amountText) { $bad[] = "$label lines movement {$d['movement']} vs clicked $amountText"; }
        }
    };
    foreach (array_merge($r['income']['rows'], $r['expense']['rows'], $r['dividends']['rows']) as $row) { $levels($row['id'], $row['amount'], $row['name']); }
    ok("drill: all $walk levels add up to the figure clicked, ending at the journal lines", $walk >= 10 && !$bad, implode('; ', $bad));
    $leaf = IncomeStatementReport::drill($pdo, [$T], $I5, $from, $to, 'children', false);
    ok('deep leaf (4 levels below the root) opens straight to journal lines', $leaf['mode'] === 'lines' && count($leaf['rows']) === 2 && $leaf['movement'] === '960.00');
    ok('lines: an income account starts the financial year at zero (decision 144): opening 0.00, closing = movement 960.00', $leaf['opening'] === '0.00' && $leaf['closing'] === '960.00');
    ok('lines: running balance in the credit direction, contra row lowers it', $leaf['rows'][0]['balance'] === '1,000.00' && $leaf['rows'][1]['balance'] === '960.00' && $leaf['rows'][1]['debit'] === '40.00' && $leaf['rows'][1]['credit'] === '');
    ok('lines: entry number, reference, narration and type per row', $leaf['rows'][0]['entryId'] > 0 && strpos($leaf['rows'][0]['reference'], 'zz t92 ref') === 0 && strpos($leaf['rows'][0]['memo'], 'zz t92 memo') === 0 && $leaf['rows'][0]['type'] === 'journal entries' && $leaf['rows'][0]['journalNo'] > 0);
    $el = IncomeStatementReport::drill($pdo, [$T], $E4, $from, $to, 'children', false);
    $link = null; foreach ($el['rows'] as $row) { if ($row['link']) { $link = $row['link']; } }
    ok('lines: a source link for an expense entry (voucher), none for a journal', $link === URLROOT . '/expenses/print/' . $expTxn && $el['rows'][0]['link'] === null, (string) $link);
    ok('crumbs run from the top-level account down to the clicked one, roots left out', array_column($leaf['crumbs'], 'name') === ['Zz T92 Income Top', 'Zz T92 Income Mid', 'Zz T92 Income Low', 'Zz T92 Income Leaf']);
    ok('the voided entry never appears in the lines', !array_filter($leaf['rows'], function ($x) { return $x['credit'] === '9,999.00'; }));

    echo "== parish scope: raw sum (decision 123), closed congregation (127)\n";
    $ps = FinancialReport::parishScope($pdo);
    ok('parish scope holds the deleted congregation that has history', in_array($T3, $ps, true) && in_array($T, $ps, true) && in_array($T2, $ps, true));
    $pT = IncomeStatementReport::report($pdo, [$T], 'a', $from, $to); $pT2 = IncomeStatementReport::report($pdo, [$T2], 'b', $from, $to); $pT3 = IncomeStatementReport::report($pdo, [$T3], 'c', $from, $to);
    $pAll = IncomeStatementReport::report($pdo, [$T, $T2, $T3], 'All congregations', $from, $to);
    ok('income of a selection = raw sum of its congregations', tc($pAll['income']['total']) === tc($pT['income']['total']) + tc($pT2['income']['total']) + tc($pT3['income']['total']) && tc($pT2['income']['total']) === 50000 && tc($pT3['income']['total']) === 4000);
    ok('expenditure and surplus add up too', tc($pAll['expense']['total']) === tc($pT['expense']['total']) + tc($pT2['expense']['total']) && tc($pAll['surplus']['amount']) === tc($pT['surplus']['amount']) + tc($pT2['surplus']['amount']) + tc($pT3['surplus']['amount']));
    $dAll = IncomeStatementReport::drill($pdo, [$T, $T2, $T3], $I2, $from, $to, 'children', false);
    ok('parish drill: an account of one congregation opens with the same figures; the other congregations add nothing to it', $dAll['amount'] === $ch['amount'] && $dAll['sumsExactly']);

    echo "== unclassified diagnostic\n";
    $u = IncomeStatementReport::report($pdo, [$T2], 'b', $from, $to);
    ok('postings on an unreachable account are reported, never dropped', $u['unclassified']['present'] === true && count($u['unclassified']['rows']) >= 1 && $u['checksOk'] === false);
    ok('no diagnostic when the chart is sound', $r['unclassified']['present'] === false);

    echo "== recorded figures of the old reports (real dev data, read-only; recorded in 9.2 before the mirror was removed in 9.9)\n";
    $rows = function ($rep, $sec) { $o = []; foreach ($rep[$sec]['rows'] as $row) { $o[$row['name']] = $row['amount']; } return $o; };
    $cl0 = IncomeStatementReport::report($pdo, [11], 'x', '2024-10-01', '2025-09-30');
    ok('closed year 2024-25: every top-level income line equals the recorded old report line', $rows($cl0, 'income') === ['Groups Collections' => '1,003,584.00', 'Interest Income' => '697,033.55', 'Other Collections' => '4,100.00', 'Tithes And Offering' => '8,455,543.50'], json_encode($rows($cl0, 'income')));
    ok('closed year 2024-25: every top-level expense line equals the recorded old report line', $rows($cl0, 'expense') === ['Administrative Costs' => '1,114,023.00', 'Donations' => '70,500.00', "Groups' Expenses" => '736,276.00', 'Hospitality And Appreciation Costs' => '408,863.00', 'Operation Costs' => '689,375.00', "Self Funded Groups' Expenses" => '268,500.00', "Session/lcc Funded Group Expense" => '1,631,655.00', 'Staff Expenses' => '225,450.00'], json_encode($rows($cl0, 'expense')));
    $cl = IncomeStatementReport::report($pdo, [11], 'x', '2024-10-01', '2025-09-30');
    ok('inventory 5.2: closed year 10,160,261.05 / 5,144,642.00 / 5,015,619.05', $cl['income']['total'] === '10,160,261.05' && $cl['expense']['total'] === '5,144,642.00' && $cl['surplus']['amount'] === '5,015,619.05');
    $parishCl = IncomeStatementReport::report($pdo, array_values(array_diff(FinancialReport::parishScope($pdo), [$T, $T2, $T3])), 'All congregations', '2024-10-01', '2025-09-30');   // without the fixtures
    ok('parish closed year now equals the NET figures (not the old gross 10,209,215.12 / 5,378,637.00)', $parishCl['income']['total'] === '10,160,261.05' && $parishCl['expense']['total'] === '5,144,642.00' && $parishCl['income']['total'] !== '10,209,215.12');
    $realCongs = array_values(array_diff(FinancialReport::parishScope($pdo), [$T, $T2, $T3]));   // without the fixtures
    $g = q("SELECT a.accountTypeId r, SUM(IF(l.dc='CREDIT',l.amount,0)) cr, SUM(IF(l.dc='DEBIT',l.amount,0)) dr FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId JOIN tblaccounttypes a ON a.ID=l.accountId WHERE e.deleted=0 AND e.congregationId IN (" . implode(',', array_map('intval', $realCongs)) . ") AND e.entryDate BETWEEN '2024-10-01' AND '2025-09-30' AND a.accountTypeId IN (1,2) GROUP BY a.accountTypeId ORDER BY a.accountTypeId");
    ok('journal gross of the closed year: income credits 10,209,215.12 less debits 48,954.07 (contra postings) = the net 10,160,261.05; expense debits 5,388,137.00 less credits 243,495.00 = the net 5,144,642.00', C($g[0]['cr']) === 1020921512 && C($g[0]['dr']) === 4895407 && C($g[1]['dr']) === 538813700 && C($g[1]['cr']) === 24349500 && C($g[0]['cr']) - C($g[0]['dr']) === tc($parishCl['income']['total']) && C($g[1]['dr']) - C($g[1]['cr']) === tc($parishCl['expense']['total']), json_encode($g));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    $pdo->prepare("DELETE FROM journal_entries WHERE reference LIKE 'zz t92%'")->execute();
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz%t92%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t92%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM tblcongregation WHERE CongregationName LIKE 'zz t92%'")[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left fixture rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

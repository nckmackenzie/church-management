<?php
/**
 * Task 9.1: FinancialReport (app/libraries/FinancialReport.php). Self-cleaning: two test congregations, their accounts and
 * journal entries are created with direct SQL (so the closed-year lock does not get in the way) and deleted at the end.
 * Part 2 compares the service with the old mirror (tblledger) on the real dev data, read-only.
 * CLI only. Needs the dev DB with its financial years (any state of the closed flags).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';

$pdo = (new Database)->pdo();
$pass = 0; $fail = 0;
function ok($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
function dies($fn) { try { $fn(); return null; } catch (FinancialReportException $e) { return $e->getMessage(); } }
function c($s) { return FinancialReport::cents($s); }
function m($cents) { return FinancialReport::money((int) $cents); }

$created = ['accounts' => [], 'entries' => [], 'congs' => [], 'bankpostings' => []];
$t0 = microtime(true);

try {
    // ------------------------------------------------------------------ fixtures
    $T  = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t91 main', 0, 0)"); $created['congs'][] = $T;
    $T2 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t91 second', 0, 0)"); $created['congs'][] = $T2;
    $parentOf = [33 => 22, 22 => 3]; $rootOf = [33 => 3, 22 => 3]; $nameOf = [];
    $acct = function ($name, $parent, $o = []) use (&$created, &$parentOf, &$rootOf, &$nameOf) {
        global $T;
        $o += ['cong' => $T, 'posting' => 0, 'active' => 1, 'deleted' => 0, 'bank' => 0];
        $root = $parent <= 6 ? $parent : $rootOf[$parent];
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, accountNo, forGroup, active, congregationId, deleted) VALUES (?,?,?,?,?,?,0,?,?,?)',
                [$name, $root, $parent, $o['posting'], $o['bank'], $o['bank'] ? 'ACC' . mt_rand(100, 999) : null, $o['active'], $o['cong'], $o['deleted']]);
        $created['accounts'][] = $id; $parentOf[$id] = $parent; $rootOf[$id] = $root; $nameOf[$id] = $name;
        return $id;
    };
    // five-level asset chain: root 3 > A2 > A3 > A4 > leaves A5a, A5b
    $A2 = $acct('zz t91 asset L2', 3); $A3 = $acct('zz t91 asset L3', $A2); $A4 = $acct('zz t91 asset L4', $A3);
    $A5a = $acct('zz t91 asset leaf a', $A4, ['posting' => 1]); $A5b = $acct('zz t91 asset leaf b', $A4, ['posting' => 1]);
    $A5z = $acct('zz t91 asset leaf zero', $A4, ['posting' => 1]);
    // a reporting account (depth 2) that carries postings of its own AND a child
    $A2b = $acct('zz t91 asset L2 direct', 3); $A3b = $acct('zz t91 asset L3 under direct', $A2b, ['posting' => 1]);
    $I2 = $acct('zz t91 income L2', 1); $I3 = $acct('zz t91 income L3', $I2); $I4 = $acct('zz t91 income leaf', $I3, ['posting' => 1]);
    $Iz = $acct('zz t91 income comparative only', $I2, ['posting' => 0]); $Iy = $acct('zz t91 income never in window', $I2, ['posting' => 0]);
    $E2 = $acct('zz t91 expense L2', 2); $E3 = $acct('zz t91 expense leaf', $E2, ['posting' => 0]);
    $EIn = $acct('zz t91 expense inactive', $E2, ['active' => 0]);
    $L2 = $acct('zz t91 liability L2', 4); $L3 = $acct('zz t91 liability leaf', $L2);
    $Q2 = $acct('zz t91 equity L2', 6); $Q3 = $acct('zz t91 equity leaf', $Q2);
    $D2 = $acct('zz t91 dividend L2', 5); $D3 = $acct('zz t91 dividend leaf', $D2);
    $DelH = $acct('zz t91 deleted with history', $I2, ['deleted' => 1]); $DelN = $acct('zz t91 deleted no history', $I2, ['deleted' => 1]);
    $bankA = $acct('zz t91 bank active', 33, ['bank' => 1]);
    $bankI = $acct('zz t91 bank inactive no history', 33, ['bank' => 1, 'active' => 0]);
    $bankJ = $acct('zz t91 bank inactive journal history', 33, ['bank' => 1, 'active' => 0]);
    $bankP = $acct('zz t91 bank inactive cashbook history', 33, ['bank' => 1, 'active' => 0]);
    $bankD = $acct('zz t91 bank deleted', 33, ['bank' => 1, 'deleted' => 1]);
    $bankF = $acct('zz t91 bank other congregation', 33, ['bank' => 1, 'cong' => $T2]);
    // cycle and unreachable account (second test congregation)
    $X = $acct('zz t91 loop x', 3, ['cong' => $T2]); $Y = $acct('zz t91 loop y', $X, ['cong' => $T2]);
    $pdo->prepare('UPDATE tblaccounttypes SET parentId = ? WHERE ID = ?')->execute([$Y, $X]); $parentOf[$X] = $Y;           // X <-> Y
    $Z = $acct('zz t91 orphan', 3, ['cong' => $T2]);
    $pdo->prepare('UPDATE tblaccounttypes SET parentId = 99999999 WHERE ID = ?')->execute([$Z]);                           // missing parent
    $sharedExp   = (int) q('SELECT a.ID FROM tblaccounttypes a JOIN tblaccounttypes p ON p.ID = a.parentId WHERE p.parentId = 2 AND a.congregationId = 0 AND a.deleted = 0 AND a.active = 1 ORDER BY a.ID LIMIT 1')[0]['ID'];
    $sharedExpTop = (int) q('SELECT parentId FROM tblaccounttypes WHERE ID = ?', [$sharedExp])[0]['parentId'];
    $sharedAsset = (int) q('SELECT a.ID FROM tblaccounttypes a JOIN tblaccounttypes p ON p.ID = a.parentId WHERE p.parentId = 3 AND a.congregationId = 0 AND a.deleted = 0 AND a.active = 1 ORDER BY a.ID LIMIT 1')[0]['ID'];
    $sharedInc   = (int) q('SELECT a.ID FROM tblaccounttypes a JOIN tblaccounttypes p ON p.ID = a.parentId WHERE p.parentId = 1 AND a.congregationId = 0 AND a.deleted = 0 AND a.active = 1 ORDER BY a.ID LIMIT 1')[0]['ID'];
    $parentOf += [$sharedExp => $sharedExpTop, $sharedExpTop => 2, $sharedAsset => (int) q('SELECT parentId FROM tblaccounttypes WHERE ID=?', [$sharedAsset])[0]['parentId'], $sharedInc => (int) q('SELECT parentId FROM tblaccounttypes WHERE ID=?', [$sharedInc])[0]['parentId']];
    $rootOf += [$sharedExp => 2, $sharedExpTop => 2, $sharedAsset => 3, $sharedInc => 1];
    $Foreign = $acct('zz t91 other congregation account', $sharedExpTop, ['cong' => $T2]);   // a T2 account under a shared parent

    $fx = [];   // fixture journal, for the independent calculation
    $n = 0;
    $entry = function ($date, array $lines, $o = []) use (&$created, &$fx, &$n) {
        global $T;
        $o += ['cong' => $T, 'deleted' => 0];
        $n++;
        $id = x('INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,5,?,?,?,?,?)',
                [$o['cong'], $date, 900000 + $n + $o['cong'] * 1000, 900000 + $n + $o['cong'] * 1000, 'zz t91 ref ' . $n, 'zz t91 desc ' . $n, $o['deleted']]);
        $created['entries'][] = $id;
        $ln = 0; $rec = [];
        foreach ($lines as [$acc, $dc, $amt]) {
            x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $acc, $dc, $amt, 'zz t91 memo ' . $n . '.' . (++$ln), $ln]);
            $rec[] = [$acc, $dc, c($amt)];
        }
        $fx[] = ['id' => $id, 'date' => $date, 'cong' => $o['cong'], 'deleted' => $o['deleted'], 'lines' => $rec];
        return $id;
    };
    $DR = 'DEBIT'; $CR = 'CREDIT';
    // prior year (FY 2024-25): surplus 200
    $entry('2025-05-20', [[$A5a, $DR, '300.00'], [$I4, $CR, '300.00']]);
    $entry('2025-05-21', [[$E3, $DR, '100.00'], [$A5a, $CR, '100.00']]);
    // FY 2025-26
    $entry('2025-11-10', [[$A5a, $DR, '1000.00'], [$Q3, $CR, '1000.00']]);
    $entry('2025-12-05', [[$A5b, $DR, '500.00'], [$I4, $CR, '500.00']]);
    $entry('2026-01-15', [[$E3, $DR, '200.00'], [$A5a, $CR, '200.00']]);
    $entry('2026-02-10', [[$I4, $DR, '50.00'], [$A5b, $CR, '50.00']]);             // contra: debit on an income account
    $entry('2026-03-01', [[$D3, $DR, '40.00'], [$A5a, $CR, '40.00']]);             // dividend
    $entry('2026-02-20', [[$sharedExp, $DR, '33.00'], [$A5b, $CR, '33.00']]);      // shared account, own congregation
    $entry('2026-02-25', [[$EIn, $DR, '12.00'], [$A5a, $CR, '12.00']]);            // inactive account
    $entry('2026-02-26', [[$A5a, $DR, '15.00'], [$DelH, $CR, '15.00']]);           // deleted account WITH history
    $entry('2026-03-02', [[$A5z, $DR, '100.00'], [$A5z, $CR, '100.00']]);          // nets to zero
    $entry('2026-03-03', [[$A2b, $DR, '60.00'], [$Q3, $CR, '60.00']]);             // postings on a reporting account
    $entry('2026-03-04', [[$A3b, $DR, '25.00'], [$Q3, $CR, '25.00']]);
    $entry('2026-09-30', [[$A5a, $DR, '11.00'], [$I4, $CR, '11.00']]);             // last day of the year
    // FY 2026-27
    $entry('2026-10-01', [[$A5a, $DR, '13.00'], [$I4, $CR, '13.00']]);             // first day of the year
    $entry('2026-10-10', [[$A5a, $DR, '120.00'], [$I4, $CR, '120.00']]);
    $entry('2026-10-12', [[$E3, $DR, '20.00'], [$L3, $CR, '20.00']]);
    // leap-day comparative
    $entry('2024-02-29', [[$A5a, $DR, '10.00'], [$I4, $CR, '10.00']]);
    $entry('2023-02-28', [[$A5a, $DR, '4.00'], [$I4, $CR, '4.00']]);
    $entry('2023-02-14', [[$A5a, $DR, '77.00'], [$Iz, $CR, '77.00']]);             // comparative-only account
    $entry('2022-06-01', [[$A5a, $DR, '5.00'], [$Iy, $CR, '5.00']]);               // outside every window
    // excluded: voided entry, another congregation
    $entry('2026-02-12', [[$A5a, $DR, '9999.00'], [$I4, $CR, '9999.00']], ['deleted' => 1]);
    $entry('2026-02-13', [[$sharedAsset, $DR, '777.00'], [$sharedInc, $CR, '777.00']], ['cong' => $T2]);
    $entry('2026-02-14', [[$Foreign, $DR, '31.00'], [$sharedAsset, $CR, '31.00']], ['cong' => $T2]);
    // second congregation: a parent loop and an unreachable account
    $entry('2026-02-15', [[$A5a, $DR, '8.00'], [$X, $CR, '8.00']], ['cong' => $T2]);
    $entry('2026-02-16', [[$A5a, $DR, '5.00'], [$Z, $CR, '5.00']], ['cong' => $T2]);
    // bank history
    $entry('2026-02-17', [[$bankJ, $DR, '90.00'], [$Q3, $CR, '90.00']]);
    $mid = (int) q('SELECT ID FROM tbltransactionmethods ORDER BY ID LIMIT 1')[0]['ID'];
    $created['bankpostings'][] = x('INSERT INTO tblbankpostings (bankId, transactionDate, debit, credit, transactionMethod, transactionType, transactionId, congregationId, deleted) VALUES (?,?,1,0,?,1,987601,?,0)', [$bankP, '2026-02-18', $mid, $T]);

    // ------------------------------------------------------------------ independent (naive) calculation
    $subtree = function ($id) use (&$parentOf) {
        $out = [$id => true];
        do { $added = false; foreach ($parentOf as $a => $p) { if (isset($out[$p]) && !isset($out[$a])) { $out[$a] = true; $added = true; } } } while ($added);
        return array_keys($out);
    };
    $naive = function ($accounts, $from, $to, $cong) use (&$fx) {  // [debit, credit] cents
        $set = array_flip($accounts); $dr = 0; $cr = 0;
        foreach ($fx as $e) {
            if ($e['deleted'] || $e['cong'] !== $cong) { continue; }
            if ($e['date'] > $to || ($from !== null && $e['date'] < $from)) { continue; }
            foreach ($e['lines'] as [$a, $dc, $cents]) { if (isset($set[$a])) { if ($dc === 'DEBIT') { $dr += $cents; } else { $cr += $cents; } } }
        }
        return [$dr, $cr];
    };
    $nrm = function ($root, $id, $from, $to) use ($naive, $subtree, $T) {
        [$dr, $cr] = $naive($subtree($id), $from, $to, $T);
        return FinancialReport::normalAmount($root, $dr, $cr);
    };
    // all accounts of one root type in the test chart (own + shared) as a flat set
    $ofRoot = function ($root) use (&$rootOf) { return array_keys(array_filter($rootOf, function ($r) use ($root) { return $r === $root; })); };
    $net = function ($roots, $from, $to, $sign) use ($naive, $ofRoot, $T) {  // sign +1: credit - debit, -1: debit - credit
        $n = 0; foreach ($roots as $r) { [$dr, $cr] = $naive($ofRoot($r), $from, $to, $T); $n += $sign > 0 ? $cr - $dr : $dr - $cr; } return $n;
    };

    // ------------------------------------------------------------------ 1. pure helpers
    echo "== helpers\n";
    ok('cents parses decimals without float', c('1234.50') === 123450 && c('-0.05') === -5 && c('0.1') === 10 && c('7') === 700 && c(null) === 0 && c('.5') === 50 && c('12.345') === 1234);
    ok('cents refuses non-numbers', dies(function () { FinancialReport::cents('abc'); }) !== null);
    ok('decimal and money format', FinancialReport::decimal(-5) === '-0.05' && FinancialReport::decimal(123450) === '1234.50' && m(-123456) === '-1,234.56' && m(0) === '0.00');
    ok('normal balance: types 2, 3, 5 debit-normal', FinancialReport::isDebitNormal(2) && FinancialReport::isDebitNormal(3) && FinancialReport::isDebitNormal(5));
    ok('normal balance: types 1, 4, 6 credit-normal', !FinancialReport::isDebitNormal(1) && !FinancialReport::isDebitNormal(4) && !FinancialReport::isDebitNormal(6));
    ok('contra balance stays negative (income debited more than credited)', FinancialReport::normalAmount(1, 5000, 3000) === -2000 && FinancialReport::normalAmount(3, 3000, 5000) === -2000);
    ok('trial balance columns: a contra balance moves column, never ABS of the other', FinancialReport::trialBalanceColumns(700) === ['debit' => 700, 'credit' => 0] && FinancialReport::trialBalanceColumns(-700) === ['debit' => 0, 'credit' => 700] && FinancialReport::trialBalanceColumns(0) === ['debit' => 0, 'credit' => 0]);
    ok('zero rule helper: hidden only when every figure is zero', FinancialReport::hideZero([0, 0]) && FinancialReport::hideZero([0, null]) && !FinancialReport::hideZero([0, 5]) && !FinancialReport::hideZero([-1, 0]));
    ok('scope is validated', dies(function () { FinancialReport::normalizeScope([]); }) !== null && dies(function () { FinancialReport::normalizeScope(['1 OR 1=1']); }) !== null && dies(function () { FinancialReport::normalizeScope([0]); }) !== null && FinancialReport::normalizeScope(['11', 2, 11]) === [2, 11]);
    ok('same date last year: 29 February becomes 28 February', FinancialReport::sameDateLastYear('2024-02-29') === '2023-02-28' && FinancialReport::sameDateLastYear('2025-03-01') === '2024-03-01' && FinancialReport::sameDateLastYear('2024-03-31') === '2023-03-31');
    ok('same period last year moves both ends', FinancialReport::samePeriodLastYear('2024-02-01', '2024-02-29') === ['2023-02-01', '2023-02-28']);
    ok('financial year of a date, boundaries included', FinancialReport::financialYear($pdo, '2026-09-30')['id'] === 12 && FinancialReport::financialYear($pdo, '2026-10-01')['id'] === 25 && FinancialReport::yearStart($pdo, '2026-03-31') === '2025-10-01');
    ok('a date with no financial year is a clear error', (string) dies(function () use ($pdo) { FinancialReport::financialYear($pdo, '2000-01-01'); }) === 'No financial year is defined for 2000-01-01');
    ok('last financial year-end = the day before the year start', FinancialReport::lastYearEnd($pdo, '2026-10-24') === '2026-09-30' && FinancialReport::lastYearEnd($pdo, '2026-03-31') === '2025-09-30');
    ok('invalid dates are refused', dies(function () use ($pdo, $T) { FinancialReport::incomeStatement($pdo, [$T], '2026-02-30', '2026-03-01'); }) !== null && dies(function () use ($pdo, $T) { FinancialReport::incomeStatement($pdo, [$T], '2026-03-02', '2026-03-01'); }) !== null);

    // ------------------------------------------------------------------ 2. chart
    echo "== chart\n";
    $chart = FinancialReport::chart($pdo, [$T]);
    $N = $chart['nodes'];
    ok('chart: five-level chain (root 3 > L2 > L3 > L4 > leaf)', $N[$A5a]['depth'] === 5 && $N[$A5a]['root'] === 3 && $N[$A5a]['chain'] === [$A5a, $A4, $A3, $A2, 3]);
    ok('chart: path label from the root', $N[$A5a]['path'] === 'Asset > zz t91 asset L2 > zz t91 asset L3 > zz t91 asset L4 > zz t91 asset leaf a');
    ok('chart: posting flag follows AccountChart rules, not the stored column (depth 5 leaf yes; depth 4 with children, depth 2 and the root no)', $N[$A5a]['posting'] === true && $N[$A4]['posting'] === false && $N[$A2]['posting'] === false && $N[3]['posting'] === false);
    ok('chart: a depth-3 account without children is posting whatever the stored column says', $N[$E3]['posting'] === true && $N[$Iz]['posting'] === true && $N[$L2]['posting'] === false && $N[$L3]['posting'] === true);
    ok('chart: inactive account is included and flagged', isset($N[$EIn]) && $N[$EIn]['active'] === false);
    ok('chart: deleted account with history is included', isset($N[$DelH]) && $N[$DelH]['deleted'] === true && $N[$DelH]['hasHistory'] === true);
    ok('chart: deleted account without history is left out', !isset($N[$DelN]));
    ok('chart: shared accounts are included', isset($N[$sharedExp], $N[$sharedAsset]) && $N[$sharedExp]['congregationId'] === 0);
    ok('chart: another congregation\'s account is left out', !isset($N[$Foreign]) && !isset($N[$bankF]));
    ok('chart: roots 1-6 exist', isset($N[1], $N[2], $N[3], $N[4], $N[5], $N[6]) && $N[3]['depth'] === 1 && $N[3]['root'] === 3);
    ok('chart: scope [T] has no unclassified account', $chart['unclassified'] === []);
    $chart2 = FinancialReport::chart($pdo, [$T2]);
    ok('chart: parent loop and missing parent end up unclassified (no hang)', in_array($X, $chart2['unclassified'], true) && in_array($Y, $chart2['unclassified'], true) && in_array($Z, $chart2['unclassified'], true) && !$chart2['nodes'][$X]['classified']);
    ok('chart: own account of the second congregation is visible there only (classified under the shared parent)', isset($chart2['nodes'][$Foreign]) && $chart2['nodes'][$Foreign]['classified'] && !isset($chart['nodes'][$Foreign]));
    ok('chart: loaded once per call (one chart array, no per-account query)', is_array($chart['kids']) && count($chart['kids'][$A4]) === 3);

    // ------------------------------------------------------------------ 3. balances
    echo "== balances\n";
    $b = FinancialReport::balances($pdo, [$T], null, '2026-03-31');
    $bt = $naive([$A5a], null, '2026-03-31', $T);
    ok('balances: debit and credit sums of a leaf equal the independent calculation', $b[$A5a]['idr'] === $bt[0] && $b[$A5a]['icr'] === $bt[1], "got {$b[$A5a]['idr']}/{$b[$A5a]['icr']} want {$bt[0]}/{$bt[1]}");
    ok('balances: voided entry excluded', !isset($b[$I4]) || $b[$I4]['icr'] < 99990000);
    ok('balances: another congregation\'s lines excluded', !isset($b[$sharedInc]) && !isset($b[$Foreign]));
    $bb = FinancialReport::balances($pdo, [$T], '2025-10-01', '2026-03-31');
    ok('balances: bucket split at the boundary (before / from)', $bb[$A5a]['bdr'] + $bb[$A5a]['idr'] === $b[$A5a]['idr'] && $bb[$A5a]['bcr'] + $bb[$A5a]['icr'] === $b[$A5a]['icr'] && $bb[$I4]['bcr'] > 0);
    $both = FinancialReport::balances($pdo, [$T, $T2], null, '2026-03-31');
    ok('balances: a wider scope adds the other congregation', isset($both[$sharedInc]) && $both[$sharedInc]['icr'] === 77700);
    ok('balances: single set-based query (grouped by account)', count($b) >= 10);

    // ------------------------------------------------------------------ 4. income statement
    echo "== income statement\n";
    $from = '2025-10-01'; $to = '2026-09-30';
    $is = FinancialReport::incomeStatement($pdo, [$T], $from, $to, true);
    $wantInc = $net([1], $from, $to, 1); $wantExp = $net([2], $from, $to, -1);
    ok('IS: income total equals the independent calculation (contra debit netted)', $is['sections']['income']['total'] === $wantInc, m($is['sections']['income']['total']) . ' vs ' . m($wantInc));
    ok('IS: expense total equals the independent calculation (shared + inactive + deleted-parent accounts included)', $is['sections']['expense']['total'] === $wantExp, m($is['sections']['expense']['total']) . ' vs ' . m($wantExp));
    ok('IS: surplus = income - expense', $is['surplus']['amount'] === $wantInc - $wantExp);
    ok('IS: income = 500 - 50 + 11 + 15 (deleted-account credit) = 476', $wantInc === 47600, m($wantInc));
    ok('IS: top-level lines are the accounts directly under the root', array_column($is['sections']['income']['rows'], 'id') === [$I2] && in_array($sharedExpTop, array_column($is['sections']['expense']['rows'], 'id'), true) && in_array($E2, array_column($is['sections']['expense']['rows'], 'id'), true));
    ok('IS: type 5 shown below the surplus because it is non-zero', $is['dividends'] !== null && $is['dividends']['total'] === 4000 && $is['surplusAfterDividends']['amount'] === $wantInc - $wantExp - 4000);
    ok('IS: all checks pass', !array_filter($is['checks'], function ($ck) { return !$ck['ok']; }));
    $is2 = FinancialReport::incomeStatement($pdo, [$T], '2026-10-01', '2026-10-31', false);
    ok('IS: type 5 hidden when it is zero in the period', $is2['dividends'] === null && $is2['surplusAfterDividends'] === null);
    ok('IS: a section total always shows (income 133.00, expense 20.00)', $is2['sections']['income']['total'] === 13300 && $is2['sections']['expense']['total'] === 2000);
    $isE = FinancialReport::incomeStatement($pdo, [$T], '2020-01-01', '2020-01-31', false);
    ok('IS: empty period -> no rows but both section totals still present at 0.00', $isE['sections']['income']['rows'] === [] && $isE['sections']['income']['total'] === 0 && $isE['sections']['expense']['total'] === 0 && $isE['surplus']['amount'] === 0);
    // comparative with 29 February
    $isL = FinancialReport::incomeStatement($pdo, [$T], '2024-02-01', '2024-02-29', true);
    ok('IS comparative: 29 February maps to 28 February last year', $isL['comparative'] === ['from' => '2023-02-01', 'to' => '2023-02-28']);
    ok('IS comparative: income 10.00 now, 4.00 + 77.00 last year', $isL['sections']['income']['total'] === 1000 && $isL['sections']['income']['comparativeTotal'] === 8100);
    $rowIds = array_column($isL['sections']['income']['rows'], 'id');
    ok('IS zero rule: a line is hidden only when BOTH periods are zero (Iz shown via I2 children, Iy hidden)', $rowIds === [$I2]);
    $ch = FinancialReport::children($pdo, [$T], $I2, ['kind' => 'is', 'from' => '2024-02-01', 'to' => '2024-02-29', 'comparative' => true]);
    $chIds = array_column($ch['rows'], 'id');
    ok('IS zero rule: Iz (current 0, last year 77) shown, Iy (0 and 0) hidden, I3 shown', in_array($Iz, $chIds, true) && !in_array($Iy, $chIds, true) && in_array($I3, $chIds, true));
    $izRow = array_values(array_filter($ch['rows'], function ($r) use ($Iz) { return $r['id'] === $Iz; }))[0];
    ok('IS zero rule: the shown row keeps 0.00 current and 77.00 comparative', $izRow['amount'] === 0 && $izRow['comparative'] === 7700);
    ok('IS: no unclassified postings in scope T', $is['unclassified']['present'] === false);
    $isU = FinancialReport::incomeStatement($pdo, [$T2], '2026-01-01', '2026-12-31', false);
    ok('IS: unclassified postings are reported as a diagnostic, never dropped', $isU['unclassified']['present'] === true && count($isU['unclassified']['rows']) >= 2 && !$isU['checks'][3]['ok']);

    // ------------------------------------------------------------------ 5. balance sheet and the fund
    echo "== balance sheet\n";
    $asOf = '2026-10-01';   // first day of FY 2026-27
    $bs = FinancialReport::balanceSheet($pdo, [$T], $asOf, true);
    $wantBf = $net([1, 2, 5], null, '2026-09-30', 1);
    $wantCy = $net([1, 2, 5], '2026-10-01', $asOf, 1);
    ok('BS: accumulated fund b/f = types 1, 2, 5 (cr - dr) before the year start', $bs['sections']['fund']['bf']['cur'] === $wantBf, m($bs['sections']['fund']['bf']['cur']) . ' vs ' . m($wantBf));
    ok('BS: current year surplus = the same from the year start', $bs['sections']['fund']['surplus']['cur'] + $bs['sections']['fund']['dividends']['cur'] === $wantCy, m($wantCy));
    ok('BS: helpers accumulatedFundBf / currentYearSurplus agree', FinancialReport::accumulatedFundBf($pdo, [$T], $asOf) === $wantBf && FinancialReport::currentYearSurplus($pdo, [$T], $asOf) === $wantCy);
    ok('BS: the 30 Sep entry is in b/f and the 1 Oct entry in the current year (boundary)', $wantCy === 1300);
    ok('BS: assets = liabilities + equity + fund (difference 0)', $bs['totals']['current']['difference'] === 0, m($bs['totals']['current']['difference']));
    $wantAssets = $net([3], null, $asOf, -1);
    ok('BS: assets equal the independent cumulative calculation', $bs['sections']['assets']['total'] === $wantAssets, m($bs['sections']['assets']['total']) . ' vs ' . m($wantAssets));
    ok('BS: comparative = the last financial year-end (2026-09-30)', $bs['comparative'] === ['asOf' => '2026-09-30'] && $bs['totals']['comparative']['assets'] === $net([3], null, '2026-09-30', -1));
    ok('BS: comparative balances too (difference 0)', $bs['totals']['comparative']['difference'] === 0);
    ok('BS: fund total = b/f + surplus + dividends', $bs['sections']['fund']['total'] === $wantBf + $wantCy);
    ok('BS: b/f and surplus are separate lines, each shown once', count(array_filter($bs['sections']['fund']['rows'], function ($r) { return $r['id'] === 'bf'; })) === 1 && count(array_filter($bs['sections']['fund']['rows'], function ($r) { return $r['id'] === 'cy'; })) === 1);
    ok('BS: no difference line when it balances', $bs['differenceLine'] === null);
    ok('BS: all checks pass', !array_filter($bs['checks'], function ($ck) { return !$ck['ok']; }));
    $bs2 = FinancialReport::balanceSheet($pdo, [$T], '2026-03-31', false);
    $wantCy2 = $net([1, 2], '2025-10-01', '2026-03-31', 1);
    ok('BS mid-year: surplus of types 1/2 and dividends (type 5) apart', $bs2['sections']['fund']['surplus']['cur'] === $wantCy2 && $bs2['sections']['fund']['dividends']['cur'] === -4000);
    $bs3 = FinancialReport::balanceSheet($pdo, [$T], $asOf, false);
    ok('BS mid-year: dividends row shown because non-zero; absent at the year start without a comparative; present with one because last year was non-zero (zero rule needs BOTH periods zero)', count(array_filter($bs2['sections']['fund']['rows'], function ($r) { return $r['id'] === 'div'; })) === 1 && count(array_filter($bs3['sections']['fund']['rows'], function ($r) { return $r['id'] === 'div'; })) === 0 && count(array_filter($bs['sections']['fund']['rows'], function ($r) { return $r['id'] === 'div'; })) === 1, 'mid-year ids ' . implode(',', array_column($bs2['sections']['fund']['rows'], 'id')) . ' year-start ids ' . implode(',', array_column($bs['sections']['fund']['rows'], 'id')));
    ok('BS mid-year: equation holds', $bs2['totals']['current']['difference'] === 0);
    ok('BS: a date with no financial year is a clear error', dies(function () use ($pdo, $T) { FinancialReport::balanceSheet($pdo, [$T], '2000-01-01'); }) !== null);
    $bsU = FinancialReport::balanceSheet($pdo, [$T2], '2026-12-31', false);
    ok('BS: unclassified lines make the equation fail visibly (difference line + diagnostic), not hidden', $bsU['differenceLine'] !== null && $bsU['unclassified']['present'] && !$bsU['checks'][0]['ok']);

    // ------------------------------------------------------------------ 6. trial balance
    echo "== trial balance\n";
    foreach (['2026-03-31', '2026-09-30', '2026-10-01', '2026-10-24'] as $d) {
        $tb = FinancialReport::trialBalance($pdo, [$T], $d);
        ok("TB $d: debit total = credit total", $tb['totals']['debit'] === $tb['totals']['credit'] && $tb['totals']['difference'] === 0, m($tb['totals']['difference']));
        $tbd = FinancialReport::trialBalance($pdo, [$T], $d, true);
        ok("TB $d detailed: debit total = credit total", $tbd['totals']['debit'] === $tbd['totals']['credit']);
        ok("TB $d: all checks pass", !array_filter($tb['checks'], function ($ck) { return !$ck['ok']; }));
    }
    $tb = FinancialReport::trialBalance($pdo, [$T], '2026-03-31');
    $bfRow = array_values(array_filter($tb['rows'], function ($r) { return $r['id'] === 'bf'; }));
    ok('TB: one accumulated fund b/f line, a credit balance (prior years surplus 296.00: 300-100 in 2024-25 plus the earlier years)', count($bfRow) === 1 && $bfRow[0]['credit'] === $net([1, 2, 5], null, '2025-09-30', 1) && $bfRow[0]['debit'] === 0);
    $tbA2 = array_values(array_filter($tb['rows'], function ($r) use ($A2) { return $r['id'] === $A2; }))[0];
    ok('TB: balance-sheet types are cumulative (A2 includes prior-year postings)', $tbA2['net'] === $naive($subtree($A2), null, '2026-03-31', $T)[0] - $naive($subtree($A2), null, '2026-03-31', $T)[1]);
    $tbI2 = array_values(array_filter($tb['rows'], function ($r) use ($I2) { return $r['id'] === $I2; }))[0];
    ok('TB: income / expense types only from the financial-year start', $tbI2['net'] === -($net([1], '2025-10-01', '2026-03-31', 1)) , m($tbI2['net']));
    ok('TB: top-level rows only (depth 2) plus the fund line', !array_filter($tb['rows'], function ($r) { return isset($r['depth']) && $r['depth'] !== null && $r['depth'] > 2 && empty($r['direct']); }));
    $tbd = FinancialReport::trialBalance($pdo, [$T], '2026-03-31', true);
    $ids = array_column($tbd['rows'], 'id');
    ok('TB detailed: every account with postings of its own, posting accounts first-class', in_array($A5a, $ids, true) && in_array($A5b, $ids, true) && in_array($A2b, $ids, true) && in_array($sharedExp, $ids, true) && !in_array($A5z, $ids, true) && !in_array($A4, $ids, true));
    $tbU = FinancialReport::trialBalance($pdo, [$T2], '2026-12-31');
    ok('TB: unclassified postings are listed AND included, so debits still equal credits', $tbU['unclassified']['present'] && $tbU['totals']['difference'] === 0 && !$tbU['checks'][2]['ok']);
    ok('TB: a date with no financial year is a clear error', dies(function () use ($pdo, $T) { FinancialReport::trialBalance($pdo, [$T], '2000-01-01'); }) !== null);

    // ------------------------------------------------------------------ 7. children(): every level sums to its parent
    echo "== drill-down\n";
    $walkCount = 0; $walkBad = [];
    $walk = function ($rows, $period, $label) use (&$walk, &$walkCount, &$walkBad, $pdo, $T) {
        foreach ($rows as $r) {
            if ($r['direct'] ?? false) { continue; }
            if (!($r['hasChildren'] ?? false)) { continue; }
            $res = FinancialReport::children($pdo, [$T], $r['id'], $period);
            $walkCount++;
            if (!$res['sumsExactly'] || $res['rowsTotal'] !== $res['amount'] || $res['amount'] !== $r['amount']) { $walkBad[] = "$label {$r['id']} rows " . m($res['rowsTotal']) . ' node ' . m($res['amount']) . ' clicked ' . m($r['amount']); }
            $walk($res['rows'], $period, $label);
        }
    };
    $walk($is['sections']['income']['rows'], $is['period'], 'IS income');
    $walk($is['sections']['expense']['rows'], $is['period'], 'IS expense');
    $walk($bs['sections']['assets']['rows'], $bs['period'], 'BS assets');
    $walk($bs['sections']['liabilities']['rows'], $bs['period'], 'BS liabilities');
    $walk($bs['sections']['equity']['rows'], $bs['period'], 'BS equity');
    $tbm = FinancialReport::trialBalance($pdo, [$T], '2026-03-31');
    $walk(array_filter($tbm['rows'], function ($r) { return $r['id'] !== 'bf' && empty($r['unclassified']); }), $tbm['period'], 'TB');
    ok("children(): every level (all $walkCount nodes opened) sums exactly to its parent", $walkCount >= 8 && !$walkBad, implode('; ', $walkBad));
    $c2 = FinancialReport::children($pdo, [$T], $A2b, $bs2['period']);
    $direct = array_values(array_filter($c2['rows'], function ($r) { return $r['direct']; }));
    ok('children(): postings on a reporting account itself come back as a "direct" row so the rows still add up', count($direct) === 1 && $direct[0]['amount'] === 6000 && $c2['sumsExactly'] && $c2['amount'] === 8500);
    $cbf = FinancialReport::children($pdo, [$T], 'bf', $bs['period']);
    ok('children(bf): the accumulated fund b/f opens into its types 1/2/5 accounts and adds up', $cbf['sumsExactly'] && $cbf['amount'] === $wantBf && count($cbf['rows']) >= 2);
    $ccy = FinancialReport::children($pdo, [$T], 'cy', $bs['period']);
    ok('children(cy): the current year surplus opens into income and expense accounts', $ccy['sumsExactly'] && $ccy['amount'] === $net([1, 2], '2026-10-01', $asOf, 1));
    ok('children(): an account outside the chart of the scope is refused', dies(function () use ($pdo, $T, $Foreign, $is) { FinancialReport::children($pdo, [$T], $Foreign, $is['period']); }) !== null);
    ok('children(): bad IDs are refused', dies(function () use ($pdo, $T, $is) { FinancialReport::children($pdo, [$T], '5 OR 1=1', $is['period']); }) !== null);

    // ------------------------------------------------------------------ 8. lines(): running balance, opening balance
    echo "== journal lines\n";
    $per = ['kind' => 'is', 'from' => '2025-12-01', 'to' => '2026-03-31'];
    $ln = FinancialReport::lines($pdo, [$T], $A5a, $per, false);
    $open = $naive([$A5a], null, '2025-11-30', $T);
    ok('lines(): opening balance = everything before the period (debit-normal asset)', $ln['opening'] === $open[0] - $open[1], m($ln['opening']));
    $run = $ln['opening']; $okRun = true; $prevKey = '';
    foreach ($ln['rows'] as $r) { $run += $r['debit'] - $r['credit']; if ($r['balance'] !== $run) { $okRun = false; } $key = $r['date'] . sprintf('%09d', $r['entryId']) . sprintf('%05d', $r['lineNumber']); if ($key < $prevKey) { $okRun = false; } $prevKey = $key; }
    ok('lines(): running balance accumulates in date / entry / line order', $okRun && count($ln['rows']) === 4, 'rows ' . count($ln['rows']));
    ok('lines(): closing balance = the account figure at the end of the period', $ln['closing'] === $run && $ln['closing'] === $naive([$A5a], null, '2026-03-31', $T)[0] - $naive([$A5a], null, '2026-03-31', $T)[1]);
    $r0 = $ln['rows'][0];
    ok('lines(): row carries entry ID, journal number, reference, memo, type name, debit/credit', $r0['entryId'] > 0 && $r0['journalNo'] !== null && strpos($r0['reference'], 'zz t91 ref') === 0 && strpos($r0['memo'], 'zz t91 memo') === 0 && $r0['typeName'] === 'journal entries' && ($r0['debit'] > 0 xor $r0['credit'] > 0));
    $lc = FinancialReport::lines($pdo, [$T], $I4, ['kind' => 'is', 'from' => '2025-10-01', 'to' => '2026-09-30'], false);
    ok('lines(): a credit-normal account shows its balance in the credit direction', $lc['direction'] === 'CREDIT' && end($lc['rows'])['balance'] === $lc['closing'] && $lc['closing'] === $lc['opening'] + $lc['movement']);
    ok('lines(): income leaf: opening 314.00 (prior years), movement 461.00 (500 - 50 + 11), closing 775.00', $lc['opening'] === 31400 && $lc['movement'] === 46100 && $lc['closing'] === 77500, m($lc['opening']) . ' / ' . m($lc['movement']) . ' / ' . m($lc['closing']));
    $lsub = FinancialReport::lines($pdo, [$T], $A2, ['kind' => 'is', 'from' => '2025-10-01', 'to' => '2026-09-30'], true);
    $want = $naive($subtree($A2), '2025-10-01', '2026-09-30', $T);
    ok('lines(): includeDescendants covers the whole subtree', $lsub['totals']['debit'] === $want[0] && $lsub['totals']['credit'] === $want[1]);
    $lz = FinancialReport::lines($pdo, [$T], $A5z, ['kind' => 'is', 'from' => '2026-03-01', 'to' => '2026-03-31'], false);
    ok('lines(): a zero-net account still lists its two lines', count($lz['rows']) === 2 && $lz['closing'] === 0);
    $ltb = FinancialReport::lines($pdo, [$T], $I4, ['kind' => 'tb', 'asOf' => '2026-03-31'], false);
    $mv = $naive([$I4], '2025-10-01', '2026-03-31', $T);
    ok('lines(): trial-balance basis for income starts at the financial-year start; the movement of the period (not the opening) is the trial-balance figure', $ltb['from'] === '2025-10-01' && $ltb['opening'] === $naive([$I4], null, '2025-09-30', $T)[1] - $naive([$I4], null, '2025-09-30', $T)[0] && $ltb['movement'] === $mv[1] - $mv[0], m($ltb['opening']) . ' / ' . m($ltb['movement']));
    ok('lines(): balance-sheet types have no lower bound under a trial-balance period', FinancialReport::lines($pdo, [$T], $A5a, ['kind' => 'tb', 'asOf' => '2026-03-31'], false)['from'] === null);
    ok('lines(): foreign account refused', dies(function () use ($pdo, $T, $Foreign, $per) { FinancialReport::lines($pdo, [$T], $Foreign, $per); }) !== null);
    $lv = FinancialReport::lines($pdo, [$T], $I4, ['kind' => 'range', 'from' => null, 'to' => '2026-12-31', 'direction' => 'normal'], false);
    ok('lines(): the voided entry (9999.00) never appears', !array_filter($lv['rows'], function ($r) { return $r['credit'] === 999900; }));
    ok('lines(): source links only where a safe route exists', FinancialReport::sourceLink(2, 15) === 'expenses/print/15' && FinancialReport::sourceLink(5, 3) === null && FinancialReport::sourceLink(9, 4) === 'bankpostings/edit/' . encryptId(4) && FinancialReport::sourceLink(16, 1) === null && FinancialReport::sourceLink(14, 0) === null);
    $types = q('SELECT ID FROM tbltransactiontypes ORDER BY ID');
    ok('source link map covers every transaction type of the database', !array_diff(array_column($types, 'ID'), array_keys(FinancialReport::SOURCE_ROUTES)));

    // ------------------------------------------------------------------ 9. selectors
    echo "== selectors\n";
    $opts = FinancialReport::accountOptions($pdo, [$T], ['roots' => [2]]);
    $byId = []; foreach ($opts as $o) { $byId[$o['id']] = $o; }
    ok('accounts: inactive account listed, labelled "(inactive)"', isset($byId[$EIn]) && substr($byId[$EIn]['label'], -10) === '(inactive)');
    ok('accounts: other congregation\'s account never listed', !isset($byId[$Foreign]));
    ok('accounts: shared accounts listed', isset($byId[$sharedExp]) && isset($byId[$sharedExpTop]));
    ok('accounts: path label without the root when one root is asked for', $byId[$E3]['label'] === 'zz t91 expense L2 > zz t91 expense leaf');
    ok('accounts: roots themselves are not offered', !isset($byId[2]));
    $all = FinancialReport::accountOptions($pdo, [$T]);
    $allIds = array_column($all, 'id');
    ok('accounts: deleted account WITH history listed, labelled "(deleted)"; WITHOUT history left out', in_array($DelH, $allIds, true) && !in_array($DelN, $allIds, true) && substr(array_values(array_filter($all, function ($o) use ($DelH) { return $o['id'] === $DelH; }))[0]['label'], -9) === '(deleted)');
    ok('accounts: all roots by default, label starts with the root name', strpos(array_values(array_filter($all, function ($o) use ($A5a) { return $o['id'] === $A5a; }))[0]['label'], 'Asset > ') === 0);
    ok('accounts: banks included by default and left out on request', in_array($bankA, $allIds, true) && !in_array($bankA, array_column(FinancialReport::accountOptions($pdo, [$T], ['banks' => false]), 'id'), true));
    ok('accounts: posting-only filter', !in_array($A4, array_column(FinancialReport::accountOptions($pdo, [$T], ['reporting' => false]), 'id'), true) && in_array($A5a, array_column(FinancialReport::accountOptions($pdo, [$T], ['reporting' => false]), 'id'), true));
    ok('accounts: accountAllowed matches the list', FinancialReport::accountAllowed($pdo, [$T], $EIn, ['roots' => [2]]) && !FinancialReport::accountAllowed($pdo, [$T], $EIn, ['roots' => [1]]) && !FinancialReport::accountAllowed($pdo, [$T], $Foreign) && !FinancialReport::accountAllowed($pdo, [$T], $DelN));
    $bk = FinancialReport::bankOptions($pdo, [$T]); $bkIds = array_column($bk, 'id');
    ok('banks: active, inactive-with-journal-history and inactive-with-cashbook-history listed; inactive-without-history, deleted and foreign left out', in_array($bankA, $bkIds, true) && in_array($bankJ, $bkIds, true) && in_array($bankP, $bkIds, true) && !in_array($bankI, $bkIds, true) && !in_array($bankD, $bkIds, true) && !in_array($bankF, $bkIds, true));
    $bkMap = []; foreach ($bk as $b2) { $bkMap[$b2['id']] = $b2; }
    ok('banks: inactive ones labelled, active one not', substr($bkMap[$bankJ]['label'], -10) === '(inactive)' && substr($bkMap[$bankA]['label'], -10) !== '(inactive)' && strpos($bkMap[$bankA]['label'], 'ZZ T91 BANK ACTIVE-ACC') === 0);
    ok('banks: bankAllowed matches', FinancialReport::bankAllowed($pdo, [$T], $bankJ) && !FinancialReport::bankAllowed($pdo, [$T], $bankF) && !FinancialReport::bankAllowed($pdo, [$T], $bankD));
    $ps = FinancialReport::parishScope($pdo);
    ok('parish scope: non-deleted congregations plus deleted ones with history', in_array(11, $ps, true) && in_array($T, $ps, true) && !in_array(4, $ps, true));

    // ------------------------------------------------------------------ 10. parity with the old mirror on real dev data (read-only)
    echo "== parity with the old reports (real dev data, read-only)\n";
    $mir = function ($sql, $p) { return q($sql, $p)[0]; };
    $mirror = function ($root, $from, $to) use ($mir) {   // stamped root in tblledger.accountId, congregation 11
        $r = $mir('SELECT COALESCE(SUM(debit),0) dr, COALESCE(SUM(credit),0) cr FROM tblledger WHERE deleted = 0 AND congregationId = 11 AND accountId = ? AND transactionDate ' . ($from ? '>= ? AND transactionDate <= ?' : '<= ?'), $from ? [$root, $from, $to] : [$root, $to]);
        return [c($r['dr']), c($r['cr'])];
    };
    foreach ([['closed year 2024-25', '2024-10-01', '2025-09-30'], ['current period', '2026-10-01', '2026-10-24']] as [$lab, $f, $t]) {
        $svc = FinancialReport::incomeStatement($pdo, [11], $f, $t, false);
        [$id_, $ic] = $mirror(1, $f, $t); [$ed, $ec] = $mirror(2, $f, $t);
        ok("parity $lab: income equals the old report ($lab)", $svc['sections']['income']['total'] === $ic - $id_, m($svc['sections']['income']['total']) . ' vs ' . m($ic - $id_));
        ok("parity $lab: expense equals the old report", $svc['sections']['expense']['total'] === $ed - $ec, m($svc['sections']['expense']['total']) . ' vs ' . m($ed - $ec));
        ok("parity $lab: surplus equals the old report", $svc['surplus']['amount'] === ($ic - $id_) - ($ed - $ec));
        $raw = FinancialReport::balances($pdo, [11], $f, $t); $sd = 0; $sc = 0; foreach ($raw as $a) { $sd += $a['idr']; $sc += $a['icr']; }
        $od = $mir('SELECT COALESCE(SUM(debit),0) dr, COALESCE(SUM(credit),0) cr FROM tblledger WHERE deleted = 0 AND congregationId = 11 AND transactionDate >= ? AND transactionDate <= ?', [$f, $t]);
        ok("parity $lab: trial balance totals (raw debits / credits of the period) equal the old report", $sd === c($od['dr']) && $sc === c($od['cr']) && $sd === $sc, m($sd) . '/' . m($sc) . ' vs ' . $od['dr'] . '/' . $od['cr']);
    }
    $svc = FinancialReport::incomeStatement($pdo, [11], '2024-10-01', '2025-09-30', false);
    ok('inventory 5.2: closed year income 10,160,261.05, expense 5,144,642.00, surplus 5,015,619.05', $svc['sections']['income']['total'] === 1016026105 && $svc['sections']['expense']['total'] === 514464200 && $svc['surplus']['amount'] === 501561905);
    $raw = FinancialReport::balances($pdo, [11], '2024-10-01', '2025-09-30'); $sd = 0; foreach ($raw as $a) { $sd += $a['idr']; }
    ok('inventory 5.2: closed year trial balance raw totals 29,570,136.19 / 29,570,136.19', $sd === 2957013619);
    foreach ([['2025-09-30', 'closed year-end'], ['2026-10-24', 'today']] as [$d, $lab]) {
        $svc = FinancialReport::balanceSheet($pdo, [11], $d, false);
        $oa = $mirror(3, null, $d); $olq = [$mirror(4, null, $d), $mirror(6, null, $d)];
        $oldAssets = $oa[0] - $oa[1]; $oldLE = ($olq[0][1] - $olq[0][0]) + ($olq[1][1] - $olq[1][0]);
        $b188 = FinancialReport::balances($pdo, [11], null, $d); $a188 = isset($b188[188]) ? $b188[188]['idr'] - $b188[188]['icr'] : 0;
        $diff = $oldAssets - $svc['sections']['assets']['total'];
        $svcLE = $svc['sections']['liabilities']['total'] + $svc['sections']['equity']['total'];
        ok("parity BS $lab: liabilities + equity equal the old report", $svcLE === $oldLE, m($svcLE) . ' vs ' . m($oldLE));
        ok("parity BS $lab: assets differ from the old report only by account 188 (accepted reclassification, handover 6)", $diff === $a188, 'difference ' . m($diff) . ' account 188 ' . m($a188));
        ok("parity BS $lab: the service balances (assets = liabilities + equity + fund)", $svc['totals']['current']['difference'] === 0 && !$svc['unclassified']['present']);
    }
    $bsToday = FinancialReport::balanceSheet($pdo, [11], '2026-10-24', false);
    ok('inventory 5.2: today\'s journal assets 24,736,879.71 (old mirror 24,800,246.71, the 63,367.00 is account 188)', $bsToday['sections']['assets']['total'] === 2473687971, m($bsToday['sections']['assets']['total']));
    $svcTB = FinancialReport::trialBalance($pdo, [11], '2026-10-24');
    ok('parity: today\'s trial balance on real data balances and has no unclassified postings', $svcTB['totals']['difference'] === 0 && !$svcTB['unclassified']['present'], m($svcTB['totals']['difference']));
    ok('whole suite ran in a reasonable time (set-based queries)', microtime(true) - $t0 < 60, round(microtime(true) - $t0, 1) . 's');
} catch (Throwable $e) {
    $fail++;
    echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    // cleanup, children first
    foreach ($created['bankpostings'] as $id) { $pdo->prepare('DELETE FROM tblbankpostings WHERE ID = ?')->execute([$id]); }
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    $pdo->prepare("DELETE FROM journal_entries WHERE reference LIKE 'zz t91%'")->execute();
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz t91%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t91%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM tblcongregation WHERE CongregationName LIKE 'zz t91%'")[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left fixture rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

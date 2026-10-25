<?php
/**
 * Task 9.4: balance sheet (BalanceSheetReport on FinancialReport): layout, fund lines, comparative, imbalance shown, drill down to
 * the lines, cross-report checks (income statement, trial balance, cash accounts), parity with the old balance sheet on the real
 * dev data. Self-cleaning fixtures. CLI only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';

$pdo = (new Database)->pdo();
$pass = 0; $fail = 0;
function ok($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
function C($s) { return FinancialReport::cents($s); }
function tcents($s) { if ($s === '' || $s === null) { return 0; } $neg = $s[0] === '('; $v = C(str_replace([',', '(', ')'], '', $s)); return $neg ? -$v : $v; }

$created = ['accounts' => [], 'entries' => [], 'congs' => []];
try {
    $T  = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t94 main', 0, 0)"); $created['congs'][] = $T;
    $T2 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t94 second', 0, 0)"); $created['congs'][] = $T2;
    $T3 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t94 closed', 0, 1)"); $created['congs'][] = $T3;
    $parentOf = []; $rootOf = [];
    $acct = function ($name, $parent, $cong) use (&$created, &$parentOf, &$rootOf) {
        $root = $parent <= 6 ? $parent : $rootOf[$parent];
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, forGroup, active, congregationId, deleted) VALUES (?,?,?,1,0,0,1,?,0)', [$name, $root, $parent, $cong]);
        $created['accounts'][] = $id; $parentOf[$id] = $parent; $rootOf[$id] = $root; return $id;
    };
    $A2 = $acct('zz t94 asset top', 3, $T); $A3 = $acct('zz t94 asset mid', $A2, $T); $A4 = $acct('zz t94 asset low', $A3, $T); $A5 = $acct('zz t94 asset leaf', $A4, $T);
    $B2 = $acct('zz t94 asset direct', 3, $T); $B3 = $acct('zz t94 asset under direct', $B2, $T);
    $L2 = $acct('zz t94 liability top', 4, $T); $L3 = $acct('zz t94 liability leaf', $L2, $T);
    $Q2 = $acct('zz t94 equity top', 6, $T); $Q3 = $acct('zz t94 equity leaf', $Q2, $T);
    $I2 = $acct('zz t94 income top', 1, $T); $I3 = $acct('zz t94 income mid', $I2, $T); $I4 = $acct('zz t94 income leaf', $I3, $T);
    $E2 = $acct('zz t94 expense top', 2, $T); $E3 = $acct('zz t94 expense leaf', $E2, $T);
    $D2 = $acct('zz t94 dividend top', 5, $T); $D3 = $acct('zz t94 dividend leaf', $D2, $T);
    $X = $acct('zz t94 loop x', 3, $T2); $Y = $acct('zz t94 loop y', $X, $T2); $pdo->prepare('UPDATE tblaccounttypes SET parentId = ? WHERE ID = ?')->execute([$Y, $X]);
    $J2 = $acct('zz t94 asset b', 3, $T2); $J3 = $acct('zz t94 asset b leaf', $J2, $T2); $JQ = $acct('zz t94 equity b', 6, $T2); $JQ3 = $acct('zz t94 equity b leaf', $JQ, $T2);
    $K2 = $acct('zz t94 asset closed', 3, $T3); $K3 = $acct('zz t94 asset closed leaf', $K2, $T3); $KI = $acct('zz t94 income closed', 1, $T3); $KI3 = $acct('zz t94 income closed leaf', $KI, $T3);

    $n = 0;
    $entry = function ($date, array $lines, $cong = null, $deleted = 0) use (&$created, &$n, $T) {
        $cong = $cong ?? $T; $n++; $txn = 940000 + $n;
        $id = x('INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,5,?,?,?,?,?)', [$cong, $date, $txn, $txn, 'zz t94 ref ' . $n, 'zz t94 desc ' . $n, $deleted]);
        $created['entries'][] = $id; $ln = 0;
        foreach ($lines as [$a, $dc, $amt]) { x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $a, $dc, $amt, 'zz t94 memo ' . $n, ++$ln]); }
        return $id;
    };
    $DR = 'DEBIT'; $CR = 'CREDIT';
    $entry('2024-12-01', [[$A5, $DR, '5000.00'], [$Q3, $CR, '5000.00']]);
    $entry('2025-05-20', [[$A5, $DR, '300.00'], [$I4, $CR, '300.00']]);
    $entry('2025-05-21', [[$E3, $DR, '100.00'], [$A5, $CR, '100.00']]);
    $entry('2025-09-30', [[$A5, $DR, '60.00'], [$I4, $CR, '60.00']]);
    $entry('2025-10-01', [[$A5, $DR, '1000.00'], [$I4, $CR, '1000.00']]);
    $entry('2025-11-10', [[$A5, $DR, '500.00'], [$L3, $CR, '500.00']]);
    $entry('2026-01-15', [[$E3, $DR, '200.00'], [$A5, $CR, '200.00']]);
    $entry('2026-02-10', [[$I4, $DR, '50.00'], [$A5, $CR, '50.00']]);                     // contra on income
    $entry('2026-03-01', [[$D3, $DR, '40.00'], [$A5, $CR, '40.00']]);                     // dividend
    $entry('2026-03-02', [[$B2, $DR, '60.00'], [$Q3, $CR, '60.00']]);                     // postings on a reporting asset account
    $entry('2026-03-03', [[$B3, $DR, '25.00'], [$Q3, $CR, '25.00']]);
    $entry('2026-02-12', [[$A5, $DR, '9999.00'], [$I4, $CR, '9999.00']], null, 1);       // voided
    $entry('2026-10-01', [[$A5, $DR, '13.00'], [$I4, $CR, '13.00']]);
    $entry('2026-10-10', [[$E3, $DR, '20.00'], [$L3, $CR, '20.00']]);
    $entry('2026-02-14', [[$J3, $DR, '700.00'], [$JQ3, $CR, '700.00']], $T2);
    $entry('2026-02-15', [[$J3, $DR, '8.00'], [$X, $CR, '8.00']], $T2);                   // unclassified credit: the equation fails visibly
    $entry('2026-02-16', [[$K3, $DR, '40.00'], [$KI3, $CR, '40.00']], $T3);              // the closed congregation's history

    echo "== layout and totals\n";
    $asOf = '2026-03-31';
    $r = BalanceSheetReport::report($pdo, [$T], '', $asOf);
    $names = function ($rows) { return array_column($rows, 'name'); };
    ok('assets: the top-level asset accounts only (names)', $names($r['assets']['rows']) === ['Zz T94 Asset Direct', 'Zz T94 Asset Top']);
    // numbers: A5 = 5000+300-100+60+1000+500-200-50-40 = 6470 ; B = 85 ; assets 6555
    ok('total assets 6,555.00 (cumulative, contra netted, postings on a reporting account included)', $r['assets']['total'] === '6,555.00', $r['assets']['total']);
    ok('liabilities 500.00', $r['liabilities']['total'] === '500.00');
    $eqNames = $names($r['equity']['rows']);
    $fundNames = $names($r['fund']['rows']);
    ok('equity: the other type 6 account only; the accumulated fund account (214) is inside the block (decision 150)', $eqNames === ['Zz T94 Equity Top'], json_encode($eqNames));
    ok('accumulated fund block: b/f, surplus to date, dividends (no movement line when zero)', $fundNames === ['Accumulated fund b/f', 'Surplus / (deficit) for the year to date', 'Dividends'], json_encode($fundNames));
    $row = []; foreach (array_merge($r['equity']['rows'], $r['fund']['rows']) as $x) { $row[$x['name']] = $x; }
    ok('type 6 account 5,085.00 (5,000 + 60 + 25)', $row['Zz T94 Equity Top']['amount'] === '5,085.00');
    ok('accumulated fund b/f = types 1, 2, 5 before the year start (income 360 - expense 100 = 260.00)', $row['Accumulated fund b/f']['amount'] === '260.00', $row['Accumulated fund b/f']['amount']);
    ok('surplus to date = income 950 (1000 - 50) - expense 200 = 750.00', $row['Surplus / (deficit) for the year to date']['amount'] === '750.00', $row['Surplus / (deficit) for the year to date']['amount']);
    ok('dividends (type 5, to date) shown because non-zero: a deduction (40.00)', $row['Dividends']['amount'] === '(40.00)');
    ok('accumulated fund c/f = b/f + surplus + dividends = 260 + 750 - 40 = 970.00 (the block total)', $r['fund']['total'] === '970.00', $r['fund']['total']);
    ok('total equity and accumulated fund 6,055.00; total liabilities, equity and fund 6,555.00', $r['equityTotal']['total'] === '6,055.00' && $r['liabilitiesEquityFund']['total'] === '6,555.00');
    ok('assets = liabilities + equity + fund: balanced, no difference line', $r['balanced'] === true && $r['difference']['amount'] === '0.00');
    $r0 = BalanceSheetReport::report($pdo, [$T], '', '2026-02-28');
    $names0 = array_column($r0['fund']['rows'], 'name');
    ok('dividends only when non-zero: absent before the dividend date (no comparative either)', !in_array('Dividends', $names0, true));
    $rz = BalanceSheetReport::report($pdo, [$T], '', '2025-09-30');
    ok('first year: contra and zero: no dividends line, b/f 0 hidden when zero', !in_array('Dividends', array_column($rz['fund']['rows'], 'name'), true));
    ok('comparative column = the balance sheet at the last year-end (2025-09-30)', $r['cmpAsOf'] === '2025-09-30' && $r['assets']['cmpTotal'] === $rz['assets']['total'] && $r['liabilities']['cmpTotal'] === $rz['liabilities']['total'] && $r['equityTotal']['cmpTotal'] === $rz['equityTotal']['total'] && $r['fund']['cmpTotal'] === $rz['fund']['total']);
    $rzr = []; foreach ($rz['fund']['rows'] as $x) { $rzr[$x['name']] = $x['amount']; }
    ok('comparative of the fund lines = the same lines at the last year-end', $row['Accumulated fund b/f']['cmp'] === ($rzr['Accumulated fund b/f'] ?? '0.00') && $row['Surplus / (deficit) for the year to date']['cmp'] === $rzr['Surplus / (deficit) for the year to date']);

    echo "== imbalance and unclassified are shown, never forced\n";
    $u = BalanceSheetReport::report($pdo, [$T2], '', $asOf);
    ok('unclassified postings: diagnostic shown, balanced = false, difference line with the amount', $u['unclassified']['present'] && $u['balanced'] === false && $u['difference']['amountRaw'] !== 0);
    ok('the imbalance equals the unclassified amount (8.00 credited to an unreachable account)', abs($u['difference']['amountRaw']) === 800, json_encode($u['difference']));
    ok('the Excel sheet carries the difference row', (function () use ($u) { foreach (BalanceSheetReport::reportSheet($u) as $row) { foreach ($row as $c) { if (is_array($c) && strpos((string) $c['v'], 'Difference') === 0) { return true; } } } return false; })());

    echo "== drill: every level adds up, down to the journal lines\n";
    $bad = []; $levels = 0; $lines = 0;
    $walk = function ($account, $amount, $label, $fund) use (&$walk, &$bad, &$levels, &$lines, $pdo, $T, $asOf) {
        $d = BalanceSheetReport::drill($pdo, [$T], $account, $asOf, 'children', false, $fund);
        $levels++;
        if ($d['mode'] === 'children') {
            if (!$d['sumsExactly'] || $d['rowsTotal'] !== $d['amount'] || ($amount !== null && $d['amount'] !== $amount)) { $bad[] = "$label level rows {$d['rowsTotal']} node {$d['amount']} clicked $amount"; }
            foreach ($d['rows'] as $row) {
                if ($row['direct']) {
                    $l = BalanceSheetReport::drill($pdo, [$T], $row['id'], $asOf, 'lines', true, $fund); $levels++; $lines++;
                    if ($l['closing'] !== $row['amount']) { $bad[] = "$label direct closing {$l['closing']} vs {$row['amount']}"; }
                } else { $walk($row['id'], $row['amount'], $label . '>' . $row['name'], $fund); }
            }
        } else {
            $lines++;
            if ($amount !== null && $d['closing'] !== $amount) { $bad[] = "$label lines closing {$d['closing']} vs $amount"; }
        }
    };
    foreach (array_merge($r['assets']['rows'], $r['liabilities']['rows']) as $row) { $walk($row['id'], $row['amount'], $row['name'], ''); }
    foreach ($r['equity']['rows'] as $row) { $walk($row['id'], $row['amount'], $row['name'], ''); }
    foreach ($r['fund']['rows'] as $row) {
        if ($row['fund'] && $row['next'] === 'children') { $walk($row['id'], $row['amount'], $row['name'], $row['id']); }
    }
    ok("drill: all $levels levels (assets, liabilities, equity, b/f, dividends) add up and the $lines line lists close at the figure clicked", $levels >= 10 && $lines >= 5 && !$bad, implode('; ', $bad));
    ok('the surplus line has no drill of its own: it points at the income statement of the year to date', $r['fund']['rows'][1]['id'] === 'cy' && $r['fund']['rows'][1]['next'] === 'incomestatement');
    $dbf = BalanceSheetReport::drill($pdo, [$T], 'bf', $asOf, 'children', false, '');
    ok('b/f opens into the prior years\' surplus (the fund account holds nothing in this congregation) and adds up to 260.00', $dbf['amount'] === '260.00' && $dbf['sumsExactly'] && count($dbf['rows']) === 1 && $dbf['rows'][0]['id'] === 'pys');
    $dpys = BalanceSheetReport::drill($pdo, [$T], 'pys', $asOf, 'children', false, '');
    ok('prior years\' surplus opens into the income / expense accounts of the earlier years (credit direction) and adds up to 260.00', $dpys['amount'] === '260.00' && $dpys['sumsExactly'] && count($dpys['rows']) >= 2 && $dpys['fund'] === 'bf');
    $dl = BalanceSheetReport::drill($pdo, [$T], $I4, $asOf, 'lines', false, 'bf');
    ok('an income account under b/f lists only the earlier years, cumulative, no restart', $dl['mode'] === 'lines' && $dl['matching'] === 2 && $dl['closing'] === '360.00' && $dl['rows'][1]['balance'] === '360.00');
    $dd = BalanceSheetReport::drill($pdo, [$T], 'div', $asOf, 'children', false, '');
    ok('dividends open into the type-5 accounts and add up', $dd['sumsExactly'] && $dd['amount'] === '(40.00)' && count($dd['rows']) === 1);
    $dl2 = BalanceSheetReport::drill($pdo, [$T], $A5, $asOf, 'lines', false, '');
    ok('a balance-sheet account lists lines from inception with the cumulative running balance', $dl2['opening'] === '0.00' && $dl2['rows'][0]['date'] === '01-12-2024' && $dl2['closing'] === '6,470.00');
    ok('crumbs run from the top-level account; roots left out', array_column(BalanceSheetReport::drill($pdo, [$T], $A4, $asOf, 'children', false, '')['crumbs'], 'name') === ['Zz T94 Asset Top', 'Zz T94 Asset Mid', 'Zz T94 Asset Low']);

    echo "== parish scope: raw sum, deleted congregation with history\n";
    $pa = BalanceSheetReport::report($pdo, [$T, $T2, $T3], 'All congregations', $asOf);
    $p1 = BalanceSheetReport::report($pdo, [$T], '', $asOf);
    $pk = BalanceSheetReport::report($pdo, [$T3], '', $asOf);
    ok('assets of a selection = raw sum of the congregations (the closed one included)', $pa['assets']['totalRaw'] === $p1['assets']['totalRaw'] + BalanceSheetReport::report($pdo, [$T2], '', $asOf)['assets']['totalRaw'] + $pk['assets']['totalRaw'] && $pk['assets']['totalRaw'] === 4000);
    ok('the deleted congregation is in the parish scope because it has history', in_array($T3, FinancialReport::parishScope($pdo), true));

    echo "== cross-report checks (fixtures and real data)\n";
    $cross = function ($cong, $dates) use ($pdo) {
        $bad = []; $n = 0;
        foreach ($dates as $d) {
            $fy = FinancialReport::financialYear($pdo, $d);
            $bs = BalanceSheetReport::report($pdo, [$cong], '', $d);
            $n++;
            if (!$bs['balanced']) { $bad[] = "$d does not balance " . $bs['difference']['amount']; }
            // surplus to date = income statement surplus from the year start
            $is = FinancialReport::incomeStatement($pdo, [$cong], $fy['start'], $d, false);
            $fund = FinancialReport::balanceSheet($pdo, [$cong], $d, false)['sections']['fund'];
            if ($fund['surplus']['cur'] !== $is['surplus']['amount']) { $bad[] = "$d surplus {$fund['surplus']['cur']} vs IS {$is['surplus']['amount']}"; }
            // b/f = TB b/f line = all earlier years' surpluses
            $tb = FinancialReport::trialBalance($pdo, [$cong], $d);
            $bf = 0; foreach ($tb['rows'] as $row) { if ($row['id'] === 'bf') { $bf = -$row['net']; } }
            if ($fund['prior']['cur'] !== $bf) { $bad[] = "$d prior years' surplus {$fund['prior']['cur']} vs TB $bf"; }
            // decision 150: the block adds up, and b/f = fund account at the year start + the prior years' surplus
            if ($fund['bf']['cur'] !== $fund['acctBf']['cur'] + $fund['prior']['cur']) { $bad[] = "$d b/f is not the account + prior years"; }
            if ($fund['bf']['cur'] + $fund['surplus']['cur'] + $fund['dividends']['cur'] + $fund['acctMove']['cur'] !== $fund['total']) { $bad[] = "$d b/f + surplus + dividends + movement != c/f"; }
            if ($bs['fund']['totalRaw'] !== $fund['total']) { $bad[] = "$d screen c/f != block total"; }
            // c/f at the year-end before = this year's b/f
            $pe = date('Y-m-d', strtotime($fy['start'] . ' -1 day'));
            if (FinancialReport::financialYear($pdo, $pe) !== null) {
                $pf = FinancialReport::balanceSheet($pdo, [$cong], $pe, false)['sections']['fund'];
                if ($pf['total'] !== $fund['bf']['cur']) { $bad[] = "$d c/f at $pe {$pf['total']} != b/f {$fund['bf']['cur']}"; }
            }
            $sum = 0;
            foreach (q('SELECT startDate, endDate FROM tblfiscalyears WHERE endDate < ? ORDER BY startDate', [$fy['start']]) as $y) {
                $yi = FinancialReport::incomeStatement($pdo, [$cong], $y['startDate'], $y['endDate'], false);
                $sum += $yi['surplus']['amount'] - ($yi['dividends'] === null ? 0 : $yi['dividends']['total']);
            }
            if ($fund['prior']['cur'] !== $sum) { $bad[] = "$d prior years' surplus {$fund['prior']['cur']} vs the sum of the earlier years' result $sum"; }
            // each top-level figure = the trial balance's figure
            $tbNet = []; foreach ($tb['rows'] as $row) { if (is_int($row['id'])) { $tbNet[$row['id']] = $row['net']; } }
            foreach (['assets' => 1, 'liabilities' => -1] as $sec => $sign) {
                foreach ($bs[$sec]['rows'] as $row) { if (!$row['direct'] && $row['amountRaw'] !== $sign * ($tbNet[$row['id']] ?? 0)) { $bad[] = "$d $sec {$row['name']} {$row['amountRaw']} vs TB " . ($tbNet[$row['id']] ?? 0); } }
            }
            foreach ($bs['equity']['rows'] as $row) { if (!$row['fund'] && !$row['direct'] && $row['amountRaw'] !== -($tbNet[$row['id']] ?? 0)) { $bad[] = "$d equity {$row['name']} vs TB"; } }
            // comparative = the balance sheet at the last year-end
            $prior = BalanceSheetReport::report($pdo, [$cong], '', date('Y-m-d', strtotime($fy['start'] . ' -1 day')));
            if ($bs['assets']['cmpTotalRaw'] !== $prior['assets']['totalRaw'] || $bs['equityTotal']['cmpTotalRaw'] !== $prior['equityTotal']['totalRaw'] || $bs['fund']['cmpTotalRaw'] !== $prior['fund']['totalRaw'] || $bs['liabilitiesEquityFund']['cmpTotalRaw'] !== $prior['liabilitiesEquityFund']['totalRaw']) { $bad[] = "$d comparative differs from the balance sheet at the last year-end"; }
        }
        return [$n, $bad];
    };
    [$n1, $b1] = $cross($T, ['2025-09-30', '2026-03-31', '2026-09-30', '2026-10-01', '2026-10-24']);
    ok("fixtures: $n1 dates: balanced; surplus = income statement; b/f = trial balance b/f = earlier years' surpluses; figures = trial balance; comparative = balance sheet at the last year-end", !$b1, implode('; ', $b1));
    [$n2, $b2] = $cross(11, ['2025-09-30', '2026-03-31', '2026-10-01', '2026-10-24']);
    ok("real data (congregation 11), $n2 dates: the same checks", !$b2, implode('; ', $b2));
    $chart = FinancialReport::chart($pdo, [11]);
    $cashTop = 22;
    foreach ([ '2025-09-30', '2026-10-24'] as $d) {
        $bs = BalanceSheetReport::report($pdo, [11], '', $d);
        $line = null; foreach ($bs['assets']['rows'] as $row) { if ($row['id'] === $cashTop) { $line = $row; } }
        $bal = FinancialReport::balances($pdo, [11], null, $d);
        $sum = 0;
        foreach ($chart['nodes'] as $nd) { if (in_array($cashTop, $nd['chain'], true) && isset($bal[$nd['id']])) { $sum += $bal[$nd['id']]['idr'] - $bal[$nd['id']]['icr']; } }
        $banks = 0; foreach ($chart['nodes'] as $nd) { if ($nd['isBank'] && isset($bal[$nd['id']]) && in_array($cashTop, $nd['chain'], true)) { $banks += $bal[$nd['id']]['idr'] - $bal[$nd['id']]['icr']; } }
        ok("$d: cash and cash equivalents (" . $line['amount'] . ') = the sum of the journal balances of the bank and cash accounts under it', $line !== null && $line['amountRaw'] === $sum, FinancialReport::money($sum));
        echo "INFO $d: of which banks " . FinancialReport::money($banks) . "\n";
    }

    echo "== accumulated fund block (decision 150), real chart\n";
    $realChart = FinancialReport::chart($pdo, [11]);
    $faId = FinancialReport::fundAccountId($realChart);
    ok('the accumulated fund account is found through account 20 (its top-level type 6 parent), not by name', $faId !== null && $realChart['nodes'][20]['chain'][count($realChart['nodes'][20]['chain']) - 2] === $faId, (string) $faId);
    foreach (['2025-09-30', '2026-10-24'] as $d) {
        $parts = FinancialReport::accumulatedFundParts($pdo, [11], $d);
        $bsr = BalanceSheetReport::report($pdo, [11], '', $d);
        ok("$d block: b/f = account at the year start + prior years' surplus; c/f = b/f + surplus + dividends + movement", $parts['bf'] === $parts['acctBf'] + $parts['prior'] && $parts['cf'] === $parts['bf'] + $parts['surplus'] + $parts['dividends'] + $parts['acctMove'] && $bsr['fund']['totalRaw'] === $parts['cf']);
        ok("$d the fund account is no longer a separate type 6 line", !in_array($faId, array_column($bsr['equity']['rows'], 'id'), true) && $bsr['balanced'] === true && $bsr['fund']['diagnostic'] === null);
        $dbf = BalanceSheetReport::drill($pdo, [11], 'bf', $d, 'children', false, '');
        $ids = array_column($dbf['rows'], 'id');
        ok("$d b/f opens two rows (the fund account at the year start, prior years' surplus) that add up to b/f", $dbf['sumsExactly'] && $dbf['rowsTotalRaw'] === $parts['bf'] && $ids === [(string) $faId, 'pys'], json_encode($ids));
        $dfa = BalanceSheetReport::drill($pdo, [11], $faId, $d, 'children', false, 'fa');
        ok("$d the fund account at the year start opens its accounts and adds up", $dfa['mode'] === 'children' && $dfa['sumsExactly'] && $dfa['amount'] === IncomeStatementReport::bracket($parts['acctBf']), $dfa['amount']);
        $dp = BalanceSheetReport::drill($pdo, [11], 'pys', $d, 'children', false, '');
        ok("$d prior years' surplus opens the earlier years' accounts and adds up", $dp['sumsExactly'] && $dp['rowsTotalRaw'] === $parts['prior']);
        $dm = BalanceSheetReport::drill($pdo, [11], $faId, $d, 'children', false, 'fm');
        ok("$d the in-year movement view of the fund account answers (movement " . FinancialReport::money($parts['acctMove']) . ')', $dm['mode'] === 'children' || $dm['mode'] === 'lines');
    }
    // the account cannot be identified: separate presentation and a diagnostic, never a guess
    $fake = $realChart; unset($fake['nodes'][20]);
    ok('without account 20 the fund account is not identified', FinancialReport::fundAccountId($fake) === null);
    $fake2 = $realChart; $fake2['nodes'][20]['classified'] = false;
    ok('an unclassified account 20 does not identify it either', FinancialReport::fundAccountId($fake2) === null);
    $fake3 = $realChart; $fake3['nodes'][20]['root'] = 3;
    ok('account 20 outside the type 6 tree does not identify it', FinancialReport::fundAccountId($fake3) === null);
    $np = FinancialReport::accumulatedFundParts($pdo, [11], '2026-10-24', $fake);
    ok('not identified: acctBf and acctMove are 0, b/f = the prior years\' surplus only', $np['fundAccountId'] === null && $np['acctBf'] === 0 && $np['acctMove'] === 0 && $np['bf'] === $np['prior']);
    echo "== parity with the old balance sheet on real dev data (read-only)\n";
    foreach (['2025-09-30', '2026-10-24'] as $d) {
        $chart = FinancialReport::chart($pdo, [11]);
        $top = function ($name) use ($chart) {   // old text name -> top-level account id
            $rows = q('SELECT ID FROM tblaccounttypes WHERE LOWER(TRIM(accountType)) = ? AND deleted = 0 AND congregationId IN (0, 11) ORDER BY congregationId DESC, ID', [strtolower(trim($name))]);
            foreach ($rows as $rw) { $nd = $chart['nodes'][(int) $rw['ID']] ?? null; if ($nd && $nd['classified']) { return $nd['depth'] === 1 ? null : (int) $nd['chain'][count($nd['chain']) - 2]; } }
            return null;
        };
        $oldA = []; foreach (q('CALL sp_BalanceSheet_Assets(11, ?)', [$d]) as $o) { $t = $top($o['account']); $oldA[$t ?? $o['account']] = ($oldA[$t ?? $o['account']] ?? 0) + C($o['amount']); }
        $pdo->query('SELECT 1');
        $oldLE = []; $oldRE = 0; foreach (q('CALL sp_BalanceSheet_LiabilitiesEquity(11, ?)', [$d]) as $o) { if ($o['account'] === 'Retained Earnings') { $oldRE = C($o['amount']); continue; } $t = $top($o['account']); $oldLE[$t ?? $o['account']] = ($oldLE[$t ?? $o['account']] ?? 0) + C($o['amount']); }
        $new = BalanceSheetReport::report($pdo, [11], '', $d);
        $newA = []; foreach ($new['assets']['rows'] as $row) { $newA[$row['id']] = $row['amountRaw']; }
        $newLE = []; foreach (array_merge($new['liabilities']['rows'], $new['equity']['rows']) as $row) { $newLE[$row['id']] = $row['amountRaw']; }
        $fund = FinancialReport::balanceSheet($pdo, [11], $d, false)['sections']['fund'];
        if ($fund['fundAccountId'] !== null) { $newLE[$fund['fundAccountId']] = $fund['acctBf']['cur'] + $fund['acctMove']['cur']; }   // the old 'opening balance equity' line
        $b188 = FinancialReport::balances($pdo, [11], null, $d)[188] ?? ['idr' => 0, 'icr' => 0];
        $d188 = $b188['idr'] - $b188['icr'];
        $diffA = []; foreach (array_unique(array_merge(array_keys($oldA), array_keys($newA))) as $k) { $df = ($newA[$k] ?? 0) - ($oldA[$k] ?? 0); if ($df !== 0) { $diffA[$k] = $df; } }
        $expA = $d188 === 0 ? [] : [186 => -$d188];
        ksort($diffA); ksort($expA);
        ok("$d assets, line by line: the only difference is account 188 (old 'due to parish', new expense): " . json_encode($diffA), $diffA === $expA, json_encode($diffA) . ' vs ' . json_encode($expA));
        $diffLE = []; foreach (array_unique(array_merge(array_keys($oldLE), array_keys($newLE))) as $k) { $df = ($newLE[$k] ?? 0) - ($oldLE[$k] ?? 0); if ($df !== 0) { $diffLE[$k] = $df; } }
        ok("$d liabilities and equity, line by line: no difference (old 'opening balance equity' = new accumulated fund account)", !$diffLE, json_encode($diffLE));
        $newRE = $fund['prior']['cur'] + $fund['surplus']['cur'] + $fund['dividends']['cur'];
        ok("$d old 'Retained Earnings' = new b/f + surplus + dividends, plus account 188 (" . FinancialReport::money($d188) . ')', $oldRE === $newRE + $d188, FinancialReport::money($oldRE) . ' vs ' . FinancialReport::money($newRE));
        echo "INFO $d: old assets 188 effect " . FinancialReport::money($d188) . '; new total assets ' . $new['assets']['total'] . "\n";
    }
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    $pdo->prepare("DELETE FROM journal_entries WHERE reference LIKE 'zz t94%'")->execute();
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz t94%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t94%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM tblcongregation WHERE CongregationName LIKE 'zz t94%'")[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left fixture rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

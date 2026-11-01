<?php
/**
 * Task 12 part B on the real dev data (no fixture rows are written; read-only): the drill-down lists of the trial balance (summary and
 * detailed), the balance sheet and the income statement, congregation and parish scope.
 *   decision 5: balance-sheet accounts (types 3, 4, 6) list only the lines from the start of the financial year of the as-of date; everything
 *               before is ONE "Balance b/f" (net figure, first row, not a transaction, part of the closing balance);
 *   decision 6: income / expense / dividend accounts: no line of an earlier year in any drill (decision 144), a period that spans financial years keeps the separator;
 *   decision 7: the accumulated fund b/f drill: one line per earlier financial year, adding up exactly to the b/f figure, no transaction list;
 *   every drill total and closing balance equals the figure clicked; Excel = screen.
 * CLI only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir(__DIR__ . '/../app');
require 'boostrap.php';
$pdo = (new Database)->pdo();
$_SESSION = ['congId' => 11, 'userId' => 64];
$pass = 0; $fail = 0;
function ok($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function cents($s) { $s = trim((string) $s); if ($s === '') { return 0; } $neg = $s[0] === '(' || $s[0] === '-'; $v = (int) round((float) str_replace([',', '(', ')', '-'], '', $s) * 100); return $neg ? -$v : $v; }
function rowNet($r) { return $r['debitRaw'] - $r['creditRaw']; }

$dates = ['2025-09-30' => 'FY 2024-25 year end', '2026-10-24' => 'current year'];
$scopes = ['congregation 11' => [11], 'parish (all)' => null];

foreach ($dates as $asOf => $dlabel) {
    $start = FinancialReport::yearStart($pdo, $asOf);
    foreach ($scopes as $slabel => $scope) {
        if ($scope === null) { $scope = FinancialReport::parishScope($pdo); }
        $tag = "$dlabel / $slabel";
        echo "== $tag (year starts $start)\n";

        // ------------------------------------------------------------ trial balance, summary and detailed
        foreach ([false, true] as $detailed) {
            // the parish scope walks every account of every congregation: the summary trial balance only (the detailed one differs only in its first level)
            if ($detailed && $slabel === 'parish (all)') { continue; }
            $rep = TrialBalanceReport::report($pdo, $scope, 'x', $asOf, $detailed);
            $bad = []; $levels = 0; $leaves = 0; $bsLeaves = 0; $plLeaves = 0;
            $walk = function ($account, $net, $label, $bf) use (&$walk, &$bad, &$levels, &$leaves, &$bsLeaves, &$plLeaves, $pdo, $scope, $asOf, $start) {
                $d = TrialBalanceReport::drill($pdo, $scope, $account, $asOf, 'children', false, $bf);
                $levels++;
                if ($d['mode'] === 'children') {
                    $nodeNet = $d['debitRaw'] - $d['creditRaw'];
                    if (!$d['sumsExactly'] || $d['rowsDebitRaw'] - $d['rowsCreditRaw'] !== $nodeNet || $nodeNet !== $net) { $bad[] = "$label rows " . ($d['rowsDebitRaw'] - $d['rowsCreditRaw']) . " node $nodeNet clicked $net"; }
                    foreach ($d['rows'] as $row) {
                        $rn = $row['debitRaw'] - $row['creditRaw'];
                        if (($row['next'] ?? '') === 'incomestatement') { continue; }
                        if ($row['direct']) { $walkLines = true; $l = TrialBalanceReport::drill($pdo, $scope, $row['id'], $asOf, 'lines', true, $bf); $levels++; $leaves++; $GLOBALS['__chk']($l, $rn, "$label>direct", $bf, $account); }
                        else { $walk($row['id'], $rn, $label . '>' . $row['name'], $bf); }
                    }
                } else {
                    $leaves++;
                    $GLOBALS['__chk']($d, $net, $label, $bf, $account);
                }
            };
            // a lines page: figure = closing, Balance b/f and the earliest line
            $GLOBALS['__chk'] = function ($l, $net, $label, $bf, $account) use (&$bad, &$bsLeaves, &$plLeaves, $start, $pdo, $scope, $asOf) {
                $fig = cents($l['figureDebit']) - cents($l['figureCredit']);
                if ($fig !== $net) { $bad[] = "$label lines figure $fig vs clicked $net"; }
                if ($bf) { return; }   // the prior-years view is a different basis (range, credit direction)
                if ($l['balanceBf'] !== null) {
                    $bsLeaves++;
                    if ($l['balanceBf']['date'] !== date('d-m-Y', strtotime($start))) { $bad[] = "$label Balance b/f date " . $l['balanceBf']['date']; }
                } else { $plLeaves++; if ($l['opening'] !== '0.00') { $bad[] = "$label income / expense opening " . $l['opening']; } }
                foreach ($l['rows'] as $r) { if (date('Y-m-d', strtotime($r['date'])) < $start) { $bad[] = "$label has a line from an earlier year: " . $r['date']; break; } }
            };
            foreach ($rep['rows'] as $row) {
                if ($row['id'] === 'bf') { continue; }
                $walk($row['id'], rowNet($row), $row['name'], false);
            }
            ok("trial balance" . ($detailed ? ' detailed' : '') . " $tag: $levels drill levels add up, $leaves line lists close at the figure clicked ($bsLeaves balance-sheet lists with a Balance b/f, $plLeaves income / expense lists), nothing before the year start", $levels > 0 && !$bad, implode('; ', array_slice($bad, 0, 5)));
        }

        // ------------------------------------------------------------ the accumulated fund b/f: one line per earlier financial year
        $res = FinancialReport::priorYearResults($pdo, $scope, $asOf);
        $bfFigure = FinancialReport::accumulatedFundBf($pdo, $scope, $asOf);
        ok("accumulated fund b/f $tag: the per-year lines add up exactly to the b/f figure (" . FinancialReport::money($bfFigure) . ')', $res['total'] === $bfFigure && array_sum(array_column($res['rows'], 'amount')) === $bfFigure, json_encode([$res['total'], $bfFigure]));
        $names = array_column($res['rows'], 'name');
        ok("accumulated fund b/f $tag: named 'Surplus / (deficit) <year>', oldest first, no zero line, each with its start and end", $names === array_values(array_filter($names, function ($n) { return strpos($n, 'Surplus / (deficit) ') === 0; }))
            && !in_array(0, array_column($res['rows'], 'amount'), true) && (function ($rows) { $s = array_values(array_filter(array_column($rows, 'start'))); $t = $s; sort($t); return $s === $t; })($res['rows'])
            && count(array_filter($res['rows'], function ($r) { return $r['id'] !== 'fy0' && ($r['start'] === null || $r['end'] === null); })) === 0, json_encode($names));
        $tb = TrialBalanceReport::drill($pdo, $scope, 'bf', $asOf, 'children', false, true);
        ok("trial balance $tag: the b/f drill = the per-year lines, no transaction or account list, opening the year's income statement", $tb['sumsExactly'] && count($tb['rows']) === count($res['rows'])
            && count(array_filter($tb['rows'], function ($r) { return $r['next'] === 'incomestatement'; })) === count($tb['rows']) && array_column($tb['rows'], 'name') === $names
            && $tb['rowsDebitRaw'] - $tb['rowsCreditRaw'] === -$bfFigure, json_encode([$tb['rowsDebitRaw'], $tb['rowsCreditRaw'], $bfFigure]));
        $bsBf = BalanceSheetReport::drill($pdo, $scope, 'bf', $asOf, 'children', false, '');
        $parts = FinancialReport::accumulatedFundParts($pdo, $scope, $asOf);
        $yearRows = array_values(array_filter($bsBf['rows'], function ($r) { return ($r['next'] ?? '') === 'incomestatement'; }));
        ok("balance sheet $tag: the b/f drill = the fund account at the year start + one line per earlier financial year; adds up to the b/f figure", $bsBf['sumsExactly'] && $bsBf['rowsTotalRaw'] === $parts['bf']
            && array_sum(array_column($yearRows, 'amountRaw')) === $parts['prior'] && array_column($yearRows, 'name') === $names && count($bsBf['rows']) === count($yearRows) + ($parts['acctBf'] !== 0 ? 1 : 0), json_encode([$bsBf['rowsTotalRaw'], $parts]));
        $one = array_values(array_filter($res['rows'], function ($r) { return $r['start'] !== null; }));
        if ($one) {
            // the line's income statement (that year's dates) shows the same surplus as the line, while the year's postings are within the b/f
            $y = $one[0];
            $is = FinancialReport::incomeStatement($pdo, $scope, $y['start'], $y['end']);
            $iss = 0; foreach (['income', 'expense'] as $k) { }
            ok("b/f line '{$y['name']}' ($tag): its start and end are the financial year's dates", $y['start'] < $y['end'] && $y['end'] < $start);
        }

        // ------------------------------------------------------------ balance sheet
        $r = BalanceSheetReport::report($pdo, $scope, 'x', $asOf);
        $bad = []; $levels = 0; $lines = 0; $bfLists = 0;
        $walkB = function ($account, $amount, $label, $fund) use (&$walkB, &$bad, &$levels, &$lines, &$bfLists, $pdo, $scope, $asOf, $start) {
            $d = BalanceSheetReport::drill($pdo, $scope, $account, $asOf, 'children', false, $fund);
            $levels++;
            if ($d['mode'] === 'children') {
                if (!$d['sumsExactly'] || $d['rowsTotal'] !== $d['amount'] || ($amount !== null && $d['amount'] !== $amount)) { $bad[] = "$label rows {$d['rowsTotal']} node {$d['amount']} clicked $amount"; }
                foreach ($d['rows'] as $row) {
                    if (($row['next'] ?? '') === 'incomestatement' && !empty($row['start'])) { continue; }
                    if ($row['direct']) { $l = BalanceSheetReport::drill($pdo, $scope, $row['id'], $asOf, 'lines', true, $fund); $levels++; $lines++; $GLOBALS['__chkB']($l, $row['amount'], "$label>direct", $fund); }
                    else { $walkB($row['id'], $row['amount'], $label . '>' . $row['name'], $fund); }
                }
            } else { $lines++; $GLOBALS['__chkB']($d, $amount, $label, $fund); }
        };
        $GLOBALS['__chkB'] = function ($l, $amount, $label, $fund) use (&$bad, &$bfLists, $start) {
            if ($amount !== null && $l['closing'] !== $amount) { $bad[] = "$label closing {$l['closing']} vs $amount"; }
            if ($fund !== '') { return; }
            if ($l['balanceBf'] !== null) { $bfLists++; if ($l['balanceBf']['date'] !== date('d-m-Y', strtotime($start))) { $bad[] = "$label Balance b/f date"; } }
            foreach ($l['rows'] as $x) { if (date('Y-m-d', strtotime($x['date'])) < $start) { $bad[] = "$label line before the year start " . $x['date']; break; } }
        };
        foreach (array_merge($r['assets']['rows'], $r['liabilities']['rows'], $r['equity']['rows']) as $row) { $walkB($row['id'], $row['amount'], $row['name'], ''); }
        ok("balance sheet $tag: $levels drill levels add up and $lines line lists close at the figure clicked ($bfLists with a Balance b/f dated the year start, no line before it)", $levels > 0 && !$bad, implode('; ', array_slice($bad, 0, 5)));
        ok("balance sheet $tag: balanced", $r['balanced'] === true);
    }
}

// ---------------------------------------------------------------- Balance b/f row: position, figure, Excel = screen, search and paging (a real account with lines before the year)
echo "== Balance b/f row on a real account\n";
$asOf = '2026-10-24'; $start = FinancialReport::yearStart($pdo, $asOf);
$cash = (int) $pdo->query("SELECT l.accountId FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId JOIN tblaccounttypes a ON a.ID = l.accountId
                           WHERE e.congregationId = 11 AND e.deleted = 0 AND a.accountTypeId = 3 AND e.entryDate < '$start'
                             AND l.accountId IN (SELECT l2.accountId FROM journal_lines l2 JOIN journal_entries e2 ON e2.ID = l2.journalEntryId WHERE e2.congregationId = 11 AND e2.deleted = 0 AND e2.entryDate BETWEEN '$start' AND '$asOf')
                           GROUP BY l.accountId ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
ok('a type 3 account with lines before and after the year start exists on dev (the check below needs it)', $cash > 0);
if ($cash > 0) {
    $period = ['kind' => 'tb', 'asOf' => $asOf];
    $all = FinancialReport::linesPage($pdo, [11], $cash, $period, false, 1, 0, '');
    $before = $pdo->query("SELECT COALESCE(SUM(CASE WHEN l.dc='DEBIT' THEN l.amount ELSE -l.amount END),0) FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE l.accountId=$cash AND e.congregationId=11 AND e.deleted=0 AND e.entryDate < '$start'")->fetchColumn();
    $upto = $pdo->query("SELECT COALESCE(SUM(CASE WHEN l.dc='DEBIT' THEN l.amount ELSE -l.amount END),0) FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE l.accountId=$cash AND e.congregationId=11 AND e.deleted=0 AND e.entryDate <= '$asOf'")->fetchColumn();
    ok('Balance b/f = the net of everything before the year start', $all['openingNet'] === FinancialReport::cents($before) && $all['bf']['date'] === $start, json_encode([$all['openingNet'], $before]));
    ok('closing balance = Balance b/f + the period\'s movement = the net up to the as-of date', $all['closingNet'] === $all['openingNet'] + $all['totals']['debit'] - $all['totals']['credit'] && $all['closingNet'] === FinancialReport::cents($upto));
    ok('Balance b/f is not counted in the period\'s debit / credit totals; the rows are only from the year start', $all['totals']['debit'] === array_sum(array_column($all['rows'], 'debit')) && $all['totals']['credit'] === array_sum(array_column($all['rows'], 'credit')) && min(array_column($all['rows'], 'date')) >= $start);
    ok('the first running balance starts from the Balance b/f', $all['rows'][0]['net'] === $all['openingNet'] + $all['rows'][0]['debit'] - $all['rows'][0]['credit']);
    $p2 = LinesView::shape(FinancialReport::linesPage($pdo, [11], $cash, $period, false, 2, 5, ''));
    $s1 = LinesView::shape(FinancialReport::linesPage($pdo, [11], $cash, $period, false, 1, 5, 'no-such-text-zz'));
    $p1 = LinesView::shape(FinancialReport::linesPage($pdo, [11], $cash, $period, false, 1, 5, ''));
    ok('paging and search never hide the Balance b/f; its figure is the same on every page and in the search result', $p2['balanceBf'] !== null && $s1['balanceBf'] !== null && $p1['opening'] === $p2['opening'] && $p2['opening'] === $s1['opening'] && $p1['balanceBf'] === ['date' => date('d-m-Y', strtotime($start)), 'label' => 'Balance b/f']);
    ok('the Balance b/f row has no entry number and no link: it is not a transaction (the shape carries no row for it)', count($p1['rows']) === min(5, count($all['rows'])) && !isset($p1['balanceBf']['link']) && !isset($p1['balanceBf']['entryId']));
    $xl = LinesView::sheetRows($all, ['x']);
    $first = null; foreach ($xl as $row) { if (isset($row[4]['v']) && $row[4]['v'] === 'Balance b/f') { $first = $row; break; } }
    $idx = array_search($first, $xl, true);
    ok('Excel = screen: the Balance b/f row (dated the year start, same figure) comes first, before the first line', $first !== null && $first[0]['v'] === $start && end($first)['v'] === $all['openingNet'] && $idx === 3 + 0 || ($first !== null && $xl[$idx + 1][0]['v'] === $all['rows'][0]['date']), json_encode($first));
    $last = end($xl);
    ok('Excel = screen: the closing balance row equals the screen closing', end($last)['v'] === $all['closing'], json_encode($last));
}

// ---------------------------------------------------------------- income / expense / dividend accounts: nothing from an earlier year; a period across years keeps the separator
echo "== income, expense and dividend accounts\n";
$asOf = '2026-10-24'; $start = FinancialReport::yearStart($pdo, $asOf);
$chart = FinancialReport::chart($pdo, [11]);
$bad = []; $checked = 0;
foreach ($chart['nodes'] as $id => $n) {
    if (!$n['classified'] || !in_array($n['root'], [1, 2, 5], true) || $n['depth'] < 2) { continue; }
    $L = FinancialReport::linesPage($pdo, [11], (int) $id, ['kind' => 'tb', 'asOf' => $asOf], true, 1, 0, '');
    if (!$L['rows']) { continue; }
    $checked++;
    if ($L['bf'] !== null || $L['openingNet'] !== 0) { $bad[] = "account $id has an opening / b/f"; }
    foreach ($L['rows'] as $r) { if ($r['date'] < $start) { $bad[] = "account $id line {$r['date']}"; break; } }
    if ($checked >= 60) { break; }
}
ok("income, expense and dividend drills (trial balance basis): $checked accounts, no line of an earlier year, no Balance b/f, opening 0.00", $checked > 0 && !$bad, implode('; ', array_slice($bad, 0, 5)));
$id = (int) $pdo->query("SELECT l.accountId FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId JOIN tblaccounttypes a ON a.ID = l.accountId
                         WHERE e.congregationId = 11 AND e.deleted = 0 AND a.accountTypeId IN (1, 2) AND e.entryDate BETWEEN '2025-09-01' AND '2026-10-24'
                         GROUP BY l.accountId HAVING SUM(e.entryDate < '2025-10-01') > 0 AND SUM(e.entryDate BETWEEN '2025-10-01' AND '2026-09-30') > 0 AND SUM(e.entryDate >= '2026-10-01') > 0
                         ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
if ($id) {
    $span = FinancialReport::linesPage($pdo, [11], $id, ['kind' => 'is', 'from' => '2025-09-01', 'to' => '2026-10-24', 'comparative' => false], false, 1, 0, '');
    $fys = array_unique(array_filter(array_column($span['rows'], 'fy')));
    $seps = 0; $prev = null; foreach ($span['rows'] as $i => $r) { $before = $i === 0 ? $r['prevFy'] : $prev; $prev = $r['fy']; if ($r['fy'] && $before && $r['fy'] !== $before) { $seps++; } }
    ok('an income statement period that spans financial years keeps the "new financial year" separator (rows carry fy / prevFy)', count($fys) >= 2 && $seps === count($fys) - 1 && $span['bf'] === null, json_encode([$fys, $seps]));
}

// ---------------------------------------------------------------- the ledger statement is not changed (decision 8)
echo "== ledger statement (decision 8: unchanged)\n";
if ($cash > 0) {
    $ls = FinancialReport::linesPage($pdo, [11], $cash, ['kind' => 'range', 'from' => '2026-10-01', 'to' => '2026-10-24', 'direction' => 'normal'], false, 1, 0, '');
    ok('a ledger statement of a balance-sheet account still shows a plain opening balance (cumulative before the from date), no Balance b/f row', $ls['bf'] === null && $ls['restartsEachYear'] === false);
    $ls2 = FinancialReport::linesPage($pdo, [11], $cash, ['kind' => 'range', 'from' => '2024-01-01', 'to' => '2026-10-24', 'direction' => 'normal'], false, 1, 0, '');
    ok('a ledger statement across several years lists every line of the period (nothing collapsed)', $ls2['bf'] === null && $ls2['count'] >= $ls['count'] && min(array_column($ls2['rows'], 'date')) < '2025-10-01');
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

<?php
/**
 * Balance sheet screens (task 9.4 / 9.5, decisions 119-122, 129-130, 141-142, 144, 147-150): shapes FinancialReport::balanceSheet() /
 * children() / linesPage() into JSON (rendered with textContent) or an Excel workbook. Shared by the congregation (Reports) and
 * the parish (Parishreports) controllers; only the scope differs. URLs carry account IDs only.
 *
 * Layout: Assets; Liabilities; Equity and accumulated fund = the other type 6 accounts + ONE accumulated fund block (decision 150):
 * Accumulated fund b/f (the accumulated fund account at the year start + the prior years' surplus), Surplus / (deficit) for the year
 * to date, Dividends only when non-zero, [Movement on the accumulated fund account during the year only when non-zero],
 * Accumulated fund c/f; Total liabilities, equity and accumulated fund; the check "assets = liabilities + equity + fund", with a red
 * difference line when it does not hold (never forced). Comparative column = the last financial year-end.
 *
 * Drill pages (account IDs): a type 3 / 4 / 6 account (fund = ''); 'bf' = the two parts of the b/f line; 'pys' = prior years'
 * surplus (its type 1, 2, 5 accounts, fund = 'bf'); the accumulated fund account at the year start (fund = 'fa'); 'div'
 * (fund = 'div'); the fund account's movement during the year (fund = 'fm'). The surplus line opens the income statement.
 */
class BalanceSheetReport
{
    const FUND_NAMES = [
        'bf'  => 'Accumulated fund b/f',
        'cy'  => 'Surplus / (deficit) for the year to date',
        'div' => 'Dividends',
        'mv'  => 'Movement on the accumulated fund account during the year',
        'pys' => "Prior years' surplus",
    ];

    private static function name(string $n): string { return FinancialReport::title($n); }
    private static function b(?int $c): ?string { return IncomeStatementReport::bracket($c); }
    private static function ddmmyyyy(string $d): string { return date('d-m-Y', strtotime($d)); }

    private static function row(array $r): array
    {
        $fund = isset($r['synthetic']);
        $direct = !empty($r['direct']);
        return [
            'id'        => $r['id'],
            'name'      => $fund ? self::FUND_NAMES[$r['id']] : self::name((string) $r['name']),
            'amount'    => self::b($r['amount']), 'cmp' => self::b($r['comparative']),
            'amountRaw' => $r['amount'],          'cmpRaw' => $r['comparative'],
            'direct'    => $direct,
            'fund'      => $fund,
            'next'      => $fund ? ($r['id'] === 'cy' ? 'incomestatement' : 'children') : ((!$direct && !empty($r['hasChildren'])) ? 'children' : 'lines'),
        ];
    }

    private static function section(array $s): array
    {
        return ['rows' => array_map([self::class, 'row'], $s['rows']), 'total' => self::b($s['total']), 'cmpTotal' => self::b($s['comparativeTotal']),
                'totalRaw' => $s['total'], 'cmpTotalRaw' => $s['comparativeTotal'], 'sumsExactly' => $s['sumsExactly']];
    }

    public static function report(PDO $db, array $scope, string $entity, string $asOf): array
    {
        TrialBalanceReport::year($db, $asOf);   // a date with no financial year is a clear 400
        $r = FinancialReport::balanceSheet($db, $scope, $asOf, true);
        $cmp = $r['comparative'] !== null;
        $assets = self::section($r['sections']['assets']);
        $liab = self::section($r['sections']['liabilities']);
        $eq = self::section($r['sections']['equity']);
        $fund = self::section($r['sections']['fund']);
        $eqTotal = $r['sections']['equity']['total'] + $r['sections']['fund']['total'];
        $eqCmp = $cmp ? $r['sections']['equity']['comparativeTotal'] + $r['sections']['fund']['comparativeTotal'] : null;
        $lef = $r['totals']['current']['liabilitiesEquityFund'];
        $lefC = $cmp ? $r['totals']['comparative']['liabilitiesEquityFund'] : null;
        $diff = $r['totals']['current']['difference'];
        $diffC = $cmp ? $r['totals']['comparative']['difference'] : null;
        $u = [];
        foreach ($r['unclassified']['rows'] as $x) { $u[] = ['id' => $x['id'], 'name' => self::name($x['name']), 'amount' => self::b($x['net'])]; }
        return [
            'entity' => $entity, 'asOf' => $asOf, 'yearName' => $r['financialYear']['name'], 'yearStart' => $r['financialYear']['start'],
            'cmpAsOf' => $cmp ? $r['comparative']['asOf'] : null, 'cmpError' => $r['comparativeError'],
            'assets' => $assets, 'liabilities' => $liab,
            'equity' => $eq,   // the other type 6 accounts
            'fund' => $fund + ['fundAccountId' => $r['sections']['fund']['fundAccountId'], 'diagnostic' => $r['sections']['fund']['diagnostic']],
            'equityTotal' => ['total' => self::b($eqTotal), 'cmpTotal' => self::b($eqCmp), 'totalRaw' => $eqTotal, 'cmpTotalRaw' => $eqCmp],
            'liabilitiesEquityFund' => ['total' => self::b($lef), 'cmpTotal' => self::b($lefC), 'totalRaw' => $lef, 'cmpTotalRaw' => $lefC],
            'balanced' => $diff === 0 && ($diffC === null || $diffC === 0),
            'difference' => ['amount' => self::b($diff), 'cmp' => self::b($diffC), 'amountRaw' => $diff, 'cmpRaw' => $diffC],
            'unclassified' => ['present' => $r['unclassified']['present'], 'message' => $r['unclassified']['message'], 'rows' => $u],
        ];
    }

    private static function prevDay(string $date): string
    {
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        $d->modify('-1 day');
        return $d->format('Y-m-d');
    }

    /** the period of a drill: the balance-sheet basis, or for the fund lines a range in the credit direction */
    private static function period(PDO $db, string $asOf, string $fund): array
    {
        if ($fund === '') { return ['kind' => 'bs', 'asOf' => $asOf, 'comparative' => true]; }
        $start = FinancialReport::yearStart($db, $asOf);
        if ($fund === 'bf' || $fund === 'fa') {   // earlier years only, cumulative
            return ['kind' => 'range', 'from' => null, 'to' => self::prevDay($start), 'direction' => 'credit', 'fy' => false];
        }
        return ['kind' => 'range', 'from' => $start, 'to' => $asOf, 'direction' => 'credit'];   // div, fm: the year to date
    }

    private static function childrenShape(array $c): array
    {
        return [
            'name' => self::name((string) ($c['node']['label'] ?? $c['node']['name'] ?? '')),
            'amount' => self::b($c['amount']), 'cmp' => self::b($c['comparative']),
            'rows' => array_map([self::class, 'row'], $c['rows']),
            'rowsTotal' => self::b($c['rowsTotal']), 'rowsTotalRaw' => $c['rowsTotal'], 'sumsExactly' => $c['sumsExactly'],
        ];
    }

    private static function plainRow(string $id, string $name, int $raw, $fund): array
    {
        return ['id' => $id, 'name' => $name, 'amount' => self::b($raw), 'cmp' => null, 'amountRaw' => $raw, 'cmpRaw' => null,
                'direct' => false, 'fund' => $fund, 'next' => 'children'];
    }

    /**
     * One drill level. $account is a chart account ID or 'bf' / 'pys' / 'div'. $fund says how an account ID is reached (see the class
     * comment). The surplus line has no drill of its own: it opens the income statement of the year to date.
     */
    public static function drill(PDO $db, array $scope, $account, string $asOf, string $mode, bool $direct, string $fund, int $page = 1, int $pageSize = 50, string $search = ''): array
    {
        TrialBalanceReport::year($db, $asOf);
        $out = ['asOf' => $asOf, 'fund' => $fund, 'direct' => $direct];
        if ($account === 'bf') {
            $parts = FinancialReport::accumulatedFundParts($db, $scope, $asOf);
            if ($parts['fundAccountId'] === null) { $account = 'pys'; }   // account not identified: the old presentation
            else {
                $chart = FinancialReport::chart($db, $scope);
                $rows = [
                    self::plainRow((string) $parts['fundAccountId'], self::name($chart['nodes'][$parts['fundAccountId']]['label']) . ' at the start of the year', $parts['acctBf'], 'fa'),
                    self::plainRow('pys', self::FUND_NAMES['pys'], $parts['prior'], true),
                ];
                $rows = array_values(array_filter($rows, function ($r) { return $r['amountRaw'] !== 0; }));
                $sum = 0; foreach ($rows as $r) { $sum += $r['amountRaw']; }
                $out['fund'] = 'bf';
                return array_merge($out, ['mode' => 'children', 'crumbs' => [], 'root' => null, 'name' => self::FUND_NAMES['bf'], 'amount' => self::b($parts['bf']), 'cmp' => null,
                    'rows' => $rows, 'rowsTotal' => self::b($sum), 'rowsTotalRaw' => $sum, 'sumsExactly' => $sum === $parts['bf'], 'block' => true]);
            }
        }
        if ($account === 'pys' || $account === 'div') {
            $c = FinancialReport::children($db, $scope, $account === 'pys' ? 'bf' : 'div', ['kind' => 'bs', 'asOf' => $asOf]);
            $out['fund'] = $account === 'pys' ? 'bf' : 'div';
            $res = array_merge($out, ['mode' => 'children', 'crumbs' => [], 'root' => null], self::childrenShape($c), ['cmp' => null]);
            $res['name'] = self::FUND_NAMES[$account];
            return $res;
        }
        $chart = FinancialReport::chart($db, $scope);
        $node = $chart['nodes'][$account] ?? null;
        $fa = FinancialReport::fundAccountId($chart);
        $allowed = $fund === '' ? [3, 4, 6] : (in_array($fund, ['fa', 'fm'], true) ? [6] : [1, 2, 5]);
        if ($node === null || !$node['classified'] || $node['depth'] < 2 || !in_array($node['root'], $allowed, true)) { IncomeStatementReport::fail('Invalid account'); }
        if ($fund === 'div' && $node['root'] !== 5) { IncomeStatementReport::fail('Invalid account'); }
        if (in_array($fund, ['fa', 'fm'], true) && ($fa === null || !in_array($fa, $node['chain'], true))) { IncomeStatementReport::fail('Invalid account'); }
        $crumbs = [];
        foreach (array_reverse($node['chain']) as $cid) {
            if (($chart['nodes'][$cid]['depth'] ?? 0) >= 2) { $crumbs[] = ['id' => $cid, 'name' => self::name($chart['nodes'][$cid]['label'])]; }
        }
        $period = self::period($db, $asOf, $fund);
        $out += ['crumbs' => $crumbs, 'root' => $node['root']];
        if (!$direct && $mode !== 'lines') {
            $c = FinancialReport::children($db, $scope, $account, $period);
            $onlyDirect = count($c['rows']) === 1 && !empty($c['rows'][0]['direct']);
            if ($c['rows'] && !$onlyDirect) { return $out + ['mode' => 'children'] + self::childrenShape($c); }
        }
        $L = FinancialReport::linesPage($db, $scope, $account, $period, !$direct, $page, $pageSize, $search);
        return $out + LinesView::shape($L) + ['name' => self::name($node['label']) . ($direct ? ' (postings on this account)' : '')];
    }

    public static function respondReport(PDO $db, array $scope, string $entity, string $asOf): void
    {
        $d = self::report($db, $scope, $entity, $asOf);
        if (!IncomeStatementReport::wantsXlsx()) { IncomeStatementReport::send($d); }
        XlsxWriter::download('balance-sheet-' . $asOf . '.xlsx', 'Balance sheet', self::reportSheet($d), [48, 20, 20]);
    }

    public static function respondDrill(PDO $db, array $scope, string $entity, $account, string $asOf, string $mode, bool $direct, string $fund, array $q): void
    {
        [$page, $size, $search] = LinesView::params($q);
        if (!IncomeStatementReport::wantsXlsx()) { IncomeStatementReport::send(self::drill($db, $scope, $account, $asOf, $mode, $direct, $fund, $page, $size, $search)); }
        $d = self::drill($db, $scope, $account, $asOf, $mode, $direct, $fund, 1, 1, '');
        $heading = array_values(array_filter([$entity, 'Balance sheet drill-down: ' . $d['name'], 'As at ' . self::ddmmyyyy($asOf)], 'strlen'));
        if ($d['mode'] === 'lines') {
            $L = FinancialReport::linesPage($db, $scope, (int) $account, self::period($db, $asOf, $fund), !$direct, 1, 0, $search);
            XlsxWriter::download('balance-sheet-lines-' . $account . '.xlsx', 'Journal lines', LinesView::sheetRows($L, $heading), LinesView::widths());
        }
        $rows = [];
        foreach ($heading as $i => $h) { $rows[] = [XlsxWriter::s($h, $i === 0)]; }
        $rows[] = [];
        $rows[] = [XlsxWriter::s('Account', true), XlsxWriter::s('As at ' . self::ddmmyyyy($asOf), true), XlsxWriter::s('Last year-end', true)];
        foreach ($d['rows'] as $r) { $rows[] = [XlsxWriter::s($r['name']), XlsxWriter::m($r['amountRaw']), XlsxWriter::m($r['cmpRaw'])]; }
        $rows[] = [XlsxWriter::s('Total', true), XlsxWriter::m($d['rowsTotalRaw'], true), null];
        XlsxWriter::download('balance-sheet-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $account) . '.xlsx', 'Balance sheet', $rows, [48, 20, 20]);
    }

    public static function reportSheet(array $d): array
    {
        $S = function ($t, $b = false) { return XlsxWriter::s($t, $b); };
        $M = function ($c, $b = false) { return XlsxWriter::m($c, $b); };
        $rows = [];
        if ($d['entity'] !== '') { $rows[] = [$S($d['entity'], true)]; }   // parish scope line only (decision 147)
        $rows[] = [$S('Balance sheet as at ' . self::ddmmyyyy($d['asOf']), $d['entity'] === '')];
        $rows[] = [];
        $rows[] = [$S('Account', true), $S(self::ddmmyyyy($d['asOf']), true), $S($d['cmpAsOf'] ? self::ddmmyyyy($d['cmpAsOf']) . ' (last year-end)' : 'Last year-end', true)];
        $sec = function ($title, $rowsIn, $totalLabel, $total, $cmp) use (&$rows, $S, $M) {
            if ($title !== null) { $rows[] = [$S($title, true)]; }
            foreach ($rowsIn as $r) { $rows[] = [$S($r['name']), $M($r['amountRaw']), $M($r['cmpRaw'])]; }
            if ($totalLabel !== null) { $rows[] = [$S($totalLabel, true), $M($total, true), $M($cmp, true)]; }
        };
        $sec('Assets', $d['assets']['rows'], 'Total assets', $d['assets']['totalRaw'], $d['assets']['cmpTotalRaw']);
        $sec('Liabilities', $d['liabilities']['rows'], 'Total liabilities', $d['liabilities']['totalRaw'], $d['liabilities']['cmpTotalRaw']);
        $sec('Equity and accumulated fund', $d['equity']['rows'], null, 0, 0);
        $sec(null, $d['fund']['rows'], 'Accumulated fund c/f', $d['fund']['totalRaw'], $d['fund']['cmpTotalRaw']);
        $rows[] = [$S('Total equity and accumulated fund', true), $M($d['equityTotal']['totalRaw'], true), $M($d['equityTotal']['cmpTotalRaw'], true)];
        $rows[] = [$S('Total liabilities, equity and accumulated fund', true), $M($d['liabilitiesEquityFund']['totalRaw'], true), $M($d['liabilitiesEquityFund']['cmpTotalRaw'], true)];
        if (!$d['balanced']) { $rows[] = [$S('Difference (assets - liabilities - equity - fund)', true), $M($d['difference']['amountRaw'], true), $M($d['difference']['cmpRaw'], true)]; }
        if ($d['fund']['diagnostic']) { $rows[] = []; $rows[] = [$S($d['fund']['diagnostic'], true)]; }
        if ($d['unclassified']['present']) { $rows[] = []; $rows[] = [$S($d['unclassified']['message'], true)]; }
        return $rows;
    }

    /** report endpoint: asof (+ cong for the parish), JSON or format=xlsx */
    public static function serveReport(PDO $db, array $scope, string $entity): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        self::respondReport($db, $scope, $entity, TrialBalanceReport::asOf($_GET));
    }

    /** drill endpoint: account ID or bf / pys / div, asof, view=lines, direct=1, fund=bf|div|fa|fm, page / pageSize / search, format=xlsx */
    public static function serveDrill(PDO $db, array $scope, string $entity): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        $asOf = TrialBalanceReport::asOf($_GET);
        $a = $_GET['account'] ?? '';
        $account = in_array($a, ['bf', 'pys', 'div'], true) ? $a : IncomeStatementReport::accountId($a);
        $fund = $_GET['fund'] ?? '';
        if (!in_array($fund, ['', 'bf', 'div', 'fa', 'fm'], true)) { IncomeStatementReport::fail('Invalid request'); }
        self::respondDrill($db, $scope, $entity, $account, $asOf, ($_GET['view'] ?? '') === 'lines' ? 'lines' : 'children', ($_GET['direct'] ?? '') === '1', $fund, $_GET);
    }
}

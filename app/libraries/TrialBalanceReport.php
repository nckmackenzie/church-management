<?php
/**
 * Trial balance screens (task 9.3, decisions 129-131, 141-142): shapes FinancialReport::trialBalance() / children() / linesPage()
 * into JSON (rendered with textContent) or an Excel workbook. Shared by the congregation (Trialbalance) and the parish
 * (Parishreports) controllers; only the scope differs. URLs carry account IDs only.
 *
 * Basis (decision 129): types 3, 4, 6 cumulative to the as-of date; types 1, 2, 5 from the start of the financial year containing
 * it; one "Accumulated fund b/f" line (types 1, 2, 5 before that start). The same basis applies at every drill level; the
 * accumulated fund b/f drills (with bf=1) into the prior years' totals of the type 1, 2, 5 accounts.
 */
class TrialBalanceReport
{
    private static function name(string $n): string { return FinancialReport::title($n); }

    private static function cols(?int $netDebit): array
    {
        $n = (int) $netDebit;
        $c = FinancialReport::trialBalanceColumns($n);
        return ['debit' => $c['debit'] ? IncomeStatementReport::bracket($c['debit']) : '', 'credit' => $c['credit'] ? IncomeStatementReport::bracket($c['credit']) : '',
                'debitRaw' => $c['debit'], 'creditRaw' => $c['credit']];
    }

    /** As Of Date from the request: strict Y-m-d, required, inside a financial year (HTTP 400 otherwise). */
    public static function asOf(array $q): string
    {
        $v = $q['asof'] ?? null;
        if (!is_string($v) || trim($v) === '') { IncomeStatementReport::fail('Please provide the as-of date'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v)) { IncomeStatementReport::fail('The as-of date must be a valid date (YYYY-MM-DD)'); }
        $d = DateTime::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) { IncomeStatementReport::fail('The as-of date is not a valid calendar date'); }
        return $v;
    }

    public static function year(PDO $db, string $asOf): array
    {
        try { return FinancialReport::financialYear($db, $asOf); }
        catch (FinancialReportException $e) { IncomeStatementReport::fail($e->getMessage()); }
        return [];
    }

    public static function report(PDO $db, array $scope, string $entity, string $asOf, bool $detailed): array
    {
        $fy = self::year($db, $asOf);
        $r = FinancialReport::trialBalance($db, $scope, $asOf, $detailed);
        $rows = [];
        foreach ($r['rows'] as $x) {
            $unc = !empty($x['unclassified']);
            $c = self::cols((int) $x['net']);
            $synthetic = !empty($x['synthetic']);
            $rows[] = [
                'id'        => $x['id'],
                'name'      => self::name((string) $x['name']),
                'section'   => $x['section'] ?? '',
                'debit'     => $c['debit'], 'credit' => $c['credit'], 'debitRaw' => $c['debitRaw'], 'creditRaw' => $c['creditRaw'],
                'direct'    => !empty($x['direct']) || $detailed,
                'next'      => $unc ? null : ($detailed ? 'lines' : ((!empty($x['hasChildren']) || $synthetic) ? 'children' : 'lines')),
                'synthetic' => $synthetic,
            ];
        }
        $u = [];
        foreach ($r['unclassified']['rows'] as $x) { $u[] = ['id' => $x['id'], 'name' => self::name($x['name']), 'amount' => IncomeStatementReport::bracket($x['net'])]; }
        return [
            'entity'   => $entity, 'asOf' => $asOf, 'detailed' => $detailed,
            'yearName' => $fy['name'], 'yearStart' => $fy['start'],
            'rows'     => $rows,
            'totals'   => ['debit' => IncomeStatementReport::bracket($r['totals']['debit']), 'credit' => IncomeStatementReport::bracket($r['totals']['credit']),
                           'debitRaw' => $r['totals']['debit'], 'creditRaw' => $r['totals']['credit'],
                           'difference' => IncomeStatementReport::bracket($r['totals']['difference']), 'balanced' => $r['totals']['difference'] === 0],
            'unclassified' => ['present' => $r['unclassified']['present'], 'message' => $r['unclassified']['message'], 'rows' => $u],
        ];
    }

    /** the period descriptor of a drill: the trial-balance basis, or (bf) the prior years in the credit direction */
    private static function period(PDO $db, string $asOf, bool $bf): array
    {
        if (!$bf) { return ['kind' => 'tb', 'asOf' => $asOf]; }
        $start = FinancialReport::yearStart($db, $asOf);
        $prev = DateTime::createFromFormat('!Y-m-d', $start);
        $prev->modify('-1 day');
        return ['kind' => 'range', 'from' => null, 'to' => $prev->format('Y-m-d'), 'direction' => 'credit', 'fy' => false];
    }

    /**
     * One drill level. $account is a chart account ID or 'bf'. $bf = the account is opened from the accumulated fund b/f line
     * (prior years' totals). Children rows add up to the node; lines are paged / searched on the server.
     */
    public static function drill(PDO $db, array $scope, $account, string $asOf, string $mode, bool $direct, bool $bf, int $page = 1, int $pageSize = 50, string $search = ''): array
    {
        self::year($db, $asOf);
        $chart = FinancialReport::chart($db, $scope);
        $out = ['asOf' => $asOf, 'bf' => $bf, 'direct' => $direct];
        if ($account === 'bf') {
            $c = FinancialReport::children($db, $scope, 'bf', ['kind' => 'tb', 'asOf' => $asOf]);
            return $out + ['mode' => 'children', 'crumbs' => [], 'name' => 'Accumulated fund b/f', 'root' => null] + self::childrenShape($c, true, $bf = true);
        }
        $node = $chart['nodes'][$account] ?? null;
        if ($node === null || !$node['classified'] || $node['depth'] < 2) { IncomeStatementReport::fail('Invalid account'); }
        if ($bf && !in_array($node['root'], [1, 2, 5], true)) { IncomeStatementReport::fail('Invalid account'); }
        $crumbs = [];
        foreach (array_reverse($node['chain']) as $cid) {
            if (($chart['nodes'][$cid]['depth'] ?? 0) >= 2) { $crumbs[] = ['id' => $cid, 'name' => self::name($chart['nodes'][$cid]['label'])]; }
        }
        $period = self::period($db, $asOf, $bf);
        $out += ['crumbs' => $crumbs, 'root' => $node['root']];
        if (!$direct && $mode !== 'lines') {
            $c = FinancialReport::children($db, $scope, $account, $period);
            $onlyDirect = count($c['rows']) === 1 && !empty($c['rows'][0]['direct']);
            if ($c['rows'] && !$onlyDirect) { return $out + ['mode' => 'children'] + self::childrenShape($c, false, $bf); }
        }
        $L = FinancialReport::linesPage($db, $scope, $account, $period, !$direct, $page, $pageSize, $search);
        $net = $bf ? -$L['movement'] : ($L['totals']['debit'] - $L['totals']['credit']);   // the trial-balance figure of the node
        $figure = self::cols($bf ? $net : $net);
        return $out + LinesView::shape($L) + ['name' => self::name($node['label']) . ($direct ? ' (postings on this account)' : ''), 'figureDebit' => $figure['debit'], 'figureCredit' => $figure['credit']];
    }

    private static function childrenShape(array $c, bool $syntheticBf, bool $bf): array
    {
        // children() of a bf period answers in the credit direction (surplus positive); the trial balance shows it as a net debit
        $sign = $bf ? -1 : 1;
        $rows = [];
        $dr = 0; $cr = 0;
        foreach ($c['rows'] as $x) {
            $net = $bf ? -(int) $x['amount'] : (int) $x['net'];
            $col = self::cols($net);
            $dr += $col['debitRaw']; $cr += $col['creditRaw'];
            $rows[] = [
                'id' => $x['id'], 'name' => self::name((string) $x['name']), 'debit' => $col['debit'], 'credit' => $col['credit'],
                'debitRaw' => $col['debitRaw'], 'creditRaw' => $col['creditRaw'],
                'direct' => !empty($x['direct']), 'next' => (empty($x['direct']) && !empty($x['hasChildren'])) ? 'children' : 'lines',
            ];
        }
        $nodeNet = $bf ? -(int) $c['amount'] : (int) $c['amount'];
        $nc = self::cols($nodeNet);
        $rowsNet = $bf ? -(int) $c['rowsTotal'] : (int) $c['rowsTotal'];
        $rc = self::cols($rowsNet);
        return [
            'name' => self::name((string) ($c['node']['label'] ?? '')),
            'debit' => $nc['debit'], 'credit' => $nc['credit'], 'debitRaw' => $nc['debitRaw'], 'creditRaw' => $nc['creditRaw'],
            'rows' => $rows, 'rowsDebit' => $rc['debit'], 'rowsCredit' => $rc['credit'], 'rowsDebitRaw' => $rc['debitRaw'], 'rowsCreditRaw' => $rc['creditRaw'],
            'sumsExactly' => $c['sumsExactly'],
        ];
    }

    private static function ddmmyyyy(string $d): string { return date('d-m-Y', strtotime($d)); }

    public static function respondReport(PDO $db, array $scope, string $entity, string $asOf, bool $detailed): void
    {
        $d = self::report($db, $scope, $entity, $asOf, $detailed);
        if (!IncomeStatementReport::wantsXlsx()) { IncomeStatementReport::send($d); }
        XlsxWriter::download('trial-balance-' . ($detailed ? 'detailed-' : '') . $asOf . '.xlsx', 'Trial balance', self::reportSheet($d), [18, 44, 18, 18]);
    }

    public static function respondDrill(PDO $db, array $scope, string $entity, $account, string $asOf, string $mode, bool $direct, bool $bf, array $q): void
    {
        [$page, $size, $search] = LinesView::params($q);
        if (!IncomeStatementReport::wantsXlsx()) { IncomeStatementReport::send(self::drill($db, $scope, $account, $asOf, $mode, $direct, $bf, $page, $size, $search)); }
        $d = self::drill($db, $scope, $account, $asOf, $mode, $direct, $bf, 1, 1, '');
        $heading = array_values(array_filter([$entity, 'Trial balance drill-down: ' . $d['name'] . ($bf ? ' (accumulated fund b/f)' : ''), 'As at ' . self::ddmmyyyy($asOf)], 'strlen'));
        if ($d['mode'] === 'lines') {
            $L = FinancialReport::linesPage($db, $scope, (int) $account, self::period($db, $asOf, $bf), !$direct, 1, 0, $search);
            XlsxWriter::download('trial-balance-lines-' . $account . '.xlsx', 'Journal lines', LinesView::sheetRows($L, $heading), LinesView::widths());
        }
        $rows = [];
        foreach ($heading as $i => $h) { $rows[] = [XlsxWriter::s($h, $i === 0)]; }
        $rows[] = [];
        $rows[] = [XlsxWriter::s('Account', true), XlsxWriter::s('Debit', true), XlsxWriter::s('Credit', true)];
        foreach ($d['rows'] as $r) { $rows[] = [XlsxWriter::s($r['name']), $r['debitRaw'] ? XlsxWriter::m($r['debitRaw']) : null, $r['creditRaw'] ? XlsxWriter::m($r['creditRaw']) : null]; }
        $rows[] = [XlsxWriter::s('Total', true), XlsxWriter::m($d['rowsDebitRaw'], true), XlsxWriter::m($d['rowsCreditRaw'], true)];
        XlsxWriter::download('trial-balance-' . $account . '.xlsx', 'Trial balance', $rows, [44, 18, 18]);
    }

    public static function reportSheet(array $d): array
    {
        $S = function ($t, $b = false) { return XlsxWriter::s($t, $b); };
        $rows = [];
        if ($d['entity'] !== '') { $rows[] = [$S($d['entity'], true)]; }   // parish scope line only (decision 147)
        $rows[] = [$S(($d['detailed'] ? 'Detailed trial balance' : 'Trial balance') . ' as at ' . self::ddmmyyyy($d['asOf']), $d['entity'] === '')];
        $rows[] = [];
        $rows[] = [$S('Section', true), $S('Account', true), $S('Debit', true), $S('Credit', true)];
        foreach ($d['rows'] as $r) {
            $rows[] = [$S($r['section']), $S($r['name']), $r['debitRaw'] ? XlsxWriter::m($r['debitRaw']) : null, $r['creditRaw'] ? XlsxWriter::m($r['creditRaw']) : null];
        }
        $rows[] = [$S('Total', true), null, XlsxWriter::m($d['totals']['debitRaw'], true), XlsxWriter::m($d['totals']['creditRaw'], true)];
        $rows[] = [$S('Difference (debits - credits)', true), null, XlsxWriter::m($d['totals']['debitRaw'] - $d['totals']['creditRaw'], true), null];
        if ($d['unclassified']['present']) { $rows[] = []; $rows[] = [$S($d['unclassified']['message'], true)]; }
        return $rows;
    }

    /** the report endpoint: as-of date (+ detailed=1), JSON or format=xlsx */
    public static function serveReport(PDO $db, array $scope, string $entity): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        self::respondReport($db, $scope, $entity, self::asOf($_GET), ($_GET['detailed'] ?? '') === '1');
    }

    /** the drill endpoint: account ID or 'bf', as-of date, view=lines, direct=1, bf=1, page / pageSize / search, format=xlsx */
    public static function serveDrill(PDO $db, array $scope, string $entity): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        $asOf = self::asOf($_GET);
        $account = ($_GET['account'] ?? '') === 'bf' ? 'bf' : IncomeStatementReport::accountId($_GET['account'] ?? null);
        $mode = ($_GET['view'] ?? '') === 'lines' ? 'lines' : 'children';
        self::respondDrill($db, $scope, $entity, $account, $asOf, $mode, ($_GET['direct'] ?? '') === '1', ($_GET['bf'] ?? '') === '1', $_GET);
    }
}

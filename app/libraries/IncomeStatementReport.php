<?php
/**
 * Income statement screens (task 9.2): shapes FinancialReport::incomeStatement() / children() / lines() into the JSON the
 * report pages and the drill-down page render with textContent, and validates the request. The congregation income statement
 * (Reports) and the parish income statement (Parishreports) share it; only the scope differs.
 *
 * Every endpoint answers JSON; a bad request answers HTTP 400 with {"success":false,"message":...}. URLs carry account IDs only.
 */
class IncomeStatementReport
{
    /** 1,234.50 or, for a negative figure, (1,234.50) */
    public static function bracket(?int $cents): ?string
    {
        if ($cents === null) { return null; }
        $t = FinancialReport::money(abs($cents));
        return $cents < 0 ? '(' . $t . ')' : $t;
    }

    public static function fail(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    public static function send(array $payload): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true] + $payload, JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /** strict Y-m-d start / end, both required, start <= end (HTTP 400 with a clear message otherwise) */
    public static function dates(array $q): array
    {
        $out = [];
        foreach (['start' => 'start date', 'end' => 'end date'] as $k => $label) {
            $v = $q[$k] ?? null;
            if (!is_string($v) || trim($v) === '') { self::fail('Please provide the ' . $label); }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v)) { self::fail('The ' . $label . ' must be a valid date (YYYY-MM-DD)'); }
            $d = DateTime::createFromFormat('!Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) { self::fail('The ' . $label . ' is not a valid calendar date'); }
            $out[$k] = $v;
        }
        if ($out['start'] > $out['end']) { self::fail('The start date cannot be after the end date'); }
        return [$out['start'], $out['end']];
    }

    /** account ID from the request: digits only, a visible non-root income statement account (types 1, 2, 5) */
    public static function accountId($v): int
    {
        if (!is_string($v) || !ctype_digit($v) || strlen($v) > 10 || (int) $v < 1) { self::fail('Invalid account'); }
        return (int) $v;
    }

    /** the congregation name for headings; the parish scope has its own */
    public static function congregationName(PDO $db, int $id): string
    {
        $st = $db->prepare('SELECT CongregationName FROM tblcongregation WHERE ID = ?');
        $st->execute([$id]);
        return (string) ($st->fetchColumn() ?: '');
    }

    /** The parish scope for the request: 0 / missing = every congregation of FinancialReport::parishScope(), else one of them. [scope, heading] */
    public static function parishScope(PDO $db, $cong): array
    {
        $all = FinancialReport::parishScope($db);
        if ($cong === null || $cong === '' || $cong === '0') { return [$all, 'All congregations']; }
        if (!is_string($cong) || !ctype_digit($cong) || strlen($cong) > 10 || !in_array((int) $cong, $all, true)) { self::fail('Invalid congregation'); }
        return [[(int) $cong], self::congregationName($db, (int) $cong)];
    }

    private static function name(string $n): string
    {
        return ucwords($n);
    }

    private static function row(array $r): array
    {
        $direct = !empty($r['direct']);
        $kids = !empty($r['hasChildren']);
        return [
            'id'        => (int) $r['id'],
            'name'      => self::name((string) $r['name']),
            'amount'    => self::bracket($r['amount']),
            'cmp'       => self::bracket($r['comparative']),
            'amountRaw' => $r['amount'],
            'cmpRaw'    => $r['comparative'],
            'direct'    => $direct,
            // children = another level of accounts; lines = the journal lines
            'next'      => (!$direct && $kids) ? 'children' : 'lines',
        ];
    }

    private static function section(array $s): array
    {
        return ['title' => $s['title'], 'rows' => array_map([self::class, 'row'], $s['rows']),
                'total' => self::bracket($s['total']), 'cmpTotal' => self::bracket($s['comparativeTotal']), 'totalRaw' => $s['total'], 'cmpTotalRaw' => $s['comparativeTotal'], 'sumsExactly' => $s['sumsExactly']];
    }

    /** The statement: Income, Total income, Expenditure, Total expenditure, Surplus, and type 5 lines only when non-zero. */
    public static function report(PDO $db, array $scope, string $entity, string $start, string $end): array
    {
        $r = FinancialReport::incomeStatement($db, $scope, $start, $end, true);
        $income = self::section($r['sections']['income']);
        $income['title'] = 'Income';
        $expense = self::section($r['sections']['expense']);
        $expense['title'] = 'Expenditure';
        $div = null;
        $final = null;
        if ($r['dividends'] !== null) {
            $div = self::section($r['dividends']);
            $div['title'] = 'Dividends';
            $final = ['amount' => self::bracket($r['surplusAfterDividends']['amount']), 'cmp' => self::bracket($r['surplusAfterDividends']['comparative']), 'amountRaw' => $r['surplusAfterDividends']['amount'], 'cmpRaw' => $r['surplusAfterDividends']['comparative']];
        }
        $u = [];
        foreach ($r['unclassified']['rows'] as $x) { $u[] = ['id' => $x['id'], 'name' => self::name($x['name']), 'amount' => self::bracket($x['net'])]; }
        return [
            'entity'       => $entity,
            'start'        => $start,
            'end'          => $end,
            'cmpStart'     => $r['comparative']['from'] ?? null,
            'cmpEnd'       => $r['comparative']['to'] ?? null,
            'income'       => $income,
            'expense'      => $expense,
            'surplus'      => ['amount' => self::bracket($r['surplus']['amount']), 'cmp' => self::bracket($r['surplus']['comparative']), 'amountRaw' => $r['surplus']['amount'], 'cmpRaw' => $r['surplus']['comparative']],
            'dividends'    => $div,
            'final'        => $final,
            'unclassified' => ['present' => $r['unclassified']['present'], 'message' => $r['unclassified']['message'], 'rows' => $u],
            'checksOk'     => !array_filter($r['checks'], function ($c) { return !$c['ok']; }),
        ];
    }

    /**
     * One drill level for an account ID: the next level of accounts (rows add up to the figure clicked) or the journal lines.
     * $mode: 'children' (default when there is a next level) or 'lines'; $direct = only the account's own postings.
     */
    public static function drill(PDO $db, array $scope, int $accountId, string $start, string $end, string $mode, bool $direct, int $page = 1, int $pageSize = 50, string $search = ''): array
    {
        $chart = FinancialReport::chart($db, $scope);
        $node = $chart['nodes'][$accountId] ?? null;
        if ($node === null || !$node['classified'] || $node['depth'] < 2 || !in_array($node['root'], [1, 2, 5], true)) {
            self::fail('Invalid account');
        }
        $period = ['kind' => 'is', 'from' => $start, 'to' => $end, 'comparative' => true];
        $crumbs = [];
        foreach (array_reverse($node['chain']) as $cid) {
            if (($chart['nodes'][$cid]['depth'] ?? 0) >= 2) { $crumbs[] = ['id' => $cid, 'name' => self::name($chart['nodes'][$cid]['name'])]; }
        }
        $out = ['crumbs' => $crumbs, 'start' => $start, 'end' => $end, 'direct' => $direct, 'root' => $node['root']];

        if (!$direct && $mode !== 'lines') {
            $c = FinancialReport::children($db, $scope, $accountId, $period);
            // a leaf shows only its own postings as one 'direct' row: that is the journal lines, not another level
            $onlyDirect = count($c['rows']) === 1 && !empty($c['rows'][0]['direct']);
            if ($c['rows'] && !$onlyDirect) {
                return $out + [
                    'mode'         => 'children',
                    'name'         => self::name($c['node']['name']),
                    'amount'       => self::bracket($c['amount']),
                    'cmp'          => self::bracket($c['comparative']),
                    'cmpStart'     => $c['period']['comparative'] ? FinancialReport::samePeriodLastYear($start, $end)[0] : null,
                    'cmpEnd'       => $c['period']['comparative'] ? FinancialReport::samePeriodLastYear($start, $end)[1] : null,
                    'rows'         => array_map([self::class, 'row'], $c['rows']),
                    'rowsTotal'    => self::bracket($c['rowsTotal']),
                    'rowsTotalRaw' => $c['rowsTotal'],
                    'sumsExactly'  => $c['sumsExactly'],
                ];
            }
        }
        // journal lines: the whole subtree, or only the postings on the account itself (server-side search and paging)
        $L = FinancialReport::linesPage($db, $scope, $accountId, $period, !$direct, $page, $pageSize, $search);
        return $out + LinesView::shape($L) + ['name' => self::name($node['name']) . ($direct ? ' (postings on this account)' : '')];
    }

    /** GET parameters of the export: format=xlsx */
    public static function wantsXlsx(): bool
    {
        return ($_GET['format'] ?? '') === 'xlsx';
    }

    /** JSON or, with format=xlsx, the Excel workbook of the statement */
    public static function respondReport(PDO $db, array $scope, string $entity, string $start, string $end): void
    {
        $d = self::report($db, $scope, $entity, $start, $end);
        if (!self::wantsXlsx()) { self::send($d); }
        XlsxWriter::download('income-statement-' . $start . '-to-' . $end . '.xlsx', 'Income statement', self::reportSheet($d), [44, 20, 28]);
    }

    /**
     * One drill level as JSON or Excel. Excel carries every row: the accounts of the level, or all the journal lines of the view
     * (those matching `search`, whatever the page).
     */
    public static function respondDrill(PDO $db, array $scope, string $entity, int $account, string $start, string $end, string $mode, bool $direct, array $q): void
    {
        [$page, $size, $search] = LinesView::params($q);
        if (!self::wantsXlsx()) { self::send(self::drill($db, $scope, $account, $start, $end, $mode, $direct, $page, $size, $search)); }
        $d = self::drill($db, $scope, $account, $start, $end, $mode, $direct, 1, 1, '');   // decides children or lines
        $heading = [$entity, 'Income statement drill-down: ' . $d['name'], 'Period ' . self::ddmmyyyy($start) . ' to ' . self::ddmmyyyy($end)];
        if ($d['mode'] === 'lines') {
            $L = FinancialReport::linesPage($db, $scope, $account, ['kind' => 'is', 'from' => $start, 'to' => $end, 'comparative' => true], !$direct, 1, 0, $search);
            XlsxWriter::download('income-statement-lines-' . $account . '.xlsx', 'Journal lines', LinesView::sheetRows($L, $heading), LinesView::widths());
        }
        $rows = [];
        foreach ($heading as $i => $h) { $rows[] = [XlsxWriter::s($h, $i === 0)]; }
        $rows[] = [];
        $rows[] = [XlsxWriter::s('Account', true), XlsxWriter::s('Amount', true), XlsxWriter::s('Last year', true)];
        foreach ($d['rows'] as $r) { $rows[] = [XlsxWriter::s($r['name']), XlsxWriter::m($r['amountRaw']), XlsxWriter::m($r['cmpRaw'])]; }
        $rows[] = [XlsxWriter::s('Total', true), XlsxWriter::m($d['rowsTotalRaw'], true), null];
        XlsxWriter::download('income-statement-' . $account . '.xlsx', 'Income statement', $rows, [44, 20, 20]);
    }

    private static function ddmmyyyy(string $d): string { return date('d-m-Y', strtotime($d)); }

    /** the statement as a worksheet: headings, period labels, lines, totals, comparative column (real numbers) */
    public static function reportSheet(array $d): array
    {
        $S = function ($t, $b = false) { return XlsxWriter::s($t, $b); };
        $rows = [
            [$S($d['entity'], true)],
            [$S('Income statement for the period ' . self::ddmmyyyy($d['start']) . ' to ' . self::ddmmyyyy($d['end']))],
            [],
            [$S('Account', true), $S(self::ddmmyyyy($d['start']) . ' - ' . self::ddmmyyyy($d['end']), true), $S($d['cmpStart'] ? self::ddmmyyyy($d['cmpStart']) . ' - ' . self::ddmmyyyy($d['cmpEnd']) . ' (last year)' : 'Last year', true)],
        ];
        $sec = function ($s) use (&$rows, $S) {
            $rows[] = [$S($s['title'], true)];
            foreach ($s['rows'] as $r) { $rows[] = [$S($r['name']), XlsxWriter::m($r['amountRaw']), XlsxWriter::m($r['cmpRaw'])]; }
            $rows[] = [$S('Total ' . strtolower($s['title']), true), XlsxWriter::m($s['totalRaw'], true), XlsxWriter::m($s['cmpTotalRaw'], true)];
        };
        $sec($d['income']);
        $sec($d['expense']);
        $rows[] = [$S('Surplus / (deficit) for the period', true), XlsxWriter::m($d['surplus']['amountRaw'], true), XlsxWriter::m($d['surplus']['cmpRaw'], true)];
        if ($d['dividends']) {
            $sec($d['dividends']);
            $rows[] = [$S('Surplus / (deficit) after dividends', true), XlsxWriter::m($d['final']['amountRaw'], true), XlsxWriter::m($d['final']['cmpRaw'], true)];
        }
        if ($d['unclassified']['present']) {
            $rows[] = [];
            $rows[] = [$S($d['unclassified']['message'], true)];
            foreach ($d['unclassified']['rows'] as $u) { $rows[] = [$S($u['name'] . ' (account ' . $u['id'] . ')'), $S($u['amount'])]; }
        }
        return $rows;
    }
}

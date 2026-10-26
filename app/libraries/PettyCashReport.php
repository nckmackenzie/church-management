<?php
/** Petty cash book from the journal (task 9.6, decisions 63 / 126). Read-only. */
class PettyCashReport
{
    /** Report accounts: only fixed petty-cash accounts that are visible PAY_CASH accounts for this congregation. */
    public static function accounts(PDO $db, int $cong): array
    {
        $out = [];
        foreach (FinancialReport::accountOptions($db, [$cong], ['roots' => [3], 'banks' => false]) as $a) {
            if (in_array($a['id'], Ledger::PETTY_CASH_ACCOUNTS, true)) { $out[] = ['id' => $a['id'], 'name' => FinancialReport::title($a['label'])]; }
        }
        return $out;
    }

    public static function accountAllowed(PDO $db, int $cong, int $account): bool
    {
        foreach (self::accounts($db, $cong) as $a) { if ($a['id'] === $account) { return true; } }
        return false;
    }

    private static function source(array $row, ?string $number): string
    {
        $type = trim((string) $row['typeName']);
        $number = trim((string) $number);
        return $type . ($number !== '' ? ' ' . $number : '');
    }

    private static function entryNumber(array $row): string
    {
        return $row['journalNo'] === null ? 'Entry ' . $row['entryId'] : 'Journal ' . $row['journalNo'];
    }

    private static function matches(array $r, string $search): bool
    {
        if ($search === '') { return true; }
        $amounts = [];
        foreach (['debit', 'credit', 'balance'] as $k) {
            $amounts[] = FinancialReport::decimal((int) $r[$k]);
            $amounts[] = FinancialReport::money((int) $r[$k]);
        }
        $text = implode(' ', [
            $r['date'], date('d-m-Y', strtotime($r['date'])), self::entryNumber($r), $r['source'], $r['reference'], $r['memo'],
            implode(' ', $amounts),
        ]);
        return mb_stripos($text, $search) !== false;
    }

    /**
     * One searched/page slice. FinancialReport supplies every figure and true running balance; only source metadata and the
     * final server-side search/page slice are added here so source document numbers are searchable too.
     */
    public static function data(PDO $db, array $scope, int $account, string $from, string $to, int $page = 1, int $pageSize = 50, string $search = ''): array
    {
        $period = ['kind' => 'range', 'from' => $from, 'to' => $to, 'direction' => 'debit'];
        $L = FinancialReport::linesPage($db, $scope, $account, $period, false, 1, 0, '');
        $numbers = FinancialReport::sourceNumbers($db, $scope, array_column($L['rows'], 'entryId'));
        foreach ($L['rows'] as &$r) {
            $r['sourceNo'] = $numbers[$r['entryId']] ?? null;
            $r['source'] = self::source($r, $r['sourceNo']);
            $r['entryNumber'] = self::entryNumber($r);
        }
        unset($r);
        $search = trim($search);
        $matched = array_values(array_filter($L['rows'], function ($r) use ($search) { return self::matches($r, $search); }));
        $fdr = 0; $fcr = 0;
        foreach ($matched as $r) { $fdr += $r['debit']; $fcr += $r['credit']; }
        $pageSize = $pageSize <= 0 ? 0 : min($pageSize, FinancialReport::MAX_PAGE_SIZE);
        $page = max(1, $page);
        $pages = $pageSize > 0 ? max(1, (int) ceil(count($matched) / $pageSize)) : 1;
        if ($page > $pages) { $page = $pages; }
        $L['rows'] = $pageSize > 0 ? array_slice($matched, ($page - 1) * $pageSize, $pageSize) : $matched;
        $L['matching'] = count($matched);
        $L['search'] = $search;
        $L['filtered'] = ['debit' => $fdr, 'credit' => $fcr];
        $L['page'] = $page; $L['pageSize'] = $pageSize; $L['pages'] = $pages;
        return $L;
    }

    public static function shape(array $L): array
    {
        $rows = [];
        foreach ($L['rows'] as $r) {
            $rows[] = [
                'date' => date('d-m-Y', strtotime($r['date'])), 'entry' => $r['entryNumber'], 'source' => $r['source'],
                'reference' => $r['reference'], 'narration' => $r['memo'],
                'receipt' => $r['debit'] ? IncomeStatementReport::bracket($r['debit']) : '',
                'payment' => $r['credit'] ? IncomeStatementReport::bracket($r['credit']) : '',
                'balance' => IncomeStatementReport::bracket($r['balance']),
                'link' => $r['link'] === null ? null : URLROOT . '/' . $r['link'],
            ];
        }
        return [
            'rows' => $rows, 'opening' => IncomeStatementReport::bracket($L['opening']), 'closing' => IncomeStatementReport::bracket($L['closing']),
            'receipts' => IncomeStatementReport::bracket($L['totals']['debit']), 'payments' => IncomeStatementReport::bracket($L['totals']['credit']),
            'filteredReceipts' => IncomeStatementReport::bracket($L['filtered']['debit']), 'filteredPayments' => IncomeStatementReport::bracket($L['filtered']['credit']),
            'count' => $L['count'], 'matching' => $L['matching'], 'search' => $L['search'], 'page' => $L['page'], 'pageSize' => $L['pageSize'], 'pages' => $L['pages'],
        ];
    }

    public static function sheetRows(array $L, string $name, string $from, string $to): array
    {
        $rows = [[XlsxWriter::s('Petty cash book: ' . $name, true)], [XlsxWriter::s('Period ' . date('d-m-Y', strtotime($from)) . ' to ' . date('d-m-Y', strtotime($to)))], []];
        $rows[] = array_map(function ($v) { return XlsxWriter::s($v, true); }, ['Date', 'Entry / journal no', 'Source', 'Reference', 'Narration', 'Receipts', 'Payments', 'Running balance']);
        $rows[] = [XlsxWriter::s('Opening balance', true), null, null, null, null, null, null, XlsxWriter::m($L['opening'], true)];
        foreach ($L['rows'] as $r) {
            $rows[] = [XlsxWriter::d($r['date']), XlsxWriter::s($r['entryNumber']), XlsxWriter::s($r['source']), XlsxWriter::s($r['reference']), XlsxWriter::s($r['memo']),
                       $r['debit'] ? XlsxWriter::m($r['debit']) : null, $r['credit'] ? XlsxWriter::m($r['credit']) : null, XlsxWriter::m($r['balance'])];
        }
        $rows[] = [XlsxWriter::s($L['search'] === '' ? 'Total of the period' : 'Total of matching rows', true), null, null, null, null,
                   XlsxWriter::m($L['filtered']['debit'], true), XlsxWriter::m($L['filtered']['credit'], true), null];
        $rows[] = [XlsxWriter::s('Closing balance', true), null, null, null, null, null, null, XlsxWriter::m($L['closing'], true)];
        return $rows;
    }

    public static function serve(PDO $db, array $scope, int $account): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        [$from, $to] = IncomeStatementReport::dates(['start' => $_GET['from'] ?? null, 'end' => $_GET['to'] ?? null]);
        [$page, $size, $search] = LinesView::params($_GET);
        $chart = FinancialReport::chart($db, $scope);
        $node = $chart['nodes'][$account] ?? null;
        if ($node === null) { IncomeStatementReport::fail('Invalid petty cash account'); }
        $name = FinancialReport::title($node['label']);
        if (IncomeStatementReport::wantsXlsx()) {
            $L = self::data($db, $scope, $account, $from, $to, 1, 0, $search);
            XlsxWriter::download('petty-cash-' . $account . '-' . $from . '-to-' . $to . '.xlsx', 'Petty cash', self::sheetRows($L, $name, $from, $to), [12, 20, 26, 20, 40, 15, 15, 17]);
        }
        $L = self::data($db, $scope, $account, $from, $to, $page, $size, $search);
        IncomeStatementReport::send(self::shape($L) + ['account' => $account, 'name' => $name, 'from' => $from, 'to' => $to]);
    }
}

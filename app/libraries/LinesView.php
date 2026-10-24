<?php
/**
 * Journal-line lists for the drill-down pages and the ledger statement (task 9.3, decisions 141-142): request parameters
 * (page, pageSize, search), the JSON shape of one page, and the rows of the Excel export. The paging, the search and the running
 * balance are done by FinancialReport::linesPage() on the server.
 */
class LinesView
{
    const DEFAULT_PAGE_SIZE = 50;

    /** page, pageSize (1..FinancialReport::MAX_PAGE_SIZE) and search from a query array; a bad value is HTTP 400 */
    public static function params(array $q): array
    {
        $page = 1; $size = self::DEFAULT_PAGE_SIZE; $search = '';
        if (isset($q['page'])) {
            if (!is_string($q['page']) || !ctype_digit($q['page']) || strlen($q['page']) > 7 || (int) $q['page'] < 1) { IncomeStatementReport::fail('Invalid page'); }
            $page = (int) $q['page'];
        }
        if (isset($q['pageSize'])) {
            if (!is_string($q['pageSize']) || !ctype_digit($q['pageSize']) || (int) $q['pageSize'] < 1 || (int) $q['pageSize'] > FinancialReport::MAX_PAGE_SIZE) { IncomeStatementReport::fail('The page size must be between 1 and ' . FinancialReport::MAX_PAGE_SIZE); }
            $size = (int) $q['pageSize'];
        }
        if (isset($q['search'])) {
            if (!is_string($q['search']) || mb_strlen($q['search']) > 100) { IncomeStatementReport::fail('Invalid search text'); }
            $search = trim($q['search']);
        }
        return [$page, $size, $search];
    }

    private static function name(string $n): string { return FinancialReport::title($n); }

    /** one page as JSON-ready data (money as text, negatives in brackets) */
    public static function shape(array $L): array
    {
        $rows = [];
        foreach ($L['rows'] as $x) {
            $rows[] = [
                'date'      => date('d-m-Y', strtotime($x['date'])),
                'entryId'   => $x['entryId'],
                'journalNo' => $x['journalNo'],
                'reference' => $x['reference'],
                'memo'      => $x['memo'],
                'type'      => $x['typeName'],
                'account'   => self::name($x['accountName']),
                'debit'     => $x['debit'] ? IncomeStatementReport::bracket($x['debit']) : '',
                'credit'    => $x['credit'] ? IncomeStatementReport::bracket($x['credit']) : '',
                'balance'   => IncomeStatementReport::bracket($x['balance']),
                'link'      => $x['link'] === null ? null : URLROOT . '/' . $x['link'],
                // financial year of the row and of the row before it (income / expense / dividend accounts restart each year, decision 144)
                'fy'        => $x['fy'] ?? null,
                'prevFy'    => $x['prevFy'] ?? null,
            ];
        }
        return [
            'mode'       => 'lines',
            'rows'       => $rows,
            'direction'  => $L['direction'] === 'CREDIT' ? 'Cr' : 'Dr',
            'opening'    => IncomeStatementReport::bracket($L['opening']),
            'closing'    => IncomeStatementReport::bracket($L['closing']),
            'movement'   => IncomeStatementReport::bracket($L['movement']),
            'debits'     => IncomeStatementReport::bracket($L['totals']['debit']),
            'credits'    => IncomeStatementReport::bracket($L['totals']['credit']),
            'count'      => $L['count'],
            'matching'   => $L['matching'],
            'search'     => $L['search'],
            'filteredDebits'  => IncomeStatementReport::bracket($L['filtered']['debit']),
            'filteredCredits' => IncomeStatementReport::bracket($L['filtered']['credit']),
            'page'       => $L['page'],
            'pageSize'   => $L['pageSize'],
            'pages'      => $L['pages'],
        ];
    }

    /**
     * Excel rows for a complete (unpaged) list: heading lines, opening balance, every row with real numbers, totals, closing.
     * @param string[] $heading text lines above the table
     */
    public static function sheetRows(array $L, array $heading, bool $withAccount = true): array
    {
        $X = 'XlsxWriter';
        $rows = [];
        $heading = array_values(array_filter($heading, 'strlen'));   // no congregation heading on congregation reports (decision 147)
        foreach ($heading as $i => $h) { $rows[] = [XlsxWriter::s($h, $i === 0)]; }
        $dir = $L['direction'] === 'CREDIT' ? 'Cr' : 'Dr';
        $rows[] = [];
        $head = ['Date', 'Entry no', 'Journal no', 'Reference', 'Narration', 'Type'];
        if ($withAccount) { $head[] = 'Account'; }
        array_push($head, 'Debit', 'Credit', "Balance ($dir)");
        $rows[] = array_map(function ($t) { return XlsxWriter::s($t, true); }, $head);
        $pad = count($head) - 3;
        $rows[] = array_merge([XlsxWriter::s('Opening balance', true)], array_fill(0, $pad - 1, null), [null, null, XlsxWriter::m($L['opening'], true)]);
        foreach ($L['rows'] as $x) {
            $r = [XlsxWriter::d($x['date']), XlsxWriter::n($x['entryId']), XlsxWriter::n($x['journalNo']), XlsxWriter::s($x['reference']), XlsxWriter::s($x['memo']), XlsxWriter::s($x['typeName'])];
            if ($withAccount) { $r[] = XlsxWriter::s(self::name($x['accountName'])); }
            array_push($r, $x['debit'] ? XlsxWriter::m($x['debit']) : null, $x['credit'] ? XlsxWriter::m($x['credit']) : null, XlsxWriter::m($x['balance']));
            $rows[] = $r;
        }
        $rows[] = array_merge([XlsxWriter::s('Total', true)], array_fill(0, $pad - 1, null), [XlsxWriter::m($L['filtered']['debit'], true), XlsxWriter::m($L['filtered']['credit'], true), null]);
        $rows[] = array_merge([XlsxWriter::s('Closing balance', true)], array_fill(0, $pad - 1, null), [null, null, XlsxWriter::m($L['closing'], true)]);
        return $rows;
    }

    public static function widths(bool $withAccount = true): array
    {
        return $withAccount ? [12, 10, 10, 18, 40, 20, 28, 15, 15, 16] : [12, 10, 10, 18, 40, 20, 15, 15, 16];
    }
}

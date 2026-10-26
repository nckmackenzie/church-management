<?php
/**
 * A searchable, paged, exportable list of transactions (task 9.7, decisions 141-142): the group income statement drill-down and the
 * contribution / expense reports share it. The caller supplies the columns and ALL rows (raw values); this class does the search,
 * the paging, the totals, the JSON shape and the Excel workbook of every matching row.
 *
 * Column: ['key' => 'date', 'label' => 'Date', 'type' => 'text' | 'date' | 'money' | 'link']
 *   text   a string
 *   date   'Y-m-d'
 *   money  integer cents
 *   link   a string; the row's `link` (route relative to URLROOT) makes it a link on screen
 * Row: ['date' => '2026-10-01', ..., 'link' => 'contributions/edit/12' | null]
 *
 * Spec: 'title' (screen / sheet title), 'lines' (text lines under the title, e.g. the period), 'columns', 'total' (key of the money
 * column to total, or null), 'file' (download name without extension), 'sheet' (sheet name), 'widths' (Excel column widths).
 */
class ReportList
{
    private static function display(array $col, $v): string
    {
        if ($v === null) { return ''; }
        switch ($col['type']) {
            case 'date':  return date('d-m-Y', strtotime((string) $v));
            case 'money': return IncomeStatementReport::bracket((int) $v);
            default:      return (string) $v;
        }
    }

    private static function matches(array $columns, array $row, string $search): bool
    {
        if ($search === '') { return true; }
        $parts = [];
        foreach ($columns as $c) {
            $v = $row[$c['key']] ?? null;
            if ($v === null) { continue; }
            $parts[] = self::display($c, $v);
            if ($c['type'] === 'date') { $parts[] = (string) $v; }
            if ($c['type'] === 'money') { $parts[] = FinancialReport::decimal((int) $v); $parts[] = FinancialReport::money((int) $v); }
        }
        return mb_stripos(implode(' ', $parts), $search) !== false;
    }

    /** matching rows, their total and the page asked for (pageSize 0 = every row) */
    public static function slice(array $spec, array $rows, int $page, int $pageSize, string $search): array
    {
        $matched = array_values(array_filter($rows, function ($r) use ($spec, $search) { return self::matches($spec['columns'], $r, $search); }));
        $total = 0;
        if ($spec['total']) { foreach ($matched as $r) { $total += (int) ($r[$spec['total']] ?? 0); } }
        $pageSize = $pageSize <= 0 ? 0 : min($pageSize, FinancialReport::MAX_PAGE_SIZE);
        $pages = $pageSize > 0 ? max(1, (int) ceil(count($matched) / $pageSize)) : 1;
        $page = min(max(1, $page), $pages);
        return [
            'rows' => $pageSize > 0 ? array_slice($matched, ($page - 1) * $pageSize, $pageSize) : $matched,
            'count' => count($rows), 'matching' => count($matched), 'total' => $total,
            'page' => $page, 'pageSize' => $pageSize, 'pages' => $pages,
        ];
    }

    /** JSON-ready data (money as text, negatives in brackets); every string is escaped by the client's textContent */
    public static function shape(array $spec, array $s, string $search): array
    {
        $rows = [];
        foreach ($s['rows'] as $r) {
            $o = [];
            foreach ($spec['columns'] as $c) { $o[$c['key']] = self::display($c, $r[$c['key']] ?? null); }
            $o['link'] = !empty($r['link']) ? URLROOT . '/' . $r['link'] : null;
            $rows[] = $o;
        }
        return [
            'title' => $spec['title'], 'lines' => array_values($spec['lines'] ?? []),
            'columns' => array_map(function ($c) { return ['key' => $c['key'], 'label' => $c['label'], 'type' => $c['type']]; }, $spec['columns']),
            'rows' => $rows, 'count' => $s['count'], 'matching' => $s['matching'], 'search' => $search,
            'total' => $spec['total'] ? IncomeStatementReport::bracket($s['total']) : null, 'totalKey' => $spec['total'],
            'page' => $s['page'], 'pageSize' => $s['pageSize'], 'pages' => $s['pages'],
        ];
    }

    /** the worksheet rows: title, lines, header, EVERY matching row with real numbers, total */
    public static function sheetRows(array $spec, array $s, string $search): array
    {
        $rows = [[XlsxWriter::s($spec['title'], true)]];
        foreach (array_filter($spec['lines'] ?? [], 'strlen') as $line) { $rows[] = [XlsxWriter::s($line)]; }
        if ($search !== '') { $rows[] = [XlsxWriter::s('Search: ' . $search)]; }
        $rows[] = [];
        $rows[] = array_map(function ($c) { return XlsxWriter::s($c['label'], true); }, $spec['columns']);
        foreach ($s['rows'] as $r) {
            $line = [];
            foreach ($spec['columns'] as $c) {
                $v = $r[$c['key']] ?? null;
                if ($c['type'] === 'money') { $line[] = $v === null ? null : XlsxWriter::m((int) $v); }
                elseif ($c['type'] === 'date') { $line[] = XlsxWriter::d($v); }
                else { $line[] = XlsxWriter::s($v ?? ''); }
            }
            $rows[] = $line;
        }
        if ($spec['total']) {
            $line = [];
            foreach ($spec['columns'] as $i => $c) {
                if ($c['key'] === $spec['total']) { $line[] = XlsxWriter::m($s['total'], true); }
                else { $line[] = $i === 0 ? XlsxWriter::s($search === '' ? 'Total' : 'Total of matching rows', true) : null; }
            }
            $rows[] = $line;
        }
        return $rows;
    }

    /** answer the request: JSON of one page (page, pageSize, search) or, with format=xlsx, the workbook of every matching row */
    public static function respond(array $spec, array $rows, array $q): void
    {
        [$page, $size, $search] = LinesView::params($q);
        if (IncomeStatementReport::wantsXlsx()) {
            $s = self::slice($spec, $rows, 1, 0, $search);
            XlsxWriter::download($spec['file'] . '.xlsx', $spec['sheet'] ?? 'Report', self::sheetRows($spec, $s, $search), $spec['widths'] ?? []);
        }
        $s = self::slice($spec, $rows, $page, $size, $search);
        IncomeStatementReport::send(self::shape($spec, $s, $search));
    }
}

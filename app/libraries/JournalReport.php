<?php
/**
 * Journal report from the journal (task 9.7): the active manual journals (journal_entries type 5) of the session congregation in a
 * date range, one block per journal with its lines (account name, debit, credit, memo) and the totals of the block. Read-only.
 * Search (journal number, date, reference, narration, memo, account, amount) and paging work on whole journals, so a block is never
 * split and its debits always equal its credits; the Excel workbook holds every matching journal.
 */
class JournalReport
{
    const TYPE_JOURNAL = 5;

    /** all journals of the period, each with its lines and totals (integer cents) */
    public static function journals(PDO $db, int $cong, string $from, string $to): array
    {
        $chart = FinancialReport::chart($db, [$cong]);
        $st = $db->prepare('SELECT e.ID, e.entryDate, e.journalNo, e.reference, e.description, l.lineNumber, l.accountId, l.dc, l.amount, l.memo,
                                   a.accountType AS fallbackName
                            FROM journal_entries e
                            JOIN journal_lines l ON l.journalEntryId = e.ID
                            LEFT JOIN tblaccounttypes a ON a.ID = l.accountId
                            WHERE e.congregationId = ? AND e.deleted = 0 AND e.transactionTypeId = ' . self::TYPE_JOURNAL . ' AND e.entryDate BETWEEN ? AND ?
                            ORDER BY e.entryDate, e.journalNo, e.ID, l.lineNumber');
        $st->execute([$cong, $from, $to]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) $r['ID'];
            if (!isset($out[$id])) {
                $out[$id] = ['id' => $id, 'journalNo' => $r['journalNo'] === null ? null : (int) $r['journalNo'], 'date' => $r['entryDate'],
                             'reference' => (string) ($r['reference'] ?? ''), 'description' => (string) ($r['description'] ?? ''),
                             'lines' => [], 'debits' => 0, 'credits' => 0];
            }
            $cents = FinancialReport::cents($r['amount']);
            $debit = $r['dc'] === 'DEBIT';
            $name = isset($chart['nodes'][(int) $r['accountId']]) ? $chart['nodes'][(int) $r['accountId']]['label'] : (string) $r['fallbackName'];
            $out[$id]['lines'][] = ['account' => FinancialReport::title($name), 'debit' => $debit ? $cents : 0, 'credit' => $debit ? 0 : $cents, 'memo' => (string) ($r['memo'] ?? '')];
            $out[$id][$debit ? 'debits' : 'credits'] += $cents;
        }
        return array_values($out);
    }

    private static function text(array $j): string
    {
        $p = [(string) $j['journalNo'], $j['date'], date('d-m-Y', strtotime($j['date'])), $j['reference'], $j['description']];
        foreach ($j['lines'] as $l) {
            array_push($p, $l['account'], $l['memo']);
            foreach (['debit', 'credit'] as $k) { if ($l[$k]) { array_push($p, FinancialReport::decimal($l[$k]), FinancialReport::money($l[$k])); } }
        }
        return implode(' ', $p);
    }

    /** the journals matching `search`, and the page asked for (pageSize 0 = all) */
    public static function slice(array $journals, int $page, int $pageSize, string $search): array
    {
        $matched = array_values(array_filter($journals, function ($j) use ($search) { return $search === '' || mb_stripos(self::text($j), $search) !== false; }));
        $dr = 0; $cr = 0;
        foreach ($matched as $j) { $dr += $j['debits']; $cr += $j['credits']; }
        $pageSize = $pageSize <= 0 ? 0 : min($pageSize, FinancialReport::MAX_PAGE_SIZE);
        $pages = $pageSize > 0 ? max(1, (int) ceil(count($matched) / $pageSize)) : 1;
        $page = min(max(1, $page), $pages);
        return ['journals' => $pageSize > 0 ? array_slice($matched, ($page - 1) * $pageSize, $pageSize) : $matched,
                'count' => count($journals), 'matching' => count($matched), 'debits' => $dr, 'credits' => $cr, 'page' => $page, 'pageSize' => $pageSize, 'pages' => $pages];
    }

    public static function shape(array $s, string $from, string $to, string $search): array
    {
        $b = function (int $c) { return IncomeStatementReport::bracket($c); };
        $js = [];
        foreach ($s['journals'] as $j) {
            $js[] = [
                'id' => $j['id'], 'journalNo' => $j['journalNo'], 'date' => date('d-m-Y', strtotime($j['date'])), 'reference' => $j['reference'], 'description' => $j['description'],
                'lines' => array_map(function ($l) use ($b) { return ['account' => $l['account'], 'debit' => $l['debit'] ? $b($l['debit']) : '', 'credit' => $l['credit'] ? $b($l['credit']) : '', 'memo' => $l['memo']]; }, $j['lines']),
                'debits' => $b($j['debits']), 'credits' => $b($j['credits']), 'balanced' => $j['debits'] === $j['credits'],
            ];
        }
        return ['from' => $from, 'to' => $to, 'journals' => $js, 'count' => $s['count'], 'matching' => $s['matching'], 'search' => $search,
                'debits' => $b($s['debits']), 'credits' => $b($s['credits']), 'page' => $s['page'], 'pageSize' => $s['pageSize'], 'pages' => $s['pages']];
    }

    public static function sheetRows(array $s, string $from, string $to, string $search): array
    {
        $S = function ($t, $bold = false) { return XlsxWriter::s($t, $bold); };
        $rows = [[$S('Journal report', true)], [$S('Period ' . date('d-m-Y', strtotime($from)) . ' to ' . date('d-m-Y', strtotime($to)))]];
        if ($search !== '') { $rows[] = [$S('Search: ' . $search)]; }
        $rows[] = [];
        $rows[] = array_map(function ($t) use ($S) { return $S($t, true); }, ['Journal no', 'Date', 'Reference', 'Description', 'Account', 'Debit', 'Credit', 'Memo']);
        foreach ($s['journals'] as $j) {
            foreach ($j['lines'] as $l) {
                $rows[] = [XlsxWriter::n($j['journalNo']), XlsxWriter::d($j['date']), $S($j['reference']), $S($j['description']), $S($l['account']),
                           $l['debit'] ? XlsxWriter::m($l['debit']) : null, $l['credit'] ? XlsxWriter::m($l['credit']) : null, $S($l['memo'])];
            }
            $rows[] = [$S('Journal ' . $j['journalNo'] . ' total', true), null, null, null, null, XlsxWriter::m($j['debits'], true), XlsxWriter::m($j['credits'], true), null];
        }
        $rows[] = [$S($search === '' ? 'Total' : 'Total of matching journals', true), null, null, null, null, XlsxWriter::m($s['debits'], true), XlsxWriter::m($s['credits'], true), null];
        return $rows;
    }

    public static function serve(PDO $db, int $cong): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        [$from, $to] = IncomeStatementReport::dates(['start' => $_GET['from'] ?? null, 'end' => $_GET['to'] ?? null]);
        [$page, $size, $search] = LinesView::params($_GET);
        $all = self::journals($db, $cong, $from, $to);
        if (IncomeStatementReport::wantsXlsx()) {
            XlsxWriter::download('journals-' . $from . '-to-' . $to . '.xlsx', 'Journals', self::sheetRows(self::slice($all, 1, 0, $search), $from, $to, $search), [12, 12, 20, 34, 34, 15, 15, 34]);
        }
        IncomeStatementReport::send(self::shape(self::slice($all, $page, $size, $search), $from, $to, $search));
    }
}

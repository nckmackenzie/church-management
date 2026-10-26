<?php
/**
 * Group (and district) income statement from the journal (task 9.7, decision 155). Read-only.
 *
 * The categories are the ones the report always had; only the source of every amount changed. Each source record is matched to its
 * ACTIVE journal entry and the amount comes from that entry, never from the source row:
 *   Requisitions  tblfundrequisition (approved, DontDeduct = 1)   entry type 12, total of the entry (its debit side)
 *   Collections   tblmmf rows with a debit
 *                   TransactionType 17 (group collection)         entry type 17, total of the entry (its debit side)
 *                   TransactionType 1 (a receipt line)            entry type 1 of the receipt: the CREDIT LINE on the sub account's
 *                                                                 income account that belongs to this row (a receipt has one line per
 *                                                                 contribution, so the entry total would be the whole receipt)
 *                   any other type                                no journal rule: listed as excluded
 *   Expenses      tblexpenses (approved, not deleted)             entry type 2, total of the entry (its debit side), by expense account
 * A source row without an active entry (voided, deleted, never posted) is EXCLUDED from the figures; excluded() lists them.
 * The period is the date of the source record (approval date, collection date, expense date), as before; the entry date is not used.
 * The group / district must belong to the session congregation, and so must every source row and every entry.
 */
class GroupIncomeStatement
{
    const KINDS = ['group', 'district'];
    const TYPE_RECEIPT = 1;
    const TYPE_EXPENSE = 2;
    const TYPE_REQUISITION = 12;
    const TYPE_COLLECTION = 17;

    private static function cols(string $kind): array
    {
        return $kind === 'district'
            ? ['req' => 'DistrictId', 'mmf' => 'DistrictId', 'exp' => 'districtId']
            : ['req' => 'GroupId', 'mmf' => 'GroupId', 'exp' => 'groupId'];
    }

    /** ['id' => , 'name' => ] of a group / district of this congregation, or null (not found, deleted, another congregation) */
    public static function owner(PDO $db, int $cong, string $kind, int $id): ?array
    {
        if (!in_array($kind, self::KINDS, true) || $id < 1) { return null; }
        $sql = $kind === 'district'
            ? 'SELECT ID, districtName AS name FROM tbldistricts WHERE ID = ? AND congregationId = ? AND deleted = 0'
            : 'SELECT ID, groupName AS name FROM tblgroups WHERE ID = ? AND congregationId = ? AND deleted = 0';
        $st = $db->prepare($sql);
        $st->execute([$id, $cong]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? ['id' => (int) $r['ID'], 'name' => (string) $r['name']] : null;
    }

    /** The groups and districts the screen offers (the same ownership rule as owner(); groups keep the old active-only list). */
    public static function choices(PDO $db, int $cong): array
    {
        $g = $db->prepare('SELECT ID, UCASE(groupName) AS name FROM tblgroups WHERE active = 1 AND deleted = 0 AND congregationId = ? ORDER BY groupName');
        $g->execute([$cong]);
        $d = $db->prepare('SELECT ID, UCASE(districtName) AS name FROM tbldistricts WHERE deleted = 0 AND congregationId = ? ORDER BY districtName');
        $d->execute([$cong]);
        return ['groups' => $g->fetchAll(PDO::FETCH_ASSOC), 'districts' => $d->fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * Every source row of the three categories for one group / district and period, each with its active entry (if any) and the
     * amount taken from it. Row: cat req|coll|exp, id, date, no, narr, src (the source row's own amount, cents), entryId (?int),
     * amount (?int cents, from the entry), accountId (exp), link (?string), srcType.
     */
    public static function sources(PDO $db, int $cong, string $kind, int $id, string $from, string $to): array
    {
        $c = self::cols($kind);
        $debit = '(SELECT SUM(l.amount) FROM journal_lines l WHERE l.journalEntryId = e.ID AND l.dc = \'DEBIT\')';
        $rows = [];

        $st = $db->prepare("SELECT r.ID, r.ApprovalDate AS d, r.ReqNo AS no, r.Purpose AS narr, r.AmountApproved AS src, e.ID AS entryId, $debit AS je
                            FROM tblfundrequisition r
                            LEFT JOIN journal_entries e ON e.transactionTypeId = " . self::TYPE_REQUISITION . " AND e.transactionId = r.ID AND e.congregationId = r.CongregationId AND e.deleted = 0
                            WHERE r.Deleted = 0 AND r.Status = 1 AND r.DontDeduct = 1 AND r.CongregationId = ? AND r.{$c['req']} = ? AND r.ApprovalDate BETWEEN ? AND ?
                            ORDER BY r.ApprovalDate, r.ID");
        $st->execute([$cong, $id, $from, $to]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = self::row('req', $r, 'Req ' . $r['no'], null, self::TYPE_REQUISITION, (int) $r['ID']);
        }

        // receipt lines: the k-th active mmf row of (receipt, income account) is the k-th credit line of the entry on that account
        $st = $db->prepare("SELECT x.ID, x.d, x.hid, h.receiptNo AS no, x.narr, x.src, e.ID AS entryId, jl.amount AS je
                            FROM (SELECT m.ID, m.TransactionDate AS d, m.TransactionId AS hid, m.Narration AS narr, m.Debit AS src, m.{$c['mmf']} AS ent, sa.AccountId AS acc,
                                         ROW_NUMBER() OVER (PARTITION BY m.TransactionId, sa.AccountId ORDER BY m.ID) AS rn
                                  FROM tblmmf m LEFT JOIN tblbanksubaccounts sa ON sa.ID = m.SubAccountId
                                  WHERE m.Deleted = 0 AND m.TransactionType = " . self::TYPE_RECEIPT . " AND m.CongregationId = ?) x
                            LEFT JOIN tblcontributions_header h ON h.ID = x.hid AND h.congregationId = ?
                            LEFT JOIN journal_entries e ON e.transactionTypeId = " . self::TYPE_RECEIPT . " AND e.transactionId = x.hid AND e.congregationId = ? AND e.deleted = 0
                            LEFT JOIN (SELECT l.journalEntryId, l.accountId, l.amount, ROW_NUMBER() OVER (PARTITION BY l.journalEntryId, l.accountId ORDER BY l.lineNumber) AS rn
                                       FROM journal_lines l WHERE l.dc = 'CREDIT') jl ON jl.journalEntryId = e.ID AND jl.accountId = x.acc AND jl.rn = x.rn
                            WHERE x.ent = ? AND x.src > 0 AND x.d BETWEEN ? AND ?
                            ORDER BY x.d, x.ID");
        $st->execute([$cong, $cong, $cong, $id, $from, $to]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = self::row('coll', $r, (string) $r['no'], null, self::TYPE_RECEIPT, (int) $r['hid']);
        }

        // group collections (17) and any other debit row of tblmmf (no journal rule for it: excluded)
        $st = $db->prepare("SELECT m.ID, m.TransactionDate AS d, m.TransactionType AS tt, m.Narration AS narr, m.Debit AS src, e.ID AS entryId, $debit AS je
                            FROM tblmmf m
                            LEFT JOIN journal_entries e ON m.TransactionType = " . self::TYPE_COLLECTION . " AND e.transactionTypeId = " . self::TYPE_COLLECTION . " AND e.transactionId = m.ID AND e.congregationId = m.CongregationId AND e.deleted = 0
                            WHERE m.Deleted = 0 AND (m.TransactionType <> " . self::TYPE_RECEIPT . " OR m.TransactionType IS NULL) AND m.Debit > 0
                                  AND m.CongregationId = ? AND m.{$c['mmf']} = ? AND m.TransactionDate BETWEEN ? AND ?
                            ORDER BY m.TransactionDate, m.ID");
        $st->execute([$cong, $id, $from, $to]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $is17 = (int) $r['tt'] === self::TYPE_COLLECTION;
            $rows[] = self::row('coll', $r, ($is17 ? 'Collection ' : 'MMF row ') . $r['ID'], null, $is17 ? self::TYPE_COLLECTION : null, (int) $r['ID']);
        }

        $st = $db->prepare("SELECT x.ID, x.expenseDate AS d, x.voucherNo AS no, x.narration AS narr, x.amount AS src, x.accountId, e.ID AS entryId, $debit AS je
                            FROM tblexpenses x
                            LEFT JOIN journal_entries e ON e.transactionTypeId = " . self::TYPE_EXPENSE . " AND e.transactionId = x.ID AND e.congregationId = x.congregationId AND e.deleted = 0
                            WHERE x.deleted = 0 AND x.status = 1 AND x.congregationId = ? AND x.{$c['exp']} = ? AND x.expenseDate BETWEEN ? AND ?
                            ORDER BY x.expenseDate, x.ID");
        $st->execute([$cong, $id, $from, $to]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = self::row('exp', $r, 'Voucher ' . $r['no'], (int) $r['accountId'], self::TYPE_EXPENSE, (int) $r['ID']);
        }
        return $rows;
    }

    private static function row(string $cat, array $r, string $no, ?int $account, ?int $linkType, int $linkId): array
    {
        $hasEntry = $r['entryId'] !== null && $r['je'] !== null;
        return [
            'cat' => $cat, 'id' => (int) $r['ID'], 'date' => (string) $r['d'], 'no' => trim($no), 'narr' => (string) ($r['narr'] ?? ''),
            'src' => FinancialReport::cents($r['src']), 'entryId' => $hasEntry ? (int) $r['entryId'] : null,
            'amount' => $hasEntry ? FinancialReport::cents($r['je']) : null,
            'accountId' => $account, 'link' => $linkType === null ? null : FinancialReport::sourceLink($linkType, $linkId),
        ];
    }

    /** the rows that count: they have an active entry */
    public static function included(array $sources): array
    {
        return array_values(array_filter($sources, function ($r) { return $r['entryId'] !== null; }));
    }

    /** the rows left out (no active entry): per category the count, the source amount and the rows */
    public static function excluded(array $sources): array
    {
        $out = ['req' => ['count' => 0, 'amount' => 0, 'rows' => []], 'coll' => ['count' => 0, 'amount' => 0, 'rows' => []], 'exp' => ['count' => 0, 'amount' => 0, 'rows' => []]];
        foreach ($sources as $r) {
            if ($r['entryId'] !== null) { continue; }
            $out[$r['cat']]['count']++;
            $out[$r['cat']]['amount'] += $r['src'];
            $out[$r['cat']]['rows'][] = $r;
        }
        return $out;
    }

    /**
     * The statement: revenue rows (zero rows left out, decision 119), revenue total, expense rows by account, expense total, result.
     * Totals always show. Amounts are the entries' (integer cents).
     */
    public static function statement(PDO $db, int $cong, string $kind, int $id, string $from, string $to): array
    {
        $sources = self::sources($db, $cong, $kind, $id, $from, $to);
        $inc = self::included($sources);
        $word = $kind === 'district' ? 'District' : 'Group';
        $sum = function (string $cat) use ($inc) { $t = 0; foreach ($inc as $r) { if ($r['cat'] === $cat) { $t += $r['amount']; } } return $t; };
        $rows = [];
        foreach ([['requisitions', $word . ' Requisitions', $sum('req')], ['collections', $word . ' Collections', $sum('coll')]] as [$key, $label, $amt]) {
            if ($amt !== 0) { $rows[] = ['key' => $key, 'label' => $label, 'amount' => $amt]; }
        }
        $revenue = $sum('req') + $sum('coll');

        $chart = FinancialReport::chart($db, [$cong]);
        $byAccount = [];
        foreach ($inc as $r) { if ($r['cat'] === 'exp') { $byAccount[$r['accountId']] = ($byAccount[$r['accountId']] ?? 0) + $r['amount']; } }
        $exp = [];
        foreach ($byAccount as $acc => $amt) {
            if ($amt === 0) { continue; }
            $exp[] = ['account' => (int) $acc, 'label' => self::accountLabel($db, $chart, (int) $acc), 'amount' => $amt];
        }
        usort($exp, function ($a, $b) { return strnatcasecmp($a['label'], $b['label']) ?: $a['account'] <=> $b['account']; });
        $expenses = array_sum(array_column($exp, 'amount'));
        return [
            'revenue' => ['rows' => $rows, 'total' => $revenue], 'expenses' => ['rows' => $exp, 'total' => $expenses],
            'result' => $revenue - $expenses, 'sources' => $sources,
        ];
    }

    private static function accountLabel(PDO $db, array $chart, int $id): string
    {
        if (isset($chart['nodes'][$id])) { return FinancialReport::title($chart['nodes'][$id]['label']); }
        $st = $db->prepare('SELECT accountType FROM tblaccounttypes WHERE ID = ?');
        $st->execute([$id]);
        return FinancialReport::title((string) $st->fetchColumn());
    }

    // ------------------------------------------------------------------ requests

    /** [kind, id, from, to] from a query array; exactly one of group / district; HTTP 400 for anything else or a foreign owner */
    public static function request(PDO $db, int $cong, array $q): array
    {
        $has = array_values(array_filter(self::KINDS, function ($k) use ($q) { return isset($q[$k]) && $q[$k] !== ''; }));
        if (count($has) !== 1) { IncomeStatementReport::fail('Please select a group or a district'); }
        $kind = $has[0];
        $v = $q[$kind];
        if (!is_string($v) || !ctype_digit($v) || strlen($v) > 10 || (int) $v < 1) { IncomeStatementReport::fail('Invalid ' . $kind); }
        [$from, $to] = IncomeStatementReport::dates($q);
        $owner = self::owner($db, $cong, $kind, (int) $v);
        if ($owner === null) { IncomeStatementReport::fail('Invalid ' . $kind); }
        return [$kind, $owner, $from, $to];
    }

    private static function ddmmyyyy(string $d): string { return date('d-m-Y', strtotime($d)); }

    public static function shape(array $st, string $kind, array $owner, string $from, string $to): array
    {
        $b = function (int $c) { return IncomeStatementReport::bracket($c); };
        return [
            'kind' => $kind, 'id' => $owner['id'], 'name' => ucwords($owner['name']), 'start' => $from, 'end' => $to,
            'revenue' => ['rows' => array_map(function ($r) use ($b) { return ['key' => $r['key'], 'label' => $r['label'], 'amount' => $b($r['amount']), 'raw' => $r['amount']]; }, $st['revenue']['rows']),
                          'total' => $b($st['revenue']['total']), 'totalRaw' => $st['revenue']['total']],
            'expenses' => ['rows' => array_map(function ($r) use ($b) { return ['account' => $r['account'], 'label' => $r['label'], 'amount' => $b($r['amount']), 'raw' => $r['amount']]; }, $st['expenses']['rows']),
                           'total' => $b($st['expenses']['total']), 'totalRaw' => $st['expenses']['total']],
            'result' => ['amount' => $b($st['result']), 'raw' => $st['result']],
        ];
    }

    public static function sheetRows(array $d): array
    {
        $S = function ($t, $bold = false) { return XlsxWriter::s($t, $bold); };
        $rows = [[$S('Income Statement', true)], [$S($d['name'])], [$S('Period ' . self::ddmmyyyy($d['start']) . ' to ' . self::ddmmyyyy($d['end']))], [], [$S('Account', true), $S('Amount', true)]];
        $rows[] = [$S('Income', true)];
        foreach ($d['revenue']['rows'] as $r) { $rows[] = [$S($r['label']), XlsxWriter::m($r['raw'])]; }
        $rows[] = [$S('Revenue Total', true), XlsxWriter::m($d['revenue']['totalRaw'], true)];
        $rows[] = [$S('Expenses', true)];
        foreach ($d['expenses']['rows'] as $r) { $rows[] = [$S($r['label']), XlsxWriter::m($r['raw'])]; }
        $rows[] = [$S('Expense Total', true), XlsxWriter::m($d['expenses']['totalRaw'], true)];
        $rows[] = [$S('Profit/Loss', true), XlsxWriter::m($d['result']['raw'], true)];
        return $rows;
    }

    /** the statement as JSON or, with format=xlsx, as a workbook */
    public static function respondStatement(PDO $db, int $cong, array $q): void
    {
        [$kind, $owner, $from, $to] = self::request($db, $cong, $q);
        $d = self::shape(self::statement($db, $cong, $kind, $owner['id'], $from, $to), $kind, $owner, $from, $to);
        if (!IncomeStatementReport::wantsXlsx()) { IncomeStatementReport::send($d); }
        XlsxWriter::download('group-income-statement-' . $kind . $owner['id'] . '-' . $from . '-to-' . $to . '.xlsx', 'Income statement', self::sheetRows($d), [46, 20]);
    }

    /**
     * The transactions behind one figure ($cat requisitions | collections | expenses, + the expense account): date, source no,
     * narration, amount (from the entry) and the source link. Only rows with an active entry, so the total is the figure clicked.
     */
    public static function detail(PDO $db, int $cong, string $kind, int $id, string $from, string $to, string $cat, ?int $account = null): array
    {
        $want = ['requisitions' => 'req', 'collections' => 'coll', 'expenses' => 'exp'][$cat];
        $rows = [];
        foreach (self::included(self::sources($db, $cong, $kind, $id, $from, $to)) as $r) {
            if ($r['cat'] !== $want || ($account !== null && $r['accountId'] !== $account)) { continue; }
            $rows[] = ['date' => $r['date'], 'no' => $r['no'], 'narr' => $r['narr'], 'amount' => $r['amount'], 'link' => $r['link']];
        }
        return $rows;
    }
    /**
     * The transactions behind one figure: $cat requisitions | collections | expenses (+ the expense account). Only included rows, so
     * the total equals the figure on the statement. JSON page (search, paging) or the workbook of every matching row.
     */
    public static function respondDetail(PDO $db, int $cong, array $q, string $cat): void
    {
        [$kind, $owner, $from, $to] = self::request($db, $cong, $q);
        $account = null;
        if ($cat === 'expenses') {
            $v = $q['account'] ?? null;
            if (!is_string($v) || !ctype_digit($v) || strlen($v) > 10 || (int) $v < 1) { IncomeStatementReport::fail('Invalid account'); }
            $account = (int) $v;
            if (!isset(FinancialReport::chart($db, [$cong])['nodes'][$account])) { IncomeStatementReport::fail('Invalid account'); }
        } elseif (!in_array($cat, ['requisitions', 'collections'], true)) {
            IncomeStatementReport::fail('Invalid report type');
        }
        $word = $kind === 'district' ? 'District' : 'Group';
        $title = $cat === 'expenses' ? self::accountLabel($db, FinancialReport::chart($db, [$cong]), $account) : ($word . ' ' . ucfirst($cat));
        $rows = self::detail($db, $cong, $kind, $owner['id'], $from, $to, $cat, $account);
        $spec = [
            'title' => $title, 'lines' => [ucwords($owner['name']), 'Period ' . self::ddmmyyyy($from) . ' to ' . self::ddmmyyyy($to)],
            'columns' => [['key' => 'date', 'label' => 'Date', 'type' => 'date'], ['key' => 'no', 'label' => 'Source no', 'type' => 'link'],
                          ['key' => 'narr', 'label' => 'Narration', 'type' => 'text'], ['key' => 'amount', 'label' => 'Amount', 'type' => 'money']],
            'total' => 'amount', 'file' => 'group-income-' . $cat . ($account ? '-' . $account : '') . '-' . $kind . $owner['id'] . '-' . $from . '-to-' . $to,
            'sheet' => 'Transactions', 'widths' => [12, 18, 50, 16],
        ];
        ReportList::respond($spec, $rows, $q);
    }
}

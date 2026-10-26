<?php
/**
 * Group / district sub account reports (task 9.8 part C): the list of balances, the statement of one sub account and the breakdown
 * behind a balance. Read-only. The sub accounts are the ones of the session congregation (the bank account they belong to, and the
 * group / district they are held for, are this congregation's); a foreign or unknown sub account is HTTP 400. Money is integer cents.
 *
 * These reports still read the sub account book (tblbanktransactions_subaccounts) and the requisition-funded expenses, as before:
 *   balance   = sum of the book up to the date - approved (status = 1) requisition expenses of the group / district up to the date
 *   statement = opening + book rows + approved (status = 1) requisition expenses of the period (decision 161: the three agree; the
 *               spending checks keep counting drafts as spent, decision 14, and are not touched here)
 * The breakdown behind a balance lists exactly the rows that make it (book rows positive, expenses negative), so its total equals
 * the balance clicked. The group breakdown is the same query as the district one with the group column.
 */
class SubAccountReport
{
    /** [id => ['id','name','kind' group|district,'ownerId']] of this congregation's sub accounts */
    public static function owned(PDO $db, int $cong): array
    {
        $st = $db->prepare("SELECT s.ID, s.AccountName, s.GroupDistrict, s.GroupId, s.DistrictId
                            FROM tblbanksubaccounts s
                                 JOIN tblaccounttypes a ON a.ID = s.BankId AND a.congregationId = ?
                                 LEFT JOIN tblgroups g ON s.GroupDistrict = 'group' AND g.ID = s.GroupId AND g.congregationId = ?
                                 LEFT JOIN tbldistricts d ON s.GroupDistrict = 'district' AND d.ID = s.DistrictId AND d.congregationId = ?
                            WHERE s.Deleted = 0 AND ((s.GroupDistrict = 'group' AND g.ID IS NOT NULL) OR (s.GroupDistrict = 'district' AND d.ID IS NOT NULL))
                            ORDER BY s.AccountName, s.ID");
        $st->execute([$cong, $cong, $cong]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $kind = $r['GroupDistrict'] === 'district' ? 'district' : 'group';
            $out[(int) $r['ID']] = ['id' => (int) $r['ID'], 'name' => (string) $r['AccountName'], 'kind' => $kind, 'ownerId' => (int) ($kind === 'group' ? $r['GroupId'] : $r['DistrictId'])];
        }
        return $out;
    }

    private static function expenseColumn(string $kind): string
    {
        return $kind === 'district' ? 'districtId' : 'groupId';
    }

    /** Balance of every sub account of the congregation at a date: [id => cents] */
    public static function balances(PDO $db, int $cong, string $asOf): array
    {
        $out = [];
        $book = $db->prepare('SELECT IFNULL(SUM(Amount),0) FROM tblbanktransactions_subaccounts WHERE SubAccountId = ? AND TransactionDate <= ?');
        foreach (self::owned($db, $cong) as $id => $s) {
            $col = self::expenseColumn($s['kind']);
            $exp = $db->prepare("SELECT IFNULL(SUM(amount),0) FROM tblexpenses WHERE deleted = 0 AND status = 1 AND expenseDate <= ? AND $col = ? AND requisitionId IS NOT NULL AND congregationId = ?");
            $book->execute([$id, $asOf]); $b = FinancialReport::cents($book->fetchColumn());
            $exp->execute([$asOf, $s['ownerId'], $cong]); $e = FinancialReport::cents($exp->fetchColumn());
            $out[$id] = $b - $e;
        }
        return $out;
    }

    /**
     * The rows behind the balance at a date: the book rows (as booked) and the requisition expenses of the group / district
     * (negative). Row: date, narr, reference, amount (cents). Ordered by date.
     */
    public static function breakdown(PDO $db, int $cong, array $sub, string $asOf): array
    {
        $col = self::expenseColumn($sub['kind']);
        $st = $db->prepare("SELECT TransactionDate AS d, Narration AS narr, Reference AS ref, Amount AS amount, 0 AS ord, ID AS id
                            FROM tblbanktransactions_subaccounts WHERE SubAccountId = ? AND TransactionDate <= ?
                            UNION ALL
                            SELECT e.expenseDate, e.narration, e.paymentReference, e.amount * -1, 1, e.ID
                            FROM tblexpenses e
                            WHERE e.deleted = 0 AND e.status = 1 AND e.requisitionId IS NOT NULL AND e.expenseDate <= ? AND e.$col = ? AND e.congregationId = ?
                            ORDER BY d, ord, id");
        $st->execute([$sub['id'], $asOf, $asOf, $sub['ownerId'], $cong]);
        return array_map(function ($r) {
            return ['date' => (string) $r['d'], 'narr' => (string) ($r['narr'] ?? ''), 'reference' => (string) ($r['ref'] ?? ''), 'amount' => FinancialReport::cents($r['amount'])];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Statement of one sub account: opening balance, then in / out rows with the running balance. Row: date, reference, narr, in, out, balance. */
    public static function statement(PDO $db, int $cong, array $sub, string $from, string $to): array
    {
        $col = self::expenseColumn($sub['kind']);
        $open = $db->prepare('SELECT IFNULL(SUM(Amount),0) FROM tblbanktransactions_subaccounts WHERE SubAccountId = ? AND TransactionDate < ?');
        $open->execute([$sub['id'], $from]);
        $oe = $db->prepare("SELECT IFNULL(SUM(amount),0) FROM tblexpenses WHERE deleted = 0 AND status = 1 AND expenseDate < ? AND $col = ? AND requisitionId IS NOT NULL AND congregationId = ?");
        $oe->execute([$from, $sub['ownerId'], $cong]);
        $balance = FinancialReport::cents($open->fetchColumn()) - FinancialReport::cents($oe->fetchColumn());
        $rows = [['date' => $from, 'reference' => '', 'narr' => 'Opening balance', 'in' => null, 'out' => null, 'balance' => $balance]];
        $st = $db->prepare("SELECT TransactionDate AS d, Reference AS ref, Narration AS narr, Amount AS amount, 0 AS ord, ID AS id
                            FROM tblbanktransactions_subaccounts WHERE SubAccountId = ? AND TransactionDate BETWEEN ? AND ?
                            UNION ALL
                            SELECT e.expenseDate, e.paymentReference, e.narration, e.amount * -1, 1, e.ID
                            FROM tblexpenses e
                            WHERE e.deleted = 0 AND e.status = 1 AND e.requisitionId IS NOT NULL AND e.expenseDate BETWEEN ? AND ? AND e.$col = ? AND e.congregationId = ?
                            ORDER BY d, ord, id");
        $st->execute([$sub['id'], $from, $to, $from, $to, $sub['ownerId'], $cong]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $amt = FinancialReport::cents($r['amount']);
            $balance += $amt;
            $rows[] = ['date' => (string) $r['d'], 'reference' => (string) ($r['ref'] ?? ''), 'narr' => (string) ($r['narr'] ?? ''),
                       'in' => $amt > 0 ? $amt : null, 'out' => $amt < 0 ? -$amt : null, 'balance' => $balance];
        }
        return $rows;
    }

    private static function ddmmyyyy(string $d): string { return date('d-m-Y', strtotime($d)); }

    /** a sub account of this congregation from the request value, else HTTP 400 */
    private static function sub(PDO $db, int $cong, $v): array
    {
        if (!is_string($v) || !ctype_digit($v) || strlen($v) > 9 || (int) $v < 1) { IncomeStatementReport::fail('Invalid sub account'); }
        $owned = self::owned($db, $cong);
        if (!isset($owned[(int) $v])) { IncomeStatementReport::fail('Invalid sub account'); }
        return $owned[(int) $v];
    }

    /** The breakdown behind a balance (account, asdate): JSON page, search, Excel. */
    public static function serveBreakdown(PDO $db, int $cong, array $q): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        $sub = self::sub($db, $cong, $q['account'] ?? null);
        $asOf = $q['asdate'] ?? null;
        [$asOf] = IncomeStatementReport::dates(['start' => $asOf, 'end' => $asOf]);
        $spec = ['title' => 'Sub account breakdown: ' . ucwords($sub['name']), 'lines' => ['As of ' . self::ddmmyyyy($asOf)],
                 'columns' => [['key' => 'date', 'label' => 'Date', 'type' => 'date'], ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                               ['key' => 'narr', 'label' => 'Narration', 'type' => 'text'], ['key' => 'reference', 'label' => 'Reference', 'type' => 'text']],
                 'total' => 'amount', 'file' => 'subaccount-' . $sub['id'] . '-breakdown-' . $asOf, 'sheet' => 'Breakdown', 'widths' => [12, 16, 50, 24]];
        ReportList::respond($spec, self::breakdown($db, $cong, $sub, $asOf), $q);
    }

    /** The sub account report: account = all (balances at `from`) or one sub account (statement from - to). JSON page, search, Excel. */
    public static function serveReport(PDO $db, int $cong, array $q): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        $account = $q['account'] ?? null;
        if ($account === 'all') {
            [$asOf] = IncomeStatementReport::dates(['start' => $q['from'] ?? null, 'end' => $q['from'] ?? null]);
            $owned = self::owned($db, $cong); $bal = self::balances($db, $cong, $asOf);
            $rows = [];
            foreach ($owned as $id => $s) { $rows[] = ['name' => $s['name'], 'balance' => $bal[$id], 'link' => 'reports/subaccountdetailed?' . http_build_query(['subaccount' => $id, 'asdate' => $asOf])]; }
            $spec = ['title' => 'Sub account balances', 'lines' => ['As of ' . self::ddmmyyyy($asOf)],
                     'columns' => [['key' => 'name', 'label' => 'Sub Account', 'type' => 'link'], ['key' => 'balance', 'label' => 'Balance', 'type' => 'money']],
                     'total' => 'balance', 'file' => 'subaccount-balances-' . $asOf, 'sheet' => 'Balances', 'widths' => [46, 18]];
            ReportList::respond($spec, $rows, $q);
        }
        $sub = self::sub($db, $cong, $account);
        [$from, $to] = IncomeStatementReport::dates(['start' => $q['from'] ?? null, 'end' => $q['to'] ?? null]);
        $spec = ['title' => 'Sub account statement: ' . ucwords($sub['name']), 'lines' => ['Period ' . self::ddmmyyyy($from) . ' to ' . self::ddmmyyyy($to)],
                 'columns' => [['key' => 'date', 'label' => 'Transaction Date', 'type' => 'date'], ['key' => 'reference', 'label' => 'Reference', 'type' => 'text'],
                               ['key' => 'narr', 'label' => 'Description', 'type' => 'text'], ['key' => 'in', 'label' => 'Money In', 'type' => 'money'],
                               ['key' => 'out', 'label' => 'Money Out', 'type' => 'money'], ['key' => 'balance', 'label' => 'Running Balance', 'type' => 'money']],
                 'total' => null, 'file' => 'subaccount-' . $sub['id'] . '-statement-' . $from . '-to-' . $to, 'sheet' => 'Statement', 'widths' => [14, 22, 46, 16, 16, 18]];
        ReportList::respond($spec, self::statement($db, $cong, $sub, $from, $to), $q);
    }
}

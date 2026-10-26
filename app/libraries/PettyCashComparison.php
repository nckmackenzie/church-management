<?php
/** Read-only finance comparison between the legacy petty-cash book and the journal (task 9.6). */
class PettyCashComparison
{
    private const ACCOUNTS = [98, 194];

    private static function congregation(PDO $db): int
    {
        $s = $db->prepare('SELECT congregationId FROM tblaccounttypes WHERE ID = 194 AND deleted = 0');
        $s->execute();
        $id = $s->fetchColumn();
        if ($id === false || (int) $id <= 0) { throw new RuntimeException('Petty cash account 194 has no owning congregation.'); }
        return (int) $id;
    }

    private static function years(PDO $db): array
    {
        return $db->query('SELECT ID, yearName, startDate, endDate FROM tblfiscalyears ORDER BY startDate, ID')->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function petty(PDO $db, int $cong): array
    {
        $s = $db->prepare("SELECT p.ID, p.TransactionDate AS txDate, p.PettyCashAccountId AS accountId,
                                 p.Debit, p.Credit, p.ExpenseId, p.TransactionType, p.TransactionId, p.IsReceipt,
                                 CASE WHEN p.ExpenseId IS NOT NULL THEN 2 WHEN p.TransactionType IS NOT NULL THEN p.TransactionType WHEN p.IsReceipt = 1 THEN 10 ELSE 0 END AS typeId,
                                 CASE WHEN p.ExpenseId IS NOT NULL THEN e.voucherNo WHEN p.TransactionType = 13 THEN b.Reference WHEN p.IsReceipt = 1 THEN p.ReceiptNo ELSE p.ReceiptNo END AS sourceNo,
                                 p.Reference, p.Narration
                            FROM tblpettycash p
                       LEFT JOIN tblexpenses e ON e.ID = p.ExpenseId AND e.congregationId = p.CongregationId
                       LEFT JOIN tblbanktransactions b ON b.ID = p.TransactionId AND p.TransactionType = 13 AND b.congregationId = p.CongregationId
                           WHERE p.Deleted = 0 AND p.CongregationId = ? AND p.PettyCashAccountId IN (98,194)");
        $s->execute([$cong]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function ledger(PDO $db, int $cong): array
    {
        $s = $db->prepare("SELECT jl.ID AS lineId, je.ID AS entryId, je.entryDate AS txDate, jl.accountId, jl.dc, jl.amount,
                                 je.transactionTypeId AS typeId, je.transactionId, je.journalNo, je.reference, COALESCE(NULLIF(jl.memo,''),je.description) AS narration,
                                 CASE je.transactionTypeId
                                   WHEN 1 THEN ch.receiptNo WHEN 2 THEN ex.voucherNo WHEN 5 THEN CAST(je.journalNo AS CHAR)
                                   WHEN 10 THEN pc.ReceiptNo WHEN 12 THEN fr.ReqNo WHEN 13 THEN bt.Reference ELSE NULL END AS sourceNo
                            FROM journal_entries je
                            JOIN journal_lines jl ON jl.journalEntryId = je.ID
                       LEFT JOIN tblcontributions_header ch ON je.transactionTypeId = 1 AND ch.ID = je.transactionId AND ch.congregationId = je.congregationId
                       LEFT JOIN tblexpenses ex ON je.transactionTypeId = 2 AND ex.ID = je.transactionId AND ex.congregationId = je.congregationId
                       LEFT JOIN tblpettycash pc ON je.transactionTypeId = 10 AND pc.ID = je.transactionId AND pc.CongregationId = je.congregationId
                       LEFT JOIN tblfundrequisition fr ON je.transactionTypeId = 12 AND fr.ID = je.transactionId AND fr.CongregationId = je.congregationId
                       LEFT JOIN tblbanktransactions bt ON je.transactionTypeId = 13 AND bt.ID = je.transactionId AND bt.congregationId = je.congregationId
                           WHERE je.deleted = 0 AND je.congregationId = ? AND jl.accountId IN (98,194)
                        ORDER BY je.entryDate, je.ID, jl.lineNumber, jl.ID");
        $s->execute([$cong]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function typeNames(PDO $db): array
    {
        $out = [0 => 'Unclassified'];
        foreach ($db->query('SELECT ID, transactionType FROM tbltransactiontypes')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['ID']] = ucwords((string) $r['transactionType']);
        }
        return $out;
    }

    private static function movement(array $rows, int $account, string $from, string $to, ?int $type = null, bool $ledger = false): int
    {
        $sum = 0;
        foreach ($rows as $r) {
            if ((int) $r['accountId'] !== $account || $r['txDate'] < $from || $r['txDate'] > $to || ($type !== null && (int) $r['typeId'] !== $type)) { continue; }
            if ($ledger) { $sum += FinancialReport::cents($r['amount']) * ($r['dc'] === 'DEBIT' ? 1 : -1); }
            else { $sum += FinancialReport::cents($r['Debit']) - FinancialReport::cents($r['Credit']); }
        }
        return $sum;
    }

    private static function closing(array $rows, int $account, string $to, bool $ledger): int
    {
        return self::movement($rows, $account, '1000-01-01', $to, null, $ledger);
    }

    private static function matched(array $line, array $p): bool
    {
        if ((int) $line['accountId'] !== (int) $p['accountId']) { return false; }
        $amount = FinancialReport::cents($line['amount']);
        if ($line['dc'] === 'DEBIT' ? FinancialReport::cents($p['Debit']) !== $amount : FinancialReport::cents($p['Credit']) !== $amount) { return false; }
        $type = (int) $line['typeId'];
        if ($type === 2) { return (int) $p['ExpenseId'] > 0 && (int) $p['ExpenseId'] === (int) $line['transactionId']; }
        if ($type === 10) { return (int) $p['ID'] === (int) $line['transactionId']; }
        if ($type === 13) { return (int) $p['TransactionType'] === 13 && (int) $p['TransactionId'] === (int) $line['transactionId']; }
        return false;
    }

    public static function data(PDO $db): array
    {
        $cong = self::congregation($db); $years = self::years($db); $petty = self::petty($db, $cong); $ledger = self::ledger($db, $cong); $types = self::typeNames($db);
        $summary = []; $byType = []; $old = [];
        foreach ($years as $y) {
            foreach (self::ACCOUNTS as $account) {
                $oldClose = self::closing($petty, $account, $y['endDate'], false); $newClose = self::closing($ledger, $account, $y['endDate'], true);
                $oldMove = self::movement($petty, $account, $y['startDate'], $y['endDate']); $newMove = self::movement($ledger, $account, $y['startDate'], $y['endDate'], null, true);
                if ($oldClose !== 0 || $newClose !== 0 || $oldMove !== 0 || $newMove !== 0) {
                    $summary[] = ['year' => $y['yearName'], 'account' => $account, 'old' => $oldClose, 'new' => $newClose, 'difference' => $newClose - $oldClose];
                    $opening = self::closing($petty, $account, date('Y-m-d', strtotime($y['startDate'] . ' -1 day')), false);
                    $receipts = 0; $payments = 0;
                    foreach ($petty as $p) { if ((int) $p['accountId'] === $account && $p['txDate'] >= $y['startDate'] && $p['txDate'] <= $y['endDate']) { $receipts += FinancialReport::cents($p['Debit']); $payments += FinancialReport::cents($p['Credit']); } }
                    $old[] = ['year' => $y['yearName'], 'account' => $account, 'opening' => $opening, 'receipts' => $receipts, 'payments' => $payments, 'closing' => $opening + $receipts - $payments];
                }
                $ids = [];
                foreach ($petty as $p) { if ((int) $p['accountId'] === $account && $p['txDate'] >= $y['startDate'] && $p['txDate'] <= $y['endDate']) { $ids[(int) $p['typeId']] = true; } }
                foreach ($ledger as $l) { if ((int) $l['accountId'] === $account && $l['txDate'] >= $y['startDate'] && $l['txDate'] <= $y['endDate']) { $ids[(int) $l['typeId']] = true; } }
                ksort($ids);
                foreach (array_keys($ids) as $type) {
                    $po = self::movement($petty, $account, $y['startDate'], $y['endDate'], $type); $jn = self::movement($ledger, $account, $y['startDate'], $y['endDate'], $type, true);
                    $byType[] = ['year' => $y['yearName'], 'account' => $account, 'typeId' => $type, 'type' => $types[$type] ?? ('Type ' . $type), 'old' => $po, 'new' => $jn, 'difference' => $jn - $po];
                }
            }
        }
        $usedPetty = []; $usedLedger = [];
        foreach ($ledger as $li => $line) {
            foreach ($petty as $pi => $p) { if (!isset($usedPetty[$pi]) && self::matched($line, $p)) { $usedLedger[$li] = true; $usedPetty[$pi] = true; break; } }
        }
        $ledgerOnly = [];
        foreach ($ledger as $i => $r) { if (!isset($usedLedger[$i])) { $ledgerOnly[] = ['id' => (int) $r['lineId'], 'sourceId' => (int) $r['transactionId'], 'date' => $r['txDate'], 'account' => (int) $r['accountId'], 'typeId' => (int) $r['typeId'], 'type' => $types[(int) $r['typeId']] ?? ('Type ' . $r['typeId']), 'source' => $r['sourceNo'] ?? '', 'side' => $r['dc'], 'amount' => FinancialReport::cents($r['amount']), 'reference' => $r['reference'], 'narration' => $r['narration']]; } }
        $pettyOnly = [];
        foreach ($petty as $i => $r) { if (!isset($usedPetty[$i])) { $debit = FinancialReport::cents($r['Debit']); $sourceId = (int) ($r['ExpenseId'] ?: ($r['TransactionId'] ?: $r['ID'])); $pettyOnly[] = ['id' => (int) $r['ID'], 'sourceId' => $sourceId, 'date' => $r['txDate'], 'account' => (int) $r['accountId'], 'typeId' => (int) $r['typeId'], 'type' => $types[(int) $r['typeId']] ?? ('Type ' . $r['typeId']), 'source' => $r['sourceNo'] ?? '', 'side' => $debit !== 0 ? 'DEBIT' : 'CREDIT', 'amount' => $debit !== 0 ? $debit : FinancialReport::cents($r['Credit']), 'reference' => $r['Reference'], 'narration' => $r['Narration']]; } }
        return compact('cong', 'summary', 'byType', 'ledgerOnly', 'pettyOnly', 'old');
    }

    private static function heading(array $labels): array { return array_map(function ($v) { return XlsxWriter::s($v, true); }, $labels); }

    public static function sheets(array $d): array
    {
        $summary = [self::heading(['Financial year', 'Account', 'Old report closing', 'New ledger closing', 'Difference'])];
        foreach ($d['summary'] as $r) { $summary[] = [XlsxWriter::s($r['year']), XlsxWriter::n($r['account']), XlsxWriter::m($r['old']), XlsxWriter::m($r['new']), XlsxWriter::m($r['difference'])]; }
        $types = [self::heading(['Financial year', 'Account', 'Transaction type', 'Old movement', 'Ledger movement', 'Difference'])];
        foreach ($d['byType'] as $r) { $types[] = [XlsxWriter::s($r['year']), XlsxWriter::n($r['account']), XlsxWriter::s($r['typeId'] . ' ' . $r['type']), XlsxWriter::m($r['old']), XlsxWriter::m($r['new']), XlsxWriter::m($r['difference'])]; }
        $detail = function (array $rows): array {
            $out = [self::heading(['Row ID', 'Date', 'Account', 'Transaction type', 'Source ID', 'Source number', 'Side', 'Amount', 'Reference', 'Narration'])];
            foreach ($rows as $r) { $out[] = [XlsxWriter::n($r['id']), XlsxWriter::d($r['date']), XlsxWriter::n($r['account']), XlsxWriter::s($r['typeId'] . ' ' . $r['type']), XlsxWriter::n($r['sourceId']), XlsxWriter::s($r['source']), XlsxWriter::s($r['side']), XlsxWriter::m($r['amount']), XlsxWriter::s($r['reference']), XlsxWriter::s($r['narration'])]; }
            return $out;
        };
        $old = [self::heading(['Financial year', 'Account', 'Opening', 'Receipts', 'Payments', 'Closing'])];
        foreach ($d['old'] as $r) { $old[] = [XlsxWriter::s($r['year']), XlsxWriter::n($r['account']), XlsxWriter::m($r['opening']), XlsxWriter::m($r['receipts']), XlsxWriter::m($r['payments']), XlsxWriter::m($r['closing'])]; }
        return [
            ['name' => 'Summary', 'rows' => $summary, 'widths' => [18, 12, 20, 20, 18]],
            ['name' => 'Difference by type', 'rows' => $types, 'widths' => [18, 12, 25, 18, 18, 18]],
            ['name' => 'Ledger lines only', 'rows' => $detail($d['ledgerOnly']), 'widths' => [12, 13, 12, 24, 12, 20, 12, 16, 24, 45]],
            ['name' => 'Petty cash rows only', 'rows' => $detail($d['pettyOnly']), 'widths' => [12, 13, 12, 24, 12, 20, 12, 16, 24, 45]],
            ['name' => 'Old report figures', 'rows' => $old, 'widths' => [18, 12, 16, 16, 16, 16]],
        ];
    }
}

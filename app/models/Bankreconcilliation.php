<?php

class Bankreconcilliation
{
    private $db;
    public function __construct()
    {
        $this->db =  new Database;
    }
    public function CheckRights($form)
    {
        if (getUserAccess($this->db->dbh,$_SESSION['userId'],$form,$_SESSION['isParish']) > 0) {
            return true;
        }else{
            return false;
        }
    }
    /** Report-safe banks of the session congregation: non-deleted, active or with history (inactive ones labelled). */
    public function getBanks()
    {
        return array_map(function($b){ return (object)['ID' => $b['id'],'Bank' => $b['label']]; },
            FinancialReport::bankOptions($this->db->pdo(),[(int)$_SESSION['congId']]));
    }
    public function isReportBank($id)
    {
        return FinancialReport::bankAllowed($this->db->pdo(),[(int)$_SESSION['congId']],(int)$id);
    }
    /**
     * Cashbook totals of one bank in [from, to] from tblbankpostings (cents). The scope (bank, congregation, not deleted, date range) is
     * stated once; a deposit is a row with debit > 0 and a withdrawal one with credit > 0, so a zero-amount row is in neither.
     * Cleared = cleared on or before `to`; uncleared = everything else in the range (never cleared, or cleared after `to`).
     * @return array{clearedDeposits:int,clearedWithdrawals:int,unclearedDeposits:int,unclearedWithdrawals:int}
     */
    private function postingTotals($bank, $from, $to)
    {
        $this->db->query('SELECT
                CAST(ROUND(IFNULL(SUM(CASE WHEN cleared = 1 AND IFNULL(clearedDare, DATE_ADD(:t1, INTERVAL 1 DAY)) <= :t1 AND debit  > 0 THEN debit  END),0) * 100) AS SIGNED) AS cd,
                CAST(ROUND(IFNULL(SUM(CASE WHEN cleared = 1 AND IFNULL(clearedDare, DATE_ADD(:t2, INTERVAL 1 DAY)) <= :t2 AND credit > 0 THEN credit END),0) * 100) AS SIGNED) AS cw,
                CAST(ROUND(IFNULL(SUM(CASE WHEN NOT (cleared = 1 AND IFNULL(clearedDare, DATE_ADD(:t3, INTERVAL 1 DAY)) <= :t3) AND debit  > 0 THEN debit  END),0) * 100) AS SIGNED) AS ud,
                CAST(ROUND(IFNULL(SUM(CASE WHEN NOT (cleared = 1 AND IFNULL(clearedDare, DATE_ADD(:t4, INTERVAL 1 DAY)) <= :t4) AND credit > 0 THEN credit END),0) * 100) AS SIGNED) AS uw
            FROM tblbankpostings
            WHERE deleted = 0 AND bankId = :bid AND congregationId = :cid AND transactionDate BETWEEN :tfrom AND :tto');
        foreach (['t1', 't2', 't3', 't4'] as $p) { $this->db->bind(':' . $p, $to); }
        $this->db->bind(':bid', $bank);
        $this->db->bind(':cid', $_SESSION['congId']);
        $this->db->bind(':tfrom', $from);
        $this->db->bind(':tto', $to);
        $r = $this->db->single();
        return ['clearedDeposits' => (int) $r->cd, 'clearedWithdrawals' => (int) $r->cw, 'unclearedDeposits' => (int) $r->ud, 'unclearedWithdrawals' => (int) $r->uw];
    }

    /** Book balance of the bank account (debit - credit of its journal lines, active entries, session congregation) up to a date, in cents. */
    public function bookBalance($bank, $asOf)
    {
        return FinancialReport::accountNet($this->db->pdo(), [(int) $_SESSION['congId']], (int) $bank, $asOf);
    }

    /**
     * Cashflow statement figures (cents). `opening` = the book balance entering `from` (journal through the previous day), `closing`
     * = the journal balance at `to`; the movements are the cashbook totals of tblbankpostings for the selected range.
     */
    public function getAmounts($data)
    {
        return $this->postingTotals($data['bank'], $data['from'], $data['to'])
             + ['opening' => $this->bookBalance($data['bank'], subtractDay($data['from'])),
                'closing' => $this->bookBalance($data['bank'], $data['to'])];
    }

    /** Reconciliation figures (cents): the cashbook totals plus `book` = the bank's journal balance at `to`. */
    public function getAmountsrecon($data)
    {
        return $this->postingTotals($data['bank'], $data['from'], $data['to'])
             + ['book' => $this->bookBalance($data['bank'], $data['to'])];
    }

    /**
     * Rows behind a cashbook total: the same scope and the same cleared / uncleared split as postingTotals(), listed.
     * $type: 'deposit' (debit > 0) or 'withdraw' (credit > 0); $cleared: true = cleared on or before the end date.
     */
    private function postingRows($type, $cleared, $data)
    {
        $col = $type === 'withdraw' ? 'credit' : 'debit';
        $split = $cleared ? '(cleared = 1 AND IFNULL(clearedDare, DATE_ADD(:tdate, INTERVAL 1 DAY)) <= :tdate)' : 'NOT (cleared = 1 AND IFNULL(clearedDare, DATE_ADD(:tdate, INTERVAL 1 DAY)) <= :tdate)';
        $this->db->query('SELECT transactionDate, CAST(ROUND(' . $col . ' * 100) AS SIGNED) AS cents, ucase(reference) AS reference
                          FROM tblbankpostings
                          WHERE deleted = 0 AND bankId = :bid AND congregationId = :cid AND transactionDate BETWEEN :sdate AND :edate
                                AND ' . $col . ' > 0 AND ' . $split . '
                          ORDER BY transactionDate, ID');
        $this->db->bind(':sdate', $data['sdate']);
        $this->db->bind(':edate', $data['edate']);
        $this->db->bind(':bid', $data['bankid']);
        $this->db->bind(':cid', $_SESSION['congId']);
        $this->db->bind(':tdate', $data['edate']);
        return array_map(function ($r) {
            return (object) ['transactionDate' => $r->transactionDate, 'amount' => FinancialReport::decimal((int) $r->cents), 'reference' => $r->reference];
        }, $this->db->resultSet());
    }

    public function UnclearedReport($data)
    {
        return $this->postingRows($data['type'] === 'withdraw' ? 'withdraw' : 'deposit', false, $data);
    }

    public function ClearedReport($data)
    {
        return $this->postingRows($data['type'] === 'withdraw' ? 'withdraw' : 'deposit', true, $data);
    }
}

<?php
class Banktransaction
{
    private $db;

    public function __construct()
    {
        $this->db =  new Database;
    }

    public function GetTransactions()
    {
        $sql = 'SELECT * FROM vw_banktransactions WHERE CongregationId = ?';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    /** transactionMethod of the bank row per transaction type: 1 deposit -> 1 Deposit, 2 withdrawal -> 2 Withdrawal, 5 transfer -> 1 (as before). */
    const BANK_METHODS = [1 => '1',2 => '2',5 => '1'];

    /** Cash-type accounts the user may pick as the deposit / withdrawal account (same rule as assertPaymentAccount). */
    public function GetCashAccounts()
    {
        $list = [];
        foreach (Ledger::listPaymentAccounts($this->db->pdo(),(int)$_SESSION['congId'],Ledger::PAY_CASH) as $a) {
            $list[] = (object)['ID' => $a['ID'],'Account' => strtoupper($a['accountType'])];
        }
        return $list;
    }

    /**
     * Server-side checks of the sub-account branches (the form only checks these in JS).
     *  - sub-account transfer: both sub-accounts belong to a bank of this congregation and differ;
     *  - deposit to sub-accounts: deposits only, each sub-account belongs to the chosen bank (of this
     *    congregation), no duplicates, amounts are numbers with at most 2 decimals, and they add up to
     *    the deposit. Blank / zero rows are dropped. Returns the cleaned list (or null when not a split).
     * Throws LedgerException.
     */
    private function checkSubaccounts($pdo,$cong,$data)
    {
        $sub = function($subId) use ($pdo,$cong){
            $row = loadresultset($pdo,'SELECT s.BankId FROM tblbanksubaccounts s JOIN tblaccounttypes a ON a.ID = s.BankId
                                       WHERE s.ID = ? AND s.Deleted = 0 AND a.isBank = 1 AND a.deleted = 0 AND a.congregationId = ?',
                                [(int)$subId,$cong]);
            return $row ? (int)$row[0]->BankId : null;
        };

        if ($data['issubtxn']) {
            if ($sub($data['bank']) === null || $sub($data['transfer']) === null) {
                throw new LedgerException(['Sub account not found for this congregation']);
            }
            if ((int)$data['bank'] === (int)$data['transfer']) {
                throw new LedgerException(['Transfering accounts cannot be same']);
            }
            return null;
        }
        if (empty($data['deposittosubs'])) {
            return null;
        }
        if ((int)$data['type'] !== 1) {
            throw new LedgerException(['Only deposits can be split over sub accounts']);
        }
        $clean = [];
        $seen = [];
        $total = 0.0;
        foreach ((array)($data['subaccounts'] ?? []) as $row) {
            $id = (int)($row->accountid ?? 0);
            $amount = trim((string)($row->amount ?? ''));
            if ($amount === '' || (is_numeric($amount) && (float)$amount == 0)) {
                continue;
            }
            if ($sub($id) !== (int)$data['bank']) {
                throw new LedgerException(['Sub account not found for the selected bank']);
            }
            if (isset($seen[$id])) {
                throw new LedgerException(['A sub account is listed more than once']);
            }
            $err = Ledger::amountError($amount,'Sub account amount');
            if ($err !== null) {
                throw new LedgerException([$err]);
            }
            $seen[$id] = true;
            $total += round((float)$amount,2);
            $clean[] = (object)['accountid' => $id,'amount' => $amount];
        }
        if (!$clean) {
            throw new LedgerException(['Enter the amounts for the sub accounts']);
        }
        if (abs(round($total,2) - round((float)$data['amount'],2)) >= 0.005) {
            throw new LedgerException(['Sub account amounts must add up to the deposit amount']);
        }
        return $clean;
    }

    /** Deposits and withdrawals need the user's cash-type account; throws LedgerException otherwise. Returns its ID or null. */
    private function cashAccountFor($pdo,$cong,$data)
    {
        if ($data['issubtxn'] || !in_array((int)$data['type'],[1,2],true)) {
            return null;
        }
        if (empty($data['cashaccount'])) {
            throw new LedgerException(['Select the crediting account']);
        }
        return Ledger::assertPaymentAccount($pdo,$cong,(int)$data['cashaccount'],Ledger::PAY_CASH);
    }

    /** Banks of this congregation that the Ledger accepts as bank accounts. */
    public function GetBanks()
    {
        $pdo = $this->db->pdo();
        $ok = array_column(Ledger::listPaymentAccounts($pdo,(int)$_SESSION['congId'],Ledger::PAY_BANK),'ID');
        $sql = "SELECT ID,UCASE(CONCAT(accountType,'-',IFNULL(accountNo,''))) AS Bank FROM tblaccounttypes WHERE isBank = 1 AND CongregationId = ? AND deleted=0";
        return array_values(array_filter(loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]),function($b) use ($ok){
            return in_array((int)$b->ID,$ok,true);
        }));
    }

    /** Transfer targets: the banks above plus the asset (cash-type) posting accounts, e.g. petty cash, fixed deposits. */
    public function GetOtherBanks()
    {
        $banks = $this->GetBanks();
        $bankids = array_map(function($b){ return (int)$b->ID; },$banks);
        $others = [];
        foreach (Ledger::listPaymentAccounts($this->db->pdo(),(int)$_SESSION['congId'],Ledger::PAY_CASH) as $a) {
            if (!in_array((int)$a['ID'],$bankids,true)) {
                $others[] = (object)['ID' => $a['ID'],'Bank' => strtoupper($a['accountType'])];
            }
        }
        return array_merge($banks,$others);
    }

    /**
     * The ledger entry of a main-account bank transaction (type 13), by account IDs.
     *   1 deposit:    Dr bank / Cr the chosen cash account
     *   2 withdrawal: Dr the chosen cash account / Cr bank
     *   5 transfer:   Cr bank / Dr the chosen account (a bank gets its own bank row)
     * then one tblbankpostings row per bank line.
     */
    private function postLedger($pdo,$cong,$tid,$data,$bank)
    {
        $type = (int)$data['type'];
        $amount = round((float)$data['amount'],2);
        $narr = !empty($data['description']) ? strtolower($data['description']) : 'bank transaction reference ' .$data['reference'];

        $bank = Ledger::assertPaymentAccount($pdo,$cong,(int)$bank,Ledger::PAY_BANK);
        if ($type === 1 || $type === 2) {
            $cash = Ledger::assertPaymentAccount($pdo,$cong,(int)$data['cashaccount'],Ledger::PAY_CASH);
            $lines = $type === 1
                ? [Ledger::debit($bank,$amount,$narr),Ledger::credit($cash,$amount,$narr)]
                : [Ledger::debit($cash,$amount,$narr),Ledger::credit($bank,$amount,$narr)];
        } elseif ($type === 5) {
            $to = (int)$data['transfer'];
            if ($to === $bank) {
                throw new LedgerException(['Transfering accounts cannot be same']);
            }
            $isbank = (int)getdbvalue($pdo,'SELECT isBank FROM tblaccounttypes WHERE ID = ?',[$to]) === 1;
            $to = Ledger::assertPaymentAccount($pdo,$cong,$to,$isbank ? Ledger::PAY_BANK : Ledger::PAY_CASH);
            $lines = [Ledger::debit($to,$amount,$narr),Ledger::credit($bank,$amount,$narr)];
        } else {
            throw new LedgerException(['Unsupported bank transaction type']);
        }

        Ledger::post($pdo,[
            'congregationId' => $cong,
            'entryDate' => $data['date'],
            'transactionTypeId' => 13,
            'transactionId' => (int)$tid,
            'reference' => $data['reference'],
            'description' => $narr
        ],$lines);
        Ledger::syncBankPostings($pdo,$cong,13,(int)$tid,self::BANK_METHODS[$type],$data['reference']);
    }

    /**
     * The tblpettycash receipt of a transfer to a petty cash account (98 or 194, Ledger::PETTY_CASH_ACCOUNTS); not a ledger table.
     * Kept in step with the transaction: created on save, updated in place on edit (it keeps its receipt number),
     * soft-deleted when the transfer is edited into something else. Numbers come from the same locked sequence as
     * the petty cash receipts screen and are never reused.
     */
    private function savePettyCash($data,$tid)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $existing = loadresultset($pdo,'SELECT ID FROM tblpettycash WHERE TransactionType = 13 AND TransactionId = ? AND CongregationId = ? AND Deleted = 0 FOR UPDATE',
                                  [(int)$tid,$cong]);

        // a sub-account transfer's "transfer to" is a sub-account ID, never a petty cash account
        $wanted = !$data['issubtxn'] && (int)$data['type'] === 5 && Ledger::isPettyCashAccount($data['transfer']);
        if(!$wanted){
            if($existing){
                $pdo->prepare('UPDATE tblpettycash SET Deleted = 1 WHERE ID = ?')->execute([$existing[0]->ID]);
            }
            return;
        }

        $narr = mb_substr(!empty($data['description']) ? strtolower($data['description']) : 'bank transaction reference ' .$data['reference'],0,150);
        if($existing){
            $pdo->prepare('UPDATE tblpettycash SET TransactionDate=?,Debit=?,BankId=?,PettyCashAccountId=?,Reference=?,Narration=? WHERE ID=?')
                ->execute([$data['date'],$data['amount'],$data['bank'],(int)$data['transfer'],strtolower($data['reference']),$narr,$existing[0]->ID]);
            return;
        }
        $pdo->prepare('INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,PettyCashAccountId,Reference,Narration,TransactionType,TransactionId,CongregationId)
                       VALUES(?,?,?,1,?,?,?,?,13,?,?)')
            ->execute([Ledger::nextNumber($pdo,$cong,Ledger::SEQ_PETTYCASH),$data['date'],$data['amount'],$data['bank'],(int)$data['transfer'],
                       strtolower($data['reference']),$narr,(int)$tid,$cong]);
    }

    private function fail($pdo,$e)
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof LedgerException) {
            return ['success' => false,'errors' => $e->getErrors()];
        }
        error_log($e->getMessage(),0);
        return ['success' => false,'errors' => []];
    }

    public function GetSubaccounts($bankid)
    {
        return loadresultset($this->db->dbh,'SELECT ID,UCASE(AccountName) AS SubAccount FROM tblbanksubaccounts WHERE (BankId=?) AND (Deleted=0)',[$bankid]);
    }

    public function GetAllSubaccounts()
    {
        return loadresultset($this->db->dbh,'SELECT ID,UCASE(AccountName) AS SubAccount,BankId FROM tblbanksubaccounts WHERE (Deleted=0)',[]);
    }

    function GetSubaccountName($id)
    {
        return getdbvalue($this->db->dbh,'SELECT AccountName FROM tblbanksubaccounts WHERE (ID=?)',[$id]);
    }

    function GetMainAccount($value)
    {
        // $subaccount = explode('-',$value)[0];
        return getdbvalue($this->db->dbh,'SELECT BankId FROM tblbanksubaccounts WHERE (ID=?)',[$value]);
    }

    /** Returns ['success' => bool, 'errors' => string[]] (empty errors on failure = unexpected error, logged). */
    public function Save($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $bank = $data['issubtxn'] ? $this->GetMainAccount($data['bank']) : $data['bank'];
            $from = $data['issubtxn'] ? $this->GetSubaccountName($data['bank']) : null;
            $to = $data['issubtxn'] ? $this->GetSubaccountName($data['transfer']) : null;

            $pdo->beginTransaction();
            $cash = $this->cashAccountFor($pdo,$cong,$data);
            if($split = $this->checkSubaccounts($pdo,$cong,$data)){
                $data['subaccounts'] = $split;
            }
            $this->db->query('INSERT INTO tblbanktransactions (TransactionDate,TransactionTypeId,BankId,Amount,TransferToId,CashAccountId,
                                                               Reference,`Description`,DepositToSubAccounts,IsSubInterTransfer,CongregationId)
                              VALUES(:ddate,:tid,:bid,:amount,:trans,:cash,:reference,:narr,:subs,:subinter, :cid)');
            $this->db->bind(':cash',$cash);
            $this->db->bind(':ddate',$data['date']);
            $this->db->bind(':tid',$data['type']);
            $this->db->bind(':bid',$bank);
            $this->db->bind(':amount',$data['amount']);
            $this->db->bind(':trans',$data['transfer']);
            $this->db->bind(':reference',$data['reference']);
            $this->db->bind(':narr',$data['description']);
            $this->db->bind(':subs',$data['deposittosubs']);
            $this->db->bind(':subinter',$data['issubtxn']);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();
            $tid = (int)$pdo->lastInsertId();

            if(!is_null($data['subaccounts'])){
                for ($i=0; $i < count($data['subaccounts']); $i++) {
                    $this->db->query('INSERT INTO `tblbanktransactions_subaccounts`(`TransactionDate`,`TransactionId`, `SubAccountId`, `Amount`,TransactionType,Narration,Reference,CongregationId)
                                      VALUES (:tdate,:tid,:sub,:amount,:ttype,:narr,:reference,:congid)');
                    $this->db->bind(':tdate',$data['date']);
                    $this->db->bind(':tid',$tid);
                    $this->db->bind(':sub',$data['subaccounts'][$i]->accountid);
                    $this->db->bind(':amount',$data['subaccounts'][$i]->amount);
                    $this->db->bind(':ttype',13);
                    $this->db->bind(':narr','sub-account deposit');
                    $this->db->bind(':reference',$data['reference']);
                    $this->db->bind(':congid',$_SESSION['congId']);
                    $this->db->execute();
                }
            }

            if($data['issubtxn']){
                $this->db->query('INSERT INTO `tblbanktransactions_subaccounts`(`TransactionDate`,`TransactionId`, `SubAccountId`, `Amount`,FromAccountId,TransactionType,Narration,Reference,CongregationId)
                                  VALUES (:tdate,:tid,:sub,:amount,:fromacc,:ttype,:narr,:reference,:congid)');
                $this->db->bind(':tdate',$data['date']);
                $this->db->bind(':tid',$tid);
                $this->db->bind(':sub',$data['transfer']);
                $this->db->bind(':amount',$data['amount']);
                $this->db->bind(':fromacc',$data['bank']);
                $this->db->bind(':ttype',13);
                $this->db->bind(':narr','Receipt from ' . $from);
                $this->db->bind(':reference',$data['reference']);
                $this->db->bind(':congid',$_SESSION['congId']);
                $this->db->execute();

                $this->db->query('INSERT INTO `tblbanktransactions_subaccounts`(`TransactionDate`,`TransactionId`, `SubAccountId`, `Amount`,ToAccountId,TransactionType,Narration,Reference,CongregationId)
                                  VALUES (:tdate,:tid,:sub,:amount,:toacc,:ttype,:narr,:reference,:congid)');
                $this->db->bind(':tdate',$data['date']);
                $this->db->bind(':tid',$tid);
                $this->db->bind(':sub',$data['bank']);
                $this->db->bind(':amount',$data['amount'] * -1);
                $this->db->bind(':toacc',$data['transfer']);
                $this->db->bind(':ttype',13);
                $this->db->bind(':narr','Transfer to ' . $to);
                $this->db->bind(':reference',$data['reference']);
                $this->db->bind(':congid',$_SESSION['congId']);
                $this->db->execute();
            }

            if(!$data['issubtxn']){
                $this->postLedger($pdo,$cong,$tid,$data,$bank);
            }

            $this->savePettyCash($data,$tid);

            saveLog($pdo,'Created bank transaction '.$tid);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $th) {
            return $this->fail($pdo,$th);
        }
    }

    public function GetSavedSubsAccounts($id)
    {
        $sql = 'SELECT s.SubAccountId,ucase(b.AccountName) As Account
                FROM `tblbanktransactions_subaccounts` s join tblbanksubaccounts b on s.SubAccountId = b.ID
                WHERE s.TransactionId = ?';
        return loadresultset($this->db->dbh,$sql,[$id]);
    }

    /** Returns ['success' => bool, 'errors' => string[]] (empty errors on failure = unexpected error, logged). */
    public function Update($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {

            $bank = $data['issubtxn'] ? $this->GetMainAccount($data['bank']) : $data['bank'];
            $from = $data['issubtxn'] ? $this->GetSubaccountName($data['bank']) : null;
            $to = $data['issubtxn'] ? $this->GetSubaccountName($data['transfer']) : null;

            $pdo->beginTransaction();
            $owner = getdbvalue($pdo,'SELECT CongregationId FROM tblbanktransactions WHERE ID = ? AND Deleted = 0',[(int)$data['id']]);
            if($owner === false || $owner === null || (int)$owner !== $cong){
                $pdo->rollBack();
                return ['success' => false,'errors' => ['Transaction not found for this congregation']];
            }

            $cash = $this->cashAccountFor($pdo,$cong,$data);
            if($split = $this->checkSubaccounts($pdo,$cong,$data)){
                $data['subaccounts'] = $split;
            }
            $this->db->query('UPDATE tblbanktransactions SET TransactionDate=:ddate,TransactionTypeId=:tid,BankId=:bid,Amount=:amount,
                                                             TransferToId=:trans,CashAccountId=:cash,Reference=:reference,`Description`=:narr
                              WHERE (ID=:id)');
            $this->db->bind(':cash',$cash);
            $this->db->bind(':ddate',$data['date']);
            $this->db->bind(':tid',$data['type']);
            $this->db->bind(':bid',$bank);
            $this->db->bind(':amount',$data['amount']);
            $this->db->bind(':trans',$data['transfer']);
            $this->db->bind(':reference',$data['reference']);
            $this->db->bind(':narr',$data['description']);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();

            $this->db->query('DELETE FROM tblbanktransactions_subaccounts WHERE (TransactionId = :tid)');
            $this->db->bind(':tid',$data['id']);
            $this->db->execute();


            if(!is_null($data['subaccounts'])){
                for ($i=0; $i < count($data['subaccounts']); $i++) {
                    // same columns as Save(), so an edited deposit keeps its date, type, narration and reference
                    $this->db->query('INSERT INTO `tblbanktransactions_subaccounts`(`TransactionDate`,`TransactionId`, `SubAccountId`, `Amount`,TransactionType,Narration,Reference,CongregationId)
                                    VALUES (:tdate,:tid,:sub,:amount,:ttype,:narr,:reference,:congid)');
                    $this->db->bind(':tdate',$data['date']);
                    $this->db->bind(':tid',$data['id']);
                    $this->db->bind(':sub',$data['subaccounts'][$i]->accountid);
                    $this->db->bind(':amount',$data['subaccounts'][$i]->amount);
                    $this->db->bind(':ttype',13);
                    $this->db->bind(':narr','sub-account deposit');
                    $this->db->bind(':reference',$data['reference']);
                    $this->db->bind(':congid',$_SESSION['congId']);
                    $this->db->execute();
                }
            }

            if($data['issubtxn']){
                $this->db->query('INSERT INTO `tblbanktransactions_subaccounts`(`TransactionDate`,`TransactionId`, `SubAccountId`, `Amount`,FromAccountId,TransactionType,Narration,Reference,CongregationId)
                                  VALUES (:tdate,:tid,:sub,:amount,:fromacc,:ttype,:narr,:reference,:congid)');
                $this->db->bind(':tdate',$data['date']);
                $this->db->bind(':tid',$data['id']);
                $this->db->bind(':sub',$data['transfer']);
                $this->db->bind(':amount',$data['amount']);
                $this->db->bind(':fromacc',$data['bank']);
                $this->db->bind(':ttype',13);
                $this->db->bind(':narr','Receipt from ' . $from);
                $this->db->bind(':reference',$data['reference']);
                $this->db->bind(':congid',$_SESSION['congId']);
                $this->db->execute();

                $this->db->query('INSERT INTO `tblbanktransactions_subaccounts`(`TransactionDate`,`TransactionId`, `SubAccountId`, `Amount`,ToAccountId,TransactionType,Narration,Reference,CongregationId)
                                  VALUES (:tdate,:tid,:sub,:amount,:toacc,:ttype,:narr,:reference,:congid)');
                $this->db->bind(':tdate',$data['date']);
                $this->db->bind(':tid',$data['id']);
                $this->db->bind(':sub',$data['bank']);
                $this->db->bind(':amount',$data['amount'] * -1);
                $this->db->bind(':toacc',$data['transfer']);
                $this->db->bind(':ttype',13);
                $this->db->bind(':narr','Transfer to ' . $to);
                $this->db->bind(':reference',$data['reference']);
                $this->db->bind(':congid',$_SESSION['congId']);
                $this->db->execute();
            }

            if($data['issubtxn']){
                // sub-account transfers post nothing to the ledger; drop any earlier entry
                Ledger::void($pdo,$cong,13,(int)$data['id']);
                Ledger::voidBankPostings($pdo,$cong,13,(int)$data['id']);
            }else{
                $this->postLedger($pdo,$cong,(int)$data['id'],$data,$bank);
            }

            $this->savePettyCash($data,$data['id']);

            saveLog($pdo,'Updated bank transaction '.(int)$data['id']);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $th) {
            return $this->fail($pdo,$th);
        }
    }

    public function CreateUpdate($data)
    {
        if(!$data['isedit']){
            return $this->Save($data);
        }else{
            return $this->Update($data);
        }
    }

    public function GetTransaction($id)
    {
        $this->db->query('SELECT * FROM tblbanktransactions WHERE ID=:id AND Deleted=0');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }

    /** Soft delete + void. Returns ['success' => bool, 'errors' => string[]]. */
    public function Delete($id)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $owner = getdbvalue($pdo,'SELECT CongregationId FROM tblbanktransactions WHERE ID = ? AND Deleted = 0',[(int)$id]);
            if($owner === false || $owner === null || (int)$owner !== $cong){
                $pdo->rollBack();
                return ['success' => false,'errors' => ['Transaction not found for this congregation']];
            }

            $this->db->query('UPDATE tblbanktransactions SET Deleted = 1
                              WHERE (ID=:id)');
            $this->db->bind(':id',$id);
            $this->db->execute();

            Ledger::void($pdo,$cong,13,(int)$id);
            Ledger::voidBankPostings($pdo,$cong,13,(int)$id);

            $this->db->query('UPDATE tblpettycash SET Deleted=1 WHERE (TransactionType = 13) AND (TransactionId=:tid)');
            $this->db->bind(':tid',$id);
            $this->db->execute();

            saveLog($pdo,'Deleted bank transaction '.(int)$id);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $th) {
            return $this->fail($pdo,$th);
        }
    }
}

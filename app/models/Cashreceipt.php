<?php
class Cashreceipt
{
    private $db;

    /** Method name of the bank row of a petty cash receipt (tbltransactionmethods 2): money leaves the bank. */
    const BANK_METHOD = 'Withdrawal';
    /** tblpettycash.Reference / Narration lengths */
    const REFERENCE_LEN = 50;
    const NARRATION_LEN = 150;
    /** 'petty cash', preselected on a new receipt */
    const DEFAULT_PETTY_ACCOUNT = 98;

    public function __construct()
    {
        $this->db = new Database;
    }

    /** Own petty cash receipts only (vw_cashreceipts); receipts created by a bank transfer are managed under Bank Transactions. */
    public function GetReceipts()
    {
        $this->db->query('SELECT * FROM vw_cashreceipts WHERE CongregationId = :id');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    /** Number to SHOW on a new receipt (the real number is allocated when it is saved). */
    public function GetReceiptNo()
    {
        return Ledger::peekNextNumber($this->db->pdo(),(int)$_SESSION['congId'],Ledger::SEQ_PETTYCASH);
    }

    /** Banks of this congregation that the Ledger accepts as bank accounts. */
    public function GetBanks()
    {
        $pdo = $this->db->pdo();
        $ok = array_column(Ledger::listPaymentAccounts($pdo,(int)$_SESSION['congId'],Ledger::PAY_BANK),'ID');
        $rows = loadresultset($pdo,"SELECT ID,CONCAT(UCASE(accountType),'-',IFNULL(`accountNo`,'')) AS BankName
                                    FROM tblaccounttypes WHERE `isBank` = 1 AND CongregationId = ? AND deleted = 0",[(int)$_SESSION['congId']]);
        return array_values(array_filter($rows,function($b) use ($ok){
            return in_array((int)$b->ID,$ok,true);
        }));
    }

    /** Petty cash accounts (Ledger::PETTY_CASH_ACCOUNTS) this congregation may use. */
    public function GetPettyCashAccounts()
    {
        $list = [];
        foreach (Ledger::listPettyCashAccounts($this->db->pdo(),(int)$_SESSION['congId']) as $a) {
            $list[] = (object)['ID' => $a['ID'],'Account' => strtoupper($a['accountType'])];
        }
        return $list;
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

    /**
     * Checks the form values. Returns the clean values; throws LedgerException with every problem.
     */
    private function check($pdo,$cong,$data)
    {
        $errors = [];

        $date = (string)($data['date'] ?? '');
        if (!date_is_valid($date)) {
            $errors[] = 'Enter a valid date';
        } elseif ($date > date('Y-m-d')) {
            $errors[] = 'Invalid date';
        } else {
            if ($problem = fiscalYearDateProblem($pdo,$date)) {
                $errors[] = $problem;
            }
        }

        $amount = trim((string)($data['amount'] ?? ''));
        if ($err = Ledger::amountError($amount)) {
            $errors[] = $err;
        }

        $reference = trim((string)($data['reference'] ?? ''));
        if ($reference === '') {
            $errors[] = 'Enter cheque no';
        } elseif (strlen($reference) > self::REFERENCE_LEN) {
            $errors[] = 'Reference cannot be longer than ' . self::REFERENCE_LEN . ' characters';
        }

        $bank = 0;
        if (empty($data['bank'])) {
            $errors[] = 'Select bank';
        } else {
            try {
                $bank = Ledger::assertPaymentAccount($pdo,$cong,(int)$data['bank'],Ledger::PAY_BANK);
            } catch (LedgerException $e) {
                $errors = array_merge($errors,$e->getErrors());
            }
        }

        $petty = 0;
        if (empty($data['pettycashaccount'])) {
            $errors[] = 'Select the petty cash account';
        } else {
            try {
                $petty = Ledger::assertPettyCashAccount($pdo,$cong,(int)$data['pettycashaccount']);
            } catch (LedgerException $e) {
                $errors = array_merge($errors,$e->getErrors());
            }
        }

        if ($errors) {
            throw new LedgerException($errors);
        }

        $narr = mb_substr(!empty(trim((string)($data['description'] ?? ''))) ? strtolower(trim($data['description'])) : 'petty cash receipt - '.$date,0,self::NARRATION_LEN);
        return ['date' => $date,'amount' => number_format((float)$amount,2,'.',''),'reference' => $reference,
                'bank' => $bank,'petty' => $petty,'narr' => $narr];
    }

    /** Dr the petty cash account / Cr the bank, then the bank row (a credit, method Withdrawal). */
    private function post($pdo,$cong,$id,$v)
    {
        Ledger::post($pdo,[
            'congregationId' => $cong,
            'entryDate' => $v['date'],
            'transactionTypeId' => 10,
            'transactionId' => (int)$id,
            'reference' => $v['reference'],
            'description' => $v['narr']
        ],[
            Ledger::debit($v['petty'],$v['amount'],$v['narr']),
            Ledger::credit($v['bank'],$v['amount'],$v['narr']),
        ]);
        Ledger::syncBankPostings($pdo,$cong,10,(int)$id,self::BANK_METHOD,$v['reference']);
    }

    /** Message when the receipt's current date is in a closed financial year (deleted or not), else null. */
    private function closedYearProblem($pdo,$date)
    {
        $year = getYearForDate($pdo,$date);
        return $year && converttobool($year->closed) ? 'The financial year '.strtoupper($year->yearName).' is closed' : null;
    }

    /** Own, active petty cash receipt of this congregation (row locked), or null. */
    private function lockReceipt($pdo,$cong,$id)
    {
        $row = loadresultset($pdo,'SELECT ID,TransactionDate FROM tblpettycash
                                   WHERE ID=? AND CongregationId=? AND Deleted=0 AND IsReceipt=1 AND TransactionType IS NULL FOR UPDATE',
                             [(int)$id,$cong]);
        return $row ? $row[0] : null;
    }

    /** Returns ['success'=>bool,'errors'=>string[]] (no errors on failure = unexpected error, logged). */
    public function Save($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $v = $this->check($pdo,$cong,$data);

            $this->db->query('INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,PettyCashAccountId,Reference,Narration,CongregationId)
                              VALUES(:rno,:tdate,:debit,:isreceipt,:bankid,:petty,:reference,:narr,:cid)');
            $this->db->bind(':rno',Ledger::nextNumber($pdo,$cong,Ledger::SEQ_PETTYCASH));
            $this->db->bind(':tdate',$v['date']);
            $this->db->bind(':debit',$v['amount']);
            $this->db->bind(':isreceipt',true);
            $this->db->bind(':bankid',$v['bank']);
            $this->db->bind(':petty',$v['petty']);
            $this->db->bind(':reference',strtolower($v['reference']));
            $this->db->bind(':narr',$v['narr']);
            $this->db->bind(':cid',$cong);
            $this->db->execute();
            $tid = (int)$pdo->lastInsertId();

            $this->post($pdo,$cong,$tid,$v);

            saveLog($pdo,'Created petty cash receipt '.$tid);
            $pdo->commit();
            return ['success' => true,'errors' => [],'id' => $tid];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }

    public function Update($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $id = (int)($data['id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $old = $id > 0 ? $this->lockReceipt($pdo,$cong,$id) : null;
            if ($old === null) {
                $pdo->rollBack();
                return ['success' => false,'errors' => ['Petty cash receipt not found for this congregation']];
            }
            if ($problem = $this->closedYearProblem($pdo,$old->TransactionDate)) {
                $pdo->rollBack();
                return ['success' => false,'errors' => [$problem]];
            }
            $v = $this->check($pdo,$cong,$data);

            $this->db->query('UPDATE tblpettycash SET TransactionDate=:tdate,Debit=:debit,BankId=:bankid,PettyCashAccountId=:petty,
                                     Reference=:reference,Narration=:narr WHERE(ID=:id)');
            $this->db->bind(':tdate',$v['date']);
            $this->db->bind(':debit',$v['amount']);
            $this->db->bind(':bankid',$v['bank']);
            $this->db->bind(':petty',$v['petty']);
            $this->db->bind(':reference',strtolower($v['reference']));
            $this->db->bind(':narr',$v['narr']);
            $this->db->bind(':id',$id);
            $this->db->execute();

            $this->post($pdo,$cong,$id,$v);

            saveLog($pdo,'Updated petty cash receipt '.$id);
            $pdo->commit();
            return ['success' => true,'errors' => [],'id' => $id];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
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

    /** Own, active petty cash receipt of this congregation, or false. */
    public function GetReceipt($id)
    {
        $this->db->query('SELECT * FROM tblpettycash
                          WHERE ID = :id AND CongregationId = :cid AND Deleted = 0 AND IsReceipt = 1 AND TransactionType IS NULL');
        $this->db->bind(':id', (int)$id);
        $this->db->bind(':cid', $_SESSION['congId']);
        return $this->db->single();
    }

    /** Soft delete + void. Returns ['success'=>bool,'errors'=>string[]]. */
    public function Delete($id)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $id = (int)$id;
        try {
            $pdo->beginTransaction();
            $old = $id > 0 ? $this->lockReceipt($pdo,$cong,$id) : null;
            if ($old === null) {
                $pdo->rollBack();
                return ['success' => false,'errors' => ['Petty cash receipt not found for this congregation']];
            }
            if ($problem = $this->closedYearProblem($pdo,$old->TransactionDate)) {
                $pdo->rollBack();
                return ['success' => false,'errors' => [$problem]];
            }

            $pdo->prepare('UPDATE tblpettycash SET Deleted = 1 WHERE ID = ?')->execute([$id]);
            Ledger::void($pdo,$cong,10,$id);
            Ledger::voidBankPostings($pdo,$cong,10,$id);

            saveLog($pdo,'Deleted petty cash receipt '.$id);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }
}

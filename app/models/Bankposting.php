<?php

class Bankposting
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    public function CheckRights($form)
    {
        if (getUserAccess($this->db->dbh,$_SESSION['userId'],$form,$_SESSION['isParish']) > 0) {
            return true;
        }else{
            return false;
        }
    }
    public function getPostings()
    {
        $this->db->query('SELECT b.ID,
		                         b.transactionDate,
                                 ucase(a.accountType) As Bank,
                                 IF(b.debit > 0,b.debit,b.credit) As Amount,
                                 ucase(b.reference) As reference
                          FROM   tblbankpostings b inner join tblaccounttypes a on b.bankId = a.ID
                          WHERE  (b.transactionType = 9) AND (b.deleted=0) AND (b.congregationId=:cid)
                          ORDER BY b.ID DESC');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getBanks()
    {
        $this->db->query("SELECT   ID,
                                   CONCAT(UCASE(`accountType`),'-',IFNULL(`accountNo`,'')) As Bank
                          FROM     tblaccounttypes
                          WHERE    (isBank=1) AND (Deleted=0) AND (congregationId=:cid)");
        $this->db->bind(':cid',$_SESSION['congId']);
        $ok = array_column(Ledger::listPaymentAccounts($this->db->pdo(),(int)$_SESSION['congId'],Ledger::PAY_BANK),'ID');
        return array_values(array_filter($this->db->resultSet(),function($b) use ($ok){
            return in_array((int)$b->ID,$ok,true);
        }));
    }
    public function getMethods()
    {
        $this->db->query('SELECT   ID,
                                   UCASE(methodName) As methodName
                          FROM     tbltransactionmethods
                          WHERE    (ID < 4)');
        return $this->db->resultSet();
    }
    /**
     * Contra accounts: posting accounts only (no banks, no reporting accounts),
     * own congregation plus shared accounts. Same rule create()/update() enforce.
     */
    public function getAccounts()
    {
        $cong = (int)$_SESSION['congId'];
        $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes
                          WHERE (deleted=0) AND (active=1) AND (isBank = 0) AND (parentId <> 0)
                                AND (congregationId IN (0,:cid)) ORDER BY accountType');
        $this->db->bind(':cid',$cong);
        $pdo = $this->db->pdo();
        return array_values(array_filter($this->db->resultSet(),function($a) use ($pdo,$cong){
            return Ledger::isPostingAccount($pdo,(int)$a->ID,$cong);
        }));
    }

    /** Checks bank and contra account; throws LedgerException. */
    private function assertAccounts($pdo,$cong,$data)
    {
        $err = Ledger::amountError($data['amount']);
        if ($err !== null) {
            throw new LedgerException([$err]);
        }
        if (!in_array((int)$data['type'],[1,2,3],true)) {
            throw new LedgerException(['Select a valid transaction type']);
        }
        $bank = Ledger::assertPaymentAccount($pdo,$cong,(int)$data['bank'],Ledger::PAY_BANK);
        $account = (int)$data['account'];
        if (!Ledger::isPostingAccount($pdo,$account,$cong)) {
            throw new LedgerException(['The G/L account selected cannot be posted to']);
        }
        $isbank = getdbvalue($pdo,'SELECT isBank FROM tblaccounttypes WHERE ID = ?',[$account]);
        if ((int)$isbank === 1) {
            throw new LedgerException(['A bank cannot be the G/L account; use a bank transaction to move money between banks']);
        }
        return [$bank,$account];
    }

    /** Dr bank / Cr contra for a deposit (method 1), Dr contra / Cr bank otherwise. */
    private function postLedger($pdo,$cong,$tid,$data,$bank,$account)
    {
        $amount = round((float)$data['amount'],2);
        $memo = !empty($data['narration']) ? strtolower($data['narration']) : $data['reference'];
        $bankline = (int)$data['type'] === 1 ? Ledger::debit($bank,$amount,$memo) : Ledger::credit($bank,$amount,$memo);
        $contra = (int)$data['type'] === 1 ? Ledger::credit($account,$amount,$memo) : Ledger::debit($account,$amount,$memo);
        Ledger::post($pdo,[
            'congregationId' => $cong,
            'entryDate' => $data['date'],
            'transactionTypeId' => 9,
            'transactionId' => $tid,
            'reference' => $data['reference'],
            'description' => $memo
        ],[$bankline,$contra]);
    }

    /** Returns ['success' => bool, 'errors' => string[]] (empty errors on failure = unexpected error, logged). */
    public function create($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $transtype = (int)$data['type'];
        try {
            $pdo->beginTransaction();
            list($bank,$account) = $this->assertAccounts($pdo,$cong,$data);
            $amount = round((float)$data['amount'],2);

            // the bank posting row is the source record of this entry (no syncBankPostings)
            $this->db->query('INSERT INTO tblbankpostings (bankId,transactionDate,debit,credit,transactionMethod,reference,
                                                accountId,narration,transactionType,congregationId)
                              VALUES(:bid,:tdate,:debit,:credit,:method,:ref,:aid,:narr,:ttype,:cid)');
            $this->db->bind(':bid',$bank);
            $this->db->bind(':tdate',$data['date']);
            $this->db->bind(':debit',$transtype === 1 ? $amount : 0);
            $this->db->bind(':credit',$transtype > 1 ? $amount : 0);
            $this->db->bind(':method',$transtype);
            $this->db->bind(':ref',$data['reference']);
            $this->db->bind(':aid',$account);
            $this->db->bind(':narr',!empty($data['narration']) ? strtolower($data['narration']) : NULL);
            $this->db->bind(':ttype',9);
            $this->db->bind(':cid',$cong);
            $this->db->execute();
            $tid = (int)$pdo->lastInsertId();

            $this->postLedger($pdo,$cong,$tid,$data,$bank,$account);

            saveLog($pdo,'Created bank posting '.$tid);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => []];
        }
    }

    const NOT_MINE = 'This bank posting was created by another transaction and can only be changed there.';

    /** The active type-9 row of this congregation, or null (any other row cannot be changed here). */
    private function findPosting($id)
    {
        $this->db->query('SELECT * FROM tblbankpostings
                          WHERE (ID=:id) AND (transactionType = 9) AND (deleted = 0) AND (congregationId = :cid)');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        $row = $this->db->single();
        return $row ? $row : null;
    }

    /** Posting row for the edit form (encrypted id), or null when it may not be edited here. */
    public function edit($id)
    {
        return $this->findPosting(decryptId($id));
    }

    /** Returns ['success' => bool, 'errors' => string[]]. */
    public function update($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $transtype = (int)$data['type'];
        try {
            $pdo->beginTransaction();
            $old = $this->findPosting($data['id']);
            if (!$old) {
                $pdo->rollBack();
                return ['success' => false,'errors' => [self::NOT_MINE]];
            }
            list($bank,$account) = $this->assertAccounts($pdo,$cong,$data);
            $amount = round((float)$data['amount'],2);

            // bank, side or amount changed -> no longer the cleared row
            $oldSide = (float)$old->debit > 0 ? 'D' : 'C';
            $newSide = $transtype === 1 ? 'D' : 'C';
            $oldAmount = (float)$old->debit > 0 ? (float)$old->debit : (float)$old->credit;
            $changed = (int)$old->bankId !== $bank || $oldSide !== $newSide || abs($oldAmount - $amount) >= 0.005;

            $this->db->query('UPDATE tblbankpostings SET bankId=:bid,transactionDate=:tdate,debit=:debit,credit=:credit,
                                               transactionMethod=:method,reference=:ref,accountId=:aid,narration=:narr'
                             .($changed ? ',cleared=0,clearedDare=NULL' : '').'
                              WHERE (ID=:id)');
            $this->db->bind(':bid',$bank);
            $this->db->bind(':tdate',$data['date']);
            $this->db->bind(':debit',$transtype === 1 ? $amount : 0);
            $this->db->bind(':credit',$transtype > 1 ? $amount : 0);
            $this->db->bind(':method',$transtype);
            $this->db->bind(':ref',$data['reference']);
            $this->db->bind(':aid',$account);
            $this->db->bind(':narr',!empty($data['narration']) ? strtolower($data['narration']) : NULL);
            $this->db->bind(':id',(int)$data['id']);
            $this->db->execute();

            $this->postLedger($pdo,$cong,(int)$data['id'],$data,$bank,$account);

            saveLog($pdo,'Updated bank posting '.(int)$data['id']);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => []];
        }
    }

    /** Soft delete + void. Returns ['success' => bool, 'errors' => string[]]. */
    public function delete($id)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            if (!$this->findPosting($id)) {
                $pdo->rollBack();
                return ['success' => false,'errors' => [self::NOT_MINE]];
            }
            $this->db->query('UPDATE tblbankpostings SET deleted=1 WHERE (ID=:id)');
            $this->db->bind(':id',(int)$id);
            $this->db->execute();

            Ledger::void($pdo,$cong,9,(int)$id);

            saveLog($pdo,'Deleted bank posting '.(int)$id);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => []];
        }
    }
}

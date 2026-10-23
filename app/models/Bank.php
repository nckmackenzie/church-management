<?php
class Bank {
    private $db;
    const OPENING_BALANCE_EQUITY_ID = 20; // tblaccounttypes.ID of 'opening balance equity'
    const OPENING_BALANCE_NARRATION = 'opening balance equity';
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

    public function GetSubaccounts()
    {
        $sql = 'SELECT 
                    s.ID,
                    s.AccountName
                FROM `tblbanksubaccounts` s join tblaccounttypes a on s.BankId = a.ID
                WHERE (a.congregationId = ?) AND (s.Deleted = 0)
                ORDER BY s.AccountName;';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function getBanks()
    {
        $this->db->query('SELECT ID,
                                 UCASE(accountType) as accountType,
                                 UCASE(accountNo) As accountNo 
                          FROM tblaccounttypes
                          WHERE (isBank=1) AND (congregationId=:cid) AND (deleted=0)');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function checkExists($name, $id)
    {
        $this->db->query('SELECT ID FROM tblaccounttypes WHERE (accountNo=:ame) AND (ID <> :id)');
        $this->db->bind(':ame',$name);
        $this->db->bind(':id',$id);
        $this->db->execute();
        if ($this->db->rowCount() > 0) {
           return false;
        }else{
            return true;
        }
    }
    /**
     * Create a bank (and its opening balance). Returns
     * ['success' => bool, 'errors' => string[]]; an empty errors list on failure
     * means an unexpected error (logged).
     */
    /**
     * Validate the opening balance typed on the add-bank form. Returns an error
     * message, or null when it is acceptable. Blank / 0 means no opening balance.
     */
    public static function openingBalanceError($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/',$value)) {
            return 'Opening balance must be a number.';
        }
        if ($value[0] === '-' && (float)$value != 0) {
            return 'Opening balance cannot be negative. Enter an overdrawn balance later with a journal.';
        }
        $decimals = strpos($value,'.') === false ? '' : rtrim(substr($value,strpos($value,'.') + 1),'0');
        if (strlen($decimals) > 2) {
            return 'Opening balance cannot have more than 2 decimal places.';
        }
        return null;
    }

    public function create($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $error = self::openingBalanceError($data['openingbal']);
        if ($error !== null) {
            return ['success' => false,'errors' => [$error]];
        }
        try {
            //start transaction
            $pdo->beginTransaction();
            $this->db->query('INSERT INTO tblaccounttypes (accountType,accountTypeId,parentId,
                              isBank,accountNo,congregationId,isSubCategory) VALUES(:bname,:tid,:pid,:isbank,:acc,:cid,:issub)');
            $this->db->bind(':bname',$data['bankname']);
            $this->db->bind(':tid',3);
            $this->db->bind(':pid',33);
            $this->db->bind(':isbank',1);
            $this->db->bind(':acc',!empty($data['account']) ? $data['account'] : NULL);
            $this->db->bind(':cid',$cong);
            $this->db->bind(':issub',1);
            $this->db->execute();
            $tid = (int)$pdo->lastInsertId();

            // zero / blank opening balance posts nothing
            if ((float)$data['openingbal'] > 0) {
                Ledger::post($pdo,[
                    'congregationId' => $cong,
                    'entryDate' => $data['asof'],
                    'transactionTypeId' => 8,
                    'transactionId' => $tid,
                    'reference' => self::OPENING_BALANCE_NARRATION,
                    'description' => self::OPENING_BALANCE_NARRATION
                ],[
                    Ledger::debit($tid,$data['openingbal'],self::OPENING_BALANCE_NARRATION),
                    Ledger::credit(self::OPENING_BALANCE_EQUITY_ID,$data['openingbal'],self::OPENING_BALANCE_NARRATION)
                ]);
                Ledger::syncBankPostings($pdo,$cong,8,$tid,'B/F');
                // the opening balance is the statement's starting balance: created reconciled
                $pdo->prepare('UPDATE tblbankpostings SET cleared = 1, clearedDare = ?
                               WHERE transactionType = 8 AND transactionId = ? AND congregationId = ? AND deleted = 0')
                    ->execute([$data['asof'],$tid,$cong]);
            }
            //save logs
            $act = 'Created Bank '.$data['bankname'];
            saveLog($pdo,$act);

            //commit transaction
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => []];
        }
    }
    public function getbank($id)
    {
        $this->db->query('SELECT * FROM tblaccounttypes WHERE (ID=:id) AND (isBank=1)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblaccounttypes SET accountType=:typ,accountNo=:acc WHERE (ID=:id)');
        $this->db->bind(':typ',$data['bankname']);
        $this->db->bind(':acc',!empty($data['account']) ? $data['account'] : NULL);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Updated Bank '.$data['bankname'];
            saveLog($this->db->dbh,$act);
            return true;
        }else{
            return false;
        }
    }

    /**
     * Reasons the bank cannot be deleted (empty = it can). Only the bank's own
     * opening balance (type 8, transactionId = bank id) is ignored.
     */
    public function checkreferenced($id)
    {
        $id = (int)$id;
        $cong = (int)$_SESSION['congId'];
        $reasons = [];

        $count = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblbankpostings
                                            WHERE bankId = ? AND deleted = 0
                                              AND NOT (transactionType = 8 AND transactionId = ?)',[$id,$id]);
        if((int)$count > 0){
            $reasons[] = 'Cannot delete: the bank has '.(int)$count.' active bank posting(s) other than its opening balance';
        }

        $count = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM journal_lines l
                                            JOIN journal_entries e ON e.ID = l.journalEntryId
                                            WHERE l.accountId = ? AND e.deleted = 0
                                              AND NOT (e.transactionTypeId = 8 AND e.transactionId = ? AND e.congregationId = ?)',
                            [$id,$id,$cong]);
        if((int)$count > 0){
            $reasons[] = 'Cannot delete: the bank is used on '.(int)$count.' active ledger line(s) other than its opening balance';
        }
        $cleared = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblbankpostings
                                              WHERE bankId = ? AND transactionType = 8 AND transactionId = ?
                                                AND congregationId = ? AND deleted = 0 AND cleared = 1',[$id,$id,$cong]);
        if((int)$cleared > 0){
            $reasons[] = 'Cannot delete: the opening balance has been reconciled. Correct it with a journal instead.';
        }
        return $reasons;
    }

    /** Returns ['success' => bool, 'errors' => string[]] (empty errors on failure = unexpected error, logged). */
    public function delete($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $id = (int)$data['id'];
        try {
            //start transaction
            $pdo->beginTransaction();

            $owner = getdbvalue($pdo,'SELECT congregationId FROM tblaccounttypes WHERE ID = ? AND isBank = 1 AND deleted = 0',[$id]);
            if($owner === false || $owner === null || (int)$owner !== $cong){
                $pdo->rollBack();
                return ['success' => false,'errors' => ['Bank not found for this congregation']];
            }
            $reasons = $this->checkreferenced($id);
            if($reasons){
                $pdo->rollBack();
                return ['success' => false,'errors' => $reasons];
            }

            $this->db->query('UPDATE tblaccounttypes SET deleted=:del WHERE (ID=:id)');
            $this->db->bind(':del',1);
            $this->db->bind(':id',$id);
            $this->db->execute();

            // opening balance (if any) is voided, never hard-deleted
            Ledger::void($pdo,$cong,8,$id);
            Ledger::voidBankPostings($pdo,$cong,8,$id);

            //save logs
            $act = 'Deleted Bank '.$data['bankname'];
            saveLog($pdo,$act);

            //commit transaction
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => []];
        }
    }

    public function GetDistrictOrGroup($type)
    {
        $sql = '';
        if($type == 'group'){
            $sql = 'SELECT ID,groupName as ColumnName FROM tblgroups WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }else{
            $sql = 'SELECT ID,districtName as ColumnName FROM tbldistricts WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }

        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function CheckSubAccountExists($data)
    {
        $value = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblbanksubaccounts 
                                            WHERE (AccountName=?) AND (Deleted=0) AND (ID <> ?)',[$data['name'],$data['id']]);
        if($value > 0) return false;
        return true;
    }

    public function CreateUpdateSubAccount($data)
    {
        if(!$data['isedit']){
            $this->db->query('INSERT INTO tblbanksubaccounts(AccountName, BankId, AccountId, GroupDistrict, GroupId, DistrictId) 
                          VALUES(:aname,:bid,:aid,:groupdistrict,:gid,:did)');
        }else{
            $this->db->query('UPDATE tblbanksubaccounts SET AccountName=:aname,BankId=:bid,AccountId=:aid,GroupDistrict=:groupdistrict,
                                                            GroupId=:gid,DistrictId=:did WHERE (ID=:id)');
        }
        $this->db->bind(':aname',$data['name']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':aid',$data['account']);
        $this->db->bind(':groupdistrict',$data['districtgroup']);
        $this->db->bind(':gid',$data['districtgroup'] === 'group' ? $data['param'] : null);
        $this->db->bind(':did',$data['districtgroup'] === 'district' ? $data['param'] : null);
        if($data['isedit']){
            $this->db->bind(':id',$data['id']);
        }
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function GetSubAccount($id)
    {
        return loadsingleset($this->db->dbh,'SELECT * FROM tblbanksubaccounts WHERE (ID=?)',[$id]);
    }

    public function deletesubaccount($data)
    {
       
        try {
            //start transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblbanksubaccounts SET Deleted=:del WHERE (ID=:id)');
            $this->db->bind(':del',1);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            
            //save logs
            $act = 'Deleted sub account '.$data['subaccount'];
            saveLog($this->db->dbh,$act);

            //commit transaction
            if ($this->db->dbh->commit()) {
               return true;
            }
            else{
                return false;
            }
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            throw $e;
        }
    }

    public function SetOpeningBalance($data)
    {
        $this->db->query('INSERT INTO tblbanktransactions_subaccounts (TransactionDate,TransactionId,SubAccountId,
                                                                       Amount,ToAccountId,Narration,TransactionType,CongregationId)
                          VALUES(:tdate,:tid,:subaccount,:amount,:toaccount,:narr,:ttype,:congid)');
        $this->db->bind(':tdate',$data['asof']);
        $this->db->bind(':tid',0);   // set to the row's own ID below
        $this->db->bind(':subaccount',$data['subaccount']);
        $this->db->bind(':amount',$data['balance']);
        $this->db->bind(':toaccount',$data['subaccount']);
        $this->db->bind(':narr','Sub account opening balance.');
        $this->db->bind(':ttype',18);
        $this->db->bind(':congid',$_SESSION['congId']);
        if(!$this->db->execute()){
            return false;
        }
        $id = (int)$this->db->dbh->lastInsertId();
        $this->db->query('UPDATE tblbanktransactions_subaccounts SET TransactionId = :id WHERE ID = :id2');
        $this->db->bind(':id',$id);
        $this->db->bind(':id2',$id);
        return $this->db->execute();
    }

    public function GetSubAccountOpeningBalances()
    {
        $sql = 'SELECT 
                    t.ID,
                    t.TransactionDate,
                    ucase(s.AccountName) as AccountName,
                    `Amount`,
                    `Narration`    
                FROM `tblbanktransactions_subaccounts` t join tblbanksubaccounts s on t.SubAccountId = s.ID
                WHERE t.TransactionType = 18 AND (t.CongregationId = ?)
                ORDER BY t.TransactionDate DESC;';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetOpeningBalanceEntry($id)
    {
        $sql = 'SELECT 
                    t.ID,
                    t.TransactionDate,
                    t.SubAccountId,
                    t.Amount
                FROM `tblbanktransactions_subaccounts` t
                WHERE t.TransactionType = 18 AND t.ID = ? AND (t.CongregationId = ?)
                LIMIT 1;';
        return loadsingleset($this->db->dbh,$sql,[$id,$_SESSION['congId']]);
    }

    public function UpdateSubAccountOpeningBalance($data)
    {
        $this->db->query('UPDATE tblbanktransactions_subaccounts 
                               SET TransactionDate=:tdate,Amount=:amount 
                               WHERE (ID=:id) AND (TransactionType=18)');
        $this->db->bind(':tdate',$data['asof']);
        $this->db->bind(':amount',$data['balance']);
        $this->db->bind(':id',$data['id']);
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function DeleteSubAccountOpeningBalance($data)
    {
        $this->db->query('DELETE FROM tblbanktransactions_subaccounts WHERE (ID=:id) AND (TransactionType=18)');
        $this->db->bind(':id',$data['id']);
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }
}
<?php
/**
 * Group / district collections (transaction type 17) on the Ledger library (task 8.9).
 *
 * The form sends only the sub account (plus date, group / district, amount, narration). The accounts are read here from
 * tblbanksubaccounts, never from the client:
 *   Dr the sub account's bank (BankId, a PAY_BANK account)  /  Cr the sub account's AccountId, both for the amount,
 * plus one tblbankpostings row (method 'Deposit'). A missing or inactive account refuses the save.
 * Task 12: the collection has ONE reference (required, at most 25 characters = tblmmf.Reference): it is written to tblmmf.Reference, to both
 * ledger lines and to the bank row's reference (it was the narration before). A collection is one row per save.
 * tblmmf.Debit raises the group's / district's held balance. tblbanktransactions_subaccounts is NOT written (decision).
 *
 * Every action loads the record by ID + TransactionType 17 + session congregation (FOR UPDATE) and returns
 * ['success' => bool, 'errors' => string[]] (no errors on failure = unexpected error, logged).
 * Delete is a void (tblmmf.Deleted = 1, Ledger::void, Ledger::voidBankPostings).
 */
class Groupcollection
{
    const TYPE = 17;
    const BANK_METHOD = 'Deposit';
    const NARRATION_LEN = 150;      // tblmmf.Narration
    /**
     * Longest collection reference: tblmmf.Reference is VARCHAR(25), the smallest column it is written to as a single value
     * (also tblbankpostings.reference 50 and journal_lines.reference 50). The column is latin1: other characters are refused.
     */
    const REFERENCE_LEN = 25;

    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetTransactions()
    {
        $sql = "SELECT
	                m.ID,
                    m.TransactionDate,
                    m.Type,
                    IF(Type = 'district',d.districtName,g.groupName) As DistrictGroup,
                    m.Debit,
                    m.Narration AS Description,
                    -- the collection's own reference; an old collection (saved before task 12) shows its old bank reference (the narration)
                    COALESCE(NULLIF(TRIM(m.Reference),''),(SELECT NULLIF(TRIM(b.reference),'') FROM tblbankpostings b
                              WHERE b.transactionType = 17 AND b.transactionId = m.ID AND b.congregationId = m.CongregationId AND b.deleted = 0
                              ORDER BY b.ID LIMIT 1),'') AS Reference
                FROM tblmmf m left join tblgroups g on m.GroupId=g.ID left join tbldistricts d on m.DistrictId = d.ID
                WHERE (m.Deleted=0) AND (m.CongregationID=?) AND (m.TransactionType=17)
                ORDER BY TransactionDate DESC;";
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetGroups()
    {
        $this->db->query('SELECT ID,UCASE(groupName) as groupName
                          FROM tblgroups
                          WHERE (active = 1) AND (deleted = 0) AND (congregationId = :cid)
                          ORDER BY groupName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetDistrictOrGroup($type)
    {
        $sql = '';
        if($type == 'group'){
            $sql = 'SELECT ID,groupName as ColumnName FROM tblgroups WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }elseif($type == 'district'){
            $sql = 'SELECT ID,districtName as ColumnName FROM tbldistricts WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }else{
            $sql = 'SELECT ID,categoryName as ColumnName FROM tblchurchrequisitioncategories WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }

        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    /** Sub accounts of a group / district of this congregation. */
    public function GetSubAccounts($type,$groupid)
    {
       if($type == 'group'){
         return loadresultset($this->db->dbh,'SELECT s.ID,s.AccountName FROM tblbanksubaccounts s
                                              JOIN tblgroups g ON g.ID = s.GroupId
                                              WHERE (s.GroupId=?) AND (s.Deleted=0) AND (g.congregationId=?)',[$groupid,$_SESSION['congId']]);
       }else{
         return loadresultset($this->db->dbh,'SELECT s.ID,s.AccountName FROM tblbanksubaccounts s
                                              JOIN tbldistricts d ON d.ID = s.DistrictId
                                              WHERE (s.DistrictId=?) AND (s.Deleted=0) AND (d.congregationId=?)',[$groupid,$_SESSION['congId']]);
       }
    }

    /** [account ID, account name, bank ID] of a sub account of this congregation, for DISPLAY on the form only. */
    public function GetAccountDetails($subaccount)
    {
        $sub = $this->subAccount($this->db->dbh,(int)$subaccount,(int)$_SESSION['congId']);
        if(!$sub){
            return [null,null,null];
        }
        $accountname = getdbvalue($this->db->dbh,'SELECT accountType FROM tblaccounttypes WHERE (ID=?)',[$sub->AccountId]);
        return [$sub->AccountId,$accountname,$sub->BankId];
    }

    /** The sub account if it belongs (through its group or district) to the congregation and is not deleted. */
    private function subAccount($pdo,$id,$cong)
    {
        $st = $pdo->prepare('SELECT s.* FROM tblbanksubaccounts s
                             LEFT JOIN tblgroups g ON s.GroupDistrict = \'group\' AND g.ID = s.GroupId
                             LEFT JOIN tbldistricts d ON s.GroupDistrict = \'district\' AND d.ID = s.DistrictId
                             WHERE s.ID = ? AND s.Deleted = 0 AND COALESCE(g.congregationId,d.congregationId) = ?');
        $st->execute([$id,$cong]);
        return $st->fetch(PDO::FETCH_OBJ);
    }

    /**
     * The collection (this congregation, type 17, not deleted) or false. Reference = its own; a collection saved before task 12
     * has none and shows the reference its bank row carries (the old narration), so the edit form can be saved (decision 4).
     */
    public function GetCollection($id)
    {
        $row = loadsingleset($this->db->dbh,'SELECT * FROM tblmmf WHERE (ID=?) AND (TransactionType=17) AND (CongregationId=?) AND (Deleted=0)',
                             [(int)$id,$_SESSION['congId']]);
        if($row && trim((string)$row->Reference) === ''){
            $row->Reference = (string)getdbvalue($this->db->dbh,'SELECT COALESCE(NULLIF(TRIM(reference),\'\'),\'\') FROM tblbankpostings
                                 WHERE transactionType = 17 AND transactionId = ? AND congregationId = ? AND deleted = 0 ORDER BY ID LIMIT 1',
                                 [(int)$row->ID,(int)$_SESSION['congId']]);
        }
        return $row;
    }

    /**
     * $data: isedit, id, tdate (Y-m-d), type ('group'|'district'), groupid, amount, narration, subaccount, reference (required, as typed).
     * Anything else the client sent (account, accountid, bankid) is not read. Returns ['success','errors','id'].
     */
    public function CreateUpdate($data)
    {
        $pdo = $this->db->dbh;
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();

            $current = null;
            if($data['isedit']){
                $st = $pdo->prepare('SELECT * FROM tblmmf WHERE ID = ? AND TransactionType = 17 AND CongregationId = ? AND Deleted = 0 FOR UPDATE');
                $st->execute([(int)$data['id'],$cong]);
                $current = $st->fetch(PDO::FETCH_OBJ);
                if(!$current){
                    return $this->refuse($pdo,'Group collection not found');
                }
            }

            $errors = [];
            $date = DateTime::createFromFormat('!Y-m-d',(string)$data['tdate']);
            if(!$date || $date->format('Y-m-d') !== $data['tdate']){
                $errors[] = 'Select a valid date';
            }elseif($data['tdate'] > date('Y-m-d')){
                $errors[] = 'Invalid date selected (it cannot be in the future)';
            }
            if($m = Ledger::amountError($data['amount'])){
                $errors[] = $m;
            }
            $narration = trim((string)$data['narration']);
            if($narration === ''){
                $errors[] = 'Enter a description';
            }elseif(mb_strlen($narration) > self::NARRATION_LEN){
                $errors[] = 'Description cannot be longer than ' . self::NARRATION_LEN . ' characters';
            }

            $reference = trim((string)($data['reference'] ?? ''));
            if($reference === ''){
                $errors[] = 'Enter the reference';
            }elseif(mb_strlen($reference) > self::REFERENCE_LEN){
                $errors[] = 'Reference cannot be longer than ' . self::REFERENCE_LEN . ' characters';
            }elseif(mb_convert_encoding(mb_convert_encoding($reference,'ISO-8859-1','UTF-8'),'UTF-8','ISO-8859-1') !== $reference){
                $errors[] = 'Reference contains characters that cannot be stored; use letters, digits and common symbols';
            }

            $type = $data['type'];
            $owner = (int)$data['groupid'];
            $sub = false;
            if($type !== 'group' && $type !== 'district'){
                $errors[] = 'Select group or district';
            }else{
                $st = $pdo->prepare($type === 'group'
                    ? 'SELECT COUNT(*) FROM tblgroups WHERE ID = ? AND congregationId = ? AND deleted = 0'
                    : 'SELECT COUNT(*) FROM tbldistricts WHERE ID = ? AND congregationId = ? AND deleted = 0');
                $st->execute([$owner,$cong]);
                if((int)$st->fetchColumn() === 0){
                    $errors[] = 'Select a valid ' . $type;
                }else{
                    $sub = $this->subAccount($pdo,(int)$data['subaccount'],$cong);
                    if(!$sub || $sub->GroupDistrict !== $type || (int)($type === 'group' ? $sub->GroupId : $sub->DistrictId) !== $owner){
                        $errors[] = 'Select a valid sub account of this ' . $type;
                        $sub = false;
                    }
                }
            }

            $bank = 0;
            $income = 0;
            if($sub){
                $bank = (int)$sub->BankId;
                $income = (int)$sub->AccountId;
                try {
                    if($bank <= 0){
                        throw new LedgerException(['missing']);
                    }
                    Ledger::assertPaymentAccount($pdo,$cong,$bank,Ledger::PAY_BANK);
                } catch (LedgerException $e) {
                    $errors[] = 'The bank of sub account ' . strtoupper($sub->AccountName) . ' is missing, inactive or not a bank account';
                }
                if($income <= 0 || ($m = Ledger::postingProblem($pdo,$income,$cong)) !== null){
                    $errors[] = 'The account of sub account ' . strtoupper($sub->AccountName) . ' is missing or cannot be posted to'
                              . ($income > 0 ? ' (' . $m . ')' : '');
                }
            }
            if($current && !$errors && ($m = Ledger::clearedBankProblem($pdo,$cong,self::TYPE,(int)$current->ID,['bankId' => $bank,'amount' => $data['amount']]))){
                $errors[] = $m;
            }
            if($errors){
                return $this->refuse($pdo,$errors);
            }

            $amount = number_format((float)$data['amount'],2,'.','');
            $params = [$data['tdate'],$type,$type === 'district' ? $owner : null,$type === 'group' ? $owner : null,$amount,(int)$sub->ID,$narration,$reference];
            if($current){
                $pdo->prepare('UPDATE tblmmf SET TransactionDate = ?, `Type` = ?, DistrictId = ?, GroupId = ?, Debit = ?, SubAccountId = ?, Narration = ?, Reference = ?
                               WHERE ID = ? AND TransactionType = 17 AND CongregationId = ?')
                    ->execute(array_merge($params,[(int)$current->ID,$cong]));
                $id = (int)$current->ID;
            }else{
                $pdo->prepare('INSERT INTO tblmmf (TransactionDate,`Type`,DistrictId,GroupId,Debit,SubAccountId,Narration,Reference,TransactionType,CongregationId)
                               VALUES (?,?,?,?,?,?,?,?,17,?)')
                    ->execute(array_merge($params,[$cong]));
                $id = (int)$pdo->lastInsertId();
            }

            Ledger::post($pdo,[
                'congregationId' => $cong,'entryDate' => $data['tdate'],'transactionTypeId' => self::TYPE,'transactionId' => $id,
                'description' => $narration,
                'reference' => $reference,
            ],[
                Ledger::debit($bank,$amount,$narration,$reference),
                Ledger::credit($income,$amount,$narration,$reference),
            ]);
            // the bank row's reference is the collection's reference (it was the narration before task 12)
            Ledger::syncBankPostings($pdo,$cong,self::TYPE,$id,self::BANK_METHOD);

            saveLog($pdo,($current ? 'Updated' : 'Created') . ' group collection ' . $id . ' for ' . $data['tdate']);
            $pdo->commit();
            return ['success' => true,'errors' => [],'id' => $id];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }

    /** Void: tblmmf.Deleted = 1, the entry and its bank row are voided (a closed financial year refuses it). */
    public function Delete($id)
    {
        $pdo = $this->db->dbh;
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT * FROM tblmmf WHERE ID = ? AND TransactionType = 17 AND CongregationId = ? AND Deleted = 0 FOR UPDATE');
            $st->execute([(int)$id,$cong]);
            $row = $st->fetch(PDO::FETCH_OBJ);
            if(!$row){
                return $this->refuse($pdo,'Group collection not found');
            }

            if($m = Ledger::clearedBankProblem($pdo,$cong,self::TYPE,(int)$row->ID)){
                return $this->refuse($pdo,$m);
            }
            Ledger::void($pdo,$cong,self::TYPE,(int)$row->ID);
            Ledger::voidBankPostings($pdo,$cong,self::TYPE,(int)$row->ID);
            $pdo->prepare('UPDATE tblmmf SET Deleted = 1 WHERE ID = ? AND TransactionType = 17 AND CongregationId = ?')
                ->execute([(int)$row->ID,$cong]);

            saveLog($pdo,'Deleted group collection ' . (int)$row->ID . ' dated ' . $row->TransactionDate);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }

    private function refuse($pdo,$messages)
    {
        if($pdo->inTransaction()){
            $pdo->rollBack();
        }
        return ['success' => false,'errors' => (array)$messages];
    }

    private function fail($pdo,$e)
    {
        if($pdo->inTransaction()){
            $pdo->rollBack();
        }
        if($e instanceof LedgerException){
            return ['success' => false,'errors' => $e->getErrors()];
        }
        error_log($e->getMessage(),0);
        return ['success' => false,'errors' => []];
    }
}

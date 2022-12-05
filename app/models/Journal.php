<?php
class Journal {
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
    public function getAccounts()
    {
        $this->db->query('SELECT ID,UCASE(accountType) as accountType 
                          FROM tblaccounttypes 
                          WHERE (deleted=0) AND (parentId <> 0)
                                AND (congregationId=:cid OR congregationId=0)
                                AND (isSubCategory = 1)
                          ORDER BY accountType');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function journalNo()
    {
       $this->db->query('SELECT COUNT(ID) 
                         FROM tblledger 
                         WHERE (isJournal=1) AND (congregationId=:cid)');
       $this->db->bind(':cid',$_SESSION['congId']);
       $result = $this->db->getValue();
       if ($result == 0) {
           return 1;
       }
       else{
            $this->db->query('SELECT journalNo 
                              FROM tblledger 
                              WHERE (isJournal = 1) AND (congregationId=:cid)
                              ORDER BY journalNo DESC LIMIT 1');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->getValue() + 1;
       }
    }
    public function getAccountId($account)
    {
        $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        return $this->db->getValue();
    }
    public function getjournalno($type = 'current')
    {
        $sql = 'SELECT COUNT(*) FROM tblledger WHERE (isJournal = 1) AND (deleted = 0) AND (congregationId = ?)';
        $entriescount = getdbvalue($this->db->dbh,$sql,[(int)$_SESSION['congId']]); //get entries count
        if((int)$entriescount === 0) return 1; //if there are no entries
        //if there are entries
        $sorttype = $type === 'current' ? 'DESC' : 'ASC';
        $journalsql = 'SELECT journalNo FROM tblledger 
                       WHERE (isJournal = 1) AND (deleted = 0) AND (congregationId = ?)
                       ORDER BY journalNo '.$sorttype.' LIMIT 1';
        $journalno = getdbvalue($this->db->dbh,$journalsql,[(int)$_SESSION['congId']]);
        if($type === 'current') return (int)$journalno + 1;
        return (int)$journalno;
    }
    public function create($data)
    {
        try {
            $five = 5;
            $jno = $data['journal'];
            $isj = 1;
            $cong = $_SESSION['congId'];
            $this->db->dbh->beginTransaction();
            $sql = 'INSERT INTO tblledger (transactionDate,account,debit,credit,narration,accountId,
                transactionType,transactionId,isJournal,journalNo,congregationId) 
                VALUES(?,?,?,?,?,?,?,?,?,?,?)';
            // $this->db->query('INSERT INTO tblledger (transactionDate,account,debit,credit,narration,
            //                               accountId,transactionType,transactionId,isJournal,journalNo,
            //                               congregationId)
            //                   VALUES(:tdate,:acc,:debit,:credit,:narr,:aid,:ttype,:tid,:isj,:jno,:cid)');
            for ($i=0; $i < count($data['details']); $i++) { 
                $date =date($data['details'][$i]['date']);
                $account =trim(strtolower($data['details'][$i]['account']));
                $debit =!empty($data['details'][$i]['debit']) ? $data['details'][$i]['debit'] : 0;
                $credit =!empty($data['details'][$i]['credit']) ? $data['details'][$i]['credit'] : 0;
                $desc =trim(strtolower($data['details'][$i]['desc']));
                $aid =$this->getAccountId(trim($data['details'][$i]['aid']));
                
                $stmt = $this->db->dbh->prepare($sql);
                $stmt->execute([$date,$account,$debit,$credit,$desc,$aid,$five,$jno,$isj,$jno,$cong]);
                // $this->db->bind(':tdate',$date);
                // $this->db->bind(':acc',$account);
                // $this->db->bind(':debit',$debit);
                // $this->db->bind(':credit',$credit);
                // $this->db->bind(':narr',$desc);
                // $this->db->bind(':aid',$aid);
                // $this->db->bind(':ttype',$five);
                // $this->db->bind(':tid',$jno);
                // $this->db->bind(':isj',$isj);
                // $this->db->bind(':jno',$jno);
                // $this->db->bind(':cid',$cong);
                // $this->db->execute();
            }    
            
            $act = 'Created Journal Entry '.$data['journal'];
            saveLog($this->db->dbh,$act);
            if ($this->db->dbh->commit()) {
                return true;
            }
            else {
                return false;
            }
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            throw $e;
        }
    }

    public function createupdate($data)
    {
        try {
            $this->db->dbh->beginTransaction();

            for ($i=0; $i < count($data['entries']); $i++) { 
                $account = strtolower(trim($data['entries'][$i]->accountname));
                $accountid = (int)trim($data['entries'][$i]->accountid);
                $accounttypeid = getdbvalue($this->db->dbh,'SELECT accountTypeId FROM tblaccounttypes WHERE ID = ?',[$accountid]);
                $parentgl = getparentgl($this->db->dbh,$account);
                $debit = !empty($data['entries'][$i]->debit) ? floatval($data['entries'][$i]->debit) : 0;
                $credit = !empty($data['entries'][$i]->credit) ? floatval($data['entries'][$i]->credit) : 0;
                $narr = !empty($data['entries'][$i]->desc) ? strtolower(trim($data['entries'][$i]->desc)) : 'journal entries #' .$data['journalno'];

                $this->db->query('INSERT INTO tblledger (transactionDate,account,parentaccount,debit,credit,narration,accountId,
                                                         transactionType,transactionId,isJournal,journalNo,congregationId)
                                  VALUES(:tdate,:account,:parent,:debit,:credit,:narration,:aid,:ttype,:tid,:isjournal,:jno,:cid)');
                $this->db->bind(':tdate',$data['date']);
                $this->db->bind(':account',$account);
                $this->db->bind(':parent',$parentgl);
                $this->db->bind(':debit',$debit);
                $this->db->bind(':credit',$credit);
                $this->db->bind(':narration',$narr);
                $this->db->bind(':aid',$accounttypeid);
                $this->db->bind(':ttype',5);
                $this->db->bind(':tid',$data['journalno']);
                $this->db->bind(':isjournal',true);
                $this->db->bind(':jno',$data['journalno']);
                $this->db->bind(':cid',(int)$_SESSION['congId']);
                $this->db->execute();
            }

            saveLog($this->db->dbh,'Made entries for journal no '.$data['journalno']);

            if(!$this->db->dbh->commit()){
                return false;
            }else{
                return true;
            }
            
        } catch (PDOException $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollBack();
            }
            error_log($e->getMessage(),0);
            return false;
        }
    }
    public function checkexists($journalno)
    {
        $sql = 'SELECT COUNT(*) FROM tblledger 
                WHERE (isJournal = 1) AND (journalNo=?) AND (deleted = 0) AND (congregationId = ?)';
        $count = getdbvalue($this->db->dbh,$sql,[(int)$journalno,(int)$_SESSION['congId']]);
        if((int)$count === 0){
            return false;
        }
        return true;
    }
    public function getjournal($journalno)
    {
        $sql = 'SELECT 
                    a.ID,
                    l.transactionDate,
                    l.account,
                    l.debit,
                    l.credit,
                    l.narration
                FROM `tblledger` l join tblaccounttypes a on l.account = a.accountType 
                WHERE (isJournal = 1) AND (journalNo = ?) AND (l.deleted = 0) AND (l.congregationId=?)';
        return loadresultset($this->db->dbh,$sql,[(int)$journalno,(int)$_SESSION['congId']]);
    }
}
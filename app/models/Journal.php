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
                          WHERE (deleted=0) AND (parentId <> 0) AND (active = 1)
                                AND (congregationId=:cid OR congregationId=0)
                          ORDER BY accountType');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function journalNo()
    {
       $this->db->query('SELECT COUNT(ID) 
                         FROM tblledger 
                         WHERE (isJournal=1) AND (congregationId=:cid) AND (deleted = 0)');
       $this->db->bind(':cid',$_SESSION['congId']);
       $result = $this->db->getValue();
       if ($result == 0) {
           return 1;
       }
       else{
            $this->db->query('SELECT journalNo 
                              FROM tblledger 
                              WHERE (isJournal = 1) AND (congregationId=:cid) AND (deleted = 0)
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
   
    public function createupdate($data)
    {
        try {
            $this->db->dbh->beginTransaction();

            if($data['isedit']){
                $this->db->query('DELETE FROM tblledger 
                                  WHERE (isJournal = 1) AND (deleted = 0) AND (journalNo=:jno) AND (congregationId = :cid)');
                $this->db->bind(':jno',$data['journalno']);
                $this->db->bind(':cid',$_SESSION['congId']);
                $this->db->execute();
            }

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

            $narr = $data['isedit'] ? 'Made changes to journal no '.$data['journalno'] : 'Made entries for journal no '.$data['journalno'];
            saveLog($this->db->dbh,$narr);

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
    public function delete($id)
    {
        $this->db->query('UPDATE tblledger SET deleted = 1
                          WHERE (isJournal = 1) AND (deleted = 0) AND (journalNo=:jno) AND (congregationId = :cid)');
        $this->db->bind(':jno',$id);
        $this->db->bind(':cid',$_SESSION['congId']);
        if(!$this->db->execute()) return false;
        return true;
    }

    public function getaccount($account)
    {
        $count = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblaccounttypes WHERE LOWER(accountType) = ?',[$account]);
        if((int)$count === 0){
            return false;
        }
        return getdbvalue($this->db->dbh,'SELECT ID FROM tblaccounttypes WHERE LOWER(accountType) = ?',[$account]);
    }

    public function getJournalDetails($data)
    {
        $sql = 'SELECT 
                    l.ID,
                    l.transactionDate,
                    l.account,
                    l.debit,
                    l.credit,
                    l.narration
                FROM `tblledger` l 
                WHERE (transactionId = ?) AND (transactionType = ?) AND (l.deleted = 0) AND (l.congregationId=?)';
        return loadresultset($this->db->dbh,$sql,[(int)$data['id'],$data['type'],(int)$_SESSION['congId']]);
    }
}
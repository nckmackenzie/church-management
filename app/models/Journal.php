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
}
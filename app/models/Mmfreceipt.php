<?php
class Mmfreceipt
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

    public function GetMMFs()
    {
        $this->db->query("SELECT m.ID,
                            DATE_FORMAT(m.TransactionDate,'%d/%m/%y') As TransactionDate,
                            UCASE(g.groupName) AS GroupName,
                            FORMAT(m.Credit,2) As Debit,
                            UCASE(m.Reference) As Reference
                        FROM 
                            tblmmf m 
                                inner join tblgroups g 
                                    on m.GroupId = g.ID
                        WHERE 
                            m.CongregationId = :cid AND TransactionType = 11
                        ORDER BY m.TransactionDate DESC");
        $this->db->bind(':cid',intval($_SESSION['congId']));
        return $this->db->resultSet();
    }
    
    public function GetBalance($data)
    {
        $this->db->query('SELECT getmmfopeningbal(:gid,:sdate) AS Balance');
        $this->db->bind(':gid',(int)$data['groupid']);
        $this->db->bind(':sdate',$data['date']);
        return $this->db->getValue();
    }

    public function GetGroups()
    {
        $this->db->query("SELECT ID,UCASE(groupName) AS GroupName 
                          FROM tblgroups 
                          WHERE (active = 1) AND (Deleted = 0) AND (congregationId = :cid)");
        $this->db->bind(':cid',intval($_SESSION['congId']));
        return $this->db->resultSet();
    }
    
    public function GetBanks()
    {
        $this->db->query("SELECT   ID,
                                   CONCAT(UCASE(`accountType`),'-',IFNULL(`accountNo`,'')) As Bank
                          FROM     tblaccounttypes 
                          WHERE    (isBank=1) AND (Deleted=0) AND (congregationId=:cid)");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function CreateUpdate($data)
    {
        try {
            
            $this->db->dbh->beginTransaction();

            if(!$data['isedit']){
                $this->db->query('INSERT INTO tblmmf (TransactionDate,GroupId,Credit,BankId,Reference,TransactionType,
                                                      CongregationId) 
                                  VALUES(:tdate,:gid,:credit,:bid,:reference,:ttype,:cid)');
            }else{
                $this->db->query('UPDATE tblmmf SET TransactionDate=:tdate,GroupId=:gid,Credit=:credit,BankId=:bid,Reference=:reference
                                  WHERE  (ID = :id)');
            }
            $this->db->bind(':tdate',$data['tdate']);
            $this->db->bind(':gid',$data['groupid']);
            $this->db->bind(':credit',$data['amount']);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':reference',strtolower($data['reference']));
            if(!$data['isedit']){
                $this->db->bind(':ttype',11);
                $this->db->bind(':cid',intval($_SESSION['congId']));
            }else{
                $this->db->bind(':id',intval($data['id']));
            }
            $this->db->execute();

            $tid = !$data['isedit'] ? $this->db->dbh->lastInsertId() : $data['id'];

            if($data['isedit']){
                deleteLedgerBanking($this->db->dbh,11,$tid);
            }

            saveToLedger($this->db->dbh,$data['tdate'],'groups balances held',$data['amount'],0,
                         $data['reference'],4,11,$tid,$_SESSION['congId']);
            
            saveToLedger($this->db->dbh,$data['tdate'],'cash at bank',0,$data['amount'],
                         $data['reference'],3,11,$tid,$_SESSION['congId']);

            saveToBanking($this->db->dbh,$data['bank'],$data['tdate'],0,$data['amount'],2,
                          $data['reference'],11,$tid,$_SESSION['congId']);

            if(!$this->db->dbh->commit()){
                return false;
            }else{
                return true;
            }

        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            throw $e;
            return false;
        }
    }

    public function CheckRefDuplication($ref,$id)
    {
        $this->db->query('SELECT COUNT(*) FROM tblmmf WHERE (Reference=:ref) AND (ID <> :id) AND (CongregationId = :cid)');
        $this->db->bind(':ref',strtolower(trim($ref)));
        $this->db->bind(':id',intval(trim($id)));
        $this->db->bind(':cid', intval($_SESSION['congId']));
        if(intval($this->db->getValue()) > 0){
            return false;
        }else{
            return true;
        }
    }

    public function GetMmf($id)
    {
        $this->db->query("SELECT * FROM tblmmf WHERE (ID = :id)");
        $this->db->bind(':id',intval($id));
        return $this->db->single();
    }

    public function Delete($id)
    {
        $this->db->query('DELETE FROM tblmmf WHERE (ID = :id)');
        $this->db->bind(':id',intval($id));
        if(!$this->db->execute()){
            return false;
        }else{
            return true;
        }
    }
}
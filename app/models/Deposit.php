<?php
class Deposit
{
    private $db;
    public function __construct()
    {
        $this->db =  new Database;
    }

    public function GetDeposits()
    {
        $sql = 'SELECT * FROM vw_deposits WHERE CongregationId = ?';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }
    
    public function GetBanks()
    {
        $sql = "SELECT ID,UCASE(CONCAT(accountType,'-',IFNULL(accountNo,''))) AS Bank FROM tblaccounttypes WHERE isBank = 1 AND CongregationId = ?";
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function CreateUpdate($data)
    {
        $reference = !empty($data['reference']) ? strtolower($data['reference']) : null;
        try
        {
            $this->db->dbh->beginTransaction();

            if($data['isedit']){
                $this->db->query('UPDATE tbldesposits SET DepositDate=:ddate,BankId=:bid,Amount=:amount,Reference=:reference,`Description`=:narr 
                                  WHERE (ID=:id)');
            }else{
                $this->db->query('INSERT INTO tbldesposits (DepositDate,BankId,Amount,Reference,`Description`,CongregationId) 
                                  VALUES(:ddate,:bid,:amount,:reference,:narr,:cid)');
            }
            $this->db->bind(':ddate',$data['date']);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':amount',$data['amount']);
            $this->db->bind(':reference',$reference);
            $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : null);
            if($data['isedit']){
                $this->db->bind(':id',$data['id']);
            }else{
                $this->db->bind(':cid',$_SESSION['congId']);
            }
            $this->db->execute();
            $tid = $data['isedit'] ? $data['id'] : $this->db->dbh->lastInsertId();

            if($data['isedit']){
                deleteLedgerBanking($this->db->dbh,13,$tid);
            }

            $narr = !empty($data['description']) ? strtolower($data['description']) : 'cash desposit for ' .$data['date'];
            $cabparent = getparentgl($this->db->dbh,'cash at bank');

            saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,$data['amount'],0,$narr,
                         3,13,$tid,$_SESSION['congId'],$reference);
            saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cabparent,0,$data['amount'],$narr,
                         3,13,$tid,$_SESSION['congId'],$reference);

            saveToBanking($this->db->dbh,$data['bank'],$data['date'],$data['amount'],0,1,
                          !empty($data['reference']) ? $data['reference'] : NULL,13,$tid,$_SESSION['congId']);
            
            if(!$this->db->dbh->commit()){
                return false;
            }
             
            return true;

        }catch(Exception $e)
        {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            throw $e;
        }
    }

    public function GetDeposit($id)
    {
        $this->db->query('SELECT * FROM tbldesposits WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }

    public function Delete($id)
    {
        try
        {
            $this->db->dbh->beginTransaction();

            $this->db->query('UPDATE tbldesposits SET Deleted = 1 
                              WHERE (ID=:id)');
            $this->db->bind(':id',$id);
            $this->db->execute();

            softdeleteLedgerBanking($this->db->dbh,13,$id);
                     
            if(!$this->db->dbh->commit()){
                return false;
            }
             
            return true;

        }catch(Exception $e)
        {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            throw $e;
        }
    }
}
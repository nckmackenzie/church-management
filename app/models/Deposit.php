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
        $sql = "SELECT ID,UCASE(CONCAT(accountType,'-',accountNo)) AS Bank FROM tblaccounttypes WHERE isBank = 1 AND CongregationId = ?";
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function CreateUpdate($data)
    {
        try
        {
            $this->db->dbh->beginTransaction();

            $this->db->query('INSERT INTO tbldesposits (DepositDate,BankId,Amount,Reference,`Description`,CongregationId) 
                              VALUES(:ddate,:bid,:amount,:reference,:narr,:cid)');
            $this->db->bind(':ddate',$data['date']);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':amount',$data['amount']);
            $this->db->bind(':reference',!empty($data['reference']) ? strtolower($data['reference']) : null);
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

            saveToLedger($this->db->dbh,$data['date'],'cash at bank',$data['amount'],0,$narr,
                         3,13,$tid,$_SESSION['congId']);
            saveToLedger($this->db->dbh,$data['date'],'cash at hand',0,$data['amount'],$narr,
                         3,13,$tid,$_SESSION['congId']);
            
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
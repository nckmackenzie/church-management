<?php

class Clearbanking
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
    public function getBanks()
    {
        $this->db->query("SELECT   ID,
                                   CONCAT(UCASE(accountType),'-',accountNo )As Bank
                          FROM     tblaccounttypes 
                          WHERE    (isBank=1) AND (Deleted=0) AND (congregationId=:cid)");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getBankings($data)
    {
        $this->db->query('SELECT b.ID,
                                 b.transactionDate,
                                 IF(b.debit > 0,b.debit,(b.credit * -1)) As Amount,
                                 ucase(b.reference) As Reference
                          FROM   tblbankpostings b
                          WHERE  (b.deleted=0) AND (b.cleared = 0) AND (b.bankId =:bid) 
                                 AND (b.transactionDate BETWEEN :st AND :en)
                          ORDER BY b.transactionDate');
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':st',$data['from']);
        $this->db->bind(':en',$data['to']);
        return $this->db->resultSet();
    }
    public function clear($data)
    {
        $today = date('Y-m-d');
        for ($i=0; $i < count($data['details']); $i++) {
            $id = $data['details'][$i];
            $this->db->query('UPDATE tblbankpostings SET cleared=1,clearedDare=:tdate WHERE ID=:id');
            $this->db->bind(':id',$id);
            $this->db->bind(':tdate',$today);
            $this->db->execute();
        }    
    }
    public function delete($id)
    {
        $this->db->query('UPDATE tblbankpostings SET deleted=1 WHERE ID=:id');
        $this->db->bind(':id',$id);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
    public function getAmounts($data)
    {
        $amounts = [];
        $deposits = 0;
        $withdrawals = 0;
        $balance = 0;

        $this->db->query('SELECT IFNULL(SUM(debit),0) As SumOfDebits
                          FROM   tblbankpostings
                          WHERE  (transactionDate BETWEEN :tfrom AND :tto) AND (cleared=1) 
                                 AND (deleted=0) AND (bankId=:bid)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $deposits = $this->db->getValue();
        array_push($amounts,$deposits);

        $this->db->query('SELECT IFNULL(SUM(credit),0) As SumOfCredits
                          FROM   tblbankpostings
                          WHERE  (transactionDate BETWEEN :tfrom AND :tto) AND (cleared=1) 
                                 AND (deleted=0) AND (bankId=:bid)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $withdrawals = $this->db->getValue();
        array_push($amounts,$withdrawals);

        $this->db->query('SELECT IFNULL(Amount,0) As Balance
                          FROM   tblbankbalances
                          WHERE  (TransactionDate=:tdate) AND (bankId=:bid)');
        $this->db->bind(':tdate',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $balance = $this->db->getValue();
        array_push($amounts,$balance);

        return $amounts;
    }
}
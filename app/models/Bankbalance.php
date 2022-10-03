<?php

class Bankbalance
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
    public function index()
    {
        $this->db->query("SELECT  b.ID,
                                  DATE_FORMAT(TransactionDate,'%d/%m/%Y') AS TransactionDate,
                                  ucase(a.accountType) As Bank,
                                  Amount
                          FROM    tblbankbalances b inner join tblaccounttypes a on b.bankId = a.ID
                          WHERE   (b.Deleted=0) AND (b.congregationId=:cid)");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getBanks()
    {
        $this->db->query('SELECT   ID,
                                   UCASE(accountType) As Bank
                          FROM     tblaccounttypes 
                          WHERE    (isBank=1) AND (Deleted=0) AND (congregationId=:cid)');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function checkExists($date,$id,$bank)
    {
       $this->db->query('SELECT COUNT(ID) FROM tblbankbalances WHERE (TransactionDate=:tdate) AND (BankId=:bank) AND (ID <> :id)');
       $this->db->bind(':tdate',$date);
       $this->db->bind(':bank',$bank);
       $this->db->bind(':id',$id);
       if ($this->db->getValue() > 0) {
           return true;
       }else{
           return false;
       }
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblbankbalances (TransactionDate,BankId,Amount,CongregationId)
                          VALUES (:tdate,:bid,:amount,:cid)');
        $this->db->bind(':tdate',$data['date']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':amount',$data['amount']);
        $this->db->bind(':cid',$_SESSION['congId']);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
    public function edit($id)
    {
        $this->db->query('SELECT * FROM tblbankbalances WHERE (ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblbankbalances SET TransactionDate=:tdate,BankId=:bid,Amount=:amount WHERE (ID=:id)');
        $this->db->bind(':tdate',$data['date']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':amount',$data['amount']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
    public function delete($id)
    {
        $this->db->query('UPDATE tblbankbalances SET Deleted=1 WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
}
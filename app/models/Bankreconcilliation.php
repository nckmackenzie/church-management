<?php

class Bankreconcilliation
{
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
    public function getBanks()
    {
        $this->db->query("SELECT   ID,
                                   CONCAT(UCASE(accountType),'-',accountNo) As Bank
                          FROM     tblaccounttypes 
                          WHERE    (isBank=1) AND (Deleted=0) AND (congregationId=:cid)");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getAmounts($data)
    {
        $amounts = [];
        $deposits = 0;
        $withdrawals = 0;
        $unclearedDeposits = 0;
        $unclearedWithdrawals = 0;

        $this->db->query('SELECT IFNULL(SUM(debit),0) As SumOfDebits
                          FROM   tblbankpostings
                          WHERE  (transactionDate BETWEEN :tfrom AND :tto) AND (cleared=1) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $deposits = $this->db->getValue();
        array_push($amounts,$deposits);

        $this->db->query('SELECT IFNULL(SUM(credit),0) As SumOfCredits
                          FROM   tblbankpostings
                          WHERE  (transactionDate BETWEEN :tfrom AND :tto) AND (cleared=1) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $withdrawals = $this->db->getValue();
        array_push($amounts,$withdrawals);

        $this->db->query('SELECT IFNULL(SUM(debit),0) As SumOfDebits
                          FROM   tblbankpostings
                          WHERE  (transactionDate BETWEEN :tfrom AND :tto) AND (cleared=0) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $unclearedDeposits = $this->db->getValue();
        array_push($amounts,$unclearedDeposits);

        $this->db->query('SELECT IFNULL(SUM(credit),0) As SumOfCredits
                          FROM   tblbankpostings
                          WHERE  (transactionDate BETWEEN :tfrom AND :tto) AND (cleared=0) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $unclearedWithdrawals = $this->db->getValue();
        array_push($amounts,$unclearedWithdrawals);

        return $amounts;
    }

    public function UnclearedReport($data)
    {
        if($data['type'] === 'withdraw'){
            $this->db->query('SELECT transactionDate,credit As amount,ucase(reference) as reference
                              FROM tblbankpostings
                              WHERE (transactionDate BETWEEN :sdate AND :edate) 
                                    AND (bankId = :bid) AND (cleared = 0) AND (credit > 0) AND (deleted = 0) AND (congregationId = :cid)
                              ORDER BY transactionDate');
        }elseif($data['type' === 'deposits']){
            $this->db->query('SELECT transactionDate,debit As amount,ucase(reference) as reference
                              FROM tblbankpostings
                              WHERE (transactionDate BETWEEN :sdate AND :edate) 
                                    AND (bankId = :bid) AND (cleared = 0) AND (debit > 0) AND (deleted = 0) AND (congregationId = :cid)
                              ORDER BY transactionDate');
        }
        $this->db->bind(':sdate',$data['sdate']);
        $this->db->bind(':edate',$data['edate']);
        $this->db->bind(':bid',$data['bankid']);
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
}
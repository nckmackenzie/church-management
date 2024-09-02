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
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid) AND (clearedDare <= :tdate)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['to']);
        $deposits = $this->db->getValue();
        array_push($amounts,$deposits);

        $this->db->query('SELECT IFNULL(SUM(credit),0) As SumOfCredits
                          FROM   tblbankpostings
                          WHERE  (transactionDate BETWEEN :tfrom AND :tto) AND (cleared=1) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid) AND (clearedDare <= :tdate)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['to']);
        $withdrawals = $this->db->getValue();
        array_push($amounts,$withdrawals);

        $this->db->query('SELECT IFNULL(SUM(debit),0) As SumOfDebits
                          FROM   tblbankpostings
                          WHERE  ((transactionDate BETWEEN :tfrom AND :tto) AND (cleared=0) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid)) 
                                 OR (cleared=1 AND clearedDare > :tdate AND (transactionDate BETWEEN :tfrom AND :tto) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid))');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['to']);
        $unclearedDeposits = $this->db->getValue();
        array_push($amounts,$unclearedDeposits);

        $this->db->query('SELECT IFNULL(SUM(credit),0) As SumOfCredits
                          FROM   tblbankpostings
                          WHERE  ((transactionDate BETWEEN :tfrom AND :tto) AND (cleared=0) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid)) OR 
                                 (cleared=1 AND clearedDare > :tdate AND (transactionDate BETWEEN :tfrom AND :tto)
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid))');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['to']);
        $unclearedWithdrawals = $this->db->getValue();
        array_push($amounts,$unclearedWithdrawals);

        $asofdate = subtractDay($data['from']);

        $this->db->query('CALL sp_balancesheet_assets(:startd,:cong)');
        // $this->db->bind(':startd',$data['from']);
        $this->db->bind(':startd',$asofdate);
        $this->db->bind(':cong',$_SESSION['congId']);
        $results = $this->db->resultSet();
        foreach($results as $result){
            if(strtolower($result->account) === 'cash at bank'){
                array_push($amounts,$result->bal);
            }
        }
        // $this->db->query($sql);
        // $this->db->bind(':account','cash at bank');
        // $this->db->bind(':asatdate',$data['from']);
        // $this->db->bind(':cid',$_SESSION['congId']);
        // $result = $this->db->single();
        // array_push($amounts,$result->bal);
        return $amounts;
    }

    public function getAmountsrecon($data)
    {
        $amounts = [];
        $deposits = 0;
        $withdrawals = 0;
        $unclearedDeposits = 0;
        $unclearedWithdrawals = 0;

        $this->db->query('SELECT IFNULL(SUM(debit),0) As SumOfDebits
                          FROM   tblbankpostings
                          WHERE  (transactionDate BETWEEN :tfrom AND :tto) AND (cleared=1) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid) AND (clearedDare <= :tdate)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['to']);
        $deposits = $this->db->getValue();
        array_push($amounts,$deposits);

        $this->db->query('SELECT IFNULL(SUM(credit),0) As SumOfCredits
                          FROM   tblbankpostings
                          WHERE  (transactionDate BETWEEN :tfrom AND :tto) AND (cleared=1) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid) AND (clearedDare <= :tdate)');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['to']);
        $withdrawals = $this->db->getValue();
        array_push($amounts,$withdrawals);

        $this->db->query('SELECT IFNULL(SUM(debit),0) As SumOfDebits
                          FROM   tblbankpostings
                          WHERE  ((transactionDate BETWEEN :tfrom AND :tto) AND (cleared=0) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid)) 
                                 OR (cleared=1 AND clearedDare > :tdate AND (transactionDate BETWEEN :tfrom AND :tto) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid))');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['to']);
        $unclearedDeposits = $this->db->getValue();
        array_push($amounts,$unclearedDeposits);

        $this->db->query('SELECT IFNULL(SUM(credit),0) As SumOfCredits
                          FROM   tblbankpostings
                          WHERE  ((transactionDate BETWEEN :tfrom AND :tto) AND (cleared=0) 
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid)) OR 
                                 (cleared=1 AND clearedDare > :tdate AND (transactionDate BETWEEN :tfrom AND :tto)
                                 AND (deleted=0) AND (bankId=:bid) AND (congregationId=:cid))');
        $this->db->bind(':tfrom',$data['from']);
        $this->db->bind(':tto',$data['to']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['to']);
        $unclearedWithdrawals = $this->db->getValue();
        array_push($amounts,$unclearedWithdrawals);

        $asofdate = $data['to'];

        $this->db->query('CALL sp_balancesheet_assets(:startd,:cong)');
        // $this->db->bind(':startd',$data['from']);
        $this->db->bind(':startd',$asofdate);
        $this->db->bind(':cong',$_SESSION['congId']);
        $results = $this->db->resultSet();
        foreach($results as $result){
            if(strtolower($result->account) === 'cash at bank'){
                array_push($amounts,$result->bal);
            }
        }
        // $this->db->query($sql);
        // $this->db->bind(':account','cash at bank');
        // $this->db->bind(':asatdate',$data['from']);
        // $this->db->bind(':cid',$_SESSION['congId']);
        // $result = $this->db->single();
        // array_push($amounts,$result->bal);
        return $amounts;
    }

    public function UnclearedReport($data)
    {
        if($data['type'] === 'withdraw'){
            $this->db->query('SELECT transactionDate,credit As amount,ucase(reference) as reference
                              FROM tblbankpostings
                              WHERE ((transactionDate BETWEEN :sdate AND :edate) 
                                    AND (bankId = :bid) AND (cleared = 0) AND (credit > 0)
                                    AND (deleted = 0) AND (congregationId = :cid)) OR 
                                    (cleared = 1 AND clearedDare > :tdate AND (transactionDate BETWEEN :sdate AND :edate)
                                    AND (bankId = :bid) AND (credit > 0) AND (deleted = 0) AND (congregationId = :cid))
                              ORDER BY transactionDate');
        }elseif($data['type'] === 'deposit'){
            $this->db->query('SELECT transactionDate,debit As amount,ucase(reference) as reference
                              FROM tblbankpostings
                              WHERE ((transactionDate BETWEEN :sdate AND :edate) 
                                    AND (bankId = :bid) AND (cleared = 0) AND (debit > 0) AND
                                    (deleted = 0) AND (congregationId = :cid)) OR (cleared = 1 AND clearedDare > :tdate AND  
                                    (transactionDate BETWEEN :sdate AND :edate) AND (bankId = :bid) AND  
                                    (deleted = 0) AND (congregationId = :cid))
                              ORDER BY transactionDate');
        }
        $this->db->bind(':sdate',$data['sdate']);
        $this->db->bind(':edate',$data['edate']);
        $this->db->bind(':bid',$data['bankid']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['edate']);
        return $this->db->resultSet();
    }

    public function ClearedReport($data)
    {
        if($data['type'] === 'withdraw'){
            $this->db->query('SELECT transactionDate,credit As amount,ucase(reference) as reference
                              FROM tblbankpostings
                              WHERE (transactionDate BETWEEN :sdate AND :edate) 
                                    AND (bankId = :bid) AND (cleared = 1) AND (credit > 0) AND (deleted = 0) 
                                    AND (congregationId = :cid) AND (clearedDare <= :tdate)
                              ORDER BY transactionDate');
        }elseif($data['type'] === 'deposit'){
            $this->db->query('SELECT transactionDate,debit As amount,ucase(reference) as reference
                              FROM tblbankpostings
                              WHERE (transactionDate BETWEEN :sdate AND :edate) 
                                    AND (bankId = :bid) AND (cleared = 1) AND (debit > 0) 
                                    AND (deleted = 0) AND (congregationId = :cid) AND (clearedDare <= :tdate)
                              ORDER BY transactionDate');
        }
        $this->db->bind(':sdate',$data['sdate']);
        $this->db->bind(':edate',$data['edate']);
        $this->db->bind(':bid',$data['bankid']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':tdate',$data['edate']);
        return $this->db->resultSet();
    }
}

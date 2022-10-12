<?php

class Clearbanking
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function getBankings($data)
    {
        $sql = 'SELECT b.ID,
                       b.transactionDate,
                       IF(b.debit > 0,b.debit,(b.credit * -1)) As Amount,
                       ucase(b.reference) As Reference
                FROM   tblbankpostings b
                WHERE  (b.deleted=0) AND (b.cleared = 0) AND (b.bankId = ?) 
                        AND (b.transactionDate BETWEEN ? AND ?)
                ORDER BY b.transactionDate';
        return loadresultset($this->db->dbh,$sql,[$data['bank'],$data['from'],$data['to']]);
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

        $depositssql ='SELECT IFNULL(SUM(debit),0) As SumOfDebits
                       FROM   tblbankpostings
                       WHERE  (transactionDate BETWEEN ? AND ?) AND (cleared=1) 
                              AND (deleted=0) AND (bankId= ?)';
        $deposits = floatval(getdbvalue($this->db->dbh,$depositssql,[$data['from'],$data['to'],$data['bank']]));
        array_push($amounts,$deposits);

        $withdrawalsql ='SELECT IFNULL(SUM(credit),0) As SumOfCredits
                         FROM   tblbankpostings
                         WHERE  (transactionDate BETWEEN ? AND ?) AND (cleared=1) 
                                AND (deleted=0) AND (bankId= ?)';
        $withdrawals = floatval(getdbvalue($this->db->dbh,$withdrawalsql,[$data['from'],$data['to'],$data['bank']]));
        array_push($amounts,$withdrawals);

        return $amounts;
    }
}
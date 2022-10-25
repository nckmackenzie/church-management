<?php
class Tb
{
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetReport($data)
    {
        if($data['type'] === 'detailed'){
            return loadresultset($this->db->dbh,'CALL sp_trialbalance(?,?,?)',[$data['sdate'],$data['edate'],$_SESSION['congId']]);
        }elseif($data['type'] === 'summary'){
            return loadresultset($this->db->dbh,'CALL sp_trialbalance_summary(?,?,?)',[$data['sdate'],$data['edate'],$_SESSION['congId']]);
        }
    }

    public function GetDetailedTbReport($data)
    {
        if($data['type'] !== 'summary' && $data['type'] !== 'detailed'){
            return false;
        }
        $sql = '';
        if($data['type'] === 'summary'){
            $sql = 'SELECT transactionDate,account,debit,credit,narration,t.TransactionType 
                    FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID 
                    WHERE (parentaccount = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0)
                    ORDER BY transactionDate';
        }elseif($data['type'] === 'detailed'){
            $sql = 'SELECT transactionDate,account,debit,credit,narration,t.TransactionType 
                    FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID 
                    WHERE (account = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0)
                    ORDER BY transactionDate';
        }
        return loadresultset($this->db->dbh,$sql,[$data['account'],$data['sdate'],$data['edate']]);
    }
}
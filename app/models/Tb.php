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
        // if($data['type'] === 'detailed'){
        //     return loadresultset($this->db->dbh,'CALL sp_trialbalance(?,?,?)',[$data['sdate'],$data['edate'],$_SESSION['congId']]);
        // }elseif($data['type'] === 'summary'){
        //     return loadresultset($this->db->dbh,'CALL sp_trialbalance_summary(?,?,?)',[$data['sdate'],$data['edate'],$_SESSION['congId']]);
        // }

        
        // $sql = 'CALL sp_get_trial_balance_updated(?,?,?)';
        // return loadresultset($this->db->dbh,$sql,[$data['asofdate'],$data['type'],$_SESSION['congId']]);

        //changes as of Nov 28, to display for current financial year
        $yearStartDate = loadsingleset($this->db->dbh,'SELECT startDate FROM tblfiscalyears WHERE ? BETWEEN startDate AND endDate', [date('Y-m-d',strtotime($data['asofdate']))]);
        if($data['type'] === 'summary'){
            $sql = 'CALL sp_trialbalance_summary_v2(?,?,?)';
        }else{
            $sql = 'CALL sp_trialbalance_detailed_v2(?,?,?)';
        }
        return loadresultset($this->db->dbh,$sql,[$yearStartDate->startDate,$data['asofdate'],$_SESSION['congId']]);
    }

    public function GetDetailedTbReport($data)
    {
        if($data['type'] !== 'summary' && $data['type'] !== 'detailed'){
            return false;
        }
        $yearStartDate = loadsingleset($this->db->dbh,'SELECT startDate FROM tblfiscalyears WHERE ? BETWEEN startDate AND endDate', [date('Y-m-d',strtotime($data['asofdate']))]);
        $sql = '';
        // if($data['type'] === 'summary'){
        //     $sql = 'SELECT transactionDate,account,debit,credit,narration,t.TransactionType 
        //             FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID 
        //             WHERE (parentaccount = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0)
        //             ORDER BY transactionDate';
        // }elseif($data['type'] === 'detailed'){
        //     $sql = 'SELECT transactionDate,account,debit,credit,narration,t.TransactionType 
        //             FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID 
        //             WHERE (account = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0)
        //             ORDER BY transactionDate';
        // }
        // return loadresultset($this->db->dbh,$sql,[$data['account'],$data['sdate'],$data['edate']]);
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
        return loadresultset($this->db->dbh,$sql,[$data['account'],$yearStartDate->startDate,$data['asofdate']]);
    }
}
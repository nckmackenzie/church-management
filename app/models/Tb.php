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
        }
    }
}
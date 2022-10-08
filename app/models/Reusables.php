<?php
class Reusables
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetParentGL($childgl)
    {
        $sql = 'SELECT parentId FROM tblaccounttypes WHERE (accountType = ?)';
        $parentid = getdbvalue($this->db->dbh,$sql,[$childgl]);
        return trim(getdbvalue($this->db->dbh,'SELECT accountType FROM tblaccounttypes WHERE (ID = ?)',[$parentid]));
    }
}
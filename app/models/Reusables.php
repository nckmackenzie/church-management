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

    public function GetAccounts($id)
    {
        $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                          WHERE (accountTypeId = :id) AND (deleted=0) AND (isBank = 0) AND (parentId <> 0) AND (isSubCategory = 1) ORDER BY accountType');
        $this->db->bind(':id',$id);
        return $this->db->resultSet();                  
    }

    public function PaymentMethods()
    {
       return paymentMethods($this->db->dbh);
    }

    public function GetBanks()
    {
        $sql = "SELECT ID,UCASE(CONCAT(accountType,'-',IFNULL(accountNo,''))) AS Bank FROM tblaccounttypes WHERE isBank = 1 AND CongregationId = ?";
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function CheckYearClosed($yearid)
    {
        return yearprotection($this->db->dbh,$yearid);
    }

    public function GetCongregationDetails()
    {
        $this->db->query("SELECT * FROM tblcongregation WHERE ID = :id");
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->single();
    }
}
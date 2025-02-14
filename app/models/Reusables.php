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
                          WHERE (accountTypeId = :id) AND (deleted=0) AND (isBank = 0) AND (parentId <> 0) 
                          AND (active = 1) AND (congregationId = 0 OR congregationId = :cong) ORDER BY accountType');
        $this->db->bind(':id',$id);
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();                  
    }

    public function GetChildAccounts($id)
    {
        $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                               WHERE (accountTypeId = :id) AND (deleted=0) AND (isBank = 0) AND (parentId <> 0) 
                                AND (active = 1) AND (isSubCategory=1) AND (congregationId = 0 OR congregationId = :cong) ORDER BY accountType');
        $this->db->bind(':id',$id);
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();                  
    }

    public function GetAccountsAll()
    {
        $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                          WHERE (deleted=0) AND (isBank = 0) AND (parentId <> 0) 
                          AND (active = 1) AND (congregationId = 0 OR congregationId = :cong) ORDER BY accountType');
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();                  
    }

    public function GetAccountsAllSubcategory()
    {
        $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                               WHERE (deleted=0) AND (isBank = 0) AND (parentId <> 0) 
                               AND (active = 1) AND (isSubCategory=1) AND (congregationId = 0 OR congregationId = :cong) ORDER BY accountType');
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();                  
    }

    public function PaymentMethods()
    {
       return paymentMethods($this->db->dbh);
    }

    public function GetBanks()
    {
        $sql = "SELECT ID,UCASE(CONCAT(accountType,'-',IFNULL(accountNo,''))) AS Bank FROM tblaccounttypes WHERE (isBank = 1) AND (deleted=0) AND (active = 1) AND (CongregationId = ?)";
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

    public function GetCongregations()
    {
        $sql = 'SELECT ID,CongregationName FROM tblcongregation WHERE (deleted = 0) AND (ID <> ?) ORDER BY CongregationName';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetBank($id)
    {
        $sql = "SELECT UCASE(CONCAT(accountType,'-',IFNULL(accountNo,''))) AS Bank FROM tblaccounttypes WHERE isBank = 1 AND active = 1 AND ID = ?";
        return getdbvalue($this->db->dbh,$sql,[(int)$id]);
    }

    public function GetFiscalYear($date)
    {
        $sql = 'SELECT ID FROM tblfiscalyears WHERE ? BETWEEN startDate AND endDate AND deleted=0';
        return getdbvalue($this->db->dbh,$sql,[$date]);
    }

    public function GetYearName($id)
    {
        $sql = 'SELECT yearName FROM tblfiscalyears WHERE ID=?';
        return getdbvalue($this->db->dbh,$sql,[$id]);
    }

    public function CheckPrefixable($yearId)
    {
        return converttobool(getdbvalue($this->db->dbh,'SELECT prefixReferences from tblfiscalyears WHERE ID=?',[$yearId]));
    }
}
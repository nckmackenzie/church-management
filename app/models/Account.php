<?php
class Account {
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
        $this->db->query('SELECT   t.ID,UCASE(t.accountType) as accountType,a.accountType as atype
                                   ,brand_level(t.ID) AS levels,t.isEditable
                          FROM     tblaccounttypes t inner join tblaccounttypes as a on t.accountTypeId=a.ID
                          WHERE    (t.isBank=0) AND (t.deleted=0) AND (brand_level(t.ID) = 2)
                          ORDER BY t.accountTypeId ASC,brand_level(t.ID) ASC ');
        return $this->db->resultSet();
    }
    public function getAccountTypes()
    {
        $this->db->query('SELECT ID,UCASE(accountType) as accountType 
                          FROM   tblaccounttypes 
                          WHERE  (parentId=0)');
        return $this->db->resultSet();
    }
    public function test()
    {
        $this->db->query('SELECT ID,accountType FROM tblaccounttypes');
        return $this->db->resultSet();
    }
    public function getAccounts($main)
    {
        $this->db->query('SELECT ID,UCASE(accountType) as accountType 
                          FROM tblaccounttypes 
                          WHERE (isBank=0) AND (parentId=:pid)
                          ORDER BY accountType');
        $this->db->bind(':pid',$main);
        return $this->db->resultSet();
    }
    public function checkExists($data)
    {
        $sql = 'SELECT COUNT(ID) FROM tblaccounttypes WHERE (accountType=?) AND (ID <> ?) AND (congregationId = 0)';
        $arr = array();
        array_push($arr,trim(strtolower($data['accountname'])));
        array_push($arr,trim($data['id']));
        $results = checkExistsMod($this->db->dbh,$sql,$arr);
        if ($results > 0) {
            return false;
        }
        else {
            return true;
        }
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblaccounttypes (accountType,parentId,accountTypeId,isSubCategory,`description`,forGroup)
                          VALUES(:atype,:pid,:accid,:issub,:narr,:forgroup)');
        $this->db->bind(':atype',strtolower($data['accountname']));
        $this->db->bind(':pid', ($data['check'] == 1) ? $data['subcategory'] : $data['accounttype']);
        $this->db->bind(':accid',$data['accounttype']);
        $this->db->bind(':issub',$data['check']);
        $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : NULL);
        $this->db->bind(':forgroup',$data['forgroup']);
        if ($this->db->execute()) {
            $act = 'Created Account '.$data['accountname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function getAccount($id)
    {
        $this->db->query('SELECT * FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
}
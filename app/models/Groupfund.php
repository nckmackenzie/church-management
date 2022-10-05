<?php
class Groupfund
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function CheckRights($form)
    {
        return checkuserrights($this->db->dbh,$_SESSION['userId'],$form);
    }

    public function GetRequests()
    {
        $this->db->query('SELECT * FROM vw_group_requisitions  
                          WHERE (congregationId = :cid)
                          ORDER BY `Status`,RequestDate DESC');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
}
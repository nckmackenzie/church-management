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

    public function GetGroups()
    {
        $this->db->query('SELECT ID,UCASE(groupName) as groupName 
                          FROM tblgroups 
                          WHERE (active = 1) AND (deleted = 0) AND (congregationId = :cid)
                          ORDER BY groupName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetBalance($group,$date)
    {
        return getdbvalue($this->db->dbh,'SELECT getmmfopeningbalbydate(?,?)',[$group,$date]);
    }

    public function CreateUpdate($data)
    {
        if(!$data['isedit']){
            $this->db->query('INSERT INTO tblfundrequisition (RequisitionDate,GroupId,Purpose,AmountRequested) 
                              VALUES(:rdate,:gid,:purpose,:amount)');
        }else{
            $this->db->query('UPDATE tblfundrequisition SET RequisitionDate=:rdate,GroupId=:gid,Purpose=:purpose,AmountRequested=:amount 
                              WHERE (ID = :id)');
        }
        $this->db->bind(':rdate',!empty($data['reqdate']) ? $data['reqdate'] : null);
        $this->db->bind(':gid',!empty($data['group']) ? $data['group'] : null);
        $this->db->bind(':purpose',!empty($data['reason']) ? strtolower($data['reason']) : null);
        $this->db->bind(':amount',!empty($data['amount']) ? $data['amount'] : null);
        if($data['isedit']){
            $this->db->bind(':id',$data['id']);
        }
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function GetGroupCongregation($gid)
    {
        return getdbvalue($this->db->dbh,'SELECT congregationId FROM tblgroups WHERE ID = ?',[$gid]);
    }

    public function GetRequest($id)
    {
        $this->db->query('SELECT * FROM tblfundrequisition WHERE (ID=:id)');
        $this->db->bind(':id',(int)$id);
        return $this->db->single();
    }

    public function Delete($id)
    {
        $this->db->query('UPDATE tblfundrequisition SET Deleted = 1 WHERE (ID = :id)');
        $this->db->bind(':id',(int)$id);
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function GetRequestStatus($id)
    {
        return getdbvalue($this->db->dbh,'SELECT `Status` FROM tblfundrequisition WHERE ID = ?',[$id]);
    }
}
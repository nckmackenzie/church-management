<?php
class Official{
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
    public function getOfficials()
    {
        $this->db->query('SELECT * FROM vw_groupofficials WHERE congregationId=:cid');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function checkExists($data)
    {
        $this->db->query('SELECT ID FROM tblgroupofficials WHERE (yearId=:yid) AND (groupId=:gid)');
        $this->db->bind(':yid',$data['year']);
        $this->db->bind(':gid',$data['group']);
        $this->db->execute();
        if ($this->db->rowCount() > 0) {
           return false;
        }else{
            return true;
        }
    }
    public function getMembers()
    {
        if ($_SESSION['isParish'] == 1) {
            $this->db->query("SELECT m.ID,
                                     CONCAT(ucase(m.memberName),'-',ucase(c.CongregationName)) as memberName
                              FROM   tblmember m inner join tblcongregation c on m.congregationId=c.ID
                              WHERE  (m.memberStatus=1) AND (m.deleted=0)
                              ORDER BY memberName");
        }else{
            $this->db->query('SELECT ID,UCASE(memberName) as memberName 
                         FROM tblmember WHERE (memberStatus=1) AND (deleted=0)
                         AND (congregationId=:cong)
                         ORDER BY memberName');
            $this->db->bind('cong',$_SESSION['congId']);
        }
        return $this->db->resultSet();
    }
    public function getGroups()
    {
        $this->db->query('SELECT ID,UCASE(groupName) as groupName 
                         FROM tblgroups WHERE (active=1) AND (deleted=0)
                         AND (congregationId=:cong)
                         ORDER BY groupName');
        $this->db->bind('cong',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getYears()
    {
        $this->db->query('SELECT ID,UCASE(yearName) as yearName 
                         FROM tblfiscalyears WHERE (closed=0) AND (deleted=0)
                         ORDER BY yearName');
        $this->db->bind('cong',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblgroupofficials (yearId,groupId,chairmanId,secretaryId,treasurerId
                         ,vchairmanId,vsecretaryId,vtreasurerId,patronId,congregationId) 
                         VALUES(:yid,:gid,:cid,:sec,:tid,:vcid,:vsid,:vtid,:pid,:cong)');
        $this->db->bind(':yid',$data['year']);
        $this->db->bind(':gid',$data['group']);
        $this->db->bind(':cid',$data['chairman']);
        $this->db->bind(':sec',$data['secretary']);
        $this->db->bind(':tid',$data['treasurer']);
        $this->db->bind(':vcid',$data['vchairman']);
        $this->db->bind(':vsid',$data['vsecretary']);
        $this->db->bind(':vtid',$data['vtreasurer']);
        $this->db->bind(':pid',$data['patron']);
        $this->db->bind(':cong',$_SESSION['congId']);
        if ($this->db->execute()) {
            $act = 'Created Group Officials For '.$data['groupname'] . ' For Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function getGroupOfficials($id)
    {
        $this->db->query('SELECT * FROM tblgroupofficials WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblgroupofficials SET chairmanId=:cid,secretaryId=:sec,
                          treasurerId=:tid,vchairmanId=:vcid,vsecretaryId=:vsid,vtreasurerId=:vtid
                          ,patronId=:pid WHERE (ID=:id)');
        $this->db->bind(':cid',$data['chairman']);
        $this->db->bind(':sec',$data['secretary']);
        $this->db->bind(':tid',$data['treasurer']);
        $this->db->bind(':vcid',$data['vchairman']);
        $this->db->bind(':vsid',$data['vsecretary']);
        $this->db->bind(':vtid',$data['vtreasurer']);
        $this->db->bind(':pid',$data['patron']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Updated Group Officials For '.$data['groupname'] . ' For Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function delete($data)
    {
        $this->db->query('DELETE FROM tblgroupofficials WHERE (ID=:id)');
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Deleted Group Officials For '.$data['groupname'] . ' For Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
}
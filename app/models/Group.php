<?php
class Group {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    function createLog($con,$activity){
        $this->db->query('INSERT INTO tbllogs (userId,activity,activityDate,congregationId)
                          VALUES(:user,:act,:actdate,:congid)');
        $currdate = date("Y/m/d");                  
        $this->db->bind(':user',$_SESSION['userId']);
        $this->db->bind(':act',$activity);
        $this->db->bind(':actdate',$currdate);
        $this->db->bind(':congid',$_SESSION['congId']);
        $this->db->execute();
    }
    public function index()
    {
        $this->db->query("SELECT ID,
                                 groupName,
                                 active,
                                 IF(active=1,'Active','Inactive') as `status`
                          FROM tblgroups
                          WHERE (deleted=:del) AND (congregationId=:cid)
                          ORDER BY groupName");
        $this->db->bind(':del',$_SESSION['zero']);
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function checkExists($name)
    {
        $this->db->query('SELECT ID FROM tblgroups WHERE (groupName=:ame)
                          AND (congregationId=:cid)');
        $this->db->bind(':ame',$name);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->execute();
        if ($this->db->rowCount() > 0) {
           return false;
        }else{
            return true;
        }
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblgroups (groupName,active,congregationId) VALUES(:did,:act,:cid)');
        $this->db->bind(':did',$data['name']);
        $this->db->bind(':act',$data['active']);
        $this->db->bind(':cid',$_SESSION['congId']);
        if ($this->db->execute()) {
            $activity = 'Added Group '.$data['name'];
            $this->createLog($this->db,$activity);
            return true;
        }
        else{
            return false;
        }
    }
    public function fetchGroup($id)
    {
        $this->db->query('SELECT * FROM tblgroups WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblgroups SET groupName=:group,active=:act WHERE (ID=:id)');
        $this->db->bind(':group',$data['name']);
        $this->db->bind(':act',$data['active']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $activity = 'Edited Group '.$data['name'];
            $this->createLog($this->db,$activity);
            return true;
        }
        else{
            return false;
        }
    }
    public function delete($data)
    {
        $this->db->query('UPDATE tblgroups SET deleted=:del WHERE (ID=:id)');
        $this->db->bind(':del',$_SESSION['one']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $activity = 'Deleted Group '.$data['name'];
            $this->createLog($this->db,$activity);
            return true;
        }
        else{
            return false;
        }
    }
}
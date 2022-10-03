<?php
class District {
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
    public function loadDistricts()
    {
        $this->db->query('SELECT * FROM tbldistricts WHERE (deleted=:del) AND (congregationId=:cid)');
        $this->db->bind(':del',$_SESSION['zero']);
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function checkExists($name)
    {
        $this->db->query('SELECT ID FROM tbldistricts WHERE (districtName=:ame)
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
        $this->db->query('INSERT INTO tbldistricts (districtName,congregationId) VALUES(:did,:cid)');
        $this->db->bind(':did',$data['name']);
        $this->db->bind(':cid',$_SESSION['congId']);
        if ($this->db->execute()) {
            return true;
        }
        else{
            return false;
        }
    }
    public function fetchDistrict($id)
    {
        $this->db->query('SELECT * FROM tbldistricts WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tbldistricts SET districtName=:did WHERE (ID=:id)');
        $this->db->bind(':did',$data['name']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            return true;
        }
        else{
            return false;
        }
    }
    public function delete($id)
    {
        $this->db->query('UPDATE tbldistricts SET deleted=:del WHERE (ID=:id)');
        $this->db->bind(':del',$_SESSION['one']);
        $this->db->bind(':id',$id);
        if ($this->db->execute()) {
            return true;
        }
        else{
            return false;
        }
    }
}
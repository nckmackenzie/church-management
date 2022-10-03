<?php
class Vat{
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
        $this->db->query('SELECT * FROM tblvats WHERE (deleted=0)');
        return $this->db->resultSet();
    }
    public function checkExists($data)
    {
        $sql = 'SELECT COUNT(ID) FROM tblvats WHERE vatName = ? AND ID <> ? AND deleted=0';
        $arr = array();
        
        array_push($arr,trim(strtolower($data['vatname'])));
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
        $this->db->query('INSERT INTO tblvats (vatName,rate,active) VALUES(:nam,:rate,:active)');
        $this->db->bind(':nam',strtolower($data['vatname']));
        $this->db->bind(':rate',$data['rate']);
        $this->db->bind(':active',$data['active']);
        if ($this->db->execute()) {
            $act = 'Created V.A.T '.$data['vatname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function getVat($id)
    {
        $this->db->query('SELECT * FROM tblvats WHERE (ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblvats SET vatName=:nam,rate=:rate,active=:active WHERE (ID=:id)');
        $this->db->bind(':nam',strtolower($data['vatname']));
        $this->db->bind(':rate',$data['rate']);
        $this->db->bind(':active',$data['active']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Updated V.A.T '.$data['vatname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function delete($data)
    {
        $this->db->query('UPDATE tblvats SET deleted=1 WHERE (ID=:id)');
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Deleted V.A.T '.$data['vatname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
}

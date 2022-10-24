<?php
class Agegroup {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    public function index()
    {
        $this->db->query('SELECT ID,
                                 ucase(ageGroupName) AS ageGroupName,
                                 fromAge,
                                 toAge
                          FROM   tblagegroup
                          WHERE  (deleted=0)
                          ORDER BY ageGroupName');
        return $this->db->resultSet();
    }
    public function checkExists($name)
    {
        $sql = 'SELECT COUNT(ID) FROM tblagegroup WHERE ageGroupName = ?';
        $results = getRecordExists($sql,$this->db->dbh,$name);
        if ($results > 0) {
            return false;
        }
        else {
            return true;
        }
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblagegroup (ageGroupName,fromAge,toAge)
                          VALUES(:aname,:fage,:tage)');
        $this->db->bind(':aname',strtolower($data['name']));
        $this->db->bind(':fage',$data['from']);
        $this->db->bind(':tage',$data['to']);
        if ($this->db->execute()) {
            $act = 'Created Age Group '.$data['name'];
            saveLog($this->db->dbh,$act);
            return true;
        }else{
            return false;
        }
    }
    public function getAgeGroup($id)
    {
        // $nid = decryptId($id);
        $this->db->query('SELECT * FROM tblagegroup WHERE ID=:id');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblagegroup SET ageGroupName=:aname,fromAge=:fage,toAge=:tage 
                          WHERE (ID=:id)');
        $this->db->bind(':aname',strtolower($data['name']));
        $this->db->bind(':fage',$data['from']);
        $this->db->bind(':tage',$data['to']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Updated Age Group '.$data['name'];
            saveLog($this->db->dbh,$act);
            return true;
        }else{
            return false;
        }
    }
    public function delete($data)
    {
        $this->db->query('UPDATE tblagegroup SET deleted = 1
                          WHERE (ID = :id)');
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Deleted Age Group '.$data['name'];
            saveLog($this->db->dbh,$act);
            return true;
        }else{
            return false;
        }
    }
}
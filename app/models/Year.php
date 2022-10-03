<?php
class Year{
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
       $this->db->query('SELECT * FROM tblfiscalyears WHERE (deleted=0)');
       return $this->db->resultSet();
    }
    public function checkExists($data)
    {
        $sql = 'SELECT COUNT(ID) FROM tblfiscalyears WHERE yearName = ? AND ID <> ? AND deleted=0';
        $arr = array();
        
        array_push($arr,trim(strtolower($data['yearname'])));
        array_push($arr,trim($data['id']));
        $results = checkExistsMod($this->db->dbh,$sql,$arr);
        if ($results > 0) {
            return false;
        }
        else {
            return true;
        }
    }
    public function checkYearConflict($date)
    {
        $sql = 'SELECT COUNT(ID) AS dbcount FROM tblfiscalyears WHERE ? BETWEEN startDate AND endDate AND deleted=0';
        $results = getRecordExists($sql,$this->db->dbh,$date);
        if ($results > 0) {
            return false;
        }
        else {
            return true;
        }
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblfiscalyears (yearName,startDate,endDate,createdDate,createdBy)
                          VALUES(:yname,:sdate,:edate,:cdate,:cby)');
        $this->db->bind(':yname',strtolower($data['yearname']));
        $this->db->bind(':sdate',$data['startdate']);
        $this->db->bind(':edate',$data['enddate']);
        $this->db->bind(':cdate',date('Y-m-d'));
        $this->db->bind(':cby',$_SESSION['userId']);
        if ($this->db->execute()) {
            $act = 'Created Financial Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function getYear($id)
    {
        $this->db->query('SELECT * FROM tblfiscalyears WHERE (ID=:id)');
        $this->db->bind(':id',decryptId(trim($id)));
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblfiscalyears SET yearName=:yname
                          WHERE (ID=:id)');
        $this->db->bind(':yname',strtolower($data['yearname']));
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Updated Financial Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function close($data)
    {
        $this->db->query('UPDATE tblfiscalyears SET closed=1,closedDate=:cdate
                          WHERE (ID=:id)');
        $this->db->bind(':cdate',date('Y-m-d'));
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Closed Financial Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function delete($data)
    {
        $this->db->query('UPDATE tblfiscalyears SET deleted=1
                          WHERE (ID=:id)');
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Deleted Financial Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
}
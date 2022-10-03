<?php
class Congregation {
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
    public function getCongregations()
    {
        $this->db->query('SELECT * FROM tblcongregation WHERE (deleted=0)');
        return $this->db->resultSet();
    }
    public function checkExists($name)
    {
        $sql = 'SELECT COUNT(ID) FROM tblcongregation WHERE CongregationName = ?';
        $results = getRecordExists($sql,$this->db->dbh,$name);
        if ($results > 0) {
            return false;
        }
        else {
            return true;
        }
    }
    public function getCongregation($id)
    {
        $this->db->query('SELECT * FROM tblcongregation WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblcongregation (ParishName,CongregationName,contact,email,`Address`
                                      ,AboutUs,IsParish,prefix,InaugurationDate,SactuaryType,YearStarted,
                                      FoundationStone,DedicationDate)
                          VALUES(:pname,:cname,:cont,:email,:addr,:abt,:isp,:prefix,:idate,:stype,:ystarted,
                                 :stone,:dedicated)');
        $this->db->bind(':pname','kalimoni parish');
        $this->db->bind(':cname',strtolower($data['congregationname']));
        $this->db->bind(':cont',!empty($data['contact']) ? $data['contact'] : NULL);
        $this->db->bind(':email',!empty($data['email']) ? $data['email'] : NULL);
        $this->db->bind(':addr',!empty($data['address']) ? strtolower($data['address']) : NULL);
        $this->db->bind(':abt',!empty($data['aboutus']) ? strtolower($data['aboutus']) : NULL);
        $this->db->bind(':isp',0);
        $this->db->bind(':prefix',!empty($data['prefix']) ? strtolower($data['prefix']) : NULL);$this->db->bind(':idate',!empty($data['inauguration']) ? $data['inauguration'] : NULL);
        $this->db->bind(':stype',$data['type']);
        $this->db->bind(':ystarted',(int)$data['started']);
        $this->db->bind(':stone',!empty($data['foundation']) ? $data['foundation'] : NULL);
        $this->db->bind(':dedicated',!empty($data['dedication']) ? $data['dedication'] : NULL);
        
        if ($this->db->execute()) {
            $activity = 'Created Congregation '.$data['congregationame'];
            $this->createLog($this->db,$activity);
            return true;
        }
        else{
            return false;
        }
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblcongregation SET CongregationName=:cname,contact=:contact,
                                 email=:email,Address=:addr,AboutUs=:about,InaugurationDate=:idate,
                                 SactuaryType=:stype,YearStarted=:ystarted,FoundationStone=:stone,DedicationDate=:dedicated 
                         WHERE (ID=:id)');
        $this->db->bind(':cname',$data['congregationname']);
        $this->db->bind(':contact',$data['contact']);
        $this->db->bind(':email',$data['email']);
        $this->db->bind(':addr',$data['address']);
        $this->db->bind(':about',$data['aboutus']);
        $this->db->bind(':idate',!empty($data['inauguration']) ? $data['inauguration'] : NULL);
        $this->db->bind(':stype',$data['type']);
        $this->db->bind(':ystarted',(int)$data['started']);
        $this->db->bind(':stone',!empty($data['foundation']) ? $data['foundation'] : NULL);
        $this->db->bind(':dedicated',!empty($data['dedication']) ? $data['dedication'] : NULL);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $activity = 'Edited Congregation Info For '.$data['congregationame'];
            $this->createLog($this->db,$activity);
            return true;
        }
        else{
            return false;
        }
    }
    public function delete($data)
    {
        $this->db->query('UPDATE tblcongregation SET deleted=1 WHERE (ID=:id)');
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $activity = 'Deleted Congregation '.$data['congregationame'];
            $this->createLog($this->db,$activity);
            return true;
        }
        else{
            return false;
        }
    }
}
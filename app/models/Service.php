<?php
class Service {
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
    public function getServices()
    {
       $this->db->query('SELECT * FROM tblservices 
                         WHERE (congregationId=:cid) AND (deleted=:del)
                         ORDER BY serviceName');
       $this->db->bind(':cid',$_SESSION['congId']);
       $this->db->bind(':del',$_SESSION['zero']);
       return $this->db->resultSet();
    }
    public function checkExists($name)
    {
        $this->db->query('SELECT ID FROM tblservices WHERE (serviceName=:ame)
                          AND (congregationId=:cid) AND (deleted=0)');
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
        $this->db->query('INSERT INTO tblservices (serviceName,serviceTime,congregationId)
                         VALUES(:sname,:stime,:cid)');
        $this->db->bind(':sname',$data['servicename']);
        $this->db->bind(':stime',$data['servicetime']);
        $this->db->bind(':cid',$_SESSION['congId']);
        if ($this->db->execute()) {
            $activity = 'Added Service '.$data['servicename'];
            $this->createLog($this->db,$activity);
           return true;
        }
        else{
            return false;
        }
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblservices SET serviceName=:sname,serviceTime=:stime 
                          WHERE (ID=:id)');
        $this->db->bind(':sname',$data['servicename']);
        $this->db->bind(':stime',$data['servicetime']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $activity = 'Edited Service '.$data['servicename'];
            $this->createLog($this->db,$activity);
           return true;
        }
        else{
            return false;
        }
    }
    public function getService($id)
    {
        $this->db->query('SELECT * FROM tblservices WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function delete($data)
    {
        $this->db->query('SELECT ID FROM tblserviceinfo WHERE (serviceId=:id)');
        $this->db->bind(':id',$data['id']);
        $this->db->execute();
        $res = $this->db->rowCount();

        $this->db->query('SELECT ID FROM tblseatbooking WHERE (serviceId=:id)');
        $this->db->bind(':id',$data['id']);
        $this->db->execute();
        $res1 = $this->db->rowCount();

        if ($res > 0 || $res1 > 0) {
            return false;
        }
        else{
            $this->db->query('UPDATE tblservices SET deleted=:del
                              WHERE (ID=:id)');
            $this->db->bind(':del',$_SESSION['one']);
            $this->db->bind(':id',$data['id']);
            if ($this->db->execute()) {
                $activity = 'Deleted Service '.$data['servicename'];
                $this->createLog($this->db,$activity);
            return true;
            }
            else{
                return false;
            }
        }
    }
    public function getServicesInfo()
    {
        $this->db->query("SELECT i.ID,
                                 UCASE(s.serviceName) as serviceName,
                                 serviceDate,
                                 UCASE(headedBy) as headedBy,
                                 UCASE(preacher) as preacher,
                                 attendance
                          FROM tblserviceinfo i inner join tblservices s on i.serviceId=s.ID
                          WHERE (i.deleted=0) AND (i.congregationId=:cid)
                          ORDER BY serviceDate DESC");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function checkInfoExists($data)
    {
        $this->db->query('SELECT ID FROM tblserviceinfo WHERE (serviceId=:id)
                          AND (serviceDate=:sdate) AND (deleted=0)');
        $this->db->bind(':id',$data['service']);
        $this->db->bind(':sdate',$data['date']);
        $this->db->execute();
        if ($this->db->rowCount() > 0) {
           return false;
        }else{
            return true;
        }
    }
    public function createinfo($data)
    {
        $this->db->query('INSERT INTO tblserviceinfo (serviceId,serviceDate,headedBy,preacher,attendance
                         ,envelopePledge,ordinary,special,congregationId)
                         VALUES(:seid,:sdate,:headed,:preacher,:attendance,:env,:ordinary,:special,:cid)');
        $this->db->bind(':seid',$data['service']);
        $this->db->bind(':sdate',$data['date']);
        $this->db->bind(':headed',!empty($data['headedby']) ? $data['headedby'] : NULL);
        $this->db->bind(':preacher',!empty($data['preacher']) ? $data['preacher'] : NULL);
        $this->db->bind(':attendance',!empty($data['attendance']) ? $data['attendance'] : NULL);
        $this->db->bind(':env',!empty($data['envelopepledge']) ? $data['envelopepledge'] : NULL);
        $this->db->bind(':ordinary',!empty($data['ordinary']) ? $data['ordinary'] : NULL);
        $this->db->bind(':special',!empty($data['special']) ? $data['special'] : NULL);
        $this->db->bind(':cid',$_SESSION['congId']);
        if ($this->db->execute()) {
            $activity = 'Added Service Info For '.$data['servicename'] . ' For Date '.$data['date'];
            $this->createLog($this->db,$activity);
           return true;
        }
        else{
            return false;
        }
    }
    public function updateinfo($data)
    {
        $this->db->query('UPDATE tblserviceinfo SET serviceId=:seid,serviceDate=:sdate,headedBy=:headed
                         ,preacher=:preacher,attendance=:attendance,envelopePledge=:env,ordinary=:ordinary,
                         special=:special WHERE (ID=:id)');
        $this->db->bind(':seid',$data['service']);
        $this->db->bind(':sdate',$data['date']);
        $this->db->bind(':headed',!empty($data['headedby']) ? $data['headedby'] : NULL);
        $this->db->bind(':preacher',!empty($data['preacher']) ? $data['preacher'] : NULL);
        $this->db->bind(':attendance',!empty($data['attendance']) ? $data['attendance'] : NULL);
        $this->db->bind(':env',!empty($data['envelopepledge']) ? $data['envelopepledge'] : NULL);
        $this->db->bind(':ordinary',!empty($data['ordinary']) ? $data['ordinary'] : NULL);
        $this->db->bind(':special',!empty($data['special']) ? $data['special'] : NULL);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $activity = 'Updated Service Info For '.$data['servicename'] . ' For Date '.$data['date'];
            $this->createLog($this->db,$activity);
           return true;
        }
        else{
            return false;
        }
    }
    public function getInfo($id)
    {
        $this->db->query('SELECT * FROM tblserviceinfo WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function deleteinfo($data)
    {
        $this->db->query('UPDATE tblserviceinfo SET deleted=:del WHERE (ID=:id)');
        $this->db->bind(':del',$_SESSION['one']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $activity = 'Deleted Service Info For '.$data['servicename'];
            $this->createLog($this->db,$activity);
           return true;
        }
        else{
            return false;
        }
    }
}
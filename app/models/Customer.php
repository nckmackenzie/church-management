<?php

class Customer {
    private $db;
    public function __construct()
    {
        $this->db =  new Database;
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
        $this->db->query('SELECT * FROM tblcustomers 
                          WHERE (congregationId=:cid) AND (deleted=:del)
                          ORDER BY customerName');
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':del',$_SESSION['zero']);
        return $this->db->resultSet();
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
    public function checkExists($name)
    {
        $this->db->query('SELECT ID FROM tblcustomers WHERE (customerName=:ame)
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
        $this->db->query('INSERT INTO tblcustomers (customerName,contact,address,pin,email,contactperson
                          ,congregationId) VALUES(:cname,:contact,:addr,:pin,:email,:person,:cid)');
        $this->db->bind(':cname',$data['customername']);
        $this->db->bind(':contact',!empty($data['contact']) ? $data['contact'] : NULL);
        $this->db->bind(':addr',!empty($data['address']) ? $data['address'] : NULL);
        $this->db->bind(':pin',!empty($data['pin']) ? $data['pin'] : NULL);
        $this->db->bind(':email',!empty($data['email']) ? $data['email'] : NULL);
        $this->db->bind(':person',$data['contactperson']);
        $this->db->bind(':cid',$_SESSION['congId']);
        if ($this->db->execute()) {
            $activity = 'Added Customer '.$data['customername'];
            $this->createLog($this->db,$activity);
            return true;
        }
        else{
            return false;
        }
    }
    public function getCustomer($id)
    {
        $this->db->query('SELECT * FROM tblcustomers WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblcustomers SET customerName=:cname,contact=:contact,address=:addr
                         ,pin=:pin,email=:email,contactperson=:person WHERE (ID=:id)');
        $this->db->bind(':cname',$data['customername']);
        $this->db->bind(':contact',!empty($data['contact']) ? $data['contact'] : NULL);
        $this->db->bind(':addr',!empty($data['address']) ? $data['address'] : NULL);
        $this->db->bind(':pin',!empty($data['pin']) ? $data['pin'] : NULL);
        $this->db->bind(':email',!empty($data['email']) ? $data['email'] : NULL);
        $this->db->bind(':person',$data['contactperson']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $activity = 'Edited Customer '.$data['customername'];
            $this->createLog($this->db,$activity);
            return true;
        }
        else{
            return false;
        }
    }
    public function delete($data)
    {
        $this->db->query('SELECT ID FROM tblinvoice_header WHERE (customerId=:id)');
        $this->db->bind(':id',$data['id']);
        $this->db->execute();
        if ($this->db->rowCount() > 0) {
            return false;
        }
        else{
            $this->db->query('UPDATE tblcustomers SET deleted=:del WHERE (ID=:id)');
            $this->db->bind(':del',$_SESSION['one']);
            $this->db->bind(':id',$data['id']);
            if ($this->db->execute()) {
                $activity = 'Deleted Customer '.$data['customername'];
                $this->createLog($this->db,$activity);
                return true;
            }
            else{
                return false;
            }
        }
        
    }
}
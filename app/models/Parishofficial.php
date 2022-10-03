<?php

class Parishofficial
{
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
    public function GetOfficials()
    {
        
    }
    public function getYears()
    {
        $this->db->query('SELECT ID,UCASE(yearName) as yearName 
                          FROM   tblfiscalyears WHERE (closed=0) AND (deleted=0)
                          ORDER BY yearName');
        $this->db->bind('cong',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getMembers()
    {
        $this->db->query("SELECT m.ID,
                                 CONCAT(ucase(m.memberName),'-',ucase(c.CongregationName)) as memberName
                          FROM   tblmember m inner join tblcongregation c on m.congregationId=c.ID
                          WHERE  (m.memberStatus=1) AND (m.deleted=0)
                          ORDER BY memberName");
        return $this->db->resultSet();
    }
    public function generateOfficialsId()
    {
        $this->db->query('SELECT COUNT(ID) FROM tblparishofficials');
        return (int)$this->db->getValue() + 1;
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblparishofficials (officialsId,startDate,endDate,parishMinisterId
                                      ,sessionClerkId,financeChairId,treasurerId,pairingElderId) 
                          VALUES(:ofid,:sdate,:edate,:pid,:scid,:fid,:treas,:peid)');
        $this->db->bind(':ofid',$data['ofid']);
        $this->db->bind(':sdate',$data['start']);
        $this->db->bind(':edate',$data['end']);
        $this->db->bind(':pid',$data['pminister']);
        $this->db->bind(':scid',$data['sclerk']);
        $this->db->bind(':fid',$data['fchair']);
        $this->db->bind(':treas',$data['treasurer']);
        $this->db->bind(':peid',$data['pelder']);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
    
}
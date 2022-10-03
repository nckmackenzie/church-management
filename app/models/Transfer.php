<?php

class Transfer
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetDistricts($cid)
    {
        $this->db->query('SELECT ID,UCASE(districtName) AS fieldName 
                          FROM tbldistricts
                          WHERE (deleted = 0) AND (congregationId = :cid)
                          ORDER BY fieldName');
        $this->db->bind(':cid',(int)$cid);
        return $this->db->resultSet();
    }

    public function GetMembers($did)
    {
        $this->db->query('SELECT ID,UCASE(memberName) AS fieldName 
                          FROM tblmember
                          WHERE (deleted = 0) AND (memberStatus = 1) AND (districtId = :did)
                          ORDER BY fieldName');
        $this->db->bind(':did',(int)$did);
        return $this->db->resultSet();
    }
    
}
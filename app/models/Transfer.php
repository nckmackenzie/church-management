<?php

class Transfer
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetCongregations()
    {
        $this->db->query('SELECT ID,UCASE(CongregationName) AS CongregationName 
                          FROM tblcongregation
                          WHERE (deleted = 0) AND (IsParish = 0)
                          ORDER BY CongregationName');
        return $this->db->resultSet();
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

    public function GetLastIDAndPrefix($cong)
    {
        $sql = 'SELECT IFNULL(MAX(RIGHT(memberId,4)),0) As id FROM `tblmember` where congregationId = ?';
        $lastid = getdbvalue($this->db->dbh,$sql,[$cong]);
        $sql1 ='SELECT prefix FROM tblcongregation WHERE ID = ?';
        $prefix = getdbvalue($this->db->dbh,$sql1,[$cong]);
        return [$lastid,$prefix];
    }
    
    public function Transfer($data)
    {
        $firstId = (int)$this->GetLastIDAndPrefix($data['newcong'])[0] + 1;
        $prefix = $this->GetLastIDAndPrefix($data['newcong'])[1];
        try {
            $this->db->dbh->beginTransaction();

            for($i=0; $i < count($data['members']); $i++){
                $memberno = formatStringId($firstId + $i);
                $memberid = $prefix .'/'.$memberno;
                $this->db->query('INSERT INTO tblmembertransfers (memberId,fromId,toId,transferDate,
                                         reason,fromDistrict,toDistrict)
                             VALUES(:mid,:fid,:tid,:tdate,:reason,:fdist,:tdist)');
                $this->db->bind(':mid',$data['members'][$i]);
                $this->db->bind(':fid',!empty($data['currentcong']) ? $data['currentcong'] : null);
                $this->db->bind(':tid',!empty($data['newcong']) ? $data['newcong'] : null);
                $this->db->bind(':tdate',!empty($data['tdate']) ? $data['tdate'] : null);
                $this->db->bind(':reason',!empty($data['reason']) ? strtolower($data['reason']) : null);
                $this->db->bind(':fdist',!empty($data['currentdist']) ? $data['currentdist'] : null);
                $this->db->bind(':tdist',!empty($data['newdist']) ? $data['newdist'] : null);
                $this->db->execute();

                $this->db->query('UPDATE tblmember SET 
                                    congregationId = :cid,
                                    districtId=:did,
                                    memberId = :mid 
                                  WHERE (ID=:id)');
                $this->db->bind(':cid',$data['newcong']);
                $this->db->bind(':did',$data['newdist']);
                $this->db->bind(':mid',$memberid);
                $this->db->bind(':id',$data['members'][$i]);
                $this->db->execute();
            }

            if(!$this->db->dbh->commit()){
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            throw $e;
            return false;
        }
    }
}
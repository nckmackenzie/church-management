<?php
class MemberReport{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    public function getDistricts()
    {
        $this->db->query('SELECT ID,UCASE(districtName) as districtName
                          FROM tbldistricts WHERE (deleted=0) AND (congregationId=:cid)
                          ORDER BY districtName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();                  
    }
    public function loadMembersRpt($district,$status,$from,$to)
    {
        if ($status < 3 ) {
            if ($district == 0) {
                $this->db->query("SELECT UCASE(M.memberName) AS memberName,UCASE(G.gender) AS gender,
                                  M.idNo,M.contact,UCASE(D.districtName) as districtName,
                                  UCASE(P.positionName) AS positionName,
                                  IF(membershipStatus = 1,'FULL',IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4, 'UNDER-12','NOT SPECIFIED')))) AS mstatus
                                  FROM tblmember M INNER JOIN tblpositions P ON M.positionId=P.ID INNER JOIN
                                  tbldistricts D ON M.districtId=D.ID INNER JOIN tblgender G ON M.genderId=G.
                                  ID WHERE (memberStatus = :sta) ORDER BY memberName");
                $this->db->bind(':sta',$status);                  
            }else{
                $this->db->query("SELECT UCASE(M.memberName) AS memberName,UCASE(G.gender) AS gender,
                                  M.idNo,M.contact,UCASE(D.districtName) as districtName,
                                  UCASE(P.positionName) AS positionName,
                                  IF(membershipStatus = 1,'FULL',IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4, 'UNDER-12','NOT SPECIFIED')))) AS mstatus
                                  FROM tblmember M INNER JOIN tblpositions P ON M.positionId=P.ID INNER JOIN
                                  tbldistricts D ON M.districtId=D.ID INNER JOIN tblgender G ON M.genderId=G.
                                  ID WHERE (memberStatus = :sta) AND (districtId=:did) ORDER BY memberName");
                $this->db->bind(':sta',$status);
                $this->db->bind(':did',$district);
            }
        }else{
            if ($district == 0) {
                $this->db->query('SELECT memberName,gender,age,contact,district,remark 
                                  FROM   vw_byage 
                                  WHERE  (age BETWEEN :froma AND :toa);
                                  ORDER BY memberName');
                $this->db->bind(':froma',$from);
                $this->db->bind(':toa',$to);
            }else{
                $this->db->query('SELECT memberName,gender,age,contact,district,remark 
                                  FROM   vw_byage 
                                  WHERE  (age BETWEEN :froma AND :toa) AND (districtId = :did);
                                  ORDER BY memberName');
                $this->db->bind(':froma',$from);
                $this->db->bind(':toa',$to);
                $this->db->bind(':did',$district);
            }
        }
        
        return $this->db->resultSet();
    }
    public function getTransfered($data)
    {
        $this->db->query('SELECT UCASE(M.memberName) AS memberName,G.gender,UCASE(P.positionName) AS
                                 positionName,UCASE(C.congregationName) AS congregation,
                                 T.transferDate,T.reason
                          FROM   tblmembertransfers T INNER JOIN tblmember M ON T.memberId=M.ID
                                 LEFT JOIN tblcongregation C ON T.toId=C.ID INNER JOIN tblgender G ON
                                 M.genderId=G.ID LEFT JOIN tblpositions P ON M.positionId=P.ID
                          WHERE (T.fromId=:fid) AND (T.transferDate BETWEEN :sta AND :endd)');
        $this->db->bind(':fid',$_SESSION['congId']);
        $this->db->bind(':sta',$data['from']);
        $this->db->bind(':endd',$data['to']);
        return $this->db->resultSet();
    }
    public function byStatusRpt($data)
    {
        if ($data['status'] == 0 && $data['district'] == 0) {
            $this->db->query("SELECT UCASE(memberName) AS memberName,UCASE(G.gender) AS gender,
                                     IF(M.memberStatus=1,'Active',IF(M.memberStatus=2,'Dormant','Deceased')) AS mstatus,UCASE(D.districtName) AS district,UCASE(P.positionName) as position,IF(membershipStatus=1,'FULL', IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4,'UNDER-12','NOT SPECIFIED')))) AS membershipStatus
                              FROM   tblmember M inner join tblgender G ON M.genderId=G.ID INNER JOIN
                                     tbldistricts D ON M.districtId=D.ID INNER JOIN tblpositions P ON
                                     M.positionId=P.ID 
                              WHERE  (M.congregationId=:cid)");
            $this->db->bind(':cid',$_SESSION['congId']);
        }
        elseif ($data['status'] != 0 && $data['district'] == 0 ) {
            $this->db->query("SELECT UCASE(memberName) AS memberName,UCASE(G.gender) AS gender,
                                     IF(M.memberStatus=1,'Active',IF(M.memberStatus=2,'Dormant','Deceased')) AS mstatus,UCASE(D.districtName) AS district,UCASE(P.positionName) as position,IF(membershipStatus=1,'FULL', IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4,'UNDER-12','NOT SPECIFIED')))) AS membershipStatus
                              FROM   tblmember M inner join tblgender G ON M.genderId=G.ID INNER JOIN
                                     tbldistricts D ON M.districtId=D.ID INNER JOIN tblpositions P ON
                                     M.positionId=P.ID 
                              WHERE  (M.congregationId=:cid) AND (M.membershipStatus = :stid)");
            $this->db->bind(':cid',$_SESSION['congId']);                  
            $this->db->bind(':stid',$data['status']);                  
        }
        elseif ($data['status'] == 0 && $data['district'] != 0 ) {
            $this->db->query("SELECT UCASE(memberName) AS memberName,UCASE(G.gender) AS gender,
                                     IF(M.memberStatus=1,'Active',IF(M.memberStatus=2,'Dormant','Deceased')) AS mstatus,UCASE(D.districtName) AS district,UCASE(P.positionName) as position,IF(membershipStatus=1,'FULL', IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4,'UNDER-12','NOT SPECIFIED')))) AS membershipStatus
                              FROM   tblmember M inner join tblgender G ON M.genderId=G.ID INNER JOIN
                                     tbldistricts D ON M.districtId=D.ID INNER JOIN tblpositions P ON
                                     M.positionId=P.ID 
                              WHERE  (M.congregationId=:cid) AND (M.districtId = :did)");
            $this->db->bind(':cid',$_SESSION['congId']);                  
            $this->db->bind(':did',$data['district']);                  
        }
        else{
            $this->db->query("SELECT UCASE(memberName) AS memberName,UCASE(G.gender) AS gender,
                                     IF(M.memberStatus=1,'Active',IF(M.memberStatus=2,'Dormant','Deceased')) AS mstatus,UCASE(D.districtName) AS district,UCASE(P.positionName) as position,IF(membershipStatus=1,'FULL', IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4,'UNDER-12','NOT SPECIFIED')))) AS membershipStatus
                              FROM   tblmember M inner join tblgender G ON M.genderId=G.ID INNER JOIN
                                     tbldistricts D ON M.districtId=D.ID INNER JOIN tblpositions P ON
                                     M.positionId=P.ID 
                              WHERE  (M.membershipStatus = :stid) AND (M.districtId = :did)");
            $this->db->bind(':stid',$data['status']);                  
            $this->db->bind(':did',$data['district']);
        }
        return $this->db->resultSet();
    }
    public function getResidenceRpt($district)
    {
        if ($district == 0) {
            $this->db->query('SELECT UCASE(M.memberName) AS memberName,UCASE(G.gender) AS gender,M.contact
                                     ,UCASE(D.districtName) as districtName,UCASE(M.occupation) AS occupation,UCASE(M.residence) AS residence
                              FROM   tblmember M INNER JOIN tblpositions P ON M.positionId=P.ID INNER JOIN
                                     tbldistricts D ON M.districtId=D.ID INNER JOIN tblgender G ON M.genderId=G.ID
                              WHERE  (M.congregationId=:cid)');
            $this->db->bind(':cid',$_SESSION['congId']);
        }else{
            $this->db->query('SELECT UCASE(M.memberName) AS memberName,UCASE(G.gender) AS gender,M.contact
                                     ,UCASE(D.districtName) as districtName,UCASE(M.occupation) AS occupation,UCASE(M.residence) AS residence
                              FROM   tblmember M INNER JOIN tblpositions P ON M.positionId=P.ID INNER JOIN
                                     tbldistricts D ON M.districtId=D.ID INNER JOIN tblgender G ON M.genderId=G.ID
                              WHERE  (M.district=:did)');
            $this->db->bind(':did',$district);
        }
        return $this->db->resultSet();
    }
    public function getGroups()
    {
        $this->db->query('SELECT ID,UCASE(groupName) as groupName
                          FROM tblgroups WHERE (active=1) AND (deleted=0) AND (congregationId=:cid)
                          ORDER BY groupName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function groupMembership($group)
    {
        if ($group == 0) {
            $this->db->query('SELECT UCASE(M.memberName) AS memberName,UCASE(N.gender) AS gender,
                                 UCASE(G.groupName) AS groupName, M.contact,UCASE(D.districtName) AS districtName
                          FROM   tblgroupmembership S INNER JOIN tblgroups G ON S.groupId=G.ID
                                 INNER JOIN tblmember M ON S.memberId=M.ID LEFT JOIN tblgender N
                                 ON M.genderId=N.ID LEFT JOIN tbldistricts D ON M.districtId=D.ID
                          WHERE  (G.congregationId = :cid)');
            $this->db->bind(':cid',$_SESSION['congId']);
        }
        else{
            $this->db->query('SELECT UCASE(M.memberName) AS memberName,UCASE(N.gender) AS gender,
                                 UCASE(G.groupName) AS groupName, M.contact,UCASE(D.districtName) AS districtName
                          FROM   tblgroupmembership S INNER JOIN tblgroups G ON S.groupId=G.ID
                                 INNER JOIN tblmember M ON S.memberId=M.ID LEFT JOIN tblgender N
                                 ON M.genderId=N.ID LEFT JOIN tbldistricts D ON M.districtId=D.ID
                          WHERE  (S.groupId = :gid)');
            $this->db->bind(':gid',$group);
        } 
        return $this->db->resultSet();                 
    }
    public function getFamily($district)
    {
        if ($district == 0) {
            // $this->db->query('SELECT ucase(m.memberName) as Main,
            //                          ucase(c.memberName) as other,
            //                          ucase(r.relationship) as relation
            //                   FROM   tblmember_family f inner join tblmember m on f.memberId = m.ID
            //                          inner join tblmember c on f.familyMemberId=c.ID
            //                          inner join tblrelationship r on f.relationshipId=r.ID');
            $this->db->query('SELECT * FROM vw_family');
        }else{
            $this->db->query('SELECT * FROM vw_family
                              WHERE  (districtId = :did)');
            $this->db->bind(':did',$district);
        }
        return $this->db->resultSet();
    }
    public function getFamilyCount()
    {
        $this->db->query('SELECT COUNT(DISTINCT memberId) AS fcount 
                          FROM tblmember_family 
                          WHERE congregationId = :cid');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->getValue();
    }
    public function getFamilyCountByDistrict($district)
    {
        $this->db->query('SELECT COUNT(DISTINCT Main) AS fcount 
                          FROM vw_family 
                          WHERE district = :did');
        $this->db->bind(':did',$district);
        return $this->db->getValue();
    }
}
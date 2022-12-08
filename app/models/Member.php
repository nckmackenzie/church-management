<?php
class Member {
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
    public function checkIDExists($data)
    {
        $sql = 'SELECT COUNT(ID) FROM tblmember WHERE (idNo=?) AND (ID <> ?)';
        $arr = array();
        array_push($arr,trim(strtolower($data['idno'])));
        array_push($arr,trim($data['id']));
        $results = checkExistsMod($this->db->dbh,$sql,$arr);
        if ($results > 0) {
            return false;
        }
        else {
            return true;
        }
    }
    public function getMembers()
    {
       $this->db->query("SELECT m.ID,
                                ucase(m.memberName) as memberName,
                                m.contact,
                                IF(m.genderId=1,'MALE',IF(m.genderId=2,'FEMALE','NOT SPECIFIED')) AS gender,
                                ucase(d.districtName) as district,
                                ucase(p.positionName) as position
                        FROM  tblmember m left join tbldistricts d on m.districtId=d.ID
                              left join tblpositions p on m.positionId=p.ID
                        WHERE (m.memberStatus=1) AND (m.congregationId=:cid) AND (m.deleted=0)
                        ORDER BY memberName");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();                ;
    }
    public function getMember($id)
    {
        $this->db->query('SELECT * FROM tblmember WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function getMarriageStatus()
    {
        $this->db->query('SELECT ID,UCASE(maritalStatus) as maritalStatus FROM tblmaritaltatus');
        return $this->db->resultSet();
    }
    public function getDistricts()
    {
        $this->db->query('SELECT ID,UCASE(districtName) as districtName
                          FROM tbldistricts
                          WHERE (congregationId=:cong) AND (deleted=0)
                          ORDER BY districtName');
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getPositions()
    {
        $this->db->query('SELECT ID,UCASE(positionName) as positionName
                          FROM tblpositions
                          WHERE (congregationId=:cong)
                          ORDER BY positionName');
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getOccupations()
    {
        $this->db->query('SELECT UCASE(industry) AS industry FROM tblindustries ORDER BY specify,industry');
        return $this->db->resultSet();
    }
    public function genMemberNo()
    {
       $this->db->query('SELECT (IFNULL(MAX(RIGHT(memberId,4)),0) + 1) as new FROM tblmember
                         WHERE (congregationId=:cong)');
       $this->db->bind(':cong',$_SESSION['congId']);
       $result = $this->db->single();
       $added =str_pad($result->new,4,'0',STR_PAD_LEFT); 

       $this->db->query('SELECT prefix FROM tblcongregation WHERE (ID=:cong)');
       $this->db->bind(':cong',$_SESSION['congId']);
       $prefix = $this->db->single();
       $new = $prefix->prefix .'/'. $added;
       return $new;
    }
    public function getId()
    {
        $id ='';
        $this->db->query('SELECT ID FROM `tblmember` ORDER BY ID DESC LIMIT 1');
        $res = $this->db->single();
        if ($res->ID == 0) {
            $id = 1;
        }
        else{
            $id = $res->ID + 1;
        }
        return $id;
    }
    public function create($data)
    {
       $id = $this->getId();
       $mno =$this->genMemberNo();
       $this->db->query('INSERT INTO tblmember (ID,memberId,memberName,idNo,dob,genderId,contact,
                         maritalStatusId,marriageType,marriageDate,registrationDate,memberStatus
                         ,passedOn,baptised,baptisedDate,membershipStatus,confirmed,confirmedDate
                         ,commissioned,commissionedDate,districtId,positionId,occupation,other,email,residence
                         ,congregationId) VALUES(:id,:mid,:mname,:idno,:dob,:gender,:contact,
                         :marital,:mtype,:mdate,:regdate,:mstatus,:pass,:bap,:bdate,:ship,:conf,
                         :cdate,:comm,:comdate,:did,:pid,:occ,:other,:email,:res,:cong)');
        $this->db->bind(':id',$id);                 
        $this->db->bind(':mid',$mno);                 
        $this->db->bind(':mname',$data['name']);                 
        $this->db->bind(':idno',$data['idno']);                 
        $this->db->bind(':dob',$data['dob']);                 
        $this->db->bind(':gender',$data['gender']);                 
        $this->db->bind(':contact',$data['contact']);                 
        $this->db->bind(':marital',$data['maritalstatus']);                 
        $this->db->bind(':mtype',$data['marriagetype']);                 
        $this->db->bind(':mdate',$data['marriagedate']);                 
        $this->db->bind(':regdate',$data['regdate']);                 
        $this->db->bind(':mstatus',$data['status']);                 
        $this->db->bind(':pass',$data['passeddate']);                 
        $this->db->bind(':bap',$data['baptised']);                 
        $this->db->bind(':bdate',$data['bapitiseddate']);                 
        $this->db->bind(':ship',$data['membershipstatus']);                 
        $this->db->bind(':conf',$data['confirmed']);                 
        $this->db->bind(':cdate',$data['confirmeddate']);                 
        $this->db->bind(':comm',$data['commissioned']);                 
        $this->db->bind(':comdate',$data['commissioneddate']);                 
        $this->db->bind(':did',$data['district']);                 
        $this->db->bind(':pid',$data['position']);                 
        $this->db->bind(':occ',$data['occupation']);                 
        $this->db->bind(':other',$data['occupationother']);                 
        $this->db->bind(':email',$data['email']);                 
        $this->db->bind(':res',$data['residence']);                 
        $this->db->bind(':cong',$_SESSION['congId']);
        if ($this->db->execute()) {
            $act ='Create Member '.$data['name'];
            $this->createLog($this->db,$act);
            $contact = $data['contact'];
            $countryPrexix ='+254';
            $sb = substr($contact,1);
            $new = $countryPrexix . $sb;
            $encoded = encrypt($id);
            sendSms($new,strtoupper($data['name']),$encoded);
            return true;
        }         
        else{
            return false;
        }        
    }
    public function delete($data)
    {
        $this->db->query('UPDATE tblmember SET deleted=:del WHERE (ID=:id)');
        $this->db->bind(':del',$_SESSION['one']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act ='Updated Member '.$data['name'];
            $this->createLog($this->db,$act);
            return true;
        }
        else{
            return false;
        }  
    }
    public function update($data)
    {
       $this->db->query('UPDATE tblmember SET memberName=:mname,idNo=:idno,dob=:dob,genderId=:gender
                        ,contact=:contact,maritalStatusId=:marital,marriageType=:mtype,marriageDate=:mdate
                        ,registrationDate=:regdate,memberStatus=:mstatus,passedOn=:pass,baptised=:bap,
                        baptisedDate=:bdate,membershipStatus=:ship,confirmed=:conf,confirmedDate=:cdate
                        ,commissioned=:comm,commissionedDate=:comdate,districtId=:did,positionId=:pid,
                        occupation=:occ,other=:other,email=:email,residence=:res WHERE (ID=:id)');
        $this->db->bind(':mname',$data['name']);                 
        $this->db->bind(':idno',$data['idno']);                 
        $this->db->bind(':dob',$data['dob']);                 
        $this->db->bind(':gender',$data['gender']);                 
        $this->db->bind(':contact',$data['contact']);                 
        $this->db->bind(':marital',$data['maritalstatus']);                 
        $this->db->bind(':mtype',$data['marriagetype']);                 
        $this->db->bind(':mdate',$data['marriagedate']);                 
        $this->db->bind(':regdate',$data['regdate']);                 
        $this->db->bind(':mstatus',$data['status']);                 
        $this->db->bind(':pass',$data['passeddate']);                 
        $this->db->bind(':bap',$data['baptised']);                 
        $this->db->bind(':bdate',$data['bapitiseddate']);                 
        $this->db->bind(':ship',$data['membershipstatus']);                 
        $this->db->bind(':conf',$data['confirmed']);                 
        $this->db->bind(':cdate',$data['confirmeddate']);                 
        $this->db->bind(':comm',$data['commissioned']);                 
        $this->db->bind(':comdate',$data['commissioneddate']);                 
        $this->db->bind(':did',$data['district']);                 
        $this->db->bind(':pid',$data['position']);                 
        $this->db->bind(':occ',$data['occupation']);                 
        $this->db->bind(':other',$data['occupationother']);                 
        $this->db->bind(':email',$data['email']);                 
        $this->db->bind(':res',$data['residence']);                 
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act ='Updated Member '.$data['name'];
            $this->createLog($this->db,$act);
            return true;
        }         
        else{
            return false;
        }        
    }
    public function getMemberDistrict($data)
    {
        $this->db->query('SELECT d.ID,UCASE(districtName) as districtName
                          FROM tblmember m left join tbldistricts d on m.districtId=d.ID
                          WHERE m.ID=:id');
        $this->db->bind(':id',$data['member']);
        return $this->db->single();
    }
    public function changedistrict($data)
    {
        try {
            $today = date('Y-m-d');
            $this->db->dbh->beginTransaction();

            $this->db->query('INSERT INTO tbldistrictchange (memberId,fromId,toId,transferDate,congregationId)
                             VALUES(:mid,:fid,:tid,:tdate,:cid)');
            $this->db->bind(':mid',$data['member']);
            $this->db->bind(':fid',$data['old']);
            $this->db->bind(':tid',$data['new']);
            $this->db->bind(':tdate',$today);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();

            $this->db->query('UPDATE tblmember SET districtId=:did WHERE (ID=:id)');
            $this->db->bind(':did',$data['new']);
            $this->db->bind(':id',$data['member']);
            $this->db->execute();

            $act ='Changed '.$data['name'] . ' From ' .$data['oldname'] . ' To ' .$data['newname'];
            $this->createLog($this->db,$act);
            if ($this->db->dbh->commit()) {
               return true;
            }
            else{
                return false;
            }

        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            throw $e;
        }
    }
    public function getCongregations()
    {
        $this->db->query('SELECT ID,UCASE(CongregationName) AS CongregationName
                          FROM tblcongregation WHERE (deleted=0) AND (IsParish=0)');
        return $this->db->resultSet();
    }
    public function getMembersByCongregation($congregation)
    {
        $this->db->query('SELECT ID,UCASE(memberName) as memberName 
                          FROM   tblmember
                          WHERE  (memberStatus=1) AND (deleted=0) AND (congregationId=:cid)
                          ORDER BY memberName');
        $this->db->bind(':cid',$congregation);
        return $this->db->resultSet();
    }
    public function getRelationships()
    {
        $this->db->query('SELECT ID,UCASE(relationship) as relationship 
                          FROM   tblrelationship');
        return $this->db->resultSet();
    }
    public function getDistrictsByCongregation($congregation)
    {
        $this->db->query('SELECT ID,UCASE(districtName) as districtName 
                          FROM   tbldistricts
                          WHERE  (deleted=0) AND (congregationId=:cid)
                          ORDER BY districtName');
        $this->db->bind(':cid',$congregation);
        return $this->db->resultSet();
    }
    public function memberTransfer($data)
    {
        try {
           //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblmembertransfers (memberId,fromId,toId,transferDate,
                                         reason,fromDistrict,toDistrict)
                             VALUES(:mid,:fid,:tid,:tdate,:reason,:fdist,:tdist)');
            $this->db->bind(':mid',$data['member']);
            $this->db->bind(':fid',$data['congregationfrom']);
            $this->db->bind(':tid',$data['newcongregation']);
            $this->db->bind(':tdate',$data['date']);
            $this->db->bind(':reason',$data['reason']);
            $this->db->bind(':fdist',$data['district']);
            $this->db->bind(':tdist',$data['newdistrict']);
            $this->db->execute();
            //update tblmember
            $this->db->query('UPDATE tblmember SET congregationId = :cid,districtId=:did WHERE (ID=:id)');
            $this->db->bind(':cid',$data['newcongregation']);
            $this->db->bind(':did',$data['newdistrict']);
            $this->db->bind(':id',$data['member']);
            $this->db->execute();

            $act = 'Transfered '.$data['membername']. ' From ' .$data['currentname'] . ' To ' . $data['newname'];
            saveLog($this->db->dbh,$act);
            if ($this->db->dbh->commit()) {
               return true;
            }else{
                return false;
            }
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            } 
            throw $e;
        }
    }
    public function createfamily($data)
    {
        try {
            $this->db->dbh->beginTransaction();
            $sql = 'INSERT INTO tblmember_family (`type`,memberId,memberName,familyMemberId,relationshipId,congregationId) 
                    VALUES(?,?,?,?,?,?)';
            //details  
            for ($i=0; $i < count($data['details']); $i++) { 
                $mid = $data['details'][$i]['mid'];
                $rid = $data['details'][$i]['rid'];
                $type = $data['details'][$i]['type'];
                $name = $data['details'][$i]['name'];
                               
                $stmt = $this->db->dbh->prepare($sql);
                $stmt->execute([$type,$data['member'],$name,$mid,$rid,$_SESSION['congId']]);
            }    
            
            $act = 'Created Family Member For '.$data['membername'];
            saveLog($this->db->dbh,$act);
            if ($this->db->dbh->commit()) {
                return true;
            }
            else {
                return false;
            }
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            throw $e;
        }
    }
    public function checkfamily($member)
    {
        $this->db->query('SELECT COUNT(ID) 
                          FROM   tblmember_family
                          WHERE  (memberId = :mid) OR (familyMemberId = :mid)');
        $this->db->bind(':mid',$member);
        return $this->db->getValue();
    }
    public function getmembersbydistrict()
    {
        $sql = 'SELECT ID,ucase(memberName) As MemberName FROM tblmember WHERE (congregationId = ?) AND (memberStatus = 1)';
        return loadresultset($this->db->dbh,$sql,[(int)$_SESSION['congId']]);
    }
    public function getcontacts($members)
    {
        $contacts = [];
        foreach($members as $member){
            $contact = getdbvalue($this->db->dbh,'SELECT contact FROM tblmember WHERE (ID = ?)',[(int)$member]);
            array_push($contacts,$contact);
        }
        return $contacts;
    }
}
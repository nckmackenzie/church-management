<?php
class Plan
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
    public function index()
    {
        $this->db->query('CALL sp_get_plans(:id)');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function GetFiscalYears()
    {
        $this->db->query('SELECT ID,
                                 UCASE(yearName) as yearName
                          FROM   tblfiscalyears
                          WHERE  (closed=0) AND (deleted=0)');
        return $this->db->resultSet();
    }
    public function GetCurrentYear()
    {
        $this->db->query('SELECT ID FROM tblfiscalyears WHERE :gdate BETWEEN startDate AND endDate');
        $this->db->bind(':gdate',date('Y-m-d'));
        return $this->db->getValue();
    }
    public function GetActivities()
    {
        $this->db->query('SELECT ID,
                                 UCASE(ActivityName) As ActivityName
                          FROM   tblactivities 
                          WHERE (CongregationId=:id)');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function CheckActivityName($act)
    {
        $this->db->query('SELECT COUNT(ID) FROM tblactivities WHERE ActivityName=:aname AND CongregationId=:cid');
        $this->db->bind(':aname',trim($act));
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->getValue();
    }
    public function CreateActivity($act)
    {
        $this->db->query('INSERT INTO tblactivities (ActivityName,CongregationId) VALUES(:aname,:cid)');
        $this->db->bind(':aname',trim($act));
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->execute();
    }
    public function GetAccounts()
    {
        $this->db->query('SELECT ID,
                                 UCASE(accountType) as accountType 
                          FROM   tblaccounttypes
                          WHERE  (parentId <> 0) AND (deleted = 0)
                                 AND ((congregationId = 0) OR (congregationId=:cid))
                          ORDER BY accountType');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function GetGroups()
    {
        // $this->db->query('SELECT ID,
        //                          UCASE(groupName) AS categoryName 
        //                   FROM tblgroups 
        //                   WHERE (active=1) AND (deleted=0) AND (congregationId=:cid)
        //                   ORDER BY groupName');
        $this->db->query('SELECT ID,`Name` AS categoryName FROM tblparishlcc where `Name`=:lname
                          UNION ALL
                          SELECT ID,
                                 UCASE(groupName) AS categoryName
                                 FROM tblgroups 
                                 WHERE (active=1) AND (deleted=0) AND (congregationId=:cid)');
        $this->db->bind(':lname','LCC');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function GetCongregationAndGroups()
    {
        $this->db->query('SELECT ID,`Name` AS categoryName FROM tblparishlcc where `Name`=:lname

                          UNION ALL

                          SELECT ID,
                                 UCASE(CongregationName) AS categoryName 
                          FROM   tblcongregation 
                          WHERE  (IsParish=0) AND (deleted=0)
                          
                          UNION ALL

                          SELECT ID,
                                 UCASE(groupName) AS categoryName 
                          FROM   tblgroups 
                          WHERE  (active=1) AND (deleted=0) AND (congregationId=:cid)');
        
        $this->db->bind(':lname','Parish');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function CheckPlanExists($data)
    {
        $sql = 'SELECT COUNT(ID) FROM tblworkplan WHERE `Level` = ? AND ID <> ? AND FiscalYearId=?';
        $arr = array();
        
        array_push($arr,trim($data['level']));
        array_push($arr,trim($data['id']));
        array_push($arr,trim($data['year']));

        $results = checkExistsMod($this->db->dbh,$sql,$arr);
        if ($results > 0) {
            return false;
        }
        else {
            return true;
        }
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblworkplan (`Level`,FiscalYearId,WorkPlanName,Theme,MeetingDate,
                                                    Activty,Reason,EstimatedCost,FromDate,ToDate,
                                                    ImplementaionDate,AccountId,OfficeResponsible,Other,
                                                    Collaborator,Results,ActualCost,EvidenceOfActivity,
                                                    Remarks,`FileName`,WorkPlanStatus,CongregationId)
                          VALUES (:lev,:fid,:pname,:theme,:mdate,:act,:reason,:ecost,:fdate,:tdate,:idate,
                                  :aid,:office,:other,:collabo,:result,:acost,:evidence,:remarks,:filen,:wstate
                                  ,:cid)');
        $this->db->bind(':lev',$data['level']);
        $this->db->bind(':fid',$data['year']);
        $this->db->bind(':pname',$data['planname']);
        $this->db->bind(':theme',!empty($data['theme']) ? $data['theme'] : null);
        $this->db->bind(':mdate',!empty($data['meetingdate']) ? $data['meetingdate'] : null);
        $this->db->bind(':act',$data['activity']);
        $this->db->bind(':reason',$data['reason']);
        $this->db->bind(':ecost',$data['costestimate']);
        $this->db->bind(':fdate',$data['fromdate']);
        $this->db->bind(':tdate',$data['todate']);
        $this->db->bind(':idate',!empty($data['actualdate']) ? $data['actualdate'] : null);
        $this->db->bind(':aid',!empty($data['account']) ? $data['account'] : null);
        $this->db->bind(':office',!empty($data['office']) ? $data['office'] : null);
        $this->db->bind(':other',!empty($data['officeother']) ? $data['officeother'] : null);
        $this->db->bind(':collabo',!empty($data['collaboratorName']) ? $data['collaboratorName'] : null);
        $this->db->bind(':result',!empty($data['results']) ? $data['results'] : null);
        $this->db->bind(':acost',!empty($data['actualcost']) ? $data['actualcost'] : null);
        $this->db->bind(':evidence',!empty($data['evidence']) ? $data['evidence'] : null);
        $this->db->bind(':remarks',!empty($data['remarks']) ? $data['remarks'] : null);
        $this->db->bind(':filen',!empty($data['filename']) ? $data['filename'] : null);
        $this->db->bind(':wstate',$data['status']);
        $this->db->bind(':cid',$_SESSION['congId']);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
    public function GetPlan($id)
    {
        $this->db->query('SELECT * FROM tblworkplan WHERE (ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblworkplan SET `Level`=:lev,FiscalYearId=:fid,WorkPlanName=:pname,Theme=:theme,
                                                  MeetingDate=:mdate,Activty=:act,Reason=:reason,EstimatedCost=:ecost,
                                                  FromDate=:fdate,ToDate=:tdate,ImplementaionDate=:idate,AccountId=:aid,
                                                  OfficeResponsible=:office,Other=:other,Collaborator=:collabo,Results=:result,
                                                  ActualCost=:acost,EvidenceOfActivity=:evidence,
                                                  `FileName`=:filen,Remarks=:remarks,WorkPlanStatus=:wstatus
                          WHERE (ID = :id)');

        $this->db->bind(':lev',$data['level']);
        $this->db->bind(':fid',$data['year']);
        $this->db->bind(':pname',$data['planname']);
        $this->db->bind(':theme',!empty($data['theme']) ? $data['theme'] : null);
        $this->db->bind(':mdate',!empty($data['meetingdate']) ? $data['meetingdate'] : null);
        $this->db->bind(':act',$data['activity']);
        $this->db->bind(':reason',$data['reason']);
        $this->db->bind(':ecost',$data['costestimate']);
        $this->db->bind(':fdate',$data['fromdate']);
        $this->db->bind(':tdate',$data['todate']);
        $this->db->bind(':idate',!empty($data['actualdate']) ? $data['actualdate'] : null);
        $this->db->bind(':aid',!empty($data['account']) ? $data['account'] : null);
        $this->db->bind(':office',!empty($data['office']) ? $data['office'] : null);
        $this->db->bind(':other',!empty($data['officeother']) ? $data['officeother'] : null);
        $this->db->bind(':collabo',!empty($data['collaboratorName']) ? $data['collaboratorName'] : null);
        $this->db->bind(':result',!empty($data['results']) ? $data['results'] : null);
        $this->db->bind(':acost',!empty($data['actualcost']) ? $data['actualcost'] : null);
        $this->db->bind(':evidence',!empty($data['evidence']) ? $data['evidence'] : null);
        $this->db->bind(':filen',!empty($data['filename']) ? $data['filename'] : null);
        $this->db->bind(':remarks',!empty($data['remarks']) ? $data['remarks'] : null);
        $this->db->bind(':wstatus',$data['status']);
        $this->db->bind(':id',$data['id']);

        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
    public function delete($id)
    {
        $this->db->query('DELETE FROM tblworkplan WHERE ID=:id');
        $this->db->bind(':id',$id);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
}
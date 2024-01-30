<?php 
class User {
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    function create($data)
    {
        $this->db->query('INSERT INTO tblusers (UserID,UserName,UsertypeId,`Password`,Active,contact,districtId,CongregationId) VALUES(:usid,:uname,:utype,:pass,:act,:contact,:district,:cong)');
        $this->db->bind(':usid',$data['userid']);
        $this->db->bind(':uname',$data['username']);
        $this->db->bind(':utype',$data['usertype']);
        $this->db->bind(':pass',password_hash($data['password'],PASSWORD_DEFAULT));
        $this->db->bind(':act',$data['active']);
        $this->db->bind(':contact',$data['contact']);
        $this->db->bind(':district',$data['district']);
        $this->db->bind(':cong',$_SESSION['congId']);
        //execute
        if ($this->db->execute()) {
            return true;
        }
        else{
            return false;
        }
    }
    public function update($data)
    {
       
        $this->db->query('UPDATE tblusers SET UserName=:uname,UsertypeId=:utype,Active=:act,
                                 contact=:contact,districtId=:district
                          WHERE  (ID=:id)');
        $this->db->bind(':uname',$data['username']);
        $this->db->bind(':utype',$data['usertype']);
        $this->db->bind(':act',$data['active']);
        $this->db->bind(':contact',$data['contact']);
        $this->db->bind(':district',$data['district']);
        $this->db->bind(':id',$data['id']);
        //execute
        if ($this->db->execute()) {
            return true;
        }
        else{
            return false;
        }
    }
    public function CreateUpdate($data)
    {
        if(!$data['isedit']){
            return $this->create($data);
        }else{
            return $this->update($data);
        }
    }
    public function CheckUserIdExists($userid,$id)
    {
        $count = getdbvalue($this->db->dbh,'SELECT count(*) 
                                            FROM tblusers 
                                            WHERE (UserID=?) AND (CongregationId=?) AND (ID <> ?)',[$userid,$_SESSION['congId'],$id]);
        if($count > 0) return true;
        return false;
    }
    public function checksuperuser($userid)
    {
        $this->db->query('SELECT superUser FROM tblusers WHERE (UserID=:id)');
        $this->db->bind(':id',$userid);
        if($this->db->getValue() == 1){
            return true;
        }else{
            return false;
        }
    }
    public function login($userid,$password,$congregation)
    {
        $this->db->query('SELECT superUser FROM tblusers WHERE (UserID=:id)');
        $this->db->bind(':id',$userid);
        $isSuperuser = $this->db->getValue();
        if($isSuperuser == 1){
            $this->db->query('SELECT U.ID,
                                 U.UserName,
                                 U.Password,
                                 C.IsParish,
                                 U.UsertypeId,
                                 C.CongregationName,
                                 U.CongregationId 
                          FROM tblusers U INNER JOIN tblcongregation C ON
                          U.CongregationId=C.ID       
                          WHERE UserID=:usid');
            $this->db->bind(':usid',$userid);
        }else{
            $this->db->query('SELECT U.ID,
                                     U.UserName,
                                     U.Password,
                                     C.IsParish,
                                     U.UsertypeId,
                                     C.CongregationName,
                                     U.CongregationId 
                              FROM tblusers U INNER JOIN tblcongregation C ON
                              U.CongregationId=C.ID       
                              WHERE UserID=:usid AND CongregationId=:cid');
            $this->db->bind(':usid',$userid);
            $this->db->bind(':cid',$congregation);
        }
        $row = $this->db->single();
        $hashed_password = $row->Password;
        if (password_verify($password,$hashed_password)) {
            return $row;
        }
        else{
            return false;
        }
    }
    public function checkIsParish($cong)
    {
        $this->db->query('SELECT IsParish FROM tblcongregation WHERE (ID=:id)');
        $this->db->bind(':id',$cong);
        return $this->db->getValue();
    }
    public function checkUserAvailability($data)
    {
        $this->db->query('SELECT superUser FROM tblusers WHERE (UserID=:id)');
        $this->db->bind(':id',$data['userid']);
        $isSuperuser = $this->db->getValue();
        
        if($isSuperuser == 0){
            $this->db->query('SELECT COUNT(ID) as result FROM tblusers WHERE UserID=:usid
                                 AND CongregationId=:cid AND Active=1');
            $this->db->bind(':usid',$data['userid']);
            $this->db->bind(':cid',$data['congregation']);
            $result = $this->db->single();
            if ($result->result > 0) {
                return true;
            }
            else{
                return false;
            }
        }
        else{
            return true;
        }
        
    }
    public function getCongregation()
    {
        $this->db->query('SELECT ID,UCASE(CongregationName) AS CongregationName FROM tblcongregation
                          WHERE (deleted=0)');
        return $this->db->resultSet();
    }
    public function getDistricts()
    {
        $this->db->query('SELECT ID,UCASE(districtName) AS districtName 
                          FROM   tbldistricts
                          WHERE (deleted=0) AND (congregationId=:cid)');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function loadUsers()
    {
        $this->db->query('SELECT * FROM vw_users WHERE (CongregationId=:cid) AND (status=:active)');
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':active','Active');
        return $this->db->resultSet();
    }
    public function passwordMatch($pass)
    {
        $this->db->query('SELECT `Password` FROM tblusers WHERE (ID=:id)');
        $this->db->bind(':id',$_SESSION['userId']);
        $password = $this->db->single();
        if (password_verify($pass,$password->Password)) {
            return true;
        }
        else{
            return false;
        }
    }
    public function password($data)
    {
        $hashed =  password_hash($data['new'],PASSWORD_DEFAULT);
        $this->db->query('UPDATE tblusers SET `Password`=:pass WHERE (ID=:id)');
        $this->db->bind(':pass',$hashed);
        $this->db->bind(':id',$_SESSION['userId']);
        if ($this->db->execute()) {
            return true;
        }
        else{
            return false;
        }
    }
    public function getUsers()
    {
       $this->db->query('SELECT ID,UCASE(UserName) as UserName FROM tblusers
                         WHERE (Active=1) AND (congregationId=:cid)
                         ORDER BY UserName');
       $this->db->bind(':cid',$_SESSION['congId']);
       return $this->db->resultSet();
    }
    public function activityresult($data)
    {
        $this->db->query("SELECT activity,
                                 DATE_FORMAT(activityDate, '%d/%m/%Y') as activityDate
                          FROM tbllogs 
                          WHERE (userId=:id) AND activityDate BETWEEN :startd AND :endd
                          ORDER BY activityDate DESC");
        $this->db->bind(':id',$data['user']);
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        $logs = $this->db->resultSet();
        $output = '
            <table class="table table-bordered table-sm table-striped" id="table">
                <thead class="bg-navy">
                    <tr>
                        <th>Activity</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>';
                    foreach ($logs as $log) {
                        $output .= '
                            <tr>
                                <td>'.strtoupper($log->activity).'</td>
                                <td>'.$log->activityDate.'</td>
                            </tr>
                        ';
                    }
        $output .= '
                </tbody>
            </table>        
        ';           
        return $output;
    }
    public function checkUserByPhone($phone)
    {
        $this->db->query('SELECT COUNT(ID) AS recordCount FROM tblusers WHERE contact=:cont');
        $this->db->bind(':cont',$phone);
        $count = $this->db->getValue();
        if ($count > 0) {
            return true;
        }else{
            return false;
        }
    }
    public function resendCredentials($data)
    {
        $this->db->query('SELECT ID FROM tblusers WHERE contact=:cont');
        $this->db->bind(':cont',$data['phone']);
        $id = $this->db->getValue();
        $this->db->query('UPDATE tblusers SET `Password`=:pass WHERE (ID=:id)');
        $this->db->bind(':pass',$data['password']);
        $this->db->bind(':id',$id);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
    public function getUser($id)
    {
        $this->db->query('SELECT * FROM tblusers WHERE (ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
   
    public function resetCredentials($data)
    {
        $this->db->query('UPDATE tblusers SET `Password`=:pass WHERE (ID=:id)');
        $this->db->bind(':pass',$data['password']);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
    public function GetNonAdmins()
    {
        $this->db->query('SELECT ID,
                                 UCASE(UserName) as UserName
                          FROM   tblusers
                          WHERE  (Active = 1) AND (UsertypeId > 2) AND (CongregationId = :cid)
                          ORDER BY UserName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function GetForms()
    {
        $this->db->query('SELECT ID,
                                 FormName,
                                 `Path` AS formpath
                          FROM   tblforms
                          WHERE  (ParishNav = :pnav)');
        $this->db->bind(':pnav',$_SESSION['isParish']);
        return $this->db->resultSet();
    }
    public function rights($data)
    {
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            //delete existing rights
            $this->db->query('DELETE FROM tbluserrights WHERE (UserId = :user)');
            $this->db->bind(':user',$data['user']);
            $this->db->execute();
            //reenter selected rights
            for ($i=0; $i < count($data['rights']); $i++) {
                if ((int)$data['rights'][$i]->access === 1) {
                    $this->db->query('INSERT INTO tbluserrights (UserId,FormId,access) VALUES(:user,:fid,:access)');
                    $this->db->bind(':user',$data['user']);
                    $this->db->bind(':fid',$data['rights'][$i]->formId);
                    $this->db->bind(':access',$data['rights'][$i]->access);
                    $this->db->execute();
                }
            }
            //commit
            if ($this->db->dbh->commit()) {
                return true;
            }else {
                return false;
            }

        } catch (\Throwable $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            error_log($e->getMessage(),0);
            return false;
        }
    }
    public function GetRights($id)
    {
        // $this->db->query('CALL sp_get_rights(:userid)');
        // $this->db->bind(':userid',decryptId($id));
        $sql = 'SELECT 	u.FormId as ID,
                        f.FormName,
                        f.Path,
                        u.access as access
                FROM   `tbluserrights` u inner join tblforms f on u.FormId = f.ID
                WHERE   u.UserId = :userid ';
        
        $sql .='UNION ALL ';

        $sql .= 'SELECT  ID,
                         FormName,
                         `Path`,
                         0 as access
                 FROM    tblforms
                 WHERE   (ParishNav = :pnav) AND ID NOT IN (SELECT FormId FROM tbluserrights WHERE (UserId = :userid))';
        $sql .= ' ORDER BY FormName';
        $this->db->query($sql);
        $this->db->bind(':pnav',$_SESSION['isParish']);
        $this->db->bind(':userid',$id);
        return $this->db->resultSet();
    }
    public function CheckRightsAssigned($id)
    {
        $this->db->query('SELECT COUNT(ID) FROM tbluserrights WHERE (UserId = :id)');
        $this->db->bind(':id',$id);
        if ($this->db->getValue() > 0) {
            return true;
        }else{
            return false;
        }
    }
    public function clonerights($data)
    {
        $this->db->query('CALL sp_menu_pairing(:user1,:user2)');
        $this->db->bind(':user1',$data['user1']);
        $this->db->bind(':user2',$data['user2']);
        if ($this->db->execute()) {
            return true;
        }else {
            return false;
        }
    }
    public function delete($id)
    {
        $rightscount = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tbluserrights WHERE UserId = ?',[$id]);
        $logscount = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tbllogs WHERE UserId = ?',[$id]);

        if((int)$rightscount !== 0 || (int)$logscount !== 0) {
            return false;
        }

        $this->db->query("DELETE FROM tblusers WHERE ID = :id");
        $this->db->bind(':id',(int)$id);
        if($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }
}
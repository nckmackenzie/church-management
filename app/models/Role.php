<?php
class Role
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetRoles()
    {
        return loadresultset($this->db->dbh,'SELECT * FROM tblroles WHERE Deleted=0',[]);
    }

    public function NameExists($role)
    {
        $count = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblroles WHERE RoleName=? AND Deleted=0',[$role]);
        if((int)$count === 0){
            return false;
        }
        return true;
    }

    public function AddRole($role)
    {
        $this->db->query('INSERT INTO tblroles (RoleName) VALUES(:role)');
        $this->db->bind(':role',$role);
        if(!$this->db->execute()){
            return false;
        };
    }

    public function GetRights($id)
    {
        $sql = 'SELECT 	r.FormId as ID,
                        f.FormName,
                        f.Path,
                        1 as access
                FROM   `tblrolerights` r inner join tblforms f on r.FormId = f.ID
                WHERE   r.RoleId = :userid ';
        
        $sql .='UNION ALL ';

        $sql .= 'SELECT  ID,
                         FormName,
                         `Path`,
                         0 as access
                 FROM    tblforms
                 WHERE   (ParishNav = :pnav) AND ID NOT IN (SELECT FormId FROM tblrolerights WHERE (RoleId = :userid))';
        $sql .= ' ORDER BY FormName';
        $this->db->query($sql);
        $this->db->bind(':pnav',0);
        $this->db->bind(':userid',$id);
        return $this->db->resultSet();
    }

    public function rights($data)
    {
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            //delete existing rights
            $this->db->query('DELETE FROM tblrolerights WHERE (RoleId = :role)');
            $this->db->bind(':role',$data['role']);
            $this->db->execute();
            //reenter selected rights
            for ($i=0; $i < count($data['rights']); $i++) {
                if ((int)$data['rights'][$i]->access === 1) {
                    $this->db->query('INSERT INTO tblrolerights (RoleId,FormId) VALUES(:role,:fid)');
                    $this->db->bind(':role',$data['role']);
                    $this->db->bind(':fid',$data['rights'][$i]->formId);
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
}
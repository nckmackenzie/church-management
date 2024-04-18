<?php
class Groupbudget {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function CheckRights($form)
    {
        return checkuserrights($this->db->dbh,$_SESSION['userId'],$form);
    }

    public function index()
    {
        $this->db->query('SELECT DISTINCT h.ID,
                                          ucase(f.yearName) as yearName,
                                          ucase(g.groupName) as groupName,
                                          FORMAT((SELECT IFNULL(SUM(amount),0) as amount from tblgroupbudget_details where ID=h.ID),2) AS BudgetAmount
                          FROM tblgroupbudget_header h INNER join tblgroupbudget_details d on 
                                          h.ID=d.ID inner join tblfiscalyears f on h.fiscalYearId=f.ID
                                          inner join tblgroups g on h.groupId = g.ID
                          WHERE (h.congregationId=:id)');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function CheckYearClosed($id)
    {
        return yearprotection($this->db->dbh,$id);
    }

    public function getFiscalYears()
    {
        $this->db->query('SELECT * FROM tblfiscalyears WHERE (closed=0) AND (deleted=0)');
        return $this->db->resultSet();
    }

    public function getGroups()
    {
        $this->db->query('SELECT ID,
                                 UCASE(groupName) as groupName
                          FROM   tblgroups
                          WHERE  (active=1) AND (deleted=0) AND (congregationId=:cid)
                          ORDER BY groupName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function getAccounts()
    {
        $this->db->query('SELECT ID,
                                 UCASE(accountType) AS accountType
                          FROM tblaccounttypes 
                          WHERE (isSubCategory = 1) AND (deleted=0)
                          ORDER BY accountType');
        return $this->db->resultSet();
    }

    public function CheckYear($data)
    {
        $sql = 'SELECT COUNT(*) FROM tblgroupbudget_header WHERE (fiscalYearId = ?) AND (groupId = ?) AND (ID <> ?)';
        return getdbvalue($this->db->dbh,$sql,[$data['year'],$data['group'],$data['id']]);
    }

    public function Save($data)
    {
        try {
            $this->db->dbh->beginTransaction();
        
            $this->db->query('INSERT INTO tblgroupbudget_header (groupId,fiscalYearId,congregationId)
                              VALUES(:gid,:yid,:cid)');
            $this->db->bind(':gid',$data['group']);
            $this->db->bind(':yid',!empty($data['year']) ? $data['year'] : null);
            $this->db->bind(':cid',(int)$_SESSION['congId']);;
            $this->db->execute();
            $id = $this->db->dbh->lastInsertId();

            for($i = 0; $i < count($data['accountsid']); $i++){
                $this->db->query('INSERT INTO tblgroupbudget_details (ID,accountId,amount) 
                                  VALUES(:hid,:aid,:amount)');
                $this->db->bind(':hid',$id);
                $this->db->bind(':aid',$data['accountsid'][$i]);
                $this->db->bind(':amount', !empty($data['amounts'][$i]) ? floatval($data['amounts'][$i]) : 0);
                $this->db->execute();
            }

            if(!$this->db->dbh->commit()){
                return false;
            }else{
                return true;
            }

        } catch (Exception $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            throw $e;
        }
    }

    public function Update($data)
    {
        try {
            $this->db->dbh->beginTransaction();
            
            $this->db->query('DELETE FROM tblgroupbudget_details WHERE (ID = :id)');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();

            for($i = 0; $i < count($data['accountsid']); $i++){
                $this->db->query('INSERT INTO tblgroupbudget_details (ID,accountId,amount) 
                                  VALUES(:hid,:aid,:amount)');
                $this->db->bind(':hid',$data['id']);
                $this->db->bind(':aid',$data['accountsid'][$i]);
                $this->db->bind(':amount',$data['amounts'][$i]);
                $this->db->execute();
            }

            if(!$this->db->dbh->commit()){
                return false;
            }else{
                return true;
            }

        } catch (Exception $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            throw $e;
        }
    }

    public function CreateUpdate($data)
    {
        if(!$data['isedit']){
            return $this->Save($data);
        }else{
            return $this->Update($data);
        }
    }

    public function BudgetHeader($id)
    {
        $this->db->query('SELECT * FROM   tblgroupbudget_header 
                          WHERE  (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }

    public function BudgetDetails($id)
    {
        $this->db->query('SELECT d.tid,
                                 ucase(a.accountType) as accountType,
                                 d.amount
                          FROM   tblgroupbudget_details d inner join tblaccounttypes a on d.accountId=a.ID
                          WHERE  (d.ID=:id)
                          ORDER BY accountType');
        $this->db->bind(':id',$id);
        return $this->db->resultSet();
    }
    
    public function Delete($data)
    {
        try {
            $this->db->dbh->beginTransaction();
            $this->db->query('DELETE FROM tblgroupbudget_details WHERE (ID=:id)');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            //header
            $this->db->query('DELETE FROM tblgroupbudget_header WHERE (ID=:id)');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            //log
            $act = 'Deleted Budget For '.$data['year'] . ' For '.$data['groupname'];
            if ($this->db->dbh->commit()) {
                return true;
            }
            else{
                return false;
            }
        } catch (\Exception $th) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            throw $th;
        }
    }
}
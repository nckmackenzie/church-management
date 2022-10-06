<?php 
class Churchbudget {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function CheckRights($form)
    {
        return checkuserrights($this->db->dbh,$_SESSION['userId'],$form);
    }

    public function CheckYearClosed($id)
    {
        return yearprotection($this->db->dbh,$id);
    }

    public function index()
    {
        $this->db->query('SELECT DISTINCT h.ID,
                                          ucase(f.yearName) as yearName,
                                          FORMAT((SELECT IFNULL(SUM(amount),0) as amount from tblchurchbudget_details where ID=h.ID),2) AS BudgetAmount
                          FROM tblchurchbudget_header h INNER join tblchurchbudget_details d on 
                                          h.ID=d.ID inner join tblfiscalyears f on h.yearId=f.ID
                          WHERE (h.congregationId=:id)');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function getFiscalYears()
    {
        $this->db->query('SELECT * FROM tblfiscalyears WHERE (closed=0) AND (deleted=0)');
        return $this->db->resultSet();
    }

    public function getAccounts()
    {
        $this->db->query('SELECT * FROM tblaccounttypes 
                          WHERE (accountTypeId = 2) AND (deleted=0) AND (isSubCategory =1)
                          ORDER BY accountType');
        return $this->db->resultSet();
    }

    public function CheckYear($data)
    {
        $sql = 'SELECT COUNT(*) FROM tblchurchbudget_header WHERE (yearId = ?) AND (ID <> ?)';
        return getdbvalue($this->db->dbh,$sql,[$data['year'],$data['id']]);
    }

    function getnewid(){
        $sql = 'SELECT COUNT(*) FROM tblchurchbudget_header';
        $results = getdbvalue($this->db->dbh,$sql,[]);
        if((int)$results === 0){
            return 1;
        }
        $sql1 = 'SELECT ID FROM tblchurchbudget_header ORDER BY ID DESC LIMIT 1';
        return getdbvalue($this->db->dbh,$sql1,[]) + 1;
    }

    public function Save($data)
    {
        try {
            $this->db->dbh->beginTransaction();
            $id = $this->getnewid();

            $this->db->query('INSERT INTO tblchurchbudget_header (ID,budgetName,yearId,congregationId)
                              VALUES(:id,:bname,:yid,:cid)');
            $this->db->bind(':id',$id);
            $this->db->bind(':bname', !empty($data['yeartext']) ? strtolower($data['yeartext']) : null);
            $this->db->bind(':yid',!empty($data['year']) ? $data['year'] : null);
            $this->db->bind(':cid',(int)$_SESSION['congId']);;
            $this->db->execute();

            for($i = 0; $i < count($data['accountsid']); $i++){
                $this->db->query('INSERT INTO tblchurchbudget_details (ID,accountId,amount) 
                                  VALUES(:hid,:aid,:amount)');
                $this->db->bind(':hid',$id);
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
                $this->dbh->rollback();
            }
            throw $e;
        }
    }

    public function Update($data)
    {
        try {
            $this->db->dbh->beginTransaction();
            
            $this->db->query('DELETE FROM tblchurchbudget_details WHERE (ID = :id)');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();

            for($i = 0; $i < count($data['accountsid']); $i++){
                $this->db->query('INSERT INTO tblchurchbudget_details (ID,accountId,amount) 
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
        $this->db->query('SELECT * FROM tblchurchbudget_header WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }

    public function BudgetDetails($id)
    {
        $this->db->query('SELECT d.tid,
                                 ucase(a.accountType) as accountType,
                                 d.amount
                          FROM   tblchurchbudget_details d inner join tblaccounttypes a on d.accountId=a.ID
                          WHERE  (d.ID=:id)
                          ORDER BY accountType');
        $this->db->bind(':id',$id);
        return $this->db->resultSet();
    }
    

    public function delete($data)
    {
        try {
            $this->db->dbh->beginTransaction();
            $this->db->query('DELETE FROM tblchurchbudget_details WHERE (ID=:id)');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            //header
            $this->db->query('DELETE FROM tblchurchbudget_header WHERE (ID=:id)');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            //log
            $act = 'Deleted Budget For '.$data['year'];
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
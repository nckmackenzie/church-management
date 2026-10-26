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

    public function getAccounts(array $legacyIds = [])
    {
        $cong = (int)$_SESSION['congId'];
        $rows = loadresultset($this->db->dbh,'SELECT ID,UCASE(accountType) accountType FROM tblaccounttypes
            WHERE deleted=0 AND active=1 AND isBank=0 AND accountTypeId IN (1,2)
              AND congregationId IN (0,?) ORDER BY accountType',[$cong]);
        $valid = array_flip(Ledger::filterPostingAccounts($this->db->dbh,$cong,array_map(fn($r)=>(int)$r->ID,$rows)));
        $out = array_values(array_filter($rows,fn($r)=>isset($valid[(int)$r->ID])));
        $present = array_flip(array_map(fn($r)=>(int)$r->ID,$out));
        foreach(array_unique(array_map('intval',$legacyIds)) as $id){
            if($id && !isset($present[$id])){
                $r = loadsingleset($this->db->dbh,'SELECT ID,UCASE(accountType) accountType FROM tblaccounttypes WHERE ID=?',[$id]);
                if($r){ $out[]=$r; $present[$id]=true; }
            }
        }
        usort($out,fn($a,$b)=>strcmp($a->accountType,$b->accountType));
        return $out;
    }

    /** True when a budget amount is not a plain non-negative decimal with at most two decimal places (the column is DECIMAL(18,2)). */
    public function amountsProblem(array $amounts)
    {
        foreach($amounts as $a){
            $a = trim((string)$a);
            if($a === ''){ continue; }
            $c = Ledger::exactCents($a);
            if($c === null || $c < 0){ return true; }
        }
        return false;
    }

    /** The amount as the exact decimal text stored in DECIMAL(18,2); a blank is 0.00. */
    private function amountText($a)
    {
        $a = trim((string)$a);
        return $a === '' ? '0.00' : FinancialReport::decimal(Ledger::exactCents($a) ?? 0);
    }
    public function accountProblem(array $ids, array $legacyIds = [])
    {
        $valid = array_flip(array_map(fn($r)=>(int)$r->ID,$this->getAccounts()));
        $legacy = array_count_values(array_map('intval',$legacyIds));
        foreach($ids as $id){
            $id=(int)$id;
            if(isset($valid[$id])){continue;}
            if(isset($legacy[$id]) && $legacy[$id]>0){$legacy[$id]--;continue;}
            if(!$id || !isset($valid[$id])){
                return 'Select an active income or expense posting account for this congregation';
            }
        }
        return null;
    }

    public function CheckYear($data)
    {
        $sql = 'SELECT COUNT(*) FROM tblchurchbudget_header WHERE (yearId = ?) AND (ID <> ?) AND (congregationId=?)';
        return getdbvalue($this->db->dbh,$sql,[$data['year'],$data['id'],$_SESSION['congId']]);
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
        if($this->accountProblem((array)$data['accountsid'])){ return false; }
        if($this->amountsProblem((array)($data['amounts'] ?? []))){ return false; }
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
                $this->db->bind(':amount',$this->amountText($data['amounts'][$i] ?? null));
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
        $header = loadsingleset($this->db->dbh,'SELECT ID FROM tblchurchbudget_header WHERE ID=? AND congregationId=?',[(int)$data['id'],(int)$_SESSION['congId']]);
        if(!$header){ return false; }
        $legacy = array_map('intval',array_column(loadresultset($this->db->dbh,'SELECT accountId FROM tblchurchbudget_details WHERE ID=?',[(int)$data['id']]),'accountId'));
        if($this->accountProblem((array)$data['accountsid'],$legacy)){ return false; }
        if($this->amountsProblem((array)($data['amounts'] ?? []))){ return false; }
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
                $this->db->bind(':amount',$this->amountText($data['amounts'][$i] ?? null));
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
        $this->db->query('SELECT d.accountId,
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

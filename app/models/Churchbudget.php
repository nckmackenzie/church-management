<?php 
class Churchbudget {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
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
                          WHERE (accountTypeId < 3) AND (deleted=0)
                          ORDER BY accountTypeId,accountType');
        return $this->db->resultSet();
    }
    public function create($year,$file)
    {
        $this->db->query('INSERT INTO tblchurchbudget_header (ID,yearId,congregationId)
                          VALUES(:id,:yid,:cid)');
        $this->db->bind(':id',5);
        $this->db->bind(':yid',2);
        $this->db->bind(':cid',1);
        $this->db->execute();
        $filename = explode('.',$file);
            if (end($filename) == 'csv') {
                $handle = fopen($file['tmp_name'],"r");
                fgetcsv($handle);
                while ($data = fgetcsv($handle)) {
                   $this->db->query('INSERT INTO tblchurchbudget_details (ID,accountId,amount)
                                      VALUES(:id,:aid,:amount)');
                    $this->db->bind(':id',5);
                    $this->db->bind(':aid',$data[0]);
                    $this->db->bind(':amount',!empty($data[2]) ? $data[2] : 0);
                    if ($this->db->execute()) {
                        return true;
                    }
                    else{
                        return false;
                    }
                }
                fclose($handle);
            }
    }
    public function budgetHeader($id)
    {
        $this->db->query('SELECT h.ID,
                                 ucase(f.yearName) as yearName,
                                 h.congregationId
                          FROM   tblchurchbudget_header h inner join tblfiscalyears f on h.yearId=f.ID
                          WHERE  (h.ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
    public function budgetDetails($id)
    {
        $this->db->query('SELECT d.tid,
                                 ucase(a.accountType) as accountType,
                                 d.amount
                          FROM   tblchurchbudget_details d inner join tblaccounttypes a on d.accountId=a.ID
                          WHERE  (d.ID=:id)
                          ORDER BY accountType');
        $this->db->bind(':id',decryptId($id));
        return $this->db->resultSet();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblchurchbudget_details SET amount=:amount WHERE (tid = :id)');
        $this->db->bind(':amount',!empty($data['amount']) ? $data['amount'] : NULL);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            return true;
        }else {
            return false;
        }
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
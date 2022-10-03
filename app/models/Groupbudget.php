<?php
class Groupbudget {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
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
                          WHERE (accountTypeId < 3) AND (deleted=0)
                          ORDER BY accountTypeId,accountType');
        return $this->db->resultSet();
    }
    public function budgetHeader($id)
    {
        $this->db->query('SELECT h.ID,
                                 ucase(f.yearName) as yearName,
                                 ucase(g.groupName) as groupName,
                                 h.congregationId
                          FROM   tblgroupbudget_header h inner join tblfiscalyears f on h.fiscalYearId=f.ID
                                 inner join tblgroups g on h.groupId = g.ID
                          WHERE  (h.ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
    public function budgetDetails($id)
    {
        $this->db->query('SELECT d.tid,
                                 ucase(a.accountType) as accountType,
                                 d.amount
                          FROM   tblgroupbudget_details d inner join tblaccounttypes a on d.accountId=a.ID
                          WHERE  (d.ID=:id)
                          ORDER BY accountType');
        $this->db->bind(':id',decryptId($id));
        return $this->db->resultSet();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblgroupbudget_details SET amount=:amount WHERE (tid = :id)');
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
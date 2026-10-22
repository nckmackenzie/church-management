<?php
class Year{
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
       $this->db->query('SELECT * FROM tblfiscalyears WHERE (deleted=0)');
       $years = $this->db->resultSet();
       // why a year cannot be deleted (empty = it can): the screen shows the Delete button only for those
       foreach ($years as $year) {
           $year->deleteBlockers = $this->deleteBlockers($year->ID);
       }
       return $years;
    }
    public function checkExists($data)
    {
        $sql = 'SELECT COUNT(ID) FROM tblfiscalyears WHERE yearName = ? AND ID <> ? AND deleted=0';
        $arr = array();
        
        array_push($arr,trim(strtolower($data['yearname'])));
        array_push($arr,trim($data['id']));
        $results = checkExistsMod($this->db->dbh,$sql,$arr);
        if ($results > 0) {
            return false;
        }
        else {
            return true;
        }
    }
    /** True when the period $start..$end overlaps no existing year, deleted years included. */
    public function checkYearConflict($start,$end)
    {
        $count = getdbvalue($this->db->dbh,'SELECT COUNT(ID) FROM tblfiscalyears WHERE startDate <= ? AND endDate >= ?',[$end,$start]);
        return (int)$count === 0;
    }
    public function create($data)
    {
        $this->db->query('INSERT INTO tblfiscalyears (yearName,startDate,endDate,createdDate,createdBy)
                          VALUES(:yname,:sdate,:edate,:cdate,:cby)');
        $this->db->bind(':yname',strtolower($data['yearname']));
        $this->db->bind(':sdate',$data['startdate']);
        $this->db->bind(':edate',$data['enddate']);
        $this->db->bind(':cdate',date('Y-m-d'));
        $this->db->bind(':cby',$_SESSION['userId']);
        if ($this->db->execute()) {
            $act = 'Created Financial Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function getYear($id)
    {
        $this->db->query('SELECT * FROM tblfiscalyears WHERE (ID=:id)');
        $this->db->bind(':id',decryptId(trim($id)));
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblfiscalyears SET yearName=:yname
                          WHERE (ID=:id)');
        $this->db->bind(':yname',strtolower($data['yearname']));
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Updated Financial Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function close($data)
    {
        $this->db->query('UPDATE tblfiscalyears SET closed=1,closedDate=:cdate
                          WHERE (ID=:id)');
        $this->db->bind(':cdate',date('Y-m-d'));
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Closed Financial Year '.$data['yearname'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    /**
     * Active records dated in a year or linked to it: [label => count] for every kind that has some.
     * Tables without a deleted flag (customer invoices, budgets, work plans, officials) count every row.
     */
    private function linkedRecords($year)
    {
        $id = (int)$year->ID;
        $range = [$year->startDate,$year->endDate];
        $checks = [
            ['ledger entries',             'SELECT COUNT(*) FROM journal_entries WHERE deleted = 0 AND entryDate BETWEEN ? AND ?', $range],
            ['old ledger rows',            'SELECT COUNT(*) FROM tblledger WHERE deleted = 0 AND transactionDate BETWEEN ? AND ?', $range],
            ['receipts',                   'SELECT COUNT(*) FROM tblcontributions_header WHERE Deleted = 0 AND fiscalYearId = ?', [$id]],
            ['expenses',                   'SELECT COUNT(*) FROM tblexpenses WHERE deleted = 0 AND fiscalYearId = ?', [$id]],
            ['petty cash entries',         'SELECT COUNT(*) FROM tblpettycash WHERE Deleted = 0 AND TransactionDate BETWEEN ? AND ?', $range],
            ['bank postings',              'SELECT COUNT(*) FROM tblbankpostings WHERE deleted = 0 AND transactionDate BETWEEN ? AND ?', $range],
            ['bank transactions',          'SELECT COUNT(*) FROM tblbanktransactions WHERE Deleted = 0 AND TransactionDate BETWEEN ? AND ?', $range],
            ['deposits',                   'SELECT COUNT(*) FROM tbldesposits WHERE Deleted = 0 AND DepositDate BETWEEN ? AND ?', $range],
            ['MMF / group fund rows',      'SELECT COUNT(*) FROM tblmmf WHERE Deleted = 0 AND TransactionDate BETWEEN ? AND ?', $range],
            ['fund requisitions',          'SELECT COUNT(*) FROM tblfundrequisition WHERE Deleted = 0 AND RequisitionDate BETWEEN ? AND ?', $range],
            ['pledges',                    'SELECT COUNT(*) FROM tblpledges_header WHERE deleted = 0 AND pledgeDate BETWEEN ? AND ?', $range],
            ['customer invoices',          'SELECT COUNT(*) FROM tblinvoice_header WHERE fiscalYearId = ?', [$id]],
            ['customer invoice payments',  'SELECT COUNT(*) FROM tblinvoice_payments WHERE paymentDate BETWEEN ? AND ?', $range],
            ['supplier invoices',          'SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE deleted = 0 AND fiscalYearId = ?', [$id]],
            ['supplier payments',          'SELECT COUNT(*) FROM tblinvoice_payments_suppliers WHERE Deleted = 0 AND paymentDate BETWEEN ? AND ?', $range],
            ['church budgets',             'SELECT COUNT(*) FROM tblchurchbudget_header WHERE yearId = ?', [$id]],
            ['group budgets',              'SELECT COUNT(*) FROM tblgroupbudget_header WHERE fiscalYearId = ?', [$id]],
            ['work plans',                 'SELECT COUNT(*) FROM tblworkplan WHERE FiscalYearId = ?', [$id]],
            ['group officials',            'SELECT COUNT(*) FROM tblgroupofficials WHERE yearId = ?', [$id]],
        ];
        $found = [];
        foreach ($checks as $c) {
            $n = (int)getdbvalue($this->db->dbh,$c[1],$c[2]);
            if ($n > 0) {
                $found[$c[0]] = $n;
            }
        }
        return $found;
    }

    /**
     * Reasons a year cannot be deleted (task 8.4d); an empty list means it can:
     *  - it must be closed (an open year is in use), and
     *  - nothing active may be dated in it or linked to it (linkedRecords()).
     */
    public function deleteBlockers($id)
    {
        $year = loadsingleset($this->db->dbh,'SELECT ID,yearName,startDate,endDate,closed,deleted FROM tblfiscalyears WHERE ID = ?',[(int)$id]);
        if (!$year || (int)$year->deleted === 1) {
            return ['Financial year not found or already deleted'];
        }
        $name = strtoupper($year->yearName);
        $reasons = [];
        if ((int)$year->closed === 0) {
            $reasons[] = "The financial year $name is open; close it before deleting it";
        }
        $linked = $this->linkedRecords($year);
        if ($linked) {
            $parts = [];
            foreach ($linked as $label => $n) {
                $parts[] = "$n $label";
            }
            $reasons[] = "The financial year $name still has records: " . implode(', ',$parts);
        }
        return $reasons;
    }

    /** Guarded delete. Returns ['success' => bool, 'errors' => string[]] (no errors on failure = unexpected error, logged). */
    public function delete($data)
    {
        $pdo = $this->db->dbh;
        try {
            $pdo->beginTransaction();
            // lock the year row so the checks and the delete see the same state
            getdbvalue($pdo,'SELECT ID FROM tblfiscalyears WHERE ID = ? FOR UPDATE',[(int)$data['id']]);
            $reasons = $this->deleteBlockers($data['id']);
            if ($reasons) {
                $pdo->rollBack();
                return ['success' => false,'errors' => $reasons];
            }
            $this->db->query('UPDATE tblfiscalyears SET deleted=1
                              WHERE (ID=:id)');
            $this->db->bind(':id',(int)$data['id']);
            $this->db->execute();
            saveLog($pdo,'Deleted Financial Year '.$data['yearname']);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => []];
        }
    }
}
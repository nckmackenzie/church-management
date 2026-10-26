<?php
class Parishreport
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
    public function GetCongregations()
    {
        $this->db->query('SELECT ID,
                                 UCASE(CongregationName) As CongregationName
                          FROM   tblcongregation
                          WHERE  (deleted = 0)');
        return $this->db->resultSet();
    }
    /**
     * Report-safe account list for one root type over the parish scope (every congregation with a non-deleted record or history,
     * decision 127): by ID with a path label, no `active` filter, deleted ones only with history (task 9.1).
     */
    public function GetAccounts($id)
    {
        return array_map(function($a){ return (object)['ID' => $a['id'],'accountType' => $a['label']]; },
            FinancialReport::accountOptions($this->db->pdo(),FinancialReport::parishScope($this->db->pdo()),['roots' => [(int)$id]]));
    }
    /** IDs of GetAccounts($root): a posted account must be one of them. @return int[] */
    public function reportAccountIds($root)
    {
        return array_map(function($a){ return $a['id']; },
            FinancialReport::accountOptions($this->db->pdo(),FinancialReport::parishScope($this->db->pdo()),['roots' => [(int)$root]]));
    }
    private function marks(array $ids)
    {
        return implode(',',array_fill(0,count($ids),'?'));
    }
    /** Account names (decision 149) from the report-safe chart of the parish scope, by account ID. */
    private function accountLabels(array $scope)
    {
        $labels = [];
        foreach (FinancialReport::chart($this->db->pdo(),$scope)['nodes'] as $id => $n) {
            $labels[(int)$id] = FinancialReport::title($n['label']);
        }
        return $labels;
    }
    private function congregationNames(array $ids)
    {
        $st = $this->db->pdo()->prepare('SELECT ID, CongregationName FROM tblcongregation WHERE ID IN (' . $this->marks($ids) . ')');
        $st->execute($ids);
        $names = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $names[(int)$r['ID']] = strtoupper(trim((string)$r['CongregationName'])); }
        return $names;
    }
    /**
     * Contributions of the selected congregations (the receipt's congregation) and accounts (task 9.7, decision 135): approved
     * (status = 1), not deleted headers only; one row per detail line, amounts in integer cents (FLOAT column, rounded to the cent).
     * @return array[]
     */
    public function GetContributions($data)
    {
        $st = $this->db->pdo()->prepare('SELECT d.ID, d.contributionDate AS cdate, h.congregationId, d.contributionTypeId AS accountId,
                                                CAST(ROUND(d.amount * 100) AS SIGNED) AS cents
                                         FROM   tblcontributions_details d JOIN tblcontributions_header h ON h.ID = d.HeaderId
                                         WHERE  h.Deleted = 0 AND h.status = 1 AND d.contributionDate BETWEEN ? AND ?
                                                AND d.contributionTypeId IN (' . $this->marks($data['accounts']) . ') AND h.congregationId IN (' . $this->marks($data['congregations']) . ')
                                         ORDER BY d.contributionDate, d.ID');
        $st->execute(array_merge([$data['start'],$data['end']],$data['accounts'],$data['congregations']));
        $names = $this->congregationNames($data['congregations']);
        $labels = $this->accountLabels($data['congregations']);
        return array_map(function($r) use ($names,$labels){
            return ['date' => $r['cdate'],'congregation' => $names[(int)$r['congregationId']] ?? '','account' => $labels[(int)$r['accountId']] ?? '','amount' => (int)$r['cents']];
        },$st->fetchAll(PDO::FETCH_ASSOC));
    }
    /**
     * One total per contributor (decision 137), for the congregations selected as the RECEIPT's (header) congregation: grouped by the
     * header congregation and the contributor's category and ID (names are for display only), approved, not deleted headers only.
     * The contributor is the member / group / district / service of the line, or the contributing congregation for the parish
     * categories (mmf, donations, others).
     * @return array[]
     */
    public function GetContributionsByContributor($data)
    {
        $who = 'CASE d.category WHEN 1 THEN d.contributor WHEN 2 THEN d.contributotGroup WHEN 3 THEN d.contributotDistrict WHEN 4 THEN d.contributotService ELSE d.contributotCong END';
        $name = 'CASE d.category WHEN 1 THEN m.memberName WHEN 2 THEN g.groupName WHEN 3 THEN t.districtName WHEN 4 THEN s.serviceName ELSE cc.CongregationName END';
        $st = $this->db->pdo()->prepare("SELECT h.congregationId, d.category, $who AS whoId, MAX($name) AS contributor,
                                                SUM(CAST(ROUND(d.amount * 100) AS SIGNED)) AS cents
                                         FROM   tblcontributions_details d JOIN tblcontributions_header h ON h.ID = d.HeaderId
                                                LEFT JOIN tblmember m ON d.contributor = m.ID LEFT JOIN tblgroups g ON d.contributotGroup = g.ID
                                                LEFT JOIN tbldistricts t ON d.contributotDistrict = t.ID LEFT JOIN tblservices s ON d.contributotService = s.ID
                                                LEFT JOIN tblcongregation cc ON d.contributotCong = cc.ID
                                         WHERE  h.Deleted = 0 AND h.status = 1 AND d.contributionDate BETWEEN ? AND ?
                                                AND d.contributionTypeId IN (" . $this->marks($data['accounts']) . ') AND h.congregationId IN (' . $this->marks($data['congregations']) . ")
                                         GROUP BY h.congregationId, d.category, $who");
        $st->execute(array_merge([$data['start'],$data['end']],$data['accounts'],$data['congregations']));
        $names = $this->congregationNames($data['congregations']);
        $rows = array_map(function($r) use ($names){
            return ['congregation' => $names[(int)$r['congregationId']] ?? '','contributor' => $r['contributor'] === null ? 'UNKNOWN CONTRIBUTOR' : strtoupper(trim((string)$r['contributor'])),
                    'amount' => (int)$r['cents'],'key' => $r['congregationId'] . ':' . $r['category'] . ':' . $r['whoId']];
        },$st->fetchAll(PDO::FETCH_ASSOC));
        usort($rows,function($a,$b){ return strcmp($a['congregation'],$b['congregation']) ?: strcmp($a['contributor'],$b['contributor']) ?: strcmp($a['key'],$b['key']); });
        return $rows;
    }
    /**
     * Expenses of the selected congregations and accounts, approved (status = 1) and not deleted, totalled per congregation and
     * account by ID (names are for display only).
     * @return array[]
     */
    public function GetExpenses($data)
    {
        $st = $this->db->pdo()->prepare('SELECT e.congregationId, e.accountId, SUM(CAST(ROUND(e.amount * 100) AS SIGNED)) AS cents
                                         FROM   tblexpenses e
                                         WHERE  e.expenseDate BETWEEN ? AND ? AND e.status = 1 AND e.deleted = 0
                                                AND e.accountId IN (' . $this->marks($data['accounts']) . ') AND e.congregationId IN (' . $this->marks($data['congregations']) . ')
                                         GROUP BY e.congregationId, e.accountId');
        $st->execute(array_merge([$data['start'],$data['end']],$data['accounts'],$data['congregations']));
        $names = $this->congregationNames($data['congregations']);
        $labels = $this->accountLabels($data['congregations']);
        $rows = array_map(function($r) use ($names,$labels){
            return ['congregation' => $names[(int)$r['congregationId']] ?? '','account' => $labels[(int)$r['accountId']] ?? '','amount' => (int)$r['cents']];
        },$st->fetchAll(PDO::FETCH_ASSOC));
        usort($rows,function($a,$b){ return strcmp($a['congregation'],$b['congregation']) ?: strcmp($a['account'],$b['account']); });
        return $rows;
    }
    public function GetGroups($cong)
    {
        $this->db->query('SELECT ID,
                                 UCASE(groupName) as groupName
                          FROM   tblgroups 
                          WHERE (active = 1) AND (deleted = 0) AND (congregationId = :cid)
                          ORDER BY groupName');
        $this->db->bind(':cid',$cong);
        return $this->db->resultSet();
    }
    public function GetYears()
    {
        $this->db->query('SELECT ID,
                                 UCASE(yearName) as yearName
                          FROM   tblfiscalyears 
                          WHERE  (deleted = 0)
                          ORDER BY yearName');
        return $this->db->resultSet();
    }
    public function GetCurrentyear()
    {
        $this->db->query('SELECT ID FROM tblfiscalyears WHERE CURDATE() BETWEEN startDate AND endDate');
        return $this->db->getValue();
    }
    public function GetBudgetVsExpense($data)
    {
        if ($data['type'] == 1) {
            $this->db->query('CALL sp_church_budgetvexpense(:year,:cong);');
            $this->db->bind(':year',$data['year']);
            $this->db->bind(':cong',$data['cong']);
        }else {
            $this->db->query('call sp_group_budgetvexpense(:year,:cong,:group);');
            $this->db->bind(':year',$data['year']);
            $this->db->bind(':cong',$data['cong']);
            $this->db->bind(':group',$data['group']);
        }
        return $this->db->resultSet();
    }
    public function GetInvoices($data)
    {
        if ($data['status'] == 1) {
            $this->db->query('CALL sp_getinvoice_withbalances(:cong,:startdate,:enddate)');
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->bind(':startdate',$data['start']);
            $this->db->bind(':enddate',$data['end']);
        }elseif ($data['status'] == 2) {
            $this->db->query('CALL sp_getinvoice_fullypaid(:cong,:startdate,:enddate)');
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->bind(':startdate',$data['start']);
            $this->db->bind(':enddate',$data['end']);
        }elseif ($data['status'] == 3) {
            $this->db->query('CALL sp_getinvoice_due(:cong)');
            $this->db->bind(':cong',$_SESSION['congId']);
        }
        return $this->db->resultSet();
    }
    public function getBanks()
    {
        return array_map(function($b){ return (object)['ID' => $b['id'],'Bank' => $b['label']]; },
            FinancialReport::bankOptions($this->db->pdo(),[(int)$_SESSION['congId']]));
    }
    public function isReportBank($id)
    {
        return FinancialReport::bankAllowed($this->db->pdo(),[(int)$_SESSION['congId']],(int)$id);
    }
    public function bankingrpt($data)
    {
        $this->db->query('SELECT transactionDate,
                                 ucase(methodName) As methodName,
                                 IF(p.debit > 0,p.debit,(p.credit * -1)) As Amount,
                                 ucase(p.reference) As reference
                          FROM   tblbankpostings p inner join tbltransactionmethods m 
                                 on p.transactionMethod = m.ID
                          WHERE  (p.bankId=:bid) AND (p.cleared=:stat) AND (p.deleted = 0) 
                                 AND (p.transactionDate BETWEEN :tstart AND :tend) AND (p.congregationId = :cid)
                          ORDER BY transactionDate');
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':stat',$data['status']);
        $this->db->bind(':tstart',$data['from']);
        $this->db->bind(':tend',$data['to']);
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    
}

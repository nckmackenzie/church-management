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
    public function GetContributions($data)
    {
        $accountParams = $this->integerPlaceholders('account',$data['accounts']);
        $congregationParams = $this->integerPlaceholders('congregation',$data['congregations']);
        $this->db->query("SELECT d.contributionDate,
                                 ucase(c.CongregationName) as congregation,
                                 ucase(a.accountType) as account,
                                 ifnull(amount,0) as sumofamount
                          FROM   tblcontributions_details d inner join tblcontributions_header h on 
                                 d.HeaderId = h.ID inner join tblaccounttypes a 
                                 on d.contributionTypeId = a.ID inner join tblcongregation c on 
                                 h.congregationId = c.ID
                          WHERE  (h.Deleted = 0) AND (d.contributionDate BETWEEN :startd AND :endd) 
                                 AND d.contributionTypeId IN (".implode(',',$accountParams).") AND h.congregationId IN (".implode(',',$congregationParams).")");
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        $this->bindIntegerList('account',$data['accounts']);
        $this->bindIntegerList('congregation',$data['congregations']);
        return $this->db->resultSet();
    }
    public function GetContributionsByContributor($data)
    {
        $accountParams = $this->integerPlaceholders('account',$data['accounts']);
        $congregationParams = $this->integerPlaceholders('congregation',$data['congregations']);
        $this->db->query("SELECT d.contributionDate,
                                 ucase(c.CongregationName) as congregation,
                                 ucase(a.accountType) as account,
                                 ifnull(amount,0) as sumofamount
                          FROM   tblcontributions_details d inner join tblcontributions_header h on 
                                 d.HeaderId = h.ID inner join tblaccounttypes a 
                                 on d.contributionTypeId = a.ID left join tblcongregation c on 
                                 d.contributotCong = c.ID
                          WHERE  (h.Deleted = 0) AND (d.contributionDate BETWEEN :startd AND :endd) 
                                 AND d.contributionTypeId IN (".implode(',',$accountParams).") AND d.contributotCong IN (".implode(',',$congregationParams).")");
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        $this->bindIntegerList('account',$data['accounts']);
        $this->bindIntegerList('congregation',$data['congregations']);
        return $this->db->resultSet();
    }
    public function GetExpenses($data)
    {
        $accountParams = $this->integerPlaceholders('account',$data['accounts']);
        $congregationParams = $this->integerPlaceholders('congregation',$data['congregations']);
        $this->db->query("SELECT ucase(c.CongregationName) as congregation,
                                 ucase(a.accountType) as account,
                                 IFNULL(SUM(amount),0) as sumofamount
                          FROM   tblexpenses e inner join tblaccounttypes a on e.accountId = a.ID
                                 inner join tblcongregation c on e.congregationId = c.ID
                          WHERE  (e.expenseDate BETWEEN :startd AND :endd) AND (e.deleted = 0)
                                 AND e.accountId IN (".implode(',',$accountParams).") AND e.congregationId IN (".implode(',',$congregationParams).")
                          GROUP BY c.CongregationName,a.accountType;");
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        $this->bindIntegerList('account',$data['accounts']);
        $this->bindIntegerList('congregation',$data['congregations']);
        return $this->db->resultSet();
    }
    private function integerPlaceholders($prefix,array $values)
    {
        $placeholders = [];
        foreach ($values as $index => $value) {
            $placeholders[] = ':' . $prefix . $index;
        }
        return $placeholders;
    }
    private function bindIntegerList($prefix,array $values)
    {
        foreach ($values as $index => $value) {
            $this->db->bind(':' . $prefix . $index,$value,PDO::PARAM_INT);
        }
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
    public function GetAssets($data)
    {
        if ($data['cong'] != 0) {
            $this->db->query('CALL sp_balancesheet_assets(:startd,:cong)');
            $this->db->bind(':startd',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else {
            $this->db->query('CALL sp_balancesheet_assets_parish(:startd)');
            $this->db->bind(':startd',$data['todate']);
        }
        return $this->db->resultSet();            
    }
    public function GetLiablityEquity($data)
    {
        if ($data['cong'] != 0) {
            $this->db->query('CALL sp_balancesheet_liablityequity(:startd,:cong)');
            $this->db->bind(':startd',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else {
            $this->db->query('CALL sp_balancesheet_liablityequity_parish(:startd)');
            $this->db->bind(':startd',$data['todate']);
        }
        return $this->db->resultSet();            
    }
    public function GetAssetsTotal($data)
    {
        if ($data['cong'] != 0) {
            $this->db->query('SELECT IFNULL(SUM(debit),0) as sumofdebits 
                              FROM   tblledger 
                              WHERE  (accountId=3) AND (transactionDate <= :sdate)
                                     AND (congregationId = :cong) AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else{
            $this->db->query('SELECT IFNULL(SUM(debit),0) as sumofdebits 
                              FROM   tblledger 
                              WHERE  (accountId=3) AND (transactionDate <= :sdate)
                                     AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
        }
        $debits = $this->db->getValue();

        if ($data['cong'] != 0) {
            $this->db->query('SELECT IFNULL(SUM(credit),0) as sumofcredits 
                              FROM   tblledger 
                              WHERE  (accountId=3) AND (transactionDate <= :sdate)
                                     AND (congregationId = :cong) AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else {
            $this->db->query('SELECT IFNULL(SUM(credit),0) as sumofcredits 
                              FROM   tblledger 
                              WHERE  (accountId=3) AND (transactionDate <= :sdate)
                                     AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
        }
        $credits = $this->db->getValue();
        return floatval($debits) - floatval($credits);
    }
    public function GetLiabilityEquityTotal($data)
    {
        if ($data['cong'] != 0) {
            $this->db->query('SELECT IFNULL(SUM(debit),0) as sumofdebits 
                              FROM   tblledger 
                              WHERE  (accountId=4 OR accountId = 6) AND (transactionDate <= :sdate)
                                      AND (congregationId = :cong) AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else {
            $this->db->query('SELECT IFNULL(SUM(debit),0) as sumofdebits 
                              FROM   tblledger 
                              WHERE  (accountId=4 OR accountId = 6) AND (transactionDate <= :sdate)
                                      AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
        }
        $debits = $this->db->getValue();

        if ($data['cong'] != 0) {
            $this->db->query('SELECT IFNULL(SUM(credit),0) as sumofcredits 
                              FROM   tblledger 
                              WHERE  (accountId=4 OR accountId = 6) AND (transactionDate <= :sdate)
                                     AND (congregationId = :cong) AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else{
            $this->db->query('SELECT IFNULL(SUM(credit),0) as sumofcredits 
                              FROM   tblledger 
                              WHERE  (accountId=4 OR accountId = 6) AND (transactionDate <= :sdate)
                                      AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
        }
        $credits = $this->db->getValue();
        return (floatval($debits) * -1) - (floatval($credits) * -1);
    }
    public function GetNetIncome($data)
    {
        if ($data['cong'] != 0) {
            $this->db->query('SELECT IFNULL(SUM(debit),0) 
                              FROM   tblledger 
                              WHERE  (accountId=1) AND transactionDate <= :sdate
                                     AND congregationId = :cong AND deleted = 0');
            $this->db->bind(':sdate',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else{
            $this->db->query('SELECT IFNULL(SUM(debit),0) 
                              FROM   tblledger 
                              WHERE  (accountId=1) AND transactionDate <= :sdate
                                     AND deleted = 0');
            $this->db->bind(':sdate',$data['todate']);
        }
        $revenueDebit = $this->db->getValue();  
        //===========================
        if ($data['cong'] != 0) {
            $this->db->query('SELECT IFNULL(SUM(credit),0) 
                              FROM   tblledger 
                              WHERE  (accountId=1) AND transactionDate <= :sdate
                                     AND (congregationId = :cong) AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else {
            $this->db->query('SELECT IFNULL(SUM(credit),0) 
                              FROM   tblledger 
                              WHERE  (accountId=1) AND transactionDate <= :sdate
                                     AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
        }
        $revenueCredit = $this->db->getValue();    
        //================================================
        $revenueBalance = (floatval($revenueDebit) - floatval($revenueCredit)) *-1;
        //=========================================================
        if ($data['cong'] != 0) {
            $this->db->query('SELECT IFNULL(SUM(debit),0) 
                              FROM   tblledger 
                              WHERE  (accountId=2) AND transactionDate <= :sdate 
                                     AND (congregationId = :cong) AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else {
            $this->db->query('SELECT IFNULL(SUM(debit),0) 
                              FROM   tblledger 
                              WHERE  (accountId=2) AND transactionDate <= :sdate 
                                     AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
        }
        $expensesDebit = $this->db->getValue();  
        //===========================
        if ($data['cong'] != 0) {
            $this->db->query('SELECT IFNULL(SUM(credit),0) 
                              FROM   tblledger 
                              WHERE  (accountId=2) AND transactionDate <= :sdate 
                                     AND (congregationId = :cong) AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
            $this->db->bind(':cong',$data['cong']);
        }else {
            $this->db->query('SELECT IFNULL(SUM(credit),0) 
                              FROM   tblledger 
                              WHERE  (accountId=2) AND transactionDate <= :sdate 
                                     AND (deleted = 0)');
            $this->db->bind(':sdate',$data['todate']);
        }
        
        $expensesCredit = $this->db->getValue();    
        //================================================
        $expensesBalance = floatval($expensesDebit) - floatval($expensesCredit);
        return floatval($revenueBalance) - floatval($expensesBalance);
    }
    /** Report-safe banks of the session congregation: non-deleted, active or with history (inactive ones labelled). */
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

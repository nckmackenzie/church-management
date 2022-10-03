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
    public function GetAccounts($id)
    {
        $this->db->query('SELECT ID,
                                 UCASE(accountType) AS accountType 
                          FROM   tblaccounttypes 
                          WHERE  (accountTypeId = :id) AND (deleted = 0)
                          ORDER BY accountType');
        $this->db->bind(':id',$id);
        return $this->db->resultSet();
    }
    public function GetContributions($data)
    {
        $this->db->query("SELECT ucase(c.CongregationName) as congregation,
                                 ucase(a.accountType) as account,
                                 ifnull(sum(amount),0) as sumofamount
                          FROM   tblcontributions_details d inner join tblcontributions_header h on 
                                 d.ID = h.ID inner join tblaccounttypes a 
                                 on d.contributionTypeId = a.ID inner join tblcongregation c on 
                                 h.congregationId = c.ID
                          WHERE  (d.contributionDate BETWEEN :startd AND :endd) 
                                 AND d.contributionTypeId IN (".$data['accounts'].") AND h.congregationId IN (".$data['congregations'].")
                          GROUP BY c.CongregationName,a.accountType");
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        return $this->db->resultSet();
    }
    public function GetExpenses($data)
    {
        $this->db->query("SELECT ucase(c.CongregationName) as congregation,
                                 ucase(a.accountType) as account,
                                 IFNULL(SUM(amount),0) as sumofamount
                          FROM   tblexpenses e inner join tblaccounttypes a on e.accountId = a.ID
                                 inner join tblcongregation c on e.congregationId = c.ID
                          WHERE  (e.expenseDate BETWEEN :startd AND :endd) AND (e.deleted = 0)
                                 AND e.accountId IN (".$data['accounts'].") AND e.congregationId IN (".$data['congregations'].")
                          GROUP BY c.CongregationName,a.accountType;");
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        return $this->db->resultSet();
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
    public function GetRevenues($data)
    {
        $sql = 'SELECT UCASE(account) as account,
                       SUM(credit) as SumOfTotal
                FROM   tblledger 
                WHERE  (transactionDate BETWEEN :startd AND :endd) AND (accountId = 1)
                       AND (deleted = 0) ';
        if ($data['cong'] != 0) {
            $sql .='AND (congregationId = :cid) ';
        }
        $sql .=' GROUP BY account';
        $this->db->query($sql);
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        if ($data['cong'] != 0) {
            $this->db->bind(':cid',$data['cong']);
        }
        return $this->db->resultSet();
    }
    public function GetRevenuesTotal($data)
    {
        $sql = 'SELECT IFNULL(SUM(credit),0) AS SumOfTotal
                FROM   tblledger
                WHERE  (transactionDate BETWEEN :startd AND :endd) AND (accountId = 1)
                       AND (deleted = 0) ';
        if ($data['cong'] != 0) {
            $sql .='AND (congregationId = :cid) ';
        }
        $this->db->query($sql);
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        if ($data['cong'] != 0) {
            $this->db->bind(':cid',$data['cong']);
        }
        return $this->db->getValue();
    }
    public function GetExpensesPL($data)
    {
        $sql = 'SELECT UCASE(account) as account,
                       SUM(debit) as SumOfTotal
                FROM   tblledger
                WHERE  (transactionDate BETWEEN :startd AND :endd) AND (accountId = 2)
                       AND (deleted = 0) ';
        if ($data['cong'] != 0) {
            $sql .='AND (congregationId = :cid) ';
        }
        $sql .='GROUP BY account';
        $this->db->query($sql);
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        if ($data['cong'] != 0) {
            $this->db->bind(':cid',$data['cong']);
        }
        return $this->db->resultSet();
    }
    public function GetExpensesTotal($data)
    {
        $sql = 'SELECT IFNULL(SUM(debit),0) AS SumOfTotal 
                FROM   tblledger
                WHERE  (transactionDate BETWEEN :startd AND :endd) AND (accountId = 2)
                       AND (deleted = 0) ';
        if ($data['cong'] != 0) {
            $sql .= 'AND (congregationId = :cid)';
        }
        $this->db->query($sql);
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        if ($data['cong'] != 0) {
            $this->db->bind(':cid',$data['cong']);
        }
        return $this->db->getValue();
    }
    public function GetTrialBalance($data)
    {
        if ($data['cong'] != 0) {
            $this->db->query('CALL sp_trialbalance(:startdate,:enddate,:cong)');
        }else {
            $this->db->query('CALL sp_trialbalance_parish(:startdate,:enddate)');
        }
        $this->db->bind(':startdate',$data['start']);
        $this->db->bind(':enddate',$data['end']);
        if ($data['cong'] != 0) {
            $this->db->bind(':cong',$data['cong']);
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
    public function getBanks()
    {
        $this->db->query('SELECT   ID,
                                   UCASE(accountType) As Bank
                          FROM     tblaccounttypes 
                          WHERE    (isBank=1) AND (Deleted=0) AND (congregationId=:cid)');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
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
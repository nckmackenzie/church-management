<?php
class Report {
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
    public function getDistricts()
    {
        $this->db->query('SELECT ID,UCASE(districtName) as districtName
                          FROM tbldistricts WHERE (deleted=0) AND (congregationId=:cid)
                          ORDER BY districtName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();                  
    }
    public function loadMembersRpt($district,$status,$from,$to)
    {
        if ($status < 3 ) {
            if ($district == 0) {
                $this->db->query("SELECT UCASE(M.memberName) AS memberName,UCASE(G.gender) AS gender,
                                  M.idNo,M.contact,UCASE(D.districtName) as districtName,
                                  UCASE(P.positionName) AS positionName,
                                  IF(membershipStatus = 1,'FULL',IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4, 'UNDER-12','NOT SPECIFIED')))) AS mstatus,
                                  IF(memberStatus = 1,'ACTIVE',IF(memberStatus=2,'DORMANT','DECEASED')) AS memberstatus
                                  FROM tblmember M LEFT JOIN tblpositions P ON M.positionId=P.ID LEFT JOIN
                                  tbldistricts D ON M.districtId=D.ID LEFT JOIN tblgender G ON M.genderId=G.
                                  ID WHERE (memberStatus = :sta) AND (M.congregationId = :cid) 
                                  ORDER BY memberName");
                $this->db->bind(':sta',$status);                  
                $this->db->bind(':cid',$_SESSION['congId']);                  
            }else{
                $this->db->query("SELECT UCASE(M.memberName) AS memberName,UCASE(G.gender) AS gender,
                                  M.idNo,M.contact,UCASE(D.districtName) as districtName,
                                  UCASE(P.positionName) AS positionName,
                                  IF(membershipStatus = 1,'FULL',IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4, 'UNDER-12','NOT SPECIFIED')))) AS mstatus,
                                  IF(memberStatus = 1,'ACTIVE',IF(memberStatus=2,'DORMANT','DECEASED')) AS memberstatus
                                  FROM tblmember M LEFT JOIN tblpositions P ON M.positionId=P.ID LEFT JOIN
                                  tbldistricts D ON M.districtId=D.ID LEFT JOIN tblgender G ON M.genderId=G.
                                  ID WHERE (memberStatus = :sta) AND (districtId=:did) ORDER BY memberName");
                $this->db->bind(':sta',$status);
                $this->db->bind(':did',$district);
            }
        }else{
            if ($district == 0) {
                $this->db->query('SELECT memberName,gender,age,contact,district,remark 
                                  FROM   vw_byage 
                                  WHERE  (age BETWEEN :froma AND :toa) AND (congregation = :cid);
                                  ORDER BY memberName');
                $this->db->bind(':froma',$from);
                $this->db->bind(':toa',$to);
                $this->db->bind(':cid',$_SESSION['congId']);
            }else{
                $this->db->query('SELECT memberName,gender,age,contact,district,remark 
                                  FROM   vw_byage 
                                  WHERE  (age BETWEEN :froma AND :toa) AND (districtId = :did);
                                  ORDER BY memberName');
                $this->db->bind(':froma',$from);
                $this->db->bind(':toa',$to);
                $this->db->bind(':did',$district);
            }
        }
        
        return $this->db->resultSet();
    }
    public function getTransfered($data)
    {
        $this->db->query('SELECT UCASE(M.memberName) AS memberName,G.gender,UCASE(P.positionName) AS
                                 positionName,UCASE(C.congregationName) AS congregation,
                                 T.transferDate,T.reason
                          FROM   tblmembertransfers T INNER JOIN tblmember M ON T.memberId=M.ID
                                 LEFT JOIN tblcongregation C ON T.toId=C.ID INNER JOIN tblgender G ON
                                 M.genderId=G.ID LEFT JOIN tblpositions P ON M.positionId=P.ID
                          WHERE (T.fromId=:fid) AND (T.transferDate BETWEEN :sta AND :endd)');
        $this->db->bind(':fid',$_SESSION['congId']);
        $this->db->bind(':sta',$data['from']);
        $this->db->bind(':endd',$data['to']);
        return $this->db->resultSet();
    }
    public function byStatusRpt($data)
    {
        if ($data['status'] == 0 && $data['district'] == 0) {
            $this->db->query("SELECT UCASE(memberName) AS memberName,UCASE(G.gender) AS gender,
                                     IF(M.memberStatus=1,'Active',IF(M.memberStatus=2,'Dormant','Deceased')) AS mstatus,UCASE(D.districtName) AS district,UCASE(P.positionName) as position,IF(membershipStatus=1,'FULL', IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4,'UNDER-12','NOT SPECIFIED')))) AS membershipStatus
                              FROM   tblmember M left join tblgender G ON M.genderId=G.ID LEFT JOIN
                                     tbldistricts D ON M.districtId=D.ID LEFT JOIN tblpositions P ON
                                     M.positionId=P.ID 
                              WHERE  (M.congregationId=:cid)");
            $this->db->bind(':cid',$_SESSION['congId']);
        }
        elseif ($data['status'] != 0 && $data['district'] == 0 ) {
            $this->db->query("SELECT UCASE(memberName) AS memberName,UCASE(G.gender) AS gender,
                                     IF(M.memberStatus=1,'Active',IF(M.memberStatus=2,'Dormant','Deceased')) AS mstatus,UCASE(D.districtName) AS district,UCASE(P.positionName) as position,IF(membershipStatus=1,'FULL', IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4,'UNDER-12','NOT SPECIFIED')))) AS membershipStatus
                              FROM   tblmember M left join tblgender G ON M.genderId=G.ID LEFT JOIN
                                     tbldistricts D ON M.districtId=D.ID LEFT JOIN tblpositions P ON
                                     M.positionId=P.ID 
                              WHERE  (M.congregationId=:cid) AND (M.membershipStatus = :stid)");
            $this->db->bind(':cid',$_SESSION['congId']);                  
            $this->db->bind(':stid',$data['status']);                  
        }
        elseif ($data['status'] == 0 && $data['district'] != 0 ) {
            $this->db->query("SELECT UCASE(memberName) AS memberName,UCASE(G.gender) AS gender,
                                     IF(M.memberStatus=1,'Active',IF(M.memberStatus=2,'Dormant','Deceased')) AS mstatus,UCASE(D.districtName) AS district,UCASE(P.positionName) as position,IF(membershipStatus=1,'FULL', IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4,'UNDER-12','NOT SPECIFIED')))) AS membershipStatus
                              FROM   tblmember M left join tblgender G ON M.genderId=G.ID LEFT JOIN
                                     tbldistricts D ON M.districtId=D.ID LEFT JOIN tblpositions P ON
                                     M.positionId=P.ID 
                              WHERE  (M.congregationId=:cid) AND (M.districtId = :did)");
            $this->db->bind(':cid',$_SESSION['congId']);                  
            $this->db->bind(':did',$data['district']);                  
        }
        else{
            $this->db->query("SELECT UCASE(memberName) AS memberName,UCASE(G.gender) AS gender,
                                     IF(M.memberStatus=1,'Active',IF(M.memberStatus=2,'Dormant','Deceased')) AS mstatus,UCASE(D.districtName) AS district,UCASE(P.positionName) as position,IF(membershipStatus=1,'FULL', IF(membershipStatus=2,'ADHERENT',IF(membershipStatus=3,'ASSOCIATE',IF(membershipStatus=4,'UNDER-12','NOT SPECIFIED')))) AS membershipStatus
                              FROM   tblmember M left join tblgender G ON M.genderId=G.ID LEFT JOIN
                                     tbldistricts D ON M.districtId=D.ID LEFT JOIN tblpositions P ON
                                     M.positionId=P.ID 
                              WHERE  (M.membershipStatus = :stid) AND (M.districtId = :did)");
            $this->db->bind(':stid',$data['status']);                  
            $this->db->bind(':did',$data['district']);
        }
        return $this->db->resultSet();
    }
    public function getResidenceRpt($district)
    {
        if ($district == 0) {
            $this->db->query('SELECT UCASE(M.memberName) AS memberName,UCASE(G.gender) AS gender,M.contact
                                     ,UCASE(D.districtName) as districtName,UCASE(M.occupation) AS occupation,UCASE(M.residence) AS residence
                              FROM   tblmember M LEFT JOIN tblpositions P ON M.positionId=P.ID LEFT JOIN
                                     tbldistricts D ON M.districtId=D.ID LEFT JOIN tblgender G ON M.genderId=G.ID
                              WHERE  (M.congregationId=:cid)
                              ORDER BY memberName');
            $this->db->bind(':cid',$_SESSION['congId']);
        }else{
            $this->db->query('SELECT UCASE(M.memberName) AS memberName,UCASE(G.gender) AS gender,M.contact
                                     ,UCASE(D.districtName) as districtName,UCASE(M.occupation) AS occupation,UCASE(M.residence) AS residence
                              FROM   tblmember M LEFT JOIN tblpositions P ON M.positionId=P.ID LEFT JOIN
                                     tbldistricts D ON M.districtId=D.ID LEFT JOIN tblgender G ON M.genderId=G.ID
                              WHERE  (M.districtId=:did)
                              ORDER BY memberName');
            $this->db->bind(':did',$district);
        }
        return $this->db->resultSet();
    }
    public function getFamilyCount()
    {
        $this->db->query('SELECT COUNT(DISTINCT memberId) AS fcount 
                          FROM tblmember_family 
                          WHERE congregationId = :cid');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->getValue();
    }
    public function getFamily($district)
    {
        if ($district == 0) {
            $this->db->query('SELECT * FROM vw_family');
        }else{
            $this->db->query('SELECT * FROM vw_family
                              WHERE  (district = :did)');
            $this->db->bind(':did',$district);
        }
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
        if ($data['type'] == 1) {
            $this->db->query('CALL sp_contribitions_report_all(:cong,:start,:end)');
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->bind(':start',$data['start']);
            $this->db->bind(':end',$data['end']);
            return $this->db->resultSet();
        }elseif ($data['type'] == 2) {
            $sql = "SELECT DISTINCT d.ID,DATE_FORMAT(d.contributionDate,'%d/%m/%Y') AS contdate,
                           ucase(a.accountType) as conttype,ucase(IF(d.category = 1,m.memberName,IF(d.category=2,g.groupName,
                           IF(d.category=3,t.districtName,s.serviceName)))) As cont,d.amount,ucase(p.paymentMethod) as paymethod,
                           UCASE(d.paymentReference) AS paymentReference
                    FROM   tblcontributions_header h inner join tblcontributions_details d left join tblmember m on d.contributor = m.ID left join tblgroups g
                           on d.contributotGroup = g.ID left join tbldistricts t on d.contributotDistrict
                           = t.ID left join tblservices s on d.contributotService = s.ID inner join
                           tblaccounttypes a on d.contributionTypeId = a.ID left join tblpaymentmethods p on d.paymentMethodId = p.ID
                    WHERE  (h.Deleted = 0) AND (h.congregationId = :cong) AND (h.status = 1) AND (d.contributionDate BETWEEN :startd AND :endd)
                           AND (d.contributionTypeId IN (".$data['account'].")) ORDER BY d.contributionDate";
            $this->db->query($sql);
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->bind(':startd',$data['start']);
            $this->db->bind(':endd',$data['end']);
            return $this->db->resultSet();
        }elseif ($data['type'] == 3) {
            $sql = 'SELECT 
                        d.districtName,
                        ifnull(sum(amount),0) as total_amount
                    FROM 
                        `tblcontributions_details` c left join tbldistricts d 
                        on c.contributotDistrict = d.ID join tblcontributions_header h on c.HeaderId = h.ID
                    WHERE (c.category = 3) AND (h.congregationId = :cong) AND (c.contributionDate BETWEEN :startd AND :endd)
                    group by d.districtName;';
            $this->db->query($sql);
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->bind(':startd',$data['start']);
            $this->db->bind(':endd',$data['end']);
            return $this->db->resultSet();
        }
    }
    public function GetExpenses($data)
    {
        if ($data['type'] == 1) {
            $this->db->query('CALL sp_expenses_all(:cong,:start,:end)');
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->bind(':start',$data['start']);
            $this->db->bind(':end',$data['end']);
            return $this->db->resultSet();
        }elseif ($data['type'] == 2) {
            $sql = "SELECT DATE_FORMAT(e.expenseDate,'%d/%m/%Y') AS expenseDate,voucherNo,ucase(a.accountType) as accountType,
                           ucase(IF(e.expenseType = 1,'lcc',g.groupName)) As costcenter,
                           e.amount,ucase(p.paymentMethod) as paymethod,
                           ucase(e.paymentReference) as payref
                    FROM   tblexpenses e inner join tblaccounttypes a on e.accountId=a.ID inner join tblpaymentmethods p  
                           on e.paymethodId = p.ID left join tblgroups g on e.groupId = g.ID
                    WHERE  (e.congregationId = :cong) AND (e.expenseDate BETWEEN :startd AND :endd) AND (e.status = 1) AND (e.deleted = 0)
                           AND (e.accountId IN (".$data['account'].") )
                    ORDER BY e.expenseDate;";
                    
            $this->db->query($sql);
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->bind(':startd',$data['start']);
            $this->db->bind(':endd',$data['end']);
            return $this->db->resultSet();
        }
    }
    public function GetPledges($data)
    {
        if ($data['type'] == 1) {
            $this->db->query('CALL sp_get_pledges(:act,:start,:end,:cong);');
            $this->db->bind(':act',1);
            $this->db->bind(':start',$data['start']);
            $this->db->bind(':end',$data['end']);
            $this->db->bind(':cong',$_SESSION['congId']);
        }elseif ($data['type'] == 2) {
            $this->db->query('CALL sp_get_pledges(:act,:start,:end,:cong);');
            $this->db->bind(':act',2);
            $this->db->bind(':start',$data['start']);
            $this->db->bind(':end',$data['end']);
            $this->db->bind(':cong',$_SESSION['congId']);
        }elseif ($data['type'] == 3) {
            $this->db->query('CALL sp_get_pledges(:act,:start,:end,:cong);');
            $this->db->bind(':act',3);
            $this->db->bind(':start',$data['start']);
            $this->db->bind(':end',$data['end']);
            $this->db->bind(':cong',$_SESSION['congId']);
        }elseif ($data['type'] == 4) {
            $this->db->query('CALL sp_get_pledge_payments(:cong,:start,:end);');
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->bind(':start',$data['start']);
            $this->db->bind(':end',$data['end']);
        }
        return $this->db->resultSet();
    }
    public function GetGroups()
    {
        $this->db->query('SELECT ID,
                                 UCASE(groupName) as groupName
                          FROM   tblgroups 
                          WHERE (active = 1) AND (deleted = 0) AND (congregationId = :cid)
                          ORDER BY groupName');
        $this->db->bind(':cid',$_SESSION['congId']);
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
            $this->db->bind(':cong',$_SESSION['congId']);
        }else {
            $this->db->query('call sp_group_budgetvexpense(:year,:cong,:group);');
            $this->db->bind(':year',$data['year']);
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->bind(':group',$data['group']);
        }
        return $this->db->resultSet();
    }

    public function GetRevenues($data)
    {
        // before changes
        $sql = 'SELECT parentaccount,(IFNULL(SUM(credit),0) - IFNULL(SUM(debit),0)) AS credit FROM tblledger 
                WHERE (deleted = 0) AND (congregationId = ?) AND (transactionDate BETWEEN ? AND ?) AND (accountId=1)
                GROUP BY parentaccount';
        return loadresultset($this->db->dbh,$sql,[(int)$_SESSION['congId'],$data['start'],$data['end']]);
       
    }

    public function GetRevenuesTotal($data)
    {
        // $this->db->query('SELECT IFNULL(SUM(credit),0) AS SumOfTotal 
        //                   FROM   tblledger
        //                   WHERE  (transactionDate BETWEEN :startd AND :endd) AND (accountId = 1)
        //                          AND (deleted = 0) AND (congregationId = :cid)');
        $this->db->query('SELECT (IFNULL(SUM(credit),0) - IFNULL(SUM(debit),0)) AS SumOfTotal 
                          FROM   tblledger
                          WHERE  (transactionDate BETWEEN :startd AND :endd) AND (accountId = 1)
                                 AND (deleted = 0) AND (congregationId = :cid)');
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->getValue();
    }
    public function GetExpensesPL($data)
    {
        $sql = 'SELECT parentaccount,(IFNULL(SUM(debit),0) - IFNULL(SUM(credit),0)) AS debit FROM tblledger 
                WHERE (deleted = 0) AND (congregationId = ?) AND (transactionDate BETWEEN ? AND ?) AND (accountId=2)
                GROUP BY parentaccount';
        return loadresultset($this->db->dbh,$sql,[(int)$_SESSION['congId'],$data['start'],$data['end']]);
    }
    
    public function GetExpensesTotal($data)
    {
        $this->db->query('SELECT (IFNULL(SUM(credit),0) + IFNULL(SUM(debit),0)) AS SumOfTotal 
                          FROM   tblledger
                          WHERE  (transactionDate BETWEEN :startd AND :endd) AND (accountId = 2)
                                 AND (deleted = 0) AND (congregationId = :cid)');
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->getValue();
    }
    public function GetChildAccounts($data)
    {
        // $sql = '';
        $accountType = getdbvalue($this->db->dbh,'SELECT accountTypeId FROM tblaccounttypes WHERE LOWER(accountType) = ?',[strtolower($data['account'])]);
        if((int)$accountType === 1){
            $sql = 'SELECT account,(IFNULL(SUM(credit),0) - IFNULL(SUM(debit),0)) AS amount FROM tblledger 
                    WHERE (deleted = 0) AND (congregationId = ?) AND (transactionDate BETWEEN ? AND ?) AND (parentaccount=?)
                    GROUP BY account';
        }elseif ((int)$accountType === 2) {
            $sql = 'SELECT account,(IFNULL(SUM(debit),0) - IFNULL(SUM(credit),0)) AS amount FROM tblledger 
                    WHERE (deleted = 0) AND (congregationId = ?) AND (transactionDate BETWEEN ? AND ?) AND (parentaccount=?)
                    GROUP BY account';
        }

        // $sql = 'SELECT account,(IFNULL(SUM(credit),0) + IFNULL(SUM(debit),0)) AS amount FROM tblledger 
        //         WHERE (deleted = 0) AND (congregationId = ?) AND (transactionDate BETWEEN ? AND ?) AND (parentaccount=?)
        //         GROUP BY account';
        return loadresultset($this->db->dbh,$sql,[(int)$_SESSION['congId'],$data['sdate'],$data['edate'],$data['account']]);
       
    }
    public function GetTrialBalance($data)
    {
        $this->db->query('CALL sp_trialbalance(:startdate,:enddate,:cong)');
        $this->db->bind(':startdate',$data['start']);
        $this->db->bind(':enddate',$data['end']);
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function GetAssets($date)
    {
        // $this->db->query('CALL sp_balancesheet_assets(:startd,:cong)');
        $this->db->query('CALL sp_get_assets(:startd,:cong)');
        $this->db->bind(':startd',$date);
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();            
    }
    public function GetLiablityEquity($date)
    {
        // $this->db->query('CALL sp_balancesheet_liablityequity(:startd,:cong)');
        // $this->db->query('CALL sp_get_liabilities_equity(:startd,:cong)'); // before v2
        $this->db->query('CALL sp_get_liabilities_equity_v2(:startd,:cong)');
        $this->db->bind(':startd',$date);
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();            
    }
    public function GetAssetsTotal($date)
    {
        $this->db->query('SELECT IFNULL(SUM(debit),0) as sumofdebits 
                          FROM   tblledger 
                          WHERE  (accountId=3) AND (transactionDate <= :sdate)
                                 AND (congregationId = :cong)');
        $this->db->bind(':sdate',$date);
        $this->db->bind(':cong',$_SESSION['congId']);                  
        $debits = $this->db->getValue();

        $this->db->query('SELECT IFNULL(SUM(credit),0) as sumofcredits 
                          FROM   tblledger 
                          WHERE  (accountId=3) AND (transactionDate <= :sdate)
                                 AND (congregationId = :cong)');
        $this->db->bind(':sdate',$date);
        $this->db->bind(':cong',$_SESSION['congId']);
        $credits = $this->db->getValue();
        return floatval($debits) - floatval($credits);
    }
    public function GetLiabilityEquityTotal($date)
    {
        $this->db->query('SELECT IFNULL(SUM(debit),0) as sumofdebits 
                          FROM   tblledger 
                          WHERE  (accountId=4 OR accountId = 6) AND (transactionDate <= :sdate)
                                 AND (congregationId = :cong)');
        $this->db->bind(':sdate',$date);
        $this->db->bind(':cong',$_SESSION['congId']);                  
        $debits = $this->db->getValue();

        $this->db->query('SELECT IFNULL(SUM(credit),0) as sumofcredits 
                          FROM   tblledger 
                          WHERE  (accountId=4 OR accountId = 6) AND (transactionDate <= :sdate)
                                 AND (congregationId = :cong)');
        $this->db->bind(':sdate',$date);
        $this->db->bind(':cong',$_SESSION['congId']);
        $credits = $this->db->getValue();
        return (floatval($debits) * -1) - (floatval($credits) * -1);
    }
    public function GetNetIncome($date)
    {
        $this->db->query('SELECT IFNULL(SUM(debit),0) 
                          FROM   tblledger 
                          WHERE  (accountId=1) AND transactionDate <= :sdate
                                 AND congregationId = :cong');
        $this->db->bind(':sdate',$date);
        $this->db->bind(':cong',$_SESSION['congId']);
        $revenueDebit = $this->db->getValue();  
        //===========================
        $this->db->query('SELECT IFNULL(SUM(credit),0) 
                          FROM   tblledger 
                          WHERE  (accountId=1) AND transactionDate <= :sdate
                                 AND (congregationId = :cong)');
        $this->db->bind(':sdate',$date);
        $this->db->bind(':cong',$_SESSION['congId']);
        $revenueCredit = $this->db->getValue();    
        //================================================
        $revenueBalance = (floatval($revenueDebit) - floatval($revenueCredit)) *-1;
        //=========================================================
        $this->db->query('SELECT IFNULL(SUM(debit),0) 
                          FROM   tblledger 
                          WHERE  (accountId=2) AND transactionDate <= :sdate 
                                 AND (congregationId = :cong)');
        $this->db->bind(':sdate',$date);
        $this->db->bind(':cong',$_SESSION['congId']);
        $expensesDebit = $this->db->getValue();  
        //===========================
        $this->db->query('SELECT IFNULL(SUM(credit),0) 
                          FROM   tblledger 
                          WHERE  (accountId=2) AND transactionDate <= :sdate 
                                 AND (congregationId = :cong)');
        $this->db->bind(':sdate',$date);
        $this->db->bind(':cong',$_SESSION['congId']);
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
    public function pettycashutil($data)
    {
        $this->db->query('CALL sp_pettycashutilization(:sdate,:edate,:congid)');
        $this->db->bind(':sdate',$data['start']);
        $this->db->bind(':edate',$data['end']);
        $this->db->bind(':congid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function debitcredittotal($data)
    {
        $debitcredit = array();
        $this->db->query('SELECT IFNULL(SUM(debit),0) As DebitsTotal 
                          FROM tblpettycash WHERE (TransactionDate BETWEEN :startd AND :endd) AND (CongregationId = :congid)');
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        $this->db->bind(':congid',$_SESSION['congId']);
        array_push($debitcredit,$this->db->getValue());

        $this->db->query('SELECT IFNULL(SUM(credit),0) As DebitsTotal 
                          FROM tblpettycash WHERE (TransactionDate BETWEEN :startd AND :endd) AND (CongregationId = :congid)');
        $this->db->bind(':startd',$data['start']);
        $this->db->bind(':endd',$data['end']);
        $this->db->bind(':congid',$_SESSION['congId']);
        array_push($debitcredit,$this->db->getValue());

        $this->db->query('SELECT getopeningbal(:sdate,:congid) As OpeningBal');
        $this->db->bind(':sdate',$data['start']);
        $this->db->bind(':congid',$_SESSION['congId']);
        array_push($debitcredit,$this->db->getValue());

        return $debitcredit;
    }
    public function getcustomersupplier($type)
    {
        if($type === 'customer'){
            $this->db->query('SELECT ID, UCASE(customerName) AS criteria 
                              FROM tblcustomers 
                              WHERE (congregationId = :cong) AND (deleted=0)
                              ORDER BY criteria');
        }else{
            $this->db->query('SELECT ID, UCASE(supplierName) AS criteria 
                              FROM tblsuppliers 
                              WHERE (congregationId = :cong) AND (deleted=0)
                              ORDER BY criteria');
        }
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getpaymentreport($data)
    {
        if($data['type'] === 'customer'){
            $this->db->query('CALL sp_getcustomerpayments(:cid,:sdate,:edate)');
        }else{
            $this->db->query('CALL sp_getsupplierpayments(:cid,:sdate,:edate)');
        }
        $this->db->bind(':cid',(int)$data['customer']);
        $this->db->bind(':sdate',$data['start']);
        $this->db->bind(':edate',$data['end']);
        return $this->db->resultSet();
    }
    public function getgroupstatement($reqid)
    {
       return loadresultset($this->db->dbh,'CALL getgroupstatement(?)',[$reqid]);
    }
    public function GetAccountType($account)
    {
        return getdbvalue($this->db->dbh,'SELECT accountTypeId FROM tblaccounttypes WHERE (accountType = ?)',[$account]);
    }
    public function GetPlDetailed($data)
    {
        if((int)$data['accounttype'] === 1)
        {
            $sql = 'SELECT transactionDate,account,(IFNULL(credit,0) - IFNULL(debit,0)) as amount,narration,t.TransactionType,l.parentaccount
                    FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID
                    WHERE (parentaccount = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0)
                    ORDER BY transactionDate';
            return loadresultset($this->db->dbh,$sql,[$data['account'],$data['sdate'],$data['edate']]);
        }elseif ((int)$data['accounttype'] === 2) {
            $sql = 'SELECT transactionDate,account,(IFNULL(debit,0) - IFNULL(credit,0)) as amount,narration,t.TransactionType,l.parentaccount
                    FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID
                    WHERE (parentaccount = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0)
                    ORDER BY transactionDate';
            return loadresultset($this->db->dbh,$sql,[$data['account'],$data['sdate'],$data['edate']]);
        }
        
    }
    public function GetPlChildAccountDetailed($data)
    {
        if((int)$data['accounttype'] === 1)
        {
            $sql = 'SELECT transactionDate,account,(IFNULL(credit,0) - IFNULL(debit,0)) as amount,narration,t.TransactionType,l.parentaccount
                    FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID
                    WHERE (account = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0)
                    ORDER BY transactionDate';
            return loadresultset($this->db->dbh,$sql,[$data['account'],$data['sdate'],$data['edate']]);
        }elseif ((int)$data['accounttype'] === 2) {
            $sql = 'SELECT transactionDate,account,(IFNULL(debit,0) - IFNULL(credit,0)) as amount,narration,t.TransactionType,l.parentaccount
                    FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID
                    WHERE (account = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0)
                    ORDER BY transactionDate';
            return loadresultset($this->db->dbh,$sql,[$data['account'],$data['sdate'],$data['edate']]);
        }
        
    }
    public function GetGroupRevenues($data)
    {
        $sql = 'SELECT IFNULL(SUM(AmountApproved),0) AS Amount FROM tblfundrequisition WHERE (Deleted=0) AND (DontDeduct=1) AND (Status=1) AND (GroupId=?) AND (ApprovalDate BETWEEN ? AND ?)';
        return getdbvalue($this->db->dbh,$sql,[$data['group'],$data['start'],$data['end']]);
    }

    public function GetGroupCollections($data)
    {
        $sql = 'SELECT IFNULL(SUM(Debit),0) AS Amount FROM tblmmf WHERE (Deleted=0) AND (GroupId=?) AND (TransactionDate BETWEEN ? AND ?)';
        return getdbvalue($this->db->dbh,$sql,[$data['group'],$data['start'],$data['end']]);
    }

    public function GetGroupExpensesPL($data)
    {
        $sql = 'SELECT 
                    a.accountType,
                    a.ID,
                    SUM(`amount`) AS Amount
                FROM `tblexpenses` e join tblaccounttypes a on e.accountId = a.ID
                WHERE e.groupId = ? AND (e.expenseDate BETWEEN ? AND ?) AND (e.deleted = 0) AND (e.status=1)
                GROUP BY a.accountType,a.ID';
        return loadresultset($this->db->dbh,$sql,[(int)$data['group'],$data['start'],$data['end']]);
    }

    public function GetGroupPlExpenseDetailed($data)
    {
        $sql = 'SELECT 
                    e.expenseDate,
                    e.amount,
                    e.paymentReference,
                    e.narration
                FROM `tblexpenses` e 
                WHERE e.groupId = ? AND (e.expenseDate BETWEEN ? AND ?) AND (e.deleted = 0) AND (e.status=1) AND (e.accountId=?)';

        return loadresultset($this->db->dbh,$sql,[(int)$data['group'],$data['sdate'],$data['edate'],$data['account']]);
    }

    public function GetGroupPlRevenueDetailed($data)
    {
        $sql = '';
        if($data['type'] === 'collections')
        {
            $sql .= 'SELECT TransactionDate,Debit As Amount,Narration 
                     FROM tblmmf 
                     WHERE (Deleted=0) AND (GroupId=?) AND (TransactionDate BETWEEN ? AND ?)';
        }
        else
        {
            $sql .= 'SELECT ApprovalDate As TransactionDate,AmountApproved As Amount,Purpose As Narration 
                     FROM tblfundrequisition 
                     WHERE (Deleted=0) AND (GroupId=?) AND (DontDeduct=1) AND (ApprovalDate BETWEEN ? AND ?)';
        }

        return loadresultset($this->db->dbh,$sql,[$data['group'],$data['sdate'],$data['edate']]);
    }

    function GetAccountTypeId($account)
    {
        return getdbvalue($this->db->dbh,'SELECT accountTypeId 
        FROM tblaccounttypes 
        WHERE LOWER(accountType) = ? ',[strtolower($account)]);
    }

    public function GetBalanceSheetItemOpeningBalance($data)
    {
        $accountType = $this->GetAccountType($data['account']);

        $yearStartDate = getdbvalue($this->db->dbh,'SELECT startDate FROM tblfiscalyears 
                                                              WHERE ? BETWEEN startDate AND endDate',[$data['asdate']]);                                                    
        $openingBalance = 0;
        if((int)$accountType === 3){
            $sql = "SELECT  IFNULL(SUM(debit - credit),0) AS OpeningBalance 
                    FROM tblledger 
                    WHERE (transactionDate < ?) AND (LOWER(account) = ?) AND (congregationId = ?) AND (deleted = 0)";
            $openingBalance = getdbvalue($this->db->dbh,$sql,[$yearStartDate,$data['account'],$_SESSION['congId']]);
        }else{
            $sql = 'SELECT  IFNULL(SUM(credit - debit),0) AS OpeningBalance 
                    FROM tblledger 
                    WHERE (transactionDate < ?) AND (LOWER(account) = ?) AND (congregationId = ?) AND (deleted = 0)';
        }
        
        $openingBalance = getdbvalue($this->db->dbh,$sql,[$yearStartDate,strtolower($data['account']),$_SESSION['congId']]);

        return ['openingBalance' => $openingBalance,'yearStartDate' => $yearStartDate,'accountTypeId' => $accountType];
    }

    public function GetDetailedBalanceSheetAccountReport($data)
    {
        $accountType = $this->GetAccountType($data['account']);
        $sql = '';
        if($accountType == 3){
            $sql = 'SELECT l.account, SUM(l.debit - l.credit) AS balance 
                     FROM tblledger l 
                     WHERE l.transactionDate <= ? AND l.congregationId = ? AND LOWER(l.parentaccount) = ? AND l.deleted = 0  
                     GROUP BY account;';
        }else{
            $sql = 'SELECT l.account, SUM(l.credit - l.debit) AS balance 
                     FROM tblledger l 
                     WHERE l.transactionDate <= ? AND l.congregationId = ? AND LOWER(l.parentaccount) = ? AND l.deleted = 0  
                     GROUP BY account;';
        }     
        return loadresultset($this->db->dbh,$sql,[$data['asdate'],$_SESSION['congId'],strtolower($data['account'])]);
    }

    public function GetChildDetailedBalanceSheetAccountReport($data)
    {
        try {
            if((int)$data['accountTypeId'] === 3){
                $sql = 'SELECT l.transactionDate,l.account,SUM(l.debit - l.credit) AS amount,l.narration,t.TransactionType 
                        FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID 
                        WHERE (LOWER(account) = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0) AND (l.congregationId=?)
                        ORDER BY transactionDate';
            }else{
                $sql = 'SELECT l.transactionDate,l.account,SUM(l.credit - l.debit) AS amount,l.narration,t.TransactionType 
                        FROM tblledger l left join tbltransactiontypes t on l.transactionType = t.ID 
                        WHERE (LOWER(account) = ?) AND (transactionDate BETWEEN ? AND ?) AND (l.deleted = 0) AND (l.congregationId=?)
                        ORDER BY transactionDate';
            }

            return loadresultset($this->db->dbh,$sql,[$data['account'],$data['yearStartDate'],$data['asdate'],$_SESSION['congId']]);
        } catch (\Exception $eh) {
            error_log($eh->getMessage());
            return false;
        }
    }

    public function GetJournalReport($data)
    {
        $this->db->query('SELECT journalNo,
                                 transactionDate,
                                 account,
                                 debit,
                                 credit,
                                 narration
                          FROM   tblledger
                          WHERE  (transactionDate BETWEEN :tstart AND :tend) AND (congregationId = :cid) 
                                 AND (isJournal=1)
                          ORDER BY transactionDate');
        $this->db->bind(':tstart',$data['from']);
        $this->db->bind(':tend',$data['to']);
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetSubaccounts()
    {
        return loadresultset($this->db->dbh,'SELECT s.ID,ucase(s.AccountName) as AccountName
                                             FROM `tblbanksubaccounts` s join tblaccounttypes a on s.BankId = a.ID WHERE
                                             (s.Deleted=0) AND  (a.congregationId = ?);',[$_SESSION['congId']]);
    }

    public function GetSubAccountReport($data)
    {
        if($data['account'] === 'all'){
            return loadresultset($this->db->dbh,'CALL sp_get_subaccounts_balances(?)',[$data['from']]);
        }else{
            $sql = "SELECT IF(GroupId IS NULL,'district','group') As Category, IF(GroupId IS NULL,DistrictId,GroupId) As CategoryId 
                    FROM `tblbanksubaccounts` 
                    WHERE ID = ?;";
            $details = loadsingleset($this->db->dbh,$sql,[$data['account']]);
            // $sql = "CALL sp_get_subaccount_statement(?,?,?)";
            if($details->Category === 'group'){
                $sql = "CALL sp_get_subaccount_statement_with_expense_group(?,?,?,?)";
            }else{
                $sql = "CALL sp_get_subaccount_statement_with_expense_district(?,?,?,?)";
            }
            return loadresultset($this->db->dbh,$sql,[$data['from'],$data['to'],$data['account'],$details->CategoryId]);
        }
        
    }

    public function GetDetailedSubAccountReport($data)
    {
        $sql = 'SELECT GroupDistrict,IF(GroupId IS NULL,DistrictId,GroupId) As CategoryId 
                FROM `tblbanksubaccounts` 
                WHERE ID = ?;';
        $details = loadsingleset($this->db->dbh,$sql,[$data['account']]);
        
        // $sql = 'SELECT 
        //             TransactionDate,
        //             Amount,
        //             Narration,
        //             Reference 
        //         FROM 
        //             tblbanktransactions_subaccounts
        //         WHERE
        //             (TransactionDate <= ?) AND (SubAccountId = ?)';
        // return loadresultset($this->db->dbh,$sql,[$data['asdate'],$data['account']]);
        if($details->GroupDistrict === 'group'){
            $sql = 'CALL sp_get_subaccounts_balances_breakdown_group(?,?,?)';
            return loadresultset($this->db->dbh,$sql,[$data['asdate'],$details->CategoryId, $data['account']]);
        }else{
            $sql = 'CALL sp_get_subaccounts_balances_breakdown_district(?,?,?)';
            return loadresultset($this->db->dbh,$sql,[$data['asdate'],$details->CategoryId, $data['account']]);
        }
    }

    public function GetAccountStatement($data)
    {
        $details = loadsingleset($this->db->dbh,'SELECT LOWER(accountType) as accountType,isSubCategory FROM tblaccounttypes WHERE (ID = ?)',[$data['account']]);
        $account = $details->accountType;
        $isSubcategory = converttobool($details->isSubCategory);
        if($isSubcategory){
            $sql = "CALL sp_get_account_statement(?,?,?,?)";
        }else{
            if($data['subledgers'] == 1){
                $sql = "CALL sp_get_account_statement_parent(?,?,?,?)";
            }else{
                $sql = "CALL sp_get_account_statement(?,?,?,?)";
            }            
        }
        return loadresultset($this->db->dbh,$sql,[$data['from'],$data['to'],$account,(int)$_SESSION['congId']]);
    }

    public function GetUnaccountedReport($data)
    {
        if($data['criteria'] === 'all'){
            $sql = "SELECT 
                        `RequisitionDate`,
                        ucase(IF(r.RequestType = 'group',g.groupName,d.districtName)) as GroupDistrict,
                        `AmountRequested`,
                        `AmountApproved`,
                        getrequisitionbalance(r.ID) AS UnaccountedAmount
                    FROM `tblfundrequisition` r
                        left join tbldistricts d on r.DistrictId = d.ID left join tblgroups g on r.GroupId = g.ID
                    WHERE r.Status = 1";     
            return loadresultset($this->db->dbh,$sql,[]);
        }else{
            $sql = "SELECT 
                        `RequisitionDate`,
                        ucase(IF(r.RequestType = 'group',g.groupName,d.districtName)) as GroupDistrict,
                        `AmountRequested`,
                        `AmountApproved`,
                        getrequisitionbalance(r.ID) AS UnaccountedAmount
                    FROM `tblfundrequisition` r
                        left join tbldistricts d on r.DistrictId = d.ID left join tblgroups g on r.GroupId = g.ID
                    WHERE (r.Status = 1) AND (r.RequestType = ?)";   
            return loadresultset($this->db->dbh,$sql,[$data['criteria']]); 
        }
        
        
    }
}

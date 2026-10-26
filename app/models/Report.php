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
    /**
     * Report-safe account list of the session congregation for one root type (task 9.1): shared + own accounts by ID with a
     * path label, no `active` filter, deleted ones only with history (both labelled). ID and accountType keep the view contract.
     */
    public function GetAccounts($id)
    {
        return array_map(function($a){ return (object)['ID' => $a['id'],'accountType' => $a['label']]; },
            FinancialReport::accountOptions($this->db->pdo(),[(int)$_SESSION['congId']],['roots' => [(int)$id]]));
    }
    /** IDs of GetAccounts($root): a posted account must be one of them. @return int[] */
    public function reportAccountIds($root)
    {
        return array_map(function($a){ return $a['id']; },
            FinancialReport::accountOptions($this->db->pdo(),[(int)$_SESSION['congId']],['roots' => [(int)$root]]));
    }
    /** "?,?,?" for a list of IDs */
    private function marks(array $ids)
    {
        return implode(',',array_fill(0,count($ids),'?'));
    }
    /** Account name as the report screens show it (decision 149), from the report-safe chart of the session congregation. */
    private function accountLabels()
    {
        $labels = [];
        foreach (FinancialReport::chart($this->db->pdo(),[(int)$_SESSION['congId']])['nodes'] as $id => $n) {
            $labels[(int)$id] = FinancialReport::title($n['label']);
        }
        return $labels;
    }
    /**
     * Contributions of the session congregation (task 9.7, decisions 135-136). Only approved (status = 1), not deleted headers; every
     * detail line is joined to ITS header (the join key was missing: the filtered query was a cross join and the routine
     * the old contributions routine still is one). Types: 1 all, 2 the accounts in $data['account'], 3 totals per district (by ID).
     * Amounts are integer cents (the column is FLOAT; it is rounded to the cent). Rows are arrays for ReportList.
     * @return array[]
     */
    public function GetContributions($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        if ($data['type'] == 3) {
            $st = $pdo->prepare('SELECT c.contributotDistrict AS districtId, t.districtName AS name, SUM(CAST(ROUND(c.amount * 100) AS SIGNED)) AS cents
                                 FROM tblcontributions_details c
                                      JOIN tblcontributions_header h ON h.ID = c.HeaderId
                                      LEFT JOIN tbldistricts t ON t.ID = c.contributotDistrict
                                 WHERE c.category = 3 AND h.Deleted = 0 AND h.status = 1 AND h.congregationId = ? AND c.contributionDate BETWEEN ? AND ?
                                 GROUP BY c.contributotDistrict, t.districtName
                                 ORDER BY t.districtName, c.contributotDistrict');
            $st->execute([$cong,$data['start'],$data['end']]);
            return array_map(function($r){
                return ['district' => $r['name'] === null ? 'NO DISTRICT' : strtoupper($r['name']),'amount' => (int)$r['cents']];
            },$st->fetchAll(PDO::FETCH_ASSOC));
        }
        $accounts = $data['type'] == 2 ? array_values(array_map('intval',$data['account'])) : [];
        $sql = "SELECT d.ID, d.contributionDate AS cdate, d.contributionTypeId AS accountId,
                       UCASE(IF(d.category = 1,m.memberName,IF(d.category = 2,g.groupName,IF(d.category = 3,t.districtName,s.serviceName)))) AS cont,
                       CAST(ROUND(d.amount * 100) AS SIGNED) AS cents, UCASE(p.paymentMethod) AS paymethod, UCASE(d.paymentReference) AS paymentReference,
                       h.ID AS headerId, h.receiptNo
                FROM   tblcontributions_details d
                       JOIN tblcontributions_header h ON h.ID = d.HeaderId
                       LEFT JOIN tblmember m ON d.contributor = m.ID
                       LEFT JOIN tblgroups g ON d.contributotGroup = g.ID
                       LEFT JOIN tbldistricts t ON d.contributotDistrict = t.ID
                       LEFT JOIN tblservices s ON d.contributotService = s.ID
                       LEFT JOIN tblpaymentmethods p ON d.paymentMethodId = p.ID
                WHERE  h.Deleted = 0 AND h.status = 1 AND h.congregationId = ? AND d.contributionDate BETWEEN ? AND ?"
                . ($accounts ? ' AND d.contributionTypeId IN (' . $this->marks($accounts) . ')' : '')
                . ' ORDER BY d.contributionDate, d.ID';
        $st = $pdo->prepare($sql);
        $st->execute(array_merge([$cong,$data['start'],$data['end']],$accounts));
        $labels = $this->accountLabels();
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'date' => $r['cdate'],'receipt' => (string)$r['receiptNo'],'account' => $labels[(int)$r['accountId']] ?? '','cont' => (string)$r['cont'],
                'amount' => (int)$r['cents'],'method' => (string)$r['paymethod'],'reference' => (string)$r['paymentReference'],
                'link' => FinancialReport::sourceLink(1,(int)$r['headerId'])
            ];
        }
        return $out;
    }
    /**
     * Expenses of the session congregation: approved (status = 1), not deleted. Types: 1 all, 2 the accounts in $data['account'].
     * Cost centre: LCC (church expense), the group's or the district's name.
     * @return array[]
     */
    public function GetExpenses($data)
    {
        $accounts = $data['type'] == 2 ? array_values(array_map('intval',$data['account'])) : [];
        $sql = "SELECT e.ID, e.expenseDate, e.voucherNo, e.accountId, UCASE(CASE e.expenseType WHEN 1 THEN 'lcc' WHEN 2 THEN g.groupName ELSE d.districtName END) AS costcenter,
                       CAST(ROUND(e.amount * 100) AS SIGNED) AS cents, UCASE(p.paymentMethod) AS paymethod, UCASE(e.paymentReference) AS payref
                FROM   tblexpenses e
                       LEFT JOIN tblpaymentmethods p ON e.paymethodId = p.ID
                       LEFT JOIN tblgroups g ON e.groupId = g.ID
                       LEFT JOIN tbldistricts d ON e.districtId = d.ID
                WHERE  e.congregationId = ? AND e.expenseDate BETWEEN ? AND ? AND e.status = 1 AND e.deleted = 0"
                . ($accounts ? ' AND e.accountId IN (' . $this->marks($accounts) . ')' : '')
                . ' ORDER BY e.expenseDate, e.ID';
        $st = $this->db->pdo()->prepare($sql);
        $st->execute(array_merge([(int)$_SESSION['congId'],$data['start'],$data['end']],$accounts));
        $labels = $this->accountLabels();
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'date' => $r['expenseDate'],'voucher' => (string)$r['voucherNo'],'account' => $labels[(int)$r['accountId']] ?? '','costcenter' => (string)$r['costcenter'],
                'amount' => (int)$r['cents'],'method' => (string)$r['paymethod'],'reference' => (string)$r['payref'],
                'link' => FinancialReport::sourceLink(2,(int)$r['ID'])
            ];
        }
        return $out;
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
            $this->db->query('CALL sp_getcustomerpayments(:cid,:sdate,:edate,:cong)');
            $this->db->bind(':cong',(int)$_SESSION['congId']);
        }else{
            $this->db->query('CALL sp_getsupplierpayments(:cid,:sdate,:edate,:cong)');
            $this->db->bind(':cong',(int)$_SESSION['congId']);
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

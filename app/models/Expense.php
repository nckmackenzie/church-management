<?php
class Expense {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    public function getExpenses()
    {
        // $this->db->query('SELECT * FROM vw_getexpenses WHERE (congregationId=:cid)
        //                   AND (deleted=0)');
        $this->db->query('SELECT * FROM vw_getexpenses_2 WHERE (congregationId=:cid)');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();                  
    }

    public function GetVoucherNo($yearId)
    {
        $to_prefix = converttobool(getdbvalue($this->db->dbh,'SELECT prefixReferences from tblfiscalyears WHERE ID=?',[$yearId]));
        if ($to_prefix){
            $sql = "SELECT IFNULL(MAX(RIGHT(voucherNo,3)),'000') AS voucherNo 
                    FROM tblexpenses WHERE congregationId=? AND fiscalYearId=?";
            return format_string(intval(getdbvalue($this->db->dbh,$sql,[$_SESSION['congId'],$yearId])) + 1);
        }else{
            $sql = 'SELECT IFNULL(voucherNo,1) AS voucherNo 
                    FROM tblexpenses 
                    WHERE congregationId=? AND fiscalYearId=?
                    ORDER BY ID DESC
                    LIMIT 1';
            return intval(getdbvalue($this->db->dbh,$sql,[$_SESSION['congId'],$yearId])) + 1;
        }
    }

    public function GetAccounts($type)
    {
        if($type == 1){
            $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                              WHERE ((accountTypeId = :expense) OR (accountTypeId = :asset) OR (accountTypeId = :liability)) 
                             AND (deleted=0) AND (isBank = 0) AND (parentId <> 0) AND (active = 1)
                             AND (isSubCategory=1) AND (congregationId = 0 OR congregationId = :cong) ORDER BY accountType');
        }else{
            $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                                   WHERE ((accountTypeId = :expense) OR (accountTypeId = :asset) OR (accountTypeId = :liability)) 
                                   AND (deleted=0) AND (isBank = 0) AND (parentId <> 0) AND (forGroup = 1) 
                                   AND (active = 1) AND (isSubCategory=1) AND (congregationId = 0 OR congregationId = :cong) ORDER BY accountType');
        }
        $this->db->bind(':expense',2);
        $this->db->bind(':asset',3);
        $this->db->bind(':liability',4);
        $this->db->bind(':cong',$_SESSION['congId']);
        return $this->db->resultSet();                  
    }
    
    public function VoucherNo()
    {
       $this->db->query('SELECT voucherNo FROM tblexpenses
                        WHERE (congregationId=:cid) ORDER BY voucherNo DESC LIMIT 1'); 
      $this->db->bind(':cid',$_SESSION['congId']);
      return ($this->db->getValue()) + 1;
    }
   
    public function getGroup()
    {
        if ($_SESSION['isParish'] == 1) {
            $this->db->query("SELECT g.ID,
                                     CONCAT(ucase(g.groupName),'-',ucase(c.CongregationName)) as groupName
                             FROM tblgroups g inner join tblcongregation c on g.congregationId=c.ID       
                             WHERE g.active = 1 AND g.deleted=0
                             ORDER BY groupName");
            return $this->db->resultSet();                 
        }else{
            $this->db->query('SELECT ID,ucase(groupName) as groupName
                          FROM tblgroups WHERE (active=1) AND (deleted=0)
                               AND (congregationId=:cid)
                          ORDER BY groupName');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet(); 
        }
    }

    public function GetGroupOrDistrict($category)
    {
        if ($category === 2) {
            $this->db->query('SELECT ID,ucase(groupName) as cost_center
                              FROM tblgroups WHERE (active=1) AND (deleted=0)
                                   AND (congregationId=:cid)
                              ORDER BY cost_center');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();                 
        }else{
            $this->db->query('SELECT ID,ucase(districtName) as cost_center
                              FROM tbldistricts WHERE (deleted=0)
                               AND (congregationId=:cid)
                          ORDER BY cost_center');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet(); 
        }
    }


    public function CheckOverSpent($data)
    {
        $yearid = getdbvalue($this->db->dbh,'SELECT getyearidbydate(?)',[$data['edate']]);
        $budgetedexists = 0;
        $budgetedamount = 0;
        $expensedamount = 0;
        if($data['type'] === 1) :
            $sql = 'SELECT COUNT(*) FROM tblchurchbudget_header WHERE (yearId = ?) AND (congregationId=?)';
            $budgetedexists = getdbvalue($this->db->dbh,$sql,[$yearid,$_SESSION['congId']]);
        elseif($data['type'] === 2) :
            $sql = 'SELECT COUNT(*) FROM tblgroupbudget_header WHERE (fiscalYearId = ?) AND (groupId=?)';
            $budgetedexists = getdbvalue($this->db->dbh,$sql,[$yearid,$data['gid']]);
        endif;

        if((int)$budgetedexists === 0) return false; //no bduget found
        
        if($data['type'] === 1) :
            $sql = 'SELECT getbudgetedamount_lcc(?,?,?) As amount';
            $budgetedamount = getdbvalue($this->db->dbh,$sql,[$data['aid'],$yearid,$_SESSION['congId']]);

            $sql2 = 'SELECT getexpensedamount(?,?,?) AS amount';
            $expensedamount = getdbvalue($this->db->dbh,$sql2,[$data['aid'],$data['edate'],$_SESSION['congId']]);
        elseif($data['type'] === 2) :
            $sql = 'SELECT getbudgetedamount(?,?,?) As amount';
            $budgetedamount = getdbvalue($this->db->dbh,$sql,[$data['aid'],$yearid,$data['gid']]);

            $sql2 = 'SELECT getgroupexpensedamount(?,?,?,?) AS amount';
            $expensedamount = getdbvalue($this->db->dbh,$sql2,[$data['aid'],$data['edate'],$data['gid'],$_SESSION['congid']]);
        endif;

        if(floatval($expensedamount) > floatval($budgetedamount)) return false;

        return true;
        
    }
    
    public function create($data)
    {
        //get names
        $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$data['account']);
        $accountname = $this->db->getValue();
        //
        $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$data['account']);
        $accountid = $this->db->getValue();
        //
        $id = getLastId($this->db->dbh,'tblexpenses');
        $fid = getYearId($this->db->dbh,$data['date']);
        $today = date('Y-m-d');
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblexpenses (ID,fiscalYearId,voucherNo,expenseType,expenseDate,
                                          accountId,groupId,districtId,paymethodId,deductfrom,bankId,amount,narration,
                                          paymentReference,`status`,hasAttachment,`fileName`,requisitionId,postedBy,postedDate,congregationId)
                              VALUES(:id,:fid,:vno,:etype,:edate,:aid,:gid,:did,:pid,:dfrom,:bid,:amount,:narr,:ref,
                                    :stat,:hasattach,:fname,:reqid,:post,:pdate,:cid)');
            $this->db->bind(':id',$id);
            $this->db->bind(':fid',$fid);                        
            $this->db->bind(':vno',$data['voucher']);                        
            $this->db->bind(':etype',$data['expensetype']);                        
            $this->db->bind(':edate',$data['date']);                        
            $this->db->bind(':aid',$data['account']);                        
            $this->db->bind(':gid',$data['expensetype'] == 2 ? $data['costcentre'] : NULL); 
            $this->db->bind(':did',$data['expensetype'] == 3 ? $data['costcentre'] : NULL); 
            $this->db->bind(':pid',$data['paymethod']);
            $this->db->bind(':dfrom',!empty($data['deductfrom']) ? $data['deductfrom'] : NULL);
            $this->db->bind(':bid',$data['bank']);                        
            $this->db->bind(':amount',$data['amount']);                        
            $this->db->bind(':narr',strtolower($data['description']));                        
            $this->db->bind(':ref',strtolower($data['reference']));                        
            $this->db->bind(':stat',0);
            $this->db->bind(':hasattach',$data['hasattachment']);   //to add                     
            $this->db->bind(':fname',$data['hasattachment'] === 1 ? $data['filename'] : NULL);   //to add 
            $this->db->bind(':reqid', $data['reqid']);    
            $this->db->bind(':post',$_SESSION['userId']);                        
            $this->db->bind(':pdate',$today);  
            $this->db->bind(':cid',$_SESSION['congId']);  
            $this->db->execute();


            $act = 'Created Expense For '.$data['date'];
            saveLog($this->db->dbh,$act);
            if ($this->db->dbh->commit()) {
                return true;
            }
            else{
                return false;
            }             
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
        }
    }

    function approve_expense($data)
    {

       try {
            
            $this->db->dbh->beginTransaction();

            $this->db->query('UPDATE tblexpenses SET `status` = 1 WHERE (ID=:id)');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();

            $expense = loadsingleset($this->db->dbh,'SELECT * FROM tblexpenses WHERE ID=?',[(int)$data['id']]);

            $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
            $this->db->bind(':id',$expense->accountId);
            $accountname = $this->db->getValue();
            //
            $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
            $this->db->bind(':id',$expense->accountId);
            $accountid = $this->db->getValue();

            $this->db->query('DELETE FROM tblpettycash WHERE (ExpenseId=:id)');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();

            if((int)$expense->paymethodId === 1 && $expense->deductfrom === 'petty cash'){
                $this->db->query('INSERT INTO tblpettycash (TransactionDate,Credit,IsReceipt,Reference,Narration,ExpenseId,CongregationId)
                                  VALUES(:tdate,:credit,:isreceipt,:ref,:narr,:eid,:cid)');
                $this->db->bind(':tdate',date('Y-m-d',strtotime($expense->expenseDate)));
                $this->db->bind(':credit',$expense->amount);
                $this->db->bind(':isreceipt',false);
                $this->db->bind(':ref', !is_null($expense->paymentReference) ? strtolower($expense->paymentReference) : null);
                $this->db->bind(':narr',!is_null($expense->narration) ? strtolower($expense->narration) : null);
                $this->db->bind(':eid',$data['id']);
                $this->db->bind(':cid',$_SESSION['congId']);
                $this->db->execute();
            }

            deleteLedgerBanking($this->db->dbh,2,$data['id']);

            $cashparent = getparentgl($this->db->dbh,'cash at bank');
            $accparent = getparentgl($this->db->dbh,$accountname);

            if(!is_null($expense->requisitionId)){
                saveToLedger($this->db->dbh,$expense->expenseDate,$accountname,$accparent,$expense->amount,0,$expense->narration,
                              $accountid,2,$data['id'],$_SESSION['congId'],$expense->paymentReference);
                saveToLedger($this->db->dbh,$expense->expenseDate,'cash holding account',$cashparent,0,$expense->amount,$expense->narration,
                              3,2,$data['id'],$_SESSION['congId'],$expense->paymentReference);
            }else{
                if($expense->deductfrom == 'petty cash' || $expense->deductfrom == 'cash at hand' || is_null($expense->deductfrom) || is_null($expense->requisitionId) ){
                    saveToLedger($this->db->dbh,$expense->expenseDate,$accountname,$accparent,$expense->amount,0,$expense->narration,
                                $accountid,2,$data['id'],$_SESSION['congId'],$expense->paymentReference);
                }
                if($expense->paymethodId == 1 && $expense->deductfrom === 'petty cash'){
                    saveToLedger($this->db->dbh,$expense->expenseDate,'petty cash',$cashparent,0,$expense->amount,$expense->narration,
                                    3,2,$data['id'],$_SESSION['congId'],$expense->paymentReference);
                }elseif ($expense->paymethodId == 1 && $expense->deductfrom === 'cash at hand') {
                    saveToLedger($this->db->dbh,$expense->expenseDate,'cash at hand',$cashparent,0,$expense->amount,$expense->narration,
                                    3,2,$data['id'],$_SESSION['congId'],$expense->paymentReference);
                }elseif ($expense->paymethodId == 1 && $expense->deductfrom === 'cash holding account') {
                    saveToLedger($this->db->dbh,$expense->expenseDate,'cash holding account',$cashparent,0,$expense->amount,$expense->narration,
                                    3,2,$data['id'],$_SESSION['congId'],$expense->paymentReference);
                }elseif($expense->paymethodId == 2){
                    saveToLedger($this->db->dbh,$expense->expenseDate,'cash at bank',$cashparent,0,$expense->amount,$expense->narration,
                                    3,2,$data['id'],$_SESSION['congId'],$expense->paymentReference);
                }elseif ((int)$expense->paymethodId > 2) {
                    saveToLedger($this->db->dbh,$expense->expenseDate,'cash at bank',$cashparent,0,$expense->amount,$expense->narration,
                                    3,2,$data['id'],$_SESSION['congId'],$expense->paymentReference);
                    saveToBanking($this->db->dbh,$expense->bankId,$expense->expenseDate,0,$expense->amount,2,
                                $expense->paymentReference,2,$data['id'],$_SESSION['congId']);             
                }
            }

            $act = 'Approved Expense For '.$data['date'] . ' Voucher No ' .$data['voucher'];
            saveLog($this->db->dbh,$act);

            if ($this->db->dbh->commit()) {
                return true;
            }
            else{
                return false;
            }     


        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
            return false;
        }
    }

    public function approve($data)
    {
        return $this->approve_expense($data);
    }
    public function getExpense($id)
    {
        $this->db->query('SELECT * FROM tblexpenses WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function getExpenseFull($id)
    {
        $this->db->query('SELECT * FROM vw_expensevoucher_2 WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
 
    public function update($data)
    {
        //get names
        $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$data['account']);
        $accountname = $this->db->getValue();
        //
        $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$data['account']);
        $accountid = $this->db->getValue();

        $fid = getYearId($this->db->dbh,$data['date']);
        
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblexpenses SET fiscalYearId=:fid,expenseType=:etype,expenseDate
                                     =:edate,accountId=:aid,groupId=:gid,districtId=:did,paymethodId=:pid,deductfrom=:dfrom,bankId=:bid
                                     ,amount=:amount,narration=:narr,paymentReference=:ref,requisitionId=:reqid
                              WHERE (ID=:id)');
            $this->db->bind(':fid',$fid);                        
            $this->db->bind(':etype',$data['expensetype']);                        
            $this->db->bind(':edate',$data['date']);                        
            $this->db->bind(':aid',$data['account']);                        
            $this->db->bind(':gid',$data['expensetype'] == 2 ? $data['costcentre'] : NULL); 
            $this->db->bind(':did',$data['expensetype'] == 3 ? $data['costcentre'] : NULL); 
            $this->db->bind(':pid',$data['paymethod']);
            $this->db->bind(':dfrom',!empty($data['deductfrom']) ? $data['deductfrom'] : NULL);
            $this->db->bind(':bid',$data['bank']);                        
            $this->db->bind(':amount',$data['amount']);                        
            $this->db->bind(':narr',strtolower($data['description']));                        
            $this->db->bind(':ref',strtolower($data['reference']));
            $this->db->bind(':reqid', $data['reqid']);                          
            $this->db->bind(':id',$data['id']); 
            $this->db->execute();
            
            if((int)$data['paymethod'] === 1 && $data['deductfrom'] === 'petty cash'){
                $this->db->query('UPDATE tblpettycash SET TransactionDate=:tdate,Credit=:credit,Reference=:ref,
                                         Narration=:narr WHERE (ExpenseId=:eid)');
                $this->db->bind(':tdate',date('Y-m-d',strtotime($data['date'])));
                $this->db->bind(':credit',$data['amount']);
                $this->db->bind(':ref',strtolower($data['reference']));
                $this->db->bind(':narr',strtolower($data['description']));
                $this->db->bind(':eid',$data['id']);
                $this->db->execute();
            }

            $act = 'Updated Expense For '.$data['date'] . ' Voucher No '.$data['voucher'];
            saveLog($this->db->dbh,$act);
            if ($this->db->dbh->commit()) {
                return true;
            }
            else{
                return false;
            }             
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
        }
    }
    public function delete($data)
    {
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblexpenses SET deleted = 1 WHERE (ID=:id)');
            $this->db->bind(':id',$data['id']); 
            $this->db->execute();
            
            $this->db->query('UPDATE tblpettycash SET Deleted = 1 WHERE (ExpenseId=:eid)');
            $this->db->bind(':eid',$data['id']);
            $this->db->execute();
                   
            softdeleteLedgerBanking($this->db->dbh,2,$data['id']);

            $act = 'Deleted Expense For '.$data['date'] . ' Voucher No '.$data['voucher'];
            saveLog($this->db->dbh,$act);
            if ($this->db->dbh->commit()) {
                return true;
            }
            else{
                return false;
            }             
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
        }
    }

    public function YearIsClosed($id) 
    {
        $yearid = getdbvalue($this->db->dbh,'SELECT fiscalYearId FROM tblexpenses WHERE ID = ?',[$id]);
        return yearprotection($this->db->dbh,$yearid);
    }

    function GetGroupBalance($group,$type)
    {
        if($type === 2){
            $sql = 'SELECT r.ID,CONCAT("Req No ",r.ReqNo," - ",FORMAT(r.AmountApproved,2)) AS Formated 
                FROM `tblfundrequisition` r 
                WHERE r.GroupId = ?
                HAVING getrequisitionbalance(r.ID) > 0;';
        }else{
            $sql = 'SELECT r.ID,CONCAT("Req No ",r.ReqNo," - ",FORMAT(r.AmountApproved,2)) AS Formated 
                FROM `tblfundrequisition` r 
                WHERE r.DistrictId = ?
                HAVING getrequisitionbalance(r.ID) > 0;';
        }
        
        return loadresultset($this->db->dbh,$sql,[$group]);
    }

    public function resetApprovals($data)
    {
        
        try {

            $this->db->dbh->beginTransaction();

            $year = getdbvalue($this->db->dbh,'SELECT ID FROM tblfiscalyears WHERE ID = ?',[$data['year']]);
            if(!$year){
                throw new \Exception('Year not found');
            }

            $expenseIds = loadresultset($this->db->dbh,'SELECT ID FROM tblexpenses WHERE (fiscalYearId = ?)',[$year]);

            foreach($expenseIds as $exp){
                $this->db->query('UPDATE tblpettycash SET Deleted = 1 WHERE (ExpenseId=:eid)');
                $this->db->bind(':eid',$exp->ID);
                $this->db->execute();

                $this->db->query('UPDATE tblledger SET deleted = 1 WHERE (transactionType=2) AND (transactionId=:eid)');
                $this->db->bind(':eid',$exp->ID);
                $this->db->execute();

                $this->db->query('UPDATE tblbankpostings SET deleted = 1 WHERE (transactionType=2) AND (transactionId=:eid)');
                $this->db->bind(':eid',$exp->ID);
                $this->db->execute();
                       
            }

            $this->db->query('UPDATE tblexpenses SET `status` = 0 WHERE (fiscalYearId=:id)');
            $this->db->bind(':id',$year); 
            $this->db->execute();
            
            if ($this->db->dbh->commit()) {
                return true;
            }
            else{
                return false;
            }     

        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
        }
    }

    public function getJournalDetails($id)
    {
        $expense = loadsingleset($this->db->dbh,'SELECT * FROM tblexpenses WHERE ID=?',[(int)$id]);
        $status = (int)$expense->status;
        if($status == 1){
            $sql = 'SELECT 
                    l.ID,
                    l.transactionDate,
                    l.account,
                    l.debit,
                    l.credit,
                    l.narration
                FROM `tblledger` l 
                WHERE (transactionId = ?) AND (transactionType = ?) AND (l.deleted = 0) AND (l.congregationId=?)';
            return loadresultset($this->db->dbh,$sql,[(int)$expense->ID,2,(int)$_SESSION['congId']]);
        }
        $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$expense->accountId);
        $accountname = $this->db->getValue();

        if(!is_null($expense->requisitionId)){
                $creditAccount = 'cash holding account';
        }else{
            if($expense->paymethodId == 1 && $expense->deductfrom === 'petty cash'){
                $creditAccount = 'petty cash';
            }elseif ($expense->paymethodId == 1 && $expense->deductfrom === 'cash at hand') {
                $creditAccount = 'cash at hand';
            }elseif ($expense->paymethodId == 1 && $expense->deductfrom === 'cash holding account') {
                $creditAccount = 'cash holding account';
            }else{
                $creditAccount = 'cash at bank';
            }
        }

        return array(
            (object)array(
                'ID' => 1,
                'transactionDate' => $expense->expenseDate,
                'account' => $accountname,
                'debit' => $expense->amount,
                'credit' => 0,
                'narration' => $expense->narration
            ),
            (object)array(
                'ID' => 2,
                'transactionDate' => $expense->expenseDate,
                'account' => $creditAccount,
                'debit' => 0,
                'credit' => $expense->amount,
                'narration' => $expense->narration
            )
        );
    }
}
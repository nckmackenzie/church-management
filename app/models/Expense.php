<?php
class Expense {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    public function getExpenses()
    {
        $this->db->query('SELECT * FROM vw_getexpenses WHERE (congregationId=:cid)
                          AND (deleted=0)');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();                  
    }

    public function GetAccounts($type)
    {
        if($type == 1){
            $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                              WHERE ((accountTypeId = :expense) OR (accountTypeId = :asset)) AND (deleted=0) AND (isBank = 0) AND (parentId <> 0) ORDER BY accountType');
        }else{
            $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                              WHERE ((accountTypeId = :expense) OR (accountTypeId = :asset)) AND (deleted=0) AND (isBank = 0) AND (parentId <> 0) AND (forGroup = 1) ORDER BY accountType');
        }
        $this->db->bind(':expense',2);
        $this->db->bind(':asset',3);
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
        // $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
        // $this->db->bind(':id',$data['account']);
        // $accountname = $this->db->getValue();
        // //
        // $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        // $this->db->bind(':id',$data['account']);
        // $accountid = $this->db->getValue();
        //
        $id = getLastId($this->db->dbh,'tblexpenses');
        $fid = getYearId($this->db->dbh,$data['date']);
        $today = date('Y-m-d');
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblexpenses (ID,fiscalYearId,voucherNo,expenseType,expenseDate,
                                          accountId,groupId,paymethodId,deductfrom,bankId,amount,narration,
                                          paymentReference,`status`,hasAttachment,`fileName`,requisitionId,postedBy,postedDate,congregationId)
                              VALUES(:id,:fid,:vno,:etype,:edate,:aid,:gid,:pid,:dfrom,:bid,:amount,:narr,:ref,
                                    :stat,:hasattach,:fname,:reqid,:post,:pdate,:cid)');
            $this->db->bind(':id',$id);
            $this->db->bind(':fid',$fid);                        
            $this->db->bind(':vno',$data['voucher']);                        
            $this->db->bind(':etype',$data['expensetype']);                        
            $this->db->bind(':edate',$data['date']);                        
            $this->db->bind(':aid',$data['account']);                        
            $this->db->bind(':gid',$data['expensetype'] == 1 ? NULL : $data['costcentre']); 
            $this->db->bind(':pid',$data['paymethod']);
            $this->db->bind(':dfrom',!empty($data['deductfrom']) ? $data['deductfrom'] : NULL);
            $this->db->bind(':bid',$data['bank']);                        
            $this->db->bind(':amount',$data['amount']);                        
            $this->db->bind(':narr',strtolower($data['description']));                        
            $this->db->bind(':ref',strtolower($data['reference']));                        
            $this->db->bind(':stat',0);
            $this->db->bind(':hasattach',$data['hasattachment']);   //to add                     
            $this->db->bind(':fname',$data['hasattachment'] === 1 ? $data['filename'] : NULL);   //to add 
            $this->db->bind(':reqid', is_numeric($data['deductfrom']) ? (int)$data['deductfrom'] : NULL);    
            $this->db->bind(':post',$_SESSION['userId']);                        
            $this->db->bind(':pdate',$today);  
            $this->db->bind(':cid',$_SESSION['congId']);  
            $this->db->execute();
            
            // if((int)$data['paymethod'] === 1 && $data['deductfrom'] === 'petty cash'){
            //     $this->db->query('INSERT INTO tblpettycash (TransactionDate,Credit,IsReceipt,Reference,Narration,ExpenseId,CongregationId)
            //                       VALUES(:tdate,:credit,:isreceipt,:ref,:narr,:eid,:cid)');
            //     $this->db->bind(':tdate',date('Y-m-d',strtotime($data['date'])));
            //     $this->db->bind(':credit',$data['amount']);
            //     $this->db->bind(':isreceipt',false);
            //     $this->db->bind(':ref',strtolower($data['reference']));
            //     $this->db->bind(':narr',strtolower($data['description']));
            //     $this->db->bind(':eid',$id);
            //     $this->db->bind(':cid',$_SESSION['congId']);
            //     $this->db->execute();
            // }
            

            // $cashparent = getparentgl($this->db->dbh,'cash at bank');
            // $accparent = getparentgl($this->db->dbh,$accountname);
            // if($data['deductfrom'] == 'petty cash' || $data['deductfrom'] == 'cash at hand' || empty($data['deductfrom'])){
            //     saveToLedger($this->db->dbh,$data['date'],$accountname,$accparent,$data['amount'],0,$data['description'],
            //                 $accountid,2,$id,$_SESSION['congId']);
            //     // if ($data['paymethod'] == 1 && $data['deductfrom'] === 'petty cash') {
            //     //     // saveToLedger($this->db->dbh,$data['date'],'petty cash',$cashparent,0,$data['amount'],$data['description'],
            //     //     //             3,2,$id,$_SESSION['congId']);
            //     // }elseif($data['paymethod'] == 1 && $data['deductfrom'] === 'cash at hand'){
            //     //     saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cashparent,0,$data['amount'],$data['description'],
            //     //                 3,2,$id,$_SESSION['congId']);
            //     // }
            //     // elseif ($data['paymethod'] == 2) {
            //     //     saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //     //                 3,2,$id,$_SESSION['congId']);
            //     // }else{
            //     //     saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //     //                 3,2,$id,$_SESSION['congId']);
            //     //     saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],2,
            //     //                 $data['reference'],2,$id,$_SESSION['congId']);             
            //     // }
            // }

            // if($data['paymethod'] == 1 && $data['deductfrom'] === 'petty cash'){
            //     saveToLedger($this->db->dbh,$data['date'],'petty cash',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$id,$_SESSION['congId']);
            // }elseif ($data['paymethod'] == 1 && $data['deductfrom'] === 'cash at hand') {
            //     saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$id,$_SESSION['congId']);
            // }elseif($data['paymethod'] == 2){
            //     saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$id,$_SESSION['congId']);
            // }elseif ((int)$data['paymethod'] > 2) {
            //     saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$id,$_SESSION['congId']);
            //     saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],2,
            //                     $data['reference'],2,$id,$_SESSION['congId']);             
            // }



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
            if($expense->deductfrom == 'petty cash' || $expense->deductfrom == 'cash at hand' || is_null($expense->deductfrom)){
                saveToLedger($this->db->dbh,$expense->expenseDate,$accountname,$accparent,$expense->amount,0,$expense->narration,
                            $accountid,2,$data['id'],$_SESSION['congId']);
            }

            if($expense->paymethodId == 1 && $expense->deductfrom === 'petty cash'){
                saveToLedger($this->db->dbh,$expense->expenseDate,'petty cash',$cashparent,0,$expense->amount,$expense->narration,
                                3,2,$data['id'],$_SESSION['congId']);
            }elseif ($expense->paymethodId == 1 && $expense->deductfrom === 'cash at hand') {
                saveToLedger($this->db->dbh,$expense->expenseDate,'cash at hand',$cashparent,0,$expense->amount,$expense->narration,
                                3,2,$data['id'],$_SESSION['congId']);
            }elseif($expense->paymethodId == 2){
                saveToLedger($this->db->dbh,$expense->expenseDate,'cash at bank',$cashparent,0,$expense->amount,$expense->narration,
                                3,2,$data['id'],$_SESSION['congId']);
            }elseif ((int)$expense->paymethodId > 2) {
                saveToLedger($this->db->dbh,$expense->expenseDate,'cash at bank',$cashparent,0,$expense->amount,$expense->narration,
                                3,2,$data['id'],$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$expense->expenseDate,0,$expense->amount,2,
                              $expense->paymentReference,2,$data['id'],$_SESSION['congId']);             
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
    //    $this->db->query('UPDATE tblexpenses SET `status` = 1 WHERE (ID=:id)');
    //    $this->db->bind(':id',$data['id']);
    //    if ($this->db->execute()) {
    //         $act = 'Approved Expense For '.$data['date'] . ' Voucher No ' .$data['voucher'];
    //         saveLog($this->db->dbh,$act);
    //         return true;
    //    }else{
    //        return false;
    //    }
    }
    public function getExpense($id)
    {
        $this->db->query('SELECT * FROM tblexpenses WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function getExpenseFull($id)
    {
        $this->db->query('SELECT * FROM vw_expensevoucher WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
 
    public function update($data)
    {
        //get names
        // $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
        // $this->db->bind(':id',$data['account']);
        // $accountname = $this->db->getValue();
        // //
        // $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        // $this->db->bind(':id',$data['account']);
        // $accountid = $this->db->getValue();

        $fid = getYearId($this->db->dbh,$data['date']);
        
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblexpenses SET fiscalYearId=:fid,expenseType=:etype,expenseDate
                                     =:edate,accountId=:aid,groupId=:gid,paymethodId=:pid,deductfrom=:dfrom,bankId=:bid
                                     ,amount=:amount,narration=:narr,paymentReference=:ref,requisitionId=:reqid
                              WHERE (ID=:id)');
            $this->db->bind(':fid',$fid);                        
            $this->db->bind(':etype',$data['expensetype']);                        
            $this->db->bind(':edate',$data['date']);                        
            $this->db->bind(':aid',$data['account']);                        
            $this->db->bind(':gid',$data['expensetype'] == 1 ? NULL : $data['costcentre']); 
            $this->db->bind(':pid',$data['paymethod']);
            $this->db->bind(':dfrom',!empty($data['deductfrom']) ? $data['deductfrom'] : NULL);
            $this->db->bind(':bid',$data['bank']);                        
            $this->db->bind(':amount',$data['amount']);                        
            $this->db->bind(':narr',strtolower($data['description']));                        
            $this->db->bind(':ref',strtolower($data['reference']));
            $this->db->bind(':reqid', is_numeric($data['deductfrom']) ? (int)$data['deductfrom'] : NULL);                          
            $this->db->bind(':id',$data['id']); 
            $this->db->execute();
            
            // if((int)$data['paymethod'] === 1 && $data['deductfrom'] === 'petty cash'){
            //     $this->db->query('UPDATE tblpettycash SET TransactionDate=:tdate,Credit=:credit,Reference=:ref,
            //                              Narration=:narr WHERE (ExpenseId=:eid)');
            //     $this->db->bind(':tdate',date('Y-m-d',strtotime($data['date'])));
            //     $this->db->bind(':credit',$data['amount']);
            //     $this->db->bind(':ref',strtolower($data['reference']));
            //     $this->db->bind(':narr',strtolower($data['description']));
            //     $this->db->bind(':eid',$data['id']);
            //     $this->db->execute();
            // }

            // if((int)$data['expensetype'] === 2){
            //     $this->db->query('UPDATE tblmmf SET TransactionDate=:tdate,GroupId=:gid,Credit=:credit,
            //                                         BankId=:bid,Reference=:reference,Narration=:narr 
            //                       WHERE (TransactionType = 2) AND (TransactionId = :id)');
            //     $this->db->bind(':tdate',$data['date']);
            //     $this->db->bind(':gid',$data['costcentre']);
            //     $this->db->bind(':credit',$data['amount']);
            //     $this->db->bind(':bid',$data['bank']);
            //     $this->db->bind(':reference',strtolower($data['reference']));
            //     $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : null);
            //     $this->db->bind(':id',intval($data['id']));
            //     $this->db->execute();
            // }
            
            // deleteLedgerBanking($this->db->dbh,2,$data['id']);
            // $cashparent = getparentgl($this->db->dbh,'cash at bank');
            // $accparent = getparentgl($this->db->dbh,$accountname);
            // if($data['deductfrom'] == 'petty cash' || $data['deductfrom'] == 'cash at hand' || empty($data['deductfrom'])){
            //     saveToLedger($this->db->dbh,$data['date'],$accountname,$accparent,$data['amount'],0,$data['description'],
            //                 $accountid,2,$data['id'],$_SESSION['congId']);
            //     // if ($data['paymethod'] == 1 && $data['deductfrom'] === 'petty cash') {
            //     //     // saveToLedger($this->db->dbh,$data['date'],'petty cash',$cashparent,0,$data['amount'],$data['description'],
            //     //     //             3,2,$id,$_SESSION['congId']);
            //     // }elseif($data['paymethod'] == 1 && $data['deductfrom'] === 'cash at hand'){
            //     //     saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cashparent,0,$data['amount'],$data['description'],
            //     //                 3,2,$id,$_SESSION['congId']);
            //     // }
            //     // elseif ($data['paymethod'] == 2) {
            //     //     saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //     //                 3,2,$id,$_SESSION['congId']);
            //     // }else{
            //     //     saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //     //                 3,2,$id,$_SESSION['congId']);
            //     //     saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],2,
            //     //                 $data['reference'],2,$id,$_SESSION['congId']);             
            //     // }
            // }

            // if($data['paymethod'] == 1 && $data['deductfrom'] === 'petty cash'){
            //     saveToLedger($this->db->dbh,$data['date'],'petty cash',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$data['id'],$_SESSION['congId']);
            // }elseif ($data['paymethod'] == 1 && $data['deductfrom'] === 'cash at hand') {
            //     saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$data['id'],$_SESSION['congId']);
            // }elseif($data['paymethod'] == 2){
            //     saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$data['id'],$_SESSION['congId']);
            // }elseif ((int)$data['paymethod'] > 2) {
            //     saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$data['id'],$_SESSION['congId']);
            //     saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],2,
            //                     $data['reference'],2,$data['id'],$_SESSION['congId']);             
            // }

            // if($data['deductfrom'] !== 'group petty cash'){
            //     $cashparent = getparentgl($this->db->dbh,'cash at bank');
            //     $accparent = getparentgl($this->db->dbh,$accountname);
            //     saveToLedger($this->db->dbh,$data['date'],$accountname,$accparent,$data['amount'],0,$data['description'],
            //                 $accountid,2,$data['id'],$_SESSION['congId']);
            //     if ($data['paymethod'] == 1 && $data['deductfrom'] === 'petty cash') {
            //         saveToLedger($this->db->dbh,$data['date'],'petty cash',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$data['id'],$_SESSION['congId']);
            //     }elseif($data['paymethod'] == 1 && $data['deductfrom'] === 'cash at hand'){
            //         saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$data['id'],$_SESSION['congId']);
            //     }
            //     elseif ($data['paymethod'] == 2) {
            //         saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$data['id'],$_SESSION['congId']);
            //     }else{
            //         saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cashparent,0,$data['amount'],$data['description'],
            //                     3,2,$data['id'],$_SESSION['congId']);
            //         saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],2,
            //                     $data['reference'],2,$data['id'],$_SESSION['congId']);             
            //     }
            // }
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

    function GetGroupBalance($group)
    {
        $sql = 'SELECT r.ID,CONCAT("Req No ",r.ReqNo," - ",FORMAT(r.AmountApproved,2)) AS Formated 
                FROM `tblfundrequisition` r 
                WHERE r.GroupId = ?
                HAVING getrequisitionbalance(r.ID) > 0;';
        return loadresultset($this->db->dbh,$sql,[$group]);
    }
}
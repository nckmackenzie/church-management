<?php
class Groupfund
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function CheckRights($form)
    {
        return checkuserrights($this->db->dbh,$_SESSION['userId'],$form);
    }

    public function GetReqNo()
    {
        return getuniqueid($this->db->dbh,'ReqNo','tblfundrequisition',(int)$_SESSION['congId']);
    }

    public function PendingApprovalCount($gid)
    {
        $sql = 'SELECT COUNT(*) FROM tblfundrequisition WHERE (GroupId = ?) AND (Deleted = 0) AND (`Status` = 0)';
        return getdbvalue($this->db->dbh,$sql,[$gid]);
    }

    public function GetRequests()
    {
        $sql = 'SELECT * FROM vw_group_requisitions  
                          WHERE (congregationId = ?)
                          ORDER BY `Status`,RequestDate DESC';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetGroups()
    {
        $this->db->query('SELECT ID,UCASE(groupName) as itemName 
                          FROM tblgroups 
                          WHERE (active = 1) AND (deleted = 0) AND (congregationId = :cid)
                          ORDER BY itemName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetDistricts()
    {
        $this->db->query('SELECT ID,UCASE(districtName) as itemName 
                          FROM tbldistricts 
                          WHERE (deleted = 0) AND (congregationId = :cid)
                          ORDER BY itemName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetBalance($group,$date,$type)
    {
        if($type === 'group'){
            return getdbvalue($this->db->dbh,'SELECT getmmfopeningbalbydate(?,?)',[$group,$date]);
        }else{
            return getdbvalue($this->db->dbh,'SELECT getmmfopeningbalbydate_district(?,?)',[$group,$date]);
        }
    }

    public function CreateUpdate($data)
    {
        if(!$data['isedit']){
            $this->db->query('INSERT INTO tblfundrequisition (ReqNo,RequisitionDate,RequestType,DistrictId,GroupId,ChurchCategoryId,Purpose,AmountRequested,RequestedBy,DontDeduct,CongregationId) 
                              VALUES(:reqno,:rdate,:rtype,:did,:gid,:catid,:purpose,:amount,:reqby,:deduct,:cid)');
            $this->db->bind(':reqno',$this->GetReqNo());
        }else{
            $this->db->query('UPDATE tblfundrequisition SET RequisitionDate=:rdate,RequestType=:rtype,DistrictId=:did,GroupId=:gid,ChurchCategoryId=:catid,Purpose=:purpose,AmountRequested=:amount,RequestedBy=:reqby,DontDeduct=:deduct 
                              WHERE (ID = :id)');
        }
        $this->db->bind(':rdate',!empty($data['reqdate']) ? $data['reqdate'] : null);
        $this->db->bind(':rtype',!empty($data['type']) ? $data['type'] : null);
        if($data['type'] === 'church'){
            $this->db->bind(':did',null);
            $this->db->bind(':gid',null);
            $this->db->bind(':catid',$data['group']);
        }elseif($data['type'] === 'district'){
            $this->db->bind(':did',$data['group']);
            $this->db->bind(':gid',null);
            $this->db->bind(':catid',null);
        }elseif($data['type'] === 'group'){
            $this->db->bind(':did',null);
            $this->db->bind(':gid',$data['group']);
            $this->db->bind(':catid',null);
        }
        // $this->db->bind(':did',$data['type'] === 'group' ? null : $data['group']);
        // $this->db->bind(':gid',$data['type'] === 'group' ? $data['group'] : null);
        $this->db->bind(':purpose',!empty($data['reason']) ? strtolower($data['reason']) : null);
        $this->db->bind(':amount',!empty($data['amount']) ? $data['amount'] : null);
        $this->db->bind(':reqby',$_SESSION['userId']);
        $this->db->bind(':deduct',$data['dontdeduct']);
        if($data['isedit']){
            $this->db->bind(':id',$data['id']);
        }else{
            $this->db->bind(':cid',$_SESSION['congId']);
        }
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function GetGroupCongregation($gid)
    {
        return getdbvalue($this->db->dbh,'SELECT congregationId FROM tblgroups WHERE ID = ?',[$gid]);
    }

    public function GetRequest($id)
    {
        $this->db->query('SELECT * FROM tblfundrequisition WHERE (ID=:id)');
        $this->db->bind(':id',(int)$id);
        return $this->db->single();
    }

    public function Delete($id)
    {
        $this->db->query('UPDATE tblfundrequisition SET Deleted = 1 WHERE (ID = :id)');
        $this->db->bind(':id',(int)$id);
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function GetRequestStatus($id)
    {
        return getdbvalue($this->db->dbh,'SELECT `Status` FROM tblfundrequisition WHERE ID = ?',[$id]);
    }

    public function GetApprovals()
    {
        $sql = 'SELECT * FROM vw_requisition_approvals   
                WHERE (congregationId = ?)';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function PayMethods()
    {
        return paymentMethods($this->db->dbh);
    }

    public function GetBanks()
    {
        $this->db->query("SELECT ID,
                                 CONCAT(UCASE(`accountType`),'-',IFNULL(`accountNo`,'')) As Bank
                          FROM   tblaccounttypes 
                          WHERE  (isBank=1) AND (Deleted=0) AND (congregationId=:cid)");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetGroupName($type,$id)
    {
        if($type === 'group'){
            return getdbvalue($this->db->dbh,'SELECT groupName FROM tblgroups WHERE ID = ?',[$id]);
        }else{
            return getdbvalue($this->db->dbh,'SELECT districtName FROM tbldistricts WHERE ID = ?',[$id]);
        }
    }

    /**
     * Hotfix: only the requisition's stored row decides group / district, type, DontDeduct, purpose and the
     * requested amount; the form supplies only id, approved amount, pay date, reference and bank.
     * Returns true (or the requester's contact) on success, ['error' => message, 'final' => bool] when refused
     * ('final' = the requisition itself cannot be approved, not a form problem) or false when the commit fails.
     */
    public function Approve($data)
    {
        $reference = !empty($data['reference']) ? strtolower($data['reference']) : null;
        $dbh = $this->db->dbh;
        try {
            $dbh->beginTransaction();

            $st = $dbh->prepare('SELECT * FROM tblfundrequisition WHERE ID = ? AND CongregationId = ? FOR UPDATE');
            $st->execute([(int)$data['id'],(int)$_SESSION['congId']]);
            $req = $st->fetch(PDO::FETCH_OBJ);
            if(!$req || (int)$req->Deleted !== 0){
                return $this->refuseApproval('Requisition not found',true);
            }
            if((int)$req->Status !== 0){
                return $this->refuseApproval('This requisition has already been '.((int)$req->Status === 1 ? 'approved' : 'rejected').'. Nothing was posted',true);
            }

            $approved = $data['approved'];
            if(!is_numeric($approved) || (float)$approved <= 0 || round((float)$approved,2) !== (float)$approved){
                return $this->refuseApproval('Enter a valid amount to approve (greater than zero, at most 2 decimals)');
            }
            $approved = (float)$approved;
            if($approved > (float)$req->AmountRequested){
                return $this->refuseApproval('Payment amount greater than amount requested');
            }

            $paydate = DateTime::createFromFormat('!Y-m-d',(string)$data['paydate']);
            if(!$paydate || $paydate->format('Y-m-d') !== $data['paydate']){
                return $this->refuseApproval('Select a valid payment date');
            }
            if($data['paydate'] > date('Y-m-d')){
                return $this->refuseApproval('Payment date cannot be in the future');
            }

            $bank = !empty($data['bank']) ? (int)$data['bank'] : null;
            if(!is_null($bank)){
                $st = $dbh->prepare('SELECT COUNT(*) FROM tblaccounttypes WHERE ID = ? AND isBank = 1 AND Deleted = 0 AND congregationId = ?');
                $st->execute([$bank,(int)$_SESSION['congId']]);
                if((int)$st->fetchColumn() === 0){
                    return $this->refuseApproval('Select a valid bank');
                }
            }

            $type = $req->RequestType;
            $dontdeduct = (int)$req->DontDeduct === 1;
            if($type === 'group'){
                $groupid = $req->GroupId;
                $groupname = getdbvalue($dbh,'SELECT groupName FROM tblgroups WHERE ID = ?',[$req->GroupId]);
            }elseif($type === 'district'){
                $groupid = $req->DistrictId;
                $groupname = getdbvalue($dbh,'SELECT districtName FROM tbldistricts WHERE ID = ?',[$req->DistrictId]);
            }else{
                $groupid = null;
                $groupname = getdbvalue($dbh,'SELECT categoryName FROM tblchurchrequisitioncategories WHERE id = ?',[$req->ChurchCategoryId]);
            }
            $desc = strtolower((string)$groupname) .' funds request approval';
            $narration = mb_substr(strtoupper((string)$req->Purpose),0,150);

            $st = $dbh->prepare('UPDATE tblfundrequisition
                                    SET AmountApproved = ?,ApprovalDate = ?,ApprovedBy = ?,`Status` = 1
                                  WHERE (ID = ?) AND (CongregationId = ?) AND (`Status` = 0)');
            $st->execute([$approved,date('Y-m-d'),(int)$_SESSION['userId'],(int)$req->ID,(int)$_SESSION['congId']]);
            if($st->rowCount() !== 1){
                return $this->refuseApproval('This requisition has already been approved. Nothing was posted',true);
            }

            $data['id'] = (int)$req->ID;
            $data['approved'] = $approved;
            $data['paydate'] = $paydate->format('Y-m-d');

            if(!$dontdeduct){
                $this->db->query('INSERT INTO tblmmf (TransactionDate,Type,DistrictId,GroupId,Credit,BankId,Reference,Narration,TransactionType,TransactionId,
                CongregationId) VALUES(:tdate,:type,:did,:gid,:credit,:bid,:reference,:narr,:ttype,:tid,:cid)');
                $this->db->bind(':tdate',$data['paydate']);
                $this->db->bind(':type',$type);
                $this->db->bind(':did', $type === 'district' ? $groupid : null);
                $this->db->bind(':gid', $type === 'group' ? $groupid : null);
                $this->db->bind(':credit',$approved);
                $this->db->bind(':bid',$bank);
                $this->db->bind(':reference',$reference);
                $this->db->bind(':narr',$narration);
                $this->db->bind(':ttype',12);
                $this->db->bind(':tid',$data['id']);
                $this->db->bind(':cid',$_SESSION['congId']);
                $this->db->execute();
            }

            $cashparent = getparentgl($this->db->dbh,'cash at hand');
            saveToLedger($this->db->dbh,$data['paydate'],'cash holding account',$cashparent,$data['approved'],0,$desc,
                                                3,12,$data['id'],$_SESSION['congId'],$reference);

            // if($data['type'] === 'group'){
            //     $gbhparent = getparentgl($this->db->dbh,'groups balances held');
            //     if(!$data['dontdeduct']){
            //         saveToLedger($this->db->dbh,$data['paydate'],'groups balances held',$gbhparent,$data['approved'],0,$desc,
            //                                     4,12,$data['id'],$_SESSION['congId'],$reference);
            //     }else{
            //         saveToLedger($this->db->dbh,$data['paydate'],"groups' expenses","groups' expenses",$data['approved'],0,$desc,
            //                                     2,12,$data['id'],$_SESSION['congId'],$reference);
            //     }
            // }else{
            //     if(!$data['dontdeduct']){
            //         saveToLedger($this->db->dbh,$data['paydate'],'district funds held','district funds held',$data['approved'],0,$desc,
            //                                     4,12,$data['id'],$_SESSION['congId'],$reference);
            //     }else{
            //         saveToLedger($this->db->dbh,$data['paydate'],"groups' expenses","groups' expenses",$data['approved'],0,$desc,
            //                                     2,12,$data['id'],$_SESSION['congId'],$reference);
            //     }
            // }
            
            saveToLedger($this->db->dbh,$data['paydate'],'petty cash',$cashparent,0,$data['approved'],$desc,
                         3,12,$data['id'],$_SESSION['congId'],$reference);

            // if((int)$data['paymethod'] === 1){
            //     saveToLedger($this->db->dbh,$data['paydate'],'cash at hand',$cashparent,0,$data['approved'],$desc,
            //              3,12,$data['id'],$_SESSION['congId'],$reference);
            // }else{
            //     saveToLedger($this->db->dbh,$data['paydate'],'cash at bank',$cashparent,0,$data['approved'],$desc,
            //                  3,12,$data['id'],$_SESSION['congId'],$reference);

            //     saveToBanking($this->db->dbh,$data['bank'],$data['paydate'],0,$data['approved'],2,
            //                  $data['reference'],12,$data['id'],$_SESSION['congId']); 
            // }

            if(!$this->db->dbh->commit()){
                return false;
            }

        } catch (Exception $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
            return ['error' => 'Unable to save this approval, nothing was posted. Contact admin for help','final' => false];
        }

        // committed: a failure below must never be reported as a failed approval (a retry would be refused anyway)
        try {
            $requestedby = getdbvalue($this->db->dbh,'SELECT RequestedBy FROM tblfundrequisition WHERE ID=?',[(int)$data['id']]);
            if(is_null($requestedby)) return true;

            $contact = getdbvalue($this->db->dbh,'SELECT contact FROM tblusers WHERE ID=?',[(int)$requestedby]);
            return $contact ? $contact : true;
        } catch (Exception $e) {
            error_log($e->getMessage(),0);
            return true;
        }
    }

    private function refuseApproval($message,$final = false)
    {
        if($this->db->dbh->inTransaction()){
            $this->db->dbh->rollback();
        }
        return ['error' => $message,'final' => $final];
    }

    public function Reverse($id)
    {
        try {
            $this->db->dbh->beginTransaction();
            
            $this->db->query('UPDATE tblfundrequisition 
                                SET AmountApproved = null, ApprovalDate = null,ApprovedBy = null,`Status` = 0
                              WHERE (ID = :id)');
            $this->db->bind(':id',$id);
            $this->db->execute();

            $this->db->query('DELETE FROM tblmmf WHERE (TransactionType = 12) AND (TransactionId = :id)');
            $this->db->bind(':id',$id);
            $this->db->execute();

            deleteLedgerBanking($this->db->dbh,12,$id);

            if(!$this->db->dbh->commit()){
                return false;
            }else{
                return true;
            }

        } catch (Exception $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
        }
    }

    public function Reject($id,$reason)
    {
        try {
            $this->db->dbh->beginTransaction();
            
            $this->db->query('UPDATE tblfundrequisition 
                                SET `Status` = 2,ReasonForRejection=:reason
                              WHERE (ID = :id)');

            $this->db->bind(':reason',$reason);
            $this->db->bind(':id',$id);
            $this->db->execute();

            $this->db->query('DELETE FROM tblmmf WHERE (TransactionType = 12) AND (TransactionId = :id)');
            $this->db->bind(':id',$id);
            $this->db->execute();

            deleteLedgerBanking($this->db->dbh,12,$id);

            if(!$this->db->dbh->commit()){
                return false;
            }else{
                $requestedby = getdbvalue($this->db->dbh,'SELECT RequestedBy FROM tblfundrequisition WHERE ID=?',[(int)$id]);
                if(is_null($requestedby)) return true;

                $contact = getdbvalue($this->db->dbh,'SELECT contact FROM tblusers WHERE ID=?',[(int)$requestedby]);
                return $contact;
            }

        } catch (Exception $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
        }
    }

    public function Unapprove($id)
    {
        try {
            $this->db->dbh->beginTransaction();
            
           
            $this->db->query('UPDATE tblfundrequisition 
                                SET AmountApproved = :app,ApprovalDate=:appdate,ApprovedBy = :appby,`Status` = 0
                              WHERE (ID = :id)');
            $this->db->bind(':app',null);
            $this->db->bind(':appdate',null);
            $this->db->bind(':appby',null);
            $this->db->bind(':id',$id);
            $this->db->execute();

            $this->db->query('DELETE from tblmmf 
                                   WHERE (TransactionType = 12) AND (TransactionId = :id)');
            $this->db->bind(':id',$id);
            $this->db->execute();
            

            deleteLedgerBanking($this->db->dbh,12,$id);           
            if(!$this->db->dbh->commit()){
                return false;
            }else{                
                return true;
            }

        } catch (Exception $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
            return false;
        }
    }
}
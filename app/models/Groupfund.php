<?php
/**
 * Group / district / church fund requisitions and their approval (transaction type 12) on the Ledger library (task 8.9).
 *
 * Approval posts, whatever DontDeduct says:
 *   Dr the RECEIVING account the user chose (a PAY_CASH account; 121 'cash holding account' = Ledger::CASH_HOLDING, a confirmed
 *   system account, is preselected)  /  Cr the "paid from" account the user chose (a PAY_CASH account when the pay method is cash, 98 preselected; a PAY_BANK account otherwise, which also
 *   writes a bank row, method 'Withdrawal'). Nothing else is inferred.
 * DontDeduct (read from the stored requisition) only decides whether a tblmmf Credit row lowers the group's /
 * district's held balance. Church-category requisitions never write one (they have no group or district).
 *
 * The receiving account is not stored on the requisition: it is the debit line of the requisition's active type-12 entry
 * (ReceivingAccountOf()), the same source Expense::requisitionStyle() reads. A new approval after an unapprove simply writes a
 * new entry, so nothing can get out of step.
 *
 * Every action loads the requisition by ID + session congregation FOR UPDATE inside its transaction. Unapprove and
 * Reject are refused while a non-deleted expense references the requisition and use Ledger::void (closed years refuse).
 * Approve / Unapprove / Reject / Reverse return true (or the requester's contact) on success, or
 * ['error' => message, 'final' => bool] ('final' = the requisition itself is not in a state that allows it).
 */
class Groupfund
{
    const TYPE = 12;
    const BANK_METHOD = 'Withdrawal';
    const CASH_METHOD = 1;          // tblpaymentmethods: 1 cash; 2 mpesa, 3 cheque, 4 bank are paid from a bank account
    const DEFAULT_PAID_FROM = 98;   // 'petty cash', preselected for cash (confirmed by the user, task 8.9)
    const REFERENCE_LEN = 25;       // tblmmf.Reference

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

    /** Held balance of a group / district up to $date; $group is the GROUP or DISTRICT ID that matches $type. Church: 0. */
    /** Church categories of this congregation, same shape as GetGroups() / GetDistricts() (ID, itemName). */
    public function GetCategories()
    {
        $this->db->query('SELECT id AS ID,UCASE(categoryName) as itemName
                          FROM tblchurchrequisitioncategories
                          WHERE (deleted = 0) AND (congregationId = :cid)
                          ORDER BY itemName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    /** The groups / districts / church categories a requisition of $type can be for (this congregation). Unknown type: []. */
    public function GetOwners($type)
    {
        if($type === 'group'){
            return $this->GetGroups();
        }elseif($type === 'district'){
            return $this->GetDistricts();
        }elseif($type === 'church'){
            return $this->GetCategories();
        }
        return [];
    }

    public function GetBalance($group,$date,$type)
    {
        if($type === 'group'){
            return getdbvalue($this->db->dbh,'SELECT getmmfopeningbalbydate(?,?)',[$group,$date]);
        }elseif($type === 'district'){
            return getdbvalue($this->db->dbh,'SELECT getmmfopeningbalbydate_district(?,?)',[$group,$date]);
        }
        return 0;
    }

    /** The group / district ID that goes with the requisition's type, for GetBalance(). */
    public function OwnerId($request)
    {
        if($request->RequestType === 'group'){
            return $request->GroupId;
        }
        return $request->RequestType === 'district' ? $request->DistrictId : null;
    }

    public function CreateUpdate($data)
    {
        // the owner must belong to this congregation and match the type; an edit must be of a pending requisition of it
        $existing = null;
        if($data['isedit']){
            $existing = $this->GetRequest((int)$data['id']);
            if(!$existing || (int)$existing->CongregationId !== (int)$_SESSION['congId'] || (int)$existing->Status !== 0 || (int)$existing->Deleted === 1){
                return false;
            }
        }
        if($this->OwnerProblem($data['type'],$data['group'],$existing) !== null){
            return false;
        }

        if(!$data['isedit']){
            $this->db->query('INSERT INTO tblfundrequisition (ReqNo,RequisitionDate,RequestType,DistrictId,GroupId,ChurchCategoryId,Purpose,AmountRequested,RequestedBy,DontDeduct,CongregationId) 
                              VALUES(:reqno,:rdate,:rtype,:did,:gid,:catid,:purpose,:amount,:reqby,:deduct,:cid)');
            $this->db->bind(':reqno',$this->GetReqNo());
        }else{
            // only a pending requisition of this congregation can be edited
            $this->db->query('UPDATE tblfundrequisition SET RequisitionDate=:rdate,RequestType=:rtype,DistrictId=:did,GroupId=:gid,ChurchCategoryId=:catid,Purpose=:purpose,AmountRequested=:amount,DontDeduct=:deduct
                              WHERE (ID = :id) AND (CongregationId = :cid) AND (`Status` = 0) AND (Deleted = 0)');
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
        if(!$data['isedit']){
            // the requester stays the original one when someone else edits the requisition
            $this->db->bind(':reqby',$_SESSION['userId']);
        }
        // a church requisition has no group / district balance to deduct from: stored as 1 whatever the (disabled, never posted) box said
        $this->db->bind(':deduct',($data['type'] === 'church' || !empty($data['dontdeduct'])) ? 1 : 0);
        if($data['isedit']){
            $this->db->bind(':id',(int)$data['id']);
        }
        $this->db->bind(':cid',$_SESSION['congId']);
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    /**
     * Why the group / district / church category cannot be the requisition's owner (it must exist, be undeleted and belong to
     * the session congregation, and match the request type), or null. $existing = the stored row of an edit: its own group
     * may be inactive.
     */
    public function OwnerProblem($type,$id,$existing = null)
    {
        $cong = (int)$_SESSION['congId'];
        $id = (int)$id;
        if($type === 'group'){
            $keep = $existing && $existing->RequestType === 'group' ? (int)$existing->GroupId : 0;
            $n = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblgroups WHERE ID = ? AND congregationId = ? AND deleted = 0 AND (active = 1 OR ID = ?)',[$id,$cong,$keep]);
            return (int)$n > 0 ? null : 'Select a valid group of this congregation';
        }
        if($type === 'district'){
            $n = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tbldistricts WHERE ID = ? AND congregationId = ? AND deleted = 0',[$id,$cong]);
            return (int)$n > 0 ? null : 'Select a valid district of this congregation';
        }
        if($type === 'church'){
            $n = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblchurchrequisitioncategories WHERE id = ? AND congregationId = ? AND deleted = 0',[$id,$cong]);
            return (int)$n > 0 ? null : 'Select a valid church category of this congregation';
        }
        return 'Select a valid request type (group, district or church)';
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
        $this->db->query('UPDATE tblfundrequisition SET Deleted = 1 WHERE (ID = :id) AND (CongregationId = :cid) AND (`Status` = 0)');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
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

    /** "Paid from" accounts for a CASH pay method (PAY_CASH; 121 itself is not offered). [ (object)['ID','Account'] ] */
    public function GetCashAccounts()
    {
        return $this->accountOptions(Ledger::PAY_CASH);
    }

    /** "Paid from" accounts for M-Pesa / cheque / bank (PAY_BANK). [ (object)['ID','Account'] ] */
    public function GetBankAccounts()
    {
        return $this->accountOptions(Ledger::PAY_BANK);
    }

    /** "Receiving account" choices: every PAY_CASH account (121 included, preselected on the form). [ (object)['ID','Account'] ] */
    public function GetReceivingAccounts()
    {
        return $this->accountOptions(Ledger::PAY_CASH,true);
    }

    /** The account the requisition's active approval debited (its receiving account), or null when it has no active entry. */
    public function ReceivingAccountOf($reqId)
    {
        $entry = Ledger::getEntry($this->db->pdo(),(int)$_SESSION['congId'],self::TYPE,(int)$reqId);
        foreach (($entry['lines'] ?? []) as $l) {
            if ($l['dc'] === Ledger::DEBIT) {
                return (int)$l['accountId'];
            }
        }
        return null;
    }

    private function accountOptions($kind,$includeHolding = false)
    {
        $out = [];
        foreach(Ledger::listPaymentAccounts($this->db->pdo(),(int)$_SESSION['congId'],$kind) as $a){
            if(!$includeHolding && (int)$a['ID'] === Ledger::CASH_HOLDING){
                continue;
            }
            $out[] = (object)['ID' => (int)$a['ID'],'Account' => strtoupper($a['accountType'])];
        }
        return $out;
    }

    public function GetGroupName($type,$id)
    {
        if($type === 'group'){
            return getdbvalue($this->db->dbh,'SELECT groupName FROM tblgroups WHERE ID = ?',[$id]);
        }elseif($type === 'district'){
            return getdbvalue($this->db->dbh,'SELECT districtName FROM tbldistricts WHERE ID = ?',[$id]);
        }
        return getdbvalue($this->db->dbh,'SELECT categoryName FROM tblchurchrequisitioncategories WHERE id = ?',[$id]);
    }

    /** The requisition of this congregation, locked for the rest of the transaction, or false. */
    private function lockRequisition($pdo,$id,$cong)
    {
        $st = $pdo->prepare('SELECT * FROM tblfundrequisition WHERE ID = ? AND CongregationId = ? FOR UPDATE');
        $st->execute([$id,$cong]);
        return $st->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Only the requisition's stored row decides group / district, type, DontDeduct, purpose and the requested amount
     * (hotfix); the form supplies id, approved amount, pay date, reference, pay method and the "paid from" account.
     */
    public function Approve($data)
    {
        $reference = !empty($data['reference']) ? strtolower(trim($data['reference'])) : null;
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();

            $req = $this->lockRequisition($pdo,(int)$data['id'],$cong);
            if(!$req || (int)$req->Deleted !== 0){
                return $this->refuseAction($pdo,'Requisition not found',true);
            }
            if((int)$req->Status !== 0){
                return $this->refuseAction($pdo,'This requisition has already been '.((int)$req->Status === 1 ? 'approved' : 'rejected').'. Nothing was posted',true);
            }

            $approved = $data['approved'];
            if(!is_numeric($approved) || (float)$approved <= 0 || round((float)$approved,2) !== (float)$approved){
                return $this->refuseAction($pdo,'Enter a valid amount to approve (greater than zero, at most 2 decimals)');
            }
            $approved = (float)$approved;
            if($approved > (float)$req->AmountRequested){
                return $this->refuseAction($pdo,'Payment amount greater than amount requested');
            }

            $paydate = DateTime::createFromFormat('!Y-m-d',(string)$data['paydate']);
            if(!$paydate || $paydate->format('Y-m-d') !== $data['paydate']){
                return $this->refuseAction($pdo,'Select a valid payment date');
            }
            if($data['paydate'] > date('Y-m-d')){
                return $this->refuseAction($pdo,'Payment date cannot be in the future');
            }

            if(is_null($reference)){
                return $this->refuseAction($pdo,'Enter payment reference');
            }
            if(mb_strlen($reference) > self::REFERENCE_LEN){
                return $this->refuseAction($pdo,'Reference cannot be longer than '.self::REFERENCE_LEN.' characters');
            }

            $method = (int)($data['paymethod'] ?? 0);
            if((int)getdbvalue($pdo,'SELECT COUNT(*) FROM tblpaymentmethods WHERE ID = ?',[$method]) === 0){
                return $this->refuseAction($pdo,'Select a valid pay method');
            }
            $isBank = $method !== self::CASH_METHOD;
            $paidfrom = (int)($data['paidfrom'] ?? 0);
            if($paidfrom === Ledger::CASH_HOLDING){
                return $this->refuseAction($pdo,'The cash holding account only pays the expenses of a requisition: it cannot be the paid-from account of an approval');
            }
            try {
                Ledger::assertPaymentAccount($pdo,$cong,$paidfrom,$isBank ? Ledger::PAY_BANK : Ledger::PAY_CASH);
            } catch (LedgerException $e) {
                return $this->refuseAction($pdo,$isBank ? 'Select the bank account it is paid from' : 'Select the cash account it is paid from');
            }

            // the receiving (debit) account: chosen on the form, never inferred; a child, active cash account from the offered list
            $rawReceiving = $data['receiving'] ?? null;
            if($rawReceiving === null || $rawReceiving === '' || $rawReceiving === 0 || $rawReceiving === '0'){
                return $this->refuseAction($pdo,'Select the account the funds are received into');
            }
            if(!is_int($rawReceiving) && !(is_string($rawReceiving) && ctype_digit($rawReceiving))){
                return $this->refuseAction($pdo,'Select a valid receiving account (an active cash account)');   // e.g. '121abc' is not 121
            }
            $receiving = (int)$rawReceiving;
            if($receiving <= 0){
                return $this->refuseAction($pdo,'Select a valid receiving account (an active cash account)');
            }
            $offered = array_map(function ($a) { return (int)$a->ID; },$this->GetReceivingAccounts());
            if(!in_array($receiving,$offered,true)){
                return $this->refuseAction($pdo,'Select a valid receiving account (an active cash account)');
            }
            try {
                Ledger::assertPaymentAccount($pdo,$cong,$receiving,Ledger::PAY_CASH);
            } catch (LedgerException $e) {
                return $this->refuseAction($pdo,'Select a valid receiving account (an active cash account)');
            }
            if($receiving === $paidfrom){
                return $this->refuseAction($pdo,'The receiving account and the paid-from account cannot be the same');
            }

            $type = $req->RequestType;
            $dontdeduct = (int)$req->DontDeduct === 1;
            if($type === 'group'){
                $groupid = $req->GroupId;
            }elseif($type === 'district'){
                $groupid = $req->DistrictId;
            }else{
                $groupid = null;
            }
            $groupname = $this->GetGroupName($type,$groupid ?? $req->ChurchCategoryId);
            $desc = strtolower((string)$groupname) .' funds request approval';
            $narration = mb_substr(strtoupper((string)$req->Purpose),0,150);

            $st = $pdo->prepare('UPDATE tblfundrequisition
                                    SET AmountApproved = ?,ApprovalDate = ?,ApprovedBy = ?,`Status` = 1
                                  WHERE (ID = ?) AND (CongregationId = ?) AND (`Status` = 0)');
            $st->execute([$approved,date('Y-m-d'),(int)$_SESSION['userId'],(int)$req->ID,$cong]);
            if($st->rowCount() !== 1){
                return $this->refuseAction($pdo,'This requisition has already been approved. Nothing was posted',true);
            }

            $id = (int)$req->ID;
            $amount = number_format($approved,2,'.','');

            // DontDeduct (stored) only decides this row; a church requisition has no group / district to deduct from
            if(!$dontdeduct && $groupid !== null){
                $pdo->prepare('INSERT INTO tblmmf (TransactionDate,Type,DistrictId,GroupId,Credit,BankId,Reference,Narration,TransactionType,TransactionId,
                               CongregationId) VALUES(?,?,?,?,?,?,?,?,12,?,?)')
                    ->execute([$data['paydate'],$type,$type === 'district' ? $groupid : null,$type === 'group' ? $groupid : null,
                               $amount,$isBank ? $paidfrom : null,$reference,$narration,$id,$cong]);
            }

            Ledger::post($pdo,[
                'congregationId' => $cong,'entryDate' => $data['paydate'],'transactionTypeId' => self::TYPE,'transactionId' => $id,
                'reference' => $reference,'description' => $desc,
            ],[
                Ledger::debit($receiving,$amount,$desc),
                Ledger::credit($paidfrom,$amount,$desc),
            ]);
            if($isBank){
                Ledger::syncBankPostings($pdo,$cong,self::TYPE,$id,self::BANK_METHOD,$reference);
            }

            saveLog($pdo,'Approved fund requisition ' . $id . ' (req no ' . $req->ReqNo . ') for ' . $amount . ' dated ' . $data['paydate']);
            $pdo->commit();
        } catch (LedgerException $e) {
            return $this->refuseAction($pdo,implode('. ',$e->getErrors()));
        } catch (Exception $e) {
            if($pdo->inTransaction()){
                $pdo->rollback();
            }
            error_log($e->getMessage(),0);
            return ['error' => 'Unable to save this approval, nothing was posted. Contact admin for help','final' => false];
        }

        return $this->contactOf((int)$req->ID);
    }

    /** The requester's contact, else true. After a commit: a failure here must never read as a failed action. */
    private function contactOf($id)
    {
        try {
            $requestedby = getdbvalue($this->db->dbh,'SELECT RequestedBy FROM tblfundrequisition WHERE ID=?',[(int)$id]);
            if(is_null($requestedby)) return true;

            $contact = getdbvalue($this->db->dbh,'SELECT contact FROM tblusers WHERE ID=?',[(int)$requestedby]);
            return $contact ? $contact : true;
        } catch (Exception $e) {
            error_log($e->getMessage(),0);
            return true;
        }
    }

    private function refuseAction($pdo,$message,$final = false)
    {
        if($pdo->inTransaction()){
            $pdo->rollback();
        }
        return ['error' => $message,'final' => $final];
    }

    /** Message listing the non-deleted expenses (draft or approved) that reference the requisition, or null. */
    private function expenseBlock($pdo,$cong,$id,$action)
    {
        $st = $pdo->prepare('SELECT voucherNo,expenseDate,`status` FROM tblexpenses
                             WHERE requisitionId = ? AND congregationId = ? AND deleted = 0 ORDER BY ID');
        $st->execute([$id,$cong]);
        $rows = $st->fetchAll(PDO::FETCH_OBJ);
        if(!$rows){
            return null;
        }
        $list = array_map(function ($r) {
            return 'voucher no ' . $r->voucherNo . ' (' . $r->expenseDate . ', ' . ((int)$r->status === 1 ? 'approved' : 'draft') . ')';
        },array_slice($rows,0,10));
        return 'Cannot ' . $action . ': ' . count($rows) . ' expense(s) reference this requisition: ' . implode(', ',$list)
             . (count($rows) > 10 ? ' and ' . (count($rows) - 10) . ' more' : '')
             . '. Delete them or remove the requisition from them first';
    }

    /**
     * Shared body of Unapprove / Reject / Reverse. Unapprove (approved -> pending) and Reject (pending -> rejected) are refused while an expense references the requisition, then void the entry and its bank rows
     * (a closed financial year refuses) and delete the requisition's tblmmf row (this congregation only).
     * Reverse (rejected -> pending) has nothing to void.
     */
    private function changeState($id,$action,$reason = null)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();

            $req = $this->lockRequisition($pdo,(int)$id,$cong);
            if(!$req || (int)$req->Deleted !== 0){
                return $this->refuseAction($pdo,'Requisition not found',true);
            }
            $status = (int)$req->Status;
            if($action === 'unapprove' && $status !== 1){
                return $this->refuseAction($pdo,'Only an approved requisition can be unapproved',true);
            }
            if($action === 'reject' && $status !== 0){
                return $this->refuseAction($pdo,'Only a pending requisition can be rejected (unapprove an approved one first)',true);
            }
            if($action === 'reverse' && $status !== 2){
                return $this->refuseAction($pdo,'Only a rejected requisition can be reversed',true);
            }

            if($action === 'reverse'){
                $pdo->prepare('UPDATE tblfundrequisition SET `Status` = 0, ReasonForRejection = NULL
                               WHERE ID = ? AND CongregationId = ? AND `Status` = 2')->execute([(int)$req->ID,$cong]);
            }else{
                if($block = $this->expenseBlock($pdo,$cong,(int)$req->ID,$action)){
                    return $this->refuseAction($pdo,$block,true);
                }
                if($m = Ledger::clearedBankProblem($pdo,$cong,self::TYPE,(int)$req->ID)){
                    return $this->refuseAction($pdo,$m,true);
                }
                Ledger::void($pdo,$cong,self::TYPE,(int)$req->ID);
                Ledger::voidBankPostings($pdo,$cong,self::TYPE,(int)$req->ID);
                $pdo->prepare('DELETE FROM tblmmf WHERE TransactionType = 12 AND TransactionId = ? AND CongregationId = ?')
                    ->execute([(int)$req->ID,$cong]);
                $pdo->prepare('UPDATE tblfundrequisition
                                  SET AmountApproved = NULL, ApprovalDate = NULL, ApprovedBy = NULL, `Status` = ?, ReasonForRejection = ?
                                WHERE ID = ? AND CongregationId = ? AND `Status` = ?')
                    ->execute([$action === 'reject' ? 2 : 0,$action === 'reject' ? $reason : null,(int)$req->ID,$cong,$status]);
            }

            saveLog($pdo,ucfirst($action) . ' fund requisition ' . (int)$req->ID . ' (req no ' . $req->ReqNo . ')');
            $pdo->commit();
        } catch (LedgerException $e) {
            return $this->refuseAction($pdo,implode('. ',$e->getErrors()),true);
        } catch (Exception $e) {
            if($pdo->inTransaction()){
                $pdo->rollback();
            }
            error_log($e->getMessage(),0);
            return ['error' => 'Unable to complete this action, nothing was changed. Contact admin for help','final' => false];
        }

        return $this->contactOf((int)$id);
    }

    public function Reverse($id)
    {
        return $this->changeState($id,'reverse');
    }

    public function Reject($id,$reason)
    {
        return $this->changeState($id,'reject',$reason);
    }

    public function Unapprove($id)
    {
        return $this->changeState($id,'unapprove');
    }
}

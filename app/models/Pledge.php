<?php
class Pledge
{
    const TYPE_INITIAL = 3;
    const TYPE_PAYMENT = 4;
    const BANK_METHOD = 'Deposit';
    private $db;
    public function __construct() { $this->db = new Database; }
    public function CheckRights($form) { return getUserAccess($this->db->dbh,$_SESSION['userId'],$form,$_SESSION['isParish']) > 0; }
    public function index() { $this->db->query('SELECT * FROM vw_pledgewithbalances WHERE congregationId=:id AND deleted=0'); $this->db->bind(':id',(int)$_SESSION['congId']); return $this->db->resultSet(); }
    public function paymentMethods() { return paymentMethods($this->db->dbh); }

    private function labelled(array $rows): array
    {
        foreach($rows as &$r){$name=is_array($r)?$r['accountType']:$r->accountType;$label=strtoupper($name);if(is_array($r)){$r=(object)['ID'=>(int)$r['ID'],'accountType'=>$name,'label'=>$label];}else{$r->label=$label;}}
        unset($r); return $rows;
    }
    public function getCashAccounts(): array { return $this->labelled(Ledger::listPaymentAccounts($this->db->dbh,(int)$_SESSION['congId'],Ledger::PAY_CASH)); }
    public function getBanks(): array { return $this->labelled(Ledger::listPaymentAccounts($this->db->dbh,(int)$_SESSION['congId'],Ledger::PAY_BANK)); }
    public function getIncomeAccounts(): array
    {
        $st=$this->db->dbh->prepare('SELECT ID,accountType FROM tblaccounttypes WHERE accountTypeId=1 AND active=1 AND deleted=0 AND isBank=0 AND congregationId IN (0,?)');$st->execute([(int)$_SESSION['congId']]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
        $ok=array_flip(Ledger::filterPostingAccounts($this->db->dbh,(int)$_SESSION['congId'],array_column($rows,'ID')));$rows=array_values(array_filter($rows,function($r)use($ok){return isset($ok[(int)$r['ID']]);}));return $this->labelled($rows);
    }
    public function getPledger($category)
    {
        $map=[1=>['tblmember','memberName','memberStatus=1'],2=>['tblgroups','groupName','active=1'],3=>['tbldistricts','districtName','1=1']];if(!isset($map[(int)$category])){return [];}
        [$table,$name,$extra]=$map[(int)$category];$this->db->query("SELECT ID,UCASE($name) pledger FROM $table WHERE deleted=0 AND $extra AND congregationId=:cid ORDER BY pledger");$this->db->bind(':cid',(int)$_SESSION['congId']);return $this->db->resultSet();
    }
    private function dateErrors($date): array { if(!date_is_valid((string)$date)){return ['Enter a valid date'];}if($date>date('Y-m-d')){return ['Future dates are not allowed'];}$p=Ledger::dateProblem($this->db->dbh,$date);return $p===null?[]:[$p]; }
    private function assertIncome($value): int { $id=(int)$value;$ids=array_map(function($a){return(int)$a->ID;},$this->getIncomeAccounts());if(!in_array($id,$ids,true)){throw new LedgerException(['Select a valid income account']);}return $id; }
    private function payment(array $data): array
    {
        $errors=[];$method=(int)($data['paymethod']??0);$st=$this->db->dbh->prepare('SELECT COUNT(*) FROM tblpaymentmethods WHERE ID=?');$st->execute([$method]);if($method<=0||!(int)$st->fetchColumn()){$errors[]='Select a valid payment method';}
        $reference=trim((string)($data['reference']??''));$account=0;$bank=null;
        if($method===1){if(empty($data['cashaccount'])){$errors[]='Select the cash account';}else{try{$account=Ledger::assertPaymentAccount($this->db->dbh,(int)$_SESSION['congId'],(int)$data['cashaccount'],Ledger::PAY_CASH);}catch(LedgerException $e){$errors=array_merge($errors,$e->getErrors());}}}
        elseif($method>1){if($reference===''){$errors[]='Enter Payment Reference';}if(empty($data['bank'])){$errors[]='Select Bank';}else{try{$account=$bank=Ledger::assertPaymentAccount($this->db->dbh,(int)$_SESSION['congId'],(int)$data['bank'],Ledger::PAY_BANK);}catch(LedgerException $e){$errors=array_merge($errors,$e->getErrors());}}}
        if(strlen($reference)>50){$errors[]='Reference cannot be longer than 50 characters';}if($errors){throw new LedgerException(array_values(array_unique($errors)));}return ['method'=>$method,'account'=>$account,'bank'=>$bank,'reference'=>$reference];
    }
    private function pledger(int $category,int $id): ?object { foreach($this->getPledger($category) as $p){if((int)$p->ID===$id){return $p;}}return null; }

    public function create($data): array
    {
        try{
            $errors=$this->dateErrors((string)($data['date']??''));if($e=Ledger::amountError($data['amountpledged']??'','Amount pledged')){$errors[]=$e;}$paid=trim((string)($data['amountpaid']??''));if($paid===''){$paid='0';}if(!in_array($paid,['0','0.0','0.00'],true)&&($e=Ledger::amountError($paid,'Initial payment'))){$errors[]=$e;}
            $category=(int)($data['category']??0);$person=$this->pledger($category,(int)($data['pledger']??0));if(!$person){$errors[]='Select a valid pledger for this congregation';}$income=$this->assertIncome($data['incomeaccount']??0);
            $pledgedC=(int)round((float)($data['amountpledged']??0)*100);$paidC=(int)round((float)$paid*100);if($paidC>$pledgedC){$errors[]='Initial payment cannot be more than the amount pledged';}$pay=null;if($paidC>0){$pay=$this->payment($data);}if($errors){throw new LedgerException(array_values(array_unique($errors)));}
            $member=$category===1?(int)$data['pledger']:null;$group=$category===2?(int)$data['pledger']:null;$district=$category===3?(int)$data['pledger']:null;$pdo=$this->db->dbh;$pdo->beginTransaction();
            $pdo->prepare('INSERT INTO tblpledges_header (pledgeDate,category,pledgerMember,pledgerGroup,pledgerDistrict,amountPledged,IncomeAccountId,congregationId) VALUES (?,?,?,?,?,?,?,?)')->execute([$data['date'],$category,$member,$group,$district,number_format($pledgedC/100,2,'.',''),$income,(int)$_SESSION['congId']]);$id=(int)$pdo->lastInsertId();
            if($paidC>0){$balance=$pledgedC-$paidC;$pdo->prepare('INSERT INTO tblpledges_details (pledgeId,paymentDate,openingBal,amountPaid,balance,paymethodId,bankId,PaymentAccountId,payReference) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$id,$data['date'],number_format($pledgedC/100,2,'.',''),number_format($paidC/100,2,'.',''),number_format($balance/100,2,'.',''),$pay['method'],$pay['bank'],$pay['account'],$pay['reference']?:null]);$narr='Pledge payment for '.strtolower($person->pledger);
                Ledger::post($pdo,['congregationId'=>(int)$_SESSION['congId'],'entryDate'=>$data['date'],'transactionTypeId'=>self::TYPE_INITIAL,'transactionId'=>$id,'reference'=>$pay['reference'],'description'=>$narr],[['accountId'=>$pay['account'],'dc'=>Ledger::DEBIT,'amount'=>$paidC/100,'memo'=>$narr],['accountId'=>$income,'dc'=>Ledger::CREDIT,'amount'=>$paidC/100,'memo'=>$narr]]);Ledger::syncBankPostings($pdo,(int)$_SESSION['congId'],self::TYPE_INITIAL,$id,self::BANK_METHOD,$pay['reference']);}
            saveLog($pdo,'Created Pledge For '.strtolower($person->pledger));$pdo->commit();return ['success'=>true,'id'=>$id];
        }catch(LedgerException $e){if($this->db->dbh->inTransaction()){$this->db->dbh->rollBack();}return ['success'=>false,'errors'=>$e->getErrors()];}catch(Throwable $e){if($this->db->dbh->inTransaction()){$this->db->dbh->rollBack();}error_log($e->getMessage());return ['success'=>false,'errors'=>['Something went wrong while saving the pledge']];}
    }
    public function getPledge($id)
    {
        if(!ctype_digit((string)$id)||(int)$id<=0){return null;}$this->db->query("SELECT IF(h.category=1,UCASE(m.memberName),IF(h.category=2,UCASE(g.groupName),UCASE(d.districtName))) pledger,h.amountPledged,COALESCE(SUM(pd.amountPaid),0) totalPaid,h.amountPledged-COALESCE(SUM(pd.amountPaid),0) balance,h.congregationId,h.deleted,h.ID,h.IncomeAccountId,a.accountType IncomeAccount FROM tblpledges_header h LEFT JOIN tblpledges_details pd ON pd.pledgeId=h.ID LEFT JOIN tblmember m ON h.pledgerMember=m.ID LEFT JOIN tblgroups g ON h.pledgerGroup=g.ID LEFT JOIN tbldistricts d ON h.pledgerDistrict=d.ID LEFT JOIN tblaccounttypes a ON a.ID=h.IncomeAccountId WHERE h.ID=:id AND h.congregationId=:cid AND h.deleted=0 GROUP BY h.ID");$this->db->bind(':id',(int)$id);$this->db->bind(':cid',(int)$_SESSION['congId']);return $this->db->single();
    }
    public function pay($data): array
    {
        $pdo=$this->db->dbh;try{$errors=$this->dateErrors((string)($data['date']??''));if($e=Ledger::amountError($data['paid']??'','Payment')){$errors[]=$e;}$pay=$this->payment($data);if($errors){throw new LedgerException($errors);}$pdo->beginTransaction();
            $st=$pdo->prepare('SELECT * FROM tblpledges_header WHERE ID=? AND congregationId=? AND deleted=0 FOR UPDATE');$st->execute([(int)($data['id']??0),(int)$_SESSION['congId']]);$h=$st->fetch(PDO::FETCH_ASSOC);if(!$h){throw new LedgerException(['Pledge not found for this congregation']);}$st=$pdo->prepare('SELECT COALESCE(SUM(amountPaid),0) FROM tblpledges_details WHERE pledgeId=?');$st->execute([$h['ID']]);$total=(float)$st->fetchColumn();$remaining=round((float)$h['amountPledged']-$total,2);$amount=round((float)$data['paid'],2);if($amount>$remaining){throw new LedgerException(['Payment cannot be more than the remaining balance']);}
            $income=(int)$h['IncomeAccountId'];if($income<=0){$income=$this->assertIncome($data['incomeaccount']??0);$pdo->prepare('UPDATE tblpledges_header SET IncomeAccountId=? WHERE ID=?')->execute([$income,$h['ID']]);}elseif($p=Ledger::postingProblem($pdo,$income,(int)$_SESSION['congId'])){throw new LedgerException(["The pledge income account cannot be posted to ($p)"]);}$balance=round($remaining-$amount,2);
            $pdo->prepare('INSERT INTO tblpledges_details (pledgeId,paymentDate,openingBal,amountPaid,balance,paymethodId,bankId,PaymentAccountId,payReference) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$h['ID'],$data['date'],number_format($remaining,2,'.',''),number_format($amount,2,'.',''),number_format($balance,2,'.',''),$pay['method'],$pay['bank'],$pay['account'],$pay['reference']?:null]);$tid=(int)$pdo->lastInsertId();$pledge=$this->getPledge($h['ID']);$narr='Pledge payment for '.strtolower($pledge?$pledge->pledger:'pledger');
            Ledger::post($pdo,['congregationId'=>(int)$_SESSION['congId'],'entryDate'=>$data['date'],'transactionTypeId'=>self::TYPE_PAYMENT,'transactionId'=>$tid,'reference'=>$pay['reference'],'description'=>$narr],[['accountId'=>$pay['account'],'dc'=>Ledger::DEBIT,'amount'=>$amount,'memo'=>$narr],['accountId'=>$income,'dc'=>Ledger::CREDIT,'amount'=>$amount,'memo'=>$narr]]);Ledger::syncBankPostings($pdo,(int)$_SESSION['congId'],self::TYPE_PAYMENT,$tid,self::BANK_METHOD,$pay['reference']);saveLog($pdo,$narr);$pdo->commit();return ['success'=>true,'id'=>$tid];
        }catch(LedgerException $e){if($pdo->inTransaction()){$pdo->rollBack();}return ['success'=>false,'errors'=>$e->getErrors()];}catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}error_log($e->getMessage());return ['success'=>false,'errors'=>['Something went wrong while saving the payment']];}
    }
    public function delete($data): array
    {
        $pdo=$this->db->dbh;try{$pdo->beginTransaction();$id=(int)($data['id']??0);$cong=(int)$_SESSION['congId'];$st=$pdo->prepare('SELECT ID FROM tblpledges_header WHERE ID=? AND congregationId=? AND deleted=0 FOR UPDATE');$st->execute([$id,$cong]);if(!$st->fetchColumn()){throw new LedgerException(['Pledge not found for this congregation']);}$st=$pdo->prepare('SELECT ID FROM tblpledges_details WHERE pledgeId=? ORDER BY ID FOR UPDATE');$st->execute([$id]);$detailIds=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));$sources=[[self::TYPE_INITIAL,$id]];foreach($detailIds as $d){$sources[]=[self::TYPE_PAYMENT,$d];}foreach($sources as [$type,$txn]){if($p=Ledger::clearedBankProblem($pdo,$cong,$type,$txn)){throw new LedgerException([$p]);}}foreach($sources as [$type,$txn]){Ledger::void($pdo,$cong,$type,$txn);Ledger::voidBankPostings($pdo,$cong,$type,$txn);}$pdo->prepare('UPDATE tblpledges_header SET deleted=1 WHERE ID=? AND congregationId=?')->execute([$id,$cong]);saveLog($pdo,'Deleted pledge '.$id);$pdo->commit();return ['success'=>true];
        }catch(LedgerException $e){if($pdo->inTransaction()){$pdo->rollBack();}return ['success'=>false,'errors'=>$e->getErrors()];}catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}error_log($e->getMessage());return ['success'=>false,'errors'=>['Something went wrong while deleting the pledge']];}
    }
}

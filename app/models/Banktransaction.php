<?php
class Banktransaction
{
    private $db;

    public function __construct()
    {
        $this->db =  new Database;
    }

    public function GetTransactions()
    {
        $sql = 'SELECT * FROM vw_banktransactions WHERE CongregationId = ?';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetOtherBanks()
    {
        $sql = "SELECT ID,UCASE(CONCAT(accountType,'-',IFNULL(accountNo,''))) AS Bank FROM tblaccounttypes WHERE (isBank = 1 AND CongregationId=? AND deleted=0) OR (accountType = ?) OR (accountType = ?)";
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId'],'fixed deposits','petty cash']);
    }

    public function GetBanks()
    {
        $sql = "SELECT ID,UCASE(CONCAT(accountType,'-',IFNULL(accountNo,''))) AS Bank FROM tblaccounttypes WHERE isBank = 1 AND CongregationId = ? AND deleted=0";
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function getAccountName($account)
    {
        //getname
        $accountDetails = array();
        $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        $accName = $this->db->getValue();
        array_push($accountDetails,$accName);

        $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        $accountId = $this->db->getValue();
        array_push($accountDetails,$accountId);

        return $accountDetails;
    }

    public function GetReceiptNo()
    {
        return getuniqueid($this->db->dbh,'ReceiptNo','tblpettycash',(int)$_SESSION['congId']);
    }

    public function GetSubaccounts($bankid)
    {
        return loadresultset($this->db->dbh,'SELECT ID,UCASE(AccountName) AS SubAccount FROM tblbanksubaccounts WHERE (BankId=?) AND (Deleted=0)',[$bankid]);
    }

    public function Save($data)
    {
        try {
            
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblbanktransactions (TransactionDate,TransactionTypeId,BankId,Amount,TransferToId,
                                                               Reference,`Description`,DepositToSubAccounts,CongregationId) 
                              VALUES(:ddate,:tid,:bid,:amount,:trans,:reference,:narr,:subs, :cid)');
            $this->db->bind(':ddate',$data['date']);
            $this->db->bind(':tid',$data['type']);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':amount',$data['amount']);
            $this->db->bind(':trans',$data['transfer']);
            $this->db->bind(':reference',$data['reference']);
            $this->db->bind(':narr',$data['description']);
            $this->db->bind(':subs',$data['deposittosubs']);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();
            $tid = $this->db->dbh->lastInsertId();

            for ($i=0; $i < count($data['subaccounts']); $i++) {
                $this->db->query('INSERT INTO `tblbanktransactions_subaccounts`(`TransactionId`, `SubAccountId`, `Amount`) 
                                  VALUES (:tid,:sub,:amount)');
                $this->db->bind(':tid',$tid);
                $this->db->bind(':sub',$data['subaccounts'][$i]->accountid);
                $this->db->bind(':amount',$data['subaccounts'][$i]->amount);
                $this->db->execute();
            }

            $narr = !empty($data['description']) ? strtolower($data['description']) : 'bank transaction reference ' .$data['reference'];
            $cabparent = getparentgl($this->db->dbh,'cash at bank');

            if((int)$data['type'] === 1){
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,$data['amount'],0,$narr,
                            3,13,$tid,$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cabparent,0,$data['amount'],$narr,
                            3,13,$tid,$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$data['date'],$data['amount'],0,1,
                          $data['reference'],13,$tid,$_SESSION['congId']);
            }elseif((int)$data['type'] === 2){
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,0,$data['amount'],$narr,
                            3,13,$tid,$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cabparent,$data['amount'],0,$narr,
                            3,13,$tid,$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],1,
                             $data['reference'],13,$tid,$_SESSION['congId']);
            }elseif((int)$data['type'] === 5){
                $pid = $data['transfer'];
                $pname = $this->getAccountName($pid)[0];
                $accountid = $this->getAccountName($pid)[1];
                $parentaccountname = getparentgl($this->db->dbh,$pname);
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,0,$data['amount'],$narr,
                            3,13,$tid,$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],$pname,$parentaccountname,$data['amount'],0,$narr,
                            $accountid,13,$tid,$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],1,
                             $data['reference'],13,$tid,$_SESSION['congId']);
            }

            if($data['transfer'] == '98'){
                $this->db->query('INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,Reference,Narration,TransactionType,TransactionId,CongregationId)
                                  VALUES(:rno,:tdate,:debit,:isreceipt,:bankid,:reference,:narr,:ttype,:tid,:cid)');
                $this->db->bind(':rno',$this->GetReceiptNo());
                $this->db->bind(':tdate',$data['date']);
                $this->db->bind(':debit',$data['amount']);
                $this->db->bind(':isreceipt',true);
                $this->db->bind(':bankid',$data['bank']);
                $this->db->bind(':reference',strtolower($data['reference']));
                $this->db->bind(':narr',$narr);
                $this->db->bind(':ttype',13);
                $this->db->bind(':tid',$tid);
                $this->db->bind(':cid',$_SESSION['congId']);
                $this->db->execute();       
            }

            if(!$this->db->dbh->commit()){
                return false;
            }
             
            return true;

        } catch (PDOException $th) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            error_log($th->getMessage(),0);
            return false;
        }
    }

    public function GetSavedSubsAccounts($id)
    {
        $sql = 'SELECT s.SubAccountId,ucase(b.AccountName) As Account 
                FROM `tblbanktransactions_subaccounts` s join tblbanksubaccounts b on s.SubAccountId = b.ID 
                WHERE s.TransactionId = ?';
        return loadresultset($this->db->dbh,$sql,[$id]);
    }

    public function Update($data)
    {
        try {
            
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblbanktransactions SET TransactionDate=:ddate,TransactionTypeId=:tid,BankId=:bid,Amount=:amount,
                                                             TransferToId=:trans,Reference=:reference,`Description`=:narr 
                              WHERE (ID=:id)');
            $this->db->bind(':ddate',$data['date']);
            $this->db->bind(':tid',$data['type']);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':amount',$data['amount']);
            $this->db->bind(':trans',$data['transfer']);
            $this->db->bind(':reference',$data['reference']);
            $this->db->bind(':narr',$data['description']);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            
            deleteLedgerBanking($this->db->dbh,13,$data['id']);

            $this->db->query('DELETE FROM tblpettycash WHERE (TransactionTypeId = 13) AND (TransactionId=:tid)');
            $this->db->bind(':tid',$data['id']);
            $this->db->execute();

            $this->db->query('DELETE FROM tblbanktransactions_subaccounts WHERE (TransactionId = :tid)');
            $this->db->bind(':tid',$data['id']);
            $this->db->execute();

            for ($i=0; $i < count($data['subaccounts']); $i++) {
                $this->db->query('INSERT INTO `tblbanktransactions_subaccounts`(`TransactionId`, `SubAccountId`, `Amount`) 
                                  VALUES (:tid,:sub,:amount)');
                $this->db->bind(':tid',$data['id']);
                $this->db->bind(':sub',$data['subaccounts'][$i]->accountid);
                $this->db->bind(':amount',$data['subaccounts'][$i]->amount);
                $this->db->execute();
            }


            $narr = !empty($data['description']) ? strtolower($data['description']) : 'bank transaction reference ' .$data['reference'];
            $cabparent = getparentgl($this->db->dbh,'cash at bank');

            if((int)$data['type'] === 1){
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,$data['amount'],0,$narr,
                            3,13,$data['id'],$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cabparent,0,$data['amount'],$narr,
                            3,13,$data['id'],$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$data['date'],$data['amount'],0,1,
                          $data['reference'],13,$data['id'],$_SESSION['congId']);
            }elseif((int)$data['type'] === 2){
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,0,$data['amount'],$narr,
                            3,13,$data['id'],$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cabparent,$data['amount'],0,$narr,
                            3,13,$data['id'],$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],1,
                             $data['reference'],13,$data['id'],$_SESSION['congId']);
            }elseif((int)$data['type'] === 5){
                $pid = $data['transfer'];
                $pname = $this->getAccountName($pid)[0];
                $accountid = $this->getAccountName($pid)[1];
                $parentaccountname = getparentgl($this->db->dbh,$pname);
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,0,$data['amount'],$narr,
                            3,13,$data['id'],$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],$pname,$parentaccountname,$data['amount'],0,$narr,
                            $accountid,13,$data['id'],$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],1,
                             $data['reference'],13,$data['id'],$_SESSION['congId']);
            }

            if($data['transfer'] == '98'){
                $this->db->query('INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,Reference,Narration,TransactionType,TransactionId,CongregationId)
                                  VALUES(:rno,:tdate,:debit,:isreceipt,:bankid,:reference,:narr,:ttype,:tid,:cid)');
                $this->db->bind(':rno',$this->GetReceiptNo());
                $this->db->bind(':tdate',$data['date']);
                $this->db->bind(':debit',$data['amount']);
                $this->db->bind(':isreceipt',true);
                $this->db->bind(':bankid',$data['bank']);
                $this->db->bind(':reference',strtolower($data['reference']));
                $this->db->bind(':narr',$narr);
                $this->db->bind(':ttype',13);
                $this->db->bind(':tid',$data['id']);
                $this->db->bind(':cid',$_SESSION['congId']);
                $this->db->execute();       
            }

            if(!$this->db->dbh->commit()){
                return false;
            }
             
            return true;

        } catch (PDOException $th) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            error_log($th->getMessage(),0);
            return false;
        }
    }

    public function CreateUpdate($data)
    {
        if(!$data['isedit']){
            return $this->Save($data);
        }else{
            return $this->Update($data);
        }
    }

    public function GetTransaction($id)
    {
        $this->db->query('SELECT * FROM tblbanktransactions WHERE ID=:id AND Deleted=0');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }

    public function Delete($id)
    {
        try {
            
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblbanktransactions SET Deleted = 1 
                              WHERE (ID=:id)');
            $this->db->bind(':id',$id);
            $this->db->execute();
            
            softdeleteLedgerBanking($this->db->dbh,13,$id);

            $this->db->query('UPDATE tblpettycash SET Deleted=1 WHERE (TransactionTypeId = 13) AND (TransactionId=:tid)');
            $this->db->bind(':tid',$id);
            $this->db->execute();
            
            if(!$this->db->dbh->commit()){
                return false;
            }
             
            return true;

        } catch (PDOException $th) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollback();
            }
            error_log($th->getMessage(),0);
            return false;
        }
    }
}
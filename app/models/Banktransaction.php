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

    public function GetBanks()
    {
        $sql = "SELECT ID,UCASE(CONCAT(accountType,'-',IFNULL(accountNo,''))) AS Bank FROM tblaccounttypes WHERE isBank = 1 AND CongregationId = ?";
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

    public function Save($data)
    {
        try {
            
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblbanktransactions (TransactionDate,TransactionTypeId,BankId,Amount,TransferToId,
                                                               Reference,`Description`,CongregationId) 
                              VALUES(:ddate,:tid,:bid,:amount,:trans,:reference,:narr,:cid)');
            $this->db->bind(':ddate',$data['date']);
            $this->db->bind(':tid',$data['type']);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':amount',$data['amount']);
            $this->db->bind(':trans',$data['transfer']);
            $this->db->bind(':reference',$data['reference']);
            $this->db->bind(':narr',$data['description']);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();
            $tid = $this->db->dbh->lastInsertId();

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
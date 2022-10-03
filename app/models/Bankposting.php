<?php

class Bankposting
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
    public function getPostings()
    {
        $this->db->query('SELECT b.ID,
		                         b.transactionDate,
                                 ucase(a.accountType) As Bank,
                                 IF(b.debit > 0,b.debit,b.credit) As Amount,
                                 ucase(b.reference) As reference
                          FROM   tblbankpostings b inner join tblaccounttypes a on b.bankId = a.ID
                          WHERE  (b.transactionType = 9) AND (b.deleted=0) AND (b.congregationId=:cid)
                          ORDER BY b.ID DESC');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getBanks()
    {
        $this->db->query("SELECT   ID,
                                   CONCAT(UCASE(`accountType`),'-',IFNULL(`accountNo`,'')) As Bank
                          FROM     tblaccounttypes 
                          WHERE    (isBank=1) AND (Deleted=0) AND (congregationId=:cid)");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getMethods()
    {
        $this->db->query('SELECT   ID,
                                   UCASE(methodName) As methodName
                          FROM     tbltransactionmethods 
                          WHERE    (ID < 4)');
        return $this->db->resultSet();
    }
    public function getAccounts()
    {
        $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                          WHERE (deleted=0) AND (isBank = 0) AND (parentId <> 0) ORDER BY accountType');
        return $this->db->resultSet();                  
    }
    public function getAccountDetails($account)
    {
        //get names
        $details = [];
        $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        $accountname = $this->db->getValue();
        array_push($details,$accountname);
        //get accountId
        $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        $accountid = $this->db->getValue();
        array_push($details,$accountid);
        return $details;
    }
    public function create($data)
    {
        try {
            //begin transaction
            $transtype = (int)$data['type'];
            $accountname = $this->getAccountDetails($data['account'])[0];
            $accountid = $this->getAccountDetails($data['account'])[1];
            $this->db->dbh->beginTransaction();
            $sql = 'INSERT INTO tblbankpostings (bankId,transactionDate,debit,credit,transactionMethod,reference,
                                                accountId,narration,transactionType,congregationId)
                    VALUES(:bid,:tdate,:debit,:credit,:method,:ref,:aid,:narr,:ttype,:cid)';
            $this->db->query($sql);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':tdate',$data['date']);
            $this->db->bind(':debit',$transtype === 1 ? $data['amount'] : 0);
            $this->db->bind(':credit',$transtype > 1 ? $data['amount'] : 0);
            $this->db->bind(':method',$transtype);
            $this->db->bind(':ref',$data['reference']);
            $this->db->bind(':aid',$data['account']);
            $this->db->bind(':narr',!empty($data['narration']) ? strtolower($data['narration']) : NULL);
            $this->db->bind(':ttype',9);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();

            $tid = $this->db->dbh->lastInsertId();

            if($transtype === 1 ){
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$data['amount'],0,
                             $data['reference'],3,9,$tid,$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],'cash at hand',0,$data['amount'],
                             $data['reference'],$accountid,9,$tid,$_SESSION['congId']);
            }else{
                saveToLedger($this->db->dbh,$data['date'],$accountname,$data['amount'],0,
                             $data['reference'],$accountid,9,$tid,$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',0,$data['amount'],
                             $data['reference'],3,9,$tid,$_SESSION['congId']);             
            }

            if ($this->db->dbh->commit()) {
                return true;
            }
            else{
                return false;
            }   

        } catch (PDOException $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            throw $e;
        }
    }
    public function edit($id)
    {
        $this->db->query('SELECT * FROM tblbankpostings WHERE (ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
    public function update($data)
    {
        try {
            //begin transaction
            $transtype = (int)$data['type'];
            $accountname = $this->getAccountDetails($data['account'])[0];
            $accountid = $this->getAccountDetails($data['account'])[1];
            $this->db->dbh->beginTransaction();
            $sql = 'UPDATE tblbankpostings SET bankId=:bid,transactionDate=:tdate,debit=:debit,credit=:credit,
                                               transactionMethod=:method,reference=:ref,accountId=:aid,narration=:narr
                    WHERE (ID=:id)';
            $this->db->query($sql);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':tdate',$data['date']);
            $this->db->bind(':debit',$transtype === 1 ? $data['amount'] : 0);
            $this->db->bind(':credit',$transtype > 1 ? $data['amount'] : 0);
            $this->db->bind(':method',$transtype);
            $this->db->bind(':ref',$data['reference']);
            $this->db->bind(':aid',$data['account']);
            $this->db->bind(':narr',!empty($data['narration']) ? strtolower($data['narration']) : NULL);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();

            $this->db->query('DELETE FROM tblledger WHERE transactionType=9 AND transactionId=:tid');
            $this->db->bind(':tid',$data['id']);
            $this->db->execute();

            if($transtype === 1 ){
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$data['amount'],0,
                             $data['reference'],3,9,$data['id'],$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],$accountname,0,$data['amount'],
                             $data['reference'],$accountid,9,$data['id'],$_SESSION['congId']);
            }else{
                saveToLedger($this->db->dbh,$data['date'],$accountname,$data['amount'],0,
                             $data['reference'],$accountid,9,$data['id'],$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',0,$data['amount'],
                             $data['reference'],3,9,$data['id'],$_SESSION['congId']);             
            }

            if ($this->db->dbh->commit()) {
                return true;
            }
            else{
                return false;
            }   

        } catch (PDOException $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            throw $e;
        }
    }
    public function delete($id)
    {
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $sql = 'UPDATE tblbankpostings SET deleted=1
                    WHERE (ID=:id)';
            $this->db->query($sql);
            $this->db->bind(':id',$id);
            $this->db->execute();

            $this->db->query('DELETE FROM tblledger WHERE transactionType=9 AND transactionId=:tid');
            $this->db->bind(':tid',$id);
            $this->db->execute();

            if ($this->db->dbh->commit()) {
                return true;
            }
            else{
                return false;
            }   

        } catch (PDOException $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            throw $e;
        }
    }
}
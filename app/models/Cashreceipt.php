<?php
class Cashreceipt
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

    public function GetReceipts()
    {
        $this->db->query('SELECT * FROM vw_cashreceipts WHERE CongregationId = :id');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetReceiptNo()
    {
        return getuniqueid($this->db->dbh,'ReceiptNo','tblpettycash',(int)$_SESSION['congId']);
    }

    public function GetBanks()
    {
        $this->db->query("SELECT ID,CONCAT(UCASE(accountType),'-',`accountNo`) AS BankName 
                          FROM tblaccounttypes 
                          WHERE `isBank` = 1 AND CongregationId=:id");
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function Save($data)
    {
        $narr = !empty($data['description']) ? strtolower($data['description']) : 'petty cash receipt - '.$data['date'];
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,Reference,Narration,CongregationId)
                              VALUES(:rno,:tdate,:debit,:isreceipt,:bankid,:reference,:narr,:cid)');
            $this->db->bind(':rno',$this->GetReceiptNo());
            $this->db->bind(':tdate',$data['date']);
            $this->db->bind(':debit',$data['amount']);
            $this->db->bind(':isreceipt',true);
            $this->db->bind(':bankid',$data['bank']);
            $this->db->bind(':reference',strtolower($data['reference']));
            $this->db->bind(':narr',$narr);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();

            $tid = $this->db->dbh->lastInsertId();

            saveToLedger($this->db->dbh,$data['date'],'petty cash',$data['amount'],0,$narr ,
                         3,10,$tid,$_SESSION['congId']);

            saveToLedger($this->db->dbh,$data['date'],'cash at bank',0,$data['amount'],$narr ,
                         3,10,$tid,$_SESSION['congId']);

            saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],2,
                         $data['reference'],10,$tid,$_SESSION['congId']); 
            
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
            throw $e;
            return false;
        }
    }

    public function Update($data)
    {
        $narr = !empty($data['description']) ? strtolower($data['description']) : 'petty cash receipt - '.$data['date'];
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblpettycash SET TransactionDate=:tdate,Debit=:debit,
                                     BankId=:bankid,Reference=:reference,Narration=:narr WHERE(ID=:id)');
            $this->db->bind(':tdate',$data['date']);
            $this->db->bind(':debit',$data['amount']);
            $this->db->bind(':bankid',$data['bank']);
            $this->db->bind(':reference',strtolower($data['reference']));
            $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : null);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();

            deleteLedgerBanking($this->db->dbh,10,$data['id']);

            saveToLedger($this->db->dbh,$data['date'],'petty cash',$data['amount'],0,$narr,
                         3,10,$data['id'],$_SESSION['congId']);

            saveToLedger($this->db->dbh,$data['date'],'cash at bank',0,$data['amount'],$narr,
                         3,10,$data['id'],$_SESSION['congId']);
            
            saveToBanking($this->db->dbh,$data['bank'],$data['date'],0,$data['amount'],2,
                         $data['reference'],10,$data['id'],$_SESSION['congId']);
            
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
            throw $e;
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

    public function GetReceipt($id)
    {
        $this->db->query('SELECT * FROM tblpettycash WHERE ID = :id');
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    public function Delete($id)
    {
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            
            $this->db->query('DELETE FROM tblpettycash WHERE ID = :id');
            $this->db->bind(':id', $id);
            $this->db->execute();

            deleteLedgerBanking($this->db->dbh,10,$id);
                    
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
            throw $e;
            return false;
        }
    }
}
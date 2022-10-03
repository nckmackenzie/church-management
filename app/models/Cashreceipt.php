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
        if (getUserAccess($this->db->dbh,$_SESSION['userId'],$form,$_SESSION['isParish']) > 0) {
            return true;
        }else{
            return false;
        }
    }

    public function GetReceipts()
    {
        $this->db->query('SELECT * FROM vw_cashreceipts WHERE CongregationId = :id');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
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
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblpettycash (TransactionDate,Debit,IsReceipt,BankId,Reference,Narration,CongregationId)
                              VALUES(:tdate,:debit,:isreceipt,:bankid,:reference,:narr,:cid)');
            $this->db->bind(':tdate',$data['date']);
            $this->db->bind(':debit',$data['amount']);
            $this->db->bind(':isreceipt',true);
            $this->db->bind(':bankid',$data['bank']);
            $this->db->bind(':reference',strtolower($data['reference']));
            $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : null);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();

            $tid = $this->db->dbh->lastInsertId();

            saveToLedger($this->db->dbh,$data['date'],'petty cash',$data['amount'],0,!empty($data['description']) ? strtolower($data['description']) : null,
                         3,10,$tid,$_SESSION['congId']);

            saveToLedger($this->db->dbh,$data['date'],'cash at bank',0,$data['amount'],!empty($data['description']) ? strtolower($data['description']) : null,
                         3,10,$tid,$_SESSION['congId']);
            
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

            $this->db->query('DELETE FROM tblledger WHERE transactionType=:ttype AND transactionId=:tid');
            $this->db->bind(':ttype',10);
            $this->db->bind(':tid',$data['id']);
            $this->db->execute();

            saveToLedger($this->db->dbh,$data['date'],'petty cash',$data['amount'],0,!empty($data['description']) ? strtolower($data['description']) : null,
                         3,10,$data['id'],$_SESSION['congId']);

            saveToLedger($this->db->dbh,$data['date'],'cash at bank',0,$data['amount'],!empty($data['description']) ? strtolower($data['description']) : null,
                         3,10,$data['id'],$_SESSION['congId']);
            
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
        $this->db->query('DELETE FROM tblpettycash WHERE ID = :id');
        $this->db->bind(':id', $id);
        if(!$this->db->execute()) {
            return false;
        }else{
            return true;
        }
    }
}
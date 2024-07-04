<?php

class Clearbanking
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function getBankings($data)
    {
        $sql = 'SELECT b.ID,
                       b.transactionDate,
                       IF(b.debit > 0,b.debit,(b.credit * -1)) As Amount,
                       ucase(b.reference) As Reference
                FROM   tblbankpostings b
                WHERE  (b.deleted=0) AND (b.cleared = 0) AND (b.bankId = ?) 
                        AND (b.transactionDate BETWEEN ? AND ?) AND (b.congregationId = ?)
                ORDER BY b.transactionDate';
        return loadresultset($this->db->dbh,$sql,[$data['bank'],$data['from'],$data['to'],$_SESSION['congId']]);
    }

    public function getClearedBankings($data)
    {
        $sql = 'SELECT b.ID,
                       b.transactionDate,
                       b.clearedDare,
                       IF(b.debit > 0,b.debit,(b.credit * -1)) As Amount,
                       ucase(b.reference) As Reference
                FROM   tblbankpostings b
                WHERE  (b.deleted=0) AND (b.cleared = 1) AND (b.bankId = ?) 
                        AND (b.transactionDate BETWEEN ? AND ?) AND (b.congregationId = ?)
                ORDER BY b.transactionDate';
        return loadresultset($this->db->dbh,$sql,[$data['bank'],$data['from'],$data['to'],$_SESSION['congId']]);
    }

    public function Clear($data)
    {
        try {

            $this->db->dbh->beginTransaction();

            for ($i=0; $i < count($data['table']); $i++) {
                $id = $data['table'][$i]->id;
                $cleardate = $data['table'][$i]->clearDate;
                $this->db->query('UPDATE tblbankpostings SET cleared=1,clearedDare=:tdate WHERE ID=:id');
                $this->db->bind(':id',$id);
                $this->db->bind(':tdate',!empty($cleardate) ? date('Y-m-d',strtotime($cleardate)) : NULL);
                $this->db->execute();
            }    

            if(!$this->db->dbh->commit()){
                return false;
            }else{
                return true;
            }
            
        } catch (PDOException $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollBack();
            }
            error_log($e->getMessage(),0);
            return false;
        }catch (Exception $e) {
            error_log($e->getMessage(),0);
            return false;
        }
            
    }

    public function Modify($data)
    {
        try {

            $this->db->dbh->beginTransaction();

            for ($i=0; $i < count($data['table']); $i++) {
                $id = $data['table'][$i]->id;
                $cleardate = $data['table'][$i]->clearDate;
                $this->db->query('UPDATE tblbankpostings SET clearedDare=:tdate WHERE ID=:id');
                $this->db->bind(':tdate',!empty($cleardate) ? date('Y-m-d',strtotime($cleardate)) : NULL);
                $this->db->bind(':id',$id);
                $this->db->execute();
            }    

            if(!$this->db->dbh->commit()){
                return false;
            }else{
                return true;
            }
            
        } catch (PDOException $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollBack();
            }
            error_log($e->getMessage(),0);
            return false;
        }catch (Exception $e) {
            error_log($e->getMessage(),0);
            return false;
        }
            
    }
    
    public function delete($id)
    {
        $this->db->query('UPDATE tblbankpostings SET deleted=1 WHERE ID=:id');
        $this->db->bind(':id',$id);
        if ($this->db->execute()) {
            return true;
        }else{
            return false;
        }
    }

    public function getAmounts($data)
    {
        $amounts = [];
        $deposits = 0;
        $withdrawals = 0;

        $depositssql ='SELECT IFNULL(SUM(debit),0) As SumOfDebits
                       FROM   tblbankpostings
                       WHERE  (transactionDate BETWEEN ? AND ?) AND (cleared=1) 
                              AND (deleted=0) AND (bankId= ?) AND (congregationId = ?)';
        $deposits = floatval(getdbvalue($this->db->dbh,$depositssql,[$data['from'],$data['to'],$data['bank'],$_SESSION['congId']]));
        array_push($amounts,$deposits);

        $withdrawalsql ='SELECT IFNULL(SUM(credit),0) As SumOfCredits
                         FROM   tblbankpostings
                         WHERE  (transactionDate BETWEEN ? AND ?) AND (cleared=1) 
                                AND (deleted=0) AND (bankId= ?) AND (congregationId = ?)';
        $withdrawals = floatval(getdbvalue($this->db->dbh,$withdrawalsql,[$data['from'],$data['to'],$data['bank'],$_SESSION['congId']]));
        array_push($amounts,$withdrawals);

        return $amounts;
    }
}
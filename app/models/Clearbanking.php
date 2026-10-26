<?php

class Clearbanking
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function getBanks()
    {
        return loadresultset($this->db->dbh,"SELECT a.ID,UCASE(CONCAT(a.accountType,'-',IFNULL(a.accountNo,''))) Bank
            FROM tblaccounttypes a
            WHERE a.isBank=1 AND a.deleted=0 AND a.congregationId=?
              AND (a.active=1 OR EXISTS (
                    SELECT 1 FROM tblbankpostings b
                    WHERE b.bankId=a.ID AND b.congregationId=a.congregationId AND b.deleted=0))
            ORDER BY a.accountType",[(int)$_SESSION['congId']]);
    }

    /** Report-safe bank of the session congregation (decision 114 / task 9.1). */
    public function isReportBank($id)
    {
        return FinancialReport::bankAllowed($this->db->pdo(), [(int) $_SESSION['congId']], (int) $id);
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
    
    public function getAmounts($data)
    {
        $amounts = [];
        $deposits = 0;
        $withdrawals = 0;

        $depositssql ='SELECT IFNULL(ROUND(SUM(debit),2),0) As SumOfDebits
                       FROM   tblbankpostings
                       WHERE  (transactionDate BETWEEN ? AND ?) AND (cleared=1) 
                              AND (deleted=0) AND (bankId= ?) AND (congregationId = ?)';
        $deposits = FinancialReport::decimal(FinancialReport::cents(getdbvalue($this->db->dbh,$depositssql,[$data['from'],$data['to'],$data['bank'],$_SESSION['congId']])));
        array_push($amounts,$deposits);

        $withdrawalsql ='SELECT IFNULL(ROUND(SUM(credit),2),0) As SumOfCredits
                         FROM   tblbankpostings
                         WHERE  (transactionDate BETWEEN ? AND ?) AND (cleared=1) 
                                AND (deleted=0) AND (bankId= ?) AND (congregationId = ?)';
        $withdrawals = FinancialReport::decimal(FinancialReport::cents(getdbvalue($this->db->dbh,$withdrawalsql,[$data['from'],$data['to'],$data['bank'],$_SESSION['congId']])));
        array_push($amounts,$withdrawals);

        return $amounts;
    }
}

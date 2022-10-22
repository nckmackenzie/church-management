<?php
class Supplier
{
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetSuppliers()
    {
        return loadresultset($this->db->dbh,'SELECT * FROM vw_suppliers WHERE congregationId = ?',[(int)$_SESSION['congId']]);
    }

    public function CreateUpdate($data)
    {
        try {
            $this->db->dbh->beginTransaction();

            if($data['isedit']){

            }else{
                $this->db->query('INSERT INTO tblsuppliers (supplierName,contact,`address`,pin,email,contactPerson,congregationId) 
                                  VALUES(:sname,:contact,:add,:pin,:email,:cperson,:cid)');
                $this->db->bind(':sname',$data['suppliername']);
                $this->db->bind(':contact',$data['contact']);
                $this->db->bind(':add',$data['address']);
                $this->db->bind(':pin',$data['pin']);
                $this->db->bind(':email',$data['email']);
                $this->db->bind(':cperson',$data['contactperson']);
                $this->db->bind(':cid',$_SESSION['congId']);
                $this->db->execute();
                $tid = $this->db->dbh->lastInsertId();
                

                if(floatval($data['balance'])){
                    $yearid = getYearId($this->db->dbh, $data['asof']);
                    $this->db->query('INSERT INTO tblinvoice_header_suppliers (invoiceDate,supplierId,
                                                  fiscalYearId,inclusiveVat,postedBy,congregationId)
                                      VALUES(:idate,:cid,:fid,:ivat,:pby,:cong)');
                    $this->db->bind(':idate',$data['asof']);
                    $this->db->bind(':cid',$tid);
                    $this->db->bind(':fid',$yearid);
                    $this->db->bind(':ivat',$data['balance']);
                    $this->db->bind(':pby',$_SESSION['userId']);
                    $this->db->bind(':cong',$_SESSION['congId']);
                    $this->db->execute();

                    saveToLedger($this->db->dbh,$data['asof'],'accounts payable','payables and accruals',0,$data['balance']
                            ,'supplier opening balance',4,15,$tid,$_SESSION['congId']);
                    saveToLedger($this->db->dbh,$data['asof'],'uncategorized expenses','uncategorized expenses',$data['balance'],0
                            ,'supplier opening balance',2,15,$tid,$_SESSION['congId']);
                }

                if(!$this->db->dbh->commit()){
                    return false;
                }else{
                    return true;
                }
            }

        } catch (PDOException $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollBack();
            }
            error_log($e->getMessage(),0);
            return false;
        }
    }
}
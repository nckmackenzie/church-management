<?php

class Payment
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetPayments()
    {
        $sql = 'SELECT * FROM vw_supplier_payments  WHERE congregationId = ?';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetPendingInvoices()
    {
        $sql = 'SELECT * FROM vw_supplier_with_bals WHERE congregationId = ?';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetPaymentId()
    {
        return getuniqueid($this->db->dbh,'paymentNo','tblinvoice_payments_suppliers',(int)$_SESSION['congId'],false);
    }
    
    public function Create($data)
    {
        try {
            $this->db->dbh->beginTransaction();
            $paymentno = $this->GetPaymentId();

            //loop through payments and save
            for($i = 0; $i < count($data['payments']); $i++) 
            {
                if(isset($data['payments'][$i]->payment) && floatval($data['payments'][$i]->payment) > 0) :
                    $this->db->query('INSERT INTO tblinvoice_payments_suppliers (paymentNo,invoice_id,paymentDate,amount,paymentId,bankId,
                                                paymentReference)
                                    VALUES(:pno,:iid,:pdate,:amount,:pid,:bid,:ref)');
                    $this->db->bind(':pno',$paymentno);
                    $this->db->bind(':iid',$data['payments'][$i]->invoiceid);
                    $this->db->bind(':pdate',$data['paydate']);
                    $this->db->bind(':amount',floatval($data['payments'][$i]->payment));
                    $this->db->bind(':pid',$data['paymethod']);
                    $this->db->bind(':bid',$data['bank']);
                    $this->db->bind(':ref',!empty($data['payments'][$i]->cheque) ? strtolower($data['payments'][$i]->cheque) : NULL);
                    $this->db->execute();
                    //get inserted id
                    $tid = $this->db->dbh->lastInsertId();

                    //update header table
                    if (floatval($data['payments'][$i]->payment) < floatval($data['payments'][$i]->balance)) {
                        $status = 1;
                    } else {
                        $status = 2;
                    }
                    $this->db->query('UPDATE tblinvoice_header_suppliers SET `status`=:stat WHERE (ID=:id)');
                    $this->db->bind(':stat',$status);
                    $this->db->bind(':id',$data['payments'][$i]->invoiceid);
                    $this->db->execute();
                    $cabparent = getparentgl($this->db->dbh,'cash at bank');
                    $accountspayableparent = 'payables and accruals'; //parent account for payables

                    saveToLedger($this->db->dbh,$data['paydate'],'accounts payable',$accountspayableparent,$data['payments'][$i]->payment,0
                                ,$data['payments'][$i]->cheque,4,7,$tid,$_SESSION['congId']);
                    if((int)$data['paymethod'] === 1){
                        saveToLedger($this->db->dbh,$data['paydate'],'petty cash',$cabparent,0,$data['payments'][$i]->payment
                            ,$data['payments'][$i]->cheque,3,7,$tid,$_SESSION['congId']);
                    }else{
                        saveToLedger($this->db->dbh,$data['paydate'],'cash at bank',$cabparent,0,$data['payments'][$i]->payment
                            ,$data['payments'][$i]->cheque,3,7,$tid,$_SESSION['congId']);
                        saveToBanking($this->db->dbh,$data['bank'],$data['paydate'],0,$data['payments'][$i]->payment,2,
                            $data['payments'][$i]->cheque,7,$tid,$_SESSION['congId']);
                    }
                endif;
            }
            
            if(!$this->db->dbh->commit()){
                return false;
            }else{
                return true;
            }

        } catch (Exception $e) {
            if($this->db->dbh->inTransaction()){
                $this->db->dbh->rollBack();
            }
            error_log($e->getMessage(),0);
            // throw $e;
        }
    }

    public function GetSupplierDetails($id)
    {
        $this->db->query('SELECT * FROM tblsuppliers WHERE ID = :id');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }

    public function GetPaymentDate($payno)
    {
        $sql = 'SELECT DISTINCT paymentDate FROM tblinvoice_payments_suppliers WHERE paymentNo = ?';
        return getdbvalue($this->db->dbh,$sql,[$payno]);
    }

    public function GetInvoicedetails($payno,$supplier)
    {
        return loadresultset($this->db->dbh,'CALL sp_getinvoicepaymentdetails(?,?)',[$payno,$supplier]);
    }

    public function GetPaymentSupplierValue($payno,$supplier)
    {
        $sql = 'SELECT 
                    IFNULL(SUM(amount),0) AS SumOfAmount
                FROM `tblinvoice_payments_suppliers` p 
                    join tblinvoice_header_suppliers s on p.invoice_Id = s.ID
                WHERE p.paymentNo = ? AND s.supplierId = ?';
        return getdbvalue($this->db->dbh,$sql,[$payno,$supplier]);
    }
}
<?php

class Supplierinvoice
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    
    public function index()
    {
        $this->db->query('CALL spGetInvoices_suppliers(:congid)');
        $this->db->bind(':congid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function getSuppliers()
    {
        $this->db->query('SELECT ID,
                                 UCASE(supplierName) as supplierName
                          FROM   tblsuppliers
                          WHERE  (deleted=0) AND (congregationId = :cid)
                          ORDER BY supplierName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function getProducts()
    {
        $this->db->query('SELECT ID,
                                 UCASE(productName) as productName
                          FROM   tblproducts
                          WHERE  (deleted=0) AND (congregationId = :cid)');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function getAccountName($account)
    {
        $this->db->query('SELECT accountId FROM tblproducts WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        $accid = $this->db->getValue();
        //getname
        $accountDetails = array();
        $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$accid);
        $accName = $this->db->getValue();
        array_push($accountDetails,$accName);

        $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$accid);
        $accountId = $this->db->getValue();
        array_push($accountDetails,$accountId);

        return $accountDetails;
    }

    public function getVats()
    {
        $this->db->query('SELECT ID,
                                 rate,
                                 UCASE(vatName) as vatName 
                          FROM tblvats WHERE (deleted=0) AND (active=1)');
        return $this->db->resultSet();
    }

    public function getAccounts()
    {
        $this->db->query('SELECT ID,
                                 UCASE(accountType) as accountType
                          FROM   tblaccounttypes t
                          WHERE  (deleted=0) AND (brand_level(t.ID) = 2)');
        return $this->db->resultSet();
    }
    
    public function getVatId($vat)
    {
        $this->db->query('SELECT ID FROM tblvats WHERE (vatName=:nam)');
        $this->db->bind(':nam',$vat);
        return $this->db->getValue();
    }

    public function getSupplierDetails($id)
    {
        $this->db->query('SELECT * FROM tblsuppliers WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }

    public function SaveProduct($data)
    {
        $this->db->query('INSERT INTO tblproducts (productName,`description`,rate,accountId,congregationId) 
                          VALUES(:pname,:narr,:rate,:account,:cid)');
        $this->db->bind(':pname',strtolower($data['productname']));
        $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : NULL);
        $this->db->bind(':rate',$data['rate']);
        $this->db->bind(':account',(int)$data['account']);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        $id = $this->db->dbh->lastInsertId(); //get last inserted id
      
        if(!$this->db->execute()){
            return [false,''];
        }
        return [true,$id];
    }

    public function GetProductId()
    {
        $sql = 'SELECT ID FROM tblproducts WHERE congregationId = ? AND Deleted = 0 ORDER BY ID DESC LIMIT 1';
        return getdbvalue($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function getRate($vat)
    {
        $this->db->query('SELECT rate FROM tblvats WHERE (ID=:id)');
        $this->db->bind(':id',$vat);
        return ($this->db->getValue()) / 100;
    }

    public function CheckInvoiceNo($invoice,$id)
    {
        $sql = 'SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE (invoiceNo = ?) AND (congregationId = ?) AND (ID <> ?)';
        $count = getdbvalue($this->db->dbh,$sql,[strtolower($invoice),$_SESSION['congId'], $id]);
        if((int)$count > 0){
            return false;
        }else{
            return true; 
        }
    }

    public function create($data)
    {
        $yearid = getYearId($this->db->dbh,$data['invoicedate']);
        $vatId = $this->getVatId($data['vat']);
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblinvoice_header_suppliers (invoiceDate,duedate,supplierId,invoiceNo,
                                          fiscalYearId,vattype,vatId,exclusiveVat,vat,inclusiveVat,
                                          postedBy,congregationId)
                              VALUES(:idate,:ddate,:cid,:inv,:fid,:vtype,:vid,:evat,:vat,:ivat,:pby,:cong)');
            $this->db->bind(':idate',$data['idate']);
            $this->db->bind(':ddate',$data['ddate']);
            $this->db->bind(':cid',$data['supplier']);
            $this->db->bind(':inv',$data['invoiceno']);
            $this->db->bind(':fid',$yearid);
            $this->db->bind(':vtype',$data['vattype']);
            $this->db->bind(':vid',!is_null($data['vat']) ? $vatId : null);
            $this->db->bind(':evat',calculateVat($data['vattype'],$data['totals'])[0]);
            $this->db->bind(':vat',calculateVat($data['vattype'],$data['totals'])[1]);
            $this->db->bind(':ivat',calculateVat($data['vattype'],$data['totals'])[2]);
            $this->db->bind(':pby',$_SESSION['userId']);
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->execute();
            //details
            $tid = $this->db->dbh->lastInsertId();

            for($i = 0; $i < count($data['table']); $i++){
                $this->db->query('INSERT INTO tblinvoice_details_suppliers (header_id,productId,qty,rate,gross)
                                  VALUES(:hid,:pid,:qty,:rate,:gross)');
                $this->db->bind(':hid',$tid);
                $this->db->bind(':pid',$data['table'][$i]->pid);
                $this->db->bind(':qty',$data['table'][$i]->qty);
                $this->db->bind(':rate',$data['table'][$i]->rate);
                $this->db->bind(':gross',$data['table'][$i]->gross);
                $this->db->execute();

                $pid = $data['details'][$i]->pid;
                $pname = $this->getAccountName($pid)[0];
                $singleAccountId = $this->getAccountName($pid)[1];
                $parentaccountname = getparentgl($this->db->dbh,$pname);
                $narr = 'supplier invoice no '.$data['invoiceno'];
                saveToLedger($this->db->dbh,$data['invoicedate'],$pname,$parentaccountname,
                             calculateVat($data['vattype'],$data['table'][$i]->gross)[2],0
                            ,$narr,$singleAccountId,6,$tid,$_SESSION['congId']);
            }

            $account = 'accounts payable';
            $narr = 'Invoice #'.$data['invoice'];
            $parentaccount = 'payables and accruals';
            $three = 4;
            saveToLedger($this->db->dbh,$data['invoicedate'],$account,$parentaccount,0,
                         calculateVat($data['vattype'],$data['totals'])[2]
                        ,$narr,$three,6,$tid,$_SESSION['congId']); 
            //save to logs
            saveLog($this->db->dbh,$narr);
            if(!$this->db->dbh->commit()){
                return false;
            }else{
                return true;
            }
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            error_log($e->getMessage(),0);
            return false;
        }
    }

    public function CreateUpdate($data)
    {
        if(!$data['isedit']){
            return $this->create($data);
        }else{
            
        }
    }

    public function getInvoiceHeader($id)
    {
        $this->db->query('SELECT ID,
                                 invoiceDate,
                                 duedate,
                                 supplierId,
                                 invoiceNo,
                                 vattype,
                                 vatId,
                                 exclusiveVat,
                                 vat,
                                 inclusiveVat
                          FROM   tblinvoice_header_suppliers
                          WHERE  (ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }

    public function getInvoiceDetails($id)
    {
        $this->db->query('SELECT productId,
                                 ucase(productName) as accountType,
                                 qty,
                                 d.rate,
                                 gross,
                                 UCASE(d.description) as `description`
                          FROM   tblinvoice_details_suppliers d inner join tblproducts p 
                                 ON d.productId = p.ID
                          WHERE  (header_id = :id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->resultSet();
    }

    public function update($data)
    {
        $yearid = getYearId($this->db->dbh,$data['invoicedate']);
        $vatId = $this->getVatId($data['vat']);
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblinvoice_header_suppliers SET invoiceDate=:idate,duedate=:ddate,
                                     supplierId=:cid,invoiceNo=:inv,fiscalYearId=:fid,vattype=:vtype
                                     ,vatId=:vid,exclusiveVat=:evat,vat=:vat,inclusiveVat=:ivat
                              WHERE  (ID=:id)');
            $this->db->bind(':idate',!empty($data['invoicedate']) ? $data['invoicedate'] : NULL);
            $this->db->bind(':ddate',!empty($data['duedate']) ? $data['duedate'] : NULL);
            $this->db->bind(':cid',$data['supplierId']);
            $this->db->bind(':inv',$data['invoice']);
            $this->db->bind(':fid',$yearid);
            $this->db->bind(':vtype',!empty($data['vattype']) ? $data['vattype'] : NULL);
            $this->db->bind(':vid',$vatId);
            $this->db->bind(':evat',calculateVat($data['vattype'],$data['totals'])[0]);
            $this->db->bind(':vat',calculateVat($data['vattype'],$data['totals'])[1]);
            $this->db->bind(':ivat',calculateVat($data['vattype'],$data['totals'])[2]);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            //delete existing
            $this->db->query('DELETE FROM tblinvoice_details_suppliers WHERE header_id=:id');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            //delete ledge
            $this->db->query('DELETE FROM tblledger WHERE (transactionType=:ttype) AND (transactionId=:tid)');
            $this->db->bind(':ttype',6);
            $this->db->bind(':tid',$data['id']);
            $this->db->execute();

            //details
            $tid = $data['id'];
            $sql = 'INSERT INTO tblinvoice_details_suppliers (header_id,productId,qty,rate,gross,`description`)
                    VALUES(?,?,?,?,?,?)';
            for ($i=0; $i < count($data['details']); $i++) { 
                $pid = $data['details'][$i]['pid'];
                $pname = $this->getAccountName($pid)[0];
                $singleAccountId = $this->getAccountName($pid)[1];
                // $pname = trim(strtolower($data['details'][$i]['pname']));
                $qty = $data['details'][$i]['qty'];
                $rate = $data['details'][$i]['rate'];
                $gross = $data['details'][$i]['gross'];
                $desc = strtolower($data['details'][$i]['desc']);
                $stmt = $this->db->dbh->prepare($sql);
                $stmt->execute([$tid,$pid,$qty,$rate,$gross,$desc]);
                $parentaccountname = getparentgl($this->db->dbh,$pname);
                saveToLedger($this->db->dbh,$data['invoicedate'],$pname,$parentaccountname,
                             calculateVat($data['vattype'],$gross)[2],0
                            ,$desc,$singleAccountId,6,$tid,$_SESSION['congId']);
            }
            $account = 'accounts payable';
            $narr = 'Invoice #'.$data['invoice'];
            $parentaccount = 'payables and accruals';
            $three = 4;
            saveToLedger($this->db->dbh,$data['invoicedate'],$account,$parentaccount,0,
                         calculateVat($data['vattype'],$data['totals'])[2]
                        ,$narr,$three,6,$tid,$_SESSION['congId']); 
            //save to logs
            saveLog($this->db->dbh, 'Updated '. $narr);
            $this->db->dbh->commit();
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            throw $e;
        }
    }

    public function fillInvoiceDetails($id)
    {
        $this->db->query('SELECT   h.ID,
                                   ucase(supplierName) as supplierName,
                                   invoiceNo,
                                   inclusiveVat,
                                   (inclusiveVat - (SELECT IFNULL(SUM(amount),0) FROM tblinvoice_payments_suppliers
                                   WHERE invoice_Id=h.ID)) as balance
                          FROM     tblinvoice_header_suppliers h inner join tblsuppliers c
                                   ON h.supplierId = c.ID
                          WHERE    (h.ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }

    public function paymethods()
    {
        return paymentMethods($this->db->dbh);
    }

    public function banks()
    {
        if ($_SESSION['isParish'] == 1) {
            return getBanksAll($this->db->dbh);
        }else{
            return getBanks($this->db->dbh,$_SESSION['congId']);
        }
    }
 
    public function getCongregationInfo()
    {
        $this->db->query('SELECT ucase(CongregationName) as CongregationName,
                                 UCASE(`Address`) as `address`,
                                 contact,
                                 email
                          FROM   tblcongregation
                          WHERE  (ID=:id)');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->single();
    }

    public function getSupplierInfo($id)
    {
        $this->db->query('SELECT UCASE(supplierName) as supplierName,
                                 UCASE(`address`) as `address`,
                                 contact,
                                 email,
                                 UCASE(pin) AS pin
                          FROM   tblsuppliers
                          WHERE  (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
}
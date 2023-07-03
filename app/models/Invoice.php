<?php
class Invoice {
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
    public function index()
    {
        $this->db->query('SELECT * FROM vw_invoices');
        return $this->db->resultSet();
    }
    public function getCustomers()
    {
        $this->db->query('SELECT ID,
                                 UCASE(customerName) as customerName
                          FROM   tblcustomers
                          WHERE  (deleted=0) AND (congregationId = :cid)
                          ORDER BY customerName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function getCustomerDetails($id)
    {
        $this->db->query('SELECT * FROM tblcustomers WHERE (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function getIncomeAccounts()
    {
        $this->db->query('SELECT ID,
                                 UCASE(accountType) as accountType
                          FROM tblaccounttypes
                          WHERE  (deleted=0) AND (parentId <> 0) AND (isSubCategory = 0)');
        return $this->db->resultSet();
    }
    public function getInvoiceNo()
    {
        $id = '';
        $this->db->query("SELECT COUNT(ID) FROM tblinvoice_header WHERE (congregationId=:cid)");
        $this->db->bind(':cid',$_SESSION['congId']);
        $result = $this->db->getValue();
        if ($result == 0) {
            $id = 1;
        }
        else {
            $this->db->query("SELECT invoiceNo 
                              FROM   tblinvoice_header
                              WHERE  (congregationId = :cid)
                              ORDER BY ID DESC LIMIT 1");
            $this->db->bind(':cid',$_SESSION['congId']);
            $id = $this->db->getValue() + 1;
            // $result = $stmt->fetchColumn();
            // $id = $result + 1;
        }
        return $id;
    }
    public function getAccountId($account)
    {
        $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        return $this->db->getValue();
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
    public function getVatId($vat)
    {
        $this->db->query('SELECT ID FROM tblvats WHERE (vatName=:nam)');
        $this->db->bind(':nam',$vat);
        return $this->db->getValue();
    }
    public function create($data)
    {
        $yearid = getYearId($this->db->dbh,$data['invoicedate']);
        $vatId = $this->getVatId($data['vat']);
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblinvoice_header (invoiceDate,duedate,customerId,invoiceNo,
                                          fiscalYearId,vattype,vatId,exclusiveVat,vat,inclusiveVat,
                                          postedBy,congregationId)
                              VALUES(:idate,:ddate,:cid,:inv,:fid,:vtype,:vid,:evat,:vat,:ivat,:pby,:cong)');
            $this->db->bind(':idate',!empty($data['invoicedate']) ? $data['invoicedate'] : NULL);
            $this->db->bind(':ddate',!empty($data['duedate']) ? $data['duedate'] : NULL);
            $this->db->bind(':cid',$data['customerid']);
            $this->db->bind(':inv',$data['invoice']);
            $this->db->bind(':fid',$yearid);
            $this->db->bind(':vtype',!empty($data['vattype']) ? $data['vattype'] : NULL);
            $this->db->bind(':vid',$vatId);
            $this->db->bind(':evat',calculateVat($data['vattype'],$data['totals'])[0]);
            $this->db->bind(':vat',calculateVat($data['vattype'],$data['totals'])[1]);
            $this->db->bind(':ivat',calculateVat($data['vattype'],$data['totals'])[2]);
            $this->db->bind(':pby',$_SESSION['userId']);
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->execute();
            //details
            $tid = $this->db->dbh->lastInsertId();
            $sql = 'INSERT INTO tblinvoice_details (header_id,productId,qty,rate,gross,`description`)
                    VALUES(?,?,?,?,?,?)';
            for ($i=0; $i < count($data['details']); $i++) { 
                $pid = $data['details'][$i]['pid'];
                $pname = $this->getAccountName($pid)[0];
                $accountid = $this->getAccountName($pid)[1];
                // $pname = trim(strtolower($data['details'][$i]['pname']));
                $qty = $data['details'][$i]['qty'];
                $rate = $data['details'][$i]['rate'];
                $gross = $data['details'][$i]['gross'];
                $desc = strtolower($data['details'][$i]['desc']);
                $stmt = $this->db->dbh->prepare($sql);
                $stmt->execute([$tid,$pid,$qty,$rate,$gross,$desc]);
                $parentaccountname = getparentgl($this->db->dbh,$pname);
                saveToLedger($this->db->dbh,$data['invoicedate'],$pname,$parentaccountname,0,
                             calculateVat($data['vattype'],$gross)[2]
                            ,$desc,$accountid,14,$tid,$_SESSION['congId']);
            }
            $account = 'accounts receivable';
            $narr = 'Invoice #'.$data['invoice'];
            $three = 3;
            saveToLedger($this->db->dbh,$data['invoicedate'],$account,$account,
                         calculateVat($data['vattype'],$data['totals'])[2],0
                        ,$narr,$three,14,$tid,$_SESSION['congId']); 
            //save to logs
            saveLog($this->db->dbh,$narr);
            $this->db->dbh->commit();
        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            throw $e;
        }
    }
    public function update($data)
    {
        $yearid = getYearId($this->db->dbh,$data['invoicedate']);
        $vatId = $this->getVatId($data['vat']);
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblinvoice_header SET invoiceDate=:idate,duedate=:ddate,
                                     customerId=:cid,invoiceNo=:inv,fiscalYearId=:fid,vattype=:vtype
                                     ,vatId=:vid,exclusiveVat=:evat,vat=:vat,inclusiveVat=:ivat
                              WHERE  (ID=:id)');
            $this->db->bind(':idate',!empty($data['invoicedate']) ? $data['invoicedate'] : NULL);
            $this->db->bind(':ddate',!empty($data['duedate']) ? $data['duedate'] : NULL);
            $this->db->bind(':cid',$data['customerid']);
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
            $this->db->query('DELETE FROM tblinvoice_details WHERE header_id=:id');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            //delete ledge
            $this->db->query('DELETE FROM tblledger WHERE (transactionType=:ttype) AND (transactionId=:tid)');
            $this->db->bind(':ttype',6);
            $this->db->bind(':tid',$data['id']);
            $this->db->execute();

            //details
            $tid = $data['id'];
            $sql = 'INSERT INTO tblinvoice_details (header_id,productId,qty,rate,gross,`description`)
                    VALUES(?,?,?,?,?,?)';
            for ($i=0; $i < count($data['details']); $i++) { 
                $pid = $data['details'][$i]['pid'];
                $pname = $this->getAccountName($pid)[0];
                $accountid = $this->getAccountName($pid)[1];
                // $pname = trim(strtolower($data['details'][$i]['pname']));
                $qty = $data['details'][$i]['qty'];
                $rate = $data['details'][$i]['rate'];
                $gross = $data['details'][$i]['gross'];
                $desc = strtolower($data['details'][$i]['desc']);
                $stmt = $this->db->dbh->prepare($sql);
                $stmt->execute([$tid,$pid,$qty,$rate,$gross,$desc]);
                $parentaccountname = getparentgl($this->db->dbh,$pname);
                saveToLedger($this->db->dbh,$data['invoicedate'],$pname,$parentaccountname,0,
                             calculateVat($data['vattype'],$gross)[2]
                            ,$desc,$accountid,14,$tid,$_SESSION['congId']);
            }
            $account = 'accounts receivable';
            $narr = 'Invoice #'.$data['invoice'];
            $three = 3;
            saveToLedger($this->db->dbh,$data['invoicedate'],$account,$account,
                         calculateVat($data['vattype'],$data['totals'])[2],0
                        ,$narr,$three,14,$tid,$_SESSION['congId']); 
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
    public function getRate($vat)
    {
        $this->db->query('SELECT rate FROM tblvats WHERE (ID=:id)');
        $this->db->bind(':id',$vat);
        return ($this->db->getValue()) / 100;
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
    public function getCustomernInfo($id)
    {
        $this->db->query('SELECT UCASE(customerName) as customerName,
                                 UCASE(`address`) as `address`,
                                 contact,
                                 email,
                                 UCASE(pin) AS pin
                          FROM   tblcustomers
                          WHERE  (ID=:id)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function getInvoiceHeader($id)
    {
        $this->db->query('SELECT ID,
                                 invoiceDate,
                                 duedate,
                                 customerId,
                                 invoiceNo,
                                 vattype,
                                 vatId,
                                 exclusiveVat,
                                 vat,
                                 inclusiveVat
                          FROM   tblinvoice_header
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
                          FROM   tblinvoice_details d inner join tblproducts p 
                                 ON d.productId = p.ID
                          WHERE  (header_id = :id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->resultSet();
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
    public function fillInvoiceDetails($id)
    {
        $this->db->query('SELECT   h.ID,
                                   ucase(customerName) as customerName,
                                   invoiceNo,
                                   inclusiveVat,
                                   (inclusiveVat - (SELECT IFNULL(SUM(amount),0) FROM tblinvoice_payments
                                   WHERE invoice_Id=h.ID)) as balance
                          FROM     tblinvoice_header h inner join tblcustomers c
                                   ON h.customerId = c.ID
                          WHERE    (h.ID=:id)');
        $this->db->bind(':id',decryptId($id));
        return $this->db->single();
    }
    public function payment($data)
    {
        if (!empty($data['bank']) || $data['bank'] != NULL) {
            $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
            $this->db->bind(':id',trim($data['bank']));
            $bankname = strtolower($this->db->getValue());
        }
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            //invoice payments
            $this->db->query('INSERT INTO tblinvoice_payments (invoice_id,paymentDate,amount,paymentId,bankId,
                                          paymentReference)
                              VALUES(:iid,:pdate,:amount,:pid,:bid,:ref)');
            $this->db->bind(':iid',$data['id']);
            $this->db->bind(':pdate',$data['paydate']);
            $this->db->bind(':amount',$data['amount']);
            $this->db->bind(':pid',$data['paymethod']);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':ref',!empty($data['reference']) ? strtolower($data['reference']) : NULL);
            $this->db->execute();
            //update invoice table
            $tid = $this->db->dbh->lastInsertId();
            if (floatval($data['amount']) < floatval($data['balance'])) {
                $status = 1;
            } else {
                $status = 2;
            }
            $this->db->query('UPDATE tblinvoice_header SET `status`=:stat WHERE (ID=:id)');
            $this->db->bind(':stat',$status);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            //ledgers
            $account = 'accounts receivable';
            $narr = 'Invoice '.$data['invoiceno'] .' Payment';
            saveToLedger($this->db->dbh,$data['paydate'],$account,$account,0,$data['amount']
                        ,$narr,3,16,$tid,$_SESSION['congId']);

            $cabparent = getparentgl($this->db->dbh,'cash at hand');            
            if ($data['paymethod'] == 1) {
                saveToLedger($this->db->dbh,$data['paydate'],'cash at hand',$cabparent,$data['amount'],0
                        ,$narr,3,16,$tid,$_SESSION['congId']);
            }
            elseif ($data['paymethod'] == 2) {
                saveToLedger($this->db->dbh,$data['paydate'],'cash at bank',$cabparent,$data['amount'],0
                        ,$narr,3,16,$tid,$_SESSION['congId']);
            }
            else {
                saveToLedger($this->db->dbh,$data['paydate'],'cash at bank',$cabparent,$data['amount'],0
                        ,$narr,3,16,$tid,$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$data['paydate'],$data['amount'],0,1,
                              $data['reference'],16,$tid,$_SESSION['congId']);
            }
            //log
            saveLog($this->db->dbh,$narr);
            if ($this->db->dbh->commit()) {
                return true;
            }else {
                return false;
            }

        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollBack();
            }
            throw $e;
        }
    }
    public function newProduct($data)
    {
        $this->db->query('INSERT INTO tblproducts (productName,`description`,rate,accountId,congregationId)
                          VALUES(:nam,:descr,:rate,:acc,:cid)');
        $this->db->bind(':nam',strtolower($data['name']));
        $this->db->bind(':descr',!empty($data['desc']) ? strtolower($data['desc']) : NULL);
        $this->db->bind(':rate',$data['sp']);
        $this->db->bind(':acc',$data['account']);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->execute();
        return $this->db->dbh->lastInsertId();
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
    public function getProductRate($product)
    {
        $this->db->query('SELECT rate FROM tblproducts WHERE (ID=:id)');
        $this->db->bind(':id',$product);
        return $this->db->getValue();
    }
}
<?php
class Contribution {
    private $db;
    public function __construct()
    {
        $this->db =  new Database;
    }
    public function CheckRights($form)
    {
        if (getUserAccess($this->db->dbh,$_SESSION['userId'],$form,$_SESSION['isParish']) > 0) {
            return true;
        }else{
            return false;
        }
    }
    public function getContributions()
    {
        $this->db->query("SELECT h.ID,
                                 h.receiptNo,
                                 DATE_FORMAT(d.contributionDate,'%d/%m/%y') AS contributionDate,
                                 FORMAT(SUM(d.amount),2) As Total
                          FROM   tblcontributions_header h inner join tblcontributions_details d on h.ID = d.HeaderId
                          WHERE  (h.congregationId = :cid)
                          GROUP BY h.ID,h.receiptNo,d.contributionDate
                          ORDER BY d.contributionDate DESC");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function receiptNo()
    {
        return getuniqueid($this->db->dbh,'receiptNo','tblcontributions_header',(int)$_SESSION['congId']);
    }
    public function getAccounts()
    {
        $this->db->query('SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes 
                          WHERE (accountTypeId = 1) AND (deleted=0) AND (isBank = 0) AND (parentId <> 0) AND (isSubCategory = 1) ORDER BY accountType');
        return $this->db->resultSet();                  
    }
    public function paymentMethods()
    {
       return paymentMethods($this->db->dbh);
    }
    public function getCategories()
    {
        $this->db->query('SELECT ID,UCASE(categoryName) AS category FROM tblcontributioncategories');
        return $this->db->resultSet();
    }
    public function getBanks()
    {
       if ($_SESSION['isParish'] == 1) {
           return getBanksAll($this->db->dbh);
       }else{
           return getBanks($this->db->dbh,$_SESSION['congId']);
       }
    }
    public function getContributor($category)
    {
        if ($category == 1 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(memberName) AS contributor
                    FROM tblmember WHERE (deleted=0) AND (memberStatus=1) AND (congregationId=:cid)
                    ORDER BY contributor');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();   
        }
        elseif ($category == 2 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(groupName) AS contributor
                    FROM tblgroups WHERE (deleted=0) AND (active=1) AND (congregationId=:cid)
                    ORDER BY contributor');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }
        elseif ($category == 3 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(districtName) AS contributor
                    FROM tbldistricts WHERE (deleted=0) AND (congregationId=:cid)
                    ORDER BY contributor');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }
        elseif ($category == 4 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(serviceName) AS contributor
                    FROM tblservices WHERE (deleted=0) AND (congregationId=:cid)
                    ORDER BY contributor');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }
        elseif ($category == 1 && $_SESSION['isParish'] == 1 ) {
            $this->db->query("SELECT m.ID,CONCAT(UCASE(memberName),'-',UCASE(c.CongregationName))
                              AS contributor
                              FROM tblmember m INNER JOIN tblcongregation c
                              ON m.congregationId = c.ID
                              WHERE (m.deleted=0) AND (memberStatus=1)
                              ORDER BY contributor");
            return $this->db->resultSet();   
        }
        elseif ($category == 2 && $_SESSION['isParish'] == 1 ) {
            $this->db->query("SELECT c.ID,
                                     CONCAT(UCASE(groupName),'-',UCASE(c.CongregationName)) AS contributor
                              FROM   tblgroups g INNER JOIN tblcongregation C 
                                     ON g.congregationId = c.ID
                              WHERE  (c.deleted=0) AND (active=1)
                              ORDER BY contributor");
            return $this->db->resultSet();
        }
        elseif ($category == 3 && $_SESSION['isParish'] == 1 ) {
            $this->db->query("SELECT d.ID,
                                     CONCAT(UCASE(districtName),'-',UCASE(c.CongregationName)) AS contributor
                              FROM   tbldistricts d inner join tblcongregation c
                                     ON d.congregationId = c.ID
                              WHERE (d.deleted=0)
                              ORDER BY contributor");
            return $this->db->resultSet();
        }
        elseif ($category == 4 && $_SESSION['isParish'] == 1 ) {
            $this->db->query("SELECT s.ID,
                                     CONCAT(UCASE(serviceName),'-',UCASE(c.CongregationName)) AS contributor
                              FROM   tblservices s inner join tblcongregation c
                                     ON s.congregationId = c.ID
                              WHERE  (s.deleted=0)
                              ORDER BY contributor");
            return $this->db->resultSet();
        }
    }
    public function checkreceiptno($receiptno,$date,$id)
    {
        $this->db->query('SELECT COUNT(*) 
                          FROM tblcontributions_header 
                          WHERE (receiptNo=:rno) AND (ID <> :id) AND (congregationId=:cid)
                                AND (fiscalYearId = :yid)');
        $this->db->bind(':rno',trim($receiptno));
        $this->db->bind(':id',$id);
        $this->db->bind(':cid',$_SESSION['congId']);
        $this->db->bind(':yid',getYearId($this->db->dbh,$date));
        if((int)$this->db->getValue() > 0){
            return false;
        }else{
            return true;
        }
    }
    public function getaccountdetails($account)
    {
        $this->db->query('SELECT accountTypeId,forGroup FROM tblaccounttypes WHERE (accountType = :account)');
        $this->db->bind('account',trim($account));
        $details = $this->db->single();
        $arr = [];
        array_push($arr,$details->accountTypeId);
        array_push($arr,$details->forGroup);
        return $arr;
    }
    public function save($data)
    {
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            
            if (!empty($data['bank']) || $data['bank'] != NULL) {
                $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
                $this->db->bind(':id',trim($data['bank']));
                $bankname = strtolower($this->db->getValue());
            }
             
            $fid = getYearId($this->db->dbh,$data['date']);
            $this->db->query('INSERT INTO tblcontributions_header (fiscalYearId,postedBy,postedDate,
                                        receiptNo,congregationId)
                            VALUES(:fid,:post,:pdate,:receipt,:cong)');
            $this->db->bind(':fid',$fid);
            $this->db->bind(':post',$_SESSION['userId']);
            $this->db->bind(':pdate',date('Y-m-d'));
            $this->db->bind(':receipt',$data['receiptno']);
            $this->db->bind(':cong',$_SESSION['congId']);
            $this->db->execute();
            $tid = $this->db->dbh->lastInsertId();
             //details
            for ($i=0; $i < count($data['accountsid']); $i++) { 
                $accountid = $this->getaccountdetails(strtolower($data['accountsname'][$i]))[0];
                $forgroup = converttobool($this->getaccountdetails(strtolower($data['accountsname'][$i]))[1]);
                $this->db->query('INSERT INTO tblcontributions_details(HeaderId,contributionDate,contributionTypeId
                                                ,paymentMethodId,bankId,amount,category,contributor,
                                                contributotGroup,contributotDistrict,contributotService,
                                                paymentReference,narration,incomeType,forGroup)
                                  VALUES(:id,:cdate,:typeid,:mid,:bid,:amount,:cat,:cont,:gcont,:dcont,:scont
                                            ,:ref,:narr,:itype,:for)');
                $this->db->bind(':id',$tid);                            
                $this->db->bind(':cdate',$data['date']);                            
                $this->db->bind(':typeid',$data['accountsid'][$i]);                            
                $this->db->bind(':mid',$data['paymethod']);                            
                $this->db->bind(':bid',!empty($data['bank']) ? $data['bank'] : null);                            
                $this->db->bind(':amount',$data['amounts'][$i]);                            
                $this->db->bind(':cat',$data['categoriesid'][$i]);                            
                $this->db->bind(':cont',(int)$data['categoriesid'][$i] === 1 ? $data['contributorsid'][$i] : NULL);                            
                $this->db->bind(':gcont',(int)$data['categoriesid'][$i] === 2 ? $data['contributorsid'][$i] : NULL);                            
                $this->db->bind(':dcont',(int)$data['categoriesid'][$i] === 3 ? $data['contributorsid'][$i] : NULL);                            
                $this->db->bind(':scont',(int)$data['categoriesid'][$i] === 4 ? $data['contributorsid'][$i] : NULL);                            
                $this->db->bind(':ref',!empty($data['reference']) ? strtolower($data['reference']) : NULL);                            
                $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : NULL);                            
                $this->db->bind(':itype',1);                            
                $this->db->bind(':for',$forgroup);                            
                $this->db->execute();

                if((int)$data['categoriesid'][$i] === 2){
                    $this->db->query('INSERT INTO tblmmf (TransactionDate,GroupId,Debit,Reference,Narration,TransactionType,
                                                            TransactionId,CongregationId) VALUES(:tdate,:gid,:debit,:ref,:narr,:ttype,:tid,:cid)');
                    $this->db->bind(':tdate',$data['date']);
                    $this->db->bind(':gid',$data['contributorsid'][$i]);
                    $this->db->bind(':debit',$data['amounts'][$i]);
                    $this->db->bind(':ref',$data['receiptno']);
                    $this->db->bind(':narr','Receipts for ' .date('d-m-Y',strtotime($data['date'])));
                    $this->db->bind(':ttype',1);
                    $this->db->bind(':tid',$tid);
                    $this->db->bind(':cid',$_SESSION['congId']);
                    $this->db->execute();
                    
                    saveToLedger($this->db->dbh,$data['date'],'groups balances held',0,$data['amounts'][$i]
                        ,$data['description'],4,1,$tid,$_SESSION['congId']);
                }else{
                    saveToLedger($this->db->dbh,$data['date'],strtolower($data['accountsname'][$i]),0,$data['amounts'][$i]
                        ,$data['description'],$accountid,1,$tid,$_SESSION['congId']);
                }
            } 
                
            if ($data['paymethod'] == 1) {
                saveToLedger($this->db->dbh,$data['date'],'cash at hand',$data['totalamount'],0,$data['description'],3,1,
                            $tid,$_SESSION['congId']);
            }else{
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$data['totalamount'],0,$data['description'],3,1,
                            $tid,$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$data['date'],$data['totalamount'],0
                             ,1,$data['reference'],1,$tid,$_SESSION['congId']);            
            }
            // elseif ($data['paymethod'] == 2) {
            //     saveToLedger($this->db->dbh,$data['date'],'mpesa',$data['amount'],0,$data['description'],3,1,
            //                 $tid,$_SESSION['congId']);
            // }
            
            $act = 'Created Contribution For '.$data['date'];
            saveLog($this->db->dbh,$act);
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
    public function create($data)
    {
        if(!$data['isedit']){
            return $this->save($data);
        }else{
            return $this->update($data);
        }
    }
    public function getforgroup($account)
    {
        $this->db->query('SELECT forGroup,accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        $results = $this->db->fetch();
        foreach ($results as $result ) {
            $output['forGroup'] = $result['forGroup'];
            $output['accountTypeId'] = $result['accountTypeId'];
        }
        return json_encode($output);
    }
    public function approve($data)
    {
        $this->db->query('UPDATE tblcontributions_header SET `status`=:app WHERE (ID=:id)');
        $this->db->bind(':app',1);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Approved Contribution For '.$data['contributor']. ' For Date '.$data['date'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function delete($data)
    {
        $this->db->query('UPDATE tblcontributions_header SET deleted=:del WHERE (ID=:id)');
        $this->db->bind(':del',1);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $this->db->query('UPDATE tblledger SET deleted=:del 
                             WHERE (transactionType=1) AND (transactionId=:id)');
            $this->db->bind(':del',1);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            $act = 'Deleted Contribution For '.$data['contributor']. ' For Date '.$data['date'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
    public function contributionHeader($id)
    {
        $this->db->query('SELECT 
                            h.receiptNo,
                            `contributionDate`,
                            `paymentMethodId`,
                            `bankId`,
                            `narration`,
                            `paymentReference`,
                            h.congregationId,
                            h.ID
                          FROM `tblcontributions_details` d 
                            INNER JOIN tblcontributions_header h
                            ON d.HeaderId = h.ID
                          WHERE HeaderId = :id
                          LIMIT 1;');
        $this->db->bind(':id',intval($id));
        return $this->db->single();
    }
    public function getContribution($id)
    {
        $this->db->query('SELECT * FROM vw_contributorspertxn WHERE (HeaderId = :id)');
        $this->db->bind(':id',$id);                         
        return $this->db->resultSet();
    }
    public function update($data)
    {
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
             
            $fid = getYearId($this->db->dbh,$data['date']);
            $this->db->query('UPDATE tblcontributions_header SET fiscalYearId=:fid,receiptNo=:receipt
                              WHERE  (ID = :id)');
            $this->db->bind(':fid',$fid);
            $this->db->bind(':receipt',$data['receiptno']);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();

            $this->db->query('DELETE FROM tblcontributions_details WHERE HeaderId = :hid');
            $this->db->bind(':hid', $data['id']);
            $this->db->execute();

            $this->db->query('DELETE FROM tblmmf WHERE TransactionType = 1 AND TransactionId = :tid');
            $this->db->bind(':tid',$data['id']);
            $this->db->execute();

            deleteLedgerBanking($this->db->dbh,1,$data['id']);
             //details
            for ($i=0; $i < count($data['accountsid']); $i++) { 
                $accountid = $this->getaccountdetails(strtolower($data['accountsname'][$i]))[0];
                $forgroup = converttobool($this->getaccountdetails(strtolower($data['accountsname'][$i]))[1]);
                $this->db->query('INSERT INTO tblcontributions_details(HeaderId,contributionDate,contributionTypeId
                                                ,paymentMethodId,bankId,amount,category,contributor,
                                                contributotGroup,contributotDistrict,contributotService,
                                                paymentReference,narration,incomeType,forGroup)
                                  VALUES(:id,:cdate,:typeid,:mid,:bid,:amount,:cat,:cont,:gcont,:dcont,:scont
                                            ,:ref,:narr,:itype,:for)');
                $this->db->bind(':id',$data['id']);                            
                $this->db->bind(':cdate',$data['date']);                            
                $this->db->bind(':typeid',$data['accountsid'][$i]);                            
                $this->db->bind(':mid',$data['paymethod']);                            
                $this->db->bind(':bid',!empty($data['bank']) ? $data['bank'] : null);                            
                $this->db->bind(':amount',$data['amounts'][$i]);                            
                $this->db->bind(':cat',$data['categoriesid'][$i]);                            
                $this->db->bind(':cont',(int)$data['categoriesid'][$i] === 1 ? $data['contributorsid'][$i] : NULL);                            
                $this->db->bind(':gcont',(int)$data['categoriesid'][$i] === 2 ? $data['contributorsid'][$i] : NULL);                            
                $this->db->bind(':dcont',(int)$data['categoriesid'][$i] === 3 ? $data['contributorsid'][$i] : NULL);                            
                $this->db->bind(':scont',(int)$data['categoriesid'][$i] === 4 ? $data['contributorsid'][$i] : NULL);                            
                $this->db->bind(':ref',!empty($data['reference']) ? strtolower($data['reference']) : NULL);                            
                $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : NULL);                            
                $this->db->bind(':itype',1);                            
                $this->db->bind(':for',$forgroup);                            
                $this->db->execute();

                if((int)$data['categoriesid'][$i] === 2){
                    $this->db->query('INSERT INTO tblmmf (TransactionDate,GroupId,Debit,Reference,TransactionType,
                                                            TransactionId,CongregationId) VALUES(:tdate,:gid,:debit,:ref,:ttype,:tid,:cid)');
                    $this->db->bind(':tdate',$data['date']);
                    $this->db->bind(':gid',$data['contributorsid'][$i]);
                    $this->db->bind(':debit',$data['amounts'][$i]);
                    $this->db->bind(':ref',$data['receiptno']);
                    $this->db->bind(':ttype',1);
                    $this->db->bind(':tid',$data['id']);
                    $this->db->bind(':cid',$_SESSION['congId']);
                    $this->db->execute();

                    saveToLedger($this->db->dbh,$data['date'],'groups balances held',0,$data['amounts'][$i]
                        ,$data['description'],4,1,$data['id'],$_SESSION['congId']);
                }else{
                    saveToLedger($this->db->dbh,$data['date'],strtolower($data['accountsname'][$i]),0,$data['amounts'][$i]
                        ,$data['description'],$accountid,1,$data['id'],$_SESSION['congId']);
                }
            } 
                
            if ($data['paymethod'] == 1) {
                saveToLedger($this->db->dbh,$data['date'],'cash at hand',$data['totalamount'],0,$data['description'],3,1,
                            $data['id'],$_SESSION['congId']);
            }else{
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$data['totalamount'],0,$data['description'],3,1,
                            $data['id'],$_SESSION['congId']);
                saveToBanking($this->db->dbh,$data['bank'],$data['date'],$data['totalamount'],0
                             ,1,$data['reference'],1,$data['id'],$_SESSION['congId']);            
            }

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
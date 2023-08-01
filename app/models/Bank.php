<?php
class Bank {
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
    public function getBanks()
    {
        $this->db->query('SELECT ID,
                                 UCASE(accountType) as accountType,
                                 UCASE(accountNo) As accountNo 
                          FROM tblaccounttypes
                          WHERE (isBank=1) AND (congregationId=:cid) AND (deleted=0)');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function checkExists($name, $id)
    {
        $this->db->query('SELECT ID FROM tblaccounttypes WHERE (accountNo=:ame) AND (ID <> :id)');
        $this->db->bind(':ame',$name);
        $this->db->bind(':id',$id);
        $this->db->execute();
        if ($this->db->rowCount() > 0) {
           return false;
        }else{
            return true;
        }
    }
    public function create($data)
    {
        try {
            //start transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblaccounttypes (accountType,accountTypeId,parentId,
                              isBank,accountNo,congregationId) VALUES(:bname,:tid,:pid,:isbank,:acc,:cid)');
            $this->db->bind(':bname',$data['bankname']);
            $this->db->bind(':tid',3);
            $this->db->bind(':pid',22);
            $this->db->bind(':isbank',1);
            $this->db->bind(':acc',!empty($data['account']) ? $data['account'] : NULL);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();
            $tid = $this->db->dbh->lastInsertId();

            if ($data['openingbal'] > 0) {
                $sql = 'SELECT COUNT(ID) FROM tblaccounttypes WHERE (accountType=?)';
                $param = trim('opening balance equity');
                if (getRecordExists($sql,$this->db->dbh,$param) == 0) {
                    $this->db->query('INSERT INTO tblaccounttypes (accountType,accountTypeId,parentId)
                                      VALUES(:atype,:aid,:pid)');
                    $this->db->bind(':atype',$param);
                    $this->db->bind(':aid',6);
                    $this->db->bind(':pid',6);
                    $this->db->execute();
                }

                $cabparent = getparentgl($this->db->dbh,'cash at bank'); //cash at bank parent

                saveToLedger($this->db->dbh,$data['asof'],$param,$param,0,$data['openingbal'],$param,6,8,
                             $tid,$_SESSION['congId']);
                saveToLedger($this->db->dbh,$data['asof'],'cash at bank',$cabparent,$data['openingbal'],0,$param,3,8,
                             $tid,$_SESSION['congId']);
                saveToBanking($this->db->dbh,$tid,$data['asof'],$data['openingbal'],0,4,$param,8,
                              $tid,$_SESSION['congId']);             
            }
            //save logs
            $act = 'Created Bank '.$data['bankname'];
            saveLog($this->db->dbh,$act);

            //commit transaction
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
            error_log($e->getMessage(),0);
        }
    }
    public function getbank($id)
    {
        $this->db->query('SELECT * FROM tblaccounttypes WHERE (ID=:id) AND (isBank=1)');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function update($data)
    {
        $this->db->query('UPDATE tblaccounttypes SET accountType=:typ,accountNo=:acc WHERE (ID=:id)');
        $this->db->bind(':typ',$data['bankname']);
        $this->db->bind(':acc',!empty($data['account']) ? $data['account'] : NULL);
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Updated Bank '.$data['bankname'];
            saveLog($this->db->dbh,$act);
            return true;
        }else{
            return false;
        }
    }

    public function checkreferenced($id)
    {
        $count = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblbankpostings WHERE bankId = ?',[(int)$id]);
        if((int)$count > 0){
            return false;
        }else{
            return true;
        }
    }

    public function delete($data)
    {
       
        try {
            //start transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblaccounttypes SET deleted=:del WHERE (ID=:id)');
            $this->db->bind(':del',1);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();

            $this->db->query('DELETE FROM tblledger WHERE (transactionType = 8) AND (transactionId = :id)');
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            
            //save logs
            $act = 'Deleted Bank '.$data['bankname'];
            saveLog($this->db->dbh,$act);

            //commit transaction
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
        }
    }

    public function GetDistrictOrGroup($type)
    {
        $sql = '';
        if($type == 'group'){
            $sql = 'SELECT ID,UCASE(groupName) as ColumnName FROM tblgroups WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }else{
            $sql = 'SELECT ID,UCASE(districtName) as ColumnName FROM tbldistricts WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }

        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function CheckSubAccountExists($data)
    {
        $value = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblbanksubaccounts 
                                            WHERE (AccountName=?) AND (Deleted=0) AND (ID <> ?)',[$data['name'],$data['id']]);
        if($value > 0) return false;
        return true;
    }

    public function CreateUpdateSubAccount($data)
    {
        if(!$data['isedit']){
            $this->db->query('INSERT INTO tblbanksubaccounts(AccountName, BankId, AccountId, GroupDistrict, GroupId, DistrictId) 
                          VALUES(:aname,:bid,:aid,:groupdistrict,:gid,:did)');
        }else{
            $this->db->query('UPDATE tblbanksubaccounts SET AccountName=:aname,BankId=:bid,AccountId=:aid,GroupDistrict=:groupdistrict,
                                                            GroupId=:gid,DistrictId=:did WHERE (ID=:id)');
        }
        $this->db->bind(':aname',$data['name']);
        $this->db->bind(':bid',$data['bank']);
        $this->db->bind(':aid',$data['account']);
        $this->db->bind(':groupdistrict',$data['districtgroup']);
        $this->db->bind(':gid',$data['districtgroup'] === 'group' ? $data['param'] : null);
        $this->db->bind(':did',$data['districtgroup'] === 'district' ? $data['param'] : null);
        if($data['isedit']){
            $this->db->bind(':id',$data['id']);
        }
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function GetSubAccount($id)
    {
        return loadsingleset($this->db->dbh,'SELECT * FROM tblbanksubaccounts WHERE (ID=?)',[$id]);
    }

    public function deletesubaccount($data)
    {
       
        try {
            //start transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('UPDATE tblbanksubaccounts SET Deleted=:del WHERE (ID=:id)');
            $this->db->bind(':del',1);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
            
            //save logs
            $act = 'Deleted sub account '.$data['subaccount'];
            saveLog($this->db->dbh,$act);

            //commit transaction
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
        }
    }
}
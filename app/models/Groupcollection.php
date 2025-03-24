<?php
class Groupcollection
{
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetTransactions()
    {
        $sql = "SELECT
	                m.ID,
                    m.TransactionDate,
                    m.Type,
                    IF(Type = 'district',d.districtName,g.groupName) As DistrictGroup,
                    m.Debit 
                FROM tblmmf m left join tblgroups g on m.GroupId=g.ID left join tbldistricts d on m.DistrictId = d.ID
                WHERE (m.Deleted=0) AND (m.CongregationID=?) AND (m.TransactionType=17)
                ORDER BY TransactionDate DESC;";
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetGroups()
    {
        $this->db->query('SELECT ID,UCASE(groupName) as groupName 
                          FROM tblgroups 
                          WHERE (active = 1) AND (deleted = 0) AND (congregationId = :cid)
                          ORDER BY groupName');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetDistrictOrGroup($type)
    {
        $sql = '';
        if($type == 'group'){
            $sql = 'SELECT ID,groupName as ColumnName FROM tblgroups WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }elseif($type == 'district'){
            $sql = 'SELECT ID,districtName as ColumnName FROM tbldistricts WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }else{
            $sql = 'SELECT ID,categoryName as ColumnName FROM tblchurchrequisitioncategories WHERE (congregationId=?) AND (deleted=0) ORDER BY ColumnName';
        }

        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetSubAccounts($type,$groupid)
    {
       if($type == 'group'){
         return loadresultset($this->db->dbh,'SELECT ID,AccountName FROM tblbanksubaccounts WHERE (GroupId=?) AND (Deleted=0)',[$groupid]);
       }else{
         return loadresultset($this->db->dbh,'SELECT ID,AccountName FROM tblbanksubaccounts WHERE (DistrictId=?) AND (Deleted=0)',[$groupid]);
       }
    }

    public function GetAccountDetails($subaccount)
    {
        $accountid = getdbvalue($this->db->dbh,'SELECT AccountId FROM tblbanksubaccounts WHERE (ID=?)',[$subaccount]);
        $bankid = getdbvalue($this->db->dbh,'SELECT BankId FROM tblbanksubaccounts WHERE (ID=?)',[$subaccount]);
        $accountname = getdbvalue($this->db->dbh,'SELECT accountType FROM tblaccounttypes WHERE (ID=?)',[$accountid]);
        return [$accountid,$accountname,$bankid];
    }

    function GetAccountType($account)
    {
        return getdbvalue($this->db->dbh,'SELECT accountTypeId FROM tblaccounttypes WHERE ID=?',[$account]);
    }

    function Save($data)
    {
        try {
            
            $this->db->dbh->beginTransaction();

            $this->db->query('INSERT INTO tblmmf (TransactionDate,`Type`,DistrictId,GroupId,Debit,SubAccountId,Narration,TransactionType,
                                                  CongregationId) VALUES(:tdate,:typ,:did,:gid,:debit,:subid,:narr,:ttype,:cid)');
            $this->db->bind(':tdate',$data['tdate']);
            $this->db->bind(':typ',$data['type']);
            $this->db->bind(':did',$data['type'] == 'district' ? $data['groupid'] : null);
            $this->db->bind(':gid',$data['type'] == 'group' ? $data['groupid'] : null);
            $this->db->bind(':debit',$data['amount']);
            $this->db->bind(':subid',$data['subaccount']);
            $this->db->bind(':narr',$data['narration']);
            $this->db->bind(':ttype',17);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();
            $tid = $this->db->dbh->lastInsertId();

            $gbhparent = getparentgl($this->db->dbh,$data['account']); 
            $accountid = $this->GetAccountType($data['accountid']);
            
            saveToLedger($this->db->dbh,$data['tdate'],$data['account'],$gbhparent,0,$data['amount']
                        ,$data['narration'],$accountid,17,$tid,$_SESSION['congId'],null);

            $cashparent = getparentgl($this->db->dbh,'cash at bank');
            saveToLedger($this->db->dbh,$data['tdate'],'cash at bank',$cashparent,$data['amount'],0,
                         $data['narration'],3,17,$tid,$_SESSION['congId'],null);

            saveToBanking($this->db->dbh,$data['bankid'],$data['tdate'],$data['amount'],0
                         ,1,$data['narration'],17,$tid,$_SESSION['congId']);

            if (!$this->db->dbh->commit()) return false;

            return true;

        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
            return false;
        }
    }

    function Update($data)
    {
        try {
            
            $this->db->dbh->beginTransaction();

            $this->db->query('UPDATE tblmmf set TransactionDate=:tdate,GroupId=:gid,Debit=:debit,SubAccountId=:subid,Narration=:narr
                              WHERE (ID=:id)');
            $this->db->bind(':tdate',$data['tdate']);
            $this->db->bind(':gid',$data['groupid']);
            $this->db->bind(':debit',$data['amount']);
            $this->db->bind(':subid',$data['subaccount']);
            $this->db->bind(':narr',$data['narration']);
            $this->db->bind(':id',$data['id']);
            $this->db->execute();
           
            deleteLedgerBanking($this->db->dbh,17,$data['id']);

            $gbhparent = getparentgl($this->db->dbh,$data['account']); 
            $accountid = $this->GetAccountType($data['accountid']);
            
            saveToLedger($this->db->dbh,$data['tdate'],$data['account'],$gbhparent,0,$data['amount']
                        ,$data['narration'],$accountid,17,$data['id'],$_SESSION['congId'],null);

            $cashparent = getparentgl($this->db->dbh,'cash at bank');
            saveToLedger($this->db->dbh,$data['tdate'],'cash at bank',$cashparent,$data['amount'],0,
                         $data['narration'],3,17,$data['id'],$_SESSION['congId'],null);

            saveToBanking($this->db->dbh,$data['bankid'],$data['tdate'],$data['amount'],0
                         ,1,$data['narration'],17,$data['id'],$_SESSION['congId']);

            if (!$this->db->dbh->commit()) return false;

            return true;

        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
            return false;
        }
    }

    public function CreateUpdate($data)
    {
        if(!$data['isedit'])
        {
            return $this->Save($data);
        }
        else
        {
            return $this->Update($data);
        }
    }

    public function GetCollection($id)
    {
        return loadsingleset($this->db->dbh,'SELECT * FROM tblmmf WHERE (ID=?)',[$id]);
    }

    public function Delete($id)
    {
        try {
            
            $this->db->dbh->beginTransaction();

            $this->db->query('UPDATE tblmmf set Deleted=1
                              WHERE (ID=:id)');
            $this->db->bind(':id',$id);
            $this->db->execute();
           
            softdeleteLedgerBanking($this->db->dbh,17,$id);

            if (!$this->db->dbh->commit()) return false;

            return true;

        } catch (\Exception $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->dbh->rollback();
            }
            error_log($e->getMessage(),0);
            return false;
        }
    }
}
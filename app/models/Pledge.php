<?php
class Pledge{
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
        $this->db->query('SELECT * FROM vw_pledgewithbalances 
                          WHERE (congregationId=:id) AND (deleted=0)');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
    }
    public function paymentMethods()
    {
       return paymentMethods($this->db->dbh);
    }

    public function getBanks()
    {
       if ($_SESSION['isParish'] == 1) {
           return getBanksAll($this->db->dbh);
       }else{
           return getBanks($this->db->dbh,$_SESSION['congId']);
       }
    }
    public function getPledger($category)
    {
        if ($category == 1 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(memberName) AS pledger
                    FROM tblmember WHERE (deleted=0) AND (memberStatus=1) AND (congregationId=:cid)
                    ORDER BY pledger');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();   
        }
        elseif ($category == 2 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(groupName) AS pledger
                    FROM tblgroups WHERE (deleted=0) AND (active=1) AND (congregationId=:cid)
                    ORDER BY pledger');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }
        elseif ($category == 3 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(districtName) AS pledger
                    FROM tbldistricts WHERE (deleted=0) AND (congregationId=:cid)
                    ORDER BY pledger');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }
    }
    public function create($data)
    {
        $id = getLastId($this->db->dbh,'tblpledges_header');
        if ($data['category'] == 1) {
            $member = $data['pledger'];
            $group = null;
            $district =null;
        }
        elseif ($data['category'] == 2) {
            $member = null;
            $group = $data['pledger'];
            $district =null;
        }
        else {
            $member = null;
            $group = null;
            $district =$data['pledger'];
        }

        $balance = floatval($data['amountpledged']) - floatval($data['amountpaid']);
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblpledges_header (ID,pledgeDate,category,pledgerMember,
                                          pledgerGroup,pledgerDistrict,amountPledged,congregationId)
                              VALUES(:id,:pdate,:cat,:mem,:grp,:dst,:pledged,:cid)');
            $this->db->bind(':id',$id);
            $this->db->bind(':pdate',$data['date']);
            $this->db->bind(':cat',$data['category']);
            $this->db->bind(':mem',$member);
            $this->db->bind(':grp',$group);
            $this->db->bind(':dst',$district);
            $this->db->bind(':pledged',$data['amountpledged']);
            $this->db->bind(':cid',$_SESSION['congId']);
            $this->db->execute();
            //details
            $this->db->query('INSERT INTO tblpledges_details (pledgeId,paymentDate,openingBal,
                                          amountPaid,balance,paymethodId,bankId,payReference)
                              VALUES(:id,:pdate,:obal,:paid,:bal,:pid,:bid,:ref)');
            $this->db->bind(':id',$id);
            $this->db->bind(':pdate',$data['date']);
            $this->db->bind(':obal',$data['amountpledged']);
            $this->db->bind(':paid',!empty($data['amountpaid']) ? $data['amountpaid'] : 0);
            $this->db->bind(':bal',$balance);
            $this->db->bind(':pid',$data['paymethod']);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':ref',strtolower($data['reference']));
            $this->db->execute();
            //ledger and bankings
            $narr = 'Pledge Payment For '.strtolower($data['pledgername']);
            $cabparent = getparentgl($this->db->dbh,'cast at bank');
            if ($data['amountpaid'] > 0) {
                saveToLedger($this->db->dbh,$data['date'],'donations and fundraising','other collections',0,$data['amountpaid'],$narr,
                             1,3,$id,$_SESSION['congId'],$data['reference']);
                if ($data['paymethod'] == 1) {
                    saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cabparent,$data['amountpaid'],0,$narr,
                             3,3,$id,$_SESSION['congId'],$data['reference']);
                }
                elseif ($data['paymethod'] == 2) {
                    saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,$data['amountpaid'],0,$narr,
                             3,3,$id,$_SESSION['congId'],$data['reference']);
                }
                else{
                    saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,$data['amountpaid'],0,$narr,
                             3,3,$id,$_SESSION['congId'],$data['reference']);
                    saveToBanking($this->db->dbh,$data['bank'],$data['date'],$data['amountpaid'],
                                  $_SESSION['zero'],$_SESSION['one'],strtolower($data['reference']),3,$id,$_SESSION['congId']);         
                }             
            }
            $act = 'Created Pledge For '.strtolower($data['pledgername']);
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
            error_log($e->getMessage(),0);
        }
    }
    public function getPledge($id)
    {
        $this->db->query('SELECT IF(h.category=1,UCASE(m.memberName),IF(h.category=2,
                                   ucase(g.groupName),ucase(d.districtName))) as pledger,
                                   FORMAT(h.amountPledged,2) AS amountPledged,
                                   getTotalPledge(h.ID) as totalPaid,
                                   (h.amountPledged - getTotalPledge(h.ID)) as balance,
                                   h.congregationId,h.deleted,h.ID
                          FROM tblpledges_header h left join tblmember m on h.pledgerMember=m.ID
                               left join tblgroups g on h.pledgerGroup =g.ID left join tbldistricts d
                               on h.pledgerDistrict=d.ID
                          WHERE h.ID = :id');
        $this->db->bind(':id',$id);
        return $this->db->single();
    }
    public function pay($data)
    {
        if (!empty($data['bank']) || $data['bank'] != NULL) {
            $this->db->query('SELECT accountType FROM tblaccounttypes WHERE (ID=:id)');
            $this->db->bind(':id',trim($data['bank']));
            $bankname = strtolower($this->db->getValue());
        }
        $balance = floatval($data['balance']) - floatval($data['paid']);
        try {
            //begin transaction
            $this->db->dbh->beginTransaction();
            $this->db->query('INSERT INTO tblpledges_details (pledgeId,paymentDate,openingBal,
                                          amountPaid,balance,paymethodId,bankId,payReference)
                              VALUES(:pid,:pdate,:obal,:paid,:bal,:pay,:bid,:ref)');
            $this->db->bind(':pid',$data['id']);
            $this->db->bind(':pdate',$data['date']);
            $this->db->bind(':obal',$data['balance']);
            $this->db->bind(':paid',$data['paid']);
            $this->db->bind(':bal',$balance);
            $this->db->bind(':pay',$data['paymethod']);
            $this->db->bind(':bid',$data['bank']);
            $this->db->bind(':ref',$data['reference']);
            $this->db->execute();
            $tid = $this->db->dbh->lastInsertId();
            //ledgers
            $narr = 'Pledge Payment For '.$data['pledger'];
            saveToLedger($this->db->dbh,$data['date'],'donations and fundraising','other collections',0,$data['paid'],$narr,
                             1,4,$tid,$_SESSION['congId'],$data['reference']);
            $cabparent = getparentgl($this->db->dbh,'cast at bank');                 
            if ($data['paymethod'] == 1) {
                saveToLedger($this->db->dbh,$data['date'],'cash at hand',$cabparent,$data['paid'],0,$narr,
                            3,4,$tid,$_SESSION['congId'],$data['reference']);
            }
            elseif ($data['paymethod'] == 2) {
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,$data['paid'],0,$narr,
                            3,4,$tid,$_SESSION['congId'],$data['reference']);
            }
            else{
                saveToLedger($this->db->dbh,$data['date'],'cash at bank',$cabparent,$data['paid'],0,$narr,
                            3,4,$tid,$_SESSION['congId'],$data['reference']);
                saveToBanking($this->db->dbh,$data['bank'],$data['date'],$data['paid'],
                                $_SESSION['zero'],$_SESSION['one'],strtolower($data['reference']),4,$tid,$_SESSION['congId']);         
            }
            
            saveLog($this->db->dbh,$narr);
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
    public function delete($data)
    {
        $this->db->query('UPDATE tblpledges_header SET deleted=1 WHERE (ID=:id)');
        $this->db->bind(':id',$data['id']);
        if ($this->db->execute()) {
            $act = 'Deleted Pledge For '.$data['pledger'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }
}
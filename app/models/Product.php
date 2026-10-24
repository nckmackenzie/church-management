<?php

class Product
{
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetProducts()
    {
        $this->db->query('SELECT p.ID,
                                 UCASE(productName) As productName,
                                 FORMAT(rate,2) As rate,
                                 UCASE(accountType) As glAccount
                          FROM 
                            tblproducts p join tblaccounttypes a on p.accountId = a.ID
                          WHERE
                            (p.deleted = 0) 
                                AND (p.congregationId = :cid)
                          ORDER BY productName');
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetGlAccounts()
    {
        // a product serves supplier invoices (expense / non-bank asset accounts) and customer invoices (income accounts):
        // the posting accounts of those roots, the same lists the invoice forms validate against
        $all = array_merge(
            Ledger::listLineAccounts($this->db->dbh,(int)$_SESSION['congId'],Ledger::SUPPLIER_LINE_TYPES),
            Ledger::listLineAccounts($this->db->dbh,(int)$_SESSION['congId'],Ledger::CUSTOMER_LINE_TYPES)
        );
        usort($all,function($x,$y){ return strcasecmp($x['accountType'],$y['accountType']); });
        return array_map(function($a){ return (object)['ID' => $a['ID'],'accountType' => strtoupper($a['accountType'])]; },$all);
    }

    /** Why a new product may not use this G/L account, or null. */
    public function AccountProblem($id)
    {
        if (!ctype_digit((string)$id) || (int)$id <= 0) { return 'Select G/L account'; }
        $cong = (int)$_SESSION['congId'];
        $a = Ledger::lineAccountProblem($this->db->dbh,$cong,(int)$id,Ledger::SUPPLIER_LINE_TYPES);
        $b = Ledger::lineAccountProblem($this->db->dbh,$cong,(int)$id,Ledger::CUSTOMER_LINE_TYPES);
        return ($a === null || $b === null) ? null : 'Select an income, expense or asset posting account';
    }

    public function CheckExist($name,$id)
    {
        $sql = 'SELECT COUNT(*) FROM tblproducts WHERE (productName = ?) AND (ID <> ?) AND (deleted = 0) AND (congregationId = ?)';
        return getdbvalue($this->db->dbh,$sql,[strtolower($name),$id,(int)$_SESSION['congId']]);
    }

    public function CreateUpdate($data)
    {
        if(!$data['isedit']){
            $this->db->query('INSERT INTO tblproducts (productName,`description`,rate,accountId,congregationId) 
                              VALUES(:pname,:narr,:rate,:account,:cid)');
        }else{
            $this->db->query('UPDATE tblproducts SET productName=:pname,`description`=:narr,rate=:rate 
                              WHERE (ID = :id) AND (congregationId = :cid)');
        }
        $this->db->bind(':pname',!empty($data['productname']) ? strtolower($data['productname']) : null);
        $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : null);
        $this->db->bind(':rate',!empty($data['rate']) ? floatval($data['rate']) : null);
        if(!$data['isedit']){
            $this->db->bind(':account',!empty($data['glaccount']) ? (int)$data['glaccount'] : null);
            $this->db->bind(':cid',(int)$_SESSION['congId']);
        }else{
            $this->db->bind(':id',(int)$data['id']);
            $this->db->bind(':cid',(int)$_SESSION['congId']);
        }
        
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function GetProduct($id)
    {
        $this->db->query('SELECT * FROM tblproducts WHERE (ID = :id) AND (congregationId = :cid)');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    public function Delete($id)
    {
        $this->db->query('UPDATE tblproducts SET deleted = 1 WHERE (ID = :id) AND (congregationId = :cid)');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

}

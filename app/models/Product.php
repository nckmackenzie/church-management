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
        $this->db->query('SELECT P.ID,
                                 UCASE(productName) As productName,
                                 FORMAT(rate,2) As rate,
                                 UCASE(accountType) As glAccount
                          FROM 
                            tblproducts p join tblaccounttypes a on p.accountId = a.ID
                          WHERE
                            (p.deleted = 0) AND (p.congregationId = :cid)
                          ORDER BY productName');
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function GetGlAccounts()
    {
        $this->db->query('SELECT ID,
                                 UCASE(accountType) as accountType
                          FROM tblaccounttypes
                          WHERE  (deleted=0) AND (accountTypeId = 1) AND (isSubCategory = 0)');
        return $this->db->resultSet();
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
                              WHERE (ID = :id)');
        }
        $this->db->bind(':pname',!empty($data['productname']) ? strtolower($data['productname']) : null);
        $this->db->bind(':narr',!empty($data['description']) ? strtolower($data['description']) : null);
        $this->db->bind(':rate',!empty($data['rate']) ? floatval($data['rate']) : null);
        if(!$data['isedit']){
            $this->db->bind(':account',!empty($data['glaccount']) ? (int)$data['glaccount'] : null);
            $this->db->bind(':cid',(int)$_SESSION['congId']);
        }else{
            $this->db->bind(':id',(int)$data['id']);
        }
        
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function GetProduct($id)
    {
        $this->db->query('SELECT * FROM tblproducts WHERE (ID = :id)');
        $this->db->bind(':id',(int)$id);
        return $this->db->single();
    }

    public function Delete($id)
    {
        $this->db->query('UPDATE tblproducts SET deleted = 1 WHERE (ID = :id)');
        $this->db->bind(':id',(int)$id);
        if(!$this->db->execute()){
            return false;
        }
        return true;
    }

    public function GetRate($id)
    {
        return getdbvalue($this->db->dbh,'SELECT rate FROM tblproducts WHERE ID = ?',[$id]);
    }
}
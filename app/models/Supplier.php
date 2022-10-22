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
}
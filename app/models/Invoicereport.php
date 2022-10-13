<?php

class Invoicereport
{
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetInvoicesWithBalance()
    {
        return loadresultset($this->db->dbh,'CALL sp_getinvoice_wih_balances(?)',[$_SESSION['congId']]);
    }
}
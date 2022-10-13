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

    public function GetInvoiceNos()
    {
        $sql = 'SELECT h.invoiceNo
                FROM tblinvoice_header_suppliers h
                WHERE (h.congregationId = ?) AND (h.deleted = 0) AND 
                    h.ID IN (SELECT DISTINCT invoice_Id FROM tblinvoice_payments_suppliers)
                ORDER By invoiceNo';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }
}
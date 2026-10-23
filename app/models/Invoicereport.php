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
                    h.ID IN (SELECT DISTINCT invoice_Id FROM tblinvoice_payments_suppliers WHERE Deleted = 0)
                ORDER By invoiceNo';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetPaymentPayInvoice($invoice)
    {
        return loadresultset($this->db->dbh,'CALL sp_get_payments_by_invoiceno(?,?)',[$invoice,(int)$_SESSION['congId']]);
    }

    public function GetSupplierBalances($data)
    {
        $sql = 'SELECT 
                    s.supplierName,
                    IFNULL(SUM(getinvoicebalance_supplier(h.ID)),0) As TotalBalance
                FROM `tblinvoice_header_suppliers` h join tblsuppliers s on h.supplierId = s.ID
                WHERE h.deleted = 0 AND s.deleted = 0 AND h.congregationId = ?
                GROUP BY s.supplierName;';
        return loadresultset($this->db->dbh,$sql,[(int)$_SESSION['congId']]);
    }

    public function GetSuppliers()
    {
        $sql = 'SELECT 
                    DISTINCT h.supplierId AS ID,
                    UCASE(s.supplierName) As supplierName
                FROM tblinvoice_header_suppliers h join tblsuppliers s on h.supplierId = s.ID
                WHERE (h.congregationId = ?) AND (h.deleted = 0) AND 
                    h.ID IN (SELECT DISTINCT invoice_Id FROM tblinvoice_payments_suppliers WHERE Deleted = 0)
                ORDER By supplierName';
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }

    public function GetPaymentPaySupplier($data)
    {
        $sql = 'CALL sp_get_payments_by_supplier(?,?,?,?);';
        return loadresultset($this->db->dbh,$sql,[$data['criteria'],$data['sdate'],$data['edate'],$_SESSION['congId']]);
    }

    public function GetAllPayments($data)
    {
        $sql = 'CALL sp_get_payments_all (?,?,?);';
        return loadresultset($this->db->dbh,$sql,[$data['sdate'],$data['edate'],$_SESSION['congId']]);
    }
}
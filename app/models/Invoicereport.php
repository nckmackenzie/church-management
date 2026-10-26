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

    /**
     * The invoices the payment-by-invoice report can show (decision 134): own congregation, non-deleted normal invoices (the
     * opening-balance rows have no number) that have at least one active payment. The value is the HEADER ID; the label is
     * "supplier - invoice number" (the same number can belong to two suppliers).
     */
    public function GetInvoiceNos()
    {
        $sql = 'SELECT h.ID, UCASE(s.supplierName) AS supplierName, h.invoiceNo
                FROM tblinvoice_header_suppliers h JOIN tblsuppliers s ON s.ID = h.supplierId
                WHERE (h.congregationId = ?) AND (h.deleted = 0) AND (h.invoiceNo IS NOT NULL) AND
                    h.ID IN (SELECT DISTINCT invoice_Id FROM tblinvoice_payments_suppliers WHERE Deleted = 0)
                ORDER BY s.supplierName, h.invoiceNo, h.ID';
        return loadresultset($this->db->dbh,$sql,[(int)$_SESSION['congId']]);
    }

    /** True when the header ID is one of GetInvoiceNos() (own congregation, normal, non-deleted, with payments). */
    public function isReportInvoice($id)
    {
        foreach ($this->GetInvoiceNos() as $row) {
            if ((int)$row->ID === (int)$id) { return true; }
        }
        return false;
    }
    /** Payments of one supplier invoice by HEADER ID (sp_get_payments_by_invoiceno, runbook 4.16a); includes the supplier and the invoice number. */
    public function GetPaymentPayInvoice($invoiceId)
    {
        return loadresultset($this->db->dbh,'CALL sp_get_payments_by_invoiceno(?,?)',[(int)$invoiceId,(int)$_SESSION['congId']]);
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
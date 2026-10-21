<?php
/**
 * The deposit form was removed (deposits are bank transactions now). Only the bank list is still
 * used, by Payments. Saving or deleting deposits is intentionally gone: it wrote type 13 with
 * tbldesposits.ID, which collides with bank transactions in the Ledger.
 */
class Deposit
{
    private $db;
    public function __construct()
    {
        $this->db =  new Database;
    }

    public function GetBanks()
    {
        $sql = "SELECT ID,UCASE(CONCAT(accountType,'-',IFNULL(accountNo,''))) AS Bank FROM tblaccounttypes WHERE isBank = 1 AND CongregationId = ?";
        return loadresultset($this->db->dbh,$sql,[$_SESSION['congId']]);
    }
}

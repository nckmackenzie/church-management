-- payments_by_invoice_header.sql (task 9.1 part C, decision 134), idempotent, MariaDB 10.4/10.6.
-- Run AFTER supplier_invoiceno_varchar.sql (runbook 4.16) and before the 9.1 code (runbook 4.16a).
--
-- sp_get_payments_by_invoiceno takes the supplier invoice HEADER ID, not the invoice number: since 8.8 the number is unique
-- per supplier only (decision 99), so two suppliers can use the same number and a number cannot identify an invoice. The
-- report screen offers "supplier - invoice number" and submits the header ID.
--   before (supplier_accounts_schema.sql, runbook 4.14):  (IN invoiceno VARCHAR(50), IN congid INT)  WHERE h.invoiceNo = invoiceno
--   after  (this file):                                    (IN invoiceid INT, IN congid INT)          WHERE h.ID = invoiceid
-- and the result gains the supplier name and the invoice number (supplierName, invoiceNo) so the report can show them.
-- Payments are ordered by their real date and ID (the old ORDER BY used the dd-mm-yy text alias, so it sorted by the day).
--
-- The routine is replaced only when it is the 4.14 definition (first parameter `invoiceno`), the hand-made INT version that
-- dev carried on 2026-10-24 (first parameter `invoiceid`, no supplier column) or this file's own version (re-running is
-- harmless); any other shape stops the script, so a production routine that differs from dev is looked at, not overwritten.
--
-- Verify: SELECT PARAMETER_NAME, DTD_IDENTIFIER FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE()
--         AND SPECIFIC_NAME='sp_get_payments_by_invoiceno' ORDER BY ORDINAL_POSITION;     -- invoiceid int(11), congid int(11)
--         SELECT ROUTINE_DEFINITION LIKE '%UCASE(s.supplierName)%' FROM information_schema.ROUTINES
--         WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_get_payments_by_invoiceno'; -- 1
-- Rollback: re-run the CREATE of sp_get_payments_by_invoiceno from supplier_accounts_schema.sql (4.14), or restore the
-- cutover backup (0.1). The routine and the 9.1 code go together (the old code passes an invoice number).
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_tmp_payments_by_invoice_header$$
CREATE PROCEDURE sp_tmp_payments_by_invoice_header()
BEGIN
 DECLARE p1 VARCHAR(64) DEFAULT NULL;
 DECLARE np INT DEFAULT 0;
 SELECT COUNT(*) INTO np FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_get_payments_by_invoiceno' AND ROUTINE_TYPE='PROCEDURE';
 IF np=0 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='sp_get_payments_by_invoiceno does not exist: run supplier_accounts_schema.sql (4.14) first';
 END IF;
 SELECT COUNT(*) INTO np FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_get_payments_by_invoiceno';
 SELECT PARAMETER_NAME INTO p1 FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_get_payments_by_invoiceno' AND ORDINAL_POSITION=1;
 IF NOT (np=2 AND p1 IN ('invoiceno','invoiceid')) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='sp_get_payments_by_invoiceno has an unexpected shape: compare it with supplier_accounts_schema.sql before replacing it';
 END IF;
END$$
CALL sp_tmp_payments_by_invoice_header()$$
DROP PROCEDURE sp_tmp_payments_by_invoice_header$$

CREATE OR REPLACE PROCEDURE sp_get_payments_by_invoiceno(IN invoiceid INT, IN congid INT)
BEGIN
 SELECT DATE_FORMAT(p.paymentDate,'%d-%m-%y') AS paymentDate,p.paymentNo,UCASE(s.supplierName) AS supplierName,h.invoiceNo,p.amount,UCASE(m.paymentMethod) AS payMethod,UCASE(p.paymentReference) AS paymentReference
 FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID JOIN tblsuppliers s ON s.ID=h.supplierId LEFT JOIN tblpaymentmethods m ON p.paymentId=m.ID
 WHERE h.ID=invoiceid AND h.congregationId=congid AND h.deleted=0 AND p.Deleted=0
 ORDER BY p.paymentDate,p.ID;
END$$
DELIMITER ;

SELECT PARAMETER_NAME,DTD_IDENTIFIER FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_get_payments_by_invoiceno' ORDER BY ORDINAL_POSITION;
SELECT ROUTINE_DEFINITION LIKE '%UCASE(s.supplierName)%' AS is_v9_1 FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_get_payments_by_invoiceno';

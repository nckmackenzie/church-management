-- payment_reports_order.sql (task 9.7 part D), idempotent, MariaDB 10.4/10.6.
-- Run AFTER payments_by_invoice_header.sql (runbook 4.16a) and before the 9.7 code is relied on (runbook 4.16b).
--
-- Three supplier payment reports sorted by the dd-mm-yy TEXT alias of the payment date, not by the date:
--   sp_get_payments_all, sp_get_payments_by_supplier   ORDER BY paymentDate   (the alias wins over the column: "01-02-26" < "15-01-26")
--   sp_getsupplierpayments                              ORDER BY p.paymentDate (the real column, but no tie-break on the ID)
-- so a report that crosses a month or a year boundary listed the 1st of February before the 15th of January. They now order by
-- the real payment date and then the payment ID. Nothing else changes: same parameters, same columns, same display alias.
-- (sp_get_payments_by_invoiceno was fixed the same way by 4.16a.)
--
-- Each routine is replaced only when it is the definition that runbook 4.14 (supplier_accounts_schema.sql) creates or this file's own
-- result (re-running is harmless). Any other body or parameter list stops the script before anything is changed, so a production
-- routine that differs from dev is looked at, not overwritten. The comparison ignores carriage returns (a CRLF copy of this file
-- stores the same text apart from them).
--   4.14 definitions (md5 of the body, CR removed):  sp_getsupplierpayments 258f1c74b9d1f2ce2d97520f00a67b97
--                                                    sp_get_payments_all de86dd62d066f9cf7306ab98b65c9461
--                                                    sp_get_payments_by_supplier 110e5782d248a9a9b704032c7ebef9bb
--   this file (md5 of the body):                     sp_getsupplierpayments 5c6107351f55dfc09142643466505106
--                                                    sp_get_payments_all 9f8963d1aa7f1757a0837b88fd965c99
--                                                    sp_get_payments_by_supplier 16c29c64c6452c0e6cfd17e9ecbd5ce0
--
-- Verify: SELECT ROUTINE_NAME, MD5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')) FROM information_schema.ROUTINES
--         WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME IN ('sp_getsupplierpayments','sp_get_payments_all','sp_get_payments_by_supplier');
--         -- the three "this file" md5 values above; the last query of this file prints the same list and "ok" per routine.
--         Functional check: payments dated 2026-01-15 and 2026-02-01 come back in that order from CALL sp_get_payments_all(...).
-- Rollback: re-run the three CREATE statements of sp_getsupplierpayments, sp_get_payments_all and sp_get_payments_by_supplier from
--         supplier_accounts_schema.sql (4.14), or restore the cutover backup (0.1). The change is order-only, so the old and the new
--         application code both run against either version.
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_tmp_payment_reports_order$$
CREATE PROCEDURE sp_tmp_payment_reports_order()
BEGIN
 DECLARE n INT DEFAULT 0;
 DECLARE np INT DEFAULT 0;
 DECLARE def VARCHAR(40) DEFAULT NULL;
 -- sp_getsupplierpayments(sid, sdate, edate, congid)
 SELECT COUNT(*) INTO n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_getsupplierpayments' AND ROUTINE_TYPE='PROCEDURE';
 IF n=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='sp_getsupplierpayments does not exist: run supplier_accounts_schema.sql (4.14) first'; END IF;
 SELECT COUNT(*) INTO np FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_getsupplierpayments';
 SELECT MD5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')) INTO def FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_getsupplierpayments' AND ROUTINE_TYPE='PROCEDURE';
 IF NOT (np=4 AND def IN ('258f1c74b9d1f2ce2d97520f00a67b97','5c6107351f55dfc09142643466505106')) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='sp_getsupplierpayments differs from the 4.14 definition: compare it with supplier_accounts_schema.sql before replacing it';
 END IF;
 -- sp_get_payments_all(sdate, edate, congid)
 SELECT COUNT(*) INTO n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_get_payments_all' AND ROUTINE_TYPE='PROCEDURE';
 IF n=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='sp_get_payments_all does not exist: run supplier_accounts_schema.sql (4.14) first'; END IF;
 SELECT COUNT(*) INTO np FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_get_payments_all';
 SELECT MD5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')) INTO def FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_get_payments_all' AND ROUTINE_TYPE='PROCEDURE';
 IF NOT (np=3 AND def IN ('de86dd62d066f9cf7306ab98b65c9461','9f8963d1aa7f1757a0837b88fd965c99')) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='sp_get_payments_all differs from the 4.14 definition: compare it with supplier_accounts_schema.sql before replacing it';
 END IF;
 -- sp_get_payments_by_supplier(suppid, sdate, edate, congid)
 SELECT COUNT(*) INTO n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_get_payments_by_supplier' AND ROUTINE_TYPE='PROCEDURE';
 IF n=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='sp_get_payments_by_supplier does not exist: run supplier_accounts_schema.sql (4.14) first'; END IF;
 SELECT COUNT(*) INTO np FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_get_payments_by_supplier';
 SELECT MD5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')) INTO def FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_get_payments_by_supplier' AND ROUTINE_TYPE='PROCEDURE';
 IF NOT (np=4 AND def IN ('110e5782d248a9a9b704032c7ebef9bb','16c29c64c6452c0e6cfd17e9ecbd5ce0')) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='sp_get_payments_by_supplier differs from the 4.14 definition: compare it with supplier_accounts_schema.sql before replacing it';
 END IF;
END$$
CALL sp_tmp_payment_reports_order()$$
DROP PROCEDURE sp_tmp_payment_reports_order$$

CREATE OR REPLACE PROCEDURE sp_getsupplierpayments(IN sid INT, IN sdate DATE, IN edate DATE, IN congid INT)
BEGIN
 SELECT DATE_FORMAT(p.paymentDate,'%d/%m/%y') AS PaymentDate,FORMAT(p.amount,2) AS Amount,UCASE(m.paymentMethod) AS PaymentMethod,UCASE(p.paymentReference) AS PayReference
 FROM tblinvoice_payments_suppliers p INNER JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID INNER JOIN tblpaymentmethods m ON p.paymentId=m.ID
 WHERE h.supplierId=sid AND h.congregationId=congid AND h.deleted=0 AND p.Deleted=0 AND p.paymentDate BETWEEN sdate AND edate
 ORDER BY p.paymentDate,p.ID;
END$$
CREATE OR REPLACE PROCEDURE sp_get_payments_all(sdate DATE, edate DATE, IN congid INT)
BEGIN
 SELECT DATE_FORMAT(p.paymentDate,'%d-%m-%y') AS paymentDate,p.paymentNo,UCASE(s.supplierName) AS supplierName,h.invoiceNo,p.amount,UCASE(p.paymentReference) AS paymentReference
 FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID LEFT JOIN tblpaymentmethods m ON p.paymentId=m.ID JOIN tblsuppliers s ON h.supplierId=s.ID
 WHERE h.deleted=0 AND p.Deleted=0 AND h.congregationId=congid AND p.paymentDate BETWEEN sdate AND edate
 ORDER BY p.paymentDate,p.ID;
END$$
CREATE OR REPLACE PROCEDURE sp_get_payments_by_supplier(IN suppid INT, sdate DATE, edate DATE, IN congid INT)
BEGIN
 SELECT DATE_FORMAT(p.paymentDate,'%d-%m-%y') AS paymentDate,p.paymentNo,h.invoiceNo,p.amount,UCASE(m.paymentMethod) AS payMethod,UCASE(p.paymentReference) AS paymentReference
 FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID LEFT JOIN tblpaymentmethods m ON p.paymentId=m.ID
 WHERE h.supplierId=suppid AND h.deleted=0 AND p.Deleted=0 AND h.congregationId=congid AND p.paymentDate BETWEEN sdate AND edate
 ORDER BY p.paymentDate,p.ID;
END$$
DELIMITER ;

SELECT ROUTINE_NAME,
       MD5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')) AS definition_md5,
       IF(MD5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')) IN ('5c6107351f55dfc09142643466505106','9f8963d1aa7f1757a0837b88fd965c99','16c29c64c6452c0e6cfd17e9ecbd5ce0'),'ok','UNEXPECTED') AS state
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME IN ('sp_getsupplierpayments','sp_get_payments_all','sp_get_payments_by_supplier')
ORDER BY ROUTINE_NAME;

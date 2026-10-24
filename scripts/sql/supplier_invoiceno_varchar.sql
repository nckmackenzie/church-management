-- supplier_invoiceno_varchar.sql (task 8.8 part A1), idempotent, MariaDB 10.4/10.6.
-- Run AFTER supplier_accounts_schema.sql and before the 8.8 code (the code accepts letters, digits, space and - / . up to 50).
--
-- tblinvoice_header_suppliers.invoiceNo INT -> VARCHAR(50), still NULL for the opening-balance rows. The conversion keeps
-- every number exactly (1234 -> '1234').
-- BEFORE the change, on production too: the new rule is "unique per supplier among the non-deleted normal invoices". The
-- script stops and lists any supplier / number used twice; resolve those rows (renumber one) and run it again. Run this
-- check by hand first on production, where real numbers like INV-0042 will be typed:
--     SELECT supplierId, invoiceNo, COUNT(*) n, GROUP_CONCAT(ID) ids FROM tblinvoice_header_suppliers
--     WHERE deleted = 0 AND invoiceNo IS NOT NULL GROUP BY supplierId, invoiceNo HAVING COUNT(*) > 1;
-- Uniqueness is enforced in the model (Supplierinvoice::save, under the congregation row lock), not by an index: a unique
-- index on (supplierId, invoiceNo) cannot ignore soft-deleted rows, and a deleted invoice's number must be reusable.
--
-- Verify: SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
--         AND TABLE_NAME='tblinvoice_header_suppliers' AND COLUMN_NAME='invoiceNo';   -- varchar(50), YES
-- Rollback: ALTER TABLE tblinvoice_header_suppliers MODIFY invoiceNo INT(11) NULL;  (only while every number is numeric;
-- otherwise restore the cutover backup)
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_tmp_supplier_invoiceno$$
CREATE PROCEDURE sp_tmp_supplier_invoiceno()
BEGIN
 DECLARE n INT;
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_header_suppliers' AND COLUMN_NAME='invoiceNo' AND DATA_TYPE<>'varchar';
 IF n=1 THEN
  SELECT COUNT(*) INTO n FROM (SELECT supplierId,invoiceNo FROM tblinvoice_header_suppliers WHERE deleted=0 AND invoiceNo IS NOT NULL GROUP BY supplierId,invoiceNo HAVING COUNT(*)>1) d;
  IF n>0 THEN
   SELECT supplierId,invoiceNo,COUNT(*) uses,GROUP_CONCAT(ID) header_ids FROM tblinvoice_header_suppliers WHERE deleted=0 AND invoiceNo IS NOT NULL GROUP BY supplierId,invoiceNo HAVING COUNT(*)>1;
   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='invoiceNo conversion refused: a supplier has the same invoice number twice (see the list above)';
  END IF;
  ALTER TABLE tblinvoice_header_suppliers MODIFY invoiceNo VARCHAR(50) NULL;
  SELECT 'invoiceNo converted' result;
 ELSE
  SELECT 'invoiceNo skipped' result;
 END IF;
END$$
CALL sp_tmp_supplier_invoiceno()$$
DROP PROCEDURE sp_tmp_supplier_invoiceno$$
DELIMITER ;
SELECT COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_header_suppliers' AND COLUMN_NAME='invoiceNo';
SELECT COUNT(*) opening_rows_with_number FROM tblinvoice_header_suppliers WHERE invoiceNo IS NULL;

-- supplier_accounts_schema.sql (task 8.7), idempotent, MariaDB 10.4/10.6.
-- Run before the 8.7 code and before fn_checkreferencedaccount_v4.sql (which needs the three new columns).
-- Needs F15 ("accounts payable", ledger_fixes.sql) only for the forms' default, not for this script.
--
-- 1. tblinvoice_header_suppliers.PayableAccountId  - the account the invoice (or opening balance row) credits; a supplier
--    payment debits exactly this account (decision 78). NULL on legacy rows: payment of such an invoice is refused.
-- 2. tblinvoice_details_suppliers.AccountId        - snapshot of the line's debit account at save time, so a later change
--    of the product's account never alters a posted invoice (decision 81).
-- 3. tblinvoice_payments_suppliers.PaymentAccountId - the cash / bank account the payment credits (decision 76).
-- 4. Money columns -> DECIMAL(18,2) (header exclusiveVat / vat / inclusiveVat, details qty / rate / gross, payments
--    amount, tblproducts.rate). The guard refuses a value that is not exact cents (tolerance 0.005 = the FLOAT noise the
--    old columns carry) or does not fit.
-- 5. Indexes on the payment and detail lookups (invoice_Id, header_id).
-- 6. Products 1 and 2 are retired (tblproducts.deleted = 1) when they still point at the reporting accounts 9 / 23
--    (decision 81). The condition is the guard: on a database where they point elsewhere nothing is retired.
-- 7. Views and routines of the supplier balances/statements ignore deleted headers, deleted suppliers and deleted
--    payments, and filter by congregation (sp_getsupplierpayments and sp_get_payments_by_invoiceno gain a congregation
--    parameter; Report::GetSupplierPayments / Invoicereport::GetPaymentPayInvoice pass it).
--
-- Rollback: drop the three FKs, the three indexes and the three columns; restore the routines/views and the old column
-- types from the cutover backup (a conversion back to FLOAT can lose cents); UPDATE tblproducts SET deleted = 0 WHERE ID IN (1,2).
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_tmp_supplier_accounts_schema$$
CREATE PROCEDURE sp_tmp_supplier_accounts_schema()
BEGIN
 DECLARE n INT; DECLARE bad INT;
 -- 1. header.PayableAccountId
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_header_suppliers' AND COLUMN_NAME='PayableAccountId';
 IF n=0 THEN ALTER TABLE tblinvoice_header_suppliers ADD PayableAccountId INT NULL AFTER vatId; SELECT 'PayableAccountId applied' result; ELSE SELECT 'PayableAccountId skipped' result; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_header_suppliers' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='PayableAccountId';
 IF n=0 THEN ALTER TABLE tblinvoice_header_suppliers ADD KEY IDX_SuppInvoice_PayableAccountId(PayableAccountId); END IF;
 SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_header_suppliers' AND CONSTRAINT_NAME='FK_SuppInvoice_PayableAccount' AND CONSTRAINT_TYPE='FOREIGN KEY';
 IF n=0 THEN ALTER TABLE tblinvoice_header_suppliers ADD CONSTRAINT FK_SuppInvoice_PayableAccount FOREIGN KEY(PayableAccountId) REFERENCES tblaccounttypes(ID); END IF;
 -- 2. details.AccountId
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_details_suppliers' AND COLUMN_NAME='AccountId';
 IF n=0 THEN ALTER TABLE tblinvoice_details_suppliers ADD AccountId INT NULL AFTER productId; SELECT 'AccountId applied' result; ELSE SELECT 'AccountId skipped' result; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_details_suppliers' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='AccountId';
 IF n=0 THEN ALTER TABLE tblinvoice_details_suppliers ADD KEY IDX_SuppDetail_AccountId(AccountId); END IF;
 SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_details_suppliers' AND CONSTRAINT_NAME='FK_SuppDetail_Account' AND CONSTRAINT_TYPE='FOREIGN KEY';
 IF n=0 THEN ALTER TABLE tblinvoice_details_suppliers ADD CONSTRAINT FK_SuppDetail_Account FOREIGN KEY(AccountId) REFERENCES tblaccounttypes(ID); END IF;
 -- 3. payments.PaymentAccountId
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_payments_suppliers' AND COLUMN_NAME='PaymentAccountId';
 IF n=0 THEN ALTER TABLE tblinvoice_payments_suppliers ADD PaymentAccountId INT NULL AFTER bankId; SELECT 'PaymentAccountId applied' result; ELSE SELECT 'PaymentAccountId skipped' result; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_payments_suppliers' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='PaymentAccountId';
 IF n=0 THEN ALTER TABLE tblinvoice_payments_suppliers ADD KEY IDX_SuppPayment_PaymentAccountId(PaymentAccountId); END IF;
 SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_payments_suppliers' AND CONSTRAINT_NAME='FK_SuppPayment_PaymentAccount' AND CONSTRAINT_TYPE='FOREIGN KEY';
 IF n=0 THEN ALTER TABLE tblinvoice_payments_suppliers ADD CONSTRAINT FK_SuppPayment_PaymentAccount FOREIGN KEY(PaymentAccountId) REFERENCES tblaccounttypes(ID); END IF;
 -- 5. lookup indexes
 SELECT COUNT(*) INTO n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_payments_suppliers' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='invoice_Id';
 IF n=0 THEN ALTER TABLE tblinvoice_payments_suppliers ADD KEY IDX_SuppPayment_InvoiceId(invoice_Id); END IF;
 SELECT COUNT(*) INTO n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_details_suppliers' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='header_id';
 IF n=0 THEN ALTER TABLE tblinvoice_details_suppliers ADD KEY IDX_SuppDetail_HeaderId(header_id); END IF;
 -- 4. money columns: exact-cents guard, then DECIMAL(18,2)
 SELECT COUNT(*) INTO bad FROM tblinvoice_header_suppliers WHERE (exclusiveVat IS NOT NULL AND (ABS(exclusiveVat-ROUND(exclusiveVat,2))>.005 OR ABS(exclusiveVat)>=10000000000000000)) OR (vat IS NOT NULL AND (ABS(vat-ROUND(vat,2))>.005 OR ABS(vat)>=10000000000000000)) OR (inclusiveVat IS NOT NULL AND (ABS(inclusiveVat-ROUND(inclusiveVat,2))>.005 OR ABS(inclusiveVat)>=10000000000000000));
 SELECT COUNT(*)+bad INTO bad FROM tblinvoice_details_suppliers WHERE ABS(qty-ROUND(qty,2))>.005 OR ABS(rate-ROUND(rate,2))>.005 OR ABS(gross-ROUND(gross,2))>.005 OR ABS(qty)>=10000000000000000 OR ABS(rate)>=10000000000000000 OR ABS(gross)>=10000000000000000;
 SELECT COUNT(*)+bad INTO bad FROM tblinvoice_payments_suppliers WHERE ABS(amount-ROUND(amount,2))>.005 OR ABS(amount)>=10000000000000000;
 SELECT COUNT(*)+bad INTO bad FROM tblproducts WHERE ABS(rate-ROUND(rate,2))>.005 OR ABS(rate)>=10000000000000000;
 IF bad>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='supplier money conversion refused: values are not exact cents or exceed DECIMAL(18,2)'; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_header_suppliers' AND COLUMN_NAME IN('exclusiveVat','vat','inclusiveVat') AND DATA_TYPE<>'decimal';
 IF n>0 THEN ALTER TABLE tblinvoice_header_suppliers MODIFY exclusiveVat DECIMAL(18,2) NULL,MODIFY vat DECIMAL(18,2) NULL,MODIFY inclusiveVat DECIMAL(18,2) NULL; SELECT 'header money converted' result; ELSE SELECT 'header money skipped' result; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_details_suppliers' AND COLUMN_NAME IN('qty','rate','gross') AND DATA_TYPE<>'decimal';
 IF n>0 THEN ALTER TABLE tblinvoice_details_suppliers MODIFY qty DECIMAL(18,2) NOT NULL,MODIFY rate DECIMAL(18,2) NOT NULL,MODIFY gross DECIMAL(18,2) NOT NULL; SELECT 'detail money converted' result; ELSE SELECT 'detail money skipped' result; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_payments_suppliers' AND COLUMN_NAME='amount' AND DATA_TYPE<>'decimal';
 IF n>0 THEN ALTER TABLE tblinvoice_payments_suppliers MODIFY amount DECIMAL(18,2) NOT NULL; SELECT 'payment money converted' result; ELSE SELECT 'payment money skipped' result; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblproducts' AND COLUMN_NAME='rate' AND DATA_TYPE<>'decimal';
 IF n>0 THEN ALTER TABLE tblproducts MODIFY rate DECIMAL(18,2) NOT NULL; SELECT 'product rate converted' result; ELSE SELECT 'product rate skipped' result; END IF;
 -- 6. retire products 1 and 2 (only while they still point at the reporting accounts 9 / 23)
 UPDATE tblproducts SET deleted=1 WHERE ID IN (1,2) AND accountId IN (9,23) AND deleted=0;
 SELECT CONCAT('products retired: ',ROW_COUNT()) result;
END$$
CALL sp_tmp_supplier_accounts_schema()$$
DROP PROCEDURE sp_tmp_supplier_accounts_schema$$

-- 7. balances: deleted payments / headers / suppliers never count
DROP FUNCTION IF EXISTS getinvoicebalance_supplier$$
CREATE FUNCTION getinvoicebalance_supplier(invoiceno INT) RETURNS DECIMAL(18,2) READS SQL DATA
BEGIN
 RETURN COALESCE((SELECT COALESCE(h.inclusiveVat,0)-COALESCE((SELECT SUM(p.amount) FROM tblinvoice_payments_suppliers p WHERE p.invoice_Id=h.ID AND p.Deleted=0),0)
                  FROM tblinvoice_header_suppliers h WHERE h.ID=invoiceno AND h.deleted=0),0);
END$$
DROP FUNCTION IF EXISTS getinvoicebalance_supplier_bydate$$
CREATE FUNCTION getinvoicebalance_supplier_bydate(invoiceno INT, pdate DATE) RETURNS DECIMAL(18,2) READS SQL DATA
BEGIN
 RETURN COALESCE((SELECT COALESCE(h.inclusiveVat,0)-COALESCE((SELECT SUM(p.amount) FROM tblinvoice_payments_suppliers p WHERE p.invoice_Id=h.ID AND p.Deleted=0 AND p.paymentDate<=pdate),0)
                  FROM tblinvoice_header_suppliers h WHERE h.ID=invoiceno AND h.deleted=0),0);
END$$
DROP FUNCTION IF EXISTS getinvoice_paid_supplier$$
CREATE FUNCTION getinvoice_paid_supplier(invoiceno INT) RETURNS DECIMAL(18,2) READS SQL DATA
BEGIN
 RETURN COALESCE((SELECT SUM(amount) FROM tblinvoice_payments_suppliers WHERE invoice_Id=invoiceno AND Deleted=0),0);
END$$
DROP FUNCTION IF EXISTS getpaymenttotalbysupplier$$
CREATE FUNCTION getpaymenttotalbysupplier(payno INT, suppid INT) RETURNS DECIMAL(18,2) READS SQL DATA
BEGIN
 RETURN COALESCE((SELECT SUM(p.amount) FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers s ON p.invoice_Id=s.ID
                  WHERE p.paymentNo=payno AND s.supplierId=suppid AND p.Deleted=0 AND s.deleted=0),0);
END$$
DROP FUNCTION IF EXISTS fn_get_supplier_balance$$
CREATE FUNCTION fn_get_supplier_balance(supplier INT) RETURNS DECIMAL(18,2) READS SQL DATA
BEGIN
 RETURN COALESCE((SELECT SUM(inclusiveVat) FROM tblinvoice_header_suppliers WHERE supplierId=supplier AND deleted=0),0)
      - COALESCE((SELECT SUM(p.amount) FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID
                  WHERE h.supplierId=supplier AND h.deleted=0 AND p.Deleted=0),0);
END$$

CREATE OR REPLACE VIEW vw_supplier_with_bals AS
 SELECT h.ID AS ID,h.supplierId AS supplierId,UCASE(s.supplierName) AS Supplier,h.invoiceNo AS invoiceNo,DATE_FORMAT(h.invoiceDate,'%d/%m/%y') AS invoiceDate,
        getinvoicebalance_supplier(h.ID) AS Balance,h.congregationId AS congregationId
 FROM tblinvoice_header_suppliers h JOIN tblsuppliers s ON h.supplierId=s.ID
 WHERE h.deleted=0 AND s.deleted=0
 HAVING getinvoicebalance_supplier(h.ID)>0 ORDER BY h.duedate$$
CREATE OR REPLACE VIEW vw_supplier_payments AS
 SELECT h.ID AS ID,h.supplierId AS supplierId,UCASE(s.supplierName) AS Supplier,h.invoiceNo AS invoiceNo,DATE_FORMAT(h.invoiceDate,'%d/%m/%y') AS invoiceDate,
        getinvoicebalance_supplier(h.ID) AS Balance,h.congregationId AS congregationId
 FROM tblinvoice_header_suppliers h JOIN tblsuppliers s ON h.supplierId=s.ID
 WHERE h.deleted=0 AND s.deleted=0
 HAVING getinvoicebalance_supplier(h.ID)>0 ORDER BY h.duedate$$
-- the payments list shows the payment NUMBER (the batch / voucher number), one row per paid invoice
CREATE OR REPLACE VIEW vw_supplierpayments2 AS
 SELECT p.ID AS ID,p.paymentNo AS paymentNo,h.supplierId AS supplierId,UCASE(s.supplierName) AS supplierName,p.paymentDate AS paymentDate,p.amount AS AmountPaid,h.congregationId AS congregationId
 FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID JOIN tblsuppliers s ON h.supplierId=s.ID
 WHERE p.Deleted=0 AND h.deleted=0 AND s.deleted=0
 ORDER BY p.paymentNo DESC$$
CREATE OR REPLACE VIEW vw_supplierpayments AS
 SELECT p.paymentNo AS paymentNo,h.supplierId AS supplierId,UCASE(s.supplierName) AS supplierName,p.paymentDate AS paymentDate,SUM(p.amount) AS AmountPaid,h.congregationId AS congregationId
 FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID JOIN tblsuppliers s ON h.supplierId=s.ID
 WHERE p.Deleted=0 AND h.deleted=0 AND s.deleted=0
 GROUP BY p.paymentNo,s.supplierName,p.paymentDate,h.congregationId ORDER BY p.paymentNo DESC$$

DROP PROCEDURE IF EXISTS spGetInvoices_suppliers$$
CREATE PROCEDURE spGetInvoices_suppliers(IN congid INT)
BEGIN
 SELECT h.ID AS ID,h.invoiceDate AS invoiceDate,h.invoiceNo AS invoiceNo,UCASE(s.supplierName) AS supplier,h.duedate AS duedate,
        h.inclusiveVat-(SELECT IFNULL(SUM(p.amount),0) FROM tblinvoice_payments_suppliers p WHERE p.invoice_Id=h.ID AND p.Deleted=0) AS balance,
        IF(h.status=0,'Unpaid',IF(h.status=1,'Partially Paid','Paid')) AS state,h.status AS status,h.postedBy AS postedBy
 FROM tblinvoice_header_suppliers h JOIN tblsuppliers s ON h.supplierId=s.ID
 WHERE h.fiscalYearId=getYearID() AND h.congregationId=congid AND h.deleted=0 AND h.invoiceNo IS NOT NULL
 ORDER BY h.status,h.ID DESC;
END$$
DROP PROCEDURE IF EXISTS sp_getinvoicepaymentdetails$$
CREATE PROCEDURE sp_getinvoicepaymentdetails(IN payno INT, IN suppid INT)
BEGIN
 SELECT h.invoiceNo,h.invoiceDate,h.duedate,h.inclusiveVat,getinvoicebalance_supplier_bydate(p.invoice_Id,p.paymentDate) AS Balance,p.amount
 FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID
 WHERE p.paymentNo=payno AND h.supplierId=suppid AND p.Deleted=0 AND h.deleted=0;
END$$
DROP PROCEDURE IF EXISTS sp_getsupplierpayments$$
CREATE PROCEDURE sp_getsupplierpayments(IN sid INT, IN sdate DATE, IN edate DATE, IN congid INT)
BEGIN
 SELECT DATE_FORMAT(p.paymentDate,'%d/%m/%y') AS PaymentDate,FORMAT(p.amount,2) AS Amount,UCASE(m.paymentMethod) AS PaymentMethod,UCASE(p.paymentReference) AS PayReference
 FROM tblinvoice_payments_suppliers p INNER JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID INNER JOIN tblpaymentmethods m ON p.paymentId=m.ID
 WHERE h.supplierId=sid AND h.congregationId=congid AND h.deleted=0 AND p.Deleted=0 AND p.paymentDate BETWEEN sdate AND edate
 ORDER BY p.paymentDate;
END$$
DROP PROCEDURE IF EXISTS sp_get_payments_all$$
CREATE PROCEDURE sp_get_payments_all(sdate DATE, edate DATE, IN congid INT)
BEGIN
 SELECT DATE_FORMAT(p.paymentDate,'%d-%m-%y') AS paymentDate,p.paymentNo,UCASE(s.supplierName) AS supplierName,h.invoiceNo,p.amount,UCASE(p.paymentReference) AS paymentReference
 FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID LEFT JOIN tblpaymentmethods m ON p.paymentId=m.ID JOIN tblsuppliers s ON h.supplierId=s.ID
 WHERE h.deleted=0 AND p.Deleted=0 AND h.congregationId=congid AND p.paymentDate BETWEEN sdate AND edate
 ORDER BY paymentDate;
END$$
DROP PROCEDURE IF EXISTS sp_get_payments_by_invoiceno$$
CREATE PROCEDURE sp_get_payments_by_invoiceno(IN invoiceno VARCHAR(50), IN congid INT)
BEGIN
 SELECT DATE_FORMAT(p.paymentDate,'%d-%m-%y') AS paymentDate,p.paymentNo,p.amount,UCASE(m.paymentMethod) AS payMethod,UCASE(p.paymentReference) AS paymentReference
 FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID LEFT JOIN tblpaymentmethods m ON p.paymentId=m.ID
 WHERE h.invoiceNo=invoiceno AND h.congregationId=congid AND h.deleted=0 AND p.Deleted=0
 ORDER BY paymentDate;
END$$
DROP PROCEDURE IF EXISTS sp_get_payments_by_supplier$$
CREATE PROCEDURE sp_get_payments_by_supplier(IN suppid INT, sdate DATE, edate DATE, IN congid INT)
BEGIN
 SELECT DATE_FORMAT(p.paymentDate,'%d-%m-%y') AS paymentDate,p.paymentNo,h.invoiceNo,p.amount,UCASE(m.paymentMethod) AS payMethod,UCASE(p.paymentReference) AS paymentReference
 FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id=h.ID LEFT JOIN tblpaymentmethods m ON p.paymentId=m.ID
 WHERE h.supplierId=suppid AND h.deleted=0 AND p.Deleted=0 AND h.congregationId=congid AND p.paymentDate BETWEEN sdate AND edate
 ORDER BY paymentDate;
END$$
DELIMITER ;

-- verification (dev 2026-10-23: columns present, money columns decimal, retired products, no legacy payable counts shown below)
SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='tblinvoice_header_suppliers' AND COLUMN_NAME IN('PayableAccountId','exclusiveVat','vat','inclusiveVat')) OR (TABLE_NAME='tblinvoice_details_suppliers' AND COLUMN_NAME IN('AccountId','qty','rate','gross')) OR (TABLE_NAME='tblinvoice_payments_suppliers' AND COLUMN_NAME IN('PaymentAccountId','amount')) OR (TABLE_NAME='tblproducts' AND COLUMN_NAME='rate')) ORDER BY TABLE_NAME,COLUMN_NAME;
SELECT COUNT(*) legacy_invoices_without_payable_account FROM tblinvoice_header_suppliers WHERE deleted=0 AND invoiceNo IS NOT NULL AND PayableAccountId IS NULL;
SELECT COUNT(*) legacy_opening_rows_without_payable_account FROM tblinvoice_header_suppliers WHERE deleted=0 AND invoiceNo IS NULL AND inclusiveVat<>0 AND PayableAccountId IS NULL;
SELECT ID,productName,accountId,deleted FROM tblproducts WHERE ID IN(1,2);

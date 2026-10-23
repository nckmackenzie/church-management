-- task 8.8: replace the known v4 function (8.7) with v5: v4 plus the customer account columns. Accepts only v4 (or its own v5, for a re-run), never v3 - run fn_checkreferencedaccount_v4.sql first. Keep in step with AccountChart::REFERENCES.
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_tmp_fn_checkref_v5$$
CREATE PROCEDURE sp_tmp_fn_checkref_v5()
BEGIN DECLARE n INT;SELECT COUNT(*) INTO n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='fn_checkreferencedaccount' AND (ROUTINE_DEFINITION LIKE '%v4_task_8_7%' OR ROUTINE_DEFINITION LIKE '%v5_task_8_8%');IF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='refused: fn_checkreferencedaccount is not the known v4/v5 definition';END IF;END$$
CALL sp_tmp_fn_checkref_v5()$$ DROP PROCEDURE sp_tmp_fn_checkref_v5$$
DROP FUNCTION IF EXISTS fn_checkreferencedaccount$$
CREATE FUNCTION fn_checkreferencedaccount(aid INT) RETURNS INT READS SQL DATA
BEGIN DECLARE v5_task_8_8 INT DEFAULT 0;RETURN
 (SELECT COUNT(*) FROM tblaccounttypes WHERE parentId=aid AND deleted=0)+(SELECT COUNT(*) FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.deleted=0 AND l.accountId=aid)
 +(SELECT COUNT(*) FROM tblbankpostings WHERE deleted=0 AND bankId=aid)+(SELECT COUNT(*) FROM tblbankpostings WHERE deleted=0 AND accountId=aid)
 +(SELECT COUNT(*) FROM tblexpenses WHERE accountId=aid)+(SELECT COUNT(*) FROM tblexpenses WHERE PaidFromAccountId=aid)+(SELECT COUNT(*) FROM tblexpenses WHERE bankId=aid)
 +(SELECT COUNT(*) FROM tblbanktransactions WHERE BankId=aid)+(SELECT COUNT(*) FROM tblbanktransactions WHERE TransferToId=aid)+(SELECT COUNT(*) FROM tblbanktransactions WHERE CashAccountId=aid)
 +(SELECT COUNT(*) FROM tblbanksubaccounts WHERE AccountId=aid)+(SELECT COUNT(*) FROM tblbanksubaccounts WHERE BankId=aid)+(SELECT COUNT(*) FROM tblcontributions_details WHERE contributionTypeId=aid)+(SELECT COUNT(*) FROM tblcontributions_details WHERE bankId=aid)
 +(SELECT COUNT(*) FROM tblmmf WHERE BankId=aid)+(SELECT COUNT(*) FROM tblpettycash WHERE BankId=aid)+(SELECT COUNT(*) FROM tblpettycash WHERE PettyCashAccountId=aid)
 +(SELECT COUNT(*) FROM tblchurchbudget_details WHERE accountId=aid)+(SELECT COUNT(*) FROM tblgroupbudget_details WHERE accountId=aid)+(SELECT COUNT(*) FROM tblproducts WHERE accountId=aid)
 +(SELECT COUNT(*) FROM tblpledges_header WHERE IncomeAccountId=aid)+(SELECT COUNT(*) FROM tblpledges_details WHERE PaymentAccountId=aid)+(SELECT COUNT(*) FROM tblpledges_details WHERE bankId=aid)
 +(SELECT COUNT(*) FROM tblinvoice_payments WHERE bankId=aid)+(SELECT COUNT(*) FROM tblinvoice_payments WHERE PaymentAccountId=aid)+(SELECT COUNT(*) FROM tblinvoice_header WHERE ReceivableAccountId=aid)+(SELECT COUNT(*) FROM tblinvoice_details WHERE AccountId=aid)+(SELECT COUNT(*) FROM tblinvoice_payments_suppliers WHERE bankId=aid)+(SELECT COUNT(*) FROM tblinvoice_payments_suppliers WHERE PaymentAccountId=aid)+(SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE PayableAccountId=aid)+(SELECT COUNT(*) FROM tblinvoice_details_suppliers WHERE AccountId=aid)+(SELECT COUNT(*) FROM tbldesposits WHERE BankId=aid)+(SELECT COUNT(*) FROM tblbankbalances WHERE BankId=aid)+(SELECT COUNT(*) FROM tblworkplan WHERE AccountId=aid);END$$
DELIMITER ;

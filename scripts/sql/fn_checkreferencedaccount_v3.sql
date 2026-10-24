-- task 8.6b: replace the known v2 function; keep in step with AccountChart::REFERENCES.
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_tmp_fn_checkref_v3$$
CREATE PROCEDURE sp_tmp_fn_checkref_v3()
BEGIN DECLARE n INT;SELECT COUNT(*) INTO n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='fn_checkreferencedaccount' AND (ROUTINE_DEFINITION LIKE '%v2_task_8_10%' OR ROUTINE_DEFINITION LIKE '%v3_task_8_6%');IF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='refused: fn_checkreferencedaccount is not the known v2/v3 definition';END IF;END$$
CALL sp_tmp_fn_checkref_v3()$$ DROP PROCEDURE sp_tmp_fn_checkref_v3$$
DROP FUNCTION IF EXISTS fn_checkreferencedaccount$$
CREATE FUNCTION fn_checkreferencedaccount(aid INT) RETURNS INT READS SQL DATA
BEGIN DECLARE v3_task_8_6 INT DEFAULT 0;RETURN
 (SELECT COUNT(*) FROM tblaccounttypes WHERE parentId=aid AND deleted=0)+(SELECT COUNT(*) FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.deleted=0 AND l.accountId=aid)
 +(SELECT COUNT(*) FROM tblbankpostings WHERE deleted=0 AND bankId=aid)+(SELECT COUNT(*) FROM tblbankpostings WHERE deleted=0 AND accountId=aid)
 +(SELECT COUNT(*) FROM tblexpenses WHERE accountId=aid)+(SELECT COUNT(*) FROM tblexpenses WHERE PaidFromAccountId=aid)+(SELECT COUNT(*) FROM tblexpenses WHERE bankId=aid)
 +(SELECT COUNT(*) FROM tblbanktransactions WHERE BankId=aid)+(SELECT COUNT(*) FROM tblbanktransactions WHERE TransferToId=aid)+(SELECT COUNT(*) FROM tblbanktransactions WHERE CashAccountId=aid)
 +(SELECT COUNT(*) FROM tblbanksubaccounts WHERE AccountId=aid)+(SELECT COUNT(*) FROM tblbanksubaccounts WHERE BankId=aid)+(SELECT COUNT(*) FROM tblcontributions_details WHERE contributionTypeId=aid)+(SELECT COUNT(*) FROM tblcontributions_details WHERE bankId=aid)
 +(SELECT COUNT(*) FROM tblmmf WHERE BankId=aid)+(SELECT COUNT(*) FROM tblpettycash WHERE BankId=aid)+(SELECT COUNT(*) FROM tblpettycash WHERE PettyCashAccountId=aid)
 +(SELECT COUNT(*) FROM tblchurchbudget_details WHERE accountId=aid)+(SELECT COUNT(*) FROM tblgroupbudget_details WHERE accountId=aid)+(SELECT COUNT(*) FROM tblproducts WHERE accountId=aid)
 +(SELECT COUNT(*) FROM tblpledges_header WHERE IncomeAccountId=aid)+(SELECT COUNT(*) FROM tblpledges_details WHERE PaymentAccountId=aid)+(SELECT COUNT(*) FROM tblpledges_details WHERE bankId=aid)
 +(SELECT COUNT(*) FROM tblinvoice_payments WHERE bankId=aid)+(SELECT COUNT(*) FROM tblinvoice_payments_suppliers WHERE bankId=aid)+(SELECT COUNT(*) FROM tbldesposits WHERE BankId=aid)+(SELECT COUNT(*) FROM tblbankbalances WHERE BankId=aid)+(SELECT COUNT(*) FROM tblworkplan WHERE AccountId=aid);END$$
DELIMITER ;

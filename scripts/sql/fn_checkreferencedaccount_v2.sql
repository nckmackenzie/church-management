-- fn_checkreferencedaccount_v2.sql  (task 8.10)  IDEMPOTENT, MariaDB 10.4 / 10.6.
-- Run on dev now and on PRODUCTION at cutover (after ledger_schema.sql, BEFORE the code deploy and BEFORE tblledger is renamed):
--   mysql -uroot cms < scripts/sql/fn_checkreferencedaccount_v2.sql      (phpMyAdmin: paste the whole file; it uses DELIMITER.)
-- fn_checkreferencedaccount(aid) = how many things still refer to the account (0 = nothing does). The old body matched tblledger by
-- account NAME (so it failed as soon as tblledger became tblledger_legacy, and never saw journal_lines). The new body has no tblledger
-- term. It counts, per account ID:
--   non-deleted child accounts; journal_lines of non-voided entries; non-deleted tblbankpostings (bankId and accountId);
--   every row of: tblexpenses (accountId, PaidFromAccountId, bankId), tblbanktransactions (BankId, TransferToId, CashAccountId),
--   tblbanksubaccounts (AccountId, BankId), tblcontributions_details (contributionTypeId, bankId), tblmmf.BankId,
--   tblpettycash (BankId, PettyCashAccountId), tblchurchbudget_details, tblgroupbudget_details, tblproducts.accountId,
--   tblpledges_header.category, tblpledges_details.bankId, tblinvoice_payments.bankId, tblinvoice_payments_suppliers.bankId,
--   tbldesposits.BankId, tblbankbalances.BankId, tblworkplan.AccountId.
-- The application no longer calls it (AccountChart::deleteReasons() does, with reasons); the same list is AccountChart::REFERENCES and
-- scripts/test_8_10_accounts.php checks the two agree. Callers found: Account::delete() only (code); no routine, view or trigger uses it.
-- Stops unless the current definition is the known one (md5 3d156c29...) or already the new one (contains 'v2_task_8_10').
-- Take a backup first:  mysqldump -u root -p --routines --triggers cms > before_8_10.sql
-- ROLLBACK: restore the old definition from that backup (it is also in scripts/sql/expense_paidfrom_schema.sql, step 5), or
--   run scripts/sql/expense_paidfrom_schema.sql step 5's CREATE FUNCTION. The old body needs tblledger to exist.

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_tmp_fn_checkref_v2$$
CREATE PROCEDURE sp_tmp_fn_checkref_v2()
BEGIN
    DECLARE fnmd5 VARCHAR(32);
    DECLARE n INT;
    SELECT md5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')), (ROUTINE_DEFINITION LIKE '%v2_task_8_10%')
      INTO fnmd5, n
      FROM information_schema.ROUTINES
     WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'fn_checkreferencedaccount';
    IF fnmd5 IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused: fn_checkreferencedaccount does not exist';
    END IF;
    IF n = 0 AND fnmd5 <> '3d156c29cbbc1468b103a202b0659859' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused: fn_checkreferencedaccount differs from the definition this script was written against (md5 3d156c29...); review it before replacing';
    END IF;
    SELECT IF(n = 0, 'fn_checkreferencedaccount will be replaced', 'fn_checkreferencedaccount already v2 (recreated unchanged)') AS result;
END$$

CALL sp_tmp_fn_checkref_v2()$$
DROP PROCEDURE sp_tmp_fn_checkref_v2$$

SET @old_sql_mode := @@sql_mode$$
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'$$
DROP FUNCTION IF EXISTS fn_checkreferencedaccount$$
CREATE FUNCTION fn_checkreferencedaccount(`aid` INT) RETURNS int(11)
    READS SQL DATA
BEGIN
    -- v2 (task 8.10): no tblledger term; keep in step with AccountChart::REFERENCES
    DECLARE v2_task_8_10 INT DEFAULT 0;   -- marks the task 8.10 definition (comments are not stored)
    RETURN
          (SELECT COUNT(*) FROM tblaccounttypes WHERE parentId = aid AND deleted = 0)
        + (SELECT COUNT(*) FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId WHERE e.deleted = 0 AND l.accountId = aid)
        + (SELECT COUNT(*) FROM tblbankpostings WHERE deleted = 0 AND bankId = aid)
        + (SELECT COUNT(*) FROM tblbankpostings WHERE deleted = 0 AND accountId = aid)
        + (SELECT COUNT(*) FROM tblexpenses WHERE accountId = aid)
        + (SELECT COUNT(*) FROM tblexpenses WHERE PaidFromAccountId = aid)
        + (SELECT COUNT(*) FROM tblexpenses WHERE bankId = aid)
        + (SELECT COUNT(*) FROM tblbanktransactions WHERE BankId = aid)
        + (SELECT COUNT(*) FROM tblbanktransactions WHERE TransferToId = aid)
        + (SELECT COUNT(*) FROM tblbanktransactions WHERE CashAccountId = aid)
        + (SELECT COUNT(*) FROM tblbanksubaccounts WHERE AccountId = aid)
        + (SELECT COUNT(*) FROM tblbanksubaccounts WHERE BankId = aid)
        + (SELECT COUNT(*) FROM tblcontributions_details WHERE contributionTypeId = aid)
        + (SELECT COUNT(*) FROM tblcontributions_details WHERE bankId = aid)
        + (SELECT COUNT(*) FROM tblmmf WHERE BankId = aid)
        + (SELECT COUNT(*) FROM tblpettycash WHERE BankId = aid)
        + (SELECT COUNT(*) FROM tblpettycash WHERE PettyCashAccountId = aid)
        + (SELECT COUNT(*) FROM tblchurchbudget_details WHERE accountId = aid)
        + (SELECT COUNT(*) FROM tblgroupbudget_details WHERE accountId = aid)
        + (SELECT COUNT(*) FROM tblproducts WHERE accountId = aid)
        + (SELECT COUNT(*) FROM tblpledges_header WHERE category = aid)
        + (SELECT COUNT(*) FROM tblpledges_details WHERE bankId = aid)
        + (SELECT COUNT(*) FROM tblinvoice_payments WHERE bankId = aid)
        + (SELECT COUNT(*) FROM tblinvoice_payments_suppliers WHERE bankId = aid)
        + (SELECT COUNT(*) FROM tbldesposits WHERE BankId = aid)
        + (SELECT COUNT(*) FROM tblbankbalances WHERE BankId = aid)
        + (SELECT COUNT(*) FROM tblworkplan WHERE AccountId = aid);
END$$
SET sql_mode = @old_sql_mode$$

DELIMITER ;

SELECT 'fn_checkreferencedaccount_v2.sql finished' AS result;

-- expense_paidfrom_schema.sql  (task 8.5c)  IDEMPOTENT, MariaDB 10.4 / 10.6.
-- Run on dev now and on PRODUCTION at cutover:   mysql -uroot cms < scripts/sql/expense_paidfrom_schema.sql
-- (phpMyAdmin: paste the whole file; it uses DELIMITER). Every step checks information_schema first and reports
-- 'applied' or 'skipped'. A step that cannot be done safely stops the script with an error (nothing after it runs):
--   * step 2 stops if any tblexpenses.amount does not survive FLOAT -> DECIMAL(18,2) exactly;
--   * step 5 stops if fn_checkreferencedaccount is neither the known old definition nor already updated.
-- Take a backup first:  mysqldump -u root -p --routines --triggers cms > before_8_5c.sql
--
--  1. tblexpenses.PaidFromAccountId INT(11) NULL (after bankId) + index + FK to tblaccounttypes(ID)
--  2. tblexpenses.amount FLOAT -> DECIMAL(18,2) NOT NULL
--  3. tblexpenses.status default 0 (was 1)
--  4. index tblpettycash(ExpenseId); index tblbankpostings(transactionType, transactionId)
--  5. fn_checkreferencedaccount also counts tblexpenses.PaidFromAccountId

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_tmp_expense_paidfrom_schema$$
CREATE PROCEDURE sp_tmp_expense_paidfrom_schema()
BEGIN
    DECLARE n INT;
    DECLARE bad INT;
    DECLARE fnmd5 VARCHAR(32);

    -- ---- step 1: column, index, foreign key
    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblexpenses' AND COLUMN_NAME = 'PaidFromAccountId';
    IF n = 0 THEN
        ALTER TABLE tblexpenses ADD COLUMN PaidFromAccountId INT(11) NULL DEFAULT NULL AFTER bankId;
        SELECT 'step 1a: column PaidFromAccountId applied' AS result;
    ELSE
        SELECT 'step 1a: column PaidFromAccountId skipped (exists)' AS result;
    END IF;

    SELECT COUNT(*) INTO n FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblexpenses' AND SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'PaidFromAccountId';
    IF n = 0 THEN
        ALTER TABLE tblexpenses ADD KEY IDX_Expense_PaidFromAccountId (PaidFromAccountId);
        SELECT 'step 1b: index IDX_Expense_PaidFromAccountId applied' AS result;
    ELSE
        SELECT 'step 1b: index skipped (an index on PaidFromAccountId exists)' AS result;
    END IF;

    SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tblexpenses'
       AND CONSTRAINT_NAME = 'FK_Expense_PaidFromAccount' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
    IF n = 0 THEN
        ALTER TABLE tblexpenses ADD CONSTRAINT FK_Expense_PaidFromAccount
              FOREIGN KEY (PaidFromAccountId) REFERENCES tblaccounttypes (ID);
        SELECT 'step 1c: foreign key FK_Expense_PaidFromAccount applied' AS result;
    ELSE
        SELECT 'step 1c: foreign key skipped (exists)' AS result;
    END IF;

    -- ---- step 2: amount FLOAT -> DECIMAL(18,2)
    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblexpenses' AND COLUMN_NAME = 'amount'
       AND DATA_TYPE IN ('float', 'double');
    IF n = 1 THEN
        -- read-only check: amounts that are not exact cents (expected 0 rows)
        SELECT COUNT(*) INTO bad FROM tblexpenses
         WHERE ABS(amount - ROUND(amount, 2)) > 0.001 OR amount IS NULL OR ABS(amount) >= 1000000000;
        IF bad > 0 THEN
            SELECT ID, amount AS stored_amount, ROUND(amount, 2) AS rounded
              FROM tblexpenses
             WHERE ABS(amount - ROUND(amount, 2)) > 0.001 OR amount IS NULL OR ABS(amount) >= 1000000000 ORDER BY ID;
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'step 2 refused: tblexpenses.amount has values that are not exact cents (rows listed above)';
        END IF;
        SELECT SUM(amount), COUNT(*) INTO @sum_before, @n_before FROM tblexpenses;
        ALTER TABLE tblexpenses MODIFY amount DECIMAL(18,2) NOT NULL;
        SELECT SUM(amount), COUNT(*) INTO @sum_after, @n_after FROM tblexpenses;
        SELECT 'step 2: amount FLOAT -> DECIMAL(18,2) applied' AS result, @n_before AS rows_before, @n_after AS rows_after,
               ROUND(@sum_before, 2) AS sum_before, @sum_after AS sum_after;
    ELSE
        SELECT 'step 2: amount skipped (already DECIMAL)' AS result;
    END IF;

    -- ---- step 3: status default 0
    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblexpenses' AND COLUMN_NAME = 'status' AND COLUMN_DEFAULT = '0';
    IF n = 0 THEN
        ALTER TABLE tblexpenses ALTER COLUMN `status` SET DEFAULT 0;
        SELECT 'step 3: status default 0 applied' AS result;
    ELSE
        SELECT 'step 3: status default skipped (already 0)' AS result;
    END IF;

    -- ---- step 4: indexes
    SELECT COUNT(*) INTO n FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblpettycash' AND SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'ExpenseId';
    IF n = 0 THEN
        ALTER TABLE tblpettycash ADD KEY IDX_PettyCash_ExpenseId (ExpenseId);
        SELECT 'step 4a: index tblpettycash(ExpenseId) applied' AS result;
    ELSE
        SELECT 'step 4a: skipped (an index starting with ExpenseId exists)' AS result;
    END IF;

    SELECT COUNT(*) INTO n FROM information_schema.STATISTICS s1
      JOIN information_schema.STATISTICS s2
        ON s2.TABLE_SCHEMA = s1.TABLE_SCHEMA AND s2.TABLE_NAME = s1.TABLE_NAME AND s2.INDEX_NAME = s1.INDEX_NAME
       AND s2.SEQ_IN_INDEX = 2 AND s2.COLUMN_NAME = 'transactionId'
     WHERE s1.TABLE_SCHEMA = DATABASE() AND s1.TABLE_NAME = 'tblbankpostings' AND s1.SEQ_IN_INDEX = 1 AND s1.COLUMN_NAME = 'transactionType';
    IF n = 0 THEN
        ALTER TABLE tblbankpostings ADD KEY IDX_BankPostings_Transaction (transactionType, transactionId);
        SELECT 'step 4b: index tblbankpostings(transactionType, transactionId) applied' AS result;
    ELSE
        SELECT 'step 4b: skipped (an equivalent index exists)' AS result;
    END IF;

    -- ---- step 5 guard: only the known old definition (or the already updated one) may be replaced
    SELECT md5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')), (ROUTINE_DEFINITION LIKE '%PaidFromAccountId%')
      INTO fnmd5, n
      FROM information_schema.ROUTINES
     WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'fn_checkreferencedaccount';
    IF fnmd5 IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'step 5 refused: fn_checkreferencedaccount does not exist';
    END IF;
    IF n = 0 AND fnmd5 <> '572b9b7be0fc5ef1fb4d6cafb441212b' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'step 5 refused: fn_checkreferencedaccount differs from the definition this script was written against (dev md5 572b9b7b...); review it before replacing';
    END IF;
    SELECT IF(n = 0, 'step 5: fn_checkreferencedaccount will be replaced',
                     'step 5: fn_checkreferencedaccount already counts PaidFromAccountId (recreated unchanged)') AS result;
END$$

CALL sp_tmp_expense_paidfrom_schema()$$
DROP PROCEDURE sp_tmp_expense_paidfrom_schema$$

-- ---- step 5: the function = its current definition + the PaidFromAccountId count (three added lines, nothing else changed)
SET @old_sql_mode := @@sql_mode$$
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'$$
DROP FUNCTION IF EXISTS fn_checkreferencedaccount$$
CREATE FUNCTION fn_checkreferencedaccount(`aid` INT) RETURNS int(11)
    DETERMINISTIC
BEGIN
	DECLARE accountname VARCHAR(150);
    DECLARE churchbudget INT;
    DECLARE bankpostings INT;
    DECLARE contributions INT;
    DECLARE expenses INT;
    DECLARE expensespaidfrom INT;
    DECLARE groupbudget INT;
    DECLARE ledger INT;
    DECLARE ledgerparent INT;
    DECLARE products INT;
    DECLARE pledges INT;
    SET accountname = (SELECT TRIM(LOWER(accountType)) AS AccountName FROM tblaccounttypes WHERE (ID = aid));
    SET churchbudget = (SELECT COUNT(*) FROM tblchurchbudget_details WHERE accountId = aid);
    SET bankpostings = (SELECT COUNT(*) FROM tblbankpostings  WHERE bankId = aid);
    SET contributions = (SELECT COUNT(*) FROM tblcontributions_details WHERE contributionTypeId = aid);
    SET expenses = (SELECT COUNT(*) FROM tblexpenses WHERE accountId = aid);
    SET expensespaidfrom = (SELECT COUNT(*) FROM tblexpenses WHERE PaidFromAccountId = aid);
    SET groupbudget = (SELECT COUNT(*) FROM tblgroupbudget_details  WHERE (accountId = aid));
    SET ledger = (SELECT COUNT(*) FROM tblledger WHERE TRIM(LOWER(account)) = accountname);
    SET ledgerparent = (SELECT COUNT(*) FROM tblledger WHERE TRIM(LOWER(parentaccount)) = accountname);
    SET products = (SELECT COUNT(*) FROM tblproducts WHERE accountId = aid);
    SET pledges = (SELECT COUNT(*) FROM tblpledges_header WHERE category = aid);
    RETURN churchbudget + bankpostings + contributions + expenses + expensespaidfrom + groupbudget + ledger + ledgerparent + products + pledges ;
END
$$

SET sql_mode = @old_sql_mode$$

DELIMITER ;

SELECT 'expense_paidfrom_schema.sql finished' AS result;

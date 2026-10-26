-- tblbankpostings money columns FLOAT -> DECIMAL(18,2)  (task 9.5, T12, runbook step 4.19)
--
-- Run:   mysql -u <user> -p <database> < scripts/sql/bankpostings_decimal.sql
--
--  1. Idempotent: when debit and credit are already DECIMAL(18,2) it prints "already DECIMAL" and changes nothing.
--  2. Guard: stops (SIGNAL, non-zero exit) and lists the rows when any debit / credit is NULL, outside DECIMAL(18,2), or farther
--     than 0.001 from a whole cent. A FLOAT keeps about 7 significant digits, so an amount over about 65,000 can already have
--     lost its cents; such a row cannot be converted unattended. NOTHING is changed when the guard stops the script.
--  3. Prints the row count and the totals before, converts, prints them after, and stops loudly if they differ by a cent.
--  4. No data is corrected and no bank row is created or removed.
--
-- The table name defaults to tblbankpostings; the test suite points it at a copy:  SET @bp_table = 'zz_copy';  before sourcing.

SET @bp_table = IFNULL(@bp_table, 'tblbankpostings');

DROP PROCEDURE IF EXISTS zz_bankpostings_decimal;
DELIMITER $$
CREATE PROCEDURE zz_bankpostings_decimal(IN t VARCHAR(64))
BEGIN
    DECLARE v_dt VARCHAR(64);
    DECLARE v_ct VARCHAR(64);

    IF t NOT REGEXP '^[A-Za-z0-9_]+$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bankpostings_decimal: invalid table name';
    END IF;

    SELECT COLUMN_TYPE INTO v_dt FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND COLUMN_NAME = 'debit';
    SELECT COLUMN_TYPE INTO v_ct FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND COLUMN_NAME = 'credit';

    IF v_dt IS NULL OR v_ct IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bankpostings_decimal: table or its debit / credit columns not found';
    END IF;

    IF v_dt = 'decimal(18,2)' AND v_ct = 'decimal(18,2)' THEN
        SELECT 'already DECIMAL(18,2): nothing to do' AS result, t AS tableName, v_dt AS debitType, v_ct AS creditType;
    ELSE
        -- guard: rows that cannot be converted exactly
        SET @bp_sql = CONCAT('SELECT COUNT(*) INTO @bp_bad FROM `', t, '` WHERE debit IS NULL OR credit IS NULL',
                             ' OR ABS(debit) >= 1e16 OR ABS(credit) >= 1e16',
                             ' OR ABS(debit - ROUND(debit, 2)) > 0.001 OR ABS(credit - ROUND(credit, 2)) > 0.001');
        PREPARE s FROM @bp_sql; EXECUTE s; DEALLOCATE PREPARE s;
        IF @bp_bad > 0 THEN
            SET @bp_sql = CONCAT('SELECT ID, bankId, transactionDate, debit, credit, cleared, deleted FROM `', t, '` WHERE debit IS NULL OR credit IS NULL',
                                 ' OR ABS(debit) >= 1e16 OR ABS(credit) >= 1e16',
                                 ' OR ABS(debit - ROUND(debit, 2)) > 0.001 OR ABS(credit - ROUND(credit, 2)) > 0.001 ORDER BY ID LIMIT 100');
            PREPARE s FROM @bp_sql; EXECUTE s; DEALLOCATE PREPARE s;
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bankpostings_decimal: rows listed above are not exact cents (or NULL / out of range); nothing was changed';
        END IF;

        -- before (the sum of the cent-rounded values is what the conversion produces)
        SET @bp_sql = CONCAT('SELECT COUNT(*), IFNULL(ROUND(SUM(ROUND(debit, 2)), 2), 0), IFNULL(ROUND(SUM(ROUND(credit, 2)), 2), 0),',
                             ' IFNULL(MAX(ABS(debit - ROUND(debit, 2))), 0), IFNULL(MAX(ABS(credit - ROUND(credit, 2))), 0)',
                             ' INTO @bp_rows, @bp_dr, @bp_cr, @bp_maxd, @bp_maxc FROM `', t, '`');
        PREPARE s FROM @bp_sql; EXECUTE s; DEALLOCATE PREPARE s;
        SELECT 'before' AS stage, t AS tableName, v_dt AS debitType, v_ct AS creditType, @bp_rows AS `rows`,
               @bp_dr AS totalDebit, @bp_cr AS totalCredit, @bp_maxd AS maxDebitOffCent, @bp_maxc AS maxCreditOffCent;

        SET @bp_sql = CONCAT('ALTER TABLE `', t, '` MODIFY debit DECIMAL(18,2) NOT NULL DEFAULT 0, MODIFY credit DECIMAL(18,2) NOT NULL DEFAULT 0');
        PREPARE s FROM @bp_sql; EXECUTE s; DEALLOCATE PREPARE s;

        SET @bp_sql = CONCAT('SELECT COUNT(*), IFNULL(SUM(debit), 0), IFNULL(SUM(credit), 0) INTO @bp_rows2, @bp_dr2, @bp_cr2 FROM `', t, '`');
        PREPARE s FROM @bp_sql; EXECUTE s; DEALLOCATE PREPARE s;
        SELECT 'after' AS stage, t AS tableName, 'decimal(18,2)' AS debitType, 'decimal(18,2)' AS creditType, @bp_rows2 AS `rows`,
               @bp_dr2 AS totalDebit, @bp_cr2 AS totalCredit;

        IF @bp_rows2 <> @bp_rows OR ABS(@bp_dr2 - @bp_dr) >= 0.005 OR ABS(@bp_cr2 - @bp_cr) >= 0.005 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bankpostings_decimal: totals differ after the conversion; restore the backup or roll back (runbook 4.19)';
        END IF;
        SELECT 'converted: totals before and after are equal to the cent' AS result;
    END IF;
END$$
DELIMITER ;

CALL zz_bankpostings_decimal(@bp_table);
DROP PROCEDURE IF EXISTS zz_bankpostings_decimal;

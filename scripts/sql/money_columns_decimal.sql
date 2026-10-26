-- Remaining FLOAT / DOUBLE money columns -> DECIMAL(18,2)  (task 9.8 part B, runbook step 4.20)
--
-- Run:   mysql -u <user> -p <database> < scripts/sql/money_columns_decimal.sql
--
-- Columns converted (every money column of the schema that was still FLOAT / DOUBLE on 2026-10-26):
--   tblcontributions_details.amount     FLOAT   NOT NULL   receipt lines
--   tblchurchbudget_details.amount      FLOAT   NOT NULL   church budget lines
--   tblgroupbudget_details.amount       FLOAT   NOT NULL   group budget lines
--   tblserviceinfo.envelopePledge       DOUBLE  NULL       service collections
--   tblserviceinfo.ordinary             DOUBLE  NULL
--   tblserviceinfo.special              DOUBLE  NULL
-- Left as they are (not money): tblvats.rate FLOAT (a percentage, 16), tbldesposits.Amount INT (retired table, no code reads it).
--
--  1. Idempotent: a column that is already DECIMAL(18,2) is skipped ("already DECIMAL").
--  2. Guard (all columns are checked first; nothing is changed when any row is listed):
--       FLOAT columns   a row is listed when the value is NULL (NOT NULL columns), outside DECIMAL(18,2), farther than 0.004 from a
--                       whole cent (the noise of a FLOAT below 131,072 is at most 0.0039), or 131,072 and above with a fractional
--                       part (a FLOAT there has a spacing of 0.0156 or more, so its cents were already lost when it was saved).
--       DOUBLE columns  listed when farther than 0.001 from a whole cent or out of range.
--     The script stops (SIGNAL, non-zero exit) and lists up to 100 rows per column. The owner decides what to do with them (the cents
--     lost in a FLOAT cannot be recovered by a script). To convert anyway after reviewing the list, run  SET @mc_accept = 1;  first:
--     the value is then converted to the nearest cent of what the column holds, and the listed rows are the only ones that can differ
--     from what was typed.
--  3. For every column it prints the row count and the total before and after and stops loudly if they differ by a cent.
--  4. No data is corrected and no row is created or removed.
--
-- Tests point it at copies of the four tables with  SET @mc_suffix = '_zz';  (table names get the suffix).
-- Rollback: ALTER TABLE ... MODIFY <column> FLOAT NOT NULL  (DOUBLE NULL for tblserviceinfo) - the old definitions; amounts above
--   about 131,000 can lose cents again, which is why the conversion exists - or restore the cutover backup (0.1).

SET @mc_suffix = IFNULL(@mc_suffix, '');
SET @mc_accept = IFNULL(@mc_accept, 0);

DROP PROCEDURE IF EXISTS zz_money_columns_decimal;
DELIMITER $$
CREATE PROCEDURE zz_money_columns_decimal(IN sfx VARCHAR(32), IN accept INT)
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_t VARCHAR(80); DECLARE v_c VARCHAR(64); DECLARE v_k VARCHAR(10); DECLARE v_n VARCHAR(3);
    DECLARE v_type VARCHAR(64); DECLARE v_tab VARCHAR(100); DECLARE v_cond VARCHAR(600);
    DECLARE v_bad INT; DECLARE v_total_bad INT DEFAULT 0; DECLARE v_pending INT DEFAULT 0;
    DECLARE cur1 CURSOR FOR SELECT tbl, col, kind, nl FROM zz_mc_targets ORDER BY tbl, col;
    DECLARE cur2 CURSOR FOR SELECT tbl, col, kind, nl FROM zz_mc_targets ORDER BY tbl, col;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    IF sfx NOT REGEXP '^[A-Za-z0-9_]*$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'money_columns_decimal: invalid table suffix';
    END IF;

    DROP TEMPORARY TABLE IF EXISTS zz_mc_targets;
    CREATE TEMPORARY TABLE zz_mc_targets (tbl VARCHAR(80) NOT NULL, col VARCHAR(64) NOT NULL, kind VARCHAR(10) NOT NULL, nl VARCHAR(3) NOT NULL);
    INSERT INTO zz_mc_targets VALUES
        (CONCAT('tblcontributions_details', sfx), 'amount',        'float',  'NO'),
        (CONCAT('tblchurchbudget_details', sfx),  'amount',        'float',  'NO'),
        (CONCAT('tblgroupbudget_details', sfx),   'amount',        'float',  'NO'),
        (CONCAT('tblserviceinfo', sfx),           'envelopePledge', 'double', 'YES'),
        (CONCAT('tblserviceinfo', sfx),           'ordinary',       'double', 'YES'),
        (CONCAT('tblserviceinfo', sfx),           'special',        'double', 'YES');

    -- phase 1: every column exists with the expected type; list the rows that cannot be converted exactly
    OPEN cur1;
    loop1: LOOP
        FETCH cur1 INTO v_t, v_c, v_k, v_n;
        IF done = 1 THEN LEAVE loop1; END IF;
        SET v_type = NULL;
        SELECT COLUMN_TYPE INTO v_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = v_t AND COLUMN_NAME = v_c;
        IF v_type IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'money_columns_decimal: a table or column was not found (see the list at the top of the script)';
        END IF;
        IF v_type = 'decimal(18,2)' THEN
            SELECT 'already DECIMAL(18,2): nothing to do' AS result, v_t AS tableName, v_c AS columnName;
        ELSEIF v_type NOT IN ('float', 'double') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'money_columns_decimal: a column has an unexpected type (neither FLOAT, DOUBLE nor DECIMAL(18,2))';
        ELSE
            SET v_pending = v_pending + 1;
            SET v_tab = CONCAT('`', v_t, '`');
            IF v_k = 'float' THEN
                SET v_cond = CONCAT('(`', v_c, '` IS NOT NULL AND (ABS(`', v_c, '`) >= 1e16 OR ABS(`', v_c, '` - ROUND(`', v_c, '`, 2)) > 0.004 OR (ABS(`', v_c, '`) >= 131072 AND `', v_c, '` <> FLOOR(`', v_c, '`))))');
            ELSE
                SET v_cond = CONCAT('(`', v_c, '` IS NOT NULL AND (ABS(`', v_c, '`) >= 1e16 OR ABS(`', v_c, '` - ROUND(`', v_c, '`, 2)) > 0.001))');
            END IF;
            IF v_n = 'NO' THEN SET v_cond = CONCAT('(`', v_c, '` IS NULL OR ', v_cond, ')'); END IF;
            SET @mc_sql = CONCAT('SELECT COUNT(*) INTO @mc_bad FROM ', v_tab, ' WHERE ', v_cond);
            PREPARE s FROM @mc_sql; EXECUTE s; DEALLOCATE PREPARE s;
            SET v_bad = @mc_bad;
            IF v_bad > 0 THEN
                SET v_total_bad = v_total_bad + v_bad;
                SET @mc_sql = CONCAT('SELECT ''', v_t, ''' AS tableName, ''', v_c, ''' AS columnName, ID, `', v_c, '` AS storedValue, ROUND(`', v_c, '`, 2) AS nearestCent FROM ', v_tab, ' WHERE ', v_cond, ' ORDER BY ID LIMIT 100');
                PREPARE s FROM @mc_sql; EXECUTE s; DEALLOCATE PREPARE s;
            END IF;
        END IF;
    END LOOP;
    CLOSE cur1;

    IF v_total_bad > 0 AND accept <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'money_columns_decimal: rows listed above are not exact cents (or NULL / out of range); nothing was changed. Review them, then SET @mc_accept = 1 to convert anyway';
    END IF;

    -- phase 2: convert column by column, with the counts and totals before and after
    SET done = 0;
    OPEN cur2;
    loop2: LOOP
        FETCH cur2 INTO v_t, v_c, v_k, v_n;
        IF done = 1 THEN LEAVE loop2; END IF;
        SET v_type = NULL;
        SELECT COLUMN_TYPE INTO v_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = v_t AND COLUMN_NAME = v_c;
        IF v_type IN ('float', 'double') THEN
            SET v_tab = CONCAT('`', v_t, '`');
            SET @mc_sql = CONCAT('SELECT COUNT(*), COUNT(`', v_c, '`), IFNULL(ROUND(SUM(ROUND(`', v_c, '`, 2)), 2), 0), IFNULL(MAX(ABS(`', v_c, '` - ROUND(`', v_c, '`, 2))), 0) INTO @mc_rows, @mc_nn, @mc_sum, @mc_off FROM ', v_tab);
            PREPARE s FROM @mc_sql; EXECUTE s; DEALLOCATE PREPARE s;
            SELECT 'before' AS stage, v_t AS tableName, v_c AS columnName, v_type AS columnType, @mc_rows AS `rows`, @mc_nn AS nonNull, @mc_sum AS total, @mc_off AS maxOffCent;
            SET @mc_sql = CONCAT('ALTER TABLE ', v_tab, ' MODIFY `', v_c, '` DECIMAL(18,2) ', IF(v_n = 'NO', 'NOT NULL', 'NULL'));
            PREPARE s FROM @mc_sql; EXECUTE s; DEALLOCATE PREPARE s;
            SET @mc_sql = CONCAT('SELECT COUNT(*), COUNT(`', v_c, '`), IFNULL(SUM(`', v_c, '`), 0) INTO @mc_rows2, @mc_nn2, @mc_sum2 FROM ', v_tab);
            PREPARE s FROM @mc_sql; EXECUTE s; DEALLOCATE PREPARE s;
            SELECT 'after' AS stage, v_t AS tableName, v_c AS columnName, 'decimal(18,2)' AS columnType, @mc_rows2 AS `rows`, @mc_nn2 AS nonNull, @mc_sum2 AS total;
            IF @mc_rows2 <> @mc_rows OR @mc_nn2 <> @mc_nn OR ABS(@mc_sum2 - @mc_sum) >= 0.005 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'money_columns_decimal: totals differ after a conversion; restore the backup or roll back (runbook 4.20)';
            END IF;
        END IF;
    END LOOP;
    CLOSE cur2;
    DROP TEMPORARY TABLE IF EXISTS zz_mc_targets;
    SELECT IF(v_pending = 0, 'nothing to convert: every money column is already DECIMAL(18,2)', 'converted: row counts and totals before and after are equal to the cent') AS result;
END$$
DELIMITER ;

CALL zz_money_columns_decimal(@mc_suffix, @mc_accept);
DROP PROCEDURE IF EXISTS zz_money_columns_decimal;

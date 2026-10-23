-- expense_id_autoincrement.sql  (task 8.5d)  IDEMPOTENT, MariaDB 10.4 / 10.6.
-- Run on dev now and on PRODUCTION at cutover (after expense_paidfrom_schema.sql):
--     mysql -uroot cms < scripts/sql/expense_id_autoincrement.sql
-- (phpMyAdmin: paste the whole file; it uses DELIMITER.)
--
-- tblexpenses.ID was a plain INT primary key: the old code computed the next ID itself (getLastId() = COUNT + 1),
-- which collides as soon as an ID has been skipped. The migrated Expense model reads the new ID with lastInsertId(),
-- so the column must be AUTO_INCREMENT. The counter starts at MAX(ID) + 1 automatically; no existing row changes.
-- Nothing references tblexpenses.ID with a foreign key (checked on dev).
-- Voucher numbers need no schema change: receipt_number_seq.kind is VARCHAR(10) and takes 'voucher'.
-- Take a backup first:  mysqldump -u root -p --routines --triggers cms > before_8_5d.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_tmp_expense_id_autoincrement$$
CREATE PROCEDURE sp_tmp_expense_id_autoincrement()
BEGIN
    DECLARE n INT;

    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblexpenses' AND COLUMN_NAME = 'ID'
       AND EXTRA LIKE '%auto_increment%';
    IF n = 0 THEN
        SELECT COUNT(*) INTO n FROM information_schema.KEY_COLUMN_USAGE
         WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'tblexpenses';
        IF n > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused: a foreign key references tblexpenses; review it before changing ID';
        END IF;
        ALTER TABLE tblexpenses MODIFY ID INT(11) NOT NULL AUTO_INCREMENT;
        SELECT 'tblexpenses.ID AUTO_INCREMENT applied' AS result;
    ELSE
        SELECT 'tblexpenses.ID AUTO_INCREMENT skipped (already set)' AS result;
    END IF;
END$$

CALL sp_tmp_expense_id_autoincrement()$$
DROP PROCEDURE sp_tmp_expense_id_autoincrement$$

DELIMITER ;

SELECT 'expense_id_autoincrement.sql finished' AS result;

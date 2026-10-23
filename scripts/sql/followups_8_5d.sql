-- followups_8_5d.sql  (task 8.5d follow-ups)  IDEMPOTENT, MariaDB 10.4 / 10.6.
-- Run on dev now and on PRODUCTION at cutover:   mysql -uroot cms < scripts/sql/followups_8_5d.sql
-- (phpMyAdmin: paste the whole file; it uses DELIMITER.)
--  1. tblpledges_header.ID AUTO_INCREMENT (Pledge::create() used getLastId() = last ID + 1; it now uses lastInsertId()).
--     Refuses if a foreign key references the table. The counter starts at MAX(ID) + 1.
--  2. getrequisitionbalance(): the two DECLAREs were DECIMAL (= DECIMAL(10,0)), which rounded cents away. Now DECIMAL(18,2).
--     Same logic otherwise (drafts still count as spent, decision 14). Stops unless the current definition is the known one
--     (md5 ff209ba2...) or is already DECIMAL(18,2).
-- Take a backup first:  mysqldump -u root -p --routines --triggers cms > before_followups.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_tmp_followups_8_5d$$
CREATE PROCEDURE sp_tmp_followups_8_5d()
BEGIN
    DECLARE n INT;
    DECLARE fnmd5 VARCHAR(32);

    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblpledges_header' AND COLUMN_NAME = 'ID' AND EXTRA LIKE '%auto_increment%';
    IF n = 0 THEN
        SELECT COUNT(*) INTO n FROM information_schema.KEY_COLUMN_USAGE
         WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'tblpledges_header';
        IF n > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused: a foreign key references tblpledges_header; review it before changing ID';
        END IF;
        ALTER TABLE tblpledges_header MODIFY ID INT(11) NOT NULL AUTO_INCREMENT;
        SELECT 'step 1: tblpledges_header.ID AUTO_INCREMENT applied' AS result;
    ELSE
        SELECT 'step 1: tblpledges_header.ID AUTO_INCREMENT skipped (already set)' AS result;
    END IF;

    SELECT md5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')), (ROUTINE_DEFINITION LIKE '%decimal(18,2)%')
      INTO fnmd5, n
      FROM information_schema.ROUTINES
     WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'getrequisitionbalance';
    IF fnmd5 IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'step 2 refused: getrequisitionbalance does not exist';
    END IF;
    IF n = 0 AND fnmd5 <> 'ff209ba241a4c6e11da90ded66141a00' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'step 2 refused: getrequisitionbalance differs from the definition this script was written against; review it before replacing';
    END IF;
    SELECT IF(n = 0, 'step 2: getrequisitionbalance will be replaced', 'step 2: getrequisitionbalance already DECIMAL(18,2) (recreated unchanged)') AS result;
END$$

CALL sp_tmp_followups_8_5d()$$
DROP PROCEDURE sp_tmp_followups_8_5d$$

SET @old_sql_mode := @@sql_mode$$
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'$$
DROP FUNCTION IF EXISTS getrequisitionbalance$$
CREATE FUNCTION getrequisitionbalance(`rid` INT) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE ApprovedAmount decimal(18,2);
  DECLARE SpentAmount decimal(18,2);
  SET ApprovedAmount = (SELECT IFNULL(SUM(AmountApproved),0) AS AmountApproved FROM tblfundrequisition WHERE ID=rid);
  SET SpentAmount = (SELECT IFNULL(SUM(amount),0) AS SpentAmount FROM tblexpenses WHERE (requisitionId=rid) AND (deleted = 0));
  RETURN ApprovedAmount - SpentAmount;
END$$
SET sql_mode = @old_sql_mode$$

DELIMITER ;

SELECT 'followups_8_5d.sql finished' AS result;

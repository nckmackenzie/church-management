-- brand_level_cycle_safe.sql  (task 8.10)  IDEMPOTENT, MariaDB 10.4 / 10.6.
-- Run on dev now and on PRODUCTION at cutover (after ledger_schema.sql, before the code deploy):
--   mysql -uroot cms < scripts/sql/brand_level_cycle_safe.sql      (phpMyAdmin: paste the whole file; it uses DELIMITER.)
-- brand_level(id) = depth of an account: 1 for a root, 2 directly under a root, ... , NULL when the chain does not end at a root.
-- The old body (repeat ... until brand_id = 0 or NULL) never ended when an account was its own ancestor (the old edit form could make
-- an account its own parent), which hung every query that called it. The new body returns the SAME values for every well-formed chain
-- and returns NULL after 50 steps, so a loop in the data can no longer hang anything.
-- Stops unless the current definition is the known old one (md5 feec469d...) or already the new one (contains 'cycle_safe_steps').
-- Take a backup first:  mysqldump -u root -p --routines --triggers cms > before_8_10.sql
-- ROLLBACK: recreate the old definition (kept below), or restore the backup:
--   CREATE FUNCTION brand_level(`brand_id` INT) RETURNS int(11)
--   begin declare p_id, l int; set l = 0; repeat set l = l + 1; select parentId into brand_id from tblaccounttypes where ID = brand_id;
--   until brand_id = 0 or brand_id is NULL end repeat; return if(brand_id=0, l, NULL); end

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_tmp_brand_level$$
CREATE PROCEDURE sp_tmp_brand_level()
BEGIN
    DECLARE fnmd5 VARCHAR(32);
    DECLARE n INT;
    SELECT md5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')), (ROUTINE_DEFINITION LIKE '%cycle_safe_steps%')
      INTO fnmd5, n
      FROM information_schema.ROUTINES
     WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'brand_level';
    IF fnmd5 IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused: brand_level does not exist';
    END IF;
    IF n = 0 AND fnmd5 <> 'feec469d7ca60f1b906989b327838829' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused: brand_level differs from the definition this script was written against (md5 feec469d...); review it before replacing';
    END IF;
    SELECT IF(n = 0, 'brand_level will be replaced', 'brand_level already cycle-safe (recreated unchanged)') AS result;
END$$

CALL sp_tmp_brand_level()$$
DROP PROCEDURE sp_tmp_brand_level$$

SET @old_sql_mode := @@sql_mode$$
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'$$
DROP FUNCTION IF EXISTS brand_level$$
CREATE FUNCTION brand_level(`brand_id` INT) RETURNS int(11)
BEGIN
   -- cycle-safe (task 8.10): same result as before, NULL after 50 steps
   DECLARE l, cycle_safe_steps INT;   -- cycle_safe_steps: marks the task 8.10 definition (comments are not stored)
   SET cycle_safe_steps = 50;
   SET l = 0;
   REPEAT
      SET l = l + 1;
      SELECT parentId INTO brand_id FROM tblaccounttypes WHERE ID = brand_id;
   UNTIL brand_id = 0 OR brand_id IS NULL OR l >= cycle_safe_steps END REPEAT;
   RETURN IF(brand_id = 0, l, NULL);
END$$
SET sql_mode = @old_sql_mode$$

DELIMITER ;

SELECT 'brand_level_cycle_safe.sql finished' AS result;

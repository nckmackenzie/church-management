-- 8.9 step 0: the group / district balance functions and the MMF statement ignored tblmmf.Deleted, so soft-deleted rows
-- (deleted contributions, deleted group collections) still counted in balances. Add AND Deleted = 0.
-- Run on production at cutover (before the 8.9 code is deployed). Re-runnable. No DEFINER clause: the running user owns them.
--   E:\xampp\mysql\bin\mysql.exe -uroot cms < scripts\sql\mmf_balance_functions_deleted.sql

DROP FUNCTION IF EXISTS getmmfopeningbal;
DROP FUNCTION IF EXISTS getmmfopeningbalbydate;
DROP FUNCTION IF EXISTS getmmfopeningbal_district;
DROP FUNCTION IF EXISTS getmmfopeningbalbydate_district;
DROP PROCEDURE IF EXISTS sp_mmf_statement;

DELIMITER $$

CREATE FUNCTION getmmfopeningbal(`gid` INT, `sdate` DATE) RETURNS decimal(18,2)
BEGIN
    DECLARE Debits DECIMAL(18,2);
    DECLARE Credits DECIMAL(18,2);
    SET Debits = (SELECT IFNULL(SUM(Debit),0) FROM tblmmf
                  WHERE (GroupId = gid) AND (Deleted = 0) AND (TransactionDate < sdate));
    SET Credits = (SELECT IFNULL(SUM(Credit),0) FROM tblmmf
                   WHERE (GroupId = gid) AND (Deleted = 0) AND (TransactionDate < sdate));
    RETURN Debits - Credits;
END$$

CREATE FUNCTION getmmfopeningbalbydate(`gid` INT, `sdate` DATE) RETURNS decimal(18,2)
BEGIN
    DECLARE Debits DECIMAL(18,2);
    DECLARE Credits DECIMAL(18,2);
    SET Debits = (SELECT IFNULL(SUM(Debit),0) FROM tblmmf
                  WHERE (GroupId = gid) AND (Deleted = 0) AND (TransactionDate <= sdate));
    SET Credits = (SELECT IFNULL(SUM(Credit),0) FROM tblmmf
                   WHERE (GroupId = gid) AND (Deleted = 0) AND (TransactionDate <= sdate));
    RETURN Debits - Credits;
END$$

CREATE FUNCTION getmmfopeningbal_district(`did` INT, `sdate` DATE) RETURNS decimal(18,2)
BEGIN
    DECLARE Debits DECIMAL(18,2);
    DECLARE Credits DECIMAL(18,2);
    SET Debits = (SELECT IFNULL(SUM(Debit),0) FROM tblmmf
                  WHERE (DistrictId = did) AND (Deleted = 0) AND (TransactionDate < sdate));
    SET Credits = (SELECT IFNULL(SUM(Credit),0) FROM tblmmf
                   WHERE (DistrictId = did) AND (Deleted = 0) AND (TransactionDate < sdate));
    RETURN Debits - Credits;
END$$

CREATE FUNCTION getmmfopeningbalbydate_district(`did` INT, `sdate` DATE) RETURNS decimal(18,2)
BEGIN
    DECLARE Debits DECIMAL(18,2);
    DECLARE Credits DECIMAL(18,2);
    SET Debits = (SELECT IFNULL(SUM(Debit),0) FROM tblmmf
                  WHERE (DistrictId = did) AND (Deleted = 0) AND (TransactionDate <= sdate));
    SET Credits = (SELECT IFNULL(SUM(Credit),0) FROM tblmmf
                   WHERE (DistrictId = did) AND (Deleted = 0) AND (TransactionDate <= sdate));
    RETURN Debits - Credits;
END$$

CREATE PROCEDURE sp_mmf_statement(`gid` INT, `sdate` DATE, `edate` DATE)
BEGIN
    SELECT
        sdate AS TransactionDate,
        'Opening Balance' AS Narration,
        '' As Reference,
        getmmfopeningbal(gid,sdate) As Debit,
        0 As Credit
    UNION ALL
    SELECT
        TransactionDate,
        '' As Narration,
        Reference,
        Debit,
        Credit
    FROM
        tblmmf
    WHERE
        (TransactionDate BETWEEN sdate AND edate) AND (GroupId = gid) AND (Deleted = 0)
    ORDER BY TransactionDate;
END$$

DELIMITER ;

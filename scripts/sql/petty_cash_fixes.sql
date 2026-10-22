-- Task 8.4b: petty cash reports and screen.
-- Idempotent (column added only if missing; routines and view are dropped / replaced). Run on dev now, on production
-- at cutover:  mysql -uroot cms < scripts/sql/petty_cash_fixes.sql
--
--  1. tblpettycash.PettyCashAccountId: which petty cash account (Ledger::PETTY_CASH_ACCOUNTS = 98, 194) a row belongs
--     to. DEFAULT 98 = what every existing row is (all transfers went to 98; only 'petty cash' = 98 expenses write
--     tblpettycash). Expense.php does not set the column yet (task 8.5 will), so its inserts keep landing on 98.
--  2. sp_pettycashutilization, getopeningbal: ignore deleted rows, take the petty cash account as a parameter.
--  3. vw_cashreceipts: only the screen's own receipts (not receipts created by a bank transfer), with the account.

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblpettycash' AND COLUMN_NAME = 'PettyCashAccountId');
SET @sql := IF(@has = 0,
               'ALTER TABLE tblpettycash ADD COLUMN PettyCashAccountId INT NOT NULL DEFAULT 98 AFTER BankId',
               'SELECT ''PettyCashAccountId already exists'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1b. foreign key (task 8.4c): every row must name an existing account. The check is a plain SELECT so a bad value
--     is visible before the ALTER fails; the key (and its index) is added only if missing.
SELECT COUNT(*) AS rows_with_unknown_petty_cash_account
FROM   tblpettycash p LEFT JOIN tblaccounttypes a ON a.ID = p.PettyCashAccountId
WHERE  a.ID IS NULL;

SET @hasfk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tblpettycash'
                     AND CONSTRAINT_NAME = 'FK_PettyCashAccount_PettyCash' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@hasfk = 0,
               'ALTER TABLE tblpettycash ADD KEY IDX_PettyCashAccountId (PettyCashAccountId), ADD CONSTRAINT FK_PettyCashAccount_PettyCash FOREIGN KEY (PettyCashAccountId) REFERENCES tblaccounttypes (ID)',
               'SELECT ''FK_PettyCashAccount_PettyCash already exists'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

DROP PROCEDURE IF EXISTS sp_pettycashutilization;
DROP FUNCTION  IF EXISTS getopeningbal;

DELIMITER $$

CREATE FUNCTION getopeningbal(tdate DATE, congid INT, accid INT) RETURNS decimal(18,2)
BEGIN
    DECLARE DebitTotal DECIMAL(18,2);
    DECLARE CreditTotal DECIMAL(18,2);
    SET DebitTotal = (SELECT IFNULL(SUM(Debit),0) FROM tblpettycash
                      WHERE TransactionDate < tdate AND CongregationId = congid
                            AND PettyCashAccountId = accid AND Deleted = 0);
    SET CreditTotal = (SELECT IFNULL(SUM(Credit),0) FROM tblpettycash
                       WHERE TransactionDate < tdate AND CongregationId = congid
                             AND PettyCashAccountId = accid AND Deleted = 0);
    RETURN DebitTotal - CreditTotal;
END$$

CREATE PROCEDURE sp_pettycashutilization(IN sdate DATE, IN edate DATE, IN congid INT, IN accid INT)
BEGIN
    SELECT DATE_FORMAT(sdate,'%d/%m/%y') AS TransactionDate,
           'Opening Balance' AS Narration,
           getopeningbal(sdate,congid,accid) AS Debit,
           0 AS Credit,
           '' AS Reference
    UNION ALL
    SELECT
        DATE_FORMAT(TransactionDate,'%d/%m/%y') AS TransactionDate,
        UCASE(Narration) AS Narration,
        FORMAT(Debit,2) AS Debit,
        FORMAT(Credit,2) AS Credit,
        UCASE(Reference) AS Reference
    FROM
        tblpettycash
    WHERE
        (TransactionDate BETWEEN sdate AND edate) AND (CongregationId = congid)
        AND (PettyCashAccountId = accid) AND (Deleted = 0)
    ORDER BY TransactionDate;
END$$

DELIMITER ;

CREATE OR REPLACE VIEW vw_cashreceipts AS
SELECT p.ID AS ID,
       date_format(p.TransactionDate,'%d/%m/%Y') AS TransactionDate,
       p.ReceiptNo AS ReceiptNo,
       p.Debit AS Debit,
       concat(ucase(a.accountType),'-',IFNULL(a.accountNo,'')) AS Bank,
       ucase(pc.accountType) AS PettyCashAccount,
       p.PettyCashAccountId AS PettyCashAccountId,
       p.Reference AS Reference,
       p.IsReceipt AS IsReceipt,
       p.CongregationId AS CongregationId
FROM   tblpettycash p
       JOIN tblaccounttypes a  ON p.BankId = a.ID
       JOIN tblaccounttypes pc ON p.PettyCashAccountId = pc.ID
WHERE  p.Deleted = 0 AND p.IsReceipt = 1 AND p.TransactionType IS NULL;

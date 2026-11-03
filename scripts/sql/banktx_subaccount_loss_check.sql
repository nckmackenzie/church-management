-- banktx_subaccount_loss_check.sql  (task 13)  READ-ONLY: SELECT statements only, nothing is created, changed or deleted.
-- CLI / phpMyAdmin only (never served by the web application). MariaDB 10.6 (also runs on 10.4 / MySQL 8).
--
-- WHY: before task 13, editing bank transaction N ran  DELETE FROM tblbanktransactions_subaccounts WHERE TransactionId = N
-- with no TransactionType and no CongregationId. The table is shared by writers with their own ID spaces (receipts type 1,
-- bank transactions type 13, sub account opening balances type 18), so an edit of bank transaction N also removed the
-- sub-account rows of receipt N (any congregation) and the opening balance row N. Editing a deposit that was split over
-- sub accounts through the old edit form also removed its own rows (the form did not send the split back).
-- This file lists what may be missing so the accountants can repair it BY HAND (owner rule: no data-correction script).
--
-- Run each statement separately. Needs the session to be allowed to read the tables only.
-- Q1  receipt lines (type 1) = the worklist      Q2  bank transactions (type 13)      Q3  opening balances (type 18): Q3a, Q3b, Q3c
-- Q4  extra rows left by DELETED bank transactions (the old Delete never removed them; fixed in task 13). Since task 13b the sub account
--     reports ignore these rows (nothing is deleted); Q4 shows what the filter removes.

-- ----------------------------------------------------------------------------------------------------
-- Q1. WORKLIST FOR THE ACCOUNTANTS. Active receipts whose lines carry a sub account (tblcontributions_details.subaccount > 0) but whose
--     type-1 rows in tblbanktransactions_subaccounts are fewer than the lines (same congregation, receipt ID, sub account and amount).
--     Contribution::saveDetails writes one row per such line: TransactionDate = line date, TransactionId = header ID,
--     SubAccountId = line sub account, Amount = line amount, TransactionType = 1, CongregationId = header congregation.
--     Columns after the amounts:
--       yearName / yearStatus : the receipt's financial year and whether it is open or closed (Contribution::update refuses a closed year)
--       hasClearedBankRow     : the receipt has an active bank row that was cleared in bank reconciliation
--       clearedRowBlocks      : the same rule as Contribution::update (Ledger::clearedBankRowProblem, and the old-receipt aggregate rule) would refuse
--                               an UNCHANGED re-save: a cleared bank row that no receipt line carries (same bank, same amount). A cleared row that
--                               a line carries does NOT block (a cleared row may stay as it is), so hasClearedBankRow = yes can still be 're-save'.
--       repair                : 're-save in the app' = open the receipt and press Save without changing anything: the app rewrites the missing
--                               sub-account rows (task 13b test: nothing else changes). 'locked: closed year' / 'locked: cleared bank row' (or both)
--                               = the app refuses the save; the accountants decide how to correct it (reopening a year is the owner's decision).
--                               The app may still show its own message (e.g. an account or sub account deactivated since): follow it.
--     Last columns: check 3 - an ACTIVE bank transaction with the same ID number as the receipt (the likely cause).
--     Receipts saved before task 8.4b may also be listed for other reasons (see scripts/sql/receipt_subaccount_rebuild.sql).
SELECT z.congregationId, z.receiptNo, z.receiptId, z.lineDate, z.lineIds, z.subAccountId, z.subAccount, z.amount, z.expectedRows, z.presentRows,
       z.yearName, z.yearStatus,
       IF(z.hasCleared, 'yes', 'no') AS hasClearedBankRow,
       IF(z.clearedBlocks, 'yes', 'no') AS clearedRowBlocks,
       CASE WHEN z.yearStatus = 'closed' AND z.clearedBlocks THEN 'locked: closed year + locked: cleared bank row'
            WHEN z.yearStatus = 'closed' THEN 'locked: closed year'
            WHEN z.clearedBlocks THEN 'locked: cleared bank row'
            ELSE 're-save in the app' END AS repair,
       z.sameIdBankTxn, z.bankTxnCongregation, z.bankTxnDate, z.bankTxnType, z.activeBankTxnWithSameId
FROM (
    SELECT h.congregationId,
           h.receiptNo,
           h.ID                          AS receiptId,
           e.lineDate,
           e.lineIds,
           e.subAccountId,
           sa.AccountName                AS subAccount,
           e.amount,
           e.expectedRows,
           IFNULL(p.presentRows,0)       AS presentRows,
           fy.yearName,
           IF(fy.closed = 1,'closed','open') AS yearStatus,
           EXISTS (SELECT 1 FROM tblbankpostings r WHERE r.congregationId = h.congregationId AND r.transactionType = 1
                   AND r.transactionId = h.ID AND r.deleted = 0 AND r.cleared = 1) AS hasCleared,
           (   EXISTS (SELECT 1 FROM tblbankpostings r
                       WHERE r.congregationId = h.congregationId AND r.transactionType = 1 AND r.transactionId = h.ID AND r.deleted = 0 AND r.cleared = 1
                         AND NOT EXISTS (SELECT 1 FROM tblcontributions_details d2
                                         WHERE d2.HeaderId = h.ID AND d2.bankId = r.bankId
                                           AND ROUND(d2.amount * 100) = ROUND(GREATEST(r.debit, r.credit) * 100)))
           AND NOT (    (SELECT COUNT(*) FROM tblbankpostings r2 WHERE r2.congregationId = h.congregationId AND r2.transactionType = 1
                         AND r2.transactionId = h.ID AND r2.deleted = 0) = 1
                    AND (SELECT COUNT(*) FROM tblcontributions_details d3 WHERE d3.HeaderId = h.ID) > 1
                    AND EXISTS (SELECT 1 FROM tblbankpostings r3
                                WHERE r3.congregationId = h.congregationId AND r3.transactionType = 1 AND r3.transactionId = h.ID
                                  AND r3.deleted = 0 AND r3.cleared = 1
                                  AND r3.bankId IN (SELECT d4.bankId FROM tblcontributions_details d4 WHERE d4.HeaderId = h.ID)
                                  AND ROUND(GREATEST(r3.debit, r3.credit) * 100) =
                                      ROUND((SELECT SUM(d5.amount) FROM tblcontributions_details d5 WHERE d5.HeaderId = h.ID) * 100))
                   )
           ) AS clearedBlocks,
           b.ID                          AS sameIdBankTxn,
           b.CongregationId              AS bankTxnCongregation,
           b.TransactionDate             AS bankTxnDate,
           b.TransactionTypeId           AS bankTxnType,
           IF(b.ID IS NULL,'no','yes - likely cause') AS activeBankTxnWithSameId
    FROM (SELECT d.HeaderId, d.subaccount AS subAccountId, d.amount, MIN(d.contributionDate) AS lineDate,
                 GROUP_CONCAT(d.ID ORDER BY d.ID) AS lineIds, COUNT(*) AS expectedRows
          FROM tblcontributions_details d
          WHERE d.subaccount IS NOT NULL AND d.subaccount > 0
          GROUP BY d.HeaderId, d.subaccount, d.amount) e
    JOIN tblcontributions_header h ON h.ID = e.HeaderId AND h.Deleted = 0
    LEFT JOIN (SELECT s.TransactionId, s.CongregationId, s.SubAccountId, s.Amount, COUNT(*) AS presentRows
               FROM tblbanktransactions_subaccounts s
               WHERE s.TransactionType = 1
               GROUP BY s.TransactionId, s.CongregationId, s.SubAccountId, s.Amount) p
           ON p.TransactionId = h.ID AND p.CongregationId = h.congregationId AND p.SubAccountId = e.subAccountId AND p.Amount = e.amount
    LEFT JOIN tblbanksubaccounts sa ON sa.ID = e.subAccountId
    LEFT JOIN tblfiscalyears fy ON fy.ID = h.fiscalYearId
    LEFT JOIN tblbanktransactions b ON b.ID = h.ID AND b.Deleted = 0
    WHERE IFNULL(p.presentRows,0) < e.expectedRows
) z
ORDER BY z.congregationId, z.receiptId, z.subAccountId;

-- ----------------------------------------------------------------------------------------------------
-- Q2. Active bank transactions (type 13) that should have sub-account rows but have fewer or different ones.
--     Bank transaction writer (Banktransaction::Save / Update), rows with TransactionType = 13, TransactionId = tblbanktransactions.ID:
--       DepositToSubAccounts = 1: one row per sub account, together = Amount (none of FromAccountId / ToAccountId set)  -> expected: at least 1 row, sum = Amount
--       IsSubInterTransfer = 1:   two rows: +Amount with FromAccountId set, -Amount with ToAccountId set               -> expected: 2 rows, sum = 0
--     (sub account opening balances, type 18, have no source table; they are listed in Q3.)
--     The last columns are check 3 (an active bank transaction with the same ID is this very transaction here, so they show
--     whether ANOTHER source with that number exists: a receipt of any congregation).
SELECT t.CongregationId,
       t.ID                           AS bankTxnId,
       t.Reference,
       t.TransactionDate,
       t.TransactionTypeId,
       t.Amount,
       IF(t.IsSubInterTransfer = 1,'sub account transfer','deposit split over sub accounts') AS kind,
       IF(t.IsSubInterTransfer = 1,2,NULL)                     AS expectedRows,
       IFNULL(r.presentRows,0)        AS presentRows,
       IF(t.IsSubInterTransfer = 1,0,t.Amount)                 AS expectedSum,
       IFNULL(r.presentSum,0)         AS presentSum,
       (SELECT h.receiptNo FROM tblcontributions_header h WHERE h.ID = t.ID AND h.Deleted = 0) AS receiptWithSameId
FROM tblbanktransactions t
LEFT JOIN (SELECT s.TransactionId, s.CongregationId, COUNT(*) AS presentRows, SUM(s.Amount) AS presentSum
           FROM tblbanktransactions_subaccounts s
           WHERE s.TransactionType = 13
           GROUP BY s.TransactionId, s.CongregationId) r
       ON r.TransactionId = t.ID AND r.CongregationId = t.CongregationId
WHERE t.Deleted = 0
  AND (t.DepositToSubAccounts = 1 OR t.IsSubInterTransfer = 1)
  AND (   IFNULL(r.presentRows,0) = 0
       OR (t.IsSubInterTransfer = 1 AND (r.presentRows <> 2 OR r.presentSum <> 0))
       OR (t.IsSubInterTransfer = 0 AND r.presentSum <> t.Amount))
ORDER BY t.CongregationId, t.ID;

-- ----------------------------------------------------------------------------------------------------
-- Q3. Sub account opening balances (type 18). A row is its own source (TransactionId = its own ID), so a lost row leaves no trace in
--     the data and a query cannot name it. Three lists help the accountants:
--  Q3a. sub accounts of the congregation WITHOUT an opening balance row (candidates: ask whether the sub account ever had one);
SELECT b.congregationId, s.ID AS subAccountId, s.AccountName AS subAccount, s.GroupDistrict,
       (SELECT COUNT(*) FROM tblbanktransactions_subaccounts t WHERE t.SubAccountId = s.ID) AS rowsInBook,
       (SELECT MIN(t.TransactionDate) FROM tblbanktransactions_subaccounts t WHERE t.SubAccountId = s.ID) AS firstBookDate
FROM tblbanksubaccounts s
JOIN tblaccounttypes b ON b.ID = s.BankId
WHERE s.Deleted = 0
  AND NOT EXISTS (SELECT 1 FROM tblbanktransactions_subaccounts t WHERE t.SubAccountId = s.ID AND t.TransactionType = 18 AND t.CongregationId = b.congregationId)
ORDER BY b.congregationId, s.ID;

--  Q3b. the opening balances that EXIST, with the active bank transaction of the same ID number (date, type, amount, reference, description):
--       before task 13 the first edit of that bank transaction deleted the opening balance, so a row listed here with a bank transaction was
--       at risk and a neighbour of the same age may already be gone (compare with the accountants' own list of opening balances).
SELECT s.CongregationId, s.ID AS openingBalanceId, s.TransactionId, s.TransactionDate, s.SubAccountId, sa.AccountName AS subAccount, s.Amount,
       IF(s.TransactionId <> s.ID,'TransactionId differs from ID','') AS note,
       b.CongregationId AS bankTxnCongregation, b.TransactionDate AS bankTxnDate, b.TransactionTypeId AS bankTxnType, b.Amount AS bankTxnAmount,
       b.Reference AS bankTxnReference, b.Description AS bankTxnDescription
FROM tblbanktransactions_subaccounts s
LEFT JOIN tblbanksubaccounts sa ON sa.ID = s.SubAccountId
LEFT JOIN tblbanktransactions b ON b.ID = s.ID AND b.Deleted = 0
WHERE s.TransactionType = 18
ORDER BY s.CongregationId, s.TransactionDate, s.ID;

--  Q3c. if the accountants know the ID of a lost opening balance (old backup, printout), set it here and run the two statements:
--       the active bank transaction with that ID number is the likely cause. A session variable only, nothing is changed.
SET @lost_opening_balance_id = 0;
SELECT b.ID AS bankTxnId, b.CongregationId, b.TransactionDate, b.TransactionTypeId, b.Amount, b.Reference, b.Description, b.Deleted
FROM tblbanktransactions b
WHERE b.ID = @lost_opening_balance_id;

-- ----------------------------------------------------------------------------------------------------
-- Q4. Rows that should not be there: type-13 rows whose bank transaction is deleted or does not exist (the old Delete left them,
--     and every sub-account balance sums the table). Informational: reported to the accountants; task 13 removes them only
--     for transactions deleted from now on.
SELECT s.CongregationId, s.ID AS rowId, s.TransactionId AS bankTxnId, s.TransactionDate, s.SubAccountId, s.Amount, s.Reference,
       IF(b.ID IS NULL,'bank transaction missing',IF(b.CongregationId <> s.CongregationId,'other congregation','bank transaction deleted')) AS why
FROM tblbanktransactions_subaccounts s
LEFT JOIN tblbanktransactions b ON b.ID = s.TransactionId
WHERE s.TransactionType = 13
  AND (b.ID IS NULL OR b.Deleted = 1 OR b.CongregationId <> s.CongregationId)
ORDER BY s.CongregationId, s.TransactionId, s.ID;

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
-- Q1  receipt lines (type 1)      Q2  bank transactions (type 13)      Q3  opening balances (type 18) to compare by eye
-- Q4  extra rows left by DELETED bank transactions (the old Delete never removed them; fixed in task 13)

-- ----------------------------------------------------------------------------------------------------
-- Q1. Active receipts whose lines carry a sub account (tblcontributions_details.subaccount > 0) but whose type-1 rows in
--     tblbanktransactions_subaccounts are fewer than the lines (same congregation, receipt ID, sub account and amount).
--     Contribution::saveDetails writes one row per such line: TransactionDate = line date, TransactionId = header ID,
--     SubAccountId = line sub account, Amount = line amount, TransactionType = 1, CongregationId = header congregation.
--     The last columns are check 3: an ACTIVE bank transaction with the same ID number as the receipt (the likely cause).
--     Receipts saved before task 8.4b may also be listed for other reasons (see scripts/sql/receipt_subaccount_rebuild.sql).
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
LEFT JOIN tblbanktransactions b ON b.ID = h.ID AND b.Deleted = 0
WHERE IFNULL(p.presentRows,0) < e.expectedRows
ORDER BY h.congregationId, h.ID, e.subAccountId;

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
-- Q3. Sub account opening balances (type 18). A row is its own source (TransactionId = its own ID), so a lost row cannot be
--     detected by a query: the accountants compare this list with their records (Banks > sub account opening balances).
--     sameIdBankTxn = an active bank transaction with the same ID number: editing it before task 13 deleted this row
--     (check 3: the likely cause of a row that is in the accountants' records but not in this list).
SELECT s.CongregationId, s.ID, s.TransactionId, s.TransactionDate, s.SubAccountId, sa.AccountName AS subAccount, s.Amount,
       IF(s.TransactionId <> s.ID,'TransactionId differs from ID','') AS note,
       (SELECT GROUP_CONCAT(b.CongregationId,'/',b.ID SEPARATOR ', ') FROM tblbanktransactions b WHERE b.ID = s.ID AND b.Deleted = 0) AS sameIdBankTxn
FROM tblbanktransactions_subaccounts s
LEFT JOIN tblbanksubaccounts sa ON sa.ID = s.SubAccountId
WHERE s.TransactionType = 18
ORDER BY s.CongregationId, s.TransactionDate, s.ID;

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

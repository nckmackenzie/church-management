-- DRAFT - NOT RUN. Task 8.4c: rebuild the stale tblbanktransactions_subaccounts rows (type 1) of ACTIVE receipts.
--
-- Why: the old Contribution::update() never rewrote these rows, so a collection line that was added, removed or
-- changed by an edit never reached the sub-account balances (dev: 27 receipts, 38 receipt / sub-account pairs,
-- net +111,713.00 to the sub-account balances once rebuilt). The receipts' details, ledger entries and tblmmf rows
-- all agree with each other, so the details are the truth.
--
-- What: for every active receipt where any sub-account's rows differ from its details (sum per sub-account), delete
-- ALL of that receipt's type-1 rows and recreate one row per detail line with subaccount > 0, with the same columns
-- Contribution::saveDetails() writes today:
--   TransactionDate = the receipt date (details.contributionDate), TransactionId = receipt ID, SubAccountId,
--   Amount = the line amount, TransactionType = 1, Narration = 'Collection', Reference = payment reference
--   (lower case, as stored in details), CongregationId = the receipt's congregation.
-- Lines with subaccount = 0 or NULL are NOT collection lines (the old update wrote '' as 0) and are ignored.
-- Receipts that already match are not touched, so a second run changes nothing.
--
-- Run (after reviewing the preview) on dev, then on production at cutover:
--   mysql -uroot cms < scripts/sql/receipt_subaccount_rebuild.sql

CREATE TEMPORARY TABLE rb_affected (rid INT PRIMARY KEY);

INSERT INTO rb_affected (rid)
SELECT DISTINCT k.rid
FROM (
    SELECT d.HeaderId AS rid, d.subaccount AS sid FROM tblcontributions_details d
      JOIN tblcontributions_header h ON h.ID = d.HeaderId AND h.Deleted = 0
     WHERE d.subaccount > 0
    UNION
    SELECT s.TransactionId, s.SubAccountId FROM tblbanktransactions_subaccounts s
      JOIN tblcontributions_header h ON h.ID = s.TransactionId AND h.Deleted = 0
     WHERE s.TransactionType = 1
) k
WHERE ABS(
        IFNULL((SELECT SUM(d.amount) FROM tblcontributions_details d WHERE d.HeaderId = k.rid AND d.subaccount = k.sid), 0)
      - IFNULL((SELECT SUM(s.Amount) FROM tblbanktransactions_subaccounts s
                 WHERE s.TransactionType = 1 AND s.TransactionId = k.rid AND s.SubAccountId = k.sid), 0)
      ) > 0.005;

-- preview: what will be deleted and what will be inserted
SELECT COUNT(*) AS receipts_to_rebuild FROM rb_affected;
SELECT COUNT(*) AS rows_to_delete, IFNULL(ROUND(SUM(s.Amount), 2), 0) AS amount_to_delete
FROM tblbanktransactions_subaccounts s JOIN rb_affected a ON a.rid = s.TransactionId WHERE s.TransactionType = 1;
SELECT COUNT(*) AS rows_to_insert, IFNULL(ROUND(SUM(d.amount), 2), 0) AS amount_to_insert
FROM tblcontributions_details d JOIN rb_affected a ON a.rid = d.HeaderId WHERE d.subaccount > 0;

START TRANSACTION;

DELETE s
FROM tblbanktransactions_subaccounts s
JOIN rb_affected a ON a.rid = s.TransactionId
WHERE s.TransactionType = 1;

INSERT INTO tblbanktransactions_subaccounts
       (TransactionDate, TransactionId, SubAccountId, Amount, TransactionType, Narration, Reference, CongregationId)
SELECT d.contributionDate, d.HeaderId, d.subaccount, ROUND(d.amount, 2), 1, 'Collection', LEFT(d.paymentReference, 120), h.congregationId
FROM tblcontributions_details d
JOIN rb_affected a ON a.rid = d.HeaderId
JOIN tblcontributions_header h ON h.ID = d.HeaderId AND h.Deleted = 0
WHERE d.subaccount > 0
ORDER BY d.ID;

SELECT ROW_COUNT() AS rows_inserted;

COMMIT;

DROP TEMPORARY TABLE rb_affected;

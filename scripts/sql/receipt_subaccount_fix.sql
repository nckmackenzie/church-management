-- Task 8.4b: remove the sub-account rows (tblbanktransactions_subaccounts, type 1) of DELETED receipts.
-- tblbanktransactions_subaccounts has no Deleted column and every sub-account balance routine sums it, so a deleted
-- receipt kept inflating its sub-account (dev: 28 rows, 43,625.00). Hard delete, as Banktransaction does for type 13.
-- Safe to re-run (second run deletes 0 rows). Run on dev now, on production at cutover.
-- (tblmmf rows of deleted receipts are already soft-deleted: Deleted = 1.)

SELECT COUNT(*) AS rows_to_delete, IFNULL(ROUND(SUM(s.Amount),2),0) AS amount
FROM   tblbanktransactions_subaccounts s
       JOIN tblcontributions_header h ON h.ID = s.TransactionId AND h.congregationId = s.CongregationId
WHERE  s.TransactionType = 1 AND h.Deleted = 1;

DELETE s
FROM   tblbanktransactions_subaccounts s
       JOIN tblcontributions_header h ON h.ID = s.TransactionId AND h.congregationId = s.CongregationId
WHERE  s.TransactionType = 1 AND h.Deleted = 1;

SELECT ROW_COUNT() AS rows_deleted;

-- any tblmmf row of a deleted receipt that is still active (expected 0)
UPDATE tblmmf m
       JOIN tblcontributions_header h ON h.ID = m.TransactionId AND h.congregationId = m.CongregationId
SET    m.Deleted = 1
WHERE  m.TransactionType = 1 AND h.Deleted = 1 AND m.Deleted = 0;

SELECT ROW_COUNT() AS mmf_rows_soft_deleted;

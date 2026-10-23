-- GENERATED FROM DEV (task 8.9 follow-up 3) - REVIEW BEFORE USE. READ-ONLY (SELECT only).
-- "F14" of the migration work relinked five type-1 bank rows that had transactionId = 0 to their receipts on dev; no script
-- exists for it and the original values cannot be recovered, so this file only FINDS the same kind of rows on production.
-- Context (docs/ledger_inventory.md, open question 12): on dev there were seven active type-1 bank rows with transactionId = 0
-- (bank 105, 2023-12-17 to 2024-07-06, 450,000 in all); five were relinked, two (bank rows 682 and 696) are duplicates accepted as
-- known legacy differences, and bank row 1565 (receipt 1890) stays because its ledger entry is voided.
--
-- Run on production BEFORE migrate_ledger.php; decide each row by hand (relink to the receipt, or accept as a legacy difference).
--   mysql -uroot cms < ledger-migration/f14_orphan_bank_rows_match.sql

-- 1. Active type-1 bank rows that belong to no receipt (transactionId 0 / NULL / a receipt that does not exist)
SELECT b.ID AS bankRowId, b.bankId, b.transactionDate, b.debit, b.reference, b.cleared, b.transactionId, b.congregationId
FROM   tblbankpostings b
       LEFT JOIN tblcontributions_header h ON h.ID = b.transactionId AND h.congregationId = b.congregationId
WHERE  b.transactionType = 1 AND b.deleted = 0 AND (b.transactionId IS NULL OR b.transactionId = 0 OR h.ID IS NULL)
ORDER  BY b.transactionDate, b.ID;

-- 2. For each of them, the receipts that could be theirs: same congregation, same date, bank receipt on the same bank, bank total
--    equal to the row's amount, and no active bank row of their own yet.
SELECT b.ID AS bankRowId, b.transactionDate, b.debit AS bankAmount, h.ID AS candidateReceiptId, h.receiptNo,
       (SELECT SUM(d.amount) FROM tblcontributions_details d WHERE d.HeaderId = h.ID) AS receiptTotal,
       (SELECT COUNT(*) FROM tblbankpostings x WHERE x.transactionType = 1 AND x.transactionId = h.ID AND x.deleted = 0) AS receiptActiveBankRows
FROM   tblbankpostings b
       JOIN tblcontributions_details d0 ON d0.contributionDate = b.transactionDate AND d0.bankId = b.bankId
       JOIN tblcontributions_header h ON h.ID = d0.HeaderId AND h.congregationId = b.congregationId AND h.Deleted = 0
WHERE  b.transactionType = 1 AND b.deleted = 0 AND (b.transactionId IS NULL OR b.transactionId = 0)
GROUP  BY b.ID, b.transactionDate, b.debit, h.ID, h.receiptNo
HAVING ABS(receiptTotal - b.debit) < 0.005
ORDER  BY b.ID, h.ID;

-- 3. Template of the fix, one statement per decided row (NOT run by this file):
--    UPDATE tblbankpostings SET transactionId = <receipt ID> WHERE ID = <bank row ID> AND transactionType = 1 AND (transactionId IS NULL OR transactionId = 0);

-- Task 12: every receipt row (and every group collection) has its own reference.
-- Idempotent. MariaDB 10.4+ (uses ADD COLUMN IF NOT EXISTS). Run after ledger_schema.sql (2.4); safe with the old code.
--
-- journal_lines had no reference field (only journal_entries.reference), so a row's reference could not reach the ledger
-- line it creates. The new column is the same length as tblbankpostings.reference (50), the smallest column a receipt row
-- reference is written to as a single value. NULL = the line has no reference of its own (every line saved before this
-- task, and every other transaction type): reports then show journal_entries.reference (the old header reference).
--
-- Nothing else changes: tblcontributions_details.paymentReference (varchar 255) already exists on every receipt row,
-- tblmmf.Reference (varchar 25) already exists for group collections (type 17), tblbankpostings.reference (50) and
-- tblbanktransactions_subaccounts.Reference (120) already exist.

ALTER TABLE journal_lines
    ADD COLUMN IF NOT EXISTS reference varchar(50) NULL AFTER memo;

-- Verify (expected: 1 row, varchar(50), YES)
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journal_lines' AND COLUMN_NAME = 'reference';

-- Rollback of scripts/sql/row_reference.sql (task 12). Idempotent.
-- Run only together with the old (pre task 12) code, or while no receipt / collection was saved with the new code:
-- dropping the column discards the row references stored on the ledger lines (the same references also stay on
-- tblcontributions_details.paymentReference, tblmmf.Reference and tblbankpostings.reference).
ALTER TABLE journal_lines DROP COLUMN IF EXISTS reference;

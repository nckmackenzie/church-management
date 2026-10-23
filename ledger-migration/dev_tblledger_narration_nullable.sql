-- GENERATED FROM DEV (task 8.9 follow-up 3) - REVIEW BEFORE USE. DEV ONLY: do NOT run on production unless LEDGER_LEGACY_WRITE is
-- ever turned on there (it must stay off).
-- Task 7 found that Ledger::mirrorLegacy() writes a nullable memo into tblledger.narration, which was NOT NULL in the old schema;
-- on dev the column was altered by hand (task7-ledger-install report). Dev definition today: narration varchar(255) DEFAULT NULL.
-- Idempotent.
ALTER TABLE tblledger MODIFY COLUMN narration VARCHAR(255) NULL DEFAULT NULL;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblledger' AND COLUMN_NAME = 'narration';

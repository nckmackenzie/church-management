-- Task 8.3b: bank transactions get a user-chosen crediting (cash-type) account.
-- Idempotent. MariaDB 10.4+ / 10.6. tblaccounttypes.ID is int(11).

-- 1. The chosen account for deposits (type 1) and withdrawals (type 2); NULL for every other type.
ALTER TABLE tblbanktransactions
    ADD COLUMN IF NOT EXISTS CashAccountId int(11) NULL AFTER TransferToId;

-- 2. Index + foreign key.
ALTER TABLE tblbanktransactions
    ADD INDEX IF NOT EXISTS IX_CashAccountId_BankTransactions (CashAccountId);
ALTER TABLE tblbanktransactions
    ADD CONSTRAINT FK_CashAccount_BankTransactions
    FOREIGN KEY IF NOT EXISTS (CashAccountId) REFERENCES tblaccounttypes (ID);

-- 3. The amount must hold cents (it was int(11), so 100.50 was stored as 100).
--    Same type as tblbanktransactions_subaccounts.Amount.
ALTER TABLE tblbanktransactions MODIFY COLUMN Amount decimal(18,2) NOT NULL;

-- 4. Backfill: the old code hard-coded 'cash at hand' (ID 34) on every deposit/withdrawal, so 34 is
--    what those rows actually posted. Only rows whose ledger entry (active or voided) really has a
--    line on 34 are filled; anything else is listed by the SELECT below and left NULL.
UPDATE tblbanktransactions t
SET t.CashAccountId = 34
WHERE t.TransactionTypeId IN (1, 2)
  AND t.CashAccountId IS NULL
  AND EXISTS (SELECT 1
              FROM journal_entries e
              JOIN journal_lines l ON l.journalEntryId = e.ID
              WHERE e.transactionTypeId = 13
                AND e.transactionId = t.ID
                AND e.congregationId = t.CongregationId
                AND l.accountId = 34);

-- Exceptions (must be reviewed by hand; the app refuses to edit them until a crediting account is chosen).
SELECT t.ID, t.CongregationId, t.TransactionTypeId, t.Deleted
FROM tblbanktransactions t
WHERE t.TransactionTypeId IN (1, 2) AND t.CashAccountId IS NULL;

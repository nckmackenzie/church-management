-- =====================================================================
-- journal_entries / journal_lines SCHEMA (task 3)
-- Target: MariaDB 10.6
-- Run once per environment, BEFORE the migration script.
-- DDL auto-commits in MariaDB, so this file is run on its own, not inside
-- the migration transaction.
-- =====================================================================


-- ---------------------------------------------------------------------
-- STEP 0 PRE-CHECK (run first, compare with the types used below)
-- FK columns must match the referenced column EXACTLY (INT vs BIGINT,
-- signed vs UNSIGNED). If they differ, change the marked columns below.
-- (information_schema returned nothing in phpMyAdmin, so SHOW is used.)
-- ---------------------------------------------------------------------
SHOW COLUMNS FROM tblaccounttypes     WHERE Field = 'ID';
SHOW COLUMNS FROM tbltransactiontypes WHERE Field = 'ID';
SHOW COLUMNS FROM tblledger
     WHERE Field IN ('ID', 'congregationId', 'transactionId', 'journalNo',
                     'transactionDate', 'reference', 'narration');


-- ---------------------------------------------------------------------
-- journal_entries: one row per transaction (header)
--
-- uq_je_source_active: only ONE active entry per source transaction.
--   activeKey is NULL for deleted entries, and NULLs never collide in a
--   unique index, so any number of voided entries may share a key
--   (journal 151: one voided, one active).
-- uq_je_journalno: journal numbers are never reused, deleted ones
--   included. journalNo is NULL for non-journals. The voided copy of
--   journal 151 is migrated with journalNo = NULL (transactionId keeps 151).
-- ---------------------------------------------------------------------
CREATE TABLE journal_entries (
  ID                INT           NOT NULL AUTO_INCREMENT,
  congregationId    INT           NOT NULL,              -- tblcongregation.ID is int(11): OK
  entryDate         DATE          NOT NULL,
  transactionTypeId INT           NOT NULL,              -- match tbltransactiontypes.ID
  transactionId     INT           NOT NULL,              -- match tblledger.transactionId
  journalNo         INT           NULL,                  -- match tblledger.journalNo
  reference         VARCHAR(255)  NULL,                  -- match tblledger.reference length
  description       VARCHAR(500)  NULL,                  -- migration: first non-blank line narration, else NULL
  deleted           TINYINT(1)    NOT NULL DEFAULT 0,
  activeKey         TINYINT AS (IF(deleted = 0, 1, NULL)) PERSISTENT,
  PRIMARY KEY (ID),
  UNIQUE KEY uq_je_source_active (congregationId, transactionTypeId, transactionId, activeKey),
  UNIQUE KEY uq_je_journalno     (congregationId, journalNo),
  KEY        ix_je_date          (congregationId, deleted, entryDate),
  KEY        ix_je_txntype       (transactionTypeId),
  CONSTRAINT fk_je_congregation
      FOREIGN KEY (congregationId) REFERENCES tblcongregation (ID),
  CONSTRAINT fk_je_txntype
      FOREIGN KEY (transactionTypeId) REFERENCES tbltransactiontypes (ID),
  CONSTRAINT chk_je_deleted   CHECK (deleted IN (0, 1)),
  CONSTRAINT chk_je_journalno CHECK (journalNo IS NULL OR transactionTypeId = 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- journal_lines: one row per debit or credit
--
-- Balanced entries and "posting accounts only" are enforced by
-- postJournalEntry and the post-migration checks, not by constraints.
-- legacyLedgerId traces each migrated line to its tblledger row and makes
-- the migration re-runnable. NULL for lines posted by the new code.
-- ---------------------------------------------------------------------
CREATE TABLE journal_lines (
  ID             INT                     NOT NULL AUTO_INCREMENT,
  journalEntryId INT                     NOT NULL,
  accountId      INT                     NOT NULL,       -- match tblaccounttypes.ID
  dc             ENUM('DEBIT','CREDIT')  NOT NULL,
  amount         DECIMAL(18,2)           NOT NULL,
  memo           VARCHAR(500)            NULL,           -- match tblledger.narration length
  lineNumber     SMALLINT UNSIGNED       NOT NULL,
  legacyLedgerId INT                     NULL,           -- match tblledger.ID
  PRIMARY KEY (ID),
  UNIQUE KEY uq_jl_line    (journalEntryId, lineNumber),
  UNIQUE KEY uq_jl_legacy  (legacyLedgerId),
  KEY        ix_jl_account (accountId, journalEntryId),
  CONSTRAINT fk_jl_entry
      FOREIGN KEY (journalEntryId) REFERENCES journal_entries (ID) ON DELETE CASCADE,
  CONSTRAINT fk_jl_account
      FOREIGN KEY (accountId) REFERENCES tblaccounttypes (ID),
  CONSTRAINT chk_jl_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- journal_number_seq: next journal number per congregation
-- postJournalEntry locks the row (SELECT ... FOR UPDATE), increments it,
-- and uses the new value, all inside the posting transaction. Avoids
-- MAX()+1, which can hand out a number again after a hard delete.
-- Seeded by the migration script from MAX(journalNo) in tblledger,
-- deleted rows included, at cutover time.
-- ---------------------------------------------------------------------
CREATE TABLE journal_number_seq (
  congregationId INT NOT NULL,                           -- tblcongregation.ID is int(11): OK
  lastJournalNo  INT NOT NULL,
  PRIMARY KEY (congregationId),
  CONSTRAINT fk_jns_congregation
      FOREIGN KEY (congregationId) REFERENCES tblcongregation (ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- VERIFY
-- ---------------------------------------------------------------------
SHOW CREATE TABLE journal_entries;
SHOW CREATE TABLE journal_lines;
SHOW CREATE TABLE journal_number_seq;

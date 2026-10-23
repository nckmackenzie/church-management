-- =====================================================================
-- tblledger PRE-MIGRATION AUDIT
-- Target: MariaDB 10.6
-- READ-ONLY: only SELECTs, plus session-only TEMPORARY tables in A10.
-- No real table is changed. Safe to run anywhere, but
-- ideally run against a fresh copy of production data.
--
-- Run each query on its own and save the results.
-- Queries marked [BLOCKER] must return 0 rows before migrating.
-- Queries marked [INFO] need a decision, not necessarily a fix.
--
-- Order on each environment: run ledger_fixes.sql first, then this audit.
--
-- Grouping key for a journal entry:
--   congregationId + transactionType + transactionId + deleted
--   (A3/A4 below group without `deleted` on purpose, to surface reused keys)
-- Account name matching: tblledger.account -> tblaccounttypes.accountType,
--   restricted to the same congregation or shared accounts (congregationId = 0).
--   Match priority: active congregation account > active shared account
--   > deleted congregation account > deleted shared account.
-- Chart of accounts design (non-negotiable):
--   root (IDs 1-6, parentId = 0)
--     -> top-level account (directly under a root): always REPORTING
--        -> any depth below: POSTING if it has no non-deleted children,
--           otherwise REPORTING
--   Only posting accounts may be posted to; reports show top-level
--   accounts with recursive drill-down.
-- =====================================================================


-- ---------------------------------------------------------------------
-- A0 [INFO] Baseline size per congregation
-- ---------------------------------------------------------------------
SELECT  congregationId,
        COUNT(*)                                        AS ledgerRows,
        COUNT(DISTINCT transactionType, transactionId)  AS entryGroups,
        SUM(deleted)                                    AS deletedRows,
        SUM(debit)                                      AS totalDebit,
        SUM(credit)                                     AS totalCredit
FROM    tblledger
GROUP BY congregationId
ORDER BY congregationId;


-- ---------------------------------------------------------------------
-- A1 [INFO] Engine and collation of tables involved
-- Foreign keys need InnoDB on both sides; the new tables will copy this collation.
-- (Returns nothing in phpMyAdmin on the host: use SHOW TABLE STATUS instead.)
-- ---------------------------------------------------------------------
SELECT  TABLE_NAME, ENGINE, TABLE_COLLATION
FROM    information_schema.TABLES
WHERE   TABLE_SCHEMA = DATABASE()
  AND   TABLE_NAME IN ('tblledger', 'tblaccounttypes', 'tbltransactiontypes',
                       'tblcongregation');


-- ---------------------------------------------------------------------
-- A2 [BLOCKER] Account names that can't be mapped to exactly one account
--   UNMAPPED  = no account with that name (likely renamed after posting)
--   AMBIGUOUS = more than one account at the same priority level
-- ---------------------------------------------------------------------
WITH names AS (
    SELECT  congregationId, account,
            COUNT(*) AS rowCount, SUM(debit) AS totalDebit, SUM(credit) AS totalCredit
    FROM    tblledger
    GROUP BY congregationId, account
),
candidates AS (
    SELECT  n.congregationId, n.account, a.ID AS accountTblId,
            (a.deleted * 2) + (a.congregationId = 0) AS rnk
    FROM    names n
    JOIN    tblaccounttypes a
            ON  a.accountType = n.account
            AND a.congregationId IN (n.congregationId, 0)
),
ranked AS (
    SELECT  c.*, MIN(rnk) OVER (PARTITION BY congregationId, account) AS bestRnk
    FROM    candidates c
)
SELECT  n.congregationId, n.account, n.rowCount, n.totalDebit, n.totalCredit,
        COUNT(r.accountTblId)                                         AS matches,
        IF(COUNT(r.accountTblId) = 0, 'UNMAPPED', 'AMBIGUOUS')        AS status,
        GROUP_CONCAT(r.accountTblId ORDER BY r.accountTblId)          AS candidateIds
FROM    names n
LEFT JOIN ranked r
        ON  r.congregationId = n.congregationId
        AND r.account        = n.account
        AND r.rnk            = r.bestRnk
GROUP BY n.congregationId, n.account, n.rowCount, n.totalDebit, n.totalCredit
HAVING  COUNT(r.accountTblId) <> 1
ORDER BY status, n.congregationId, n.account;


-- ---------------------------------------------------------------------
-- A3 [BLOCKER] Unbalanced entry groups (debits <> credits)
-- ---------------------------------------------------------------------
SELECT  congregationId, transactionType, transactionId,
        MIN(transactionDate)        AS transactionDate,
        MAX(deleted)                AS deleted,
        COUNT(*)                    AS lineCount,
        SUM(debit)                  AS totalDebit,
        SUM(credit)                 AS totalCredit,
        SUM(debit) - SUM(credit)    AS difference
FROM    tblledger
GROUP BY congregationId, transactionType, transactionId
HAVING  SUM(debit) <> SUM(credit)
ORDER BY congregationId, transactionType, transactionId;


-- ---------------------------------------------------------------------
-- A4 Group consistency
--   [BLOCKER] MIXED_DATES, PARTIAL_DELETE, MIXED_ISJOURNAL, MIXED_JOURNALNO
--   [INFO]    MIXED_REFERENCE (an entry header holds one reference)
-- ---------------------------------------------------------------------
SELECT  congregationId, transactionType, transactionId, COUNT(*) AS lineCount,
        CONCAT_WS(', ',
            IF(COUNT(DISTINCT transactionDate) > 1,              'MIXED_DATES',     NULL),
            IF(MIN(deleted) <> MAX(deleted),                     'PARTIAL_DELETE',  NULL),
            IF(COUNT(DISTINCT isJournal) > 1,                    'MIXED_ISJOURNAL', NULL),
            IF(COUNT(DISTINCT COALESCE(journalNo, -1)) > 1,      'MIXED_JOURNALNO', NULL),
            IF(COUNT(DISTINCT COALESCE(reference, '')) > 1,      'MIXED_REFERENCE', NULL)
        ) AS flags
FROM    tblledger
GROUP BY congregationId, transactionType, transactionId
HAVING  flags <> ''
ORDER BY congregationId, transactionType, transactionId;


-- ---------------------------------------------------------------------
-- A5 [BLOCKER] Lines that don't fit the DEBIT/CREDIT + positive amount model
--   NEGATIVE   = negative debit or credit
--   BOTH_SIDES = debit and credit both > 0 on one row
--   ZERO       = both 0 (could simply be skipped; decide after seeing count)
-- ---------------------------------------------------------------------
SELECT  ID, congregationId, transactionType, transactionId, transactionDate,
        account, debit, credit,
        CASE
            WHEN debit < 0 OR credit < 0   THEN 'NEGATIVE'
            WHEN debit > 0 AND credit > 0  THEN 'BOTH_SIDES'
            ELSE 'ZERO'
        END AS issue
FROM    tblledger
WHERE   debit < 0 OR credit < 0
   OR   (debit > 0 AND credit > 0)
   OR   (debit = 0 AND credit = 0)
ORDER BY issue, congregationId, transactionType, transactionId;


-- ---------------------------------------------------------------------
-- A6 [BLOCKER] Journal flag consistency
-- Journals: transactionType = 5, isJournal = 1, transactionId = journalNo
-- ---------------------------------------------------------------------
SELECT  ID, congregationId, transactionType, transactionId, isJournal, journalNo
FROM    tblledger
WHERE   (transactionType = 5 AND (isJournal <> 1 OR NOT (journalNo <=> transactionId)))
   OR   (transactionType <> 5 AND isJournal = 1)
ORDER BY congregationId, transactionId;


-- ---------------------------------------------------------------------
-- A7 [BLOCKER] Unknown transaction types / account type ids / congregations
-- (journal_entries has foreign keys to tbltransactiontypes and tblcongregation)
-- ---------------------------------------------------------------------
SELECT  'transactionType not in tbltransactiontypes' AS issue,
        l.transactionType AS value, COUNT(*) AS rowCount
FROM    tblledger l
LEFT JOIN tbltransactiontypes t ON t.ID = l.transactionType
WHERE   t.ID IS NULL
GROUP BY l.transactionType
UNION ALL
SELECT  'accountId not in 1-6', accountId, COUNT(*)
FROM    tblledger
WHERE   accountId NOT IN (1, 2, 3, 4, 5, 6)
GROUP BY accountId
UNION ALL
SELECT  'congregationId not in tblcongregation', l.congregationId, COUNT(*)
FROM    tblledger l
LEFT JOIN tblcongregation c ON c.ID = l.congregationId
WHERE   c.ID IS NULL
GROUP BY l.congregationId;


-- ---------------------------------------------------------------------
-- A8 [INFO] Mapped accounts whose stored ledger data disagrees with tblaccounttypes
--   TYPE_MISMATCH    = tblledger.accountId <> account's accountTypeId
--                      (reports will move to the account's type -> figures shift)
--   PARENT_MISMATCH  = tblledger.parentaccount <> account's current parent name
--                      (reports grouped by parent will shift)
--   POSTED_TO_PARENT = posting made to a reporting account (isSubCategory = 0)
--   ACCOUNT_DELETED / ACCOUNT_INACTIVE = maps only to a deleted/inactive account
-- Only uniquely-mapped names appear here (A2 covers the rest).
-- ---------------------------------------------------------------------
WITH candidates AS (
    SELECT  n.congregationId, n.account, a.ID AS accountTblId,
            (a.deleted * 2) + (a.congregationId = 0) AS rnk
    FROM    (SELECT DISTINCT congregationId, account FROM tblledger) n
    JOIN    tblaccounttypes a
            ON  a.accountType = n.account
            AND a.congregationId IN (n.congregationId, 0)
),
ranked AS (
    SELECT  c.*,
            MIN(rnk) OVER (PARTITION BY congregationId, account)      AS bestRnk,
            COUNT(*) OVER (PARTITION BY congregationId, account, rnk) AS cntAtRnk
    FROM    candidates c
),
acct_map AS (
    SELECT congregationId, account, accountTblId
    FROM   ranked
    WHERE  rnk = bestRnk AND cntAtRnk = 1
)
SELECT  l.congregationId, l.account,
        l.accountId         AS ledgerTypeId,
        a.accountTypeId     AS accountTypeId,
        l.parentaccount     AS ledgerParent,
        p.accountType       AS currentParent,
        a.ID                AS mappedAccountId,
        COUNT(*)            AS rowCount,
        SUM(l.debit)        AS totalDebit,
        SUM(l.credit)       AS totalCredit,
        CONCAT_WS(', ',
            IF(NOT (l.accountId <=> a.accountTypeId),                         'TYPE_MISMATCH',    NULL),
            IF(COALESCE(l.parentaccount, '') <> COALESCE(p.accountType, ''),  'PARENT_MISMATCH',  NULL),
            IF(a.isSubCategory = 0,                                           'POSTED_TO_PARENT', NULL),
            IF(a.deleted = 1,                                                 'ACCOUNT_DELETED',  NULL),
            IF(a.active = 0,                                                  'ACCOUNT_INACTIVE', NULL)
        ) AS flags
FROM    tblledger l
JOIN    acct_map m          ON m.congregationId = l.congregationId AND m.account = l.account
JOIN    tblaccounttypes a   ON a.ID = m.accountTblId
LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
GROUP BY l.congregationId, l.account, l.accountId, a.accountTypeId,
         l.parentaccount, p.accountType, a.ID, a.isSubCategory, a.deleted, a.active
HAVING  flags <> ''
ORDER BY l.congregationId, l.account;


-- ---------------------------------------------------------------------
-- A9 [BLOCKER] Ledger postings to accounts that are not posting accounts
--   TOP_LEVEL_WITH_CHILDREN = account directly under a root, posted to
--   TOP_LEVEL_NO_CHILDREN   = account directly under a root with no
--                             children (needs a child to post to)
--   PARENT_WITH_CHILDREN    = deeper reporting account, posted to
--   ROOT / ORPHAN           = chart structure problem (ORPHAN = its parent
--                             chain never reaches a root, incl. cycles)
-- 'cash at bank' is checked separately in A10 (it is remapped to the banks).
-- Each row needs a target posting account before migrating.
-- ---------------------------------------------------------------------
WITH RECURSIVE tree AS (
    SELECT  ID, 0 AS depth
    FROM    tblaccounttypes
    WHERE   parentId = 0
    UNION ALL
    SELECT  a.ID, t.depth + 1
    FROM    tblaccounttypes a
    JOIN    tree t ON a.parentId = t.ID
    WHERE   t.depth < 20
),
candidates AS (
    SELECT  n.congregationId, n.account, a.ID AS accountTblId,
            (a.deleted * 2) + (a.congregationId = 0) AS rnk
    FROM    (SELECT DISTINCT congregationId, account FROM tblledger) n
    JOIN    tblaccounttypes a
            ON  a.accountType = n.account
            AND a.congregationId IN (n.congregationId, 0)
),
ranked AS (
    SELECT  c.*,
            MIN(rnk) OVER (PARTITION BY congregationId, account)      AS bestRnk,
            COUNT(*) OVER (PARTITION BY congregationId, account, rnk) AS cntAtRnk
    FROM    candidates c
),
acct_map AS (
    SELECT congregationId, account, accountTblId
    FROM   ranked
    WHERE  rnk = bestRnk AND cntAtRnk = 1
),
used AS (
    SELECT  m.accountTblId,
            COUNT(*)                AS rowCount,
            SUM(l.debit)            AS totalDebit,
            SUM(l.credit)           AS totalCredit,
            MIN(l.transactionDate)  AS firstDate,
            MAX(l.transactionDate)  AS lastDate,
            GROUP_CONCAT(DISTINCT l.transactionType ORDER BY l.transactionType) AS transactionTypes
    FROM    tblledger l
    JOIN    acct_map m ON m.congregationId = l.congregationId AND m.account = l.account
    WHERE   l.account <> 'cash at bank'
    GROUP BY m.accountTblId
),
lvl AS (
    SELECT  a.ID, a.accountType, a.accountTypeId, p.accountType AS parentName,
            t.depth,
            (SELECT COUNT(*) FROM tblaccounttypes c
              WHERE c.parentId = a.ID AND c.deleted = 0) AS childCount,
            a.parentId
    FROM    tblaccounttypes a
    LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
    LEFT JOIN tree t            ON t.ID = a.ID
)
SELECT  v.ID, v.accountType, v.accountTypeId, v.parentName, v.depth, v.childCount,
        CASE
            WHEN v.parentId = 0                       THEN 'ROOT'
            WHEN v.depth IS NULL                      THEN 'ORPHAN'
            WHEN v.depth = 1 AND v.childCount > 0     THEN 'TOP_LEVEL_WITH_CHILDREN'
            WHEN v.depth = 1                          THEN 'TOP_LEVEL_NO_CHILDREN'
            ELSE 'PARENT_WITH_CHILDREN'
        END AS issue,
        u.rowCount, u.totalDebit, u.totalCredit, u.firstDate, u.lastDate, u.transactionTypes
FROM    used u
JOIN    lvl v ON v.ID = u.accountTblId
WHERE   NOT (v.depth >= 2 AND v.childCount = 0)
ORDER BY v.accountTypeId, v.accountType;


-- ---------------------------------------------------------------------
-- A10 'cash at bank' lines -> actual bank account (tblaccounttypes, isBank = 1)
-- RUN THIS WHOLE A10 BLOCK IN ONE GO (one phpMyAdmin SQL box). It builds
-- session-only TEMPORARY tables (gone when the session ends; no real table
-- is changed), then shows the summary (A10a) and the blocker details (A10b).
--
-- Each 'cash at bank' line is matched to tblbankpostings on congregation +
-- transactionType + transactionId + same side and amount (tolerance 0.50,
-- because bankpostings uses FLOAT). Postings in the same deleted state as the
-- ledger line are preferred.
--   MATCHED               = exactly one bank found            -> use it
--   FALLBACK_105          = no match, dated before 2026-05-15 -> co-op bank (105)
--   [BLOCKER] AMBIGUOUS        = more than one bank matches
--   [BLOCKER] BANK_NOT_ISBANK  = bankId is not an isBank account
--   [BLOCKER] NO_MATCH_AFTER_CUTOFF = no match on/after 2026-05-15
-- If almost nothing is MATCHED, the debit/credit sides may be reversed
-- between the two tables - tell Claude before going further.
-- ---------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_cab;
CREATE TEMPORARY TABLE tmp_cab (PRIMARY KEY (ID))
SELECT  ID, congregationId, transactionType, transactionId, transactionDate,
        debit, credit, deleted, narration
FROM    tblledger
WHERE   account = 'cash at bank';

DROP TEMPORARY TABLE IF EXISTS tmp_bp;
CREATE TEMPORARY TABLE tmp_bp (KEY k_txn (congregationId, transactionType, transactionId))
SELECT  congregationId, transactionType, transactionId, bankId, deleted, debit, credit
FROM    tblbankpostings
WHERE   transactionId IS NOT NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_cand;
CREATE TEMPORARY TABLE tmp_cand (KEY k_id (ID))
SELECT  c.ID, b.bankId, (b.deleted <> c.deleted) AS rnk
FROM    tmp_cab c
JOIN    tmp_bp b
        ON  b.congregationId  = c.congregationId
        AND b.transactionType = c.transactionType
        AND b.transactionId   = c.transactionId
        AND ABS(b.debit  - c.debit)  < 0.5
        AND ABS(b.credit - c.credit) < 0.5;

DROP TEMPORARY TABLE IF EXISTS tmp_best;
CREATE TEMPORARY TABLE tmp_best (PRIMARY KEY (ID))
SELECT  ID, MIN(rnk) AS bestRnk
FROM    tmp_cand
GROUP BY ID;

DROP TEMPORARY TABLE IF EXISTS tmp_cab_map;
CREATE TEMPORARY TABLE tmp_cab_map (PRIMARY KEY (ID))
SELECT  r.*,
        CASE
            WHEN r.banks > 1                        THEN 'AMBIGUOUS'
            WHEN r.banks = 1 AND a.ID IS NULL       THEN 'BANK_NOT_ISBANK'
            WHEN r.banks = 1                        THEN 'MATCHED'
            WHEN r.transactionDate < '2026-05-15'   THEN 'FALLBACK_105'
            ELSE 'NO_MATCH_AFTER_CUTOFF'
        END AS status
FROM (
    SELECT  c.ID, c.congregationId, c.transactionType, c.transactionId,
            c.transactionDate, c.debit, c.credit, c.deleted, c.narration,
            COUNT(DISTINCT k.bankId)        AS banks,
            GROUP_CONCAT(DISTINCT k.bankId) AS bankIds,
            MIN(k.bankId)                   AS bankId
    FROM    tmp_cab c
    LEFT JOIN tmp_best b ON b.ID = c.ID
    LEFT JOIN tmp_cand k ON k.ID = c.ID AND k.rnk = b.bestRnk
    GROUP BY c.ID, c.congregationId, c.transactionType, c.transactionId,
             c.transactionDate, c.debit, c.credit, c.deleted, c.narration
) r
LEFT JOIN tblaccounttypes a ON a.ID = r.bankId AND a.isBank = 1;

-- A10a summary
SELECT  status, bankId, COUNT(*) AS lineCount, SUM(debit) AS totalDebit, SUM(credit) AS totalCredit
FROM    tmp_cab_map
GROUP BY status, bankId
ORDER BY status, bankId;

-- A10b [BLOCKER] details
SELECT  status, ID AS ledgerId, transactionType, transactionId, transactionDate,
        debit, credit, deleted, bankIds, narration
FROM    tmp_cab_map
WHERE   status IN ('AMBIGUOUS', 'BANK_NOT_ISBANK', 'NO_MATCH_AFTER_CUTOFF')
ORDER BY status, transactionDate, ledgerId;


-- ---------------------------------------------------------------------
-- A11 [BLOCKER] Chart of accounts structure (whole tblaccounttypes, not deleted)
--   FLAG_SHOULD_BE_1 / FLAG_SHOULD_BE_0 = isSubCategory doesn't match the
--                                          chart rule (fixed generically by F4)
--   ORPHAN = parent chain never reaches a root (incl. cycles); manual fix
-- ---------------------------------------------------------------------
WITH RECURSIVE tree AS (
    SELECT  ID, 0 AS depth
    FROM    tblaccounttypes
    WHERE   parentId = 0
    UNION ALL
    SELECT  a.ID, t.depth + 1
    FROM    tblaccounttypes a
    JOIN    tree t ON a.parentId = t.ID
    WHERE   t.depth < 20
),
lvl AS (
    SELECT  a.ID, a.accountType, a.accountTypeId, a.isSubCategory, a.congregationId,
            a.parentId, p.accountType AS parentName, t.depth,
            (SELECT COUNT(*) FROM tblaccounttypes c
              WHERE c.parentId = a.ID AND c.deleted = 0) AS childCount
    FROM    tblaccounttypes a
    LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
    LEFT JOIN tree t            ON t.ID = a.ID
    WHERE   a.deleted = 0
)
SELECT  ID, accountType, accountTypeId, parentName, isSubCategory, childCount,
        congregationId, depth,
        CASE
            WHEN parentId = 0                                   THEN NULL
            WHEN depth IS NULL                                  THEN 'ORPHAN'
            WHEN (depth = 1 OR childCount > 0) AND isSubCategory <> 0 THEN 'FLAG_SHOULD_BE_0'
            WHEN depth >= 2 AND childCount = 0 AND isSubCategory <> 1 THEN 'FLAG_SHOULD_BE_1'
        END AS issue
FROM    lvl
HAVING  issue IS NOT NULL
ORDER BY issue, accountTypeId, accountType;

-- =====================================================================
-- POST-MIGRATION VERIFICATION (task 5)
-- Target: MariaDB 10.4+ (local 10.4.32, production 10.6.28)
-- READ-ONLY: SELECTs only. Run each query on its own (phpMyAdmin).
--
-- These checks are independent of the ones inside migrate_ledger.php:
-- they compare every migrated line with its source row in tblledger.
--
-- Queries marked [BLOCKER] must return 0 rows (or the stated result).
-- Queries marked [COMPARE] are for comparing with the old reports.
--
-- PRODUCTION / after the rename: replace `tblledger` with
-- `tblledger_legacy` in V1, V2, V3 and V7.
--
-- Known, expected exceptions:
--   * expenses 724 and 727 (ledger rows 22133, 22134): deleted, one-legged,
--     not migrated
--   * the voided copy of journal 151: journalNo NULL
-- =====================================================================


-- ---------------------------------------------------------------------
-- V0 [INFO] Counts
-- Expected on local (2026-10-20): 11,734 entries (11,668 active, 66 deleted),
-- 24,126 lines, 24,128 ledger rows.
-- ---------------------------------------------------------------------
SELECT  (SELECT COUNT(*) FROM journal_entries)                  AS entries,
        (SELECT COUNT(*) FROM journal_entries WHERE deleted = 0) AS activeEntries,
        (SELECT COUNT(*) FROM journal_entries WHERE deleted = 1) AS deletedEntries,
        (SELECT COUNT(*) FROM journal_lines)                    AS lines,
        (SELECT COUNT(*) FROM journal_lines
          WHERE legacyLedgerId IS NOT NULL)                     AS migratedLines,
        (SELECT COUNT(*) FROM tblledger)                        AS ledgerRows;


-- ---------------------------------------------------------------------
-- V1 [BLOCKER] Rows missing on either side
--   NOT_MIGRATED : ledger row with no journal line
--                  -> expected exactly 2 rows: 22133 and 22134
--   NO_SOURCE    : migrated line whose ledger row doesn't exist -> 0
-- ---------------------------------------------------------------------
SELECT  'NOT_MIGRATED' AS issue, l.ID AS ledgerId, l.transactionType, l.transactionId,
        l.deleted, l.debit, l.credit
FROM    tblledger l
LEFT JOIN journal_lines jl ON jl.legacyLedgerId = l.ID
WHERE   jl.ID IS NULL
UNION ALL
SELECT  'NO_SOURCE', jl.legacyLedgerId, NULL, NULL, NULL, NULL, NULL
FROM    journal_lines jl
LEFT JOIN tblledger l ON l.ID = jl.legacyLedgerId
WHERE   jl.legacyLedgerId IS NOT NULL
  AND   l.ID IS NULL
ORDER BY issue, ledgerId;


-- ---------------------------------------------------------------------
-- V2 [BLOCKER] Line-by-line comparison with tblledger -> 0 rows
-- Checks congregation, transaction type/id, date, deleted flag, side and
-- amount, memo, journal number, and line order.
-- ---------------------------------------------------------------------
SELECT  jl.legacyLedgerId, e.ID AS entryId, e.transactionTypeId, e.transactionId,
        CONCAT_WS(', ',
            IF(e.congregationId    <> l.congregationId,             'CONGREGATION', NULL),
            IF(e.transactionTypeId <> l.transactionType
               OR e.transactionId  <> l.transactionId,              'SOURCE',       NULL),
            IF(e.entryDate         <> DATE(l.transactionDate),      'DATE',         NULL),
            IF(e.deleted           <> l.deleted,                    'DELETED',      NULL),
            IF(jl.amount <> IF(jl.dc = 'DEBIT', l.debit, l.credit)
               OR IF(jl.dc = 'DEBIT', l.credit, l.debit) <> 0,      'AMOUNT',       NULL),
            IF(NOT (jl.memo <=> IF(TRIM(l.narration) = '', NULL, l.narration)),
                                                                    'MEMO',         NULL),
            IF(NOT (e.journalNo <=> IF(l.transactionType = 5, l.journalNo, NULL))
               AND NOT (e.deleted = 1 AND e.journalNo IS NULL
                        AND e.description LIKE '[voided duplicate of journal%'),
                                                                    'JOURNAL_NO',   NULL)
        ) AS issues
FROM    journal_lines jl
JOIN    journal_entries e ON e.ID = jl.journalEntryId
JOIN    tblledger l       ON l.ID = jl.legacyLedgerId
HAVING  issues <> ''
ORDER BY jl.legacyLedgerId;

-- V2b [BLOCKER] Line numbers follow the ledger row order -> 0 rows
SELECT  journalEntryId, legacyLedgerId, lineNumber, expectedLine
FROM (
    SELECT  journalEntryId, legacyLedgerId, lineNumber,
            ROW_NUMBER() OVER (PARTITION BY journalEntryId ORDER BY legacyLedgerId) AS expectedLine
    FROM    journal_lines
    WHERE   legacyLedgerId IS NOT NULL
) x
WHERE   lineNumber <> expectedLine;


-- ---------------------------------------------------------------------
-- V3 [BLOCKER] Account mapping -> 0 rows
-- Each line's account must have the ledger row's account name (same
-- congregation or shared), except 'cash at bank' lines, which must be on
-- a bank account (isBank = 1).
-- ---------------------------------------------------------------------
SELECT  jl.legacyLedgerId, l.account AS ledgerAccount,
        a.ID AS accountId, a.accountType AS mappedAccount, a.isBank, a.congregationId
FROM    journal_lines jl
JOIN    tblledger l       ON l.ID = jl.legacyLedgerId
JOIN    tblaccounttypes a ON a.ID = jl.accountId
WHERE   NOT (
            (l.account = 'cash at bank' AND a.isBank = 1)
         OR (l.account <> 'cash at bank' AND a.accountType = l.account
             AND a.congregationId IN (l.congregationId, 0))
        )
ORDER BY jl.legacyLedgerId;

-- V3b [INFO] 'cash at bank' lines per bank
-- Expected: 105 -> 9,909 (9,649 matched + 260 fallback); 195 -> 433
SELECT  jl.accountId, a.accountType, COUNT(*) AS lineCount
FROM    journal_lines jl
JOIN    tblledger l       ON l.ID = jl.legacyLedgerId
JOIN    tblaccounttypes a ON a.ID = jl.accountId
WHERE   l.account = 'cash at bank'
GROUP BY jl.accountId, a.accountType;


-- ---------------------------------------------------------------------
-- V4 [BLOCKER] Entry integrity -> 0 rows
--   UNBALANCED / NO_LINES / SINGLE_LINE
-- ---------------------------------------------------------------------
SELECT  e.ID AS entryId, e.transactionTypeId, e.transactionId, e.deleted,
        COUNT(jl.ID)                                     AS lineCount,
        SUM(IF(jl.dc = 'DEBIT',  jl.amount, 0))          AS totalDebit,
        SUM(IF(jl.dc = 'CREDIT', jl.amount, 0))          AS totalCredit,
        CASE
            WHEN COUNT(jl.ID) = 0 THEN 'NO_LINES'
            WHEN COUNT(jl.ID) = 1 THEN 'SINGLE_LINE'
            ELSE 'UNBALANCED'
        END AS issue
FROM    journal_entries e
LEFT JOIN journal_lines jl ON jl.journalEntryId = e.ID
GROUP BY e.ID, e.transactionTypeId, e.transactionId, e.deleted
HAVING  COUNT(jl.ID) < 2
    OR  SUM(IF(jl.dc = 'DEBIT', jl.amount, 0)) <> SUM(IF(jl.dc = 'CREDIT', jl.amount, 0))
ORDER BY issue, e.ID;


-- ---------------------------------------------------------------------
-- V5 [BLOCKER] Lines on accounts that are not posting accounts -> 0 rows
-- Posting account: depth >= 2 below a root, no non-deleted children.
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
)
SELECT  a.ID, a.accountType, t.depth, a.isSubCategory,
        (SELECT COUNT(*) FROM tblaccounttypes c
          WHERE c.parentId = a.ID AND c.deleted = 0) AS childCount,
        COUNT(*) AS lineCount
FROM    journal_lines jl
JOIN    tblaccounttypes a ON a.ID = jl.accountId
LEFT JOIN tree t          ON t.ID = a.ID
GROUP BY a.ID, a.accountType, t.depth, a.isSubCategory
HAVING  t.depth IS NULL OR t.depth < 2 OR childCount > 0 OR a.isSubCategory <> 1;


-- ---------------------------------------------------------------------
-- V6 [BLOCKER] Journal number sequence -> 0 rows
-- lastJournalNo must be >= every journal number already used.
-- ---------------------------------------------------------------------
SELECT  e.congregationId, MAX(e.journalNo) AS maxJournalNo, s.lastJournalNo
FROM    journal_entries e
LEFT JOIN journal_number_seq s ON s.congregationId = e.congregationId
WHERE   e.journalNo IS NOT NULL
GROUP BY e.congregationId, s.lastJournalNo
HAVING  s.lastJournalNo IS NULL OR s.lastJournalNo < MAX(e.journalNo);


-- ---------------------------------------------------------------------
-- V7 [COMPARE] Net movement per financial year and account type,
-- old (tblledger.accountId) vs new (tblaccounttypes.accountTypeId).
-- Active entries only. Net = debit - credit. FY runs Oct-Sep and is
-- labelled by the year it ends (FY2025 = Oct 2024 - Sep 2025).
-- Differences are expected ONLY where the ledger stored a different type
-- than the account now has (A8 TYPE_MISMATCH); V7b lists them, and its
-- totals per FY and type must equal the differences here.
-- ---------------------------------------------------------------------
SELECT  fy, typeId,
        SUM(oldNet)               AS oldNet,
        SUM(newNet)               AS newNet,
        SUM(newNet) - SUM(oldNet) AS difference
FROM (
    SELECT  YEAR(transactionDate) + (MONTH(transactionDate) >= 10) AS fy,
            accountId AS typeId, debit - credit AS oldNet, 0 AS newNet
    FROM    tblledger
    WHERE   deleted = 0
    UNION ALL
    SELECT  YEAR(e.entryDate) + (MONTH(e.entryDate) >= 10),
            a.accountTypeId, 0, IF(jl.dc = 'DEBIT', jl.amount, -jl.amount)
    FROM    journal_lines jl
    JOIN    journal_entries e ON e.ID = jl.journalEntryId AND e.deleted = 0
    JOIN    tblaccounttypes a ON a.ID = jl.accountId
) x
GROUP BY fy, typeId
ORDER BY fy, typeId;

-- V7b [COMPARE] Lines whose stored type differs from the account's type
SELECT  YEAR(e.entryDate) + (MONTH(e.entryDate) >= 10) AS fy,
        a.ID AS accountId, a.accountType,
        l.accountId                                  AS oldTypeId,
        a.accountTypeId                              AS newTypeId,
        COUNT(*)                                     AS lineCount,
        SUM(IF(jl.dc = 'DEBIT', jl.amount, -jl.amount)) AS net
FROM    journal_lines jl
JOIN    journal_entries e ON e.ID = jl.journalEntryId AND e.deleted = 0
JOIN    tblledger l       ON l.ID = jl.legacyLedgerId
JOIN    tblaccounttypes a ON a.ID = jl.accountId
WHERE   l.accountId <> a.accountTypeId
GROUP BY fy, a.ID, a.accountType, l.accountId, a.accountTypeId
ORDER BY fy, a.accountType;


-- ---------------------------------------------------------------------
-- V8 [BLOCKER] Balance sheet equation at a date -> difference = 0.00
-- Assets = Liabilities + Equity + accumulated surplus (types 1, 2, 5).
-- Set @asAt first (run both statements together).
-- ---------------------------------------------------------------------
SET @asAt = CURDATE();

SELECT  @asAt AS asAt,
        SUM(IF(a.accountTypeId = 3,         n.dr - n.cr, 0)) AS assets,
        SUM(IF(a.accountTypeId = 4,         n.cr - n.dr, 0)) AS liabilities,
        SUM(IF(a.accountTypeId = 6,         n.cr - n.dr, 0)) AS equity,
        SUM(IF(a.accountTypeId IN (1, 2, 5), n.cr - n.dr, 0)) AS accumulatedSurplus,
        SUM(IF(a.accountTypeId = 3, n.dr - n.cr, 0))
          - SUM(IF(a.accountTypeId IN (1, 2, 4, 5, 6), n.cr - n.dr, 0)) AS difference
FROM (
    SELECT  jl.accountId,
            SUM(IF(jl.dc = 'DEBIT',  jl.amount, 0)) AS dr,
            SUM(IF(jl.dc = 'CREDIT', jl.amount, 0)) AS cr
    FROM    journal_lines jl
    JOIN    journal_entries e ON e.ID = jl.journalEntryId
    WHERE   e.deleted = 0
      AND   e.entryDate <= @asAt
    GROUP BY jl.accountId
) n
JOIN    tblaccounttypes a ON a.ID = n.accountId;


-- ---------------------------------------------------------------------
-- V9 [COMPARE] Balance sheet by top-level account at @asAt
-- Run the SET and the query together in ONE phpMyAdmin box: each box is
-- a new connection, so @asAt from V8 is gone (NULL = no rows).
-- Compare with the existing balance sheet
-- report (sp_BalanceSheet_Assets / _LiabilitiesEquity) for the same date.
-- Balances are shown on the normal side (assets debit, others credit).
-- Expected differences: accounts in V7b, and the banks now rolling up
-- under cash and cash equivalents (22) via cash at bank (33).
-- ---------------------------------------------------------------------
SET @asAt = CURDATE();

WITH RECURSIVE anc AS (
    SELECT  a.ID, a.ID AS topId
    FROM    tblaccounttypes a
    JOIN    tblaccounttypes r ON r.ID = a.parentId AND r.parentId = 0
    UNION ALL
    SELECT  c.ID, t.topId
    FROM    tblaccounttypes c
    JOIN    anc t ON c.parentId = t.ID
),
bal AS (
    SELECT  jl.accountId,
            SUM(IF(jl.dc = 'DEBIT', jl.amount, -jl.amount)) AS net
    FROM    journal_lines jl
    JOIN    journal_entries e ON e.ID = jl.journalEntryId
    WHERE   e.deleted = 0
      AND   e.entryDate <= @asAt
    GROUP BY jl.accountId
)
SELECT  ta.accountTypeId AS typeId, ta.ID AS topAccountId, ta.accountType AS topAccount,
        SUM(IF(ta.accountTypeId = 3, b.net, -b.net)) AS balance
FROM    bal b
JOIN    anc t              ON t.ID = b.accountId
JOIN    tblaccounttypes ta ON ta.ID = t.topId
WHERE   ta.accountTypeId IN (3, 4, 6)
GROUP BY ta.accountTypeId, ta.ID, ta.accountType
UNION ALL
SELECT  6, NULL, 'accumulated surplus (computed)', -SUM(b.net)
FROM    bal b
JOIN    tblaccounttypes a ON a.ID = b.accountId
WHERE   a.accountTypeId IN (1, 2, 5)
ORDER BY typeId, topAccount;

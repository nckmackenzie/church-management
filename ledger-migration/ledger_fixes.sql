-- =====================================================================
-- tblledger PRE-MIGRATION DATA FIXES
-- Target: MariaDB 10.6
-- Run AFTER taking a backup, then re-run ledger_audit.sql to confirm.
-- Every statement is safe to re-run: it only changes rows that still
-- need the fix.
--
-- WHERE TO RUN: local now. On production, run the whole file only at
-- cutover (same window as the migration and the code deploy). F12 and the
-- revised F4 change the chart structure, which the OLD app's reports
-- may rely on, so don't run them on production while the old code is live.
--
-- Order matters: F4 runs LAST so it covers every account created or moved
-- by the fixes above it.
--
-- Chart rule (applies to F4, F12 and audit A9/A11):
--   root (IDs 1-6, parentId = 0)
--     -> top-level account (directly under a root): always REPORTING
--        -> any depth below: POSTING if it has no non-deleted children,
--           otherwise REPORTING
--   isSubCategory: 1 = posting account, 0 = reporting account.
-- =====================================================================


-- ---------------------------------------------------------------------
-- F1 (from A4) Expense 843 has its two lines on different dates.
-- The expense record's expenseDate is 2025-11-04; the petty cash credit
-- line (pcv 627) was stored as 2025-11-11.
-- ---------------------------------------------------------------------
UPDATE  tblledger
SET     transactionDate = '2025-11-04'
WHERE   congregationId  = 11
  AND   transactionType = 2
  AND   transactionId   = 843
  AND   reference       = 'pcv 627'
  AND   transactionDate <> '2025-11-04';


-- ---------------------------------------------------------------------
-- F2 (from A2) One ledger row (ID 5217) still held an account's old,
-- renamed name. Same row ID on dev and production.
-- ---------------------------------------------------------------------
UPDATE  tblledger
SET     account = 'group funds held - church school'
WHERE   ID = 5217
  AND   account <> 'group funds held - church school';


-- ---------------------------------------------------------------------
-- F3 Chart: petty cash development (194) becomes a child of
-- cash and cash equivalents (22), like petty cash.
-- ---------------------------------------------------------------------
UPDATE  tblaccounttypes
SET     parentId = 22, isSubCategory = 1
WHERE   ID = 194
  AND   (parentId <> 22 OR isSubCategory <> 1);


-- ---------------------------------------------------------------------
-- F4 has moved to the END of this file (see "F4" below).
-- ---------------------------------------------------------------------


-- ---------------------------------------------------------------------
-- F5 (from A9) Income/expense parents posted to directly in 2023-2024.
-- Each gets an INACTIVE child "2023-2024 - <parent>" holding those old
-- postings. Parent totals don't change; inactive keeps the child out of
-- posting dropdowns but it still shows in reports.
-- Only postings dated up to 2024-12-31 are moved, so any newer posting
-- to a parent still shows up in A9.
--   150 groups' expenses            184 interest income
--   127 self funded groups' expenses 32 tithes and offering
--    31 other collections            28 operation costs
--    29 staff expenses               64 donations
-- ---------------------------------------------------------------------
INSERT INTO tblaccounttypes
        (accountType, accountTypeId, parentId, isSubCategory, description,
         isBank, forGroup, isEditable, active, congregationId, deleted)
SELECT  CONCAT('2023-2024 - ', p.accountType), p.accountTypeId, p.ID, 1,
        'Pre-migration postings made directly to the parent account',
        0, p.forGroup, 1, 0, p.congregationId, 0
FROM    tblaccounttypes p
WHERE   p.ID IN (150, 184, 127, 32, 31, 28, 29, 64)
  AND   NOT EXISTS (
            SELECT 1 FROM tblaccounttypes c
            WHERE  c.parentId = p.ID
              AND  c.accountType = CONCAT('2023-2024 - ', p.accountType)
              AND  c.deleted = 0);

UPDATE  tblledger
SET     account = CONCAT('2023-2024 - ', account)
WHERE   congregationId = 11
  AND   transactionDate <= '2024-12-31'
  AND   account IN ('groups'' expenses', 'interest income',
                    'self funded groups'' expenses', 'tithes and offering',
                    'other collections', 'operation costs',
                    'staff expenses', 'donations');


-- ---------------------------------------------------------------------
-- F6 New parent "accumulated fund" under Equity (root 6);
-- opening balance equity (20) becomes its child.
-- ---------------------------------------------------------------------
INSERT INTO tblaccounttypes
        (accountType, accountTypeId, parentId, isSubCategory, description,
         isBank, forGroup, isEditable, active, congregationId, deleted)
SELECT  'accumulated fund', 6, 6, 0,
        'Reporting parent for opening balance equity and accumulated surplus',
        0, 0, 1, 1, 0, 0
FROM    DUAL
WHERE   NOT EXISTS (
            SELECT 1 FROM tblaccounttypes
            WHERE  accountType = 'accumulated fund' AND parentId = 6 AND deleted = 0);

UPDATE  tblaccounttypes o
JOIN    tblaccounttypes af
        ON  af.accountType = 'accumulated fund' AND af.parentId = 6 AND af.deleted = 0
SET     o.parentId = af.ID, o.isSubCategory = 1
WHERE   o.ID = 20
  AND   (o.parentId <> af.ID OR o.isSubCategory <> 1);


-- ---------------------------------------------------------------------
-- F7 (from A9) The mt. horeb funds request posted to the parent
-- "district funds held" belongs to its child (119).
-- ---------------------------------------------------------------------
UPDATE  tblledger
SET     account = 'district funds'' held - mt horeb'
WHERE   ID = 7708
  AND   account = 'district funds held'
  AND   narration LIKE 'mt. horeb%';


-- ---------------------------------------------------------------------
-- F8 (from A9) Journal 6 (opening balances at 30.09.2023): the 8,000
-- "unidentified accrual" posted to the parent "payables and accruals"
-- belongs to audit fees (38). Two accounts are named "audit fees"
-- (38 liability, 54 expense), which breaks name matching, so 38 is
-- renamed first.
-- ---------------------------------------------------------------------
UPDATE  tblaccounttypes
SET     accountType = 'audit fees payable'
WHERE   ID = 38
  AND   accountType = 'audit fees';

UPDATE  tblledger
SET     account = 'audit fees payable'
WHERE   ID = 10097
  AND   account = 'payables and accruals';


-- ---------------------------------------------------------------------
-- F9 (from A9) Journal 70 (2024-09-30) moved four group expenses out of
-- "group funds held" but credited the parent "group funds" with the total
-- (214,810). Split that credit back to each group's own funds-held child:
--   woman's guild 40,000 | pcmf 149,550 | bsr 1,660 | church school 23,600
-- The 3 extra lines are inserted first; line 12110 is then reduced to the
-- woman's guild amount. Both steps only run while 12110 is untouched.
-- ---------------------------------------------------------------------
INSERT INTO tblledger
        (transactionDate, account, parentaccount, debit, credit, narration,
         reference, accountId, transactionType, transactionId, isJournal,
         journalNo, congregationId, deleted)
SELECT  l.transactionDate, v.account, 'group funds', 0.00, v.amount, l.narration,
        l.reference, l.accountId, l.transactionType, l.transactionId, l.isJournal,
        l.journalNo, l.congregationId, l.deleted
FROM    tblledger l
CROSS JOIN (
        SELECT 'group funds held - pcmf'          AS account, 149550.00 AS amount
        UNION ALL SELECT 'group funds held - bsr',            1660.00
        UNION ALL SELECT 'group funds held - church school', 23600.00
) v
WHERE   l.ID = 12110
  AND   l.account = 'group funds'
  AND   l.credit  = 214810.00;

UPDATE  tblledger
SET     account = 'group funds held - woman''s guild', credit = 40000.00
WHERE   ID = 12110
  AND   account = 'group funds'
  AND   credit  = 214810.00;


-- ---------------------------------------------------------------------
-- F10 (from A9) "mt. zion funds request approval" lines (26,000) were
-- posted to the parent "district funds held"; there is no mt. zion child.
-- Journal 140 (2025-09-30) reversed 13,000 of district expenses out of
-- district funds held, which can only be mt. zion's. Create the child and
-- move all six lines there. It is left ACTIVE: it carries a 13,000 debit
-- balance for the accountants to investigate and clear by journal.
-- ---------------------------------------------------------------------
INSERT INTO tblaccounttypes
        (accountType, accountTypeId, parentId, isSubCategory, description,
         isBank, forGroup, isEditable, active, congregationId, deleted)
SELECT  'district funds held - mt. zion', p.accountTypeId, p.ID, 1,
        'Created during ledger migration for mt. zion funds requests',
        0, p.forGroup, 1, 1, p.congregationId, 0
FROM    tblaccounttypes p
WHERE   p.ID = 118
  AND   NOT EXISTS (
            SELECT 1 FROM tblaccounttypes
            WHERE  accountType = 'district funds held - mt. zion' AND deleted = 0);

UPDATE  tblledger
SET     account = 'district funds held - mt. zion'
WHERE   congregationId = 11
  AND   account = 'district funds held'
  AND   ID IN (5720, 8800, 9718, 10107, 10823, 20476);


-- ---------------------------------------------------------------------
-- F11 (from A10) Journals 156 (jprc refund) and 157 (withdrawal from cic)
-- hit 'cash at bank' after 2026-05-15 with no bank posting. Confirmed as
-- co-op bank (105).
-- ---------------------------------------------------------------------
UPDATE  tblledger
SET     account = 'co-op bank'
WHERE   ID IN (29253, 29287)
  AND   account = 'cash at bank'
  AND   transactionType = 5
  AND   transactionId IN (156, 157);


-- ---------------------------------------------------------------------
-- F12 Chart: "cash at bank" (33, under cash and cash equivalents 22)
-- becomes a reporting account, and the bank accounts co-op bank (105)
-- and kcb development account (195) become its children.
-- Result: Asset (3) -> 22 -> 33 -> 105 / 195. Reports roll up recursively.
-- No ledger lines map to 33 after migration (they go to 105/195, see A10).
-- ---------------------------------------------------------------------
UPDATE  tblaccounttypes
SET     isSubCategory = 0
WHERE   ID = 33
  AND   isSubCategory <> 0;

UPDATE  tblaccounttypes
SET     parentId = 33, isSubCategory = 1
WHERE   ID IN (105, 195)
  AND   (parentId <> 33 OR isSubCategory <> 1);


-- ---------------------------------------------------------------------
-- F4 (RUNS LAST) Chart: align isSubCategory with the chart rule.
--   directly under a root (parent's parentId = 0)  -> 0 (reporting)
--   deeper, with non-deleted children              -> 0 (reporting)
--   deeper, no non-deleted children                -> 1 (posting)
-- Deleted accounts are left alone. ORPHAN / cycle accounts are reported
-- by A11, not fixed here.
-- ---------------------------------------------------------------------
UPDATE  tblaccounttypes a
JOIN    tblaccounttypes p ON p.ID = a.parentId
LEFT JOIN (
        SELECT  parentId
        FROM    tblaccounttypes
        WHERE   deleted = 0
        GROUP BY parentId
) ch ON ch.parentId = a.ID
SET     a.isSubCategory = IF(p.parentId = 0 OR ch.parentId IS NOT NULL, 0, 1)
WHERE   a.parentId <> 0
  AND   a.deleted = 0
  AND   a.isSubCategory <> IF(p.parentId = 0 OR ch.parentId IS NOT NULL, 0, 1);

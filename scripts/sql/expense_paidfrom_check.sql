-- expense_paidfrom_check.sql  (task 8.5b)  READ-ONLY: SELECT statements only, nothing is created, changed or deleted.
--
-- Works out, for every row of tblexpenses (all congregations, drafts and deleted included), the account the new column
-- tblexpenses.PaidFromAccountId would get, and every place where the sources disagree:
--   L = the type-2 entry in journal_entries / journal_lines (active entry; for a deleted expense, its most recent deleted entry)
--   T = what the old columns say (method 1: 'petty cash' 98, 'development petty cash' 194, 'cash holding' / numeric / requisition 121,
--       'cash at hand' 34; methods 2-4: bankId, 0 = none)
--   P = tblpettycash rows with this ExpenseId      B = tblbankpostings rows of type 2 with this transactionId
-- Proposed PaidFromAccountId = L's credit account; when there is no entry, T.
--
-- Run each statement separately in phpMyAdmin (every statement repeats the shared WITH block so it stands alone).
-- Needs MariaDB 10.2+ / MySQL 8 (WITH RECURSIVE, window functions): fine on dev (10.4) and production (10.6).
-- Re-run on PRODUCTION BEFORE CUTOVER: production has newer expenses and the groups below must be re-decided for them.
-- Group numbers follow the 8.5b brief (1 clean ... 11 expense-side accounts); Q1 gives every group's count, total and IDs.
-- For long ID lists in Q1 / Q13 run first:  SET SESSION group_concat_max_len = 1000000;   (a session setting, changes no data)


-- ----------------------------------------------------------------------------------------------------
-- Q1. GROUP SUMMARY: count, total amount and every expense ID per group. Groups overlap (an expense can be in several);
--     group 3 is split because drafts / deleted drafts with no entry are expected.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT g, label, COUNT(*) AS n, ROUND(SUM(amount),2) AS total, GROUP_CONCAT(ID ORDER BY ID) AS ids FROM (
    SELECT '1'  AS g, 'clean' AS label, ID, amount FROM z WHERE g1
    UNION ALL SELECT '2',  'L credit <> T', ID, amount FROM z WHERE g2
    UNION ALL SELECT '3a', 'no entry: draft (status 0, not deleted)', ID, amount FROM z WHERE g3 AND status = 0 AND deleted = 0
    UNION ALL SELECT '3b', 'no entry: deleted', ID, amount FROM z WHERE g3 AND deleted = 1
    UNION ALL SELECT '3c', 'no entry: approved and not deleted (needs a decision)', ID, amount FROM z WHERE g3 AND status = 1 AND deleted = 0
    UNION ALL SELECT '4',  'T empty but L has an account', ID, amount FROM z WHERE g4
    UNION ALL SELECT '5',  'L debit account <> expense accountId', ID, amount FROM z WHERE g5
    UNION ALL SELECT '6',  'amount / date / entry shape mismatch', ID, amount FROM z WHERE g6
    UNION ALL SELECT '7',  'entry in another congregation', ID, amount FROM z WHERE g7
    UNION ALL SELECT '8a', 'petty cash row but L credit is another account', ID, amount FROM z WHERE g8a
    UNION ALL SELECT '8b', 'credited to 98, no active petty cash row', ID, amount FROM z WHERE g8b
    UNION ALL SELECT '8c', 'credited to 194, no active petty cash row', ID, amount FROM z WHERE g8c
    UNION ALL SELECT '8d', 'petty cash amount / date <> L', ID, amount FROM z WHERE g8d
    UNION ALL SELECT '8e', 'active petty cash row on draft / deleted expense', ID, amount FROM z WHERE g8e
    UNION ALL SELECT '9a', 'bank-credited, no active bank row', ID, amount FROM z WHERE g9a
    UNION ALL SELECT '9b', 'bank row bank / amount / date <> L', ID, amount FROM z WHERE g9b
    UNION ALL SELECT '9c', 'bank row on expense not credited to a bank', ID, amount FROM z WHERE g9c
    UNION ALL SELECT '10', 'proposed account invalid under new rules', ID, amount FROM z WHERE g10
    UNION ALL SELECT '11', 'expense-side account not postable / bank / income / equity', ID, amount FROM z WHERE g11
) q GROUP BY g, label ORDER BY g;


-- ----------------------------------------------------------------------------------------------------
-- Q2. FINAL COUNT CHECK: total = clean + flagged. 'flagged' = in at least one of groups 2-11 or no entry.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT COUNT(*) AS total_expenses,
       SUM(g1) AS clean,
       SUM(NOT g1) AS not_clean,
       SUM(g1 AND (g2 OR g3 OR g4 OR g5 OR g6 OR g7 OR g8 OR g9 OR g10 OR g11)) AS clean_but_flagged_must_be_0,
       SUM(NOT g1 AND NOT (g2 OR g3 OR g4 OR g5 OR g6 OR g7 OR g8 OR g9 OR g10 OR g11)) AS unclassified_must_be_0,
       SUM(g3) AS no_entry_in_group_3,
       SUM(NOT g1 AND NOT g3) AS flagged_with_entry
FROM z;


-- ----------------------------------------------------------------------------------------------------
-- Q3. PROPOSED VALUE: how many expenses get which proposed PaidFromAccountId, from which source, with validity.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT proposed_src, proposed, (SELECT name FROM acc WHERE acc.ID = z.proposed) AS account, pm AS method,
       status, deleted, COUNT(*) AS n, ROUND(SUM(amount),2) AS total, SUM(g10) AS invalid_under_new_rules
FROM z GROUP BY proposed_src, proposed, pm, status, deleted ORDER BY proposed_src, proposed, pm, status, deleted;


-- ----------------------------------------------------------------------------------------------------
-- Q4. GROUP 2: L credit <> T, per expense, both values shown.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT ID, status, deleted, expenseDate, amount, pm, deductfrom, bankId, requisitionId, T, (SELECT name FROM acc WHERE ID = z.T) AS T_name,
       cr_acct AS L_credit, (SELECT name FROM acc WHERE ID = z.cr_acct) AS L_name, p_n, p_acct, b_n, b_bank
FROM z WHERE g2 ORDER BY ID;


-- ----------------------------------------------------------------------------------------------------
-- Q5. GROUP 3: no entry. T value and whether it is usable under the new rules.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT ID, status, deleted, expenseDate, amount, pm, deductfrom, bankId, requisitionId, T, t_problem, p_n, p_del_n, b_n, b_del_n
FROM z WHERE g3 ORDER BY status, deleted, ID;


-- ----------------------------------------------------------------------------------------------------
-- Q6. GROUP 4: T empty but L has an account (backfill would come from the ledger only).
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT ID, status, deleted, expenseDate, amount, pm, deductfrom, bankId, cr_acct AS L_credit, (SELECT name FROM acc WHERE ID = z.cr_acct) AS L_name,
       p_n, p_acct, b_n, b_bank
FROM z WHERE g4 ORDER BY ID;


-- ----------------------------------------------------------------------------------------------------
-- Q7. GROUPS 5, 6, 7: debit account, amount / date / shape, congregation.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT ID, status, deleted, expenseDate, entryDate, amount, cr_amt, dr_amt, cr_n, dr_n, accountId, dr_acct, other_cong,
       g5, g6, g7
FROM z WHERE g5 OR g6 OR g7 ORDER BY ID;


-- ----------------------------------------------------------------------------------------------------
-- Q8. GROUP 8 (petty cash): every flagged row with the petty cash row beside the ledger credit.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT ID, status, deleted, expenseDate, amount, pm, deductfrom, requisitionId, cr_acct AS L_credit, p_n, p_acct, p_amt, p_date, entryDate,
       g8a, g8b, g8c, g8d, g8e
FROM z WHERE g8a OR g8b OR g8d OR g8e ORDER BY ID;


-- ----------------------------------------------------------------------------------------------------
-- Q9. GROUP 8c: what a petty cash row with PettyCashAccountId = 194 would add for the expenses credited to 194, per fiscal year.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT fy, (SELECT yearName FROM tblfiscalyears WHERE ID = z.fy) AS yearName, (SELECT closed FROM tblfiscalyears WHERE ID = z.fy) AS closed,
       COUNT(*) AS n, ROUND(SUM(amount),2) AS total, MIN(expenseDate) AS first_date, MAX(expenseDate) AS last_date
FROM z WHERE g8c GROUP BY fy ORDER BY fy;


-- ----------------------------------------------------------------------------------------------------
-- Q10. GROUP 9 (bank): every flagged row with cleared status.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT ID, status, deleted, expenseDate, amount, pm, bankId, cr_acct AS L_credit, cr_is_bank, entryDate, b_n, b_bank, b_cr, b_dr, b_date,
       b_cleared, b_del_n, b_del_cleared, g9a, g9b, g9c
FROM z WHERE g9 ORDER BY ID;


-- ----------------------------------------------------------------------------------------------------
-- Q11. GROUP 10: proposed account invalid under the new rules (PAY_CASH / PAY_BANK / requisition = 121).
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT ID, status, deleted, expenseDate, amount, pm, deductfrom, bankId, requisitionId, proposed_src, proposed,
       (SELECT name FROM acc WHERE ID = z.proposed) AS proposed_name, proposed_problem
FROM z WHERE g10 ORDER BY proposed_problem, ID;


-- ----------------------------------------------------------------------------------------------------
-- Q12. GROUP 10, summary: which accounts the cash methods (method 1) and bank methods (2-4) actually use.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT IF(pm = 1, 'method 1 (cash)', 'methods 2-4 (bank)') AS kind, proposed, (SELECT name FROM acc WHERE ID = z.proposed) AS account,
       (SELECT isBank FROM acc WHERE ID = z.proposed) AS isBank, (SELECT incash FROM acc WHERE ID = z.proposed) AS under_cash_root,
       COUNT(*) AS n, ROUND(SUM(amount),2) AS total, SUM(requisitionId IS NOT NULL) AS with_requisition, SUM(g10) AS invalid
FROM z WHERE proposed IS NOT NULL GROUP BY kind, proposed ORDER BY kind, n DESC;


-- ----------------------------------------------------------------------------------------------------
-- Q13. GROUP 11: expense-side accounts that are not postable (or bank / income / equity), with the ledger debit account for comparison.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT accountId, (SELECT name FROM acc WHERE ID = z.accountId) AS account, exp_acct_problem, COUNT(*) AS n, ROUND(SUM(amount),2) AS total,
       SUM(has_entry AND dr_acct = accountId) AS same_as_ledger_debit, SUM(active_exp) AS active_n, GROUP_CONCAT(ID ORDER BY ID) AS ids
FROM z WHERE g11 GROUP BY accountId, exp_acct_problem ORDER BY n DESC;


-- ----------------------------------------------------------------------------------------------------
-- Q14. SHAPE BY DELETED STATE: deleted expenses whose entry or petty cash / bank rows are still active.
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT ID, status, deleted, entry_deleted, p_n, p_del_n, b_n, b_del_n
FROM z WHERE deleted = 1 AND (entry_deleted = 0 OR IFNULL(p_n,0) > 0 OR IFNULL(b_n,0) > 0) ORDER BY ID;


-- ----------------------------------------------------------------------------------------------------
-- Q15. EXPENSES WITH MORE THAN ONE ENTRY ROW (active entries per expense) - must be 0.
SELECT transactionId, congregationId, COUNT(*) AS entries, SUM(deleted = 0) AS active_entries
FROM journal_entries WHERE transactionTypeId = 2 GROUP BY transactionId, congregationId HAVING SUM(deleted = 0) > 1;


-- ----------------------------------------------------------------------------------------------------
-- Q16. EXPENSES WHOSE TYPE-2 AMOUNT IS NOT 2-DECIMAL SAFE (tblexpenses.amount is FLOAT): value vs ROUND to cents.
SELECT ID, amount AS float_amount, ROUND(amount,2) AS rounded, ABS(amount - ROUND(amount,2)) AS diff
FROM tblexpenses WHERE ABS(amount - ROUND(amount,2)) > 0.001 OR amount <= 0 ORDER BY ID;


-- ----------------------------------------------------------------------------------------------------
-- R1. REQUISITION EXPENSES: link to tblfundrequisition and its type-12 approval entry (Dr 121 / Cr 98), group / cost-centre match.
--     Counts per link state (all expenses with requisitionId set, drafts and deleted included).
SELECT e.status, e.deleted,
       (r.ID IS NOT NULL) AS requisition_exists, IFNULL(r.Status, -1) AS req_status, IFNULL(r.Deleted, -1) AS req_deleted,
       (e.expenseType = 2 AND r.GroupId = e.groupId) AS group_matches, (e.expenseType = 3 AND r.DistrictId = e.districtId) AS district_matches,
       (e.congregationId = r.CongregationId) AS same_congregation,
       COUNT(*) AS n, ROUND(SUM(e.amount),2) AS total
FROM tblexpenses e LEFT JOIN tblfundrequisition r ON r.ID = e.requisitionId
WHERE e.requisitionId IS NOT NULL
GROUP BY e.status, e.deleted, requisition_exists, req_status, req_deleted, group_matches, district_matches, same_congregation;


-- ----------------------------------------------------------------------------------------------------
-- R2. REQUISITION EXPENSES THAT DO NOT MATCH their requisition's type / group / district / congregation, or whose requisition is missing,
--     unapproved or deleted.
SELECT e.ID, e.status, e.deleted, e.expenseType, e.groupId, e.districtId, e.amount, e.requisitionId, r.RequestType, r.GroupId AS req_group,
       r.DistrictId AS req_district, r.Status AS req_status, r.Deleted AS req_deleted, r.CongregationId AS req_cong
FROM tblexpenses e LEFT JOIN tblfundrequisition r ON r.ID = e.requisitionId
WHERE e.requisitionId IS NOT NULL
  AND (r.ID IS NULL OR r.Status <> 1 OR r.Deleted = 1 OR r.CongregationId <> e.congregationId
       OR (e.expenseType = 2 AND (r.RequestType <> 'group' OR r.GroupId <> e.groupId))
       OR (e.expenseType = 3 AND (r.RequestType <> 'district' OR r.DistrictId <> e.districtId))
       OR e.expenseType = 1)
ORDER BY e.ID;


-- ----------------------------------------------------------------------------------------------------
-- R3. TYPE-12 APPROVAL ENTRY of each requisition used by an expense: active entry lines (expected Dr 121 / Cr 98 for the approved amount).
SELECT r.ID AS requisition, r.Status, r.Deleted, r.AmountApproved, COUNT(DISTINCT j.ID) AS active_entries,
       SUM(IF(l.dc = 'DEBIT' AND l.accountId = 121, l.amount, 0)) AS dr_121, SUM(IF(l.dc = 'CREDIT' AND l.accountId = 98, l.amount, 0)) AS cr_98,
       SUM(IF(l.accountId NOT IN (98, 121), 1, 0)) AS lines_other_accounts
FROM tblfundrequisition r
     LEFT JOIN journal_entries j ON j.transactionTypeId = 12 AND j.transactionId = r.ID AND j.congregationId = r.CongregationId AND j.deleted = 0
     LEFT JOIN journal_lines l ON l.journalEntryId = j.ID
WHERE r.ID IN (SELECT requisitionId FROM tblexpenses WHERE requisitionId IS NOT NULL)
GROUP BY r.ID, r.Status, r.Deleted, r.AmountApproved
HAVING active_entries <> 1 OR ABS(dr_121 - IFNULL(r.AmountApproved,0)) > 0.01 OR ABS(cr_98 - IFNULL(r.AmountApproved,0)) > 0.01 OR lines_other_accounts > 0
ORDER BY r.ID;


-- ----------------------------------------------------------------------------------------------------
-- R4. OVERSPENT REQUISITIONS: approved amount vs approved non-deleted expenses; drafts counted separately (getrequisitionbalance counts drafts too).
SELECT r.ID AS requisition, r.Status, r.Deleted, r.AmountApproved,
       IFNULL(SUM(IF(e.status = 1 AND e.deleted = 0, e.amount, 0)), 0) AS spent_approved,
       IFNULL(SUM(IF(e.status = 0 AND e.deleted = 0, e.amount, 0)), 0) AS spent_drafts,
       ROUND(IFNULL(SUM(IF(e.status = 1 AND e.deleted = 0, e.amount, 0)), 0) - IFNULL(r.AmountApproved,0), 2) AS overspent_approved,
       COUNT(e.ID) AS expenses
FROM tblfundrequisition r JOIN tblexpenses e ON e.requisitionId = r.ID
GROUP BY r.ID, r.Status, r.Deleted, r.AmountApproved
HAVING spent_approved > IFNULL(r.AmountApproved,0) OR spent_drafts > 0
ORDER BY r.ID;


-- ----------------------------------------------------------------------------------------------------
-- R5. REQUISITION EXPENSES BY PAYMENT METHOD AND DEDUCT-FROM (a requisition expense paid by cheque / bank conflicts with 'must be 121').
SELECT paymethodId, IFNULL(deductfrom,'(null)') AS deductfrom, status, deleted, COUNT(*) AS n, ROUND(SUM(amount),2) AS total, GROUP_CONCAT(ID ORDER BY ID) AS ids
FROM tblexpenses WHERE requisitionId IS NOT NULL GROUP BY paymethodId, deductfrom, status, deleted ORDER BY n DESC;


-- ----------------------------------------------------------------------------------------------------
-- R6. EXPENSES PAID FROM 121 (ledger) WITHOUT A REQUISITION, and the reverse (a requisition expense not credited to 121).
WITH RECURSIVE
tree (ID) AS (                       -- every account under 'cash and cash equivalents' (22), as Ledger::cashTree()
    SELECT ID FROM tblaccounttypes WHERE parentId = 22 AND deleted = 0
    UNION
    SELECT a.ID FROM tblaccounttypes a JOIN tree t ON a.parentId = t.ID WHERE a.deleted = 0
),
acc AS (                             -- postable_any mirrors Ledger::whyNotPostable() (the congregation test is applied per expense)
    SELECT a.ID, a.accountType AS name, a.accountTypeId, a.isBank, a.congregationId,
           (a.ID IN (SELECT ID FROM tree)) AS incash,
           (a.deleted = 0 AND a.parentId <> 0 AND p.ID IS NOT NULL AND p.deleted = 0 AND p.parentId <> 0
            AND a.isSubCategory = 1 AND a.active = 1
            AND NOT EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)) AS postable_any
    FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
),
ent AS (                             -- L: the active type-2 entry, else the most recent deleted one
    SELECT je.*, ROW_NUMBER() OVER (PARTITION BY je.congregationId, je.transactionId ORDER BY je.deleted, je.ID DESC) AS rn
    FROM journal_entries je WHERE je.transactionTypeId = 2
),
ln AS (
    SELECT l.journalEntryId,
           SUM(l.dc = 'CREDIT') AS cr_n, SUM(l.dc = 'DEBIT') AS dr_n,
           SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt, SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt,
           MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM journal_lines l GROUP BY l.journalEntryId
),
pc AS (                              -- P: petty cash rows of the expense
    SELECT ExpenseId, SUM(Deleted = 0) AS p_n, SUM(IF(Deleted = 0, Credit, 0)) AS p_amt,
           MIN(IF(Deleted = 0, PettyCashAccountId, NULL)) AS p_acct, MIN(IF(Deleted = 0, TransactionDate, NULL)) AS p_date,
           SUM(Deleted = 1) AS p_del_n
    FROM tblpettycash WHERE ExpenseId IS NOT NULL GROUP BY ExpenseId
),
bp AS (                              -- B: type-2 bank rows of the expense
    SELECT transactionId, SUM(deleted = 0) AS b_n, MIN(IF(deleted = 0, bankId, NULL)) AS b_bank,
           SUM(IF(deleted = 0, credit, 0)) AS b_cr, SUM(IF(deleted = 0, debit, 0)) AS b_dr,
           MIN(IF(deleted = 0, transactionDate, NULL)) AS b_date, MAX(IF(deleted = 0, cleared, 0)) AS b_cleared,
           SUM(deleted = 1) AS b_del_n, MAX(IF(deleted = 1, cleared, 0)) AS b_del_cleared
    FROM tblbankpostings WHERE transactionType = 2 GROUP BY transactionId
),
x AS (
    SELECT e.ID, e.congregationId AS cong, e.fiscalYearId AS fy, e.status, e.deleted, e.expenseDate, ROUND(e.amount, 2) AS amount,
           e.accountId, e.paymethodId AS pm, e.deductfrom, e.bankId, e.requisitionId, e.groupId, e.districtId, e.expenseType,
           (e.status = 1 AND e.deleted = 0) AS active_exp,
           n.ID AS entryId, n.deleted AS entry_deleted, n.entryDate, n.congregationId AS entry_cong,
           ln.cr_n, ln.dr_n, ln.cr_acct, ln.dr_acct, ln.cr_amt, ln.dr_amt,
           (SELECT COUNT(*) FROM journal_entries o WHERE o.transactionTypeId = 2 AND o.transactionId = e.ID
                   AND o.congregationId <> e.congregationId) AS other_cong,
           -- T: what the old text / columns say
           CASE WHEN e.paymethodId = 1 THEN
                     CASE WHEN e.requisitionId IS NOT NULL OR e.deductfrom = 'cash holding' OR e.deductfrom REGEXP '^[0-9]+$' THEN 121
                          WHEN e.deductfrom = 'petty cash' THEN 98
                          WHEN e.deductfrom = 'development petty cash' THEN 194
                          WHEN e.deductfrom = 'cash at hand' THEN 34
                          ELSE NULL END
                ELSE NULLIF(e.bankId, 0) END AS T,
           pc.p_n, pc.p_amt, pc.p_acct, pc.p_date, pc.p_del_n,
           bp.b_n, bp.b_bank, bp.b_cr, bp.b_dr, bp.b_date, bp.b_cleared, bp.b_del_n, bp.b_del_cleared
    FROM tblexpenses e
         LEFT JOIN ent n ON n.transactionId = e.ID AND n.congregationId = e.congregationId AND n.rn = 1
         LEFT JOIN ln ON ln.journalEntryId = n.ID
         LEFT JOIN pc ON pc.ExpenseId = e.ID
         LEFT JOIN bp ON bp.transactionId = e.ID
),
y AS (
    SELECT x.*,
           COALESCE(x.cr_acct, x.T) AS proposed,
           CASE WHEN x.cr_acct IS NOT NULL THEN 'ledger' WHEN x.T IS NOT NULL THEN 'text' ELSE 'none' END AS proposed_src,
           (x.entryId IS NOT NULL) AS has_entry,
           -- the proposed account is valid under the new rules (method 1 = PAY_CASH, methods 2-4 = PAY_BANK, requisition = 121)
           CASE WHEN COALESCE(x.cr_acct, x.T) IS NULL THEN NULL
                WHEN pa.ID IS NULL OR pa.postable_any = 0 OR pa.congregationId NOT IN (0, x.cong) THEN 'not postable for this congregation'
                WHEN x.pm = 1 AND (pa.isBank = 1 OR pa.incash = 0) THEN 'method 1 needs a cash account (PAY_CASH)'
                WHEN x.pm > 1 AND pa.isBank = 0 THEN 'method 2-4 needs a bank account (PAY_BANK)'
                WHEN x.requisitionId IS NOT NULL AND pa.ID <> 121 THEN 'requisition expense must be paid from 121'
                WHEN pa.ID = 121 AND x.requisitionId IS NULL THEN '121 needs a requisition'
                ELSE NULL END AS proposed_problem,
           -- the same test applied to T alone (usable for expenses with no entry)
           CASE WHEN x.T IS NULL THEN 'T empty'
                WHEN ta.ID IS NULL OR ta.postable_any = 0 OR ta.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN x.pm = 1 AND (ta.isBank = 1 OR ta.incash = 0) THEN 'method 1 needs cash account'
                WHEN x.pm > 1 AND ta.isBank = 0 THEN 'method 2-4 needs bank'
                WHEN x.requisitionId IS NOT NULL AND ta.ID <> 121 THEN 'requisition needs 121'
                WHEN ta.ID = 121 AND x.requisitionId IS NULL THEN '121 needs requisition'
                ELSE NULL END AS t_problem,
           IFNULL(lca.isBank, 0) AS cr_is_bank,
           CASE WHEN ea.ID IS NULL OR ea.postable_any = 0 OR ea.congregationId NOT IN (0, x.cong) THEN 'not postable'
                WHEN ea.isBank = 1 THEN 'bank account'
                WHEN ea.accountTypeId IN (1, 5, 6) THEN 'income/dividend/equity account'
                ELSE NULL END AS exp_acct_problem
    FROM x
         LEFT JOIN acc pa  ON pa.ID  = COALESCE(x.cr_acct, x.T)
         LEFT JOIN acc ta  ON ta.ID  = x.T
         LEFT JOIN acc lca ON lca.ID = x.cr_acct
         LEFT JOIN acc ea  ON ea.ID  = x.accountId
),
f AS (
    SELECT y.*,
           (y.has_entry = 0) AS g3,
           (y.has_entry AND y.cr_acct IS NOT NULL AND y.T IS NOT NULL AND y.cr_acct <> y.T) AS g2,
           (y.has_entry AND y.T IS NULL AND y.cr_acct IS NOT NULL) AS g4,
           (y.has_entry AND (y.dr_acct IS NULL OR y.dr_acct <> y.accountId)) AS g5,
           (y.has_entry AND (y.cr_n <> 1 OR y.dr_n <> 1 OR ABS(y.cr_amt - y.amount) > 0.01 OR ABS(y.dr_amt - y.amount) > 0.01
                             OR y.entryDate <> y.expenseDate)) AS g6,
           (y.other_cong > 0) AS g7,
           -- 8a petty cash row but the ledger credit is another account (or there is no entry)
           (IFNULL(y.p_n,0) > 0 AND (y.has_entry = 0 OR y.cr_acct IS NULL OR y.p_acct <> y.cr_acct)) AS g8a,
           -- 8b credited to 98 on an active expense with no active petty cash row
           (y.active_exp AND y.has_entry AND y.cr_acct = 98 AND IFNULL(y.p_n,0) = 0) AS g8b,
           -- 8c credited to 194 and no active petty cash row (0 after the 8.5c backfill in open years)
           (y.active_exp AND y.has_entry AND y.cr_acct = 194 AND IFNULL(y.p_n,0) = 0) AS g8c,
           -- 8d petty cash amount / date differs from the ledger
           (IFNULL(y.p_n,0) > 0 AND y.has_entry AND (ABS(y.p_amt - y.cr_amt) > 0.01 OR y.p_date <> y.entryDate)) AS g8d,
           -- 8e active petty cash row on a draft or deleted expense
           (IFNULL(y.p_n,0) > 0 AND y.active_exp = 0) AS g8e,
           -- 9a bank-credited active expense with no active bank row
           (y.active_exp AND y.has_entry AND y.cr_is_bank = 1 AND IFNULL(y.b_n,0) = 0) AS g9a,
           -- 9b bank row differs from the ledger (bank, amount, date, more than one row, or a debit)
           (IFNULL(y.b_n,0) > 0 AND y.has_entry AND (y.b_bank <> y.cr_acct OR ABS(y.b_cr - y.cr_amt) > 0.01 OR y.b_date <> y.entryDate
                                                      OR y.b_n > 1 OR y.b_dr <> 0)) AS g9b,
           -- 9c active bank row on an expense not credited to a bank (or a draft / deleted one)
           (IFNULL(y.b_n,0) > 0 AND (y.has_entry = 0 OR y.cr_is_bank = 0 OR y.active_exp = 0)) AS g9c,
           (y.proposed_problem IS NOT NULL) AS g10,
           (y.exp_acct_problem IS NOT NULL) AS g11
    FROM y
),
z AS (
    SELECT f.*, (f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e) AS g8, (f.g9a OR f.g9b OR f.g9c) AS g9,
           (f.has_entry AND NOT (f.g2 OR f.g4 OR f.g5 OR f.g6 OR f.g7 OR f.g8a OR f.g8b OR f.g8c OR f.g8d OR f.g8e
                                 OR f.g9a OR f.g9b OR f.g9c OR f.g10 OR f.g11)) AS g1
    FROM f
)
SELECT ID, status, deleted, pm, deductfrom, requisitionId, cr_acct AS L_credit, amount,
       IF(cr_acct = 121 AND requisitionId IS NULL, '121 without requisition', 'requisition not credited to 121') AS problem
FROM z WHERE has_entry AND ((cr_acct = 121 AND requisitionId IS NULL) OR (requisitionId IS NOT NULL AND cr_acct <> 121)) ORDER BY ID;

-- ----------------------------------------------------------------------------------------------------
-- R7. REQUISITION STYLE (task 8.5c, for 8.5d): every requisition used by an expense.
--     new   = exactly one active type-12 approval entry and it DEBITS 121 (cash holding account)
--     old   = exactly one active type-12 approval entry and it debits another account (funds held / groups' expenses ...)
--     unclear = no type-12 entry, more than one active entry, only voided entries, or an entry that is not two lines
--     paid_from = the PaidFromAccountId values of the expenses (needs expense_paidfrom_schema.sql + the backfill)
WITH ap AS (
    SELECT r.ID AS req, COUNT(DISTINCT j.ID) AS active_entries, COUNT(l.ID) AS lines_n,
           SUM(l.dc = 'DEBIT' AND l.accountId = 121) AS dr121, MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct
    FROM tblfundrequisition r
         LEFT JOIN journal_entries j ON j.transactionTypeId = 12 AND j.transactionId = r.ID AND j.congregationId = r.CongregationId AND j.deleted = 0
         LEFT JOIN journal_lines l ON l.journalEntryId = j.ID
    GROUP BY r.ID
),
voided AS (
    SELECT transactionId AS req, COUNT(*) AS n FROM journal_entries WHERE transactionTypeId = 12 AND deleted = 1 GROUP BY transactionId
)
SELECT r.ID AS requisition, r.RequestType, r.Status, r.Deleted, r.AmountApproved,
       CASE WHEN ap.active_entries = 1 AND ap.lines_n = 2 AND ap.dr121 = 1 THEN 'new'
            WHEN ap.active_entries = 1 AND ap.lines_n = 2 THEN 'old'
            ELSE 'unclear' END AS style,
       ap.active_entries, IFNULL(v.n, 0) AS voided_entries, ap.dr_acct AS approval_debit_account,
       COUNT(e.ID) AS expenses,
       IFNULL(SUM(IF(e.status = 1 AND e.deleted = 0, e.amount, 0)), 0) AS spent_approved,
       IFNULL(SUM(IF(e.status = 0 AND e.deleted = 0, e.amount, 0)), 0) AS spent_drafts,
       GROUP_CONCAT(DISTINCT e.PaidFromAccountId ORDER BY e.PaidFromAccountId) AS paid_from
FROM tblfundrequisition r
     JOIN tblexpenses e ON e.requisitionId = r.ID
     JOIN ap ON ap.req = r.ID
     LEFT JOIN voided v ON v.req = r.ID
GROUP BY r.ID, r.RequestType, r.Status, r.Deleted, r.AmountApproved, style, ap.active_entries, v.n, ap.dr_acct
ORDER BY r.ID;

-- ----------------------------------------------------------------------------------------------------
-- R8. BOUNDARY between the styles: lowest / highest requisition ID of each style, and how many requisitions / expenses.
WITH ap AS (
    SELECT r.ID AS req, COUNT(DISTINCT j.ID) AS active_entries, COUNT(l.ID) AS lines_n,
           SUM(l.dc = 'DEBIT' AND l.accountId = 121) AS dr121
    FROM tblfundrequisition r
         LEFT JOIN journal_entries j ON j.transactionTypeId = 12 AND j.transactionId = r.ID AND j.congregationId = r.CongregationId AND j.deleted = 0
         LEFT JOIN journal_lines l ON l.journalEntryId = j.ID
    GROUP BY r.ID
),
st AS (
    SELECT r.ID AS req,
           CASE WHEN ap.active_entries = 1 AND ap.lines_n = 2 AND ap.dr121 = 1 THEN 'new'
                WHEN ap.active_entries = 1 AND ap.lines_n = 2 THEN 'old' ELSE 'unclear' END AS style
    FROM tblfundrequisition r JOIN ap ON ap.req = r.ID
    WHERE r.ID IN (SELECT requisitionId FROM tblexpenses WHERE requisitionId IS NOT NULL)
)
SELECT style, MIN(req) AS lowest_requisition, MAX(req) AS highest_requisition, COUNT(*) AS requisitions,
       (SELECT COUNT(*) FROM tblexpenses e WHERE e.requisitionId IN (SELECT req FROM st s2 WHERE s2.style = st.style)) AS expenses,
       GROUP_CONCAT(req ORDER BY req) AS requisition_ids
FROM st GROUP BY style ORDER BY style;

-- ----------------------------------------------------------------------------------------------------
-- Q17. AFTER THE 8.5c BACKFILL (needs PaidFromAccountId): every expense with an entry must agree with the ledger on both sides.
--      Expected: all three counts 0; expenses_without_paid_from = the deleted / unusable rows with no entry (dev: 3).
SELECT COUNT(*) AS expenses_with_entry,
       SUM(IFNULL(e.PaidFromAccountId, 0) <> ln.cr_acct) AS paid_from_differs_from_credit,
       SUM(e.accountId <> ln.dr_acct) AS account_differs_from_debit,
       (SELECT COUNT(*) FROM tblexpenses x WHERE x.PaidFromAccountId IS NULL) AS expenses_without_paid_from
FROM tblexpenses e
     JOIN (SELECT n.congregationId, n.transactionId, n.ID AS entryId,
                  ROW_NUMBER() OVER (PARTITION BY n.congregationId, n.transactionId ORDER BY n.deleted, n.ID DESC) AS rn
           FROM journal_entries n WHERE n.transactionTypeId = 2) en
       ON en.transactionId = e.ID AND en.congregationId = e.congregationId AND en.rn = 1
     JOIN (SELECT journalEntryId, MAX(IF(dc = 'CREDIT', accountId, NULL)) AS cr_acct, MAX(IF(dc = 'DEBIT', accountId, NULL)) AS dr_acct
           FROM journal_lines GROUP BY journalEntryId) ln ON ln.journalEntryId = en.entryId;

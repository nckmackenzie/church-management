-- 8.9 production audit (READ-ONLY: SELECT only). Run on production FIRST, before any script of the runbook, and again after
-- the cutover (part B). Every query is standalone. Expected values are the dev results of 2026-10-22 (congregation 11);
-- production may differ: what matters is that nothing unexpected shows up and that you have read each result.
--   mysql -uroot cms < ledger-migration/audit_8_9_production.sql > audit_8_9_before.txt

-- ============================================================ PART A - old ledger (tblledger), before the cutover
SELECT 'A1 type-12 approvals whose tblledger rows are not exactly 2 (doubled posting). Dev: 2 (requisitions 58 and 201, 4 rows each)' AS check_name;
SELECT t.transactionId AS requisitionId, r.ReqNo, r.AmountApproved, r.ApprovalDate, r.CongregationId, COUNT(*) AS ledger_rows
FROM   tblledger t LEFT JOIN tblfundrequisition r ON r.ID = t.transactionId
WHERE  t.transactionType = 12 AND t.deleted = 0
GROUP  BY t.transactionId, r.ReqNo, r.AmountApproved, r.ApprovalDate, r.CongregationId
HAVING COUNT(*) <> 2;

SELECT 'A2 doubled approvals: the financial year of each (a CLOSED year needs the sign-off script) and any active duplicate bank row. Dev: 58 in 2023-2024 (CLOSED, 2 bank rows), 201 in 2025-2026 (open, no bank row)' AS check_name;
SELECT t.transactionId AS requisitionId, r.ReqNo, MIN(t.transactionDate) AS entryDate,
       (SELECT CONCAT(y.yearName, IF(y.closed = 1, ' (CLOSED)', ' (open)')) FROM tblfiscalyears y
         WHERE MIN(t.transactionDate) BETWEEN y.startDate AND y.endDate ORDER BY y.deleted, y.ID DESC LIMIT 1) AS financial_year,
       (SELECT COUNT(*) FROM tblbankpostings b WHERE b.transactionType = 12 AND b.transactionId = t.transactionId AND b.deleted = 0) AS active_bank_rows
FROM   tblledger t JOIN tblfundrequisition r ON r.ID = t.transactionId
WHERE  t.transactionType = 12 AND t.deleted = 0
GROUP  BY t.transactionId, r.ReqNo
HAVING COUNT(*) <> 2;

SELECT 'A3 approved requisitions with no active type-12 ledger rows. Dev: 0' AS check_name;
SELECT r.ID, r.ReqNo, r.AmountApproved, r.ApprovalDate, r.DontDeduct
FROM   tblfundrequisition r
WHERE  r.Status = 1 AND r.Deleted = 0
  AND  NOT EXISTS (SELECT 1 FROM tblledger t WHERE t.transactionType = 12 AND t.transactionId = r.ID AND t.deleted = 0);

SELECT 'A4 active type-12 ledger rows of a requisition that is not approved / is deleted / missing. Dev: 0' AS check_name;
SELECT DISTINCT t.transactionId, r.Status, r.Deleted
FROM   tblledger t LEFT JOIN tblfundrequisition r ON r.ID = t.transactionId
WHERE  t.transactionType = 12 AND t.deleted = 0 AND (r.ID IS NULL OR r.Status <> 1 OR r.Deleted = 1);

SELECT 'A5 expenses booked against REJECTED or deleted requisitions (unapprove / reject now refuse while an expense exists). Dev: 6 (expenses 683, 707, 746-749)' AS check_name;
SELECT x.ID AS expenseId, x.expenseDate, x.amount, x.status, x.requisitionId, r.ReqNo, r.Status AS requisitionStatus, r.Deleted AS requisitionDeleted
FROM   tblexpenses x JOIN tblfundrequisition r ON r.ID = x.requisitionId
WHERE  x.deleted = 0 AND (r.Status <> 1 OR r.Deleted = 1);

SELECT 'A6 approved requisitions spent beyond the approved amount (old data, informational). Dev: 6 (39, 40, 67, 71, 81, 223)' AS check_name;
SELECT r.ID, r.ReqNo, r.AmountApproved, SUM(x.amount) AS spent
FROM   tblfundrequisition r JOIN tblexpenses x ON x.requisitionId = r.ID AND x.deleted = 0 AND x.status = 1
WHERE  r.Status = 1 AND r.Deleted = 0
GROUP  BY r.ID, r.ReqNo, r.AmountApproved HAVING SUM(x.amount) > r.AmountApproved;

SELECT 'A7 approved, deducting requisitions with no tblmmf credit row, and DontDeduct ones that have one. Dev: 0 and 0' AS check_name;
SELECT 'no row' AS problem, r.ID FROM tblfundrequisition r
WHERE r.Status = 1 AND r.DontDeduct = 0 AND r.Deleted = 0 AND r.RequestType IN ('group', 'district')
  AND NOT EXISTS (SELECT 1 FROM tblmmf m WHERE m.TransactionType = 12 AND m.TransactionId = r.ID)
UNION ALL
SELECT 'row on a DontDeduct requisition', r.ID FROM tblfundrequisition r JOIN tblmmf m ON m.TransactionType = 12 AND m.TransactionId = r.ID WHERE r.DontDeduct = 1;

SELECT 'A8 duplicate tblmmf type-12 rows per requisition. Dev: 0' AS check_name;
SELECT TransactionId, COUNT(*) FROM tblmmf WHERE TransactionType = 12 GROUP BY TransactionId HAVING COUNT(*) > 1;

SELECT 'A9 tblmmf rows by type and Deleted (the balance functions now ignore Deleted = 1). Dev: type 1 = 1,240 (28 deleted), 12 = 83, 17 = 102 (1 deleted), 11 = 0' AS check_name;
SELECT TransactionType, SUM(Deleted = 0) AS active, SUM(Deleted = 1) AS deleted, SUM(IF(Deleted = 1, Debit, 0)) AS deleted_debit, SUM(IF(Deleted = 1, Credit, 0)) AS deleted_credit
FROM   tblmmf GROUP BY TransactionType;

SELECT 'A10 type-17 collections and type-11 receipts with no active ledger rows, and ledger rows with no active source. Dev: 0 and 0' AS check_name;
SELECT 'type 17 source without ledger rows' AS problem, m.ID FROM tblmmf m
WHERE m.TransactionType = 17 AND m.Deleted = 0 AND NOT EXISTS (SELECT 1 FROM tblledger t WHERE t.transactionType = 17 AND t.transactionId = m.ID AND t.deleted = 0)
UNION ALL
SELECT 'type 17 ledger rows without active source', t.transactionId FROM tblledger t
WHERE t.transactionType = 17 AND t.deleted = 0 AND NOT EXISTS (SELECT 1 FROM tblmmf m WHERE m.ID = t.transactionId AND m.TransactionType = 17 AND m.Deleted = 0)
UNION ALL
SELECT 'type 11 source without ledger rows', m.ID FROM tblmmf m
WHERE m.TransactionType = 11 AND m.Deleted = 0 AND NOT EXISTS (SELECT 1 FROM tblledger t WHERE t.transactionType = 11 AND t.transactionId = m.ID AND t.deleted = 0);

SELECT 'A11 MMF receipts (type 11) on production (dev has none: the new "Group funds held" dropdown is not used for old receipts)' AS check_name;
SELECT COUNT(*) AS mmf_receipts, MIN(TransactionDate), MAX(TransactionDate) FROM tblmmf WHERE TransactionType = 11;

SELECT 'A12 active type 11 / 12 / 17 bank rows that are already cleared (reconciled). The new code will not void or change them. Dev: 6 type 12 (5 cleared), 101 type 17 (all cleared)' AS check_name;
SELECT transactionType, COUNT(*) AS active_rows, SUM(cleared = 1) AS cleared_rows
FROM   tblbankpostings WHERE transactionType IN (11, 12, 17) AND deleted = 0 GROUP BY transactionType;

SELECT 'A13 funds-held liability accounts the MMF dropdown will offer (active posting accounts, type 4, under 25 / 118). Dev: 52 group efforts, 53 registration and renewal fees, 102, 115, 133, 134, 215 (116, 117, 168, 119, 120 are inactive). 52 and 53 are liability accounts under 25 too, so the dropdown offers them: confirm that is wanted' AS check_name;
SELECT a.ID, a.accountType, a.parentId, a.active, a.isSubCategory, a.congregationId
FROM   tblaccounttypes a
WHERE  a.accountTypeId = 4 AND a.parentId IN (25, 118) AND a.deleted = 0 ORDER BY a.active DESC, a.ID;

SELECT 'A14 the system account 121 and the petty cash accounts must exist, be active posting accounts under 22 (cash and cash equivalents). Dev: 121 and 98 active, isSubCategory 1, parent 22' AS check_name;
SELECT ID, accountType, parentId, active, isSubCategory, isBank, deleted, congregationId FROM tblaccounttypes WHERE ID IN (121, 98, 194, 34);

SELECT 'A15 requisition request numbers that repeat inside a congregation (informational: ReqNo is COUNT + 1). Dev: 2 pairs' AS check_name;
SELECT CongregationId, ReqNo, COUNT(*) AS n, GROUP_CONCAT(ID) AS ids FROM tblfundrequisition GROUP BY CongregationId, ReqNo HAVING COUNT(*) > 1;

SELECT 'A16 balance of every group and district today, with the OLD rule (deleted rows counted) and the NEW rule (ignored); keep this output to compare after the functions are replaced' AS check_name;
SELECT * FROM (
SELECT 'group' AS kind, g.ID, g.congregationId, g.groupName AS name,
       (SELECT IFNULL(SUM(Debit) - SUM(Credit), 0) FROM tblmmf WHERE GroupId = g.ID AND TransactionDate <= CURDATE()) AS balance_old_rule,
       (SELECT IFNULL(SUM(Debit) - SUM(Credit), 0) FROM tblmmf WHERE GroupId = g.ID AND Deleted = 0 AND TransactionDate <= CURDATE()) AS balance_new_rule
FROM   tblgroups g WHERE g.deleted = 0
UNION ALL
SELECT 'district', d.ID, d.congregationId, d.districtName,
       (SELECT IFNULL(SUM(Debit) - SUM(Credit), 0) FROM tblmmf WHERE DistrictId = d.ID AND TransactionDate <= CURDATE()),
       (SELECT IFNULL(SUM(Debit) - SUM(Credit), 0) FROM tblmmf WHERE DistrictId = d.ID AND Deleted = 0 AND TransactionDate <= CURDATE())
FROM   tbldistricts d WHERE d.deleted = 0
) x WHERE balance_old_rule <> 0 OR balance_new_rule <> 0 ORDER BY kind, congregationId, ID;

-- ============================================================ PART B - journal entries (after migrate_ledger.php --commit)
SELECT 'B1 type-12 entries that do not have exactly 2 lines. Expected 0 AFTER the repair scripts (dev before the repair: 2)' AS check_name;
SELECT e.ID AS entryId, e.transactionId AS requisitionId, COUNT(*) AS line_count
FROM   journal_entries e JOIN journal_lines l ON l.journalEntryId = e.ID
WHERE  e.transactionTypeId = 12 AND e.deleted = 0 GROUP BY e.ID, e.transactionId HAVING COUNT(*) <> 2;

SELECT 'B2 every approved requisition has exactly one active type-12 entry, and Expense::requisitionStyle() can classify it (a debit on 121 = new). Expected: 0 rows' AS check_name;
SELECT r.ID, r.ReqNo, COUNT(e.ID) AS active_entries
FROM   tblfundrequisition r LEFT JOIN journal_entries e ON e.transactionTypeId = 12 AND e.transactionId = r.ID AND e.deleted = 0 AND e.congregationId = r.CongregationId
WHERE  r.Status = 1 AND r.Deleted = 0 GROUP BY r.ID, r.ReqNo HAVING COUNT(e.ID) <> 1;

SELECT 'B3 style of the approved requisitions (new = a debit line on 121). Dev: new 126, old 66 (192 entries with lines)' AS check_name;
SELECT style, COUNT(*) AS entries FROM (
    SELECT e.ID, IF(SUM(l.accountId = 121 AND l.dc = 'DEBIT') > 0, 'new', 'old') AS style
    FROM   journal_entries e JOIN journal_lines l ON l.journalEntryId = e.ID
    WHERE  e.transactionTypeId = 12 AND e.deleted = 0 GROUP BY e.ID
) x GROUP BY style;

SELECT 'B4 type-17 entries that do not have 2 lines or are unbalanced. Expected 0 rows' AS check_name;
SELECT e.ID, e.transactionId FROM journal_entries e JOIN (SELECT journalEntryId, COUNT(*) n, SUM(IF(dc = 'DEBIT', amount, 0)) d, SUM(IF(dc = 'CREDIT', amount, 0)) c FROM journal_lines GROUP BY journalEntryId) lc ON lc.journalEntryId = e.ID
WHERE  e.transactionTypeId = 17 AND e.deleted = 0 AND (lc.n <> 2 OR lc.d <> lc.c);

SELECT 'B5 active bank rows of types 11 / 12 / 17 without an active entry. Expected 0' AS check_name;
SELECT b.ID, b.transactionType, b.transactionId FROM tblbankpostings b
WHERE  b.transactionType IN (11, 12, 17) AND b.deleted = 0
  AND  NOT EXISTS (SELECT 1 FROM journal_entries e WHERE e.transactionTypeId = b.transactionType AND e.transactionId = b.transactionId AND e.deleted = 0 AND e.congregationId = b.congregationId);

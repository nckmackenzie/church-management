-- chart_flags_check.sql  (task 8.10)  READ-ONLY (SELECT only, no temporary tables), MariaDB 10.4 / 10.6.
-- Run on dev, and on PRODUCTION before and after the cutover:   mysql -uroot cms -t < scripts/sql/chart_flags_check.sql
-- Every query below must return NO ROWS after the 8.10 cutover (the C-queries and the old isSubCategory values are what
-- ledger_fixes.sql / the 8.10 screens repair). A row in A..G is a chart defect; H is information only.
-- Computed flag (decision 65): a root or an account directly under a root = reporting (0); deeper = posting (1) when it has no
-- non-deleted children, otherwise reporting (0). Deleted rows are left out of A, B, C, F.

SELECT 'A. stored isSubCategory differs from the computed value' AS check_name;
WITH RECURSIVE t (ID, depth, rootId) AS (
    SELECT ID, 1, ID FROM tblaccounttypes WHERE parentId = 0
    UNION ALL
    SELECT a.ID, t.depth + 1, t.rootId FROM tblaccounttypes a JOIN t ON a.parentId = t.ID WHERE t.depth < 30
)
SELECT a.ID, a.accountType, t.depth, a.isSubCategory AS stored,
       CASE WHEN t.depth <= 2 THEN 0 WHEN (SELECT COUNT(*) FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0) > 0 THEN 0 ELSE 1 END AS computed
FROM   tblaccounttypes a JOIN t ON t.ID = a.ID
WHERE  a.deleted = 0
  AND  a.isSubCategory <> CASE WHEN t.depth <= 2 THEN 0 WHEN (SELECT COUNT(*) FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0) > 0 THEN 0 ELSE 1 END;

SELECT 'B. posting accounts (stored 1) that have non-deleted children' AS check_name;
SELECT a.ID, a.accountType, COUNT(c.ID) AS children
FROM   tblaccounttypes a JOIN tblaccounttypes c ON c.parentId = a.ID AND c.deleted = 0
WHERE  a.deleted = 0 AND a.isSubCategory = 1
GROUP  BY a.ID, a.accountType;

SELECT 'C. reporting accounts (computed) with active journal lines or bank rows' AS check_name;
WITH RECURSIVE t (ID, depth) AS (
    SELECT ID, 1 FROM tblaccounttypes WHERE parentId = 0
    UNION ALL
    SELECT a.ID, t.depth + 1 FROM tblaccounttypes a JOIN t ON a.parentId = t.ID WHERE t.depth < 30
)
SELECT a.ID, a.accountType, t.depth,
       (SELECT COUNT(*) FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId WHERE e.deleted = 0 AND l.accountId = a.ID) AS journal_lines,
       (SELECT COUNT(*) FROM tblbankpostings b WHERE b.deleted = 0 AND (b.bankId = a.ID OR b.accountId = a.ID)) AS bank_rows
FROM   tblaccounttypes a JOIN t ON t.ID = a.ID
WHERE  a.deleted = 0
  AND (t.depth <= 2 OR EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0))
  AND (EXISTS (SELECT 1 FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId WHERE e.deleted = 0 AND l.accountId = a.ID)
    OR EXISTS (SELECT 1 FROM tblbankpostings b WHERE b.deleted = 0 AND (b.bankId = a.ID OR b.accountId = a.ID)));

SELECT 'D. accountTypeId differs from the root of the chain (non-root accounts)' AS check_name;
WITH RECURSIVE t (ID, depth, rootId) AS (
    SELECT ID, 1, ID FROM tblaccounttypes WHERE parentId = 0
    UNION ALL
    SELECT a.ID, t.depth + 1, t.rootId FROM tblaccounttypes a JOIN t ON a.parentId = t.ID WHERE t.depth < 30
)
SELECT a.ID, a.accountType, a.accountTypeId, t.rootId, a.deleted
FROM   tblaccounttypes a JOIN t ON t.ID = a.ID
WHERE  t.depth > 1 AND (a.accountTypeId IS NULL OR a.accountTypeId <> t.rootId);

SELECT 'E. non-deleted accounts under a DELETED parent' AS check_name;
SELECT a.ID, a.accountType, p.ID AS parent_id, p.accountType AS parent
FROM   tblaccounttypes a JOIN tblaccounttypes p ON p.ID = a.parentId
WHERE  a.deleted = 0 AND p.deleted = 1;

SELECT 'F. accounts not reachable from a root: orphaned parentId, loops, self-parents' AS check_name;
WITH RECURSIVE t (ID, depth) AS (
    SELECT ID, 1 FROM tblaccounttypes WHERE parentId = 0
    UNION ALL
    SELECT a.ID, t.depth + 1 FROM tblaccounttypes a JOIN t ON a.parentId = t.ID WHERE t.depth < 30
)
SELECT a.ID, a.accountType, a.parentId, a.deleted
FROM   tblaccounttypes a
WHERE  a.ID NOT IN (SELECT ID FROM t);

SELECT 'G. banks whose parent is not 33, or non-banks under 33' AS check_name;
SELECT a.ID, a.accountType, a.isBank, a.parentId, a.deleted
FROM   tblaccounttypes a
WHERE  a.deleted = 0 AND ((a.isBank = 1 AND a.parentId <> 33) OR (a.isBank = 0 AND a.parentId = 33));

SELECT 'H. INFORMATION: non-deleted accounts under an INACTIVE parent (allowed: decision 73 warns, it does not block)' AS check_name;
SELECT a.ID, a.accountType, p.ID AS parent_id, p.accountType AS parent
FROM   tblaccounttypes a JOIN tblaccounttypes p ON p.ID = a.parentId
WHERE  a.deleted = 0 AND p.deleted = 0 AND p.active = 0;

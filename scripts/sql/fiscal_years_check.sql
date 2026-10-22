-- Read-only (task 8.4c). Run on production:  mysql -uroot cms < scripts/sql/fiscal_years_check.sql
-- Shows how financial years are marked closed vs deleted, and whether anything is ambiguous.

-- 1. All years. The app lists only deleted = 0. "Close" sets closed = 1 + closedDate; "Delete" only sets deleted = 1.
SELECT ID, yearName, startDate, endDate, closed, closedDate, deleted
FROM   tblfiscalyears ORDER BY startDate, ID;

-- 2. AMBIGUOUS: deleted but never closed. Expected 0 rows (dev: 0). If any row shows up, a date in that year is
--    refused as "The financial year ... has been deleted" (not "closed"); decide what such a year means.
SELECT ID, yearName, startDate, endDate, closed, deleted
FROM   tblfiscalyears WHERE deleted = 1 AND closed = 0;

-- 3. Closed and deleted (the normal "archived" case on dev: years 1-9). Posting into these is refused as "closed".
SELECT ID, yearName, startDate, endDate, closedDate
FROM   tblfiscalyears WHERE deleted = 1 AND closed = 1;

-- 4. Closed year with no closedDate (dev: year 9). Only informational: the closed flag is what the checks use.
SELECT ID, yearName FROM tblfiscalyears WHERE closed = 1 AND closedDate IS NULL;

-- 5. Overlapping ranges (a re-created year over a deleted one is allowed by the app: its overlap check ignores
--    deleted years). The date lookup prefers the non-deleted year. Expected: only deleted-vs-live pairs, if any.
SELECT a.ID AS yearA, a.yearName AS nameA, a.deleted AS deletedA, b.ID AS yearB, b.yearName AS nameB, b.deleted AS deletedB
FROM   tblfiscalyears a JOIN tblfiscalyears b ON a.ID < b.ID AND a.startDate <= b.endDate AND b.startDate <= a.endDate;

-- 6. Transactions whose own date falls in a year that is closed or deleted but were saved after it was closed
--    cannot be told apart here; instead count the active records per year, to see what sits in deleted years.
SELECT f.ID, f.yearName, f.closed, f.deleted,
       (SELECT COUNT(*) FROM tblcontributions_header h WHERE h.fiscalYearId = f.ID AND h.Deleted = 0) AS receipts,
       (SELECT COUNT(*) FROM tblpettycash p WHERE p.Deleted = 0 AND p.TransactionDate BETWEEN f.startDate AND f.endDate) AS petty_cash_rows,
       (SELECT COUNT(*) FROM journal_entries e WHERE e.deleted = 0 AND e.entryDate BETWEEN f.startDate AND f.endDate) AS ledger_entries
FROM   tblfiscalyears f ORDER BY f.startDate, f.ID;

-- 7. Active transactions dated in a range that NO year covers (these are refused as "No financial year is defined").
SELECT 'receipts' AS what, COUNT(*) AS n
FROM   tblcontributions_details d JOIN tblcontributions_header h ON h.ID = d.HeaderId AND h.Deleted = 0
WHERE  NOT EXISTS (SELECT 1 FROM tblfiscalyears f WHERE d.contributionDate BETWEEN f.startDate AND f.endDate)
UNION ALL
SELECT 'petty cash rows', COUNT(*)
FROM   tblpettycash p
WHERE  p.Deleted = 0 AND NOT EXISTS (SELECT 1 FROM tblfiscalyears f WHERE p.TransactionDate BETWEEN f.startDate AND f.endDate);

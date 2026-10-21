-- V10 - bank lines vs bank postings (read-only). Expected: no rows from either query.
--
-- A) Active journal entries written by the new code (no line has legacyLedgerId) that have at
--    least one line on an isBank = 1 account, whose bank lines do not match the active
--    tblbankpostings rows of the same congregationId + transactionType + transactionId.
--    Compared as a multiset of (bankId, side, ROUND(amount, 2)). transactionTypeId 9 excluded
--    (the bank posting is the source record).
-- B) Active tblbankpostings rows (type 9 excluded; IDs 682, 696, 1565 excluded as known legacy
--    differences) whose source has no active entry.

-- A
WITH scope AS (
    SELECT e.ID, e.congregationId, e.transactionTypeId AS ttype, e.transactionId AS tid, e.journalNo, e.entryDate
    FROM   journal_entries e
    WHERE  e.deleted = 0 AND e.transactionTypeId <> 9
      AND  EXISTS (SELECT 1 FROM journal_lines l JOIN tblaccounttypes a ON a.ID = l.accountId
                   WHERE l.journalEntryId = e.ID AND a.isBank = 1)
      AND  NOT EXISTS (SELECT 1 FROM journal_lines l WHERE l.journalEntryId = e.ID AND l.legacyLedgerId IS NOT NULL)
),
lg AS (   -- bank lines
    SELECT s.congregationId, s.ttype, s.tid, l.accountId AS bankId, l.dc AS side,
           ROUND(l.amount, 2) AS amt, COUNT(*) AS cnt
    FROM   scope s
    JOIN   journal_lines l   ON l.journalEntryId = s.ID
    JOIN   tblaccounttypes a ON a.ID = l.accountId AND a.isBank = 1
    GROUP  BY s.congregationId, s.ttype, s.tid, l.accountId, l.dc, ROUND(l.amount, 2)
),
bp AS (   -- active bank rows of those sources
    SELECT s.congregationId, s.ttype, s.tid, p.bankId,
           IF(ROUND(p.debit, 2) > 0, 'DEBIT', 'CREDIT') AS side,
           ROUND(IF(ROUND(p.debit, 2) > 0, p.debit, p.credit), 2) AS amt, COUNT(*) AS cnt
    FROM   scope s
    JOIN   tblbankpostings p ON p.congregationId = s.congregationId AND p.transactionType = s.ttype
                            AND p.transactionId = s.tid AND p.deleted = 0
    GROUP  BY s.congregationId, s.ttype, s.tid, p.bankId, IF(ROUND(p.debit, 2) > 0, 'DEBIT', 'CREDIT'),
              ROUND(IF(ROUND(p.debit, 2) > 0, p.debit, p.credit), 2)
)
SELECT s.ttype AS transactionType, s.tid AS transactionId, s.journalNo, s.entryDate, s.congregationId,
       (SELECT COALESCE(SUM(cnt), 0) FROM lg WHERE lg.congregationId = s.congregationId AND lg.ttype = s.ttype AND lg.tid = s.tid) AS bankLineCount,
       (SELECT COALESCE(SUM(cnt), 0) FROM bp WHERE bp.congregationId = s.congregationId AND bp.ttype = s.ttype AND bp.tid = s.tid) AS bankRowCount,
       (SELECT GROUP_CONCAT(CONCAT(bankId, ' ', side, ' ', amt, ' x', cnt) ORDER BY bankId, side, amt SEPARATOR '; ')
          FROM lg WHERE lg.congregationId = s.congregationId AND lg.ttype = s.ttype AND lg.tid = s.tid) AS linesPerBank,
       (SELECT GROUP_CONCAT(CONCAT(bankId, ' ', side, ' ', amt, ' x', cnt) ORDER BY bankId, side, amt SEPARATOR '; ')
          FROM bp WHERE bp.congregationId = s.congregationId AND bp.ttype = s.ttype AND bp.tid = s.tid) AS rowsPerBank
FROM   scope s
WHERE  EXISTS (SELECT 1 FROM lg WHERE lg.congregationId = s.congregationId AND lg.ttype = s.ttype AND lg.tid = s.tid
                 AND NOT EXISTS (SELECT 1 FROM bp WHERE bp.congregationId = lg.congregationId AND bp.ttype = lg.ttype
                                   AND bp.tid = lg.tid AND bp.bankId = lg.bankId AND bp.side = lg.side
                                   AND bp.amt = lg.amt AND bp.cnt = lg.cnt))
   OR  EXISTS (SELECT 1 FROM bp WHERE bp.congregationId = s.congregationId AND bp.ttype = s.ttype AND bp.tid = s.tid
                 AND NOT EXISTS (SELECT 1 FROM lg WHERE lg.congregationId = bp.congregationId AND lg.ttype = bp.ttype
                                   AND lg.tid = bp.tid AND lg.bankId = bp.bankId AND lg.side = bp.side
                                   AND lg.amt = bp.amt AND lg.cnt = bp.cnt))
ORDER  BY s.congregationId, s.ttype, s.tid;

-- B
SELECT p.congregationId, p.transactionType, p.transactionId, COUNT(*) AS activeBankRows,
       SUM(p.debit) AS debits, SUM(p.credit) AS credits, MIN(p.transactionDate) AS firstDate
FROM   tblbankpostings p
WHERE  p.deleted = 0 AND p.transactionType <> 9
  AND  p.ID NOT IN (682, 696, 1565)   -- known legacy differences (see handover)
  AND  NOT EXISTS (SELECT 1 FROM journal_entries e
                   WHERE e.congregationId = p.congregationId AND e.transactionTypeId = p.transactionType
                     AND e.transactionId = p.transactionId AND e.deleted = 0)
GROUP  BY p.congregationId, p.transactionType, p.transactionId
ORDER  BY p.congregationId, p.transactionType, p.transactionId;

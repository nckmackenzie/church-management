-- Task 8.4b: locked, never-reused number sequences for receipts and petty cash receipts.
-- Idempotent. Run on dev now, on production at cutover:  mysql -uroot cms < scripts/sql/receipt_sequences.sql
--
--   kind = 'receipt'    one sequence per congregation + fiscal year   (tblcontributions_header.receiptNo)
--   kind = 'pettycash'  one sequence per congregation, fiscalYearId = 0 (tblpettycash.ReceiptNo, shared with
--                       transfers to petty cash)
-- Seeded from the current maxima, deleted rows included, so a deleted number is never handed out again.
-- A unique index on tblcontributions_header (congregationId, fiscalYearId, receiptNo) is NOT possible: dev already
-- has duplicates (receipt no. 1513 x20 and 3 other numbers x2). The sequence row is locked (FOR UPDATE) while a
-- number is allocated, which is what guarantees uniqueness for new numbers.

CREATE TABLE IF NOT EXISTS receipt_number_seq (
    congregationId INT         NOT NULL,
    kind           VARCHAR(10) NOT NULL,
    fiscalYearId   INT         NOT NULL DEFAULT 0,
    lastNo         INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (congregationId, kind, fiscalYearId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- receipts: number = digits after the last '/' (covers prefixed years such as 2025-2026/001 as well as plain numbers)
INSERT INTO receipt_number_seq (congregationId, kind, fiscalYearId, lastNo)
SELECT congregationId, 'receipt', fiscalYearId, MAX(CAST(SUBSTRING_INDEX(receiptNo, '/', -1) AS UNSIGNED))
FROM   tblcontributions_header
WHERE  receiptNo REGEXP '^([^/]*/)?[0-9]+$'
GROUP BY congregationId, fiscalYearId
ON DUPLICATE KEY UPDATE lastNo = GREATEST(lastNo, VALUES(lastNo));

-- petty cash receipts (own receipts and transfers to petty cash share one numbering)
INSERT INTO receipt_number_seq (congregationId, kind, fiscalYearId, lastNo)
SELECT CongregationId, 'pettycash', 0, MAX(ReceiptNo)
FROM   tblpettycash
WHERE  ReceiptNo IS NOT NULL
GROUP BY CongregationId
ON DUPLICATE KEY UPDATE lastNo = GREATEST(lastNo, VALUES(lastNo));

SELECT * FROM receipt_number_seq ORDER BY congregationId, kind, fiscalYearId;

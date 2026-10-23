-- GENERATED FROM DEV (task 8.9 follow-up 3) - REVIEW BEFORE USE. Idempotent: inserts only IDs that are missing.
-- tbltransactiontypes (journal_entries.transactionTypeId has a foreign key to it) and tbltransactionmethods (tblbankpostings.transactionMethod)
-- are lookup tables whose seed rows are in no script of the repository. Dev content on 2026-10-23 is below. Production almost certainly has
-- 1-17 already (the old ledger used them); this file exists so that the cutover can CHECK them and fill a gap. Method 7 'Journal' is also in
-- scripts/sql/add_journal_method.sql. Type 18 (bank sub-account opening balance) does not write the ledger and is not on dev either.
-- Compare names first:  SELECT * FROM tbltransactiontypes ORDER BY ID;  SELECT * FROM tbltransactionmethods ORDER BY ID;

INSERT INTO tbltransactiontypes (ID, TransactionType)
SELECT v.ID, v.TransactionType FROM (
    SELECT 1 AS ID, 'receipts' AS TransactionType UNION ALL SELECT 2, 'expenses' UNION ALL SELECT 3, 'pledges'
    UNION ALL SELECT 4, 'pledge payment' UNION ALL SELECT 5, 'journal entries' UNION ALL SELECT 6, 'supplier invoices'
    UNION ALL SELECT 7, 'Supplier payment' UNION ALL SELECT 8, 'bank opening balance' UNION ALL SELECT 9, 'bank posting'
    UNION ALL SELECT 10, 'petty cash receipt' UNION ALL SELECT 11, 'mmf receipts' UNION ALL SELECT 12, 'group fund approval'
    UNION ALL SELECT 13, 'cash deposits' UNION ALL SELECT 14, 'customer invoices' UNION ALL SELECT 15, 'supplier opening bal'
    UNION ALL SELECT 16, 'customer invoice payment' UNION ALL SELECT 17, 'Group collections'
) v WHERE NOT EXISTS (SELECT 1 FROM tbltransactiontypes t WHERE t.ID = v.ID);

INSERT INTO tbltransactionmethods (ID, methodName)
SELECT v.ID, v.methodName FROM (
    SELECT 1 AS ID, 'Deposit' AS methodName UNION ALL SELECT 2, 'Withdrawal' UNION ALL SELECT 3, 'Charges' UNION ALL SELECT 4, 'B/F'
    UNION ALL SELECT 5, 'Transfers' UNION ALL SELECT 6, 'inter-transfer(sub accounts)' UNION ALL SELECT 7, 'Journal'
) v WHERE NOT EXISTS (SELECT 1 FROM tbltransactionmethods t WHERE t.ID = v.ID);

SELECT (SELECT COUNT(*) FROM tbltransactiontypes WHERE ID BETWEEN 1 AND 17) AS types_1_to_17_expected_17,
       (SELECT COUNT(*) FROM tbltransactionmethods WHERE ID BETWEEN 1 AND 7) AS methods_1_to_7_expected_7;

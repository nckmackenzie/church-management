-- Task 8.1b: add the 'Journal' bank transaction method (idempotent).
-- tbltransactionmethods.ID is not AUTO_INCREMENT, so the next ID is MAX(ID) + 1.
INSERT INTO tbltransactionmethods (ID, methodName)
SELECT t.nextId, 'Journal'
FROM (SELECT COALESCE(MAX(ID), 0) + 1 AS nextId FROM tbltransactionmethods) t
WHERE NOT EXISTS (SELECT 1 FROM tbltransactionmethods WHERE LOWER(methodName) = 'journal');

SELECT ID, methodName FROM tbltransactionmethods WHERE LOWER(methodName) = 'journal';

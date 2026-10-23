# Task 8.9 petty cash book vs ledger findings

Date: 2026-10-23  
Scope: dev database, congregation 11 (the only congregation with active `tblpettycash` rows).  
Method: read-only SQL and code inspection. No code or data change described here has been implemented.

## What the two balances mean

The petty cash report uses `tblpettycash`: opening balance before the selected start date plus `Debit - Credit` in the selected range, filtered by congregation, `PettyCashAccountId` and `Deleted = 0`. The ledger comparison below uses active `journal_entries` and `journal_lines`, with `DEBIT - CREDIT`, for accounts 98 and 194.

`Difference` is **ledger minus petty cash book**. Closing balances include every active row dated on or before the fiscal-year end, which is how the report's opening-plus-movement calculation arrives at its closing figure.

| Fiscal year | Account | Petty cash closing | Ledger closing | Difference |
|---|---:|---:|---:|---:|
| 2022/2023 | 98 | 0.00 | 13,479.00 | 13,479.00 |
| 2023-2024 | 98 | 64,133.00 | -48,928.00 | -113,061.00 |
| 2024-2025 | 98 | 1,465,652.00 | 109,600.00 | -1,356,052.00 |
| 2025-2026 | 98 | 3,740,683.00 | 1,232,300.00 | -2,508,383.00 |
| 2025-2026 | 194 | -1,121,615.00 | -1,121,615.00 | 0.00 |
| 2026-2027 | 98 | 3,739,683.00 | 1,225,300.00 | -2,514,383.00 |
| 2026-2027 | 194 | -1,121,615.00 | -1,121,615.00 | 0.00 |

The 2026-2027 figures include all records dated in that fiscal year, including an existing approval dated 2026-12-01; they are not an "as at 2026-10-23" cut-off.

## Difference by transaction type

These are movements within each fiscal year, not cumulative closings. Zero-difference movements are included where they prove that a writer is in step.

| Fiscal year | Account | Type | Petty cash movement | Ledger movement | Difference |
|---|---:|---|---:|---:|---:|
| 2022/2023 | 98 | 5 Journals | 0.00 | 13,479.00 | 13,479.00 |
| 2023-2024 | 98 | 2 Expenses | -2,180,067.00 | -2,180,067.00 | 0.00 |
| 2023-2024 | 98 | 5 Journals | 0.00 | -126,540.00 | -126,540.00 |
| 2023-2024 | 98 | 13 Transfers | 2,244,200.00 | 2,244,200.00 | 0.00 |
| 2024-2025 | 98 | 2 Expenses | -1,923,861.00 | -1,882,861.00 | 41,000.00 |
| 2024-2025 | 98 | 5 Journals | 0.00 | -105,538.00 | -105,538.00 |
| 2024-2025 | 98 | 12 Approvals | 0.00 | -1,178,453.00 | -1,178,453.00 |
| 2024-2025 | 98 | 13 Transfers | 3,325,380.00 | 3,325,380.00 | 0.00 |
| 2025-2026 | 98 | 2 Expenses | -4,593,551.00 | -4,552,761.00 | 40,790.00 |
| 2025-2026 | 98 | 12 Approvals | 0.00 | -1,193,121.00 | -1,193,121.00 |
| 2025-2026 | 98 | 13 Transfers | 6,868,582.00 | 6,868,582.00 | 0.00 |
| 2025-2026 | 194 | 2 Expenses | -1,121,615.00 | -1,121,615.00 | 0.00 |
| 2026-2027 | 98 | 2 Expenses | -1,000.00 | -1,000.00 | 0.00 |
| 2026-2027 | 98 | 5 Journals | 0.00 | -1,000.00 | -1,000.00 |
| 2026-2027 | 98 | 12 Approvals | 0.00 | -5,000.00 | -5,000.00 |

The expense exceptions are existing-data issues, not failures of the current expense writer:

- Three active petty cash rows (IDs 665, 780 and 643; expenses 442, 466 and 675) total 41,000.00 against account 98, while their active ledger entries credit account 121. They produce the 2024-2025 expense difference.
- Expense 1249 has a 40,790.00 debit to account 98 in 2025-2026. The petty cash writer represents only an expense's **paid-from credit** side, so it does not represent this debit; this produces the 2025-2026 expense difference for account 98.

## Which paths write `tblpettycash` today

Only three application models contain live `tblpettycash` insert/update logic: `Banktransaction`, `Cashreceipt`, and `Expense`.

| Type / path | Writes `tblpettycash`? | Side represented |
|---|---|---|
| 13 bank transfer to account 98 or 194 | Yes | Debit (receipt into petty cash). A transfer to any other account has no petty cash row. |
| 10 petty cash receipt | Yes | Debit. The row is linked to its type-10 journal entry by using the petty cash row ID as the transaction ID, but its `TransactionType` / `TransactionId` columns are left NULL. |
| 2 expense paid from account 98 or 194 | Yes | Credit. It is linked by `ExpenseId`; its `TransactionType` / `TransactionId` columns are left NULL. |
| 5 journal | No | Either ledger side can use 98/194; no petty cash row is written. |
| 12 group-fund approval | No | Either side can use 98/194 after follow-up 4; no petty cash row is written. |
| 1 receipt | No | A cash receipt can select a PAY_CASH account, including 98/194; no petty cash row is written. |
| 9 manual bank posting | No | Its non-bank contra can be 98/194 on either side; no petty cash row is written. |
| 7 legacy supplier payment | No | The legacy path can credit the account named `petty cash`; no petty cash row is written. This remains part of task 8.7. |
| 8 bank opening, 11 MMF receipt | No | Their account rules do not offer 98/194 in normal use. |
| 17 group collection | No | It uses the sub-account's configured posting account and bank. The code does not exclude 98/194, but it writes no petty cash row. |
| All remaining paths | No | The code search found no other `tblpettycash` writer. If such a path posts 98/194, the ledger and petty cash book diverge. |

The NULL transaction-type metadata on type-2 and type-10 petty cash rows means the breakdown above had to classify expense rows by `ExpenseId` and receipt rows by `IsReceipt`. This is another reason not to make `tblpettycash` a second complete accounting ledger.

## Approvals involving accounts 98 or 194 with no petty cash row

All active matches are credits to account 98. There are no matching approvals on account 194 and no approval has an active type-12 `tblpettycash` row.

| Fiscal year | Account / side | Approval entries | Total with no petty cash row |
|---|---|---:|---:|
| 2024-2025 | 98 credit | 66 | 1,178,453.00 |
| 2025-2026 | 98 credit | 59 | 1,193,121.00 |
| 2026-2027 | 98 credit | 1 | 5,000.00 |
| **Total** | **98 credit** | **126** | **2,376,574.00** |

## Options and effects

### (a) Make approvals write `tblpettycash`

For a new approval, write a debit row when the receiving account is 98/194 and a credit row when the paid-from account is 98/194; if both sides are petty cash accounts, the book needs both effects or a net transfer rule. Update/void behavior must remain atomic with the approval and must define stable linkage and numbering.

- **No backfill:** closed-year data and report figures already seen remain unchanged. New approvals become visible in the petty cash report, but the historical 2,376,574.00 approval gap remains permanently. Journals, receipts, manual bank postings, supplier payments and configured group collections can still create new gaps.
- **Backfill:** inserting the 126 missing approval credits would reduce the historical petty cash report by 1,178,453.00 in 2024-2025, another 1,193,121.00 in 2025-2026, and 5,000.00 in 2026-2027. This changes closed-year books and figures users have already seen, requires explicit sign-off and rollback data, and still does not resolve journal or expense anomalies.
- Backfilling from the active ledger should not generate receipt numbers for approval credits; doing so would change an unrelated sequence. A new linkage convention and uniqueness rule would be required first.

### (b) Make the petty cash report read the ledger

Read active journal lines for accounts 98/194, with the ledger entry date/reference/description and transaction type. The report would immediately use the accounting source of truth for every posting path.

- Closed and historical report figures would change to the ledger-closing values in the first table. For example, account 98 at 2024-2025 year end changes from 1,465,652.00 to 109,600.00, and at 2025-2026 year end from 3,740,683.00 to 1,232,300.00.
- The change exposes rather than hides existing journal and approval activity. It also removes the need to keep adding synchronization code whenever another posting path can use 98/194.
- `tblpettycash` can remain as operational/legacy receipt metadata while the financial balance and transaction listing come from the ledger. Any receipt-number-only columns can be joined by transaction identity where a reliable link exists; type-10 linkage should be made explicit in a later schema task.

## Recommendation

Choose **(b): make the petty cash report read the ledger**, in a separate task with owner sign-off on the historical figure changes. The journal is already the canonical balanced record and is protected by fiscal-year locks. Option (a) creates and maintains a second partial ledger, does not cover the existing journal differences, and would require every future posting path to remember an extra write.

Before releasing option (b), show finance the year-by-year reconciliation above, agree whether the three stale 2024-2025 expense rows and expense 1249 need separate corrections, and preserve a read-only export of the old report figures. Do not change closed-year data merely to make the legacy book agree; decide those corrections separately with explicit sign-off.

## Additional issues found while inspecting the touched paths

1. Fixed in this follow-up: the approval controller cast `paymethod` and `paidfrom` to integers and `approved` to float before model validation, so prefixed strings could be accepted and array-shaped fields could reach string functions.
2. Fixed in this follow-up: the approvals list's legacy view had no receiving-account information. The new list query fetches the active type-12 debit account in one grouped join, with no query per row.
3. Existing data: the three stale petty cash expense rows totaling 41,000.00 and expense 1249's 40,790.00 debit are described above. No data was changed.
4. Existing design: type-2 and type-10 petty cash rows do not populate `TransactionType` / `TransactionId` consistently; audit code must infer their source.
5. Existing data/runbook: doubled approvals 58 and 201 still await the documented repair scripts and required sign-off. A grouped approvals-list join avoids duplicate display rows but must not be treated as repairing that data.
6. Existing presentation risk, left unchanged: the approval and approvals views contain older unescaped database-backed fields. The new `ReceivedInto` cell is escaped; a complete output-escaping pass belongs in a separate UI-hardening task.


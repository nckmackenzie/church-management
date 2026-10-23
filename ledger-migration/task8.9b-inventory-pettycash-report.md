# Task 8.9b inventory: petty cash report on the ledger

Date: 2026-10-23

Branch: `ledger/8.9b-pettycash-report` (from committed 8.9 commit `2d14927`)

Scope: code inspection and read-only SQL against the dev `cms` database. No application code or data was changed.

## Decision and executive summary

The accounting rows, opening balance and closing balance of the petty cash report will come from active `journal_entries` / `journal_lines`. There will be no `tblpettycash` backfill. `tblpettycash` remains a source/metadata table only where the ledger does not hold the operational field, principally petty-cash receipt numbers and the type-10 receipt document.

The current report is not a full petty-cash ledger. It reads only `tblpettycash`, whose live writers cover type 2 expenses paid from 98/194, type 10 petty-cash receipts, and type 13 transfers into 98/194. It omits journals, approvals, ordinary receipts, manual bank postings and any future posting path that uses the selected account.

The dev row reconciliation is strong enough to switch the accounting query: 918 of 921 active `tblpettycash` rows have a journal line with the same source, account, side and amount. The other three are known stale type-2 rows totaling 41,000.00 that say account 98 while their entries credit account 121. There are no missing active entries, amount mismatches or side mismatches. Dev has no persisted type-10 row, active or deleted.

## 1. Current report

### Request and rendering path

| Layer | File / object | Current responsibility |
|---|---|---|
| Navigation | `app/views/inc/admin-nav.php`, `admin-parish.php`, `finance-nav.php`, `finance-parish.php` | Links to **Petty Cash Utilization**. |
| Controller page | `app/controllers/Reports.php:1428-1432`, `Reports::pettycash()` | Loads the account list and renders the page. |
| Controller data endpoint | `app/controllers/Reports.php:1434-1497`, `Reports::pettycashrpt()` | Accepts GET filters, calls the model, calculates closing balance and returns an HTML table fragment. This is not a JSON API. |
| Model movements | `app/models/Report.php:506-513`, `Report::pettycashutil()` | Calls `sp_pettycashutilization`. |
| Model accounts | `app/models/Report.php:516-523`, `Report::pettycashaccounts()` | Calls `Ledger::listPettyCashAccounts()`, which restricts the list to fixed IDs 98/194 that are valid `PAY_CASH` posting accounts for the session congregation. |
| Model totals | `app/models/Report.php:525-553`, `Report::debitcredittotal()` | Runs two `tblpettycash` sums and calls `getopeningbal`. |
| View and JavaScript | `app/views/reports/pettycash.php` | Date/account form, AJAX request, DataTable, client totals and export/print buttons. The JavaScript is inline; there is no petty-cash-specific external JS file. |
| Stored procedure | `sp_pettycashutilization`, installed by `scripts/sql/petty_cash_fixes.sql:50-70` | Returns one opening row plus active `tblpettycash` movements. |
| Stored function | `getopeningbal`, installed by `scripts/sql/petty_cash_fixes.sql:37-48` | `SUM(Debit)-SUM(Credit)` before the start date. |
| Database view | `vw_cashreceipts`, installed by `scripts/sql/petty_cash_fixes.sql:74-89` | Not used by the report. It serves the petty-cash receipt maintenance screen. |

The live dev definitions match `scripts/sql/petty_cash_fixes.sql`. No trigger reads or writes `tblpettycash`; no other database routine or view references it beyond the two routines and `vw_cashreceipts` above.

### Filters and scope

- Start and end dates are blank initially and required by browser JavaScript. Movements include both dates; opening is strictly before the start date.
- Account is a single-select. Today it offers account 98, and account 194 only where its congregation ownership permits it (dev congregation 11). Invalid account IDs silently fall back to 98, or the first allowed account.
- Congregation is not a visible filter. Every model query uses `$_SESSION['congId']`.
- The server does not require the range to stay inside one fiscal year. Closed years are readable, which is correct for a report.

### Rows, columns and totals

The stored procedure returns an artificial **Opening Balance** row followed by movements. The table displays:

1. Date (intended as `dd-mm-YYYY`, but the controller reparses the procedure's `dd/mm/yy` string incorrectly; see issue 3)
2. Narration
3. Debit
4. Credit
5. Reference

The footer displays:

- Debit total calculated in the browser over the returned debit column. This includes the opening row.
- Credit total calculated in the browser over the returned credit column.
- Closing balance calculated on the server as `opening + movement debits - movement credits`, placed in the fifth column under the **Reference** heading.

The current dev `tblpettycash` summaries that should be frozen before cutover are:

| Fiscal year | Account | Opening | Debits | Credits | Closing |
|---|---:|---:|---:|---:|---:|
| 2023-2024 | 98 | 0.00 | 2,244,200.00 | 2,180,067.00 | 64,133.00 |
| 2023-2024 | 194 | 0.00 | 0.00 | 0.00 | 0.00 |
| 2024-2025 | 98 | 64,133.00 | 3,325,380.00 | 1,923,861.00 | 1,465,652.00 |
| 2024-2025 | 194 | 0.00 | 0.00 | 0.00 | 0.00 |
| 2025-2026 | 98 | 1,465,652.00 | 6,868,582.00 | 4,593,551.00 | 3,740,683.00 |
| 2025-2026 | 194 | 0.00 | 0.00 | 1,121,615.00 | -1,121,615.00 |
| 2026-2027 | 98 | 3,740,683.00 | 0.00 | 1,000.00 | 3,739,683.00 |
| 2026-2027 | 194 | -1,121,615.00 | 0.00 | 0.00 | -1,121,615.00 |

The switch intentionally changes these to the ledger figures documented in `ledger-migration/task8.9-pettycash-findings.md`; for example, account 98 closes 2024-2025 at 109,600.00 and 2025-2026 at 1,232,300.00 in the ledger.

### Printing and exports

After a successful preview, DataTables supplies Excel, PDF and print buttons, with the footer requested for Excel/PDF. There is no CSV option and no server-side/export audit artifact. The export has only the table: it does not embed the chosen account, congregation, date range, fiscal-year status, extraction timestamp, old-query version or a clear opening/closing summary. Users can search/reorder the client table, so this is not a suitable immutable pre-switch record.

## 2. Everything else that reads `tblpettycash`

Runtime uses found by repository search and by inspecting every dev routine/view are below. Tests, audits and migration utilities are listed separately because they are not user-facing readers.

| Reader / path | What it reads | Must its accounting result move to the ledger? | Disposition |
|---|---|---|---|
| `Report::pettycashutil()` / `debitcredittotal()` and the two routines | Movement, opening and totals | **Yes** | Replace with the ledger query. Retire these report calls; the old routine/function can be dropped later through a scripted schema task once no caller remains. |
| `Cashreceipt::GetReceipts()` via `vw_cashreceipts` | Type-10 source documents: receipt number, bank, account, amount, reference | **No** | Keep as operational/source metadata. It is not a balance calculation. Optionally join the active ledger entry to flag an orphan, but do not replace the source-document screen with journal lines. |
| `Cashreceipt::lockReceipt()` / `GetReceipt()` | The source row being edited or deleted | **No** | Keep. Type 10 uses `tblpettycash.ID` as `journal_entries.transactionId`. |
| `Ledger::highestNumber()` (`Ledger.php:426`) | Highest petty-cash receipt number, including deleted rows | **No** | Keep until receipt numbering moves to another source table. The ledger deliberately has no receipt-number field. |
| `Banktransaction::savePettyCash()` | Existing type-13 metadata row before update/void | **No** | Keep if finance wants the transfer's petty-cash receipt number. It must not be used for balance. |
| `Expense::syncPettyCash()` / expense void | Existing type-2 mirror row before update/void | **No balance read** | Once the report has switched, new type-2 mirror rows add no field that is absent from `tblexpenses` or the ledger. Recommendation: stop creating/updating them in the implementation task, but retain old rows read-only for the frozen export and audit. This is a code decision, not a data backfill/delete. |
| `Year::linkedRecords()` (`Year.php:114`) | Count of active source rows in a fiscal-year date range before year deletion | **No** | Keep as a source/orphan guard. `journal_entries` is already checked separately; the extra check protects legacy/source rows that have lost a journal entry. |

No dashboard, balance widget, "insufficient petty cash" check, or public/application API reads `tblpettycash`. Expense validation checks requisition balance, and approval validation checks MMF/group or district balances; neither checks petty-cash availability. There is no petty-cash receipt print action. Expense voucher printing (`Expenses::print()` and `app/views/expenses/print.php`) already gets approved posting lines from `Ledger::getEntry()` and does not read `tblpettycash`.

Non-runtime references are the 8.4-8.9 tests, `scripts/expense_paidfrom_backfill.php`, the expense/fiscal-year audit SQL and schema/sequence scripts. They should remain as historical/audit tooling. Future tests must stop treating a type-2 `tblpettycash` mirror as the accounting result if the implementation stops that writer.

## 3. Report fields that are not native ledger fields

The report's current five fields are available from the ledger: `entryDate`, line/entry memo or description, `dc`, `amount`, and `reference`. The complication is historical completeness: among the 918 exact row matches, 150 `tblpettycash` rows have a reference while the migrated journal entry reference is blank (131 type 2 and 19 type 13). In all 150 cases the source document has the same reference as `tblpettycash`, so the reliable fallback is the source document, not the mirror table.

Potential additional fields are assessed below.

| Desired field | Reliable source and join | Reliability / limitation |
|---|---|---|
| Transaction type | `journal_entries.transactionTypeId` -> `tbltransactiontypes` | Reliable and should be shown. |
| Source ID | `journal_entries.transactionId` | Reliable within congregation + type. Do not show it as a business document number. |
| Receipt/document number | Type 1: `tblcontributions_header.ID = transactionId` -> `receiptNo`; type 2: `tblexpenses.ID` -> `voucherNo`; type 5: `journal_entries.journalNo`; type 12: `tblfundrequisition.ID` -> `ReqNo`; type 10: `tblpettycash.ID = transactionId` -> `ReceiptNo`; type 13: `tblpettycash.TransactionType=13 AND TransactionId=journal transactionId` when the transfer created a petty-cash receipt | Reliable for those mappings, subject to legacy duplicate business numbers (`ReqNo` and some receipt numbers are not unique). The database ID link, not the displayed number, is the key. Type 9 has only its source row ID/reference, not a separate receipt number. Transfers to cash accounts other than 98/194 have no petty-cash receipt number. |
| Reference | Ledger first; fallback to `tblexpenses.paymentReference`, `tblbanktransactions.Reference`, contribution detail/header context, `tblbankpostings.reference`, requisition/entry reference, or type-10 `tblpettycash.Reference` | Reliable by source identity. This preserves the 150 dev references missing from migrated entry headers. |
| Payee | No general ledger or expense payee field exists | **Not recoverable for ordinary expenses.** `tblexpenses.postedBy` is the clerk, not the payee. A type-12 `RequestedBy` user is the requester, not necessarily the payee. Do not label either as payee. |
| Received from | Type 1 contribution details identify a member/group/district/service/congregation according to `category`; aggregate by header ID. Type 13 can say the funding bank. Type 10 has only bank, reference and narration. | Type 1 is reliable but may have multiple contributors on one receipt/ledger debit and must be aggregated without multiplying the journal line. Type 10 has no person/organization "received from" field. Bank source is not the same as a human payer. |
| Funding/contra account | Other line(s) of the same journal entry | Reliable as accounting data, but an entry may have multiple contra lines, so aggregate names rather than assuming one. |
| Petty-cash receipt number | `tblpettycash` for type 10 and type-13 transfers into 98/194 | This is the main metadata reason to retain the table. It is absent for journals, approvals, ordinary receipts and non-98/194 cash transfers. |

### NULL link convention

- All 798 active type-2 rows have `TransactionType IS NULL` and `TransactionId IS NULL`; join them through `ExpenseId` to a type-2 entry.
- A type-10 row also leaves those two columns NULL; its journal source link is implicit: type 10 and `journal_entries.transactionId = tblpettycash.ID`.
- Type 13 uses explicit `TransactionType=13` / `TransactionId`.
- Dev has no active duplicate source links, but the table has no unique key enforcing one active row per `ExpenseId` or type-13 transaction.

Source metadata must be pre-aggregated to one row per `(congregationId, transactionTypeId, transactionId)` before joining. Joining contribution details directly would duplicate a cash journal line and corrupt report totals.

## 4. Accounts the report could offer

No choice is made in this inventory.

| Candidate | Dev congregation 11 result | Effect / trade-off |
|---|---|---|
| Keep 98 and 194 only | 98 **petty cash**; 194 **petty cash development** | Smallest semantic change and keeps the name "Petty Cash". It excludes cash activity on 34 and 121 even though those accounts are valid cash payment accounts. Account 194 is congregation-11-owned and is correctly hidden elsewhere. |
| All `PAY_CASH` accounts from `Ledger::listPaymentAccounts()` | 34 **cash at hand**, 98 **petty cash**, 121 **cash holding account**, 167 **fixed deposits**, 194 **petty cash development** | Reuses the exact posting rule and automatically tracks chart changes. It broadens this into a cash-account book and includes fixed deposits because account 167 is a posting descendant of cash-and-cash-equivalents. |
| Explicit cash-account selector / reportable-cash classification | Whatever finance marks reportable; it could start with 98/194 and optionally include 34/121 while excluding 167 | Best business semantics, but needs a defined source of configuration (new flag/table or an approved fixed configuration) and account-admin rules. It must still validate ownership, active/deleted state and posting status with Ledger rules. |

Recommendation for the owner's decision: if this task must avoid a new chart classification, retain 98/194 for the first switch. If finance wants one general cash book, use an explicit reportable-cash selector rather than silently treating every `PAY_CASH` descendant (especially fixed deposits) as petty cash.

## 5. Row-level reconciliation on dev

Matching used the source identity described above, the session congregation, an active entry, and a journal line with the same account, side and amount. Amounts were compared as DECIMAL values.

| Classification | Active rows | Amount |
|---|---:|---:|
| Exact account + side + amount | 918 | 22,217,256.00 |
| Different ledger account | 3 | 41,000.00 |
| No active posting | 0 | 0.00 |
| Different amount | 0 | 0.00 |
| Different side | 0 | 0.00 |
| Unlinkable / other mismatch | 0 | 0.00 |
| **Total** | **921** | **22,258,256.00** |

All 921 active rows belong to congregation 11. All 798 expense source rows exist, are non-deleted and approved; all 123 type-13 source transactions exist and are active.

The exceptions are:

| `tblpettycash.ID` | Date | Source | Book expectation | Active ledger line | Amount | Reference / narration |
|---:|---|---|---|---|---:|---|
| 643 | 2024-11-01 | Expense 675 | Cr 98 | Cr 121 | 3,000.00 | `bk173ro3z5` / visitation |
| 665 | 2024-10-05 | Expense 442 | Cr 98 | Cr 121 | 20,000.00 | `pvc567` / dinner |
| 780 | 2024-10-05 | Expense 466 | Cr 98 | Cr 121 | 18,000.00 | `pcv 567` / deworming and lifestyle check up |

Matched rows by fiscal year and type:

| Fiscal year | Type | Account | Rows | Amount | Exceptions |
|---|---:|---:|---:|---:|---:|
| 2023-2024 | 2 | 98 | 178 | 2,180,067.00 | 0 |
| 2023-2024 | 13 | 98 | 27 | 2,244,200.00 | 0 |
| 2024-2025 | 2 | 98 | 165 | 1,923,861.00 | 3 / 41,000.00 |
| 2024-2025 | 13 | 98 | 34 | 3,325,380.00 | 0 |
| 2025-2026 | 2 | 98 | 419 | 4,593,551.00 | 0 |
| 2025-2026 | 13 | 98 | 62 | 6,868,582.00 | 0 |
| 2025-2026 | 2 | 194 | 35 | 1,121,615.00 | 0 |
| 2026-2027 | 2 | 98 | 1 | 1,000.00 | 0 |

There are zero persisted type-10 rows in dev, including deleted rows. The type-10 tests create rows inside self-cleaning/rolled-back test work, so this production-like dataset cannot prove a historical type-10 join. The code contract is clear and must be covered in the new report tests: entry type 10 uses the inserted `tblpettycash.ID` as `transactionId`, with a debit to the selected petty-cash account.

## 6. Read-only export of the old figures

Before deploying the new query, create a read-only export package outside the web root and do not import it back into the database. Recommended package layout:

```text
pettycash-legacy-export-<timestamp>/
  manifest.json
  summary.csv
  congregation-<id>/account-<id>/<fiscal-year>-rows.csv
  congregation-<id>/account-<id>/<fiscal-year>-rendered.html
  query.sql
  SHA256SUMS.txt
```

For every congregation with `tblpettycash` rows, every offered account, and every fiscal year:

- Export the exact active legacy movements, including source row ID, raw date, ReceiptNo, Debit, Credit, Reference, Narration, ExpenseId, TransactionType and TransactionId.
- Store opening before `startDate`, period debits, period credits and closing through `endDate` in `summary.csv`.
- Save the exact old report HTML as users would see it, because the old procedure's formatted-date ordering is part of the historical presentation.
- Put congregation/account/year IDs and names, date bounds, closed/deleted flags, row count, extraction UTC time, database name, application commit, query hash and exporter version in the manifest.
- Hash every artifact and make the package read-only in finance's controlled storage. Have finance sign the `summary.csv` figures before the switch.
- Also generate a normalized raw CSV ordered by actual `(TransactionDate, ID)`; do not replace the exact-rendered copy with it.

The exporter should use a consistent read-only transaction (`START TRANSACTION READ ONLY WITH CONSISTENT SNAPSHOT` where supported), parameterized SELECTs only, and fail if its recomputed summary does not match the row files. This inventory did not create the export or write any database row.

## 7. Proposed design and test plan

### Query and model design

Use one model operation to return a report DTO containing account, range, opening, movements, debit total, credit total and closing. Do not call a totals method repeatedly.

Accounting selection:

```sql
-- opening
SUM(CASE l.dc WHEN 'DEBIT' THEN l.amount ELSE -l.amount END)
WHERE e.congregationId = :congregation
  AND e.deleted = 0
  AND l.accountId = :account
  AND e.entryDate < :start

-- movements
WHERE e.congregationId = :congregation
  AND e.deleted = 0
  AND l.accountId = :account
  AND e.entryDate BETWEEN :start AND :end
ORDER BY e.entryDate, e.ID, l.lineNumber
```

The implementation can use CTEs/window functions to return the opening and running balance in one query, or use two parameterized SELECTs inside one consistent read-only transaction. Totals must be calculated from the same raw DECIMAL result set, not by reparsing formatted HTML. Format money and dates only at the view boundary.

Join `tbltransactiontypes` and pre-aggregated source metadata by `(congregationId, transactionTypeId, transactionId)`. Use guarded joins for type 1 contributions, type 2 expenses, type 9 manual bank postings, type 10 petty-cash receipts, type 12 requisitions and type 13 bank transactions. Type 5 uses `journalNo` and has no separate source table. Unknown/future types must still render from the journal header instead of disappearing through an inner join.

The date range reads closed years exactly like open years. Do not filter out a row because its fiscal year is closed or because an old fiscal-year definition is marked deleted; the immutable journal entry date is the accounting filter. Show a year/closed label only as context. No report action may call a posting, void or year-unlock path.

Validate raw GET strings on the server: scalar exact `Y-m-d` dates, `start <= end`, digit-only account ID, allowed account for the session congregation. Return a controlled 4xx/validation fragment for arrays, malformed dates and disallowed accounts instead of silently falling back or producing 1970-01-01.

Render an explicit opening row and closing row (or labelled summary cards), with negative opening displayed on the credit side or as a signed balance—not as a negative debit. Recommended columns are Date, Transaction Type, Source No, Narration, Debit, Credit, Reference and Running Balance. Every database-backed value must be HTML-escaped.

### Automated tests

Model/query tests:

- Opening excludes the start date; movements include both range endpoints.
- Correct debit-minus-credit signs, negative opening, running balance and closing.
- Active entries only; a voided entry is excluded even if its source row remains.
- Exact account and session congregation scoping, including a same-ID/source fixture in another congregation.
- Closed-year entries remain readable without changing fiscal-year flags or any data.
- Type 1, 2, 5, 9, 10, 12 and 13 metadata joins; a missing source document still renders the journal line with a fallback label.
- A multi-detail contribution produces one cash journal movement, with contributors aggregated rather than duplicated.
- Historical source-reference fallback, including blank ledger references for types 2/13.
- Type-10 implicit ID join and type-2 `ExpenseId` legacy join.
- The three known different-account rows do not appear in account 98 merely because `tblpettycash` says 98; they appear under the ledger account 121 if that account is offered.
- Query count remains fixed as rows grow; add an EXPLAIN/large-fixture check. The current indexes use `journal_lines.ix_jl_account` then the journal-entry primary key on dev; no N+1 source query is acceptable.
- DECIMAL assertions, not float equality.

HTTP tests through Apache:

- Login is required and a restricted user without `petty cash utilization` is denied for both page and data endpoint.
- Valid request returns the selected account/range, opening, each transaction type, totals and closing.
- Array inputs, `98abc`, negative/zero/disallowed account IDs, impossible dates and reversed ranges are rejected.
- Other-congregation and deleted-entry markers never appear.
- A closed-year request succeeds and leaves the year closed.
- Narration/reference/source values containing HTML are escaped.
- Account-list assertions match whichever option the owner chooses in section 4.
- Regression requests cover the petty-cash receipt list, expense voucher print and year-delete blocker because those retain source-table reads.

Browser check:

- Preview each offered account in an open and a closed fiscal year.
- Verify true chronological order, opening placement, debit/credit side, running/closing arithmetic and responsive layout.
- Search, pagination and a second Preview must work without duplicate/stale DataTable wrappers.
- Excel, PDF and print must include congregation, account, range, generated time, opening and closing, all filtered rows and clearly labelled totals. Compare the new report to the signed legacy export and the ledger reconciliation.
- Confirm no POST, database mutation, fiscal-year toggle or production endpoint is involved.

## 8. Issues found in the inspected paths

No issue below was changed in this read-only task.

### High priority for the report switch

1. **Missing authorization:** `Reports::pettycash()` and `pettycashrpt()` do not call `checkrights()`, although dev has the `petty cash utilization` form right. Any authenticated user who knows the URL can reach them.
2. **Stored XSS risk:** `pettycashrpt()` concatenates Narration and Reference into HTML without escaping. The current movement fields come from user-controlled source text.
3. **Wrong dates and chronological order:** the procedure formats dates as `dd/mm/yy`, orders by that string alias, and the controller then passes the locale-formatted string through `strtotime()`. On this dev PHP, `01/10/23` becomes 2023-01-10 and `13/10/23` fails and displays 01-01-1970. A real 2023-2024 procedure call also begins with 01/01/24 and 01/05/24 before its 01/10/23 opening row. Keep dates as SQL dates, order by the raw date/stable IDs, and format only once at the view boundary.
4. **Weak server validation:** malformed dates pass through `strtotime()`/`date()` (often becoming 1970-01-01), reversed ranges are checked only in JavaScript, array-shaped inputs can raise warnings/errors, and an invalid account silently changes to another account.
5. **Ten database operations per preview:** one procedure call plus three calls to `debitcredittotal()`, each of which executes three queries. That is nine repeated total/opening queries. They are not a consistent snapshot.
6. **Legacy table is a partial ledger:** journals, approvals and other posting paths are omitted by design. The closed-year figures therefore differ materially from the canonical ledger.
7. **Opening presentation is wrong for an overdraft/negative balance:** the procedure always puts opening in Debit. Account 194 therefore displays `-1,121,615.00` as a negative debit in 2026-2027.
8. **Footer semantics are unclear:** debit total includes opening, credit total does not have a corresponding signed opening treatment, and closing is displayed under the Reference column with no label.

### Data/source issues

9. Three active type-2 mirror rows (643, 665, 780) point at account 98 while their entries credit 121, totaling 41,000.00. The ledger report must follow 121; any correction is a separate signed data task.
10. Expense 1249 has a 40,790.00 debit to account 98. It is a legitimate ledger-side appearance that the type-2 mirror design cannot represent because that mirror records only paid-from credits.
11. All type-2 and type-10 rows use NULL `TransactionType`/`TransactionId`; source inference differs by type. There is no database uniqueness constraint on active source links.
12. Dev has no persisted type-10 row, so the migration needs a deliberate test fixture rather than assuming historical coverage.
13. There are 150 exact matched legacy rows whose journal header reference is blank while the source document and `tblpettycash` agree on a nonblank reference. A ledger-only header display would lose visible references unless source fallback is implemented.

### UI/export and retained-source issues

14. The view initializes `$('#table').DataTable()` before a result table exists, replaces `#results` before destroying the previous instance, and assigns `total` without declaration. Repeated previews can leave brittle DataTable state/global variables.
15. The current Excel/PDF/print output has no report context or immutable audit manifest; it is unsuitable as the only record of figures users previously saw.
16. `vw_cashreceipts` uses inner joins to both account rows. It is appropriate for the source screen, but any broken legacy BankId/account reference makes a receipt disappear rather than show an "unknown account" marker. `BankId` has no foreign key in `tblpettycash`.
17. The cash-receipt controller normalizes date and account POST fields before model validation; malformed/array input can error and digit-prefixed account values can be cast to valid IDs. This is the same strict-input class fixed for approvals and should be a separate hardening item.
18. The cash-receipt list/form contain older unescaped database-backed fields (bank/reference/description) and the list heading says `Rerefence`. These are outside the report query but were found while tracing the retained source screen.
19. `Banktransaction::savePettyCash()` updates only the first active type-13 mirror if duplicates exist, unlike `Expense::syncPettyCash()`, which soft-deletes extras. Current dev has no duplicate active link, but the database does not prevent one.
20. The SQL objects are created with the dev `root@localhost` definer / legacy client charset. Removing the report's routine dependency is preferable to deploying another definer-bound report procedure; `vw_cashreceipts` still needs the production schema-diff/runbook treatment.

The CodeRabbit CLI required by the local review skill is not installed in this workspace, so this issue review was performed manually using repository-wide searches, full path inspection and read-only database metadata/data queries.

## 9. Questions for the owner

1. **Which accounts should be offered?** Recommendation: keep 98/194 for the first ledger switch if the screen remains "Petty Cash"; otherwise approve an explicit reportable-cash selector. Do not automatically include all `PAY_CASH` accounts without accepting that 167 fixed deposits will appear and the report becomes a general cash book.
2. **Should the report gain Transaction Type, Source No and Running Balance?** Recommendation: yes. They make newly visible journals/approvals explainable. Keep Payee out until a real payee field exists; label aggregated type-1 data "Received from" only where it is meaningful.
3. **How should historical blank ledger references display?** Recommendation: ledger reference first, then the source document's reference. The source recovers all 150 dev cases; use `tblpettycash` only for type-10/source metadata, not as the accounting fallback.
4. **Should new expense approvals continue writing type-2 mirror rows after the report switches?** Recommendation: no. Freeze old rows for audit/export; source expense fields plus the ledger contain everything the mirror holds. Keep type-10 and type-13 receipt-number metadata.
5. **Do finance users accept the documented closed-year figure changes without correcting old entries?** Recommendation: sign the legacy export and ledger comparison, then display the ledger as-is. Handle the 41,000 stale mirror rows and expense 1249 only in separate, explicitly approved data tasks.
6. **Where should the signed legacy export be retained, and for how long?** Recommendation: finance-controlled, read-only storage outside the application/web root, with SHA-256 manifest and the normal accounting-record retention period.
7. **Should date ranges cross fiscal years?** Recommendation: yes, because the ledger query is date-based; show the included years/closed status and keep closed years readable. Do not silently clip the range.
8. **Which right protects the screen?** Recommendation: use the existing `petty cash utilization` right on both the page and AJAX endpoint, with admin/superuser behavior left consistent with `checkrights()`.
9. **Should type-10 linkage be made explicit in `TransactionType`/`TransactionId` later?** Recommendation: document and test the current ID link for this report task; consider normalizing new type-10 metadata in a separate schema task, with no old-data backfill required for the ledger balance.

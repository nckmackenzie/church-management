# `tblledger` Inventory (Task 6)

Read-only inventory of every `tblledger` / `tblbankpostings` access and every chart-of-accounts
(`tblaccounttypes`) access relevant to replacing `tblledger` with the `journal_entries` /
`journal_lines` header/lines model. No application code was changed to produce this document.
The sections below describe the code **as it was before task 8**; the migration status table
records what has changed since.

Framework: custom MVC, not Laravel. Controllers in `app/controllers/*.php`, models in
`app/models/*.php` (PDO via a `Database` wrapper), views in `app/views/<module>/*.php`, shared
procedural helpers in `app/helpers/url_helper.php`. Stored procedures/functions dumped (schema
only, no triggers exist) in `db_routines.sql`.

**Important context found during this inventory, not asked for but directly relevant:** a
migration script `scripts/migrate_ledger.php` already exists in the working tree (untracked,
per `git status`) with `--dry-run`/`--commit` modes, and log files for both a dry run and a
commit run already exist under `scripts/logs/` (also untracked), both dated today. This looks
like work-in-progress on exactly this migration — flagging it since it wasn't mentioned in the
task brief.

## Migration status (task 8)

A type is **Done** only after the browser test on dev (create, edit, delete in the UI; check
`journal_entries` / `journal_lines` / `tblbankpostings`; old reports match via the mirror).

| Step | Types | Code | Library tests | Browser test | Status |
|---|---|---|---|---|---|
| 8.1 | 5 Journals | **Migrated** 2026-10-21 (8.1 + 8.1b) | 56/56 pass | Passed 2026-10-21 | **Done** |
| 8.2 | 8 Bank opening balance + bank create/delete | **Migrated** 2026-10-21 (8.2 + 8.2b) | 56/56 pass | Passed 2026-10-21 | **Done** |
| 8.3 | 9 Bank postings, 13 Bank transactions (deposits form removed) | **Migrated** 2026-10-21 (8.3 + 8.3b + 8.3c) | 71/71 + 57 + 48 functional | Passed 2026-10-21 | **Done** |
| 8.4 | 1 Contributions, 10 Petty cash | **Migrated** 2026-10-21 (8.4b–8.4d) | 115 + 163 + 28 + 23 | Passed 2026-10-22 | **Done** |
| 8.5 | 2 Expenses | **Migrated** 2026-10-22 (8.5c + 8.5d) | 123 ledger + 255 expenses (+ 163 + 28 receipts / year lock) | Passed 2026-10-22 | **Done** (8.5f number-sequence fix pending) |
| 8.6 | 3/4 Pledges | Old code | – | – | Not started |
| 8.7 | 6, 7, 15 Suppliers | Old code | – | – | Not started |
| 8.8 | 14, 16 Customer invoices / payments | Old code | – | – | Not started |
| 8.9 | 11, 17, 12 MMF / group collections / group funds | **Migrated** 2026-10-22 (+ hotfix `b853aea`) | 169 + 261 expenses | Browser test pending | **Code done**, data repairs written, not run |
| 8.10 | Account screens | Old code | – | – | Not started |
| 8.11 | Clean-up of `url_helper.php` helpers | Old code | – | – | Not started |

### Inferred accounts — ask the user before migrating (decision 16)

The old code picked these accounts in the backend instead of letting the user choose. **The new code must not infer any account**: before each step's prompt, ask the user how each one is chosen (normally a form dropdown validated by `Ledger`). Status per account:

| Step | Type / path | Account the old code assumed | How it is chosen now |
|---|---|---|---|
| 8.2 | 8 Bank opening balance | 'opening balance equity' (by name, auto-created) | **Fixed system account ID 20**, confirmed |
| 8.3 | 9 Bank posting, method 1 (create only) | 'cash at hand' (update used the chosen contra) | User's contra account (dropdown) |
| 8.3 | 13 Bank transaction deposit / withdrawal | 'cash at hand' (34) | **"Crediting/Debiting account" dropdown** (PAY_CASH = accounts under 22, decision 19), stored in `CashAccountId` (8.3b/8.3c) |
| 8.3 | 13 Transfer | target by name (`getAccountName`) | Transfer dropdown by ID |
| 8.4 | 1 Contributions, 10 Petty cash receipts | 'cash at hand' (34) for cash; generic 'cash at bank'; 'petty cash' (98) | Cash: PAY_CASH dropdown, 34 preselected; mpesa/cheque/bank: chosen bank; petty cash: dropdown 98/194 + bank (decisions 22, 24) |
| 8.4 | 13 Transfer → petty cash receipt (`Banktransaction::savePettyCash()`) | receipt in `tblpettycash` only when the target is **98** (not 194 'petty cash development') | Any petty cash account (98 or 194) creates the receipt (decision 24) |
| 8.5 | 2 Expenses | 'petty cash', 'development petty cash' (no such row; real one is 'petty cash development'), 'cash at hand', 'cash holding account'; `Expenses.php:61` default 'petty cash' | **Nothing is inferred (decision 16).** One "Paid from" account for every method, stored in `tblexpenses.PaidFromAccountId` and chosen on the form: cash = a `PAY_CASH` account (98 preselected), M-Pesa / cheque / bank = a `PAY_BANK` account; the expense (debit) account is a posting account of types 2, 3, 4 chosen on the form. Post = Dr `accountId` / Cr `PaidFromAccountId`. `deductfrom` / `bankId` are legacy and no longer read |
| 8.6 | 3/4 Pledges | 'cast at bank' (typo) | **Ask** (bank / cash dropdown?) |
| 8.7 | 6 Supplier invoices, 7 payments, 15 opening balance | 'accounts payable' (no chart row), parent 'payables and accruals' | **Ask** (question C) |
| 8.8 | 14 Customer invoices, 16 payments | 'accounts receivable' (ID 9 by name, `$three = 3`) | **Ask** (fixed system account or chosen?) |
| 8.9 | 11 MMF receipts | 'groups balances held' (no chart row), 'cash at bank' | **Decided:** debit = a "Group funds held" dropdown (active posting liability accounts under 25 / 118), credit = the chosen bank (PAY_BANK) |
| 8.9 | 17 Group collections, 12 Group fund approvals | 'cash at bank', group funds-held accounts | **Decided:** 17 = Dr the sub account's bank / Cr its account, read on the server; 12 = Dr **121 (fixed system account, `Ledger::CASH_HOLDING`)** / Cr a "Paid from" account (cash = PAY_CASH, 98 preselected; M-Pesa / cheque / bank = PAY_BANK + bank row) |

**8.1 journals — what changed (supersedes the journal entries in §1, §3, §5, §8, §9, §10 below):**

- `app/models/Journal.php`: `journalNo()`, `getjournalno()`, `checkexists()` removed; `peekNumber()` added (`Ledger::peekNextJournalNo`, display only). `createupdate()` uses `Ledger::nextJournalNo()` (new) / existing number (edit, refused if voided or missing), `Ledger::post()` by account ID, then `Ledger::syncBankPostings(..., 'journal')`, all in one transaction. `delete()` is now in a transaction: `Ledger::void()` + `Ledger::voidBankPostings()`. `getjournal()` now returns `Ledger::getEntry()` (name join gone). `getAccounts()` lists exactly the posting accounts (shared + banks included, reporting accounts excluded). Direct `tblledger` INSERT/DELETE/UPDATE removed.
- `app/controllers/Journals.php`: number endpoint returns the peek number; save message shows the saved number; `LedgerException::getErrors()` shown (422 / flash).
- `public/dist/js/pages/journals/index-v1.js`: `firstno` removed; success alert shows the server message. (Old `index.js` not loaded, untouched.)
- **Journals now write `tblbankpostings`** for bank lines (method 'Journal', uncleared; edit keeps cleared rows whose bank/side/amount are unchanged). §9 "journals never write it" no longer holds.
- Left on the old path: `Journal::getJournalDetails()` (serves the **expense** posting preview, type 2, still reads `tblledger` and has `'petty cash'` / `'cash at bank'` literals → step 8.5); Excel import `validateimport` / `Journal::getaccount()` still matches by account **name** (resolves to an ID; Ledger rejects non-posting accounts); `Report::GetJournalReport()` and all reports (task 9, work via the dev mirror).
- `app/libraries/Ledger.php`: `syncBankPostings()`, `voidBankPostings()`, private `bankMethodId()` added (unknown method → `LedgerException`).
- DB (dev): `tbltransactionmethods` row **7 'Journal'** added (`scripts/sql/add_journal_method.sql` = F13 in `ledger_fixes.sql`; **must run on production at cutover**).
- New read-only check: `scripts/sql/ledger_verify_v10.sql` (bank lines vs bank postings). On dev: part A 0 rows; part B 2 legacy groups (type 1 `transactionId = 0`, receipt 1890), resolved by F14 + exclusion of bank rows 682, 696, 1565 (handover question F).

**8.2 banks — what changed (supersedes the bank entries in §1c, §3, §7 below):**

- `app/models/Bank.php`: `create()` posts the opening balance with `Ledger::post()` (type 8, Dr the new bank ID / Cr opening balance equity **ID 20**) + `syncBankPostings(..., 'B/F')`; the by-name lookup/auto-create of 'opening balance equity', `saveToLedger`, `saveToBanking`, `'cash at bank'` and `getparentgl()` are gone. `checkreferenced()` returns reasons: active bank rows or active journal lines other than the bank's own type-8 ones (old guard counted every `tblbankpostings` row, deleted and own type-8 included, so a bank with an opening balance could never be deleted). `delete()` runs in one transaction: ownership check, guard, soft delete, `Ledger::void()` + `voidBankPostings()` (old: hard `DELETE FROM tblledger`). `update()` unchanged (name / account no only; the opening balance is not editable).
- `app/controllers/Banks.php`: shows Ledger / guard messages instead of generic ones.
- 8.2b: `Bank::openingBalanceError()` (controller + model) rejects negative, non-numeric and > 2-decimal opening balances; the opening-balance bank row is created cleared (`clearedDare` = as-of date); `checkreferenced()` also refuses delete when that row is cleared. Nothing in the app un-clears a bank row (`Clearbanking` only clears / changes the date), so a bank with an opening balance can't be deleted from the UI.

**8.3 bank postings / bank transactions — what changed (supersedes §1b/§1c/§3/§5/§9 entries for types 9 and 13):**

- `app/models/Bankposting.php` (type 9): the `tblbankpostings` row is the source record (no `syncBankPostings`); `create/update/delete` in one transaction with `Ledger::post()` / `void()` (hard `DELETE FROM tblledger` removed); bank by ID (PAY_BANK), contra = non-bank posting account (old: generic 'cash at bank', and create credited 'cash at hand' while update credited the contra); edit un-clears the row only when bank, side or amount change; contra dropdown congregation-filtered, posting accounts only; edit/update/delete refuse any row that isn't an active type-9 row of the congregation (old: any row by ID, any congregation).
- `app/models/Banktransaction.php` (type 13): `Save/Update/Delete` use `Ledger::post()` + `syncBankPostings()` / `void()` + `voidBankPostings()`; dropdowns from `Ledger::listPaymentAccounts()`; bank-to-bank transfers write two bank rows (old: one); ownership check on update/delete; sub-account transfers (type 6) post nothing. **8.3b:** 'cash at hand' (34) replaced by a user-chosen "Crediting/Debiting account" (new column `CashAccountId`, backfilled 34 — what the old code posted); bank row methods per type (1 deposit, 2 withdrawal, 1 transfer); `tblbanktransactions.Amount` int → decimal(18,2) and no `(int)` cast; sub-account `:reference` bound in `Save()` and `Update()`'s sub-account loop now writes the same columns as `Save()`. Script `scripts/sql/bank_txn_cash_account.sql` (production at cutover). **8.3c:** PAY_CASH limited to non-bank posting accounts under 22 (dev: 34, 121, 167, 98, 194); `Banktransaction::checkSubaccounts()` validates sub-account transfers and deposit splits server-side (congregation, bank, duplicates, amounts, total); `Banktransactions::edit()` redirects for a missing transaction; `saveLog` on bank transaction save/update/delete; shared `Ledger::amountError()`.
- **Deposits:** `app/controllers/Deposits.php` and `app/views/deposits/` deleted (still routable before, on the legacy path); `app/models/Deposit.php` reduced to `GetBanks()` (used by `Payments.php:14,31`).
- `decryptId()` returns 0 for non-numeric input.
- `Deposit.php` / `tbldesposits`: form removed from the app; out of scope (type 13 = bank transactions only).
- `Clearbanking::delete()`, `Clearbankings::delete()` and the unused delete modal removed. `views/banks/add.php`: opening balance `step="0.01" min="0"`.

**8.4 inventory (2026-10-21):** payment methods are `tblpaymentmethods` (1 cash, 2 mpesa, 3 cheque, 4 bank; only rule `paymethod == 1` → cash). Type 10 never used on dev. Collection rows take their income account from `tblbanksubaccounts.AccountId`. 23 existing-code issues (receipt number check/sequence, unvalidated edit id → `transactionId = 0`, ownership and closed-year gaps, petty cash screen editing transfer receipts, petty cash reports ignoring `Deleted`, stale sub-account rows) — all to be fixed in 8.4b.

**8.4c/8.4d (2026-10-21):** closed-year lock moved into `Ledger::post()` / `void()` (covers every migrated type; unmigrated types still unprotected until their step); `Year::delete()` guarded (closed and empty years only), overlap check includes deleted years; `tblpettycash.PettyCashAccountId` FK. Receipts with legacy `subaccount = 0` editable again.

**8.5 expenses — what changed (2026-10-22; supersedes the expense entries above, incl. the `Journal::getJournalDetails()` note in the 8.1 block):**
- **Schema (8.5c + 8.5d):** `tblexpenses.PaidFromAccountId` (FK to `tblaccounttypes`, backfilled from the ledger credit), `amount` DECIMAL(18,2), `status` default 0, indexes on `tblpettycash(ExpenseId)` and `tblbankpostings(transactionType, transactionId)`, `fn_checkreferencedaccount` also counts `PaidFromAccountId` (`scripts/sql/expense_paidfrom_schema.sql`, backfill `scripts/expense_paidfrom_backfill.php`); `tblexpenses.ID` is now AUTO_INCREMENT (`scripts/sql/expense_id_autoincrement.sql`; the old `getLastId()` was COUNT + 1). `deductfrom` / `bankId` stay as legacy columns: new rows leave them NULL, updates leave them alone, nothing reads them.
- **Posting (`Expense::post()`, the only place that builds the lines):** on approve (and on edit of an approved expense, in place) Dr `accountId` / Cr `PaidFromAccountId`, date = expense date, reference = payment reference, memo = narration, `transactionTypeId` 2. Bank-paid (M-Pesa, cheque, bank): `Ledger::syncBankPostings(..., 'Withdrawal')`, cleared flag kept when unchanged. Paid from 98 / 194: one active `tblpettycash` row (`PettyCashAccountId` = the paid-from account), updated in place, soft-deleted when the account is no longer a petty cash account, inserted when it becomes one; `tblpettycash` rows are never hard-deleted.
- **Rules at every save (create, edit, approve):** expense account = posting account of types 2/3/4, no bank, valid for the congregation, `forGroup = 1` for group expenses (`Expense::GetExpenseAccounts()` feeds add, edit and AJAX; `accountProblem()` is the server check; on edit the expense's own inactive account is also listed); paid-from account by payment kind (`Ledger::assertPaymentAccount`); not the same account on both sides; requisition (approved, same congregation, same group / district, balance >= amount counting approved non-deleted expenses except this one; a new-style requisition = its type-12 entry debits 121, must be paid from 121; 121 needs a requisition; requisitions are paid in cash). Draft create / edit / delete also refuse a date in a closed year or with no financial year.
- **Actions:** per-expense Unapprove (`Ledger::void` + `voidBankPostings`, petty cash row soft-deleted, status 0) replaces `resetApprovals()` (removed); delete of an approved expense voids everything; every action loads the expense by ID + session congregation `FOR UPDATE`, refuses deleted expenses, validates the ID, writes `tbllogs`, and shows `LedgerException::getErrors()` to the user. Roles are enforced in `Expense::roleProblem()` exactly like the buttons: drafts approve / edit / delete by `userType < 3` or `> 4`; approved edit / delete / unapprove by `userType < 3`.
- **Vouchers and number sequences (8.5f):** `Ledger::SEQ_VOUCHER` in `receipt_number_seq` (kind `voucher`), per congregation + fiscal year. **Every kind (receipt, voucher, petty cash): `nextNumber()` = max(counter, highest number stored in the source table, deleted rows included) + 1**, so it always equals `peekNextNumber()` and never falls behind a number carried into the scope. **Year change = new number:** an edit (or approve) that moves an expense or a receipt into another fiscal year takes the next number of the new year's sequence and retires the old one (message "renumbered from X to Y because the date moved to <year>", both numbers in `tbllogs`); an edit inside the same year keeps its number (replaces the 8.4 / 8.5d rule "kept even when the date moves year"). Petty cash numbers have no year (safety net only). Existing duplicates are left as they are. The form shows `peekNextNumber()`.
- **View posting:** `Expense::getJournalDetails()` (voucher print) and the type-2 branch of `Journal::getJournalDetails()` (eye icon) are one method: approved = `Ledger::getEntry()`; draft = a two-line preview marked "Not posted".
- **Removed from the expense path:** `saveToLedger` / `saveToBanking` / `deleteLedgerBanking` / `softdeleteLedgerBanking`, `computeLedgerTotals()` / `assertLedgerBalanced()`, `getparentgl()`, every account literal, the hard-coded options of `add.php` / `edit.php`, `VoucherNo()`, `GetVoucherNo()`, `GetRequisitions()`, `YearIsClosed()`, `resetApprovals()`.
- **Left as is (decision 14):** drafts still count as spent in `CheckOverSpent` / `getexpensedamount` / `getgroupexpensedamount` / `getrequisitionbalance`; only the `$_SESSION['congid']` typo was fixed. Requisitions 201 and 58 (type-12 approvals posted twice) are untouched (task 8.9).
- **Tests:** `scripts/test_expenses.php` (255 checks, self-cleaning), voucher checks added to `scripts/test_ledger.php`; V10 and 8.5c Q17 clean.

**8.9 MMF receipts / group collections / group fund approvals — what changed (2026-10-22; supersedes the type 11, 12 and 17 entries in §1b, §1c, §2, §3, §5 and §9 above):**
- **Hotfix first (commit `b853aea`, `scripts/logs/hotfix-fund-approval-guard-2026-10-22.md`):** `Approve()` reloads the requisition by ID + congregation `FOR UPDATE`, requires Status 0, reads group / district, type, `DontDeduct` and purpose from the stored row, validates amount and pay date, guards the status UPDATE with `AND Status = 0`; rights + `checkcenter` on the POST; the Approve button is disabled on first submit. This is kept in the migrated code.
- **Step 0 — balance functions (`scripts/sql/mmf_balance_functions_deleted.sql`, run on production at cutover, before the 8.9 code):** `getmmfopeningbal`, `getmmfopeningbalbydate`, `getmmfopeningbal_district`, `getmmfopeningbalbydate_district` and `sp_mmf_statement` ignore `tblmmf.Deleted = 1`. `Report::GetGroup*` already filtered `Deleted = 0`. Dev balances before / after: `scripts/logs/task8.9-step0-balances-before.txt` / `-after.txt` (district 58 204,449 -> 201,589; 60 95,904 -> 91,504; 78 20,539 -> 16,739; church school 234,186 -> 225,731; woman's guild 428,661 -> 415,451; BSR 401,234 -> 389,584; the 29 deleted rows, 44,375 in all).
- **System account (decision, like 20):** `Ledger::CASH_HOLDING = 121` 'cash holding account', the fixed debit of every type-12 approval; `Expense::CASH_HOLDING` now points at it. It is a PAY_CASH posting account.
- **11 MMF receipts (`Mmfreceipt`):** Dr "Group funds held" (new required dropdown: active posting liability accounts, accountTypeId 4, children of 25 and 118) / Cr the chosen bank's own account (PAY_BANK), bank row method 2 (Withdrawal), `Ledger::post` keyed on `tblmmf.ID`. The balance is computed on the server (rows up to the date, deleted rows ignored, the record's own row excluded on edit; shown on the form by the same function). Amount numeric > 0, <= 2 decimals; reference required, <= 25 characters, unique among active type-11 receipts (was: any type, deleted rows included); `cashparent` bug gone; `edit()` null-checked. Load / update / void by ID + type 11 + congregation. Delete = void: `Deleted = 1`, `Ledger::void`, `voidBankPostings`. The index hides deleted receipts. The funds-held account is not stored on `tblmmf`: the edit form reads it from the entry's debit line.
- **17 Group collections (`Groupcollection`):** the client sends only the sub account. Dr the sub account's `BankId` (PAY_BANK) / Cr its `AccountId` (posting, active), both read on the server; a missing or inactive account, a sub account of another group / district or congregation refuses the save; `account`, `accountid`, `bankid` from the client are ignored. Bank row method 1 (Deposit). `Update()` now writes `Type`, `DistrictId` and `GroupId` (a district collection no longer gets the district ID in `GroupId`). Load / update / void by ID + type 17 + congregation; delete stays a void. `tblbanktransactions_subaccounts` is still NOT fed. Sub-account lookups are congregation-checked.
- **12 Fund approvals (`Groupfund`):** Dr 121 / Cr "Paid from": cash = a PAY_CASH account (98 preselected, 121 not offered), M-Pesa / cheque / bank = a PAY_BANK account plus a bank row (method 2). Pay method default is now cash. `DontDeduct` (stored) only decides the `tblmmf` Credit row; church-category requisitions never write one; the ledger lines are the same either way. **Unapprove** and **Reject** are refused while any non-deleted expense (draft or approved) references the requisition (the voucher numbers are listed), then `Ledger::void` (closed years refuse) + `voidBankPostings`, no hard delete of ledger rows, and the `tblmmf` delete is filtered by congregation. Reverse (rejected -> pending) stays. Reject works on a PENDING requisition only (decision: unapprove an approved one first); Unapprove only on an approved one. `GetBalance` uses the district ID for district requisitions (church = 0). A requisition edit / delete now requires Status 0 and the congregation. `requisitionStyle()` still classes a new approval as 'new' (tested).
- **Cleared bank rows and dates (decisions):** a void (MMF / collection delete, unapprove) is refused with a clear message when an active bank row is already cleared (`Ledger::clearedBankProblem`); an edit of an MMF receipt or collection is refused when it would change the bank or amount of a cleared row (date, reference and description may change). The MMF balance counts rows up to and including the date. Group collections refuse a future date (MMF and approvals already did).
- **Rights and ownership:** `checkrights` + `checkcenter` (or the congregation filter in the model) on create, edit, update, delete, approve, reject, unapprove and reverse of all three types; the Group collection JSON endpoints answer 403.
- **Other fixes:** `getamountavailable` and the Group collection AJAX endpoints `exit` after their 400; type 11 reference check scoped to type 11 and active rows.
- **Data repairs (written, NOT run):** `ledger-migration/fix_double_approval_open_year.php` (requisition no 199 / dev ID 201) and `ledger-migration/fix_double_approval_closed_year.php` (requisition no 57 / dev ID 58: direct SQL into the closed year 2023-24, needs `--i-have-sign-off`). Both find the rows by content, check counts, default to a dry run and save the removed rows as JSON.
- **Tests:** `scripts/test_8_9_group_funds.php` (169 checks, self-cleaning), `scripts/test_expenses.php` still 261 / 0.
- **Not done / left:** `ReqNo` is still `COUNT(*) + 1` (duplicates exist); the 6 approved expenses on rejected requisitions (683, 707, 746-749) and 6 overspent requisitions are old data; `getrequisitionbalance` still counts drafts; closed-year paydate is now refused by `Ledger::post`.

## Summary

| Area | Files touched | Locations found |
|---|---|---|
| 1. `tblledger` writes (INSERT/UPDATE/DELETE) | 16 model files + `url_helper.php` | ~85 (72 `saveToLedger` calls + ~13 direct SQL) |
| 1. `tblledger` reads (SELECT) | `Report.php`, `Parishreport.php`, `Tb.php`, `Journal.php`, `Expense.php`, `db_routines.sql` | ~25 PHP call sites + ~20 stored procs/functions |
| 2. Shared insert/delete helpers + callers | `url_helper.php` (4 helpers) | 4 definitions, ~110 call sites across 17 model files |
| 3. Edit/delete flows per transaction type | 16 model files | 18 transaction types profiled, 6 gaps/anomalies found |
| 4. Reports & read paths | `Report.php`, `Parishreport.php`, `Tb.php`, `Bankreconcilliation.php`, `db_routines.sql`, controllers | 6 named reports + ~17 stored procedures |
| 5. Account selection for posting | `Account.php`, `Reusables.php`, 12+ other models, views | ~25 dropdown/lookup locations |
| 6. Fixed chart-depth assumptions | `Account.php`, `accounts/index.php`, `url_helper.php`, `Reusables.php`, `Report.php`, `db_routines.sql` | 7 locations |
| 7. Account create/edit/delete screens | `Accounts.php`, `Account.php`, `Banks.php`, `Bank.php`, views | 1 main CRUD + 1 parallel Bank CRUD |
| 8. Journal numbers | `Journal.php` | 3 methods (generate, validate, consume) |
| 9. `tblbankpostings` writes | `Bankposting.php`, `Clearbanking.php`, `Expense.php`, `url_helper.php` | ~10 locations; journals never write it |
| 10. Transaction (DB) wrapping | 16 model files | All but 5 single-statement methods are wrapped |

Account-name/number legend confirmed live in this inventory (via direct read-only query against
`tblaccounttypes`):

```
ID 3   Asset (root)
 ID 22  cash and cash equivalents   parentId=3   isSubCategory=0   (reporting, level 2)
  ID 33  cash at bank               parentId=22  isSubCategory=0   (level 3 — already non-posting in the DB)
   ID 105 co-op bank                parentId=33  isSubCategory=1  isBank=1  (level 4, posting)
   ID 195 kcb development account   parentId=33  isSubCategory=1  isBank=1  (level 4, posting)
```

`isSubCategory` on "cash at bank" (33) is **already 0** in the live DB — the flag side of the
stated change is in place. What is **not** in place is the dozens of application call sites that
still post the literal string `'cash at bank'` directly into `tblledger.account`/`parentaccount`
instead of resolving/selecting the actual bank (105, 195, …). Nothing in the write path enforces
"only posting accounts may be posted to" — see §5/§6.

Transaction-type legend (reverse-engineered from code; `tbltransactiontypes`' own seed data was
not found in the repo, so this is inferred from context, not authoritative — see Open Questions):

| type | meaning | source table |
|---|---|---|
| 1 | Contribution/receipt | `tblcontributions_header` |
| 2 | Expense | `tblexpenses` |
| 3 | Pledge — initial payment at pledge creation | `tblpledges_header`/`_details` |
| 4 | Pledge — subsequent payment | `tblpledges_details` |
| 5 | Journal entry | none (`isJournal=1`, `transactionId=journalNo`) |
| 6 | Supplier invoice | `tblinvoice_header_suppliers` |
| 7 | Supplier payment | `tblinvoice_payments_suppliers` |
| 8 | Bank opening balance | `tblaccounttypes` (bank row) |
| 9 | Bank posting (manual) | `tblbankpostings` |
| 10 | Petty cash receipt | `tblpettycash` |
| 11 | MMF receipt | `tblmmf` |
| 12 | Group/district fund requisition approval | `tblfundrequisition` |
| 13 | Bank transaction / cash deposit (shared) | `tblbanktransactions` and `tbldesposits` |
| 14 | Customer invoice | `tblinvoice_header` |
| 15 | Supplier opening balance | `tblsuppliers`/`tblinvoice_header_suppliers` |
| 16 | Customer invoice payment | `tblinvoice_payments` |
| 17 | Group collection (MMF-style) | `tblmmf` |
| 18 | Bank sub-account opening balance | `tblbanktransactions_subaccounts` (does **not** write `tblledger` — confirmed) |

---

## 1. Every `tblledger` access

### 1a. Shared helper definitions (see §2 for full caller list)

- `app/helpers/url_helper.php:79` — `saveToLedger()` — write — generic ledger row INSERT, used by every transaction type below.
- `app/helpers/url_helper.php:86` — `saveToBanking()` — write — generic `tblbankpostings` INSERT.
- `app/helpers/url_helper.php:93` — `deleteLedgerBanking()` — write — hard DELETE of `tblledger` + `tblbankpostings` by `(transactionType, transactionId)` (edit path).
- `app/helpers/url_helper.php:103` — `softdeleteLedgerBanking()` — write — sets `deleted=1` on `tblledger` + `tblbankpostings` (delete path).

### 1b. Writes grouped by transaction type (via `saveToLedger`, unless noted)

- **Type 1 — Contribution/receipt**: `app/models/Contribution.php:241,270,273` (`save()`), `:494,521,524` (`update()`).
- **Type 2 — Expense**: `app/models/Expense.php:300,302,306,310,313,316,319,322,325` (`approve_expense()`), `:443,445,449,453,456,459,462,465,468` (`update()`); also direct `UPDATE ... SET deleted=1` bulk at `Expense.php:584` (`resetApprovals()`).
- **Type 3 — Pledge initial payment**: `app/models/Pledge.php:112,115,119,123` (`create()`).
- **Type 4 — Pledge subsequent payment**: `app/models/Pledge.php:186,190,194,198` (`pay()`).
- **Type 5 — Journal**: `app/models/Journal.php:88` (`createupdate()`, direct INSERT, not via helper); delete at `:72` (hard) and `:148` (soft) — see §3. **[Migrated in 8.1]**
- **Type 6 — Supplier invoice**: `app/models/Supplierinvoice.php:177,186` (`create()`), `:254,263` (`update()`).
- **Type 7 — Supplier payment**: `app/models/Payment.php:77,80,83` (`Create()`).
- **Type 8 — Bank opening balance**: `app/models/Bank.php:80,82` (`create()`).
- **Type 9 — Bank posting**: `app/models/Bankposting.php:98,100,103,105` (`create()`), `:160,162,165,167` (`update()`).
- **Type 10 — Petty cash receipt**: `app/models/Cashreceipt.php:53,56` (`Save()`), `:97,100` (`Update()`).
- **Type 11 — MMF receipt**: `app/models/Mmfreceipt.php:92,95` (`CreateUpdate()`, both branches).
- **Type 12 — Group fund approval**: `app/models/Groupfund.php:197,219` (`Approve()`).
- **Type 13 — Bank transaction / cash deposit**: `app/models/Banktransaction.php:146,148,153,155,164,166` (`Save()`), `:290,292,297,299,308,310` (`Update()`); `app/models/Deposit.php:56,58` (`CreateUpdate()`).
- **Type 14 — Customer invoice**: `app/models/Invoice.php:145,152` (`create()`), `:214,221` (`update()`).
- **Type 15 — Supplier opening balance**: `app/models/Supplier.php:73,75,78,80` (`CreateUpdate()`, new-supplier branch).
- **Type 16 — Customer invoice payment**: `app/models/Invoice.php:356,361,365,369` (`payment()`).
- **Type 17 — Group collection**: `app/models/Groupcollection.php:94,98` (`Save()`), `:138,142` (`Update()`).
- **Type 18 — Bank sub-account opening balance**: no `tblledger` write at all — confirmed (`app/models/Bank.php:297,311` only touch `tblbanktransactions_subaccounts`).
- Dead code (not executed): commented-out `saveToLedger` calls in `Banktransaction.php` (~338-358), `Contribution.php` (~259-283, 510-514), `Groupfund.php` (~203-230).

### 1c. Other direct (non-helper) `tblledger` writes

- `app/models/Journal.php:72` — `createupdate()` — write — `DELETE FROM tblledger WHERE isJournal=1 AND journalNo=...` (hard delete on edit, inside transaction). **[Removed in 8.1]**
- `app/models/Journal.php:148` — `delete()` — write — `UPDATE tblledger SET deleted=1 ...` (soft delete; **not** wrapped in a transaction). **[Removed in 8.1]**
- `app/models/Account.php:110` — `update()` — write — `UPDATE tblledger SET account=... WHERE TRIM(LOWER(account))=...` (rename-cascade fixup).
- `app/models/Account.php:116` — `update()` — write — same for `parentaccount`.
- `app/models/Bank.php:147` — `delete()` — write — `DELETE FROM tblledger WHERE transactionType=8 AND transactionId=:id` (hard delete when bank record itself is deleted — not soft, inconsistent with convention).
- `app/models/Bankposting.php:152` — `update()` — write — hard delete+reinsert of type-9 rows on edit.
- `app/models/Bankposting.php:196` — `delete()` — write — **hard** `DELETE FROM tblledger` even though the paired `tblbankpostings` row is only soft-deleted in the same method — anomaly, see §3.
- `app/models/Invoice.php:193-194` — `update()` — write — `DELETE FROM tblledger WHERE transactionType=:ttype ...` with `:ttype` bound to literal `6`, while this file posts customer invoices as type `14` everywhere else (lines 147,154,216,223). **Likely bug**: edit of a customer invoice deletes type-6 (supplier invoice) rows sharing the same numeric ID instead of its own type-14 rows.
- `app/models/Expense.php:584,588` — `resetApprovals()` — write — bulk `UPDATE ... SET deleted=1` on `tblledger` and `tblbankpostings` for all expenses in a fiscal year, looped per ID.

### 1d. Reads (SELECT) — PHP

- `app/models/Report.php:316` — `GetRevenues()` — read — income-statement income rows, `parentaccount` + `debit/credit`, `accountId=1`.
- `app/models/Report.php:329` — `GetRevenuesTotal()` — read — total income figure, `accountId=1`.
- `app/models/Report.php:340` — `GetExpensesPL()` — read — income-statement expense rows, `parentaccount`, `accountId=2`.
- `app/models/Report.php:348` — `GetExpensesTotal()` — read — total expense figure, `accountId=2`.
- `app/models/Report.php:362` / `:366` — `GetChildAccounts()` — read — P&L drilldown children of a parent; filters `parentaccount = ?` (name match).
- `app/models/Report.php:404-473` — `GetAssetsTotal`/`GetLiabilityEquityTotal`/`GetNetIncome` — read — `SUM(debit)`/`SUM(credit)` by `accountId` (3; 4 or 6; 1; 2) — accountId-based, but largely dead/unused in the live balance-sheet flow (see §4 open question).
- `app/models/Report.php:578-607` — `GetPlDetailed`/`GetPlChildAccountDetailed` — read — P&L drilldown detail, joins `tblledger l LEFT JOIN tbltransactiontypes t`; filters `parentaccount = ?` or `account = ?` (name match).
- `app/models/Report.php:682-688` — `GetBalanceSheetItemOpeningBalance()` — read — `LOWER(account) = ?` (name match) + date filter.
- `app/models/Report.php:701-709` — `GetDetailedBalanceSheetAccountReport()` — read — `LOWER(parentaccount) = ?` (name match), groups by `account`.
- `app/models/Report.php:745` — `GetJournalReport()` — read — the Journals report list; `journalNo, account, debit, credit, narration`, `isJournal=1`.
- `app/models/Parishreport.php:141,159,177,195` — diocese-wide equivalents of the above 4 Report.php methods, keyed on `account` (not `parentaccount`); `congregationId=0` branch means **no** congregation filter at all (cross-tenant aggregate).
- `app/models/Parishreport.php:251-378` — diocese-wide `GetAssetsTotal`/`GetLiabilityEquityTotal`/`GetNetIncome`.
- `app/models/Tb.php:11-30` — `GetReport()` — read — trial balance entry point, calls `sp_trialbalance_summary_v2`/`sp_trialbalance_detailed_v2`.
- `app/models/Tb.php:33-63` — `GetDetailedTbReport()` — read — drilldown, filters `parentaccount = ?` or `account = ?` (name match).
- `app/models/Journal.php:142` — `getjournal($journalno)` — read — **`JOIN tblaccounttypes a ON l.account = a.accountType`** (joins ledger's text name to the chart's text name, not via `accountId`→`ID`) — the most fragile join found in the codebase. **[Replaced by `Ledger::getEntry()` in 8.1]**
- `app/models/Journal.php:181,230` — `getJournalDetails()` — read — ledger lines for a `(transactionId, transactionType)` pair. (Confirmed in 8.1: used only by the expense posting preview, type 2.)
- `app/models/Expense.php:625` — `getJournalDetails($id)` — read — same pattern, `transactionType=2`.

### 1e. Reads — views / JS

No raw `tblledger` SQL exists in any `app/views/**` file or front-end JS — confirmed by search. All reads happen server-side in models before being handed to views.

---

## 2. Shared insert/delete helpers and their callers

Defined together in `app/helpers/url_helper.php`:

```
79  function saveToLedger($connection,$date,$account,$parent,$debit,$credit,$narration,$accountId,$type,$tid,$cong,$reference)
86  function saveToBanking($connection,$bank,$date,$debit,$credit,$method,$reference,$type,$tid,$cong)
93  function deleteLedgerBanking($connection,$type,$tid)
103 function softdeleteLedgerBanking($connection,$type,$tid)
```

- **`saveToLedger()` callers** — see the per-transaction-type list in §1b (72 active call sites across 16 model files, types 1,2,3,4,6,7,8,9,10,11,12,13,14,15,16,17; type 5/journal inserts directly instead, `Journal.php:88`).
- **`saveToBanking()` callers** — `Bank.php:84` (type 8), `Banktransaction.php:150,157,168,294,301,312` (type 13), `Cashreceipt.php:59,103` (type 10), `Contribution.php:279,529` (type 1, bank/other paymethod), `Deposit.php:61` (type 13), `Groupcollection.php:101,145` (type 17), `Expense.php:327,470` (type 2, bank paymethod), `Invoice.php:371` (type 16), `Mmfreceipt.php:98` (type 11), `Payment.php:85` (type 7), `Pledge.php:125,200` (types 3/4, bank/other). Note: manual bank postings (type 9, `Bankposting.php`) never call this — they write `tblbankpostings` directly (§9).
- **`deleteLedgerBanking()` callers (hard delete, used on edit)** — `Cashreceipt.php:94`, `Banktransaction.php:234`, `Groupfund.php:267,300,341` (Reverse/Reject/Unapprove — all three "undo" flows, not just literal edits), `Groupcollection.php:133`, `Expense.php:294,437`, `Supplierinvoice.php:235`, `Deposit.php:50`, `Contribution.php:438`, `Mmfreceipt.php:86,147` (edit branch **and** `Delete()` — see §3 anomaly).
- **`softdeleteLedgerBanking()` callers (soft delete, used on source-record delete)** — `Deposit.php:97`, `Contribution.php:354`, `Expense.php:509`, `Cashreceipt.php:148`, `Banktransaction.php:421`, `Groupcollection.php:189`, `Supplierinvoice.php:397`, `Supplier.php:123`.

---

## 3. Edit and delete flows per transaction type

| Type | Edit (hard delete + reinsert) | Delete (soft) | Notes/anomalies |
|---|---|---|---|
| 1 Contribution | `Contribution.php:438` → reinsert 494/521/524 | `Contribution.php:354` | Standard. |
| 2 Expense | `Expense.php:294` (approve, reposts) / `:437` (update, only if already approved) | `Expense.php:509` | Plus bulk soft-delete `resetApprovals()` at `:584/588`. `create()` posts nothing (draft). |
| 3/4 Pledge | **None** — no edit method exists at all | `Pledge.php:218` sets `tblpledges_header.deleted=1` only — **no ledger/banking cleanup call of any kind**, not transaction-wrapped | **Gap**: pledge ledger rows are never reversed on delete. |
| 5 Journal | `Journal.php:72` hard delete → reinsert `:88` | `Journal.php:148` direct `UPDATE...deleted=1`, not via helper, **not transaction-wrapped** | Only type that never touches `tblbankpostings` (confirmed). **[8.1: edit in place via `Ledger::post()`, delete = `Ledger::void()` in a transaction, bank lines synced]** |
| 6 Supplier invoice | `Supplierinvoice.php:235` → reinsert 254/263 | `Supplierinvoice.php:397` | Standard. |
| 7 Supplier payment | **None** | **None** | `Payment.php` has no Edit/Update/Delete/Reverse method at all. **Gap.** |
| 8 Bank opening balance | N/A (set at bank creation only) | `Bank.php:147` **hard** DELETE when the bank record itself is deleted | Diverges from soft-delete convention, but this deletes the parent bank, not an edit of a type-8 row. |
| 9 Bank posting | `Bankposting.php:152` → reinsert 160-168 | `Bankposting.php:196` **hard** DELETE of ledger rows while `tblbankpostings.deleted=1` (soft) in the same method | **Anomaly** — asymmetric soft/hard delete. |
| 10 Petty cash | `Cashreceipt.php:94` → reinsert 97/100 | `Cashreceipt.php:148` | Standard. |
| 11 MMF receipt | `Mmfreceipt.php:86` → reinsert 92/95 | `Mmfreceipt.php:147` `Delete()` calls `deleteLedgerBanking` (**hard**) and also hard-deletes the `tblmmf` row itself | **Anomaly** — only "Delete" method that hard-deletes everything. |
| 12 Group fund approval | `Groupfund.php:267/300/341` all use hard `deleteLedgerBanking` (Reverse/Reject/Unapprove) | **None** | No soft-delete path exists for type 12 at all; "reversal" is always a hard delete. |
| 13 Bank txn/Deposit | `Banktransaction.php:234`→reinsert 290-310; `Deposit.php:50`→reinsert 56/58 | `Banktransaction.php:421`; `Deposit.php:97` | Standard; type shared across two source tables. |
| 14 Customer invoice | `Invoice.php:193`→reinsert 214/221 — **deletes type=6 rows, not type=14** (bug, §1c) | **None** — no delete method exists | **Gap + bug.** |
| 15 Supplier opening balance | N/A (set at supplier creation) | `Supplier.php:123` | Standard. |
| 16 Customer invoice payment | **None** | **None** | `Invoice::payment()` only creates; no edit/reversal path. **Gap.** |
| 17 Group collection | `Groupcollection.php:133`→reinsert 138-146 | `Groupcollection.php:189` | Matches convention exactly. |
| 18 Bank sub-account opening balance | `Bank.php:297` | `Bank.php:311` (hard delete) | Never touches `tblledger` at all. |

---

## 4. Reports and read paths

### Balance Sheet
Exact procedure names in `db_routines.sql`:
- `sp_BalanceSheet_AccountDrilldown` (line 131) — filters `account = p_account` (name match), `deleted=0`, `congregationId`, date range.
- `sp_BalanceSheet_Assets` (line 147) — `parentaccount AS account, SUM(debit-credit)` WHERE `accountId=3`, grouped.
- `sp_balancesheet_assets_parish` (line 160) — diocese-wide, no congregation filter, via `gettotaldebits_bs_parish`/`gettotalcredits_bs_parish`.
- `sp_BalanceSheet_LiabilitiesEquity` (line 167) — liabilities/equity by `parentaccount` (`accountId IN (4,6)`) unioned with a synthetic all-time `'Retained Earnings'` row (`accountId IN (1,2,5)`).
- `sp_balanceSheet_liablityequity` (193) / `_parish` (200) — per-`account` liabilities/equity via `gettotaldebits_bs`/`gettotalcredits_bs`.
- Unreferenced-from-PHP variants also present: `sp_get_assets` (462), `sp_get_assets_v1` (473), `sp_get_liabilities_equity_v2` (490), `sp_get_liabilities_equity_v3` (528) — appear to be newer/experimental replacements not yet wired into any controller.

PHP call sites: `Report.php:388` (`GetAssets`→`sp_BalanceSheet_Assets`), `:397` (`GetLiablityEquity`→`sp_BalanceSheet_LiabilitiesEquity`), `:730` (`GetChildDetailedBalanceSheetAccountReport`→`sp_BalanceSheet_AccountDrilldown`); `Parishreport.php:226/230/238/242` (diocese-wide equivalents). Controllers: `Reports.php:1139-1283` (`balancesheet`, `balancesheetrpt`, `balancesheetdetailed`, `balancesheetchilddetails(rpt)`); `Parishreports.php:568-` (diocese-wide).

### Bank Reconciliation
- `Bankreconcilliation.php:35-85` `getAmounts()` — reads `tblbankpostings` mostly, but line 89 calls `sp_balancesheet_assets` then scans results for `strtolower($result->account) === 'cash at bank'` — a hardcoded-string dependency used by the **Cashflow Statement** sub-report (`Bankreconcilliations.php:116-120`), not the main bank-recon screen.
- `getAmountsrecon()` (lines 108-204, same file) — used by the main `bankrecon()` endpoint (`Bankreconcilliations.php:33-41`) — reads only `tblbankpostings`, not `tblledger`.
- **So `tblledger` is read by Bank Reconciliation only through the Cashflow Statement sub-report**, and it is keyed on the literal name "cash at bank" regardless of which actual bank is being reconciled.

### Trial Balance
- `Tb.php:11-30` → `sp_trialbalance_summary_v2` (1002) / `sp_trialbalance_detailed_v2` (954) — group by `parentaccount`/`account`; `type_order` derived from `accountId`.
- `Tb.php:33-63` `GetDetailedTbReport()` — drilldown, filters `parentaccount = ?` or `account = ?`.
- Legacy/parallel: `sp_trialbalance` (946, used by `Report.php:379`), `sp_trialbalance_parish` (986, `Parishreport.php:214`), `sp_trialbalance_summary` (994, no PHP caller found), `sp_get_trial_balance_updated` (842, no PHP caller found).
- Controllers: `Trialbalance.php` (live screen, via `Tb.php`) and `Reports.php:1088-1138` (older parallel screen via `sp_trialbalance`); `Parishreports.php:513-567` (diocese-wide).

### Income Statement / Income & Expenditure
- Per-congregation: `Reports.php:622-705` (`incomestatement`/`incomestatementrpt`) → `GetRevenues`+`GetExpensesPL` (grouped by `parentaccount`). Drilldown: `plchildaccount(rpt)` (707-759, filters `parentaccount=?`) and `plchildaccountdetailed(rpt)` (761-819, filters `account=?`).
- Diocese-wide: `Parishreports.php:437-512` → `Parishreport.php` equivalents (grouped by `account`).
- Group-level P&L (`Report.php:610-664`, `GetGroupRevenues`/`GetGroupCollections`/`GetGroupExpensesPL`) does **not** touch `tblledger` at all — reads `tblfundrequisition`/`tblmmf`/`tblexpenses` directly, bypassing the ledger entirely.

### Dashboard
No `tblledger`-backed dashboard figures were found anywhere. `Mains.php` (the only home controller) only loads the congregation list; no financial summary widgets exist.

### Other named reports / endpoints
- **Ledger / Account Statement**: `Reports.php:1847-1920` (`ledger_statement`, `ledgerstatementrpt`) → `Report.php:808 GetAccountStatement()` → `sp_get_account_statement` (356) or `sp_get_account_statement_parent` (409, includes children via `account=acc OR parentaccount=acc`) — both compute an opening balance + running-balance statement keyed directly on text `account`/`parentaccount`.
- **Journals report**: `Reports.php:1621-1681` → `Report.php:737 GetJournalReport()` (direct SELECT, `isJournal=1`).
- **Journal entry screen**: `Journal.php` model backs create/edit/view (`getjournal`, `getJournalDetails`, `journalNo`/`getjournalno`, `checkexists`). **[8.1: now `peekNumber`, `getjournal` = `Ledger::getEntry`; number methods and `checkexists` removed]**
- **Expense "view posting" popup**: `Expense.php:613-627 getJournalDetails($id)`.
- **Account-deletion guard**: `fn_checkreferencedaccount` (db_routines.sql:1046) — includes two `COUNT(*) FROM tblledger WHERE TRIM(LOWER(account))=...`/`parentaccount` checks, called from `Account::delete()` (`Account.php:136-149`).

### Non-balance-sheet stored procedures/functions in `db_routines.sql` referencing `tblledger`

| Name | Purpose |
|---|---|
| `sp_get_account_statement` (356) / `_parent` (409) | Account statement with opening + running balance, keyed on `account` text. |
| `sp_get_trial_balance_updated` (842) | Alternate trial balance, no PHP caller found — likely abandoned. |
| `sp_trialbalance` (946) / `_parish` (986) | Legacy trial balance per `account`. |
| `sp_trialbalance_detailed_v2` (954) / `sp_trialbalance_summary_v2` (1002) | Current trial balance (per `account`/`parentaccount`). |
| `sp_trialbalance_summary` (994) | Legacy summary, no PHP caller found. |
| `fn_checkreferencedaccount` (1046) | Account-in-use guard (name-matched against `tblledger`). |
| `gettotalcredits`/`gettotaldebits` (1367/1402) + `_bs`/`_bs_parish`/`_parent`/`_parish` variants (1374-1430) | Scalar helpers summing credit/debit for one account **name**, feeding the trial-balance/balance-sheet procs above. |

All of these helper functions filter `tblledger` by the **text** `account`/`parentaccount` column rather than joining through `accountId`→`tblaccounttypes.ID` — the single most migration-fragile pattern in the reporting layer.

---

## 5. Account selection for posting

| Location | Filter conditions | Purpose |
|---|---|---|
| `Account.php:16-25` `index()` | `isBank=0`, `deleted=0`, `brand_level(t.ID)=2`, cong | Main COA admin list — **only level-2 (reporting) rows** |
| `app/views/accounts/index.php:74-106` | raw inline PDO query, `isBank=0`, `deleted=0`, `parentId=?` | For each level-2 row, fetches only direct children (level 3) — level-4 bank rows never appear here |
| `Account.php:38-46` `getAccounts($main)` | `isBank=0`, `parentId=:pid` | AJAX subcategory populate, one level |
| `Accounts.php:42-55` `getsubcategory()` | wraps the above | AJAX endpoint for add/edit forms |
| `Account.php:26-32` `getAccountTypes()` | `parentId=0` | Lists the 6 roots |
| `Reusables.php:17-24` `GetAccounts($id)` | `accountTypeId=:id`, `deleted=0`, `isBank=0`, `parentId<>0`, `active=1`, cong — **no `isSubCategory` filter** | Generic dropdown (Banks module) — can include reporting-level rows |
| `Reusables.php:27-35` `GetChildAccounts($id)` | same + `isSubCategory=1` | Posting-only version |
| `Reusables.php:37-44` `GetAccountsAll()` | `deleted=0`, `isBank=0`, `parentId<>0`, `active=1`, cong — **no `isSubCategory`** | Used by `Banks.php:200,277`, `Reports.php:1850` |
| `Reusables.php:46-53` `GetAccountsAllSubcategory()` | + `isSubCategory=1` | Real posting dropdown for Contributions (`Contributions.php:47,101,242`), Expenses (`Expenses.php:46,497`) |
| `Expense.php:34-52` `GetAccounts($type)` | `accountTypeId IN (2,3,4)`, `deleted=0`, `isBank=0`, `parentId<>0`, `active=1`, `isSubCategory=1`, cong | Expense-entry account dropdown |
| `Journal.php:16-25` `getAccounts()` | `deleted=0`, `parentId<>0`, `active=1`, `isSubCategory=1`, cong | Journal-entry screen dropdown **[8.1: + `congregationId IN (0, cong)` and `Ledger::isPostingAccount()` filter]** |
| `Plan.php:60-70` `GetAccounts()` | `parentId<>0`, `deleted=0`, cong — **no `isSubCategory`/`isBank`/`active`** | Budget "Plans" dropdown — can list reporting/bank rows |
| `Report.php:170-179` / `Parishreport.php:25-34` `GetAccounts($id)` | `accountTypeId=:id`, `deleted=0` only | Report filter dropdowns — intentionally broad |
| `Report.php:357-376` `GetChildAccounts()` | resolves `accountTypeId` by `LOWER(accountType)=?`, then `tblledger WHERE parentaccount=?` | P&L drilldown, single-level, name-driven |
| `Product.php:27-34` `GetGlAccounts()` | `deleted=0`, `isSubCategory=0`, `parentId<>0` | Product "Income Account" category picker — deliberately reporting-level |
| `Invoice.php:37-44` `getIncomeAccounts()` | `deleted=0`, `parentId<>0`, `isSubCategory=0` | Same pattern for Invoices |
| `Churchbudget.php:37-43` / `Groupbudget.php:50-58` `getAccounts()` | `isSubCategory=1`, `deleted=0` | Budget line accounts, no cong/active filter |
| Bank-specific pickers: `url_helper.php:131-149` `getBanks()`/`getBanksAll()`, `Reusables.php:60-64,84-88`, `Deposit.php:18`, `Banktransaction.php:19-27`, `Bank.php:28-37,105-110`, `Bankreconcilliation.php:18-26`, `Bankposting.php:36`, `Bankbalance.php:34`, `Report.php:484`, `Parishreport.php:394`, `Mmfreceipt.php:51`, `Groupfund.php:149` | `isBank=1`, `deleted=0`, `active=1` (some), cong | The actual "pick a specific bank" dropdowns — correctly filtered, but **none of these currently feed the hardcoded `'cash at bank'` postings** below |
| `Banktransaction.php:17-21` `GetOtherBanks()` | `isBank=1 AND cong=? AND deleted=0` **OR** `accountType='fixed deposits'` **OR** `accountType='petty cash'` | Mixes `isBank` filter with hardcoded name literals |

### Resolving an account by NAME instead of by ID

- `url_helper.php:390-402` `getparentgl($con,$childgl)` — looks up `isSubCategory`/`parentId` **by `accountType = ?` (name match)**, walks up **one level**, returns the input name unchanged if not found or already a reporting account. Called at nearly every posting site.
- `Reusables.php:10-15` `GetParentGL($childgl)` — a divergent duplicate of the above, **without** the `isSubCategory` guard.
- `Account.php:109-119` `update()` — the only mechanism keeping historic `tblledger` text in sync with a renamed `tblaccounttypes` row is a literal `TRIM(LOWER(account))=...` rewrite.
- `db_routines.sql:1046-1068` `fn_checkreferencedaccount()` — name-matches `tblledger.account`/`parentaccount` as part of the account-delete guard.
- `Bankreconcilliation.php:94-98` — `strtolower($result->account) === 'cash at bank'`, independent of which bank is actually being reconciled.
- `Report.php:357-374`, `Tb.php:43-55`, `db_routines.sql:147-205` — all group/filter by `parentaccount`/`account` text.
- `scripts/migrate_ledger.php:199-227` — the migration's own mapping is explicitly name-based (`JOIN tblaccounttypes a ON a.accountType = n.account ...`), with a congregation-specific-vs-shared tiebreak — itself evidence that name is the only identity old rows carry.

### Hard-coded account names/IDs

Numeric IDs:
- `scripts/migrate_ledger.php:31` — `CAB_FALLBACK_BANK = 105` (co-op bank) — unresolved pre-cutoff "cash at bank" rows are force-assigned to bank 105.
- `Bank.php:55-63` `create()` — every new bank is inserted with `parentId=22` hardcoded ("cash and cash equivalents") — one level higher than the existing 105/195 rows (`parentId=33`) — worth confirming intent. **[Checked in 8.1: dev code now inserts `parentId=33, isSubCategory=1`]**
- `Bank.php:72-75` — "opening balance equity" inserted with `accountId=6, parentId=6` hardcoded.
- `Invoice.php:151` — `$three = 3` hardcoded for the "accounts receivable" line's `accountId`.
- `Supplierinvoice.php:185` — `$three = 4` hardcoded for "accounts payable".
- `Report.php:350` — `accountId = 2` hardcoded (Expense root).
- `db_routines.sql:152,164,172,187,197,204` — `accountId = 3` / `IN (4,6)` / `IN (1,2,5)` hardcoded root types in balance-sheet procs (expected, since the 6 roots are fixed by design).

Hard-coded `'cash at bank'` literal string posted directly via `saveToLedger`: `Invoice.php:365,369`; `Groupcollection.php:97,98,141,142`; `Banktransaction.php:143,146,153,164,287,290,297,308`; `Bankposting.php:94,98,105,156,160,167`; `Pledge.php:119,123,194,198`; `Bank.php:78,82`; `Payment.php:74,83`; `Expense.php:296,322,325,439,465,468,643`; `Journal.php:199` (in `getJournalDetails()`, expense preview); `Cashreceipt.php:51,56,96,100`; `Contribution.php:268,273,519,524`; `Deposit.php:54,56`; `Mmfreceipt.php:95`; `Bankreconcilliation.php:95` (compared, not posted).

Other hardcoded literals / latent bugs found:
- `Pledge.php:110,188` — `getparentgl($db,'cast at bank')` — **typo** for "cash at bank". No row named `'cast at bank'` exists live, so `getparentgl()` returns the misspelled literal unchanged; every pledge-payment ledger line gets `parentaccount='cast at bank'`, an orphan string no report will ever match.
- `Mmfreceipt.php:89-96` — `$cashparent` is computed from `getparentgl(...,'groups balances held')` (apparent copy-paste; should resolve `'cash at bank'`) and then used as the parent for the `'cash at bank'` line. `'groups balances held'` also does not exist as a row in `tblaccounttypes` — both lookups return unresolved literals.
- `Supplierinvoice.php:182,184,259,261` — hardcodes `$account='accounts payable'`, `$parentaccount='payables and accruals'`, bypassing `getparentgl()` entirely. `'accounts payable'` does **not** exist as a chart row (verified live) — every supplier-invoice ledger entry references a name with no backing chart row.
- `Invoice.php:149,152` — `$account='accounts receivable'` self-referencing parent (relies on ID 9 already being `isSubCategory=0`).
- `Expense.php:153,156,162,281,305,309,315,424,448,452,458,634,637-643`, `Journal.php:190-199` — repeated literal `'petty cash'`/`'development petty cash'` (the real chart row is named `'petty cash development'` — reversed word order, a latent mismatch worth checking), `'cash at hand'`, `'cash holding account'`.
- `Expenses.php:61` — `'deductfrom' => 'petty cash'` default.
- `Banktransaction.php:19-20` — literals `'fixed deposits'`, `'petty cash'` mixed into the `isBank=1` filter.

Verified live: `'accounts payable'`, `'groups balances held'`, `'development petty cash'`, `'cast at bank'` have **no backing row** in `tblaccounttypes`; `'petty cash development'`, `'cash at hand'`, `'cash holding account'`, `'fixed deposits'`, `'petty cash'`, `'accounts receivable'`, `'payables and accruals'`, `'opening balance equity'` **do** exist.

---

## 6. Fixed chart-depth assumptions

- `Account.php:16-25` (`brand_level(t.ID)=2`) + `accounts/index.php:74-106` (one-level child join) — the entire COA admin screen is architecturally capped at exactly 2 visible levels below root. Level-4+ rows (e.g. `co-op bank` 105, `kcb development account` 195, both `parentId=33`) never appear here at all — manageable only through the separate Banks module.
- `accounts/add.php`/`edit.php` via `Accounts.php:42-55,108-136`, `Account.php:38-46` — the "Sub Category Of" dropdown is always populated from `WHERE parentId=:pid` where `:pid` is a root or a reporting-level account — no UI path lets you pick an arbitrary existing account at any depth as a new parent.
- `url_helper.php:390-402` `getparentgl()` — walks exactly **one level up**, never recurses. For a chain like 195→33→22, calling it on the level-4 name would only ever surface level 3. In practice this is masked because call sites hardcode `'cash at bank'` (33, already `isSubCategory=0`) as the child, making the function self-referencing rather than actually walking up.
- `Reusables.php:10-15` `GetParentGL()` — same one-level design, no `isSubCategory` guard at all.
- `Report.php:357-374` + `db_routines.sql:147-205` (all `GROUP BY parentaccount`) — every P&L/balance-sheet report is built from a flat parent/child pair stamped onto the ledger row at posting time; no true multi-level rollup is possible.
- `scripts/migrate_ledger.php:171-186,465-471` — the migration script's own `isPosting` rule requires **at least 3 levels** (`parentId!==0 && grandParentId!==0`) and rejects level-2 postings — the one place in the codebase that correctly treats depth as variable, but written from scratch since no live app code enforces or understands it.
- `db_routines.sql:1036-1044` `brand_level()` — this function itself **is** recursive (loops up via `parentId` until 0) — the depth bug is not in this function but in how its result is used as a hardcoded `=2` filter in `Account.php`/`accounts/index.php`.

---

## 7. Account create/edit/delete screens

Controller: `Accounts.php`. Model: `Account.php`. Views: `accounts/{add,edit,index}.php`.

- **`isSubCategory`**: set **manually by a form checkbox** (`accounts/add.php:57-66`, name=`check`), read in `Accounts.php:68,151`, written straight to `isSubCategory` in `Account.php:69,101`. Not computed from whether the account actually has children — no integrity check that a parent-with-children isn't marked `isSubCategory=1`, or that a true leaf is marked `1`.
- **`parentId`**: set from the "Account Type" dropdown (one of 6 roots) or, if "Sub Category Of" is checked, the one-level `subcategory` dropdown (§6). No server-side validation in `create()`/`update()` that the chosen `parentId` is non-deleted/active/acyclic.
- **Create**: `Account.php:62-81` — plain INSERT; name-uniqueness check via `checkExists()` (`:47-60`, `WHERE accountType=? AND congregationId=0`).
- **Update**: `Account.php:89-134` — `UPDATE tblaccounttypes` then, same transaction, rewrites `tblledger.account`/`parentaccount` by matching the OLD lowercased name (§5). No cascading re-parent of children if `parentId` changes.
- **Delete**: `Account.php:136-149` — **hard** `DELETE FROM tblaccounttypes` (not soft, despite the table having a `deleted` column used everywhere else). Guarded by `fn_checkreferencedaccount()` (db_routines.sql:1046), which blocks delete if referenced in `tblchurchbudget_details`, `tblbankpostings` (by `bankId`), `tblcontributions_details`, `tblexpenses`, `tblgroupbudget_details`, `tblledger` (name match on `account`/`parentaccount`), `tblproducts`, `tblpledges_header`. The `tblledger` half of this guard is name-based — a since-orphaned ledger row (e.g. after a rename that didn't propagate) would not be detected. **No check of children at all** — deleting a parent can silently orphan rows whose `parentId` points at the now-gone ID.
- `accounts/index.php:94-101` — delete button only rendered for the level-3 "children" rows this view fetches; level-2 rows never get a delete button in this screen.
- **Banks are a parallel, separate CRUD** for `isBank=1` rows: `Banks.php`/`Bank.php`. `Bank::delete()` (`Bank.php:136-153`) is a **soft** delete (`deleted=1`) plus a **hard** `DELETE FROM tblledger WHERE transactionType=8 AND transactionId=:id`, guarded by `Bank::checkreferenced()` (`:126-134`, checks `tblbankpostings.bankId` count only — does **not** check `tblledger` by name at all). A materially different delete policy from the main Accounts screen on the same table.

---

## 8. Journal numbers

**[8.1: all three replaced. Numbers now come from `Ledger::nextJournalNo()` (locked `journal_number_seq`) inside the save transaction; the form shows `Ledger::peekNextJournalNo()`; edit uses the existing entry's number; `checkexists()` removed — uniqueness is enforced by `Ledger::post()` and the `UNIQUE (congregationId, journalNo)` key.]**

- **Generation**: `Journal.php:26-44` `journalNo()` and `:51-64` `getjournalno($type)` — both compute `MAX(journalNo)+1` scoped by `congregationId` and `deleted=0`; return `1` if no rows exist. **No sequence table** — pure `SELECT MAX(...)+1`, subject to race conditions under concurrent creation (no locking/`FOR UPDATE`, no unique constraint found).
- **Validation**: `Journal.php:123-132` `checkexists($journalno)` — `SELECT COUNT(*) FROM tblledger WHERE isJournal=1 AND journalNo=? AND deleted=0 AND congregationId=?`. A separate check-then-use call, not atomic with the subsequent insert — does not actually prevent a race between check and write.
- **Consumption**: `Journal.php:66-122` `createupdate()` uses the controller-supplied `$data['journalno']` directly as both `transactionId` and `journalNo` for every inserted row (lines 90-101), and as the key for hard-delete-then-reinsert on edit (line 72-76).

---

## 9. `tblbankpostings` writes

- `url_helper.php:86` `saveToBanking()` — INSERT — shared helper (§2 for callers). Inserts only `bankId, transactionDate, debit, credit, transactionMethod, reference, transactionType, transactionId, congregationId` (`accountId`, `narration` left NULL).
- `url_helper.php:97` `deleteLedgerBanking()` — DELETE — paired hard delete (§2).
- `url_helper.php:107` `softdeleteLedgerBanking()` — UPDATE `deleted=1` — paired soft delete (§2).
- `Bankposting.php:77` `create()` — direct INSERT (type 9 is the one type that writes `tblbankpostings` directly rather than via `saveToBanking()`); also fills `accountId` (contra account) and `narration`.
- `Bankposting.php:137` `update()` — direct UPDATE (type 9 edit).
- `Bankposting.php:190` `delete()` — direct `UPDATE deleted=1` (paired with the **hard** ledger delete noted in §1c/§3 — asymmetric).
- `Clearbanking.php:47` `Clear()` — `UPDATE cleared=1, clearedDare=...` — bank-rec "mark cleared," no ledger write.
- `Clearbanking.php:81` `Modify()` — adjust clear date, reconciliation only.
- `Clearbanking.php:108` `delete()` — `UPDATE deleted=1 WHERE ID=:id`, single statement, not transaction-wrapped, scoped by raw row ID not transactionType.
- `Expense.php:588` `resetApprovals()` — bulk `UPDATE deleted=1` paired with the type-2 ledger bulk soft-delete (§1c).
- **[8.1] `Ledger::syncBankPostings()` / `Ledger::voidBankPostings()`** (`app/libraries/Ledger.php`) — INSERT / UPDATE in place (keeps `cleared`) / soft delete. Called by `Journal.php` (type 5, method 'Journal'); every later type will call them instead of `saveToBanking()` / the delete helpers.

**Do journal entries (type 5) ever write `tblbankpostings`? No.** Confirmed — no `saveToBanking(...,5,...)` call exists, `Journal.php` never references `tblbankpostings`, and the shared helpers are never invoked with type 5 for banking. Journals are ledger-only. **[No longer true after 8.1: journals with bank lines write bank rows via `Ledger::syncBankPostings()`.]**

`tblbankpostings.transactionMethod` is an FK to `tbltransactionmethods` (1 Deposit, 2 Withdrawal, 3 Charges, 4 B/F, 5 Transfers, 6 inter-transfer(sub accounts), 7 Journal — 7 added on dev in 8.1b). Checked in 8.1b: reconciliation / cashbook / balance queries take the side from `debit`/`credit`, never from the method; `Bankposting::getMethods()` (`ID < 4`) is the manual-posting dropdown; `Report.php:494` / `Parishreport.php:404` `bankingrpt()` inner-join the method for its label (a missing method row would drop the posting from that report).

---

## 10. Transaction (DB) wrapping

Nearly every ledger-writing path wraps both the source-record write and the ledger/banking write in one `beginTransaction()`/`commit()`/`rollback()` block (PDO). Exceptions found — single-statement methods with no transaction wrapper:

- `Journal.php:146-154` `delete()` — one `UPDATE tblledger SET deleted=1` statement. **[8.1: now wrapped, `Ledger::void()` + `voidBankPostings()`]**
- `Pledge.php:218-230` `delete()` — one `UPDATE tblpledges_header SET deleted=1`; has nothing else to wrap since no ledger cleanup exists (§3).
- `Clearbanking.php:106-115` `delete()` — one `UPDATE tblbankpostings SET deleted=1`.
- `Account.php:62-81` `create()` — plain insert, doesn't touch ledger.
- `Groupfund.php:117-130` `Delete()`/`GetRequestStatus()` — pre-approval requisition operations; no ledger rows exist yet at that stage.

Everything else — `Contribution.save/update/delete`, `Expense.create/approve_expense/update/delete/resetApprovals`, `Pledge.create/pay`, `Supplierinvoice.create/update/Delete`, `Payment.Create`, `Supplier.CreateUpdate/Delete`, `Bank.create/delete/deletesubaccount`, `Bankposting.create/update/delete`, `Banktransaction.Save/Update/Delete`, `Deposit.CreateUpdate/Delete`, `Cashreceipt.Save/Update/Delete`, `Mmfreceipt.CreateUpdate/Delete`, `Groupcollection.Save/Update/Delete`, `Groupfund.Approve/Reverse/Reject/Unapprove`, `Invoice.create/update/payment`, `Journal.createupdate`, `Account.update` — wrap the source-row write and the ledger/banking write(s) in a single PDO transaction with rollback on exception. This is the dominant, consistent pattern.

---

## Open questions

1. **`Invoice.php::update()` ledger-delete type bug** (`Invoice.php:193-194`) — binds `:ttype` to literal `6` while this file posts type `14` everywhere else. Treated here as a real bug (likely copy-pasted from `Supplierinvoice.php`), not a deliberate design choice — no supporting reason found in surrounding code.
2. **Pledge delete has no ledger cleanup at all** (`Pledge.php:218`) and no edit method exists either — confirmed by reading the full file. Unclear whether this is intentional ("pledges are never really reversed, stale ledger rows are accepted") or an unimplemented gap; the migration needs an explicit decision either way.
3. **Supplier payments (type 7) and customer invoice payments (type 16) have no edit/delete/reversal path anywhere** — confirmed by searching all writes to `tblinvoice_payments`/`tblinvoice_payments_suppliers` and all methods on `Payment.php`/`Invoice.php`. Could be handled by some mechanism not found in this search, or simply unimplemented.
4. **Divergent hard/soft delete conventions** for types 8, 9, 11, 12 (§3) — each diverges from the "edit=hard delete+reinsert, delete=soft `deleted=1`" convention in a different way. Could not determine from code alone whether each divergence is intentional per-feature design or accumulated inconsistency across different developers/times — flagged individually rather than normalized.
5. **`tbltransactiontypes` seed data was not found anywhere in the repo** (not in `db_routines.sql`, which is schema-only for routines, not table data). The transaction-type legend in this document is inferred entirely from code context (variable names, account names, narrations, source tables), not from an authoritative source. Recommend confirming against the live table, especially for any type numbers not encountered in this search.
6. **Dead/unreferenced stored procedures**: `sp_get_assets`, `sp_get_assets_v1`, `sp_get_liabilities_equity_v2`/`_v3`, `sp_get_trial_balance_updated`, `sp_trialbalance_summary` (non-v2) exist in `db_routines.sql` but no PHP call site was found for them. They may be vestigial, in-progress replacements, or invoked via dynamic SQL this search didn't catch (none was observed, but can't be fully ruled out).
7. **`Report.php`'s `GetAssetsTotal`/`GetLiabilityEquityTotal`/`GetNetIncome`** (accountId-based, the "good" read pattern) appear commented-out/unused in the per-congregation balance sheet (`Reports.php`) while their `Parishreport.php` twins are still live in the diocese-wide balance sheet (`Parishreports.php:587-589`) — Net Income may be computed/displayed differently (or not at all) between the two variants. Worth confirming intended behavior before migrating either.
8. **`Bank::create()`'s hardcoded `parentId=22`** nests new banks one level higher than the existing 105/195 rows (`parentId=33`). Could not determine if intentional (new banks normally go directly under "cash and cash equivalents," bypassing "cash at bank") or a latent inconsistency. **[Resolved: decision 4; dev code already uses `parentId=33, isSubCategory=1`.]**
9. **`fn_checkreferencedaccount`'s exact PHP caller** was inferred (via `Account::delete()`'s guard behavior) rather than directly confirmed by grepping an explicit `CALL fn_checkreferencedaccount`/`SELECT fn_checkreferencedaccount(...)` in `Account.php` — worth a direct line-level check if this matters for migration planning.
10. **`scripts/migrate_ledger.php` and `scripts/logs/`** already exist in the working tree (untracked) with apparent dry-run and commit runs from today. This wasn't mentioned in the task brief; its existence, and whatever its logs show about `UNMAPPED`/`AMBIGUOUS`/fallback-bank counts for historical "cash at bank" rows, is directly relevant to scoping the fix work that follows this inventory and should be reviewed before planning next steps.
11. **No application-level enforcement that only posting accounts (`isSubCategory=1`) may be posted to** exists anywhere in the live write path — `saveToLedger()` is a raw INSERT with no validation, and `getparentgl()` only computes a parent name, never blocks the child. The only place this rule is enforced today is the one-off migration script's own validation logic, not any guard rail for new transactions going forward. **[Now enforced by `Ledger.php` for every migrated type.]**
12. **Orphan bank rows (found by V10, 8.1b):** seven type-1 rows with `transactionId = 0` (bank 105, 2023-12-17 to 2024-07-06, 450,000) and receipt 1890's active bank row while its ledger entry is voided. **Resolved (handover question F):** five relinked by F14; 682, 696 (duplicates) and 1565 (receipt 1890) accepted as known legacy differences and excluded from V10.

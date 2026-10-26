# Task 9.5b report: reconciliation range, cashflow expected balance, Clear Banking

Branch `ledger/9-reports`, on top of `b7ea1a9`. Run on DEV on 2026-10-26. Not pushed; no production data or backfill change.

## 1. Summary

- Decisions 153 and 154 are implemented and recorded. Reconciliation remains a selected from-to range report; it was not changed to an as-at cashbook calculation.
- Cashflow retains the period movement layout, labels movements “per cashbook”, shows the journal opening and closing book balances, and now calculates expected statement balance as closing book - range uncleared deposits + range uncleared withdrawals. It therefore equals reconciliation for the same bank and range and no longer subtracts uncleared items twice.
- Clear Banking summary inputs use strict `Y-m-d` dates with from <= to and the decision-114 report-safe bank list. Invalid input answers 400 without PHP warnings. The nonexistent `getAmounts()[2]` read and repeated summary queries are gone. The S2 clear-by-ID behavior was not changed.
- All 31 suites ran sequentially in one run: **3,404 passed, 0 failed**.

## 2. Per-bank figures

The last closed year is 2024-10-01 through 2025-09-30. “Today” is 2026-10-26 and its financial-year-to-date range is 2026-10-01 through 2026-10-26. Book and cashflow closing book are the selected bank's journal balance at the to-date. Uncleared figures contain only cashbook rows dated inside the stated range; NULL clear date is uncleared.

| Bank | As at / range | Book | Uncleared deposits (count / amount) | Uncleared withdrawals (count / amount) | Reconciliation expected statement | Cashflow expected statement | Equal |
|---|---|---:|---:|---:|---:|---:|:---:|
| 105 — co-op bank | 30-09-2025; 01-10-2024 to 30-09-2025 | 441,467.50 | 0 / 0.00 | 1 / 18,000.00 | 459,467.50 | 459,467.50 | yes |
| 105 — co-op bank | 26-10-2026; 01-10-2026 to 26-10-2026 | (49,184.00) | 1 / 10,000.00 | 1 / 20,000.00 | (39,184.00) | (39,184.00) | yes |
| 195 — kcb development account | 30-09-2025; 01-10-2024 to 30-09-2025 | 0.00 | 0 / 0.00 | 0 / 0.00 | 0.00 | 0.00 | yes |
| 195 — kcb development account | 26-10-2026; 01-10-2026 to 26-10-2026 | 24,704.00 | 0 / 0.00 | 0 / 0.00 | 24,704.00 | 24,704.00 | yes |

Cashflow opening balances entering each range (journal through the previous day) are: bank 105, 162,009.00 for the last closed year and (39,184.00) for current FYTD; bank 195, 0.00 and 24,704.00 respectively. These openings and the displayed cashbook movements are contextual; neither is mixed into the expected-statement formula.

## 3. Clear Banking

- `Clearbankings::summaryData()` is shared by `getbankings` and the legacy `getValues`: scalar positive bank ID, report-safe for the session congregation, strict real `Y-m-d` dates and from <= to.
- `getValues` calls `getAmounts()` once and returns its real `debits` and `credits` values; it no longer reads index 2 or fabricates balance/variance from an undefined value.
- `getbankings` accepts only no status (uncleared screen) or `status=cleared`; other values return 400. A cleared row with a NULL clear date is serialized as NULL without passing NULL to `strtotime`.
- Bank option IDs are cast to integers and account names are HTML-escaped on both Clear Banking views.
- Tests cover valid summaries and malformed, reversed, missing, array and foreign-bank inputs. Row clearing itself is unchanged; S2 remains with the security review under decision 117.

## 4. Full sequential suite run

| Suite | Passed | Failed |
|---|---:|---:|
| test_8_10_accounts.php | 189 | 0 |
| test_8_10_http.php | 138 | 0 |
| test_8_11_cleanup.php | 30 | 0 |
| test_8_11_http.php | 58 | 0 |
| test_8_6_http.php | 24 | 0 |
| test_8_6_pledges.php | 39 | 0 |
| test_8_7_http.php | 155 | 0 |
| test_8_7_suppliers.php | 306 | 0 |
| test_8_8_customers.php | 212 | 0 |
| test_8_8_http.php | 130 | 0 |
| test_8_9_group_funds.php | 238 | 0 |
| test_8_9_http.php | 138 | 0 |
| test_9_1_http.php | 77 | 0 |
| test_9_1_report_service.php | 142 | 0 |
| test_9_2_http.php | 58 | 0 |
| test_9_2_income_statement.php | 42 | 0 |
| test_9_3_http.php | 96 | 0 |
| test_9_3_trial_balance.php | 79 | 0 |
| test_9_4_balance_sheet.php | 57 | 0 |
| test_9_4_http.php | 80 | 0 |
| test_9_5_bank.php | 45 | 0 |
| test_9_5_http.php | 52 | 0 |
| test_bank_postings_and_transactions.php | 63 | 0 |
| test_bank_transactions_cash_account.php | 49 | 0 |
| test_cleared_bank_rows.php | 59 | 0 |
| test_expenses.php | 263 | 0 |
| test_hotfix_sqli_http.php | 231 | 0 |
| test_ledger.php | 134 | 0 |
| test_receipts.php | 169 | 0 |
| test_year_delete.php | 23 | 0 |
| test_year_lock_screens.php | 28 | 0 |
| **Total (31 suites)** | **3,404** | **0** |

### Why 9.5 reported 2,803 after the 9.4 total of 3,287

The suites did not shrink. Six legacy suites printed uppercase `PASSED/FAILED` summaries with cleanup text, so the 9.5 counter skipped exactly 595 passing checks: bank postings/transactions 63, bank-transactions cash account 49, expenses 263, receipts 169, year delete 23, year lock screens 28. Their final lines are now standardized to `N passed, M failed`, with nonzero exit on failure.

The true 9.5 total was 3,398: the 9.4 baseline 3,287, plus 20 checks when `test_9_4_balance_sheet.php` grew from 37 to 57 for decision 150, plus the new 9.5 suites (43 + 48). This step adds six checks: `test_9_5_bank.php` 43 -> 45 and `test_9_5_http.php` 48 -> 52. Final total: **3,404**.

## 5. Issues found and fixed

- `app/controllers/Bankreconcilliations.php` (old cashflow formula around line 109): combined a journal opening with cashbook movements and adjusted uncleared items again. Impact: expected balance could differ from reconciliation and double-subtract uncleared items. Fix: add journal closing book balance and use closing - uncleared deposits + uncleared withdrawals.
- `app/controllers/Clearbankings.php` (old lines 90-107): `getValues()` queried three times and read nonexistent index 2. Impact: undefined-offset warnings and meaningless balance/variance. Fix: one query, use the two returned fields.
- `app/controllers/Clearbankings.php` (old `getbankings` lines 33-47 and `getValues` lines 90-94): unchecked `trim($_GET[...])`, permissive `strtotime`, integer coercion and no report-safe bank check. Impact: warnings on missing/array input, normalized invalid dates, reversed ranges and cross-congregation bank reads. Fix: one strict shared validator and 400 responses.
- `app/controllers/Clearbankings.php` (old line 71): `strtotime(NULL)` when a cleared row had no clear date. Impact: PHP warning/deprecation and misleading 1970-style date risk. Fix: preserve NULL.
- `app/views/clearbankings/{index,modify}.php` (bank option line 27): account names were emitted unescaped. Impact: stored markup could execute in the option list. Fix: integer IDs and HTML-escaped account names.
- Six legacy suite endings did not use the common count syntax and four did not reliably expose failures through a final explicit exit. Impact: the D.1 count under-reported successful checks. Fix: uniform final count lines and failure exit codes.

## 6. Owner browser checklist

1. Reconciliation: select bank 105, a from/to range and statement balance. Confirm only in-range rows appear in cleared/uncleared links, list totals equal summary totals, and expected statement balance is book - uncleared deposits + uncleared withdrawals.
2. Cashflow: use the same bank/range. Confirm opening and closing are labelled as journal balances, all movements are labelled “per cashbook”, and expected statement balance exactly equals reconciliation.
3. Repeat both screens for bank 195 and at 30-09-2025.
4. Clear Banking: fetch a valid bank/range, tick an uncleared row, choose a valid clear date and save. Confirm it leaves the uncleared list and appears in the cleared list; rerun reconciliation and cashflow and confirm their equal expected balances update consistently.
5. Try an invalid/reversed date in Clear Banking and confirm the request is rejected without a PHP warning.

## 7. Git

Final `git status --short --branch`:

```text
## ledger/9-reports
```

Final `git log --oneline -3` (the first hash is represented as `HEAD` because adding a commit's own hash to its tracked report would change that hash):

```text
HEAD 9.5b: cashflow expected balance; Clear Banking fix; reconciliation range confirmed
b7ea1a9 9.5: bank reconciliation/cashflow book balance from journal; accumulated fund block; tblbankpostings DECIMAL
2c33b4a 9.4: balance sheet on journal with recursive drill; FY-zero opening for 1/2/5
```

Not pushed.

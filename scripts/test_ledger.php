<?php
/**
 * test_ledger.php - checks Ledger.php against the dev database (task 7).
 *
 * Run from the app root:
 *   C:\xampp\php\php.exe scripts\test_ledger.php [--cong=11] [--config=PATH] [--ledger=PATH]
 *
 * Everything runs inside ONE transaction that is always rolled back:
 * nothing is saved, including journal numbers.
 * Exit code: 0 = all passed, 1 = a check failed, 2 = setup error.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
error_reporting(E_ALL);

$cong       = 11;
$configPath = dirname(__DIR__) . '/app/config/config.php';
$ledgerPath = dirname(__DIR__) . '/app/libraries/Ledger.php';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--cong=(\d+)$/', $arg, $m))    { $cong = (int) $m[1]; continue; }
    if (preg_match('/^--config=(.+)$/', $arg, $m))   { $configPath = $m[1]; continue; }
    if (preg_match('/^--ledger=(.+)$/', $arg, $m))   { $ledgerPath = $m[1]; continue; }
    fwrite(STDERR, "Unknown option: $arg\n"); exit(2);
}
foreach ([$configPath, $ledgerPath] as $f) {
    if (!is_file($f)) { fwrite(STDERR, "Not found: $f\n"); exit(2); }
}
require $configPath;
require_once $ledgerPath;

$db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS ' : '  FAIL ') . $name . ($detail !== '' ? " - $detail" : '') . PHP_EOL;
}
/** Expect $fn to throw $class (and the message to contain $contains). */
function expectError(string $name, callable $fn, string $class, string $contains = ''): void
{
    try {
        $fn();
        check($name, false, 'no error was raised');
    } catch (Throwable $e) {
        $ok = $e instanceof $class && ($contains === '' || stripos($e->getMessage(), $contains) !== false);
        check($name, $ok, $e->getMessage());
    }
}

/** First posting account matching a filter, using Ledger's own rule. */
function pick(PDO $db, int $cong, string $where): ?int
{
    $st = $db->prepare("SELECT ID FROM tblaccounttypes
                        WHERE deleted = 0 AND active = 1 AND congregationId IN (0, ?) AND $where ORDER BY ID");
    $st->execute([$cong]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (Ledger::isPostingAccount($db, (int) $id, $cong)) { return (int) $id; }
    }
    return null;
}

$bank    = pick($db, $cong, 'isBank = 1');
$cash    = pick($db, $cong, 'isBank = 0 AND parentId = ' . Ledger::CASH_ROOT);          // a real cash account
$asset   = pick($db, $cong, 'isBank = 0 AND accountTypeId = 3 AND parentId <> ' . Ledger::CASH_ROOT); // asset outside 'cash and cash equivalents'
$expense = pick($db, $cong, 'accountTypeId = 2');
$income  = pick($db, $cong, 'accountTypeId = 1');
$st = $db->prepare('SELECT a.ID FROM tblaccounttypes a
                    WHERE a.deleted = 0 AND a.parentId <> 0
                      AND EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)
                    ORDER BY (a.ID = 33) DESC, a.ID LIMIT 1');
$st->execute();
$reporting = (int) $st->fetchColumn();

echo "Ledger tests - congregation $cong" . PHP_EOL;
echo "Accounts used: bank $bank, cash $cash, expense $expense, income $income, reporting $reporting" . PHP_EOL . PHP_EOL;
if (!$bank || !$cash || !$expense || !$income || !$reporting) {
    fwrite(STDERR, "Could not find one of the test accounts.\n"); exit(2);
}

$TEST_TXN = 2000000000; // fake source id, far above real ids
$OPEN = '2026-01-15';   // inside the open financial year 2025-2026 (a date outside every year is refused since 8.4d)

// 1. Must run inside a transaction
expectError('refuses to write outside a transaction', function () use ($db, $cong) {
    Ledger::nextJournalNo($db, $cong);
}, LogicException::class, 'transaction');

expectError('bank sync refuses to run outside a transaction', function () use ($db, $cong) {
    Ledger::syncBankPostings($db, $cong, 2, 1, 'journal');
}, LogicException::class, 'transaction');
expectError('bank void refuses to run outside a transaction', function () use ($db, $cong) {
    Ledger::voidBankPostings($db, $cong, 2, 1);
}, LogicException::class, 'transaction');

$db->beginTransaction();
// The checks need years 10, 11 and 12 open; set that here, inside the rolled-back transaction, so the run never depends on
// (or changes) the flags on dev.
$db->exec('UPDATE tblfiscalyears SET closed = 0, deleted = 0 WHERE ID IN (10, 11, 12)');
try {
    // 2. Journal numbers
    $peek = Ledger::peekNextJournalNo($db, $cong);
    $j1   = Ledger::nextJournalNo($db, $cong);
    $j2   = Ledger::nextJournalNo($db, $cong);
    check('next journal number matches the preview', $j1 === $peek, "preview $peek, got $j1");
    check('journal numbers increase by 1', $j2 === $j1 + 1, "$j1 then $j2");

    // 3. Create a journal
    $h = ['congregationId' => $cong, 'entryDate' => $OPEN, 'transactionTypeId' => 5,
          'transactionId' => $j1, 'reference' => 'TEST', 'description' => 'ledger test journal'];
    $id = Ledger::post($db, $h, [Ledger::debit($expense, '1000.00', 'test dr'), Ledger::credit($bank, 1000, 'test cr')]);
    $e  = Ledger::getEntry($db, $cong, 5, $j1);
    check('journal created', $e !== null && (int) $e['ID'] === $id && (int) $e['journalNo'] === $j1);
    check('journal has 2 lines', $e !== null && count($e['lines']) === 2);

    // 4. Edit in place
    $id2 = Ledger::post($db, $h, [
        Ledger::debit($expense, 1500), Ledger::credit($bank, 1000), Ledger::credit($cash, 500)]);
    $e = Ledger::getEntry($db, $cong, 5, $j1);
    $sum = 0.0;
    foreach ($e['lines'] as $l) { if ($l['dc'] === 'DEBIT') { $sum += (float) $l['amount']; } }
    check('edit keeps the same entry', $id2 === $id, "first $id, after edit $id2");
    check('edit replaces the lines', count($e['lines']) === 3 && abs($sum - 1500) < 0.001);

    // 5. Rule checks (nothing written when they fail)
    expectError('rejects unbalanced entry', function () use ($db, $h, $expense, $bank) {
        Ledger::post($db, $h, [Ledger::debit($expense, 100), Ledger::credit($bank, 99.99)]);
    }, LedgerException::class, 'do not equal');
    expectError('rejects a single line', function () use ($db, $h, $expense) {
        Ledger::post($db, $h, [Ledger::debit($expense, 100)]);
    }, LedgerException::class, 'at least 2 lines');
    expectError('rejects zero / negative amounts', function () use ($db, $h, $expense, $bank) {
        Ledger::post($db, $h, [Ledger::debit($expense, 0), Ledger::credit($bank, -5)]);
    }, LedgerException::class, 'greater than 0');
    expectError('rejects more than 2 decimals', function () use ($db, $h, $expense, $bank) {
        Ledger::post($db, $h, [Ledger::debit($expense, '10.005'), Ledger::credit($bank, '10.005')]);
    }, LedgerException::class, 'decimals');
    expectError("rejects reporting account $reporting", function () use ($db, $h, $expense, $reporting) {
        Ledger::post($db, $h, [Ledger::debit($expense, 10), Ledger::credit($reporting, 10)]);
    }, LedgerException::class, 'reporting');
    expectError('rejects a root account', function () use ($db, $h, $expense) {
        Ledger::post($db, $h, [Ledger::debit($expense, 10), Ledger::credit(3, 10)]);
    }, LedgerException::class, 'root');
    expectError('rejects an unknown account', function () use ($db, $h, $expense) {
        Ledger::post($db, $h, [Ledger::debit($expense, 10), Ledger::credit(999999999, 10)]);
    }, LedgerException::class, 'does not exist');
    expectError('rejects an invalid date', function () use ($db, $h, $expense, $bank) {
        Ledger::post($db, array_merge($h, ['entryDate' => '2026-02-30']), [Ledger::debit($expense, 1), Ledger::credit($bank, 1)]);
    }, LedgerException::class, 'not a valid');
    $e = Ledger::getEntry($db, $cong, 5, $j1);
    check('failed posts left the entry unchanged', count($e['lines']) === 3);

    // 6. Payment accounts
    check('bank accepted for a bank payment method', Ledger::assertPaymentAccount($db, $cong, $bank, Ledger::PAY_BANK) === $bank);
    check('cash account accepted for cash/M-Pesa', Ledger::assertPaymentAccount($db, $cong, $cash, Ledger::PAY_CASH) === $cash);
    expectError('bank rejected for cash/M-Pesa', function () use ($db, $cong, $bank) {
        Ledger::assertPaymentAccount($db, $cong, $bank, Ledger::PAY_CASH);
    }, LedgerException::class, 'bank');
    expectError('non-bank rejected for a bank method', function () use ($db, $cong, $cash) {
        Ledger::assertPaymentAccount($db, $cong, $cash, Ledger::PAY_BANK);
    }, LedgerException::class, 'not a bank');
    expectError('expense account rejected for cash/M-Pesa', function () use ($db, $cong, $expense) {
        Ledger::assertPaymentAccount($db, $cong, $expense, Ledger::PAY_CASH);
    }, LedgerException::class, 'not a cash account');
    expectError('asset outside cash and cash equivalents rejected for cash/M-Pesa', function () use ($db, $cong, $asset) {
        Ledger::assertPaymentAccount($db, $cong, $asset, Ledger::PAY_CASH);
    }, LedgerException::class, "must be under 'cash and cash equivalents'");
    $banks = array_column(Ledger::listPaymentAccounts($db, $cong, Ledger::PAY_BANK), 'ID');
    $cashes = array_column(Ledger::listPaymentAccounts($db, $cong, Ledger::PAY_CASH), 'ID');
    check('bank dropdown lists only banks', in_array($bank, $banks, true) && !in_array($cash, $banks, true), count($banks) . ' banks');
    check('cash dropdown lists no banks', in_array($cash, $cashes, true) && !in_array($bank, $cashes, true), count($cashes) . ' accounts');
    // 'cash and cash equivalents' tree (task 8.3c)
    check('account under 22 accepted for cash', $cash !== null && Ledger::assertPaymentAccount($db, $cong, $cash, Ledger::PAY_CASH) === $cash);
    check('asset outside 22 absent from the cash list', $asset !== null && !in_array($asset, $cashes, true));
    check('reporting account 22 itself is not a cash account', !in_array(Ledger::CASH_ROOT, $cashes, true));
    $tree = $db->prepare('WITH RECURSIVE t (ID) AS (SELECT ID FROM tblaccounttypes WHERE parentId = ? AND deleted = 0
                                                   UNION SELECT a.ID FROM tblaccounttypes a JOIN t ON a.parentId = t.ID WHERE a.deleted = 0)
                          SELECT ID FROM t');
    $tree->execute([Ledger::CASH_ROOT]);
    $under22 = array_map('intval', $tree->fetchAll(PDO::FETCH_COLUMN));
    check('every account in the cash list is a descendant of 22', $cashes && !array_diff(array_map('intval', $cashes), $under22), count($cashes) . ' accounts');
    check('bank accounts (under 33 under 22) still refused for cash', !in_array($bank, $cashes, true));

    // shared amount check
    check('amountError accepts 100.50 and .5', Ledger::amountError('100.50') === null && Ledger::amountError('.5') === null);
    check('amountError refuses blank, 0, negative, text', Ledger::amountError('') !== null && Ledger::amountError('0') !== null
        && Ledger::amountError('-5') !== null && Ledger::amountError('abc') !== null);
    check('amountError refuses 3 decimals', stripos(Ledger::amountError('1.234') ?? '', '2 decimal places') !== false);
    check('amountError uses the label', strpos(Ledger::amountError('x', 'Sub account amount') ?? '', 'Sub account amount') === 0);

    // account chart changes made inside this (rolled back) transaction
    $mk = $db->prepare('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isBank, congregationId, isSubCategory, active, deleted)
                         VALUES (?, 3, ?, 0, 0, ?, 1, ?)');
    $mk->execute(['zz test deleted parent', Ledger::CASH_ROOT, 0, 1]);
    $delParent = (int) $db->lastInsertId();
    $mk->execute(['zz test child of deleted parent', $delParent, 1, 0]);
    $orphan = (int) $db->lastInsertId();
    check('account under a deleted parent is not postable', !Ledger::isPostingAccount($db, $orphan, $cong));
    expectError('account under a deleted parent is refused', function () use ($db, $cong, $orphan) {
        Ledger::assertPaymentAccount($db, $cong, $orphan, Ledger::PAY_CASH);
    }, LedgerException::class, 'deleted parent');
    $mk->execute(['zz test new cash account', Ledger::CASH_ROOT, 1, 0]);
    $newCash = (int) $db->lastInsertId();
    expectError('cash tree is cached until cleared', function () use ($db, $cong, $newCash) {
        Ledger::assertPaymentAccount($db, $cong, $newCash, Ledger::PAY_CASH);
    }, LedgerException::class, 'not a cash account');
    Ledger::clearCashCache();
    check('after clearCashCache a new account under 22 is accepted', Ledger::assertPaymentAccount($db, $cong, $newCash, Ledger::PAY_CASH) === $newCash);
    check('and it appears in the cash list', in_array($newCash, array_column(Ledger::listPaymentAccounts($db, $cong, Ledger::PAY_CASH), 'ID')));

    // 7. Void, and journal numbers are never reused
    check('void returns true', Ledger::void($db, $cong, 5, $j1) === true);
    check('voided journal no longer active', Ledger::getEntry($db, $cong, 5, $j1) === null);
    check('void of nothing returns false', Ledger::void($db, $cong, 5, $j1) === false);
    expectError('voided journal number cannot be reused', function () use ($db, $h, $expense, $bank) {
        Ledger::post($db, $h, [Ledger::debit($expense, 1), Ledger::credit($bank, 1)]);
    }, LedgerException::class, 'already been used');

    // 8. Non-journal: void then re-post creates a new entry (e.g. re-approval)
    $g = ['congregationId' => $cong, 'entryDate' => $OPEN, 'transactionTypeId' => 2,
          'transactionId' => $TEST_TXN, 'reference' => null, 'description' => null];
    $a = Ledger::post($db, $g, [Ledger::debit($expense, 250, 'first memo'), Ledger::credit($cash, 250)]);
    $e = Ledger::getEntry($db, $cong, 2, $TEST_TXN);
    check('description defaults to first memo', $e['description'] === 'first memo');
    check('non-journal has no journal number', $e['journalNo'] === null);
    Ledger::void($db, $cong, 2, $TEST_TXN);
    $b = Ledger::post($db, $g, [Ledger::debit($expense, 300), Ledger::credit($cash, 300)]);
    check('re-post after void creates a new entry', $b !== $a, "old $a, new $b");

    // 9. Dev mirror into tblledger
    if (defined('LEDGER_LEGACY_WRITE') && LEDGER_LEGACY_WRITE === true) {
        $st = $db->prepare('SELECT COUNT(*) FROM tblledger WHERE congregationId = ? AND transactionType = 2
                            AND transactionId = ? AND deleted = 0');
        $st->execute([$cong, $TEST_TXN]);
        check('legacy mirror wrote 2 active tblledger rows', (int) $st->fetchColumn() === 2);
    } else {
        echo '  (legacy mirror off: LEDGER_LEGACY_WRITE not true)' . PHP_EOL;
    }

    // 10. Bank postings (tblbankpostings sync)
    $bank2 = null;
    $st = $db->prepare('SELECT ID FROM tblaccounttypes WHERE isBank = 1 AND deleted = 0 AND active = 1
                        AND congregationId IN (0, ?) AND ID <> ? ORDER BY ID');
    $st->execute([$cong, $bank]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (Ledger::isPostingAccount($db, (int) $id, $cong)) { $bank2 = (int) $id; break; }
    }
    if ($bank2 === null) { throw new RuntimeException('need a second posting bank account for the bank tests'); }

    $rows = function (int $t, int $x, bool $all = false) use ($db, $cong) {
        $st = $db->prepare('SELECT * FROM tblbankpostings WHERE congregationId = ? AND transactionType = ? AND transactionId = ?'
                           . ($all ? '' : ' AND deleted = 0') . ' ORDER BY ID');
        $st->execute([$cong, $t, $x]);
        return $st->fetchAll();
    };
    $journalMethod = (int) $db->query("SELECT ID FROM tbltransactionmethods WHERE LOWER(methodName) = 'journal'")->fetchColumn();
    check('Journal method exists in tbltransactionmethods', $journalMethod > 0, 'ID ' . $journalMethod);
    $B1  = $TEST_TXN + 1;
    $hb  = ['congregationId' => $cong, 'entryDate' => $OPEN, 'transactionTypeId' => 2,
            'transactionId' => $B1, 'reference' => 'REF1', 'description' => 'bank test'];

    // bank debit line -> one uncleared row
    Ledger::post($db, $hb, [Ledger::debit($bank, '500.00', 'dep memo'), Ledger::credit($income, '500.00')]);
    check('sync returns 1 for one bank line', Ledger::syncBankPostings($db, $cong, 2, $B1, 'journal') === 1);
    $r = $rows(2, $B1);
    $m = $r ? $r[0] : [];
    check('one uncleared bank row, debit side', count($r) === 1 && (int) $m['bankId'] === $bank && (int) $m['cleared'] === 0
          && abs($m['debit'] - 500) < 0.005 && abs($m['credit']) < 0.005 && $m['clearedDare'] === null);
    check('row has method, reference and narration', (int) $m['transactionMethod'] === $journalMethod && $m['reference'] === 'REF1'
          && $m['narration'] === 'dep memo' && (int) $m['transactionType'] === 2 && (int) $m['transactionId'] === $B1);
    check('calling sync again changes nothing', Ledger::syncBankPostings($db, $cong, 2, $B1, 'journal') === 1
          && count($rows(2, $B1, true)) === 1);

    // mark cleared, edit narration only -> same row, still cleared
    $db->prepare('UPDATE tblbankpostings SET cleared = 1, clearedDare = ? WHERE ID = ?')->execute(['2026-01-05', $m['ID']]);
    Ledger::post($db, $hb, [Ledger::debit($bank, '500.00', 'new memo'), Ledger::credit($income, '500.00')]);
    Ledger::syncBankPostings($db, $cong, 2, $B1, 'journal');
    $r = $rows(2, $B1);
    check('narration edit keeps the same cleared row', count($r) === 1 && (int) $r[0]['ID'] === (int) $m['ID']
          && (int) $r[0]['cleared'] === 1 && $r[0]['clearedDare'] === '2026-01-05' && $r[0]['narration'] === 'new memo');

    // 8.9 follow-up: the library refuses to drop a CLEARED row (an amount or bank change on a reconciled posting)
    Ledger::post($db, $hb, [Ledger::debit($bank, '750.50', 'new memo'), Ledger::credit($income, '750.50')]);
    expectError('sync refuses to soft-delete a cleared bank row (amount change)', function () use ($db, $cong, $B1) {
        Ledger::syncBankPostings($db, $cong, 2, $B1, 'journal');
    }, LedgerException::class, 'already been cleared');
    $r = $rows(2, $B1);
    check('...and the cleared row is untouched (still active, cleared, 500)', count($r) === 1 && (int) $r[0]['ID'] === (int) $m['ID'] && (int) $r[0]['cleared'] === 1 && abs($r[0]['debit'] - 500) < 0.005 && count($rows(2, $B1, true)) === 1);
    Ledger::post($db, $hb, [Ledger::debit($bank2, '500.00', 'new memo'), Ledger::credit($income, '500.00')]);
    expectError('sync refuses to drop a cleared bank row (bank change)', function () use ($db, $cong, $B1) {
        Ledger::syncBankPostings($db, $cong, 2, $B1, 'journal');
    }, LedgerException::class, 'already been cleared');
    Ledger::post($db, $hb, [Ledger::debit($bank, '500.00', 'new memo'), Ledger::credit($income, '500.00')]);
    check('a re-sync with the cleared row unchanged is fine', Ledger::syncBankPostings($db, $cong, 2, $B1, 'journal') === 1);
    expectError('void of the bank rows refuses a cleared row', function () use ($db, $cong, $B1) {
        Ledger::voidBankPostings($db, $cong, 2, $B1);
    }, LedgerException::class, 'already been cleared');
    // un-clear the row (what a reconciliation reversal would do) and carry on with the amount change
    $db->prepare('UPDATE tblbankpostings SET cleared = 0, clearedDare = NULL WHERE ID = ?')->execute([$m['ID']]);
    // change bank amount -> old soft-deleted, new uncleared
    Ledger::post($db, $hb, [Ledger::debit($bank, '750.50', 'new memo'), Ledger::credit($income, '750.50')]);
    Ledger::syncBankPostings($db, $cong, 2, $B1, 'journal');
    $r   = $rows(2, $B1);
    $all = $rows(2, $B1, true);
    check('amount edit: old row soft-deleted, new uncleared row',
          count($all) === 2 && count($r) === 1 && (int) $r[0]['ID'] !== (int) $m['ID'] && (int) $r[0]['cleared'] === 0
          && abs($r[0]['debit'] - 750.50) < 0.005 && (int) $all[0]['deleted'] === 1);

    // move the bank line to another bank
    Ledger::post($db, $hb, [Ledger::debit($bank2, '750.50', 'new memo'), Ledger::credit($income, '750.50')]);
    Ledger::syncBankPostings($db, $cong, 2, $B1, 'journal');
    $r = $rows(2, $B1);
    check("bank edit $bank -> $bank2: old soft-deleted, new row on new bank",
          count($r) === 1 && (int) $r[0]['bankId'] === $bank2 && count($rows(2, $B1, true)) === 3);

    // transfer: two bank lines, opposite sides
    $B2 = $TEST_TXN + 2;
    $ht = array_merge($hb, ['transactionId' => $B2]);
    Ledger::post($db, $ht, [Ledger::debit($bank2, 200, 'to second bank'), Ledger::credit($bank, 200, 'from first bank')]);
    check('transfer syncs 2 rows', Ledger::syncBankPostings($db, $cong, 2, $B2, 'journal') === 2);
    $r  = $rows(2, $B2);
    $dr = null;
    $cr = null;
    foreach ($r as $x) { if ($x['debit'] > 0) { $dr = $x; } else { $cr = $x; } }
    check('transfer rows are on opposite sides of the right banks', count($r) === 2 && $dr && $cr
          && (int) $dr['bankId'] === $bank2 && (int) $cr['bankId'] === $bank
          && abs($dr['debit'] - 200) < 0.005 && abs($cr['credit'] - 200) < 0.005);
    check('transfer re-sync with no change keeps both rows', Ledger::syncBankPostings($db, $cong, 2, $B2, 'journal') === 2
          && count($rows(2, $B2, true)) === 2);

    // remove all bank lines
    Ledger::post($db, $ht, [Ledger::debit($expense, 200), Ledger::credit($cash, 200)]);
    check('removing all bank lines returns 0', Ledger::syncBankPostings($db, $cong, 2, $B2, 'journal') === 0);
    check('...and soft-deletes the rows', count($rows(2, $B2)) === 0 && count($rows(2, $B2, true)) === 2);

    // unknown method fails loudly and writes nothing
    $B5 = $TEST_TXN + 5;
    Ledger::post($db, array_merge($hb, ['transactionId' => $B5]), [Ledger::debit($bank, 40), Ledger::credit($income, 40)]);
    foreach (['nonsense', '999', ''] as $bad) {
        expectError("unknown method '$bad' is rejected", function () use ($db, $cong, $B5, $bad) {
            Ledger::syncBankPostings($db, $cong, 2, $B5, $bad);
        }, LedgerException::class, 'unknown bank transaction method');
    }
    check('...and no bank row was written', count($rows(2, $B5, true)) === 0);
    check('method can be given by ID or name', Ledger::syncBankPostings($db, $cong, 2, $B5, (string) $journalMethod) === 1
          && Ledger::syncBankPostings($db, $cong, 2, $B5, 'JOURNAL') === 1 && count($rows(2, $B5, true)) === 1);
    // void + voidBankPostings
    $B3 = $TEST_TXN + 3;
    $hv = array_merge($hb, ['transactionId' => $B3]);
    Ledger::post($db, $hv, [Ledger::debit($bank, 90), Ledger::credit($income, 90)]);
    Ledger::syncBankPostings($db, $cong, 2, $B3, 'journal');
    check('void entry', Ledger::void($db, $cong, 2, $B3) === true);
    check('voidBankPostings returns 1', Ledger::voidBankPostings($db, $cong, 2, $B3) === 1);
    check('entry and rows are deleted', Ledger::getEntry($db, $cong, 2, $B3) === null && count($rows(2, $B3)) === 0
          && Ledger::voidBankPostings($db, $cong, 2, $B3) === 0);

    // source with no active entry
    $B4 = $TEST_TXN + 4;
    check('no active entry: sync returns 0', Ledger::syncBankPostings($db, $cong, 2, $B4, 'journal') === 0
          && count($rows(2, $B4)) === 0);
    $db->prepare('INSERT INTO tblbankpostings (bankId, transactionDate, debit, credit, transactionMethod, transactionType, transactionId, congregationId)
                  VALUES (?, ?, 5, 0, 1, 2, ?, ?)')->execute([$bank, $OPEN, $B4, $cong]);
    check('no active entry: sync soft-deletes leftover rows', Ledger::syncBankPostings($db, $cong, 2, $B4, 'journal') === 0
          && count($rows(2, $B4)) === 0 && count($rows(2, $B4, true)) === 1);


    // =================== petty cash accounts (task 8.4b)
    echo PHP_EOL . 'Petty cash accounts' . PHP_EOL;
    check('PETTY_CASH_ACCOUNTS is 98 and 194', Ledger::PETTY_CASH_ACCOUNTS === [98, 194]);
    check('isPettyCashAccount: 98, 194, "194" yes', Ledger::isPettyCashAccount(98) && Ledger::isPettyCashAccount(194) && Ledger::isPettyCashAccount('194'));
    check('isPettyCashAccount: 34, a bank, 0, text no', !Ledger::isPettyCashAccount(34) && !Ledger::isPettyCashAccount($bank) && !Ledger::isPettyCashAccount(0) && !Ledger::isPettyCashAccount('abc'));
    $lp = array_map('intval', array_column(Ledger::listPettyCashAccounts($db, $cong), 'ID')); sort($lp);
    check('listPettyCashAccounts (congregation 11): exactly 98 and 194', $lp === [98, 194], json_encode($lp));
    $otherCong = (int) $db->query('SELECT ID FROM tblcongregation WHERE ID <> ' . $cong . ' ORDER BY ID LIMIT 1')->fetchColumn();
    $lpo = array_map('intval', array_column(Ledger::listPettyCashAccounts($db, $otherCong), 'ID'));
    check('listPettyCashAccounts (another congregation): 98 but not 194 (194 belongs to congregation 11)', in_array(98, $lpo, true) && !in_array(194, $lpo, true), json_encode($lpo));
    check('assertPettyCashAccount accepts 98 and 194', Ledger::assertPettyCashAccount($db, $cong, 98) === 98 && Ledger::assertPettyCashAccount($db, $cong, 194) === 194);
    expectError('assertPettyCashAccount refuses 34 (cash at hand)', function () use ($db, $cong) { Ledger::assertPettyCashAccount($db, $cong, 34); }, LedgerException::class, 'not a petty cash account');
    expectError('assertPettyCashAccount refuses a bank', function () use ($db, $cong, $bank) { Ledger::assertPettyCashAccount($db, $cong, $bank); }, LedgerException::class, 'not a petty cash account');
    expectError('assertPettyCashAccount refuses 194 for another congregation', function () use ($db, $otherCong) { Ledger::assertPettyCashAccount($db, $otherCong, 194); }, LedgerException::class, 'another congregation');

    // =================== number sequences (task 8.4b)
    echo PHP_EOL . 'Number sequences' . PHP_EOL;
    $peek = Ledger::peekNextNumber($db, $cong, Ledger::SEQ_PETTYCASH);
    $n1 = Ledger::nextNumber($db, $cong, Ledger::SEQ_PETTYCASH);
    $n2 = Ledger::nextNumber($db, $cong, Ledger::SEQ_PETTYCASH);
    check('petty cash: preview = next number', $peek === $n1, "preview $peek, got $n1");
    check('petty cash: numbers increase by 1', $n2 === $n1 + 1);
    check('petty cash: preview moves on after a number is taken', Ledger::peekNextNumber($db, $cong, Ledger::SEQ_PETTYCASH) === $n2 + 1);
    check('petty cash ignores the year', Ledger::nextNumber($db, $cong, Ledger::SEQ_PETTYCASH, 12) === $n2 + 1 && Ledger::nextNumber($db, $cong, Ledger::SEQ_PETTYCASH, 99) === $n2 + 2);
    expectError('receipt sequence needs a fiscal year', function () use ($db, $cong) { Ledger::nextNumber($db, $cong, Ledger::SEQ_RECEIPT, 0); }, LedgerException::class, 'fiscal year');
    expectError('receipt preview needs a fiscal year', function () use ($db, $cong) { Ledger::peekNextNumber($db, $cong, Ledger::SEQ_RECEIPT, 0); }, LedgerException::class, 'fiscal year');
    expectError('unknown sequence refused', function () use ($db, $cong) { Ledger::nextNumber($db, $cong, 'nope', 1); }, LogicException::class, 'unknown');
    $r12 = Ledger::peekNextNumber($db, $cong, Ledger::SEQ_RECEIPT, 12);
    $a = Ledger::nextNumber($db, $cong, Ledger::SEQ_RECEIPT, 12);
    $b = Ledger::nextNumber($db, $cong, Ledger::SEQ_RECEIPT, 12);
    check('receipt: preview = next, numbers increase', $a === $r12 && $b === $a + 1, "$r12 / $a / $b");
    $r11 = Ledger::peekNextNumber($db, $cong, Ledger::SEQ_RECEIPT, 11);
    check('receipt: each fiscal year has its own sequence', Ledger::nextNumber($db, $cong, Ledger::SEQ_RECEIPT, 11) === $r11 && $r11 !== $b + 1);
    // a missing sequence row is seeded from the highest number in the source table (deleted rows included)
    $db->prepare('DELETE FROM receipt_number_seq WHERE congregationId = ? AND kind = ?')->execute([$cong, 'receipt']);
    $mx = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(receiptNo, '/', -1) AS UNSIGNED)), 0) FROM tblcontributions_header WHERE congregationId = ? AND fiscalYearId = 12");
    $mx->execute([$cong]);
    $max12 = (int) $mx->fetchColumn();
    check('receipt: preview with no sequence row = max + 1', Ledger::peekNextNumber($db, $cong, Ledger::SEQ_RECEIPT, 12) === $max12 + 1);
    check('receipt: missing sequence row is seeded from max + 1', Ledger::nextNumber($db, $cong, Ledger::SEQ_RECEIPT, 12) === $max12 + 1
          && Ledger::nextNumber($db, $cong, Ledger::SEQ_RECEIPT, 12) === $max12 + 2);
    $db->prepare('DELETE FROM receipt_number_seq WHERE congregationId = ? AND kind = ?')->execute([$cong, 'pettycash']);
    $mp = $db->prepare('SELECT COALESCE(MAX(ReceiptNo), 0) FROM tblpettycash WHERE CongregationId = ?');
    $mp->execute([$cong]);
    $maxp = (int) $mp->fetchColumn();
    check('petty cash: missing sequence row is seeded from max + 1 (deleted rows included)', Ledger::nextNumber($db, $cong, Ledger::SEQ_PETTYCASH) === $maxp + 1);

    // expense voucher numbers (task 8.5d): per congregation + fiscal year, seeded from tblexpenses
    $mv = $db->prepare('SELECT COALESCE(MAX(voucherNo), 0) FROM tblexpenses WHERE congregationId = ? AND fiscalYearId = 12');
    $mv->execute([$cong]);
    $maxv = (int) $mv->fetchColumn();
    $db->prepare('DELETE FROM receipt_number_seq WHERE congregationId = ? AND kind = ?')->execute([$cong, 'voucher']);
    check('voucher: preview with no sequence row = max + 1 of tblexpenses (deleted rows included)', Ledger::peekNextNumber($db, $cong, Ledger::SEQ_VOUCHER, 12) === $maxv + 1);
    $vp = Ledger::peekNextNumber($db, $cong, Ledger::SEQ_VOUCHER, 12);
    $va = Ledger::nextNumber($db, $cong, Ledger::SEQ_VOUCHER, 12);
    $vb = Ledger::nextNumber($db, $cong, Ledger::SEQ_VOUCHER, 12);
    check('voucher: missing sequence row is seeded from max + 1; preview = next; numbers increase by 1', $va === $maxv + 1 && $va === $vp && $vb === $va + 1, "$maxv / $vp / $va / $vb");
    check('voucher: preview moves on after a number is taken, and reserves nothing', Ledger::peekNextNumber($db, $cong, Ledger::SEQ_VOUCHER, 12) === $vb + 1 && Ledger::peekNextNumber($db, $cong, Ledger::SEQ_VOUCHER, 12) === $vb + 1);
    $v11 = Ledger::peekNextNumber($db, $cong, Ledger::SEQ_VOUCHER, 11);
    check('voucher: each fiscal year has its own sequence', Ledger::nextNumber($db, $cong, Ledger::SEQ_VOUCHER, 11) === $v11 && Ledger::peekNextNumber($db, $cong, Ledger::SEQ_VOUCHER, 12) === $vb + 1);
    check('voucher: the sequence is separate from receipts and petty cash',
          Ledger::peekNextNumber($db, $cong, Ledger::SEQ_RECEIPT, 12) !== $vb + 1 || Ledger::peekNextNumber($db, $cong, Ledger::SEQ_PETTYCASH) !== $vb + 1);
    expectError('voucher sequence needs a fiscal year', function () use ($db, $cong) { Ledger::nextNumber($db, $cong, Ledger::SEQ_VOUCHER, 0); }, LedgerException::class, 'fiscal year');
    expectError('voucher preview needs a fiscal year', function () use ($db, $cong) { Ledger::peekNextNumber($db, $cong, Ledger::SEQ_VOUCHER, 0); }, LedgerException::class, 'fiscal year');
    $vo = Ledger::peekNextNumber($db, 99999, Ledger::SEQ_VOUCHER, 12);
    check('voucher: another congregation has its own sequence (empty = 1)', $vo === 1, (string) $vo);

    // safety net (task 8.5f): a stored number above the counter makes the next allocation skip past it; peek = next
    $uid = (int) $db->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();
    $acctAny = (int) $db->query('SELECT ID FROM tblaccounttypes WHERE deleted = 0 LIMIT 1')->fetchColumn();
    $seqRow = function (string $kind, int $year, int $last) use ($db, $cong) {
        $db->prepare('DELETE FROM receipt_number_seq WHERE congregationId = ? AND kind = ? AND fiscalYearId = ?')->execute([$cong, $kind, $year]);
        $db->prepare('INSERT INTO receipt_number_seq (congregationId, kind, fiscalYearId, lastNo) VALUES (?, ?, ?, ?)')->execute([$cong, $kind, $year, $last]);
    };
    $stored = [
        'receipt' => function (int $no) use ($db, $cong, $uid) {
            $db->prepare("INSERT INTO tblcontributions_header (fiscalYearId, postedBy, postedDate, receiptNo, congregationId) VALUES (12, ?, CURDATE(), ?, ?)")->execute([$uid, (string) $no, $cong]);
        },
        'voucher' => function (int $no) use ($db, $cong, $uid, $acctAny) {
            $db->prepare("INSERT INTO tblexpenses (fiscalYearId, voucherNo, expenseType, expenseDate, accountId, paymethodId, amount, status, postedBy, postedDate, congregationId, deleted)
                          VALUES (12, ?, 1, '2026-01-15', ?, 1, 1, 0, ?, CURDATE(), ?, 1)")->execute([$no, $acctAny, $uid, $cong]);   // a DELETED row still counts
        },
        'pettycash' => function (int $no) use ($db, $cong) {
            $db->prepare("INSERT INTO tblpettycash (ReceiptNo, TransactionDate, CongregationId, Deleted) VALUES (?, CURDATE(), ?, 1)")->execute([$no, $cong]);
        },
    ];
    foreach ([['receipt', Ledger::SEQ_RECEIPT, 12], ['voucher', Ledger::SEQ_VOUCHER, 12], ['pettycash', Ledger::SEQ_PETTYCASH, 0]] as [$label, $kind, $yr]) {
        $seqRow($kind, $yr, 3);
        $stored[$label](880000);
        $pk = Ledger::peekNextNumber($db, $cong, $kind, $yr);
        $nx = Ledger::nextNumber($db, $cong, $kind, $yr);
        $nx2 = Ledger::nextNumber($db, $cong, $kind, $yr);
        $cn = $db->prepare('SELECT lastNo FROM receipt_number_seq WHERE congregationId = ? AND kind = ? AND fiscalYearId = ?');
        $cn->execute([$cong, $kind, $yr]);
        check("$label: a stored number above the counter is skipped (peek = next = stored + 1, counter follows)", $pk === 880001 && $nx === 880001 && $nx2 === 880002 && (int) $cn->fetchColumn() === 880002, json_encode([$pk, $nx, $nx2]));
        $seqRow($kind, $yr, 880100);
        check("$label: a counter above every stored number wins", Ledger::peekNextNumber($db, $cong, $kind, $yr) === 880101 && Ledger::nextNumber($db, $cong, $kind, $yr) === 880101);
    }

    // =================== financial year lock (task 8.4d)
    echo PHP_EOL . 'Financial year lock' . PHP_EOL;
    $OPEN10 = '2023-11-15';    // year 10 (2023-2024, opened at the start of the run)
    $OPEN11 = '2025-01-15';    // year 11 (2024-2025, opened at the start of the run)
    $NOYEAR = '2013-06-01';    // covered by no year
    $DELCLOSED = '2022-09-01'; // year 8 (2021/2022): closed and deleted (history, never touched)
    $setYear = function (int $id, int $closed, int $deleted) use ($db) {
        $db->prepare('UPDATE tblfiscalyears SET closed = ?, deleted = ? WHERE ID = ?')->execute([$closed, $deleted, $id]);
    };
    $mkh = function (int $n, string $date) use ($cong, $TEST_TXN) {
        return ['congregationId' => $cong, 'entryDate' => $date, 'transactionTypeId' => 2, 'transactionId' => $TEST_TXN + $n, 'reference' => 'YR', 'description' => 'year lock test'];
    };
    $two = function () use ($expense, $income) { return [Ledger::debit($expense, 100), Ledger::credit($income, 100)]; };
    $postErr = function (string $name, array $h, string $msg) use ($db, $two) {
        expectError($name, function () use ($db, $h, $two) { Ledger::post($db, $h, $two()); }, LedgerException::class, $msg);
    };
    check('yearForDate: the year covering a date; deleted years are found too', Ledger::yearForDate($db, $OPEN)['ID'] == 12 && Ledger::yearForDate($db, $DELCLOSED)['ID'] == 8 && Ledger::yearForDate($db, $NOYEAR) === null);
    check('dateProblem: open year -> null, no year -> message', Ledger::dateProblem($db, $OPEN) === null && Ledger::dateProblem($db, $NOYEAR) === 'No financial year is defined for 2013-06-01');
    check('closedProblem: a date outside every year is not a problem', Ledger::closedProblem($db, $NOYEAR) === null);

    // new entries
    $postErr('post: no financial year for the date refused', $mkh(60, $NOYEAR), 'No financial year is defined for 2013-06-01');
    $postErr('post: closed AND deleted year refused (reported as closed)', $mkh(61, $DELCLOSED), 'The financial year 2021/2022 is closed');
    $setYear(10, 1, 0);
    $postErr('post: closed year refused', $mkh(62, $OPEN10), 'The financial year 2023-2024 is closed');
    $setYear(10, 0, 1);
    $postErr('post: deleted (never closed) year refused', $mkh(63, $OPEN10), 'The financial year 2023-2024 has been deleted');
    $setYear(10, 0, 0);
    check('failed year-lock posts wrote nothing', Ledger::getEntry($db, $cong, 2, $TEST_TXN + 60) === null && Ledger::getEntry($db, $cong, 2, $TEST_TXN + 62) === null
          && Ledger::getEntry($db, $cong, 2, $TEST_TXN + 63) === null);
    check('post: an open year is accepted', Ledger::post($db, $mkh(64, $OPEN10), $two()) > 0 && Ledger::post($db, $mkh(65, $OPEN11), $two()) > 0);

    // edit of an entry whose year is closed afterwards
    $setYear(10, 1, 0);
    $postErr('edit: entry dated in a closed year refused (same date)', $mkh(64, $OPEN10), 'is closed');
    expectError('edit: moving it out names the entry date', function () use ($db, $mkh, $two, $OPEN) { Ledger::post($db, $mkh(64, $OPEN), $two()); }, LedgerException::class, 'the entry is dated 2023-11-15');
    $postErr('edit: moving a closed-year entry to an open date refused', $mkh(64, $OPEN), 'The financial year 2023-2024 is closed');
    check('edit refused: the entry is unchanged', Ledger::getEntry($db, $cong, 2, $TEST_TXN + 64)['entryDate'] === $OPEN10);
    $setYear(10, 0, 0);
    // edit moving INTO a closed year
    $setYear(11, 1, 0);
    $postErr('edit: an entry already in a closed year cannot be re-posted', $mkh(65, $OPEN11), 'The financial year 2024-2025 is closed');
    $setYear(11, 0, 0);
    $hMove = $mkh(66, $OPEN);
    Ledger::post($db, $hMove, $two());
    $setYear(11, 1, 0);
    $postErr('edit: moving an entry into a closed year refused', array_merge($hMove, ['entryDate' => $OPEN11]), 'The financial year 2024-2025 is closed');
    $setYear(11, 0, 0);
    check('edit: moving between two open years works', Ledger::post($db, array_merge($hMove, ['entryDate' => $OPEN11]), $two()) > 0
          && Ledger::getEntry($db, $cong, 2, $TEST_TXN + 66)['entryDate'] === $OPEN11);

    // void
    $setYear(10, 1, 0);
    expectError('void: entry dated in a closed year refused', function () use ($db, $cong, $TEST_TXN) { Ledger::void($db, $cong, 2, $TEST_TXN + 64); }, LedgerException::class, 'The financial year 2023-2024 is closed');
    check('void refused: the entry is still active', Ledger::getEntry($db, $cong, 2, $TEST_TXN + 64) !== null);
    $setYear(10, 0, 1);
    expectError('void: entry dated in a deleted (never closed) year refused', function () use ($db, $cong, $TEST_TXN) { Ledger::void($db, $cong, 2, $TEST_TXN + 64); }, LedgerException::class, 'has been deleted');
    $setYear(10, 0, 0);
    check('void: works again once the year is open (returns true)', Ledger::void($db, $cong, 2, $TEST_TXN + 64) === true && Ledger::getEntry($db, $cong, 2, $TEST_TXN + 64) === null);
    check('void: nothing to void still returns false', Ledger::void($db, $cong, 2, $TEST_TXN + 99) === false);
    // an entry dated outside every year (legacy) can be voided but not re-posted
    $db->prepare('INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, deleted) VALUES (?, ?, 2, ?, 0)')
       ->execute([$cong, $NOYEAR, $TEST_TXN + 70]);
    $postErr('edit: an entry dated outside every year cannot be re-posted on that date', $mkh(70, $NOYEAR), 'No financial year is defined');
    check('void: an entry dated outside every year can be voided', Ledger::void($db, $cong, 2, $TEST_TXN + 70) === true);

} catch (Throwable $e) {
    check('unexpected error', false, $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
}

echo PHP_EOL . "$pass passed, $fail failed. Everything was rolled back." . PHP_EOL;
exit($fail === 0 ? 0 : 1);

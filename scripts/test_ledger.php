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
$cash    = pick($db, $cong, 'isBank = 0 AND accountTypeId = 3');
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

// 1. Must run inside a transaction
expectError('refuses to write outside a transaction', function () use ($db, $cong) {
    Ledger::nextJournalNo($db, $cong);
}, LogicException::class, 'transaction');

$db->beginTransaction();
try {
    // 2. Journal numbers
    $peek = Ledger::peekNextJournalNo($db, $cong);
    $j1   = Ledger::nextJournalNo($db, $cong);
    $j2   = Ledger::nextJournalNo($db, $cong);
    check('next journal number matches the preview', $j1 === $peek, "preview $peek, got $j1");
    check('journal numbers increase by 1', $j2 === $j1 + 1, "$j1 then $j2");

    // 3. Create a journal
    $h = ['congregationId' => $cong, 'entryDate' => date('Y-m-d'), 'transactionTypeId' => 5,
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
    }, LedgerException::class, 'not an asset');
    $banks = array_column(Ledger::listPaymentAccounts($db, $cong, Ledger::PAY_BANK), 'ID');
    $cashes = array_column(Ledger::listPaymentAccounts($db, $cong, Ledger::PAY_CASH), 'ID');
    check('bank dropdown lists only banks', in_array($bank, $banks, true) && !in_array($cash, $banks, true), count($banks) . ' banks');
    check('cash dropdown lists no banks', in_array($cash, $cashes, true) && !in_array($bank, $cashes, true), count($cashes) . ' accounts');

    // 7. Void, and journal numbers are never reused
    check('void returns true', Ledger::void($db, $cong, 5, $j1) === true);
    check('voided journal no longer active', Ledger::getEntry($db, $cong, 5, $j1) === null);
    check('void of nothing returns false', Ledger::void($db, $cong, 5, $j1) === false);
    expectError('voided journal number cannot be reused', function () use ($db, $h, $expense, $bank) {
        Ledger::post($db, $h, [Ledger::debit($expense, 1), Ledger::credit($bank, 1)]);
    }, LedgerException::class, 'already been used');

    // 8. Non-journal: void then re-post creates a new entry (e.g. re-approval)
    $g = ['congregationId' => $cong, 'entryDate' => date('Y-m-d'), 'transactionTypeId' => 2,
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
} catch (Throwable $e) {
    check('unexpected error', false, $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
}

echo PHP_EOL . "$pass passed, $fail failed. Everything was rolled back." . PHP_EOL;
exit($fail === 0 ? 0 : 1);

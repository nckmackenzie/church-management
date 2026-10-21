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

expectError('bank sync refuses to run outside a transaction', function () use ($db, $cong) {
    Ledger::syncBankPostings($db, $cong, 2, 1, 'journal');
}, LogicException::class, 'transaction');
expectError('bank void refuses to run outside a transaction', function () use ($db, $cong) {
    Ledger::voidBankPostings($db, $cong, 2, 1);
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
    $hb  = ['congregationId' => $cong, 'entryDate' => date('Y-m-d'), 'transactionTypeId' => 2,
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
                  VALUES (?, ?, 5, 0, 1, 2, ?, ?)')->execute([$bank, date('Y-m-d'), $B4, $cong]);
    check('no active entry: sync soft-deletes leftover rows', Ledger::syncBankPostings($db, $cong, 2, $B4, 'journal') === 0
          && count($rows(2, $B4)) === 0 && count($rows(2, $B4, true)) === 1);

} catch (Throwable $e) {
    check('unexpected error', false, $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
}

echo PHP_EOL . "$pass passed, $fail failed. Everything was rolled back." . PHP_EOL;
exit($fail === 0 ? 0 : 1);

<?php
/**
 * Task 9.5: bank reconciliation / cashflow figures (Bankreconcilliation model) on a two-bank fixture, exact-cent matching in Ledger,
 * the tblbankpostings DECIMAL(18,2) script (guard, idempotence, totals), the accumulated-fund wording of decision 151.
 * Self-cleaning (zz t95 prefix). CLI only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Bankreconcilliation.php';

$pdo = (new Database)->pdo();
$pass = 0; $fail = 0;
function ok($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }

$created = ['accounts' => [], 'entries' => [], 'congs' => [], 'postings' => []];
try {
    $T  = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t95 main', 0, 0)"); $created['congs'][] = $T;
    $T2 = x("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t95 other', 0, 0)"); $created['congs'][] = $T2;
    $_SESSION['congId'] = $T; $_SESSION['userId'] = 64;
    $bankAcct = function ($name, $cong) use (&$created) {
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, forGroup, active, congregationId, deleted) VALUES (?,3,33,1,1,0,1,?,0)', [$name, $cong]);
        $created['accounts'][] = $id; return $id;
    };
    $acct = function ($name, $root, $cong) use (&$created) {
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, forGroup, active, congregationId, deleted) VALUES (?,?,?,1,0,0,1,?,0)', [$name, $root, $root, $cong]);
        $created['accounts'][] = $id; return $id;
    };
    $B1 = $bankAcct('zz t95 bank one', $T); $B2 = $bankAcct('zz t95 bank two', $T);
    $OTH = $acct('zz t95 income', 1, $T);
    $n = 0;
    $entry = function ($cong, $date, array $lines, $deleted = 0) use (&$created, &$n) {
        $n++; $txn = 950000 + $n;
        $id = x('INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,5,?,?,?,?,?)', [$cong, $date, $txn, $txn, 'zz t95 ref ' . $n, 'zz t95 desc ' . $n, $deleted]);
        $created['entries'][] = $id; $ln = 0;
        foreach ($lines as [$a, $dc, $amt]) { x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $a, $dc, $amt, 'zz t95 memo ' . $n, ++$ln]); }
        return $id;
    };
    $DR = 'DEBIT'; $CR = 'CREDIT';
    // bank one: +1,000.00 (Sep 1), -200.00 (Sep 10), +50.00 (Oct 5, after the as-of date); excluded: voided +9,999.00, another congregation +5,555.00
    $entry($T, '2025-09-01', [[$B1, $DR, '1000.00'], [$OTH, $CR, '1000.00']]);
    $entry($T, '2025-09-10', [[$OTH, $DR, '200.00'], [$B1, $CR, '200.00']]);
    $entry($T, '2025-10-05', [[$B1, $DR, '50.00'], [$OTH, $CR, '50.00']]);
    $entry($T, '2025-09-15', [[$B1, $DR, '9999.00'], [$OTH, $CR, '9999.00']], 1);
    $entry($T2, '2025-09-15', [[$B1, $DR, '5555.00'], [$OTH, $CR, '5555.00']]);
    // bank two: +777.00 and a line on the parent account of nothing: other bank's lines never count for bank one
    $entry($T, '2025-09-05', [[$B2, $DR, '777.00'], [$OTH, $CR, '777.00']]);

    $method = (int) q('SELECT ID FROM tbltransactionmethods ORDER BY ID LIMIT 1')[0]['ID'];
    $post = function ($bank, $date, $debit, $credit, $cleared, $clearedOn, $deleted = 0, $cong = null, $ref = 'zz t95 ref') use (&$created, $T, $method) {
        $id = x('INSERT INTO tblbankpostings (bankId, transactionDate, debit, credit, transactionMethod, reference, cleared, clearedDare, transactionType, transactionId, deleted, congregationId) VALUES (?,?,?,?,?,?,?,?,9,0,?,?)',
                [$bank, $date, $debit, $credit, $method, $ref, $cleared, $clearedOn, $deleted, $cong ?? $T]);
        $created['postings'][] = $id; return $id;
    };
    $post($B1, '2025-09-01', '1000.00', '0.00', 1, '2025-09-05');         // cleared deposit
    $post($B1, '2025-09-12', '0.00', '80.00', 1, '2025-09-14');           // cleared withdrawal
    $post($B1, '2025-09-10', '0.00', '200.00', 0, null);                  // uncleared withdrawal
    $post($B1, '2025-09-20', '300.00', '0.00', 1, '2025-10-15');          // deposit cleared AFTER the as-of date = uncleared at it
    $post($B1, '2025-09-21', '0.00', '0.00', 0, null);                    // zero-amount row: in neither list nor total
    $post($B1, '2025-09-22', '111.00', '0.00', 0, null, 1);               // deleted
    $post($B1, '2025-09-23', '444.00', '0.00', 0, null, 0, $T2);          // another congregation
    $post($B2, '2025-09-24', '555.00', '0.00', 0, null);                  // another bank
    $post($B1, '2025-09-25', '25.00', '0.00', 1, null);                   // cleared flag without date = uncleared
    $post($B1, '2025-08-31', '55.00', '0.00', 0, null);                   // before the range
    $post($B1, '2025-10-01', '65.00', '0.00', 0, null);                   // after the range

    $m = new Bankreconcilliation;
    $d = ['bank' => $B1, 'from' => '2025-09-01', 'to' => '2025-09-30'];

    echo "== book balance from the journal\n";
    ok('book balance of bank one at 30-09-2025 = 1,000.00 - 200.00 = 800.00 (other bank, voided entry, other congregation excluded)', $m->bookBalance($B1, '2025-09-30') === 80000, (string) $m->bookBalance($B1, '2025-09-30'));
    ok('book balance at 31-10-2025 includes the October line: 850.00', $m->bookBalance($B1, '2025-10-31') === 85000);
    ok('book balance of bank two = 777.00', $m->bookBalance($B2, '2025-09-30') === 77700);
    ok('book balance before any posting is 0', $m->bookBalance($B1, '2025-08-31') === 0);
    $r = $m->getAmountsrecon($d);
    ok('getAmountsrecon returns the journal balance as `book`', $r['book'] === 80000);
    ok('the cashbook (tblbankpostings) is not the book balance: 1000 - 80 - 200 - ... differs from the journal', ($r['clearedDeposits'] - $r['clearedWithdrawals'] - $r['unclearedWithdrawals'] + $r['unclearedDeposits']) !== $r['book']);

    echo "== cleared / uncleared from the cashbook\n";
    ok('cleared deposits 1,000.00 (cleared on or before the end date)', $r['clearedDeposits'] === 100000, (string) $r['clearedDeposits']);
    ok('cleared withdrawals 80.00', $r['clearedWithdrawals'] === 8000);
    ok('uncleared deposits 325.00 (late clear and NULL clear date included; deleted, other bank, other congregation, before/after range excluded)', $r['unclearedDeposits'] === 32500, (string) $r['unclearedDeposits']);
    ok('uncleared withdrawals 200.00', $r['unclearedWithdrawals'] === 20000);
    $up = $m->UnclearedReport(['type' => 'deposit', 'bankid' => $B1, 'sdate' => '2025-09-01', 'edate' => '2025-09-30']);
    ok('uncleared deposit list = 300.00 late-clear + 25.00 NULL-clear-date rows only (no boundary or zero row)', count($up) === 2 && array_column($up, 'amount') === ['300.00', '25.00'], json_encode($up));
    $uw = $m->UnclearedReport(['type' => 'withdraw', 'bankid' => $B1, 'sdate' => '2025-09-01', 'edate' => '2025-09-30']);
    ok('uncleared withdrawal list = the 200.00 row only (the zero-amount row is not a withdrawal)', count($uw) === 1 && $uw[0]->amount === '200.00');
    $cd = $m->ClearedReport(['type' => 'deposit', 'bankid' => $B1, 'sdate' => '2025-09-01', 'edate' => '2025-09-30']);
    $cw = $m->ClearedReport(['type' => 'withdraw', 'bankid' => $B1, 'sdate' => '2025-09-01', 'edate' => '2025-09-30']);
    ok('cleared lists: the 1,000.00 deposit and the 80.00 withdrawal', count($cd) === 1 && $cd[0]->amount === '1000.00' && count($cw) === 1 && $cw[0]->amount === '80.00');
    $sumList = function ($rows) { $t = 0; foreach ($rows as $x) { $t += FinancialReport::cents($x->amount); } return $t; };
    ok('every list adds up to its total on the screen', $sumList($up) === $r['unclearedDeposits'] && $sumList($uw) === $r['unclearedWithdrawals'] && $sumList($cd) === $r['clearedDeposits'] && $sumList($cw) === $r['clearedWithdrawals']);
    ok('the selected range excludes the day before and day after from both totals and detail', $r['unclearedDeposits'] === 32500 && !in_array('55.00', array_column($up, 'amount'), true) && !in_array('65.00', array_column($up, 'amount'), true));
    $wide = $m->getAmountsrecon(['bank' => $B1, 'from' => '2025-01-01', 'to' => '2025-12-31']);
    ok('a wider range picks up both boundary rows and the NULL-clear row (145.00) and clears the late 300.00 deposit', $wide['unclearedDeposits'] === 14500 && $wide['clearedDeposits'] === 130000, json_encode($wide));
    $stray = $m->getAmountsrecon(['bank' => $B2, 'from' => '2025-09-01', 'to' => '2025-09-30']);
    ok('bank two: its own cashbook row only (555.00 uncleared) and its own journal balance', $stray['unclearedDeposits'] === 55500 && $stray['book'] === 77700);
    $cnt = $pdo->query("SELECT COUNT(*) FROM tblbankpostings WHERE bankId = $B1 AND debit = 0 AND credit = 0")->fetchColumn();
    ok('the zero-amount row exists in the cashbook but is in no list', (int) $cnt === 1);

    echo "== cashflow opening balance from the journal\n";
    $cf = $m->getAmounts(['bank' => $B1, 'from' => '2025-10-01', 'to' => '2025-10-31']);
    ok('opening balance on 01-10-2025 = the journal balance at 30-09-2025 (800.00)', $cf['opening'] === 80000);
    $cf0 = $m->getAmounts(['bank' => $B1, 'from' => '2025-09-01', 'to' => '2025-09-30']);
    ok('opening balance on 01-09-2025 = 0, closing book balance = 800.00; movements are the range cashbook totals', $cf0['opening'] === 0 && $cf0['closing'] === 80000 && $cf0['clearedDeposits'] === 100000);
    $reconExpected = $r['book'] - $r['unclearedDeposits'] + $r['unclearedWithdrawals'];
    $cashflowExpected = $cf0['closing'] - $cf0['unclearedDeposits'] + $cf0['unclearedWithdrawals'];
    $oldCashflowExpected = $cf0['opening'] + $cf0['clearedDeposits'] - $cf0['clearedWithdrawals'] - $cf0['unclearedDeposits'] + $cf0['unclearedWithdrawals'];
    ok('cashflow expected statement balance equals reconciliation (675.00) and no longer uses the mixed opening/movements formula', $cashflowExpected === 67500 && $cashflowExpected === $reconExpected && $cashflowExpected !== $oldCashflowExpected);
    ok('a bank with no journal lines before the start opens at 0 (no undefined index)', $m->getAmounts(['bank' => $B2, 'from' => '2025-09-01', 'to' => '2025-09-30'])['opening'] === 0 && $m->getAmounts(['bank' => $B2, 'from' => '2025-09-06', 'to' => '2025-09-30'])['opening'] === 77700);

    echo "== exact cents (Ledger)\n";
    ok('exactCents: "100.1" = 10010, 100.1 (float) = 10010, "5." = 500, ".5" = 50, "-3.10" = -310, 7 = 700', Ledger::exactCents('100.1') === 10010 && Ledger::exactCents(100.1) === 10010 && Ledger::exactCents('5.') === 500 && Ledger::exactCents('.5') === 50 && Ledger::exactCents('-3.10') === -310 && Ledger::exactCents(7) === 700);
    ok('exactCents: a third decimal, an exponent, empty and text are null; "1.500" is fine', Ledger::exactCents('0.015') === null && Ledger::exactCents(0.015) === null && Ledger::exactCents('1e3') === null && Ledger::exactCents('') === null && Ledger::exactCents('abc') === null && Ledger::exactCents('1.500') === 150 && Ledger::exactCents(null) === null);
    ok('amountError: 0.001, 12.345 refused for decimals; 0, 0.00 and text for being no positive number; 17 integer digits too large; 12.30 and 5 fine', Ledger::amountError('12.345') !== null && strpos((string) Ledger::amountError('12.345'), 'decimal') !== false && Ledger::amountError('0.001') !== null && Ledger::amountError('0') !== null && Ledger::amountError('0.00') !== null && Ledger::amountError('x') !== null && strpos((string) Ledger::amountError('12345678901234567'), 'large') !== false && Ledger::amountError('12.30') === null && Ledger::amountError('5') === null);
    $rm = new ReflectionMethod('Ledger', 'matchBankRow'); $rm->setAccessible(true);
    $rows = [['bankId' => $B1, 'debit' => '100.00', 'credit' => '0.00'], ['bankId' => $B1, 'debit' => '0.00', 'credit' => '100.00']];
    ok('matchBankRow: the same amount matches its row', $rm->invoke(null, $rows, ['bankId' => $B1, 'debit' => '100.00', 'credit' => '0.00'], []) === 0);
    ok('matchBankRow: 100.01 is a different row (no 0.005 tolerance)', $rm->invoke(null, $rows, ['bankId' => $B1, 'debit' => '100.01', 'credit' => '0.00'], []) === null);
    ok('matchBankRow: 99.99 is a different row; the credit side matches the credit row', $rm->invoke(null, $rows, ['bankId' => $B1, 'debit' => '99.99', 'credit' => '0.00'], []) === null && $rm->invoke(null, $rows, ['bankId' => $B1, 'debit' => '0.00', 'credit' => '100.00'], []) === 1);
    ok('matchBankRow: a used row, or another bank, never matches', $rm->invoke(null, $rows, ['bankId' => $B1, 'debit' => '100.00', 'credit' => '0.00'], [0 => true]) === null && $rm->invoke(null, $rows, ['bankId' => $B2, 'debit' => '100.00', 'credit' => '0.00'], []) === null);
    $clr = x('INSERT INTO tblbankpostings (bankId, transactionDate, debit, credit, transactionMethod, reference, cleared, clearedDare, transactionType, transactionId, deleted, congregationId) VALUES (?,?,?,?,?,?,1,?,98,951001,0,?)', [$B1, '2025-09-02', '100.00', '0.00', $method, 'zz t95 cleared', '2025-09-03', $T]);
    $created['postings'][] = $clr;
    ok('clearedBankProblem: keeping the cleared row at 100.00 (string or float) is allowed', Ledger::clearedBankProblem($pdo, $T, 98, 951001, ['bankId' => $B1, 'amount' => '100.00']) === null && Ledger::clearedBankProblem($pdo, $T, 98, 951001, ['bankId' => $B1, 'amount' => 100.0]) === null);
    ok('clearedBankProblem: 100.01 or 99.99 is a change of a reconciled row', Ledger::clearedBankProblem($pdo, $T, 98, 951001, ['bankId' => $B1, 'amount' => '100.01']) !== null && Ledger::clearedBankProblem($pdo, $T, 98, 951001, ['bankId' => $B1, 'amount' => '99.99']) !== null);
    ok('clearedBankProblem: another bank, or no keep (a void), is refused', Ledger::clearedBankProblem($pdo, $T, 98, 951001, ['bankId' => $B2, 'amount' => '100.00']) !== null && Ledger::clearedBankProblem($pdo, $T, 98, 951001) !== null);
    $col = q("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblbankpostings' AND COLUMN_NAME IN ('debit','credit')");
    ok('dev tblbankpostings debit and credit are DECIMAL(18,2) (script run in step 4.19)', count($col) === 2 && $col[0]['COLUMN_TYPE'] === 'decimal(18,2)' && $col[1]['COLUMN_TYPE'] === 'decimal(18,2)');

    echo "== DECIMAL script: guard, conversion, idempotence (on a copy)\n";
    $mysql = 'E:/xampp/mysql/bin/mysql.exe';
    $runSql = function ($table) use ($mysql) {
        $tmp = tempnam(sys_get_temp_dir(), 'bp') . '.sql';
        file_put_contents($tmp, "SET @bp_table = '$table';\n" . file_get_contents(__DIR__ . '/sql/bankpostings_decimal.sql'));
        $out = []; exec('"' . $mysql . '" -u ' . DB_USER . ' ' . DB_NAME . ' --table < "' . $tmp . '" 2>&1', $out, $code); @unlink($tmp);
        return [$code, implode("\n", $out)];
    };
    $pdo->exec('DROP TABLE IF EXISTS zz_t95_bp');
    $pdo->exec('CREATE TABLE zz_t95_bp (ID INT AUTO_INCREMENT PRIMARY KEY, bankId INT NOT NULL, transactionDate DATE NOT NULL, debit FLOAT NOT NULL, credit FLOAT NOT NULL, cleared TINYINT NOT NULL DEFAULT 0, deleted TINYINT NOT NULL DEFAULT 0)');
    $ins = $pdo->prepare('INSERT INTO zz_t95_bp (bankId, transactionDate, debit, credit) VALUES (?,?,?,?)');
    foreach ([[1, '2025-01-01', 100.5, 0], [1, '2025-01-02', 0, 20.25], [2, '2025-01-03', 1500000, 0], [2, '2025-01-04', 0.1, 0], [2, '2025-01-05', 0, 0.3]] as $r2) { $ins->execute($r2); }
    $pdo->exec('INSERT INTO zz_t95_bp (bankId, transactionDate, debit, credit) VALUES (3, "2025-01-06", 1234567.89, 0)');   // a FLOAT cannot hold this to the cent
    [$code, $out] = $runSql('zz_t95_bp');
    ok('guard: a FLOAT amount that has lost its cents stops the script (non-zero exit), lists the row and names the problem', $code !== 0 && strpos($out, '2025-01-06') !== false && strpos($out, 'not exact cents') !== false, $out);
    $type = q("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zz_t95_bp' AND COLUMN_NAME = 'debit'")[0]['COLUMN_TYPE'];
    ok('guard: nothing was changed (still float)', $type === 'float', $type);
    $pdo->exec('DELETE FROM zz_t95_bp WHERE bankId = 3');
    [$code, $out] = $runSql('zz_t95_bp');
    ok('conversion succeeds with exact rows: before and after row counts and totals printed', $code === 0 && strpos($out, 'before') !== false && strpos($out, 'after') !== false && strpos($out, 'totals before and after are equal') !== false, $out);
    $cols = q("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zz_t95_bp' AND COLUMN_NAME IN ('debit','credit') ORDER BY COLUMN_NAME");
    ok('both columns are decimal(18,2) NOT NULL', count($cols) === 2 && $cols[0]['COLUMN_TYPE'] === 'decimal(18,2)' && $cols[1]['COLUMN_TYPE'] === 'decimal(18,2)' && $cols[0]['IS_NULLABLE'] === 'NO' && $cols[1]['IS_NULLABLE'] === 'NO');
    $tot = q('SELECT SUM(debit) d, SUM(credit) c, COUNT(*) n FROM zz_t95_bp')[0];
    ok('totals equal the original amounts to the cent (1,500,100.60 debit, 20.55 credit; 5 rows)', $tot['d'] === '1500100.60' && $tot['c'] === '20.55' && (int) $tot['n'] === 5, json_encode($tot));
    ok('the cent values are exact: 0.1 + 0.3 style floats come out as 0.10 and 0.30', q('SELECT debit FROM zz_t95_bp WHERE ID = 4')[0]['debit'] === '0.10' && q('SELECT credit FROM zz_t95_bp WHERE ID = 5')[0]['credit'] === '0.30');
    [$code, $out] = $runSql('zz_t95_bp');
    ok('second run: "already DECIMAL", exit 0, no change', $code === 0 && strpos($out, 'already DECIMAL') !== false, $out);
    [$code, $out] = $runSql('zz_t95_missing');
    ok('a missing table is a clear error, not a silent success', $code !== 0 && strpos($out, 'not found') !== false, $out);
    [$code, $out] = $runSql('bad name; drop table x');
    ok('a table name with SQL in it is refused', $code !== 0 && strpos($out, 'invalid table name') !== false, $out);
    $pdo->exec('DROP TABLE IF EXISTS zz_t95_bp');

    echo "== decision 151: the trial balance line is called Prior years' surplus b/f\n";
    $tb = FinancialReport::trialBalance($pdo, [11], '2026-10-24');
    $names = array_column(array_filter($tb['rows'], function ($r) { return $r['id'] === 'bf'; }), 'name');
    ok("the computed line is named \"Prior years' surplus b/f\"", $names === ["Prior years' surplus b/f"], json_encode($names));
    $ch = FinancialReport::children($pdo, [11], 'bf', ['kind' => 'tb', 'asOf' => '2026-10-24']);
    ok('no screen still calls it Accumulated fund b/f on the trial balance side', strpos(json_encode($tb), 'Accumulated fund b/f') === false && strpos(json_encode($ch), 'Accumulated fund b/f') === false);
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    $pdo->exec('DROP TABLE IF EXISTS zz_t95_bp');
    foreach ($created['postings'] as $id) { $pdo->prepare('DELETE FROM tblbankpostings WHERE ID = ?')->execute([$id]); }
    $pdo->prepare("DELETE FROM tblbankpostings WHERE reference LIKE 'zz t95%'")->execute();
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    $pdo->prepare("DELETE FROM journal_entries WHERE reference LIKE 'zz t95%'")->execute();
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz t95%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t95%'")[0]['n']
          + (int) q("SELECT COUNT(*) n FROM tblbankpostings WHERE reference LIKE 'zz t95%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM tblcongregation WHERE CongregationName LIKE 'zz t95%'")[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left fixture rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

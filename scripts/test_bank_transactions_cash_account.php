<?php
// 8.3b functional run: bank transactions with a user-chosen cash account (cong 11); cleans up after itself.
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Banktransaction.php';

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;
$_SESSION['userId'] = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();

$tables = ['journal_entries' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tblbanktransactions' => 'ID', 'tblpettycash' => 'ID', 'tbllogs' => 'ID', 'tblbanktransactions_subaccounts' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function lines($id) {
    $r = q('SELECT l.accountId,l.dc FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId
            WHERE e.congregationId=11 AND e.transactionTypeId=13 AND e.transactionId=? AND e.deleted=0', [$id]);
    return array_column($r, 'dc', 'accountId');
}
function brows($id) { return q('SELECT * FROM tblbankpostings WHERE transactionType=13 AND transactionId=? AND deleted=0 ORDER BY ID', [$id]); }
function src($id) { return q('SELECT * FROM tblbanktransactions WHERE ID=?', [$id])[0]; }

try {
    $bt = new Banktransaction;
    $b1 = (int)$bt->GetBanks()[0]->ID;
    $cash = array_map(function ($a) { return (int)$a->ID; }, $bt->GetCashAccounts());
    echo "bank $b1; cash-type accounts: " . implode(',', $cash) . "\n";
    chk('cash dropdown has 34 and no bank', in_array(34, $cash, true) && !in_array($b1, $cash, true));
    $other = null;
    foreach ($cash as $c) { if (!in_array($c, [34, 98, 167], true)) { $other = $c; break; } }
    chk('another cash-type account available', $other !== null);
    $mk = function ($o) use ($b1) {
        return $o + ['id' => null, 'isedit' => false, 'date' => '2026-01-15', 'bank' => $b1, 'type' => 1, 'transfer' => null, 'cashaccount' => null,
                     'amount' => '500', 'reference' => 'T83B', 'description' => 'Test', 'deposittosubs' => false, 'subaccounts' => null,
                     'issubmain' => false, 'issubtransfer' => false, 'issubtxn' => false];
    };
    $newid = function () { return (int)q('SELECT MAX(ID) m FROM tblbanktransactions')[0]['m']; };
    $count = function () { return (int)q('SELECT COUNT(*) c FROM tblbanktransactions')[0]['c']; };

    foreach (['', '0', '-5', '1.234', 'abc', '100.50', '.5'] as $v) { echo "   amountError('$v') = " . json_encode(Ledger::amountError($v)) . "\n"; }
    chk('amountError rules', Ledger::amountError('100.50') === null && Ledger::amountError('1.234') !== null && Ledger::amountError('0') !== null && Ledger::amountError('-5') !== null);

    // ---- deposits
    $r = $bt->CreateUpdate($mk(['type' => 1, 'cashaccount' => 34, 'amount' => '100.50'])); chk('deposit to 34 saved', $r['success'], json_encode($r));
    $d = $newid();
    chk('deposit: Dr bank / Cr 34', lines($d) == [$b1 => 'DEBIT', 34 => 'CREDIT'], json_encode(lines($d)));
    chk('deposit: CashAccountId 34 and Amount 100.50 stored', src($d)['CashAccountId'] == 34 && src($d)['Amount'] == '100.50', json_encode(src($d)));
    $br = brows($d);
    chk('deposit: bank row debit 100.5, method 1', count($br) == 1 && abs($br[0]['debit'] - 100.5) < 0.001 && $br[0]['transactionMethod'] == 1);
    chk('deposit: ledger line amount 100.50', q('SELECT l.amount FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=13 AND e.transactionId=? AND e.congregationId=11 AND e.deleted=0 LIMIT 1', [$d])[0]['amount'] == '100.50');

    $r = $bt->CreateUpdate($mk(['type' => 1, 'cashaccount' => $other])); chk('deposit to other cash account saved', $r['success'], json_encode($r));
    $d2 = $newid(); chk('deposit 2: Dr bank / Cr other account', lines($d2) == [$b1 => 'DEBIT', $other => 'CREDIT'], json_encode(lines($d2)));

    // edit ONLY the crediting account of a cleared deposit
    $row = brows($d2)[0];
    q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE ID=?', ['2026-01-20', $row['ID']]);
    $r = $bt->CreateUpdate($mk(['id' => $d2, 'isedit' => true, 'type' => 1, 'cashaccount' => 34])); chk('edit crediting account only', $r['success'], json_encode($r));
    $after = brows($d2);
    chk('same bank row kept, still cleared', count($after) == 1 && $after[0]['ID'] == $row['ID'] && $after[0]['cleared'] == 1 && $after[0]['clearedDare'] == '2026-01-20', json_encode($after));
    chk('entry non-bank line now 34', lines($d2) == [$b1 => 'DEBIT', 34 => 'CREDIT'] && src($d2)['CashAccountId'] == 34);
    // 8.9 follow-up: a cleared bank row can no longer be changed (bank / amount) or voided; the test refuses it, then un-clears the row and goes on
    $r = $bt->Delete($d2); chk('delete of a cleared deposit refused', !$r['success'] && stripos(json_encode($r), 'cleared') !== false && count(brows($d2)) == 1, json_encode($r));
    q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE ID=?', [$row['ID']]);

    // ---- withdrawal
    $r = $bt->CreateUpdate($mk(['type' => 2, 'cashaccount' => $other])); chk('withdrawal saved', $r['success'], json_encode($r));
    $w = $newid(); $br = brows($w);
    chk('withdrawal: Dr chosen account / Cr bank', lines($w) == [$other => 'DEBIT', $b1 => 'CREDIT'], json_encode(lines($w)));
    chk('withdrawal: credit row, method 2', count($br) == 1 && $br[0]['credit'] == 500 && $br[0]['transactionMethod'] == 2, json_encode($br));
    // method changes on a normal edit (existing rows are not changed otherwise): old-style row with method 1 -> edit narration -> 2
    q('UPDATE tblbankpostings SET transactionMethod=1 WHERE ID=?', [$br[0]['ID']]);
    $r = $bt->CreateUpdate($mk(['id' => $w, 'isedit' => true, 'type' => 2, 'cashaccount' => $other, 'description' => 'Edited']));
    $br = brows($w); chk('edit of an old method-1 withdrawal row sets method 2, same row', $r['success'] && count($br) == 1 && $br[0]['transactionMethod'] == 2);

    // ---- refusals
    $n = $count(); $e0 = (int)q('SELECT COUNT(*) c FROM journal_entries')[0]['c'];
    foreach ([[1, null, 'deposit without account'], [2, null, 'withdrawal without account'], [1, $b1, 'bank as cash account'], [1, 33, 'reporting account'], [2, 987654, 'unknown account'], [1, 50, 'asset outside cash and cash equivalents']] as $c) {
        $r = $bt->CreateUpdate($mk(['type' => $c[0], 'cashaccount' => $c[1]]));
        echo "   [{$c[2]}] " . json_encode($r['errors']) . "\n";
        chk("refused: {$c[2]}", !$r['success']);
    }
    chk('refusals saved nothing', $count() == $n && (int)q('SELECT COUNT(*) c FROM journal_entries')[0]['c'] == $e0);

    // ---- transfer ignores a posted cash account
    $r = $bt->CreateUpdate($mk(['type' => 5, 'transfer' => '98', 'cashaccount' => 34])); chk('transfer saved', $r['success'], json_encode($r));
    $t = $newid(); $br = brows($t);
    chk('transfer: CashAccountId NULL, Dr 98 / Cr bank, method 1', src($t)['CashAccountId'] === null && lines($t) == [98 => 'DEBIT', $b1 => 'CREDIT'] && count($br) == 1 && $br[0]['transactionMethod'] == 1, json_encode(src($t)));
    // switching a deposit to a transfer clears the account
    $r = $bt->CreateUpdate($mk(['id' => $d, 'isedit' => true, 'type' => 5, 'transfer' => '167', 'cashaccount' => 34]));
    chk('deposit edited into transfer: CashAccountId NULL, Dr 167', $r['success'] && src($d)['CashAccountId'] === null && lines($d) == [167 => 'DEBIT', $b1 => 'CREDIT'], json_encode($r));

    // ---- sub-account deposit saves with the reference
    $sub = q('SELECT ID FROM tblbanksubaccounts WHERE BankId=? AND Deleted=0 LIMIT 1', [$b1]);
    if ($sub) {
        $r = $bt->CreateUpdate($mk(['type' => 1, 'cashaccount' => 34, 'amount' => '200', 'deposittosubs' => true, 'reference' => 'SUBREF',
                                    'subaccounts' => [(object)['accountid' => $sub[0]['ID'], 'amount' => '200']]]));
        chk('sub-account deposit saved', $r['success'], json_encode($r));
        $s = $newid();
        $rows = q('SELECT Reference, Narration, Amount, TransactionType FROM tblbanktransactions_subaccounts WHERE TransactionId=?', [$s]);
        chk('sub-account row has reference SUBREF', count($rows) == 1 && $rows[0]['Reference'] === 'SUBREF' && $rows[0]['Amount'] == '200.00', json_encode($rows));
        chk('sub-account deposit ledger Dr bank / Cr 34', lines($s) == [$b1 => 'DEBIT', 34 => 'CREDIT']);
        $sub2 = q('SELECT ID FROM tblbanksubaccounts WHERE BankId=? AND Deleted=0 AND ID<>? LIMIT 1', [$b1, $sub[0]['ID']]);
        $r = $bt->CreateUpdate($mk(['id' => $s, 'isedit' => true, 'type' => 1, 'cashaccount' => 34, 'amount' => '300', 'date' => '2026-01-16', 'deposittosubs' => true, 'reference' => 'SUBREF2',
                                    'subaccounts' => [(object)['accountid' => $sub[0]['ID'], 'amount' => '100'], (object)['accountid' => $sub2[0]['ID'], 'amount' => '200']]]));
        chk('sub-account deposit edited', $r['success'], json_encode($r));
        $rows = q('SELECT TransactionDate, TransactionType, Narration, Reference, Amount, CongregationId FROM tblbanktransactions_subaccounts WHERE TransactionId=? ORDER BY ID', [$s]);
        chk('edited sub-account rows: 2 rows with date, type 13, narration, reference, congregation', count($rows) == 2
            && $rows[0]['TransactionDate'] == '2026-01-16' && $rows[0]['TransactionType'] == 13 && $rows[0]['Narration'] === 'sub-account deposit'
            && $rows[0]['Reference'] === 'SUBREF2' && $rows[1]['Reference'] === 'SUBREF2' && $rows[0]['CongregationId'] == 11
            && $rows[0]['Amount'] == '100.00' && $rows[1]['Amount'] == '200.00', json_encode($rows));
        $save = q('SELECT COUNT(*) c FROM tblbanktransactions_subaccounts WHERE TransactionId=?', [$s])[0]['c'];
        chk('edit replaced (not duplicated) the sub-account rows', $save == 2);
    } else { chk('sub-account available for bank', false); }

    // ---- server-side checks of the sub-account branches
    $sub = q('SELECT ID FROM tblbanksubaccounts WHERE BankId=? AND Deleted=0 ORDER BY ID LIMIT 2', [$b1]);
    $foreignSub = q('SELECT s.ID FROM tblbanksubaccounts s JOIN tblaccounttypes a ON a.ID=s.BankId WHERE s.Deleted=0 AND a.congregationId<>11 LIMIT 1');
    $n = $count(); $e0 = (int)q('SELECT COUNT(*) c FROM journal_entries')[0]['c'];
    $split = function ($rows, $amount = '300') use ($mk, $bt) {
        return $bt->CreateUpdate($mk(['type' => 1, 'cashaccount' => 34, 'amount' => $amount, 'deposittosubs' => true, 'subaccounts' => $rows]));
    };
    $r = $split([(object)['accountid' => $sub[0]['ID'], 'amount' => '100'], (object)['accountid' => $sub[1]['ID'], 'amount' => '150']]);
    echo '   [split that does not add up] ' . json_encode($r['errors']) . "\n"; chk('refused: split not adding up to the deposit', !$r['success']);
    $r = $split([(object)['accountid' => $sub[0]['ID'], 'amount' => '100'], (object)['accountid' => $sub[0]['ID'], 'amount' => '200']]);
    echo '   [duplicate sub account] ' . json_encode($r['errors']) . "\n"; chk('refused: duplicate sub account', !$r['success']);
    $r = $split([(object)['accountid' => 987654, 'amount' => '300']]);
    echo '   [unknown sub account] ' . json_encode($r['errors']) . "\n"; chk('refused: unknown sub account', !$r['success']);
    $r = $split([(object)['accountid' => $sub[0]['ID'], 'amount' => '300.123']]);
    echo '   [3 decimals] ' . json_encode($r['errors']) . "\n"; chk('refused: sub account amount with 3 decimals', !$r['success']);
    $r = $split([(object)['accountid' => $sub[0]['ID'], 'amount' => '0'], (object)['accountid' => $sub[1]['ID'], 'amount' => '']]);
    echo '   [all blank] ' . json_encode($r['errors']) . "\n"; chk('refused: no sub account amounts', !$r['success']);
    $r = $bt->CreateUpdate($mk(['type' => 5, 'transfer' => 4, 'bank' => 987654, 'issubtxn' => true, 'issubmain' => true, 'issubtransfer' => true]));
    echo '   [unknown sub-account transfer] ' . json_encode($r['errors']) . "\n"; chk('refused: sub-account transfer with unknown sub account', !$r['success']);
    if ($foreignSub) {
        $r = $bt->CreateUpdate($mk(['type' => 6, 'transfer' => $foreignSub[0]['ID'], 'bank' => $sub[0]['ID'], 'issubtxn' => true, 'issubmain' => true, 'issubtransfer' => true]));
        echo '   [other congregation sub account] ' . json_encode($r['errors']) . "\n"; chk('refused: sub-account of another congregation', !$r['success']);
    }
    $r = $bt->CreateUpdate($mk(['type' => 6, 'transfer' => $sub[1]['ID'], 'bank' => $sub[0]['ID'], 'issubtxn' => true, 'issubmain' => true, 'issubtransfer' => true]));
    chk('sub-to-sub transfer of the same congregation saved, no ledger entry', $r['success'] && !lines($newid()), json_encode($r));
    chk('refused sub-account saves wrote nothing (except the one valid transfer)', $count() == $n + 1 && (int)q('SELECT COUNT(*) c FROM journal_entries')[0]['c'] == $e0);
    $r = $split([(object)['accountid' => $sub[0]['ID'], 'amount' => '100'], (object)['accountid' => $sub[1]['ID'], 'amount' => '200'], (object)['accountid' => $sub[1]['ID'] + 0, 'amount' => '']]);
    chk('blank sub account rows are ignored when the rest adds up', $r['success'], json_encode($r));
    $sid = $newid();
    chk('only the 2 non-blank sub-account rows were stored', (int)q('SELECT COUNT(*) c FROM tblbanktransactions_subaccounts WHERE TransactionId=?', [$sid])[0]['c'] == 2);

    // ---- audit log
    chk('bank transactions write tbllogs rows', (int)q('SELECT COUNT(*) c FROM tbllogs WHERE ID > ? AND Activity LIKE "%bank transaction%"', [$max['tbllogs']])[0]['c'] >= 3);

    // ---- deletes
    foreach (['d' => $d, 'd2' => $d2, 'w' => $w, 't' => $t] as $k => $id) {
        $r = $bt->Delete($id); chk("delete $k", $r['success'] && !brows($id) && !lines($id));
    }
} catch (Throwable $e) {
    $fail++; echo "EXCEPTION: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n";
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId > ?')->execute([$max['journal_entries']]);
foreach ($tables as $t => $k) { $pdo->prepare("DELETE FROM $t WHERE $k > ?")->execute([$max[$t]]); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k > {$max[$t]}")->fetchColumn(); }
echo "\nPASSED $pass FAILED $fail ; leftover rows after cleanup: $left\n";

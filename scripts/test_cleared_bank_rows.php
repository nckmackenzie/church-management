<?php
// Cleared (reconciled) bank rows: every migrated path that voids, deletes or edits bank postings refuses to void a cleared
// row and to change its bank or amount (Ledger::clearedBankProblem); date / reference / description edits still work.
// Types 1 (receipts), 2 (expenses), 5 (journals), 9 (bank postings, delete only), 10 (petty cash receipts), 13 (bank
// transactions, incl. a bank-to-bank transfer). Congregation 11, dev only; cleans up after itself.
//   E:\xampp\php\php.exe scripts\test_cleared_bank_rows.php
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
foreach (['Contribution', 'Cashreceipt', 'Expense', 'Journal', 'Banktransaction', 'Bankposting'] as $m) { require_once "models/$m.php"; }

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;
$_SESSION['isParish'] = 0;
$_SESSION['userType'] = 1;
$_SESSION['userId'] = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();

$tables = ['journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tblcontributions_header' => 'ID',
           'tblcontributions_details' => 'ID', 'tblmmf' => 'ID', 'tblbanktransactions_subaccounts' => 'ID', 'tblpettycash' => 'ID',
           'tbllogs' => 'ID', 'tblbanktransactions' => 'ID', 'tblexpenses' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$seqSnapshot = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$jseq = $pdo->query('SELECT * FROM journal_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$yearSnapshot = $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC);

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function brows($type, $id) { return q('SELECT * FROM tblbankpostings WHERE transactionType=? AND transactionId=? AND deleted=0 ORDER BY ID', [$type, $id]); }
function setCleared($type, $id, $on) { q('UPDATE tblbankpostings SET cleared=?, clearedDare=? WHERE transactionType=? AND transactionId=? AND deleted=0', [$on ? 1 : 0, $on ? '2026-01-20' : null, $type, $id]); }
function activeEntries($type, $id) { return (int)q('SELECT COUNT(*) c FROM journal_entries WHERE congregationId=11 AND transactionTypeId=? AND transactionId=? AND deleted=0', [$type, $id])[0]['c']; }
function msgs($r) { return implode(' | ', $r['errors'] ?? []); }
function cleared($r) { return isset($r['success']) && $r['success'] === false && stripos(msgs($r), 'cleared') !== false; }

try {
    $OPEN = '2026-01-15';
    $bank = 105; $bank2 = 195;
    $member = (int)q('SELECT ID FROM tblmember WHERE congregationId=11 LIMIT 1')[0]['ID'];

    // ================================================================ type 1 receipts
    $ct = new Contribution;
    $mk = function ($o = []) use ($bank, $member, $OPEN) {
        return $o + ['id' => '', 'isedit' => false, 'date' => $OPEN, 'paymethod' => '2', 'bank' => $bank, 'cashaccount' => '',
                     'reference' => 'T89CLR', 'description' => 'Test receipt', 'accountsid' => [93], 'accountsname' => ['offerings'],
                     'amounts' => ['100.50'], 'categoriesid' => [1], 'categoriesname' => ['MEMBER'], 'contributorsid' => [$member],
                     'contributorsname' => ['X'], 'subaccount' => ['']];
    };
    $r = $ct->create($mk()); chk('receipt: bank receipt saved', $r['success'], msgs($r));
    $h = (int)$r['id'];
    setCleared(1, $h, true);
    $r = $ct->create($mk(['id' => $h, 'isedit' => true, 'amounts' => ['200']]));
    chk('receipt: edit of the total refused (bank row cleared)', cleared($r) && abs(brows(1, $h)[0]['debit'] - 100.5) < 0.001, msgs($r));
    $r = $ct->create($mk(['id' => $h, 'isedit' => true, 'paymethod' => '1', 'bank' => '', 'cashaccount' => 34]));
    chk('receipt: switching a cleared bank receipt to cash refused', cleared($r) && count(brows(1, $h)) === 1, msgs($r));
    $r = $ct->create($mk(['id' => $h, 'isedit' => true, 'bank' => $bank2]));
    chk('receipt: moving it to another bank refused', cleared($r) && (int)brows(1, $h)[0]['bankId'] === $bank, msgs($r));
    $r = $ct->create($mk(['id' => $h, 'isedit' => true, 'description' => 'Edited description', 'reference' => 'T89CLR2']));
    chk('receipt: description / reference edit ok, bank row kept cleared', $r['success'] && count(brows(1, $h)) === 1 && (int)brows(1, $h)[0]['cleared'] === 1, msgs($r));
    $r = $ct->delete(['id' => $h]);
    chk('receipt: delete refused (bank row cleared), still active', cleared($r) && activeEntries(1, $h) === 1 && count(brows(1, $h)) === 1, msgs($r));
    setCleared(1, $h, false);
    $r = $ct->create($mk(['id' => $h, 'isedit' => true, 'amounts' => ['200']]));
    chk('receipt: amount edit ok once the row is not cleared', $r['success'], msgs($r));
    $r = $ct->delete(['id' => $h]);
    chk('receipt: delete ok once the row is not cleared', $r['success'] && activeEntries(1, $h) === 0 && count(brows(1, $h)) === 0, msgs($r));

    // ================================================================ type 10 petty cash receipts
    $cr = new Cashreceipt;
    $pm = function ($o = []) use ($bank, $OPEN) { return $o + ['id' => '', 'isedit' => false, 'date' => $OPEN, 'bank' => $bank, 'pettycashaccount' => 98, 'amount' => '50', 'reference' => 'T89PC', 'description' => 'pc']; };
    $r = $cr->CreateUpdate($pm()); chk('petty cash receipt saved', $r['success'], msgs($r));
    $p = (int)$r['id'];
    setCleared(10, $p, true);
    $r = $cr->CreateUpdate($pm(['id' => $p, 'isedit' => true, 'amount' => '60']));
    chk('petty cash receipt: amount edit refused (cleared)', cleared($r) && abs(brows(10, $p)[0]['credit'] - 50) < 0.001, msgs($r));
    $r = $cr->CreateUpdate($pm(['id' => $p, 'isedit' => true, 'bank' => $bank2]));
    chk('petty cash receipt: bank change refused (cleared)', cleared($r) && (int)brows(10, $p)[0]['bankId'] === $bank, msgs($r));
    $r = $cr->CreateUpdate($pm(['id' => $p, 'isedit' => true, 'description' => 'edited', 'pettycashaccount' => 194]));
    chk('petty cash receipt: other fields edit ok, cleared row kept', $r['success'] && (int)brows(10, $p)[0]['cleared'] === 1, msgs($r));
    $r = $cr->Delete($p);
    chk('petty cash receipt: delete refused (cleared)', cleared($r) && activeEntries(10, $p) === 1 && count(brows(10, $p)) === 1, msgs($r));
    setCleared(10, $p, false);
    $r = $cr->Delete($p);
    chk('petty cash receipt: delete ok once not cleared', $r['success'] && activeEntries(10, $p) === 0, msgs($r));

    // ================================================================ type 2 expenses
    $E = new Expense;
    $acct = (int)q('SELECT ID FROM tblaccounttypes WHERE ID IN (' . implode(',', array_map(function ($a) { return (int)$a->ID; }, $E->GetExpenseAccounts(1))) . ') AND accountTypeId=2 ORDER BY ID LIMIT 1')[0]['ID'];
    $em = function ($o = []) use ($acct, $OPEN, $bank) {
        return $o + ['date' => $OPEN, 'expensetype' => 1, 'account' => $acct, 'costcentre' => 0, 'paymethod' => 2, 'paidfrom' => $bank, 'reqid' => '',
                     'amount' => '100.50', 'reference' => 'T89EX', 'description' => 'Test expense', 'hasattachment' => 0, 'filename' => ''];
    };
    $r = $E->create($em()); chk('expense (bank) created', $r['success'], msgs($r));
    $x = (int)$r['id'];
    $r = $E->approve($x); chk('expense approved (bank row written)', $r['success'] && count(brows(2, $x)) === 1, msgs($r));
    setCleared(2, $x, true);
    $r = $E->update($x, $em(['amount' => '120']));
    chk('expense: amount edit refused (cleared)', cleared($r) && abs(brows(2, $x)[0]['credit'] - 100.5) < 0.001, msgs($r));
    $r = $E->update($x, $em(['paidfrom' => $bank2]));
    chk('expense: paid-from bank change refused (cleared)', cleared($r) && (int)brows(2, $x)[0]['bankId'] === $bank, msgs($r));
    $r = $E->update($x, $em(['paymethod' => 1, 'paidfrom' => 98]));
    chk('expense: switch to cash refused (cleared)', cleared($r) && count(brows(2, $x)) === 1, msgs($r));
    $r = $E->update($x, $em(['description' => 'Edited expense', 'reference' => 'T89EX2']));
    chk('expense: description / reference edit ok, cleared row kept', $r['success'] && (int)brows(2, $x)[0]['cleared'] === 1, msgs($r));
    $r = $E->unapprove($x);
    chk('expense: unapprove refused (cleared), still approved', cleared($r) && (int)q('SELECT status FROM tblexpenses WHERE ID=?', [$x])[0]['status'] === 1 && activeEntries(2, $x) === 1, msgs($r));
    $r = $E->delete($x);
    chk('expense: delete of an approved expense refused (cleared)', cleared($r) && (int)q('SELECT deleted FROM tblexpenses WHERE ID=?', [$x])[0]['deleted'] === 0, msgs($r));
    setCleared(2, $x, false);
    $r = $E->unapprove($x);
    chk('expense: unapprove ok once not cleared', $r['success'] && activeEntries(2, $x) === 0 && count(brows(2, $x)) === 0, msgs($r));
    $r = $E->delete($x); chk('expense: draft delete ok', $r['success'], msgs($r));

    // ================================================================ type 5 journals
    $jm = new Journal;
    $income = 93;
    $jd = function ($o = []) use ($bank, $income, $OPEN) {
        return $o + ['isedit' => false, 'journalno' => null, 'date' => $OPEN, 'entries' => [
            (object)['accountid' => $bank, 'debit' => '100', 'credit' => '', 'desc' => 'cleared test'],
            (object)['accountid' => $income, 'debit' => '', 'credit' => '100', 'desc' => 'cleared test']]];
    };
    $r = $jm->createupdate($jd()); chk('journal with a bank line saved', $r['success'] && count(brows(5, (int)$r['journalno'])) === 1, msgs($r));
    $j = (int)$r['journalno'];
    setCleared(5, $j, true);
    $r = $jm->createupdate($jd(['isedit' => true, 'journalno' => $j, 'entries' => [
        (object)['accountid' => $bank, 'debit' => '150', 'credit' => '', 'desc' => 'x'], (object)['accountid' => $income, 'debit' => '', 'credit' => '150', 'desc' => 'x']]]));
    chk('journal: amount edit of the bank line refused (cleared)', cleared($r) && abs(brows(5, $j)[0]['debit'] - 100) < 0.001, msgs($r));
    $r = $jm->createupdate($jd(['isedit' => true, 'journalno' => $j, 'entries' => [
        (object)['accountid' => $bank2, 'debit' => '100', 'credit' => '', 'desc' => 'x'], (object)['accountid' => $income, 'debit' => '', 'credit' => '100', 'desc' => 'x']]]));
    chk('journal: moving the line to another bank refused (cleared)', cleared($r) && (int)brows(5, $j)[0]['bankId'] === $bank, msgs($r));
    $r = $jm->createupdate($jd(['isedit' => true, 'journalno' => $j, 'entries' => [
        (object)['accountid' => $bank, 'debit' => '100', 'credit' => '', 'desc' => 'edited'], (object)['accountid' => 94, 'debit' => '', 'credit' => '100', 'desc' => 'edited']]]));
    chk('journal: other account / memo edit ok, cleared row kept', $r['success'] && (int)brows(5, $j)[0]['cleared'] === 1, msgs($r));
    $r = $jm->delete($j);
    chk('journal: delete refused (cleared)', cleared($r) && activeEntries(5, $j) === 1 && count(brows(5, $j)) === 1, msgs($r));
    setCleared(5, $j, false);
    $r = $jm->delete($j); chk('journal: delete ok once not cleared', $r['success'] && activeEntries(5, $j) === 0, msgs($r));

    // ================================================================ type 13 bank transactions
    $bt = new Banktransaction;
    $bm = function ($o = []) use ($bank, $OPEN) {
        return $o + ['id' => null, 'isedit' => false, 'date' => $OPEN, 'bank' => $bank, 'type' => 1, 'transfer' => null, 'cashaccount' => 34, 'amount' => 500,
                     'reference' => 'T89BT', 'description' => 'Test', 'deposittosubs' => false, 'subaccounts' => null,
                     'issubmain' => false, 'issubtransfer' => false, 'issubtxn' => false];
    };
    $newbt = function () { return (int)q('SELECT MAX(ID) m FROM tblbanktransactions')[0]['m']; };
    $r = $bt->CreateUpdate($bm()); chk('bank transaction (deposit) saved', $r['success'], msgs($r));
    $d = $newbt();
    setCleared(13, $d, true);
    $r = $bt->CreateUpdate($bm(['id' => $d, 'isedit' => true, 'amount' => 700]));
    chk('bank transaction: amount edit refused (cleared)', cleared($r) && abs(brows(13, $d)[0]['debit'] - 500) < 0.001, msgs($r));
    $r = $bt->CreateUpdate($bm(['id' => $d, 'isedit' => true, 'bank' => $bank2]));
    chk('bank transaction: bank change refused (cleared)', cleared($r) && (int)brows(13, $d)[0]['bankId'] === $bank, msgs($r));
    $r = $bt->CreateUpdate($bm(['id' => $d, 'isedit' => true, 'description' => 'Edited', 'reference' => 'T89BT2']));
    chk('bank transaction: description / reference edit ok, cleared row kept', $r['success'] && (int)brows(13, $d)[0]['cleared'] === 1, msgs($r));
    $r = $bt->Delete($d);
    chk('bank transaction: delete refused (cleared)', cleared($r) && activeEntries(13, $d) === 1 && count(brows(13, $d)) === 1, msgs($r));
    setCleared(13, $d, false);
    $r = $bt->Delete($d); chk('bank transaction: delete ok once not cleared', $r['success'] && activeEntries(13, $d) === 0, msgs($r));

    // tblpettycash rows are scoped by congregation: a row of another congregation with the same TransactionType / TransactionId is left alone
    $r = $bt->CreateUpdate($bm(['reference' => 'T89SC'])); chk('scoping: deposit saved', $r['success'], msgs($r));
    $sc = $newbt();
    q("INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,PettyCashAccountId,Reference,Narration,TransactionType,TransactionId,CongregationId) VALUES (NULL,?,10,1,?,98,'x','t89 foreign',13,?,2)", [$OPEN, $bank, $sc]);
    $foreignPc = (int)$pdo->lastInsertId();
    q("INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,PettyCashAccountId,Reference,Narration,TransactionType,TransactionId,CongregationId) VALUES (NULL,?,10,1,?,98,'x','t89 own',13,?,11)", [$OPEN, $bank, $sc]);
    $ownPc = (int)$pdo->lastInsertId();
    $r = $bt->Delete($sc);
    chk('scoping: delete of the bank transaction voids the own petty cash row only', $r['success'] && (int)q('SELECT Deleted d FROM tblpettycash WHERE ID=?', [$ownPc])[0]['d'] === 1
        && (int)q('SELECT Deleted d FROM tblpettycash WHERE ID=?', [$foreignPc])[0]['d'] === 0, msgs($r));
    // bank -> bank transfer: two bank rows, both cleared; unchanged edit passes, a change of either refused
    $r = $bt->CreateUpdate($bm(['type' => 5, 'transfer' => $bank2, 'amount' => 300, 'reference' => 'T89TR'])); chk('bank-to-bank transfer saved', $r['success'] && count(brows(13, $newbt())) === 2, msgs($r));
    $t = $newbt();
    setCleared(13, $t, true);
    $r = $bt->CreateUpdate($bm(['id' => $t, 'isedit' => true, 'type' => 5, 'transfer' => $bank2, 'amount' => 300, 'reference' => 'T89TR', 'description' => 'edited transfer']));
    chk('transfer: unchanged banks / amount with both rows cleared: edit ok', $r['success'] && count(brows(13, $t)) === 2, msgs($r));
    $r = $bt->CreateUpdate($bm(['id' => $t, 'isedit' => true, 'type' => 5, 'transfer' => $bank2, 'amount' => 310, 'reference' => 'T89TR']));
    chk('transfer: amount change refused (both rows cleared)', cleared($r), msgs($r));
    $r = $bt->Delete($t);
    chk('transfer: delete refused (cleared)', cleared($r) && count(brows(13, $t)) === 2, msgs($r));
    setCleared(13, $t, false);
    $r = $bt->Delete($t); chk('transfer: delete ok once not cleared', $r['success'], msgs($r));

    // ================================================================ type 9 bank postings (delete only; edit un-clears by design, 8.3)
    $bp = new Bankposting;
    $contra = (int)$bp->getAccounts()[0]->ID;
    $r = $bp->create(['date' => $OPEN, 'bank' => $bank, 'type' => 1, 'amount' => '100.50', 'account' => $contra, 'reference' => 'T89BP', 'narration' => 'Test']);
    chk('bank posting saved', $r['success'], msgs($r));
    $b9 = (int)q('SELECT MAX(ID) m FROM tblbankpostings WHERE transactionType=9 AND congregationId=11')[0]['m'];
    q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE ID=?', ['2026-01-20', $b9]);
    $r = $bp->delete($b9);
    chk('bank posting: delete refused (cleared)', cleared($r) && (int)q('SELECT deleted FROM tblbankpostings WHERE ID=?', [$b9])[0]['deleted'] === 0 && activeEntries(9, $b9) === 1, msgs($r));
    q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE ID=?', [$b9]);
    $r = $bp->delete($b9); chk('bank posting: delete ok once not cleared', $r['success'] && activeEntries(9, $b9) === 0, msgs($r));
} catch (Throwable $e) {
    $fail++;
    echo 'FAIL exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
    $pdo->exec('DELETE FROM journal_lines WHERE journalEntryId > ' . $max['journal_entries'] . ' OR ID > ' . $max['journal_lines']);
    foreach ($tables as $t => $k) { if ($t !== 'journal_lines') { $pdo->exec("DELETE FROM $t WHERE $k > {$max[$t]}"); } }
    $pdo->exec('DELETE FROM receipt_number_seq'); $ins = $pdo->prepare('INSERT INTO receipt_number_seq (congregationId,kind,fiscalYearId,lastNo) VALUES (?,?,?,?)');
    foreach ($seqSnapshot as $s) { $ins->execute([$s['congregationId'], $s['kind'], $s['fiscalYearId'], $s['lastNo']]); }
    $pdo->exec('DELETE FROM journal_number_seq'); $ins = $pdo->prepare('INSERT INTO journal_number_seq (congregationId,lastJournalNo) VALUES (?,?)');
    foreach ($jseq as $s) { $ins->execute([$s['congregationId'], $s['lastJournalNo']]); }
    foreach ($tables as $t => $k) { chk("cleanup: $t back to max id {$max[$t]}", (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn() === $max[$t]); }
    chk('cleanup: fiscal years untouched', $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC) == $yearSnapshot);
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

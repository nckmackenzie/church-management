<?php
// 8.4d: the financial year lock as each migrated screen (model) shows it - journals, banks, bank postings, bank
// transactions, receipts, petty cash receipts (cong 11, dev only). Year 2023-2024 (ID 10) is closed / reopened
// during the run and always restored; every row the run creates is removed.
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
foreach (['Journal', 'Bank', 'Bankposting', 'Banktransaction', 'Contribution', 'Cashreceipt'] as $m) { require_once "models/$m.php"; }

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;
$_SESSION['isParish'] = 0;
$_SESSION['userId'] = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();

$tables = ['journal_entries' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tblbanktransactions' => 'ID', 'tblpettycash' => 'ID',
           'tbllogs' => 'ID', 'tblcontributions_header' => 'ID', 'tblcontributions_details' => 'ID', 'tblaccounttypes' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$jseq = $pdo->query('SELECT * FROM journal_number_seq WHERE congregationId = 11')->fetch(PDO::FETCH_ASSOC);
$seqSnapshot = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function setYear($closed) { global $pdo; $pdo->prepare('UPDATE tblfiscalyears SET closed=?, deleted=0 WHERE ID=10')->execute([$closed]); }
function entries($type, $id) { return (int)q('SELECT COUNT(*) c FROM journal_entries WHERE congregationId=11 AND transactionTypeId=? AND transactionId=? AND deleted=0', [$type, $id])[0]['c']; }
function entryDate($type, $id) { $r = q('SELECT entryDate FROM journal_entries WHERE congregationId=11 AND transactionTypeId=? AND transactionId=? AND deleted=0', [$type, $id]); return $r ? $r[0]['entryDate'] : null; }
function said($r, $needle = 'The financial year 2023-2024 is closed') { return !$r['success'] && strpos(implode(' | ', $r['errors']), $needle) !== false; }

$D = '2023-11-15';   // inside year 2023-2024
try {
    $bank = 105;
    $expense = (int)q('SELECT ID FROM tblaccounttypes WHERE ID=51')[0]['ID'];
    $income = 86;
    $member = (int)q('SELECT ID FROM tblmember WHERE congregationId=11 LIMIT 1')[0]['ID'];

    // ---------------- journals
    $jm = new Journal;
    $jdata = function ($o = []) use ($expense, $income, $D) {
        return $o + ['isedit' => false, 'journalno' => null, 'date' => $D, 'entries' => [
            (object)['accountid' => $expense, 'debit' => '100', 'credit' => '', 'desc' => 'year lock'],
            (object)['accountid' => $income, 'debit' => '', 'credit' => '100', 'desc' => 'year lock']]];
    };
    setYear(1);
    $before = (int)q('SELECT COUNT(*) c FROM journal_entries')[0]['c'];
    $r = $jm->createupdate($jdata()); echo '   [journal create] ' . json_encode($r['errors']) . "\n";
    chk('journal: new journal dated in a closed year refused with the year message', said($r) && (int)q('SELECT COUNT(*) c FROM journal_entries')[0]['c'] == $before);
    setYear(0);
    $r = $jm->createupdate($jdata()); chk('journal: same journal accepted while the year is open', $r['success'], json_encode($r));
    $jno = (int)$r['journalno'];
    setYear(1);
    $r = $jm->createupdate($jdata(['isedit' => true, 'journalno' => $jno])); echo '   [journal edit] ' . json_encode($r['errors']) . "\n";
    chk('journal: edit of a journal in a closed year refused', said($r));
    $r = $jm->delete($jno); echo '   [journal delete] ' . json_encode($r['errors']) . "\n";
    chk('journal: delete of a journal in a closed year refused, journal still active', said($r) && entries(5, $jno) == 1);
    setYear(0);
    $r = $jm->delete($jno); chk('journal: delete works once the year is open', $r['success'] && entries(5, $jno) == 0, json_encode($r));

    // ---------------- banks (opening balance)
    $bm = new Bank;
    $banksBefore = (int)q('SELECT COUNT(*) c FROM tblaccounttypes WHERE isBank=1')[0]['c'];
    setYear(1);
    $r = $bm->create(['bankname' => 'zz yearlock bank', 'account' => '1', 'openingbal' => '500', 'asof' => $D]); echo '   [bank opening balance] ' . json_encode($r['errors']) . "\n";
    chk('bank: opening balance dated in a closed year refused, the bank is not created', said($r) && (int)q('SELECT COUNT(*) c FROM tblaccounttypes WHERE isBank=1')[0]['c'] == $banksBefore);
    $r = $bm->create(['bankname' => 'zz yearlock bank', 'account' => '1', 'openingbal' => '0', 'asof' => $D]);
    chk('bank: a bank with no opening balance is still created in a closed year (nothing is posted)', $r['success'], json_encode($r));

    // ---------------- bank postings (type 9)
    $bp = new Bankposting;
    $contra = (int)$bp->getAccounts()[0]->ID;
    $pb = ['date' => $D, 'bank' => $bank, 'type' => 1, 'amount' => '100', 'account' => $contra, 'reference' => 'YL9', 'narration' => 'year lock'];
    $n = (int)q('SELECT COUNT(*) c FROM tblbankpostings WHERE transactionType=9')[0]['c'];
    $r = $bp->create($pb); echo '   [bank posting create] ' . json_encode($r['errors']) . "\n";
    chk('bank posting: new posting dated in a closed year refused, no row written', said($r) && (int)q('SELECT COUNT(*) c FROM tblbankpostings WHERE transactionType=9')[0]['c'] == $n);
    setYear(0);
    $r = $bp->create($pb); chk('bank posting: accepted while the year is open', $r['success'], json_encode($r));
    $pid = (int)q('SELECT MAX(ID) m FROM tblbankpostings WHERE transactionType=9')[0]['m'];
    setYear(1);
    $r = $bp->update(['id' => $pid, 'narration' => 'edited'] + $pb); echo '   [bank posting edit] ' . json_encode($r['errors']) . "\n";
    chk('bank posting: edit in a closed year refused, row unchanged', said($r) && q('SELECT narration n FROM tblbankpostings WHERE ID=?', [$pid])[0]['n'] === 'year lock');
    $r = $bp->delete($pid); echo '   [bank posting delete] ' . json_encode($r['errors']) . "\n";
    chk('bank posting: delete in a closed year refused, row and entry still active', said($r) && q('SELECT deleted d FROM tblbankpostings WHERE ID=?', [$pid])[0]['d'] == 0 && entries(9, $pid) == 1);
    setYear(0);
    $r = $bp->delete($pid); chk('bank posting: delete works once the year is open', $r['success'], json_encode($r));

    // ---------------- bank transactions (type 13)
    $bt = new Banktransaction;
    $tx = function ($o = []) use ($bank, $D) {
        return $o + ['id' => null, 'isedit' => false, 'date' => $D, 'bank' => $bank, 'type' => 1, 'transfer' => null, 'cashaccount' => 34, 'amount' => '250',
                     'reference' => 'YL13', 'description' => 'year lock', 'deposittosubs' => false, 'subaccounts' => null, 'issubmain' => false, 'issubtransfer' => false, 'issubtxn' => false];
    };
    setYear(1);
    $n = (int)q('SELECT COUNT(*) c FROM tblbanktransactions')[0]['c'];
    $r = $bt->CreateUpdate($tx()); echo '   [bank transaction create] ' . json_encode($r['errors']) . "\n";
    chk('bank transaction: new deposit dated in a closed year refused, nothing saved', said($r) && (int)q('SELECT COUNT(*) c FROM tblbanktransactions')[0]['c'] == $n);
    setYear(0);
    $r = $bt->CreateUpdate($tx()); chk('bank transaction: accepted while the year is open', $r['success'], json_encode($r));
    $tid = (int)q('SELECT MAX(ID) m FROM tblbanktransactions')[0]['m'];
    setYear(1);
    $r = $bt->CreateUpdate($tx(['id' => $tid, 'isedit' => true, 'amount' => '300'])); echo '   [bank transaction edit] ' . json_encode($r['errors']) . "\n";
    chk('bank transaction: edit in a closed year refused, amount unchanged', said($r) && q('SELECT Amount a FROM tblbanktransactions WHERE ID=?', [$tid])[0]['a'] == 250);
    $r = $bt->Delete($tid); echo '   [bank transaction delete] ' . json_encode($r['errors']) . "\n";
    chk('bank transaction: delete in a closed year refused, still active', said($r) && q('SELECT Deleted d FROM tblbanktransactions WHERE ID=?', [$tid])[0]['d'] == 0 && entries(13, $tid) == 1);
    setYear(0);
    $r = $bt->Delete($tid); chk('bank transaction: delete works once the year is open', $r['success'], json_encode($r));

    // ---------------- receipts (type 1)
    $cm = new Contribution;
    $rc = function ($o = []) use ($bank, $member, $D) {
        return $o + ['id' => '', 'isedit' => false, 'date' => $D, 'paymethod' => '2', 'bank' => $bank, 'cashaccount' => '', 'reference' => 'YL1',
                     'description' => 'year lock', 'accountsid' => [93], 'accountsname' => ['x'], 'amounts' => ['100'], 'categoriesid' => [1],
                     'categoriesname' => ['M'], 'contributorsid' => [$member], 'contributorsname' => ['X'], 'subaccount' => ['']];
    };
    setYear(1);
    $n = (int)q('SELECT COUNT(*) c FROM tblcontributions_header')[0]['c'];
    $r = $cm->create($rc()); echo '   [receipt create] ' . json_encode($r['errors']) . "\n";
    chk('receipt: new receipt dated in a closed year refused', said($r) && (int)q('SELECT COUNT(*) c FROM tblcontributions_header')[0]['c'] == $n);
    setYear(0);
    $r = $cm->create($rc()); chk('receipt: accepted while the year is open', $r['success'], json_encode($r));
    $rid = (int)$r['id'];
    setYear(1);
    $r = $cm->create($rc(['id' => $rid, 'isedit' => true, 'amounts' => ['120']])); echo '   [receipt edit] ' . json_encode($r['errors']) . "\n";
    chk('receipt: edit in a closed year refused', said($r) && entryDate(1, $rid) === $D);
    $r = $cm->delete(['id' => $rid]); echo '   [receipt delete] ' . json_encode($r['errors']) . "\n";
    chk('receipt: delete in a closed year refused', said($r) && entries(1, $rid) == 1);
    setYear(0);
    $r = $cm->delete(['id' => $rid]); chk('receipt: delete works once the year is open', $r['success'], json_encode($r));

    // ---------------- petty cash receipts (type 10)
    $cr = new Cashreceipt;
    $pc = function ($o = []) use ($bank, $D) {
        return $o + ['id' => '', 'isedit' => false, 'date' => $D, 'bank' => $bank, 'pettycashaccount' => 98, 'amount' => '50', 'reference' => 'YL10', 'description' => 'year lock'];
    };
    setYear(1);
    $n = (int)q('SELECT COUNT(*) c FROM tblpettycash')[0]['c'];
    $r = $cr->CreateUpdate($pc()); echo '   [petty cash create] ' . json_encode($r['errors']) . "\n";
    chk('petty cash: new receipt dated in a closed year refused', said($r) && (int)q('SELECT COUNT(*) c FROM tblpettycash')[0]['c'] == $n);
    setYear(0);
    $r = $cr->CreateUpdate($pc()); chk('petty cash: accepted while the year is open', $r['success'], json_encode($r));
    $pid = (int)$r['id'];
    setYear(1);
    $r = $cr->CreateUpdate($pc(['id' => $pid, 'isedit' => true, 'amount' => '60'])); echo '   [petty cash edit] ' . json_encode($r['errors']) . "\n";
    chk('petty cash: edit in a closed year refused', said($r) && entries(10, $pid) == 1);
    $r = $cr->Delete($pid); echo '   [petty cash delete] ' . json_encode($r['errors']) . "\n";
    chk('petty cash: delete in a closed year refused', said($r) && entries(10, $pid) == 1);
    setYear(0);
    $r = $cr->Delete($pid); chk('petty cash: delete works once the year is open', $r['success'], json_encode($r));

    // ---------------- the Ledger lock itself catches a screen that has no model-level check (here: a receipt whose
    // header year is open but whose entry is dated in a closed year, i.e. the entry date is what is locked)
    setYear(0);
    $r = $cm->create($rc()); $rid = (int)$r['id'];
    setYear(1);
    $pdo->prepare('UPDATE tblcontributions_header SET fiscalYearId = 12 WHERE ID = ?')->execute([$rid]);   // header looks open, entry is dated 2023-11-15
    $r = $cm->delete(['id' => $rid]); echo '   [receipt delete, header year open] ' . json_encode($r['errors']) . "\n";
    chk('Ledger::void refuses even when the screen-level check passes', said($r) && entries(1, $rid) == 1);
    setYear(0);
} catch (Throwable $e) {
    $fail++; echo "EXCEPTION: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
}

$pdo->exec('UPDATE tblfiscalyears SET closed=0, deleted=0 WHERE ID=10');   // dev year 2023-2024 is open
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId > ?')->execute([$max['journal_entries']]);
foreach ($tables as $t => $k) { $pdo->prepare("DELETE FROM $t WHERE $k > ?")->execute([$max[$t]]); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->prepare('DELETE FROM journal_number_seq WHERE congregationId = 11')->execute();
if ($jseq) { $pdo->prepare('INSERT INTO journal_number_seq (congregationId, lastJournalNo) VALUES (?, ?)')->execute([$jseq['congregationId'], $jseq['lastJournalNo']]); }
$pdo->exec('DELETE FROM receipt_number_seq');
$ins = $pdo->prepare('INSERT INTO receipt_number_seq (congregationId,kind,fiscalYearId,lastNo) VALUES (?,?,?,?)');
foreach ($seqSnapshot as $s) { $ins->execute([$s['congregationId'], $s['kind'], $s['fiscalYearId'], $s['lastNo']]); }
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k > {$max[$t]}")->fetchColumn(); }
$y = $pdo->query('SELECT closed, deleted FROM tblfiscalyears WHERE ID = 10')->fetch(PDO::FETCH_ASSOC);
echo "\nPASSED $pass FAILED $fail ; leftover rows after cleanup: $left ; year 2023-2024 restored: " . ($y['closed'] == 0 && $y['deleted'] == 0 ? 'yes' : 'NO') . "\n";

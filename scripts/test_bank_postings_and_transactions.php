<?php
// Functional run of Bankposting + Banktransaction against dev (cong 11); cleans up every row it creates.
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
foreach (['Bankposting', 'Banktransaction'] as $m) { require_once "models/$m.php"; }

$pdo = (new Database)->pdo();
$cong = 11;
$uid = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();
$_SESSION['congId'] = $cong;
$_SESSION['userId'] = $uid;

$tables = ['journal_entries' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tblbanktransactions' => 'ID', 'tblpettycash' => 'ID', 'tbllogs' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function lines($type, $id) {
    return q('SELECT l.accountId,l.dc,l.amount FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId
              WHERE e.congregationId=11 AND e.transactionTypeId=? AND e.transactionId=? AND e.deleted=0 ORDER BY l.lineNumber', [$type, $id]);
}
function brows($type, $id, $active = true) {
    if ($type == 9) { return q('SELECT * FROM tblbankpostings WHERE ID=? AND transactionType=9' . ($active ? ' AND deleted=0' : ''), [$id]); }
    return q('SELECT * FROM tblbankpostings WHERE transactionType=? AND transactionId=?' . ($active ? ' AND deleted=0' : '') . ' ORDER BY ID', [$type, $id]);
}

try {
    $bp = new Bankposting; $bt = new Banktransaction;
    $banks = array_map(function ($b) { return (int)$b->ID; }, $bp->getBanks());
    $accts = $bp->getAccounts();
    $accIds = array_map(function ($a) { return (int)$a->ID; }, $accts);
    echo "banks: " . implode(',', $banks) . "  contra accounts: " . count($accts) . "\n";
    chk('contra list has no bank / reporting (33)', !array_intersect($accIds, array_merge($banks, [33])));
    $b1 = $banks[0]; $b2 = $banks[1] ?? null;
    chk('two banks for transfer test', $b2 !== null);
    $contra = (int)$accts[0]->ID;
    $base = ['date' => '2026-01-15', 'bank' => $b1, 'type' => 1, 'amount' => '100.50', 'account' => $contra, 'reference' => 'T83', 'narration' => 'Test Narr'];

    // ---------- type 9
    echo "\n-- type 9\n";
    $r = $bp->create($base); chk('t9 create', $r['success'], json_encode($r));
    $id9 = (int)q('SELECT MAX(ID) m FROM tblbankpostings WHERE transactionType=9 AND congregationId=11')[0]['m'];
    $l = lines(9, $id9);
    chk('t9 deposit Dr bank / Cr contra', count($l) == 2 && $l[0]['accountId'] == $b1 && $l[0]['dc'] == 'DEBIT' && $l[1]['accountId'] == $contra && $l[1]['dc'] == 'CREDIT', json_encode($l));
    $row = brows(9, $id9)[0] ?? null;
    chk('t9 row debit 100.5 method 1 uncleared', $row && abs($row['debit'] - 100.5) < 0.001 && $row['transactionMethod'] == 1 && $row['cleared'] == 0);
    chk('t9 entry transactionId = row ID, mirror rows', count(q('SELECT 1 FROM tblledger WHERE transactionType=9 AND transactionId=? AND deleted=0', [$id9])) == 2);

    q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE ID=?', ['2026-01-20', $id9]);
    $r = $bp->update(['id' => $id9, 'narration' => 'Changed narr'] + $base); chk('t9 edit narration', $r['success'], json_encode($r));
    $row = brows(9, $id9)[0]; chk('t9 narration-only keeps cleared + date', $row['cleared'] == 1 && $row['clearedDare'] == '2026-01-20');
    // 8.9 follow-up: a cleared row keeps its bank, side and amount (it used to be un-cleared by such an edit)
    $r = $bp->update(['id' => $id9, 'date' => '2026-01-16'] + $base); chk('t9 date-only edit of a cleared row ok, stays cleared', $r['success'] && brows(9, $id9)[0]['cleared'] == 1 && brows(9, $id9)[0]['transactionDate'] == '2026-01-16');
    $r = $bp->update(['id' => $id9, 'amount' => '120'] + $base); chk('t9 amount change on a cleared row refused', !$r['success'] && stripos(json_encode($r), 'cleared') !== false && abs(brows(9, $id9)[0]['debit'] - 100.5) < 0.001 && brows(9, $id9)[0]['cleared'] == 1);
    $r = $bp->update(['id' => $id9, 'type' => 2] + $base); chk('t9 side change on a cleared row refused', !$r['success'] && stripos(json_encode($r), 'cleared') !== false && brows(9, $id9)[0]['debit'] > 0);
    if ($b2) { $r = $bp->update(['id' => $id9, 'bank' => $b2] + $base); chk('t9 bank change on a cleared row refused', !$r['success'] && stripos(json_encode($r), 'cleared') !== false && brows(9, $id9)[0]['bankId'] == $b1); }
    chk('t9 refused edits left the entry as it was (100.50, one active entry)', lines(9, $id9)[0]['amount'] == 100.5 && count(q('SELECT 1 FROM journal_entries WHERE transactionTypeId=9 AND transactionId=? AND congregationId=11 AND deleted=0', [$id9])) == 1);
    q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE ID=?', [$id9]);
    $r = $bp->update(['id' => $id9, 'amount' => '120', 'date' => '2026-01-16'] + $base); chk('t9 edit amount', $r['success']);
    $row = brows(9, $id9)[0]; chk('t9 amount change on an uncleared row', $row['cleared'] == 0 && $row['clearedDare'] === null && abs($row['debit'] - 120) < 0.001);
    chk('t9 entry updated in place (amount 120, one active entry)', lines(9, $id9)[0]['amount'] == 120 && count(q('SELECT 1 FROM journal_entries WHERE transactionTypeId=9 AND transactionId=? AND congregationId=11 AND deleted=0', [$id9])) == 1);
    $r = $bp->update(['id' => $id9, 'amount' => '120', 'type' => 2] + $base); chk('t9 edit side to withdrawal', $r['success']);
    $row = brows(9, $id9)[0]; $l = lines(9, $id9);
    chk('t9 side change: Cr bank / Dr contra', $row['cleared'] == 0 && $row['credit'] == 120 && count($l) == 2 && array_column($l,'dc','accountId') == [$contra => 'DEBIT', $b1 => 'CREDIT'], json_encode($l));
    if ($b2) {
        $r = $bp->update(['id' => $id9, 'amount' => '120', 'type' => 2, 'bank' => $b2] + $base);
        chk('t9 bank change moves to bank 2', $r['success'] && brows(9, $id9)[0]['cleared'] == 0 && brows(9, $id9)[0]['bankId'] == $b2);
    }
    // validation
    $n0 = (int)q('SELECT COUNT(*) c FROM tblbankpostings')[0]['c'];
    foreach ([['account', $b1, 'bank as contra'], ['account', 33, 'reporting contra'], ['bank', $contra, 'non-bank as bank'], ['amount', '-5', 'negative'], ['amount', '1.234', '3 decimals'], ['type', 9, 'bad method']] as $c) {
        $r = $bp->create([$c[0] => $c[1]] + $base);
        echo "   [{$c[2]}] " . json_encode($r['errors']) . "\n";
        chk("t9 refuses {$c[2]}", !$r['success']);
    }
    chk('t9 failed creates left no rows', (int)q('SELECT COUNT(*) c FROM tblbankpostings')[0]['c'] == $n0);
    // guard: rows that are not type 9
    $other = q('SELECT ID FROM tblbankpostings WHERE transactionType<>9 AND deleted=0 AND congregationId=11 LIMIT 1')[0]['ID'];
    $foreign = q('SELECT ID FROM tblbankpostings WHERE congregationId<>11 AND deleted=0 LIMIT 1');
    $r = $bp->delete($other); chk('t9 guard: delete refuses non-9 row', !$r['success'] && $r['errors'][0] === Bankposting::NOT_MINE);
    $r = $bp->update(['id' => $other] + $base); chk('t9 guard: update refuses non-9 row', !$r['success'] && $r['errors'][0] === Bankposting::NOT_MINE);
    chk('t9 guard: edit refuses non-9 row', $bp->edit(encryptId($other)) === null);
    chk('t9 guard: other row untouched', (int)q('SELECT deleted d FROM tblbankpostings WHERE ID=?', [$other])[0]['d'] === 0);
    if ($foreign) { $r = $bp->delete($foreign[0]['ID']); chk('t9 guard: refuses another congregation row', !$r['success']); }
    $r = $bp->delete($id9); chk('t9 delete', $r['success']);
    chk('t9 delete: row deleted, entry voided, none active', (int)q('SELECT deleted d FROM tblbankpostings WHERE ID=?', [$id9])[0]['d'] === 1 && !lines(9, $id9));
    $r = $bp->delete($id9); chk('t9 second delete refused', !$r['success']);

    // ---------- type 13
    echo "\n-- type 13\n";
    $mk = function ($o) use ($b1) {
        return $o + ['id' => null, 'isedit' => false, 'date' => '2026-01-15', 'bank' => $b1, 'type' => 1, 'transfer' => null, 'cashaccount' => 34, 'amount' => 500,
                     'reference' => 'T83BT', 'description' => 'Test', 'deposittosubs' => false, 'subaccounts' => null,
                     'issubmain' => false, 'issubtransfer' => false, 'issubtxn' => false];
    };
    $newid = function () { return (int)q('SELECT MAX(ID) m FROM tblbanktransactions')[0]['m']; };

    $r = $bt->CreateUpdate($mk(['type' => 1])); chk('t13 deposit save', $r['success'], json_encode($r));
    $d = $newid(); $l = lines(13, $d); $br = brows(13, $d);
    chk('t13 deposit Dr bank / Cr 34', count($l) == 2 && $l[0]['accountId'] == $b1 && $l[0]['dc'] == 'DEBIT' && $l[1]['accountId'] == 34, json_encode($l));
    chk('t13 deposit one bank row debit 500 method 1', count($br) == 1 && $br[0]['debit'] == 500 && $br[0]['transactionMethod'] == 1);
    q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE ID=?', ['2026-01-20', $br[0]['ID']]);
    $r = $bt->CreateUpdate($mk(['id' => $d, 'isedit' => true, 'description' => 'Edited narr', 'type' => 1])); chk('t13 edit narration', $r['success']);
    $br2 = brows(13, $d); chk('t13 narration edit keeps same cleared row', count($br2) == 1 && $br2[0]['ID'] == $br[0]['ID'] && $br2[0]['cleared'] == 1);
    // 8.9 follow-up: a cleared bank row can no longer be changed (bank / amount) or voided; the test refuses it, then un-clears the row and goes on
    $r = $bt->CreateUpdate($mk(['id' => $d, 'isedit' => true, 'amount' => 700, 'type' => 1])); chk('t13 edit amount on a cleared row refused', !$r['success'] && stripos(json_encode($r), 'cleared') !== false);
    q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE ID=?', [$br[0]['ID']]);
    $r = $bt->CreateUpdate($mk(['id' => $d, 'isedit' => true, 'amount' => 700, 'type' => 1])); chk('t13 edit amount', $r['success']);
    $br3 = brows(13, $d); chk('t13 amount edit: new uncleared row, old voided', count($br3) == 1 && $br3[0]['ID'] != $br[0]['ID'] && $br3[0]['cleared'] == 0 && $br3[0]['debit'] == 700);

    $r = $bt->CreateUpdate($mk(['type' => 2])); chk('t13 withdrawal save', $r['success'], json_encode($r));
    $w = $newid(); $l = lines(13, $w); $br = brows(13, $w);
    chk('t13 withdrawal Dr 34 / Cr bank, credit row method 2', $l[0]['accountId'] == 34 && $l[0]['dc'] == 'DEBIT' && $l[1]['accountId'] == $b1 && count($br) == 1 && $br[0]['credit'] == 500 && $br[0]['transactionMethod'] == 2, json_encode($l));

    $r = $bt->CreateUpdate($mk(['type' => 5, 'transfer' => '98'])); chk('t13 transfer to petty cash save', $r['success'], json_encode($r));
    $p = $newid(); $l = lines(13, $p); $br = brows(13, $p);
    chk('t13 petty cash: Dr 98 / Cr bank, one bank row', $l[0]['accountId'] == 98 && $l[0]['dc'] == 'DEBIT' && $l[1]['accountId'] == $b1 && count($br) == 1 && $br[0]['credit'] == 500, json_encode($l));
    chk('t13 petty cash receipt row written', count(q('SELECT 1 FROM tblpettycash WHERE TransactionType=13 AND TransactionId=? AND Deleted=0', [$p])) == 1);
    $r = $bt->CreateUpdate($mk(['id' => $p, 'isedit' => true, 'type' => 5, 'transfer' => '98', 'amount' => 650])); chk('t13 petty cash edit', $r['success']);
    chk('t13 petty cash edit: 1 receipt (650), entry 650', count(q('SELECT 1 FROM tblpettycash WHERE TransactionType=13 AND TransactionId=?', [$p])) == 1 && lines(13, $p)[0]['amount'] == 650);

    $r = $bt->CreateUpdate($mk(['type' => 5, 'transfer' => '167'])); chk('t13 transfer to fixed deposits save', $r['success'], json_encode($r));
    $fdid = $newid(); $l = lines(13, $fdid);
    chk('t13 fixed deposits: Dr 167 / Cr bank', $l[0]['accountId'] == 167 && $l[1]['accountId'] == $b1 && count(brows(13, $fdid)) == 1, json_encode($l));

    if ($b2) {
        $r = $bt->CreateUpdate($mk(['type' => 5, 'transfer' => (string)$b2])); chk('t13 bank-to-bank save', $r['success'], json_encode($r));
        $x = $newid(); $br = brows(13, $x);
        chk('t13 bank-to-bank: TWO bank rows (out of 1, into 2)', count($br) == 2 && array_sum(array_column($br,'credit')) == 500 && array_sum(array_column($br,'debit')) == 500 && in_array($b1, array_column($br,'bankId')) && in_array($b2, array_column($br,'bankId')), json_encode($br));
        $r = $bt->Delete($x); chk('t13 bank-to-bank delete', $r['success'] && !brows(13, $x) && !lines(13, $x));
    }
    // validation
    $n = (int)q('SELECT COUNT(*) c FROM tblbanktransactions')[0]['c'];
    foreach ([['transfer', '33', 'type 5 to reporting account', 5], ['transfer', '999999', 'type 5 to unknown account', 5], ['bank', '33', 'reporting as bank', 1]] as $c) {
        $in = [$c[0] => $c[1], 'type' => $c[3]];
        if ($c[3] == 5 && $c[0] != 'transfer') { $in['transfer'] = '98'; }
        $r = $bt->CreateUpdate($mk($in));
        echo "   [{$c[2]}] " . json_encode($r['errors']) . "\n";
        chk("t13 refuses {$c[2]}", !$r['success']);
    }
    chk('t13 failed saves rolled back (no new source rows)', (int)q('SELECT COUNT(*) c FROM tblbanktransactions')[0]['c'] == $n);
    // delete
    foreach (['deposit' => $d, 'withdrawal' => $w, 'petty cash' => $p, 'fixed deposits' => $fdid] as $name => $id) {
        $r = $bt->Delete($id);
        chk("t13 delete $name", $r['success'] && !brows(13, $id) && !lines(13, $id) && (int)q('SELECT Deleted d FROM tblbanktransactions WHERE ID=?', [$id])[0]['d'] === 1);
    }
    chk('t13 petty cash receipt soft-deleted', count(q('SELECT 1 FROM tblpettycash WHERE TransactionType=13 AND TransactionId=? AND Deleted=0', [$p])) == 0);
    $r = $bt->Delete($d); chk('t13 second delete refused', !$r['success']);
    // Delete refuses another congregation's transaction
    $fo = q('SELECT ID FROM tblbanktransactions WHERE CongregationId<>11 AND Deleted=0 LIMIT 1');
    if ($fo) { $r = $bt->Delete($fo[0]['ID']); chk('t13 delete refuses another congregation', !$r['success']); }
} catch (Throwable $e) {
    $fail++; echo "EXCEPTION: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// ---------- clean up everything created in this run
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId > ?')->execute([$max['journal_entries']]);
foreach ($tables as $t => $k) { $pdo->prepare("DELETE FROM $t WHERE $k > ?")->execute([$max[$t]]); }
// entries created earlier but touched are all new IDs; sanity check nothing is left
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k > {$max[$t]}")->fetchColumn(); }
echo "\nPASSED $pass FAILED $fail ; leftover rows after cleanup: $left\n";

<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
/**
 * Task 13: editing / deleting a bank transaction must not touch other transactions' rows in tblbanktransactions_subaccounts
 * (receipts type 1, opening balances type 18, other congregations), writes its own type-13 rows correctly (split and sub-account
 * transfer), is all-or-nothing, and scripts/sql/banktx_subaccount_loss_check.sql lists what the OLD statement removes.
 * Fixtures use explicit IDs (20001+) so that a bank transaction and a receipt share one ID number. Self-cleaning (also resets AUTO_INCREMENT).
 */
chdir(__DIR__ . '/../app');
require 'boostrap.php';
require_once 'models/Banktransaction.php';

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;
$_SESSION['userId'] = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();
$OTHER = 12;   // another congregation id; only used as a number in CongregationId

$tables = ['journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblbankpostings' => 'ID', 'tblbanktransactions' => 'ID', 'tblpettycash' => 'ID', 'tbllogs' => 'ID',
           'tblbanktransactions_subaccounts' => 'ID', 'tblcontributions_header' => 'ID', 'tblcontributions_details' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function x($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->rowCount(); }

/** the statements of the read-only check script, in order (comment lines dropped) */
function checkQueries() {
    $sql = '';
    foreach (file(__DIR__ . '/sql/banktx_subaccount_loss_check.sql') as $l) { if (!preg_match('/^\s*--/', $l)) { $sql .= $l; } }
    return array_values(array_filter(array_map('trim', explode(";\n", str_replace("\r", '', $sql)))));
}
function runCheck($i) { $qs = checkQueries(); return q(rtrim($qs[$i], ';')); }
function ids($rows, $col) { return array_map('intval', array_column($rows, $col)); }
/** all rows of the table for a (type, transaction id, congregation), normalised for comparison */
function subrows($type, $tid, $cong = 11) {
    return q('SELECT TransactionDate,TransactionId,SubAccountId,Amount,FromAccountId,ToAccountId,Narration,TransactionType,Reference,CongregationId
              FROM tblbanktransactions_subaccounts WHERE TransactionType=? AND TransactionId=? AND CongregationId=? ORDER BY SubAccountId,Amount', [$type, $tid, $cong]);
}
function hash_rows($r) { return md5(json_encode($r)); }

try {
    $bt = new Banktransaction;
    $b1 = (int)$bt->GetBanks()[0]->ID;
    $sub = q('SELECT ID FROM tblbanksubaccounts WHERE BankId=? AND Deleted=0 ORDER BY ID LIMIT 2', [$b1]);
    chk('two sub accounts on the first bank', count($sub) === 2);
    $s1 = (int)$sub[0]['ID']; $s2 = (int)$sub[1]['ID'];
    $cType = (int)q('SELECT contributionTypeId c FROM tblcontributions_details WHERE contributionTypeId>0 LIMIT 1')[0]['c'];
    $mk = function ($o) use ($b1) {
        return $o + ['id' => null, 'isedit' => false, 'date' => '2026-01-15', 'bank' => $b1, 'type' => 1, 'transfer' => null, 'cashaccount' => 34,
                     'amount' => '300', 'reference' => 'T13', 'description' => 'Test 13', 'deposittosubs' => false, 'subaccounts' => null,
                     'issubmain' => false, 'issubtransfer' => false, 'issubtxn' => false];
    };
    $splitOf = function ($a1, $a2) use ($s1, $s2) { return [(object)['accountid' => $s1, 'amount' => $a1], (object)['accountid' => $s2, 'amount' => $a2]]; };

    /** fixture: bank transaction N (plain deposit) + receipt N with a sub-account line and its type-1 row + a type-18 row, a type-13 row of another congregation,
     *  and a type-1 row of another congregation, all carrying TransactionId = N */
    $fixture = function ($n) use ($pdo, $b1, $s1, $s2, $cType, $OTHER) {
        x('INSERT INTO tblbanktransactions (ID,TransactionDate,TransactionTypeId,BankId,Amount,CashAccountId,Reference,Description,CongregationId)
           VALUES (?,?,1,?,300,34,?,?,11)', [$n, '2026-01-15', $b1, 'T13', 'fixture']);
        x('INSERT INTO tblcontributions_header (ID,fiscalYearId,postedBy,postedDate,status,Deleted,receiptNo,congregationId) VALUES (?,12,?,?,1,0,?,11)',
          [$n, $_SESSION['userId'], '2026-01-10', "T13-$n"]);
        x('INSERT INTO tblcontributions_details (HeaderId,contributionDate,contributionTypeId,paymentMethodId,bankId,amount,category,incomeType,forGroup,paymentReference,subaccount)
           VALUES (?,?,?,2,?,150,2,1,0,?,?)', [$n, '2026-01-10', $cType, $b1, 'R13', $s1]);
        $ins = 'INSERT INTO tblbanktransactions_subaccounts (TransactionDate,TransactionId,SubAccountId,Amount,TransactionType,Narration,Reference,CongregationId) VALUES (?,?,?,?,?,?,?,?)';
        x($ins, ['2026-01-10', $n, $s1, '150.00', 1, 'Collection', 'R13', 11]);              // the receipt's row (type 1)
        x($ins, ['2023-10-01', $n, $s2, '999.00', 18, 'Sub account opening balance.', null, 11]);  // an opening balance with TransactionId = N
        x($ins, ['2026-01-11', $n, $s1, '77.00', 13, 'sub-account deposit', 'OTHER', $OTHER]);   // another congregation's type-13 row
        x($ins, ['2026-01-11', $n, $s2, '55.00', 1, 'Collection', 'OTHER', $OTHER]);             // another congregation's receipt row
    };
    $others = function ($n) {
        return [hash_rows(subrows(1, $n)), hash_rows(subrows(18, $n)), hash_rows(subrows(13, $n, 12)), hash_rows(subrows(1, $n, 12))];
    };
    $lostIds = function () { return ids(runCheck(0), 'receiptId'); };

    // ---------------------------------------------------------------- the old statement loses rows; the check script lists them
    $N = 20001;
    $fixture($N);
    chk('baseline: check script does not list the fixture receipt', !in_array($N, $lostIds(), true));
    $before = $others($N);
    chk('fixture rows exist (receipt row, opening balance, other-congregation rows)', count(subrows(1, $N)) === 1 && count(subrows(18, $N)) === 1 && count(subrows(13, $N, 12)) === 1 && count(subrows(1, $N, 12)) === 1);
    $old = x('DELETE FROM tblbanktransactions_subaccounts WHERE (TransactionId = ?)', [$N]);   // the statement of d9dfb48 (Banktransaction::update)
    chk('OLD statement deletes all 4 rows of other transactions / congregations', $old === 4, "deleted $old");
    chk('check script Q1 lists the receipt whose row the old statement removed (with the bank transaction of the same ID)', in_array($N, $lostIds(), true));
    $row = array_values(array_filter(runCheck(0), function ($r) use ($N) { return (int)$r['receiptId'] === $N; }))[0] ?? [];
    chk('Q1 row: receipt number, sub account, amount, expected 1 / present 0, same-ID bank transaction named', ($row['receiptNo'] ?? '') === "T13-$N" && (int)$row['subAccountId'] === $s1
        && (float)$row['amount'] === 150.0 && (int)$row['expectedRows'] === 1 && (int)$row['presentRows'] === 0 && (int)$row['sameIdBankTxn'] === $N && strpos($row['activeBankTxnWithSameId'], 'yes') === 0, json_encode($row));
    chk('check script Q3 no longer shows the opening balance row of the fixture', !in_array($N, ids(runCheck(2), 'TransactionId'), true));
    // put the rows back (what the accountants would do by hand)
    x('DELETE FROM tblbanktransactions_subaccounts WHERE TransactionId=?', [$N]);
    x('INSERT INTO tblbanktransactions_subaccounts (TransactionDate,TransactionId,SubAccountId,Amount,TransactionType,Narration,Reference,CongregationId) VALUES
      (?,?,?,?,1,?,?,11),(?,?,?,?,18,?,?,11),(?,?,?,?,13,?,?,?),(?,?,?,?,1,?,?,?)',
      ['2026-01-10', $N, $s1, '150.00', 'Collection', 'R13',
       '2023-10-01', $N, $s2, '999.00', 'Sub account opening balance.', null,
       '2026-01-11', $N, $s1, '77.00', 'sub-account deposit', 'OTHER', $OTHER,
       '2026-01-11', $N, $s2, '55.00', 'Collection', 'OTHER', $OTHER]);
    $before = $others($N);
    chk('rows restored; check script is clean again', !in_array($N, $lostIds(), true));

    // ---------------------------------------------------------------- the fixed Update: split
    $r = $bt->CreateUpdate($mk(['id' => $N, 'isedit' => true, 'deposittosubs' => true, 'subaccounts' => $splitOf('100', '200')]));
    chk('edit into a split deposit saved', $r['success'], json_encode($r));
    chk('split edit: receipt rows, opening balance and other-congregation rows untouched', $others($N) === $before);
    $own = subrows(13, $N);
    chk('split edit: own type-13 rows = 2, amounts 100 / 200, date, reference, narration', count($own) === 2 && (float)$own[0]['Amount'] === 100.0 && (float)$own[1]['Amount'] === 200.0
        && (int)$own[0]['SubAccountId'] === $s1 && (int)$own[1]['SubAccountId'] === $s2 && $own[0]['TransactionDate'] === '2026-01-15' && $own[0]['Reference'] === 'T13'
        && $own[0]['Narration'] === 'sub-account deposit' && (int)$own[0]['CongregationId'] === 11, json_encode($own));
    chk('split edit: DepositToSubAccounts flag saved', (int)q('SELECT DepositToSubAccounts d FROM tblbanktransactions WHERE ID=?', [$N])[0]['d'] === 1);
    chk('split edit: check script Q1 and Q2 list nothing for N', !in_array($N, $lostIds(), true) && !in_array($N, ids(runCheck(1), 'bankTxnId'), true));
    $saved = $bt->GetSavedSubsAccounts($N);
    chk('GetSavedSubsAccounts returns only the own rows (2, with amounts)', count($saved) === 2 && (float)$saved[0]->Amount + (float)$saved[1]->Amount === 300.0, json_encode($saved));

    $r = $bt->CreateUpdate($mk(['id' => $N, 'isedit' => true, 'deposittosubs' => true, 'subaccounts' => $splitOf('50', '250'), 'reference' => 'T13B']));
    $own = subrows(13, $N);
    chk('re-edit rewrites the split (50 / 250, new reference), still 2 rows', $r['success'] && count($own) === 2 && (float)$own[0]['Amount'] === 50.0 && (float)$own[1]['Amount'] === 250.0 && $own[0]['Reference'] === 'T13B');
    chk('re-edit: other rows still untouched', $others($N) === $before);

    $r = $bt->CreateUpdate($mk(['id' => $N, 'isedit' => true]));
    chk('edit back to a plain deposit: own rows gone, flag cleared, others untouched', $r['success'] && !subrows(13, $N)
        && (int)q('SELECT DepositToSubAccounts d FROM tblbanktransactions WHERE ID=?', [$N])[0]['d'] === 0 && $others($N) === $before, json_encode($r));

    // ---------------------------------------------------------------- the fixed Update: sub-account transfer (a second colliding pair)
    $M = 20002;
    $fixture($M);
    $beforeM = $others($M);
    $r = $bt->CreateUpdate($mk(['id' => $M, 'isedit' => true, 'type' => 6, 'bank' => $s1, 'transfer' => $s2, 'amount' => '120', 'issubtxn' => true, 'issubmain' => true, 'issubtransfer' => true, 'cashaccount' => null]));
    chk('edit into a sub-account transfer saved', $r['success'], json_encode($r));
    $own = subrows(13, $M);
    $by = []; foreach ($own as $o) { $by[(int)$o['SubAccountId']] = $o; }
    chk('transfer: 2 own rows, +120 on the receiving sub account (FromAccountId), -120 on the giving one (ToAccountId)',
        count($own) === 2 && isset($by[$s2], $by[$s1]) && (float)$by[$s2]['Amount'] === 120.0 && (int)$by[$s2]['FromAccountId'] === $s1 && $by[$s2]['ToAccountId'] === null
        && (float)$by[$s1]['Amount'] === -120.0 && (int)$by[$s1]['ToAccountId'] === $s2 && $by[$s1]['FromAccountId'] === null, json_encode($own));
    chk('transfer: flags saved (IsSubInterTransfer = 1), no ledger entry', (int)q('SELECT IsSubInterTransfer i FROM tblbanktransactions WHERE ID=?', [$M])[0]['i'] === 1
        && !q('SELECT 1 FROM journal_entries WHERE transactionTypeId=13 AND transactionId=? AND congregationId=11 AND deleted=0', [$M]));
    chk('transfer edit: receipt, opening balance and other-congregation rows untouched', $others($M) === $beforeM);
    $r = $bt->CreateUpdate($mk(['id' => $M, 'isedit' => true, 'type' => 6, 'bank' => $s2, 'transfer' => $s1, 'amount' => '80', 'issubtxn' => true, 'issubmain' => true, 'issubtransfer' => true, 'cashaccount' => null]));
    $own = subrows(13, $M);
    chk('transfer re-edit reverses the direction, still 2 rows summing to 0', $r['success'] && count($own) === 2 && abs(array_sum(array_column($own, 'Amount'))) < 0.001 && $others($M) === $beforeM, json_encode($r));

    // ---------------------------------------------------------------- another congregation / not found
    $other = hash_rows(q('SELECT * FROM tblbanktransactions_subaccounts WHERE CongregationId=12 AND TransactionId IN (?,?) ORDER BY ID', [$N, $M]));
    $_SESSION['congId'] = 12;
    $r = $bt->CreateUpdate($mk(['id' => $N, 'isedit' => true, 'deposittosubs' => true, 'subaccounts' => $splitOf('1', '299')]));
    $_SESSION['congId'] = 11;
    chk('edit from another congregation is refused and writes nothing', !$r['success'] && hash_rows(q('SELECT * FROM tblbanktransactions_subaccounts WHERE CongregationId=12 AND TransactionId IN (?,?) ORDER BY ID', [$N, $M])) === $other
        && !subrows(13, $N) && $others($N) === $before, json_encode($r));

    // ---------------------------------------------------------------- all or nothing: a failure after the UPDATE / DELETE / INSERTs
    $r = $bt->CreateUpdate($mk(['deposittosubs' => true, 'subaccounts' => $splitOf('100', '200'), 'reference' => 'T13F']));
    chk('split deposit saved (via Save)', $r['success'], json_encode($r));
    $F = (int)q('SELECT MAX(ID) m FROM tblbanktransactions')[0]['m'];
    $rowsF = subrows(13, $F); $srcF = q('SELECT * FROM tblbanktransactions WHERE ID=?', [$F]);
    $postF = q('SELECT * FROM tblbankpostings WHERE transactionType=13 AND transactionId=? AND deleted=0', [$F]);
    $entF = q('SELECT e.ID,e.entryDate,l.accountId,l.dc,l.amount FROM journal_entries e JOIN journal_lines l ON l.journalEntryId=e.ID WHERE e.transactionTypeId=13 AND e.transactionId=? AND e.congregationId=11 AND e.deleted=0 ORDER BY l.ID', [$F]);
    chk('Save wrote 2 own rows and set the flag', count($rowsF) === 2 && (int)$srcF[0]['DepositToSubAccounts'] === 1 && $postF && $entF);
    x('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE transactionType=13 AND transactionId=?', ['2026-01-20', $F]);
    $postF = q('SELECT * FROM tblbankpostings WHERE transactionType=13 AND transactionId=? AND deleted=0', [$F]);
    $logs = (int)q('SELECT COUNT(*) c FROM tbllogs')[0]['c'];
    $r = $bt->CreateUpdate($mk(['id' => $F, 'isedit' => true, 'deposittosubs' => true, 'subaccounts' => $splitOf('1', '499'), 'amount' => '500', 'reference' => 'CHANGED', 'description' => 'changed']));
    chk('edit of a cleared deposit (amount) is refused AFTER the sub-account rows were rewritten', !$r['success'] && stripos(json_encode($r), 'cleared') !== false, json_encode($r));
    chk('...and nothing is half-written: bank transaction row, own sub-account rows, ledger entry, bank rows, audit log all as before',
        q('SELECT * FROM tblbanktransactions WHERE ID=?', [$F]) === $srcF && subrows(13, $F) === $rowsF
        && q('SELECT e.ID,e.entryDate,l.accountId,l.dc,l.amount FROM journal_entries e JOIN journal_lines l ON l.journalEntryId=e.ID WHERE e.transactionTypeId=13 AND e.transactionId=? AND e.congregationId=11 AND e.deleted=0 ORDER BY l.ID', [$F]) === $entF
        && q('SELECT * FROM tblbankpostings WHERE transactionType=13 AND transactionId=? AND deleted=0', [$F]) === $postF
        && (int)q('SELECT COUNT(*) c FROM tbllogs')[0]['c'] === $logs);
    chk('the connection is not left inside a transaction', !$pdo->inTransaction());
    $r = $bt->CreateUpdate($mk(['id' => $F, 'isedit' => true, 'deposittosubs' => true, 'subaccounts' => $splitOf('100', '150'), 'amount' => '300']));
    chk('an invalid split (does not add up) is refused and leaves the rows alone', !$r['success'] && subrows(13, $F) === $rowsF, json_encode($r));
    x('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE transactionType=13 AND transactionId=?', [$F]);

    // ---------------------------------------------------------------- delete keeps other transactions' rows, removes its own
    $beforeF_others = [$others($N), $others($M)];
    $r = $bt->Delete($F);
    chk('delete of the split deposit removes its own sub-account rows (the book has no Deleted column)', $r['success'] && !subrows(13, $F), json_encode($r));
    chk('check script Q4 finds no row left by a deleted transaction', !in_array($F, ids(runCheck(3), 'bankTxnId'), true));
    $r = $bt->Delete($M);   // M is a sub-account transfer sharing its ID with a receipt, an opening balance and other-congregation rows
    chk('delete of transfer M removes its own 2 rows only', $r['success'] && !subrows(13, $M) && $others($M) === $beforeM, json_encode($r));
    $r = $bt->Delete($N);
    chk('delete of N keeps the receipt row, opening balance and other-congregation rows', $r['success'] && $others($N) === $before, json_encode($r));
    $_SESSION['congId'] = 12;
    $rowsM = q('SELECT COUNT(*) c FROM tblbanktransactions_subaccounts WHERE TransactionId IN (?,?)', [$N, $M]);
    $r = $bt->Delete($N);
    $_SESSION['congId'] = 11;
    chk('delete of an already deleted / foreign transaction is refused and changes nothing', !$r['success'] && q('SELECT COUNT(*) c FROM tblbanktransactions_subaccounts WHERE TransactionId IN (?,?)', [$N, $M]) === $rowsM);

    // ---------------------------------------------------------------- the old UI problem: a split lost through an old-form edit is listed by Q2
    $r = $bt->CreateUpdate($mk(['deposittosubs' => true, 'subaccounts' => $splitOf('100', '200'), 'reference' => 'T13Q2']));
    $Q = (int)q('SELECT MAX(ID) m FROM tblbanktransactions')[0]['m'];
    chk('Q2 baseline: a complete split is not listed', $r['success'] && !in_array($Q, ids(runCheck(1), 'bankTxnId'), true));
    x('DELETE FROM tblbanktransactions_subaccounts WHERE TransactionType=13 AND TransactionId=?', [$Q]);   // what the old edit form did to a split deposit
    $row = array_values(array_filter(runCheck(1), function ($r) use ($Q) { return (int)$r['bankTxnId'] === $Q; }))[0] ?? [];
    chk('Q2 lists the split deposit whose rows are gone (expected sum 300, present 0)', $row && (float)$row['expectedSum'] === 300.0 && (int)$row['presentRows'] === 0, json_encode($row));
    $bt->Delete($Q);

    // ---------------------------------------------------------------- the new edit form data
    $c = file_get_contents(__DIR__ . '/../app/controllers/Banktransactions.php');
    chk('controller blocks the edit of a sub-account transfer and passes the saved split', strpos($c, 'IsSubInterTransfer') !== false && strpos($c, "'savedsubs'") !== false);
} catch (Throwable $e) {
    $fail++; echo "EXCEPTION: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n";
}

if ($pdo->inTransaction()) { $pdo->rollBack(); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $t => $k) { $pdo->prepare("DELETE FROM $t WHERE $k > ?")->execute([$max[$t]]); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
foreach (['tblbanktransactions', 'tblcontributions_header'] as $t) {   // explicit fixture IDs moved the counter
    $pdo->exec("ALTER TABLE $t AUTO_INCREMENT = " . ((int)$pdo->query("SELECT IFNULL(MAX(ID),0) FROM $t")->fetchColumn() + 1));
}
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k > {$max[$t]}")->fetchColumn(); }
echo "\nleftover rows after cleanup: $left\n";
chk('self-cleaning: nothing left behind', $left === 0);
echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

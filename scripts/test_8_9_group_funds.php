<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
// 8.9 functional run: MMF receipts (11), group collections (17), fund approvals (12) on the Ledger library,
// congregation 11, dev only; cleans up after itself (and restores the fiscal years it touches).
//   E:\xampp\php\php.exe scripts\test_8_9_group_funds.php
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Mmfreceipt.php';
require_once 'models/Groupcollection.php';
require_once 'models/Groupfund.php';
require_once 'models/Expense.php';

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;
$_SESSION['isParish'] = 0;
$_SESSION['userType'] = 1;
$_SESSION['userId'] = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();

$tables = ['tblmmf' => 'ID', 'tblfundrequisition' => 'ID', 'tblexpenses' => 'ID', 'journal_entries' => 'ID', 'journal_lines' => 'ID',
           'tblbankpostings' => 'ID', 'tbllogs' => 'ID', 'tblbanksubaccounts' => 'ID', 'tblchurchrequisitioncategories' => 'id', 'tblaccounttypes' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$yearSnapshot = $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC);
$reqSnap = md5(json_encode($pdo->query('SELECT * FROM tblfundrequisition WHERE ID <= ' . $max['tblfundrequisition'] . ' ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC)));
$mmfSnap = md5(json_encode($pdo->query('SELECT * FROM tblmmf WHERE ID <= ' . $max['tblmmf'] . ' ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC)));
$balBefore = json_encode($pdo->query('SELECT g.ID, getmmfopeningbalbydate(g.ID,CURDATE()) b FROM tblgroups g ORDER BY g.ID')->fetchAll(PDO::FETCH_ASSOC));

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function entry($type, $id) {
    return q('SELECT l.accountId,l.dc,l.amount FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId
              WHERE e.congregationId=11 AND e.transactionTypeId=? AND e.transactionId=? AND e.deleted=0 ORDER BY l.lineNumber', [$type, $id]);
}
function voided($type, $id) { return (int)q('SELECT COUNT(*) c FROM journal_entries WHERE congregationId=11 AND transactionTypeId=? AND transactionId=? AND deleted=1', [$type, $id])[0]['c']; }
function activeEntries($type, $id) { return (int)q('SELECT COUNT(*) c FROM journal_entries WHERE congregationId=11 AND transactionTypeId=? AND transactionId=? AND deleted=0', [$type, $id])[0]['c']; }
function brows($type, $id, $active = true) { return q('SELECT * FROM tblbankpostings WHERE transactionType=? AND transactionId=?' . ($active ? ' AND deleted=0' : '') . ' ORDER BY ID', [$type, $id]); }
function mmf($id) { $r = q('SELECT * FROM tblmmf WHERE ID=?', [$id]); return $r ? $r[0] : null; }
function mmf12($id) { return q('SELECT * FROM tblmmf WHERE TransactionType=12 AND TransactionId=?', [$id]); }
function req($id) { return q('SELECT * FROM tblfundrequisition WHERE ID=?', [$id])[0]; }
function setYear($id, $closed, $deleted) { q('UPDATE tblfiscalyears SET closed=?, deleted=? WHERE ID=?', [$closed, $deleted, $id]); }
function errs($r) { return implode(' | ', $r['errors'] ?? []); }
function has($r, $needle) { return stripos(errs($r), $needle) !== false; }
function ok($r) { return $r === true || (is_string($r) && $r !== ''); }
function refused($r) { return is_array($r) && isset($r['error']); }
function newReq($type, $ownerId, $dd = 0, $amount = 1000, $status = 0) {
    global $pdo, $reqNo;
    $reqNo++;
    q('INSERT INTO tblfundrequisition (ReqNo,RequisitionDate,RequestType,DistrictId,GroupId,ChurchCategoryId,Purpose,AmountRequested,RequestedBy,DontDeduct,Status,Deleted,CongregationId)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,0,11)',
      [$reqNo, date('Y-m-d'), $type, $type === 'district' ? $ownerId : null, $type === 'group' ? $ownerId : null, $type === 'church' ? $ownerId : null,
       'test 8.9', $amount, $_SESSION['userId'], $dd, $status]);
    return (int)$pdo->lastInsertId();
}
$reqNo = 991000;
function appForm($id, $o = []) {
    return array_merge(['id' => $id, 'approved' => 1000.0, 'paydate' => date('Y-m-d'), 'reference' => 'T89-' . $id, 'paymethod' => 1, 'paidfrom' => 98, 'receiving' => 121,
                        'type' => 'group', 'groupid' => 0, 'dontdeduct' => false, 'reason' => 'x', 'group' => 'x'], $o);
}

$mm = new Mmfreceipt; $gc = new Groupcollection; $gf = new Groupfund; $ex = new Expense;
$G = 40; $FUNDS = 115; $BANK = 105; $D = 57; $SUB_G = 1; $SUB_D = 7;
$today = date('Y-m-d');

try {
    // =============================================================== STEP 0 (balances ignore deleted rows)
    q("INSERT INTO tblmmf (TransactionDate,Type,GroupId,Debit,SubAccountId,Narration,TransactionType,CongregationId,Deleted) VALUES (?,?,?,?,?,?,17,11,1)", [$today, 'group', $G, 777, $SUB_G, 'deleted row']);
    $b0 = (float)q('SELECT getmmfopeningbalbydate(?,?) b', [$G, $today])[0]['b'];
    $b1 = (float)q('SELECT SUM(Debit)-SUM(Credit) b FROM tblmmf WHERE GroupId=? AND Deleted=0 AND TransactionDate<=?', [$G, $today])[0]['b'];
    chk('step0 getmmfopeningbalbydate ignores deleted rows', abs($b0 - $b1) < 0.001, "$b0 vs $b1");
    q("INSERT INTO tblmmf (TransactionDate,Type,DistrictId,Debit,SubAccountId,Narration,TransactionType,CongregationId,Deleted) VALUES (?,?,?,?,?,?,17,11,1)", [$today, 'district', $D, 555, $SUB_D, 'deleted row']);
    $d0 = (float)q('SELECT getmmfopeningbalbydate_district(?,?) b', [$D, $today])[0]['b'];
    $d1 = (float)q('SELECT SUM(Debit)-SUM(Credit) b FROM tblmmf WHERE DistrictId=? AND Deleted=0 AND TransactionDate<=?', [$D, $today])[0]['b'];
    chk('step0 district balance ignores deleted rows', abs($d0 - $d1) < 0.001, "$d0 vs $d1");

    // =============================================================== STEP 1 MMF receipts
    $base = ['isedit' => false, 'id' => 0, 'tdate' => $today, 'groupid' => $G, 'amount' => '100', 'bank' => $BANK, 'fundsheld' => $FUNDS, 'reference' => 'T89MMF1'];
    $r = $mm->CreateUpdate($base);
    chk('mmf create ok', $r['success'] === true, errs($r));
    $m1 = (int)($r['id'] ?? 0);
    chk('mmf tblmmf row: type 11, Credit 100, group, bank, lowercased ref', ($row = mmf($m1)) && (int)$row['TransactionType'] === 11 && (float)$row['Credit'] === 100.0
        && (int)$row['GroupId'] === $G && (int)$row['BankId'] === $BANK && $row['Reference'] === 't89mmf1' && (int)$row['Deleted'] === 0);
    $e = entry(11, $m1);
    chk('mmf entry Dr funds held / Cr bank', count($e) === 2 && (int)$e[0]['accountId'] === $FUNDS && $e[0]['dc'] === 'DEBIT' && (float)$e[0]['amount'] === 100.0
        && (int)$e[1]['accountId'] === $BANK && $e[1]['dc'] === 'CREDIT', json_encode($e));
    $b = brows(11, $m1);
    chk('mmf one bank row: Withdrawal (2), credit 100, bank 105', count($b) === 1 && (int)$b[0]['transactionMethod'] === 2 && (float)$b[0]['credit'] === 100.0 && (int)$b[0]['bankId'] === $BANK, json_encode($b));

    // edit in place: balance excludes the record's own row
    $bal = $mm->GetBalance($G, $today, $m1);
    $r = $mm->CreateUpdate(array_merge($base, ['isedit' => true, 'id' => $m1, 'amount' => (string)$bal]));
    chk('mmf edit to the full balance (own row excluded) ok', $r['success'] === true, errs($r));
    $r = $mm->CreateUpdate(array_merge($base, ['isedit' => true, 'id' => $m1, 'amount' => (string)($bal + 0.01)]));
    chk('mmf edit above the balance refused', $r['success'] === false && has($r, 'available amount'), errs($r));
    $r = $mm->CreateUpdate(array_merge($base, ['isedit' => true, 'id' => $m1, 'amount' => '250', 'reference' => 'T89MMF1B']));
    chk('mmf edit ok (amount, reference)', $r['success'] === true, errs($r));
    chk('mmf edit: still one active entry, replaced in place', activeEntries(11, $m1) === 1 && (float)entry(11, $m1)[0]['amount'] === 250.0);
    $b = brows(11, $m1);
    chk('mmf edit: one active bank row, 250', count($b) === 1 && (float)$b[0]['credit'] === 250.0, json_encode($b));

    $bad = [
        'amount 0' => ['amount' => '0'], 'amount negative' => ['amount' => '-100'], 'amount text' => ['amount' => 'abc'], 'amount 3 decimals' => ['amount' => '1.005'],
        'amount above balance' => ['amount' => '999999999'], 'reference empty' => ['reference' => ' '], 'reference 26 chars' => ['reference' => str_repeat('x', 26)],
        'reference duplicate' => ['reference' => 'T89MMF1B'], 'future date' => ['tdate' => date('Y-m-d', strtotime('+1 day'))], 'bad date' => ['tdate' => 'abc'],
        'group of nobody' => ['groupid' => 99999999], 'bank = cash account' => ['bank' => 34], 'bank = non-existent' => ['bank' => 99999999],
        'funds held = bank' => ['fundsheld' => $BANK], 'funds held = cash holding' => ['fundsheld' => 121], 'funds held = inactive (116)' => ['fundsheld' => 116],
        'funds held = reporting parent 25' => ['fundsheld' => 25], 'funds held empty' => ['fundsheld' => 0],
    ];
    foreach ($bad as $label => $o) {
        $r = $mm->CreateUpdate(array_merge($base, ['reference' => 'T89MMFX'], $o));
        chk("mmf validation: $label", $r['success'] === false && $r['errors'], errs($r));
    }
    chk('mmf validation: nothing extra written', (int)q("SELECT COUNT(*) c FROM tblmmf WHERE TransactionType=11 AND ID>?", [$max['tblmmf']])[0]['c'] === 1);

    // load / update / void by ID + type + congregation
    $t17 = (int)q('SELECT ID FROM tblmmf WHERE TransactionType=17 AND Deleted=0 AND CongregationId=11 LIMIT 1')[0]['ID'];
    $t12 = (int)q('SELECT ID FROM tblmmf WHERE TransactionType=12 AND CongregationId=11 LIMIT 1')[0]['ID'];
    $t1 = (int)q('SELECT ID FROM tblmmf WHERE TransactionType=1 AND Deleted=0 AND CongregationId=11 LIMIT 1')[0]['ID'];
    $snapOther = md5(json_encode(q('SELECT * FROM tblmmf WHERE ID IN (?,?,?)', [$t17, $t12, $t1])));
    foreach ([$t17 => 'type 17', $t12 => 'type 12', $t1 => 'type 1'] as $oid => $label) {
        chk("mmf GetMmf($label row) = none", !$mm->GetMmf($oid));
        $r = $mm->CreateUpdate(array_merge($base, ['isedit' => true, 'id' => $oid, 'reference' => 'T89MMFY']));
        chk("mmf edit of a $label row refused", $r['success'] === false && has($r, 'not found'), errs($r));
        $r = $mm->Delete($oid);
        chk("mmf delete of a $label row refused", $r['success'] === false && has($r, 'not found'), errs($r));
    }
    chk('mmf other rows untouched', md5(json_encode(q('SELECT * FROM tblmmf WHERE ID IN (?,?,?)', [$t17, $t12, $t1]))) === $snapOther);
    $_SESSION['congId'] = 12;
    $r = $mm->Delete($m1);
    $_SESSION['congId'] = 11;
    chk('mmf delete from another congregation refused', $r['success'] === false && (int)mmf($m1)['Deleted'] === 0);

    // closed year
    $r = $mm->CreateUpdate(array_merge($base, ['tdate' => '2025-01-15', 'reference' => 'T89MMFC']));
    chk('mmf create in a closed year refused', $r['success'] === false && has($r, 'closed'), errs($r));

    // a cleared (reconciled) bank row: no void, no change of bank / amount; date and reference may change
    q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE transactionType=11 AND transactionId=? AND deleted=0', [$today, $m1]);
    $r = $mm->Delete($m1);
    chk('mmf delete refused: bank row cleared', $r['success'] === false && has($r, 'cleared') && (int)mmf($m1)['Deleted'] === 0 && activeEntries(11, $m1) === 1, errs($r));
    $r = $mm->CreateUpdate(array_merge($base, ['isedit' => true, 'id' => $m1, 'amount' => '251', 'reference' => 'T89MMF1B']));
    chk('mmf edit of the amount refused: bank row cleared', $r['success'] === false && has($r, 'cleared') && (float)entry(11, $m1)[0]['amount'] === 250.0, errs($r));
    $r = $mm->CreateUpdate(array_merge($base, ['isedit' => true, 'id' => $m1, 'amount' => '250', 'reference' => 'T89MMF1C']));
    chk('mmf edit of the reference only ok (cleared row kept)', $r['success'] === true && count(brows(11, $m1)) === 1 && (int)brows(11, $m1)[0]['cleared'] === 1, errs($r));
    q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE transactionType=11 AND transactionId=?', [$m1]);

    // void
    $r = $mm->Delete($m1);
    chk('mmf delete (void) ok', $r['success'] === true, errs($r));
    chk('mmf void: tblmmf.Deleted = 1, row kept', ($row = mmf($m1)) && (int)$row['Deleted'] === 1);
    chk('mmf void: entry voided, none active', activeEntries(11, $m1) === 0 && voided(11, $m1) === 1);
    chk('mmf void: bank rows voided', count(brows(11, $m1)) === 0 && count(brows(11, $m1, false)) >= 1);
    $names = array_map(function ($x) { return (int)$x->ID; }, $mm->GetMMFs());
    chk('mmf index hides the deleted receipt', !in_array($m1, $names, true));
    $r = $mm->Delete($m1);
    chk('mmf second delete refused', $r['success'] === false && has($r, 'not found'));
    chk('mmf balance after void ignores the receipt', abs($mm->GetBalance($G, $today) - $b1) < 0.001 + 0, ($mm->GetBalance($G, $today)) . " vs $b1");
    $r = $mm->CreateUpdate(array_merge($base, ['reference' => 'T89MMF1']));
    chk('mmf reference of a deleted receipt can be reused', $r['success'] === true, errs($r));
    $m2 = (int)$r['id'];

    // =============================================================== STEP 2 group collections
    $cb = ['isedit' => false, 'id' => null, 'tdate' => $today, 'type' => 'group', 'groupid' => $G, 'amount' => '300', 'narration' => 'test collection', 'subaccount' => $SUB_G];
    $r = $gc->CreateUpdate(array_merge($cb, ['account' => 'cash at bank', 'accountid' => 3, 'bankid' => 195]));  // client extras are ignored
    chk('collection create ok (client account / bank fields ignored)', $r['success'] === true, errs($r));
    $c1 = (int)($r['id'] ?? 0);
    $row = mmf($c1);
    chk('collection tblmmf row: type 17, Debit 300, group 40, sub 1, no district', $row && (int)$row['TransactionType'] === 17 && (float)$row['Debit'] === 300.0
        && (int)$row['GroupId'] === $G && $row['DistrictId'] === null && (int)$row['SubAccountId'] === $SUB_G && $row['Type'] === 'group');
    $e = entry(17, $c1);
    chk('collection entry Dr sub-account bank (105) / Cr sub-account account (125)', count($e) === 2 && (int)$e[0]['accountId'] === 105 && $e[0]['dc'] === 'DEBIT'
        && (int)$e[1]['accountId'] === 125 && $e[1]['dc'] === 'CREDIT', json_encode($e));
    $b = brows(17, $c1);
    chk('collection one bank row: Deposit (1), debit 300, bank 105 (not the tampered 195)', count($b) === 1 && (int)$b[0]['transactionMethod'] === 1 && (float)$b[0]['debit'] === 300.0 && (int)$b[0]['bankId'] === 105, json_encode($b));
    chk('collection does not write tblbanktransactions_subaccounts', (int)q('SELECT COUNT(*) c FROM tblbanktransactions_subaccounts WHERE TransactionType=17')[0]['c'] === 0);

    $r = $gc->CreateUpdate(array_merge($cb, ['isedit' => true, 'id' => $c1, 'amount' => '350', 'narration' => 'edited']));
    chk('collection edit ok', $r['success'] === true, errs($r));
    $e = entry(17, $c1);
    chk('collection edit: entry replaced in place (350)', activeEntries(17, $c1) === 1 && (float)$e[0]['amount'] === 350.0);
    chk('collection edit: one bank row, 350', count($b = brows(17, $c1)) === 1 && (float)$b[0]['debit'] === 350.0);

    // district collection, then edit it (the old Update wrote the district ID into GroupId)
    $r = $gc->CreateUpdate(array_merge($cb, ['type' => 'district', 'groupid' => $D, 'subaccount' => $SUB_D, 'narration' => 'district one']));
    chk('district collection create ok', $r['success'] === true, errs($r));
    $c2 = (int)$r['id'];
    $row = mmf($c2);
    chk('district collection: DistrictId set, GroupId null', $row['Type'] === 'district' && (int)$row['DistrictId'] === $D && $row['GroupId'] === null, json_encode($row));
    chk('district collection credits the district sub-account account (176)', (int)entry(17, $c2)[1]['accountId'] === 176);
    $r = $gc->CreateUpdate(array_merge($cb, ['isedit' => true, 'id' => $c2, 'type' => 'district', 'groupid' => $D, 'subaccount' => $SUB_D, 'amount' => '400', 'narration' => 'district edited']));
    $row = mmf($c2);
    chk('district edit keeps Type + DistrictId, GroupId stays NULL', $r['success'] === true && $row['Type'] === 'district' && (int)$row['DistrictId'] === $D && $row['GroupId'] === null && (float)$row['Debit'] === 400.0, errs($r) . json_encode($row));
    $r = $gc->CreateUpdate(array_merge($cb, ['isedit' => true, 'id' => $c2, 'type' => 'group', 'groupid' => $G, 'subaccount' => $SUB_G, 'amount' => '400', 'narration' => 'now a group one']));
    $row = mmf($c2);
    chk('district -> group edit writes Type, GroupId and clears DistrictId', $r['success'] === true && $row['Type'] === 'group' && (int)$row['GroupId'] === $G && $row['DistrictId'] === null, errs($r) . json_encode($row));

    $badc = [
        'amount 0' => ['amount' => '0'], 'amount text' => ['amount' => 'abc'], 'amount 3 decimals' => ['amount' => '1.005'], 'narration empty' => ['narration' => ' '],
        'narration 151 chars' => ['narration' => str_repeat('x', 151)], 'bad type' => ['type' => 'church'], 'bad date' => ['tdate' => 'x'],
        'group of nobody' => ['groupid' => 99999999], 'sub account of another group' => ['subaccount' => $SUB_D], 'sub account missing' => ['subaccount' => 99999999],
        'sub account of the district under type group' => ['type' => 'district'],
    ];
    foreach ($badc as $label => $o) {
        $r = $gc->CreateUpdate(array_merge($cb, $o));
        chk("collection validation: $label", $r['success'] === false && $r['errors'], errs($r));
    }
    // sub account whose account is inactive / missing / whose bank is not a bank
    q("INSERT INTO tblbanksubaccounts (AccountName,BankId,AccountId,GroupDistrict,GroupId,Deleted) VALUES ('t89 inactive account',105,205,'group',?,0)", [$G]); $sInactive = (int)$pdo->lastInsertId();
    q("INSERT INTO tblbanksubaccounts (AccountName,BankId,AccountId,GroupDistrict,GroupId,Deleted) VALUES ('t89 no account',105,0,'group',?,0)", [$G]); $sNoAcc = (int)$pdo->lastInsertId();
    q("INSERT INTO tblbanksubaccounts (AccountName,BankId,AccountId,GroupDistrict,GroupId,Deleted) VALUES ('t89 not a bank',34,125,'group',?,0)", [$G]); $sNoBank = (int)$pdo->lastInsertId();
    q("INSERT INTO tblbanksubaccounts (AccountName,BankId,AccountId,GroupDistrict,GroupId,Deleted) VALUES ('t89 no bank',0,125,'group',?,0)", [$G]); $sBank0 = (int)$pdo->lastInsertId();
    foreach (['inactive account' => $sInactive, 'missing account' => $sNoAcc, 'bank is not a bank' => $sNoBank, 'missing bank' => $sBank0] as $label => $sid) {
        $r = $gc->CreateUpdate(array_merge($cb, ['subaccount' => $sid]));
        chk("collection refused: sub account with $label", $r['success'] === false && $r['errors'], errs($r));
    }
    chk('collection validation: nothing extra written', (int)q('SELECT COUNT(*) c FROM tblmmf WHERE TransactionType=17 AND ID>? AND Deleted=0', [$max['tblmmf']])[0]['c'] === 2);

    // ownership / type
    foreach ([$t12 => 'type 12', $t1 => 'type 1'] as $oid => $label) {
        $r = $gc->CreateUpdate(array_merge($cb, ['isedit' => true, 'id' => $oid]));
        chk("collection edit of a $label row refused", $r['success'] === false && has($r, 'not found'), errs($r));
        $r = $gc->Delete($oid);
        chk("collection delete of a $label row refused", $r['success'] === false && has($r, 'not found'), errs($r));
    }
    chk('collection GetCollection of an MMF receipt = none', !$gc->GetCollection($m2));
    $_SESSION['congId'] = 12;
    $r = $gc->Delete($c1);
    $x = $gc->GetSubAccounts('group', $G);
    $_SESSION['congId'] = 11;
    chk('collection delete / sub accounts from another congregation refused', $r['success'] === false && count($x) === 0);

    // closed years
    $r = $gc->CreateUpdate(array_merge($cb, ['tdate' => '2024-01-15']));
    chk('collection create in a closed year refused', $r['success'] === false && has($r, 'closed'), errs($r));
    $closedC = (int)q("SELECT m.ID FROM tblmmf m JOIN journal_entries e ON e.transactionTypeId=17 AND e.transactionId=m.ID AND e.deleted=0 WHERE m.TransactionType=17 AND m.Deleted=0 LIMIT 1")[0]['ID'];
    $r = $gc->Delete($closedC);
    chk('collection delete of a closed-year collection refused (its bank row is cleared, too)', $r['success'] === false && (has($r, 'closed') || has($r, 'cleared')) && (int)mmf($closedC)['Deleted'] === 0, errs($r));
    chk('collection closed-year delete left entry + bank row active', activeEntries(17, $closedC) === 1 && count(brows(17, $closedC)) === 1);

    // future date, cleared bank row
    $r = $gc->CreateUpdate(array_merge($cb, ['tdate' => date('Y-m-d', strtotime('+1 day'))]));
    chk('collection create with a future date refused', $r['success'] === false && has($r, 'future'), errs($r));
    $r = $gc->CreateUpdate(array_merge($cb, ['isedit' => true, 'id' => $c1, 'tdate' => date('Y-m-d', strtotime('+1 day')), 'amount' => '350']));
    chk('collection edit to a future date refused', $r['success'] === false && has($r, 'future') && (float)mmf($c1)['Debit'] === 350.0, errs($r));
    q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE transactionType=17 AND transactionId=? AND deleted=0', [$today, $c1]);
    $r = $gc->Delete($c1);
    chk('collection delete refused: bank row cleared', $r['success'] === false && has($r, 'cleared') && (int)mmf($c1)['Deleted'] === 0 && activeEntries(17, $c1) === 1, errs($r));
    $r = $gc->CreateUpdate(array_merge($cb, ['isedit' => true, 'id' => $c1, 'amount' => '351', 'narration' => 'edited']));
    chk('collection edit of the amount refused: bank row cleared', $r['success'] === false && has($r, 'cleared') && (float)mmf($c1)['Debit'] === 350.0, errs($r));
    $r = $gc->CreateUpdate(array_merge($cb, ['isedit' => true, 'id' => $c1, 'amount' => '350', 'narration' => 'edited again']));
    chk('collection edit of the description only ok (cleared row kept)', $r['success'] === true && (int)brows(17, $c1)[0]['cleared'] === 1, errs($r));
    q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE transactionType=17 AND transactionId=?', [$c1]);

    // void
    $r = $gc->Delete($c1);
    chk('collection delete (void) ok', $r['success'] === true, errs($r));
    chk('collection void: Deleted = 1, entry and bank row voided', (int)mmf($c1)['Deleted'] === 1 && activeEntries(17, $c1) === 0 && voided(17, $c1) === 1 && count(brows(17, $c1)) === 0);
    $names = array_map(function ($x) { return (int)$x->ID; }, $gc->GetTransactions());
    chk('collection index hides the voided collection', !in_array($c1, $names, true));
    $r = $gc->Delete($c1);
    chk('collection second delete refused', $r['success'] === false);

    // =============================================================== STEP 3 fund approvals
    // cash approval, 98 preselected style
    $f1 = newReq('group', $G, 0, 1000);
    $a = $gf->Approve(appForm($f1));
    chk('approve (cash, 98) ok', ok($a), json_encode($a));
    $e = entry(12, $f1);
    chk('approve entry Dr 121 / Cr 98', count($e) === 2 && (int)$e[0]['accountId'] === 121 && $e[0]['dc'] === 'DEBIT' && (int)$e[1]['accountId'] === 98 && $e[1]['dc'] === 'CREDIT' && (float)$e[0]['amount'] === 1000.0, json_encode($e));
    chk('approve (cash): no bank row', count(brows(12, $f1)) === 0);
    $m = mmf12($f1);
    chk('approve: tblmmf Credit row for the group', count($m) === 1 && (int)$m[0]['GroupId'] === $G && (float)$m[0]['Credit'] === 1000.0 && $m[0]['BankId'] === null, json_encode($m));
    chk('approve: requisitionStyle() = new', $ex->requisitionStyle($pdo, 11, $f1) === 'new');
    $b = $gf->Approve(appForm($f1));
    chk('double approval refused, nothing more posted', refused($b) && $b['final'] === true && count(entry(12, $f1)) === 2 && count(mmf12($f1)) === 1, json_encode($b));

    // bank approval
    $f2 = newReq('group', $G, 0, 500);
    $a = $gf->Approve(appForm($f2, ['approved' => 500.0, 'paymethod' => 4, 'paidfrom' => $BANK, 'reference' => 'CHQ' . $f2]));
    chk('approve (bank, 105) ok', ok($a), json_encode($a));
    $e = entry(12, $f2);
    chk('approve (bank) entry Dr 121 / Cr 105', count($e) === 2 && (int)$e[0]['accountId'] === 121 && (int)$e[1]['accountId'] === $BANK, json_encode($e));
    $b = brows(12, $f2);
    chk('approve (bank): one bank row, Withdrawal (2), credit 500, bank 105', count($b) === 1 && (int)$b[0]['transactionMethod'] === 2 && (float)$b[0]['credit'] === 500.0 && (int)$b[0]['bankId'] === $BANK, json_encode($b));
    chk('approve (bank): tblmmf BankId = 105', (int)mmf12($f2)[0]['BankId'] === $BANK);
    chk('approve (bank): requisitionStyle() = new', $ex->requisitionStyle($pdo, 11, $f2) === 'new');

    // account rules
    $f3 = newReq('group', $G, 0, 1000);
    $cases = [
        'paid from = 121' => ['paidfrom' => 121], 'cash method with a bank account' => ['paymethod' => 1, 'paidfrom' => $BANK],
        'bank method with a cash account' => ['paymethod' => 3, 'paidfrom' => 98], 'paid from = non-posting root 3' => ['paidfrom' => 3],
        'paid from missing' => ['paidfrom' => 0], 'paid from non-existent' => ['paidfrom' => 99999999], 'pay method invalid' => ['paymethod' => 99],
        'pay method missing' => ['paymethod' => 0], 'reference 26 chars' => ['reference' => str_repeat('x', 26)], 'reference empty' => ['reference' => ''],
        'pay date in a closed year' => ['paydate' => '2025-01-15'],
    ];
    foreach ($cases as $label => $o) {
        $r = $gf->Approve(appForm($f3, $o));
        chk("approve refused: $label", refused($r) && $r['final'] === false && !$pdo->inTransaction(), json_encode($r));
    }
    chk('approve refusals: still pending, nothing posted', (int)req($f3)['Status'] === 0 && count(entry(12, $f3)) === 0 && count(mmf12($f3)) === 0 && activeEntries(12, $f3) === 0);

    // DontDeduct (stored) and tampered form fields
    $f4 = newReq('group', $G, 1, 300);
    $a = $gf->Approve(appForm($f4, ['approved' => 300.0, 'dontdeduct' => false, 'groupid' => 41, 'type' => 'district']));
    chk('DontDeduct (stored) with a tampered form: ok, no tblmmf row, same 2 ledger lines', ok($a) && count(mmf12($f4)) === 0 && count(entry(12, $f4)) === 2 && (int)entry(12, $f4)[0]['accountId'] === 121);
    $f5 = newReq('group', $G, 0, 200);
    $a = $gf->Approve(appForm($f5, ['approved' => 200.0, 'dontdeduct' => true, 'groupid' => 41, 'type' => 'district', 'reason' => 'tampered']));
    $m = mmf12($f5);
    chk('tampered groupid / type / dontdeduct ignored', ok($a) && count($m) === 1 && (int)$m[0]['GroupId'] === $G && $m[0]['DistrictId'] === null && $m[0]['Type'] === 'group');
    $f6 = newReq('district', $D, 0, 150);
    $a = $gf->Approve(appForm($f6, ['approved' => 150.0]));
    $m = mmf12($f6);
    chk('district requisition: tblmmf row for the district', ok($a) && count($m) === 1 && (int)$m[0]['DistrictId'] === $D && $m[0]['GroupId'] === null && $m[0]['Type'] === 'district');
    $catId = (int)q('SELECT id FROM tblchurchrequisitioncategories WHERE congregationId=11 AND deleted=0 LIMIT 1')[0]['id'];
    $f7 = newReq('church', $catId, 0, 120);
    $a = $gf->Approve(appForm($f7, ['approved' => 120.0]));
    chk('church requisition: approved, 2 ledger lines, NO tblmmf row', ok($a) && count(entry(12, $f7)) === 2 && count(mmf12($f7)) === 0, json_encode($a));
    chk('GetBalance: district uses the district ID; church = 0', abs((float)$gf->GetBalance($D, $today, 'district') - $d1 + 150) < 0.001 + 0 && (float)$gf->GetBalance(null, $today, 'church') === 0.0,
        $gf->GetBalance($D, $today, 'district') . " vs " . ($d1 - 150));
    chk('OwnerId: group / district / church', $gf->OwnerId(req($f1) ? (object)req($f1) : null) == $G && $gf->OwnerId((object)req($f6)) == $D && $gf->OwnerId((object)req($f7)) === null);

    // other congregation
    $f8 = newReq('group', $G, 0, 100);
    $_SESSION['congId'] = 12;
    $r = $gf->Approve(appForm($f8, ['approved' => 100.0]));
    $_SESSION['congId'] = 11;
    chk('approve from another congregation refused', refused($r) && $r['final'] === true && (int)req($f8)['Status'] === 0);

    // unapprove blocked by expenses (draft and approved), list of vouchers
    q('INSERT INTO tblexpenses (fiscalYearId,voucherNo,expenseType,expenseDate,accountId,groupId,paymethodId,PaidFromAccountId,amount,narration,status,requisitionId,postedBy,postedDate,deleted,congregationId)
       VALUES (12,909001,2,?,100,?,1,121,100,?,0,?,?,?,0,11)', [$today, $G, 'test 8.9 draft', $f1, $_SESSION['userId'], $today]);
    $x1 = (int)$pdo->lastInsertId();
    $u = $gf->Unapprove($f1);
    chk('unapprove blocked by a DRAFT expense, voucher listed', refused($u) && $u['final'] === true && strpos($u['error'], '909001') !== false && strpos($u['error'], 'draft') !== false, json_encode($u));
    chk('blocked unapprove: still approved, entry active, tblmmf row kept', (int)req($f1)['Status'] === 1 && count(entry(12, $f1)) === 2 && count(mmf12($f1)) === 1);
    q('UPDATE tblexpenses SET status=1 WHERE ID=?', [$x1]);
    $u = $gf->Reject($f1, 'test');
    chk('reject of an APPROVED requisition refused (pending only)', refused($u) && stripos($u['error'], 'pending') !== false && (int)req($f1)['Status'] === 1 && count(entry(12, $f1)) === 2, json_encode($u));
    $f10 = newReq('group', $G, 0, 100);
    q('INSERT INTO tblexpenses (fiscalYearId,voucherNo,expenseType,expenseDate,accountId,groupId,paymethodId,PaidFromAccountId,amount,narration,status,requisitionId,postedBy,postedDate,deleted,congregationId) VALUES (12,909002,2,?,100,?,1,121,50,?,0,?,?,?,0,11)', [$today, $G, 'test 8.9 draft on pending', $f10, $_SESSION['userId'], $today]);
    $u = $gf->Reject($f10, 'x');
    chk('reject of a pending requisition blocked by a draft expense, voucher listed', refused($u) && strpos($u['error'], '909002') !== false && (int)req($f10)['Status'] === 0, json_encode($u));
    q('UPDATE tblexpenses SET deleted=1 WHERE ID=?', [$x1]);

    // unapprove in a closed year (close the open year that covers today for a moment)
    $yid = (int)q('SELECT ID FROM tblfiscalyears WHERE ? BETWEEN startDate AND endDate AND deleted=0 LIMIT 1', [$today])[0]['ID'];
    setYear($yid, 1, 0);
    $u = $gf->Unapprove($f1);
    setYear($yid, 0, 0);
    chk('unapprove in a closed year refused (Ledger::void)', refused($u) && stripos($u['error'], 'closed') !== false, json_encode($u));
    chk('closed-year refusal rolled everything back', (int)req($f1)['Status'] === 1 && count(entry(12, $f1)) === 2 && count(mmf12($f1)) === 1);
    $real = q("SELECT r.ID FROM tblfundrequisition r WHERE r.Status=1 AND r.Deleted=0 AND r.CongregationId=11 AND NOT EXISTS (SELECT 1 FROM tblexpenses x WHERE x.requisitionId=r.ID AND x.deleted=0)
               AND EXISTS (SELECT 1 FROM journal_entries e WHERE e.transactionTypeId=12 AND e.transactionId=r.ID AND e.deleted=0 AND e.entryDate < '2025-01-01') ORDER BY r.ID LIMIT 1");
    if ($real) {
        $rid = (int)$real[0]['ID'];
        $before = md5(json_encode([req($rid), entry(12, $rid), mmf12($rid), brows(12, $rid)]));
        $u = $gf->Unapprove($rid);
        chk('unapprove of a real closed-year approval refused, nothing changed', refused($u) && stripos($u['error'], 'closed') !== false && md5(json_encode([req($rid), entry(12, $rid), mmf12($rid), brows(12, $rid)])) === $before, json_encode($u));
    }

    // unapprove (no expense), re-approve, reject, reverse
    $u = $gf->Unapprove($f2);
    chk('unapprove ok', ok($u), json_encode($u));
    chk('unapprove: pending again, entry voided (not deleted), bank row voided, tblmmf row removed',
        (int)req($f2)['Status'] === 0 && req($f2)['AmountApproved'] === null && activeEntries(12, $f2) === 0 && voided(12, $f2) === 1 && count(brows(12, $f2)) === 0 && count(brows(12, $f2, false)) === 1 && count(mmf12($f2)) === 0);
    $a = $gf->Approve(appForm($f2, ['approved' => 500.0, 'paymethod' => 4, 'paidfrom' => $BANK, 'reference' => 'CHQ' . $f2 . 'B']));
    chk('re-approval after unapprove ok: new active entry, old stays voided', ok($a) && activeEntries(12, $f2) === 1 && voided(12, $f2) === 1 && count(brows(12, $f2)) === 1);
    $u = $gf->Unapprove($f3);
    chk('unapprove of a pending requisition refused', refused($u) && $u['final'] === true);
    $u = $gf->Reject($f2, 'test reason');
    chk('reject of an approved requisition refused', refused($u) && (int)req($f2)['Status'] === 1 && activeEntries(12, $f2) === 1);
    // a cleared (reconciled) bank row blocks the unapprove with a clear message
    q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE transactionType=12 AND transactionId=? AND deleted=0', [$today, $f2]);
    $u = $gf->Unapprove($f2);
    chk('unapprove refused: bank row already cleared', refused($u) && stripos($u['error'], 'cleared') !== false && (int)req($f2)['Status'] === 1 && activeEntries(12, $f2) === 1 && count(brows(12, $f2)) === 1 && count(mmf12($f2)) === 1, json_encode($u));
    q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE transactionType=12 AND transactionId=?', [$f2]);
    $u = $gf->Unapprove($f2);
    chk('unapprove ok once the bank row is not cleared', ok($u) && (int)req($f2)['Status'] === 0);
    $u = $gf->Reject($f2, 'test reason');
    chk('reject of a pending requisition ok', ok($u) && (int)req($f2)['Status'] === 2 && req($f2)['ReasonForRejection'] === 'test reason');
    $u = $gf->Reject($f2, 'again');
    chk('reject of a rejected requisition refused', refused($u) && (int)req($f2)['Status'] === 2);
    $u = $gf->Reverse($f2);
    chk('reverse (rejected -> pending) ok', ok($u) && (int)req($f2)['Status'] === 0 && req($f2)['ReasonForRejection'] === null);
    $u = $gf->Reverse($f2);
    chk('reverse of a pending requisition refused', refused($u));
    $u = $gf->Reject($f3, 'pending one');
    chk('reject of a pending requisition ok', ok($u) && (int)req($f3)['Status'] === 2);
    $_SESSION['congId'] = 12;
    $u1 = $gf->Unapprove($f1); $u2 = $gf->Reject($f1, 'x'); $u3 = $gf->Reverse($f3);
    $_SESSION['congId'] = 11;
    chk('unapprove / reject / reverse from another congregation refused', refused($u1) && refused($u2) && refused($u3) && (int)req($f1)['Status'] === 1 && (int)req($f3)['Status'] === 2);

    // requisition edit / delete guards
    $f9 = newReq('group', $G, 0, 100);
    $ok = $gf->CreateUpdate(['isedit' => true, 'id' => $f1, 'reqdate' => $today, 'type' => 'group', 'group' => 41, 'reason' => 'hacked', 'amount' => 5, 'dontdeduct' => false]);
    chk('edit of an APPROVED requisition changes nothing', (int)req($f1)['GroupId'] === $G && req($f1)['Purpose'] === 'test 8.9' && (float)req($f1)['AmountRequested'] === 1000.0, json_encode(req($f1)));
    $_SESSION['congId'] = 12;
    $gf->Delete($f9);
    $_SESSION['congId'] = 11;
    chk('delete from another congregation changes nothing', (int)req($f9)['Deleted'] === 0);
    $gf->Delete($f1);
    chk('delete of an approved requisition changes nothing', (int)req($f1)['Deleted'] === 0);
    $gf->Delete($f9);
    chk('delete of a pending requisition of this congregation ok', (int)req($f9)['Deleted'] === 1);


    // =============================================================== follow-up 4: user-chosen RECEIVING (debit) account
    $E = new Expense;
    $entryDr = function ($id) { $e = entry(12, $id); return $e ? ['dr' => (int)$e[0]['accountId'], 'cr' => (int)$e[1]['accountId'], 'n' => count($e)] : null; };
    // default 121 (the preselected account)
    $q1 = newReq('group', $G, 0, 1000);
    $a = $gf->Approve(appForm($q1));
    chk('receiving: default 121 -> Dr 121 / Cr 98', ok($a) && $entryDr($q1) == ['dr' => 121, 'cr' => 98, 'n' => 2], json_encode($a));
    chk('receiving: requisitionReceiving() = new / 121', $E->requisitionReceiving($pdo, 11, $q1) == ['style' => 'new', 'account' => 121]);
    chk('receiving: ReceivingAccountOf() reads the entry', $gf->ReceivingAccountOf($q1) === 121);
    // another account
    $q2 = newReq('group', $G, 0, 1000);
    $a = $gf->Approve(appForm($q2, ['receiving' => 34]));
    chk('receiving: cash at hand (34) chosen -> Dr 34 / Cr 98', ok($a) && $entryDr($q2) == ['dr' => 34, 'cr' => 98, 'n' => 2], json_encode($a));
    chk('receiving: requisitionReceiving() = new / 34; ReceivingAccountOf = 34', $E->requisitionReceiving($pdo, 11, $q2) == ['style' => 'new', 'account' => 34] && $gf->ReceivingAccountOf($q2) === 34);
    $q3 = newReq('group', $G, 0, 1000);
    $a = $gf->Approve(appForm($q3, ['receiving' => 194, 'paidfrom' => 98, 'paymethod' => 1]));
    chk('receiving: petty cash development (194) chosen', ok($a) && $entryDr($q3)['dr'] === 194);
    $q4 = newReq('group', $G, 0, 800);
    $a = $gf->Approve(appForm($q4, ['approved' => 800.0, 'receiving' => 98, 'paymethod' => 4, 'paidfrom' => $BANK]));
    chk('receiving: petty cash (98) funded from a bank (105): Dr 98 / Cr 105 + bank row', ok($a) && $entryDr($q4) == ['dr' => 98, 'cr' => $BANK, 'n' => 2] && count(brows(12, $q4)) === 1);
    // refusals (the requisition stays pending, nothing is posted)
    q("INSERT INTO tblaccounttypes (accountType,accountTypeId,parentId,isSubCategory,isBank,active,congregationId,deleted) VALUES ('zz t89 inactive cash',3,22,1,0,0,0,0)"); $inactiveCash = (int)$pdo->lastInsertId();
    q("INSERT INTO tblaccounttypes (accountType,accountTypeId,parentId,isSubCategory,isBank,active,congregationId,deleted) VALUES ('zz t89 parent cash',3,22,0,0,1,0,0)"); $parentCash = (int)$pdo->lastInsertId();
    q("INSERT INTO tblaccounttypes (accountType,accountTypeId,parentId,isSubCategory,isBank,active,congregationId,deleted) VALUES ('zz t89 child of parent',3,?,1,0,1,0,0)", [$parentCash]);
    $rq = newReq('group', $G, 0, 1000);
    $bad = [
        'missing (null)' => ['receiving' => null], 'missing (empty)' => ['receiving' => ''], 'zero' => ['receiving' => 0], 'text' => ['receiving' => 'abc'],
        'negative' => ['receiving' => '-5'], 'digits with trailing text (121abc)' => ['receiving' => '121abc'], 'array' => ['receiving' => [121]],
        'non-existent account' => ['receiving' => 99999999], 'inactive cash account' => ['receiving' => $inactiveCash],
        'parent (reporting) account under 22: cash at bank 33' => ['receiving' => 33], 'a parent account of the test chart' => ['receiving' => $parentCash],
        'cash and cash equivalents 22 itself' => ['receiving' => 22], 'a root account' => ['receiving' => 3], 'a bank account (outside the cash list)' => ['receiving' => $BANK],
        'an expense account (outside the list)' => ['receiving' => 51], 'an income account' => ['receiving' => 93],
        'equal to the paid-from account (98 / 98)' => ['receiving' => 98, 'paidfrom' => 98],
        'paid from the cash holding account' => ['paidfrom' => 121, 'receiving' => 34],
    ];
    foreach ($bad as $label => $o) {
        $r = $gf->Approve(appForm($rq, $o));
        chk("receiving refused: $label", refused($r) && $r['final'] === false && !$pdo->inTransaction(), json_encode($r));
    }
    chk('receiving refusals: still pending, nothing posted', (int)req($rq)['Status'] === 0 && !q('SELECT 1 FROM journal_entries WHERE transactionTypeId=12 AND transactionId=?', [$rq]) && count(mmf12($rq)) === 0);
    $r = $gf->Approve(appForm($rq, ['receiving' => 121, 'paidfrom' => 98]));
    chk('receiving: a valid choice after the refusals is accepted', ok($r));
    $offered = array_map(function ($a) { return (int)$a->ID; }, $gf->GetReceivingAccounts());
    chk('receiving list: PAY_CASH accounts incl. 121, 98, 34; no bank, no parent, no inactive', in_array(121, $offered, true) && in_array(98, $offered, true) && in_array(34, $offered, true) && !array_intersect($offered, [$BANK, 33, 22, $inactiveCash, $parentCash]), json_encode($offered));

    // expenses: an expense of a NEW-style requisition is paid from the account its approval debited
    $acctG = (int)q('SELECT ID FROM tblaccounttypes WHERE ID IN (' . implode(',', array_map(function ($a) { return (int)$a->ID; }, $E->GetExpenseAccounts(2))) . ') AND accountTypeId=2 ORDER BY ID LIMIT 1')[0]['ID'];
    $ex = function ($req, $paidFrom, $o = []) use ($E, $acctG, $G, $today) {
        return $E->create($o + ['date' => $today, 'expensetype' => 2, 'account' => $acctG, 'costcentre' => $G, 'paymethod' => 1, 'paidfrom' => $paidFrom, 'reqid' => $req,
                                'amount' => '100.50', 'reference' => 'T89X', 'description' => 'receiving test', 'hasattachment' => 0, 'filename' => '']);
    };
    $r = $ex($q2, 121);
    chk('expense of a requisition funded into 34, paid from 121: refused, says to pay from that account', !$r['success'] && stripos(implode('|', $r['errors']), 'pay it from that account') !== false && stripos(implode('|', $r['errors']), 'cash at hand') !== false, json_encode($r));
    $r = $ex($q2, 98);
    chk('...paid from 98 (the approval credited 98): refused too', !$r['success'] && stripos(implode('|', $r['errors']), 'pay it from that account') !== false, json_encode($r));
    $r = $ex($q2, 34);
    chk('...paid from 34 (the account the approval debited): accepted', $r['success'], json_encode($r));
    $x34 = (int)($r['id'] ?? 0);
    $r = $E->approve($x34);
    chk('...and the expense can be approved (Dr expense / Cr 34)', $r['success'] && (int)entry(2, $x34)[1]['accountId'] === 34, json_encode($r));
    $r = $ex($q1, 34);
    chk('expense of a requisition funded into 121, paid from 34: refused', !$r['success'] && stripos(implode('|', $r['errors']), 'pay it from that account') !== false, json_encode($r));
    $r = $ex($q1, 121);
    chk('...paid from 121 (the default): accepted, as before', $r['success'], json_encode($r));
    $x121 = (int)($r['id'] ?? 0);
    $r = $ex($q4, 98);
    chk('expense of a requisition funded into 98 (from a bank): paid from 98 accepted', $r['success'], json_encode($r));
    $r = $ex(0, 121);
    chk('121 still pays only requisition expenses (no requisition: refused)', !$r['success'] && stripos(implode('|', $r['errors']), 'cash holding account can only pay') !== false, json_encode($r));
    $opts = $E->GetRequisitionOptions(2, $G, 0);
    $byId = []; foreach ($opts as $o) { $byId[$o->ID] = $o; }
    chk('requisition options carry style and account (34 for the cash-at-hand one, 121 for the default one)', isset($byId[$q2], $byId[$q1]) && $byId[$q2]->Style === 'new' && $byId[$q2]->Account === 34 && $byId[$q1]->Account === 121);

    // unapprove with an expense is blocked; re-approve with a DIFFERENT receiving account
    $u = $gf->Unapprove($q2);
    chk('unapprove blocked while an expense (paid from 34) exists, voucher listed', refused($u) && $u['final'] === true && stripos($u['error'], 'voucher no') !== false, json_encode($u));
    $E->unapprove($x34); $E->delete($x34);
    $u = $gf->Unapprove($q2);
    chk('unapprove ok once the expense is deleted', ok($u) && (int)req($q2)['Status'] === 0 && activeEntries(12, $q2) === 0);
    chk('requisitionReceiving() of an unapproved requisition: style null (cannot be classified)', $E->requisitionReceiving($pdo, 11, $q2) == ['style' => null, 'account' => null]);
    $a = $gf->Approve(appForm($q2, ['receiving' => 121]));
    chk('re-approve with ANOTHER receiving account (121): Dr 121, old entry stays voided', ok($a) && $entryDr($q2)['dr'] === 121 && voided(12, $q2) === 1 && $gf->ReceivingAccountOf($q2) === 121);
    $r = $ex($q2, 34);
    chk('...now an expense paid from 34 is refused', !$r['success'] && stripos(implode('|', $r['errors']), 'pay it from that account') !== false);
    $r = $ex($q2, 121);
    chk('...and from 121 accepted', $r['success'], json_encode($r));

    // OLD-style requisitions keep their rule: an approval that debited a funds-held account (no cash debit)
    $old = newReq('group', $G, 0, 1000, 1);
    q('UPDATE tblfundrequisition SET AmountApproved=1000, ApprovalDate=? WHERE ID=?', [$today, $old]);
    $pdo->beginTransaction();
    Ledger::post($pdo, ['congregationId' => 11, 'entryDate' => $today, 'transactionTypeId' => 12, 'transactionId' => $old, 'reference' => 'old', 'description' => 'old style'], [Ledger::debit(115, '1000.00'), Ledger::credit(34, '1000.00')]);
    $pdo->commit();
    chk('old-style requisition (Dr funds held 115): style old, no account', $E->requisitionReceiving($pdo, 11, $old) == ['style' => 'old', 'account' => null]);
    foreach ([98, 34, 121] as $pf) {
        $r = $ex($old, $pf, ['reference' => "T89O$pf"]);
        chk("old-style requisition: an expense paid from $pf is accepted (rule unchanged)", $r['success'], json_encode($r));
    }
    // every real approval is classified exactly as before (new = debit on 121 in the existing data; old = no cash debit)
    $before = q("SELECT e.transactionId AS rid, MAX(l.accountId = 121 AND l.dc = 'DEBIT') AS to121 FROM journal_entries e JOIN journal_lines l ON l.journalEntryId = e.ID
                 WHERE e.transactionTypeId = 12 AND e.deleted = 0 AND e.transactionId <= ? GROUP BY e.transactionId", [$max['tblfundrequisition']]);
    $diff = 0; $accDiff = 0;
    foreach ($before as $b) {
        $rec = $E->requisitionReceiving($pdo, 11, (int)$b['rid']);
        if ($rec['style'] !== ((int)$b['to121'] ? 'new' : 'old')) { $diff++; }
        if ((int)$b['to121'] && $rec['account'] !== 121) { $accDiff++; }
    }
    chk('existing approvals (' . count($before) . '): style identical to the old "debit on 121" rule, and every new one reports account 121', $diff === 0 && $accDiff === 0 && count($before) > 150, "diff $diff acc $accDiff n " . count($before));

    // church requisitions are stored with DontDeduct = 1 whatever is posted
    $catId2 = (int)q('SELECT id FROM tblchurchrequisitioncategories WHERE congregationId=11 AND deleted=0 LIMIT 1')[0]['id'];
    $ok = $gf->CreateUpdate(['isedit' => false, 'id' => '', 'reqdate' => $today, 'type' => 'church', 'group' => $catId2, 'reason' => 'church dd', 'amount' => 50.0, 'dontdeduct' => false]);
    $cid2 = (int)q('SELECT MAX(ID) m FROM tblfundrequisition')[0]['m'];
    chk('church requisition: DontDeduct stored as 1 although false was posted', $ok === true && (int)req($cid2)['DontDeduct'] === 1 && req($cid2)['RequestType'] === 'church');
    $ok = $gf->CreateUpdate(['isedit' => true, 'id' => $cid2, 'reqdate' => $today, 'type' => 'church', 'group' => $catId2, 'reason' => 'church dd edited', 'amount' => 60.0, 'dontdeduct' => false]);
    chk('...and still 1 after an edit', $ok === true && (int)req($cid2)['DontDeduct'] === 1);
    $ok = $gf->CreateUpdate(['isedit' => false, 'id' => '', 'reqdate' => $today, 'type' => 'group', 'group' => $G, 'reason' => 'group dd', 'amount' => 50.0, 'dontdeduct' => false]);
    $gid2 = (int)q('SELECT MAX(ID) m FROM tblfundrequisition')[0]['m'];
    chk('group requisition keeps what was posted (DontDeduct 0)', $ok === true && (int)req($gid2)['DontDeduct'] === 0);

    // requisition ownership: the group / district / category must belong to the congregation and match the type
    q("INSERT INTO tblchurchrequisitioncategories (categoryName,congregationId,deleted) VALUES ('t89 foreign category',2,0)"); $foreignCat = (int)$pdo->lastInsertId();
    $ownCat = (int)q('SELECT id FROM tblchurchrequisitioncategories WHERE congregationId=11 AND deleted=0 LIMIT 1')[0]['id'];
    $foreignGroup = (int)q('SELECT ID FROM tblgroups WHERE congregationId<>11 AND deleted=0 LIMIT 1')[0]['ID'];
    $foreignDistrict = (int)q('SELECT ID FROM tbldistricts WHERE congregationId NOT IN (0,11) AND deleted=0 LIMIT 1')[0]['ID'];
    $nreq = function () { return (int)q('SELECT COUNT(*) c FROM tblfundrequisition')[0]['c']; };
    $rq = function ($o = []) { return $o + ['isedit' => false, 'id' => '', 'reqdate' => date('Y-m-d'), 'type' => 'group', 'group' => 40, 'reason' => 'ownership test', 'amount' => 100.0, 'dontdeduct' => false]; };
    $n0 = $nreq();
    chk('requisition: group of another congregation refused', $gf->CreateUpdate($rq(['group' => $foreignGroup])) === false && $nreq() === $n0);
    chk('requisition: district of another congregation refused', $gf->CreateUpdate($rq(['type' => 'district', 'group' => $foreignDistrict])) === false && $nreq() === $n0);
    chk('requisition: church category of another congregation refused', $gf->CreateUpdate($rq(['type' => 'church', 'group' => $foreignCat])) === false && $nreq() === $n0);
    chk('requisition: the district (shared, congregation 0) is refused too', $gf->CreateUpdate($rq(['type' => 'district', 'group' => (int)q('SELECT ID FROM tbldistricts WHERE congregationId=0 LIMIT 1')[0]['ID']])) === false);
    chk('requisition: type does not match the owner (a group ID that does not exist as a district of ours)', $gf->CreateUpdate($rq(['type' => 'district', 'group' => $foreignGroup])) === false && $nreq() === $n0);
    chk('requisition: unknown type / non-existent owner refused', $gf->CreateUpdate($rq(['type' => 'bogus'])) === false && $gf->CreateUpdate($rq(['group' => 99999999])) === false && $gf->OwnerProblem('group', 0) !== null);
    chk('requisition: own group created', $gf->CreateUpdate($rq()) === true && $nreq() === $n0 + 1);
    $rid = (int)q('SELECT MAX(ID) m FROM tblfundrequisition')[0]['m'];
    chk('requisition: own district created', $gf->CreateUpdate($rq(['type' => 'district', 'group' => $D])) === true);
    chk('requisition: own church category created', $gf->CreateUpdate($rq(['type' => 'church', 'group' => $ownCat])) === true);
    $cr1 = req($rid);
    chk('requisition: stored owner columns match the type', (int)$cr1['GroupId'] === 40 && $cr1['DistrictId'] === null && $cr1['ChurchCategoryId'] === null);
    // edit: ownership checked again, RequestedBy stays
    $by = (int)$cr1['RequestedBy'];
    $otherUser = (int)q('SELECT ID FROM tblusers WHERE ID<>? ORDER BY ID LIMIT 1', [$by])[0]['ID'];
    $_SESSION['userId'] = $otherUser;
    $okEdit = $gf->CreateUpdate($rq(['isedit' => true, 'id' => $rid, 'group' => 41, 'reason' => 'edited by someone else', 'amount' => 250.0]));
    $_SESSION['userId'] = $by;
    $cr2 = req($rid);
    chk('requisition edit by another user: saved, group and amount changed', $okEdit === true && (int)$cr2['GroupId'] === 41 && (float)$cr2['AmountRequested'] === 250.0);
    chk('requisition edit keeps the original RequestedBy', (int)$cr2['RequestedBy'] === $by && $otherUser !== $by, $cr2['RequestedBy'] . ' vs ' . $by);
    chk('requisition edit to a foreign group refused, nothing changed', $gf->CreateUpdate($rq(['isedit' => true, 'id' => $rid, 'group' => $foreignGroup])) === false && (int)req($rid)['GroupId'] === 41);
    chk('requisition edit changing the type to district with a group ID refused', $gf->CreateUpdate($rq(['isedit' => true, 'id' => $rid, 'type' => 'district', 'group' => 41])) === false && req($rid)['DistrictId'] === null);
    $_SESSION['congId'] = 12;
    chk('requisition edit from another congregation refused', $gf->CreateUpdate($rq(['isedit' => true, 'id' => $rid, 'group' => 40])) === false);
    $_SESSION['congId'] = 11;
    chk('requisition edit of a missing id refused', $gf->CreateUpdate($rq(['isedit' => true, 'id' => 99999999])) === false);
} catch (Throwable $e) {
    $fail++;
    echo 'FAIL exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
    foreach ($yearSnapshot as $y) { setYear($y['ID'], $y['closed'], $y['deleted']); }
    // child rows first
    $pdo->exec('DELETE FROM journal_lines WHERE ID > ' . $max['journal_lines'] . ' OR journalEntryId > ' . $max['journal_entries']);
    foreach ($tables as $t => $k) { if ($t !== 'journal_lines') { $pdo->exec("DELETE FROM $t WHERE $k > {$max[$t]}"); } }
    $reqAfter = md5(json_encode($pdo->query('SELECT * FROM tblfundrequisition WHERE ID <= ' . $max['tblfundrequisition'] . ' ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC)));
    $mmfAfter = md5(json_encode($pdo->query('SELECT * FROM tblmmf WHERE ID <= ' . $max['tblmmf'] . ' ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC)));
    chk('cleanup: existing requisitions untouched', $reqSnap === $reqAfter);
    chk('cleanup: existing tblmmf rows untouched', $mmfSnap === $mmfAfter);
    chk('cleanup: group balances as before', $balBefore === json_encode($pdo->query('SELECT g.ID, getmmfopeningbalbydate(g.ID,CURDATE()) b FROM tblgroups g ORDER BY g.ID')->fetchAll(PDO::FETCH_ASSOC)));
    foreach ($tables as $t => $k) { chk("cleanup: $t back to max id {$max[$t]}", (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn() === $max[$t]); }
    $yearsNow = $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC);
    chk('cleanup: fiscal years restored', $yearsNow == $yearSnapshot);
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

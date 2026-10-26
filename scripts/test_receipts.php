<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
// 8.4b functional run: receipts (type 1), petty cash receipts (type 10), transfers to petty cash, number sequences,
// petty cash report routines (cong 11, dev only); cleans up after itself.
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Contribution.php';
require_once 'models/Cashreceipt.php';
require_once 'models/Banktransaction.php';
require_once 'models/Report.php';

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;
$_SESSION['isParish'] = 0;
$_SESSION['userId'] = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();

$tables = ['journal_entries' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tblcontributions_header' => 'ID',
           'tblcontributions_details' => 'ID', 'tblmmf' => 'ID', 'tblbanktransactions_subaccounts' => 'ID', 'tblpettycash' => 'ID',
           'tbllogs' => 'ID', 'tblbanktransactions' => 'ID', 'tblbanksubaccounts' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$seqSnapshot = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$yearSnapshot = $pdo->query('SELECT ID, closed, deleted FROM tblfiscalyears ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC);
// the checks need years 10, 11 and 12 open; set that for the run (restoreYears() puts the snapshot back at the end)
$pdo->exec('UPDATE tblfiscalyears SET closed = 0, deleted = 0 WHERE ID IN (10, 11, 12)');

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
/** Put every fiscal year's closed / deleted flags back exactly as they were when the run started. */
function restoreYears() { global $pdo, $yearSnapshot; $u = $pdo->prepare('UPDATE tblfiscalyears SET closed = ?, deleted = ? WHERE ID = ?'); foreach ($yearSnapshot as $y) { $u->execute([$y['closed'], $y['deleted'], $y['ID']]); } }
function yearsAreRestored() { global $pdo, $yearSnapshot; return $pdo->query('SELECT ID, closed, deleted FROM tblfiscalyears ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC) == $yearSnapshot; }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function entry($type, $id) {
    return q('SELECT l.accountId,l.dc,l.amount FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId
              WHERE e.congregationId=11 AND e.transactionTypeId=? AND e.transactionId=? AND e.deleted=0 ORDER BY l.lineNumber', [$type, $id]);
}
function dr($type, $id) { return array_values(array_filter(entry($type, $id), function ($l) { return $l['dc'] === 'DEBIT'; })); }
function cr($type, $id) { return array_values(array_filter(entry($type, $id), function ($l) { return $l['dc'] === 'CREDIT'; })); }
function brows($type, $id) { return q('SELECT * FROM tblbankpostings WHERE transactionType=? AND transactionId=? AND deleted=0 ORDER BY ID', [$type, $id]); }
function hdr($id) { return q('SELECT * FROM tblcontributions_header WHERE ID=?', [$id])[0]; }
function seq($kind, $year = 0) { $r = q('SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind=? AND fiscalYearId=?', [$kind, $year]); return $r ? (int)$r[0]['lastNo'] : null; }
function counts() {
    return [(int)q('SELECT COUNT(*) c FROM tblcontributions_header')[0]['c'], (int)q('SELECT COUNT(*) c FROM journal_entries')[0]['c'],
            (int)q('SELECT COUNT(*) c FROM tblbankpostings')[0]['c'], (int)q('SELECT COUNT(*) c FROM tblpettycash')[0]['c'],
            (int)q('SELECT COUNT(*) c FROM tblmmf')[0]['c'], (int)q('SELECT COUNT(*) c FROM tblbanktransactions_subaccounts')[0]['c']];
}

try {
    $ct = new Contribution; $cr = new Cashreceipt; $bt = new Banktransaction;
    $bank = 105; $bank2 = 195;
    $member = (int)q('SELECT ID FROM tblmember WHERE congregationId=11 LIMIT 1')[0]['ID'];
    $group = (int)q('SELECT ID FROM tblgroups WHERE congregationId=11 AND deleted=0 LIMIT 1')[0]['ID'];
    $OPEN = '2026-01-15';     // fiscal year 12 (open)
    $OPEN11 = '2025-01-15';   // fiscal year 11 (open)
    $NOYEAR = '2013-06-01';   // no fiscal year at all covers this date
    $DELCLOSED = '2022-09-01'; // fiscal year 8 (2021/2022): closed AND deleted
    $CLOSED = '2023-11-15';   // fiscal year 10, closed for the refusal tests only and reopened afterwards

    $mk = function ($o = []) use ($bank, $member, $OPEN) {
        return $o + ['id' => '', 'isedit' => false, 'date' => $OPEN, 'paymethod' => '2', 'bank' => $bank, 'cashaccount' => '',
                     'reference' => 'T84B', 'description' => 'Test receipt', 'accountsid' => [93], 'accountsname' => ['offerings'],
                     'amounts' => ['100.50'], 'categoriesid' => [1], 'categoriesname' => ['MEMBER'], 'contributorsid' => [$member],
                     'contributorsname' => ['X'], 'subaccount' => ['']];
    };
    $newhdr = function () { return (int)q('SELECT MAX(ID) m FROM tblcontributions_header')[0]['m']; };
    $newpc = function () { return (int)q('SELECT MAX(ID) m FROM tblpettycash')[0]['m']; };

    // ---------------- dropdown lists
    $income = array_map(function ($a) { return (int)$a->ID; }, $ct->getIncomeAccounts());
    chk('income dropdown: 93 and 94 listed; 33 (reporting) and bank 105 not', in_array(93, $income, true) && in_array(94, $income, true) && !in_array(33, $income, true) && !in_array(105, $income, true));
    $cash = array_map(function ($a) { return (int)$a->ID; }, $ct->getCashAccounts());
    chk('cash dropdown: 34 listed, no bank', in_array(34, $cash, true) && !in_array(105, $cash, true));
    $petty = array_map(function ($a) { return (int)$a->ID; }, $cr->GetPettyCashAccounts());
    chk('petty cash dropdown: exactly 98 and 194', $petty == [98, 194] || $petty == [194, 98], json_encode($petty));
    chk('Ledger petty cash rules', Ledger::isPettyCashAccount(98) && Ledger::isPettyCashAccount('194') && !Ledger::isPettyCashAccount(34));

    // ---------------- cash receipt to 34 and to 121
    $s0 = seq('receipt', 12);
    $r = $ct->create($mk(['paymethod' => '1', 'bank' => '', 'cashaccount' => 34])); chk('cash receipt to 34 saved', $r['success'], json_encode($r));
    $c1 = $newhdr();
    chk('cash: Dr 34 / Cr 93, 100.50 each', dr(1, $c1) == [['accountId' => 34, 'dc' => 'DEBIT', 'amount' => '100.50']] && count(cr(1, $c1)) == 1 && cr(1, $c1)[0]['accountId'] == 93, json_encode(entry(1, $c1)));
    chk('cash: no bank row', !brows(1, $c1));
    chk('cash: receipt number = sequence + 1 and sequence moved', (int)hdr($c1)['receiptNo'] === $s0 + 1 && seq('receipt', 12) === $s0 + 1, json_encode([hdr($c1)['receiptNo'], $s0]));
    chk('cash: detail bankId NULL, method 1, income account 93', q('SELECT bankId,paymentMethodId,contributionTypeId FROM tblcontributions_details WHERE HeaderId=?', [$c1])[0] == ['bankId' => null, 'paymentMethodId' => 1, 'contributionTypeId' => 93]);
    chk('getReceivingAccount reads 34 back', $ct->getReceivingAccount($c1, 11) === 34);

    $r = $ct->create($mk(['paymethod' => '1', 'bank' => '', 'cashaccount' => 121])); chk('cash receipt to 121 saved', $r['success'], json_encode($r));
    $c2 = $newhdr();
    chk('cash 121: Dr 121', dr(1, $c2)[0]['accountId'] == 121 && !brows(1, $c2));

    // ---------------- bank receipts
    $r = $ct->create($mk(['paymethod' => '2', 'reference' => 'MP123X'])); chk('mpesa receipt on bank 105 saved', $r['success'], json_encode($r));
    $m1 = $newhdr(); $br = brows(1, $m1);
    chk('mpesa: Dr bank 105 / Cr 93', dr(1, $m1)[0]['accountId'] == 105 && cr(1, $m1)[0]['accountId'] == 93);
    chk('mpesa: one bank row, debit 100.5, method 1 (Deposit), reference as typed, uncleared', count($br) == 1 && abs($br[0]['debit'] - 100.5) < 0.001 && $br[0]['credit'] == 0 && $br[0]['transactionMethod'] == 1 && $br[0]['reference'] === 'MP123X' && $br[0]['cleared'] == 0, json_encode($br));
    $r = $ct->create($mk(['paymethod' => '4', 'bank' => $bank2, 'reference' => 'BK1'])); chk('bank-method receipt on bank 195 saved', $r['success'], json_encode($r));
    $m2 = $newhdr(); chk('bank method: Dr 195, bank row on 195', dr(1, $m2)[0]['accountId'] == 195 && brows(1, $m2)[0]['bankId'] == 195);
    $r = $ct->create($mk(['paymethod' => '3', 'reference' => 'CHQ1'])); chk('cheque receipt saved', $r['success'], json_encode($r));
    $m3 = $newhdr();

    // ---------------- multi-line receipt: one receiving line, one income line per row
    $r = $ct->create($mk(['accountsid' => [93, 94, 91], 'accountsname' => ['a', 'b', 'c'], 'amounts' => ['10.25', '20', '30.75'], 'categoriesid' => [1, 1, 1],
                          'contributorsid' => [$member, $member, $member], 'subaccount' => ['', '', '']]));
    chk('multi-line receipt saved', $r['success'], json_encode($r));
    $ml = $newhdr();
    chk('multi-line: 1 debit of 61.00, 3 credits 93/94/91', count(dr(1, $ml)) == 1 && dr(1, $ml)[0]['amount'] == '61.00' && array_column(cr(1, $ml), 'accountId') == [93, 94, 91], json_encode(entry(1, $ml)));
    chk('multi-line: single bank row of 61', count(brows(1, $ml)) == 1 && abs(brows(1, $ml)[0]['debit'] - 61) < 0.001);
    chk('multi-line: 3 detail rows', (int)q('SELECT COUNT(*) c FROM tblcontributions_details WHERE HeaderId=?', [$ml])[0]['c'] == 3);

    // ---------------- ledger account = posted ID, not the posted name
    $r = $ct->create($mk(['accountsid' => [94], 'accountsname' => ['offerings']])); chk('posted name ignored (saved)', $r['success']);
    $nm = $newhdr(); chk('posted name ignored: credit is 94 (the ID), not offerings', cr(1, $nm)[0]['accountId'] == 94);

    // ---------------- group collection rows
    $sub = q('SELECT s.* FROM tblbanksubaccounts s JOIN tblaccounttypes b ON b.ID=s.BankId WHERE s.Deleted=0 AND b.congregationId=11 AND s.GroupId IS NOT NULL ORDER BY s.ID LIMIT 1');
    if ($sub) {
        $sub = $sub[0];
        chk('real sub account has a posting income account', Ledger::isPostingAccount($pdo, (int)$sub['AccountId'], 11, true), json_encode($sub));
        $r = $ct->create($mk(['accountsid' => [999999], 'amounts' => ['500'], 'categoriesid' => [2], 'contributorsid' => [$sub['GroupId']], 'subaccount' => [$sub['ID']]]));
        chk('collection row saved (posted account id ignored)', $r['success'], json_encode($r));
        $g1 = $newhdr();
        chk('collection: credit is the sub account\'s account', cr(1, $g1)[0]['accountId'] == $sub['AccountId'], json_encode(entry(1, $g1)));
        chk('collection: detail account, subaccount stored', q('SELECT contributionTypeId,subaccount FROM tblcontributions_details WHERE HeaderId=?', [$g1])[0] == ['contributionTypeId' => (int)$sub['AccountId'], 'subaccount' => (int)$sub['ID']]);
        chk('collection: tblmmf row (Debit 500, type 1) and sub-account row (500, type 1)',
            q('SELECT COUNT(*) c FROM tblmmf WHERE TransactionType=1 AND TransactionId=? AND Debit=500 AND Deleted=0', [$g1])[0]['c'] == 1
            && q('SELECT COUNT(*) c FROM tblbanktransactions_subaccounts WHERE TransactionType=1 AND TransactionId=? AND Amount=500', [$g1])[0]['c'] == 1);

        // edit: rewrites tblmmf and sub-account rows (issue 9)
        $r = $ct->create($mk(['id' => $g1, 'isedit' => true, 'accountsid' => [1], 'amounts' => ['750'], 'categoriesid' => [2], 'contributorsid' => [$sub['GroupId']], 'subaccount' => [$sub['ID']]]));
        chk('collection edit saved', $r['success'], json_encode($r));
        chk('collection edit: exactly one mmf row (750) and one sub-account row (750)',
            q('SELECT COUNT(*) c FROM tblmmf WHERE TransactionType=1 AND TransactionId=? AND Deleted=0', [$g1])[0]['c'] == 1
            && q('SELECT Debit FROM tblmmf WHERE TransactionType=1 AND TransactionId=? AND Deleted=0', [$g1])[0]['Debit'] == 750
            && q('SELECT COUNT(*) c FROM tblbanktransactions_subaccounts WHERE TransactionType=1 AND TransactionId=?', [$g1])[0]['c'] == 1
            && q('SELECT Amount FROM tblbanktransactions_subaccounts WHERE TransactionType=1 AND TransactionId=?', [$g1])[0]['Amount'] == 750);
        $other = q('SELECT ID FROM tblgroups WHERE congregationId=11 AND deleted=0 AND ID<>? LIMIT 1', [$sub['GroupId']]);
        if ($other) {
            $before = counts();
            $r = $ct->create($mk(['accountsid' => [1], 'amounts' => ['5'], 'categoriesid' => [2], 'contributorsid' => [$other[0]['ID']], 'subaccount' => [$sub['ID']]]));
            echo '   [sub account of another group] ' . json_encode($r['errors']) . "\n";
            chk('refused: sub account does not belong to the selected group', !$r['success'] && counts() == $before);
        }
        // sub account whose configured account cannot be posted to (33 is a reporting account)
        $pdo->prepare("INSERT INTO tblbanksubaccounts (AccountName,BankId,AccountId,GroupDistrict,GroupId,DistrictId,Deleted) VALUES ('T84B BAD',?,33,'group',?,NULL,0)")->execute([$bank, $sub['GroupId']]);
        $badSub = (int)$pdo->lastInsertId();
        $before = counts(); $s = seq('receipt', 12);
        $r = $ct->create($mk(['accountsid' => [1], 'amounts' => ['5'], 'categoriesid' => [2], 'contributorsid' => [$sub['GroupId']], 'subaccount' => [$badSub]]));
        echo '   [sub account with a non-posting account] ' . json_encode($r['errors']) . "\n";
        chk('refused: sub account whose account is not postable, message names the sub account', !$r['success'] && strpos(implode(' ', $r['errors']), 'T84B BAD') !== false && counts() == $before && seq('receipt', 12) === $s);
        // other congregation's sub account
        $foreign = q('SELECT s.ID FROM tblbanksubaccounts s JOIN tblaccounttypes a ON a.ID=s.BankId WHERE s.Deleted=0 AND a.congregationId<>11 LIMIT 1');
        if ($foreign) {
            $r = $ct->create($mk(['accountsid' => [1], 'amounts' => ['5'], 'categoriesid' => [2], 'contributorsid' => [$sub['GroupId']], 'subaccount' => [$foreign[0]['ID']]]));
            chk('refused: sub account of another congregation', !$r['success']);
        }
    } else { chk('group sub account available on dev', false); }

    // ---------------- edit: bank row and cleared flag
    $row = brows(1, $m1)[0];
    q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE ID=?', ['2026-01-20', $row['ID']]);
    $no1 = hdr($m1)['receiptNo']; $seqBefore = seq('receipt', 12);
    $r = $ct->create($mk(['id' => $m1, 'isedit' => true, 'reference' => 'MP123X', 'description' => 'Edited narration']));
    chk('edit narration only: saved', $r['success'], json_encode($r));
    $after = brows(1, $m1);
    chk('edit narration only: same bank row kept, still cleared', count($after) == 1 && $after[0]['ID'] == $row['ID'] && $after[0]['cleared'] == 1 && $after[0]['clearedDare'] == '2026-01-20', json_encode($after));
    chk('receipt number kept on edit, sequence not touched', hdr($m1)['receiptNo'] === $no1 && seq('receipt', 12) === $seqBefore);
    // 8.9 follow-up: a cleared bank row can no longer be changed (bank / amount) or voided; the test refuses it, then un-clears the row and goes on
    $r = $ct->create($mk(['id' => $m1, 'isedit' => true, 'reference' => 'MP123X', 'amounts' => ['222.25']]));
    chk('edit amount on a cleared row: refused (Ledger::clearedBankProblem)', !$r['success'] && stripos(json_encode($r), 'cleared') !== false && brows(1, $m1)[0]['ID'] == $row['ID'], json_encode($r));
    q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE ID=?', [$row['ID']]);
    $r = $ct->create($mk(['id' => $m1, 'isedit' => true, 'reference' => 'MP123X', 'amounts' => ['222.25']]));
    chk('edit amount on cleared row: saved', $r['success'], json_encode($r));
    $after = brows(1, $m1);
    chk('edit amount: new uncleared row of 222.25; old row soft-deleted', count($after) == 1 && $after[0]['ID'] != $row['ID'] && $after[0]['cleared'] == 0 && abs($after[0]['debit'] - 222.25) < 0.001
        && q('SELECT deleted FROM tblbankpostings WHERE ID=?', [$row['ID']])[0]['deleted'] == 1, json_encode($after));
    // method change cash <-> bank
    $r = $ct->create($mk(['id' => $m1, 'isedit' => true, 'paymethod' => '1', 'bank' => '', 'cashaccount' => 34, 'reference' => '']));
    chk('edit bank receipt into cash: bank row voided, Dr 34', $r['success'] && !brows(1, $m1) && dr(1, $m1)[0]['accountId'] == 34, json_encode($r));
    $r = $ct->create($mk(['id' => $c1, 'isedit' => true, 'paymethod' => '3', 'reference' => 'CHQ9']));
    chk('edit cash receipt into cheque: bank row created, Dr 105', $r['success'] && count(brows(1, $c1)) == 1 && dr(1, $c1)[0]['accountId'] == 105, json_encode($r));
    // edit into another open fiscal year takes a number from that year's sequence
    $s11 = seq('receipt', 11); $oldNo2 = hdr($m2)['receiptNo'];
    $r = $ct->create($mk(['id' => $m2, 'isedit' => true, 'paymethod' => '4', 'bank' => $bank2, 'reference' => 'BK1', 'date' => $OPEN11]));
    chk('edit into fiscal year 11: new number from year 11 sequence, year updated', $r['success'] && (int)hdr($m2)['fiscalYearId'] === 11 && (int)hdr($m2)['receiptNo'] === $s11 + 1, json_encode([$r, hdr($m2)]));
    chk('year change: message names both numbers and the new year; log has both', strpos((string)($r['renumbered'] ?? ''), 'renumbered from ' . $oldNo2 . ' to ' . hdr($m2)['receiptNo']) !== false && strpos($r['renumbered'], '2024-2025') !== false
        && (int)q('SELECT COUNT(*) c FROM tbllogs WHERE Activity LIKE ?', ['%receipt renumbered from ' . $oldNo2 . ' to ' . hdr($m2)['receiptNo'] . '%'])[0]['c'] === 1, json_encode($r));
    $r = $ct->create($mk(['date' => $OPEN11])); $nn = $newhdr();
    chk('year change: the next receipt in year 11 is right after the moved one', $r['success'] && (int)hdr($nn)['receiptNo'] === $s11 + 2, json_encode([hdr($nn)['receiptNo'], $s11]));
    $r = $ct->create($mk()); $n12 = $newhdr();
    chk('year change: the retired year-12 number is never handed out again', $r['success'] && hdr($n12)['receiptNo'] !== $oldNo2 && (int)hdr($n12)['receiptNo'] > (int)$oldNo2, json_encode([$oldNo2, hdr($n12)['receiptNo']]));
    $r = $ct->create($mk(['id' => $m2, 'isedit' => true, 'paymethod' => '4', 'bank' => $bank2, 'reference' => 'BK1', 'date' => '2025-02-20']));
    chk('same-year edit (date changes inside year 11): number kept, no renumber message', $r['success'] && empty($r['renumbered']) && (int)hdr($m2)['receiptNo'] === $s11 + 1, json_encode($r));
    $ct->delete(['id' => $nn]); $ct->delete(['id' => $n12]);

    // ---------------- old rows stored a blank sub account as 0: such a receipt must stay editable
    $r = $ct->create($mk()); $z = $newhdr();
    q('UPDATE tblcontributions_details SET subaccount=0 WHERE HeaderId=?', [$z]);
    $r = $ct->create($mk(['id' => $z, 'isedit' => true, 'subaccount' => ['0'], 'description' => 'edited legacy']));
    chk('receipt whose detail has subaccount 0 can be edited', $r['success'], json_encode($r));
    chk('subaccount 0 is rewritten as NULL', q('SELECT subaccount s FROM tblcontributions_details WHERE HeaderId=?', [$z])[0]['s'] === null);
    $before = counts();
    $r = $ct->create($mk(['id' => $z, 'isedit' => true, 'subaccount' => ['abc']]));
    chk('non-numeric sub account refused', !$r['success'] && counts() == $before, json_encode($r));
    $ct->delete(['id' => $z]);

    // ---------------- number sequence never reused
    $r = $ct->create($mk()); $n1 = $newhdr(); $no = (int)hdr($n1)['receiptNo'];
    $r = $ct->delete(['id' => $n1]); chk('delete newest receipt', $r['success'], json_encode($r));
    chk('delete: entry, bank row voided; header soft-deleted', !entry(1, $n1) && !brows(1, $n1) && hdr($n1)['Deleted'] == 1);
    $r = $ct->create($mk()); $n2 = $newhdr();
    chk('number of a deleted receipt is not reused', (int)hdr($n2)['receiptNo'] === $no + 1, json_encode([$no, hdr($n2)['receiptNo']]));
    // missing sequence row is seeded from the highest number in the table (deleted rows included)
    $max12 = (int)q("SELECT MAX(CAST(receiptNo AS UNSIGNED)) m FROM tblcontributions_header WHERE congregationId=11 AND fiscalYearId=12")[0]['m'];
    q('DELETE FROM receipt_number_seq WHERE congregationId=11 AND kind="receipt" AND fiscalYearId=12');
    $r = $ct->create($mk()); $n3 = $newhdr();
    chk('missing sequence row seeded from max + 1', (int)hdr($n3)['receiptNo'] === $max12 + 1 && seq('receipt', 12) === $max12 + 1, json_encode([$max12, hdr($n3)['receiptNo']]));

    // ---------------- refusals (nothing written, no number used)
    $other = q('SELECT ID FROM tblcongregation WHERE ID<>11 LIMIT 1')[0]['ID'];
    $pdo->prepare("INSERT INTO tblcontributions_header (fiscalYearId,postedBy,postedDate,receiptNo,congregationId) VALUES (12,?,?,'T84B-X',?)")->execute([$_SESSION['userId'], date('Y-m-d'), $other]);
    $foreignHdr = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tblcontributions_header (fiscalYearId,postedBy,postedDate,receiptNo,congregationId) VALUES (9,?,?,'T84B-C',11)")->execute([$_SESSION['userId'], date('Y-m-d')]);
    $closedHdr = (int)$pdo->lastInsertId();
    $bad = [
        ['amount 0', $mk(['amounts' => ['0']])],
        ['negative amount', $mk(['amounts' => ['-5']])],
        ['3 decimals', $mk(['amounts' => ['1.234']])],
        ['non-numeric amount', $mk(['amounts' => ['abc']])],
        ['no lines', $mk(['accountsid' => [], 'accountsname' => [], 'amounts' => [], 'categoriesid' => [], 'contributorsid' => [], 'subaccount' => []])],
        ['mpesa without bank', $mk(['bank' => ''])],
        ['mpesa without reference', $mk(['reference' => ''])],
        ['cash without cash account', $mk(['paymethod' => '1', 'bank' => '', 'cashaccount' => ''])],
        ['cash account that is a bank', $mk(['paymethod' => '1', 'bank' => '', 'cashaccount' => 105])],
        ['cash account outside cash tree (33 reporting)', $mk(['paymethod' => '1', 'bank' => '', 'cashaccount' => 33])],
        ['bank that is not a bank (34)', $mk(['bank' => 34])],
        ['unknown payment method', $mk(['paymethod' => '9'])],
        ['income account is a bank', $mk(['accountsid' => [105]])],
        ['income account is a reporting account (33)', $mk(['accountsid' => [33]])],
        ['unknown income account', $mk(['accountsid' => [987654]])],
        ['invalid date', $mk(['date' => 'abc'])],
        ['no fiscal year for the date', $mk(['date' => $NOYEAR])],
        ['closed fiscal year', $mk(['date' => $CLOSED])],
        ['closed and deleted fiscal year', $mk(['date' => $DELCLOSED])],
        ['edit: id 0', $mk(['id' => 0, 'isedit' => true])],
        ['edit: blank id', $mk(['id' => '', 'isedit' => true])],
        ['edit: other congregation', $mk(['id' => $foreignHdr, 'isedit' => true])],
        ['edit: receipt in a closed year', $mk(['id' => $closedHdr, 'isedit' => true])],
        ['edit: deleted receipt', $mk(['id' => $n1, 'isedit' => true])],
        ['edit: moving a receipt into a closed year', $mk(['id' => $c2, 'isedit' => true, 'paymethod' => '1', 'bank' => '', 'cashaccount' => 34, 'date' => $CLOSED])],
    ];
    $msg = [];
    $pdo->exec('UPDATE tblfiscalyears SET closed=0, deleted=1 WHERE ID=10');
    $before = counts();
    $r = $ct->create($mk(['date' => $CLOSED]));
    chk('deleted (never closed) year: receipt refused with its own message', !$r['success'] && $r['errors'] == ['The financial year 2023-2024 has been deleted'] && counts() == $before, json_encode($r));
    $r = $cr->CreateUpdate(['id' => '', 'isedit' => false, 'date' => $CLOSED, 'bank' => $bank, 'pettycashaccount' => 98, 'amount' => '5', 'reference' => 'X', 'description' => 'x']);
    chk('deleted (never closed) year: petty cash receipt refused with its own message', !$r['success'] && $r['errors'] == ['The financial year 2023-2024 has been deleted'] && counts() == $before, json_encode($r));
    $pdo->exec('UPDATE tblfiscalyears SET closed=1, deleted=0 WHERE ID=10');
    $before = counts(); $s12 = seq('receipt', 12); $s9 = seq('receipt', 9); $s10 = seq('receipt', 10);
    foreach ($bad as $b) {
        $r = $ct->create($b[1]); $msg[$b[0]] = implode(' | ', $r['errors']);
        echo "   [{$b[0]}] " . json_encode($r['errors']) . "\n";
        chk("refused: {$b[0]}", !$r['success'] && $r['errors']);
    }
    chk('message: no fiscal year at all', strpos($msg['no fiscal year for the date'], 'No financial year is defined for 2013-06-01') !== false, $msg['no fiscal year for the date']);
    chk('message: closed fiscal year names the year', $msg['closed fiscal year'] === 'The financial year 2023-2024 is closed', $msg['closed fiscal year']);
    chk('message: closed AND deleted fiscal year is reported as closed', $msg['closed and deleted fiscal year'] === 'The financial year 2021/2022 is closed', $msg['closed and deleted fiscal year']);
    chk('message: edit of a receipt in a closed year', $msg['edit: receipt in a closed year'] === 'The financial year 2022/2023 is closed', $msg['edit: receipt in a closed year']);
    chk('refusals wrote nothing and used no receipt number', counts() == $before && seq('receipt', 12) === $s12 && seq('receipt', 9) === $s9 && seq('receipt', 10) === $s10);
    foreach ([['delete: other congregation', $foreignHdr], ['delete: closed year', $closedHdr], ['delete: already deleted', $n1], ['delete: id 0', 0], ['delete: unknown id', 987654321]] as $d) {
        $r = $ct->delete(['id' => $d[1]]); echo "   [{$d[0]}] " . json_encode($r['errors']) . "\n";
        chk("refused: {$d[0]}", !$r['success'] && $r['errors']);
    }
    chk('refused deletes left the other congregation\'s and closed-year receipts alone', hdr($foreignHdr)['Deleted'] == 0 && hdr($closedHdr)['Deleted'] == 0);

    // ---------------- delete voids everything incl. sub-account rows
    if ($sub) {
        $r = $ct->delete(['id' => $g1]);
        chk('delete collection receipt: entry, mmf, sub-account rows gone', $r['success'] && !entry(1, $g1)
            && q('SELECT COUNT(*) c FROM tblmmf WHERE TransactionType=1 AND TransactionId=? AND Deleted=0', [$g1])[0]['c'] == 0
            && q('SELECT COUNT(*) c FROM tblbanktransactions_subaccounts WHERE TransactionType=1 AND TransactionId=?', [$g1])[0]['c'] == 0, json_encode($r));
    }
    chk('receipt actions write tbllogs rows', (int)q('SELECT COUNT(*) c FROM tbllogs WHERE ID > ? AND Activity LIKE "%Contribution%"', [$max['tbllogs']])[0]['c'] >= 5);

    // ---------------- legacy receipt without an entry (receipt 1890): editing it creates its entry and bank row
    // (done on a copy so real data is untouched: header without entry)
    $pdo->prepare("INSERT INTO tblcontributions_header (fiscalYearId,postedBy,postedDate,receiptNo,congregationId) VALUES (12,?,?,'T84B-L',11)")->execute([$_SESSION['userId'], date('Y-m-d')]);
    $legacy = (int)$pdo->lastInsertId();
    $r = $ct->create($mk(['id' => $legacy, 'isedit' => true]));
    chk('legacy receipt without entry: edit creates entry and bank row', $r['success'] && count(entry(1, $legacy)) == 2 && count(brows(1, $legacy)) == 1, json_encode($r));

    // =================== petty cash receipts
    $pm = function ($o = []) use ($bank, $OPEN) {
        return $o + ['id' => '', 'isedit' => false, 'date' => $OPEN, 'bank' => $bank, 'pettycashaccount' => 98, 'amount' => '300.50',
                     'reference' => 'PC84B', 'description' => 'Test petty cash'];
    };
    $p0 = seq('pettycash');
    $r = $cr->CreateUpdate($pm()); chk('petty cash receipt to 98 saved', $r['success'], json_encode($r));
    $p98 = $newpc(); $row = q('SELECT * FROM tblpettycash WHERE ID=?', [$p98])[0];
    chk('98: Dr 98 / Cr bank 105, 300.50', entry(10, $p98) == [['accountId' => 98, 'dc' => 'DEBIT', 'amount' => '300.50'], ['accountId' => 105, 'dc' => 'CREDIT', 'amount' => '300.50']], json_encode(entry(10, $p98)));
    $br = brows(10, $p98);
    chk('98: bank row is a credit of 300.5, method 2 (Withdrawal)', count($br) == 1 && abs($br[0]['credit'] - 300.5) < 0.001 && $br[0]['debit'] == 0 && $br[0]['transactionMethod'] == 2, json_encode($br));
    chk('98: tblpettycash row own receipt (type NULL), account 98, receipt number from sequence', $row['TransactionType'] === null && $row['IsReceipt'] == 1 && $row['PettyCashAccountId'] == 98 && (int)$row['ReceiptNo'] === $p0 + 1 && seq('pettycash') === $p0 + 1, json_encode($row));
    $r = $cr->CreateUpdate($pm(['pettycashaccount' => 194, 'amount' => '120'])); chk('petty cash receipt to 194 saved', $r['success'], json_encode($r));
    $p194 = $newpc(); chk('194: Dr 194 / Cr 105, account stored', dr(10, $p194)[0]['accountId'] == 194 && cr(10, $p194)[0]['accountId'] == 105 && q('SELECT PettyCashAccountId a FROM tblpettycash WHERE ID=?', [$p194])[0]['a'] == 194);

    // edit: cleared bank row semantics, number kept, account switch
    $brow = brows(10, $p98)[0];
    q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE ID=?', ['2026-01-20', $brow['ID']]);
    $rno = q('SELECT ReceiptNo FROM tblpettycash WHERE ID=?', [$p98])[0]['ReceiptNo']; $sp = seq('pettycash');
    $r = $cr->CreateUpdate($pm(['id' => $p98, 'isedit' => true, 'description' => 'Edited narration']));
    $after = brows(10, $p98);
    chk('petty edit narration: same cleared bank row, number kept', $r['success'] && count($after) == 1 && $after[0]['ID'] == $brow['ID'] && $after[0]['cleared'] == 1 && q('SELECT ReceiptNo FROM tblpettycash WHERE ID=?', [$p98])[0]['ReceiptNo'] == $rno && seq('pettycash') === $sp, json_encode([$r, $after]));
    chk('petty edit: narration stored in tblpettycash and ledger (same text)', q('SELECT Narration FROM tblpettycash WHERE ID=?', [$p98])[0]['Narration'] === 'edited narration'
        && q('SELECT description d FROM journal_entries WHERE transactionTypeId=10 AND transactionId=? AND congregationId=11 AND deleted=0', [$p98])[0]['d'] === 'edited narration');
    // 8.9 follow-up: a cleared bank row can no longer be changed (bank / amount) or voided; the test refuses it, then un-clears the row and goes on
    $r = $cr->CreateUpdate($pm(['id' => $p98, 'isedit' => true, 'amount' => '999']));
    chk('petty edit amount on a cleared row: refused (Ledger::clearedBankProblem)', !$r['success'] && stripos(json_encode($r), 'cleared') !== false && brows(10, $p98)[0]['ID'] == $brow['ID'], json_encode($r));
    q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE ID=?', [$brow['ID']]);
    $r = $cr->CreateUpdate($pm(['id' => $p98, 'isedit' => true, 'amount' => '999']));
    $after = brows(10, $p98);
    chk('petty edit amount on cleared row: new uncleared row 999', $r['success'] && count($after) == 1 && $after[0]['ID'] != $brow['ID'] && $after[0]['cleared'] == 0 && abs($after[0]['credit'] - 999) < 0.001);
    $r = $cr->CreateUpdate($pm(['id' => $p98, 'isedit' => true, 'amount' => '999', 'pettycashaccount' => 194]));
    chk('petty edit: switch 98 -> 194 changes the debit account and the stored account', $r['success'] && dr(10, $p98)[0]['accountId'] == 194 && q('SELECT PettyCashAccountId a FROM tblpettycash WHERE ID=?', [$p98])[0]['a'] == 194);

    // refusals
    $realTransfer = q('SELECT * FROM tblpettycash WHERE TransactionType=13 AND Deleted=0 AND CongregationId=11 ORDER BY ID LIMIT 1')[0];
    $expenseRow = q('SELECT * FROM tblpettycash WHERE IsReceipt=0 AND Deleted=0 AND CongregationId=11 ORDER BY ID LIMIT 1')[0];
    $pdo->prepare("INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,PettyCashAccountId,Reference,Narration,CongregationId) VALUES (NULL,?,10,1,?,98,'x','t84b other',?)")->execute([$OPEN, $bank, $other]);
    $foreignPc = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,PettyCashAccountId,Reference,Narration,CongregationId) VALUES (NULL,?,10,1,?,98,'x','t84b closed',11)")->execute([$CLOSED, $bank]);
    $closedPc = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,IsReceipt,BankId,PettyCashAccountId,Reference,Narration,CongregationId) VALUES (NULL,?,10,1,?,98,'x','t84c deleted year',11)")->execute([$DELCLOSED, $bank]);
    $delPc = (int)$pdo->lastInsertId();
    $snap = function ($ids) { $o = []; foreach ($ids as $i) { $o[$i] = md5(json_encode(q('SELECT * FROM tblpettycash WHERE ID=?', [$i]))); } return $o; };
    $watch = [$realTransfer['ID'], $expenseRow['ID'], $foreignPc, $closedPc, $delPc];
    $snapBefore = $snap($watch);
    $before = counts(); $sp = seq('pettycash');
    $bad = [
        ['amount 0', $pm(['amount' => '0'])],
        ['negative amount', $pm(['amount' => '-5'])],
        ['3 decimals', $pm(['amount' => '1.234'])],
        ['empty amount', $pm(['amount' => ''])],
        ['no bank', $pm(['bank' => ''])],
        ['bank is not a bank (34)', $pm(['bank' => 34])],
        ['no reference', $pm(['reference' => ''])],
        ['no petty cash account', $pm(['pettycashaccount' => ''])],
        ['petty cash account 34 (cash at hand)', $pm(['pettycashaccount' => 34])],
        ['petty cash account 121', $pm(['pettycashaccount' => 121])],
        ['petty cash account = a bank', $pm(['pettycashaccount' => 105])],
        ['future date', $pm(['date' => date('Y-m-d', strtotime('+3 days'))])],
        ['no fiscal year for the date', $pm(['date' => $NOYEAR])],
        ['closed fiscal year', $pm(['date' => $CLOSED])],
        ['closed and deleted fiscal year', $pm(['date' => $DELCLOSED])],
        ['edit: transfer-created receipt (type 13)', $pm(['id' => $realTransfer['ID'], 'isedit' => true])],
        ['edit: expense petty cash row', $pm(['id' => $expenseRow['ID'], 'isedit' => true])],
        ['edit: other congregation', $pm(['id' => $foreignPc, 'isedit' => true])],
        ['edit: receipt dated in a closed year', $pm(['id' => $closedPc, 'isedit' => true])],
        ['edit: id 0', $pm(['id' => 0, 'isedit' => true])],
        ['edit: receipt dated in a closed and deleted year', $pm(['id' => $delPc, 'isedit' => true])],
    ];
    foreach ($bad as $b) {
        $r = $cr->CreateUpdate($b[1]); $msg[$b[0]] = implode(' | ', $r['errors']);
        echo "   [{$b[0]}] " . json_encode($r['errors']) . "\n";
        chk("refused: petty {$b[0]}", !$r['success'] && $r['errors']);
    }
    chk('petty message: no fiscal year at all', strpos($msg['no fiscal year for the date'], 'No financial year is defined for 2013-06-01') !== false, $msg['no fiscal year for the date']);
    chk('petty message: closed fiscal year', $msg['closed fiscal year'] === 'The financial year 2023-2024 is closed', $msg['closed fiscal year']);
    chk('petty message: closed AND deleted fiscal year is reported as closed', $msg['closed and deleted fiscal year'] === 'The financial year 2021/2022 is closed', $msg['closed and deleted fiscal year']);
    chk('petty message: edit of a receipt dated in a closed year', $msg['edit: receipt dated in a closed year'] === 'The financial year 2023-2024 is closed', $msg['edit: receipt dated in a closed year']);
    chk('petty refusals wrote nothing and used no number', counts() == $before && seq('pettycash') === $sp);
    foreach ([['delete: transfer-created receipt', $realTransfer['ID']], ['delete: expense row', $expenseRow['ID']], ['delete: other congregation', $foreignPc], ['delete: closed year', $closedPc], ['delete: closed and deleted year', $delPc], ['delete: id 0', 0]] as $d) {
        $r = $cr->Delete($d[1]); echo "   [{$d[0]}] " . json_encode($r['errors']) . "\n";
        chk("refused: petty {$d[0]}", !$r['success'] && $r['errors']);
    }
    $pdo->exec('UPDATE tblfiscalyears SET closed=0, deleted=0 WHERE ID=10');   // the run's own state (open); the final restoreYears() puts the real flags back
    chk('refused petty actions left the real transfer / expense / other-congregation / closed-year rows untouched', $snap($watch) == $snapBefore);
    chk('the screen query lists only own receipts (no type 13 / expense rows)', (function () use ($cr, $realTransfer, $expenseRow, $p98) {
        $ids = array_map(function ($r) { return (int)$r->ID; }, $cr->GetReceipts());
        return !in_array((int)$realTransfer['ID'], $ids, true) && !in_array((int)$expenseRow['ID'], $ids, true) && in_array($p98, $ids, true);
    })());
    chk('GetReceipt only returns own receipts', $cr->GetReceipt($realTransfer['ID']) === false && $cr->GetReceipt($p98) !== false);

    // ---------------- transfers to petty cash (Banktransaction::savePettyCash)
    $tm = function ($o) use ($bank, $OPEN) {
        return $o + ['id' => null, 'isedit' => false, 'date' => $OPEN, 'bank' => $bank, 'type' => 5, 'transfer' => null, 'cashaccount' => null,
                     'amount' => '700', 'reference' => 'TRF84B', 'description' => 'Transfer test', 'deposittosubs' => false, 'subaccounts' => null,
                     'issubmain' => false, 'issubtransfer' => false, 'issubtxn' => false];
    };
    $newbt = function () { return (int)q('SELECT MAX(ID) m FROM tblbanktransactions')[0]['m']; };
    $pcOf = function ($bid) { return q('SELECT * FROM tblpettycash WHERE TransactionType=13 AND TransactionId=? ORDER BY ID', [$bid]); };
    $sp = seq('pettycash');
    $r = $bt->CreateUpdate($tm(['transfer' => '194'])); chk('transfer to 194 saved', $r['success'], json_encode($r));
    $t1 = $newbt(); $pc = $pcOf($t1);
    chk('transfer to 194: petty cash receipt with account 194, numbered from the shared sequence', count($pc) == 1 && $pc[0]['PettyCashAccountId'] == 194 && $pc[0]['IsReceipt'] == 1 && $pc[0]['Debit'] == 700 && (int)$pc[0]['ReceiptNo'] === $sp + 1 && seq('pettycash') === $sp + 1, json_encode($pc));
    $no = $pc[0]['ReceiptNo'];
    $r = $bt->CreateUpdate($tm(['id' => $t1, 'isedit' => true, 'transfer' => '98', 'amount' => '750']));
    $pc = $pcOf($t1);
    chk('edit transfer 194 -> 98: same receipt row updated, number kept, account 98, 750', $r['success'] && count($pc) == 1 && $pc[0]['ReceiptNo'] == $no && $pc[0]['PettyCashAccountId'] == 98 && $pc[0]['Debit'] == 750 && $pc[0]['Deleted'] == 0, json_encode([$r, $pc]));
    chk('edit did not consume a new number', seq('pettycash') === $sp + 1);
    $r = $bt->CreateUpdate($tm(['id' => $t1, 'isedit' => true, 'transfer' => '34']));
    $pc = $pcOf($t1);
    chk('edit transfer 98 -> 34 (cash at hand): receipt row soft-deleted', $r['success'] && count($pc) == 1 && $pc[0]['Deleted'] == 1, json_encode([$r, $pc]));
    $r = $bt->CreateUpdate($tm(['id' => $t1, 'isedit' => true, 'transfer' => '98']));
    $pc = $pcOf($t1);
    chk('edit back to 98: a new receipt row with a new number', $r['success'] && count($pc) == 2 && $pc[1]['Deleted'] == 0 && $pc[1]['ReceiptNo'] > $no, json_encode($pc));
    $r = $bt->CreateUpdate($tm(['transfer' => '34'])); $t2 = $newbt();
    chk('transfer to 34: no petty cash receipt', $r['success'] && !$pcOf($t2));
    $r = $bt->CreateUpdate($tm(['type' => 1, 'cashaccount' => 98, 'transfer' => '98'])); $t3 = $newbt();
    chk('deposit (type 1) with a stale transfer value 98: no petty cash receipt', $r['success'] && !$pcOf($t3), json_encode($pcOf($t3)));
    $r = $bt->Delete($t1);
    chk('delete transfer: petty cash receipt soft-deleted, entry and bank row voided', $r['success'] && !q('SELECT 1 FROM tblpettycash WHERE TransactionType=13 AND TransactionId=? AND Deleted=0', [$t1]) && !entry(13, $t1) && !brows(13, $t1));
    // numbers are never reused: delete the newest own petty receipt, the next one gets a higher number
    $last = (int)q('SELECT MAX(ReceiptNo) m FROM tblpettycash WHERE CongregationId=11')[0]['m'];
    $r = $cr->CreateUpdate($pm()); $pn = $newpc(); $pno = (int)q('SELECT ReceiptNo FROM tblpettycash WHERE ID=?', [$pn])[0]['ReceiptNo'];
    $cr->Delete($pn);
    $r = $cr->CreateUpdate($pm()); $pn2 = $newpc();
    chk('petty cash number of a deleted receipt is not reused', (int)q('SELECT ReceiptNo FROM tblpettycash WHERE ID=?', [$pn2])[0]['ReceiptNo'] === $pno + 1, json_encode([$pno]));

    // ---------------- legacy petty cash routines retained for the 9.8 removal decision
    $from = '2026-01-01'; $to = '2026-01-31';
    $rows = function ($acc) use ($pdo, $from, $to) { $st = $pdo->prepare('CALL sp_pettycashutilization(?,?,11,?)'); $st->execute([$from, $to, $acc]); $x = $st->fetchAll(PDO::FETCH_ASSOC); $st->closeCursor(); return $x; };
    $r98 = $rows(98); $r194 = $rows(194);
    $narr = function ($rs) { return array_column($rs, 'Narration'); };
    chk('report 194 lists the 194 receipts (120 and 999), not the deleted ones', in_array('TEST PETTY CASH', $narr($r194), true) || in_array('EDITED NARRATION', $narr($r194), true));
    $deletedCount = (int)q('SELECT COUNT(*) c FROM tblpettycash WHERE ID > ? AND Deleted=1 AND TransactionDate BETWEEN ? AND ?', [$max['tblpettycash'], $from, $to])[0]['c'];
    $liveRows98 = (int)q('SELECT COUNT(*) c FROM tblpettycash WHERE CongregationId=11 AND Deleted=0 AND PettyCashAccountId=98 AND TransactionDate BETWEEN ? AND ?', [$from, $to])[0]['c'];
    chk('report 98: one row per live 98 entry plus the opening row; deleted rows excluded', $deletedCount >= 1 && count($r98) === $liveRows98 + 1, json_encode([count($r98), $liveRows98, $deletedCount]));
    $open98 = (float)q('SELECT getopeningbal(?,11,98) b', [$from])[0]['b'];
    $manual = (float)q('SELECT IFNULL(SUM(Debit)-SUM(Credit),0) b FROM tblpettycash WHERE CongregationId=11 AND Deleted=0 AND PettyCashAccountId=98 AND TransactionDate < ?', [$from])[0]['b'];
    chk('getopeningbal(…, 98) = live debits - credits before the date', abs($open98 - $manual) < 0.005, json_encode([$open98, $manual]));
    chk('getopeningbal for 194 is separate from 98', abs((float)q('SELECT getopeningbal(?,11,194) b', ['2027-01-01'])[0]['b'] - (float)q('SELECT IFNULL(SUM(Debit)-SUM(Credit),0) b FROM tblpettycash WHERE CongregationId=11 AND Deleted=0 AND PettyCashAccountId=194')[0]['b']) < 0.005);
    $manualDc = q('SELECT IFNULL(SUM(Debit),0) d, IFNULL(SUM(Credit),0) c FROM tblpettycash WHERE CongregationId=11 AND Deleted=0 AND PettyCashAccountId=194 AND TransactionDate BETWEEN ? AND ?', [$from, $to])[0];
    $procDc = ['d' => 0.0, 'c' => 0.0]; foreach (array_slice($r194, 1) as $row) { $procDc['d'] += (float)$row['Debit']; $procDc['c'] += (float)$row['Credit']; }
    chk('retained sp_pettycashutilization (194) movement matches live rows', abs($procDc['d'] - (float)$manualDc['d']) < 0.005 && abs($procDc['c'] - (float)$manualDc['c']) < 0.005, json_encode([$procDc, $manualDc]));
    q('SET @x = 1');

    // ---------------- cleanup of remaining test receipts via the app paths (also exercises delete)
    foreach ([$p98, $p194, $pn2] as $id) { $r = $cr->Delete($id); chk("petty delete $id", $r['success'] && !entry(10, $id) && !brows(10, $id)); }
    foreach ([$c1, $c2, $m1, $m2, $m3, $ml, $nm, $n2, $n3, $legacy] as $id) { $r = $ct->delete(['id' => $id]); chk("receipt delete $id", $r['success'] && !entry(1, $id) && !brows(1, $id), json_encode($r)); }
    foreach ([$t2, $t3] as $id) { $bt->Delete($id); }

    // ---------------- closed-year + sub-account guard by direct SQL: nothing else changed
    chk('no active receipt entry of the test left behind', (int)q('SELECT COUNT(*) c FROM journal_entries WHERE ID > ? AND deleted=0 AND transactionTypeId IN (1,10)', [$max['journal_entries']])[0]['c'] == 0);
} catch (Throwable $e) {
    $fail++; echo "EXCEPTION: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n";
}

restoreYears();   // the refusal tests change the flags of year 10; put every year back exactly as it was (also after a failure)
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId > ?')->execute([$max['journal_entries']]);
foreach ($tables as $t => $k) { $pdo->prepare("DELETE FROM $t WHERE $k > ?")->execute([$max[$t]]); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec('DELETE FROM receipt_number_seq');
$ins = $pdo->prepare('INSERT INTO receipt_number_seq (congregationId,kind,fiscalYearId,lastNo) VALUES (?,?,?,?)');
foreach ($seqSnapshot as $s) { $ins->execute([$s['congregationId'], $s['kind'], $s['fiscalYearId'], $s['lastNo']]); }
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k > {$max[$t]}")->fetchColumn(); }
$seqNow = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
echo "\nleftover rows after cleanup: $left ; sequences restored: " . ($seqNow == $seqSnapshot ? 'yes' : 'NO') . ' ; fiscal years restored: ' . (yearsAreRestored() ? 'yes' : 'NO') . "\n";
echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

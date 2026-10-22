<?php
// 8.5d functional run: expenses (type 2) on the Ledger library, congregation 11, dev only; cleans up after itself.
//   C:\xampp\php\php.exe scripts\test_expenses.php
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Expense.php';
require_once 'models/Journal.php';

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;
$_SESSION['isParish'] = 0;
$_SESSION['userType'] = 1;
$_SESSION['userId'] = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();

$tables = ['tblexpenses' => 'ID', 'journal_entries' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tblpettycash' => 'ID',
           'tbllogs' => 'ID', 'tblfundrequisition' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$seqSnapshot = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$yearSnapshot = $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC);

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function entry($id) {
    return q('SELECT l.accountId,l.dc,l.amount FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId
              WHERE e.congregationId=11 AND e.transactionTypeId=2 AND e.transactionId=? AND e.deleted=0 ORDER BY l.lineNumber', [$id]);
}
function entryRow($id) { $r = q('SELECT * FROM journal_entries WHERE congregationId=11 AND transactionTypeId=2 AND transactionId=? AND deleted=0', [$id]); return $r ? $r[0] : null; }
function brows($id) { return q('SELECT * FROM tblbankpostings WHERE transactionType=2 AND transactionId=? AND deleted=0 ORDER BY ID', [$id]); }
function prows($id, $active = true) { return q('SELECT * FROM tblpettycash WHERE ExpenseId=?' . ($active ? ' AND Deleted=0' : '') . ' ORDER BY ID', [$id]); }
function ex($id) { $r = q('SELECT * FROM tblexpenses WHERE ID=?', [$id]); return $r ? $r[0] : null; }
function seq($kind, $year) { $r = q('SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind=? AND fiscalYearId=?', [$kind, $year]); return $r ? (int)$r[0]['lastNo'] : null; }
function counts() {
    return [(int)q('SELECT COUNT(*) c FROM tblexpenses')[0]['c'], (int)q('SELECT COUNT(*) c FROM journal_entries')[0]['c'],
            (int)q('SELECT COUNT(*) c FROM tblbankpostings')[0]['c'], (int)q('SELECT COUNT(*) c FROM tblpettycash')[0]['c'],
            (int)q('SELECT COUNT(*) c FROM tblfundrequisition')[0]['c']];
}
function setYear($id, $closed, $deleted) { q('UPDATE tblfiscalyears SET closed=?, deleted=? WHERE ID=?', [$closed, $deleted, $id]); }
function snap($table, $key, $upto) { return md5(json_encode(q("SELECT * FROM $table WHERE $key <= ? ORDER BY $key", [$upto]))); }

$real = [];
foreach (['tblexpenses' => 'ID', 'journal_entries' => 'ID', 'tblpettycash' => 'ID', 'tblbankpostings' => 'ID', 'tblledger' => 'ID'] as $t => $k) { $real[$t] = snap($t, $k, $max[$t]); }
$real['journal_lines'] = snap('journal_lines', 'journalEntryId', $max['journal_entries']);

try {
    $E = new Expense;
    $OPEN = '2026-01-15';      // fiscal year 12 (open)
    $OPEN25 = '2026-10-10';    // fiscal year 25 (open)
    $CLOSED = '2023-11-15';    // fiscal year 10 (closed)
    $NOYEAR = '2013-06-01';
    $DELCLOSED = '2022-09-01'; // fiscal year 8: closed and deleted
    $other = (int)q('SELECT ID FROM tblcongregation WHERE ID<>11 LIMIT 1')[0]['ID'];

    $acctList = $E->GetExpenseAccounts(1);
    $acct1 = (int)q('SELECT ID FROM tblaccounttypes WHERE ID IN (' . implode(',', array_map(function ($a) { return (int)$a->ID; }, $acctList)) . ') AND accountTypeId=2 ORDER BY ID LIMIT 1')[0]['ID'];
    $gList = $E->GetExpenseAccounts(2);
    $gacct = (int)q('SELECT ID FROM tblaccounttypes WHERE ID IN (' . implode(',', array_map(function ($a) { return (int)$a->ID; }, $gList)) . ') AND accountTypeId=2 ORDER BY ID LIMIT 1')[0]['ID'];
    $groups = q('SELECT ID FROM tblgroups WHERE congregationId=11 AND deleted=0 AND active=1 ORDER BY ID LIMIT 2');
    $group = (int)$groups[0]['ID']; $group2 = (int)$groups[1]['ID'];
    $foreignGroup = q('SELECT ID FROM tblgroups WHERE congregationId<>11 AND deleted=0 LIMIT 1');

    $mk = function ($o = []) use ($acct1, $OPEN) {
        return $o + ['date' => $OPEN, 'expensetype' => 1, 'account' => $acct1, 'costcentre' => 0, 'paymethod' => 1, 'paidfrom' => 98, 'reqid' => '',
                     'amount' => '100.50', 'reference' => 'T85D', 'description' => 'Test expense', 'hasattachment' => 0, 'filename' => ''];
    };
    $newid = function () { return (int)q('SELECT MAX(ID) m FROM tblexpenses')[0]['m']; };
    $ids = function ($list) { $o = array_map(function ($a) { return (int)$a->ID; }, $list); sort($o); return $o; };
    $make = function ($o = []) use ($E, $mk, $newid) { $r = $E->create($mk($o)); if (!$r['success']) { echo '   [create failed] ' . json_encode($r) . "\n"; } return $r['success'] ? $r['id'] : 0; };
    $mkReq = function ($o) use ($pdo, $group) {
        $o += ['no' => 99001, 'type' => 'group', 'group' => $group, 'district' => null, 'approved' => 1000, 'status' => 1, 'deleted' => 0, 'cong' => 11];
        $pdo->prepare("INSERT INTO tblfundrequisition (ReqNo,RequisitionDate,RequestType,DistrictId,GroupId,Purpose,AmountRequested,AmountApproved,ApprovalDate,Status,DontDeduct,Deleted,CongregationId)
                       VALUES (?,?,?,?,?,'T85D test',?,?,?,?,0,?,?)")
            ->execute([$o['no'], '2026-01-10', $o['type'], $o['district'], $o['group'], $o['approved'], $o['approved'], '2026-01-12', $o['status'], $o['deleted'], $o['cong']]);
        return (int)$pdo->lastInsertId();
    };
    $approval = function ($reqId, $dr, $cr, $amount = '1000') use ($pdo) {
        $pdo->beginTransaction();
        Ledger::post($pdo, ['congregationId' => 11, 'entryDate' => '2026-01-12', 'transactionTypeId' => 12, 'transactionId' => $reqId, 'description' => 'T85D approval'],
                     [Ledger::debit($dr, $amount), Ledger::credit($cr, $amount)]);
        $pdo->commit();
    };

    // ================= dropdown lists = server-side rules
    $rm = new ReflectionMethod('Expense', 'accountProblem'); $rm->setAccessible(true);
    $allIds = array_map('intval', array_column(q('SELECT ID FROM tblaccounttypes'), 'ID'));
    foreach ([1, 2, 3] as $t) {
        $dd = $ids($E->GetExpenseAccounts($t));
        $acc = array_values(array_filter($allIds, function ($id) use ($rm, $E, $pdo, $t) { return $rm->invoke($E, $pdo, 11, $id, $t, false) === null; }));
        sort($acc);
        chk("expense accounts type $t: the dropdown is exactly the set the server check accepts (" . count($dd) . ' accounts)', $dd == $acc && $dd, json_encode([count($dd), count($acc)]));
    }
    $t1 = $ids($E->GetExpenseAccounts(1)); $t2 = $ids($E->GetExpenseAccounts(2));
    chk('expense accounts: no bank (105), no income (93), no reporting (33), no inactive (203)', !array_intersect([105, 195, 93, 33, 203], $t1));
    chk('expense accounts: group list is a subset of the church list (forGroup rule)', !array_diff($t2, $t1) && count($t2) < count($t1));
    $legacy = q('SELECT ID,expenseType,accountId FROM tblexpenses WHERE accountId IN (199,200,203,204) AND deleted=0 ORDER BY ID LIMIT 1')[0];
    chk('edit list: the expense\'s own inactive account is also offered', in_array((int)$legacy['accountId'], $ids($E->GetExpenseAccounts((int)$legacy['expenseType'], (int)$legacy['ID'])), true)
        && !in_array((int)$legacy['accountId'], $ids($E->GetExpenseAccounts((int)$legacy['expenseType'])), true), json_encode($legacy));

    $cash = $ids($E->GetPaidFromAccounts(1)); $bankl = $ids($E->GetPaidFromAccounts(3));
    chk('paid from, cash: the Ledger PAY_CASH list (34, 121, 167, 98, 194), no bank', $cash == (function () use ($pdo) { $x = array_map(function ($a) { return (int)$a['ID']; }, Ledger::listPaymentAccounts($pdo, 11, Ledger::PAY_CASH)); sort($x); return $x; })() && !array_diff([34, 121, 167, 98, 194], $cash) && !array_intersect([105, 195], $cash), json_encode($cash));
    chk('paid from, M-Pesa / cheque / bank: the Ledger PAY_BANK list (105, 195), no cash', $bankl == [105, 195] && $ids($E->GetPaidFromAccounts(2)) == $bankl && $ids($E->GetPaidFromAccounts(4)) == $bankl, json_encode($bankl));
    chk('add form preselects 98 (cash list)', $E->DefaultPaidFrom($E->GetPaidFromAccounts(1)) === 98 && $E->DefaultPaidFrom($E->GetPaidFromAccounts(3)) === '');

    // ================= vouchers
    $v12 = seq('voucher', 12);
    $peek = $E->peekVoucherNo($OPEN);
    chk('voucher preview = highest voucher of the year + 1 and reserves nothing', $peek === (int)q('SELECT MAX(voucherNo) m FROM tblexpenses WHERE congregationId=11 AND fiscalYearId=12')[0]['m'] + 1 && seq('voucher', 12) === $v12, json_encode([$peek, $v12]));
    chk('voucher preview is empty when no fiscal year covers the date', $E->peekVoucherNo($NOYEAR) === '');

    // ================= lifecycle for every paid-from case
    $reqNew = $mkReq(['no' => 99001]); $approval($reqNew, 121, 98);
    $cases = [
        ['paid from 98 (cash)',            ['paymethod' => 1, 'paidfrom' => 98],  98, false, true],
        ['paid from 194 (cash)',           ['paymethod' => 1, 'paidfrom' => 194], 194, false, true],
        ['paid from 34 (cash at hand)',    ['paymethod' => 1, 'paidfrom' => 34],  34, false, false],
        ['paid from 121 + new-style req',  ['paymethod' => 1, 'paidfrom' => 121, 'expensetype' => 2, 'costcentre' => $group, 'account' => $gacct, 'reqid' => $reqNew], 121, false, false],
        ['paid from 105 (cheque)',         ['paymethod' => 3, 'paidfrom' => 105], 105, true, false],
        ['paid from 105 (M-Pesa)',         ['paymethod' => 2, 'paidfrom' => 105], 105, true, false],
    ];
    $lifeIds = [];
    foreach ($cases as [$label, $o, $pf, $isBank, $isPetty]) {
        $acct = $o['account'] ?? $acct1;
        $peek = $E->peekVoucherNo($OPEN);
        $c0 = counts();
        $r = $E->create($mk($o));
        chk("[$label] create saved", $r['success'], json_encode($r));
        $id = $r['id']; $lifeIds[] = $id; $row = ex($id); $voucher = (int)$row['voucherNo'];
        chk("[$label] draft: status 0, voucher = the preview, paid-from stored, deductfrom and bankId NULL", (int)$row['status'] === 0 && $voucher === $peek && (int)$row['PaidFromAccountId'] === $pf && $row['deductfrom'] === null && $row['bankId'] === null && (int)$row['paymethodId'] === $o['paymethod'] && $row['amount'] === '100.50', json_encode($row));
        chk("[$label] draft: no entry, no bank row, no petty cash row", !entry($id) && !brows($id) && !prows($id, false));
        chk("[$label] draft: ID from lastInsertId is the new MAX ID", $id === $newid() && $id > $max['tblexpenses']);
        $pv = $E->getJournalDetails($id);
        chk("[$label] preview: Dr expense account / Cr paid-from account, rows marked Not posted", count($pv) === 2 && $pv[0]->notPosted && $pv[1]->notPosted
            && $pv[0]->account === q('SELECT accountType a FROM tblaccounttypes WHERE ID=?', [$acct])[0]['a'] && $pv[1]->account === q('SELECT accountType a FROM tblaccounttypes WHERE ID=?', [$pf])[0]['a'] && (float)$pv[0]->debit === 100.5 && (float)$pv[1]->credit === 100.5);

        $r = $E->approve($id); chk("[$label] approve saved", $r['success'], json_encode($r));
        chk("[$label] posted: status 1, Dr expense account / Cr paid-from, 100.50 each", (int)ex($id)['status'] === 1 && entry($id) == [['accountId' => $acct, 'dc' => 'DEBIT', 'amount' => '100.50'], ['accountId' => $pf, 'dc' => 'CREDIT', 'amount' => '100.50']], json_encode(entry($id)));
        $en = entryRow($id);
        chk("[$label] entry: date, reference and memo come from the expense", $en['entryDate'] === $OPEN && $en['reference'] === 't85d' && $en['description'] === 'test expense' && (int)$en['transactionTypeId'] === 2, json_encode($en));
        $br = brows($id);
        if ($isBank) {
            chk("[$label] bank row: one credit of 100.5, method 2 (Withdrawal), uncleared, on bank $pf", count($br) === 1 && abs($br[0]['credit'] - 100.5) < 0.001 && $br[0]['debit'] == 0 && (int)$br[0]['transactionMethod'] === 2 && (int)$br[0]['bankId'] === $pf && (int)$br[0]['cleared'] === 0, json_encode($br));
        } else {
            chk("[$label] no bank row", !$br);
        }
        $pr = prows($id);
        if ($isPetty) {
            chk("[$label] petty cash row: one, account $pf, Credit 100.50, IsReceipt 0, dated like the expense", count($pr) === 1 && (int)$pr[0]['PettyCashAccountId'] === $pf && $pr[0]['Credit'] === '100.50' && (int)$pr[0]['IsReceipt'] === 0 && $pr[0]['TransactionDate'] === $OPEN && (int)$pr[0]['CongregationId'] === 11, json_encode($pr));
        } else {
            chk("[$label] no petty cash row", !$pr);
        }
        $view = $E->getJournalDetails($id);
        chk("[$label] posted view = ledger entry, not marked Not posted", count($view) === 2 && !$view[0]->notPosted && !$view[1]->notPosted
            && abs((float)$view[0]->debit - 100.5) < 0.001 && abs((float)$view[1]->credit - 100.5) < 0.001 && $view[1]->account === q('SELECT accountType a FROM tblaccounttypes WHERE ID=?', [$pf])[0]['a']);

        // edit narration only: bank row kept (cleared stays), petty row updated in place
        if ($isBank) { q('UPDATE tblbankpostings SET cleared=1, clearedDare=? WHERE ID=?', ['2026-01-20', $br[0]['ID']]); }
        $r = $E->update($id, $mk($o + ['description' => 'Edited narration']));
        chk("[$label] edit narration: saved, voucher kept", $r['success'] && (int)ex($id)['voucherNo'] === $voucher, json_encode($r));
        $after = brows($id);
        if ($isBank) {
            chk("[$label] edit narration: same bank row kept, still cleared", count($after) === 1 && $after[0]['ID'] == $br[0]['ID'] && (int)$after[0]['cleared'] === 1 && $after[0]['clearedDare'] === '2026-01-20', json_encode($after));
        }
        if ($isPetty) {
            $pa = prows($id);
            chk("[$label] edit narration: same petty cash row updated in place", count($pa) === 1 && $pa[0]['ID'] == $pr[0]['ID'] && $pa[0]['Narration'] === 'edited narration', json_encode($pa));
        }
        // 8.9 follow-up: a cleared bank row can no longer be changed (bank / amount) or voided; the test refuses it, then un-clears the row and goes on
        if ($isBank) {
            $rr = $E->update($id, $mk($o + ['description' => 'Edited narration', 'amount' => '222.25']));
            chk("[$label] edit amount on a cleared row: refused (Ledger::clearedBankProblem)", !$rr['success'] && stripos(json_encode($rr), 'cleared') !== false && brows($id)[0]['ID'] == $br[0]['ID'], json_encode($rr));
            q('UPDATE tblbankpostings SET cleared=0, clearedDare=NULL WHERE ID=?', [$br[0]['ID']]);
        }
        // edit amount
        $r = $E->update($id, $mk($o + ['description' => 'Edited narration', 'amount' => '222.25']));
        chk("[$label] edit amount: saved, entry 222.25", $r['success'] && entry($id)[0]['amount'] === '222.25' && entry($id)[1]['amount'] === '222.25', json_encode($r));
        if ($isBank) {
            $a2 = brows($id);
            chk("[$label] edit amount on a cleared row: new uncleared row 222.25, old row soft-deleted", count($a2) === 1 && $a2[0]['ID'] != $br[0]['ID'] && (int)$a2[0]['cleared'] === 0 && abs($a2[0]['credit'] - 222.25) < 0.001 && (int)q('SELECT deleted d FROM tblbankpostings WHERE ID=?', [$br[0]['ID']])[0]['d'] === 1, json_encode($a2));
        }
        if ($isPetty) {
            $pa = prows($id);
            chk("[$label] edit amount: petty row updated in place to 222.25", count($pa) === 1 && $pa[0]['ID'] == $pr[0]['ID'] && $pa[0]['Credit'] === '222.25');
        }

        // unapprove -> draft, everything reversed
        $nPetty = count(prows($id, false));
        $r = $E->unapprove($id);
        chk("[$label] unapprove saved", $r['success'], json_encode($r));
        chk("[$label] unapproved: status 0, entry voided, bank rows voided, petty row soft-deleted (not removed)", (int)ex($id)['status'] === 0 && !entry($id) && !brows($id) && !prows($id) && count(prows($id, false)) === $nPetty);
        $pv = $E->getJournalDetails($id);
        chk("[$label] unapproved expense shows the Not posted preview again", $pv[0]->notPosted);
        // re-approve
        $r = $E->approve($id);
        chk("[$label] re-approve saved: new entry, one active petty row at most", $r['success'] && count(entry($id)) === 2 && count(prows($id)) === ($isPetty ? 1 : 0) && count(brows($id)) === ($isBank ? 1 : 0), json_encode($r));
        chk("[$label] re-approve: a fresh entry (the voided one is kept)", (int)q('SELECT COUNT(*) c FROM journal_entries WHERE congregationId=11 AND transactionTypeId=2 AND transactionId=?', [$id])[0]['c'] === 2);
        // delete approved
        $r = $E->delete($id);
        chk("[$label] delete approved: deleted = 1, entry / bank rows voided, petty row soft-deleted", $r['success'] && (int)ex($id)['deleted'] === 1 && !entry($id) && !brows($id) && !prows($id) && count(prows($id, false)) >= ($isPetty ? 1 : 0), json_encode($r));
        chk("[$label] deleted expense has no preview / view", $E->getJournalDetails($id) === false);
    }

    // ================= paid-from changes on an approved expense: petty cash row follows in place, never hard-deleted
    $id = $make(); $E->approve($id);
    $p0 = prows($id)[0]['ID'];
    $r = $E->update($id, $mk(['paidfrom' => 194]));
    $pa = prows($id);
    chk('approved edit 98 -> 194: credit 194, same petty row moved to account 194', $r['success'] && entry($id)[1]['accountId'] == 194 && count($pa) === 1 && $pa[0]['ID'] == $p0 && (int)$pa[0]['PettyCashAccountId'] === 194, json_encode([$r, $pa]));
    $r = $E->update($id, $mk(['paidfrom' => 34]));
    chk('approved edit 194 -> 34: petty row soft-deleted, none active', $r['success'] && !prows($id) && count(prows($id, false)) === 1 && (int)prows($id, false)[0]['Deleted'] === 1, json_encode($r));
    $r = $E->update($id, $mk(['paidfrom' => 98]));
    chk('approved edit 34 -> 98: a new petty row inserted', $r['success'] && count(prows($id)) === 1 && count(prows($id, false)) === 2 && (int)prows($id)[0]['PettyCashAccountId'] === 98, json_encode($r));
    $r = $E->update($id, $mk(['paymethod' => 3, 'paidfrom' => 105]));
    chk('approved edit cash -> cheque on bank 105: bank row created, petty row soft-deleted', $r['success'] && count(brows($id)) === 1 && !prows($id) && entry($id)[1]['accountId'] == 105, json_encode($r));
    $r = $E->update($id, $mk(['paymethod' => 1, 'paidfrom' => 34]));
    chk('approved edit cheque -> cash: bank row voided', $r['success'] && !brows($id), json_encode($r));
    $E->delete($id);

    // ================= draft header edit + delete
    $id = $make(); $voucher = (int)ex($id)['voucherNo'];
    $r = $E->update($id, $mk(['amount' => '55', 'paidfrom' => 34, 'description' => 'Draft edit']));
    chk('draft edit: header only (no entry, no petty row), voucher kept', $r['success'] && !entry($id) && !prows($id, false) && ex($id)['amount'] === '55.00' && (int)ex($id)['PaidFromAccountId'] === 34 && (int)ex($id)['voucherNo'] === $voucher && ex($id)['deductfrom'] === null);
    // year change = new number from the NEW year's sequence; the old number is retired; same-year edits keep it
    $next25 = $E->peekVoucherNo($OPEN25);
    $r = $E->update($id, $mk(['date' => '2026-02-20']));
    chk('draft edit within the same year (date changes): voucher kept, no renumber message', $r['success'] && (int)ex($id)['voucherNo'] === $voucher && empty($r['renumbered']), json_encode($r));
    $r = $E->update($id, $mk(['date' => $OPEN25]));
    chk('draft edit into another open year: year updated, voucher = next number of year 25 (not the old one)', $r['success'] && (int)ex($id)['fiscalYearId'] === 25 && (int)ex($id)['voucherNo'] === $next25 && $next25 !== $voucher, json_encode([$r, $voucher, $next25]));
    chk('renumber: message names both numbers and the year; log has both', strpos((string)($r['renumbered'] ?? ''), "from $voucher to $next25") !== false && strpos($r['renumbered'], '2026-2027') !== false
        && (int)q('SELECT COUNT(*) c FROM tbllogs WHERE Activity LIKE ?', ["%voucher no $next25 (was $voucher)%"])[0]['c'] === 1, json_encode($r));
    chk('renumber: the next allocation in year 25 is right after it', $E->peekVoucherNo($OPEN25) === $next25 + 1 && (int)ex($make(['date' => $OPEN25]))['voucherNo'] === $next25 + 1);
    $r = $E->update($id, $mk(['date' => $OPEN]));
    $back = (int)ex($id)['voucherNo'];
    chk('moving back to year 12 gives a fresh year-12 number, never the retired one', $r['success'] && $back !== $voucher && $back === (int)q('SELECT MAX(voucherNo) m FROM tblexpenses WHERE congregationId=11 AND fiscalYearId=12')[0]['m'] && $back > $voucher, json_encode([$voucher, $back]));
    $extra = (int)ex($make())['voucherNo'];
    chk('retired number is not handed out again by the next year-12 expense', $extra === $back + 1 && $extra !== $voucher);
    // approved expense moved into another year: renumbered too, entry follows the date
    $ap = $make(); $E->approve($ap); $apv = (int)ex($ap)['voucherNo'];
    $r = $E->update($ap, $mk(['date' => $OPEN25]));
    chk('approved edit into another year: renumbered, entry dated in the new year', $r['success'] && (int)ex($ap)['voucherNo'] !== $apv && entryRow($ap)['entryDate'] === $OPEN25 && !empty($r['renumbered']), json_encode($r));
    $E->delete($ap);
    $r = $E->delete($id);
    chk('draft delete: deleted = 1, nothing posted', $r['success'] && (int)ex($id)['deleted'] === 1 && !entry($id));

    // ================= legacy fields are left alone on update
    $id = $make(); q("UPDATE tblexpenses SET deductfrom='petty cash', bankId=7 WHERE ID=?", [$id]);
    $E->update($id, $mk(['description' => 'x']));
    chk('update leaves the legacy deductfrom / bankId untouched', ex($id)['deductfrom'] === 'petty cash' && (int)ex($id)['bankId'] === 7);
    $E->delete($id);

    // ================= requisitions
    $reqOld = $mkReq(['no' => 99002]); $approval($reqOld, $gacct, 34);
    $reqSmall = $mkReq(['no' => 99003, 'approved' => 500]); $approval($reqSmall, 121, 98, '500');
    $reqRejected = $mkReq(['no' => 99004, 'status' => 2]);
    $reqDeleted = $mkReq(['no' => 99005, 'deleted' => 1]);
    $reqOther = $mkReq(['no' => 99006, 'group' => $group2]); $approval($reqOther, 121, 98);
    $reqForeign = $mkReq(['no' => 99007, 'cong' => $other]);
    $reqNoEntry = $mkReq(['no' => 99008]);
    $reqDistrict = $mkReq(['no' => 99009, 'type' => 'district', 'group' => null, 'district' => 1]);
    $greq = function ($o) use ($mk, $group, $gacct) { return $mk($o + ['expensetype' => 2, 'costcentre' => $group, 'account' => $gacct, 'paymethod' => 1, 'paidfrom' => 121]); };
    $opt = $E->GetRequisitionOptions(2, $group);
    $optIds = array_map(function ($r) { return $r->ID; }, $opt);
    $styles = []; foreach ($opt as $r) { $styles[$r->ID] = $r->Style; }
    chk('requisition list: approved, same group, with balance; not rejected / deleted / other group / foreign', in_array($reqNew, $optIds) && in_array($reqOld, $optIds) && !array_intersect([$reqRejected, $reqDeleted, $reqOther, $reqForeign], $optIds), json_encode($optIds));
    chk('requisition list: style new = approval debited 121, old = it did not, none when no entry', $styles[$reqNew] === 'new' && $styles[$reqOld] === 'old' && $styles[$reqNoEntry] === null, json_encode($styles));

    // new-style: paid from 121 passes; 98 refused
    $before = counts();
    $r = $E->create($greq(['reqid' => $reqNew, 'paidfrom' => 98]));
    echo '   [new-style requisition paid from 98] ' . json_encode($r['errors']) . "\n";
    chk('refused: new-style requisition not paid from 121', !$r['success'] && counts() == $before);
    $r = $E->create($greq(['reqid' => $reqOld, 'paidfrom' => 98]));
    chk('old-style requisition: any cash account (98) accepted', $r['success'], json_encode($r)); $oldExp = $r['id'] ?? 0;
    $r = $E->create($greq(['reqid' => $reqOld, 'paidfrom' => 121]));
    chk('old-style requisition: 121 accepted too', $r['success'], json_encode($r));

    // balance
    $a = $E->create($greq(['reqid' => $reqSmall, 'amount' => '300']));
    chk('requisition 500: first draft of 300 accepted', $a['success'], json_encode($a));
    $b = $E->create($greq(['reqid' => $reqSmall, 'amount' => '300']));
    chk('requisition 500: second draft of 300 accepted (drafts do not count)', $b['success'], json_encode($b));
    $r = $E->approve($a['id']); chk('approve first 300', $r['success'], json_encode($r));
    $r = $E->approve($b['id']);
    echo '   [approve beyond the balance] ' . json_encode($r['errors']) . "\n";
    chk('refused on approve: amount exceeds the remaining balance (200)', !$r['success'] && strpos(implode(' ', $r['errors']), '200.00') !== false && (int)ex($b['id'])['status'] === 0 && !entry($b['id']));
    $before = counts();
    $r = $E->create($greq(['reqid' => $reqSmall, 'amount' => '300']));
    chk('refused on create: amount exceeds the remaining balance', !$r['success'] && counts() == $before);
    $r = $E->update($a['id'], $greq(['reqid' => $reqSmall, 'amount' => '500']));
    chk('edit of the approved expense may use the whole balance (it excludes itself)', $r['success'] && entry($a['id'])[0]['amount'] === '500.00', json_encode($r));
    $r = $E->update($a['id'], $greq(['reqid' => $reqSmall, 'amount' => '500.01']));
    chk('refused on edit: 500.01 exceeds the approved 500', !$r['success']);
    $E->delete($a['id']); $E->delete($b['id']);

    $refuse = [];
    $bad = [
        'rejected requisition' => $greq(['reqid' => $reqRejected]),
        'deleted requisition' => $greq(['reqid' => $reqDeleted]),
        'requisition of another group' => $greq(['reqid' => $reqOther]),
        'requisition of another congregation' => $greq(['reqid' => $reqForeign]),
        'requisition without an approval entry (cannot classify)' => $greq(['reqid' => $reqNoEntry]),
        'requisition of a district on a group expense' => $greq(['reqid' => $reqDistrict]),
        'unknown requisition' => $greq(['reqid' => 987654]),
        'requisition on a church expense' => $mk(['reqid' => $reqNew, 'paidfrom' => 121]),
        'requisition paid by cheque' => $greq(['reqid' => $reqOld, 'paymethod' => 3, 'paidfrom' => 105]),
        '121 without a requisition' => $mk(['paidfrom' => 121]),
        'overspent requisition (1000.01 of 1000)' => $greq(['reqid' => $reqNew, 'amount' => '1000.01']),
    ];
    $before = counts(); $v = seq('voucher', 12);
    foreach ($bad as $name => $in) {
        $r = $E->create($in); echo "   [$name] " . json_encode($r['errors']) . "\n";
        chk("refused: $name", !$r['success'] && $r['errors']);
    }
    chk('requisition refusals wrote nothing and used no voucher number', counts() == $before && seq('voucher', 12) === $v);

    // ================= other refusals
    $bad = [
        'cash method with a bank account (105)' => $mk(['paymethod' => 1, 'paidfrom' => 105]),
        'cheque method with a cash account (98)' => $mk(['paymethod' => 3, 'paidfrom' => 98]),
        'M-Pesa with a cash account (34)' => $mk(['paymethod' => 2, 'paidfrom' => 34]),
        'cash with a reporting account (33)' => $mk(['paidfrom' => 33]),
        'paid from missing' => $mk(['paidfrom' => '']),
        'paid from unknown' => $mk(['paidfrom' => 987654]),
        'unknown payment method' => $mk(['paymethod' => 9]),
        'expense account is a bank (105)' => $mk(['account' => 105]),
        'expense account is income (93)' => $mk(['account' => 93]),
        'expense account is a reporting account (33)' => $mk(['account' => 33]),
        'inactive expense account on a new expense (203)' => $mk(['account' => 203]),
        'expense account missing' => $mk(['account' => '']),
        'expense account = paid-from account (34)' => $mk(['account' => 34, 'paidfrom' => 34]),
        'group expense on an account not for groups' => $mk(['expensetype' => 2, 'costcentre' => $group, 'account' => (int)array_values(array_diff($t1, $t2))[0]]),
        'group expense without a group' => $mk(['expensetype' => 2, 'costcentre' => '', 'account' => $gacct]),
        'unknown expense type' => $mk(['expensetype' => 7]),
        'amount 0' => $mk(['amount' => '0']),
        'negative amount' => $mk(['amount' => '-5']),
        '3 decimals' => $mk(['amount' => '1.234']),
        'non-numeric amount' => $mk(['amount' => 'abc']),
        'empty amount' => $mk(['amount' => '']),
        'no reference' => $mk(['reference' => '']),
        'reference over 50 characters' => $mk(['reference' => str_repeat('r', 51)]),
        'no description' => $mk(['description' => '']),
        'description over 50 characters' => $mk(['description' => str_repeat('d', 51)]),
        'invalid date' => $mk(['date' => 'abc']),
        'no fiscal year for the date' => $mk(['date' => $NOYEAR]),
        'closed fiscal year' => $mk(['date' => $CLOSED]),
        'closed and deleted fiscal year' => $mk(['date' => $DELCLOSED]),
    ];
    if ($foreignGroup) { $bad['group of another congregation'] = $mk(['expensetype' => 2, 'costcentre' => $foreignGroup[0]['ID'], 'account' => $gacct]); }
    $msg = [];
    $before = counts(); $v = seq('voucher', 12); $v10 = seq('voucher', 10);
    foreach ($bad as $name => $in) {
        $r = $E->create($in); $msg[$name] = implode(' | ', $r['errors']);
        echo "   [$name] " . json_encode($r['errors']) . "\n";
        chk("refused: $name", !$r['success'] && $r['errors']);
    }
    chk('message: closed fiscal year names the year', strpos($msg['closed fiscal year'], 'The financial year 2023-2024 is closed') !== false, $msg['closed fiscal year']);
    chk('message: no fiscal year at all', strpos($msg['no fiscal year for the date'], 'No financial year is defined for 2013-06-01') !== false);
    chk('message: closed AND deleted year reported as closed', strpos($msg['closed and deleted fiscal year'], 'The financial year 2021/2022 is closed') !== false, $msg['closed and deleted fiscal year']);
    chk('refusals wrote nothing and used no voucher number', counts() == $before && seq('voucher', 12) === $v && seq('voucher', 10) === $v10);

    // ================= approve / edit / unapprove / delete refusals
    $pdo->prepare("INSERT INTO tblexpenses (fiscalYearId,voucherNo,expenseType,expenseDate,accountId,paymethodId,PaidFromAccountId,amount,narration,paymentReference,status,postedBy,postedDate,congregationId)
                   VALUES (12,9990,1,?,?,1,98,10,'t85d foreign','t85d',0,?,?,?)")->execute([$OPEN, $acct1, $_SESSION['userId'], date('Y-m-d'), $other]);
    $foreignEx = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tblexpenses (fiscalYearId,voucherNo,expenseType,expenseDate,accountId,paymethodId,PaidFromAccountId,amount,narration,paymentReference,status,postedBy,postedDate,congregationId)
                   VALUES (10,9991,1,?,?,1,98,10,'t85d closed','t85d',0,?,?,11)")->execute([$CLOSED, $acct1, $_SESSION['userId'], date('Y-m-d')]);
    $closedDraft = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tblexpenses (fiscalYearId,voucherNo,expenseType,expenseDate,accountId,paymethodId,PaidFromAccountId,amount,narration,paymentReference,status,postedBy,postedDate,congregationId)
                   VALUES (12,9992,1,?,?,1,NULL,10,'t85d legacy','t85d',0,?,?,11)")->execute([$OPEN, $acct1, $_SESSION['userId'], date('Y-m-d')]);
    $nullPaid = (int)$pdo->lastInsertId();
    $deadId = $make(); $E->delete($deadId);
    $apprId = $make(); $E->approve($apprId);
    $draftId = $make();
    $watch = [$foreignEx, $closedDraft, $nullPaid, $deadId, $apprId];
    $snapEx = function () use ($watch) { $o = []; foreach ($watch as $i) { $o[$i] = md5(json_encode([ex($i), entry($i), brows($i), prows($i, false)])); } return $o; };
    $s0 = $snapEx(); $before = counts(); $v = seq('voucher', 12);
    $tests = [
        ['approve: other congregation', function () use ($E, $foreignEx) { return $E->approve($foreignEx); }],
        ['edit: other congregation', function () use ($E, $foreignEx, $mk) { return $E->update($foreignEx, $mk()); }],
        ['delete: other congregation', function () use ($E, $foreignEx) { return $E->delete($foreignEx); }],
        ['unapprove: other congregation', function () use ($E, $foreignEx) { return $E->unapprove($foreignEx); }],
        ['approve: id 0', function () use ($E) { return $E->approve(0); }],
        ['edit: id 0', function () use ($E, $mk) { return $E->update(0, $mk()); }],
        ['delete: unknown id', function () use ($E) { return $E->delete(987654321); }],
        ['approve: deleted expense', function () use ($E, $deadId) { return $E->approve($deadId); }],
        ['edit: deleted expense', function () use ($E, $deadId, $mk) { return $E->update($deadId, $mk()); }],
        ['delete: already deleted', function () use ($E, $deadId) { return $E->delete($deadId); }],
        ['unapprove: deleted expense', function () use ($E, $deadId) { return $E->unapprove($deadId); }],
        ['approve: already approved', function () use ($E, $apprId) { return $E->approve($apprId); }],
        ['unapprove: a draft', function () use ($E, $draftId) { return $E->unapprove($draftId); }],
        ['edit draft dated in a closed year', function () use ($E, $closedDraft, $mk) { return $E->update($closedDraft, $mk()); }],
        ['delete draft dated in a closed year', function () use ($E, $closedDraft) { return $E->delete($closedDraft); }],
        ['approve draft dated in a closed year', function () use ($E, $closedDraft) { return $E->approve($closedDraft); }],
        ['approve legacy draft without a paid-from account', function () use ($E, $nullPaid) { return $E->approve($nullPaid); }],
        ['edit approved: moving into a closed year', function () use ($E, $apprId, $mk, $CLOSED) { return $E->update($apprId, $mk(['date' => $CLOSED])); }],
        ['edit draft: moving into a closed year', function () use ($E, $draftId, $mk, $CLOSED) { return $E->update($draftId, $mk(['date' => $CLOSED])); }],
    ];
    $r2 = [];
    foreach ($tests as [$name, $fn]) {
        $r = $fn(); $r2[$name] = implode(' | ', $r['errors']);
        echo "   [$name] " . json_encode($r['errors']) . "\n";
        chk("refused: $name", !$r['success'] && $r['errors']);
    }
    chk('refused actions changed nothing (rows, entries, bank, petty, vouchers)', $snapEx() == $s0 && counts() == $before && seq('voucher', 12) === $v);
    chk('message: closed-year draft edit', strpos($r2['delete draft dated in a closed year'], 'The financial year 2023-2024 is closed') !== false, $r2['delete draft dated in a closed year']);
    chk('message: legacy draft without paid-from asks to choose one', strpos($r2['approve legacy draft without a paid-from account'], 'paid from') !== false, $r2['approve legacy draft without a paid-from account']);
    chk('message: deleted expense', strpos($r2['edit: deleted expense'], 'deleted') !== false);

    // old date in a closed year: approved paths refused by Ledger / the closed-year check
    setYear(12, 1, 0);
    $before = counts(); $s1 = md5(json_encode([ex($apprId), entry($apprId), brows($apprId), prows($apprId, false)]));
    foreach ([['edit approved: its date is now in a closed year', $E->update($apprId, $mk(['description' => 'x']))],
              ['unapprove approved: closed year', $E->unapprove($apprId)],
              ['delete approved: closed year', $E->delete($apprId)],
              ['delete draft: closed year', $E->delete($draftId)],
              ['edit draft: closed year', $E->update($draftId, $mk(['description' => 'x']))],
              ['create: closed year (open year closed meanwhile)', $E->create($mk())]] as [$name, $r]) {
        echo "   [$name] " . json_encode($r['errors']) . "\n";
        chk("refused: $name", !$r['success'] && strpos(implode(' ', $r['errors']), 'The financial year 2025-2026 is closed') !== false);
    }
    chk('closed-year refusals changed nothing', md5(json_encode([ex($apprId), entry($apprId), brows($apprId), prows($apprId, false)])) === $s1 && counts() == $before);
    setYear(12, 0, 0);

    // approved expense that is deleted-year / no-entry: unapprove of a legacy approved expense without an entry still works
    $pdo->prepare("INSERT INTO tblexpenses (fiscalYearId,voucherNo,expenseType,expenseDate,accountId,paymethodId,PaidFromAccountId,amount,narration,paymentReference,status,postedBy,postedDate,congregationId)
                   VALUES (12,9993,1,?,?,1,98,10,'t85d no entry','t85d',1,?,?,11)")->execute([$OPEN, $acct1, $_SESSION['userId'], date('Y-m-d')]);
    $noEntry = (int)$pdo->lastInsertId();
    $r = $E->update($noEntry, $mk(['amount' => '10']));
    chk('legacy approved expense without an entry: edit creates its entry and petty cash row', $r['success'] && count(entry($noEntry)) === 2 && count(prows($noEntry)) === 1, json_encode($r));
    $E->delete($noEntry);

    // ================= roles (server side, same as the buttons)
    $roles = function ($type) use ($E, $mk, $make) {
        $res = [];
        $d = $make();                                    // created with an allowed role
        $_SESSION['userType'] = $type;
        $res['edit'] = $E->update($d, $mk(['description' => 'role']))['success'];
        $res['approve'] = $E->approve($d)['success'];
        $_SESSION['userType'] = 1;
        $E->approve($d);                                 // (no-op when the role above already approved it)
        $_SESSION['userType'] = $type;
        $res['edit approved'] = $E->update($d, $mk(['description' => 'role2']))['success'];
        $res['unapprove'] = $E->unapprove($d)['success'];
        $res['delete approved'] = $E->delete($d)['success'];
        $_SESSION['userType'] = 1;
        $E->delete($d);
        $d2 = $make();
        $_SESSION['userType'] = $type;
        $res['delete draft'] = $E->delete($d2)['success'];
        $_SESSION['userType'] = 1;
        $E->delete($d2);
        return $res;
    };
    // want: [draft edit + approve + delete, approved edit + unapprove + delete]
    foreach ([1 => [true, true], 2 => [true, true], 3 => [false, false], 4 => [false, false], 5 => [true, false], 6 => [true, false]] as $type => $want) {
        $got = $roles($type);
        chk("role userType $type: draft edit/approve/delete " . ($want[0] ? 'allowed' : 'refused') . ', approved edit/unapprove/delete ' . ($want[1] ? 'allowed' : 'refused'),
            $got['edit'] === $want[0] && $got['approve'] === $want[0] && $got['delete draft'] === $want[0]
            && $got['edit approved'] === $want[1] && $got['unapprove'] === $want[1] && $got['delete approved'] === $want[1], json_encode($got));
    }
    unset($_SESSION['userType']);
    $r = $E->approve($draftId);
    chk('no userType in the session: refused', !$r['success']);
    $_SESSION['userType'] = 1;

    // ================= voucher numbers
    $v1 = (int)ex($make())['voucherNo'];
    $last = (int)q('SELECT MAX(ID) m FROM tblexpenses')[0]['m']; $E->delete($last);
    $v2 = (int)ex($make())['voucherNo'];
    chk('voucher number of a deleted expense is not reused', $v2 === $v1 + 1, json_encode([$v1, $v2]));
    $maxv = (int)q('SELECT MAX(voucherNo) m FROM tblexpenses WHERE congregationId=11 AND fiscalYearId=12')[0]['m'];
    q('DELETE FROM receipt_number_seq WHERE congregationId=11 AND kind="voucher" AND fiscalYearId=12');
    $v3 = (int)ex($make())['voucherNo'];
    chk('missing sequence row is seeded from max + 1 (deleted rows included)', $v3 === $maxv + 1 && seq('voucher', 12) === $v3, json_encode([$maxv, $v3]));
    // year 25 may already hold real expenses / a sequence row on dev: only its own counter may move
    $s25 = seq('voucher', 25); $s12 = seq('voucher', 12);
    $v25 = (int)ex($make(['date' => $OPEN25]))['voucherNo'];
    chk('each fiscal year has its own sequence (year 25 counter moves, year 12 counter does not)', seq('voucher', 25) === $v25 && $v25 > (int)$s25 && seq('voucher', 12) === $s12, json_encode([$s25, $v25, $s12, seq('voucher', 12)]));
    $dupe = (int)q('SELECT voucherNo v FROM tblexpenses WHERE congregationId=11 AND fiscalYearId=12 GROUP BY voucherNo HAVING COUNT(*) > 1 ORDER BY voucherNo LIMIT 1')[0]['v'];
    chk('existing duplicate vouchers are left as they are (' . $dupe . ' still appears more than once)', (int)q('SELECT COUNT(*) c FROM tblexpenses WHERE congregationId=11 AND fiscalYearId=12 AND voucherNo=?', [$dupe])[0]['c'] > 1);

    // ================= shared journal view (print and the eye icon)
    $id = $make(); $drv = $E->getJournalDetails($id);
    $jm = new Journal;
    $viaJournal = $jm->getJournalDetails(['id' => $id, 'type' => 2]);
    chk('Journal::getJournalDetails (eye icon) uses the same method: draft preview', $viaJournal == $drv && $viaJournal[0]->notPosted);
    $E->approve($id);
    $viaJournal = $jm->getJournalDetails(['id' => $id, 'type' => 2]);
    chk('Journal::getJournalDetails: posted entry lines (Dr / Cr names from the ledger)', $viaJournal == $E->getJournalDetails($id) && count($viaJournal) === 2 && !$viaJournal[0]->notPosted);
    chk('Journal::getJournalDetails: other congregation / unknown / deleted = false', $jm->getJournalDetails(['id' => $foreignEx, 'type' => 2]) === false && $jm->getJournalDetails(['id' => 987654321, 'type' => 2]) === false && $jm->getJournalDetails(['id' => $deadId, 'type' => 2]) === false);
    chk('getExpenseFull / getExpense refuse another congregation', $E->getExpense($foreignEx) === false && $E->getExpenseFull($foreignEx) === false && $E->getExpense($id) !== false);
    $E->delete($id);

    // ================= logs
    chk('create / approve / update / unapprove / delete write tbllogs rows', (int)q('SELECT COUNT(*) c FROM tbllogs WHERE ID > ? AND Activity LIKE "%expense%"', [$max['tbllogs']])[0]['c'] >= 40
        && (int)q('SELECT COUNT(DISTINCT SUBSTRING_INDEX(Activity, " ", 1)) c FROM tbllogs WHERE ID > ? AND Activity LIKE "% expense %"', [$max['tbllogs']])[0]['c'] >= 5);

    // ================= cleanup through the app paths, then verify
    foreach (q('SELECT ID FROM tblexpenses WHERE ID > ? AND deleted=0 AND congregationId=11', [$max['tblexpenses']]) as $row) {
        $r = $E->delete((int)$row['ID']);
        if (!$r['success']) { q('UPDATE tblexpenses SET deleted=1 WHERE ID=?', [$row['ID']]); }
    }
    chk('no active test entry, bank row or petty cash row left', (int)q('SELECT COUNT(*) c FROM journal_entries WHERE ID > ? AND deleted=0 AND transactionTypeId=2', [$max['journal_entries']])[0]['c'] === 0
        && (int)q('SELECT COUNT(*) c FROM tblbankpostings WHERE ID > ? AND deleted=0 AND transactionType=2', [$max['tblbankpostings']])[0]['c'] === 0
        && (int)q('SELECT COUNT(*) c FROM tblpettycash WHERE ID > ? AND Deleted=0', [$max['tblpettycash']])[0]['c'] === 0);
    $now = [];
    foreach (['tblexpenses' => 'ID', 'journal_entries' => 'ID', 'tblpettycash' => 'ID', 'tblbankpostings' => 'ID', 'tblledger' => 'ID'] as $t => $k) { $now[$t] = snap($t, $k, $max[$t]); }
    $now['journal_lines'] = snap('journal_lines', 'journalEntryId', $max['journal_entries']);
    chk('real data untouched: every pre-existing expense, entry, line, bank row, petty cash row and ledger row is identical', $now == $real, json_encode(array_keys(array_diff_assoc($now, $real))));
} catch (Throwable $e) {
    $fail++; echo "EXCEPTION: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n";
}

foreach ($yearSnapshot as $y) { $pdo->prepare('UPDATE tblfiscalyears SET closed=?, deleted=? WHERE ID=?')->execute([$y['closed'], $y['deleted'], $y['ID']]); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId > ?')->execute([$max['journal_entries']]);
foreach ($tables as $t => $k) { $pdo->prepare("DELETE FROM $t WHERE $k > ?")->execute([$max[$t]]); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec('ALTER TABLE tblexpenses AUTO_INCREMENT = ' . ($max['tblexpenses'] + 1));
$pdo->exec('DELETE FROM receipt_number_seq');
$ins = $pdo->prepare('INSERT INTO receipt_number_seq (congregationId,kind,fiscalYearId,lastNo) VALUES (?,?,?,?)');
foreach ($seqSnapshot as $s) { $ins->execute([$s['congregationId'], $s['kind'], $s['fiscalYearId'], $s['lastNo']]); }
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k > {$max[$t]}")->fetchColumn(); }
$seqNow = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$yearsNow = $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC);
echo "\nPASSED $pass FAILED $fail ; leftover rows after cleanup: $left ; sequences restored: " . ($seqNow == $seqSnapshot ? 'yes' : 'NO') . ' ; fiscal years restored: ' . ($yearsNow == $yearSnapshot ? 'yes' : 'NO') . "\n";
exit($fail === 0 ? 0 : 1);

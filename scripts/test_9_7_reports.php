<?php
/**
 * Task 9.7: group income statement (decision 155), journal report, contribution / expense reports (decisions 135-137) and the
 * payment-report ordering script. Fixtures use the year 2098 and the marker "zz t97"; the suite is self-cleaning and CLI-only.
 * It also runs the old-versus-new parity on the real dev data (read-only) and prints it as INFO lines for the task report.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir(__DIR__ . '/../app');
require 'boostrap.php';
require_once 'models/Report.php';
require_once 'models/Parishreport.php';

$pdo = (new Database)->pdo(); $pass = 0; $fail = 0;
$_SESSION['congId'] = 11; $_SESSION['userId'] = 64; $_SESSION['isParish'] = 0;
function ok97($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ($extra === '' ? '' : ": $extra") . "\n"; } }
function info97($t) { echo "INFO $t\n"; }
function q97($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x97($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
function c97($v) { return FinancialReport::cents($v); }
function m97($cents) { return FinancialReport::decimal($cents); }

$created = ['entries' => [], 'reqs' => [], 'mmf' => [], 'exps' => [], 'headers' => [], 'groups' => [], 'districts' => [], 'subs' => [], 'congs' => [], 'suppliers' => [], 'invoices' => [], 'payments' => []];
$cong = 11;

/** an active (or voided) journal entry with lines [[account, 'DEBIT'|'CREDIT', amount], ...] */
function entry97($type, $txn, $date, $deleted, array $lines, $c = 11, $journalNo = null, $reference = null, $description = 'zz t97 entry') {
    global $created;
    $id = x97('INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (?,?,?,?,?,?,?,?)',
              [$c, $date, $type, $txn, $journalNo, $reference, $description, $deleted ? 1 : 0]);
    $created['entries'][] = $id;
    foreach ($lines as $i => [$acc, $dc, $amt, $memo]) { x97('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $acc, $dc, $amt, $memo ?? null, $i + 1]); }
    return $id;
}
function two97($acc1, $acc2, $amt) { return [[$acc1, 'DEBIT', $amt, null], [$acc2, 'CREDIT', $amt, null]]; }

try {
    $db = $pdo;

    // =====================================================================================================================
    echo "== A. group income statement: parity with the old report on the dev data (read-only)\n";
    // =====================================================================================================================
    $periods = ['FY10' => ['2023-10-01', '2024-09-30'], 'FY11' => ['2024-10-01', '2025-09-30'], 'FY12' => ['2025-10-01', '2026-09-30'], 'FY25' => ['2026-10-01', '2027-09-30']];
    $legacy = function ($kind, $id, $from, $to) {
        $c = $kind === 'district' ? ['DistrictId', 'DistrictId', 'districtId'] : ['GroupId', 'GroupId', 'groupId'];
        $req = q97("SELECT IFNULL(SUM(AmountApproved),0) a FROM tblfundrequisition WHERE Deleted=0 AND DontDeduct=1 AND Status=1 AND {$c[0]}=? AND ApprovalDate BETWEEN ? AND ?", [$id, $from, $to])[0]['a'];
        $col = q97("SELECT IFNULL(SUM(Debit),0) a FROM tblmmf WHERE Deleted=0 AND {$c[1]}=? AND TransactionDate BETWEEN ? AND ?", [$id, $from, $to])[0]['a'];
        $exp = [];
        foreach (q97("SELECT accountId, SUM(amount) a FROM tblexpenses WHERE {$c[2]}=? AND expenseDate BETWEEN ? AND ? AND deleted=0 AND status=1 GROUP BY accountId", [$id, $from, $to]) as $r) { $exp[(int) $r['accountId']] = c97($r['a']); }
        return ['req' => c97($req), 'coll' => c97($col), 'exp' => $exp];
    };
    $entities = [];
    foreach (q97('SELECT ID FROM tblgroups WHERE congregationId = ? AND deleted = 0', [$cong]) as $r) { $entities[] = ['group', (int) $r['ID']]; }
    foreach (q97('SELECT ID FROM tbldistricts WHERE congregationId = ? AND deleted = 0', [$cong]) as $r) { $entities[] = ['district', (int) $r['ID']]; }
    $bad = []; $dev = []; $checked = 0; $exclTotals = ['req' => [0, 0], 'coll' => [0, 0], 'exp' => [0, 0]];
    foreach ($periods as $plabel => [$from, $to]) {
        foreach ($entities as [$kind, $id]) {
            $st = GroupIncomeStatement::statement($db, $cong, $kind, $id, $from, $to);
            $ex = GroupIncomeStatement::excluded($st['sources']);
            $old = $legacy($kind, $id, $from, $to);
            $inc = GroupIncomeStatement::included($st['sources']);
            foreach (['req', 'coll'] as $cat) {
                $newSum = 0; $devSum = 0;
                foreach ($inc as $r) { if ($r['cat'] === $cat) { $newSum += $r['amount']; $devSum += $r['amount'] - $r['src']; if ($r['amount'] !== $r['src']) { $dev[] = "$cat#{$r['id']}"; } } }
                // old - new must be exactly the excluded rows' own amounts less what the entries differ by
                if ($old[$cat] - $newSum !== $ex[$cat]['amount'] - $devSum) { $bad[] = "$kind $id $plabel $cat old {$old[$cat]} new $newSum excluded {$ex[$cat]['amount']} dev $devSum"; }
                $exclTotals[$cat][0] += $ex[$cat]['count']; $exclTotals[$cat][1] += $ex[$cat]['amount'];
                $checked++;
            }
            $newExp = []; $devExp = 0;
            foreach ($inc as $r) { if ($r['cat'] === 'exp') { $newExp[$r['accountId']] = ($newExp[$r['accountId']] ?? 0) + $r['amount']; $devExp += $r['amount'] - $r['src']; if ($r['amount'] !== $r['src']) { $dev[] = "exp#{$r['id']}"; } } }
            $exExp = [];
            foreach ($ex['exp']['rows'] as $r) { $exExp[$r['accountId']] = ($exExp[$r['accountId']] ?? 0) + $r['src']; }
            foreach (array_unique(array_merge(array_keys($old['exp']), array_keys($newExp))) as $acc) {
                $diffAcc = ($old['exp'][$acc] ?? 0) - ($newExp[$acc] ?? 0);
                $inAcc = 0; foreach ($inc as $r) { if ($r['cat'] === 'exp' && $r['accountId'] === $acc) { $inAcc += $r['amount'] - $r['src']; } }
                if ($diffAcc !== ($exExp[$acc] ?? 0) - $inAcc) { $bad[] = "$kind $id $plabel exp account $acc old " . ($old['exp'][$acc] ?? 0) . ' new ' . ($newExp[$acc] ?? 0); }
                $checked++;
            }
            $exclTotals['exp'][0] += $ex['exp']['count']; $exclTotals['exp'][1] += $ex['exp']['amount'];
            if ($kind === 'group' && in_array($id, [40, 45, 49], true) && in_array($plabel, ['FY11', 'FY25'], true)) {
                info97(sprintf('parity %s group %d: old req %s coll %s exp %s | new req %s coll %s exp %s | excluded req %d/%s coll %d/%s exp %d/%s', $plabel, $id,
                    m97($old['req']), m97($old['coll']), m97(array_sum($old['exp'])),
                    m97(array_sum(array_map(function ($r) { return $r['cat'] === 'req' ? $r['amount'] : 0; }, $inc))), m97(array_sum(array_map(function ($r) { return $r['cat'] === 'coll' ? $r['amount'] : 0; }, $inc))), m97($st['expenses']['total']),
                    $ex['req']['count'], m97($ex['req']['amount']), $ex['coll']['count'], m97($ex['coll']['amount']), $ex['exp']['count'], m97($ex['exp']['amount'])));
            }
        }
    }
    ok97("every group and district x 4 periods x category ($checked comparisons): old minus new is exactly the excluded rows (and the known double entries)", !$bad, implode(' | ', array_slice($bad, 0, 5)));
    sort($dev); $devU = array_values(array_unique($dev));
    ok97('the only included rows whose entry differs from the source amount are the two doubled approvals (requisitions 58 and 201, runbook 2.2 / 2.3)', $devU === ['req#201', 'req#58'] || $devU === ['req#58', 'req#201'] || $devU === ['req#201'] || $devU === ['req#58'] || $devU === [], json_encode($devU));
    info97('excluded rows over all groups/districts and the four periods: requisitions ' . $exclTotals['req'][0] . ' rows ' . m97($exclTotals['req'][1]) . ', collections ' . $exclTotals['coll'][0] . ' rows ' . m97($exclTotals['coll'][1]) . ', expenses ' . $exclTotals['exp'][0] . ' rows ' . m97($exclTotals['exp'][1]));
    $exp10 = GroupIncomeStatement::excluded(GroupIncomeStatement::sources($db, $cong, 'group', 45, '2023-10-01', '2024-09-30'));
    ok97('group 45 in 2023-2024 shows the three old expenses without a journal entry (vouchers 228, 231, 233): 35,426.00', $exp10['exp']['count'] === 3 && $exp10['exp']['amount'] === 3542600, json_encode([$exp10['exp']['count'], $exp10['exp']['amount']]));
    $s40 = GroupIncomeStatement::statement($db, $cong, 'group', 40, '2024-10-01', '2025-09-30');
    ok97('group 40, 2024-2025: collections 220,975.00 and expenses 99,300.00 equal the old report', $s40['revenue']['total'] === 22097500 && $s40['expenses']['total'] === 9930000 && $s40['result'] === 22097500 - 9930000);
    $tbl = q97('SELECT COUNT(*) n FROM tblledger')[0]['n'];
    ok97('the statement does not read tblledger (it holds ' . $tbl . ' rows; the sources are the document tables and the journal)', strpos(file_get_contents(__DIR__ . '/../app/libraries/GroupIncomeStatement.php'), 'tblledger') === false);

    // =====================================================================================================================
    echo "== B. group income statement: fixtures (2098)\n";
    // =====================================================================================================================
    $other = x97("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t97 other', 0, 0)"); $created['congs'][] = $other;
    $G = x97("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zz97', 'zz t97 group <b>x</b>', 1, 0, ?)", [$cong]); $created['groups'][] = $G;
    $G2 = x97("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zz97b', 'zz t97 empty group', 1, 0, ?)", [$cong]); $created['groups'][] = $G2;
    $GO = x97("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zz97o', 'zz t97 foreign group', 1, 0, ?)", [$other]); $created['groups'][] = $GO;
    $GD = x97("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zz97d', 'zz t97 deleted group', 1, 1, ?)", [$cong]); $created['groups'][] = $GD;
    $D = x97("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t97 district', 0, ?)", [$cong]); $created['districts'][] = $D;
    $DO = x97("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t97 foreign district', 0, ?)", [$other]); $created['districts'][] = $DO;
    $INC = 30; $CASH = 34; $EXA = 26; $EXB = 27;
    $sub = x97("INSERT INTO tblbanksubaccounts (AccountName, BankId, AccountId, GroupDistrict, GroupId, DistrictId, Deleted) VALUES ('zz t97 sub', 105, ?, 'group', ?, NULL, 0)", [$INC, $G]); $created['subs'][] = $sub;
    $FROM = '2098-01-01'; $TO = '2098-12-31';
    $reqIns = function ($amount, $status, $dont, $deleted, $c = 11, $date = '2098-02-10', $entity = null, $type = 'group', $purpose = 'zz t97 requisition') use ($G, &$created) {
        $id = x97('INSERT INTO tblfundrequisition (ReqNo, RequisitionDate, RequestType, DistrictId, GroupId, Purpose, AmountRequested, AmountApproved, ApprovalDate, Status, DontDeduct, Deleted, CongregationId) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [970000 + count($created['reqs']), $date, $type, $type === 'district' ? ($entity ?? 0) : null, $type === 'group' ? ($entity ?? $G) : null, $purpose, $amount, $amount, $date, $status, $dont, $deleted, $c]);
        $created['reqs'][] = $id; return $id;
    };
    // requisitions
    $rA = $reqIns('10000.00', 1, 1, 0, 11, '2098-02-10', null, 'group', 'zz t97 alpha one');   entry97(12, $rA, '2098-02-11', false, two97($CASH, 100, '10000.00'));
    $rB = $reqIns('5000.00', 1, 1, 0);                                                          entry97(12, $rB, '2098-02-10', true, two97($CASH, 100, '5000.00'));          // voided entry
    $rC = $reqIns('7000.00', 1, 1, 0);                                                                                                                                     // no entry
    $rD = $reqIns('300.00', 1, 0, 0);                                                           entry97(12, $rD, '2098-02-10', false, two97($CASH, 100, '300.00'));          // DontDeduct = 0
    $rE = $reqIns('400.00', 0, 1, 0);                                                           entry97(12, $rE, '2098-02-10', false, two97($CASH, 100, '400.00'));          // not approved
    $rF = $reqIns('500.00', 1, 1, 1);                                                           entry97(12, $rF, '2098-02-10', false, two97($CASH, 100, '500.00'));          // deleted
    $rG = $reqIns('9500.00', 1, 1, 0, 11, '2098-03-01', null, 'group', 'zz t97 alpha two');      entry97(12, $rG, '2098-03-01', false, two97($CASH, 100, '9000.00'));        // entry differs: the entry wins
    $rH = $reqIns('600.00', 1, 1, 0, $other);                                                   entry97(12, $rH, '2098-02-10', false, two97($CASH, 100, '600.00'), $other);  // another congregation's row
    $rI = $reqIns('1000.00', 1, 1, 0, 11, '2098-04-01', $D, 'district', 'zz t97 district req'); entry97(12, $rI, '2098-04-01', false, two97($CASH, 100, '1000.00'));
    $rJ = $reqIns('800.00', 1, 1, 0, 11, '2099-01-01');                                         entry97(12, $rJ, '2099-01-01', false, two97($CASH, 100, '800.00'));          // outside the period
    // collections: group collections (17) and receipt lines (1)
    $mmfIns = function ($type, $debit, $deleted, $txnId, $c = 11, $date = '2098-05-05', $group = null, $district = null, $tt = 17, $narr = 'zz t97 collection') use (&$created, $G, $sub) {
        $id = x97('INSERT INTO tblmmf (TransactionDate, `Type`, DistrictId, GroupId, Debit, Credit, SubAccountId, Narration, TransactionType, TransactionId, CongregationId, Deleted) VALUES (?,?,?,?,?,0,?,?,?,?,?,?)',
            [$date, $type, $district, $group === false ? null : ($group ?? $G), $debit, $tt === 1 ? $sub : null, $narr, $tt, $txnId, $c, $deleted]);
        $created['mmf'][] = $id; return $id;
    };
    $m1 = $mmfIns('group', '3000.00', 0, null, 11, '2098-05-05', null, null, 17, 'zz t97 beta one'); entry97(17, $m1, '2098-05-06', false, two97($CASH, $INC, '3000.00'));
    $m2 = $mmfIns('group', '2000.00', 0, null);                                                         // no entry
    $m3 = $mmfIns('group', '4000.00', 0, null);                                                         entry97(17, $m3, '2098-05-05', true, two97($CASH, $INC, '4000.00'));   // voided entry
    $m4 = $mmfIns('group', '900.00', 1, null);                                                          entry97(17, $m4, '2098-05-05', false, two97($CASH, $INC, '900.00'));    // mmf row deleted
    $m5 = $mmfIns('group', '100.00', 0, 55, 11, '2098-05-05', null, null, 11);                          // another transaction type with a debit: no journal rule
    $m6 = $mmfIns('district', '300.00', 0, null, 11, '2098-05-05', false, $D, 17);                      entry97(17, $m6, '2098-05-05', false, two97($CASH, $INC, '300.00'));
    $hd = x97("INSERT INTO tblcontributions_header (fiscalYearId, postedBy, postedDate, status, Deleted, receiptNo, congregationId) VALUES (12, 64, '2098-06-01', 1, 0, 'ZZ97-1', ?)", [$cong]); $created['headers'][] = $hd;
    $r1 = $mmfIns('group', '1001.00', 0, $hd, 11, '2098-06-01', null, null, 1, 'zz t97 gamma receipt'); $r2 = $mmfIns('group', '500.00', 0, $hd, 11, '2098-06-01', null, null, 1, 'zz t97 gamma receipt');
    $r3 = $mmfIns('group', '800.00', 0, $hd, 11, '2098-06-01', null, null, 1, 'zz t97 gamma receipt');   // a third line, but the entry has only two credit lines on the sub account's income account
    entry97(1, $hd, '2098-06-01', false, [[$CASH, 'DEBIT', '9300.00', null], [$INC, 'CREDIT', '1000.00', null], [93, 'CREDIT', '2000.00', null], [$INC, 'CREDIT', '500.00', null], [94, 'CREDIT', '5800.00', null]]);
    // expenses
    $expIns = function ($acc, $amount, $status, $deleted, $c = 11, $group = null, $district = null, $date = '2098-07-07', $voucher = null) use (&$created, $G) {
        $id = x97("INSERT INTO tblexpenses (fiscalYearId, voucherNo, expenseType, expenseDate, accountId, groupId, districtId, paymethodId, amount, narration, paymentReference, status, postedBy, postedDate, deleted, congregationId) VALUES (12,?,?,?,?,?,?,1,?,?,?,?,64,'2098-07-07',?,?)",
            [$voucher ?? (970000 + count($created['exps'])), $district ? 3 : 2, $date, $acc, $district ? null : ($group ?? $G), $district, $amount, 'zz t97 delta ' . count($created['exps']), 'zz t97 ref', $status, $deleted, $c]);
        $created['exps'][] = $id; return $id;
    };
    $e1 = $expIns($EXA, '2500.00', 1, 0); entry97(2, $e1, '2098-07-08', false, two97($EXA, $CASH, '2000.00'));    // the entry says 2,000
    $e2 = $expIns($EXA, '1000.00', 1, 0); entry97(2, $e2, '2098-07-07', false, two97($EXA, $CASH, '1000.00'));
    $e3 = $expIns($EXB, '700.00', 1, 0);  entry97(2, $e3, '2098-07-07', false, two97($EXB, $CASH, '700.00'));
    $e4 = $expIns($EXB, '444.00', 1, 0);  entry97(2, $e4, '2098-07-07', true, two97($EXB, $CASH, '444.00'));       // voided
    $e5 = $expIns($EXB, '555.00', 0, 0);  entry97(2, $e5, '2098-07-07', false, two97($EXB, $CASH, '555.00'));       // not approved
    $e6 = $expIns($EXB, '666.00', 1, 1);  entry97(2, $e6, '2098-07-07', false, two97($EXB, $CASH, '666.00'));       // deleted
    $e7 = $expIns($EXB, '777.00', 1, 0, $other);  entry97(2, $e7, '2098-07-07', false, two97($EXB, $CASH, '777.00'), $other);   // another congregation's row on this group
    $e8 = $expIns($EXA, '400.00', 1, 0, 11, null, $D); entry97(2, $e8, '2098-07-07', false, two97($EXA, $CASH, '400.00'));        // district expense

    $st = GroupIncomeStatement::statement($db, $cong, 'group', $G, $FROM, $TO);
    $revRows = []; foreach ($st['revenue']['rows'] as $r) { $revRows[$r['key']] = $r['amount']; }
    ok97('requisitions: only approved, DontDeduct = 1, undeleted rows with an active entry; the amount is the entry\'s (10,000.00 + 9,000.00, not 9,500.00)', ($revRows['requisitions'] ?? null) === 1900000, json_encode($revRows));
    ok97('collections: group collection 3,000.00 + receipt lines 1,000.00 and 500.00 from the entry\'s credit lines (the source says 1,001.00)', ($revRows['collections'] ?? null) === 450000, json_encode($revRows));
    ok97('revenue total, expense total and result add up', $st['revenue']['total'] === 2350000 && $st['expenses']['total'] === 370000 && $st['result'] === 1980000, json_encode([$st['revenue']['total'], $st['expenses']['total'], $st['result']]));
    $expRows = []; foreach ($st['expenses']['rows'] as $r) { $expRows[$r['account']] = $r['amount']; }
    ok97('expenses by account: 26 = 3,000.00 (2,000.00 from the entry + 1,000.00), 27 = 700.00; voided / unapproved / deleted / foreign rows are out', $expRows === [$EXA => 300000, $EXB => 70000], json_encode($expRows));
    $ex = GroupIncomeStatement::excluded($st['sources']);
    ok97('excluded requisitions: the voided-entry row and the row without an entry (2 rows, 12,000.00)', $ex['req']['count'] === 2 && $ex['req']['amount'] === 1200000, json_encode([$ex['req']['count'], $ex['req']['amount']]));
    ok97('excluded collections: no entry, voided entry, third receipt line, and the debit row of another type (4 rows, 2,000 + 4,000 + 800 + 100)', $ex['coll']['count'] === 4 && $ex['coll']['amount'] === 690000, json_encode([$ex['coll']['count'], $ex['coll']['amount']]));
    ok97('excluded expenses: the voided entry (1 row, 444.00)', $ex['exp']['count'] === 1 && $ex['exp']['amount'] === 44400, json_encode([$ex['exp']['count'], $ex['exp']['amount']]));
    $fr = GroupIncomeStatement::detail($db, $cong, 'group', $G, $FROM, $TO, 'requisitions');
    $fc = GroupIncomeStatement::detail($db, $cong, 'group', $G, $FROM, $TO, 'collections');
    $fx = GroupIncomeStatement::detail($db, $cong, 'group', $G, $FROM, $TO, 'expenses', $EXA);
    ok97('drill totals equal the figure clicked (requisitions, collections, expenses of account 26)', array_sum(array_column($fr, 'amount')) === 1900000 && array_sum(array_column($fc, 'amount')) === 450000 && array_sum(array_column($fx, 'amount')) === 300000);
    ok97('drill rows carry date, source no, narration, amount; requisition number and voucher number are shown', in_array('Req ' . (970000), array_column($fr, 'no'), true) && count($fx) === 2 && strpos($fx[0]['no'], 'Voucher ') === 0 && $fr[0]['narr'] === 'zz t97 alpha one');
    $links = array_column($fc, 'link', 'no');
    ok97('source links come from SOURCE_ROUTES: the receipt line opens the receipt, the group collection opens the collection, an expense its voucher, a requisition has none',
         ($links['ZZ97-1'] ?? null) === 'contributions/edit/' . $hd && $links['Collection ' . $m1] === 'groupcollections/edit/' . $m1 && $fx[0]['link'] === 'expenses/print/' . $e1 && $fr[0]['link'] === null, json_encode($links));
    $spec = ['title' => 't', 'lines' => [], 'columns' => [['key' => 'date', 'label' => 'Date', 'type' => 'date'], ['key' => 'no', 'label' => 'No', 'type' => 'link'], ['key' => 'narr', 'label' => 'Narration', 'type' => 'text'], ['key' => 'amount', 'label' => 'Amount', 'type' => 'money']], 'total' => 'amount', 'file' => 'x'];
    $s1 = ReportList::slice($spec, $fr, 1, 1, '');
    $s2 = ReportList::slice($spec, $fr, 2, 1, '');
    ok97('paging: two requisitions, page size 1 gives two pages with one row each', $s1['pages'] === 2 && count($s1['rows']) === 1 && count($s2['rows']) === 1 && $s1['rows'][0]['no'] !== $s2['rows'][0]['no']);
    $sr = ReportList::slice($spec, $fr, 1, 50, 'alpha two');
    ok97('search finds by narration and totals the matching rows only (9,000.00)', $sr['matching'] === 1 && $sr['total'] === 900000);
    ok97('search by amount (10,000.00 and 10000.00) and by source number', ReportList::slice($spec, $fr, 1, 50, '10,000.00')['matching'] === 1 && ReportList::slice($spec, $fr, 1, 50, '10000.00')['matching'] === 1 && ReportList::slice($spec, $fc, 1, 50, 'ZZ97')['matching'] === 2);
    $all = ReportList::slice($spec, $fr, 1, 0, '');
    ok97('page size 0 (the Excel case) returns every matching row', count($all['rows']) === 2 && $all['pages'] === 1);
    $sheet = ReportList::sheetRows(['title' => 'T'] + $spec, $all, '');
    ok97('worksheet rows hold real money cells and a total row', count($sheet) >= 6 && end($sheet)[3]['t'] === 'm' && end($sheet)[3]['v'] === 1900000);

    // district vs group
    $sd = GroupIncomeStatement::statement($db, $cong, 'district', $D, $FROM, $TO);
    ok97('district statement: requisition 1,000.00 + collection 300.00 - expense 400.00 (District labels), the group rows do not leak in', $sd['revenue']['total'] === 130000 && $sd['expenses']['total'] === 40000 && $sd['result'] === 90000 && $sd['revenue']['rows'][0]['label'] === 'District Requisitions', json_encode($sd['revenue']));
    ok97('the group statement does not include the district rows (collections stay 4,500.00)', ($revRows['collections'] ?? 0) === 450000);
    $empty = GroupIncomeStatement::statement($db, $cong, 'group', $G2, $FROM, $TO);
    ok97('zero rule: a group with no figures shows no rows but the totals (0.00)', $empty['revenue']['rows'] === [] && $empty['expenses']['rows'] === [] && $empty['revenue']['total'] === 0 && $empty['result'] === 0);
    ok97('ownership: a foreign group, a foreign district, a deleted group, a missing id and an unknown kind are refused', GroupIncomeStatement::owner($db, $cong, 'group', $GO) === null && GroupIncomeStatement::owner($db, $cong, 'district', $DO) === null && GroupIncomeStatement::owner($db, $cong, 'group', $GD) === null && GroupIncomeStatement::owner($db, $cong, 'group', 999999) === null && GroupIncomeStatement::owner($db, $cong, 'user', $G) === null);
    ok97('ownership: the congregation\'s own group and district are accepted', GroupIncomeStatement::owner($db, $cong, 'group', $G)['id'] === $G && GroupIncomeStatement::owner($db, $cong, 'district', $D)['id'] === $D);
    ok97('ownership is checked before anything is read: the other congregation cannot select this congregation\'s group even though its own requisition (600.00) and expense (777.00) rows carry that group id', GroupIncomeStatement::owner($db, $other, 'group', $G) === null && GroupIncomeStatement::owner($db, $other, 'district', $D) === null);
    $chk = GroupIncomeStatement::choices($db, $cong);
    ok97('the screen offers this congregation\'s active groups and its districts only', in_array($G, array_column($chk['groups'], 'ID')) && !in_array($GO, array_column($chk['groups'], 'ID')) && !in_array($GD, array_column($chk['groups'], 'ID')) && in_array($D, array_column($chk['districts'], 'ID')) && !in_array($DO, array_column($chk['districts'], 'ID')));
    $shaped = GroupIncomeStatement::shape($st, 'group', ['id' => $G, 'name' => 'zz t97 group <b>x</b>'], $FROM, $TO);
    ok97('the statement JSON carries the name as data (escaping is the client\'s textContent) and bracketed negatives', $shaped['name'] === 'Zz T97 Group <b>x</b>' && is_string($shaped['result']['amount']));
    $sheetS = GroupIncomeStatement::sheetRows($shaped);
    $textCells = []; foreach ($sheetS as $row) { foreach ($row as $cell) { if ($cell && $cell['t'] === 's') { $textCells[] = $cell['v']; } } }
    ok97('Excel statement: names only, no congregation heading, title Income Statement, the labels of the old layout', in_array('Income Statement', $textCells, true) && in_array('Revenue Total', $textCells, true) && in_array('Expense Total', $textCells, true) && in_array('Profit/Loss', $textCells, true) && !preg_grep('/pcea|bethany/i', $textCells));

    // =====================================================================================================================
    echo "== C. journal report\n";
    // =====================================================================================================================
    $jr = [];
    $jr[1] = entry97(5, 971001, '2098-03-01', false, [[98, 'DEBIT', '1234.50', 'zz t97 petty memo'], [$INC, 'CREDIT', '1234.50', 'zz t97 income memo <b>']], 11, 971001, 'zz t97 ref one', 'zz t97 first journal');
    $jr[2] = entry97(5, 971002, '2098-03-15', true, two97(98, $INC, '50.00'), 11, 971002, 'zz t97 voided ref', 'zz t97 voided journal');        // voided
    $jr[3] = entry97(1, 971003, '2098-03-15', false, two97(98, $INC, '60.00'), 11, null, 'zz t97 receipt', 'zz t97 receipt entry');             // not a journal
    $jr[4] = entry97(5, 971004, '2098-03-15', false, two97(98, $INC, '70.00'), $other, 971004, 'zz t97 other ref', 'zz t97 other journal'); // another congregation
    $jr[5] = entry97(5, 971005, '2098-03-31', false, [[98, 'DEBIT', '10.00', null], [$INC, 'DEBIT', '5.25', null], [$CASH, 'CREDIT', '15.25', null]], 11, 971005, null, 'zz t97 last day');   // boundary, no reference
    $jr[6] = entry97(5, 971006, '2098-04-01', false, two97(98, $INC, '80.00'), 11, 971006, 'zz t97 next month', 'zz t97 outside');                 // after the range
    $js = JournalReport::journals($db, $cong, '2098-03-01', '2098-03-31');
    ok97('journal report lists the active journals of the congregation in the range only (no voided journal, no receipt entry, no other congregation, boundaries inclusive)', array_column($js, 'journalNo') === [971001, 971005], json_encode(array_column($js, 'journalNo')));
    $balanced = true; foreach ($js as $j) { if ($j['debits'] !== $j['credits']) { $balanced = false; } }
    ok97('debits equal credits in every journal block (123,450 and 1,525 cents)', $balanced && $js[0]['debits'] === 123450 && $js[1]['debits'] === 1525);
    ok97('each journal carries its reference (missing one is blank) and description; lines have the account name and memo', $js[0]['reference'] === 'zz t97 ref one' && $js[1]['reference'] === '' && $js[0]['description'] === 'zz t97 first journal' && $js[0]['lines'][0]['memo'] === 'zz t97 petty memo' && count($js[1]['lines']) === 3);
    ok97('account labels are names only, never the type > parent > name path', strpos($js[0]['lines'][0]['account'], '>') === false && $js[0]['lines'][0]['account'] !== '', $js[0]['lines'][0]['account']);
    $sl = function ($s) use ($js) { return JournalReport::slice($js, 1, 50, $s)['matching']; };
    ok97('search: journal number, reference, narration, memo, account, amount (1,234.50 and 1234.50)', $sl('971005') === 1 && $sl('ref one') === 1 && $sl('last day') === 1 && $sl('income memo') === 1 && $sl('petty cash') >= 1 && $sl('1,234.50') === 1 && $sl('1234.50') === 1 && $sl('nothing-matches') === 0);
    $p1 = JournalReport::slice($js, 1, 1, ''); $p2 = JournalReport::slice($js, 2, 1, '');
    ok97('paging works on whole journals: page size 1 gives two pages and each page keeps all lines of its journal', $p1['pages'] === 2 && count($p1['journals'][0]['lines']) === 2 && count($p2['journals'][0]['lines']) === 3);
    $shapeJ = JournalReport::shape($p1, '2098-03-01', '2098-03-31', '');
    ok97('JSON keeps markup as data and shows blocks with their totals', $shapeJ['journals'][0]['lines'][1]['memo'] === 'zz t97 income memo <b>' && $shapeJ['journals'][0]['debits'] === '1,234.50' && $shapeJ['journals'][0]['balanced'] === true);
    $sheetJ = JournalReport::sheetRows(JournalReport::slice($js, 1, 0, ''), '2098-03-01', '2098-03-31', '');
    ok97('Excel rows: every line of every matching journal, a total row per journal and the grand total', count($sheetJ) === 4 + 5 + 2 + 1, (string) count($sheetJ));
    $all5 = q97("SELECT COUNT(*) n FROM journal_entries WHERE transactionTypeId=5 AND deleted=0 AND congregationId=? AND entryDate BETWEEN '2023-09-01' AND '2026-12-31' AND transactionId<>0", [$cong])[0]['n'];
    $devJ = JournalReport::journals($db, $cong, '2023-09-01', '2026-12-31'); $ba = 0; foreach ($devJ as $j) { if ($j['debits'] !== $j['credits']) { $ba++; } }
    ok97('dev data: every journal of the congregation is listed once and balances (' . count($devJ) . ' journals)', count($devJ) === (int) q97("SELECT COUNT(*) n FROM journal_entries e WHERE e.transactionTypeId=5 AND e.deleted=0 AND e.congregationId=? AND e.entryDate BETWEEN '2023-09-01' AND '2026-12-31' AND EXISTS (SELECT 1 FROM journal_lines l WHERE l.journalEntryId=e.ID)", [$cong])[0]['n'] && $ba === 0);
    $oldRows = q97("SELECT COUNT(*) n, SUM(debit) d, SUM(credit) c FROM tblledger WHERE isJournal=1 AND congregationId=? AND transactionDate BETWEEN '2023-09-01' AND '2026-12-31'", [$cong])[0];
    $oldActive = q97("SELECT COUNT(*) n FROM tblledger WHERE isJournal=1 AND deleted=0 AND congregationId=? AND transactionDate BETWEEN '2023-09-01' AND '2026-12-31'", [$cong])[0]['n'];
    $newLines = 0; $newDr = 0; foreach ($devJ as $j) { $newLines += count($j['lines']); $newDr += $j['debits']; }
    info97("journal report parity: old mirror rows (voided included) {$oldRows['n']}, old mirror active rows $oldActive, new lines $newLines; old debit total " . $oldRows['d'] . ', new debit total ' . m97($newDr));
    ok97('dev data: the new line count equals the old mirror\'s ACTIVE rows (the old query also listed the deleted ones)', $newLines === (int) $oldActive, "new $newLines old active $oldActive");

    // =====================================================================================================================
    echo "== D. contribution and expense reports\n";
    // =====================================================================================================================
    $report = new Report; $parish = new Parishreport;
    $acA = 30; $acB = 31;
    $cat3 = $acA;
    $mkHeader = function ($c, $status, $deleted, $no) use (&$created) { $id = x97("INSERT INTO tblcontributions_header (fiscalYearId, postedBy, postedDate, status, Deleted, receiptNo, congregationId) VALUES (12, 64, '2098-08-01', ?, ?, ?, ?)", [$status, $deleted, $no, $c]); $created['headers'][] = $id; return $id; };
    $mkDetail = function ($h, $date, $acc, $amount, $category, $who = []) {
        return x97('INSERT INTO tblcontributions_details (HeaderId, contributionDate, contributionTypeId, paymentMethodId, amount, category, contributor, contributotGroup, contributotDistrict, contributotService, contributotCong, paymentReference, narration, incomeType, forGroup) VALUES (?,?,?,1,?,?,?,?,?,?,?,?,?,1,0)',
            [$h, $date, $acc, $amount, $category, $who['member'] ?? null, $who['group'] ?? null, $who['district'] ?? null, $who['service'] ?? null, $who['cong'] ?? null, 'zz t97 pay ref', 'zz t97']);
    };
    $dA = x97("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t97 same name', 0, ?)", [$cong]); $created['districts'][] = $dA;
    $dB = x97("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t97 same name', 0, ?)", [$cong]); $created['districts'][] = $dB;
    $gA = x97("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zz97c', 'zz t97 same name', 1, 0, ?)", [$cong]); $created['groups'][] = $gA;
    $gB = x97("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zz97e', 'zz t97 same name', 1, 0, ?)", [$cong]); $created['groups'][] = $gB;
    $hOk = $mkHeader($cong, 1, 0, 'ZZ97-OK'); $hDel = $mkHeader($cong, 1, 1, 'ZZ97-DEL'); $hPend = $mkHeader($cong, 0, 0, 'ZZ97-PEND'); $hOth = $mkHeader($other, 1, 0, 'ZZ97-OTH');
    $dd = ['ok1' => $mkDetail($hOk, '2098-08-02', $acA, '1000.00', 3, ['district' => $dA]), 'ok2' => $mkDetail($hOk, '2098-08-03', $acB, '250.50', 3, ['district' => $dB]),
           'ok3' => $mkDetail($hOk, '2098-08-04', $acA, '75.00', 2, ['group' => $gA]), 'ok4' => $mkDetail($hOk, '2098-08-05', $acA, '25.00', 2, ['group' => $gB]),
           'del' => $mkDetail($hDel, '2098-08-02', $acA, '3333.00', 3, ['district' => $dA]), 'pend' => $mkDetail($hPend, '2098-08-02', $acA, '4444.00', 3, ['district' => $dA]),
           'oth' => $mkDetail($hOth, '2098-08-02', $acA, '5555.00', 3, ['district' => $dA])];
    $R = ['type' => 1, 'start' => '2098-08-01', 'end' => '2098-08-31', 'account' => []];
    $rows1 = $report->GetContributions($R);
    ok97('contributions (all): only the approved, undeleted receipts of this congregation (1,000.00 + 250.50 + 75.00 + 25.00); the deleted, unapproved and other-congregation details are out', array_sum(array_column($rows1, 'amount')) === 135050 && count($rows1) === 4, json_encode(array_column($rows1, 'amount')));
    // Task 9.8: sp_contribitions_report_all and sp_expenses_all are dropped. The old routine was a header x detail cross join with no Deleted filter; its result is reproduced below by an equivalent query.
    ok97('the report no longer calls the old cross-join routine', strpos(file_get_contents(__DIR__ . '/../app/models/Report.php'), 'CALL sp_contribitions') === false);
    $lgSum = (int) q97("SELECT IFNULL(SUM(CAST(ROUND(d.amount*100) AS SIGNED)),0) s FROM tblcontributions_details d WHERE d.contributionDate BETWEEN '2098-08-01' AND '2098-08-31' AND EXISTS (SELECT 1 FROM tblcontributions_header h WHERE h.congregationId = ? AND h.status = 1)", [$cong])[0]['s'];
    ok97('what that cross join returns (every detail in the range as long as the congregation has one approved receipt): ' . m97($lgSum) . ' instead of 1,350.50 - it includes the deleted, unapproved and other-congregation details', $lgSum === 135050 + 333300 + 444400 + 555500, m97($lgSum));
    $rows2 = $report->GetContributions(['type' => 2, 'start' => '2098-08-01', 'end' => '2098-08-31', 'account' => [$acB]]);
    ok97('contributions by account: the account filter applies, with the header join (250.50)', count($rows2) === 1 && $rows2[0]['amount'] === 25050 && $rows2[0]['receipt'] === 'ZZ97-OK');
    $rows3 = $report->GetContributions(['type' => 3, 'start' => '2098-08-01', 'end' => '2098-08-31', 'account' => []]);
    $byName = []; foreach ($rows3 as $r) { $byName[] = $r['amount']; }
    ok97('contributions by district: grouped by district ID (two districts with one name stay two rows), approved and undeleted only', $byName === [100000, 25050] || $byName === [25050, 100000], json_encode($rows3));
    ok97('contributions rows carry the receipt number and the receipt link; money is integer cents', $rows1[0]['receipt'] === 'ZZ97-OK' && strpos((string) $rows1[0]['link'], 'contributions/edit/') === 0 && is_int($rows1[0]['amount']));
    $xe = [];   // expenses
    $mkExp = function ($c, $acc, $amount, $status, $deleted) use ($mkExp, &$created) { $id = x97("INSERT INTO tblexpenses (fiscalYearId, voucherNo, expenseType, expenseDate, accountId, groupId, districtId, paymethodId, amount, narration, paymentReference, status, postedBy, postedDate, deleted, congregationId) VALUES (12,?,1,'2098-09-09',?,NULL,NULL,1,?,'zz t97 exp','zz t97 xref',?,64,'2098-09-09',?,?)", [980000 + count($created['exps']), $acc, $amount, $status, $deleted, $c]); $created['exps'][] = $id; return $id; };
    $mkExp($cong, $EXA, '100.00', 1, 0); $mkExp($cong, $EXA, '200.00', 0, 0); $mkExp($cong, $EXA, '400.00', 1, 1); $mkExp($cong, $EXB, '800.00', 1, 0); $mkExp($other, $EXA, '1600.00', 1, 0);
    $E1 = $report->GetExpenses(['type' => 1, 'start' => '2098-09-01', 'end' => '2098-09-30', 'account' => []]);
    ok97('expenses (all): approved and undeleted of this congregation only (100.00 + 800.00)', array_sum(array_column($E1, 'amount')) === 90000 && count($E1) === 2, json_encode(array_column($E1, 'amount')));
    $E2 = $report->GetExpenses(['type' => 2, 'start' => '2098-09-01', 'end' => '2098-09-30', 'account' => [$EXB]]);
    ok97('expenses by account: the filter applies and the cost centre of a church expense is LCC', count($E2) === 1 && $E2[0]['amount'] === 80000 && $E2[0]['costcenter'] === 'LCC' && strpos((string) $E2[0]['link'], 'expenses/print/') === 0);
    $lge = q97("SELECT e.amount FROM tblexpenses e INNER JOIN tblaccounttypes a ON e.accountId = a.ID INNER JOIN tblpaymentmethods p ON e.paymethodId = p.ID LEFT JOIN tblgroups g ON e.groupId = g.ID WHERE e.congregationId = ? AND e.expenseDate BETWEEN ? AND ? AND e.status = 1 AND e.deleted = 0", [$cong, '2098-09-01', '2098-09-30']);
    ok97('expenses (all) equals the query of the old routine sp_expenses_all (now dropped) for the same range (its filters were already right)', count($lge) === count($E1));

    // parish
    $pData = ['congregations' => [$cong, $other], 'accounts' => [$acA, $acB], 'start' => '2098-08-01', 'end' => '2098-08-31'];
    $pc = $parish->GetContributions($pData);
    $byC = []; foreach ($pc as $r) { $byC[$r['congregation']] = ($byC[$r['congregation']] ?? 0) + $r['amount']; }
    ok97('parish contributions: approved, undeleted headers only, by the receipt\'s congregation (this congregation 1,350.50, the other one 5,555.00)', array_values($byC) !== [] && ($byC[strtoupper(trim(q97('SELECT CongregationName FROM tblcongregation WHERE ID=?', [$cong])[0]['CongregationName']))] ?? null) === 135050 && ($byC['ZZ T97 OTHER'] ?? null) === 555500, json_encode($byC));
    $sumPc = array_sum(array_column($pc, 'amount'));
    $m1w = $mkDetail($hOk, '2098-08-06', $acA, '10.00', 1, ['member' => 1]); $m1x = $mkDetail($hOk, '2098-08-07', $acA, '15.00', 1, ['member' => 1]); $m2w = $mkDetail($hOk, '2098-08-08', $acA, '20.00', 4, ['service' => 1]);
    $mc = $mkDetail($hOk, '2098-08-09', $acA, '30.00', 5, ['cong' => $other]);   // parish category: contributor is a congregation; the receipt's congregation is $cong
    $pc2 = $parish->GetContributions($pData); $sumPc2 = array_sum(array_column($pc2, 'amount'));
    $pb = $parish->GetContributionsByContributor($pData);
    ok97('parish by contributor: one total per contributor (member 1: 10.00 + 15.00 = 25.00), two districts with one name stay separate rows', in_array(2500, array_column(array_filter($pb, function ($r) { return strpos($r['key'], ':1:') !== false; }), 'amount'), true) && count(array_filter($pb, function ($r) use ($cong) { return $r['contributor'] === 'ZZ T97 SAME NAME' && strpos($r['key'], $cong . ':') === 0; })) === 4, json_encode($pb));
    ok97('parish by contributor is based on the receipt\'s (header) congregation: the congregation-category line appears under the HEADER congregation, not the contributing one', count(array_filter($pb, function ($r) use ($cong) { return $r['contributor'] === 'ZZ T97 OTHER' && strpos($r['key'], $cong . ':5:') === 0; })) === 1);
    ok97('parish by contributor: the totals add up to the parish contributions report for the same filters', array_sum(array_column($pb, 'amount')) === $sumPc2, m97(array_sum(array_column($pb, 'amount'))) . ' vs ' . m97($sumPc2));
    ok97('no contributor total is repeated per receipt line: fewer rows than the contribution lines', count($pb) < count($pc2));
    $pe = $parish->GetExpenses(['congregations' => [$cong, $other], 'accounts' => [$EXA, $EXB], 'start' => '2098-09-01', 'end' => '2098-09-30']);
    ok97('parish expenses: approved and undeleted, totalled per congregation and account by ID (100.00, 800.00 and the other congregation\'s 1,600.00)', array_sum(array_column($pe, 'amount')) === 90000 + 160000 && count($pe) === 3, json_encode($pe));
    // parity on the dev data
    $cs = ['FY11' => ['2024-10-01', '2025-09-30'], 'FY12' => ['2025-10-01', '2026-09-30'], 'FY25' => ['2026-10-01', '2027-09-30']];
    $accIds = $report->reportAccountIds(1); $accE = $report->reportAccountIds(2);
    $mk = function (array $ids) { return implode(',', array_map('intval', $ids)); };
    foreach ($cs as $label => [$from, $to]) {
        // the old routine is a cross join: every detail of the range once (DISTINCT d.ID) as long as the congregation has one approved receipt
        $o1 = (int) q97('SELECT IFNULL(SUM(CAST(ROUND(d.amount*100) AS SIGNED)),0) s FROM tblcontributions_details d WHERE d.contributionDate BETWEEN ? AND ? AND EXISTS (SELECT 1 FROM tblcontributions_header h WHERE h.congregationId = ? AND h.status = 1)', [$from, $to, $cong])[0]['s'];
        $n1 = array_sum(array_column($report->GetContributions(['type' => 1, 'start' => $from, 'end' => $to, 'account' => []]), 'amount'));
        $expl = q97("SELECT IFNULL(SUM(CAST(ROUND(d.amount*100) AS SIGNED)),0) s FROM tblcontributions_details d LEFT JOIN tblcontributions_header h ON h.ID=d.HeaderId WHERE d.contributionDate BETWEEN ? AND ? AND NOT (h.Deleted=0 AND h.status=1 AND h.congregationId=?)", [$from, $to, $cong])[0]['s'];
        info97("$label contributions all: old (routine, cross join) " . m97($o1) . ' new ' . m97($n1) . ' difference ' . m97($o1 - $n1) . ' = details of deleted / unapproved / foreign headers ' . m97((int) $expl));
        ok97("$label contributions (all): old minus new equals the details of deleted, unapproved or foreign headers", $o1 - $n1 === (int) $expl, m97($o1 - $n1) . ' vs ' . m97((int) $expl));
        $in = $mk($accIds);
        $o2 = (int) q97("SELECT IFNULL(SUM(x.c),0) s FROM (SELECT DISTINCT d.ID, CAST(ROUND(d.amount*100) AS SIGNED) c FROM tblcontributions_header h INNER JOIN tblcontributions_details d WHERE h.Deleted=0 AND h.congregationId=? AND h.status=1 AND d.contributionDate BETWEEN ? AND ? AND d.contributionTypeId IN ($in)) x", [$cong, $from, $to])[0]['s'];
        $n2 = array_sum(array_column($report->GetContributions(['type' => 2, 'start' => $from, 'end' => $to, 'account' => $accIds]), 'amount'));
        $expl2 = q97("SELECT IFNULL(SUM(CAST(ROUND(d.amount*100) AS SIGNED)),0) s FROM tblcontributions_details d LEFT JOIN tblcontributions_header h ON h.ID=d.HeaderId WHERE d.contributionDate BETWEEN ? AND ? AND d.contributionTypeId IN ($in) AND NOT (h.Deleted=0 AND h.status=1 AND h.congregationId=?)", [$from, $to, $cong])[0]['s'];
        ok97("$label contributions by account (old hotfix query with the cross join): old minus new equals the details of deleted, unapproved or foreign headers", $o2 - $n2 === (int) $expl2, m97($o2 - $n2) . ' vs ' . m97((int) $expl2));
        info97("$label contributions by account: old " . m97($o2) . ' new ' . m97($n2) . ' difference ' . m97($o2 - $n2));
        $o3 = q97("SELECT d.districtName n, SUM(CAST(ROUND(c.amount*100) AS SIGNED)) s FROM tblcontributions_details c LEFT JOIN tbldistricts d ON c.contributotDistrict=d.ID JOIN tblcontributions_header h ON c.HeaderId=h.ID WHERE c.category=3 AND h.congregationId=? AND c.contributionDate BETWEEN ? AND ? GROUP BY d.districtName", [$cong, $from, $to]);
        $n3 = $report->GetContributions(['type' => 3, 'start' => $from, 'end' => $to, 'account' => []]);
        $expl3 = q97("SELECT IFNULL(SUM(CAST(ROUND(c.amount*100) AS SIGNED)),0) s FROM tblcontributions_details c JOIN tblcontributions_header h ON c.HeaderId=h.ID WHERE c.category=3 AND h.congregationId=? AND c.contributionDate BETWEEN ? AND ? AND NOT (h.Deleted=0 AND h.status=1)", [$cong, $from, $to])[0]['s'];
        $o3s = array_sum(array_column($o3, 's')); $n3s = array_sum(array_column($n3, 'amount'));
        ok97("$label contributions by district: old minus new equals the deleted / unapproved details; grouping by ID gives at least as many rows as grouping by name", $o3s - $n3s === (int) $expl3 && count($n3) >= count($o3), m97($o3s - $n3s) . ' vs ' . m97((int) $expl3));
        info97("$label by district: old " . m97((int) $o3s) . ' (' . count($o3) . ' names) new ' . m97($n3s) . ' (' . count($n3) . ' districts)');
        $oes = 0; foreach (q97("SELECT e.amount FROM tblexpenses e INNER JOIN tblaccounttypes a ON e.accountId = a.ID INNER JOIN tblpaymentmethods p ON e.paymethodId = p.ID LEFT JOIN tblgroups g ON e.groupId = g.ID WHERE e.congregationId = ? AND e.expenseDate BETWEEN ? AND ? AND e.status = 1 AND e.deleted = 0", [$cong, $from, $to]) as $r) { $oes += c97($r['amount']); }
        $nes = array_sum(array_column($report->GetExpenses(['type' => 1, 'start' => $from, 'end' => $to, 'account' => []]), 'amount'));
        ok97("$label expenses: old routine and new report agree to the cent (" . m97($nes) . ')', $oes === $nes, m97($oes) . ' vs ' . m97($nes));
        $pd = ['congregations' => FinancialReport::parishScope($pdo), 'accounts' => $parish->reportAccountIds(1), 'start' => $from, 'end' => $to];
        $po = (int) q97('SELECT IFNULL(SUM(CAST(ROUND(d.amount*100) AS SIGNED)),0) s FROM tblcontributions_details d JOIN tblcontributions_header h ON h.ID=d.HeaderId WHERE h.Deleted=0 AND d.contributionDate BETWEEN ? AND ? AND d.contributionTypeId IN (' . $mk($pd['accounts']) . ') AND h.congregationId IN (' . $mk($pd['congregations']) . ')', [$from, $to])[0]['s'];
        $pn = array_sum(array_column($parish->GetContributions($pd), 'amount'));
        $pu = (int) q97('SELECT IFNULL(SUM(CAST(ROUND(d.amount*100) AS SIGNED)),0) s FROM tblcontributions_details d JOIN tblcontributions_header h ON h.ID=d.HeaderId WHERE h.Deleted=0 AND h.status<>1 AND d.contributionDate BETWEEN ? AND ? AND d.contributionTypeId IN (' . $mk($pd['accounts']) . ') AND h.congregationId IN (' . $mk($pd['congregations']) . ')', [$from, $to])[0]['s'];
        ok97("$label parish contributions: old (no approval filter) minus new equals the unapproved receipts", $po - $pn === $pu, m97($po - $pn) . ' vs ' . m97($pu));
        $pbOld = (int) q97('SELECT IFNULL(SUM(CAST(ROUND(d.amount*100) AS SIGNED)),0) s FROM tblcontributions_details d JOIN tblcontributions_header h ON h.ID=d.HeaderId WHERE h.Deleted=0 AND d.contributionDate BETWEEN ? AND ? AND d.contributionTypeId IN (' . $mk($pd['accounts']) . ') AND d.contributotCong IN (' . $mk($pd['congregations']) . ')', [$from, $to])[0]['s'];
        $pbNew = array_sum(array_column($parish->GetContributionsByContributor($pd), 'amount'));
        info97("$label parish by contributor: old (scoped by the contributing congregation, one row per line) " . m97($pbOld) . ' new (by the receipt\'s congregation, one total per contributor) ' . m97($pbNew));
        ok97("$label parish by contributor: new total equals the parish contributions report", $pbNew === $pn, m97($pbNew) . ' vs ' . m97($pn));
        $peOld = (int) q97('SELECT IFNULL(SUM(CAST(ROUND(e.amount*100) AS SIGNED)),0) s FROM tblexpenses e WHERE e.deleted=0 AND e.expenseDate BETWEEN ? AND ? AND e.accountId IN (' . $mk($parish->reportAccountIds(2)) . ') AND e.congregationId IN (' . $mk($pd['congregations']) . ')', [$from, $to])[0]['s'];
        $peNew = array_sum(array_column($parish->GetExpenses(['congregations' => $pd['congregations'], 'accounts' => $parish->reportAccountIds(2), 'start' => $from, 'end' => $to]), 'amount'));
        $peUn = (int) q97('SELECT IFNULL(SUM(CAST(ROUND(e.amount*100) AS SIGNED)),0) s FROM tblexpenses e WHERE e.deleted=0 AND e.status<>1 AND e.expenseDate BETWEEN ? AND ? AND e.accountId IN (' . $mk($parish->reportAccountIds(2)) . ') AND e.congregationId IN (' . $mk($pd['congregations']) . ')', [$from, $to])[0]['s'];
        ok97("$label parish expenses: old (no approval filter) minus new equals the unapproved expenses", $peOld - $peNew === $peUn, m97($peOld - $peNew) . ' vs ' . m97($peUn));
        info97("$label parish contributions: old " . m97($po) . ' new ' . m97($pn) . '; parish expenses: old ' . m97($peOld) . ' new ' . m97($peNew));
    }
    $lines = 0; foreach (['app/models/Report.php', 'app/models/Parishreport.php', 'app/models/Bankreconcilliation.php', 'app/models/Year.php'] as $f) { if (preg_match('/FROM\s+tblledger|JOIN\s+tblledger/i', file_get_contents(__DIR__ . '/../' . $f))) { $lines++; } }
    ok97('no report model reads tblledger any more (Report, Parishreport, Bankreconcilliation, Year)', $lines === 0);
    $readers = []; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') { continue; }
        $text = file_get_contents($f->getPathname());
        if (!preg_match_all('/tblledger\b/i', $text, $mt, PREG_OFFSET_CAPTURE)) { continue; }
        foreach ($mt[0] as [$word, $pos]) {
            $before = substr($text, max(0, $pos - 16), min(16, $pos));
            $lineStart = strrpos(substr($text, 0, $pos), "\n"); $from0 = $lineStart === false ? 0 : $lineStart + 1;
            $line = ltrim(substr($text, $from0, $pos - $from0));
            if (preg_match('/(INSERT INTO|UPDATE|DELETE FROM)\s+$/i', $before) || preg_match('~^(//|\*|/\*)~', $line)) { continue; }   // a write of the dev mirror, or a comment
            $readers[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen(realpath(__DIR__ . '/../app')) + 1)) . ' near "' . trim($before) . '"';
        }
    }
    ok97('application code that SELECTs from tblledger: none', !$readers, implode(', ', $readers));

    // =====================================================================================================================
    echo "== E. payment reports: order by the real date, then the ID; the script\n";
    // =====================================================================================================================
    $sup = x97("INSERT INTO tblsuppliers (supplierName, congregationId, deleted) VALUES ('zz t97 supplier', ?, 0)", [$cong]); $created['suppliers'][] = $sup;
    $inv = x97("INSERT INTO tblinvoice_header_suppliers (invoiceDate, supplierId, invoiceNo, fiscalYearId, inclusiveVat, status, postedBy, deleted, congregationId) VALUES ('2097-12-01', ?, 'ZZ97-INV', 12, 1000, 1, 64, 0, ?)", [$sup, $cong]); $created['invoices'][] = $inv;
    // inserted in a scrambled order; text order of dd-mm-yy would put 01-02-98 before 15-01-98 and 02-01-98 before 31-12-97
    foreach ([['2098-02-01', 1], ['2097-12-31', 2], ['2098-01-15', 3], ['2098-01-02', 4], ['2098-01-15', 5]] as [$d, $n]) {
        $created['payments'][] = x97("INSERT INTO tblinvoice_payments_suppliers (paymentNo, invoice_Id, paymentDate, amount, paymentId, paymentReference, Deleted) VALUES (?,?,?,?,1,?,0)", [970000 + $n, $inv, $d, '10.00', 'zz t97 p' . $n]);
    }
    $want = [970002, 970004, 970003, 970005, 970001];   // 2097-12-31, 2098-01-02, 2098-01-15 (ID order), 2098-02-01
    $call = function ($sql, $args) use ($pdo) { $st = $pdo->prepare($sql); $st->execute($args); $r = $st->fetchAll(PDO::FETCH_ASSOC); $st->closeCursor(); return $r; };
    $a = $call('CALL sp_get_payments_all(?,?,?)', ['2097-12-01', '2098-02-28', $cong]);
    ok97('sp_get_payments_all orders across a month and a year boundary by the real date, then the payment ID', array_map('intval', array_column($a, 'paymentNo')) === $want, json_encode(array_column($a, 'paymentNo')));
    $b = $call('CALL sp_get_payments_by_supplier(?,?,?,?)', [$sup, '2097-12-01', '2098-02-28', $cong]);
    ok97('sp_get_payments_by_supplier: same order', array_map('intval', array_column($b, 'paymentNo')) === $want, json_encode(array_column($b, 'paymentNo')));
    $c = $call('CALL sp_getsupplierpayments(?,?,?,?)', [$sup, '2097-12-01', '2098-02-28', $cong]);
    ok97('sp_getsupplierpayments: same order (references identify the rows)', array_column($c, 'PayReference') === ['ZZ T97 P2', 'ZZ T97 P4', 'ZZ T97 P3', 'ZZ T97 P5', 'ZZ T97 P1'], json_encode(array_column($c, 'PayReference')));
    ok97('the display date stays dd-mm-yy / dd/mm/yy', $a[0]['paymentDate'] === '31-12-97' && $b[4]['paymentDate'] === '01-02-98' && $c[1]['PaymentDate'] === '02/01/98');
    // the script: guard and idempotence, on the real routines, restored at the end
    $mysql = 'E:/xampp/mysql/bin/mysql.exe';
    $runScript = function () use ($mysql) { $out = []; exec('"' . $mysql . '" -u ' . DB_USER . ' ' . DB_NAME . ' --table < "' . realpath(__DIR__ . '/sql/payment_reports_order.sql') . '" 2>&1', $out, $code); return [$code, implode("\n", $out)]; };
    $md5 = function ($n) { return q97("SELECT MD5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')) m FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ?", [$n])[0]['m']; };
    $new = ['sp_getsupplierpayments' => '5c6107351f55dfc09142643466505106', 'sp_get_payments_all' => '9f8963d1aa7f1757a0837b88fd965c99', 'sp_get_payments_by_supplier' => '16c29c64c6452c0e6cfd17e9ecbd5ce0'];
    $old = ['sp_getsupplierpayments' => '258f1c74b9d1f2ce2d97520f00a67b97', 'sp_get_payments_all' => 'de86dd62d066f9cf7306ab98b65c9461', 'sp_get_payments_by_supplier' => '110e5782d248a9a9b704032c7ebef9bb'];
    ok97('dev equals the script: the three routines have the script\'s definitions', array_map($md5, array_keys($new)) === array_values($new));
    // the bodies in the script file hash to the same values (the md5 constants in the script are real)
    $scriptText = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/sql/payment_reports_order.sql'));
    $bodies = []; preg_match_all('/CREATE OR REPLACE PROCEDURE (\w+)\(.*?\)\s*(BEGIN.*?END)\$\$/s', $scriptText, $mm, PREG_SET_ORDER); foreach ($mm as $x) { $bodies[$x[1]] = md5($x[2]); }
    ok97('the CREATE statements in the script hash to the md5 values the script names', $bodies === $new, json_encode($bodies));
    $schemaText = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/sql/supplier_accounts_schema.sql'));
    $oldBodies = []; $oldCreate = [];
    foreach (array_keys($old) as $n) { if (preg_match('/CREATE PROCEDURE ' . $n . '(\(.*?\))\s*(BEGIN.*?END)\$\$/s', $schemaText, $x)) { $oldBodies[$n] = md5($x[2]); $oldCreate[$n] = 'CREATE PROCEDURE ' . $n . $x[1] . "\n" . $x[2]; } }
    ok97('the "before" md5 values in the script are the definitions runbook 4.14 creates', $oldBodies === $old, json_encode($oldBodies));
    [$code, $out] = $runScript();
    ok97('re-running on the result is harmless: exit 0 and every routine "ok"', $code === 0 && substr_count($out, 'ok') >= 3 && strpos($out, 'UNEXPECTED') === false, $out);
    foreach ($oldCreate as $n => $ddl) { $pdo->exec("DROP PROCEDURE $n"); $pdo->exec($ddl); }
    ok97('rollback (re-creating the 4.14 definitions) restores the old md5 values', array_map($md5, array_keys($old)) === array_values($old));
    [$code, $out] = $runScript();
    ok97('from the 4.14 definitions the script replaces all three and reports ok', $code === 0 && array_map($md5, array_keys($new)) === array_values($new), $out);
    $pdo->exec('DROP PROCEDURE sp_get_payments_all');
    $pdo->exec("CREATE PROCEDURE sp_get_payments_all(sdate DATE, edate DATE, IN congid INT) BEGIN SELECT 1 AS x; END");
    [$code, $out] = $runScript();
    ok97('a routine that is neither the 4.14 definition nor the script\'s stops the script before anything is changed', $code !== 0 && strpos($out, 'sp_get_payments_all differs') !== false && $md5('sp_getsupplierpayments') === $new['sp_getsupplierpayments'] && $md5('sp_get_payments_all') !== $new['sp_get_payments_all'], $out);
    $pdo->exec('DROP PROCEDURE sp_get_payments_all');
    $pdo->exec($oldCreate['sp_get_payments_all']);
    $pdo->exec('DROP PROCEDURE sp_getsupplierpayments');
    $pdo->exec('DROP PROCEDURE sp_get_payments_by_supplier');
    [$code, $out] = $runScript();
    ok97('a missing routine is a clear error', $code !== 0 && strpos($out, 'does not exist') !== false, $out);
    $pdo->exec($oldCreate['sp_getsupplierpayments']); $pdo->exec($oldCreate['sp_get_payments_by_supplier']);
    [$code, $out] = $runScript();
    ok97('final: dev again equals the script', $code === 0 && array_map($md5, array_keys($new)) === array_values($new), $out);
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    // restore the routines first if a failure left them off the script's state
    try {
        $final = ['sp_getsupplierpayments' => '5c6107351f55dfc09142643466505106', 'sp_get_payments_all' => '9f8963d1aa7f1757a0837b88fd965c99', 'sp_get_payments_by_supplier' => '16c29c64c6452c0e6cfd17e9ecbd5ce0'];
        $bad = false;
        foreach ($final as $n => $m) { $r = q97("SELECT MD5(REPLACE(ROUTINE_DEFINITION, CHAR(13), '')) m FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ?", [$n]); if (!$r || $r[0]['m'] !== $m) { $bad = true; } }
        if ($bad && isset($oldCreate)) { foreach ($oldCreate as $n => $ddl) { $pdo->exec("DROP PROCEDURE IF EXISTS $n"); $pdo->exec($ddl); } exec('"E:/xampp/mysql/bin/mysql.exe" -u ' . DB_USER . ' ' . DB_NAME . ' < "' . realpath(__DIR__ . '/sql/payment_reports_order.sql') . '" 2>&1'); }
    } catch (Throwable $e2) { echo 'WARN could not restore routines: ' . $e2->getMessage() . "\n"; }
    foreach ($created['payments'] as $id) { $pdo->prepare('DELETE FROM tblinvoice_payments_suppliers WHERE ID = ?')->execute([$id]); }
    foreach ($created['invoices'] as $id) { $pdo->prepare('DELETE FROM tblinvoice_header_suppliers WHERE ID = ?')->execute([$id]); }
    foreach ($created['suppliers'] as $id) { $pdo->prepare('DELETE FROM tblsuppliers WHERE ID = ?')->execute([$id]); }
    foreach (array_reverse($created['entries']) as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    foreach ($created['headers'] as $id) { $pdo->prepare('DELETE FROM tblcontributions_details WHERE HeaderId = ?')->execute([$id]); $pdo->prepare('DELETE FROM tblcontributions_header WHERE ID = ?')->execute([$id]); }
    foreach ($created['mmf'] as $id) { $pdo->prepare('DELETE FROM tblmmf WHERE ID = ?')->execute([$id]); }
    foreach ($created['exps'] as $id) { $pdo->prepare('DELETE FROM tblexpenses WHERE ID = ?')->execute([$id]); }
    foreach ($created['reqs'] as $id) { $pdo->prepare('DELETE FROM tblfundrequisition WHERE ID = ?')->execute([$id]); }
    foreach ($created['subs'] as $id) { $pdo->prepare('DELETE FROM tblbanksubaccounts WHERE ID = ?')->execute([$id]); }
    foreach ($created['groups'] as $id) { $pdo->prepare('DELETE FROM tblgroups WHERE ID = ?')->execute([$id]); }
    foreach ($created['districts'] as $id) { $pdo->prepare('DELETE FROM tbldistricts WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    $left = 0;
    foreach (['tblgroups' => 'groupName', 'tbldistricts' => 'districtName', 'tblsuppliers' => 'supplierName', 'tblcongregation' => 'CongregationName'] as $t => $col) { $left += (int) $pdo->query("SELECT COUNT(*) FROM $t WHERE $col LIKE 'zz t97%'")->fetchColumn(); }
    $left += (int) $pdo->query("SELECT COUNT(*) FROM journal_entries WHERE description LIKE 'zz t97%'")->fetchColumn();
    $left += (int) $pdo->query("SELECT COUNT(*) FROM tblmmf WHERE Narration LIKE 'zz t97%'")->fetchColumn();
    $left += (int) $pdo->query("SELECT COUNT(*) FROM tblexpenses WHERE narration LIKE 'zz t97%'")->fetchColumn();
    $left += (int) $pdo->query("SELECT COUNT(*) FROM tblfundrequisition WHERE Purpose LIKE 'zz t97%'")->fetchColumn();
    ok97('no fixture row is left behind', $left === 0, (string) $left);
}
echo "$pass passed / $fail failed\n";
exit($fail ? 1 : 0);

<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
// Task 8.10: chart of accounts (AccountChart library, Account / Bank models, the three SQL routines). Decisions 65-75 of
// docs/ledger_inventory.md. Congregation 11, dev only; every row it creates is removed at the end, real rows it edits are restored.
//   E:\xampp\php\php.exe scripts\test_8_10_accounts.php
// Needs the 8.10 routines on the dev DB (scripts/sql/brand_level_cycle_safe.sql, fn_checkreferencedaccount_v2.sql).
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Account.php';
require_once 'models/Bank.php';

$pdo = (new Database)->pdo();
$USER = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();
function as_($type, $cong, $parish = 0) {
    global $USER;
    $_SESSION['userId'] = $USER; $_SESSION['userType'] = $type; $_SESSION['congId'] = $cong; $_SESSION['isParish'] = $parish;
}
as_(1, 11, 0);   // super admin working in congregation 11

$tables = ['tblaccounttypes' => 'ID', 'journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblbankpostings' => 'ID', 'tbllogs' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$acctSnapshot = $pdo->query('SELECT * FROM tblaccounttypes WHERE ID <= ' . $max['tblaccounttypes'] . ' ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC);
$brandBefore = $pdo->query("SELECT ROUTINE_DEFINITION FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'brand_level'")->fetchColumn();

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function row($id) { $r = q('SELECT * FROM tblaccounttypes WHERE ID=?', [$id]); return $r ? $r[0] : null; }
function flag($id) { $r = row($id); return $r ? (int)$r['isSubCategory'] : null; }
function errs($r) { return implode(' | ', array_map('strval', $r['errors'] ?? [])); }
function has($r, $needle) { return stripos(errs($r), $needle) !== false; }

/** The read-only chart check (scripts/sql/chart_flags_check.sql): number of rows in sections A..G (H is information only). */
function chartViolations() {
    global $pdo;
    $sql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/sql/chart_flags_check.sql'));
    $n = 0; $by = [];
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if (preg_match("/^SELECT '([A-H])\\. /", $stmt, $m)) { $section = $m[1]; continue; }
        $rows = $pdo->query($stmt)->fetchAll(PDO::FETCH_ASSOC);
        if ($section !== 'H' && $rows) { $n += count($rows); $by[$section] = ($by[$section] ?? 0) + count($rows); }
    }
    return [$n, $by];
}
function clean($label) { [$n, $by] = chartViolations(); chk("chart_flags_check empty after: $label", $n === 0, json_encode($by)); }

$acc = new Account;
$bankModel = new Bank;
$mk = function ($name, $parent, $o = []) use ($acc) {
    $r = $acc->create($o + ['accountname' => $name, 'parent' => $parent, 'description' => '', 'forgroup' => 0, 'congregation' => (int)$_SESSION['congId']]);
    if (!$r['success']) { echo "  (fixture '$name' refused: " . errs($r) . ")\n"; }
    return $r;
};
$upd = function ($id, $o = []) use ($acc) {
    $a = row($id);
    return $acc->update($o + ['id' => $id, 'accountname' => $a['accountType'], 'parent' => null, 'description' => (string)$a['description'],
                              'forgroup' => (int)$a['forGroup'], 'active' => (int)$a['active']]);
};

try {
    chk('baseline: dev chart passes chart_flags_check before the test', chartViolations()[0] === 0, json_encode(chartViolations()[1]));

    // ================================================================ library: tree functions (decision 65)
    chk('depth: root 1, 26 = 2, 33 = 3, bank 105 = 4', AccountChart::depthOf($pdo, 1) === 1 && AccountChart::depthOf($pdo, 26) === 2 && AccountChart::depthOf($pdo, 33) === 3 && AccountChart::depthOf($pdo, 105) === 4);
    chk('rootOf: bank 105 -> 3 (asset), 26 -> 2, a root is its own root', AccountChart::rootOf($pdo, 105) === 3 && AccountChart::rootOf($pdo, 26) === 2 && AccountChart::rootOf($pdo, 6) === 6);
    chk('computeFlag: roots and depth 2 = 0, a leaf at depth 3+ = 1, 33 (has banks) = 0', AccountChart::computeFlag($pdo, 1) === 0 && AccountChart::computeFlag($pdo, 26) === 0
        && AccountChart::computeFlag($pdo, 105) === 1 && AccountChart::computeFlag($pdo, 33) === 0 && AccountChart::computeFlag($pdo, 34) === 1);
    chk('descendants(22) holds 33, 105 and every bank under it', ($d = AccountChart::descendants($pdo, 22)) && in_array(33, $d, true) && in_array(105, $d, true) && !in_array(22, $d, true));
    chk('wouldCycle: itself, a descendant; not a sibling', AccountChart::wouldCycle($pdo, 22, 22) && AccountChart::wouldCycle($pdo, 22, 105) && !AccountChart::wouldCycle($pdo, 105, 34));
    chk('isProtected: roots, 9, 20, 22, 23, 25, 33, 34, 98, 118, 121, 167, 194 and a bank; not 26 or 93', array_reduce([1, 6, 9, 20, 22, 23, 25, 33, 34, 98, 118, 121, 167, 194], function ($c, $i) { return $c && AccountChart::isProtected($i); }, true)
        && AccountChart::isProtected(row(105)) && AccountChart::isProtected(row(34)) && !AccountChart::isProtected(row(26)) && !AccountChart::isProtected(93));

    // ================================================================ decisions 65, 66, 68: create under a depth-2 account, parent flips, root from parent
    $r = $mk('zz t810 a', 26);
    $A = (int)($r['id'] ?? 0);
    chk('create under a depth-2 account: ok, posting (1), accountTypeId = root 2, parent 26 stays reporting (0)', $r['success'] && flag($A) === 1 && (int)row($A)['accountTypeId'] === 2 && flag(26) === 0, errs($r));
    chk('create: name stored trimmed and lowercased', row($A)['accountType'] === 'zz t810 a');
    clean('create A');
    $r = $mk('zz t810 a1', $A);
    $A1 = (int)($r['id'] ?? 0);
    chk('create under a leaf with no postings: ok, the parent A becomes reporting (0) automatically, child posting (1)', $r['success'] && flag($A) === 0 && flag($A1) === 1 && (int)row($A1)['accountTypeId'] === 2, errs($r));
    $r = $mk('zz t810 a1x', $A1);
    $A1x = (int)($r['id'] ?? 0);
    chk('any depth: a grandchild at depth 5 is created', $r['success'] && AccountChart::depthOf($pdo, $A1x) === 5 && flag($A1x) === 1 && flag($A1) === 0, errs($r));
    clean('create at depth 5');
    $r = $acc->delete($A1x);
    chk('soft delete of the leaf: success, row kept with deleted = 1, old parent recomputed back to posting', $r['success'] && (int)row($A1x)['deleted'] === 1 && flag($A1) === 1, errs($r));
    clean('delete leaf');
    $r = $acc->delete($A);
    chk('delete refused while the account has a sub account (reason lists it)', !$r['success'] && has($r, 'sub account') && has($r, 'zz t810 a1') && (int)row($A)['deleted'] === 0, errs($r));
    $r = $acc->create(['accountname' => 'zz t810 root', 'parent' => 0, 'description' => '', 'forgroup' => 0, 'congregation' => 11]);
    chk('a root cannot be created (parent 0 / none refused)', !$r['success'] && isset($r['errors']['parent']));

    // ================================================================ decision 66: accountTypeId never posted; picker excludes
    $parents = array_column(AccountChart::allowedParents($pdo, 11), null, 'ID');
    chk('picker lists roots and any depth: 26 (depth 2), a depth-3 account, the root 2', isset($parents[26]) && isset($parents[$A1]) && isset($parents[2]));
    chk('picker excludes banks, 33, accounts with postings (20), deleted accounts', !isset($parents[105]) && !isset($parents[33]) && !isset($parents[20]) && !isset($parents[99]) && !isset($parents[$A1x]));
    chk('picker path is "asset > ..." names; root filter works', isset($parents[26]) && $parents[26]['path'] === 'Expense > administrative costs'
        && count(array_filter(AccountChart::allowedParents($pdo, 11, null, 2), function ($p) { return $p['rootId'] !== 2; })) === 0);
    $self = array_column(AccountChart::allowedParents($pdo, 11, $A), 'ID');
    chk('picker for an account being edited excludes itself and its descendants', !in_array($A, $self, true) && !in_array($A1, $self, true) && in_array(26, $self, true));
    chk('picker for a shared account (0) lists shared accounts only', count(array_filter(AccountChart::allowedParents($pdo, 0), function ($p) use ($A) { return $p['ID'] === $A; })) === 0);

    // ================================================================ decision 67: parent problems, server side
    $reject = function ($label, $parent, $needle, $cong = 11) use ($acc) {
        $r = $acc->create(['accountname' => 'zz t810 rej', 'parent' => $parent, 'description' => '', 'forgroup' => 0, 'congregation' => $cong]);
        chk("create refused: $label", !$r['success'] && stripos(implode(' ', $r['errors']), $needle) !== false && !q("SELECT ID FROM tblaccounttypes WHERE accountType='zz t810 rej'"), implode('|', $r['errors']));
    };
    $reject('parent is a bank (105)', 105, 'bank');
    $reject('parent is 33 cash at bank', 33, 'banks');
    $reject('parent does not exist', 999999, 'does not exist');
    $reject('parent is a deleted account', $A1x, 'deleted');
    $reject('parent has journal lines (20 opening balance equity)', 20, 'postings');
    $reject('parent has bank postings / other references (34 cash at hand or 105 is a bank; 98 petty cash)', 98, 'postings');
    $as = $mk('zz t810 cong2', 26, ['congregation' => 2]);   // type 1 may create for ... (refused: congregation is the session one)
    chk('a user cannot create for another congregation (congregation 2 refused when the session is 11)', !$as['success'] && isset($as['errors']['congregation']), errs($as));
    as_(1, 2, 0); $C2 = (int)$mk('zz t810 cong2 parent', 26)['id']; as_(1, 11, 0);
    chk('fixture: an account of congregation 2 exists', $C2 > 0 && (int)row($C2)['congregationId'] === 2);
    $reject("parent is another congregation's account", $C2, 'another congregation');
    $reject('a shared account under a congregation account', $A, 'shared', 0);
    $reject('non-numeric / empty parent through the model is the form error, not a crash', 0, 'Select');

    // ================================================================ 8.10c: decision 68 corrected, reporting parents, protected parents, banks under 33
    chk('8.10c: "tithes and offering" (32, reporting, 11 legacy receipt lines) passes parentProblem for congregation 11', AccountChart::computeFlag($pdo, 32) === 0 && AccountChart::postingReasons($pdo, 32) !== [] && AccountChart::parentProblem($pdo, 32, 11) === null, (string)AccountChart::parentProblem($pdo, 32, 11));
    $offered = array_column(AccountChart::allowedParents($pdo, 11), 'ID');
    chk('8.10c: the picker offers 32 (and the other reporting accounts with legacy references: 31, 127, 9, 23)', in_array(32, $offered, true) && in_array(31, $offered, true) && in_array(127, $offered, true) && in_array(9, $offered, true) && in_array(23, $offered, true));
    chk('8.10c: the picker offers the protected parents 22, 25, 118 (and roots), not 33 and not a bank', in_array(22, $offered, true) && in_array(25, $offered, true) && in_array(118, $offered, true) && in_array(1, $offered, true) && !in_array(33, $offered, true) && !in_array(105, $offered, true) && !in_array(195, $offered, true));
    chk('8.10c: a posting account WITH postings is still not offered (94 tithes, 98 petty cash, 20) and parentProblem refuses it', !array_intersect([94, 98, 20, 34], $offered) && stripos((string)AccountChart::parentProblem($pdo, 94, 11), 'posting account with postings') !== false);
    $r = $mk('zz t810 under tithes offering', 32);
    $T1 = (int)($r['id'] ?? 0);
    chk('8.10c: create under the reporting parent 32 is accepted; 32 stays reporting; the child is posting under root 1', $r['success'] && flag(32) === 0 && flag($T1) === 1 && (int)row($T1)['accountTypeId'] === 1, errs($r));
    $r = $mk('zz t810 under cash equivalents', 22);
    $T2 = (int)($r['id'] ?? 0);
    chk('8.10c: create under the protected reporting parent 22 is accepted (22 itself unchanged)', $r['success'] && flag(22) === 0 && (int)row(22)['parentId'] === 3, errs($r));
    $r = $mk('zz t810 under groupfunds', 25);
    $T3 = (int)($r['id'] ?? 0);
    chk('8.10c: create under the protected reporting parent 25 is accepted', $r['success'] && flag(25) === 0, errs($r));
    $r = $mk('zz t810 under bank 33 refused again', 33);
    chk('8.10c: 33 and a bank are still refused as parents', !$r['success'] && !$mk('zz t810 under bank 105 again', 105)['success']);
    $r = $upd($T2, ['parent' => 32]);
    chk('8.10c: MOVE under a reporting parent with legacy references (32) is accepted (and across roots, no postings)', $r['success'] && (int)row($T2)['parentId'] === 32 && (int)row($T2)['accountTypeId'] === 1, errs($r));
    $r = $upd($T2, ['parent' => 25]);
    chk('8.10c: MOVE under the protected reporting parent 25 is accepted', $r['success'] && (int)row($T2)['accountTypeId'] === 4, errs($r));
    $r = $upd($T2, ['parent' => 94]);
    chk('8.10c: MOVE under a posting account with postings (94) is refused', !$r['success'] && has($r, 'posting account with postings') && (int)row($T2)['parentId'] === 25, errs($r));
    $r = $upd($T2, ['parent' => 33]);
    chk('8.10c: MOVE under 33 is refused', !$r['success']);
    // a posting account WITHOUT postings is a valid parent and flips to reporting (unchanged behaviour)
    $r = $mk('zz t810 under fresh leaf', $T1);
    chk('8.10c: a posting parent without postings is accepted and flips to reporting', $r['success'] && flag($T1) === 0, errs($r));
    clean('8.10c creates and moves');
    // F12: every live bank under 33, idempotent, deleted banks untouched
    $fixes = file_get_contents(__DIR__ . '/../ledger-migration/ledger_fixes.sql');
    $f12 = '';
    if (preg_match('/-- F12 Chart.*?\n(UPDATE\s+tblaccounttypes\s+SET\s+parentId = 33.*?;)/s', $fixes, $mm)) { $f12 = $mm[1]; }
    chk('8.10c F12: the bank move selects live banks (isBank = 1, deleted = 0), not an ID list', $f12 !== '' && stripos($f12, 'isBank = 1') !== false && stripos($f12, 'deleted = 0') !== false && stripos($f12, 'IN (105') === false, $f12);
    q('UPDATE tblaccounttypes SET parentId = 22, isSubCategory = 0 WHERE ID IN (101, 169)');
    [$n, $by] = chartViolations();
    chk('8.10c: banks left under 22 (101 and 169 as on production) are reported by chart_flags_check section G', ($by['G'] ?? 0) === 2, json_encode($by));
    $st = $pdo->exec($f12);
    chk('8.10c F12: moves exactly 101 and 169 (105 and 195 already under 33), sets isSubCategory = 1', $st === 2 && (int)row(101)['parentId'] === 33 && (int)row(169)['parentId'] === 33 && flag(101) === 1 && flag(169) === 1, "rows $st");
    chk('8.10c F12: idempotent (second run changes 0 rows); deleted bank 99 untouched (still under 22)', $pdo->exec($f12) === 0 && (int)row(99)['parentId'] === 22 && (int)row(99)['deleted'] === 1);
    Ledger::clearCashCache();
    chk('8.10c: banks are never PAY_CASH accounts (isBank is excluded), under 22 or 33', !in_array(101, array_column(Ledger::listPaymentAccounts($pdo, 2, Ledger::PAY_CASH), 'ID'), true) && Ledger::postingProblem($pdo, 169, 8) === null);
    clean('F12 widened');
    // ================================================================ decision 68: reasons for postings (every reference)
    $pr = AccountChart::postingReasons($pdo, 20);
    chk('postingReasons(20 opening balance equity): journal lines', $pr && stripos(implode(' ', $pr), 'journal line') !== false, implode('|', $pr));
    chk('postingReasons(105 bank): bank postings, receipts, transactions listed with counts', ($t = implode(' ', AccountChart::postingReasons($pdo, 105))) && stripos($t, 'bank posting') !== false && stripos($t, 'bank transaction') !== false && preg_match('/\d+ /', $t), $t);
    chk('postingReasons: 98 petty cash rows, 127 expenses, 31 receipt lines, 9 products, 33 church budget', stripos(implode(' ', AccountChart::postingReasons($pdo, 98)), 'petty cash') !== false
        && stripos(implode(' ', AccountChart::postingReasons($pdo, 127)), 'expense') !== false && stripos(implode(' ', AccountChart::postingReasons($pdo, 31)), 'receipt') !== false
        && stripos(implode(' ', AccountChart::postingReasons($pdo, 9)), 'product') !== false && stripos(implode(' ', AccountChart::postingReasons($pdo, 33)), 'church budget') !== false);
    chk('postingReasons: none for a fresh account; subtree flag includes the descendants', AccountChart::postingReasons($pdo, $A) === [] && count(AccountChart::postingReasons($pdo, 22, true)) > 0);
    // every REFERENCES entry runs and every table / column exists
    $okCols = true; $badCol = '';
    foreach (AccountChart::REFERENCES as [$t, $c]) {
        if ((int)q('SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c])[0]['n'] !== 1) { $okCols = false; $badCol = "$t.$c"; }
    }
    chk('every REFERENCES table.column exists in the live schema', $okCols, $badCol);
    chk('referenceCounts for a missing ID is empty and runs every query', AccountChart::referenceCounts($pdo, [999999]) === []);
    // fn_checkreferencedaccount v2 == children + library counts, for EVERY account (the routine and the library list must agree)
    $bad = [];
    foreach (q('SELECT ID FROM tblaccounttypes WHERE ID <= ' . $max['tblaccounttypes']) as $r) {
        $id = (int)$r['ID'];
        $kids = (int)q('SELECT COUNT(*) n FROM tblaccounttypes WHERE parentId=? AND deleted=0', [$id])[0]['n'];
        $lib = $kids + array_sum(AccountChart::referenceCounts($pdo, [$id])[$id] ?? []);
        $fn = (int)q('SELECT fn_checkreferencedaccount(?) n', [$id])[0]['n'];
        if ($fn !== $lib) { $bad[] = "$id fn=$fn lib=$lib"; }
    }
    chk('fn_checkreferencedaccount v2 = children + AccountChart::referenceCounts for all ' . count($acctSnapshot) . ' accounts', !$bad, implode(', ', array_slice($bad, 0, 5)));
    $def = $pdo->query("SELECT ROUTINE_DEFINITION FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'fn_checkreferencedaccount'")->fetchColumn();
    chk('fn_checkreferencedaccount v2 has no tblledger reference (survives the rename to tblledger_legacy)', stripos($def, 'tblledger') === false && stripos($def, 'journal_lines') !== false);

    // ================================================================ postings made by the Ledger block create/move/delete; voided history does not
    $P1 = (int)$mk('zz t810 p1', 26)['id'];      // expense posting accounts under 26 (root 2)
    $P2 = (int)$mk('zz t810 p2', 26)['id'];
    $post = function ($txn) use ($pdo, $P1, $P2) {
        $pdo->beginTransaction();
        Ledger::post($pdo, ['congregationId' => 11, 'entryDate' => '2026-01-15', 'transactionTypeId' => 8, 'transactionId' => $txn, 'reference' => 'T810', 'description' => 'T810'],
                     [Ledger::debit($P1, '10.00', 'T810'), Ledger::credit($P2, '10.00', 'T810')]);
        $pdo->commit();
    };
    $void = function ($txn) use ($pdo) { $pdo->beginTransaction(); Ledger::void($pdo, 11, 8, $txn); $pdo->commit(); };
    $post(900810);
    $r = $mk('zz t810 under p1', $P1);
    chk('decision 68: no child under an account with a journal line; reasons listed; P1 stays posting', !$r['success'] && has($r, 'postings') && has($r, 'journal line') && flag($P1) === 1, errs($r));
    $r = $acc->delete($P1);
    chk('decision 70: delete refused while a non-voided journal line exists', !$r['success'] && has($r, 'journal line') && (int)row($P1)['deleted'] === 0, errs($r));
    chk('picker no longer offers P1 (it has postings)', !in_array($P1, array_column(AccountChart::allowedParents($pdo, 11), 'ID'), true));
    $void(900810);
    chk('after the entry is voided the account has no reasons (voided history does not block)', AccountChart::postingReasons($pdo, $P1) === [] && AccountChart::deleteReasons($pdo, $P1) === []);
    $r = $mk('zz t810 under p1 again', $P1);
    chk('after voiding, a child may be added under P1 (it flips to reporting)', $r['success'] && flag($P1) === 0, errs($r));
    $acc->delete((int)$r['id']);
    $post(900811);   // a live entry again for the move tests

    // ================================================================ decision 69: moves
    $inc = (int)$mk('zz t810 inc parent', 1)['id'];          // depth-2 reporting account under Income (root 1)
    $r = $upd($P1, ['parent' => $inc]);
    chk('move of an account WITH postings to another root (Expense -> Income) refused, nothing changed', !$r['success'] && has($r, 'same account type') && (int)row($P1)['parentId'] === 26 && (int)row($P1)['accountTypeId'] === 2, errs($r));
    $E2 = (int)$mk('zz t810 exp parent 2', 2)['id'];          // depth-2 account under Expense
    $r = $upd($P1, ['parent' => $E2]);
    chk('move of an account with postings inside the same root: allowed, flags recomputed, root unchanged', $r['success'] && (int)row($P1)['parentId'] === $E2 && (int)row($P1)['accountTypeId'] === 2 && flag($P1) === 1, errs($r));
    $r = $upd($P1, ['parent' => 2]);
    chk('move of an account with postings directly under a root refused (it would become a reporting account holding postings)', !$r['success'] && has($r, 'top-level reporting'), errs($r));
    // parent with postings in the subtree, moving the PARENT across roots is refused as well
    $G = (int)$mk('zz t810 grp', 26)['id'];
    $Gc = (int)$mk('zz t810 grp child', $G)['id'];
    $Gcc = (int)$mk('zz t810 grp grandchild', $Gc)['id'];
    $r = $upd($G, ['parent' => $inc]);
    chk('no postings in the subtree: move to another root allowed', $r['success'] && (int)row($G)['accountTypeId'] === 1, errs($r));
    chk('  ... the whole subtree accountTypeId follows (child and grandchild = 1), flags recomputed', (int)row($Gc)['accountTypeId'] === 1 && (int)row($Gcc)['accountTypeId'] === 1 && flag($Gcc) === 1 && flag($Gc) === 0 && flag($G) === 0 && flag(26) === 0, json_encode([row($Gc)['accountTypeId'], flag($Gcc), flag($Gc)]));
    clean('cross-root move of a clean subtree');
    // put a posting on the grandchild: now the subtree cannot change root
    $pdo->beginTransaction();
    Ledger::post($pdo, ['congregationId' => 11, 'entryDate' => '2026-01-15', 'transactionTypeId' => 8, 'transactionId' => 900812, 'reference' => 'T810', 'description' => 'T810'],
                 [Ledger::debit($P1, '5.00', 'T810'), Ledger::credit($Gcc, '5.00', 'T810')]);
    $pdo->commit();
    $r = $upd($G, ['parent' => 26]);
    chk('a posting on a DESCENDANT makes the cross-root move refused (grandchild named in the reasons)', !$r['success'] && has($r, 'zz t810 grp grandchild') && (int)row($G)['accountTypeId'] === 1, errs($r));
    $void(900812);
    $r = $upd($G, ['parent' => $Gc]);
    chk('cycle: moving an account under its own child refused', !$r['success'] && has($r, 'itself or its own sub accounts'), errs($r));
    $r = $upd($G, ['parent' => $G]);
    chk('cycle: moving an account under itself refused (the old form could do this and hang brand_level)', !$r['success'] && has($r, 'itself or its own sub accounts'), errs($r));
    $r = $upd($Gc, ['parent' => $Gcc]);
    chk('cycle: moving an account under its grandchild refused', !$r['success'], errs($r));
    $r = $upd($G, ['parent' => 105]);
    chk('move under a bank refused', !$r['success'] && has($r, 'bank'), errs($r));
    $r = $upd($G, ['parent' => 33]);
    chk('move under 33 refused', !$r['success'] && has($r, 'banks'), errs($r));
    $r = $upd($G, ['parent' => 20]);
    chk('move under an account with postings refused', !$r['success'] && has($r, 'postings'), errs($r));
    $r = $upd($G, ['parent' => $C2]);
    chk("move under another congregation's account refused", !$r['success'] && has($r, 'another congregation'), errs($r));
    $r = $upd(34, ['parent' => 26]);
    chk('protected account (34 cash at hand) cannot be moved', !$r['success'] && has($r, 'protected') && (int)row(34)['parentId'] === 22, errs($r));
    $r = $upd(105, ['parent' => 26]);
    chk('a bank cannot be moved (or edited) on the account screens', !$r['success'] && has($r, 'Banks'), errs($r));
    $r = $upd($G, ['parent' => 'x']);
    chk('a non-numeric parent is refused without a crash', !$r['success']);
    clean('refused moves changed nothing');
    $void(900811);

    // ================================================================ decision 70: delete
    $r = $acc->delete(34);
    chk('protected account (34) cannot be deleted', !$r['success'] && has($r, 'protected') && (int)row(34)['deleted'] === 0, errs($r));
    $r = $acc->delete(105);
    chk('a bank cannot be deleted on the account screens', !$r['success'] && has($r, 'bank'), errs($r));
    $r = $acc->delete(26);
    chk('delete lists EVERY reason: 26 has sub accounts (and a combination with postings lists both)', !$r['success'] && has($r, 'sub account'), errs($r));
    $r = $acc->delete(9);
    chk('account 9 (protected, products, sub accounts): all reasons listed together', !$r['success'] && count($r['errors']) >= 2, errs($r));
    foreach (['', 'abc', '0', '-5', '999999', '1 OR 1=1'] as $bad) {
        $r = $acc->delete($bad);
        chk("delete of a missing / non-numeric ID '$bad' returns an error, no exception", !$r['success'] && $r['errors'], errs($r));
    }
    $r = $acc->delete($Gcc);
    chk('delete a clean leaf: ok, kept with deleted = 1, parent recomputed to posting', $r['success'] && flag($Gc) === 1 && (int)row($Gcc)['deleted'] === 1, errs($r));
    $r = $acc->delete($Gcc);
    chk('deleting twice is refused (already deleted)', !$r['success'] && has($r, 'deleted'), errs($r));
    clean('deletes');

    // ================================================================ decision 71: protected: rename / move / delete refused; description + forGroup allowed (restored)
    $before34 = row(34);
    $r = $upd(34, ['accountname' => 'cash on hand']);
    chk('protected 34 cannot be renamed', !$r['success'] && has($r, 'protected') && row(34)['accountType'] === $before34['accountType'], errs($r));
    $r = $upd(34, ['active' => 0]);
    chk('protected 34 cannot be deactivated', !$r['success'] && (int)row(34)['active'] === 1, errs($r));
    $r = $upd(34, ['description' => 'zz t810 d', 'forgroup' => 1]);
    chk('protected 34: description and forGroup edits are allowed', $r['success'] && row(34)['description'] === 'zz t810 d' && (int)row(34)['forGroup'] === 1, errs($r));
    $r = $upd(34, ['description' => (string)$before34['description'], 'forgroup' => (int)$before34['forGroup']]);
    chk('  ... and restored', $r['success'] && row(34)['description'] === $before34['description'] && (int)row(34)['forGroup'] === (int)$before34['forGroup'], errs($r));
    $r = $upd(1, ['description' => 'zz t810 root']);
    chk('a root: description edit keeps accountTypeId NULL and parentId 0', $r['success'] && row(1)['accountTypeId'] === null && (int)row(1)['parentId'] === 0, errs($r));
    $upd(1, ['description' => (string)$acctSnapshot[0]['description']]);
    $r = $upd(9, ['description' => 'zz t810']);
    chk('isEditable = 0 (account 9): no edits at all, server side', !$r['success'] && has($r, 'cannot be edited') && row(9)['description'] === q('SELECT description FROM tblaccounttypes WHERE ID=9')[0]['description'], errs($r));
    $names = array_column($pdo->query('SELECT ID, isEditable FROM tblaccounttypes WHERE isEditable = 0')->fetchAll(PDO::FETCH_ASSOC), 'ID');
    chk('dev has exactly one isEditable = 0 account (9)', array_map('intval', $names) === [9]);

    // ================================================================ decision 72: shared / congregation ownership
    $S = (int)$mk('zz t810 shared', 26, ['congregation' => 0])['id'];
    chk('type 1 (admin) creates a shared account (congregation 0)', $S > 0 && (int)row($S)['congregationId'] === 0);
    as_(3, 11, 0);
    $r = $upd($S, ['description' => 'zz']);
    chk('congregation login (type 3, own congregation) cannot edit a shared account', !$r['success'] && has($r, 'Shared accounts'), errs($r));
    $r = $acc->delete($S);
    chk('congregation login cannot delete a shared account', !$r['success'] && has($r, 'Shared accounts') && (int)row($S)['deleted'] === 0, errs($r));
    $r = $acc->create(['accountname' => 'zz t810 sh2', 'parent' => 26, 'description' => '', 'forgroup' => 0, 'congregation' => 0]);
    chk('congregation login cannot create a shared account', !$r['success'] && isset($r['errors']['congregation']), errs($r));
    chk('createCongregation: a congregation login posting "0" gets its own congregation; a parish login / admin gets 0', Account::createCongregation('0', Account::session()) === 11
        && Account::createCongregation('0', ['userType' => 3, 'isParish' => true, 'congId' => 1]) === 0 && Account::createCongregation('0', ['userType' => 2, 'isParish' => false, 'congId' => 8]) === 0
        && Account::createCongregation('11', ['userType' => 1, 'isParish' => true, 'congId' => 1]) === 1);
    $own = (int)$mk('zz t810 own', 26)['id'];
    $r = $upd($own, ['description' => 'edited by owner']);
    chk('congregation login edits its own account', $r['success'] && row($own)['description'] === 'edited by owner', errs($r));
    chk('congregation is fixed: update() has no way to change it (still 11)', (int)row($own)['congregationId'] === 11);
    $r = $upd($C2, ['description' => 'zz']);
    chk("congregation login cannot edit another congregation's account", !$r['success'] && has($r, 'another congregation'), errs($r));
    $r = $acc->delete($C2);
    chk("congregation login cannot delete another congregation's account", !$r['success'] && has($r, 'another congregation') && (int)row($C2)['deleted'] === 0, errs($r));
    as_(3, 1, 1);   // a parish login
    $r = $upd($S, ['description' => 'parish edit']);
    chk('parish login (type 3) may edit a shared account', $r['success'], errs($r));
    $r = $upd($own, ['description' => 'zz']);
    chk("parish login cannot edit a congregation's own account (not its own congregation)", !$r['success'] && has($r, 'another congregation'), errs($r));
    $r = $acc->delete($S);
    chk('parish login may delete a shared account (no references)', $r['success'], errs($r));
    as_(2, 8, 0);   // an administrator (type 2) of a congregation
    $S2 = (int)$mk('zz t810 shared by admin2', 26, ['congregation' => 0])['id'];
    chk('type 2 administrator may create and later delete a shared account', $S2 > 0 && $acc->delete($S2)['success']);
    as_(1, 11, 0);
    chk('the index shows no Edit / Delete for accounts the session may not manage', (function () use ($acc, $own, $C2) {
        as_(3, 11, 0);
        $rows = array_column(array_map(function ($r) { return (array)$r; }, $acc->index()), null, 'ID');
        $okOwn = $rows[$own]['canEdit'] && $rows[$own]['canDelete'];
        $okShared = !$rows[26]['canEdit'] && !$rows[26]['canDelete'];
        $okBank = !$rows[105]['canEdit'] && !$rows[105]['canDelete'] && $rows[105]['note'] === 'Managed in Banks';
        $okProt = !$rows[34]['canEdit'] && !$rows[34]['canDelete'] && $rows[34]['note'] !== '';
        $okNo9 = !$rows[9]['canEdit'] && !$rows[9]['canDelete'];
        $okOther = !isset($rows[101]) && !isset($GLOBALS['zzx']);
        as_(1, 11, 0);
        return $okOwn && $okShared && $okBank && $okProt && $okNo9 && $okOther;
    })());

    // ================================================================ decision 73: active
    $B1 = (int)$mk('zz t810 act parent', 26)['id'];
    $B2 = (int)$mk('zz t810 act child', $B1)['id'];
    $r = $upd($B1, ['active' => 0]);
    chk('deactivating a parent with active children: saved, warning names the children', $r['success'] && (int)row($B1)['active'] === 0 && count($r['warnings']) === 1 && stripos($r['warnings'][0], 'zz t810 act child') !== false, errs($r) . json_encode($r['warnings'] ?? ''));
    $r = $upd($B1, ['active' => 0]);
    chk('saving it again while inactive gives no new warning', $r['success'] && !$r['warnings']);
    $post(900813);
    $pdo->prepare('UPDATE tblaccounttypes SET active = 1 WHERE ID = ?')->execute([$P1]);
    $r = $upd($P1, ['active' => 0]);
    chk('deactivating an account WITH a balance is allowed', $r['success'] && (int)row($P1)['active'] === 0, errs($r));
    $r = $upd($P1, ['active' => 1]);
    chk('and it can be reactivated', $r['success'] && (int)row($P1)['active'] === 1, errs($r));
    $void(900813);
    // reports must not filter on active: the report account lists use deleted only
    $src = file_get_contents(__DIR__ . '/../app/models/Report.php') . file_get_contents(__DIR__ . '/../app/models/Parishreport.php');
    preg_match_all('/FROM\s+tblaccounttypes[^;]{0,300}?WHERE[^;]{0,300}?/is', $src, $m);
    $badReport = array_filter($m[0], function ($s) { return preg_match('/active\s*=\s*1/i', $s); });
    chk('reports do not filter tblaccounttypes on active', !$badReport, implode(' || ', array_map(function ($s) { return substr(preg_replace('/\s+/', ' ', $s), 0, 80); }, $badReport)));

    // ================================================================ decision 74: forGroup explicit, description NULL
    $F = (int)$mk('zz t810 fg1', 26, ['forgroup' => 1, 'description' => '0'])['id'];
    chk('forGroup ticked on a child of a forGroup = 0 parent is stored as ticked (no server override)', (int)row($F)['forGroup'] === 1 && (int)row(26)['forGroup'] === 0);
    chk("description '0' and blank are stored as NULL, never the literal 0", row($F)['description'] === null);
    $F2 = (int)$mk('zz t810 fg2', $F, ['forgroup' => 0, 'description' => 'Some Text'])['id'];
    chk('forGroup unticked under a forGroup = 1 parent (F) is stored as 0; description lowercased', (int)row($F)['forGroup'] === 1 && (int)row($F2)['forGroup'] === 0 && row($F2)['description'] === 'some text');
    $r = $upd($F2, ['forgroup' => 1, 'description' => '']);
    chk('edit: forGroup follows the tick, blank description -> NULL', $r['success'] && (int)row($F2)['forGroup'] === 1 && row($F2)['description'] === null, errs($r));

    // ================================================================ decision 75: names
    $r = $mk('  ZZ T810 A  ', 26);
    chk('duplicate name (case / spaces) in the same scope refused', !$r['success'] && has($r, 'already exists'), errs($r));
    chk('nameProblem: empty and > 100 characters', AccountChart::nameProblem($pdo, '   ', 11) !== null && AccountChart::nameProblem($pdo, str_repeat('x', 101), 11) !== null);
    $r = $mk('zz t810 a', $inc);
    chk('the same name under another parent is still a duplicate (unique across every level)', !$r['success'], errs($r));
    $r = $mk('administrative costs', $E2);
    chk('a name used by a shared account cannot be re-used by a congregation account (including depth 3)', !$r['success'] && has($r, 'already exists'), errs($r));
    $r = $mk('zz t810 cong2 parent', 26);
    chk("another congregation's account name is not in the visible scope: re-use allowed for congregation 11", $r['success'], errs($r));
    if ($r['success']) { $acc->delete((int)$r['id']); }
    $r = $upd($A, ['accountname' => 'zz t810 a']);
    chk('an account is never blocked by its own name (save without rename)', $r['success'], errs($r));
    $r = $upd($A, ['accountname' => 'zz t810 own']);
    chk('rename to an existing name refused', !$r['success'] && has($r, 'already exists'), errs($r));
    $r = $upd($A, ['accountname' => 'ZZ T810 A RENAMED ']);
    chk('rename: trimmed + lowercased', $r['success'] && row($A)['accountType'] === 'zz t810 a renamed', errs($r));
    $dead = (int)$mk('zz t810 dead', 26)['id']; $acc->delete($dead);
    $r = $mk('zz t810 dead', 26);
    chk("a deleted account's name cannot be re-used (old reports still match by name)", !$r['success'] && has($r, 'deleted') && has($r, 'cannot be re-used'), errs($r));
    $r = $upd($A, ['accountname' => 'zz t810 dead']);
    chk('rename to a deleted account\'s name refused', !$r['success'] && has($r, 'cannot be re-used'), errs($r));
    as_(1, 2, 0);
    $r = $mk('zz t810 own', 26);
    chk('a SHARED-scope check: congregation 2 cannot use the name of a congregation-11 account only if it is shared; own name is fine for 2', $r['success'] || has($r, 'already exists'), errs($r));
    if ($r['success']) { $acc->delete((int)$r['id']); }
    as_(1, 11, 0);
    $dup = AccountChart::nameProblem($pdo, 'equity', 11, 101);
    chk('existing duplicates are left: nameProblem for the bank "equity" (101) of cong 2 still sees root 6 for another account', AccountChart::nameProblem($pdo, 'equity', 11, 999999) !== null && $dup !== null);
    // a shared account's name must be free in every congregation
    $r = $mk('zz t810 shared name', 26, ['congregation' => 0]);
    as_(1, 2, 0);
    $r2 = $mk('zz t810 shared name', 26);
    chk('a shared account name is unique across congregations (cong 2 cannot re-use it)', $r['success'] && !$r2['success'] && has($r2, 'already exists'), errs($r2));
    as_(1, 11, 0);
    if ($r['success']) { q('UPDATE tblaccounttypes SET deleted = 1 WHERE ID = ?', [(int)$r['id']]); }
    $mkShared = $mk('zz t810 shared name 2', 26, ['congregation' => 0]);
    as_(1, 2, 0); $x = $mk('zz t810 shared name 2', 26); as_(1, 11, 0);
    $sharedAfter = AccountChart::nameProblem($pdo, 'zz t810 own', 0);
    chk('creating a shared account checks the name against every congregation (own account of 11 blocks a shared one)', $sharedAfter !== null, (string)$sharedAfter);

    // ================================================================ rename: the journal holds account ids, so a rename rewrites nothing else (9.9: no mirror text)
    $mn = (int)$mk('zz t810 mirror', 26)['id'];
    $other = (int)$mk('zz t810 mirror other', 26)['id'];
    $jl0 = (int)q('SELECT COUNT(*) n FROM journal_lines')[0]['n'];
    $je0 = (int)q('SELECT COUNT(*) n FROM journal_entries')[0]['n'];
    $r = $upd($mn, ['accountname' => 'zz t810 mirror renamed']);
    chk('rename: the account carries the new name, the other account is untouched and no journal row is written', $r['success'] && q('SELECT accountType FROM tblaccounttypes WHERE ID = ?', [$mn])[0]['accountType'] === 'zz t810 mirror renamed' && q('SELECT accountType FROM tblaccounttypes WHERE ID = ?', [$other])[0]['accountType'] === 'zz t810 mirror other' && (int)q('SELECT COUNT(*) n FROM journal_lines')[0]['n'] === $jl0 && (int)q('SELECT COUNT(*) n FROM journal_entries')[0]['n'] === $je0);
    $n0 = (int)q('SELECT COUNT(*) n FROM tbllogs')[0]['n'];
    $r = $upd($mn, ['description' => 'no rename']);
    chk('a save without a name change keeps the name', $r['success'] && q('SELECT accountType FROM tblaccounttypes WHERE ID = ?', [$mn])[0]['accountType'] === 'zz t810 mirror renamed');
    chk('update writes a log row inside the transaction', (int)q('SELECT COUNT(*) n FROM tbllogs')[0]['n'] === $n0 + 1);
    $src = file_get_contents(__DIR__ . '/../app/views/accounts/edit.php');
    chk('the edit form no longer posts initialname or isSubCategory', stripos($src, 'initialname') === false && stripos($src, 'name="check"') === false);
    $src = file_get_contents(__DIR__ . '/../app/views/accounts/add.php');
    chk('the add form has no isSubCategory box (check) and no accounttype source dropdown', stripos($src, 'name="check"') === false && stripos($src, 'name="accounttype"') === false);

    // ================================================================ routines: brand_level cycle safe, flags check catches loops
    chk('brand_level: unchanged results (root 1, 26 = 2, bank 105 = 4), NULL for a missing ID', (int)q('SELECT brand_level(1) v')[0]['v'] === 1 && (int)q('SELECT brand_level(26) v')[0]['v'] === 2
        && (int)q('SELECT brand_level(105) v')[0]['v'] === 4 && q('SELECT brand_level(999999) v')[0]['v'] === null);
    $L = (int)$mk('zz t810 loop', 26)['id'];
    $L2 = (int)$mk('zz t810 loop child', $L)['id'];
    q('UPDATE tblaccounttypes SET parentId = ? WHERE ID = ?', [$L2, $L]);       // a loop L -> L2 -> L, made by hand
    $t0 = microtime(true);
    $v = q('SELECT brand_level(?) v', [$L])[0]['v'];
    chk('brand_level on a loop returns NULL at once (the old body never ended)', $v === null && microtime(true) - $t0 < 5, "v=" . var_export($v, true));
    q('UPDATE tblaccounttypes SET parentId = ? WHERE ID = ?', [$L, $L]);        // and a self parent
    chk('brand_level on a self-parent returns NULL', q('SELECT brand_level(?) v', [$L])[0]['v'] === null);
    chk('AccountChart on a loop: chain / depth / root null, descendants and wouldCycle terminate', AccountChart::chain($pdo, $L) === null && AccountChart::depthOf($pdo, $L) === null && AccountChart::rootOf($pdo, $L) === null
        && is_array(AccountChart::descendants($pdo, $L)) && AccountChart::computeFlag($pdo, $L) === null);
    [$n, $by] = chartViolations();
    chk('chart_flags_check section F reports the loop (accounts not reachable from a root)', ($by['F'] ?? 0) >= 2, json_encode($by));
    $r = $upd($L2, ['description' => 'x']);
    chk('an edit of an account on a broken chain does not hang or crash', is_array($r));
    q('UPDATE tblaccounttypes SET parentId = 26 WHERE ID = ?', [$L]);
    q('UPDATE tblaccounttypes SET isSubCategory = 1 WHERE ID = ?', [$L2]);
    q('UPDATE tblaccounttypes SET isSubCategory = 0 WHERE ID = ?', [$P2]);
    [$n, $by] = chartViolations();
    chk('chart_flags_check section A reports a wrong stored flag; B a posting account with children', ($by['A'] ?? 0) >= 1 && ($by['B'] ?? 0) >= 0, json_encode($by));
    $ch = AccountChart::recomputeFlags($pdo, [$L, $L2, $P2]);
    chk('recomputeFlags repairs exactly the wrong flags and returns what it changed', flag($P2) === 1 && flag($L2) === 1 && count($ch) >= 1, json_encode($ch));
    q('UPDATE tblaccounttypes SET accountTypeId = 1 WHERE ID = ?', [$L2]);
    [$n, $by] = chartViolations();
    chk('chart_flags_check section D reports accountTypeId <> root', ($by['D'] ?? 0) === 1, json_encode($by));
    q('UPDATE tblaccounttypes SET accountTypeId = 2 WHERE ID = ?', [$L2]);
    q('UPDATE tblaccounttypes SET isBank = 1, parentId = 26 WHERE ID = ?', [$L2]);
    [$n, $by] = chartViolations();
    chk('chart_flags_check section G reports a bank not under 33', ($by['G'] ?? 0) >= 1, json_encode($by));
    q('UPDATE tblaccounttypes SET isBank = 0, parentId = ? WHERE ID = ?', [$L, $L2]);
    q('UPDATE tblaccounttypes SET deleted = 1 WHERE ID = ?', [$L]);
    [$n, $by] = chartViolations();
    chk('chart_flags_check section E reports an active account under a deleted parent', ($by['E'] ?? 0) === 1, json_encode($by));
    q('UPDATE tblaccounttypes SET deleted = 0 WHERE ID = ?', [$L]);
    // C: a posting on a reporting account
    $pdo->beginTransaction();
    Ledger::post($pdo, ['congregationId' => 11, 'entryDate' => '2026-01-15', 'transactionTypeId' => 8, 'transactionId' => 900814, 'reference' => 'T810', 'description' => 'T810'],
                 [Ledger::debit($P1, '1.00', 'T810'), Ledger::credit($P2, '1.00', 'T810')]);
    $pdo->commit();
    q('UPDATE tblaccounttypes SET parentId = ? WHERE ID = ?', [$P2, $mn]);      // P2 (with lines) now has a child by hand
    [$n, $by] = chartViolations();
    chk('chart_flags_check section C reports a reporting account with active journal lines', ($by['C'] ?? 0) >= 1, json_encode($by));
    q('UPDATE tblaccounttypes SET parentId = 26 WHERE ID = ?', [$mn]);
    $void(900814);

    // ================================================================ Banks module
    $nBanks = (int)q('SELECT COUNT(*) n FROM tblaccounttypes WHERE isBank = 1')[0]['n'];
    $r = $bankModel->create(['bankname' => 'zz t810 bank', 'account' => 'zz810acct', 'openingbal' => '', 'asof' => null]);
    $bk = (int)q("SELECT ID FROM tblaccounttypes WHERE accountType = 'zz t810 bank'")[0]['ID'];
    chk('Bank::create: parent 33, posting flag 1, 33 stays reporting', $r['success'] && (int)row($bk)['parentId'] === 33 && flag($bk) === 1 && flag(33) === 0 && (int)row($bk)['accountTypeId'] === 3, json_encode($r));
    clean('bank create');
    $r = $bankModel->create(['bankname' => 'ZZ T810 BANK', 'account' => '', 'openingbal' => '', 'asof' => null]);
    chk('Bank::create refuses a duplicate name (decision 75)', !$r['success'] && stripos(implode(' ', $r['errors']), 'already exists') !== false, implode('|', $r['errors']));
    chk('bankModel->nameError / checkExists: own bank name and account number found', $bankModel->nameError('zz t810 bank') !== '' && $bankModel->nameError('zz t810 bank', $bk) === '' && $bankModel->checkExists('zz810acct', '') === false && $bankModel->checkExists('zz810acct', $bk) === true);
    $r = $bankModel->update(['id' => $bk, 'bankname' => 'zz t810 bank renamed', 'account' => 'zz810acct2']);
    chk('Bank::update: own bank renamed in a transaction with a log row', $r['success'] && row($bk)['accountType'] === 'zz t810 bank renamed' && row($bk)['accountNo'] === 'zz810acct2', json_encode($r));
    $before = row(34);
    $r = $bankModel->update(['id' => 34, 'bankname' => 'zz t810 hijack', 'account' => '']);
    chk('Bank::update refuses a non-bank account (the old query renamed ANY account)', !$r['success'] && row(34)['accountType'] === $before['accountType'], json_encode($r));
    $r = $bankModel->update(['id' => 101, 'bankname' => 'zz t810 hijack', 'account' => '']);
    chk("Bank::update refuses another congregation's bank (101, congregation 2)", !$r['success'] && row(101)['accountType'] !== 'zz t810 hijack', json_encode($r));
    foreach (['', 'abc', '999999', '34 OR 1=1'] as $bad) {
        $r = $bankModel->update(['id' => $bad, 'bankname' => 'zz', 'account' => '']);
        chk("Bank::update with ID '$bad' refused cleanly", !$r['success']);
    }
    $r = $bankModel->update(['id' => $bk, 'bankname' => 'co-op bank', 'account' => '']);
    chk('Bank::update: name of another account refused (decision 75); an own-name save passes', !$r['success'] && $bankModel->update(['id' => $bk, 'bankname' => 'zz t810 bank renamed', 'account' => 'zz810acct2'])['success']);
    $r = $bankModel->update(['id' => $bk, 'bankname' => 'zz t810 bank renamed', 'account' => '01128659557700']);
    chk('Bank::update: account number unique per congregation (105 already has it)', !$r['success'] && has($r, 'number'), errs($r));
    // sub accounts
    $subOk = ['id' => null, 'isedit' => false, 'name' => 'zz t810 sub', 'bank' => (string)$bk, 'account' => (string)$F2, 'districtgroup' => 'group', 'param' => '1'];
    chk('sub account: own bank + a posting non-bank account is accepted', $bankModel->subAccountProblem($subOk) === null, (string)$bankModel->subAccountProblem($subOk));
    chk('sub account: a reporting account (26) refused', stripos((string)$bankModel->subAccountProblem(['account' => '26'] + $subOk), 'G/L') !== false);
    chk('sub account: a bank as the G/L account refused', $bankModel->subAccountProblem(['account' => '105'] + $subOk) !== null);
    chk('sub account: a root, a missing and a non-numeric account refused', $bankModel->subAccountProblem(['account' => '1'] + $subOk) !== null && $bankModel->subAccountProblem(['account' => '999999'] + $subOk) !== null && $bankModel->subAccountProblem(['account' => 'x'] + $subOk) !== null);
    chk("sub account: another congregation's bank (101) and a non-bank (34) as parent bank refused", $bankModel->subAccountProblem(['bank' => '101'] + $subOk) !== null && $bankModel->subAccountProblem(['bank' => '34'] + $subOk) !== null);
    chk('sub account: a deleted bank refused (99)', $bankModel->subAccountProblem(['bank' => '99'] + $subOk) !== null);
    $glList = array_column($bankModel->GetGlAccounts(), 'ID');
    chk('GL dropdown (Banks::subaccount): posting accounts only (F2 yes; 26, 105, 1 no)', in_array((string)$F2, array_map('strval', $glList), true) && !in_array(26, $glList) && !in_array('26', $glList) && !in_array('105', array_map('strval', $glList)) && !in_array('1', array_map('strval', $glList)));
    $existing = q('SELECT s.ID, s.BankId, s.AccountId FROM tblbanksubaccounts s JOIN tblaccounttypes b ON b.ID = s.BankId WHERE s.Deleted = 0 AND b.congregationId = 11 LIMIT 1');
    if ($existing) {
        $e = $existing[0];
        as_(1, 8, 0);
        chk("sub account edit: a sub account of another congregation's bank refused", stripos((string)$bankModel->subAccountProblem(['id' => (int)$e['ID'], 'isedit' => true, 'bank' => (string)$e['BankId'], 'account' => (string)$e['AccountId']] + $subOk), 'bank') !== false);
        as_(1, 11, 0);
    }
    $r = $bankModel->delete(['id' => $bk, 'bankname' => 'zz t810 bank renamed']);
    chk('Bank::delete: soft delete, parent 33 recomputed (still reporting), ledger cache cleared', $r['success'] && (int)row($bk)['deleted'] === 1 && flag(33) === 0, json_encode($r));
    $r = $acc->delete($bk);
    chk('the bank row (deleted) cannot be deleted on the account screens either', !$r['success']);
    // bank delete when it was 33's last child would flip 33: asserted through the library, no real bank is touched
    q('UPDATE tblaccounttypes SET deleted = 1 WHERE parentId = 33');
    $changed = AccountChart::recomputeFlags($pdo, [33]);
    chk('library: 33 would compute to posting (1) if every bank under it were deleted (flag follows the rule)', ($changed[33] ?? null) === 1);
    q('UPDATE tblaccounttypes SET deleted = 0 WHERE parentId = 33 AND ID <> ? AND ID <> 99', [$bk]);
    AccountChart::recomputeFlags($pdo, [33]);

    // ================================================================ index: one query, any depth, escaping
    $xss = (int)$mk('zz t810 <b>x</b>', 26)['id'];
    $tree = $acc->index();
    $ids = array_map(function ($r) { return $r->ID; }, $tree);
    chk('index: the whole tree at any depth (roots first, a depth-5 chain visible), banks listed read-only', in_array($A1, $ids, true) && $tree[0]->depth === 1 && count(array_filter($tree, function ($r) { return $r->depth === 4; })) >= 2 && count(array_unique($ids)) === count($ids));
    $queries = substr_count(file_get_contents(__DIR__ . '/../app/views/accounts/index.php'), 'new PDO');
    chk('index view has no per-row PDO connection', $queries === 0);
    $html = (function () use ($tree) { ob_start(); $data = ['accounts' => $tree]; $_SESSION['userName'] = 'x'; ob_end_clean(); return ''; })();
    chk('the account row with < in its name is stored as typed (output escaping is checked through the rendered page by test_8_10_http.php)', row($xss)['accountType'] === 'zz t810 <b>x</b>');

    // ================================================================ no leftovers in the chart after the whole run (before cleanup)
    // (cleanup follows; the final chart check runs after it)
} catch (Throwable $e) {
    $fail++;
    echo 'EXCEPTION ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
}

// ---------------------------------------------------------------- cleanup: everything this run created, then verify the table is as it was
$pdo->exec("DELETE FROM journal_lines WHERE ID > {$max['journal_lines']}");
$pdo->exec('DELETE FROM journal_entries WHERE ID > ' . $max['journal_entries']);
$pdo->exec('DELETE FROM tblbankpostings WHERE ID > ' . $max['tblbankpostings']);
$pdo->exec('DELETE FROM tbllogs WHERE ID > ' . $max['tbllogs']);
$pdo->exec('DELETE FROM tblaccounttypes WHERE ID > ' . $max['tblaccounttypes']);
Ledger::clearCashCache();
// real rows edited / hand-restored by this test
$now = $pdo->query('SELECT * FROM tblaccounttypes WHERE ID <= ' . $max['tblaccounttypes'] . ' ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC);
$diff = [];
foreach ($acctSnapshot as $i => $r) { if ($r != $now[$i]) { $diff[] = $r['ID']; } }
chk('cleanup: every pre-existing account row is exactly as before the run', !$diff && count($now) === count($acctSnapshot), json_encode($diff));
chk('cleanup: no leftover rows in journal / log / account tables', (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE ID > {$max['journal_entries']}")->fetchColumn() === 0);
$brandAfter = $pdo->query("SELECT ROUTINE_DEFINITION FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'brand_level'")->fetchColumn();
chk('cleanup: the routines are untouched', $brandBefore === $brandAfter);
chk('final: chart_flags_check empty', chartViolations()[0] === 0, json_encode(chartViolations()[1]));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

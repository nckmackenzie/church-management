<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
// 8.4d: guarded financial year delete and the year overlap check (dev only). Temporary years are created far in the
// future; every row the run creates is removed and no existing year is touched.
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Year.php';

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;
$_SESSION['isParish'] = 1;
$_SESSION['userId'] = (int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();

$tables = ['tblfiscalyears' => 'ID', 'journal_entries' => 'ID', 'tblcontributions_header' => 'ID', 'tblpettycash' => 'ID', 'tbllogs' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$existing = md5(json_encode($pdo->query('SELECT * FROM tblfiscalyears WHERE ID <= ' . $max['tblfiscalyears'] . ' ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC)));

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }

try {
    $ym = new Year;
    $mkyear = function ($name, $start, $end, $close = true) use ($ym, $pdo) {
        $ym->create(['yearname' => $name, 'startdate' => $start, 'enddate' => $end]);
        $id = (int)$pdo->query('SELECT MAX(ID) FROM tblfiscalyears')->fetchColumn();
        if ($close) { $ym->close(['id' => $id, 'yearname' => $name]); }
        return $id;
    };
    $msg = function ($r) { return implode(' | ', $r['errors']); };
    // a free period is derived from the data: the first October-September after the latest year (deleted years included)
    $lastEnd = new DateTime($pdo->query('SELECT MAX(endDate) FROM tblfiscalyears')->fetchColumn());
    $lastStart = new DateTime($pdo->query('SELECT startDate FROM tblfiscalyears ORDER BY endDate DESC, ID DESC LIMIT 1')->fetchColumn());
    $free = clone $lastEnd; $free->modify('+1 day');
    $freeStart = $free->format('Y-m-d');
    $freeEnd = (clone $free)->modify('+1 year -1 day')->format('Y-m-d');
    $oneOver = (clone $lastEnd)->format('Y-m-d');                                      // starts on the last day of the latest year
    $startsInside = (clone $lastStart)->modify('+5 months')->format('Y-m-d');          // starts inside the latest year
    $startsInsideEnd = (clone $lastStart)->modify('+17 months')->format('Y-m-d');

    // ---- overlap check (deleted years included)
    chk('overlap: a period after the last year is free', $ym->checkYearConflict($freeStart, $freeEnd) === true);
    chk('overlap: one day over the last year is refused', $ym->checkYearConflict($oneOver, $freeEnd) === false);
    chk('overlap: starting inside an existing year is refused', $ym->checkYearConflict($startsInside, $startsInsideEnd) === false);
    chk('overlap: ending inside an existing year is refused', $ym->checkYearConflict('2025-01-01', '2025-03-31') === false);
    chk('overlap: enclosing existing years is refused (the old check missed this)', $ym->checkYearConflict('2000-01-01', '2100-01-01') === false);
    chk('overlap: a DELETED year still blocks (2021/2022 is deleted)', $ym->checkYearConflict('2022-01-01', '2022-03-31') === false);
    chk('overlap: a period before every year is free', $ym->checkYearConflict('2010-01-01', '2010-12-31') === true);

    // ---- open year cannot be deleted
    $open = $mkyear('zz open year', '2090-10-01', '2091-09-30', false);
    $r = $ym->delete(['id' => $open, 'yearname' => 'zz open year']); echo '   [open year] ' . $msg($r) . "\n";
    chk('open year refused, says to close it first', !$r['success'] && strpos($msg($r), 'is open; close it before deleting it') !== false && q('SELECT deleted d FROM tblfiscalyears WHERE ID=?', [$open])[0]['d'] == 0);

    // ---- closed, empty year can be deleted
    $closed = $mkyear('zz closed year', '2091-10-01', '2092-09-30');
    $blockers = $ym->deleteBlockers($closed);
    chk('closed year without records: no blockers', $blockers === [], json_encode($blockers));
    $listed = array_values(array_filter($ym->index(), function ($y) use ($closed) { return (int)$y->ID === $closed; }));
    chk('index(): the closed empty year has no blockers (Delete button shown)', $listed && $listed[0]->deleteBlockers === []);
    $listedOpen = array_values(array_filter($ym->index(), function ($y) use ($open) { return (int)$y->ID === $open; }));
    chk('index(): the open year carries its blocker (Delete button disabled)', $listedOpen && count($listedOpen[0]->deleteBlockers) === 1 && strpos($listedOpen[0]->deleteBlockers[0], 'is open') !== false);
    $real = array_values(array_filter($ym->index(), function ($y) { return (int)$y->ID === 12; }));
    chk('index(): the real open year 2025-2026 is not deletable', $real && $real[0]->deleteBlockers && strpos(implode(' ', $real[0]->deleteBlockers), 'is open') !== false);
    $r = $ym->delete(['id' => $closed, 'yearname' => 'zz closed year']);
    chk('closed year without records deleted (soft)', $r['success'] && q('SELECT deleted d FROM tblfiscalyears WHERE ID=?', [$closed])[0]['d'] == 1, json_encode($r));
    $r = $ym->delete(['id' => $closed, 'yearname' => 'zz closed year']);
    chk('deleting an already deleted year is refused', !$r['success'] && strpos($msg($r), 'not found or already deleted') !== false);
    $r = $ym->delete(['id' => 987654, 'yearname' => 'x']);
    chk('unknown year refused', !$r['success']);
    $r = $ym->delete(['id' => 9, 'yearname' => 'x']);
    chk('already deleted real year (2022/2023) refused', !$r['success']);

    // ---- a closed year with active records cannot be deleted; each kind is checked
    $cases = [
        ['ledger entries',     "INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, deleted) VALUES (11, ?, 2, 2000000001, 0)", '2092-10-05', 'journal_entries', '1 ledger entries'],
        ['receipts',           "INSERT INTO tblcontributions_header (fiscalYearId, postedBy, postedDate, receiptNo, congregationId) VALUES (?, {$_SESSION['userId']}, '2026-01-01', 'ZZ1', 11)", 'ID', 'tblcontributions_header', '1 receipts'],
        ['petty cash entries', "INSERT INTO tblpettycash (TransactionDate, CongregationId) VALUES (?, 11)", '2092-10-06', 'tblpettycash', '1 petty cash entries'],
    ];
    foreach ($cases as $c) {
        $yr = $mkyear('zz year ' . $c[0], '2092-10-01', '2093-09-30');
        $val = $c[2] === 'ID' ? $yr : $c[2];
        $pdo->prepare($c[1])->execute([$val]);
        $rowId = (int)$pdo->query("SELECT MAX(ID) FROM {$c[3]}")->fetchColumn();
        $r = $ym->delete(['id' => $yr, 'yearname' => 'zz']); echo "   [{$c[0]}] " . $msg($r) . "\n";
        chk("closed year with {$c[0]} refused, reason names them", !$r['success'] && strpos($msg($r), $c[4]) !== false && q('SELECT deleted d FROM tblfiscalyears WHERE ID=?', [$yr])[0]['d'] == 0);
        // once the record is deleted the year can go
        $flag = $c[3] === 'tblpettycash' || $c[3] === 'tblcontributions_header' ? 'Deleted' : 'deleted';
        $pdo->prepare("UPDATE {$c[3]} SET $flag = 1 WHERE ID = ?")->execute([$rowId]);
        $r = $ym->delete(['id' => $yr, 'yearname' => 'zz']);
        chk("...and is deletable once the {$c[0]} are deleted", $r['success'], json_encode($r));
        $pdo->exec("UPDATE tblfiscalyears SET startDate = DATE_ADD(startDate, INTERVAL 10 YEAR), endDate = DATE_ADD(endDate, INTERVAL 10 YEAR) WHERE ID = $yr");   // free the period for the next case
    }
    // open AND records: both reasons
    $both = $mkyear('zz both', '2100-10-01', '2101-09-30', false);
    $pdo->prepare("INSERT INTO tblpettycash (TransactionDate, CongregationId) VALUES ('2100-11-01', 11)")->execute();
    $r = $ym->delete(['id' => $both, 'yearname' => 'zz']); echo '   [open + records] ' . $msg($r) . "\n";
    chk('open year with records: both reasons given', !$r['success'] && count($r['errors']) === 2);
} catch (Throwable $e) {
    $fail++; echo "EXCEPTION: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
}

foreach ($tables as $t => $k) { $pdo->prepare("DELETE FROM $t WHERE $k > ?")->execute([$max[$t]]); }
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k > {$max[$t]}")->fetchColumn(); }
$same = md5(json_encode($pdo->query('SELECT * FROM tblfiscalyears ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC))) === $existing;
echo "\nPASSED $pass FAILED $fail ; leftover rows after cleanup: $left ; existing years unchanged: " . ($same ? 'yes' : 'NO') . "\n";

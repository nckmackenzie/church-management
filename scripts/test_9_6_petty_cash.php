<?php
/** Task 9.6: ledger petty-cash book and read-only finance comparison. Self-cleaning, CLI-only. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir(__DIR__ . '/../app');
require 'boostrap.php';

$pdo = (new Database)->pdo(); $pass = 0; $fail = 0;
function ok96($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ($extra === '' ? '' : ": $extra") . "\n"; } }
function q96($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x96($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
function sums96(array $rows) { $d = 0; $c = 0; foreach ($rows as $r) { $d += $r['debit']; $c += $r['credit']; } return [$d, $c]; }

$created = ['entries' => [], 'petty' => [], 'expenses' => [], 'reqs' => [], 'congs' => []];
$out = __DIR__ . '/logs/zz-task9.6-comparison.xlsx';
try {
    echo "== finance comparison on the unmodified dev data\n";
    $tables = ['tblpettycash', 'journal_entries', 'journal_lines']; $before = [];
    foreach ($tables as $t) { $before[$t] = q96("CHECKSUM TABLE `$t`")[0]['Checksum']; }
    $d = PettyCashComparison::data($pdo);
    $key = []; foreach ($d['summary'] as $r) { $key[$r['year'] . ':' . $r['account']] = [$r['old'], $r['new']]; }
    ok96('2024-2025 account 98 is old 1,465,652.00 / ledger 109,600.00', ($key['2024-2025:98'] ?? null) === [146565200, 10960000], json_encode($key['2024-2025:98'] ?? null));
    ok96('2025-2026 account 98 is old 3,740,683.00 / ledger 1,232,300.00', ($key['2025-2026:98'] ?? null) === [374068300, 123230000], json_encode($key['2025-2026:98'] ?? null));
    ok96('account 194 is equal in 2025-2026', ($key['2025-2026:194'] ?? null) === [-112161500, -112161500]);
    $stale = array_column(array_filter($d['pettyOnly'], function ($r) { return in_array($r['id'], [665, 780, 643], true); }), 'id'); sort($stale);
    ok96('petty-only detail identifies stale rows 643, 665 and 780', $stale === [643, 665, 780], json_encode($stale));
    $expense1249 = array_values(array_filter($d['ledgerOnly'], function ($r) { return $r['typeId'] === 2 && $r['sourceId'] === 1249 && $r['account'] === 98 && $r['side'] === 'DEBIT' && $r['amount'] === 4079000; }));
    ok96('ledger-only detail identifies expense 1249 debit 40,790.00', count($expense1249) === 1, json_encode($expense1249));
    $sheets = PettyCashComparison::sheets($d); $book = XlsxWriter::buildWorkbook($sheets);
    ok96('comparison workbook has five sheets and is an XLSX ZIP', count($sheets) === 5 && substr($book, 0, 2) === 'PK' && strpos($book, 'Old report figures') !== false);
    $cmd = '"' . PHP_BINARY . '" "' . dirname(__DIR__) . '/scripts/pettycash_comparison.php" --out "' . $out . '"';
    exec($cmd . ' 2>&1', $console, $code);
    ok96('tracked CLI comparison script writes the workbook and prints a short summary', $code === 0 && is_file($out) && filesize($out) > 1000 && strpos(implode("\n", $console), '5 sheets') !== false, implode(" | ", $console));
    $after = []; foreach ($tables as $t) { $after[$t] = q96("CHECKSUM TABLE `$t`")[0]['Checksum']; }
    ok96('comparison class and CLI script change no source-table row or value', $before === $after, json_encode([$before, $after]));

    echo "== ledger-backed petty cash book fixture\n";
    $cong = 11; $scope = [$cong]; $_SESSION['congId'] = $cong; $_SESSION['userId'] = 64;
    $base98 = PettyCashReport::data($pdo,$scope,98,'2098-02-01','2098-02-28',1,50,'')['opening'];
    $base194 = PettyCashReport::data($pdo,$scope,194,'2098-02-01','2098-02-28',1,50,'')['opening'];
    $other = x96("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t96 other',0,0)"); $created['congs'][] = $other;
    $expenseAccount = (int) q96('SELECT ID FROM tblaccounttypes WHERE accountTypeId=2 AND deleted=0 ORDER BY ID LIMIT 1')[0]['ID'];
    $expense = x96('INSERT INTO tblexpenses (voucherNo,expenseType,expenseDate,accountId,paymethodId,PaidFromAccountId,amount,narration,status,postedBy,postedDate,deleted,congregationId) VALUES (96002,1,?,?,?,?,?,\'zz t96 expense\',1,64,?,0,?)', ['2098-02-10', $expenseAccount, 1, 98, '100.00', '2098-02-10', $cong]); $created['expenses'][] = $expense;
    $req = x96("INSERT INTO tblfundrequisition (ReqNo,RequisitionDate,Purpose,AmountRequested,AmountApproved,Status,DontDeduct,Deleted,CongregationId) VALUES (96003,'2098-02-12','zz t96 request',50,50,2,0,0,?)", [$cong]); $created['reqs'][] = $req;
    $petty = x96("INSERT INTO tblpettycash (ReceiptNo,TransactionDate,Debit,Credit,IsReceipt,PettyCashAccountId,Reference,Narration,CongregationId,Deleted) VALUES (96001,'2098-02-05',300,0,1,98,'zz t96 receipt ref','zz t96 receipt',?,0)", [$cong]); $created['petty'][] = $petty;
    $n = 0;
    $entry = function ($congregation, $date, $type, $tx, $journal, array $lines, $deleted = 0) use (&$created, &$n) {
        $n++; $id = x96('INSERT INTO journal_entries (congregationId,entryDate,transactionTypeId,transactionId,journalNo,reference,description,deleted) VALUES (?,?,?,?,?,?,?,?)', [$congregation,$date,$type,$tx,$journal,'zz t96 ref '.$n,'zz t96 <script>memo '.$n,$deleted]);
        $created['entries'][] = $id; $ln = 0; foreach ($lines as [$a,$dc,$amt]) { x96('INSERT INTO journal_lines (journalEntryId,accountId,dc,amount,memo,lineNumber) VALUES (?,?,?,?,?,?)', [$id,$a,$dc,$amt,'zz t96 <script>memo '.$n,++$ln]); } return $id;
    };
    $entry($cong,'2098-01-01',5,96000,96000,[[98,'DEBIT','1000.00'],[$expenseAccount,'CREDIT','1000.00']]);
    $entry($cong,'2098-02-05',10,$petty,null,[[98,'DEBIT','300.00'],[$expenseAccount,'CREDIT','300.00']]);
    $entry($cong,'2098-02-10',2,$expense,null,[[$expenseAccount,'DEBIT','100.00'],[98,'CREDIT','100.00']]);
    $entry($cong,'2098-02-12',12,$req,null,[[$expenseAccount,'DEBIT','50.00'],[98,'CREDIT','50.00']]);
    $entry($cong,'2098-02-14',5,96004,96004,[[98,'DEBIT','25.00'],[$expenseAccount,'CREDIT','25.00']]);
    $entry($cong,'2098-02-16',13,96005,null,[[98,'DEBIT','75.00'],[$expenseAccount,'CREDIT','75.00']]);
    $entry($cong,'2098-02-18',5,96006,96006,[[98,'DEBIT','999.00'],[$expenseAccount,'CREDIT','999.00']],1);
    $entry($other,'2098-02-19',5,96007,96007,[[98,'DEBIT','777.00'],[$expenseAccount,'CREDIT','777.00']]);
    $entry($cong,'2098-03-01',5,96008,96008,[[98,'DEBIT','888.00'],[$expenseAccount,'CREDIT','888.00']]);
    $entry($cong,'2098-02-20',2,96009,null,[[$expenseAccount,'DEBIT','60.00'],[194,'CREDIT','60.00']]);

    $r = PettyCashReport::data($pdo,$scope,98,'2098-02-01','2098-02-28',1,50,'');
    ok96('opening is cumulative from inception and excludes the range', $r['opening'] === $base98 + 100000, (string) $r['opening']);
    ok96('every active in-scope type in the fixture appears (10 receipt, 2 expense, 12 approval, 5 journal, 13 transfer)', array_values(array_unique(array_column($r['rows'],'typeId'))) === [10,2,12,5,13], json_encode(array_column($r['rows'],'typeId')));
    ok96('voided, other-congregation and after-range lines are excluded', count($r['rows']) === 5 && !in_array(99900,array_column($r['rows'],'debit'),true) && !in_array(77700,array_column($r['rows'],'debit'),true) && !in_array(88800,array_column($r['rows'],'debit'),true));
    $sources = []; foreach ($r['rows'] as $row) { $sources[$row['typeId']] = $row['source']; }
    ok96('source numbers map type 10 to receipt, type 2 to voucher and type 12 to requisition', strpos($sources[10],'96001') !== false && strpos($sources[2],'96002') !== false && strpos($sources[12],'96003') !== false, json_encode($sources));
    ok96('the journal source includes its journal number', strpos($sources[5],'96004') !== false);
    ok96('period receipts 400.00, payments 150.00 and closing rises by 250.00', $r['totals'] === ['debit'=>40000,'credit'=>15000] && $r['closing'] === $r['opening'] + 25000, json_encode([$r['totals'],$r['closing']]));
    [$dr,$cr] = sums96($r['rows']);
    ok96('detail rows add to report totals', [$dr,$cr] === [$r['totals']['debit'],$r['totals']['credit']]);
    $ledger = FinancialReport::linesPage($pdo,$scope,98,['kind'=>'range','from'=>'2098-02-01','to'=>'2098-02-28','direction'=>'debit'],false,1,0,'');
    ok96('petty cash report equals ledger statement for the same account/range', $r['opening'] === $ledger['opening'] && $r['closing'] === $ledger['closing'] && $r['totals'] === $ledger['totals'] && array_column($r['rows'],'lineId') === array_column($ledger['rows'],'lineId'));
    $r194 = PettyCashReport::data($pdo,$scope,194,'2098-02-01','2098-02-28',1,50,'');
    ok96('account 194 is separate: one 60.00 payment reduces its own cumulative balance', count($r194['rows']) === 1 && $r194['totals']['credit'] === 6000 && $r194['opening'] === $base194 && $r194['closing'] === $base194 - 6000);
    $search = PettyCashReport::data($pdo,$scope,98,'2098-02-01','2098-02-28',1,50,'96003');
    ok96('source-number search is server-side and keeps true report closing', $search['matching'] === 1 && $search['rows'][0]['typeId'] === 12 && $search['closing'] === $r['closing']);
    $page = PettyCashReport::data($pdo,$scope,98,'2098-02-01','2098-02-28',2,2,'');
    ok96('paging returns the requested slice with whole-period totals', $page['page'] === 2 && count($page['rows']) === 2 && $page['count'] === 5 && $page['totals'] === $r['totals']);
    $shaped = PettyCashReport::shape($r);
    ok96('JSON shape retains hostile narration only as data for the client textContent renderer', strpos($shaped['rows'][0]['narration'] ?? '','<script>') !== false);
    $accounts = PettyCashReport::accounts($pdo,$cong);
    ok96('dropdown contains only the two fixed, visible petty-cash accounts and names only', array_column($accounts,'id') === [98,194] && !isset($accounts[0]['code']));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    if (is_file($out)) { unlink($out); }
    foreach (array_reverse($created['entries']) as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId=?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID=?')->execute([$id]); }
    foreach ($created['petty'] as $id) { $pdo->prepare('DELETE FROM tblpettycash WHERE ID=?')->execute([$id]); }
    foreach ($created['expenses'] as $id) { $pdo->prepare('DELETE FROM tblexpenses WHERE ID=?')->execute([$id]); }
    foreach ($created['reqs'] as $id) { $pdo->prepare('DELETE FROM tblfundrequisition WHERE ID=?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID=?')->execute([$id]); }
}
echo "$pass passed / $fail failed\n";
exit($fail ? 1 : 0);

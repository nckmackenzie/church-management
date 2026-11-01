<?php
/**
 * Task 12 through Apache with real sessions (CLI only; needs the dev-only scripts/dev_session_helper.php):
 *   Part A: the Receipts form (a reference per row, no header reference, same messages in the browser JS and the server), the saved rows, bank rows and
 *           ledger lines, the list, the edit screen (old receipts show the header reference per row), escaping; Group / district collections (one row per
 *           collection: reference required, 25 characters = tblmmf.Reference), list, edit, delete.
 *   Part B: Excel = screen for the Balance b/f row and for the accumulated fund b/f lines (trial balance and balance sheet).
 * Self-cleaning: every row written is removed again (receipts through the application's own delete, the rest by ID).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir(__DIR__ . '/../app');
require 'boostrap.php';
$pdo = (new Database)->pdo();
$_SESSION = ['congId' => 11, 'userId' => 64];
$base = URLROOT; $pass = 0; $fail = 0;
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function ok($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
$helper = __DIR__ . '/dev_session_helper.php';
if (!is_file($helper)) { exit("scripts/dev_session_helper.php is missing\n"); }
require_once $helper;

/** [status, body, headers]; redirects are not followed */
function http($method, $path, $sid, $body = null, $json = false) {
    global $base, $phpErrors;
    $ch = curl_init($base . '/' . $path);
    $h = $json ? ['Content-Type: application/json'] : [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_HTTPHEADER => $h, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json ? json_encode($body) : http_build_query($body ?? []));
    }
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $hd = substr((string) $raw, 0, $hs); $b = substr((string) $raw, $hs);
    if (stripos($hd, 'spreadsheetml') === false && preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', $b, $m)) { $phpErrors[] = $path . ' -> ' . $m[2]; }
    return [$code, $b, $hd];
}
function J($b) { return json_decode($b, true); }
function redirectsTo($r, $needle) { return in_array($r[0], [301, 302, 303], true) && preg_match('~^location:\s*(\S+)~mi', $r[2], $m) && strpos($m[1], $needle) !== false; }
function xlsx($bytes) {
    $tmp = tempnam(sys_get_temp_dir(), 'xh') . '.xlsx'; file_put_contents($tmp, $bytes);
    $z = new ZipArchive();
    if ($z->open($tmp) !== true) { return null; }
    $xml = $z->getFromName('xl/worksheets/sheet1.xml'); $z->close(); @unlink($tmp);
    $sx = simplexml_load_string($xml); if ($sx === false) { return null; }
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $c) {
            $col = preg_replace('/\d+/', '', (string) $c['r']); $idx = 0; for ($i = 0; $i < strlen($col); $i++) { $idx = $idx * 26 + ord($col[$i]) - 64; }
            $r[$idx - 1] = isset($c->is) ? ['s', (string) $c->is->t] : ['n', (float) $c->v];
        }
        $rows[] = $r;
    }
    return $rows;
}
function cents($s) { $s = trim((string) $s); if ($s === '') { return 0; } $neg = $s[0] === '(' || $s[0] === '-'; $v = (int) round((float) str_replace([',', '(', ')', '-'], '', $s) * 100); return $neg ? -$v : $v; }

$tables = ['tblcontributions_header' => 'ID', 'tblcontributions_details' => 'ID', 'tblmmf' => 'ID', 'tblbanktransactions_subaccounts' => 'ID', 'journal_entries' => 'ID',
           'journal_lines' => 'ID', 'tblbankpostings' => 'ID', 'tbllogs' => 'ID'];
$max = []; foreach ($tables as $t => $k) { $max[$t] = (int) $pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$seqSnapshot = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$yearSnapshot = $pdo->query('SELECT ID, closed, deleted FROM tblfiscalyears ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC);
$phpErrors = [];
try {
    $admin = mkSession(64, 11);
    $member = (int) q('SELECT ID FROM tblmember WHERE congregationId=11 LIMIT 1')[0]['ID'];
    $today = date('Y-m-d');
    $newHeader = function () { return (int) q('SELECT MAX(ID) m FROM tblcontributions_header')[0]['m']; };
    $rowsOf = function ($id) { return q('SELECT paymentReference, amount FROM tblcontributions_details WHERE HeaderId=? ORDER BY ID', [$id]); };
    $banks = function ($id) { return q('SELECT reference, debit, cleared FROM tblbankpostings WHERE transactionType=1 AND transactionId=? AND deleted=0 ORDER BY ID', [$id]); };
    $form = function (array $o = []) use ($member, $today) {
        return $o + ['date' => $today, 'paymethod' => '2', 'bank' => 105, 'cashaccount' => '', 'description' => 'task 12 http',
                     'accountsid' => [93, 94], 'accountsname' => ['a', 'b'], 'amounts' => ['10.50', '20'], 'categoriesid' => [1, 1], 'categoriesname' => ['M', 'M'],
                     'contributorsid' => [$member, $member], 'contributorsname' => ['X', 'X'], 'subaccount' => ['', ''], 'references' => ['HT-A1', 'HT-B2'], 'id' => '', 'isedit' => ''];
    };

    echo "== Receipts form\n";
    $r = http('GET', 'contributions/add', $admin);
    ok('the add form opens', $r[0] === 200 && strpos($r[1], 'contributions/create') !== false);
    ok('no header reference field any more (name="reference" is gone)', !preg_match('~name="reference"\s|name="reference"$|id="reference"~m', $r[1]));
    ok('a row reference input next to the amount (starts empty, 50 characters), and a Reference column in the rows table', preg_match('~id="rowreference"[^>]*maxlength="50"~', $r[1]) === 1 && strpos($r[1], '<th>Reference</th>') !== false && strpos($r[1], 'id="rowreference"') !== false);
    ok('the group / district collection window has its own required reference (the row of a collection needs one too)', strpos($r[1], 'id="collectionreference"') !== false && preg_match('~id="collectionreference"[^>]*mandatory~', $r[1]) === 1);
    $js = (string) @file_get_contents(URLROOT . '/dist/js/pages/contributions-v2.js');
    ok('browser validation uses the same messages as the server (enter the reference / cannot be longer than 50 characters), a new row starts empty', strpos($js, 'enter the reference') !== false && strpos($js, 'cannot be longer than ${MAX_REFERENCE} characters') !== false && strpos($js, "rowReferenceInput.value = ''") !== false && strpos($js, 'referenceError(') !== false);
    $cjs = (string) @file_get_contents(URLROOT . '/dist/js/pages/contributions-collection.js');
    ok('the collection window validates the reference too and adds references[] to the row', strpos($cjs, 'enter the reference') !== false && strpos($cjs, 'name="references[]"') !== false);

    $n0 = (int) q('SELECT COUNT(*) c FROM tblcontributions_header')[0]['c'];
    $r = http('POST', 'contributions/create', $admin, $form(['references' => ['HT-A1', '']]));
    ok('a missing row reference: the form comes back with the message naming the row; nothing saved', $r[0] === 200 && strpos($r[1], 'Line 2: enter the reference') !== false && (int) q('SELECT COUNT(*) c FROM tblcontributions_header')[0]['c'] === $n0);
    ok('the rows typed are kept on the returned form (reference of row 1 included)', strpos($r[1], 'value="HT-A1"') !== false);
    $r = http('POST', 'contributions/create', $admin, $form(['references' => [str_repeat('Z', 51), 'ok']]));
    ok('a 51-character reference: the same length message as the browser', strpos($r[1], 'Line 1: reference cannot be longer than 50 characters') !== false && (int) q('SELECT COUNT(*) c FROM tblcontributions_header')[0]['c'] === $n0);

    $r = http('POST', 'contributions/create', $admin, $form());
    $h1 = $newHeader();
    ok('a valid 2-row receipt: saved and redirected to the list', redirectsTo($r, 'contributions') && $h1 > $max['tblcontributions_header'], 'status ' . $r[0]);
    ok('the rows keep their own references; two bank rows with their own reference and amount', array_column($rowsOf($h1), 'paymentReference') === ['HT-A1', 'HT-B2'] && array_column($banks($h1), 'reference') === ['HT-A1', 'HT-B2'] && array_map('floatval', array_column($banks($h1), 'debit')) === [10.5, 20.0]);
    $r = http('GET', 'contributions', $admin);
    ok('the receipts list shows the row references, comma-separated (no header reference column)', strpos($r[1], 'HT-A1, HT-B2') !== false && strpos($r[1], '<th>Reference</th>') !== false);
    $r = http('GET', "contributions/edit/$h1", $admin);
    ok('edit screen: each row has its own reference input, filled', preg_match_all('~name="references\[\]"[^>]*value="(HT-A1|HT-B2)"~', $r[1], $m) === 2 && !preg_match('~id="reference"~', $r[1]));

    // escaping
    $r = http('POST', 'contributions/create', $admin, $form(['references' => ['a"><script>x</script>', 'b&c']]));
    $h2 = $newHeader();
    ok('a reference with markup is saved as typed', redirectsTo($r, 'contributions') && array_column($rowsOf($h2), 'paymentReference') === ['a"><script>x</script>', 'b&c'], 'status ' . $r[0]);
    $r = http('GET', "contributions/edit/$h2", $admin);
    ok('edit screen: the markup in a reference is escaped on output', strpos($r[1], '<script>x</script>') === false && strpos($r[1], 'a&quot;&gt;&lt;script&gt;x&lt;/script&gt;') !== false && strpos($r[1], 'b&amp;c') !== false);
    $r = http('GET', 'contributions', $admin);
    ok('the list: the markup in a reference is escaped', strpos($r[1], '<script>x</script>') === false && strpos($r[1], 'A&quot;&gt;&lt;SCRIPT&gt;X&lt;/SCRIPT&gt;') !== false);

    // old receipt: the rows without their own reference show the header reference; saving works with it
    $e = (int) q('SELECT ID FROM journal_entries WHERE congregationId=11 AND transactionTypeId=1 AND transactionId=? AND deleted=0', [$h1])[0]['ID'];
    q('UPDATE journal_lines SET reference=NULL WHERE journalEntryId=?', [$e]);
    q('UPDATE journal_entries SET reference=? WHERE ID=?', ['OLD<HEADER>', $e]);
    q('UPDATE tblcontributions_details SET paymentReference=NULL WHERE HeaderId=?', [$h1]);
    $r = http('GET', "contributions/edit/$h1", $admin);
    ok('an old receipt (rows without a reference): every row is pre-filled with the old header reference (escaped)', preg_match_all('~name="references\[\]"[^>]*value="OLD&lt;HEADER&gt;"~', $r[1]) === 2);
    $r = http('GET', 'contributions', $admin);
    ok('the list shows the old header reference for those rows', strpos($r[1], 'OLD&lt;HEADER&gt;') !== false);
    $r = http('POST', 'contributions/create', $admin, $form(['id' => $h1, 'isedit' => '1', 'references' => ['OLD<HEADER>', 'OLD<HEADER>']]));
    ok('the old receipt can be saved with the pre-filled references (duplicates allowed)', redirectsTo($r, 'contributions') && array_column($rowsOf($h1), 'paymentReference') === ['OLD<HEADER>', 'OLD<HEADER>'] && count($banks($h1)) === 2, 'status ' . $r[0]);

    foreach ([$h1, $h2] as $id) {
        $r = http('POST', 'contributions/delete', $admin, ['id' => $id]);
        ok("receipt $id deleted through the page: entry and every bank row voided", redirectsTo($r, 'contributions') && !q('SELECT 1 FROM journal_entries WHERE transactionTypeId=1 AND transactionId=? AND deleted=0', [$id]) && !$banks($id));
    }

    echo "== Group / district collections\n";
    $G = (int) q('SELECT s.GroupId FROM tblbanksubaccounts s JOIN tblgroups g ON g.ID = s.GroupId WHERE s.GroupDistrict=\'group\' AND s.Deleted=0 AND g.congregationId=11 LIMIT 1')[0]['GroupId'];
    $SUB = (int) q('SELECT ID FROM tblbanksubaccounts WHERE GroupId=? AND GroupDistrict=\'group\' AND Deleted=0 LIMIT 1', [$G])[0]['ID'];
    $r = http('GET', 'groupcollections/add', $admin);
    ok('the collection form has a required reference (25 characters), one row per collection', preg_match('~name="reference"[^>]*maxlength="25"[^>]*mandatory|name="reference"[^>]*mandatory[^>]*maxlength="25"|maxlength="25"[^>]*name="reference"~', $r[1]) === 1 || preg_match('~id="reference"[^>]*maxlength="25"~', $r[1]) === 1);
    $cb = ['isedit' => false, 'id' => null, 'tdate' => $today, 'type' => 'group', 'groupid' => $G, 'amount' => '75', 'narration' => 'task 12 http', 'subaccount' => $SUB, 'reference' => ''];
    $r = http('POST', 'groupcollections/createupdate', $admin, $cb, true);
    ok('collection without a reference -> 422 "Enter the reference"', $r[0] === 422 && J($r[1])['message'] === 'Enter the reference', $r[1]);
    $r = http('POST', 'groupcollections/createupdate', $admin, ['reference' => str_repeat('R', 26)] + $cb, true);
    ok('collection with a 26-character reference -> 422 with the length', $r[0] === 422 && strpos(J($r[1])['message'], 'cannot be longer than 25 characters') !== false, $r[1]);
    $r = http('POST', 'groupcollections/createupdate', $admin, ['reference' => 'GC<1>'] + $cb, true);
    $cid = (int) q('SELECT MAX(ID) m FROM tblmmf WHERE TransactionType=17')[0]['m'];
    ok('collection with a reference -> 201; the mmf row, the bank row and both ledger lines carry it', $r[0] === 201 && q('SELECT Reference FROM tblmmf WHERE ID=?', [$cid])[0]['Reference'] === 'GC<1>'
        && q('SELECT reference FROM tblbankpostings WHERE transactionType=17 AND transactionId=? AND deleted=0', [$cid])[0]['reference'] === 'GC<1>'
        && array_column(q('SELECT l.reference FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=17 AND e.transactionId=? AND e.deleted=0', [$cid]), 'reference') === ['GC<1>', 'GC<1>'], $r[1]);
    $r = http('GET', 'groupcollections', $admin);
    ok('the collections list shows the reference (escaped)', strpos($r[1], 'GC&lt;1&gt;') !== false && strpos($r[1], 'GC<1>') === false);
    $r = http('GET', "groupcollections/edit/$cid", $admin);
    ok('the collection edit form is pre-filled with the reference (escaped)', strpos($r[1], 'value="GC&lt;1&gt;"') !== false);
    $r = http('POST', 'groupcollections/delete', $admin, ['id' => $cid]);
    ok('collection delete through the page = void', redirectsTo($r, 'groupcollections') && !q('SELECT 1 FROM journal_entries WHERE transactionTypeId=17 AND transactionId=? AND deleted=0', [$cid]) && !q('SELECT 1 FROM tblbankpostings WHERE transactionType=17 AND transactionId=? AND deleted=0', [$cid]));

    echo "== Part B: Excel = screen\n";
    $asof = '2026-10-24'; $start = FinancialReport::yearStart($pdo, $asof);
    $acc = (int) $pdo->query("SELECT l.accountId FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId JOIN tblaccounttypes a ON a.ID = l.accountId
                              WHERE e.congregationId = 11 AND e.deleted = 0 AND a.accountTypeId = 3 AND e.entryDate < '$start'
                                AND l.accountId IN (SELECT l2.accountId FROM journal_lines l2 JOIN journal_entries e2 ON e2.ID = l2.journalEntryId WHERE e2.congregationId = 11 AND e2.deleted = 0 AND e2.entryDate BETWEEN '$start' AND '$asof')
                              GROUP BY l.accountId ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
    $row = function ($rows, $label) { foreach ($rows as $r) { foreach ($r as $c) { if ($c[0] === 's' && $c[1] === $label) { return $r; } } } return null; };
    foreach (['balance sheet' => ["reports/balancesheetdrillrpt?account=$acc&asof=$asof&view=lines", 'closing'], 'trial balance' => ["trialbalance/drillrpt?account=$acc&asof=$asof&view=lines", 'closing']] as $label => [$url, $_]) {
        $r = http('GET', $url, $admin); $j = J($r[1]);
        ok("$label drill (account $acc): the screen has the Balance b/f dated the year start and only the lines of the year", $r[0] === 200 && $j['mode'] === 'lines' && $j['balanceBf']['label'] === 'Balance b/f' && $j['balanceBf']['date'] === date('d-m-Y', strtotime($start))
            && !array_filter($j['rows'], function ($x) use ($start) { return date('Y-m-d', strtotime($x['date'])) < $start; }), substr($r[1], 0, 300));
        $x = http('GET', $url . '&format=xlsx', $admin); $rows = xlsx($x[1]);
        $bf = $rows ? $row($rows, 'Balance b/f') : null;
        $cl = $rows ? $row($rows, 'Closing balance') : null;
        ok("$label Excel = screen: a Balance b/f row with the screen's figure comes first, and the closing balance equals the screen", $x[0] === 200 && $bf !== null && abs(end($bf)[1] - cents($j['opening']) / 100) < 0.005
            && $cl !== null && abs(end($cl)[1] - cents($j['closing']) / 100) < 0.005, json_encode($bf));
        $nLines = count(array_filter($rows, function ($r) { return isset($r[1]) && $r[1][0] === 'n' && isset($r[0]) && $r[0][0] === 'n'; }));
        ok("$label Excel = screen: as many lines as the screen's " . $j['count'], $nLines === $j['count']);
    }
    // the accumulated fund b/f: one line per earlier financial year; Excel = screen
    $cong = 11;
    foreach (['balance sheet' => ["reports/balancesheetdrillrpt?account=bf&asof=$asof", 'amountRaw'], 'trial balance' => ["trialbalance/drillrpt?account=bf&asof=$asof&bf=1", 'creditRaw']] as $label => [$url, $_]) {
        $r = http('GET', $url, $admin); $j = J($r[1]);
        $years = array_values(array_filter($j['rows'], function ($x) { return ($x['next'] ?? '') === 'incomestatement'; }));
        ok("$label b/f drill: one line per earlier financial year, 'Surplus / (deficit) <year>', each opening that year's income statement", $r[0] === 200 && $j['sumsExactly'] && count($years) >= 1 && count(array_filter($years, function ($x) { return strpos($x['name'], 'Surplus / (deficit) ') === 0 && $x['start'] && $x['end']; })) === count($years), substr($r[1], 0, 300));
        $x = http('GET', $url . '&format=xlsx', $admin); $rows = xlsx($x[1]);
        $names = []; foreach ($rows as $rw) { if (isset($rw[0]) && $rw[0][0] === 's' && strpos($rw[0][1], 'Surplus / (deficit) ') === 0) { $names[] = $rw[0][1]; } }
        ok("$label b/f Excel = screen: the same year lines in the same order", $x[0] === 200 && $names === array_column($years, 'name'), json_encode([$names, array_column($years, 'name')]));
    }
    $pr = http('GET', "parishreports/trialbalancedrillrpt?account=bf&asof=$asof&bf=1", $admin);
    ok('parish trial balance: the b/f drill gives the same kind of per-year lines', $pr[0] === 200 && J($pr[1])['sumsExactly'] && count(J($pr[1])['rows']) >= 1);
    $pg = http('GET', 'trialbalance/drill?account=bf&asof=' . $asof . '&bf=1', $admin);
    ok('the trial balance drill page carries the income statement address for the year lines', strpos($pg[1], 'data-isurl="' . URLROOT . '/reports/incomestatement"') !== false);
    $pg = http('GET', 'parishreports/trialbalancedrill?account=bf&asof=' . $asof . '&bf=1', $admin);
    ok('the parish drill page carries the parish income statement address', strpos($pg[1], 'data-isurl="' . URLROOT . '/parishreports/incomestatement"') !== false);
} catch (Throwable $e) {
    $fail++; echo 'FAIL exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (array_reverse($tables, true) as $t => $k) { $pdo->exec("DELETE FROM $t WHERE $k > {$max[$t]}"); }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec('DELETE FROM receipt_number_seq');
    $ins = $pdo->prepare('INSERT INTO receipt_number_seq (congregationId,kind,fiscalYearId,lastNo) VALUES (?,?,?,?)');
    foreach ($seqSnapshot as $s) { $ins->execute([$s['congregationId'], $s['kind'], $s['fiscalYearId'], $s['lastNo']]); }
    ok('no PHP warning / notice / fatal error in any response', !$phpErrors, json_encode(array_values(array_unique($phpErrors))));
    foreach ($sessions as $sid) { @unlink(session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid); }
    foreach ($tables as $t => $k) { ok("cleanup: $t back to max id {$max[$t]}", (int) $pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn() === $max[$t]); }
    ok('cleanup: receipt number sequences and fiscal year flags as before', $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC) == $seqSnapshot && $pdo->query('SELECT ID, closed, deleted FROM tblfiscalyears ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC) == $yearSnapshot);
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

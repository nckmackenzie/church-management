<?php
/**
 * Task 9.1 through Apache with real sessions: the report-safe selectors (accounts and banks), server-side refusal of forged IDs
 * (400), the payment-by-invoice-number report by header ID with two suppliers sharing an invoice number. Self-cleaning.
 * CLI only. Needs the ignored scripts/dev_session_helper.php.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
$pdo = (new Database)->pdo();
$_SESSION = ['congId' => 11, 'userId' => 64];
$base = URLROOT; $pass = 0; $fail = 0;
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
function ok($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
$helper = __DIR__ . '/dev_session_helper.php';
if (!is_file($helper)) { exit("scripts/dev_session_helper.php is missing\n"); }
require_once $helper;
function http($method, $path, $sid, $body = null) {
    global $base, $phpErrors;
    $ch = curl_init($base . '/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body ?? [])); }
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $b = substr((string) $raw, $hs);
    if (preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', $b, $m)) { $phpErrors[] = $path . ' -> ' . $m[2]; }
    return [$code, $b];
}
/** the <option> list of a rendered page: value => visible text (entity-decoded) */
function options($html) { preg_match_all('~<option value="([^"]*)"[^>]*>(.*?)</option>~s', $html, $m, PREG_SET_ORDER); $o = []; foreach ($m as $r) { $o[$r[1]][] = html_entity_decode($r[2], ENT_QUOTES, 'UTF-8'); } return $o; }

$created = ['accounts' => [], 'entries' => [], 'suppliers' => [], 'headers' => [], 'payments' => []];
try {
    $admin11 = mkSession(64, 11);
    $admin2  = mkSession(64, 2);

    // ------------------------------------------------------------ fixtures in congregation 11 (own) and 2 (foreign)
    $acct = function ($name, $parent, $o = []) use (&$created) {
        $o += ['cong' => 11, 'active' => 1, 'deleted' => 0, 'bank' => 0, 'posting' => 1];
        $root = (int) q('SELECT accountTypeId FROM tblaccounttypes WHERE ID = ?', [$parent])[0]['accountTypeId'] ?: $parent;
        $id = x('INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, isBank, accountNo, forGroup, active, congregationId, deleted) VALUES (?,?,?,?,?,?,0,?,?,?)',
                [$name, $root, $parent, $o['posting'], $o['bank'], $o['bank'] ? 'ZZ' . mt_rand(100, 999) : null, $o['active'], $o['cong'], $o['deleted']]);
        $created['accounts'][] = $id; return $id;
    };
    $incInactive = $acct('zz t91h inactive income', 32, ['active' => 0]);           // type 1 under 'tithes and offering'
    $incActive   = $acct('zz t91h active income', 32);
    $incForeign  = $acct('zz t91h foreign income', 32, ['cong' => 2]);
    $expDeletedH = $acct('zz t91h deleted expense with history', 26, ['deleted' => 1]);
    $expDeletedN = $acct('zz t91h deleted expense no history', 26, ['deleted' => 1]);
    $expForeign  = $acct('zz t91h foreign expense', 26, ['cong' => 2]);
    $expActive   = $acct('zz t91h active expense', 26);
    $bankA = $acct('zz t91h bank active', 33, ['bank' => 1]);
    $bankH = $acct('zz t91h bank inactive with history', 33, ['bank' => 1, 'active' => 0]);
    $bankN = $acct('zz t91h bank inactive no history', 33, ['bank' => 1, 'active' => 0]);
    $bankF = $acct('zz t91h bank foreign', 33, ['bank' => 1, 'cong' => 2]);
    $bankD = $acct('zz t91h bank deleted', 33, ['bank' => 1, 'deleted' => 1]);
    $n = 0;
    $entry = function ($lines) use (&$created, &$n) {
        $n++; $id = x("INSERT INTO journal_entries (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted) VALUES (11, '2026-10-20', 5, ?, ?, ?, 'zz t91h', 0)", [940000 + $n, 940000 + $n, 'zz t91h ' . $n]);
        $created['entries'][] = $id; $ln = 0; foreach ($lines as [$a, $dc, $amt]) { x('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber) VALUES (?,?,?,?,?,?)', [$id, $a, $dc, $amt, 'zz t91h', ++$ln]); } return $id;
    };
    $entry([[$bankH, 'DEBIT', '10.00'], [$incInactive, 'CREDIT', '10.00']]);          // inactive income + inactive bank both have history
    $entry([[$expDeletedH, 'DEBIT', '5.00'], [$bankA, 'CREDIT', '5.00']]);            // deleted expense has history

    // suppliers sharing one invoice number (congregation 11) and a foreign supplier (congregation 2)
    $sup = function ($name, $cong = 11) use (&$created) { $id = x('INSERT INTO tblsuppliers (supplierName, congregationId) VALUES (?, ?)', [$name, $cong]); $created['suppliers'][] = $id; return $id; };
    $sA = $sup('zz t91h <b>Supplier</b> A'); $sB = $sup('zz t91h Supplier B'); $sF = $sup('zz t91h Foreign Supplier', 2);
    $hdr = function ($sid, $no, $cong = 11, $deleted = 0) use (&$created) {
        $id = x("INSERT INTO tblinvoice_header_suppliers (invoiceDate, duedate, supplierId, invoiceNo, fiscalYearId, inclusiveVat, status, postedBy, deleted, congregationId) VALUES ('2026-10-20','2026-11-20',?,?,25,100.00,0,64,?,?)", [$sid, $no, $deleted, $cong]);
        $created['headers'][] = $id; return $id; };
    $pay = function ($hid, $amt, $ref, $no) use (&$created) { $id = x("INSERT INTO tblinvoice_payments_suppliers (paymentNo, paymentNo2, invoice_Id, paymentDate, amount, paymentId, paymentReference, Deleted) VALUES (?,?,?,'2026-10-21',?,1,?,0)", [$no, $no, $hid, $amt, $ref]); $created['payments'][] = $id; return $id; };
    $hA = $hdr($sA, 'ZZT91-INV'); $hB = $hdr($sB, 'ZZT91-INV'); $hF = $hdr($sF, 'ZZT91-INV', 2); $hDel = $hdr($sA, 'ZZT91-DEL', 11, 1);
    $hOpen = $hdr($sA, null);   // an opening-balance row has no number
    $pay($hA, '111.00', 'ZZ A REF', 990001); $pay($hB, '222.00', 'ZZ B REF', 990002); $pay($hF, '333.00', 'ZZ F REF', 990003); $pay($hDel, '444.00', 'ZZ D REF', 990004); $pay($hOpen, '555.00', 'ZZ O REF', 990005);

    // ------------------------------------------------------------ pages render, with the right lists
    echo "== selectors on the report screens\n";
    $pages = ['reports/contributions', 'reports/expenses', 'reports/ledger_statement', 'reports/banking', 'parishreports/contributions', 'parishreports/bycontributor',
              'parishreports/expenses', 'parishreports/banking', 'bankbalances/add', 'bankreconcilliations', 'bankreconcilliations/cashflow_statement', 'invoicereports'];
    $html = [];
    foreach ($pages as $p) { [$code, $body] = http('GET', $p, $admin11); $html[$p] = $body; ok("renders $p", $code === 200, "status $code"); }
    $inc = options($html['reports/contributions']); $expo = options($html['reports/expenses']); $led = options($html['reports/ledger_statement']);
    ok('contributions: inactive income account listed and labelled "(inactive)"', isset($inc[$incInactive]) && substr($inc[$incInactive][0], -10) === '(inactive)');
    ok('contributions: active own account listed; foreign account never', isset($inc[$incActive]) && !isset($inc[$incForeign]));
    ok('contributions: expense accounts not offered', !isset($inc[$expActive]));
    ok('expenses: deleted account WITH history listed and labelled "(deleted)"; deleted without history and foreign not listed', isset($expo[$expDeletedH]) && substr($expo[$expDeletedH][0], -9) === '(deleted)' && !isset($expo[$expDeletedN]) && !isset($expo[$expForeign]) && isset($expo[$expActive]));
    ok('ledger statement: inactive, deleted-with-history, shared and bank accounts listed; deleted-without-history and foreign not', isset($led[$incInactive], $led[$expDeletedH], $led[$bankA], $led[32]) && !isset($led[$expDeletedN]) && !isset($led[$incForeign]) && !isset($led[$expForeign]));
    ok('ledger statement: labels show the path', $led[$incActive][0] === 'income > tithes and offering > zz t91h active income');
    foreach (['reports/banking', 'parishreports/banking', 'bankbalances/add', 'bankreconcilliations', 'bankreconcilliations/cashflow_statement'] as $p) {
        $o = options($html[$p]);
        ok("$p: active bank, inactive bank with history (labelled) listed", isset($o[$bankA]) && isset($o[$bankH]) && strpos(end($o[$bankH]), '(inactive)') !== false);
        ok("$p: inactive without history, other congregation's and deleted banks not listed", !isset($o[$bankN]) && !isset($o[$bankF]) && !isset($o[$bankD]));
    }
    $po = options($html['parishreports/contributions']); $pe = options($html['parishreports/expenses']); $pb = options($html['parishreports/bycontributor']);
    ok('parish contributions / by contributor: inactive and foreign-congregation income accounts listed (parish scope), deleted-without-history never', isset($po[$incInactive], $po[$incForeign], $pb[$incInactive]) && !isset($po[$expDeletedN]));
    ok('parish expenses: deleted-with-history listed, deleted-without-history not', isset($pe[$expDeletedH]) && !isset($pe[$expDeletedN]));

    // ------------------------------------------------------------ forged IDs
    echo "== forged IDs\n";
    $get = function ($path, $sid = null) use ($admin11) { return http('GET', $path, $sid ?? $admin11); };
    $q = http_build_query(['from' => '2026-10-01', 'to' => '2026-10-31', 'subledgers' => 0]);
    foreach (['another congregation' => $incForeign, 'deleted without history' => $expDeletedN, 'non-digit' => 'abc', 'SQL text' => '1 OR 1=1', 'zero' => '0', 'empty' => ''] as $label => $acc) {
        [$code] = $get('reports/ledgerstatementrpt?account=' . urlencode((string) $acc) . '&' . $q);
        ok("ledger statement refuses $label with 400", $code === 400, "status $code");
    }
    [$code, $body] = $get('reports/ledgerstatementrpt?' . $q);
    ok('ledger statement refuses a missing account with 400', $code === 400, "status $code");
    [$code, $body] = $get('reports/ledgerstatementrpt?account=' . $incInactive . '&' . $q);
    ok('ledger statement accepts an inactive account with history (200 and the statement table; the figures still come from the old routine until 9.3)', $code === 200 && strpos($body, 'id="table"') !== false, "status $code");
    [$code] = $get('reports/ledgerstatementrpt?account=' . $expDeletedH . '&' . $q);
    ok('ledger statement accepts a deleted account with history', $code === 200, "status $code");
    $dates = '&start=2026-10-01&end=2026-10-31';
    foreach (['foreign account' => $incForeign, 'deleted without history' => $expDeletedN, 'expense account in the income report' => $expActive] as $label => $acc) {
        [$code] = $get('reports/contributionsrpt?type=2&account%5B%5D=' . $acc . $dates);
        ok("contribution report refuses $label with 400", $code === 400, "status $code");
    }
    [$code] = $get('reports/contributionsrpt?type=2&account%5B%5D=' . $incInactive . $dates);
    ok('contribution report accepts an inactive account', $code === 200, "status $code");
    [$code] = $get('reports/contributionsrpt?type=1' . $dates);
    ok('contribution report type 1 (all) still needs no account list', $code === 200, "status $code");
    foreach (['foreign account' => $expForeign, 'deleted without history' => $expDeletedN, 'income account in the expense report' => $incActive] as $label => $acc) {
        [$code] = $get('reports/expensesrpt?type=2&account%5B%5D=' . $acc . $dates);
        ok("expense report refuses $label with 400", $code === 400, "status $code");
    }
    [$code] = $get('reports/expensesrpt?type=2&account%5B%5D=' . $expDeletedH . $dates);
    ok('expense report accepts a deleted account with history', $code === 200, "status $code");
    $pq = 'congregations%5B%5D=11&start=2026-10-01&end=2026-10-31';
    foreach (['parishreports/contributionsrpt' => [$incInactive, $expDeletedN], 'parishreports/bycontributorrpt' => [$incInactive, $expDeletedN], 'parishreports/expensesrpt' => [$expDeletedH, $expDeletedN]] as $path => [$good, $bad]) {
        [$c1] = $get($path . '?' . $pq . '&accounts%5B%5D=' . $good); [$c2] = $get($path . '?' . $pq . '&accounts%5B%5D=' . $bad); [$c3] = $get($path . '?' . $pq . '&accounts%5B%5D=abc');
        ok("$path: accepts a listed account, refuses a deleted-without-history one and a non-digit with 400", $c1 === 200 && $c2 === 400 && $c3 === 400, "$c1/$c2/$c3");
    }
    $bq = 'from=2026-10-01&to=2026-10-31&status=0';
    foreach (['reports/bankingrpt', 'parishreports/bankingrpt'] as $path) {
        [$c1] = $get($path . '?bank=' . $bankH . '&' . $bq);
        $bad = [];
        foreach (['foreign bank' => $bankF, 'deleted bank' => $bankD, 'inactive bank without history' => $bankN, 'non-digit' => 'abc', 'income account id' => $incActive] as $label => $b) { [$c] = $get($path . '?bank=' . urlencode((string) $b) . '&' . $bq); if ($c !== 400) { $bad[] = "$label=$c"; } }
        ok("$path: accepts an inactive bank with history; refuses foreign / deleted / no-history / non-digit / non-bank with 400", $c1 === 200 && !$bad, "ok=$c1 bad: " . implode(',', $bad));
    }
    $rq = 'from=2026-10-01&to=2026-10-31&balance=0';
    foreach (['bankreconcilliations/bankrecon', 'bankreconcilliations/cashflow_report'] as $path) {
        [$c1] = $get($path . '?bank=' . $bankH . '&' . $rq); $bad = [];
        foreach (['foreign bank' => $bankF, 'deleted bank' => $bankD, 'non-digit' => "abc'\"<script>"] as $label => $b) { [$c, $body] = $get($path . '?bank=' . urlencode((string) $b) . '&' . $rq); if ($c !== 400) { $bad[] = "$label=$c"; } }
        ok("$path: accepts an inactive bank with history; refuses foreign / deleted / non-digit with 400", $c1 === 200 && !$bad, "ok=$c1 bad: " . implode(',', $bad));
    }
    foreach (['bankreconcilliations/unclearedreport', 'bankreconcilliations/clearedreport'] as $path) {
        [$c1] = $get($path . '?type=deposit&bank=' . $bankH . '&sdate=2026-10-01&edate=2026-10-31'); [$c2] = $get($path . '?type=deposit&bank=' . $bankF . '&sdate=2026-10-01&edate=2026-10-31'); [$c3] = $get($path . '?type=deposit&bank=abc&sdate=2026-10-01&edate=2026-10-31');
        ok("$path: valid bank 200, foreign / non-digit 400", $c1 === 200 && $c2 === 400 && $c3 === 400, "$c1/$c2/$c3");
    }
    $before = (int) q('SELECT COUNT(*) n FROM tblbankbalances')[0]['n'];
    [$c1] = http('POST', 'bankbalances/create', $admin11, ['date' => '2026-10-20', 'bank' => $bankF, 'amount' => '5']);
    [$c2] = http('POST', 'bankbalances/create', $admin11, ['date' => '2026-10-20', 'bank' => 'abc', 'amount' => '5']);
    [$c3] = http('POST', 'bankbalances/update', $admin11, ['id' => '1', 'date' => '2026-10-20', 'bank' => $bankD, 'amount' => '5']);
    ok('bank balance create / update refuse a forged bank with 400 and write nothing', $c1 === 400 && $c2 === 400 && $c3 === 400 && (int) q('SELECT COUNT(*) n FROM tblbankbalances')[0]['n'] === $before, "$c1/$c2/$c3");
    [$c4] = http('POST', 'bankbalances/create', $admin2, ['date' => '2026-10-20', 'bank' => $bankA, 'amount' => '5']);
    ok('a session of another congregation cannot use congregation 11\'s bank (400)', $c4 === 400, "status $c4");

    // ------------------------------------------------------------ payment by invoice number (decision 134)
    echo "== payment by invoice number\n";
    [$code, $body] = http('GET', 'invoicereports/fetchselectoptions?type=invoiceno', $admin11);
    $opt = options(json_decode($body, true) ?? '');
    ok('invoice selector: one option per invoice HEADER ID, label "supplier — invoice number"', $code === 200 && isset($opt[$hA], $opt[$hB]) && $opt[$hA][0] === 'ZZ T91H <B>SUPPLIER</B> A — ZZT91-INV' && $opt[$hB][0] === 'ZZ T91H SUPPLIER B — ZZT91-INV');
    ok('invoice selector: two suppliers sharing a number give two different values', $hA !== $hB && count(array_filter(array_keys($opt), function ($k) use ($hA, $hB) { return in_array((int) $k, [$hA, $hB], true); })) === 2);
    ok('invoice selector: foreign, deleted and opening-balance invoices not offered', !isset($opt[$hF]) && !isset($opt[$hDel]) && !isset($opt[$hOpen]));
    ok('invoice selector: supplier name is HTML-escaped in the markup', strpos(json_decode($body, true), '&lt;B&gt;SUPPLIER&lt;/B&gt;') !== false && strpos(json_decode($body, true), '<B>SUPPLIER</B>') === false);
    [$code, $body] = http('GET', 'invoicereports/getinvoicereport?type=byinvoice&criteria=' . $hA, $admin11);
    $r = json_decode($body, true);
    ok('report by header ID: only that supplier\'s payment, with the supplier and invoice number', $code === 200 && count($r['results']) === 1 && $r['results'][0]['supplierName'] === 'ZZ T91H <B>SUPPLIER</B> A' && $r['results'][0]['invoiceNo'] === 'ZZT91-INV' && $r['results'][0]['amount'] === '111.00' && $r['results'][0]['paymentReference'] === 'ZZ A REF', $body);
    [$code, $body] = http('GET', 'invoicereports/getinvoicereport?type=byinvoice&criteria=' . $hB, $admin11);
    $r = json_decode($body, true);
    ok('report by header ID: the other supplier with the same number gets its own payment only', $code === 200 && count($r['results']) === 1 && $r['results'][0]['amount'] === '222.00' && $r['results'][0]['supplierName'] === 'ZZ T91H SUPPLIER B');
    foreach (['foreign invoice' => $hF, 'deleted invoice' => $hDel, 'opening-balance row' => $hOpen, 'non-digit' => 'abc', 'SQL text' => '1 OR 1=1', 'the old contract (invoice number)' => 'ZZT91-INV', 'zero' => '0'] as $label => $crit) {
        [$code, $body] = http('GET', 'invoicereports/getinvoicereport?type=byinvoice&criteria=' . urlencode((string) $crit), $admin11);
        ok("report refuses $label with 400", $code === 400, "status $code $body");
    }
    [$code] = http('GET', 'invoicereports/getinvoicereport?type=byinvoice&criteria=' . $hA, $admin2);
    ok('a session of another congregation cannot read congregation 11\'s invoice (400)', $code === 400, "status $code");
    [$code, $body] = http('GET', 'invoicereports', $admin11);
    ok('the page loads the v2 script, which exists and has the report-type fix and the supplier column', $code === 200 && strpos($body, 'invoicereports-v2.js') !== false && strpos((string) file_get_contents('E:/xampp/htdocs/cms/public/dist/js/pages/reports/invoices/invoicereports-v2.js'), "type === 'balances' || type === 'supplierbalances'") !== false && strpos((string) file_get_contents('E:/xampp/htdocs/cms/public/dist/js/pages/reports/invoices/table.js'), '<th>Supplier</th>') !== false);
    [$code, $body] = http('GET', 'invoicereports/fetchselectoptions?type=supplier', $admin11);
    ok('supplier selector escapes names too', strpos(json_decode($body, true), '&lt;B&gt;') !== false);

    ok('rendered pages and endpoints emitted no PHP warnings', $phpErrors === [], implode(', ', $phpErrors));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    foreach ($created['payments'] as $id) { $pdo->prepare('DELETE FROM tblinvoice_payments_suppliers WHERE ID = ?')->execute([$id]); }
    foreach ($created['headers'] as $id) { $pdo->prepare('DELETE FROM tblinvoice_header_suppliers WHERE ID = ?')->execute([$id]); }
    foreach ($created['suppliers'] as $id) { $pdo->prepare('DELETE FROM tblsuppliers WHERE ID = ?')->execute([$id]); }
    foreach ($created['entries'] as $id) { $pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$id]); $pdo->prepare('DELETE FROM journal_entries WHERE ID = ?')->execute([$id]); }
    foreach (array_reverse($created['accounts']) as $id) { $pdo->prepare('DELETE FROM tblaccounttypes WHERE ID = ?')->execute([$id]); }
    foreach ($sessions as $sid) { $f = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid; if (is_file($f)) { @unlink($f); } }
    $left = (int) q("SELECT COUNT(*) n FROM tblaccounttypes WHERE accountType LIKE 'zz t91h%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM journal_entries WHERE reference LIKE 'zz t91h%'")[0]['n'] + (int) q("SELECT COUNT(*) n FROM tblsuppliers WHERE supplierName LIKE 'zz t91h%'")[0]['n'] + (int) q('SELECT COUNT(*) n FROM tblinvoice_payments_suppliers WHERE paymentNo BETWEEN 990001 AND 990005')[0]['n'];
    if ($left) { $fail++; echo "FAIL cleanup left $left rows\n"; } else { $pass++; echo "PASS cleanup: no fixture row remains\n"; }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

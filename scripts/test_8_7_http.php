<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
// 8.7 HTTP-level run: the real Supplierinvoices, Payments and Suppliers controllers through Apache with real PHP sessions
// (rights, ownership, rendered forms, every AJAX URL the supplier / invoice / payment JavaScript calls, real JSON and form POSTs).
// Sessions are created for existing dev users (no password) and removed at the end; the test adds its own tbluserrights rows and
// removes every row it created. Congregation 11 (+ 2 for the forged IDs), dev only.
//   E:\xampp\php\php.exe scripts\test_8_7_http.php        (Apache running; needs the ignored scripts/dev_session_helper.php)
chdir(__DIR__ . '/../app');
require 'boostrap.php';
$pdo = (new Database)->pdo();
$base = URLROOT;
$tables = ['tblsuppliers' => 'ID', 'tblinvoice_header_suppliers' => 'ID', 'tblinvoice_details_suppliers' => 'ID', 'tblinvoice_payments_suppliers' => 'ID', 'tblproducts' => 'ID',
           'journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tbllogs' => 'ID', 'tbluserrights' => 'ID', 'tblaccounttypes' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT COALESCE(MAX($k),0) FROM $t")->fetchColumn(); }
$seqs = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC);

$pass = 0; $fail = 0;
function chk($n, $v, $x = '') { global $pass, $fail; if ($v) { $pass++; echo "PASS $n\n"; } else { $fail++; echo "FAIL $n $x\n"; } }
function qh($s, $p = []) { global $pdo; $x = $pdo->prepare($s); $x->execute($p); return $x->fetchAll(PDO::FETCH_ASSOC); }
function q($s, $p = []) { return qh($s, $p); }
function one($s, $p = []) { $r = qh($s, $p); return $r ? $r[0] : null; }
function cnt($s, $p = []) { $r = one($s, $p); return $r ? (int)array_values($r)[0] : 0; }
function jo($b) { $j = json_decode($b, true); return json_last_error() === JSON_ERROR_NONE ? $j : null; }
$helper = __DIR__ . '/dev_session_helper.php';
if (!is_file($helper)) { exit("scripts/dev_session_helper.php is missing\n"); }
require_once $helper;

function http87($method, $path, $sid = '', $body = null, $json = false, $rawBody = null) {
    global $base, $phpErrors;
    $ch = curl_init($base . '/' . $path);
    $h = [];
    if ($json) { $h[] = 'Content-Type: application/json'; }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_HTTPHEADER => $h, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody !== null ? $rawBody : ($json ? json_encode($body) : http_build_query($body ?? [])));
    }
    $raw = curl_exec($ch);
    if (preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', (string)$raw, $m)) { $phpErrors[] = $path . ' -> ' . $m[2]; }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = [];
    foreach (explode("\r\n", substr((string)$raw, 0, $hs)) as $l) { if (strpos($l, ':') !== false) { [$k, $v] = explode(':', $l, 2); $headers[strtolower(trim($k))] = trim($v); } }
    return [$code, $headers, substr((string)$raw, $hs)];
}
function redir($r, $s) { return in_array($r[0], [301, 302, 303], true) && strpos($r[1]['location'] ?? '', $s) !== false; }
function opts($html, $name) {
    $d = new DOMDocument; @$d->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $o = [];
    foreach ((new DOMXPath($d))->query("//select[@name='$name']//option") as $n) { $o[$n->getAttribute('value')] = [$n->hasAttribute('selected'), trim($n->textContent)]; }
    return $o;
}
function jsfile($url) { return (string)@file_get_contents($url); }

$cong2User = (int)$pdo->query('SELECT ID FROM tblusers WHERE CongregationId=2 AND UsertypeId=4 ORDER BY ID LIMIT 1')->fetchColumn();
$parish = (int)$pdo->query('SELECT ID FROM tblusers WHERE UsertypeId=3 AND CongregationId=1 ORDER BY ID LIMIT 1')->fetchColumn();
$ins = $pdo->prepare('INSERT IGNORE INTO tbluserrights(UserId,FormId,access) VALUES(?,?,1)');
foreach ([95, 98, $cong2User] as $u) { foreach ([5, 22, 24] as $f) { $ins->execute([$u, $f]); } }
foreach ([50, 66, 68] as $f) { $ins->execute([$parish, $f]); }
$uRight = mkSession(95);              // type 3, congregation 11
$uType4 = mkSession(98);              // type 4, congregation 11
$uNone = mkSession(101);              // no rights
$uAdmin = mkSession(64, 11);          // super admin acting in 11
$uOther = mkSession($cong2User);      // congregation 2
$uParish = mkSession($parish);
$today = date('Y-m-d');
$closedDate = '2024-05-01';
$f15 = Ledger::accountsPayableId($pdo);

// fixtures
function acct($name, $parent, $posting = 1, $active = 1, $cong = 11, $type = 4) {
    global $pdo;
    $pdo->prepare('INSERT INTO tblaccounttypes(accountType,accountTypeId,parentId,isSubCategory,isBank,active,congregationId,deleted) VALUES(?,?,?,?,0,?,?,0)')->execute([$name, $type, $parent, $posting, $active, $cong]);
    return (int)$pdo->lastInsertId();
}
$payA = acct('zz http payable', 23); $payRep = acct('zz http payable parent', 23, 0); $payInactive = acct('zz http payable inactive', 23, 1, 0); $payForeign = acct('zz http payable foreign', 23, 1, 1, 2);
$expInactive = acct('zz http exp inactive', 26, 1, 0, 11, 2);
function prod($name, $account, $cong = 11, $deleted = 0) { global $pdo; $pdo->prepare('INSERT INTO tblproducts(productName,rate,accountId,congregationId,deleted) VALUES(?,?,?,?,?)')->execute([$name, '100.00', $account, $cong, $deleted]); return (int)$pdo->lastInsertId(); }
$pOk = prod('zz http product', 51); $pAsset = prod('zz http asset product', 167); $pBad = prod('zz http income product', 90); $pBadInactive = prod('zz http inactive product', $expInactive); $pForeign = prod('zz http foreign product', 51, 2);
$pXss = prod('zz <b>bold</b> product', 51);
$pdo->exec("INSERT INTO tblsuppliers(supplierName,email,pin,congregationId) VALUES('zz http supplier','zz@example.com','p123',11),('zz <script>alert(1)</script> supplier',NULL,NULL,11)");
$sup = (int)$pdo->lastInsertId(); $supXss = $sup + 1;
$pdo->exec("INSERT INTO tblsuppliers(supplierName,congregationId) VALUES('zz http foreign supplier',2)"); $supForeign = (int)$pdo->lastInsertId();
$no = 910000;
function invoicePayload($o = []) {
    global $sup, $pOk, $payA, $today, $no;
    return ['header' => array_merge(['supplier' => $sup, 'invoiceDate' => $today, 'dueDate' => $today, 'vatType' => '1', 'vat' => '', 'invoiceNo' => (string)(++$no), 'payable' => $payA, 'id' => '', 'isEdit' => ''], $o['header'] ?? []),
            'table' => $o['table'] ?? [['pid' => (string)$pOk, 'qty' => '2', 'rate' => '50.00']]];
}

try {
    // ------------------------------------------------------------ pages, rights, no session
    $pages = ['suppliers', 'suppliers/add', 'supplierinvoices', 'supplierinvoices/add', 'payments', 'payments/add'];
    foreach ($pages as $p) {
        foreach (['admin' => $uAdmin, 'right' => $uRight, 'type4' => $uType4, 'parish' => $uParish] as $l => $sid) {
            $r = http87('GET', $p, $sid);
            chk("$p opens for $l", $r[0] === 200 && stripos($r[2], '<html') !== false, "status {$r[0]}");
        }
        $r = http87('GET', $p, $uNone);
        chk("$p denied without the right", redir($r, 'deniedaccess'), "status {$r[0]}");
        $r = http87('GET', $p, '');
        chk("$p without a session goes to login", redir($r, 'users'), "status {$r[0]}");
    }
    $r = http87('GET', 'supplierinvoices/fetchsupplierdetails?sid=' . $sup, $uNone);
    chk('AJAX denied without the right (fetchsupplierdetails)', redir($r, 'deniedaccess'));

    // ------------------------------------------------------------ rendered supplier invoice form
    $add = http87('GET', 'supplierinvoices/add', $uRight)[2];
    $payOpts = opts($add, 'payable');
    $selected = array_keys(array_filter($payOpts, function($o) { return $o[0]; }));
    chk('invoice form: "Crediting account" dropdown preselects accounts payable (the F15 account, found at runtime)', array_map('strval', $selected) === [(string)$f15] && stripos($payOpts[(string)$f15][1], 'accounts payable') !== false, json_encode($selected));
    chk('invoice form: crediting list = posting accounts under 23 (own account in, reporting / inactive / foreign / 23 / banks out)', isset($payOpts[(string)$payA]) && !isset($payOpts[(string)$payRep]) && !isset($payOpts[(string)$payInactive]) && !isset($payOpts[(string)$payForeign]) && !isset($payOpts['23']) && !isset($payOpts['105']));
    chk('invoice form: the list equals Ledger::listPayableAccounts', array_keys(array_filter($payOpts, function($v, $k) { return $k !== ''; }, ARRAY_FILTER_USE_BOTH)) == array_map('strval', array_column(Ledger::listPayableAccounts($pdo, 11), 'ID')) || count(array_diff(array_keys($payOpts), [''], array_map('strval', array_column(Ledger::listPayableAccounts($pdo, 11), 'ID')))) === 0);
    $prodOpts = opts($add, 'product');
    chk('invoice form: valid products listed; products 1 and 2, bad-account, foreign products absent', isset($prodOpts[(string)$pOk]) && isset($prodOpts[(string)$pAsset]) && !isset($prodOpts['1']) && !isset($prodOpts['2']) && !isset($prodOpts[(string)$pBad]) && !isset($prodOpts[(string)$pBadInactive]) && !isset($prodOpts[(string)$pForeign]));
    $accOpts = opts($add, 'account');
    chk('invoice form: the product modal lists only expense / non-bank asset posting accounts', isset($accOpts['51']) && isset($accOpts['167']) && !isset($accOpts['90']) && !isset($accOpts['26']) && !isset($accOpts['105']) && !isset($accOpts[(string)$expInactive]));
    chk('invoice form: the modal posts to supplierinvoices/saveproduct (not invoices/newproduct)', strpos($add, 'supplierinvoices/saveproduct') !== false && strpos($add, 'invoices/newproduct') === false);
    chk('invoice form: line table has an Account column and loads supplier-v2.js', strpos($add, '<th width="20%">Account</th>') !== false && strpos($add, 'pages/invoices/supplier-v2.js') !== false);
    chk('invoice form: the supplier names are escaped (no raw script tag)', stripos($add, '<script>alert(1)</script>') === false && stripos($add, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && stripos($add, '<b>bold</b>') === false);
    foreach (['invoices/supplier-v2.js', 'invoices/functionalities-v2.js', 'invoices/ajax-requests-v2.js', 'utils/feedback.js', 'suppliers/add-v2.js', 'suppliers/payment-v2.js', 'suppliers/values-v2.js'] as $js) {
        $src = jsfile($base . '/dist/js/pages/' . $js);
        chk("JS $js is served and does not use nextSibling.nextSibling", strlen($src) > 200 && strpos($src, 'nextSibling.nextSibling') === false);
    }
    chk('the shared utils.js is untouched (git diff is empty)', trim((string)shell_exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' diff --stat -- public/dist/js/pages/utils/utils.js')) === '');

    // ------------------------------------------------------------ AJAX: supplier details, product
    $r = http87('GET', 'supplierinvoices/fetchsupplierdetails?sid=' . $sup, $uRight);
    $j = jo($r[2]);
    chk('fetchsupplierdetails returns email and PIN of an own supplier', $r[0] === 200 && $j['email'] === 'zz@example.com' && $j['pin'] === 'P123');
    $r = http87('GET', 'supplierinvoices/fetchsupplierdetails?sid=' . $supForeign, $uRight);
    chk('fetchsupplierdetails of another congregation\'s supplier is a 404 JSON', $r[0] === 404 && jo($r[2]) !== null);
    $r = http87('GET', 'supplierinvoices/fetchsupplierdetails', $uRight);
    chk('fetchsupplierdetails without an ID is a 400 JSON', $r[0] === 400 && jo($r[2]) !== null);
    $r = http87('POST', 'supplierinvoices/fetchsupplierdetails', $uRight, []);
    chk('fetchsupplierdetails by POST is a 405', $r[0] === 405);
    $r = http87('GET', 'supplierinvoices/getproduct?pid=' . $pOk, $uRight);
    $j = jo($r[2]);
    chk('getproduct returns rate and the debit account of an own product', $r[0] === 200 && (float)$j['rate'] === 100.0 && (int)$j['accountId'] === 51 && $j['account'] === strtoupper(one('SELECT accountType FROM tblaccounttypes WHERE ID=51')['accountType']));
    foreach ([['foreign product', $pForeign, 404], ['retired product 1', 1, 404], ['retired product 2', 2, 404], ['product with an income account', $pBad, 422], ['product with an inactive account', $pBadInactive, 422], ['junk', 'x', 404], ['empty', '', 400]] as [$label, $pid, $code]) {
        $r = http87('GET', 'supplierinvoices/getproduct?pid=' . urlencode((string)$pid), $uRight);
        chk("getproduct: $label -> $code JSON", $r[0] === $code && jo($r[2]) !== null, "status {$r[0]}");
    }
    $r = http87('POST', 'supplierinvoices/getproduct', $uRight, []);
    chk('getproduct by POST is a 405', $r[0] === 405);
    $r = http87('GET', 'products/getrate?pid=' . $pForeign, $uAdmin);
    chk('products/getrate of another congregation\'s product returns nothing useful', $r[0] === 200 && (jo($r[2]) === false || jo($r[2]) === null || trim($r[2]) === 'false'));

    $r = http87('POST', 'supplierinvoices/saveproduct', $uRight, ['productName' => 'zz <i>ajax</i> product', 'description' => 'd', 'rate' => '75.50', 'account' => '51'], true);
    $j = jo($r[2]); $newPid = (int)($j['productid'] ?? 0);
    chk('saveproduct creates the product and returns ITS id and escaped options', $r[0] === 200 && $newPid > 0 && one('SELECT productName FROM tblproducts WHERE ID=?', [$newPid])['productName'] === 'zz <i>ajax</i> product' && strpos($j['products'], 'value="' . $newPid . '"') !== false && stripos($j['products'], '<i>ajax</i>') === false && stripos($j['products'], '&lt;i&gt;ajax&lt;/i&gt;') !== false);
    foreach ([['income account', ['account' => '90']], ['bank account', ['account' => '105']], ['reporting account', ['account' => '26']], ['missing rate', ['rate' => '']], ['duplicate name', ['productName' => 'zz http product']]] as [$label, $over]) {
        $c = cnt('SELECT COUNT(*) FROM tblproducts');
        $r = http87('POST', 'supplierinvoices/saveproduct', $uRight, array_merge(['productName' => 'zz bad ' . $label, 'description' => '', 'rate' => '5', 'account' => '51'], $over), true);
        chk("saveproduct refuses $label (422 JSON, nothing saved)", $r[0] === 422 && jo($r[2]) !== null && cnt('SELECT COUNT(*) FROM tblproducts') === $c, "status {$r[0]}");
    }
    $r = http87('GET', 'supplierinvoices/saveproduct', $uRight);
    chk('saveproduct by GET is a 405', $r[0] === 405);
    $r = http87('POST', 'supplierinvoices/saveproduct', $uRight, null, false, 'not json');
    chk('saveproduct with a body that is not JSON is a 400', $r[0] === 400);

    // ------------------------------------------------------------ AJAX: create / edit / delete an invoice
    $r = http87('POST', 'supplierinvoices/createupdate', $uRight, invoicePayload(['header' => ['vatType' => '3', 'vat' => '16'], 'table' => [['pid' => (string)$pOk, 'qty' => '2', 'rate' => '50.00']]]), true);
    $j = jo($r[2]); $inv = (int)($j['id'] ?? 0);
    chk('real JSON create: 200, the invoice and its type 6 entry exist (Dr product account 116.00 / Cr crediting account)', $r[0] === 200 && $inv > 0 && one("SELECT GROUP_CONCAT(CONCAT(l.accountId,'|',l.dc,'|',l.amount) ORDER BY l.lineNumber) g FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=6 AND e.transactionId=? AND e.deleted=0", [$inv])['g'] === "51|DEBIT|116.00,$payA|CREDIT|116.00", $r[2]);
    chk('create stores the crediting account and a congregation-11 owner row', (int)one('SELECT PayableAccountId FROM tblinvoice_header_suppliers WHERE ID=?', [$inv])['PayableAccountId'] === $payA && (int)one('SELECT congregationId FROM tblinvoice_header_suppliers WHERE ID=?', [$inv])['congregationId'] === 11);
    $entriesBefore = cnt('SELECT COUNT(*) FROM journal_entries WHERE ID>?', [$max['journal_entries']]);
    foreach ([['crediting account missing', ['header' => ['payable' => '']]], ['crediting account = reporting', ['header' => ['payable' => (string)$payRep]]], ['future date', ['header' => ['invoiceDate' => date('Y-m-d', strtotime('+1 day'))]]],
              ['closed-year date', ['header' => ['invoiceDate' => $closedDate]]], ['forged supplier', ['header' => ['supplier' => (string)$supForeign]]], ['forged product', ['table' => [['pid' => (string)$pForeign, 'qty' => '1', 'rate' => '1']]]],
              ['retired product', ['table' => [['pid' => '1', 'qty' => '1', 'rate' => '1']]]], ['bad quantity', ['table' => [['pid' => (string)$pOk, 'qty' => '-1', 'rate' => '1']]]], ['no lines', ['table' => []]]] as [$label, $over]) {
        $r = http87('POST', 'supplierinvoices/createupdate', $uRight, invoicePayload($over), true);
        $j = jo($r[2]);
        chk("real JSON create refused: $label (422 JSON with a message)", $r[0] === 422 && isset($j['message']) && $j['message'] !== '', "status {$r[0]} " . $r[2]);
    }
    chk('refused creates left no entry behind', cnt('SELECT COUNT(*) FROM journal_entries WHERE ID>?', [$max['journal_entries']]) === $entriesBefore);
    $r = http87('POST', 'supplierinvoices/createupdate', $uRight, invoicePayload(['header' => ['invoiceNo' => 'INV-0042/A']]), true);
    chk('real JSON create accepts a text invoice number', $r[0] === 200 && one('SELECT invoiceNo FROM tblinvoice_header_suppliers WHERE ID=?', [(int)jo($r[2])['id']])['invoiceNo'] === 'INV-0042/A', $r[2]);
    $r = http87('POST', 'supplierinvoices/createupdate', $uRight, invoicePayload(['header' => ['invoiceNo' => 'inv-0042/a']]), true);
    chk('real JSON create: the same number for the same supplier is a 422', $r[0] === 422 && stripos($r[2], 'already exists') !== false);
    $r = http87('POST', 'supplierinvoices/createupdate', $uRight, invoicePayload(['header' => ['invoiceNo' => 'bad<number']]), true);
    chk('real JSON create: an invalid number is a 422 with an escaped message', $r[0] === 422 && strpos($r[2], 'bad<number') === false);
    $r = http87('POST', 'supplierinvoices/createupdate', $uNone, invoicePayload(), true);
    chk('create denied without the right', redir($r, 'deniedaccess'));
    $r = http87('GET', 'supplierinvoices/createupdate', $uRight);
    chk('createupdate by GET is a 405', $r[0] === 405);
    $r = http87('POST', 'supplierinvoices/createupdate', $uRight, null, false, '{"nope":1}');
    chk('createupdate without a header is a 400', $r[0] === 400);
    // a product name with markup must not reach the page as markup in an error message
    $r = http87('POST', 'supplierinvoices/createupdate', $uRight, invoicePayload(['table' => [['pid' => (string)$pBadInactive, 'qty' => '1', 'rate' => '1']]]), true);
    $j = jo($r[2]);
    chk('error messages are HTML-escaped (the page inserts them as HTML)', $r[0] === 422 && strpos($j['message'], '<') === false);

    $enc = encryptId($inv);
    $r = http87('GET', 'supplierinvoices/edit/' . $enc, $uRight);
    $eo = opts($r[2], 'payable');
    chk('edit form: 200, the stored crediting account is the selected one, the lines show their account', $r[0] === 200 && isset($eo[(string)$payA]) && $eo[(string)$payA][0] && strpos($r[2], 'class="account"') !== false && strpos($r[2], 'value="' . $inv . '"') !== false);
    $r = http87('GET', 'supplierinvoices/edit/' . encryptId(999999), $uRight);
    chk('edit form of an unknown invoice redirects to the list', redir($r, 'supplierinvoices'));
    $pdo->prepare("INSERT INTO tblinvoice_header_suppliers(invoiceDate,duedate,supplierId,invoiceNo,fiscalYearId,vattype,PayableAccountId,exclusiveVat,vat,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,?,25,1,?,10,0,10,64,2)")->execute([$today, $today, $supForeign, 920001, $f15]);
    $foreignInv = (int)$pdo->lastInsertId();
    $r = http87('GET', 'supplierinvoices/edit/' . encryptId($foreignInv), $uRight);
    chk('edit form of ANOTHER congregation\'s invoice (forged ID) redirects to the list and shows nothing', redir($r, 'supplierinvoices') && strpos($r[2], '920001') === false);
    $r = http87('GET', 'supplierinvoices/print/' . encryptId($foreignInv), $uRight);
    chk('print of another congregation\'s invoice (forged ID) redirects', redir($r, 'supplierinvoices'));
    $r = http87('GET', 'supplierinvoices/print/' . $enc, $uRight);
    chk('print of an own invoice renders (escaped, no PHP warning)', $r[0] === 200 && stripos($r[2], 'Invoice #') !== false);

    $edit = invoicePayload(['header' => ['id' => (string)$inv, 'isEdit' => '1', 'invoiceNo' => (string)one('SELECT invoiceNo FROM tblinvoice_header_suppliers WHERE ID=?', [$inv])['invoiceNo'], 'payable' => (string)$f15], 'table' => [['pid' => (string)$pOk, 'qty' => '3', 'rate' => '10.00']]]);
    $entryId = one('SELECT ID FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$inv])['ID'];
    $r = http87('POST', 'supplierinvoices/createupdate', $uRight, $edit, true);
    chk('real JSON edit: updated in place (same entry), new payable and amount', $r[0] === 200 && one('SELECT ID FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$inv])['ID'] === $entryId && (int)one('SELECT PayableAccountId FROM tblinvoice_header_suppliers WHERE ID=?', [$inv])['PayableAccountId'] === $f15 && one('SELECT inclusiveVat FROM tblinvoice_header_suppliers WHERE ID=?', [$inv])['inclusiveVat'] === '30.00', $r[2]);
    $forgedEdit = invoicePayload(['header' => ['id' => (string)$foreignInv, 'isEdit' => '1', 'invoiceNo' => '920001']]);
    $r = http87('POST', 'supplierinvoices/createupdate', $uRight, $forgedEdit, true);
    chk('real JSON edit of another congregation\'s invoice (forged ID) is refused and changes nothing', $r[0] === 422 && one('SELECT inclusiveVat FROM tblinvoice_header_suppliers WHERE ID=?', [$foreignInv])['inclusiveVat'] === '10.00');

    // pay it, then the paid-invoice rules over HTTP
    $payForm = http87('GET', 'payments/add', $uRight)[2];
    $methods = opts($payForm, 'paymethod'); $cashOpts = opts($payForm, 'cashaccount'); $bankOpts = opts($payForm, 'bank');
    chk('payment form: methods cash / cheque / bank only', array_keys($methods) == ['1', '3', '4']);
    chk('payment form: the cash list is the PAY_CASH list and the bank list is the PAY_BANK list (they differ)', isset($cashOpts['34']) && !isset($cashOpts['105']) && isset($bankOpts['105']) && !isset($bankOpts['34']) && !isset($bankOpts['101']));
    chk('payment form: the pending invoice shows its crediting account, the checkbox is enabled', strpos($payForm, 'value="' . $inv . '"') !== false && strpos($payForm, 'suppliers/payment-v2.js') !== false);
    chk('payment form: a legacy invoice without a crediting account is listed but its checkbox is disabled', preg_match('~class="chkbx" id="\d+" disabled~', $payForm) === 1 || cnt('SELECT COUNT(*) FROM vw_supplier_with_bals v JOIN tblinvoice_header_suppliers h ON h.ID=v.ID WHERE v.congregationId=11 AND h.PayableAccountId IS NULL') === 0);
    chk('payment form: the supplier names are escaped', stripos($payForm, '<script>alert(1)</script>') === false);
    $hdr = ['paydate' => $today, 'paymethod' => '1', 'cashaccount' => '34', 'bank' => ''];
    $seqBefore = cnt("SELECT COALESCE(MAX(lastNo),0) FROM receipt_number_seq WHERE congregationId=11 AND kind='suppay'");
    $r = http87('POST', 'payments/create', $uRight, ['header' => $hdr, 'payments' => [['invoiceid' => (string)$inv, 'payment' => '10.00', 'cheque' => '']]], true);
    $j = jo($r[2]); $payRow = (int)one('SELECT MAX(ID) m FROM tblinvoice_payments_suppliers WHERE invoice_Id=?', [$inv])['m'];
    chk('real JSON payment (cash): 200, row, entry Dr the invoice\'s stored payable / Cr 34, no bank row', $r[0] === 200 && !empty($j['success']) && one("SELECT GROUP_CONCAT(CONCAT(l.accountId,'|',l.dc,'|',l.amount) ORDER BY l.lineNumber) g FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=7 AND e.transactionId=? AND e.deleted=0", [$payRow])['g'] === "$f15|DEBIT|10.00,34|CREDIT|10.00" && cnt('SELECT COUNT(*) FROM tblbankpostings WHERE transactionType=7 AND transactionId=?', [$payRow]) === 0, $r[2]);
    $r = http87('POST', 'payments/create', $uRight, ['header' => ['paydate' => $today, 'paymethod' => '3', 'bank' => '105', 'cashaccount' => ''], 'payments' => [['invoiceid' => (string)$inv, 'payment' => '5.00', 'cheque' => 'HTTP-CHQ']]], true);
    $payRow2 = (int)one('SELECT MAX(ID) m FROM tblinvoice_payments_suppliers WHERE invoice_Id=?', [$inv])['m'];
    chk('real JSON payment (cheque): one withdrawal bank row on the chosen bank', $r[0] === 200 && cnt('SELECT COUNT(*) FROM tblbankpostings WHERE transactionType=7 AND transactionId=? AND bankId=105 AND deleted=0', [$payRow2]) === 1, $r[2]);
    chk('payment numbers come from the locked sequence (two batches -> +2)', cnt("SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind='suppay'") === $seqBefore + 2);
    $dbBefore = [cnt('SELECT COUNT(*) FROM tblinvoice_payments_suppliers'), cnt('SELECT COUNT(*) FROM journal_entries WHERE transactionTypeId=7 AND ID>?', [$max['journal_entries']])];
    foreach ([['overpayment', ['payments' => [['invoiceid' => (string)$inv, 'payment' => '15.01', 'cheque' => '']]]], ['forged invoice of another congregation', ['payments' => [['invoiceid' => (string)$foreignInv, 'payment' => '1', 'cheque' => '']]]],
              ['bank used for cash', ['header' => ['paydate' => $today, 'paymethod' => '1', 'cashaccount' => '105', 'bank' => '']]], ['future date', ['header' => ['paydate' => date('Y-m-d', strtotime('+1 day')), 'paymethod' => '1', 'cashaccount' => '34', 'bank' => '']]],
              ['method not offered', ['header' => ['paydate' => $today, 'paymethod' => '2', 'cashaccount' => '34', 'bank' => '']]]] as [$label, $over]) {
        $body = ['header' => $over['header'] ?? $hdr, 'payments' => $over['payments'] ?? [['invoiceid' => (string)$inv, 'payment' => '1.00', 'cheque' => '']]];
        $r = http87('POST', 'payments/create', $uRight, $body, true);
        $j = jo($r[2]);
        chk("payment refused: $label (422 JSON with a message)", $r[0] === 422 && !empty($j['message']), "status {$r[0]} " . $r[2]);
    }
    chk('refused payments wrote nothing', [cnt('SELECT COUNT(*) FROM tblinvoice_payments_suppliers'), cnt('SELECT COUNT(*) FROM journal_entries WHERE transactionTypeId=7 AND ID>?', [$max['journal_entries']])] === $dbBefore);
    $r = http87('GET', 'payments/create', $uRight);
    chk('payments/create by GET is a 405', $r[0] === 405);
    $r = http87('POST', 'payments/create', $uNone, ['header' => $hdr, 'payments' => []], true);
    chk('payments/create denied without the right', redir($r, 'deniedaccess'));
    $r = http87('POST', 'payments/create', $uRight, null, false, 'x');
    chk('payments/create with a body that is not JSON is a 400', $r[0] === 400);
    $r = http87('GET', 'payments/print/' . $payRow, $uRight);
    chk('voucher print of an own payment renders', $r[0] === 200 && stripos($r[2], 'Voucher No') !== false);
    $pdo->prepare('INSERT INTO tblinvoice_payments_suppliers(paymentNo,paymentNo2,invoice_Id,paymentDate,amount,paymentId,PaymentAccountId) VALUES(77,77,?,?,1,1,34)')->execute([$foreignInv, $today]);
    $foreignPay = (int)$pdo->lastInsertId();
    $r = http87('GET', 'payments/print/' . $foreignPay, $uRight);
    chk('voucher print of another congregation\'s payment (forged ID) redirects and shows nothing', redir($r, 'payments'));
    $r = http87('GET', 'payments', $uRight);
    chk('payments list shows own payments only', strpos($r[2], 'payments/print/' . $payRow) !== false && strpos($r[2], 'payments/print/' . $foreignPay) === false);

    // paid invoice: the form is locked and the server refuses the edit / delete
    $r = http87('GET', 'supplierinvoices/edit/' . $enc, $uAdmin);
    chk('edit form of an invoice with payments shows the lock notice and marks the form locked', $r[0] === 200 && strpos($r[2], 'only the due date and the invoice number can be changed') !== false && strpos($r[2], 'data-locked="1"') !== false);
    $locked = invoicePayload(['header' => ['id' => (string)$inv, 'isEdit' => '1', 'invoiceNo' => (string)one('SELECT invoiceNo FROM tblinvoice_header_suppliers WHERE ID=?', [$inv])['invoiceNo'], 'payable' => (string)$f15], 'table' => [['pid' => (string)$pOk, 'qty' => '4', 'rate' => '10.00']]]);
    $r = http87('POST', 'supplierinvoices/createupdate', $uAdmin, $locked, true);
    chk('real JSON edit of a paid invoice (changed lines) is refused with the reason', $r[0] === 422 && stripos($r[2], 'has payments') !== false);
    $r = http87('POST', 'supplierinvoices/delete', $uRight, ['id' => $inv]);
    chk('real delete of an invoice with payments is refused (flash) and nothing changes', redir($r, 'supplierinvoices') && (int)one('SELECT deleted FROM tblinvoice_header_suppliers WHERE ID=?', [$inv])['deleted'] === 0);

    // roles and the list buttons
    $r = http87('POST', 'supplierinvoices/createupdate', $uRight, invoicePayload(), true); $mine = (int)jo($r[2])['id'];
    $list = http87('GET', 'supplierinvoices', $uRight)[2];
    chk('list: a type 3 user sees Edit for their own unpaid invoice and no Delete button', strpos($list, 'supplierinvoices/edit/' . encryptId($mine)) !== false && strpos($list, 'custom-font btndel') === false);
    $listAdmin = http87('GET', 'supplierinvoices', $uAdmin)[2];
    chk('list: the administrator sees Edit and Delete for it', strpos($listAdmin, 'supplierinvoices/edit/' . encryptId($mine)) !== false && strpos($listAdmin, 'custom-font btndel') !== false);
    $listType4 = http87('GET', 'supplierinvoices', $uType4)[2];
    chk('list: a type 4 user does not get Edit on someone else\'s invoice', strpos($listType4, 'supplierinvoices/edit/' . encryptId($mine)) === false);
    $r = http87('GET', 'supplierinvoices/edit/' . encryptId($mine), $uType4);
    chk('edit page of someone else\'s invoice is refused for a type 4 user', redir($r, 'supplierinvoices'));
    $r = http87('POST', 'supplierinvoices/delete', $uType4, ['id' => $mine]);
    chk('delete by a type 4 user is refused', redir($r, 'supplierinvoices') && (int)one('SELECT deleted FROM tblinvoice_header_suppliers WHERE ID=?', [$mine])['deleted'] === 0);
    $r = http87('POST', 'supplierinvoices/delete', $uOther, ['id' => $mine]);
    chk('delete from another congregation (forged ID) changes nothing', (int)one('SELECT deleted FROM tblinvoice_header_suppliers WHERE ID=?', [$mine])['deleted'] === 0);
    $r = http87('POST', 'supplierinvoices/delete', $uRight, ['id' => $foreignInv]);
    chk('delete of another congregation\'s invoice (forged ID) changes nothing', (int)one('SELECT deleted FROM tblinvoice_header_suppliers WHERE ID=?', [$foreignInv])['deleted'] === 0);
    $r = http87('POST', 'supplierinvoices/delete', $uAdmin, ['id' => $mine]);
    chk('real delete by the administrator voids the entry and soft-deletes the header', redir($r, 'supplierinvoices') && (int)one('SELECT deleted FROM tblinvoice_header_suppliers WHERE ID=?', [$mine])['deleted'] === 1 && cnt('SELECT COUNT(*) FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$mine]) === 0);
    $r = http87('GET', 'supplierinvoices/delete', $uAdmin);
    chk('supplierinvoices/delete by GET is refused', redir($r, 'deniedaccess'));

    // ------------------------------------------------------------ suppliers
    $sf = http87('GET', 'suppliers/add', $uRight)[2];
    $so = opts($sf, 'payable');
    chk('supplier form: crediting account dropdown with accounts payable preselected', array_map('strval', array_keys(array_filter($so, function($o) { return $o[0]; }))) === [(string)$f15] && !isset($so[(string)$payRep]) && !isset($so['23']) && isset($so[(string)$payA]));
    chk('supplier form: opening balance, as-of date and the add-v2.js module', strpos($sf, 'name="openingbal"') !== false && strpos($sf, 'name="asof"') !== false && strpos($sf, 'suppliers/add-v2.js') !== false);
    $r = http87('POST', 'suppliers/createupdate', $uRight, ['id' => '0', 'isedit' => '', 'suppliername' => 'zz http new supplier', 'contact' => '', 'pin' => '', 'address' => '', 'email' => '', 'contactperson' => '', 'openingbal' => '-120.50', 'asof' => $today, 'payable' => (string)$payA], true);
    $j = jo($r[2]); $ns = (int)($j['id'] ?? 0);
    chk('real JSON supplier create with a negative opening balance: Dr payable / Cr 20', $r[0] === 200 && $ns > 0 && one("SELECT GROUP_CONCAT(CONCAT(l.accountId,'|',l.dc,'|',l.amount) ORDER BY l.lineNumber) g FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=15 AND e.transactionId=? AND e.deleted=0", [$ns])['g'] === "$payA|DEBIT|120.50,20|CREDIT|120.50", $r[2]);
    $r = http87('GET', 'suppliers/edit/' . $ns, $uRight);
    $eo = opts($r[2], 'payable');
    chk('supplier edit form: shows the balance, the date and the stored crediting account', $r[0] === 200 && strpos($r[2], 'value="-120.50"') !== false && strpos($r[2], 'value="' . $today . '"') !== false && isset($eo[(string)$payA]) && $eo[(string)$payA][0]);
    $r = http87('POST', 'suppliers/createupdate', $uRight, ['id' => (string)$ns, 'isedit' => '1', 'suppliername' => 'zz http new supplier', 'contact' => '0711111111', 'pin' => '', 'address' => '', 'email' => '', 'contactperson' => '', 'openingbal' => '200', 'asof' => $today, 'payable' => (string)$f15], true);
    chk('real JSON supplier edit changes the opening balance in place (Dr 20 / Cr accounts payable)', $r[0] === 200 && one("SELECT GROUP_CONCAT(CONCAT(l.accountId,'|',l.dc,'|',l.amount) ORDER BY l.lineNumber) g FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=15 AND e.transactionId=? AND e.deleted=0", [$ns])['g'] === "20|DEBIT|200.00,$f15|CREDIT|200.00", $r[2]);
    foreach ([['duplicate name', ['suppliername' => 'zz http supplier']], ['balance without a date', ['asof' => '']], ['balance with reporting account', ['payable' => '23']], ['future date', ['asof' => date('Y-m-d', strtotime('+2 days'))]],
              ['3-decimal balance', ['openingbal' => '1.234']], ['closed-year date', ['asof' => $closedDate]]] as [$label, $over]) {
        $b4 = cnt('SELECT COUNT(*) FROM tblsuppliers');
        $r = http87('POST', 'suppliers/createupdate', $uRight, array_merge(['id' => '0', 'isedit' => '', 'suppliername' => 'zz http bad ' . $label, 'contact' => '', 'pin' => '', 'address' => '', 'email' => '', 'contactperson' => '', 'openingbal' => '10', 'asof' => $today, 'payable' => (string)$payA], $over), true);
        chk("supplier create refused: $label (422 JSON, nothing saved)", $r[0] === 422 && !empty(jo($r[2])['message']) && cnt('SELECT COUNT(*) FROM tblsuppliers') === $b4, "status {$r[0]} " . $r[2]);
    }
    $r = http87('POST', 'suppliers/createupdate', $uRight, ['id' => (string)$supForeign, 'isedit' => '1', 'suppliername' => 'hijacked', 'openingbal' => '', 'asof' => '', 'payable' => ''], true);
    chk('real JSON edit of another congregation\'s supplier (forged ID) is refused', $r[0] === 422 && one('SELECT supplierName FROM tblsuppliers WHERE ID=?', [$supForeign])['supplierName'] === 'zz http foreign supplier');
    $r = http87('GET', 'suppliers/edit/' . $supForeign, $uRight);
    chk('supplier edit form of another congregation (forged ID) redirects', redir($r, 'suppliers'));
    $r = http87('GET', 'suppliers/createupdate', $uRight);
    chk('suppliers/createupdate by GET is a 405', $r[0] === 405);
    $r = http87('POST', 'suppliers/createupdate', $uRight, null, false, 'x');
    chk('suppliers/createupdate with a body that is not JSON is a 400', $r[0] === 400);
    $idx = http87('GET', 'suppliers', $uAdmin)[2];
    chk('supplier list: names escaped, own suppliers only', stripos($idx, '<script>alert(1)</script>') === false && strpos($idx, 'ZZ HTTP NEW SUPPLIER') !== false && strpos($idx, 'ZZ HTTP FOREIGN SUPPLIER') === false);
    $r = http87('POST', 'suppliers/delete', $uRight, ['id' => $supForeign]);
    chk('supplier delete of another congregation (forged ID) changes nothing', (int)one('SELECT deleted FROM tblsuppliers WHERE ID=?', [$supForeign])['deleted'] === 0);
    $r = http87('POST', 'suppliers/delete', $uRight, ['id' => $sup]);
    chk('supplier with an invoice cannot be deleted (flash)', redir($r, 'suppliers') && (int)one('SELECT deleted FROM tblsuppliers WHERE ID=?', [$sup])['deleted'] === 0);
    $r = http87('POST', 'suppliers/delete', $uRight, ['id' => $ns]);
    chk('real supplier delete: type 15 voided, supplier soft-deleted', redir($r, 'suppliers') && (int)one('SELECT deleted FROM tblsuppliers WHERE ID=?', [$ns])['deleted'] === 1 && cnt('SELECT COUNT(*) FROM journal_entries WHERE transactionTypeId=15 AND transactionId=? AND deleted=0', [$ns]) === 0);
    $r = http87('GET', 'suppliers/delete', $uRight);
    chk('suppliers/delete by GET is refused', redir($r, 'deniedaccess'));

    chk('no PHP warnings / notices / fatals in any HTTP response', count($phpErrors) === 0, implode(', ', $phpErrors));
} finally {
    foreach ($sessions as $s) { @unlink(session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $s); }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (array_reverse($tables, true) as $t => $k) { $pdo->exec("DELETE FROM $t WHERE $k>" . $max[$t]); }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec('DELETE FROM receipt_number_seq');
    $insq = $pdo->prepare('INSERT INTO receipt_number_seq(congregationId,kind,fiscalYearId,lastNo) VALUES(?,?,?,?)');
    foreach ($seqs as $s) { $insq->execute([$s['congregationId'], $s['kind'], $s['fiscalYearId'], $s['lastNo']]); }
    $st = $pdo->prepare('UPDATE tblfiscalyears SET closed=?,deleted=? WHERE ID=?');
    foreach ($years as $y) { $st->execute([$y['closed'], $y['deleted'], $y['ID']]); }
}
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k>" . $max[$t])->fetchColumn(); }
chk('cleanup: no row above the starting IDs remains', $left === 0);
echo "RESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);

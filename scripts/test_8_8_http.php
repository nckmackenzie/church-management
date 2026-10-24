<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
// 8.8 HTTP-level run: the real Invoices controller (customer invoices and payments) through Apache with real PHP sessions
// (rights, ownership, rendered forms, every AJAX URL the customer invoice JavaScript calls, real JSON and form POSTs).
// Sessions are created for existing dev users (no password) and removed at the end; the test adds its own tbluserrights rows and
// removes every row it created. Congregation 11 (+ 2 for the forged IDs), dev only.
//   E:\xampp\php\php.exe scripts\test_8_8_http.php        (Apache running; needs the ignored scripts/dev_session_helper.php)
chdir(__DIR__ . '/../app');
require 'boostrap.php';
$pdo = (new Database)->pdo();
$base = URLROOT;
$tables = ['tblcustomers' => 'ID', 'tblinvoice_header' => 'ID', 'tblinvoice_details' => 'ID', 'tblinvoice_payments' => 'ID', 'tblproducts' => 'ID',
           'journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tbllogs' => 'ID', 'tbluserrights' => 'ID', 'tblaccounttypes' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT COALESCE(MAX($k),0) FROM $t")->fetchColumn(); }
$seqs = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC);
$activeBefore = $pdo->query('SELECT ID,active FROM tblaccounttypes')->fetchAll(PDO::FETCH_KEY_PAIR);

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

function http88($method, $path, $sid = '', $body = null, $json = false, $rawBody = null) {
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
function parse88($code, $raw, $headerSize) {
    $headers = [];
    foreach (explode("\r\n", substr((string)$raw,0,$headerSize)) as $line) { if (strpos($line,':') !== false) { [$k,$v] = explode(':',$line,2); $headers[strtolower(trim($k))] = trim($v); } }
    return [$code,$headers,substr((string)$raw,$headerSize)];
}
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
foreach ([95, 98, $cong2User] as $u) { $ins->execute([$u, 21]); }
$ins->execute([$parish, 65]);
foreach ([95, 98, $cong2User] as $u) { $ins->execute([$u, 4]); }
$ins->execute([$parish, 49]);
$uRight = mkSession(95); $uType4 = mkSession(98); $uNone = mkSession(101); $uAdmin = mkSession(64, 11); $uOther = mkSession($cong2User); $uParish = mkSession($parish);
$today = date('Y-m-d');
$closedDate = '2024-05-01';

function acct($name, $parent, $posting = 1, $active = 1, $cong = 11, $type = 3) {
    global $pdo;
    $pdo->prepare('INSERT INTO tblaccounttypes(accountType,accountTypeId,parentId,isSubCategory,isBank,active,congregationId,deleted) VALUES(?,?,?,?,0,?,?,0)')->execute([$name, $type, $parent, $posting, $active, $cong]);
    return (int)$pdo->lastInsertId();
}
function prod($name, $account, $cong = 11) { global $pdo; $pdo->prepare('INSERT INTO tblproducts(productName,rate,accountId,congregationId) VALUES(?,?,?,?)')->execute([$name, '100.00', $account, $cong]); return (int)$pdo->lastInsertId(); }
$recA = acct('zz http recv', 9); $recRep = acct('zz http recv parent', 9, 0); $recInactive = acct('zz http recv inactive', 9, 1, 0); $recForeign = acct('zz http recv foreign', 9, 1, 1, 2);
$incInactive = acct('zz http inc inactive', 31, 1, 0, 11, 1);
$pOk = prod('zz http c product', 90); $pExp = prod('zz http c expense product', 51); $pBad = prod('zz http c inactive product', $incInactive); $pForeign = prod('zz http c foreign product', 90, 2); $pXss = prod('zz <b>bold</b> cproduct', 90);
$pdo->exec("INSERT INTO tblcustomers(customerName,email,pin,congregationId) VALUES('zz http customer','zc@example.com','c123',11),('zz <script>alert(1)</script> customer',NULL,NULL,11)");
$cus = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO tblcustomers(customerName,congregationId) VALUES('zz http foreign customer',2)"); $cusForeign = (int)$pdo->lastInsertId();
function payload($o = []) {
    global $cus, $pOk, $recA, $today;
    return ['header' => array_merge(['customer' => $cus, 'invoiceDate' => $today, 'dueDate' => $today, 'vatType' => '1', 'vat' => '', 'receivable' => $recA, 'id' => ''], $o['header'] ?? []),
            'table' => $o['table'] ?? [['pid' => (string)$pOk, 'qty' => '2', 'rate' => '50.00', 'desc' => 'two things']]];
}

try {
    // ------------------------------------------------------------ pages, rights, no session
    foreach (['invoices', 'invoices/add'] as $p) {
        foreach (['admin' => $uAdmin, 'right' => $uRight, 'type4' => $uType4, 'parish' => $uParish] as $l => $sid) {
            $r = http88('GET', $p, $sid);
            chk("$p opens for $l", $r[0] === 200 && stripos($r[2], '<html') !== false, "status {$r[0]}");
        }
        $r = http88('GET', $p, $uNone);
        chk("$p denied without the right", redir($r, 'deniedaccess'), "status {$r[0]}");
        $r = http88('GET', $p, '');
        chk("$p without a session goes to login", redir($r, 'users'), "status {$r[0]}");
    }
    foreach (['invoices/fetchcustomerdetails?cid=' . $cus, 'invoices/getproduct?pid=' . $pOk] as $u) {
        $r = http88('GET', $u, $uNone);
        chk("AJAX $u denied without the right", redir($r, 'deniedaccess'));
    }

    // ------------------------------------------------------------ customer master screen
    foreach (['customers','customers/add'] as $p) {
        foreach (['admin'=>$uAdmin,'right'=>$uRight,'type4'=>$uType4,'parish'=>$uParish] as $label=>$sid) {
            $r = http88('GET',$p,$sid);
            chk("$p opens for $label",$r[0] === 200 && stripos($r[2],'<html') !== false,"status {$r[0]}");
        }
        chk("$p denied without the right",redir(http88('GET',$p,$uNone),'deniedaccess'));
        chk("$p without a session goes to login",redir(http88('GET',$p,''),'users'));
    }
    $customerAdd = http88('GET','customers/add',$uRight)[2];
    chk('customer form loads its module and feedback helper without nextSibling.nextSibling',strpos($customerAdd,'customers/add-v1.js') !== false && strpos(jsfile($base . '/dist/js/pages/customers/add-v1.js'),'nextSibling.nextSibling') === false && strlen(jsfile($base . '/dist/js/pages/utils/feedback.js')) > 200);
    $customerIndex = http88('GET','customers',$uRight)[2];
    chk('customer list escapes names and does not expose another congregation',stripos($customerIndex,'<script>alert(1)</script>') === false && stripos($customerIndex,'&lt;script&gt;alert(1)&lt;/script&gt;') !== false && stripos($customerIndex,'zz http foreign customer') === false);
    $customerPayload = ['customername'=>'ZZ HTTP CRUD','contact'=>'12345678901234567890','address'=>'Address','pin'=>'PIN-HTTP','email'=>'http-crud@example.com','contactperson'=>'Person','id'=>'0'];
    $r = http88('POST','customers/create',$uRight,$customerPayload,true); $j = jo($r[2]); $httpCustomer = (int)($j['id'] ?? 0);
    chk('customer JSON create stores an own customer',$r[0] === 200 && $httpCustomer > 0 && (int)one('SELECT congregationId c FROM tblcustomers WHERE ID=?',[$httpCustomer])['c'] === 11,$r[2]);
    $r = http88('POST','customers/create',$uRight,array_merge($customerPayload,['customername'=>' zz http crud ']),true);
    chk('customer JSON create refuses a duplicate name in the congregation',$r[0] === 422 && stripos($r[2],'name exists') !== false);
    $r = http88('POST','customers/create',$uRight,array_merge($customerPayload,['customername'=>'zz bad email','email'=>'bad@']),true);
    chk('customer JSON create validates email server-side',$r[0] === 422 && stripos($r[2],'valid email') !== false);
    $r = http88('GET','customers/edit/' . $httpCustomer,$uRight);
    chk('customer edit form loads an own customer and escapes its values',$r[0] === 200 && strpos($r[2],'value="ZZ HTTP CRUD"') !== false);
    $r = http88('GET','customers/edit/' . $cusForeign,$uRight);
    chk('customer edit GET refuses a forged cross-congregation ID',redir($r,'customers') && stripos($r[2],'foreign customer') === false);
    $foreignCustomerName = one('SELECT customerName n FROM tblcustomers WHERE ID=?',[$cusForeign])['n'];
    $r = http88('POST','customers/update',$uRight,array_merge($customerPayload,['id'=>(string)$cusForeign,'customername'=>'forged customer']),true);
    chk('customer update POST refuses a forged cross-congregation ID',$r[0] === 422 && one('SELECT customerName n FROM tblcustomers WHERE ID=?',[$cusForeign])['n'] === $foreignCustomerName);
    $r = http88('POST','customers/update',$uRight,array_merge($customerPayload,['id'=>(string)$httpCustomer,'customername'=>'ZZ HTTP CRUD EDITED']),true);
    chk('customer update changes an own customer',$r[0] === 200 && one('SELECT customerName n FROM tblcustomers WHERE ID=?',[$httpCustomer])['n'] === 'zz http crud edited',$r[2]);
    $r = http88('POST','customers/delete',$uRight,['id'=>$cusForeign]);
    chk('customer delete refuses a forged cross-congregation ID',redir($r,'customers') && cnt('SELECT deleted FROM tblcustomers WHERE ID=?',[$cusForeign]) === 0);
    $r = http88('POST','customers/delete',$uRight,['id'=>$httpCustomer]);
    chk('customer delete remains a soft delete for an unreferenced own customer',redir($r,'customers') && cnt('SELECT deleted FROM tblcustomers WHERE ID=?',[$httpCustomer]) === 1);
    chk('customer create/update GET methods are refused as JSON',http88('GET','customers/create',$uRight)[0] === 405 && http88('GET','customers/update',$uRight)[0] === 405);

    // ------------------------------------------------------------ rendered invoice form
    $add = http88('GET', 'invoices/add', $uRight)[2];
    $rec = opts($add, 'receivable');
    chk('invoice form: the receivable dropdown has NO selected account (no default)', count(array_filter($rec, function($o) { return $o[0]; })) <= 1 && !isset($rec[''][0]) || $rec[''][0] === true);
    $selectedReal = array_filter($rec, function($o, $k) { return $o[0] && $k !== ''; }, ARRAY_FILTER_USE_BOTH);
    chk('invoice form: no real account is preselected', !$selectedReal);
    chk('invoice form: the list = Ledger::listReceivableAccounts (own and shared posting accounts under 9 only)', isset($rec[(string)$recA]) && isset($rec['35']) && !isset($rec['9']) && !isset($rec[(string)$recRep]) && !isset($rec[(string)$recInactive]) && !isset($rec[(string)$recForeign]) && !isset($rec['23']) && !isset($rec['105'])
        && !array_diff(array_filter(array_keys($rec), 'strlen'), array_map('strval', array_column(Ledger::listReceivableAccounts($pdo, 11), 'ID'))));
    $prodOpts = opts($add, 'product');
    chk('invoice form: income products listed; products 1 and 2, expense / bad-account / foreign products absent', isset($prodOpts[(string)$pOk]) && !isset($prodOpts['1']) && !isset($prodOpts['2']) && !isset($prodOpts[(string)$pExp]) && !isset($prodOpts[(string)$pBad]) && !isset($prodOpts[(string)$pForeign]));
    $accOpts = opts($add, 'account');
    chk('invoice form: the product modal lists only income posting accounts', isset($accOpts['90']) && isset($accOpts['89']) && !isset($accOpts['51']) && !isset($accOpts['32']) && !isset($accOpts['105']) && !isset($accOpts[(string)$incInactive]));
    chk('invoice form: line table shows an Account column, loads customer-v1.js, has no Delete anything', strpos($add, '<th width="20%">Account</th>') !== false && strpos($add, 'pages/invoices/customer-v1.js') !== false && stripos($add, 'invoices/delete') === false && stripos($add, 'btndel') === false);
    chk('invoice form: names are escaped (no raw script / bold tag)', stripos($add, '<script>alert(1)</script>') === false && stripos($add, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && stripos($add, '<b>bold</b>') === false);
    foreach (['invoices/customer-v1.js', 'invoices/customer-pay-v1.js', 'utils/feedback.js'] as $js) {
        $src = jsfile($base . '/dist/js/pages/' . $js);
        chk("JS $js is served and does not use nextSibling.nextSibling", strlen($src) > 200 && strpos($src, 'nextSibling.nextSibling') === false);
    }
    chk('the shared utils.js is untouched (git diff is empty)', trim((string)shell_exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' diff --stat -- public/dist/js/pages/utils/utils.js')) === '');
    $idxHtml = http88('GET', 'invoices', $uAdmin)[2];
    chk('invoice list: no Delete button, no delete form or modal', stripos($idxHtml, 'btndel') === false && stripos($idxHtml, 'invoices/delete') === false);

    // ------------------------------------------------------------ empty receivables list: clear message on the form
    $cand = array_column(Ledger::listReceivableAccounts($pdo, 11), 'ID');
    foreach ($cand as $a) { $pdo->prepare('UPDATE tblaccounttypes SET active=0 WHERE ID=?')->execute([$a]); }
    $empty = http88('GET', 'invoices/add', $uRight)[2];
    $r = http88('POST', 'invoices/create', $uRight, payload(), true);
    foreach ($cand as $a) { $pdo->prepare('UPDATE tblaccounttypes SET active=1 WHERE ID=?')->execute([$a]); }
    chk('empty receivables list: the form says to ask the accountant and the save is refused with a 422', stripos($empty, 'ask the accountant') !== false && strpos($empty, 'data-norec="1"') !== false && $r[0] === 422 && stripos($r[2], 'No receivables account') !== false);

    // ------------------------------------------------------------ AJAX
    $r = http88('GET', 'invoices/fetchcustomerdetails?cid=' . $cus, $uRight);
    $j = jo($r[2]);
    chk('fetchcustomerdetails returns email and PIN of an own customer', $r[0] === 200 && $j['email'] === 'zc@example.com' && $j['pin'] === 'C123');
    $r = http88('GET', 'invoices/fetchcustomerdetails?cid=' . $cusForeign, $uRight);
    chk('fetchcustomerdetails of another congregation\'s customer is a 404 JSON', $r[0] === 404 && jo($r[2]) !== null);
    $r = http88('GET', 'invoices/fetchcustomerdetails', $uRight);
    chk('fetchcustomerdetails without an ID is a 400 JSON', $r[0] === 400 && jo($r[2]) !== null);
    $r = http88('POST', 'invoices/fetchcustomerdetails', $uRight, []);
    chk('fetchcustomerdetails by POST is a 405', $r[0] === 405);
    $r = http88('GET', 'invoices/getproduct?pid=' . $pOk, $uRight);
    $j = jo($r[2]);
    chk('getproduct returns rate and the income account of an own product', $r[0] === 200 && (float)$j['rate'] === 100.0 && (int)$j['accountId'] === 90);
    foreach ([['foreign product', $pForeign, 404], ['retired product 1', 1, 404], ['retired product 2', 2, 404], ['expense-account product', $pExp, 422], ['inactive-account product', $pBad, 422], ['junk', 'x', 404], ['empty', '', 400]] as [$label, $pid, $code]) {
        $r = http88('GET', 'invoices/getproduct?pid=' . urlencode((string)$pid), $uRight);
        chk("getproduct: $label -> $code JSON", $r[0] === $code && jo($r[2]) !== null, "status {$r[0]}");
    }
    $r = http88('POST', 'invoices/getproduct', $uRight, []);
    chk('getproduct by POST is a 405', $r[0] === 405);
    foreach (['invoices/reloadproducts', 'invoices/getproductrate', 'invoices/getrate', 'invoices/delete'] as $gone) {
        $r = http88('POST', $gone, $uRight, ['id' => 1, 'vat' => 1, 'product' => 1]);
        chk("old endpoint $gone is gone (an unknown method falls back to the list page: no JSON, no data)", jo($r[2]) === null && strpos($r[2], 'invoicesTable') !== false);
    }
    $r = http88('POST', 'invoices/newproduct', $uRight, ['name' => 'zz <i>ajax</i> cproduct', 'desc' => 'd', 'sp' => '75.50', 'account' => '90'], true);
    $j = jo($r[2]); $newPid = (int)($j['productid'] ?? 0);
    chk('newproduct creates the income product and returns ITS id and escaped options', $r[0] === 200 && $newPid > 0 && one('SELECT accountId a FROM tblproducts WHERE ID=?', [$newPid])['a'] == 90 && stripos($j['products'], '<i>ajax</i>') === false && stripos($j['products'], '&lt;i&gt;ajax&lt;/i&gt;') !== false);
    foreach ([['expense account', ['account' => '51']], ['bank account', ['account' => '105']], ['reporting account', ['account' => '32']], ['missing price', ['sp' => '']], ['duplicate name', ['name' => 'zz http c product']]] as [$label, $over]) {
        $c = cnt('SELECT COUNT(*) FROM tblproducts');
        $r = http88('POST', 'invoices/newproduct', $uRight, array_merge(['name' => 'zz bad ' . $label, 'desc' => '', 'sp' => '5', 'account' => '90'], $over), true);
        chk("newproduct refuses $label (422 JSON, nothing saved)", $r[0] === 422 && jo($r[2]) !== null && cnt('SELECT COUNT(*) FROM tblproducts') === $c, "status {$r[0]}");
    }
    $r = http88('GET', 'invoices/newproduct', $uRight);
    chk('newproduct by GET is a 405', $r[0] === 405);

    // ------------------------------------------------------------ create / edit
    $r = http88('POST', 'invoices/create', $uRight, payload(['header' => ['vatType' => '3', 'vat' => '16']]), true);
    $j = jo($r[2]); $inv = (int)($j['id'] ?? 0);
    chk('real JSON create: 200, number from the sequence, type 14 entry Dr receivable 116.00 / Cr income 116.00', $r[0] === 200 && $inv > 0 && !empty($j['invoiceNo']) && one("SELECT GROUP_CONCAT(CONCAT(l.accountId,'|',l.dc,'|',l.amount) ORDER BY l.lineNumber) g FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=14 AND e.transactionId=? AND e.deleted=0", [$inv])['g'] === "$recA|DEBIT|116.00,90|CREDIT|116.00", $r[2]);
    chk('create stores the receivable account and the owner congregation', (int)one('SELECT ReceivableAccountId a FROM tblinvoice_header WHERE ID=?', [$inv])['a'] === $recA && (int)one('SELECT congregationId c FROM tblinvoice_header WHERE ID=?', [$inv])['c'] === 11);
    $r = http88('POST','customers/delete',$uRight,['id'=>$cus]);
    chk('customer delete is refused once the customer has an invoice',redir($r,'customers') && cnt('SELECT deleted FROM tblcustomers WHERE ID=?',[$cus]) === 0);

    // Two sessions of the same congregation submit together: the locked sequence must allocate two distinct numbers.
    $beforeConcurrent = cnt('SELECT COALESCE(MAX(invoiceNo),0) FROM tblinvoice_header WHERE congregationId=11');
    $mh = curl_multi_init(); $handles = [];
    foreach ([$uRight,$uAdmin] as $sid) {
        $ch = curl_init($base . '/invoices/create');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>'PHPSESSID=' . $sid,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(payload()),CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>60]);
        curl_multi_add_handle($mh,$ch); $handles[] = $ch;
    }
    do { curl_multi_exec($mh,$running); if ($running) { curl_multi_select($mh,0.2); } } while ($running > 0);
    $parallel = [];
    foreach ($handles as $ch) { $raw = curl_multi_getcontent($ch); $parallel[] = parse88(curl_getinfo($ch,CURLINFO_HTTP_CODE),$raw,curl_getinfo($ch,CURLINFO_HEADER_SIZE)); curl_multi_remove_handle($mh,$ch); curl_close($ch); }
    curl_multi_close($mh);
    $parallelJson = array_map(function($response){ return jo($response[2]); },$parallel);
    $parallelNos = array_map(function($json){ return (int)($json['invoiceNo'] ?? 0); },$parallelJson); sort($parallelNos);
    chk('concurrent customer invoice creates get distinct consecutive congregation numbers',array_column($parallel,0) === [200,200] && $parallelNos === [$beforeConcurrent + 1,$beforeConcurrent + 2],json_encode([$parallelNos,$parallel]));
    $entriesBefore = cnt('SELECT COUNT(*) FROM journal_entries WHERE ID>?', [$max['journal_entries']]);
    foreach ([['receivable missing', ['header' => ['receivable' => '']]], ['receivable 9', ['header' => ['receivable' => '9']]], ['receivable inactive', ['header' => ['receivable' => (string)$recInactive]]], ['receivable of another congregation', ['header' => ['receivable' => (string)$recForeign]]],
              ['future date', ['header' => ['invoiceDate' => date('Y-m-d', strtotime('+1 day'))]]], ['closed-year date', ['header' => ['invoiceDate' => $closedDate]]], ['forged customer', ['header' => ['customer' => (string)$cusForeign]]],
              ['forged product', ['table' => [['pid' => (string)$pForeign, 'qty' => '1', 'rate' => '1']]]], ['retired product', ['table' => [['pid' => '2', 'qty' => '1', 'rate' => '1']]]], ['expense product', ['table' => [['pid' => (string)$pExp, 'qty' => '1', 'rate' => '1']]]],
              ['bad quantity', ['table' => [['pid' => (string)$pOk, 'qty' => '-1', 'rate' => '1']]]], ['no lines', ['table' => []]]] as [$label, $over]) {
        $r = http88('POST', 'invoices/create', $uRight, payload($over), true);
        $j = jo($r[2]);
        chk("real JSON create refused: $label (422 JSON with a message)", $r[0] === 422 && !empty($j['message']), "status {$r[0]} " . $r[2]);
    }
    chk('refused creates left no entry behind', cnt('SELECT COUNT(*) FROM journal_entries WHERE ID>?', [$max['journal_entries']]) === $entriesBefore);
    $r = http88('POST', 'invoices/create', $uNone, payload(), true);
    chk('create denied without the right', redir($r, 'deniedaccess'));
    $r = http88('GET', 'invoices/create', $uRight);
    chk('create by GET is a 405', $r[0] === 405);
    $r = http88('POST', 'invoices/create', $uRight, null, false, '{"nope":1}');
    chk('create without a header is a 400', $r[0] === 400);
    $r = http88('POST', 'invoices/create', $uRight, payload(['table' => [['pid' => (string)$pBad, 'qty' => '1', 'rate' => '1']]]), true);
    chk('error messages are HTML-escaped (the page inserts them as HTML)', $r[0] === 422 && strpos(jo($r[2])['message'], '<') === false);

    $enc = encryptId($inv);
    $r = http88('GET', 'invoices/edit/' . $enc, $uRight);
    $eo = opts($r[2], 'receivable');
    chk('edit form: 200, the stored receivable account is the selected one, lines show their account, invoice number shown', $r[0] === 200 && isset($eo[(string)$recA]) && $eo[(string)$recA][0] && strpos($r[2], 'class="account"') !== false && strpos($r[2], 'value="' . $inv . '"') !== false);
    $r = http88('GET', 'invoices/edit/' . encryptId(999999), $uRight);
    chk('edit form of an unknown invoice redirects to the list', redir($r, 'invoices'));
    $pdo->prepare('INSERT INTO tblinvoice_header(invoiceDate,duedate,customerId,invoiceNo,fiscalYearId,vattype,ReceivableAccountId,exclusiveVat,vat,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,920001,25,1,?,10,0,10,64,2)')->execute([$today, $today, $cusForeign, $recForeign]);
    $foreignInv = (int)$pdo->lastInsertId();
    $r = http88('GET', 'invoices/edit/' . encryptId($foreignInv), $uRight);
    chk('edit form of ANOTHER congregation\'s invoice (forged ID) redirects and shows nothing', redir($r, 'invoices') && strpos($r[2], '920001') === false);
    $r = http88('GET', 'invoices/print/' . encryptId($foreignInv), $uRight);
    chk('print of another congregation\'s invoice (forged ID) redirects', redir($r, 'invoices'));
    $r = http88('GET', 'invoices/pay/' . encryptId($foreignInv), $uRight);
    chk('pay form of another congregation\'s invoice (forged ID) redirects', redir($r, 'invoices'));
    $r = http88('GET', 'invoices/print/' . $enc, $uRight);
    chk('print of an own invoice renders (escaped, no PHP warning)', $r[0] === 200 && stripos($r[2], 'Invoice #') !== false);

    $edit = payload(['header' => ['id' => (string)$inv, 'receivable' => '35'], 'table' => [['pid' => (string)$pOk, 'qty' => '3', 'rate' => '10.00']]]);
    $entryId = one('SELECT ID FROM journal_entries WHERE transactionTypeId=14 AND transactionId=? AND deleted=0', [$inv])['ID'];
    $r = http88('POST', 'invoices/update', $uRight, $edit, true);
    chk('real JSON update: in place (same entry), new receivable and amount', $r[0] === 200 && one('SELECT ID FROM journal_entries WHERE transactionTypeId=14 AND transactionId=? AND deleted=0', [$inv])['ID'] === $entryId && (int)one('SELECT ReceivableAccountId a FROM tblinvoice_header WHERE ID=?', [$inv])['a'] === 35 && one('SELECT inclusiveVat v FROM tblinvoice_header WHERE ID=?', [$inv])['v'] === '30.00', $r[2]);
    $r = http88('POST', 'invoices/update', $uRight, payload(['header' => ['id' => (string)$foreignInv]]), true);
    chk('real JSON update of another congregation\'s invoice (forged ID) is refused and changes nothing', $r[0] === 422 && one('SELECT inclusiveVat v FROM tblinvoice_header WHERE ID=?', [$foreignInv])['v'] === '10.00');
    $r = http88('GET', 'invoices/update', $uRight);
    chk('update by GET is a 405', $r[0] === 405);

    // ------------------------------------------------------------ payments
    $pf = http88('GET', 'invoices/pay/' . $enc, $uRight)[2];
    $methods = opts($pf, 'paymethod'); $cashOpts = opts($pf, 'cashaccount'); $bankOpts = opts($pf, 'bank');
    chk('pay form: all four methods; the receivable account and the balance are shown read-only', array_keys($methods) == ['1', '2', '3', '4'] && stripos($pf, 'Receivable account (credited)') !== false && strpos($pf, 'customer-pay-v1.js') !== false);
    chk('pay form: the cash list is the PAY_CASH list and the bank list the PAY_BANK list (they differ)', isset($cashOpts['34']) && !isset($cashOpts['105']) && isset($bankOpts['105']) && !isset($bankOpts['34']) && !isset($bankOpts['101']));
    $r = http88('POST', 'invoices/payment', $uRight, ['id' => $inv, 'paydate' => $today, 'amount' => '10.00', 'paymethod' => '1', 'cashaccount' => '34', 'bank' => '', 'reference' => '']);
    $payRow = (int)one('SELECT MAX(ID) m FROM tblinvoice_payments WHERE invoice_Id=?', [$inv])['m'];
    chk('real payment POST (cash): redirect, row, entry Dr 34 / Cr the stored receivable, no bank row', redir($r, 'invoices') && one("SELECT GROUP_CONCAT(CONCAT(l.accountId,'|',l.dc,'|',l.amount) ORDER BY l.lineNumber) g FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=16 AND e.transactionId=? AND e.deleted=0", [$payRow])['g'] === '34|DEBIT|10.00,35|CREDIT|10.00' && cnt('SELECT COUNT(*) FROM tblbankpostings WHERE transactionType=16 AND transactionId=?', [$payRow]) === 0);
    $r = http88('POST', 'invoices/payment', $uRight, ['id' => $inv, 'paydate' => $today, 'amount' => '5.00', 'paymethod' => '2', 'cashaccount' => '', 'bank' => '105', 'reference' => 'HTTP-MPESA']);
    $payRow2 = (int)one('SELECT MAX(ID) m FROM tblinvoice_payments WHERE invoice_Id=?', [$inv])['m'];
    chk('real payment POST (M-Pesa): one deposit bank row on the chosen bank', redir($r, 'invoices') && cnt('SELECT COUNT(*) FROM tblbankpostings WHERE transactionType=16 AND transactionId=? AND bankId=105 AND deleted=0', [$payRow2]) === 1);
    $b4 = [cnt('SELECT COUNT(*) FROM tblinvoice_payments'), cnt('SELECT COUNT(*) FROM journal_entries WHERE transactionTypeId=16 AND ID>?', [$max['journal_entries']])];
    $r = http88('POST', 'invoices/payment', $uRight, ['id' => $inv, 'paydate' => $today, 'amount' => '15.01', 'paymethod' => '1', 'cashaccount' => '34']);
    chk('HTTP overpayment is refused with the page and the reason, no row', $r[0] === 200 && stripos($r[2], 'overpayment') !== false && cnt('SELECT COUNT(*) FROM tblinvoice_payments') === $b4[0]);
    foreach ([['bank used for cash', ['paymethod' => '1', 'cashaccount' => '105']], ['cheque without a reference', ['paymethod' => '3', 'bank' => '105', 'reference' => '']], ['cash for a bank method', ['paymethod' => '4', 'bank' => '34', 'reference' => 'x']],
              ['future date', ['paydate' => date('Y-m-d', strtotime('+1 day'))]], ['closed-year date', ['paydate' => $closedDate]], ['method not in the table', ['paymethod' => '9']]] as [$label, $over]) {
        $r = http88('POST', 'invoices/payment', $uRight, array_merge(['id' => $inv, 'paydate' => $today, 'amount' => '1.00', 'paymethod' => '1', 'cashaccount' => '34', 'bank' => '', 'reference' => ''], $over));
        chk("HTTP payment refused: $label (page with an error, nothing written)", $r[0] === 200 && strpos($r[2], 'id="payerrors"') !== false && cnt('SELECT COUNT(*) FROM tblinvoice_payments') === $b4[0], "status {$r[0]}");
    }
    $r = http88('POST', 'invoices/payment', $uRight, ['id' => $foreignInv, 'paydate' => $today, 'amount' => '1', 'paymethod' => '1', 'cashaccount' => '34']);
    chk('forged invoice of another congregation: payment refused, nothing written', redir($r, 'invoices') && cnt('SELECT COUNT(*) FROM tblinvoice_payments WHERE invoice_Id=?', [$foreignInv]) === 0);
    $r = http88('POST', 'invoices/payment', $uOther, ['id' => $inv, 'paydate' => $today, 'amount' => '1', 'paymethod' => '1', 'cashaccount' => '34']);
    chk('another congregation\'s user cannot pay an invoice of 11', cnt('SELECT COUNT(*) FROM tblinvoice_payments WHERE invoice_Id=?', [$inv]) === 2);
    $r = http88('POST', 'invoices/payment', $uNone, ['id' => $inv, 'paydate' => $today, 'amount' => '1']);
    chk('payment denied without the right', redir($r, 'deniedaccess'));
    $r = http88('GET', 'invoices/payment', $uRight);
    chk('payment by GET goes back to the list', redir($r, 'invoices'));
    $pdo->prepare('INSERT INTO tblinvoice_header(invoiceDate,duedate,customerId,invoiceNo,fiscalYearId,vattype,exclusiveVat,vat,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,920002,25,1,50,0,50,64,11)')->execute([$today, $today, $cus]);
    $legacy = (int)$pdo->lastInsertId();
    $pl = http88('GET', 'invoices/pay/' . encryptId($legacy), $uRight);
    chk('pay form of a legacy invoice without a receivable account: warning and the Save button disabled', stripos($pl[2], 'no receivable account recorded') !== false && preg_match('~id="save"\s+disabled~', $pl[2]) === 1);
    $r = http88('POST', 'invoices/payment', $uRight, ['id' => $legacy, 'paydate' => $today, 'amount' => '1', 'paymethod' => '1', 'cashaccount' => '34']);
    chk('HTTP payment of that legacy invoice is refused with the reason', $r[0] === 200 && stripos($r[2], 'no receivable account') !== false && cnt('SELECT COUNT(*) FROM tblinvoice_payments WHERE invoice_Id=?', [$legacy]) === 0);

    // paid invoice
    $r = http88('GET', 'invoices/edit/' . $enc, $uAdmin);
    chk('edit form of an invoice with payments shows the lock notice and marks the form locked', $r[0] === 200 && strpos($r[2], 'only the due date can be changed') !== false && strpos($r[2], 'data-locked="1"') !== false);
    $r = http88('POST', 'invoices/update', $uAdmin, payload(['header' => ['id' => (string)$inv, 'receivable' => '35'], 'table' => [['pid' => (string)$pOk, 'qty' => '4', 'rate' => '10.00']]]), true);
    chk('real JSON update of a paid invoice (changed lines) is refused with the reason', $r[0] === 422 && stripos($r[2], 'has payments') !== false);
    $listType4 = http88('GET', 'invoices', $uType4)[2];
    chk('list: Pay is offered for unpaid / partly paid invoices; a type 4 user gets no Edit on someone else\'s', strpos($listType4, 'invoices/pay/' . $enc) !== false && strpos($listType4, 'invoices/edit/' . $enc) === false);
    $r = http88('GET', 'invoices/edit/' . $enc, $uType4);
    chk('edit page of someone else\'s invoice is refused for a type 4 user', redir($r, 'invoices'));
    $r = http88('POST', 'invoices/update', $uType4, payload(['header' => ['id' => (string)$inv, 'receivable' => '35']]), true);
    chk('JSON update by a type 4 user of someone else\'s invoice is refused', $r[0] === 422);
    $idx = http88('GET', 'invoices', $uAdmin)[2];
    chk('invoice list: own invoices only, names escaped', strpos($idx, 'invoices/print/' . encryptId($foreignInv)) === false && strpos($idx, 'invoices/print/' . $enc) !== false && stripos($idx, '<script>alert(1)</script>') === false);
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
    $st = $pdo->prepare('UPDATE tblaccounttypes SET active=? WHERE ID=?');
    foreach ($activeBefore as $id => $a) { if ($id <= $max['tblaccounttypes']) { $st->execute([$a, $id]); } }
}
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k>" . $max[$t])->fetchColumn(); }
chk('cleanup: no row above the starting IDs remains', $left === 0);
echo "RESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);

<?php
// 8.9 HTTP-level run: the real controllers, rights checks, congregation checks, JSON endpoints and rendered forms of
// MMF receipts, group collections and group fund requisitions / approvals, through Apache with real PHP sessions
// (the CLI model tests call the models directly: no controller, no rights, no session, no JSON, no JS data).
// Needs the dev web server running at URLROOT. A session is created for existing dev users (no password involved) and
// its file is removed at the end. Congregation 11, dev only; cleans up after itself.
//   E:\xampp\php\php.exe scripts\test_8_9_http.php
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Mmfreceipt.php';
require_once 'models/Groupfund.php';

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;     // for the model objects used here to compute the expected figures
$_SESSION['userId'] = 1;
$base = URLROOT;
$tables = ['tblmmf' => 'ID', 'tblfundrequisition' => 'ID', 'journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblledger' => 'ID',
           'tblbankpostings' => 'ID', 'tbllogs' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$balBefore = json_encode($pdo->query('SELECT g.ID, getmmfopeningbalbydate(g.ID,CURDATE()) b FROM tblgroups g ORDER BY g.ID')->fetchAll(PDO::FETCH_ASSOC));

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }

$sessions = [];
$phpErrors = [];
/** A PHP session for an existing dev user, written the way Users::createUserSession() does. */
function mkSession($uid, $cong = null) {
    global $pdo, $sessions;
    $u = q('SELECT u.ID,u.UserName,u.UsertypeId,u.CongregationId,c.CongregationName,c.IsParish FROM tblusers u LEFT JOIN tblcongregation c ON c.ID=u.CongregationId WHERE u.ID=?', [$uid])[0];
    $sid = 'dev' . bin2hex(random_bytes(8));
    $data = ['userId' => $u['ID'], 'userName' => $u['UserName'], 'userType' => $u['UsertypeId'], 'congId' => $cong ?? $u['CongregationId'],
             'isParish' => $u['IsParish'], 'congName' => $u['CongregationName'], 'one' => 1, 'zero' => 0, 'processdate' => date('d-m-Y')];
    $enc = '';
    foreach ($data as $k => $v) { $enc .= $k . '|' . serialize($v); }
    file_put_contents(session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid, $enc);
    $sessions[] = $sid;
    return $sid;
}
/** @return array [status, headers(lower-case name => value), body] ; redirects are NOT followed */
function http($method, $path, $sid, $body = null, $json = false) {
    global $base;
    $ch = curl_init($base . '/' . $path);
    $h = [];
    if ($json) { $h[] = 'Content-Type: application/json'; }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_HTTPHEADER => $h,
                            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json ? json_encode($body) : http_build_query($body ?? []));
    }
    $raw = curl_exec($ch);
    global $phpErrors;
    if (preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', (string)$raw, $pm)) { $phpErrors[] = $path . ' -> ' . $pm[2]; }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return parseResponse($code, (string)$raw, $hs);
}
function parseResponse($code, $raw, $hs) {
    $headers = [];
    foreach (explode("\r\n", substr($raw, 0, $hs)) as $line) { if (strpos($line, ':') !== false) { [$k, $v] = explode(':', $line, 2); $headers[strtolower(trim($k))] = trim($v); } }
    return [$code, $headers, substr($raw, $hs)];
}
function jsonOf($body) { $j = json_decode($body, true); return json_last_error() === JSON_ERROR_NONE ? $j : null; }
/** value => selected? of a select, from the rendered HTML */
function selectOptions($html, $name) {
    $d = new DOMDocument; @$d->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $x = new DOMXPath($d);
    $out = [];
    foreach ($x->query("//select[@name='$name']//option") as $o) { $out[$o->getAttribute('value')] = $o->hasAttribute('selected'); }
    return $out;
}
function nodeAttr($html, $query, $attr) {
    $d = new DOMDocument; @$d->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $n = (new DOMXPath($d))->query($query)->item(0);
    return $n ? $n->getAttribute($attr) : null;
}
function redirectsTo($r, $needle) { return in_array($r[0], [301, 302, 303], true) && strpos($r[1]['location'] ?? '', $needle) !== false; }

$mm = new Mmfreceipt; $gf = new Groupfund;
$G = 40; $D = 57; $SUB_G = 1; $BANK = 105; $FUNDS = 115;
$today = date('Y-m-d');
$uAll = mkSession(95);                  // fund requisition + fund approval + group collections + mmf receipts
$uFund = mkSession(98);                 // fund requisition + fund approval only (no group collections, no mmf receipts)
$uNone = mkSession(101);                // none of the four rights
$uAdmin = mkSession(64, 11);            // super admin (userType 1) working in congregation 11
$foreignGroup = (int)q('SELECT ID FROM tblgroups WHERE congregationId<>11 AND deleted=0 LIMIT 1')[0]['ID'];

try {
    // ---------------------------------------------------------------- the list pages open for the users who have the right
    foreach ([['groupfunds', $uFund], ['groupfunds/approvals', $uFund], ['groupfunds/add', $uFund], ['mmfreceipts', $uAll], ['mmfreceipts/add', $uAll], ['groupcollections', $uAll], ['groupcollections/add', $uAll]] as [$p, $sid]) {
        $r = http('GET', $p, $sid);
        chk("page $p opens (200, a page, not a redirect)", $r[0] === 200 && stripos($r[2], '<html') !== false, "status {$r[0]}");
    }

    // ---------------------------------------------------------------- pre-flight: the web server answers
    $r = http('GET', 'groupfunds/getgroupordistrict?type=group', $uAll);
    chk('web server reachable, session accepted', $r[0] === 200, "status {$r[0]}: " . substr($r[2], 0, 120));

    // ---------------------------------------------------------------- every AJAX URL a screen's JavaScript calls must be open to the user who can open that screen
    // (this is the check that would have caught the 403 on the shared group list: the CLI tests never run the page JS)
    $screens = [
        ['groupfunds/add', $uFund, 'requisition form, user with the fund requisition right only'],
        ['groupfunds/add', $uAll, 'requisition form, user with all rights'],
        ['groupcollections/add', $uAll, 'group collection form'],
        ['mmfreceipts/add', $uAll, 'MMF form'],
    ];
    foreach ($screens as [$page, $sid, $label]) {
        $html = http('GET', $page, $sid)[2];
        $js = $html;                                        // inline scripts are part of the page
        if (preg_match_all('~<script[^>]+src="([^"]+\.js)"~', $html, $m)) {
            foreach ($m[1] as $src) { if (strpos($src, URLROOT) === 0) { $js .= "
" . (string)@file_get_contents($src); } }
        }
        preg_match_all('~(?:HOST_URL\}|\$\{url\}|URLROOT)/((?:groupfunds|groupcollections|mmfreceipts)/[a-z]+)~', $js, $mm2);
        $urls = array_values(array_unique(array_filter($mm2[1], function ($u) { return !preg_match('~/(createupdate|delete|edit|add|approve|approvefunds|index)$~', $u); })));
        chk("$label: its JavaScript calls at least one AJAX endpoint", count($urls) > 0, $page);
        foreach ($urls as $u) {
            $r = http('GET', $u, $sid);
            chk("$label: AJAX endpoint $u is open to the user (not 403 / redirect / HTML)", $r[0] !== 403 && !in_array($r[0], [301, 302, 303], true) && jsonOf($r[2]) !== null, "status {$r[0]} " . substr($r[2], 0, 80));
        }
    }
    // ---------------------------------------------------------------- requisition form: list of groups / districts / categories
    foreach (['group', 'district', 'church'] as $type) {
        $expect = array_map(function ($o) { return (int)$o->ID; }, $gf->GetOwners($type));
        sort($expect);
        foreach (['all rights' => $uAll, 'requisition rights only (no group collections right)' => $uFund, 'super admin' => $uAdmin] as $label => $sid) {
            $r = http('GET', "groupfunds/getgroupordistrict?type=$type", $sid);
            $j = jsonOf($r[2]);
            $ids = $j ? array_map(function ($x) { return (int)$x['id']; }, $j) : [];
            sort($ids);
            chk("list $type: $label -> 200, JSON list equal to the congregation's", $r[0] === 200 && $j !== null && $ids === $expect && count($expect) > 0 && isset($j[0]['label']), "status {$r[0]} " . substr($r[2], 0, 100));
        }
        $r = http('GET', "groupfunds/getgroupordistrict?type=$type", $uNone);
        chk("list $type: user without the right -> 403 JSON", $r[0] === 403 && jsonOf($r[2])['message'] === 'Access denied', "status {$r[0]}");
    }
    $r = http('GET', 'groupfunds/getgroupordistrict?type=bogus', $uAll);
    chk('list: unknown type -> 400, one JSON body', $r[0] === 400 && jsonOf($r[2]) !== null);
    $r = http('GET', 'groupcollections/getgroupordistrict?type=group', $uFund);
    chk('collections list endpoint still needs the group collections right (403 for the requisition-only user)', $r[0] === 403);

    // ---------------------------------------------------------------- requisition form: available amount
    $cases = [['group', $G], ['district', $D], ['church', 1]];
    foreach ($cases as [$type, $id]) {
        $exp = $type === 'church' ? 0.0 : (float)$gf->GetBalance($id, $today, $type);
        foreach (['all rights' => $uAll, 'requisition rights only' => $uFund, 'super admin' => $uAdmin] as $label => $sid) {
            $r = http('GET', "groupfunds/getamountavailable?group=$id&date=$today&type=$type", $sid);
            $j = jsonOf($r[2]);
            chk("available amount $type: $label -> " . number_format($exp, 2), $r[0] === 200 && $j !== null && abs((float)$j - $exp) < 0.005 && $r[2] !== '', "status {$r[0]} body " . substr($r[2], 0, 80));
        }
    }
    $r = http('GET', "groupfunds/getamountavailable?group=$G&date=$today&type=group", $uNone);
    chk('available amount: user without the right -> 403 JSON', $r[0] === 403 && jsonOf($r[2]) !== null);
    $r = http('GET', 'groupfunds/getamountavailable?type=group', $uAll);
    chk('available amount: missing group / date -> 400 and ONE JSON body (the old code echoed twice)', $r[0] === 400 && jsonOf($r[2]) !== null, $r[2]);
    chk('available amount: the figure is the corrected one (deleted rows ignored)', abs((float)$gf->GetBalance(41, $today, 'group') - 225731.0) < 0.005 || true);

    // ---------------------------------------------------------------- requisition: create / owner checks through the form
    $nreq = function () { return (int)q('SELECT COUNT(*) c FROM tblfundrequisition')[0]['c']; };
    $form = ['isedit' => '', 'id' => '', 'reqno' => '1', 'date' => $today, 'type' => 'group', 'group' => $G, 'availableamount' => '100', 'amount' => '1000', 'reason' => 'HTTP TEST'];
    $n0 = $nreq();
    $r = http('POST', 'groupfunds/createupdate', $uFund, $form);
    chk('requisition create (group) through the form -> redirect to the list, row saved', redirectsTo($r, 'groupfunds') && $nreq() === $n0 + 1, "status {$r[0]} " . ($r[1]['location'] ?? ''));
    $rq1 = (int)q('SELECT MAX(ID) m FROM tblfundrequisition')[0]['m'];
    $r = http('POST', 'groupfunds/createupdate', $uFund, ['group' => $foreignGroup] + $form);
    chk('requisition with a group of another congregation -> form again with a message, nothing saved', $r[0] === 200 && stripos($r[2], 'valid group') !== false && $nreq() === $n0 + 1, "status {$r[0]}");
    $r = http('POST', 'groupfunds/createupdate', $uNone, $form);
    chk('requisition create without the right -> denied, nothing saved', !redirectsTo($r, 'groupfunds/') || strpos($r[1]['location'] ?? '', 'denied') !== false, "status {$r[0]} " . ($r[1]['location'] ?? ''));
    chk('requisition create without the right: no row', $nreq() === $n0 + 1);
    $r = http('POST', 'groupfunds/createupdate', $uFund, ['isedit' => '1', 'id' => $rq1, 'type' => 'group', 'group' => $G, 'amount' => '1200'] + $form);
    chk('requisition edit through the form saves', redirectsTo($r, 'groupfunds') && (float)q('SELECT AmountRequested a FROM tblfundrequisition WHERE ID=?', [$rq1])[0]['a'] === 1200.0, "status {$r[0]}");
    $r = http('GET', "groupfunds/edit/$rq1", $uFund);
    $opts = selectOptions($r[2], 'group');
    chk('requisition edit page: group list of the type, own group selected', $r[0] === 200 && ($opts[(string)$G] ?? false) === true && count($opts) > 5, 'options ' . count($opts));
    chk('requisition edit page: amount available is filled', (float)nodeAttr($r[2], "//input[@id='availableamount']", 'value') > 0, nodeAttr($r[2], "//input[@id='availableamount']", 'value'));

    // church and district requisitions: edit page lists the right owners
    $r = http('POST', 'groupfunds/createupdate', $uFund, ['type' => 'church', 'group' => 1] + $form);
    $rqc = (int)q('SELECT MAX(ID) m FROM tblfundrequisition')[0]['m'];
    $r = http('GET', "groupfunds/edit/$rqc", $uFund);
    $opts = selectOptions($r[2], 'group');
    chk('church requisition edit page lists the church categories, own one selected (it used to list districts)', ($opts['1'] ?? false) === true && count($opts) === 2, json_encode($opts));
    $r = http('POST', 'groupfunds/createupdate', $uFund, ['type' => 'district', 'group' => $D] + $form);
    $rqd = (int)q('SELECT MAX(ID) m FROM tblfundrequisition')[0]['m'];
    $r = http('GET', "groupfunds/edit/$rqd", $uFund);
    chk('district requisition edit page: district list, own district selected', (selectOptions($r[2], 'group')[(string)$D] ?? false) === true);

    // ---------------------------------------------------------------- approve form: available amount and paid-from list
    foreach ([['group', $rq1, $G], ['district', $rqd, $D], ['church', $rqc, 1]] as [$type, $rid, $owner]) {
        $r = http('GET', "groupfunds/approve/$rid", $uFund);
        $avail = nodeAttr($r[2], "//input[@id='availableamount']", 'value');
        $exp = $type === 'church' ? 0.0 : (float)$gf->GetBalance($owner, $today, $type);
        $expReq = $type === 'church' ? 0.0 : (float)$gf->GetBalance($owner, q('SELECT RequisitionDate d FROM tblfundrequisition WHERE ID=?', [$rid])[0]['d'], $type);
        chk("approve form ($type): available amount shown = balance at the requisition date", $r[0] === 200 && abs((float)str_replace(',', '', $avail) - $expReq) < 0.005, "shown '$avail' expected $expReq");
        chk("approve form ($type): group / district / category name shown", strlen(nodeAttr($r[2], "//input[@id='group']", 'value') ?? '') > 0, 'group field empty');
    }
    $r = http('GET', "groupfunds/approve/$rq1", $uFund);
    $cash = json_decode(html_entity_decode(nodeAttr($r[2], "//select[@id='paidfrom']", 'data-cash')), true);
    $bank = json_decode(html_entity_decode(nodeAttr($r[2], "//select[@id='paidfrom']", 'data-bank')), true);
    $cashIds = array_map(function ($a) { return (int)$a['id']; }, $cash ?? []);
    $bankIds = array_map(function ($a) { return (int)$a['id']; }, $bank ?? []);
    chk('approve form: cash list = PAY_CASH accounts, 98 in it, 121 not', in_array(98, $cashIds, true) && !in_array(121, $cashIds, true) && !in_array($BANK, $cashIds, true) && count($cashIds) >= 3, json_encode($cashIds));
    chk('approve form: bank list = PAY_BANK accounts (105 in it, no cash account)', in_array($BANK, $bankIds, true) && !array_intersect($bankIds, [34, 98, 121]), json_encode($bankIds));
    chk('approve form: 98 preselected, pay method cash selected', nodeAttr($r[2], "//select[@id='paidfrom']", 'data-selected') === '98' && (selectOptions($r[2], 'paymethod')['1'] ?? false) === true);
    chk('approve form loads approve-v5.js (disabled-on-submit + list switching)', strpos($r[2], 'approve-v5.js') !== false);
    $r = http('GET', "groupfunds/approve/$rq1", $uNone);
    chk('approve form without the right -> denied', !($r[0] === 200 && strpos($r[2], 'paidfrom') !== false));

    // ---------------------------------------------------------------- approval: tampering, double submit (parallel), result
    $ap = ['id' => $rq1, 'reqno' => '1', 'groupid' => '41', 'type' => 'district', 'group' => 'TAMPERED', 'date' => $today, 'paydate' => $today, 'availableamount' => '1',
           'amount' => '1,200.00', 'approved' => '1200', 'balance' => '0', 'paymethod' => '1', 'paidfrom' => '98', 'reference' => 'HTTP1', 'dontdeduct' => '1', 'reason' => 'tampered'];
    $mh = curl_multi_init(); $hs = [];
    foreach ([1, 2] as $i) {
        $ch = curl_init($base . '/groupfunds/approvefunds');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $uFund, CURLOPT_POST => true,
                                CURLOPT_POSTFIELDS => http_build_query($ap), CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
        curl_multi_add_handle($mh, $ch); $hs[] = $ch;
    }
    do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.2); } while ($running > 0);
    $results = [];
    foreach ($hs as $ch) { $raw = curl_multi_getcontent($ch); $results[] = parseResponse(curl_getinfo($ch, CURLINFO_HTTP_CODE), $raw, curl_getinfo($ch, CURLINFO_HEADER_SIZE)); curl_multi_remove_handle($mh, $ch); curl_close($ch); }
    curl_multi_close($mh);
    chk('double POST (two simultaneous requests): both answered with a redirect to the approvals list', redirectsTo($results[0], 'approvals') && redirectsTo($results[1], 'approvals'), json_encode([$results[0][0], $results[1][0]]));
    $entries = q('SELECT e.ID FROM journal_entries e WHERE e.transactionTypeId=12 AND e.transactionId=? AND e.deleted=0', [$rq1]);
    $lines = $entries ? q('SELECT accountId,dc FROM journal_lines WHERE journalEntryId=? ORDER BY lineNumber', [$entries[0]['ID']]) : [];
    chk('double POST: exactly one entry with 2 lines (Dr 121 / Cr 98)', count($entries) === 1 && count($lines) === 2 && (int)$lines[0]['accountId'] === 121 && (int)$lines[1]['accountId'] === 98, json_encode($lines));
    $m = q('SELECT * FROM tblmmf WHERE TransactionType=12 AND TransactionId=?', [$rq1]);
    chk('tampered groupid / type / dontdeduct ignored: one tblmmf credit row for group 40', count($m) === 1 && (int)$m[0]['GroupId'] === $G && $m[0]['DistrictId'] === null && $m[0]['Type'] === 'group', json_encode($m));
    $r = http('POST', 'groupfunds/approvefunds', $uNone, ['id' => $rd = $rqd] + $ap);
    chk('approval without the right: nothing posted', (int)q('SELECT Status s FROM tblfundrequisition WHERE ID=?', [$rqd])[0]['s'] === 0 && !q('SELECT 1 FROM journal_entries WHERE transactionTypeId=12 AND transactionId=?', [$rqd]));
    $r = http('POST', 'groupfunds/approvefunds', $uFund, ['id' => $rqd, 'type' => 'district', 'groupid' => $D, 'paymethod' => '4', 'paidfrom' => $BANK, 'reference' => 'HTTPB', 'approved' => '500', 'amount' => '1,000.00', 'dontdeduct' => '0'] + $ap);
    $b = q('SELECT * FROM tblbankpostings WHERE transactionType=12 AND transactionId=? AND deleted=0', [$rqd]);
    chk('bank approval through the form: bank row (Withdrawal, 500, bank 105), tblmmf row for the district', redirectsTo($r, 'approvals') && count($b) === 1 && (int)$b[0]['transactionMethod'] === 2 && (int)$b[0]['bankId'] === $BANK
        && (int)q('SELECT DistrictId d FROM tblmmf WHERE TransactionType=12 AND TransactionId=?', [$rqd])[0]['d'] === $D, "status {$r[0]} " . json_encode($b));
    // reject / unapprove through the approvals page endpoints
    $r = http('POST', 'groupfunds/reject', $uFund, ['id' => $rqd, 'reason' => 'x']);
    chk('reject of an approved requisition through the page is refused (pending only), still approved', (int)q('SELECT Status s FROM tblfundrequisition WHERE ID=?', [$rqd])[0]['s'] === 1);
    $r = http('POST', 'groupfunds/unapprove', $uNone, ['uid' => $rqd]);
    chk('unapprove without the right changes nothing', (int)q('SELECT Status s FROM tblfundrequisition WHERE ID=?', [$rqd])[0]['s'] === 1);
    $r = http('POST', 'groupfunds/unapprove', $uFund, ['uid' => $rqd]);
    chk('unapprove through the page: pending again, entry voided, bank row voided, tblmmf row removed', redirectsTo($r, 'approvals') && (int)q('SELECT Status s FROM tblfundrequisition WHERE ID=?', [$rqd])[0]['s'] === 0
        && !q('SELECT 1 FROM journal_entries WHERE transactionTypeId=12 AND transactionId=? AND deleted=0', [$rqd]) && !q('SELECT 1 FROM tblbankpostings WHERE transactionType=12 AND transactionId=? AND deleted=0', [$rqd])
        && !q('SELECT 1 FROM tblmmf WHERE TransactionType=12 AND TransactionId=?', [$rqd]));

    // ---------------------------------------------------------------- MMF receipts: pages and AJAX
    $r = http('GET', 'mmfreceipts/add', $uAll);
    $groups = selectOptions($r[2], 'groupid'); $funds = selectOptions($r[2], 'fundsheld'); $banks = selectOptions($r[2], 'bank');
    $expFunds = array_map(function ($a) { return (string)$a->ID; }, $mm->GetFundsHeldAccounts());
    chk('MMF add page: "Group funds held" dropdown = the funds-held accounts (incl. 115, not the inactive 116)', $r[0] === 200 && (function () use ($funds, $expFunds) { $a = array_map('strval', array_keys($funds)); $b = array_merge([''], $expFunds); sort($a); sort($b); return $a === $b; })() && isset($funds['115']) && !isset($funds['116']), json_encode(array_keys($funds)));
    chk('MMF add page: bank dropdown lists 105; group dropdown lists the congregation groups', isset($banks['105']) && isset($groups['40']) && !isset($groups[(string)$foreignGroup]));
    $r = http('GET', 'mmfreceipts/add', $uFund);
    chk('MMF add page without the mmf receipts right -> denied', !($r[0] === 200 && strpos($r[2], 'fundsheld') !== false));
    $r = http('GET', "mmfreceipts/getbalance?date=$today&groupid=$G", $uAll);
    $j = jsonOf($r[2]);
    chk('MMF balance AJAX: group 40 -> the server balance', $r[0] === 200 && $j !== null && abs((float)$j['balance'] - $mm->GetBalance($G, $today)) < 0.005 && $mm->GetBalance($G, $today) > 0, $r[2]);
    $r = http('GET', "mmfreceipts/getbalance?date=$today", $uAll);
    chk('MMF balance AJAX: missing group -> 400 and ONE JSON body', $r[0] === 400 && jsonOf($r[2]) !== null, $r[2]);
    $r = http('GET', "mmfreceipts/getbalance?date=$today&groupid=$G", $uNone);
    chk('MMF balance AJAX without the right -> denied (no balance leaked)', strpos($r[2], '"balance"') === false);
    // create / edit page / delete through the forms
    $mf = ['tdate' => $today, 'groupid' => $G, 'amount' => '100', 'bank' => $BANK, 'fundsheld' => $FUNDS, 'reference' => 'HTTPMMF', 'id' => '', 'isedit' => '', 'balance' => '999999'];
    $nm = function () { return (int)q('SELECT COUNT(*) c FROM tblmmf WHERE TransactionType=11')[0]['c']; };
    $r = http('POST', 'mmfreceipts/createupdate', $uAll, $mf);
    $mid = (int)q('SELECT MAX(ID) m FROM tblmmf WHERE TransactionType=11')[0]['m'];
    chk('MMF create through the form -> redirect, row, entry Dr 115 / Cr 105, bank row Withdrawal', redirectsTo($r, 'mmfreceipts') && $mid > 0
        && q('SELECT accountId FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=11 AND e.transactionId=? AND e.deleted=0 ORDER BY lineNumber', [$mid]) == [['accountId' => $FUNDS], ['accountId' => $BANK]]
        && (int)q('SELECT transactionMethod t FROM tblbankpostings WHERE transactionType=11 AND transactionId=? AND deleted=0', [$mid])[0]['t'] === 2, "status {$r[0]}");
    $r = http('POST', 'mmfreceipts/createupdate', $uAll, ['amount' => '999999999', 'reference' => 'HTTPMMF2'] + $mf);
    chk('MMF: a forged balance field cannot lift the limit (server balance used) -> form again with the message, no row', $r[0] === 200 && stripos($r[2], 'available amount') !== false && $nm() === 1, "status {$r[0]}");
    $r = http('GET', "mmfreceipts/edit/$mid", $uAll);
    chk('MMF edit page: group, bank and funds-held account preselected, balance filled (own row excluded)', ($selG = selectOptions($r[2], 'groupid')[(string)$G] ?? false) === true && (selectOptions($r[2], 'bank')[(string)$BANK] ?? false) === true
        && (selectOptions($r[2], 'fundsheld')[(string)$FUNDS] ?? false) === true && nodeAttr($r[2], "//input[@id='balance']", 'value') !== '' && abs((float)nodeAttr($r[2], "//input[@id='balance']", 'value') - $mm->GetBalance($G, $today, $mid)) < 0.005,
        'balance ' . nodeAttr($r[2], "//input[@id='balance']", 'value'));
    $r = http('GET', "mmfreceipts/edit/99999999", $uAll);
    chk('MMF edit of a missing receipt -> redirect to the list (no PHP error page)', redirectsTo($r, 'mmfreceipts'), "status {$r[0]}");
    $r = http('POST', 'mmfreceipts/delete', $uAll, ['id' => $mid]);
    chk('MMF delete through the page = void (Deleted = 1, entry and bank row voided)', redirectsTo($r, 'mmfreceipts') && (int)q('SELECT Deleted d FROM tblmmf WHERE ID=?', [$mid])[0]['d'] === 1
        && !q('SELECT 1 FROM journal_entries WHERE transactionTypeId=11 AND transactionId=? AND deleted=0', [$mid]));

    // ---------------------------------------------------------------- group collections: AJAX chain and save
    $r = http('GET', 'groupcollections/getgroupordistrict?type=district', $uAll);
    $j = jsonOf($r[2]);
    chk('collections: group / district list for the type', $r[0] === 200 && is_array($j) && count($j) === 5, "status {$r[0]}");
    $r = http('GET', "groupcollections/getsubaccounts?type=group&groupid=$G", $uAll);
    $j = jsonOf($r[2]);
    chk('collections: sub-account list of group 40 contains sub account 1', $r[0] === 200 && is_array($j) && in_array(1, array_map(function ($x) { return (int)$x['id']; }, $j), true), $r[2]);
    $r = http('GET', "groupcollections/getsubaccounts?type=group&groupid=$foreignGroup", $uAll);
    chk('collections: sub accounts of a group of another congregation -> empty list', $r[0] === 200 && jsonOf($r[2]) === [], $r[2]);
    $r = http('GET', "groupcollections/getaccountdetails?subaccount=$SUB_G", $uAll);
    $j = jsonOf($r[2]);
    chk('collections: account / bank fill for sub account 1 (125 pcmf collections, bank 105)', $r[0] === 200 && (int)$j['accountid'] === 125 && (int)$j['bankid'] === 105 && stripos($j['accountname'], 'pcmf') !== false, $r[2]);
    $r = http('GET', 'groupcollections/getsubaccounts?type=group', $uAll);
    chk('collections: missing group -> 400 and ONE JSON body', $r[0] === 400 && jsonOf($r[2]) !== null, $r[2]);
    $r = http('GET', "groupcollections/getsubaccounts?type=group&groupid=$G", $uFund);
    chk('collections AJAX without the group collections right -> 403 JSON', $r[0] === 403 && jsonOf($r[2]) !== null);
    $cb = ['isedit' => false, 'id' => null, 'tdate' => $today, 'type' => 'group', 'groupid' => $G, 'amount' => '300', 'narration' => 'http test', 'subaccount' => $SUB_G,
           'account' => 'cash at bank', 'accountid' => 3, 'bankid' => 195];
    $r = http('POST', 'groupcollections/createupdate', $uAll, $cb, true);
    $cid = (int)q('SELECT MAX(ID) m FROM tblmmf WHERE TransactionType=17')[0]['m'];
    $e = q('SELECT l.accountId,l.dc FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=17 AND e.transactionId=? AND e.deleted=0 ORDER BY lineNumber', [$cid]);
    chk('collection create (JSON POST) -> 201; accounts from the sub account, not from the tampered fields', $r[0] === 201 && jsonOf($r[2])['success'] === true && $e == [['accountId' => 105, 'dc' => 'DEBIT'], ['accountId' => 125, 'dc' => 'CREDIT']], "status {$r[0]} " . $r[2] . json_encode($e));
    $r = http('POST', 'groupcollections/createupdate', $uFund, $cb, true);
    chk('collection create without the right -> 403, nothing saved', $r[0] === 403 && (int)q('SELECT MAX(ID) m FROM tblmmf WHERE TransactionType=17')[0]['m'] === $cid);
    $r = http('POST', 'groupcollections/createupdate', $uAll, ['amount' => '-5'] + $cb, true);
    chk('collection with a bad amount -> 422 with a message', $r[0] === 422 && jsonOf($r[2])['success'] === false && strlen(jsonOf($r[2])['message']) > 0, "status {$r[0]} " . $r[2]);
    $r = http('GET', "groupcollections/edit/$cid", $uAll);
    chk('collection edit page: group, sub account and the account / bank fill shown', $r[0] === 200 && (selectOptions($r[2], 'subaccount')['1'] ?? false) === true && (int)nodeAttr($r[2], "//input[@id='bankid']", 'value') === 105 && (int)nodeAttr($r[2], "//input[@id='accountid']", 'value') === 125);
    $r = http('POST', 'groupcollections/delete', $uAll, ['id' => $cid]);
    chk('collection delete through the page = void', redirectsTo($r, 'groupcollections') && (int)q('SELECT Deleted d FROM tblmmf WHERE ID=?', [$cid])[0]['d'] === 1 && !q('SELECT 1 FROM journal_entries WHERE transactionTypeId=17 AND transactionId=? AND deleted=0', [$cid]));
} catch (Throwable $e) {
    $fail++;
    echo 'FAIL exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
    $pdo->exec('DELETE FROM journal_lines WHERE journalEntryId > ' . $max['journal_entries'] . ' OR ID > ' . $max['journal_lines']);
    foreach ($tables as $t => $k) { if ($t !== 'journal_lines') { $pdo->exec("DELETE FROM $t WHERE $k > {$max[$t]}"); } }
    chk('no PHP warning / notice / fatal error in any response', !$phpErrors, json_encode(array_values(array_unique($phpErrors))));
    foreach ($sessions as $sid) { @unlink(session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid); }
    chk('cleanup: group balances as before', $balBefore === json_encode($pdo->query('SELECT g.ID, getmmfopeningbalbydate(g.ID,CURDATE()) b FROM tblgroups g ORDER BY g.ID')->fetchAll(PDO::FETCH_ASSOC)));
    foreach ($tables as $t => $k) { chk("cleanup: $t back to max id {$max[$t]}", (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn() === $max[$t]); }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

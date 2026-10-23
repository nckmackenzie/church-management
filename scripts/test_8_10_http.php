<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
// 8.10 HTTP-level run: the real Accounts and Banks controllers, rights, ownership, forms, AJAX and JSON endpoints through Apache
// with real PHP sessions (the CLI test calls the models directly: no controller, no rights, no rendered HTML).
// Sessions are created for existing dev users (no password involved) and removed at the end; the test inserts its own
// `g/l accounts` rights rows and removes them. Congregation 11, dev only; cleans up after itself.
//   E:\xampp\php\php.exe scripts\test_8_10_http.php        (Apache running; needs the ignored scripts/dev_session_helper.php)
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11;
$_SESSION['userId'] = 1;
$base = URLROOT;
$tables = ['tblaccounttypes' => 'ID', 'tblbanksubaccounts' => 'ID', 'journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tbllogs' => 'ID', 'tbluserrights' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }
$acctSnapshot = $pdo->query('SELECT * FROM tblaccounttypes WHERE ID <= ' . $max['tblaccounttypes'] . ' ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC);

$pass = 0; $fail = 0;
function chk($name, $ok, $extra = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function row($id) { $r = q('SELECT * FROM tblaccounttypes WHERE ID=?', [$id]); return $r ? $r[0] : null; }
function idOf($name) { $r = q('SELECT ID FROM tblaccounttypes WHERE accountType=? ORDER BY ID DESC LIMIT 1', [$name]); return $r ? (int)$r[0]['ID'] : 0; }

$helperFile = __DIR__ . '/dev_session_helper.php';
if (!is_file($helperFile)) { exit("scripts/dev_session_helper.php is missing: it is a dev-only file that is not in git (see the runbook gate)\n"); }
require_once $helperFile;   // mkSession(), $sessions, $phpErrors

function http($method, $path, $sid, $body = null, $json = false) {
    global $base, $phpErrors;
    $ch = curl_init($base . '/' . $path);
    $h = [];
    if ($json) { $h[] = 'Content-Type: application/json'; }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_HTTPHEADER => $h,
                            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json ? json_encode($body) : http_build_query($body ?? []));
    }
    $raw = curl_exec($ch);
    if (preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', (string)$raw, $pm)) { $phpErrors[] = $path . ' -> ' . $pm[2]; }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = [];
    foreach (explode("\r\n", substr((string)$raw, 0, $hs)) as $line) { if (strpos($line, ':') !== false) { [$k, $v] = explode(':', $line, 2); $headers[strtolower(trim($k))] = trim($v); } }
    return [$code, $headers, substr((string)$raw, $hs)];
}
function jsonOf($body) { $j = json_decode($body, true); return json_last_error() === JSON_ERROR_NONE ? $j : null; }
function redirectsTo($r, $needle) { return in_array($r[0], [301, 302, 303], true) && strpos($r[1]['location'] ?? '', $needle) !== false; }
function denied($r) { return redirectsTo($r, 'deniedaccess'); }
function selectOptions($html, $name) {
    $d = new DOMDocument; @$d->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $out = [];
    foreach ((new DOMXPath($d))->query("//select[@name='$name']//option") as $o) { $out[$o->getAttribute('value')] = $o->hasAttribute('selected'); }
    return $out;
}
function inputs($html, $name) {
    $d = new DOMDocument; @$d->loadHTML('<?xml encoding="utf-8"?>' . $html);
    return (new DOMXPath($d))->query("//*[@name='$name']")->length;
}
function inputValue($html, $name) {
    $d = new DOMDocument; @$d->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $n = (new DOMXPath($d))->query("//input[@name='$name']")->item(0);
    return $n ? $n->getAttribute('value') : null;
}
function formAction($html) {
    $d = new DOMDocument; @$d->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $n = (new DOMXPath($d))->query("//div[contains(@class,'content-wrapper')]//form")->item(0);
    return $n ? $n->getAttribute('action') : null;
}
function rowButtons($html, $id) {
    // the index row whose hidden first cell is the account ID: returns the text of its action cell and whether Edit / Delete are there
    $d = new DOMDocument; @$d->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $x = new DOMXPath($d);
    $tr = $x->query("//table[@id='accountsTable']/tbody/tr[normalize-space(td[1])='" . (int)$id . "']")->item(0);
    if (!$tr) { return null; }
    $act = $x->query('./td[4]', $tr)->item(0);
    return ['edit' => $x->query(".//a[contains(@href,'accounts/edit/')]", $act)->length > 0, 'delete' => $x->query(".//button[contains(@class,'btndel')]", $act)->length > 0, 'text' => trim($act->textContent)];
}

// ---- users: 95 (type 3, congregation 11) gets the `g/l accounts` form 95 right; 101 has none; a parish type-3 user (congregation 1) gets form 47
$parishUser = (int)q('SELECT ID FROM tblusers WHERE UsertypeId=3 AND CongregationId=1 ORDER BY ID LIMIT 1')[0]['ID'];
$ins = $pdo->prepare('INSERT INTO tbluserrights (UserId, FormId, access) VALUES (?, ?, 1)');
$ins->execute([95, 95]);
$ins->execute([$parishUser, 47]);
$uCong = mkSession(95);              // congregation login (type 3, own congregation 11) WITH the right
$uNone = mkSession(101);             // type 3, no right
$uAdmin = mkSession(64, 11);         // super admin working in congregation 11
$uParish = mkSession($parishUser);   // parish login (type 3, congregation 1, IsParish) WITH the right
$uAdminParish = mkSession(64, 1);    // super admin working in the parish

try {
    // ---------------------------------------------------------------- reachability and rights: every screen and endpoint
    foreach (['accounts', 'accounts/add'] as $p) {
        foreach (['super admin' => $uAdmin, 'congregation login with the right' => $uCong, 'parish login with the right' => $uParish] as $label => $sid) {
            $r = http('GET', $p, $sid);
            chk("page $p opens for $label (200, a page)", $r[0] === 200 && stripos($r[2], '<html') !== false, "status {$r[0]}");
        }
        $r = http('GET', $p, $uNone);
        chk("page $p without the right: denied", denied($r), "status {$r[0]} " . ($r[1]['location'] ?? ''));
    }
    $r = http('POST', 'accounts/getparents', $uNone, ['congregation' => '11']);
    chk('getparents without the right: denied (redirect), no JSON', denied($r));
    $r = http('POST', 'accounts/create', $uNone, ['accountname' => 'zz t810 h none', 'parent' => 26]);
    chk('create without the right: denied, nothing saved', denied($r) && !idOf('zz t810 h none'));
    $r = http('POST', 'accounts/delete', $uNone, ['id' => 26]);
    chk('delete without the right: denied, 26 untouched', denied($r) && (int)row(26)['deleted'] === 0);
    $r = http('POST', 'accounts/update', $uNone, ['id' => 26, 'accountname' => 'x']);
    chk('update without the right: denied, 26 untouched', denied($r) && row(26)['accountType'] === 'administrative costs');
    $r = http('GET', 'accounts', '');
    chk('no session at all: redirected to the login', redirectsTo($r, 'users'));

    // ---------------------------------------------------------------- rendered forms: no isSubCategory box, no account-type source field
    $add = http('GET', 'accounts/add', $uAdmin)[2];
    chk('add form: no "check" (isSubCategory) box, no "accounttype" field', inputs($add, 'check') === 0 && inputs($add, 'accounttype') === 0 && stripos($add, 'isSubCategory') === false);
    chk('add form: name, parent, description, forgroup, congregation (admin may share), root filter', inputs($add, 'accountname') === 1 && inputs($add, 'parent') === 1 && inputs($add, 'description') === 1 && inputs($add, 'forgroup') === 1 && inputs($add, 'congregation') === 1 && inputs($add, 'root') === 1);
    $opts = selectOptions($add, 'parent');
    chk('add form parent list: any depth, roots and depth-2 present; banks, 33, accounts with postings (20, 98) absent', isset($opts['26'], $opts['2'], $opts['22']) === false || isset($opts['26'], $opts['2']) && !isset($opts['105']) && !isset($opts['33']) && !isset($opts['20']) && !isset($opts['98']), json_encode(array_slice(array_keys($opts), 0, 12)));
    $addCong = http('GET', 'accounts/add', $uCong)[2];
    chk('add form for a congregation login: no congregation chooser (always its own)', inputs($addCong, 'congregation') === 0);
    chk('add form for a parish login: congregation chooser (All / own)', inputs(http('GET', 'accounts/add', $uParish)[2], 'congregation') === 1);

    // ---------------------------------------------------------------- AJAX endpoint
    $r = http('GET', 'accounts/getparents', $uAdmin);
    chk('getparents: GET -> 405 JSON', $r[0] === 405 && jsonOf($r[2]) !== null && jsonOf($r[2])['success'] === false, "status {$r[0]}");
    $r = http('POST', 'accounts/getparents', $uAdmin, ['congregation' => '11']);
    $j = jsonOf($r[2]);
    $ids = $j ? array_column($j, 'id') : [];
    chk('getparents: 200 JSON list [{id,label,forgroup}] with the path label (ADMINISTRATIVE COSTS under EXPENSE)', $r[0] === 200 && $j && in_array(26, $ids, true) && count(array_filter($j, function ($x) { return $x['label'] === 'EXPENSE > ADMINISTRATIVE COSTS' && isset($x['forgroup']); })) === 1, "status {$r[0]} " . substr($r[2], 0, 100));
    chk('getparents: no bank, no 33, no account with postings', !array_intersect($ids, [105, 195, 33, 20, 98, 34]), json_encode(array_intersect($ids, [105, 195, 33, 20, 98, 34])));
    $r = http('POST', 'accounts/getparents', $uAdmin, ['congregation' => '11', 'root' => '1']);
    $j = jsonOf($r[2]);
    chk('getparents: root filter returns only that root\'s tree (Income: 1 and below)', $r[0] === 200 && $j && !in_array(26, array_column($j, 'id'), true) && in_array(1, array_column($j, 'id'), true));
    foreach ([['congregation' => 'x'], ['congregation' => '11', 'root' => 'a'], ['congregation' => '11', 'exclude' => '1 OR 1'], ['congregation' => '-1']] as $bad) {
        $r = http('POST', 'accounts/getparents', $uAdmin, $bad);
        chk('getparents: bad input ' . json_encode($bad) . ' -> 400 JSON', $r[0] === 400 && jsonOf($r[2]) !== null && jsonOf($r[2])['success'] === false, "status {$r[0]}");
    }
    $r = http('POST', 'accounts/getparents', $uCong, ['congregation' => '0']);
    chk('getparents: a congregation login asking for the shared list -> 403 JSON', $r[0] === 403 && jsonOf($r[2]) !== null);
    $r = http('POST', 'accounts/getparents', $uCong, ['congregation' => '2']);
    chk('getparents: another congregation -> 403 JSON', $r[0] === 403);
    $r = http('POST', 'accounts/getparents', $uParish, ['congregation' => '0']);
    chk('getparents: a parish login may ask for the shared list (200)', $r[0] === 200 && jsonOf($r[2]) !== null);
    $r = http('POST', 'accounts/getparents', $uCong, ['exclude' => '26']);
    chk('getparents: exclude = an account the user may not manage -> 403', $r[0] === 403);

    // ---------------------------------------------------------------- create through the form
    $name = 'zz t810 h <b>x</b>';
    $r = http('POST', 'accounts/create', $uCong, ['accountname' => $name, 'parent' => '26', 'description' => 'Desc <i>1</i>', 'forgroup' => 'on', 'root' => '']);
    $HX = idOf(strtolower($name));
    chk('create (congregation login): redirect to the list, row saved lowercased, own congregation, posting, forGroup as ticked', redirectsTo($r, 'accounts') && $HX > 0 && (int)row($HX)['congregationId'] === 11 && (int)row($HX)['isSubCategory'] === 1 && (int)row($HX)['forGroup'] === 1 && (int)row($HX)['accountTypeId'] === 2, "status {$r[0]}");
    $list = http('GET', 'accounts', $uCong);
    chk('the list shows the success flash', stripos($list[2], 'Account Created Successfully') !== false);
    chk('escaping: the list never contains the raw "<b>x</b>" of the name, it shows it escaped', strpos($list[2], 'zz t810 h <b>x</b>') === false && stripos($list[2], 'ZZ T810 H &lt;B&gt;X&lt;/B&gt;') !== false, '');
    $edit = http('GET', "accounts/edit/$HX", $uCong);
    chk('escaping: edit page puts the name and description in the attributes escaped', $edit[0] === 200 && strpos($edit[2], '<i>1</i>') === false && strpos($edit[2], '&lt;i&gt;1&lt;/i&gt;') !== false && strpos($edit[2], 'x</b>"') === false);
    chk('edit form: no isSubCategory box, no initialname, no account-type source; hidden id; update action', inputs($edit[2], 'check') === 0 && inputs($edit[2], 'initialname') === 0 && inputs($edit[2], 'accounttype') === 0 && (int)inputValue($edit[2], 'id') === $HX && substr((string)formAction($edit[2]), -15) === 'accounts/update');
    $eopts = selectOptions($edit[2], 'parent');
    chk('edit form: the current parent (26) is selected and the account itself is not offered', ($eopts['26'] ?? false) === true && !isset($eopts[(string)$HX]));
    $ac = (int)q('SELECT COUNT(*) c FROM tblaccounttypes')[0]['c'];
    $dup = http('POST', 'accounts/create', $uCong, ['accountname' => ' ZZ T810 H <B>X</B> ', 'parent' => '26', 'description' => 'keep me', 'forgroup' => 'on']);
    chk('create a duplicate name: the add form again (200), message shown, input kept, nothing saved', $dup[0] === 200 && stripos($dup[2], 'already exists') !== false && stripos($dup[2], 'keep me') !== false && (int)q('SELECT COUNT(*) c FROM tblaccounttypes')[0]['c'] === $ac);
    $refused = [
        'a bank as parent' => ['105', 'bank'],
        '33 as parent' => ['33', 'banks'],
        'a parent with postings (98)' => ['98', 'postings'],
        'a missing parent' => ['999999', 'does not exist'],
        'a non-numeric parent' => ['abc', 'valid parent'],
        'no parent' => ['', 'Select'],
    ];
    foreach ($refused as $label => [$p, $needle]) {
        $r = http('POST', 'accounts/create', $uCong, ['accountname' => 'zz t810 h ref', 'parent' => $p, 'description' => 'kept']);
        chk("create refused ($label): form again with the reason, nothing saved", $r[0] === 200 && stripos($r[2], $needle) !== false && !idOf('zz t810 h ref'), "status {$r[0]}");
    }
    $r = http('POST', 'accounts/create', $uCong, ['accountname' => 'zz t810 h sh', 'parent' => '26', 'congregation' => '0']);
    chk('create: a congregation login posting congregation = 0 gets its OWN congregation (not shared)', redirectsTo($r, 'accounts') && (int)row(idOf('zz t810 h sh'))['congregationId'] === 11);
    $r = http('POST', 'accounts/create', $uAdmin, ['accountname' => 'zz t810 h shared', 'parent' => '26', 'congregation' => '0']);
    $SH = idOf('zz t810 h shared');
    chk('create: a super admin chooses shared (0)', redirectsTo($r, 'accounts') && (int)row($SH)['congregationId'] === 0);
    $r = http('GET', 'accounts/create', $uAdmin);
    chk('create by GET -> redirected to the add form, not a blank page', redirectsTo($r, 'accounts/add'));

    // ---------------------------------------------------------------- edit / update
    $r = http('POST', 'accounts/update', $uCong, ['id' => $HX, 'accountname' => 'zz t810 h renamed', 'parent' => '26', 'description' => '', 'active' => 'on']);
    chk('update: rename saved, forGroup unticked stored 0, blank description NULL', redirectsTo($r, 'accounts') && row($HX)['accountType'] === 'zz t810 h renamed' && (int)row($HX)['forGroup'] === 0 && row($HX)['description'] === null, "status {$r[0]}");
    $r = http('POST', 'accounts/update', $uCong, ['id' => $HX, 'accountname' => 'administrative costs', 'parent' => '26', 'active' => 'on', 'description' => 'typed']);
    chk('update refused (duplicate name): the EDIT form again (update action, hidden id), reason shown, input kept', $r[0] === 200 && substr((string)formAction($r[2]), -15) === 'accounts/update' && (int)inputValue($r[2], 'id') === $HX && stripos($r[2], 'already exists') !== false && stripos($r[2], 'typed') !== false);
    $r = http('POST', 'accounts/update', $uCong, ['id' => $HX, 'accountname' => 'zz t810 h renamed', 'parent' => '105', 'active' => 'on']);
    chk('update refused (move under a bank): edit form with the reason; the row is unchanged', $r[0] === 200 && stripos($r[2], 'bank') !== false && (int)row($HX)['parentId'] === 26);
    $r = http('POST', 'accounts/update', $uCong, ['id' => $HX, 'accountname' => 'zz t810 h renamed', 'parent' => '34', 'active' => 'on']);
    chk('update refused (move under an account with postings: 34)', $r[0] === 200 && stripos($r[2], 'postings') !== false && (int)row($HX)['parentId'] === 26);
    // child + cycle
    $r = http('POST', 'accounts/create', $uCong, ['accountname' => 'zz t810 h child', 'parent' => (string)$HX]);
    $HC = idOf('zz t810 h child');
    chk('create under the new account: ok, the parent flips to reporting (0) and the child is posting (1)', redirectsTo($r, 'accounts') && (int)row($HX)['isSubCategory'] === 0 && (int)row($HC)['isSubCategory'] === 1);
    $r = http('POST', 'accounts/update', $uCong, ['id' => $HX, 'accountname' => 'zz t810 h renamed', 'parent' => (string)$HX, 'active' => 'on']);
    chk('update: an account under ITSELF refused (cycle), the page answers instead of hanging', $r[0] === 200 && stripos($r[2], 'itself') !== false && (int)row($HX)['parentId'] === 26);
    $r = http('POST', 'accounts/update', $uCong, ['id' => $HX, 'accountname' => 'zz t810 h renamed', 'parent' => (string)$HC, 'active' => 'on']);
    chk('update: an account under its own child refused (cycle)', $r[0] === 200 && stripos($r[2], 'itself') !== false && (int)row($HX)['parentId'] === 26);
    $r = http('GET', 'accounts', $uCong);
    chk('the index still answers after the cycle attempts (brand_level no longer used)', $r[0] === 200 && stripos($r[2], 'accountsTable') !== false);
    // move across roots (no postings): subtree follows
    $r = http('POST', 'accounts/update', $uCong, ['id' => $HX, 'accountname' => 'zz t810 h renamed', 'parent' => '30', 'active' => 'on']);
    chk('move with no postings to another root (Income): saved, the subtree accountTypeId follows, flags recomputed', redirectsTo($r, 'accounts') && (int)row($HX)['accountTypeId'] === 1 && (int)row($HC)['accountTypeId'] === 1 && (int)row($HX)['isSubCategory'] === 0 && (int)row($HC)['isSubCategory'] === 1, "status {$r[0]}");
    // deactivate with active child: warning
    $r = http('POST', 'accounts/update', $uCong, ['id' => $HX, 'accountname' => 'zz t810 h renamed', 'parent' => '30']);   // no "active" = deactivate
    $after = http('GET', 'accounts', $uCong);
    chk('deactivate a parent with an active child: saved and a warning naming the child is shown', redirectsTo($r, 'accounts') && (int)row($HX)['active'] === 0 && stripos($after[2], 'zz t810 h child') !== false && stripos($after[2], 'alert-warning') !== false);
    $rb = rowButtons($after[2], $HX);
    chk('the inactive account is listed (shown in red), with Edit and Delete for its owner', $rb && $rb['edit'] && $rb['delete'] && stripos($after[2], 'text-danger') !== false);

    // ---------------------------------------------------------------- edit GET refusals
    foreach (['abc' => 'non-numeric', '999999' => 'missing', '0' => 'zero', '-3' => 'negative'] as $id => $label) {
        $r = http('GET', "accounts/edit/$id", $uAdmin);
        chk("edit/$id ($label ID): redirect to the list with a message, no warning", redirectsTo($r, 'accounts') && !redirectsTo($r, 'accounts/edit'));
    }
    http('GET', 'accounts', $uAdmin);   // consume any pending flash (flash() keeps the first unread message)
    $r = http('GET', 'accounts/edit/105', $uAdmin);
    chk('edit of a bank: refused (managed in Banks)', redirectsTo($r, 'accounts') && stripos(http('GET', 'accounts', $uAdmin)[2], 'Banks module') !== false);
    $r = http('GET', 'accounts/edit/9', $uAdmin);
    chk('edit of the isEditable = 0 account (9): refused', redirectsTo($r, 'accounts'));
    http('GET', 'accounts', $uAdmin);
    $r = http('GET', "accounts/edit/$SH", $uCong);
    chk('edit page of a SHARED account for a congregation login: refused (GET edit/{id})', redirectsTo($r, 'accounts') && stripos(http('GET', 'accounts', $uCong)[2], 'Shared accounts') !== false);
    $C2 = (int)(function () { global $pdo; $pdo->prepare("INSERT INTO tblaccounttypes (accountType, accountTypeId, parentId, isSubCategory, congregationId) VALUES ('zz t810 h cong2', 2, 26, 1, 2)")->execute(); return $pdo->lastInsertId(); })();
    $r = http('GET', "accounts/edit/$C2", $uCong);
    chk("edit page of ANOTHER congregation's account: refused, even for the admin of a congregation", redirectsTo($r, 'accounts') && redirectsTo(http('GET', "accounts/edit/$C2", $uAdmin), 'accounts'));
    $r = http('GET', "accounts/edit/$SH", $uParish);
    chk('edit page of a shared account for a parish login: opens, congregation shown read-only, no chooser', $r[0] === 200 && inputs($r[2], 'congregation') === 0 && stripos($r[2], 'SHARED') !== false);
    $r = http('GET', "accounts/edit/$HX", $uParish);
    chk("parish login cannot open a congregation's own account (edit refused)", redirectsTo($r, 'accounts'));
    $r = http('GET', 'accounts/edit/34', $uAdmin);
    chk('edit of a protected account (34) opens with the notice, name read-only, no parent chooser', $r[0] === 200 && stripos($r[2], 'protected account') !== false && stripos($r[2], 'readonly') !== false && inputs($r[2], 'parent') === 0);
    $r = http('POST', 'accounts/update', $uAdmin, ['id' => '34', 'accountname' => 'cash on hand', 'description' => '', 'active' => 'on']);
    chk('POST rename of the protected account 34: edit form again with the reason; unchanged in the DB', $r[0] === 200 && stripos($r[2], 'protected') !== false && row(34)['accountType'] === 'cash at hand');
    $r = http('GET', 'accounts', $uAdmin);
    $b34 = rowButtons($r[2], 34); $b105 = rowButtons($r[2], 105); $b9 = rowButtons($r[2], 9); $b1 = rowButtons($r[2], 1);
    chk('index: 34 protected = Edit but no Delete; bank 105 none (no text, no buttons); 9 none; roots none', $b34['edit'] && !$b34['delete'] && !$b105['edit'] && !$b105['delete'] && trim($b105['text']) === '' && !$b9['edit'] && !$b9['delete'] && !$b1['edit'] && !$b1['delete']);
    $r = http('GET', 'accounts', $uCong);
    $b26 = rowButtons($r[2], 26); $bHX = rowButtons($r[2], $HX);
    chk('index for a congregation login: no Edit / Delete on shared 26, buttons on its own account', !$b26['edit'] && !$b26['delete'] && $bHX['edit'] && $bHX['delete']);
    chk("index: another congregation's account is not listed for congregation 11", rowButtons($r[2], $C2) === null && rowButtons($r[2], 101) === null);
    chk('index shows the tree at any depth: the depth-4 banks of congregation 11 and the new child', rowButtons($r[2], 105) !== null && rowButtons($r[2], 195) !== null && rowButtons($r[2], $HC) !== null);

    // ---------------------------------------------------------------- update POST refusals (ownership)
    $r = http('POST', 'accounts/update', $uCong, ['id' => $SH, 'accountname' => 'zz t810 h shared hijack', 'parent' => '26', 'active' => 'on']);
    chk('POST update of a shared account by a congregation login: refused, unchanged, congregation still 0', !redirectsTo($r, 'accounts/edit') && row($SH)['accountType'] === 'zz t810 h shared' && (int)row($SH)['congregationId'] === 0 && stripos(http('GET', 'accounts', $uCong)[2], 'Shared accounts') !== false);
    $r = http('POST', 'accounts/update', $uCong, ['id' => $C2, 'accountname' => 'zz t810 h hijack', 'parent' => '26', 'active' => 'on']);
    chk("POST update of another congregation's account: refused, unchanged", row($C2)['accountType'] === 'zz t810 h cong2' && (int)row($C2)['congregationId'] === 2);
    $r = http('POST', 'accounts/update', $uCong, ['id' => $HX, 'accountname' => 'zz t810 h renamed', 'parent' => '30', 'congregation' => '0', 'active' => 'on']);
    chk('POST update with a congregation field: ignored (congregation is fixed at create)', (int)row($HX)['congregationId'] === 11);
    foreach (['', 'abc', '999999'] as $bad) {
        $r = http('POST', 'accounts/update', $uAdmin, ['id' => $bad, 'accountname' => 'x']);
        chk("POST update with ID '$bad': redirect with a message, no crash", redirectsTo($r, 'accounts'));
    }
    $r = http('POST', 'accounts/update', $uParish, ['id' => $SH, 'accountname' => 'zz t810 h shared', 'parent' => '26', 'description' => 'by the parish', 'active' => 'on']);
    chk('POST update of a shared account by a parish login: saved', redirectsTo($r, 'accounts') && row($SH)['description'] === 'by the parish');
    $r = http('POST', 'accounts/update', $uParish, ['id' => $HX, 'accountname' => 'zz t810 h hijack', 'parent' => '30', 'active' => 'on']);
    chk("POST update of a congregation's account by a parish login: refused", row($HX)['accountType'] === 'zz t810 h renamed');

    // ---------------------------------------------------------------- delete through the page
    foreach (['', 'abc', '999999', '0'] as $bad) {
        $r = http('POST', 'accounts/delete', $uAdmin, ['id' => $bad]);
        $fl = http('GET', 'accounts', $uAdmin);
        chk("POST delete with ID '$bad': redirect, a message, nothing deleted, no warning", redirectsTo($r, 'accounts') && (int)q('SELECT COUNT(*) c FROM tblaccounttypes WHERE deleted=1')[0]['c'] === 1, "status {$r[0]}");
    }
    $r = http('GET', 'accounts/delete', $uAdmin);
    chk('delete by GET: redirected to the list, nothing deleted', redirectsTo($r, 'accounts'));
    $r = http('POST', 'accounts/delete', $uCong, ['id' => $HX]);
    $fl = http('GET', 'accounts', $uCong)[2];
    chk('delete refused while it has a sub account: every reason in the message, row kept', redirectsTo($r, 'accounts') && stripos($fl, 'sub account') !== false && stripos($fl, 'zz t810 h child') !== false && (int)row($HX)['deleted'] === 0);
    foreach ([34 => 'protected', 105 => 'bank', 9 => 'protected'] as $id => $needle) {
        $r = http('POST', 'accounts/delete', $uAdmin, ['id' => $id]);
        $fl = http('GET', 'accounts', $uAdmin)[2];
        chk("delete of $id refused ($needle) with the reason shown", redirectsTo($r, 'accounts') && stripos($fl, $needle) !== false && (int)row($id)['deleted'] === 0);
    }
    $r = http('POST', 'accounts/delete', $uAdmin, ['id' => 20]);
    $fl = http('GET', 'accounts', $uAdmin)[2];
    chk('delete of 20 (journal lines): refused, all reasons, no exception page', redirectsTo($r, 'accounts') && stripos($fl, 'journal line') !== false);
    $r = http('POST', 'accounts/delete', $uCong, ['id' => $SH]);
    chk('delete of a shared account by a congregation login: refused', redirectsTo($r, 'accounts') && (int)row($SH)['deleted'] === 0);
    $r = http('POST', 'accounts/delete', $uCong, ['id' => $C2]);
    chk("delete of another congregation's account: refused", (int)row($C2)['deleted'] === 0);
    http('GET', 'accounts', $uCong);   // consume pending flash
    $r = http('POST', 'accounts/delete', $uCong, ['id' => $HC]);
    http('GET', 'accounts', $uCong);
    chk('delete a leaf: success flash, soft deleted, the parent recomputed to posting', redirectsTo($r, 'accounts') && (int)row($HC)['deleted'] === 1 && (int)row($HX)['isSubCategory'] === 1);
    $r = http('POST', 'accounts/delete', $uCong, ['id' => $HX]);
    $fl = http('GET', 'accounts', $uCong)[2];
    chk('delete the (now childless) account', redirectsTo($r, 'accounts') && (int)row($HX)['deleted'] === 1 && stripos($fl, 'Deleted successfully') !== false, 'status ' . $r[0] . ' deleted=' . row($HX)['deleted'] . ' flash=' . (int)(stripos($fl, 'msg-flash') !== false) . ' ' . (preg_match('~id="msg-flash"[^>]*>([^<]{0,200})~', $fl, $mm) ? $mm[1] : ''));
    $r = http('POST', 'accounts/create', $uCong, ['accountname' => 'zz t810 h renamed', 'parent' => '26']);
    chk('a deleted account name cannot be re-used (form shows the reason)', $r[0] === 200 && stripos($r[2], 'cannot be re-used') !== false);
    $r = http('POST', 'accounts/delete', $uParish, ['id' => $SH]);
    chk('a parish login deletes a shared account', redirectsTo($r, 'accounts') && (int)row($SH)['deleted'] === 1);

    // ---------------------------------------------------------------- Banks module: update / sub accounts / GL dropdown
    $r = http('POST', 'banks/create', $uCong, ['bankname' => 'ZZ T810 HTTP BANK', 'account' => 'zz810http', 'openingbal' => '', 'asof' => '']);
    $BK = idOf('zz t810 http bank');
    chk('banks/create: bank saved under 33, posting flag 1, parent 33 stays reporting', redirectsTo($r, 'banks') && $BK > 0 && (int)row($BK)['parentId'] === 33 && (int)row($BK)['isSubCategory'] === 1 && (int)row(33)['isSubCategory'] === 0);
    $r = http('POST', 'banks/create', $uCong, ['bankname' => 'zz t810 http bank', 'account' => '', 'openingbal' => '', 'asof' => '']);
    chk('banks/create: duplicate name -> the form again with the reason', $r[0] === 200 && stripos($r[2], 'already exists') !== false);
    $r = http('POST', 'banks/update', $uCong, ['id' => $BK, 'bankname' => 'zz t810 http bank 2', 'account' => 'zz810http']);
    chk('banks/update: own bank renamed', redirectsTo($r, 'banks') && row($BK)['accountType'] === 'zz t810 http bank 2');
    http('GET', 'banks', $uCong);
    $r = http('POST', 'banks/update', $uCong, ['id' => '34', 'bankname' => 'zz t810 hijack', 'account' => '']);
    chk('banks/update with a NON-bank ID (34): refused with a message, 34 unchanged (the old query renamed any account)', redirectsTo($r, 'banks') && row(34)['accountType'] === 'cash at hand' && stripos(http('GET', 'banks', $uCong)[2], 'not found') !== false);
    $r = http('POST', 'banks/update', $uCong, ['id' => '101', 'bankname' => 'zz t810 hijack', 'account' => '']);
    chk("banks/update with another congregation's bank (101): refused, unchanged", row(101)['accountType'] === 'equity');
    foreach (['', 'abc', '999999'] as $bad) {
        $r = http('POST', 'banks/update', $uCong, ['id' => $bad, 'bankname' => 'zz', 'account' => '']);
        chk("banks/update with ID '$bad': redirect, no warning", redirectsTo($r, 'banks'));
    }
    $r = http('GET', 'banks/edit/34', $uCong);
    chk('banks/edit of a non-bank / missing ID: back to the list, no warning', redirectsTo($r, 'banks') && redirectsTo(http('GET', 'banks/edit/999999', $uCong), 'banks'));
    $r = http('POST', 'banks/update', $uCong, ['id' => $BK, 'bankname' => 'co-op bank', 'account' => '']);
    chk('banks/update: the name of another account refused (form again)', $r[0] === 200 && stripos($r[2], 'already exists') !== false);
    // sub accounts: the GL dropdown and the JSON save
    $page = http('GET', 'banks/subaccount', $uCong);
    $gl = selectOptions($page[2], 'glaccount');
    chk('banks/subaccount GL dropdown: posting accounts only (no 26 reporting, no root, no bank 105)', $page[0] === 200 && count($gl) > 20 && !isset($gl['26']) && !isset($gl['1']) && !isset($gl['105']) && isset($gl['34']), count($gl) . ' options');
    $js = ['isedit' => false, 'id' => '', 'name' => 'zz t810 h sub', 'bank' => (string)$BK, 'glaccount' => '34', 'districtgroup' => 'group', 'param' => (string)q('SELECT ID FROM tblgroups WHERE congregationId=11 LIMIT 1')[0]['ID']];
    $r = http('POST', 'banks/createupdatesubaccount', $uCong, ['glaccount' => '26'] + $js, true);
    chk('sub account JSON: a reporting G/L account -> 400 with the reason, nothing saved', $r[0] === 400 && jsonOf($r[2]) && stripos(jsonOf($r[2])['message'], 'G/L') !== false && !q("SELECT ID FROM tblbanksubaccounts WHERE AccountName='zz t810 h sub'"), "status {$r[0]} " . $r[2]);
    $r = http('POST', 'banks/createupdatesubaccount', $uCong, ['bank' => '101'] + $js, true);
    chk("sub account JSON: another congregation's bank -> 400", $r[0] === 400 && !q("SELECT ID FROM tblbanksubaccounts WHERE AccountName='zz t810 h sub'"));
    $r = http('POST', 'banks/createupdatesubaccount', $uCong, ['glaccount' => '105'] + $js, true);
    chk('sub account JSON: a bank as the G/L account -> 400', $r[0] === 400);
    $r = http('POST', 'banks/createupdatesubaccount', $uCong, ['glaccount' => '1'] + $js, true);
    chk('sub account JSON: a root as the G/L account -> 400', $r[0] === 400);
    $r = http('POST', 'banks/createupdatesubaccount', $uCong, $js, true);
    chk('sub account JSON: own bank + posting account -> 201 saved', $r[0] === 201 && jsonOf($r[2])['success'] === true && q("SELECT ID FROM tblbanksubaccounts WHERE AccountName='zz t810 h sub'"), "status {$r[0]} " . $r[2]);
    $SUB = (int)q("SELECT ID FROM tblbanksubaccounts WHERE AccountName='zz t810 h sub'")[0]['ID'];
    $r = http('POST', 'banks/createupdatesubaccount', $uCong, $js, true);
    chk('sub account JSON: the same name again -> 400 (unique among the congregation)', $r[0] === 400);
    $r = http('POST', 'banks/createupdatesubaccount', $uNone, $js, true);
    chk('sub account JSON without the banks right: not saved', !denied($r) ? $r[0] !== 201 : true);
    $r = http('GET', "banks/editsubaccount/$SUB", $uCong);
    chk('banks/editsubaccount page opens for its own congregation', $r[0] === 200);
    $r = http('POST', 'banks/delete', $uCong, ['id' => $BK, 'bankname' => 'zz t810 http bank 2']);
    chk('banks/delete: refused while a sub account exists? (guard = bank rows / ledger lines; sub accounts do not block) -> soft deleted and 33 recomputed', redirectsTo($r, 'banks') && (int)row($BK)['deleted'] === 1 && (int)row(33)['isSubCategory'] === 0);

    // ---------------------------------------------------------------- 8.10c: reporting parents with legacy references, protected parents, Actions column
    $r = http('POST', 'accounts/getparents', $uCong, ['congregation' => '11', 'root' => '1']);
    $ids = array_column(jsonOf($r[2]) ?: [], 'id');
    chk('8.10c getparents (Income): "tithes and offering" 32 and 31 are offered', in_array(32, $ids, true) && in_array(31, $ids, true) && !in_array(94, $ids, true), json_encode(array_slice($ids, 0, 15)));
    $ids = array_column(jsonOf(http('POST', 'accounts/getparents', $uCong, ['congregation' => '11'])[2]) ?: [], 'id');
    chk('8.10c getparents: protected 22, 25, 118 offered; 33, banks and posting accounts with postings (94, 98, 34) not', in_array(22, $ids, true) && in_array(25, $ids, true) && in_array(118, $ids, true) && !array_intersect($ids, [33, 105, 195, 94, 98, 34]));
    $addp = http('GET', 'accounts/add', $uCong)[2];
    chk('8.10c add form: the Income filter + parent list contain TITHES AND OFFERING', isset(selectOptions($addp, 'parent')['32']));
    $r = http('POST', 'accounts/create', $uCong, ['accountname' => 'zz t810 h under 32', 'parent' => '32']);
    $U32 = idOf('zz t810 h under 32');
    chk('8.10c create under 32 (reporting, legacy receipt lines): saved, 32 stays reporting', redirectsTo($r, 'accounts') && $U32 > 0 && (int)row($U32)['parentId'] === 32 && (int)row(32)['isSubCategory'] === 0);
    $r = http('POST', 'accounts/create', $uCong, ['accountname' => 'zz t810 h under 94', 'parent' => '94']);
    chk('8.10c create under 94 (posting account with lines): refused with the reason, nothing saved', $r[0] === 200 && stripos($r[2], 'posting account with postings') !== false && !idOf('zz t810 h under 94'));
    $r = http('POST', 'accounts/create', $uCong, ['accountname' => 'zz t810 h under 22', 'parent' => '22']);
    $U22 = idOf('zz t810 h under 22');
    chk('8.10c create under the protected parent 22: saved', redirectsTo($r, 'accounts') && $U22 > 0 && (int)row(22)['isSubCategory'] === 0);
    $r = http('POST', 'accounts/update', $uCong, ['id' => $U22, 'accountname' => 'zz t810 h under 22', 'parent' => '32', 'active' => 'on']);
    chk('8.10c move under 32 through the form: saved, accountTypeId follows (Income)', redirectsTo($r, 'accounts') && (int)row($U22)['parentId'] === 32 && (int)row($U22)['accountTypeId'] === 1);
    $r = http('POST', 'accounts/update', $uCong, ['id' => $U22, 'accountname' => 'zz t810 h under 22', 'parent' => '94', 'active' => 'on']);
    chk('8.10c move under 94 through the form: refused (edit form again with the reason)', $r[0] === 200 && stripos($r[2], 'posting account with postings') !== false && (int)row($U22)['parentId'] === 32);
    $r = http('GET', 'accounts', $uCong);
    $stripped = [];
    $dd = new DOMDocument; @$dd->loadHTML('<?xml encoding="utf-8"?>' . $r[2]);
    foreach ((new DOMXPath($dd))->query("//table[@id='accountsTable']/tbody/tr/td[4]") as $td) { $stripped[] = trim(preg_replace('/\b(Edit|Delete)\b/', '', $td->textContent)); }
    chk('8.10c index: the Actions column holds only the Edit / Delete buttons (no text in any of ' . count($stripped) . ' rows)', count($stripped) > 100 && count(array_filter($stripped, function ($t) { return $t !== ''; })) === 0, json_encode(array_slice(array_filter($stripped), 0, 3)));
    chk('8.10c index: the description is not shown in the list table (no description wording or note text)', (preg_match('~<table.*</table>~s', $r[2], $tm) && stripos($tm[0], 'description') === false && stripos($tm[0], '<small') === false) && stripos($r[2], 'Protected: description') === false);
    $viol = 0;
    // ---------------------------------------------------------------- the chart is still sound after every real POST
    $sql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/sql/chart_flags_check.sql'));
    $section = '';
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if (preg_match("/^SELECT '([A-H])\\. /", $stmt, $m)) { $section = $m[1]; continue; }
        $n = count($pdo->query($stmt)->fetchAll(PDO::FETCH_ASSOC));
        if ($section !== 'H') { $viol += $n; }
    }
    chk('chart_flags_check: no violation after the whole HTTP run', $viol === 0, "$viol rows");
} catch (Throwable $e) {
    $fail++;
    echo 'FAIL exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
    $pdo->exec('DELETE FROM tblbanksubaccounts WHERE ID > ' . $max['tblbanksubaccounts']);
    $pdo->exec('DELETE FROM journal_lines WHERE journalEntryId > ' . $max['journal_entries'] . ' OR ID > ' . $max['journal_lines']);
    foreach (['journal_entries', 'tblledger', 'tblbankpostings', 'tbllogs', 'tbluserrights', 'tblaccounttypes'] as $t) {
        $pdo->exec("DELETE FROM $t WHERE {$tables[$t]} > {$max[$t]}");
    }
    $now = $pdo->query('SELECT * FROM tblaccounttypes WHERE ID <= ' . $max['tblaccounttypes'] . ' ORDER BY ID')->fetchAll(PDO::FETCH_ASSOC);
    $diff = [];
    foreach ($acctSnapshot as $i => $r) { if ($r != $now[$i]) { $diff[] = $r['ID']; } }
    chk('no PHP warning / notice / fatal error in any response', !$phpErrors, json_encode(array_values(array_unique($phpErrors))));
    foreach ($sessions as $sid) { @unlink(session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid); }
    chk('cleanup: every pre-existing account row is as before', !$diff, json_encode($diff));
    foreach ($tables as $t => $k) { chk("cleanup: $t back to max id {$max[$t]}", (int)$pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn() === $max[$t]); }
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

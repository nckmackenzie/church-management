<?php
/**
 * Task 13 through Apache with a real session (CLI only; needs the dev-only scripts/dev_session_helper.php):
 * the bank transaction edit screen shows the saved split, the POST edit keeps other transactions' sub-account rows (a receipt with the
 * same ID number, an opening balance, another congregation), a sub-account transfer cannot be opened for edit, and delete keeps them too.
 * Self-cleaning (also resets AUTO_INCREMENT).
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
    if (preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~', $b, $m)) { $phpErrors[] = $path . ' -> ' . $m[2]; }
    return [$code, $b, $hd];
}
function redirectsTo($r, $needle) { return in_array($r[0], [301, 302, 303], true) && preg_match('~^location:\s*(\S+)~mi', $r[2], $m) && strpos($m[1], $needle) !== false; }
function x($sql, $p = []) { global $pdo; $pdo->prepare($sql)->execute($p); }
function subrows($type, $tid, $cong = 11) {
    return q('SELECT TransactionDate,TransactionId,SubAccountId,Amount,FromAccountId,ToAccountId,Narration,TransactionType,Reference,CongregationId
              FROM tblbanktransactions_subaccounts WHERE TransactionType=? AND TransactionId=? AND CongregationId=? ORDER BY SubAccountId,Amount', [$type, $tid, $cong]);
}

$tables = ['journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblbankpostings' => 'ID', 'tblbanktransactions' => 'ID', 'tblpettycash' => 'ID', 'tbllogs' => 'ID',
           'tblbanktransactions_subaccounts' => 'ID', 'tblcontributions_header' => 'ID', 'tblcontributions_details' => 'ID', 'tblbanksubaccounts' => 'ID'];
$max = []; foreach ($tables as $t => $k) { $max[$t] = (int) $pdo->query("SELECT IFNULL(MAX($k),0) FROM $t")->fetchColumn(); }

try {
    require_once 'models/Banktransaction.php';
    $bt = new Banktransaction;
    $b1 = (int) $bt->GetBanks()[0]->ID;
    $sub = q('SELECT ID FROM tblbanksubaccounts WHERE BankId=? AND Deleted=0 ORDER BY ID LIMIT 2', [$b1]);
    $s1 = (int) $sub[0]['ID']; $s2 = (int) $sub[1]['ID'];
    $cType = (int) q('SELECT contributionTypeId c FROM tblcontributions_details WHERE contributionTypeId>0 LIMIT 1')[0]['c'];
    $admin = mkSession(64, 11);
    // admin-entered names with markup: a sub account and (temporarily) the bank's own name
    $evil = '<script>alert(1)</script> & Co';
    $gid = (int)q('SELECT GroupId g FROM tblbanksubaccounts WHERE GroupId IS NOT NULL AND Deleted=0 LIMIT 1')[0]['g'];
    x("INSERT INTO tblbanksubaccounts (AccountName,BankId,AccountId,GroupDistrict,GroupId,DistrictId,Deleted) VALUES (?,?,33,'group',?,NULL,0)", [$evil, $b1, $gid]);
    $origBank = q('SELECT accountType FROM tblaccounttypes WHERE ID=?', [$b1])[0]['accountType'];
    x('UPDATE tblaccounttypes SET accountType=? WHERE ID=?', ['<b>BNK</b> & Co', $b1]);
    $add = http('GET', 'banktransactions/add', $admin);
    ok('add screen: sub account name with markup is escaped in both dropdowns (no raw tag)', stripos($add[1], '<script>alert(1)</script>') === false && substr_count($add[1], '&lt;SCRIPT&gt;ALERT(1)&lt;/SCRIPT&gt; &amp; CO') >= 2, $add[0]);
    ok('add screen: bank name with markup is escaped (optgroup label and transfer list)', stripos($add[1], '<b>BNK</b>') === false && strpos($add[1], '&lt;B&gt;BNK&lt;/B&gt; &amp; CO') !== false);
    x('UPDATE tblaccounttypes SET accountType=? WHERE ID=?', [$origBank, $b1]);

    // a split deposit through the model (as the add form would), ID shared with a receipt, an opening balance and other-congregation rows
    $N = 20011;
    x('INSERT INTO tblbanktransactions (ID,TransactionDate,TransactionTypeId,BankId,Amount,CashAccountId,Reference,Description,CongregationId) VALUES (?,?,1,?,300,34,?,?,11)', [$N, '2026-01-15', $b1, 'H13', 'fixture']);
    x('INSERT INTO tblcontributions_header (ID,fiscalYearId,postedBy,postedDate,status,Deleted,receiptNo,congregationId) VALUES (?,12,64,?,1,0,?,11)', [$N, '2026-01-10', "H13-$N"]);
    x('INSERT INTO tblcontributions_details (HeaderId,contributionDate,contributionTypeId,paymentMethodId,bankId,amount,category,incomeType,forGroup,paymentReference,subaccount) VALUES (?,?,?,2,?,150,2,1,0,?,?)', [$N, '2026-01-10', $cType, $b1, 'R13', $s1]);
    $ins = 'INSERT INTO tblbanktransactions_subaccounts (TransactionDate,TransactionId,SubAccountId,Amount,TransactionType,Narration,Reference,CongregationId) VALUES (?,?,?,?,?,?,?,?)';
    foreach ([['2026-01-10', $N, $s1, '150.00', 1, 'Collection', 'R13', 11], ['2023-10-01', $N, $s2, '999.00', 18, 'Sub account opening balance.', null, 11],
              ['2026-01-11', $N, $s1, '77.00', 13, 'sub-account deposit', 'OTHER', 12], ['2026-01-11', $N, $s2, '55.00', 1, 'Collection', 'OTHER', 12]] as $r) { x($ins, $r); }
    $keep = function () use ($N) { return md5(json_encode([subrows(1, $N), subrows(18, $N), subrows(13, $N, 12), subrows(1, $N, 12)])); };
    $kept = $keep();

    $post = function ($subs, $over = []) use ($N, $b1) {
        return $over + ['id' => (string) $N, 'isedit' => '1', 'date' => '2026-01-15', 'bank' => (string) $b1, 'type' => '1', 'transferto' => '', 'cashaccount' => '34', 'amount' => '300',
                        'reference' => 'H13', 'description' => 'fixture', 'touched' => '', 'deposittosubs' => 'on', 'subaccounts' => $subs];
    };
    $r = http('POST', 'banktransactions/createupdate', $admin, $post([['accountid' => (string) $s1, 'amount' => '100'], ['accountid' => (string) $s2, 'amount' => '200']]), true);
    ok('POST edit into a split saved (201)', $r[0] === 201 && json_decode($r[1], true)['success'] === true, $r[0] . ' ' . substr($r[1], 0, 200));
    $own = subrows(13, $N);
    $r = http('GET', 'banktransactions', $admin);
    ok('after a successful edit the list shows "Saved successfully" (the edit screen goes back to the list)', $r[0] === 200 && strpos($r[1], 'Saved successfully') !== false);
    ok('own rows = 100 / 200', count($own) === 2 && (float) $own[0]['Amount'] === 100.0 && (float) $own[1]['Amount'] === 200.0);
    ok('receipt row, opening balance and other-congregation rows untouched', $keep() === $kept);

    $r = http('GET', "banktransactions/edit/$N", $admin);
    ok('edit screen renders (200)', $r[0] === 200, (string) $r[0]);
    ok('edit screen: split checkbox checked and not disabled', preg_match('~<input[^>]*id="deposittosubs"[^>]*>~', $r[1], $m) && strpos($m[0], 'checked') !== false && strpos($m[0], 'disabled') === false, $m[0] ?? '');
    ok('edit screen: saved split in data-saved (both sub accounts, amounts)', preg_match('~id="subaccountsContainer" data-saved="([^"]*)"~', $r[1], $m)
        && ($j = json_decode(html_entity_decode($m[1]), true)) && count($j) === 2 && (float) $j[0]['amount'] + (float) $j[1]['amount'] === 300.0, $m[1] ?? '');
    ok('edit screen: bank dropdown lists the sub accounts of the bank', strpos($r[1], 'value="' . $s1 . '-sub"') !== false);
    ok('edit screen loads index-v3.js', strpos($r[1], 'banktransactions/index-v3.js') !== false && is_file(__DIR__ . '/../public/dist/js/pages/banktransactions/index-v3.js'));
    $r = http('GET', 'dist/js/pages/banktransactions/index-v3.js', $admin);
    ok('index-v3.js is served and restores the saved split', $r[0] === 200 && strpos($r[1], 'showSavedSplit') !== false);
    ok('index-v3.js: names go in with textContent (no innerHTML), no blocking alert(), inline setError, edit goes back to the list',
        strpos($r[1], 'innerHTML') === false && strpos($r[1], 'alert(') === false && strpos($r[1], 'setError(') !== false
        && strpos($r[1], 'item.SubAccount') !== false && strpos($r[1], 'tr.insertCell().textContent = item.SubAccount') !== false
        && strpos($r[1], 'window.location.href') !== false && strpos($r[1], '/banktransactions`') !== false);
    $fb = http('GET', 'dist/js/pages/utils/feedback.js', $admin);
    ok('feedback.js (setError / clearError) is served', $fb[0] === 200 && strpos($fb[1], 'export function setError') !== false);
    ok('no older bank transaction script is still referenced', strpos($add[1], 'index-v1.js') === false && strpos($add[1], 'index-v2.js') === false);

    // saving the edit screen's own form data again (checked box, same amounts) keeps the split
    $r = http('POST', 'banktransactions/createupdate', $admin, $post([['accountid' => (string) $s1, 'amount' => '100'], ['accountid' => (string) $s2, 'amount' => '200']], ['reference' => 'H13B']), true);
    ok('saving the form again keeps the 2 own rows', $r[0] === 201 && count(subrows(13, $N)) === 2 && $keep() === $kept);
    // the old form dropped the box (disabled): the request then has no split -> a plain deposit, own rows removed, nothing else
    $r = http('POST', 'banktransactions/createupdate', $admin, ['deposittosubs' => null] + $post([]), true);
    ok('an edit without the split box turns it into a plain deposit; other rows untouched', $r[0] === 201 && !subrows(13, $N) && $keep() === $kept, $r[0] . ' ' . substr($r[1], 0, 150));

    // a sub-account transfer cannot be opened for edit
    $M = 20012;
    x('INSERT INTO tblbanktransactions (ID,TransactionDate,TransactionTypeId,BankId,Amount,TransferToId,Reference,Description,IsSubInterTransfer,CongregationId) VALUES (?,?,6,?,50,?,?,?,1,11)', [$M, '2026-01-16', $b1, $b1, 'H13T', 'sub transfer']);
    x($ins, ['2026-01-16', $M, $s2, '50.00', 13, 'Receipt from x', 'H13T', 11]);
    x($ins, ['2026-01-16', $M, $s1, '-50.00', 13, 'Transfer to y', 'H13T', 11]);
    $r = http('GET', "banktransactions/edit/$M", $admin);
    ok('edit of a sub-account transfer redirects to the list', redirectsTo($r, 'banktransactions'), $r[0] . ' ' . substr($r[2], 0, 200));
    ok('...and its rows are untouched', count(subrows(13, $M)) === 2);

    // delete through the controller
    $r = http('POST', 'banktransactions/delete', $admin, ['id' => (string) $M]);
    ok('delete of the transfer: redirect, its 2 rows gone', redirectsTo($r, 'banktransactions') && !subrows(13, $M));
    $r = http('POST', 'banktransactions/delete', $admin, ['id' => (string) $N]);
    ok('delete of N: redirect, receipt row / opening balance / other-congregation rows kept', redirectsTo($r, 'banktransactions') && $keep() === $kept);
    ok('no PHP warning / notice / fatal error in any response', !$phpErrors, json_encode(array_values(array_unique($phpErrors))));
} catch (Throwable $e) {
    $fail++; echo "EXCEPTION: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n";
}

if (isset($origBank, $b1)) { x('UPDATE tblaccounttypes SET accountType=? WHERE ID=?', [$origBank, $b1]); }
foreach ($sessions as $sid) { @unlink(session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $t => $k) { $pdo->prepare("DELETE FROM $t WHERE $k > ?")->execute([$max[$t]]); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
foreach (['tblbanktransactions', 'tblcontributions_header'] as $t) {
    $pdo->exec("ALTER TABLE $t AUTO_INCREMENT = " . ((int) $pdo->query("SELECT IFNULL(MAX(ID),0) FROM $t")->fetchColumn() + 1));
}
$left = 0; foreach ($tables as $t => $k) { $left += (int) $pdo->query("SELECT COUNT(*) FROM $t WHERE $k > {$max[$t]}")->fetchColumn(); }
ok('self-cleaning: nothing left behind', $left === 0);
echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

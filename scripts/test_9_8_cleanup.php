<?php
/**
 * Task 9.8: the routine / view drop script and its list, the money-column DECIMAL script, the budget / service amount writers and the
 * sub account reports. The drop script is exercised on a scratch schema (cms_t98_replay) with synthetic objects; on dev it is only
 * run in dry-run mode. Self-cleaning, CLI-only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir(__DIR__ . '/../app');
require 'boostrap.php';
require_once 'models/Churchbudget.php';
require_once 'models/Groupbudget.php';
require_once 'models/Service.php';
require_once 'models/Report.php';
$appDir = getcwd();
require_once __DIR__ . '/drop_unused_routines_views.php';   // defines the lists; runs nothing when included
chdir($appDir);

$pdo = (new Database)->pdo(); $pass = 0; $fail = 0;
$_SESSION['congId'] = 11; $_SESSION['userId'] = 64;
function ok98($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ($extra === '' ? '' : ": $extra") . "\n"; } }
function q98($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function x98($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }
$mysql = 'E:/xampp/mysql/bin/mysql.exe';
$scratch = 'cms_t98_replay';
$created = ['groups' => [], 'districts' => [], 'subs' => [], 'book' => [], 'exps' => [], 'congs' => [], 'headers' => []];
$tmpFiles = [];

try {
    // =====================================================================================================================
    echo "== A. the drop list\n";
    // =====================================================================================================================
    $lists = ['view' => [DROP_VIEWS, KEEP_VIEWS], 'procedure' => [DROP_PROCEDURES, KEEP_PROCEDURES], 'function' => [DROP_FUNCTIONS, KEEP_FUNCTIONS]];
    $dup = false; $both = [];
    foreach ($lists as $t => [$d, $k]) { if (count($d) !== count(array_unique($d)) || count($k) !== count(array_unique($k))) { $dup = true; } $both = array_merge($both, array_intersect($d, $k)); }
    ok98('the fixed lists have no duplicate and no object in both the drop and the keep list', !$dup && !$both, implode(',', $both));
    ok98('the lists are the reviewed size: 16 views, 35 procedures, 30 functions dropped (81); 17 / 19 / 26 kept', count(DROP_VIEWS) === 16 && count(DROP_PROCEDURES) === 35 && count(DROP_FUNCTIONS) === 30 && count(KEEP_VIEWS) === 17 && count(KEEP_PROCEDURES) === 19 && count(KEEP_FUNCTIONS) === 26);
    $docText = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../docs/ledger_inventory.md'));
    $a = strpos($docText, '### Routines and views dropped at cutover (step 6.2)');
    ok98('the document has the section "Routines and views dropped at cutover (step 6.2)"', $a !== false);
    $sec = $a === false ? '' : substr($docText, $a, ($e = strpos($docText, "\n### ", $a + 10)) === false ? null : $e - $a);
    $parts = preg_split('/\n#### /', $sec);
    $table = function ($title) use ($parts) { foreach ($parts as $p) { if (stripos($p, $title) === 0) { preg_match_all('/^\| `([A-Za-z0-9_$]+)` \| (function|procedure|view) \|/m', $p, $m, PREG_SET_ORDER); $o = []; foreach ($m as $x) { $o[$x[2]][] = $x[1]; } return $o; } } return null; };
    $dd = $table('Dropped'); $kk = $table('Kept');
    $norm = function ($arr) { $arr = $arr ?: []; sort($arr); return array_values($arr); };
    ok98('the document\'s "Dropped" table is exactly the script\'s drop list (views, procedures, functions)', $dd !== null && $norm($dd['view'] ?? []) === $norm(DROP_VIEWS) && $norm($dd['procedure'] ?? []) === $norm(DROP_PROCEDURES) && $norm($dd['function'] ?? []) === $norm(DROP_FUNCTIONS),
         $dd === null ? 'no table' : json_encode([count($dd['view'] ?? []), count($dd['procedure'] ?? []), count($dd['function'] ?? [])]));
    ok98('the document\'s "Kept" table is exactly the script\'s keep list', $kk !== null && $norm($kk['view'] ?? []) === $norm(KEEP_VIEWS) && $norm($kk['procedure'] ?? []) === $norm(KEEP_PROCEDURES) && $norm($kk['function'] ?? []) === $norm(KEEP_FUNCTIONS),
         $kk === null ? 'no table' : json_encode([count($kk['view'] ?? []), count($kk['procedure'] ?? []), count($kk['function'] ?? [])]));
    $runbook = strpos($docText, '| 6.2 |') !== false;
    ok98('the runbook has step 6.2 (dry run, commit with backup, verification, rollback)', $runbook);

    echo "== B. dev after the real run (dry run in this test)\n";
    ob_start(); $r = run(['x', '--dry-run'], $pdo); $out = ob_get_clean(); chdir($appDir);
    ok98('dev dry run: ok, nothing left to drop, no unknown routine or view, nothing referenced', $r['ok'] && $r['exit'] === 0 && strpos($out, '0 routines/views to drop are present') !== false && strpos($out, 'No unknown routines or views.') !== false && strpos($out, 'STOP') === false, $out);
    $haveRoutines = array_column(q98("SELECT ROUTINE_NAME n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()"), 'n');
    $haveViews = array_column(q98("SELECT TABLE_NAME n FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()"), 'n');
    $low = function ($a) { return array_map('strtolower', $a); };
    ok98('none of the 81 listed objects exists on dev', !array_intersect($low(array_merge(DROP_PROCEDURES, DROP_FUNCTIONS)), $low($haveRoutines)) && !array_intersect($low(DROP_VIEWS), $low($haveViews)));
    ok98('every kept object still exists on dev (nothing outside the list was dropped)', !array_diff($low(array_merge(KEEP_PROCEDURES, KEEP_FUNCTIONS)), $low($haveRoutines)) && !array_diff($low(KEEP_VIEWS), $low($haveViews)));
    ok98('dev holds exactly the kept objects: 45 routines and 17 views', count($haveRoutines) === count(KEEP_PROCEDURES) + count(KEEP_FUNCTIONS) && count($haveViews) === count(KEEP_VIEWS), count($haveRoutines) . '/' . count($haveViews));
    $backup = __DIR__ . '/logs/drop_9_8_backup.sql';
    if (is_file($backup)) {
        $bt = file_get_contents($backup); $missing = [];
        foreach (array_merge(DROP_VIEWS, DROP_PROCEDURES, DROP_FUNCTIONS) as $n) { if (strpos($n, 'zz_') !== 0 && strpos($bt, '`' . $n . '`') === false) { $missing[] = $n; } }   // zz_* helpers exist only after a stopped guard
        ok98('the dev backup file of the real run holds a definition of every dropped object', !$missing, implode(',', $missing));
    }
    // a kept object that depends on nothing dropped: spot-check by calling what the screens call
    $answers = true; try { q98('SELECT getYearID() a, getmmfopeningbalbydate(40, CURDATE()) b, getrequisitionbalance(1) c, fn_get_supplier_balance(1) d, getinvoicebalance_supplier(1) e, getbudgetedamount(26, 12, 40) f'); } catch (Throwable $e) { $answers = false; }
    ok98('kept routines still answer (getYearID, getmmfopeningbalbydate, getrequisitionbalance, fn_get_supplier_balance, getinvoicebalance_supplier, getbudgetedamount)', $answers);

    echo "== C. the drop script on a scratch schema (synthetic objects)\n";
    $pdo->exec("DROP SCHEMA IF EXISTS `$scratch`");
    $pdo->exec("CREATE SCHEMA `$scratch`");
    $sp = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $scratch, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $make = function (PDO $c, string $type, string $name, string $body = null) {
        if ($type === 'view') { $c->exec("CREATE VIEW `$name` AS " . ($body ?? 'SELECT 1 AS x')); }
        elseif ($type === 'procedure') { $c->exec("CREATE PROCEDURE `$name`() " . ($body ?? 'BEGIN SELECT 1; END')); }
        else { $c->exec("CREATE FUNCTION `$name`() RETURNS INT DETERMINISTIC " . ($body ?? 'RETURN 1')); }
    };
    foreach (DROP_VIEWS as $n) { $make($sp, 'view', $n); }
    foreach (DROP_PROCEDURES as $n) { $make($sp, 'procedure', $n); }
    foreach (DROP_FUNCTIONS as $n) { $make($sp, 'function', $n); }
    foreach (array_slice(KEEP_VIEWS, 0, 3) as $n) { $make($sp, 'view', $n); }
    foreach (array_slice(KEEP_FUNCTIONS, 0, 3) as $n) { $make($sp, 'function', $n); }
    $make($sp, 'procedure', 'zz_unknown_helper');
    // a listed function used by a dropped procedure and by a view that is dropped: fine
    ob_start(); $r = run(['x'], $sp); $dry = ob_get_clean(); chdir($appDir);
    ok98('scratch dry run (the default) lists exactly the 81 listed objects and changes nothing', $r['ok'] && strpos($dry, '81 routines/views to drop are present, 0 are already gone') !== false && substr_count($dry, '  DROP ') === 81, substr($dry, 0, 300));
    ok98('scratch dry run reports the object outside both lists as unknown and does not touch it', strpos($dry, 'UNKNOWN objects') !== false && strpos($dry, 'procedure zz_unknown_helper') !== false);
    $cnt = function () use ($sp, $scratch) { return (int) $sp->query("SELECT (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '$scratch') + (SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = '$scratch')")->fetchColumn(); };
    $before = $cnt();
    ok98('the dry run dropped nothing', $before === 81 + 6 + 1, (string) $before);
    ob_start(); $r2 = run(['x', '--commit'], $sp); $o2 = ob_get_clean(); chdir($appDir);
    ok98('--commit without --backup is refused (exit 2) and changes nothing', !$r2['ok'] && $r2['exit'] === 2 && $cnt() === $before);
    $bk = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 't98_backup_' . getmypid() . '.sql'; $tmpFiles[] = $bk;
    // a remaining object that refers to a listed one stops the whole run
    $make($sp, 'procedure', 'zz_holder', 'BEGIN SELECT `' . DROP_FUNCTIONS[0] . '`(); END');
    ob_start(); $r3 = run(['x', '--commit', '--backup', $bk], $sp); $o3 = ob_get_clean(); chdir($appDir);
    ok98('a routine that stays but refers to a listed function stops the run: exit 1, nothing dropped, no backup', !$r3['ok'] && $r3['exit'] === 1 && strpos($o3, 'STOP') !== false && strpos($o3, 'zz_holder') !== false && $cnt() === $before + 1 && !is_file($bk), $o3);
    $sp->exec('DROP PROCEDURE zz_holder');
    ob_start(); $r4 = run(['x', '--commit', '--backup', $bk], $sp); $o4 = ob_get_clean(); chdir($appDir);
    $after = $cnt();
    ok98('commit with a backup: writes the backup, drops the 81 objects, verifies, leaves the kept and the unknown ones', $r4['ok'] && is_file($bk) && strpos($o4, 'Verified: none of the 81 dropped objects remains.') !== false && $after === 6 + 1, $o4);
    $names = array_merge(array_column($sp->query("SELECT ROUTINE_NAME n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '$scratch'")->fetchAll(PDO::FETCH_ASSOC), 'n'), array_column($sp->query("SELECT TABLE_NAME n FROM information_schema.VIEWS WHERE TABLE_SCHEMA = '$scratch'")->fetchAll(PDO::FETCH_ASSOC), 'n'));
    ok98('what is left is the kept sample and the unknown helper', !array_intersect($low($names), $low(array_merge(DROP_VIEWS, DROP_PROCEDURES, DROP_FUNCTIONS))) && in_array('zz_unknown_helper', $names, true));
    $bt = file_get_contents($bk);
    ok98('the backup holds a DELIMITER-wrapped definition of every dropped object, functions before views before procedures', substr_count($bt, "\nDELIMITER $$") === count(DROP_PROCEDURES) + count(DROP_FUNCTIONS) && strpos($bt, '-- function ' . DROP_FUNCTIONS[0]) < strpos($bt, '-- view ' . DROP_VIEWS[0]) && strpos($bt, '-- view ' . DROP_VIEWS[0]) < strpos($bt, '-- procedure ' . DROP_PROCEDURES[0]));
    ob_start(); $r5 = run(['x', '--commit', '--backup', $bk . '.2'], $sp); $o5 = ob_get_clean(); chdir($appDir); $tmpFiles[] = $bk . '.2';
    ok98('second run: nothing to drop, exit 0, no second backup', $r5['ok'] && strpos($o5, 'Nothing to drop') !== false && !is_file($bk . '.2'));
    ob_start(); $r6 = run(['x', '--commit', '--backup', $bk], $sp); ob_get_clean(); chdir($appDir);
    ok98('an existing backup file is never overwritten (exit 2)', !$r6['ok'] && $r6['exit'] === 2);
    // replay
    $outp = []; exec('"' . $mysql . '" -u ' . DB_USER . ' ' . $scratch . ' < "' . $bk . '" 2>&1', $outp, $code);
    ok98('the backup replays with the mysql client (exit 0)', $code === 0, implode("\n", $outp));
    ok98('after the replay the 81 objects exist again, as the right types', (int) $sp->query("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = '$scratch' AND TABLE_NAME IN ('" . implode("','", DROP_VIEWS) . "')")->fetchColumn() === 16
        && (int) $sp->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '$scratch' AND ROUTINE_TYPE = 'PROCEDURE' AND ROUTINE_NAME IN ('" . implode("','", DROP_PROCEDURES) . "')")->fetchColumn() === 35
        && (int) $sp->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '$scratch' AND ROUTINE_TYPE = 'FUNCTION' AND ROUTINE_NAME IN ('" . implode("','", DROP_FUNCTIONS) . "')")->fetchColumn() === 30);
    ob_start(); run(['x', '--dry-run'], $sp); $o7 = ob_get_clean(); chdir($appDir);
    ok98('and a dry run sees them again (the replay restored exactly what was dropped)', strpos($o7, '81 routines/views to drop are present') !== false);
    $sp = null;
    $pdo->exec("DROP SCHEMA IF EXISTS `$scratch`");

    // =====================================================================================================================
    echo "== D. money columns: the DECIMAL script\n";
    // =====================================================================================================================
    $cols = q98("SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND ((TABLE_NAME IN ('tblcontributions_details','tblchurchbudget_details','tblgroupbudget_details') AND COLUMN_NAME = 'amount') OR (TABLE_NAME = 'tblserviceinfo' AND COLUMN_NAME IN ('envelopePledge','ordinary','special'))) ORDER BY 1,2");
    ok98('dev: all six money columns are DECIMAL(18,2) (runbook step 4.20 was run)', count($cols) === 6 && count(array_filter($cols, function ($c) { return $c['ty'] === 'decimal(18,2)'; })) === 6, json_encode($cols));
    $left = q98("SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE') AND DATA_TYPE IN ('float','double','real')");
    ok98('dev: the only FLOAT / DOUBLE column left is tblvats.rate (a percentage, not money)', count($left) === 1 && $left[0]['t'] === 'tblvats' && $left[0]['c'] === 'rate', json_encode($left));
    $sfx = '_t98';
    $copy = function () use ($pdo, $sfx) {
        foreach (['tblcontributions_details', 'tblchurchbudget_details', 'tblgroupbudget_details', 'tblserviceinfo'] as $t) { $pdo->exec("DROP TABLE IF EXISTS `$t$sfx`"); $pdo->exec("CREATE TABLE `$t$sfx` LIKE `$t`"); }
        foreach (['tblcontributions_details', 'tblchurchbudget_details', 'tblgroupbudget_details'] as $t) { $pdo->exec("ALTER TABLE `$t$sfx` MODIFY amount FLOAT NOT NULL"); }
        foreach (['envelopePledge', 'ordinary', 'special'] as $c) { $pdo->exec("ALTER TABLE `tblserviceinfo$sfx` MODIFY `$c` DOUBLE NULL"); }
    };
    $dropCopies = function () use ($pdo, $sfx) { foreach (['tblcontributions_details', 'tblchurchbudget_details', 'tblgroupbudget_details', 'tblserviceinfo'] as $t) { $pdo->exec("DROP TABLE IF EXISTS `$t$sfx`"); } };
    $runMoney = function ($accept = 0) use ($mysql, $sfx) {
        $tmp = tempnam(sys_get_temp_dir(), 'mc') . '.sql';
        file_put_contents($tmp, "SET @mc_suffix = '$sfx';\nSET @mc_accept = $accept;\n" . file_get_contents(__DIR__ . '/sql/money_columns_decimal.sql'));
        $o = []; exec('"' . $mysql . '" -u ' . DB_USER . ' ' . DB_NAME . ' --table < "' . $tmp . '" 2>&1', $o, $code); @unlink($tmp);
        return [$code, implode("\n", $o)];
    };
    $copy();
    $pdo->exec("INSERT INTO `tblcontributions_details$sfx` (HeaderId, contributionDate, contributionTypeId, paymentMethodId, amount, category) VALUES (1,'2098-01-01',30,1,100.5,2),(1,'2098-01-02',30,1,40000.4,2),(1,'2098-01-03',30,1,0.1,2),(1,'2098-01-04',30,1,131071.75,2),(1,'2098-01-05',30,1,250000,2)");
    $pdo->exec("INSERT INTO `tblchurchbudget_details$sfx` (ID, accountId, amount) VALUES (1, 26, 5400000), (1, 27, 1250.25)");
    $pdo->exec("INSERT INTO `tblserviceinfo$sfx` (serviceId, serviceDate, envelopePledge, ordinary, special, congregationId) VALUES (1,'2098-01-01',100.5,NULL,3.25,11)");
    $pdo->exec("INSERT INTO `tblcontributions_details$sfx` (HeaderId, contributionDate, contributionTypeId, paymentMethodId, amount, category) VALUES (1,'2098-01-06',30,1,1234567.89,2)");   // a FLOAT cannot keep these cents
    $pdo->exec("INSERT INTO `tblserviceinfo$sfx` (serviceId, serviceDate, envelopePledge, ordinary, special, congregationId) VALUES (1,'2098-01-02',1,3.005,1,11)");   // three decimals typed
    [$code, $out] = $runMoney();
    ok98('guard: a FLOAT line whose cents are lost (1,234,567.89) and a DOUBLE with three decimals stop the script (non-zero exit), both are listed with their table and column', $code !== 0 && strpos($out, 'tblcontributions_details_t98') !== false && strpos($out, '1234570') !== false && strpos($out, 'tblserviceinfo_t98') !== false && strpos($out, 'ordinary') !== false && strpos($out, 'nothing was changed') !== false, $out);
    $types = array_column(q98("SELECT COLUMN_TYPE ty FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('tblcontributions_details$sfx','tblchurchbudget_details$sfx','tblgroupbudget_details$sfx','tblserviceinfo$sfx') AND COLUMN_NAME IN ('amount','envelopePledge','ordinary','special')"), 'ty');
    ok98('guard: nothing was changed (every column is still float or double)', count($types) === 6 && !array_filter($types, function ($t) { return strpos($t, 'decimal') !== false; }), json_encode($types));
    ok98('guard: the small FLOAT values (100.5, 40000.4, 0.1, 131071.75) are not listed, only the rows that cannot be exact', substr_count($out, '|') > 0 && strpos($out, '40000.4') === false && strpos($out, '131071.75') === false);
    $pdo->exec("DELETE FROM `tblcontributions_details$sfx` WHERE amount > 1000000 AND amount < 1300000");
    $pdo->exec("DELETE FROM `tblserviceinfo$sfx` WHERE ordinary = 3.005");
    [$code, $out] = $runMoney();
    ok98('with exact rows the conversion succeeds: exit 0, before and after printed, totals equal to the cent', $code === 0 && strpos($out, 'before') !== false && strpos($out, 'after') !== false && strpos($out, 'converted: row counts and totals before and after are equal to the cent') !== false, $out);
    $tot = q98("SELECT SUM(amount) s, COUNT(*) n FROM `tblcontributions_details$sfx`")[0];
    ok98('contribution totals: 100.50 + 40,000.40 + 0.10 + 131,071.75 + 250,000.00 = 421,172.75 over 5 rows, exactly', $tot['s'] === '421172.75' && (int) $tot['n'] === 5, json_encode($tot));
    $row = q98("SELECT amount FROM `tblcontributions_details$sfx` WHERE contributionDate = '2098-01-02'")[0]['amount'];
    ok98('40000.4 became 40000.40 (not 40000.3984...)', $row === '40000.40', $row);
    $svc = q98("SELECT envelopePledge e, ordinary o, special s FROM `tblserviceinfo$sfx`")[0];
    ok98('service info: NULL stays NULL, 100.5 -> 100.50, 3.25 -> 3.25', $svc['e'] === '100.50' && $svc['o'] === null && $svc['s'] === '3.25', json_encode($svc));
    ok98('budget details converted with the total unchanged (5,401,250.25)', q98("SELECT SUM(amount) s FROM `tblchurchbudget_details$sfx`")[0]['s'] === '5401250.25');
    [$code, $out] = $runMoney();
    ok98('second run: "already DECIMAL(18,2)" for every column, exit 0, nothing changed', $code === 0 && substr_count($out, 'already DECIMAL(18,2)') >= 6 && strpos($out, 'nothing to convert') !== false, $out);
    // @mc_accept converts after a review
    $copy();
    $pdo->exec("INSERT INTO `tblcontributions_details$sfx` (HeaderId, contributionDate, contributionTypeId, paymentMethodId, amount, category) VALUES (1,'2098-01-06',30,1,1234567.89,2)");
    [$code, $out] = $runMoney(0);
    [$code2, $out2] = $runMoney(1);
    ok98('@mc_accept = 1 converts after the listed rows were reviewed (the list is still printed), to the nearest cent of what the column held', $code !== 0 && $code2 === 0 && strpos($out2, 'storedValue') !== false && q98("SELECT amount FROM `tblcontributions_details$sfx`")[0]['amount'] === '1234567.88', $out2);
    ok98('the helper procedure does not stay behind after a successful run', (int) q98("SELECT COUNT(*) n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'zz_money_columns_decimal'")[0]['n'] === 0);
    $dropCopies();
    [$code, $out] = $runMoney();
    ok98('a missing table is a clear error', $code !== 0 && strpos($out, 'not found') !== false, $out);
    $tmp = tempnam(sys_get_temp_dir(), 'mc') . '.sql'; file_put_contents($tmp, "SET @mc_suffix = 'x; drop table y';\n" . file_get_contents(__DIR__ . '/sql/money_columns_decimal.sql'));
    $o = []; exec('"' . $mysql . '" -u ' . DB_USER . ' ' . DB_NAME . ' --table < "' . $tmp . '" 2>&1', $o, $code); @unlink($tmp);
    ok98('a suffix with SQL in it is refused', $code !== 0 && strpos(implode("\n", $o), 'invalid table suffix') !== false, implode("\n", $o));

    echo "== E. writers of the converted columns\n";
    $cb = new Churchbudget; $gb = new Groupbudget;
    foreach ([$cb, $gb] as $m) {
        $n = get_class($m);
        ok98("$n: blank, whole, one and two decimals are valid amounts", !$m->amountsProblem(['', '0', '100', '1250.5', '1250.25', ' 7 ', '.5']));
        ok98("$n: three decimals, a negative, a thousands comma and text are refused", $m->amountsProblem(['12.345']) && $m->amountsProblem(['-5']) && $m->amountsProblem(['1,000']) && $m->amountsProblem(['abc']) && $m->amountsProblem(['1e3']));
        $rm = new ReflectionMethod($n, 'amountText'); $rm->setAccessible(true);
        ok98("$n: amounts are stored as exact decimal text (5400000.5 -> 5400000.50, blank -> 0.00, 0.1 -> 0.10)", $rm->invoke($m, '5400000.5') === '5400000.50' && $rm->invoke($m, '') === '0.00' && $rm->invoke($m, '0.1') === '0.10' && $rm->invoke($m, null) === '0.00');
    }
    $sv = new Service; $rm = new ReflectionMethod('Service', 'infoMoney'); $rm->setAccessible(true);
    $okv = $rm->invoke($sv, ['envelopepledge' => '100.5', 'ordinary' => '', 'special' => '3']);
    ok98('service info: blank is NULL, others exact decimal text; three decimals or a negative is refused (false)', $okv === ['envelopepledge' => '100.50', 'ordinary' => null, 'special' => '3.00'] && $rm->invoke($sv, ['ordinary' => '1.234']) === false && $rm->invoke($sv, ['special' => '-1']) === false);
    // a receipt line above 131,000 keeps its cents and reports to the cent
    $hid = x98("INSERT INTO tblcontributions_header (fiscalYearId, postedBy, postedDate, status, Deleted, receiptNo, congregationId) VALUES (12, 64, '2098-08-01', 1, 0, 'ZZ98-BIG', 11)"); $created['headers'][] = $hid;
    x98("INSERT INTO tblcontributions_details (HeaderId, contributionDate, contributionTypeId, paymentMethodId, amount, category, narration, incomeType, forGroup) VALUES (?,'2098-08-02',30,1,?,2,'zz t98',1,0)", [$hid, '1234567.89']);
    x98("INSERT INTO tblcontributions_details (HeaderId, contributionDate, contributionTypeId, paymentMethodId, amount, category, narration, incomeType, forGroup) VALUES (?,'2098-08-03',30,1,?,2,'zz t98',1,0)", [$hid, '131072.55']);
    $rep = (new Report)->GetContributions(['type' => 1, 'start' => '2098-08-01', 'end' => '2098-08-31', 'account' => []]);
    ok98('a receipt line of 1,234,567.89 and one of 131,072.55 are stored and reported to the cent (the FLOAT column could not hold them)', array_column($rep, 'amount') === [123456789, 13107255], json_encode(array_column($rep, 'amount')));
    ok98('the stored text is exact: 1234567.89 and 131072.55', array_column(q98("SELECT amount FROM tblcontributions_details WHERE HeaderId = ? ORDER BY ID", [$hid]), 'amount') === ['1234567.89', '131072.55']);

    // =====================================================================================================================
    echo "== F. sub account reports (group and district breakdown)\n";
    // =====================================================================================================================
    $other = x98("INSERT INTO tblcongregation (CongregationName, IsParish, deleted) VALUES ('zz t98 other', 0, 0)"); $created['congs'][] = $other;
    $G = x98("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zz98', 'zz t98 group', 1, 0, 11)"); $created['groups'][] = $G;
    $GO = x98("INSERT INTO tblgroups (groupID, groupName, active, deleted, congregationId) VALUES ('zz98o', 'zz t98 foreign group', 1, 0, ?)", [$other]); $created['groups'][] = $GO;
    $D = x98("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t98 district', 0, 11)"); $created['districts'][] = $D;
    $DO = x98("INSERT INTO tbldistricts (districtName, deleted, congregationId) VALUES ('zz t98 foreign district', 0, ?)", [$other]); $created['districts'][] = $DO;
    $sub = function ($name, $kind, $gid, $did, $deleted = 0, $bank = 105) use (&$created) { $id = x98("INSERT INTO tblbanksubaccounts (AccountName, BankId, AccountId, GroupDistrict, GroupId, DistrictId, Deleted) VALUES (?,?,30,?,?,?,?)", [$name, $bank, $kind, $gid, $did, $deleted]); $created['subs'][] = $id; return $id; };
    $sG = $sub('zz t98 group sub <b>x</b>', 'group', $G, null); $sD = $sub('zz t98 district sub', 'district', null, $D);
    $sGO = $sub('zz t98 foreign group sub', 'group', $GO, null); $sDO = $sub('zz t98 foreign district sub', 'district', null, $DO); $sDel = $sub('zz t98 deleted sub', 'group', $G, null, 1);
    $book = function ($sub, $date, $amount, $narr, $ref = 'zz t98 ref', $c = 11) use (&$created) { $id = x98("INSERT INTO tblbanktransactions_subaccounts (TransactionDate, TransactionId, SubAccountId, Amount, Narration, TransactionType, Reference, CongregationId) VALUES (?,?,?,?,?,1,?,?)", [$date, 1, $sub, $amount, $narr, $ref, $c]); $created['book'][] = $id; return $id; };
    $exp = function ($group, $district, $amount, $date, $req, $status, $deleted, $c = 11, $narr = 'zz t98 expense') use (&$created) {
        $id = x98("INSERT INTO tblexpenses (fiscalYearId, voucherNo, expenseType, expenseDate, accountId, groupId, districtId, paymethodId, amount, narration, paymentReference, status, requisitionId, postedBy, postedDate, deleted, congregationId) VALUES (12,?,?,?,26,?,?,1,?,?,'zz t98 xref',?,?,64,'2098-01-01',?,?)",
            [985000 + count($created['exps']), $group ? 2 : 3, $date, $group, $district, $amount, $narr, $status, $req, $deleted, $c]);
        $created['exps'][] = $id; return $id;
    };
    $book($sG, '2097-12-31', '1000.00', 'zz t98 opening <i>x</i>'); $book($sG, '2098-02-01', '500.00', 'zz t98 collection'); $book($sG, '2098-12-01', '9999.00', 'zz t98 after');
    $book($sGO, '2098-02-01', '77.00', 'zz t98 foreign');
    $exp($G, null, '300.00', '2098-03-01', 1, 1, 0, 11, 'zz t98 paid from requisition');
    $exp($G, null, '40.00', '2098-03-02', 1, 0, 0);          // not approved: in neither the balance nor the statement (decision 161)
    $exp($G, null, '50.00', '2098-03-03', null, 1, 0);       // not funded by a requisition
    $exp($G, null, '60.00', '2098-03-04', 1, 1, 1);          // deleted
    $exp($G, null, '70.00', '2098-03-05', 1, 1, 0, $other);  // another congregation's row on our group id
    $exp($G, null, '80.00', '2098-12-05', 1, 1, 0);          // after the date
    $book($sD, '2098-02-01', '2000.00', 'zz t98 district collection'); $exp(null, $D, '500.00', '2098-03-01', 1, 1, 0, 11, 'zz t98 district paid');
    $owned = SubAccountReport::owned($pdo, 11);
    ok98('owned(): this congregation\'s group and district sub accounts only (not the foreign group\'s, the foreign district\'s or the deleted one)', isset($owned[$sG]) && isset($owned[$sD]) && !isset($owned[$sGO]) && !isset($owned[$sDO]) && !isset($owned[$sDel]));
    ok98('owned(): another congregation sees none of them', !array_intersect([$sG, $sD], array_keys(SubAccountReport::owned($pdo, $other))));
    $asOf = '2098-06-30';
    $bal = SubAccountReport::balances($pdo, 11, $asOf);
    ok98('group balance = book (1,000 + 500) - approved requisition expenses (300) = 1,200.00; the unapproved 40, after-date, unfunded, deleted and foreign rows are out (decision 161)', $bal[$sG] === 120000, (string) $bal[$sG]);
    ok98('district balance = 2,000 - 500 = 1,500.00', $bal[$sD] === 150000);
    $bg = SubAccountReport::breakdown($pdo, 11, $owned[$sG], $asOf);
    ok98('GROUP breakdown: 3 rows (book 1,000 and 500, approved expense -300), total equals the balance', count($bg) === 3 && array_sum(array_column($bg, 'amount')) === $bal[$sG] && array_column($bg, 'amount') === [100000, 50000, -30000], json_encode(array_column($bg, 'amount')));
    $bd = SubAccountReport::breakdown($pdo, 11, $owned[$sD], $asOf);
    ok98('DISTRICT breakdown: book 2,000 and expense -500, total equals the balance', array_column($bd, 'amount') === [200000, -50000] && array_sum(array_column($bd, 'amount')) === $bal[$sD]);
    ok98('breakdown rows carry date, narration and reference as data', $bg[0]['narr'] === 'zz t98 opening <i>x</i>' && $bg[0]['reference'] === 'zz t98 ref' && $bg[0]['date'] === '2097-12-31');
    $st = SubAccountReport::statement($pdo, 11, $owned[$sG], '2098-01-01', '2098-12-31');
    ok98('statement: opening 1,000 then the book 500, the approved expense -300 (the unapproved 40 is not listed) and the after-date rows; running balance continues', $st[0]['narr'] === 'Opening balance' && $st[0]['balance'] === 100000 && $st[1]['in'] === 50000 && $st[2]['out'] === 30000 && array_column($st, 'balance') === [100000, 150000, 120000, 1119900, 1111900], json_encode(array_column($st, 'balance')));
    $spec = ['title' => 't', 'lines' => [], 'columns' => [['key' => 'date', 'label' => 'D', 'type' => 'date'], ['key' => 'amount', 'label' => 'A', 'type' => 'money'], ['key' => 'narr', 'label' => 'N', 'type' => 'text']], 'total' => 'amount', 'file' => 'x'];
    $sl = ReportList::slice($spec, $bg, 1, 2, '');
    ok98('paging and search work on the breakdown (page size 2 -> 2 pages; search "paid" -> the expense)', $sl['pages'] === 2 && count($sl['rows']) === 2 && ReportList::slice($spec, $bg, 1, 50, 'requisition')['matching'] === 1);
    ok98('no sub account report calls a stored routine any more', !preg_match('/CALL\s+sp_get_subaccount|get_subaccount_/i', file_get_contents(__DIR__ . '/../app/libraries/SubAccountReport.php') . file_get_contents(__DIR__ . '/../app/models/Report.php')));
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    try { $pdo->exec("DROP SCHEMA IF EXISTS `$scratch`"); } catch (Throwable $e2) {}
    foreach (['tblcontributions_details', 'tblchurchbudget_details', 'tblgroupbudget_details', 'tblserviceinfo'] as $t) { try { $pdo->exec("DROP TABLE IF EXISTS `{$t}_t98`"); } catch (Throwable $e2) {} }
    try { $pdo->exec('DROP PROCEDURE IF EXISTS zz_money_columns_decimal'); } catch (Throwable $e2) {}   // a stopped guard run leaves its helper
    foreach ($created['headers'] as $id) { $pdo->prepare('DELETE FROM tblcontributions_details WHERE HeaderId = ?')->execute([$id]); $pdo->prepare('DELETE FROM tblcontributions_header WHERE ID = ?')->execute([$id]); }
    foreach ($created['exps'] as $id) { $pdo->prepare('DELETE FROM tblexpenses WHERE ID = ?')->execute([$id]); }
    foreach ($created['book'] as $id) { $pdo->prepare('DELETE FROM tblbanktransactions_subaccounts WHERE ID = ?')->execute([$id]); }
    foreach ($created['subs'] as $id) { $pdo->prepare('DELETE FROM tblbanksubaccounts WHERE ID = ?')->execute([$id]); }
    foreach ($created['groups'] as $id) { $pdo->prepare('DELETE FROM tblgroups WHERE ID = ?')->execute([$id]); }
    foreach ($created['districts'] as $id) { $pdo->prepare('DELETE FROM tbldistricts WHERE ID = ?')->execute([$id]); }
    foreach ($created['congs'] as $id) { $pdo->prepare('DELETE FROM tblcongregation WHERE ID = ?')->execute([$id]); }
    foreach ($tmpFiles as $f) { @unlink($f); }
    $left = 0;
    foreach (['tblgroups' => 'groupName', 'tbldistricts' => 'districtName', 'tblbanksubaccounts' => 'AccountName', 'tblcongregation' => 'CongregationName', 'tblexpenses' => 'narration'] as $t => $col) { $left += (int) $pdo->query("SELECT COUNT(*) FROM $t WHERE $col LIKE 'zz t98%'")->fetchColumn(); }
    $left += (int) $pdo->query("SELECT COUNT(*) FROM tblbanktransactions_subaccounts WHERE Narration LIKE 'zz t98%'")->fetchColumn();
    $left += (int) $pdo->query("SELECT COUNT(*) FROM tblcontributions_header WHERE receiptNo LIKE 'ZZ98%'")->fetchColumn();
    $left += (int) $pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$scratch'")->fetchColumn();
    ok98('no fixture row, scratch schema or copy table is left behind', $left === 0, (string) $left);
}
echo "$pass passed / $fail failed\n";
exit($fail ? 1 : 0);

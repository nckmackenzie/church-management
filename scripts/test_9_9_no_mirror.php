<?php
/**
 * Task 9.9: the legacy ledger mirror is gone and the application does not depend on tblledger.
 *   - no application file (app/, public/) mentions tblledger, LEDGER_LEGACY_WRITE, legacyEnabled or mirrorLegacy;
 *   - config.php defines no mirror switch; Ledger has no mirror methods;
 *   - no stored routine or view definition mentions tblledger (information_schema);
 *   - outside the test suites, only the cutover tooling (listed below, with the reason) still names tblledger / tblledger_legacy;
 *   - posting and voiding through Ledger touch the journal tables only (works whether tblledger exists, is renamed or is absent).
 * Read-only apart from one self-cleaning journal entry in the open fiscal year. CLI only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';

$pdo = (new Database)->pdo();
$pass = 0; $fail = 0;
function ok99($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name $extra\n"; } }
function q99($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }

/** every file below $dir (skipping .git, node_modules and the given sub paths) whose text matches $re: [relative path => first match] */
function scan99(string $dir, string $re, array $skip = [], array $ext = ['php', 'js', 'sql', 'md', 'json', 'html', 'txt', 'ini', 'htaccess']): array
{
    $root = realpath($dir); $hits = [];
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($f) use ($root, $skip) {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            if ($f->isDir() && in_array($f->getFilename(), ['.git', 'node_modules'], true)) { return false; }
            foreach ($skip as $s) { if ($rel === $s || strpos($rel, $s . '/') === 0) { return false; } }
            return true;
        }));
    foreach ($it as $f) {
        if (!$f->isFile() || !in_array(strtolower($f->getExtension()), $ext, true) || $f->getSize() > 3000000) { continue; }
        $text = file_get_contents($f->getPathname());
        if (preg_match($re, $text, $m)) { $hits[str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1))] = $m[0]; }
    }
    ksort($hits);
    return $hits;
}

$names = '/tblledger|LEDGER_LEGACY_WRITE|legacyEnabled|mirrorLegacy/i';
$created = ['entries' => []];

try {
    echo "== application code\n";
    $app = scan99(__DIR__ . '/../app', $names);
    ok99('no file under app/ mentions tblledger, LEDGER_LEGACY_WRITE, legacyEnabled or mirrorLegacy', !$app, json_encode($app));
    $pub = scan99(__DIR__ . '/../public', $names);
    ok99('no file under public/ mentions them either', !$pub, json_encode($pub));
    $cfg = file_get_contents(__DIR__ . '/../app/config/config.php');
    ok99('config.php defines no ledger mirror switch (ENVIRONMENT stays)', stripos($cfg, 'LEDGER_LEGACY') === false && strpos($cfg, "define('ENVIRONMENT'") !== false);
    $ledger = new ReflectionClass('Ledger'); $mm = array_map(function ($m) { return strtolower($m->getName()); }, $ledger->getMethods());
    ok99('Ledger has no legacyEnabled / mirrorLegacy method', !in_array('legacyenabled', $mm, true) && !in_array('mirrorlegacy', $mm, true));
    ok99('Ledger::void and post do not name an old table', !preg_match('/tblledger|cash at bank/i', file_get_contents(__DIR__ . '/../app/libraries/Ledger.php')));
    $acct = file_get_contents(__DIR__ . '/../app/models/Account.php');
    ok99('Account::update has no mirror rename rewrite', stripos($acct, 'tblledger') === false && stripos($acct, 'LEGACY') === false);

    echo "== database objects\n";
    $r = q99("SELECT ROUTINE_NAME n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_DEFINITION LIKE '%tblledger%'");
    ok99('no stored routine definition mentions tblledger (' . count(q99("SELECT 1 FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()")) . ' routines checked)', !$r, json_encode(array_column($r, 'n')));
    $v = q99("SELECT TABLE_NAME n FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND VIEW_DEFINITION LIKE '%tblledger%'");
    ok99('no view definition mentions tblledger (' . count(q99("SELECT 1 FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()")) . ' views checked)', !$v, json_encode(array_column($v, 'n')));
    $tr = q99("SELECT TRIGGER_NAME n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND ACTION_STATEMENT LIKE '%tblledger%'");
    ok99('no trigger mentions tblledger', !$tr, json_encode(array_column($tr, 'n')));
    $fk = q99("SELECT TABLE_NAME n FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IN ('tblledger', 'tblledger_legacy')");
    ok99('no table has a foreign key to tblledger / tblledger_legacy (the table can be renamed or dropped)', !$fk, json_encode(array_column($fk, 'n')));

    echo "== what still names the old table (cutover tooling only)\n";
    // each entry: file => why it still reads / names tblledger or tblledger_legacy
    $allowed = [
        'ledger-migration/_doubled_approval.php' => 'doubled-approval repair, works on tblledger before the migration (runbook 2.2 / 2.3)',
        'ledger-migration/audit_8_9_production.sql' => 'audit part A reads tblledger before the migration (runbook 1.3)',
        'ledger-migration/dev_schema_snapshot.sql' => 'dev-only schema reference, never deployed',
        'ledger-migration/dev_tblledger_narration_nullable.sql' => 'dev-only helper, never deployed',
        'ledger-migration/fix_double_approval_closed_year.php' => 'repair before the migration (runbook 2.3)',
        'ledger-migration/fix_double_approval_open_year.php' => 'repair before the migration (runbook 2.2)',
        'ledger-migration/ledger_audit.sql' => 'audit of tblledger before the migration (runbook 1.1)',
        'ledger-migration/ledger_fixes.sql' => 'pre-migration fixes of tblledger (runbook 2.1)',
        'ledger-migration/ledger_schema.sql' => 'journal schema, comments name the source table (runbook 2.4)',
        'ledger-migration/ledger_verify.sql' => 'V0-V9 compare the journal with tblledger_legacy (runbook 3.2)',
        'scripts/drop_unused_routines_views.php' => 'comments naming why the dropped routines were dropped (runbook 6.2)',
        'scripts/migrate_ledger.php' => 'the migration: reads tblledger, renames it to tblledger_legacy (runbook 3.1)',
        'scripts/sql/expense_paidfrom_schema.sql' => 'step 4.6 installs the old delete guard that still reads tblledger; 4.11 replaces it (runbook 4.6, 4.11)',
        'scripts/sql/fn_checkreferencedaccount_v2.sql' => 'comments explaining why v2 has no tblledger term (runbook 4.11)',
    ];
    $found = [];
    foreach (['ledger-migration', 'scripts'] as $d) {
        foreach (scan99(__DIR__ . '/../' . $d, $names, ['logs']) as $rel => $m) {
            $path = $d . '/' . $rel;
            if (preg_match('~^scripts/(test_|dev_)~', $path)) { continue; }
            if (strpos($path, 'ledger-migration/logs/') === 0) { continue; }
            $found[$path] = $m;
        }
    }
    $unexpected = array_diff_key($found, $allowed);
    ok99('every file outside the test suites that names tblledger is in the cutover-tooling list', !$unexpected, json_encode(array_keys($unexpected)));
    $vanished = array_diff_key($allowed, $found);
    ok99('every file in the cutover-tooling list still exists and names the table (the list is exact)', !$vanished, json_encode(array_keys($vanished)));
    $tests = [];
    foreach (glob(__DIR__ . '/test_*.php') as $t) {
        if (basename($t) === basename(__FILE__)) { continue; }
        if (preg_match_all('/(INSERT INTO|UPDATE|DELETE FROM|FROM|JOIN)\s+tblledger\b/i', file_get_contents($t), $mm2)) { $tests[basename($t)] = $mm2[0]; }
    }
    ok99('no test suite reads or writes tblledger', !$tests, json_encode($tests));

    echo "== postings touch the journal only\n";
    $cong = 11;
    $pick = function ($where) use ($pdo, $cong) {
        foreach (q99("SELECT ID FROM tblaccounttypes WHERE deleted = 0 AND active = 1 AND congregationId IN (0, ?) AND $where ORDER BY ID", [$cong]) as $r) { if (Ledger::isPostingAccount($pdo, (int) $r['ID'], $cong)) { return (int) $r['ID']; } }
        return 0;
    };
    $expense = $pick('accountTypeId = 2'); $cash = $pick('isBank = 0 AND parentId = ' . Ledger::CASH_ROOT);
    if ($expense && $cash) {
        $txn = 2000000000 + random_int(1, 99999);
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE tblfiscalyears SET closed = 0, deleted = 0 WHERE ID = 12');
            $je0 = (int) q99('SELECT COUNT(*) n FROM journal_entries')[0]['n'];
            $id = Ledger::post($pdo, ['congregationId' => $cong, 'entryDate' => '2026-01-15', 'transactionTypeId' => 2, 'transactionId' => $txn, 'reference' => 'zz t99', 'description' => 'zz t99'], [Ledger::debit($expense, '12.34'), Ledger::credit($cash, '12.34')]);
            ok99('Ledger::post writes one journal entry with two lines', (int) q99('SELECT COUNT(*) n FROM journal_entries')[0]['n'] === $je0 + 1 && (int) q99('SELECT COUNT(*) n FROM journal_lines WHERE journalEntryId = ?', [$id])[0]['n'] === 2);
            ok99('Ledger::void voids it', Ledger::void($pdo, $cong, 2, $txn) === true && (int) q99('SELECT deleted d FROM journal_entries WHERE ID = ?', [$id])[0]['d'] === 1);
        } catch (Throwable $e) {
            ok99('Ledger post / void in a transaction', false, get_class($e) . ': ' . $e->getMessage());
        }
        $pdo->rollBack();
        ok99('rolled back: the test entry is gone', (int) q99("SELECT COUNT(*) n FROM journal_entries WHERE reference = 'zz t99'")[0]['n'] === 0);
    } else {
        echo "SKIP posting check: no posting accounts found\n";
    }
} catch (Throwable $e) {
    $fail++; echo 'FAIL unexpected exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
}
echo "\n$pass passed / $fail failed\n";
exit($fail ? 1 : 0);

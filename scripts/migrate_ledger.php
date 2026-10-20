<?php
/**
 * migrate_ledger.php - tblledger -> journal_entries / journal_lines (task 4)
 *
 * Place in <app root>\scripts\ and run from the app root, e.g.:
 *   C:\xampp\php\php.exe scripts\migrate_ledger.php --dry-run
 *   C:\xampp\php\php.exe scripts\migrate_ledger.php --commit
 *
 *   --dry-run      validate, write everything in ONE transaction, verify, ROLLBACK
 *   --commit       same, but COMMIT, then rename tblledger -> tblledger_legacy
 *   --no-rename    with --commit: keep tblledger (old screens keep working on local)
 *   --reset        delete existing rows in the 3 journal tables first (same transaction)
 *   --config=PATH  path to config.php (default: <app root>/app/config/config.php)
 *
 * Prerequisites: ledger_fixes.sql, ledger_audit.sql (clean), ledger_schema.sql.
 * Validation runs BEFORE anything is written; any stop condition writes nothing.
 * A log is written to scripts/logs/.
 * Exit codes: 0 = OK, 1 = stopped by validation/verification, 2 = error.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ini_set('memory_limit', '1024M');
set_time_limit(0);

const CAB_FALLBACK_BANK = 105;          // co-op bank
const CAB_CUTOFF        = '2026-05-15'; // no fallback on/after this date
const CAB_TOLERANCE     = 0.5;          // tblbankpostings uses FLOAT

// ---------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------
$opts = ['dry-run' => false, 'commit' => false, 'reset' => false, 'no-rename' => false];
$configPath = dirname(__DIR__) . '/app/config/config.php';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--config=(.+)$/', $arg, $m)) { $configPath = $m[1]; continue; }
    $k = substr($arg, 2);
    if (substr($arg, 0, 2) !== '--' || !array_key_exists($k, $opts)) {
        fwrite(STDERR, "Unknown option: $arg\n");
        exit(2);
    }
    $opts[$k] = true;
}
if ($opts['dry-run'] === $opts['commit']) {
    fwrite(STDERR, "Use exactly one of --dry-run or --commit.\n");
    exit(2);
}
$dryRun = $opts['dry-run'];
$rename = !$dryRun && !$opts['no-rename'];

foreach (['pdo_mysql', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) { fwrite(STDERR, "PHP extension '$ext' is not enabled for the CLI.\n"); exit(2); }
}
if (!is_file($configPath)) {
    fwrite(STDERR, "Config not found: $configPath (use --config=PATH)\n");
    exit(2);
}
require $configPath;

// ---------------------------------------------------------------------
// Logging and helpers
// ---------------------------------------------------------------------
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) { mkdir($logDir, 0775, true); }
if (!is_file("$logDir/.htaccess")) { file_put_contents("$logDir/.htaccess", "Require all denied\n"); }
$logFile = $logDir . '/migrate_ledger_' . date('Ymd_His') . ($dryRun ? '_dryrun' : '_commit') . '.log';
$logFh = fopen($logFile, 'w');

function out(string $msg = ''): void
{
    global $logFh;
    echo $msg, PHP_EOL;
    fwrite($logFh, $msg . PHP_EOL);
}

function finish(int $code): void
{
    global $logFile, $t0;
    out(sprintf('Finished in %.1fs. Log: %s', microtime(true) - $t0, $logFile));
    exit($code);
}

function cents($v): int { return (int) round((float) $v * 100); }

function money(int $c): string { return number_format($c / 100, 2, '.', ','); }

function colLength(PDO $db, string $table, string $col): int
{
    $r = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($col))->fetch();
    if (!$r || !preg_match('/\((\d+)\)/', $r['Type'], $m)) {
        throw new RuntimeException("Can't read the length of $table.$col");
    }
    return (int) $m[1];
}

/** A10 rule: map a 'cash at bank' line to a real bank. Returns [bankId|null, status]. */
function resolveCab(array $l, array $bankPostings, array $accounts): array
{
    $key = $l['congregationId'] . '|' . $l['transactionType'] . '|' . $l['transactionId'];
    $best = null;
    $banks = [];
    foreach ($bankPostings[$key] ?? [] as $b) {
        if (abs((float) $b['debit'] - (float) $l['debit']) >= CAB_TOLERANCE
            || abs((float) $b['credit'] - (float) $l['credit']) >= CAB_TOLERANCE) {
            continue;
        }
        $rnk = (int) ((int) $b['deleted'] !== (int) $l['deleted']);  // same deleted state preferred
        if ($best === null || $rnk < $best) { $best = $rnk; $banks = []; }
        if ($rnk === $best) { $banks[(int) $b['bankId']] = true; }
    }
    if (count($banks) > 1) { return [null, 'AMBIGUOUS']; }
    if (count($banks) === 1) {
        $bankId = array_key_first($banks);
        return (int) ($accounts[$bankId]['isBank'] ?? 0) === 1 ? [$bankId, 'MATCHED'] : [null, 'BANK_NOT_ISBANK'];
    }
    return $l['txnDate'] < CAB_CUTOFF ? [CAB_FALLBACK_BANK, 'FALLBACK'] : [null, 'NO_MATCH_AFTER_CUTOFF'];
}

// ---------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------
$t0 = microtime(true);
$db = null;
out('tblledger migration - ' . ($dryRun ? 'DRY RUN' : 'COMMIT') . ' - ' . date('Y-m-d H:i:s'));

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $db->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    out('Database: ' . DB_NAME . ' @ ' . DB_HOST . ' (server ' . $db->query('SELECT VERSION()')->fetchColumn() . ')');

    // ---- Pre-flight -------------------------------------------------
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $has = fn(string $t): bool => in_array($t, $tables, true);

    if (!$has('tblledger')) {
        throw new RuntimeException('tblledger not found' . ($has('tblledger_legacy')
            ? ' - it was already renamed (migration has run). Restore the backup to repeat.' : '.'));
    }
    foreach (['journal_entries', 'journal_lines', 'journal_number_seq'] as $t) {
        if (!$has($t)) { throw new RuntimeException("$t not found - run ledger_schema.sql first."); }
    }
    if ($rename && $has('tblledger_legacy')) {
        throw new RuntimeException('tblledger_legacy already exists; drop or rename it first, or use --no-rename.');
    }
    $existing = (int) $db->query('SELECT (SELECT COUNT(*) FROM journal_entries)
                                       + (SELECT COUNT(*) FROM journal_lines)
                                       + (SELECT COUNT(*) FROM journal_number_seq)')->fetchColumn();
    if ($existing > 0 && !$opts['reset']) {
        throw new RuntimeException("The journal tables already hold $existing rows. Use --reset to replace them.");
    }

    $memoLen = colLength($db, 'journal_lines', 'memo');
    $refLen  = colLength($db, 'journal_entries', 'reference');
    $descLen = colLength($db, 'journal_entries', 'description');

    // ---- Load reference data ----------------------------------------
    // Posting account (chart rule): not a root, not directly under a root,
    // no non-deleted children, and isSubCategory = 1.
    $accounts = [];
    foreach ($db->query('
            SELECT  a.ID, a.accountType, a.isBank, a.isSubCategory, a.parentId,
                    p.parentId AS grandParentId,
                    (SELECT COUNT(*) FROM tblaccounttypes c
                      WHERE c.parentId = a.ID AND c.deleted = 0) AS childCount
            FROM    tblaccounttypes a
            LEFT JOIN tblaccounttypes p ON p.ID = a.parentId') as $r) {
        $r['isPosting'] = (int) $r['parentId'] !== 0
            && $r['grandParentId'] !== null && (int) $r['grandParentId'] !== 0
            && (int) $r['childCount'] === 0
            && (int) $r['isSubCategory'] === 1;
        $accounts[(int) $r['ID']] = $r;
    }

    $congregations = array_flip(array_map('intval',
        $db->query('SELECT ID FROM tblcongregation')->fetchAll(PDO::FETCH_COLUMN)));

    $bankPostings = [];
    foreach ($db->query('SELECT congregationId, transactionType, transactionId, bankId, deleted, debit, credit
                         FROM tblbankpostings WHERE transactionId IS NOT NULL') as $r) {
        $bankPostings[$r['congregationId'] . '|' . $r['transactionType'] . '|' . $r['transactionId']][] = $r;
    }

    // Ledger lines with their mapped account. The mapping is done in SQL (same
    // logic and collation as audit A2/A8) so name matching behaves identically.
    $ledgerSql = "
        WITH candidates AS (
            SELECT  n.congregationId, n.account, a.ID AS accountTblId,
                    (a.deleted * 2) + (a.congregationId = 0) AS rnk
            FROM    (SELECT DISTINCT congregationId, account FROM tblledger) n
            JOIN    tblaccounttypes a
                    ON  a.accountType = n.account
                    AND a.congregationId IN (n.congregationId, 0)
        ),
        ranked AS (
            SELECT  c.*, MIN(rnk) OVER (PARTITION BY congregationId, account) AS bestRnk
            FROM    candidates c
        ),
        best AS (
            SELECT  congregationId, account, MIN(accountTblId) AS accountTblId, COUNT(*) AS matches
            FROM    ranked
            WHERE   rnk = bestRnk
            GROUP BY congregationId, account
        )
        SELECT  l.ID, l.congregationId, l.transactionType, l.transactionId, l.deleted,
                DATE(l.transactionDate) AS txnDate, l.debit, l.credit,
                l.narration, l.reference, l.account,
                (l.account = 'cash at bank') AS isCab,
                b.accountTblId, COALESCE(b.matches, 0) AS matches
        FROM    tblledger l
        LEFT JOIN best b
                ON  b.congregationId = l.congregationId
                AND b.account        = l.account
        ORDER BY l.ID";

    $groups = [];
    $rowCount = 0;
    foreach ($db->query($ledgerSql) as $r) {
        $rowCount++;
        $groups[$r['congregationId'] . '|' . $r['transactionType'] . '|' . $r['transactionId'] . '|' . $r['deleted']][] = $r;
    }
    out("Read $rowCount ledger rows in " . count($groups) . ' groups.');

    $activeJournals = [];
    foreach ($groups as $lines) {
        if ((int) $lines[0]['transactionType'] === 5 && (int) $lines[0]['deleted'] === 0) {
            $activeJournals[$lines[0]['congregationId'] . '|' . $lines[0]['transactionId']] = true;
        }
    }

    // ---- Validate and build -----------------------------------------
    $errors = [];
    $warnings = [];
    $skipped = [];
    $skippedIds = [];
    $entries = [];
    $cabStats = [];
    $lineTotal = 0;

    foreach ($groups as $lines) {
        $f    = $lines[0];
        $cong = (int) $f['congregationId'];
        $type = (int) $f['transactionType'];
        $txn  = (int) $f['transactionId'];
        $del  = (int) $f['deleted'];
        $label = "cong $cong / type $type / txn $txn" . ($del ? ' (deleted)' : '');
        $ids = array_map(fn($l) => (int) $l['ID'], $lines);

        $dr = 0;
        $cr = 0;
        foreach ($lines as $l) { $dr += cents($l['debit']); $cr += cents($l['credit']); }
        if ($dr !== $cr) {
            $msg = "$label: debits " . money($dr) . ' <> credits ' . money($cr) . ' (lines ' . implode(',', $ids) . ')';
            if ($del) { $skipped[] = $msg; array_push($skippedIds, ...$ids); }
            else      { $errors[] = "UNBALANCED $msg"; }
            continue;
        }
        if (!isset($congregations[$cong])) { $errors[] = "UNKNOWN_CONGREGATION $label"; continue; }

        $dates = array_values(array_unique(array_column($lines, 'txnDate')));
        if (count($dates) > 1) { $errors[] = "MIXED_DATES $label: " . implode(', ', $dates); continue; }
        if ($dates[0] === null) { $errors[] = "NO_DATE $label"; continue; }

        $outLines = [];
        $ok = true;
        $n = 0;
        foreach ($lines as $l) {
            $id = (int) $l['ID'];
            $d = cents($l['debit']);
            $c = cents($l['credit']);
            if ($d < 0 || $c < 0 || ($d > 0) === ($c > 0)) {
                $errors[] = "BAD_AMOUNT line $id ($label): debit {$l['debit']}, credit {$l['credit']}";
                $ok = false;
                continue;
            }

            if ((int) $l['isCab'] === 1) {
                [$accId, $status] = resolveCab($l, $bankPostings, $accounts);
                $statKey = $status . ' -> ' . ($accId ?? '-');
                $cabStats[$statKey] = ($cabStats[$statKey] ?? 0) + 1;
                if ($accId === null) {
                    $errors[] = "CASH_AT_BANK_$status line $id ($label)";
                    $ok = false;
                    continue;
                }
            } elseif ((int) $l['matches'] === 0) {
                $errors[] = "UNMAPPED line $id ($label): '{$l['account']}'";
                $ok = false;
                continue;
            } elseif ((int) $l['matches'] > 1) {
                $errors[] = "AMBIGUOUS line $id ($label): '{$l['account']}'";
                $ok = false;
                continue;
            } else {
                $accId = (int) $l['accountTblId'];
            }

            if (empty($accounts[$accId]['isPosting'])) {
                $errors[] = "NOT_POSTING line $id ($label): account $accId '" . ($accounts[$accId]['accountType'] ?? '?') . "'";
                $ok = false;
                continue;
            }

            $memo = $l['narration'];
            if ($memo !== null && trim($memo) === '') { $memo = null; }
            if ($memo !== null && mb_strlen($memo) > $memoLen) {
                $errors[] = "MEMO_TOO_LONG line $id ($label): " . mb_strlen($memo) . " > $memoLen";
                $ok = false;
                continue;
            }

            $outLines[] = [$accId, $d > 0 ? 'DEBIT' : 'CREDIT', $d > 0 ? $l['debit'] : $l['credit'], $memo, ++$n, $id];
        }
        if (!$ok) { continue; }

        // Header: reference = first non-blank; description = first non-blank narration.
        $ref = null;
        $refs = [];
        $desc = null;
        foreach ($lines as $l) {
            if ($l['reference'] !== null && trim($l['reference']) !== '') {
                $refs[$l['reference']] = true;
                $ref ??= $l['reference'];
            }
            if ($desc === null && $l['narration'] !== null && trim($l['narration']) !== '') {
                $desc = $l['narration'];
            }
        }
        if (count($refs) > 1) {
            $warnings[] = "MIXED_REFERENCE $label: using '$ref' (all: " . implode(' | ', array_keys($refs)) . ')';
        }
        if ($ref !== null && mb_strlen($ref) > $refLen) {
            $errors[] = "REFERENCE_TOO_LONG $label: " . mb_strlen($ref) . " > $refLen";
            continue;
        }

        $journalNo = $type === 5 ? $txn : null;
        if ($type === 5 && $del && isset($activeJournals["$cong|$txn"])) {
            // Voided copy of a reused journal number (journal 151).
            $journalNo = null;
            $desc = rtrim("[voided duplicate of journal $txn] " . ($desc ?? ''));
            $warnings[] = "JOURNAL_NO_REUSED $label: voided copy migrated with journalNo NULL";
        }
        if ($desc !== null && mb_strlen($desc) > $descLen) { $desc = mb_substr($desc, 0, $descLen); }

        $entries[] = ['h' => [$cong, $dates[0], $type, $txn, $journalNo, $ref, $desc, $del], 'lines' => $outLines];
        $lineTotal += count($outLines);
    }

    // ---- Report validation ------------------------------------------
    $activeCount = count(array_filter($entries, fn($e) => $e['h'][7] === 0));
    out();
    out('Entries to migrate: ' . count($entries) . " ($activeCount active, " . (count($entries) - $activeCount) . " deleted), $lineTotal lines.");
    out();
    out("'cash at bank' lines:");
    ksort($cabStats);
    foreach ($cabStats as $k => $v) { out("  $k: $v"); }
    out();
    out('Skipped (deleted and unbalanced, kept only in tblledger_legacy): ' . count($skipped));
    foreach ($skipped as $s) { out("  $s"); }
    out();
    out('Warnings: ' . count($warnings));
    foreach ($warnings as $w) { out("  $w"); }
    out();

    if ($errors) {
        out('STOPPED - ' . count($errors) . ' error(s). Nothing was written:');
        foreach ($errors as $e) { out("  $e"); }
        finish(1);
    }
    out('Validation passed.');

    // ---- Write ------------------------------------------------------
    $db->beginTransaction();

    if ($opts['reset']) {
        $db->exec('DELETE FROM journal_lines');
        $db->exec('DELETE FROM journal_entries');
        $db->exec('DELETE FROM journal_number_seq');
        out('Reset: existing journal rows deleted.');
    }

    $insE = $db->prepare('INSERT INTO journal_entries
        (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insL = $db->prepare('INSERT INTO journal_lines
        (journalEntryId, accountId, dc, amount, memo, lineNumber, legacyLedgerId)
        VALUES (?, ?, ?, ?, ?, ?, ?)');

    foreach ($entries as $e) {
        $insE->execute($e['h']);
        $entryId = (int) $db->lastInsertId();
        foreach ($e['lines'] as $ln) {
            $insL->execute(array_merge([$entryId], $ln));
        }
    }

    // Journal numbers are never reused: seed from the highest ever used, deleted rows included.
    $db->exec('INSERT INTO journal_number_seq (congregationId, lastJournalNo)
               SELECT  c.ID, GREATEST(COALESCE(MAX(l.journalNo), 0), COALESCE(MAX(l.transactionId), 0))
               FROM    tblcongregation c
               LEFT JOIN tblledger l ON l.congregationId = c.ID AND l.transactionType = 5
               GROUP BY c.ID');
    foreach ($db->query('SELECT congregationId, lastJournalNo FROM journal_number_seq WHERE lastJournalNo > 0') as $r) {
        out("Journal number sequence: congregation {$r['congregationId']} -> last {$r['lastJournalNo']}");
    }

    // ---- Verify (inside the transaction) -----------------------------
    $fail = [];

    $gotEntries = (int) $db->query('SELECT COUNT(*) FROM journal_entries')->fetchColumn();
    $gotLines   = (int) $db->query('SELECT COUNT(*) FROM journal_lines WHERE legacyLedgerId IS NOT NULL')->fetchColumn();
    if ($gotEntries !== count($entries)) { $fail[] = "entry count $gotEntries, expected " . count($entries); }
    if ($gotLines !== $lineTotal)        { $fail[] = "line count $gotLines, expected $lineTotal"; }
    if ($gotLines !== $rowCount - count($skippedIds)) {
        $fail[] = "line count $gotLines <> ledger rows $rowCount - skipped " . count($skippedIds);
    }

    $totals = function (array $rows): array {
        $t = [];
        foreach ($rows as $r) { $t[$r['congregationId'] . '|' . $r['deleted']] = [cents($r['dr']), cents($r['cr'])]; }
        ksort($t);
        return $t;
    };
    $newTotals = $totals($db->query("
        SELECT  e.congregationId, e.deleted,
                SUM(IF(l.dc = 'DEBIT',  l.amount, 0)) AS dr,
                SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr
        FROM    journal_lines l
        JOIN    journal_entries e ON e.ID = l.journalEntryId
        GROUP BY e.congregationId, e.deleted")->fetchAll());
    $skipList = $skippedIds ?: [0];
    $st = $db->prepare('SELECT congregationId, deleted, SUM(debit) AS dr, SUM(credit) AS cr
                        FROM tblledger WHERE ID NOT IN (' . implode(',', array_fill(0, count($skipList), '?')) . ')
                        GROUP BY congregationId, deleted');
    $st->execute($skipList);
    $oldTotals = $totals($st->fetchAll());
    if ($newTotals !== $oldTotals) { $fail[] = 'debit/credit totals per congregation differ from tblledger'; }
    foreach ($newTotals as $k => [$d, $c]) {
        out("Totals $k (congregation|deleted): debit " . money($d) . ', credit ' . money($c));
    }

    $unbalanced = (int) $db->query("
        SELECT COUNT(*) FROM (
            SELECT  journalEntryId
            FROM    journal_lines
            GROUP BY journalEntryId
            HAVING  SUM(IF(dc = 'DEBIT', amount, 0)) <> SUM(IF(dc = 'CREDIT', amount, 0))
        ) x")->fetchColumn();
    if ($unbalanced > 0) { $fail[] = "$unbalanced unbalanced entries"; }

    $nonPosting = (int) $db->query('
        SELECT  COUNT(*)
        FROM    journal_lines jl
        JOIN    tblaccounttypes a   ON a.ID = jl.accountId
        LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
        WHERE   a.isSubCategory <> 1 OR a.parentId = 0 OR p.ID IS NULL OR p.parentId = 0
           OR   EXISTS (SELECT 1 FROM tblaccounttypes c WHERE c.parentId = a.ID AND c.deleted = 0)')->fetchColumn();
    if ($nonPosting > 0) { $fail[] = "$nonPosting lines on non-posting accounts"; }

    if ($fail) {
        $db->rollBack();
        out();
        out('VERIFICATION FAILED - rolled back, nothing saved:');
        foreach ($fail as $f) { out("  $f"); }
        finish(1);
    }
    out('Verification passed.');

    // ---- Finish -----------------------------------------------------
    if ($dryRun) {
        $db->rollBack();
        out('DRY RUN: rolled back. Nothing was saved.');
        finish(0);
    }

    $db->commit();
    out('COMMITTED.');

    if ($rename) {
        try {
            $db->exec('RENAME TABLE tblledger TO tblledger_legacy');
            out('Renamed tblledger -> tblledger_legacy.');
        } catch (Throwable $e) {
            out('Data is committed, but the rename failed: ' . $e->getMessage());
            out('Run manually: RENAME TABLE tblledger TO tblledger_legacy;');
            finish(2);
        }
    } else {
        out('tblledger kept (--no-rename).');
    }
    finish(0);

} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) { $db->rollBack(); }
    out('ERROR: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    out('Nothing was saved.');
    finish(2);
}
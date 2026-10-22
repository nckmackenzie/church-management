<?php
/**
 * expense_paidfrom_backfill.php - tblexpenses.PaidFromAccountId backfill from the ledger (task 8.5c)
 *
 * Run from the app root:
 *   C:\xampp\php\php.exe scripts\expense_paidfrom_backfill.php --dry-run
 *   C:\xampp\php\php.exe scripts\expense_paidfrom_backfill.php --commit
 *
 *   --dry-run      validate, write everything in ONE transaction, verify, ROLLBACK
 *   --commit       same, but COMMIT (a second --commit changes nothing)
 *   --expect-dev   also compare the PaidFromAccountId totals with the 8.5b dev summary (leave it off on production)
 *   --config=PATH  path to config.php (default: <app root>/app/config/config.php)
 *
 * Prerequisite: scripts/sql/expense_paidfrom_schema.sql has been run.
 * The ledger (journal_entries / journal_lines) is the source of truth; the old text columns are used only where an
 * expense has no entry. No expense IDs are hard-coded: every rule is data-driven and anything outside the rules stops
 * the run before a single row is written.
 *
 *   Rule A  PaidFromAccountId = the entry's credit account; no entry -> text mapping (98 / 194 / 34 / 121 / bankId) or NULL
 *   Rule B  accountId = the entry's debit account when it differs and is a descendant of the current accountId
 *   Rule C  petty cash rows for approved expenses credited to a petty cash account, in OPEN financial years only
 *
 * Exit codes: 0 = OK, 1 = stopped by a rule or verification, 2 = error.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ini_set('memory_limit', '512M');
set_time_limit(0);

// accounts the text mapping resolves to (ID => expected chart name, lower case)
const MAP_PETTY_CASH = 98;
const MAP_DEV_PETTY  = 194;
const MAP_CASH_HAND  = 34;
const MAP_CASH_HOLD  = 121;
const EXPECTED_NAMES = [98 => 'petty cash', 194 => 'petty cash development', 34 => 'cash at hand', 121 => 'cash holding account'];

// 8.5b proposed-value summary on dev: account => [count, total]; null = no usable account
const DEV_EXPECTED = [
    98  => [766, 9085280.00],
    105 => [322, 20846149.00],
    121 => [131, 2389099.00],
    194 => [35, 1121615.00],
    34  => [19, 52826.00],
    195 => [7, 2974555.00],
    0   => [3, 65000.00],   // NULL
];

// ---------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------
$opts = ['dry-run' => false, 'commit' => false, 'expect-dev' => false];
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

foreach (['pdo_mysql', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) { fwrite(STDERR, "PHP extension '$ext' is not enabled for the CLI.\n"); exit(2); }
}
if (!is_file($configPath)) { fwrite(STDERR, "Config not found: $configPath (use --config=PATH)\n"); exit(2); }
require $configPath;
require dirname(__DIR__) . '/app/libraries/Ledger.php';

// ---------------------------------------------------------------------
// Logging and helpers
// ---------------------------------------------------------------------
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) { mkdir($logDir, 0775, true); }
if (!is_file("$logDir/.htaccess")) { file_put_contents("$logDir/.htaccess", "Require all denied\n"); }
$logFile = $logDir . '/expense_paidfrom_' . date('Ymd_His') . ($dryRun ? '_dryrun' : '') . '.log';
$logFh = fopen($logFile, 'w');
$t0 = microtime(true);

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

/** "1,2,3" style list, capped so one stop does not flood the log. */
function ids(array $a, int $cap = 400): string
{
    $a = array_values($a);
    $s = implode(',', array_slice($a, 0, $cap));
    return $s . (count($a) > $cap ? ' ... (+' . (count($a) - $cap) . ' more)' : '');
}

function colLength(PDO $db, string $table, string $col): int
{
    $r = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($col))->fetch();
    if (!$r || !preg_match('/\((\d+)\)/', $r['Type'], $m)) { throw new RuntimeException("Can't read the length of $table.$col"); }
    return (int) $m[1];
}

/** Row count + CRC32 sum over every column except $skip (and an optional WHERE). */
function checksum(PDO $db, string $table, string $where = '', array $skip = []): array
{
    $cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "
                       . $db->quote($table) . ' ORDER BY ORDINAL_POSITION')->fetchAll(PDO::FETCH_COLUMN);
    $parts = [];
    foreach ($cols as $c) {
        if (!in_array($c, $skip, true)) { $parts[] = "IFNULL(CAST(`$c` AS CHAR),'~')"; }
    }
    $sql = "SELECT COUNT(*) AS n, IFNULL(SUM(CRC32(CONCAT_WS('#'," . implode(',', $parts) . '))),0) AS crc FROM `' . $table . '`'
         . ($where !== '' ? " WHERE $where" : '');
    $r = $db->query($sql)->fetch();
    return [(int) $r['n'], (string) $r['crc']];
}

/** SQL condition: the row's date falls in a closed or deleted financial year (same pick as Ledger::yearForDate). */
function closedYearCond(string $dateCol): string
{
    return "(SELECT (y.closed = 1 OR y.deleted = 1) FROM tblfiscalyears y WHERE $dateCol BETWEEN y.startDate AND y.endDate "
         . 'ORDER BY y.deleted ASC, y.ID DESC LIMIT 1) = 1';
}

out('tblexpenses.PaidFromAccountId backfill - ' . ($dryRun ? 'DRY RUN' : 'COMMIT') . ' - ' . date('Y-m-d H:i:s'));

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

    // -----------------------------------------------------------------
    // Prerequisites
    // -----------------------------------------------------------------
    $has = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME = 'tblexpenses' AND COLUMN_NAME = 'PaidFromAccountId'")->fetchColumn();
    if ($has === 0) { out('STOP: tblexpenses.PaidFromAccountId does not exist. Run scripts/sql/expense_paidfrom_schema.sql first.'); finish(1); }

    $accounts = [];
    foreach ($db->query('SELECT ID, accountType, parentId FROM tblaccounttypes') as $a) { $accounts[(int) $a['ID']] = $a; }
    $problems = [];
    foreach (EXPECTED_NAMES as $id => $name) {
        $got = isset($accounts[$id]) ? strtolower(trim($accounts[$id]['accountType'])) : null;
        if ($got !== $name) {
            $problems[] = "account $id should be '$name' but is " . ($got === null ? 'missing' : "'$got'");
        }
    }
    if ($problems) { out('STOP: the chart of accounts does not match the text-mapping constants:'); foreach ($problems as $p) { out("  - $p"); } finish(1); }

    $pettyAccounts = Ledger::PETTY_CASH_ACCOUNTS;
    $refLen = colLength($db, 'tblpettycash', 'Reference');
    $narLen = colLength($db, 'tblpettycash', 'Narration');

    // -----------------------------------------------------------------
    // Load
    // -----------------------------------------------------------------
    $expenses = [];
    foreach ($db->query('SELECT ID, congregationId, status, deleted, expenseDate, ROUND(amount, 2) AS amount, accountId, paymethodId,
                                deductfrom, bankId, requisitionId, PaidFromAccountId, narration, paymentReference
                         FROM tblexpenses ORDER BY ID') as $e) {
        $expenses[(int) $e['ID']] = $e;
    }
    out('Expenses: ' . count($expenses));

    // every type-2 entry with its line summary, keyed by transactionId (any congregation, to catch strays)
    $entries = [];
    $st = $db->query("SELECT j.ID, j.congregationId, j.transactionId, j.entryDate, j.deleted,
                             COUNT(l.ID) AS line_n, SUM(l.dc = 'DEBIT') AS dr_n, SUM(l.dc = 'CREDIT') AS cr_n,
                             SUM(IF(l.dc = 'DEBIT', l.amount, 0)) AS dr_amt, SUM(IF(l.dc = 'CREDIT', l.amount, 0)) AS cr_amt,
                             MAX(IF(l.dc = 'DEBIT', l.accountId, NULL)) AS dr_acct, MAX(IF(l.dc = 'CREDIT', l.accountId, NULL)) AS cr_acct
                      FROM journal_entries j LEFT JOIN journal_lines l ON l.journalEntryId = j.ID
                      WHERE j.transactionTypeId = 2 GROUP BY j.ID ORDER BY j.ID");
    foreach ($st as $j) { $entries[(int) $j['transactionId']][] = $j; }
    $orphans = array_diff(array_keys($entries), array_keys($expenses));

    $petty = [];   // active petty cash rows of expenses
    foreach ($db->query('SELECT ID, ExpenseId, TransactionDate, Credit, PettyCashAccountId, CongregationId
                         FROM tblpettycash WHERE ExpenseId IS NOT NULL AND Deleted = 0 ORDER BY ID') as $p) {
        $petty[(int) $p['ExpenseId']][] = $p;
    }

    // -----------------------------------------------------------------
    // Pick the entry of every expense and validate (nothing is written yet)
    // -----------------------------------------------------------------
    $stops = [];                 // label => [expense IDs]
    $infoNoEntryReset = [];      // drafts whose only entries are deleted (an unapproved expense)
    $chosen = [];                // expense ID => entry row | null
    $addStop = function (string $label, int $id) use (&$stops) { $stops[$label][] = $id; };

    foreach ($expenses as $id => $e) {
        $cong = (int) $e['congregationId'];
        $all = $entries[$id] ?? [];
        $other = array_filter($all, function ($j) use ($cong) { return (int) $j['congregationId'] !== $cong; });
        if ($other) { $addStop('entry in another congregation', $id); }
        $same = array_values(array_filter($all, function ($j) use ($cong) { return (int) $j['congregationId'] === $cong; }));
        $active = array_values(array_filter($same, function ($j) { return (int) $j['deleted'] === 0; }));
        $deletedEntries = array_values(array_filter($same, function ($j) { return (int) $j['deleted'] === 1; }));

        $entry = null;
        if (count($active) > 1) {
            $addStop('more than one active entry', $id);
        } elseif ((int) $e['deleted'] === 1) {
            if ($active) { $addStop('active entry on a deleted expense', $id); }
            elseif ($deletedEntries) { $entry = end($deletedEntries); }
        } elseif ($active) {
            if ((int) $e['status'] === 0) { $addStop('active entry on an unapproved (draft) expense', $id); }
            else { $entry = $active[0]; }
        } elseif ($deletedEntries) {
            if ((int) $e['status'] === 1) { $addStop('approved expense whose only entries are deleted', $id); }
            else { $infoNoEntryReset[] = $id; }   // approval was reset: no entry, text mapping applies
        }

        if ($entry !== null) {
            if ((int) $entry['line_n'] !== 2 || (int) $entry['dr_n'] !== 1 || (int) $entry['cr_n'] !== 1) {
                $addStop('entry without exactly one debit and one credit line', $id);
            } else {
                if (cents($entry['dr_amt']) !== cents($e['amount']) || cents($entry['cr_amt']) !== cents($e['amount'])) {
                    $addStop('entry amount differs from the expense amount', $id);
                }
                if ($entry['entryDate'] !== $e['expenseDate']) { $addStop('entry date differs from the expense date', $id); }
                foreach (['dr_acct', 'cr_acct'] as $k) {
                    if (!isset($accounts[(int) $entry[$k]])) { $addStop('entry uses an account that does not exist', $id); }
                }
            }
        }
        $chosen[$id] = $entry;
    }

    // -----------------------------------------------------------------
    // Rule A: PaidFromAccountId
    // -----------------------------------------------------------------
    $newPaid = [];        // expense ID => account ID | null
    $nullRows = [];       // expense ID => reason
    $fromLedger = $fromText = 0;
    foreach ($expenses as $id => $e) {
        $entry = $chosen[$id];
        if ($entry !== null && (int) $entry['line_n'] === 2 && (int) $entry['cr_n'] === 1) {
            $acct = (int) $entry['cr_acct'];
            $fromLedger++;
        } else {
            $fromText++;
            $acct = null;
            $pm = (int) $e['paymethodId'];
            $d = $e['deductfrom'];
            if ($pm === 1) {
                if ($e['requisitionId'] !== null || $d === 'cash holding' || ($d !== null && ctype_digit(trim($d)))) { $acct = MAP_CASH_HOLD; }
                elseif ($d === 'petty cash')             { $acct = MAP_PETTY_CASH; }
                elseif ($d === 'development petty cash') { $acct = MAP_DEV_PETTY; }
                elseif ($d === 'cash at hand')           { $acct = MAP_CASH_HAND; }
            } elseif ($pm >= 2 && (int) $e['bankId'] > 0) {
                $acct = (int) $e['bankId'];
                if (!isset($accounts[$acct])) { $addStop('bankId is not an account', $id); $acct = null; }
            }
            if ($acct === null) { $nullRows[$id] = 'no entry and no usable text (method ' . $pm . ', deductfrom ' . var_export($d, true) . ', bankId ' . var_export($e['bankId'], true) . ')'; }
        }
        $newPaid[$id] = $acct;
        $cur = $e['PaidFromAccountId'];
        if ($cur !== null && (int) $cur !== (int) $acct) { $addStop('PaidFromAccountId already set to a different value', $id); }
        if ($acct === null && (int) $e['status'] === 1 && (int) $e['deleted'] === 0) { $addStop('NULL PaidFromAccountId on an approved, non-deleted expense', $id); }
    }

    // -----------------------------------------------------------------
    // Rule B: expense-side accountId
    // -----------------------------------------------------------------
    $isDescendant = function (int $child, int $ancestor) use ($accounts): bool {
        $seen = [];
        $cur = $child;
        while (isset($accounts[$cur]) && !isset($seen[$cur])) {
            $seen[$cur] = true;
            $p = (int) $accounts[$cur]['parentId'];
            if ($p === $ancestor) { return true; }
            if ($p === 0) { return false; }
            $cur = $p;
        }
        return false;
    };
    $newAccount = [];     // expense ID => debit account (only the ones that change)
    foreach ($expenses as $id => $e) {
        $entry = $chosen[$id];
        if ($entry === null || (int) $entry['dr_n'] !== 1 || (int) $entry['line_n'] !== 2) { continue; }
        $dr = (int) $entry['dr_acct'];
        if ($dr === (int) $e['accountId']) { continue; }
        if ($isDescendant($dr, (int) $e['accountId'])) { $newAccount[$id] = $dr; }
        else { $addStop('ledger debit account is not a descendant of the expense accountId', $id); }
    }

    // -----------------------------------------------------------------
    // Rule C: petty cash rows (open financial years only)
    // -----------------------------------------------------------------
    $yearCache = [];
    $openYear = function (string $date) use ($db, &$yearCache): ?array {
        if (!array_key_exists($date, $yearCache)) {
            $y = Ledger::yearForDate($db, $date);
            $yearCache[$date] = ($y !== null && (int) $y['closed'] === 0 && (int) $y['deleted'] === 0) ? $y : null;
        }
        return $yearCache[$date];
    };
    $pettyInserts = [];       // expense ID => row
    $infoClosed = [];         // closed/deleted year petty cash mismatches (information only)
    $mapLenStops = [];
    foreach ($expenses as $id => $e) {
        $entry = $chosen[$id];
        $open = $openYear($e['expenseDate']) !== null;
        $approved = (int) $e['status'] === 1 && (int) $e['deleted'] === 0;
        $creditAcct = ($entry !== null && (int) $entry['cr_n'] === 1 && (int) $entry['line_n'] === 2) ? (int) $entry['cr_acct'] : null;
        $pettyCredit = $approved && $creditAcct !== null && in_array($creditAcct, $pettyAccounts, true);
        $rows = $petty[$id] ?? [];

        if (!$open) {
            // information only: what is out of step in a closed / deleted / undefined year
            if ($rows && !$pettyCredit)                              { $infoClosed[$id] = 'active petty cash row, ledger credit ' . var_export($creditAcct, true); }
            elseif ($rows && $creditAcct !== null && (int) $rows[0]['PettyCashAccountId'] !== $creditAcct) { $infoClosed[$id] = 'petty cash account ' . $rows[0]['PettyCashAccountId'] . ' <> ledger credit ' . $creditAcct; }
            elseif (!$rows && $pettyCredit)                          { $infoClosed[$id] = 'credited to ' . $creditAcct . ' but no petty cash row'; }
            continue;
        }

        if (count($rows) > 1) { $addStop('more than one active petty cash row', $id); continue; }
        if ($rows && !$pettyCredit) { $addStop('active petty cash row on an open-year expense not credited to a petty cash account', $id); continue; }
        if ($rows && $pettyCredit) {
            $r = $rows[0];
            if ((int) $r['PettyCashAccountId'] !== $creditAcct || cents($r['Credit']) !== cents($e['amount']) || $r['TransactionDate'] !== $e['expenseDate']) {
                $addStop('petty cash row account / amount / date differs from the entry', $id);
            }
            continue;
        }
        if (!$rows && $pettyCredit) {
            $ref = $e['paymentReference'] !== null ? mb_strtolower($e['paymentReference']) : null;
            $nar = $e['narration'] !== null ? mb_strtolower($e['narration']) : null;
            if (($ref !== null && mb_strlen($ref) > $refLen) || ($nar !== null && mb_strlen($nar) > $narLen)) {
                $addStop('reference / narration longer than the tblpettycash column', $id);
                continue;
            }
            $pettyInserts[$id] = [
                'TransactionDate' => $e['expenseDate'], 'Credit' => $e['amount'], 'IsReceipt' => 0, 'Reference' => $ref,
                'Narration' => $nar, 'ExpenseId' => $id, 'CongregationId' => (int) $e['congregationId'], 'PettyCashAccountId' => $creditAcct,
            ];
        }
    }

    // -----------------------------------------------------------------
    // Report the plan; stop before writing if any rule was broken
    // -----------------------------------------------------------------
    $changePaid = array_filter($expenses, function ($e) use ($newPaid) { return $newPaid[(int) $e['ID']] !== null && $e['PaidFromAccountId'] === null; });
    out('');
    out('Entries: ' . $fromLedger . ' expenses take the ledger credit account, ' . $fromText . ' use the text mapping / NULL.');
    if ($orphans) { out('Information: ' . count($orphans) . ' type-2 entries have no expense: ' . ids($orphans)); }
    if ($infoNoEntryReset) { out('Information: ' . count($infoNoEntryReset) . ' unapproved expenses have only deleted entries (approval reset), text mapping used: ' . ids($infoNoEntryReset)); }
    out('Rule A: ' . count($changePaid) . ' expenses get a PaidFromAccountId; ' . count($nullRows) . ' stay NULL.');
    foreach ($nullRows as $id => $why) {
        $e = $expenses[$id];
        out(sprintf('  NULL  expense %d (status %d, deleted %d, %s, %s): %s', $id, $e['status'], $e['deleted'], $e['expenseDate'], $e['amount'], $why));
    }
    out('Rule B: ' . count($newAccount) . ' expenses get accountId = the ledger debit account.');
    foreach ($newAccount as $id => $dr) {
        out(sprintf('  expense %d: accountId %d (%s) -> %d (%s)', $id, $expenses[$id]['accountId'], $accounts[(int) $expenses[$id]['accountId']]['accountType'] ?? '?', $dr, $accounts[$dr]['accountType']));
    }
    $pcTot = [];
    foreach ($pettyInserts as $r) { $pcTot[$r['PettyCashAccountId']][0] = ($pcTot[$r['PettyCashAccountId']][0] ?? 0) + 1; $pcTot[$r['PettyCashAccountId']][1] = ($pcTot[$r['PettyCashAccountId']][1] ?? 0) + cents($r['Credit']); }
    out('Rule C: ' . count($pettyInserts) . ' petty cash rows to insert (open years only).');
    foreach ($pcTot as $a => $v) { out(sprintf('  account %d (%s): %d rows, %s', $a, $accounts[$a]['accountType'], $v[0], money($v[1]))); }
    out('  expenses: ' . ids(array_keys($pettyInserts)));
    out('Information: closed / deleted / undefined year petty cash mismatches left untouched: ' . count($infoClosed));
    foreach ($infoClosed as $id => $why) { out("  expense $id: $why"); }

    if ($stops) {
        out('');
        out('STOP: the following rules were broken. Nothing was written.');
        foreach ($stops as $label => $list) { out(sprintf('  - %s (%d): %s', $label, count(array_unique($list)), ids(array_unique($list)))); }
        finish(1);
    }

    // -----------------------------------------------------------------
    // Checksums before
    // -----------------------------------------------------------------
    $closedPettyWhere = closedYearCond('TransactionDate');
    $before = [
        'closed-year petty cash' => checksum($db, 'tblpettycash', $closedPettyWhere),
        'journal_entries'        => checksum($db, 'journal_entries'),
        'journal_lines'          => checksum($db, 'journal_lines'),
        'tblbankpostings'        => checksum($db, 'tblbankpostings'),
        'tblexpenses (other columns)' => checksum($db, 'tblexpenses', '', ['PaidFromAccountId', 'accountId']),
    ];
    $closedPettyMax = (int) $db->query("SELECT IFNULL(MAX(ID),0) FROM tblpettycash WHERE $closedPettyWhere")->fetchColumn();
    $closedPettySum = (string) $db->query("SELECT IFNULL(SUM(Credit),0) + IFNULL(SUM(Debit),0) FROM tblpettycash WHERE $closedPettyWhere")->fetchColumn();

    // -----------------------------------------------------------------
    // Write (one transaction)
    // -----------------------------------------------------------------
    $db->beginTransaction();
    $nPaid = $nAcct = $nPetty = 0;
    $updPaid = $db->prepare('UPDATE tblexpenses SET PaidFromAccountId = ? WHERE ID = ? AND PaidFromAccountId IS NULL');
    $updAcct = $db->prepare('UPDATE tblexpenses SET accountId = ? WHERE ID = ?');
    $insPetty = $db->prepare('INSERT INTO tblpettycash (TransactionDate, Credit, IsReceipt, Reference, Narration, ExpenseId, CongregationId, PettyCashAccountId)
                              VALUES (:TransactionDate, :Credit, :IsReceipt, :Reference, :Narration, :ExpenseId, :CongregationId, :PettyCashAccountId)');
    foreach ($changePaid as $id => $e) {
        $updPaid->execute([$newPaid[$id], $id]);
        $nPaid += $updPaid->rowCount();
    }
    foreach ($newAccount as $id => $dr) {
        $updAcct->execute([$dr, $id]);
        $nAcct += $updAcct->rowCount();
    }
    foreach ($pettyInserts as $r) {
        $insPetty->execute($r);
        $nPetty += $insPetty->rowCount();
    }
    out('');
    out("Written: PaidFromAccountId $nPaid, accountId $nAcct, petty cash rows $nPetty");

    // -----------------------------------------------------------------
    // Verification (any failure -> ROLLBACK, exit 1)
    // -----------------------------------------------------------------
    $fail = [];
    $now = [];
    foreach ($db->query('SELECT ID, PaidFromAccountId, accountId FROM tblexpenses') as $r) { $now[(int) $r['ID']] = $r; }
    $bad = [];
    foreach ($expenses as $id => $e) {
        $entry = $chosen[$id];
        if ($entry !== null && (int) $entry['line_n'] === 2) {
            if ((int) $now[$id]['PaidFromAccountId'] !== (int) $entry['cr_acct'] || (int) $now[$id]['accountId'] !== (int) $entry['dr_acct']) { $bad[] = $id; }
        } elseif ($now[$id]['PaidFromAccountId'] !== null ? (int) $now[$id]['PaidFromAccountId'] !== (int) $newPaid[$id] : $newPaid[$id] !== null) {
            $bad[] = $id;
        }
    }
    if ($bad) { $fail[] = 'expenses whose PaidFromAccountId / accountId do not match the ledger: ' . ids($bad); }
    out('Verify 1: every expense with an entry has PaidFromAccountId = credit account and accountId = debit account: ' . ($bad ? 'FAILED' : 'OK'));

    // per-account table
    $sum = [];
    foreach ($db->query('SELECT PaidFromAccountId AS a, COUNT(*) AS n, SUM(amount) AS t FROM tblexpenses GROUP BY PaidFromAccountId ORDER BY n DESC') as $r) {
        $sum[(int) $r['a']] = [(int) $r['n'], cents($r['t'])];   // NULL account -> 0
    }
    out('Verify 2: PaidFromAccountId per account' . ($opts['expect-dev'] ? ' (compared with the 8.5b dev summary)' : ' (not compared)') . ':');
    out(sprintf('  %-8s %-28s %6s %16s %s', 'account', 'name', 'count', 'total', $opts['expect-dev'] ? 'expected' : ''));
    $keys = array_unique(array_merge(array_keys($sum), $opts['expect-dev'] ? array_keys(DEV_EXPECTED) : []));
    foreach ($keys as $a) {
        $v = $sum[$a] ?? [0, 0];
        $name = $a === 0 ? '(NULL)' : ($accounts[$a]['accountType'] ?? '?');
        $note = '';
        if ($opts['expect-dev']) {
            $x = DEV_EXPECTED[$a] ?? [0, 0.0];
            $ok = $v[0] === $x[0] && $v[1] === cents($x[1]);
            $note = $ok ? sprintf('%d / %s OK', $x[0], money(cents($x[1]))) : sprintf('%d / %s MISMATCH', $x[0], money(cents($x[1])));
            if (!$ok) { $fail[] = "account $a: got {$v[0]} / " . money($v[1]) . ", expected {$x[0]} / " . money(cents($x[1])); }
        }
        out(sprintf('  %-8s %-28s %6d %16s %s', $a === 0 ? '-' : $a, $name, $v[0], money($v[1]), $note));
    }

    // petty cash vs ledger per open year and petty cash account
    out('Verify 3: expense petty cash credits vs type-2 entry credits, per open year and petty cash account:');
    $years = $db->query('SELECT ID, yearName, startDate, endDate FROM tblfiscalyears WHERE closed = 0 AND deleted = 0 ORDER BY startDate')->fetchAll();
    $pcSql = $db->prepare('SELECT IFNULL(SUM(Credit),0) FROM tblpettycash WHERE ExpenseId IS NOT NULL AND Deleted = 0
                           AND PettyCashAccountId = ? AND TransactionDate BETWEEN ? AND ?');
    $enSql = $db->prepare("SELECT IFNULL(SUM(l.amount),0) FROM journal_entries j JOIN journal_lines l ON l.journalEntryId = j.ID
                           WHERE j.transactionTypeId = 2 AND j.deleted = 0 AND l.dc = 'CREDIT' AND l.accountId = ? AND j.entryDate BETWEEN ? AND ?");
    foreach ($years as $y) {
        foreach ($pettyAccounts as $a) {
            $pcSql->execute([$a, $y['startDate'], $y['endDate']]);
            $enSql->execute([$a, $y['startDate'], $y['endDate']]);
            $p = cents($pcSql->fetchColumn());
            $en = cents($enSql->fetchColumn());
            out(sprintf('  %-10s account %-4d petty cash %16s   ledger %16s   %s', $y['yearName'], $a, money($p), money($en), $p === $en ? 'OK' : 'MISMATCH'));
            if ($p !== $en) { $fail[] = "petty cash vs ledger differs in {$y['yearName']} for account $a"; }
        }
    }

    // untouched data
    $after = [
        'closed-year petty cash' => checksum($db, 'tblpettycash', $closedPettyWhere),
        'journal_entries'        => checksum($db, 'journal_entries'),
        'journal_lines'          => checksum($db, 'journal_lines'),
        'tblbankpostings'        => checksum($db, 'tblbankpostings'),
        'tblexpenses (other columns)' => checksum($db, 'tblexpenses', '', ['PaidFromAccountId', 'accountId']),
    ];
    $closedPettyMaxAfter = (int) $db->query("SELECT IFNULL(MAX(ID),0) FROM tblpettycash WHERE $closedPettyWhere")->fetchColumn();
    $closedPettySumAfter = (string) $db->query("SELECT IFNULL(SUM(Credit),0) + IFNULL(SUM(Debit),0) FROM tblpettycash WHERE $closedPettyWhere")->fetchColumn();
    out('Verify 4: untouched tables (count + checksum before / after):');
    foreach ($before as $k => $b) {
        $ok = $b === $after[$k];
        out(sprintf('  %-28s %6d %-12s -> %6d %-12s %s', $k, $b[0], $b[1], $after[$k][0], $after[$k][1], $ok ? 'OK' : 'CHANGED'));
        if (!$ok) { $fail[] = "$k changed"; }
    }
    $okClosed = $closedPettyMax === $closedPettyMaxAfter && $closedPettySum === $closedPettySumAfter;
    out(sprintf('  closed-year petty cash MAX(ID) %d -> %d, SUM %s -> %s: %s', $closedPettyMax, $closedPettyMaxAfter, $closedPettySum, $closedPettySumAfter, $okClosed ? 'OK' : 'CHANGED'));
    if (!$okClosed) { $fail[] = 'closed-year petty cash changed'; }

    if ($fail) {
        $db->rollBack();
        out('');
        out('VERIFICATION FAILED - rolled back, nothing was changed:');
        foreach ($fail as $f) { out("  - $f"); }
        finish(1);
    }

    $changes = $nPaid + $nAcct + $nPetty;
    if ($dryRun) {
        $db->rollBack();
        out('');
        out("DRY RUN OK - rolled back. Would change: PaidFromAccountId $nPaid, accountId $nAcct, petty cash rows $nPetty (total $changes).");
    } else {
        $db->commit();
        out('');
        out("COMMITTED. Changed: PaidFromAccountId $nPaid, accountId $nAcct, petty cash rows $nPetty (total $changes)." . ($changes === 0 ? ' Nothing to do (already up to date).' : ''));
    }
    finish(0);
} catch (Throwable $ex) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) { $db->rollBack(); }
    out('ERROR: ' . $ex->getMessage() . ' (' . basename($ex->getFile()) . ':' . $ex->getLine() . ')');
    finish(2);
}

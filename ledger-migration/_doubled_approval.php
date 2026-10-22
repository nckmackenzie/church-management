<?php
/**
 * Shared code of the two doubled fund-approval repair scripts (task 8.9, STEP 5). Not run directly.
 *
 * A fund approval (type 12) was posted twice for a requisition: its entry has 4 lines where lines 3-4 repeat lines 1-2
 * (and, for a bank approval, a duplicate bank row). The repair removes the repeat. Nothing is looked up by a fixed ID:
 * the requisition is found by congregation + requisition number + approved amount + type, and every row is checked by
 * content before anything is touched; any surprise stops the run with nothing changed.
 *
 *   journal_lines    lines 3-4 of the entry are DELETED (a line has no void state)
 *   tblledger        the repeated rows of the old ledger (when the table has them) are SOFT-deleted (deleted = 1)
 *   tblbankpostings  the repeated bank row is SOFT-deleted (deleted = 1), never the first one
 *
 * Both tables are handled when present: on production before the ledger cutover only tblledger has the rows; after it,
 * the journal entry has them too. Every touched row is written to ledger-migration/logs/ as JSON first (the undo).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

function fixDoubledApproval(array $c, array $argv): int
{
    error_reporting(E_ALL);
    $commit = in_array('--commit', $argv, true);
    $dry    = in_array('--dry-run', $argv, true);
    if ($commit === $dry) {
        fwrite(STDERR, "Use exactly one of --dry-run or --commit.\n");
        return 2;
    }
    if ($commit && !empty($c['needsSignOff']) && !in_array('--i-have-sign-off', $argv, true)) {
        fwrite(STDERR, "This script writes directly to a CLOSED financial year. Add --i-have-sign-off once it has been approved.\n");
        return 2;
    }
    $configPath = dirname(__DIR__) . '/app/config/config.php';
    foreach ($argv as $a) { if (preg_match('/^--config=(.+)$/', $a, $m)) { $configPath = $m[1]; } }
    if (!is_file($configPath)) { fwrite(STDERR, "Config not found: $configPath (use --config=PATH)\n"); return 2; }
    require_once $configPath;

    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $q = function (string $sql, array $p = []) use ($pdo): array { $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); };
    $say = function (string $m) { echo $m, PHP_EOL; };
    $stop = function (string $m) use ($say): int { $say('STOPPED, nothing changed: ' . $m); return 1; };

    $say('== ' . $c['label'] . ($commit ? ' (COMMIT)' : ' (dry run: rolled back)'));

    // --- the requisition, by content
    $reqs = $q('SELECT * FROM tblfundrequisition WHERE CongregationId = ? AND ReqNo = ? AND RequestType = ? AND AmountApproved = ? AND Status = 1 AND Deleted = 0',
               [$c['cong'], $c['reqNo'], $c['type'], $c['amount']]);
    if (count($reqs) !== 1) { return $stop('expected exactly 1 approved requisition (congregation ' . $c['cong'] . ', req no ' . $c['reqNo'] . ', ' . $c['type'] . ', approved ' . $c['amount'] . '), found ' . count($reqs)); }
    $req = $reqs[0];
    $say('requisition ID ' . $req['ID'] . ' req no ' . $req['ReqNo'] . ' approved ' . $req['AmountApproved'] . ' on ' . $req['ApprovalDate']);

    $backup = ['label' => $c['label'], 'requisition' => $req, 'journal_lines' => [], 'tblledger' => [], 'tblbankpostings' => []];
    $changes = ['journal_lines' => [], 'tblledger' => [], 'tblbankpostings' => []];
    $touched = 0;

    // --- the journal entry (absent before the ledger cutover)
    $hasJournal = !empty($q("SHOW TABLES LIKE 'journal_entries'"));
    $entryDate = null;
    if ($hasJournal) {
        $entries = $q('SELECT * FROM journal_entries WHERE congregationId = ? AND transactionTypeId = 12 AND transactionId = ? AND deleted = 0', [$c['cong'], $req['ID']]);
        if (count($entries) > 1) { return $stop('more than one active type-12 entry for the requisition'); }
        if (count($entries) === 1) {
            $e = $entries[0];
            $entryDate = $e['entryDate'];
            $lines = $q('SELECT * FROM journal_lines WHERE journalEntryId = ? ORDER BY lineNumber', [$e['ID']]);
            if (count($lines) !== 4) { return $stop('entry ' . $e['ID'] . ' has ' . count($lines) . ' lines, expected 4'); }
            $key = function (array $l): string { return $l['accountId'] . '|' . $l['dc'] . '|' . $l['amount']; };
            if ($key($lines[0]) !== $key($lines[2]) || $key($lines[1]) !== $key($lines[3])) { return $stop('entry ' . $e['ID'] . ': lines 3-4 do not repeat lines 1-2'); }
            if ($lines[0]['dc'] === $lines[1]['dc']) { return $stop('entry ' . $e['ID'] . ': lines 1-2 are not one debit and one credit'); }
            if (abs((float) $lines[0]['amount'] - (float) $req['AmountApproved']) > 0.005) { return $stop('entry ' . $e['ID'] . ': line amount ' . $lines[0]['amount'] . ' is not the approved amount'); }
            $changes['journal_lines'] = [(int) $lines[2]['ID'], (int) $lines[3]['ID']];
            $backup['journal_lines'] = [$lines[2], $lines[3]];
            $say('journal entry ' . $e['ID'] . ' dated ' . $entryDate . ': delete lines ' . $lines[2]['lineNumber'] . ' and ' . $lines[3]['lineNumber'] . ' (IDs ' . implode(', ', $changes['journal_lines']) . ')');
        } else {
            $say('no active journal entry (not migrated yet): tblledger only');
        }
    }

    // --- the old ledger: the repeated rows (the 2nd copy of each of the 4 rows)
    $hasLedger = !empty($q("SHOW TABLES LIKE 'tblledger'"));
    if ($hasLedger) {
        $rows = $q('SELECT * FROM tblledger WHERE congregationId = ? AND transactionType = 12 AND transactionId = ? AND deleted = 0 ORDER BY ID', [$c['cong'], $req['ID']]);
        if (count($rows) === 4) {
            $key = function (array $r): string { return $r['account'] . '|' . (float) $r['debit'] . '|' . (float) $r['credit'] . '|' . $r['transactionDate']; };
            $first = [];
            $dups = [];
            foreach ($rows as $r) { if (isset($first[$key($r)])) { $dups[] = $r; } else { $first[$key($r)] = true; } }
            if (count($dups) !== 2 || count($first) !== 2) { return $stop('tblledger rows of the requisition are not 2 rows repeated twice'); }
            $changes['tblledger'] = array_map('intval', array_column($dups, 'ID'));
            $backup['tblledger'] = $dups;
            $say('tblledger: soft-delete the repeated rows ' . implode(', ', $changes['tblledger']));
            $entryDate = $entryDate ?? $dups[0]['transactionDate'];
        } elseif (count($rows) === 2) {
            $say('tblledger already has 2 rows for the requisition: nothing to repair there');
        } elseif (count($rows) !== 0) {
            return $stop('tblledger has ' . count($rows) . ' active rows for the requisition, expected 4 (doubled) or 2');
        }
    }
    if (!$changes['journal_lines'] && !$changes['tblledger'] && empty($c['bank'])) {
        return $stop('nothing is doubled for this requisition (already repaired?)');
    }

    // --- the year of the entry must be the kind this script is for
    $dateForYear = $entryDate ?? $req['ApprovalDate'];
    $years = $q('SELECT yearName, closed, deleted FROM tblfiscalyears WHERE ? BETWEEN startDate AND endDate ORDER BY deleted ASC, ID DESC LIMIT 1', [$dateForYear]);
    if (!$years) { return $stop('no financial year covers ' . $dateForYear); }
    $isClosed = (int) $years[0]['closed'] === 1 || (int) $years[0]['deleted'] === 1;
    $say('financial year ' . $years[0]['yearName'] . ' is ' . ($isClosed ? 'CLOSED' : 'open'));
    if ($c['year'] === 'open' && $isClosed) { return $stop('the year is closed: use fix_double_approval_closed_year.php (direct SQL, needs sign-off)'); }
    if ($c['year'] === 'closed' && !$isClosed) { return $stop('the year is open: use fix_double_approval_open_year.php'); }

    // --- the bank row (closed-year / bank approvals only): the later one of two identical active rows
    $bankRows = $q('SELECT * FROM tblbankpostings WHERE congregationId = ? AND transactionType = 12 AND transactionId = ? AND deleted = 0 ORDER BY ID', [$c['cong'], $req['ID']]);
    if (empty($c['bank'])) {
        if ($bankRows) { return $stop('the requisition has bank rows; use the closed-year script, which handles them'); }
    } else {
        if (count($bankRows) === 2) {
            $same = $bankRows[0]['bankId'] === $bankRows[1]['bankId'] && $bankRows[0]['transactionDate'] === $bankRows[1]['transactionDate']
                 && abs((float) $bankRows[0]['credit'] - (float) $bankRows[1]['credit']) < 0.005 && abs((float) $bankRows[0]['debit'] - (float) $bankRows[1]['debit']) < 0.005;
            if (!$same) { return $stop('the 2 bank rows are not identical'); }
            if (abs((float) $bankRows[0]['credit'] - (float) $req['AmountApproved']) > 0.005) { return $stop('bank row amount ' . $bankRows[0]['credit'] . ' is not the approved amount'); }
            $changes['tblbankpostings'] = [(int) $bankRows[1]['ID']];
            $backup['tblbankpostings'] = [$bankRows[1]];
            $say('bank: soft-delete the duplicate row ' . $bankRows[1]['ID'] . ' (keeps ' . $bankRows[0]['ID'] . ', bank ' . $bankRows[0]['bankId'] . ', ' . $bankRows[0]['credit'] . ')');
        } elseif (count($bankRows) !== 1) {
            return $stop('expected 2 (doubled) or 1 bank rows, found ' . count($bankRows));
        }
    }

    // --- write (one transaction; a dry run rolls back)
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) { mkdir($logDir, 0775, true); }
    $pdo->beginTransaction();
    try {
        if ($changes['journal_lines']) {
            $n = $pdo->prepare('DELETE FROM journal_lines WHERE ID IN (?, ?)');
            $n->execute($changes['journal_lines']);
            $touched += $n->rowCount();
            if ($n->rowCount() !== 2) { throw new RuntimeException('journal_lines: deleted ' . $n->rowCount() . ' rows, expected 2'); }
        }
        foreach (['tblledger', 'tblbankpostings'] as $t) {
            if ($changes[$t]) {
                $in = implode(',', array_fill(0, count($changes[$t]), '?'));
                $n = $pdo->prepare("UPDATE $t SET deleted = 1 WHERE ID IN ($in) AND deleted = 0");
                $n->execute($changes[$t]);
                $touched += $n->rowCount();
                if ($n->rowCount() !== count($changes[$t])) { throw new RuntimeException("$t: changed " . $n->rowCount() . ' rows, expected ' . count($changes[$t])); }
            }
        }
        // verify: 2 lines / 2 rows / 1 bank row remain active
        if ($hasJournal && $changes['journal_lines']) {
            $left = (int) $q('SELECT COUNT(*) c FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId WHERE e.congregationId = ? AND e.transactionTypeId = 12 AND e.transactionId = ? AND e.deleted = 0', [$c['cong'], $req['ID']])[0]['c'];
            if ($left !== 2) { throw new RuntimeException("verification: $left journal lines left, expected 2"); }
        }
        if ($hasLedger && $changes['tblledger']) {
            $left = (int) $q('SELECT COUNT(*) c FROM tblledger WHERE congregationId = ? AND transactionType = 12 AND transactionId = ? AND deleted = 0', [$c['cong'], $req['ID']])[0]['c'];
            if ($left !== 2) { throw new RuntimeException("verification: $left tblledger rows left, expected 2"); }
        }
        if (!empty($c['bank']) && $changes['tblbankpostings']) {
            $left = (int) $q('SELECT COUNT(*) c FROM tblbankpostings WHERE congregationId = ? AND transactionType = 12 AND transactionId = ? AND deleted = 0', [$c['cong'], $req['ID']])[0]['c'];
            if ($left !== 1) { throw new RuntimeException("verification: $left bank rows left, expected 1"); }
        }
        if ($commit) {
            $file = $logDir . '/' . $c['file'] . '_' . date('Ymd_His') . '_undo.json';
            file_put_contents($file, json_encode($backup, JSON_PRETTY_PRINT));
            $pdo->commit();
            $say("COMMITTED: $touched rows changed. The removed rows are saved in $file");
        } else {
            $pdo->rollBack();
            $say("Dry run: $touched rows would change. Rolled back.");
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $say('ERROR, rolled back: ' . $e->getMessage());
        return 2;
    }
    return 0;
}

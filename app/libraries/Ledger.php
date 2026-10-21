<?php
/**
 * Ledger - the single way to write accounting entries (task 7).
 *
 * Writes journal_entries (header) and journal_lines (lines). Replaces
 * saveToLedger() / deleteLedgerBanking() / softdeleteLedgerBanking() for the
 * ledger side. tblbankpostings is still written separately for now (task 8).
 *
 * Rules enforced on every write:
 *   - at least 2 lines, every amount > 0 with at most 2 decimals
 *   - total debits = total credits
 *   - every line is on a POSTING account (handover section 6):
 *       not a root, not directly under a root, no non-deleted children,
 *       isSubCategory = 1, not deleted, same congregation or shared (0),
 *       active (an inactive account is allowed only if the entry being
 *       edited already uses it)
 *   - journal numbers come from journal_number_seq and are never reused,
 *     including numbers of deleted journals
 *   - every call must run inside the caller's DB transaction, together with
 *     the write of the source record
 *
 * Usage (inside the model's existing beginTransaction/commit block):
 *
 *   $pdo = $this->db->pdo();
 *   Ledger::post($pdo, [
 *       'congregationId'    => $cong,
 *       'entryDate'         => '2026-10-20',
 *       'transactionTypeId' => 2,              // tbltransactiontypes.ID
 *       'transactionId'     => $expenseId,     // ID in the source table
 *       'reference'         => $ref,
 *       'description'       => $narration,
 *   ], [
 *       Ledger::debit($expenseAccountId, $amount, $narration),
 *       Ledger::credit($bankAccountId, $amount, $narration),
 *   ]);
 *
 *   Ledger::void($pdo, $cong, 2, $expenseId);     // delete / reverse / reject
 *
 *   $no = Ledger::nextJournalNo($pdo, $cong);     // new journal: transactionId = $no
 *
 * post() creates the entry, or - if an active entry already exists for the
 * same congregation + transaction type + transaction id - replaces its lines
 * in place (edit). After void(), post() creates a new entry (re-approval);
 * for journals that is refused, because the number has been used.
 *
 * Dev only: if LEDGER_LEGACY_WRITE is defined and true (config.php), every
 * post()/void() is mirrored into tblledger so old and new reports can be
 * compared. Must be false/undefined on production.
 *
 * PHP 7.4+.
 */

class LedgerException extends RuntimeException
{
    /** @var string[] */
    private $errors;

    public function __construct(array $errors)
    {
        $this->errors = array_values($errors);
        parent::__construct('Ledger posting rejected: ' . implode('; ', $this->errors));
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

class Ledger
{
    const DEBIT        = 'DEBIT';
    const CREDIT       = 'CREDIT';
    const TYPE_JOURNAL = 5;

    const PAY_BANK = 'bank';   // payment method is bank-related -> a bank account (isBank = 1)
    const PAY_CASH = 'cash';   // cash, M-Pesa etc. -> an asset posting account that is not a bank

    // -----------------------------------------------------------------
    // Line builders
    // -----------------------------------------------------------------

    public static function debit(int $accountId, $amount, ?string $memo = null): array
    {
        return ['accountId' => $accountId, 'dc' => self::DEBIT, 'amount' => $amount, 'memo' => $memo];
    }

    public static function credit(int $accountId, $amount, ?string $memo = null): array
    {
        return ['accountId' => $accountId, 'dc' => self::CREDIT, 'amount' => $amount, 'memo' => $memo];
    }

    // -----------------------------------------------------------------
    // Writes
    // -----------------------------------------------------------------

    /**
     * Create or replace (edit) the active entry for a source transaction.
     *
     * @param array $h     congregationId, entryDate (Y-m-d), transactionTypeId,
     *                     transactionId, reference (optional), description (optional;
     *                     defaults to the first non-blank memo)
     * @param array $lines each: accountId, dc ('DEBIT'|'CREDIT'), amount, memo (optional)
     * @return int journal_entries.ID
     * @throws LedgerException when the entry breaks a rule (nothing is written)
     */
    public static function post(PDO $db, array $h, array $lines): int
    {
        self::requireTransaction($db);

        $cong = (int) ($h['congregationId'] ?? 0);
        $type = (int) ($h['transactionTypeId'] ?? 0);
        $txn  = (int) ($h['transactionId'] ?? 0);
        $date = (string) ($h['entryDate'] ?? '');

        $errors = [];
        if ($cong <= 0) { $errors[] = 'congregationId is required'; }
        if ($type <= 0) { $errors[] = 'transactionTypeId is required'; }
        if ($txn <= 0)  { $errors[] = 'transactionId is required'; }
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) { $errors[] = "entryDate '$date' is not a valid Y-m-d date"; }
        if ($errors) { throw new LedgerException($errors); }

        // Existing active entry = edit. Lock it for the rest of the transaction.
        $st = $db->prepare('SELECT ID FROM journal_entries
                            WHERE congregationId = ? AND transactionTypeId = ? AND transactionId = ? AND deleted = 0
                            FOR UPDATE');
        $st->execute([$cong, $type, $txn]);
        $existingId = $st->fetchColumn();
        $existingId = $existingId === false ? null : (int) $existingId;

        $alreadyUsed = [];
        if ($existingId !== null) {
            $st = $db->prepare('SELECT DISTINCT accountId FROM journal_lines WHERE journalEntryId = ?');
            $st->execute([$existingId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $a) { $alreadyUsed[(int) $a] = true; }
        }

        $clean = self::validateLines($db, $cong, $lines, $alreadyUsed);

        $journalNo = null;
        if ($type === self::TYPE_JOURNAL) {
            $journalNo = $txn;
            if ($existingId === null) {
                $st = $db->prepare('SELECT COUNT(*) FROM journal_entries WHERE congregationId = ? AND journalNo = ?');
                $st->execute([$cong, $journalNo]);
                if ((int) $st->fetchColumn() > 0) {
                    throw new LedgerException(["journal number $journalNo has already been used (numbers are never reused)"]);
                }
                // Keep the sequence ahead of any number used.
                $db->prepare('INSERT INTO journal_number_seq (congregationId, lastJournalNo) VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE lastJournalNo = GREATEST(lastJournalNo, VALUES(lastJournalNo))')
                   ->execute([$cong, $journalNo]);
            }
        }

        $ref  = self::blankToNull($h['reference'] ?? null);
        $desc = self::blankToNull($h['description'] ?? null);
        if ($desc === null) {
            foreach ($clean as $l) { if ($l['memo'] !== null) { $desc = $l['memo']; break; } }
        }

        if ($existingId !== null) {
            $db->prepare('UPDATE journal_entries SET entryDate = ?, reference = ?, description = ? WHERE ID = ?')
               ->execute([$date, $ref, $desc, $existingId]);
            $db->prepare('DELETE FROM journal_lines WHERE journalEntryId = ?')->execute([$existingId]);
            $entryId = $existingId;
        } else {
            $db->prepare('INSERT INTO journal_entries
                              (congregationId, entryDate, transactionTypeId, transactionId, journalNo, reference, description, deleted)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 0)')
               ->execute([$cong, $date, $type, $txn, $journalNo, $ref, $desc]);
            $entryId = (int) $db->lastInsertId();
        }

        $ins = $db->prepare('INSERT INTO journal_lines (journalEntryId, accountId, dc, amount, memo, lineNumber)
                             VALUES (?, ?, ?, ?, ?, ?)');
        $n = 0;
        foreach ($clean as $l) {
            $ins->execute([$entryId, $l['accountId'], $l['dc'], $l['amount'], $l['memo'], ++$n]);
        }

        if (self::legacyEnabled()) {
            self::mirrorLegacy($db, $cong, $type, $txn, $date, $ref, $journalNo, $clean);
        }

        return $entryId;
    }

    /**
     * Soft-delete the active entry of a source transaction (delete, reverse,
     * reject, unapprove). Returns false if there was no active entry.
     */
    public static function void(PDO $db, int $cong, int $type, int $txn): bool
    {
        self::requireTransaction($db);

        $st = $db->prepare('UPDATE journal_entries SET deleted = 1
                            WHERE congregationId = ? AND transactionTypeId = ? AND transactionId = ? AND deleted = 0');
        $st->execute([$cong, $type, $txn]);
        $voided = $st->rowCount() > 0;

        if (self::legacyEnabled()) {
            $db->prepare('UPDATE tblledger SET deleted = 1
                          WHERE congregationId = ? AND transactionType = ? AND transactionId = ? AND deleted = 0')
               ->execute([$cong, $type, $txn]);
        }

        return $voided;
    }

    /**
     * Allocate the next journal number (locks the sequence row until the
     * caller's transaction ends). Use the result as transactionId for post().
     */
    public static function nextJournalNo(PDO $db, int $cong): int
    {
        self::requireTransaction($db);

        $st = $db->prepare('SELECT lastJournalNo FROM journal_number_seq WHERE congregationId = ? FOR UPDATE');
        $st->execute([$cong]);
        $last = $st->fetchColumn();

        if ($last === false) {
            $st = $db->prepare('SELECT COALESCE(MAX(journalNo), 0) FROM journal_entries WHERE congregationId = ?');
            $st->execute([$cong]);
            $next = (int) $st->fetchColumn() + 1;
            $db->prepare('INSERT INTO journal_number_seq (congregationId, lastJournalNo) VALUES (?, ?)')
               ->execute([$cong, $next]);
            return $next;
        }

        $next = (int) $last + 1;
        $db->prepare('UPDATE journal_number_seq SET lastJournalNo = ? WHERE congregationId = ?')
           ->execute([$next, $cong]);
        return $next;
    }

    /** Next journal number for DISPLAY on a new-journal form only (not reserved). */
    public static function peekNextJournalNo(PDO $db, int $cong): int
    {
        $st = $db->prepare('SELECT GREATEST(
                                COALESCE((SELECT lastJournalNo FROM journal_number_seq WHERE congregationId = ?), 0),
                                COALESCE((SELECT MAX(journalNo) FROM journal_entries WHERE congregationId = ?), 0))');
        $st->execute([$cong, $cong]);
        return (int) $st->fetchColumn() + 1;
    }

    // -----------------------------------------------------------------
    // Payment accounts (payment method dropdowns)
    // -----------------------------------------------------------------

    /**
     * Validate the account chosen for a payment method and return it.
     *   PAY_BANK: must be a bank account (isBank = 1)
     *   PAY_CASH: must be an Asset (type 3) account that is not a bank
     * In both cases it must be a posting account.
     */
    public static function assertPaymentAccount(PDO $db, int $cong, int $accountId, string $kind): int
    {
        $info = self::accountInfo($db, [$accountId])[$accountId] ?? null;
        $why  = self::whyNotPostable($info, $cong, false);
        if ($why === null) { $why = self::whyNotPaymentKind($info, $kind); }
        if ($why !== null) {
            throw new LedgerException(["payment account $accountId: $why"]);
        }
        return $accountId;
    }

    /**
     * Accounts to offer in a payment-method dropdown, using exactly the same
     * rules as assertPaymentAccount(). Returns [ ['ID'=>, 'accountType'=>, 'parentName'=>], ... ].
     */
    public static function listPaymentAccounts(PDO $db, int $cong, string $kind): array
    {
        $st = $db->prepare('SELECT ID FROM tblaccounttypes
                            WHERE deleted = 0 AND active = 1 AND congregationId IN (0, ?)
                              AND ' . ($kind === self::PAY_BANK ? 'isBank = 1' : 'isBank = 0 AND accountTypeId = 3'));
        $st->execute([$cong]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        $out = [];
        foreach (self::accountInfo($db, $ids) as $id => $a) {
            if (self::whyNotPostable($a, $cong, false) === null && self::whyNotPaymentKind($a, $kind) === null) {
                $out[] = ['ID' => $id, 'accountType' => $a['accountType'], 'parentName' => $a['parentName']];
            }
        }
        usort($out, function ($x, $y) { return strcasecmp($x['accountType'], $y['accountType']); });
        return $out;
    }

    /** True if the account can be posted to (handover section 6). */
    public static function isPostingAccount(PDO $db, int $accountId, int $cong): bool
    {
        $info = self::accountInfo($db, [$accountId])[$accountId] ?? null;
        return self::whyNotPostable($info, $cong, false) === null;
    }

    // -----------------------------------------------------------------
    // Read
    // -----------------------------------------------------------------

    /**
     * The active entry of a source transaction with its lines, or null.
     * Lines include accountName and parentName for display.
     */
    public static function getEntry(PDO $db, int $cong, int $type, int $txn): ?array
    {
        $st = $db->prepare('SELECT * FROM journal_entries
                            WHERE congregationId = ? AND transactionTypeId = ? AND transactionId = ? AND deleted = 0');
        $st->execute([$cong, $type, $txn]);
        $h = $st->fetch(PDO::FETCH_ASSOC);
        if (!$h) { return null; }

        $st = $db->prepare('SELECT l.lineNumber, l.accountId, a.accountType AS accountName,
                                   p.accountType AS parentName, l.dc, l.amount, l.memo
                            FROM   journal_lines l
                            JOIN   tblaccounttypes a   ON a.ID = l.accountId
                            LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
                            WHERE  l.journalEntryId = ?
                            ORDER BY l.lineNumber');
        $st->execute([$h['ID']]);
        $h['lines'] = $st->fetchAll(PDO::FETCH_ASSOC);
        return $h;
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    private static function requireTransaction(PDO $db): void
    {
        if (!$db->inTransaction()) {
            throw new LogicException('Ledger writes must run inside the caller\'s database transaction.');
        }
    }

    private static function legacyEnabled(): bool
    {
        return defined('LEDGER_LEGACY_WRITE') && LEDGER_LEGACY_WRITE === true;
    }

    private static function blankToNull($v): ?string
    {
        if ($v === null) { return null; }
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    /** Validate and normalise lines; throws LedgerException listing every problem. */
    private static function validateLines(PDO $db, int $cong, array $lines, array $allowInactive): array
    {
        $errors = [];
        $clean  = [];
        $dr = 0;
        $cr = 0;
        $ids = [];

        if (count($lines) < 2) { $errors[] = 'an entry needs at least 2 lines'; }

        foreach (array_values($lines) as $i => $l) {
            $n   = $i + 1;
            $acc = (int) ($l['accountId'] ?? 0);
            $dc  = strtoupper((string) ($l['dc'] ?? ''));
            $amt = $l['amount'] ?? null;

            if ($acc <= 0) { $errors[] = "line $n: accountId is required"; }
            if ($dc !== self::DEBIT && $dc !== self::CREDIT) { $errors[] = "line $n: dc must be DEBIT or CREDIT"; }
            if (!is_numeric($amt)) { $errors[] = "line $n: amount '" . (string) $amt . "' is not a number"; continue; }

            $raw   = (float) $amt * 100;
            $cents = (int) round($raw);
            if (abs($raw - $cents) > 0.0001) { $errors[] = "line $n: amount $amt has more than 2 decimals"; }
            if ($cents <= 0) { $errors[] = "line $n: amount must be greater than 0"; }

            if ($dc === self::DEBIT) { $dr += $cents; } elseif ($dc === self::CREDIT) { $cr += $cents; }
            if ($acc > 0) { $ids[$acc] = true; }

            $clean[] = [
                'accountId' => $acc,
                'dc'        => $dc,
                'amount'    => number_format($cents / 100, 2, '.', ''),
                'memo'      => self::blankToNull($l['memo'] ?? null),
            ];
        }

        if ($dr !== $cr) {
            $errors[] = 'debits ' . number_format($dr / 100, 2) . ' do not equal credits ' . number_format($cr / 100, 2);
        }

        if ($ids) {
            $info = self::accountInfo($db, array_keys($ids));
            foreach (array_keys($ids) as $acc) {
                $why = self::whyNotPostable($info[$acc] ?? null, $cong, isset($allowInactive[$acc]));
                if ($why !== null) { $errors[] = "account $acc: $why"; }
            }
        }

        if ($errors) { throw new LedgerException($errors); }
        return $clean;
    }

    /** @return array<int, array> keyed by account ID */
    private static function accountInfo(PDO $db, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) { return []; }

        $st = $db->prepare('SELECT a.ID, a.accountType, a.accountTypeId, a.parentId, a.isSubCategory, a.isBank,
                                   a.active, a.deleted, a.congregationId,
                                   p.parentId    AS grandParentId,
                                   p.accountType AS parentName,
                                   (SELECT COUNT(*) FROM tblaccounttypes c
                                     WHERE c.parentId = a.ID AND c.deleted = 0) AS childCount
                            FROM   tblaccounttypes a
                            LEFT JOIN tblaccounttypes p ON p.ID = a.parentId
                            WHERE  a.ID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $st->execute($ids);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(int) $r['ID']] = $r; }
        return $out;
    }

    /** Reason the account can't be posted to, or null if it can. */
    private static function whyNotPostable(?array $a, int $cong, bool $allowInactive): ?string
    {
        if ($a === null)                                         { return 'does not exist'; }
        if ((int) $a['deleted'] === 1)                           { return "'{$a['accountType']}' is deleted"; }
        if (!in_array((int) $a['congregationId'], [0, $cong], true)) { return "'{$a['accountType']}' belongs to another congregation"; }
        if ((int) $a['parentId'] === 0)                          { return "'{$a['accountType']}' is a root account"; }
        if ($a['grandParentId'] === null)                        { return "'{$a['accountType']}' has a missing parent account"; }
        if ((int) $a['grandParentId'] === 0)                     { return "'{$a['accountType']}' is a top-level reporting account"; }
        if ((int) $a['childCount'] > 0)                          { return "'{$a['accountType']}' has sub-accounts (reporting account)"; }
        if ((int) $a['isSubCategory'] !== 1)                     { return "'{$a['accountType']}' is not marked as a posting account"; }
        if ((int) $a['active'] !== 1 && !$allowInactive)         { return "'{$a['accountType']}' is inactive"; }
        return null;
    }

    private static function whyNotPaymentKind(?array $a, string $kind): ?string
    {
        if ($a === null) { return 'does not exist'; }
        if ($kind === self::PAY_BANK) {
            return (int) $a['isBank'] === 1 ? null : "'{$a['accountType']}' is not a bank account";
        }
        if ($kind === self::PAY_CASH) {
            if ((int) $a['isBank'] === 1)        { return "'{$a['accountType']}' is a bank account; choose a bank payment method"; }
            if ((int) $a['accountTypeId'] !== 3) { return "'{$a['accountType']}' is not an asset account"; }
            return null;
        }
        return "unknown payment kind '$kind'";
    }

    /** DEV ONLY: mirror the entry into tblledger so old reports keep working. */
    private static function mirrorLegacy(PDO $db, int $cong, int $type, int $txn, string $date,
                                         ?string $ref, ?int $journalNo, array $lines): void
    {
        $db->prepare('DELETE FROM tblledger
                      WHERE congregationId = ? AND transactionType = ? AND transactionId = ? AND deleted = 0')
           ->execute([$cong, $type, $txn]);

        $info = self::accountInfo($db, array_column($lines, 'accountId'));
        $ins  = $db->prepare('INSERT INTO tblledger
                (transactionDate, account, parentaccount, debit, credit, narration, reference, accountId,
                 transactionType, transactionId, isJournal, journalNo, congregationId, deleted)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');

        foreach ($lines as $l) {
            $a = $info[$l['accountId']];
            // The old app posted every bank line as 'cash at bank'.
            $name   = (int) $a['isBank'] === 1 ? 'cash at bank' : $a['accountType'];
            $parent = (int) $a['isBank'] === 1 ? 'cash at bank' : $a['parentName'];
            // tblledger.narration / reference are NOT NULL in the old schema.
            $ins->execute([
                $date, $name, $parent,
                $l['dc'] === self::DEBIT ? $l['amount'] : '0.00',
                $l['dc'] === self::CREDIT ? $l['amount'] : '0.00',
                mb_substr((string) $l['memo'], 0, 255), (string) $ref, (int) $a['accountTypeId'],
                $type, $txn, $type === self::TYPE_JOURNAL ? 1 : 0, $journalNo, $cong,
            ]);
        }
    }
}

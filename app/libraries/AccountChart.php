<?php
/**
 * Chart of accounts rules (task 8.10, decisions 65-75 in docs/ledger_inventory.md).
 *
 * Every method takes the caller's PDO; the ones that read-then-write expect the caller's open transaction (they never
 * begin or commit). Nothing here writes except recomputeFlags().
 *
 * isSubCategory is COMPUTED (decision 65): a root (parentId 0) and an account directly under a root are reporting (0);
 * deeper accounts are posting (1) when they have no non-deleted children, otherwise reporting (0).
 */
class AccountChart
{
    /** Roots 1-6 and the system accounts the code relies on by ID or by name (decision 71). Banks (isBank = 1) are protected as well. */
    const PROTECTED = [1, 2, 3, 4, 5, 6, 9, 20, 22, 23, 25, 33, 34, 98, 118, 121, 167, 194];

    /** 'cash at bank': the container of the banks. Banks only, created in the Banks module (decision 67). */
    const BANKS_PARENT = 33;

    /** Deepest chain walked before a loop is assumed (the chart is 4 deep today). */
    const MAX_DEPTH = 30;

    /**
     * Every column that holds a tblaccounttypes.ID (inventory section 3). [table, column, label, extra WHERE].
     * journal_lines count only lines of non-voided entries; tblbankpostings only non-deleted rows; the rest count every row.
     * Keep in step with fn_checkreferencedaccount (scripts/sql/fn_checkreferencedaccount_v2.sql): test_8_10_accounts.php compares them.
     */
    const REFERENCES = [
        ['journal_lines',                  'accountId',          'active journal line(s)',            'journal'],
        ['tblbankpostings',                'bankId',             'bank posting(s) as the bank',       'tblbankpostings.deleted = 0'],
        ['tblbankpostings',                'accountId',          'bank posting(s) as the account',    'tblbankpostings.deleted = 0'],
        ['tblexpenses',                    'accountId',          'expense(s) on the account',         ''],
        ['tblexpenses',                    'PaidFromAccountId',  'expense(s) paid from the account',  ''],
        ['tblexpenses',                    'bankId',             'expense(s) (legacy bank column)',   ''],
        ['tblbanktransactions',            'BankId',             'bank transaction(s)',               ''],
        ['tblbanktransactions',            'TransferToId',       'bank transfer(s) received',         ''],
        ['tblbanktransactions',            'CashAccountId',      'bank transaction(s) using it as cash account', ''],
        ['tblbanksubaccounts',             'AccountId',          'bank sub account(s) mapped to it',  ''],
        ['tblbanksubaccounts',             'BankId',             'bank sub account(s) of the bank',   ''],
        ['tblcontributions_details',       'contributionTypeId', 'receipt line(s) on the account',    ''],
        ['tblcontributions_details',       'bankId',             'receipt line(s) paid into the bank', ''],
        ['tblmmf',                         'BankId',             'MMF receipt(s)',                    ''],
        ['tblpettycash',                   'BankId',             'petty cash row(s) of the bank',     ''],
        ['tblpettycash',                   'PettyCashAccountId', 'petty cash row(s)',                 ''],
        ['tblchurchbudget_details',        'accountId',          'church budget line(s)',             ''],
        ['tblgroupbudget_details',         'accountId',          'group budget line(s)',              ''],
        ['tblproducts',                    'accountId',          'product(s)',                        ''],
        ['tblpledges_header',              'category',           'pledge(s)',                         ''],
        ['tblpledges_details',             'bankId',             'pledge payment(s)',                 ''],
        ['tblinvoice_payments',            'bankId',             'customer payment(s)',               ''],
        ['tblinvoice_payments_suppliers',  'bankId',             'supplier payment(s)',               ''],
        ['tbldesposits',                   'BankId',             'deposit(s)',                        ''],
        ['tblbankbalances',                'BankId',             'bank balance row(s)',               ''],
        ['tblworkplan',                    'AccountId',          'work plan line(s)',                 ''],
    ];

    // -----------------------------------------------------------------
    // Reading the tree
    // -----------------------------------------------------------------

    /** One account row (or null). With $lock the row is locked FOR UPDATE (caller's transaction). */
    public static function load(PDO $db, int $id, bool $lock = false): ?array
    {
        $st = $db->prepare('SELECT ID, accountType, accountTypeId, parentId, isSubCategory, `description`, isBank, accountNo, forGroup,
                                   isEditable, active, congregationId, deleted
                            FROM tblaccounttypes WHERE ID = ?' . ($lock ? ' FOR UPDATE' : ''));
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * IDs from the account up to its root, the account first, or null if the chain is broken (missing parent, loop, too deep).
     * @return int[]|null
     */
    public static function chain(PDO $db, int $id): ?array
    {
        $out = [];
        $seen = [];
        $st = $db->prepare('SELECT ID, parentId FROM tblaccounttypes WHERE ID = ?');
        $cur = $id;
        while (true) {
            if (isset($seen[$cur]) || count($out) > self::MAX_DEPTH) { return null; }
            $seen[$cur] = true;
            $st->execute([$cur]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) { return null; }
            $out[] = (int) $r['ID'];
            if ((int) $r['parentId'] === 0) { return $out; }
            $cur = (int) $r['parentId'];
        }
    }

    /** 1 for a root, 2 directly under a root, ...; null when the chain is broken. */
    public static function depthOf(PDO $db, int $id): ?int
    {
        $c = self::chain($db, $id);
        return $c === null ? null : count($c);
    }

    /** The root account ID of the chain (the account itself when it is a root); null when the chain is broken. */
    public static function rootOf(PDO $db, int $id): ?int
    {
        $c = self::chain($db, $id);
        return $c === null ? null : end($c);
    }

    /**
     * The computed isSubCategory (1 posting / 0 reporting) from the CURRENT tree, or null when the chain is broken.
     * A pure function of depth and non-deleted children, so it can be evaluated before or after a change.
     */
    public static function computeFlag(PDO $db, int $id): ?int
    {
        $depth = self::depthOf($db, $id);
        if ($depth === null) { return null; }
        if ($depth <= 2) { return 0; }
        $st = $db->prepare('SELECT COUNT(*) FROM tblaccounttypes WHERE parentId = ? AND deleted = 0');
        $st->execute([$id]);
        return (int) $st->fetchColumn() > 0 ? 0 : 1;
    }

    /**
     * Store the computed flag on each non-deleted account in $ids where it differs. Deleted rows are left alone.
     * @return array<int,int> changed ID => new flag
     */
    public static function recomputeFlags(PDO $db, array $ids): array
    {
        $changed = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($i) { return $i > 0; })));
        $sel = $db->prepare('SELECT isSubCategory FROM tblaccounttypes WHERE ID = ? AND deleted = 0');
        $upd = $db->prepare('UPDATE tblaccounttypes SET isSubCategory = ? WHERE ID = ?');
        foreach ($ids as $id) {
            $sel->execute([$id]);
            $stored = $sel->fetchColumn();
            if ($stored === false) { continue; }
            $flag = self::computeFlag($db, $id);
            if ($flag === null || (int) $stored === $flag) { continue; }
            $upd->execute([$flag, $id]);
            $changed[$id] = $flag;
        }
        return $changed;
    }

    /**
     * Every account below $id, deleted rows included (their history moves with the subtree), not $id itself.
     * The depth cap means a loop in the data cannot run forever.
     * @return int[]
     */
    public static function descendants(PDO $db, int $id): array
    {
        $st = $db->prepare('WITH RECURSIVE t (ID, d) AS (
                                SELECT ID, 1 FROM tblaccounttypes WHERE parentId = ? AND ID <> ?
                                UNION ALL
                                SELECT a.ID, t.d + 1 FROM tblaccounttypes a JOIN t ON a.parentId = t.ID WHERE t.d < ' . self::MAX_DEPTH . ' AND a.ID <> ?
                            ) SELECT DISTINCT ID FROM t');
        $st->execute([$id, $id, $id]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** The account and everything below it. */
    public static function subtreeIds(PDO $db, int $id): array
    {
        return array_merge([$id], self::descendants($db, $id));
    }

    /** True if making $newParentId the parent of $id would create a loop (itself or one of its descendants). */
    public static function wouldCycle(PDO $db, int $id, int $newParentId): bool
    {
        return $id === $newParentId || in_array($newParentId, self::descendants($db, $id), true);
    }

    // -----------------------------------------------------------------
    // Postings / references
    // -----------------------------------------------------------------

    /**
     * Reference counts per account: [id => [label => count]] for the IDs that have any.
     * @return array<int, array<string,int>>
     */
    public static function referenceCounts(PDO $db, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $out = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            foreach (self::REFERENCES as [$table, $col, $label, $extra]) {
                if ($extra === 'journal') {
                    $sql = "SELECT l.accountId AS k, COUNT(*) AS n FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId
                            WHERE e.deleted = 0 AND l.accountId IN ($in) GROUP BY l.accountId";
                } else {
                    $sql = "SELECT $table.$col AS k, COUNT(*) AS n FROM $table WHERE $table.$col IN ($in)"
                         . ($extra !== '' ? " AND $extra" : '') . " GROUP BY $table.$col";
                }
                $st = $db->prepare($sql);
                $st->execute($chunk);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $k = (int) $r['k'];
                    $out[$k][$label] = (isset($out[$k][$label]) ? $out[$k][$label] : 0) + (int) $r['n'];
                }
            }
        }
        return $out;
    }

    /** The subset of $ids that has any posting or reference (decision 68). @return array<int,true> */
    public static function hasPostings(PDO $db, array $ids): array
    {
        $out = [];
        foreach (self::referenceCounts($db, $ids) as $id => $counts) {
            if (array_sum($counts) > 0) { $out[$id] = true; }
        }
        return $out;
    }

    /**
     * Human-readable reasons the account has postings / references (empty = none). With $subtree the account's descendants,
     * deleted ones too, are included (their history re-types with the subtree).
     * @return string[]
     */
    public static function postingReasons(PDO $db, int $id, bool $subtree = false): array
    {
        $ids = $subtree ? self::subtreeIds($db, $id) : [$id];
        $counts = self::referenceCounts($db, $ids);
        if (!$counts) { return []; }
        $names = [];
        $st = $db->prepare('SELECT ID, accountType FROM tblaccounttypes WHERE ID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $names[(int) $r['ID']] = $r['accountType']; }

        $out = [];
        foreach ($ids as $i) {
            if (empty($counts[$i])) { continue; }
            $parts = [];
            foreach ($counts[$i] as $label => $n) { $parts[] = "$n $label"; }
            $who = $subtree ? "'" . ($names[$i] ?? $i) . "' (ID $i): " : '';
            $out[] = $who . implode(', ', $parts);
        }
        return $out;
    }

    // -----------------------------------------------------------------
    // Rules
    // -----------------------------------------------------------------

    /** True for roots 1-6, the system accounts in PROTECTED and every bank. Accepts an account row or an ID (ID only: banks need a row). */
    public static function isProtected($account): bool
    {
        if (is_array($account)) {
            return in_array((int) $account['ID'], self::PROTECTED, true) || (int) ($account['isBank'] ?? 0) === 1;
        }
        return in_array((int) $account, self::PROTECTED, true);
    }

    /** Why this session may not create / edit / delete the account (decision 72), or null. */
    public static function manageProblem(array $account, array $session): ?string
    {
        $cong = (int) $account['congregationId'];
        if ($cong === 0) {
            return self::mayManageShared($session) ? null : 'Shared accounts can only be changed by the parish or an administrator';
        }
        return $cong === (int) $session['congId'] ? null : 'This account belongs to another congregation';
    }

    public static function canManage(PDO $db, array $account, array $session): bool
    {
        return self::manageProblem($account, $session) === null;
    }

    /** Parish logins and user types 1 / 2 may create, edit and delete shared accounts. */
    public static function mayManageShared(array $session): bool
    {
        return converttobool($session['isParish'] ?? 0) || (int) ($session['userType'] ?? 9) <= 2;
    }

    /**
     * Why $parentId cannot be the parent of an account of $congregation (0 = shared), or null (decisions 67 and 68).
     * $selfId = the account being moved (so itself and its descendants are refused).
     */
    public static function parentProblem(PDO $db, int $parentId, int $congregation, ?int $selfId = null): ?string
    {
        $p = self::load($db, $parentId);
        if (!$p) { return 'The parent account does not exist'; }
        $name = "'" . $p['accountType'] . "'";
        if ((int) $p['deleted'] === 1) { return "The parent account $name is deleted"; }
        if (self::chain($db, $parentId) === null) { return "The parent account $name has a broken parent chain"; }
        if ((int) $p['isBank'] === 1) { return "A bank cannot be a parent account ($name); banks are managed in the Banks module"; }
        if ($parentId === self::BANKS_PARENT) { return "$name holds the banks and cannot be a parent account; banks are created in the Banks module"; }
        $pc = (int) $p['congregationId'];
        if ($congregation === 0 && $pc !== 0) { return "A shared account can only sit under a shared account; $name belongs to a congregation"; }
        if ($pc !== 0 && $pc !== $congregation) { return "The parent account $name belongs to another congregation"; }
        if ($selfId !== null && self::wouldCycle($db, $selfId, $parentId)) { return "An account cannot be moved under itself or its own sub accounts ($name)"; }
        $reasons = self::postingReasons($db, $parentId);
        if ($reasons) { return "No sub account can be added under $name because it has postings (" . implode('; ', $reasons) . ')'; }
        return null;
    }

    /** Why the account cannot move under $newParentId (decisions 69, 71, 67), or null. Ownership is checked by the caller (manageProblem). */
    public static function moveProblem(PDO $db, int $id, int $newParentId): ?string
    {
        $a = self::load($db, $id);
        if (!$a) { return 'The account does not exist'; }
        if ((int) $a['isBank'] === 1) { return 'A bank is managed in the Banks module and cannot be moved here'; }
        if (self::isProtected($a)) { return "'" . $a['accountType'] . "' is a protected account and cannot be moved"; }
        if ($newParentId === (int) $a['parentId']) { return null; }
        $why = self::parentProblem($db, $newParentId, (int) $a['congregationId'], $id);
        if ($why !== null) { return $why; }

        $oldRoot = self::rootOf($db, $id);
        $newRoot = self::rootOf($db, $newParentId);
        $posted = self::postingReasons($db, $id, true);
        if ($oldRoot !== $newRoot && $posted) {
            return 'The account or one of its sub accounts has postings, so it can only move inside the same account type (root). Postings: ' . implode('; ', $posted);
        }
        // an account with postings must stay a posting account: directly under a root it would become a reporting account
        $newDepth = (self::depthOf($db, $newParentId) ?? 0) + 1;
        if ($newDepth <= 2 && self::hasPostings($db, [$id])) {
            return "'" . $a['accountType'] . "' has postings and would become a top-level reporting account; move it under another account instead";
        }
        return null;
    }

    /** Why the name cannot be used (decision 75: trimmed, case-insensitive, within the visible scope, deleted rows included), or null. */
    public static function nameProblem(PDO $db, string $name, int $congregation, ?int $excludeId = null): ?string
    {
        $n = strtolower(trim($name));
        if ($n === '') { return 'Enter the account name'; }
        if (strlen($n) > 100) { return 'The account name is longer than 100 characters'; }
        $st = $db->prepare('SELECT ID, deleted FROM tblaccounttypes
                            WHERE TRIM(LOWER(accountType)) = ? AND ID <> ? AND (? = 0 OR congregationId IN (0, ?))
                            ORDER BY deleted LIMIT 1');
        $st->execute([$n, (int) $excludeId, $congregation, $congregation]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return null; }
        return (int) $r['deleted'] === 1
            ? "An account named '$n' was deleted; its name cannot be re-used"
            : "An account named '$n' already exists";
    }

    /**
     * Every reason the account cannot be soft-deleted (decision 70), empty = it can.
     * @return string[]
     */
    public static function deleteReasons(PDO $db, int $id): array
    {
        $a = self::load($db, $id);
        if (!$a) { return ['The account does not exist']; }
        $name = "'" . $a['accountType'] . "'";
        if ((int) $a['deleted'] === 1) { return ["$name is already deleted"]; }
        $out = [];
        if ((int) $a['isBank'] === 1) { $out[] = "$name is a bank: banks are deleted in the Banks module"; }
        elseif (self::isProtected($a)) { $out[] = "$name is a protected account"; }
        $st = $db->prepare('SELECT accountType FROM tblaccounttypes WHERE parentId = ? AND deleted = 0 ORDER BY accountType');
        $st->execute([$id]);
        $kids = $st->fetchAll(PDO::FETCH_COLUMN);
        if ($kids) {
            $out[] = "$name has " . count($kids) . ' sub account(s): ' . implode(', ', array_slice($kids, 0, 8)) . (count($kids) > 8 ? ', ...' : '');
        }
        foreach (self::postingReasons($db, $id) as $r) { $out[] = "$name has $r"; }
        return $out;
    }

    // -----------------------------------------------------------------
    // Picker
    // -----------------------------------------------------------------

    /**
     * The accounts that may be the parent of an account of $congregation (decisions 67 and 68), with their path.
     * $selfId (an account being edited) removes itself and its descendants. $root limits the list to one root's tree.
     * Returns [ ['ID'=>, 'path'=>'asset > cash ...', 'forGroup'=>, 'rootId'=>], ... ] sorted by path.
     */
    public static function allowedParents(PDO $db, int $congregation, ?int $selfId = null, ?int $root = null): array
    {
        $rows = [];
        foreach ($db->query('SELECT ID, accountType, parentId, isBank, forGroup, congregationId, deleted FROM tblaccounttypes')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[(int) $r['ID']] = $r;
        }
        $skip = [];
        if ($selfId !== null) { foreach (self::subtreeIds($db, $selfId) as $i) { $skip[$i] = true; } }

        $cand = [];
        foreach ($rows as $id => $r) {
            if ((int) $r['deleted'] === 1 || (int) $r['isBank'] === 1 || $id === self::BANKS_PARENT || isset($skip[$id])) { continue; }
            $pc = (int) $r['congregationId'];
            if ($pc !== 0 && $pc !== $congregation) { continue; }
            if ($congregation === 0 && $pc !== 0) { continue; }
            $names = [];
            $cur = $id;
            $guard = 0;
            $ok = true;
            while (true) {
                if (!isset($rows[$cur]) || ++$guard > self::MAX_DEPTH) { $ok = false; break; }
                array_unshift($names, $rows[$cur]['accountType']);
                if ((int) $rows[$cur]['parentId'] === 0) { break; }
                $cur = (int) $rows[$cur]['parentId'];
            }
            if (!$ok) { continue; }
            if ($root !== null && $cur !== $root) { continue; }
            $cand[$id] = ['ID' => $id, 'path' => implode(' > ', $names), 'forGroup' => (int) $r['forGroup'], 'rootId' => $cur];
        }
        $busy = self::hasPostings($db, array_keys($cand));
        $out = [];
        foreach ($cand as $id => $c) { if (!isset($busy[$id])) { $out[] = $c; } }
        usort($out, function ($a, $b) { return strcmp($a['path'], $b['path']); });
        return $out;
    }
}

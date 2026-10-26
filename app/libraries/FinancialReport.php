<?php
/**
 * FinancialReport - the single READ service behind every financial report (task 9, decisions 118-137 in
 * docs/ledger_inventory.md). It never writes.
 *
 * Source of every figure: journal_entries (deleted = 0) + journal_lines, keyed by account ID, over the chart
 * tblaccounttypes read as a tree. Nothing is matched by name and nothing is inferred.
 *
 * Scope: a list of congregation IDs (one for a congregation login, a validated selection for parish reports).
 * Every query filters journal_entries.deleted = 0 and journal_entries.congregationId IN (scope) and binds every value.
 *
 * Money: integer cents in PHP (DECIMAL strings are parsed digit by digit, never through float). public static cents() /
 * decimal() / money() convert.
 *
 * Normal balance (decision 119 ff.): root types 2 (expense), 3 (asset) and 5 (dividend) are debit-normal, 1 (income),
 * 4 (liability) and 6 (equity) credit-normal. A figure is the signed amount in the account's normal direction: a contra
 * balance stays negative (never ABS()).
 *
 * Period descriptors (passed to children() / lines(); every row returned by a builder carries the one to use for its
 * own drill-down, so a drill always keeps the basis of the report it came from):
 *   ['kind' => 'is',    'from' => Y-m-d, 'to' => Y-m-d, 'comparative' => bool]    income statement (decision 122)
 *   ['kind' => 'bs',    'asOf' => Y-m-d, 'comparative' => bool]                    balance sheet: types 3/4/6 cumulative
 *   ['kind' => 'tb',    'asOf' => Y-m-d]                                           trial balance (decision 129)
 *   ['kind' => 'range', 'from' => ?Y-m-d, 'to' => Y-m-d, 'direction' => 'normal'|'credit'|'debit', 'fy' => bool]   plain range
 *       (lines of an income / expense / dividend account restart at zero at every financial-year start unless 'fy' => false, decision 144)
 * Synthetic nodes of a 'bs' / 'tb' period: 'bf' (accumulated fund b/f), 'cy' (current year surplus, types 1/2) and
 * 'div' (type 5 in the current year) can be passed to children() in place of an account ID.
 *
 * PHP 7.4+.
 */

class FinancialReportException extends RuntimeException
{
}

class FinancialReport
{
    const MAX_DEPTH = AccountChart::MAX_DEPTH;

    /**
     * The fixed system account 'opening balance equity' (confirmed by the owner, like Ledger's account 20). Its top-level type-6
     * ancestor is the 'accumulated fund' account F6 created (decision 150); it is found through this ID, never by name.
     */
    const OPENING_BALANCE_EQUITY = 20;

    /** largest page linesPage() returns */
    const MAX_PAGE_SIZE = 200;

    /** root account types that are debit-normal */
    const DEBIT_NORMAL  = [2, 3, 5];
    /** root account types that are credit-normal */
    const CREDIT_NORMAL = [1, 4, 6];

    const ROOT_TITLES = [1 => 'Income', 2 => 'Expenses', 3 => 'Assets', 4 => 'Liabilities', 5 => 'Dividends', 6 => 'Equity'];

    /**
     * Source document of a journal entry, by transaction type (decision 124): a route RELATIVE to URLROOT, only where the
     * route exists, opens the record by the ID in journal_entries.transactionId and checks the congregation (verified in
     * task 9.1: see the report). 'enc' = the route expects encryptId($id). null = no link.
     */
    const SOURCE_ROUTES = [
        1  => ['contributions/edit/{id}',   'plain', 'receipt (edit screen, congregation checked by checkcenter)'],
        2  => ['expenses/print/{id}',       'plain', 'expense voucher'],
        3  => null,
        4  => null,
        5  => null,
        6  => ['supplierinvoices/print/{id}', 'plain', 'supplier invoice'],
        7  => ['payments/print/{id}',       'plain', 'supplier payment voucher'],
        8  => null,
        9  => ['bankpostings/edit/{id}',    'enc',   'bank posting (edit screen)'],
        10 => ['cashreceipts/edit/{id}',    'plain', 'petty cash receipt (edit screen)'],
        11 => ['mmfreceipts/edit/{id}',     'plain', 'MMF receipt (edit screen)'],
        12 => null,
        13 => ['banktransactions/edit/{id}', 'plain', 'bank transaction (edit screen, congregation checked by checkcenter)'],
        14 => ['invoices/print/{id}',       'plain', 'customer invoice'],
        15 => ['suppliers/edit/{id}',       'plain', 'supplier (opening balance)'],
        16 => null,
        17 => ['groupcollections/edit/{id}', 'plain', 'group collection (edit screen, congregation checked by checkcenter)'],
    ];

    /** Display form of an account label: first letter of each word (and of a bracketed parent name) in capitals. */
    public static function title(string $s): string
    {
        return ucwords($s, " (");
    }

    // =================================================================
    // Money
    // =================================================================

    /** Parse a DECIMAL string ("1234.50", "-0.10", null) into integer cents, with no float in between. */
    public static function cents($v): int
    {
        if ($v === null || $v === '') { return 0; }
        if (is_int($v)) { return $v * 100; }
        $s   = trim((string) $v);
        $neg = false;
        if ($s !== '' && ($s[0] === '-' || $s[0] === '+')) { $neg = $s[0] === '-'; $s = substr($s, 1); }
        $parts = explode('.', $s, 2);
        $int   = $parts[0] === '' ? '0' : $parts[0];
        $frac  = isset($parts[1]) ? substr(str_pad($parts[1], 2, '0'), 0, 2) : '00';
        if (!ctype_digit($int) || !ctype_digit($frac)) {
            throw new FinancialReportException("'" . (string) $v . "' is not a decimal amount");
        }
        $c = (int) $int * 100 + (int) $frac;
        return $neg ? -$c : $c;
    }

    /** "1234.50" / "-0.05" (plain decimal string, for storage and comparisons). */
    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** "1,234.50" / "-1,234.50" (display). */
    public static function money(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . number_format(intdiv(abs($cents), 100)) . '.' . str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** The signed amount in the normal direction of the root type (debit-normal: dr - cr, credit-normal: cr - dr). */
    public static function normalAmount(int $root, int $dr, int $cr): int
    {
        return in_array($root, self::DEBIT_NORMAL, true) ? $dr - $cr : $cr - $dr;
    }

    public static function isDebitNormal(int $root): bool
    {
        return in_array($root, self::DEBIT_NORMAL, true);
    }

    /** Trial balance columns of a net debit balance (dr - cr): ['debit' => cents, 'credit' => cents]; a contra balance moves column. */
    public static function trialBalanceColumns(int $netDebit): array
    {
        return ['debit' => max($netDebit, 0), 'credit' => max(-$netDebit, 0)];
    }

    /** Zero rule (decision 119): true when EVERY figure (current and comparative) is zero. Section totals never go through this. */
    public static function hideZero(array $figures): bool
    {
        foreach ($figures as $f) {
            if ($f !== null && (int) $f !== 0) { return false; }
        }
        return true;
    }

    // =================================================================
    // Scope and dates
    // =================================================================

    /** @return int[] sorted, unique, positive */
    public static function normalizeScope(array $scope): array
    {
        $out = [];
        foreach ($scope as $c) {
            if (is_int($c)) { $i = $c; }
            elseif (is_string($c) && ctype_digit($c) && strlen($c) <= 10) { $i = (int) $c; }
            else { throw new FinancialReportException('the congregation scope must be a list of positive integers'); }
            if ($i < 1) { throw new FinancialReportException('the congregation scope must be a list of positive integers'); }
            $out[$i] = $i;
        }
        if (!$out) { throw new FinancialReportException('the congregation scope is empty'); }
        ksort($out);
        return array_values($out);
    }

    private static function marks(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    public static function checkDate($d, string $label = 'date'): string
    {
        $s = is_string($d) ? $d : '';
        $o = DateTime::createFromFormat('!Y-m-d', $s);
        if (!$o || $o->format('Y-m-d') !== $s) { throw new FinancialReportException("$label '" . (is_scalar($d) ? (string) $d : '') . "' is not a valid Y-m-d date"); }
        return $s;
    }

    /**
     * The financial year covering a date, by the rule of Ledger::yearForDate() (a non-deleted one wins when a deleted one overlaps):
     * ['id','name','start','end','closed','deleted']. A date no year covers is a clear error.
     */
    public static function financialYear(PDO $db, string $date): array
    {
        self::checkDate($date);
        $y = Ledger::yearForDate($db, $date);
        if ($y === null) { throw new FinancialReportException('No financial year is defined for ' . $date); }
        $st = $db->prepare('SELECT startDate, endDate FROM tblfiscalyears WHERE ID = ?');
        $st->execute([(int) $y['ID']]);
        $d = $st->fetch(PDO::FETCH_ASSOC);
        return ['id' => (int) $y['ID'], 'name' => (string) $y['yearName'], 'start' => (string) $d['startDate'], 'end' => (string) $d['endDate'],
                'closed' => (int) $y['closed'], 'deleted' => (int) $y['deleted']];
    }

    /** Start date of the financial year containing the date. */
    public static function yearStart(PDO $db, string $date): string
    {
        return self::financialYear($db, $date)['start'];
    }

    /** The same calendar date one year earlier; 29 February becomes 28 February. */
    public static function sameDateLastYear(string $date): string
    {
        $o = DateTime::createFromFormat('!Y-m-d', self::checkDate($date));
        $y = (int) $o->format('Y') - 1;
        $m = (int) $o->format('n');
        $d = (int) $o->format('j');
        if ($m === 2 && $d === 29) { $d = 28; }
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    /** [from, to] of the same period last year (decision 122; both ends moved back one calendar year, 29 Feb -> 28 Feb). */
    public static function samePeriodLastYear(string $from, string $to): array
    {
        return [self::sameDateLastYear($from), self::sameDateLastYear($to)];
    }

    /** The last financial year-end before the date: the day before the start of the financial year containing it (decision 122). */
    public static function lastYearEnd(PDO $db, string $date): string
    {
        $o = DateTime::createFromFormat('!Y-m-d', self::yearStart($db, $date));
        $o->modify('-1 day');
        return $o->format('Y-m-d');
    }

    // =================================================================
    // Chart
    // =================================================================

    /**
     * The chart as a tree for a scope, loaded ONCE per call (no active filter). Included: non-deleted accounts that are
     * shared (congregationId 0) or the scope's own, every account (any congregation) that has journal lines in scope, and
     * the ancestors of those; deleted accounts ONLY when they or a descendant have journal lines in scope.
     *
     * node: id, name, parentId, root (1-6 or null), depth (1 = root, null when unclassified), path ('asset > cash ...'),
     *       pathNames, chain (self ... root), isBank, posting (AccountChart rules: depth >= 3 and no non-deleted child; the
     *       stored column is not read), active, deleted, congregationId, hasHistory (own lines in scope), classified
     *       (reachable from a root 1-6 through included nodes), typeMismatch (accountTypeId differs from the root).
     * Accounts that cannot be reached from a root (parent loop, missing or excluded parent) are 'classified' = false and are
     * listed in 'unclassified': every report shows them as a diagnostic and never drops their lines.
     *
     * @return array{nodes: array<int,array>, kids: array<int,int[]>, unclassified: int[], scope: int[]}
     */
    public static function chart(PDO $db, array $scope): array
    {
        $scope = self::normalizeScope($scope);
        $rows  = $db->query('SELECT ID, accountType, parentId, accountTypeId, isBank, isSubCategory, active, deleted, congregationId FROM tblaccounttypes')
                    ->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        $liveKids = [];
        foreach ($rows as $r) {
            $id = (int) $r['ID'];
            $byId[$id] = $r;
            if ((int) $r['deleted'] === 0) { $p = (int) $r['parentId']; $liveKids[$p] = ($liveKids[$p] ?? 0) + 1; }
        }

        $st = $db->prepare('SELECT DISTINCT l.accountId FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId
                            WHERE e.deleted = 0 AND e.congregationId IN (' . self::marks($scope) . ')');
        $st->execute($scope);
        $hist = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $a) { $hist[(int) $a] = true; }

        // the account and every ancestor of an account with history
        $below = [];
        foreach (array_keys($hist) as $h) {
            $cur = $h;
            for ($i = 0; $i <= self::MAX_DEPTH && isset($byId[$cur]); $i++) {
                if (isset($below[$cur]) && $cur !== $h) { break; }
                $below[$cur] = true;
                $cur = (int) $byId[$cur]['parentId'];
                if ($cur === 0) { break; }
            }
        }

        $visible = array_merge([0], $scope);
        $included = [];
        foreach ($byId as $id => $r) {
            $mine = in_array((int) $r['congregationId'], $visible, true);
            if ((int) $r['deleted'] === 0) {
                if ($mine || isset($below[$id])) { $included[$id] = true; }
            } elseif (isset($below[$id])) {
                $included[$id] = true;
            }
        }

        $nodes = [];
        $kids = [];
        $unclassified = [];
        foreach (array_keys($included) as $id) {
            $r = $byId[$id];
            $chain = [];
            $seen = [];
            $cur = $id;
            $ok = false;
            while (true) {
                if (isset($seen[$cur]) || !isset($included[$cur]) || count($chain) > self::MAX_DEPTH) { break; }
                $seen[$cur] = true;
                $chain[] = $cur;
                if ((int) $byId[$cur]['parentId'] === 0) { $ok = true; break; }
                $cur = (int) $byId[$cur]['parentId'];
            }
            $root = null;
            if ($ok) {
                $rootId = (int) end($chain);
                $root = ($rootId >= 1 && $rootId <= 6) ? $rootId : null;
                if ($root === null) { $ok = false; }
            }
            $names = [];
            if ($ok) { foreach (array_reverse($chain) as $c) { $names[] = (string) $byId[$c]['accountType']; } }
            else { $names[] = (string) $r['accountType']; }
            $depth = $ok ? count($chain) : null;
            $nodes[$id] = [
                'id'             => $id,
                'name'           => (string) $r['accountType'],
                'parentId'       => (int) $r['parentId'],
                'root'           => $ok ? $root : null,
                'depth'          => $depth,
                'path'           => implode(' > ', $names),
                'pathNames'      => $names,
                'chain'          => $ok ? $chain : [$id],
                'isBank'         => (int) $r['isBank'] === 1,
                'posting'        => $ok && $depth >= 3 && empty($liveKids[$id]),
                'active'         => (int) $r['active'] === 1,
                'deleted'        => (int) $r['deleted'] === 1,
                'congregationId' => (int) $r['congregationId'],
                'hasHistory'     => isset($hist[$id]),
                'classified'     => $ok,
                'typeMismatch'   => $ok && $depth >= 2 && $r['accountTypeId'] !== null && (int) $r['accountTypeId'] !== $root,
            ];
            if ($ok) { $kids[(int) $r['parentId']][] = $id; }
            else { $unclassified[] = $id; }
        }
        foreach ($kids as $p => $list) {
            usort($list, function ($a, $b) use ($nodes) { return strnatcasecmp($nodes[$a]['name'], $nodes[$b]['name']) ?: $a <=> $b; });
            $kids[$p] = $list;
        }
        sort($unclassified);
        // decision 149: the label of an account is its name only; two visible accounts with one name get the parent's name in
        // brackets (and the ID if even the parents share a name); (deleted) / (inactive) suffixes stay. The path is never shown.
        $visibleCong = array_merge([0], $scope);
        $seen = [];
        foreach ($nodes as $n) {
            if ($n['classified'] && $n['depth'] >= 2 && in_array($n['congregationId'], $visibleCong, true)) { $seen[strtolower(trim($n['name']))][] = $n['id']; }
        }
        foreach ($nodes as $id => $n) {
            $label = $n['name'];
            $key = strtolower(trim($n['name']));
            if ($n['classified'] && $n['depth'] >= 2 && count($seen[$key] ?? []) > 1) {
                $parent = $nodes[$n['parentId']]['name'] ?? '';
                $label .= ' (' . $parent . ')';
                $twins = 0;
                foreach ($seen[$key] as $tid) { if (strtolower($nodes[$nodes[$tid]['parentId']]['name'] ?? '') === strtolower($parent)) { $twins++; } }
                if ($twins > 1) { $label .= ' [' . $id . ']'; }
            }
            if ($n['classified'] && $n['depth'] >= 2) { $label .= ($n['deleted'] ? ' (deleted)' : '') . (!$n['active'] ? ' (inactive)' : ''); }
            $nodes[$id]['label'] = $label;
        }
        return ['nodes' => $nodes, 'kids' => $kids, 'unclassified' => $unclassified, 'scope' => $scope];
    }

    // =================================================================
    // Balances
    // =================================================================

    /**
     * Debit / credit sums per account in ONE set-based query. Two buckets split at $boundary:
     *   b* = entries dated before $boundary (none when $boundary is null),  i* = from $boundary (inception when null) to $to.
     * @return array<int, array{bdr:int,bcr:int,idr:int,icr:int}> direct (own-line) figures, integer cents
     */
    public static function balances(PDO $db, array $scope, ?string $boundary, string $to): array
    {
        $scope = self::normalizeScope($scope);
        self::checkDate($to, 'to date');
        if ($boundary !== null) { self::checkDate($boundary, 'boundary date'); }
        $b = $boundary ?? '0001-01-01';
        $st = $db->prepare("SELECT l.accountId,
                                   SUM(CASE WHEN e.entryDate <  ? AND l.dc = 'DEBIT'  THEN l.amount ELSE 0 END) AS bdr,
                                   SUM(CASE WHEN e.entryDate <  ? AND l.dc = 'CREDIT' THEN l.amount ELSE 0 END) AS bcr,
                                   SUM(CASE WHEN e.entryDate >= ? AND l.dc = 'DEBIT'  THEN l.amount ELSE 0 END) AS idr,
                                   SUM(CASE WHEN e.entryDate >= ? AND l.dc = 'CREDIT' THEN l.amount ELSE 0 END) AS icr
                            FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId
                            WHERE e.deleted = 0 AND e.congregationId IN (" . self::marks($scope) . ") AND e.entryDate <= ?
                            GROUP BY l.accountId");
        $st->execute(array_merge([$b, $b, $b, $b], $scope, [$to]));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['accountId']] = ['bdr' => self::cents($r['bdr']), 'bcr' => self::cents($r['bcr']),
                                           'idr' => self::cents($r['idr']), 'icr' => self::cents($r['icr'])];
        }
        return $out;
    }

    /**
     * Debit minus credit, in cents, of the lines posted ON ONE account (not its sub-accounts) by active entries of the scope, dated up
     * to and including $to (9.5: the book balance of a bank). Inception when $to is the only bound.
     */
    public static function accountNet(PDO $db, array $scope, int $accountId, string $to): int
    {
        $scope = self::normalizeScope($scope);
        self::checkDate($to, 'to date');
        $st = $db->prepare("SELECT SUM(CASE WHEN l.dc = 'DEBIT' THEN l.amount ELSE 0 END) AS dr, SUM(CASE WHEN l.dc = 'CREDIT' THEN l.amount ELSE 0 END) AS cr
                            FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId
                            WHERE l.accountId = ? AND e.deleted = 0 AND e.congregationId IN (" . self::marks($scope) . ") AND e.entryDate <= ?");
        $st->execute(array_merge([$accountId], $scope, [$to]));
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return self::cents($r['dr']) - self::cents($r['cr']);
    }
    private static function zeroBuckets(): array
    {
        return ['bdr' => 0, 'bcr' => 0, 'idr' => 0, 'icr' => 0];
    }

    /**
     * Roll the direct figures up to every ancestor. Accounts that are not classified (or not in the chart at all) go to
     * 'unclassified' and are never added to a root.
     * @return array{total: array<int,array>, direct: array<int,array>, unclassified: array<int,array>}
     */
    public static function rollup(array $chart, array $balances): array
    {
        $total = [];
        $direct = [];
        $unc = [];
        foreach ($balances as $id => $b) {
            $node = $chart['nodes'][$id] ?? null;
            if ($node === null || !$node['classified']) { $unc[$id] = $b; continue; }
            $direct[$id] = $b;
            foreach ($node['chain'] as $anc) {
                if (!isset($total[$anc])) { $total[$anc] = self::zeroBuckets(); }
                foreach ($b as $k => $v) { $total[$anc][$k] += $v; }
            }
        }
        return ['total' => $total, 'direct' => $direct, 'unclassified' => $unc];
    }

    // =================================================================
    // Fund (types 1, 2, 5)
    // =================================================================

    /**
     * Accumulated fund b/f (decision 121): all prior years' result, types 1, 2 and 5, credit - debit, before the start of the
     * financial year containing $date. Cents.
     */
    public static function accumulatedFundBf(PDO $db, array $scope, string $date, ?array $chart = null): int
    {
        $chart = $chart ?? self::chart($db, $scope);
        $start = self::yearStart($db, $date);
        $r = self::rollup($chart, self::balances($db, $scope, $start, $date));
        return self::fundOf($r['total'], [1, 2, 5], 'b');
    }

    /**
     * Current year surplus: the same (types 1, 2, 5, credit - debit) from the financial-year start to $date. Cents. The
     * balance sheet shows its two parts apart: the surplus of types 1 and 2, and the dividends of type 5 (decision 120).
     */
    public static function currentYearSurplus(PDO $db, array $scope, string $date, ?array $chart = null): int
    {
        $chart = $chart ?? self::chart($db, $scope);
        $start = self::yearStart($db, $date);
        $r = self::rollup($chart, self::balances($db, $scope, $start, $date));
        return self::fundOf($r['total'], [1, 2, 5], 'i');
    }

    /** credit - debit of the given roots in the 'b' or 'i' bucket of rolled totals */
    private static function fundOf(array $rolled, array $roots, string $bucket): int
    {
        $n = 0;
        foreach ($roots as $r) {
            $b = $rolled[$r] ?? self::zeroBuckets();
            $n += $b[$bucket . 'cr'] - $b[$bucket . 'dr'];
        }
        return $n;
    }

    // =================================================================
    // Periods -> sets of figures
    // =================================================================

    /**
     * Turn a period descriptor into figure sets: each ['key' => 'cur'|'cmp', 'mode', 'boundary', 'to', 'direction', ...].
     * @return array{mode:string, sets: array<int,array>, period: array, comparativeError: ?string}
     */
    private static function describe(PDO $db, array $period): array
    {
        $kind = (string) ($period['kind'] ?? '');
        $err  = null;
        $sets = [];
        $norm = ['kind' => $kind];
        if ($kind === 'is') {
            $from = self::checkDate($period['from'] ?? null, 'from date');
            $to   = self::checkDate($period['to'] ?? null, 'to date');
            if ($from > $to) { throw new FinancialReportException("the start date $from is after the end date $to"); }
            $sets[] = ['key' => 'cur', 'mode' => 'is', 'boundary' => $from, 'to' => $to, 'from' => $from];
            $norm += ['from' => $from, 'to' => $to, 'comparative' => !empty($period['comparative'])];
            if (!empty($period['comparative'])) {
                [$cf, $ct] = self::samePeriodLastYear($from, $to);
                $sets[] = ['key' => 'cmp', 'mode' => 'is', 'boundary' => $cf, 'to' => $ct, 'from' => $cf];
            }
        } elseif ($kind === 'bs' || $kind === 'tb') {
            $asOf = self::checkDate($period['asOf'] ?? null, 'as-of date');
            $fy   = self::financialYear($db, $asOf);
            $sets[] = ['key' => 'cur', 'mode' => $kind, 'boundary' => $fy['start'], 'to' => $asOf, 'asOf' => $asOf, 'yearStart' => $fy['start']];
            $norm += ['asOf' => $asOf];
            if ($kind === 'bs') {
                $norm['comparative'] = !empty($period['comparative']);
                if (!empty($period['comparative'])) {
                    try {
                        $prior = self::lastYearEnd($db, $asOf);
                        $pfy   = self::financialYear($db, $prior);
                        $sets[] = ['key' => 'cmp', 'mode' => 'bs', 'boundary' => $pfy['start'], 'to' => $prior, 'asOf' => $prior, 'yearStart' => $pfy['start']];
                    } catch (FinancialReportException $e) {
                        $err = 'No comparative: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($kind === 'range') {
            $from = ($period['from'] ?? null) === null ? null : self::checkDate($period['from'], 'from date');
            $to   = self::checkDate($period['to'] ?? null, 'to date');
            $dir  = (string) ($period['direction'] ?? 'normal');
            if (!in_array($dir, ['normal', 'credit', 'debit'], true)) { throw new FinancialReportException("unknown direction '$dir'"); }
            if ($from !== null && $from > $to) { throw new FinancialReportException("the start date $from is after the end date $to"); }
            $fy = !array_key_exists('fy', $period) || $period['fy'] !== false;
            $sets[] = ['key' => 'cur', 'mode' => 'range', 'boundary' => $from, 'to' => $to, 'from' => $from, 'direction' => $dir, 'fy' => $fy];
            $norm += ['from' => $from, 'to' => $to, 'direction' => $dir, 'fy' => $fy];
        } else {
            throw new FinancialReportException("unknown period kind '$kind'");
        }
        return ['mode' => $kind, 'sets' => $sets, 'period' => $norm, 'comparativeError' => $err];
    }

    /** The figure of a node (root type $root, rolled buckets $b) in the presentation of a set. */
    private static function figure(array $set, int $root, array $b): int
    {
        switch ($set['mode']) {
            case 'is':
                return self::normalAmount($root, $b['idr'], $b['icr']);
            case 'bs':
                return self::normalAmount($root, $b['bdr'] + $b['idr'], $b['bcr'] + $b['icr']);
            case 'tb':
                // net debit: balance-sheet types cumulative, income / expense / dividend types from the financial-year start
                return in_array($root, [3, 4, 6], true) ? ($b['bdr'] + $b['idr']) - ($b['bcr'] + $b['icr']) : $b['idr'] - $b['icr'];
            default: // range
                if ($set['direction'] === 'credit') { return $b['icr'] - $b['idr']; }
                if ($set['direction'] === 'debit')  { return $b['idr'] - $b['icr']; }
                return self::normalAmount($root, $b['idr'], $b['icr']);
        }
    }

    /** Figures of a node whose chain is classified, or of an unclassified account (root unknown: net debit, cumulative). */
    private static function nodeFigure(array $set, ?int $root, array $b): int
    {
        if ($root === null) {
            return ($set['mode'] === 'is' || $set['mode'] === 'range') ? $b['idr'] - $b['icr'] : ($b['bdr'] + $b['idr']) - ($b['bcr'] + $b['icr']);
        }
        return self::figure($set, $root, $b);
    }

    /**
     * Build the working context of a request: the chart once, and per set one balances query + roll-up.
     * @return array<string,mixed>
     */
    private static function context(PDO $db, array $scope, array $period, ?array $chart = null): array
    {
        $scope = self::normalizeScope($scope);
        $d     = self::describe($db, $period);
        $chart = $chart ?? self::chart($db, $scope);
        $sets  = [];
        foreach ($d['sets'] as $spec) {
            $r = self::rollup($chart, self::balances($db, $scope, $spec['boundary'], $spec['to']));
            $sets[$spec['key']] = ['spec' => $spec, 'total' => $r['total'], 'direct' => $r['direct'], 'unc' => $r['unclassified']];
        }
        return ['db' => $db, 'scope' => $scope, 'chart' => $chart, 'mode' => $d['mode'], 'period' => $d['period'], 'sets' => $sets,
                'comparativeError' => $d['comparativeError'], 'vis' => []];
    }

    /** [current, comparative|null] figures of a node, rolled (own + descendants) or direct (own lines only). */
    private static function figs(array $ctx, int $id, bool $direct = false): array
    {
        $node = $ctx['chart']['nodes'][$id];
        $out = [];
        foreach (['cur', 'cmp'] as $k) {
            if (!isset($ctx['sets'][$k])) { $out[$k] = null; continue; }
            $s = $ctx['sets'][$k];
            $b = ($direct ? ($s['direct'][$id] ?? null) : ($s['total'][$id] ?? null)) ?? self::zeroBuckets();
            $out[$k] = self::figure($s['spec'], (int) $node['root'], $b);
        }
        return $out;
    }

    /** zero rule for the tree: visible when the node, its direct postings or any visible descendant has a non-zero figure */
    private static function visible(array &$ctx, int $id, int $depthGuard = 0): bool
    {
        if (isset($ctx['vis'][$id])) { return $ctx['vis'][$id]; }
        $f = self::figs($ctx, $id);
        $v = !self::hideZero([$f['cur'], $f['cmp']]);
        if (!$v && $depthGuard <= self::MAX_DEPTH) {
            foreach ($ctx['chart']['kids'][$id] ?? [] as $k) {
                if (self::visible($ctx, $k, $depthGuard + 1)) { $v = true; break; }
            }
        }
        return $ctx['vis'][$id] = $v;
    }

    /**
     * The rows one level below $parentId (a node of the chart): its visible child accounts and, when the node itself has
     * postings, one 'direct' row for them, so the rows always add up to the node's figure. Zero rows are left out (119).
     */
    private static function levelRows(array &$ctx, int $parentId, bool $applyZeroRule = true): array
    {
        $rows = [];
        foreach ($ctx['chart']['kids'][$parentId] ?? [] as $kid) {
            if ($applyZeroRule && !self::visible($ctx, $kid)) { continue; }
            $rows[] = self::rowOf($ctx, $kid, false);
        }
        $dir = self::figs($ctx, $parentId, true);
        $has = isset($ctx['sets']['cur']['direct'][$parentId]);
        if ($applyZeroRule ? !self::hideZero([$dir['cur'], $dir['cmp']]) : $has) {
            $rows[] = self::rowOf($ctx, $parentId, true);
        }
        return $rows;
    }

    private static function rowOf(array &$ctx, int $id, bool $direct): array
    {
        $node = $ctx['chart']['nodes'][$id];
        $f    = self::figs($ctx, $id, $direct);
        $hasKids = false;
        if (!$direct) {
            foreach ($ctx['chart']['kids'][$id] ?? [] as $k) { if (self::visible($ctx, $k)) { $hasKids = true; break; } }
        }
        $row = [
            'id'          => $id,
            'direct'      => $direct,
            'name'        => $direct ? $node['label'] . ' (postings on this account)' : $node['label'],
            'rawName'     => $node['name'],
            'path'        => $node['path'],
            'depth'       => $node['depth'],
            'root'        => $node['root'],
            'posting'     => $node['posting'],
            'isBank'      => $node['isBank'],
            'active'      => $node['active'],
            'deleted'     => $node['deleted'],
            'amount'      => $f['cur'],
            'comparative' => $f['cmp'],
            'hasChildren' => $hasKids,
            // a node can be opened to its journal lines when its own postings (or, without children, its subtree) have figures
            'hasLines'    => $direct || !$hasKids,
            'period'      => $ctx['period'],
        ];
        if ($ctx['mode'] === 'tb') {
            $row += self::trialBalanceColumns((int) $f['cur']);
            $row['net'] = $f['cur'];
        }
        return $row;
    }

    private static function sum(array $rows, string $key = 'amount'): int
    {
        $n = 0;
        foreach ($rows as $r) { $n += (int) $r[$key]; }
        return $n;
    }

    /** A root's section: its visible top-level lines and its TOTAL (always shown, even at 0.00). */
    private static function section(array &$ctx, int $root): array
    {
        $f = self::figs($ctx, $root);
        $rows = self::levelRows($ctx, $root);
        return [
            'root'             => $root,
            'title'            => self::ROOT_TITLES[$root],
            'rows'             => $rows,
            'total'            => $f['cur'],
            'comparativeTotal' => $f['cmp'],
            'sumsExactly'      => self::sum($rows) === (int) $f['cur'],
        ];
    }

    private static function unclassifiedReport(array $ctx): array
    {
        $rows = [];
        $set = $ctx['sets']['cur'];
        foreach ($set['unc'] as $id => $b) {
            $node = $ctx['chart']['nodes'][$id] ?? null;
            $net  = self::nodeFigure($set['spec'], null, $b);
            $rows[] = ['id' => $id, 'name' => $node ? $node['name'] : ('account ' . $id), 'path' => $node ? $node['path'] : ('account ' . $id),
                       'net' => $net, 'debit' => max($net, 0), 'credit' => max(-$net, 0)];
        }
        usort($rows, function ($a, $b) { return $a['id'] <=> $b['id']; });
        $net = 0;
        foreach ($rows as $r) { $net += $r['net']; }
        return ['rows' => $rows, 'net' => $net, 'present' => count($rows) > 0,
                'message' => count($rows) ? count($rows) . ' account(s) with postings cannot be reached from a root account of the chart (parent loop, missing or deleted parent); their lines are not part of any type total.' : ''];
    }

    private static function check(string $name, bool $ok, string $detail = ''): array
    {
        return ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    }

    // =================================================================
    // Report builders
    // =================================================================

    /**
     * Income statement (decisions 119, 120, 122, 130): types 1 and 2 by top-level account, surplus = income - expense, type 5
     * (dividends) as its own block below the surplus only when non-zero in either period.
     */
    public static function incomeStatement(PDO $db, array $scope, string $from, string $to, bool $comparative = false): array
    {
        $ctx = self::context($db, $scope, ['kind' => 'is', 'from' => $from, 'to' => $to, 'comparative' => $comparative]);
        $inc = self::section($ctx, 1);
        $exp = self::section($ctx, 2);
        $div = self::section($ctx, 5);
        $cmp = isset($ctx['sets']['cmp']);
        $surplus = (int) $inc['total'] - (int) $exp['total'];
        $surplusC = $cmp ? (int) $inc['comparativeTotal'] - (int) $exp['comparativeTotal'] : null;
        $showDiv = !self::hideZero([$div['total'], $div['comparativeTotal']]);
        $unc = self::unclassifiedReport($ctx);
        return [
            'kind'        => 'income_statement',
            'scope'       => $ctx['scope'],
            'from'        => $from,
            'to'          => $to,
            'comparative' => $cmp ? ['from' => $ctx['sets']['cmp']['spec']['from'], 'to' => $ctx['sets']['cmp']['spec']['to']] : null,
            'period'      => $ctx['period'],
            'sections'    => ['income' => $inc, 'expense' => $exp],
            'surplus'     => ['amount' => $surplus, 'comparative' => $surplusC],
            'dividends'   => $showDiv ? $div : null,
            'surplusAfterDividends' => $showDiv ? ['amount' => $surplus - (int) $div['total'], 'comparative' => $cmp ? $surplusC - (int) $div['comparativeTotal'] : null] : null,
            'unclassified' => $unc,
            'checks'      => [
                self::check('income lines add up to the income total', $inc['sumsExactly']),
                self::check('expense lines add up to the expense total', $exp['sumsExactly']),
                self::check('dividend lines add up to the dividend total', $div['sumsExactly']),
                self::check('no unclassified postings', !$unc['present'], $unc['message']),
            ],
        ];
    }

    /**
     * Balance sheet (decisions 119-122, 130): assets (3), liabilities (4), equity (6), cumulative to the date, plus the fund:
     * accumulated fund b/f, current year surplus and (when non-zero) dividends. Assets must equal liabilities + equity + fund;
     * a difference is returned as a diagnostic line, never absorbed.
     */
    public static function balanceSheet(PDO $db, array $scope, string $asOf, bool $comparative = false): array
    {
        $ctx = self::context($db, $scope, ['kind' => 'bs', 'asOf' => $asOf, 'comparative' => $comparative]);
        $assets = self::section($ctx, 3);
        $liab   = self::section($ctx, 4);
        $equity = self::section($ctx, 6);
        $fund   = self::bsFund($ctx);
        if ($fund['fundAccountId'] !== null) {
            // decision 150: the accumulated fund account is part of the accumulated fund block, not a type 6 line of its own
            $fa = $fund['fundAccountId'];
            $f = self::figs($ctx, $fa);
            $equity['rows'] = array_values(array_filter($equity['rows'], function ($r) use ($fa) { return $r['id'] !== $fa; }));
            $equity['total'] -= (int) $f['cur'];
            if ($equity['comparativeTotal'] !== null) { $equity['comparativeTotal'] -= (int) $f['cmp']; }
            $equity['sumsExactly'] = self::sum($equity['rows']) === $equity['total'];
            // the block holds the fund account's balance at the year start and its movement; its direct postings, if any, are inside
        }
        $unc    = self::unclassifiedReport($ctx);
        $cmp    = isset($ctx['sets']['cmp']);

        $tot = function (string $k) use ($assets, $liab, $equity, $fund) {
            $a = (int) $assets[$k === 'cur' ? 'total' : 'comparativeTotal'];
            $l = (int) $liab[$k === 'cur' ? 'total' : 'comparativeTotal'];
            $e = (int) $equity[$k === 'cur' ? 'total' : 'comparativeTotal'];
            $f = (int) $fund[$k === 'cur' ? 'total' : 'comparativeTotal'];
            return ['assets' => $a, 'liabilitiesEquityFund' => $l + $e + $f, 'difference' => $a - ($l + $e + $f)];
        };
        $cur = $tot('cur');
        $cm  = $cmp ? $tot('cmp') : null;
        $fy  = self::financialYear($db, $asOf);
        return [
            'kind'          => 'balance_sheet',
            'scope'         => $ctx['scope'],
            'asOf'          => $asOf,
            'financialYear' => $fy,
            'comparative'   => $cmp ? ['asOf' => $ctx['sets']['cmp']['spec']['asOf']] : null,
            'comparativeError' => $ctx['comparativeError'],
            'period'        => $ctx['period'],
            'sections'      => ['assets' => $assets, 'liabilities' => $liab, 'equity' => $equity, 'fund' => $fund],
            'totals'        => ['current' => $cur, 'comparative' => $cm],
            // the diagnostic line: shown whenever Assets differ from Liabilities + Equity + fund
            'differenceLine' => ($cur['difference'] !== 0 || ($cm !== null && $cm['difference'] !== 0))
                ? ['name' => 'Difference (assets - liabilities - equity - fund)', 'amount' => $cur['difference'], 'comparative' => $cm['difference'] ?? null] : null,
            'unclassified'  => $unc,
            'checks'        => [
                self::check('assets = liabilities + equity + fund', $cur['difference'] === 0 && ($cm === null || $cm['difference'] === 0),
                            'difference ' . self::money($cur['difference'])),
                self::check('every section adds up', $assets['sumsExactly'] && $liab['sumsExactly'] && $equity['sumsExactly']),
                self::check('no unclassified postings', !$unc['present'], $unc['message']),
            ],
        ];
    }

    /**
     * The 'accumulated fund' account of the balance sheet (decision 150): the top-level (directly under the root) type-6 account
     * that contains OPENING_BALANCE_EQUITY. null when that cannot be established unambiguously (account 20 missing, not under a
     * type-6 root, directly under the root itself, or not reachable); the balance sheet then keeps the separate presentation and
     * says so. Nothing is guessed.
     */
    public static function fundAccountId(array $chart): ?int
    {
        $n = $chart['nodes'][self::OPENING_BALANCE_EQUITY] ?? null;
        if ($n === null || !$n['classified'] || $n['root'] !== 6 || $n['depth'] < 3) { return null; }
        $top = $n['chain'][count($n['chain']) - 2] ?? null;
        $t = $top === null ? null : ($chart['nodes'][$top] ?? null);
        return ($t !== null && $t['classified'] && $t['root'] === 6 && $t['depth'] === 2 && !$t['deleted']) ? (int) $top : null;
    }

    /**
     * The parts of the accumulated fund block at a date (decision 150), in cents, credit positive: the fund account's balance at the
     * financial-year start (`acctBf`) and its movement since (`acctMove`), the prior years' surplus of types 1, 2, 5 (`prior`), the
     * surplus of types 1, 2 (`surplus`) and the type 5 result (`dividends`, a deduction), b/f = acctBf + prior, c/f = b/f + surplus +
     * dividends + acctMove. `fundAccountId` null = separate presentation (acctBf, acctMove = 0).
     * @return array<string,mixed>
     */
    public static function accumulatedFundParts(PDO $db, array $scope, string $asOf, ?array $chart = null): array
    {
        $ctx = self::context($db, $scope, ['kind' => 'bs', 'asOf' => $asOf, 'comparative' => false], $chart);
        return self::fundParts($ctx, 'cur');
    }

    private static function fundParts(array $ctx, string $k): array
    {
        $fa = self::fundAccountId($ctx['chart']);
        $t = $ctx['sets'][$k]['total'];
        $b = ($fa !== null ? ($t[$fa] ?? null) : null) ?? self::zeroBuckets();
        $acctBf = $fa === null ? 0 : $b['bcr'] - $b['bdr'];
        $acctMove = $fa === null ? 0 : $b['icr'] - $b['idr'];
        $prior = self::fundOf($t, [1, 2, 5], 'b');
        $surplus = self::fundOf($t, [1, 2], 'i');
        $div = self::fundOf($t, [5], 'i');
        $bf = $acctBf + $prior;
        return ['fundAccountId' => $fa, 'acctBf' => $acctBf, 'acctMove' => $acctMove, 'prior' => $prior, 'surplus' => $surplus, 'dividends' => $div,
                'bf' => $bf, 'cf' => $bf + $surplus + $div + $acctMove];
    }
    /** The fund block of a balance sheet: b/f, current year surplus, dividends (only when non-zero), movement on the fund account (only when non-zero). */
    private static function bsFund(array $ctx): array
    {
        $parts = ['cur' => self::fundParts($ctx, 'cur'), 'cmp' => isset($ctx['sets']['cmp']) ? self::fundParts($ctx, 'cmp') : null];
        $fa = $parts['cur']['fundAccountId'];
        $val = function (string $key, string $k) use ($parts) { return $parts[$k] === null ? null : $parts[$k][$key]; };
        $def = ['bf' => 'Accumulated fund b/f', 'cy' => 'Surplus / (deficit) for the year to date', 'div' => 'Dividends', 'mv' => 'Movement on the accumulated fund account during the year'];
        $src = ['bf' => 'bf', 'cy' => 'surplus', 'div' => 'dividends', 'mv' => 'acctMove'];
        $rows = [];
        foreach ($def as $code => $label) {
            $cur = $val($src[$code], 'cur'); $cmp = $val($src[$code], 'cmp');
            // zero rule (119): a zero line is left out (type 5 therefore shows only when non-zero, decision 120); the block TOTAL always shows
            if (self::hideZero([$cur, $cmp])) { continue; }
            $rows[] = ['id' => $code, 'synthetic' => true, 'direct' => false, 'name' => $label, 'path' => $label, 'depth' => 2, 'root' => null,
                       'posting' => false, 'amount' => $cur, 'comparative' => $cmp, 'hasChildren' => true, 'hasLines' => false, 'period' => $ctx['period']];
        }
        $total = $parts['cur']['cf'];
        $totalC = $parts['cmp'] === null ? null : $parts['cmp']['cf'];
        // the surplus of the year to date is shown apart from the prior years' surplus, which is part of b/f
        return ['root' => null, 'title' => 'Accumulated fund', 'rows' => $rows, 'total' => $total, 'comparativeTotal' => $totalC,
                'sumsExactly' => self::sum($rows) === $total,
                'fundAccountId' => $fa,
                'diagnostic' => $fa === null ? "The accumulated fund account (the type 6 account that contains account " . self::OPENING_BALANCE_EQUITY . ") could not be identified unambiguously; the type 6 accounts are shown separately and the accumulated fund holds only the result of the financial years." : null,
                'surplus' => ['cur' => $parts['cur']['surplus'], 'cmp' => $val('surplus', 'cmp')],
                'dividends' => ['cur' => $parts['cur']['dividends'], 'cmp' => $val('dividends', 'cmp')],
                'bf' => ['cur' => $parts['cur']['bf'], 'cmp' => $val('bf', 'cmp')],
                'prior' => ['cur' => $parts['cur']['prior'], 'cmp' => $val('prior', 'cmp')],
                'acctBf' => ['cur' => $parts['cur']['acctBf'], 'cmp' => $val('acctBf', 'cmp')],
                'acctMove' => ['cur' => $parts['cur']['acctMove'], 'cmp' => $val('acctMove', 'cmp')]];
    }

    /**
     * Trial balance (decisions 129-131). Basis: types 3, 4, 6 cumulative to $asOf; types 1, 2, 5 from the start of the financial
     * year containing $asOf; one 'accumulated fund b/f' line (types 1, 2, 5 before that start). Rows are the top-level accounts
     * (or, $detailed, every account with postings of its own). Debits must equal credits; unclassified postings are listed and
     * included in the totals so the check stays honest.
     */
    public static function trialBalance(PDO $db, array $scope, string $asOf, bool $detailed = false): array
    {
        $ctx = self::context($db, $scope, ['kind' => 'tb', 'asOf' => $asOf]);
        $fy  = self::financialYear($db, $asOf);
        $rows = [];
        $order = [3, 4, 6, 1, 2, 5];
        if ($detailed) {
            // every account with postings of its own (posting accounts, plus any reporting account that still carries postings)
            $list = [];
            foreach ($ctx['sets']['cur']['direct'] as $id => $_) {
                $f = self::figs($ctx, $id, true);
                if (self::hideZero([$f['cur']])) { continue; }
                $n = $ctx['chart']['nodes'][$id];
                $list[] = array_merge([
                    'id' => $id, 'direct' => false, 'name' => $n['name'], 'path' => $n['path'], 'depth' => $n['depth'], 'root' => $n['root'],
                    'posting' => $n['posting'], 'isBank' => $n['isBank'], 'active' => $n['active'], 'deleted' => $n['deleted'],
                    'amount' => $f['cur'], 'comparative' => null, 'net' => $f['cur'], 'hasChildren' => false, 'hasLines' => true,
                    'period' => $ctx['period'],
                ], self::trialBalanceColumns((int) $f['cur']));
            }
            foreach ($order as $root) {
                $sub = array_values(array_filter($list, function ($r) use ($root) { return $r['root'] === $root; }));
                usort($sub, function ($a, $b) { return strnatcasecmp($a['path'], $b['path']) ?: $a['id'] <=> $b['id']; });
                foreach ($sub as $r) { $r['section'] = self::ROOT_TITLES[$root]; $rows[] = $r; }
            }
        } else {
            foreach ($order as $root) {
                foreach (self::levelRows($ctx, $root) as $r) { $r['section'] = self::ROOT_TITLES[$root]; $rows[] = $r; }
            }
        }
        // accumulated fund b/f: net debit = types 1, 2, 5 debit - credit before the year start
        $t = $ctx['sets']['cur']['total'];
        $bfNet = -self::fundOf($t, [1, 2, 5], 'b');
        if (!self::hideZero([$bfNet])) {
            $rows[] = array_merge(['id' => 'bf', 'synthetic' => true, 'direct' => false, 'name' => "Prior years' surplus b/f", 'path' => "Prior years' surplus b/f", 'depth' => 2,
                                   'root' => null, 'posting' => false, 'amount' => $bfNet, 'comparative' => null, 'net' => $bfNet,
                                   'hasChildren' => true, 'hasLines' => false, 'period' => $ctx['period'], 'section' => 'Fund'],
                                  self::trialBalanceColumns($bfNet));
        }
        $unc = self::unclassifiedReport($ctx);
        foreach ($unc['rows'] as $u) {
            $rows[] = ['id' => $u['id'], 'unclassified' => true, 'direct' => false, 'name' => $u['name'], 'path' => $u['path'], 'depth' => null, 'root' => null,
                       'posting' => false, 'amount' => $u['net'], 'comparative' => null, 'net' => $u['net'], 'debit' => $u['debit'], 'credit' => $u['credit'],
                       'hasChildren' => false, 'hasLines' => true, 'period' => $ctx['period'], 'section' => 'Unclassified'];
        }
        $dr = 0; $cr = 0;
        foreach ($rows as $r) { $dr += $r['debit']; $cr += $r['credit']; }
        // totals must also hold when zero rows were hidden: compute them from every figure, not only the visible ones
        $allNet = $bfNet + $unc['net'];
        foreach ($order as $root) { $allNet += (int) self::figs($ctx, $root)['cur']; }
        return [
            'kind'          => 'trial_balance',
            'scope'         => $ctx['scope'],
            'asOf'          => $asOf,
            'financialYear' => $fy,
            'detailed'      => $detailed,
            'period'        => $ctx['period'],
            'rows'          => $rows,
            'totals'        => ['debit' => $dr, 'credit' => $cr, 'difference' => $dr - $cr],
            'unclassified'  => $unc,
            'checks'        => [
                self::check('debit total = credit total', $dr === $cr, 'difference ' . self::money($dr - $cr)),
                self::check('every figure nets to zero (hidden zero rows included)', $allNet === 0, 'net ' . self::money($allNet)),
                self::check('no unclassified postings', !$unc['present'], $unc['message']),
            ],
        ];
    }

    // =================================================================
    // Drill-down
    // =================================================================

    /**
     * The next level of any node on the basis of the report it came from. $accountId is a chart account or, for 'bs' / 'tb'
     * periods, a synthetic 'bf' / 'cy' / 'div'. The rows sum exactly to the node ('sumsExactly').
     *
     * @return array{node:array, amount:int, comparative:?int, rows:array, rowsTotal:int, sumsExactly:bool, period:array}
     */
    public static function children(PDO $db, array $scope, $accountId, array $period): array
    {
        if (is_string($accountId) && in_array($accountId, ['bf', 'cy', 'div'], true)) {
            return self::syntheticChildren($db, $scope, $accountId, $period);
        }
        if (!(is_int($accountId) || (is_string($accountId) && ctype_digit($accountId)))) {
            throw new FinancialReportException('the account must be a positive integer');
        }
        $id  = (int) $accountId;
        $ctx = self::context($db, $scope, $period);
        $node = $ctx['chart']['nodes'][$id] ?? null;
        if ($node === null || !$node['classified']) {
            throw new FinancialReportException("account $id is not part of the chart for this scope");
        }
        $f    = self::figs($ctx, $id);
        $rows = self::levelRows($ctx, $id);
        $rt   = self::sum($rows);
        return ['node' => self::nodeSummary($node), 'amount' => $f['cur'], 'comparative' => $f['cmp'], 'rows' => $rows,
                'rowsTotal' => $rt, 'sumsExactly' => $rt === (int) $f['cur'], 'period' => $ctx['period'], 'comparativeError' => $ctx['comparativeError']];
    }

    private static function nodeSummary(array $n): array
    {
        return ['id' => $n['id'], 'name' => $n['name'], 'label' => $n['label'], 'path' => $n['path'], 'depth' => $n['depth'], 'root' => $n['root'],
                'posting' => $n['posting'], 'isBank' => $n['isBank'], 'active' => $n['active'], 'deleted' => $n['deleted']];
    }

    private static function syntheticChildren(PDO $db, array $scope, string $code, array $period): array
    {
        if (!in_array($period['kind'] ?? '', ['bs', 'tb'], true)) {
            throw new FinancialReportException("'$code' only exists in a balance sheet or trial balance period");
        }
        $asOf  = self::checkDate($period['asOf'] ?? null, 'as-of date');
        $start = self::yearStart($db, $asOf);
        $prev  = DateTime::createFromFormat('!Y-m-d', $start);
        $prev->modify('-1 day');
        if ($code === 'bf')     { $range = ['kind' => 'range', 'from' => null,   'to' => $prev->format('Y-m-d'), 'direction' => 'credit', 'fy' => false]; $roots = [1, 2, 5]; $label = "Prior years' surplus b/f"; }
        elseif ($code === 'cy') { $range = ['kind' => 'range', 'from' => $start, 'to' => $asOf,                  'direction' => 'credit']; $roots = [1, 2];    $label = 'Current year surplus'; }
        else                    { $range = ['kind' => 'range', 'from' => $start, 'to' => $asOf,                  'direction' => 'credit']; $roots = [5];       $label = 'Dividends'; }
        $ctx = self::context($db, $scope, $range);
        $rows = [];
        $amount = 0;
        foreach ($roots as $r) {
            $amount += (int) self::figs($ctx, $r)['cur'];
            foreach (self::levelRows($ctx, $r) as $row) { $rows[] = $row; }
        }
        $rt = self::sum($rows);
        // a trial balance shows the b/f as a net debit (the opposite sign)
        return ['node' => ['id' => $code, 'name' => $label, 'path' => $label, 'depth' => 2, 'root' => null, 'posting' => false],
                'amount' => $amount, 'comparative' => null, 'rows' => $rows, 'rowsTotal' => $rt, 'sumsExactly' => $rt === $amount,
                'period' => $ctx['period'], 'direction' => 'credit', 'comparativeError' => null];
    }

    /**
     * Journal lines of an account (and, $includeDescendants, of everything below it) for a period (decision 124), every row.
     * See linesPage(): this is its unpaged, unfiltered form.
     */
    public static function lines(PDO $db, array $scope, int $accountId, array $period, bool $includeDescendants = false): array
    {
        return self::linesPage($db, $scope, $accountId, $period, $includeDescendants, 1, 0, '');
    }

    /** Start date of the financial year covering the date (the Ledger::yearForDate rule), or null when no year covers it. */
    private static function yearStartSoft(PDO $db, string $date): ?string
    {
        $st = $db->prepare('SELECT startDate FROM tblfiscalyears WHERE ? BETWEEN startDate AND endDate ORDER BY deleted ASC, ID DESC LIMIT 1');
        $st->execute([$date]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    /**
     * Shared by lines() and linesPage(): the node, the basis (from / to / direction), the account IDs, the opening balance and
     * the financial-year rule of decision 144: income, expense and dividend accounts (types 1, 2, 5) start every financial year at
     * zero. Their opening balance is only the movement from the start of the financial year containing `from` to the day before
     * `from`, and the running balance restarts at zero at each later year start (`restart`). Balance-sheet accounts (3, 4, 6) keep
     * the cumulative opening balance.
     */
    private static function linesSetup(PDO $db, array $scope, int $accountId, array $period, bool $includeDescendants): array
    {
        $scope = self::normalizeScope($scope);
        $chart = self::chart($db, $scope);
        $node  = $chart['nodes'][$accountId] ?? null;
        if ($node === null || !$node['classified']) { throw new FinancialReportException("account $accountId is not part of the chart for this scope"); }
        $root = (int) $node['root'];

        $d    = self::describe($db, $period);
        $spec = $d['sets'][0];
        // from / to of THIS account under the basis of the report
        if ($spec['mode'] === 'bs' || $spec['mode'] === 'tb') {
            $from = in_array($root, [3, 4, 6], true) ? null : $spec['yearStart'];
            $to   = $spec['to'];
            $creditNormal = !self::isDebitNormal($root);
        } else {
            $from = $spec['from'];
            $to   = $spec['to'];
            $creditNormal = $spec['mode'] === 'range' && $spec['direction'] === 'credit' ? true
                          : ($spec['mode'] === 'range' && $spec['direction'] === 'debit' ? false : !self::isDebitNormal($root));
        }
        $plRoot  = in_array($root, [1, 2, 5], true);
        $restart = $plRoot && ($spec['mode'] !== 'range' || $spec['fy']);
        $firstFy = ($restart && $from !== null) ? self::yearStartSoft($db, $from) : null;

        $ids = [$accountId];
        if ($includeDescendants) {
            $stack = [$accountId]; $seen = [$accountId => true]; $n = 0;
            while ($stack && $n++ < 5000) {
                $cur = array_pop($stack);
                foreach ($chart['kids'][$cur] ?? [] as $k) { if (!isset($seen[$k])) { $seen[$k] = true; $ids[] = $k; $stack[] = $k; } }
            }
        }

        // opening: everything before $from; for a restarting account only from the start of that financial year
        $opening = 0;
        if ($from !== null && !($restart && $firstFy === null)) {
            $lower = $restart ? ' AND e.entryDate >= ?' : '';
            $st = $db->prepare("SELECT COALESCE(SUM(CASE WHEN l.dc = 'DEBIT' THEN l.amount ELSE -l.amount END), 0)
                                FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId
                                WHERE e.deleted = 0 AND e.congregationId IN (" . self::marks($scope) . ') AND l.accountId IN (' . self::marks($ids) . ') AND e.entryDate < ?' . $lower);
            $st->execute(array_merge($scope, $ids, [$from], $restart ? [$firstFy] : []));
            $opening = self::cents($st->fetchColumn());
        }
        return ['scope' => $scope, 'node' => $node, 'from' => $from, 'to' => $to, 'creditNormal' => $creditNormal, 'ids' => $ids, 'opening' => $opening,
                'restart' => $restart, 'firstFy' => $firstFy];
    }

    /**
     * One page of journal lines for server-side paging and search (decision 142). The running balance of every row is the TRUE
     * running balance of the account: the window function runs over the whole list of the period and the search / page only
     * filter afterwards. For income, expense and dividend accounts the balance restarts at zero at every financial-year start
     * (decision 144); each row carries `fy` (the start date of its financial year) and `prevFy` (that of the row before it in the
     * whole list) so the screen can show a "new financial year" separator.
     *
     * $search matches (substring) the date (dd-mm-yyyy and yyyy-mm-dd), entry ID, journal number, reference, narration, type,
     * account name and the debit / credit amount (with or without thousands separators). $pageSize 0 = every matching row
     * (used by the Excel export); otherwise capped at MAX_PAGE_SIZE. Ordered by entry date, entry ID, line number.
     *
     * Returns the account, the basis, 'rows' = the page, 'matching' (rows matching the search), 'filtered' (debit / credit totals
     * of the matching rows), 'page', 'pageSize', 'pages'; 'totals', 'movement', 'opening', 'closing' are those of the whole period.
     */
    public static function linesPage(PDO $db, array $scope, int $accountId, array $period, bool $includeDescendants, int $page = 1, int $pageSize = 50, string $search = ''): array
    {
        $st0 = self::linesSetup($db, $scope, $accountId, $period, $includeDescendants);
        $scope = $st0['scope']; $node = $st0['node']; $from = $st0['from']; $to = $st0['to']; $creditNormal = $st0['creditNormal']; $ids = $st0['ids'];
        $opening = $st0['opening']; $restart = $st0['restart']; $firstFy = $st0['firstFy'];
        $pageSize = $pageSize <= 0 ? 0 : min($pageSize, self::MAX_PAGE_SIZE);
        $page = max(1, $page);

        $base = "SELECT e.ID AS entryId, e.entryDate, e.journalNo, COALESCE(e.reference, '') AS reference, COALESCE(NULLIF(l.memo, ''), e.description, '') AS narr,
                        e.transactionTypeId, COALESCE(t.transactionType, '') AS typeName, e.transactionId, l.lineNumber, l.accountId, a.accountType AS accountName,
                        CASE WHEN l.dc = 'DEBIT'  THEN l.amount ELSE 0 END AS debit,
                        CASE WHEN l.dc = 'CREDIT' THEN l.amount ELSE 0 END AS credit,
                        (SELECT f.startDate FROM tblfiscalyears f WHERE e.entryDate BETWEEN f.startDate AND f.endDate ORDER BY f.deleted ASC, f.ID DESC LIMIT 1) AS fy
                 FROM journal_lines l
                 JOIN journal_entries e ON e.ID = l.journalEntryId
                 JOIN tblaccounttypes a ON a.ID = l.accountId
                 LEFT JOIN tbltransactiontypes t ON t.ID = e.transactionTypeId
                 WHERE e.deleted = 0 AND e.congregationId IN (" . self::marks($scope) . ') AND l.accountId IN (' . self::marks($ids) . ') AND e.entryDate <= ?'
              . ($from !== null ? ' AND e.entryDate >= ?' : '');
        $inner = "SELECT b.*,
                         SUM(b.debit - b.credit) OVER (" . ($restart ? 'PARTITION BY b.fy ' : '') . "ORDER BY b.entryDate, b.entryId, b.lineNumber ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS runningNet,
                         LAG(b.fy) OVER (ORDER BY b.entryDate, b.entryId, b.lineNumber) AS prevFy
                  FROM ($base) b";
        $params = array_merge($scope, $ids, [$to]);
        if ($from !== null) { $params[] = $from; }
        // the opening balance applies to the first year's partition only when the account restarts every year
        $baseOf = function ($fy) use ($restart, $opening, $firstFy) { return ($restart && $fy !== $firstFy) ? 0 : $opening; };

        $where = '';
        $wp = [];
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';
            $num = str_replace([',', ' '], '', $search);
            $conds = ["DATE_FORMAT(entryDate, '%d-%m-%Y') LIKE ?", "DATE_FORMAT(entryDate, '%Y-%m-%d') LIKE ?", 'CAST(entryId AS CHAR) LIKE ?', 'CAST(COALESCE(journalNo, \'\') AS CHAR) LIKE ?',
                      'reference LIKE ?', 'narr LIKE ?', 'typeName LIKE ?', 'accountName LIKE ?'];
            $wp = array_fill(0, count($conds), $like);
            if ($num !== '' && preg_match('/^\d+(\.\d*)?$/', $num)) {
                $nl = '%' . addcslashes($num, '\\%_') . '%';
                $conds[] = '(debit > 0 AND CAST(debit AS CHAR) LIKE ?)';
                $conds[] = '(credit > 0 AND CAST(credit AS CHAR) LIKE ?)';
                $wp[] = $nl; $wp[] = $nl;
            }
            $where = ' WHERE ' . implode(' OR ', $conds);
        }

        // matching rows and their totals
        $st = $db->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(debit), 0) AS dr, COALESCE(SUM(credit), 0) AS cr FROM ($inner) q$where");
        $st->execute(array_merge($params, $wp));
        $agg = $st->fetch(PDO::FETCH_ASSOC);
        $matching = (int) $agg['n'];

        // the period as a whole, and its last row (the closing balance)
        $st = $db->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(debit), 0) AS dr, COALESCE(SUM(credit), 0) AS cr FROM ($inner) q");
        $st->execute($params);
        $all = $st->fetch(PDO::FETCH_ASSOC);
        $dr = self::cents($all['dr']); $cr = self::cents($all['cr']);
        $st = $db->prepare("SELECT fy, runningNet FROM ($inner) q ORDER BY entryDate DESC, entryId DESC, lineNumber DESC LIMIT 1");
        $st->execute($params);
        $last = $st->fetch(PDO::FETCH_ASSOC);
        $closingNet = $last ? $baseOf($last['fy'] === null ? null : (string) $last['fy']) + self::cents($last['runningNet']) : $opening;

        $sql = "SELECT * FROM ($inner) q$where ORDER BY entryDate, entryId, lineNumber";
        $qp = array_merge($params, $wp);
        if ($pageSize > 0) { $sql .= ' LIMIT ' . (int) $pageSize . ' OFFSET ' . (int) (($page - 1) * $pageSize); }
        $st = $db->prepare($sql);
        $st->execute($qp);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $fy = $r['fy'] === null ? null : (string) $r['fy'];
            $net = $baseOf($fy) + self::cents($r['runningNet']);
            $type = (int) $r['transactionTypeId'];
            $rows[] = [
                'entryId' => (int) $r['entryId'], 'date' => (string) $r['entryDate'], 'journalNo' => $r['journalNo'] === null ? null : (int) $r['journalNo'],
                'reference' => (string) $r['reference'], 'memo' => (string) $r['narr'], 'typeId' => $type, 'typeName' => (string) $r['typeName'],
                'transactionId' => (int) $r['transactionId'], 'lineNumber' => (int) $r['lineNumber'], 'accountId' => (int) $r['accountId'], 'accountName' => (string) $r['accountName'],
                'debit' => self::cents($r['debit']), 'credit' => self::cents($r['credit']),
                'balance' => $creditNormal ? -$net : $net, 'net' => $net, 'link' => self::sourceLink($type, (int) $r['transactionId']),
                'fy' => $restart ? $fy : null, 'prevFy' => $restart && $r['prevFy'] !== null ? (string) $r['prevFy'] : null,
            ];
        }
        return [
            'account' => self::nodeSummary($node), 'from' => $from, 'to' => $to, 'direction' => $creditNormal ? 'CREDIT' : 'DEBIT',
            'includeDescendants' => $includeDescendants, 'restartsEachYear' => $restart, 'firstFy' => $firstFy,
            'opening' => $creditNormal ? -$opening : $opening, 'openingNet' => $opening,
            'rows' => $rows, 'matching' => $matching, 'search' => $search,
            'filtered' => ['debit' => self::cents($agg['dr']), 'credit' => self::cents($agg['cr'])],
            'page' => $page, 'pageSize' => $pageSize, 'pages' => $pageSize > 0 ? max(1, (int) ceil($matching / $pageSize)) : 1,
            'totals' => ['debit' => $dr, 'credit' => $cr], 'count' => (int) $all['n'],
            'movement' => $creditNormal ? $cr - $dr : $dr - $cr,
            'closing' => $creditNormal ? -$closingNet : $closingNet, 'closingNet' => $closingNet,
        ];
    }

    /**
     * Source-document numbers for journal entries, keyed by entry ID. This is metadata only: financial figures still come solely
     * from journal_entries / journal_lines. Every lookup is scoped to the entry's congregation. A type without a document number
     * returns null. Type 10 deliberately uses tblpettycash only for its receipt number (decision 126).
     * @return array<int,?string>
     */
    public static function sourceNumbers(PDO $db, array $scope, array $entryIds): array
    {
        $scope = self::normalizeScope($scope);
        $ids = [];
        foreach ($entryIds as $id) { $id = (int) $id; if ($id > 0) { $ids[$id] = $id; } }
        if (!$ids) { return []; }
        $ids = array_values($ids);
        $sql = "SELECT e.ID,
                       CASE e.transactionTypeId
                         WHEN 1  THEN (SELECT CAST(h.receiptNo AS CHAR) FROM tblcontributions_header h WHERE h.ID=e.transactionId AND h.congregationId=e.congregationId LIMIT 1)
                         WHEN 2  THEN (SELECT CAST(x.voucherNo AS CHAR) FROM tblexpenses x WHERE x.ID=e.transactionId AND x.congregationId=e.congregationId LIMIT 1)
                         WHEN 5  THEN CAST(e.journalNo AS CHAR)
                         WHEN 10 THEN (SELECT CAST(p.ReceiptNo AS CHAR) FROM tblpettycash p WHERE p.ID=e.transactionId AND p.CongregationId=e.congregationId LIMIT 1)
                         WHEN 12 THEN (SELECT CAST(r.ReqNo AS CHAR) FROM tblfundrequisition r WHERE r.ID=e.transactionId AND r.CongregationId=e.congregationId LIMIT 1)
                         WHEN 13 THEN (SELECT b.Reference FROM tblbanktransactions b WHERE b.ID=e.transactionId AND b.CongregationId=e.congregationId LIMIT 1)
                         ELSE NULL
                       END AS sourceNo
                FROM journal_entries e
                WHERE e.ID IN (" . self::marks($ids) . ") AND e.congregationId IN (" . self::marks($scope) . ')';
        $st = $db->prepare($sql);
        $st->execute(array_merge($ids, $scope));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(int) $r['ID']] = $r['sourceNo'] === null ? null : trim((string) $r['sourceNo']); }
        return $out;
    }

    /** Route (relative to URLROOT) of the source document of an entry, or null where no safe route exists. */
    public static function sourceLink(int $transactionTypeId, int $transactionId): ?string
    {
        $m = self::SOURCE_ROUTES[$transactionTypeId] ?? null;
        if ($m === null || $transactionId < 1) { return null; }
        $id = $m[1] === 'enc' ? encryptId($transactionId) : $transactionId;
        return str_replace('{id}', (string) $id, $m[0]);
    }

    // =================================================================
    // Report-safe selectors
    // =================================================================

    /**
     * Accounts a report screen may offer for a scope: visible to the scope (shared + the scope's own), by ID, labelled with the
     * path, NO active filter (inactive ones are labelled), deleted ones only when they or a descendant have journal lines in
     * scope (labelled). Roots are never offered. $opts: 'roots' => root types to keep (e.g. [1]), 'banks' => false to leave out
     * banks, 'reporting' => false to offer posting accounts only.
     * @return array<int, array{id:int,label:string,name:string,path:string,root:int,depth:int,posting:bool,active:bool,deleted:bool,isBank:bool}>
     */
    public static function accountOptions(PDO $db, array $scope, array $opts = []): array
    {
        $chart = self::chart($db, $scope);
        $roots = isset($opts['roots']) ? array_map('intval', (array) $opts['roots']) : null;
        $allowBanks = $opts['banks'] ?? true;
        $allowReporting = $opts['reporting'] ?? true;
        $visibleCong = array_merge([0], $chart['scope']);
        $out = [];
        foreach ($chart['nodes'] as $n) {
            if (!$n['classified'] || $n['depth'] < 2) { continue; }
            if (!in_array($n['congregationId'], $visibleCong, true)) { continue; }
            if ($roots !== null && !in_array($n['root'], $roots, true)) { continue; }
            if (!$allowBanks && $n['isBank']) { continue; }
            if (!$allowReporting && !$n['posting']) { continue; }
            $out[] = ['id' => $n['id'], 'label' => $n['label'], 'name' => $n['name'], 'path' => $n['path'], 'root' => $n['root'],
                      'depth' => $n['depth'], 'posting' => $n['posting'], 'active' => $n['active'], 'deleted' => $n['deleted'], 'isBank' => $n['isBank']];
        }
        usort($out, function ($a, $b) { return strnatcasecmp($a['label'], $b['label']) ?: $a['id'] <=> $b['id']; });
        return $out;
    }

    /** True when the ID is in the account selector of the same scope and options (server-side validation of a posted ID). */
    public static function accountAllowed(PDO $db, array $scope, int $accountId, array $opts = []): bool
    {
        foreach (self::accountOptions($db, $scope, $opts) as $a) { if ($a['id'] === $accountId) { return true; } }
        return false;
    }

    /**
     * The scope's own banks for a report screen: non-deleted, and active OR with journal / bank-posting history (an inactive one
     * is labelled). Posting screens keep their active-only lists.
     * @return array<int, array{id:int,label:string,name:string,accountNo:string,active:bool,congregationId:int}>
     */
    public static function bankOptions(PDO $db, array $scope): array
    {
        $scope = self::normalizeScope($scope);
        $st = $db->prepare('SELECT a.ID, a.accountType, a.accountNo, a.active, a.congregationId FROM tblaccounttypes a
                            WHERE a.isBank = 1 AND a.deleted = 0 AND a.congregationId IN (' . self::marks($scope) . ')
                              AND (a.active = 1
                                   OR EXISTS (SELECT 1 FROM journal_lines l JOIN journal_entries e ON e.ID = l.journalEntryId
                                              WHERE l.accountId = a.ID AND e.deleted = 0 AND e.congregationId = a.congregationId)
                                   OR EXISTS (SELECT 1 FROM tblbankpostings b WHERE b.bankId = a.ID AND b.deleted = 0))
                            ORDER BY a.accountType, a.ID');
        $st->execute($scope);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $no = trim((string) ($r['accountNo'] ?? ''));
            $label = strtoupper((string) $r['accountType']) . ($no !== '' ? '-' . strtoupper($no) : '') . ((int) $r['active'] !== 1 ? ' (inactive)' : '');
            $out[] = ['id' => (int) $r['ID'], 'label' => $label, 'name' => (string) $r['accountType'], 'accountNo' => $no,
                      'active' => (int) $r['active'] === 1, 'congregationId' => (int) $r['congregationId']];
        }
        return $out;
    }

    public static function bankAllowed(PDO $db, array $scope, int $bankId): bool
    {
        foreach (self::bankOptions($db, $scope) as $b) { if ($b['id'] === $bankId) { return true; } }
        return false;
    }

    /**
     * Congregations a parish report may cover: every non-deleted congregation, plus a deleted one that has journal history
     * (decision 127). IDs only.
     * @return int[]
     */
    public static function parishScope(PDO $db): array
    {
        $ids = $db->query('SELECT c.ID FROM tblcongregation c
                           WHERE c.deleted = 0 OR EXISTS (SELECT 1 FROM journal_entries e WHERE e.congregationId = c.ID AND e.deleted = 0)
                           ORDER BY c.ID')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $ids);
    }
}

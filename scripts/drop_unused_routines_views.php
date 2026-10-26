<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
/**
 * Drop the stored routines and views nothing uses any more (task 9.8, decisions 128 and 160; runbook step 6.2).
 *
 *   php scripts/drop_unused_routines_views.php                                   dry run (the default): prints the plan, changes nothing
 *   php scripts/drop_unused_routines_views.php --dry-run
 *   php scripts/drop_unused_routines_views.php --commit --backup <file.sql>      writes the backup, then drops
 *   (--config=PATH for a config.php elsewhere)
 *
 * The lists below are FIXED and reviewed: no wildcard, no pattern. They are the same as the table "Routines and views dropped at
 * cutover (step 6.2)" in docs/ledger_inventory.md (test_9_8_cleanup.php compares them). Dev is a replica of production
 * (decision 160), so the list built on dev is the production list; step 1.5 (schema diff) confirms it before the cutover.
 *
 * The run
 *   1. notes every listed object that does not exist (not an error: a second run finds nothing to drop);
 *   2. STOPS if any object that is NOT being dropped (a routine, view, trigger or event) still refers to a listed one;
 *   3. lists every routine / view in the database that is in neither list ("unknown") and never touches it;
 *   4. with --commit: writes SHOW CREATE of every object it will drop to the backup file (DELIMITER-wrapped, replayable with the
 *      mysql client: functions, views, procedures), drops views first, then procedures, then functions, and verifies that none of the
 *      listed objects remains.
 * Rollback: replay the backup file with  mysql -u <user> -p <database> < backup.sql  (or restore the cutover backup 0.1).
 */

// ----------------------------------------------------------------------------------------------------------------------------
// THE DROP LIST (name => why). Keep in step with docs/ledger_inventory.md.
// ----------------------------------------------------------------------------------------------------------------------------
const DROP_VIEWS = [
    'vw_deposits', 'vw_elders', 'vw_expenses', 'vw_expensevoucher', 'vw_getcontribution', 'vw_getcontributions', 'vw_getexpenses',
    'vw_loadpledgebalances', 'vw_members_minimal', 'vw_supplier_payments', 'vw_supplierpayments',
    'vwcontributions', 'vwglaccounts', 'vwloadgroups', 'vwmemberreport', 'vwmembers',
];
const DROP_PROCEDURES = [
    // no caller since before task 9
    'pledgesWithBalances', 'spGetInvoices', 'spGetOfficials', 'sp_getPledge', 'sp_role_pairing', 'sp_get_rights',
    // read tblledger; replaced by FinancialReport (9.2-9.5)
    'sp_BalanceSheet_AccountDrilldown', 'sp_BalanceSheet_Assets', 'sp_BalanceSheet_LiabilitiesEquity', 'sp_balanceSheet_liablityequity',
    'sp_balanceSheet_liablityequity_parish', 'sp_balancesheet_assets_parish', 'sp_get_account_statement', 'sp_get_account_statement_parent',
    'sp_get_assets', 'sp_get_assets_v1', 'sp_get_liabilities_equity_v2', 'sp_get_liabilities_equity_v3', 'sp_get_trial_balance_updated',
    'sp_trialbalance', 'sp_trialbalance_detailed_v2', 'sp_trialbalance_parish', 'sp_trialbalance_summary', 'sp_trialbalance_summary_v2',
    // replaced by application SQL (9.6 petty cash, 9.7 contribution / expense reports, 9.8 sub account reports)
    'sp_pettycashutilization', 'sp_contribitions_report_all', 'sp_expenses_all',
    'sp_get_subaccount_statement', 'sp_get_subaccount_statement_with_expense_district', 'sp_get_subaccount_statement_with_expense_group',
    'sp_get_subaccounts_balances', 'sp_get_subaccounts_balances_breakdown_district',
    // only a test used it
    'sp_mmf_statement',
    // helpers left behind when the guard of bankpostings_decimal.sql (4.19) / money_columns_decimal.sql (4.20) stopped a run
    'zz_bankpostings_decimal', 'zz_money_columns_decimal',
];
const DROP_FUNCTIONS = [
    // read tblledger; replaced by FinancialReport (9.2-9.5)
    'gettotalcredits', 'gettotalcredits_bs', 'gettotalcredits_bs_parish', 'gettotalcredits_parent', 'gettotalcredits_parish',
    'gettotaldebits', 'gettotaldebits_bs', 'gettotaldebits_bs_parish', 'gettotaldebits_parent', 'gettotaldebits_parish', 'get_year_start_date',
    // no caller since before task 9 / only dropped routines call them
    'getcongregationname', 'getcurrentcongregation', 'getcurrentdistrict', 'getdistrictname', 'getpaymenttotalbysupplier',
    'getmmfopeningbal', 'getmmfopeningbal_district', 'getopeningbal',
    // only a test used it (the working equivalent is getinvoice_paid)
    'getAmountPaid',
    // sub account reports now use application SQL (9.8)
    'get_subaccount_balance_by_date', 'get_subaccount_balance_by_date_district', 'get_subaccount_balance_by_date_group',
    'get_subaccount_expense_op_district', 'get_subaccount_expense_op_group', 'get_subaccount_expense_total',
    'get_subaccount_expense_total_district', 'get_subaccount_expense_total_group',
    'get_subaccount_opening_balance_district', 'get_subaccount_opening_balance_group',
];
// ----------------------------------------------------------------------------------------------------------------------------
// THE REMAINING OBJECTS (used by the application, or kept for a runbook guard / test: see the document). Anything in the database that
// is in neither list is reported as unknown and left alone.
// ----------------------------------------------------------------------------------------------------------------------------
const KEEP_VIEWS = [
    'vw_banktransactions', 'vw_byage', 'vw_cashreceipts', 'vw_contributorspertxn', 'vw_expensevoucher_2', 'vw_family',
    'vw_getexpenses_2', 'vw_groupofficials', 'vw_group_requisitions', 'vw_invoices', 'vw_pledgewithbalances',
    'vw_requisition_approvals', 'vw_supplierpayments2', 'vw_suppliers', 'vw_supplier_with_bals', 'vw_users', 'vw_user_rights',
];
const KEEP_PROCEDURES = [
    'getgroupstatement', 'getPledge', 'spGetInvoices_suppliers', 'sp_church_budgetvexpense', 'sp_getcustomerpayments',
    'sp_getinvoicepaymentdetails', 'sp_getinvoice_due', 'sp_getinvoice_fullypaid', 'sp_getinvoice_wih_balances',
    'sp_getinvoice_withbalances', 'sp_getsupplierpayments', 'sp_get_payments_all', 'sp_get_payments_by_invoiceno',
    'sp_get_payments_by_supplier', 'sp_get_plans', 'sp_get_pledges', 'sp_get_pledge_payments', 'sp_group_budgetvexpense',
    'sp_menu_pairing',
];
const KEEP_FUNCTIONS = [
    'brand_level', 'fn_checkreferencedaccount', 'fn_get_supplier_balance', 'getamountpaid_pledge',
    'getbudgetedamount', 'getbudgetedamount_lcc', 'getChairmanId', 'getcontributor', 'getcontributorid', 'getexpensedamount',
    'getexpensetotal_group', 'getexpensetotal_lcc', 'getgroupexpensedamount', 'getinvoicebalance', 'getinvoicebalance_supplier',
    'getinvoicebalance_supplier_bydate', 'getinvoice_paid', 'getinvoice_paid_supplier', 'getmmfopeningbalbydate',
    'getmmfopeningbalbydate_district', 'getmonthexpense', 'getmonthexpense_group', 'getrequisitionbalance', 'getTotalPledge',
    'getYearID', 'getyearidbydate',
];

function usage(): void { fwrite(STDERR, "Usage: php scripts/drop_unused_routines_views.php [--dry-run | --commit --backup <file.sql>] [--config=PATH]\n"); }

/** a definition without comments and string literals' case, for a plain name search */
function stripComments(string $sql): string
{
    $sql = preg_replace('~/\*.*?\*/~s', ' ', $sql) ?? $sql;
    $sql = preg_replace('~(--|#)[^\r\n]*~', ' ', $sql) ?? $sql;
    return $sql;
}

function mentions(string $definition, string $name): bool
{
    return (bool) preg_match('/(?<![A-Za-z0-9_$])`?' . preg_quote($name, '/') . '`?(?![A-Za-z0-9_$])/i', stripComments($definition));
}

/** @return array{ok:bool, exit:int} */
function run(array $argv, ?PDO $injected = null): array
{
    $commit = false; $backup = null; $config = null;
    for ($i = 1; $i < count($argv); $i++) {
        $a = $argv[$i];
        if ($a === '--commit') { $commit = true; }
        elseif ($a === '--dry-run') { $commit = false; }
        elseif ($a === '--backup' && isset($argv[$i + 1])) { $backup = $argv[++$i]; }
        elseif (strpos($a, '--config=') === 0) { $config = substr($a, 9); }
        else { usage(); return ['ok' => false, 'exit' => 2]; }
    }
    // the script changes directory to app/ to load the configuration: resolve the paths against where it was started
    $abs = function ($p) { return preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/])~', $p) ? $p : getcwd() . DIRECTORY_SEPARATOR . $p; };
    if ($backup !== null && trim($backup) !== '') { $backup = $abs($backup); }
    if ($config !== null) { $config = $abs($config); }
    if ($commit && ($backup === null || trim($backup) === '')) { fwrite(STDERR, "--commit needs --backup <file.sql>\n"); return ['ok' => false, 'exit' => 2]; }
    if ($commit) {
        $dir = dirname($backup);
        if (!is_dir($dir) || !is_writable($dir)) { fwrite(STDERR, "The backup directory does not exist or is not writable: $dir\n"); return ['ok' => false, 'exit' => 2]; }
        if (file_exists($backup)) { fwrite(STDERR, "The backup file already exists (it is never overwritten): $backup\n"); return ['ok' => false, 'exit' => 2]; }
    }

    if ($injected === null) {
        chdir(__DIR__ . '/../app');
        require_once $config ?? 'config/config.php';
        require_once 'libraries/Database.php';
        $db = (new Database)->pdo();
    } else {
        $db = $injected;
    }
    $schema = $db->query('SELECT DATABASE()')->fetchColumn();

    // what exists
    $have = ['view' => [], 'procedure' => [], 'function' => []];
    foreach ($db->query("SELECT TABLE_NAME n, VIEW_DEFINITION d FROM information_schema.VIEWS WHERE TABLE_SCHEMA = " . $db->quote($schema)) as $r) { $have['view'][$r['n']] = (string) $r['d']; }
    foreach ($db->query("SELECT ROUTINE_TYPE t, ROUTINE_NAME n, ROUTINE_DEFINITION d FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = " . $db->quote($schema)) as $r) { $have[strtolower($r['t'])][$r['n']] = (string) $r['d']; }
    $trig = []; foreach ($db->query("SELECT TRIGGER_NAME n, ACTION_STATEMENT d FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = " . $db->quote($schema)) as $r) { $trig['trigger ' . $r['n']] = (string) $r['d']; }
    foreach ($db->query("SELECT EVENT_NAME n, EVENT_DEFINITION d FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . $db->quote($schema)) as $r) { $trig['event ' . $r['n']] = (string) $r['d']; }

    $drop = ['view' => DROP_VIEWS, 'procedure' => DROP_PROCEDURES, 'function' => DROP_FUNCTIONS];
    $keep = ['view' => KEEP_VIEWS, 'procedure' => KEEP_PROCEDURES, 'function' => KEEP_FUNCTIONS];
    foreach ($drop as $type => $names) {
        if (count($names) !== count(array_unique($names))) { fwrite(STDERR, "The $type drop list has a duplicate.\n"); return ['ok' => false, 'exit' => 2]; }
        if (array_intersect($names, $keep[$type])) { fwrite(STDERR, "A $type is in both the drop and the keep list: " . implode(', ', array_intersect($names, $keep[$type])) . "\n"); return ['ok' => false, 'exit' => 2]; }
    }
    // names are case-insensitive in MariaDB for routines / views on this platform: compare lower case
    $lower = function (array $a) { return array_map('strtolower', $a); };
    $present = []; $missing = [];
    foreach ($drop as $type => $names) {
        $haveLower = array_change_key_case($have[$type], CASE_LOWER);
        foreach ($names as $n) { if (isset($haveLower[strtolower($n)])) { $present[] = [$type, $n]; } else { $missing[] = [$type, $n]; } }
    }
    printf("Schema %s: %d routines/views to drop are present, %d are already gone.\n", $schema, count($present), count($missing));
    foreach ($present as [$t, $n]) { printf("  DROP %-9s %s\n", $t, $n); }
    foreach ($missing as [$t, $n]) { printf("  note: %-9s %s does not exist (nothing to drop)\n", $t, $n); }

    // unknown objects
    $unknown = [];
    foreach ($have as $type => $objs) {
        $known = array_merge($lower($drop[$type]), $lower($keep[$type]));
        foreach (array_keys($objs) as $n) { if (!in_array(strtolower($n), $known, true)) { $unknown[] = [$type, $n]; } }
    }
    if ($unknown) {
        echo "UNKNOWN objects (in neither list, never touched; compare with production in runbook 1.5):\n";
        foreach ($unknown as [$t, $n]) { echo "  $t $n\n"; }
    } else {
        echo "No unknown routines or views.\n";
    }

    // still referenced by something that stays?
    $going = []; foreach ($present as [$t, $n]) { $going[strtolower($t . ' ' . $n)] = true; }
    $refs = [];
    $holders = [];
    foreach ($have as $type => $objs) { foreach ($objs as $n => $def) { if (!isset($going[strtolower($type . ' ' . $n)])) { $holders[$type . ' ' . $n] = $def; } } }
    foreach ($trig as $k => $def) { $holders[$k] = $def; }
    foreach ($present as [$t, $n]) {
        foreach ($holders as $holder => $def) { if (mentions($def, $n)) { $refs[] = "$t $n is used by $holder"; } }
    }
    if ($refs) {
        echo "STOP: a listed object is still referenced by an object that stays:\n";
        foreach ($refs as $r) { echo "  $r\n"; }
        return ['ok' => false, 'exit' => 1];
    }
    echo "No remaining routine, view, trigger or event refers to a listed object.\n";

    if (!$commit) {
        echo count($present) ? "Dry run: nothing was changed. Re-run with --commit --backup <file.sql> after reading this list.\n" : "Dry run: nothing to drop.\n";
        return ['ok' => true, 'exit' => 0];
    }
    if (!$present) { echo "Nothing to drop (no backup written).\n"; return ['ok' => true, 'exit' => 0]; }

    // backup: functions, views, procedures (the order they are replayed in)
    $sqlMode = $db->query('SELECT @@sql_mode')->fetchColumn();
    $out = "-- Backup of the routines and views dropped by scripts/drop_unused_routines_views.php (task 9.8)\n-- schema " . $schema . ', ' . date('Y-m-d H:i:s') . "\n-- Replay: mysql -u <user> -p " . $schema . " < this file\n\n";
    $show = function (string $type, string $name) use ($db) {
        $row = $db->query('SHOW CREATE ' . strtoupper($type) . ' `' . str_replace('`', '', $name) . '`')->fetch(PDO::FETCH_NUM);
        return $row;
    };
    foreach (['function', 'view', 'procedure'] as $type) {
        foreach ($present as [$t, $n]) {
            if ($t !== $type) { continue; }
            $row = $show($t, $n);
            if (!$row) { fwrite(STDERR, "Could not read the definition of $t $n.\n"); return ['ok' => false, 'exit' => 1]; }
            if ($t === 'view') {
                $out .= '-- view ' . $n . "\nDROP VIEW IF EXISTS `" . $n . "`;\n" . $row[1] . ";\n\n";
            } else {
                $mode = $row[1];
                $out .= '-- ' . $t . ' ' . $n . "\nDELIMITER $$\nDROP " . strtoupper($t) . ' IF EXISTS `' . $n . "`$$\nSET @bk_sql_mode = @@sql_mode$$\nSET sql_mode = " . $db->quote($mode) . "$$\n" . $row[2] . "$$\nSET sql_mode = @bk_sql_mode$$\nDELIMITER ;\n\n";
            }
        }
    }
    if (file_put_contents($backup, $out) === false) { fwrite(STDERR, "Could not write the backup.\n"); return ['ok' => false, 'exit' => 1]; }
    printf("Backup written: %s (%d bytes, %d objects).\n", $backup, strlen($out), count($present));

    foreach (['view' => 'VIEW', 'procedure' => 'PROCEDURE', 'function' => 'FUNCTION'] as $type => $kw) {
        foreach ($present as [$t, $n]) { if ($t === $type) { $db->exec("DROP $kw `" . str_replace('`', '', $n) . '`'); echo "  dropped $t $n\n"; } }
    }
    // verification
    $left = [];
    foreach ($present as [$t, $n]) {
        $c = $t === 'view'
            ? $db->query('SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ' . $db->quote($schema) . ' AND TABLE_NAME = ' . $db->quote($n))->fetchColumn()
            : $db->query('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ' . $db->quote($schema) . ' AND ROUTINE_NAME = ' . $db->quote($n))->fetchColumn();
        if ((int) $c > 0) { $left[] = "$t $n"; }
    }
    if ($left) { echo 'VERIFICATION FAILED, still present: ' . implode(', ', $left) . "\n"; return ['ok' => false, 'exit' => 1]; }
    echo "Verified: none of the " . count($present) . " dropped objects remains.\n";
    return ['ok' => true, 'exit' => 0];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    try {
        $r = run($argv);
        exit($r['exit']);
    } catch (Throwable $e) {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

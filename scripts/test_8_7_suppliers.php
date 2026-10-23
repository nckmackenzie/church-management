<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
// 8.7 supplier invoices (6), supplier payments (7) and supplier opening balances (15) through the models, congregation 11.
// Self-cleaning: every row above the starting IDs is removed, the number sequences, the fiscal year flags and the F15 account
// are restored. Needs the 8.7 schema (scripts/sql/supplier_accounts_schema.sql) and F15 on the dev DB.
//   E:\xampp\php\php.exe scripts\test_8_7_suppliers.php
ini_set('session.save_path', __DIR__ . '/logs');
chdir(__DIR__ . '/../app');
require 'boostrap.php';
foreach (['Supplierinvoice', 'Payment', 'Supplier', 'Product', 'Invoicereport'] as $m) { require_once "models/$m.php"; }

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11; $_SESSION['userId'] = 64; $_SESSION['userType'] = 1; $_SESSION['isParish'] = 0; $_SESSION['zero'] = 0; $_SESSION['one'] = 1;

$tables = ['tblsuppliers' => 'ID', 'tblinvoice_header_suppliers' => 'ID', 'tblinvoice_details_suppliers' => 'ID', 'tblinvoice_payments_suppliers' => 'ID',
           'tblproducts' => 'ID', 'journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tbllogs' => 'ID', 'tblaccounttypes' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT COALESCE(MAX($k),0) FROM $t")->fetchColumn(); }
$years = $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC);
$seqs = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$f15 = $pdo->query("SELECT * FROM tblaccounttypes WHERE LOWER(TRIM(accountType))='accounts payable' AND parentId=23 AND deleted=0")->fetchAll(PDO::FETCH_ASSOC);

$pass = 0; $fail = 0;
function ok($n, $v, $x = '') { global $pass, $fail; if ($v) { $pass++; echo "PASS $n\n"; } else { $fail++; echo "FAIL $n $x\n"; } }
function rows($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function one($sql, $p = []) { $r = rows($sql, $p); return $r ? $r[0] : null; }
function cnt($sql, $p = []) { $r = one($sql, $p); return $r ? (int)array_values($r)[0] : 0; }
function lines($type, $txn) {
    $e = one('SELECT ID FROM journal_entries WHERE transactionTypeId=? AND transactionId=? AND congregationId=? AND deleted=0', [$type, $txn, $_SESSION['congId']]);
    if (!$e) { return null; }
    return array_map(function($l) { return $l['accountId'] . '|' . $l['dc'] . '|' . $l['amount']; },
        rows('SELECT accountId,dc,amount FROM journal_lines WHERE journalEntryId=? ORDER BY lineNumber', [$e['ID']]));
}
function errs($r) { return implode(' / ', $r['errors'] ?? []); }
function has($r, $needle) { return !$r['success'] && stripos(errs($r), $needle) !== false; }
function newAccount($name, $parent, $posting = 1, $active = 1, $cong = 11, $deleted = 0, $type = 4) {
    global $pdo;
    $pdo->prepare('INSERT INTO tblaccounttypes(accountType,accountTypeId,parentId,isSubCategory,isBank,active,congregationId,deleted) VALUES(?,?,?,?,0,?,?,?)')
        ->execute([$name, $type, $parent, $posting, $active, $cong, $deleted]);
    return (int)$pdo->lastInsertId();
}
$no = 700000;
function nextno() { global $no; return (string)(++$no); }
function dbstate() {
    // everything the supplier screens can change, as one string: used to prove a refusal changed nothing
    $t = [];
    global $max;
    // the big tables only from the rows this run created (a refusal must not touch those either)
    foreach (['tblinvoice_header_suppliers' => 0, 'tblinvoice_details_suppliers' => 0, 'tblinvoice_payments_suppliers' => 0, 'tblsuppliers' => 0, 'journal_entries' => 1, 'journal_lines' => 1, 'tblbankpostings' => 1, 'tblledger' => 1] as $tb => $from) {
        $t[] = $tb . ':' . md5(json_encode(rows("SELECT * FROM $tb" . ($from ? ' WHERE ID > ' . $max[$tb] : '') . ' ORDER BY 1')));
    }
    return implode(',', $t);
}

$today = date('Y-m-d');
$closedDate = '2024-05-01';   // financial year 2023-2024, closed on dev
$noYear = '1990-01-01';
$model = new Supplierinvoice; $pay = new Payment; $sup = new Supplier;

// ---------------------------------------------------------------- fixtures
$pdo->exec("INSERT INTO tblsuppliers(supplierName,congregationId) VALUES('zz supplier a',11),('zz supplier b',11)");
$sa = (int)$pdo->lastInsertId(); $sb = $sa + 1;
$pdo->exec("INSERT INTO tblsuppliers(supplierName,congregationId) VALUES('zz supplier foreign',2)"); $sForeign = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO tblsuppliers(supplierName,congregationId,deleted) VALUES('zz supplier deleted',11,1)"); $sDeleted = (int)$pdo->lastInsertId();

$pa = newAccount('zz payable a', 23);
$pParent = newAccount('zz payable parent', 23, 0);
$pDeep = newAccount('zz payable deep', $pParent);
$pInactive = newAccount('zz payable inactive', 23, 1, 0);
$pForeign = newAccount('zz payable foreign', 23, 1, 1, 2);
$pDeleted = newAccount('zz payable deleted', 23, 1, 1, 11, 1);
$pOther = 52;   // 'group efforts': a posting liability account NOT under 23
$expA = 51; $expB = 54; $assetA = 167; $incomeA = 90; $reportingExp = 26;
$expInactive = newAccount('zz exp inactive', 26, 1, 0, 11, 0, 2);
$expForeign = newAccount('zz exp foreign', 26, 1, 1, 2, 0, 2);
$expDeleted = newAccount('zz exp deleted', 26, 1, 1, 11, 1, 2);

function product($name, $account, $rate = '1000.00', $cong = 11, $deleted = 0) {
    global $pdo;
    $pdo->prepare('INSERT INTO tblproducts(productName,rate,accountId,congregationId,deleted) VALUES(?,?,?,?,?)')->execute([$name, $rate, $account, $cong, $deleted]);
    return (int)$pdo->lastInsertId();
}
$p1 = product('zz prod expense', $expA); $p2 = product('zz prod asset', $assetA, '500.50'); $p3 = product('zz prod expense b', $expB);
$pInc = product('zz prod income', $incomeA); $pRep = product('zz prod reporting', $reportingExp); $pBank = product('zz prod bank', 105);
$pInact = product('zz prod inactive acct', $expInactive); $pDel = product('zz prod deleted', $expA, '1.00', 11, 1);
$pForeignProd = product('zz prod foreign cong', $expA, '1.00', 2); $pForeignAcct = product('zz prod foreign acct', $expForeign); $pDelAcct = product('zz prod deleted acct', $expDeleted);

function base($o = []) {
    global $sa, $pa, $p1, $today;
    return array_merge(['id' => '', 'isedit' => false, 'supplier' => $sa, 'idate' => $today, 'ddate' => $today, 'vattype' => 1, 'vat' => '',
                        'invoiceno' => nextno(), 'payable' => $pa, 'table' => [['pid' => $p1, 'qty' => '2', 'rate' => '1000.00']]], $o);
}
function header_row($id) { return one('SELECT * FROM tblinvoice_header_suppliers WHERE ID=?', [$id]); }

try {
    // sanity: the environment these tests assume
    ok('env: financial year 2023-2024 is closed and 2026-2027 open', cnt("SELECT COUNT(*) FROM tblfiscalyears WHERE closed=1 AND deleted=0 AND ? BETWEEN startDate AND endDate", [$closedDate]) === 1 && Ledger::dateProblem($pdo, $today) === null);

    // ------------------------------------------------------------ F15 resolver and the payable list
    $f15id = Ledger::accountsPayableId($pdo);
    $row = one("SELECT * FROM tblaccounttypes WHERE LOWER(TRIM(accountType))='accounts payable' AND parentId=23 AND deleted=0");
    ok('F15: resolver finds the shared posting account named accounts payable under 23 without a hard-coded ID', $f15id !== null && (int)$row['ID'] === $f15id && (int)$row['congregationId'] === 0 && (int)$row['isSubCategory'] === 1 && (int)$row['active'] === 1);
    $dup = newAccount('accounts payable', 23, 1, 1, 0);
    ok('F15: two live rows named accounts payable -> no default', Ledger::accountsPayableId($pdo) === null);
    $pdo->prepare('UPDATE tblaccounttypes SET deleted=1 WHERE ID=?')->execute([$dup]);
    ok('F15: a deleted duplicate is ignored', Ledger::accountsPayableId($pdo) === $f15id);
    $pdo->prepare('UPDATE tblaccounttypes SET active=0 WHERE ID=?')->execute([$f15id]);
    ok('F15: inactive -> no default', Ledger::accountsPayableId($pdo) === null);
    $pdo->prepare('UPDATE tblaccounttypes SET active=1, congregationId=11 WHERE ID=?')->execute([$f15id]);
    ok('F15: congregation-owned (not shared) -> no default', Ledger::accountsPayableId($pdo) === null);
    $pdo->prepare('UPDATE tblaccounttypes SET congregationId=0, accountType=? WHERE ID=?')->execute(['accounts payables', $f15id]);
    ok('F15: renamed -> no default', Ledger::accountsPayableId($pdo) === null);
    $pdo->prepare('UPDATE tblaccounttypes SET accountType=? WHERE ID=?')->execute(['accounts payable', $f15id]);
    ok('F15: restored -> found again', Ledger::accountsPayableId($pdo) === $f15id);
    ok('F15: the form model gives the same default', $model->getDefaultPayable() === $f15id && $sup->getDefaultPayable() === $f15id);

    $list = array_column(Ledger::listPayableAccounts($pdo, 11), 'ID');
    ok('payable list: F15 account, own account and a deeper posting account are offered', in_array($f15id, $list) && in_array($pa, $list) && in_array($pDeep, $list));
    ok('payable list: reporting parent, 23 itself, inactive, other congregation, deleted, a liability outside 23 and a bank are not', !array_intersect([$pParent, 23, $pInactive, $pForeign, $pDeleted, $pOther, 105], $list));
    $list2 = array_column(Ledger::listPayableAccounts($pdo, 2), 'ID');
    ok('payable list is per congregation (2 sees its own account and shared ones, not 11\'s)', in_array($pForeign, $list2) && !in_array($pa, $list2) && in_array($f15id, $list2));
    foreach ([[$pa, true], [$pDeep, true], [$pParent, false], [23, false], [$pInactive, false], [$pForeign, false], [$pDeleted, false], [$pOther, false], [105, false], [0, false], [999999, false]] as [$acc, $want]) {
        $ok = true; try { Ledger::assertPayableAccount($pdo, 11, $acc); } catch (LedgerException $e) { $ok = false; }
        ok("assertPayableAccount($acc) " . ($want ? 'accepted' : 'refused') . ' (same rule as the list)', $ok === $want && ($ok === in_array($acc, $list)));
    }
    ok('an inactive stored payable can be kept on an edit', Ledger::payableProblem($pdo, 11, $pInactive, true) === null);

    // ------------------------------------------------------------ line accounts and products
    $lineIds = array_column($model->lineAccounts(), 'ID');
    ok('line accounts: expense and non-bank asset posting accounts are offered', in_array($expA, $lineIds) && in_array($assetA, $lineIds));
    ok('line accounts: income, reporting, bank, inactive, foreign and deleted are not', !array_intersect([$incomeA, $reportingExp, 105, $expInactive, $expForeign, $expDeleted, 23], $lineIds));
    $prodIds = array_map(function($p) { return (int)$p->ID; }, $model->getProducts());
    ok('product list: valid products only (no retired, no foreign, no bad account)', in_array($p1, $prodIds) && in_array($p2, $prodIds) && !array_intersect([$pInc, $pRep, $pBank, $pInact, $pDel, $pForeignProd, $pForeignAcct, $pDelAcct, 1, 2], $prodIds));
    ok('products 1 and 2 are retired on dev', cnt('SELECT COUNT(*) FROM tblproducts WHERE ID IN (1,2) AND deleted=1') === 2);
    $d = $model->getProductDetails($p1);
    ok('product details: rate, account and no problem', $d && (int)$d['accountId'] === $expA && $d['problem'] === null && (float)$d['rate'] === 1000.0);
    ok('product details: bad account reported', $model->getProductDetails($pInc)['problem'] !== null && $model->getProductDetails($pBank)['problem'] !== null);
    ok('product details: foreign, deleted, retired 1 and 2, junk -> null', $model->getProductDetails($pForeignProd) === null && $model->getProductDetails($pDel) === null && $model->getProductDetails(1) === null && $model->getProductDetails(2) === null && $model->getProductDetails('x') === null && $model->getProductDetails('1 OR 1=1') === null);

    // modal: SaveProduct
    $n0 = cnt('SELECT COUNT(*) FROM tblproducts');
    $r = $model->SaveProduct(['productname' => 'zz new product', 'description' => 'd', 'rate' => '12.50', 'account' => $expA]);
    $newP = (int)($r['id'] ?? 0);
    ok('SaveProduct returns the ID of the row it inserted (no lastInsertId race)', $r['success'] && $newP > 0 && one('SELECT productName,accountId,congregationId FROM tblproducts WHERE ID=?', [$newP])['productName'] === 'zz new product');
    foreach ([['income account', ['account' => $incomeA]], ['bank account', ['account' => 105]], ['reporting account', ['account' => $reportingExp]], ['foreign account', ['account' => $expForeign]],
              ['inactive account', ['account' => $expInactive]], ['no account', ['account' => '']], ['text account', ['account' => 'abc']], ['duplicate name', ['productname' => 'zz new product']],
              ['empty name', ['productname' => '']], ['zero rate', ['rate' => '0']], ['3 decimals', ['rate' => '1.005']], ['negative rate', ['rate' => '-1']], ['array rate', ['rate' => ['1']]], ['huge rate', ['rate' => '99999999999999']],
              ['long name', ['productname' => str_repeat('x', 256)]]] as [$label, $over]) {
        $c = cnt('SELECT COUNT(*) FROM tblproducts');
        $r = $model->SaveProduct(array_merge(['productname' => 'zz p2' . $label, 'description' => '', 'rate' => '5', 'account' => $expA], $over));
        ok("SaveProduct refuses $label", !$r['success'] && cnt('SELECT COUNT(*) FROM tblproducts') === $c, errs($r));
    }

    // ------------------------------------------------------------ create: every posting shape
    $r = $model->save(base(['table' => [['pid' => $p1, 'qty' => '2', 'rate' => '1000.00'], ['pid' => $p2, 'qty' => '1', 'rate' => '500.50']]]));
    $i1 = (int)($r['id'] ?? 0);
    ok('create (no VAT, two lines) succeeds', $r['success'] && $i1 > 0, errs($r));
    $h = header_row($i1);
    ok('create: header stores the chosen crediting account, exact money and open status', (int)$h['PayableAccountId'] === $pa && $h['inclusiveVat'] === '2500.50' && $h['exclusiveVat'] === '2500.50' && $h['vat'] === '0.00' && (int)$h['status'] === 0 && (int)$h['congregationId'] === 11 && (int)$h['supplierId'] === $sa && (int)$h['deleted'] === 0);
    $dl = rows('SELECT productId,AccountId,qty,rate,gross FROM tblinvoice_details_suppliers WHERE header_id=? ORDER BY ID', [$i1]);
    ok('create: each line snapshots the product\'s account', count($dl) === 2 && (int)$dl[0]['AccountId'] === $expA && (int)$dl[1]['AccountId'] === $assetA && $dl[0]['gross'] === '2000.00' && $dl[1]['gross'] === '500.50');
    ok('create: type 6 entry keyed on the header: Dr each line account / Cr the payable, exact amounts', lines(6, $i1) === ["$expA|DEBIT|2000.00", "$assetA|DEBIT|500.50", "$pa|CREDIT|2500.50"], json_encode(lines(6, $i1)));
    $e = one('SELECT * FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$i1]);
    ok('create: entry date, congregation and reference', $e['entryDate'] === $today && (int)$e['congregationId'] === 11 && $e['reference'] === 'supplier invoice-' . $h['invoiceNo']);
    ok('create: no bank row for an invoice', cnt('SELECT COUNT(*) FROM tblbankpostings WHERE transactionType=6 AND transactionId=?', [$i1]) === 0);
    ok('create: legacy mirror has the 3 rows and balances', cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=6 AND transactionId=? AND deleted=0', [$i1]) === 3 && cnt('SELECT SUM(debit)=SUM(credit) FROM tblledger WHERE transactionType=6 AND transactionId=? AND deleted=0', [$i1]) === 1);

    // VAT: inclusive and exclusive, balanced to the cent
    $r = $model->save(base(['vattype' => 2, 'vat' => '16', 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1160.00']]]));
    $i2 = (int)$r['id']; $h = header_row($i2);
    ok('VAT inclusive: exclusive 1000.00, VAT 160.00, total 1160.00, debit and credit 1160.00', $r['success'] && $h['exclusiveVat'] === '1000.00' && $h['vat'] === '160.00' && $h['inclusiveVat'] === '1160.00' && lines(6, $i2) === ["$expA|DEBIT|1160.00", "$pa|CREDIT|1160.00"], errs($r) . json_encode(lines(6, $i2)));
    ok('VAT inclusive stores the VAT row', (int)$h['vattype'] === 2 && $h['vatId'] !== null);
    $r = $model->save(base(['vattype' => 3, 'vat' => '16', 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.00']]]));
    $i3 = (int)$r['id']; $h = header_row($i3);
    ok('VAT exclusive: exclusive 1000.00, VAT 160.00, total 1160.00, debit and credit 1160.00', $r['success'] && $h['exclusiveVat'] === '1000.00' && $h['vat'] === '160.00' && $h['inclusiveVat'] === '1160.00' && lines(6, $i3) === ["$expA|DEBIT|1160.00", "$pa|CREDIT|1160.00"]);
    $r = $model->save(base(['vattype' => 3, 'vat' => '16', 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '33.33'], ['pid' => $p3, 'qty' => '1', 'rate' => '0.10'], ['pid' => $p2, 'qty' => '3', 'rate' => '0.10']]]));
    $i4 = (int)$r['id']; $h = header_row($i4); $ln = lines(6, $i4);
    // 33.33 -> VAT 5.33 -> 38.66 ; 0.10 -> 0.02 -> 0.12 ; 0.30 -> 0.05 -> 0.35 ; total 39.13
    ok('VAT exclusive rounding per line: lines 38.66 / 0.12 / 0.35, credit 39.13, header = sum of lines', $r['success'] && $ln === ["$expA|DEBIT|38.66", "$expB|DEBIT|0.12", "$assetA|DEBIT|0.35", "$pa|CREDIT|39.13"] && $h['inclusiveVat'] === '39.13' && $h['vat'] === '5.40' && $h['exclusiveVat'] === '33.73', errs($r) . json_encode($ln) . json_encode($h));
    $r = $model->save(base(['vattype' => 2, 'vat' => '16', 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '0.99'], ['pid' => $p1, 'qty' => '7', 'rate' => '1.13'], ['pid' => $p3, 'qty' => '1', 'rate' => '1234567.89']]]));
    $i5 = (int)$r['id']; $h = header_row($i5);
    $drTotal = one("SELECT SUM(IF(l.dc='DEBIT',l.amount,0)) d, SUM(IF(l.dc='CREDIT',l.amount,0)) c FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=6 AND e.transactionId=? AND e.deleted=0", [$i5]);
    ok('VAT inclusive with awkward amounts: entry balances to the cent and equals the header total', $r['success'] && $drTotal['d'] === $drTotal['c'] && $drTotal['c'] === $h['inclusiveVat'] && number_format($h['exclusiveVat'] + $h['vat'], 2, '.', '') === $h['inclusiveVat'], json_encode([$drTotal, $h]));

    $r = $model->save(base(['payable' => $pDeep, 'table' => [['pid' => $p3, 'qty' => '1', 'rate' => '10.00', 'gross' => '999999', 'account' => $incomeA]], 'totals' => 5, 'total' => '1']));
    $i6 = (int)$r['id'];
    ok('client totals, gross and line account are ignored; a deeper payable account is stored', $r['success'] && header_row($i6)['inclusiveVat'] === '10.00' && lines(6, $i6) === ["$expB|DEBIT|10.00", "$pDeep|CREDIT|10.00"] && (int)header_row($i6)['PayableAccountId'] === $pDeep);

    // ------------------------------------------------------------ create: refusals change nothing
    $before = dbstate();
    $refuse = [
        ['crediting account = reporting 23', ['payable' => 23], 'crediting'], ['crediting = reporting parent', ['payable' => $pParent], 'crediting'], ['crediting inactive', ['payable' => $pInactive], 'inactive'],
        ['crediting foreign', ['payable' => $pForeign], 'another congregation'], ['crediting deleted', ['payable' => $pDeleted], 'deleted'], ['crediting outside 23', ['payable' => $pOther], 'payables'],
        ['crediting a bank', ['payable' => 105], 'crediting'], ['crediting missing', ['payable' => ''], 'crediting'], ['crediting text', ['payable' => 'abc'], 'crediting'], ['crediting array', ['payable' => [1]], 'crediting'],
        ['supplier of another congregation', ['supplier' => $sForeign], 'supplier'], ['deleted supplier', ['supplier' => $sDeleted], 'supplier'], ['unknown supplier', ['supplier' => 999999], 'supplier'], ['missing supplier', ['supplier' => ''], 'supplier'],
        ['invalid date', ['idate' => '2026-02-30'], 'date'], ['future invoice date', ['idate' => date('Y-m-d', strtotime('+1 day'))], 'future'], ['closed-year date', ['idate' => $closedDate, 'ddate' => $today], 'closed'],
        ['date with no financial year', ['idate' => $noYear, 'ddate' => $today], 'financial year'], ['slash date', ['idate' => '01/02/2026'], 'date'], ['due before invoice', ['ddate' => '2000-01-01'], 'due date'], ['bad due date', ['ddate' => 'x'], 'due date'],
        ['vat type 9', ['vattype' => 9], 'vat'], ['vat type missing', ['vattype' => ''], 'vat'], ['vat missing for inclusive', ['vattype' => 2, 'vat' => ''], 'vat'], ['vat rate not in the table', ['vattype' => 3, 'vat' => '17'], 'vat'],
        ['vat 14% (inactive row)', ['vattype' => 3, 'vat' => '14'], 'vat'],
        ['invoice no with markup', ['invoiceno' => '<b>1</b>'], 'invoice no'], ['invoice no empty', ['invoiceno' => ''], 'invoice no'], ['invoice no blank', ['invoiceno' => '   '], 'invoice no'], ['invoice no 51 characters', ['invoiceno' => str_repeat('A', 51)], 'invoice no'],
        ['invoice no with a quote', ['invoiceno' => "A'1"], 'invoice no'], ['invoice no array', ['invoiceno' => ['1']], 'invoice no'], ['invoice no with #', ['invoiceno' => 'INV#1'], 'invoice no'],
        ['no lines', ['table' => []], 'products'], ['lines not an array', ['table' => 'x'], 'products'],
        ['income product', ['table' => [['pid' => $pInc, 'qty' => '1', 'rate' => '1']]], 'account'], ['reporting-account product', ['table' => [['pid' => $pRep, 'qty' => '1', 'rate' => '1']]], 'account'],
        ['bank-account product', ['table' => [['pid' => $pBank, 'qty' => '1', 'rate' => '1']]], 'account'], ['inactive-account product', ['table' => [['pid' => $pInact, 'qty' => '1', 'rate' => '1']]], 'account'],
        ['foreign-account product', ['table' => [['pid' => $pForeignAcct, 'qty' => '1', 'rate' => '1']]], 'account'], ['deleted-account product', ['table' => [['pid' => $pDelAcct, 'qty' => '1', 'rate' => '1']]], 'account'],
        ['retired product 1', ['table' => [['pid' => 1, 'qty' => '1', 'rate' => '1']]], 'product'], ['retired product 2', ['table' => [['pid' => 2, 'qty' => '1', 'rate' => '1']]], 'product'],
        ['deleted product', ['table' => [['pid' => $pDel, 'qty' => '1', 'rate' => '1']]], 'product'], ['product of another congregation', ['table' => [['pid' => $pForeignProd, 'qty' => '1', 'rate' => '1']]], 'product'],
        ['unknown product', ['table' => [['pid' => 999999, 'qty' => '1', 'rate' => '1']]], 'product'], ['text product', ['table' => [['pid' => 'x', 'qty' => '1', 'rate' => '1']]], 'product'],
        ['quantity 0', ['table' => [['pid' => $p1, 'qty' => '0', 'rate' => '1']]], 'quantity'], ['quantity negative', ['table' => [['pid' => $p1, 'qty' => '-1', 'rate' => '1']]], 'quantity'],
        ['quantity 3 decimals', ['table' => [['pid' => $p1, 'qty' => '1.005', 'rate' => '1']]], 'quantity'], ['quantity text', ['table' => [['pid' => $p1, 'qty' => 'abc', 'rate' => '1']]], 'quantity'],
        ['quantity array', ['table' => [['pid' => $p1, 'qty' => [1], 'rate' => '1']]], 'quantity'], ['quantity huge', ['table' => [['pid' => $p1, 'qty' => '99999999', 'rate' => '1']]], 'quantity'],
        ['rate 0', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '0']]], 'rate'], ['rate negative', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '-5']]], 'rate'],
        ['rate 3 decimals', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1.001']]], 'rate'], ['rate text', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1e3']]], 'rate'],
        ['rate huge', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '99999999999999']]], 'rate'], ['amount rounds to zero', ['table' => [['pid' => $p1, 'qty' => '0.01', 'rate' => '0.01']]], 'at least'],
        ['total out of range', ['table' => [['pid' => $p1, 'qty' => '1000000', 'rate' => '99999999.99'], ['pid' => $p1, 'qty' => '1000000', 'rate' => '99999999.99']]], 'range'],
        ['201 lines', ['table' => array_fill(0, 201, ['pid' => $p1, 'qty' => '1', 'rate' => '1'])], 'more than'],
    ];
    foreach ($refuse as [$label, $over, $needle]) {
        $r = $model->save(base($over));
        ok("create refused: $label", !$r['success'] && stripos(errs($r), $needle) !== false, errs($r));
    }
    ok('create refusals wrote nothing at all (rows, entries, bank rows, mirror unchanged)', dbstate() === $before);
    $dupNo = (string)header_row($i1)['invoiceNo'];
    $r = $model->save(base(['invoiceno' => $dupNo]));
    ok('create refused: invoice no already used by this supplier', has($r, 'already exists'));
    // 8.8 A1: the number is text now (VARCHAR(50)), unique per supplier
    ok('invoiceNo is VARCHAR(50) and nullable', one("SELECT COLUMN_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_header_suppliers' AND COLUMN_NAME='invoiceNo'") == ['t' => 'varchar(50)', 'n' => 'YES']);
    $r = $model->save(base(['invoiceno' => ' INV-0042 ']));
    $iL = (int)($r['id'] ?? 0);
    ok('a number with letters, digits and a dash is accepted, trimmed and stored exactly', $r['success'] && header_row($iL)['invoiceNo'] === 'INV-0042', errs($r));
    ok('the entry reference carries the text number', one('SELECT reference FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$iL])['reference'] === 'supplier invoice-INV-0042');
    $r = $model->save(base(['invoiceno' => 'inv-0042']));
    ok('the same number (any case) for the same supplier is refused', has($r, 'already exists'), errs($r));
    $r = $model->save(base(['invoiceno' => 'INV-0042', 'supplier' => $sb]));
    ok('the same number for a DIFFERENT supplier is allowed', $r['success'], errs($r));
    foreach (['a/b.c 1', '2026/07/15', 'X 9', '0', str_repeat('Z', 50)] as $okNo) {
        $r = $model->save(base(['invoiceno' => $okNo, 'supplier' => $sb]));
        ok("number '$okNo' accepted", $r['success'] && header_row((int)$r['id'])['invoiceNo'] === $okNo, errs($r));
    }
    $r = $model->save(base(['id' => $iL, 'isedit' => true, 'invoiceno' => (string)header_row($i2)['invoiceNo']]));
    ok('an edit cannot take a number the supplier already has', has($r, 'already exists'), errs($r));
    $r = $model->save(base(['id' => $iL, 'isedit' => true, 'invoiceno' => 'INV-0043']));
    ok('an edit may take a fresh number', $r['success'] && header_row($iL)['invoiceNo'] === 'INV-0043', errs($r));
    $r = $model->save(base(['id' => $iL, 'isedit' => true, 'invoiceno' => 'INV-0042']));
    $r = $model->save(base(['id' => $iL, 'isedit' => true, 'invoiceno' => 'inv-0042']));
    ok('an edit may keep its own number (case change allowed)', $r['success'], errs($r));
    ok('opening-balance rows keep a NULL number and do not collide with each other', cnt('SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE invoiceNo IS NULL AND deleted=0') >= 2);
    $pdo->prepare('UPDATE tblinvoice_header_suppliers SET deleted=1 WHERE ID=?')->execute([$i6]);
    $r = $model->save(base(['invoiceno' => (string)header_row($i6)['invoiceNo']])); $reuse = (int)($r['id'] ?? 0);
    ok('an invoice no of a deleted invoice can be used again', $r['success']);
    $pdo->prepare('UPDATE tblinvoice_header_suppliers SET deleted=0 WHERE ID=?')->execute([$i6]);

    // a failure after the header is written rolls the lot back: a closed-year invoice date in the middle of a batch cannot be simulated
    // through the model, so force a failure with an unpostable account behind a valid-looking line
    $pdo->prepare('UPDATE tblaccounttypes SET active=0 WHERE ID=?')->execute([$expB]);
    $before = dbstate();
    $r = $model->save(base(['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '5'], ['pid' => $p3, 'qty' => '1', 'rate' => '5']]]));
    $pdo->prepare('UPDATE tblaccounttypes SET active=1 WHERE ID=?')->execute([$expB]);
    ok('a line whose account was deactivated refuses the whole invoice', !$r['success'] && dbstate() === $before, errs($r));

    // ------------------------------------------------------------ edit in place
    $oldEntry = one('SELECT ID FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$i1])['ID'];
    $pdo->prepare('UPDATE tblproducts SET accountId=? WHERE ID=?')->execute([$expB, $p1]);   // the product moves to another account after posting
    $r = $model->save(base(['id' => $i1, 'isedit' => true, 'invoiceno' => (string)header_row($i1)['invoiceNo'], 'table' => [['pid' => $p1, 'qty' => '2', 'rate' => '1000.00'], ['pid' => $p2, 'qty' => '1', 'rate' => '500.50']]]));
    ok('edit with the same lines keeps the account snapshot even though the product\'s account changed', $r['success'] && lines(6, $i1) === ["$expA|DEBIT|2000.00", "$assetA|DEBIT|500.50", "$pa|CREDIT|2500.50"], errs($r) . json_encode(lines(6, $i1)));
    $r = $model->save(base(['id' => $i1, 'isedit' => true, 'invoiceno' => (string)header_row($i1)['invoiceNo'], 'payable' => $pDeep, 'vattype' => 3, 'vat' => '16', 'ddate' => date('Y-m-d', strtotime('+30 days')),
                            'table' => [['pid' => $p1, 'qty' => '3', 'rate' => '100.00'], ['pid' => $p3, 'qty' => '1', 'rate' => '50.00']]]));
    $h = header_row($i1);
    $newEntry = one('SELECT ID FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$i1])['ID'];
    ok('edit: the same header and the SAME entry are updated in place', $r['success'] && $newEntry === $oldEntry && cnt('SELECT COUNT(*) FROM journal_entries WHERE transactionTypeId=6 AND transactionId=?', [$i1]) === 1 && cnt('SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE ID=?', [$i1]) === 1, errs($r));
    ok('edit: changed line (new account snapshot), VAT, payable and totals', lines(6, $i1) === ["$expB|DEBIT|348.00", "$expB|DEBIT|58.00", "$pDeep|CREDIT|406.00"] && $h['inclusiveVat'] === '406.00' && $h['vat'] === '56.00' && (int)$h['PayableAccountId'] === $pDeep, json_encode(lines(6, $i1)));
    ok('edit: old detail rows replaced, not added', cnt('SELECT COUNT(*) FROM tblinvoice_details_suppliers WHERE header_id=?', [$i1]) === 2);
    ok('edit: mirror follows (3 rows, balanced)', cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=6 AND transactionId=? AND deleted=0', [$i1]) === 3);
    $pdo->prepare('UPDATE tblproducts SET accountId=? WHERE ID=?')->execute([$expA, $p1]);
    $before = dbstate();
    $bad = [
        ['into a closed year', ['idate' => $closedDate, 'ddate' => $today], 'closed'], ['future date', ['idate' => date('Y-m-d', strtotime('+1 day')), 'ddate' => '2999-01-01'], 'future'],
        ['crediting reporting', ['payable' => 23], 'crediting'], ['supplier of another congregation', ['supplier' => $sForeign], 'supplier'],
        ['income product', ['table' => [['pid' => $pInc, 'qty' => '1', 'rate' => '1']]], 'account'], ['duplicate invoice no', ['invoiceno' => (string)header_row($i2)['invoiceNo']], 'already exists'],
    ];
    foreach ($bad as [$label, $over, $needle]) {
        $r = $model->save(base(array_merge(['id' => $i1, 'isedit' => true, 'invoiceno' => (string)header_row($i1)['invoiceNo']], $over)));
        ok("edit refused: $label", !$r['success'] && stripos(errs($r), $needle) !== false, errs($r));
    }
    ok('refused edits changed nothing', dbstate() === $before);
    foreach ([['unknown id', 999999], ['junk id', 'x'], ['empty id', ''], ['injection id', '1 OR 1=1']] as [$label, $id]) {
        $r = $model->save(base(['id' => $id, 'isedit' => true]));
        ok("edit refused: $label", has($r, 'not found'), errs($r));
    }
    // a foreign congregation's invoice is not found for this session
    $pdo->prepare("INSERT INTO tblinvoice_header_suppliers(invoiceDate,duedate,supplierId,invoiceNo,fiscalYearId,vattype,PayableAccountId,exclusiveVat,vat,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,?,25,1,?,10,0,10,64,2)")->execute([$today, $today, $sForeign, 880001, $pa]);
    $foreignInv = (int)$pdo->lastInsertId();
    $before = dbstate();
    $r = $model->save(base(['id' => $foreignInv, 'isedit' => true, 'invoiceno' => '880001']));
    ok('edit refused: invoice of another congregation (forged ID)', has($r, 'not found') && dbstate() === $before);
    $r = $model->Delete($foreignInv);
    ok('delete refused: invoice of another congregation (forged ID)', !$r['success'] && stripos(errs($r), 'not found') !== false && dbstate() === $before);
    ok('lookups by ID are congregation-scoped: header, supplier, product rate', $model->getInvoiceHeader(encryptId($foreignInv)) === false && $model->getSupplierDetails($sForeign) === false && (new Product)->GetRate($pForeignProd) === false && (new Product)->GetRate($p1) !== false);

    // closed-year lock on the OLD date of a stored invoice
    $r = $model->save(base(['invoiceno' => nextno()])); $ic = (int)$r['id'];
    $pdo->prepare('UPDATE tblinvoice_header_suppliers SET invoiceDate=? WHERE ID=?')->execute([$closedDate, $ic]);
    $pdo->prepare("UPDATE journal_entries SET entryDate=? WHERE transactionTypeId=6 AND transactionId=?")->execute([$closedDate, $ic]);
    $before = dbstate();
    $r = $model->save(base(['id' => $ic, 'isedit' => true, 'invoiceno' => (string)header_row($ic)['invoiceNo']]));
    ok('edit refused when the stored invoice is in a closed year (old date)', has($r, 'closed') && dbstate() === $before, errs($r));
    $r = $model->Delete($ic);
    ok('delete refused when the stored invoice is in a closed year', has($r, 'closed') && dbstate() === $before, errs($r));

    // roles (the buttons of the list): types 3 and 4 edit only their own unpaid invoices and never delete
    $r = $model->save(base(['invoiceno' => nextno()])); $ir = (int)$r['id'];
    $_SESSION['userType'] = 4; $_SESSION['userId'] = 98;
    $r = $model->save(base(['id' => $ir, 'isedit' => true, 'invoiceno' => (string)header_row($ir)['invoiceNo']]));
    ok('role: a type 4 user cannot edit an invoice posted by someone else', has($r, 'your own'));
    $r = $model->Delete($ir);
    ok('role: a type 4 user cannot delete', has($r, 'right to delete'));
    $r = $model->save(base(['invoiceno' => nextno()])); $imine = (int)$r['id'];
    $r = $model->save(base(['id' => $imine, 'isedit' => true, 'invoiceno' => (string)header_row($imine)['invoiceNo'], 'ddate' => date('Y-m-d', strtotime('+10 days'))]));
    ok('role: a type 4 user can edit their own unpaid invoice (and the owner stays)', $r['success'] && (int)header_row($imine)['postedBy'] === 98);
    $_SESSION['userType'] = 1; $_SESSION['userId'] = 64;

    // ------------------------------------------------------------ delete
    $r = $model->save(base(['invoiceno' => nextno(), 'payable' => $pDeep])); $id = (int)$r['id'];
    $r = $model->Delete($id);
    ok('delete voids the entry, the mirror and soft-deletes the header', $r['success'] && cnt('SELECT COUNT(*) FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$id]) === 0 && cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=6 AND transactionId=? AND deleted=0', [$id]) === 0 && (int)header_row($id)['deleted'] === 1 && cnt('SELECT COUNT(*) FROM tblinvoice_details_suppliers WHERE header_id=?', [$id]) === 1);
    $r = $model->Delete($id);
    ok('delete of an already deleted invoice is refused', has($r, 'not found'));
    $r = $model->save(base(['id' => $id, 'isedit' => true]));
    ok('edit of a deleted invoice is refused', has($r, 'not found'));
    foreach (['', 'x', '0', '-1'] as $junk) { $r = $model->Delete($junk); ok("delete junk id '$junk' refused", !$r['success']); }
    // a cleared bank row refuses the void (defensive: type 6 never writes one, so plant it)
    $r = $model->save(base(['invoiceno' => nextno()])); $idc = (int)$r['id'];
    $pdo->prepare("INSERT INTO tblbankpostings(bankId,transactionDate,debit,credit,transactionMethod,reference,cleared,clearedDare,narration,transactionType,transactionId,congregationId,deleted) VALUES(105,?,0,5,2,'x',1,?,'x',6,?,11,0)")->execute([$today, $today, $idc]);
    $before = dbstate();
    $r = $model->Delete($idc);
    ok('a cleared bank row refuses the void and nothing changes', has($r, 'cleared') && dbstate() === $before, errs($r));
    $pdo->prepare('UPDATE tblbankpostings SET deleted=1 WHERE transactionType=6 AND transactionId=?')->execute([$idc]);

    // ------------------------------------------------------------ payments
    $r = $model->save(base(['invoiceno' => nextno(), 'payable' => $pa, 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.00']]])); $pi1 = (int)$r['id'];
    $r = $model->save(base(['invoiceno' => nextno(), 'payable' => $pDeep, 'table' => [['pid' => $p3, 'qty' => '1', 'rate' => '400.00']]])); $pi2 = (int)$r['id'];
    $seq0 = (int)(one("SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind='suppay'")['lastNo'] ?? 0);
    $hdr = ['paydate' => $today, 'paymethod' => 1, 'cashaccount' => 34, 'bank' => ''];
    $r = $pay->Create($hdr + ['payments' => [['invoiceid' => $pi1, 'payment' => '300.25', 'cheque' => '']]]);
    $pid1 = (int)($r['ids'][0] ?? 0);
    ok('cash payment succeeds', $r['success'] && $pid1 > 0, errs($r));
    $pr = one('SELECT * FROM tblinvoice_payments_suppliers WHERE ID=?', [$pid1]);
    ok('cash payment row: money account stored, no bank, exact decimal, batch number', (int)$pr['PaymentAccountId'] === 34 && $pr['bankId'] === null && $pr['amount'] === '300.25' && (int)$pr['paymentId'] === 1 && (int)$pr['Deleted'] === 0 && (int)$pr['paymentNo'] === $seq0 + 1 && (int)$pr['paymentNo2'] === (int)$pr['paymentNo']);
    ok('cash payment entry (type 7, keyed on the payment row): Dr the invoice\'s stored payable / Cr the cash account', lines(7, $pid1) === ["$pa|DEBIT|300.25", "34|CREDIT|300.25"], json_encode(lines(7, $pid1)));
    ok('cash payment creates no bank row', cnt('SELECT COUNT(*) FROM tblbankpostings WHERE transactionType=7 AND transactionId=?', [$pid1]) === 0);
    ok('status is partially paid after the first payment', (int)header_row($pi1)['status'] === 1);
    $r = $pay->Create(['paydate' => $today, 'paymethod' => 3, 'bank' => 105, 'cashaccount' => '', 'payments' => [['invoiceid' => $pi2, 'payment' => '150.00', 'cheque' => 'CHQ-1'], ['invoiceid' => $pi1, 'payment' => '100.00', 'cheque' => 'CHQ-2']]]);
    ok('cheque payment of two invoices in one batch succeeds', $r['success'] && count($r['ids']) === 2 && (int)$r['paymentNo'] === $seq0 + 2, errs($r));
    $rowsB = rows('SELECT * FROM tblinvoice_payments_suppliers WHERE paymentNo=? ORDER BY invoice_Id', [$seq0 + 2]);
    ok('batch: one row per invoice with the same number and the chosen bank', count($rowsB) === 2 && (int)$rowsB[0]['PaymentAccountId'] === 105 && (int)$rowsB[0]['bankId'] === 105 && $rowsB[0]['paymentReference'] === 'chq-2' && $rowsB[1]['paymentReference'] === 'chq-1');
    $idP1 = (int)$rowsB[0]['ID']; $idP2 = (int)$rowsB[1]['ID'];   // ordered by invoice id: pi1 then pi2
    ok('each payment debits ITS invoice\'s stored payable account (two different payables) and credits the bank', lines(7, $idP1) === ["$pa|DEBIT|100.00", "105|CREDIT|100.00"] && lines(7, $idP2) === ["$pDeep|DEBIT|150.00", "105|CREDIT|150.00"], json_encode([lines(7, $idP1), lines(7, $idP2)]));
    $bk = rows('SELECT * FROM tblbankpostings WHERE transactionType=7 AND transactionId IN (?,?) AND deleted=0 ORDER BY transactionId', [$idP1, $idP2]);
    ok('bank payments get one withdrawal bank row each (credit side, uncleared) with the reference', count($bk) === 2 && (float)$bk[0]['credit'] === 100.0 && (float)$bk[0]['debit'] === 0.0 && (int)$bk[0]['bankId'] === 105 && (int)$bk[0]['cleared'] === 0 && $bk[0]['reference'] === 'chq-2' && (int)$bk[0]['transactionMethod'] === (int)one("SELECT ID FROM tbltransactionmethods WHERE methodName='Withdrawal'")['ID']);
    ok('status after the second payment: invoice 1 partial (400.25 of 1000), invoice 2 partial (150 of 400)', (int)header_row($pi1)['status'] === 1 && (int)header_row($pi2)['status'] === 1);
    $r = $pay->Create($hdr + ['payments' => [['invoiceid' => $pi2, 'payment' => '250.00', 'cheque' => '']]]);
    ok('paying the exact balance sets the status to paid', $r['success'] && (int)header_row($pi2)['status'] === 2, errs($r));
    ok('payment numbers are sequential per congregation from the locked sequence', (int)$r['paymentNo'] === $seq0 + 3 && (int)one("SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind='suppay'")['lastNo'] === $seq0 + 3);

    $before = dbstate(); $seqNow = cnt("SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind='suppay'");
    $r = $pay->Create($hdr + ['payments' => [['invoiceid' => $pi2, 'payment' => '0.01', 'cheque' => '']]]);
    ok('overpayment of a paid invoice refused', has($r, 'overpayment'), errs($r));
    $r = $pay->Create($hdr + ['payments' => [['invoiceid' => $pi1, 'payment' => '100.00', 'cheque' => ''], ['invoiceid' => $pi2, 'payment' => '0.01', 'cheque' => '']]]);
    ok('a batch with one overpaid invoice writes nothing for the others and does not burn a number', has($r, 'overpayment') && dbstate() === $before && cnt("SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind='suppay'") === $seqNow, errs($r));
    $r = $pay->Create($hdr + ['payments' => [['invoiceid' => $pi1, 'payment' => '599.76', 'cheque' => '']]]);
    ok('one cent over the balance (599.75) is refused', has($r, 'overpayment'));
    $refusePay = [
        ['unknown method 2', ['paymethod' => 2], 'method'], ['method 9', ['paymethod' => 9], 'method'], ['method text', ['paymethod' => 'x'], 'method'], ['method missing', ['paymethod' => ''], 'method'],
        ['cash without an account', ['cashaccount' => ''], 'cash account'], ['cash with a bank account', ['cashaccount' => 105], 'bank account'], ['cash with a non-cash account', ['cashaccount' => 52], 'cash'],
        ['cash with an unknown account', ['cashaccount' => 999999], 'exist'], ['cash with a text account', ['cashaccount' => 'x'], 'cash account'],
        ['cheque without a bank', ['paymethod' => 3, 'cashaccount' => '', 'bank' => ''], 'bank'], ['cheque with a cash account as bank', ['paymethod' => 3, 'bank' => 34], 'not a bank'], ['bank of another congregation', ['paymethod' => 4, 'bank' => 101], 'another congregation'],
        ['future date', ['paydate' => date('Y-m-d', strtotime('+1 day'))], 'greater'], ['invalid date', ['paydate' => '2026-02-30'], 'date'], ['closed-year date', ['paydate' => $closedDate], 'closed'], ['no financial year', ['paydate' => $noYear], 'financial year'], ['empty date', ['paydate' => ''], 'date'],
    ];
    foreach ($refusePay as [$label, $over, $needle]) {
        $r = $pay->Create(array_merge($hdr, ['payments' => [['invoiceid' => $pi1, 'payment' => '1.00', 'cheque' => 'R' . mt_rand()]]], $over));
        ok("payment refused: $label", !$r['success'] && stripos(errs($r), $needle) !== false, errs($r));
    }
    $bankH = ['paydate' => $today, 'paymethod' => 3, 'bank' => 105, 'cashaccount' => ''];
    $refuseRows = [
        ['cheque without a reference', [['invoiceid' => $pi1, 'payment' => '1', 'cheque' => '']], 'reference'], ['reference over 50 characters', [['invoiceid' => $pi1, 'payment' => '1', 'cheque' => str_repeat('c', 51)]], '50'],
        ['the same reference twice', [['invoiceid' => $pi1, 'payment' => '1', 'cheque' => 'dup'], ['invoiceid' => $pi2, 'payment' => '1', 'cheque' => 'DUP']], 'more than once'],
        ['the same invoice twice', [['invoiceid' => $pi1, 'payment' => '1', 'cheque' => 'a'], ['invoiceid' => $pi1, 'payment' => '1', 'cheque' => 'b']], 'twice'],
        ['amount 0', [['invoiceid' => $pi1, 'payment' => '0', 'cheque' => 'a']], 'amount'], ['negative amount', [['invoiceid' => $pi1, 'payment' => '-5', 'cheque' => 'a']], 'amount'],
        ['3 decimals', [['invoiceid' => $pi1, 'payment' => '1.005', 'cheque' => 'a']], 'decimal'], ['amount text', [['invoiceid' => $pi1, 'payment' => 'x', 'cheque' => 'a']], 'amount'],
        ['array amount', [['invoiceid' => $pi1, 'payment' => [1], 'cheque' => 'a']], 'amount'], ['no rows', [], 'no payments'], ['rows not an array', 'x', 'no payments'],
        ['unknown invoice', [['invoiceid' => 999999, 'payment' => '1', 'cheque' => 'a']], 'not found'], ['text invoice', [['invoiceid' => 'x', 'payment' => '1', 'cheque' => 'a']], 'invoice'],
        ['invoice of another congregation (forged ID)', [['invoiceid' => $foreignInv, 'payment' => '1', 'cheque' => 'a']], 'not found'],
    ];
    foreach ($refuseRows as [$label, $rowsIn, $needle]) {
        $b4 = dbstate();
        $r = $pay->Create(array_merge($bankH, ['payments' => $rowsIn]));
        ok("payment refused: $label", !$r['success'] && stripos(errs($r), $needle) !== false && dbstate() === $b4, errs($r));
    }
    ok('payment refusals left the number sequence alone', cnt("SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind='suppay'") === $seqNow);

    // legacy invoice without a stored payable: refused with a clear message
    $pdo->prepare("INSERT INTO tblinvoice_header_suppliers(invoiceDate,duedate,supplierId,invoiceNo,fiscalYearId,vattype,exclusiveVat,vat,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,?,25,1,500,0,500,64,11)")->execute([$today, $today, $sa, 880002]);
    $legacy = (int)$pdo->lastInsertId();
    $b4 = dbstate();
    $r = $pay->Create($hdr + ['payments' => [['invoiceid' => $legacy, 'payment' => '10', 'cheque' => '']]]);
    ok('legacy invoice without a crediting account cannot be paid (clear message, nothing written)', has($r, 'no crediting account') && dbstate() === $b4, errs($r));
    $pend = array_column(array_map(function($o) { return (array)$o; }, $pay->GetPendingInvoices()), 'PayableAccountId', 'ID');
    ok('the pending list shows the legacy invoice with a NULL account and the new ones with theirs', array_key_exists($legacy, $pend) && $pend[$legacy] === null && (int)$pend[$pi1] === $pa);
    ok('dev legacy invoices without a crediting account (reported, not backfilled)', cnt('SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE invoiceNo IS NOT NULL AND PayableAccountId IS NULL AND ID IN (3,4,5)') === 3);
    $_SESSION['congId'] = 2;
    $b4 = dbstate();
    $r = $pay->Create($hdr + ['paymethod' => 4, 'bank' => 101, 'cashaccount' => '', 'payments' => [['invoiceid' => 3, 'payment' => '1', 'cheque' => 'x']]]);
    ok('the three dev legacy invoices (congregation 2, Nov 2022) refuse payment', !$r['success'] && dbstate() === $b4, errs($r));
    $_SESSION['congId'] = 11;

    // decision 82: invoice with payments
    $before = dbstate();
    $same = ['id' => $pi1, 'isedit' => true, 'invoiceno' => (string)header_row($pi1)['invoiceNo'], 'payable' => $pa, 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.00']]];
    $changes = [
        ['the date', ['idate' => date('Y-m-d', strtotime('-1 day'))], 'date'], ['the supplier', ['supplier' => $sb], 'supplier'], ['the crediting account', ['payable' => $pDeep], 'crediting account'],
        ['a quantity', ['table' => [['pid' => $p1, 'qty' => '2', 'rate' => '500.00']]], 'lines'], ['a rate (same total)', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '999.99']]], 'lines'],
        ['the product', ['table' => [['pid' => $p3, 'qty' => '1', 'rate' => '1000.00']]], 'lines'], ['an added line', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.00'], ['pid' => $p3, 'qty' => '1', 'rate' => '1.00']]], 'lines'],
        ['the VAT type', ['vattype' => 3, 'vat' => '16'], 'VAT'],
    ];
    foreach ($changes as [$label, $over, $needle]) {
        $r = $model->save(base(array_merge($same, $over)));
        ok("invoice with payments: changing $label is refused", !$r['success'] && stripos(errs($r), 'has payments') !== false && stripos(errs($r), $needle) !== false, errs($r));
    }
    $r = $model->Delete($pi1);
    ok('invoice with payments cannot be deleted', has($r, 'has payments') && dbstate() === $before);
    ok('refused edits and delete left every row as it was', dbstate() === $before);
    $entryBefore = one('SELECT ID FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$pi1])['ID'];
    $r = $model->save(base(array_merge($same, ['invoiceno' => nextno(), 'ddate' => date('Y-m-d', strtotime('+45 days'))])));
    ok('invoice with payments: the due date and the invoice number can still be changed (entry updated in place)', $r['success'] && header_row($pi1)['duedate'] === date('Y-m-d', strtotime('+45 days')) && one('SELECT ID FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$pi1])['ID'] === $entryBefore && lines(6, $pi1) === ["$expA|DEBIT|1000.00", "$pa|CREDIT|1000.00"] && (int)header_row($pi1)['status'] === 1, errs($r));

    // reports: deleted payments / headers / suppliers never count; congregation filters
    $b = one('SELECT getinvoicebalance_supplier(?) b, getinvoice_paid_supplier(?) p', [$pi1, $pi1]);
    ok('balance function = total - active payments (1000 - 400.25)', $b['b'] === '599.75' && $b['p'] === '400.25');
    $pdo->prepare('UPDATE tblinvoice_payments_suppliers SET Deleted=1 WHERE ID=?')->execute([$pid1]);
    $b = one('SELECT getinvoicebalance_supplier(?) b, getinvoice_paid_supplier(?) p, getinvoicebalance_supplier_bydate(?,?) d', [$pi1, $pi1, $pi1, $today]);
    ok('a deleted payment no longer counts in the balance functions', $b['b'] === '900.00' && $b['p'] === '100.00' && $b['d'] === '900.00', json_encode($b));
    ok('a deleted payment is not in the payments list view', cnt('SELECT COUNT(*) FROM vw_supplierpayments2 WHERE ID=?', [$pid1]) === 0 && cnt('SELECT COUNT(*) FROM vw_supplierpayments2 WHERE ID=?', [$idP1]) === 1);
    $pdo->prepare('UPDATE tblinvoice_payments_suppliers SET Deleted=0 WHERE ID=?')->execute([$pid1]);
    $r = $model->save(base(['invoiceno' => nextno(), 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '77.00']]])); $ix = (int)$r['id'];
    ok('a pending invoice is in vw_supplier_with_bals', cnt('SELECT COUNT(*) FROM vw_supplier_with_bals WHERE ID=?', [$ix]) === 1);
    $pdo->prepare('UPDATE tblinvoice_header_suppliers SET deleted=1 WHERE ID=?')->execute([$ix]);
    ok('a deleted invoice is not (view, balance function, supplier balance)', cnt('SELECT COUNT(*) FROM vw_supplier_with_bals WHERE ID=?', [$ix]) === 0 && one('SELECT getinvoicebalance_supplier(?) b', [$ix])['b'] === '0.00');
    $rep = new Invoicereport;
    $names = array_map(function($o) { return $o->supplierName; }, $rep->GetSupplierBalances([]));
    ok('Invoicereport::GetSupplierBalances is congregation-filtered (own suppliers only, no deleted ones)', in_array('zz supplier a', $names) && !in_array('zz supplier foreign', $names) && !in_array('xyz', $names));
    $bs = one('SELECT fn_get_supplier_balance(?) b', [$sa]);
    ok('fn_get_supplier_balance ignores deleted headers and deleted payments', $bs['b'] === number_format((float)one('SELECT SUM(inclusiveVat) s FROM tblinvoice_header_suppliers WHERE supplierId=? AND deleted=0', [$sa])['s'] - (float)one('SELECT COALESCE(SUM(p.amount),0) s FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON h.ID=p.invoice_Id WHERE h.supplierId=? AND h.deleted=0 AND p.Deleted=0', [$sa])['s'], 2, '.', ''));
    ok('payment statement routines take the congregation (sp_get_payments_by_invoiceno with the wrong one is empty)', count(rows('CALL sp_get_payments_by_invoiceno(?,?)', [(string)header_row($pi1)['invoiceNo'], 11])) >= 1 && count(rows('CALL sp_get_payments_by_invoiceno(?,?)', [(string)header_row($pi1)['invoiceNo'], 2])) === 0);

    // ------------------------------------------------------------ opening balances (type 15)
    $so = function($o = []) { global $pa, $today; return array_merge(['id' => 0, 'isedit' => false, 'suppliername' => 'zz opening ' . mt_rand(1000, 9999), 'contact' => '', 'pin' => '', 'address' => '', 'email' => '', 'contactperson' => '', 'balance' => '1000', 'asof' => $today, 'payable' => $pa], $o); };
    $r = $sup->CreateUpdate($so()); $s1 = (int)$r['id'];
    $oh = one('SELECT * FROM tblinvoice_header_suppliers WHERE supplierId=? AND invoiceNo IS NULL AND deleted=0', [$s1]);
    ok('opening balance +1000: row stores the payable and the signed balance', $r['success'] && (int)$oh['PayableAccountId'] === $pa && $oh['inclusiveVat'] === '1000.00' && $oh['invoiceDate'] === $today && (int)$oh['fiscalYearId'] > 0, errs($r));
    ok('opening balance +1000: Dr 20 opening balance equity / Cr the payable, keyed on the SUPPLIER id', lines(15, $s1) === ['20|DEBIT|1000.00', "$pa|CREDIT|1000.00"], json_encode(lines(15, $s1)));
    $e1 = one('SELECT ID FROM journal_entries WHERE transactionTypeId=15 AND transactionId=? AND deleted=0', [$s1])['ID'];
    ok('opening balance: no bank row, mirror has 2 rows', cnt('SELECT COUNT(*) FROM tblbankpostings WHERE transactionType=15 AND transactionId=?', [$s1]) === 0 && cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=15 AND transactionId=? AND deleted=0', [$s1]) === 2);
    ok('the supplier list shows the balance', (float)one('SELECT balance FROM vw_suppliers WHERE ID=?', [$s1])['balance'] === 1000.0);
    $r = $sup->CreateUpdate($so(['id' => $s1, 'isedit' => true, 'suppliername' => (string)one('SELECT supplierName FROM tblsuppliers WHERE ID=?', [$s1])['supplierName'], 'balance' => '-250.50', 'payable' => $pDeep]));
    ok('opening balance changed to -250.50 flips the sides with positive amounts, in the SAME entry', $r['success'] && lines(15, $s1) === ["$pDeep|DEBIT|250.50", '20|CREDIT|250.50'] && one('SELECT ID FROM journal_entries WHERE transactionTypeId=15 AND transactionId=? AND deleted=0', [$s1])['ID'] === $e1 && cnt('SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE supplierId=? AND invoiceNo IS NULL AND deleted=0', [$s1]) === 1, errs($r));
    ok('opening balance row follows (signed balance and new payable)', one('SELECT inclusiveVat,PayableAccountId FROM tblinvoice_header_suppliers WHERE supplierId=? AND invoiceNo IS NULL AND deleted=0', [$s1])['inclusiveVat'] === '-250.50');
    $r = $sup->CreateUpdate($so(['id' => $s1, 'isedit' => true, 'suppliername' => (string)one('SELECT supplierName FROM tblsuppliers WHERE ID=?', [$s1])['supplierName'], 'balance' => '0', 'asof' => '', 'payable' => '']));
    ok('opening balance changed to zero voids the entry and retires the row', $r['success'] && lines(15, $s1) === null && cnt('SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE supplierId=? AND invoiceNo IS NULL AND deleted=0', [$s1]) === 0 && cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=15 AND transactionId=? AND deleted=0', [$s1]) === 0, errs($r));
    $r = $sup->CreateUpdate($so(['id' => $s1, 'isedit' => true, 'suppliername' => (string)one('SELECT supplierName FROM tblsuppliers WHERE ID=?', [$s1])['supplierName'], 'balance' => '75.10']));
    ok('zero -> positive posts a fresh entry', $r['success'] && lines(15, $s1) === ['20|DEBIT|75.10', "$pa|CREDIT|75.10"], errs($r));
    $r = $sup->CreateUpdate($so(['balance' => '-10', 'payable' => $pa])); $s2 = (int)$r['id'];
    ok('create with a negative balance: Dr payable / Cr 20', $r['success'] && lines(15, $s2) === ["$pa|DEBIT|10.00", '20|CREDIT|10.00']);
    $r = $sup->CreateUpdate($so(['balance' => '', 'asof' => '', 'payable' => ''])); $s3 = (int)$r['id'];
    ok('create with a blank / zero balance posts nothing and stores no opening row', $r['success'] && lines(15, $s3) === null && cnt('SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE supplierId=?', [$s3]) === 0);

    // opening balance with payments (decision 82 logic)
    $ohid = (int)one('SELECT ID FROM tblinvoice_header_suppliers WHERE supplierId=? AND invoiceNo IS NULL AND deleted=0', [$s1])['ID'];
    $r = $pay->Create($hdr + ['payments' => [['invoiceid' => $ohid, 'payment' => '25.10', 'cheque' => '']]]);
    ok('an opening balance row can be paid (Dr its stored payable / Cr cash)', $r['success'] && lines(7, $r['ids'][0]) === ["$pa|DEBIT|25.10", '34|CREDIT|25.10'] && (int)header_row($ohid)['status'] === 1, errs($r));
    $nm = (string)one('SELECT supplierName FROM tblsuppliers WHERE ID=?', [$s1])['supplierName'];
    $before = dbstate();
    foreach ([['the amount', ['balance' => '80']], ['the date', ['asof' => date('Y-m-d', strtotime('-1 day'))]], ['the crediting account', ['payable' => $pDeep]], ['to zero', ['balance' => '0', 'asof' => '', 'payable' => '']]] as [$label, $over]) {
        $r = $sup->CreateUpdate($so(array_merge(['id' => $s1, 'isedit' => true, 'suppliername' => $nm, 'balance' => '75.10'], $over)));
        ok("opening balance with payments: changing $label is refused", has($r, 'has payments'), errs($r));
    }
    ok('those refusals changed nothing', dbstate() === $before);
    $r = $sup->CreateUpdate($so(['id' => $s1, 'isedit' => true, 'suppliername' => $nm, 'balance' => '75.10', 'contact' => '0712345678', 'address' => 'Somewhere']));
    ok('opening balance with payments: the supplier\'s other details can still be edited, entry untouched', $r['success'] && one('SELECT contact FROM tblsuppliers WHERE ID=?', [$s1])['contact'] === '0712345678' && lines(15, $s1) === ['20|DEBIT|75.10', "$pa|CREDIT|75.10"], errs($r));

    // validation of the supplier form
    $b4 = dbstate();
    foreach ([['name empty', ['suppliername' => ''], 'name'], ['name over 255', ['suppliername' => str_repeat('n', 256)], '255'], ['balance text', ['balance' => 'abc'], 'number'], ['balance 3 decimals', ['balance' => '1.005'], 'decimal'],
              ['balance huge', ['balance' => '99999999999999'], 'large'], ['balance array', ['balance' => [1]], 'number'],
              ['balance without a date', ['asof' => ''], 'date'], ['balance with a future date', ['asof' => date('Y-m-d', strtotime('+1 day'))], 'balance date'], ['balance with an invalid date', ['asof' => '2026-02-30'], 'date'],
              ['balance in a closed year', ['asof' => $closedDate], 'closed'], ['balance with no financial year', ['asof' => $noYear], 'financial year'],
              ['balance without a crediting account', ['payable' => ''], 'crediting'], ['balance with reporting 23', ['payable' => 23], 'crediting'], ['balance with an inactive account', ['payable' => $pInactive], 'inactive'],
              ['balance with a foreign account', ['payable' => $pForeign], 'another congregation'], ['balance with a bank', ['payable' => 105], 'crediting'], ['balance outside 23', ['payable' => $pOther], 'payables'],
              ['invalid email', ['email' => 'nope'], 'email'], ['contact over 20', ['contact' => str_repeat('1', 21)], '20'], ['pin over 15', ['pin' => str_repeat('1', 16)], '15']] as [$label, $over, $needle]) {
        $r = $sup->CreateUpdate($so($over));
        ok("supplier create refused: $label", !$r['success'] && stripos(errs($r), $needle) !== false, errs($r));
    }
    ok('refused supplier creates wrote nothing', dbstate() === $b4);

    // name uniqueness per congregation
    $r = $sup->CreateUpdate($so(['suppliername' => 'ZZ Same Name', 'balance' => '']));
    $sSame = (int)$r['id'];
    $r2 = $sup->CreateUpdate($so(['suppliername' => '  zz same name ', 'balance' => '']));
    ok('same supplier name in the same congregation is refused (case and spacing insensitive)', has($r2, 'exists'), errs($r2));
    $_SESSION['congId'] = 2;
    $r3 = $sup->CreateUpdate($so(['suppliername' => 'zz same name', 'balance' => '']));
    $sOther = (int)($r3['id'] ?? 0);
    ok('the same name in ANOTHER congregation is allowed', $r3['success'] && $sOther > 0, errs($r3));
    $r4 = $sup->CreateUpdate($so(['id' => $sSame, 'isedit' => true, 'suppliername' => 'renamed in the wrong place', 'balance' => '']));
    ok('editing a supplier of another congregation is refused (forged ID)', has($r4, 'not found') && one('SELECT supplierName FROM tblsuppliers WHERE ID=?', [$sSame])['supplierName'] === 'zz same name');
    $r5 = $sup->Delete($sSame);
    ok('deleting a supplier of another congregation is refused (forged ID)', !$r5['success'] && (int)one('SELECT deleted FROM tblsuppliers WHERE ID=?', [$sSame])['deleted'] === 0);
    ok('GetSupplier by ID is congregation-scoped', $sup->GetSupplier($sSame) === false);
    $_SESSION['congId'] = 11;
    $r6 = $sup->CreateUpdate($so(['id' => $sSame, 'isedit' => true, 'suppliername' => 'zz supplier a', 'balance' => '']));
    ok('renaming onto an existing name of the congregation is refused', has($r6, 'exists'));
    $r7 = $sup->CreateUpdate($so(['id' => $sSame, 'isedit' => true, 'suppliername' => 'zz same name', 'balance' => '']));
    ok('saving a supplier under its own name is fine', $r7['success']);

    // supplier delete rules
    $r = $sup->Delete($sa);
    ok('supplier with a normal invoice cannot be deleted', has($r, 'invoices') && (int)one('SELECT deleted FROM tblsuppliers WHERE ID=?', [$sa])['deleted'] === 0);
    $r = $sup->Delete($s1);
    ok('supplier whose opening balance has a payment cannot be deleted', has($r, 'payments') && lines(15, $s1) !== null);
    $r = $sup->Delete($s2);
    ok('supplier with only an opening balance: type 15 voided, row and supplier soft-deleted', $r['success'] && lines(15, $s2) === null && (int)one('SELECT deleted FROM tblsuppliers WHERE ID=?', [$s2])['deleted'] === 1 && cnt('SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE supplierId=? AND deleted=0', [$s2]) === 0 && cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=15 AND transactionId=? AND deleted=0', [$s2]) === 0, errs($r));
    $r = $sup->Delete($s2);
    ok('deleting a deleted supplier is refused', has($r, 'not found'));
    foreach (['', 'x', '0', '1 OR 1=1'] as $junk) { $r = $sup->Delete($junk); ok("supplier delete junk id '$junk' refused", !$r['success']); }
    $r = $sup->CreateUpdate($so(['balance' => '5'])); $s4 = (int)$r['id'];
    $pdo->prepare("INSERT INTO tblbankpostings(bankId,transactionDate,debit,credit,transactionMethod,reference,cleared,clearedDare,narration,transactionType,transactionId,congregationId,deleted) VALUES(105,?,0,5,2,'x',1,?,'x',15,?,11,0)")->execute([$today, $today, $s4]);
    $b4 = dbstate();
    $r = $sup->Delete($s4);
    ok('a cleared bank row refuses the supplier delete (void) and nothing changes', has($r, 'cleared') && dbstate() === $b4, errs($r));
    $pdo->prepare('UPDATE tblbankpostings SET deleted=1 WHERE transactionType=15 AND transactionId=?')->execute([$s4]);
    // a closed-year opening balance cannot be changed or deleted, but the supplier's other details can
    $pdo->prepare('UPDATE tblinvoice_header_suppliers SET invoiceDate=? WHERE supplierId=? AND invoiceNo IS NULL')->execute([$closedDate, $s4]);
    $pdo->prepare("UPDATE journal_entries SET entryDate=? WHERE transactionTypeId=15 AND transactionId=?")->execute([$closedDate, $s4]);
    $nm4 = (string)one('SELECT supplierName FROM tblsuppliers WHERE ID=?', [$s4])['supplierName'];
    $r = $sup->CreateUpdate($so(['id' => $s4, 'isedit' => true, 'suppliername' => $nm4, 'balance' => '6', 'asof' => $closedDate]));
    ok('a closed-year opening balance cannot be changed', has($r, 'closed'), errs($r));
    $r = $sup->CreateUpdate($so(['id' => $s4, 'isedit' => true, 'suppliername' => $nm4, 'balance' => '5', 'asof' => $closedDate, 'contact' => '0700000000']));
    ok('...but an unchanged opening balance does not block editing the supplier', $r['success'] && one('SELECT contact FROM tblsuppliers WHERE ID=?', [$s4])['contact'] === '0700000000', errs($r));
    $r = $sup->Delete($s4);
    ok('a closed-year opening balance cannot be deleted', has($r, 'closed'));
    $pdo->prepare('UPDATE tblinvoice_header_suppliers SET deleted=1 WHERE supplierId=?')->execute([$s4]);

    // dev mirror for the three types: every active entry is mirrored and balances
    $bad = cnt("SELECT COUNT(*) FROM (SELECT e.transactionTypeId,e.transactionId FROM journal_entries e WHERE e.deleted=0 AND e.transactionTypeId IN (6,7,15) AND e.ID>? AND NOT EXISTS (SELECT 1 FROM tblledger l WHERE l.transactionType=e.transactionTypeId AND l.transactionId=e.transactionId AND l.congregationId=e.congregationId AND l.deleted=0)) x", [$max['journal_entries']]);
    ok('dev legacy mirror: every entry made here has its tblledger rows', $bad === 0);
    $bal = cnt("SELECT COUNT(*) FROM (SELECT transactionType,transactionId FROM tblledger WHERE deleted=0 AND transactionType IN (6,7,15) AND ID>? GROUP BY transactionType,transactionId HAVING ROUND(SUM(debit)-SUM(credit),2)<>0) x", [$max['tblledger']]);
    ok('dev legacy mirror: every mirrored transaction balances', $bal === 0);
    ok('nothing in the new code posts a negative amount (journal_lines)', cnt('SELECT COUNT(*) FROM journal_lines WHERE ID>? AND amount<=0', [$max['journal_lines']]) === 0);

    // payment number sequence is per congregation and never count+1
    $_SESSION['congId'] = 2;
    $sCong2 = (int)$r3['id'];
    $pdo->prepare("INSERT INTO tblinvoice_header_suppliers(invoiceDate,duedate,supplierId,invoiceNo,fiscalYearId,vattype,PayableAccountId,exclusiveVat,vat,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,?,25,1,?,100,0,100,64,2)")->execute([$today, $today, $sCong2, 880003, $f15id]);
    $inv2 = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tblinvoice_payments_suppliers(paymentNo,paymentNo2,invoice_Id,paymentDate,amount,paymentId) VALUES(40,40,?,?,1,1)")->execute([$inv2, $today]);
    $r = $pay->Create(['paydate' => $today, 'paymethod' => 1, 'cashaccount' => 34, 'bank' => '', 'payments' => [['invoiceid' => $inv2, 'payment' => '5', 'cheque' => '']]]);
    ok('the sequence starts from the highest stored number of that congregation (41 after a stored 40), not from a count', $r['success'] && (int)$r['paymentNo'] === 41, errs($r));
    $_SESSION['congId'] = 11;
    $cur = cnt("SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind='suppay'");
    $r = $pay->Create($hdr + ['payments' => [['invoiceid' => $pi1, 'payment' => '1', 'cheque' => '']]]);
    ok('congregation 11 keeps its own sequence (unaffected by the payments of congregation 2)', $r['success'] && (int)$r['paymentNo'] === $cur + 1, errs($r));
    $pdo->prepare('UPDATE tblinvoice_payments_suppliers SET Deleted=1 WHERE invoice_Id=?')->execute([$pi1]);
    $seqRow = (int)one("SELECT lastNo FROM receipt_number_seq WHERE congregationId=11 AND kind='suppay'")['lastNo'];
    $pdo->prepare('UPDATE tblinvoice_header_suppliers SET status=0 WHERE ID=?')->execute([$pi1]);
    $r = $pay->Create($hdr + ['payments' => [['invoiceid' => $pi1, 'payment' => '1', 'cheque' => '']]]);
    ok('a number is never reused even when its payments are deleted', $r['success'] && (int)$r['paymentNo'] === $seqRow + 1, errs($r));
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (array_reverse($tables, true) as $t => $k) { $pdo->exec("DELETE FROM $t WHERE $k>" . $max[$t]); }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec('DELETE FROM receipt_number_seq');
    $ins = $pdo->prepare('INSERT INTO receipt_number_seq(congregationId,kind,fiscalYearId,lastNo) VALUES(?,?,?,?)');
    foreach ($seqs as $s) { $ins->execute([$s['congregationId'], $s['kind'], $s['fiscalYearId'], $s['lastNo']]); }
    $st = $pdo->prepare('UPDATE tblfiscalyears SET closed=?,deleted=? WHERE ID=?');
    foreach ($years as $y) { $st->execute([$y['closed'], $y['deleted'], $y['ID']]); }
    foreach ($f15 as $a) {
        $pdo->prepare('UPDATE tblaccounttypes SET accountType=?,active=?,congregationId=?,deleted=? WHERE ID=?')->execute([$a['accountType'], $a['active'], $a['congregationId'], $a['deleted'], $a['ID']]);
    }
    $pdo->exec('UPDATE tblproducts SET deleted=1 WHERE ID IN (1,2)');
}
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k>" . $max[$t])->fetchColumn(); }
ok('cleanup: no row above the starting IDs remains and the F15 account is as it was', $left === 0 && count($pdo->query("SELECT ID FROM tblaccounttypes WHERE LOWER(TRIM(accountType))='accounts payable' AND parentId=23 AND deleted=0 AND active=1 AND congregationId=0")->fetchAll()) === 1);
echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);

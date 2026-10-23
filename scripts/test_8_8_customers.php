<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
// 8.8 customer invoices (14) and customer payments (16) through the models, congregation 11.
// Self-cleaning: every row above the starting IDs is removed; the number sequences, the fiscal year flags and the accounts
// this run deactivates are restored. Needs scripts/sql/customer_accounts_schema.sql on the dev DB.
//   E:\xampp\php\php.exe scripts\test_8_8_customers.php
ini_set('session.save_path', __DIR__ . '/logs');
chdir(__DIR__ . '/../app');
require 'boostrap.php';
foreach (['Invoice', 'Product', 'Supplierinvoice', 'Reusables'] as $m) { require_once "models/$m.php"; }

$pdo = (new Database)->pdo();
$_SESSION['congId'] = 11; $_SESSION['userId'] = 64; $_SESSION['userType'] = 1; $_SESSION['isParish'] = 0; $_SESSION['zero'] = 0; $_SESSION['one'] = 1;

$tables = ['tblcustomers' => 'ID', 'tblinvoice_header' => 'ID', 'tblinvoice_details' => 'ID', 'tblinvoice_payments' => 'ID', 'tblproducts' => 'ID',
           'tblsuppliers' => 'ID', 'tblinvoice_header_suppliers' => 'ID', 'tblinvoice_details_suppliers' => 'ID',
           'journal_entries' => 'ID', 'journal_lines' => 'ID', 'tblledger' => 'ID', 'tblbankpostings' => 'ID', 'tbllogs' => 'ID', 'tblaccounttypes' => 'ID'];
$max = [];
foreach ($tables as $t => $k) { $max[$t] = (int)$pdo->query("SELECT COALESCE(MAX($k),0) FROM $t")->fetchColumn(); }
$years = $pdo->query('SELECT ID,closed,deleted FROM tblfiscalyears')->fetchAll(PDO::FETCH_ASSOC);
$seqs = $pdo->query('SELECT * FROM receipt_number_seq')->fetchAll(PDO::FETCH_ASSOC);
$activeBefore = $pdo->query('SELECT ID,active FROM tblaccounttypes')->fetchAll(PDO::FETCH_KEY_PAIR);

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
function newAccount($name, $parent, $posting = 1, $active = 1, $cong = 11, $deleted = 0, $type = 3) {
    global $pdo;
    $pdo->prepare('INSERT INTO tblaccounttypes(accountType,accountTypeId,parentId,isSubCategory,isBank,active,congregationId,deleted) VALUES(?,?,?,?,0,?,?,?)')
        ->execute([$name, $type, $parent, $posting, $active, $cong, $deleted]);
    return (int)$pdo->lastInsertId();
}
function dbstate() {
    global $max;
    $t = [];
    foreach (['tblinvoice_header' => 0, 'tblinvoice_details' => 0, 'tblinvoice_payments' => 0, 'tblcustomers' => 0, 'journal_entries' => 1, 'journal_lines' => 1, 'tblbankpostings' => 1, 'tblledger' => 1] as $tb => $from) {
        $t[] = $tb . ':' . md5(json_encode(rows("SELECT * FROM $tb" . ($from ? ' WHERE ID > ' . $max[$tb] : '') . ' ORDER BY 1')));
    }
    return implode(',', $t);
}
function product($name, $account, $rate = '100.00', $cong = 11, $deleted = 0) {
    global $pdo;
    $pdo->prepare('INSERT INTO tblproducts(productName,rate,accountId,congregationId,deleted) VALUES(?,?,?,?,?)')->execute([$name, $rate, $account, $cong, $deleted]);
    return (int)$pdo->lastInsertId();
}

$today = date('Y-m-d');
$closedDate = '2024-05-01';
$noYear = '1990-01-01';
$model = new Invoice;

// ---------------------------------------------------------------- fixtures
$pdo->exec("INSERT INTO tblcustomers(customerName,congregationId) VALUES('zz customer a',11),('zz customer b',11)");
$ca = (int)$pdo->lastInsertId(); $cb = $ca + 1;
$pdo->exec("INSERT INTO tblcustomers(customerName,congregationId) VALUES('zz customer foreign',2)"); $cForeign = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO tblcustomers(customerName,congregationId,deleted) VALUES('zz customer deleted',11,1)"); $cDeleted = (int)$pdo->lastInsertId();

$ra = newAccount('zz recv a', 9);
$rParent = newAccount('zz recv parent', 9, 0);
$rDeep = newAccount('zz recv deep', $rParent);
$rInactive = newAccount('zz recv inactive', 9, 1, 0);
$rForeign = newAccount('zz recv foreign', 9, 1, 1, 2);
$rDeleted = newAccount('zz recv deleted', 9, 1, 1, 11, 1);
$incA = 90; $incB = 89;
$incInactive = newAccount('zz inc inactive', 31, 1, 0, 11, 0, 1);
$incForeign = newAccount('zz inc foreign', 31, 1, 1, 2, 0, 1);
$incDeleted = newAccount('zz inc deleted', 31, 1, 1, 11, 1, 1);
$p1 = product('zz c prod income', $incA); $p2 = product('zz c prod income b', $incB, '500.50'); $p3 = product('zz c prod income a2', $incA, '10.00');
$pExp = product('zz c prod expense', 51); $pAsset = product('zz c prod asset', 167); $pRep = product('zz c prod reporting', 32); $pBank = product('zz c prod bank', 105);
$pInact = product('zz c prod inactive', $incInactive); $pDel = product('zz c prod deleted', $incA, '1.00', 11, 1); $pForeignProd = product('zz c prod foreign', $incA, '1.00', 2);
$pForeignAcct = product('zz c prod foreign acct', $incForeign); $pDelAcct = product('zz c prod deleted acct', $incDeleted);

function base($o = []) {
    global $ca, $ra, $p1, $today;
    return array_merge(['id' => '', 'isedit' => false, 'customer' => $ca, 'idate' => $today, 'ddate' => $today, 'vattype' => 1, 'vat' => '',
                        'receivable' => $ra, 'table' => [['pid' => $p1, 'qty' => '2', 'rate' => '1000.00']]], $o);
}
function hdr($id) { return one('SELECT * FROM tblinvoice_header WHERE ID=?', [$id]); }

try {
    ok('env: financial year 2023-2024 is closed and 2026-2027 open', cnt("SELECT COUNT(*) FROM tblfiscalyears WHERE closed=1 AND deleted=0 AND ? BETWEEN startDate AND endDate", [$closedDate]) === 1 && Ledger::dateProblem($pdo, $today) === null);
    ok('the customer tables have no deleted column and invoiceNo is INT (documented in the schema script)', cnt("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('tblinvoice_header','tblinvoice_details','tblinvoice_payments') AND COLUMN_NAME IN ('deleted','Deleted')") === 0
        && one("SELECT DATA_TYPE t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblinvoice_header' AND COLUMN_NAME='invoiceNo'")['t'] === 'int');
    ok('there is no resolver / default for receivables and no invoice delete anywhere', !method_exists('Ledger', 'accountsReceivableId') && !method_exists($model, 'delete') && !method_exists($model, 'Delete') && stripos(file_get_contents('controllers/Invoices.php'), 'function delete') === false && strpos(file_get_contents('views/invoices/index.php'), 'invoices/delete') === false);

    // ------------------------------------------------------------ receivable list
    $list = array_column(Ledger::listReceivableAccounts($pdo, 11), 'ID');
    ok('receivable list: own account and a deeper posting account are offered, with the shared children of 9', in_array($ra, $list) && in_array($rDeep, $list) && in_array(35, $list));
    ok('receivable list: 9 itself, reporting parent, inactive, other congregation, deleted, banks, payables and income accounts are not', !array_intersect([9, $rParent, $rInactive, $rForeign, $rDeleted, 105, 23, 38, 90, 34], $list));
    ok('receivable list is per congregation', in_array($rForeign, array_column(Ledger::listReceivableAccounts($pdo, 2), 'ID')) && !in_array($ra, array_column(Ledger::listReceivableAccounts($pdo, 2), 'ID')));
    foreach ([[$ra, true], [$rDeep, true], [35, true], [9, false], [$rParent, false], [$rInactive, false], [$rForeign, false], [$rDeleted, false], [105, false], [38, false], [90, false], [0, false], [999999, false]] as [$acc, $want]) {
        $got = Ledger::receivableProblem($pdo, 11, $acc) === null;
        ok("receivableProblem($acc) " . ($want ? 'accepts' : 'refuses') . ' (same rule as the list)', $got === $want && $got === in_array($acc, $list));
    }
    ok('an inactive stored receivable can be kept on an edit', Ledger::receivableProblem($pdo, 11, $rInactive, true) === null);

    // ------------------------------------------------------------ line account lists (A2)
    $incIds = array_column($model->lineAccounts(), 'ID');
    ok('customer line accounts: income posting accounts only', in_array($incA, $incIds) && in_array($incB, $incIds) && !array_intersect([51, 167, 32, 105, $incInactive, $incForeign, $incDeleted, 9, $ra], $incIds));
    $prodIds = array_map(function($p) { return (int)$p->ID; }, $model->getProducts());
    ok('customer product list: income products only (no expense / asset / reporting / bank / inactive / foreign / deleted / retired)', in_array($p1, $prodIds) && in_array($p2, $prodIds) && !array_intersect([$pExp, $pAsset, $pRep, $pBank, $pInact, $pDel, $pForeignProd, $pForeignAcct, $pDelAcct, 1, 2], $prodIds));
    $pm = new Product; $gl = array_map(function($a) { return (int)$a->ID; }, $pm->GetGlAccounts());
    $sup = new Supplierinvoice; $supIds = array_column($sup->lineAccounts(), 'ID');
    ok('Product::GetGlAccounts = the supplier line accounts + the customer line accounts (posting only, no reporting, no bank)', !array_diff(array_merge($supIds, $incIds), $gl) && !array_diff($gl, array_merge($supIds, $incIds)) && !array_intersect([9, 32, 105, 23], $gl));
    ok('Product::AccountProblem: income, expense and asset accounts accepted; reporting, bank, payable and junk refused', $pm->AccountProblem($incA) === null && $pm->AccountProblem(51) === null && $pm->AccountProblem(167) === null && $pm->AccountProblem(32) !== null && $pm->AccountProblem(105) !== null && $pm->AccountProblem(38) !== null && $pm->AccountProblem('x') !== null && $pm->AccountProblem(0) !== null);
    $d = $model->getProductDetails($p1);
    ok('product details: rate, income account, no problem', $d && (int)$d['accountId'] === $incA && $d['problem'] === null);
    ok('product details: expense / bank / reporting accounts are problems; foreign, deleted, retired 1 and 2, junk are null', $model->getProductDetails($pExp)['problem'] !== null && $model->getProductDetails($pBank)['problem'] !== null && $model->getProductDetails($pRep)['problem'] !== null
        && $model->getProductDetails($pForeignProd) === null && $model->getProductDetails($pDel) === null && $model->getProductDetails(1) === null && $model->getProductDetails(2) === null && $model->getProductDetails('x') === null);
    $n0 = cnt('SELECT COUNT(*) FROM tblproducts');
    $r = $model->newProduct(['name' => 'zz new c product', 'desc' => 'd', 'sp' => '12.50', 'account' => $incA]);
    ok('newProduct creates an income product and returns its id', $r['success'] && (int)one('SELECT accountId a FROM tblproducts WHERE ID=?', [$r['id']])['a'] === $incA);
    foreach ([['expense account', ['account' => 51]], ['bank account', ['account' => 105]], ['reporting account', ['account' => 32]], ['inactive', ['account' => $incInactive]], ['text account', ['account' => 'x']], ['duplicate name', ['name' => 'zz new c product']],
              ['zero price', ['sp' => '0']], ['3 decimals', ['sp' => '1.005']], ['array price', ['sp' => ['1']]], ['empty name', ['name' => '']]] as [$label, $over]) {
        $c = cnt('SELECT COUNT(*) FROM tblproducts');
        $r = $model->newProduct(array_merge(['name' => 'zz bad ' . $label, 'desc' => '', 'sp' => '5', 'account' => $incA], $over));
        ok("newProduct refuses $label", !$r['success'] && cnt('SELECT COUNT(*) FROM tblproducts') === $c, errs($r));
    }

    // ------------------------------------------------------------ create: every posting shape
    $seq0 = $model->peekInvoiceNo();
    $r = $model->save(base(['table' => [['pid' => $p1, 'qty' => '2', 'rate' => '1000.00', 'desc' => 'First'], ['pid' => $p2, 'qty' => '1', 'rate' => '500.50']]]));
    $i1 = (int)($r['id'] ?? 0);
    ok('create (no VAT, two lines) succeeds', $r['success'] && $i1 > 0, errs($r));
    $h = hdr($i1);
    ok('create: header stores the chosen receivable account, exact money, status open, and a system number', (int)$h['ReceivableAccountId'] === $ra && $h['inclusiveVat'] === '2500.50' && $h['exclusiveVat'] === '2500.50' && $h['vat'] === '0.00' && (int)$h['status'] === 0 && (int)$h['congregationId'] === 11 && (int)$h['customerId'] === $ca && (int)$h['invoiceNo'] === $seq0 && $r['invoiceNo'] == $seq0);
    $dl = rows('SELECT productId,AccountId,qty,rate,gross,description FROM tblinvoice_details WHERE header_id=? ORDER BY ID', [$i1]);
    ok('create: each line snapshots the product\'s income account; descriptions kept', count($dl) === 2 && (int)$dl[0]['AccountId'] === $incA && (int)$dl[1]['AccountId'] === $incB && $dl[0]['gross'] === '2000.00' && $dl[1]['gross'] === '500.50' && $dl[0]['description'] === 'first');
    ok('create: type 14 entry keyed on the header: Dr the receivable (total) / Cr each line account', lines(14, $i1) === ["$ra|DEBIT|2500.50", "$incA|CREDIT|2000.00", "$incB|CREDIT|500.50"], json_encode(lines(14, $i1)));
    $e = one('SELECT * FROM journal_entries WHERE transactionTypeId=14 AND transactionId=? AND deleted=0', [$i1]);
    ok('create: entry date, congregation and reference', $e['entryDate'] === $today && (int)$e['congregationId'] === 11 && $e['reference'] === 'invoice-' . $seq0);
    ok('create: no bank row; legacy mirror has 3 balanced rows', cnt('SELECT COUNT(*) FROM tblbankpostings WHERE transactionType=14 AND transactionId=?', [$i1]) === 0 && cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=14 AND transactionId=? AND deleted=0', [$i1]) === 3 && cnt('SELECT SUM(debit)=SUM(credit) FROM tblledger WHERE transactionType=14 AND transactionId=? AND deleted=0', [$i1]) === 1);

    $r = $model->save(base(['vattype' => 2, 'vat' => '16', 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1160.00']]]));
    $i2 = (int)$r['id']; $h = hdr($i2);
    ok('VAT inclusive: exclusive 1000.00, VAT 160.00, total 1160.00', $r['success'] && $h['exclusiveVat'] === '1000.00' && $h['vat'] === '160.00' && $h['inclusiveVat'] === '1160.00' && lines(14, $i2) === ["$ra|DEBIT|1160.00", "$incA|CREDIT|1160.00"], errs($r));
    $r = $model->save(base(['vattype' => 3, 'vat' => '16', 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.00']]]));
    $i3 = (int)$r['id']; $h = hdr($i3);
    ok('VAT exclusive: exclusive 1000.00, VAT 160.00, total 1160.00', $r['success'] && $h['exclusiveVat'] === '1000.00' && $h['vat'] === '160.00' && $h['inclusiveVat'] === '1160.00' && lines(14, $i3) === ["$ra|DEBIT|1160.00", "$incA|CREDIT|1160.00"]);
    $r = $model->save(base(['vattype' => 3, 'vat' => '16', 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '33.33'], ['pid' => $p3, 'qty' => '1', 'rate' => '0.10'], ['pid' => $p2, 'qty' => '3', 'rate' => '0.10']]]));
    $i4 = (int)$r['id']; $h = hdr($i4);
    ok('VAT exclusive rounding per line: 38.66 / 0.12 / 0.35, debit 39.13, header = sum of lines', $r['success'] && lines(14, $i4) === ["$ra|DEBIT|39.13", "$incA|CREDIT|38.66", "$incA|CREDIT|0.12", "$incB|CREDIT|0.35"] && $h['inclusiveVat'] === '39.13' && $h['vat'] === '5.40' && $h['exclusiveVat'] === '33.73', errs($r) . json_encode(lines(14, $i4)));
    $r = $model->save(base(['vattype' => 2, 'vat' => '16', 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '0.99'], ['pid' => $p1, 'qty' => '7', 'rate' => '1.13'], ['pid' => $p2, 'qty' => '1', 'rate' => '1234567.89']]]));
    $i5 = (int)$r['id']; $h = hdr($i5);
    $tot = one("SELECT SUM(IF(l.dc='DEBIT',l.amount,0)) d, SUM(IF(l.dc='CREDIT',l.amount,0)) c FROM journal_lines l JOIN journal_entries e ON e.ID=l.journalEntryId WHERE e.transactionTypeId=14 AND e.transactionId=? AND e.deleted=0", [$i5]);
    ok('VAT inclusive with awkward amounts: the entry balances to the cent and equals the header total', $r['success'] && $tot['d'] === $tot['c'] && $tot['c'] === $h['inclusiveVat'] && number_format($h['exclusiveVat'] + $h['vat'], 2, '.', '') === $h['inclusiveVat'], json_encode([$tot, $h]));
    $r = $model->save(base(['receivable' => $rDeep, 'invoiceno' => '99999', 'totals' => 5, 'table' => [['pid' => $p3, 'qty' => '1', 'rate' => '10.00', 'gross' => '999999', 'account' => 51]]]));
    $i6 = (int)$r['id'];
    ok('client totals, gross, line account and invoice number are ignored; a deeper receivable account is stored', $r['success'] && hdr($i6)['inclusiveVat'] === '10.00' && lines(14, $i6) === ["$rDeep|DEBIT|10.00", "$incA|CREDIT|10.00"] && (int)hdr($i6)['invoiceNo'] === $seq0 + 5);
    ok('invoice numbers are consecutive from the locked sequence', array_map(function($i) { return (int)hdr($i)['invoiceNo']; }, [$i1, $i2, $i3, $i4, $i5, $i6]) === range($seq0, $seq0 + 5));

    // ------------------------------------------------------------ create refusals change nothing
    $before = dbstate(); $seqRow = cnt("SELECT COALESCE(MAX(lastNo),0) FROM receipt_number_seq WHERE congregationId=11 AND kind='custinv'");
    $refuse = [
        ['receivable = 9 (reporting)', ['receivable' => 9], 'receivable'], ['receivable = reporting parent', ['receivable' => $rParent], 'receivable'], ['receivable inactive', ['receivable' => $rInactive], 'inactive'],
        ['receivable of another congregation', ['receivable' => $rForeign], 'another congregation'], ['receivable deleted', ['receivable' => $rDeleted], 'deleted'], ['receivable a bank', ['receivable' => 105], 'receivable'],
        ['receivable a payable account', ['receivable' => 38], 'receivable'], ['receivable an income account', ['receivable' => 90], 'receivable'], ['receivable missing', ['receivable' => ''], 'receivable'], ['receivable text', ['receivable' => 'x'], 'receivable'], ['receivable array', ['receivable' => [1]], 'receivable'],
        ['customer of another congregation', ['customer' => $cForeign], 'customer'], ['deleted customer', ['customer' => $cDeleted], 'customer'], ['unknown customer', ['customer' => 999999], 'customer'], ['missing customer', ['customer' => ''], 'customer'],
        ['invalid date', ['idate' => '2026-02-30'], 'date'], ['future invoice date', ['idate' => date('Y-m-d', strtotime('+1 day'))], 'future'], ['closed-year date', ['idate' => $closedDate], 'closed'], ['no financial year', ['idate' => $noYear], 'financial year'],
        ['due before invoice', ['ddate' => '2000-01-01'], 'due date'], ['bad due date', ['ddate' => 'x'], 'due date'], ['vat type 9', ['vattype' => 9], 'vat'], ['vat missing for inclusive', ['vattype' => 2, 'vat' => ''], 'vat'], ['vat rate not in the table', ['vattype' => 3, 'vat' => '17'], 'vat'], ['vat 14%', ['vattype' => 3, 'vat' => '14'], 'vat'],
        ['no lines', ['table' => []], 'products'], ['lines not an array', ['table' => 'x'], 'products'],
        ['expense product', ['table' => [['pid' => $pExp, 'qty' => '1', 'rate' => '1']]], 'account'], ['asset product', ['table' => [['pid' => $pAsset, 'qty' => '1', 'rate' => '1']]], 'account'], ['reporting-account product', ['table' => [['pid' => $pRep, 'qty' => '1', 'rate' => '1']]], 'account'],
        ['bank-account product', ['table' => [['pid' => $pBank, 'qty' => '1', 'rate' => '1']]], 'account'], ['inactive-account product', ['table' => [['pid' => $pInact, 'qty' => '1', 'rate' => '1']]], 'account'],
        ['foreign-account product', ['table' => [['pid' => $pForeignAcct, 'qty' => '1', 'rate' => '1']]], 'account'], ['deleted-account product', ['table' => [['pid' => $pDelAcct, 'qty' => '1', 'rate' => '1']]], 'account'],
        ['retired product 1', ['table' => [['pid' => 1, 'qty' => '1', 'rate' => '1']]], 'product'], ['retired product 2', ['table' => [['pid' => 2, 'qty' => '1', 'rate' => '1']]], 'product'], ['deleted product', ['table' => [['pid' => $pDel, 'qty' => '1', 'rate' => '1']]], 'product'],
        ['product of another congregation', ['table' => [['pid' => $pForeignProd, 'qty' => '1', 'rate' => '1']]], 'product'], ['unknown product', ['table' => [['pid' => 999999, 'qty' => '1', 'rate' => '1']]], 'product'], ['text product', ['table' => [['pid' => 'x', 'qty' => '1', 'rate' => '1']]], 'product'],
        ['quantity 0', ['table' => [['pid' => $p1, 'qty' => '0', 'rate' => '1']]], 'quantity'], ['quantity negative', ['table' => [['pid' => $p1, 'qty' => '-1', 'rate' => '1']]], 'quantity'], ['quantity 3 decimals', ['table' => [['pid' => $p1, 'qty' => '1.005', 'rate' => '1']]], 'quantity'],
        ['quantity text', ['table' => [['pid' => $p1, 'qty' => 'abc', 'rate' => '1']]], 'quantity'], ['quantity array', ['table' => [['pid' => $p1, 'qty' => [1], 'rate' => '1']]], 'quantity'], ['quantity huge', ['table' => [['pid' => $p1, 'qty' => '99999999', 'rate' => '1']]], 'quantity'],
        ['rate 0', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '0']]], 'rate'], ['rate negative', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '-5']]], 'rate'], ['rate 3 decimals', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1.001']]], 'rate'],
        ['rate exponent', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1e3']]], 'rate'], ['rate huge', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '99999999999999']]], 'rate'], ['amount rounds to zero', ['table' => [['pid' => $p1, 'qty' => '0.01', 'rate' => '0.01']]], 'at least'],
        ['description over 255', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1', 'desc' => str_repeat('d', 256)]]], 'description'],
        ['total out of range', ['table' => [['pid' => $p1, 'qty' => '1000000', 'rate' => '99999999.99'], ['pid' => $p1, 'qty' => '1000000', 'rate' => '99999999.99']]], 'range'], ['201 lines', ['table' => array_fill(0, 201, ['pid' => $p1, 'qty' => '1', 'rate' => '1'])], 'more than'],
    ];
    foreach ($refuse as [$label, $over, $needle]) {
        $r = $model->save(base($over));
        ok("create refused: $label", !$r['success'] && stripos(errs($r), $needle) !== false, errs($r));
    }
    ok('create refusals wrote nothing and burned no invoice number', dbstate() === $before && cnt("SELECT COALESCE(MAX(lastNo),0) FROM receipt_number_seq WHERE congregationId=11 AND kind='custinv'") === $seqRow);
    $pdo->prepare('UPDATE tblaccounttypes SET active=0 WHERE ID=?')->execute([$incB]);
    $before = dbstate();
    $r = $model->save(base(['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '5'], ['pid' => $p2, 'qty' => '1', 'rate' => '5']]]));
    $pdo->prepare('UPDATE tblaccounttypes SET active=1 WHERE ID=?')->execute([$incB]);
    ok('a line whose income account was deactivated refuses the whole invoice', !$r['success'] && dbstate() === $before, errs($r));

    // ------------------------------------------------------------ empty receivables list
    $cand = array_column(Ledger::listReceivableAccounts($pdo, 11), 'ID');
    foreach ($cand as $a) { $pdo->prepare('UPDATE tblaccounttypes SET active=0 WHERE ID=?')->execute([$a]); }
    $before = dbstate();
    $r = $model->save(base());
    ok('an empty receivables list refuses the save with a clear message', has($r, 'No receivables account') && dbstate() === $before && !$model->getReceivableAccounts(), errs($r));
    foreach ($cand as $a) { $pdo->prepare('UPDATE tblaccounttypes SET active=1 WHERE ID=?')->execute([$a]); }
    ok('...and works again when the list is back', $model->save(base())['success']);

    // ------------------------------------------------------------ edit in place
    $oldEntry = one('SELECT ID FROM journal_entries WHERE transactionTypeId=14 AND transactionId=? AND deleted=0', [$i1])['ID'];
    $pdo->prepare('UPDATE tblproducts SET accountId=? WHERE ID=?')->execute([$incB, $p1]);
    $r = $model->save(base(['id' => $i1, 'isedit' => true, 'table' => [['pid' => $p1, 'qty' => '2', 'rate' => '1000.00', 'desc' => 'First'], ['pid' => $p2, 'qty' => '1', 'rate' => '500.50']]]));
    ok('edit with the same lines keeps the account snapshot although the product\'s account changed; the number is unchanged', $r['success'] && lines(14, $i1) === ["$ra|DEBIT|2500.50", "$incA|CREDIT|2000.00", "$incB|CREDIT|500.50"] && (int)hdr($i1)['invoiceNo'] === $seq0, errs($r));
    $r = $model->save(base(['id' => $i1, 'isedit' => true, 'receivable' => $rDeep, 'vattype' => 3, 'vat' => '16', 'ddate' => date('Y-m-d', strtotime('+30 days')), 'table' => [['pid' => $p1, 'qty' => '3', 'rate' => '100.00'], ['pid' => $p3, 'qty' => '1', 'rate' => '50.00']]]));
    $h = hdr($i1); $newEntry = one('SELECT ID FROM journal_entries WHERE transactionTypeId=14 AND transactionId=? AND deleted=0', [$i1])['ID'];
    ok('edit: the same header and the SAME entry are updated in place', $r['success'] && $newEntry === $oldEntry && cnt('SELECT COUNT(*) FROM journal_entries WHERE transactionTypeId=14 AND transactionId=?', [$i1]) === 1 && cnt('SELECT COUNT(*) FROM tblinvoice_header WHERE ID=?', [$i1]) === 1, errs($r));
    ok('edit: new line account snapshot, VAT, receivable and totals', lines(14, $i1) === ["$rDeep|DEBIT|406.00", "$incB|CREDIT|348.00", "$incA|CREDIT|58.00"] && $h['inclusiveVat'] === '406.00' && $h['vat'] === '56.00' && (int)$h['ReceivableAccountId'] === $rDeep && cnt('SELECT COUNT(*) FROM tblinvoice_details WHERE header_id=?', [$i1]) === 2, json_encode(lines(14, $i1)));
    $pdo->prepare('UPDATE tblproducts SET accountId=? WHERE ID=?')->execute([$incA, $p1]);
    $before = dbstate();
    foreach ([['into a closed year', ['idate' => $closedDate, 'ddate' => $today], 'closed'], ['future date', ['idate' => date('Y-m-d', strtotime('+1 day')), 'ddate' => '2999-01-01'], 'future'], ['receivable 9', ['receivable' => 9], 'receivable'],
              ['customer of another congregation', ['customer' => $cForeign], 'customer'], ['expense product', ['table' => [['pid' => $pExp, 'qty' => '1', 'rate' => '1']]], 'account']] as [$label, $over, $needle]) {
        $r = $model->save(base(array_merge(['id' => $i1, 'isedit' => true], $over)));
        ok("edit refused: $label", !$r['success'] && stripos(errs($r), $needle) !== false, errs($r));
    }
    ok('refused edits changed nothing', dbstate() === $before);
    foreach ([['unknown id', 999999], ['junk id', 'x'], ['empty id', ''], ['injection id', '1 OR 1=1']] as [$label, $id]) {
        $r = $model->save(base(['id' => $id, 'isedit' => true]));
        ok("edit refused: $label", has($r, 'not found'), errs($r));
    }
    $pdo->prepare('INSERT INTO tblinvoice_header(invoiceDate,duedate,customerId,invoiceNo,fiscalYearId,vattype,ReceivableAccountId,exclusiveVat,vat,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,500,25,1,?,10,0,10,64,2)')->execute([$today, $today, $cForeign, $rForeign]);
    $foreignInv = (int)$pdo->lastInsertId();
    $before = dbstate();
    $r = $model->save(base(['id' => $foreignInv, 'isedit' => true]));
    ok('edit refused: invoice of another congregation (forged ID)', has($r, 'not found') && dbstate() === $before);
    ok('lookups by ID are congregation-scoped: header, pay form, customer, print info, product rate', $model->getInvoiceHeader(encryptId($foreignInv)) === false && $model->fillInvoiceDetails(encryptId($foreignInv)) === false && $model->getCustomerDetails($cForeign) === false && $model->getCustomernInfo($cForeign) === false && (new Product)->GetRate($pForeignProd) === false);
    ok('lookups of an own invoice work', $model->getInvoiceHeader(encryptId($i1)) && $model->fillInvoiceDetails(encryptId($i1)) && $model->getCustomerDetails($ca));

    // closed-year lock on the OLD date of a stored invoice
    $r = $model->save(base()); $ic = (int)$r['id'];
    $pdo->prepare('UPDATE tblinvoice_header SET invoiceDate=? WHERE ID=?')->execute([$closedDate, $ic]);
    $pdo->prepare('UPDATE journal_entries SET entryDate=? WHERE transactionTypeId=14 AND transactionId=?')->execute([$closedDate, $ic]);
    $before = dbstate();
    $r = $model->save(base(['id' => $ic, 'isedit' => true]));
    ok('edit refused when the stored invoice is in a closed year (old date)', has($r, 'closed') && dbstate() === $before, errs($r));
    $r = $model->payment(['id' => $ic, 'paydate' => $today, 'amount' => '1', 'paymethod' => 1, 'cashaccount' => 34]);
    ok('...but it can still be paid (the payment is dated in an open year)', $r['success'], errs($r));

    // roles
    $r = $model->save(base()); $ir = (int)$r['id'];
    $_SESSION['userType'] = 4; $_SESSION['userId'] = 98;
    $r = $model->save(base(['id' => $ir, 'isedit' => true]));
    ok('role: a type 4 user cannot edit an invoice posted by someone else', has($r, 'your own'));
    $r = $model->save(base()); $imine = (int)$r['id'];
    $r = $model->save(base(['id' => $imine, 'isedit' => true, 'ddate' => date('Y-m-d', strtotime('+10 days'))]));
    ok('role: a type 4 user can edit their own unpaid invoice; the poster stays', $r['success'] && (int)hdr($imine)['postedBy'] === 98);
    $_SESSION['userType'] = 1; $_SESSION['userId'] = 64;

    // Invoice.php:193 regression: the old update deleted type 6 (a SUPPLIER invoice) with the same numeric ID
    $r = $model->save(base()); $ireg = (int)$r['id'];
    $pdo->beginTransaction();
    Ledger::post($pdo, ['congregationId' => 11, 'entryDate' => $today, 'transactionTypeId' => 6, 'transactionId' => $ireg, 'reference' => 'zz-supplier'], [Ledger::debit(51, '7.00'), Ledger::credit(38, '7.00')]);
    $pdo->commit();
    $supEntry = one('SELECT ID,reference FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$ireg]);
    $supMirror = cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=6 AND transactionId=? AND deleted=0', [$ireg]);
    $r = $model->save(base(['id' => $ireg, 'isedit' => true, 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '33.00']]]));
    ok('Invoice.php:193 regression: updating customer invoice N leaves the SUPPLIER entry of the same numeric ID untouched', $r['success']
        && one('SELECT ID,reference FROM journal_entries WHERE transactionTypeId=6 AND transactionId=? AND deleted=0', [$ireg]) == $supEntry
        && cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=6 AND transactionId=? AND deleted=0', [$ireg]) === $supMirror && $supMirror === 2
        && lines(14, $ireg) === ["$ra|DEBIT|33.00", "$incA|CREDIT|33.00"] && cnt('SELECT COUNT(*) FROM tblledger WHERE transactionType=14 AND transactionId=? AND deleted=0', [$ireg]) === 2, errs($r));

    // ------------------------------------------------------------ payments (type 16)
    $r = $model->save(base(['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.00']]])); $pi = (int)$r['id'];
    $pay = ['id' => $pi, 'paydate' => $today, 'amount' => '300.25', 'paymethod' => 1, 'cashaccount' => 34, 'bank' => '', 'reference' => ''];
    $r = $model->payment($pay); $pid1 = (int)($r['id'] ?? 0);
    ok('cash payment succeeds', $r['success'] && $pid1 > 0, errs($r));
    $pr = one('SELECT * FROM tblinvoice_payments WHERE ID=?', [$pid1]);
    ok('cash payment row: money account stored, no bank, exact decimal', (int)$pr['PaymentAccountId'] === 34 && $pr['bankId'] === null && $pr['amount'] === '300.25' && (int)$pr['paymentId'] === 1);
    ok('cash payment entry (type 16, keyed on the payment row): Dr the cash account / Cr the invoice\'s stored receivable', lines(16, $pid1) === ['34|DEBIT|300.25', "$ra|CREDIT|300.25"], json_encode(lines(16, $pid1)));
    ok('cash payment creates no bank row; status is partially paid', cnt('SELECT COUNT(*) FROM tblbankpostings WHERE transactionType=16 AND transactionId=?', [$pid1]) === 0 && (int)hdr($pi)['status'] === 1);
    $depositId = (int)one("SELECT ID FROM tbltransactionmethods WHERE methodName='Deposit'")['ID'];
    foreach ([[2, 'MPESA-1', '100.00'], [3, 'CHQ-1', '50.00'], [4, 'BANK-1', '25.00']] as [$method, $ref, $amt]) {
        $r = $model->payment(['id' => $pi, 'paydate' => $today, 'amount' => $amt, 'paymethod' => $method, 'cashaccount' => '', 'bank' => 105, 'reference' => $ref]);
        $tid = (int)($r['id'] ?? 0);
        $bk = rows('SELECT * FROM tblbankpostings WHERE transactionType=16 AND transactionId=? AND deleted=0', [$tid]);
        ok("method $method: Dr the bank / Cr the stored receivable, ONE deposit bank row (debit side, uncleared) with the reference", $r['success'] && lines(16, $tid) === ["105|DEBIT|$amt", "$ra|CREDIT|$amt"]
            && count($bk) === 1 && (float)$bk[0]['debit'] === (float)$amt && (float)$bk[0]['credit'] === 0.0 && (int)$bk[0]['bankId'] === 105 && (int)$bk[0]['cleared'] === 0 && $bk[0]['reference'] === strtolower($ref) && (int)$bk[0]['transactionMethod'] === $depositId, errs($r));
        $prow = one('SELECT PaymentAccountId a, bankId b FROM tblinvoice_payments WHERE ID=?', [$tid]);
        ok("method $method: the chosen bank is stored as the payment account", (int)$prow['a'] === 105 && (int)$prow['b'] === 105);
    }
    ok('paid so far 475.25: still partially paid', (int)hdr($pi)['status'] === 1);
    $r = $model->payment(['id' => $pi, 'paydate' => $today, 'amount' => '524.75', 'paymethod' => 1, 'cashaccount' => 98]);
    ok('paying the exact balance (524.75) sets the status to paid; petty cash is a valid cash account', $r['success'] && (int)hdr($pi)['status'] === 2, errs($r));
    $before = dbstate();
    $r = $model->payment($pay + []); $r2 = $model->payment(array_merge($pay, ['amount' => '0.01']));
    ok('overpayment of a paid invoice (one cent) is refused', has($r2, 'overpayment') && dbstate() === $before, errs($r2));
    $r = $model->save(base(['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '10.00']]])); $pj = (int)$r['id'];
    $r = $model->payment(array_merge($pay, ['id' => $pj, 'amount' => '10.01']));
    ok('one cent over the balance is refused', has($r, 'overpayment'));
    $refusePay = [
        ['method 9', ['paymethod' => 9], 'method'], ['method text', ['paymethod' => 'x'], 'method'], ['method missing', ['paymethod' => ''], 'method'],
        ['cash without an account', ['cashaccount' => ''], 'cash account'], ['cash with a bank account', ['cashaccount' => 105], 'bank account'], ['cash with a non-cash account', ['cashaccount' => 52], 'cash'], ['cash with an unknown account', ['cashaccount' => 999999], 'exist'],
        ['cheque without a bank', ['paymethod' => 3, 'cashaccount' => '', 'bank' => '', 'reference' => 'R'], 'bank'], ['cheque with a cash account as bank', ['paymethod' => 3, 'bank' => 34, 'reference' => 'R'], 'not a bank'],
        ['bank of another congregation', ['paymethod' => 4, 'bank' => 101, 'reference' => 'R'], 'another congregation'], ['bank method without a reference', ['paymethod' => 4, 'bank' => 105, 'reference' => ''], 'reference'],
        ['reference over 50', ['paymethod' => 4, 'bank' => 105, 'reference' => str_repeat('r', 51)], '50'], ['future date', ['paydate' => date('Y-m-d', strtotime('+1 day'))], 'greater'], ['invalid date', ['paydate' => '2026-02-30'], 'date'],
        ['closed-year date', ['paydate' => $closedDate], 'closed'], ['no financial year', ['paydate' => $noYear], 'financial year'], ['amount 0', ['amount' => '0'], 'amount'], ['negative amount', ['amount' => '-5'], 'amount'],
        ['3 decimals', ['amount' => '1.005'], 'decimal'], ['amount text', ['amount' => 'x'], 'amount'], ['amount array', ['amount' => [1]], 'amount'], ['unknown invoice', ['id' => 999999], 'not found'], ['text invoice', ['id' => 'x'], 'invoice'],
        ['invoice of another congregation (forged ID)', ['id' => $foreignInv], 'not found'],
    ];
    foreach ($refusePay as [$label, $over, $needle]) {
        $b4 = dbstate();
        $r = $model->payment(array_merge($pay, ['id' => $pj, 'amount' => '1.00'], $over));
        ok("payment refused: $label", !$r['success'] && stripos(errs($r), $needle) !== false && dbstate() === $b4, errs($r));
    }
    // a legacy invoice without a stored receivable account
    $pdo->prepare('INSERT INTO tblinvoice_header(invoiceDate,duedate,customerId,invoiceNo,fiscalYearId,vattype,exclusiveVat,vat,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,880002,25,1,500,0,500,64,11)')->execute([$today, $today, $ca]);
    $legacy = (int)$pdo->lastInsertId();
    $b4 = dbstate();
    $r = $model->payment(array_merge($pay, ['id' => $legacy, 'amount' => '10']));
    ok('a legacy invoice without a receivable account cannot be paid (clear message, nothing written)', has($r, 'no receivable account') && dbstate() === $b4, errs($r));
    ok('dev has no legacy customer invoice without a receivable account (the 0 reported)', cnt('SELECT COUNT(*) FROM tblinvoice_header WHERE ReceivableAccountId IS NULL AND ID<=?', [$max['tblinvoice_header']]) === 0);
    $r = $model->save(base(['id' => $legacy, 'isedit' => true, 'receivable' => $ra, 'invoiceno' => '880002', 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '500.00']]]));
    ok('an unpaid legacy invoice can be edited: the user picks the receivable account and the entry is created', $r['success'] && (int)hdr($legacy)['ReceivableAccountId'] === $ra && lines(14, $legacy) === ["$ra|DEBIT|500.00", "$incA|CREDIT|500.00"], errs($r));
    $_SESSION['congId'] = 2;
    $b4 = dbstate();
    $r = $model->payment(array_merge($pay, ['id' => $pi, 'amount' => '1']));
    ok('another congregation cannot pay an invoice of congregation 11 (forged ID)', has($r, 'not found') && dbstate() === $b4);
    $_SESSION['congId'] = 11;
    ok('there is no payment edit or delete in the model', !method_exists($model, 'deletePayment') && !method_exists($model, 'updatePayment'));

    // decision 82: invoice with payments
    $before = dbstate();
    $same = ['id' => $pi, 'isedit' => true, 'table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.00']]];
    foreach ([['the date', ['idate' => date('Y-m-d', strtotime('-1 day'))], 'date'], ['the customer', ['customer' => $cb], 'customer'], ['the receivable account', ['receivable' => $rDeep], 'receivable account'],
              ['a quantity', ['table' => [['pid' => $p1, 'qty' => '2', 'rate' => '500.00']]], 'lines'], ['a rate (higher total)', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.01']]], 'lines'],
              ['the product', ['table' => [['pid' => $p2, 'qty' => '1', 'rate' => '1000.00']]], 'lines'], ['an added line', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.00'], ['pid' => $p3, 'qty' => '1', 'rate' => '1.00']]], 'lines'],
              ['a line description', ['table' => [['pid' => $p1, 'qty' => '1', 'rate' => '1000.00', 'desc' => 'Changed']]], 'line descriptions'],
              ['the VAT type', ['vattype' => 3, 'vat' => '16'], 'VAT']] as [$label, $over, $needle]) {
        $r = $model->save(base(array_merge($same, $over)));
        ok("invoice with payments: changing $label is refused", !$r['success'] && stripos(errs($r), 'has payments') !== false && stripos(errs($r), $needle) !== false, errs($r));
    }
    ok('refused edits of the paid invoice changed nothing', dbstate() === $before);
    $entryBefore = one('SELECT ID FROM journal_entries WHERE transactionTypeId=14 AND transactionId=? AND deleted=0', [$pi])['ID'];
    $r = $model->save(base(array_merge($same, ['ddate' => date('Y-m-d', strtotime('+45 days'))])));
    ok('invoice with payments: the due date alone can still be changed (same entry, same lines, status kept)', $r['success'] && hdr($pi)['duedate'] === date('Y-m-d', strtotime('+45 days')) && one('SELECT description d FROM tblinvoice_details WHERE header_id=?', [$pi])['d'] === null
        && one('SELECT ID FROM journal_entries WHERE transactionTypeId=14 AND transactionId=? AND deleted=0', [$pi])['ID'] === $entryBefore && lines(14, $pi) === ["$ra|DEBIT|1000.00", "$incA|CREDIT|1000.00"] && (int)hdr($pi)['status'] === 2, errs($r));

    // ------------------------------------------------------------ number sequence per congregation
    $_SESSION['congId'] = 2;
    $pdo->prepare('INSERT INTO tblinvoice_header(invoiceDate,duedate,customerId,invoiceNo,fiscalYearId,vattype,ReceivableAccountId,exclusiveVat,vat,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,700,25,1,?,10,0,10,64,2)')->execute([$today, $today, $cForeign, $rForeign]);
    ok('a congregation\'s next number starts above its highest stored number (701 after a stored 700), not from a count', $model->peekInvoiceNo() === 701);
    $_SESSION['congId'] = 11;
    $last = (int)one('SELECT MAX(invoiceNo) m FROM tblinvoice_header WHERE congregationId=11')['m'];
    $r = $model->save(base());
    ok('congregation 11 keeps its own sequence', $r['success'] && (int)hdr((int)$r['id'])['invoiceNo'] === $last + 1, errs($r));

    // ------------------------------------------------------------ reports / views / routines
    $v = one('SELECT * FROM vw_invoices WHERE ID=?', [$pi]);
    ok('vw_invoices carries the congregation and the balance (paid in full: 0)', (int)$v['congregationId'] === 11 && (float)$v['balance'] === 0.0 && (int)$v['status'] === 2);
    $idx = array_map(function($o) { return (int)$o->ID; }, $model->index());
    ok('Invoice::index lists only the session congregation\'s invoices', in_array($pi, $idx) && !in_array($foreignInv, $idx));
    ok('getAmountPaid keeps the cents (it used to round to whole units)', rows('SELECT getAmountPaid(?) a', [$pi])[0]['a'] === '1000.00' && rows('SELECT getAmountPaid(?) a', [$pj])[0]['a'] === '0.00');
    $due = rows('CALL sp_getinvoice_withbalances(?,?,?)', [11, '2000-01-01', '2999-12-31']);
    $dueIds = array_column($due, 'ID');
    ok('sp_getinvoice_withbalances uses its congregation parameter (it ignored it before)', in_array($pj, $dueIds) && !in_array($foreignInv, $dueIds) && count(rows('CALL sp_getinvoice_withbalances(?,?,?)', [2, '2000-01-01', '2999-12-31'])) >= 1 && !in_array($pj, array_column(rows('CALL sp_getinvoice_withbalances(?,?,?)', [2, '2000-01-01', '2999-12-31']), 'ID')));
    ok('sp_getinvoice_fullypaid and sp_getinvoice_due use the congregation', in_array($pi, array_column(rows('CALL sp_getinvoice_fullypaid(?,?,?)', [11, '2000-01-01', '2999-12-31']), 'ID')) && !in_array($pi, array_column(rows('CALL sp_getinvoice_fullypaid(?,?,?)', [2, '2000-01-01', '2999-12-31']), 'ID')) && !in_array($foreignInv, array_column(rows('CALL sp_getinvoice_due(?)', [11]), 'ID')));
    ok('sp_getcustomerpayments takes the congregation', count(rows('CALL sp_getcustomerpayments(?,?,?,?)', [$ca, '2000-01-01', '2999-12-31', 11])) >= 5 && count(rows('CALL sp_getcustomerpayments(?,?,?,?)', [$ca, '2000-01-01', '2999-12-31', 2])) === 0);

    // dev legacy mirror
    $bad = cnt("SELECT COUNT(*) FROM journal_entries e WHERE e.deleted=0 AND e.transactionTypeId IN (14,16) AND e.ID>? AND NOT EXISTS (SELECT 1 FROM tblledger l WHERE l.transactionType=e.transactionTypeId AND l.transactionId=e.transactionId AND l.congregationId=e.congregationId AND l.deleted=0)", [$max['journal_entries']]);
    ok('dev legacy mirror: every type 14 / 16 entry made here has its tblledger rows', $bad === 0);
    $unb = cnt("SELECT COUNT(*) FROM (SELECT transactionType,transactionId FROM tblledger WHERE deleted=0 AND transactionType IN (14,16) AND ID>? GROUP BY transactionType,transactionId HAVING ROUND(SUM(debit)-SUM(credit),2)<>0) x", [$max['tblledger']]);
    ok('dev legacy mirror: every mirrored transaction balances', $unb === 0);
    ok('no journal line made here has a zero or negative amount', cnt('SELECT COUNT(*) FROM journal_lines WHERE ID>? AND amount<=0', [$max['journal_lines']]) === 0);
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (array_reverse($tables, true) as $t => $k) { $pdo->exec("DELETE FROM $t WHERE $k>" . $max[$t]); }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec('DELETE FROM receipt_number_seq');
    $ins = $pdo->prepare('INSERT INTO receipt_number_seq(congregationId,kind,fiscalYearId,lastNo) VALUES(?,?,?,?)');
    foreach ($seqs as $s) { $ins->execute([$s['congregationId'], $s['kind'], $s['fiscalYearId'], $s['lastNo']]); }
    $st = $pdo->prepare('UPDATE tblfiscalyears SET closed=?,deleted=? WHERE ID=?');
    foreach ($years as $y) { $st->execute([$y['closed'], $y['deleted'], $y['ID']]); }
    $st = $pdo->prepare('UPDATE tblaccounttypes SET active=? WHERE ID=?');
    foreach ($activeBefore as $id => $a) { if ($id <= $max['tblaccounttypes']) { $st->execute([$a, $id]); } }
    $pdo->exec('UPDATE tblproducts SET deleted=1 WHERE ID IN (1,2)');
}
$left = 0;
foreach ($tables as $t => $k) { $left += (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $k>" . $max[$t])->fetchColumn(); }
$actDiff = 0; foreach ($pdo->query('SELECT ID,active FROM tblaccounttypes WHERE ID<=' . $max['tblaccounttypes'])->fetchAll(PDO::FETCH_KEY_PAIR) as $id => $a) { if ((int)$activeBefore[$id] !== (int)$a) { $actDiff++; } }
ok('cleanup: no row above the starting IDs remains and every account flag is as it was', $left === 0 && $actDiff === 0);
echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);

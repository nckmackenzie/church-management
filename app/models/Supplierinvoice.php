<?php

/**
 * Supplier invoices (transaction type 6), task 8.7.
 *
 * Posting: Dr the account of each line (snapshotted in tblinvoice_details_suppliers.AccountId from the product, expense or
 * non-bank asset posting accounts), Cr the crediting account chosen on the form (stored in
 * tblinvoice_header_suppliers.PayableAccountId, any posting account under 23). transactionId = the header ID.
 *
 * VAT (unchanged since the legacy code, no VAT account): the VAT is part of what is debited. Per line, with G = qty x rate
 * rounded to the cent: type 1 (no VAT) inclusive = G; type 2 (inclusive) inclusive = G, VAT = G x 16 / 116; type 3
 * (exclusive) VAT = G x 16 / 100, inclusive = G + VAT. Every VAT is rounded to the cent per line (half up) and the header
 * holds the sums of the lines, so the entry (Dr each line's inclusive amount, Cr the sum) balances to the cent.
 */
class Supplierinvoice
{
    const TYPE_INVOICE = 6;
    /** the only VAT rate the invoice maths has ever supported (calculateVat() in url_helper.php) */
    const VAT_RATE = 16;
    const MAX_LINES = 200;
    /** largest invoice total in cents (DECIMAL(18,2) holds far more; this keeps every product below in integer range) */
    const MAX_CENTS = 9999999999999;
    /** root account types a line may be debited to: expense, asset (non-bank) */
    const LINE_TYPES = [2, 3];

    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    // -----------------------------------------------------------------
    // Lists
    // -----------------------------------------------------------------

    public function index()
    {
        $this->db->query('CALL spGetInvoices_suppliers(:congid)');
        $this->db->bind(':congid',(int)$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function getSuppliers()
    {
        $this->db->query('SELECT ID,
                                 UCASE(supplierName) as supplierName
                          FROM   tblsuppliers
                          WHERE  (deleted=0) AND (congregationId = :cid)
                          ORDER BY supplierName');
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->resultSet();
    }

    /** Products a new line may use: the congregation's own, not deleted (retired), whose account is a valid line account. */
    public function getProducts()
    {
        $valid = array_flip(array_column($this->lineAccounts(),'ID'));
        $this->db->query('SELECT ID,
                                 UCASE(productName) as productName,
                                 accountId
                          FROM   tblproducts
                          WHERE  (deleted=0) AND (congregationId = :cid)
                          ORDER BY productName');
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return array_values(array_filter($this->db->resultSet(),function($p) use ($valid){
            return isset($valid[(int)$p->accountId]);
        }));
    }

    public function getVats()
    {
        $this->db->query('SELECT ID,
                                 rate,
                                 UCASE(vatName) as vatName
                          FROM tblvats WHERE (deleted=0) AND (active=1)');
        return $this->db->resultSet();
    }

    /** The crediting accounts to offer: posting accounts under 23 (Ledger::listPayableAccounts, the list the server validates against). */
    public function getPayableAccounts(): array
    {
        return array_map(function($a){ return (object)$a; },Ledger::listPayableAccounts($this->db->dbh,(int)$_SESSION['congId']));
    }

    /** The preselected crediting account ('accounts payable', F15) or null; never hard-coded. */
    public function getDefaultPayable(): ?int
    {
        return Ledger::accountsPayableId($this->db->dbh);
    }

    /**
     * Accounts a line may be debited to (and the product modal offers): active, visible posting accounts of the Expense
     * or Asset root, banks excluded. Returns [ ['ID'=>, 'accountType'=>], ... ].
     */
    public function lineAccounts(): array
    {
        $cong = (int)$_SESSION['congId'];
        $st = $this->db->dbh->prepare('SELECT ID FROM tblaccounttypes
                                       WHERE deleted = 0 AND active = 1 AND isBank = 0 AND accountTypeId IN (' . implode(',',self::LINE_TYPES) . ')
                                         AND congregationId IN (0,?)');
        $st->execute([$cong]);
        $ids = Ledger::filterPostingAccounts($this->db->dbh,$cong,array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));
        if (!$ids) { return []; }
        $st = $this->db->dbh->prepare('SELECT ID, UCASE(accountType) AS accountType FROM tblaccounttypes WHERE ID IN (' . implode(',',array_fill(0,count($ids),'?')) . ') ORDER BY accountType');
        $st->execute($ids);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Why $accountId may not be a line's debit account, or null. */
    public function lineAccountProblem(int $accountId, bool $allowInactive = false): ?string
    {
        $pdo = $this->db->dbh;
        if (($why = Ledger::postingProblem($pdo,$accountId,(int)$_SESSION['congId'],$allowInactive)) !== null) { return $why; }
        $st = $pdo->prepare('SELECT accountType, accountTypeId, isBank FROM tblaccounttypes WHERE ID = ?');
        $st->execute([$accountId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if ((int)$a['isBank'] === 1) { return "'{$a['accountType']}' is a bank account"; }
        if (!in_array((int)$a['accountTypeId'],self::LINE_TYPES,true)) { return "'{$a['accountType']}' is not an expense or asset account"; }
        return null;
    }

    // -----------------------------------------------------------------
    // Lookups (always the session congregation)
    // -----------------------------------------------------------------

    public function getSupplierDetails($id)
    {
        if (!ctype_digit((string)$id) || (int)$id <= 0) { return false; }
        $this->db->query('SELECT * FROM tblsuppliers WHERE (ID=:id) AND (congregationId=:cid) AND (deleted=0)');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    /**
     * A product of this congregation as the invoice form needs it: rate, account and whether a line may use it.
     * null for an unknown / foreign / retired product.
     */
    public function getProductDetails($id): ?array
    {
        if (!ctype_digit((string)$id) || (int)$id <= 0) { return null; }
        $st = $this->db->dbh->prepare('SELECT p.ID, p.productName, p.rate, p.accountId, UCASE(a.accountType) AS account
                                       FROM tblproducts p JOIN tblaccounttypes a ON a.ID = p.accountId
                                       WHERE p.ID = ? AND p.congregationId = ? AND p.deleted = 0');
        $st->execute([(int)$id,(int)$_SESSION['congId']]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) { return null; }
        $p['problem'] = $this->lineAccountProblem((int)$p['accountId']);
        return $p;
    }

    /** One account (ID, accountType, parentName) for a dropdown that must still show an account no longer on the list. */
    public function getPayableAccountRow(int $id)
    {
        $st = $this->db->dbh->prepare('SELECT a.ID, a.accountType, p.accountType AS parentName FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId WHERE a.ID = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_OBJ);
        return $r ?: null;
    }

    public function getVatId($rate)
    {
        $st = $this->db->dbh->prepare('SELECT ID FROM tblvats WHERE deleted = 0 AND active = 1 AND rate = ?');
        $st->execute([$rate]);
        return $st->fetchColumn();
    }

    /** New product from the invoice modal. Returns ['success','errors','id']. */
    public function SaveProduct($data): array
    {
        $pdo = $this->db->dbh;
        $name = strtolower(trim((string)($data['productname'] ?? '')));
        $errors = [];
        if ($name === '') { $errors[] = 'Enter the product name'; }
        elseif (strlen($name) > 255) { $errors[] = 'Product name cannot be longer than 255 characters'; }
        $desc = trim((string)($data['description'] ?? ''));
        if (strlen($desc) > 255) { $errors[] = 'Description cannot be longer than 255 characters'; }
        if (($e = Ledger::amountError($data['rate'] ?? '','Rate')) !== null) { $errors[] = $e; }
        elseif (self::cents(trim((string)$data['rate'])) > self::MAX_CENTS) { $errors[] = 'Rate is too large'; }
        $account = (string)($data['account'] ?? '');
        if (!ctype_digit($account) || (int)$account <= 0) { $errors[] = 'Select the G/L account'; }
        elseif (($why = $this->lineAccountProblem((int)$account)) !== null) { $errors[] = "G/L account: $why"; }
        if (!$errors) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM tblproducts WHERE productName = ? AND deleted = 0 AND congregationId = ?');
            $st->execute([$name,(int)$_SESSION['congId']]);
            if ((int)$st->fetchColumn() > 0) { $errors[] = 'A product with this name already exists'; }
        }
        if ($errors) { return ['success' => false,'errors' => $errors]; }
        try {
            $st = $pdo->prepare('INSERT INTO tblproducts (productName,`description`,rate,accountId,congregationId) VALUES(?,?,?,?,?)');
            $st->execute([$name,$desc !== '' ? strtolower($desc) : null,self::money(self::cents(trim((string)$data['rate']))),(int)$account,(int)$_SESSION['congId']]);
            return ['success' => true,'id' => (int)$pdo->lastInsertId()];
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Unable to save the product. Retry or contact admin']];
        }
    }

    // -----------------------------------------------------------------
    // Money helpers (integer cents; no float ever reaches the ledger)
    // -----------------------------------------------------------------

    /** '12.5' -> 1250. Only for strings already accepted by Ledger::amountError(). */
    private static function cents(string $s): int
    {
        $parts = explode('.',$s,2);
        return ((int)$parts[0]) * 100 + (int)str_pad(substr($parts[1] ?? '',0,2),2,'0');
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100,2,'.','');
    }

    /** A posted value as a trimmed string, or null when it is not a scalar (an array / object posted by hand). */
    private static function scalar($v): ?string
    {
        return is_scalar($v) || $v === null ? trim((string)$v) : null;
    }

    /** [exclusive, vat, inclusive] cents of one line gross for a VAT type (see the class comment). */
    private static function lineVat(int $vattype, int $gross): array
    {
        if ($vattype === 2) {
            $vat = intdiv($gross * self::VAT_RATE * 2 + (100 + self::VAT_RATE),2 * (100 + self::VAT_RATE));
            return [$gross - $vat,$vat,$gross];
        }
        if ($vattype === 3) {
            $vat = intdiv($gross * self::VAT_RATE * 2 + 100,200);
            return [$gross,$vat,$gross + $vat];
        }
        return [$gross,0,$gross];
    }

    // -----------------------------------------------------------------
    // Create / update
    // -----------------------------------------------------------------

    /**
     * Create or update (in place) a supplier invoice. $d: id, isedit, supplier, idate, ddate, vattype, vat (the VAT rate),
     * invoiceno, payable, table (list of ['pid','qty','rate']). Client totals are not accepted: lines, VAT and total are
     * recomputed here. Returns ['success'=>true,'id'=>ID] or ['success'=>false,'errors'=>[...]].
     */
    public function save(array $d): array
    {
        $pdo = $this->db->dbh;
        try {
            $in = $this->validateShape($d);
            $cong = (int)$_SESSION['congId'];
            $pdo->beginTransaction();

            // one invoice save at a time per congregation: the invoice number check below cannot race
            $pdo->prepare('SELECT ID FROM tblcongregation WHERE ID = ? FOR UPDATE')->execute([$cong]);

            $old = null;
            $oldLines = [];
            if ($in['isedit']) {
                $old = $this->lockInvoice($in['id']);
                if (!$old) { throw new LedgerException(['Invoice not found for this congregation']); }
                if (($p = $this->roleProblem($old,'edit')) !== null) { throw new LedgerException([$p]); }
                if (($p = Ledger::closedProblem($pdo,$old['invoiceDate'])) !== null) {
                    throw new LedgerException([$p . ': the invoice is dated ' . $old['invoiceDate'] . ' and cannot be changed']);
                }
                $oldLines = $this->storedLines((int)$old['ID']);
            }

            $errors = [];
            if (($p = Ledger::dateProblem($pdo,$in['idate'])) !== null) { $errors[] = $p; }

            $supplier = $this->getSupplierDetails($in['supplier']);
            if (!$supplier) { $errors[] = 'Select a valid supplier for this congregation'; }

            $vatId = null;
            if ($in['vattype'] > 1) {
                $vatRow = $this->getVatId($in['vat']);
                if (!$vatRow) { $errors[] = 'Select a valid VAT'; }
                elseif ((float)$in['vat'] != self::VAT_RATE) { $errors[] = 'Only the ' . self::VAT_RATE . '% VAT rate is supported'; }
                else { $vatId = (int)$vatRow; }
            }

            $st = $pdo->prepare('SELECT COUNT(*) FROM tblinvoice_header_suppliers
                                 WHERE invoiceNo = ? AND congregationId = ? AND ID <> ? AND deleted = 0');
            $st->execute([(int)$in['invoiceno'],$cong,$old ? (int)$old['ID'] : 0]);
            if ((int)$st->fetchColumn() > 0) { $errors[] = 'Invoice no already exists'; }

            // crediting account: the stored one may stay even if it was deactivated since
            $keepPayable = $old && (int)$old['PayableAccountId'] === $in['payable'];
            if (($why = Ledger::payableProblem($pdo,$cong,$in['payable'],$keepPayable)) !== null) {
                $errors[] = "Crediting account: $why";
            }

            $lines = $this->buildLines($in,$oldLines,$errors);
            if ($errors) { throw new LedgerException(array_values(array_unique($errors))); }

            $exc = $vat = $inc = 0;
            foreach ($lines as $l) { $exc += $l['exc']; $vat += $l['vat']; $inc += $l['inc']; }
            if ($inc <= 0 || $inc > self::MAX_CENTS) { throw new LedgerException(['The invoice total is out of range']); }

            // decision 82: once any active payment exists only the due date and the invoice number may change
            if ($old) {
                $paid = $this->activePayments((int)$old['ID']);
                if ($paid['count'] > 0) {
                    if ($inc < $paid['cents']) { throw new LedgerException(['The invoice total cannot be less than the payments already made']); }
                    if (($why = $this->changedAccountingFields($old,$oldLines,$in,$lines,$vatId,$inc)) !== null) {
                        throw new LedgerException(["This invoice has payments, so its $why cannot be changed. Only the due date and the invoice number can be edited"]);
                    }
                }
            }

            $year = Ledger::yearForDate($pdo,$in['idate']);
            $status = 0;
            if ($old) {
                $status = $this->statusFor($inc,$this->activePayments((int)$old['ID'])['cents']);
                $pdo->prepare('UPDATE tblinvoice_header_suppliers
                               SET invoiceDate = ?, duedate = ?, supplierId = ?, invoiceNo = ?, fiscalYearId = ?, vattype = ?, vatId = ?,
                                   PayableAccountId = ?, exclusiveVat = ?, vat = ?, inclusiveVat = ?, `status` = ?
                               WHERE ID = ? AND congregationId = ?')
                    ->execute([$in['idate'],$in['ddate'],$in['supplier'],(int)$in['invoiceno'],(int)$year['ID'],$in['vattype'],$vatId,
                               $in['payable'],self::money($exc),self::money($vat),self::money($inc),$status,(int)$old['ID'],$cong]);
                $id = (int)$old['ID'];
                $pdo->prepare('DELETE FROM tblinvoice_details_suppliers WHERE header_id = ?')->execute([$id]);
            } else {
                $pdo->prepare('INSERT INTO tblinvoice_header_suppliers (invoiceDate,duedate,supplierId,invoiceNo,fiscalYearId,vattype,vatId,
                                                                        PayableAccountId,exclusiveVat,vat,inclusiveVat,postedBy,congregationId)
                               VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$in['idate'],$in['ddate'],$in['supplier'],(int)$in['invoiceno'],(int)$year['ID'],$in['vattype'],$vatId,
                               $in['payable'],self::money($exc),self::money($vat),self::money($inc),(int)$_SESSION['userId'],$cong]);
                $id = (int)$pdo->lastInsertId();
            }

            $ins = $pdo->prepare('INSERT INTO tblinvoice_details_suppliers (header_id,productId,AccountId,qty,rate,gross) VALUES(?,?,?,?,?,?)');
            $debits = [];
            $narr = 'supplier invoice no ' . $in['invoiceno'];
            foreach ($lines as $l) {
                $ins->execute([$id,$l['pid'],$l['account'],self::money($l['qty']),self::money($l['rate']),self::money($l['gross'])]);
                $debits[] = Ledger::debit($l['account'],self::money($l['inc']),$narr);
            }
            $credit = 'Invoice #' . $in['invoiceno'];
            Ledger::post($pdo,[
                'congregationId' => $cong,
                'entryDate' => $in['idate'],
                'transactionTypeId' => self::TYPE_INVOICE,
                'transactionId' => $id,
                'reference' => 'supplier invoice-' . $in['invoiceno'],
                'description' => $credit,
            ],array_merge($debits,[Ledger::credit($in['payable'],self::money($inc),$credit)]));
            // no bank line can exist on this entry (the accounts are expense / asset posting accounts and a payable); defensive
            Ledger::syncBankPostings($pdo,$cong,self::TYPE_INVOICE,$id,'Withdrawal');

            saveLog($pdo,$credit);
            $pdo->commit();
            return ['success' => true,'id' => $id];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Something went wrong while saving the invoice']];
        }
    }

    /** Shape and number checks that need no database; throws a LedgerException listing every problem. */
    private function validateShape(array $d): array
    {
        $errors = [];
        $isedit = !empty($d['isedit']);
        $id = self::scalar($d['id'] ?? '');
        if ($isedit && ($id === null || !ctype_digit($id) || (int)$id <= 0)) { $errors[] = 'Invoice not found for this congregation'; }

        $supplier = self::scalar($d['supplier'] ?? '');
        if ($supplier === null || !ctype_digit($supplier) || (int)$supplier <= 0) { $errors[] = 'Select supplier'; }

        $idate = self::scalar($d['idate'] ?? '');
        if ($idate === null || !date_is_valid($idate)) { $errors[] = 'Enter a valid invoice date'; $idate = null; }
        elseif ($idate > date('Y-m-d')) { $errors[] = 'Invoice date cannot be a future date'; }
        $ddate = self::scalar($d['ddate'] ?? '');
        if ($ddate === null || !date_is_valid($ddate)) { $errors[] = 'Enter a valid due date'; $ddate = null; }
        elseif ($idate !== null && $idate > $ddate) { $errors[] = 'Invoice date cannot be greater than due date'; }

        $vattype = self::scalar($d['vattype'] ?? '');
        if (!in_array($vattype,['1','2','3'],true)) { $errors[] = 'Select the VAT type'; $vattype = '1'; }
        $vat = self::scalar($d['vat'] ?? '');
        if ((int)$vattype > 1 && ($vat === null || !is_numeric($vat))) { $errors[] = 'Select vat'; }

        $invoiceno = self::scalar($d['invoiceno'] ?? '');
        if ($invoiceno === null || $invoiceno === '') { $errors[] = 'Enter the invoice no'; }
        elseif (!ctype_digit($invoiceno) || (int)$invoiceno <= 0 || strlen($invoiceno) > 9) { $errors[] = 'Invoice no must be a number (up to 9 digits)'; }

        $payable = self::scalar($d['payable'] ?? '');
        if ($payable === null || !ctype_digit($payable) || (int)$payable <= 0) { $errors[] = 'Select the crediting account'; $payable = '0'; }

        $table = $d['table'] ?? null;
        if (!is_array($table) || !$table) { $errors[] = 'Enter products'; $table = []; }
        elseif (count($table) > self::MAX_LINES) { $errors[] = 'An invoice cannot have more than ' . self::MAX_LINES . ' lines'; }
        $rows = [];
        foreach (array_values($table) as $i => $row) {
            $n = $i + 1;
            $row = (array)$row;
            $pid = self::scalar($row['pid'] ?? '');
            $qty = self::scalar($row['qty'] ?? '');
            $rate = self::scalar($row['rate'] ?? '');
            if ($pid === null || !ctype_digit($pid) || (int)$pid <= 0) { $errors[] = "Line $n: select a product"; }
            if (($e = Ledger::amountError($qty,"Line $n: quantity")) !== null) { $errors[] = $e; }
            elseif (self::cents($qty) > 100000000) { $errors[] = "Line $n: quantity is too large"; }
            if (($e = Ledger::amountError($rate,"Line $n: rate")) !== null) { $errors[] = $e; }
            elseif (self::cents($rate) > self::MAX_CENTS) { $errors[] = "Line $n: rate is too large"; }
            $rows[] = ['pid' => $pid,'qty' => $qty,'rate' => $rate];
        }
        if ($errors) { throw new LedgerException(array_values(array_unique($errors))); }

        return ['isedit' => $isedit,'id' => (int)$id,'supplier' => (int)$supplier,'idate' => $idate,'ddate' => $ddate,
                'vattype' => (int)$vattype,'vat' => $vat,'invoiceno' => $invoiceno,'payable' => (int)$payable,'table' => $rows];
    }

    /**
     * Resolve each submitted line against the database: the product (own congregation, not retired) and its debit
     * account. A line identical to a stored line (same product, quantity and rate) keeps the account stored on that line
     * (the snapshot); a new or changed line takes the product's account now. Problems are appended to $errors.
     * Returns [ ['pid','account','qty','rate','gross','exc','vat','inc'] ] (cents).
     */
    private function buildLines(array $in, array $oldLines, array &$errors): array
    {
        $pdo = $this->db->dbh;
        $cong = (int)$_SESSION['congId'];
        $out = [];
        $unused = $oldLines;
        $total = 0;
        foreach ($in['table'] as $i => $row) {
            $n = $i + 1;
            $st = $pdo->prepare('SELECT ID, productName, accountId FROM tblproducts WHERE ID = ? AND congregationId = ? AND deleted = 0');
            $st->execute([(int)$row['pid'],$cong]);
            $product = $st->fetch(PDO::FETCH_ASSOC);
            $qty = self::cents($row['qty']);
            $rate = self::cents($row['rate']);

            $account = null;
            foreach ($unused as $k => $o) {
                if ((int)$o['productId'] === (int)$row['pid'] && self::cents($o['qty']) === $qty && self::cents($o['rate']) === $rate && $o['AccountId'] !== null) {
                    $account = (int)$o['AccountId'];
                    $keep = true;
                    unset($unused[$k]);
                    break;
                }
            }
            if (!$product && $account === null) {
                $errors[] = "Line $n: the product is not available (unknown, retired or of another congregation)";
                continue;
            }
            if ($account === null) { $account = (int)$product['accountId']; $keep = false; }
            if (($why = $this->lineAccountProblem($account,$keep)) !== null) {
                $name = $product ? $product['productName'] : 'product ' . $row['pid'];
                $errors[] = "Line $n ($name): account $why";
                continue;
            }

            $gross = intdiv($qty * $rate + 50,100);
            if ($gross <= 0) { $errors[] = "Line $n: the line amount must be at least 0.01"; continue; }
            $total += $gross;
            if ($total > self::MAX_CENTS) { $errors[] = 'The invoice total is out of range'; break; }
            [$exc,$vat,$inc] = self::lineVat($in['vattype'],$gross);
            $out[] = ['pid' => (int)$row['pid'],'account' => $account,'qty' => $qty,'rate' => $rate,'gross' => $gross,'exc' => $exc,'vat' => $vat,'inc' => $inc];
        }
        return $out;
    }

    /** Decision 82: names the first accounting field a submitted invoice changes against the stored one, or null. */
    private function changedAccountingFields(array $old, array $oldLines, array $in, array $lines, ?int $vatId, int $inc): ?string
    {
        if ($old['invoiceDate'] !== $in['idate']) { return 'date'; }
        if ((int)$old['supplierId'] !== $in['supplier']) { return 'supplier'; }
        if ($old['PayableAccountId'] === null || (int)$old['PayableAccountId'] !== $in['payable']) { return 'crediting account'; }
        if ((int)$old['vattype'] !== $in['vattype'] || ($old['vatId'] === null ? null : (int)$old['vatId']) !== $vatId) { return 'VAT'; }
        $a = array_map(function($l){ return $l['productId'] . '|' . $l['AccountId'] . '|' . self::cents($l['qty']) . '|' . self::cents($l['rate']); },$oldLines);
        $b = array_map(function($l){ return $l['pid'] . '|' . $l['account'] . '|' . $l['qty'] . '|' . $l['rate']; },$lines);
        sort($a); sort($b);
        if ($a !== $b) {
            // the accounts are compared apart so the message names what changed
            $pa = array_map(function($l){ return $l['productId'] . '|' . self::cents($l['qty']) . '|' . self::cents($l['rate']); },$oldLines);
            $pb = array_map(function($l){ return $l['pid'] . '|' . $l['qty'] . '|' . $l['rate']; },$lines);
            sort($pa); sort($pb);
            return $pa === $pb ? 'line accounts' : 'lines';
        }
        if (self::cents((string)$old['inclusiveVat']) !== $inc) { return 'total'; }
        return null;
    }

    private function statusFor(int $totalCents, int $paidCents): int
    {
        if ($paidCents <= 0) { return 0; }
        return $paidCents >= $totalCents ? 2 : 1;
    }

    // -----------------------------------------------------------------
    // Reads of one invoice
    // -----------------------------------------------------------------

    /** The invoice header by ID + session congregation (+ not deleted, a real invoice and not an opening balance), locked. */
    private function lockInvoice(int $id): ?array
    {
        $st = $this->db->dbh->prepare('SELECT * FROM tblinvoice_header_suppliers
                                       WHERE ID = ? AND congregationId = ? AND deleted = 0 AND invoiceNo IS NOT NULL FOR UPDATE');
        $st->execute([$id,(int)$_SESSION['congId']]);
        $h = $st->fetch(PDO::FETCH_ASSOC);
        return $h ?: null;
    }

    private function storedLines(int $headerId): array
    {
        $st = $this->db->dbh->prepare('SELECT productId, AccountId, qty, rate, gross FROM tblinvoice_details_suppliers WHERE header_id = ? ORDER BY ID');
        $st->execute([$headerId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Active payments of an invoice: count and total in cents. */
    private function activePayments(int $headerId): array
    {
        $st = $this->db->dbh->prepare('SELECT COUNT(*), COALESCE(SUM(amount),0) FROM tblinvoice_payments_suppliers WHERE invoice_Id = ? AND Deleted = 0');
        $st->execute([$headerId]);
        $r = $st->fetch(PDO::FETCH_NUM);
        return ['count' => (int)$r[0],'cents' => self::cents(number_format((float)$r[1],2,'.',''))];
    }

    /** Who may do what (the buttons of supplierinvoices/index.php): types 3 and 4 edit only their own unpaid invoices and never delete. */
    private function roleProblem(array $h, string $action): ?string
    {
        $type = (int)$_SESSION['userType'];
        if ($type <= 2 || $type > 4) { return null; }
        if ($action === 'delete') { return 'You do not have the right to delete invoices'; }
        if ((int)$h['postedBy'] !== (int)$_SESSION['userId'] || (int)$h['status'] !== 0) {
            return 'You can only edit your own unpaid invoices';
        }
        return null;
    }

    /** The invoice for the edit / print pages (ID + session congregation), or false. */
    public function getInvoiceHeader($id)
    {
        $this->db->query('SELECT ID,
                                 invoiceDate,
                                 duedate,
                                 supplierId,
                                 invoiceNo,
                                 vattype,
                                 vatId,
                                 PayableAccountId,
                                 exclusiveVat,
                                 vat,
                                 inclusiveVat,
                                 `status`,
                                 postedBy,
                                 fiscalYearId,
                                 congregationId
                          FROM   tblinvoice_header_suppliers
                          WHERE  (ID=:id) AND (congregationId=:cid) AND (deleted=0) AND (invoiceNo IS NOT NULL)');
        $this->db->bind(':id',decryptId($id));
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    /** Lines of an invoice with the product and the account snapshotted on each (the product's account for rows saved before 8.7). */
    public function getInvoiceDetails($id)
    {
        $this->db->query('SELECT d.productId,
                                 ucase(p.productName) as accountType,
                                 d.AccountId,
                                 ucase(a.accountType) as account,
                                 d.qty,
                                 d.rate,
                                 d.gross,
                                 UCASE(d.description) as `description`
                          FROM   tblinvoice_details_suppliers d inner join tblproducts p
                                 ON d.productId = p.ID
                                 left join tblaccounttypes a ON a.ID = COALESCE(d.AccountId, p.accountId)
                          WHERE  (d.header_id = :id)
                          ORDER BY d.ID');
        $this->db->bind(':id',(int)$id);
        return $this->db->resultSet();
    }

    public function hasPayments($id): bool
    {
        return $this->activePayments((int)$id)['count'] > 0;
    }

    public function getCongregationInfo()
    {
        $this->db->query('SELECT ucase(CongregationName) as CongregationName,
                                 UCASE(`Address`) as `address`,
                                 contact,
                                 email
                          FROM   tblcongregation
                          WHERE  (ID=:id)');
        $this->db->bind(':id',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    public function getSupplierInfo($id)
    {
        $this->db->query('SELECT UCASE(supplierName) as supplierName,
                                 UCASE(`address`) as `address`,
                                 contact,
                                 email,
                                 UCASE(pin) AS pin
                          FROM   tblsuppliers
                          WHERE  (ID=:id) AND (congregationId=:cid)');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    // -----------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------

    /** Delete = void the entry and soft-delete the header. Refused with active payments or in a closed year. Returns ['success','errors']. */
    public function Delete($id): array
    {
        $pdo = $this->db->dbh;
        try {
            $cong = (int)$_SESSION['congId'];
            if (!ctype_digit((string)$id) || (int)$id <= 0) { throw new LedgerException(['Unable to find selected invoice']); }
            $pdo->beginTransaction();
            $h = $this->lockInvoice((int)$id);
            if (!$h) { throw new LedgerException(['Invoice not found for this congregation']); }
            if (($p = $this->roleProblem($h,'delete')) !== null) { throw new LedgerException([$p]); }
            if ($this->activePayments((int)$h['ID'])['count'] > 0) {
                throw new LedgerException(['This invoice has payments and cannot be deleted']);
            }
            if (($p = Ledger::closedProblem($pdo,$h['invoiceDate'])) !== null) {
                throw new LedgerException([$p . ': the invoice is dated ' . $h['invoiceDate'] . ' and cannot be deleted']);
            }
            if (($p = Ledger::clearedBankProblem($pdo,$cong,self::TYPE_INVOICE,(int)$h['ID'])) !== null) { throw new LedgerException([$p]); }
            Ledger::void($pdo,$cong,self::TYPE_INVOICE,(int)$h['ID']);
            Ledger::voidBankPostings($pdo,$cong,self::TYPE_INVOICE,(int)$h['ID']);
            $pdo->prepare('UPDATE tblinvoice_header_suppliers SET deleted = 1 WHERE ID = ? AND congregationId = ?')->execute([(int)$h['ID'],$cong]);
            saveLog($pdo,'Deleted supplier invoice #' . $h['invoiceNo']);
            $pdo->commit();
            return ['success' => true];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Something went wrong while deleting the invoice']];
        }
    }
}

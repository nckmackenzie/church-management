<?php

/**
 * Customer invoices (transaction type 14) and customer payments (type 16), task 8.8.
 *
 * Invoice: Dr the receivable account chosen on the form (any active, visible posting account in the subtree of 9; stored in
 * tblinvoice_header.ReceivableAccountId), Cr the income account of each line (taken from the product, snapshotted in
 * tblinvoice_details.AccountId). transactionId = the header ID. VAT and rounding: see InvoiceMath (same rule as supplier
 * invoices). The invoice number is generated (locked sequence per congregation); nobody types it. There is no invoice delete
 * (decision 83).
 *
 * Payment: one tblinvoice_payments row and one type-16 entry (transactionId = the payment row ID) per payment, in one
 * transaction: Dr the cash / bank account chosen on the form (decision 76, stored in PaymentAccountId), Cr the invoice's
 * stored ReceivableAccountId (read here, never chosen). Cheque / bank / M-Pesa get a tblbankpostings row (Deposit); cash
 * none. No payment edit or delete (decision 2).
 */
class Invoice
{
    const TYPE_INVOICE = 14;
    const TYPE_PAYMENT = 16;
    const BANK_METHOD = 'Deposit';
    const MAX_LINES = 200;

    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    public function CheckRights($form)
    {
        return getUserAccess($this->db->dbh,$_SESSION['userId'],$form,$_SESSION['isParish']) > 0;
    }

    // -----------------------------------------------------------------
    // Lists and lookups (always the session congregation)
    // -----------------------------------------------------------------

    public function index()
    {
        $this->db->query('SELECT * FROM vw_invoices WHERE congregationId = :cid');
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function getCustomers()
    {
        $this->db->query('SELECT ID,
                                 UCASE(customerName) as customerName
                          FROM   tblcustomers
                          WHERE  (deleted=0) AND (congregationId = :cid)
                          ORDER BY customerName');
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->resultSet();
    }

    public function getCustomerDetails($id)
    {
        if (!ctype_digit((string)$id) || (int)$id <= 0) { return false; }
        $this->db->query('SELECT * FROM tblcustomers WHERE (ID=:id) AND (congregationId=:cid) AND (deleted=0)');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    /** The receivable accounts to offer (posting accounts in 9's subtree, no default). The same list the server validates against. */
    public function getReceivableAccounts(): array
    {
        return array_map(function($a){ return (object)$a; },Ledger::listReceivableAccounts($this->db->dbh,(int)$_SESSION['congId']));
    }

    /** One account (ID, accountType) for a dropdown that must still show an account no longer on the list. */
    public function getAccountRow(int $id)
    {
        $st = $this->db->dbh->prepare('SELECT ID, accountType FROM tblaccounttypes WHERE ID = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_OBJ);
        return $r ?: null;
    }

    /** Income accounts a line may be credited to (the product modal offers these). [ ['ID'=>, 'accountType'=>], ... ] */
    public function lineAccounts(): array
    {
        return array_map(function($a){ return ['ID' => $a['ID'],'accountType' => strtoupper($a['accountType'])]; },
            Ledger::listLineAccounts($this->db->dbh,(int)$_SESSION['congId'],Ledger::CUSTOMER_LINE_TYPES));
    }

    public function lineAccountProblem(int $accountId, bool $allowInactive = false): ?string
    {
        return Ledger::lineAccountProblem($this->db->dbh,(int)$_SESSION['congId'],$accountId,Ledger::CUSTOMER_LINE_TYPES,$allowInactive);
    }

    /** Products a new line may use: own, not retired, with a valid income account. */
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

    /** A product of this congregation as the form needs it (rate, account, problem), or null for an unknown / foreign / retired one. */
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

    public function getVats()
    {
        $this->db->query('SELECT ID,
                                 rate,
                                 UCASE(vatName) as vatName
                          FROM tblvats WHERE (deleted=0) AND (active=1)');
        return $this->db->resultSet();
    }

    public function getVatId($rate)
    {
        $st = $this->db->dbh->prepare('SELECT ID FROM tblvats WHERE deleted = 0 AND active = 1 AND rate = ?');
        $st->execute([$rate]);
        return $st->fetchColumn();
    }

    /** The number the next invoice will get (display only; the real one is allocated under a lock when saving). */
    public function peekInvoiceNo()
    {
        return Ledger::peekNextNumber($this->db->dbh,(int)$_SESSION['congId'],Ledger::SEQ_CUSTOMER_INVOICE);
    }

    public function paymethods()
    {
        return paymentMethods($this->db->dbh);
    }

    public function getCashAccounts(): array
    {
        return array_map(function($a){ return (object)$a; },Ledger::listPaymentAccounts($this->db->dbh,(int)$_SESSION['congId'],Ledger::PAY_CASH));
    }

    public function getBanks(): array
    {
        return array_map(function($a){ return (object)$a; },Ledger::listPaymentAccounts($this->db->dbh,(int)$_SESSION['congId'],Ledger::PAY_BANK));
    }

    /** New product from the invoice modal (income accounts only). Returns ['success','errors','id']. */
    public function newProduct($data): array
    {
        $pdo = $this->db->dbh;
        $name = strtolower(trim((string)($data['name'] ?? '')));
        $errors = [];
        if ($name === '') { $errors[] = 'Enter the product name'; }
        elseif (strlen($name) > 255) { $errors[] = 'Product name cannot be longer than 255 characters'; }
        $desc = trim((string)($data['desc'] ?? ''));
        if (strlen($desc) > 255) { $errors[] = 'Description cannot be longer than 255 characters'; }
        if (($e = Ledger::amountError($data['sp'] ?? '','Selling price')) !== null) { $errors[] = $e; }
        elseif (InvoiceMath::cents(trim((string)$data['sp'])) > InvoiceMath::MAX_CENTS) { $errors[] = 'Selling price is too large'; }
        $account = (string)($data['account'] ?? '');
        if (!ctype_digit($account) || (int)$account <= 0) { $errors[] = 'Select the income account'; }
        elseif (($why = $this->lineAccountProblem((int)$account)) !== null) { $errors[] = "Income account: $why"; }
        if (!$errors) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM tblproducts WHERE productName = ? AND deleted = 0 AND congregationId = ?');
            $st->execute([$name,(int)$_SESSION['congId']]);
            if ((int)$st->fetchColumn() > 0) { $errors[] = 'A product with this name already exists'; }
        }
        if ($errors) { return ['success' => false,'errors' => $errors]; }
        try {
            $st = $pdo->prepare('INSERT INTO tblproducts (productName,`description`,rate,accountId,congregationId) VALUES(?,?,?,?,?)');
            $st->execute([$name,$desc !== '' ? strtolower($desc) : null,InvoiceMath::money(InvoiceMath::cents(trim((string)$data['sp']))),(int)$account,(int)$_SESSION['congId']]);
            return ['success' => true,'id' => (int)$pdo->lastInsertId()];
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Unable to save the product. Retry or contact admin']];
        }
    }

    // -----------------------------------------------------------------
    // Create / update
    // -----------------------------------------------------------------

    /**
     * Create or update (in place) a customer invoice. $d: id, isedit, customer, idate, ddate, vattype, vat (the VAT rate),
     * receivable, table (list of ['pid','qty','rate','desc']). Client totals and the invoice number are not accepted: lines,
     * VAT, total and number are produced here. Returns ['success'=>true,'id'=>ID,'invoiceNo'=>n] or ['success'=>false,'errors'=>[...]].
     */
    public function save(array $d): array
    {
        $pdo = $this->db->dbh;
        try {
            $in = $this->validateShape($d);
            $cong = (int)$_SESSION['congId'];
            $pdo->beginTransaction();
            $pdo->prepare('SELECT ID FROM tblcongregation WHERE ID = ? FOR UPDATE')->execute([$cong]);

            $old = null;
            $oldLines = [];
            if ($in['isedit']) {
                $old = $this->lockInvoice($in['id']);
                if (!$old) { throw new LedgerException(['Invoice not found for this congregation']); }
                if (($p = $this->roleProblem($old)) !== null) { throw new LedgerException([$p]); }
                if (($p = Ledger::closedProblem($pdo,$old['invoiceDate'])) !== null) {
                    throw new LedgerException([$p . ': the invoice is dated ' . $old['invoiceDate'] . ' and cannot be changed']);
                }
                $oldLines = $this->storedLines((int)$old['ID']);
            }

            $errors = [];
            if (($p = Ledger::dateProblem($pdo,$in['idate'])) !== null) { $errors[] = $p; }
            if (!$this->getCustomerDetails($in['customer'])) { $errors[] = 'Select a valid customer for this congregation'; }

            $vatId = null;
            if ($in['vattype'] > 1) {
                $vatRow = $this->getVatId($in['vat']);
                if (!$vatRow) { $errors[] = 'Select a valid VAT'; }
                elseif ((float)$in['vat'] != InvoiceMath::VAT_RATE) { $errors[] = 'Only the ' . InvoiceMath::VAT_RATE . '% VAT rate is supported'; }
                else { $vatId = (int)$vatRow; }
            }

            if (!Ledger::listReceivableAccounts($pdo,$cong)) {
                $errors[] = 'No receivables account is set up. Ask the accountant to create a posting account under accounts receivable';
            } else {
                $keep = $old && $old['ReceivableAccountId'] !== null && (int)$old['ReceivableAccountId'] === $in['receivable'];
                if (($why = Ledger::receivableProblem($pdo,$cong,$in['receivable'],$keep)) !== null) { $errors[] = "Receivable account: $why"; }
            }

            $lines = $this->buildLines($in,$oldLines,$errors);
            if ($errors) { throw new LedgerException(array_values(array_unique($errors))); }

            $exc = $vat = $inc = 0;
            foreach ($lines as $l) { $exc += $l['exc']; $vat += $l['vat']; $inc += $l['inc']; }
            if ($inc <= 0 || $inc > InvoiceMath::MAX_CENTS) { throw new LedgerException(['The invoice total is out of range']); }

            // decision 82: once any payment exists only the due date may change (customer invoices have no reference field)
            if ($old) {
                $paid = $this->payments((int)$old['ID']);
                if ($paid['count'] > 0) {
                    if ($inc < $paid['cents']) { throw new LedgerException(['The invoice total cannot be less than the payments already made']); }
                    if (($why = $this->changedAccountingFields($old,$oldLines,$in,$lines,$vatId,$inc)) !== null) {
                        throw new LedgerException(["This invoice has payments, so its $why cannot be changed. Only the due date can be edited"]);
                    }
                }
            }

            $year = Ledger::yearForDate($pdo,$in['idate']);
            if ($old) {
                $status = $this->statusFor($inc,$this->payments((int)$old['ID'])['cents']);
                $pdo->prepare('UPDATE tblinvoice_header
                               SET invoiceDate = ?, duedate = ?, customerId = ?, fiscalYearId = ?, vattype = ?, vatId = ?,
                                   ReceivableAccountId = ?, exclusiveVat = ?, vat = ?, inclusiveVat = ?, `status` = ?
                               WHERE ID = ? AND congregationId = ?')
                    ->execute([$in['idate'],$in['ddate'],$in['customer'],(int)$year['ID'],$in['vattype'],$vatId,$in['receivable'],
                               InvoiceMath::money($exc),InvoiceMath::money($vat),InvoiceMath::money($inc),$status,(int)$old['ID'],$cong]);
                $id = (int)$old['ID'];
                $number = (string)$old['invoiceNo'];
                $pdo->prepare('DELETE FROM tblinvoice_details WHERE header_id = ?')->execute([$id]);
            } else {
                $number = (string)Ledger::nextNumber($pdo,$cong,Ledger::SEQ_CUSTOMER_INVOICE);
                $pdo->prepare('INSERT INTO tblinvoice_header (invoiceDate,duedate,customerId,invoiceNo,fiscalYearId,vattype,vatId,
                                                              ReceivableAccountId,exclusiveVat,vat,inclusiveVat,postedBy,congregationId)
                               VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$in['idate'],$in['ddate'],$in['customer'],(int)$number,(int)$year['ID'],$in['vattype'],$vatId,$in['receivable'],
                               InvoiceMath::money($exc),InvoiceMath::money($vat),InvoiceMath::money($inc),(int)$_SESSION['userId'],$cong]);
                $id = (int)$pdo->lastInsertId();
            }

            $ins = $pdo->prepare('INSERT INTO tblinvoice_details (header_id,productId,AccountId,qty,rate,gross,`description`) VALUES(?,?,?,?,?,?,?)');
            $credits = [];
            foreach ($lines as $l) {
                $ins->execute([$id,$l['pid'],$l['account'],InvoiceMath::money($l['qty']),InvoiceMath::money($l['rate']),InvoiceMath::money($l['gross']),$l['desc']]);
                $credits[] = Ledger::credit($l['account'],InvoiceMath::money($l['inc']),$l['desc'] ?? ('invoice ' . $number));
            }
            $debit = 'Invoice #' . $number;
            Ledger::post($pdo,[
                'congregationId' => $cong,
                'entryDate' => $in['idate'],
                'transactionTypeId' => self::TYPE_INVOICE,
                'transactionId' => $id,
                'reference' => 'invoice-' . $number,
                'description' => $debit,
            ],array_merge([Ledger::debit($in['receivable'],InvoiceMath::money($inc),$debit)],$credits));
            // no bank line can exist on this entry (income posting accounts and a receivable); defensive
            Ledger::syncBankPostings($pdo,$cong,self::TYPE_INVOICE,$id,self::BANK_METHOD);

            saveLog($pdo,($old ? 'Updated ' : '') . $debit);
            $pdo->commit();
            return ['success' => true,'id' => $id,'invoiceNo' => $number];
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
        $id = InvoiceMath::scalar($d['id'] ?? '');
        if ($isedit && ($id === null || !ctype_digit($id) || (int)$id <= 0)) { $errors[] = 'Invoice not found for this congregation'; }

        $customer = InvoiceMath::scalar($d['customer'] ?? '');
        if ($customer === null || !ctype_digit($customer) || (int)$customer <= 0) { $errors[] = 'Select customer'; }

        $idate = InvoiceMath::scalar($d['idate'] ?? '');
        if ($idate === null || !date_is_valid($idate)) { $errors[] = 'Enter a valid invoice date'; $idate = null; }
        elseif ($idate > date('Y-m-d')) { $errors[] = 'Invoice date cannot be a future date'; }
        $ddate = InvoiceMath::scalar($d['ddate'] ?? '');
        if ($ddate === null || !date_is_valid($ddate)) { $errors[] = 'Enter a valid due date'; $ddate = null; }
        elseif ($idate !== null && $idate > $ddate) { $errors[] = 'Invoice date cannot be greater than due date'; }

        $vattype = InvoiceMath::scalar($d['vattype'] ?? '');
        if (!in_array($vattype,['1','2','3'],true)) { $errors[] = 'Select the VAT type'; $vattype = '1'; }
        $vat = InvoiceMath::scalar($d['vat'] ?? '');
        if ((int)$vattype > 1 && ($vat === null || !is_numeric($vat))) { $errors[] = 'Select vat'; }

        $receivable = InvoiceMath::scalar($d['receivable'] ?? '');
        if ($receivable === null || !ctype_digit($receivable) || (int)$receivable <= 0) { $errors[] = 'Select the receivable account'; $receivable = '0'; }

        $table = $d['table'] ?? null;
        if (!is_array($table) || !$table) { $errors[] = 'Enter products'; $table = []; }
        elseif (count($table) > self::MAX_LINES) { $errors[] = 'An invoice cannot have more than ' . self::MAX_LINES . ' lines'; }
        $rows = [];
        foreach (array_values($table) as $i => $row) {
            $n = $i + 1;
            $row = (array)$row;
            $pid = InvoiceMath::scalar($row['pid'] ?? '');
            $qty = InvoiceMath::scalar($row['qty'] ?? '');
            $rate = InvoiceMath::scalar($row['rate'] ?? '');
            $desc = InvoiceMath::scalar($row['desc'] ?? '');
            if ($pid === null || !ctype_digit($pid) || (int)$pid <= 0) { $errors[] = "Line $n: select a product"; }
            if (($e = Ledger::amountError($qty,"Line $n: quantity")) !== null) { $errors[] = $e; }
            elseif (InvoiceMath::cents($qty) > 100000000) { $errors[] = "Line $n: quantity is too large"; }
            if (($e = Ledger::amountError($rate,"Line $n: rate")) !== null) { $errors[] = $e; }
            elseif (InvoiceMath::cents($rate) > InvoiceMath::MAX_CENTS) { $errors[] = "Line $n: rate is too large"; }
            if ($desc === null || strlen($desc) > 255) { $errors[] = "Line $n: description cannot be longer than 255 characters"; $desc = ''; }
            $rows[] = ['pid' => $pid,'qty' => $qty,'rate' => $rate,'desc' => $desc === '' ? null : strtolower($desc)];
        }
        if ($errors) { throw new LedgerException(array_values(array_unique($errors))); }

        return ['isedit' => $isedit,'id' => (int)$id,'customer' => (int)$customer,'idate' => $idate,'ddate' => $ddate,
                'vattype' => (int)$vattype,'vat' => $vat,'receivable' => (int)$receivable,'table' => $rows];
    }

    /**
     * Resolve each submitted line: the product (own congregation, not retired) and its income account. A line identical to
     * a stored one (same product, quantity, rate) keeps the account stored on that line; a new or changed line takes the
     * product's account now. Returns [ ['pid','account','qty','rate','gross','exc','vat','inc','desc'] ] (cents).
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
            $qty = InvoiceMath::cents($row['qty']);
            $rate = InvoiceMath::cents($row['rate']);

            $account = null;
            $keep = false;
            foreach ($unused as $k => $o) {
                if ((int)$o['productId'] === (int)$row['pid'] && InvoiceMath::cents((string)$o['qty']) === $qty && InvoiceMath::cents((string)$o['rate']) === $rate && $o['AccountId'] !== null) {
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
            if ($account === null) { $account = (int)$product['accountId']; }
            if (($why = $this->lineAccountProblem($account,$keep)) !== null) {
                $name = $product ? $product['productName'] : 'product ' . $row['pid'];
                $errors[] = "Line $n ($name): account $why";
                continue;
            }

            $gross = InvoiceMath::gross($qty,$rate);
            if ($gross <= 0) { $errors[] = "Line $n: the line amount must be at least 0.01"; continue; }
            $total += $gross;
            if ($total > InvoiceMath::MAX_CENTS) { $errors[] = 'The invoice total is out of range'; break; }
            [$exc,$vat,$inc] = InvoiceMath::lineVat($in['vattype'],$gross);
            $out[] = ['pid' => (int)$row['pid'],'account' => $account,'qty' => $qty,'rate' => $rate,'gross' => $gross,'exc' => $exc,'vat' => $vat,'inc' => $inc,'desc' => $row['desc']];
        }
        return $out;
    }

    /** Decision 82: names the first accounting field a submitted invoice changes against the stored one, or null. */
    private function changedAccountingFields(array $old, array $oldLines, array $in, array $lines, ?int $vatId, int $inc): ?string
    {
        if ($old['invoiceDate'] !== $in['idate']) { return 'date'; }
        if ((int)$old['customerId'] !== $in['customer']) { return 'customer'; }
        if ($old['ReceivableAccountId'] === null || (int)$old['ReceivableAccountId'] !== $in['receivable']) { return 'receivable account'; }
        if ((int)$old['vattype'] !== $in['vattype'] || ($old['vatId'] === null ? null : (int)$old['vatId']) !== $vatId) { return 'VAT'; }
        $a = array_map(function($l){ return $l['productId'] . '|' . $l['AccountId'] . '|' . InvoiceMath::cents((string)$l['qty']) . '|' . InvoiceMath::cents((string)$l['rate']) . '|' . ($l['description'] ?? ''); },$oldLines);
        $b = array_map(function($l){ return $l['pid'] . '|' . $l['account'] . '|' . $l['qty'] . '|' . $l['rate'] . '|' . ($l['desc'] ?? ''); },$lines);
        sort($a); sort($b);
        if ($a !== $b) {
            $pa = array_map(function($l){ return $l['productId'] . '|' . InvoiceMath::cents((string)$l['qty']) . '|' . InvoiceMath::cents((string)$l['rate']); },$oldLines);
            $pb = array_map(function($l){ return $l['pid'] . '|' . $l['qty'] . '|' . $l['rate']; },$lines);
            sort($pa); sort($pb);
            if ($pa !== $pb) { return 'lines'; }
            $aa = array_map(function($l){ return $l['productId'] . '|' . $l['AccountId']; },$oldLines);
            $ab = array_map(function($l){ return $l['pid'] . '|' . $l['account']; },$lines);
            sort($aa); sort($ab);
            return $aa === $ab ? 'line descriptions' : 'line accounts';
        }
        if (InvoiceMath::cents((string)$old['inclusiveVat']) !== $inc) { return 'total'; }
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

    /** The invoice header by ID + session congregation, locked. */
    private function lockInvoice(int $id): ?array
    {
        $st = $this->db->dbh->prepare('SELECT * FROM tblinvoice_header WHERE ID = ? AND congregationId = ? FOR UPDATE');
        $st->execute([$id,(int)$_SESSION['congId']]);
        $h = $st->fetch(PDO::FETCH_ASSOC);
        return $h ?: null;
    }

    private function storedLines(int $headerId): array
    {
        $st = $this->db->dbh->prepare('SELECT productId, AccountId, qty, rate, gross, `description` FROM tblinvoice_details WHERE header_id = ? ORDER BY ID');
        $st->execute([$headerId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Payments of an invoice: count and total in cents. */
    private function payments(int $headerId): array
    {
        $st = $this->db->dbh->prepare('SELECT COUNT(*), COALESCE(SUM(amount),0) FROM tblinvoice_payments WHERE invoice_Id = ?');
        $st->execute([$headerId]);
        $r = $st->fetch(PDO::FETCH_NUM);
        return ['count' => (int)$r[0],'cents' => InvoiceMath::cents(number_format((float)$r[1],2,'.',''))];
    }

    /** Who may edit (the buttons of invoices/index.php): types 3 and 4 only their own unpaid invoices. */
    private function roleProblem(array $h): ?string
    {
        $type = (int)$_SESSION['userType'];
        if ($type <= 2 || $type > 4) { return null; }
        if ((int)$h['postedBy'] !== (int)$_SESSION['userId'] || (int)$h['status'] !== 0) { return 'You can only edit your own unpaid invoices'; }
        return null;
    }

    public function hasPayments($id): bool
    {
        return $this->payments((int)$id)['count'] > 0;
    }

    /** The invoice for the edit / print pages (encrypted ID + session congregation), or false. */
    public function getInvoiceHeader($id)
    {
        $this->db->query('SELECT ID,
                                 invoiceDate,
                                 duedate,
                                 customerId,
                                 invoiceNo,
                                 vattype,
                                 vatId,
                                 ReceivableAccountId,
                                 exclusiveVat,
                                 vat,
                                 inclusiveVat,
                                 `status`,
                                 postedBy,
                                 fiscalYearId,
                                 congregationId
                          FROM   tblinvoice_header
                          WHERE  (ID=:id) AND (congregationId=:cid)');
        $this->db->bind(':id',decryptId($id));
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    /** Lines with the product and the account snapshotted on each. */
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
                          FROM   tblinvoice_details d inner join tblproducts p
                                 ON d.productId = p.ID
                                 left join tblaccounttypes a ON a.ID = COALESCE(d.AccountId, p.accountId)
                          WHERE  (d.header_id = :id)
                          ORDER BY d.ID');
        $this->db->bind(':id',(int)$id);
        return $this->db->resultSet();
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

    public function getCustomernInfo($id)
    {
        $this->db->query('SELECT UCASE(customerName) as customerName,
                                 UCASE(`address`) as `address`,
                                 contact,
                                 email,
                                 UCASE(pin) AS pin
                          FROM   tblcustomers
                          WHERE  (ID=:id) AND (congregationId=:cid)');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    /** The invoice for the payment form (encrypted ID + session congregation) with its balance and receivable account, or false. */
    public function fillInvoiceDetails($id)
    {
        $this->db->query('SELECT   h.ID,
                                   ucase(customerName) as customerName,
                                   invoiceNo,
                                   inclusiveVat,
                                   h.ReceivableAccountId,
                                   ucase(a.accountType) as receivable,
                                   (inclusiveVat - (SELECT IFNULL(SUM(amount),0) FROM tblinvoice_payments
                                   WHERE invoice_Id=h.ID)) as balance
                          FROM     tblinvoice_header h inner join tblcustomers c
                                   ON h.customerId = c.ID
                                   left join tblaccounttypes a ON a.ID = h.ReceivableAccountId
                          WHERE    (h.ID=:id) AND (h.congregationId=:cid)');
        $this->db->bind(':id',decryptId($id));
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    // -----------------------------------------------------------------
    // Payment (type 16)
    // -----------------------------------------------------------------

    /**
     * Save one payment. $data: id (plain invoice ID), paydate, amount, paymethod, cashaccount (PAY_CASH account for method 1),
     * bank (PAY_BANK account for the other methods), reference. Returns ['success'=>true,'id'=>paymentID] or ['success'=>false,'errors'=>[...]].
     */
    public function payment(array $data): array
    {
        $pdo = $this->db->dbh;
        try {
            $cong = (int)$_SESSION['congId'];
            $errors = [];

            $paydate = InvoiceMath::scalar($data['paydate'] ?? '');
            if ($paydate === null || !date_is_valid($paydate)) { $errors[] = 'Enter a valid payment date'; }
            elseif ($paydate > date('Y-m-d')) { $errors[] = 'Payment date cannot be greater than current date'; }
            elseif (($p = Ledger::dateProblem($pdo,$paydate)) !== null) { $errors[] = $p; }

            $amount = InvoiceMath::scalar($data['amount'] ?? '');
            if (($e = Ledger::amountError($amount,'Amount')) !== null) { $errors[] = $e; }
            elseif (InvoiceMath::cents($amount) > InvoiceMath::MAX_CENTS) { $errors[] = 'Amount is too large'; }

            $method = InvoiceMath::scalar($data['paymethod'] ?? '');
            $st = $pdo->prepare('SELECT COUNT(*) FROM tblpaymentmethods WHERE ID = ?');
            if ($method === null || !ctype_digit($method) || (int)$method <= 0) { $errors[] = 'Select a valid payment method'; }
            else { $st->execute([(int)$method]); if (!(int)$st->fetchColumn()) { $errors[] = 'Select a valid payment method'; } }
            $method = (int)$method;

            $reference = InvoiceMath::scalar($data['reference'] ?? '');
            if ($reference === null) { $errors[] = 'Invalid reference'; $reference = ''; }
            if (strlen($reference) > 50) { $errors[] = 'Reference cannot be longer than 50 characters'; }
            if ($method > 1 && $reference === '') { $errors[] = 'Enter Payment Reference'; }

            $account = 0;
            $bank = null;
            if ($method === 1) {
                $acc = InvoiceMath::scalar($data['cashaccount'] ?? '');
                if ($acc === null || !ctype_digit($acc) || (int)$acc <= 0) { $errors[] = 'Select the cash account'; }
                else {
                    try { $account = Ledger::assertPaymentAccount($pdo,$cong,(int)$acc,Ledger::PAY_CASH); }
                    catch (LedgerException $e) { $errors = array_merge($errors,$e->getErrors()); }
                }
            } elseif ($method > 1) {
                $acc = InvoiceMath::scalar($data['bank'] ?? '');
                if ($acc === null || !ctype_digit($acc) || (int)$acc <= 0) { $errors[] = 'Select Bank'; }
                else {
                    try { $account = $bank = Ledger::assertPaymentAccount($pdo,$cong,(int)$acc,Ledger::PAY_BANK); }
                    catch (LedgerException $e) { $errors = array_merge($errors,$e->getErrors()); }
                }
            }
            $iid = InvoiceMath::scalar($data['id'] ?? '');
            if ($iid === null || !ctype_digit($iid) || (int)$iid <= 0) { $errors[] = 'Invoice not found for this congregation'; }
            if ($errors) { throw new LedgerException(array_values(array_unique($errors))); }

            $pdo->beginTransaction();
            $h = $this->lockInvoice((int)$iid);
            if (!$h) { throw new LedgerException(['Invoice not found for this congregation']); }
            if ($h['ReceivableAccountId'] === null) {
                throw new LedgerException(['Cannot pay invoice ' . $h['invoiceNo'] . ': it has no receivable account recorded (it was entered before the ledger migration). Ask the administrator to correct it first']);
            }
            $paid = $this->payments((int)$h['ID']);
            $total = InvoiceMath::cents((string)$h['inclusiveVat']);
            $cents = InvoiceMath::cents($amount);
            if ($cents > $total - $paid['cents']) {
                throw new LedgerException(['Overpayment of invoice ' . $h['invoiceNo'] . ': the balance is ' . InvoiceMath::money(max($total - $paid['cents'],0)) . ', the payment is ' . InvoiceMath::money($cents)]);
            }

            $ref = $reference === '' ? null : strtolower($reference);
            $pdo->prepare('INSERT INTO tblinvoice_payments (invoice_Id,paymentDate,amount,paymentId,bankId,PaymentAccountId,paymentReference) VALUES(?,?,?,?,?,?,?)')
                ->execute([$h['ID'],$paydate,InvoiceMath::money($cents),$method,$bank,$account,$ref]);
            $tid = (int)$pdo->lastInsertId();
            $narr = 'Invoice ' . $h['invoiceNo'] . ' Payment';
            Ledger::post($pdo,[
                'congregationId' => $cong,
                'entryDate' => $paydate,
                'transactionTypeId' => self::TYPE_PAYMENT,
                'transactionId' => $tid,
                'reference' => $ref,
                'description' => $narr,
            ],[
                Ledger::debit($account,InvoiceMath::money($cents),$narr),
                Ledger::credit((int)$h['ReceivableAccountId'],InvoiceMath::money($cents),$narr),
            ]);
            Ledger::syncBankPostings($pdo,$cong,self::TYPE_PAYMENT,$tid,self::BANK_METHOD,$ref);

            $pdo->prepare('UPDATE tblinvoice_header SET `status` = ? WHERE ID = ? AND congregationId = ?')
                ->execute([$this->statusFor($total,$paid['cents'] + $cents),$h['ID'],$cong]);
            saveLog($pdo,$narr);
            $pdo->commit();
            return ['success' => true,'id' => $tid];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Something went wrong while saving the payment']];
        }
    }
}

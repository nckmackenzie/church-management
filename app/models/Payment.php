<?php

/**
 * Supplier payments (transaction type 7), task 8.7.
 *
 * One tblinvoice_payments_suppliers row and one type-7 entry (transactionId = the payment row ID) per paid invoice, all in
 * one transaction: Dr the payable account STORED ON THE INVOICE (decision 78, read here, never chosen on the form),
 * Cr the cash / bank account chosen on the form (decision 76: cash -> a PAY_CASH account, cheque / bank -> a PAY_BANK
 * account, stored in PaymentAccountId). A bank payment gets its tblbankpostings row (method Withdrawal); cash gets none.
 * There is no payment edit or delete (decision 2).
 */
class Payment
{
    const TYPE_PAYMENT = 7;
    const BANK_METHOD = 'Withdrawal';
    /** payment methods the form offers: cash, cheque, bank (tblpaymentmethods IDs) */
    const METHODS = [1, 3, 4];
    const MAX_ROWS = 200;

    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetPayments()
    {
        $sql = 'SELECT * FROM vw_supplierpayments2  WHERE congregationId = ?';
        return loadresultset($this->db->dbh,$sql,[(int)$_SESSION['congId']]);
    }

    /**
     * Invoices (and opening balances) with a balance, with the crediting account recorded on them. A row without one
     * (PayableAccountId NULL: it predates 8.7) is listed but cannot be paid.
     */
    public function GetPendingInvoices()
    {
        $sql = 'SELECT v.*, h.PayableAccountId, UCASE(a.accountType) AS payable
                FROM vw_supplier_with_bals v
                     JOIN tblinvoice_header_suppliers h ON h.ID = v.ID
                     LEFT JOIN tblaccounttypes a ON a.ID = h.PayableAccountId
                WHERE v.congregationId = ?';
        return loadresultset($this->db->dbh,$sql,[(int)$_SESSION['congId']]);
    }

    public function paymentMethods()
    {
        $in = implode(',',self::METHODS);
        return loadresultset($this->db->dbh,"SELECT ID, UCASE(paymentMethod) AS paymentMethod FROM tblpaymentmethods WHERE ID IN ($in) ORDER BY ID",[]);
    }

    public function getCashAccounts(): array
    {
        return array_map(function($a){ return (object)$a; },Ledger::listPaymentAccounts($this->db->dbh,(int)$_SESSION['congId'],Ledger::PAY_CASH));
    }

    public function getBanks(): array
    {
        return array_map(function($a){ return (object)$a; },Ledger::listPaymentAccounts($this->db->dbh,(int)$_SESSION['congId'],Ledger::PAY_BANK));
    }

    private function cents(string $s): int
    {
        $parts = explode('.',$s,2);
        return ((int)$parts[0]) * 100 + (int)str_pad(substr($parts[1] ?? '',0,2),2,'0');
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100,2,'.','');
    }

    private function scalar($v): ?string
    {
        return is_scalar($v) || $v === null ? trim((string)$v) : null;
    }

    /**
     * Save the payments of one batch. $data: paydate, paymethod, bank (PAY_BANK account for methods 3 / 4), cashaccount
     * (PAY_CASH account for method 1), payments (list of ['invoiceid','payment','cheque' (the reference)]).
     * Returns ['success'=>true,'paymentNo'=>n,'ids'=>[...]] or ['success'=>false,'errors'=>[...]].
     */
    public function Create(array $data): array
    {
        $pdo = $this->db->dbh;
        try {
            $cong = (int)$_SESSION['congId'];
            $errors = [];

            $paydate = $this->scalar($data['paydate'] ?? '');
            if ($paydate === null || !date_is_valid($paydate)) { $errors[] = 'Enter a valid payment date'; }
            elseif ($paydate > date('Y-m-d')) { $errors[] = 'Payment date cannot be greater than current date'; }
            elseif (($p = Ledger::dateProblem($pdo,$paydate)) !== null) { $errors[] = $p; }

            $method = $this->scalar($data['paymethod'] ?? '');
            if ($method === null || !ctype_digit($method) || !in_array((int)$method,self::METHODS,true)) { $errors[] = 'Select a valid payment method'; }
            $method = (int)$method;

            $account = 0;
            $bank = null;
            if ($method === 1) {
                $acc = $this->scalar($data['cashaccount'] ?? '');
                if ($acc === null || !ctype_digit($acc) || (int)$acc <= 0) { $errors[] = 'Select the cash account'; }
                else {
                    try { $account = Ledger::assertPaymentAccount($pdo,$cong,(int)$acc,Ledger::PAY_CASH); }
                    catch (LedgerException $e) { $errors = array_merge($errors,$e->getErrors()); }
                }
            } elseif ($method > 1) {
                $acc = $this->scalar($data['bank'] ?? '');
                if ($acc === null || !ctype_digit($acc) || (int)$acc <= 0) { $errors[] = 'Select Bank'; }
                else {
                    try { $account = $bank = Ledger::assertPaymentAccount($pdo,$cong,(int)$acc,Ledger::PAY_BANK); }
                    catch (LedgerException $e) { $errors = array_merge($errors,$e->getErrors()); }
                }
            }

            $rows = $data['payments'] ?? null;
            if (!is_array($rows) || !$rows) { $errors[] = 'No payments selected for payment'; $rows = []; }
            elseif (count($rows) > self::MAX_ROWS) { $errors[] = 'Too many payments in one batch'; }
            $clean = [];
            $seenInvoice = [];
            $seenRef = [];
            foreach (array_values($rows) as $i => $row) {
                $n = $i + 1;
                $row = (array)$row;
                $iid = $this->scalar($row['invoiceid'] ?? '');
                $amount = $this->scalar($row['payment'] ?? '');
                $ref = $this->scalar($row['cheque'] ?? '');
                if ($iid === null || !ctype_digit($iid) || (int)$iid <= 0) { $errors[] = "Payment $n: invoice not found"; continue; }
                if (isset($seenInvoice[(int)$iid])) { $errors[] = "Payment $n: the same invoice is selected twice"; continue; }
                $seenInvoice[(int)$iid] = true;
                if (($e = Ledger::amountError($amount,"Payment $n: amount")) !== null) { $errors[] = $e; continue; }
                if ($this->cents($amount) > 9999999999999) { $errors[] = "Payment $n: amount is too large"; continue; }
                if ($ref === null) { $errors[] = "Payment $n: invalid reference"; continue; }
                if ($method > 1 && $ref === '') { $errors[] = "Payment $n: payment reference not entered"; }
                if (strlen($ref) > 50) { $errors[] = "Payment $n: reference cannot be longer than 50 characters"; }
                if ($ref !== '') {
                    if (isset($seenRef[strtolower($ref)])) { $errors[] = 'One or more cheques entered more than once'; }
                    $seenRef[strtolower($ref)] = true;
                }
                $clean[] = ['invoice' => (int)$iid,'cents' => $this->cents($amount),'ref' => $ref === '' ? null : strtolower($ref)];
            }
            if ($errors) { throw new LedgerException(array_values(array_unique($errors))); }

            usort($clean,function($a,$b){ return $a['invoice'] <=> $b['invoice']; }); // fixed lock order
            $pdo->beginTransaction();
            $paymentNo = Ledger::nextNumber($pdo,$cong,Ledger::SEQ_SUPPLIER_PAYMENT);

            $insert = $pdo->prepare('INSERT INTO tblinvoice_payments_suppliers (paymentNo,paymentNo2,invoice_Id,paymentDate,amount,paymentId,bankId,PaymentAccountId,paymentReference)
                                     VALUES(?,?,?,?,?,?,?,?,?)');
            $ids = [];
            foreach ($clean as $c) {
                $st = $pdo->prepare('SELECT h.*, s.supplierName FROM tblinvoice_header_suppliers h JOIN tblsuppliers s ON s.ID = h.supplierId
                                     WHERE h.ID = ? AND h.congregationId = ? AND h.deleted = 0 AND s.deleted = 0 FOR UPDATE');
                $st->execute([$c['invoice'],$cong]);
                $h = $st->fetch(PDO::FETCH_ASSOC);
                if (!$h) { throw new LedgerException(['Invoice ' . $c['invoice'] . ' not found for this congregation']); }
                $label = $h['invoiceNo'] === null ? 'the opening balance of ' . strtoupper($h['supplierName']) : 'invoice ' . $h['invoiceNo'];
                if ($h['PayableAccountId'] === null) {
                    throw new LedgerException(["Cannot pay $label: it has no crediting account recorded (it was entered before the ledger migration). Ask the administrator to correct it first"]);
                }

                $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM tblinvoice_payments_suppliers WHERE invoice_Id = ? AND Deleted = 0');
                $st->execute([$h['ID']]);
                $paid = $this->cents(number_format((float)$st->fetchColumn(),2,'.',''));
                $total = $this->cents((string)$h['inclusiveVat']);
                $balance = $total - $paid;
                if ($c['cents'] > $balance) {
                    throw new LedgerException(["Overpayment of $label: the balance is " . $this->money(max($balance,0)) . ', the payment is ' . $this->money($c['cents'])]);
                }

                $insert->execute([$paymentNo,$paymentNo,$h['ID'],$paydate,$this->money($c['cents']),$method,$bank,$account,$c['ref']]);
                $tid = (int)$pdo->lastInsertId();
                $narr = 'Payment of ' . $label;
                Ledger::post($pdo,[
                    'congregationId' => $cong,
                    'entryDate' => $paydate,
                    'transactionTypeId' => self::TYPE_PAYMENT,
                    'transactionId' => $tid,
                    'reference' => $c['ref'],
                    'description' => $narr,
                ],[
                    Ledger::debit((int)$h['PayableAccountId'],$this->money($c['cents']),$narr),
                    Ledger::credit($account,$this->money($c['cents']),$narr),
                ]);
                Ledger::syncBankPostings($pdo,$cong,self::TYPE_PAYMENT,$tid,self::BANK_METHOD,$c['ref']);

                $status = $paid + $c['cents'] >= $total ? 2 : 1;
                $pdo->prepare('UPDATE tblinvoice_header_suppliers SET `status` = ? WHERE ID = ? AND congregationId = ?')->execute([$status,$h['ID'],$cong]);
                $ids[] = $tid;
            }
            saveLog($pdo,'Supplier payment no ' . $paymentNo);
            $pdo->commit();
            return ['success' => true,'paymentNo' => $paymentNo,'ids' => $ids];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Couldnt save selected payment(s)! Retry or contact admin']];
        }
    }

    // -----------------------------------------------------------------
    // Voucher (print) - always through the payment's invoice, so only the session congregation's payments load
    // -----------------------------------------------------------------

    /** A payment of this congregation (with its supplier), or false. */
    public function PaymentDetails($id)
    {
        if (!ctype_digit((string)$id) || (int)$id <= 0) { return false; }
        $sql = 'SELECT p.*, h.supplierId, COALESCE(p.PaymentAccountId, p.bankId) AS moneyAccountId
                FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON p.invoice_Id = h.ID
                WHERE p.ID = ? AND h.congregationId = ? AND p.Deleted = 0';
        return loadsingleset($this->db->dbh,$sql,[(int)$id,(int)$_SESSION['congId']]);
    }

    public function GetSupplierDetails($id)
    {
        $this->db->query('SELECT * FROM tblsuppliers WHERE ID = :id AND congregationId = :cid');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    /** Name of the cash / bank account a payment used. */
    public function GetMoneyAccount($id)
    {
        return getdbvalue($this->db->dbh,"SELECT UCASE(CONCAT(accountType,IF(accountNo IS NULL OR accountNo = '','',CONCAT('-',accountNo)))) FROM tblaccounttypes WHERE ID = ?",[(int)$id]);
    }

    public function GetInvoicedetails($payno,$supplier)
    {
        return loadresultset($this->db->dbh,'CALL sp_getinvoicepaymentdetails(?,?)',[(int)$payno,(int)$supplier]);
    }

    public function GetPaymentSupplierValue($payno,$supplier)
    {
        $sql = 'SELECT
                    IFNULL(SUM(amount),0) AS SumOfAmount
                FROM `tblinvoice_payments_suppliers` p
                    join tblinvoice_header_suppliers s on p.invoice_Id = s.ID
                WHERE p.paymentNo = ? AND s.supplierId = ? AND s.congregationId = ? AND p.Deleted = 0';
        return getdbvalue($this->db->dbh,$sql,[(int)$payno,(int)$supplier,(int)$_SESSION['congId']]);
    }
}
